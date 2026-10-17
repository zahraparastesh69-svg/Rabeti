<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * خواندن تنظیمات کسب‌وکار از DB
 *
 * همه پارامترها از جدول settings خوانده می‌شوند
 * و در صورت نبودن مقدار، مقدار پیش‌فرض استفاده می‌شود.
 *
 * مثال:
 *
 * Setting::get('ship_fee_peyk_tehran', '80000');
 * Setting::getInt('ship_window_days', 3);
 */
final class Setting
{
    private static ?array $cache = null;

    /**
     * بارگذاری تنظیمات از DB
     */
    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];

        try {

            foreach (
                db()->query(
                    "SELECT `key`, `value`
                     FROM settings"
                ) as $row
            ) {

                self::$cache[(string)$row['key']] = (string)$row['value'];
            }
        } catch (Throwable $e) {

            /*
             * اگر جدول settings هنوز ساخته نشده باشد،
             * مقادیر پیش‌فرض استفاده خواهند شد.
             */
        }
    }

    /**
     * دریافت مقدار متنی
     */
    public static function get(
        string $key,
        string $default = ''
    ): string {

        self::load();

        return self::$cache[$key] ?? $default;
    }

    /**
     * دریافت مقدار عددی
     */
    public static function getInt(
        string $key,
        int $default
    ): int {

        $value = self::get(
            $key,
            ''
        );

        return (
            $value !== ''
            && is_numeric($value)
        )
            ? (int)$value
            : $default;
    }
    /**
     * ذخیره مقدار (INSERT یا UPDATE)
     */
    public static function set(
        string $key,
        string $value
    ): void {

        $stmt = db()->prepare("
            INSERT INTO settings (`key`, `value`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `updated_at` = NOW()
        ");

        $stmt->execute([$key, $value]);

        /* بروزرسانی cache */
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /**
     * دریافت مقدار boolean
     */
    public static function getBool(
        string $key,
        bool $default = false
    ): bool {

        $value = self::get($key, '');

        if ($value === '') {
            return $default;
        }

        return in_array(
            strtolower($value),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }
}
