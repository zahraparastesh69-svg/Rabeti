<?php
$pageTitle = 'تولیدی جوراب رابطی — بازار عمده جوراب با قیمت کارخانه';
$pageDescription = 'خرید عمده انواع جوراب مردانه، زنانه و بچگانه با قیمت مستقیم تولیدکننده و تخفیف پلکانی. سفارش تولید با برند اختصاصی شما.';
ob_start();
?>

<!-- ▂▂▂ بنر هیرو (Hero Section) مدرن و سه‌بعدی ▂▂▂ -->
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-white via-white to-brand-50/40 border border-white p-8 md:p-16 mb-12 shadow-3d-card">
    <!-- افکت‌های نوری سه‌بعدی درون بنر -->
    <div class="absolute -top-24 -right-24 w-80 h-80 bg-brand-200/40 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-brand-400/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 max-w-2xl">
        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200/70 bg-brand-50/80 px-4 py-1.5 text-xs font-bold text-brand mb-6 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-brand-accent animate-pulse"></span>
            تولید مستقیم و بی‌واسطه از کارگاه
        </div>

        <h1 class="text-3xl md:text-5xl font-black leading-[1.25] mb-5 text-slate-900 tracking-tight">
            خرید عمده جوراب،<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand via-brand-600 to-brand-accent">
                با اصالت بافت و قیمت کارخانه
            </span>
        </h1>

        <p class="text-slate-600 text-base md:text-lg mb-8 leading-8 font-normal">
            تأمین پایدار پوشاک پا برای بنکداران، فروشگاه‌های زنجیره‌ای و آنلاین‌شاپ‌ها. قیمت شفاف پلکانی، تنوع بالای بافت و ارسال روزانه به سراسر کشور.
        </p>

        <div class="flex flex-wrap items-center gap-4">
            <a href="/products"
                class="group bg-gradient-to-r from-brand to-brand-600 text-white font-extrabold px-8 py-3.5 rounded-2xl
                    hover:shadow-lg hover:shadow-brand/25 active:scale-95 transition-all flex items-center gap-2">
                <span>مشاهده کاتالوگ و قیمت‌ها</span>
                <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>

            <a href="/custom-order"
                class="bg-white/80 hover:bg-white text-slate-700 font-bold px-7 py-3.5 rounded-2xl
                    border border-slate-200/80 hover:border-brand-200 hover:text-brand shadow-sm transition active:scale-95">
                سفارش تولید با برند شما
            </a>
        </div>
    </div>
</section>

<!-- ▂▂▂ ارزش‌ها و مزایا (کارت‌های سه‌بعدی مینیمال) ▂▂▂ -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-14">
    <?php
    $features = [
        [
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v.375c0 .621.504 1.125 1.125 1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />',
            'title' => 'قیمت دست‌اول تولیدی',
            'desc' => 'حذف تمام دلال‌ها با سود تضمینی فروشگاه شما'
        ],
        [
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />',
            'title' => 'حداقل جین خرید منعطف',
            'desc' => 'امکان سفارش تستی از یک کارتن تا سفارش کانتینری'
        ],
        [
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25h2.25m-2.25 0v11.25m0-11.25l-3.25-3.25m0 0l-3.25 3.25M11 4.25v14.5" />',
            'title' => 'تحویل سریع باربری',
            'desc' => 'بسته‌بندی پلمپ و ارسال ۴۸ ساعته سراسری'
        ],
        [
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />',
            'title' => 'تولید با مارک اختصاصی',
            'desc' => 'طراحی مارک، بندانگشتی و جعبه با لوگوی شما'
        ]
    ];

    foreach ($features as $f):
    ?>
        <div class="card-3d bg-white/90 border border-slate-100 p-6 rounded-2xl shadow-3d-subtle hover:shadow-3d-hover hover:border-brand-200 transition-all flex flex-col items-center text-center group">
            <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-brand group-hover:text-white transition-all shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <?= $f['icon'] ?>
                </svg>
            </div>
            <h3 class="font-bold text-slate-800 mb-1.5 text-sm md:text-base"><?= $f['title'] ?></h3>
            <p class="text-xs text-slate-500 leading-5"><?= $f['desc'] ?></p>
        </div>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ دسته‌بندی محصولات (استایل ویترین مینیمال و سه‌بعدی) ▂▂▂ -->
