<?php

/**
 * helpers.php — توابع کمکی مشترک
 *
 * این فایل یک‌بار در bootstrap.php require می‌شود و در تمام صفحات
 * قابل استفاده است.
 */

if (!function_exists('current_user_group')) {
    /**
     * گروه مشتری کاربر وارد‌شده را برمی‌گرداند.
     *
     * اولویت:
     *   ۱. users.customer_group (اگر مشتری لاگین است)
     *   ۲. companies.customer_group (اگر مشتری به شرکت وصل است و users خالی است)
     *   ۳. 'regular' (پیش‌فرض — برای مهمان یا ادمین)
     *
     * @return string یکی از 'regular' | 'bronze' | 'silver' | 'gold'
     */
    function current_user_group(): string
    {
        if (!is_logged_in()) {
            return 'regular';
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? null;
        if (!$userId) {
            return 'regular';
        }

        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $pdo = db();

        try {
            // ۱) users.customer_group (اولویت اول)
            $stmt = $pdo->prepare("
                SELECT customer_group
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$userId]);
            $userGroup = $stmt->fetchColumn();

            if ($userGroup !== false && $userGroup !== null && $userGroup !== '') {
                $cache = (string)$userGroup;
                return $cache;
            }

            // ۲) companies.customer_group (fallback)
            $stmt = $pdo->prepare("
                SELECT c.customer_group
                FROM users u
                INNER JOIN company_users cu ON cu.user_id = u.id
                INNER JOIN companies c ON c.id = cu.company_id
                WHERE u.id = ?
                ORDER BY c.id DESC
                LIMIT 1
            ");
            $stmt->execute([(int)$userId]);
            $row = $stmt->fetch();

            if ($row && !empty($row['customer_group'])) {
                $cache = (string)$row['customer_group'];
                return $cache;
            }
        } catch (Throwable $e) {
            error_log('current_user_group failed: ' . $e->getMessage());
        }

        // ۳) پیش‌فرض
        $cache = 'regular';
        return $cache;
    }
}
if (!function_exists('unit_label')) {
    /**
     * برچسب فارسی واحد فروش.
     * 
     * @param string $unit کد واحد: 'جفتی' | 'بسته' | 'جین'
     * @return string
     */
    function unit_label(string $unit): string
    {
        $map = [
            'جفتی' => 'جفت',
            'بسته' => 'بسته',
            'جین'  => 'جین',
        ];
        return $map[$unit] ?? 'عدد';
    }
}

if (!function_exists('unit_label_from_product')) {
    /**
     * برچسب واحد از product_id (با کوئری).
     */
    function unit_label_from_product(PDO $db, int $productId): string
    {
        static $cache = [];
        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        try {
            $stmt = $db->prepare("
                SELECT spec_value FROM product_specs
                WHERE product_id = ? AND spec_key = 'واحد سفارش'
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $unit = (string)($stmt->fetchColumn() ?: 'جفتی');
        } catch (Throwable $e) {
            $unit = 'جفتی';
        }

        $cache[$productId] = unit_label($unit);
        return $cache[$productId];
    }
}
if (!function_exists('resolve_customer_group')) {
    /**
     * گروه مشتری را برای یک user_id مشخص برمی‌گرداند.
     *
     * @param int $userId
     * @return string یکی از 'regular' | 'bronze' | 'silver' | 'gold'
     */
    function resolve_customer_group(int $userId): string
    {
        if ($userId < 1) {
            return 'regular';
        }

        $pdo = db();

        try {
            // ۱) users.customer_group
            $stmt = $pdo->prepare("
                SELECT customer_group
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $userGroup = $stmt->fetchColumn();

            if ($userGroup !== false && $userGroup !== null && $userGroup !== '') {
                return (string)$userGroup;
            }

            // ۲) companies.customer_group (fallback)
            $stmt = $pdo->prepare("
                SELECT c.customer_group
                FROM company_users cu
                INNER JOIN companies c ON c.id = cu.company_id
                WHERE cu.user_id = ?
                ORDER BY c.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row && !empty($row['customer_group'])) {
                return (string)$row['customer_group'];
            }
        } catch (Throwable $e) {
            error_log('resolve_customer_group failed: ' . $e->getMessage());
        }

        return 'regular';
    }
}
if (!function_exists('customer_group_code')) {
    /**
     * کد گروه مشتری فعلی (بدون کوئری اضافی اگر لاگین نبود).
     */
    function customer_group_code(): string
    {
        return current_user_group();
    }
}

if (!function_exists('resolve_company_id')) {
    /**
     * company_id مرتبط با یک user_id را برمی‌گرداند.
     *
     * @param int $userId
     * @return int|null
     */
    function resolve_company_id(int $userId): ?int
    {
        if ($userId < 1) {
            return null;
        }
        $pdo = db();
        try {
            $stmt = $pdo->prepare("
                SELECT cu.company_id
                FROM company_users cu
                WHERE cu.user_id = ?
                ORDER BY cu.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('inventory_state')) {
    /**
     * وضعیت موجودی یک محصول را برمی‌گرداند:
     *   qty_physical, reserved, unsellable, available_for_sale
     *
     * available_for_sale = qty_physical - reserved - unsellable
     *
     * @param int $productId
     * @return array{qty_physical:int, reserved:int, unsellable:int, available_for_sale:int}
     */
    function inventory_state(int $productId): array
    {
        $pdo = db();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(qty_physical, 0) AS qty_physical,
                    COALESCE(reserved, 0)     AS reserved,
                    COALESCE(unsellable, 0)    AS unsellable
                FROM inventory
                WHERE product_id = ?
            ");
            $stmt->execute([$productId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            // ستون‌های جدید هنوز migrate نشده — fallback به qty_available
            try {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(qty_available, 0) AS qty_physical
                    FROM inventory WHERE product_id = ?
                ");
                $stmt->execute([$productId]);
                $row = $stmt->fetch();
            } catch (Throwable $e2) {
                $row = null;
            }
            if (!$row) {
                return [
                    'qty_physical'        => 0,
                    'reserved'            => 0,
                    'unsellable'          => 0,
                    'available_for_sale' => 0,
                ];
            }
            $row['reserved']   = 0;
            $row['unsellable'] = 0;
        }

        $phys = (int)($row['qty_physical'] ?? 0);
        $res  = (int)($row['reserved'] ?? 0);
        $uns  = (int)($row['unsellable'] ?? 0);
        $avail = max(0, $phys - $res - $uns);

        return [
            'qty_physical'        => $phys,
            'reserved'            => $res,
            'unsellable'          => $uns,
            'available_for_sale' => $avail,
        ];
    }
}

if (!function_exists('log_stock_movement')) {
    /**
     * ثبت یک گردش موجودی.
     *
     * @param array{
     *   product_id:int,
     *   movement_type:string,
     *   quantity:int,
     *   reference_type?:string,
     *   reference_id?:int,
     *   order_id?:int,
     *   order_item_id?:int,
     *   reason?:string,
     *   created_by?:int,
     *   ip?:string
     * } $m
     * @return bool  true اگر ثبت شد
     */
    function log_stock_movement(array $m): bool
    {
        $pdo = db();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements
                (product_id, movement_type, quantity,
                 reference_type, reference_id,
                 order_id, order_item_id, reason,
                 created_by, ip)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $ok = $stmt->execute([
                (int)$m['product_id'],
                (string)$m['movement_type'],
                (int)$m['quantity'],
                $m['reference_type'] ?? null,
                $m['reference_id'] ?? null,
                $m['order_id'] ?? null,
                $m['order_item_id'] ?? null,
                $m['reason'] ?? null,
                $m['created_by'] ?? null,
                $m['ip'] ?? null,
            ]);
            return $ok;
        } catch (Throwable $e) {
            error_log('stock_movement log failed: ' . $e->getMessage());
            return false;
        }
    }
}
if (!function_exists('get_setting')) {
    /**
     * خواندن یک تنظیم از جدول settings.
     */
    function get_setting(string $key, $default = null)
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $stmt = db()->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            $cache[$key] = $v !== false ? $v : $default;
            return $cache[$key];
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('record_audit')) {
    /**
     * ثبت یک رکورد در audit_logs.
     *
     * @param string      $action   نام عملیات (مثلاً 'order.status.change')
     * @param string|null $entity   نام موجودیت (مثلاً 'order')
     * @param int|null    $entityId
     * @param array|null  $context  داده‌های اضافی (به‌صورت JSON در context ذخیره می‌شود)
     *
     * نمونه‌های context:
     *   ['old_status' => 'pending', 'new_status' => 'processing']
     *   ['old_price' => 50000, 'new_price' => 45000, 'reason' => 'تخفیف فصلی']
     *   ['qty' => 100, 'movement_type' => 'stock_in']
     *
     * اگر context شامل 'reason' باشد، آن به‌صورت جداگانه در فیلد reason ذخیره می‌شود.
     * بقیهٔ context به‌صورت JSON در فیلد context ذخیره می‌شود.
     */
    function record_audit(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $context = null
    ): void {
        $userId = $_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // استخراج reason از context (در صورت وجود)
        $reason = null;
        $ctxJson = null;
        if ($context !== null) {
            $ctx = $context;
            if (isset($ctx['reason']) && is_string($ctx['reason'])) {
                $reason = mb_substr($ctx['reason'], 0, 500);
                unset($ctx['reason']);
            }
            // اگر دادهٔ اضافی باقی ماند، به‌صورت JSON ذخیره کن
            if (!empty($ctx)) {
                $ctxJson = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        try {
            // اگر ستون‌های reason/context وجود دارند (migration 019 اجرا شده)
            $stmt = db()->prepare("
                INSERT INTO audit_logs
                (user_id, action, reason, context, entity, entity_id, ip)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $reason,
                $ctxJson,
                $entity,
                $entityId,
                $ip,
            ]);
        } catch (Throwable $e) {
            // fallback اگر migration 019 هنوز اجرا نشده (ستون‌های reason/context نیستند)
            try {
                // در fallback قدیمی: context را با CONCAT در action ذخیره می‌کردیم
                // اکنون: اگر context غیر خالی است، اولین مورد را به action اضافه می‌کنیم
                $actionWithCtx = $action;
                if ($reason !== null) {
                    $actionWithCtx .= ': ' . $reason;
                }

                $stmt = db()->prepare("
                    INSERT INTO audit_logs
                    (user_id, action, entity, entity_id, ip)
                    VALUES (?,?,?,?,?)
                ");
                $stmt->execute([
                    $userId,
                    mb_substr($actionWithCtx, 0, 60),
                    $entity,
                    $entityId,
                    $ip,
                ]);
            } catch (Throwable $e2) {
                error_log('audit log failed (fallback): ' . $e2->getMessage());
            }
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════
 |  نمایش تاریخ شمسی (جلالی) — سراسری
 |
 |  این دو تابع یک‌بار در bootstrap.php لود می‌شوند و در همهٔ صفحات
 |  ادمین و سایت در دسترس‌اند. الگوریتم همان الگوریتم امتحان‌شدهٔ
 |  PdfService::toJalali / fin_jalali است (میلادی → جلالی).
 ═══════════════════════════════════════════════════════════════════ */

if (!function_exists('fa_digits')) {
    /**
     * ارقام لاتین را به فارسی تبدیل می‌کند.
     */
    function fa_digits(string|int|float|null $value): string
    {
        return strtr((string)$value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}

if (!function_exists('jdate_fa')) {
    /**
     * تاریخ/زمان مای‌اس‌کیو‌ال (میلادی) را به رشتهٔ شمسی فارسی تبدیل می‌کند.
     *
     *   jdate_fa('2026-11-05')             → «۱۴۰۵/۰۸/۱۴»
     *   jdate_fa('2026-11-05 14:30:00', true) → «۱۴۰۵/۰۸/۱۴ - ۱۴:۳۰»
     *   jdate_fa(null)                     → «—»
     *
     * @param string|null $mysqlDate مقدار ستون DATETIME/DATE/التیمستامپ
     * @param bool        $withTime  نمایش ساعت بعد از تاریخ
     */
    function jdate_fa(?string $mysqlDate, bool $withTime = false): string
    {
        if ($mysqlDate === null || trim($mysqlDate) === ''
            || substr($mysqlDate, 0, 10) === '0000-00-00') {
            return '—';
        }

        $ts = strtotime($mysqlDate);
        if ($ts === false) {
            return '—';
        }

        $gy = (int)date('Y', $ts);
        $gm = (int)date('n', $ts);
        $gd = (int)date('j', $ts);

        $g = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $jy  = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        $gy2 = ($gm > 2) ? $gy + 1 : $gy;

        $d = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $g[$gm - 1];

        $jy += 33 * intdiv($d, 12053);
        $d %= 12053;
        $jy += 4 * intdiv($d, 1461);
        $d %= 1461;

        if ($d > 365) {
            $jy += intdiv($d - 1, 365);
            $d = ($d - 1) % 365;
        }

        $jm = ($d < 186) ? 1 + intdiv($d, 31) : 7 + intdiv($d - 186, 30);
        $jd = 1 + (($d < 186) ? $d % 31 : ($d - 186) % 30);

        $out = $jy . '/' . str_pad((string)$jm, 2, '0', STR_PAD_LEFT)
             . '/' . str_pad((string)$jd, 2, '0', STR_PAD_LEFT);

        if ($withTime) {
            $out .= ' - ' . date('H:i', $ts);
        }

        return fa_digits($out);
    }
}


if (!function_exists('private_file_url')) {
    /** آدرس سرو امن برای فایل‌های خصوصی (فیش، کارت ملی، رسید سفارش اختصاصی). */
    function private_file_url(?string $path): string
    {
        return '/file?p=' . rawurlencode(ltrim(str_replace('\\', '/', (string)$path), '/'));
    }
}
