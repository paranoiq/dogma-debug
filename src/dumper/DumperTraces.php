<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_slice;
use function debug_backtrace;
use function end;
use function implode;
use function in_array;
use function preg_match;
use function str_replace;
use function str_split;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

trait DumperTraces
{

    /** @var int */
    public static $traceLength = 1;

    /** @var bool - show class, method, arguments */
    public static $traceDetails = true;

    /** @var int */
    public static $traceArgsDepth = 0;

    /** @var int[] - count of lines of code shown for each filtered frame. [5] means 5 lines for first, 0 for others... */
    public static $traceCodeLines = [5];

    /** @var array<string|null> ($class, $method) */
    public static $traceSkip = [
        [null, 'ld'],
        [null, 'rd'],
        [null, 'rc'],
        [null, 'rb'],
        [null, 'rf'],
        [null, 'rl'],
        [null, 'rt'],
        [self::class, null],
        [DebugClient::class, null],
        [Callstack::class, null],
        [ErrorHandler::class, null],
        [ExceptionHandler::class, null],
        [FileHandler::class, null],
        [IoHandler::class, null],
        [SqlHandler::class, null],
        [Assert::class, null],

        // proxies
        [null, 'call_user_func'],
        [null, 'call_user_func_array'],

        // io origins
        [null, 'Composer\Autoload\includeFile'],
        ['Composer\Autoload\ClassLoader', 'loadClass'],
    ];

    /** @var string - common path prefix to remove from all paths */
    public static $trimPathPrefix = '';

    public static function trimPathPrefixBefore(string $string): void
    {
        $traces = debug_backtrace();
        $trace = end($traces);
        $dir = $trace ? ($trace['file'] ?? __DIR__) : __DIR__;
        $dir = str_replace('\\', '/', $dir);

        $end = strpos($dir, $string);
        if ($end) {
            self::$trimPathPrefix = substr($dir, 0, $end);
        }
    }

    public static function trimPathPrefixAfter(string $string): void
    {
        $traces = debug_backtrace();
        $trace = end($traces);
        $dir = $trace ? ($trace['file'] ?? __DIR__) : __DIR__;
        $dir = str_replace('\\', '/', $dir);

        $end = strpos($dir, $string);

        if ($end) {
            self::$trimPathPrefix = substr($dir, 0, $end + strlen($string) + 1);
        }
    }

    public static function extractName(Callstack $callstack): ?string
    {
        $callstack = $callstack->filter(self::$traceSkip);
        foreach ($callstack->frames as $frame) {
            $line = $frame->getLineCode();
            if ($line === null) {
                continue;
            }

            return self::findExpression($line);
        }

        return null;
    }

    public static function findExpression(string $line): ?string
    {
        $start = strpos($line, '(');
        if ($start === false) {
            return null;
        }
        $line = trim(substr($line, $start + 1));

        if ($line[0] === '"' || $line[0] === "'" || $line[0] === '-' || preg_match('/^[0-9]/', $line)) {
            // literal
            return null;
        }

        $chars = str_split($line);
        $pars = 1;
        $bras = 0;
        foreach ($chars as $i => $char) {
            switch ($char) {
                case '(': $pars++; break;
                case ')': $pars--; break;
                case '[': $bras++; break;
                case ']': $bras--; break;
            }
            if ($pars === 1 && $bras === 0 && $char === ',') {
                break;
            } elseif ($pars === 0 && $bras === 0) {
                break;
            }
        }

        $expression = implode('', array_slice($chars, 0, $i));
        if (in_array(strtolower($expression), ['true', 'false', 'null', 'nan', 'inf'], true)) {
            return null;
        }

        // we need to go deeper!
        if (preg_match('~(?:ld|rd|rc|rf|rl|rt|Debug(?:Client)?::[a-zA-Z]+)\(.*\)~', $expression)) {
            return self::findExpression($expression);
        }

        return $expression;
    }

    /**
     * @param int[] $codeLines
     */
    public static function formatCallstack(Callstack $callstack, ?int $length = null, ?int $argsDepth = null, ?array $codeLines = null): string
    {
        $length = $length ?? self::$traceLength;
        if ($length === 0) {
            return '';
        }

        $oldDepth = self::$maxDepth;
        self::$maxDepth = ($argsDepth ?? self::$traceArgsDepth);

        $codeLines = $codeLines ?? self::$traceCodeLines;

        $results = [];
        $n = 0;
        foreach ($callstack->frames as $frame) {
            $result = self::formatFrame($frame);

            if (isset($codeLines[$n]) && $frame->file !== null) {
                $lines = (int) floor($codeLines[$n] / 2);
                $lines = $frame->getLinesAround($lines, $lines);
                foreach ($lines as $i => $line) {
                    $lines[$i] = Ansi::lgray($i . ':') . ' ' . ($i === $frame->line ? Ansi::white($line) : Ansi::dyellow($line));
                }
                $result .= "\n" . implode("\n", $lines);
            }

            $results[] = $result;
            $n++;
            if ($n >= $length) {
                break;
            }
        }

        self::$maxDepth = $oldDepth;

        return implode("\n", $results);
    }

    private static function formatFrame(CallstackFrame $frame): string
    {
        $args = '';
        if ($frame->args !== []) {
            $in = self::bracket('[');
            $out = self::bracket(']');
            $args = self::dumpArray($frame->getNamedArgs());
            $args = substr($args, strlen($in), strrpos($args, $out) - strlen($out));
        }

        $classMethod = '';
        if (self::$traceDetails && $frame->function !== null) {
            $classMethod = ' ' . self::symbol('--') . ' '
                . ($frame->class !== null ? self::nameDim($frame->class) . self::symbol('::') : '')
                . self::nameDim($frame->function) . self::bracket('(') . $args . self::bracket(')');
        }

        $fileLine = '';
        if ($frame->file !== null && $frame->line !== null) {
            $fileLine = self::fileLine($frame->file, $frame->line);
        }

        return self::info("^--- in ") . $fileLine . $classMethod;
    }

}