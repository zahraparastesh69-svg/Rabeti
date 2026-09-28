<?php

require __DIR__ . '/config/database.php';

$db = db();

$checks = [
    'invoices' => 'SELECT COUNT(*) FROM invoices',
    'payment_submissions' => 'SELECT COUNT(*) FROM payment_submissions',
    'payment_verifications' => 'SELECT COUNT(*) FROM payment_verifications',
    'payment_verification_history' => 'SELECT COUNT(*) FROM payment_verification_history',
    'financial_transactions' => 'SELECT COUNT(*) FROM financial_transactions',
];

echo PHP_EOL;
echo "=== PHP FINANCIAL CONNECTION TEST ===" . PHP_EOL;

foreach ($checks as $name => $sql) {
    $count = (int)$db->query($sql)->fetchColumn();

    echo sprintf(
        "%-40s OK | rows=%d" . PHP_EOL,
        $name,
        $count
    );
}

echo PHP_EOL;
echo "ALL FINANCIAL TABLES ARE ACCESSIBLE." . PHP_EOL;
