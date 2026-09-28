<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$db = db();

$h = static fn($v) =>
htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

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
| Helpers
|--------------------------------------------------------------------------
*/

function toman(int $amount): string
{
    return number_format($amount) . ' تومان';
}

function orderStatusLabel(string $status): string
{
    return match ($status) {

        'pending_review' => 'در انتظار بررسی',
        'processing'     => 'در حال پردازش',
        'production'     => 'در حال تولید',
        'ready'          => 'آماده ارسال',
        'shipped'        => 'ارسال شده',
        'delivered'      => 'تحویل شده',
        'completed'      => 'تکمیل شده',
        'cancelled'      => 'لغو شده',

        default => $status ?: 'ثبت شده',
    };
}

function orderStatusClass(string $status): string
{
    return match ($status) {

        'pending_review' => 'is-pending',
        'processing'     => 'is-processing',
        'production'     => 'is-processing',
        'ready'          => 'is-ready',
        'shipped'        => 'is-shipped',
        'delivered'      => 'is-delivered',
        'completed'      => 'is-completed',
        'cancelled'      => 'is-cancelled',

        default => '',
    };
}

function paymentStatusLabel(string $status): string
{
    return match ($status) {

        'unpaid'  => 'پرداخت‌نشده',
        'partial' => 'پرداخت ناقص',
        'paid'    => 'پرداخت‌شده',
        'void'    => 'باطل',

        default => 'ثبت نشده',
    };
}

function paymentStatusClass(string $status): string
{
    return match ($status) {

        'unpaid'  => 'is-unpaid',
        'partial' => 'is-partial',
        'paid'    => 'is-paid',
        'void'    => 'is-void',

        default => '',
    };
}

function reconciliationLabel(string $type): string
{
    return match ($type) {

        'settled'  => 'تسویه',
        'debtor'   => 'بدهکار',
        'creditor' => 'بستانکار',

        default => 'ثبت نشده',
    };
}

function reconciliationClass(string $type): string
{
    return match ($type) {

        'settled'  => 'is-settled',
        'debtor'   => 'is-debtor',
        'creditor' => 'is-creditor',

        default => '',
    };
}

function paymentRequestLabel(string $status): string
{
    return match ($status) {

        'pending'   => 'در انتظار بررسی',
        'confirmed' => 'تأیید شده',
        'rejected'  => 'رد شده',

        default => 'ثبت نشده',
    };
}

function paymentRequestClass(string $status): string
{
    return match ($status) {

        'pending'   => 'is-pending',
        'confirmed' => 'is-confirmed',
        'rejected'  => 'is-rejected',

        default => '',
    };
}

/*
|--------------------------------------------------------------------------
| دریافت سفارش‌ها
|--------------------------------------------------------------------------
|
| نکته مهم:
| اینجا paid_total فاکتور را مستقیماً به عنوان «پرداخت تأییدشده»
| نمایش نمی‌دهیم.
|
| مجموع payments.status = confirmed
| مرجع نمایش پرداخت واقعی تأییدشده است.
|
*/

