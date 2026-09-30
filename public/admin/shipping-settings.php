<?php
/* [FILE] public/admin/shipping-settings.php — تنظیمات ارسال و زمان‌بندی */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

/* ═══ ساخت جدول settings اگر نیست ═══ */
try {
    $db->query("SELECT 1 FROM settings LIMIT 1");
} catch (Throwable $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(60) PRIMARY KEY,
        `value` TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ═══ تعریف فیلدها — گروه‌بندی شده ═══ */
$fields = [

    /* هزینه‌ها */
    ['key' => 'ship_fee_peyk_tehran',    'label' => 'هزینه پیک (تهران)',        'type' => 'number', 'group' => 'هزینه‌ها'],
    ['key' => 'ship_fee_post_tehran',    'label' => 'هزینه پست (تهران)',        'type' => 'number', 'group' => 'هزینه‌ها'],
    ['key' => 'ship_fee_post_other',     'label' => 'هزینه پست (شهرستان)',      'type' => 'number', 'group' => 'هزینه‌ها'],
    ['key' => 'ship_fee_in_person',      'label' => 'هزینه تحویل حضوری',        'type' => 'number', 'group' => 'هزینه‌ها'],

    /* زمان‌بندی */
    ['key' => 'ship_cutoff_time',        'label' => 'ساعت cutoff سفارش (۱۶:۰۰)', 'type' => 'time',  'group' => 'زمان‌بندی'],
    ['key' => 'ship_prep_minutes_peyk',  'label' => 'آماده‌سازی پیک (دقیقه)',     'type' => 'number', 'group' => 'زمان‌بندی'],
    ['ship_prep_minutes_in_person', 'label' => 'آماده‌سازی حضوری (دقیقه)', 'type' => 'number', 'group' => 'زمان‌بندی'][0] ?? null,
    ['key' => 'ship_window_days',        'label' => 'پنجره نمایش (روز)',         'type' => 'number', 'group' => 'زمان‌بندی'],

    /* ظرفیت */
    ['key' => 'ship_capacity_peyk',      'label' => 'ظرفیت پیک (هر بازه)',       'type' => 'number', 'group' => 'ظرفیت'],
    ['key' => 'ship_capacity_post',      'label' => 'ظرفیت پست (هر روز)',        'type' => 'number', 'group' => 'ظرفیت'],
    ['key' => 'ship_capacity_in_person', 'label' => 'ظرفیت حضوری (هر بازه)',     'type' => 'number', 'group' => 'ظرفیت'],

    /* بازه‌های پیک */
    ['key' => 'ship_slot_peyk_1',        'label' => 'بازه ۱ پیک',                'type' => 'text',   'group' => 'بازه‌های پیک'],
    ['key' => 'ship_slot_peyk_2',        'label' => 'بازه ۲ پیک',                'type' => 'text',   'group' => 'بازه‌های پیک'],
    ['key' => 'ship_slot_peyk_3',        'label' => 'بازه ۳ پیک',                'type' => 'text',   'group' => 'بازه‌های پیک'],

    /* بازه‌های حضوری */
    ['key' => 'ship_slot_in_person_1',   'label' => 'بازه ۱ حضوری',              'type' => 'text',   'group' => 'بازه‌های حضوری'],
    ['key' => 'ship_slot_in_person_2',   'label' => 'بازه ۲ حضوری',              'type' => 'text',   'group' => 'بازه‌های حضوری'],

    /* ساعات پست */
    ['key' => 'ship_post_hours_start',   'label' => 'شروع ساعات پست',            'type' => 'time',   'group' => 'ساعات پست'],
    ['key' => 'ship_post_hours_end',     'label' => 'پایان ساعات پست',           'type' => 'time',   'group' => 'ساعات پست'],

    /* اعلامیه سایت */
    ['key' => 'site_announce',           'label' => 'متن نوار اعلان سایت',       'type' => 'text',   'group' => 'اعلان سایت'],
];

/* حذف آیتم خراب احتمالی (index شماره‌ای) */
$fields = array_values(array_filter($fields, fn($f) => is_array($f) && isset($f['key'])));

/* ═══ خواندن مقادیر فعلی ═══ */
$keys = array_column($fields, 'key');
$placeholders = implode(',', array_fill(0, count($keys), '?'));

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($placeholders)");
$stmt->execute($keys);
$current = [];
foreach ($stmt->fetchAll() as $row) {
    $current[$row['key']] = $row['value'];
}

/* مقادیر پیش‌فرض برای کلیدهای خالی */
$defaults = [
    'ship_fee_peyk_tehran' => '80000',
    'ship_fee_post_tehran' => '45000',
    'ship_fee_post_other' => '65000',
    'ship_fee_in_person' => '0',
    'ship_cutoff_time' => '16:00',
    'ship_prep_minutes_peyk' => '60',
    'ship_prep_minutes_in_person' => '120',
    'ship_window_days' => '7',
    'ship_capacity_peyk' => '8',
    'ship_capacity_post' => '20',
    'ship_capacity_in_person' => '6',
    'ship_slot_peyk_1' => '10:00-13:00',
    'ship_slot_peyk_2' => '13:00-16:00',
    'ship_slot_peyk_3' => '16:00-19:00',
    'ship_slot_in_person_1' => '10:00-13:00',
    'ship_slot_in_person_2' => '16:00-19:00',
    'ship_post_hours_start' => '10:00',
    'ship_post_hours_end' => '17:00',
    'site_announce' => 'ارسال رایگان برای سفارش‌های بالای ۱۰ میلیون تومان',
];

/* ═══ ذخیره ═══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = 0;
    foreach ($fields as $f) {
        $key = $f['key'];
        if (!array_key_exists($key, $_POST['val'] ?? [])) continue;
        $value = trim((string)$_POST['val'][$key]);

        $chk = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key`=?");
        $chk->execute([$key]);

        if ((int)$chk->fetchColumn() > 0) {
            $db->prepare("UPDATE settings SET `value`=? WHERE `key`=?")->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?)")->execute([$key, $value]);
        }
        $updated++;
    }

    /* تعطیلات */
    $holidaysRaw = trim((string)($_POST['holidays'] ?? ''));
    $db->exec("DELETE FROM holidays");
    foreach (preg_split('/\r\n|\r|\n/', $holidaysRaw) as $line) {
        $line = trim($line);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*[=:|\-]?\s*(.*)$/', $line, $m)) {
            $db->prepare("INSERT INTO holidays (day, title) VALUES (?,?)")
                ->execute([$m[1], trim($m[2]) ?: 'تعطیل رسمی']);
        }
    }

    header('Location: /admin/shipping-settings?success=' . urlencode("$updated تنظیم ذخیره شد."));
    exit;
}

