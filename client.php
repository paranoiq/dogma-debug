<?php
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
use Dogma\Debug\Message;
use Dogma\Debug\PharStreamWrapper;
use Dogma\Debug\PhpStreamWrapper;
use Dogma\Debug\Request;
use Dogma\Debug\Str;
use Dogma\Debug\StreamInterceptor;
use Dogma\Debug\StreamWrapperMixin;
use Dogma\Debug\System;
use Dogma\Debug\ZlibStreamWrapper;

return (static function (): bool {
    global $_dogma_debug_start, $_dogma_debug_no_config;

    $start = $_dogma_debug_start ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

    // do not load auto-prepended libs from other location than tests are when in tests
    // tester loads local copy, which may differ from stable auto-prepended version
    $prepend = (string)ini_get('auto_prepend_file');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
    if ($prepend !== ''
        && $prepend !== str_replace('\\', '/', __FILE__)
        && $prepend !== str_replace(['\\', 'client'], ['/', 'shortcuts'], __FILE__)
        && substr($script, -5) === '.phpt'
        && substr($script, 0, (int) strpos($script, 'dogma-debug'))
            !== substr($prepend, 0, (int) strpos($prepend, 'dogma-debug'))
    ) {
        return false;
    }

    // already loaded
    if (class_exists(Debugger::class)) {
        return false;
    }

    require_once __DIR__ . '/src/tools/polyfils.php';
    require_once __DIR__ . '/src/tools/App.php';
    require_once __DIR__ . '/src/tools/Str.php';
    require_once __DIR__ . '/src/tools/Color.php';
    require_once __DIR__ . '/src/tools/Ansi.php';
    require_once __DIR__ . '/src/tools/Ascii.php';
    require_once __DIR__ . '/src/tools/Diff.php';
    require_once __DIR__ . '/src/tools/Http.php';
    require_once __DIR__ . '/src/tools/Links.php';
    require_once __DIR__ . '/src/tools/MysqlResultInfo.php';
    require_once __DIR__ . '/src/tools/RedisParser.php';
    require_once __DIR__ . '/src/tools/Resources.php';
    require_once __DIR__ . '/src/tools/System.php';
    require_once __DIR__ . '/src/tools/Sapi.php';
    require_once __DIR__ . '/src/tools/Sql.php';
    require_once __DIR__ . '/src/tools/Units.php';
    require_once __DIR__ . '/src/tools/VirtualFile.php';

    require_once __DIR__ . '/src/Message.php';
    require_once __DIR__ . '/src/Request.php';
    require_once __DIR__ . '/src/CallstackFrame.php';
    require_once __DIR__ . '/src/Callstack.php';
    require_once __DIR__ . '/src/Intercept.php';
    require_once __DIR__ . '/src/DebugServer.php';
    require_once __DIR__ . '/src/Debugger.php';
    require_once __DIR__ . '/src/Info.php';

    require_once __DIR__ . '/src/dumper/TableDumper.php';
    require_once __DIR__ . '/src/dumper/DumperComponents.php';
    require_once __DIR__ . '/src/dumper/DumperTraces.php';
    require_once __DIR__ . '/src/dumper/Dumper.php';

    require_once __DIR__ . '/src/dumper/FormattersReflection.php';
    require_once __DIR__ . '/src/dumper/FormattersDefault.php';
    require_once __DIR__ . '/src/dumper/FormattersDoctrine.php';
    require_once __DIR__ . '/src/dumper/FormattersDom.php';
    require_once __DIR__ . '/src/dumper/FormattersDogma.php';
    require_once __DIR__ . '/src/dumper/FormattersBrick.php';
    require_once __DIR__ . '/src/dumper/FormattersConsistence.php';

    require_once __DIR__ . '/src/handlers/AmqpHandler.php';
    require_once __DIR__ . '/src/handlers/DependenciesHandler.php';
    require_once __DIR__ . '/src/handlers/ErrorHandler.php';
    require_once __DIR__ . '/src/handlers/ExceptionHandler.php';
    require_once __DIR__ . '/src/handlers/HttpHandler.php';
    require_once __DIR__ . '/src/handlers/MemoryHandler.php';
    require_once __DIR__ . '/src/handlers/OutputHandler.php';
    require_once __DIR__ . '/src/handlers/PhpUnitHandler.php';
    require_once __DIR__ . '/src/handlers/RedisHandler.php';
    require_once __DIR__ . '/src/handlers/RequestHandler.php';
    require_once __DIR__ . '/src/handlers/ResourcesHandler.php';
    require_once __DIR__ . '/src/handlers/ShutdownHandler.php';
    require_once __DIR__ . '/src/handlers/SqlHandler.php';

    require_once __DIR__ . '/src/interceptors/AutoloadInterceptor.php';
    require_once __DIR__ . '/src/interceptors/BuffersInterceptor.php';
    require_once __DIR__ . '/src/interceptors/CurlInterceptor.php';
    require_once __DIR__ . '/src/interceptors/DnsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/ErrorInterceptor.php';
    require_once __DIR__ . '/src/interceptors/ExecInterceptor.php';
    require_once __DIR__ . '/src/interceptors/HeadersInterceptor.php';
    require_once __DIR__ . '/src/interceptors/MailInterceptor.php';
    require_once __DIR__ . '/src/interceptors/MysqliInterceptor.php';
    require_once __DIR__ . '/src/interceptors/PcntlInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SessionInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SettingsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/ShutdownInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SocketsInterceptor.php';
    require_once __DIR__ . '/src/interceptors/StreamInterceptor.php';
    require_once __DIR__ . '/src/interceptors/StreamWrapperInterceptor.php';
    require_once __DIR__ . '/src/interceptors/SyslogInterceptor.php';

    if (extension_loaded('mysqli')) {
        require_once __DIR__ . '/src/proxies/MysqliProxy.php';
        require_once __DIR__ . '/src/proxies/MysqliStatementProxy.php';
        require_once __DIR__ . '/src/proxies/MysqliStatementWrapper.php';
    }
    if (extension_loaded('pdo')) {
        if (PHP_VERSION_ID < 80000) {
            require_once __DIR__ . '/src7/proxies/PdoProxy7.php';
            require_once __DIR__ . '/src7/proxies/PdoStatementProxy7.php';
        } elseif (PHP_VERSION_ID < 80100) {
            require_once __DIR__ . '/src8/proxies/PdoProxy8.php';
            require_once __DIR__ . '/src8/proxies/PdoStatementProxy8.php';
        } else {
            require_once __DIR__ . '/src8/proxies/PdoProxy81.php';
            require_once __DIR__ . '/src8/proxies/PdoStatementProxy8.php';
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
    $forceLoadClasses = [
        Str::class, Ansi::class, Http::class, Request::class, System::class, Intercept::class, Debugger::class, Dumper::class,
        FileStreamWrapper::class, HttpStreamWrapper::class, FtpStreamWrapper::class, PharStreamWrapper::class, PhpStreamWrapper::class, ZlibStreamWrapper::class,
        StreamInterceptor::class,
    ];
    $forceLoadObjects = [];
    array_map(static function ($class) use ($forceLoadObjects): void {
        // just calling class_exists() is not enough in some cases : E
        $forceLoadObjects[] = new $class();
    }, $forceLoadClasses);
    $forceLoadObjects[] = new CallstackFrame(null, null);
    $forceLoadObjects[] = new Callstack([]);
    $forceLoadObjects[] = Message::create(Message::OUTPUT_WIDTH, '');
    $forceLoadObjects[] = StreamInterceptor::enabled();
    $forceLoadObjects[] = FileStreamWrapper::enabled();

    Debugger::setStart($start);
    Request::init();

    if ($_SERVER['PHP_SELF'] !== 'server.php') {
        $configFile = __DIR__ . '/debug-config.php';
        if (isset($_dogma_debug_no_config)) {
            $configFile = "turned off ({$configFile})";
        } elseif (!file_exists($configFile)) {
            $configFile = "does not exist ({$configFile})";
        } else {
            require_once $configFile;
        }
        if (Debugger::$printConfiguration) {
            $connection = Debugger::$connection;
            $address = Debugger::$remoteAddress;
            $port = Debugger::$remotePort;
            $logFile = Debugger::$logFile ?? 'default';
            echo "Debugger - connection: {$connection}, logSocket: {$address}:{$port}, logFile: {$logFile}, configFile: {$configFile}\n";
        }
    }

    if (ini_get('allow_url_include')) {
        Debugger::error('Security warning: ini directive allow_url_include should be off.');
    }

    foreach (Debugger::$beforeStart as $function) {
        $function();
    }

    return true;
})();

?>