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

/* ══════════ وضعیت ورود ══════════ */

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

/* ══════════ ورود ادمین ══════════ */

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

    $ok =
        $user &&
        !empty($user['password_hash']) &&
        password_verify(
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

    $_SESSION['admin_id'] =
        (int)$user['id'];

    $_SESSION['admin_name'] =
        $user['full_name'] ?? 'مدیر';

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

/* ══════════ ثبت‌نام مشتری ══════════ */

function register_customer(
    string $fullName,
    string $companyName,
    string $mobile,
    string $password
): array {

    $pdo = db();

    $fullName =
        trim($fullName);

    $companyName =
        trim($companyName);

    $mobile =
        trim($mobile);

    if ($fullName === '') {
        return [
            'ok' => false,
            'error' =>
            'نام و نام خانوادگی را وارد کنید.'
        ];
    }

    if (!preg_match('/^09\d{9}$/', $mobile)) {
        return [
            'ok' => false,
            'error' =>
            'شماره موبایل صحیح نیست.'
        ];
    }

    if (strlen($password) < 8) {
        return [
            'ok' => false,
            'error' =>
            'رمز عبور باید حداقل ۸ کاراکتر باشد.'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id, role, is_active
        FROM users
        WHERE mobile = ?
        LIMIT 1
    ");

    $stmt->execute([$mobile]);

    $existing =
        $stmt->fetch();

    if ($existing) {

        return [
            'ok' => false,
            'error' =>
            'این شماره موبایل قبلاً ثبت شده است.'
        ];
    }

    $passwordHash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );

    /*
     * با توجه به تفاوت احتمالی نسخه‌های
     * schema، ستون‌ها را قبل از INSERT بررسی می‌کنیم.
     */

    $columnsStmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'users'
    ");

    $columns =
        $columnsStmt
        ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN)
        : [];

    $hasCompanyName =
        in_array(
            'company_name',
            $columns,
            true
        );

    $hasCreatedAt =
        in_array(
            'created_at',
            $columns,
            true
        );

    if (
        $hasCompanyName &&
        $hasCreatedAt
    ) {

        $stmt = $pdo->prepare("
            INSERT INTO users
            (
                full_name,
                company_name,
                mobile,
                password_hash,
                role,
                is_active,
                created_at
            )
            VALUES
            (?, ?, ?, ?, 'customer', 1, NOW())
        ");

        $stmt->execute([
            $fullName,
            $companyName,
            $mobile,
            $passwordHash
        ]);
    } elseif ($hasCompanyName) {

        $stmt = $pdo->prepare("
            INSERT INTO users
            (
                full_name,
                company_name,
                mobile,
                password_hash,
                role,
                is_active
            )
            VALUES
            (?, ?, ?, ?, 'customer', 1)
        ");

        $stmt->execute([
            $fullName,
            $companyName,
            $mobile,
            $passwordHash
        ]);
    } elseif ($hasCreatedAt) {

        $stmt = $pdo->prepare("
            INSERT INTO users
            (
                full_name,
                mobile,
                password_hash,
                role,
                is_active,
                created_at
            )
            VALUES
            (?, ?, ?, 'customer', 1, NOW())
        ");

        $stmt->execute([
            $fullName,
            $mobile,
            $passwordHash
        ]);
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO users
            (
                full_name,
                mobile,
                password_hash,
                role,
                is_active
            )
            VALUES
            (?, ?, ?, 'customer', 1)
        ");

        $stmt->execute([
            $fullName,
            $mobile,
            $passwordHash
        ]);
    }

    $customerId =
        (int)$pdo->lastInsertId();

    /* ورود خودکار */

    session_regenerate_id(true);

    $_SESSION['customer_id'] =
        $customerId;

    $_SESSION['customer_name'] =
        $fullName;

    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name']
    );

    return [
        'ok'   => true,
        'id'   => $customerId,
        'name' => $fullName
    ];
}

/* ══════════ گارد ادمین ══════════ */

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header(
            'Location: /admin/login'
        );
        exit;
    }
}

function admin_name(): string
{
    return $_SESSION['admin_name']
        ?? 'مدیر';
}

/* ══════════ خروج کامل ══════════ */

function logout_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params =
            session_get_cookie_params();

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
/* ─── ثبت‌نام مشتری + شرکت در انتظار تأیید ─── */
function register_customer(string $fullName, string $companyName, string $mobile, string $password): array
{
    $pdo = db();

    // موبایل تکراری؟
    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'این شماره قبلاً ثبت شده است. وارد شوید.'];
    }

    $pdo->beginTransaction();

    try {
        // ۱. کاربر — شرکت در انتظار تأیید ادمین است
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, company_name, mobile, password_hash, role, is_active)
            VALUES (?, ?, ?, ?, 'customer', 1)
        ");
        $stmt->execute([$fullName, $companyName ?: null, $mobile, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        // ۲. شرکت در وضعیت pending
        $stmt = $pdo->prepare("INSERT INTO companies (name, status) VALUES (?, 'pending')");
        $stmt->execute([$companyName ?: 'مشتری بدون نام شرکت']);
        $companyId = (int)$pdo->lastInsertId();

        // ۳. رابط کاربر-شرکت با نقش owner
        $stmt = $pdo->prepare("INSERT INTO company_users (company_id, user_id, role) VALUES (?,?, 'owner')");
        $stmt->execute([$companyId, $userId]);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip) VALUES (?, 'customer.register', 'user', ?, ?)")
            ->execute([$userId, $userId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        $pdo->commit();

        return ['ok' => true, 'id' => $userId, 'name' => $fullName];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;   // به catch بالای api/register.php برمی‌گردد
    }
}
