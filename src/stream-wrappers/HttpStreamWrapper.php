<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

/**
 * Tracks operations on HTTP(S) files opened via fopen() - protocol "http://" or "https://"
 */
class HttpStreamWrapper extends StreamWrapper
{
    use StreamWrapperMixin;

    public const PROTOCOL = 'http';

}
