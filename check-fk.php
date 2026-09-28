<?php
require __DIR__ . '/config/database.php';

$db = db();

$sql = "
    SELECT
        k.TABLE_NAME,
        k.COLUMN_NAME,
        k.REFERENCED_TABLE_NAME,
        k.REFERENCED_COLUMN_NAME,
        k.CONSTRAINT_NAME,
        r.DELETE_RULE,
        r.UPDATE_RULE
    FROM information_schema.KEY_COLUMN_USAGE AS k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = 'sock_b2b'
      AND k.TABLE_NAME IN (
          'users',
          'orders',
          'order_items',
          'invoices',
          'payments'
      )
      AND k.REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY
        FIELD(
            k.TABLE_NAME,
            'users',
            'orders',
            'order_items',
            'invoices',
            'payments'
        ),
        k.COLUMN_NAME
";

$rows = $db->query($sql)->fetchAll();

if (!$rows) {
    echo "NO FOREIGN KEYS FOUND" . PHP_EOL;
    exit;
}

foreach ($rows as $r) {
    echo $r['TABLE_NAME']
        . '.' . $r['COLUMN_NAME']
        . ' -> '
        . $r['REFERENCED_TABLE_NAME']
        . '.' . $r['REFERENCED_COLUMN_NAME']
        . ' | DELETE=' . $r['DELETE_RULE']
        . ' | UPDATE=' . $r['UPDATE_RULE']
        . ' | CONSTRAINT=' . $r['CONSTRAINT_NAME']
        . PHP_EOL;
}
