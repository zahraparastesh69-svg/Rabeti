<?php
// ============================================================
// main.php — قالب اصلی (طراحی دقیق بر اساس پروتوتایپ رابطی)
// ============================================================

$pageTitle = $pageTitle ?? 'تولیدی جوراب رابطی — تولید و فروش عمده جوراب';
$pageDescription = $pageDescription ?? 'کارگاه تولیدی جوراب رابطی: تولید و فروش عمده جوراب مردانه، زنانه، بچگانه و ورزشی با قیمت پلکانی.';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#00656B">

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- فونت وزیر متن -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#00656B', // رنگ فیروزه‌ای تیره دقیق هدر و دکمه‌ها
                            hover: '#004f54',
                            light: '#E6F2F2',
                            soft: '#F4F9F9'
                        },
                        accent: {
                            orange: '#F59E0B',
                            teal: '#0D9494'
                        },
                        ink: {
                            900: '#1E293B',
                            700: '#334155',
                            500: '#64748B',
                            400: '#94A3B8',
                            200: '#E2E8F0',
                            100: '#F1F5F9'
                        }
                    },
                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background-color: #FFFFFF;
            color: #1E293B;
            -webkit-font-smoothing: antialiased;
        }

        /* اسکرول‌بار مخفی برای استوری‌ها در موبایل */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <?= $extraHead ?? '' ?>
</head>

<body class="min-h-screen flex flex-col bg-white">

    <!-- ▂▂▂ Header دقیق بر اساس عکس ۱ ▂▂▂ -->
    <header class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-[1400px] mx-auto px-4 lg:px-8 h-20 flex items-center justify-between gap-4">

            <!-- سمت راست: لوگو و منو -->
            <div class="flex items-center gap-8">
                <!-- لوگو -->
                <a href="/" class="flex items-center gap-2.5 shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                        <!-- آیکون کارگاه/ساختمان لوگو -->
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 3L2 9v12h20V9L12 3zm0 2.2L19 9.3v9.7H5V9.3L12 5.2zM7 11h2v6H7v-6zm4 0h2v6h-2v-6zm4 0h2v6h-2v-6z"/>
                        </svg>
                    </div>
                    <span class="font-black text-xl text-ink-900 tracking-tight">
                        تولیدی جوراب <span class="text-brand">رابطی</span>
                    </span>
                </a>

                <!-- منو دسکتاپ -->
                <nav class="hidden xl:flex items-center gap-6 text-[13.5px] font-bold text-ink-700">
                    <a href="/" class="text-brand font-black hover:text-brand transition">صفحه نخست</a>
                    <a href="/products" class="hover:text-brand transition">محصولات</a>
                    <a href="/gifts" class="hover:text-brand transition">کادویی</a>
                    <a href="/custom-order" class="hover:text-brand transition">سفارش تولید اختصاصی</a>
                    <a href="/tracking" class="hover:text-brand transition">پیگیری سفارش</a>
                    <a href="/contact" class="hover:text-brand transition">ارتباط با ما</a>
                </nav>
            </div>

            <!-- سمت چپ: سرچ‌بار، سبد خرید، ورود و ثبت‌نام -->
            <div class="flex items-center gap-3">
                <!-- سرچ‌بار کپسولی -->
                <div class="hidden md:flex items-center relative w-64 lg:w-72">
                    <input type="text"
                           placeholder="جستجوی محصولات..."
                           class="w-full bg-ink-100/70 text-xs text-ink-900 placeholder:text-ink-400 rounded-full py-2.5 pr-4 pl-9 border border-transparent focus:border-brand/30 focus:bg-white focus:outline-none transition">
                    <svg class="w-4 h-4 text-ink-400 absolute left-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>

                <!-- سبد خرید -->
                <a href="/cart" class="w-10 h-10 rounded-xl border border-ink-200 flex items-center justify-center text-ink-700 hover:text-brand hover:border-brand transition shrink-0" title="سبد خرید">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>
                </a>

                <!-- ورود (Outline) -->
                <a href="/login" class="text-xs font-bold text-brand border border-brand/80 px-4 py-2.5 rounded-xl hover:bg-brand-light transition shrink-0">
                    ورود
                </a>

                <!-- ثبت‌نام (Solid Teal) -->
                <a href="/register" class="text-xs font-bold text-white bg-brand hover:bg-brand-hover px-4 py-2.5 rounded-xl transition shadow-sm shrink-0">
                    ثبت‌نام
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ بدنه اصلی ▂▂▂ -->
    <main class="flex-1 max-w-[1400px] w-full mx-auto px-4 lg:px-8 py-6">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer مینیمال و استاندارد ▂▂▂ -->
    <footer class="bg-ink-100/60 border-t border-ink-200 mt-20">
        <div class="max-w-[1400px] mx-auto px-4 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-8 text-sm">
            <div class="space-y-3 md:col-span-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white font-bold text-sm">ر</div>
                    <span class="font-extrabold text-base text-ink-900">تولیدی جوراب رابطی</span>
                </div>
                <p class="text-ink-500 text-xs leading-6 max-w-md">
                    تولید و پخش مستقیم انواع جوراب پنبه‌ای، ورزشی و فانتزی با تخفیف‌های پلکانی برای فروشگاه‌ها، بنکداران و سازمان‌ها در سراسر ایران.
                </p>
            </div>

            <div>
                <h4 class="font-bold text-ink-900 mb-3 text-xs">بخش‌های سایت</h4>
                <ul class="space-y-2 text-xs text-ink-500">
                    <li><a href="/products" class="hover:text-brand">فروشگاه و محصولات</a></li>
                    <li><a href="/custom-order" class="hover:text-brand">سفارش با برند اختصاصی</a></li>
                    <li><a href="/tracking" class="hover:text-brand">سامانه رهگیری سفارش</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-ink-900 mb-3 text-xs">ارتباط با کارگاه</h4>
                <ul class="space-y-2 text-xs text-ink-500">
                    <li>تلفن: ۰۲۱-۱۲۳۴۵۶۷۸</li>
                    <li>همراه فروش: ۰۹۱۲۷۲۶۴۵۶۰</li>
                    <li>تهران، بازار بزرگ، بازارچه تولیدکنندگان</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-ink-200 py-4 text-center text-[11px] text-ink-400">
            © <?= date('Y') ?> کلیه حقوق برای کارگاه تولیدی جوراب رابطی محفوظ است.
        </div>
    </footer>

</body>
</html>
