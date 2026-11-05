<?php
/**
 * checkout.php — redirect به /checkout-address
 *
 * این فایل در نسخه‌های قدیمی جریان checkout بود، ولی اکنون deprecated است.
 * تمام سفارش‌ها باید از جریان چندمرحله‌ای cart → checkout-address →
 * checkout-shipping → checkout-review → payment → order-success عبور کنند.
 *
 * این redirect برای حفظ backward compat URLهای قدیمی نگه داشته شده است.
 * کد legacy در نسخه‌های قبلی این فایل بود ولی در مرحله ۲ گام ۶ حذف شد.
 */

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

/* ثبت در audit_logs برای مشاهدهٔ استفاده از URL قدیمی */
if (is_logged_in()) {
    try {
        $userId = $_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? null;
        db()->prepare("
            INSERT INTO audit_logs (user_id, action, reason, entity, ip)
            VALUES (?, 'legacy.checkout_redirected', 'legacy URL used', 'system', ?)
        ")->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);
    } catch (Throwable $e) {
        // نادیده — redirect در هر صورت انجام شود
    }
}

/* redirect دائم به جریان جدید */
http_response_code(301);
header('Location: /checkout-address');
exit;
