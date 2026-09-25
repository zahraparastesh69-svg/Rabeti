<?php
require_once ROOT_PATH . '/app/services/Time.php';

final class ShippingService
{
    private const TEHRAN = 'تهران';

    /* ═══ گزینه‌های مجاز برای نمایش ═══ */
    public static function getOptions(string $province): array
    {
        $now      = Time::now();
        $province = self::normalizeProvince($province);
        $isTehran = ($province === self::TEHRAN);

        $cutoff      = Time::cutoff();
        $prepPeek    = Setting::getInt('ship_prep_minutes_peyk', 60);
        $prepInPerson = Setting::getInt('ship_prep_minutes_in_person', 120);
        $windowDays  = Setting::getInt('ship_window_days', 7);
        $eligibleToday = $now < $cutoff;   // BR6

        $methods = [];

        /* ─── پیک — فقط تهران (BR4) ─── */
        if ($isTehran) {
            $methods['peyk'] = [
                'label' => 'پیک موتوری',
                'fee'   => Setting::getInt('ship_fee_peyk_tehran', 80000),
                'days'  => self::buildDays('peyk', $now, $windowDays, $eligibleToday, $prepPeek),
            ];
        }

        /* ─── پست — همه (شهرستان: +۲۴ ساعت هوشمند) ─── */
        $postFee = $isTehran
            ? Setting::getInt('ship_fee_post_tehran', 45000)
            : Setting::getInt('ship_fee_post_other', 65000);
        $methods['post'] = [
            'label' => 'پست پیشتاز',
            'fee'   => $postFee,
            'days'  => self::buildPostDays($province, $now, $windowDays, $eligibleToday),
        ];

        /* ─── حضوری — فقط تهران (تحویل از کارگاه) ─── */
        if ($isTehran) {
            $methods['in_person'] = [
                'label' => 'تحویل حضوری از کارگاه',
                'fee'   => Setting::getInt('ship_fee_in_person', 0),
                'days'  => self::buildDays('in_person', $now, $windowDays, $eligibleToday, $prepInPerson),
            ];
        }

        /* روزهای بدون گزینه حذف شوند */
        foreach ($methods as $m => $cfg) {
            $methods[$m]['days'] = array_values(array_filter($cfg['days'], fn($d) => !empty($d['slots'])));
        }

        return ['province' => $province, 'is_tehran' => $isTehran, 'methods' => $methods];
    }

    /* ─── ساخت روزها و بازه‌ها برای پیک/حضوری ─── */
    private static function buildDays(
        string $method,
        DateTimeImmutable $now,
        bool $eligibleToday,
        int $prepMinutes,
        int $days = 0
    ): array {
        $window = $days ?: Setting::getInt('ship_window_days', 7);
        $capKey = 'ship_capacity_' . $method;
        $slotKeys = $method === 'peyk'
            ? ['ship_slot_peyk_1', 'ship_slot_peyk_2', 'ship_slot_peyk_3']
            : ['ship_slot_in_person_1', 'ship_slot_in_person_2'];

        $out = [];
        $cursor = $now;

        for ($i = 0; $i < $window + 7; $i++) {   // پنجره + احتساب تعطیلات
            $day = ($i === 0) ? $cursor : $cursor->modify('+1 day');
            $cursor = $day;

            if (!Time::isWorkingDay($day)) continue;   // BR9, BR10

            if ($i === 0 && !$eligibleToday) continue; // BR6 — امروز بسته

            $slots = [];
            foreach ($slotKeys as $sk) {
                $range = Setting::get($sk, '');
                if ($range === '' || !str_contains($range, '-')) continue;
                [$startS, $endS] = explode('-', $range);
                $slotStart = $day->setTime(...array_map('intval', explode(':', $startS)));

                /* آماده‌سازی: شروع بازه ≥ now + prep */
                if (
                    $day->format('Y-m-d') === $now->format('Y-m-d')
                    && $now->modify("+{$prepMinutes} minutes") > $slotStart
                ) {
                    continue;
                }

                $capacity = Setting::getInt($capKey, 8);
                $reserved = self::slotReserved($day, $method, $range);
                $left = max(0, $capacity - $reserved);

                $slots[] = [
                    'id'       => $method . '|' . $day->format('Y-m-d') . '|' . $range,
                    'label'    => self::slotLabelFa($startS, $endS),
                    'left'     => $left,
                    'available' => $left > 0,
                ];
            }

            if ($slots) {
                $out[] = [
                    'date' => $day->format('Y-m-d'),
                    'label' => Time::dayLabelFa($day),
                    'slots' => $slots,
                ];
            }
            if (count($out) >= $window) break;
        }
        return $out;
    }

