<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

$isAdmin    = is_admin_logged_in();
$isCustomer = is_customer_logged_in();
$isLoggedIn = $isAdmin || $isCustomer;

$userName = $isAdmin
    ? ($_SESSION['admin_name'] ?? 'مدیر')
    : ($_SESSION['customer_name'] ?? 'کاربر');
?>
<?php
$cartCount = 0;
if (is_logged_in()) {
    try {
        $uid = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
        $stmt = db()->prepare("
            SELECT COALESCE(SUM(ci.qty), 0)
            FROM cart_items ci
            JOIN carts c ON c.id = ci.cart_id
            WHERE c.user_id = ? AND c.status = 'active'
        ");
        $stmt->execute([$uid]);
        $cartCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>
        <?= htmlspecialchars(
            $pageTitle ?? 'رابطی — خرید عمده جوراب',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/app.css?v=35">

</head>

<body class="app-body">

    <!-- ══════════ نوار اعلان ══════════ -->

    <div class="r6-announce">
        ارسال رایگان برای سفارش‌های بالای ۱۰ میلیون تومان
    </div>

    <!-- ══════════ هدر ══════════ -->

    <header
        class="header"
        id="siteHeader">

        <div class="header-inner">

            <div class="header-right">

                <button
                    type="button"
                    class="hamburger"
                    id="menuBtn"
                    aria-label="باز کردن منو">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <a
                    href="/"
                    class="brand r6-brand">
                    <span class="r6-logo">رابطی</span>
                    <span class="r6-logo-en">RABETI</span>
                </a>

            </div>

            <div class="header-left">

                <a href="/cart" class="header-action-btn" title="سبد خرید" aria-label="سبد خرید">
                    <svg
                        width="19"
                        height="19"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 7V6a6 6 0 1112 0v1h2.2l1.4 12.6A2 2 0 0119.6 22H4.4a2 2 0 01-2-2.4L3.8 7H6zm2 0h8V6a4 4 0 10-8 0v1z" />
                    </svg>

                    <span class="badge" id="cartBadge" <?= $cartCount ? '' : 'style="display:none;"' ?>><?= $cartCount ?></span>
                </a>

                <div class="header-account">

                    <?php if ($isAdmin): ?>

                        <a
                            href="/admin"
                            class="header-account-btn">
                            پنل مدیریت
                        </a>

                        <a
                            href="/admin/logout"
                            class="header-account-logout">
                            خروج
                        </a>

                    <?php elseif ($isCustomer): ?>

                        <span class="header-account-name">
                            <?= htmlspecialchars(
                                $userName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <a
                            href="/api/logout"
                            class="header-account-logout">
                            خروج
                        </a>

                    <?php else: ?>

                        <button
                            type="button"
                            class="r-btn r-btn-outline auth-trigger"
                            data-auth-open="login">
                            ورود / ثبت‌نام
                        </button>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </header>

    <!-- ══════════ کشوی موبایل ══════════ -->

    <div
        class="overlay"
        id="overlay"></div>

    <aside
        class="drawer r6-drawer"
        id="drawer">

        <div class="drawer-head">

            <button
                type="button"
                class="drawer-close"
                id="closeBtn"
                aria-label="بستن">
                ×
            </button>

            <a
                href="/"
                class="brand r6-brand">
                <span class="r6-logo">رابطی</span>
                <span class="r6-logo-en">RABETI</span>
            </a>

        </div>

        <?php if ($isLoggedIn): ?>

            <div class="drawer-user-box">

                <span>
                    <?= $isAdmin
                        ? 'حساب مدیریت'
                        : 'حساب شما' ?>
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>
            <?php if ($isCustomer): ?>
                <a href="/account-orders" class="r6-drawer-link">سفارش‌های من</a>
            <?php endif; ?>

        <?php endif; ?>

        <nav class="drawer-nav">

            <?php if ($isAdmin): ?>

                <a
                    href="/admin"
                    class="r6-drawer-link">
                    پنل مدیریت
                </a>

            <?php endif; ?>

            <a
                href="/products"
                class="r6-drawer-link">
                محصولات
            </a>

            <a
                href="/products"
                class="r6-drawer-link">
                پرفروش‌ها
            </a>

            <a
                href="/products"
                class="r6-drawer-link">
                جدیدها
            </a>

            <a
                href="/custom-order"
                class="r6-drawer-link">
                سفارش اختصاصی
            </a>


            <a
                href="/contact"
                class="r6-drawer-link">
                راهنمای سفارش
            </a>

            <a
                href="/about"
                class="r6-drawer-link">
                درباره رابطی
            </a>

            <?php if ($isLoggedIn): ?>

                <a
                    href="<?= $isAdmin
                                ? '/admin/logout'
                                : '/api/logout' ?>"
                    class="r6-drawer-link">
                    خروج از حساب
                </a>

            <?php endif; ?>

        </nav>

    </aside>

    <!-- ══════════ محتوا ══════════ -->

    <main class="container page-shell">
        <?= $content ?? '' ?>
    </main>

    <!-- ══════════ فوتر ══════════ -->

    <footer class="r6-footer">

        <div class="container">

            <div class="r6-footer-top">

                <div class="r6-footer-brand">

                    <span class="r6-logo">رابطی</span>
                    <span class="r6-logo-en">RABETI</span>

                    <p>
                        تولید و پخش عمده جوراب با قیمت همکاری،
                        موجودی واقعی و ارسال سریع.
                    </p>

                </div>

                <div class="r6-footer-cols">

                    <div>
                        <b>خرید</b>
                        <a href="/products">محصولات</a>
                        <a href="/custom-order">سفارش اختصاصی</a>
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

            <div class="r6-footer-bottom">
                © کارگاه تولیدی جوراب رابطی
            </div>

        </div>

    </footer>

    <!-- ══════════ ابزارها ══════════ -->

    <button
        class="back-to-top"
        id="backToTop"
        aria-label="بازگشت به بالا">
        ↑
    </button>

    <div
        class="toast"
        id="toast"></div>

    <!-- ══════════ نوار پایین موبایل ══════════ -->

    <nav
        class="bottom-nav"
        aria-label="ناوبری اصلی">

        <a href="/" class="bn-item">
            <span>خانه</span>
        </a>

        <a href="/products" class="bn-item">
            <span>دسته‌بندی</span>
        </a>

        <a href="/products" class="bn-item">
            <span>جستجو</span>
        </a>

        <a href="/custom-order" class="bn-item">
            <span>سفارش</span>
        </a>

        <?php if ($isAdmin): ?>

            <a
                href="/admin"
                class="bn-item">
                <span>پنل</span>
            </a>

        <?php elseif ($isCustomer): ?>

            <a
                href="/api/logout"
                class="bn-item">
                <span>خروج</span>
            </a>

        <?php else: ?>

            <button
                type="button"
                class="bn-item"
                data-auth-open="login">
                <span>حساب</span>
            </button>

        <?php endif; ?>

    </nav>
    <!-- ═══ Modal ورود / ثبت‌نام / فراموشی رمز ═══ -->
    <!-- ═══ Modal ورود / ثبت‌نام / فراموشی رمز ═══ -->
    <div class="auth-modal-overlay" id="authModalOverlay">
        <div class="auth-modal" role="dialog" aria-modal="true">
            <button class="auth-modal-close" id="authModalClose" aria-label="بستن">×</button>

            <!-- ═══ تب‌های اصلی — بیرون نماها، همیشه بالای مودال ═══ -->
            <div class="auth-tabs" id="authTabsBox">
                <button type="button" class="auth-tab active" data-tab="login">ورود</button>
                <button type="button" class="auth-tab" data-tab="register">ثبت‌نام</button>
                <span class="auth-tab-slider" id="authTabSlider"></span>
            </div>

            <!-- ═══ نما: ورود ═══ -->
            <div class="auth-view active" id="view-login">
                <form class="auth-pane active" id="pane-login" method="post">
                    <h3 class="auth-pane-title">ورود به حساب</h3>

                    <div class="form-error" id="loginError" style="display:none;"></div>

                    <div class="form-field">
                        <label for="loginMobile">شماره موبایل</label>
                        <input type="tel" name="mobile" id="loginMobile" dir="ltr"
                            inputmode="numeric" autocomplete="tel"
                            placeholder="09121234567" required>
                    </div>
                    <div class="form-field">
                        <label for="loginPassword">رمز عبور</label>
                        <input type="password" name="password" id="loginPassword" dir="ltr"
                            autocomplete="current-password" required>
                    </div>

                    <button type="button" class="auth-forgot-link" id="forgotLink">
                        فراموشی رمز عبور؟
                    </button>

                    <button type="submit" class="btn btn-primary co-submit" id="loginBtn">ورود</button>
                </form>
            </div>

            <!-- ═══ نما: ثبت‌نام ═══ -->
            <div class="auth-view" id="view-register">

                <!-- نوع حساب: حقیقی / حقوقی -->
                <div class="auth-tabs auth-tabs-sm" style="margin:0 0 14px;">
                    <button type="button" class="auth-tab active" data-utype="real">شخص حقیقی</button>
                    <button type="button" class="auth-tab" data-utype="legal">شخص حقوقی</button>
                    <span class="auth-tab-slider" id="utypeSlider"></span>
                </div>

                <form class="auth-pane active" id="pane-register" method="post" novalidate>
                    <h3 class="auth-pane-title">ثبت‌نام عمده‌فروشی</h3>

                    <div class="form-error" id="registerError" style="display:none;"></div>

                    <div class="form-field">
                        <label>نام و نام خانوادگی <i>*</i></label>
                        <input type="text" name="full_name" autocomplete="name" required>
                    </div>

                    <!-- فیلدهای حقیقی -->
                    <div class="utype-real">
                        <div class="form-field">
                            <label>کد ملی <i>*</i></label>
                            <input type="text" name="national_code" dir="ltr"
                                inputmode="numeric" maxlength="10" placeholder="کد ملی ۱۰ رقمی">
                        </div>
                        <div class="form-field">
                            <label>جنسیت <i>*</i></label>
                            <div style="display:flex;gap:14px;">
                                <label style="display:flex;align-items:center;gap:5px;font-weight:400;">
                                    <input type="radio" name="gender" value="male" checked> آقا
                                </label>
                                <label style="display:flex;align-items:center;gap:5px;font-weight:400;">
                                    <input type="radio" name="gender" value="female"> خانم
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- فیلدهای حقوقی -->
                    <div class="utype-legal" style="display:none;">

                        <div class="form-field">
                            <label>نام مغازه / شرکت</label>
                            <input type="text" name="company_name" autocomplete="organization">
                        </div>
                        <div class="form-field">
                            <label>شناسه اقتصادی <i>*</i></label>
                            <input type="text" name="economic_code" dir="ltr"
                                inputmode="numeric" maxlength="12"
                                placeholder="۱۰ تا ۱۲ رقم">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>شماره موبایل <i>*</i></label>
                        <input type="tel" name="mobile" dir="ltr" inputmode="numeric"
                            autocomplete="tel" placeholder="09121234567" required>
                    </div>

                    <div class="form-field">
                        <label>ایمیل</label>
                        <input type="email" name="email" dir="ltr" autocomplete="email">
                    </div>

                    <div class="form-field">
                        <label>رمز عبور <i>*</i></label>
                        <input type="password" name="password" dir="ltr"
                            autocomplete="new-password" placeholder="حداقل ۸ کاراکتر" required>
                    </div>

                    <div class="form-field">
                        <label>تکرار رمز عبور <i>*</i></label>
                        <input type="password" name="password2" dir="ltr"
                            autocomplete="new-password" required>
                    </div>

                </form>

                <!-- دکمه ثبت — جدا از فرم اصلی، همیشه پایینِ دید -->
                <form method="post" id="registerSubmitForm" novalidate>
                    <button type="submit" class="btn btn-primary co-submit" id="registerBtn">
                        ایجاد حساب
                    </button>
                </form>

            </div>

            <!-- ═══ نما: فراموشی رمز ═══ -->
            <div class="auth-view" id="view-forgot">

                <!-- مرحله ۱: انتخاب روش -->
                <div id="forgot-step-1">
                    <h3 class="auth-pane-title">بازیابی رمز عبور</h3>
                    <p class="auth-pane-note">روش دریافت کد بازیابی را انتخاب کنید.</p>

                    <div class="form-error" id="forgotError" style="display:none;"></div>

                    <div class="forgot-channel-grid">
                        <button type="button" class="forgot-channel" data-channel="sms">
                            <span class="fc-icon">📱</span>
                            <b>ارسال به موبایل</b>
                            <small>کد پیامکی</small>
                        </button>
                        <button type="button" class="forgot-channel" data-channel="email">
                            <span class="fc-icon">📧</span>
                            <b>ارسال به ایمیل</b>
                            <small>کد ایمیلی</small>
                        </button>
                    </div>
                </div>

                <!-- مرحله ۲: ورود شماره/ایمیل -->
                <div id="forgot-step-2" style="display:none;">
                    <button type="button" class="auth-back" id="forgotBack1">→</button>
                    <h3 class="auth-pane-title" id="forgotTargetTitle">ارسال کد بازیابی</h3>

                    <div class="form-field">
                        <label id="forgotInputLabel">شماره موبایل</label>
                        <input type="text" id="forgotTarget" dir="ltr" required>
                    </div>

                    <button type="button" class="btn btn-primary co-submit" id="forgotSendBtn">
                        ارسال کد بازیابی
                    </button>
                </div>

                <!-- مرحله ۳: کد + رمز جدید -->
                <div id="forgot-step-3" style="display:none;">
                    <button type="button" class="auth-back" id="forgotBack2">→</button>
                    <h3 class="auth-pane-title">ثبت کد و رمز جدید</h3>

                    <div class="form-error" id="forgotError2" style="display:none;"></div>

                    <div class="form-field">
                        <label>کد ۶ رقمی ارسال‌شده</label>
                        <input type="text" id="forgotCode" dir="ltr" inputmode="numeric"
                            maxlength="6" placeholder="------" required>
                    </div>
                    <div class="form-field">
                        <label>رمز عبور جدید</label>
                        <input type="password" id="forgotNewPass" dir="ltr"
                            placeholder="حداقل ۸ کاراکتر" required>
                    </div>
                    <div class="form-field">
                        <label>تکرار رمز عبور جدید</label>
                        <input type="password" id="forgotNewPass2" dir="ltr" required>
                    </div>

                    <button type="button" class="btn btn-primary co-submit" id="forgotResetBtn">
                        تغییر رمز عبور
                    </button>
                </div>

                <!-- مرحله ۴: موفقیت -->
                <div id="forgot-step-4" style="display:none;">
                    <div class="payment-result payment-result-success">
                        <div class="payment-result-icon">✓</div>
                        <div>
                            <h2>رمز عبور تغییر کرد</h2>
                            <p>حالا با رمز جدید وارد شوید.</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary co-submit" id="forgotDoneBtn">
                        رفتن به ورود
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script src="/assets/js/app.js?v=24"></script>

</body>

</html>