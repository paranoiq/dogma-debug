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
use const PHP_SAPI;
use function count;
use function debug_backtrace;
use function in_array;
use function preg_match;
use function str_replace;

/**
 * In CallstackFrame a function is always paired with file and line in which it is defined,
 * unlike result of debug_backtrace(), where function is paired with file and line from which it was called.
 * (Callstack says "now you are in this file, on this line, inside this function",
 * where backtrace says "now you are in this file, on this line, and you are calling that function")
 *
 * This makes Callstack more easy to comprehend and more easy to filter (skip) some frames.
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
 * @phpstan-type PhpBacktraceItem array{file?: string, line?: int, function?: string, class?: class-string, object?: object, type?: '->'|'::'|null, args?: array<int, mixed>}
 */
class Callstack
{

    // non-function names used as functions
    public const INCLUDES = ['include', 'include_once', 'require', 'require_once'];

    /** @var CallstackFrame[] */
    public $frames;

    /**
     * @param CallstackFrame[] $frames
     */
    public function __construct(array $frames)
    {
        $this->frames = $frames;
    }

    public static function get(): self
    {
        return self::fromBacktrace(debug_backtrace());
    }

    public static function fromThrowable(Throwable $e): self
    {
        $trace = $e->getTrace();
        if ($trace) {
            $file = $e->getFile();
            $line = $e->getLine();
            if ($file !== null) {
                array_unshift($trace, ['file' => $file, 'line' => $line]);
            }

            return self::fromBacktrace($trace);
        }

        return new self([new CallstackFrame($e->getFile(), $e->getLine())]);
    }

    /**
     * @param PhpBacktraceItem[] $trace
     * @return self
     */
    public static function fromBacktrace(array $trace): self
    {
        global $argv;

        $frames = [];
        for ($i = 0; $i <= count($trace); $i++) {
            $file = isset($trace[$i]['file']) ? str_replace('\\', '/', $trace[$i]['file']) : null;
            $line = $trace[$i]['line'] ?? null;
            if ($file !== null && $file === str_replace('\\', '/', __FILE__)) {
                // always skip self
                continue;
            }

            $j = $i + 1;
            $function = $trace[$j]['function'] ?? null;
            $class = $trace[$j]['class'] ?? null;
            $object = $trace[$j]['object'] ?? null;
            $args = $trace[$j]['args'] ?? ($i === count($trace) && $function === null && PHP_SAPI === 'cli' ? $argv : []);
            $type = $trace[$j]['type'] ?? null;

            if ($function === null && $file === null) {
                continue;
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

            $frames[] = new CallstackFrame($file, $line, $class, $function, $type, $object, $args);
        }

        return new self($frames);
    }

    /**
     * @param string[] $filters
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
