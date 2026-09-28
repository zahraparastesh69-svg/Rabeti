<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/InvoiceService.php';
require_once ROOT_PATH . '/app/services/PdfService.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$db = db();

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


/*
|--------------------------------------------------------------------------
| پیدا کردن ID فاکتور با شماره فاکتور
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        id,
        user_id
    FROM invoices
    WHERE invoice_number = ?
    LIMIT 1
");

$stmt->execute([
    $invoiceNumber
]);

$invoiceRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoiceRow) {
    http_response_code(404);
    exit('فاکتور پیدا نشد.');
}


/*
|--------------------------------------------------------------------------
| بررسی دسترسی
|--------------------------------------------------------------------------
*/

$invoiceOwnerId = (int)($invoiceRow['user_id'] ?? 0);

if (
    !$isAdmin
    && $invoiceOwnerId !== $userId
) {
    http_response_code(403);
    exit('دسترسی غیرمجاز.');
}


/*
|--------------------------------------------------------------------------
| دریافت کامل فاکتور
|--------------------------------------------------------------------------
*/

$invoice = InvoiceService::find(
    $db,
    (int)$invoiceRow['id']
);

if (!$invoice) {
    http_response_code(404);
    exit('فاکتور پیدا نشد.');
}


/*
|--------------------------------------------------------------------------
| تولید و دانلود PDF
|--------------------------------------------------------------------------
*/

try {

    PdfService::output(
        $invoice
    );
} catch (Throwable $e) {

    http_response_code(500);

    exit('PDF ERROR: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ));

    http_response_code(500);
?>
    <!doctype html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="utf-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1">
        <meta
            name="robots"
            content="noindex, nofollow, noarchive">

        <title>خطا در تولید PDF</title>
    </head>

    <body
        style="
            font-family:Tahoma,Arial,sans-serif;
            padding:30px;
            line-height:2;
        ">

        <h2>
            تولید PDF انجام نشد
        </h2>

        <p>
            فاکتور پیدا شد، اما هنگام تولید فایل PDF خطایی رخ داد.
        </p>

        <a
            href="/invoice-print?inv=<?= rawurlencode($invoiceNumber) ?>">
            بازگشت به فاکتور
        </a>

    </body>

    </html>
<?php
}
