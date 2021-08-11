<?php declare(strict_types = 1);

namespace Dogma\Debug;

use function array_slice;
use function explode;
use function file_get_contents;
use function implode;
use function is_file;
use function is_readable;
use function preg_match;
use function str_replace;
use function str_split;
use function strpos;
use function substr;
use function trim;

trait DumperTraces
{

    /** @var int */
    public static $traceLength = 1;

    /** @var bool - displaying class and method where we are, not what we are calling, so it is shifted in comparison with debug_backtrace() */
    public static $traceDetails = false;

    /** @var array<string|null> ($class, $method) */
    public static $traceSkip = [
        [null, 'd'],
        [null, 'rd'],
        [null, 'rf'],
        [null, 'rl'],
        [null, 't'],
        [self::class, null],
        [Assert::class, null],
        [FileStreamWrapper::class, null],

        // io origins
        [null, 'include'],
        [null, 'include_once'],
        [null, 'require'],
        [null, 'require_once'],
        [null, 'Composer\Autoload\includeFile'],
        ['Composer\Autoload\ClassLoader', 'loadClass'],
    ];

    /** @var string - common path prefix to remove from all paths */
    public static $trimPathPrefix = '';

    public static function trimPathPrefixBefore(string $string): void
    {
        $dir = str_replace('\\', '/', __DIR__);

        self::$trimPathPrefix = substr($dir, 0, strpos($dir, $string));
    }

    /**
     * @param mixed[] $traces
     * @return string|null
     */
    public static function extractName(array $traces): ?string
    {
        foreach ($traces as $i => $trace) {
            if (self::skipTrace($traces, $i)) {
                continue;
            }

            $filePath = $trace['file'] ?? null;
            if ($filePath === null || !is_file($filePath) || !is_readable($filePath)) {
                return null;
            }
            $source = file_get_contents($filePath);
            $lines = explode("\n", (string)$source);
            $lineIndex = $trace['line'] - 1;
            if (!isset($lines[$lineIndex])) {
                return null;
            }
            $line = $lines[$lineIndex];

            $expression = self::findExpression($line);

            if ($expression !== null) {
                if (preg_match('/null|false|true|nan|-?inf/i', $expression)) {
                    return null;
                }

                return $expression;
            }
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

        return implode('', array_slice($chars, 0, $i));
    }

    public static function formatTrace(array $traces, ?int $order): string
    {
        if (self::$traceLength === 0) {
            return '';
        }

        $result = '';
        $n = 0;
        foreach ($traces as $i => $trace) {
            if (self::skipTrace($traces, $i)) {
                continue;
            }

            $result .= self::formatTraceLine($traces, $i, $order);
            $order = null;

            $n++;
            if ($n >= self::$traceLength) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param mixed[][] $traces
     */
    public static function formatTraceLine(array $traces, int $i, ?int $order): ?string
    {
        $filePath = $traces[$i]['file'] ?? null;
        if ($filePath === null) {
            return null;
        }

        $line = $traces[$i]['line'] ?? '?';
        $classMethod = '';
        if (self::$traceDetails && isset($traces[$i + 1]['function'])) {
            $classMethod = ' ' . self::symbol('/') . ' ' . (isset($traces[$i + 1]['class']) ? self::nameDim($traces[$i + 1]['class']) . self::symbol('::') : '') . self::nameDim($traces[$i + 1]['function']) . self::bracket('()');
        }
        $order = $order ? self::info(" ($order)") : '';

        return self::info("^--- in ") . self::fileLine($filePath, $line) . $classMethod . $order . "\n";
    }

    public static function skipTrace(array $traces, int $i): bool
    {
        $class = $traces[$i + 1]['class'] ?? null;
        $method = $traces[$i + 1]['function'] ?? null;
        foreach (self::$traceSkip as [$skipClass, $skipMethod]) {
            if ($class === $skipClass && ($method === $skipMethod || $skipMethod === null)) {
                return true;
            }
        }

        return false;
    }

}