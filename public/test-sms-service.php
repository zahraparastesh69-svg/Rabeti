<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/settings.php';
require_once ROOT_PATH . '/app/services/SmsService.php';

echo "<pre>";

try {
    $patternId = Setting::getInt('sms_pattern_order_approved', 0);

    echo "Pattern ID: " . $patternId . "\n";

    $result = SmsService::sendPattern(
        '09127264560',
        $patternId,
        [
            'ORDER' => 'TEST-1001',
            'DATE'  => date('Y/m/d'),
            'MONEY' => '1250000'
        ],
        null,
        null,
        'order_approved_test'
    );

    echo "\n=== SMS TEST SUCCESS ===\n\n";
    print_r($result);

} catch (Throwable $e) {

    echo "\n=== SMS TEST FAILED ===\n\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
