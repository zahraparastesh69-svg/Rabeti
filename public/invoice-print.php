<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/InvoiceService.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$userId = (int)(
    $_SESSION['admin_id']
    ?? $_SESSION['customer_id']
    ?? 0
);

if ($userId <= 0) {
    header('Location: /');
    exit;
}

$isAdmin = !empty($_SESSION['admin_id']);

$invoiceNumber = trim(
    (string)(
        $_GET['inv']
        ?? $_GET['invoice']
        ?? ''
    )
);

if ($invoiceNumber === '') {
    http_response_code(404);
    exit('فاکتور پیدا نشد.');
}

$invoice = InvoiceService::find(
    $invoiceNumber,
    $userId,
    $isAdmin
);

if (!$invoice) {
    http_response_code(404);
    exit('فاکتور پیدا نشد.');
}

if (!InvoiceService::authorize(
    $invoice,
    $userId,
    $isAdmin
)) {
    http_response_code(403);
    exit('دسترسی غیرمجاز.');
}

$items = $invoice['items'] ?? [];
$totals = $invoice['totals'] ?? [];

function e(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function fa(string|int|float $value): string
{
    return InvoiceService::faDigits(
        (string)$value
    );
}

function moneyFa(string|int|float $value): string
{
    return InvoiceService::money($value);
}

$statusLabel = InvoiceService::statusLabel(
    (string)$invoice['invoice_status']
);

$orderStatusLabel = InvoiceService::orderStatusLabel(
    (string)$invoice['order_status']
);

$statusClass = match ($invoice['invoice_status']) {
    'paid'    => 'is-paid',
    'partial' => 'is-partial',
    'void'    => 'is-void',
    default   => 'is-unpaid',
};

$customerName = trim(
    (string)($invoice['customer_name'] ?? '')
);

$companyName = trim(
    (string)($invoice['customer_company'] ?? '')
);

$customerMobile = trim(
    (string)($invoice['customer_mobile'] ?? '')
);

$orderNumber = trim(
    (string)($invoice['order_number'] ?? '')
);

$issueDate = InvoiceService::dateFa(
    $invoice['issue_date'] ?? null
);

$dueDate = InvoiceService::dateFa(
    $invoice['due_date'] ?? null
);
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta
        name="robots"
        content="noindex, nofollow, noarchive">

    <meta
        name="googlebot"
        content="noindex, nofollow, noarchive">

    <title>
        فاکتور <?= e($invoice['invoice_number']) ?>
    </title>

    <style>
        :root {
            --ink: #202124;
            --muted: #6b7280;
            --line: #e7e7e7;
            --soft: #f7f7f7;
            --accent: #111827;
            --success: #147a45;
            --warning: #a16207;
            --danger: #b42318;
        }

        * {
            box-sizing: border-box;
        }

        html {
            direction: rtl;
            background: #f2f3f5;
        }

        body {
            margin: 0;
            padding: 20px;
            color: var(--ink);
            background: #f2f3f5;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
            font-size: 13px;
            line-height: 1.7;
        }

        button,
        a {
            font: inherit;
        }

        .invoice-page {
            width: min(900px, 100%);
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
        }

        .invoice-inner {
            padding: 24px 26px;
        }

        /* Toolbar */

        .toolbar {
            width: min(900px, 100%);
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .toolbar-right,
        .toolbar-left {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #ddd;
            background: #fff;
            color: #222;
            text-decoration: none;
            border-radius: 8px;
            padding: 7px 11px;
            cursor: pointer;
            white-space: nowrap;
        }

        .toolbar .primary {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        /* Header */

        .invoice-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            min-width: 0;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .brand-subtitle {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .invoice-meta {
            text-align: left;
            min-width: 175px;
        }

        .invoice-number {
            font-size: 16px;
            font-weight: 800;
            direction: ltr;
            unicode-bidi: plaintext;
        }

        .invoice-date {
            color: var(--muted);
            font-size: 11px;
            margin-top: 3px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            margin-top: 6px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }

        .is-paid {
            background: #eaf7ef;
            color: var(--success);
        }

        .is-partial {
            background: #fff7df;
            color: var(--warning);
        }

        .is-unpaid {
            background: #fff0ee;
            color: var(--danger);
        }

        .is-void {
            background: #f1f1f1;
            color: #666;
        }

        /* Information */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 16px 0;
        }

        .info-card {
            background: var(--soft);
            border-radius: 9px;
            padding: 10px 12px;
            min-width: 0;
        }

        .info-label {
            color: var(--muted);
            font-size: 10px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 12px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        /* Desktop items */

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th {
            background: #f7f7f7;
            color: #555;
            font-size: 10px;
            font-weight: 700;
            padding: 8px 7px;
            border-bottom: 1px solid var(--line);
        }

        .items td {
            padding: 8px 7px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            font-size: 11px;
        }

        .items .col-number {
            width: 34px;
            text-align: center;
        }

        .items .col-qty {
            width: 58px;
            text-align: center;
        }

        .items .col-price {
            width: 105px;
            text-align: left;
        }

        .items .col-total {
            width: 115px;
            text-align: left;
        }

        .product-name {
            font-weight: 700;
        }

        .product-code {
            color: var(--muted);
            font-size: 9px;
            margin-top: 1px;
            direction: ltr;
            unicode-bidi: plaintext;
        }

        .product-detail {
            color: var(--muted);
            font-size: 9px;
            margin-top: 1px;
        }

        .number {
            direction: ltr;
            unicode-bidi: plaintext;
            white-space: nowrap;
        }

        /* Mobile cards */

        .mobile-items {
            display: none;
        }

        .mobile-item {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
        }

        .mobile-item-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .mobile-item-number {
            color: var(--muted);
            font-size: 10px;
        }

        .mobile-item-name {
            font-weight: 800;
            font-size: 12px;
        }

        .mobile-item-code {
            color: var(--muted);
            font-size: 9px;
            direction: ltr;
            unicode-bidi: plaintext;
        }

        .mobile-item-detail {
            color: var(--muted);
            font-size: 9px;
            margin-top: 2px;
        }

        .mobile-item-bottom {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 7px;
            margin-top: 9px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
        }

        .mobile-stat-label {
            color: var(--muted);
            font-size: 9px;
        }

        .mobile-stat-value {
            font-weight: 700;
            font-size: 10px;
            margin-top: 1px;
        }

        /* Totals */

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 285px;
            gap: 22px;
            margin-top: 17px;
        }

        .note-box {
            color: var(--muted);
            font-size: 10px;
        }

        .note-title {
            font-weight: 700;
            color: #555;
            margin-bottom: 3px;
        }

        .totals {
            width: 100%;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 4px 0;
            font-size: 11px;
        }

        .total-row+.total-row {
            border-top: 1px solid #f0f0f0;
        }

        .total-final {
            margin-top: 5px;
            padding: 9px 0 0;
            border-top: 1px solid #222;
            font-size: 14px;
            font-weight: 800;
        }

        /* Footer */

        .invoice-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 9px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        /* Mobile */

        @media (max-width: 650px) {

            body {
                padding: 8px;
                background: #f2f3f5;
                font-size: 12px;
            }

            .toolbar {
                margin-bottom: 8px;
                flex-wrap: wrap;
            }

            .toolbar-left {
                width: 100%;
                order: 2;
            }

            .toolbar-right {
                width: 100%;
                order: 1;
                justify-content: space-between;
            }

            .toolbar a,
            .toolbar button {
                flex: 1;
                text-align: center;
                padding: 8px 7px;
                font-size: 11px;
            }

            .invoice-page {
                border-radius: 11px;
            }

            .invoice-inner {
                padding: 15px 13px;
            }

            .invoice-head {
                gap: 10px;
            }

            .brand-title {
                font-size: 16px;
            }

            .brand-subtitle {
                font-size: 9px;
            }

            .invoice-meta {
                min-width: 0;
                text-align: left;
            }

            .invoice-number {
                font-size: 12px;
            }

            .invoice-date {
                font-size: 9px;
            }

            .status {
                font-size: 9px;
                padding: 2px 6px;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 7px;
                margin: 11px 0;
            }

            .info-card {
                padding: 8px 9px;
            }

            .info-label {
                font-size: 8px;
            }

            .info-value {
                font-size: 10px;
            }

            .desktop-items {
                display: none;
            }

            .mobile-items {
                display: block;
            }

            .bottom-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-top: 12px;
            }

            .totals {
                background: var(--soft);
                border-radius: 9px;
                padding: 9px 11px;
            }

            .invoice-footer {
                flex-direction: column;
                gap: 3px;
            }
        }

        /* Print */

        @media print {

            @page {
                size: A4;
                margin: 8mm;
            }

            html,
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            body {
                font-size: 10px;
            }

            .toolbar {
                display: none !important;
            }

            .invoice-page {
                width: 100%;
                max-width: none;
                margin: 0;
                border: 0;
                border-radius: 0;
                overflow: visible;
            }

            .invoice-inner {
                padding: 0;
            }

            .invoice-head {
                padding-bottom: 8px;
            }

            .info-grid {
                margin: 9px 0;
            }

            .info-card {
                padding: 6px 8px;
            }

            .items th,
            .items td {
                padding: 5px 5px;
            }

            .bottom-grid {
                margin-top: 9px;
            }

            .invoice-footer {
                margin-top: 9px;
            }

            .mobile-items {
                display: none !important;
            }

            .desktop-items {
                display: table !important;
            }

            .invoice-page,
            .info-card,
            tr,
            .bottom-grid {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <div class="toolbar">

        <div class="toolbar-right">

            <a href="/"
                aria-label="بازگشت به صفحه اصلی">
                خانه
            </a>

            <button
                type="button"
                onclick="history.back()">
                بازگشت
            </button>

        </div>

        <div class="toolbar-left">

            <button
                type="button"
                onclick="window.print()"
                class="primary">
                چاپ
            </button>

            <a
                href="/invoice-pdf?inv=<?= rawurlencode($invoice['invoice_number']) ?>"
                target="_blank"
                rel="noopener">
                PDF
            </a>

        </div>

    </div>

    <main class="invoice-page">

        <div class="invoice-inner">

            <header class="invoice-head">

                <div class="brand">
                    <div class="brand-title">
                        فاکتور فروش
                    </div>

                    <div class="brand-subtitle">
                        فروش عمده جوراب
                    </div>
                </div>

                <div class="invoice-meta">

                    <div class="invoice-number">
                        <?= e($invoice['invoice_number']) ?>
                    </div>

                    <div class="invoice-date">
                        تاریخ صدور:
                        <?= e($issueDate) ?>
                    </div>

                    <div class="status <?= e($statusClass) ?>">
                        <?= e($statusLabel) ?>
                    </div>

                </div>

            </header>


            <section class="info-grid">

                <div class="info-card">

                    <div class="info-label">
                        مشتری
                    </div>

                    <div class="info-value">
                        <?= e($customerName ?: '—') ?>
                    </div>

                </div>


                <?php if ($companyName !== ''): ?>

                    <div class="info-card">

                        <div class="info-label">
                            شرکت / فروشگاه
                        </div>

                        <div class="info-value">
                            <?= e($companyName) ?>
                        </div>

                    </div>

                <?php else: ?>

                    <div class="info-card">

                        <div class="info-label">
                            شماره تماس
                        </div>

                        <div class="info-value number">
                            <?= e($customerMobile ?: '—') ?>
                        </div>

                    </div>

                <?php endif; ?>


                <div class="info-card">

                    <div class="info-label">
                        سفارش
                    </div>

                    <div class="info-value number">
                        <?= e($orderNumber ?: '—') ?>
                    </div>

                </div>


                <div class="info-card">

                    <div class="info-label">
                        وضعیت سفارش
                    </div>

                    <div class="info-value">
                        <?= e($orderStatusLabel) ?>
                    </div>

                </div>


                <div class="info-card">

                    <div class="info-label">
                        تاریخ سررسید
                    </div>

                    <div class="info-value">
                        <?= e($dueDate) ?>
                    </div>

                </div>


                <div class="info-card">

                    <div class="info-label">
                        تعداد اقلام
                    </div>

                    <div class="info-value">
                        <?= fa(count($items)) ?>
                    </div>

                </div>

            </section>


            <!-- Desktop -->

            <div class="desktop-items">

                <table class="items">

                    <thead>

                        <tr>

                            <th class="col-number">
                                #
                            </th>

                            <th>
                                کالا
                            </th>

                            <th class="col-qty">
                                تعداد
                            </th>

                            <th class="col-price">
                                قیمت واحد
                            </th>

                            <th class="col-total">
                                مبلغ
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($items as $index => $item): ?>

                            <tr>

                                <td class="col-number">
                                    <?= fa($index + 1) ?>
                                </td>

                                <td>

                                    <div class="product-name">
                                        <?= e(
                                            $item['product_name']
                                                ?? 'محصول'
                                        ) ?>
                                    </div>

                                    <?php if (!empty($item['product_code'])): ?>

                                        <div class="product-code">
                                            <?= e($item['product_code']) ?>
                                        </div>

                                    <?php endif; ?>

                                    <div class="product-detail">

                                        <?php
                                        $details = array_filter([
                                            $item['variant_name'] ?? '',
                                            $item['color_name'] ?? '',
                                            $item['size_label'] ?? '',
                                        ]);
                                        ?>

                                        <?= e(
                                            implode(' · ', $details)
                                                ?: '—'
                                        ) ?>

                                    </div>

                                </td>

                                <td class="col-qty">
                                    <?= fa((int)$item['qty']) ?>
                                </td>

                                <td class="col-price number">
                                    <?= e(
                                        moneyFa(
                                            $item['unit_price']
                                                ?? 0
                                        )
                                    ) ?>
                                </td>

                                <td class="col-total number">
                                    <?= e(
                                        moneyFa(
                                            $item['subtotal']
                                                ?? 0
                                        )
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- Mobile -->

            <div class="mobile-items">

                <?php foreach ($items as $index => $item): ?>

                    <article class="mobile-item">

                        <div class="mobile-item-head">

                            <div>

                                <div class="mobile-item-name">
                                    <?= e(
                                        $item['product_name']
                                            ?? 'محصول'
                                    ) ?>
                                </div>

                                <?php if (!empty($item['product_code'])): ?>

                                    <div class="mobile-item-code">
                                        <?= e($item['product_code']) ?>
                                    </div>

                                <?php endif; ?>

                                <?php
                                $details = array_filter([
                                    $item['variant_name'] ?? '',
                                    $item['color_name'] ?? '',
                                    $item['size_label'] ?? '',
                                ]);
                                ?>

                                <div class="mobile-item-detail">
                                    <?= e(
                                        implode(' · ', $details)
                                            ?: '—'
                                    ) ?>
                                </div>

                            </div>

                            <div class="mobile-item-number">
                                <?= fa($index + 1) ?>
                            </div>

                        </div>


                        <div class="mobile-item-bottom">

                            <div>

                                <div class="mobile-stat-label">
                                    تعداد
                                </div>

                                <div class="mobile-stat-value">
                                    <?= fa((int)$item['qty']) ?>
                                </div>

                            </div>

                            <div>

                                <div class="mobile-stat-label">
                                    قیمت واحد
                                </div>

                                <div class="mobile-stat-value number">
                                    <?= e(
                                        moneyFa(
                                            $item['unit_price']
                                                ?? 0
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div>

                                <div class="mobile-stat-label">
                                    مبلغ
                                </div>

                                <div class="mobile-stat-value number">
                                    <?= e(
                                        moneyFa(
                                            $item['subtotal']
                                                ?? 0
                                        )
                                    ) ?>
                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


            <section class="bottom-grid">

                <div class="note-box">

                    <div class="note-title">
                        توضیحات
                    </div>

                    <div>
                        <?= nl2br(
                            e(
                                (string)(
                                    $invoice['order_note']
                                    ?? ''
                                )
                            )
                        ) ?: '—' ?>
                    </div>

                </div>


                <div class="totals">

                    <div class="total-row">

                        <span>
                            جمع کالاها
                        </span>

                        <strong class="number">
                            <?= e(
                                moneyFa(
                                    $totals['subtotal'] ?? 0
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            تخفیف
                        </span>

                        <strong class="number">
                            <?= e(
                                moneyFa(
                                    $totals['discount'] ?? 0
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            مالیات
                        </span>

                        <strong class="number">
                            <?= e(
                                moneyFa(
                                    $totals['tax'] ?? 0
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="total-row total-final">

                        <span>
                            مبلغ نهایی
                        </span>

                        <strong class="number">
                            <?= e(
                                moneyFa(
                                    $totals['total'] ?? 0
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>


            <footer class="invoice-footer">

                <span>
                    این فاکتور به‌صورت الکترونیکی صادر شده است.
                </span>

                <span>
                    شماره فاکتور:
                    <?= e($invoice['invoice_number']) ?>
                </span>

            </footer>

        </div>

    </main>

</body>

</html>