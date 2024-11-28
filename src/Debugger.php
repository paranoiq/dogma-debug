<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.PHP.GlobalKeyword.NotAllowed
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

namespace Dogma\Debug;

use DateTime;
use Socket;
use Throwable;
use function array_shift;
use function array_sum;
use function connection_status;
use function count;
use function dirname;
use function end;
use function error_get_last;
use function explode;
use function fclose;
use function file_get_contents;
use function fopen;
use function fwrite;
use function headers_list;
use function http_response_code;
use function implode;
use function is_array;
use function is_file;
use function is_float;
use function is_int;
use function is_null;
use function is_object;
use function is_string;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function number_format;
use function ob_get_clean;
use function ob_start;
use function register_shutdown_function;
use function session_status;
use function session_write_close;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_write;
use function sprintf;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strval;
use function substr;
use function touch;
use function trim;
use const AF_INET;
use const CONNECTION_ABORTED;
use const CONNECTION_TIMEOUT;
use const PHP_SAPI;
use const PHP_SESSION_ACTIVE;
use const PHP_VERSION;
use const SOCK_STREAM;
use const SOL_TCP;

/**
 * @phpstan-import-type PhpBacktraceItem from Callstack
 */
class Debugger
{

    public const CONNECTION_LOCAL = 1;
    public const CONNECTION_SOCKET = 2;
    public const CONNECTION_FILE = 3;

    public const COMPONENTS_REPORT_AUTO_ACTIVATION = 1;
    public const COMPONENTS_DENY_AUTO_ACTIVATION = 2;

    // connection ------------------------------------------------------------------------------------------------------

    /** @var int Switching between dumps to local STDOUT or remote console over a socket or through a log file */
    public static $connection = self::CONNECTION_FILE;

    /** @var string Address on which is server.php running */
    public static $remoteAddress = '127.0.0.1';

    /** @var int Port on which is server.php running */
    public static $remotePort = 1729;

    /** @var string|null */
    public static $logFile;

    // debugging the debugger ------------------------------------------------------------------------------------------

    /** @var bool Show how debugger client is configured */
    public static $printConfiguration = false;

    /** @var bool Behavior when component automatically activates another or cannot be activated because of system requirements (Windows, missing extensions etc.) @see self::COMPONENTS_* */
    public static $componentDependencies = self::COMPONENTS_REPORT_AUTO_ACTIVATION;

    /** @var bool Show notice when a debugger component accidentally outputs anything to stdout */
    public static $reportDebuggerAccidentalOutput = true;

    // configuration ---------------------------------------------------------------------------------------------------

    /** @var int Max length of a dump message [bytes after all formatting] */
    public static $maxMessageLength = 20000;

    /** @var int Amount of memory reserved for OOM shutdown */
    public static $reserveMemory = 100000;

    /** @var list<callable(): void> Functions to call before starting the actual request */
    public static $beforeStart = [];

    /** @var list<callable(string): bool> Functions to call before sending a message. Receives Message as first argument. Returns false to stop message from sending */
    public static $beforeSend = [];

    /** @var list<callable(): void> Functions to call before debugger shutdown. Can dump some final things before debug footer is sent */
    public static $beforeShutdown = [];

    /** @var int|null Output console width (affects how dumps are formatted) */
    public static $outputWidth = 200;

    /** @var string Format of time for request header */
    public static $headerTimeFormat = 'D H:i:s.v';

    /** @var string Foreground color of request header/footer and process id label */
    public static $headerColor = Ansi::BLACK;

    /** @var string Background color of request header/footer */
    public static $headerBg = Ansi::LYELLOW;

    /** @var list<class-string<StreamWrapper>> Order of stream handler stats in request footer */
    public static $footerStreamWrappers = [
        FileStreamWrapper::class,
        PharStreamWrapper::class,
        HttpStreamWrapper::class,
        FtpStreamWrapper::class,
        DataStreamWrapper::class,
        PhpStreamWrapper::class,
        ZlibStreamWrapper::class,
    ];

    /** @var list<class-string> */
    public static $footerHandlers = [
        SqlHandler::class,
        RedisHandler::class,
        AmqpHandler::class,
        HttpHandler::class,
        ShutdownHandler::class,
        RequestHandler::class,
        ErrorHandler::class,
    ];

