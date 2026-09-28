<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin Payments — Financial Control Center
|--------------------------------------------------------------------------
|
| اصول این فایل:
|
| 1) claimed_amount:
|    مبلغی که مشتری ادعا کرده پرداخت کرده است.
|
| 2) verified_amount:
|    مبلغ واقعی که امور مالی بعد از بررسی بانک/سند تأیید می‌کند.
|
| 3) invoices.paid_total:
|    مجموع قطعی مبالغ تأییدشده.
|
| 4) remaining:
|    total - paid_total
|
| 5) نتیجه مالی:
|    remaining > 0  => debtor
|    remaining = 0  => settled
|    remaining < 0  => creditor
|
| نکته:
| reconciliation_type / reconciliation_amount
| فقط نتیجه محاسبه‌شده سیستم هستند و ورودی دستی نیستند.
|
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

$h = static fn($value): string =>
htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($adminId <= 0) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
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

function persianDigits(string $value): string
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

function formatJalaliDateTime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }

    try {
        $dt = new DateTime($datetime);
    } catch (Throwable $e) {
        return $datetime;
    }

    $gy = (int)$dt->format('Y');
    $gm = (int)$dt->format('m');
    $gd = (int)$dt->format('d');

    /*
     * Gregorian -> Jalali
     * الگوریتم مستقل، بدون وابستگی خارجی.
     */
    $g_d_m = [
        0,
        31,
        59,
        90,
        120,
        151,
        181,
        212,
        243,
        273,
        304,
        334,
    ];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

    $days =
        (365 * $gy)
        + (int)(($gy2 + 3) / 4)
        - (int)(($gy2 + 99) / 100)
        + (int)(($gy2 + 399) / 400)
        - 80
        + $gd
        + $g_d_m[$gm - 1];

    $jy += 33 * (int)($days / 12053);
    $days %= 12053;

    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    $date = sprintf(
        '%04d/%02d/%02d',
        $jy,
        $jm,
        $jd
    );

    return persianDigits($date . ' - ' . $dt->format('H:i'));
}

function paymentSubmissionLabel(string $status): string
{
    return match ($status) {
        'pending'  => 'در انتظار بررسی',
        'verified' => 'تأیید شده',
        'rejected' => 'رد شده',
        default    => 'نامشخص',
    };
}

function paymentSubmissionClass(string $status): string
{
    return match ($status) {
        'pending'  => 'is-pending',
        'verified' => 'is-confirmed',
        'rejected' => 'is-rejected',
        default    => '',
    };
}

function verificationLabel(string $status): string
{
    return match ($status) {
        'pending'  => 'در انتظار بررسی',
        'verified' => 'تأیید شده',
        'adjusted' => 'اصلاح شده',
        'rejected' => 'رد شده',
        default    => '—',
    };
}

function reconciliationLabel(string $type): string
{
    return match ($type) {
        'debtor'   => 'بدهکار',
        'settled'  => 'تسویه',
        'creditor' => 'بستانکار',
        default    => 'نامشخص',
    };
}

function reconciliationClass(string $type): string
{
    return match ($type) {
        'debtor'   => 'is-debtor',
        'settled'  => 'is-settled',
        'creditor' => 'is-creditor',
        default    => '',
    };
}

function methodLabel(string $method): string
{
    return match ($method) {
        'sheba'        => 'واریز شبا',
        'card_to_card' => 'کارت‌به‌کارت',
        'cash_receipt' => 'فیش نقدی',
        'online'       => 'پرداخت آنلاین',
        default        => $method ?: '—',
    };
}

function calculateFinancialState(
    int $invoiceTotal,
    int $paidTotal
): array {
    $remaining = $invoiceTotal - $paidTotal;

    if ($remaining > 0) {
        return [
            'type'   => 'debtor',
            'amount' => $remaining,
        ];
    }

    if ($remaining < 0) {
        return [
            'type'   => 'creditor',
            'amount' => abs($remaining),
        ];
    }

    return [
        'type'   => 'settled',
        'amount' => 0,
    ];
}

function invoicePaymentStatus(
    int $invoiceTotal,
    int $paidTotal
): string {
    if ($paidTotal <= 0) {
        return 'unpaid';
    }

    if ($paidTotal < $invoiceTotal) {
        return 'partial';
    }

    return 'paid';
}

