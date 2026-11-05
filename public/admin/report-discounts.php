<?php
/* [FILE] public/admin/report-discounts.php — گزارش تخفیف‌ها */

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
require_once __DIR__ . '/financial-helpers.php';

require_admin();

$db = db();

fin_applyPreset();
$finRange = fin_dateRange();
[$from, $to] = $finRange;

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money = static fn($v): string => number_format((int)$v) . ' تومان';

/* ═══ تخفیف در سطح آیتم + سطح سفارش
   BUG FIX: legacy checkout.php (قبل از تسویه با checkout-review.php)
   order_items با base_unit_price=0 ایجاد می‌کرد، که باعث می‌شد تخفیف‌ها
   گم شوند. اصلاح: تخفیف آیتم را بر اساس discount_amount * qty محاسبه کن
   (این فیلد در legacy هم 0 است ولی در سفارش‌های جدید درست است).
   اگر base_unit_price > 0 و > unit_price: (base-unit)*qty
   در غیر این صورت: discount_amount * qty (که در جدید همان مقدار است)
   در legacy هم 0 خواهد بود (که درست است — تخفیفی نبوده).
═══ */
$stmt = $db->prepare("
    SELECT 
        i.invoice_number, 
        i.issued_at,
        u.full_name, 
        u.company_name,
        u.customer_group AS buyer_group,
        o.order_number,
        o.order_number,
        COALESCE(
            (SELECT SUM(
                (oi.group_discount_percent / 100) * oi.base_unit_price * oi.qty
            ) FROM order_items oi WHERE oi.order_id = o.id AND oi.group_discount_percent > 0),
            0
        ) AS group_discount_amount,
        COALESCE(
            (SELECT SUM(
                (oi.tier_discount_percent / 100) * oi.base_unit_price * oi.qty
            ) FROM order_items oi WHERE oi.order_id = o.id AND oi.tier_discount_percent > 0),
            0
        ) AS tier_discount_amount,
        COALESCE(
            (SELECT SUM(
                CASE 
                    WHEN oi.discount_source = 'group' 
                        THEN (oi.group_discount_percent / 100) * oi.base_unit_price * oi.qty
                    WHEN oi.discount_source = 'tier'  
                        THEN (oi.tier_discount_percent / 100) * oi.base_unit_price * oi.qty
                    ELSE 0
                END
            ) FROM order_items oi WHERE oi.order_id = o.id),
            0
        ) AS total_discount_amount,
        o.discount_amount AS order_discount
    FROM invoices i
    INNER JOIN orders o ON o.id = i.order_id
    INNER JOIN users u ON u.id = i.user_id
    WHERE i.issued_at BETWEEN ? AND ?
    ORDER BY i.issued_at DESC
");
$stmt->execute([$from, $to]);
$discounts = $stmt->fetchAll();

/* فیلتر فقط تخفیف‌دارها */
$onlyDiscounted = isset($_GET['only']) && $_GET['only'] === '1';
if ($onlyDiscounted) {
    $discounts = array_filter(
        $discounts,
        fn($d) => ((int)($d['group_discount_amount'] ?? 0) + (int)($d['tier_discount_amount'] ?? 0) + (int)($d['order_discount'] ?? 0)) > 0
    );
}

$totalGroupDisc = 0;
$totalTierDisc  = 0;
$totalOrderDisc = 0;

foreach ($discounts as $d) {
    $totalGroupDisc += (int)($d['group_discount_amount'] ?? 0);
    $totalTierDisc  += (int)($d['tier_discount_amount']  ?? 0);
    $totalOrderDisc += (int)($d['order_discount']        ?? 0);
}

/* ═══ خروجی CSV ═══ */
if (($_GET['export'] ?? '') === 'csv') {
    require_once ROOT_PATH . '/app/services/CsvExporter.php';
    $csv = new CsvExporter('discounts-' . date('Y-m-d'));
    $csv->header([
        'تاریخ',
        'شماره فاکتور',
        'سفارش',
        'مشتری',
        'گروه مشتری',
        'تخفیف سطح',
        'تخفیف پله‌ای',
        'تخفیف سفارش',
        'جمع'
    ]);
    foreach ($discounts as $d) {
        $groupD = (int)($d['group_discount_amount'] ?? 0);
        $tierD  = (int)($d['tier_discount_amount']  ?? 0);
        $orderD = (int)($d['order_discount']        ?? 0);
        $csv->row([
            $d['issued_at'],
            $d['invoice_number'],
            $d['order_number'],
            $d['full_name'] . ($d['company_name'] ? ' — ' . $d['company_name'] : ''),
            $d['buyer_group'] ?? '—',
            $groupD,
            $tierD,
            $orderD,
            $groupD + $tierD + $orderD,
        ]);
    }
    $csv->end();
}

$pageTitle  = 'گزارش تخفیف‌ها';
$activeMenu = 'reports';

ob_start();
?>

<nav class="breadcrumb"><a href="/">خانه</a> › <span>گزارش تخفیف‌ها</span></nav>

<section class="catalog-head">
    <h1 class="catalog-title">گزارش تخفیف‌ها</h1>
    <p class="catalog-sub">از <?= $h(fin_jalali($from)) ?> تا <?= $h(fin_jalali($to)) ?></p>
</section>

<?php fin_rangeForm('/admin/report-discounts'); ?>

<div style="text-align:left;margin:8px 0 14px;">
    <a href="?export=csv&from=<?= $h($from) ?>&to=<?= $h($to) ?>" class="btn btn-orange" style="padding:7px 16px;font-size:.78rem;">
        ⬇ خروجی CSV
    </a>
</div>

<div class="stat-cards" style="grid-template-columns:repeat(3,minmax(0,1fr));">
    <div class="stat-card stat-card-accent">
        <div class="stat-card-top"><span class="stat-card-label">تخفیف سطح مشتری</span><span class="stat-card-icon">🏷</span></div>
        <div class="num"><?= $money($totalGroupDisc) ?></div>
        <div class="stat-card-note">برنزی / نقره‌ای / طلایی</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-top"><span class="stat-card-label">تخفیف پله‌ای</span><span class="stat-card-icon">📊</span></div>
        <div class="num"><?= $money($totalTierDisc) ?></div>
        <div class="stat-card-note">بر اساس تعداد خرید</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-top"><span class="stat-card-label">تخفیف سفارش</span><span class="stat-card-icon">%</span></div>
        <div class="num"><?= $money($totalOrderDisc) ?></div>
        <div class="stat-card-note">در سطح کل سفارش</div>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>تاریخ</th>
                <th>شماره فاکتور</th>
                <th>سفارش</th>
                <th>خریدار</th>
                <th>گروه</th>
                <th>تخفیف سطح</th>
                <th>تخفیف پله‌ای</th>
                <th>تخفیف سفارش</th>
                <th>جمع</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            $groupLabels = [
                'regular' => 'عادی',
                'bronze'  => 'برنزی',
                'silver'  => 'نقره‌ای',
                'gold'    => 'طلایی',
            ];
            $groupBadges = [
                'regular' => '#e0e0e0',
                'bronze'  => '#cd7f32',
                'silver'  => '#a8a8a8',
                'gold'    => '#d4af37',
            ];
            foreach ($discounts as $d):
                $groupDisc = (int)($d['group_discount_amount'] ?? 0);
                $tierDisc  = (int)($d['tier_discount_amount']  ?? 0);
                $orderDisc = (int)($d['order_discount']        ?? 0);
                $total = $groupDisc + $tierDisc + $orderDisc;
                $bg    = $d['buyer_group'] ?? 'regular';
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $h(fin_jalali($d['issued_at'])) ?></td>
                    <td dir="ltr"><?= $h($d['invoice_number']) ?></td>
                    <td dir="ltr"><?= $h($d['order_number']) ?></td>
                    <td>
                        <?= $h($d['full_name']) ?>
                        <?= $d['company_name'] ? ' — ' . $h($d['company_name']) : '' ?>
                    </td>
                    <td>
                        <span style="background:<?= $groupBadges[$bg] ?? '#e0e0e0' ?>30;
                            border:1px solid <?= $groupBadges[$bg] ?? '#e0e0e0' ?>;
                            padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:700;">
                            <?= $groupLabels[$bg] ?? 'عادی' ?>
                        </span>
                    </td>
                    <td><?= $groupDisc > 0 ? $money($groupDisc) : '—' ?></td>
                    <td><?= $tierDisc > 0 ? $money($tierDisc) : '—' ?></td>
                    <td><?= $orderDisc > 0 ? $money($orderDisc) : '—' ?></td>
                    <td style="font-weight:800;"><?= $total > 0 ? $money($total) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="background:#f8fafc;font-weight:800;">
                <td colspan="6" style="text-align:left;">جمع کل تخفیف‌ها</td>
                <td><?= $money($totalGroupDisc) ?></td>
                <td><?= $money($totalTierDisc) ?></td>
                <td><?= $money($totalOrderDisc) ?></td>
                <td><?= $money($totalGroupDisc + $totalTierDisc + $totalOrderDisc) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