    /* ─── پست: شهرستان با +۲۴ ساعت هوشمند (BR7, §۶.۴) ─── */
    private static function buildPostDays(
        string $province,
        DateTimeImmutable $now,
        bool $eligibleToday,
        int $days = 0
    ): array {
        $isTehran = ($province === self::TEHRAN);
        $window = $days ?: Setting::getInt('ship_window_days', 7);
        $capacity = Setting::getInt('ship_capacity_post', 20);
        $startS = Setting::get('ship_post_hours_start', '10:00');
        $endS   = Setting::get('ship_post_hours_end', '17:00');

        /* مبنا: تهران cutoff معمول؛ شهرستان +۲۴ ساعت هوشمند */
        $base = $isTehran ? $now : self::firstPostMoment($now);
        $eligibleToday = $eligibleToday && $isTehran;

        $out = [];
        $cursor = $base;

        for ($i = 0; $i < $window + 7; $i++) {
            $day = ($i === 0) ? $cursor : $cursor->modify('+1 day');
            $cursor = $day;

            if (!Time::isWorkingDay($day)) continue;
            if ($i === 0 && !$eligibleToday) continue;

            $capacityLeft = self::slotReserved($day, 'post', 'daily');
            $left = max(0, $capacity - $capacityLeft);

            $out[] = [
                'date' => $day->format('Y-m-d'),
                'label' => Time::dayLabelFa($day),
                'slots' => [[
                    'id'        => 'post|' . $day->format('Y-m-d') . '|daily',
                    'label'     => 'تحویل به پست (' . $startS . ' تا ' . $endS . ')',
                    'left'      => $left,
                    'available' => $left > 0,
                ]],
            ];
            if (count($out) >= $window) break;
        }
        return $out;
    }

    /* +۲۴ ساعت هوشمند پست (§۶.۴) */
    private static function firstPostMoment(DateTimeImmutable $now): DateTimeImmutable
    {
        $plus24 = $now->modify('+24 hours');
        return Time::firstWorkingMoment($plus24);
    }

    private static function slotReserved(DateTimeImmutable $day, string $method, string $label): int
    {
        $stmt = db()->prepare("SELECT reserved FROM delivery_slots
                               WHERE slot_date=? AND method=? AND slot_label=? LIMIT 1");
        $stmt->execute([$day->format('Y-m-d'), $method, $label]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /* اطمینان از وجود ردیف slot در DB (برای ظرفیت) */
    public static function ensureSlotRow(
        string $date,
        string $method,
        string $label,
        string $start,
        string $end,
        int $capacity
    ): int {
        $db = db();
        $stmt = $db->prepare("SELECT id, reserved FROM delivery_slots
                              WHERE slot_date=? AND method=? AND slot_label=? LIMIT 1");
        $stmt->execute([$date, $method, $label]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['reserved'];

        $db->prepare("INSERT INTO delivery_slots (slot_date, method, slot_label, slot_start, slot_end, capacity)
                      VALUES (?,?,?,?,?,?)")
            ->execute([$date, $method, $label, $start, $end, $capacity]);
        return 0;
    }

    /* ═══ اعتبارسنجی انتخاب نهایی (در commit) ═══ */
    public static function validateChoice(
        string $province,
        string $method,
        string $slotId,
        int $addressId,
        int $userId
    ): array {
        $options = self::getOptions($province);
        if (!isset($options['methods'][$method])) {
            throw new RuntimeException('این روش ارسال برای منطقه شما مجاز نیست');
        }
        [$m, $date, $range] = explode('|', $slotId);

        foreach ($options['methods'][$method]['days'] as $day) {
            if ($day['date'] !== $date) continue;
            foreach ($day['slots'] as $slot) {
                if ($slot['id'] === $slotId && $slot['available']) {
                    return [
                        'method' => $method,
                        'date'   => $date,
                        'label'  => $slot['label'],
                        'fee'    => $options['methods'][$method]['fee'],
                    ];
                }
            }
        }
        throw new RuntimeException('بازه ارسال انتخابی شما دیگر موجود نیست — بازه جدید انتخاب کنید');
    }

    /* رزرو ظرفیت */
    public static function reserveSlot(string $slotId): void
    {
        [$m, $date, $range] = explode('|', $slotId);
        [$start, $end] = explode('-', $range);
        $capacity = Setting::getInt('ship_capacity_' . $m, 8);

        self::ensureSlotRow($date, $m, $range, $start, $end, $capacity);
        db()->prepare("UPDATE delivery_slots SET reserved = reserved + 1
                       WHERE slot_date=? AND method=? AND slot_label=? AND reserved < capacity")
            ->execute([$date, $m, $range]);
    }

    /* آزادسازی (لغو سفارش) */
    public static function releaseSlot(string $slotId): void
    {
        [$m, $date, $range] = explode('|', $slotId);
        db()->prepare("UPDATE delivery_slots SET reserved = GREATEST(0, reserved - 1)
                       WHERE slot_date=? AND method=? AND slot_label=?")
            ->execute([$date, $m, $range]);
    }

    public static function normalizeProvince(string $p): string
    {
        $p = trim($p);
        $p = str_replace(['ه‌', 'ي'], ['ه', 'ی'], $p);
        return $p === '' ? '' : $p;
    }

    private static function slotLabelFa(string $start, string $end): string
    {
        return str_replace(':', ':', $start) . ' تا ' . str_replace(':', ':', $end);
    }
}
