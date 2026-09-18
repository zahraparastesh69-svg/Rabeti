<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ بنر اسلایدی / هیرو موبایلی ▂▂▂ -->
<section class="hero-banner">
    <!-- 📷 وقتی بک‌اند متصل شد: <img src="/uploads/banners/hero1.jpg" alt="تولیدی جوراب رابطی"> -->
    <div class="hero-overlay">
        <span class="hero-tag">تولید و پخش عمده</span>
        <h1 class="hero-title">مستقیم از خط تولید، بدون واسطه</h1>
        <p class="hero-subtitle">تضمین کیفیت بافت و بهترین قیمت برای بنکداران</p>
        <a href="/products" class="hero-btn">کاتالوگ محصولات ←</a>
    </div>
</section>

<!-- ▂▂▂ دسته‌بندی محصولات (گرید اپلیکیشنی تمیز) ▂▂▂ -->
<section class="app-section">
    <div class="section-head">
        <h2>دسته‌بندی محصولات</h2>
        <a href="/products">همه دسته‌ها</a>
    </div>

    <div class="cat-grid">
        <?php
        // 🔧 ساختار آماده برای اتصال دیتابیس (آیکون، نام، عکس و مسیر)
        $categories = [
            ['icon' => '🧦', 'name' => 'مردانه',   'count' => '۴ زیردسته', 'url' => '/products?cat=men'],
            ['icon' => '🧦', 'name' => 'زنانه',    'count' => '۴ زیردسته', 'url' => '/products?cat=women'],
            ['icon' => '🧒', 'name' => 'بچگانه',   'count' => '۳ زیردسته', 'url' => '/products?cat=kids'],
            ['icon' => '⚽', 'name' => 'ورزشی',    'count' => '۳ زیردسته', 'url' => '/products?cat=sport'],
            ['icon' => '🧶', 'name' => 'پشمی',     'count' => '۳ زیردسته', 'url' => '/products?cat=wool'],
            ['icon' => '🎁', 'name' => 'ست و پک',  'count' => 'پک جعبه‌ای', 'url' => '/products?cat=packs'],
        ];

        foreach ($categories as $cat):
        ?>
            <a href="<?= htmlspecialchars($cat['url']) ?>" class="cat-item">
                <div class="cat-img-box">
                    <!-- 📷 بعداً در صورت داشتن عکس: <img src="/uploads/..." alt="<?= $cat['name'] ?>"> -->
                    <span class="cat-icon-placeholder"><?= $cat['icon'] ?></span>
                </div>
                <span class="cat-label"><?= htmlspecialchars($cat['name']) ?></span>
                <span class="cat-sublabel"><?= htmlspecialchars($cat['count']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ بنر مزیت همکاری (چرا جوراب رابطی) ▂▂▂ -->
<section class="app-section">
    <div class="why-card">
        <div class="why-badge">🛡️ مزیت تولیدی</div>
        <h3>چرا خرید عمده از رابطی؟</h3>
        <p>
            تولید مستقیم با نخ پنبه مرغوب، قیمت رقابتی درب کارخانه، تضمین مرجوعی در صورت عدم رضایت، و سفارش اختصاصی برند از ۵۰۰ جفت به بالا.
        </p>
        <div class="why-footer">
            <a href="tel:09120000000" class="btn btn-outline">تماس با کارخانه</a>
            <a href="/products" class="btn btn-orange">مشاهده کاتالوگ</a>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
