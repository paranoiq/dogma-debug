<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: rl rb rf
// phpcs:disable PSR2.Files.EndFileNewline.NoneFound
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

use Dogma\Debug\Ansi;
use Dogma\Debug\Callstack;
use Dogma\Debug\CallstackFrame;
use Dogma\Debug\Debugger;
use Dogma\Debug\Dumper;
use Dogma\Debug\Http;
use Dogma\Debug\Packet;
use Dogma\Debug\Request;
use Dogma\Debug\Str;
use Dogma\Debug\System;
use Dogma\Debug\Takeover;

$_dogma_debug_start = $_dogma_debug_start ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

// do not load auto-prepended libs when in test cases
// tester loads local copy, which may differ from stable auto-prepended version
$_dogma_debug_prepend = ini_get('auto_prepend_file');
$_dogma_debug_script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
if ($_dogma_debug_prepend === str_replace('\\', '/', __FILE__)
    && substr($_dogma_debug_script, -5) === '.phpt'
    && substr($_dogma_debug_script, 0, (int) strpos($_dogma_debug_script, 'dogma-debug'))
        !== substr($_dogma_debug_prepend, 0, (int) strpos($_dogma_debug_prepend, 'dogma-debug'))
) {
    unset($_dogma_debug_prepend, $_dogma_debug_script);
    return;
}
unset($_dogma_debug_prepend, $_dogma_debug_script);

if (!class_exists(Debugger::class)) {
    require_once __DIR__ . '/src/tools/Str.php';
    require_once __DIR__ . '/src/tools/Ansi.php';
    require_once __DIR__ . '/src/tools/Http.php';
    require_once __DIR__ . '/src/tools/Request.php';
    require_once __DIR__ . '/src/tools/System.php';
    require_once __DIR__ . '/src/tools/Cp437.php';
    require_once __DIR__ . '/src/Packet.php';
    require_once __DIR__ . '/src/CallstackFrame.php';
    require_once __DIR__ . '/src/Callstack.php';
    require_once __DIR__ . '/src/Takeover.php';
    require_once __DIR__ . '/src/Debugger.php';

    require_once __DIR__ . '/src/dumper/DumperFormatters.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersDogma.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersDom.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersConsistence.php';
    require_once __DIR__ . '/src/dumper/DumperTraces.php';
    require_once __DIR__ . '/src/dumper/Dumper.php';

    require_once __DIR__ . '/src/handlers/ErrorHandler.php';
    require_once __DIR__ . '/src/handlers/ExceptionHandler.php';
    require_once __DIR__ . '/src/handlers/ProcessHandler.php';
    require_once __DIR__ . '/src/handlers/ShutdownHandler.php';
    require_once __DIR__ . '/src/handlers/FileHandler.php';
    require_once __DIR__ . '/src/handlers/PharHandler.php';
    require_once __DIR__ . '/src/handlers/OutputHandler.php';
    require_once __DIR__ . '/src/handlers/RequestHandler.php';
    require_once __DIR__ . '/src/handlers/SqlHandler.php';

    // force classes to load. otherwise, it may fail in the middle of a stream_wrapper call used by require
    array_map('class_exists', [
        Str::class, Ansi::class, Http::class, Request::class, System::class, Packet::class, CallstackFrame::class,
        Callstack::class, Takeover::class, Debugger::class, Dumper::class, Debugger::class,
    ]);

    Debugger::$timers['total'] = $_dogma_debug_start;
    unset($_dogma_debug_start);

    /**
     * Local dump
     *
     * @param mixed $value
     * @return mixed
     */
    function ld($value, ?int $maxDepth = null, ?int $traceLength = null)
    {
        echo Dumper::dump($value, $maxDepth, $traceLength) . "\n";

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
        Debugger::backtrace($length, $argsDepth, $lines);
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
     * Remote timer
     *
     * @param string|int|null $name
     */
    function rt($name = ''): void
    {
        Debugger::timer($name);
    }

    // configure client, unless the current process is actually a starting server
    if (is_readable(__DIR__ . '/config.php') && $_SERVER['PHP_SELF'] !== 'server.php') {
        require_once __DIR__ . '/config.php';
    }
}

?>