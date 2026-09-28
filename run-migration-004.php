<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$sql = file_get_contents(__DIR__ . '/database/004_create-financial-transactions.sql');

db()->exec($sql);

echo "Migration 004 executed successfully.\n";

$stmt = db()->query("SHOW TABLES LIKE 'financial_transactions'");
echo $stmt->fetchColumn()
    ? "Table financial_transactions exists.\n"
    : "Table financial_transactions NOT FOUND.\n";
