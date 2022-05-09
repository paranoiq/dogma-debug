<?php declare(strict_types = 1);
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
use function array_filter;
use function array_shift;
use function array_sum;
use function connection_aborted;
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
use function ignore_user_abort;
use function implode;
use function is_array;
use function is_file;
use function is_null;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function number_format;
use function ob_get_clean;
use function ob_start;
use function register_shutdown_function;
use function serialize;
use function session_status;
use function session_write_close;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_write;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function touch;
use function trim;
use function unserialize;
use const AF_INET;
use const CONNECTION_ABORTED;
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

    /** @var int Switching between dumps to local STDOUT or remote console over a socket or through a log file */
    public static $connection = self::CONNECTION_FILE;

    /** @var string Address on which is server.php running */
    public static $remoteAddress = '127.0.0.1';

    /** @var int Port on which is server.php running */
    public static $remotePort = 1729;

    /** @var string|null */
    public static $logFile;

    /** @var bool Show notice when a debugger component automatically activates another or cannot be activated because of system requirements (Windows, missing extensions etc.) */
    public static $showDependenciesInfo = true;

    /** @var int Max length of a dump message [bytes after all formatting] */
    public static $maxMessageLength = 20000;

    /** @var int Amount of memory reserved for OOM shutdown */
    public static $reserveMemory = 100000;

    /** @var callable[] Functions to call before starting the actual request */
    public static $beforeStart = [];

    /** @var callable[] Functions to call before sending a packet. Receives Packet as first argument. Returns true to stop packet from sending */
    public static $beforeSend = [];

    /** @var callable[] Functions to call before debugger shutdown. Can dump some final things before debug footer is sent */
    public static $beforeShutdown = [];

    /** @var int|null Output console width (affects how dumps are formatted) */
    public static $outputWidth = 200;

    /** @var string Format of time for request header */
    public static $headerTimeFormat = 'D H:i:s.v';

    /** @var string Background color of request header, footer and process id label */
    public static $headerColor = Ansi::LYELLOW;

    /** @var class-string[] Order of stream handler stats in request footer */
    public static $footerStreamWrappers = [
        FileStreamWrapper::class,
        PharStreamWrapper::class,
        HttpStreamWrapper::class,
        FtpStreamWrapper::class,
        DataStreamWrapper::class,
        PhpStreamWrapper::class,
        ZlibStreamWrapper::class,
    ];

    /** @var string[] Background colors of handler labels */
    public static $handlerColors = [
        'default' => Ansi::DGREEN,

        // basic handlers
        ErrorHandler::NAME => Ansi::DGREEN,
        ExceptionHandler::NAME => Ansi::DGREEN,
        MemoryHandler::NAME => Ansi::DGREEN,
        OutputHandler::NAME => Ansi::DGREEN,
        RequestHandler::NAME => Ansi::DGREEN,
        ResourcesHandler::NAME => Ansi::DGREEN,
        ShutdownHandler::NAME => Ansi::DGREEN,

        // database handlers
        RedisHandler::NAME => Ansi::DGREEN,
        SqlHandler::NAME => Ansi::DGREEN,

        // intercept handlers
        AutoloadInterceptor::NAME => Ansi::DGREEN,
        CurlInterceptor::NAME => Ansi::DGREEN,
        DnsInterceptor::NAME => Ansi::DGREEN,
        FilesystemInterceptor::NAME => Ansi::DGREEN,
        MailInterceptor::NAME => Ansi::DGREEN,
        SessionInterceptor::NAME => Ansi::DGREEN,
        SettingsInterceptor::NAME => Ansi::DGREEN,
        //SocketsHandler::NAME => Ansi::DGREEN,
        StreamWrapper::NAME => Ansi::DGREEN,
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
        FilesystemInterceptor::PROTOCOL_TCP => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_UDP => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_UNIX => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_UDG => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_SSL => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_TLS => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_TLS_10 => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_TLS_11 => Ansi::DGREEN,
        FilesystemInterceptor::PROTOCOL_TLS_12 => Ansi::DGREEN,
    ];

    // internals -------------------------------------------------------------------------------------------------------

    /** @var array<string, float> - previous state of timers indexed by name [seconds from start] */
    private static $timers = [];

    /** @var array<string, int> - previous states of memory indexed by name [bytes] */
    private static $memory = [];

    /** @var string|null - textual description of termination reason - exit(...)|signal (...)|memory limit (...)|time limit (...) */
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
    private static $shutdownDone = false;

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function dump($value, ?int $maxDepth = null, ?int $traceLength = null)
    {
        ob_start();

        $dump = Dumper::dump($value, $maxDepth, $traceLength);
        self::send(Packet::DUMP, $dump);

        self::checkAccidentalOutput(__FUNCTION__);

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function varDump($value, bool $colors = true)
    {
        ob_start();

        $dump = Dumper::varDump($value, $colors);
        self::send(Packet::DUMP, $dump);

        self::checkAccidentalOutput(__FUNCTION__);

        return $value;
    }

    public static function capture(callable $callback, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        ob_start();
        $callback();
        $value = ob_get_clean();

        if ($value === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in Debugger::capture(). ", Ansi::DRED);
            self::send(Packet::ERROR, $message);

            return '';
        }

        ob_start();

        $dump = Dumper::dump($value, $maxDepth, $traceLength);
        self::send(Packet::DUMP, $dump);

        self::checkAccidentalOutput(__FUNCTION__);

        return $value;
    }

    /**
     * @param string|int|float|bool|null $label
     * @return string|int|float|bool
     */
    public static function label($label, ?string $name = null)
    {
        ob_start();

        if ($label === null) {
            $label = 'null';
        } elseif ($label === false) {
            $label = 'false';
        } elseif ($label === true) {
            $label = 'true';
        }
        $message = Ansi::white($name ? " $name: $label " : " $label ", Ansi::DRED);

        self::send(Packet::LABEL, $message);

        self::checkAccidentalOutput(__FUNCTION__);

        return $label;
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

        if ($callstack instanceof Callstack) {
            // ok
        } elseif (is_array($callstack)) {
            $callstack = Callstack::fromBacktrace($callstack);
        } else {
            $callstack = Callstack::get(Dumper::$traceFilters);
        }
        $trace = Dumper::formatCallstack($callstack, $length, $argsDepth, $codeLines, $codeDepth);

        self::send(Packet::CALLSTACK, $trace);

        self::checkAccidentalOutput(__FUNCTION__);
    }

    public static function function(): void
    {
        ob_start();

        $frame = Callstack::get(['~Debugger::function$~', '~^rf$~'])->last();
        $class = $frame->class ?? null;
        $function = $frame->function ?? null;

        if ($class !== null) {
            $class = explode('\\', $class);
            $class = end($class);

            $message = Ansi::white(" $class::$function() ", Ansi::DRED);
        } else {
            $message = Ansi::white(" $function() ", Ansi::DRED);
        }

        self::send(Packet::CALLSTACK, $message);

        self::checkAccidentalOutput(__FUNCTION__);
    }

    /**
     * @param string|int|null $name
     */
    public static function timer($name = ''): void
    {
        ob_start();

        $name = (string) $name;

        if (isset(self::$timers[$name])) {
            $start = self::$timers[$name];
            self::$timers[$name] = microtime(true);
        } elseif (isset(self::$timers[''])) {
            $start = self::$timers[''];
            self::$timers[''] = microtime(true);
        } else {
            self::$timers[''] = microtime(true);
            return;
        }

        $time = Units::time(microtime(true) - $start);
        $name = $name ? 'Timer' . $name : 'Timer';
        $message = Ansi::white(" $name: $time ", Ansi::DGREEN);

        self::send(Packet::TIMER, $message);

        self::checkAccidentalOutput(__FUNCTION__);
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

        self::send(Packet::MEMORY, $message);

        self::checkAccidentalOutput(__FUNCTION__);
    }

    // internals -------------------------------------------------------------------------------------------------------

    public static function send(
        int $type,
        string $message,
        string $backtrace = '',
        ?float $duration = null
    ): void
    {
        if (!self::$connected) {
            self::init();
        }

        $message = str_replace(Packet::MARKER, "||||", $message);
        $packet = new Packet($type, $message, $backtrace, $duration);

        foreach (self::$beforeSend as $function) {
            if ($function($packet)) {
                return;
            }
        }

        if (self::$connection === self::CONNECTION_LOCAL) {
            self::$server->renderPacket($packet);
            return;
        }

        $packet = serialize($packet) . Packet::MARKER;

        if (self::$connection === self::CONNECTION_FILE) {
            $file = fopen(self::$logFile, 'a');
            if (!$file) {
                $m = error_get_last()['message'] ?? '???';
                self::error("Could not send data to debug server: $m");
                self::print($message . "\n" . $backtrace);
                return;
            }
            $result = fwrite($file, $packet);
            if (!$result) {
                $m = error_get_last()['message'] ?? '???';
                self::error("Could not send data to debug server: $m");
                self::print($message . "\n" . $backtrace);
                fclose($file);
                return;
            }
            fclose($file);

            return;
        }

        $result = @socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            $m = error_get_last()['message'] ?? '???';
            self::error("Could not send data to debug server: $m");
            self::print($message . "\n" . $backtrace);
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

    public static function dependencyInfo(string $message): void
    {
        if (self::$showDependenciesInfo) {
            $callstack = Callstack::get(Dumper::$traceFilters);
            self::send(Packet::INFO, Ansi::lmagenta($message), Dumper::formatCallstack($callstack, 1, 0, 0));
        }
    }

    public static function error(string $message): void
    {
        if (self::$connection === self::CONNECTION_SOCKET) {
            $message = sprintf("Dogma Debugger: $message. Debug server should be running on %s:%s.", self::$remoteAddress, self::$remotePort);
        } else {
            $message = sprintf("Dogma Debugger: $message. Log file is probably not writable: %s.", self::$logFile);
        }

        if (PHP_SAPI === 'cli') {
            echo Ansi::white($message) . "\n";
        } else {
            echo $message . "\n";
        }
    }

    public static function getStart(): float
    {
        return self::$timers['total'];
    }

    public static function setStart(float $time): void
    {
        self::$timers['total'] = $time;
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

        self::registerShutdown();
        self::$connected = true;

        if (!self::$initDone) {
            if (Packet::$count === 0) {
                $header = self::createHeader();
                $packet = new Packet(Packet::INTRO, $header);

                if (self::$connection === self::CONNECTION_LOCAL) {
                    self::$server->renderPacket($packet);
                } else {
                    $packet = serialize($packet) . Packet::MARKER;

                    if (self::$connection === self::CONNECTION_FILE) {
                        $file = fopen(self::$logFile, 'a');
                        if (!$file) {
                            return;
                        }
                        $result = fwrite($file, $packet);
                        if (!$result) {
                            return;
                        }
                    } else {
                        $result = @socket_write(self::$socket, $packet, strlen($packet));
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
        register_shutdown_function(static function (): void {
            if (!self::$shutdownDone) {
                self::$reserved = false;
                try {
                    foreach (self::$beforeShutdown as $callback) {
                        $callback();
                    }
                } finally {
                    self::send(Packet::OUTRO, self::createFooter());
                    self::$shutdownDone = true;
                }
            }
        });
    }

    private static function checkAccidentalOutput(string $function): void
    {
        $output = ob_get_clean();
        if ($output === "") {
            return;
        } elseif ($output === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in Debugger::$function(). ", Ansi::DRED);
        } else {
            $message = Ansi::white(' Accidental output: ', Ansi::DRED) . ' ' . Dumper::dumpValue($output);
        }

        self::send(Packet::ERROR, $message);
    }

    // header & footer -------------------------------------------------------------------------------------------------

    private static function createHeader(): string
    {
        global $argv;

        /** @var DateTime $dt */
        $dt = DateTime::createFromFormat('U.u', number_format(self::$timers['total'], 6, '.', ''));
        $time = $dt->format(self::$headerTimeFormat);
        $id = implode('/', array_filter(System::getIds()));
        $php = PHP_VERSION . ', ' . Request::$sapi;
        $header = "\n" . Ansi::white(" >> #$id $time | PHP $php ", self::$headerColor) . ' ';
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
                $header .= Ansi::white(" $method ", RequestHandler::$methodColors[strtolower($method)]) . ' ';
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
                    if (Str::startsWith($name, 'HTTP_')) {
                        $headers[Http::normalizeHeaderName($name)] = $value;
                    }
                }
                $header .= "\n" . Ansi::white("headers:") . ' ' . Dumper::dumpArray($headers);
            }

            // request body
            if (RequestHandler::$requestBody) {
                // todo
            }
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
                    $footer .= "   " . Dumper::key($name) . ': ' . Dumper::value($value) . "\n";
                }
            }
        }

        // common things (pid, output, time, memory)
        if (OutputHandler::enabled()) {
            OutputHandler::terminateAllOutputBuffers();
        }
        $outputLength = OutputHandler::getTotalLength();
        $output = $outputLength > 0 ? Units::memory($outputLength) . ', ' : '';
        $start = self::$timers['total'];
        $time = Units::time(microtime(true) - $start);
        $memory = Units::memory(memory_get_peak_usage(false));
        $id = implode('/', array_filter(System::getIds()));
        $footer .= Ansi::white(" << #$id, {$output}$time, $memory ", self::$headerColor);

        // includes io
        $events = 0;
        $time = 0.0;
        foreach (self::$footerStreamWrappers as $wrapper) {
            $stats = $wrapper::getStats(true);
            $events += $stats['events']['total'];
            $time += $stats['time']['total'];
        }
        if ($events > 0) {
            $time = Units::time($time);
            $footer .= Ansi::white("| inc: {$events}× $time ", self::$headerColor);
        }

        // stream wrappers io
        foreach (self::$footerStreamWrappers as $wrapper) {
            $ioStats = $wrapper::getStats();
            if ($ioStats['events']['fopen'] > 0) {
                $ioTime = Units::time($ioStats['time']['total']);
                $footer .= Ansi::white('| ' . $wrapper::NAME . ": {$ioStats['events']['fopen']}× $ioTime ", self::$headerColor);
            }
        }

        // database io
        $stats = SqlHandler::getStats();
        $conn = $stats['events']['connect'];
        if ($conn > 0) {
            $queries = $stats['events']['select'] + $stats['events']['insert'] + $stats['events']['update']
                + $stats['events']['delete'] + $stats['events']['query'];
            $sqlTime = Units::time($stats['time']['total']);
            $rows = $stats['rows']['total'];
            $footer .= Ansi::white("| db: $conn con, $queries q, $sqlTime, $rows rows ", self::$headerColor);
        }

        // redis io
        $stats = RedisHandler::getStats();
        $events = $stats['events']['total'];
        if ($events > 0) {
            $queries = $stats['events']['total'];
            $time = Units::time($stats['time']['total']);
            $data = Units::memory((int) $stats['data']['total']);
            $rows = $stats['rows']['total'];
            $footer .= Ansi::white("| redis: $queries q, $time, $data, $rows rows ", self::$headerColor);
        }

        // amqp io
        $stats = AmqpHandler::getStats();
        $events = $stats['events']['total'];
        if ($events > 0) {
            $queries = $stats['events']['total'];
            $time = Units::time($stats['time']['total']);
            $data = Units::memory((int) $stats['data']['total']);
            $rows = $stats['rows']['total'];
            $footer .= Ansi::white("| amqp: $queries q, $time, $data, $rows rows ", self::$headerColor);
        }

        // termination reason
        if (Resources::timeLimit() !== 0.0 && Resources::timeRemaining() < 0 && (self::$terminatedBy === null || self::$terminatedBy === 'signal (profiling)')) {
            $reason = 'time limit (' . Resources::timeLimit() . ' s)';
            $footer .= ' ' . Ansi::white(' ' . $reason . ' ', Ansi::LMAGENTA);
        } elseif (self::$terminatedBy !== null) {
            $footer .= ' ' . Ansi::white(' ' . self::$terminatedBy . ' ', Ansi::LMAGENTA);
        } elseif (PHP_SAPI !== 'cli' && !ignore_user_abort() && connection_aborted()) {
            $reason = connection_status() === CONNECTION_ABORTED ? 'connection aborted' : 'connection timeout';
            $footer .= ' ' . Ansi::white(' ' . $reason . ' ', Ansi::LMAGENTA);
        }

        // response code
        $status = http_response_code();
        if ($status !== false) {
            $message = Http::RESPONSE_MESSAGES[$status] ?? 'Unknown';
            $color = RequestHandler::$responseColors[$status] ?? Ansi::DYELLOW;
            foreach (RequestHandler::$responseColors as $code => $color) {
                if (Str::startsWith((string) $status, (string) $code)) {
                    break;
                }
            }
            $footer .= ' ' . Ansi::white(' ' . $status . ' ' . $message . ' ', $color);
        }

        // errors
        ErrorHandler::disable();
        $errors = ErrorHandler::getMessages();
        if ($errors !== []) {
            $count = ErrorHandler::getCount();
            $list = ErrorHandler::$listErrors ? ':' : '';
            $footer .= ' ' . Ansi::white($count > 1 ? " $count errors$list " : " 1 error$list ", Ansi::LRED);
            //$footer .= ' ' . self::$name;
            if ($list) {
                foreach ($errors as $error => $files) {
                    $file = $line = $count = null;
                    foreach ($files as $fileLine => $count) {
                        [$file, $line] = Str::splitByLast($fileLine, ':');
                    }
                    $footer .= "\n " . Ansi::white(array_sum($files) . '×') . ' ' . Ansi::lyellow($error);
                    if ($file !== '') {
                        $footer .= Dumper::info(' - e.g. in ') . Dumper::fileLine((string) $file, (int) $line)
                            . Dumper::info(' ' . $count . '×');
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

        $packet = serialize(new Packet(Packet::OUTPUT_WIDTH, '')) . Packet::MARKER;
        $result = socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            $m = error_get_last()['message'] ?? '???';
            self::error("Could not send data to debug server: $m");
        }

        $content = socket_read(self::$socket, 10000);
        if ($content === false) {
            self::$outputWidth = 120;
        } else {
            foreach (explode(Packet::MARKER, $content) as $message) {
                if (!$message) {
                    continue;
                }
                /** @var Packet $response */
                $response = unserialize($message, ['allowed_classes' => [Packet::class]]);
                if ($response->type === Packet::OUTPUT_WIDTH) {
                    self::$outputWidth = (int) $response->payload;
                }
            }
        }

        return self::$outputWidth;
    }

}
