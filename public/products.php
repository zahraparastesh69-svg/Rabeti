<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$catSlug   = trim($_GET['cat'] ?? '');
$activeCat = null;

$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

if ($catSlug !== '') {
    $stmt = db()->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$catSlug]);
    $activeCat = $stmt->fetch();
    if (!$activeCat) {
        http_response_code(404);
        $pageTitle = 'دسته یافت نشد';
        ob_start();
        echo '<div class="empty-state"><h2>این دسته پیدا نشد</h2><a href="/products" class="btn btn-orange">همه محصولات</a></div>';
        $content = ob_get_clean();
        require ROOT_PATH . '/views/layouts/main.php';
        exit;
    }
}

/* محصولات — با عکس از DB */
$sql = "
    SELECT p.id, p.code, p.name, p.slug, p.base_moq,
           pr.price, inv.qty_available,
           c.name AS cat_name,
           (SELECT image_path FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
";
$params = [];
if ($activeCat) {
    $sql .= " AND p.category_id = ? ";
    $params[] = $activeCat['id'];
}
$sql .= " ORDER BY p.id DESC LIMIT 60";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = ($activeCat ? $activeCat['name'] . ' — ' : '') . 'کاتالوگ محصولات | رابطی';
ob_start();
?>

<!-- ▂▂▂ سربرگ ▂▂▂ -->
<section class="page-head">
    <h1 class="section-title"><?= $activeCat ? $h($activeCat['name']) : 'همه محصولات' ?></h1>
    <p class="section-sub"><?= count($products) ?> مدل — قیمت همکاری، مستقیم از تولیدی</p>
</section>

<!-- ▂▂▂ فیلتر دسته‌ها ▂▂▂ -->
<section class="filter-bar">
    <a href="/products" class="filter-chip <?= !$activeCat ? 'active' : '' ?>">همه</a>
    <?php foreach ($categories as $cat): ?>
        <a href="/products?cat=<?= $h($cat['slug']) ?>"
            class="filter-chip <?= $activeCat && $activeCat['id'] === $cat['id'] ? 'active' : '' ?>">
            <?= trim($cat['icon'] ?? '') ?: '🧦' ?> <?= $h($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ گرید محصولات ▂▂▂ -->
<?php if ($products): ?>
    <section class="prod-grid">
        <?php foreach ($products as $index => $p): ?>
            <?php $stock = (int)($p['qty_available'] ?? 0); ?>
            <article class="prod-card" data-product-id="<?= (int)$p['id'] ?>">

                <a href="/product/<?= $h($p['slug']) ?>" class="prod-img">
                    <?php if ($index === 0 && !$activeCat): ?><span class="prod-badge">محبوب</span><?php endif; ?>
                    <?php if (!empty($p['img'])): ?>
                        <img src="<?= $h($p['img']) ?>" alt="<?= $h($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span>🧦</span>
                    <?php endif; ?>
                </a>

                <div class="prod-info">
                    <div class="prod-cat"><?= $h($p['cat_name']) ?></div>
                    <div class="prod-name"><a href="/product/<?= $h($p['slug']) ?>"><?= $h($p['name']) ?></a></div>
                    <div class="prod-code">کد <?= $h($p['code']) ?> | حداقل <?= (int)$p['base_moq'] ?> جفت</div>

                    <div class="prod-price">
                        <?= $p['price'] !== null ? number_format((float)$p['price']) : 'استعلام' ?> تومان
                        <small>قیمت همکاری</small>
                    </div>

                    <div class="prod-stock <?= $stock > 0 ? 'in' : 'out' ?>">
                        <?= $stock > 0 ? '● موجود در انبار (' . number_format($stock) . ' جفت)' : '● ناموجود' ?>
                    </div>

                    <?php if ($stock > 0): ?>
                        <div class="stepper" data-min="1">
                            <button type="button" class="step plus">+</button>
                            <input type="text" inputmode="numeric" class="step-num step-input" value="۱">
                            <button type="button" class="step minus">−</button>
                        </div>
                        <button type="button" class="quick-add">افزودن سریع</button>
                    <?php endif; ?>
                </div>

            </article>
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
require ROOT_PATH . '/views/layouts/main.php';
