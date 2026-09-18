<?php
$pageTitle = 'جوراب‌لند — بازار عمده جوراب';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="rounded-3xl overflow-hidden mb-12 bg-white border border-ink-200 shadow-card relative">
    <!-- نوار رنگی بالای بنر -->
    <div class="h-1.5 bg-gradient-to-l from-brand via-brand-hover to-accent-orange"></div>

    <div class="p-8 md:p-14 grid md:grid-cols-2 gap-8 items-center">
        <div class="text-center md:text-right">
            <span class="inline-block text-xs font-bold text-brand bg-brand-light rounded-full px-4 py-1.5 mb-5">
                ⚡ بازار آنلاین عمده جوراب
            </span>

            <h1 class="text-3xl md:text-5xl font-black leading-[1.35] text-ink-900 mb-4">
                خرید عمده جوراب
                <span class="text-brand">بدون واسطه</span>
                <br class="hidden md:block">
                با قیمت تولیدکننده
            </h1>

            <p class="text-ink-500 text-base md:text-lg leading-8 max-w-lg mb-7 md:mx-0 mx-auto">
                کاتالوگ کامل، قیمت ویژه اعضا، ثبت سفارش در چند ثانیه و ارسال به سراسر کشور —
                مخصوص فروشگاه‌داران، پخش‌کنندگان و برندهای در حال رشد.
            </p>

            <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                <a href="/products"
                    class="bg-brand hover:bg-brand-hover text-white font-black px-8 py-4 rounded-2xl shadow-lg shadow-brand/25 transition active:scale-95">
                    مشاهده کاتالوگ ←
                </a>
                <a href="/register"
                    class="bg-white border-2 border-ink-200 hover:border-brand text-ink-900 hover:text-brand font-bold px-8 py-4 rounded-2xl transition active:scale-95">
                    ثبت‌نام عمده‌فروشی
                </a>
            </div>
        </div>

        <!-- تصویر/موجوعیت هیرو -->
        <div class="hidden md:flex items-center justify-center">
            <div class="w-64 h-64 bg-gradient-to-b from-brand-light to-white rounded-full flex items-center justify-center text-[9rem] shadow-inner">
                🧦
            </div>
        </div>
    </div>

    <!-- آمار -->
    <div class="border-t border-ink-200 grid grid-cols-3 divide-x divide-x-reverse divide-ink-200 text-center">
        <div class="py-5">
            <div class="text-xl md:text-3xl font-black text-brand">+۱۲۰</div>
            <div class="text-[11px] md:text-xs text-ink-500 mt-1">مدل محصول</div>
        </div>
        <div class="py-5">
            <div class="text-xl md:text-3xl font-black text-brand">+۸۵۰</div>
            <div class="text-[11px] md:text-xs text-ink-500 mt-1">فروشگاه فعال</div>
        </div>
        <div class="py-5">
            <div class="text-xl md:text-3xl font-black text-brand">۲۴h</div>
            <div class="text-[11px] md:text-xs text-ink-500 mt-1">ارسال سفارش</div>
        </div>
    </div>
</section>

<!-- ▂▂▂ دسته‌بندی‌ها ▂▂▂ -->
<section class="mb-12">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl md:text-2xl font-black text-ink-900">دسته‌بندی‌ها</h2>
        <a href="/products" class="text-sm text-brand font-bold hover:underline">مشاهده همه ←</a>
    </div>
    <div class="flex md:grid md:grid-cols-4 gap-3 overflow-x-auto md:overflow-visible pb-2 -mx-4 px-4 md:mx-0 md:px-0">
        <?php
        $categories = [
            ['🧦', 'مردانه',  'bg-brand-light'],
            ['🩱', 'زنانه',   'bg-pink-50'],
            ['🧒', 'بچگانه',  'bg-sky-50'],
            ['⚽', 'ورزشی',   'bg-amber-50'],
        ];
        foreach ($categories as [$icon, $name, $bg]):
        ?>
            <a href="/products"
                class="card-lift shrink-0 w-32 md:w-auto bg-white border border-ink-200 rounded-2xl p-5 text-center shadow-card">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl <?= $bg ?> flex items-center justify-center text-3xl">
                    <?= $icon ?>
                </div>
                <div class="font-bold text-sm text-ink-900"><?= $name ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ پرفروش‌ها ▂▂▂ -->
