<?php
/* [FILE] public/admin/financial-helpers.php — هلپرهای گزارش‌های مالی */

/* ═══ بازه تاریخ: پیش‌فرض این ماه؛ از GET می‌خواند ═══ */
function fin_dateRange(): array
{
    $from = trim((string)($_GET['from'] ?? ''));
    $to   = trim((string)($_GET['to'] ?? ''));

    if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');           // اول ماه جاری
    }
    if ($to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d 23:59:59');     // تا الان
        $to = substr($to, 0, 10);
    }
    return [$from, $to . ' 23:59:59'];
}

/* ═══ دوره‌های آماده: month/quarter/half/year ═══ */
function fin_presetRange(string $preset): ?array
{
    $now = new DateTime('now');
    return match ($preset) {
        'month'   => [date('Y-m-01'), date('Y-m-d')],
        'quarter' => [date('Y-m-d', strtotime('-2 months', strtotime(date('Y-m-01')))), date('Y-m-d')],
        'half'    => [date('Y-m-d', strtotime('-5 months', strtotime(date('Y-m-01')))), date('Y-m-d')],
        'year'    => [date('Y-01-01'), date('Y-m-d')],
        default   => null,
    };
}

/* ═══ تاریخ شمسی ═══ */
function fin_jalali(?string $mysqlDate): string
{
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

    return $jy . '/' . str_pad((string)$jm, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string)$jd, 2, '0', STR_PAD_LEFT);
}

/* ═══ فرم مشترک بازه تاریخ + دوره‌های آماده (HTML) ═══ */
function fin_rangeForm(string $action, array $hidden = []): void
{
    [$from, $to] = $GLOBALS['finRange'];

    $presets = [
        'month'   => 'این ماه',
        'quarter' => 'سه ماه اخیر',
        'half'    => 'شش ماه اخیر',
        'year'    => 'از ابتدای سال',
    ];

    echo '<div class="admin-card" style="padding:14px;margin-bottom:16px;">
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">';

    foreach ($hidden as $k => $v) {
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string)$v) . '">';
    }

    echo '<div><label style="display:block;font-size:11px;font-weight:700;margin-bottom:4px;">از تاریخ (میلادی)</label>
        <input type="date" name="from" value="' . htmlspecialchars($from) . '"
        style="padding:8px 10px;border:1px solid #dbe2ea;border-radius:9px;font-family:inherit;"></div>';

    echo '<div><label style="display:block;font-size:11px;font-weight:700;margin-bottom:4px;">تا تاریخ</label>
        <input type="date" name="to" value="' . htmlspecialchars($to) . '"
        style="padding:8px 10px;border:1px solid #dbe2ea;border-radius:9px;font-family:inherit;"></div>';

    echo '<div><label style="display:block;font-size:11px;font-weight:700;margin-bottom:4px;">دوره‌های آماده</label>
        <select name="preset" onchange="this.form.submit()" style="padding:8px 10px;border:1px solid #dbe2ea;border-radius:9px;font-family:inherit;">
        <option value="">انتخاب دوره</option>';
    foreach ($presets as $k => $lbl) {
        echo '<option value="' . $k . '">' . $lbl . '</option>';
    }
    echo '</select></div>';

    echo '<button type="submit" class="btn btn-orange" style="padding:9px 20px;font-size:.78rem;">اعمال فیلتر</button>';

    echo '</form></div>';
}

/* ═══ اعمال preset اگر آمده ═══ */
function fin_applyPreset(): void
{
    $preset = trim((string)($_GET['preset'] ?? ''));
    if ($preset === '') return;

    $range = fin_presetRange($preset);
    if ($range) {
        $_GET['from'] = $range[0];
        $_GET['to']   = $range[1];
    }
}