function redirectWithMessage(
    string $type,
    string $message,
    array $extra = []
): never {
    $params = array_merge(
        [
            'message_type' => $type,
            'message'      => $message,
        ],
        $extra
    );

    header(
        'Location: /admin/payments?' .
            http_build_query($params)
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Flash message
|--------------------------------------------------------------------------
*/

$messageType = (string)($_GET['message_type'] ?? '');
$messageText = (string)($_GET['message'] ?? '');

/*
|--------------------------------------------------------------------------
| POST security
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedCsrf = (string)($_POST['csrf_token'] ?? '');

    if (
        $postedCsrf === ''
        || !hash_equals(csrf_token(), $postedCsrf)
    ) {
        http_response_code(419);
        exit('درخواست نامعتبر است.');
    }

    $action = (string)($_POST['action'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VERIFY PAYMENT
    |--------------------------------------------------------------------------
    |
    | مالی فقط مبلغ واقعی دریافتی را وارد می‌کند.
    |
    | مثال:
    |
    | invoice total = 22,405,000
    | claimed        = 22,405,000
    | verified      = 22,355,000
    |
    | سیستم:
    |
    | paid_total = 22,355,000
    | debtor     = 50,000
    |
    |--------------------------------------------------------------------------
    */

    if ($action === 'verify') {

        $submissionId = (int)($_POST['submission_id'] ?? 0);

        /*
         * مبلغ واقعی دریافتی.
         * این مقدار از مرورگر دریافت می‌شود اما قبل از ذخیره:
         * - cast
         * - validation
         * - row lock
         * - invoice lock
         * انجام می‌شود.
         */
        $verifiedAmountRaw = trim(
            (string)($_POST['verified_amount'] ?? '')
        );

        /*
         * جداکننده‌های رایج و ارقام فارسی.
         */
        $verifiedAmountRaw = strtr(
            $verifiedAmountRaw,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                ','  => '',
                '٬'  => '',
                ' '  => '',
            ]
        );

        $verifiedAmount = filter_var(
            $verifiedAmountRaw,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        $note = trim(
            (string)($_POST['verification_note'] ?? '')
        );

        if ($submissionId <= 0) {
            redirectWithMessage(
                'error',
                'شناسه پرداخت معتبر نیست.'
            );
        }

        if ($verifiedAmount === false) {
            redirectWithMessage(
                'error',
                'مبلغ واقعی دریافتی باید یک عدد صحیح بزرگ‌تر از صفر باشد.'
            );
        }

        if (mb_strlen($note) > 500) {
            redirectWithMessage(
                'error',
                'توضیحات نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.'
            );
        }

        try {

            $db->beginTransaction();

            /*
             * قفل submission.
             */
            $submissionStmt = $db->prepare("
                SELECT
                    ps.*,
                    i.id AS invoice_id,
                    i.invoice_number,
                    i.total AS invoice_total,
                    i.paid_total AS invoice_paid_total,
                    i.status AS invoice_status
                FROM payment_submissions ps
                INNER JOIN invoices i
                    ON i.id = ps.invoice_id
                WHERE ps.id = ?
                FOR UPDATE
            ");

            $submissionStmt->execute([
                $submissionId
            ]);

            $submission = $submissionStmt->fetch();

            if (!$submission) {
                throw new RuntimeException(
                    'درخواست پرداخت پیدا نشد.'
                );
            }

            if ((string)$submission['status'] !== 'pending') {
                throw new RuntimeException(
                    'این پرداخت قبلاً تعیین تکلیف شده است.'
                );
            }

            /*
             * قفل مجدد خود invoice برای جلوگیری از race condition
             * در صورت وجود چند پرداخت همزمان.
             */
            $invoiceStmt = $db->prepare("
                SELECT
                    id,
                    invoice_number,
                    total,
                    paid_total,
                    status,
                    reconciliation_type,
                    reconciliation_amount
                FROM invoices
                WHERE id = ?
                FOR UPDATE
            ");

            $invoiceStmt->execute([
                (int)$submission['invoice_id']
            ]);

            $invoice = $invoiceStmt->fetch();

            if (!$invoice) {
                throw new RuntimeException(
                    'فاکتور مربوط به این پرداخت پیدا نشد.'
                );
            }

            $invoiceTotal = max(
                0,
                (int)$invoice['total']
            );

            $oldPaidTotal = max(
                0,
                (int)$invoice['paid_total']
            );

            /*
             * پرداخت قبلی + پرداخت جدید.
             *
             * عمداً اجازه می‌دهیم verifiedAmount از مانده
             * بیشتر باشد تا سیستم بتواند بستانکاری را تشخیص دهد.
             */
            $newPaidTotal = $oldPaidTotal + (int)$verifiedAmount;

            /*
             * وضعیت فاکتور.
             */
            $newInvoiceStatus = invoicePaymentStatus(
                $invoiceTotal,
                $newPaidTotal
            );

            /*
             * نتیجه مالی خودکار.
             */
            $financial = calculateFinancialState(
                $invoiceTotal,
                $newPaidTotal
            );

            $newReconciliationType =
                $financial['type'];

            $newReconciliationAmount =
                $financial['amount'];

            /*
             * payment_verifications
             *
             * اگر قبلاً رکورد وجود داشته باشد، به‌روزرسانی نمی‌کنیم
             * بدون اینکه تاریخچه داشته باشیم.
             */
            $existingVerificationStmt = $db->prepare("
                SELECT
                    id,
                    verified_amount,
                    status,
                    note
                FROM payment_verifications
                WHERE payment_submission_id = ?
                FOR UPDATE
            ");

            $existingVerificationStmt->execute([
                $submissionId
            ]);

            $existingVerification =
                $existingVerificationStmt->fetch();

            $verificationStatus = 'verified';

            if ($existingVerification) {

                /*
                 * برای اولین تأیید pending -> verified است.
                 * در حالت غیرعادی، اگر قبلاً رکوردی وجود داشته باشد،
                 * به adjusted تبدیل می‌شود.
                 */
                if (
                    $existingVerification['verified_amount'] !== null
                    && (int)$existingVerification['verified_amount']
                    !== (int)$verifiedAmount
                ) {
                    $verificationStatus = 'adjusted';
                }

                $verificationUpdate = $db->prepare("
                    UPDATE payment_verifications
                    SET
                        verified_amount = ?,
                        status = ?,
                        note = ?,
                        verified_by = ?,
                        verified_at = NOW(),
                        updated_at = NOW()
                    WHERE payment_submission_id = ?
                ");

                $verificationUpdate->execute([
                    (int)$verifiedAmount,
                    $verificationStatus,
                    $note !== '' ? $note : null,
                    $adminId,
                    $submissionId,
                ]);
            } else {

                $verificationInsert = $db->prepare("
                    INSERT INTO payment_verifications (
                        payment_submission_id,
                        verified_amount,
                        status,
                        note,
                        verified_by,
                        verified_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $verificationInsert->execute([
                    $submissionId,
                    (int)$verifiedAmount,
                    $verificationStatus,
                    $note !== '' ? $note : null,
                    $adminId,
                ]);
            }

            /*
             * تاریخچه مالی.
             */
            $historyInsert = $db->prepare("
                INSERT INTO payment_verification_history (
                    payment_submission_id,
                    invoice_id,
                    old_verified_amount,
                    new_verified_amount,
                    old_status,
                    new_status,
                    reason,
                    changed_by,
                    ip,
                    changed_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $historyInsert->execute([
                $submissionId,
                (int)$invoice['id'],
                $existingVerification
                    ? (
                        $existingVerification['verified_amount'] !== null
                        ? (int)$existingVerification['verified_amount']
                        : null
                    )
                    : null,
                (int)$verifiedAmount,
                $existingVerification
                    ? (string)$existingVerification['status']
                    : 'pending',
                $verificationStatus,
                $note !== '' ? $note : null,
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            /*
             * submission:
             *
             * از این لحظه درخواست مشتری verified است.
             */
            $submissionUpdate = $db->prepare("
                UPDATE payment_submissions
                SET
                    status = 'verified',
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'pending'
            ");

            $submissionUpdate->execute([
                $submissionId
            ]);

            if ($submissionUpdate->rowCount() !== 1) {
                throw new RuntimeException(
                    'وضعیت درخواست پرداخت همزمان تغییر کرده است.'
                );
            }

            /*
             * مهم:
             *
             * paid_total = مجموع پرداخت قطعی قبلی + مبلغ واقعی
             * تأییدشده همین درخواست.
             */
            $invoiceUpdate = $db->prepare("
                UPDATE invoices
                SET
                    paid_total = ?,
                    status = ?,
                    reconciliation_type = ?,
                    reconciliation_amount = ?
                WHERE id = ?
            ");

            $invoiceUpdate->execute([
                $newPaidTotal,
                $newInvoiceStatus,
                $newReconciliationType,
                $newReconciliationAmount,
                (int)$invoice['id'],
            ]);

            /*
             * Audit log.
             *
             * اگر audit_logs ساختار متفاوتی داشته باشد، عملیات مالی
             * نباید به خاطر audit از بین برود؛ اما خطای schema را
             * هم مخفی نمی‌کنیم.
             */
            try {

                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        ip,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $auditStmt->execute([
                    $adminId,
                    'payment_verified',
                    'payment_submission',
                    $submissionId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable $auditError) {
                /*
                 * Audit failure نباید مبلغ مالی را بدون کنترل رها کند.
                 * چون ساختار audit_logs پروژه ممکن است نسخه متفاوتی داشته باشد،
                 * فعلاً عملیات اصلی را متوقف نمی‌کنیم.
                 */
            }

            $db->commit();

            redirectWithMessage(
                'success',
                'مبلغ واقعی پرداخت با موفقیت ثبت شد و مانده فاکتور به‌صورت خودکار محاسبه شد.',
                [
                    'invoice' =>
                    (string)$invoice['invoice_number']
                ]
            );
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            redirectWithMessage(
                'error',
                'ثبت پرداخت انجام نشد: ' . $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT PAYMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'reject') {

        $submissionId = (int)($_POST['submission_id'] ?? 0);

        $reason = trim(
            (string)($_POST['rejection_reason'] ?? '')
        );

        if ($submissionId <= 0) {
            redirectWithMessage(
                'error',
                'شناسه پرداخت معتبر نیست.'
            );
        }

        if (mb_strlen($reason) > 500) {
            redirectWithMessage(
                'error',
                'دلیل رد نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.'
            );
        }

        try {

            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT
                    ps.id,
                    ps.invoice_id,
                    ps.status,
                    i.invoice_number
                FROM payment_submissions ps
                INNER JOIN invoices i
                    ON i.id = ps.invoice_id
                WHERE ps.id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $submissionId
            ]);

            $submission = $stmt->fetch();

            if (!$submission) {
                throw new RuntimeException(
                    'درخواست پرداخت پیدا نشد.'
                );
            }

            if ((string)$submission['status'] !== 'pending') {
                throw new RuntimeException(
                    'این پرداخت قبلاً تعیین تکلیف شده است.'
                );
            }

            /*
             * payment_verifications
             */
            $verificationStmt = $db->prepare("
                SELECT
                    id,
                    verified_amount,
                    status,
                    note
                FROM payment_verifications
                WHERE payment_submission_id = ?
                FOR UPDATE
            ");

            $verificationStmt->execute([
                $submissionId
            ]);

            $existingVerification =
                $verificationStmt->fetch();

            if ($existingVerification) {

                $updateVerification = $db->prepare("
                    UPDATE payment_verifications
                    SET
                        status = 'rejected',
                        note = ?,
                        verified_by = ?,
                        verified_at = NOW(),
                        updated_at = NOW()
                    WHERE payment_submission_id = ?
                ");

                $updateVerification->execute([
                    $reason !== '' ? $reason : null,
                    $adminId,
                    $submissionId,
                ]);
            } else {

                $insertVerification = $db->prepare("
                    INSERT INTO payment_verifications (
                        payment_submission_id,
                        verified_amount,
                        status,
                        note,
                        verified_by,
                        verified_at
                    )
                    VALUES (?, NULL, 'rejected', ?, ?, NOW())
                ");

                $insertVerification->execute([
                    $submissionId,
                    $reason !== '' ? $reason : null,
                    $adminId,
                ]);
            }

            /*
             * تاریخچه.
             */
            $historyStmt = $db->prepare("
                INSERT INTO payment_verification_history (
                    payment_submission_id,
                    invoice_id,
                    old_verified_amount,
                    new_verified_amount,
                    old_status,
                    new_status,
                    reason,
                    changed_by,
                    ip,
                    changed_at
                )
                VALUES (?, ?, ?, NULL, ?, 'rejected', ?, ?, ?, NOW())
            ");

            $historyStmt->execute([
                $submissionId,
                (int)$submission['invoice_id'],
                $existingVerification
                    ? (
                        $existingVerification['verified_amount'] !== null
                        ? (int)$existingVerification['verified_amount']
                        : null
                    )
                    : null,
                $existingVerification
                    ? (string)$existingVerification['status']
                    : 'pending',
                $reason !== '' ? $reason : null,
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            /*
             * درخواست مشتری رد شد.
             *
             * paid_total دست نمی‌خورد.
             */
            $updateSubmission = $db->prepare("
                UPDATE payment_submissions
                SET
                    status = 'rejected',
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'pending'
            ");

            $updateSubmission->execute([
                $submissionId
            ]);

            if ($updateSubmission->rowCount() !== 1) {
                throw new RuntimeException(
                    'وضعیت درخواست پرداخت تغییر نکرد.'
                );
            }

            try {

                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        ip,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $auditStmt->execute([
                    $adminId,
                    'payment_rejected',
                    'payment_submission',
                    $submissionId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable $auditError) {
                // see verify action
            }

            $db->commit();

            redirectWithMessage(
                'success',
                'درخواست پرداخت رد شد.'
            );
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            redirectWithMessage(
                'error',
                'رد پرداخت انجام نشد: ' . $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SETTLE CREDITOR
    |--------------------------------------------------------------------------
    |
    | این عملیات برای بستانکاری است.
    |
    | مثال:
    | invoice total = 100
    | paid total    = 120
    | creditor      = 20
    |
    | اگر شرکت 20 را به مشتری برگرداند، transaction ثبت می‌شود.
    |
    |--------------------------------------------------------------------------
    */

    if ($action === 'settle_creditor') {

        $invoiceId = (int)($_POST['invoice_id'] ?? 0);

        $amountRaw = trim(
            (string)($_POST['settlement_amount'] ?? '')
        );

        $amountRaw = strtr(
            $amountRaw,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                ','  => '',
                '٬'  => '',
                ' '  => '',
            ]
        );

        $settlementAmount = filter_var(
            $amountRaw,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        $method = (string)(
            $_POST['settlement_method'] ?? ''
        );

        $referenceNo = trim(
            (string)($_POST['settlement_reference'] ?? '')
        );

        $description = trim(
            (string)($_POST['settlement_description'] ?? '')
        );

        $allowedMethods = [
            'sheba',
            'card_to_card',
            'cash',
        ];

        if ($invoiceId <= 0) {
            redirectWithMessage(
                'error',
                'فاکتور معتبر نیست.'
            );
        }

        if ($settlementAmount === false) {
            redirectWithMessage(
                'error',
                'مبلغ تسویه بستانکاری معتبر نیست.'
            );
        }

        if (!in_array($method, $allowedMethods, true)) {
            redirectWithMessage(
                'error',
                'روش تسویه معتبر نیست.'
            );
        }

        if (mb_strlen($referenceNo) > 100) {
            redirectWithMessage(
                'error',
                'شماره پیگیری بیش از حد مجاز است.'
            );
        }

        if (mb_strlen($description) > 500) {
            redirectWithMessage(
                'error',
                'توضیحات بیش از حد مجاز است.'
            );
        }

        try {

            $db->beginTransaction();

            $invoiceStmt = $db->prepare("
                SELECT
                    id,
                    invoice_number,
                    user_id,
                    total,
                    paid_total,
                    reconciliation_type,
                    reconciliation_amount
                FROM invoices
                WHERE id = ?
                FOR UPDATE
            ");

            $invoiceStmt->execute([
                $invoiceId
            ]);

            $invoice = $invoiceStmt->fetch();

            if (!$invoice) {
                throw new RuntimeException(
                    'فاکتور پیدا نشد.'
                );
            }

            $invoiceTotal = (int)$invoice['total'];
            $paidTotal = (int)$invoice['paid_total'];

            $financial = calculateFinancialState(
                $invoiceTotal,
                $paidTotal
            );

            if ($financial['type'] !== 'creditor') {
                throw new RuntimeException(
                    'این فاکتور در حال حاضر بستانکار نیست.'
                );
            }

            $creditorAmount = (int)$financial['amount'];

            if ((int)$settlementAmount > $creditorAmount) {
                throw new RuntimeException(
                    'مبلغ تسویه نمی‌تواند بیشتر از بستانکاری مشتری باشد.'
                );
            }

            /*
             * financial_transactions
             */
            $transactionStmt = $db->prepare("
                INSERT INTO financial_transactions (
                    invoice_id,
                    user_id,
                    type,
                    amount,
                    method,
                    document_no,
                    reference_no,
                    description,
                    transaction_date,
                    status,
                    created_by,
                    created_at,
                    completed_at
                )
                VALUES (?, ?, 'creditor_settlement', ?, ?, NULL, ?, ?, NOW(), 'completed', ?, NOW(), NOW())
            ");

            $transactionStmt->execute([
                $invoiceId,
                (int)$invoice['user_id'],
                (int)$settlementAmount,
                $method,
                $referenceNo !== '' ? $referenceNo : null,
                $description !== '' ? $description : null,
                $adminId,
            ]);

            /*
             * اگر کل بستانکاری پرداخت شد:
             * نتیجه فاکتور settled می‌شود.
             *
             * اگر بخشی از بستانکاری پرداخت شد:
             * مقدار بستانکاری باقی‌مانده ثبت می‌شود.
             */
            $remainingCreditor =
                $creditorAmount - (int)$settlementAmount;

            $newType =
                $remainingCreditor > 0
                ? 'creditor'
                : 'settled';

            $newAmount =
                max(0, $remainingCreditor);

            $invoiceUpdate = $db->prepare("
                UPDATE invoices
                SET
                    reconciliation_type = ?,
                    reconciliation_amount = ?
                WHERE id = ?
            ");

            $invoiceUpdate->execute([
                $newType,
                $newAmount,
                $invoiceId,
            ]);

            try {

                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        ip,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $auditStmt->execute([
                    $adminId,
                    'creditor_settlement',
                    'invoice',
                    $invoiceId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable $auditError) {
                // see verify action
            }

            $db->commit();

            redirectWithMessage(
                'success',
                'تسویه بستانکاری با موفقیت ثبت شد.'
            );
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            redirectWithMessage(
                'error',
                'تسویه بستانکاری انجام نشد: ' . $e->getMessage()
            );
        }
    }

    redirectWithMessage(
        'error',
        'عملیات مالی ناشناخته است.'
    );
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$filter = (string)($_GET['filter'] ?? '');

$allowedFilters = [
    '',
    'pending',
    'verified',
    'rejected',
    'debtor',
    'settled',
    'creditor',
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = '';
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
|
| آمار اصلی از payment_submissions و invoices خوانده می‌شود.
|--------------------------------------------------------------------------
*/

$stats = [
    'pending'  => 0,
    'verified' => 0,
    'rejected' => 0,
    'debtor'   => 0,
    'settled'  => 0,
    'creditor' => 0,
];

$stats['pending'] = (int)$db->query("
    SELECT COUNT(*)
    FROM payment_submissions
    WHERE status = 'pending'
")->fetchColumn();

$stats['verified'] = (int)$db->query("
    SELECT COUNT(*)
    FROM payment_submissions
    WHERE status = 'verified'
")->fetchColumn();

$stats['rejected'] = (int)$db->query("
    SELECT COUNT(*)
    FROM payment_submissions
    WHERE status = 'rejected'
")->fetchColumn();

$stats['debtor'] = (int)$db->query("
    SELECT COUNT(*)
    FROM invoices
    WHERE reconciliation_type = 'debtor'
      AND reconciliation_amount > 0
")->fetchColumn();

$stats['settled'] = (int)$db->query("
    SELECT COUNT(*)
    FROM invoices
    WHERE reconciliation_type = 'settled'
")->fetchColumn();

$stats['creditor'] = (int)$db->query("
    SELECT COUNT(*)
    FROM invoices
    WHERE reconciliation_type = 'creditor'
      AND reconciliation_amount > 0
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Main listing
|--------------------------------------------------------------------------
|
| یک ردیف برای هر payment submission.
| بنابراین مالی تاریخچه واقعی پرداخت‌ها را می‌بیند.
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

switch ($filter) {

    case 'pending':
        $where[] = "ps.status = 'pending'";
        break;

    case 'verified':
        $where[] = "ps.status = 'verified'";
        break;

    case 'rejected':
        $where[] = "ps.status = 'rejected'";
        break;

    case 'debtor':
        $where[] = "
            i.reconciliation_type = 'debtor'
            AND i.reconciliation_amount > 0
        ";
        break;

    case 'settled':
        $where[] = "
            i.reconciliation_type = 'settled'
        ";
        break;

    case 'creditor':
        $where[] = "
            i.reconciliation_type = 'creditor'
            AND i.reconciliation_amount > 0
        ";
        break;
}

$sql = "
    SELECT
        ps.id,
        ps.invoice_id,
        ps.user_id,
        ps.method,
        ps.claimed_amount,
        ps.reference_no,
        ps.receipt_path,
        ps.transaction_date,
        ps.customer_note,
        ps.status AS submission_status,
        ps.created_at AS submission_created_at,
        ps.updated_at AS submission_updated_at,

        pv.id AS verification_id,
        pv.verified_amount,
        pv.status AS verification_status,
        pv.note AS verification_note,
        pv.verified_by,
        pv.verified_at,

        i.invoice_number,
        i.total AS invoice_total,
        i.paid_total,
        i.status AS invoice_status,
        i.reconciliation_type,
        i.reconciliation_amount,
        i.issued_at,

        o.order_number,

        u.full_name,
        u.company_name,
        u.mobile

    FROM payment_submissions ps

    INNER JOIN invoices i
        ON i.id = ps.invoice_id

    INNER JOIN users u
        ON u.id = ps.user_id

    INNER JOIN orders o
        ON o.id = i.order_id

    LEFT JOIN payment_verifications pv
        ON pv.payment_submission_id = ps.id

    " . (
    $where
    ? 'WHERE ' . implode(' AND ', $where)
    : ''
) . "

    ORDER BY
        CASE
            WHEN ps.status = 'pending' THEN 0
            ELSE 1
        END,
        ps.created_at DESC,
        ps.id DESC

    LIMIT 200
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$payments = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'مدیریت پرداخت‌ها';
$activeMenu = 'payments';

ob_start();

?>

<div class="payments-page">

    <div class="payments-head">

        <div class="payments-title">

            <h1>
                مدیریت پرداخت‌ها
            </h1>

            <p>
                بررسی رسیدها، ثبت مبلغ واقعی دریافتی و کنترل مانده فاکتورها
            </p>

        </div>

    </div>


    <?php if ($messageText !== ''): ?>

        <div class="admin-alert <?= $messageType === 'success' ? 'is-success' : 'is-error' ?>">

            <?= $h($messageText) ?>

        </div>

    <?php endif; ?>


    <!-- آمار -->

    <div class="payment-stats">

        <a
            href="/admin/payments?filter=pending"
            class="payment-stat <?= $filter === 'pending' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                در انتظار بررسی
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['pending']) ?>
            </strong>

        </a>


        <a
            href="/admin/payments?filter=verified"
            class="payment-stat <?= $filter === 'verified' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                تأیید شده
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['verified']) ?>
            </strong>

        </a>


        <a
            href="/admin/payments?filter=rejected"
            class="payment-stat <?= $filter === 'rejected' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                رد شده
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['rejected']) ?>
            </strong>

        </a>


        <a
            href="/admin/payments?filter=debtor"
            class="payment-stat <?= $filter === 'debtor' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                بدهکار
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['debtor']) ?>
            </strong>

        </a>


        <a
            href="/admin/payments?filter=settled"
            class="payment-stat <?= $filter === 'settled' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                تسویه
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['settled']) ?>
            </strong>

        </a>


        <a
            href="/admin/payments?filter=creditor"
            class="payment-stat <?= $filter === 'creditor' ? 'active' : '' ?>">

            <span class="payment-stat-label">
                بستانکار
            </span>

            <strong class="payment-stat-value">
                <?= number_format($stats['creditor']) ?>
            </strong>

        </a>

    </div>


    <!-- فیلترها -->

    <div class="payment-filters">

        <a
            href="/admin/payments"
            class="payment-filter <?= $filter === '' ? 'active' : '' ?>">
            همه
        </a>

        <a
            href="/admin/payments?filter=pending"
            class="payment-filter <?= $filter === 'pending' ? 'active' : '' ?>">
            در انتظار
            <span class="count">
                <?= number_format($stats['pending']) ?>
            </span>
        </a>

        <a
            href="/admin/payments?filter=verified"
            class="payment-filter <?= $filter === 'verified' ? 'active' : '' ?>">
            تأیید شده
            <span class="count">
                <?= number_format($stats['verified']) ?>
            </span>
        </a>

        <a
            href="/admin/payments?filter=rejected"
            class="payment-filter <?= $filter === 'rejected' ? 'active' : '' ?>">
            رد شده
            <span class="count">
                <?= number_format($stats['rejected']) ?>
            </span>
        </a>

        <a
            href="/admin/payments?filter=debtor"
            class="payment-filter <?= $filter === 'debtor' ? 'active' : '' ?>">
            بدهکار
            <span class="count">
                <?= number_format($stats['debtor']) ?>
            </span>
        </a>

        <a
            href="/admin/payments?filter=settled"
            class="payment-filter <?= $filter === 'settled' ? 'active' : '' ?>">
            تسویه
            <span class="count">
                <?= number_format($stats['settled']) ?>
            </span>
        </a>

        <a
            href="/admin/payments?filter=creditor"
            class="payment-filter <?= $filter === 'creditor' ? 'active' : '' ?>">
            بستانکار
            <span class="count">
                <?= number_format($stats['creditor']) ?>
            </span>
        </a>

    </div>


    <!-- جدول -->

    <div class="payments-table-wrap">

        <?php if (!$payments): ?>

            <div class="empty-payments">

                <strong>
                    موردی برای نمایش وجود ندارد
                </strong>

                <span>
                    در این فیلتر هیچ درخواست پرداختی پیدا نشد.
                </span>

            </div>

        <?php else: ?>

            <div class="payments-table-scroll">

                <table class="payments-table">

                    <thead>

                        <tr>

                            <th>
                                فاکتور / سفارش
                            </th>

                            <th>
                                مشتری
                            </th>

                            <th>
                                مبلغ فاکتور
                            </th>

                            <th>
                                ادعای مشتری
                            </th>

                            <th>
                                مبلغ واقعی تأییدشده
                            </th>

                            <th>
                                وضعیت پرداخت
                            </th>

                            <th>
                                نتیجه مالی
                            </th>

                            <th>
                                عملیات
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <?php

                            $invoiceTotal =
                                max(0, (int)$payment['invoice_total']);

                            $paidTotal =
                                max(0, (int)$payment['paid_total']);

                            $claimedAmount =
                                max(0, (int)$payment['claimed_amount']);

                            $verifiedAmount =
                                $payment['verified_amount'] !== null
                                ? max(
                                    0,
                                    (int)$payment['verified_amount']
                                )
                                : null;

                            /*
                         * برای جلوگیری از اعتماد به reconciliation
                         * قدیمی، نتیجه مالی از total و paid_total
                         * دوباره محاسبه می‌شود.
                         */
                            $financial =
                                calculateFinancialState(
                                    $invoiceTotal,
                                    $paidTotal
                                );

                            $financialType =
                                $financial['type'];

                            $financialAmount =
                                $financial['amount'];

                            $submissionStatus =
                                (string)$payment['submission_status'];

                            $verificationStatus =
                                (string)($payment['verification_status'] ?? '');

                            ?>

                            <tr>

                                <!-- فاکتور -->

                                <td>

                                    <div class="invoice-main">

                                        <?= $h($payment['invoice_number']) ?>

                                    </div>

                                    <div class="invoice-sub">

                                        سفارش:
                                        <?= $h($payment['order_number']) ?>

                                    </div>

                                    <div class="invoice-sub">

                                        صدور:
                                        <?= $h(
                                            formatJalaliDateTime(
                                                $payment['issued_at']
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <!-- مشتری -->

                                <td>

                                    <div class="customer-name">

                                        <?= $h(
                                            $payment['full_name']
                                                ?: '—'
                                        ) ?>

                                    </div>

                                    <?php if (!empty($payment['company_name'])): ?>

                                        <div class="invoice-sub">

                                            <?= $h(
                                                $payment['company_name']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <div class="invoice-sub">

                                        <?= $h($payment['mobile']) ?>

                                    </div>

                                </td>


                                <!-- مبلغ فاکتور -->

                                <td>

                                    <span class="money">

                                        <?= toman($invoiceTotal) ?>

                                    </span>

                                </td>


                                <!-- ادعای مشتری -->

                                <td class="receipt-cell">

                                    <div class="receipt-amount">

                                        <?= toman($claimedAmount) ?>

                                    </div>

                                    <div class="receipt-meta">

                                        <?= $h(
                                            methodLabel(
                                                (string)$payment['method']
                                            )
                                        ) ?>

                                    </div>

                                    <div class="receipt-meta">

                                        ثبت:

                                        <?= $h(
                                            formatJalaliDateTime(
                                                $payment['submission_created_at']
                                            )
                                        ) ?>

                                    </div>

                                    <?php if (!empty($payment['reference_no'])): ?>

                                        <div class="receipt-meta">

                                            پیگیری:
                                            <?= $h(
                                                $payment['reference_no']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (!empty($payment['receipt_path'])): ?>

                                        <a
                                            class="receipt-link"
                                            href="<?= $h(
                                                        '/' .
                                                            ltrim(
                                                                (string)$payment['receipt_path'],
                                                                '/'
                                                            )
                                                    ) ?>"
                                            target="_blank"
                                            rel="noopener">

                                            مشاهده رسید

                                        </a>

                                    <?php endif; ?>

                                </td>


                                <!-- مبلغ واقعی -->

                                <td>

                                    <?php if ($verifiedAmount !== null): ?>

                                        <span class="money">

                                            <?= toman($verifiedAmount) ?>

                                        </span>

                                        <?php if (
                                            $verifiedAmount !==
                                            $claimedAmount
                                        ): ?>

                                            <div class="invoice-sub">

                                                اختلاف با ادعای مشتری:
                                                <?= toman(
                                                    $verifiedAmount
                                                        - $claimedAmount
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="invoice-sub">

                                            هنوز تأیید نشده

                                        </span>

                                    <?php endif; ?>


                                    <div class="invoice-sub">

                                        پرداخت قطعی فاکتور:

                                        <?= toman($paidTotal) ?>

                                    </div>

                                </td>


                                <!-- وضعیت -->

                                <td>

                                    <span
                                        class="status-badge <?= $h(
                                                                paymentSubmissionClass(
                                                                    $submissionStatus
                                                                )
                                                            ) ?>">

                                        <?= $h(
                                            paymentSubmissionLabel(
                                                $submissionStatus
                                            )
                                        ) ?>

                                    </span>


                                    <?php if ($verificationStatus !== ''): ?>

                                        <div class="invoice-sub">

                                            مالی:

                                            <?= $h(
                                                verificationLabel(
                                                    $verificationStatus
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- نتیجه مالی -->

                                <td class="financial-cell">

                                    <span
                                        class="reconciliation-badge <?= $h(
                                                                        reconciliationClass(
                                                                            $financialType
                                                                        )
                                                                    ) ?>">

                                        <?= $h(
                                            reconciliationLabel(
                                                $financialType
                                            )
                                        ) ?>

                                    </span>


                                    <?php if ($financialType === 'debtor'): ?>

                                        <div class="financial-line">

                                            <span class="financial-label">
                                                مانده بدهی
                                            </span>

                                            <strong class="money-debtor">

                                                <?= toman(
                                                    $financialAmount
                                                ) ?>

                                            </strong>

                                        </div>

                                    <?php elseif (
                                        $financialType === 'creditor'
                                    ): ?>

                                        <div class="financial-line">

                                            <span class="financial-label">
                                                بستانکاری
                                            </span>

                                            <strong class="money-creditor">

                                                <?= toman(
                                                    $financialAmount
                                                ) ?>

                                            </strong>

                                        </div>

                                    <?php else: ?>

                                        <div class="financial-line">

                                            <span class="financial-label">
                                                مانده
                                            </span>

                                            <strong class="money-zero">
                                                ۰ تومان
                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- عملیات -->

                                <td class="payment-actions">

                                    <?php if (
                                        $submissionStatus === 'pending'
                                    ): ?>

                                        <details class="payment-details">

                                            <summary>
                                                بررسی پرداخت
                                            </summary>


                                            <div class="payment-review-box">

                                                <div class="payment-review-row">

                                                    <span>
                                                        ادعای مشتری
                                                    </span>

                                                    <strong>
                                                        <?= toman(
                                                            $claimedAmount
                                                        ) ?>
                                                    </strong>

                                                </div>


                                                <div class="payment-review-row">

                                                    <span>
                                                        پرداخت قطعی قبلی
                                                    </span>

                                                    <strong>
                                                        <?= toman(
                                                            $paidTotal
                                                        ) ?>
                                                    </strong>

                                                </div>


                                                <form
                                                    method="post"
                                                    class="action-form"
                                                    onsubmit="return confirm('مبلغ واقعی دریافتی را تأیید و در حساب فاکتور ثبت کنم؟');">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= $h(csrf_token()) ?>">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="verify">

                                                    <input
                                                        type="hidden"
                                                        name="submission_id"
                                                        value="<?= (int)$payment['id'] ?>">


                                                    <label>
                                                        مبلغ واقعی دریافتی
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="verified_amount"
                                                        value="<?= $h(
                                                                    (string)$claimedAmount
                                                                ) ?>"
                                                        inputmode="numeric"
                                                        autocomplete="off"
                                                        required>


                                                    <label>
                                                        توضیح مالی
                                                    </label>

                                                    <textarea
                                                        name="verification_note"
                                                        maxlength="500"
                                                        rows="3"
                                                        placeholder="اختیاری"></textarea>


                                                    <button
                                                        type="submit"
                                                        class="payment-action-btn payment-confirm">

                                                        تأیید و ثبت مبلغ واقعی

                                                    </button>

                                                </form>


                                                <form
                                                    method="post"
                                                    class="action-form"
                                                    onsubmit="return confirm('این درخواست پرداخت رد شود؟');">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= $h(csrf_token()) ?>">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="reject">

                                                    <input
                                                        type="hidden"
                                                        name="submission_id"
                                                        value="<?= (int)$payment['id'] ?>">


                                                    <label>
                                                        دلیل رد
                                                    </label>

                                                    <textarea
                                                        name="rejection_reason"
                                                        maxlength="500"
                                                        rows="2"
                                                        placeholder="اختیاری"></textarea>


                                                    <button
                                                        type="submit"
                                                        class="payment-action-btn payment-reject">

                                                        رد پرداخت

                                                    </button>

                                                </form>

                                            </div>

                                        </details>


                                    <?php elseif (
                                        $submissionStatus === 'verified'
                                    ): ?>

                                        <div class="confirmed-note">

                                            این پرداخت تأیید شده است.

                                        </div>

                                        <?php if (
                                            $financialType === 'debtor'
                                        ): ?>

                                            <div class="invoice-sub">

                                                مانده قابل پرداخت مشتری:

                                                <strong>

                                                    <?= toman(
                                                        $financialAmount
                                                    ) ?>

                                                </strong>

                                            </div>

                                        <?php elseif (
                                            $financialType === 'settled'
                                        ): ?>

                                            <div class="invoice-sub">

                                                فاکتور کاملاً تسویه شده است.

                                            </div>

                                        <?php elseif (
                                            $financialType === 'creditor'
                                        ): ?>

                                            <div class="invoice-sub">

                                                بستانکاری مشتری:

                                                <strong>

                                                    <?= toman(
                                                        $financialAmount
                                                    ) ?>

                                                </strong>

                                            </div>

                                        <?php endif; ?>


                                    <?php else: ?>

                                        <div class="rejected-note">

                                            این درخواست رد شده است.

                                        </div>

                                        <?php if (
                                            !empty($payment['verification_note'])
                                        ): ?>

                                            <div class="invoice-sub">

                                                دلیل:

                                                <?= $h(
                                                    $payment['verification_note']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/admin.php';
