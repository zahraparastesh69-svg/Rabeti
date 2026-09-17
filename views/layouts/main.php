<?php
// ============================================================
// main.php — تولیدی جوراب رابطی (طراحی فوق‌مدرن، سه‌بعدی و مینیمال)
// ============================================================

$pageTitle = $pageTitle ?? 'تولیدی جوراب رابطی — تولید و فروش عمده جوراب';
$pageDescription = $pageDescription ?? 'کارگاه تولیدی جوراب رابطی: تولید و فروش عمده انواع جوراب با کیفیت صادراتی و قیمت رقابتی.';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#0F766E">

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
                            50: '#F0FDFA',
                            100: '#CCFBF1',
                            200: '#99F6E4',
                            400: '#2DD4BF',
                            DEFAULT: '#0F766E', // فیروزه‌ای عمیق لوکس
                            600: '#0D9488',
                            dark: '#042F2E',
                            accent: '#14B8A6' // فیروزه‌ای نئونی درخشان
                        },
                        surface: '#F8FAFC'
                    },
                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    },
                    boxShadow: {
                        '3d-subtle': '0 10px 30px -10px rgba(15, 118, 110, 0.12), 0 4px 6px -2px rgba(0, 0, 0, 0.04)',
                        '3d-card': '0 20px 40px -15px rgba(15, 118, 110, 0.18), 0 0 0 1px rgba(255, 255, 255, 0.8) inset',
                        '3d-hover': '0 30px 60px -12px rgba(15, 118, 110, 0.28), 0 12px 24px -8px rgba(0, 0, 0, 0.08)',
                        'inner-light': 'inset 0 1px 1px 0 rgba(255, 255, 255, 0.6)'
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background-color: #FAFCFC;
            -webkit-font-smoothing: antialiased;
        }

        /* 🧊 افکت گلس و کارت سه‌بعدی */
        .glass-panel {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.7);
        }

        .card-3d {
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            transform: perspective(1000px) rotateX(0deg) rotateY(0deg);
        }

        .card-3d:hover {
            transform: perspective(1000px) translateY(-8px) scale(1.015);
        }

        /* حباب‌های نور پس‌زمینه برای عمق سه‌بعدی */
        .ambient-glow {
            position: fixed;
            width: 550px;
            height: 550px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.09) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        @media (prefers-reduced-motion: reduce) {
            .card-3d {
                transform: none !important;
                transition: none;
            }
        }
    </style>

    <?= $extraHead ?? '' ?>
</head>

