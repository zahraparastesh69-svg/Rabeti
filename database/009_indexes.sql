-- =====================================================
--  JoorabRabeti - 009_indexes.sql
--  Performance indexes for B2B wholesale queries
--  (idempotent — safe to re-run)
-- =====================================================

-- 1. products: فیلتر محصولات فعال بر اساس دسته
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
             AND INDEX_NAME = 'idx_products_active_cat');
SET @sql := IF(@i = 0,
  'CREATE INDEX idx_products_active_cat ON products (is_active, category_id)',
  'SELECT "idx_products_active_cat already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. product_images: دریافت تصویر اصلی هر محصول
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_images'
             AND INDEX_NAME = 'idx_product_images_main');
SET @sql := IF(@i = 0,
  'CREATE INDEX idx_product_images_main ON product_images (product_id, is_main)',
  'SELECT "idx_product_images_main already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. invoices: صورت‌حساب مشتری بر اساس وضعیت
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
             AND INDEX_NAME = 'idx_invoices_user_status');
SET @sql := IF(@i = 0,
  'CREATE INDEX idx_invoices_user_status ON invoices (user_id, status)',
  'SELECT "idx_invoices_user_status already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. users: حذف index تکراری mobile
--    (uq_users_mobile قبلاً این کار را پوشش می‌دهد)
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'mobile');
SET @sql := IF(@i > 0,
  'ALTER TABLE users DROP INDEX mobile',
  'SELECT "mobile index already dropped" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 009_indexes.sql
-- =====================================================
