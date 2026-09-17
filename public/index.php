<?php
 $pageTitle = 'تولیدی جوراب رابطی — بازار عمده جوراب با قیمت تولیدکننده';
 $pageDescription = 'خرید عمده جوراب مردانه، زنانه و بچگانه با قیمت تولیدکننده و تخفیف پلکانی. سفارش تولید اختصاصی با برند شما.';
ob_start();
?>

<!-- ▂▂▂ بنر اصلی ▂▂▂ -->
<section class="relative overflow-hidden rounded-2xl border border-line bg-card p-10 md:p-16 mb-12">
    <!-- هاله‌های پس‌زمینه -->
    <div class="absolute -top-24 -right-24 w-72 h-72 bg-brand opacity-25 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-teal opacity-15 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="relative">
        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-ink/60 px-4 py-1.5 text-xs font-bold text-teal mb-6">
            🏭 تولید مستقیم از کارگاه
        </span>

        <h1 class="text-3xl md:text-5xl font-black leading-tight mb-4 text-white">
            خرید عمده جوراب،<br>
            <span class="text-teal">بدون واسطه</span> و با قیمت تولیدکننده
        </h1>

        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-8">
            کاتالوگ کامل محصولات، قیمت ویژه اعضا، سفارش آنلاین و ارسال سریع —
            مخصوص فروشگاه‌داران و پخش‌کنندگان
        </p>

        <div class="flex flex-wrap gap-4">
            <a href="/products"
                class="bg-brand text-white font-bold px-8 py-3.5 rounded-xl
                    hover:bg-brand-strong hover:-translate-y-0.5 active:translate-y-0.5
                    shadow-[0_4px_0_#003d3d,0_8px_20px_rgba(0,101,107,.3)]
                    active:shadow-[0_1px_0_#003d3d] transition">
                مشاهده کاتالوگ
            </a>

            <a href="/register"
                class="border-2 border-line text-white font-bold px-8 py-3.5 rounded-xl
                    hover:border-teal hover:text-teal hover:-translate-y-0.5 transition">
                ثبت‌نام عمده‌فروشی
            </a>
        </div>
    </div>
</section>

<!-- ▂▂▂ مزیت‌ها ▂▂▂ -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
    <?php
    $features = [
        ['🏭', 'قیمت تولیدکننده', 'بدون واسطه، مستقیم از کارگاه'],
        ['📦', 'حداقل سفارش کم', 'شروع از جعبه‌های کوچک'],
        ['🚚', 'ارسال سریع', 'به سراسر کشور'],
        ['🏷️', 'برند اختصاصی', 'تولید با لیبل و بسته‌بندی شما'],
    ];
    foreach ($features as [$icon, $title, $desc]):
    ?>
        <div class="bg-card rounded-xl p-5 border border-line text-center card-3d">
            <div class="text-3xl mb-2"><?= $icon ?></div>
            <div class="font-bold mb-1 text-white"><?= $title ?></div>
            <div class="text-xs text-gray-400 leading-5"><?= $desc ?></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ دسته‌بندی‌ها ▂▂▂ -->
<section class="mb-12">
    <h2 class="text-2xl font-extrabold mb-6 text-white">دسته‌بندی محصولات</h2>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        $categories = [
            ['🧦', 'جوراب مردانه'],
            ['🧦', 'جوراب زنانه'],
            ['🧦', 'جوراب بچگانه'],
            ['⚽', 'جوراب ورزشی'],
        ];
        foreach ($categories as [$icon, $name]):
        ?>
            <a href="/products"
                class="bg-card rounded-xl p-6 border border-line text-center
                    hover:border-teal card-3d transition">
                <div class="text-4xl mb-3"><?= $icon ?></div>
                <div class="font-bold text-white"><?= $name ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ نوار CTA سفارش اختصاصی ▂▂▂ -->
<section class="relative overflow-hidden rounded-2xl border border-line bg-card p-8 md:p-12">
    <div class="absolute -top-20 -left-20 w-64 h-64 bg-brand opacity-20 rounded-full blur-[80px] pointer-events-none"></div>

    <div class="relative text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-ink/60 px-4 py-1.5 text-xs font-bold text-teal mb-4">
            🧵 مخصوص کسب‌وکارها
        </span>

        <h2 class="text-2xl md:text-3xl font-extrabold mb-3 text-white">
            برند خودتان را بسازید
        </h2>

        <p class="text-gray-400 mb-6 max-w-md mx-auto leading-7">
            تولید جوراب با لوگو، لیبل و بسته‌بندی اختصاصی شما —
            نمونه‌سازی، تأیید مشتری و سپس تولید انبوه
        </p>

        <a href="/custom-order"
            class="inline-block bg-teal text-black hover:bg-teal/90 font-bold px-8 py-3.5 rounded-xl
                hover:-translate-y-0.5 active:translate-y-0.5
                shadow-[0_4px_0_rgba(45,212,191,.3),0_8px_20px_rgba(45,212,191,.15)] transition">
            درخواست سفارش اختصاصی
        </a>
    </div>
</section>

<?php
 $content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';