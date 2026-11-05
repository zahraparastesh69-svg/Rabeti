<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

/* ─── فیلترها ─── */
$filterAging = trim((string)($_GET['aging'] ?? ''));
$filterUser  = (int)($_GET['user_id'] ?? 0);
$filterFrom  = trim((string)($_GET['from'] ?? ''));
$filterTo    = trim((string)($_GET['to'] ?? ''));

$conditions = [];
$params = [];

/* فقط فاکتورهایی که بدهی دارند (partial یا unpaid) */
$conditions[] = "i.status IN ('unpaid', 'partial')";
$conditions[] = "i.paid_total < i.total";

if ($filterUser > 0) {
    $conditions[] = "i.user_id = ?";
    $params[] = $filterUser;
}
if ($filterFrom !== '') {
    $conditions[] = "i.issued_at >= ?";
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $conditions[] = "i.issued_at <= ?";
    $params[] = $filterTo . ' 23:59:59';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

/* کوئری مطالبات */
$sql = "
    SELECT i.id, i.invoice_number, i.user_id, i.total, i.paid_total,
           (i.total - i.paid_total) AS balance,
           i.issued_at,
           i.due_date,
           DATEDIFF(CURDATE(), i.due_date) AS days_overdue,
           u.full_name AS customer_name, u.mobile AS customer_mobile,
           c.name AS company_name, c.customer_group, c.payment_terms, c.due_days,
           o.order_number, o.id AS order_id, o.status AS order_status
    FROM invoices i
    INNER JOIN users u ON u.id = i.user_id
    LEFT JOIN company_users cu ON cu.user_id = u.id
    LEFT JOIN companies c ON c.id = cu.company_id
    LEFT JOIN orders o ON o.id = i.order_id
    $where
    ORDER BY
        CASE
            WHEN i.due_date IS NULL THEN 4
            WHEN i.due_date < CURDATE() THEN 1
            WHEN i.due_date = CURDATE() THEN 2
            ELSE 3
        END,
        days_overdue DESC,
        i.due_date ASC,
        i.id ASC
    LIMIT 300
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* فیلتر ageing بر اساس days_overdue */
if ($filterAging !== '') {
    $rows = array_filter($rows, function ($r) use ($filterAging) {
        $days = (int)$r['days_overdue'];
        if ($r['due_date'] === null) return false; // مطالبات بدون سررسید را فیلتر کن
        switch ($filterAging) {
            case 'overdue':      return $days > 0;
            case 'due_today':    return $days === 0;
            case 'upcoming_7':   return $days >= -7 && $days < 0;
            case 'upcoming_30':  return $days >= -30 && $days < -7;
            case 'overdue_30':   return $days > 30;
            case 'overdue_60':   return $days > 60;
            case 'overdue_90':   return $days > 90;
            default:             return true;
        }
    });
}

/* محاسبهٔ شاخص‌های خلاصه */
$totalReceivable = 0;
$totalOverdue    = 0;
$countOverdue    = 0;
$countNotDue    = 0;
$countNoDueDate = 0;

foreach ($rows as $r) {
    $totalReceivable += (int)$r['balance'];
    $days = (int)$r['days_overdue'];
    if ($r['due_date'] === null) {
        $countNoDueDate++;
    } elseif ($days > 0) {
        $totalOverdue += (int)$r['balance'];
        $countOverdue++;
    } else {
        $countNotDue++;
    }
}

/* ─── CSV export ─── */
$export = ($_GET['export'] ?? '') === 'csv';
if ($export) {
    require_once ROOT_PATH . '/app/services/CsvExporter.php';
    $csv = new CsvExporter('receivables-' . date('Y-m-d'));
    $csv->header([
        'شناسه', 'شماره فاکتور', 'مشتری', 'شرکت',
        'مبلغ فاکتور', 'پرداخت‌شده', 'مانده',
        'تاریخ صدور', 'سررسید', 'روزهای تأخیر',
        'شماره سفارش', 'وضعیت سفارش'
    ]);
    foreach ($rows as $r) {
        $csv->row([
            $r['id'],
            $r['invoice_number'],
            $r['customer_name'],
            $r['company_name'] ?? '',
            (int)$r['total'],
            (int)$r['paid_total'],
            (int)$r['balance'],
            $r['issued_at'],
            $r['due_date'],
            $r['days_overdue'] !== null ? (int)$r['days_overdue'] : '',
            $r['order_number'] ?? '',
            $r['order_status'] ?? '',
        ]);
    }
    $csv->end();
}

/* فهرست کاربران */
$users = $db->query("SELECT id, full_name, mobile FROM users WHERE role = 'customer' ORDER BY full_name")->fetchAll();

/* گروه‌بندی بر اساس مشتری برای خلاصه */
$byCustomer = [];
foreach ($rows as $r) {
    $uid = (int)$r['user_id'];
    if (!isset($byCustomer[$uid])) {
        $byCustomer[$uid] = [
            'name' => $r['customer_name'],
            'mobile' => $r['customer_mobile'],
            'company' => $r['company_name'],
            'total_balance' => 0,
            'count' => 0,
            'overdue_balance' => 0,
            'overdue_count' => 0,
        ];
    }
    $byCustomer[$uid]['total_balance'] += (int)$r['balance'];
    $byCustomer[$uid]['count']++;
    if ($r['due_date'] !== null && (int)$r['days_overdue'] > 0) {
        $byCustomer[$uid]['overdue_balance'] += (int)$r['balance'];
        $byCustomer[$uid]['overdue_count']++;
    }
}
// sort by overdue_balance desc
uasort($byCustomer, fn($a, $b) => $b['overdue_balance'] <=> $a['overdue_balance']);

$activeMenu = 'receivables';
$pageTitle  = 'مطالبات و سررسید';

ob_start();
?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مالی</span>
        <h1>مطالبات و سررسید</h1>
        <p>مدیریت فاکتورهای معوق (به‌اقتصاد بالاتر از سررسید) و آینده.</p>
    </div>
    <a href="?export=csv&<?= http_build_query(array_filter(['aging' => $filterAging, 'user_id' => $filterUser, 'from' => $filterFrom, 'to' => $filterTo])) ?>"
       class="admin-soft-action">
        ⬇ خروجی CSV
    </a>
</div>

<!-- ====================================================
     شاخص‌های خلاصه
==================================================== -->
<div class="stat-cards">
    <div class="stat-card stat-card-accent">
        <div class="stat-card-top">
            <span class="stat-card-label">کل مطالبات</span>
            <span class="stat-card-icon">💰</span>
        </div>
        <div class="num"><?= number_format($totalReceivable) ?></div>
        <div class="stat-card-note">تومان (<?= count($rows) ?> فاکتور)</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <span class="stat-card-label">معوق (سررسید شده)</span>
            <span class="stat-card-icon">⚠</span>
        </div>
        <div class="num"><?= number_format($totalOverdue) ?></div>
        <div class="stat-card-note">تومان (<?= $countOverdue ?> فاکتور)</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <span class="stat-card-label">سررسید امروز</span>
            <span class="stat-card-icon">📅</span>
        </div>
        <div class="num">
            <?php
            $dueToday = 0;
            foreach ($rows as $r) {
                if ($r['due_date'] !== null && (int)$r['days_overdue'] === 0) $dueToday += (int)$r['balance'];
            }
            echo number_format($dueToday);
            ?>
        </div>
        <div class="stat-card-note">تومان</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <span class="stat-card-label">بدون سررسید</span>
            <span class="stat-card-icon">؟</span>
        </div>
        <div class="num"><?= $countNoDueDate ?></div>
        <div class="stat-card-note">فاکتور</div>
    </div>
</div>

<!-- ====================================================
     فیلتر
==================================================== -->
<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;margin:24px 0 18px;align-items:flex-end;">
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">وضعیت تأخیر:</label>
        <select name="aging" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <option value="overdue"    <?= $filterAging === 'overdue' ? 'selected' : '' ?>>معوق (سررسید شده)</option>
            <option value="due_today"  <?= $filterAging === 'due_today' ? 'selected' : '' ?>>سررسید امروز</option>
            <option value="upcoming_7" <?= $filterAging === 'upcoming_7' ? 'selected' : '' ?>>۷ روز آینده</option>
            <option value="upcoming_30"<?= $filterAging === 'upcoming_30' ? 'selected' : '' ?>>۳۰ روز آینده</option>
            <option value="overdue_30" <?= $filterAging === 'overdue_30' ? 'selected' : '' ?>>بیش از ۳۰ روز تأخیر</option>
            <option value="overdue_60" <?= $filterAging === 'overdue_60' ? 'selected' : '' ?>>بیش از ۶۰ روز تأخیر</option>
            <option value="overdue_90" <?= $filterAging === 'overdue_90' ? 'selected' : '' ?>>بیش از ۹۰ روز تأخیر</option>
        </select>
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">مشتری:</label>
        <select name="user_id" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['full_name']) ?> — <?= htmlspecialchars($u['mobile']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">از تاریخ صدور:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>

    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">تا:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>

    <button type="submit" style="padding:6px 14px;background:var(--teal-700);color:#fff;border:none;border-radius:6px;cursor:pointer;">
        فیلتر
    </button>
</form>

<!-- ====================================================
     جدول خلاصه بر اساس مشتری
==================================================== -->
<div class="admin-section-head" style="margin-top:24px;">
    <div>
        <span class="admin-kicker">خلاصه</span>
        <h2>مطالبات بر اساس مشتری</h2>
    </div>
</div>

<div class="admin-table-wrap" style="margin-bottom:32px;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>مشتری</th>
                <th>شرکت</th>
                <th>تعداد فاکتور</th>
                <th>کل مانده</th>
                <th>مانده معوق</th>
                <th>تعداد معوق</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($byCustomer)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:#999;">مطالباتی یافت نشد.</td></tr>
            <?php else: ?>
                <?php foreach (array_slice($byCustomer, 0, 20, true) as $uid => $c): ?>
                    <tr style="<?= $c['overdue_count'] > 0 ? 'background:#fff8f8;' : '' ?>">
                        <td>
                            <?= htmlspecialchars($c['name']) ?><br>
                            <span dir="ltr" style="font-size:.7rem;color:#999;"><?= htmlspecialchars($c['mobile']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($c['company'] ?? '—') ?></td>
                        <td><?= (int)$c['count'] ?></td>
                        <td><b><?= number_format($c['total_balance']) ?></b> ت</td>
                        <td style="<?= $c['overdue_balance'] > 0 ? 'color:#c62828;font-weight:700;' : '' ?>">
                            <?= $c['overdue_balance'] > 0 ? number_format($c['overdue_balance']) : '—' ?>
                            <?= $c['overdue_balance'] > 0 ? ' ت' : '' ?>
                        </td>
                        <td><?= $c['overdue_count'] > 0 ? (int)$c['overdue_count'] : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ====================================================
     جدول تفصیلی فاکتورها
==================================================== -->
<div class="admin-section-head">
    <div>
        <span class="admin-kicker">تفصیلی</span>
        <h2>فهرست فاکتورهای مطالبات (<?= count($rows) ?>)</h2>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>شماره فاکتور</th>
                <th>سفارش</th>
                <th>مشتری</th>
                <th>مبلغ فاکتور</th>
                <th>پرداخت‌شده</th>
                <th>مانده</th>
                <th>تاریخ صدور</th>
                <th>سررسید</th>
                <th>روزهای تأخیر</th>
                <th>وضعیت</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" style="text-align:center;padding:24px;color:#999;">مطالباتی یافت نشد.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $days = $r['due_date'] !== null ? (int)$r['days_overdue'] : null;
                    $overdue = $days !== null && $days > 0;
                    $dueToday = $days === 0;
                    $upcoming = $days !== null && $days < 0;
                    ?>
                    <tr style="<?= $overdue ? 'background:#fff0f0;' : ($dueToday ? 'background:#fffbea;' : '') ?>">
                        <td dir="ltr" style="text-align:right;">
                            <a href="/admin/payments?invoice_id=<?= (int)$r['id'] ?>" style="color:var(--teal-700);">
                                <b><?= htmlspecialchars($r['invoice_number']) ?></b>
                            </a>
                        </td>
                        <td>
                            <?php if ($r['order_id']): ?>
                                <a href="/admin/order-detail?id=<?= (int)$r['order_id'] ?>" style="color:var(--teal-700);">
                                    <?= htmlspecialchars($r['order_number']) ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= number_format((int)$r['total']) ?> ت</td>
                        <td><?= number_format((int)$r['paid_total']) ?> ت</td>
                        <td><b><?= number_format((int)$r['balance']) ?> ت</b></td>
                        <td class="td-muted td-nowrap"><?= jdate_fa($r['issued_at']) ?></td>
                        <td class="td-muted td-nowrap">
                            <?= $r['due_date'] !== null ? jdate_fa($r['due_date']) : '<span style="color:#ccc;">تعریف نشده</span>' ?>
                        </td>
                        <td style="font-weight:700;<?= $overdue ? 'color:#c62828;' : ($upcoming ? 'color:#a06010;' : '') ?>">
                            <?php
                            if ($days === null) {
                                echo '—';
                            } elseif ($days > 0) {
                                echo "+{$days} روز تأخیر";
                            } elseif ($days === 0) {
                                echo 'سررسید امروز';
                            } else {
                                $abs = abs($days);
                                echo "{$abs} روز تا سررسید";
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($r['order_status'] === 'cancelled'): ?>
                                <span class="st-badge st-rejected">لغو شده</span>
                            <?php elseif ($overdue): ?>
                                <span class="st-badge st-rejected">معوق</span>
                            <?php elseif ($dueToday): ?>
                                <span class="st-badge st-quoted">سررسید امروز</span>
                            <?php elseif ($upcoming): ?>
                                <span class="st-badge st-new">در انتظار</span>
                            <?php else: ?>
                                <span class="st-badge st-new"><?= htmlspecialchars($r['order_status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
