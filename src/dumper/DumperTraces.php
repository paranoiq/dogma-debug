<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_keys;
use function array_slice;
use function array_values;
use function floor;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_split;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

trait DumperTraces
{

    /**
     * @return non-empty-string|true|null
     */
    public static function findExpression(Callstack $callstack)
    {
        $callstack = $callstack->filter(self::$traceFilters);
        foreach ($callstack->frames as $frame) {
            $line = $frame->getLineCode();
            if ($line === null) {
                continue;
            }

            return self::getExpression($line);
        }

        return null;
    }

    /**
     * @param non-empty-string $line
     * @return non-empty-string|true|null
     */
    public static function getExpression(string $line)
    {
        $start = strpos($line, '(');
        if ($start === false) {
            return null;
        }
        $line = trim(substr($line, $start + 1));

        if ($line[0] === '"' || $line[0] === "'" || $line[0] === '-' || preg_match('/^[0-9]/', $line)) {
            // literal
            return true;
        }

        $chars = str_split($line);
        $pars = 1;
        $bras = 0;
        foreach ($chars as $i => $char) {
            switch ($char) {
                case '(':
                    $pars++;
                    break;
                case ')':
                    $pars--;
                    break;
                case '[':
                    $bras++;
                    break;
                case ']':
                    $bras--;
                    break;
            }
            if ($pars === 1 && $bras === 0 && $char === ',') {
                break;
            } elseif ($pars === 0 && $bras === 0) {
                break;
            }
        }

        $expression = implode('', array_slice($chars, 0, $i));
        if (in_array(strtolower($expression), ['true', 'false', 'null', 'nan', 'inf'], true)) {
            return true;
        }

        if ($expression === '') {
            return null;
        }

        // we need to go deeper!
        if (preg_match('~(?:ld|rd|rc|rf|rl|rt|Debug(?:Client)?::[a-zA-Z]+)\(.*\)~', $expression)) {
            return self::getExpression($expression);
        }

        return $expression;
    }

    public static function formatCallstack(
        Callstack $callstack,
        ?int $length = null,
        ?int $argsDepth = null,
        ?int $codeLines = null,
        ?int $codeDepth = null
    ): string
    {
        $length = $length ?? self::$traceLength;
        if ($length === 0) {
            return '';
        }

        $oldDepth = self::$maxDepth;
        self::$maxDepth = ($argsDepth ?? self::$traceArgsDepth);

        $codeLines = $codeLines ?? self::$traceCodeLines;
        $codeDepth = $codeDepth ?? self::$traceCodeDepth;

        $prevArgs = '';
        $results = [];
        $n = 0;
        foreach ($callstack->frames as $frame) {
            [$result, $args] = self::formatFrame($frame, $prevArgs);
            if ($result === null) {
                continue;
            }
            $prevArgs = $args;

            if ($codeLines > 0 && $n < $codeDepth && $frame->file !== null) {
                $lines = (int) floor($codeLines / 2);
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

    /**
     * @return array{string|null, string}
     */
    private static function formatFrame(CallstackFrame $frame, string $prevArgs): array
    {
        $skipArgs = ' ' . self::exceptions('...') . ' ';
        $unknownArgs = ' ' . self::exceptions('???') . ' ';

        $currentArgs = '';
        if ($frame->args === false) {
            $currentArgs = $unknownArgs;
        } elseif ($frame->args !== [] && self::$maxDepth === 0) {
            $currentArgs = $skipArgs;
        } elseif ($frame->args !== []) {
            $currentArgs = self::dumpArguments($frame->getNamedArgs());
        }

        $same = $currentArgs === $prevArgs && $currentArgs !== $skipArgs && $currentArgs !== $unknownArgs && $currentArgs !== '';
        $args = $same ? ' ' . self::exceptions('^ same') . ' ' : $currentArgs;

        $classMethod = '';
        if (self::$traceDetails && $frame->function !== null) {
            $classMethod = ($frame->class !== null ? self::nameDim($frame->class) . self::symbol('::') : '')
                . self::nameDim($frame->function) . self::bracket('(') . $args . self::bracket(')');
        }

        $fileLine = '';
        if ($frame->file !== null && $frame->line !== null) {
            $fileLine = self::fileLine(self::trimPath(self::normalizePath($frame->file)), $frame->line);
        }

        $separator = '';
        if ($fileLine && $classMethod) {
            $separator = ' ' . self::symbol('--') . ' ';
        }

        if ($fileLine === '' && $classMethod === '') {
            return [null, $currentArgs];
        }

        $timeMemory = '';
        if ($frame->time || $frame->memory) {
            $timeMemory = ' ';
            $timeMemory .= $frame->time ? self::time(Units::time($frame->time)) : '';
            $timeMemory .= $frame->time && $frame->memory ? ' ' : '';
            $timeMemory .= $frame->memory ? self::memory(Units::memory($frame->memory)) : '';
        }

        $number = $frame->number !== null && self::$traceNumbered ? ' ' . $frame->number : '';

        return [self::info("^---$number in ") . $fileLine . $separator . $classMethod . $timeMemory, $currentArgs];
    }

    /**
     * Normalize file path:
     * - changes \ to /
     * - removes . dirs
     * - resolves .. dirs if possible
     * - removes duplicate /
     * - removes ending /
     * - removes starting / from relative paths
     * - keeps / after uri scheme intact (https://en.wikipedia.org/wiki/File_URI_scheme)
     */
    public static function normalizePath(string $path, bool $relative = false): string
    {
        $path = str_replace('\\', '/', $path);

        $patterns = [
            '~/\./~' => '/', // remove . dirs
            '~(?<!:)(?<!:/)/{2,}~' => '/', // remove duplicate /, but keep up to three / after uri scheme
            '~([^/\.]+/(?R)*\.\./?)~' => '', // resolve .. dirs
        ];
        do {
            $previous = $path;
            $path = preg_replace(array_keys($patterns), array_values($patterns), $path);
        } while ($path !== $previous);

        $patterns = [
            '~/+\.?$~' => '', // end /.
            '~^\.$~' => '', // sole .
            '~^/\.~' => '.', // /.. on start
        ];
        $path = preg_replace(array_keys($patterns), array_values($patterns), $path);

        return $relative && $path[0] === '/' ? substr($path, 1) : $path;
    }

    public static function trimPath(string $path): string
    {
        foreach (self::$trimPathPrefixes as $prefix) {
            $after = preg_replace($prefix, '', $path);
            if ($after !== $path) {
                return $after;
            }
        }

        return $path;
    }

}
