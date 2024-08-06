<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use function get_class;

class FormattersDoctrine
{

    public static function register(): void
    {
        Dumper::$shortObjectFormatters[Table::class] = [self::class, 'dumpTableShort'];
        Dumper::$shortObjectFormatters[Column::class] = [self::class, 'dumpColumnShort'];

        Dumper::$objectFormatters[TableDiff::class] = [self::class, 'dumpTableDiff'];
    }

    public static function dumpTableShort(Table $table): string
    {
        return Dumper::class(get_class($table)) . Dumper::bracket('(')
            . Dumper::value($table->getName()) . ' ' . Dumper::exceptions('...')
            . Dumper::bracket(')') . Dumper::objectHashInfo($table);
    }

    public static function dumpColumnShort(Column $column): string
    {
        return Dumper::class(get_class($column)) . Dumper::bracket('(')
            . Dumper::value($column->getName()) . ' ' . Dumper::exceptions('...')
            . Dumper::bracket(')') . Dumper::objectHashInfo($column);
    }

    public static function dumpTableDiff(TableDiff $tableDiff, int $depth): string
    {
        return Dumper::dumpObject($tableDiff, $depth, Dumper::FILTER_EMPTY_ARRAYS);
    }

}
