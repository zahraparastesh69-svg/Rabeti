-- ═══════════════════════════════════════════════════════════════════
-- Migration 001: Pricing v2 — Customer Groups + Base Price + Tiers
-- تاریخ: 2026-10-28
-- ⚠️ قبل از اجرا از دیتابیس بکاپ بگیر:
--    mysqldump -u root sock_b2b > backup_before_pricing_v2.sql
-- ═══════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════
-- ۱) جدول جدید customer_groups
-- ═══════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `customer_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `min_purchase_jin` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- داده اولیه
INSERT INTO `customer_groups`
    (`code`, `name`, `discount_percent`, `min_purchase_jin`, `is_active`, `sort_order`)
VALUES
    ('regular', 'مشتری عادی',  0.00,    0,   1, 10),
    ('bronze',  'برنزی',      12.00,  100,   1, 20),
    ('silver',  'نقره‌ای',    15.00,  500,   1, 30),
    ('gold',    'طلایی',      20.00, 1000,   1, 40)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `discount_percent` = VALUES(`discount_percent`),
    `min_purchase_jin` = VALUES(`min_purchase_jin`),
    `sort_order` = VALUES(`sort_order`);


-- ═══════════════════════════════════════════════════════════════════
-- ۲) تغییر users — customer_group + total_purchased_jin + upgraded_at
-- ═══════════════════════════════════════════════════════════════════
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `customer_group` VARCHAR(50) NOT NULL DEFAULT 'regular' AFTER `role`,
    ADD COLUMN IF NOT EXISTS `total_purchased_jin` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `customer_group`,
    ADD COLUMN IF NOT EXISTS `group_upgraded_at` DATETIME NULL AFTER `total_purchased_jin`,
    ADD INDEX IF NOT EXISTS `idx_customer_group` (`customer_group`);


-- ═══════════════════════════════════════════════════════════════════
-- ۳) تغییر companies — customer_group از ENUM به VARCHAR
-- ═══════════════════════════════════════════════════════════════════
ALTER TABLE `companies`
    MODIFY COLUMN `customer_group` VARCHAR(50) NOT NULL DEFAULT 'regular';

ALTER TABLE `companies`
    ADD COLUMN IF NOT EXISTS `total_purchased_jin` INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `group_upgraded_at` DATETIME NULL;

-- نگاشت مقادیر قبلی
UPDATE `companies` SET `customer_group` = 'regular' WHERE `customer_group` = 'retail';
UPDATE `companies` SET `customer_group` = 'bronze'  WHERE `customer_group` = 'wholesale';
UPDATE `companies` SET `customer_group` = 'gold'    WHERE `customer_group` = 'vip';


-- ═══════════════════════════════════════════════════════════════════
-- ۴) تغییر products — base_price, status, meta_*
-- ═══════════════════════════════════════════════════════════════════
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `base_price` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `base_moq`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft' AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `meta_title` VARCHAR(200) NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `meta_description` TEXT NULL AFTER `meta_title`,
    ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- مهاجرت: قیمت پایه فعلی از product_prices (wholesale, min_qty=1)
UPDATE `products` p
INNER JOIN `product_prices` pp
    ON pp.product_id = p.id
   AND pp.customer_group = 'wholesale'
   AND pp.min_qty = 1
SET p.base_price = pp.price
WHERE p.base_price = 0;

-- برای محصولاتی که wholesale نداشتند، از retail یا vip یا اولین قیمت موجود
UPDATE `products` p
INNER JOIN `product_prices` pp ON pp.product_id = p.id
SET p.base_price = pp.price
WHERE p.base_price = 0
  AND pp.min_qty = 1
  AND pp.price > 0;

-- محصولاتی که base_price > 0 دارند → status=active
UPDATE `products`
SET `status` = IF(`is_active` = 1, 'active', 'inactive')
WHERE `base_price` > 0;


-- ═══════════════════════════════════════════════════════════════════
-- ۵) جدول جدید product_tier_discounts
-- ═══════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `product_tier_discounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `min_qty` INT UNSIGNED NOT NULL,
    `discount_percent` DECIMAL(5,2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_product_qty` (`product_id`, `min_qty`),
    KEY `idx_product` (`product_id`),
    CONSTRAINT `fk_ptd_product` FOREIGN KEY (`product_id`)
        REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- مهاجرت: پله‌های wholesale فعلی به product_tier_discounts (با محاسبه درصد)
INSERT INTO `product_tier_discounts`
    (`product_id`, `min_qty`, `discount_percent`, `sort_order`)
SELECT
    pp.product_id,
    pp.min_qty,
    ROUND((1 - (pp.price / p.base_price)) * 100, 2),
    ROW_NUMBER() OVER (PARTITION BY pp.product_id ORDER BY pp.min_qty)
FROM `product_prices` pp
INNER JOIN `products` p ON p.id = pp.product_id
WHERE pp.customer_group = 'wholesale'
  AND pp.min_qty > 1
  AND p.base_price > 0
  AND pp.price < p.base_price
ON DUPLICATE KEY UPDATE
    `discount_percent` = VALUES(`discount_percent`);


-- ═══════════════════════════════════════════════════════════════════
-- ۶) settings — آستانه ارتقا
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO `settings` (`key`, `value`) VALUES
    ('customer_group.bronze_min_jin', '100'),
    ('customer_group.silver_min_jin', '500'),
    ('customer_group.gold_min_jin',   '1000')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);


-- ═══════════════════════════════════════════════════════════════════
-- ۷) تغییر order_items — تفکیک تخفیف
-- ═══════════════════════════════════════════════════════════════════
ALTER TABLE `order_items`
    ADD COLUMN IF NOT EXISTS `group_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `base_unit_price`,
    ADD COLUMN IF NOT EXISTS `tier_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `group_discount_percent`,
    ADD COLUMN IF NOT EXISTS `discount_source` ENUM('none','group','tier') NOT NULL DEFAULT 'none' AFTER `discount_percent`;

-- مهاجرت سفارش‌های قبلی
UPDATE `order_items`
SET `tier_discount_percent` = `discount_percent`,
    `group_discount_percent` = 0,
    `discount_source` = IF(`discount_percent` > 0, 'tier', 'none')
WHERE `discount_source` = 'none' AND `discount_percent` > 0;


SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════
-- ✅ پایان مهاجرت
-- ═══════════════════════════════════════════════════════════════════