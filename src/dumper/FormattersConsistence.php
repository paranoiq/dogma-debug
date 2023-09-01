<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Consistence\Enum\Enum;
use Consistence\Enum\MultiEnum;
use function array_filter;
use function array_keys;
use function array_search;
use function get_class;
use function implode;
use function in_array;
use function is_string;

class FormattersConsistence
{

    private static function register(): void
    {
        Dumper::$objectFormatters[MultiEnum::class] = [self::class, 'dumpConsistenceMultiEnum']; // must precede Enum
        Dumper::$objectFormatters[Enum::class] = [self::class, 'dumpConsistenceEnum'];
    }

    private static function dumpConsistenceMultiEnum(MultiEnum $enum): string
    {
        $values = $enum::getAvailableValues();
        $keys = array_keys(array_filter($values, static function ($value) use ($values): bool {
            return in_array($value, $values, true);
        }));
        $value = $enum->getValue();
        $value = is_string($value) ? Dumper::string($value) : Dumper::int((string) $value);

        return Dumper::class(get_class($enum)) . Dumper::bracket('(')
            . $value . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2(implode('|', $keys))
            . Dumper::bracket(')');
    }

    private static function dumpConsistenceEnum(Enum $enum): string
    {
        $key = array_search($enum->getValue(), $enum::getAvailableValues(), true);
        $value = $enum->getValue();
        $value = is_string($value) ? Dumper::string($value) : Dumper::int((string) $value);

        return Dumper::class(get_class($enum)) . Dumper::bracket('(')
            . $value . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2((string) $key)
            . Dumper::bracket(')');
    }

}
