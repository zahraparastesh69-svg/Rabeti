<?php
require __DIR__ . '/config/database.php';

$db = db();

$sql = "
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        COLUMN_TYPE,
        IS_NULLABLE,
        COLUMN_KEY,
        COLUMN_DEFAULT,
        EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'sock_b2b'
      AND TABLE_NAME IN ('order_items', 'invoices', 'payments')
    ORDER BY
        FIELD(TABLE_NAME, 'order_items', 'invoices', 'payments'),
        ORDINAL_POSITION
";

$rows = $db->query($sql)->fetchAll();

$current = '';

foreach ($rows as $r) {
    if ($current !== $r['TABLE_NAME']) {
        echo PHP_EOL;
        echo "===== {$r['TABLE_NAME']} =====" . PHP_EOL;
        $current = $r['TABLE_NAME'];
    }

    echo $r['COLUMN_NAME']
        . " | " . $r['COLUMN_TYPE']
        . " | NULL=" . $r['IS_NULLABLE']
        . " | KEY=" . $r['COLUMN_KEY']
        . " | DEFAULT=" . ($r['COLUMN_DEFAULT'] ?? 'NULL')
        . " | " . $r['EXTRA']
        . PHP_EOL;
}
