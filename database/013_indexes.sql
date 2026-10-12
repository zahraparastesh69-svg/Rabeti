-- =====================================================
--  JoorabRabeti - 013_indexes.sql
--  Performance indexes for B2B wholesale queries
-- =====================================================

-- -----------------------------------------------------
--  1. products: filter active products by category
-- -----------------------------------------------------
CREATE INDEX idx_products_active_cat 
    ON products (is_active, category_id);

-- -----------------------------------------------------
--  2. product_images: get main image per product
-- -----------------------------------------------------
CREATE INDEX idx_product_images_main 
    ON product_images (product_id, is_main);

-- -----------------------------------------------------
--  3. invoices: customer statements by status
-- -----------------------------------------------------
CREATE INDEX idx_invoices_user_status 
    ON invoices (user_id, status);

-- -----------------------------------------------------
--  4. users: drop duplicate mobile index
--     (uq_users_mobile already covers this)
-- -----------------------------------------------------
ALTER TABLE users DROP INDEX mobile;

-- =====================================================
--  End of 013_indexes.sql
-- =====================================================