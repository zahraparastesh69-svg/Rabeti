<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$db = db();

foreach (['invoices', 'payments', 'orders', 'audit_logs'] as $table) {
    echo "\n================ $table ================\n";
    $stmt = $db->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n";
}
