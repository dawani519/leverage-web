<?php

namespace App\Support;

/** Naira/kobo conversion without floats. All stored amounts are integer kobo. */
class Money
{
    public static function naira(int $kobo): string
    {
        $sign = $kobo < 0 ? '-' : '';
        $kobo = abs($kobo);

        return $sign.'₦'.number_format(intdiv($kobo, 100)).'.'.str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Parse user input like "1,250.50" or "₦900" into kobo. Returns null if it is not a valid amount. */
    public static function parse(string $input): ?int
    {
        $clean = str_replace([',', ' ', '₦'], '', trim($input));

        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
