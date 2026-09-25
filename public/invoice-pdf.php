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

try {

    PdfService::output(
        $invoice
    );
} catch (Throwable $e) {

    error_log(
        'Invoice PDF error: ' .
            $e->getMessage()
    );

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
            فاکتور پیدا شد، اما سرویس PDF در حال حاضر
            آماده نیست.
        </p>

        <a
            href="/invoice-print?inv=<?= rawurlencode($invoiceNumber) ?>">
            بازگشت به فاکتور
        </a>

    </body>

    </html>
<?php
}
