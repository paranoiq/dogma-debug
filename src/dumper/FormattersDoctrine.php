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
use function get_class;

class FormattersDoctrine
{

    public static function register(): void
    {
        Dumper::$shortObjectFormatters[Table::class] = [self::class, 'dumpTableShort'];
        Dumper::$shortObjectFormatters[Column::class] = [self::class, 'dumpColumnShort'];
    }

    public static function dumpTableShort(Table $table): string
    {
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($table)) : '';

        return Dumper::class(get_class($table)) . Dumper::bracket('(')
            . Dumper::value($table->getName()) . ' ' . Dumper::exceptions('...')
            . Dumper::bracket(')') . $info;
    }

    public static function dumpColumnShort(Column $column): string
    {
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($column)) : '';

        return Dumper::class(get_class($column)) . Dumper::bracket('(')
            . Dumper::value($column->getName()) . ' ' . Dumper::exceptions('...')
            . Dumper::bracket(')') . $info;
    }

}
