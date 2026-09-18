<?php
require __DIR__ . '/../config/database.php';

// ─── بنر هیرو ───
 $banner = db()->query("SELECT * FROM banners WHERE position='hero' AND is_active=1 ORDER BY sort_order LIMIT 1")->fetch();
 $banner = $banner ?: [];
 $bannerImage = trim($banner['image'] ?? '');
 $bannerLink  = $banner['link'] ?? '/products';

// ─── دسته‌بندی‌ها ───
 $categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

// ─── محصولات ویژه ───
 $products = db()->query("
    SELECT p.id, p.code, p.name, p.base_moq, pr.price, inv.qty_available
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id
    LIMIT 4
")->fetchAll();

 $pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو (سبک B2B امیران) ▂▂▂ -->
<section class="hero-container">
    <div class="hero-content">
        <h1 class="hero-title">پلتفرم عمده‌فروشی مستقیم از کارخانه</h1>
        <p class="hero-desc">خرید عمده جوراب بدون واسطه، با کیفیت تضمینی و قیمت دست اول. ما به صادراتکنندگان و عمده‌فروشان کمک می‌کنیم تا کسب‌وکار خود را گسترش دهند.</p>
        <a href="<?= htmlspecialchars($bannerLink) ?>" class="btn-hero">مشاهده کاتالوگ <span class="arrow">←</span></a>
    </div>
    <div class="hero-image">
        <?php if ($bannerImage): ?>
            <img class="banner-img" src="<?= htmlspecialchars($bannerImage) ?>" alt="بنر اصلی" onerror="this.style.display='none';">
        <?php else: ?>
            <div class="hero-placeholder">🧦</div>
        <?php endif; ?>
    </div>
</section>

<!-- ▂▂▂ دسته‌بندی‌ها ▂▂▂ -->
<section class="section">
    <div class="section-head">
        <h2 class="section-title">دسته‌بندی محصولات</h2>
        <p class="section-sub">روی دسته بزنید تا مدل‌های آن را ببینید</p>
    </div>
    <div class="cat-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="/products?cat=<?= htmlspecialchars($cat['slug']) ?>" class="cat-card">
                <div class="cat-icon"><?= htmlspecialchars($cat['icon'] ?? '🧦') ?></div>
                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ مزایا (سبک سایتی که عکسش را فرستادید) ▂▂▂ -->
<section class="features-section">
    <div class="feature-row">
        <div class="feature-text">
            <h2>چرا جوراب رابطی؟</h2>
            <p>ما به عنوان یک پلتفرم B2B، تولیدکنندگان را مستقیماً به خریداران عمده متصل می‌کنیم تا با راندمان بالاتر، محصولات درجه یک را با بهترین قیمت تهیه کنید.</p>
        </div>
        <div class="feature-cards">
            <div class="f-card"><span>⏱️</span><h3>ثبت سفارش ۲۴ ساعته</h3></div>
            <div class="f-card"><span>🏷️</span><h3>برند اختصاصی شما</h3></div>
            <div class="f-card"><span>🚀</span><h3>ارسال فوری سراسری</h3></div>
            <div class="f-card"><span>✨</span><h3>تضمین کیفیت</h3></div>
        </div>
    </div>
</section>

<!-- ▂▂▂ محصولات ویژه ▂▂▂ -->
<section class="section">
    <div class="section-head">
        <h2 class="section-title">محصولات پرفروش</h2>
        <p class="section-sub">عمده و با قیمت کارخانه</p>
    </div>
    <div class="prod-grid">
        <?php foreach ($products as $p): ?>
            <a href="/products/<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="prod-card">
                <div class="prod-img">🧦</div>
                <div class="prod-info">
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-code">کد: <?= htmlspecialchars($p['code']) ?></div>
                    <div class="prod-price">
                        <?= $p['price'] !== null ? number_format((float)$p['price']) . ' تومان' : 'استعلام قیمت' ?>
                    </div>
                    <div class="prod-moq">حداقل سفارش: <?= (int)$p['base_moq'] ?> جفت</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php
 $content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
?>