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

use Dogma\Debug\DebugClient;
use Dogma\Debug\Dumper;

$_dogma_debug_start = microtime(true);

if (!class_exists(Dumper::class)) {
    require_once __DIR__ . '/src/Debug/Str.php';
    require_once __DIR__ . '/src/Debug/Ansi.php';
    require_once __DIR__ . '/src/Debug/Http.php';
    require_once __DIR__ . '/src/Debug/Packet.php';
    require_once __DIR__ . '/src/Debug/System.php';
    require_once __DIR__ . '/src/Debug/CallstackFrame.php';
    require_once __DIR__ . '/src/Debug/Callstack.php';
    require_once __DIR__ . '/src/Debug/DebugClient.php';
    DebugClient::$timers['total'] = $_dogma_debug_start;
    unset($_dogma_debug_start);

    require_once __DIR__ . '/src/Debug/Cp437.php';
    require_once __DIR__ . '/src/Debug/DumperFormatters.php';
    require_once __DIR__ . '/src/Debug/DumperHandlers.php';
    require_once __DIR__ . '/src/Debug/DumperHandlersDom.php';
    require_once __DIR__ . '/src/Debug/DumperTraces.php';
    require_once __DIR__ . '/src/Debug/Dumper.php';

    require_once __DIR__ . '/src/Debug/ErrorHandler.php';
    require_once __DIR__ . '/src/Debug/ExceptionHandler.php';
    require_once __DIR__ . '/src/Debug/FileStreamWrapper.php';

    if (is_readable(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }

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
        return DebugClient::dump($value, $depth, $trace);
    }

    /**
     * Remote capture dump
     *
     * @param callable $callback
     * @param int|bool $depth
     * @param int $trace
     * @return string
     */
    function rc(callable $callback, $depth = 5, int $trace = 1): string
    {
        return DebugClient::capture($callback, $depth, $trace);
    }

    /**
     * Remote function/method name dump
     */
    function rf(): void
    {
        DebugClient::function();
    }

    /**
     * Remote label print
     *
     * @param mixed $label
     * @return mixed
     */
    function rl($label)
    {
        return DebugClient::label($label);
    }

    /**
     * Remote timer
     *
     * @param string|int|null $label
     */
    function rt($label = ''): void
    {
        DebugClient::timer($label);
    }

}

?>
