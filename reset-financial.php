<?php

require __DIR__ . '/config/database.php';

$db = db();

/*
|--------------------------------------------------------------------------
| SAFETY CHECK
|--------------------------------------------------------------------------
*/

$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();

if ($currentDb !== 'sock_b2b') {
    die(
        "ABORT: Expected database sock_b2b, got: "
        . ($currentDb ?: 'NULL')
        . PHP_EOL
    );
}

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " RABETI FINANCIAL SYSTEM RESET + REBUILD" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "Database: {$currentDb}" . PHP_EOL;
echo PHP_EOL;

echo "WARNING:" . PHP_EOL;
echo "The following financial data will be DELETED:" . PHP_EOL;
echo " - invoices" . PHP_EOL;
echo " - payments" . PHP_EOL;
echo " - payment_verification_history" . PHP_EOL;
echo " - financial_transactions" . PHP_EOL;
echo PHP_EOL;

echo "The following will NOT be deleted:" . PHP_EOL;
echo " - users" . PHP_EOL;
echo " - orders" . PHP_EOL;
echo " - order_items" . PHP_EOL;
echo " - products" . PHP_EOL;
echo " - companies" . PHP_EOL;
echo " - audit_logs" . PHP_EOL;
echo PHP_EOL;


/*
|--------------------------------------------------------------------------
| DROP OLD FINANCIAL TABLES
|--------------------------------------------------------------------------
|
| DDL in MariaDB causes implicit commits, so this is intentionally NOT
| wrapped in a fake transaction.
|
*/

$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$dropTables = [
    'payment_verification_history',
    'payment_verifications',
    'payment_submissions',
    'payments',
    'financial_transactions',
    'invoices',
];

foreach ($dropTables as $table) {
    echo "Dropping {$table}..." . PHP_EOL;
    $db->exec("DROP TABLE IF EXISTS `{$table}`");
}

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

echo PHP_EOL;
echo "Old financial tables removed." . PHP_EOL;
echo PHP_EOL;


/*
|--------------------------------------------------------------------------
| INVOICES
|--------------------------------------------------------------------------
*/

echo "Creating invoices..." . PHP_EOL;

