<?php
/* ابزار زمان — همه محاسبات ارسال از اینجا */
class Time
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    }

    public static function cutoff(): DateTimeImmutable
    {
        return self::now()->setTime(
            ...(explode(':', Setting::get('ship_cutoff_time', '16:00')))
        );
    }

    public static function isFriday(DateTimeImmutable $d): bool
    {
        return (int)$d->format('N') === 5;   // N: 1=Mon ... 5=Friday... 6=Sat, 7=Sun
    }

    public static function isHoliday(DateTimeImmutable $d): bool
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM holidays WHERE day = ?");
        $stmt->execute([$d->format('Y-m-d')]);
        return (bool)$stmt->fetchColumn();
    }

    public static function isWorkingDay(DateTimeImmutable $d): bool
    {
        return !self::isFriday($d) && !self::isHoliday($d);
    }

    /* اولین روز/ساعت کاری بعد از یک زمان مشخص (+۲۴ ساعت پست) */
    public static function firstWorkingMoment(DateTimeImmutable $t): DateTimeImmutable
    {
        $workStart = Setting::get('ship_post_hours_start', '10:00');
        [$h, $m] = explode(':', $workStart);

        $d = $t;
        for ($i = 0; $i < 14; $i++) {
            if (self::isWorkingDay($d)) {
                $candidate = $d->setTime((int)$h, (int)$m);
                if ($candidate >= $t) return $candidate;
            }
            $d = $d->modify('+1 day')->setTime(0, 0);
        }
        return $t;
    }

    /* روزهای هفته فارسی */
    public static function dayLabelFa(DateTimeImmutable $d): string
    {
        $days = [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'];
        return $days[(int)$d->format('N')] . ' ' . $d->format('Y/m/d');
    }
}
