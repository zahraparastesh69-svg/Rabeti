<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

/* ─── تغییر وضعیت سفارش ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    $allowed = ['pending_review', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'];

    if ($id > 0 && in_array($status, $allowed, true)) {

        $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);

        // اگر لغو شد → موجودی برگردد (قانون قفل‌شده: Release)
        if ($status === 'cancelled') {
            $db->prepare("
                UPDATE inventory inv
                JOIN order_items oi ON oi.product_id = inv.product_id
                SET inv.qty_available = inv.qty_available + oi.qty
                WHERE oi.order_id = ?
            ")->execute([$id]);
        }

        $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
            VALUES (?, ?, 'order', ?, ?)
        ")->execute([$_SESSION['admin_id'], 'order.status.' . $status, $id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        header('Location: /admin/orders');
        exit;
    }
}

/* ─── فیلتر وضعیت ─── */
$filter = $_GET['status'] ?? '';
$where  = '';
$params = [];
if ($filter !== '' && in_array($filter, ['pending_review', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'], true)) {
    $where = " WHERE o.status = ? ";
    $params[] = $filter;
}

/* ─── لیست سفارش‌ها ─── */
$stmt = $db->prepare("
    SELECT o.*,
           u.full_name AS customer_name, u.mobile,
           c.name AS company_name,
           (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS total_qty
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN companies c ON c.id = o.company_id
    $where
    ORDER BY o.id DESC
    LIMIT 100
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$all    = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending_review'")->fetchColumn();

$statusLabels = [
    'pending_review' => 'در انتظار بررسی',
    'processing'     => 'در حال پردازش',
    'packed'         => 'بسته‌بندی شد',
    'shipped'        => 'ارسال شد',
    'delivered'      => 'تحویل شد',
    'cancelled'      => 'لغو شده',
];

$activeMenu = 'orders';
$pageTitle  = 'سفارش‌ها';
ob_start();
?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
    <a href="/admin/orders" class="st-badge <?= $filter === '' ? 'st-quoted' : '' ?>"
        style="text-decoration:none;padding:7px 16px;">همه (<?= (int)$all ?>)</a>
    <a href="/admin/orders?status=pending_review" class="st-badge st-new <?= $filter === 'pending_review' ? '' : '' ?>"
        style="text-decoration:none;padding:7px 16px;">در انتظار (<?= (int)$pending ?>)</a>
    <?php foreach (['processing', 'packed', 'shipped', 'delivered', 'cancelled'] as $s): ?>
        <a href="/admin/orders?status=<?= $s ?>" class="st-badge"
            style="text-decoration:none;padding:7px 16px;background:#fff;border:1px solid var(--card-border);color:var(--muted);">
            <?= $statusLabels[$s] ?>
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
                    <th style="min-width:190px;">وضعیت ← تغییر</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr style="<?= $o['status'] === 'pending_review' ? 'background:#fffbea;' : '' ?>">
                        <td><a dir="ltr" href="/admin/order-detail?id=<?= (int)$o['id'] ?>" style="color:var(--teal-700);"><b><?= htmlspecialchars($o['order_number']) ?></b></a></td>
                        <td><b dir="ltr"><?= htmlspecialchars($o['order_number']) ?></b></td>
                        <td>
                            <?= htmlspecialchars($o['customer_name']) ?><br>
                            <span style="font-size:.72rem;color:var(--muted);">
                                <?= htmlspecialchars($o['company_name'] ?? 'بدون شرکت') ?>
                            </span>
                        </td>
                        <td><?= (int)$o['total_qty'] ?> جفت</td>
                        <td><b style="color:var(--teal-800);"><?= number_format((float)$o['total']) ?></b> ت</td>
                        <td style="font-size:.75rem;">
                            <?= htmlspecialchars($o['shipping_name']) ?><br>
                            <span style="color:var(--muted);"><?= htmlspecialchars($o['shipping_city']) ?></span>
                        </td>
                        <td style="font-size:.72rem;color:var(--muted);"><?= $o['created_at'] ?></td>
                        <td>
                            <span class="st-badge st-new"><?= $statusLabels[$o['status']] ?></span><br><br>
                            <form method="post" style="display:flex;gap:6px;">
                                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                <select name="status" style="font-size:.75rem;padding:6px;border:1px solid var(--card-border);border-radius:8px;">
                                    <?php foreach ($statusLabels as $key => $lbl): ?>
                                        <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-orange" style="padding:6px 14px;font-size:.72rem;">ثبت</button>
                            </form>
                            <?php if ($o['status'] !== 'cancelled'): ?>
                                <form method="post" style="margin-top:6px;" onsubmit="return confirm('سفارش لغو شود؟ موجودی انبار برمی‌گردد.');">
                                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button class="btn" style="padding:4px 12px;font-size:.68rem;background:#fff0f0;color:#c62828;">لغو سفارش</button>
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
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
