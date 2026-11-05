-- =====================================================
--  JoorabRabeti - 019_audit_log_reason.sql
--  افزودن ستون reason به audit_logs برای ذخیرهٔ زمینهٔ عملیات
--  (قبلاً context با CONCAT در action ذخیره می‌شد — اکنون به‌صورت فیلد جدا).
--
--  نکته: برای backward compat، context قبلی در action باقی می‌ماند
--  ولی record_audit جدید هم reason را به‌صورت جداگانه می‌نویسد.
-- =====================================================

-- ۱. افزودن ستون reason
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND COLUMN_NAME = 'reason'
);

SET @sql := IF(@col = 0,
  'ALTER TABLE audit_logs ADD COLUMN reason VARCHAR(500) NULL DEFAULT NULL AFTER action',
  'SELECT "reason already exists" AS msg'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۲. افزودن ستون context (JSON برای داده‌های ساختاریافته)
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND COLUMN_NAME = 'context'
);

SET @sql := IF(@col = 0,
  'ALTER TABLE audit_logs ADD COLUMN context JSON NULL DEFAULT NULL AFTER reason',
  'SELECT "context already exists" AS msg'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ۳. ایندکس برای جستجوی سریع بر اساس reason
SET @i := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND INDEX_NAME = 'idx_audit_reason'
);

SET @sql := IF(@i = 0,
  'ALTER TABLE audit_logs ADD INDEX idx_audit_reason (reason(100))',
  'SELECT "idx_audit_reason already exists" AS msg'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
--  End of 019_audit_log_reason.sql
-- =====================================================
