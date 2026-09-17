<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'جوراب‌لند — بازار عمده جوراب' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

    <script>
        // 🎨 Design Tokens — نسخه ۲ (قفل‌شده)
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#00656B', // فیروزه‌ای اصلی
                            hover: '#004F54', // هاور دکمه‌ها
                            light: '#dcdcf5', // بج و زمینه ملایم
                        },
                        accent: {
                            orange: '#F59E0B', // تاکید نارنجی
                        },
                        ink: {
                            900: '#1E293B', // تیترها
                            700: '#334155', // متن پررنگ
                            500: '#64748B', // توضیحات
                            400: '#94A3B8', // غیرفعال
                            200: '#E2E8F0', // بردرها
                            100: '#F1F5F9', // زمینه خنثی
                        },
                    },
                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    },
                    boxShadow: {
                        card: '0 1px 3px rgba(30,41,59,.06), 0 8px 24px -8px rgba(0,101,107,.12)',
                        'card-hover': '0 4px 8px rgba(30,41,59,.08), 0 16px 40px -8px rgba(0,101,107,.25)',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
        }

        /* 🧊 کارت با بلندشدن نرم */
        .card-lift {
            transition: transform .3s cubic-bezier(.2, .8, .3, 1), box-shadow .3s;
        }

        .card-lift:hover {
            transform: translateY(-6px);
            box-shadow: 0 4px 8px rgba(30, 41, 59, .08),
                0 16px 40px -8px rgba(0, 101, 107, .25);
        }

        /* 📱 فاصله پایین برای نوار موبایل */
        @media (max-width: 767px) {
            body {
                padding-bottom: 72px;
            }
        }
    </style>
</head>

<body class="bg-ink-100 text-ink-900 min-h-screen">

    <!-- ▂▂▂ Header ▂▂▂ -->
    <header class="bg-white/90 backdrop-blur border-b border-ink-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-3">

            <!-- لوگو -->
            <a href="/" class="font-black text-xl shrink-0 text-brand">
                جوراب‌<span class="text-ink-900">لند</span>
            </a>

            <!-- منو (دسکتاپ) -->
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-ink-700">
                <a href="/" class="hover:text-brand transition">خانه</a>
                <a href="/products" class="hover:text-brand transition">محصولات</a>
                <a href="/custom-order" class="hover:text-brand transition">سفارش اختصاصی</a>
                <a href="/about" class="hover:text-brand transition">درباره ما</a>
            </nav>

            <!-- سمت چپ -->
            <div class="flex items-center gap-2">
                <a href="/cart" class="relative p-2.5 hover:bg-ink-100 rounded-xl transition" title="سبد خرید">
                    <svg class="w-6 h-6 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>
                    <span id="cart-badge" class="hidden absolute top-0 left-0 bg-accent-orange text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center">۰</span>
                </a>
                <a href="/login" class="text-sm font-bold bg-brand hover:bg-brand-hover text-white px-4 md:px-5 py-2.5 rounded-xl transition active:scale-95">
                    ورود
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer (دسکتاپ) ▂▂▂ -->
    <footer class="hidden md:block bg-ink-900 text-gray-300 mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-10 text-sm">
            <div>
                <div class="font-black text-white text-lg mb-3">جوراب‌<span class="text-brand-light">لند</span></div>
                <p class="leading-7 text-gray-400">بازار آنلاین عمده جوراب — سفارش مستقیم از تولیدکننده، بدون واسطه</p>
            </div>
            <div>
                <div class="font-bold text-white mb-3">دسترسی سریع</div>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="/products" class="hover:text-white transition">کاتالوگ محصولات</a></li>
                    <li><a href="/register" class="hover:text-white transition">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order" class="hover:text-white transition">سفارش تولید اختصاصی</a></li>
                </ul>
            </div>
            <div>
                <div class="font-bold text-white mb-3">تماس با ما</div>
                <ul class="space-y-2 text-gray-400">
                    <li dir="ltr" class="text-right">۰۲۱ - ۱۲۳۴۵۶۷۸</li>
                    <li dir="ltr" class="text-right">۰۹۱۲ - ۱۲۳۴۵۶۷</li>
                    <li>شنبه تا پنجشنبه — ۹ تا ۱۷</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs text-gray-500">
            © تمامی حقوق برای جوراب‌لند محفوظ است
        </div>
    </footer>

    <!-- ▂▂▂ نوار پایین موبایل ▂▂▂ -->
    <nav class="md:hidden bg-white/95 backdrop-blur border-t border-ink-200 fixed bottom-0 inset-x-0 z-50 h-[72px]"
        style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="grid grid-cols-4 h-full text-center">

            <a href="/" class="flex flex-col items-center justify-center gap-1 text-brand">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
                </svg>
                <span class="text-[10px] font-bold">خانه</span>
            </a>

            <a href="/products" class="flex flex-col items-center justify-center gap-1 text-ink-500 active:text-brand">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span class="text-[10px] font-bold">محصولات</span>
            </a>

            <a href="/cart" class="flex flex-col items-center justify-center gap-1 text-ink-500 active:text-brand">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                </svg>
                <span class="text-[10px] font-bold">سبد</span>
            </a>

            <a href="/login" class="flex flex-col items-center justify-center gap-1 text-ink-500 active:text-brand">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0" />
                </svg>
                <span class="text-[10px] font-bold">حساب من</span>
            </a>
        </div>
    </nav>

</body>

</html>