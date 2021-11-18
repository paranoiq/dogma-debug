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
 * Tracks operations on local files opened via fopen() - protocol "file://"
 */
class FileStreamHandler extends StreamHandler
{
    use StreamHandlerShared;

    private const PROTOCOL = 'file';
    private const TIME_UNIT = 'μs';

}
