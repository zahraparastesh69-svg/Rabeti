<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/NotifyService.php';

echo "<pre>";

 $user = db()->query("SELECT id FROM users WHERE mobile = '09127264560' LIMIT 1")->fetch();
echo "User ID: " . ($user['id'] ?? 'NOT FOUND') . "\n\n";

try {
    $code = create_password_reset(db(), (int)$user['id'], 'sms', '09127264560');
    echo "کد ساخته شد: $code\n\n";
} catch (Throwable $e) {
    echo "خطا در ساخت کد: " . $e->getMessage() . "\n";
    exit;
}

try {
    NotifyService::send((int)$user['id'], '09127264560', null, 'تست رابطی', 'کد بازیابی رابطی: ' . $code);
    echo "✅ NotifyService اجرا شد بدون خطا";
} catch (Throwable $e) {
    echo "❌ خطای NotifyService:\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
}