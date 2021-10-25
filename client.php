<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: rl rb rf
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

use Dogma\Debug\DebugClient;
use Dogma\Debug\Dumper;

$_dogma_debug_start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

if (!class_exists(DebugClient::class)) {
    require_once __DIR__ . '/src/tools/Str.php';
    require_once __DIR__ . '/src/tools/Ansi.php';
    require_once __DIR__ . '/src/tools/Http.php';
    require_once __DIR__ . '/src/tools/Request.php';
    require_once __DIR__ . '/src/tools/System.php';
    require_once __DIR__ . '/src/tools/Cp437.php';
    require_once __DIR__ . '/src/Packet.php';
    require_once __DIR__ . '/src/CallstackFrame.php';
    require_once __DIR__ . '/src/Callstack.php';
    require_once __DIR__ . '/src/DebugClient.php';

    require_once __DIR__ . '/src/dumper/DumperFormatters.php';
    require_once __DIR__ . '/src/dumper/DumperHandlers.php';
    require_once __DIR__ . '/src/dumper/DumperHandlersDom.php';
    require_once __DIR__ . '/src/dumper/DumperTraces.php';
    require_once __DIR__ . '/src/dumper/Dumper.php';

    require_once __DIR__ . '/src/handlers/ErrorHandler.php';
    require_once __DIR__ . '/src/handlers/ExceptionHandler.php';
    require_once __DIR__ . '/src/handlers/IoHandler.php';
    require_once __DIR__ . '/src/handlers/FileHandler.php';
    require_once __DIR__ . '/src/handlers/SqlHandler.php';

    DebugClient::$timers['total'] = $_dogma_debug_start;
    unset($_dogma_debug_start);

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
     * Remote backtrace dump
     *
     * @param int|null $length
     * @param int|null $argsDepth
     * @param int[] $lines
     */
    function rb(?int $length = null, ?int $argsDepth = null, array $lines = []): void
    {
        DebugClient::backtrace($length, $argsDepth, $lines);
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

    // do not configure client when the current process is actually a starting server
    if (is_readable(__DIR__ . '/config.php') && $_SERVER['PHP_SELF'] !== 'server.php') {
        require_once __DIR__ . '/config.php';
    }

}

?>