<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once ROOT_PATH . '/app/services/InvoiceService.php';

$db = db();

$h = static fn($v): string =>
htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$moneyFa = static function ($amount): string {
    return number_format((int)$amount) . ' تومان';
};

$dateTimeFa = static function ($value): string {
    if (!$value) {
        return '---';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return (string)$value;
    }

    return date('Y/m/d - H:i', $timestamp);
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending'   => 'در انتظار تأیید مالی',
        'confirmed' => 'تأیید شده',
        'rejected'  => 'رد شده',
        default     => 'نامشخص',
    };
};

$methodLabel = static function (string $method): string {
    return match ($method) {
        'sheba'         => 'انتقال با شبا',
        'card_to_card'  => 'کارت به کارت',
        'cash_receipt'  => 'فیش نقدی',
        'online'        => 'درگاه پرداخت آنلاین',
        default         => 'پرداخت',
    };
};


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Invoice lookup
|--------------------------------------------------------------------------
*/

$invoiceId     = (int)($_GET['id'] ?? 0);
$invoiceNumber = trim((string)($_GET['inv'] ?? ''));

$invoice = null;

try {
    if ($invoiceId > 0) {

        $invoice = InvoiceService::find(
            $db,
            $invoiceId
        );
    } elseif ($invoiceNumber !== '') {

        $stmt = $db->prepare("
            SELECT id
            FROM invoices
            WHERE invoice_number = ?
            LIMIT 1
        ");

        $stmt->execute([
            $invoiceNumber
        ]);

        $foundInvoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($foundInvoice) {
            $invoice = InvoiceService::find(
                $db,
                (int)$foundInvoice['id']
            );
        }
    }
} catch (Throwable $e) {
    $invoice = null;
}


/*
|--------------------------------------------------------------------------
| Invoice not found
|--------------------------------------------------------------------------
*/

if (!$invoice) {

    http_response_code(404);

    header('X-Robots-Tag: noindex, nofollow, noarchive');

?>
    <!doctype html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>فاکتور پیدا نشد | رابطی</title>
        <link rel="stylesheet" href="assets\css\app.css?v=2">
    </head>

    <body class="invoice-page invoice-error-page">

        <main class="invoice-error">

            <div class="invoice-error-icon">!</div>

            <h1>فاکتور پیدا نشد</h1>

            <p>
                فاکتور موردنظر وجود ندارد یا دیگر قابل مشاهده نیست.
            </p>

            <a
                href="/account/orders"
                class="action-btn action-back">
                بازگشت به سفارش‌ها
            </a>

        </main>

    </body>

    </html>
<?php

    exit;
}


/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

$isAdmin = !empty($_SESSION['admin_id']);

$invoiceUserId = (int)(
    $invoice['user_id']
    ?? $invoice['customer_id']
    ?? $invoice['order_user_id']
    ?? 0
);

if (!$isAdmin && $invoiceUserId !== $userId) {

    http_response_code(403);

    header('X-Robots-Tag: noindex, nofollow, noarchive');

?>
    <!doctype html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>دسترسی غیرمجاز | رابطی</title>
        <link rel="stylesheet" href="/assets/css/invoice.css?v=1">
    </head>

    <body class="invoice-page invoice-error-page">

        <main class="invoice-error">

            <div class="invoice-error-icon">!</div>

            <h1>دسترسی غیرمجاز</h1>

            <p>
                شما اجازه مشاهده این فاکتور را ندارید.
            </p>

            <a
                href="/account/orders"
                class="action-btn action-back">
                بازگشت به سفارش‌ها
            </a>

        </main>

    </body>

    </html>
<?php

    exit;
}

/*
|--------------------------------------------------------------------------
| Payment history
|--------------------------------------------------------------------------
| Financial truth:
| - invoices.paid_total = total actually verified/recorded as paid
| - payment_submissions = customer payment requests/history
| - payment_verifications = financial verification details
|--------------------------------------------------------------------------
*/

$paymentStmt = $db->prepare("
    SELECT
        ps.id,
        ps.method,
        ps.claimed_amount,
        ps.reference_no,
        ps.receipt_path,
        ps.transaction_date,
        ps.customer_note,
        ps.status,
        ps.created_at,
        ps.updated_at,

        pv.verified_amount,
        pv.status AS verification_status,
        pv.note AS verification_note,
        pv.verified_by,
        pv.verified_at

    FROM payment_submissions ps

    LEFT JOIN payment_verifications pv
        ON pv.payment_submission_id = ps.id

    WHERE ps.invoice_id = ?

    ORDER BY ps.created_at ASC, ps.id ASC
");

$paymentStmt->execute([
    (int)$invoice['id']
]);

$payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Payment calculations
|--------------------------------------------------------------------------
| Do NOT calculate confirmed paid amount from submissions.
| The authoritative value is invoices.paid_total.
|--------------------------------------------------------------------------
*/

$confirmedPaidFromPayments = max(
    0,
    (int)($invoice['paid_total'] ?? 0)
);

$pendingAmount  = 0;
$rejectedAmount = 0;

$hasPendingPayment = false;

foreach ($payments as &$payment) {

    $claimedAmount = max(
        0,
        (int)($payment['claimed_amount'] ?? 0)
    );

    $verifiedAmount = $payment['verified_amount'] !== null
        ? max(0, (int)$payment['verified_amount'])
        : null;

    $submissionStatus = (string)(
        $payment['status'] ?? ''
    );

    $verificationStatus = (string)(
        $payment['verification_status'] ?? ''
    );

    /*
     * Pending is determined from the customer's submission.
     */
    if ($submissionStatus === 'pending') {

        $pendingAmount += $claimedAmount;
        $hasPendingPayment = true;
    }

    /*
     * Rejected submissions are historical only.
     */
    if ($submissionStatus === 'rejected') {

        $rejectedAmount += $claimedAmount;
    }

    /*
     * Keep normalized values available to the invoice template.
     */
    $payment['_claimed_amount'] = $claimedAmount;
    $payment['_verified_amount'] = $verifiedAmount;
    $payment['_submission_status'] = $submissionStatus;
    $payment['_verification_status'] = $verificationStatus;
}

unset($payment);


/*
|--------------------------------------------------------------------------
| Invoice data
|--------------------------------------------------------------------------
*/

$items  = $invoice['items'] ?? [];
$totals = $invoice['totals'] ?? [];

$invoiceNo = (string)(
    $invoice['invoice_number']
    ?? $invoice['number']
    ?? $invoiceNumber
    ?? '---'
);

$orderNo = (string)(
    $invoice['order_number']
    ?? $invoice['order']['order_number']
    ?? '---'
);

$invoiceDate = (string)(
    $invoice['issued_at']
    ?? $invoice['created_at']
    ?? $invoice['invoice_date']
    ?? $invoice['date']
    ?? ''
);

if ($invoiceDate !== '') {

    $timestamp = strtotime($invoiceDate);

    if ($timestamp !== false) {
        $invoiceDateDisplay = date('Y/m/d', $timestamp);
    } else {
        $invoiceDateDisplay = $invoiceDate;
    }
} else {

    $invoiceDateDisplay = date('Y/m/d');
}


/*
|--------------------------------------------------------------------------
| Customer information
|--------------------------------------------------------------------------
*/

$customerName = (string)(
    $invoice['customer_name']
    ?? $invoice['shipping_name']
    ?? $invoice['user_name']
    ?? '---'
);

$customerMobile = (string)(
    $invoice['customer_mobile']
    ?? $invoice['shipping_mobile']
    ?? $invoice['mobile']
    ?? '---'
);

$customerProvince = (string)(
    $invoice['shipping_province']
    ?? $invoice['province']
    ?? ''
);

$customerCity = (string)(
    $invoice['shipping_city']
    ?? $invoice['city']
    ?? ''
);

$customerAddress = (string)(
    $invoice['shipping_address']
    ?? $invoice['address']
    ?? ''
);

$customerPostal = (string)(
    $invoice['shipping_postal']
    ?? $invoice['postal_code']
    ?? ''
);

$customerNote = (string)(
    $invoice['customer_note']
    ?? $invoice['note']
    ?? ''
);


/*
|--------------------------------------------------------------------------
| Financial totals
|--------------------------------------------------------------------------
*/

$subtotal = (int)(
    $totals['subtotal']
    ?? $invoice['subtotal']
    ?? 0
);

$discount = (int)(
    $totals['discount']
    ?? $invoice['discount_total']
    ?? 0
);

$afterDiscount = (int)(
    $totals['after_discount']
    ?? max(0, $subtotal - $discount)
);

$shipping = (int)(
    $totals['shipping']
    ?? $invoice['shipping_fee']
    ?? 0
);

$tax = (int)(
    $totals['tax']
    ?? 0
);

$total = (int)(
    $totals['total']
    ?? $invoice['invoice_total']
    ?? $invoice['total']
    ?? ($afterDiscount + $shipping + $tax)
);


/*
|--------------------------------------------------------------------------
| IMPORTANT:
| paid_total is the confirmed payment total maintained by finance.
|
| We DO NOT add pending payments here.
| We DO NOT subtract pending payments from remaining.
|--------------------------------------------------------------------------
*/

$paid = max(
    0,
    (int)(
        $invoice['paid_total']
        ?? $totals['paid']
        ?? $confirmedPaidFromPayments
    )
);

$paid = min(
    $paid,
    max(0, $total)
);

$remaining = max(
    0,
    $total - $paid
);


/*
|--------------------------------------------------------------------------
| Reconciliation
|--------------------------------------------------------------------------
|
| These values are authoritative financial results entered by admin.
| We do NOT recalculate them from payment records.
|--------------------------------------------------------------------------
*/

$reconciliationType = (string)(
    $invoice['reconciliation_type']
    ?? 'settled'
);

$reconciliationAmount = max(
    0,
    (int)(
        $invoice['reconciliation_amount']
        ?? 0
    )
);

$reconciliationLabel = match ($reconciliationType) {
    'debtor'   => 'بدهکار',
    'creditor' => 'بستانکار',
    'settled'  => 'تسویه',
    default    => 'ثبت نشده',
};


/*
|--------------------------------------------------------------------------
| Payment CTA rules
|--------------------------------------------------------------------------
*/

$invoiceStatus = (string)(
    $invoice['status']
    ?? 'unpaid'
);

$canPay = (
    !$hasPendingPayment
    && $remaining > 0
    && $reconciliationType !== 'creditor'
    && $invoiceStatus !== 'void'
);

$logoPath = '/Image/logo3.jpg';

$itemsCount = 0;

foreach ($items as $item) {
    $itemsCount += (int)(
        $item['qty'] ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| SEO / privacy
|--------------------------------------------------------------------------
*/

header(
    'X-Robots-Tag: noindex, nofollow, noarchive'
);

?>
<!doctype html>

<html
    lang="fa"
    dir="rtl">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <meta
        name="robots"
        content="noindex, nofollow, noarchive">

    <meta
        name="googlebot"
        content="noindex, nofollow, noarchive">

    <title>
        فاکتور <?= $h($invoiceNo) ?> | رابطی
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/app.css?v=2">

</head>

<body class="invoice-page">


    <div class="invoice-actions">

        <div class="actions-right">

            <button
                type="button"
                class="action-btn action-print"
                onclick="window.print()">
                🖨 چاپ
            </button>

            <a
                href="/invoice-pdf.php?inv=<?= rawurlencode($invoiceNo) ?>"
                class="action-btn action-pdf">
                📄 دریافت PDF
            </a>

        </div>

        <div class="actions-left">

            <a
                href="/account/orders"
                class="action-btn action-back">
                ← بازگشت
            </a>

        </div>

    </div>


    <main class="invoice-sheet">


        <!-- =========================================================
         HEADER
    ========================================================== -->

        <header class="invoice-header">

            <div class="invoice-header-top">

                <div class="brand-block">

                    <img
                        src="<?= $h($logoPath) ?>"
                        alt="رابطی"
                        class="brand-logo">

                    <div class="brand-text">

                        <div class="brand-name">
                            رابطی
                        </div>

                        <div class="brand-subtitle">
                            تولید مستقیم و فروش عمده جوراب
                        </div>

                    </div>

                </div>


                <div class="invoice-title">

                    <div class="invoice-title-label">
                        فاکتور فروش
                    </div>

                    <div class="invoice-title-number">
                        <?= $h($invoiceNo) ?>
                    </div>

                </div>

            </div>


            <div class="invoice-meta">

                <div class="meta-card">

                    <span class="meta-label">
                        شماره فاکتور
                    </span>

                    <strong class="meta-value ltr">
                        <?= $h($invoiceNo) ?>
                    </strong>

                </div>


                <div class="meta-card">

                    <span class="meta-label">
                        شماره سفارش
                    </span>

                    <strong class="meta-value ltr">
                        <?= $h($orderNo) ?>
                    </strong>

                </div>


                <div class="meta-card">

                    <span class="meta-label">
                        تاریخ صدور
                    </span>

                    <strong class="meta-value">
                        <?= $h($invoiceDateDisplay) ?>
                    </strong>

                </div>

            </div>

        </header>


        <div class="invoice-content">


            <!-- =====================================================
             PARTIES
        ====================================================== -->

            <section class="parties">

                <div class="party-card">

                    <div class="party-head">

                        <div class="party-title">
                            خریدار
                        </div>

                        <div class="party-badge">
                            مشتری
                        </div>

                    </div>


                    <div class="party-row">

                        <span class="party-row-label">
                            نام
                        </span>

                        <strong class="party-row-value">
                            <?= $h($customerName) ?>
                        </strong>

                    </div>


                    <div class="party-row">

                        <span class="party-row-label">
                            موبایل
                        </span>

                        <strong class="party-row-value ltr">
                            <?= $h($customerMobile) ?>
                        </strong>

                    </div>


                    <?php if ($customerProvince !== '' || $customerCity !== ''): ?>

                        <div class="party-row">

                            <span class="party-row-label">
                                شهر
                            </span>

                            <strong class="party-row-value">
                                <?= $h(
                                    trim(
                                        $customerProvince
                                            . '، '
                                            . $customerCity,
                                        '، '
                                    )
                                ) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                    <?php if ($customerAddress !== ''): ?>

                        <div class="party-row">

                            <span class="party-row-label">
                                آدرس
                            </span>

                            <strong class="party-row-value party-address">
                                <?= $h($customerAddress) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                    <?php if ($customerPostal !== ''): ?>

                        <div class="party-row">

                            <span class="party-row-label">
                                کد پستی
                            </span>

                            <strong class="party-row-value ltr">
                                <?= $h($customerPostal) ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>


                <div class="party-card seller-card">

                    <div class="party-head">

                        <div class="party-title">
                            فروشنده
                        </div>

                        <div class="party-badge seller-badge">
                            رابطی
                        </div>

                    </div>


                    <div class="party-row">

                        <span class="party-row-label">
                            نام
                        </span>

                        <strong class="party-row-value">
                            کارگاه تولید جوراب رابطی
                        </strong>

                    </div>


                    <div class="party-row">

                        <span class="party-row-label">
                            نوع فروش
                        </span>

                        <strong class="party-row-value">
                            عمده و مستقیم از تولیدکننده
                        </strong>

                    </div>


                    <div class="party-row">

                        <span class="party-row-label">
                            وضعیت
                        </span>

                        <strong class="party-row-value">
                            فاکتور رسمی سفارش
                        </strong>

                    </div>

                </div>

            </section>


            <!-- =====================================================
             PRODUCTS
        ====================================================== -->

            <section class="products-section">

                <div class="section-title">
                    اقلام سفارش
                </div>


                <?php if (!empty($items)): ?>

                    <!-- Desktop -->

                    <div class="table-wrap">

                        <table class="products-table">

                            <thead>

                                <tr>

                                    <th class="col-index">
                                        #
                                    </th>

                                    <th>
                                        محصول
                                    </th>

                                    <th>
                                        تعداد
                                    </th>

                                    <th>
                                        قیمت پایه
                                    </th>

                                    <th>
                                        تخفیف
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

                                <?php foreach ($items as $index => $item): ?>

                                    <?php

                                    $productName = (string)(
                                        $item['product_name']
                                        ?? $item['name']
                                        ?? 'محصول'
                                    );

                                    $productCode = (string)(
                                        $item['product_code']
                                        ?? $item['code']
                                        ?? ''
                                    );

                                    $qty = (int)(
                                        $item['qty']
                                        ?? 0
                                    );

                                    $baseUnitPrice = (int)(
                                        $item['base_unit_price']
                                        ?? $item['base_price']
                                        ?? $item['price']
                                        ?? 0
                                    );

                                    $discountPercent = (float)(
                                        $item['discount_percent']
                                        ?? 0
                                    );

                                    $discountAmount = (int)(
                                        $item['discount_amount']
                                        ?? 0
                                    );

                                    $unitPrice = (int)(
                                        $item['unit_price']
                                        ?? $baseUnitPrice
                                    );

                                    $lineTotal = (int)(
                                        $item['line_total']
                                        ?? ($unitPrice * $qty)
                                    );

                                    ?>

                                    <tr>

                                        <td class="col-index">
                                            <?= $index + 1 ?>
                                        </td>


                                        <td>

                                            <div class="product-name">
                                                <?= $h($productName) ?>
                                            </div>

                                            <?php if ($productCode !== ''): ?>

                                                <div class="product-code">
                                                    کد:
                                                    <?= $h($productCode) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <span class="qty-value">
                                                <?= number_format($qty) ?>
                                            </span>

                                            جفت

                                        </td>


                                        <td class="price">
                                            <?= $moneyFa($baseUnitPrice) ?>
                                        </td>


                                        <td class="discount-cell">

                                            <?php if ($discountPercent > 0): ?>

                                                <span class="discount-percent">
                                                    <?= rtrim(
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
                                                    ) ?>٪
                                                </span>

                                                <?php if ($discountAmount > 0): ?>

                                                    <span class="discount-amount">
                                                        <?= $moneyFa($discountAmount) ?>
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="no-discount">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td class="price final-price">
                                            <?= $moneyFa($unitPrice) ?>
                                        </td>


                                        <td class="price line-total">
                                            <?= $moneyFa($lineTotal) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- Mobile -->

                    <div class="mobile-items">

                        <?php foreach ($items as $index => $item): ?>

                            <?php

                            $productName = (string)(
                                $item['product_name']
                                ?? $item['name']
                                ?? 'محصول'
                            );

                            $productCode = (string)(
                                $item['product_code']
                                ?? $item['code']
                                ?? ''
                            );

                            $qty = (int)(
                                $item['qty']
                                ?? 0
                            );

                            $baseUnitPrice = (int)(
                                $item['base_unit_price']
                                ?? $item['base_price']
                                ?? $item['price']
                                ?? 0
                            );

                            $discountPercent = (float)(
                                $item['discount_percent']
                                ?? 0
                            );

                            $discountAmount = (int)(
                                $item['discount_amount']
                                ?? 0
                            );

                            $unitPrice = (int)(
                                $item['unit_price']
                                ?? $baseUnitPrice
                            );

                            $lineTotal = (int)(
                                $item['line_total']
                                ?? ($unitPrice * $qty)
                            );

                            ?>

                            <article class="mobile-item">

                                <div class="mobile-item-head">

                                    <div>

                                        <div class="mobile-item-index">
                                            <?= $index + 1 ?>
                                        </div>

                                        <div class="mobile-item-name">
                                            <?= $h($productName) ?>
                                        </div>

                                        <?php if ($productCode !== ''): ?>

                                            <div class="product-code">
                                                کد:
                                                <?= $h($productCode) ?>
                                            </div>

                                        <?php endif; ?>

                                    </div>


                                    <strong class="mobile-item-total">
                                        <?= $moneyFa($lineTotal) ?>
                                    </strong>

                                </div>


                                <div class="mobile-item-grid">

                                    <div class="mobile-detail">

                                        <span class="mobile-detail-label">
                                            تعداد
                                        </span>

                                        <strong class="mobile-detail-value">
                                            <?= number_format($qty) ?>
                                            جفت
                                        </strong>

                                    </div>


                                    <div class="mobile-detail">

                                        <span class="mobile-detail-label">
                                            قیمت پایه
                                        </span>

                                        <strong class="mobile-detail-value">
                                            <?= $moneyFa($baseUnitPrice) ?>
                                        </strong>

                                    </div>


                                    <div class="mobile-detail">

                                        <span class="mobile-detail-label">
                                            تخفیف
                                        </span>

                                        <strong class="mobile-detail-value discount-cell">

                                            <?php if ($discountPercent > 0): ?>

                                                <?= rtrim(
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
                                                ) ?>٪

                                            <?php else: ?>

                                                —

                                            <?php endif; ?>

                                        </strong>

                                    </div>


                                    <div class="mobile-detail">

                                        <span class="mobile-detail-label">
                                            قیمت واحد نهایی
                                        </span>

                                        <strong class="mobile-detail-value final-price">
                                            <?= $moneyFa($unitPrice) ?>
                                        </strong>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-items">
                        موردی برای نمایش در این فاکتور وجود ندارد.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =====================================================
             PAYMENT HISTORY
        ====================================================== -->

            <section class="payment-section">

                <div class="section-title">
                    تاریخچه پرداخت
                </div>


                <?php if (!empty($payments)): ?>

                    <div class="payment-history">

                        <?php foreach ($payments as $payment): ?>

                            <?php

                            $paymentStatus = (string)(
                                $payment['status'] ?? ''
                            );

                            $paymentMethod = (string)(
                                $payment['method'] ?? ''
                            );

                            $paymentAmount = max(
                                0,
                                (int)(
                                    $payment['amount'] ?? 0
                                )
                            );

                            $paymentCreatedAt = $payment['created_at']
                                ?? null;

                            $paymentConfirmedAt = $payment['confirmed_at']
                                ?? null;

                            $referenceNo = trim(
                                (string)(
                                    $payment['reference_no']
                                    ?? ''
                                )
                            );

                            $adminNote = trim(
                                (string)(
                                    $payment['admin_note']
                                    ?? ''
                                )
                            );

                            ?>

                            <article
                                class="
                                payment-item
                                payment-item-<?= $h($paymentStatus) ?>
                            ">

                                <div class="payment-item-main">

                                    <div class="payment-item-title">

                                        <span class="payment-method">
                                            <?= $h(
                                                $methodLabel(
                                                    $paymentMethod
                                                )
                                            ) ?>
                                        </span>

                                        <span
                                            class="
                                            payment-status
                                            payment-status-<?= $h($paymentStatus) ?>
                                        ">
                                            <?= $h(
                                                $statusLabel(
                                                    $paymentStatus
                                                )
                                            ) ?>
                                        </span>

                                    </div>


                                    <div class="payment-item-amount">
                                        <?= $moneyFa($paymentAmount) ?>
                                    </div>

                                </div>


                                <div class="payment-item-meta">

                                    <div>

                                        <span class="payment-meta-label">
                                            ثبت پرداخت
                                        </span>

                                        <span>
                                            <?= $h(
                                                $dateTimeFa(
                                                    $paymentCreatedAt
                                                )
                                            ) ?>
                                        </span>

                                    </div>


                                    <?php if ($paymentStatus === 'confirmed' && $paymentConfirmedAt): ?>

                                        <div>

                                            <span class="payment-meta-label">
                                                تأیید مالی
                                            </span>

                                            <span>
                                                <?= $h(
                                                    $dateTimeFa(
                                                        $paymentConfirmedAt
                                                    )
                                                ) ?>
                                            </span>

                                        </div>

                                    <?php endif; ?>


                                    <?php if ($referenceNo !== ''): ?>

                                        <div>

                                            <span class="payment-meta-label">
                                                شماره پیگیری
                                            </span>

                                            <span class="ltr">
                                                <?= $h($referenceNo) ?>
                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <?php if ($adminNote !== ''): ?>

                                    <div class="payment-admin-note">

                                        <strong>
                                            توضیح مالی:
                                        </strong>

                                        <?= $h($adminNote) ?>

                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="payment-empty">
                        هنوز پرداختی برای این فاکتور ثبت نشده است.
                    </div>

                <?php endif; ?>


                <?php if ($hasPendingPayment): ?>

                    <div class="payment-alert payment-alert-pending">

                        <span class="payment-alert-icon">
                            ⏳
                        </span>

                        <div>

                            <strong>
                                پرداخت شما در انتظار تأیید مالی است.
                            </strong>

                            <p>
                                مبلغ
                                <?= $moneyFa($pendingAmount) ?>
                                ثبت شده اما هنوز توسط واحد مالی تأیید نشده است.
                                تا زمان تعیین تکلیف این پرداخت، امکان ثبت پرداخت جدید وجود ندارد.
                            </p>

                        </div>

                    </div>

                <?php elseif ($remaining <= 0): ?>

                    <div class="payment-alert payment-alert-success">

                        <span class="payment-alert-icon">
                            ✓
                        </span>

                        <div>

                            <strong>
                                این فاکتور به‌طور کامل پرداخت شده است.
                            </strong>

                            <p>
                                مانده قابل پرداخت این فاکتور صفر است.
                            </p>

                        </div>

                    </div>

                <?php elseif ($reconciliationType === 'creditor'): ?>

                    <div class="payment-alert payment-alert-creditor">

                        <span class="payment-alert-icon">
                            ✓
                        </span>

                        <div>

                            <strong>
                                وضعیت مالی: بستانکار
                            </strong>

                            <p>
                                مبلغ بستانکاری ثبت‌شده:
                                <?= $moneyFa($reconciliationAmount) ?>
                                <br>
                                در این وضعیت نیازی به ثبت پرداخت جدید نیست.
                            </p>

                        </div>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =====================================================
             BOTTOM
        ====================================================== -->

            <section class="invoice-bottom">


                <div class="bottom-left">

                    <?php if ($customerNote !== ''): ?>

                        <div class="note-box">

                            <div class="note-box-title">
                                توضیحات مشتری
                            </div>

                            <p>
                                <?= nl2br($h($customerNote)) ?>
                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Reconciliation -->

                    <div class="reconciliation-box">

                        <div class="reconciliation-title">
                            نتیجه مالی
                        </div>


                        <?php if ($reconciliationType === 'debtor'): ?>

                            <div class="reconciliation-result debtor">

                                <span>
                                    وضعیت
                                </span>

                                <strong>
                                    بدهکار
                                </strong>

                            </div>

                            <div class="reconciliation-amount">

                                <span>
                                    مبلغ بدهی ثبت‌شده
                                </span>

                                <strong>
                                    <?= $moneyFa($reconciliationAmount) ?>
                                </strong>

                            </div>


                        <?php elseif ($reconciliationType === 'creditor'): ?>

                            <div class="reconciliation-result creditor">

                                <span>
                                    وضعیت
                                </span>

                                <strong>
                                    بستانکار
                                </strong>

                            </div>

                            <div class="reconciliation-amount">

                                <span>
                                    مبلغ بستانکاری ثبت‌شده
                                </span>

                                <strong>
                                    <?= $moneyFa($reconciliationAmount) ?>
                                </strong>

                            </div>


                        <?php elseif ($reconciliationType === 'settled'): ?>

                            <div class="reconciliation-result settled">

                                <span>
                                    وضعیت
                                </span>

                                <strong>
                                    تسویه
                                </strong>

                            </div>


                        <?php else: ?>

                            <div class="reconciliation-result">

                                <span>
                                    وضعیت
                                </span>

                                <strong>
                                    <?= $h($reconciliationLabel) ?>
                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if ($itemsCount > 0): ?>

                        <div class="items-summary">

                            <span>
                                تعداد کل اقلام
                            </span>

                            <strong>
                                <?= number_format($itemsCount) ?>
                                جفت
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =================================================
                 TOTALS
            ================================================== -->

                <div class="totals-box">

                    <div class="totals-head">
                        خلاصه مالی
                    </div>


                    <div class="total-row">

                        <span class="total-label">
                            جمع قیمت پایه
                        </span>

                        <span class="total-value">
                            <?= $moneyFa($subtotal) ?>
                        </span>

                    </div>


                    <?php if ($discount > 0): ?>

                        <div class="total-row discount-total">

                            <span class="total-label">
                                تخفیف سفارش
                            </span>

                            <span class="total-value">
                                -
                                <?= $moneyFa($discount) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <div class="total-row">

                        <span class="total-label">
                            مبلغ پس از تخفیف
                        </span>

                        <span class="total-value">
                            <?= $moneyFa($afterDiscount) ?>
                        </span>

                    </div>


                    <?php if ($shipping > 0): ?>

                        <div class="total-row">

                            <span class="total-label">
                                هزینه ارسال
                            </span>

                            <span class="total-value">
                                <?= $moneyFa($shipping) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($tax > 0): ?>

                        <div class="total-row">

                            <span class="total-label">
                                مالیات
                            </span>

                            <span class="total-value">
                                <?= $moneyFa($tax) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <div class="total-row grand-total">

                        <span class="total-label">
                            مبلغ نهایی فاکتور
                        </span>

                        <strong class="total-value">
                            <?= $moneyFa($total) ?>
                        </strong>

                    </div>


                    <div class="total-row paid-row">

                        <span class="total-label">
                            پرداخت تأییدشده
                        </span>

                        <strong class="total-value">
                            <?= $moneyFa($paid) ?>
                        </strong>

                    </div>


                    <?php if ($pendingAmount > 0): ?>

                        <div class="total-row pending-row">

                            <span class="total-label">
                                در انتظار تأیید مالی
                            </span>

                            <strong class="total-value">
                                <?= $moneyFa($pendingAmount) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                    <?php if ($rejectedAmount > 0): ?>

                        <div class="total-row rejected-row">

                            <span class="total-label">
                                پرداخت‌های ردشده
                            </span>

                            <strong class="total-value">
                                <?= $moneyFa($rejectedAmount) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                    <div class="total-row remaining-row">

                        <span class="total-label">
                            مانده فاکتور
                        </span>

                        <strong class="total-value">
                            <?= $moneyFa($remaining) ?>
                        </strong>

                    </div>


                    <?php if ($canPay): ?>

                        <div class="payment-action">

                            <a
                                href="/payment.php?inv=<?= rawurlencode($invoiceNo) ?>"
                                class="payment-button">
                                💳 پرداخت مانده فاکتور
                            </a>

                            <div class="payment-action-hint">
                                مبلغ قابل پرداخت:
                                <strong>
                                    <?= $moneyFa($remaining) ?>
                                </strong>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <div class="thank-you">
                از اعتماد شما به رابطی سپاسگزاریم.
            </div>

        </div>


        <!-- =========================================================
         FOOTER
    ========================================================== -->

        <footer class="invoice-footer">

            <div class="footer-brand">
                رابطی — تولید مستقیم جوراب
            </div>

            <div class="footer-contact">
                تمامی اطلاعات مالی این فاکتور از سیستم مالی ثبت‌شده سفارش خوانده می‌شود.
            </div>

        </footer>

    </main>


</body>

</html>