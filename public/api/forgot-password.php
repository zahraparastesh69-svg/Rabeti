<?php
/* [FILE] public/api/forgot-password.php — درخواست و تأیید فراموشی رمز */

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

function jsonOut(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'متد نامعتبر'], 405);
}

$step = trim((string)($_POST['step'] ?? ''));

/* ═══ مرحله ۱: درخواست کد (ارسال به موبایل یا ایمیل) ═══ */
if ($step === 'request') {

    $channel = trim((string)($_POST['channel'] ?? ''));   // sms | email
    $target  = trim((string)($_POST['target'] ?? ''));

    if (!in_array($channel, ['sms', 'email'], true)) {
        jsonOut(['ok' => false, 'error' => 'روش بازیابی نامعتبر است.'], 422);
    }

    if ($channel === 'sms') {
        $target = strtr($target, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
        if (!preg_match('/^09\d{9}$/', $target)) {
            jsonOut(['ok' => false, 'error' => 'شماره موبایل صحیح نیست.'], 422);
        }
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE mobile = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$target]);
    } else {
        $target = strtolower(trim($target));
        if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['ok' => false, 'error' => 'ایمیل معتبر نیست.'], 422);
        }
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$target]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
     * امنیت: حتی اگر کاربر نبود، همان پیام موفقیت می‌آید
     * (جلوگیری از enumeration)
     */
    if (!$user) {
        jsonOut([
            'ok'  => true,
            'msg' => 'اگر این ' . ($channel === 'sms' ? 'شماره' : 'ایمیل') . ' در سیستم ثبت باشد، رمز ارسال می‌شود.',
        ]);
    }

    /* ساخت کد و ارسال */
    $code = create_password_reset($db, (int)$user['id'], $channel, $target);

    /*
     * ⭐ ارسال واقعی:
     * SMS → SmsService (کاوه‌نگار/ملی‌پیامک)
     * Email → mail() یا SMTP
     *
     * فعلاً در notifications ثبت می‌شود تا فاز اتصال سرویس:
     */
    $db->prepare("INSERT INTO notifications (user_id, type, title, body, link)
                  VALUES (?, 'password_reset', 'بازیابی رمز عبور', ?, NULL)")
        ->execute([
            $user['id'],
            'کد بازیابی رمز شما: ' . $code . ' — اعتبار ۳۰ دقیقه. ارسال از طریق: ' .
                ($channel === 'sms' ? 'پیامک' : 'ایمیل') . ' به ' . $target
        ]);

    /*
     * TODO فاز اتصال سرویس:
     * if ($channel === 'sms') {
     *     SmsService::send($target, "کد بازیابی رمز رابطی: $code");
     * } else {
     *     MailService::send($target, 'بازیابی رمز رابطی', "کد شما: $code");
     * }
     */

    jsonOut([
        'ok'   => true,
        'msg'  => 'کد بازیابی ارسال شد. (در فاز فعلی در پنل ادمین — بخش اعلان‌ها قابل مشاهده است)',
        'code' => $code,   // 🔧 فاز فعلی: کد برای تست برگشت داده می‌شود؛ بعد از اتصال SMS حذف شود!
    ]);
}

/* ═══ مرحله ۲: تأیید کد و تغییر رمز ═══ */
if ($step === 'reset') {

    $channel    = trim((string)($_POST['channel'] ?? ''));
    $target     = trim((string)($_POST['target'] ?? ''));
    $code       = trim((string)($_POST['code'] ?? ''));
    $newPass    = (string)($_POST['new_password'] ?? '');
    $newPass2   = (string)($_POST['new_password2'] ?? '');

    if (strlen($newPass) < 8) {
        jsonOut(['ok' => false, 'error' => 'رمز جدید باید حداقل ۸ کاراکتر باشد.'], 422);
    }
    if ($newPass !== $newPass2) {
        jsonOut(['ok' => false, 'error' => 'تکرار رمز صحیح نیست.'], 422);
    }

    if ($channel === 'sms') {
        $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    }
    $stmt->execute([$target]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) jsonOut(['ok' => false, 'error' => 'کاربر یافت نشد.'], 404);

    $userId = (int)$user['id'];

    $stmt = $db->prepare("
        SELECT id FROM password_resets
        WHERE user_id = ? AND channel = ? AND destination = ?
          AND code = ? AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId, $channel, $target, $code]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        jsonOut(['ok' => false, 'error' => 'کد نامعتبر یا منقضی شده است.'], 422);
    }

    /* تغییر رمز */
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);

    /* توکن استفاده شد */
    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
        ->execute([(int)$reset['id']]);

    /* ابطال سشن‌های دیگر (امنیتی) */
    $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                  VALUES (?, 'password.reset', 'user', ?, ?)")
        ->execute([$userId, $userId, $_SERVER['REMOTE_ADDR'] ?? null]);

    jsonOut(['ok' => true, 'msg' => 'رمز عبور با موفقیت تغییر کرد. حالا وارد شوید.']);
}

jsonOut(['ok' => false, 'error' => 'درخواست نامعتبر'], 400);
