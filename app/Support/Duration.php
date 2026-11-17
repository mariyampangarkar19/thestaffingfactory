<?php

namespace App\Support;

/**
 * Worked time is stored in minutes and shown as hours and minutes ("7 h 30").
 */
final class Duration
{
    public static function format(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Hours as a decimal string for spreadsheets ("7.50"). */
    public static function decimalHours(?int $minutes): string
    {
        return number_format(($minutes ?? 0) / 60, 2, '.', '');
    }
}
