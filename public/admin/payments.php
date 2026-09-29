<?php

declare(strict_types=1);

/* [FILE] public/admin/payments.php — Financial Center (ادمین) */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ═══════════ Helpers ═══════════ */

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$money = static fn($v): string => number_format(max(0, (int)$v)) . ' تومان';

$toEn = static function (string $raw): int {
    $clean = trim(str_replace([',', '٬', ' '], '', strtr($raw, [
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
    ])));
    return filter_var($clean, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
};

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

$subStatusLabel = static fn(string $s): string => match ($s) {
    'pending' => 'در انتظار بررسی',
    'verified' => 'تأیید شد',
    'adjusted' => 'اصلاح شد',
    'rejected' => 'رد شد',
    default => $s,
};

$subStatusClass = static fn(string $s): string => match ($s) {
    'pending' => 'warning',
    'verified' => 'success',
    'adjusted' => 'info',
    'rejected' => 'danger',
    default => 'muted',
};

$methodLabel = static fn(string $m): string => match ($m) {
    'sheba' => 'شبا',
    'card_to_card' => 'کارت به کارت',
    'cash_receipt' => 'فیش نقدی',
    'online' => 'درگاه',
    default => $m,
};

/* ═══════════ محاسبه نتیجه مالی — از حقیقت (total / paid_total) ═══════════ */

$reconcile = static function (int $invoiceId) use ($db): array {

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(v.verified_amount), 0)
        FROM payment_verifications v
        INNER JOIN payment_submissions ps ON ps.id = v.payment_submission_id
        WHERE ps.invoice_id = ? AND v.status IN ('verified','adjusted')
    ");
    $stmt->execute([$invoiceId]);
    $paid = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT total FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $total = (int)$stmt->fetchColumn();

    $remaining = $total - $paid;

    if ($remaining > 0) {
        $type = 'debtor';
        $amount = $remaining;
    } elseif ($remaining < 0) {
        $type = 'creditor';
        $amount = abs($remaining);
    } else {
        $type = 'settled';
        $amount = 0;
    }

    $status = ($paid >= $total && $paid > 0) ? 'paid'
        : (($paid > 0) ? 'partial' : 'unpaid');

    $db->prepare("UPDATE invoices SET paid_total=?, status=?, reconciliation_type=?, reconciliation_amount=? WHERE id=?")
        ->execute([$paid, $status, $type, $amount, $invoiceId]);

    if ($status === 'paid') {
        $db->prepare("UPDATE orders SET status='processing' WHERE id=(SELECT order_id FROM invoices WHERE id=?) AND status='pending_review'")
            ->execute([$invoiceId]);
    }

    return [
        'paid' => $paid,
        'total' => $total,
        'remaining' => $remaining,
        'type' => $type,
        'amount' => $amount,
        'status' => $status
    ];
};

/* ═══════════ CSRF ═══════════ */

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
$csrf = csrf_token();

