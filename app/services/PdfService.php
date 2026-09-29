<?php

declare(strict_types=1);

final class PdfService
{
    private static bool $loaded = false;

    /**
     * Load TCPDF from Composer.
     */
    private static function loadTcpdf(): void
    {
        if (self::$loaded) {
            return;
        }

        $autoload = ROOT_PATH . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException(
                'Composer autoload پیدا نشد.'
            );
        }

        require_once $autoload;

        if (!class_exists('TCPDF')) {
            throw new RuntimeException(
                'TCPDF در Composer نصب نشده است.'
            );
        }

        self::$loaded = true;
    }

    /**
     * Generate invoice PDF and force download.
     */
    public static function output(array $invoice): void
    {
        self::loadTcpdf();

        $pdf = new TCPDF(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $pdf->SetCreator('RABETI');
        $pdf->SetAuthor('رابطی');
        $pdf->SetTitle(
            'فاکتور ' .
                (string)($invoice['invoice_number'] ?? '')
        );

        $pdf->SetSubject('فاکتور فروش');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->setRTL(true);

        /*
         * A4 margins
         */
        $pdf->SetMargins(
            9,
            8,
            9
        );

        $pdf->SetAutoPageBreak(
            true,
            8
        );

        /*
         * Persian-compatible font.
         */
        $pdf->SetFont(
            'dejavusans',
            '',
            8
        );

        $pdf->AddPage();

        $html = self::buildHtml($invoice);

        $pdf->writeHTML(
            $html,
            true,
            false,
            true,
            false,
            ''
        );

        /*
         * Safe filename
         */
        $invoiceNumber = (string)(
            $invoice['invoice_number']
            ?? 'invoice'
        );

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '',
            $invoiceNumber
        );

        if ($safeNumber === '') {
            $safeNumber = 'invoice';
        }

        $filename =
            'RABETI-invoice-' .
            $safeNumber .
            '.pdf';

        /*
         * D = force download
         */
        $pdf->Output(
            $filename,
            'D'
        );
    }


    /**
     * Build invoice HTML.
     */
    private static function buildHtml(
        array $invoice
    ): string {

        /*
         * Escape
         */
        $e = static function ($value): string {

            return htmlspecialchars(
                (string)$value,
                ENT_QUOTES |
                    ENT_SUBSTITUTE,
                'UTF-8'
            );
        };


        /*
         * Persian number formatting
         */
        $faDigits = static function (
            string $value
        ): string {

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
                ]
            );
        };


        /*
         * Number
         */
        $number = static function (
            $value
        ) use ($faDigits): string {

            return $faDigits(
                number_format(
                    (int)$value,
                    0,
                    '.',
                    ','
                )
            );
        };


        /*
         * Money
         */
        $money = static function (
            $value
        ) use ($number): string {

            return $number($value) .
                ' تومان';
        };


        /*
         * =====================================================
         * Invoice data
         * =====================================================
         */

        $invoiceNumber = (string)(
            $invoice['invoice_number']
            ?? '—'
        );

        $orderNumber = (string)(
            $invoice['order_number']
            ?? '—'
        );

        $customerName = (string)(
            $invoice['shipping_name']
            ?? $invoice['customer_name']
            ?? '—'
        );

        $customerMobile = (string)(
            $invoice['shipping_mobile']
            ?? $invoice['customer_mobile']
            ?? '—'
        );

        $province = trim(
            (string)(
                $invoice['shipping_province']
                ?? ''
            )
        );

        $city = trim(
            (string)(
                $invoice['shipping_city']
                ?? ''
            )
        );

        $address = trim(
            (string)(
                $invoice['shipping_address']
                ?? ''
            )
        );

        $postal = trim(
            (string)(
                $invoice['shipping_postal']
                ?? ''
            )
        );


        /*
         * =====================================================
         * Date
         * =====================================================
         */

        $issuedAt =
            $invoice['invoice_issued_at']
            ?? $invoice['order_created_at']
            ?? null;

        $issuedDate = '—';
        $issuedTime = '—';

        if ($issuedAt) {

            $timestamp = strtotime(
                (string)$issuedAt
            );

            if ($timestamp !== false) {

                $issuedDate =
                    $faDigits(
                        date(
                            'Y/m/d',
                            $timestamp
                        )
                    );

                $issuedTime =
                    $faDigits(
                        date(
                            'H:i',
                            $timestamp
                        )
                    );
            }
        }


        /*
         * =====================================================
         * Status
         * =====================================================
         */

        $orderStatus = (string)(
            $invoice['status']
            ?? $invoice['order_status']
            ?? '—'
        );


        /*
         * =====================================================
         * Totals
         * =====================================================
         */

        $totals = $invoice['totals'] ?? [];

        $subtotal = (int)($totals['subtotal'] ?? 0);

        /* فیکس: مستقیم از ردیف invoices */
        $total = (int)($invoice['total'] ?? $invoice['invoice_total'] ?? 0);
        $paid  = (int)($invoice['paid_total'] ?? $invoice['invoice_paid_total'] ?? 0);

        /* بستانکاری هم محاسبه می‌شود — بدون max(0) */
        $remaining = $total - $paid;

        /*
         * =====================================================
         * Logo
         * =====================================================
         */

        $logoPath =
            ROOT_PATH .
            '/public/Image/logo3.jpg';

        $logoHtml = '';

        if (is_file($logoPath)) {

            $logoPathForPdf =
                str_replace(
                    '\\',
                    '/',
                    $logoPath
                );

            $logoHtml = '
                <img
                    src="' .
                $e($logoPathForPdf) .
                '"
                    width="58"
                    height="58"
                    style="
                        max-width:58mm;
                        max-height:58mm;
                    "
                >
            ';
        }


        /*
         * =====================================================
         * Customer address
         * =====================================================
         */

        $addressParts = array_filter(
            [
                $province,
                $city,
                $address,
            ],
            static function ($value) {
                return trim(
                    (string)$value
                ) !== '';
            }
        );

        $fullAddress = implode(
            '، ',
            $addressParts
        );

        if ($postal !== '') {

            $fullAddress .=
                ($fullAddress !== '' ? ' — ' : '') .
                'کد پستی: ' .
                $postal;
        }

        if ($fullAddress === '') {
            $fullAddress = '—';
        }


        /*
         * =====================================================
         * Items
         * =====================================================
         */

        $items =
            $invoice['items']
            ?? [];

        $itemsHtml = '';

        foreach (
            $items as $index => $item
        ) {

            $productName = (string)(
                $item['product_name']
                ?? 'محصول'
            );

            $productCode = trim(
                (string)(
                    $item['product_code']
                    ?? ''
                )
            );

            $qty = (int)(
                $item['qty']
                ?? 0
            );

            $baseUnitPrice = (int)(
                $item['base_unit_price']
                ?? $item['unit_price']
                ?? 0
            );

            $unitPrice = (int)(
                $item['unit_price']
                ?? 0
            );

            $discountPercent = (float)(
                $item['discount_percent']
                ?? 0
            );

            $discountAmount = (int)(
                $item['discount_amount']
                ?? max(
                    0,
                    (
                        $baseUnitPrice -
                        $unitPrice
                    ) * $qty
                )
            );

            $lineTotal = (int)(
                $item['line_total']
                ?? (
                    $unitPrice * $qty
                )
            );


            $discountText = '—';

            if (
                $discountPercent > 0
            ) {

                $discountText =
                    $faDigits(
                        rtrim(
                            rtrim(
                                number_format(
                                    $discountPercent,
                                    2,
                                    '.',
                                    ''
                                ),
                                '0'
                            ),
                            '.'
                        )
                    ) .
                    '٪';
            }


            $codeHtml = '';

            if ($productCode !== '') {

                $codeHtml = '
                    <div class="product-code">
                        کد: ' .
                    $e($productCode) .
                    '</div>
                ';
            }


            $itemsHtml .= '
                <tr>

                    <td
                        class="center"
                        width="7%"
                    >
                        ' .
                $number(
                    $index + 1
                ) .
                '
                    </td>

                    <td
                        class="product"
                        width="29%"
                    >
                        <div class="product-name">
                            ' .
                $e($productName) .
                '
                        </div>

                        ' .
                $codeHtml .
                '
                    </td>

                    <td
                        class="center"
                        width="10%"
                    >
                        ' .
                $number($qty) .
                '
                    </td>

                    <td
                        class="money"
                        width="16%"
                    >
                        ' .
                $money(
                    $baseUnitPrice
                ) .
                '
                    </td>

                    <td
                        class="center"
                        width="11%"
                    >
                        ' .
                $discountText .
                '
                    </td>

                    <td
                        class="money"
                        width="27%"
                    >
                        ' .
                $money($lineTotal) .
                '
                    </td>

                </tr>
            ';
        }


        if ($itemsHtml === '') {

            $itemsHtml = '
                <tr>

                    <td
                        colspan="6"
                        class="empty"
                    >
                        موردی برای نمایش وجود ندارد.
                    </td>

                </tr>
            ';
        }


        /*
         * =====================================================
         * Note
         * =====================================================
         */

        $note =
            trim(
                (string)(
                    $invoice['customer_note']
                    ?? ''
                )
            );

        $noteHtml = '';

        if ($note !== '') {

            $noteHtml = '
                <div class="bottom-box">

                    <div class="box-title">
                        یادداشت سفارش
                    </div>

                    <div class="note">
                        ' .
                nl2br(
                    $e($note)
                ) .
                '
                    </div>

                </div>
            ';
        } else {

            $noteHtml = '
                <div class="bottom-box">

                    <div class="box-title">
                        یادداشت سفارش
                    </div>

                    <div class="note muted">
                        —
                    </div>

                </div>
            ';
        }


        /*
         * =====================================================
         * HTML
         * =====================================================
         */

        return '
