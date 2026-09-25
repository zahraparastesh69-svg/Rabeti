<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * خواندن تنظیمات کسب‌وکار از DB — همه پارامترها از پنل قابل تغییر
 * Setting::get('ship_fee_peyk_tehran', 80000)
 */
class Setting
{
    private static ?array $cache = null;

    private static function load(): void
    {
        if (self::$cache !== null) return;
        self::$cache = [];
        try {
            foreach (db()->query("SELECT `key`, `value` FROM settings") as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) {
            // جدول هنوز نیست — مقادیر پیش‌فرض استفاده می‌شود
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function getInt(string $key, int $default): int
    {
        $v = self::get($key, '');
        return ($v !== '' && is_numeric($v)) ? (int)$v : $default;
    }
}
