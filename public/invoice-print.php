<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$isAdmin = is_admin_logged_in();

$invoiceNumber = trim((string)($_GET['inv'] ?? ''));

if ($invoiceNumber === '') {
    exit('شماره فاکتور مشخص نشده است.');
}

/*
|--------------------------------------------------------------------------
| دریافت فاکتور
|--------------------------------------------------------------------------
| مهم:
| از SELECT i.*, o.* استفاده نمی‌کنیم.
| چون فیلدهای مشترکی مثل status / id / user_id می‌توانند روی هم
| overwrite شوند و باعث خطاهایی مثل pending_review شوند.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.*,

        o.id AS order_id,
        o.order_number,
        o.status AS order_status,
        o.shipping_mobile,
        o.shipping_address,
        o.shipping_city,
        o.shipping_province,

        u.full_name AS customer_name,
        u.mobile AS customer_mobile

    FROM invoices i

    INNER JOIN orders o
        ON o.id = i.order_id

    INNER JOIN users u
        ON u.id = i.user_id

    WHERE
        i.invoice_number = ?
        AND (i.user_id = ? OR ? = 1)

    LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute([
    $invoiceNumber,
    $userId,
    $isAdmin ? 1 : 0
]);

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    http_response_code(404);
    exit('فاکتور یافت نشد.');
}


/*
|--------------------------------------------------------------------------
| اقلام فاکتور
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");

$stmt->execute([
    (int)$invoice['order_id']
]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| توابع کمکی
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function moneyFa(mixed $value): string
{
    if ($value === null || $value === '') {
        return '۰';
    }

    return number_format(
        (float)$value,
        0,
        '.',
        ','
    );
}

function faDigits(string $value): string
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
        ]
    );
}

function formatDateFa(?string $date): string
{
    if (!$date) {
        return '—';
    }

    try {
        $d = new DateTimeImmutable($date);
        return faDigits($d->format('Y/m/d'));
    } catch (Throwable) {
        return e($date);
    }
}

function invoiceStatusLabel(?string $status): string
{
    $labels = [
        'unpaid'  => 'پرداخت‌نشده',
        'partial' => 'پرداخت جزئی',
        'paid'    => 'پرداخت‌شده',
        'void'    => 'باطل‌شده',
        'cancelled' => 'لغوشده',
    ];

    return $labels[$status ?? ''] ?? 'نامشخص';
}

function invoiceStatusClass(?string $status): string
{
    return match ($status) {
        'paid'      => 'status-paid',
        'partial'   => 'status-partial',
        'void',
        'cancelled' => 'status-danger',
        default     => 'status-unpaid',
    };
}

function orderStatusLabel(?string $status): string
{
    $labels = [
        'pending_review' => 'در انتظار بررسی',
        'pending'        => 'در انتظار پرداخت',
        'confirmed'      => 'تأیید شده',
        'processing'     => 'در حال آماده‌سازی',
        'ready'          => 'آماده تحویل',
        'shipped'        => 'ارسال شده',
        'delivered'      => 'تحویل شده',
        'cancelled'      => 'لغو شده',
        'rejected'       => 'رد شده',
    ];

    return $labels[$status ?? ''] ?? 'نامشخص';
}


/*
|--------------------------------------------------------------------------
| اطلاعات اصلی
|--------------------------------------------------------------------------
*/

$invoiceStatus = (string)($invoice['status'] ?? '');
$orderStatus   = (string)($invoice['order_status'] ?? '');

$invoiceStatusText = invoiceStatusLabel($invoiceStatus);
$orderStatusText   = orderStatusLabel($orderStatus);

$invoiceStatusCss = invoiceStatusClass($invoiceStatus);

$subtotal = 0;

foreach ($items as $item) {
    $qty = (float)($item['quantity'] ?? $item['qty'] ?? 0);

    $unitPrice = (float)(
        $item['unit_price']
        ?? $item['price']
        ?? 0
    );

    $subtotal += $qty * $unitPrice;
}


/*
|--------------------------------------------------------------------------
| مبالغ
|--------------------------------------------------------------------------
*/

$totalAmount = (float)(
    $invoice['total_amount']
    ?? $invoice['grand_total']
    ?? $invoice['amount']
    ?? $subtotal
);

$discountAmount = (float)(
    $invoice['discount_amount']
    ?? $invoice['discount']
    ?? 0
);

$shippingAmount = (float)(
    $invoice['shipping_amount']
    ?? $invoice['shipping_fee']
    ?? 0
);

