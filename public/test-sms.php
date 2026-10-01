<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/app/services/NotifyService.php';

echo "<pre>";
try {
    NotifyService::send(0, '09127264560', null, 'تست', 'کد تست: 123456');
    echo "SMS OK";
} catch (Throwable $e) {
    echo "خطا: " . $e->getMessage() . "\n";
    echo "فایل: " . $e->getFile() . ":" . $e->getLine();
}
echo "</pre>";
