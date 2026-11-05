-- =====================================================
--  JoorabRabeti - 014_order_status_split.sql
--  تفکیک orders.status (تک‌فیلد ترکیبی) به سه فیلد مستقل:
--    payment_status    — وضعیت پرداخت
--    fulfillment_status — وضعیت تأمین/تولید
--    shipping_status   — وضعیت ارسال
--
--  ستون قدیمی status به‌عنوان وضعیت کلی سفارش حفظ می‌شود
--  (backward compatibility) اما منطق کد جدید از سه ستون مستقل
--  استفاده خواهد کرد.
--
--  نگاشت مقادیر فعلی:
--    pending_review  → payment=unpaid, fulfillment=pending,       shipping=not_ready
--    processing      → payment=partial|paid, fulfillment=processing, shipping=not_ready
--    packed          → payment=paid|partial,    fulfillment=packed,  shipping=ready
--    shipped-pik     → payment=paid,             fulfillment=completed, shipping=in_transit (peyk)
--    shipped-post    → payment=paid,             fulfillment=completed, shipping=in_transit (post)
--    shipped         → payment=paid,             fulfillment=completed, shipping=in_transit
--    delivered       → payment=paid,             fulfillment=completed, shipping=delivered
--    cancelled       → payment=void,             fulfillment=cancelled, shipping=not_applicable
--
--  نکته: برای عدم از دست رفتن دادهٔ قدیمی، mapping بر اساس وضعیت فعلی
--  و وضعیت invoice (paid/partial/unpaid) انجام می‌شود.
-- =====================================================

-- 1. افزودن payment_status
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_status');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN payment_status ENUM('unpaid','partial','paid','overpaid','refunded_partially','refunded_fully','void','pending_verification') NOT NULL DEFAULT 'unpaid' AFTER status",
  'SELECT "payment_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. افزودن fulfillment_status
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'fulfillment_status');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN fulfillment_status ENUM('pending','processing','packed','completed','cancelled','needs_production','partial_supply') NOT NULL DEFAULT 'pending' AFTER payment_status",
  'SELECT "fulfillment_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. افزودن shipping_status
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_status');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN shipping_status ENUM('not_ready','ready','packing','in_transit','delivered','failed','returned','not_applicable') NOT NULL DEFAULT 'not_ready' AFTER fulfillment_status",
  'SELECT "shipping_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. افزودن shipping_method (برای حفظ peyk/post/in_person که قبلاً در status کدگذاری شده بود)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_method');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN shipping_method ENUM('peyk','post','in_person') NULL DEFAULT NULL AFTER shipping_status",
  'SELECT "shipping_method exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. افزودن cancelled_at (نقطهٔ زمانی لغو)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cancelled_at');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN cancelled_at DATETIME NULL DEFAULT NULL AFTER updated_at",
  'SELECT "cancelled_at exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. افزودن cancellation_reason
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cancellation_reason');
SET @sql := IF(@c = 0,
  "ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(500) NULL DEFAULT NULL AFTER cancelled_at",
  'SELECT "cancellation_reason exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7. مهاجرت داده — mapping از status قدیمی به سه ستون جدید
--    با JOIN invoices برای تشخیص وضعیت پرداخت واقعی

UPDATE orders o
LEFT JOIN invoices i ON i.order_id = o.id
SET o.payment_status = CASE
    WHEN o.status = 'cancelled' THEN 'void'
    WHEN o.status = 'delivered' THEN 'paid'
    WHEN o.status IN ('shipped','shipped-pik','shipped-post') THEN 'paid'
    WHEN o.status = 'packed' AND i.paid_total >= o.total AND o.total > 0 THEN 'paid'
    WHEN o.status = 'packed' AND i.paid_total > 0 THEN 'partial'
    WHEN o.status = 'processing' AND i.paid_total >= o.total AND o.total > 0 THEN 'paid'
    WHEN o.status = 'processing' AND i.paid_total > 0 THEN 'partial'
    WHEN o.status = 'pending_review' AND i.paid_total > 0 THEN 'pending_verification'
    ELSE 'unpaid'
END;

UPDATE orders
SET fulfillment_status = CASE
    WHEN status = 'cancelled' THEN 'cancelled'
    WHEN status = 'delivered' THEN 'completed'
    WHEN status IN ('shipped','shipped-pik','shipped-post') THEN 'completed'
    WHEN status = 'packed' THEN 'packed'
    WHEN status = 'processing' THEN 'processing'
    ELSE 'pending'
END;

UPDATE orders
SET shipping_status = CASE
    WHEN status = 'cancelled' THEN 'not_applicable'
    WHEN status = 'delivered' THEN 'delivered'
    WHEN status IN ('shipped','shipped-pik','shipped-post') THEN 'in_transit'
    WHEN status = 'packed' THEN 'ready'
    ELSE 'not_ready'
END;

UPDATE orders
SET shipping_method = CASE
    WHEN status = 'shipped-pik' THEN 'peyk'
    WHEN status = 'shipped-post' THEN 'post'
    ELSE NULL
END;

UPDATE orders
SET cancelled_at = updated_at
WHERE status = 'cancelled' AND cancelled_at IS NULL;

-- 8. ایندکس‌ها برای فیلتر سریع
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
             AND INDEX_NAME = 'idx_orders_payment_status');
SET @sql := IF(@i = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_payment_status (payment_status)',
  'SELECT "idx_orders_payment_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
             AND INDEX_NAME = 'idx_orders_fulfillment_status');
SET @sql := IF(@i = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_fulfillment_status (fulfillment_status)',
  'SELECT "idx_orders_fulfillment_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
             AND INDEX_NAME = 'idx_orders_shipping_status');
SET @sql := IF(@i = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_shipping_status (shipping_status)',
  'SELECT "idx_orders_shipping_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 014_order_status_split.sql
-- =====================================================
