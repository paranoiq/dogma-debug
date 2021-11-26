<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function round;

class Units
{

    public static function size(int $size, int $digits = 3): string
    {
        $size = (float) $size;

        if ($size >= 2 ** 60) {
            $divider = 2 ** 60;
            $unit = ' ZB';
        } elseif ($size >= 2 ** 50) {
            $divider = 2 ** 50;
            $unit = ' EB';
        } elseif ($size >= 2 ** 40) {
            $divider = 2 ** 40;
            $unit = ' TB';
        } elseif ($size >= 2 ** 30) {
            $divider = 2 ** 30;
            $unit = ' GB';
        } elseif ($size >= 2 ** 20) {
            $divider = 2 ** 20;
            $unit = ' MB';
        } elseif ($size >= 2 ** 10) {
            $divider = 2 ** 10;
            $unit = ' kB';
        } else {
            $divider = 1;
            $unit = ' B';
        }

        return self::digits($size / $divider, $digits) . $unit;
    }

    public static function time(float $time, int $digits = 3): string
    {
        if ($time >= 60 * 60) {
            $multiplier = 1 / 3600;
            $unit = ' hours';
        } elseif ($time >= 60) {
            $multiplier = 1 / 60;
            $unit = ' min';
        } elseif ($time >= 1) {
            $multiplier = 1;
            $unit = ' s';
        } elseif ($time >= 0.001) {
            $multiplier = 1000;
            $unit = ' ms';
        } elseif ($time >= 0.000001) {
            $multiplier = 1000000;
            $unit = ' μs';
        } else {
            $multiplier = 1000000000;
            $unit = ' ns';
        }

        return self::digits($time * $multiplier, $digits) . $unit;
    }

    private static function digits(float $number, int $digits): float
    {
        if ($number >= 1000) {
            return round($number, $digits - 4);
        } elseif ($number >= 100) {
            return round($number, $digits - 3);
        } elseif ($number >= 10) {
            return round($number, $digits - 2);
        } else {
            return round($number, $digits - 1);
        }
    }

}