<section class="mb-14">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl md:text-2xl font-black text-slate-900">دسته‌بندی‌های اصلی</h2>
            <p class="text-xs md:text-sm text-slate-500 mt-1">جوراب‌های بافته شده با سیلندر بالا و نخ پنبه سوپر</p>
        </div>
        <a href="/products" class="text-xs md:text-sm font-bold text-brand hover:text-brand-accent transition flex items-center gap-1">
            مشاهده همه
            <span>←</span>
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        <?php
        $categories = [
            ['title' => 'جوراب مردانه', 'tag' => 'ساق‌دار، کالج، نیم‌ساق', 'bg' => 'from-slate-50 to-teal-50/50'],
            ['title' => 'جوراب زنانه', 'tag' => 'فانتزی، شیشه‌ای، مچی', 'bg' => 'from-slate-50 to-emerald-50/50'],
            ['title' => 'جوراب بچگانه', 'tag' => 'ضدحساسیت، عروسکی', 'bg' => 'from-slate-50 to-cyan-50/50'],
            ['title' => 'جوراب ورزشی', 'tag' => 'حوله ای، تنفسی، کفه ضخیم', 'bg' => 'from-slate-50 to-teal-50/50'],
        ];
        foreach ($categories as $cat):
        ?>
            <a href="/products"
                class="card-3d group relative overflow-hidden bg-gradient-to-b <?= $cat['bg'] ?> p-6 rounded-2xl border border-slate-200/70 hover:border-brand-400 shadow-3d-subtle hover:shadow-3d-hover transition-all">
                <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-brand-200/30 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>

                <div class="relative z-10">
                    <span class="text-xs font-semibold text-brand-600 bg-brand-50 border border-brand-100 px-2.5 py-1 rounded-lg inline-block mb-4">
                        عمده و کارتنی
                    </span>
                    <h3 class="font-black text-base md:text-lg text-slate-800 mb-1 group-hover:text-brand transition-colors">
                        <?= $cat['title'] ?>
                    </h3>
                    <p class="text-xs text-slate-500">
                        <?= $cat['tag'] ?>
                    </p>
                </div>

                <div class="mt-8 flex justify-end">
                    <span class="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:bg-brand group-hover:text-white transition">
                        ←
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ بنر سفارشی‌سازی و B2B (سه‌بعدی با لایه‌های نوری) ▂▂▂ -->
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-dark via-brand to-brand-600 text-white p-8 md:p-14 shadow-2xl">
    <div class="absolute top-0 right-0 w-96 h-96 bg-brand-accent/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 max-w-xl mx-auto text-center">
        <span class="inline-block rounded-full bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1 text-xs font-bold text-teal-200 mb-4 shadow-inner">
            سفارشات سازمانی و برندیگ اختصاصی
        </span>

        <h2 class="text-2xl md:text-4xl font-black mb-4 leading-snug">
            برند اختصاصی خودتان را تولید کنید
        </h2>

        <p class="text-teal-50/80 text-sm md:text-base mb-8 leading-7 font-light">
            صفر تا صد تولید بافت جوراب، طراحی مارک مقوایی، درج لوگو روی کش جوراب و سلفون اختصاصی را به خط تولید ما بسپارید.
        </p>

        <a href="/custom-order"
            class="inline-block bg-white text-brand-dark font-extrabold px-8 py-3.5 rounded-2xl
                hover:bg-brand-50 hover:shadow-xl hover:shadow-black/10 active:scale-95 transition-all">
            استعلام هزینه تولید سفارشی
        </a>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
