<?php

declare(strict_types=1);

/**
 * RABETI — Financial Center
 * public/admin/payments.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ─── CSRF (تعریف محافظت‌شده — اگر در auth.php هم هست تداخلی ندارد) ─── */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrfValid')) {
    function csrfValid(string $token): bool
    {
        return $token !== '' && hash_equals(csrf_token(), $token);
    }
}

/* ─── توابع نمایش وضعیت — منطق اصلاح‌شده (Pending ≠ صفر) ─── */

function invoicePaymentState(array $inv): array
{
    $pendingCount = (int)($inv['pending_count'] ?? 0);
    $paidTotal    = (int)($inv['paid_total'] ?? 0);
    $pendingClaim = (int)($inv['pending_claimed_total'] ?? 0);
    $total        = (int)($inv['total'] ?? 0);

    if ($pendingCount > 0) {
        return [
            'label' => 'در انتظار بررسی مالی'
                . ($pendingClaim > 0 ? ' (' . number_format($pendingClaim) . ' تومان)' : ''),
            'class' => 'is-pending',
        ];
    }
    if ($total > 0 && $paidTotal >= $total) {
        return ['label' => 'پرداخت‌شده کامل', 'class' => 'is-confirmed'];
    }
    if ($paidTotal > 0) {
        return ['label' => 'پرداخت جزئی — ' . number_format($paidTotal) . ' تومان', 'class' => 'is-info'];
    }
    return ['label' => 'بدون پرداخت ثبت‌شده', 'class' => 'is-muted'];
}

function claimDiffLabel(int $claimed, int $verified, int $pendingCount): array
{
    if ($pendingCount > 0) {
        return ['label' => '—', 'class' => 'is-muted'];   // هنوز بررسی نشده
    }
    if ($claimed === $verified) {
        return ['label' => 'بدون اختلاف', 'class' => 'is-confirmed'];
    }
    $diff = $verified - $claimed;
    return [
        'label' => number_format($diff) . ' تومان',
        'class' => $diff < 0 ? 'is-danger' : 'is-success',
    ];
}

function invoicePaymentStateClass(string $label): string
{
    if (str_contains($label, 'در انتظار')) return 'is-pending';
    if (str_contains($label, 'کامل'))      return 'is-confirmed';
    if (str_contains($label, 'قسمتی'))     return 'is-info';
    return 'is-muted';
}

function toman(int $amount): string
{
    return number_format($amount) . ' تومان';
}

function financialStatusLabel(string $status): string
{
    return match ($status) {
        'pending'   => 'در انتظار بررسی',
        'confirmed' => 'تأیید شده',
        'rejected'  => 'رد شده',
        default     => 'نامشخص',
    };
}

function verificationStatusLabel(string $status): string
{
    return match ($status) {
        'unverified' => 'بررسی نشده',
        'verified'   => 'تأیید شده',
        'rejected'   => 'رد شده',
        'adjusted'   => 'اصلاح شده',
        default      => 'نامشخص',
    };
}

function reconciliationLabel(string $type): string
{
    return match ($type) {
        'settled'  => 'تسویه',
        'debtor'   => 'بدهکار',
        'creditor' => 'بستانکار',
        default    => 'ثبت نشده',
    };
}

function reconciliationClass(string $type): string
{
    return match ($type) {
        'settled'  => 'is-confirmed',
        'debtor'   => 'is-danger',
        'creditor' => 'is-purple',
        default    => 'is-muted',
    };
}

function paymentMethodLabel(string $method): string
{
    return match ($method) {
        'sheba'        => 'شبا',
        'card_to_card' => 'کارت به کارت',
        'cash_receipt' => 'واریز نقدی',
        'online'       => 'درگاه آنلاین',
        default        => $method,
    };
}

function formatDateTime(?string $date): string
{
    if (!$date) return '—';
    $time = strtotime($date);
    return $time === false ? $date : date('Y/m/d H:i', $time);
}

function redirectBack(array $params = []): never
{
    $url = '/admin/payments';
    if ($params) $url .= '?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['financial_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['financial_flash'] ?? null;
    unset($_SESSION['financial_flash']);
    return is_array($flash) ? $flash : null;
}

