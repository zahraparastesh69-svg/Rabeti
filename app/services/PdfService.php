<?php

declare(strict_types=1);

final class PdfService
{
    private static bool $loaded = false;

    /* ══════════ ابعاد لوگو — تأیید‌شده ══════════ */
    private const LOGO_MAX_WIDTH_MM  = 25;
    private const LOGO_MAX_HEIGHT_MM = 35;
    private const LOGO_PATH          = '/Image/logo2.jpg';

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
            throw new RuntimeException('TCPDF در Composer نصب نشده است.');
        }

        self::$loaded = true;
    }

    /**
     * Generate and stream invoice PDF.
     */
    public static function output(array $invoice): void
    {
        self::loadTcpdf();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('sock-b2b');
        $pdf->SetAuthor('sock-b2b');
        $pdf->SetTitle('فاکتور ' . (string)($invoice['invoice_number'] ?? ''));
        $pdf->SetSubject('فاکتور فروش');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);

        $pdf->SetMargins(10, 9, 10);
        $pdf->SetAutoPageBreak(true, 9);

        $pdf->SetFont('dejavusans', '', 8.5);

        $pdf->AddPage();

        $html = self::buildHtml($invoice);

        $pdf->writeHTML($html, true, false, true, false, '');

        $invoiceNumber = (string)($invoice['invoice_number'] ?? 'invoice');
        $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '', $invoiceNumber);
        if ($safeNumber === '') $safeNumber = 'invoice';

        $pdf->Output('invoice-' . $safeNumber . '.pdf', 'I');
    }

    /**
     * تبدیل میلادی به جلالی — بدون کتابخانه
     */
    private static function toJalali(?string $mysqlDate): string
    {
        if (!$mysqlDate) return '—';

        $ts = strtotime($mysqlDate);
        if ($ts === false) return '—';

        $gy = (int)date('Y', $ts);
        $gm = (int)date('n', $ts);
        $gd = (int)date('j', $ts);

        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        $gy2 = ($gm > 2) ? $gy + 1 : $gy;

        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $g_d_m[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $jm = ($days < 186) ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        $months = [
            '',
            'فروردین',
            'اردیبهشت',
            'خرداد',
            'تیر',
            'مرداد',
            'شهریور',
            'مهر',
            'آبان',
            'آذر',
            'دی',
            'بهمن',
            'اسفند'
        ];

        return $jy . '/' . str_pad((string)$jm, 2, '0', STR_PAD_LEFT)
            . '/' . str_pad((string)$jd, 2, '0', STR_PAD_LEFT)
            . ' (' . $months[$jm] . ')';
    }

    /**
     * Build PDF HTML.
     */
    private static function buildHtml(array $invoice): string
    {
        $e = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $faDigits = static function ($value): string {
            return strtr(number_format((float)$value, 0, '.', ','), [
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
        };

        $money = static function ($value) use ($faDigits): string {
            return $faDigits($value) . ' تومان';
        };

        /* ═══ اطلاعات فاکتور ═══ */

        $invoiceNumber = $e($invoice['invoice_number'] ?? '—');
        $orderNumber   = $e($invoice['order_number'] ?? '—');
        $customerName  = $e($invoice['customer_name'] ?? '—');
        $customerMobile = $e($invoice['customer_mobile'] ?? '—');

        $companyName = trim((string)($invoice['customer_company'] ?? ''));

        $invoiceStatus = InvoiceService::statusLabel(
            (string)($invoice['invoice_status'] ?? '')
        );
        $orderStatus = InvoiceService::orderStatusLabel(
            (string)($invoice['order_status'] ?? '')
        );

        $issuedDate = self::toJalali($invoice['invoice_issued_at'] ?? null);
        $issuedTime = InvoiceService::timeFa($invoice['invoice_issued_at'] ?? null);

        /* ═══ مجموع‌ها ═══ */

        $totals    = $invoice['totals'] ?? [];
        $subtotal  = (int)($totals['subtotal'] ?? 0);
        $total     = (int)($totals['total'] ?? $invoice['invoice_total'] ?? 0);
        $paid      = (int)($totals['paid'] ?? $invoice['invoice_paid_total'] ?? 0);
        $remaining = (int)($totals['remaining'] ?? max(0, $total - $paid));

        /* ═══ لوگو — ۲۵ عرض × ۳۵ ارتفاع میلی‌متر ═══ */

        $logoPath = PUBLIC_PATH . self::LOGO_PATH;
        $logoHtml = '';

        if (is_file($logoPath)) {
            $logoPathForPdf = str_replace('\\', '/', $logoPath);
            $logoHtml = '
                <td class="logo-area" width="30%">
                    <div class="logo-box">
                        <img src="' . $e($logoPathForPdf) . '"
                             style="max-width:' . self::LOGO_MAX_WIDTH_MM . 'mm;
                                    max-height:' . self::LOGO_MAX_HEIGHT_MM . 'mm;
                                    width:auto; height:auto;">
                    </div>
                </td>';
        }

        /* ═══ اقلام ═══ */

        $itemsHtml = '';
        $items = $invoice['items'] ?? [];

        foreach ($items as $index => $item) {
            $productName = $e($item['product_name'] ?? 'محصول');
            $productCode = trim((string)($item['product_code'] ?? ''));
            $qty      = (int)($item['qty'] ?? 0);
            $unitPrice = (int)($item['unit_price'] ?? 0);
            $lineTotal = (int)($item['line_total'] ?? 0);

            $codeHtml = '';
            if ($productCode !== '') {
                $codeHtml = '<div class="product-code">کد:
                    <span dir="ltr">' . $e($productCode) . '</span>
                </div>';
            }

            $itemsHtml .= '
                <tr>
                    <td class="item-cell center" width="8%">' . $faDigits($index + 1) . '</td>
                    <td class="item-cell product" width="37%">
                        <div class="product-name">' . $productName . '</div>
                        ' . $codeHtml . '
                    </td>
                    <td class="item-cell center" width="12%">' . $faDigits($qty) . '</td>
                    <td class="item-cell money" width="21.5%">' . $money($unitPrice) . '</td>
                    <td class="item-cell money strong" width="21.5%">' . $money($lineTotal) . '</td>
                </tr>';
        }

        if ($itemsHtml === '') {
            $itemsHtml = '<tr><td colspan="5" class="empty-cell">موردی برای نمایش وجود ندارد.</td></tr>';
        }

        /* ═══ شرکت ═══ */
        $companyHtml = '';
        if ($companyName !== '') {
            $companyHtml = '
                <tr>
                    <td class="info-label">شرکت</td>
                    <td class="info-value">' . $e($companyName) . '</td>
                </tr>';
        }

        /* ═══ آدرس ═══ */
        $shippingAddress = trim(implode('، ', array_filter([
            $invoice['shipping_province'] ?? '',
            $invoice['shipping_city'] ?? '',
            $invoice['shipping_address'] ?? '',
        ])));

        $addressHtml = '';
        if ($shippingAddress !== '') {
            $addressHtml = '
                <tr>
                    <td class="info-label">آدرس ارسال</td>
                    <td class="info-value address">' . $e($shippingAddress) . '</td>
                </tr>';
        }

        /* ═══ یادداشت ═══ */
        $noteHtml = '';
        $note = trim((string)($invoice['customer_note'] ?? ''));
        if ($note !== '') {
            $noteHtml = '
                <div class="section-title">یادداشت سفارش</div>
                <div class="note-box">' . nl2br($e($note)) . '</div>';
        }

        /* ═══ HTML نهایی ═══ */

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

    table { border-collapse: collapse; }

    /* ─── Header ─── */
    .header {
        width: 100%;
        border-bottom: 0.7mm solid #222222;
        padding-bottom: 4mm;
        margin-bottom: 5mm;
    }
    .header-table { width: 100%; }

    .logo-area {
        vertical-align: middle;
        text-align: right;
    }

    /* کادر لوگو: ۲۵ عرض × ۳۵ ارتفاع میلی‌متر */
    .logo-box {
        width: ' . self::LOGO_MAX_WIDTH_MM . 'mm;
        height: ' . self::LOGO_MAX_HEIGHT_MM . 'mm;
        text-align: right;
        vertical-align: middle;
        overflow: hidden;
    }
    .logo-box img {
        max-width: ' . self::LOGO_MAX_WIDTH_MM . 'mm !important;
        max-height: ' . self::LOGO_MAX_HEIGHT_MM . 'mm !important;
        width: auto !important;
        height: auto !important;
    }

    .title-area {
        width: 70%;
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

    /* ─── Meta — کدها LTR داخل متن RTL ─── */
    .meta { width: 100%; margin-bottom: 5mm; }
    .meta td {
        border: 0.25mm solid #dddddd;
        padding: 3mm;
        vertical-align: middle;
        line-height: 1.5;
    }
    .meta-label { width: 18%; background-color: #f5f5f5; font-weight: bold; }
    .meta-value { width: 32%; }
    .ltr-code {
        direction: ltr;
        unicode-bidi: embed;
        font-family: dejavusans;
        font-weight: normal;
    }

    /* ─── Section title ─── */
    .section-title {
        font-size: 9.5pt;
        font-weight: bold;
        margin-top: 4mm;
        margin-bottom: 2.5mm;
        padding-bottom: 2mm;
        border-bottom: 0.64mm solid #222222;
        line-height: 1.5;
    }

    /* ─── Customer ─── */
    .customer { width: 100%; margin-bottom: 5mm; }
    .customer td {
        border: 0.25mm solid #dddddd;
        padding: 3mm;
        vertical-align: middle;
        line-height: 1.7;
    }
    .info-label { width: 20%; background-color: #f7f7f7; font-weight: bold; }
    .info-value { width: 80%; }
    .address { line-height: 1.9; }

    /* ─── Items — فشرده‌تر ─── */
    .items { width: 100%; margin-top: 2mm; }
    .items th {
        background-color: #222222;
        color: #ffffff;
        border: 0.25mm solid #222222;
        padding: 3mm 2mm;
        vertical-align: middle;
        font-size: 8pt;
        line-height: 1.5;
    }
    .item-cell {
        border: 0.25mm solid #d5d5d5;
        padding: 2.8mm 2.5mm;
        vertical-align: middle;
        line-height: 1.5;
    }
    .center { text-align: center; }
    .product { text-align: right; }
    .product-name {
        font-size: 8.5pt;
        line-height: 1.6;
        margin-bottom: 0.5mm;
    }
    .product-code {
        font-size: 7pt;
        color: #777777;
        margin-top: 0.5mm;
        line-height: 1.6;
    }
    .money { text-align: center; direction: rtl; line-height: 1.6; }
    .strong { font-weight: bold; }
    .empty-cell { text-align: center; padding: 6mm; color: #777777; }

    /* ─── Totals ─── */
    .totals-wrapper { width: 100%; margin-top: 6mm; }
    .totals { width: 55%; }
    .totals td {
        border-bottom: 0.25mm solid #dddddd;
        padding: 3mm;
        line-height: 1.6;
    }
    .totals-label { width: 48%; background-color: #f7f7f7; font-weight: bold; }
    .totals-value { width: 52%; text-align: left; font-weight: bold; }
    .grand-total td {
        border-top: 0.7mm solid #222222;
        border-bottom: 0.7mm solid #222222;
        padding: 3.5mm 3mm;
        font-size: 9.5pt;
    }
    .remaining td { background-color: #f1f1f1; }

    /* ─── Note ─── */
    .note-box {
        border: 0.25mm solid #dddddd;
        background-color: #fafafa;
        padding: 3.5mm;
        line-height: 1.9;
    }

    /* ─── Footer ─── */
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
                <td class="title-area" width="70%">
                    <div class="invoice-title">فاکتور فروش</div>
                    <div class="invoice-subtitle">فروش عمده جوراب</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- META -->
    <table class="meta">
        <tr>
            <td class="meta-label">شماره فاکتور</td>
            <td class="meta-value"><span class="ltr-code">' . $invoiceNumber . '</span></td>
            <td class="meta-label">تاریخ</td>
            <td class="meta-value">' . $issuedDate . '</td>
        </tr>
        <tr>
            <td class="meta-label">شماره سفارش</td>
            <td class="meta-value"><span class="ltr-code">' . $orderNumber . '</span></td>
            <td class="meta-label">ساعت صدور</td>
            <td class="meta-value">' . $issuedTime . '</td>
        </tr>
        <tr>
            <td class="meta-label">وضعیت فاکتور</td>
            <td class="meta-value">' . $e($invoiceStatus) . '</td>
            <td class="meta-label">وضعیت سفارش</td>
            <td class="meta-value">' . $e($orderStatus) . '</td>
        </tr>
    </table>

    <!-- CUSTOMER -->
    <div class="section-title">مشخصات مشتری</div>
    <table class="customer">
        <tr>
            <td class="info-label">نام مشتری</td>
            <td class="info-value">' . $customerName . '</td>
        </tr>
        ' . $companyHtml . '
        <tr>
            <td class="info-label">شماره موبایل</td>
            <td class="info-value">' . $customerMobile . '</td>
        </tr>
        ' . $addressHtml . '
    </table>

    <!-- ITEMS -->
    <div class="section-title">جزئیات اقلام</div>
    <table class="items">
        <thead>
            <tr>
                <th width="8%">ردیف</th>
                <th width="37%">محصول</th>
                <th width="12%">تعداد</th>
                <th width="21.5%">قیمت واحد</th>
                <th width="21.5%">مبلغ</th>
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
                <td class="totals-label">جمع اقلام</td>
                <td class="totals-value">' . $money($subtotal) . '</td>
            </tr>
            <tr class="grand-total">
                <td class="totals-label">مبلغ کل</td>
                <td class="totals-value">' . $money($total) . '</td>
            </tr>
            <tr>
                <td class="totals-label">پرداخت‌شده</td>
                <td class="totals-value">' . $money($paid) . '</td>
            </tr>
            <tr class="remaining">
                <td class="totals-label">مانده قابل پرداخت</td>
                <td class="totals-value">' . $money($remaining) . '</td>
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
