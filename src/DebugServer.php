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

use Socket;
use const AF_INET;
use const SOCK_STREAM;
use const SOL_TCP;
use function count;
use function explode;
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

class DebugServer
{

    /** @var int */
    private $port;

    /** @var string */
    private $address;

    /** @var resource|Socket */
    private $socket;

    /** @var resource[]|Socket[] */
    private $connections = [];

    /** @var array<int, string> */
    private $ids = [];

    /** @var bool */
    private $groupInfo = true;

    /** @var Packet|null */
    private $lastRequest;

    /** @var float */
    private $durationSum = 0.0;

    public function __construct(int $port, string $address)
    {
        $this->port = $port;
        $this->address = $address;
    }

    /**
     * @return never-return
     */
    public function run(): void
    {
        $this->connect();

        while (true) {
            $newConnection = socket_accept($this->socket);
            if ($newConnection) {
                //socket_set_nonblock($newConnection);
                $this->connections[] = $newConnection;
            }

            foreach ($this->connections as $i => $connection) {
                $content = @socket_read($connection, 1000000);
                if ($content === false) {
                    if (socket_last_error() === 10035) { // Win: WSAEWOULDBLOCK
                        continue;
                    }
                    // closed
                    echo "\n" . Ansi::white(" << #{$this->ids[$i]} disconnected ", Ansi::DYELLOW);
                    socket_close($connection);
                    unset($this->connections[$i]);
                    unset($this->ids[$i]);
                    continue;
                } elseif ($content === '') {
                    // nothing to read
                    continue;
                }

                $outro = $this->processRequest($content, $i);
                if ($outro) {
                    socket_close($connection);
                    unset($this->connections[$i]);
                }
            }
            /*$content = stream_socket_recvfrom($this->sock, 1, 0, $peer);
            if ($content) {
                $this->processRequest($content, $peer);
            }*/

            usleep(20);
        }
    }

    private function processRequest(string $content, int $i): bool
    {
        $outro = false;
        foreach (explode(Packet::MARKER, $content) as $message) {
            if ($message === '') {
                continue;
            }

            /** @var Packet|false $packet */
            $packet = unserialize($message, ['allowed_classes' => [Packet::class]]);
            // broken serialization - probably too big packet split to chunks
            if ($packet === false) {
                echo ">>>" . $message . Ansi::RESET_FORMAT . "<<<";
                continue;
            }

            // handle server -> client communication
            if ($packet->type === Packet::OUTPUT_WIDTH) {
                $response = serialize(new Packet(Packet::OUTPUT_WIDTH, (string) System::getTerminalWidth()));
                socket_write($this->connections[$i], $response . Packet::MARKER);
                //stream_socket_sendto($this->sock, $response, 0, $connection);
                continue;
            }

            $this->ids[$i] = $packet->pid;

            $this->renderPacket($packet);

            if ($packet->type === Packet::OUTRO) {
                $outro = true;
            }
        }

        return $outro;
    }

    public function renderPacket(Packet $packet): void
    {
        // delete previous backtrace to save space
        if ($this->groupInfo
            && $this->lastRequest
            && $packet->backtrace
            && $this->lastRequest->backtrace === $packet->backtrace
            && $this->lastRequest->type === $packet->type
        ) {
            echo Ansi::DELETE_ROW;
            echo Ansi::UP;
            $this->durationSum += $packet->duration;
        } else {
            $this->durationSum = $packet->duration;
        }

        // process id
        if (count($this->connections) > 1 && $packet->type !== Packet::INTRO && $packet->type !== Packet::OUTRO) {
            echo "\n" . Ansi::white(" #$packet->pid ", Ansi::DYELLOW) . ' ';
        } else {
            echo "\n";
        }

        // payload
        echo $packet->payload;

        // duration
        if ($packet->duration > 0.000000000001) {
            echo ' ' . Ansi::dblue('(' . Units::time($packet->duration) . ')');
        }

        // backtrace
        if ($packet->backtrace) {
            echo "\n" . $packet->backtrace;
        }

        // duration sum for similar request from same place
        if ($packet->backtrace && $this->durationSum !== $packet->duration) {
            if ($this->durationSum > 0.000000000001) {
                echo ' ' . Ansi::dblue('(total ' . Units::time($this->durationSum) . ')');
            }
        }

        $this->lastRequest = $packet;
    }

    private function connect(): void
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        //$sock = stream_socket_server("tcp://$this->address:$this->port");
        if ($sock === false) {
            echo Ansi::lred("Could not create socket.\n");
            exit(1);
        }
        $this->socket = $sock;
        if (@!socket_bind($this->socket, $this->address, $this->port)) {
            echo Ansi::lred("Could not bind to address.\n");
            exit(1);
        }
        if (!socket_listen($this->socket, 20)) {
            echo Ansi::lred("Could not listen on socket.\n");
            exit(1);
        }
        if (!socket_set_nonblock($this->socket)) {
            echo Ansi::lred("Could not set socket to non-blocking.\n");
            exit(1);
        }
        //stream_set_blocking($this->sock, false);

        System::switchTerminalToUtf8();

        echo "Listening on port " . Ansi::white($this->port) . "\n";
    }

}
