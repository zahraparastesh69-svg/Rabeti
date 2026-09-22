<?php
// auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/* ============================================================
   وضعیت ورود
============================================================ */

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function is_customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}

function is_logged_in(): bool
{
    return is_admin_logged_in() || is_customer_logged_in();
}

/* ============================================================
   ورود ادمین
============================================================ */

function attempt_admin_login(string $mobile, string $password): bool
{
    $pdo = db();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM login_attempts
        WHERE ip = ?
          AND success = 0
          AND created_at > NOW() - INTERVAL 15 MINUTE
    ");

    $stmt->execute([$ip]);

    if ((int)$stmt->fetchColumn() >= 5) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE mobile = ?
          AND role = 'admin'
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$mobile]);

    $user = $stmt->fetch();

    $ok = $user && password_verify(
        $password,
        $user['password_hash']
    );

    $pdo->prepare("
        INSERT INTO login_attempts
        (mobile, ip, success)
        VALUES (?, ?, ?)
    ")->execute([
        $mobile,
        $ip,
        $ok ? 1 : 0
    ]);

    if (!$ok) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['admin_id']   = (int)$user['id'];
    $_SESSION['admin_name'] = $user['full_name'];

    unset(
        $_SESSION['customer_id'],
        $_SESSION['customer_name']
    );

    $pdo->prepare("
        UPDATE users
        SET last_login_at = NOW()
        WHERE id = ?
    ")->execute([
        $user['id']
    ]);

    $pdo->prepare("
        INSERT INTO audit_logs
        (user_id, action, ip)
        VALUES (?, 'admin.login', ?)
    ")->execute([
        $user['id'],
        $ip
    ]);

    return true;
}

/* ============================================================
   گارد ادمین
============================================================ */

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/login');
        exit;
    }
}

function admin_name(): string
{
    return $_SESSION['admin_name'] ?? 'مدیر';
}

/* ============================================================
   خروج کامل
============================================================ */

function logout_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
