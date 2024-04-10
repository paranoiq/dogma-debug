<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Brick\DateTime\Instant;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Money\Currency;
use function get_class;
use function intval;

class FormattersBrick
{

    public static function register(): void
    {
        // math
        Dumper::$objectFormatters[BigInteger::class] = [self::class, 'dumpBigInteger'];
        Dumper::$objectFormatters[BigRational::class] = [self::class, 'dumpBigRational'];

        // money
        Dumper::$objectFormatters[Currency::class] = [self::class, 'dumpCurrency'];

        // date-time
        Dumper::$objectFormatters[Instant::class] = [self::class, 'dumpInstant'];
        Dumper::$objectFormatters[LocalDate::class] = [self::class, 'dumpLocalDate'];
        Dumper::$objectFormatters[LocalTime::class] = [self::class, 'dumpLocalTime'];
    }

    public static function dumpBigInteger(BigInteger $integer): string
    {
        $value = $integer->__toString();

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($integer)) : '';

        return Dumper::class(get_class($integer)) . Dumper::bracket('(')
            . Dumper::value($value)
            . Dumper::bracket(')') . $info;
    }

    public static function dumpBigRational(BigRational $rational): string
    {
        $numerator = $rational->getNumerator()->__toString();
        $denominator = $rational->getDenominator()->__toString();

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($rational)) : '';

        return Dumper::class(get_class($rational)) . Dumper::bracket('(')
            . Dumper::value($numerator . ' / ' . $denominator) . ' (' . Dumper::value2('~' . (intval($numerator) / intval($denominator))) . ')'
            . Dumper::bracket(')') . $info;
    }

    public static function dumpCurrency(Currency $currency): string
    {
        $value = $currency->__toString();

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($currency)) : '';

        return Dumper::class(get_class($currency)) . Dumper::bracket('(')
            . Dumper::value($value) . ' ' . Dumper::value2($currency->getDefaultFractionDigits())
            . Dumper::bracket(')') . $info;
    }

    public static function dumpInstant(Instant $dt): string
    {
        $value = $dt->__toString();

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($dt)) : '';

        return Dumper::class(get_class($dt)) . Dumper::bracket('(')
            . Dumper::value($value) . ' ' . Dumper::value2($dt->getEpochSecond() . ' ' . $dt->getNano())
            . Dumper::bracket(')') . $info;
    }

    public static function dumpLocalDate(LocalDate $date): string
    {
        $value = $date->__toString();
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($date)) : '';

        return Dumper::class(get_class($date)) . Dumper::bracket('(')
            . Dumper::value($value) . Dumper::bracket(')') . $info;
    }

    public static function dumpLocalTime(LocalTime $time): string
    {
        $value = $time->__toString();
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($time)) : '';

        return Dumper::class(get_class($time)) . Dumper::bracket('(')
            . Dumper::value($value) . Dumper::bracket(')') . $info;
    }

}
