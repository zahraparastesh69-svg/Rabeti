<?php
// نمونه متغیرهای فرضی برای لاگین (در سیستم خودتان از Session یا Auth استفاده کنید)
$isLoggedIn = $isLoggedIn ?? false;
$userName   = $userName ?? 'کاربر گرامی';
$cartCount  = $cartCount ?? 0;
$notifCount = $notifCount ?? 0;
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

    <!-- هدر سایت -->
    <header class="header">
        <div class="header-inner">
            <!-- سمت راست: برند و دکمه منو -->
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

            <!-- سمت چپ: اعلان، سبد خرید، حساب کاربری -->
            <div class="header-left">
                <!-- اعلان‌ها -->
                <a href="/notifications" class="header-action-btn" aria-label="اعلان‌ها" title="اعلان‌ها">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <?php if ($notifCount > 0): ?>
                        <span class="badge"><?= $notifCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- سبد خرید -->
                <a href="/cart" class="header-action-btn" aria-label="سبد خرید" title="سبد خرید">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- احراز هویت -->
                <div class="user-auth-section">
                    <?php if ($isLoggedIn): ?>
                        <a href="/profile" class="user-profile-chip">
                            <span class="avatar-icon">👤</span>
                            <span class="user-name"><?= htmlspecialchars($userName) ?></span>
                        </a>
                    <?php else: ?>
                        <div class="auth-links">
                            <a href="/login" class="btn-link">ورود</a>
                            <span class="divider">/</span>
                            <a href="/register" class="btn-link">ثبت‌نام</a>
                        </div>
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
        </nav>
    </aside>

    <!-- محتوای صفحات -->
    <main class="container">
        <!-- ساختار جدید هیرو بنر فیروزه‌ای -->
        <section class="hero-container">
            <div class="hero-banner">
                <!-- تصویر یا آیکون مرکزی بنر -->
                <div class="banner-inner-icon">🧦</div>
            </div>
            
            <!-- دکمه خارج از بنر با فاصله مناسب -->
            <div class="hero-cta-wrap">
                <a href="/products" class="btn-hero">
                    <span>مشاهده محصولات</span>
                    <span class="arrow">←</span>
                </a>
            </div>
        </section>

        <?= $content ?>
    </main>

    <!-- بخش زنجیره مزایا و خدمات سه‌بعدی (قبل از فوتر) -->
    <section class="container features-3d-section">
        <div class="features-grid">
            <div class="feature-card-3d">
                <div class="card-glow"></div>
                <div class="feature-icon">⏱️</div>
                <h3>ثبت سفارش ۲۴ ساعته</h3>
                <p>سامانه اتوماتیک دریافت سفارش عمده در تمامی ساعات شبانه‌روز</p>
            </div>
            <div class="feature-card-3d">
                <div class="card-glow"></div>
                <div class="feature-icon">🏷️</div>
                <h3>تولید با طرح و برند شما</h3>
                <p>بافت سفارشی و بسته‌بندی با لیبل و برند اختصاصی شما</p>
            </div>
            <div class="feature-card-3d">
                <div class="card-glow"></div>
                <div class="feature-icon">🚀</div>
                <h3>تحویل فوری به سراسر کشور</h3>
                <p>ارسال سریع بار از کارخانه به باربری و تحویل در کوتاه‌ترین زمان</p>
            </div>
            <div class="feature-card-3d">
                <div class="card-glow"></div>
                <div class="feature-icon">✨</div>
                <h3>تضمین کیفیت و قیمت</h3>
                <p>استفاده از نخ و کش درجه یک با تضمین مستقیم کارخانه</p>
            </div>
        </div>
    </section>

    <!-- فوتر -->
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
</body>
</html>
