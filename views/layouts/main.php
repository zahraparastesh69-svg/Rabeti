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

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>
        <?= htmlspecialchars(
            $pageTitle ?? 'رابطی — خرید عمده جوراب',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/app.css?v=18">

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

            <!-- راست -->

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
                    <span class="r6-logo">
                        رابطی
                    </span>

                    <span class="r6-logo-en">
                        RABETI
                    </span>
                </a>

            </div>

            <!-- مرکز -->

            <div class="header-center">

                <?php if ($isAdmin): ?>

                    <div class="header-user-state admin">
                        <span class="header-user-dot"></span>
                        <span>مدیریت</span>
                    </div>

                <?php elseif ($isCustomer): ?>

                    <div class="header-user-state">
                        <span class="header-user-dot"></span>

                        <span>
                            خوش آمدید،
                            <?= htmlspecialchars(
                                $userName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    </div>

                <?php endif; ?>

            </div>

            <!-- چپ -->

            <div class="header-left">

                <a
                    href="#"
                    class="header-action-btn"
                    title="سبد خرید"
                    aria-label="سبد خرید">

                    <svg
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 7V6a6 6 0 1112 0v1h2.2l1.4 12.6A2 2 0 0119.6 22H4.4a2 2 0 01-2-2.4L3.8 7H6zm2 0h8V6a4 4 0 10-8 0v1z" />
                    </svg>

                    <span
                        class="badge"
                        id="cartBadge"
                        style="display:none;">
                        ۰
                    </span>

                </a>

                <div
                    class="header-account"
                    id="headerAccount">

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
                            class="r-btn r-btn-outline"
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
                <span class="r6-logo">
                    رابطی
                </span>

                <span class="r6-logo-en">
                    RABETI
                </span>
            </a>

        </div>

        <div class="drawer-user-box">

            <?php if ($isAdmin): ?>

                <span class="drawer-user-label">
                    حساب مدیریت
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            <?php elseif ($isCustomer): ?>

                <span class="drawer-user-label">
                    حساب شما
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            <?php else: ?>

                <span class="drawer-user-label">
                    خوش آمدید
                </span>

                <strong>
                    به فروشگاه رابطی
                </strong>

            <?php endif; ?>

        </div>

        <nav class="drawer-nav">

            <?php if ($isAdmin): ?>

                <a
                    href="/admin"
                    class="r6-drawer-link drawer-admin-link">
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
                    href="<?= $isAdmin ? '/admin/logout' : '/api/logout' ?>"
                    class="r6-drawer-link drawer-logout-link">
                    خروج از حساب
                </a>

            <?php else: ?>

                <button
                    type="button"
                    class="r6-drawer-link drawer-login-button"
                    data-auth-open="login">
                    ورود / ثبت‌نام
                </button>

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

                    <span class="r6-logo">
                        رابطی
                    </span>

                    <span class="r6-logo-en">
                        RABETI
                    </span>

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

        <a
            href="/"
            class="bn-item">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.9">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.25 12l8.95-8.95a1.5 1.5 0 012.1 0L22.25 12M4.5 9.75v10.5h6v-6h3v6h6V9.75" />
            </svg>

            <span>خانه</span>
        </a>

        <a
            href="/products"
            class="bn-item">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.9">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>

            <span>دسته‌بندی</span>
        </a>

        <a
            href="/products"
            class="bn-item">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.9">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 21l-5.2-5.2M16.8 10.4a6.4 6.4 0 11-12.8 0 6.4 6.4 0 0112.8 0z" />
            </svg>

            <span>جستجو</span>
        </a>

        <a
            href="/custom-order"
            class="bn-item">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.9">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 3.5l2.4 5.2 5.6.7-4.1 3.9 1.1 5.5-5-2.7-5 2.7 1.1-5.5L4 9.4l5.6-.7L12 3.5z" />
            </svg>

            <span>سفارش</span>
        </a>

        <?php if ($isAdmin): ?>

            <a
                href="/admin"
                class="bn-item">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.9">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0" />
                </svg>

                <span>پنل</span>
            </a>

        <?php elseif ($isCustomer): ?>

            <a
                href="/api/logout"
                class="bn-item">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.9">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 6.5a3.5 3.5 0 117 0M4.5 20a7.5 7.5 0 0115 0" />
                </svg>

                <span>خروج</span>
            </a>

        <?php else: ?>

            <button
                type="button"
                class="bn-item"
                data-auth-open="login">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.9">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0" />
                </svg>

                <span>حساب</span>
            </button>

        <?php endif; ?>

    </nav>

    <!-- ══════════ Modal ورود / ثبت‌نام ══════════ -->

    <div
        class="auth-modal-overlay"
        id="authModalOverlay">

        <div
            class="auth-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="authModalTitle">

            <button
                type="button"
                class="auth-modal-close"
                id="authModalClose"
                aria-label="بستن">
                ×
            </button>

            <div class="auth-tabs">

                <button
                    type="button"
                    class="auth-tab active"
                    data-tab="login">
                    ورود
                </button>

                <button
                    type="button"
                    class="auth-tab"
                    data-tab="register">
                    ثبت‌نام
                </button>

                <span
                    class="auth-tab-slider"
                    id="authTabSlider"></span>

            </div>

            <!-- ورود -->

            <form
                class="auth-pane active"
                id="pane-login"
                method="post">

                <div class="auth-pane-top">

                    <span class="auth-pane-kicker">
                        حساب رابطی
                    </span>

                    <h3
                        class="auth-pane-title"
                        id="authModalTitle">
                        ورود به حساب
                    </h3>

                    <p class="auth-pane-note">
                        برای ادامه سفارش و مشاهده وضعیت حساب وارد شوید.
                    </p>

                </div>

                <div
                    class="form-error"
                    id="loginError"
                    style="display:none;"></div>

                <div class="form-field">

                    <label for="loginMobile">
                        شماره موبایل
                    </label>

                    <input
                        type="tel"
                        name="mobile"
                        id="loginMobile"
                        dir="ltr"
                        inputmode="numeric"
                        autocomplete="tel"
                        placeholder="09121234567"
                        required>

                </div>

                <div class="form-field">

                    <label for="loginPassword">
                        رمز عبور
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="loginPassword"
                        dir="ltr"
                        autocomplete="current-password"
                        required>

                </div>

                <button
                    type="submit"
                    class="r-btn r-btn-primary co-submit"
                    id="loginBtn">
                    ورود
                </button>

            </form>

            <!-- ثبت نام -->

            <form
                class="auth-pane"
                id="pane-register"
                method="post">

                <div class="auth-pane-top">

                    <span class="auth-pane-kicker">
                        شروع همکاری
                    </span>

                    <h3 class="auth-pane-title">
                        ثبت‌نام
                    </h3>

                    <p class="auth-pane-note">
                        اطلاعات شما پس از ثبت، طبق روند فروشگاه بررسی می‌شود.
                    </p>

                </div>

                <div class="form-field">

                    <label>
                        نام و نام خانوادگی
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        autocomplete="name"
                        required>

                </div>

                <div class="form-field">

                    <label>
                        نام مغازه / شرکت
                    </label>

                    <input
                        type="text"
                        name="company_name"
                        autocomplete="organization"
                        required>

                </div>

                <div class="form-field">

                    <label>
                        موبایل
                    </label>

                    <input
                        type="tel"
                        name="mobile"
                        dir="ltr"
                        inputmode="numeric"
                        autocomplete="tel"
                        placeholder="09121234567"
                        required>

                </div>

                <div class="form-field">

                    <label>
                        رمز عبور
                    </label>

                    <input
                        type="password"
                        name="password"
                        dir="ltr"
                        autocomplete="new-password"
                        placeholder="حداقل ۸ کاراکتر"
                        required>

                </div>

                <div class="form-field">

                    <label>
                        تکرار رمز عبور
                    </label>

                    <input
                        type="password"
                        name="password2"
                        dir="ltr"
                        autocomplete="new-password"
                        required>

                </div>

                <button
                    type="submit"
                    class="r-btn r-btn-primary co-submit">
                    ایجاد حساب
                </button>

            </form>

        </div>

    </div>

    <script src="/assets/js/app.js?v=18"></script>

</body>

</html>