<section class="mb-12">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl md:text-2xl font-black text-ink-900">پرفروش‌های این هفته 🔥</h2>
        <a href="/products" class="text-sm text-brand font-bold hover:underline">مشاهده همه ←</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5">
        <?php
        // 🔧 داده ساختگی — بعداً از Mock/دیتابیس
        $products = [
            ['جوراب نخی مردانه', 'S125', '۱۸۵,۰۰۰', '۴۸'],
            ['جوراب اسپرت',      'S130', '۲۲۰,۰۰۰', '۳۲'],
            ['جوراب ساق کوتاه',  'S142', '۱۶۵,۰۰۰', '۶۰'],
            ['جوراب پشمی',       'S118', '۲۹۰,۰۰۰', '۲۴'],
        ];
        foreach ($products as $i => [$name, $code, $price, $moq]):
            $isTop = ($i === 0); // اولین محصول = بج «پرفروش‌ترین» نارنجی
        ?>
            <a href="/products" class="card-lift bg-white border border-ink-200 rounded-2xl overflow-hidden shadow-card">
                <div class="relative aspect-square bg-ink-100 flex items-center justify-center text-6xl">
                    🧦
                    <?php if ($isTop): ?>
                        <span class="absolute top-3 right-3 bg-accent-orange text-white text-[10px] font-bold rounded-full px-3 py-1">
                            پرفروش‌ترین
                        </span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <div class="font-bold text-sm text-ink-900 mb-1"><?= $name ?></div>
                    <div class="text-[10px] text-ink-400 mb-3">کد: <?= $code ?></div>
                    <div class="flex items-end justify-between">
                        <div>
                            <div class="text-[10px] text-ink-400">عمده (جعبه)</div>
                            <div class="font-black text-brand"><?= $price ?> <span class="text-[10px] font-normal text-ink-400">تومان</span></div>
                        </div>
                        <div class="text-[10px] bg-ink-100 rounded-lg px-2 py-1 text-ink-500">
                            MOQ <?= $moq ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ مزیت‌ها ▂▂▂ -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-12">
    <?php
    $features = [
        ['🏭', 'قیمت تولیدکننده', 'بدون واسطه'],
        ['📦', 'MOQ منصفانه', 'شروع از یک جعبه'],
        ['🚚', 'ارسال سریع', 'سراسر کشور'],
        ['🏷️', 'برند اختصاصی', 'لیبل و بسته‌بندی شما'],
    ];
    foreach ($features as [$icon, $title, $desc]):
    ?>
        <div class="bg-white border border-ink-200 rounded-2xl p-4 md:p-5 text-center shadow-card">
            <div class="w-11 h-11 mx-auto mb-2 rounded-xl bg-brand-light flex items-center justify-center text-xl"><?= $icon ?></div>
            <div class="font-bold text-sm text-ink-900"><?= $title ?></div>
            <div class="text-[11px] text-ink-500 mt-1"><?= $desc ?></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ CTA سفارش اختصاصی ▂▂▂ -->
<section class="rounded-3xl overflow-hidden bg-brand relative mb-4">
    <div class="absolute -top-16 -left-16 w-64 h-64 bg-white/10 rounded-full"></div>
    <div class="absolute -bottom-20 -right-10 w-72 h-72 bg-black/10 rounded-full"></div>
    <div class="relative p-8 md:p-14 text-center text-white">
        <h2 class="text-2xl md:text-4xl font-black mb-3">برند خودتان را بسازید</h2>
        <p class="text-brand-light mb-7">تولید جوراب با لوگو، لیبل و بسته‌بندی اختصاصی — از ۵۰۰ جفت</p>
        <a href="/custom-order"
            class="inline-block bg-accent-orange hover:brightness-105 text-white font-black px-8 py-4 rounded-2xl shadow-lg transition active:scale-95">
            درخواست سفارش اختصاصی ←
        </a>
    </div>
</section>

<?php<?php
 $pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="hero">
    <span class="hero-badge">⚡ تولید مستقیم از کارخانه</span>
    <h1>محصولات تولیدی جوراب رابطی<br>با کیفیت تضمینی و قیمت عمده</h1>
    <p>
        خرید مستقیم از تولیدکننده بدون واسطه — کاتالوگ کامل محصولات،
        قیمت ویژه فروشگاه‌داران و پخش‌کنندگان، ارسال به سراسر کشور
    </p>
    <div class="hero-actions">
        <a href="/products" class="btn btn-orange">مشاهده محصولات</a>
        <a href="/register" class="btn btn-ghost">ثبت‌نام عمده‌فروشی</a>
    </div>
    <!-- 📷 تصویر کارخانه — فعلاً Placeholder -->
    <div class="hero-img">🏭</div>
</section>

<!-- ▂▂▂ دسته‌بندی محصولات ▂▂▂ -->
<section class="section">
    <h2 class="section-title">دسته‌بندی محصولات</h2>
    <p class="section-sub">روی هر دسته بزنید تا زیردسته‌ها باز شود</p>

    <div class="cat-grid">
        <?php
        // 🔧 موقت — بعداً از دیتابیس خوانده می‌شود
        $categories = [
            ['🧦', 'مردانه',  ['نخی', 'اسپرت', 'ساق‌بلند', 'مجلسی']],
            ['🩱', 'زنانه',   ['نازک', 'پشمی', 'ساق‌کوتاه', 'رنگارنگ']],
            ['🧒', 'بچگانه',  ['نوزادی', 'کودکانه', 'مدرسه']],
            ['⚽', 'ورزشی',   ['فوتبال', 'توپ‌دار', 'کوهنوردی']],
            ['🧶', 'پشمی',   ['زمستانی', 'جیر', 'برک']],
            ['🎁', 'ست و پک', ['ست ۳عددی', 'پک هدیه', 'عمده جعبه']],
        ];
        foreach ($categories as [$icon, $name, $subs]):
            $count = count($subs);
        ?>
        <div class="cat-card" role="button" tabindex="0">
            <div class="cat-icon"><?= $icon ?></div>
            <div class="cat-name"><?= $name ?></div>
            <span class="cat-badge"><?= $count ?> زیردسته</span>

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
            چون مستقیم از خط تولید کارخانه تحویل می‌گیرید — بدون واسطه،
            بدون حاشیه سود اضافه، با کنترل کیفیت مرحله‌به‌مرحله و امکان
            تولید با برند اختصاصی شما از ۵۰۰ جفت به بالا
        </p>
        <a href="/products" class="btn btn-orange">مشاهده محصولات</a>
    </div>
</section>

<?php
 $content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
