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

$db       = db();
$adminId  = (int)$_SESSION['admin_id'];
$flash    = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = $_POST['do'] ?? '';

    /* ۱) افزودن کاربر ادمین جدید */
    if ($do === 'create') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $mobile   = trim((string)($_POST['mobile'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (mb_strlen($fullName) < 3) {
            $flashErr = 'نام و نام خانوادگی الزامی است';
        } elseif (!preg_match('/^09\d{9}$/', $mobile)) {
            $flashErr = 'شماره موبایل معتبر نیست';
        } elseif (strlen($password) < 8) {
            $flashErr = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
        } else {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                $stmt->execute([$mobile]);
                if ($stmt->fetch()) {
                    $flashErr = 'این شماره موبایل قبلاً ثبت شده است';
                } else {
                    $db->prepare("
                        INSERT INTO users (full_name, mobile, password_hash, role, is_active)
                        VALUES (?, ?, ?, 'admin', 1)
                    ")->execute([$fullName, $mobile, password_hash($password, PASSWORD_DEFAULT)]);

                    $newId = (int)$db->lastInsertId();
                    $db->prepare("
                        INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                        VALUES (?, 'admin.user.create', 'user', ?, ?)
                    ")->execute([$adminId, $newId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

                    $flash = 'کاربر ادمین با موفقیت افزوده شد.';
                }
            } catch (Throwable $e) {
                $flashErr = $e->getMessage();
            }
        }
    }

    /* ۲) فعال/غیرفعال‌سازی کاربر ادمین */
    if ($do === 'toggle_active') {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId === $adminId) {
            $flashErr = 'نمی‌توانید حساب خودتان را غیرفعال کنید';
        } else {
            $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$targetId]);
            $current = $stmt->fetchColumn();
            if ($current !== false) {
                $newActive = $current ? 0 : 1;
                $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")
                    ->execute([$newActive, $targetId]);

                $db->prepare("
                    INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                    VALUES (?, ?, 'user', ?, ?)
                ")->execute([
                    $adminId,
                    $newActive ? 'admin.user.activate' : 'admin.user.deactivate',
                    $targetId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]);
                $flash = $newActive ? 'کاربر فعال شد.' : 'کاربر غیرفعال شد.';
            }
        }
    }

    /* ۳) تغییر رمز کاربر ادمین (توسط مدیر کل) */
    if ($do === 'reset_password') {
        $targetId  = (int)($_POST['id'] ?? 0);
        $newPass   = (string)($_POST['new_password'] ?? '');
        if (strlen($newPass) < 8) {
            $flashErr = 'رمز جدید باید حداقل ۸ کاراکتر باشد';
        } elseif ($targetId < 1) {
            $flashErr = 'کاربر نامعتبر';
        } else {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'admin'")
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $targetId]);

            $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                VALUES (?, 'admin.user.reset_password', 'user', ?, ?)
            ")->execute([$adminId, $targetId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

            $flash = 'رمز عبور کاربر تغییر کرد.';
        }
    }
}

$admins = $db->query("
    SELECT id, full_name, mobile, is_active, last_login_at, created_at
    FROM users
    WHERE role = 'admin'
    ORDER BY id ASC
")->fetchAll();

$activeMenu = 'system-users';
$pageTitle  = 'کاربران ادمین';

ob_start();
?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مدیریت سیستم</span>
        <h1>کاربران ادمین</h1>
        <p>افزودن، غیرفعال‌سازی و تغییر رمز کاربران ادمین.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success" style="background:#e8f5ee;color:#1a7f4b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✓ <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>
<?php if ($flashErr): ?>
    <div class="alert alert-error" style="background:#fff0f0;color:#c62828;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✗ <?= htmlspecialchars($flashErr) ?>
    </div>
<?php endif; ?>

<!-- فرم افزودن ادمین جدید -->
<div class="admin-card" style="background:#fff;border:1px solid var(--card-border);border-radius:14px;padding:18px;margin-bottom:20px;max-width:600px;">
    <h3 style="margin:0 0 12px;font-size:.92rem;">افزودن کاربر ادمین جدید</h3>
    <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create">

        <div style="grid-column:1/-1;">
            <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">نام و نام خانوادگی:</label>
            <input type="text" name="full_name" required
                style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;">
        </div>

        <div>
            <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">موبایل:</label>
            <input type="text" name="mobile" required pattern="^09\d{9}$" dir="ltr"
                style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;">
        </div>

        <div>
            <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">رمز عبور (حداقل ۸ کاراکتر):</label>
            <input type="password" name="password" required minlength="8"
                style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;">
        </div>

        <div style="grid-column:1/-1;text-align:left;">
            <button type="submit" style="padding:8px 18px;background:var(--teal-700);color:#fff;border:none;border-radius:6px;cursor:pointer;">
                افزودن ادمین
            </button>
        </div>
    </form>
</div>

<!-- فهرست ادمین‌ها -->
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>شناسه</th>
                <th>نام</th>
                <th>موبایل</th>
                <th>آخرین ورود</th>
                <th>وضعیت</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td>#<?= (int)$a['id'] ?></td>
                    <td>
                        <?= htmlspecialchars($a['full_name']) ?>
                        <?php if ((int)$a['id'] === $adminId): ?>
                            <span class="st-badge st-converted" style="margin-right:6px;font-size:.65rem;">شما</span>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr" style="text-align:right;"><?= htmlspecialchars($a['mobile']) ?></td>
                    <td class="td-muted td-nowrap"><?= jdate_fa($a['last_login_at'] ?? null, true) ?></td>
                    <td>
                        <span class="st-badge <?= $a['is_active'] ? 'st-converted' : 'st-rejected' ?>">
                            <?= $a['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int)$a['id'] !== $adminId): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="toggle_active">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit"
                                    style="padding:5px 12px;font-size:.72rem;background:<?= $a['is_active'] ? '#fff0f0;color:#c62828' : '#e8f5ee;color:#1a7f4b' ?>;border:none;border-radius:4px;cursor:pointer;">
                                    <?= $a['is_active'] ? 'غیرفعال' : 'فعال‌سازی' ?>
                                </button>
                            </form>

                            <button type="button" onclick="document.getElementById('reset-<?= (int)$a['id'] ?>').showModal()"
                                style="padding:5px 12px;font-size:.72rem;background:#eef;color:#33475b;border:none;border-radius:4px;cursor:pointer;">
                                تغییر رمز
                            </button>

                            <dialog id="reset-<?= (int)$a['id'] ?>" style="border-radius:10px;border:1px solid #ccc;padding:20px;">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="reset_password">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <h3 style="margin:0 0 12px;">تغییر رمز: <?= htmlspecialchars($a['full_name']) ?></h3>
                                    <input type="password" name="new_password" required minlength="8"
                                        placeholder="رمز جدید (حداقل ۸ کاراکتر)"
                                        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;margin-bottom:12px;">
                                    <div style="text-align:left;">
                                        <button type="button" onclick="document.getElementById('reset-<?= (int)$a['id'] ?>').close()"
                                            style="padding:6px 12px;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">انصراف</button>
                                        <button type="submit" style="padding:6px 12px;background:var(--teal-700);color:#fff;border:none;border-radius:4px;cursor:pointer;">ذخیره</button>
                                    </div>
                                </form>
                            </dialog>
                        <?php else: ?>
                            <span style="color:#999;font-size:.72rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
