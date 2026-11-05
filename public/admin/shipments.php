<?php
/* Auto-detect bootstrap (local + host) */
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
require_once ROOT_PATH . '/app/services/ShippingReturnsService.php';

require_admin();

$db       = db();
$adminId  = (int)$_SESSION['admin_id'];
$flash    = '';
$flashErr = '';

/* ─── POST handlers ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = $_POST['do'] ?? '';

    try {
        /* ۱) ثبت ارسال سفارش */
        if ($do === 'ship_order') {
            $orderId      = (int)($_POST['order_id'] ?? 0);
            $carrier      = trim((string)($_POST['carrier'] ?? ''));
            $trackingCode = trim((string)($_POST['tracking_code'] ?? ''));
            $shippedAt    = trim((string)($_POST['shipped_at'] ?? ''));
            if ($shippedAt === '') $shippedAt = null;

            if ($orderId < 1) {
                throw new InvalidArgumentException('شناسه سفارش نامعتبر');
            }

            ShippingReturnsService::shipOrder($db, $orderId, [
                'carrier'      => $carrier,
                'tracking_code' => $trackingCode,
                'shipped_at'    => $shippedAt,
            ], $adminId);

            $flash = "سفارش #{$orderId} ارسال شد.";
        }

        /* ۲) ثبت تحویل به مشتری */
        if ($do === 'deliver_order') {
            $orderId     = (int)($_POST['order_id'] ?? 0);
            $deliveredAt = trim((string)($_POST['delivered_at'] ?? ''));
            if ($deliveredAt === '') $deliveredAt = null;

            if ($orderId < 1) {
                throw new InvalidArgumentException('شناسه سفارش نامعتبر');
            }

            ShippingReturnsService::deliverOrder($db, $orderId, $deliveredAt, $adminId);
            $flash = "سفارش #{$orderId} تحویل داده شد.";
        }

        /* ۳) شکست ارسال */
        if ($do === 'fail_shipment') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $reason  = trim((string)($_POST['reason'] ?? ''));
            if ($orderId < 1) throw new InvalidArgumentException('شناسه سفارش نامعتبر');
            if (mb_strlen($reason) < 3) throw new InvalidArgumentException('دلیل شکست ارسال الزامی است');

            ShippingReturnsService::failShipment($db, $orderId, $reason, $adminId);
            $flash = "وضعیت سفارش #{$orderId} به «شکست در ارسال» به‌روزرسانی شد.";
        }

        /* ۴) ثبت درخواست بازگشت */
        if ($do === 'return_request') {
            $refundId = (int)($_POST['refund_id'] ?? 0);
            $reason   = trim((string)($_POST['reason'] ?? ''));
            if ($refundId < 1) throw new InvalidArgumentException('شناسه درخواست نامعتبر');
            if (mb_strlen($reason) < 3) throw new InvalidArgumentException('دلیل الزامی است');

            ShippingReturnsService::markReturnRequested($db, $refundId, $reason, $adminId);
            $flash = "درخواست بازگشت #{$refundId} ثبت شد.";
        }

        /* ۵) دریافت کالای برگشتی */
        if ($do === 'return_received') {
            $refundId = (int)($_POST['refund_id'] ?? 0);
            $qty       = (int)($_POST['qty'] ?? 0);
            $sellable  = ($_POST['sellable'] ?? '') === '1';

            if ($refundId < 1) throw new InvalidArgumentException('شناسه درخواست نامعتبر');
            if ($qty < 1) throw new InvalidArgumentException('مقدار باید مثبت باشد');

            ShippingReturnsService::markReturnReceived($db, $refundId, $qty, $sellable, $adminId);
            $flash = "کالای برگشتی #{$refundId} در انبار ثبت شد (" . ($sellable ? 'قابل فروش' : 'غیرقابل فروش') . ").";
        }

        /* ۶) رد درخواست بازگشت */
        if ($do === 'reject_return') {
            $refundId = (int)($_POST['refund_id'] ?? 0);
            $reason   = trim((string)($_POST['reason'] ?? ''));
            if ($refundId < 1) throw new InvalidArgumentException('شناسه درخواست نامعتبر');
            if (mb_strlen($reason) < 3) throw new InvalidArgumentException('دلیل الزامی است');

            ShippingReturnsService::rejectReturn($db, $refundId, $reason, $adminId);
            $flash = "درخواست بازگشت #{$refundId} رد شد.";
        }
    } catch (Throwable $e) {
        $flashErr = $e->getMessage();
    }
}

