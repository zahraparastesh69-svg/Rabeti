<?php

require __DIR__ . '/config/database.php';

$db = db();

echo "=== INVOICE PAYMENT SUMMARY ===\n\n";

$sql = "
    SELECT
        i.id,
        i.invoice_number,
        i.total,
        i.paid_total,
        i.status,
        i.reconciliation_type,
        i.reconciliation_amount,

        COUNT(p.id) AS payment_count,

        COALESCE(
            SUM(
                CASE
                    WHEN p.status = 'confirmed'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS confirmed_sum,

        COALESCE(
            SUM(
                CASE
                    WHEN p.status = 'pending'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS pending_sum

    FROM invoices i

    LEFT JOIN payments p
        ON p.invoice_id = i.id

    GROUP BY
        i.id,
        i.invoice_number,
        i.total,
        i.paid_total,
        i.status,
        i.reconciliation_type,
        i.reconciliation_amount

    ORDER BY i.id DESC
    LIMIT 30
";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    print_r($row);
    echo str_repeat('-', 80) . "\n";
}

echo "\n=== PAYMENT COUNT PER INVOICE ===\n\n";

$sql = "
    SELECT
        invoice_id,
        COUNT(*) AS payment_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
    FROM payments
    GROUP BY invoice_id
    HAVING COUNT(*) > 1
    ORDER BY payment_count DESC
";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "هیچ فاکتوری با بیش از یک پرداخت پیدا نشد.\n";
} else {
    print_r($rows);
}
