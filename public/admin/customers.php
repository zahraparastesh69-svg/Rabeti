<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
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

require_admin();

$db = db();
$adminId = (int)$_SESSION['admin_id'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/* ─── عملیات ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $do  = $_POST['do'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    /* ۱) approve/reject/suspend/activate شرکت */
    $statusMap = [
        'approve'  => ['active',    'customer.approve'],
        'reject'   => ['rejected',  'customer.reject'],
        'suspend'  => ['suspended', 'customer.suspend'],
        'activate' => ['active',    'customer.activate'],
    ];

    if ($id > 0 && isset($statusMap[$do])) {
        [$status, $action] = $statusMap[$do];

        $db->prepare("UPDATE companies SET status = ?, approved_at = NOW() WHERE id = ?")
            ->execute([$status, $id]);

        $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
            VALUES (?, ?, 'company', ?, ?)
        ")->execute([$adminId, $action, $id, $ip]);

        header('Location: /admin/customers');
        exit;
    }

    /* ۲) ویرایش گروه و شرایط پرداخت شرکت */
    if ($id > 0 && $do === 'update_company') {
        $allowedGroups = ['regular', 'bronze', 'silver', 'gold'];
        $group = in_array($_POST['customer_group'] ?? '', $allowedGroups, true)
            ? $_POST['customer_group'] : 'regular';
        $creditLimit  = max(0, (int)($_POST['credit_limit'] ?? 0));
        $dueDays      = max(0, (int)($_POST['due_days'] ?? 0));
        $paymentTerms = trim((string)($_POST['payment_terms'] ?? 'prepaid'));
        if (mb_strlen($paymentTerms) > 30) $paymentTerms = substr($paymentTerms, 0, 30);
        $internalNotes = trim((string)($_POST['internal_notes'] ?? ''));
        if (mb_strlen($internalNotes) > 5000) $internalNotes = substr($internalNotes, 0, 5000);

        $db->prepare("
            UPDATE companies
            SET customer_group = ?,
                credit_limit   = ?,
                due_days        = ?,
                payment_terms   = ?,
                internal_notes  = ?
            WHERE id = ?
        ")->execute([$group, $creditLimit, $dueDays, $paymentTerms, $internalNotes !== '' ? $internalNotes : null, $id]);

        $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
            VALUES (?, 'company.update_terms', 'company', ?, ?)
        ")->execute([$adminId, $id, $ip]);

        header('Location: /admin/customers?updated=' . $id);
        exit;
    }
    /* ۴) ذخیره آستانه‌های ارتقا */
    if ($do === 'update_thresholds') {
        $thresholdsToSave = [
            'customer_group.bronze_min_jin' => max(0, (int)($_POST['bronze_min_jin'] ?? 100)),
            'customer_group.silver_min_jin' => max(0, (int)($_POST['silver_min_jin'] ?? 500)),
            'customer_group.gold_min_jin'   => max(0, (int)($_POST['gold_min_jin']   ?? 1000)),
        ];

        $stmt = $db->prepare("
        INSERT INTO settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
    ");
        foreach ($thresholdsToSave as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }

        /* آپدیت customer_groups.min_purchase_jin */
        $db->prepare("UPDATE customer_groups SET min_purchase_jin = ? WHERE code = 'bronze'")
            ->execute([$thresholdsToSave['customer_group.bronze_min_jin']]);
        $db->prepare("UPDATE customer_groups SET min_purchase_jin = ? WHERE code = 'silver'")
            ->execute([$thresholdsToSave['customer_group.silver_min_jin']]);
        $db->prepare("UPDATE customer_groups SET min_purchase_jin = ? WHERE code = 'gold'")
            ->execute([$thresholdsToSave['customer_group.gold_min_jin']]);

        $db->prepare("
        INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
        VALUES (?, 'settings.update_thresholds', 'settings', NULL, ?)
    ")->execute([$adminId, $ip]);

        header('Location: /admin/customers?updated=thresholds');
        exit;
    }
    /* ۳) فعال/غیرفعال‌سازی کاربر (toggle is_active) */
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0 && $do === 'toggle_user_active') {
        $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
        $stmt->execute([$userId]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $newActive = $current ? 0 : 1;
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")
                ->execute([$newActive, $userId]);

            $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                VALUES (?, ?, 'user', ?, ?)
            ")->execute([
                $adminId,
                $newActive ? 'customer.activate_user' : 'customer.deactivate_user',
                $userId,
                $ip,
            ]);
        }
        header('Location: /admin/customers');
        exit;
    }
}

