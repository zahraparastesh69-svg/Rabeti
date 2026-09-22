<?php
require __DIR__ . '/../config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$catSlug = trim($_GET['cat'] ?? '');
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
        echo '<div class="empty-state"><h2>این دسته پیدا نشد</h2><a href="/products" class="btn btn-primary">همه محصولات</a></div>';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/main.php';
        exit;
    }
}

$sql = "
    SELECT p.id, p.slug, p.code, p.name, p.base_moq, pr.price, inv.qty_available,
           (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
";
$params = [];
if ($activeCat) {
    $sql .= " AND (p.category_id = ? OR c.parent_id = ?) ";
    $params[] = $activeCat['id'];
    $params[] = $activeCat['id'];
}
$sql .= " ORDER BY p.id DESC LIMIT 60";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = ($activeCat ? $activeCat['name'] . ' — ' : '') . 'کاتالوگ محصولات | رابطی';
ob_start();
?>

<!-- ▂▂▂ سربرگ + فیلتر دسته‌ها (چیپ‌های عکس‌دار مثل صفحه اصلی) ▂▂▂ -->
<section class="catalog-head">
    <h1 class="catalog-title"><?= $activeCat ? $h($activeCat['name']) : 'همه محصولات' ?></h1>
    <p class="catalog-sub"><?= count($products) ?> مدل — قیمت همکاری، مستقیم از تولیدی</p>
</section>

<section class="cats" role="navigation">
    <a href="/products" class="cat <?= !$activeCat ? 'is-active' : '' ?>">
        <span class="fb">✦</span><span class="nm">همه</span>
    </a>
    <?php foreach ($categories as $cat): ?>
        <a href="/products?cat=<?= $h($cat['slug']) ?>"
            class="cat <?= $activeCat && $activeCat['id'] === $cat['id'] ? 'is-active' : '' ?>">
            <?php if (!empty($cat['image_path'])): ?>
                <img src="<?= $h($cat['image_path']) ?>" alt="<?= $h($cat['name']) ?>">
            <?php else: ?>
                <span class="fb"><?= $h(trim($cat['icon'] ?? '') ?: '🧦') ?></span>
            <?php endif; ?>
            <span class="nm"><?= $h($cat['name']) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ گرید محصولات — کارت کامل با Stepper مثل صفحه اصلی ▂▂▂ -->
<?php if ($products): ?>
    <section class="section">
        <div class="grid">
            <?php foreach ($products as $i => $p): ?>
                <?php $stock = (int)($p['qty_available'] ?? 0); ?>
                <article class="card">
                    <a href="/product/<?= $h($p['slug']) ?>" class="card-img">
                        <?php if ($i === 0 && !$activeCat): ?><span class="badge">محبوب</span><?php endif; ?>
                        <?php if (!empty($p['img'])): ?>
                            <img src="<?= $h($p['img']) ?>" alt="<?= $h($p['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span>🧦</span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body">
                        <h3><a href="/product/<?= $h($p['slug']) ?>"><?= $h($p['name']) ?></a></h3>
                        <span class="card-attr">کد <?= $h($p['code']) ?> | حداقل <?= (int)$p['base_moq'] ?> جفت</span>
                        <div class="price">
                            <b><?= $p['price'] !== null ? number_format((float)$p['price']) : 'استعلام' ?> تومان</b>
                            <small>قیمت همکاری</small>
                        </div>
                        <span class="stock <?= $stock > 0 ? 'in' : 'out' ?>">
                            <?= $stock > 0 ? '● موجود در انبار' : '● ناموجود' ?>
                        </span>
                        <?php if ($stock > 0): ?>
                            <div class="stepper" data-min="1">
                                <button type="button" class="step plus">+</button>
                                <span class="step-num">۱</span>
                                <button type="button" class="step minus">−</button>
                            </div>
                            <button type="button" class="quick-add"
                                onclick="showToast('✓ به سبد اضافه شد — سبد در فاز بعد فعال می‌شود')">
                                افزودن سریع
                            </button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <div class="empty-state">
        <h2>فعلاً محصولی در این دسته نیست</h2>
        <a href="/products" class="btn btn-primary">مشاهده همه محصولات</a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