/* ─── فیلترها ─── */
$filterStatus = trim((string)($_GET['status'] ?? ''));
$filterFrom   = trim((string)($_GET['from'] ?? ''));
$filterTo     = trim((string)($_GET['to'] ?? ''));
$export       = ($_GET['export'] ?? '') === 'csv';

$conditions = [];
$params = [];

if ($filterStatus !== '') {
    $conditions[] = 'o.shipping_status = ?';
    $params[] = $filterStatus;
}
if ($filterFrom !== '') {
    $conditions[] = 's.created_at >= ?';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $conditions[] = 's.created_at <= ?';
    $params[] = $filterTo . ' 23:59:59';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* ─── لیست سفارشات با shipment ─── */
$shipments = $db->prepare("
    SELECT o.id, o.order_number, o.status, o.shipping_status, o.shipping_method, o.total,
           o.created_at,
           u.full_name AS customer_name, u.mobile AS customer_mobile,
           s.method AS ship_method, s.fee AS ship_fee, s.delivery_date, s.slot_label,
           s.tracking_code, s.carrier, s.shipped_at, s.delivered_at, s.return_reason,
           s.ship_city, s.ship_address
    FROM orders o
    INNER JOIN order_shipments s ON s.order_id = o.id
    LEFT JOIN users u ON u.id = o.user_id
    $where
    ORDER BY
        CASE o.shipping_status
            WHEN 'ready' THEN 1
            WHEN 'packing' THEN 2
            WHEN 'in_transit' THEN 3
            WHEN 'failed' THEN 4
            WHEN 'returned' THEN 5
            ELSE 9
        END,
        o.id DESC
    LIMIT 200
");
$shipments->execute($params);
$shipments = $shipments->fetchAll();

/* ─── لیست درخواست‌های بازگشت ─── */
$returns = $db->prepare("
    SELECT r.id, r.invoice_id, r.user_id, r.amount, r.sheba, r.account_holder,
           r.status AS refund_status, r.admin_note, r.paid_at, r.created_at,
           r.return_reason, r.return_status, r.return_received_at, r.return_qty,
           r.order_item_id,
           u.full_name AS customer_name, u.mobile AS customer_mobile,
           i.invoice_number, i.total AS invoice_total, i.paid_total,
           o.order_number, o.id AS order_id
    FROM refund_requests r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN invoices i ON i.id = r.invoice_id
    LEFT JOIN orders o ON o.id = i.order_id
    ORDER BY
        CASE r.return_status
            WHEN 'requested' THEN 1
            WHEN 'in_transit' THEN 2
            ELSE 9
        END,
        r.id DESC
    LIMIT 100
");
$returns->execute([]);
$returns = $returns->fetchAll();

$activeMenu = 'shipments';
$pageTitle  = 'ارسال و مرجوعی';

ob_start();
?>

<?php if ($flash): ?>
    <div class="alert alert-success" style="background:#e8f5ee;color:#1a7f4b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✓ <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>
<?php if ($flashErr): ?>
    <div class="alert alert-error" style="background:#fff0f0;color:#c62828;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✗ <?= htmlspecialchars($flashErr) ?>
    </div>
<?php endif; ?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">عملیات</span>
        <h1>ارسال و مرجوعی</h1>
        <p>مدیریت کامل ارسال کالا و مرجوعی‌ها در یک پنل.</p>
    </div>
</div>

<!-- ====================================================
     فیلتر ارسال‌ها
==================================================== -->
<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:flex-end;">
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">وضعیت ارسال:</label>
        <select name="status" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <option value="not_ready" <?= $filterStatus === 'not_ready' ? 'selected' : '' ?>>آماده ارسال نیست</option>
            <option value="ready" <?= $filterStatus === 'ready' ? 'selected' : '' ?>>آماده ارسال</option>
            <option value="packing" <?= $filterStatus === 'packing' ? 'selected' : '' ?>>در حال بسته‌بندی</option>
            <option value="in_transit" <?= $filterStatus === 'in_transit' ? 'selected' : '' ?>>در مسیر</option>
            <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>تحویل شده</option>
            <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>شکست</option>
            <option value="returned" <?= $filterStatus === 'returned' ? 'selected' : '' ?>>برگشتی</option>
        </select>
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">از:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">تا:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <button type="submit" style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:6px;cursor:pointer;">
        فیلتر
    </button>
</form>

<!-- ====================================================
     جدول ارسال‌ها
==================================================== -->
<div class="admin-section-head" style="margin-top:24px;">
    <div>
        <span class="admin-kicker">سفارش‌ها</span>
        <h2>ارسال‌های جاری (<?= count($shipments) ?>)</h2>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>سفارش</th>
                <th>مشتری</th>
                <th>روش</th>
                <th>کد ردیابی</th>
                <th>شرکت حمل</th>
                <th>وضعیت</th>
                <th>تاریخ ارسال</th>
                <th>تاریخ تحویل</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shipments)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:24px;color:#999;">هیچ ارسالی یافت نشد.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($shipments as $s): ?>
                    <tr>
                        <td>
                            <a href="/admin/order-detail?id=<?= (int)$s['id'] ?>" style="color:var(--teal-700);font-weight:700;">
                                <?= htmlspecialchars($s['order_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($s['customer_name']) ?><br>
                            <span dir="ltr" style="font-size:.7rem;color:#999;"><?= htmlspecialchars($s['customer_mobile']) ?></span>
                        </td>
                        <td>
                            <?= ['peyk' => 'پیک', 'post' => 'پست', 'in_person' => 'حضوری'][$s['ship_method'] ?? 'post'] ?? $s['ship_method'] ?>
                        </td>
                        <td dir="ltr" style="text-align:right;font-size:.75rem;">
                            <?= $s['tracking_code'] ? htmlspecialchars($s['tracking_code']) : '<span style="color:#ccc;">—</span>' ?>
                        </td>
                        <td style="font-size:.75rem;">
                            <?= $s['carrier'] ? htmlspecialchars($s['carrier']) : '<span style="color:#ccc;">—</span>' ?>
                        </td>
                        <td>
                            <?php
                            $shippingStatusLabels = [
                                'not_ready'    => 'آماده نیست',
                                'ready'        => 'آماده ارسال',
                                'packing'      => 'بسته‌بندی',
                                'in_transit'   => 'در مسیر',
                                'delivered'    => 'تحویل شده',
                                'failed'       => 'شکست',
                                'returned'     => 'برگشتی',
                            ];
                            $cssClass = [
                                'not_ready'    => 'st-new',
                                'ready'        => 'st-quoted',
                                'packing'      => 'st-quoted',
                                'in_transit'   => 'st-converted',
                                'delivered'    => 'st-converted',
                                'failed'       => 'st-rejected',
                                'returned'     => 'st-rejected',
                            ];
                            $label = $shippingStatusLabels[$s['shipping_status']] ?? $s['shipping_status'];
                            $cls = $cssClass[$s['shipping_status']] ?? 'st-new';
                            ?>
                            <span class="st-badge <?= $cls ?>"><?= $label ?></span>
                        </td>
                        <td class="td-muted td-nowrap"><?= jdate_fa($s['shipped_at'] ?? null) ?></td>
                        <td class="td-muted td-nowrap"><?= jdate_fa($s['delivered_at'] ?? null) ?></td>
                        <td>
                            <?php if (in_array($s['shipping_status'], ['ready', 'packing'], true)): ?>
                                <button type="button"
                                    onclick="document.getElementById('ship-modal-<?= (int)$s['id'] ?>').showModal()"
                                    style="padding:5px 10px;font-size:.72rem;background:#e8f5ee;color:#1a7f4b;border:none;border-radius:4px;cursor:pointer;">
                                    📦 ثبت ارسال
                                </button>

                                <dialog id="ship-modal-<?= (int)$s['id'] ?>" style="max-width:480px;border-radius:10px;border:1px solid #ccc;padding:20px;">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="ship_order">
                                        <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                        <h3 style="margin:0 0 12px;">ثبت ارسال: <?= htmlspecialchars($s['order_number']) ?></h3>

                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">شرکت حمل:</label>
                                        <input type="text" name="carrier" placeholder="مثلاً: پست ایران، تیپاکس، چاپار"
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;">

                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">کد ردیابی:</label>
                                        <input type="text" name="tracking_code" dir="ltr"
                                            placeholder="کد رهگیری پست/پیک"
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;">

                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">زمان ارسال (اختیاری):</label>
                                        <input type="datetime-local" name="shipped_at"
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:14px;">

                                        <div style="text-align:left;">
                                            <button type="button" onclick="document.getElementById('ship-modal-<?= (int)$s['id'] ?>').close()"
                                                style="padding:6px 12px;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">انصراف</button>
                                            <button type="submit"
                                                style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                                ثبت ارسال
                                            </button>
                                        </div>
                                    </form>
                                </dialog>
                            <?php elseif ($s['shipping_status'] === 'in_transit'): ?>
                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="deliver_order">
                                    <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit"
                                        style="padding:5px 10px;font-size:.72rem;background:#e8f5ee;color:#1a7f4b;border:none;border-radius:4px;cursor:pointer;"
                                        onclick="return confirm('تأیید تحویل به مشتری؟');">
                                        ✓ تحویل
                                    </button>
                                </form>

                                <button type="button"
                                    onclick="document.getElementById('fail-modal-<?= (int)$s['id'] ?>').showModal()"
                                    style="padding:5px 10px;font-size:.72rem;background:#fff0f0;color:#c62828;border:none;border-radius:4px;cursor:pointer;">
                                    ✗ شکست
                                </button>

                                <dialog id="fail-modal-<?= (int)$s['id'] ?>" style="max-width:480px;border-radius:10px;border:1px solid #ccc;padding:20px;">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="fail_shipment">
                                        <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                        <h3 style="margin:0 0 12px;">شکست ارسال: <?= htmlspecialchars($s['order_number']) ?></h3>
                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">دلیل (الزامی):</label>
                                        <textarea name="reason" required rows="3"
                                            placeholder="مثلاً: گم‌شدگی محموله، رد توسط مشتری، ..."
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:14px;"></textarea>
                                        <div style="text-align:left;">
                                            <button type="button" onclick="document.getElementById('fail-modal-<?= (int)$s['id'] ?>').close()"
                                                style="padding:6px 12px;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">انصراف</button>
                                            <button type="submit" style="padding:6px 14px;background:#c62828;color:#fff;border:none;border-radius:4px;cursor:pointer;">ثبت</button>
                                        </div>
                                    </form>
                                </dialog>
                            <?php else: ?>
                                <a href="/admin/order-detail?id=<?= (int)$s['id'] ?>" style="color:var(--teal-700);font-size:.72rem;">
                                    جزئیات ←
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ====================================================
     جدول مرجوعی‌ها
==================================================== -->
<div class="admin-section-head" style="margin-top:32px;">
    <div>
        <span class="admin-kicker">مرجوعی</span>
        <h2>درخواست‌های بازگشت کالا (<?= count($returns) ?>)</h2>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>مشتری</th>
                <th>فاکتور/سفارش</th>
                <th>مبلغ</th>
                <th>وضعیت استرداد</th>
                <th>وضعیت بازگشت کالا</th>
                <th>تعداد برگشتی</th>
                <th>تاریخ دریافت</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($returns)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:24px;color:#999;">هیچ درخواست بازگشتی یافت نشد.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($returns as $r): ?>
                    <tr>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($r['customer_name']) ?><br>
                            <span dir="ltr" style="font-size:.7rem;color:#999;"><?= htmlspecialchars($r['customer_mobile']) ?></span>
                        </td>
                        <td>
                            <?php if ($r['order_id']): ?>
                                <a href="/admin/order-detail?id=<?= (int)$r['order_id'] ?>" style="color:var(--teal-700);">
                                    <?= htmlspecialchars($r['order_number']) ?>
                                </a><br>
                            <?php endif; ?>
                            <span dir="ltr" style="font-size:.7rem;color:#999;"><?= htmlspecialchars($r['invoice_number']) ?></span>
                        </td>
                        <td><?= number_format((int)$r['amount']) ?> ت</td>
                        <td>
                            <?php
                            $refundStatusLabels = [
                                'pending' => 'در انتظار',
                                'paid'    => 'پرداخت شد',
                                'rejected' => 'رد شد',
                            ];
                            $cls = ['pending' => 'st-new', 'paid' => 'st-converted', 'rejected' => 'st-rejected'][$r['refund_status']] ?? 'st-new';
                            ?>
                            <span class="st-badge <?= $cls ?>">
                                <?= $refundStatusLabels[$r['refund_status']] ?? $r['refund_status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $returnStatusLabels = [
                                'not_requested'         => 'درخواست نشده',
                                'requested'              => 'درخواست‌شده',
                                'in_transit'             => 'در راه',
                                'received_sellable'     => 'دریافت (قابل فروش)',
                                'received_unsellable'   => 'دریافت (غیرقابل فروش)',
                                'rejected'               => 'رد شده',
                            ];
                            $cls = [
                                'not_requested'         => 'st-new',
                                'requested'              => 'st-quoted',
                                'in_transit'             => 'st-quoted',
                                'received_sellable'     => 'st-converted',
                                'received_unsellable'   => 'st-rejected',
                                'rejected'               => 'st-rejected',
                            ][$r['return_status']] ?? 'st-new';
                            ?>
                            <span class="st-badge <?= $cls ?>">
                                <?= $returnStatusLabels[$r['return_status']] ?? $r['return_status'] ?>
                            </span>
                        </td>
                        <td>
                            <?= $r['return_qty'] !== null ? number_format((int)$r['return_qty']) . ' واحد' : '—' ?>

                        <td class="td-muted td-nowrap"><?= jdate_fa($r['return_received_at'] ?? null) ?></td>
                        <td>
                            <?php if ($r['return_status'] === 'not_requested' && $r['refund_status'] === 'pending'): ?>
                                <button type="button"
                                    onclick="document.getElementById('ret-req-<?= (int)$r['id'] ?>').showModal()"
                                    style="padding:5px 10px;font-size:.72rem;background:#eef;color:#33475b;border:none;border-radius:4px;cursor:pointer;">
                                    درخواست بازگشت
                                </button>

                                <dialog id="ret-req-<?= (int)$r['id'] ?>" style="max-width:480px;border-radius:10px;border:1px solid #ccc;padding:20px;">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="return_request">
                                        <input type="hidden" name="refund_id" value="<?= (int)$r['id'] ?>">
                                        <h3 style="margin:0 0 12px;">درخواست بازگشت کالا #<?= (int)$r['id'] ?></h3>
                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">دلیل (الزامی):</label>
                                        <textarea name="reason" required rows="3"
                                            placeholder="مثلاً: کالا معیوب، اشتباه ارسال شده، ..."
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:14px;"></textarea>
                                        <div style="text-align:left;">
                                            <button type="button" onclick="document.getElementById('ret-req-<?= (int)$r['id'] ?>').close()"
                                                style="padding:6px 12px;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">انصراف</button>
                                            <button type="submit" style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:4px;cursor:pointer;">ثبت</button>
                                        </div>
                                    </form>
                                </dialog>
                            <?php elseif ($r['return_status'] === 'requested' || $r['return_status'] === 'in_transit'): ?>
                                <button type="button"
                                    onclick="document.getElementById('ret-rec-<?= (int)$r['id'] ?>').showModal()"
                                    style="padding:5px 10px;font-size:.72rem;background:#e8f5ee;color:#1a7f4b;border:none;border-radius:4px;cursor:pointer;">
                                    دریافت کالا
                                </button>

                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="reject_return">
                                    <input type="hidden" name="refund_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="reason" value="رد به‌دلیل عدم تطابق">
                                    <button type="submit"
                                        style="padding:5px 10px;font-size:.72rem;background:#fff0f0;color:#c62828;border:none;border-radius:4px;cursor:pointer;"
                                        onclick="return confirm('رد درخواست بازگشت؟');">
                                        ✗ رد
                                    </button>
                                </form>

                                <dialog id="ret-rec-<?= (int)$r['id'] ?>" style="max-width:480px;border-radius:10px;border:1px solid #ccc;padding:20px;">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="return_received">
                                        <input type="hidden" name="refund_id" value="<?= (int)$r['id'] ?>">
                                        <h3 style="margin:0 0 12px;">دریافت کالای برگشتی #<?= (int)$r['id'] ?></h3>
                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">تعداد:</label>
                                        <input type="number" name="qty" required min="1"
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;">

                                        <label style="font-size:.78rem;display:block;margin-bottom:4px;">وضعیت کالا:</label>
                                        <select name="sellable" required
                                            style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:14px;">
                                            <option value="1">قابل فروش (سالم)</option>
                                            <option value="0">غیرقابل فروش (معیوب/خسارت‌دیده)</option>
                                        </select>

                                        <div style="text-align:left;">
                                            <button type="button" onclick="document.getElementById('ret-rec-<?= (int)$r['id'] ?>').close()"
                                                style="padding:6px 12px;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">انصراف</button>
                                            <button type="submit" style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:4px;cursor:pointer;">ثبت دریافت</button>
                                        </div>
                                    </form>
                                </dialog>
                            <?php else: ?>
                                <span style="color:#999;font-size:.72rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