function auditFinancial(
    PDO $db,
    int $userId,
    string $action,
    ?string $entity = null,
    ?int $entityId = null
): void {
    $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                  VALUES (?,?,?,?,?)")
        ->execute([$userId, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/* ═══════════ POST actions ═══════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim((string)($_POST['action'] ?? ''));
    $token  = (string)($_POST['csrf_token'] ?? '');

    if (!csrfValid($token)) {
        flash('error', 'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
        redirectBack();
    }

    $normalizeAmount = static function (string $raw): int|false {
        $clean = trim(str_replace([',', '٬', ' ', '٫'], '', $raw));
        return filter_var($clean, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    };

    /* ═══ تأیید پرداخت ═══ */
    if ($action === 'confirm_payment') {

        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $verifiedAmount = $normalizeAmount((string)($_POST['verified_amount'] ?? ''));
        $note = trim((string)($_POST['verification_note'] ?? ''));

        if ($paymentId <= 0) {
            flash('error', 'شناسه پرداخت نامعتبر است.');
            redirectBack();
        }
        if ($verifiedAmount === false) {
            flash('error', 'مبلغ واقعی تأییدشده باید بیشتر از صفر باشد.');
            redirectBack();
        }
        if (mb_strlen($note) > 500) {
            flash('error', 'توضیح مالی حداکثر ۵۰۰ کاراکتر است.');
            redirectBack();
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new RuntimeException('پرداخت موردنظر پیدا نشد.');
            if ($payment['status'] !== 'pending') throw new RuntimeException('این پرداخت قبلاً بررسی شده است.');

            $invoiceId = (int)$payment['invoice_id'];

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) throw new RuntimeException('فاکتور مربوطه پیدا نشد.');

            $claimedAmount = (int)($payment['claimed_amount'] ?? $payment['amount'] ?? 0);
            $oldVerified   = (int)($payment['verified_amount'] ?? 0);

            $stmt = $db->prepare("
                UPDATE payments SET
                    verified_amount = ?, verification_status = 'verified',
                    verification_note = ?, verified_by = ?, verified_at = NOW(),
                    status = 'confirmed', confirmed_by = ?, confirmed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$verifiedAmount, $note !== '' ? $note : null, $adminId, $adminId, $paymentId]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('پرداخت همزمان توسط کاربر دیگری بررسی شده.');

            /* تاریخچه */
            $reason = $note;
            if ($claimedAmount !== $verifiedAmount) {
                $diffText = ($verifiedAmount - $claimedAmount) > 0
                    ? 'مبلغ واقعی بیشتر از ادعای مشتری است.'
                    : 'مبلغ واقعی کمتر از ادعای مشتری است.';
                $reason = trim(($note !== '' ? $note . ' | ' : '') . $diffText);
            }

            $db->prepare("INSERT INTO payment_verification_history
                (payment_id, invoice_id, old_amount, new_amount, old_status, new_status, reason, changed_by, ip)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $paymentId,
                    $invoiceId,
                    $oldVerified,
                    $verifiedAmount,
                    'pending',
                    'confirmed',
                    $reason !== '' ? $reason : null,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            /* paid_total فقط از verified_amount */
            $stmt = $db->prepare("SELECT COALESCE(SUM(verified_amount),0) FROM payments
                                  WHERE invoice_id = ? AND status = 'confirmed'");
            $stmt->execute([$invoiceId]);
            $paidTotal = (int)$stmt->fetchColumn();

            $invoiceTotal = (int)$invoice['total'];
            $invStatus = ($paidTotal >= $invoiceTotal && $paidTotal > 0) ? 'paid'
                : (($paidTotal > 0) ? 'partial' : 'unpaid');

            $db->prepare("UPDATE invoices SET paid_total = ?, status = ? WHERE id = ?")
                ->execute([$paidTotal, $invStatus, $invoiceId]);

            /* سفارش کامل پرداخت شد → processing */
            if ($invStatus === 'paid') {
                $db->prepare("UPDATE orders SET status='processing' WHERE id=? AND status='pending_review'")
                    ->execute([(int)$invoice['order_id']]);
            }

            auditFinancial($db, $adminId, 'payment.confirm', 'payment', $paymentId);
            $db->commit();

            flash('success', $claimedAmount !== $verifiedAmount
                ? 'پرداخت تأیید شد؛ ادعای مشتری ' . toman($claimedAmount) . '، تأییدشده ' . toman($verifiedAmount)
                : 'پرداخت با مبلغ ' . toman($verifiedAmount) . ' تأیید شد.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            flash('error', 'تأیید پرداخت انجام نشد: ' . $e->getMessage());
        }
        redirectBack();
    }

    /* ═══ اصلاح مبلغ تأییدشده ═══ */
    if ($action === 'adjust_payment') {

        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $verifiedAmount = $normalizeAmount((string)($_POST['verified_amount'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($paymentId <= 0) {
            flash('error', 'شناسه نامعتبر.');
            redirectBack();
        }
        if ($verifiedAmount === false) {
            flash('error', 'مبلغ جدید باید بیشتر از صفر باشد.');
            redirectBack();
        }
        if ($reason === '') {
            flash('error', 'ثبت دلیل برای اصلاح الزامی است.');
            redirectBack();
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new RuntimeException('پرداخت پیدا نشد.');
            if ($payment['status'] !== 'confirmed') throw new RuntimeException('فقط پرداخت تأییدشده قابل اصلاح است.');

            $invoiceId = (int)$payment['invoice_id'];
            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) throw new RuntimeException('فاکتور پیدا نشد.');

            $oldVerified = (int)($payment['verified_amount'] ?? 0);
            if ($oldVerified === $verifiedAmount) throw new RuntimeException('مبلغ جدید تفاوتی ندارد.');

            $db->prepare("UPDATE payments SET
                            verified_amount = ?, verification_status = 'adjusted',
                            verification_note = ?, verified_by = ?, verified_at = NOW()
                          WHERE id = ? AND status = 'confirmed'")
                ->execute([$verifiedAmount, $reason, $adminId, $paymentId]);

            $db->prepare("INSERT INTO payment_verification_history
                (payment_id, invoice_id, old_amount, new_amount, old_status, new_status, reason, changed_by, ip)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $paymentId,
                    $invoiceId,
                    $oldVerified,
                    $verifiedAmount,
                    'confirmed',
                    'confirmed',
                    $reason,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            $stmt = $db->prepare("SELECT COALESCE(SUM(verified_amount),0) FROM payments
                                  WHERE invoice_id = ? AND status = 'confirmed'");
            $stmt->execute([$invoiceId]);
            $paidTotal = (int)$stmt->fetchColumn();
            $invoiceTotal = (int)$invoice['total'];
            $invStatus = ($paidTotal >= $invoiceTotal && $paidTotal > 0) ? 'paid'
                : (($paidTotal > 0) ? 'partial' : 'unpaid');

            $db->prepare("UPDATE invoices SET paid_total=?, status=? WHERE id=?")
                ->execute([$paidTotal, $invStatus, $invoiceId]);

            auditFinancial($db, $adminId, 'payment.adjust', 'payment', $paymentId);
            $db->commit();

            flash('success', 'مبلغ اصلاح شد: ' . toman($oldVerified) . ' ← ' . toman($verifiedAmount));
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            flash('error', 'اصلاح انجام نشد: ' . $e->getMessage());
        }
        redirectBack();
    }

    /* ═══ رد پرداخت ═══ */
    if ($action === 'reject_payment') {

        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($paymentId <= 0) {
            flash('error', 'شناسه نامعتبر.');
            redirectBack();
        }
        if ($reason === '') {
            flash('error', 'ثبت دلیل برای رد الزامی است.');
            redirectBack();
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new RuntimeException('پرداخت پیدا نشد.');
            if ($payment['status'] !== 'pending') throw new RuntimeException('فقط پرداخت pending قابل رد است.');

            $invoiceId = (int)$payment['invoice_id'];
            $oldVerified = (int)($payment['verified_amount'] ?? 0);

            $db->prepare("UPDATE payments SET
                            verified_amount = 0, verification_status = 'rejected',
                            verification_note = ?, verified_by = ?, verified_at = NOW(),
                            status = 'rejected'
                          WHERE id = ? AND status = 'pending'")
                ->execute([$reason, $adminId, $paymentId]);

            $db->prepare("INSERT INTO payment_verification_history
                (payment_id, invoice_id, old_amount, new_amount, old_status, new_status, reason, changed_by, ip)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $paymentId,
                    $invoiceId,
                    $oldVerified,
                    0,
                    'pending',
                    'rejected',
                    $reason,
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

            auditFinancial($db, $adminId, 'payment.reject', 'payment', $paymentId);
            $db->commit();

            flash('success', 'پرداخت رد شد و دلیل آن در سابقه ثبت شد.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            flash('error', 'رد پرداخت انجام نشد: ' . $e->getMessage());
        }
        redirectBack();
    }

    /* ═══ تسویه بستانکار ═══ */
    if ($action === 'settle_creditor') {

        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount = $normalizeAmount((string)($_POST['settlement_amount'] ?? ''));
        $method = trim((string)($_POST['settlement_method'] ?? ''));
        $docNo  = trim((string)($_POST['settlement_document_no'] ?? ''));
        $refNo  = trim((string)($_POST['settlement_reference_no'] ?? ''));
        $desc   = trim((string)($_POST['settlement_description'] ?? ''));

        if ($invoiceId <= 0) {
            flash('error', 'فاکتور نامعتبر.');
            redirectBack();
        }
        if ($amount === false) {
            flash('error', 'مبلغ تسویه باید بیشتر از صفر باشد.');
            redirectBack();
        }
        if (!in_array($method, ['sheba', 'card_to_card', 'cash'], true)) {
            flash('error', 'روش تسویه نامعتبر.');
            redirectBack();
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) throw new RuntimeException('فاکتور پیدا نشد.');
            if (($invoice['reconciliation_type'] ?? '') !== 'creditor') {
                throw new RuntimeException('این فاکتور در وضعیت بستانکار نیست.');
            }

            $reconciliationAmount = (int)($invoice['reconciliation_amount'] ?? 0);

            $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM financial_transactions
                                  WHERE invoice_id=? AND type='creditor_settlement' AND status='completed'");
            $stmt->execute([$invoiceId]);
            $already = (int)$stmt->fetchColumn();
            $remaining = max(0, $reconciliationAmount - $already);

            if ($remaining <= 0) throw new RuntimeException('بستانکاری قبلاً کاملاً تسویه شده.');
            if ($amount > $remaining) throw new RuntimeException('مبلغ بیشتر از مانده بستانکاری (' . toman($remaining) . ') است.');

            $db->prepare("INSERT INTO financial_transactions
                (invoice_id, user_id, type, amount, method, document_no, reference_no, description, status, created_by, completed_at)
                VALUES (?,?, 'creditor_settlement', ?,?,?,?,?, 'completed', ?, NOW())")
                ->execute([
                    $invoiceId,
                    (int)$invoice['user_id'],
                    $amount,
                    $method,
                    $docNo ?: null,
                    $refNo ?: null,
                    $desc ?: null,
                    $adminId
                ]);

            $txnId = (int)$db->lastInsertId();
            auditFinancial($db, $adminId, 'financial.creditor_settlement', 'financial_transaction', $txnId);
            $db->commit();

            flash('success', ($remaining - $amount) === 0
                ? 'بستانکاری کاملاً تسویه شد.'
                : 'تسویه ثبت شد. مانده: ' . toman($remaining - $amount));
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            flash('error', 'تسویه انجام نشد: ' . $e->getMessage());
        }
        redirectBack();
    }

    flash('error', 'عملیات مالی نامعتبر است.');
    redirectBack();
}

/* ═══════════ فیلتر و لیست ═══════════ */

$filter = trim((string)($_GET['filter'] ?? ''));
$allowedFilters = ['', 'pending', 'paid', 'partial', 'settled', 'debtor', 'creditor', 'rejected'];
if (!in_array($filter, $allowedFilters, true)) $filter = '';

$search  = trim((string)($_GET['q'] ?? ''));
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* خلاصه */
$summary = ['pending' => 0, 'paid' => 0, 'partial' => 0, 'settled' => 0, 'debtor' => 0, 'creditor' => 0, 'rejected' => 0];
$summary['pending'] = (int)$db->query("SELECT COUNT(DISTINCT invoice_id) FROM payments WHERE status='pending'")->fetchColumn();
$summary['paid']    = (int)$db->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
$summary['partial'] = (int)$db->query("SELECT COUNT(*) FROM invoices WHERE status='partial'")->fetchColumn();
$summary['debtor']  = (int)$db->query("SELECT COUNT(*) FROM invoices WHERE reconciliation_type='debtor' AND reconciliation_amount>0")->fetchColumn();
$summary['creditor'] = (int)$db->query("
    SELECT COUNT(*) FROM invoices i
    WHERE i.reconciliation_type='creditor' AND i.reconciliation_amount>0
      AND i.reconciliation_amount > COALESCE((SELECT SUM(ft.amount) FROM financial_transactions ft
          WHERE ft.invoice_id=i.id AND ft.type='creditor_settlement' AND ft.status='completed'),0)")->fetchColumn();
$summary['settled'] = (int)$db->query("
    SELECT COUNT(*) FROM invoices i
    WHERE i.reconciliation_type='settled'
       OR (i.reconciliation_type='creditor' AND i.reconciliation_amount>0
           AND i.reconciliation_amount <= COALESCE((SELECT SUM(ft.amount) FROM financial_transactions ft
               WHERE ft.invoice_id=i.id AND ft.type='creditor_settlement' AND ft.status='completed'),0))")->fetchColumn();
$summary['rejected'] = (int)$db->query("SELECT COUNT(DISTINCT invoice_id) FROM payments WHERE status='rejected'")->fetchColumn();

/* فیلتر */
$where = [];
$params = [];
switch ($filter) {
    case 'pending':
        $where[] = "EXISTS (SELECT 1 FROM payments fp WHERE fp.invoice_id=i.id AND fp.status='pending')";
        break;
    case 'paid':
        $where[] = "i.status='paid'";
        break;
    case 'partial':
        $where[] = "i.status='partial'";
        break;
    case 'debtor':
        $where[] = "i.reconciliation_type='debtor' AND i.reconciliation_amount>0";
        break;
    case 'rejected':
        $where[] = "EXISTS (SELECT 1 FROM payments rp WHERE rp.invoice_id=i.id AND rp.status='rejected')";
        break;
    case 'creditor':
    case 'settled':
        $sub = "COALESCE((SELECT SUM(ft.amount) FROM financial_transactions ft
                WHERE ft.invoice_id=i.id AND ft.type='creditor_settlement' AND ft.status='completed'),0)";
        $where[] = $filter === 'creditor'
            ? "i.reconciliation_type='creditor' AND i.reconciliation_amount > $sub"
            : "(i.reconciliation_type='settled' OR (i.reconciliation_type='creditor' AND i.reconciliation_amount>0 AND i.reconciliation_amount <= $sub))";
        break;
}
if ($search !== '') {
    $where[] = "(i.invoice_number LIKE ? OR o.order_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.company_name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT COUNT(*) FROM invoices i
    INNER JOIN orders o ON o.id=i.order_id
    INNER JOIN users u ON u.id=i.user_id
    $whereSql");
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $db->prepare("
    SELECT i.id, i.invoice_number, i.order_id, i.user_id, i.total, i.paid_total, i.status,
           i.reconciliation_type, i.reconciliation_amount, i.issued_at,
           o.order_number, u.full_name, u.mobile, u.company_name,
           (SELECT COALESCE(SUM(CASE WHEN p.status<>'rejected' THEN COALESCE(p.claimed_amount,p.amount) ELSE 0 END),0)
            FROM payments p WHERE p.invoice_id=i.id) AS claimed_total,
           (SELECT COALESCE(SUM(CASE WHEN p.status='confirmed' THEN COALESCE(p.verified_amount,0) ELSE 0 END),0)
            FROM payments p WHERE p.invoice_id=i.id) AS verified_total,
           (SELECT COALESCE(SUM(CASE WHEN p.status='pending' THEN COALESCE(p.claimed_amount,p.amount) ELSE 0 END),0)
            FROM payments p WHERE p.invoice_id=i.id) AS pending_claimed_total,
           (SELECT COUNT(*) FROM payments p WHERE p.invoice_id=i.id AND p.status='pending') AS pending_count,
           (SELECT COUNT(*) FROM payments p WHERE p.invoice_id=i.id AND p.status='rejected') AS rejected_count
    FROM invoices i
    INNER JOIN orders o ON o.id=i.order_id
    INNER JOIN users u ON u.id=i.user_id
    $whereSql
    ORDER BY (EXISTS (SELECT 1 FROM payments pp WHERE pp.invoice_id=i.id AND pp.status='pending')) ASC,
             i.issued_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$flash = getFlash();

$adminPageTitle = 'مرکز مالی';
$activeMenu = 'payments';

ob_start();
?>

<div class="financial-page">

    <?php if ($flash): ?>
        <div class="financial-flash <?= $flash['type'] === 'success' ? 'is-success' : 'is-error' ?>">
            <?= $h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ═══ خلاصه ═══ -->
    <section class="financial-summary-grid">
        <a href="/admin/payments?filter=pending" class="financial-summary-card is-warning">
            <span class="financial-summary-icon">!</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">در انتظار بررسی</span>
                <strong><?= number_format($summary['pending']) ?></strong>
            </span>
        </a>
        <a href="/admin/payments?filter=paid" class="financial-summary-card is-success">
            <span class="financial-summary-icon">✓</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">پرداخت کامل</span>
                <strong><?= number_format($summary['paid']) ?></strong>
            </span>
        </a>
        <a href="/admin/payments?filter=partial" class="financial-summary-card is-info">
            <span class="financial-summary-icon">◐</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">پرداخت قسمتی</span>
                <strong><?= number_format($summary['partial']) ?></strong>
            </span>
        </a>
        <a href="/admin/payments?filter=debtor" class="financial-summary-card is-danger">
            <span class="financial-summary-icon">↓</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">بدهکار</span>
                <strong><?= number_format($summary['debtor']) ?></strong>
            </span>
        </a>
        <a href="/admin/payments?filter=creditor" class="financial-summary-card is-purple">
            <span class="financial-summary-icon">↑</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">بستانکار</span>
                <strong><?= number_format($summary['creditor']) ?></strong>
            </span>
        </a>
        <a href="/admin/payments?filter=settled" class="financial-summary-card is-teal">
            <span class="financial-summary-icon">✓</span>
            <span class="financial-summary-content">
                <span class="financial-summary-label">تسویه</span>
                <strong><?= number_format($summary['settled']) ?></strong>
            </span>
        </a>
    </section>

    <!-- ═══ فیلتر و جستجو ═══ -->
    <section class="financial-toolbar">
        <form method="get" action="/admin/payments" class="financial-search">
            <input type="text" name="q" value="<?= $h($search) ?>" placeholder="جستجو: فاکتور، سفارش، مشتری، موبایل...">
            <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= $h($filter) ?>"><?php endif; ?>
            <button type="submit" class="financial-search-btn">جستجو</button>
        </form>
        <nav class="financial-filters">
            <?php
            $filters = [
                '' => 'همه',
                'pending' => 'در انتظار بررسی',
                'paid' => 'پرداخت کامل',
                'partial' => 'پرداخت قسمتی',
                'settled' => 'تسویه',
                'debtor' => 'بدهکار',
                'creditor' => 'بستانکار',
                'rejected' => 'رد شده'
            ];
            foreach ($filters as $key => $label):
                $url = '/admin/payments';
                $qs = [];
                if ($key !== '') $qs['filter'] = $key;
                if ($search !== '') $qs['q'] = $search;
                if ($qs) $url .= '?' . http_build_query($qs);
            ?>
                <a href="<?= $h($url) ?>" class="<?= $filter === $key ? 'is-active' : '' ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <!-- ═══ لیست فاکتورها ═══ -->
    <section class="financial-list">
        <div class="financial-list-head">
            <div>
                <h2>فاکتورها</h2>
                <span>اطلاعات مالی در سطح فاکتور</span>
            </div>
            <div class="financial-list-count"><?= number_format($totalRows) ?> فاکتور</div>
        </div>

        <?php if (!$invoices): ?>
            <div class="financial-empty">
                <div class="financial-empty-icon">◌</div>
                <h3>موردی پیدا نشد</h3>
                <p>با فیلتر یا جستجوی فعلی، فاکتوری وجود ندارد.</p>
            </div>
        <?php else: ?>

            <?php foreach ($invoices as $invoice):
                $invoiceId     = (int)$invoice['id'];
                $invoiceTotal  = (int)$invoice['total'];
                $claimedTotal  = (int)$invoice['claimed_total'];
                $verifiedTotal = (int)$invoice['verified_total'];
                $pendingClaimed = (int)$invoice['pending_claimed_total'];
                $pendingCount  = (int)$invoice['pending_count'];
                $rejectedCount = (int)$invoice['rejected_count'];

                $paymentProgress = ($invoiceTotal > 0)
                    ? min(100, (int)round(($verifiedTotal / $invoiceTotal) * 100)) : 0;

                /* اختلاف — فقط بعد از بررسی */
                $diffLabel = '—';
                $diffClass = 'is-muted';
                if ($pendingCount === 0 && $verifiedTotal !== $claimedTotal) {
                    $diff = $verifiedTotal - $claimedTotal;
                    $diffLabel = number_format($diff) . ' تومان';
                    $diffClass = $diff < 0 ? 'is-danger' : 'is-success';
                }

                /* پرداخت‌های این فاکتور */
                $stmt = $db->prepare("SELECT * FROM payments WHERE invoice_id=? ORDER BY id DESC");
                $stmt->execute([$invoiceId]);
                $invPayments = $stmt->fetchAll();

                /* تاریخچه بررسی */
                $stmt = $db->prepare("SELECT h.*, u.full_name AS changer FROM payment_verification_history h
                                  LEFT JOIN users u ON u.id = h.changed_by
                                  WHERE h.invoice_id=? ORDER BY h.id DESC LIMIT 10");
                $stmt->execute([$invoiceId]);
                $history = $stmt->fetchAll();

                $creditorRemaining = 0;
                if (($invoice['reconciliation_type'] ?? '') === 'creditor') {
                    $creditorRemaining = max(0, (int)$invoice['reconciliation_amount'] - 0);
                }
            ?>

                <article class="financial-invoice-card" data-invoice="<?= $invoiceId ?>">

                    <!-- سر کارت -->
                    <div class="fic-head">
                        <div>
                            <b dir="ltr" class="fic-inv"><?= $h($invoice['invoice_number']) ?></b>
                            <small dir="ltr">سفارش: <?= $h($invoice['order_number']) ?></small>
                        </div>
                        <div class="fic-customer">
                            <b><?= $h($invoice['full_name']) ?></b>
                            <small><?= $h($invoice['company_name'] ?: '') ?> · <span dir="ltr"><?= $h($invoice['mobile']) ?></span></small>
                        </div>
                        <div class="fic-date"><?= $h(formatDateTime($invoice['issued_at'])) ?></div>
                    </div>

                    <!-- ⭐ بلوک مالی — منطق اصلاح‌شده -->
                    <div class="fin-block">
                        <div class="fin-row">
                            <span class="fin-k">مبلغ فاکتور</span>
                            <span class="fin-v b"><?= number_format($invoiceTotal) ?> تومان</span>
                        </div>
                        <div class="fin-row">
                            <span class="fin-k">ادعای مشتری</span>
                            <span class="fin-v"><?= number_format($claimedTotal) ?> تومان</span>
                        </div>
                        <div class="fin-row">
                            <span class="fin-k">تأیید مالی</span>
                            <span class="fin-v <?= $verifiedTotal > 0 ? '' : 'is-pending' ?>">
                                <?= $verifiedTotal > 0 ? number_format($verifiedTotal) . ' تومان' : 'در انتظار بررسی' ?>
                            </span>
                        </div>
                        <div class="fin-row">
                            <span class="fin-k">اختلاف</span>
                            <span class="fin-v <?= $diffClass ?>"><?= $diffLabel ?></span>
                        </div>
                        <div class="fin-row fin-state">
                            <span class="fin-k">وضعیت پرداخت</span>
                            <?php $payState = invoicePaymentState([
                                'pending_count' => $pendingCount,
                                'paid_total' => $verifiedTotal,
                                'pending_claimed_total' => $pendingClaimed,
                                'total' => $invoiceTotal,
                            ]); ?>
                            <span class="fin-v <?= $payState['class'] ?>"><?= $h($payState['label']) ?></span>
                        </div>
                        <div class="fin-row fin-state">
                            <span class="fin-k">نتیجه مالی (ثبت ادمین)</span>
                            <span class="fin-v <?= reconciliationClass($invoice['reconciliation_type'] ?? '') ?>">
                                <?= $h(reconciliationLabel($invoice['reconciliation_type'] ?? '')) ?>
                            </span>
                        </div>
                        <div class="fin-row">
                            <span class="fin-k">مانده فاکتور</span>
                            <span class="fin-v b"><?= number_format(max(0, $invoiceTotal - $verifiedTotal)) ?> تومان</span>
                        </div>
                    </div>

                    <!-- ═══ پرداخت‌ها ═══ -->
                    <div class="fic-payments">
                        <?php foreach ($invPayments as $p): ?>
                            <div class="fic-payment <?= $p['status'] === 'pending' ? 'is-pending-row' : '' ?>">
                                <div class="fic-payment-info">
                                    <b><?= $h(paymentMethodLabel($p['method'])) ?></b>
                                    <span><?= number_format((int)($p['claimed_amount'] ?? $p['amount'])) ?> تومان
                                        <?= ($p['claimed_amount'] ?? null) !== null && (int)$p['claimed_amount'] !== (int)$p['amount'] ? '' : '' ?>
                                    </span>
                                    <?php if ($p['reference_no']): ?>
                                        <span dir="ltr">پیگیری: <?= $h($p['reference_no']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($p['receipt_path']): ?>
                                        <a href="<?= $h($p['receipt_path']) ?>" target="_blank">🖼 فیش</a>
                                    <?php endif; ?>
                                    <small><?= $h(formatDateTime($p['created_at'])) ?></small>
                                </div>
                                <span class="st-badge <?= $p['status'] === 'pending' ? 'st-new' : ($p['status'] === 'confirmed' ? 'st-converted' : 'st-rejected') ?>">
                                    <?= financialStatusLabel($p['status']) ?>
                                </span>

                                <?php if ($p['status'] === 'pending'): ?>
                                    <div class="fic-payment-forms">
                                        <form method="post" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm_payment">
                                            <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                            <input type="number" name="verified_amount" dir="ltr"
                                                value="<?= (int)($p['claimed_amount'] ?? $p['amount']) ?>" min="1" required
                                                placeholder="مبلغ واقعی">
                                            <input type="text" name="verification_note" placeholder="توضیح مالی (اختیاری)">
                                            <button class="btn btn-orange" style="padding:6px 12px;font-size:.72rem;">✓ تأیید</button>
                                        </form>
                                        <form method="post" class="inline-form" onsubmit="return confirm('رد شود؟');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject_payment">
                                            <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                            <input type="text" name="reason" required placeholder="دلیل رد (الزامی)">
                                            <button class="btn" style="padding:6px 12px;font-size:.72rem;background:#fff0f0;color:#c62828;">✗ رد</button>
                                        </form>
                                    </div>
                                <?php elseif ($p['status'] === 'confirmed'): ?>
                                    <details class="fic-adjust">
                                        <summary>✏️ اصلاح مبلغ تأییدشده</summary>
                                        <form method="post" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="adjust_payment">
                                            <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                            <input type="number" name="verified_amount" dir="ltr"
                                                value="<?= (int)($p['verified_amount'] ?? 0) ?>" min="1" required>
                                            <input type="text" name="reason" required placeholder="دلیل اصلاح (الزامی)">
                                            <button class="btn" style="padding:6px 12px;font-size:.72rem;background:var(--teal-100);color:var(--teal-800);">ثبت اصلاح</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ═══ تاریخچه ═══ -->
                    <?php if ($history): ?>
                        <details class="fic-history">
                            <summary>📜 تاریخچه بررسی (<?= count($history) ?>)</summary>
                            <ul>
                                <?php foreach ($history as $hh): ?>
                                    <li>
                                        <?= $h(formatDateTime($hh['changed_at'])) ?> —
                                        <?= $h($hh['changer'] ?? '—') ?>:
                                        <?= number_format((int)($hh['old_amount'] ?? 0)) ?> ←
                                        <?= number_format((int)($hh['new_amount'] ?? 0)) ?>
                                        <?= $hh['reason'] ? ' — ' . $h($hh['reason']) : '' ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>

                    <!-- ═══ تسویه بستانکار ═══ -->
                    <?php if (($invoice['reconciliation_type'] ?? '') === 'creditor'): ?>
                        <details class="fic-settle">
                            <summary>💰 تسویه بستانکاری</summary>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="settle_creditor">
                                <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                                <input type="number" name="settlement_amount" dir="ltr" min="1" required placeholder="مبلغ تسویه">
                                <select name="settlement_method">
                                    <option value="sheba">شبا</option>
                                    <option value="card_to_card">کارت به کارت</option>
                                    <option value="cash">نقدی</option>
                                </select>
                                <input type="text" name="settlement_document_no" dir="ltr" placeholder="شماره سند">
                                <input type="text" name="settlement_reference_no" dir="ltr" placeholder="شماره پیگیری">
                                <input type="text" name="settlement_description" placeholder="شرح">
                                <button class="btn btn-orange" style="padding:6px 14px;font-size:.72rem;">ثبت تسویه</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <!-- ═══ فاکتور PDF ═══ -->
                    <div class="fic-links">
                        <a href="/invoice-print?inv=<?= $h($invoice['invoice_number']) ?>" target="_blank">🧾 فاکتور</a>
                    </div>

                </article>
            <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- ═══ صفحه‌بندی ═══ -->
<?php if ($totalPages > 1): ?>
    <nav class="financial-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $qs = array_filter(['filter' => $filter, 'q' => $search, 'page' => $i]);
        ?>
            <a href="/admin/payments<?= $qs ? '?' . http_build_query($qs) : '' ?>"
                class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

</div>

<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
