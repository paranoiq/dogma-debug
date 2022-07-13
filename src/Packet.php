<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Exception;
use function microtime;
use function str_ends_with;
use function strlen;
use function substr;

class Packet
{

    public const INTRO = 1;
    public const OUTRO = 2;
    public const DUMP = 3;
    public const LABEL = 4;
    public const TIMER = 5;
    public const MEMORY = 6;
    public const CALLSTACK = 7;
    public const ERROR = 8;
    public const EXCEPTION = 9;
    public const INTERCEPT = 10;
    public const INFO = 11;
    public const RAW = 12;

    public const STD_IO = 13;
    public const STREAM_IO = 14;
    public const SQL = 15;
    public const REDIS = 16;
    public const AMQP = 17;

    public const OUTPUT_WIDTH = 100;

    private const ALLOWED_CHARS = ["\n", "\r", "\t", "\e"];

    public const MARKER = "\x01\x07\x02\x09";

    /** @var int */
    public $type;

    /** @var string */
    public $payload;

    /** @var string|null */
    public $backtrace;

    /** @var int */
    public $bell;

    /** @var int */
    public $counter = -1;

    /** @var float|null */
    public $time;

    /** @var float|null */
    public $duration;

    /** @var int|null */
    public $processId;

    /** @var int|null */
    public $threadId;

    /**
     * @var int
     * @internal
     */
    public static $count = 0;

    public function __construct(
        int $type,
        string $payload,
        ?string $backtrace = null,
        ?float $duration = null,
        bool $bell = false
    )
    {
        // todo: temporary
        if (str_ends_with($payload, "\n")) {
            throw new Exception('Payload should not end with new line.');
        }
        if (Str::isBinary($payload, self::ALLOWED_CHARS)) {
            Dumper::$binaryEscaping = Dumper::ESCAPING_CP437;
            Debugger::dump($payload);
            throw new Exception('Payload can not contain special characters.');
        }
        if ($backtrace !== null && Str::isBinary($backtrace, self::ALLOWED_CHARS)) {
            echo Dumper::varDump($backtrace);
            throw new Exception('Backtrace can not contain special characters.');
        }
        if (strlen($payload) > Debugger::$maxMessageLength) {
            $payload = substr($payload, 0, Debugger::$maxMessageLength) . Dumper::exceptions(' ... ');
        }

        $this->type = $type;
        $this->payload = $payload;
        $this->backtrace = $backtrace;
        $this->duration = $duration;
        $this->bell = $bell;

        if ($type !== self::OUTPUT_WIDTH) {
            $this->time = microtime(true);
            $this->counter = ++self::$count;
            [$this->processId, $this->threadId] = System::getIds();
        }
    }

    /**
     * Serialized packet structure is marked by ASCII control characters:
     * - "Start of Heading" \x01
     *   - int $type, int $bell, int $counter, float $time, float $duration, int $processId, int $threadId separated by "Unit Separator" \x1F
     * - "Start of Text" \x02
     *   - formatted main message
     * - "End of Text" \x03
     *   - formatted backtrace
     * - "End of Transmission" \x04
     */
    public function serialize(): string
    {
        return "\x01$this->type\x1F$this->bell\x1F$this->counter\x1F$this->time\x1F$this->duration\x1F$this->processId\x1F$this->threadId"
            . "\x02$this->payload\x03$this->backtrace\x04";
    }

}
