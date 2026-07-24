<?php

namespace App\Util;

final class BcMath
{
    /**
     * bcadd/bcdiv truncan en vez de redondear; esto suma medio dígito en la posición
     * scale+1 antes de truncar, dando un redondeo half-up real.
     */
    public static function round(string $number, int $scale): string
    {
        $half = '0.' . str_pad('5', $scale + 1, '0', STR_PAD_LEFT);

        return str_starts_with($number, '-')
            ? bcsub($number, $half, $scale)
            : bcadd($number, $half, $scale);
    }
}
