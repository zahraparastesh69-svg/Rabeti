-- =====================================================
--  JoorabRabeti - 013_indexes.sql
--  Performance indexes for B2B wholesale queries
-- =====================================================

-- -----------------------------------------------------
--  1. products: filter active products by category
-- -----------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_active_cat');
SET @sql := IF(@i = 0, 'CREATE INDEX idx_products_active_cat ON products (is_active, category_id)', 'SELECT "idx_products_active_cat already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------
--  2. product_images: get main image per product
-- -----------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_images' AND INDEX_NAME = 'idx_product_images_main');
SET @sql := IF(@i = 0, 'CREATE INDEX idx_product_images_main ON product_images (product_id, is_main)', 'SELECT "idx_product_images_main already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------
--  3. invoices: customer statements by status
-- -----------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'idx_invoices_user_status');
SET @sql := IF(@i = 0, 'CREATE INDEX idx_invoices_user_status ON invoices (user_id, status)', 'SELECT "idx_invoices_user_status already exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------
--  4. users: drop duplicate mobile index
--     (uq_users_mobile already covers this)
-- -----------------------------------------------------
SET @dup := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'mobile');
SET @uq := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_mobile');
SET @sql := IF(@dup > 0 AND @uq > 0, 'ALTER TABLE users DROP INDEX mobile', 'SELECT "users.mobile index drop skipped" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 013_indexes.sql
-- =====================================================