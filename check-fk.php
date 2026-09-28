<?php

require __DIR__ . '/config/database.php';

$db = db();

$sql = "
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'sock_b2b'
  AND REFERENCED_TABLE_NAME IN ('orders', 'invoices', 'payments')
ORDER BY
    REFERENCED_TABLE_NAME,
    TABLE_NAME
";

echo "=== FOREIGN KEY DEPENDENCIES ===\n\n";

print_r($db->query($sql)->fetchAll(PDO::FETCH_ASSOC));
