<?php

declare(strict_types=1);

final class PdfService
{
    private static bool $loaded = false;

    /**
     * Load TCPDF.
     */
    private static function loadTcpdf(): void
    {
        if (self::$loaded) {
            return;
        }

        $autoload = ROOT_PATH . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException('Composer autoload پیدا نشد.');
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
     * Generate and stream invoice PDF.
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

        $pdf->SetCreator('sock-b2b');
        $pdf->SetAuthor('sock-b2b');

        $pdf->SetTitle(
            'فاکتور ' . (string)($invoice['invoice_number'] ?? '')
        );

        $pdf->SetSubject('فاکتور فروش');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->setRTL(true);

        /*
         * Page margins
         */
        $pdf->SetMargins(
            10,
            9,
            10
        );

        $pdf->SetAutoPageBreak(
            true,
            9
        );

        /*
         * Current fallback font.
         * بعداً فونت فارسی اصلی پروژه را اینجا قرار می‌دهیم.
         */
        $pdf->SetFont(
            'dejavusans',
            '',
            8.5
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
            $invoice['invoice_number'] ?? 'invoice'
        );

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '',
            $invoiceNumber
        );

        if ($safeNumber === '') {
            $safeNumber = 'invoice';
        }

        $filename = 'invoice-' . $safeNumber . '.pdf';

