<?php
// ============================================================
// Layout — تولیدی جوراب رابطی (روشن + عناصر فیروزه‌ای)
// ============================================================

$pageTitle = $pageTitle ?? 'تولیدی جوراب رابطی — تولید و فروش عمده جوراب';
$pageDescription = $pageDescription ?? 'کارگاه تولیدی جوراب رابطی: تولید و فروش عمده جوراب مردانه، زنانه، بچگانه و ورزشی با قیمت پلکانی. سفارش تولید اختصاصی با برند شما.';
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

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" as="style"
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css"
        rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#00656B',
                        'brand-strong': '#004D4D',
                        'brand-tint': '#E6F2F2',
                        teal: '#0D9494',
                        ink: '#143636',
                    },
                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    },
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* 🧊 افکت ۳بعدی کارت‌ها */
        .card-3d {
            transition:
                transform .35s cubic-bezier(.2, .8, .3, 1),
                box-shadow .35s;
        }

        .card-3d:hover {
            transform: translateY(-8px) rotateX(4deg);
            box-shadow:
                0 24px 48px -12px rgba(0, 101, 107, .22),
                0 8px 16px -8px rgba(0, 0, 0, .1);
        }

        /* 🖨 چاپ */
        @media print {

            header,
            footer,
            nav {
                display: none !important;
            }

            body {
                background: #fff !important;
            }
        }

        /* ⚡ کاهش انیمیشن */
        @media (prefers-reduced-motion: reduce) {

            .card-3d,
            .card-3d:hover {
                transition: none;
                transform: none !important;
            }
        }
    </style>

    <?= $extraHead ?? '' ?>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen font-vazir">

    <!-- ▂▂▂ Header ▂▂▂ -->
    <header class="sticky top-0 z-50 bg-brand text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-3">

            <!-- لوگو -->
            <a href="/" class="font-black text-xl shrink-0 hover:opacity-90 transition">
                تولیدی <span class="text-teal-300">جوراب رابطی</span>
            </a>

            <!-- منو (دسکتاپ) -->
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-white/80" aria-label="منوی اصلی">
                <a href="/" class="hover:text-white transition">خانه</a>
                <a href="/products" class="hover:text-white transition">محصولات</a>
                <a href="/custom-order" class="hover:text-white transition">سفارش تولید اختصاصی</a>
                <a href="/about" class="hover:text-white transition">درباره ما</a>
            </nav>

            <!-- سمت چپ -->
            <div class="flex items-center gap-2">
                <a href="/cart"
                    class="relative p-2.5 hover:bg-white/10 rounded-xl transition"
                    title="سبد خرید"
                    aria-label="سبد خرید">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>

                    <span id="cart-badge"
                        class="hidden absolute top-0 left-0 bg-teal-300 text-brand text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
                        ۰
                    </span>
                </a>

                <a href="/login"
                    class="text-sm font-bold bg-white text-brand px-4 md:px-5 py-2.5 rounded-xl
                        hover:bg-brand-tint active:scale-95 transition shadow-sm">
                    ورود
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer (دسکتاپ) — فیروزه‌ای برند ▂▂▂ -->
    <footer class="bg-brand text-white mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-10 text-sm text-white/70">
            <div>
                <div class="font-black text-white text-lg mb-3">
                    تولیدی <span class="text-teal-300">جوراب رابطی</span>
                </div>

                <p class="leading-7">
                    کارگاه تولیدی جوراب — سفارش مستقیم از تولیدکننده، بدون واسطه.
                    خرید عمده با قیمت پلکانی و تولید اختصاصی با برند شما.
                </p>
            </div>

            <div>
                <div class="font-bold text-white mb-3">دسترسی سریع</div>

                <ul class="space-y-2">
                    <li><a href="/products" class="hover:text-teal-300 transition">کاتالوگ محصولات</a></li>
                    <li><a href="/register" class="hover:text-teal-300 transition">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order" class="hover:text-teal-300 transition">سفارش تولید اختصاصی</a></li>
                </ul>
            </div>

            <div>
                <div class="font-bold text-white mb-3">تماس با ما</div>

                <ul class="space-y-2">
                    <li dir="ltr" class="text-right">۰۲۱ - ۱۲۳۴۵۶۷۸</li>
                    <li dir="ltr" class="text-right">۰۹۱۲ - ۷۲۶ ۴۵۶۰</li>
                    <li>شنبه تا پنجشنبه — ۹ تا ۱۷</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/15 py-4 text-center text-xs text-white/50">
            © <?= date('Y') ?> تمامی حقوق برای تولیدی جوراب رابطی محفوظ است
        </div>
    </footer>

    <!-- ▂▂▂ نوار پایین موبایل — فیروزه‌ای برند ▂▂▂ -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 h-[72px] bg-brand text-white border-t border-white/10"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="ناوبری موبایل">
        <div class="grid grid-cols-4 h-full text-center">

            <a href="/"
                class="flex flex-col items-center justify-center gap-1 text-teal-300 active:scale-95 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
                </svg>
                <span class="text-[10px] font-bold">خانه</span>
            </a>

            <a href="/products"
                class="flex flex-col items-center justify-center gap-1 text-white/70 active:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span class="text-[10px] font-bold">محصولات</span>
            </a>

            <a href="/cart"
                class="flex flex-col items-center justify-center gap-1 text-white/70 active:text-white transition relative">
                <span class="relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>

                    <span id="cart-badge-mobile"
                        class="hidden absolute -top-1.5 -left-2 bg-teal-300 text-brand text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center">
                        ۰
                    </span>
                </span>

                <span class="text-[10px] font-bold">سبد</span>
            </a>

            <a href="/login"
                class="flex flex-col items-center justify-center gap-1 text-white/70 active:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.