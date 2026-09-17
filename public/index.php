<?php
$pageTitle = 'بازار عمده جوراب | جوراب‌لند B2B';
ob_start();
?>

<!-- ▂▂▂ بنر اصلی ▂▂▂ -->
<section class="bg-gradient-to-l from-blue-600 to-blue-800 rounded-2xl text-white p-10 md:p-16 mb-12">
    <h1 class="text-3xl md:text-5xl font-extrabold leading-tight mb-4">
        خرید عمده جوراب،<br>بدون واسطه و با قیمت تولیدکننده
    </h1>
    <p class="text-blue-100 text-lg mb-8 max-w-xl leading-8">
        کاتالوگ کامل محصولات، قیمت ویژه اعضا، سفارش آنلاین و ارسال سریع —
        مخصوص فروشگاه‌داران و پخش‌کنندگان
    </p>
    <div class="flex flex-wrap gap-4">
        <a href="/products" class="bg-white text-blue-700 font-bold px-8 py-3 rounded-xl hover:bg-blue-50">
            مشاهده کاتالوگ
        </a>
        <a href="/register" class="border-2 border-white/50 text-white font-bold px-8 py-3 rounded-xl hover:bg-white/10">
            ثبت‌نام عمده‌فروشی
        </a>
    </div>
</section>

<!-- ▂▂▂ مزیت‌ها ▂▂▂ -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
    <?php
    $features = [
        ['🏭', 'قیمت تولیدکننده', 'بدون واسطه، مستقیم از کارخانه'],
        ['📦', 'حداقل سفارش کم', 'شروع از جعبه‌های کوچک'],
        ['🚚', 'ارسال سریع', 'به سراسر کشور'],
        ['🏷️', 'برند اختصاصی', 'تولید با لیبل و بسته‌بندی شما'],
    ];
    foreach ($features as [$icon, $title, $desc]):
    ?>
        <div class="bg-white rounded-xl p-5 border border-gray-100 text-center">
            <div class="text-3xl mb-2"><?= $icon ?></div>
            <div class="font-bold mb-1"><?= $title ?></div>
            <div class="text-xs text-gray-500 leading-5"><?= $desc ?></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ دسته‌بندی‌ها ▂▂▂ -->
<section class="mb-12">
    <h2 class="text-2xl font-extrabold mb-6">دسته‌بندی محصولات</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        // 🔧 فعلاً دستی — از هفته بعد از داده واقعی خوانده می‌شود
        $categories = [
            ['🧦', 'جوراب مردانه'],
            ['🧦', 'جوراب زنانه'],
            ['🧦', 'جوراب بچگانه'],
            ['⚽', 'جوراب ورزشی'],
        ];
        foreach ($categories as [$icon, $name]):
        ?>
            <a href="/products" class="bg-white rounded-xl p-6 border border-gray-100 text-center hover:border-blue-400 hover:shadow-md transition">
                <div class="text-4xl mb-3"><?= $icon ?></div>
                <div class="font-bold"><?= $name ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ نوار CTA سفارش اختصاصی ▂▂▂ -->
<section class="bg-gray-900 text-white rounded-2xl p-8 md:p-12 text-center">
    <h2 class="text-2xl font-extrabold mb-3">برند خودتان را بسازید</h2>
    <p class="text-gray-400 mb-6">تولید جوراب با لوگو، لیبل و بسته‌بندی اختصاصی شما</p>
    <a href="/custom-order" class="inline-block bg-blue-600 hover:bg-blue-500 font-bold px-8 py-3 rounded-xl">
        درخواست سفارش اختصاصی
    </a>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
