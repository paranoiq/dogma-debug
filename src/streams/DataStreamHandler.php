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
 * Tracks operations on data streams opened via fopen() - protocol "data://"
 */
class DataStreamHandler extends StreamHandler
{
    use StreamHandlerShared;

    private const PROTOCOL = 'data';
    private const TIME_UNIT = 'μs';

}
