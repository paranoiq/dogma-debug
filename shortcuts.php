<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable PSR2.Files.EndFileNewline.NoneFound
// phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline

use Dogma\Debug\Debugger;
use Dogma\Debug\Dumper;

if (!function_exists('rd')) {
    /**
     * Local dump
     *
     * @param mixed $value
     * @return mixed
     */
    function ld($value, ?int $maxDepth = null, ?int $traceLength = null)
    {
        Debugger::print(Dumper::dump($value, $maxDepth, $traceLength));

        return $value;
    }

    /**
     * Remote dump implemented with native var_dump() + some colors
     *
     * @param mixed $value
     * @return mixed
     */
    function lvd($value, bool $colors = true)
    {
        Debugger::print(Dumper::varDump($value, $colors));

        return $value;
    }

    /**
     * Remote dump
     *
     * @param mixed $value
     * @return mixed
     */
    function rd($value, ?int $maxDepth = null, ?int $traceLength = null)
    {
        return Debugger::dump($value, $maxDepth, $traceLength);
    }

    /**
     * Remote dump implemented with native var_dump() + some colors
     *
     * @param mixed $value
     * @return mixed
     */
    function rvd($value, bool $colors = true)
    {
        return Debugger::varDump($value, $colors);
    }

    /**
     * Remote capture dump
     */
    function rc(callable $callback, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        return Debugger::capture($callback, $maxDepth, $traceLength);
    }

    /**
     * Remote backtrace dump
     *
     * @param int[] $lines
     */
    function rb(?int $length = null, ?int $argsDepth = null, array $lines = []): void
    {
        Debugger::callstack($length, $argsDepth, $lines);
    }

    /**
     * Remotely print function/method name
     */
    function rf(): void
    {
        Debugger::function();
    }

    /**
     * Remote label print
     *
     * @param string|int|float|bool $label
     * @return string|int|float|bool
     */
    function rl($label, ?string $name = null)
    {
        return Debugger::label($label, $name);
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
}

if (!require_once(__DIR__ . '/client.php')) {
    return;
}

?>