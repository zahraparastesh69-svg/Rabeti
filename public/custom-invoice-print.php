<?php
/* ════ RABETI-CUSTOM-ORDER | نسخه: v1 (سازگار با v5) | 2026-10-30 | جدیدترین نسخه (LATEST) ════ */

/**
 * custom-invoice-print.php — چاپ پیش‌فاکتور و فاکتور نهایی سفارش اختصاصی
 * URL:
 *   /custom-invoice-print.php?id=42&type=quotation   → پیش‌فاکتور
 *   /custom-invoice-print.php?id=42&type=final       → فاکتور نهایی
 *
 * دسترسی (یکی از سه حالت):
 *   ۱) ادمین
 *   ۲) کاربر لاگینِ مالک سفارش
 *   ۳) مهمان با کد پیگیری + موبایل صحیح (?code=CR-...&mobile=09...)
 *
 * سازگاری با v5 — نگاشت فیلدها:
 *   quotation_sent_at       → proforma_issued_at
 *   final_invoice_issued_at → final_invoice_at
 *   quotation_amount        → total_amount
 *   final_amount            → final_invoice_amount
 *   quotation_base_price    → total_amount
 *   logo_position / logo_size / packaging_type / *_discount → حذف (در v5 جمع نمی‌شوند)
 *   delivery_days           → proforma_validity_days (تنظیمات)
 *
 * تنظیمات مرتبط (custom_order_settings):
 *   invoice_shop_name       (پیش‌فرض: جوراب رابطی)
 *   invoice_logo_path       (پیش‌فرض: /Image/logo3.jpg)
 *   proforma_validity_days  (پیش‌فرض: 7)
 */

declare(strict_types=1);

/* Auto-detect bootstrap (local + host) */
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
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once __DIR__ . '/custom-order-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ───────── ورودی‌ها ───────── */
$id   = (int)($_GET['id'] ?? 0);
$type = ((string)($_GET['type'] ?? 'quotation')) === 'final' ? 'final' : 'quotation';
$code   = strtoupper(co_en_digits(trim((string)($_GET['code'] ?? ''))));
$mobile = co_en_digits(trim((string)($_GET['mobile'] ?? '')));

if ($id <= 0) {
    http_response_code(400);
    exit('شناسه سفارش نامعتبر است');
}

$order = co_load_order_full($id);
if ($order === null) {
    http_response_code(404);
    exit('سفارش یافت نشد');
}

/* ───────── گارد دسترسی: ادمین / مالک / مهمان با کد+موبایل ───────── */
$coAdmin = false;
if (function_exists('is_admin')) {
    try { $coAdmin = (bool)is_admin(); } catch (Throwable $e) { $coAdmin = false; }
} elseif (function_exists('current_user_is_admin')) {
    try { $coAdmin = (bool)current_user_is_admin(); } catch (Throwable $e) { $coAdmin = false; }
}
if (!$coAdmin) {
    $role = strtolower((string)($_SESSION['user_role'] ?? $_SESSION['role'] ?? ''));
    $coAdmin = in_array($role, ['admin', 'superadmin', '1', 'manager'], true)
        || !empty($_SESSION['admin_id'])
        || !empty($_SESSION['is_admin'])
        || !empty($_SESSION['admin_logged_in']);
}

$uid      = co_current_user_id();
$isOwner  = ($uid !== null && (int)($order['user_id'] ?? 0) === $uid);
$isGuestOk = ($order['user_id'] === null
    && $code !== ''
    && hash_equals(strtoupper((string)$order['tracking_code']), $code)
    && $mobile !== ''
    && hash_equals((string)$order['mobile'], $mobile));

if (!$coAdmin && !$isOwner && !$isGuestOk) {
    http_response_code(403);
    exit('دسترسی به این سند مجاز نیست');
}

/* ═══════════ نگاشت فیلدهای v5 ═══════════ */
$isFinal = $type === 'final';

/* وجود مدرک */
if ($isFinal && empty($order['final_invoice_at'])) {
    http_response_code(400);
    exit('فاکتور نهایی هنوز صادر نشده است');
}
if (!$isFinal && empty($order['proforma_issued_at'])) {
    http_response_code(400);
    exit('پیش‌فاکتور هنوز صادر نشده است');
}

$invoiceNo   = $isFinal ? ($order['final_invoice_number'] ?? '') : ($order['proforma_number'] ?? '');
$issueDate   = $isFinal ? ($order['final_invoice_at'] ?? null) : ($order['proforma_issued_at'] ?? null);
$total       = $isFinal
    ? (float)($order['final_invoice_amount'] ?? 0)
    : (float)($order['total_amount'] ?? 0);
if ($total <= 0) {
    $total = (float)($order['total_amount'] ?? 0);
}
$downAmount  = (float)($order['down_payment_amount'] ?? 0);
$downPercent = (float)($order['down_payment_percent'] ?? co_default_down_payment_percent());
$setAmount   = (float)($order['settlement_amount'] ?? 0);
if ($setAmount <= 0 && $total > 0) {
    $setAmount = co_compute_settlement($total, $downAmount);
}

