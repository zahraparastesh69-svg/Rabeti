<?php

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

/* علامت‌گذاری به‌عنوان خوانده‌شده */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare("UPDATE contact_messages SET status='read' WHERE id=?")->execute([$id]);
        header('Location: /admin/messages');
        exit;
    }
}

$messages = db()->query("
    SELECT * FROM contact_messages ORDER BY (status='new') DESC, id DESC
")->fetchAll();

$activeMenu = 'messages';
$pageTitle  = 'پیام‌های تماس';
ob_start();
?>

<!-- ═══ هدر صفحه ═══ -->
<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مشتریان و ارتباطات</span>
        <h1>پیام‌های تماس</h1>
        <p>پیام‌های ثبت‌شده از فرم تماس سایت — جدیدها بالای لیست هستند.</p>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>نام / موبایل</th>
                <th>موضوع</th>
                <th>پیام</th>
                <th>تاریخ</th>
                <th>وضعیت</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($messages): foreach ($messages as $m): ?>
                    <tr>
                        <td class="td-muted"><?= $m['id'] ?></td>
                        <td>
                            <b><?= htmlspecialchars($m['name']) ?></b><br>
                            <span dir="ltr" class="td-muted td-nowrap"><?= htmlspecialchars($m['mobile']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($m['subject'] ?? '—') ?></td>
                        <td class="td-muted"><?= htmlspecialchars(mb_substr($m['message'], 0, 100)) ?></td>
                        <td class="td-muted td-nowrap"><?= jdate_fa($m['created_at'] ?? null, true) ?></td>
                        <td><span class="st-badge st-<?= $m['status'] ?>"><?= ['new' => 'جدید', 'read' => 'خوانده شد', 'answered' => 'پاسخ داده شد'][$m['status']] ?></span></td>
                        <td>
                            <?php if ($m['status'] === 'new'): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-orange btn-sm">خوانده شد</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <h2>پیامی وجود ندارد</h2>
                            <p>به محض ثبت پیام جدید از فرم تماس سایت، اینجا نمایش داده می‌شود.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