/* ═══════════ POST ═══════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        header('Location: /admin/payments');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    /* ═══ تأیید پرداخت با مبلغ واقعی ═══ */
    if ($action === 'verify_payment') {

        $subId  = (int)($_POST['submission_id'] ?? 0);
        $actual = $toEn((string)($_POST['actual_amount'] ?? ''));
        $note   = trim((string)($_POST['note'] ?? ''));

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT ps.*, i.total AS invoice_total, i.invoice_number
                FROM payment_submissions ps
                INNER JOIN invoices i ON i.id = ps.invoice_id
                WHERE ps.id = ? FOR UPDATE
            ");
            $stmt->execute([$subId]);
            $sub = $stmt->fetch();
            if (!$sub) throw new RuntimeException('پرداخت پیدا نشد.');
            if ($sub['status'] !== 'pending') throw new RuntimeException('این پرداخت قبلاً بررسی شده است.');
            if ($actual <= 0) throw new RuntimeException('مبلغ واقعی باید بیشتر از صفر باشد.');

            $db->prepare("INSERT INTO payment_verifications
                (payment_submission_id, verified_amount, status, note, verified_by, verified_at)
                VALUES (?,?,?,?,?,NOW())")
                ->execute([$subId, $actual, 'verified', $note !== '' ? $note : null, $adminId]);

            $db->prepare("UPDATE payment_submissions SET status='verified', updated_at=NOW() WHERE id=? AND status='pending'")
                ->execute([$subId]);

            $fin = $reconcile((int)$sub['invoice_id']);

            $db->prepare("INSERT INTO payment_verification_history
    (payment_submission_id, invoice_id, old_verified_amount, new_verified_amount, old_status, new_status, reason, changed_by, ip)
    VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $subId,
                    (int)$sub['invoice_id'],
                    0,
                    $actual,
                    'pending',
                    'verified',
                    ($note !== '' ? $note . ' | ' : '') . 'ادعای مشتری: ' . number_format((int)$sub['claimed_amount']),
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            $db->commit();

            $msg = 'تأیید شد — مبلغ واقعی: ' . $money($actual);
            if ((int)$sub['claimed_amount'] !== $actual) {
                $msg .= ' | ادعا: ' . $money((int)$sub['claimed_amount']);
            }
            if ($fin['remaining'] > 0) {
                $msg .= ' | بدهکار خودکار: ' . $money($fin['remaining']);
            } elseif ($fin['remaining'] < 0) {
                $msg .= ' | بستانکار: ' . $money(abs($fin['remaining']));
            }
            header('Location: /admin/payments?success=' . urlencode($msg));
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            header('Location: /admin/payments?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /* ═══ اصلاح مبلغ تأییدشده ═══ */
    if ($action === 'adjust_payment') {

        $subId  = (int)($_POST['submission_id'] ?? 0);
        $newAmt = $toEn((string)($_POST['actual_amount'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT ps.*, v.id AS vid, v.verified_amount
                FROM payment_submissions ps
                INNER JOIN payment_verifications v ON v.payment_submission_id = ps.id
                WHERE ps.id = ? FOR UPDATE
            ");
            $stmt->execute([$subId]);
            $sub = $stmt->fetch();
            if (!$sub) throw new RuntimeException('پرداخت پیدا نشد.');
            if (!in_array($sub['status'], ['verified', 'adjusted'], true))
                throw new RuntimeException('فقط پرداخت تأییدشده قابل اصلاح است.');
            if ($newAmt <= 0) throw new RuntimeException('مبلغ نامعتبر است.');
            if ($reason === '') throw new RuntimeException('ثبت دلیل الزامی است.');
            if ((int)$sub['verified_amount'] === $newAmt) throw new RuntimeException('مبلغ جدید تفاوتی ندارد.');

            $oldAmount = (int)$sub['verified_amount'];

            $db->prepare("UPDATE payment_verifications
                          SET verified_amount=?, status='adjusted', note=?, verified_by=?, verified_at=NOW()
                          WHERE id=?")
                ->execute([$newAmt, $reason, $adminId, (int)$sub['vid']]);

            $fin = $reconcile((int)$sub['invoice_id']);

            $db->prepare("INSERT INTO payment_verification_history
    (payment_submission_id, invoice_id, old_verified_amount, new_verified_amount, old_status, new_status, reason, changed_by, ip)
    VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $subId,
                    (int)$sub['invoice_id'],
                    $oldAmount,
                    $newAmt,
                    $sub['status'],
                    'adjusted',
                    $reason,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            $db->commit();

            header('Location: /admin/payments?success=' . urlencode(
                'اصلاح شد: ' . $money($oldAmount) . ' ← ' . $money($newAmt) .
                    ' | بدهکار فعلی: ' . $money(max(0, $fin['remaining']))
            ));
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            header('Location: /admin/payments?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /* ═══ رد پرداخت ═══ */
    if ($action === 'reject_payment') {

        $subId  = (int)($_POST['submission_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT ps.*, i.invoice_number FROM payment_submissions ps
                                  INNER JOIN invoices i ON i.id = ps.invoice_id WHERE ps.id=? FOR UPDATE");
            $stmt->execute([$subId]);
            $sub = $stmt->fetch();
            if (!$sub) throw new RuntimeException('پرداخت پیدا نشد.');
            if ($sub['status'] !== 'pending') throw new RuntimeException('قبلاً بررسی شده است.');
            if ($reason === '') throw new RuntimeException('دلیل رد الزامی است.');

            $db->prepare("INSERT INTO payment_verifications
                (payment_submission_id, verified_amount, status, note, verified_by, verified_at)
                VALUES (?,NULL,'rejected',?,?,NOW())")
                ->execute([$subId, $reason, $adminId]);

            $db->prepare("UPDATE payment_submissions SET status='rejected', updated_at=NOW() WHERE id=? AND status='pending'")
                ->execute([$subId]);

            $reconcile((int)$sub['invoice_id']);

            $db->prepare("INSERT INTO payment_verification_history
    (payment_submission_id, invoice_id, old_verified_amount, new_verified_amount, old_status, new_status, reason, changed_by, ip)
    VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $subId,
                    (int)$sub['invoice_id'],
                    0,
                    0,
                    'pending',
                    'rejected',
                    $reason,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            $db->commit();
            header('Location: /admin/payments?success=' . urlencode('پرداخت رد شد.'));
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            header('Location: /admin/payments?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /* ═══ تسویه بستانکاری + علامت‌گذاری درخواست بازگشت وجه مشتری ═══ */
    if ($action === 'settle_creditor') {

        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount    = $toEn((string)($_POST['amount'] ?? ''));
        $method    = trim((string)($_POST['method'] ?? ''));
        $docNo     = trim((string)($_POST['document_no'] ?? ''));
        $refNo     = trim((string)($_POST['reference_no'] ?? ''));

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id=? FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) throw new RuntimeException('فاکتور پیدا نشد.');

            $fin = $reconcile($invoiceId);
            if ($fin['type'] !== 'creditor') throw new RuntimeException('این فاکتور بستانکار نیست.');
            if ($amount > $fin['amount']) throw new RuntimeException('مبلغ بیشتر از بستانکاری است.');

            $db->prepare("INSERT INTO financial_transactions
                (invoice_id, user_id, type, amount, method, document_no, reference_no, status, created_by, completed_at)
                VALUES (?,?, 'creditor_settlement', ?, ?, ?, ?, 'completed', ?, NOW())")
                ->execute([$invoiceId, (int)$invoice['user_id'], $amount, $method, $docNo ?: null, $refNo ?: null, $adminId]);
            $txnId = (int)$db->lastInsertId();

            /* ⭐ درخواست بازگشت وجه مشتری → paid */
            $stmt = $db->prepare("SELECT id FROM refund_requests
                                  WHERE invoice_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$invoiceId]);
            if ($refundRow = $stmt->fetch()) {
                $db->prepare("UPDATE refund_requests
                              SET status='paid', paid_at=NOW(),
                                  admin_note=?
                              WHERE id=?")
                    ->execute([
                        'سند تسویه #' . $txnId . ($refNo !== '' ? ' | پیگیری: ' . $refNo : null),
                        (int)$refundRow['id']
                    ]);
            }

            $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                          VALUES (?, 'creditor_settled', 'invoice', ?, ?)")
                ->execute([$adminId, $invoiceId, $_SERVER['REMOTE_ADDR'] ?? null]);

            $db->commit();

            $remainingAfter = $fin['amount'] - $amount;
            header('Location: /admin/payments?success=' . urlencode(
                $remainingAfter === 0 ? 'بستانکاری کاملاً تسویه شد.' : 'تسویه ثبت شد. مانده: ' . $money($remainingAfter)
            ));
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            header('Location: /admin/payments?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: /admin/payments');
    exit;
}

/* ═══════════ داده‌های صفحه ═══════════ */

$filter = trim((string)($_GET['filter'] ?? 'all'));
if (!in_array($filter, ['all', 'pending', 'verified', 'rejected'], true)) $filter = 'all';
$search = trim((string)($_GET['q'] ?? ''));

$success = trim((string)($_GET['success'] ?? ''));
$error   = trim((string)($_GET['error'] ?? ''));

/* آمار */
$stats = [
    'pending'  => (int)$db->query("SELECT COUNT(*) FROM payment_submissions WHERE status='pending'")->fetchColumn(),
    'verified' => (int)$db->query("SELECT COUNT(*) FROM payment_submissions WHERE status='verified'")->fetchColumn(),
    'rejected' => (int)$db->query("SELECT COUNT(*) FROM payment_submissions WHERE status='rejected'")->fetchColumn(),
];

/* فاکتورهای بستانکار (computed) با درخواست بازگشت وجه */
$creditorInvoices = $db->query("
    SELECT i.id, i.invoice_number, i.total, i.paid_total,
           o.order_number, u.full_name,
           (i.paid_total - i.total) AS creditor_amount,
           (SELECT r.id FROM refund_requests r WHERE r.invoice_id = i.id AND r.status='pending' ORDER BY r.id DESC LIMIT 1) AS refund_id,
           (SELECT r.sheba FROM refund_requests r WHERE r.invoice_id = i.id AND r.status='pending' ORDER BY r.id DESC LIMIT 1) AS refund_sheba,
           (SELECT r.account_holder FROM refund_requests r WHERE r.invoice_id = i.id AND r.status='pending' ORDER BY r.id DESC LIMIT 1) AS refund_holder
    FROM invoices i
    INNER JOIN orders o ON o.id = i.order_id
    INNER JOIN users u ON u.id = i.user_id
    WHERE i.paid_total > i.total
    ORDER BY (i.paid_total - i.total) DESC
")->fetchAll();

/* لیست submissions */
$where = [];
$params = [];
if ($filter === 'pending')  $where[] = "ps.status='pending'";
if ($filter === 'verified') $where[] = "ps.status='verified'";
if ($filter === 'rejected') $where[] = "ps.status='rejected'";

if ($search !== '') {
    $where[] = "(i.invoice_number LIKE ? OR o.order_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.company_name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT ps.*, i.invoice_number, i.total AS invoice_total, i.paid_total,
           o.order_number, u.full_name, u.company_name, u.mobile
    FROM payment_submissions ps
    INNER JOIN invoices i ON i.id = ps.invoice_id
    INNER JOIN orders o ON o.id = i.order_id
    INNER JOIN users u ON u.id = ps.user_id
    {$whereSql}
    ORDER BY (ps.status='pending') DESC, ps.id DESC
    LIMIT 100
");
$stmt->execute($params);
$submissions = $stmt->fetchAll();

$pageTitle  = 'مرکز مالی';
$activeMenu = 'payments';

ob_start();
?>

<style>
    /* ═══ Financial Center v2 — self-contained ═══ */
    .finc {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .finc-alert {
        padding: 12px 16px;
        border-radius: 10px;
        font-size: .8rem;
        font-weight: 700;
    }

    .finc-alert.ok {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .finc-alert.err {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .finc-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .finc-stat {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 15px;
    }

    .finc-stat span {
        display: block;
        font-size: 11px;
        color: #64748b;
        margin-bottom: 7px;
    }

    .finc-stat strong {
        font-size: 22px;
    }

    .finc-toolbar {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 12px 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .finc-filter {
        padding: 7px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        text-decoration: none;
        background: #fff;
    }

    .finc-filter.on {
        background: #173e4a;
        border-color: #173e4a;
        color: #fff;
    }

    .finc-search {
        margin-right: auto;
        display: flex;
        gap: 6px;
    }

    .finc-search input {
        min-width: 230px;
        height: 38px;
        border: 1px solid #dbe2ea;
        border-radius: 9px;
        padding: 0 11px;
        font-family: inherit;
    }

    .finc-search button {
        height: 38px;
        padding: 0 16px;
        border: 0;
        border-radius: 9px;
        background: #173e4a;
        color: #fff;
        cursor: pointer;
        font-family: inherit;
        font-weight: 700;
    }

    .finc-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 16px 18px;
    }

    .finc-head {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }

    .finc-inv {
        font-size: 15px;
        font-weight: 800;
    }

    .finc-inv small {
        display: block;
        font-weight: 400;
        color: #64748b;
        font-size: 11px;
    }

    .finc-cust {
        text-align: left;
        font-size: 12px;
        color: #475569;
    }

    .finc-cust b {
        display: block;
        color: #111827;
        font-size: 13px;
    }

    .finc-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 9px;
        margin-bottom: 12px;
    }

    .finc-box {
        background: #f8fafc;
        border-radius: 10px;
        padding: 10px 12px;
    }

    .finc-box span {
        display: block;
        font-size: 10px;
        color: #64748b;
        margin-bottom: 4px;
    }

    .finc-box strong {
        font-size: 13.5px;
    }

    .finc-box.ok {
        background: #ecfdf5;
    }

    .finc-box.ok strong {
        color: #047857;
    }

    .finc-box.deb {
        background: #fef2f2;
    }

    .finc-box.deb strong {
        color: #b91c1c;
    }

    .finc-box.hot {
        background: #fffbeb;
        border: 1px solid #fde68a;
    }

    .finc-box.hot strong {
        color: #b45309;
    }

    .finc-badge {
        display: inline-flex;
        padding: 4px 11px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
    }

    .finc-badge.warning {
        background: #fffbeb;
        color: #b45309;
    }

    .finc-badge.success {
        background: #ecfdf5;
        color: #047857;
    }

    .finc-badge.danger {
        background: #fef2f2;
        color: #b91c1c;
    }

    .finc-badge.info {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .finc-badge.muted {
        background: #f1f5f9;
        color: #64748b;
    }

    .finc-badge.accent {
        background: #fff2e7;
        color: #c96f12;
    }

    .finc-sub {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 9px;
    }

    .finc-sub.pending {
        background: #fffdf5;
        border-color: #fde68a;
    }

    .finc-sub-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .finc-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px 16px;
        font-size: 11.5px;
        color: #64748b;
        margin-top: 7px;
    }

    .finc-meta a {
        color: #2563eb;
    }

    .finc-verify {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e5e7eb;
    }

    .finc-verify input[type="number"] {
        width: 155px;
        padding: 8px 10px;
        border: 1px solid #dbe2ea;
        border-radius: 9px;
        font-family: inherit;
        text-align: center;
    }

    .finc-verify input[type="text"] {
        flex: 1;
        min-width: 150px;
        padding: 8px 10px;
        border: 1px solid #dbe2ea;
        border-radius: 9px;
        font-family: inherit;
    }

    .finc-verify button {
        padding: 8px 15px;
        border: 0;
        border-radius: 9px;
        font-family: inherit;
        font-weight: 700;
        cursor: pointer;
        font-size: 12px;
    }

    .finc-verify .go {
        background: #059669;
        color: #fff;
    }

    .finc-verify .no {
        background: #fee2e2;
        color: #b91c1c;
    }

    .finc-note {
        font-size: 11px;
        color: #64748b;
        margin-top: 6px;
    }

    .finc-hist summary {
        cursor: pointer;
        font-size: 12px;
        color: #2563eb;
        font-weight: 600;
    }

    .finc-hist ul {
        padding: 8px 14px 0;
        margin: 8px 0 0;
    }

    .finc-hist li {
        font-size: 11.5px;
        color: #475569;
        padding: 6px 0;
        border-bottom: 1px dashed #eef2f7;
        list-style: none;
    }

    /* ═══ تسویه بستانکار ═══ */
    .finc-settle {
        background: #f6f2ff;
        border: 1px solid #ddd0f7;
        border-radius: 14px;
        padding: 16px 18px;
    }

    .finc-settle-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }

    .finc-settle-head h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #5b21b6;
    }

    .finc-settle-amount {
        font-size: 18px;
        font-weight: 800;
        color: #5b21b6;
    }

    .finc-settle form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .finc-settle .full {
        grid-column: 1 / -1;
    }

    .finc-settle label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 5px;
    }

    .finc-settle input,
    .finc-settle select {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 11px;
        border: 1px solid #dbe2ea;
        border-radius: 9px;
        font-family: inherit;
        font-size: 13px;
    }

    .finc-settle .actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .finc-settle .actions button {
        padding: 10px 20px;
        border: 0;
        border-radius: 9px;
        background: #5b21b6;
        color: #fff;
        font-family: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    .finc-refund-info {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 12px;
        color: #047857;
        margin-bottom: 12px;
    }

    .finc-refund-info b {
        display: block;
        margin-bottom: 3px;
    }

    .finc-links a {
        font-size: 12px;
        color: #2563eb;
        text-decoration: none;
        font-weight: 600;
        margin-left: 14px;
    }

    .finc-empty {
        text-align: center;
        color: #94a3b8;
        padding: 40px;
        background: #fff;
        border: 1px dashed #e5e7eb;
        border-radius: 14px;
    }

    @media (max-width: 900px) {
        .finc-stats {
            grid-template-columns: 1fr 1fr;
        }

        .finc-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 560px) {
        .finc-stats {
            grid-template-columns: 1fr;
        }

        .finc-grid {
            grid-template-columns: 1fr;
        }

        .finc-settle form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="finc">

    <?php if ($success): ?><div class="finc-alert ok">✅ <?= $h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="finc-alert err">⚠️ <?= $h($error) ?></div><?php endif; ?>

    <!-- ═══ آمار ═══ -->
    <div class="finc-stats">
        <div class="finc-stat"><span>در انتظار بررسی</span><strong><?= number_format($stats['pending']) ?></strong></div>
        <div class="finc-stat"><span>تأییدشده</span><strong><?= number_format($stats['verified']) ?></strong></div>
        <div class="finc-stat"><span>ردشده</span><strong><?= number_format($stats['rejected']) ?></strong></div>
    </div>

    <!-- ═══ فیلتر ═══ -->
    <div class="finc-toolbar">
        <?php
        $filters = ['all' => 'همه', 'pending' => 'در انتظار بررسی', 'verified' => 'تأییدشده', 'rejected' => 'ردشده'];
        foreach ($filters as $k => $lbl):
            $url = '/admin/payments' . ($k !== 'all' ? '?filter=' . $k : '');
        ?>
            <a href="<?= $url ?>" class="finc-filter <?= $filter === $k ? 'on' : '' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
        <form method="get" class="finc-search">
            <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= $h($filter) ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= $h($search) ?>" placeholder="فاکتور / سفارش / مشتری / موبایل">
            <button>جستجو</button>
        </form>
    </div>

    <!-- ═══ تسویه بستانکار + درخواست بازگشت وجه مشتری ═══ -->
    <?php if ($creditorInvoices): ?>
        <?php foreach ($creditorInvoices as $ci): ?>
            <div class="finc-settle">
                <div class="finc-settle-head">
                    <h3>💰 تسویه بستانکاری</h3>
                    <span class="finc-settle-amount"><?= $money((int)$ci['creditor_amount']) ?></span>
                </div>

                <div style="font-size:12px;color:#475569;margin-bottom:10px;">
                    فاکتور <b dir="ltr"><?= $h($ci['invoice_number']) ?></b> —
                    سفارش <span dir="ltr"><?= $h($ci['order_number']) ?></span> —
                    <?= $h($ci['full_name']) ?>
                </div>

                <?php if ($ci['refund_id']): ?>
                    <div class="finc-refund-info">
                        <b>📋 درخواست بازگشت وجه مشتری (در انتظار پرداخت)</b>
                        شبا: <span dir="ltr"><?= $h($ci['refund_sheba']) ?></span>
                        — به نام: <b><?= $h($ci['refund_holder']) ?></b>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="action" value="settle_creditor">
                    <input type="hidden" name="invoice_id" value="<?= (int)$ci['id'] ?>">

                    <div>
                        <label>مبلغ بازگشتی</label>
                        <input type="number" name="amount" min="1" max="<?= (int)$ci['creditor_amount'] ?>"
                            value="<?= (int)$ci['creditor_amount'] ?>" required>
                    </div>
                    <div>
                        <label>روش</label>
                        <select name="method">
                            <option value="sheba">شبا</option>
                            <option value="card_to_card">کارت به کارت</option>
                            <option value="cash">نقدی</option>
                        </select>
                    </div>
                    <div>
                        <label>شماره سند</label>
                        <input type="text" name="document_no" dir="ltr">
                    </div>
                    <div>
                        <label>شماره پیگیری</label>
                        <input type="text" name="reference_no" dir="ltr">
                    </div>
                    <div class="actions">
                        <button type="submit">ثبت تسویه و اعلان به مشتری ←</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ═══ پرداخت‌ها ═══ -->
    <?php if (!$submissions): ?>
        <div class="finc-empty">با فیلتر فعلی پرداختی وجود ندارد.</div>
    <?php endif; ?>

    <?php foreach ($submissions as $s):
        $invTotal  = (int)$s['invoice_total'];
        $paidTotal = (int)$s['paid_total'];
        $remaining = max(0, $invTotal - $paidTotal);
        $claimed   = (int)$s['claimed_amount'];
    ?>

        <div class="finc-card">

            <div class="finc-head">
                <div>
                    <div class="finc-inv"><span dir="ltr"><?= $h($s['invoice_number']) ?></span>
                        <small dir="ltr">سفارش: <?= $h($s['order_number']) ?></small>
                    </div>
                </div>
                <div class="finc-cust">
                    <b><?= $h($s['full_name']) ?></b>
                    <?= $h($s['company_name'] ?: '') ?> · <span dir="ltr"><?= $h($s['mobile']) ?></span>
                </div>
            </div>

            <div class="finc-grid">
                <div class="finc-box"><span>مبلغ فاکتور</span><strong><?= $money($invTotal) ?></strong></div>
                <div class="finc-box ok"><span>پرداخت تأییدشده</span><strong><?= $money($paidTotal) ?></strong></div>
                <div class="finc-box <?= $s['status'] === 'pending' ? 'hot' : 'ok' ?>">
                    <span>ادعای مشتری</span><strong><?= $money($claimed) ?></strong>
                </div>
                <div class="finc-box <?= $remaining > 0 ? 'deb' : 'ok' ?>">
                    <span>مانده فاکتور</span><strong><?= $money($remaining) ?></strong>
                </div>
            </div>

            <div class="fin2-badge <?= $subStatusClass($s['status']) ?>" style="margin-bottom:10px;">
                <?= $h($subStatusLabel($s['status'])) ?>
            </div>

            <div class="finc-sub <?= $s['status'] === 'pending' ? 'pending' : '' ?>">

                <div class="finc-sub-head">
                    <div class="finc-meta" style="margin:0;">
                        <b><?= $h($methodLabel($s['method'])) ?></b>
                        <?php if ($s['reference_no']): ?>
                            <span dir="ltr">پیگیری: <?= $h($s['reference_no']) ?></span>
                        <?php endif; ?>
                        <?php if ($s['receipt_path']): ?>
                            <a href="<?= $h($s['receipt_path']) ?>" target="_blank">🖼 مشاهده فیش</a>
                        <?php endif; ?>
                        <span><?= $jalali($s['created_at'], true) ?></span>
                    </div>
                </div>

                <?php if ($s['status'] === 'pending'): ?>
                    <form method="post" class="finc-verify">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="action" value="verify_payment">
                        <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                        <label style="font-size:11px;font-weight:700;">مبلغ واقعی بانک:</label>
                        <input type="number" name="actual_amount" min="1" required
                            placeholder="مبلغ رسید بانکی">
                        <input type="text" name="note" placeholder="توضیح مالی (اختیاری)">
                        <button type="submit" class="go">✓ تأیید و ثبت</button>
                    </form>
                    <form method="post" class="finc-verify">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="action" value="reject_payment">
                        <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                        <input type="text" name="reason" required placeholder="دلیل رد (الزامی)">
                        <button type="submit" class="no">✗ رد</button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($s['status'], ['verified'], true)): ?>
                    <form method="post" class="finc-verify">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="action" value="adjust_payment">
                        <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                        <label style="font-size:11px;font-weight:700;">اصلاح مبلغ تأییدشده:</label>
                        <input type="number" name="actual_amount" min="1" required value="<?= (int)($s['claimed_amount']) ?>">
                        <input type="text" name="reason" required placeholder="دلیل اصلاح (الزامی)">
                        <button type="submit" class="go" style="background:#2563eb;">ثبت اصلاح</button>
                    </form>
                <?php endif; ?>

            </div>

            <?php
            /* تاریخچه */
            $stmt = $db->prepare("SELECT h.*, u.full_name AS changer
                      FROM payment_verification_history h
                      LEFT JOIN users u ON u.id = h.changed_by
                      WHERE h.payment_submission_id = ? ORDER BY h.id DESC LIMIT 10");
            $stmt->execute([(int)$s['id']]);
            $histories = $stmt->fetchAll();
            ?>

            <?php if ($histories): ?>
                <details class="finc-hist">
                    <summary>📜 تاریخچه بررسی (<?= count($histories) ?>)</summary>
                    <ul>
                        <?php foreach ($histories as $hh): ?>
                            <li>
                                <?= $jalali($hh['changed_at'], true) ?> — <?= $h($hh['changer'] ?? '—') ?>:
                                <?= number_format((int)($hh['old_amount'] ?? 0)) ?> ←
                                <?= number_format((int)($hh['new_amount'] ?? 0)) ?>
                                <?= !empty($hh['reason']) ? ' — ' . $h($hh['reason']) : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
