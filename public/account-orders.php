<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$db = db();

$statusLabels = [
    'pending_review' => 'در انتظار بررسی',
    'processing'     => 'در حال پردازش',
    'packed'         => 'بسته‌بندی شد',
    'shipped'        => 'ارسال شد',
    'delivered'      => 'تحویل شد',
    'cancelled'      => 'لغو شده',
];

$stmt = $db->prepare("
    SELECT o.*,
           i.invoice_number, i.status AS pay_status, i.paid_total,
           (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS total_qty,
           (SELECT COUNT(*) FROM payments p WHERE p.invoice_id = i.id AND p.status='pending') AS pending_payments
    FROM orders o
    LEFT JOIN invoices i ON i.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.id DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'سفارش‌های من | رابطی';
ob_start();
?>

<nav class="breadcrumb"><a href="/">خانه</a> › <span>سفارش‌های من</span></nav>

<section class="catalog-head">
    <h1 class="catalog-title">سفارش‌های من</h1>
    <p class="catalog-sub"><?= count($orders) ?> سفارش</p>
</section>

<?php if ($orders): ?>
    <div class="my-orders">

        <?php foreach ($orders as $o): ?>
            <article class="my-order-card">

                <div class="mo-head">
                    <div>
                        <b dir="ltr"><?= $h($o['order_number']) ?></b>
                        <small><?= $o['created_at'] ?></small>
                    </div>
                    <span class="st-badge <?= [
                                                'pending_review' => 'st-new',
                                                'processing' => 'st-read',
                                                'packed' => 'st-quoted',
                                                'shipped' => 'st-quoted',
                                                'delivered' => 'st-converted',
                                                'cancelled' => 'st-rejected',
                                            ][$o['status']] ?>">
                        <?= $statusLabels[$o['status']] ?>
                    </span>
                </div>

                <div class="mo-body">
                    <div class="mo-row">
                        <span>تعداد</span>
                        <b><?= number_format((int)($o['total_qty'] ?? 0)) ?> جفت</b>
                    </div>
                    <div class="mo-row">
                        <span>مبلغ کل</span>
                        <b><?= number_format((float)$o['total']) ?> تومان</b>
                    </div>
                    <div class="mo-row">
                        <span>پرداخت</span>
                        <b class="<?= ($o['pay_status'] ?? 'unpaid') === 'paid' ? 'stock in' : 'stock out' ?>">
                            <?= ['unpaid' => 'پرداخت‌نشده', 'partial' => 'پرداخت جزئی', 'paid' => 'پرداخت‌شده', 'void' => 'باطل'][$o['pay_status'] ?? 'unpaid'] ?>
                            <?php if (($o['pending_payments'] ?? 0) > 0): ?>
                                <small style="color:var(--cta-d);">(در انتظار تأیید مالی)</small>
                            <?php endif; ?>
                        </b>
                    </div>
                </div>

                <div class="mo-actions">
                    <?php if (($o['pay_status'] ?? 'unpaid') !== 'paid' && $o['status'] !== 'cancelled'): ?>
                        <a href="/payment?order=<?= $h($o['order_number']) ?>" class="btn btn-primary">
                            <?= ($o['pending_payments'] ?? 0) > 0 ? 'پیگیری پرداخت' : 'پرداخت' ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($o['invoice_number']): ?>
                        <a href="/invoice-print?inv=<?= $h($o['invoice_number']) ?>" target="_blank" class="btn btn-outline">
                            🧾 فاکتور
                        </a>
                    <?php endif; ?>
                </div>

            </article>
        <?php endforeach; ?>

    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h2>هنوز سفارشی ثبت نکرده‌اید</h2>
        <a href="/products" class="btn btn-primary">شروع خرید</a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
