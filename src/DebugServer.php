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

use DateTime;
use DateTimeZone;
use Socket;
use function array_diff_key;
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
use function in_array;
use function ltrim;
use function number_format;
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

    /** @var bool */
    private $alwaysShowPids = false;

    /** @var bool */
    private $checkDeadProcesses = true;

    /** @var string */
    private $defaultPidColor = Color::NAMED_COLORS['tomato'];

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private $position = 0;

    /** @var resource[]|Socket[] */
    private $connections = [];

    /** @var array<int, int> */
    private $connectionPids = [];

    /** @var array<int, bool> */
    private $logPids = [];

    /** @var array<int, bool> */
    private $deadPids = [];

    /** @var array<int, string> */
    private $pidColors = [];

    /** @var array<int, string> */
    private $oldPidColors = [];

    /** @var array<string> */
    private $unusedPidColors;

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

        $colors = array_diff_key(Color::NAMED_COLORS, Color::NAMED_COLORS_4BIT);
        $this->unusedPidColors = Color::filterByLightness($colors, 25, 90);
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

                foreach ($this->connections as $connectionId => $connection) {
                    $content = @socket_read($connection, 1000000);
                    if ($content === false) {
                        if (socket_last_error() === 10035) { // Win: WSAEWOULDBLOCK
                            continue;
                        }
                        // closed
                        echo "\n" . Ansi::black(" #{$this->connectionPids[$connectionId]} END process disconnected ", Ansi::DYELLOW);
                        socket_close($connection);
                        $this->removePid($this->connectionPids[$connectionId], $connectionId);
                        unset($this->connections[$connectionId]);
                        continue;
                    } elseif ($content === '') {
                        // nothing to read
                        continue;
                    }

                    $outro = $this->processRequests($content, $connectionId);
                    if ($outro) {
                        socket_close($connection);
                        unset($this->connections[$connectionId]);
                    }
                }
            }

            clearstatcache();
            if (file_exists($this->file)) {
                $size = filesize($this->file);
                if ($size !== $this->position) {
                    $file = fopen($this->file, 'rb');
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
                        $this->processRequests($content, null);
                        $this->position += strlen($content);
                    }
                    fclose($file);
                }
            }

            if ($this->checkDeadProcesses) {
                // processed with 1 round trip delay to prevent race conditions
                foreach ($this->deadPids as $pid => $x) {
                    if (isset($this->logPids[$pid])) {
                        echo "\n" . Ansi::black(" #{$pid} END process ended without sending outro ", Ansi::DYELLOW);
                        $this->removePid($pid, null);
                    }
                }

                foreach ($this->logPids as $pid => $x) {
                    if (!System::processExists($pid)) {
                        $this->deadPids[$pid] = true;
                    }
                }
            }

            usleep(100000);
        }
    }

    private function addPid(int $pid, ?int $connectionId): void
    {
        if ($connectionId !== null) {
            if (!isset($this->connectionPids[$connectionId])) {
                $this->connectionPids[$connectionId] = $pid;

                [$color, $key] = Color::pickMostDistant($this->unusedPidColors, $this->pidColors, $this->defaultPidColor);
                unset($this->unusedPidColors[$key]);
                $this->pidColors[$pid] = $color;
            }
        } else {
            $this->logPids[$pid] = true;

            [$color, $key] = Color::pickMostDistant($this->unusedPidColors, $this->pidColors, $this->defaultPidColor);
            unset($this->unusedPidColors[$key]);
            $this->pidColors[$pid] = $color;
        }
    }

    private function removePid(int $pid, ?int $connectionId): void
    {
        if ($connectionId !== null) {
            unset($this->connectionPids[$connectionId]);
        } else {
            unset($this->logPids[$pid], $this->deadPids[$pid]);
        }
        $color = $this->pidColors[$pid] ?? $this->defaultPidColor;
        $this->oldPidColors[$pid] = $color;
        unset($this->pidColors[$pid]);
        if (!in_array($color, $this->unusedPidColors, true)) {
            $this->unusedPidColors[] = $color;
        }
    }

    private function processRequests(string $content, ?int $connectionId): bool
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
                socket_write($this->connections[$connectionId], $response);
                //stream_socket_sendto($this->sock, $response, 0, $connection);
                continue;
            }

            if ($connectionId !== null) {
                $this->addPid($message->processId, $connectionId);
            } elseif ($message->processId !== 0 && $message->type === Message::INTRO) {
                $this->addPid($message->processId, null);
            }

            $this->renderMessage($message);

            if ($message->type === Message::OUTRO) {
                $outro = true;
                if ($connectionId === null) {
                    $this->removePid($message->processId, null);
                }
                // todo: should unset also for socket connection?
                // todo: how to handle dangling messages sent after shutdown_handler? (e.g. from closing session)
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

        echo "\n";

        // flags
        if ($message->flags & Message::FLAG_BELL) {
            echo "\x07";
        }
        if ($message->flags & Message::FLAG_SHOW_TIME) {
            $formatted = number_format($message->time, 6, '.', '');
            $dateTime = DateTime::createFromFormat('U.u', $formatted, new DateTimeZone('UTC'));
            echo str_replace('00 ', ' ', $dateTime->format('H:i:s.u '));
        }

        // process id
        $isIntroOutro = $message->type === Message::INTRO || $message->type === Message::OUTRO;
        $showPids = $isIntroOutro || $this->alwaysShowPids || ($message->flags & Message::FLAG_SHOW_PID) || (count($this->connections) + count($this->logPids)) > 1;
        if ($showPids && $message->processId !== 0) {
            $color = $this->pidColors[$message->processId] ?? $this->oldPidColors[$message->processId] ?? $this->defaultPidColor;
            echo Ansi::rgb(" #{$message->processId} ", null, $color) . ($isIntroOutro ? '' : ' ');
        }

        // payload
        echo ltrim($message->payload);

        // duration
        if (($message->flags & Message::FLAG_SHOW_DURATION) && $message->duration > 0.000000000001) {
            echo ' ' . Ansi::dblue('(' . Units::time($message->duration) . ')');
        }

        // backtrace
        if ($message->backtrace) {
            echo "\n" . $message->backtrace;
        }

        // duration sum for similar request from same place
        if ($message->backtrace && $this->durationSum !== $message->duration) {
            if (($message->flags & Message::FLAG_SHOW_DURATION) && $this->durationSum > 0.000000000001) {
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
