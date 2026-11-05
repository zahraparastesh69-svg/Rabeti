-- =====================================================
--  JoorabRabeti - 016_products_customers_columns.sql
--  افزودن ستون‌های ساختاریافته به products و companies.
-- =====================================================

-- === products ===

-- brand
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'brand');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN brand VARCHAR(100) NULL DEFAULT NULL AFTER name",
  'SELECT "brand exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- material (جنس)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'material');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN material VARCHAR(100) NULL DEFAULT NULL AFTER brand",
  'SELECT "material exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- color
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'color');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN color VARCHAR(60) NULL DEFAULT NULL AFTER material",
  'SELECT "color exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- size
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'size');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN size VARCHAR(60) NULL DEFAULT NULL AFTER color",
  'SELECT "size exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- is_custom_orderable (قابل سفارش اختصاصی)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_custom_orderable');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN is_custom_orderable TINYINT(1) NOT NULL DEFAULT 0 AFTER base_moq",
  'SELECT "is_custom_orderable exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cost_price (برای محاسبه سود در گزارش‌های آینده)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'cost_price');
SET @sql := IF(@c = 0,
  "ALTER TABLE products ADD COLUMN cost_price INT UNSIGNED NOT NULL DEFAULT 0 AFTER base_moq",
  'SELECT "cost_price exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ایندکس برند و جنس برای فیلتر
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
             AND INDEX_NAME = 'idx_products_brand');
SET @sql := IF(@i = 0,
  'ALTER TABLE products ADD INDEX idx_products_brand (brand)',
  'SELECT "idx_products_brand exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- === companies ===

-- payment_terms (شرایط پرداخت: net_7, net_15, net_30, cod, prepaid, ...)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'payment_terms');
SET @sql := IF(@c = 0,
  "ALTER TABLE companies ADD COLUMN payment_terms VARCHAR(30) NOT NULL DEFAULT 'prepaid' AFTER credit_limit",
  'SELECT "payment_terms exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- internal_notes (یادداشت‌های داخلی فقط برای ادمین)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'internal_notes');
SET @sql := IF(@c = 0,
  "ALTER TABLE companies ADD COLUMN internal_notes TEXT NULL DEFAULT NULL AFTER payment_terms",
  'SELECT "internal_notes exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- due_days (تعداد روز مهلت پرداخت از صدور فاکتور)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'due_days');
SET @sql := IF(@c = 0,
  "ALTER TABLE companies ADD COLUMN due_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_terms",
  'SELECT "due_days exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 016_products_customers_columns.sql
-- =====================================================
