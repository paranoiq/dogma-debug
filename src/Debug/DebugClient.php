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
use Dogma\Debug\Colors as C;
use function explode;
use function memory_get_peak_usage;
use function number_format;
use function round;
use function serialize;
use function socket_read;
use function socket_write;
use function sprintf;
use function strlen;
use function unserialize;

class DebugClient
{

    /** @var int */
    public static $counter = 0;

    /** @var float[] */
    public static $timers = [];

    /** @var string */
    public static $remoteAddress = '127.0.0.1';

    /** @var int */
    public static $remotePort = 1729;

    /** @var int|null */
    public static $outputWidth;

    /** @var Socket|resource */
    private static $socket;

    public static function getOutputWidth(): int
    {
        if (self::$outputWidth !== null) {
            return self::$outputWidth;
        }

        if (self::$socket === null) {
            self::remoteConnect();
        }

        $packet = serialize(new Packet(Packet::OUTPUT_WIDTH, '')) . Packet::MARKER;
        $result = socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            die("Could not send data to debug server.\n");
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

    public static function remoteWrite(int $type, string $message, string $backtrace = '', ?float $duration = null): void
    {
        if (self::$socket === null) {
            self::remoteConnect();
        }

        if (self::$counter === 0) {
            $header = self::requestHeader();
            $packet = serialize(new Packet(Packet::INTRO, $header)) . Packet::MARKER;
            $result = socket_write(self::$socket, $packet, strlen($packet));
            if (!$result) {
                die("Could not send data to debug server.\n");
            }
        }

        $packet = serialize(new Packet($type, $message, $backtrace, $duration)) . Packet::MARKER;
        $result = socket_write(self::$socket, $packet, strlen($packet));
        if (!$result) {
            die("Could not send data to debug server.\n");
        }
    }

    private static function requestHeader(): string
    {
        global $argv;

        $dt = new DateTime();
        $time = $dt->format('Y-m-d H:i:s');
        $version = PHP_VERSION;
        $sapi = PHP_SAPI;
        $header = "\n" . C::color(" $time PHP $version $sapi ", C::WHITE, C::DBLUE) . " ";

        if ($sapi === 'cli') {
            $args = $argv;
            $args[0] = Dumper::file($args[0]);
            $header .= implode(' ', $args);
            $process = getmypid();
            $header .= ' ' . C::dgray("(pid: $process)") . ' ';
        } else {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $header .= 'AJAX ';
            }
            if (isset($_SERVER['REQUEST_METHOD'])) {
                $header .= $_SERVER['REQUEST_METHOD'] . ' ';
            }
            if (!empty($_SERVER['REQUEST_URI'])) {
                $header .= self::highlightUrl($_SERVER['REQUEST_URI']) . ' ';
            }
        }

        return C::pad($header, self::getOutputWidth() - 2, '-');
    }

    private static function highlightUrl(string $url): string
    {
        $url = (string) preg_replace('/([a-zA-Z0-9_-]+)=/', C::dyellow('$1') . '=', $url);
        $url = (string) preg_replace('/=([a-zA-Z0-9_-]+)/', '=' . C::lcyan('$1'), $url);
        $url = (string) preg_replace('/[\\/?&=]/', C::dgray('$0'), $url);

        return $url;
    }

    private static function remoteConnect(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            die("Could not create socket to debug server.\n");
        }
        self::$socket = $socket;

        $result = @socket_connect(self::$socket, self::$remoteAddress, self::$remotePort);
        if (!$result) {
            echo C::lred(sprintf("Could not connect to debug server. Should be running on %s:%s.", self::$remoteAddress, self::$remotePort)) . "\n";
            die();
        }

        register_shutdown_function(static function (): void {
            static $done = false;
            if (!$done) {
                $start = self::$timers['total'];
                $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
                $memory = number_format(memory_get_peak_usage(true) / 1000000, 3, '.', ' ');

                $stats = FileStreamWrapper::getStats();
                $userTime = round($stats['userTime']['total'] * 1000, 3);
                $includeTime = round($stats['includeTime']['total'] * 1000, 3);

                $message = "$time ms | $memory MB | inc: {$stats['includeEvents']['open']}× $includeTime ms | i/o: {$stats['userEvents']['open']}× $userTime ms";
                self::remoteWrite(Packet::OUTRO, C::color(" $message ", C::WHITE, C::DBLUE));

                $done = true;
            }
        });
    }

}
