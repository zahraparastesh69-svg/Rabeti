<?php

declare(strict_types=1);

final class InvoiceService
{
    /**
     * پیدا کردن فاکتور + هدر + اقلام + جمع‌ها
     */
    public static function find(PDO $db, int $invoiceId): ?array
    {
        // ستون‌های واقعی invoices: total, paid_total, status, issued_at
        // ستون‌های واقعی orders: order_number, created_at, shipping_name, shipping_mobile
        $stmt = $db->prepare("
    SELECT
        i.id                AS invoice_id,
        i.invoice_number,
        i.issued_at,
        i.total             AS invoice_total,
        i.paid_total        AS paid_total,
        i.status            AS invoice_status,
        i.user_id,

        o.id                AS order_id,
        o.order_number,
        o.created_at        AS order_created_at,

        /* ⭐ گیرنده (از orders) */
        o.shipping_name,
        o.shipping_mobile,
        o.shipping_province,
        o.shipping_city,
        o.shipping_address,
        o.shipping_postal,

        /* ⭐ خریدار (از users) — این‌ها را اضافه کردم */
        u.full_name         AS buyer_full_name,
        u.mobile            AS buyer_mobile,
        u.national_code     AS buyer_national_code,
        u.company_name      AS buyer_company,
        u.economic_code     AS buyer_economic_code,
        u.customer_type     AS buyer_type,

        /* ⭐ مبالغ (از orders) */
        o.subtotal          AS order_subtotal,
        o.discount_total    AS order_discount_total,
        o.shipping_fee      AS order_shipping_fee,
        o.shipping_cost     AS order_shipping_cost,
        o.shipping_method   AS order_shipping_method

    FROM invoices i
    INNER JOIN orders o ON o.id = i.order_id
    LEFT  JOIN users  u ON u.id = i.user_id
    WHERE i.id = ?
    LIMIT 1
");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        $invoice['items']  = self::getItems($db, (int)$invoice['order_id']);
        $invoice['totals'] = self::calculateTotals($invoice, $invoice['items']);

        return $invoice;
    }

    /**
     * اقلام سفارش (با تصویر)
     */
    private static function getItems(PDO $db, int $orderId): array
    {
        $stmt = $db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.product_code,
                oi.product_name,
                oi.qty,
                oi.base_unit_price,
                oi.discount_percent,
                oi.discount_amount,
                oi.unit_price,
                oi.line_total,
                (
                    SELECT spec_value FROM product_specs
                    WHERE product_id = oi.product_id AND spec_key = 'واحد سفارش'
                    LIMIT 1
                ) AS unit_code,
                (
                    SELECT pi.image_path
                    FROM product_images pi
                    WHERE pi.product_id = oi.product_id
                    ORDER BY pi.is_main DESC, pi.sort_order ASC
                    LIMIT 1
                ) AS image
            FROM order_items oi
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * محاسبه جمع‌های فاکتور
     */
    private static function calculateTotals(array $invoice, array $items): array
    {
        $itemsSubtotal = 0;
        $itemsDiscount = 0;
        $itemsTotal    = 0;

        foreach ($items as $item) {
            $qty            = (int)($item['qty'] ?? 0);
            $baseUnitPrice  = (int)($item['base_unit_price'] ?? 0);
            $discountAmount = (int)($item['discount_amount'] ?? 0);
            $lineTotal      = (int)($item['line_total'] ?? 0);

            if ($baseUnitPrice > 0 && $qty > 0) {
                $itemsSubtotal += $baseUnitPrice * $qty;
            } else {
                $itemsSubtotal += $lineTotal + $discountAmount;
            }

            $itemsDiscount += $discountAmount;
            $itemsTotal    += $lineTotal;
        }

        /* ⭐ subtotal: اولویت به orders.subtotal (اگر > 0)، وگرنه از اقلام */
        $orderSubtotal = (int)($invoice['order_subtotal'] ?? 0);
        $subtotal = $orderSubtotal > 0 ? $orderSubtotal : $itemsSubtotal;

        /* ⭐ تخفیف: اولویت به orders.discount_total (اگر > 0)، وگرنه از اقلام */
        $orderDiscount = (int)($invoice['order_discount_total'] ?? 0);
        $discount = $orderDiscount > 0 ? $orderDiscount : $itemsDiscount;

        $afterDiscount = max(0, $subtotal - $discount);

        /* ⭐ هزینه ارسال: از orders.shipping_fee (اگر 0 بود، shipping_cost را امتحان کن) */
        $shipping = (int)($invoice['order_shipping_fee'] ?? 0);
        if ($shipping <= 0) {
            $shipping = (int)($invoice['order_shipping_cost'] ?? 0);
        }

        /* مبلغ نهایی: از invoices.total (اگر > 0)، وگرنه محاسبه */
        $invoiceTotal = (int)($invoice['invoice_total'] ?? 0);
        $total = $invoiceTotal > 0 ? $invoiceTotal : ($afterDiscount + $shipping);

        /* پرداخت‌شده */
        $paid = (int)($invoice['paid_total'] ?? 0);

        $remaining = max(0, $total - $paid);

        /* مالیات */
        $tax = 0;

        return [
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'after_discount' => $afterDiscount,
            'shipping'       => $shipping,
            'tax'            => $tax,
            'total'          => $total,
            'paid'           => $paid,
            'remaining'      => $remaining,
        ];
    }

    /**
     * فرمت مبلغ
     */
    public static function money(int|float $amount): string
    {
        return number_format((float)$amount) . ' تومان';
    }

    /**
     * تبدیل ارقام انگلیسی به فارسی
     */
    public static function faDigits(string $value): string
    {
        return strtr($value, [
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
        ]);
    }
}
