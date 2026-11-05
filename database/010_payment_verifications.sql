-- =====================================================
--  JoorabRabeti - 010_payment_verifications.sql
--  تعریف جدول payment_verifications که کد در admin/payments.php
--  و public/invoice-pdf.php و public/invoice-print.php و
--  public/account-orders.php و admin/report-ledger.php به آن وابسته است
--  ولی قبلاً در هیچ migration تعریف نشده بود.
--
--  ستون‌ها دقیقاً مطابق با INSERT/UPDATE/SELECT در admin/payments.php هستند.
-- =====================================================

CREATE TABLE IF NOT EXISTS `payment_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_submission_id` INT UNSIGNED NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `verified_amount` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('verified','rejected','adjusted') NOT NULL DEFAULT 'verified',
  `note` VARCHAR(500) DEFAULT NULL,
  `verified_by` INT UNSIGNED NOT NULL,
  `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pv_submission` (`payment_submission_id`),
  KEY `idx_pv_invoice` (`invoice_id`),
  KEY `idx_pv_invoice_status` (`invoice_id`, `status`),
  KEY `idx_pv_user` (`verified_by`),
  KEY `idx_pv_created` (`verified_at`),
  CONSTRAINT `fk_pv_submission`
    FOREIGN KEY (`payment_submission_id`)
    REFERENCES `payment_submissions` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_pv_invoice`
    FOREIGN KEY (`invoice_id`)
    REFERENCES `invoices` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_pv_user`
    FOREIGN KEY (`verified_by`)
    REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
--  End of 010_payment_verifications.sql
-- =====================================================
