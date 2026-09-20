<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'رابطی — خرید عمده جوراب' ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=15">
</head>

<body class="app-body">

    <!-- ▂▂▂ نوار اعلان ▂▂▂ -->
    <div class="r6-announce">ارسال رایگان برای سفارش‌های بالای ۱۰ میلیون تومان</div>

    <!-- ▂▂▂ هدر ▂▂▂ -->
    <header class="header" id="siteHeader">
        <div class="header-inner">
            <div class="header-right">
                <button class="hamburger" id="menuBtn" aria-label="منو">
                    <span></span><span></span><span></span>
                </button>
                <a href="/" class="brand r6-brand">
                    <span class="r6-logo">رابطی</span>
                    <span class="r6-logo-en">RABETI</span>
                </a>
            </div>
            <div class="header-left">
                <a href="/products" class="header-action-btn" title="جستجو">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M16.8 10.4a6.4 6.4 0 11-12.8 0 6.4 6.4 0 0112.8 0z" />
                    </svg>
                </a>
                <a href="#" class="header-action-btn" title="سبد خرید">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 7V6a6 6 0 1112 0v1h2.2l1.4 12.6A2 2 0 0119.6 22H4.4a2 2 0 01-2-2.4L3.8 7H6zm2 0h8V6a4 4 0 10-8 0v1z" />
                    </svg>
                    <span class="badge" id="cartBadge" style="display:none;">۰</span>
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ کشوی منو ▂▂▂ -->
    <div class="overlay" id="overlay"></div>
    <aside class="drawer r6-drawer" id="drawer">
        <div class="drawer-head">
            <button class="drawer-close" id="closeBtn" aria-label="بستن">×</button>
            <a href="/" class="brand r6-brand">
                <span class="r6-logo">رابطی</span>
                <span class="r6-logo-en">RABETI</span>
            </a>
        </div>
        <nav class="drawer-nav">
            <a href="/products" class="r6-drawer-link">محصولات</a>
            <a href="/products" class="r6-drawer-link">پرفروش‌ها</a>
            <a href="/products" class="r6-drawer-link">جدیدها</a>
            <a href="/custom-order" class="r6-drawer-link">خرید عمده</a>
            <a href="/contact" class="r6-drawer-link">راهنمای سفارش</a>
        </nav>
        <div class="r6-drawer-foot">
            <button type="button" class="r-btn r-btn-outline" data-auth-open="login">ورود / ثبت‌نام</button>
        </div>
    </aside>

    <!-- ▂▂▂ محتوا ▂▂▂ -->
    <main class="container page-shell">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ فوتر ▂▂▂ -->
    <footer class="r6-footer">
        <div class="container">
            <div class="r6-footer-top">
                <div class="r6-footer-brand">
                    <span class="r6-logo">رابطی</span>
                    <span class="r6-logo-en">RABETI</span>
                    <p>تولید و پخش عمده جوراب با قیمت همکاری، موجودی واقعی و ارسال سریع.</p>
                </div>
                <div class="r6-footer-cols">
                    <div>
                        <b>خرید</b>
                        <a href="/products">محصولات</a>
                        <a href="/custom-order">خرید عمده</a>
                    </div>
                    <div>
                        <b>راهنما</b>
                        <a href="/contact">شرایط سفارش</a>
                        <a href="/contact">ارسال و پرداخت</a>
                    </div>
                    <div>
                        <b>رابطی</b>
                        <a href="/about">درباره ما</a>
                        <a href="/contact">تماس با ما</a>
                    </div>
                </div>
            </div>
            <div class="r6-footer-bottom">© کارگاه تولیدی جوراب رابطی</div>
        </div>
    </footer>

    <!-- ▂▂▂ بازگشت به بالا + توست ▂▂▂ -->
    <button class="back-to-top" id="backToTop" aria-label="بازگشت به بالا">↑</button>
    <div class="toast" id="toast"></div>

    <!-- ▂▂▂ نوار پایین اپ‌مانند (فقط موبایل) ▂▂▂ -->
    <nav class="bottom-nav" aria-label="ناوبری اصلی">
        <a href="/" class="bn-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
            </svg><span>خانه</span></a>
        <a href="/products" class="bn-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg><span>دسته‌بندی</span></a>
        <a href="/products" class="bn-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M16.8 10.4a6.4 6.4 0 11-12.8 0 6.4 6.4 0 0112.8 0z" />
            </svg><span>جستجو</span></a>
        <a href="#" class="bn-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4l1.6 12.3a2 2 0 002 1.7h9.7a2 2 0 002-1.6l1.3-6.4H5.2M9 21a.75.75 0 100-1.5.75.75 0 000 1.5zm8 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
            </svg><span>سفارش</span></a>
        <a href="/login" class="bn-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0" />
            </svg><span>حساب</span></a>
    </nav>

    <!-- ▂▂▂ Modal ورود / ثبت‌نام ▂▂▂ -->
    <div class="auth-modal-overlay" id="authModalOverlay">
        <div class="auth-modal" role="dialog" aria-modal="true">
            <button class="auth-modal-close" id="authModalClose" aria-label="بستن">×</button>
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">ورود</button>
                <button class="auth-tab" data-tab="register">ثبت‌نام</button>
                <span class="auth-tab-slider" id="authTabSlider"></span>
            </div>
            <form class="auth-pane active" id="pane-login" method="post" action="/login">
                <h3 class="auth-pane-title">ورود به حساب</h3>
                <div class="form-field">
                    <label>ایمیل</label>
                    <input type="email" name="email" dir="ltr" placeholder="" required>
                </div>
                <div class="form-field">
                    <label>رمز عبور</label>
                    <input type="password" name="password" dir="ltr" placeholder="" required>
                </div>
                <button type="submit" class="r-btn r-btn-primary co-submit">ورود</button>
            </form>
            <form class="auth-pane" id="pane-register" method="post" action="/register">
                <h3 class="auth-pane-title">ثبت‌نام عمده‌فروشی</h3>
                <p class="auth-pane-note">پس از ثبت، حساب شما توسط کارشناس بررسی و فعال می‌شود.</p>
                <div class="form-field">
                    <label>نام و نام خانوادگی</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-field">
                    <label>نام مغازه / شرکت</label>
                    <input type="text" name="company_name" required>
                </div>
                <div class="form-field">
                    <label>موبایل</label>
                    <input type="tel" name="mobile" dir="ltr" required>
                </div>
                <div class="form-field">
                    <label>رمز عبور</label>
                    <input type="password" name="password" dir="ltr" required>
                </div>
                <button type="submit" class="r-btn r-btn-primary co-submit">ثبت درخواست عضویت</button>
            </form>
        </div>
    </div>
    <script src="/assets/js/app.js"></script>
</body>

</html>