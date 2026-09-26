<?php

namespace App\Support;

final class RapMoney
{
    // Domain sanity limit: ten trillion euros per RAP cell, well above the
    // entire State budget. This also rejects concatenated PDF columns.
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(str_replace(["\u{00a0}", "\u{202f}"], ' ', $value));
        if (! preg_match('/^([+-]?)\s*(\d+|\d{1,3}(?: \d{3})+|\d{1,3}(?:\.\d{3})+)(?:([,.])(\d{1,2}))?$/D', $value, $parts)) {
            return null;
        }
        $integer = ltrim(str_replace([' ', '.'], '', $parts[2]), '0') ?: '0';
        if (strlen($integer) > 13) {
            return null;
        }
        $amount = $integer.'.'.str_pad($parts[4] ?? '', 2, '0');

        return $parts[1] === '-' && $amount !== '0.00' ? '-'.$amount : $amount;
    }
}
