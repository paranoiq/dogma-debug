<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use DateTime;
use function array_shift;
use function array_sum;
use function end;
use function error_get_last;
use function explode;
use function getmypid;
use function headers_list;
use function http_response_code;
use function implode;
use function memory_get_peak_usage;
use function microtime;
use function number_format;
use function ob_get_clean;
use function ob_start;
use function register_shutdown_function;
use function serialize;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_write;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function ucfirst;
use function unserialize;
use const AF_INET;
use const PHP_SAPI;
use const PHP_VERSION;
use const SOCK_STREAM;
use const SOL_TCP;

class DebugClient
{

    /** @var string */
    public static $remoteAddress = '127.0.0.1';

    /** @var int */
    public static $remotePort = 1729;

    /** @var int|null */
    public static $outputWidth = 200;

    /** @var string */
    public static $timeFormat = 'D H:i:s.v';

    /** @var string */
    public static $headerColor = Ansi::LYELLOW;

    /** @var int */
    public static $counter = 0;

    /** @var float[] */
    public static $timers = [];

    /** @var Socket|resource */
    private static $socket;

    /** @var bool */
    private static $initDone = false;

    /** @var bool */
    private static $shutdownDone = false;

    /**
     * @param mixed $value
     * @param int|bool $depth
     * @return mixed
     */
    public static function dump($value, $depth = 5, int $trace = 1)
    {
        ob_start();

        $dump = Dumper::dump($value, $depth, $trace);
        self::send(Packet::DUMP, $dump);

        self::handleAccidentalOutput(ob_get_clean());

        return $value;
    }

    /**
     * @param int|bool $depth
     */
    public static function capture(callable $callback, $depth = 5, int $trace = 1): string
    {
        ob_start();
        $callback();
        $value = ob_get_clean();

        ob_start();

        $dump = Dumper::dump($value, $depth, $trace);
        self::send(Packet::DUMP, $dump);

        self::handleAccidentalOutput(ob_get_clean());

        return $value;
    }

    /**
     * @param mixed $label
     * @return mixed
     */
    public static function label($label)
    {
        ob_start();

        if ($label === null) {
            $label = 'null';
        } elseif ($label === false) {
            $label = 'false';
        } elseif ($label === true) {
            $label = 'true';
        }
        $message = Ansi::white(" $label ", Ansi::DRED);

        self::send(Packet::LABEL, $message);

        self::handleAccidentalOutput(ob_get_clean());

        return $label;
    }

    /**
     * @param int[] $lines
     */
    public static function backtrace(?int $length = null, ?int $argsDepth = null, array $lines = []): void
    {
        ob_start();

        $callstack = Callstack::get()->filter(Dumper::$traceSkip);
        $trace = Dumper::formatCallstack($callstack, $length, $argsDepth, $lines);

        self::send(Packet::TRACE, $trace);

        self::handleAccidentalOutput(ob_get_clean());
    }

    public static function function(): void
    {
        ob_start();

        $frame = Callstack::get()->filter(Dumper::$traceSkip)->last();
        $class = $frame->class ?? null;
        $function = $frame->function ?? null;

        if ($class !== null) {
            $class = explode('\\', $class);
            $class = end($class);

            $message = Ansi::white(" $class::$function() ", Ansi::DRED);
        } else {
            $message = Ansi::white(" $function() ", Ansi::DRED);
        }

        self::send(Packet::TRACE, $message);

        self::handleAccidentalOutput(ob_get_clean());
    }

    /**
     * @param string|int|null $label
     */
    public static function timer($label = ''): void
    {
        ob_start();

        $label = (string) $label;

        if (isset(self::$timers[$label])) {
            $start = self::$timers[$label];
            self::$timers[$label] = microtime(true);
        } elseif (isset(self::$timers[null])) {
            $start = self::$timers[null];
            self::$timers[null] = microtime(true);
        } else {
            self::$timers[null] = microtime(true);
            return;
        }

        $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
        $label = $label ? ucfirst($label) : 'Timer';
        $message = Ansi::white(" $label: $time ms ", Ansi::DGREEN);

        self::send(Packet::TIMER, $message);

        self::handleAccidentalOutput(ob_get_clean());
    }

    // internals -------------------------------------------------------------------------------------------------------

