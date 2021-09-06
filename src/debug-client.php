<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: dt rl pid sapi URI rdm rda rf
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

/**
 * Configure this file as auto-prepended to use shortcuts in any project.
 */

use Dogma\Debug\DebugClient;
use Dogma\Debug\Dumper;
use Dogma\Debug\Colors as C;
use Dogma\Debug\Packet;

if (!class_exists(Dumper::class)) {
    error_reporting(E_ALL);

    require_once __DIR__ . '/Debug/Str.php';
    require_once __DIR__ . '/Debug/Colors.php';
    require_once __DIR__ . '/Debug/Packet.php';
    require_once __DIR__ . '/Debug/DebugClient.php';
    DebugClient::$timers['total'] = microtime(true);

    require_once __DIR__ . '/Debug/Cp437.php';
    require_once __DIR__ . '/Debug/DumperFormatters.php';
    require_once __DIR__ . '/Debug/DumperHandlers.php';
    require_once __DIR__ . '/Debug/DumperHandlersDom.php';
    require_once __DIR__ . '/Debug/DumperTraces.php';
    require_once __DIR__ . '/Debug/Dumper.php';

    require_once __DIR__ . '/Debug/FileStreamWrapper.php';

    /**
     * Local dump
     *
     * @param mixed $value
     * @return mixed
     */
    function ld($value, $depth = 5, int $trace = 1)
    {
        echo Dumper::dump($value, $depth, $trace) . "\n";

        return $value;
    }

    /**
     * Remote dump
     *
     * @param mixed $value
     * @param int|bool $depth
     * @param int $trace
     * @return mixed
     */
    function rd($value, $depth = 5, int $trace = 1)
    {
        $dump = Dumper::dump($value, $depth, $trace);

        DebugClient::remoteWrite(Packet::DUMP, $dump);

        return $value;
    }

    /**
     * Remote capture dump
     *
     * @param callable $callback
     * @param int|bool $depth
     * @param int $trace
     * @return mixed
     */
    function rc(callable $callback, $depth = 5, int $trace = 1)
    {
        ob_start();
        $callback();
        $value = ob_end_clean();

        $dump = Dumper::dump($value, $depth, $trace);

        DebugClient::remoteWrite(Packet::DUMP, $dump);

        return $value;
    }

    /**
     * Remote function/method name dump
     */
    function rf(): void
    {
        $trace = debug_backtrace()[1];
        $class = $trace['class'] ?? null;
        $function = $trace['function'] ?? null;

        if ($class !== null) {
            $class = explode('\\', $class);
            $class = end($class);

            $message = C::color(" $class::$function() ", C::WHITE, C::DRED);
        } else {
            $message = C::color(" $function() ", C::WHITE, C::DRED);
        }

        DebugClient::remoteWrite(Packet::LOCATION,$message);
    }

    /**
     * Remote label print
     *
     * @param mixed $label
     * @return mixed
     */
    function rl($label)
    {
        if ($label === null) {
            $label = 'null';
        } elseif ($label === false) {
            $label = 'false';
        } elseif ($label === true) {
            $label = 'true';
        }
        $message = C::color(" $label ", C::WHITE, C::DRED);

        DebugClient::remoteWrite(Packet::LABEL, $message);

        return $label;
    }

    /**
     * Remote timer
     *
     * @param string|int|null $label
     */
    function rt($label = ''): void
    {
        $label = (string) $label;

        if (isset(DebugClient::$timers[$label])) {
            $start = DebugClient::$timers[$label];
            DebugClient::$timers[$label] = microtime(true);
        } elseif (isset(DebugClient::$timers[null])) {
            $start = DebugClient::$timers[null];
            DebugClient::$timers[null] = microtime(true);
        } else {
            DebugClient::$timers[null] = microtime(true);
            return;
        }

        $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
        $label = $label ? ucfirst($label) : 'Timer';
        $message = C::color(" $label: $time ms ", C::WHITE, C::DGREEN);

        DebugClient::remoteWrite(Packet::TIMER, $message);
    }

}

?>
