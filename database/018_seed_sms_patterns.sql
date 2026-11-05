-- =====================================================
--  JoorabRabeti - 018_seed_sms_patterns.sql
--  الگوهای SMS مورد استفاده در سرویس‌های پرداخت/فراموشی رمز/سفارش.
--  idempotent: با ON DUPLICATE KEY UPDATE safe است.
-- =====================================================

INSERT INTO settings (`key`, `value`)
VALUES
    ('sms_pattern_password_reset', '2411'),
    ('sms_pattern_order_approved', '2412'),
    ('sms_pattern_order_shipping', '2411')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`);
