<?php

namespace App\Support;

class Decimal
{
    public static function integer(string $value, int $scale): int
    {
        $parts = explode('.', $value);

        return (int) $parts[0] * (10 ** $scale) + (int) str_pad($parts[1] ?? '', $scale, '0');
    }

    public static function format(int $value, int $scale): string
    {
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);
        $factor = 10 ** $scale;

        return $sign.intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function total(string $quantity, string $price): string
    {
        $product = self::integer($quantity, 4) * self::integer($price, 2);

        return self::format(intdiv($product + 5000, 10000), 2);
    }
}
