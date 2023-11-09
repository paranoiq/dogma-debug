<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Exception;
use function dechex;
use function explode;
use function floatval;
use function intval;
use function ltrim;
use function microtime;
use function ord;
use function preg_replace_callback;
use function rtrim;
use function str_ends_with;
use function strlen;
use function strpos;
use function substr;

class Message
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

    /** @var int */
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
        ?string $backtrace,
        ?float $duration,
        int $bell,
        float $time,
        int $counter,
        int $processId,
        ?int $threadId
    ) {
        $this->type = $type;
        $this->payload = $payload;
        $this->backtrace = $backtrace;
        $this->duration = $duration;
        $this->bell = $bell;
        $this->time = $time;
        $this->counter = $counter;
        $this->processId = $processId;
        $this->threadId = $threadId;
    }

    /**
     * @param int|bool $bell
     */
    public static function create(
        int $type,
        string $payload,
        ?string $backtrace = null,
        ?float $duration = null,
        $bell = 0
    ): self
    {
        // todo: temporary
        if (str_ends_with($payload, "\n")) {
            throw new Exception('Payload should not end with new line.');
        }
        // todo: somehow some special chars are avoiding detection and escaping :E
        $char = Str::isBinary($payload, self::ALLOWED_CHARS);
        if ($char !== null) {
            $hex = dechex(ord($char));
            $pos = strpos($payload, $char);
            Debugger::send(self::ERROR, "Payload can not contain special characters. Found \\x{$hex} at position {$pos}.");
            $payload = preg_replace_callback("~[\\x00-\\x08\\x0B-\\x1A\\x1C-\\x1F]~", static function (array $m): string {
                return '\x' . Str::charToHex($m[0]);
            }, $payload);
        }
        if ($backtrace !== null) {
            $char = Str::isBinary($backtrace, self::ALLOWED_CHARS);
            if ($char !== null) {
                $hex = dechex(ord($char));
                $pos = strpos($payload, $char);
                Debugger::send(self::ERROR, "Backtrace can not contain special characters. Found \\x{$hex} at position {$pos}.");
                $backtrace = preg_replace_callback("~[\\x00-\\x08\\x0B-\\x1A\\x1C-\\x1F]~", static function (array $m): string {
                    return '\x' . Str::charToHex($m[0]);
                }, $backtrace);
            }
        }
        if (strlen($payload) > Debugger::$maxMessageLength) {
            $payload = substr($payload, 0, Debugger::$maxMessageLength) . Dumper::exceptions(' ... ');
        }

        if ($type !== self::OUTPUT_WIDTH) {
            $time = microtime(true);
            $counter = ++self::$count;
            [$processId, $threadId] = System::getProcessAndThreadId();
        } else {
            $time = 0.0;
            $counter = 0;
            $processId = 0;
            $threadId = null;
        }

        return new self($type, $payload, $backtrace, $duration, intval($bell), $time, $counter, $processId, $threadId);
    }

    /**
     * Serialized message structure is marked by ASCII control characters:
     * - "Start of Heading" \x01
     *   - int $type, int $bell, int $counter, float $time, float $duration, int $processId, int $threadId separated by "Unit Separator" \x1F
     * - "Start of Text" \x02
     *   - formatted main message
     * - "End of Text" \x03
     *   - formatted backtrace
     * - "End of Transmission" \x04
     */
    public function encode(): string
    {
        return "\x01{$this->type}\x1F{$this->bell}\x1F{$this->counter}\x1F{$this->time}\x1F{$this->duration}\x1F{$this->processId}\x1F{$this->threadId}"
            . "\x02{$this->payload}\x03{$this->backtrace}\x04";
    }

    public static function decode(string $data): self
    {
        $parts = explode("\x02", $data);
        $head = ltrim($parts[0], "\x01");
        $rest = $parts[1] ?? '';

        $parts = explode("\x1F", $head);
        $type = (intval($parts[0])) ?: self::DUMP;
        $bell = intval($parts[1] ?? 0);
        $counter = intval($parts[2] ?? 0);
        $time = floatval($parts[3] ?? microtime(true));
        $duration = floatval($parts[4] ?? 0.0);
        $processId = intval($parts[5] ?? 0);
        $threadId = isset($parts[6]) ? intval($parts[6]) : null;

        $parts = explode("\x03", $rest);
        $payload = $parts[0];
        $backtrace = rtrim($parts[1] ?? '', "\x04");

        return new self($type, $payload, $backtrace, $duration, $bell, $time, $counter, $processId, $threadId);
    }

}
