<?php
/* [FILE] public/account-orders.php — Customer my-orders */

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$jalali = static function (?string $mysqlDate, bool $withTime = false): string {
    if (!$mysqlDate) return '—';
    $ts = strtotime($mysqlDate);
    if ($ts === false) return '—';
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
    return $withTime ? $out . ' — ' . date('H:i', $ts) : $out;
};

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$db = db();

$statusLabels = [
    'pending_review' => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'packed' => 'بسته‌بندی شد',
    'shipped' => 'ارسال شد',
    'delivered' => 'تحویل شد',
    'cancelled' => 'لغو شده',
];

$stmt = $db->prepare("
    SELECT o.*, i.invoice_number, i.status AS pay_status
    FROM orders o
    LEFT JOIN invoices i ON i.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.id DESC LIMIT 50
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

/* ═══ برای هر سفارش: تأییدشده / در انتظار / مانده (بستانکار هم محاسبه می‌شود) ═══ */
$orderFinancials = [];
foreach ($orders as $o) {
    if (!$o['invoice_number']) {
        $orderFinancials[(int)$o['id']] = [
            'paid' => 0,
            'pending' => 0,
            'remaining' => (int)$o['total'],
            'has_pending' => false,
        ];
        continue;
    }
    $stmt = $db->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN ps.status='verified' THEN COALESCE(v.verified_amount,0) ELSE 0 END),0) AS paid,
          COALESCE(SUM(CASE WHEN ps.status='pending' THEN ps.claimed_amount ELSE 0 END),0) AS pending
        FROM payment_submissions ps
        LEFT JOIN payment_verifications v ON v.payment_submission_id = ps.id
        WHERE ps.invoice_id = (SELECT id FROM invoices WHERE invoice_number = ?)
    ");
    $stmt->execute([$o['invoice_number']]);
    $fin = $stmt->fetch();

    $paid    = (int)($fin['paid'] ?? 0);
    $pending = (int)($fin['pending'] ?? 0);

    /* ⭐ بدون max(0) — عدد منفی یعنی بستانکار (مازاد پرداخت) */
    $orderFinancials[(int)$o['id']] = [
        'paid'        => $paid,
        'pending'     => $pending,
        'remaining'   => (int)$o['total'] - $paid,
        'has_pending' => $pending > 0,
    ];
}

$pageTitle = 'سفارش‌های من | رابطی';
ob_start();
?>

<nav class="breadcrumb"><a href="/">خانه</a> › <span>سفارش‌های من</span></nav>

<section class="catalog-head">
    <h1 class="catalog-title">سفارش‌های من</h1>
    <p class="catalog-sub"><?= count($orders) ?> سفارش — مانده‌ها بر اساس تأیید واحد مالی محاسبه می‌شود</p>
</section>

<?php if ($orders): ?>
    <div class="my-orders">

        <?php foreach ($orders as $o):
            $fin = $orderFinancials[(int)$o['id']];
            $remaining  = $fin['remaining'];
            $hasPending = $fin['has_pending'];
            $isCreditor = ($remaining < 0);
        ?>
            <article class="my-order-card">

                <div class="mo-head">
                    <div>
                        <b dir="ltr"><?= $h($o['order_number']) ?></b>
                        <small><?= $jalali($o['created_at'], true) ?></small>
                    </div>
                    <span class="st-badge <?= [
                                                'pending_review' => 'st-new',
                                                'processing' => 'st-read',
                                                'packed' => 'st-quoted',
                                                'shipped' => 'st-quoted',
                                                'delivered' => 'st-converted',
                                                'cancelled' => 'st-rejected',
                                            ][$o['status']] ?>"><?= $statusLabels[$o['status']] ?></span>
                </div>

                <div class="mo-body">
                    <div class="mo-row">
                        <span>مبلغ فاکتور</span>
                        <b><?= number_format((float)$o['total']) ?> تومان</b>
                    </div>
                    <div class="mo-row">
                        <span>پرداخت تأییدشده</span>
                        <b><?= number_format($fin['paid']) ?> تومان</b>
                    </div>

                    <?php if ($hasPending): ?>
                        <div class="mo-row">
                            <span>در انتظار بررسی مالی</span>
                            <b style="color:var(--cta-d);"><?= number_format($fin['pending']) ?> تومان</b>
                        </div>
                    <?php endif; ?>

                    <div class="mo-row">
                        <span>مانده بدهی</span>
                        <?php if ($remaining > 0): ?>
                            <b class="stock out"><?= number_format($remaining) ?> تومان</b>
                        <?php elseif ($isCreditor): ?>
                            <b class="stock in">بستانکار: <?= number_format(abs($remaining)) ?> تومان</b>
                        <?php else: ?>
                            <b class="stock in">تسویه کامل ✓</b>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mo-actions">
                    <?php if ($o['status'] !== 'cancelled' && $remaining > 0 && !$hasPending): ?>
                        <a href="/payment?order=<?= $h($o['order_number']) ?>" class="btn btn-primary">
                            💳 پرداخت مانده بدهی (<?= number_format($remaining) ?> تومان)
                        </a>
                    <?php elseif ($hasPending): ?>
                        <a href="/payment?order=<?= $h($o['order_number']) ?>" class="btn btn-outline">
                            پیگیری پرداخت در انتظار بررسی
                        </a>
                        <?php elseif ($isCreditor): ?>
    <?php
    /* آیا درخواست بازگشت قبلی pending است؟ */
    $stmt = $db->prepare("SELECT id, status FROM refund_requests
                          WHERE invoice_id = (SELECT id FROM invoices WHERE invoice_number = ?)
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$o['invoice_number']]);
    $existingRefund = $stmt->fetch();
    ?>
    <?php if ($existingRefund && $existingRefund['status'] === 'pending'): ?>
        <span class="btn btn-outline" style="cursor:default;">
            ⏳ درخواست بازگشت وجه در انتظار بررسی
        </span>
    <?php elseif ($existingRefund && $existingRefund['status'] === 'paid'): ?>
        <span class="btn btn-outline" style="cursor:default;">
            ✓ بازگشت وجه انجام شد
        </span>
    <?php else: ?>
        <button type="button" class="btn btn-primary"
                onclick="document.getElementById('refundBox<?= (int)$o['id'] ?>').style.display='block'">
            💰 درخواست بازگشت وجه (<?= number_format(abs($remaining)) ?> تومان)
        </button>

        <div id="refundBox<?= (int)$o['id'] ?>" style="display:none;margin-top:10px;
             background:#fff;border:1px solid var(--line,#E6E2D8);border-radius:12px;padding:14px;">
            <form method="post" action="/refund-request">
                <input type="hidden" name="csrf_token" value="<?= $h(csrf_token()) ?>">
                <input type="hidden" name="invoice_number" value="<?= $h($o['invoice_number']) ?>">

                <div class="form-field">
                    <label>شماره شبا <i>*</i></label>
                    <input type="text" name="sheba" dir="ltr" maxlength="26"
                           placeholder="IRxxxxxxxxxxxxxxxxxxxxxx" required>
                    <span class="field-hint">شبا باید به نام خودتان باشد</span>
                </div>

                <div class="form-field">
                    <label>نام صاحب حساب <i>*</i></label>
                    <input type="text" name="account_holder" required>
                </div>

                <button type="submit" class="btn btn-primary">ثبت درخواست بازگشت ←</button>
                <p class="field-hint">وجه بستانکاری ظرف ۲۴ ساعت کاری به این شبا واریز می‌شود</p>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>


<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