        /*
         * Direct browser output.
         */
        $pdf->Output(
            $filename,
            'I'
        );
    }

    /**
     * Build PDF HTML.
     */
    private static function buildHtml(array $invoice): string
    {
        /*
         * Escape helper
         */
        $e = static function ($value): string {
            return htmlspecialchars(
                (string)$value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };

        /*
         * Number helper
         */
        $number = static function ($value): string {
            return InvoiceService::faDigits(
                number_format(
                    (float)$value,
                    0,
                    '.',
                    ','
                )
            );
        };

        /*
         * Money helper
         */
        $money = static function ($value): string {
            return InvoiceService::faDigits(
                number_format(
                    (float)$value,
                    0,
                    '.',
                    ','
                )
            ) . ' تومان';
        };

        /*
         * =====================================================
         * Invoice information
         * =====================================================
         */

        $invoiceNumber = $e(
            $invoice['invoice_number'] ?? '—'
        );

        $orderNumber = $e(
            $invoice['order_number'] ?? '—'
        );

        $customerName = $e(
            $invoice['customer_name'] ?? '—'
        );

        $companyName = trim(
            (string)($invoice['customer_company'] ?? '')
        );

        $customerMobile = $e(
            $invoice['customer_mobile'] ?? '—'
        );

        $invoiceStatus = InvoiceService::statusLabel(
            (string)($invoice['invoice_status'] ?? '')
        );

        $orderStatus = InvoiceService::orderStatusLabel(
            (string)($invoice['order_status'] ?? '')
        );

        $issuedDate = InvoiceService::dateFa(
            $invoice['invoice_issued_at'] ?? null
        );

        $issuedTime = InvoiceService::timeFa(
            $invoice['invoice_issued_at'] ?? null
        );

        /*
         * =====================================================
         * Totals
         * =====================================================
         */

        $totals = $invoice['totals'] ?? [];

        $subtotal = (int)(
            $totals['subtotal'] ?? 0
        );

        $total = (int)(
            $totals['total']
            ?? $invoice['invoice_total']
            ?? 0
        );

        $paid = (int)(
            $totals['paid']
            ?? $invoice['invoice_paid_total']
            ?? 0
        );

        $remaining = (int)(
            $totals['remaining']
            ?? max(0, $total - $paid)
        );

        /*
         * =====================================================
         * LOGO
         * =====================================================
         *
         * Logo source:
         *
         * public/Image/logo2.jpg
         *
         * Maximum visual area:
         *
         * 35mm × 16mm
         *
         * تصویر با نسبت خودش داخل این محدوده قرار می‌گیرد.
         */

        $logoPath = ROOT_PATH . '/public/Image/logo2.jpg';

        $logoHtml = '';

        if (is_file($logoPath)) {

            $logoPathForPdf = str_replace(
                '\\',
                '/',
                $logoPath
            );

            $logoHtml = '
                <td
                    class="logo-area"
                    width="38%"
                >
                    <div class="logo-box">

                        <img
                            src="' . $e($logoPathForPdf) . '"
                            width="35"
                            height="16"
                            style="
                                max-width:35mm;
                                max-height:16mm;
                            "
                        >

                    </div>
                </td>
            ';
        }

        /*
         * =====================================================
         * ITEMS
         * =====================================================
         */

        $itemsHtml = '';

        $items = $invoice['items'] ?? [];

        foreach ($items as $index => $item) {

            $productName = $e(
                $item['product_name'] ?? 'محصول'
            );

            $productCode = trim(
                (string)($item['product_code'] ?? '')
            );

            $qty = (int)(
                $item['qty'] ?? 0
            );

            $unitPrice = (int)(
                $item['unit_price'] ?? 0
            );

            $lineTotal = (int)(
                $item['line_total'] ?? 0
            );

            $codeHtml = '';

            if ($productCode !== '') {

                $codeHtml = '
                    <div class="product-code">
                        کد: ' . $e($productCode) . '
                    </div>
                ';
            }

            $itemsHtml .= '
                <tr>

                    <td
                        class="item-cell center"
                        width="8%"
                    >
                        ' . $number($index + 1) . '
                    </td>

                    <td
                        class="item-cell product"
                        width="37%"
                    >
                        <div class="product-name">
                            ' . $productName . '
                        </div>

                        ' . $codeHtml . '

                    </td>

                    <td
                        class="item-cell center"
                        width="12%"
                    >
                        ' . $number($qty) . '
                    </td>

                    <td
                        class="item-cell money"
                        width="21.5%"
                    >
                        ' . $money($unitPrice) . '
                    </td>

                    <td
                        class="item-cell money strong"
                        width="21.5%"
                    >
                        ' . $money($lineTotal) . '
                    </td>

                </tr>
            ';
        }

        if ($itemsHtml === '') {

            $itemsHtml = '
                <tr>

                    <td
                        colspan="5"
                        class="empty-cell"
                    >
                        موردی برای نمایش وجود ندارد.
                    </td>

                </tr>
            ';
        }

        /*
         * =====================================================
         * COMPANY
         * =====================================================
         */

        $companyHtml = '';

        if ($companyName !== '') {

            $companyHtml = '
                <tr>

                    <td class="info-label">
                        شرکت
                    </td>

                    <td class="info-value">
                        ' . $e($companyName) . '
                    </td>

                </tr>
            ';
        }

        /*
         * =====================================================
         * ADDRESS
         * =====================================================
         */

        $shippingAddress = trim(
            implode(
                '، ',
                array_filter([
                    $invoice['shipping_province'] ?? '',
                    $invoice['shipping_city'] ?? '',
                    $invoice['shipping_address'] ?? '',
                ])
            )
        );

        $addressHtml = '';

        if ($shippingAddress !== '') {

            $addressHtml = '
                <tr>

                    <td class="info-label">
                        آدرس ارسال
                    </td>

                    <td class="info-value address">
                        ' . $e($shippingAddress) . '
                    </td>

                </tr>
            ';
        }

        /*
         * =====================================================
         * CUSTOMER NOTE
         * =====================================================
         */

        $noteHtml = '';

        $note = trim(
            (string)($invoice['customer_note'] ?? '')
        );

        if ($note !== '') {

            $noteHtml = '

                <div class="section-title">
                    یادداشت سفارش
                </div>

                <div class="note-box">
                    ' . nl2br($e($note)) . '
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

<html lang="fa" dir="rtl">

<head>

<meta charset="UTF-8">

<style>

    body {

        direction: rtl;

        font-family: dejavusans;

        font-size: 8.5pt;

        color: #222222;

        line-height: 1.65;

    }

    table {

        border-collapse: collapse;

    }

    /*
     * =================================================
     * HEADER
     * =================================================
     */

    .header {

        width: 100%;

        border-bottom: 0.7mm solid #222222;

        padding-bottom: 4mm;

        margin-bottom: 5mm;

    }

    .header-table {

        width: 100%;

    }

    .logo-area {

        vertical-align: middle;

        text-align: right;

    }

    /*
     * محدوده واقعی لوگو
     *
     * 35 × 16 mm
     */

    .logo-box {

        width: 35mm;

        height: 16mm;

        text-align: right;

        vertical-align: middle;

    }

    .logo-box img {

        max-width: 35mm;

        max-height: 16mm;

    }

    .title-area {

        width: 62%;

        vertical-align: middle;

        text-align: left;

    }

    .invoice-title {

        font-size: 15pt;

        font-weight: bold;

        line-height: 1.3;

        margin-bottom: 2mm;

    }

    .invoice-subtitle {

        font-size: 8pt;

        color: #777777;

        line-height: 1.5;

    }

    /*
     * =================================================
     * META
     * =================================================
     */

    .meta {

        width: 100%;

        margin-bottom: 5mm;

    }

    .meta td {

        border: 0.25mm solid #dddddd;

        padding-top: 3mm;

        padding-bottom: 3mm;

        padding-left: 3mm;

        padding-right: 3mm;

        vertical-align: middle;

        line-height: 1.5;

    }

    .meta-label {

        width: 15%;

        background-color: #f5f5f5;

        font-weight: bold;

    }

    .meta-value {

        width: 35%;

    }

    /*
     * =================================================
     * SECTION TITLE
     * =================================================
     */

    .section-title {

        font-size: 9.5pt;

        font-weight: bold;

        margin-top: 4mm;

        margin-bottom: 2.5mm;

        padding-bottom: 2mm;

        border-bottom: 0.35mm solid #222222;

        line-height: 1.5;

    }

    /*
     * =================================================
     * CUSTOMER
     * =================================================
     */

    .customer {

        width: 100%;

        margin-bottom: 5mm;

    }

    .customer td {

        border: 0.25mm solid #dddddd;

        padding-top: 3mm;

        padding-bottom: 3mm;

        padding-left: 3mm;

        padding-right: 3mm;

        vertical-align: middle;

        line-height: 1.7;

    }

    .info-label {

        width: 20%;

        background-color: #f7f7f7;

        font-weight: bold;

    }

    .info-value {

        width: 80%;

    }

    .address {

        line-height: 1.9;

    }

    /*
     * =================================================
     * ITEMS
     * =================================================
     */

    .items {

        width: 100%;

        margin-top: 2mm;

    }

    .items th {

        background-color: #222222;

        color: #ffffff;

        border: 0.25mm solid #222222;

        padding-top: 3mm;

        padding-bottom: 3mm;

        padding-left: 2mm;

        padding-right: 2mm;

        vertical-align: middle;

        font-size: 8pt;

        line-height: 1.5;

    }

    /*
     * فاصله بیشتر بین خطوط و ردیف‌ها
     */

    .item-cell {

        border: 0.25mm solid #d5d5d5;

        padding-top: 4.5mm;

        padding-bottom: 4.5mm;

        padding-left: 2.5mm;

        padding-right: 2.5mm;

        vertical-align: middle;

        line-height: 1.8;

    }

    .center {

        text-align: center;

    }

    .product {

        text-align: right;

    }

    .product-name {

        font-size: 8.5pt;

        line-height: 1.8;

    }

    .product-code {

        font-size: 7pt;

        color: #777777;

        margin-top: 1.5mm;

        line-height: 1.8;

    }

    .money {

        text-align: center;

        direction: rtl;

        line-height: 1.7;

    }

    .strong {

        font-weight: bold;

    }

    .empty-cell {

        text-align: center;

        padding-top: 6mm;

        padding-bottom: 6mm;

        color: #777777;

    }

    /*
     * =================================================
     * TOTALS
     * =================================================
     */

    .totals-wrapper {

        width: 100%;

        margin-top: 6mm;

    }

    .totals {

        width: 55%;

        margin-left: 0;

    }

    .totals td {

        border-bottom: 0.25mm solid #dddddd;

        padding-top: 3mm;

        padding-bottom: 3mm;

        padding-left: 3mm;

        padding-right: 3mm;

        line-height: 1.6;

    }

    .totals-label {

        width: 48%;

        background-color: #f7f7f7;

        font-weight: bold;

    }

    .totals-value {

        width: 52%;

        text-align: left;

        font-weight: bold;

    }

    .grand-total td {

        border-top: 0.7mm solid #222222;

        border-bottom: 0.7mm solid #222222;

        padding-top: 3.5mm;

        padding-bottom: 3.5mm;

        font-size: 9.5pt;

    }

    .remaining td {

        background-color: #f1f1f1;

    }

    /*
     * =================================================
     * NOTE
     * =================================================
     */

    .note-box {

        border: 0.25mm solid #dddddd;

        background-color: #fafafa;

        padding-top: 3.5mm;

        padding-bottom: 3.5mm;

        padding-left: 3.5mm;

        padding-right: 3.5mm;

        line-height: 1.9;

    }

    /*
     * =================================================
     * FOOTER
     * =================================================
     */

    .footer {

        width: 100%;

        border-top: 0.25mm solid #dddddd;

        margin-top: 7mm;

        padding-top: 3mm;

        text-align: center;

        color: #777777;

        font-size: 7pt;

        line-height: 1.8;

    }

</style>

</head>

<body>

    <!-- HEADER -->

    <div class="header">

        <table class="header-table">

            <tr>

                ' . $logoHtml . '

                <td
                    class="title-area"
                    width="62%"
                >

                    <div class="invoice-title">
                        فاکتور فروش
                    </div>

                    <div class="invoice-subtitle">
                        فروش عمده جوراب
                    </div>

                </td>

            </tr>

        </table>

    </div>


    <!-- META -->

    <table class="meta">

        <tr>

            <td class="meta-label">
                شماره فاکتور
            </td>

            <td class="meta-value">
                ' . $invoiceNumber . '
            </td>

            <td class="meta-label">
                تاریخ
            </td>

            <td class="meta-value">
                ' . $issuedDate . '
            </td>

        </tr>

        <tr>

            <td class="meta-label">
                شماره سفارش
            </td>

            <td class="meta-value">
                ' . $orderNumber . '
            </td>

            <td class="meta-label">
                ساعت صدور
            </td>

            <td class="meta-value">
                ' . $issuedTime . '
            </td>

        </tr>

        <tr>

            <td class="meta-label">
                وضعیت فاکتور
            </td>

            <td class="meta-value">
                ' . $e($invoiceStatus) . '
            </td>

            <td class="meta-label">
                وضعیت سفارش
            </td>

            <td class="meta-value">
                ' . $e($orderStatus) . '
            </td>

        </tr>

    </table>


    <!-- CUSTOMER -->

    <div class="section-title">
        مشخصات مشتری
    </div>

    <table class="customer">

        <tr>

            <td class="info-label">
                نام مشتری
            </td>

            <td class="info-value">
                ' . $customerName . '
            </td>

        </tr>

        ' . $companyHtml . '

        <tr>

            <td class="info-label">
                شماره موبایل
            </td>

            <td class="info-value">
                ' . $customerMobile . '
            </td>

        </tr>

        ' . $addressHtml . '

    </table>


    <!-- ITEMS -->

    <div class="section-title">
        جزئیات اقلام
    </div>

    <table class="items">

        <thead>

            <tr>

                <th width="8%">
                    ردیف
                </th>

                <th width="37%">
                    محصول
                </th>

                <th width="12%">
                    تعداد
                </th>

                <th width="21.5%">
                    قیمت واحد
                </th>

                <th width="21.5%">
                    مبلغ
                </th>

            </tr>

        </thead>

        <tbody>

            ' . $itemsHtml . '

        </tbody>

    </table>


    <!-- TOTALS -->

    <div class="totals-wrapper">

        <table class="totals">

            <tr>

                <td class="totals-label">
                    جمع اقلام
                </td>

                <td class="totals-value">
                    ' . $money($subtotal) . '
                </td>

            </tr>

            <tr class="grand-total">

                <td class="totals-label">
                    مبلغ کل
                </td>

                <td class="totals-value">
                    ' . $money($total) . '
                </td>

            </tr>

            <tr>

                <td class="totals-label">
                    پرداخت‌شده
                </td>

                <td class="totals-value">
                    ' . $money($paid) . '
                </td>

            </tr>

            <tr class="remaining">

                <td class="totals-label">
                    مانده قابل پرداخت
                </td>

                <td class="totals-value">
                    ' . $money($remaining) . '
                </td>

            </tr>

        </table>

    </div>


    ' . $noteHtml . '


    <!-- FOOTER -->

    <div class="footer">

        این فاکتور به‌صورت الکترونیکی صادر شده است.

        <br>

        sock-b2b

    </div>

</body>

</html>';
    }
}
