<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'جوراب‌لند — بازار عمده جوراب' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

    <script>
        // 🎨 Design Tokens — قفل‌شده برای کل پروژه
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0B0B14', // پس‌زمینه اصلی
                        card: '#14141F', // سطح کارت‌ها
                        line: '#232333', // خطوط و بوردر
                        lime: '#CCFF00', // تاکید اصلی
                        grape: '#8B5CF6', // بنفش نئونی
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
        }

        /* ✨ افکت شیشه‌ای */
        .glass {
            background: rgba(20, 20, 31, .75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        /* 🧊 افکت ۳بعدی کارت‌ها */
        .card-3d {
            transition: transform .35s cubic-bezier(.2, .8, .3, 1), box-shadow .35s;
            transform-style: preserve-3d;
        }

        .card-3d:hover {
            transform: translateY(-8px) rotateX(4deg);
            box-shadow: 0 24px 48px -12px rgba(139, 92, 246, .35),
                0 8px 16px -8px rgba(0, 0, 0, .6);
        }

        /* 🌈 هاله‌های رنگی پس‌زمینه */
        .glow {
            position: absolute;
            border-radius: 9999px;
            filter: blur(90px);
            opacity: .35;
            pointer-events: none;
        }

        /* 📱 فاصله پایین برای نوار موبایل */
        @media (max-width: 767px) {
            body {
                padding-bottom: 72px;
            }
        }
    </style>
</head>

<body class="bg-ink text-gray-100 min-h-screen relative overflow-x-hidden">

    <!-- 🌈 هاله‌های پس‌زمینه -->
    <div class="glow w-96 h-96 bg-grape -top-32 -right-32"></div>
    <div class="glow w-80 h-80 bg-lime opacity-10 top-1/2 -left-40"></div>

    <!-- ▂▂▂ Header ▂▂▂ -->
    <header class="glass border-b border-line sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-3">

            <!-- لوگو -->
            <a href="/" class="font-black text-xl shrink-0">
                جوراب‌<span class="text-lime">لند</span>
            </a>

            <!-- منو (دسکتاپ) -->
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-gray-300">
                <a href="/" class="hover:text-lime transition">خانه</a>
                <a href="/products" class="hover:text-lime transition">محصولات</a>
                <a href="/custom-order" class="hover:text-lime transition">سفارش اختصاصی</a>
                <a href="/about" class="hover:text-lime transition">درباره ما</a>
            </nav>

            <!-- سمت چپ -->
            <div class="flex items-center gap-2">
                <a href="/cart" class="relative p-2.5 hover:bg-white/5 rounded-xl transition" title="سبد خرید">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>
                    <span id="cart-badge" class="hidden absolute top-0 left-0 bg-lime text-black text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center">۰</span>
                </a>
                <a href="/login" class="text-sm font-bold bg-lime text-black px-4 md:px-5 py-2.5 rounded-xl hover:brightness-110 active:scale-95 transition">
                    ورود
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="max-w-7xl mx-auto px-4 py-8 relative">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer (دسکتاپ) ▂▂▂ -->
    <footer class="hidden md:block border-t border-line mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-10 text-sm text-gray-400">
            <div>
                <div class="font-black text-white text-lg mb-3">جوراب‌<span class="text-lime">لند</span></div>
                <p class="leading-7">بازار آنلاین عمده جوراب — سفارش مستقیم از تولیدکننده، بدون واسطه</p>
            </div>
            <div>
                <div class="font-bold text-white mb-3">دسترسی سریع</div>
                <ul class="space-y-2">
                    <li><a href="/products" class="hover:text-lime transition">کاتالوگ محصولات</a></li>
                    <li><a href="/register" class="hover:text-lime transition">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order" class="hover:text-lime transition">سفارش تولید اختصاصی</a></li>
                </ul>
            </div>
            <div>
                <div class="font-bold text-white mb-3">تماس با ما</div>
                <ul class="space-y-2">
                    <li dir="ltr" class="text-right">۰۲۱ - ۱۲۳۴۵۶۷۸</li>
                    <li dir="ltr" class="text-right">۰۹۱۲ - ۱۲۳۴۵۶۷</li>
                    <li>شنبه تا پنجشنبه — ۹ تا ۱۷</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-line py-4 text-center text-xs text-gray-600">
            © تمامی حقوق برای جوراب‌لند محفوظ است
        </div>
    </footer>

    <!-- ▂▂▂ نوار پایین موبایل (مثل اپلیکیشن) ▂▂▂ -->
    <nav class="md:hidden glass border-t border-line fixed bottom-0 inset-x-0 z-50 h-[72px]"
        style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="grid grid-cols-4 h-full text-center">

            <a href="/" class="flex flex-col items-center justify-center gap-1 text-lime">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
                </svg>
                <span class="text-[10px] font-bold">خانه</span>
            </a>

            <a href="/products" class="flex flex-col items-center justify-center gap-1 text-gray-400 active:text-lime">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0