-- =====================================================
--  JoorabRabeti - 015_order_item_fulfillment.sql
--  افزودن ستون‌های تفکیک تأمین به order_items
--
--  هر قلم سفارش باید بتواند نشان دهد چه مقدار:
--    - تأمین از موجودی (qty_supplied_from_stock)
--    - رزرو شده (qty_reserved)
--    - نیازمند تولید/تأمین مجدد (qty_to_produce)
--    - آماده تحویل (qty_ready)
--    - ارسال‌شده (qty_delivered)
--    - لغو/مرجوع (qty_cancelled)
--
--  invariant: qty = qty_reserved + qty_to_produce (در زمان ثبت)
--             qty_reserved = qty_supplied_from_stock + ... (در حال تأمین)
--             qty_delivered + qty_cancelled = qty (در پایان)
-- =====================================================

-- 1. qty_reserved
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_reserved');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_reserved INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty",
  'SELECT "qty_reserved exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. qty_supplied_from_stock
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_supplied_from_stock');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_supplied_from_stock INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_reserved",
  'SELECT "qty_supplied_from_stock exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. qty_to_produce
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_to_produce');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_to_produce INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_supplied_from_stock",
  'SELECT "qty_to_produce exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. qty_ready
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_ready');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_ready INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_to_produce",
  'SELECT "qty_ready exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. qty_delivered
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_delivered');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_delivered INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_ready",
  'SELECT "qty_delivered exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. qty_cancelled
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'qty_cancelled');
SET @sql := IF(@c = 0,
  "ALTER TABLE order_items ADD COLUMN qty_cancelled INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_delivered",
  'SELECT "qty_cancelled exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7. مهاجرت داده — برای سفارش‌های قبلی، کل qty به‌عنوان supplied_or_reserved در نظر گرفته می‌شود
--    (مگرcancelled، که qty_cancelled = qty)
UPDATE order_items oi
INNER JOIN orders o ON o.id = oi.order_id
SET oi.qty_cancelled = oi.qty
WHERE o.status = 'cancelled';

UPDATE order_items oi
INNER JOIN orders o ON o.id = oi.order_id
SET oi.qty_delivered = oi.qty
WHERE o.status = 'delivered';

UPDATE order_items oi
INNER JOIN orders o ON o.id = oi.order_id
SET oi.qty_reserved = oi.qty
WHERE o.status NOT IN ('cancelled', 'delivered')
  AND oi.qty_reserved = 0
  AND oi.qty_delivered = 0;

-- 8. ایندکس برای جستجوی سریع اقلام نیازمند تولید
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
             AND INDEX_NAME = 'idx_oi_to_produce');
SET @sql := IF(@i = 0,
  'ALTER TABLE order_items ADD INDEX idx_oi_to_produce (qty_to_produce)',
  'SELECT "idx_oi_to_produce exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 015_order_item_fulfillment.sql
-- =====================================================
