<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

class HttpHandler extends StreamWrapper
{
    use StreamHandler;

    private const PROTOCOL = 'http';
    private const PACKET_TYPE = Packet::HTTP_IO;

    public const TIME_MULTIPLIER = 1000;
    public const TIME_UNIT = 'ms';

}
