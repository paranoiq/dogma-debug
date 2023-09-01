<?php
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
use function clearstatcache;
use function count;
use function explode;
use function extension_loaded;
use function fclose;
use function file_exists;
use function filesize;
use function fopen;
use function fread;
use function fseek;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_listen;
use function socket_read;
use function socket_set_nonblock;
use function socket_write;
use function str_replace;
use function strlen;
use function usleep;
use const AF_INET;
use const SOCK_STREAM;
use const SOL_TCP;

class DebugServer
{

    /** @var bool */
    private $useSockets;

    /** @var int */
    private $port;

    /** @var string */
    private $address;

    /** @var resource|Socket|null */
    private $socket;

    /** @var string */
    private $file;

    /** @var bool */
    private $groupInfo = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private $position = 0;

    /** @var resource[]|Socket[] */
    private $connections = [];

    /** @var array<int, int|string> */
    private $ids = [];

    /** @var Message|null */
    private $lastRequest;

    /** @var float */
    private $durationSum = 0.0;

    public function __construct(int $port, string $address, string $file)
    {
        $this->port = $port;
        $this->address = $address;
        $this->file = str_replace('\\', '/', $file);
        $this->useSockets = extension_loaded('sockets');
    }

    /**
     * @return never-return
     */
    public function run(): void
    {
        System::switchTerminalToUtf8();

        if ($this->useSockets && $this->socket) {
            $this->socketsConnect();
            echo "Listening on port " . Ansi::white($this->port) . " and watching " . Ansi::white($this->file) . "\n";
        } else {
            echo "Sockets are unavailable. Watching " . Ansi::white($this->file) . "\n";
        }

        while (true) {
            if ($this->useSockets && $this->socket) {
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

                    $outro = $this->processRequests($content, $i);
                    if ($outro) {
                        socket_close($connection);
                        unset($this->connections[$i]);
                    }
                }
            }

            clearstatcache();
            if (file_exists($this->file)) {
                $size = filesize($this->file);
                if ($size !== $this->position) {
                    $file = fopen($this->file, 'r');
                    if ($file === false) {
                        // todo
                        continue;
                    }
                    if ($size < $this->position) {
                        $this->position = 0;
                    } else {
                        fseek($file, $this->position);
                    }
                    $content = fread($file, 10000000);
                    if ($content === false) {
                        // todo
                        fclose($file);
                        continue;
                    } else {
                        $this->processRequests($content, 0); // todo
                        $this->position += strlen($content);
                    }
                    fclose($file);
                }
            }

            usleep(1000000);
        }
    }

    private function processRequests(string $content, int $i): bool
    {
        $outro = false;
        foreach (explode("\x04", $content) as $data) {
            if ($data === '') {
                continue;
            }

            $message = Message::decode($data);

            // handle server -> client communication
            if ($message->type === Message::OUTPUT_WIDTH) {
                $response = Message::create(Message::OUTPUT_WIDTH, (string) System::getTerminalWidth())->encode();
                socket_write($this->connections[$i], $response);
                //stream_socket_sendto($this->sock, $response, 0, $connection);
                continue;
            }

            $this->ids[$i] = $message->processId;

            $this->renderMessage($message);

            if ($message->type === Message::OUTRO) {
                $outro = true;
            }
        }

        return $outro;
    }

    public function renderMessage(Message $message): void
    {
        // delete previous backtrace to save space
        if ($this->groupInfo
            && $this->lastRequest
            && $message->backtrace
            && $this->lastRequest->backtrace === $message->backtrace
            && $this->lastRequest->type === $message->type
        ) {
            echo Ansi::DELETE_ROW;
            echo Ansi::UP;
            $this->durationSum += $message->duration;
        } else {
            $this->durationSum = $message->duration;
        }

        // process id
        if (count($this->connections) > 1 && $message->type !== Message::INTRO && $message->type !== Message::OUTRO) {
            echo "\n" . Ansi::white(" #$message->processId ", Ansi::DYELLOW) . ' ';
        } else {
            echo "\n";
        }

        // payload
        echo $message->payload;
        if ($message->bell) {
            echo "\x07";
        }

        // duration
        if ($message->duration > 0.000000000001) {
            echo ' ' . Ansi::dblue('(' . Units::time($message->duration) . ')');
        }

        // backtrace
        if ($message->backtrace) {
            echo "\n" . $message->backtrace;
        }

        // duration sum for similar request from same place
        if ($message->backtrace && $this->durationSum !== $message->duration) {
            if ($this->durationSum > 0.000000000001) {
                echo ' ' . Ansi::dblue('(total ' . Units::time($this->durationSum) . ')');
            }
        }

        $this->lastRequest = $message;
    }

    private function socketsConnect(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo Ansi::lred("Could not create socket.\n\n");
            return;
        }
        if (@!socket_bind($socket, $this->address, $this->port)) {
            echo Ansi::lred("Could not bind to address {$this->address}:{$this->port}.\n\n");
            return;
        }
        if (!socket_listen($socket, 20)) {
            echo Ansi::lred("Could not listen on socket {$this->address}:{$this->port}.\n\n");
            return;
        }
        if (!socket_set_nonblock($socket)) {
            echo Ansi::lred("Could not set socket to non-blocking.\n\n");
            return;
        }
        $this->socket = $socket;
    }

}