$stmt = $db->prepare("
    SELECT
        o.id,
        o.order_number,
        o.user_id,
        o.status,
        o.total,
        o.subtotal,
        o.discount_total,
        o.shipping_fee,
        o.created_at,

        i.id AS invoice_id,
        i.invoice_number,
        i.total AS invoice_total,
        i.paid_total AS invoice_paid_total,
        i.status AS invoice_status,
        i.reconciliation_type,
        i.reconciliation_amount,

        COALESCE(
            (
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.invoice_id = i.id
                  AND p.user_id = ?
                  AND p.status = 'confirmed'
            ),
            0
        ) AS confirmed_paid_total,

        (
            SELECT p.status
            FROM payments p
            WHERE p.invoice_id = i.id
              AND p.user_id = ?
            ORDER BY p.id DESC
            LIMIT 1
        ) AS last_payment_status,

        (
            SELECT p.amount
            FROM payments p
            WHERE p.invoice_id = i.id
              AND p.user_id = ?
            ORDER BY p.id DESC
            LIMIT 1
        ) AS last_payment_amount,

        (
            SELECT p.created_at
            FROM payments p
            WHERE p.invoice_id = i.id
              AND p.user_id = ?
            ORDER BY p.id DESC
            LIMIT 1
        ) AS last_payment_created_at

    FROM orders o

    LEFT JOIN invoices i
        ON i.order_id = o.id
       AND i.user_id = o.user_id

    WHERE o.user_id = ?

    ORDER BY o.id DESC
");

$stmt->execute([
    $userId,
    $userId,
    $userId,
    $userId,
    $userId,
]);

$orders = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| آماده‌سازی وضعیت مالی هر سفارش
|--------------------------------------------------------------------------
*/

foreach ($orders as &$order) {

    $invoiceTotal = (int)(
        $order['invoice_total']
        ?? $order['total']
        ?? 0
    );

    /*
     * فقط پرداخت‌های CONFIRMED
     */
    $confirmedPaid = (int)(
        $order['confirmed_paid_total']
        ?? 0
    );

    /*
     * نتیجه مالی مستقیماً از invoices
     */
    $reconciliationType = (string)(
        $order['reconciliation_type']
        ?? 'settled'
    );

    $reconciliationAmount = max(
        0,
        (int)(
            $order['reconciliation_amount']
            ?? 0
        )
    );

    /*
     * آخرین وضعیت درخواست پرداخت
     */
    $lastPaymentStatus = (string)(
        $order['last_payment_status']
        ?? ''
    );

    /*
     * آیا یک درخواست پرداخت در انتظار بررسی وجود دارد؟
     */
    $pendingStmt = $db->prepare("
        SELECT
            COUNT(*)
        FROM payments
        WHERE invoice_id = ?
          AND user_id = ?
          AND status = 'pending'
    ");

    $pendingStmt->execute([
        (int)($order['invoice_id'] ?? 0),
        $userId,
    ]);

    $hasPendingPayment =
        (int)$pendingStmt->fetchColumn() > 0;

    /*
     * آیا پرداخت تأییدشده وجود دارد؟
     */
    $hasConfirmedPayment =
        $confirmedPaid > 0;

    /*
     * آیا فاکتور بستانکار است؟
     */
    $isCreditor =
        $reconciliationType === 'creditor';

    /*
     * آیا فاکتور بدهکار است؟
     */
    $isDebtor =
        $reconciliationType === 'debtor';

    /*
     * آیا نتیجه مالی تسویه است؟
     */
    $isSettled =
        $reconciliationType === 'settled';

    /*
     * مبلغ قابل پرداخت:
     *
     * فقط و فقط reconciliation_amount
     * چون طبق منطق پروژه این مقدار توسط امور مالی
     * به عنوان مانده نهایی ثبت می‌شود.
     */
    $payableAmount = 0;

    if ($isDebtor && $reconciliationAmount > 0) {
        $payableAmount = $reconciliationAmount;
    }

    /*
     * اگر بستانکار یا تسویه باشد:
     * هیچ مبلغی برای پرداخت وجود ندارد.
     */
    if ($isCreditor || $isSettled) {
        $payableAmount = 0;
    }

    /*
     * آیا کاربر اجازه ارسال پرداخت جدید دارد؟
     */
    $canPay = (
        $payableAmount > 0
        && !$hasPendingPayment
        && !$isCreditor
        && !$isSettled
    );

    /*
     * وضعیت قابل نمایش پرداخت
     */
    if ($hasPendingPayment) {

        $displayPaymentStatus = 'pending';
    } elseif ($hasConfirmedPayment) {

        $displayPaymentStatus = 'confirmed';
    } else {

        $displayPaymentStatus = 'none';
    }

    $order['_invoice_total'] =
        $invoiceTotal;

    $order['_confirmed_paid'] =
        $confirmedPaid;

    $order['_reconciliation_type'] =
        $reconciliationType;

    $order['_reconciliation_amount'] =
        $reconciliationAmount;

    $order['_last_payment_status'] =
        $lastPaymentStatus;

    $order['_has_pending'] =
        $hasPendingPayment;

    $order['_has_confirmed'] =
        $hasConfirmedPayment;

    $order['_is_debtor'] =
        $isDebtor;

    $order['_is_creditor'] =
        $isCreditor;

    $order['_is_settled'] =
        $isSettled;

    $order['_payable_amount'] =
        $payableAmount;

    $order['_can_pay'] =
        $canPay;

    $order['_display_payment_status'] =
        $displayPaymentStatus;
}

unset($order);

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'سفارش‌های من | رابطی';

ob_start();
?>

<style>
    .account-orders-page {
        width: min(1180px, calc(100% - 32px));
        margin: 30px auto 70px;
    }

    .account-orders-head {
        margin-bottom: 24px;
    }

    .account-orders-head h1 {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 800;
    }

    .account-orders-head p {
        margin: 0;
        color: #777;
        line-height: 1.9;
    }

    .orders-list {
        display: grid;
        gap: 20px;
    }

    .order-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0, 0, 0, .04);
    }

    .order-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        background: #fafafa;
        border-bottom: 1px solid #eee;
    }

    .order-number {
        font-weight: 800;
        font-size: 17px;
    }

    .order-date {
        margin-top: 6px;
        color: #777;
        font-size: 13px;
    }

    .order-status-badge,
    .invoice-payment-badge,
    .reconciliation-badge,
    .request-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .order-status-badge {
        background: #f2f2f2;
        color: #555;
    }

    .order-status-badge.is-pending {
        background: #fff5d9;
        color: #9a6b00;
    }

    .order-status-badge.is-processing {
        background: #e9f4ff;
        color: #1769aa;
    }

    .order-status-badge.is-ready {
        background: #e9f8ef;
        color: #18794e;
    }

    .order-status-badge.is-shipped,
    .order-status-badge.is-delivered,
    .order-status-badge.is-completed {
        background: #e9f8ef;
        color: #18794e;
    }

    .order-status-badge.is-cancelled {
        background: #fff0f0;
        color: #b42318;
    }

    .order-card-body {
        padding: 22px;
    }

    .order-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }

    .order-summary-item {
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 14px;
        background: #fff;
    }

    .order-summary-label {
        display: block;
        color: #777;
        font-size: 12px;
        margin-bottom: 7px;
    }

    .order-summary-value {
        display: block;
        font-weight: 800;
        font-size: 15px;
    }

    .financial-box {
        border: 1px solid #e9e9e9;
        border-radius: 16px;
        padding: 18px;
        background: #fcfcfc;
    }

    .financial-title {
        margin: 0 0 15px;
        font-size: 16px;
        font-weight: 800;
    }

    .financial-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .financial-item {
        padding: 13px;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 12px;
    }

    .financial-label {
        display: block;
        color: #777;
        font-size: 12px;
        margin-bottom: 6px;
    }

    .financial-value {
        display: block;
        font-weight: 800;
        line-height: 1.7;
    }

    .financial-value.debtor {
        color: #b42318;
    }

    .financial-value.creditor {
        color: #1769aa;
    }

    .financial-value.settled {
        color: #18794e;
    }

    .invoice-payment-badge.is-unpaid {
        background: #fff5d9;
        color: #9a6b00;
    }

    .invoice-payment-badge.is-partial {
        background: #fff5d9;
        color: #9a6b00;
    }

    .invoice-payment-badge.is-paid {
        background: #e9f8ef;
        color: #18794e;
    }

    .invoice-payment-badge.is-void {
        background: #f1f1f1;
        color: #666;
    }

    .reconciliation-badge.is-settled {
        background: #e9f8ef;
        color: #18794e;
    }

    .reconciliation-badge.is-debtor {
        background: #fff0f0;
        color: #b42318;
    }

    .reconciliation-badge.is-creditor {
        background: #e9f4ff;
        color: #1769aa;
    }

    .request-badge.is-pending {
        background: #fff5d9;
        color: #9a6b00;
    }

    .request-badge.is-confirmed {
        background: #e9f8ef;
        color: #18794e;
    }

    .request-badge.is-rejected {
        background: #fff0f0;
        color: #b42318;
    }

    .payment-message {
        margin-top: 15px;
        padding: 14px 16px;
        border-radius: 13px;
        line-height: 1.9;
        font-size: 13px;
    }

    .payment-message.pending {
        background: #fff8e6;
        color: #775900;
        border: 1px solid #f0dfad;
    }

    .payment-message.confirmed {
        background: #eefaf3;
        color: #19633e;
        border: 1px solid #ccebd8;
    }

    .payment-message.debtor {
        background: #fff5f5;
        color: #8f2118;
        border: 1px solid #f1cccc;
    }

    .payment-message.creditor {
        background: #f0f7ff;
        color: #185b91;
        border: 1px solid #cfe3f6;
    }

    .payment-message.settled {
        background: #eefaf3;
        color: #19633e;
        border: 1px solid #ccebd8;
    }

    .order-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .order-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 18px;
        border-radius: 11px;
        border: 1px solid #ddd;
        background: #fff;
        color: #333;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        transition: .2s ease;
    }

    .order-action:hover {
        border-color: #bbb;
        transform: translateY(-1px);
    }

    .order-action.primary {
        border-color: var(--brand, #111);
        background: var(--brand, #111);
        color: #fff;
    }

    .order-action.primary:hover {
        opacity: .92;
    }

    .order-action.disabled {
        opacity: .6;
        cursor: not-allowed;
        pointer-events: none;
    }

    .empty-orders {
        padding: 60px 20px;
        text-align: center;
        border: 1px dashed #ddd;
        border-radius: 18px;
        background: #fff;
    }

    .empty-orders strong {
        display: block;
        margin-bottom: 8px;
        font-size: 18px;
    }

    .empty-orders span {
        color: #777;
        font-size: 14px;
    }

    @media (max-width: 900px) {

        .order-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .financial-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {

        .account-orders-page {
            width: min(100% - 20px, 680px);
            margin: 20px auto 50px;
        }

        .account-orders-head h1 {
            font-size: 23px;
        }

        .order-card-head {
            align-items: flex-start;
            flex-direction: column;
            padding: 16px;
        }

        .order-card-body {
            padding: 16px;
        }

        .order-summary-grid,
        .financial-grid {
            grid-template-columns: 1fr;
        }

        .order-actions {
            flex-direction: column;
        }

        .order-action {
            width: 100%;
        }
    }
</style>

<div class="account-orders-page">

    <div class="account-orders-head">

        <h1>
            سفارش‌های من
        </h1>

        <p>
            وضعیت سفارش‌ها، فاکتورها و وضعیت مالی پرداخت‌های شما
        </p>

    </div>

    <?php if (!$orders): ?>

        <div class="empty-orders">

            <strong>
                هنوز سفارشی ثبت نکرده‌اید
            </strong>

            <span>
                سفارش‌های ثبت‌شده شما در این قسمت نمایش داده می‌شوند.
            </span>

            <div class="order-actions" style="justify-content:center;margin-top:20px;">

                <a
                    href="/products.php"
                    class="order-action primary">
                    مشاهده محصولات
                </a>

            </div>

        </div>

    <?php else: ?>

        <div class="orders-list">

            <?php foreach ($orders as $order): ?>

                <?php

                $orderNumber =
                    (string)$order['order_number'];

                $invoiceNumber =
                    (string)($order['invoice_number'] ?? '');

                $orderStatus =
                    (string)($order['status'] ?? '');

                $invoiceTotal =
                    (int)$order['_invoice_total'];

                $confirmedPaid =
                    (int)$order['_confirmed_paid'];

                $reconciliationType =
                    (string)$order['_reconciliation_type'];

                $reconciliationAmount =
                    (int)$order['_reconciliation_amount'];

                $hasPending =
                    (bool)$order['_has_pending'];

                $hasConfirmed =
                    (bool)$order['_has_confirmed'];

                $isDebtor =
                    (bool)$order['_is_debtor'];

                $isCreditor =
                    (bool)$order['_is_creditor'];

                $isSettled =
                    (bool)$order['_is_settled'];

                $payableAmount =
                    (int)$order['_payable_amount'];

                $canPay =
                    (bool)$order['_can_pay'];

                $invoiceStatus =
                    (string)($order['invoice_status'] ?? 'unpaid');

                ?>

                <article class="order-card">

                    <!-- سربرگ سفارش -->

                    <div class="order-card-head">

                        <div>

                            <div class="order-number">

                                سفارش

                                <span dir="ltr">
                                    <?= $h($orderNumber) ?>
                                </span>

                            </div>

                            <?php if (!empty($order['created_at'])): ?>

                                <div class="order-date">

                                    <?= $h(
                                        date(
                                            'Y/m/d H:i',
                                            strtotime(
                                                (string)$order['created_at']
                                            )
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <span
                            class="order-status-badge <?= $h(orderStatusClass($orderStatus)) ?>">

                            <?= $h(
                                orderStatusLabel($orderStatus)
                            ) ?>

                        </span>

                    </div>

                    <div class="order-card-body">

                        <!-- خلاصه -->

                        <div class="order-summary-grid">

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    فاکتور
                                </span>

                                <?php if ($invoiceNumber !== ''): ?>

                                    <strong
                                        class="order-summary-value"
                                        dir="ltr">

                                        <?= $h($invoiceNumber) ?>

                                    </strong>

                                <?php else: ?>

                                    <strong class="order-summary-value">
                                        هنوز صادر نشده
                                    </strong>

                                <?php endif; ?>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    مبلغ فاکتور
                                </span>

                                <strong class="order-summary-value">
                                    <?= toman($invoiceTotal) ?>
                                </strong>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    پرداخت تأییدشده
                                </span>

                                <strong class="order-summary-value">

                                    <?= toman($confirmedPaid) ?>

                                </strong>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    وضعیت پرداخت
                                </span>

                                <strong class="order-summary-value">

                                    <span
                                        class="invoice-payment-badge <?= $h(paymentStatusClass($invoiceStatus)) ?>">

                                        <?= $h(
                                            paymentStatusLabel($invoiceStatus)
                                        ) ?>

                                    </span>

                                </strong>

                            </div>

                        </div>

                        <!-- وضعیت مالی -->

                        <div class="financial-box">

                            <h3 class="financial-title">
                                وضعیت مالی فاکتور
                            </h3>

                            <div class="financial-grid">

                                <div class="financial-item">

                                    <span class="financial-label">
                                        نتیجه مالی
                                    </span>

                                    <span
                                        class="reconciliation-badge <?= $h(reconciliationClass($reconciliationType)) ?>">

                                        <?= $h(
                                            reconciliationLabel(
                                                $reconciliationType
                                            )
                                        ) ?>

                                    </span>

                                </div>

                                <div class="financial-item">

                                    <span class="financial-label">

                                        <?php if ($isDebtor): ?>

                                            مانده بدهی

                                        <?php elseif ($isCreditor): ?>

                                            بستانکاری شما

                                        <?php else: ?>

                                            مانده مالی

                                        <?php endif; ?>

                                    </span>

                                    <strong
                                        class="financial-value <?= $isDebtor
                                                                    ? 'debtor'
                                                                    : ($isCreditor
                                                                        ? 'creditor'
                                                                        : 'settled') ?>">

                                        <?php if ($reconciliationAmount > 0): ?>

                                            <?= toman($reconciliationAmount) ?>

                                        <?php else: ?>

                                            ۰ تومان

                                        <?php endif; ?>

                                    </strong>

                                </div>

                                <div class="financial-item">

                                    <span class="financial-label">
                                        وضعیت درخواست پرداخت
                                    </span>

                                    <?php if ($hasPending): ?>

                                        <span class="request-badge is-pending">
                                            در انتظار بررسی
                                        </span>

                                    <?php elseif ($hasConfirmed): ?>

                                        <span class="request-badge is-confirmed">
                                            تأیید شده
                                        </span>

                                    <?php else: ?>

                                        <span class="request-badge">
                                            ثبت نشده
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <!-- پیام مالی -->

                            <?php if ($hasPending): ?>

                                <div class="payment-message pending">

                                    <strong>
                                        درخواست پرداخت شما در انتظار بررسی است.
                                    </strong>

                                    <br>

                                    تا زمانی که امور مالی این پرداخت را بررسی
                                    نکرده است، امکان ثبت پرداخت جدید برای این
                                    فاکتور وجود ندارد.

                                </div>

                            <?php elseif ($isCreditor): ?>

                                <div class="payment-message creditor">

                                    <strong>
                                        بستانکار هستید.
                                    </strong>

                                    <br>

                                    طبق ثبت مالی این فاکتور،
                                    <?= toman($reconciliationAmount) ?>
                                    به عنوان بستانکاری شما ثبت شده است.
                                    بنابراین در حال حاضر نیازی به پرداخت
                                    دیگری ندارید.

                                </div>

                            <?php elseif ($isSettled): ?>

                                <div class="payment-message settled">

                                    <strong>
                                        این فاکتور تسویه شده است.
                                    </strong>

                                    <br>

                                    مانده قابل پرداخت وجود ندارد.

                                </div>

                            <?php elseif ($isDebtor): ?>

                                <div class="payment-message debtor">

                                    <strong>
                                        مانده بدهی شما
                                        <?= toman($reconciliationAmount) ?>
                                        است.
                                    </strong>

                                    <br>

                                    فقط همین مبلغ به عنوان مانده قابل پرداخت
                                    در نظر گرفته شده است.

                                </div>

                            <?php elseif (!$hasConfirmed): ?>

                                <div class="payment-message pending">

                                    هنوز پرداخت تأییدشده‌ای برای این فاکتور
                                    ثبت نشده است.

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- عملیات -->

                        <div class="order-actions">

                            <?php if ($invoiceNumber !== ''): ?>

                                <a
                                    href="/invoice-print.php?inv=<?= rawurlencode($invoiceNumber) ?>"
                                    target="_blank"
                                    class="order-action">

                                    🧾 مشاهده / چاپ فاکتور

                                </a>

                                <a
                                    href="/invoice-pdf.php?inv=<?= rawurlencode($invoiceNumber) ?>"
                                    class="order-action">

                                    📄 دریافت PDF

                                </a>

                            <?php endif; ?>


                            <?php if ($canPay): ?>

                                <a
                                    href="/payment.php?order=<?= rawurlencode($orderNumber) ?>"
                                    class="order-action primary">

                                    💳 پرداخت مانده بدهی
                                    —
                                    <?= toman($payableAmount) ?>

                                </a>

                            <?php elseif ($hasPending): ?>

                                <span
                                    class="order-action disabled">

                                    ⏳ پرداخت در انتظار بررسی

                                </span>

                            <?php elseif ($isCreditor): ?>

                                <span
                                    class="order-action disabled">

                                    ✓ نیازی به پرداخت نیست

                                </span>

                            <?php elseif ($isSettled): ?>

                                <span
                                    class="order-action disabled">

                                    ✓ تسویه شده

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