<!DOCTYPE html>

<html
    lang="fa"
    dir="rtl"
>

<head>

<meta charset="UTF-8">

<style>

* {
    font-family: dejavusans;
}

body {
    direction: rtl;
    font-family: dejavusans;
    font-size: 8pt;
    color: #222222;
    line-height: 1.55;
}

table {
    border-collapse: collapse;
}

.header {
    width: 100%;
    border-bottom: 0.7mm solid #222222;
    padding-bottom: 3mm;
    margin-bottom: 4mm;
}

.header-table {
    width: 100%;
}

.logo-cell {
    width: 25%;
    vertical-align: middle;
    text-align: right;
}

.logo-cell img {
    max-width: 22mm;
    max-height: 22mm;
}

.title-cell {
    width: 75%;
    vertical-align: middle;
    text-align: right;
}

.brand {
    font-size: 16pt;
    font-weight: bold;
    line-height: 1.3;
}

.subtitle {
    color: #777777;
    font-size: 8pt;
    line-height: 1.5;
}

.invoice-title {
    font-size: 12pt;
    font-weight: bold;
    margin-top: 1mm;
}


/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

.meta {
    width: 100%;
    margin-bottom: 4mm;
}

.meta td {
    border: 0.25mm solid #dddddd;
    padding: 2.2mm;
    vertical-align: middle;
    line-height: 1.45;
}

