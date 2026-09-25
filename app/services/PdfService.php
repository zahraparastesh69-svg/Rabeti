<?php

declare(strict_types=1);

final class PdfService
{
    private static bool $loaded = false;

    /**
     * بارگذاری TCPDF از Composer
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
     * تولید و نمایش مستقیم PDF
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
            'فاکتور ' .
                (string)($invoice['invoice_number'] ?? '')
        );

        $pdf->SetSubject('فاکتور فروش');

        /*
         * حذف Header و Footer پیش‌فرض TCPDF
         */
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        /*
         * RTL
         */
        $pdf->setRTL(true);

        /*
         * حاشیه‌ها
         */
        $pdf->SetMargins(
            10,
            10,
            10
        );

        $pdf->SetAutoPageBreak(
            true,
            10
        );

        /*
         * فونت فعلی.
         *
         * در مرحله بعد فونت فارسی واقعی پروژه
         * به این بخش اضافه می‌شود.
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
         * جلوگیری از نام فایل نامعتبر
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
         * I = نمایش مستقیم در مرورگر
         *
         * هیچ فایل مشترکی روی سرور ذخیره نمی‌شود.
         */
        $pdf->Output(
            $filename,
            'I'
        );
    }

    /**
     * ساخت HTML مخصوص PDF
     *
     * این HTML مستقل از invoice-print.php است.
     */
    private static function buildHtml(
        array $invoice
    ): string {
        $e = static function ($value): string {
            return htmlspecialchars(
                (string)$value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };

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
         * اطلاعات اصلی
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

        /*
         * وضعیت‌ها
         */
        $invoiceStatus = InvoiceService::statusLabel(
            (string)(
                $invoice['invoice_status'] ?? ''
            )
        );

        $orderStatus = InvoiceService::orderStatusLabel(
            (string)(
                $invoice['order_status'] ?? ''
            )
        );

        /*
         * تاریخ
         */
        $issuedAt = InvoiceService::dateFa(
            $invoice['invoice_issued_at'] ?? null
        );

        $issuedTime = InvoiceService::timeFa(
            $invoice['invoice_issued_at'] ?? null
        );

        /*
         * مبالغ
         */
        $totals = $invoice['totals'] ?? [];

        $subtotal = (int)(
            $totals['subtotal'] ?? 0
        );

        $total = (int)(
            $totals['total'] ??
            $invoice['invoice_total'] ??
            0
        );

        $paid = (int)(
            $totals['paid'] ??
            $invoice['invoice_paid_total'] ??
            0
        );

        $remaining = (int)(
            $totals['remaining'] ??
            max(0, $total - $paid)
        );

        /*
         * اقلام
         */
        $itemsHtml = '';

        $items = $invoice['items'] ?? [];

        foreach ($items as $index => $item) {

            $productName = $e(
                $item['product_name'] ?? 'محصول'
            );

            $productCode = trim(
                (string)(
                    $item['product_code'] ?? ''
                )
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
                    <br>
                    <span class="product-code">
                        کد: ' . $e($productCode) . '
                    </span>
                ';
            }

            $itemsHtml .= '
                <tr>

                    <td class="center">
                        ' . $number($index + 1) . '
                    </td>

                    <td class="product">
                        <strong>
                            ' . $productName . '
                        </strong>
                        ' . $codeHtml . '
                    </td>

                    <td class="center">
                        ' . $number($qty) . '
                    </td>

                    <td class="money">
                        ' . $money($unitPrice) . '
                    </td>

                    <td class="money">
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
                        class="empty"
                    >
                        موردی برای نمایش وجود ندارد.
                    </td>
                </tr>
            ';
        }

        /*
         * اطلاعات شرکت
         */
        $companyHtml = '';

        if ($companyName !== '') {
            $companyHtml = '
                <tr>
                    <td class="label">
                        شرکت
                    </td>

                    <td>
                        ' . $e($companyName) . '
                    </td>
                </tr>
            ';
        }

        /*
         * آدرس
         */
        $shippingAddress = trim(
            implode('، ', array_filter([
                $invoice['shipping_province'] ?? '',
                $invoice['shipping_city'] ?? '',
                $invoice['shipping_address'] ?? '',
            ]))
        );

        $addressHtml = '';

        if ($shippingAddress !== '') {
            $addressHtml = '
                <tr>
                    <td class="label">
                        آدرس ارسال
                    </td>

                    <td>
                        ' . $e($shippingAddress) . '
                    </td>
                </tr>
            ';
        }

        /*
         * یادداشت
         */
        $noteHtml = '';

        $note = trim(
            (string)(
                $invoice['customer_note'] ?? ''
            )
        );

        if ($note !== '') {
            $noteHtml = '
                <div class="note-title">
                    یادداشت سفارش
                </div>

                <div class="note">
                    ' . nl2br($e($note)) . '
                </div>
            ';
        }

        return '
        <style>

            body {
                font-family: dejavusans;
                font-size: 8.5pt;
                color: #222222;
                line-height: 1.5;
            }

            .header {
                border-bottom: 2px solid #222222;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }

            .title {
                font-size: 18pt;
                font-weight: bold;
            }

            .subtitle {
                color: #666666;
                font-size: 8pt;
            }

            .meta {
                border: 1px solid #d7d7d7;
                background-color: #f7f7f7;
            }

            .meta td {
                padding: 5px;
                border-bottom: 1px solid #e5e5e5;
            }

            .meta tr:last-child td {
                border-bottom: none;
            }

            .label {
                color: #666666;
                font-weight: bold;
            }

            .section-title {
                font-size: 10pt;
                font-weight: bold;
                padding-bottom: 4px;
            }

            .customer {
                border: 1px solid #d7d7d7;
            }

            .customer td {
                padding: 5px;
                border-bottom: 1px solid #e5e5e5;
            }

            .customer tr:last-child td {
                border-bottom: none;
            }

            .items {
                border-collapse: collapse;
                margin-top: 5px;
            }

            .items th {
                background-color: #eeeeee;
                border: 1px solid #cfcfcf;
                padding: 5px;
                font-weight: bold;
                font-size: 8pt;
            }

            .items td {
                border: 1px solid #d8d8d8;
                padding: 5px;
                font-size: 8pt;
            }

            .items .center {
                text-align: center;
            }

            .items .money {
                text-align: right;
                white-space: nowrap;
            }

            .items .product {
                text-align: right;
            }

            .product-code {
                color: #777777;
                font-size: 7pt;
            }

            .empty {
                text-align: center;
                color: #777777;
                padding: 10px;
            }

            .totals {
                margin-top: 8px;
            }

            .totals td {
                padding: 4px;
            }

            .totals .total-label {
                font-weight: bold;
                font-size: 10pt;
            }

            .totals .total-value {
                font-weight: bold;
                font-size: 10pt;
            }

            .remaining {
                border: 1px solid #bbbbbb;
                background-color: #f7f7f7;
                font-weight: bold;
            }

            .note-title {
                font-weight: bold;
                margin-top: 8px;
            }

            .note {
                border: 1px solid #d7d7d7;
                padding: 6px;
                margin-top: 3px;
            }

            .footer-text {
                text-align: center;
                color: #777777;
                font-size: 7.5pt;
                margin-top: 10px;
            }

        </style>

        <!-- HEADER -->

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            class="header"
        >
            <tr>

                <td width="65%">

                    <span class="title">
                        فاکتور فروش
                    </span>

                    <br>

                    <span class="subtitle">
                        سند فروش و سفارش مشتری
                    </span>

                </td>

                <td
                    width="35%"
                    align="left"
                >

                    <strong>
                        ' . $invoiceNumber . '
                    </strong>

                    <br>

                    تاریخ:
                    ' . $e($issuedAt) . '

                    <br>

                    ساعت:
                    ' . $e($issuedTime) . '

                </td>

            </tr>
        </table>


        <!-- META -->

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            class="meta"
        >

            <tr>

                <td width="25%" class="label">
                    شماره فاکتور
                </td>

                <td width="25%">
                    ' . $invoiceNumber . '
                </td>

                <td width="25%" class="label">
                    شماره سفارش
                </td>

                <td width="25%">
                    ' . $orderNumber . '
                </td>

            </tr>

            <tr>

                <td class="label">
                    وضعیت فاکتور
                </td>

                <td>
                    ' . $e($invoiceStatus) . '
                </td>

                <td class="label">
                    وضعیت سفارش
                </td>

                <td>
                    ' . $e($orderStatus) . '
                </td>

            </tr>

        </table>

        <br>


        <!-- CUSTOMER -->

        <div class="section-title">
            اطلاعات مشتری
        </div>

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            class="customer"
        >

            <tr>

                <td
                    width="25%"
                    class="label"
                >
                    نام مشتری
                </td>

                <td width="25%">
                    ' . $customerName . '
                </td>

                <td
                    width="25%"
                    class="label"
                >
                    موبایل
                </td>

                <td width="25%">
                    ' . $customerMobile . '
                </td>

            </tr>

            ' . $companyHtml . '

            ' . $addressHtml . '

        </table>

        <br>


        <!-- ITEMS -->

        <div class="section-title">
            اقلام سفارش
        </div>

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            class="items"
        >

            <thead>

                <tr>

                    <th width="7%">
                        ردیف
                    </th>

                    <th width="38%">
                        کالا
                    </th>

                    <th width="12%">
                        تعداد
                    </th>

                    <th width="21%">
                        قیمت واحد
                    </th>

                    <th width="22%">
                        مبلغ
                    </th>

                </tr>

            </thead>

            <tbody>

                ' . $itemsHtml . '

            </tbody>

        </table>


        <!-- TOTALS -->

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            class="totals"
        >

            <tr>

                <td width="65%"></td>

                <td width="20%">
                    جمع کالاها
                </td>

                <td
                    width="15%"
                    align="right"
                >
                    ' . $money($subtotal) . '
                </td>

            </tr>

            <tr>

                <td></td>

                <td class="total-label">
                    مبلغ کل
                </td>

                <td
                    align="right"
                    class="total-value"
                >
                    ' . $money($total) . '
                </td>

            </tr>

            <tr>

                <td></td>

                <td>
                    پرداخت‌شده
                </td>

                <td align="right">
                    ' . $money($paid) . '
                </td>

            </tr>

            <tr class="remaining">

                <td></td>

                <td>
                    مانده قابل پرداخت
                </td>

                <td align="right">
                    ' . $money($remaining) . '
                </td>

            </tr>

        </table>

        ' . $noteHtml . '

        <div class="footer-text">
            این سند به صورت الکترونیکی صادر شده است.
        </div>
        ';
    }
}
