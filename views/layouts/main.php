<?php
// ═════════════ اطلاعات کاربر (مطابق Session/Auth خودتان تنظیم کنید) ═════════════
$isLoggedIn  = $isLoggedIn ?? false;
$userName    = $userName ?? 'کاربر گرامی';
$userMobile  = $userMobile ?? '';
$userInitial = mb_substr($userName, 0, 1);

$cartCount  = $cartCount ?? 0;
$notifCount = $notifCount ?? 0;
$msgCount   = $msgCount ?? 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'تولیدی جوراب رابطی') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

    <!-- ═════════════ هدر سایت ═════════════ -->
    <header class="header">
        <div class="header-inner">

            <!-- سمت راست: دکمه منو + برند -->
            <div class="header-right">
                <button class="hamburger" id="menuBtn" type="button" aria-label="منو">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <a href="/" class="brand">
                    <div class="brand-icon">🧦</div>
                    <div class="brand-text">
                        <span>جوراب رابطی</span>
                        <small>تولید و پخش مستقیم</small>
                    </div>
                </a>
            </div>

            <!-- سمت چپ: پیام، اعلان، سبد خرید، کاربر -->
            <div class="header-left">

                <!-- پیام‌ها -->
                <a href="/messages" class="header-action-btn" aria-label="پیام‌ها" title="پیام‌ها">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <?php if ($msgCount > 0): ?>
                        <span class="badge"><?= (int)$msgCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- اعلان‌ها -->
                <a href="/notifications" class="header-action-btn" aria-label="اعلان‌ها" title="اعلان‌ها">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($notifCount > 0): ?>
                        <span class="badge"><?= (int)$notifCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- سبد خرید -->
                <a href="/cart" class="header-action-btn" aria-label="سبد خرید" title="سبد خرید">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="badge"><?= (int)$cartCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- احراز هویت -->
                <div class="user-auth-section">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-menu" id="userMenu">
                            <button class="user-profile-chip" id="userMenuToggle" type="button" aria-expanded="false" aria-haspopup="true">
                                <span class="user-avatar"><?= htmlspecialchars($userInitial) ?></span>
                                <span class="user-chip-name"><?= htmlspecialchars($userName) ?></span>
                                <span class="caret">▾</span>
                            </button>
                            <div class="user-dropdown" id="userDropdown">
                                <div class="user-dropdown-head">
                                    <b><?= htmlspecialchars($userName) ?></b>
                                    <?php if ($userMobile): ?>
                                        <span><?= htmlspecialchars($userMobile) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="/account">👤 حساب کاربری</a>
                                <a href="/orders">📦 پیگیری سفارش</a>
                                <a href="/account/wishlist">❤️ علاقه‌مندی‌ها</a>
                                <a href="/account/addresses">📍 آدرس‌ها</a>
                                <div class="user-dropdown-divider"></div>
                                <a href="/logout" class="logout">🚪 خروج از حساب</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="btn-login-highlight">ورود / ثبت‌نام</a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <!-- پرده تاریک‌کننده منو -->
    <div class="overlay" id="overlay"></div>

    <!-- منوی کشویی Drawer -->
    <aside class="drawer" id="drawer">
        <div class="drawer-head">
            <strong>منوی دسترسی سریع</strong>
            <button class="drawer-close" id="closeBtn" type="button" aria-label="بستن">&times;</button>
        </div>
        <nav class="drawer-nav">
            <a href="/" class="drawer-link">🏠 خانه</a>
            <a href="/products" class="drawer-link">🧦 کاتالوگ محصولات</a>
            <a href="/custom-order" class="drawer-link">🏷️ سفارش تولید اختصاصی</a>
            <a href="/about" class="drawer-link">🏭 درباره ما</a>
            <a href="/contact" class="drawer-link">📞 تماس با ما</a>
            <div class="drawer-divider"></div>
            <?php if ($isLoggedIn): ?>
                <a href="/account" class="drawer-link">👤 حساب کاربری</a>
                <a href="/orders" class="drawer-link">📦 پیگیری سفارش</a>
                <a href="/logout" class="drawer-link">🚪 خروج از حساب</a>
            <?php else: ?>
                <a href="/login" class="drawer-link">👤 ورود / ثبت‌نام</a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- ═════════════ محتوای صفحه ═════════════ -->
    <!-- بنر هیرو و کارت‌های مزایا به index.php منتقل شده‌اند -->
    <main class="container">
        <?= $content ?>
    </main>

    <!-- ═════════════ فوتر ═════════════ -->
    <footer class="footer">
        <div class="footer-inner">
            <div>
                <div class="footer-brand">🧦 جوراب رابطی</div>
                <p class="footer-desc">تولیدکننده تخصصی انواع جوراب با کیفیت تضمینی و قیمت عمده دست اول، ارسال مستقیم به تمام نقاط کشور.</p>
            </div>
            <div>
                <h4>دسترسی سریع</h4>
                <ul>
                    <li><a href="/products">لیست محصولات</a></li>
                    <li><a href="/orders">پیگیری سفارش</a></li>
                    <li><a href="/register">همکاری با تولیدی</a></li>
                </ul>
            </div>
            <div>
                <h4>ارتباط با ما</h4>
                <ul>
                    <li><a href="tel:02100000000">شماره دفتر فروش: تماس بگیرید</a></li>
                    <li><a href="#">پشتیبانی واتساپ و ایتا</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-copy">
            تمامی حقوق برای کارخانه تولیدی جوراب رابطی محفوظ است.
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
    <script>
        (function() {
            // ═══ منوی کشویی موبایل (Drawer) ═══
            var menuBtn = document.getElementById('menuBtn');
            var closeBtn = document.getElementById('closeBtn');
            var drawer = document.getElementById('drawer');
            var overlay = document.getElementById('overlay');

            function openDrawer() {
                if (drawer) drawer.classList.add('open');
                if (overlay) overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeDrawer() {
                if (drawer) drawer.classList.remove('open');
                if (overlay) overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
            if (menuBtn) menuBtn.addEventListener('click', openDrawer);
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
            if (overlay) overlay.addEventListener('click', closeDrawer);

            // ═══ منوی کشویی کاربر ═══
            var userMenu = document.getElementById('userMenu');
            var userToggle = document.getElementById('userMenuToggle');
            var userDropdown = document.getElementById('userDropdown');

            if (userMenu && userToggle && userDropdown) {
                userToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isOpen = userDropdown.classList.toggle('open');
                    userMenu.classList.toggle('open', isOpen);
                    userToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                document.addEventListener('click', function(e) {
                    if (!userMenu.contains(e.target)) {
                        userDropdown.classList.remove('open');
                        userMenu.classList.remove('open');
                        userToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // ═══ Fallback تصویر بنر (در صورت خطا در لود عکس) ═══
            document.querySelectorAll('.banner-img').forEach(function(img) {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    var fb = document.getElementById('bannerFallback');
                    if (fb) fb.classList.add('show');
                });
            });
        })();
    </script>
</body>

</html>