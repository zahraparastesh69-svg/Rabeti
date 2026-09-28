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
        href="/assets/css/app.css?v=30">

    <style>
        /* ══════════ Auth Modal ══════════ */

        .auth-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(18, 32, 38, .56);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .22s ease, visibility .22s ease;
        }

        .auth-modal-overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .auth-modal {
            position: relative;
            width: min(430px, 100%);
            max-height: calc(100svh - 36px);
            overflow: hidden;
            border: 1px solid #e6e0d8;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 25px 80px rgba(18, 35, 42, .18);
        }

        .auth-modal-close {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 5;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border: 1px solid #e4e8e9;
            border-radius: 10px;
            background: #fff;
            color: #637179;
            font-family: inherit;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .auth-tabs {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin: 20px 20px 0;
            padding: 4px;
            border-radius: 12px;
            background: #f2f5f5;
        }

        .auth-tab {
            position: relative;
            z-index: 2;
            height: 40px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #809097;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .auth-tab.active {
            color: #24434e;
        }

        .auth-tab-slider {
            position: absolute;
            top: 4px;
            right: 4px;
            width: calc(50% - 4px);
            height: 40px;
            border-radius: 9px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(22, 42, 49, .07);
            transition: transform .22s ease;
        }

        .auth-tabs.register-mode .auth-tab-slider {
            transform: translateX(-100%);
        }

        .auth-modal .auth-pane {
            display: none;
            padding: 20px 24px 24px;
            overflow-y: auto;
            max-height: calc(100svh - 125px);
        }

        .auth-modal .auth-pane.active {
            display: block;
        }

        .auth-pane-top {
            margin-bottom: 16px;
            padding-left: 30px;
        }

        .auth-pane-kicker {
            display: block;
            margin-bottom: 5px;
            color: #87959a;
            font-size: 9px;
            font-weight: 800;
        }

        .auth-pane-title {
            margin: 0 0 6px;
            color: #263d45;
            font-size: 21px;
            font-weight: 900;
        }

        .auth-pane-note {
            margin: 0;
            color: #8a969b;
            font-size: 10px;
            line-height: 1.8;
        }

        .auth-modal .form-field {
            margin-bottom: 12px;
        }

        .auth-modal .form-field label {
            display: block;
            margin-bottom: 6px;
            color: #3a4d54;
            font-size: 11px;
            font-weight: 800;
        }

        .auth-modal .form-field input {
            width: 100%;
            height: 44px;
            padding: 0 12px;
            box-sizing: border-box;
            border: 1px solid #dce3e6;
            border-radius: 11px;
            background: #fbfcfc;
            color: #243840;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: .18s ease;
        }

        .auth-modal .form-field input:focus {
            background: #fff;
            border-color: #6d9aa6;
            box-shadow: 0 0 0 4px rgba(109, 154, 166, .10);
        }

        .auth-modal .r-btn {
            width: 100%;
            min-height: 44px;
        }

        .auth-modal .form-error {
            display: none;
            margin-bottom: 12px;
            padding: 9px 11px;
            border: 1px solid #edd0cb;
            border-radius: 10px;
            background: #fff6f4;
            color: #b04c43;
            font-size: 10px;
            line-height: 1.8;
        }

        .auth-trigger {
            border: 0;
            cursor: pointer;
            font-family: inherit;
        }

        .header-account {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-account-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid #dce4e6;
            border-radius: 10px;
            background: #fff;
            color: #466b76;
            text-decoration: none;
            font-size: 10px;
            font-weight: 800;
        }

        .header-account-logout {
            color: #9c6963;
            text-decoration: none;
            font-size: 10px;
            font-weight: 800;
        }

        .header-account-name {
            color: #445860;
            font-size: 10px;
            font-weight: 800;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .header-user-state {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #728289;
            font-size: 10px;
            font-weight: 700;
        }

        .header-user-state.admin {
            color: #496d78;
        }

        .header-user-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #5e9a87;
        }

        /* موبایل */

        @media (max-width: 760px) {

            .header-center {
                display: none;
            }

            .header-account-name,
            .header-account-logout,
            .header-account-btn {
                display: none;
            }

            .header-account .auth-trigger {
                min-height: 36px;
                padding: 0 11px;
                font-size: 10px;
            }

            .auth-modal-overlay {
                align-items: flex-end;
                padding: 0;
            }

            .auth-modal {
                width: 100%;
                border-radius: 20px 20px 0 0;
                max-height: 90svh;
            }

            .auth-modal .auth-pane {
                max-height: calc(90svh - 110px);
                padding: 18px 18px 22px;
            }

            .auth-tabs {
                margin: 14px 14px 0;
            }
        }
    </style>
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
                <a href="/account/orders" class="r6-drawer-link">سفارش‌های من</a>
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

    <!-- ══════════ Modal ورود / ثبت‌نام ══════════ -->

    <div
        class="auth-modal-overlay"
        id="authModalOverlay">

        <div
            class="auth-modal"
            role="dialog"
            aria-modal="true">

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

                    <h3 class="auth-pane-title">
                        ورود به حساب
                    </h3>

                    <p class="auth-pane-note">
                        برای ادامه سفارش وارد حساب خود شوید.
                    </p>

                </div>

                <div
                    class="form-error"
                    id="loginError"></div>

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
                    class="r-btn r-btn-primary"
                    id="loginBtn">
                    ورود
                </button>

            </form>

            <!-- ثبت‌نام -->

            <form
                class="auth-pane"
                id="pane-register"
                method="post"
                novalidate>

                <div class="auth-pane-top">

                    <span class="auth-pane-kicker">
                        شروع همکاری
                    </span>

                    <h3 class="auth-pane-title">
                        ایجاد حساب
                    </h3>

                    <p class="auth-pane-note">
                        اطلاعات خود را وارد کنید تا حساب شما ساخته شود.
                    </p>

                </div>

                <div
                    class="form-error"
                    id="registerError"></div>

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
                        autocomplete="organization">

                </div>

                <div class="form-field">

                    <label>
                        شماره موبایل
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
                    class="r-btn r-btn-primary"
                    id="registerBtn">
                    ایجاد حساب
                </button>

            </form>

        </div>

    </div>

    <script src="/assets/js/app.js?v=23"></script>

</body>

</html>