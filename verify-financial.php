<?php

require __DIR__ . '/config/database.php';

$db = db();

echo PHP_EOL;
echo "=== FINANCIAL TABLES ===" . PHP_EOL;

$tables = [
    'invoices',
    'payment_submissions',
    'payment_verifications',
    'payment_verification_history',
    'financial_transactions',
];

$stmt = $db->prepare("
    SELECT
        t.TABLE_NAME,
        t.TABLE_ROWS
    FROM information_schema.tables AS t
    WHERE t.TABLE_SCHEMA = DATABASE()
      AND t.TABLE_NAME = ?
");

foreach ($tables as $table) {
    $stmt->execute([$table]);
    $row = $stmt->fetch();

    echo sprintf(
        "%-35s %s" . PHP_EOL,
        $table,
        $row ? 'EXISTS' : 'MISSING'
    );
}

echo PHP_EOL;
echo "=== FOREIGN KEYS ===" . PHP_EOL;

$stmt = $db->query("
    SELECT
        k.TABLE_NAME AS child_table,
        k.COLUMN_NAME AS child_column,
        k.REFERENCED_TABLE_NAME AS parent_table,
        k.REFERENCED_COLUMN_NAME AS parent_column,
        k.CONSTRAINT_NAME AS constraint_name,
        r.DELETE_RULE AS delete_rule,
        r.UPDATE_RULE AS update_rule
    FROM information_schema.KEY_COLUMN_USAGE AS k
    INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
        ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
       AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
       AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.TABLE_NAME IN (
          'invoices',
          'payment_submissions',
          'payment_verifications',
          'payment_verification_history',
          'financial_transactions'
      )
      AND k.REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY
        k.TABLE_NAME,
        k.CONSTRAINT_NAME
");

while ($row = $stmt->fetch()) {
    echo sprintf(
        "%-45s -> %-30s | DELETE=%-8s | UPDATE=%s" . PHP_EOL,
        $row['child_table'] . '.' . $row['child_column'],
        $row['parent_table'] . '.' . $row['parent_column'],
        $row['delete_rule'],
        $row['update_rule']
    );
}

echo PHP_EOL;
echo "=== COUNTS ===" . PHP_EOL;

foreach ($tables as $table) {
    $count = (int)$db
        ->query("SELECT COUNT(*) FROM `{$table}`")
        ->fetchColumn();

    echo sprintf(
        "%-35s %d" . PHP_EOL,
        $table,
        $count
    );
}

echo PHP_EOL;
echo "VERIFY COMPLETE" . PHP_EOL;
