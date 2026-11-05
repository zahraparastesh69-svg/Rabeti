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
$filterAction = trim((string)($_GET['action'] ?? ''));
$filterEntity = trim((string)($_GET['entity'] ?? ''));
$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterFrom  = trim((string)($_GET['from'] ?? ''));
$filterTo    = trim((string)($_GET['to'] ?? ''));

$conditions = [];
$params = [];

if ($filterAction !== '') {
    $conditions[] = 'al.action LIKE ?';
    $params[] = "%$filterAction%";
}
if ($filterEntity !== '') {
    $conditions[] = 'al.entity = ?';
    $params[] = $filterEntity;
}
/* جستجوی reason (در صورت وجود ستون) */
$filterReason = trim((string)($_GET['reason'] ?? ''));
if ($filterReason !== '') {
    // اگر ستون reason وجود دارد، آنجا جستجو کن؛ در غیر این صورت، action را جستجو کن (fallback)
    try {
        $check = $db->query("SHOW COLUMNS FROM audit_logs LIKE 'reason'");
        if ($check && $check->fetch()) {
            $conditions[] = 'al.reason LIKE ?';
            $params[] = "%$filterReason%";
        } else {
            $conditions[] = 'al.action LIKE ?';
            $params[] = "%$filterReason%";
        }
    } catch (Throwable $e) {
        $conditions[] = 'al.action LIKE ?';
        $params[] = "%$filterReason%";
    }
}
if ($filterUser > 0) {
    $conditions[] = 'al.user_id = ?';
    $params[] = $filterUser;
}
if ($filterFrom !== '') {
    $conditions[] = 'al.created_at >= ?';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $conditions[] = 'al.created_at <= ?';
    $params[] = $filterTo . ' 23:59:59';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* ─── CSV export ─── */
$export = ($_GET['export'] ?? '') === 'csv';
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['شناسه', 'زمان', 'کاربر', 'عملیات', 'موجودیت', 'شناسه موجودیت', 'IP']);
}

$perPage = $export ? 100000 : 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT al.*, u.full_name AS user_name
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $where
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

/* فهرست کاربران */
$users = $db->query("SELECT id, full_name, mobile FROM users ORDER BY id")->fetchAll();

$activeMenu = 'system-audit';
$pageTitle  = 'رویدادها';

if ($export) {
    // هدر CSV با ستون‌های جدید reason/context
    fputcsv($out, ['شناسه', 'زمان', 'کاربر', 'عملیات', 'دلیل', 'موجودیت', 'شناسه موجودیت', 'IP', 'context']);
    foreach ($logs as $l) {
        $ctx = '';
        if (!empty($l['context'])) {
            // نمایش JSON به‌صورت ساده
            $decoded = json_decode($l['context'], true);
            if (is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $k => $v) {
                    $parts[] = $k . '=' . (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
                }
                $ctx = implode(', ', $parts);
            } else {
                $ctx = (string)$l['context'];
            }
        }
        fputcsv($out, [
            $l['id'],
            $l['created_at'],
            $l['user_name'] ?? '—',
            $l['action'],
            $l['reason'] ?? '',
            $l['entity'] ?? '',
            $l['entity_id'] ?? '',
            $l['ip'] ?? '',
            $ctx,
        ]);
    }
    fclose($out);
    exit;
}

ob_start();
?>

<div class="admin-page-intro">
    <div>
        <span class="admin-kicker">مدیریت سیستم</span>
        <h1>گزارش رویدادها</h1>
        <p>هر تغییر حساس (ورود، تغییر وضعیت سفارش، تأیید پرداخت، اصلاح موجودی و ...) در اینجا ثبت می‌شود.</p>
    </div>
    <a href="?export=csv&<?= http_build_query(array_filter(['action' => $filterAction, 'entity' => $filterEntity, 'user_id' => $filterUser, 'from' => $filterFrom, 'to' => $filterTo])) ?>"
       class="admin-soft-action">
        ⬇ خروجی CSV
    </a>
</div>

<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:flex-end;">
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">عملیات:</label>
        <input type="text" name="action" value="<?= htmlspecialchars($filterAction) ?>"
            placeholder="مثلاً: order."
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">موجودیت:</label>
        <select name="entity" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
            <option value="">همه</option>
            <option value="order"   <?= $filterEntity === 'order' ? 'selected' : '' ?>>سفارش</option>
            <option value="company" <?= $filterEntity === 'company' ? 'selected' : '' ?>>شرکت</option>
            <option value="user"    <?= $filterEntity === 'user' ? 'selected' : '' ?>>کاربر</option>
            <option value="product" <?= $filterEntity === 'product' ? 'selected' : '' ?>>محصول</option>
            <option value="invoice" <?= $filterEntity === 'invoice' ? 'selected' : '' ?>>فاکتور</option>
        </select>
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">کاربر:</label>
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
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">دلیل:</label>
        <input type="text" name="reason" value="<?= htmlspecialchars($filterReason ?? '') ?>"
            placeholder="مثلاً: لغو، تغییر، تأیید"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">از:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <div>
        <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">تا:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"
            style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.78rem;">
    </div>
    <button type="submit" class="btn btn-orange btn-sm">
        فیلتر
    </button>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>زمان</th>
                <th>کاربر</th>
                <th>عملیات</th>
                <th>دلیل</th>
                <th>موجودیت</th>
                <th>شناسه</th>
                <th>context</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;color:#999;">رویدادی یافت نشد.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="td-muted td-nowrap"><?= jdate_fa($l['created_at'], true) ?></td>
                        <td><?= htmlspecialchars($l['user_name'] ?? 'سیستم') ?></td>
                        <td dir="ltr" style="text-align:right;font-size:.75rem;">
                            <b><?= htmlspecialchars($l['action']) ?></b>
                        </td>
                        <td style="font-size:.75rem;">
                            <?php if (!empty($l['reason'])): ?>
                                <?= htmlspecialchars($l['reason']) ?>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($l['entity'] ?? '—') ?></td>
                        <td dir="ltr" style="text-align:right;"><?= htmlspecialchars((string)($l['entity_id'] ?? '')) ?></td>
                        <td style="font-size:.7rem;color:#666;">
                            <?php
                            if (!empty($l['context'])) {
                                $decoded = json_decode($l['context'], true);
                                if (is_array($decoded)) {
                                    $parts = [];
                                    foreach ($decoded as $k => $v) {
                                        $val = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
                                        $parts[] = htmlspecialchars($k) . '=' . htmlspecialchars($val);
                                    }
                                    echo implode('<br>', $parts);
                                } else {
                                    echo '<span style="color:#999;">—</span>';
                                }
                            } else {
                                echo '<span style="color:#999;">—</span>';
                            }
                            ?>
                        </td>
                        <td dir="ltr" style="text-align:right;font-size:.7rem;color:#999;"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (count($logs) === $perPage): ?>
    <div style="text-align:center;margin-top:16px;">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn">
            صفحهٔ بعد ←
        </a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
