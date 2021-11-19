<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use const SEEK_SET;
use const STREAM_CLIENT_CONNECT;
use function array_shift;
use function call_user_func_array;
use function count;
use function explode;
use function fopen;
use function in_array;
use function is_resource;
use function microtime;
use function preg_match;
use function stream_get_meta_data;
use function stream_socket_client;

/**
 * Tracks file operations regardless of stream protocol
 * Useful when not possible to set a stream protocol - e.g. for TCP
 */
class FilesHandler
{

    public const NAME = 'files';

    public const PROTOCOL_TCP = 'tcp';
    public const PROTOCOL_UDP = 'udp';
    public const PROTOCOL_UNIX = 'unix';
    public const PROTOCOL_UDG = 'udg';
    public const PROTOCOL_SSL = 'ssl';
    public const PROTOCOL_TLS = 'tls';
    public const PROTOCOL_TLS_10 = 'tlsv1.0';
    public const PROTOCOL_TLS_11 = 'tlsv1.1';
    public const PROTOCOL_TLS_12 = 'tlsv1.2';
    public const PROTOCOL_UNKNOWN = 'unknown';

    /** @var int Types of events to log */
    public static $logEvents = StreamHandler::ALL & ~StreamHandler::INFO;

    /** @var string[] List of protocols to log self::PROTOCOL:* constants */
    public static $logProtocols = [self::PROTOCOL_UNKNOWN];

    /** @var array<string, array<string, array<string, array{class-string, string}>>> ($protocol => $function => $pathExpression => $callable) */
    public static $redirect = [];

    /** @var callable(int $event, float $time, string $path, bool $isInclude, string $call, mixed[] $params, mixed $return): bool User log filter */
    public static $logFilter;

    /** @var bool */
    public static $filterTrace = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private static $intercept = Intercept::NONE;

    /** @var array<int, string> ($resourceId => $uri)  */
    private static $uris = [];

    /** @var int[][] */
    private static $events = [];

    /** @var float[][] */
    private static $time = [];

    public static function enabled(): bool
    {
        return self::$intercept !== Intercept::NONE;
    }

    /**
     * Default implementation of an overloaded function handler
     *
     * @param string|resource $file
     * @param callable-string $function
     * @param mixed[] $params
     * @param mixed $defaultReturn
     * @return mixed
     */
    public static function handle(int $group, $file, string $function, array $params, $defaultReturn)
    {
        [$protocol, $path, $ignored] = self::ignored($group, $file);

        if ($ignored || self::$intercept === Intercept::SILENT || self::$intercept === Intercept::LOG_CALLS) {
            $start = microtime(true);
            $result = call_user_func_array($function, $params);
            if ($params[0] === $path || is_resource($params[0])) {
                array_shift($params);
            }
            self::redirectOrLog($protocol, $group, $path, microtime(true) - $start, $function, $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept === Intercept::PREVENT_CALLS) {
            if ($params[0] === $path || is_resource($params[0])) {
                array_shift($params);
            }
            self::redirectOrLog($protocol, $group, $path, null, $function, $params, $defaultReturn);

            return $defaultReturn;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    public static function redirectOrLog(
        string $handler,
        int $group,
        string $path,
        ?float $duration,
        string $function,
        array $params,
        $return,
        bool $ignored = false
    ): void
    {
        if (!isset(self::$events[$handler][$group])) {
            self::$events[$handler][$group] = 0;
            self::$time[$handler][$group] = 0.0;
        }
        self::$events[$handler][$group]++;
        if ($duration !== null) {
            self::$time[$handler][$group] += $duration;
        }

        if (isset(self::$redirect[$handler][$function])) {
            /** @var callable-string $redirect */
            foreach (self::$redirect[$handler][$function] as $expression => $redirect) {
                if (preg_match($expression, $path)) {
                    $redirect($path, $duration, $params, $return);
                    return;
                }
            }
        }

        if ($ignored || self::$intercept === Intercept::SILENT) {
            return;
        }

        $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler])
            . ' ' . Dumper::file($path) . ' ' . Dumper::call($function, $params, $return);

        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::INTERCEPT, $message, $trace, $duration);
    }

    /**
     * @param string|resource $file
     * @return array{string, string, bool}
     */
    private static function ignored(int $action, $file): array
    {
        if ((self::$logEvents & $action) === 0) {
            return ['', '', true];
        }

        if (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            $resourceId = (int) $file;
            $parts = explode('_', $meta['stream_type']);
            if (count($parts) > 1) {
                $protocol = $parts[0];
            } else {
                $protocol = self::PROTOCOL_UNKNOWN;
            }

            if (isset(self::$uris[$resourceId])) {
                $file = self::$uris[$resourceId];
            } else {
                $file = $meta['uri'] ?? '';
            }
        } else {
            $parts = explode('://', $file);
            if (count($parts) > 1) {
                $protocol = $parts[0];
            } else {
                $protocol = FileStreamHandler::PROTOCOL;
            }
        }

        $allow = in_array($protocol, self::$logProtocols, true);

        return [$protocol, $file, $allow];
    }

