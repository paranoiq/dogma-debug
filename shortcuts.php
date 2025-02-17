<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */
error_reporting(E_ALL);
ini_set('display_errors', 'on');

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
     * Remote dump implemented with native var_dump() + some colors
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function lvd($value, ...$args)
    {
        Debugger::print(Dumper::varDump($value, ...$args));

        return $value;
    }

    /**
     * Local dump
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function ld($value, ...$args)
    {
        Debugger::print(Dumper::dump($value, ...$args));

        return $value;
    }

    /**
     * Remote dump implemented with native var_dump() + some colors
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function rvd($value, ...$args)
    {
        return Debugger::varDump($value, ...$args);
    }

    /**
     * Remote dump
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function rd($value, ...$args)
    {
        return Debugger::dump($value, ...$args);
    }

    /**
     * Remote dump all (turn off limit on array length)
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function rda($value, ...$args)
    {
        $prev = Dumper::$config->arrayMaxLength;
        Dumper::$config->arrayMaxLength = 1000000000;

        $result = Debugger::dump($value, ...$args);
        Dumper::$config->arrayMaxLength = $prev;

        return $result;
    }

    /**
     * Remote dump table
     *
     * @template T
     * @param T $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function rdt($value, ...$args)
    {
        return Debugger::dumpTable($value, ...$args);
    }

    /**
     * Remote dump exception (formatted same way as when thrown)
     *
     * @template T of Throwable
     * @param T $exception
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     * @return T
     */
    function re(Throwable $exception, ...$args): Throwable
    {
        return Debugger::dumpException($exception, ...$args);
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
     *
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     */
    function rc(callable $callback, ...$args): string
    {
        return Debugger::capture($callback, ...$args);
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
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     */
    function rb($callstack = null, ...$args): void
    {
        Debugger::callstack($callstack, ...$args);
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
     * @param string|int $group
     * @param string|int $name
     */
    function rt($group = '', $name = ''): void
    {
        Debugger::timer($group, $name);
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

if (!require_once __DIR__ . '/client.php') {
    return;
}
