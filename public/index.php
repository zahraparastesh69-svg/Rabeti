<?php
require __DIR__ . '/../config/database.php';

// ─── بنر هیرو ───
$banner = db()->query("SELECT * FROM banners WHERE position='hero' AND is_active=1 ORDER BY sort_order LIMIT 1")->fetch();
$banner = $banner ?: [];
$bannerImage = trim($banner['image'] ?? ''); // ← نام ستون تصویر را مطابق دیتابیس خود تنظیم کنید
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
?>
<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو: بنر بزرگ بدون کادر اضافه ▂▂▂ -->
<section class="hero-container">
    <div class="hero-banner">
        <?php if ($bannerImage): ?>
            <img class="banner-img" src="<?= htmlspecialchars($bannerImage) ?>" alt="بنر اصلی"
                onerror="this.style.display='none'; document.getElementById('bannerFallback').style.display='flex';">
        <?php endif; ?>

        <div class="banner-fallback<?= $bannerImage ? '' : ' show' ?>" id="bannerFallback"
            <?= $bannerImage ? 'style="display:none"' : '' ?>>
            <span class="banner-inner-icon">🧦</span>
            <h1>تولیدی جوراب رابطی</h1>
            <p>خرید عمده بدون واسطه، مستقیم از خط تولید کارخانه</p>
        </div>
    </div>

    <div class="hero-cta-wrap">
        <a href="<?= htmlspecialchars($bannerLink) ?>" class="btn-hero">
            مشاهده محصولات <span class="arrow">←</span>
        </a>
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
            <?php
            $icon = trim($cat['icon'] ?? '');
            $iconIsImage = $icon !== '' && (
                preg_match('~^https?://~i', $icon) ||
                preg_match('~\.(png|jpe?g|webp|svg|gif)(\?.*)?$~i', $icon)
            );
            ?>
            <a href="/products?cat=<?= htmlspecialchars($cat['slug']) ?>" class="cat-card">
                <div class="cat-icon">
                    <?php if ($iconIsImage): ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($cat['name']) ?>"
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span class="cat-icon-fallback"><?= htmlspecialchars($cat['name']) ?></span>
                    <?php elseif ($icon !== ''): ?>
                        <span class="cat-emoji"><?= htmlspecialchars($icon) ?></span>
                    <?php else: ?>
                        <span class="cat-icon-fallback show"><?= htmlspecialchars($cat['name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ چرا جوراب رابطی؟ (کارت‌های سه‌بعدی) ▂▂▂ -->
<section class="features-3d-section">
    <div class="section-head">
        <h2 class="section-title">چرا جوراب رابطی؟</h2>
        <p class="section-sub">چهار دلیل برای خرید مستقیم از کارخانه</p>
    </div>
    <div class="features-grid">
        <div class="feature-card-3d">
            <span class="feature-icon">⏱️</span>
            <h3>ثبت سفارش ۲۴ ساعته</h3>
            <p>سامانه اتوماتیک دریافت سفارش عمده در تمامی ساعات شبانه‌روز</p>
        </div>
        <div class="feature-card-3d">
            <span class="feature-icon">🏷️</span>
            <h3>تولید با طرح و برند شما</h3>
            <p>بافت سفارشی و بسته‌بندی با لیبل و برند اختصاصی شما</p>
        </div>
        <div class="feature-card-3d">
            <span class="feature-icon">🚀</span>
            <h3>تحویل فوری به سراسر کشور</h3>
            <p>ارسال سریع بار از کارخانه به باربری و تحویل در کوتاه‌ترین زمان</p>
        </div>
        <div class="feature-card-3d">
            <span class="feature-icon">✨</span>
            <h3>تضمین کیفیت و قیمت</h3>
            <p>استفاده از نخ و کش درجه یک با تضمین مستقیم کارخانه</p>
        </div>
    </div>
</section>

<!-- ▂▂▂ محصولات ویژه ▂▂▂ -->
<section class="section">
    <div class="section-head">
        <h2 class="section-title">محصولات ویژه</h2>
        <p class="section-sub">پرفروش‌های تولیدی — عمده و با قیمت کارخانه</p>
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
