<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'تولیدی جوراب رابطی' ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

    <!-- ▂▂▂ هدر چسبان ▂▂▂ -->
    <header class="header">
        <div class="header-inner">
            <a href="/" class="brand">
                <span class="brand-icon">
                    <!-- آیکون مغازه (SVG داخلی) -->
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64M21 21V14.3m0 0L19.3 5.7a1.5 1.5 0 00-1.47-1.2H6.17a1.5 1.5 0 00-1.47 1.2L3 14.3m18 0H3m4.5 6.75V16.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v4.5" />
                    </svg>
                </span>
                <span>تولیدی جوراب رابطی<small>عمده — بدون واسطه</small></span>
            </a>

            <button class="hamburger" id="menuBtn" aria-label="منو">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <!-- ▂▂▂ کشوی منو ▂▂▂ -->
    <div class="overlay" id="overlay"></div>
    <aside class="drawer" id="drawer">
        <div class="drawer-head">
            <span class="font-bold">منو</span>
            <button class="drawer-close" id="closeBtn" aria-label="بستن">×</button>
        </div>
        <nav class="drawer-nav">
            <a href="/" class="drawer-link">🏠 خانه</a>
            <a href="/products" class="drawer-link">🧦 محصولات</a>
            <a href="/custom-order" class="drawer-link">🏷️ سفارش تولید اختصاصی</a>
            <a href="/about" class="drawer-link">🏭 درباره تولیدی</a>
            <a href="/contact" class="drawer-link">📞 تماس با ما</a>
            <a href="/login" class="drawer-link">👤 ورود / ثبت‌نام</a>
        </nav>
    </aside>

    <!-- ▂▂▂ محتوا ▂▂▂ -->
    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ فوتر ▂▂▂ -->
    <footer class="footer">
        <div class="footer-inner">
            <div>
                <div class="footer-brand">
                    <span class="brand-icon" style="width:36px;height:36px;">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64M21 21V14.3m0 0L19.3 5.7a1.5 1.5 0 00-1.47-1.2H6.17a1.5 1.5 0 00-1.47 1.2L3 14.3m18 0H3m4.5 6.75V16.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v4.5" />
                        </svg>
                    </span>
                    تولیدی جوراب رابطی
                </div>
                <p class="footer-desc">تولید و پخش عمده جوراب — سفارش مستقیم از کارخانه، بدون واسطه، با کیفیت تضمینی</p>
            </div>
            <div>
                <h4>دسترسی سریع</h4>
                <ul>
                    <li><a href="/products">کاتالوگ محصولات</a></li>
                    <li><a href="/register">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order">سفارش تولید اختصاصی</a></li>
                </ul>
            </div>
            <div>
                <h4>تماس با ما</h4>
                <ul>
                    <li><a href="tel:02112345678">☎️ ۰۲۱-۱۲۳۴۵۶۷۸</a></li>
                    <li><a href="tel:09121234567">📱 ۰۹۱۲-۱۲۳۴۵۶۷</a></li>
                    <li><a href="#">🕐 شنبه تا پنجشنبه — ۹ تا ۱۷</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-copy">© تمامی حقوق برای تولیدی جوراب رابطی محفوظ است</div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>

</html>