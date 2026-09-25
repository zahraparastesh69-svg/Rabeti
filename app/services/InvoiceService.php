<?php

declare(strict_types=1);

require_once ROOT_PATH . '/config/database.php';

final class InvoiceService
{
    /**
     * دریافت فاکتور با کنترل مالکیت.
     *
     * مشتری فقط فاکتورهای خودش را می‌بیند.
     * ادمین می‌تواند فاکتور هر مشتری را مشاهده کند.
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
                i.invoice_number,
                i.order_id AS invoice_order_id,
                i.user_id AS invoice_user_id,

                i.total AS invoice_total,
                i.paid_total AS invoice_paid_total,
                i.status AS invoice_status,
                i.issued_at AS invoice_issued_at,

                o.id AS order_id,
                o.order_number,
                o.status AS order_status,

                o.subtotal AS order_subtotal,
                o.shipping_cost,
                o.shipping_fee,
                o.total AS order_total,

                o.shipping_name,
                o.shipping_mobile,
                o.shipping_province,
                o.shipping_city,
                o.shipping_address,
                o.shipping_postal,
                o.customer_note,

                o.created_at AS order_created_at,
                o.updated_at AS order_updated_at,

                u.id AS customer_id,
                u.full_name AS customer_name,
                u.company_name AS customer_company,
                u.mobile AS customer_mobile

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
     * اطلاعات محصول در خود order_items ذخیره شده
     * و برای نمایش فاکتور نیازی به جدول‌های variant/color/size نیست.
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
                oi.product_id,
                oi.product_code,
                oi.product_name,
                oi.qty,
                oi.unit_price,
                oi.line_total

            FROM order_items oi

            WHERE oi.order_id = ?

            ORDER BY oi.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$orderId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * محاسبه مبالغ فاکتور.
     *
     * subtotal از مجموع line_total اقلام محاسبه می‌شود.
     *
     * مبلغ نهایی مرجع:
     * invoices.total
     */
    public static function calculateTotals(
        array $invoice,
        array $items
    ): array {
        $itemsSubtotal = 0;

        foreach ($items as $item) {
            $itemsSubtotal += (int)($item['line_total'] ?? 0);
        }

        $invoiceTotal = (int)($invoice['invoice_total'] ?? 0);
        $paidTotal    = (int)($invoice['invoice_paid_total'] ?? 0);

        /*
         * اگر total فاکتور موجود باشد،
         * همان مبلغ نهایی مرجع است.
         *
         * در غیر این صورت از مجموع اقلام استفاده می‌کنیم.
         */
        $total = $invoiceTotal > 0
            ? $invoiceTotal
            : $itemsSubtotal;

        $paid = max(0, $paidTotal);

        /*
         * مانده نباید منفی نمایش داده شود.
         */
        $remaining = max(0, $total - $paid);

        return [
            'subtotal'  => $itemsSubtotal,
            'total'     => $total,
            'paid'      => $paid,
            'remaining' => $remaining,
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
            default   => 'نامشخص',
        };
    }

    /**
     * وضعیت سفارش.
     */
    public static function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending_review' => 'در انتظار بررسی',
            'processing'     => 'در حال پردازش',
            'packed'         => 'بسته‌بندی شده',
            'shipped'        => 'ارسال شده',
            'delivered'      => 'تحویل شده',
            'cancelled'      => 'لغو شده',
            default          => 'نامشخص',
        };
    }

    /**
     * فرمت مبلغ به تومان.
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
     * تاریخ صدور فاکتور.
     *
     * منبع واقعی دیتابیس:
     * invoices.issued_at
     *
     * فعلاً تاریخ میلادی با ارقام فارسی نمایش داده می‌شود.
     * اتصال به Time/Jalali را می‌توان در مرحله بعد انجام داد.
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

    /**
     * ساعت.
     */
    public static function timeFa(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '—';
        }

        return self::faDigits(
            date('H:i', $timestamp)
        );
    }

    /**
     * وضعیت پرداخت بر اساس مبلغ پرداخت‌شده.
     *
     * این متد برای نمایش امن‌تر وضعیت نیز قابل استفاده است.
     */
    public static function paymentState(
        int $total,
        int $paid
    ): string {
        if ($total <= 0) {
            return 'unpaid';
        }

        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }
}
