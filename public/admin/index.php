<?php

// ۱. ابتدا دیتابیس را لود کنید
require_once __DIR__ . '/../config/database.php';

// ۲. حالا که دیتابیس لود شده و ROOT_PATH تعریف شده
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$countCustom   = (int)db()->query("SELECT COUNT(*) FROM custom_order_requests WHERE status='new'")->fetchColumn();
$countMessages = (int)db()->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
$countProducts = (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$countCats     = (int)db()->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();

$latestCustom = db()->query("
    SELECT id, name, mobile, product_type, qty, status, created_at
    FROM custom_order_requests ORDER BY id DESC LIMIT 5
")->fetchAll();

$activeMenu = 'dashboard';
$pageTitle  = 'داشبورد';
ob_start();
?>

<div class="stat-cards">
    <div class="stat-card accent">
        <div class="num"><?= $countCustom ?></div>
        <div class="lbl">درخواست اختصاصی جدید</div>
    </div>
    <div class="stat-card accent">
        <div class="num"><?= $countMessages ?></div>
        <div class="lbl">پیام خوانده‌نشده</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $countProducts ?></div>
        <div class="lbl">محصول فعال</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $countCats ?></div>
        <div class="lbl">دسته‌بندی</div>
    </div>
</div>

<h2 class="section-title" style="font-size:1rem;margin-bottom:14px;">آخرین درخواست‌های تولید اختصاصی</h2>
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>نام</th>
                <th>موبایل</th>
                <th>نوع</th>
                <th>تعداد</th>
                <th>وضعیت</th>
                <th>تاریخ</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($latestCustom): foreach ($latestCustom as $r): ?>
                    <tr>
                        <td>CR-<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td dir="ltr"><?= htmlspecialchars($r['mobile']) ?></td>
                        <td><?= htmlspecialchars($r['product_type'] ?? '—') ?></td>
                        <td><?= number_format($r['qty']) ?></td>
                        <td><span class="st-badge st-<?= $r['status'] ?>"><?= [
                                                                                'new' => 'جدید',
                                                                                'in_review' => 'در بررسی',
                                                                                'quoted' => 'قیمت داده شد',
                                                                                'converted' => 'تبدیل شد',
                                                                                'rejected' => 'رد شد'
                                                                            ][$r['status']] ?></span></td>
                        <td><?= $r['created_at'] ?></td>
                    </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--muted);padding:30px;">هنوز درخواستی ثبت نشده</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
