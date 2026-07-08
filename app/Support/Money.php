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
     * Format a Toman amount for display. Locale-aware:
     *   en => "1,250,000 Toman"   (Latin digits, Latin separator)
     *   fa => "۱٬۲۵۰٬۰۰۰ تومان"    (Persian digits, Persian separator)
     */
    public static function toman(int|float|null $amount, bool $withLabel = true): string
    {
        $amount = (int) round((float) ($amount ?? 0));

        if (app()->getLocale() === 'fa') {
            $grouped = self::toPersianDigits(number_format($amount, 0, '.', '٬'));

            return $withLabel ? $grouped.' تومان' : $grouped;
        }

        $grouped = number_format($amount, 0, '.', ',');

        return $withLabel ? $grouped.' Toman' : $grouped;
    }

    /**
     * Convert Latin digits to Persian — but ONLY under the Persian locale, so
     * the English storefront keeps Latin numerals. The name + signature are
     * unchanged so the existing call sites work under both locales.
     */
    public static function toPersianDigits(string $value): string
    {
        if (app()->getLocale() !== 'fa') {
            return $value;
        }

        return str_replace(range('0', '9'), self::PERSIAN_DIGITS, $value);
    }

    /** Rial amount that ZarinPal expects (1 Toman = 10 Rial). */
    public static function tomanToRial(int $toman): int
    {
        return $toman * 10;
    }
}
