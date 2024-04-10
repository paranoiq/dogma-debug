<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable PSR2.Files.EndFileNewline.NoneFound
// phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline

use Dogma\Debug\Ansi;
use Dogma\Debug\Callstack;
use Dogma\Debug\Debugger;
use Dogma\Debug\Diff;
use Dogma\Debug\Dumper;
use Dogma\Debug\Message;

if (!function_exists('rd')) {
    /**
     * Local dump
     *
     * @template T
     * @param T $value
     * @return T
     */
    function ld($value, ?int $maxDepth = null, ?int $traceLength = null)
    {
        Debugger::print(Dumper::dump($value, $maxDepth, $traceLength));

        return $value;
    }

    /**
     * Remote dump implemented with native var_dump() + some colors
     *
     * @template T
     * @param T $value
     * @return T
     */
    function lvd($value, bool $colors = true)
    {
        Debugger::print(Dumper::varDump($value, $colors));

        return $value;
    }

    /**
     * Remote dump
     *
     * @template T
     * @param T $value
     * @return T
     */
    function rd($value, ?int $maxDepth = null, ?int $traceLength = null, ?string $name = null)
    {
        return Debugger::dump($value, $maxDepth, $traceLength, $name);
    }

    /**
     * Remote dump implemented with native var_dump() + some colors
     *
     * @template T
     * @param T $value
     * @return T
     */
    function rvd($value, bool $colors = true)
    {
        return Debugger::varDump($value, $colors);
    }

    /**
     * Remote dump exception (formatted same way as when thrown)
     *
     * @template T of Throwable
     * @param T $exception
     * @return T
     */
    function re(Throwable $exception): Throwable
    {
        return Debugger::dumpException($exception);
    }

    /**
     * Remote diff dump
     */
    function rdf(string $a, string $b): void
    {
        $message = Ansi::white('diff:') . ' ' . Diff::cliDiff($a, $b);

        Debugger::send(Message::DUMP, $message);
    }

    /**
     * Remote capture dump
     */
    function rc(callable $callback, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        return Debugger::capture($callback, $maxDepth, $traceLength);
    }

    /**
     * Remotely print function/method name
     */
    function rf(bool $withLocation = true, bool $withDepth = false, ?string $function = null): void
    {
        Debugger::function($withLocation, $withDepth, $function);
    }

    /**
     * Remote backtrace dump
     * @phpstan-import-type PhpBacktraceItem from Callstack
     * @param Callstack|PhpBacktraceItem[]|null $callstack
     */
    function rb(?int $length = null, ?int $argsDepth = null, ?int $codeLines = null, ?int $codeDepth = null, $callstack = null): void
    {
        Debugger::callstack($length, $argsDepth, $codeLines, $codeDepth, $callstack);
    }

    /**
     * Remote label print
     *
     * @param string|int|float|bool|object|null $label
     * @return string|int|float|bool|object|null
     */
    function rl($label, ?string $name = null, ?string $color = null)
    {
        return Debugger::label($label, $name, $color);
    }

    /**
     * Remote timer. Shows time since previous event or from start of the request
     *
     * @param string|int|null $name
     */
    function rt($name = ''): void
    {
        Debugger::timer($name);
    }

    /**
     * Remote memory report. Shows memory consumed/freed from previous event or total
     *
     * @param string|int|null $name
     */
    function rm($name = ''): void
    {
        Debugger::memory($name);
    }

    /**
     * Remote write. Write raw formatted string to debug output
     */
    function rw(string $data): void
    {
        Debugger::raw($data);
    }

    /**
     * Extract private property from an object
     *
     * @param object $object
     * @return mixed
     */
    function xp($object, string $property, ?string $class = null)
    {
        $prop = new ReflectionProperty($class ?? get_class($object), $property);
        if (PHP_VERSION_ID <= 80000) {
            $prop->setAccessible(true);
        }

        return $prop->getValue($object);
    }
}

if (!require_once(__DIR__ . '/client.php')) {
    return;
}

?>