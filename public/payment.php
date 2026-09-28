<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) =>
htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$userId = (int)(
    $_SESSION['admin_id']
    ?? $_SESSION['customer_id']
    ?? 0
);

$db = db();

$orderNumber = trim($_GET['order'] ?? '');

if ($orderNumber === '' || $userId <= 0) {
    header('Location: /');
    exit;
}

/*
|--------------------------------------------------------------------------
| توابع کمکی
|--------------------------------------------------------------------------
*/

function moneyFa(int $amount): string
{
    return number_format(max(0, $amount)) . ' تومان';
}

function paymentMethodLabel(string $method): string
{
    return match ($method) {
        'sheba'        => 'انتقال با شبا',
        'card_to_card' => 'کارت به کارت',
        'cash_receipt' => 'فیش نقدی',
        'online'       => 'درگاه پرداخت آنلاین',
        default        => 'نامشخص',
    };
}

function uploadReceipt(array $file): array
{
    if (
        !isset($file['error'])
        || $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'ok' => true,
            'path' => null,
        ];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'error' => 'بارگذاری فایل ناموفق بود.',
        ];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [
            'ok' => false,
            'error' => 'حجم فیش نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }

    $tmp = $file['tmp_name'] ?? '';

    if (
        $tmp === ''
        || !is_uploaded_file($tmp)
    ) {
        return [
            'ok' => false,
            'error' => 'فایل ارسالی معتبر نیست.',
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        return [
            'ok' => false,
            'error' => 'فرمت فیش باید JPG، PNG یا WEBP باشد.',
        ];
    }

    $uploadDir = ROOT_PATH . '/public/uploads/receipts';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return [
                'ok' => false,
                'error' => 'پوشه ذخیره فیش قابل ایجاد نیست.',
            ];
        }
    }

    $filename =
        'receipt_'
        . date('Ymd_His')
        . '_'
        . bin2hex(random_bytes(6))
        . '.'
        . $allowed[$mime];

    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        return [
            'ok' => false,
            'error' => 'ذخیره فیش ناموفق بود.',
        ];
    }

    return [
        'ok' => true,
        'path' => '/uploads/receipts/' . $filename,
    ];
}

/*
|--------------------------------------------------------------------------
| دریافت سفارش + فاکتور
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        o.*,

        i.id AS invoice_id,
        i.invoice_number,
        i.total AS invoice_total,
        i.paid_total,
        i.status AS invoice_status,
        i.reconciliation_type,
        i.reconciliation_amount

    FROM orders o

    LEFT JOIN invoices i
        ON i.order_id = o.id

    WHERE o.order_number = ?
      AND o.user_id = ?

    LIMIT 1
");

$stmt->execute([
    $orderNumber,
    $userId,
]);

$order = $stmt->fetch();

if (!$order) {
    header('Location: /');
    exit;
}

/*
|--------------------------------------------------------------------------
| ساخت فاکتور در صورت نبودن
|--------------------------------------------------------------------------
*/

