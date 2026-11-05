<?php
/* [FILE] public/invoice-print.php — Invoice printable view */

declare(strict_types=1);

$__bp = [
    __DIR__ . '/../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../config/bootstrap.php',
];
foreach ($__bp as $__b) {
    if (is_file($__b)) {
        require_once $__b;
        break;
    }
}
unset($__bp, $__b);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once ROOT_PATH . '/app/services/InvoiceService.php';

$db = db();

$h = static fn($v): string =>
htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$moneyFa = static function ($amount): string {
    $formatted = number_format((int)$amount);
    // تبدیل اعداد لاتین به فارسی
    $formatted = strtr($formatted, [
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
        ',' => '٬',  // جداکننده هزارگان فارسی
    ]);
    return $formatted . ' تومان';
};

/* ═══ تاریخ شمسی ═══ */
$dateTimeFa = static function ($value): string {
    if (!$value) return '—';
    $ts = strtotime((string)$value);
    if ($ts === false) return (string)$value;

    $gy = (int)date('Y', $ts);
    $gm = (int)date('n', $ts);
    $gd = (int)date('j', $ts);
    $g = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $d = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g[$gm - 1];
    $jy += 33 * intdiv($d, 12053);
    $d %= 12053;
    $jy += 4 * intdiv($d, 1461);
    $d %= 1461;
    if ($d > 365) {
        $jy += intdiv($d - 1, 365);
        $d = ($d - 1) % 365;
    }
    $jm = ($d < 186) ? 1 + intdiv($d, 31) : 7 + intdiv($d - 186, 30);
    $jd = 1 + (($d < 186) ? $d % 31 : ($d - 186) % 30);

    $out = $jy . '/' . str_pad((string)$jm, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string)$jd, 2, '0', STR_PAD_LEFT);
    return $out . ' - ' . date('H:i', $ts);
};

$dateOnlyFa = static function ($value) use ($dateTimeFa): string {
    $full = $dateTimeFa($value);
    return $full === '—' ? '—' : explode(' - ', $full)[0];
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending'   => 'در انتظار تأیید مالی',
        'verified'  => 'تأیید شده',
        'adjusted'  => 'اصلاح شده',
        'rejected'  => 'رد شده',
        'confirmed' => 'تأیید شده',
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

/* ═══ احراز هویت ═══ */
if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /');
    exit;
}

/* ═══ یافتن فاکتور ═══ */
$invoiceId     = (int)($_GET['id'] ?? 0);
$invoiceNumber = trim((string)($_GET['inv'] ?? ''));

$invoice = null;

try {
    if ($invoiceId > 0) {
        $invoice = InvoiceService::find($db, $invoiceId);
    } elseif ($invoiceNumber !== '') {
        $stmt = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ? LIMIT 1");
        $stmt->execute([$invoiceNumber]);
        if ($found = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invoice = InvoiceService::find($db, (int)$found['id']);
        }
    }
} catch (Throwable $e) {
    $invoice = null;
}

if (!$invoice) {
    http_response_code(404);
?>
    <!doctype html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="utf-8">
        <title>فاکتور پیدا نشد | رابطی</title>
    </head>

    <body class="invoice-page invoice-error-page" style="font-family:Tahoma;padding:40px;text-align:center;">
        <h1>فاکتور پیدا نشد</h1>
        <p>فاکتور موردنظر وجود ندارد.</p>
        <a href="/account-orders">بازگشت به سفارش‌ها</a>
    </body>

    </html>
<?php
    exit;
}

/* ═══ دسترسی ═══ */
$isAdmin = !empty($_SESSION['admin_id']);
$invoiceUserId = (int)($invoice['user_id'] ?? $invoice['customer_id'] ?? $invoice['order_user_id'] ?? 0);

if (!$isAdmin && $invoiceUserId !== $userId) {
    http_response_code(403);
    exit('دسترسی غیرمجاز.');
}

/* ═══ شناسه فاکتور (سازگار با id و invoice_id) ═══ */
$invoiceId = (int)($invoice['invoice_id'] ?? $invoice['id'] ?? 0);

