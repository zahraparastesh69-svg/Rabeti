<?php

declare(strict_types=1);

/* ═══════════════════════════════════════════════════════════════════
 *  RABETI — مدیریت سفارش‌ها (ادمین)  |  v2 | 2026-10-23
 *
 *  تغییرات نسبت به نسخه قبل:
 *    • ⭐ غیرفعال کردن <select> + <button> تا تایید مالی
 *      (فقط برای status = pending_review با paid_total < total)
 *    • ⭐ محاسبه $canAdvance قبل از فرم (قبلاً بعد از select بود → بی‌اثر)
 *    • ⭐ چک سرور در POST — حتی با DevTools نمی‌توان دور زد
 *    • ⭐ کوئری: LEFT JOIN invoices برای invoice_total/invoice_paid_total
 *    • بهینه‌سازی: helper توابع، پیام‌های استاندارد
 * ═══════════════════════════════════════════════════════════════════ */

/* ─── Bootstrap ─── */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) {
    if (is_file($__b)) {
        require_once $__b;
        break;
    }
}
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/OrderSmsService.php';
require_once ROOT_PATH . '/app/services/SmsService.php';
require_once ROOT_PATH . '/config/settings.php';

require_admin();

$db      = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ─── Helper ─── */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ═══════════════════════════════════════════════════════════════════
 *  وضعیت‌های مجاز سفارش (دست نخورد)
 * ═══════════════════════════════════════════════════════════════════ */

$allowedStatuses = [
    'pending_review',
    'processing',
    'packed',
    'shipped-pik',
    'shipped-post',
    'shipped',
    'delivered',
    'cancelled',
];

$statusLabels = [
    'pending_review' => 'در انتظار تائید واحد مالی',
    'processing'     => 'ارسال درخواست به انبار',
    'packed'         => 'بسته بندی و آماده تحویل',
    'shipped-pik'    => 'تحویل پیک جهت ارسال',
    'shipped-post'   => 'تحویل پست جهت ارسال',
    'shipped'        => 'ارسال شد',
    'delivered'      => 'تحویل شد',
    'cancelled'      => 'لغو شده',
];

/* ─── وضعیت‌هایی که SMS ارسال می‌کنند ─── */
$shippingSmsStatuses = ['packed', 'shipped-pik', 'shipped-post'];