    public static function send(int $type, string $message, string $backtrace = '', ?float $duration = null): void
    {
        if (!self::$initDone) {
            self::init();
        }

        $message = str_replace(Packet::MARKER, "||||", $message);

        $packet = serialize(new Packet($type, $message, $backtrace, $duration)) . Packet::MARKER;
        $result = @socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            $m = error_get_last()['message'] ?? '???';
            self::error(trim("Could not send data to debug server: $m", '.') . '.');
        }
    }

    /**
     * When called by user, init() logs request start/end even if no other debug events are being logged.
     * Otherwise, debugger is initiated automatically with first error or user event.
     */
    public static function init(): void
    {
        if (self::$socket === null) {
            self::connect();
        }

        if (!self::$initDone) {
            if (self::$counter === 0) {
                $header = self::createHeader();
                $packet = serialize(new Packet(Packet::INTRO, $header)) . Packet::MARKER;
                $result = @socket_write(self::$socket, $packet, strlen($packet));
                if (!$result) {
                    return;
                }
            }

            self::$initDone = true;
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
            return;
        }

        register_shutdown_function(static function (): void {
            if (!self::$shutdownDone) {
                self::$shutdownDone = true;
                self::send(Packet::OUTRO, self::createFooter());
            }
        });
    }

    private static function handleAccidentalOutput(string $output): void
    {
        if ($output === "") {
            return;
        }

        $message = Ansi::white(' Accidental output: ', Ansi::DRED) . ' ' . Dumper::dumpValue($output);

        self::send(Packet::ERROR, $message);
    }

    private static function error(string $message): void
    {
        $message = sprintf("Dogma Debugger: $message. Debug server should be running on %s:%s.", self::$remoteAddress, self::$remotePort);

        echo Ansi::white($message) . "\n";
    }

    private static function createHeader(): string
    {
        global $argv;

        $dt = DateTime::createFromFormat('U.u', number_format(self::$timers['total'], 6, '.', ''));
        $time = $dt->format(self::$timeFormat);
        $version = PHP_VERSION;
        $sapi = str_replace('handler', '', PHP_SAPI);
        $header = "\n" . Ansi::white(" >> $time | PHP $version", self::$headerColor);

        if ($sapi === 'cli') {
            $pid = getmypid(); // . ' (' . cli_get_process_title() . ')';
            $header .= Ansi::white(", $sapi, #$pid ", self::$headerColor) . ' ';

            $args = $argv;
            $args[0] = Dumper::file($args[0]);
            $header .= implode(' ', $args) . ' ';
        } else {
            $header .= Ansi::white(", $sapi ", self::$headerColor) . ' ';

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $header .= Ansi::white(' AJAX ', Http::$methodColors['ajax']) . ' ';
            }
            if (isset($_SERVER['REQUEST_METHOD'])) {
                $header .= Ansi::white(' ' . $_SERVER['REQUEST_METHOD'] . ' ', Http::$methodColors[strtolower($_SERVER['REQUEST_METHOD'])]) . ' ';
            }
            if (!empty($_SERVER['SCRIPT_URI'])) {
                $header .= Dumper::highlightUrl($_SERVER['SCRIPT_URI']) . ' ';
            }
        }

        // request headers
        if (IoHandler::$requestHeaders) {
            // todo
        }

        // request body
        if (IoHandler::$requestBody) {
            // todo
        }

        return Ansi::pad($header, self::getOutputWidth() - 2, '-');
    }

    private static function createFooter(): string
    {
        $footer = '';

        // last error
        if (ErrorHandler::$showLastError) {
            $e = error_get_last();
            if ($e !== null) {
                rd($e);
            }
        }

        // response headers
        if (IoHandler::$responseHeaders) {
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

        // common things
        $sapi = str_replace('handler', '', PHP_SAPI);
        $pid = $sapi === 'cli' ? ', #' . getmypid() : '';
        IoHandler::terminateAllOutputBuffers();
        $outputLength = IoHandler::getTotalLength();
        $output = $outputLength > 0 ? number_format($outputLength / 1024) . ' kB, ' : '';
        $start = self::$timers['total'];
        $time = number_format((microtime(true) - $start) * 1000, 0, '.', ' ');
        $memory = number_format(memory_get_peak_usage(true) / 1024**2, 0, '.', ' ');

        $footer .= Ansi::white(" << {$output}$time ms, $memory MB{$pid} ", self::$headerColor);

        // file io
        $stats = FileHandler::getStats();
        $includeTime = number_format($stats['includeTime']['total'] * 1000);
        if ($includeTime > 0.000001) {
            $footer .= Ansi::white("| inc: {$stats['includeEvents']['open']}× $includeTime ms ", self::$headerColor);
        }
        $userTime = number_format($stats['userTime']['total'] * 1000);
        if ($userTime > 0.000001) {
            $footer .= Ansi::white("| file: {$stats['userEvents']['open']}× $userTime ms ", self::$headerColor);
        }

        // database
        $stats = SqlHandler::getStats();
        $conn = $stats['events']['connect'];
        if ($conn > 0) {
            $queries = $stats['events']['select'] + $stats['events']['insert'] + $stats['events']['update']
                + $stats['events']['delete'] + $stats['events']['query'];
            $sqlTime = number_format($stats['time']['total'] * 1000);
            $rows = $stats['rows']['total'];
            $footer .= Ansi::white("| db: $conn conn, $queries quer, $sqlTime ms, $rows rows ", self::$headerColor);
        }

        // response code
        $status = http_response_code();
        if ($status !== false) {
            $message = Http::RESPONSE_MESSAGES[$status] ?? 'Unknown';
            $color = Http::$responseColors[$status] ?? Ansi::DYELLOW;
            foreach (Http::$responseColors as $code => $color) {
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
            if ($list) {
                foreach ($errors as $error => $files) {
                    $file = $line = $count = null;
                    foreach ($files as $fileLine => $count) {
                        [$file, $line] = explode(':', $fileLine);
                    }
                    $footer .= "\n " . Ansi::white(array_sum($files) . '×') . ' ' . Ansi::lyellow($error);
                    if ($file !== '') {
                        $footer .= Dumper::info(' - eg in ') . Dumper::fileLine((string)$file, (int)$line) . Dumper::info(' ' . $count . '×');
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

        if (self::$socket === null) {
            self::connect();
        }

        $packet = serialize(new Packet(Packet::OUTPUT_WIDTH, '')) . Packet::MARKER;
        $result = socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            self::error("Could not send data to debug server.");
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