    /**
     * @return int[][][]|float[][][]
     */
    public function getStats(): array
    {
        return [self::$events, self::$time];
    }

    // intercept handlers ----------------------------------------------------------------------------------------------

    /**
     * Take control over majority of file and directory functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptFileFunctions(int $level = Intercept::LOG_CALLS): void
    {
        // as in stream wrappers
        Intercept::register(self::NAME, 'fopen', [self::class, 'fakeFopen']);
        Intercept::register(self::NAME, 'fclose', [self::class, 'fakeFclose']);
        Intercept::register(self::NAME, 'flock', [self::class, 'fakeFlock']);
        Intercept::register(self::NAME, 'fread', [self::class, 'fakeFread']);
        Intercept::register(self::NAME, 'fwrite', [self::class, 'fakeFwrite']);
        Intercept::register(self::NAME, 'fputs', [self::class, 'fakeFwrite']); // alias ^
        Intercept::register(self::NAME, 'ftruncate', [self::class, 'fakeFtruncate']);
        Intercept::register(self::NAME, 'fflush', [self::class, 'fakeFflush']);
        Intercept::register(self::NAME, 'fseek', [self::class, 'fakeFseek']);
        Intercept::register(self::NAME, 'feof', [self::class, 'fakeFeof']);
        Intercept::register(self::NAME, 'ftell', [self::class, 'fakeFtell']);
        Intercept::register(self::NAME, 'fstat', [self::class, 'fakeFstat']); // STAT

        Intercept::register(self::NAME, 'stream_socket_client', [self::class, 'fakeSocketClient']);

        Intercept::register(self::NAME, 'fgets', [self::class, 'fakeFgets']); // READ

        /*Intercept::register(self::NAME, 'touch', [self::class, 'fakeTouch']); // META
        Intercept::register(self::NAME, 'chown', [self::class, 'fakeChown']); // META
        Intercept::register(self::NAME, 'chgrp', [self::class, 'fakeChgrp']); // META
        Intercept::register(self::NAME, 'chmod', [self::class, 'fakeChmod']); // META

        Intercept::register(self::NAME, 'stream_set_blocking', [self::class, 'fakeSetBlocking']); // SET
        Intercept::register(self::NAME, 'stream_set_read_buffer', [self::class, 'fakeSetReadBuffer']); // SET
        Intercept::register(self::NAME, 'stream_set_write_buffer', [self::class, 'fakeSetWriteBuffer']); // SET
        Intercept::register(self::NAME, 'stream_set_timeout', [self::class, 'fakeSetTimeout']); // SET

        Intercept::register(self::NAME, 'opendir', [self::class, 'fakeOpendir']);
        Intercept::register(self::NAME, 'readdir', [self::class, 'fakeReaddir']);
        Intercept::register(self::NAME, 'rewinddir', [self::class, 'fakeRewinddir']);
        Intercept::register(self::NAME, 'closedir', [self::class, 'fakeClosedir']);
        Intercept::register(self::NAME, 'mkdir', [self::class, 'fakeMkdir']);
        Intercept::register(self::NAME, 'rmdir', [self::class, 'fakeRmdir']);

        Intercept::register(self::NAME, 'rename', [self::class, 'fakeRename']);
        Intercept::register(self::NAME, 'unlink', [self::class, 'fakeUnlink']);
        Intercept::register(self::NAME, 'lstat', [self::class, 'fakeLstat']); // STAT
        Intercept::register(self::NAME, 'stat', [self::class, 'fakeStat']); // STAT

        // other file functions
        Intercept::register(self::NAME, 'glob', [self::class, 'fakeUmask']); // OPENDIR | READDIR

        Intercept::register(self::NAME, 'tmpfile', [self::class, 'fakeTmpfile']); // OPEN
        Intercept::register(self::NAME, 'readfile', [self::class, 'fakeReadfile']); // OPEN | READ
        Intercept::register(self::NAME, 'file', [self::class, 'fakeFile']); // OPEN | LOCK? | READ
        Intercept::register(self::NAME, 'file_get_contents', [self::class, 'fakeGetContents']); // OPEN | LOCK? | READ
        Intercept::register(self::NAME, 'file_put_contents', [self::class, 'fakePutContents']); // OPEN | LOCK | WRITE
        Intercept::register(self::NAME, 'copy', [self::class, 'fakeCopy']); // OPEN | LOCK? | READ? | WRITE
        Intercept::register(self::NAME, 'link', [self::class, 'fakeLink']); // OPEN | WRITE
        Intercept::register(self::NAME, 'symlink', [self::class, 'fakeSymlink']); // OPEN | WRITE

        Intercept::register(self::NAME, 'rewind', [self::class, 'fakeRewind']); // TELL

        Intercept::register(self::NAME, 'fgetc', [self::class, 'fakeFgetc']); // READ
        Intercept::register(self::NAME, 'fgets', [self::class, 'fakeFgets']); // READ
        Intercept::register(self::NAME, 'fgetss', [self::class, 'fakeFgetss']); // READ
        Intercept::register(self::NAME, 'fpassthru', [self::class, 'fakePassthru']); // READ

        Intercept::register(self::NAME, 'fsync', [self::class, 'fakeSync']); // FLUSH
        Intercept::register(self::NAME, 'fdatasync', [self::class, 'fakeDatasync']); // FLUSH

        Intercept::register(self::NAME, 'fscanf', [self::class, 'fakeFscanf']); // READ

        Intercept::register(self::NAME, 'lchgrp', [self::class, 'fakeLchgrp']); // META
        Intercept::register(self::NAME, 'lchown', [self::class, 'fakeLchown']); // META

        Intercept::register(self::NAME, 'file_exists', [self::class, 'fakeExists']); // STAT
        Intercept::register(self::NAME, 'fileatime', [self::class, 'fakeAtime']); // STAT
        Intercept::register(self::NAME, 'filectime', [self::class, 'fakeCtime']); // STAT
        Intercept::register(self::NAME, 'filegroup', [self::class, 'fakeGroup']); // STAT
        Intercept::register(self::NAME, 'fileinode', [self::class, 'fakeInode']); // STAT
        Intercept::register(self::NAME, 'filemtime', [self::class, 'fakeMtime']); // STAT
        Intercept::register(self::NAME, 'fileowner', [self::class, 'fakeOwner']); // STAT
        Intercept::register(self::NAME, 'fileperms', [self::class, 'fakePerms']); // STAT
        Intercept::register(self::NAME, 'filesize', [self::class, 'fakeSize']); // STAT
        Intercept::register(self::NAME, 'filetype', [self::class, 'fakeType']); // STAT
        Intercept::register(self::NAME, 'is_dir', [self::class, 'fakeIsDir']); // STAT
        Intercept::register(self::NAME, 'is_executable', [self::class, 'fakeIsExecutable']); // STAT
        Intercept::register(self::NAME, 'is_file', [self::class, 'fakeIsFile']); // STAT
        Intercept::register(self::NAME, 'is_link', [self::class, 'fakeIsLink']); // STAT
        Intercept::register(self::NAME, 'is_readable', [self::class, 'fakeIsReadable']); // STAT
        Intercept::register(self::NAME, 'is_uploaded_file', [self::class, 'fakeIsUploaded']); // STAT
        Intercept::register(self::NAME, 'is_writable', [self::class, 'fakeIsWritable']); // STAT
        Intercept::register(self::NAME, 'is_writeable', [self::class, 'fakeIsWritable']); // alias ^
        Intercept::register(self::NAME, 'linkinfo', [self::class, 'fakeLinkinfo']); // STAT
        Intercept::register(self::NAME, 'realpath', [self::class, 'fakeRealpath']); // STAT
        Intercept::register(self::NAME, 'readlink', [self::class, 'fakeReadlink']); // STAT

        Intercept::register(self::NAME, 'move_uploaded_file', [self::class, 'fakeUmask']); // RENAME

        // misc OS commands
        Intercept::register(self::NAME, 'umask', [self::class, 'fakeUmask']);
        Intercept::register(self::NAME, 'tempnam', [self::class, 'fakeTempnam']);
        Intercept::register(self::NAME, 'clearstatcache', [self::class, 'fakeClearStatCache']);
        Intercept::register(self::NAME, 'diskfreespace', [self::class, 'fakeFreeSpace']);
        Intercept::register(self::NAME, 'disk_free_space', [self::class, 'fakeFreeSpace']); // alias ^
        Intercept::register(self::NAME, 'disk_total_space', [self::class, 'fakeTotalSpace']);
        Intercept::register(self::NAME, 'realpath_cache_get', [self::class, 'fakeRealpathCacheGet']);
        Intercept::register(self::NAME, 'realpath_cache_size', [self::class, 'fakeRealpathCacheSize']);
        Intercept::register(self::NAME, 'set_file_buffer', [self::class, 'fakeSetFileBuffer']);
        */
        self::$intercept = $level;
    }

    /**
     * @param resource|null $context
     * @return resource|false
     */
    public static function fakeFopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
    {
        [$protocol, $path, $ignored] = self::ignored(FileStreamHandler::OPEN, $filename);

        $params = [$mode, $use_include_path, $context];
        if ($ignored || self::$intercept === Intercept::SILENT || self::$intercept === Intercept::LOG_CALLS) {
            $start = microtime(true);
            if ($context !== null) {
                $result = fopen($filename, $mode, $use_include_path, $context);
            } else {
                $result = fopen($filename, $mode, $use_include_path);
            }
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $filename;
            }
            self::redirectOrLog($protocol, StreamHandler::OPEN, $path, microtime(true) - $start, 'fopen', $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept === Intercept::PREVENT_CALLS) {
            self::redirectOrLog($protocol, StreamHandler::OPEN, $path, 0.0, 'fopen', $params, false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param resource|null $context
     * @return resource|false
     */
    public static function fakeSocketClient(
        string $address,
        int &$error_code = 0,
        string &$error_message = '',
        ?float $timeout = null,
        int $flags = STREAM_CLIENT_CONNECT,
        $context = null
    )
    {
        [$protocol, $path, $ignored] = self::ignored(FileStreamHandler::OPEN, $address);

        $params = [&$error_code, &$error_message, $timeout, $flags, $context];
        if ($ignored || self::$intercept === Intercept::SILENT || self::$intercept === Intercept::LOG_CALLS) {
            $start = microtime(true);
            if ($context !== null) {
                $result = stream_socket_client($address, ...$params);
            } else {
                $result = stream_socket_client($address, $error_code, $error_message, $timeout, $flags);
            }
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $address;
            }
            self::redirectOrLog($protocol, StreamHandler::OPEN, $path, microtime(true) - $start, 'stream_socket_client', $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept === Intercept::PREVENT_CALLS) {
            self::redirectOrLog($protocol, StreamHandler::OPEN, $path, null, 'stream_socket_client', $params, false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param resource $stream
     */
    public static function fakeFclose($stream): bool
    {
        return self::handle(StreamHandler::CLOSE, $stream, 'fclose', [$stream], true);
    }

    /**
     * @param resource $stream
     */
    public static function fakeFlock($stream, int $operation, int &$would_block = 0): bool
    {
        return self::handle(StreamHandler::LOCK, $stream, 'flock', [$stream, $operation, &$would_block], true);
    }

    /**
     * @param resource $stream
     * @return string|false
     */
    public static function fakeFread($stream, int $length)
    {
        return self::handle(StreamHandler::READ, $stream, 'fread', [$stream, $length], false);
    }

    /**
     * @param resource $stream
     * @return string|false
     */
    public static function fakeFgets($stream, ?int $length = null)
    {
        if ($length !== null) {
            return self::handle(StreamHandler::READ, $stream, 'fgets', [$stream, $length], false);
        } else {
            return self::handle(StreamHandler::READ, $stream, 'fgets', [$stream], false);
        }
    }

    /**
     * @param resource $stream
     * @return int|false
     */
    public static function fakeFwrite($stream, string $data, ?int $length = null)
    {
        if ($length === null) {
            return self::handle(StreamHandler::WRITE, $stream, 'fwrite', [$stream, $data], false);
        } else {
            return self::handle(StreamHandler::WRITE, $stream, 'fwrite', [$stream, $data, $length], false);
        }
    }

    /**
     * @param resource $stream
     */
    public static function fakeFtruncate($stream, int $size): bool
    {
        return self::handle(StreamHandler::WRITE, $stream, 'ftruncate', [$stream, $size], true);
    }

    /**
     * @param resource $stream
     */
    public static function fakeFflush($stream): bool
    {
        return self::handle(StreamHandler::FLUSH, $stream, 'fflush', [$stream], true);
    }

    /**
     * @param resource $stream
     */
    public static function fakeFseek($stream, int $offset, int $whence = SEEK_SET): int
    {
        return self::handle(StreamHandler::SEEK, $stream, 'fseek', [$stream, $offset, $whence], 0);
    }

    /**
     * @param resource $stream
     */
    public static function fakeFeof($stream): bool
    {
        return self::handle(StreamHandler::INFO, $stream, 'feof', [$stream], true);
    }

    /**
     * @param resource $stream
     * @return int|false
     */
    public static function fakeFtell($stream)
    {
        return self::handle(StreamHandler::INFO, $stream, 'ftell', [$stream], false);
    }

    /**
     * @param resource $stream
     * @return int[]|false
     */
    public static function fakeFstat($stream)
    {
        return self::handle(StreamHandler::STAT, $stream, 'fstat', [$stream], false);
    }

}
