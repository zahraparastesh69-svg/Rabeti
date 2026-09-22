<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/upload.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$countCustom = (int)db()->query("
    SELECT COUNT(*)
    FROM custom_order_requests
    WHERE status = 'new'
")->fetchColumn();

$countMessages = (int)db()->query("
    SELECT COUNT(*)
    FROM contact_messages
    WHERE status = 'new'
")->fetchColumn();

$countProducts = (int)db()->query("
    SELECT COUNT(*)
    FROM products
    WHERE is_active = 1
")->fetchColumn();

$countCats = (int)db()->query("
    SELECT COUNT(*)
    FROM categories
    WHERE is_active = 1
")->fetchColumn();
$countPendingCustomers = (int)db()->query("SELECT COUNT(*) FROM companies WHERE status='pending'")->fetchColumn();

$latestCustom = db()->query("
    SELECT
        id,
        name,
        mobile,
        product_type,
        qty,
        status,
        created_at
    FROM custom_order_requests
    ORDER BY id DESC
    LIMIT 5
")->fetchAll();

$activeMenu = 'dashboard';
$pageTitle  = 'داشبورد';

ob_start();
?>

<div class="admin-dashboard">

    <div class="admin-page-intro">

        <div>
            <span class="admin-kicker">
                نمای کلی
            </span>

            <h1>
                داشبورد
            </h1>

            <p>
                وضعیت فعلی فروشگاه و درخواست‌های اخیر را در یک نگاه ببینید.
            </p>
        </div>

        <a
            href="/"
            class="admin-soft-action">
            مشاهده سایت
        </a>

    </div>

    <div class="stat-cards">

        <div class="stat-card stat-card-accent">

            <div class="stat-card-top">
                <span class="stat-card-label">
                    درخواست اختصاصی جدید
                </span>

                <span class="stat-card-icon">
                    ↗
                </span>
            </div>

            <div class="num">
                <?= $countCustom ?>
            </div>

            <div class="stat-card-note">
                نیازمند بررسی
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-card-top">
                <span class="stat-card-label">
                    پیام‌های جدید
                </span>

                <span class="stat-card-icon">
                    ≋
                </span>
            </div>

            <div class="num">
                <?= $countMessages ?>
            </div>

            <div class="stat-card-note">
                منتظر پاسخ
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-card-top">
                <span class="stat-card-label">
                    محصولات فعال
                </span>

                <span class="stat-card-icon">
                    □
                </span>
            </div>

            <div class="num">
                <?= $countProducts ?>
            </div>

            <div class="stat-card-note">
                در سایت قابل فروش
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-card-top">
                <span class="stat-card-label">
                    دسته‌بندی‌ها
                </span>

                <span class="stat-card-icon">
                    ◇
                </span>
            </div>

            <div class="num">
                <?= $countCats ?>
            </div>

            <div class="stat-card-note">
                دسته فعال
            </div>

        </div>

    </div>

    <div class="admin-section-head">

        <div>

            <span class="admin-kicker">
                درخواست‌ها
            </span>

            <h2>
                آخرین درخواست‌های تولید اختصاصی
            </h2>

        </div>

        <a
            href="/admin/custom-orders"
            class="admin-section-link">
            مشاهده همه
        </a>

    </div>

    <div class="admin-table-wrap">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>شناسه</th>
                    <th>نام</th>
                    <th>موبایل</th>
                    <th>نوع</th>
                    <th>تعداد</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($latestCustom): ?>

                    <?php foreach ($latestCustom as $r): ?>

                        <?php
                        $statusLabels = [
                            'new'       => 'جدید',
                            'in_review' => 'در بررسی',
                            'quoted'    => 'قیمت داده شد',
                            'converted' => 'تبدیل شد',
                            'rejected'  => 'رد شد',
                        ];

                        $statusLabel =
                            $statusLabels[$r['status']]
                            ?? $r['status'];
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    CR-<?= str_pad(
                                            $r['id'],
                                            5,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $r['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td dir="ltr">
                                <?= htmlspecialchars(
                                    $r['mobile'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $r['product_type'] ?? '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (int)$r['qty']
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="st-badge st-<?= htmlspecialchars(
                                                            $r['status'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>">
                                    <?= htmlspecialchars(
                                        $statusLabel,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $r['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="admin-empty-row">
                            هنوز درخواستی ثبت نشده است.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php
$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/admin.php';
