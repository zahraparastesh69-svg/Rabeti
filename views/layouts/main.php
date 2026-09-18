<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'تولیدی جوراب رابطی') ?></title>
    <!-- اتصال درست به app.css -->
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

    <!-- هدر هماهنگ با کلاس‌های app.css -->
    <header class="header">
        <div class="header-inner">
            <!-- گروه راست: برند + دکمه منو، چسبیده به هم -->
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
        </div>
    </header>

    <!-- پرده تاریک‌کننده پس‌زمینه (هماهنگ با app.js) -->
    <div class="overlay" id="overlay"></div>

    <!-- منوی کشویی Drawer (هماهنگ با app.js) -->
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
            <a href="/login" class="drawer-link">👤 ورود / ثبت‌نام</a>
        </nav>
    </aside>

    <!-- محتوای صفحات -->
    <main class="container">
        <?= $content ?>
    </main>

    <!-- فوتر هماهنگ -->
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

    <!-- فراخوانی app.js در انتهای صفحه -->
    <script src="/assets/js/app.js"></script>
</body>

</html>