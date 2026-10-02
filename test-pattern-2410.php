<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/settings.php';
require_once ROOT_PATH . '/app/services/SmsService.php';

$mobile = '09127264560';

$variables = [
    'ORDER'  => 'ORD-261002-0912',
    'DATE'   => '1405/07/10',
    'TIME'   => date('H:i'),
    'PACKED' => 'تحویل پیک جهت ارسال',
    'LOGO'   => 'از خرید شما متشکریم',
];

try {
    $result = SmsService::sendPattern(
        $mobile,
        2410,
        $variables,
        null,
        null,
        'test_order_shipping_2410'
    );

    echo PHP_EOL;
    echo "===== RESULT =====" . PHP_EOL;
    print_r($result);
    echo PHP_EOL;
    echo "===== VARIABLES =====" . PHP_EOL;
    print_r($variables);
    echo PHP_EOL;
} catch (Throwable $e) {
    echo PHP_EOL;
    echo "===== ERROR =====" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
}
