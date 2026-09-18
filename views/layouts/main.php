<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'تولیدی جوراب رابطی' ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="app-body">

    <!-- ▂▂▂ هدر ▂▂▂ -->
    <header class="header" id="siteHeader">
        <div class="header-inner">

            <div class="header-right">
                <button class="hamburger" id="menuBtn" aria-label="منو">
                    <span></span><span></span><span></span>
                </button>
                <a href="/" class="brand">
                    <span class="brand-icon"><span>🧦</span></span>
                    <span class="brand-text">
                        <span>جوراب رابطی</span>
                        <small>بازار عمده — مستقیم از تولیدی</small>
                    </span>
                </a>
            </div>

            <div class="header-left">
                <a href="#" class="header-action-btn hide-mobile" title="پیام‌ها">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.6 10.5a3.4 3.4 0 116.8 0c0 4.2 1.7 5.4 1.7 5.4H6.9s1.7-1.2 1.7-5.4M10.3 19a1.7 1.7 0 003.4 0" />
                    </svg>
                </a>
                <a href="#" class="header-action-btn hide-mobile" title="اعلان‌ها">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.9 17.1v1.1a2.9 2.9 0 11-5.8 0v-1.1M12 3.4a6 6 0 00-6 6v2.2c0 .8-.3 1.7-.9 2.3l-.7.8h15.2l-.7-.8c-.6-.6-.9-1.5-.9-2.3V9.4a6 6 0 00-6-6z" />
                    </svg>
                </a>
                <a href="#" class="header-action-btn" title="سبد خرید">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                    </svg>
                    <span class="badge" id="cartBadge" style="display:none;">۰</span>
                </a>

                <!-- 🔧 فاز بک‌اند: با لاگین، این بخش به user-menu تبدیل می‌شود -->
                <div class="user-auth-section">
                    <a href="/login" class="btn-login-highlight">ورود / ثبت‌نام</a>
                </div>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ کشوی منو ▂▂▂ -->
    <div class="overlay" id="overlay"></div>
    <aside class="drawer" id="drawer">
        <div class="drawer-head">
            <span style="font-weight:800;">منو</span>
            <button class="drawer-close" id="closeBtn" aria-label="بستن">×</button>
        </div>
        <nav class="drawer-nav">
            <a href="/" class="drawer-link">🏠 خانه</a>
            <a href="/products" class="drawer-link">🧦 کاتالوگ محصولات</a>
            <a href="/custom-order" class="drawer-link">🏷️ سفارش تولید اختصاصی</a>
            <a href="/about" class="drawer-link">🏭 درباره ما</a>
            <a href="/contact" class="drawer-link">📞 تماس با ما</a>
            <div class="drawer-divider"></div>
            <a href="/login" class="drawer-link">👤 ورود / ثبت‌نام</a>
        </nav>
    </aside>

    <!-- ▂▂▂ محتوا ▂▂▂ -->
    <main class="container page-shell">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ فوتر ▂▂▂ -->
    <footer class="footer">
        <div class="footer-glow"></div>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">
                        <span class="brand-icon"><span>🧦</span></span>
                        جوراب رابطی
                    </div>
                    <p class="footer-about">
                        بازار آنلاین خرید عمده جوراب — مستقیم از خط تولید،
                        بدون واسطه، با قیمت کارخانه و کیفیت تضمینی.
                    </p>
                    <div class="footer-social">
                        <a href="#" title="اینستاگرام">📷</a>
                        <a href="#" title="واتساپ">💬</a>
                        <a href="#" title="تلگرام">✈️</a>
                    </div>
                </div>
                <div>
                    <div class="footer-col-title">دسترسی سریع</div>
                    <ul class="footer-links">
                        <li><a href="/products">کاتالوگ محصولات</a></li>
                        <li><a href="/custom-order">سفارش تولید اختصاصی</a></li>
                        <li><a href="/register">ثبت‌نام عمده‌فروشان</a></li>
                        <li><a href="/about">درباره ما</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-title">تماس با ما</div>
                    <div class="footer-contact-item"><span class="ico">☎️</span><span dir="ltr">021-12345678</span></div>
                    <div class="footer-contact-item"><span class="ico">📱</span><span dir="ltr">0912-1234567</span></div>
                    <div class="footer-contact-item"><span class="ico">🕐</span><span>شنبه تا پنجشنبه — ۹ تا ۱۷</span></div>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© تمامی حقوق برای تولیدی جوراب رابطی محفوظ است</span>
                <a href="/contact">پشتیبانی و ارتباط با ما</a>
            </div>
        </div>
    </footer>

    <!-- ▂▂▂ بازگشت به بالا + توست ▂▂▂ -->
    <button class="back-to-top" id="backToTop" aria-label="بازگشت به بالا">↑</button>
    <div class="toast" id="toast"></div>

    <script src="/assets/js/app.js"></script>
</body>

</html>