    /** @var array<string, string> Background colors of handler labels */
    public static $handlerColors = [
        'default' => Ansi::DGREEN,
        'event' => Ansi::LBLUE,

        // basic handlers
        ErrorHandler::NAME => Ansi::DGREEN,
        ExceptionHandler::NAME => Ansi::DGREEN,
        MemoryHandler::NAME => Ansi::DGREEN,
        OutputHandler::NAME => Ansi::DGREEN,
        RequestHandler::NAME => Ansi::DGREEN,
        ResourcesHandler::NAME => Ansi::DGREEN,
        ShutdownHandler::NAME => Ansi::DGREEN,

        // database handlers
        SqlHandler::NAME => Ansi::DGREEN,
        RedisHandler::NAME => Ansi::DGREEN,
        AmqpHandler::NAME => Ansi::DGREEN,

        // intercept handlers
        AutoloadInterceptor::NAME => Ansi::DGREEN,
        CurlInterceptor::NAME => Ansi::DGREEN,
        DnsInterceptor::NAME => Ansi::DGREEN,
        MailInterceptor::NAME => Ansi::DGREEN,
        SessionInterceptor::NAME => Ansi::DGREEN,
        SettingsInterceptor::NAME => Ansi::DGREEN,
        SocketsInterceptor::NAME => Ansi::DGREEN,
        StreamInterceptor::NAME => Ansi::DGREEN,
        SyslogInterceptor::NAME => Ansi::DGREEN,

        // stream handlers
        DataStreamWrapper::PROTOCOL => Ansi::DGREEN,
        FileStreamWrapper::PROTOCOL => Ansi::DGREEN,
        FtpStreamWrapper::PROTOCOL => Ansi::DGREEN,
        HttpStreamWrapper::PROTOCOL => Ansi::DGREEN,
        PharStreamWrapper::PROTOCOL => Ansi::DGREEN,
        PhpStreamWrapper::PROTOCOL => Ansi::DGREEN,
        ZlibStreamWrapper::PROTOCOL => Ansi::DGREEN,

        // stream transports
        StreamInterceptor::PROTOCOL_TCP => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_UDP => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_UNIX => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_UDG => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_SSL => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_TLS => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_TLS_10 => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_TLS_11 => Ansi::DGREEN,
        StreamInterceptor::PROTOCOL_TLS_12 => Ansi::DGREEN,
    ];

    /** @var bool - show pid for all logged events (otherwise shown only on request start/end) */
    public static $alwaysShowPid = false;

    /** @var bool - show time of all logged events (otherwise shown only on request start/end) */
    public static $alwaysShowTime = false;

    /** @var bool - show duration of measured events */
    public static $alwaysShowDuration = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var array<string, float> - starts of timers indexed by name [timestamp] */
    private static $timerStarts = [];

    /** @var array<string, float> - previous state of timers indexed by name [timestamp] */
    private static $timerPrevious = [];

    /** @var array<string, int> - counts of timer events indexed by name */
    private static $timerEvents = [];

    /** @var array<string, int> - previous states of memory indexed by name [bytes] */
    private static $memory = [];

    /**
     * @var string|null - textual description of termination reason:
     *   todo: "finished in <file:line>" // need to detect executed file and insert handler at the end
     *   todo: "unhandled error" (error is dumped) // need to check other registered error handlers
     *   "uncaught exception" (exception is dumped)
     *   "exit (...)", todo: <file:line>
     *   "signal (...)"
     *   "memory limit (...)"
     *   "time limit (...)"
     *   "connection aborted"
     */
    private static $terminatedBy;

    /** @var string - simplified name of the process/request */
    private static $name;

    /** @var string|false|null - reserved memory for case of OOM shutdown. false if already freed */
    public static $reserved;

    /** @var resource|Socket */
    private static $socket;

    /** @var DebugServer */
    private static $server;

    /** @var bool */
    private static $connected = false;

    /** @var bool */
    private static $initDone = false;

    /** @var bool */
    private static $shutdownPostponed = false;

    /** @var bool */
    private static $shutdownDone = false;

    /**
     * @template T
     * @param T $value
     * @return T
     */
    public static function varDump($value, bool $colors = true)
    {
        ob_start();

        $dump = Dumper::varDump($value, $colors);
        self::send(Message::DUMP, $dump);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $value;
    }

    /**
     * @template T
     * @param T $value
     * @return T
     */
    public static function dump($value, ?int $maxDepth = null, ?int $traceLength = null, ?string $name = null)
    {
        ob_start();

        $dump = Dumper::dump($value, $maxDepth, $traceLength, $name);
        self::send(Message::DUMP, $dump);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $value;
    }

