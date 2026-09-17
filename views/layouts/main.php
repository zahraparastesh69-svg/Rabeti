<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title><?= $pageTitle ?? 'جوراب‌لند — بازار عمده جوراب' ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css"
        rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#111111',
                        accent: '#E8A83E',
                        surface: '#FFFFFF',
                        soft: '#F5F4F1',
                        line: '#E8E6E1',
                        muted: '#77736C'
                    },

                    fontFamily: {
                        vazir: ['Vazirmatn', 'Tahoma', 'sans-serif']
                    },

                    boxShadow: {
                        soft: '0 10px 35px rgba(0,0,0,.07)',
                        card: '0 14px 45px rgba(0,0,0,.08)',
                        floating: '0 20px 60px rgba(0,0,0,.12)',
                        inset: 'inset 0 1px 0 rgba(255,255,255,.8)'
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --bg: #f7f6f3;
            --surface: #ffffff;
            --text: #151515;
            --muted: #77736c;
            --line: #e7e4de;
            --accent: #e8a83e;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* --------------------------------
           نرم و مینیمال
        -------------------------------- */

        ::selection {
            background: #111;
            color: #fff;
        }

        a {
            text-decoration: none;
        }

        /* --------------------------------
           Header
        -------------------------------- */

        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;

            background: rgba(247, 246, 243, .88);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            border-bottom: 1px solid rgba(0, 0, 0, .06);
        }

        .header-inner {
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* --------------------------------
           Logo
        -------------------------------- */

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: #111;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -.5px;
        }

        .logo-mark {
            width: 34px;
            height: 34px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background: #111;
            color: #fff;

            font-size: 14px;
            font-weight: 900;

            box-shadow:
                0 5px 12px rgba(0, 0, 0, .16),
                inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        /* --------------------------------
           Desktop Navigation
        -------------------------------- */

        .desktop-nav {
            display: flex;
            align-items: center;
            gap: 34px;
        }

        .desktop-nav a {
            position: relative;

            color: #68645e;
            font-size: 13px;
            font-weight: 600;

            transition:
                color .2s ease,
                transform .2s ease;
        }

        .desktop-nav a:hover {
            color: #111;
            transform: translateY(-1px);
        }

        .desktop-nav a::after {
            content: '';

            position: absolute;
            bottom: -9px;
            right: 0;

            width: 0;
            height: 2px;

            background: #111;
            border-radius: 10px;

            transition: width .25s ease;
        }

        .desktop-nav a:hover::after {
            width: 100%;
        }

        /* --------------------------------
           Header Actions
        -------------------------------- */

        .header-actions {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .icon-button {
            width: 42px;
            height: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background: #fff;
            color: #333;

            border: 1px solid #e8e5df;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .04),
                inset 0 1px 0 #fff;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .icon-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .08);
        }

        /* --------------------------------
           Main
        -------------------------------- */

        .page-container {
            max-width: 1280px;
            margin: auto;
            padding: 42px 20px 80px;
        }

        /* --------------------------------
           3D Cards
        -------------------------------- */

        .card-3d {
            position: relative;

            background: #fff;

            border: 1px solid rgba(0, 0, 0, .055);
            border-radius: 22px;

            box-shadow:
                0 8px 20px rgba(0, 0, 0, .045),
                0 25px 55px rgba(0, 0, 0, .035),
                inset 0 1px 0 rgba(255, 255, 255, .9);

            transition:
                transform .28s cubic-bezier(.2, .8, .2, 1),
                box-shadow .28s ease;
        }

        .card-3d:hover {
            transform: translateY(-5px);

            box-shadow:
                0 15px 30px rgba(0, 0, 0, .07),
                0 30px 70px rgba(0, 0, 0, .07),
                inset 0 1px 0 rgba(255, 255, 255, 1);
        }

        /* --------------------------------
           Product Card
        -------------------------------- */

        .product-card {
            overflow: hidden;
        }

        .product-image {
            position: relative;

            aspect-ratio: 1 / 1;

            background:
                linear-gradient(145deg,
                    #f3f2ef,
                    #ffffff);

            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            transition:
                transform .5s cubic-bezier(.2, .8, .2, 1);
        }

        .product-card:hover .product-image img {
            transform: scale(1.045);
        }

        /* --------------------------------
           Buttons
        -------------------------------- */

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 46px;

            padding: 0 20px;

            border-radius: 13px;

            background: #111;
            color: #fff;

            font-size: 13px;
            font-weight: 700;

            box-shadow:
                0 5px 0 #000,
                0 12px 22px rgba(0, 0, 0, .12);

            transition:
                transform .15s ease,
                box-shadow .15s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);

            box-shadow:
                0 7px 0 #000,
                0 16px 28px rgba(0, 0, 0, .14);
        }

        .btn-primary:active {
            transform: translateY(3px);

            box-shadow:
                0 2px 0 #000,
                0 5px 12px rgba(0, 0, 0, .1);
        }

        .btn-accent {
            background: var(--accent);
            color: #111;

            box-shadow:
                0 5px 0 #c48a2c,
                0 12px 22px rgba(232, 168, 62, .18);
        }

        .btn-accent:hover {
            box-shadow:
                0 7px 0 #c48a2c,
                0 16px 28px rgba(232, 168, 62, .22);
        }

        /* --------------------------------
           Badge
        -------------------------------- */

        .badge {
            display: inline-flex;
            align-items: center;

            padding: 6px 10px;

            border-radius: 9px;

            background: #f1f0ed;
            color: #55514b;

            font-size: 10px;
            font-weight: 700;
        }

        .badge-accent {
            background: #fff1d8;
            color: #9a651b;
        }

        /* --------------------------------
           Section titles
        -------------------------------- */

        .section-title {
            font-size: clamp(22px, 3vw, 34px);
            font-weight: 900;
            letter-spacing: -.7px;
            line-height: 1.4;
        }

        .section-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 2;
        }

        /* --------------------------------
           Footer
        -------------------------------- */

        .site-footer {
            background: #111;
            color: #aaa;
            margin-top: 80px;
        }

        .footer-title {
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .footer-link {
            color: #999;
            font-size: 12px;

            transition: color .2s ease;
        }

        .footer-link:hover {
            color: #fff;
        }

        /* --------------------------------
           Mobile Bottom Navigation
        -------------------------------- */

        .mobile-nav {
            background: rgba(255, 255, 255, .94);

            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);

            border-top: 1px solid rgba(0, 0, 0, .07);

            box-shadow:
                0 -8px 30px rgba(0, 0, 0, .06);
        }

        .mobile-nav a {
            color: #888;
            transition: color .2s ease;
        }

        .mobile-nav a.active {
            color: #111;
        }

        .mobile-nav .nav-icon {
            width: 38px;
            height: 30px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;
        }

        .mobile-nav a.active .nav-icon {
            background: #111;
            color: #fff;
        }

        /* --------------------------------
           Mobile
        -------------------------------- */

        @media (max-width: 767px) {

            body {
                padding-bottom: 78px;
            }

            .header-inner {
                height: 64px;
            }

            .desktop-nav {
                display: none;
            }

            .page-container {
                padding: 24px 14px 50px;
            }

            .logo {
                font-size: 17px;
            }

            .logo-mark {
                width: 31px;
                height: 31px;
                border-radius: 10px;
            }

            .header-actions {
                gap: 6px;
            }

            .icon-button {
                width: 38px;
                height: 38px;
                border-radius: 11px;
            }

            .btn-primary,
            .btn-accent {
                min-height: 44px;
                border-radius: 12px;
            }

            .card-3d {
                border-radius: 18px;
            }
        }

        /* --------------------------------
           Reduce motion
        -------------------------------- */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>


