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

$adminId = (int)$_SESSION['admin_id'];
$flash    = '';
$flashErr = '';

/* اگر درخواست ساخت پشتیبان آمده، اسکریپت shell را اجرا کن */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = $_POST['do'] ?? '';

    if ($do === 'take_backup') {
        $script = ROOT_PATH . '/scripts/backup.sh';
        if (!is_executable($script) && !is_file($script)) {
            $flashErr = 'اسکریپت scripts/backup.sh موجود نیست.';
        } else {
            $output = [];
            $exit   = 0;
            // اجرای اسکریپت با shell
            $cmd = 'bash ' . escapeshellarg($script) . ' 2>&1';
            exec($cmd, $output, $exit);
            $log = implode("\n", $output);

            // ثبت در audit
            try {
                db()->prepare("
                    INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                    VALUES (?, 'system.backup', 'system', NULL, ?)
                ")->execute([$adminId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
            } catch (Throwable $e) {}

            if ($exit === 0) {
                $flash = 'پشتیبان با موفقیت ساخته شد.';
                if (preg_match('/OK: (.+)$/', $log, $m)) {
                    $flash .= ' فایل: ' . $m[1];
                }
            } else {
                $flashErr = 'خطا در پشتیبان‌گیری: ' . htmlspecialchars($log);
            }
        }
    }

    if ($do === 'restore' && !empty($_POST['filename'])) {
        $filename = basename((string)$_POST['filename']);
        $path = ROOT_PATH . '/scripts/_backups/' . $filename;
        if (!is_file($path)) {
            $flashErr = 'فایل پشتیبان یافت نشد.';
        } else {
            // فقط نام امن
            if (!preg_match('/^[A-Za-z0-9_\-\.]+\.sql\.gz$/', $filename)) {
                $flashErr = 'نام فایل نامعتبر.';
            } else {
                try {
                    db()->prepare("
                        INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                        VALUES (?, 'system.restore', 'system', NULL, ?)
                    ")->execute([$adminId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
                    $flash = 'درخواست بازگردانی ثبت شد. برای اجرای بازگردانی، از SSH این دستور را اجرا کنید:';
                    $flash .= '<br><code>gunzip &lt; ' . htmlspecialchars($path) . ' | mysql -h... -u... -p DBNAME</code>';
                } catch (Throwable $e) {}
            }
        }
    }
}

/* فهرست فایل‌های پشتیبان */
$backupDir = ROOT_PATH . '/scripts/_backups';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql.gz');
    rsort($files);
    foreach ($files as $f) {
        $backups[] = [
            'filename' => basename($f),
            'size'     => filesize($f),
            'mtime'    => filemtime($f),
        ];
    }
}

$activeMenu = 'system-backup';
$pageTitle  = 'پشتیبان‌گیری';

ob_start();
?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مدیریت سیستم</span>
        <h1>پشتیبان‌گیری</h1>
        <p>تهیهٔ نسخهٔ پشتیبان از پایگاه داده پیش از هر تغییر اساسی.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success" style="background:#e8f5ee;color:#1a7f4b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✓ <?= $flash ?>
    </div>
<?php endif; ?>
<?php if ($flashErr): ?>
    <div class="alert alert-error" style="background:#fff0f0;color:#c62828;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✗ <?= $flashErr ?>
    </div>
<?php endif; ?>

<div class="admin-card" style="background:#fff;border:1px solid var(--card-border);border-radius:14px;padding:18px;margin-bottom:20px;">
    <h3 style="font-size:.95rem;margin:0 0 12px;">ساخت پشتیبان جدید</h3>
    <p style="font-size:.82rem;color:#666;margin-bottom:12px;">
        این عملیات اسکریپت <code>scripts/backup.sh</code> را اجرا می‌کند که با
        <code>mysqldump --single-transaction</code> یک پشتیبان gzip از پایگاه داده می‌سازد.
    </p>
    <form method="post" onsubmit="return confirm('پشتیبان‌گیری انجام شود؟ این عملیات چند ثانیه طول می‌کشد.');">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="take_backup">
        <button type="submit" class="btn btn-orange">
            📦 ساخت پشتیبان جدید
        </button>
    </form>
</div>

<div class="admin-card" style="background:#fff;border:1px solid var(--card-border);border-radius:14px;padding:18px;">
    <h3 style="font-size:.95rem;margin:0 0 12px;">پشتیبان‌های موجود (<?= count($backups) ?> فایل)</h3>
    <?php if (empty($backups)): ?>
        <p style="font-size:.82rem;color:#999;">هنوز پشتیبانی ساخته نشده است.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>نام فایل</th>
                    <th>حجم</th>
                    <th>زمان</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td dir="ltr" style="text-align:right;"><?= htmlspecialchars($b['filename']) ?></td>
                        <td><?= number_format(round($b['size'] / 1024)) ?> KB</td>
                        <td class="td-muted td-nowrap"><?= jdate_fa(date('Y-m-d H:i:s', (int)$b['mtime']), true) ?></td>
                        <td>
                            <form method="post" style="display:inline;"
                                onsubmit="return confirm('بازگردانی از این فایل؟ این عملیات روی دیتابیس زنده است — نیاز به تأیید از SSH دارد.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="restore">
                                <input type="hidden" name="filename" value="<?= htmlspecialchars($b['filename']) ?>">
                                <button type="submit" class="btn btn-sm btn-warn">
                                    درخواست بازگردانی
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:.72rem;color:#999;margin-top:8px;">
            ⚠ برای بازگردانی واقعی، دستور SQL از طریق SSH اجرا شود. پشتیبان‌گیری خودکار از طریق cron قابل‌تنظیم است.
        </p>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
