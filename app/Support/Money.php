<?php

namespace App\Support;

/**
 * Currency + numeral helpers. Prices are stored as integer Toman.
 */
class Money
{
    /** Map of Latin digits to Persian digits. */
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * Format a Toman amount for display, e.g. 1250000 => "۱٬۲۵۰٬۰۰۰ تومان".
     */
    public static function toman(int|float|null $amount, bool $withLabel = true): string
    {
        $amount = (int) round((float) ($amount ?? 0));
        $grouped = number_format($amount, 0, '.', '٬'); // Persian thousands separator
        $fa = self::toPersianDigits($grouped);

        return $withLabel ? $fa.' تومان' : $fa;
    }

    /** Convert any Latin digits in a string to Persian digits. */
    public static function toPersianDigits(string $value): string
    {
        return str_replace(range('0', '9'), self::PERSIAN_DIGITS, $value);
    }

    /** Rial amount that ZarinPal expects (1 Toman = 10 Rial). */
    public static function tomanToRial(int $toman): int
    {
        return $toman * 10;
    }
}