<body>

    <!-- =========================
         HEADER
    ========================== -->

    <header class="site-header">

        <div class="max-w-7xl mx-auto px-4">

            <div class="header-inner">

                <!-- Logo -->

                <a href="/" class="logo">

                    <span class="logo-mark">
                        ج
                    </span>

                    <span>
                        جوراب‌<strong>لند</strong>
                    </span>

                </a>


                <!-- Desktop Navigation -->

                <nav class="desktop-nav">

                    <a href="/">خانه</a>

                    <a href="/products">
                        محصولات
                    </a>

                    <a href="/custom-order">
                        تولید اختصاصی
                    </a>

                    <a href="/about">
                        درباره ما
                    </a>

                </nav>


                <!-- Actions -->

                <div class="header-actions">

                    <a
                        href="/cart"
                        class="icon-button relative"
                        title="سبد خرید">

                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            viewBox="0 0 24 24">

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2" />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 21a.75.75 0 100-1.5A.75.75 0 009 21zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />

                        </svg>


                        <span
                            id="cart-badge"
                            class="hidden absolute -top-1 -left-1 bg-black text-white text-[9px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
                            ۰
                        </span>

                    </a>


                    <a
                        href="/login"
                        class="btn-primary hidden sm:inline-flex">
                        ورود
                    </a>

                </div>

            </div>

        </div>

    </header>


    <!-- =========================
         MAIN
    ========================== -->

    <main class="page-container">

        <?= $content ?? '' ?>

    </main>


    <!-- =========================
         FOOTER
    ========================== -->

    <footer class="site-footer hidden md:block">

        <div class="max-w-7xl mx-auto px-4 py-16">

            <div class="grid md:grid-cols-3 gap-14">

                <!-- Brand -->

                <div>

                    <div class="flex items-center gap-3 mb-5">

                        <span class="logo-mark">
                            ج
                        </span>

                        <div class="text-white font-black">
                            جوراب‌لند
                        </div>

                    </div>

                    <p class="text-sm leading-8 text-gray-500 max-w-sm">

                        بازار آنلاین عمده جوراب؛
                        سفارش مستقیم از تولیدکننده
                        با امکان تولید اختصاصی.

                    </p>

                </div>


                <!-- Links -->

                <div>

                    <div class="footer-title">
                        دسترسی سریع
                    </div>

                    <div class="space-y-3">

                        <a
                            href="/products"
                            class="footer-link block">
                            کاتالوگ محصولات
                        </a>

                        <a
                            href="/register"
                            class="footer-link block">
                            ثبت‌نام عمده‌فروشان
                        </a>

                        <a
                            href="/custom-order"
                            class="footer-link block">
                            تولید اختصاصی
                        </a>

                    </div>

                </div>


                <!-- Contact -->

                <div>

                    <div class="footer-title">
                        ارتباط با ما
                    </div>

                    <div class="space-y-3 text-xs">

                        <div dir="ltr" class="text-right">
                            ۰۲۱ - ۱۲۳۴۵۶۷۸
                        </div>

                        <div dir="ltr" class="text-right">
                            ۰۹۱۲ - ۱۲۳۴۵۶۷
                        </div>

                        <div>
                            شنبه تا پنجشنبه — ۹ تا ۱۷
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="border-t border-white/10">

            <div
                class="max-w-7xl mx-auto px-4 py-5 text-center text-xs text-gray-600">

                © تمامی حقوق برای جوراب‌لند محفوظ است

            </div>

        </div>

    </footer>


    <!-- =========================
         MOBILE NAV
    ========================== -->

    <nav
        class="mobile-nav md:hidden fixed bottom-0 inset-x-0 z-50 h-[72px]"
        style="padding-bottom: env(safe-area-inset-bottom)">

        <div class="grid grid-cols-4 h-full">

            <!-- Home -->

            <a
                href="/"
                class="active flex flex-col items-center justify-center gap-1">

                <span class="nav-icon">

                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4.5 10v10h6v-6h3v6h6V10" />

                    </svg>

                </span>

                <span class="text-[9px] font-bold">
                    خانه
                </span>

            </a>


            <!-- Products -->

            <a
                href="/products"
                class="flex flex-col items-center justify-center gap-1">

                <span class="nav-icon">

                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v13a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 18.5v-13z" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8 7h8M8 11h8M8 15h5" />

                    </svg>

                </span>

                <span class="text-[9px] font-bold">
                    محصولات
                </span>

            </a>


            <!-- Custom -->

            <a
                href="/custom-order"
                class="flex flex-col items-center justify-center gap-1">

                <span class="nav-icon">

                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3v18M3 12h18" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6l12 12M18 6L6 18" />

                    </svg>

                </span>

                <span class="text-[9px] font-bold">
                    تولید اختصاصی
                </span>

            </a>


            <!-- Account -->

            <a
                href="/login"
                class="flex flex-col items-center justify-center gap-1">

                <span class="nav-icon">

                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24">

                        <circle
                            cx="12"
                            cy="8"
                            r="3.2" />

                        <path
                            stroke-linecap="round"
                            d="M5 20c.8-3.2 3.2-5 7-5s6.2 1.8 7 5" />

                    </svg>

                </span>

                <span class="text-[9px] font-bold">
                    حساب من
                </span>

            </a>

        </div>

    </nav>

</body>

</html>