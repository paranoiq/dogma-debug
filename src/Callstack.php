<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.PHP.GlobalKeyword.NotAllowed

namespace Dogma\Debug;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use function array_reverse;
use function array_unshift;
use function count;
use function debug_backtrace;
use function explode;
use function floatval;
use function in_array;
use function intval;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strval;
use const PHP_SAPI;

/**
 * In CallstackFrame a function is always paired with file and line in which it is defined,
 * unlike result of debug_backtrace(), where function is paired with file and line from which it was called.
 * (Callstack says "now you are in this file, on this line, inside this function",
 * where backtrace says "now you are in this file, on this line, and you are calling that function")
 *
 * This makes Callstack easier to comprehend and easier to filter (skip) some frames.
 *
 * For example: this backtrace:
 * [
 *   { file: foo.php, function: bar() }, // foo() calls bar()
 *   { file: index.php, function: foo() }, // index.php calls foo()
 * ]
 * is transformed to this Callstack:
 * [
 *   { file: bar.php, function: bar() }, // inside bar(), called from foo(); file and line for bar() is inferred from reflection
 *   { file: foo.php, function: foo() }, // inside foo(), called from index.php
 *   { file: index.php, function: null }, // inside no function here, thus function is null
 * ]
 *
 * @phpstan-type PhpBacktraceItem array{file?: ?string, line?: int, function?: ?string, class?: ?class-string, object?: ?object, type?: '->'|'::'|null, args?: array<int, mixed>|false, number?: int, time?: float, memory?: int}
 */
class Callstack
{

    // non-function names used as functions
    public const INCLUDES = ['include', 'include_once', 'require', 'require_once'];

    /** @var list<CallstackFrame> */
    public $frames;

    /**
     * @param list<CallstackFrame> $frames
     */
    public function __construct(array $frames)
    {
        $this->frames = $frames;
    }

    /**
     * @param list<string> $filters
     */
    public static function get(array $filters = [], bool $filter = true): self
    {
        /** @var list<PhpBacktraceItem> $trace */
        $trace = debug_backtrace();

        return self::fromBacktrace($trace, $filters, $filter);
    }

    /**
     * @param list<string> $filters
     */
    public static function fromThrowable(Throwable $e, array $filters = [], bool $filter = true): self
    {
        /** @var list<PhpBacktraceItem> $trace */
        $trace = $e->getTrace();
        if ($trace) {
            $file = $e->getFile();
            $line = $e->getLine();
            if ($file !== null) {
                array_unshift($trace, ['file' => $file, 'line' => $line]);
            }

            return self::fromBacktrace($trace, $filters, $filter);
        }

        $that = new self([new CallstackFrame($e->getFile(), $e->getLine())]);

        return $filter && $filters ? $that->filter($filters) : $that;
    }

    /**
     * @param PhpBacktraceItem[] $trace
     * @param list<string> $filters
     */
    public static function fromBacktrace(array $trace, array $filters = [], bool $filter = true): self
    {
        global $argv;

        $frames = [];
        for ($i = -1; $i <= count($trace); $i++) {
            $j = $i + 1;
            $file = isset($trace[$i]['file']) ? str_replace('\\', '/', $trace[$i]['file']) : null;
            $line = $trace[$i]['line'] ?? null;
            $function = $trace[$j]['function'] ?? null;
            $class = $trace[$j]['class'] ?? null;
            $object = $trace[$j]['object'] ?? null;
            $args = $trace[$j]['args'] ?? ($i === count($trace) && $function === null && PHP_SAPI === 'cli' ? $argv : []);
            $type = $trace[$j]['type'] ?? null;

            if ($class === self::class) {
                // always skip self
                continue;
            } elseif ($function === null && $file === null) {
                // on some internal functions that call back
                continue;
            }

            if ($function !== null && str_contains($function, '{closure:')) {
                // too long and redundant
                $function = preg_replace('~[^:{]+\\{closure:.*:([0-9]+)~', '{closure:\\1', $function);
            }

            // fill starting line of function for last frame
            if ($file === null && $function !== null) {
                if ($class !== null) {
                    $ref = new ReflectionMethod($class, $function);
                    $file = $ref->getFileName();
                    $line = $ref->getStartLine();
                } elseif (!in_array($function, self::INCLUDES, true)) {
                    $ref = new ReflectionFunction($function);
                    $file = $ref->getFileName();
                    $line = $ref->getStartLine();
                }
                $file = $file ?: null;
                if ($line === false) {
                    $line = null;
                }
            }

            // from OOM message
            $number = $trace[$i]['number'] ?? count($trace) - $i;
            $time = $trace[$j]['time'] ?? null;
            $memory = $trace[$j]['memory'] ?? null;
            if ($number === 0 && $line === 0) {
                continue;
            }

            $frames[] = new CallstackFrame($file, $line, $class, $function, $type, $object, $args, $number, $time, $memory);
        }

        $that = new self($frames);

        return $filter && $filters ? $that->filter($filters) : $that;
    }

    public static function fromOutOfMemoryMessage(string $message): self
    {
        $message = Str::normalizeLineEndings($message);

        /** @var list<PhpBacktraceItem> $frames */
        $frames = [];
        foreach (explode("\n", $message) as $line) {
            if (!preg_match('~\s+([0-9]+\\.[0-9]+)\s+([0-9]+)\s+([0-9]+)\\.\s+([^(]+)\\(\\)\s+(.*)~', $line, $m)) {
                continue;
            }

            [, $time, $memory, $number, $classFunction, $fileLine] = $m;

            $type = str_contains($classFunction, '->') ? '->' : (str_contains($classFunction, '::') ? '::' : null);
            $class = $function = null;
            if ($type !== null) {
                /** @var class-string $class */
                [$class, $function] = explode($type, $classFunction);
            } elseif ($classFunction !== '{main}') {
                $function = $classFunction;
            }

            [$file, $line] = Str::splitByLast($fileLine, ':');

            $frames[] = [
                'file' => $file,
                'line' => intval($line),
                'function' => strval($function),
                'class' => $class,
                'object' => null,
                'type' => $type,
                'args' => false,
                'number' => intval($number) - 1,
                'time' => floatval($time),
                'memory' => intval($memory),
            ];
        }

        return self::fromBacktrace(array_reverse($frames));
    }

    /**
     * @param list<string> $filters
     */
    public function filter(array $filters): self
    {
        $frames = [];
        foreach ($this->frames as $frame) {
            $name = $frame->export();
            foreach ($filters as $filter) {
                if (preg_match($filter, $name)) {
                    continue 2;
                }
            }
            $frames[] = $frame;
        }

        // do not apply filters when result is empty
        if ($frames === []) {
            return $this;
        }

        return new self($frames);
    }

    public function last(): CallstackFrame
    {
        return $this->frames[0];
    }

    public function previous(): ?CallstackFrame
    {
        return $this->frames[1] ?? null;
    }

}
