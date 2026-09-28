<?php

require_once __DIR__ . '/config/database.php';

$db = db();

$rows = $db->query("
    SELECT
        id,
        invoice_number,
        total,
        paid_total,
        status,
        reconciliation_type,
        reconciliation_amount
    FROM invoices
    ORDER BY id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

echo '<pre>';
print_r($rows);
echo '</pre>';