$taxAmount = (float)(
    $invoice['tax_amount']
    ?? $invoice['tax']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| لینک PDF
|--------------------------------------------------------------------------
*/

$pdfUrl = '/invoice-pdf.php?inv=' . rawurlencode($invoiceNumber);


/*
|--------------------------------------------------------------------------
| عنوان
|--------------------------------------------------------------------------
*/

$pageTitle = 'فاکتور ' . $invoiceNumber;

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            direction: rtl;
        }

        body {
            margin: 0;
            background: #f4f6f8;
            color: #1f2937;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
            line-height: 1.8;
        }

        button,
        a {
            font-family: inherit;
        }

        .page {
            width: 100%;
            max-width: 1050px;
            margin: 0 auto;
            padding: 24px;
        }

        /* --------------------------------------------------
           Toolbar
        -------------------------------------------------- */

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .toolbar-right,
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            min-height: 42px;
            padding: 9px 15px;

            border: 1px solid #d1d5db;
            border-radius: 10px;

            background: #fff;
            color: #374151;

            text-decoration: none;
            cursor: pointer;

            font-size: 14px;
            font-weight: 700;

            transition:
                background .15s ease,
                border-color .15s ease,
                transform .15s ease;
        }

        .btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1f2937;
            border-color: #1f2937;
        }

        .btn-pdf {
            background: #7f1d1d;
            border-color: #7f1d1d;
            color: #fff;
        }

        .btn-pdf:hover {
            background: #991b1b;
            border-color: #991b1b;
        }

        /* --------------------------------------------------
           Invoice
        -------------------------------------------------- */

        .invoice {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .05);
        }

        .invoice-header {
            padding: 28px;
            border-bottom: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .brand-title {
            font-size: 23px;
            font-weight: 900;
            color: #111827;
        }

        .brand-subtitle {
            color: #6b7280;
            font-size: 13px;
        }

        .invoice-title {
            text-align: left;
        }

        .invoice-title h1 {
            margin: 0 0 4px;
            font-size: 24px;
            color: #111827;
        }

        .invoice-number {
            font-family: Tahoma, Arial, sans-serif;
            font-weight: 800;
            color: #4b5563;
        }

        /* --------------------------------------------------
           Meta
        -------------------------------------------------- */

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            padding: 22px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .meta-card {
            padding: 14px;
            background: #f9fafb;
            border: 1px solid #eef0f2;
            border-radius: 12px;
        }

        .meta-label {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 12px;
        }

        .meta-value {
            display: block;
            color: #111827;
            font-size: 14px;
            font-weight: 800;
        }

        .status {
            display: inline-flex;
            align-items: center;
            width: fit-content;

            padding: 4px 9px;
            border-radius: 999px;

            font-size: 12px;
            font-weight: 800;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-partial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* --------------------------------------------------
           Customer
        -------------------------------------------------- */

        .section {
            padding: 24px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            margin: 0 0 15px;
            font-size: 16px;
            font-weight: 900;
            color: #111827;
        }

        .customer-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 24px;
        }

        .info-row {
            display: flex;
            gap: 8px;
            font-size: 14px;
        }

        .info-label {
            min-width: 95px;
            color: #6b7280;
        }

        .info-value {
            color: #111827;
            font-weight: 700;
        }

        /* --------------------------------------------------
           Items
        -------------------------------------------------- */

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .items-table {
            width: 100%;
            min-width: 680px;
            border-collapse: collapse;
        }

        .items-table th,
        .items-table td {
            padding: 13px 10px;
            border-bottom: 1px solid #edf0f2;
            text-align: right;
            vertical-align: middle;
        }

        .items-table th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
        }

        .items-table td {
            font-size: 13px;
            color: #374151;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .product-name {
            color: #111827;
            font-weight: 800;
        }

        .product-code {
            display: block;
            color: #9ca3af;
            font-size: 11px;
            margin-top: 2px;
        }

        /* --------------------------------------------------
           Totals
        -------------------------------------------------- */

        .totals {
            display: flex;
            justify-content: flex-end;
            padding: 22px 28px;
        }

        .totals-box {
            width: 100%;
            max-width: 420px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 7px 0;
            font-size: 14px;
        }

        .total-row .label {
            color: #6b7280;
        }

        .total-row .value {
            color: #111827;
            font-weight: 800;
        }

        .grand-total {
            margin-top: 10px;
            padding-top: 14px;
            border-top: 2px solid #111827;

            display: flex;
            justify-content: space-between;
            gap: 20px;

            font-size: 18px;
            font-weight: 900;
        }

        .grand-total .value {
            color: #111827;
        }

        /* --------------------------------------------------
           Footer
        -------------------------------------------------- */

        .invoice-footer {
            padding: 20px 28px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            gap: 20px;

            color: #6b7280;
            font-size: 12px;
        }

        .footer-actions {
            display: none;
        }

        /* --------------------------------------------------
           Mobile
        -------------------------------------------------- */

        @media (max-width: 760px) {

            .page {
                padding: 10px;
            }

            .toolbar {
                position: sticky;
                top: 0;
                z-index: 20;

                padding: 8px 0;
                background: #f4f6f8;

                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-right,
            .toolbar-left {
                width: 100%;
            }

            .toolbar .btn {
                flex: 1;
            }

            .invoice {
                border-radius: 12px;
            }

            .invoice-header {
                padding: 20px 16px;
                flex-direction: column;
            }

            .invoice-title {
                text-align: right;
            }

            .invoice-title h1 {
                font-size: 20px;
            }

            .meta-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 16px;
                gap: 8px;
            }

            .meta-card {
                padding: 11px;
            }

            .section {
                padding: 18px 16px;
            }

            .customer-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .info-row {
                flex-direction: column;
                gap: 0;
            }

            .info-label {
                min-width: 0;
                font-size: 11px;
            }

            .info-value {
                font-size: 13px;
            }

            .totals {
                padding: 18px 16px;
            }

            .invoice-footer {
                padding: 16px;
                flex-direction: column;
            }

            .footer-actions {
                display: flex;
                gap: 8px;
            }

            .footer-actions .btn {
                flex: 1;
            }
        }

        @media (max-width: 420px) {

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .brand-title {
                font-size: 20px;
            }

            .btn {
                font-size: 12px;
                padding: 8px 10px;
            }
        }

        /* --------------------------------------------------
           Print
        -------------------------------------------------- */

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #fff;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .no-print,
            .toolbar,
            .footer-actions {
                display: none !important;
            }

            .invoice {
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .invoice-header {
                padding: 0 0 15px;
            }

            .section {
                padding: 15px 0;
            }

            .meta-grid {
                padding: 15px 0;
            }

            .totals {
                padding: 15px 0;
            }

            .invoice-footer {
                padding: 15px 0;
            }
        }
    </style>