/* ─── لیست شرکت‌ها با مدیر آن‌ها ─── */
$companies = $db->query("
    SELECT c.*,
           (SELECT u.full_name FROM company_users cu
            JOIN users u ON u.id = cu.user_id
            WHERE cu.company_id = c.id AND cu.role = 'owner' LIMIT 1) AS owner_name,
           (SELECT u.id FROM company_users cu
            JOIN users u ON u.id = cu.user_id
            WHERE cu.company_id = c.id AND cu.role = 'owner' LIMIT 1) AS owner_user_id,
           (SELECT u.mobile FROM company_users cu
            JOIN users u ON u.id = cu.user_id
            WHERE cu.company_id = c.id AND cu.role = 'owner' LIMIT 1) AS owner_mobile,
           (SELECT u.is_active FROM company_users cu
            JOIN users u ON u.id = cu.user_id
            WHERE cu.company_id = c.id AND cu.role = 'owner' LIMIT 1) AS owner_is_active,
           (SELECT COUNT(*) FROM company_users cu WHERE cu.company_id = c.id) AS user_count
    FROM companies c
    ORDER BY (c.status = 'pending') DESC, c.id DESC
")->fetchAll();

/* آستانه‌های سطح */
$thresholds = [
    'bronze' => (int)get_setting('customer_group.bronze_min_jin', 100),
    'silver' => (int)get_setting('customer_group.silver_min_jin', 500),
    'gold'   => (int)get_setting('customer_group.gold_min_jin',   1000),
];

/* گروه‌های قابل تنظیم (از DB) */
$customerGroups = $db->query("
    SELECT code, name, discount_percent, min_purchase_jin
    FROM customer_groups
    WHERE is_active = 1
    ORDER BY sort_order
")->fetchAll();

$statusLabels = [
    'pending'   => 'در انتظار تأیید',
    'active'    => 'فعال',
    'suspended' => 'معلق',
    'rejected'  => 'رد شده',
];

$groupLabels = [
    'regular' => 'مشتری سطح1',
    'bronze'  => 'برنزی',
    'silver'  => 'نقره‌ای',
    'gold'    => 'طلایی',
];

$groupBadges = [
    'regular' => '#e0e0e0',
    'bronze'  => '#cd7f32',
    'silver'  => '#a8a8a8',
    'gold'    => '#d4af37',
];

$updatedId = isset($_GET['updated']) ? (int)$_GET['updated'] : 0;

$activeMenu = 'customers';
$pageTitle  = 'مشتریان';

ob_start();
?>

<?php if ($updatedId > 0): ?>
    <div class="alert alert-success" style="background:#e8f5ee;color:#1a7f4b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        ✓ اطلاعات شرکت با موفقیت به‌روزرسانی شد.
    </div>
<?php endif; ?>

<?php if ($companies): ?>

    <!-- تنظیمات آستانه ارتقا -->
    <details style="margin-bottom:16px;background:#f8fafc;border:1px solid var(--card-border);border-radius:12px;padding:14px;">
        <summary style="cursor:pointer;font-weight:700;font-size:.82rem;">⚙️ تنظیم آستانه‌های ارتقا</summary>
        <form method="post" style="margin-top:12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="update_thresholds">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;align-items:end;">
                <div>
                    <label style="font-size:.72rem;color:var(--muted);display:block;margin-bottom:4px;">برنزی (جین)</label>
                    <input type="number" name="bronze_min_jin" value="<?= $thresholds['bronze'] ?>" min="0"
                        style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:.72rem;color:var(--muted);display:block;margin-bottom:4px;">نقره‌ای (جین)</label>
                    <input type="number" name="silver_min_jin" value="<?= $thresholds['silver'] ?>" min="0"
                        style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:.72rem;color:var(--muted);display:block;margin-bottom:4px;">طلایی (جین)</label>
                    <input type="number" name="gold_min_jin" value="<?= $thresholds['gold'] ?>" min="0"
                        style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
            </div>
            <button type="submit" class="btn btn-orange" style="margin-top:12px;padding:8px 20px;font-size:.78rem;">
                ✓ ذخیره آستانه‌ها
            </button>
        </form>
    </details>

    <div class="admin-table-wrap">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>شرکت / فروشگاه</th>
                    <th>مدیر</th>
                    <th>اعضا</th>
                    <th>گروه قیمت</th>
                    <th>سقف اعتبار</th>
                    <th>شرایط پرداخت</th>
                    <th>وضعیت</th>
                    <th style="min-width:240px;">عملیات</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($companies as $c): ?>

                    <tr style="<?= $c['status'] === 'pending' ? 'background:#fffbea;' : '' ?>">

                        <td>
                            <b><?= htmlspecialchars($c['name']) ?></b><br>
                            <span style="font-size:.7rem;color:var(--muted);">
                                ثبت: <?= jdate_fa($c['created_at']) ?>
                            </span>
                        </td>

                        <td>
                            <?= htmlspecialchars($c['owner_name'] ?? '—') ?><br>
                            <span dir="ltr" style="font-size:.75rem;color:var(--muted);">
                                <?= htmlspecialchars($c['owner_mobile'] ?? '') ?>
                            </span>
                            <?php if (!empty($c['owner_user_id'])): ?>
                                <form method="post" style="display:inline;margin-top:4px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="toggle_user_active">
                                    <input type="hidden" name="user_id" value="<?= (int)$c['owner_user_id'] ?>">
                                    <button type="submit"
                                        class="btn"
                                        style="padding:3px 10px;font-size:.65rem;background:<?= $c['owner_is_active'] ? '#fff0f0;color:#c62828' : '#e8f5ee;color:#1a7f4b' ?>;border:none;cursor:pointer;border-radius:4px;"
                                        title="<?= $c['owner_is_active'] ? 'غیرفعال‌سازی ورود کاربر' : 'فعال‌سازی ورود کاربر' ?>">
                                        <?= $c['owner_is_active'] ? '✕ غیرفعال کاربر' : '✓ فعال کاربر' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td><?= (int)$c['user_count'] ?></td>

                        <!-- فرم ویرایش گروه قیمت -->
                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="update_company">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <select name="customer_group"
                                    onchange="this.form.submit()"
                                    style="font-size:.72rem;padding:4px 8px;border:2px solid <?= $groupBadges[$c['customer_group']] ?? '#e0e0e0' ?>;border-radius:4px;cursor:pointer;background:<?= $groupBadges[$c['customer_group']] ?? '#e0e0e0' ?>20;">
                                    <?php foreach ($groupLabels as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $c['customer_group'] === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="credit_limit" value="<?= (int)$c['credit_limit'] ?>">
                                <input type="hidden" name="due_days" value="<?= (int)($c['due_days'] ?? 0) ?>">
                                <input type="hidden" name="payment_terms" value="<?= htmlspecialchars($c['payment_terms'] ?? 'prepaid') ?>">
                                <input type="hidden" name="internal_notes" value="<?= htmlspecialchars($c['internal_notes'] ?? '') ?>">
                            </form>

                            <?php
                            $group = $c['customer_group'] ?? 'regular';
                            $total = (int)($c['total_purchased_jin'] ?? 0);

                            // آستانه بعدی
                            $nextThreshold = null;
                            $nextLevel = null;
                            foreach (['bronze' => $thresholds['bronze'], 'silver' => $thresholds['silver'], 'gold' => $thresholds['gold']] as $lvl => $th) {
                                if ($total < $th) {
                                    $nextThreshold = $th;
                                    $nextLevel = $lvl;
                                    break;
                                }
                            }
                            ?>

                            <?php if ($nextThreshold !== null && $group !== 'gold'): ?>
                                <?php
                                $currentBase = 0;
                                if ($group === 'bronze') $currentBase = $thresholds['bronze'];
                                elseif ($group === 'silver') $currentBase = $thresholds['silver'];
                                $needed = $nextThreshold - $total;
                                $progress = $nextThreshold > $currentBase
                                    ? round((($total - $currentBase) / ($nextThreshold - $currentBase)) * 100)
                                    : 0;
                                $progress = max(0, min(100, $progress));
                                ?>
                                <div style="font-size:.68rem;color:var(--muted);margin-top:6px;direction:rtl;">
                                    خرید تجمعی: <b><?= number_format($total) ?></b> جین
                                    <div style="background:#eee;border-radius:3px;height:4px;margin:4px 0;overflow:hidden;">
                                        <div style="background:var(--teal-700);height:100%;width:<?= $progress ?>%;"></div>
                                    </div>
                                    <span style="color:#888;"><?= number_format($needed) ?> جین تا <?= $groupLabels[$nextLevel] ?></span>
                                </div>
                            <?php else: ?>
                                <div style="font-size:.68rem;color:var(--muted);margin-top:6px;">
                                    خرید تجمعی: <b><?= number_format($total) ?></b> جین
                                    <?php if ($group === 'gold'): ?>
                                        <span style="color:#d4af37;font-weight:700;">⭐ بالاترین سطح</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="update_company">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="number" name="credit_limit" value="<?= (int)$c['credit_limit'] ?>"
                                    onchange="this.form.submit()"
                                    style="width:90px;font-size:.72rem;padding:4px 6px;border:1px solid #ddd;border-radius:4px;">
                                <input type="hidden" name="customer_group" value="<?= htmlspecialchars($c['customer_group']) ?>">
                                <input type="hidden" name="due_days" value="<?= (int)($c['due_days'] ?? 0) ?>">
                                <input type="hidden" name="payment_terms" value="<?= htmlspecialchars($c['payment_terms'] ?? 'prepaid') ?>">
                                <input type="hidden" name="internal_notes" value="<?= htmlspecialchars($c['internal_notes'] ?? '') ?>">
                            </form>
                        </td>

                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="update_company">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="text" name="payment_terms"
                                    value="<?= htmlspecialchars($c['payment_terms'] ?? 'prepaid') ?>"
                                    onchange="this.form.submit()"
                                    placeholder="prepaid/net_30/..."
                                    style="width:100px;font-size:.72rem;padding:4px 6px;border:1px solid #ddd;border-radius:4px;">
                                <input type="hidden" name="customer_group" value="<?= htmlspecialchars($c['customer_group']) ?>">
                                <input type="hidden" name="credit_limit" value="<?= (int)$c['credit_limit'] ?>">
                                <input type="hidden" name="due_days" value="<?= (int)($c['due_days'] ?? 0) ?>">
                                <input type="hidden" name="internal_notes" value="<?= htmlspecialchars($c['internal_notes'] ?? '') ?>">
                            </form>
                        </td>

                        <td>
                            <span class="st-badge <?= [
                                                        'pending'   => 'st-new',
                                                        'active'    => 'st-converted',
                                                        'suspended' => 'st-rejected',
                                                        'rejected'  => 'st-rejected',
                                                    ][$c['status']] ?>">
                                <?= $statusLabels[$c['status']] ?? $c['status'] ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($c['status'] === 'pending'): ?>

                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="approve">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn" style="padding:6px 14px;font-size:.72rem;background:#e8f5ee;color:#1a7f4b;font-weight:800;">
                                        ✓ تأیید
                                    </button>
                                </form>

                                <form method="post" style="display:inline;" onsubmit="return confirm('این درخواست رد شود؟');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="reject">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn" style="padding:6px 14px;font-size:.72rem;background:#fff0f0;color:#c62828;">
                                        ✗ رد
                                    </button>
                                </form>

                            <?php elseif ($c['status'] === 'active'): ?>

                                <form method="post" style="display:inline;" onsubmit="return confirm('این شرکت تعلیق شود؟');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="suspend">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn" style="padding:6px 14px;font-size:.72rem;background:#fff4e0;color:#a06010;">
                                        ⏸ تعلیق
                                    </button>
                                </form>

                            <?php else: ?>

                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="activate">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn" style="padding:6px 14px;font-size:.72rem;background:var(--teal-100);color:var(--teal-800);">
                                        ↺ فعال‌سازی
                                    </button>
                                </form>

                            <?php endif; ?>

                            <!-- دکمه‌ی «یادداشت‌ها» برای باز شدن مدال کوچک -->
                            <button type="button"
                                class="btn"
                                style="padding:6px 14px;font-size:.72rem;background:#eef;color:#33475b;"
                                onclick="document.getElementById('notes-modal-<?= (int)$c['id'] ?>').showModal()">
                                📝 یادداشت
                            </button>

                            <!-- مدال یادداشت داخلی -->
                            <dialog id="notes-modal-<?= (int)$c['id'] ?>" style="max-width:500px;border-radius:10px;border:1px solid #ccc;padding:24px;">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="update_company">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="customer_group" value="<?= htmlspecialchars($c['customer_group']) ?>">
                                    <input type="hidden" name="credit_limit" value="<?= (int)$c['credit_limit'] ?>">
                                    <input type="hidden" name="due_days" value="<?= (int)($c['due_days'] ?? 0) ?>">
                                    <input type="hidden" name="payment_terms" value="<?= htmlspecialchars($c['payment_terms'] ?? 'prepaid') ?>">
                                    <h3 style="margin:0 0 12px 0;">یادداشت‌های داخلی — <?= htmlspecialchars($c['name']) ?></h3>
                                    <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">مهلت پرداخت (روز):</label>
                                    <input type="number" name="due_days" value="<?= (int)($c['due_days'] ?? 0) ?>"
                                        style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;">
                                    <label style="font-size:.72rem;color:#666;display:block;margin-bottom:4px;">یادداشت (فقط برای ادمین‌ها):</label>
                                    <textarea name="internal_notes" rows="5"
                                        style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;"><?= htmlspecialchars($c['internal_notes'] ?? '') ?></textarea>
                                    <div style="margin-top:12px;text-align:left;">
                                        <button type="button" onclick="document.getElementById('notes-modal-<?= (int)$c['id'] ?>').close()"
                                            style="padding:6px 14px;font-size:.78rem;background:#f0f0f0;border:none;border-radius:4px;cursor:pointer;">
                                            انصراف
                                        </button>
                                        <button type="submit"
                                            style="padding:6px 14px;font-size:.78rem;background:var(--teal-700);color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                            ذخیره
                                        </button>
                                    </div>
                                </form>

                            </dialog>

                        </td>

                    </tr>


                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

<?php else: ?>


    <div class="empty-state">
        <h2>هنوز مشتری‌ای ثبت‌نام نکرده است</h2>
    </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