/* مقدار نمایشی هر فیلد */
foreach ($fields as &$f) {
    $f['value'] = $current[$f['key']] ?? $defaults[$f['key']] ?? '';
}
unset($f);

/* تعطیلات فعلی */
$holidaysText = '';
foreach ($db->query("SELECT day, title FROM holidays ORDER BY day") as $hh) {
    $holidaysText .= $hh['day'] . ' = ' . $hh['title'] . "\n";
}

$success = trim((string)($_GET['success'] ?? ''));

$pageTitle  = 'تنظیمات ارسال و زمان‌بندی';
$activeMenu = 'shipping-settings';

ob_start();
?>

<style>
    .ship-set-group {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 16px;
    }

    .ship-set-group h3 {
        margin: 0 0 14px;
        font-size: 15px;
        font-weight: 800;
        color: #173e4a;
    }

    .ship-set-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .ship-set-field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 5px;
    }

    .ship-set-field input {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 11px;
        border: 1px solid #dbe2ea;
        border-radius: 9px;
        font-family: inherit;
        font-size: 13px;
    }

    .ship-set-field small {
        display: block;
        margin-top: 4px;
        color: #94a3b8;
        font-size: 10px;
    }

    @media (max-width:760px) {
        .ship-set-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="finc">

    <?php if ($success): ?><div class="finc-alert ok">✅ <?= $h($success) ?></div><?php endif; ?>

    <form method="post">

        <?php
        /* گروه‌بندی فیلدها */
        $groups = [];
        foreach ($fields as $f) $groups[$f['group']][] = $f;
        ?>

        <?php foreach ($groups as $groupName => $groupFields): ?>
            <div class="ship-set-group">
                <h3><?= $h($groupName) ?></h3>

                <div class="ship-set-grid">
                    <?php foreach ($groupFields as $f): ?>
                        <div class="ship-set-field">
                            <label><?= $h($f['label']) ?></label>

                            <?php if ($f['type'] === 'number'): ?>
                                <input type="number" name="val[<?= $h($f['key']) ?>]"
                                    value="<?= $h($f['value']) ?>" min="0">
                            <?php elseif ($f['type'] === 'time'): ?>
                                <input type="time" name="val[<?= $h($f['key']) ?>]"
                                    value="<?= $h($f['value']) ?>">
                            <?php else: ?>
                                <input type="text" name="val[<?= $h($f['key']) ?>]"
                                    value="<?= $h($f['value']) ?>">
                            <?php endif; ?>

                            <?php if (str_starts_with($f['key'], 'ship_slot_')): ?>
                                <small>قالب: HH:MM-HH:MM</small>
                            <?php elseif (str_starts_with($f['key'], 'ship_fee_')): ?>
                                <small>تومان</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ═══ تعطیلات ═══ -->
        <div class="ship-set-group">
            <h3>🗓 تعطیلات رسمی</h3>
            <p style="margin:0 0 10px;font-size:12px;color:#64748b;">
                در این روزها هیچ بازه ارسالی پیشنهاد نمی‌شود.
                هر خط یک تاریخ میلادی، سپس عنوان — مثال:
                <code>2026-03-20 نوروز</code>
            </p>
            <textarea name="holidays" rows="5" dir="ltr"
                style="width:100%;box-sizing:border-box;padding:10px;
                border:1px solid #dbe2ea;border-radius:9px;font-family:monospace;font-size:12px;"
                placeholder="2026-03-20 نوروز"><?= $h($holidaysText) ?></textarea>
        </div>

        <button type="submit" class="btn btn-orange"
            style="padding:12px 30px;font-size:.85rem;">
            💾 ذخیره همه تنظیمات
        </button>
    </form>

</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
