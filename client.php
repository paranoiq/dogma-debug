<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable PSR2.Files.ClosingTag.NotAllowed
// phpcs:disable PSR2.Files.EndFileNewline.NoneFound
// phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

use Dogma\Debug\Ansi;
use Dogma\Debug\Callstack;
use Dogma\Debug\CallstackFrame;
use Dogma\Debug\Debugger;
use Dogma\Debug\Dumper;
use Dogma\Debug\FileStreamHandler;
use Dogma\Debug\FtpStreamHandler;
use Dogma\Debug\Http;
use Dogma\Debug\HttpStreamHandler;
use Dogma\Debug\Intercept;
use Dogma\Debug\Packet;
use Dogma\Debug\PharStreamHandler;
use Dogma\Debug\PhpStreamHandler;
use Dogma\Debug\Request;
use Dogma\Debug\Str;
use Dogma\Debug\StreamHandler;
use Dogma\Debug\StreamHandlerShared;
use Dogma\Debug\System;
use Dogma\Debug\ZlibStreamHandler;

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
    require_once __DIR__ . '/src/tools/RedisParser.php';
    require_once __DIR__ . '/src/tools/Resources.php';
    require_once __DIR__ . '/src/tools/Cp437.php';
    require_once __DIR__ . '/src/Packet.php';
    require_once __DIR__ . '/src/CallstackFrame.php';
    require_once __DIR__ . '/src/Callstack.php';
    require_once __DIR__ . '/src/Intercept.php';
    require_once __DIR__ . '/src/Debugger.php';

    require_once __DIR__ . '/src/dumper/DumperFormatters.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersDom.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersDogma.php';
    require_once __DIR__ . '/src/dumper/DumperFormattersConsistence.php';
    require_once __DIR__ . '/src/dumper/DumperTraces.php';
    require_once __DIR__ . '/src/dumper/Dumper.php';

    require_once __DIR__ . '/src/handlers/CurlHandler.php';
    require_once __DIR__ . '/src/handlers/DnsHandler.php';
    require_once __DIR__ . '/src/handlers/ErrorHandler.php';
    require_once __DIR__ . '/src/handlers/ExceptionHandler.php';
    require_once __DIR__ . '/src/handlers/FilesHandler.php';
    require_once __DIR__ . '/src/handlers/MailHandler.php';
    require_once __DIR__ . '/src/handlers/MemoryHandler.php';
    require_once __DIR__ . '/src/handlers/OutputHandler.php';
    require_once __DIR__ . '/src/handlers/RedisHandler.php';
    require_once __DIR__ . '/src/handlers/RequestHandler.php';
    require_once __DIR__ . '/src/handlers/ResourcesHandler.php';
    require_once __DIR__ . '/src/handlers/SettingsHandler.php';
    require_once __DIR__ . '/src/handlers/SessionHandler.php';
    require_once __DIR__ . '/src/handlers/ShutdownHandler.php';
    require_once __DIR__ . '/src/handlers/StreamHandler.php';
    require_once __DIR__ . '/src/handlers/SyslogHandler.php';
    require_once __DIR__ . '/src/handlers/SqlHandler.php';

    require_once __DIR__ . '/src/streams/StreamHandlerShared.php';
    require_once __DIR__ . '/src/streams/DataStreamHandler.php';
    require_once __DIR__ . '/src/streams/FileStreamHandler.php';
    require_once __DIR__ . '/src/streams/FtpStreamHandler.php';
    require_once __DIR__ . '/src/streams/HttpStreamHandler.php';
    require_once __DIR__ . '/src/streams/PharStreamHandler.php';
    require_once __DIR__ . '/src/streams/PhpStreamHandler.php';
    require_once __DIR__ . '/src/streams/ZlibStreamHandler.php';

    // force classes to load. otherwise, it may fail in the middle of a stream_wrapper call used by require
    trait_exists(StreamHandlerShared::class);
    array_map('class_exists', [
        Str::class, Ansi::class, Http::class, Request::class, System::class, Packet::class, CallstackFrame::class,
        Callstack::class, Intercept::class, Debugger::class, Dumper::class,
        StreamHandler::class, FileStreamHandler::class, HttpStreamHandler::class, FtpStreamHandler::class,
        PharStreamHandler::class, PhpStreamHandler::class, ZlibStreamHandler::class,
    ]);

    Debugger::setStart($_dogma_debug_start);

    // configure client, unless the current process is actually a starting server
    if (is_readable(__DIR__ . '/debug-config.php') && $_SERVER['PHP_SELF'] !== 'server.php') {
        require_once __DIR__ . '/debug-config.php';
    }

    if (ini_get('allow_url_include')) {
        Debugger::error('Security warning: ini directive allow_url_include should be off.');
    }
}

unset($_dogma_debug_start);

?>