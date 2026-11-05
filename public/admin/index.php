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
require_once ROOT_PATH . '/config/upload.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

/*
|--------------------------------------------------------------------------
| کوئری امن — هر خطای دیتابیس به‌جای صفحهٔ سفید، مقدار پیش‌فرض برمی‌گرداند
|--------------------------------------------------------------------------
*/

$_safe = function (callable $fn, $default) {
    try {
        return $fn();
    } catch (Throwable $e) {
        return $default;
    }
};

$_scalar = function (string $sql, int $default = 0) use ($_safe, $db) {
    return $_safe(function () use ($db, $sql) {
        return (int) $db->query($sql)->fetchColumn();
    }, $default);
};

$_rows = function (string $sql, array $default = []) use ($_safe, $db) {
    return $_safe(function () use ($db, $sql) {
        return $db->query($sql)->fetchAll();
    }, $default);
};

/*
|--------------------------------------------------------------------------
| Dashboard Data — KPIها از داده‌های واقعی
|--------------------------------------------------------------------------
*/

/* شاخص‌های ارتباطات و کاتالوگ (موجود) */
$countCustom    = $_scalar("SELECT COUNT(*) FROM custom_order_requests WHERE status = 'new'");
$countMessages  = $_scalar("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$countProducts  = $_scalar("SELECT COUNT(*) FROM products WHERE is_active = 1");
$countCats      = $_scalar("SELECT COUNT(*) FROM categories WHERE is_active = 1");
$countPendingCustomers = $_scalar("SELECT COUNT(*) FROM companies WHERE status = 'pending'");

/* شاخص‌های عملیاتی — سفارش‌ها بر اساس status (تک‌فیلد قدیمی) */
$countOrdersNew           = $_scalar("SELECT COUNT(*) FROM orders WHERE status = 'pending_review'");
$countOrdersProcessing    = $_scalar("SELECT COUNT(*) FROM orders WHERE status = 'processing'");
$countOrdersPacked        = $_scalar("SELECT COUNT(*) FROM orders WHERE status = 'packed'");
$countOrdersShipped       = $_scalar("SELECT COUNT(*) FROM orders WHERE status IN ('shipped','shipped-pik','shipped-post')");
$countOrdersDelivered     = $_scalar("SELECT COUNT(*) FROM orders WHERE status = 'delivered'");
$countOrdersCancelled     = $_scalar("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");

/* شاخص‌های نیازمند تولید (order_items.qty_to_produce > 0) */
$countNeedsProduction = $_scalar("
    SELECT COUNT(DISTINCT oi.order_id)
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE oi.qty_to_produce > 0
      AND o.status NOT IN ('cancelled','delivered')
");

/* پرداخت‌های در انتظار تأیید */
$countPendingPayments = $_scalar("SELECT COUNT(*) FROM payment_submissions WHERE status = 'pending'");

/* مطالبات سررسیدشده — فاکتور‌هایی که متعلق به سفارش‌های تحویل‌شده‌اند اما هنوز کامل پرداخت نشده‌اند */
$overdueRow = $_rows("
    SELECT COUNT(*) AS cnt, COALESCE(SUM(i.total - i.paid_total), 0) AS amount
    FROM invoices i
    INNER JOIN orders o ON o.id = i.order_id
    WHERE o.status = 'delivered'
      AND i.status IN ('unpaid','partial')
");
$overdueReceivables   = (int) ($overdueRow[0]['amount'] ?? 0);
$countOverdueInvoices = (int) ($overdueRow[0]['cnt'] ?? 0);

/* ارزش موجودی انبار — بر اساس base_price (قیمت عادی فروش) */
$inventoryValue = $_scalar("
    SELECT COALESCE(SUM(COALESCE(inv.qty_physical, 0) * COALESCE(p.base_price, 0)), 0)
    FROM products p
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
");

/* کالاهای پرفروش (۳۰ روز اخیر) */
$topSellers = $_rows("
    SELECT oi.product_code, oi.product_name,
           SUM(oi.qty) AS sold_qty,
           SUM(oi.line_total) AS sold_amount
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND o.status NOT IN ('cancelled')
    GROUP BY oi.product_id, oi.product_code, oi.product_name
    ORDER BY sold_qty DESC
    LIMIT 5
");

/* کالاهای کم‌موجود (موجودی فیزیکی < 20 یا NULL) */
$lowStockProducts = $_rows("
    SELECT p.id, p.code, p.name,
           COALESCE(inv.qty_physical, 0) AS qty_physical,
           COALESCE(inv.reserved, 0) AS qty_reserved
    FROM products p
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
      AND COALESCE(inv.qty_physical, 0) < 20
    ORDER BY COALESCE(inv.qty_physical, 0) ASC
    LIMIT 5
");

if (!$lowStockProducts) {
    $lowStockProducts = $_rows("
        SELECT p.id, p.code, p.name,
               COALESCE(inv.qty_available, 0) AS qty_physical,
               0 AS qty_reserved
        FROM products p
        LEFT JOIN inventory inv ON inv.product_id = p.id
        WHERE p.is_active = 1
          AND COALESCE(inv.qty_available, 0) < 20
        ORDER BY COALESCE(inv.qty_available, 0) ASC
        LIMIT 5
    ");
}

/* فروش روزانه، ماهانه و مقایسه دوره قبل */
$salesToday     = $_scalar("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status NOT IN ('cancelled')");
$salesThisMonth = $_scalar("SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND status NOT IN ('cancelled')");
$salesPrevMonth = $_scalar("SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01') AND status NOT IN ('cancelled')");

$salesTrendPct = $salesPrevMonth > 0
    ? round(($salesThisMonth - $salesPrevMonth) / $salesPrevMonth * 100, 1)
    : null;

/* آخرین درخواست‌های اختصاصی */
$latestCustom = $_rows("
    SELECT id, name, mobile, product_type, qty, status, created_at
    FROM custom_order_requests
    ORDER BY id DESC
    LIMIT 5
");

/* آخرین سفارش‌ها */
$latestOrders = $_rows("
    SELECT o.id, o.order_number, o.total, o.status, o.created_at,
           u.full_name AS customer_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.id DESC
    LIMIT 5
");

unset($_safe, $_scalar, $_rows);

/* برچسب فارسی وضعیت‌ها (مقادیر خام دیتابیس انگلیسی‌اند) */
$statusLabelsFa = [
    'pending_review' => 'در انتظار تأیید',
    'processing'      => 'در حال تأمین',
    'packed'          => 'آماده ارسال',
    'shipped'         => 'ارسال شد',
    'shipped-pik'     => 'ارسال (پیک)',
    'shipped-post'    => 'ارسال (پست)',
    'delivered'       => 'تحویل شد',
    'cancelled'       => 'لغو شد',
    'new'             => 'جدید',
    'contacted'       => 'تماس گرفته شد',
    'quoted'          => 'پیش‌فاکتور شد',
    'in_progress'     => 'در جریان',
    'done'            => 'تمام شد',
];

$activeMenu = 'dashboard';
$pageTitle  = 'داشبورد';

ob_start();
?>

<div class="admin-dashboard">

    <!-- =====================================================
         PAGE INTRO
    ====================================================== -->

    <div class="admin-page-intro">

        <div>
            <span class="admin-kicker">نمای کلی فروشگاه</span>
            <h1>داشبورد</h1>
            <p>وضعیت فعلی فروشگاه و فعالیت‌های مهم را در یک نگاه ببینید.</p>
        </div>

        <a href="/" class="admin-soft-action" target="_blank" rel="noopener">
            مشاهده فروشگاه
        </a>

    </div>

    <!-- =====================================================
         KPI - فروش و عملیات (۱۲ شاخص هدف)
    ====================================================== -->

    <div class="admin-section-head" style="margin-top:24px;">
        <div>
            <span class="admin-kicker">سفارش‌ها و عملیات</span>
            <h2>سفارش‌های جاری</h2>
        </div>
    </div>

    <div class="stat-cards">

        <a href="/admin/orders?status=pending_review" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">در انتظار تأیید</span>
                <span class="stat-card-icon">⏳</span>
            </div>
            <div class="num"><?= number_format($countOrdersNew) ?></div>
            <div class="stat-card-note">نیازمند بررسی</div>
        </a>

        <a href="/admin/orders?status=processing" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">در حال تأمین</span>
                <span class="stat-card-icon">⚙</span>
            </div>
            <div class="num"><?= number_format($countOrdersProcessing) ?></div>
            <div class="stat-card-note">در حال آماده‌سازی</div>
        </a>

        <a href="/admin/orders?status=packed" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">آماده ارسال</span>
                <span class="stat-card-icon">📦</span>
            </div>
            <div class="num"><?= number_format($countOrdersPacked) ?></div>
            <div class="stat-card-note">بسته‌بندی‌شده</div>
        </a>

        <a href="/admin/orders?status=shipped" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">ارسال‌شده</span>
                <span class="stat-card-icon">🚚</span>
            </div>
            <div class="num"><?= number_format($countOrdersShipped) ?></div>
            <div class="stat-card-note">در مسیر</div>
        </a>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">نیازمند تولید</span>
                <span class="stat-card-icon">🏭</span>
            </div>
            <div class="num"><?= number_format($countNeedsProduction) ?></div>
            <div class="stat-card-note">کسری موجودی</div>
        </div>

        <a href="/admin/orders?status=cancelled" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">لغو شده</span>
                <span class="stat-card-icon">✗</span>
            </div>
            <div class="num"><?= number_format($countOrdersCancelled) ?></div>
            <div class="stat-card-note">کل دوره</div>
        </a>

    </div>

    <!-- =====================================================
         KPI - مالی
    ====================================================== -->

    <div class="admin-section-head" style="margin-top:24px;">
        <div>
            <span class="admin-kicker">مالی</span>
            <h2>پرداخت‌ها و مطالبات</h2>
        </div>
    </div>

    <div class="stat-cards">

        <a href="/admin/payments?status=pending" class="stat-card stat-card-accent" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">پرداخت‌های در انتظار</span>
                <span class="stat-card-icon">💳</span>
            </div>
            <div class="num"><?= number_format($countPendingPayments) ?></div>
            <div class="stat-card-note">نیازمند تأیید</div>
        </a>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">مطالبات سررسیدشده</span>
                <span class="stat-card-icon">⚠</span>
            </div>
            <div class="num"><?= number_format($countOverdueInvoices) ?></div>
            <div class="stat-card-note">
                مبلغ: <?= number_format($overdueReceivables) ?> ت
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">فروش امروز</span>
                <span class="stat-card-icon">📈</span>
            </div>
            <div class="num"><?= number_format($salesToday) ?></div>
            <div class="stat-card-note">تومان</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">فروش این ماه</span>
                <span class="stat-card-icon">📊</span>
            </div>
            <div class="num"><?= number_format($salesThisMonth) ?></div>
            <div class="stat-card-note">
                <?php if ($salesTrendPct !== null): ?>
                    روند: <?= $salesTrendPct > 0 ? '↗ +' . $salesTrendPct : '↘ ' . $salesTrendPct ?>%
                <?php else: ?>
                    نسبت به ماه قبل: نامشخص
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- =====================================================
         KPI - انبار و کاتالوگ
    ====================================================== -->

    <div class="admin-section-head" style="margin-top:24px;">
        <div>
            <span class="admin-kicker">انبار و کاتالوگ</span>
            <h2>موجودی و محصولات</h2>
        </div>
    </div>

    <div class="stat-cards">

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">ارزش موجودی انبار</span>
                <span class="stat-card-icon">💰</span>
            </div>
            <div class="num"><?= number_format($inventoryValue) ?></div>
            <div class="stat-card-note">تومان (بر اساس قیمت پایه)</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">محصولات فعال</span>
                <span class="stat-card-icon">□</span>
            </div>
            <div class="num"><?= number_format($countProducts) ?></div>
            <div class="stat-card-note">دسته‌بندی: <?= number_format($countCats) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-card-label">کالاهای کم‌موجود</span>
                <span class="stat-card-icon">⚠</span>
            </div>
            <div class="num"><?= count($lowStockProducts) ?></div>
            <div class="stat-card-note">زیر ۲۰</div>
        </div>

        <a href="/admin/customers?status=pending" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">مشتریان در انتظار</span>
                <span class="stat-card-icon">+</span>
            </div>
            <div class="num"><?= number_format($countPendingCustomers) ?></div>
            <div class="stat-card-note">نیازمند بررسی</div>
        </a>

    </div>

    <!-- =====================================================
         جداول — پرفروش‌ها و کم‌موجودها
    ====================================================== -->

    <div class="admin-grid-2col">

        <!-- کالاهای پرفروش -->
        <div class="admin-card">
            <h3>کالاهای پرفروش (۳۰ روز اخیر)</h3>
            <?php if ($topSellers): ?>
                <div class="admin-table-wrap admin-table-wrap--in-card">
<table class="admin-table admin-table--compact">
                    <thead>
                        <tr>
                            <th>کد</th>
                            <th>محصول</th>
                            <th>تعداد</th>
                            <th>مبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSellers as $p): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($p['product_code']) ?></b></td>
                                <td><?= htmlspecialchars($p['product_name']) ?></td>
                                <td><?= number_format((int)$p['sold_qty']) ?></td>
                                <td><?= number_format((int)$p['sold_amount']) ?> ت</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
</div>
            <?php else: ?>
                <p class="admin-empty-text">داده‌ای برای نمایش وجود ندارد.</p>
            <?php endif; ?>
        </div>

        <!-- کالاهای کم‌موجود -->
        <div class="admin-card">
            <h3>کالاهای کم‌موجود</h3>
            <?php if ($lowStockProducts): ?>
                <div class="admin-table-wrap admin-table-wrap--in-card">
<table class="admin-table admin-table--compact">
                    <thead>
                        <tr>
                            <th>کد</th>
                            <th>محصول</th>
                            <th>موجودی</th>
                            <th>رزرو</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $p): ?>
                            <tr class="<?= (int)$p['qty_physical'] < 10 ? 'row-danger' : '' ?>">
                                <td><b><?= htmlspecialchars($p['code']) ?></b></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= number_format((int)$p['qty_physical']) ?></td>
                                <td><?= number_format((int)$p['qty_reserved']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
</div>
            <?php else: ?>
                <p class="admin-empty-text">همهٔ محصولات موجودی کافی دارند.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- =====================================================
         آخرین سفارش‌ها و درخواست‌های اختصاصی
    ====================================================== -->

    <div class="admin-grid-2col">

        <div class="admin-card">
            <h3>آخرین سفارش‌ها</h3>
            <?php if ($latestOrders): ?>
                <div class="admin-table-wrap admin-table-wrap--in-card">
<table class="admin-table admin-table--compact">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>مشتری</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestOrders as $o): ?>
                            <tr>
                                <td dir="ltr" class="td-rtl"><b><?= htmlspecialchars($o['order_number']) ?></b></td>
                                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                                <td><?= number_format((int)$o['total']) ?></td>
                                <td><span class="st-badge st-quoted"><?= htmlspecialchars($statusLabelsFa[$o['status']] ?? $o['status']) ?></span></td>
                                <td class="td-muted td-nowrap"><?= jdate_fa($o['created_at'] ?? null) ?></td>
                                <td>
                                    <a href="/admin/order-detail?id=<?= (int)$o['id'] ?>" class="admin-link">
                                        مشاهده
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
</div>
            <?php else: ?>
                <p class="admin-empty-text">هنوز سفارشی ثبت نشده است.</p>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <h3>درخواست‌های اختصاصی جدید</h3>
            <?php if ($latestCustom): ?>
                <div class="admin-table-wrap admin-table-wrap--in-card">
<table class="admin-table admin-table--compact">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>موبایل</th>
                            <th>نوع</th>
                            <th>تعداد</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestCustom as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td dir="ltr" class="td-rtl"><?= htmlspecialchars($c['mobile']) ?></td>
                                <td><?= htmlspecialchars($c['product_type'] ?? '—') ?></td>
                                <td><?= $c['qty'] ? number_format((int)$c['qty']) : '—' ?></td>
                                <td><span class="st-badge st-new"><?= htmlspecialchars($statusLabelsFa[$c['status']] ?? $c['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
</div>
            <?php else: ?>
                <p class="admin-empty-text">درخواست اختصاصی جدیدی وجود ندارد.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- =====================================================
         ارتباطات
    ====================================================== -->

    <div class="admin-section-head" style="margin-top:24px;">
        <div>
            <span class="admin-kicker">ارتباطات</span>
            <h2>صندوق پیام‌ها</h2>
        </div>
    </div>

    <div class="stat-cards">
        <a href="/admin/messages" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">پیام‌های جدید</span>
                <span class="stat-card-icon">✉</span>
            </div>
            <div class="num"><?= number_format($countMessages) ?></div>
            <div class="stat-card-note">منتظر پاسخ</div>
        </a>

        <a href="/admin/custom-orders" class="stat-card" style="text-decoration:none;color:inherit;">
            <div class="stat-card-top">
                <span class="stat-card-label">سفارش‌های اختصاصی</span>
                <span class="stat-card-icon">↗</span>
            </div>
            <div class="num"><?= number_format($countCustom) ?></div>
            <div class="stat-card-note">نیازمند بررسی</div>
        </a>
    </div>

</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