/* ═══ تاریخچه پرداخت ═══ */
$paymentStmt = $db->prepare("
    SELECT
        ps.id, ps.method, ps.claimed_amount, ps.reference_no,
        ps.receipt_path, ps.transaction_date, ps.status, ps.created_at,
        v.verified_amount, v.status AS verification_status,
        v.note AS verification_note, v.verified_at
    FROM payment_submissions ps
    LEFT JOIN payment_verifications v ON v.payment_submission_id = ps.id
    WHERE ps.invoice_id = ?
    ORDER BY ps.created_at ASC, ps.id ASC
");
$paymentStmt->execute([$invoiceId]);
$payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

/* ═══ اسناد بازگشت وجه ═══ */
$refunds = [];
$stmt = $db->prepare("
    SELECT ft.amount, ft.method, ft.document_no, ft.reference_no, ft.completed_at
    FROM financial_transactions ft
    WHERE ft.invoice_id = ? AND ft.type = 'creditor_settlement' AND ft.status = 'completed'
    ORDER BY ft.completed_at ASC
");
$stmt->execute([$invoiceId]);
$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRefunded = 0;
foreach ($refunds as $r) $totalRefunded += (int)$r['amount'];

/* ═══ محاسبات ═══ */
$pendingAmount = 0;
$hasPendingPayment = false;

foreach ($payments as &$payment) {
    $claimed = max(0, (int)($payment['claimed_amount'] ?? 0));
    $payment['_claimed']  = $claimed;
    $payment['_verified'] = $payment['verified_amount'] !== null
        ? max(0, (int)$payment['verified_amount'])
        : null;

    if ((string)$payment['status'] === 'pending') {
        $pendingAmount += $claimed;
        $hasPendingPayment = true;
    }
}
unset($payment);

/* ═══ داده‌های نمایش ═══ */
$items  = $invoice['items'] ?? [];
$totals = $invoice['totals'] ?? [];

$invoiceNo   = (string)($invoice['invoice_number'] ?? $invoiceNumber ?? '—');
$orderNo     = (string)($invoice['order_number'] ?? '—');
$invoiceDate = (string)($invoice['order_created_at'] ?? $invoice['issued_at'] ?? '');

$invoiceStatus = (string)($invoice['status'] ?? 'unpaid');

// ⭐ خریدار = کاربر (نه گیرنده)

/* ⭐ خریدار (از users) — صاحب فاکتور */
$buyerName        = (string)($invoice['buyer_full_name'] ?? '—');
$buyerMobile      = (string)($invoice['buyer_mobile'] ?? '—');
$buyerNational    = (string)($invoice['buyer_national_code'] ?? '');
$buyerEconomic    = (string)($invoice['buyer_economic_code'] ?? '');
$buyerCompany     = (string)($invoice['buyer_company'] ?? '');
$buyerType        = (string)($invoice['buyer_type'] ?? 'real');  // real | legal

/* ⭐ گیرنده (از orders) — فقط برای ارسال */
$receiverName     = (string)($invoice['shipping_name'] ?? '');
$receiverMobile   = (string)($invoice['shipping_mobile'] ?? '');
$receiverProvince = (string)($invoice['shipping_province'] ?? '');
$receiverCity     = (string)($invoice['shipping_city'] ?? '');
$receiverAddress  = (string)($invoice['shipping_address'] ?? '');
$receiverPostal   = (string)($invoice['shipping_postal'] ?? '');
$customerNote     = (string)($invoice['customer_note'] ?? $invoice['note'] ?? '');

$subtotal = (int)($totals['subtotal'] ?? 0);
$discount = (int)($totals['discount'] ?? 0);       // ⭐ اضافه شد
$shipping = (int)($totals['shipping'] ?? 0);
$tax      = (int)($totals['tax'] ?? 0);
$total    = (int)($totals['total'] ?? 0);
$paid     = (int)($totals['paid'] ?? 0);
$remaining = $total - $paid;

$recType = 'تسویه';
$recAmount = 0;
if ($remaining > 0) {
    $recType = 'بدهکار';
    $recAmount = $remaining;
} elseif ($remaining < 0) {
    $recType = 'بستانکار';
    $recAmount = abs($remaining);
}

$canPay = !$hasPendingPayment
    && $remaining > 0
    && $recType !== 'بستانکار'
    && $invoiceStatus !== 'void';

$logoPath = '/Image/logo3.jpg';
$siteUrl  = 'https://JorabRabeti.ir';

$itemsCount = 0;
foreach ($items as $item) $itemsCount += (int)($item['qty'] ?? 0);

header('X-Robots-Tag: noindex, nofollow, noarchive');
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>فاکتور <?= $h($invoiceNo) ?> | رابطی</title>

    <style>
        /* ═══════════════════════════════════════════════════════════════
       Invoice Print — v2 (پاک‌سازی‌شده)
       ─────────────────────────────────────────────────────────────
       • حذف .buyer-line* (استفاده نمی‌شود — از .party-row-split استفاده می‌کنیم)
       • یکپارچه‌سازی @media print و @media (max-width: 640px)
       • حذف تکرار .receiver-line و .party-row-split
       • همه استایل‌های فعال در جای درست
    ═══════════════════════════════════════════════════════════════ */

        /* ─── Reset ─── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.7;
            color: #1f2937;
            background: #f2f3f5;
            padding: 20px;
            direction: rtl;
        }

        /* ─── Actions (دکمه‌های بالا) ─── */
        .invoice-actions {
            max-width: 900px;
            margin: 0 auto 15px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .actions-right,
        .actions-left {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #1f2937;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all .15s;
        }

        .action-btn:hover {
            background: #f9fafb;
        }

        .action-print,
        .action-pdf {
            background: #2a5f78;
            color: #fff;
            border-color: #2a5f78;
        }

        .action-print:hover,
        .action-pdf:hover {
            background: #1f4a5e;
        }

        /* ─── Sheet ─── */
        .invoice-sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .06);
        }

        /* ─── Header ─── */
        .invoice-header {
            padding-bottom: 20px;
            border-bottom: 2px solid #2a5f78;
            margin-bottom: 22px;
        }

        .invoice-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        .brand-text h1,
        .brand-name {
            font-size: 20px;
            font-weight: 900;
            color: #2a5f78;
            margin-bottom: 3px;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #6b7280;
        }

        .brand-site-url {
            font-size: 11px;
            color: #2a5f78;
            font-weight: 700;
            direction: ltr;
            text-align: right;
            margin-top: 3px;
        }

        .invoice-title {
            text-align: left;
        }

        .invoice-title-label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .invoice-title-number {
            font-size: 16px;
            font-weight: 800;
            color: #2a5f78;
            direction: ltr;
            text-align: left;
        }

        .invoice-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 18px;
        }

        .meta-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .meta-label {
            display: block;
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        .meta-value {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
        }

        .meta-value.ltr {
            direction: ltr;
            text-align: left;
        }

        /* ─── Parties (خریدار / فروشنده) ─── */
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .party-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 16px;
            background: #f9fafb;
        }

        .party-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #d1d5db;
        }

        .party-title {
            font-size: 12px;
            font-weight: 800;
            color: #2a5f78;
        }

        .party-badge {
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            background: #2a5f78;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .seller-badge {
            background: #6b7280;
        }

        /* ─── ردیف معمولی (موبایل، شرکت، ...) ─── */
        .party-row {
            display: flex;
            gap: 8px;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .party-row-label {
            color: #6b7280;
            min-width: 55px;
        }

        .party-row-value {
            color: #1f2937;
            font-weight: 600;
            flex: 1;
        }

        .party-row-value.ltr {
            direction: ltr;
        }

        .party-address {
            line-height: 1.6;
        }

        /* ─── ردیف نصف‌نصف (نام + کد ملی) ─── */
        .party-row-split {
            display: flex;
            gap: 12px;
            margin-bottom: 5px;
            font-size: 12px;
            width: 100%;
        }

        .party-row-half {
            flex: 1 1 50%;
            display: flex;
            gap: 6px;
            min-width: 0;
        }

        .party-row-half .party-row-label {
            color: #6b7280;
            min-width: 40px;
            flex-shrink: 0;
            font-size: 12px;
        }

        .party-row-half .party-row-value {
            color: #1f2937;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .party-row-half .party-row-value.ltr {
            direction: ltr;
        }

        /* ─── گیرنده (یک خط زیر دو ستون) ─── */
        .receiver-line {
            background: #f0f9ff;
            border: 1px dashed #7dd3fc;
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 22px;
            font-size: 12px;
            color: #075985;
            line-height: 1.8;
        }

        .receiver-line strong {
            color: #0369a1;
            margin-left: 4px;
        }

        /* ─── Section title ─── */
        .section-title {
            font-size: 14px;
            font-weight: 800;
            color: #2a5f78;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* ─── Products table ─── */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 22px;
        }

        .products-table th {
            background: #f8fafc;
            padding: 10px 8px;
            text-align: right;
            font-weight: 700;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .products-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .products-table td.center {
            text-align: center;
        }

        .products-table td.ltr {
            direction: ltr;
            text-align: left;
        }

        .item-thumb {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            display: block;
        }

        .item-thumb-empty {
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .product-name {
            font-weight: 700;
            color: #1f2937;
        }

        .product-code {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* ─── Payment history ─── */
        .payment-section {
            margin-bottom: 22px;
        }

        .payment-history {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .payment-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            background: #f9fafb;
        }

        .payment-item-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-method {
            font-weight: 700;
        }

        .payment-status {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 8px;
            background: #e5e7eb;
        }

        .payment-status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .payment-status-verified,
        .payment-status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .payment-status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .payment-item-amount {
            font-weight: 900;
            color: #2a5f78;
        }

        .payment-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px;
            margin-top: 8px;
            font-size: 11px;
            color: #6b7280;
        }

        .payment-meta-label {
            color: #9ca3af;
        }

        /* ─── Bottom (نتیجه مالی + خلاصه) ─── */
        .invoice-bottom {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            margin-top: 10px;
        }

        .note-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .note-box-title {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .reconciliation-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12px;
        }

        .reconciliation-title {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .reconciliation-result,
        .reconciliation-amount {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .reconciliation-result strong,
        .reconciliation-amount strong {
            font-weight: 900;
        }

        .items-summary {
            display: flex;
            justify-content: space-between;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12px;
            margin-top: 10px;
        }

        /* ─── Totals ─── */
        .totals-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px 20px;
            height: fit-content;
        }

        .totals-head {
            font-size: 13px;
            font-weight: 800;
            color: #2a5f78;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
        }

        .grand-total {
            border-top: 2px solid #2a5f78;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 15px;
            font-weight: 900;
            color: #2a5f78;
        }

        .paid-row {
            color: #059669;
        }

        .pending-row {
            color: #d97706;
        }

        .remaining-row,
        .refund-row {
            color: #dc2626;
        }

        /* ─── Footer ─── */
        .invoice-footer {
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }

        .invoice-footer .site-url {
            color: #2a5f78;
            font-weight: 700;
            direction: ltr;
            display: inline-block;
        }

        /* ═══════════════════════════════════════════════════════════════
       Responsive — موبایل (ساختار حفظ، اندازه‌ها کوچک‌تر)
    ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 640px) {
            body {
                padding: 10px;
                font-size: 12px;
            }

            .invoice-sheet {
                padding: 18px 14px;
                border-radius: 8px;
            }

            /* دو ستون خریدار/فروشنده → یک ستون */
            .parties {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .party-card {
                padding: 10px 12px;
            }

            .party-title {
                font-size: 11px;
            }

            .party-badge {
                font-size: 9px;
            }

            .party-row,
            .party-row-split {
                font-size: 11px;
                gap: 8px;
                margin-bottom: 4px;
            }

            .party-row-label {
                min-width: 45px;
                font-size: 10px;
            }

            .party-row-half .party-row-label {
                min-width: 35px;
                font-size: 10px;
            }

            .receiver-line {
                padding: 8px 12px;
                font-size: 11px;
                line-height: 1.7;
            }

            .products-table {
                font-size: 10px;
            }

            .products-table th,
            .products-table td {
                padding: 6px 4px;
            }

            .item-thumb,
            .item-thumb-empty {
                width: 48px;
                height: 48px;
                font-size: 18px;
            }

            .product-name {
                font-size: 11px;
            }

            .product-code {
                font-size: 9px;
            }

            .invoice-header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .invoice-title {
                text-align: right;
                width: 100%;
            }

            .invoice-meta {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .brand-name {
                font-size: 16px;
            }

            .brand-logo {
                width: 44px;
                height: 44px;
            }

            .invoice-bottom {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .totals-box {
                padding: 12px 14px;
            }

            .total-row {
                font-size: 11px;
                padding: 5px 0;
            }

            .grand-total {
                font-size: 13px;
            }

            .payment-item {
                padding: 10px 12px;
            }

            .payment-item-main {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .payment-item-amount {
                font-size: 13px;
            }

            .payment-item-meta {
                font-size: 10px;
                gap: 6px 12px;
            }

            .invoice-actions {
                flex-direction: column;
                gap: 8px;
            }

            .actions-right,
            .actions-left {
                width: 100%;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }

            .invoice-footer {
                font-size: 9px;
                line-height: 1.7;
            }
        }

        /* ═══════════════════════════════════════════════════════════════
       Print — A4
    ═══════════════════════════════════════════════════════════════ */
        @media print {
            body {
                background: #fff;
                padding: 0;
                font-size: 11px;
            }

            .invoice-actions {
                display: none !important;
            }

            .invoice-sheet {
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
                padding: 15px 20px;
            }

            .invoice-header {
                padding-bottom: 15px;
                margin-bottom: 18px;
            }

            .brand-text h1,
            .brand-name {
                font-size: 18px;
            }

            .invoice-title-number {
                font-size: 14px;
            }

            .products-table th,
            .products-table td {
                padding: 6px 5px;
                font-size: 10px;
            }

            .item-thumb,
            .item-thumb-empty {
                width: 75px;
                height: 75px;
            }

            .totals-box {
                padding: 12px 16px;
            }

            .total-row {
                font-size: 11px;
                padding: 4px 0;
            }

            .grand-total {
                font-size: 13px;
            }

            .invoice-footer {
                margin-top: 20px;
                font-size: 9px;
            }

            @page {
                size: A4;
                margin: 10mm;
            }

            tr,
            .party-card,
            .totals-box,
            .payment-item {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body class="invoice-page">

    <div class="invoice-actions">
        <div class="actions-right">
            <button type="button" class="action-btn action-print" onclick="window.print()">🖨 چاپ</button>
            <a href="/invoice-pdf.php?inv=<?= rawurlencode($invoiceNo) ?>" class="action-btn action-pdf">📄 دریافت PDF</a>
        </div>
        <div class="actions-left">
            <a href="/account-orders" class="action-btn action-back">← بازگشت</a>
        </div>
    </div>

    <main class="invoice-sheet">

        <!-- Header -->
        <header class="invoice-header">
            <div class="invoice-header-top">
                <div class="brand-block">
                    <img src="<?= $h($logoPath) ?>" alt="رابطی" class="brand-logo">
                    <div class="brand-text">
                        <div class="brand-name">رابطی</div>
                        <div class="brand-subtitle">کارگاه تولیدی جوراب رابطی</div>
                        <div class="brand-site-url"><?= $h($siteUrl) ?></div>
                    </div>
                </div>
                <div class="invoice-title">
                    <div class="invoice-title-label">فاکتور فروش</div>
                    <div class="invoice-title-number"><?= $h($invoiceNo) ?></div>
                </div>
            </div>

            <div class="invoice-meta">
                <div class="meta-card">
                    <span class="meta-label">شماره فاکتور</span>
                    <strong class="meta-value ltr"><?= $h($invoiceNo) ?></strong>
                </div>
                <div class="meta-card">
                    <span class="meta-label">شماره سفارش</span>
                    <strong class="meta-value ltr"><?= $h($orderNo) ?></strong>
                </div>
                <div class="meta-card">
                    <span class="meta-label">تاریخ صدور</span>
                    <strong class="meta-value"><?= $h($dateOnlyFa($invoiceDate)) ?></strong>
                </div>
            </div>
        </header>

        <div class="invoice-content">

            <!-- Parties -->
            <section class="parties">
                <!-- ═══ خریدار ═══ -->
                <div class="party-card">
                    <div class="party-head">
                        <div class="party-title">خریدار</div>
                    </div>

                    <!-- ⭐ خط ۱: نام (نصف چپ) + کد ملی (نصف راست) -->
                    <div class="party-row-split">
                        <div class="party-row-half">
                            <span class="party-row-label">نام</span>
                            <strong class="party-row-value"><?= $h($buyerName) ?></strong>
                        </div>
                        <div class="party-row-half">
                            <span class="party-row-label">کد ملی</span>
                            <strong class="party-row-value ltr"><?= $buyerNational !== '' ? $h($buyerNational) : '—' ?></strong>
                        </div>
                    </div>

                    <!-- ⭐ خط ۲: موبایل -->
                    <div class="party-row">
                        <span class="party-row-label">موبایل</span>
                        <strong class="party-row-value ltr"><?= $h($buyerMobile) ?></strong>
                    </div>


                    <!-- ⭐ خط ۳: شرکت (اگر هست) -->
                    <?php if ($buyerCompany !== ''): ?>
                        <div class="party-row">
                            <span class="party-row-label">شرکت</span>
                            <strong class="party-row-value"><?= $h($buyerCompany) ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- ⭐ خط ۴: کد اقتصادی (اگر هست) -->
                    <?php if ($buyerEconomic !== ''): ?>
                        <div class="party-row">
                            <span class="party-row-label">کد اقتصادی</span>
                            <strong class="party-row-value ltr"><?= $h($buyerEconomic) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ فروشنده ═══ -->
                <div class="party-card seller-card">
                    <div class="party-head">
                        <div class="party-title">فروشنده</div>
                    </div>
                    <div class="party-row">
                        <span class="party-row-label">نام</span>
                        <strong class="party-row-value">کارگاه تولید جوراب رابطی</strong>
                    </div>
                    <div class="party-row">
                        <span class="party-row-label">آدرس</span>
                        <strong class="party-row-value">بازار بزرگ تهران — عباس‌آباد، سرای لطفی‌پور، پلاک ۲۲</strong>
                    </div>
                    <div class="party-row">
                        <span class="party-row-label">وب‌سایت</span>
                        <strong class="party-row-value ltr"><?= $h($siteUrl) ?></strong>
                    </div>
                </div>
            </section>

            <!-- ═══ گیرنده — یک خط زیر دو ستون ═══ -->
            <?php if ($receiverName !== '' || $receiverAddress !== ''): ?>
                <?php
                $receiverParts = [];
                if ($receiverName !== '')    $receiverParts[] = $receiverName;
                if ($receiverMobile !== '')  $receiverParts[] = $receiverMobile;
                if ($receiverProvince !== '' || $receiverCity !== '') {
                    $receiverParts[] = trim($receiverProvince . ($receiverCity !== '' ? '، ' . $receiverCity : ''));
                }
                if ($receiverAddress !== '') $receiverParts[] = $receiverAddress;
                if ($receiverPostal !== '')  $receiverParts[] = 'کد پستی: ' . $receiverPostal;
                ?>
                <div class="receiver-line">
                    <strong>📦 گیرنده:</strong>
                    <?= $h(implode(' — ', $receiverParts)) ?>
                </div>
            <?php endif; ?>

            <!-- Items -->
            <section class="products-section">
                <div class="section-title">اقلام سفارش</div>

                <?php if (!empty($items)): ?>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">تصویر</th>
                                <th>محصول</th>
                                <th style="width: 70px;">تعداد</th>
                                <th style="width: 110px;">قیمت واحد</th>
                                <th style="width: 120px;">مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item):
                                $productName = (string)($item['product_name'] ?? $item['name'] ?? 'محصول');
                                $productCode = (string)($item['product_code'] ?? $item['code'] ?? '');
                                $qty         = (int)($item['qty'] ?? 0);
                                $unitPrice   = (int)($item['unit_price'] ?? $item['base_unit_price'] ?? 0);
                                $lineTotal   = (int)($item['line_total'] ?? ($unitPrice * $qty));
                                $itemImage   = (string)($item['image'] ?? '');
                            ?>
                                <tr>
                                    <td class="center">
                                        <?php if ($itemImage !== ''): ?>
                                            <img src="<?= $h($itemImage) ?>"
                                                alt="<?= $h($productName) ?>"
                                                class="item-thumb">
                                        <?php else: ?>
                                            <div class="item-thumb-empty">🧦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="product-name"><?= $h($productName) ?></div>
                                        <?php if ($productCode !== ''): ?>
                                            <div class="product-code">کد: <?= $h($productCode) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="center"><?= number_format($qty) ?> <?= htmlspecialchars(unit_label($item['unit_code'] ?? 'جفتی')) ?></td>
                                    <td class="ltr"><?= $moneyFa($unitPrice) ?></td>
                                    <td class="ltr"><strong><?= $moneyFa($lineTotal) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#9ca3af;text-align:center;padding:20px;">موردی برای نمایش وجود ندارد.</p>
                <?php endif; ?>
            </section>

            <!-- Payments -->
            <section class="payment-section">
                <div class="section-title">تاریخچه پرداخت</div>

                <?php if (!empty($payments)): ?>
                    <div class="payment-history">

                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $paymentStatus   = (string)($payment['status'] ?? '');
                            $paymentMethod   = (string)($payment['method'] ?? '');
                            $claimed         = (int)$payment['_claimed'];
                            $verified        = $payment['_verified'];  // null اگر pending
                            $hasMismatch     = ($verified !== null && $verified !== $claimed);
                            $displayAmount   = $verified ?? $claimed;  // اگر verified هست، آن را؛ وگرنه claimed
                            ?>
                            <article class="payment-item">
                                <div class="payment-item-main">
                                    <div>
                                        <span class="payment-method"><?= $h($methodLabel($paymentMethod)) ?></span>
                                        <span class="payment-status payment-status-<?= $h($paymentStatus) ?>">
                                            <?= $h($statusLabel($paymentStatus)) ?>
                                        </span>
                                    </div>
                                    <div class="payment-item-amount">
                                        <?= $moneyFa($displayAmount) ?>
                                        <?php if ($verified === null): ?>
                                            <small style="display:block;font-size:10px;color:#92400e;font-weight:400;">
                                                (ادعا — در انتظار تأیید)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($hasMismatch): ?>
                                    <div style="margin-top:8px;padding:6px 10px;background:#fef3c7;border-radius:6px;font-size:11px;color:#92400e;">
                                        ⚠️ اظهارات مشتری: <strong><?= $moneyFa($claimed) ?></strong>
                                        — مغایرت: <strong><?= $moneyFa(abs($claimed - $verified)) ?></strong>
                                        (تأیید مالی: <?= $moneyFa($verified) ?>)
                                    </div>
                                <?php endif; ?>

                                <div class="payment-item-meta">
                                    <div>
                                        <span class="payment-meta-label">ثبت:</span>
                                        <?= $h($dateTimeFa($payment['created_at'] ?? null)) ?>
                                    </div>
                                    <?php if (trim((string)($payment['reference_no'] ?? '')) !== ''): ?>
                                        <div>
                                            <span class="payment-meta-label">پیگیری:</span>
                                            <span class="ltr"><?= $h($payment['reference_no']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($verified !== null && !empty($payment['verified_at'])): ?>
                                        <div>
                                            <span class="payment-meta-label">تأیید مالی:</span>
                                            <?= $h($dateTimeFa($payment['verified_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="payment-item" style="text-align:center;color:#9ca3af;">
                        هنوز پرداختی برای این فاکتور ثبت نشده است.
                    </div>
                <?php endif; ?>
            </section>

            <!-- Bottom: Reconciliation + Totals -->
            <section class="invoice-bottom">
                <div class="bottom-left">
                    <?php if ($customerNote !== ''): ?>
                        <div class="note-box">
                            <div class="note-box-title">توضیحات مشتری</div>
                            <p><?= nl2br($h($customerNote)) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="reconciliation-box">
                        <div class="reconciliation-title">نتیجه مالی</div>
                        <div class="reconciliation-result">
                            <span>وضعیت:</span>
                            <strong><?= $h($recType) ?></strong>
                        </div>
                        <?php if ($recAmount > 0): ?>
                            <div class="reconciliation-amount">
                                <span>مبلغ:</span>
                                <strong><?= $moneyFa($recAmount) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($itemsCount > 0): ?>
                        <div class="items-summary">
                            <span>تعداد کل اقلام:</span>
                            <strong><?= number_format($itemsCount) ?> <?= htmlspecialchars(unit_label($items[0]['unit_code'] ?? 'جفتی')) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="totals-box">
                    <div class="totals-head">خلاصه مالی</div>

                    <!-- ═══ اقلام و تخفیف ═══ -->
                    <div class="total-row">
                        <span>جمع اقلام:</span>
                        <strong><?= $moneyFa($subtotal) ?></strong>
                    </div>

                    <div class="total-row">
                        <span>تخفیف:</span>
                        <strong style="<?= $discount > 0 ? 'color:#059669;' : '' ?>">
                            <?= $discount > 0 ? '- ' . $moneyFa($discount) : '۰ تومان' ?>
                        </strong>
                    </div>

                    <div class="total-row">
                        <span>هزینه ارسال:</span>
                        <strong><?= $shipping > 0 ? $moneyFa($shipping) : '۰ تومان' ?></strong>
                    </div>

                    <?php if ($tax > 0): ?>
                        <div class="total-row">
                            <span>مالیات:</span>
                            <strong><?= $moneyFa($tax) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="total-row grand-total">
                        <span>مبلغ نهایی:</span>
                        <span><?= $moneyFa($total) ?></span>
                    </div>

                    <!-- ═══ جمع پرداخت‌ها با تفکیک ═══ -->
                    <?php
                    $sumVerified = 0;
                    $sumPending  = 0;
                    $sumRejected = 0;

                    foreach ($payments as $p) {
                        $st = (string)($p['status'] ?? '');
                        if ($st === 'verified' || $st === 'adjusted' || $st === 'confirmed') {
                            $sumVerified += (int)($p['_verified'] ?? 0);
                        } elseif ($st === 'pending') {
                            $sumPending += (int)($p['_claimed'] ?? 0);
                        } elseif ($st === 'rejected') {
                            $sumRejected += (int)($p['_claimed'] ?? 0);
                        }
                    }
                    ?>

                    <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #d1d5db;">
                        <div style="font-size:12px;font-weight:800;color:#2a5f78;margin-bottom:8px;">
                            جمع پرداخت‌ها
                        </div>

                        <div class="total-row" style="color:#059669;">
                            <span>✓ تأییدشده:</span>
                            <strong><?= $moneyFa($sumVerified) ?></strong>
                        </div>

                        <?php if ($sumPending > 0): ?>
                            <div class="total-row" style="color:#d97706;">
                                <span>⏳ در انتظار تأیید:</span>
                                <strong><?= $moneyFa($sumPending) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($sumRejected > 0): ?>
                            <div class="total-row" style="color:#dc2626;">
                                <span>✗ ردشده:</span>
                                <strong><?= $moneyFa($sumRejected) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalRefunded > 0): ?>
                        <div class="total-row refund-row" style="margin-top:8px;">
                            <span>بازگشت وجه:</span>
                            <strong>- <?= $moneyFa($totalRefunded) ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- ═══ مانده (بر اساس verified) ═══ -->
                    <?php
                    $effectivePaid = $sumVerified;
                    $remainingCalc = $total - $effectivePaid;

                    if ($remainingCalc > 0) {
                        $recTypeCalc   = 'بدهکار';
                        $recAmountCalc = $remainingCalc;
                        $recColor      = '#dc2626';
                    } elseif ($remainingCalc < 0) {
                        $recTypeCalc   = 'بستانکار';
                        $recAmountCalc = abs($remainingCalc);
                        $recColor      = '#1d4ed8';
                    } else {
                        $recTypeCalc   = 'تسویه';
                        $recAmountCalc = 0;
                        $recColor      = '#059669';
                    }
                    ?>

                    <div class="total-row" style="
        border-top:2px solid #2a5f78;
        margin-top:10px;
        padding-top:10px;
        font-weight:900;
        font-size:14px;
        color:<?= $recColor ?>;
    ">
                        <span><?= $recTypeCalc === 'بستانکار' ? 'بستانکاری:' : ($recTypeCalc === 'بدهکار' ? 'مانده بدهی:' : 'مانده:') ?></span>
                        <strong><?= $moneyFa($recAmountCalc) ?></strong>
                    </div>
                </div>
            </section>

        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            تعداد کل: <?= number_format($itemsCount) ?> <?= htmlspecialchars(unit_label($items[0]['unit_code'] ?? 'جفتی')) ?> |
            تولیدی جوراب رابطی |
            <span class="site-url"><?= $h($siteUrl) ?></span>
        </div>

    </main>

</body>

</html>