<body class="text-slate-800 min-h-screen relative selection:bg-brand-200 selection:text-brand-dark pb-20 md:pb-0">

    <!-- لایه‌های نوری پس‌زمینه (ایجاد عمق سه‌بعدی و مدرن) -->
    <div class="ambient-glow -top-32 -right-20"></div>
    <div class="ambient-glow top-1/2 -left-40"></div>

    <!-- ▂▂▂ هدر مینیمال شیشه‌ای و شناور ▂▂▂ -->
    <header class="sticky top-4 z-50 max-w-7xl mx-auto px-4 mb-6">
        <div class="glass-panel shadow-3d-subtle rounded-2xl px-6 h-18 flex items-center justify-between transition-all duration-300">

            <!-- لوگو با تایپوگرافی سه‌بعدی -->
            <a href="/" class="flex items-center gap-2 group">
                <span class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand to-brand-accent text-white flex items-center justify-center font-black shadow-md shadow-brand/20 group-hover:scale-105 transition">
                    ر
                </span>
                <div class="font-extrabold text-xl text-slate-800 tracking-tight">
                    تولیدی جوراب <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand to-brand-accent">رابطی</span>
                </div>
            </a>

            <!-- منو ناوبری -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                <a href="/" class="text-brand transition">خانه</a>
                <a href="/products" class="hover:text-brand transition">محصولات</a>
                <a href="/custom-order" class="hover:text-brand transition">تولید سفارشی</a>
                <a href="/about" class="hover:text-brand transition">درباره ما</a>
            </nav>

            <!-- ابزارک‌ها (سبد خرید و ورود) -->
            <div class="flex items-center gap-3">
                <a href="/cart" class="relative p-2.5 rounded-xl text-slate-700 hover:bg-brand-50 hover:text-brand transition shadow-sm border border-slate-100 bg-white" title="سبد خرید">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25c-.67 0-1.19-.578-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    <span id="cart-badge" class="hidden absolute -top-1 -right-1 bg-brand-accent text-white text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center ring-2 ring-white">
                        ۰
                    </span>
                </a>

                <a href="/login" class="text-sm font-bold text-white bg-gradient-to-r from-brand to-brand-600 hover:from-brand-600 hover:to-brand px-5 py-2.5 rounded-xl shadow-md shadow-brand/20 active:scale-95 transition-all">
                    ورود / ثبت‌نام
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="relative z-10 max-w-7xl mx-auto px-4 py-6">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ فوتر مینیمال با رنگ‌بندی تمیز ▂▂▂ -->
    <footer class="relative z-10 mt-28 bg-white border-t border-slate-100/80">
        <div class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 md:grid-cols-12 gap-10">

            <div class="md:col-span-5 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-brand text-white flex items-center justify-center font-black text-sm">ر</span>
                    <span class="font-black text-lg text-slate-900">جوراب رابطی</span>
                </div>
                <p class="text-slate-500 text-sm leading-7 max-w-sm">
                    تولید مستقیم و بی‌واسطه جوراب با مرغوب‌ترین نخ پنبه و کش انگلیسی. خرید عمده در تنوع بالا با بسته‌بندی ویژه فروشگاه‌ها.
                </p>
            </div>

            <div class="md:col-span-3 space-y-3 text-sm">
                <h4 class="font-bold text-slate-900 text-base">دسترسی سریع</h4>
                <ul class="space-y-2.5 text-slate-600">
                    <li><a href="/products" class="hover:text-brand transition">کاتالوگ محصولات جدید</a></li>
                    <li><a href="/custom-order" class="hover:text-brand transition">تولید طرح اختصاصی</a></li>
                    <li><a href="/about" class="hover:text-brand transition">درباره کارگاه</a></li>
                </ul>
            </div>

            <div class="md:col-span-4 space-y-3 text-sm">
                <h4 class="font-bold text-slate-900 text-base">تماس با بخش فروش</h4>
                <div class="space-y-2 text-slate-600">
                    <p class="flex items-center gap-2 font-mono">
                        <span class="text-slate-400">تلفن:</span> ۰۲۱-۱۲۳۴۵۶۷۸
                    </p>
                    <p class="flex items-center gap-2 font-mono">
                        <span class="text-slate-400">همراه:</span> ۰۹۱۲۷۲۶۴۵۶۰
                    </p>
                    <p class="text-xs text-slate-400 mt-2">پاسخگویی شنبه تا چهارشنبه ۹ الی ۱۸</p>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 py-6 text-center text-xs text-slate-400">
            © <?= date('Y') ?> کلیه حقوق مادی و معنوی متعلق به کارگاه جوراب رابطی است.
        </div>
    </footer>

    <!-- ▂▂▂ نوار پایین موبایل شیشه‌ای (شناور و مدرن) ▂▂▂ -->
    <nav class="md:hidden fixed bottom-3 inset-x-4 z-50 h-16 glass-panel rounded-2xl shadow-3d-hover border border-white/80"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="ناوبری موبایل">
        <div class="grid grid-cols-4 h-full items-center text-center">

            <a href="/" class="flex flex-col items-center gap-1 text-brand">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
                </svg>
                <span class="text-[10px] font-bold">خانه</span>
            </a>

            <a href="/products" class="flex flex-col items-center gap-1 text-slate-400 hover:text-brand">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span class="text-[10px] font-bold">محصولات</span>
            </a>

            <a href="/cart" class="flex flex-col items-center gap-1 text-slate-400 hover:text-brand relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25c-.67 0-1.19-.578-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
                <span class="text-[10px] font-bold">سبد</span>
            </a>

            <a href="/login" class="flex flex-col items-center gap-1 text-slate-400 hover:text-brand">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <span class="text-[10px] font-bold">پروفایل</span>
            </a>
        </div>
    </nav>

</body>

</html>