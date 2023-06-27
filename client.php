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
use Dogma\Debug\FileStreamWrapper;
use Dogma\Debug\FtpStreamWrapper;
use Dogma\Debug\Http;
use Dogma\Debug\HttpStreamWrapper;
use Dogma\Debug\Intercept;
use Dogma\Debug\Packet;
use Dogma\Debug\PharStreamWrapper;
use Dogma\Debug\PhpStreamWrapper;
use Dogma\Debug\Request;
use Dogma\Debug\Str;
use Dogma\Debug\StreamInterceptor;
use Dogma\Debug\StreamWrapperMixin;
use Dogma\Debug\System;
use Dogma\Debug\ZlibStreamWrapper;

$_dogma_debug_start = $_dogma_debug_start ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

// do not load auto-prepended libs from other location than tests are when in tests
// tester loads local copy, which may differ from stable auto-prepended version
$_dogma_debug_prepend = (string) ini_get('auto_prepend_file');
$_dogma_debug_script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
if ($_dogma_debug_prepend !== str_replace('\\', '/', __FILE__)
    && $_dogma_debug_prepend !== str_replace(['\\', 'client'], ['/', 'shortcuts'], __FILE__)
    && substr($_dogma_debug_script, -5) === '.phpt'
    && substr($_dogma_debug_script, 0, (int) strpos($_dogma_debug_script, 'dogma-debug'))
        !== substr($_dogma_debug_prepend, 0, (int) strpos($_dogma_debug_prepend, 'dogma-debug'))
) {
    unset($_dogma_debug_prepend, $_dogma_debug_script);
    return false;
}
unset($_dogma_debug_prepend, $_dogma_debug_script);

if (!class_exists(Debugger::class)) {
    require_once __DIR__ . '/src/tools/polyfils.php';
    require_once __DIR__ . '/src/tools/Str.php';
    require_once __DIR__ . '/src/tools/Ansi.php';
    require_once __DIR__ . '/src/tools/Ascii.php';
    require_once __DIR__ . '/src/tools/Diff.php';
    require_once __DIR__ . '/src/tools/Http.php';
    require_once __DIR__ . '/src/tools/System.php';
    require_once __DIR__ . '/src/tools/RedisParser.php';
    require_once __DIR__ . '/src/tools/Resources.php';
    require_once __DIR__ . '/src/tools/Units.php';
    require_once __DIR__ . '/src/tools/VirtualFile.php';

    require_once __DIR__ . '/src/Packet.php';
    require_once __DIR__ . '/src/Request.php';
    require_once __DIR__ . '/src/CallstackFrame.php';
    require_once __DIR__ . '/src/Callstack.php';
    require_once __DIR__ . '/src/Intercept.php';
    require_once __DIR__ . '/src/DebugServer.php';
    require_once __DIR__ . '/src/Debugger.php';
    require_once __DIR__ . '/src/Info.php';

    require_once __DIR__ . '/src/dumper/DumperFormatters.php';
    require_once __DIR__ . '/src/dumper/DumperTraces.php';
    require_once __DIR__ . '/src/dumper/Dumper.php';

    require_once __DIR__ . '/src/dumper/FormattersReflection.php';
    require_once __DIR__ . '/src/dumper/FormattersDom.php';
    require_once __DIR__ . '/src/dumper/FormattersDogma.php';
    require_once __DIR__ . '/src/dumper/FormattersConsistence.php';

    require_once __DIR__ . '/src/handlers/AmqpHandler.php';
    require_once __DIR__ . '/src/handlers/ErrorHandler.php';
    require_once __DIR__ . '/src/handlers/ExceptionHandler.php';
    require_once __DIR__ . '/src/handlers/MemoryHandler.php';
    require_once __DIR__ . '/src/handlers/OutputHandler.php';
    require_once __DIR__ . '/src/handlers/PhpUnitHandler.php';
    require_once __DIR__ . '/src/handlers/RedisHandler.php';
    require_once __DIR__ . '/src/handlers/RequestHandler.php';
    require_once __DIR__ . '/src/handlers/ResourcesHandler.php';
    require_once __DIR__ . '/src/handlers/ShutdownHandler.php';
    require_once __DIR__ . '/src/handlers/SqlHandler.php';

    require_once __DIR__ . '/src/interceptors/AutoloadInterceptor.php';
    require_once __DIR__ . '/src/interceptors/CurlInterceptor.php';
    require_once __DIR__ . '/src/interceptors/DnsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/ErrorInterceptor.php';
    require_once __DIR__ . '/src/interceptors/ExecInterceptor.php';
    require_once __DIR__ . '/src/interceptors/HeadersInterceptor.php';
    require_once __DIR__ . '/src/interceptors/MailInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SessionInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SettingsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SocketsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/StreamInterceptor.php';
    require_once __DIR__ . '/src/interceptors/StreamWrapperInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SyslogInterceptor.php';

    if (extension_loaded('mysqli')) {
        require_once __DIR__ . '/src/interceptors/FakeMysqli.php';
        require_once __DIR__ . '/src/interceptors/MysqliInterceptor.php';
    }
    if (extension_loaded('pdo')) {
        if (PHP_VERSION_ID < 80000) {
            require_once __DIR__ . '/src7/FakePdo7.php';
        } else {
            require_once __DIR__ . '/src8/FakePdo8.php';
        }
    }

    require_once __DIR__ . '/src/stream-wrappers/StreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/StreamWrapperMixin.php';
    require_once __DIR__ . '/src/stream-wrappers/DataStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/FileStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/FtpStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/HttpStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/PharStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/PhpStreamWrapper.php';
    require_once __DIR__ . '/src/stream-wrappers/ZlibStreamWrapper.php';

    // force classes to load
    // (after including source files some classes may be in a half-loaded state because of resolving circular references.
    // we need to force them to really fully load, otherwise PHP may crash when it wants to use them in middle of
    // a stream_wrapper call used by require or include, where loading and finalizing other classes is not possible.
    // @see: https://www.npopov.com/2021/10/20/Early-binding-in-PHP.html)
    trait_exists(StreamWrapperMixin::class);
    $_dogma_debug_force_load_classes = [
        Str::class, Ansi::class, Http::class, Request::class, System::class, Intercept::class, Debugger::class, Dumper::class,
        FileStreamWrapper::class, HttpStreamWrapper::class, FtpStreamWrapper::class, PharStreamWrapper::class, PhpStreamWrapper::class, ZlibStreamWrapper::class,
        StreamInterceptor::class,
    ];
    $x = [];
    array_map(static function ($class) use ($x): void {
        // just calling class_exists() is not enough in some cases : E
        $x[] = new $class();
    }, $_dogma_debug_force_load_classes);
    $x[] = new CallstackFrame(null, null);
    $x[] = new Callstack([]);
    $x[] = new Packet(Packet::OUTPUT_WIDTH, '');
    $x[] = StreamInterceptor::enabled();
    $x[] = FileStreamWrapper::enabled();

    Debugger::setStart($_dogma_debug_start);
    Request::init();

    // configure client, unless the current process is actually a starting server
    if (is_readable(__DIR__ . '/debug-config.php') && $_SERVER['PHP_SELF'] !== 'server.php') {
        require_once __DIR__ . '/debug-config.php';
    }

    if (ini_get('allow_url_include')) {
        Debugger::error('Security warning: ini directive allow_url_include should be off.');
    }
}

unset($_dogma_debug_start, $_dogma_debug_force_load_classes);

foreach (Debugger::$beforeStart as $function) {
    $function();
}

return true;

?>