<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

if (!isset($countCustom)) {
    $countCustom = (int)db()->query("
        SELECT COUNT(*)
        FROM custom_order_requests
        WHERE status = 'new'
    ")->fetchColumn();
}

if (!isset($countMessages)) {
    $countMessages = (int)db()->query("
        SELECT COUNT(*)
        FROM contact_messages
        WHERE status = 'new'
    ")->fetchColumn();
}
if (!isset($countPendingCustomers)) {
    $countPendingCustomers = (int)db()->query("
        SELECT COUNT(*)
        FROM companies
        WHERE status = 'pending'
    ")->fetchColumn();
}
if (!isset($countOrders)) {
    $countOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='pending_review'")->fetchColumn();
}

$adminPageTitle =
    $pageTitle ?? 'پنل مدیریت';

$menu = [
    [
        'key'   => 'dashboard',
        'label' => 'داشبورد',
        'href'  => '/admin',
        'icon'  => 'dashboard'
    ],
    [
        'key'   => 'payments',
        'label' => 'تأیید پرداخت‌ها',
        'href'  => '/admin/payments',
        'icon'  => 'message',
    ],
    [
        'key'   => 'custom',
        'label' => 'سفارش‌های اختصاصی',
        'href'  => '/admin/custom-orders',
        'icon'  => 'custom',
        'badge' => $countCustom
    ],
    [
        'key'   => 'messages',
        'label' => 'پیام‌ها',
        'href'  => '/admin/messages',
        'icon'  => 'message',
        'badge' => $countMessages
    ],
    [
        'key'   => 'products',
        'label' => 'محصولات',
        'href'  => '/admin/products',
        'icon'  => 'products'
    ],
    [
        'key'   => 'categories',
        'label' => 'دسته‌بندی‌ها',
        'href'  => '/admin/categories',
        'icon'  => 'categories'
    ],
    [
        'key'   => 'banners',
        'label' => 'بنرهای صفحه اصلی',
        'href'  => '/admin/banners',
        'icon'  => 'banner'
    ],
    [
        'key'   => 'customers',
        'label' => 'مشتریان',
        'href'  => '/admin/customers',
        'icon'  => 'customers',
        'badge' => $countPendingCustomers
    ],
    [
        'key'   => 'orders',
        'label' => 'سفارش‌ها',
        'href'  => '/admin/orders',
        'icon'  => 'products',
        'badge' => ($countOrders ?? 0)
    ],

];

function admin_icon(string $name): string
{
    $icons = [

        'dashboard' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
            </svg>
        ',

        'custom' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7.5A2.5 2.5 0 016.5 5h11A2.5 2.5 0 0120 7.5v9a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 16.5z"/>
                <path d="M8 5v-1M16 5v-1M8 10h8M8 14h5"/>
            </svg>
        ',

        'message' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v8a2.5 2.5 0 01-2.5 2.5H11l-4.5 4v-4H6.5A2.5 2.5 0 014 13.5z"/>
                <path d="M8 8h8M8 11.5h5"/>
            </svg>
        ',

        'products' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7.5h16M5 7.5v11A1.5 1.5 0 006.5 20h11a1.5 1.5 0 001.5-1.5v-11"/>
                <path d="M8 4h8l1 3.5H7z"/>
            </svg>
        ',

        'categories' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5.5A1.5 1.5 0 015.5 4h5A1.5 1.5 0 0112 5.5v5a1.5 1.5 0 01-1.5 1.5h-5A1.5 1.5 0 014 10.5z"/>
                <path d="M14 13.5a1.5 1.5 0 011.5-1.5h3a1.5 1.5 0 011.5 1.5v5a1.5 1.5 0 01-1.5 1.5h-3a1.5 1.5 0 01-1.5-1.5z"/>
            </svg>
        ',

        'banner' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3.5" y="4" width="17" height="16" rx="2"/>
                <circle cx="8.5" cy="9" r="1.5"/>
                <path d="M5.5 17l4.5-4 3 2.5 2-2 3.5 3.5"/>
            </svg>
        ',

        'customers' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="9" cy="8" r="3"/>
                <path d="M3.5 19a5.5 5.5 0 0111 0"/>
                <path d="M15.5 5.5a3 3 0 010 5.8M17 14a4.5 4.5 0 013.5 4.4"/>
            </svg>
        ',

    ];

    return $icons[$name] ?? '';
}
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
            $adminPageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | رابطی
    </title>

    <link
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="/assets/css/app.css?v=22">

    <link
        rel="stylesheet"
        href="/assets/css/admin.css?v=5">

</head>

<body class="admin-body">

    <aside
        class="admin-sidebar"
        id="adminSidebar">

        <div class="admin-brand">

            <a
                href="/admin"
                class="admin-brand-main">
                <span class="admin-brand-mark">
                    ر
                </span>

                <span>
                    <strong>رابطی</strong>
                    <small>پنل مدیریت</small>
                </span>
            </a>

        </div>

        <div class="admin-nav-caption">
            مدیریت فروشگاه
        </div>

        <nav class="admin-nav">

            <?php foreach ($menu as $item): ?>

                <?php
                $isActive =
                    ($activeMenu ?? '') === $item['key'];
                ?>

                <a
                    href="<?= $item['href'] ?>"
                    class="admin-nav-link <?= $isActive ? 'active' : '' ?>">

                    <span class="admin-nav-icon">
                        <?= admin_icon($item['icon']) ?>
                    </span>

                    <span class="admin-nav-text">
                        <?= htmlspecialchars(
                            $item['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <?php if (isset($item['badge'])): ?>

                        <span class="side-badge">
                            <?= (int)$item['badge'] ?>
                        </span>

                    <?php endif; ?>

                </a>

            <?php endforeach; ?>

        </nav>

        <div class="admin-sidebar-bottom">

            <a
                href="/"
                class="admin-nav-link secondary">
                <span class="admin-nav-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3.5 10.5L12 4l8.5 6.5" />
                        <path d="M5.5 9.5V20h13V9.5M9 20v-6h6v6" />
                    </svg>
                </span>

                <span class="admin-nav-text">
                    مشاهده سایت
                </span>
            </a>

            <a
                href="/admin/logout"
                class="admin-nav-link danger">
                <span class="admin-nav-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 4H5.5A1.5 1.5 0 004 5.5v13A1.5 1.5 0 005.5 20H10" />
                        <path d="M14 8l4 4-4 4M18 12H9" />
                    </svg>
                </span>

                <span class="admin-nav-text">
                    خروج
                </span>
            </a>

        </div>

    </aside>

    <div
        class="admin-overlay"
        id="adminOverlay"></div>

    <div class="admin-main">

        <header class="admin-topbar">

            <button
                type="button"
                class="admin-burger"
                id="adminBurger"
                aria-label="باز کردن منو">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="admin-topbar-heading">

                <span class="admin-topbar-eyebrow">
                    رابطی
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $adminPageTitle,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

            <div class="admin-user">

                <span class="admin-user-avatar">
                    <?= htmlspecialchars(
                        mb_substr(
                            admin_name(),
                            0,
                            1,
                            'UTF-8'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <span class="admin-user-info">

                    <small>
                        مدیر فروشگاه
                    </small>

                    <strong>
                        <?= htmlspecialchars(
                            admin_name(),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </span>

            </div>

        </header>

        <main class="admin-content">
            <?= $content ?? '' ?>
        </main>

    </div>

    <script src="/assets/js/admin.js?v=3"></script>

</body>

</html>