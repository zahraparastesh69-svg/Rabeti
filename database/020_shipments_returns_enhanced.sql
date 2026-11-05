-- =====================================================
--  JoorabRabeti - 020_shipments_returns_enhanced.sql
--  افزودن ستون‌های ردیابی ارسال و بازگشت کالا.
--
--  این migration ستون‌های زیر را اضافه می‌کند:
--  - order_shipments: tracking_code, carrier, shipped_at, delivered_at, return_reason
--  - orders: shipping_status (در 014 اضافه شده — اینجا فقط به‌روزرسانی)
--  - refund_requests: return_reason, return_status, return_received_at, return_qty
--  - invoices: due_date (برای مطالبات با سررسید)
-- =====================================================

-- === order_shipments ===

-- ۱. tracking_code (کد ردیابی پست/پیک)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'tracking_code');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN tracking_code VARCHAR(100) NULL DEFAULT NULL AFTER fee',
  'SELECT "tracking_code exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۲. carrier (نام شرکت حمل)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'carrier');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN carrier VARCHAR(100) NULL DEFAULT NULL AFTER tracking_code',
  'SELECT "carrier exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۳. shipped_at (زمان واقعی تحویل به شرکت حمل)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'shipped_at');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN shipped_at DATETIME NULL DEFAULT NULL AFTER carrier',
  'SELECT "shipped_at exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۴. delivered_at (زمان تحویل به مشتری)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'delivered_at');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER shipped_at',
  'SELECT "delivered_at exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۵. return_reason (در صورت برگشت کالا)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'return_reason');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN return_reason VARCHAR(500) NULL DEFAULT NULL AFTER delivered_at',
  'SELECT "return_reason exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۶. updated_at برای order_shipments
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_shipments'
             AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@c = 0,
  'ALTER TABLE order_shipments ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
  'SELECT "updated_at exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- === refund_requests ===

-- ۷. return_reason (دلیل درخواست بازگشت)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND COLUMN_NAME = 'return_reason');
SET @sql := IF(@c = 0,
  'ALTER TABLE refund_requests ADD COLUMN return_reason VARCHAR(500) NULL DEFAULT NULL AFTER admin_note',
  'SELECT "return_reason exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۸. return_status (وضعیت بازگشت کالا)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND COLUMN_NAME = 'return_status');
SET @sql := IF(@c = 0,
  "ALTER TABLE refund_requests ADD COLUMN return_status ENUM('not_requested','requested','in_transit','received_sellable','received_unsellable','rejected') NOT NULL DEFAULT 'not_requested' AFTER return_reason",
  'SELECT "return_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۹. return_received_at (زمان دریافت کالای برگشتی در انبار)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND COLUMN_NAME = 'return_received_at');
SET @sql := IF(@c = 0,
  'ALTER TABLE refund_requests ADD COLUMN return_received_at DATETIME NULL DEFAULT NULL AFTER return_status',
  'SELECT "return_received_at exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۱۰. return_qty (تعداد جفت برگشتی)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND COLUMN_NAME = 'return_qty');
SET @sql := IF(@c = 0,
  'ALTER TABLE refund_requests ADD COLUMN return_qty INT UNSIGNED NULL DEFAULT NULL AFTER return_received_at',
  'SELECT "return_qty exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۱۱. order_item_id (ارجاع به قلم سفارش)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND COLUMN_NAME = 'order_item_id');
SET @sql := IF(@c = 0,
  'ALTER TABLE refund_requests ADD COLUMN order_item_id INT UNSIGNED NULL DEFAULT NULL AFTER return_qty',
  'SELECT "order_item_id exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- === invoices ===

-- ۱۲. due_date (سررسید فاکتور برای محاسبهٔ مطالبات)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
             AND COLUMN_NAME = 'due_date');
SET @sql := IF(@c = 0,
  'ALTER TABLE invoices ADD COLUMN due_date DATE NULL DEFAULT NULL AFTER issued_at',
  'SELECT "due_date exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۱۳. مهاجرت داده: set due_date برای فاکتورهای تحویل‌شده
--     بر اساس companies.due_days (اگر صفر یا NULL، سررسید ۱۴ روز پس از صدور)
UPDATE invoices i
INNER JOIN orders o ON o.id = i.order_id
LEFT JOIN (
    SELECT cu.user_id, c.due_days
    FROM company_users cu
    INNER JOIN companies c ON c.id = cu.company_id
) cd ON cd.user_id = i.user_id
SET i.due_date = DATE_ADD(i.issued_at, INTERVAL COALESCE(cd.due_days, 14) DAY)
WHERE i.due_date IS NULL
  AND i.status IN ('unpaid', 'partial');

-- ۱۴. ایندکس برای جستجوی سریع بر اساس due_date
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
             AND INDEX_NAME = 'idx_invoices_due_date');
SET @sql := IF(@i = 0,
  'ALTER TABLE invoices ADD INDEX idx_invoices_due_date (due_date, status)',
  'SELECT "idx_invoices_due_date exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۱۵. ایندکس برای return_status
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
             AND INDEX_NAME = 'idx_rr_return_status');
SET @sql := IF(@i = 0,
  'ALTER TABLE refund_requests ADD INDEX idx_rr_return_status (return_status, status)',
  'SELECT "idx_rr_return_status exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 020_shipments_returns_enhanced.sql
-- =====================================================