$db->exec("
    CREATE TABLE invoices (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        invoice_number VARCHAR(30) NOT NULL,
        order_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,

        total INT UNSIGNED NOT NULL,
        paid_total INT UNSIGNED NOT NULL DEFAULT 0,

        status ENUM(
            'unpaid',
            'partial',
            'paid',
            'void'
        ) NOT NULL DEFAULT 'unpaid',

        reconciliation_type ENUM(
            'settled',
            'debtor',
            'creditor'
        ) NOT NULL DEFAULT 'settled',

        reconciliation_amount INT UNSIGNED NOT NULL DEFAULT 0,

        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY uq_invoices_number (invoice_number),
        UNIQUE KEY uq_invoices_order (order_id),

        KEY idx_invoices_user (user_id),
        KEY idx_invoices_status (status),
        KEY idx_invoices_reconciliation (
            reconciliation_type,
            reconciliation_amount
        ),

        CONSTRAINT fk_invoices_order
            FOREIGN KEY (order_id)
            REFERENCES orders(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_invoices_user
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| PAYMENT SUBMISSIONS
|--------------------------------------------------------------------------
|
| This table stores ONLY what the customer submitted.
| claimed_amount is never treated as verified money.
|
*/

echo "Creating payment_submissions..." . PHP_EOL;

$db->exec("
    CREATE TABLE payment_submissions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        invoice_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,

        method ENUM(
            'sheba',
            'card_to_card',
            'cash_receipt',
            'online'
        ) NOT NULL,

        claimed_amount INT UNSIGNED NOT NULL,

        reference_no VARCHAR(100) DEFAULT NULL,
        receipt_path VARCHAR(255) DEFAULT NULL,
        transaction_date DATETIME DEFAULT NULL,

        customer_note VARCHAR(500) DEFAULT NULL,

        status ENUM(
            'pending',
            'verified',
            'rejected'
        ) NOT NULL DEFAULT 'pending',

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        KEY idx_ps_invoice (invoice_id),
        KEY idx_ps_user (user_id),
        KEY idx_ps_status (status),
        KEY idx_ps_invoice_status (invoice_id, status),
        KEY idx_ps_created (created_at),

        CONSTRAINT fk_ps_invoice
            FOREIGN KEY (invoice_id)
            REFERENCES invoices(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_ps_user
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| PAYMENT VERIFICATIONS
|--------------------------------------------------------------------------
|
| One financial verification record per payment submission.
|
*/

echo "Creating payment_verifications..." . PHP_EOL;

$db->exec("
    CREATE TABLE payment_verifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        payment_submission_id INT UNSIGNED NOT NULL,

        verified_amount INT UNSIGNED DEFAULT NULL,

        status ENUM(
            'pending',
            'verified',
            'adjusted',
            'rejected'
        ) NOT NULL DEFAULT 'pending',

        note VARCHAR(500) DEFAULT NULL,

        verified_by INT UNSIGNED DEFAULT NULL,
        verified_at DATETIME DEFAULT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY uq_pv_submission (payment_submission_id),

        KEY idx_pv_status (status),
        KEY idx_pv_verified_by (verified_by),
        KEY idx_pv_verified_at (verified_at),

        CONSTRAINT fk_pv_submission
            FOREIGN KEY (payment_submission_id)
            REFERENCES payment_submissions(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_pv_verified_by
            FOREIGN KEY (verified_by)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| PAYMENT VERIFICATION HISTORY
|--------------------------------------------------------------------------
|
| Immutable audit trail for verification changes.
|
*/

echo "Creating payment_verification_history..." . PHP_EOL;

$db->exec("
    CREATE TABLE payment_verification_history (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        payment_submission_id INT UNSIGNED NOT NULL,
        invoice_id INT UNSIGNED NOT NULL,

        old_verified_amount INT UNSIGNED DEFAULT NULL,
        new_verified_amount INT UNSIGNED DEFAULT NULL,

        old_status ENUM(
            'pending',
            'verified',
            'adjusted',
            'rejected'
        ) DEFAULT NULL,

        new_status ENUM(
            'pending',
            'verified',
            'adjusted',
            'rejected'
        ) DEFAULT NULL,

        reason VARCHAR(500) DEFAULT NULL,

        changed_by INT UNSIGNED NOT NULL,

        ip VARCHAR(45) DEFAULT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        KEY idx_pvh_submission (
            payment_submission_id
        ),

        KEY idx_pvh_invoice (
            invoice_id
        ),

        KEY idx_pvh_changed_by (
            changed_by
        ),

        KEY idx_pvh_changed_at (
            changed_at
        ),

        CONSTRAINT fk_pvh_submission
            FOREIGN KEY (payment_submission_id)
            REFERENCES payment_submissions(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_pvh_invoice
            FOREIGN KEY (invoice_id)
            REFERENCES invoices(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_pvh_changed_by
            FOREIGN KEY (changed_by)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| FINANCIAL TRANSACTIONS
|--------------------------------------------------------------------------
|
| Currently used for creditor settlement.
|
*/

echo "Creating financial_transactions..." . PHP_EOL;

$db->exec("
    CREATE TABLE financial_transactions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        invoice_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,

        type ENUM(
            'creditor_settlement'
        ) NOT NULL,

        amount INT UNSIGNED NOT NULL,

        method ENUM(
            'sheba',
            'card_to_card',
            'cash'
        ) NOT NULL,

        document_no VARCHAR(100) DEFAULT NULL,
        reference_no VARCHAR(100) DEFAULT NULL,

        description VARCHAR(500) DEFAULT NULL,

        transaction_date DATETIME DEFAULT NULL,

        status ENUM(
            'pending',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'completed',

        created_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,

        PRIMARY KEY (id),

        KEY idx_financial_invoice (invoice_id),
        KEY idx_financial_user (user_id),
        KEY idx_financial_type (type),
        KEY idx_financial_status (status),
        KEY idx_financial_created (created_at),
        KEY idx_financial_creator (created_by),

        CONSTRAINT fk_financial_invoice
            FOREIGN KEY (invoice_id)
            REFERENCES invoices(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_financial_user
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT,

        CONSTRAINT fk_financial_creator
            FOREIGN KEY (created_by)
            REFERENCES users(id)
            ON DELETE RESTRICT
            ON UPDATE RESTRICT
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| FINAL VERIFICATION
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " VERIFYING NEW FINANCIAL STRUCTURE" . PHP_EOL;
echo "==============================================" . PHP_EOL;

$expectedTables = [
    'invoices',
    'payment_submissions',
    'payment_verifications',
    'payment_verification_history',
    'financial_transactions',
];

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = 'sock_b2b'
      AND table_name = ?
");

foreach ($expectedTables as $table) {
    $stmt->execute([$table]);
    $exists = (int)$stmt->fetchColumn();

    echo $table . ': ' . ($exists ? 'OK' : 'MISSING') . PHP_EOL;

    if (!$exists) {
        throw new RuntimeException(
            "Verification failed: {$table} was not created."
        );
    }
}

echo PHP_EOL;

$oldTables = [
    'payments',
];

foreach ($oldTables as $table) {
    $stmt->execute([$table]);
    $exists = (int)$stmt->fetchColumn();

    echo $table . ': ' . ($exists ? 'STILL EXISTS' : 'REMOVED') . PHP_EOL;

    if ($exists) {
        throw new RuntimeException(
            "Old table {$table} still exists."
        );
    }
}

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " FINANCIAL RESET + REBUILD COMPLETED" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "Customers/orders/products were NOT deleted." . PHP_EOL;
echo "All previous financial records were deleted." . PHP_EOL;
echo "New financial schema is ready." . PHP_EOL;
echo PHP_EOL;
