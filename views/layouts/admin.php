<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'پنل مدیریت' ?> | جوراب رابطی</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=4">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body class="admin-body">

    <!-- ▂▂▂ سایدبار ▂▂▂ -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-side-brand">
            <span class="brand-icon" style="width:36px;height:36px;">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64M21 21V14.3m0 0L19.3 5.7a1.5 1.5 0 00-1.47-1.2H6.17a1.5 1.5 0 00-1.47 1.2L3 14.3m18 0H3m4.5 6.75V16.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v4.5" />
                </svg>
            </span>
            پنل مدیریت
        </div>
        <nav class="admin-nav">
            <a href="/admin" class="admin-nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">📊 داشبورد</a>
            <a href="/admin/custom-orders" class="admin-nav-link <?= ($activeMenu ?? '') === 'custom' ? 'active' : '' ?>">🏷️ سفارش‌های اختصاصی <span class="side-badge"><?= $countCustom ?? 0 ?></span></a>
            <a href="/admin/messages" class="admin-nav-link <?= ($activeMenu ?? '') === 'messages' ? 'active' : '' ?>">📨 پیام‌ها <span class="side-badge"><?= $countMessages ?? 0 ?></span></a>
            <a href="/admin/products" class="admin-nav-link <?= ($activeMenu ?? '') === 'products' ? 'active' : '' ?>">🧦 محصولات</a>
            <a href="/admin/categories" class="admin-nav-link <?= ($activeMenu ?? '') === 'categories' ? 'active' : '' ?>">📁 دسته‌بندی‌ها</a>
            <a href="/admin/banners" class="admin-nav-link <?= ($activeMenu ?? '') === 'banners' ? 'active' : '' ?>">🖼️ بنر صفحه اصلی</a>
            <a href="/admin/customers" class="admin-nav-link <?= ($activeMenu ?? '') === 'customers' ? 'active' : '' ?>">👥 مشتریان</a>
            <hr class="admin-sep">
            <a href="/" class="admin-nav-link">🏠 مشاهده سایت</a>
            <a href="/admin/logout" class="admin-nav-link danger">🚪 خروج</a>
        </nav>
    </aside>

    <div class="admin-overlay" id="adminOverlay"></div>

    <!-- ▂▂▂ محتوا ▂▂▂ -->
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="adminBurger">☰</button>
            <span class="admin-topbar-title"><?= $pageTitle ?? 'پنل مدیریت' ?></span>
            <span class="admin-user">مدیر سیستم</span>
        </header>

        <div class="admin-content">
            <?= $content ?? '' ?>
        </div>
    </div>

    <script src="/assets/js/admin.js"></script>
</body>

</html>