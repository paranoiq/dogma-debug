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
use function unserialize;
use function usleep;
use const AF_INET;
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
                echo ">>>" . $message . Ansi::RESET_FORMAT . "<<<";
                continue;
            }

            if ($request->type === Packet::OUTPUT_WIDTH) {
                $response = serialize(new Packet(Packet::OUTPUT_WIDTH, (string) System::getTerminalWidth()));
                socket_write($connection, $response . Packet::MARKER);
                continue;
            }

            // delete previous backtrace to save space
            if ($this->lastRequest
                && $request->backtrace
                && $this->lastRequest->backtrace === $request->backtrace
                && $this->lastRequest->type === $request->type
            ) {
                echo Ansi::UP;
                echo Ansi::DELETE_ROW;
                $this->durationSum += $request->duration;
            } else {
                $this->durationSum = $request->duration;
            }

            echo $request->payload . "\n";

            echo $request->backtrace;
            if ($request->backtrace && $this->durationSum !== $request->duration) {
                if ($this->durationSum > 0.000000000001) {
                    echo ' ' . Ansi::color('(total ' . round($this->durationSum * 1000000) . ' μs)', Dumper::$colors['time']) . "\n";
                }
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
            echo Ansi::lred("Could not create socket.\n");
            exit(1);
        }
        if (@!socket_bind($this->sock, $this->address, $this->port)) {
            echo Ansi::lred("Could not bind to address.\n");
            exit(1);
        }
        if (!socket_listen($this->sock, 20)) {
            echo Ansi::lred("Could not listen on socket.\n");
            exit(1);
        }
        if (!socket_set_nonblock($this->sock)) {
            echo Ansi::lred("Could not set socket to non-blocking.\n");
            exit(1);
        }

        System::switchTerminalToUtf8();

        echo "Listening on port " . Ansi::white($this->port) . "\n";
    }

}