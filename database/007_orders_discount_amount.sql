-- 007_orders_discount_amount.sql
-- ستون discount_amount در سطح سفارش (برای گزارش تخفیف‌ها)
-- در 002_orders.sql قبلاً اضافه شده، این migration برای دیتابیس‌های قدیمی که قبل از 002 ساخته شده‌اند نگه داشته شده است.

-- idempotent: فقط اگر ستون وجود ندارد اضافه کن
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'discount_amount'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE orders ADD COLUMN discount_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_fee',
  'SELECT "discount_amount already exists" AS msg'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
