<?php
// auth.php — نسخه نهایی v3

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,       // لوکال http؛ روی HTTPS → true
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/* ═══════════════════════════════════════════════════════════════
   وضعیت ورود
═══════════════════════════════════════════════════════════════ */

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


/* ═══════════════════════════════════════════════════════════════
   CSRF
═══════════════════════════════════════════════════════════════ */

/**
 * ساخت یا دریافت توکن CSRF فعلی نشست.
 */
function csrf_token(): string
{
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || strlen($_SESSION['csrf_token']) < 32
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * اعتبارسنجی توکن CSRF ارسال‌شده.
 *
 * در صورت نامعتبر بودن، درخواست متوقف می‌شود.
 */
function verify_csrf(?string $token = null): void
{
    $token = $token
        ?? ($_POST['csrf_token'] ?? null);

    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if (
        !is_string($token)
        || $token === ''
        || !is_string($sessionToken)
        || $sessionToken === ''
        || !hash_equals($sessionToken, $token)
    ) {
        http_response_code(419);

        exit('درخواست نامعتبر یا منقضی شده است. صفحه را دوباره بارگذاری کنید.');
    }
}


/* ═══════════════════════════════════════════════════════════════
   ورود ادمین
═══════════════════════════════════════════════════════════════ */

function attempt_admin_login(string $mobile, string $password): bool
{
    $pdo = db();
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limit: حداکثر ۵ تلاش ناموفق در ۱۵ دقیقه
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

    $ok = $user
        && !empty($user['password_hash'])
        && password_verify($password, $user['password_hash']);

    $pdo->prepare("
        INSERT INTO login_attempts (mobile, ip, success)
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
    $_SESSION['admin_name'] = $user['full_name'] ?? 'مدیر';

    unset(
        $_SESSION['customer_id'],
        $_SESSION['customer_name']
    );

    // توکن CSRF قبلی بعد از تغییر هویت نباید ادامه پیدا کند.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
        VALUES
            (?, 'admin.login', ?)
    ")->execute([
        $user['id'],
        $ip
    ]);

    return true;
}


/* ═══════════════════════════════════════════════════════════════
   ورود مشتری — API
═══════════════════════════════════════════════════════════════ */

function attempt_customer_login(
    string $mobile,
    string $password
): array {

    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            password_hash,
            is_active
        FROM users
        WHERE mobile = ?
          AND role = 'customer'
        LIMIT 1
    ");

    $stmt->execute([$mobile]);

    $user = $stmt->fetch();

    if (
        !$user
        || !password_verify(
            $password,
            $user['password_hash'] ?? ''
        )
    ) {
        return [
            'ok' => false,
            'error' => 'شماره موبایل یا رمز عبور نادرست است.'
        ];
    }

    if (!$user['is_active']) {
        return [
            'ok' => false,
            'error' => 'حساب شما غیرفعال است. با پشتیبانی تماس بگیرید.'
        ];
    }

    session_regenerate_id(true);

    $_SESSION['customer_id']   = (int)$user['id'];
    $_SESSION['customer_name'] = $user['full_name'];

    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name']
    );

    // توکن CSRF جدید برای نشست جدید
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $pdo->prepare("
        UPDATE users
        SET last_login_at = NOW()
        WHERE id = ?
    ")->execute([
        $user['id']
    ]);

    return [
        'ok'   => true,
        'name' => $user['full_name']
    ];
}


/* ═══════════════════════════════════════════════════════════════
   ثبت‌نام مشتری + شرکت در انتظار تأیید
═══════════════════════════════════════════════════════════════ */

function register_customer(
    string $fullName,
    string $companyName,
    string $mobile,
    string $password
): array {

    $pdo = db();

    $fullName    = trim($fullName);
    $companyName = trim($companyName);
    $mobile      = trim($mobile);

    if ($fullName === '') {
        return [
            'ok' => false,
            'error' => 'نام و نام خانوادگی را وارد کنید.'
        ];
    }

    if (!preg_match('/^09\d{9}$/', $mobile)) {
        return [
            'ok' => false,
            'error' => 'شماره موبایل صحیح نیست.'
        ];
    }

    if (strlen($password) < 8) {
        return [
            'ok' => false,
            'error' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.'
        ];
    }

    // موبایل تکراری
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE mobile = ?
        LIMIT 1
    ");

    $stmt->execute([$mobile]);

    if ($stmt->fetch()) {
        return [
            'ok' => false,
            'error' => 'این شماره موبایل قبلاً ثبت شده است.'
        ];
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $pdo->beginTransaction();

    try {

        // ۱. کاربر مشتری
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
            $companyName !== '' ? $companyName : null,
            $mobile,
            $passwordHash
        ]);

        $customerId = (int)$pdo->lastInsertId();

        // ۲. شرکت در وضعیت pending
        $stmt = $pdo->prepare("
            INSERT INTO companies
                (name, status)
            VALUES
                (?, 'pending')
        ");

        $stmt->execute([
            $companyName !== ''
                ? $companyName
                : 'مشتری بدون نام شرکت'
        ]);

        $companyId = (int)$pdo->lastInsertId();

        // ۳. ارتباط کاربر و شرکت با نقش owner
        $pdo->prepare("
            INSERT INTO company_users
                (company_id, user_id, role)
            VALUES
                (?, ?, 'owner')
        ")->execute([
            $companyId,
            $customerId
        ]);

        // ۴. Audit
        $pdo->prepare("
            INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    entity,
                    entity_id,
                    ip
                )
            VALUES
                (
                    ?,
                    'customer.register',
                    'user',
                    ?,
                    ?
                )
        ")->execute([
            $customerId,
            $customerId,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);

        $pdo->commit();
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // اگر جدول‌های شرکت هنوز آماده نباشند،
        // ثبت کاربر به‌تنهایی انجام می‌شود.
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

        try {

            $stmt->execute([
                $fullName,
                $mobile,
                $passwordHash
            ]);

            $customerId = (int)$pdo->lastInsertId();
        } catch (Throwable $e2) {

            return [
                'ok' => false,
                'error' => 'خطا در ثبت اطلاعات. دوباره تلاش کنید.'
            ];
        }
    }

    return [
        'ok'   => true,
        'id'   => $customerId,
        'name' => $fullName
    ];
}


/* ═══════════════════════════════════════════════════════════════
   گاردها
═══════════════════════════════════════════════════════════════ */

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/login');
        exit;
    }
}

function require_customer(): void
{
    if (!is_customer_logged_in()) {
        header('Location: /');
        exit;
    }
}


/* ═══════════════════════════════════════════════════════════════
   نام کاربر
═══════════════════════════════════════════════════════════════ */

function admin_name(): string
{
    return $_SESSION['admin_name'] ?? 'مدیر';
}

function customer_name(): string
{
    return $_SESSION['customer_name'] ?? 'کاربر';
}


/* ═══════════════════════════════════════════════════════════════
   خروج کامل
═══════════════════════════════════════════════════════════════ */

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
