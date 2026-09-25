<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();
$db = db();

/* تأیید / رد */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['confirm', 'reject'], true)) {
        $newStatus = $action === 'confirm' ? 'confirmed' : 'rejected';

        $stmt = $db->prepare("SELECT p.*, i.total AS inv_total FROM payments p
                              JOIN invoices i ON i.id = p.invoice_id WHERE p.id = ?");
        $stmt->execute([$id]);
        $pay = $stmt->fetch();
        if ($pay && $pay['status'] === 'pending') {

            $db->prepare("UPDATE payments SET status=?, confirmed_by=?, confirmed_at=NOW() WHERE id=?")
                ->execute([$newStatus, $_SESSION['admin_id'], $id]);

            /* جمع پرداخت‌های تأییدشده فاکتور */
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='confirmed'");
            $stmt->execute([$pay['invoice_id']]);
            $paid = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("SELECT total FROM invoices WHERE id=?");
            $stmt->execute([$pay['invoice_id']]);
            $invTotal = (int)$stmt->fetchColumn();

            $invStatus = $paid >= $invTotal ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            $db->prepare("UPDATE invoices SET paid_total=?, status=? WHERE id=?")
                ->execute([$paid, $invStatus, $pay['invoice_id']]);

            /* اگر فاکتور کامل پرداخت شد → سفارش وارد پردازش شود */
            if ($invStatus === 'paid') {
                $stmt = $db->prepare("SELECT order_id FROM invoices WHERE id=?");
                $stmt->execute([$pay['invoice_id']]);
                $orderId = $stmt->fetchColumn();
                $db->prepare("UPDATE orders SET status='processing' WHERE id=? AND status='pending_review'")
                    ->execute([$orderId]);
            }

            $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                          VALUES (?, 'payment.$action', 'payment', ?, ?)")
                ->execute([$_SESSION['admin_id'], $id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        }
        header('Location: /admin/payments');
        exit;
    }
}

$payments = $db->query("
    SELECT p.*, i.invoice_number, o.order_number, u.full_name
    FROM payments p
    JOIN invoices i ON i.id = p.invoice_id
    JOIN orders o   ON o.id = i.order_id
    JOIN users u    ON u.id = p.user_id
    ORDER BY (p.status='pending') DESC, p.id DESC
    LIMIT 100
")->fetchAll();

$activeMenu = 'payments';
$pageTitle  = 'تأیید پرداخت‌ها';
ob_start();
?>

<?php if ($payments): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>فاکتور</th>
                    <th>سفارش</th>
                    <th>مشتری</th>
                    <th>روش</th>
                    <th>مبلغ</th>
                    <th>اثبات</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr style="<?= $p['status'] === 'pending' ? 'background:#fffbea;' : '' ?>">
                        <td dir="ltr"><?= htmlspecialchars($p['invoice_number']) ?></td>
                        <td dir="ltr"><?= htmlspecialchars($p['order_number']) ?></td>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td><?= ['sheba' => 'شبا', 'card_to_card' => 'کارت‌به‌کارت', 'cash_receipt' => 'فیش نقدی', 'online' => 'درگاه'][$p['method']] ?></td>
                        <td><b><?= number_format((float)$p['amount']) ?></b> ت</td>
                        <td style="font-size:.75rem;">
                            <?php if ($p['reference_no']): ?>
                                پیگیری: <span dir="ltr"><?= htmlspecialchars($p['reference_no']) ?></span>
                            <?php endif; ?>
                            <?php if ($p['receipt_path']): ?>
                                <br><a href="<?= htmlspecialchars($p['receipt_path']) ?>" target="_blank" style="color:var(--teal-700);">🖼 مشاهده فیش</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="st-badge <?= $p['status'] === 'pending' ? 'st-new' : ($p['status'] === 'confirmed' ? 'st-converted' : 'st-rejected') ?>">
                                <?= ['pending' => 'در انتظار', 'confirmed' => 'تأیید شد', 'rejected' => 'رد شد'][$p['status']] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button class="btn" style="padding:6px 12px;font-size:.72rem;background:#e8f5ee;color:#1a7f4b;font-weight:800;">✓ تأیید</button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('رد شود؟');">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn" style="padding:6px 12px;font-size:.72rem;background:#fff0f0;color:#c62828;">✗ رد</button>
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
        <h2>پرداختی ثبت نشده است</h2>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
