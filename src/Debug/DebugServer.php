<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: WSAEWOULDBLOCK

namespace Dogma\Debug;

use Dogma\Debug\Colors as C;
use function exec;
use function explode;
use function round;
use function serialize;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_listen;
use function socket_read;
use function socket_set_nonblock;
use function socket_write;
use function strpos;
use function strtolower;
use function trim;
use function unserialize;
use function usleep;
use const AF_INET;
use const PHP_OS;
use const SOCK_STREAM;
use const SOL_TCP;

class DebugServer
{

    /** @var int */
    private $port;

    /** @var string */
    private $address;

    /** @var resource */
    private $sock;

    /** @var int */
    private $columns;

    /** @var Packet|null */
    private $lastRequest;

    /** @var float */
    private $durationSum = 0.0;

    public function __construct(int $port, string $address)
    {
        $this->port = $port;
        $this->address = $address;
    }

    public function run(): void
    {
        $this->connect();

        $connections = [];
        while (true) {
            $newConnection = socket_accept($this->sock);
            if ($newConnection) {
                $connections[] = $newConnection;
            }

            foreach ($connections as $i => $connection) {
                $content = socket_read($connection, 1000000);
                if ($content === false) {
                    if (socket_last_error() === 10035) { // Win: WSAEWOULDBLOCK
                        continue;
                    }
                    socket_close($connection);
                    unset($connections[$i]);
                } elseif ($content) {
                    $this->processRequest($content, $connection);
                }
            }

            usleep(20);
        }
    }

    /**
     * @param string $content
     * @param resource $connection
     */
    private function processRequest(string $content, $connection): void
    {
        foreach (explode(Packet::MARKER, $content) as $message) {
            if ($message === '') {
                continue;
            }

            /** @var Packet $request */
            $request = unserialize($message, ['allowed_classes' => [Packet::class]]);
            if ($request === false) {
                echo $message;
                continue;
            }

            if ($request->type === Packet::OUTPUT_WIDTH) {
                $response = serialize(new Packet(Packet::OUTPUT_WIDTH, (string) $this->getTerminalWidth()));
                socket_write($connection, $response . Packet::MARKER);
                continue;
            }

            // delete previous backtrace to save space
            if ($this->lastRequest
                && $request->backtrace
                && $this->lastRequest->backtrace === $request->backtrace
                && $this->lastRequest->type === $request->type
            ) {
                echo "\x1B[A"; // up
                echo "\x1B[K"; // delete
                $this->durationSum += $request->duration;
            } else {
                $this->durationSum = $request->duration;
            }

            echo $request->payload . "\n";

            echo $request->backtrace;
            if ($request->backtrace && $this->durationSum !== $request->duration) {
                echo ' ' . C::color('(total ' . round($this->durationSum * 1000000) . ' μs)', Dumper::$colors['time']) . "\n";
            } elseif ($request->backtrace) {
                echo "\n";
            }

            $this->lastRequest = $request;
        }
    }

    private function connect(): void
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->sock) {
            echo C::lred("Could not create socket.\n");
            exit(1);
        }
        if (!socket_bind($this->sock, $this->address, $this->port)) {
            echo C::lred("Could not bind to address.\n");
            exit(1);
        }
        if (!socket_listen($this->sock, 20)) {
            echo C::lred("Could not listen on socket.\n");
            exit(1);
        }
        if (!socket_set_nonblock($this->sock)) {
            echo C::lred("Could not set socket to non-blocking.\n");
            exit(1);
        }

        $this->switchTerminalToUtf8();

        echo "Listening on port " . C::white($this->port) . "\n";
    }

    private function getTerminalWidth(): int
    {
        if ($this->columns) {
            return $this->columns;
        }

        if ($this->isWindows()) {
            exec('mode CON', $output);
            [, $this->columns] = explode(':', $output[4]);
            $this->columns = (int) trim($this->columns);
        } else {
            $this->columns = (int) exec('/usr/bin/env tput cols');
        }

        if (!$this->columns) {
            $this->columns = 120;
        }

        return $this->columns;
    }

    private function switchTerminalToUtf8(): void
    {
        if ($this->isWindows()) {
            exec('chcp 65001');
        }
    }

    private function isWindows(): bool
    {
        $os = strtolower(PHP_OS);

        return strpos($os, 'win') !== false && strpos($os, 'darwin') === false;
    }

}