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
use function strlen;

class DebugClient
{

    /** @var Socket|resource */
    private static $socket;

    /** @var int */
    public static $counter;

    /** @var float[] */
    public static $timers = [];

    public static function remoteWrite(string $message): void
    {
        global $argv;

        if (self::$socket === null) {
            self::remoteConnect();
        }

        if (self::$counter === null) {
            $header = self::requestHeader();
            $message = $header . "\n" . $message;
            var_dump($_SERVER['PHP_SELF']);
            var_dump($argv);
        }

        $result = socket_write(self::$socket, $message, strlen($message));
        if (!$result) {
            die("Could not send data to debug server.\n");
        }

        self::$counter++;
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

        return C::pad($header, 120, '-');
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

        $result = @socket_connect(self::$socket, '127.0.0.1', 6666);
        if (!$result) {
            echo C::lred("Could not connect to debug server. Should be running on port 6666.") . "\n";
            die();
        }

        register_shutdown_function(static function (): void {
            static $done = false;
            if (!$done) {
                $start = self::$timers['total'];
                $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
                $memory = number_format(memory_get_peak_usage(true) / 1000000, 3, '.', ' ');
                $message = "$time ms, $memory MB";
                self::remoteWrite(C::color(" $message ", C::WHITE, C::DBLUE) . "\n");

                $done = true;
            }
        });
    }

}