if (empty($order['invoice_id'])) {

    $invoiceNumber =
        'INV-'
        . date('ymd')
        . '-'
        . str_pad(
            (string)random_int(0, 9999),
            4,
            '0',
            STR_PAD_LEFT
        );

    $invoiceStmt = $db->prepare("
        INSERT INTO invoices
            (
                invoice_number,
                order_id,
                user_id,
                total
            )
        VALUES
            (?, ?, ?, ?)
    ");

    $invoiceStmt->execute([
        $invoiceNumber,
        (int)$order['id'],
        $userId,
        (int)$order['total'],
    ]);

    $order['invoice_id'] = (int)$db->lastInsertId();
    $order['invoice_number'] = $invoiceNumber;
    $order['invoice_total'] = (int)$order['total'];
    $order['paid_total'] = 0;
    $order['invoice_status'] = 'unpaid';
    $order['reconciliation_type'] = 'settled';
    $order['reconciliation_amount'] = 0;
}

/*
|--------------------------------------------------------------------------
| اطلاعات مالی فاکتور
|--------------------------------------------------------------------------
*/

$invoiceId = (int)$order['invoice_id'];

$invoiceTotal = (int)(
    $order['invoice_total']
    ?? $order['total']
    ?? 0
);

$paidTotal = (int)(
    $order['paid_total']
    ?? 0
);

$reconciliationType = (string)(
    $order['reconciliation_type']
    ?? 'settled'
);

$reconciliationAmount = (int)(
    $order['reconciliation_amount']
    ?? 0
);

/*
|--------------------------------------------------------------------------
| پرداخت‌های این فاکتور
|--------------------------------------------------------------------------
*/

$paymentsStmt = $db->prepare("
    SELECT
        id,
        method,
        amount,
        status,
        reference_no,
        receipt_path,
        created_at
    FROM payments
    WHERE invoice_id = ?
      AND user_id = ?
    ORDER BY id DESC
");

$paymentsStmt->execute([
    $invoiceId,
    $userId,
]);

$payments = $paymentsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| محاسبه وضعیت
|--------------------------------------------------------------------------
*/

$pendingAmount = 0;

foreach ($payments as $payment) {

    if ((string)$payment['status'] === 'pending') {
        $pendingAmount += (int)$payment['amount'];
    }
}

/*
|--------------------------------------------------------------------------
| مانده واقعی فاکتور
|--------------------------------------------------------------------------
*/

$invoiceRemaining = max(
    0,
    $invoiceTotal - $paidTotal
);

/*
|--------------------------------------------------------------------------
| مبلغ مجاز برای پرداخت
|
| اگر حسابداری بدهکار اعلام کرده باشد،
| reconciliation_amount مرجع مبلغ بدهی است.
|--------------------------------------------------------------------------
*/

if ($reconciliationType === 'debtor') {

    $payableAmount = $reconciliationAmount;

    /*
     * برای جلوگیری از پرداخت بیش از مانده واقعی فاکتور
     */
    if ($invoiceRemaining > 0) {
        $payableAmount = min(
            $payableAmount,
            $invoiceRemaining
        );
    }
} else {

    $payableAmount = $invoiceRemaining;
}

/*
|--------------------------------------------------------------------------
| اگر pending وجود دارد، مشتری نباید دوباره پرداخت کند.
|--------------------------------------------------------------------------
*/

$hasPending = $pendingAmount > 0;

/*
|--------------------------------------------------------------------------
| بستانکار یعنی مشتری نباید پرداخت کند.
|--------------------------------------------------------------------------
*/

$isCreditor =
    $reconciliationType === 'creditor'
    && $reconciliationAmount > 0;

/*
|--------------------------------------------------------------------------
| تسویه کامل
|--------------------------------------------------------------------------
*/

$isFullyPaid =
    $invoiceRemaining <= 0
    || $payableAmount <= 0;

/*
|--------------------------------------------------------------------------
| ثبت پرداخت
|--------------------------------------------------------------------------
*/

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $method = trim(
        (string)($_POST['method'] ?? '')
    );

    $referenceNo = trim(
        (string)($_POST['reference_no'] ?? '')
    );

    /*
     * مبلغ فقط برای پرداخت دستی دریافت می‌شود.
     * برای online هرگز مبلغ POST را قبول نمی‌کنیم.
     */
    $requestedAmount = (int)(
        $_POST['amount'] ?? 0
    );

    $validMethods = [
        'sheba',
        'card_to_card',
        'cash_receipt',
        'online',
    ];

    /*
     * بررسی روش
     */
    if (!in_array($method, $validMethods, true)) {

        $error = 'روش پرداخت نامعتبر است.';

        /*
     * بستانکار
     */
    } elseif ($isCreditor) {

        $error =
            'طبق نتیجه مالی ثبت‌شده، شما بستانکار هستید و مبلغی برای پرداخت ندارید.';

        /*
     * پرداخت کامل
     */
    } elseif ($isFullyPaid) {

        $error =
            'این فاکتور در حال حاضر مبلغ قابل پرداختی ندارد.';

        /*
     * پرداخت pending
     */
    } elseif ($hasPending) {

        $error =
            'یک پرداخت در انتظار بررسی برای این فاکتور وجود دارد. تا تعیین تکلیف آن، پرداخت جدید امکان‌پذیر نیست.';

        /*
     * IPG
     *
     * در نسخه فعلی API درگاه متصل نیست.
     * اما مسیر داده و method از همین حالا آماده است.
     */
    } elseif ($method === 'online') {

        /*
         * بسیار مهم:
         * مبلغ از فرم گرفته نمی‌شود.
         *
         * وقتی IPG اضافه شود:
         *
         * $payableAmount
         *
         * مستقیماً به سرویس درگاه ارسال خواهد شد.
         */
        $error =
            'درگاه پرداخت آنلاین هنوز فعال نشده است.';
    } else {

        /*
         * پرداخت دستی
         */

        if ($requestedAmount <= 0) {

            $error =
                'مبلغ پرداخت را وارد کنید.';
        } elseif ($requestedAmount > $payableAmount) {

            $error =
                'مبلغ پرداخت نمی‌تواند بیشتر از '
                . moneyFa($payableAmount)
                . ' باشد.';
        } elseif (
            $referenceNo === ''
            && empty($_FILES['receipt']['name'])
        ) {

            $error =
                'شماره پیگیری یا تصویر فیش واریزی را وارد کنید.';
        } else {

            /*
             * آپلود فیش
             */
            $upload = uploadReceipt(
                $_FILES['receipt'] ?? []
            );

            if (!$upload['ok']) {

                $error = $upload['error'];
            } else {

                $receiptPath =
                    $upload['path'];

                try {

                    /*
                     * تراکنش
                     */
                    $db->beginTransaction();

                    /*
                     * فاکتور را قفل می‌کنیم تا دو درخواست همزمان
                     * نتوانند پرداخت تکراری ثبت کنند.
                     */
                    $lockInvoice = $db->prepare("
                        SELECT
                            id,
                            total,
                            paid_total,
                            reconciliation_type,
                            reconciliation_amount
                        FROM invoices
                        WHERE id = ?
                          AND user_id = ?
                        FOR UPDATE
                    ");

                    $lockInvoice->execute([
                        $invoiceId,
                        $userId,
                    ]);

                    $lockedInvoice =
                        $lockInvoice->fetch();

                    if (!$lockedInvoice) {
                        throw new RuntimeException(
                            'invoice_not_found'
                        );
                    }

                    /*
                     * بررسی pending بعد از قفل
                     */
                    $pendingStmt = $db->prepare("
                        SELECT
                            id,
                            amount
                        FROM payments
                        WHERE invoice_id = ?
                          AND user_id = ?
                          AND status = 'pending'
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $pendingStmt->execute([
                        $invoiceId,
                        $userId,
                    ]);

                    $pendingPayment =
                        $pendingStmt->fetch();

                    if ($pendingPayment) {

                        $db->rollBack();

                        $error =
                            'یک پرداخت در انتظار بررسی برای این فاکتور وجود دارد.';
                    } else {

                        /*
                         * محاسبه مجدد سمت سرور
                         */
                        $lockedTotal =
                            (int)$lockedInvoice['total'];

                        $lockedPaid =
                            (int)$lockedInvoice['paid_total'];

                        $lockedRemaining =
                            max(
                                0,
                                $lockedTotal - $lockedPaid
                            );

                        $lockedReconType =
                            (string)$lockedInvoice['reconciliation_type'];

                        $lockedReconAmount =
                            (int)$lockedInvoice['reconciliation_amount'];

                        if (
                            $lockedReconType === 'creditor'
                            && $lockedReconAmount > 0
                        ) {

                            $db->rollBack();

                            $error =
                                'طبق نتیجه مالی ثبت‌شده، شما بستانکار هستید و مبلغی برای پرداخت ندارید.';
                        } else {

                            if (
                                $lockedReconType === 'debtor'
                            ) {

                                $lockedPayable =
                                    $lockedReconAmount;

                                if ($lockedRemaining > 0) {
                                    $lockedPayable = min(
                                        $lockedPayable,
                                        $lockedRemaining
                                    );
                                }
                            } else {

                                $lockedPayable =
                                    $lockedRemaining;
                            }

                            if (
                                $requestedAmount <= 0
                                || $requestedAmount > $lockedPayable
                            ) {

                                $db->rollBack();

                                $error =
                                    'مبلغ پرداخت معتبر نیست. مبلغ مجاز: '
                                    . moneyFa($lockedPayable);
                            } else {

                                /*
                                 * ثبت پرداخت
                                 *
                                 * مهم:
                                 * paid_total اینجا تغییر نمی‌کند.
                                 */
                                $insert = $db->prepare("
                                    INSERT INTO payments
                                        (
                                            invoice_id,
                                            user_id,
                                            method,
                                            amount,
                                            status,
                                            reference_no,
                                            receipt_path
                                        )
                                    VALUES
                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            'pending',
                                            ?,
                                            ?
                                        )
                                ");

                                $insert->execute([
                                    $invoiceId,
                                    $userId,
                                    $method,
                                    $requestedAmount,
                                    $referenceNo !== ''
                                        ? $referenceNo
                                        : null,
                                    $receiptPath,
                                ]);

                                $db->commit();

                                $success = true;
                            }
                        }
                    }
                } catch (Throwable $e) {

                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    $success = false;

                    $error =
                        'ثبت اطلاعات پرداخت انجام نشد. دوباره تلاش کنید.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

$settings = [];

$settingsStmt = $db->query("
    SELECT
        `key`,
        `value`
    FROM settings
    WHERE `key` LIKE 'payment_%'
");

foreach ($settingsStmt as $setting) {

    $settings[(string)$setting['key']] = $setting['value'];
}

/*
|--------------------------------------------------------------------------
| اگر ثبت موفق بود
|--------------------------------------------------------------------------
*/

if ($success) {

    $successMessage =
        'درخواست پرداخت شما با موفقیت ثبت شد و پس از بررسی مالی تعیین تکلیف می‌شود.';
}

/*
|--------------------------------------------------------------------------
| نمایش مجدد وضعیت بعد از POST
|--------------------------------------------------------------------------
*/

if ($success) {

    $hasPending = true;
}

/*
|--------------------------------------------------------------------------
| عنوان صفحه
|--------------------------------------------------------------------------
*/

$pageTitle = 'پرداخت سفارش | رابطی';

ob_start();

?>

<nav class="breadcrumb">
    <a href="/">خانه</a>
    <span>›</span>
    <a href="/account-orders.php">سفارش‌های من</a>
    <span>›</span>
    <span>پرداخت</span>
</nav>


<section class="payment-page">

    <header class="payment-head">

        <div>
            <span class="payment-eyebrow">
                پرداخت سفارش
            </span>

            <h1>
                تکمیل پرداخت
            </h1>

            <p>
                سفارش
                <b dir="ltr">
                    <?= $h($order['order_number']) ?>
                </b>
            </p>
        </div>

        <?php if (!empty($order['invoice_number'])): ?>

            <div class="payment-invoice-number">

                <span>
                    شماره فاکتور
                </span>

                <b dir="ltr">
                    <?= $h($order['invoice_number']) ?>
                </b>

            </div>

        <?php endif; ?>

    </header>


    <?php if ($success): ?>

        <div class="payment-result payment-result-success">

            <div class="payment-result-icon">
                ✓
            </div>

            <div>

                <h2>
                    اطلاعات پرداخت ثبت شد
                </h2>

                <p>
                    <?= $h($successMessage) ?>
                </p>

            </div>

        </div>


        <div class="payment-actions">

            <a
                href="/invoice-print.php?inv=<?= rawurlencode((string)$order['invoice_number']) ?>"
                target="_blank"
                class="btn btn-outline">
                🧾 مشاهده / چاپ فاکتور
            </a>

            <a
                href="/account-orders.php"
                class="btn btn-primary">
                بازگشت به سفارش‌ها
            </a>

        </div>


    <?php else: ?>


        <?php if ($error): ?>

            <div class="payment-alert payment-alert-error">
                ⚠️
                <?= $h($error) ?>
            </div>

        <?php endif; ?>


        <!-- خلاصه مالی -->

        <div class="payment-summary">

            <div class="payment-summary-row">

                <span>
                    مبلغ فاکتور
                </span>

                <strong>
                    <?= moneyFa($invoiceTotal) ?>
                </strong>

            </div>


            <div class="payment-summary-row">

                <span>
                    پرداخت تأییدشده
                </span>

                <strong class="payment-paid">
                    <?= moneyFa($paidTotal) ?>
                </strong>

            </div>


            <?php if ($pendingAmount > 0): ?>

                <div class="payment-summary-row">

                    <span>
                        پرداخت در انتظار بررسی
                    </span>

                    <strong class="payment-pending">
                        <?= moneyFa($pendingAmount) ?>
                    </strong>

                </div>

            <?php endif; ?>


            <div class="payment-summary-row payment-summary-total">

                <span>
                    مبلغ قابل پرداخت
                </span>

                <strong>
                    <?= moneyFa($payableAmount) ?>
                </strong>

            </div>

        </div>


        <?php if ($isCreditor): ?>

            <div class="payment-result payment-result-info">

                <div class="payment-result-icon">
                    ℹ️
                </div>

                <div>

                    <h2>
                        شما بستانکار هستید
                    </h2>

                    <p>
                        طبق ثبت مالی، مبلغ
                        <b>
                            <?= moneyFa($reconciliationAmount) ?>
                        </b>
                        به نفع شما ثبت شده است و در حال حاضر نیازی به پرداخت ندارید.
                    </p>

                </div>

            </div>


        <?php elseif ($hasPending): ?>

            <div class="payment-result payment-result-warning">

                <div class="payment-result-icon">
                    ⏳
                </div>

                <div>

                    <h2>
                        پرداخت شما در انتظار بررسی است
                    </h2>

                    <p>
                        تا زمانی که این پرداخت تعیین تکلیف نشود،
                        امکان ثبت پرداخت دیگری برای این فاکتور وجود ندارد.
                    </p>

                </div>

            </div>


        <?php elseif ($isFullyPaid): ?>

            <div class="payment-result payment-result-success">

                <div class="payment-result-icon">
                    ✓
                </div>

                <div>

                    <h2>
                        این فاکتور تسویه شده است
                    </h2>

                    <p>
                        مبلغ دیگری برای پرداخت وجود ندارد.
                    </p>

                </div>

            </div>


        <?php else: ?>


            <form
                method="post"
                class="payment-form"
                enctype="multipart/form-data"
                id="paymentForm">


                <section class="payment-section">

                    <div class="payment-section-head">

                        <span class="payment-step">
                            ۱
                        </span>

                        <div>

                            <h2>
                                انتخاب روش پرداخت
                            </h2>

                            <p>
                                روش پرداخت خود را انتخاب کنید.
                            </p>

                        </div>

                    </div>


                    <div class="payment-method-grid">


                        <!-- شبا -->

                        <label class="payment-method-card">

                            <input
                                type="radio"
                                name="method"
                                value="sheba"
                                required>

                            <span class="payment-method-icon">
                                🏦
                            </span>

                            <span class="payment-method-content">

                                <strong>
                                    انتقال با شبا
                                </strong>

                                <small>
                                    واریز بانکی به شماره شبا
                                </small>

                            </span>

                            <span class="payment-method-check">
                                ✓
                            </span>

                        </label>


                        <!-- کارت به کارت -->

                        <label class="payment-method-card">

                            <input
                                type="radio"
                                name="method"
                                value="card_to_card">

                            <span class="payment-method-icon">
                                💳
                            </span>

                            <span class="payment-method-content">

                                <strong>
                                    کارت به کارت
                                </strong>

                                <small>
                                    انتقال مستقیم کارت به کارت
                                </small>

                            </span>

                            <span class="payment-method-check">
                                ✓
                            </span>

                        </label>


                        <!-- فیش نقدی -->

                        <label class="payment-method-card">

                            <input
                                type="radio"
                                name="method"
                                value="cash_receipt">

                            <span class="payment-method-icon">
                                🧾
                            </span>

                            <span class="payment-method-content">

                                <strong>
                                    فیش نقدی
                                </strong>

                                <small>
                                    واریز نقدی و ارسال فیش
                                </small>

                            </span>

                            <span class="payment-method-check">
                                ✓
                            </span>

                        </label>


                        <!-- IPG -->

                        <label class="payment-method-card payment-method-card-online">

                            <input
                                type="radio"
                                name="method"
                                value="online"
                                disabled>

                            <span class="payment-method-icon">
                                🔒
                            </span>

                            <span class="payment-method-content">

                                <strong>
                                    درگاه پرداخت آنلاین
                                </strong>

                                <small>
                                    به‌زودی فعال می‌شود
                                </small>

                            </span>

                        </label>


                    </div>

                </section>


                <!-- اطلاعات حساب -->

                <section
                    class="payment-section payment-bank-info"
                    id="bankInfo"
                    hidden>

                    <div class="payment-section-head">

                        <span class="payment-step">
                            ۲
                        </span>

                        <div>

                            <h2>
                                اطلاعات واریز
                            </h2>

                            <p>
                                مبلغ دقیق قابل پرداخت:
                                <b>
                                    <?= moneyFa($payableAmount) ?>
                                </b>
                            </p>

                        </div>

                    </div>


                    <div
                        class="bank-detail"
                        id="shebaBox"
                        hidden>

                        <span>
                            شماره شبا
                        </span>

                        <div class="bank-value-row">

                            <code dir="ltr">
                                <?= $h($settings['payment_sheba'] ?? '-') ?>
                            </code>

                            <button
                                type="button"
                                class="btn-copy"
                                data-copy="<?= $h($settings['payment_sheba'] ?? '') ?>">
                                کپی
                            </button>

                        </div>

                        <small>
                            به نام
                            <?= $h($settings['payment_card_name'] ?? '') ?>
                        </small>

                    </div>


                    <div
                        class="bank-detail"
                        id="cardBox"
                        hidden>

                        <span>
                            شماره کارت
                        </span>

                        <div class="bank-value-row">

                            <code dir="ltr">
                                <?= $h($settings['payment_card'] ?? '-') ?>
                            </code>

                            <button
                                type="button"
                                class="btn-copy"
                                data-copy="<?= $h($settings['payment_card'] ?? '') ?>">
                                کپی
                            </button>

                        </div>

                        <small>
                            به نام
                            <?= $h($settings['payment_card_name'] ?? '') ?>
                        </small>

                    </div>

                </section>


                <!-- مبلغ -->

                <section
                    class="payment-section"
                    id="amountSection"
                    hidden>

                    <div class="payment-section-head">

                        <span class="payment-step">
                            ۳
                        </span>

                        <div>

                            <h2>
                                مبلغ واریزی
                            </h2>

                            <p>
                                مبلغی که واقعاً واریز کرده‌اید را وارد کنید.
                            </p>

                        </div>

                    </div>


                    <div class="payment-amount-box">

                        <label for="paymentAmount">
                            مبلغ پرداخت
                        </label>

                        <div class="payment-amount-input">

                            <input
                                type="number"
                                id="paymentAmount"
                                name="amount"
                                min="1"
                                max="<?= (int)$payableAmount ?>"
                                step="1"
                                inputmode="numeric"
                                placeholder="<?= (int)$payableAmount ?>">

                            <span>
                                تومان
                            </span>

                        </div>

                        <small>
                            حداکثر قابل پرداخت:
                            <b>
                                <?= moneyFa($payableAmount) ?>
                            </b>
                        </small>

                    </div>

                </section>


                <!-- اثبات -->

                <section
                    class="payment-section"
                    id="proofSection"
                    hidden>

                    <div class="payment-section-head">

                        <span class="payment-step">
                            ۴
                        </span>

                        <div>

                            <h2>
                                اثبات واریز
                            </h2>

                            <p>
                                شماره پیگیری یا تصویر فیش را ثبت کنید.
                            </p>

                        </div>

                    </div>


                    <div class="payment-proof-grid">

                        <div class="payment-field">

                            <label for="referenceNo">
                                شماره پیگیری
                            </label>

                            <input
                                type="text"
                                id="referenceNo"
                                name="reference_no"
                                dir="ltr"
                                autocomplete="off"
                                placeholder="مثلاً 840512">

                        </div>


                        <div class="payment-field">

                            <label for="receipt">
                                تصویر فیش
                            </label>

                            <input
                                type="file"
                                id="receipt"
                                name="receipt"
                                accept="image/jpeg,image/png,image/webp">

                            <small>
                                JPG / PNG / WEBP — حداکثر ۲MB
                            </small>

                        </div>

                    </div>


                    <div class="payment-proof-note">
                        حداقل یکی از «شماره پیگیری» یا «تصویر فیش» الزامی است.
                    </div>

                </section>


                <div class="payment-submit-row">

                    <button
                        type="submit"
                        class="btn btn-primary payment-submit"
                        id="paymentSubmit"
                        disabled>

                        ثبت درخواست پرداخت

                    </button>

                </div>


            </form>


        <?php endif; ?>


    <?php endif; ?>

</section>


<script>
    (function() {

        const methods = document.querySelectorAll(
            'input[name="method"]'
        );

        const bankInfo = document.getElementById(
            'bankInfo'
        );

        const shebaBox = document.getElementById(
            'shebaBox'
        );

        const cardBox = document.getElementById(
            'cardBox'
        );

        const amountSection = document.getElementById(
            'amountSection'
        );

        const proofSection = document.getElementById(
            'proofSection'
        );

        const amountInput = document.getElementById(
            'paymentAmount'
        );

        const referenceInput = document.getElementById(
            'referenceNo'
        );

        const receiptInput = document.getElementById(
            'receipt'
        );

        const submitButton = document.getElementById(
            'paymentSubmit'
        );


        function updatePaymentForm() {

            const selected =
                document.querySelector(
                    'input[name="method"]:checked'
                );

            if (!selected) {
                return;
            }

            const method = selected.value;

            bankInfo.hidden = false;
            amountSection.hidden = false;
            proofSection.hidden = false;

            shebaBox.hidden =
                method !== 'sheba';

            cardBox.hidden =
                method !== 'card_to_card';

            /*
             * درگاه آنلاین بعداً:
             *
             * amountSection و proofSection
             * برای IPG نمایش داده نمی‌شوند.
             *
             * مبلغ مستقیماً از PHP به gateway
             * ارسال خواهد شد.
             */

            if (method === 'online') {

                bankInfo.hidden = true;
                amountSection.hidden = true;
                proofSection.hidden = true;

                if (submitButton) {
                    submitButton.disabled = false;
                }

                return;
            }

            validateForm();
        }


        function validateForm() {

            if (!submitButton) {
                return;
            }

            const selected =
                document.querySelector(
                    'input[name="method"]:checked'
                );

            if (!selected) {
                submitButton.disabled = true;
                return;
            }

            const amount =
                Number(
                    amountInput?.value || 0
                );

            const max =
                Number(
                    amountInput?.max || 0
                );

            const reference =
                (
                    referenceInput?.value || ''
                ).trim();

            const hasReceipt = !!(
                receiptInput &&
                receiptInput.files &&
                receiptInput.files.length
            );

            const validAmount =
                amount > 0 &&
                amount <= max;

            const validProof =
                reference !== '' ||
                hasReceipt;

            submitButton.disabled = !validAmount ||
                !validProof;
        }


        methods.forEach(function(radio) {

            radio.addEventListener(
                'change',
                updatePaymentForm
            );

        });


        [
            amountInput,
            referenceInput,
            receiptInput
        ].forEach(function(element) {

            if (!element) {
                return;
            }

            element.addEventListener(
                'input',
                validateForm
            );

            element.addEventListener(
                'change',
                validateForm
            );

        });


        document
            .querySelectorAll('.btn-copy')
            .forEach(function(button) {

                button.addEventListener(
                    'click',
                    function() {

                        const value =
                            button.dataset.copy || '';

                        if (!value) {
                            return;
                        }

                        if (
                            navigator.clipboard &&
                            navigator.clipboard.writeText
                        ) {

                            navigator.clipboard
                                .writeText(value)
                                .then(function() {

                                    if (
                                        typeof showToast ===
                                        'function'
                                    ) {
                                        showToast(
                                            '✓ کپی شد'
                                        );
                                    }

                                });

                        }

                    }
                );

            });

    })();
</script>


<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