/* ═══════════════════════════════════════════════════════════════════
 *  POST — تغییر وضعیت سفارش
 * ═══════════════════════════════════════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $id = ($id !== false && $id !== null) ? (int)$id : 0;

    $newStatus = trim((string)($_POST['status'] ?? ''));

    /* اعتبارسنجی اولیه */
    if ($id <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        header('Location: /admin/orders');
        exit;
    }

    /* خواندن سفارش فعلی */
    $orderStmt = $db->prepare("
        SELECT id, status, total
        FROM orders
        WHERE id = ?
        LIMIT 1
    ");
    $orderStmt->execute([$id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: /admin/orders');
        exit;
    }

    $oldStatus = (string)$order['status'];

    /* ═══ ⭐ چک سرور: اگر pending_review → مرحله بعد، مالی باید تایید کرده باشد ═══ */
    if (
        $oldStatus === 'pending_review'
        && $newStatus !== 'pending_review'
        && $newStatus !== 'cancelled'
    ) {
        $invStmt = $db->prepare("
            SELECT total, paid_total
            FROM invoices
            WHERE order_id = ?
            LIMIT 1
        ");
        $invStmt->execute([$id]);
        $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

        if ($inv) {
            $invTotal = (int)($inv['total'] ?? 0);
            $invPaid  = (int)($inv['paid_total'] ?? 0);

            /* اگر فاکتور مبلغ دارد ولی تسویه نشده → رد */
            if ($invTotal > 0 && $invPaid < $invTotal) {
                $_SESSION['flash_error'] = 'در انتظار تائید واحد مالی — تا تسویه نشده امکان ثبت مرحله بعد نیست';
                header('Location: /admin/orders');
                exit;
            }
        }
    }

    /* اگر تغییری نکرده، کاری نکن */
    if ($oldStatus === $newStatus) {
        header('Location: /admin/orders');
        exit;
    }

    $shouldSendShippingSms = in_array($newStatus, $shippingSmsStatuses, true);

    $db->beginTransaction();
    try {
        /* ─── تغییر وضعیت ─── */
        $update = $db->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
              AND status = ?
        ");
        $update->execute([$newStatus, $id, $oldStatus]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Order status changed by another request.');
        }

        /* ─── آزادسازی رزرو در صورت لغو ─── */
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $release = $db->prepare("
                UPDATE inventory inv
                JOIN order_items oi ON oi.product_id = inv.product_id
                SET inv.reserved = GREATEST(0, inv.reserved - oi.qty_reserved)
                WHERE oi.order_id = ?
            ");
            $release->execute([$id]);

            $items = $db->prepare("
                SELECT product_id, qty_reserved
                FROM order_items
                WHERE order_id = ? AND qty_reserved > 0
            ");
            $items->execute([$id]);

            foreach ($items as $it) {
                $db->prepare("
                    INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type, reference_id,
                     order_id, order_item_id, reason, created_by, ip)
                    VALUES (?, 'release', ?, 'order', NULL, ?, NULL, ?, ?, ?)
                ")->execute([
                    (int)$it['product_id'],
                    (int)$it['qty_reserved'],
                    $id,
                    'آزادسازی رزرو به‌دلیل لغو سفارش',
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]);
            }
        }

        /* ─── Audit ─── */
        $db->prepare("
            INSERT INTO audit_logs
            (user_id, action, entity, entity_id, ip)
            VALUES (?, ?, 'order', ?, ?)
        ")->execute([
            $adminId,
            'order.status.' . $newStatus,
            $id,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ORDER STATUS UPDATE FAILED: ' . $e->getMessage());
        header('Location: /admin/orders');
        exit;
    }

    /* ─── SMS (بعد از commit — خطای SMS rollback نمی‌کند) ─── */
    if ($shouldSendShippingSms) {
        try {
            OrderSmsService::orderShipping($id);
        } catch (Throwable $e) {
            error_log('ORDER SHIPPING SMS FAILED: ' . $e->getMessage());
        }
    }

    /* ─── PRG ─── */
    header('Location: /admin/orders');
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
 *  فیلتر وضعیت
 *
 *  پیش‌فرض: «در انتظار تائید» — تا با شمارندهٔ badge سایدبار (پندینگ)
 *  هماهنگ باشد. کاربر با کلیک روی «همه» می‌تواند کل لیست را ببیند.
 * ═══════════════════════════════════════════════════════════════════ */

$hasStatusParam = array_key_exists('status', $_GET);
$filter = trim((string)($_GET['status'] ?? ''));

// اگر پارامتر status اصلاً ارسال نشده، پیش‌فرض را روی pending_review می‌گذاریم.
// (مقدار خالیِ ارسال‌شده = درخواست نمایش «همه».)
if (!$hasStatusParam) {
    $filter = 'pending_review';
}

$where  = '';
$params = [];

if ($filter !== '' && in_array($filter, $allowedStatuses, true)) {
    $where    = " WHERE o.status = ? ";
    $params[] = $filter;
} elseif ($filter === 'pending') {
    // میانبر انسانی: ?status=pending → همان pending_review
    $where    = " WHERE o.status = ? ";
    $params[] = 'pending_review';
    $filter   = 'pending_review';
}

/* ═══════════════════════════════════════════════════════════════════
 *  لیست سفارش‌ها + اطلاعات فاکتور
 * ═══════════════════════════════════════════════════════════════════ */

$stmt = $db->prepare("
    SELECT
        o.*,
        u.full_name AS customer_name,
        u.mobile,
        c.name AS company_name,
        (
            SELECT SUM(qty)
            FROM order_items
            WHERE order_id = o.id
        ) AS total_qty,
        i.total      AS invoice_total,
        i.paid_total AS invoice_paid_total,
        i.status     AS invoice_status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN companies c ON c.id = o.company_id
    LEFT JOIN invoices i  ON i.order_id = o.id
    {$where}
    ORDER BY o.id DESC
    LIMIT 100
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════════════════════════════════
 *  شمارنده‌ها
 * ═══════════════════════════════════════════════════════════════════ */

$all     = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending_review'")->fetchColumn();

/* ═══════════════════════════════════════════════════════════════════
 *  Layout
 * ═══════════════════════════════════════════════════════════════════ */

$activeMenu = 'orders';
$pageTitle  = 'سفارش‌ها';

ob_start();
?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="form-alert error">
        ⚠️ <?= $h($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- ═══ هدر صفحه ═══ -->
<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مدیریت فروش</span>
        <h1>سفارش‌ها</h1>
        <p>لیست سفارش‌های ثبت‌شده و تغییر وضعیت آن‌ها. پیش‌فرض روی «در انتظار تائید» است.</p>
    </div>
</div>

<!-- ═══ فیلترهای وضعیت ═══ -->
<div class="filter-tabs" role="tablist" aria-label="فیلتر وضعیت سفارش">

    <a href="/admin/orders?status="
        class="filter-tab <?= $filter === '' ? 'is-active' : '' ?>"
        role="tab" aria-selected="<?= $filter === '' ? 'true' : 'false' ?>">
        همه <span class="filter-tab-count"><?= $all ?></span>
    </a>

    <a href="/admin/orders"
        class="filter-tab filter-tab--accent <?= $filter === 'pending_review' ? 'is-active' : '' ?>"
        role="tab" aria-selected="<?= $filter === 'pending_review' ? 'true' : 'false' ?>">
        در انتظار تائید <span class="filter-tab-count"><?= $pending ?></span>
    </a>

    <?php foreach (['processing', 'packed', 'shipped-pik', 'shipped-post', 'shipped', 'delivered', 'cancelled'] as $s): ?>
        <a href="/admin/orders?status=<?= urlencode($s) ?>"
            class="filter-tab <?= $filter === $s ? 'is-active' : '' ?>"
            role="tab" aria-selected="<?= $filter === $s ? 'true' : 'false' ?>">
            <?= $h($statusLabels[$s]) ?>
        </a>
    <?php endforeach; ?>

</div>

<?php if ($orders): ?>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>شماره</th>
                    <th>مشتری / شرکت</th>
                    <th>اقلام</th>
                    <th>مبلغ کل</th>
                    <th>ارسال به</th>
                    <th>تاریخ</th>
                    <th class="col-status">وضعیت ← تغییر</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($orders as $o): ?>

                    <?php
                    $currentStatus = (string)$o['status'];
                    $statusLabel   = $statusLabels[$currentStatus] ?? $currentStatus;
                    $rowDanger     = $currentStatus === 'pending_review' ? 'row-warn' : '';

                    /* ⭐ محاسبه $canAdvance — قبل از فرم */
                    $canAdvance     = true;
                    $disabledReason = '';

                    if ($currentStatus === 'pending_review') {
                        $paidTotal = (int)($o['invoice_paid_total'] ?? 0);
                        $total     = (int)($o['invoice_total'] ?? $o['total'] ?? 0);

                        if ($total > 0 && $paidTotal < $total) {
                            $canAdvance     = false;
                            $disabledReason = 'در انتظار تائید واحد مالی — تا تسویه نشده امکان ثبت مرحله بعد نیست';
                        }
                    }

                    $disabledAttr = $canAdvance
                        ? ''
                        : 'disabled aria-disabled="true" title="' . $h($disabledReason) . '"';
                    $disabledCls = $canAdvance ? '' : ' is-disabled';
                    ?>

                    <tr class="<?= $rowDanger ?>">

                        <!-- شماره سفارش -->
                        <td>
                            <a dir="ltr" class="admin-link" href="/admin/order-detail?id=<?= (int)$o['id'] ?>">
                                <b><?= $h($o['order_number']) ?></b>
                            </a>
                        </td>

                        <!-- مشتری -->
                        <td>
                            <b><?= $h($o['customer_name'] ?? '') ?></b><br>
                            <span class="td-muted">
                                <?= $h($o['company_name'] ?? 'بدون شرکت') ?>
                            </span>
                        </td>

                        <!-- اقلام -->
                        <td><?= number_format((int)$o['total_qty']) ?> واحد</td>

                        <!-- مبلغ -->
                        <td>
                            <b class="td-amount">
                                <?= number_format((float)$o['total']) ?>
                            </b> ت
                        </td>

                        <!-- ارسال به -->
                        <td>
                            <b><?= $h($o['shipping_name'] ?? '') ?></b><br>
                            <span class="td-muted"><?= $h($o['shipping_city'] ?? '') ?></span>
                        </td>

                        <!-- تاریخ -->
                        <td class="td-muted td-nowrap">
                            <?= jdate_fa($o['created_at'], true) ?>
                        </td>

                        <!-- وضعیت + فرم -->
                        <td class="col-status">

                            <span class="st-badge st-new"><?= $h($statusLabel) ?></span>

                            <?php if (!$canAdvance): ?>
                                <div class="status-lock">
                                    🔒 <?= $h($disabledReason) ?>
                                </div>
                            <?php endif; ?>

                            <br>

                            <!-- ═══ فرم تغییر وضعیت ═══ -->
                            <form method="post" action="/admin/orders" class="status-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">

                                <select name="status" class="status-select<?= $disabledCls ?>"
                                    <?= $disabledAttr ?>>
                                    <?php foreach ($statusLabels as $key => $lbl): ?>
                                        <option value="<?= $h($key) ?>"
                                            <?= $currentStatus === $key ? 'selected' : '' ?>>
                                            <?= $h($lbl) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" class="btn btn-orange btn-sm<?= $disabledCls ?>"
                                    <?= $disabledAttr ?>>
                                    ثبت
                                </button>
                            </form>

                            <!-- ═══ فرم لغو ═══ -->
                            <?php if ($currentStatus !== 'cancelled'): ?>
                                <form method="post" action="/admin/orders" class="status-form cancel-form"
                                    onsubmit="return confirm('سفارش لغو شود؟ موجودی انبار برمی‌گردد.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-sm btn-cancel">
                                        لغو سفارش
                                    </button>
                                </form>
                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

<?php else: ?>

    <div class="empty-state">
        <h2>سفارشی با این فیلتر نیست</h2>
        <p>می‌توانید فیلتر دیگری را امتحان کنید یا همهٔ سفارش‌ها را ببینید.</p>
    </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
