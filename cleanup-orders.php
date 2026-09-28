<?php

require __DIR__ . '/config/database.php';

$db = db();

$tables = [
    'payments',
    'invoices',
    'order_items',
    'order_shipments',
    'orders',
    'custom_order_requests',
];

echo "=== BEFORE CLEANUP ===\n\n";

foreach ($tables as $table) {
    $count = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo str_pad($table, 25) . " : " . $count . "\n";
}

echo "\n";
echo "THIS SCRIPT WILL DELETE ALL ORDER/FINANCIAL TEST DATA.\n";
echo "Products, users, categories, prices and inventory will remain.\n\n";

echo "Type DELETE to continue: ";
$input = trim(fgets(STDIN));

if ($input !== 'DELETE') {
    echo "\nCANCELLED. Nothing was deleted.\n";
    exit;
}

try {
    $db->beginTransaction();

    foreach ($tables as $table) {
        $db->exec("DELETE FROM `$table`");
    }

    $db->commit();

    echo "\n=== CLEANUP COMPLETED ===\n\n";

    foreach ($tables as $table) {
        $count = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo str_pad($table, 25) . " : " . $count . "\n";
    }

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo "\nERROR - ROLLBACK DONE\n";
    echo $e->getMessage() . "\n";
}
