<?php
require __DIR__ . '/../config/database.php';

/* ─────────── فیلتر دسته از URL: ?cat=men ─────────── */
$catSlug = trim($_GET['cat'] ?? '');
$activeCat = null;

/* دسته‌بندی‌ها برای نوار فیلتر */
$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

/* اعتبارسنجی دسته انتخابی (Prepared — نه مستقیم در کوئری!) */
if ($catSlug !== '') {
    $stmt = db()->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$catSlug]);
    $activeCat = $stmt->fetch();
    if (!$activeCat) {
        http_response_code(404);
        $pageTitle = 'دسته یافت نشد';
        ob_start();
        echo '<div class="empty-state"><div class="empty-icon">🔍</div><h2>این دسته پیدا نشد</h2><a href="/products" class="btn btn-orange">همه محصولات</a></div>';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/main.php';
        exit;
    }
}

/* ─────────── کوئری محصولات ─────────── */
$sql = "
    SELECT p.id, p.code, p.name, p.slug, p.base_moq,
           pr.price, inv.qty_available,
           c.name AS cat_name, c.slug AS cat_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
";
$params = [];

if ($activeCat) {
    // دسته انتخابی + زیردسته‌هایش
    $sql .= " AND (p.category_id = ? OR c.parent_id = ?) ";
    $params[] = $activeCat['id'];
    $params[] = $activeCat['id'];
}

$sql .= " ORDER BY p.id DESC LIMIT 60";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = ($activeCat ? $activeCat['name'] . ' — ' : '') . 'کاتالوگ محصولات | جوراب رابطی';
ob_start();
?>

<!-- ▂▂▂ سرصفحه کاتالوگ ▂▂▂ -->
<section class="page-head">
    <h1 class="section-title"><?= $activeCat ? htmlspecialchars($activeCat['name']) : 'همه محصولات' ?></h1>
    <p class="section-sub"><?= count($products) ?> مدل — قیمت عمده کارخانه</p>
</section>

<!-- ▂▂▂ نوار فیلتر دسته‌ها (چیپ‌های اسکرولی) ▂▂▂ -->
<section class="filter-bar">
    <a href="/products" class="filter-chip <?= !$activeCat ? 'active' : '' ?>">همه</a>
    <?php foreach ($categories as $cat): ?>
        <a href="/products?cat=<?= $cat['slug'] ?>"
            class="filter-chip <?= $activeCat && $activeCat['id'] === $cat['id'] ? 'active' : '' ?>">
            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ گرید محصولات ▂▂▂ -->
<?php if ($products): ?>
    <section class="prod-grid">
        <?php foreach ($products as $p): ?>
            <a href="/product/<?= $p['slug'] ?>" class="prod-card">
                <div class="prod-img">🧦</div>
                <div class="prod-info">
                    <div class="prod-cat"><?= htmlspecialchars($p['cat_name']) ?></div>
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-code">کد: <?= $p['code'] ?></div>
                    <div class="prod-row">
                        <span class="prod-price"><?= number_format($p['price']) ?> تومان</span>
                        <span class="prod-stock <?= $p['qty_available'] > 0 ? 'in' : 'out' ?>">
                            <?= $p['qty_available'] > 0 ? 'موجود' : 'ناموجود' ?>
                        </span>
                    </div>
                    <div class="prod-moq">حداقل: <?= $p['base_moq'] ?> جفت</div>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h2>فعلاً محصولی در این دسته نیست</h2>
        <a href="/products" class="btn btn-orange">مشاهده همه محصولات</a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
