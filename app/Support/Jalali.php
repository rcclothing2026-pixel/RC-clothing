<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Self-contained Jalali (Persian) calendar conversion + formatting. No external
 * dependency. Used everywhere dates are shown or entered so the whole site is
 * Jalali-only.
 */
class Jalali
{
    /** @return array{0:int,1:int,2:int} [jy, jm, jd] */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} [gy, gm, gd] */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4)
            + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0));
        $sal_a = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 13 && $gd > $sal_a[$gm]) {
            $gd -= $sal_a[$gm];
            $gm++;
        }

        return [$gy, $gm, $gd];
    }

    /** Format a date as a Jalali string with Persian digits, e.g. ۱۴۰۳/۰۳/۲۶ [۱۴:۳۰]. */
    public static function format($date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }
        $c = $date instanceof Carbon ? $date : Carbon::parse($date);
        [$jy, $jm, $jd] = self::toJalali((int) $c->format('Y'), (int) $c->format('m'), (int) $c->format('d'));
        $out = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        if ($withTime) {
            $out .= ' '.$c->format('H:i');
        }

        return Money::toPersianDigits($out);
    }

    /**
     * Parse a Jalali "YYYY/MM/DD" (Persian or Latin digits, / - or ٫ separators)
     * into a Gregorian "Y-m-d" string for storage. Returns null if unparseable.
     */
    public static function parse(?string $jalali): ?string
    {
        if (! $jalali || trim($jalali) === '') {
            return null;
        }
        $latin = strtr($jalali, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if (! preg_match('/^(\d{4})\D(\d{1,2})\D(\d{1,2})$/', trim($latin), $m)) {
            return null;
        }
        [$gy, $gm, $gd] = self::toGregorian((int) $m[1], (int) $m[2], (int) $m[3]);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /**
     * Parse a Jalali "YYYY/MM/DD HH:MM" (or YYYY-MM-DD HH:MM) into a
     * Gregorian "Y-m-d H:i" string. Falls through to date-only parse if no
     * time component is present. Returns null if unparseable.
     */
    public static function parseDateTime(?string $jalali): ?string
    {
        if (! $jalali || trim($jalali) === '') {
            return null;
        }
        $latin = strtr($jalali, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if (preg_match('/^(\d{4})\D(\d{1,2})\D(\d{1,2})\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', trim($latin), $m)) {
            [$gy, $gm, $gd] = self::toGregorian((int) $m[1], (int) $m[2], (int) $m[3]);
            $s = isset($m[6]) ? (int) $m[6] : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, (int) $m[4], (int) $m[5], $s);
        }
        // No time component → date-only, midnight.
        $date = self::parse($jalali);
        return $date ? $date.' 00:00:00' : null;
    }
}
