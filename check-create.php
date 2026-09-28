<?php
require __DIR__ . '/config/database.php';

$db = db();

$tables = [
    'users',
    'orders',
    'order_items',
    'invoices',
    'payments'
];

foreach ($tables as $table) {
    echo PHP_EOL;
    echo "==================== {$table} ====================" . PHP_EOL;

    $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch();

    echo $row['Create Table'] . PHP_EOL;
}
