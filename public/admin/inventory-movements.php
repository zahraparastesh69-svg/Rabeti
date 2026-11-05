<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/OrderService.php';

require_admin();

$db = db();

/* ─── فیلترها ─── */
$filterProduct = (int)($_GET['product_id'] ?? 0);
$filterType    = trim((string)($_GET['type'] ?? ''));
$filterFrom   = trim((string)($_GET['from'] ?? ''));
$filterTo     = trim((string)($_GET['to'] ?? ''));

$conditions = [];
$params = [];

if ($filterProduct > 0) {
    $conditions[] = 'sm.product_id = ?';
    $params[] = $filterProduct;
}
if ($filterType !== '' && in_array($filterType, ['stock_in','stock_out','reserve','release','allocate','ship','return_sellable','return_unsellable','adjust_physical','adjust_unsellable','production_in'], true)) {
    $conditions[] = 'sm.movement_type = ?';
    $params[] = $filterType;
}
if ($filterFrom !== '') {
    $conditions[] = 'sm.created_at >= ?';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $conditions[] = 'sm.created_at <= ?';
    $params[] = $filterTo . ' 23:59:59';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* ─── CSV export ─── */
$export = ($_GET['export'] ?? '') === 'csv';
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-movements-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($out, ['شناسه', 'محصول', 'کد', 'نوع', 'مقدار', 'موجودی فیزیکی', 'رزرو', 'غیرقابل فروش', 'مرجع', 'سفارش', 'دلیل', 'کاربر', 'زمان', 'IP']);
}

/* ─── صفحه‌بندی ─── */
$perPage = $export ? 100000 : 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* ─── کوئری ─── */
$sql = "
    SELECT sm.*,
           p.code AS product_code,
           p.name AS product_name,
           u.full_name AS creator_name
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.created_by
    $where
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

/* ─── فهرست محصولات برای dropdown ─── */
$products = $db->query("
    SELECT p.id, p.code, p.name
    FROM products p
    ORDER BY p.name
")->fetchAll();

$activeMenu = 'inventory-movements';
$pageTitle  = 'گردش موجودی';

if ($export) {
    foreach ($movements as $m) {
        fputcsv($out, [
            $m['id'],
            $m['product_name'],
            $m['product_code'],
            $m['movement_type'],
            $m['quantity'],
            $m['balance_physical'],
            $m['balance_reserved'],
            $m['balance_unsellable'],
            $m['reference_type'] . ':' . ($m['reference_id'] ?? ''),
            $m['order_id'] ?? '',
            $m['reason'],
            $m['creator_name'],
            $m['created_at'],
            $m['ip'],
        ]);
    }
    fclose($out);
    exit;
}

ob_start();
?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">عملیات</span>
        <h1>گردش موجودی</h1>
        <p>هر ورود/خروج/رزرو/آزادسازی/اصلاح موجودی به‌صورت خودکار در این فهرست ثبت می‌شود.</p>
    </div>
    <a href="?export=csv&<?= http_build_query(array_filter(['product_id' => $filterProduct, 'type' => $filterType, 'from' => $filterFrom, 'to' => $filterTo])) ?>"
       class="admin-soft-action">
        ⬇ خروجی CSV
    </a>
</div>

<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:flex-end;">
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">محصول:</label>
        <select name="product_id" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $filterProduct === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['code']) ?> — <?= htmlspecialchars($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">نوع گردش:</label>
        <select name="type" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <option value="stock_in" <?= $filterType === 'stock_in' ? 'selected' : '' ?>>ورود کالا</option>
            <option value="stock_out" <?= $filterType === 'stock_out' ? 'selected' : '' ?>>خروج کالا</option>
            <option value="reserve" <?= $filterType === 'reserve' ? 'selected' : '' ?>>رزرو</option>
            <option value="release" <?= $filterType === 'release' ? 'selected' : '' ?>>آزادسازی</option>
            <option value="allocate" <?= $filterType === 'allocate' ? 'selected' : '' ?>>تخصیص</option>
            <option value="ship" <?= $filterType === 'ship' ? 'selected' : '' ?>>ارسال</option>
            <option value="return_sellable" <?= $filterType === 'return_sellable' ? 'selected' : '' ?>>مرجوعی قابل فروش</option>
            <option value="return_unsellable" <?= $filterType === 'return_unsellable' ? 'selected' : '' ?>>مرجوعی غیرقابل فروش</option>
            <option value="adjust_physical" <?= $filterType === 'adjust_physical' ? 'selected' : '' ?>>اصلاح فیزیکی</option>
            <option value="production_in" <?= $filterType === 'production_in' ? 'selected' : '' ?>>ورود تولید</option>
        </select>
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">از تاریخ:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">تا تاریخ:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>

    <button type="submit" style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:6px;cursor:pointer;">
        فیلتر
    </button>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>زمان</th>
                <th>محصول</th>
                <th>نوع</th>
                <th>مقدار</th>
                <th>موجودی نهایی</th>
                <th>مرجع</th>
                <th>سفارش</th>
                <th>دلیل</th>
                <th>کاربر</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr><td colspan="10" style="text-align:center;padding:32px;color:#999;">هیچ گردشی یافت نشد.</td></tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="td-muted td-nowrap"><?= jdate_fa($m['created_at'], true) ?></td>
                        <td>
                            <b><?= htmlspecialchars($m['product_code'] ?? '—') ?></b><br>
                            <span style="font-size:.7rem;color:#999;"><?= htmlspecialchars($m['product_name'] ?? '') ?></span>
                        </td>
                        <td>
                            <span class="st-badge <?= in_array($m['movement_type'], ['reserve','ship','stock_out','allocate']) ? 'st-rejected' : 'st-converted' ?>">
                                <?= htmlspecialchars($m['movement_type']) ?>
                            </span>
                        </td>
                        <td dir="ltr" style="text-align:right;<?= $m['quantity'] < 0 ? 'color:#c62828;' : '' ?>">
                            <?= $m['quantity'] >= 0 ? '+' : '' ?><?= (int)$m['quantity'] ?>
                        </td>
                        <td dir="ltr" style="text-align:right;">
                            فیزیکی: <?= (int)$m['balance_physical'] ?>
                            <br><span style="font-size:.7rem;color:#999;">رزرو: <?= (int)$m['balance_reserved'] ?></span>
                        </td>
                        <td dir="ltr" style="text-align:right;font-size:.72rem;">
                            <?= htmlspecialchars($m['reference_type'] ?? '—') ?>:<?= htmlspecialchars($m['reference_id'] ?? '') ?>
                        </td>
                        <td dir="ltr" style="text-align:right;">
                            <?php if ($m['order_id']): ?>
                                <a href="/admin/order-detail?id=<?= (int)$m['order_id'] ?>" style="color:var(--teal-700);">
                                    #<?= (int)$m['order_id'] ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:.72rem;"><?= htmlspecialchars($m['reason'] ?? '') ?></td>
                        <td style="font-size:.72rem;"><?= htmlspecialchars($m['creator_name'] ?? 'سیستم') ?></td>
                        <td dir="ltr" style="text-align:right;font-size:.7rem;color:#999;"><?= htmlspecialchars($m['ip'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (count($movements) === $perPage): ?>
    <div style="text-align:center;margin-top:16px;">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn">
            صفحهٔ بعد ←
        </a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
