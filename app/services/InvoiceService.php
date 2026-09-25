<?php

declare(strict_types=1);

require_once ROOT_PATH . '/config/database.php';

final class InvoiceService
{
    /**
     * دریافت فاکتور با کنترل مالکیت.
     *
     * اگر $isAdmin برابر true باشد، فاکتور بدون محدودیت user_id
     * برای پنل مدیریت قابل دریافت است.
     */
    public static function find(
        string $invoiceNumber,
        int $userId,
        bool $isAdmin = false
    ): ?array {
        $invoiceNumber = trim($invoiceNumber);

        if ($invoiceNumber === '') {
            return null;
        }

        $db = db();

        $sql = "
            SELECT
                i.id AS invoice_id,
                i.order_id AS invoice_order_id,
                i.user_id AS invoice_user_id,
                i.invoice_number,
                i.issue_date,
                i.due_date,
                i.total AS invoice_total,
                i.tax AS invoice_tax,
                i.discount AS invoice_discount,
                i.status AS invoice_status,
                i.pdf_path,

                o.id AS order_id,
                o.order_number,
                o.status AS order_status,
                o.shipping_method_id,
                o.shipping_time_id,
                o.total_amount AS order_total_amount,
                o.note AS order_note,
                o.is_custom_order,
                o.created_at AS order_created_at,

                u.id AS customer_id,
                u.full_name AS customer_name,
                u.phone AS customer_mobile,
                u.company_name AS customer_company

            FROM invoices i

            INNER JOIN orders o
                ON o.id = i.order_id

            INNER JOIN users u
                ON u.id = i.user_id

            WHERE i.invoice_number = ?
        ";

        $params = [$invoiceNumber];

        if (!$isAdmin) {
            $sql .= " AND i.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = self::getItems(
            (int)$invoice['order_id']
        );

        $invoice['totals'] = self::calculateTotals(
            $invoice,
            $invoice['items']
        );

        return $invoice;
    }

    /**
     * دریافت اقلام فاکتور.
     *
     * ساختار order_items طبق schema:
     * id, order_id, size_id, qty, unit_price, subtotal
     *
     * برای نمایش بهتر، اطلاعات سایز/رنگ/واریانت نیز در صورت وجود
     * از روابط محصولات خوانده می‌شود.
     */
    public static function getItems(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $db = db();

        $sql = "
            SELECT
                oi.id,
                oi.order_id,
                oi.size_id,
                oi.qty,
                oi.unit_price,
                oi.subtotal,

                ps.size_label,

                pc.color_name,
                pc.color_hex,

                pv.name AS variant_name,

                p.id AS product_id,
                p.name AS product_name,
                p.code AS product_code

            FROM order_items oi

            LEFT JOIN product_sizes ps
                ON ps.id = oi.size_id

            LEFT JOIN product_colors pc
                ON pc.id = ps.color_id

            LEFT JOIN product_variants pv
                ON pv.id = pc.variant_id

            LEFT JOIN products p
                ON p.id = pv.product_id

            WHERE oi.order_id = ?

            ORDER BY oi.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$orderId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * محاسبه نهایی مبالغ.
     *
     * مبلغ اقلام از order_items گرفته می‌شود.
     * تخفیف و مالیات از invoices گرفته می‌شود.
     */
    public static function calculateTotals(
        array $invoice,
        array $items
    ): array {
        $itemsSubtotal = 0;

        foreach ($items as $item) {
            $itemsSubtotal += (float)($item['subtotal'] ?? 0);
        }

        $discount = (float)($invoice['invoice_discount'] ?? 0);
        $tax      = (float)($invoice['invoice_tax'] ?? 0);
        $total    = (float)($invoice['invoice_total'] ?? 0);

        /*
         * اگر total فاکتور در دیتابیس معتبر باشد همان مرجع نهایی است.
         * subtotal برای نمایش قبل از تخفیف/مالیات محاسبه می‌شود.
         */
        if ($total <= 0) {
            $total = max(0, $itemsSubtotal - $discount + $tax);
        }

        return [
            'subtotal' => $itemsSubtotal,
            'discount' => $discount,
            'tax'      => $tax,
            'total'    => $total,
        ];
    }

    /**
     * کنترل مالکیت فاکتور.
     */
    public static function authorize(
        array $invoice,
        int $userId,
        bool $isAdmin = false
    ): bool {
        if ($isAdmin) {
            return true;
        }

        return (int)($invoice['invoice_user_id'] ?? 0) === $userId;
    }

    /**
     * وضعیت فاکتور.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid'    => 'پرداخت‌شده',
            'partial' => 'پرداخت جزئی',
            'unpaid'  => 'پرداخت‌نشده',
            'void'    => 'باطل',
            default   => 'در انتظار بررسی',
        };
    }

    /**
     * وضعیت سفارش.
     */
    public static function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'در انتظار بررسی',
            'confirmed' => 'تأیید شده',
            'producing' => 'در حال آماده‌سازی',
            'shipped'   => 'ارسال شده',
            'done'      => 'تکمیل شده',
            default     => 'نامشخص',
        };
    }

    /**
     * فرمت مبلغ.
     */
    public static function money(float|int|string $amount): string
    {
        return number_format(
            (float)$amount,
            0,
            '.',
            ','
        ) . ' تومان';
    }

    /**
     * تبدیل ارقام انگلیسی به فارسی.
     */
    public static function faDigits(string $value): string
    {
        return strtr(
            $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
                ',' => '٬',
            ]
        );
    }

    /**
     * تاریخ میلادی دیتابیس → نمایش فارسی.
     *
     * برای جلوگیری از وابستگی اجباری به کتابخانه جلالی،
     * فعلاً تاریخ خام را با ارقام فارسی نمایش می‌دهیم.
     *
     * اگر Time پروژه تبدیل جلالی داشته باشد، در مرحله بعد
     * این متد به Time::toJalali متصل می‌شود.
     */
    public static function dateFa(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return self::faDigits($date);
        }

        return self::faDigits(
            date('Y/m/d', $timestamp)
        );
    }
}
