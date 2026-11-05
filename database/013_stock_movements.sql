-- =====================================================
--  JoorabRabeti - 013_stock_movements.sql
--  جدول گردش موجودی — هر تغییر موجودی (فیزیکی، رزرو، غیرقابل فروش)
--  باید یک رکورد در این جدول ثبت کند. موجودی نباید بدون سابقه
--  به‌صورت مستقیم تغییر کند.
--
--  types:
--    stock_in            — ورود کالا به انبار (فیزیکی +)
--    stock_out           — خروج کالا به دلایل غیرفروش (فیزیکی -)
--    reserve             — رزرو برای سفارش (reserved +)
--    release             — آزادسازی رزرو (reserved -)
--    allocate            — تخصیص رزرو به خروج (reserved -, فیزیکی -)
--    ship                — ارسال به مشتری (فیزیکی -)
--    return_sellable     — مرجوعی قابل فروش (فیزیکی +)
--    return_unsellable   — مرجوعی غیرقابل فروش (unsellable +)
--    adjust_physical     — اصلاح موجودی فیزیکی با دلیل
--    adjust_unsellable   — اصلاح موجودی غیرقابل فروش
--    production_in       — ورود محصول تولیدشده (فیزیکی +)
-- =====================================================

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `movement_type` VARCHAR(40) NOT NULL,
  `quantity` INT NOT NULL,
  `balance_physical` INT NOT NULL DEFAULT 0,
  `balance_reserved` INT NOT NULL DEFAULT 0,
  `balance_unsellable` INT NOT NULL DEFAULT 0,
  `reference_type` VARCHAR(40) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `order_item_id` INT UNSIGNED DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_product` (`product_id`, `created_at`),
  KEY `idx_sm_type` (`movement_type`, `created_at`),
  KEY `idx_sm_order` (`order_id`),
  KEY `idx_sm_reference` (`reference_type`, `reference_id`),
  KEY `idx_sm_created` (`created_at`),
  CONSTRAINT `fk_sm_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_sm_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_sm_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
--  End of 013_stock_movements.sql
-- =====================================================
