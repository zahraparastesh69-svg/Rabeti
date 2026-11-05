

-- جدول payments در این پروژه وجود ندارد (پرداخت‌ها در payment_submissions هستند و claimed_amount
-- از قبل در آن تعریف شده). ALTER/UPDATE قبلی روی payments حذف شد چون اجرای migration را می‌شکست.



CREATE TABLE IF NOT EXISTS payment_verification_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    payment_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,

    previous_verified_amount INT UNSIGNED NULL,
    new_verified_amount INT UNSIGNED NULL,

    action ENUM(
        'verify',
        'adjust',
        'reject',
        'reopen'
    ) NOT NULL,

    note VARCHAR(500) NULL,

    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_pvh_payment (payment_id),
    KEY idx_pvh_invoice (invoice_id),
    KEY idx_pvh_created (created_at),

    CONSTRAINT fk_pvh_payment
        FOREIGN KEY (payment_id)
        REFERENCES payment_submissions(id),

    CONSTRAINT fk_pvh_invoice
        FOREIGN KEY (invoice_id)
        REFERENCES invoices(id),

    CONSTRAINT fk_pvh_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;




ALTER TABLE financial_transactions
    ADD COLUMN transaction_date DATETIME NULL AFTER description;

UPDATE financial_transactions
SET transaction_date = created_at
WHERE transaction_date IS NULL;