.meta-label {
    width: 14%;
    background-color: #f5f5f5;
    font-weight: bold;
}

.meta-value {
    width: 19.3%;
}


/*
|--------------------------------------------------------------------------
| PARTIES
|--------------------------------------------------------------------------
*/

.parties {
    width: 100%;
    margin-bottom: 4mm;
}

.party {
    width: 50%;
    vertical-align: top;
}

.party-gap {
    width: 2%;
}

.party-box {
    border: 0.25mm solid #dddddd;
    padding: 3mm;
    min-height: 29mm;
}

.party-title {
    font-size: 9pt;
    font-weight: bold;
    border-bottom: 0.5mm solid #222222;
    padding-bottom: 1.5mm;
    margin-bottom: 2mm;
}

.party-row {
    line-height: 1.65;
    margin-bottom: 0.8mm;
}

.party-label {
    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

.section-title {
    font-size: 9pt;
    font-weight: bold;
    border-bottom: 0.5mm solid #222222;
    padding-bottom: 1.5mm;
    margin-bottom: 2mm;
}

.items {
    width: 100%;
}

.items th {
    background-color: #222222;
    color: #ffffff;
    border: 0.25mm solid #222222;
    padding: 2.2mm 1.5mm;
    vertical-align: middle;
    font-size: 7.5pt;
    line-height: 1.4;
}

.items td {
    border: 0.25mm solid #d5d5d5;
    padding: 2.5mm 1.5mm;
    vertical-align: middle;
    line-height: 1.55;
}

.center {
    text-align: center;
}

.product {
    text-align: right;
}

.product-name {
    font-size: 8pt;
    line-height: 1.55;
}

.product-code {
    font-size: 6.8pt;
    color: #777777;
    margin-top: 0.7mm;
}

.money {
    text-align: center;
    direction: rtl;
}

.empty {
    text-align: center;
    padding: 5mm;
    color: #777777;
}


/*
|--------------------------------------------------------------------------
| BOTTOM
|--------------------------------------------------------------------------
*/

.bottom {
    width: 100%;
    margin-top: 4mm;
}

.bottom-left {
    width: 55%;
    vertical-align: top;
    padding-left: 3mm;
}

.bottom-right {
    width: 45%;
    vertical-align: top;
}

.bottom-box {
    border: 0.25mm solid #dddddd;
    padding: 2.8mm;
}

.box-title {
    font-weight: bold;
    font-size: 8.5pt;
    border-bottom: 0.4mm solid #222222;
    padding-bottom: 1.3mm;
    margin-bottom: 2mm;
}

.note {
    line-height: 1.8;
}

.muted {
    color: #777777;
}

/*
 * =====================================================
 * نتیجه مالی — COMPUTED
 * =====================================================
 */

 $reconciliationLabel2 = match(true) {
    $remaining > 0 => 'بدهکار',
    $remaining < 0 => 'بستانکار',
    default        => 'تسویه',
};

 $reconciliationAmount2 = abs($remaining);

 $reconHtml = '
    <table class="meta" style="margin-top:3mm;">

        <tr>

            <td class="meta-label">
                نتیجه مالی (محاسبه‌شده سیستمی)
            </td>

            <td class="meta-value">
                ' . $e($reconciliationLabel2) . '
            </td>

        </tr>

        ' . ($reconciliationAmount2 > 0 ? '
        <tr>

            <td class="meta-label">
                مبلغ
            </td>

            <td class="meta-value">
                ' . $money($reconciliationAmount2) . '
            </td>

        </tr>' : '') . '

    </table>
';

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

.totals {
    width: 100%;
}

.totals td {
    border-bottom: 0.25mm solid #dddddd;
    padding: 2mm;
    line-height: 1.45;
}

.total-label {
    width: 48%;
    background-color: #f7f7f7;
    font-weight: bold;
}

.total-value {
    width: 52%;
    text-align: left;
    font-weight: bold;
}

.grand-total td {
    border-top: 0.6mm solid #222222;
    border-bottom: 0.6mm solid #222222;
    font-size: 9pt;
    padding-top: 2.5mm;
    padding-bottom: 2.5mm;
}

.remaining td {
    background-color: #f1f1f1;
}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {
    width: 100%;
    border-top: 0.25mm solid #dddddd;
    margin-top: 4mm;
    padding-top: 2mm;
    text-align: center;
    color: #777777;
    font-size: 6.5pt;
}

</style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <table class="header-table">

        <tr>

            <td class="logo-cell">
                ' .
            $logoHtml .
            '
            </td>

            <td class="title-cell">

                <div class="brand">
                    رابطی
                </div>

                <div class="subtitle">
                    تولید مستقیم · فروش عمده
                </div>

                <div class="invoice-title">
                    فاکتور فروش
                </div>

            </td>

        </tr>

    </table>

</div>


<!-- =========================================================
     META
========================================================= -->

<table class="meta">

    <tr>

        <td class="meta-label">
            شماره فاکتور
        </td>

        <td class="meta-value">
            ' .
            $e($invoiceNumber) .
            '
        </td>

        <td class="meta-label">
            تاریخ
        </td>

        <td class="meta-value">
            ' .
            $issuedDate .
            '
        </td>

    </tr>

    <tr>

        <td class="meta-label">
            شماره سفارش
        </td>

        <td class="meta-value">
            ' .
            $e($orderNumber) .
            '
        </td>

        <td class="meta-label">
            ساعت
        </td>

        <td class="meta-value">
            ' .
            $issuedTime .
            '
        </td>

    </tr>

    <tr>

        <td class="meta-label">
            وضعیت سفارش
        </td>

        <td class="meta-value">
            ' .
            $e($orderStatus) .
            '
        </td>

        <td class="meta-label">
            نوع فروش
        </td>

        <td class="meta-value">
            عمده
        </td>

    </tr>

</table>


<!-- =========================================================
     PARTIES
========================================================= -->

<table class="parties">

    <tr>

        <td class="party">

            <div class="party-box">

                <div class="party-title">
                    فروشنده
                </div>

                <div class="party-row">
                    <span class="party-label">
                        نام:
                    </span>
                    رابطی
                </div>

                <div class="party-row">
                    <span class="party-label">
                        نوع فعالیت:
                    </span>
                    تولید و فروش عمده جوراب
                </div>

            </div>

        </td>

        <td class="party-gap">
        </td>

        <td class="party">

            <div class="party-box">

                <div class="party-title">
                    مشتری
                </div>

                <div class="party-row">
                    <span class="party-label">
                        نام:
                    </span>
                    ' .
            $e($customerName) .
            '
                </div>

                <div class="party-row">
                    <span class="party-label">
                        موبایل:
                    </span>
                    ' .
            $e($customerMobile) .
            '
                </div>

                <div class="party-row">
                    <span class="party-label">
                        آدرس:
                    </span>
                    ' .
            $e($fullAddress) .
            '
                </div>

            </div>

        </td>

    </tr>

</table>


<!-- =========================================================
     ITEMS
========================================================= -->

<div class="section-title">
    جزئیات اقلام
</div>

<table class="items">

    <thead>

        <tr>

            <th width="7%">
                ردیف
            </th>

            <th width="29%">
                محصول
            </th>

            <th width="10%">
                تعداد
            </th>

            <th width="16%">
                قیمت پایه
            </th>

            <th width="11%">
                تخفیف
            </th>

            <th width="27%">
                مبلغ نهایی
            </th>

        </tr>

    </thead>

    <tbody>

        ' .
            $itemsHtml .
            '

    </tbody>

</table>


<!-- =========================================================
     BOTTOM
========================================================= -->

<table class="bottom">

    <tr>

        <td class="bottom-left">

            ' .
            $noteHtml .
            '

        </td>

        <td class="bottom-right">

            <div class="bottom-box">

                <div class="box-title">
                    خلاصه مالی
                </div>

                <table class="totals">

                    <tr>

                        <td class="total-label">
                            جمع اقلام
                        </td>

                        <td class="total-value">
                            ' .
            $money($subtotal) .
            '
                        </td>

                    </tr>

                    <tr>

                        <td class="total-label">
                            تخفیف
                        </td>

                        <td class="total-value">
                            ' .
            $money($discount) .
            '
                        </td>

                    </tr>

                    <tr>

                        <td class="total-label">
                            هزینه ارسال
                        </td>

                        <td class="total-value">
                            ' .
            $money($shipping) .
            '
                        </td>

                    </tr>

                    <tr class="grand-total">

                        <td class="total-label">
                            مبلغ کل
                        </td>

                        <td class="total-value">
                            ' .
            $money($total) .
            '
                        </td>

                    </tr>

                    <tr>

                        <td class="total-label">
                            پرداخت‌شده
                        </td>
                        <tr>

    <td class="totals-label">
        در انتظار تأیید مالی
    </td>

    <td class="totals-value">
        ' . $money(
                (int)($invoice['pending_amount'] ?? 0)
            ) . '
    </td>

</tr>

<tr class="grand-total">

    <td class="totals-label">
        مانده قطعی
    </td>

    <td class="totals-value">
        ' . $money(
                max(0, $remaining)
            ) . '
    </td>

</tr>

                        <td class="total-value">
                            ' .
            $money($paid) .
            '
                        </td>

                    </tr>

                    <tr class="remaining">

                        <td class="total-label">
                            مانده قابل پرداخت
                        </td>

                        <td class="total-value">
                            ' .
            $money($remaining) .
            '
                        </td>

                    </tr>

                </table>

            </div>

        </td>

    </tr>

</table>


<!-- =========================================================
     FOOTER
========================================================= -->

<div class="footer">

    این فاکتور به‌صورت الکترونیکی توسط رابطی صادر شده است.

</div>


</body>

</html>
';
    }
}