</head>

<body>

    <div class="page">

        <!-- =====================================================
         Toolbar
    ====================================================== -->

        <div class="toolbar no-print">

            <div class="toolbar-right">

                <a
                    href="/orders"
                    class="btn">
                    ← بازگشت به سفارش‌ها
                </a>

                <a
                    href="/"
                    class="btn">
                    خانه
                </a>

            </div>

            <div class="toolbar-left">

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="window.print()">
                    🖨 چاپ فاکتور
                </button>

                <a
                    href="<?= e($pdfUrl) ?>"
                    class="btn btn-pdf"
                    target="_blank"
                    rel="noopener">
                    📄 دریافت PDF
                </a>

            </div>

        </div>


        <!-- =====================================================
         Invoice
    ====================================================== -->

        <main class="invoice">

            <!-- Header -->

            <header class="invoice-header">

                <div class="brand">

                    <div class="brand-title">
                        فاکتور فروش
                    </div>

                    <div class="brand-subtitle">
                        سیستم فروش B2B
                    </div>

                </div>

                <div class="invoice-title">

                    <h1>
                        فاکتور
                    </h1>

                    <div class="invoice-number" dir="ltr">
                        <?= e($invoice['invoice_number']) ?>
                    </div>

                </div>

            </header>


            <!-- Meta -->

            <section class="meta-grid">

                <div class="meta-card">

                    <span class="meta-label">
                        شماره فاکتور
                    </span>

                    <span class="meta-value" dir="ltr">
                        <?= e($invoice['invoice_number']) ?>
                    </span>

                </div>


                <div class="meta-card">

                    <span class="meta-label">
                        شماره سفارش
                    </span>

                    <span class="meta-value" dir="ltr">
                        <?= e($invoice['order_number']) ?>
                    </span>

                </div>


                <div class="meta-card">

                    <span class="meta-label">
                        تاریخ صدور
                    </span>

                    <span class="meta-value">
                        <?= formatDateFa($invoice['issued_at'] ?? null) ?>
                    </span>

                </div>


                <div class="meta-card">

                    <span class="meta-label">
                        وضعیت فاکتور
                    </span>

                    <span class="status <?= e($invoiceStatusCss) ?>">
                        <?= e($invoiceStatusText) ?>
                    </span>

                </div>

            </section>


            <!-- Customer -->

            <section class="section">

                <h2 class="section-title">
                    اطلاعات مشتری
                </h2>

                <div class="customer-grid">

                    <div class="info-row">

                        <span class="info-label">
                            نام مشتری
                        </span>

                        <span class="info-value">
                            <?= e($invoice['customer_name'] ?? '—') ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            شماره موبایل
                        </span>

                        <span class="info-value" dir="ltr">
                            <?= e(
                                $invoice['customer_mobile']
                                    ?? $invoice['shipping_mobile']
                                    ?? '—'
                            ) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            وضعیت سفارش
                        </span>

                        <span class="info-value">
                            <?= e($orderStatusText) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            شهر
                        </span>

                        <span class="info-value">
                            <?= e(
                                $invoice['shipping_city']
                                    ?? '—'
                            ) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            استان
                        </span>

                        <span class="info-value">
                            <?= e(
                                $invoice['shipping_province']
                                    ?? '—'
                            ) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            آدرس
                        </span>

                        <span class="info-value">
                            <?= e(
                                $invoice['shipping_address']
                                    ?? '—'
                            ) ?>
                        </span>

                    </div>

                </div>

            </section>


            <!-- Items -->

            <section class="section">

                <h2 class="section-title">
                    اقلام فاکتور
                </h2>

                <div class="table-wrap">

                    <table class="items-table">

                        <thead>

                            <tr>

                                <th style="width:50px;">
                                    ردیف
                                </th>

                                <th>
                                    کالا
                                </th>

                                <th>
                                    رنگ
                                </th>

                                <th>
                                    سایز
                                </th>

                                <th>
                                    تعداد
                                </th>

                                <th>
                                    قیمت واحد
                                </th>

                                <th>
                                    مبلغ
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!$items): ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        style="text-align:center;padding:30px;">
                                        کالایی برای این فاکتور ثبت نشده است.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($items as $index => $item): ?>

                                    <?php

                                    $qty = (float)(
                                        $item['quantity']
                                        ?? $item['qty']
                                        ?? 0
                                    );

                                    $unitPrice = (float)(
                                        $item['unit_price']
                                        ?? $item['price']
                                        ?? 0
                                    );

                                    $lineTotal = $qty * $unitPrice;

                                    $productName =
                                        $item['product_name']
                                        ?? $item['name']
                                        ?? $item['title']
                                        ?? 'کالا';

                                    $productCode =
                                        $item['product_code']
                                        ?? $item['sku']
                                        ?? '';

                                    $color =
                                        $item['color_name']
                                        ?? $item['color']
                                        ?? '—';

                                    $size =
                                        $item['size_name']
                                        ?? $item['size']
                                        ?? '—';

                                    ?>

                                    <tr>

                                        <td>
                                            <?= faDigits((string)($index + 1)) ?>
                                        </td>

                                        <td>

                                            <span class="product-name">
                                                <?= e($productName) ?>
                                            </span>

                                            <?php if ($productCode !== ''): ?>

                                                <span
                                                    class="product-code"
                                                    dir="ltr">
                                                    <?= e($productCode) ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= e($color) ?>
                                        </td>

                                        <td>
                                            <?= e($size) ?>
                                        </td>

                                        <td>
                                            <?= faDigits(
                                                number_format($qty, 0, '.', '')
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= moneyFa($unitPrice) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= moneyFa($lineTotal) ?>
                                            </strong>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- Totals -->

            <section class="totals">

                <div class="totals-box">

                    <div class="total-row">

                        <span class="label">
                            جمع کالاها
                        </span>

                        <span class="value">
                            <?= moneyFa($subtotal) ?>
                        </span>

                    </div>


                    <?php if ($discountAmount > 0): ?>

                        <div class="total-row">

                            <span class="label">
                                تخفیف
                            </span>

                            <span class="value">
                                <?= moneyFa($discountAmount) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($shippingAmount > 0): ?>

                        <div class="total-row">

                            <span class="label">
                                هزینه ارسال
                            </span>

                            <span class="value">
                                <?= moneyFa($shippingAmount) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($taxAmount > 0): ?>

                        <div class="total-row">

                            <span class="label">
                                مالیات
                            </span>

                            <span class="value">
                                <?= moneyFa($taxAmount) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <div class="grand-total">

                        <span>
                            مبلغ نهایی
                        </span>

                        <span class="value">
                            <?= moneyFa($totalAmount) ?>
                        </span>

                    </div>

                </div>

            </section>


            <!-- Footer -->

            <footer class="invoice-footer">

                <div>
                    این فاکتور به صورت سیستمی صادر شده است.
                </div>

                <div dir="ltr">
                    <?= e($invoice['invoice_number']) ?>
                </div>


                <div class="footer-actions no-print">

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()">
                        🖨 چاپ
                    </button>

                    <a
                        href="<?= e($pdfUrl) ?>"
                        class="btn btn-pdf"
                        target="_blank"
                        rel="noopener">
                        📄 PDF
                    </a>

                </div>

            </footer>

        </main>

    </div>

</body>

</html>