    /**
     * @template T
     * @param T $value
     * @return T
     */
    public static function dumpTable($value, ?int $traceLength = null, ?string $name = null)
    {
        ob_start();

        $dump = TableDumper::dump($value);
        self::send(Message::DUMP, "\n" . $dump);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $value;
    }

    /**
     * @template T
     * @param T&Throwable $exception
     * @return T&Throwable
     */
    public static function dumpException(Throwable $exception): Throwable
    {
        ob_start();

        $message = ExceptionHandler::formatException($exception, ExceptionHandler::SOURCE_DUMPED);

        self::send(Message::EXCEPTION, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $exception;
    }

    public static function capture(callable $callback, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        ob_start();
        $callback();
        $value = ob_get_clean();

        if ($value === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in Debugger::capture(). ", Ansi::DRED);
            self::send(Message::ERROR, $message);

            return '';
        }

        ob_start();

        $dump = Dumper::dump($value, $maxDepth, $traceLength);
        self::send(Message::DUMP, $dump);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $value;
    }

    /**
     * @param string|int|float|bool|object|null $label
     * @return string|int|float|bool|object|null
     */
    public static function label($label, ?string $name = null, ?string $color = null)
    {
        ob_start();

        $color = $color ?? Ansi::LGRAY;

        if ($label === null) {
            $value = 'null';
        } elseif ($label === false) {
            $value = 'false';
        } elseif ($label === true) {
            $value = 'true';
        } elseif (is_string($label)) {
            $value = Dumper::escapeRawString($label, Dumper::$rawEscaping, Ansi::BLACK, $color);
        } elseif (is_int($label) || is_float($label)) {
            $value = $label;
        } elseif (is_object($label)) {
            $value = get_class($label) . ' #' . Dumper::objectHash($label);
        } else {
            $message = Ansi::white(' Invalid value sent to Debugger::label() ', Ansi::LRED);
            self::send(Message::LABEL, $message);
            return $label;
        }
        if ($name !== null) {
            $name = Dumper::escapeRawString($name, Dumper::$rawEscaping, Ansi::BLACK, $color);
        }

        $message = Ansi::black($name ? " {$name}: {$value} " : " {$value} ", $color);

        self::send(Message::LABEL, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $label;
    }

    public static function raw(string $message, string $background = Ansi::BLACK): string
    {
        ob_start();

        // escape special chars, that can interfere with message formatting
        $message = Dumper::escapeRawString($message, Dumper::$rawEscaping, Ansi::LGRAY, $background);

        self::send(Message::RAW, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);

        return $message;
    }

    /**
     * @param Callstack|PhpBacktraceItem[]|null $callstack
     */
    public static function callstack(
        ?int $length = null,
        ?int $argsDepth = null,
        ?int $codeLines = null,
        ?int $codeDepth = null,
        $callstack = null
    ): void
    {
        ob_start();

        if (is_array($callstack)) {
            $callstack = Callstack::fromBacktrace($callstack);
        } elseif ($callstack === null) {
            $callstack = Callstack::get(Dumper::$traceFilters);
        }
        $trace = Dumper::formatCallstack($callstack, $length, $argsDepth, $codeLines, $codeDepth);

        self::send(Message::CALLSTACK, $trace);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);
    }

    public static function function(bool $withLocation = true, bool $withDepth = false, ?string $function = null): void
    {
        static $x = false;
        ob_start();

        $callstack = Callstack::get(['~Debugger::function$~', '~^rf$~']);
        $frame = $callstack->last();
        $class = $frame->class ?? null;
        $function = $function ?? $frame->function ?? null;
        // todo: show args

        $depth = '';
        if ($withDepth) {
            $depth = str_repeat('| ', $callstack->getDepth());
        }
        $location = '';
        if ($withLocation) {
            $location = ' in ' . Dumper::fileLine($frame->file, $frame->line);
        }

        if ($class !== null) {
            $class = explode('\\', $class);
            $class = end($class);

            $message = Ansi::white(" {$depth}{$class}::{$function}() ", Ansi::DCYAN) . $location;
        } else {
            $message = Ansi::white(" {$depth}{$function}() ", Ansi::DCYAN) . $location;
        }

        self::send(Message::CALLSTACK, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);
    }

    /**
     * @param string|int|null $name
     */
    public static function timer($group = '', $name = ''): void
    {
        ob_start();

        $group = strval($group);

        if (isset(self::$timerStarts[$group])) {
            // next call
            $previous = self::$timerPrevious[$group];
            $time = microtime(true);
            self::$timerPrevious[$group] = $time;
            self::$timerEvents[$group]++;
            $event = self::$timerEvents[$group];
        } elseif (isset(self::$timerStarts[''])) {
            // referring to global timer
            $previous = self::$timerPrevious[''];
            $time = microtime(true);
            self::$timerStarts[$group] = $time;
            self::$timerPrevious[$group] = $time;
            self::$timerEvents[$group] = 1;
            $event = self::$timerEvents[''];
        } else {
            // first call ever
            $time = microtime(true);
            self::$timerStarts[''] = $time;
            self::$timerPrevious[''] = $time;
            self::$timerEvents[''] = 0;
            self::$timerStarts[$group] = $time;
            self::$timerPrevious[$group] = $time;
            self::$timerEvents[$group] = 0;
            return;
        }

        $time = Units::time(microtime(true) - $previous);
        if ($group !== '') {
            $message = Ansi::white('Timer ') . Ansi::lyellow($group) . Ansi::white(" {$event}:") . ' ' . Dumper::time($time) . ' ' . $name;
        } else {
            $message = Ansi::white("Timer {$event}:") . ' ' . Dumper::time($time) . ' ' . $name;
        }

        self::send(Message::TIMER, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);
    }

    /**
     * @param string|int|null $name
     */
    public static function memory($name = ''): void
    {
        ob_start();

        $name = (string) $name;

        $last = $previous = end(self::$memory) ?: 0;
        if (isset(self::$memory[$name])) {
            $previous = self::$memory[$name];
        }

        $now = memory_get_usage();
        unset(self::$memory[$name]); // because of end()
        self::$memory[$name] = $now;

        $memory = ' ' . Dumper::memory(Units::memory($now, 4));

        if ($name !== '') {
            $change = $now - $previous;
            $pos = $change >= 0 ? '+' : '-';
            $memory .= ", since last " . Ansi::white($name) . ": " . Dumper::memory($pos . Units::memory($change));
        }

        $change = $now - $last;
        $pos = $change >= 0 ? '+' : '-';
        $memory .= ', change: ' . Dumper::memory($pos . Units::memory($change));

        $name = $name ? 'Memory ' . $name : 'Memory';
        $message = Ansi::white(" $name: ", Ansi::DGREEN) . $memory;

        self::send(Message::MEMORY, $message);

        self::checkAccidentalOutput(__CLASS__, __FUNCTION__);
    }

    // internals -------------------------------------------------------------------------------------------------------

    /**
     * Ensures that debugger actions have not accidental output
     *
     * @param class-string $class
     */
    public static function guarded(callable $callback, string $class, string $method): void
    {
        ob_start();

        $callback();

        self::checkAccidentalOutput($class, $method);
    }

    public static function send(
        int $type,
        string $payload,
        string $backtrace = '',
        ?float $duration = null,
        ?int $processId = null
    ): void
    {
        static $depth = 0;

        if (!self::$connected) {
            self::init();
        }

        try {
            $depth++;
            if ($depth <= 1 && Request::$application === 'phpunit' && PhpUnitHandler::$announceTestCaseName) {
                PhpUnitHandler::announceTestCaseName();
            }
            if ($depth <= 1 && self::$shutdownDone) {
                ShutdownHandler::announceDestructorCallAfterShutdown();
            }
        } finally {
            $depth--;
        }

        $flags = (self::$alwaysShowPid ? Message::FLAG_SHOW_PID : 0)
            | (self::$alwaysShowTime ? Message::FLAG_SHOW_TIME : 0)
            | (self::$alwaysShowDuration ? Message::FLAG_SHOW_DURATION : 0);
        $message = Message::create($type, $payload, $backtrace, $duration, $flags, $processId);

        foreach (self::$beforeSend as $function) {
            $result = $function($message);
            if ($result === false) {
                return;
            } elseif ($result instanceof Message) {
                $message = $result;
            }
        }

        if (self::$connection === self::CONNECTION_LOCAL) {
            self::$server->renderMessage($message);
            return;
        }

        $data = $message->encode();

        if (self::$connection === self::CONNECTION_FILE) {
            $file = fopen(self::$logFile, 'ab');
            if (!$file) {
                $m = error_get_last()['message'] ?? '???';
                self::error("Could not send data to debug server: {$m}");
                self::print($payload . "\n" . $backtrace);
                return;
            }
            $result = fwrite($file, $data);
            if (!$result) {
                $m = error_get_last()['message'] ?? '???';
                self::error("Could not send data to debug server: {$m}");
                self::print($payload . "\n" . $backtrace);
                fclose($file);
                return;
            }
            fclose($file);

            return;
        }

        $result = @socket_write(self::$socket, $data, strlen($data));
        if (!$result) {
            $m = error_get_last()['message'] ?? '???';
            self::error("Could not send data to debug server: {$m}");
            self::print($payload . "\n" . $backtrace);
        }
    }

    public static function print(string $message): void
    {
        if (PHP_SAPI === 'cli') {
            echo $message . "\n";
        } else {
            echo Ansi::removeColors($message) . "\n";
        }
    }

    public static function dependencyInfo(string $message, bool $autoActivated = false): void
    {
        if (self::$componentDependencies === self::COMPONENTS_DENY_AUTO_ACTIVATION) {
            $callstack = Callstack::get(Dumper::$traceFilters);
            self::send(Message::INFO, Ansi::lmagenta('DENIED: ' . $message), Dumper::formatCallstack($callstack, 1, 0, 0));
            exit;
        }
        if (self::$componentDependencies === self::COMPONENTS_REPORT_AUTO_ACTIVATION) {
            $callstack = Callstack::get(Dumper::$traceFilters);
            self::send(Message::INFO, Ansi::lmagenta($message), Dumper::formatCallstack($callstack, 1, 0, 0));
        }
    }

    public static function error(string $message): void
    {
        if (self::$connection === self::CONNECTION_SOCKET) {
            $message = sprintf("Dogma Debugger: {$message}. Debug server should be running on %s:%s.", self::$remoteAddress, self::$remotePort);
        } else {
            $message = sprintf("Dogma Debugger: {$message}. Log file is probably not writable: %s.", self::$logFile);
        }

        if (PHP_SAPI === 'cli') {
            echo Ansi::white($message) . "\n";
        } else {
            echo $message . "\n";
        }
    }

    public static function getStart(): float
    {
        return self::$timerStarts[''];
    }

    /**
     * @internal
     */
    public static function setStart(float $time): void
    {
        self::$timerStarts[''] = $time;
        self::$timerPrevious[''] = $time;
        self::$timerEvents[''] = 0;
    }

    public static function setTermination(string $reason): void
    {
        self::$terminatedBy = $reason;
    }

    /**
     * When called by user, init() logs request start/end even if no other debug events are being logged.
     * Otherwise, debugger is initiated automatically with first error or user event.
     */
    public static function init(): void
    {
        if (self::$logFile === null) {
            self::$logFile = dirname(__DIR__) . '/debugger.log';
        }

        if (self::$connection === self::CONNECTION_SOCKET && self::$socket === null) {
            self::connect();
        } elseif (self::$connection === self::CONNECTION_LOCAL && self::$server === null) {
            self::$server = new DebugServer(1729, '127.0.0.1', self::$logFile);
        } elseif (self::$connection === self::CONNECTION_FILE) {
            if (!is_file(self::$logFile)) {
                touch(self::$logFile);
            }
        }

        self::$connected = true;

        if (!self::$initDone) {
            self::registerShutdown();

            if (Message::$count === 0) {
                $header = self::createHeader();
                $flags = (self::$alwaysShowPid ? Message::FLAG_SHOW_PID : 0)
                    | (self::$alwaysShowTime ? Message::FLAG_SHOW_TIME : 0)
                    | (self::$alwaysShowDuration ? Message::FLAG_SHOW_DURATION : 0);
                $message = Message::create(Message::INTRO, $header, null, $flags);

                if (self::$connection === self::CONNECTION_LOCAL) {
                    self::$server->renderMessage($message);
                } else {
                    $data = $message->encode();

                    if (self::$connection === self::CONNECTION_FILE) {
                        $file = fopen(self::$logFile, 'ab');
                        if (!$file) {
                            return;
                        }
                        $result = fwrite($file, $data);
                        if (!$result) {
                            return;
                        }
                    } else {
                        $result = @socket_write(self::$socket, $data, strlen($data));
                        if (!$result) {
                            return;
                        }
                    }
                }
            }

            self::$initDone = true;

            if (is_null(self::$reserved)) {
                self::$reserved = str_repeat('!', self::$reserveMemory);
            }
        }
    }

    private static function connect(): void
    {
        if (self::$socket !== null) {
            return;
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            self::error("Could not create socket to debug server.");
            return;
        }
        self::$socket = $socket;

        $result = @socket_connect(self::$socket, self::$remoteAddress, self::$remotePort);
        if (!$result && $_SERVER['PHP_SELF'] !== 'server.php') {
            self::error("Could not connect to debug server.");
        }
    }

    private static function registerShutdown(): void
    {
        $handler = static function (): void {
            if (!self::$shutdownDone) {
                self::$reserved = false;

                if (!self::$shutdownPostponed) {
                    // postpone debugger shutdown after other registered shutdown handlers
                    self::$shutdownPostponed = true;
                    self::registerShutdown();
                    return;
                }

                try {
                    foreach (self::$beforeShutdown as $callback) {
                        $callback();
                    }
                } finally {
                    self::send(Message::OUTRO, self::createFooter());
                    self::$shutdownDone = true;
                }
            }
        };
        if (Intercept::$wrapEventHandlers & Intercept::EVENT_SHUTDOWN) {
            $handler = Intercept::wrapEventHandler($handler, Intercept::EVENT_SHUTDOWN);
        }
        register_shutdown_function($handler);
    }

    /**
     * @param class-string $class
     */
    private static function checkAccidentalOutput(string $class, string $method): void
    {
        $output = ob_get_clean();
        if ($output === "") {
            return;
        } elseif ($output === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in {$class}::{$method}(). ", Ansi::DRED);
        } elseif (!self::$reportDebuggerAccidentalOutput) {
            return;
        } else {
            $message = Ansi::white(' Accidental output: ', Ansi::DRED) . ' ' . Dumper::dumpValue($output, 0);
        }

        self::send(Message::ERROR, $message);
    }

    // header & footer -------------------------------------------------------------------------------------------------

    public static function sendForkedProcessHeader(int $parentId, int $childId): void
    {
        $dt = new DateTime();
        $time = $dt->format(self::$headerTimeFormat);
        $php = PHP_VERSION . ' ' . Request::$sapi;
        $packages = DependenciesHandler::getPackagesInfo();
        $header = "\n" . Ansi::color(" START {$time} | PHP {$php} " . $packages, self::$headerColor, self::$headerBg) . ' ';
        if (Request::$application && Request::$environment) {
            $header .= Ansi::white(' ' . Request::$application . '/' . Request::$environment . ' ', Ansi::DBLUE) . ' ';
        } elseif (Request::$application) {
            $header .= Ansi::white(' ' . Request::$application . ' ', Ansi::DBLUE) . ' ';
        }

        $header .= "forked from #{$parentId}";

        self::send(Message::INTRO, $header, '', null, $childId);
    }

    private static function createHeader(): string
    {
        global $argv;

        /** @var DateTime $dt */
        $dt = DateTime::createFromFormat('U.u', number_format(self::$timerStarts[''], 6, '.', ''));
        $time = $dt->format(self::$headerTimeFormat);
        $php = PHP_VERSION . ' ' . Request::$sapi;
        $packages = DependenciesHandler::getPackagesInfo();
        $header = "\n" . Ansi::color(" START {$time} | PHP {$php} " . $packages, self::$headerColor, self::$headerBg) . ' ';
        if (Request::$application && Request::$environment) {
            $header .= Ansi::white(' ' . Request::$application . '/' . Request::$environment . ' ', Ansi::DBLUE) . ' ';
        } elseif (Request::$application) {
            $header .= Ansi::white(' ' . Request::$application . ' ', Ansi::DBLUE) . ' ';
        }

        if (Request::isCli()) {
            $args = $argv;
            if (count($args) === 1 && $args[0] === '-') {
                if (RequestHandler::$stdinData) {
                    $stdin = (string) file_get_contents('php://stdin');
                    $trim = trim($stdin);
                    $args[0] = Dumper::string(substr($trim, 0, 50) . (strlen($trim) > 50 ? '...' : '')) . ' | php';
                    // todo: faking input through PhpStreamHandler is needed for this, because php://stdin can not be rewinded
                } else {
                    $args[0] = 'php://stdin <?php ...';
                }
            } else {
                $args[0] = Dumper::file($args[0]);
            }
            self::$name = implode(' ', $args);
            $header .= self::$name . ' ';
        } else {
            if (Request::isAjax()) {
                $header .= Ansi::white(' AJAX ', RequestHandler::$methodColors['ajax']) . ' ';
            }

            $method = Request::getMethod();
            if ($method !== null) {
                $header .= Ansi::white(" {$method} ", RequestHandler::$methodColors[strtolower($method)]) . ' ';
            }

            $url = Request::getUrl();
            if ($url !== null) {
                self::$name = Dumper::url($url);
                $header .= self::$name . ' ';
            } else {
                self::$name = '';
            }

            $file = Request::getFile();
            if (RequestHandler::$showIndex && $file !== null) {
                $header .= Dumper::info('(') . Dumper::file($file) . Dumper::info(')') . ' ';
            }
        }

        $header = Ansi::pad($header, self::getOutputWidth() - 2, '-');

        if (!Request::isCli()) {
            // request headers
            if (RequestHandler::$requestHeaders) {
                $headers = [];
                foreach ($_SERVER as $name => $value) {
                    if (str_starts_with($name, 'HTTP_')) {
                        $headers[Http::normalizeHeaderName($name)] = $value;
                    }
                }
                $header .= "\n" . Ansi::white("headers:") . ' ' . Dumper::dumpArray($headers);
            }

            // request body
            //if (RequestHandler::$requestBody) {
                // todo
            //}
        }

        return $header;
    }

    private static function createFooter(): string
    {
        $footer = '';

        // closing session
        $sessionStatus = session_status();
        if (SessionInterceptor::$terminateSessionInShutdownHandler && $sessionStatus === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // last error
        /*if (ErrorHandler::$showLastError) {
            $e = error_get_last();
            if ($e !== null) {
                rd($e);
            }
        }*/

        // response headers
        if (RequestHandler::$responseHeaders) {
            $headers = headers_list();
            if ($headers !== []) {
                $footer .= Ansi::white(' headers: ', Ansi::DGREEN) . "\n";
                foreach ($headers as $header) {
                    $parts = explode(': ', $header);
                    $name = array_shift($parts);
                    $value = implode(': ', $parts);
                    $footer .= "   " . Dumper::key($name, true) . ': ' . Dumper::value($value) . "\n";
                }
            }
        }

        // common things (pid, output, time, memory)
        if (OutputHandler::enabled()) {
            OutputHandler::terminateAllOutputBuffers();
        }
        $output = Units::memoryWs(OutputHandler::getTotalLength());
        $time = Units::timeWs(microtime(true) - self::$timerStarts['']);
        $memory = Units::memory(memory_get_peak_usage(false));
        $footer .= Ansi::color(" END {$output}{$time}{$memory} ", self::$headerColor, self::$headerBg);

        // includes io
        $events = 0;
        $time = 0.0;
        /** @var class-string<StreamWrapper> $wrapper */
        foreach (self::$footerStreamWrappers as $wrapper) {
            $stats = $wrapper::getStats(true);
            $events += $stats['events']['total'];
            $time += $stats['time']['total'];
        }
        if ($events > 0) {
            $time = Units::time($time);
            $footer .= Ansi::color("| inc: {$events}× {$time} ", self::$headerColor, self::$headerBg);
        }

        // stream wrappers io
        /** @var class-string<StreamWrapper> $wrapper */
        foreach (self::$footerStreamWrappers as $wrapper) {
            $ioStats = $wrapper::getStats();
            if ($ioStats['events']['fopen'] > 0) {
                $time = Units::time($ioStats['time']['total']);
                $footer .= Ansi::color('| ' . $wrapper::PROTOCOL . ": {$ioStats['events']['fopen']}× {$time} ", self::$headerColor, self::$headerBg);
            }
        }

        // database io
        $stats = SqlHandler::getStats();
        $conn = $stats['events']['connect'];
        if ($conn > 0) {
            $connections = $conn > 1 ? Units::unitWs($conn, 'con') : '';
            $queries = Units::unitWs($stats['events']['total'], 'q');
            $time = Units::timeWs($stats['time']['total']);
            $errorCount = $stats['errors']['total'];
            if ($errorCount === 0) {
                $rows = Units::units($stats['rows']['total'], 'row');
                $errors = '';
            } else {
                $rows = Units::unitsWs($stats['rows']['total'], 'row');
                $errors = Units::units($errorCount, 'error');
            }
            $footer .= Ansi::color("| db: {$connections}{$queries}{$time}{$rows}{$errors} ", self::$headerColor, self::$headerBg);
        }

        // redis io
        $stats = RedisHandler::getStats();
        $events = $stats['events']['total'];
        if ($events > 0) {
            $queries = Units::unitWs($stats['events']['total'], 'q');
            $time = Units::timeWs($stats['time']['total']);
            $data = Units::memoryWs((int) $stats['data']['total']);
            $rows = Units::units($stats['rows']['total'], 'row');
            $footer .= Ansi::color("| redis: {$queries}{$time}{$data}{$rows} ", self::$headerColor, self::$headerBg);
        }

        // amqp io
        $stats = AmqpHandler::getStats();
        $events = $stats['events']['total'];
        if ($events > 0) {
            $queries = Units::unitWs($stats['events']['total'], 'q');
            $time = Units::timeWs($stats['time']['total']);
            $data = Units::memoryWs((int) $stats['data']['total']);
            $rows = Units::units($stats['rows']['total'], 'row');
            $footer .= Ansi::color("| amqp: {$queries}{$time}{$data}{$rows} ", self::$headerColor, self::$headerBg);
        }

        // http io
        $stats = HttpHandler::getStats();
        $events = $stats['events']['total'];
        if ($events > 0) {
            $queries = Units::unitWs($stats['events']['total'], 'r');
            $time = Units::timeWs($stats['time']['total']);
            $data = Units::memory((int) $stats['data']['total']);
            $footer .= Ansi::color("| http: {$queries}{$time}{$data} ", self::$headerColor, self::$headerBg);
        }

        // termination reason
        if (Resources::timeLimit() !== 0.0 && Resources::timeRemaining() < 0 && (self::$terminatedBy === null || self::$terminatedBy === 'signal (profiling)')) {
            $reason = 'time limit (' . Resources::timeLimit() . ' s)';
            $footer .= ' ' . Ansi::white(" {$reason} ", Ansi::LMAGENTA);
        } elseif (self::$terminatedBy !== null) {
            $footer .= ' ' . Ansi::white(' ' . self::$terminatedBy . ' ', Ansi::LMAGENTA);
        } elseif (connection_status() !== 0) {
            $connectionStatus = connection_status();
            if ($connectionStatus === CONNECTION_ABORTED) {
                $reason = 'connection aborted';
            } elseif ($connectionStatus === CONNECTION_TIMEOUT) {
                $reason = 'time limit (' . Resources::timeLimit() . ' s)';
            } else {
                $reason = 'connection aborted & time limit (' . Resources::timeLimit() . ' s)';
            }
            $footer .= ' ' . Ansi::white(" {$reason} ", Ansi::LMAGENTA);
        }

        // response code
        $status = http_response_code();
        if ($status !== false) {
            $message = Http::RESPONSE_MESSAGES[$status] ?? 'Unknown';
            $color = RequestHandler::$responseColors[substr($status, 0, 1)] ?? Ansi::DYELLOW;
            $footer .= ' ' . Ansi::white(" {$status} {$message} ", $color);
        }

        // errors
        ErrorHandler::removeLogLimits(); // display following errors
        $errors = ErrorHandler::getMessages();
        $count = ErrorHandler::getCount();
        $muted = ErrorHandler::getMutedCount();
        if ($count + $muted > 0) {
            $err = Units::units($count, 'error');
            if ($muted) {
                $err .= " ({$muted} muted)";
            }
            $list = ErrorHandler::$listErrors && $errors !== [] ? ':' : '';
            $footer .= ' ' . Ansi::white(" {$err}{$list} ", Ansi::LRED);
            if ($list) {
                foreach ($errors as $error => $files) {
                    $file = $line = $count = null;
                    foreach ($files as $fileLine => $count) {
                        [$file, $line] = Str::splitByLast($fileLine, ':');
                    }
                    $footer .= "\n " . Ansi::white(array_sum($files) . '×') . ' ' . Ansi::lyellow($error);
                    if ($file !== '') {
                        $footer .= Dumper::info(' - e.g. in ') . Dumper::fileLine((string) $file, (int) $line) . Dumper::info(" {$count}×");
                    }
                }
            }
        }

        return $footer;
    }

    public static function getOutputWidth(): int
    {
        if (self::$outputWidth !== null) {
            return self::$outputWidth;
        }

        if (self::$connection === self::CONNECTION_LOCAL) {
            self::$outputWidth = System::getTerminalWidth();

            return self::$outputWidth;
        } elseif (self::$connection === self::CONNECTION_FILE) {
            // todo: back
            self::$outputWidth = System::getTerminalWidth();

            return self::$outputWidth;
        }

        if (self::$socket === null) {
            self::connect();
        }

        $data = Message::create(Message::OUTPUT_WIDTH, '')->encode();
        $result = socket_write(self::$socket, $data, strlen($data));
        if (!$result) {
            $m = error_get_last()['message'] ?? '???';
            self::error("Could not send data to debug server: {$m}");
        }

        $content = socket_read(self::$socket, 10000);
        if ($content === false) {
            self::$outputWidth = 120;
        } else {
            foreach (explode("\x04", $content) as $data) {
                if (!$data) {
                    continue;
                }
                $response = Message::decode($data);
                if ($response->type === Message::OUTPUT_WIDTH) {
                    self::$outputWidth = (int) $response->payload;
                }
            }
        }

        return self::$outputWidth;
    }

}
