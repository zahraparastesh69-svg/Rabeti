<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,       // جاوااسکریپت به کوکی دسترسی ندارد
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ─── ورود ادمین ─── */
function attempt_admin_login(string $mobile, string $password): bool
{
    global $pdo;
    $pdo = $pdo ?? db();

    // Rate limit: حداکثر ۵ تلاش ناموفق در ۱۵ دقیقه
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
                           WHERE ip = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE");
    $stmt->execute([$_SERVER['REMOTE_ADDR']]);
    if ((int)$stmt->fetchColumn() >= 5) return false;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();

    $ok = $user && password_verify($password, $user['password_hash']);

    $pdo->prepare("INSERT INTO login_attempts (mobile, ip, success) VALUES (?,?,?)")
        ->execute([$mobile, $_SERVER['REMOTE_ADDR'], $ok ? 1 : 0]);

    if (!$ok) return false;

    session_regenerate_id(true);   // جلوی Session Fixation
    $_SESSION['admin_id']    = $user['id'];
    $_SESSION['admin_name']  = $user['full_name'];

    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip) VALUES (?, 'admin.login', ?)")
        ->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

    return true;
}

/* ─── گارد: هر صفحه ادمین اول این را صدا می‌زند ─── */
function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login');
        exit;
    }
}

function admin_name(): string
{
    return $_SESSION['admin_name'] ?? 'مدیر';
}
