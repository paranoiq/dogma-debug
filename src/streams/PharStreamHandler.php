<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

/**
 * Tracks operations on PHP archive files (phar) opened via fopen() - protocol "phar://"
 */
class PharStreamHandler extends StreamHandler
{
    use StreamHandlerShared;

    private const PROTOCOL = 'phar';
    private const TIME_UNIT = 'μs';

}
