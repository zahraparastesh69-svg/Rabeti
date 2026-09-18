<?php
require __DIR__ . '/../config/database.php';

// بنر هیرو
$banner = db()->query("SELECT * FROM banners WHERE position='hero' AND is_active=1 ORDER BY sort_order LIMIT 1")->fetch();

// دسته‌بندی‌ها
$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

// پرفروش‌ها: ۴ محصول با قیمت و موجودی
$products = db()->query("
    SELECT p.id, p.code, p.name, p.base_moq, pr.price, inv.qty_available
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id
    LIMIT 4
")->fetchAll();
?>
<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="hero">
    <h1><?= htmlspecialchars($banner['title'] ?? 'تولیدی جوراب رابطی') ?></h1>
    <p><?= htmlspecialchars($banner['subtitle'] ?? '') ?></p>
    <div class="hero-actions">
        <a href="<?= htmlspecialchars($banner['link'] ?? '/products') ?>" class="btn btn-orange">مشاهده محصولات ←</a>
    </div>
    <div class="hero-img">🧦</div>
</section>
<!-- ▂▂▂ دسته‌بندی‌ها ▂▂▂ -->
<section class="section">
    <div class="section-head">
        <h2 class="section-title">دسته‌بندی محصولات</h2>
        <p class="section-sub">روی دسته بزنید تا مدل‌های آن را ببینید</p>
    </div>

    <div class="cat-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="/products?cat=<?= $cat['slug'] ?>" class="cat-card">
                <div class="cat-icon"><?= $cat['icon'] ?? '🧦' ?></div>
                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">محصولات ویژه</h2>
        <p class="section-sub">پرفروش‌های تولیدی — عمده و با قیمت کارخانه</p>
    </div>

    <div class="prod-grid">
        <?php foreach ($products as $p): ?>
            <a href="/products/<?= $p['slug'] ?? $p['id'] ?>" class="prod-card">
                <div class="prod-img">🧦</div>
                <div class="prod-info">
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-code">کد: <?= $p['code'] ?></div>
                    <div class="prod-price"><?= number_format($p['price']) ?> تومان</div>
                    <div class="prod-moq">حداقل سفارش: <?= $p['base_moq'] ?> جفت</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ چرا جوراب رابطی؟ ▂▂▂ -->
<section class="section">
    <div class="why-card">
        <div class="why-icon">🛡️</div>
        <h2>چرا جوراب رابطی؟</h2>
        <p>
            چون مستقیم از خط تولید کارخانه خرید می‌کنید — بدون واسطه،
            با قیمت عمده شفاف، کنترل کیفیت تضمینی و امکان تولید
            با برند اختصاصی شما از ۵۰۰ جفت به بالا
        </p>
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
