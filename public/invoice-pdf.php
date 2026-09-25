<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$isAdmin = is_admin_logged_in();

$invoiceNumber = trim((string)($_GET['inv'] ?? ''));

if ($invoiceNumber === '') {
    http_response_code(400);
    exit('شماره فاکتور مشخص نشده است.');
}


/*
|--------------------------------------------------------------------------
| دریافت فاکتور
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
| اقلام
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
| TCPDF
|--------------------------------------------------------------------------
|
| مسیر TCPDF در پروژه‌های مختلف ممکن است متفاوت باشد.
| این بخش چند مسیر متداول را بررسی می‌کند.
|--------------------------------------------------------------------------
*/

$tcpdfLoaded = false;

$tcpdfPaths = [
    ROOT_PATH . '/vendor/autoload.php',
    ROOT_PATH . '/tcpdf/tcpdf.php',
    ROOT_PATH . '/libraries/tcpdf/tcpdf.php',
    ROOT_PATH . '/app/libs/tcpdf/tcpdf.php',
];

foreach ($tcpdfPaths as $path) {

    if (!is_file($path)) {
        continue;
    }

    require_once $path;

    if (class_exists('TCPDF')) {
        $tcpdfLoaded = true;
        break;
    }
}

if (!$tcpdfLoaded) {

    http_response_code(500);

    exit('TCPDF در پروژه نصب یا در مسیر مشخص‌شده پیدا نشد.');
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function pdfEsc(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function pdfMoney(mixed $value): string
{
    return number_format(
        (float)($value ?? 0),
        0,
        '.',
        ','
    );
}

function pdfStatus(?string $status): string
{
    return [
        'unpaid'  => 'پرداخت نشده',
        'partial' => 'پرداخت جزئی',
        'paid'    => 'پرداخت شده',
        'void'    => 'باطل شده',
        'cancelled' => 'لغو شده',
    ][$status ?? ''] ?? 'نامشخص';
}


/*
|--------------------------------------------------------------------------
| محاسبه
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($items as $item) {

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

    $subtotal += $qty * $unitPrice;
}

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
| TCPDF
|--------------------------------------------------------------------------
*/

$pdf = new TCPDF(
    'P',
    'mm',
    'A4',
    true,
    'UTF-8',
    false
);

$pdf->SetCreator('sock-b2b');
$pdf->SetAuthor('sock-b2b');
$pdf->SetTitle('فاکتور ' . $invoiceNumber);
$pdf->SetSubject('Invoice');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();


/*
|--------------------------------------------------------------------------
| فونت فارسی
|--------------------------------------------------------------------------
|
| اگر فونت فارسی سفارشی در پروژه داری، این بخش را می‌توان
| بعداً به فونت پروژه متصل کرد.
|--------------------------------------------------------------------------
*/

$pdf->SetFont('dejavusans', '', 9);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

$html = '

<h1 style="text-align:center;">
    فاکتور فروش
</h1>

<table cellpadding="5" cellspacing="0" border="1">

<tr>
    <td width="50%">
        <b>شماره فاکتور:</b>
        ' . pdfEsc($invoice['invoice_number']) . '
    </td>

    <td width="50%">
        <b>شماره سفارش:</b>
        ' . pdfEsc($invoice['order_number']) . '
    </td>
</tr>

<tr>
    <td>
        <b>تاریخ:</b>
        ' . pdfEsc(
    $invoice['issued_at'] ?? '—'
) . '
    </td>

    <td>
        <b>وضعیت:</b>
        ' . pdfStatus(
    $invoice['status'] ?? null
) . '
    </td>
</tr>

</table>

<br>

<h3>
    اطلاعات مشتری
</h3>

<table cellpadding="5" cellspacing="0" border="1">

<tr>

    <td width="50%">
        <b>نام:</b>
        ' . pdfEsc(
    $invoice['customer_name'] ?? '—'
) . '
    </td>

    <td width="50%">
        <b>موبایل:</b>
        ' . pdfEsc(
    $invoice['customer_mobile']
        ?? $invoice['shipping_mobile']
        ?? '—'
) . '
    </td>

</tr>

<tr>

    <td colspan="2">
        <b>آدرس:</b>
        ' . pdfEsc(
    $invoice['shipping_address'] ?? '—'
) . '
    </td>

</tr>

</table>

<br>

<h3>
    اقلام فاکتور
</h3>

<table cellpadding="5" cellspacing="0" border="1">

<tr>
    <th width="8%">ردیف</th>
    <th width="32%">کالا</th>
    <th width="15%">رنگ</th>
    <th width="10%">سایز</th>
    <th width="10%">تعداد</th>
    <th width="12%">قیمت</th>
    <th width="13%">مبلغ</th>
</tr>
';


foreach ($items as $index => $item) {

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

    $color =
        $item['color_name']
        ?? $item['color']
        ?? '—';

    $size =
        $item['size_name']
        ?? $item['size']
        ?? '—';

    $html .= '

    <tr>

        <td align="center">
            ' . ($index + 1) . '
        </td>

        <td>
            ' . pdfEsc($productName) . '
        </td>

        <td>
            ' . pdfEsc($color) . '
        </td>

        <td>
            ' . pdfEsc($size) . '
        </td>

        <td align="center">
            ' . number_format($qty, 0) . '
        </td>

        <td align="right">
            ' . pdfMoney($unitPrice) . '
        </td>

        <td align="right">
            ' . pdfMoney($lineTotal) . '
        </td>

    </tr>

    ';
}


$html .= '

</table>

<br>

<table cellpadding="6" cellspacing="0" border="0">

<tr>
    <td width="70%">
        جمع کالاها
    </td>

    <td width="30%" align="right">
        ' . pdfMoney($subtotal) . '
    </td>
</tr>
';


if ($discountAmount > 0) {

    $html .= '

    <tr>
        <td width="70%">
            تخفیف
        </td>

        <td width="30%" align="right">
            ' . pdfMoney($discountAmount) . '
        </td>
    </tr>

    ';
}


if ($shippingAmount > 0) {

    $html .= '

    <tr>
        <td width="70%">
            هزینه ارسال
        </td>

        <td width="30%" align="right">
            ' . pdfMoney($shippingAmount) . '
        </td>
    </tr>

    ';
}


if ($taxAmount > 0) {

    $html .= '

    <tr>
        <td width="70%">
            مالیات
        </td>

        <td width="30%" align="right">
            ' . pdfMoney($taxAmount) . '
        </td>
    </tr>

    ';
}


$html .= '

<tr>
    <td width="70%">
        <b>مبلغ نهایی</b>
    </td>

    <td width="30%" align="right">
        <b>' . pdfMoney($totalAmount) . '</b>
    </td>
</tr>

</table>

<br><br>

<div align="center">
    این فاکتور به صورت سیستمی صادر شده است.
</div>

';


$pdf->writeHTML(
    $html,
    true,
    false,
    true,
    false,
    ''
);


/*
|--------------------------------------------------------------------------
| خروجی PDF
|--------------------------------------------------------------------------
*/

$pdf->Output(
    'invoice-' . preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '-',
        $invoiceNumber
    ) . '.pdf',
    'D'
);

exit;
