<?php
// ============================================================
// Layout — تولیدی جوراب رابطی
//   روشن + فیروزه‌ای برند + Premium مینیمال
//   متغیرها: $pageTitle, $content | اختیاری: $extraHead, $extraScripts
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

    <!-- فونت وزیرمتن -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" as="style"
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css"
        rel="stylesheet">

    <!-- Tailwind — production: build محلی -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#00656B',
                        'brand-strong': '#004D4D',
                        'brand-deep': '#063D3D',
                        'brand-tint': '#F2F8F8',
                        surface: '#FFFFFF',
                        'surface-alt': '#F5F8F8',
                        ink: '#143636',
                        body: '#243737',
                        muted: '#6D7C7C',
                        faint: '#9AA8A8',
                        line: '#E2ECEC',
                        'line-strong': '#D3E0E0',
                        accent: '#F59E3F',
                    },
                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    },
                    boxShadow: {
                        'card': '0 2px 12px rgba(6, 61, 61, 0.06)',
                        'card-hover': '0 12px 28px rgba(6, 61, 61, 0.12)',
                        'btn': '0 3px 0 #004D4D, 0 6px 16px rgba(6, 61, 61, 0.18)',
                        'btn-sm': '0 2px 0 #004D4D, 0 3px 8px rgba(6, 61, 61, 0.1)',
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

        /* 🖨 چاپ */
        @media print {
            header, footer, .no-print {
                display: none !important;
            }
        }

        /* ⚡ کاهش حرکت */
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>

    <?= $extraHead ?? '' ?>
</head>

<body class="bg-surface-alt text-body min-h-screen font-vazir flex flex-col">

    <!-- ▂▂▂ Header ▂▂▂ -->
    <header class="bg-surface border-b border-line sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-3">

            <!-- لوگو -->
            <a href="/" class="font-black text-xl shrink-0 hover:opacity-80 transition text-ink">
                تولیدی <span class="text-brand">جوراب رابطی</span>
            </a>

            <!-- منو (دسکتاپ) -->
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-muted" aria-label="منوی اصلی">
                <a href="/" class="hover:text-brand transition">خانه</a>
                <a href="/products" class="hover:text-brand transition">محصولات</a>
                <a href="/custom-order" class="hover:text-brand transition">سفارش اختصاصی</a>
                <a href="/about" class="hover:text-brand transition">درباره ما</a>
            </nav>

            <!-- سمت چپ -->
            <div class="flex items-center gap-2">
                <a href="/cart"
                    class="relative p-2.5 hover:bg-brand-tint rounded-xl transition"
                    title="سبد خرید"
                    aria-label="سبد خرید">
                    <svg class="w-6 h-6 text-ink" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>

                    <span id="cart-badge"
                        class="hidden absolute top-0 left-0 bg-brand text-white text-[10px] font-bold w-5 h-5 rounded-full items-center justify-center">
                        ۰
                    </span>
                </a>

                <a href="/login"
                    class="hidden md:inline-flex text-sm font-bold text-brand border-[1.5px] border-brand
                        px-5 py-2 rounded-xl hover:bg-brand-tint
                        shadow-btn-sm transition items-center">
                    ورود
                </a>

                <a href="/register"
                    class="hidden md:inline-flex text-sm font-bold bg-brand text-white
                        px-5 py-2 rounded-xl hover:bg-brand-strong
                        shadow-btn transition items-center">
                    ثبت‌نام
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="flex-1">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer ▂▂▂ -->
    <footer class="bg-brand-deep text-gray-300 mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-10 text-sm">
            <div>
                <div class="font-black text-white text-lg mb-3">
                    تولیدی <span class="text-teal-300">جوراب رابطی</span>
                </div>

                <p class="leading-7 text-gray-400">
                    کارگاه تولیدی جوراب — سفارش مستقیم از تولیدکننده، بدون واسطه.
                    خرید عمده با قیمت پلکانی و تولید اختصاصی با برند شما.
                </p>
            </div>

            <div>
                <div class="font-bold text-white mb-3">دسترسی سریع</div>

                <ul class="space-y-2">
                    <li><a href="/products" class="hover:text-white transition">کاتالوگ محصولات</a></li>
                    <li><a href="/register" class="hover:text-white transition">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order" class="hover:text-white transition">سفارش تولید اختصاصی</a></li>
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

        <div class="border-t border-white/10 py-4 text-center text-xs text-gray-500">
            © <?= date('Y') ?> تمامی حقوق برای تولیدی جوراب رابطی محفوظ است
        </div>
    </footer>

    <?= $extraScripts ?? '' ?>
</body>

</html>