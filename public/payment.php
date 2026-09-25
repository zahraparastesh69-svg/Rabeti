<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/config/upload.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$db = db();

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
        i.status AS inv_status
    FROM orders o
    LEFT JOIN invoices i ON i.order_id = o.id
    WHERE o.order_number = ?
      AND o.user_id = ?
    LIMIT 1
");

$stmt->execute([
    trim($_GET['order'] ?? ''),
    $userId
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
if (!$order['invoice_id']) {

    $invNum = 'INV-'
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
            (invoice_number, order_id, user_id, total)
        VALUES
            (?, ?, ?, ?)
    ");

    $invoiceStmt->execute([
        $invNum,
        $order['id'],
        $userId,
        $order['total']
    ]);

    $order['invoice_id'] = (int)$db->lastInsertId();
    $order['invoice_number'] = $invNum;
}

/*
|--------------------------------------------------------------------------
| بررسی اینکه قبلاً پرداختی برای سفارش ثبت شده یا نه
|--------------------------------------------------------------------------
*/
$paymentStmt = $db->prepare("
    SELECT
        id,
        method,
        amount,
        reference_no,
        receipt_path
    FROM payments
    WHERE invoice_id = ?
      AND user_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$paymentStmt->execute([
    $order['invoice_id'],
    $userId
]);

$existingPayment = $paymentStmt->fetch();

/*
|--------------------------------------------------------------------------
| ثبت پرداخت
|--------------------------------------------------------------------------
*/
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * اگر قبلاً پرداخت ثبت شده، دوباره ثبت نکن
     */
    if ($existingPayment) {

        $success = true;
    } else {

        $method = trim($_POST['method'] ?? '');
        $refNo = trim($_POST['reference_no'] ?? '');

        $validMethods = [
            'sheba',
            'card_to_card',
            'cash_receipt'
        ];

        /*
         * اعتبارسنجی روش پرداخت
         */
        if (!in_array($method, $validMethods, true)) {

            $error = 'روش پرداخت نامعتبر است';

            /*
         * حداقل یکی از شماره پیگیری یا فیش باید وجود داشته باشد
         */
        } elseif (
            $refNo === ''
            && empty($_FILES['receipt']['name'])
        ) {

            $error = 'شماره پیگیری وارد کنید یا فیش را بارگذاری کنید';
        } else {

            $receiptPath = null;

            /*
             * آپلود فیش
             */
            if (!empty($_FILES['receipt']['name'])) {

                $up = upload_image_ex(
                    $_FILES['receipt'],
                    'receipts'
                );

                if ($up['ok']) {

                    $receiptPath = $up['path'];
                } else {

                    $error = 'آپلود فیش ناموفق: ' . $up['error'];
                }
            }

            /*
             * ثبت پرداخت
             */
            if (!$error) {

                try {

                    /*
                     * دوباره داخل تراکنش بررسی می‌کنیم
                     * تا در ارسال همزمان فرم، پرداخت تکراری ثبت نشود.
                     */
                    $db->beginTransaction();

                    $checkStmt = $db->prepare("
                        SELECT id
                        FROM payments
                        WHERE invoice_id = ?
                          AND user_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $checkStmt->execute([
                        $order['invoice_id'],
                        $userId
                    ]);

                    $alreadyExists = $checkStmt->fetchColumn();

                    if ($alreadyExists) {

                        $db->commit();

                        $success = true;
                    } else {

                        $insertPayment = $db->prepare("
                            INSERT INTO payments
                                (
                                    invoice_id,
                                    user_id,
                                    method,
                                    amount,
                                    reference_no,
                                    receipt_path
                                )
                            VALUES
                                (?, ?, ?, ?, ?, ?)
                        ");

                        $insertPayment->execute([
                            $order['invoice_id'],
                            $userId,
                            $method,
                            (int)$order['total'],
                            $refNo !== '' ? $refNo : null,
                            $receiptPath
                        ]);

                        $db->commit();

                        $success = true;
                    }
                } catch (Throwable $e) {

                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    $success = false;

                    /*
                     * پیام فنی خطا به کاربر نمایش داده نمی‌شود.
                     */
                    $error = 'ثبت اطلاعات پرداخت انجام نشد. دوباره تلاش کنید.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| دریافت شماره شبا / کارت از Settings
|--------------------------------------------------------------------------
*/
$settings = [];

$settingsStmt = $db->query("
    SELECT `key`, `value`
    FROM settings
    WHERE `key` LIKE 'payment_%'
");

foreach ($settingsStmt as $s) {
    $settings[$s['key']] = $s['value'];
}

$pageTitle = 'پرداخت سفارش | رابطی';

ob_start();
?>

<nav class="breadcrumb">
    <a href="/">خانه</a> › <span>پرداخت</span>
</nav>

<?php if ($success): ?>

    <div class="empty-state">
        <div class="empty-icon">✅</div>

        <h2>اطلاعات پرداخت شما ثبت شد</h2>

        <p class="catalog-sub" style="margin-bottom:20px;">
            پس از تأیید مالی، سفارش شما وارد مرحله پردازش می‌شود.
        </p>

        <a
            href="/invoice-print?inv=<?= $h($order['invoice_number']) ?>"
            target="_blank"
            class="btn btn-outline"
            style="margin-bottom:10px;display:inline-block;">
            🧾 مشاهده / چاپ فاکتور
        </a>

        <br>

        <a href="/" class="btn btn-primary">
            بازگشت به صفحه اصلی
        </a>
    </div>

<?php else: ?>

    <section class="catalog-head">

        <h1 class="catalog-title">
            پرداخت سفارش
        </h1>

        <p class="catalog-sub">

            سفارش
            <b dir="ltr">
                <?= $h($order['order_number']) ?>
            </b>

            —

            مبلغ قابل پرداخت:

            <b style="color:var(--brand);">
                <?= number_format((float)$order['total']) ?>
                تومان
            </b>

        </p>

    </section>

    <?php if ($error): ?>

        <div class="form-alert error" style="display:block;">
            ⚠️ <?= $h($error) ?>
        </div>

    <?php endif; ?>

    <form
        method="post"
        class="co-form"
        enctype="multipart/form-data">

        <!-- ▂▂▂ انتخاب روش ▂▂▂ -->

        <div class="pay-methods">

            <label class="pay-method">

                <input
                    type="radio"
                    name="method"
                    value="sheba"
                    required>

                <span class="pay-method-body">

                    <b>
                        انتقال با شبا
                    </b>

                    <small>
                        شماره شبا نمایش داده می‌شود
                    </small>

                </span>

            </label>


            <label class="pay-method">

                <input
                    type="radio"
                    name="method"
                    value="card_to_card">

                <span class="pay-method-body">

                    <b>
                        کارت به کارت
                    </b>

                    <small>
                        شماره کارت نمایش داده می‌شود
                    </small>

                </span>

            </label>


            <label class="pay-method">

                <input
                    type="radio"
                    name="method"
                    value="cash_receipt">

                <span class="pay-method-body">

                    <b>
                        فیش نقدی
                    </b>

                    <small>
                        واریز حضوری به حساب
                    </small>

                </span>

            </label>


            <div class="pay-method pay-method-disabled">

                <span class="pay-method-body">

                    <b>
                        درگاه پرداخت آنلاین
                    </b>

                    <small>
                        به‌زودی 🔒
                    </small>

                </span>

            </div>

        </div>


        <!-- ▂▂▂ نمایش شبا/کارت انتخاب‌شده (JS) ▂▂▂ -->

        <div
            class="pay-details"
            id="paySheba"
            style="display:none;">

            <label>
                شماره شبا
            </label>

            <div class="pay-copy-row">

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
                به نام:
                <?= $h($settings['payment_card_name'] ?? '') ?>
            </small>

        </div>


        <div
            class="pay-details"
            id="payCard"
            style="display:none;">

            <label>
                شماره کارت
            </label>

            <div class="pay-copy-row">

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
                به نام:
                <?= $h($settings['payment_card_name'] ?? '') ?>
            </small>

        </div>


        <!-- ▂▂▂ اثبات پرداخت ▂▂▂ -->

        <div
            class="pay-proof"
            id="payProof"
            style="display:none;">

            <div class="form-field">

                <label>
                    شماره پیگیری
                </label>

                <input
                    type="text"
                    name="reference_no"
                    dir="ltr"
                    placeholder="مثلاً: 840512">

            </div>


            <div class="form-field">

                <label>
                    یا بارگذاری فیش واریزی
                </label>

                <input
                    type="file"
                    name="receipt"
                    accept="image/jpeg,image/png,image/webp">

                <span class="field-hint">
                    JPG/PNG — حداکثر ۲MB
                </span>

            </div>


            <p class="form-hint">
                حداقل یکی از این دو لازم است — پس از تأیید مالی اطلاع داده می‌شود
            </p>

        </div>


        <button
            type="submit"
            class="btn btn-primary co-submit"
            id="paySubmit"
            disabled>
            ثبت اطلاعات پرداخت ←
        </button>

    </form>


    <script>
        // نمایش جزئیات بر اساس روش انتخابی
        document.querySelectorAll('input[name="method"]').forEach(r => {

            r.addEventListener('change', () => {

                document.getElementById('paySheba').style.display =
                    r.value === 'sheba' ?
                    'block' :
                    'none';

                document.getElementById('payCard').style.display =
                    r.value === 'card_to_card' ?
                    'block' :
                    'none';

                document.getElementById('payProof').style.display =
                    'block';

                document.getElementById('paySubmit').disabled =
                    false;
            });

        });


        // کپی به کلیپ‌بورد
        document.querySelectorAll('.btn-copy').forEach(b => {

            b.addEventListener('click', () => {

                navigator.clipboard.writeText(b.dataset.copy)

                    .then(() => showToast('✓ کپی شد'))

                    .catch(() => showToast('کپی نشد — دستی انتخاب کنید'));

            });

        });
    </script>

<?php endif; ?>

<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