/* تنظیمات سند */
$shopName    = (string)co_setting('invoice_shop_name', 'جوراب رابطی');
$logoPath    = (string)co_setting('invoice_logo_path', '/Image/logo3.jpg');
$validityDays = (int)co_setting('proforma_validity_days', 7);
$validUntil  = (!$isFinal && $issueDate)
    ? date('Y/m/d', strtotime((string)$issueDate) + $validityDays * 86400)
    : null;

$items    = $order['items'] ?? [];
$totalQty = array_sum(array_map(static fn($it) => (int)$it['qty'], $items));

$docTitle = $isFinal ? 'فاکتور فروش (نهایی)' : 'پیش‌فاکتور تولید اختصاصی';
$docColor = $isFinal ? '#065f46' : '#9a3412';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h($docTitle) ?> — <?= $h((string)$invoiceNo) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Tahoma, "Vazirmatn", Arial, sans-serif;
        background: #eef1f5;
        color: #1f2937;
        font-size: 13px;
        line-height: 1.9;
        padding: 24px 12px;
    }
    .sheet {
        max-width: 820px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 28px 32px;
        box-shadow: 0 6px 24px rgba(15, 23, 42, .08);
    }
    .doc-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        border-bottom: 3px solid <?= $docColor ?>;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .doc-head .brand { display: flex; align-items: center; gap: 12px; }
    .doc-head .brand img { max-height: 64px; max-width: 180px; object-fit: contain; }
    .doc-head .brand .name { font-size: 17px; font-weight: 800; color: #111827; }
    .doc-head .brand .sub { font-size: 11px; color: #6b7280; }
    .doc-title { text-align: left; }
    .doc-title .t {
        display: inline-block;
        background: <?= $docColor ?>;
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        padding: 6px 18px;
        border-radius: 8px;
    }
    .doc-title .no { font-size: 12px; color: #374151; margin-top: 8px; direction: ltr; text-align: left; }
    .doc-title .dt { font-size: 12px; color: #6b7280; }

    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 24px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }
    .meta div { min-width: 200px; font-size: 12px; }
    .meta b { color: #111827; }
    .meta .ltr { direction: ltr; unicode-bidi: embed; display: inline-block; }

    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    table.items th, table.items td {
        border: 1px solid #e5e7eb;
        padding: 8px 10px;
        text-align: center;
        font-size: 12px;
    }
    table.items th { background: #f3f4f6; font-weight: 700; color: #374151; }
    table.items td.num { direction: ltr; font-feature-settings: "tnum"; }
    table.items tr.total-row td { background: #f8fafc; font-weight: 800; }

    .totals {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .totals .box {
        flex: 1 1 180px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 14px;
        background: #f8fafc;
    }
    .totals .box.hl { background: #fff7ed; border-color: #fdba74; }
    .totals .box.fin { background: #f0fdf4; border-color: #86efac; }
    .totals .lbl { font-size: 11px; color: #6b7280; }
    .totals .val { font-size: 15px; font-weight: 800; color: #111827; }
    .totals .val .unit { font-size: 11px; font-weight: 400; color: #6b7280; }

    .notes {
        border: 1px dashed #d1d5db;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 11.5px;
        color: #4b5563;
        margin-bottom: 16px;
    }
    .notes b { color: #111827; }
    .validity {
        display: inline-block;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        color: #92400e;
        border-radius: 8px;
        padding: 4px 12px;
        font-size: 11.5px;
        font-weight: 700;
        margin-bottom: 16px;
    }
    .foot {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        border-top: 1px solid #e5e7eb;
        padding-top: 14px;
        font-size: 11px;
        color: #6b7280;
    }
    .sign { text-align: center; min-width: 160px; }
    .sign .line { margin-top: 46px; border-top: 1px dashed #9ca3af; padding-top: 6px; font-size: 11px; }

    .printbar { text-align: center; margin-top: 18px; }
    .printbar button {
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        background: <?= $docColor ?>;
        color: #fff;
        border: 0;
        border-radius: 8px;
        padding: 11px 34px;
        cursor: pointer;
    }
    .printbar button:hover { filter: brightness(.92); }

    @media print {
        body { background: #fff; padding: 0; }
        .sheet { border: 0; box-shadow: none; border-radius: 0; max-width: none; padding: 10mm 12mm; }
        .printbar { display: none; }
        @page { size: A4; margin: 8mm; }
    }
</style>
</head>
<body>

<div class="sheet">

    <div class="doc-head">
        <div class="brand">
            <?php if ($logoPath !== ''): ?>
                <img src="<?= $h($logoPath) ?>" alt="<?= $h($shopName) ?>">
            <?php endif; ?>
            <div>
                <div class="name"><?= $h($shopName) ?></div>
                <div class="sub">تولید جوراب با برند شما — سفارشات اختصاصی</div>
            </div>
        </div>
        <div class="doc-title">
            <span class="t"><?= $h($docTitle) ?></span>
            <div class="no">شماره سند: <b><?= $h((string)$invoiceNo) ?></b></div>
            <div class="dt">تاریخ صدور: <?= $h(co_fa_date($issueDate ? (string)$issueDate : null)) ?></div>
        </div>
    </div>

    <div class="meta">
        <div><b>خریدار:</b> <?= $h($order['full_name'] ?? '—') ?></div>
        <div><b>موبایل:</b> <span class="ltr"><?= $h($order['mobile'] ?? '—') ?></span></div>
        <div><b>برند:</b> <?= $h($order['brand_name'] ?: '—') ?></div>
        <div><b>کد پیگیری سفارش:</b> <span class="ltr"><?= $h($order['tracking_code'] ?? '—') ?></span></div>
        <?php if (!empty($order['ref_product_name'])): ?>
            <div><b>محصول مبدأ:</b> <?= $h($order['ref_product_name']) ?> (کد <span class="ltr"><?= $h($order['ref_product_code'] ?? '—') ?></span>)</div>
        <?php endif; ?>
    </div>

    <?php if (!$isFinal && $validUntil !== null): ?>
        <div class="validity">⏳ اعتبار این پیش‌فاکتور: <?= $validityDays ?> روز از تاریخ صدور — معتبر تا <?= $h($validUntil) ?></div>
    <?php endif; ?>

    <table class="items">
        <thead>
            <tr>
                <th style="width:44px">#</th>
                <th>نوع جوراب</th>
                <th>رنگ / طرح</th>
                <th style="width:110px">تعداد (جفت)</th>
                <th>توضیح</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td class="num"><?= (int)$i + 1 ?></td>
                    <td><?= $h((string)$it['product_type']) ?></td>
                    <td><?= $h((string)($it['color'] ?: '—')) ?></td>
                    <td class="num"><?= number_format((int)$it['qty']) ?></td>
                    <td><?= $h((string)($it['notes'] ?: '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3">جمع کل</td>
                <td class="num"><?= number_format($totalQty) ?></td>
                <td>جفت</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <div class="box <?= $isFinal ? 'fin' : 'hl' ?>">
            <div class="lbl"><?= $isFinal ? 'مبلغ فاکتور نهایی' : 'مبلغ کل کار' ?></div>
            <div class="val"><?= number_format($total) ?> <span class="unit">تومان</span></div>
        </div>
        <div class="box">
            <div class="lbl">پیش‌پرداخت (<?= $downPercent > 0 ? $h((string)$downPercent) : '۳۰' ?>٪)</div>
            <div class="val"><?= number_format($downAmount) ?> <span class="unit">تومان</span></div>
        </div>
        <?php if ($isFinal): ?>
            <div class="box">
                <div class="lbl">تسویه نهایی (مابه‌التفاوت)</div>
                <div class="val"><?= number_format($setAmount) ?> <span class="unit">تومان</span></div>
            </div>
            <div class="box fin">
                <div class="lbl">جمع تسویه‌شده (پیش‌پرداخت + تسویه)</div>
                <div class="val"><?= number_format($downAmount + $setAmount) ?> <span class="unit">تومان</span></div>
            </div>
        <?php else: ?>
            <div class="box">
                <div class="lbl">مابه‌التفاوت (تسویه پس از تایید نمونه)</div>
                <div class="val"><?= number_format($setAmount) ?> <span class="unit">تومان</span></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="notes">
        <?php if (!$isFinal): ?>
            <b>شرایط پیش‌فاکتور:</b>
            پس از پرداخت پیش‌پرداخت و تایید آن، نمونه‌ی کار تولید و ارسال می‌شود؛
            پس از تایید نمونه توسط شما و تسویه‌ی مابه‌التفاوت، تولید انبوه آغاز می‌گردد.
            قیمت‌ها بر اساس مشخصات اعلام‌شده در همین سند است و هرگونه تغییر در جنس نخ،
            رنگ، بسته‌بندی یا لیبل، مبلغ را بازنگری می‌کند.
            <?php if (!empty($order['admin_note'])): ?>
                <br><b>توضیحات فروشنده:</b> <?= nl2br($h((string)$order['admin_note'])) ?>
            <?php endif; ?>
        <?php else: ?>
            <b>فاکتور فروش:</b>
            این سند گواه تحویل و تسویه‌ی کامل سفارش اختصاصی با کد پیگیری
            <span dir="ltr"><?= $h($order['tracking_code'] ?? '—') ?></span> است.
            <?php if (!empty($order['shipping_carrier']) || !empty($order['shipping_tracking_code'])): ?>
                <br><b>ارسال:</b> <?= $h((string)($order['shipping_carrier'] ?? '')) ?>
                — کد رهگیری <span dir="ltr"><?= $h((string)($order['shipping_tracking_code'] ?? '')) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="foot">
        <div>
            این سند توسط سیستم سفارشات اختصاصی <?= $h($shopName) ?> تولید شده است.<br>
            تاریخ چاپ: <?= $h(date('Y/m/d H:i')) ?>
        </div>
        <div class="sign">
            مهر و امضای فروشنده
            <div class="line"></div>
        </div>
    </div>

</div>

<div class="printbar">
    <button type="button" onclick="window.print()">🖨 چاپ / ذخیره PDF</button>
</div>

</body>
</html>
