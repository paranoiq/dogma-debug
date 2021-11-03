<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function microtime;

class Packet
{

    public const INTRO = 1;
    public const OUTRO = 2;
    public const DUMP = 3;
    public const LABEL = 4;
    public const TIMER = 5;
    public const TRACE = 6;
    public const TAKEOVER = 7;
    public const STD_IO = 8;
    public const FILE_IO = 9;
    public const DB_IO = 10;
    public const HTTP_IO = 11;
    public const FTP_IO = 12;
    public const ERROR = 13;
    public const EXCEPTION = 14;

    public const OUTPUT_WIDTH = 100;

    public const MARKER = "\x01\x07\x02\x09";

    /** @var int */
    public $type;

    /** @var string */
    public $payload;

    /** @var string|null */
    public $backtrace;

    /** @var int */
    public $counter = -1;

    /** @var float|null */
    public $time = 0.0;

    /** @var float|null */
    public $duration = 0.0;

    /** @var string|null */
    public $pid = '0';

    public function __construct(
        int $type,
        string $payload,
        ?string $backtrace = null,
        ?float $duration = null
    )
    {
        $this->type = $type;
        $this->payload = $payload;
        $this->backtrace = $backtrace;
        $this->duration = $duration;

        if ($type !== self::OUTPUT_WIDTH) {
            $this->time = microtime(true);
            $this->counter = Debugger::$counter++;
            $this->pid = System::getId();
        }
    }

}
