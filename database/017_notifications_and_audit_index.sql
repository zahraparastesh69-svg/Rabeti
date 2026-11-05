-- =====================================================
--  JoorabRabeti - 017_notifications_and_audit_index.sql
--  افزودن ایندکس به audit_logs برای مشاهدهٔ رویدادها
--  و تعریف جدول notifications برای اعلان‌های درون‌برنامه‌ای.
-- =====================================================

-- ایندکس بر اساس created_at (برای صفحهٔ مشاهدهٔ رویدادها)
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
             AND INDEX_NAME = 'idx_audit_created');
SET @sql := IF(@i = 0,
  'ALTER TABLE audit_logs ADD INDEX idx_audit_created (created_at)',
  'SELECT "idx_audit_created exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ایندکس بر اساس action
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
             AND INDEX_NAME = 'idx_audit_action');
SET @sql := IF(@i = 0,
  'ALTER TABLE audit_logs ADD INDEX idx_audit_action (action)',
  'SELECT "idx_audit_action exists" AS msg');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- جدول notifications — اعلان‌های درون‌برنامه‌ای برای ادمین/مشتری
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `audience` ENUM('admin','customer','specific') NOT NULL DEFAULT 'admin',
  `category` VARCHAR(40) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`),
  KEY `idx_notif_audience_read` (`audience`, `is_read`),
  KEY `idx_notif_category` (`category`),
  KEY `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
--  End of 017_notifications_and_audit_index.sql
-- =====================================================
