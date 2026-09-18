<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="hero">
    <h1>محصولات تولیدی جوراب رابطی با کیفیت تضمینی و قیمت عمده</h1>
 <p>
    خرید عمده جوراب مستقیم از تولیدکننده — کاتالوگ کامل، قیمت ویژه
    فروشگاه‌داران و پخش‌کنندگان، ارسال به سراسر کشور
</p>
    <div class="hero-actions">
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
        <a href="/register" class="btn btn-ghost">به‌عنوان تولیدکننده شروع کنید</a>
    </div>
    <!-- 📷 عکس واقعی کارخانه — فعلاً Placeholder -->
    <div class="hero-img">🧦 🏭</div>
</section>

<!-- ▂▂▂ دسته‌بندی محصولات ▂▂▂ -->
<section class="section">
    <h2 class="section-title">دسته‌بندی محصولات</h2>
    <p class="section-sub">انواع جوراب تولیدی — عمده و با قیمت کارخانه</p>

    <div class="cat-grid">
        <?php
        // 🔧 موقت — بعداً از جدول categories دیتابیس خوانده می‌شود
        $categories = [
            ['🧦', 'مردانه',    ['نخی', 'اسپرت', 'ساق‌بلند', 'مجلسی']],
            ['🧦', 'زنانه',     ['نازک', 'پشمی', 'ساق‌کوتاه', 'رنگارنگ']],
            ['🧒', 'بچگانه',    ['نوزادی', 'کودکانه', 'مدرسه']],
            ['⚽', 'ورزشی',     ['فوتبال', 'توپ‌دار', 'کوهنوردی']],
            ['🧶', 'پشمی',      ['زمستانی', 'جیر', 'برک']],
            ['🎁', 'ست و پک',   ['ست ۳عددی', 'پک هدیه', 'جعبه عمده']],
        ];
        foreach ($categories as [$icon, $name, $subs]):
        ?>
        <div class="cat-card" role="button" tabindex="0">
            <div class="cat-icon"><?= $icon ?></div>
            <div class="cat-name"><?= $name ?></div>
            <span class="cat-badge"><?= count($subs) ?> زیردسته</span>

            <div class="cat-subs">
                <div class="cat-chips">
                    <?php foreach ($subs as $sub): ?>
                    <a href="/products" class="cat-chip"><?= $sub ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
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
