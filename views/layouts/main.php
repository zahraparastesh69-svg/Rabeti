<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'تولیدی جوراب رابطی' ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=6">
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
                    <button type="button" class="btn-login-highlight" data-auth-open="login">ورود / ثبت‌نام</button>
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
            <button type="button" class="drawer-link" data-auth-open="login" style="width:100%;background:none;border:none;cursor:pointer;font-family:inherit;">👤 ورود / ثبت‌نام</button>
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

    <!-- ▂▂▂ نوار پایین اپ‌مانند (فقط موبایل) ▂▂▂ -->
    <nav class="bottom-nav" aria-label="ناوبری اصلی">
        <a href="/" class="bn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
            </svg>
            <span>خانه</span>
        </a>
        <a href="/products" class="bn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            <span>محصولات</span>
        </a>
        <a href="#" class="bn-item bn-cart">
            <span class="bn-cart-badge" id="bnCartBadge" style="display:none;">۰</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
            </svg>
            <span>سبد</span>
        </a>
        <a href="/login" class="bn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0" />
            </svg>
            <span>حساب</span>
        </a>
    </nav>
    <!-- ▂▂▂ Modal ورود / ثبت‌نام ▂▂▂ -->
    <div class="auth-modal-overlay" id="authModalOverlay">
        <div class="auth-modal" role="dialog" aria-modal="true">
            <button class="auth-modal-close" id="authModalClose" aria-label="بستن">×</button>

            <!-- تب‌ها -->
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">ورود</button>
                <button class="auth-tab" data-tab="register">ثبت‌نام</button>
                <span class="auth-tab-slider" id="authTabSlider"></span>
            </div>

            <!-- فرم ورود -->
            <form class="auth-pane active" id="pane-login" method="post" action="/login">
                <h3 class="auth-pane-title">ورود به حساب</h3>
                <div class="form-field">
                    <label>موبایل</label>
                    <input type="tel" name="mobile" dir="ltr" placeholder="09121234567" required>
                </div>
                <div class="form-field">
                    <label>رمز عبور</label>
                    <input type="password" name="password" dir="ltr" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-orange co-submit">ورود ←</button>
            </form>

            <!-- فرم ثبت‌نام -->
            <form class="auth-pane" id="pane-register" method="post" action="/register">
                <h3 class="auth-pane-title">ثبت‌نام عمده‌فروشی</h3>
                <p class="auth-pane-note">پس از ثبت، حساب شما توسط کارشناس بررسی و فعال می‌شود.</p>
                <div class="form-field">
                    <label>نام و نام خانوادگی</label>
                    <input type="text" name="full_name" placeholder="مثلاً: کاظم قوطبی" required>
                </div>
                <div class="form-field">
                    <label>نام مغازه / شرکت</label>
                    <input type="text" name="company_name" placeholder="مثلاً: پخش قوطبی" required>
                </div>
                <div class="form-field">
                    <label>موبایل</label>
                    <input type="tel" name="mobile" dir="ltr" placeholder="09121234567" required>
                </div>
                <div class="form-field">
                    <label>رمز عبور</label>
                    <input type="password" name="password" dir="ltr" placeholder="حداقل ۸ کاراکتر" required>
                </div>
                <button type="submit" class="btn btn-orange co-submit">ثبت درخواست عضویت ←</button>
            </form>
        </div>
    </div>
    <script src="/assets/js/app.js"></script>
</body>

</html>