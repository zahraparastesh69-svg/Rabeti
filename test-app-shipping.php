<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/settings.php';
require_once ROOT_PATH . '/app/services/SmsService.php';
require_once ROOT_PATH . '/app/services/OrderSmsService.php';

$orderId = 28;

echo "=== Testing orderShipping for order $orderId ===" . PHP_EOL;

try {
    $result = OrderSmsService::orderShipping($orderId);
    echo "===== RESULT =====" . PHP_EOL;
    print_r($result);
} catch (Throwable $e) {
    echo "===== ERROR =====" . PHP_EOL;
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo PHP_EOL . "===== TRACE =====" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
