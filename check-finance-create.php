<?php
require __DIR__ . '/config/database.php';

$db = db();

$tables = [
    'payment_verification_history',
    'financial_transactions',
    'audit_logs'
];

foreach ($tables as $table) {
    echo PHP_EOL;
    echo "==================== {$table} ====================" . PHP_EOL;

    $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch();

    echo $row['Create Table'] . PHP_EOL;
}
