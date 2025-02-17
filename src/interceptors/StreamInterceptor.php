<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use function array_shift;
use function call_user_func_array;
use function count;
use function explode;
use function fopen;
use function gettype;
use function in_array;
use function is_resource;
use function microtime;
use function preg_match;
use function stream_get_meta_data;
use function stream_socket_client;
use const SEEK_SET;
use const STREAM_CLIENT_CONNECT;

/**
 * Tracks file/stream operations regardless of stream protocol
 * Useful when not possible to set a stream wrapper - e.g. for TCP
 */
class StreamInterceptor
{

    public const NAME = 'stream';

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
    public static $logEvents = StreamWrapper::ALL & ~StreamWrapper::INFO;

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

        if ($ignored || self::$intercept === Intercept::SILENT || (self::$intercept & Intercept::LOG_CALLS)) {
            $start = microtime(true);
            $result = call_user_func_array($function, $params);
            if ($params[0] === $path || is_resource($params[0])) {
                array_shift($params);
            }
            self::redirectOrLog($protocol, $group, $path, microtime(true) - $start, $function, $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept & Intercept::PREVENT_CALLS) {
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

        if ($ignored || (self::$intercept & Intercept::SILENT) || self::$intercept === Intercept::NONE) {
            return;
        }

        $message = Ansi::white(" {$handler}: ", Debugger::$handlerColors[$handler])
            . ' ' . Dumper::file($path) . ' ' . Dumper::call($function, $params, $return);

        $callstack = Callstack::get(Dumper::$config->traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, 0);

        Debugger::send(Message::INTERCEPT, $message, $trace, $duration);
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
        } elseif (gettype($file) === 'resource (closed)') {
            $protocol = 'closed';
        } else {
            $parts = explode('://', $file);
            if (count($parts) > 1) {
                $protocol = $parts[0];
            } else {
                $protocol = FileStreamWrapper::PROTOCOL;
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
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for file reading/writing functions.");
        }

        // as in stream wrappers
        Intercept::registerFunction(self::NAME, 'fopen', self::class);
        Intercept::registerFunction(self::NAME, 'fclose', self::class);
        Intercept::registerFunction(self::NAME, 'flock', self::class);
        Intercept::registerFunction(self::NAME, 'fread', self::class);
        Intercept::registerFunction(self::NAME, 'fwrite', self::class);
        Intercept::registerFunction(self::NAME, 'fputs', self::class); // alias ^
        Intercept::registerFunction(self::NAME, 'ftruncate', self::class);
        Intercept::registerFunction(self::NAME, 'fflush', self::class);
        Intercept::registerFunction(self::NAME, 'fseek', self::class);
        Intercept::registerFunction(self::NAME, 'feof', self::class);
        Intercept::registerFunction(self::NAME, 'ftell', self::class);
        Intercept::registerFunction(self::NAME, 'fstat', self::class); // STAT

        Intercept::registerFunction(self::NAME, 'stream_socket_client', self::class);

        Intercept::registerFunction(self::NAME, 'fgets', self::class); // READ

        /*Intercept::register(self::NAME, 'touch', self::class); // META
        Intercept::register(self::NAME, 'chown', self::class); // META
        Intercept::register(self::NAME, 'chgrp', self::class); // META
        Intercept::register(self::NAME, 'chmod', self::class); // META

        Intercept::register(self::NAME, 'stream_set_blocking', self::class); // SET
        Intercept::register(self::NAME, 'stream_set_read_buffer', self::class); // SET
        Intercept::register(self::NAME, 'stream_set_write_buffer', self::class); // SET
        Intercept::register(self::NAME, 'stream_set_timeout', self::class); // SET

        Intercept::register(self::NAME, 'opendir', self::class);
        Intercept::register(self::NAME, 'readdir', self::class);
        Intercept::register(self::NAME, 'rewinddir', self::class);
        Intercept::register(self::NAME, 'closedir', self::class);
        Intercept::register(self::NAME, 'mkdir', self::class);
        Intercept::register(self::NAME, 'rmdir', self::class);

        Intercept::register(self::NAME, 'rename', self::class);
        Intercept::register(self::NAME, 'unlink', self::class);
        Intercept::register(self::NAME, 'lstat', self::class); // STAT
        Intercept::register(self::NAME, 'stat', self::class); // STAT

        // other file functions
        Intercept::register(self::NAME, 'glob', self::class); // OPENDIR | READDIR

        Intercept::register(self::NAME, 'tmpfile', self::class); // OPEN
        Intercept::register(self::NAME, 'readfile', self::class); // OPEN | READ
        Intercept::register(self::NAME, 'file', self::class); // OPEN | LOCK? | READ
        Intercept::register(self::NAME, 'file_get_contents', self::class); // OPEN | LOCK? | READ
        Intercept::register(self::NAME, 'file_put_contents', self::class); // OPEN | LOCK | WRITE
        Intercept::register(self::NAME, 'copy', self::class); // OPEN | LOCK? | READ? | WRITE
        Intercept::register(self::NAME, 'link', self::class); // OPEN | WRITE
        Intercept::register(self::NAME, 'symlink', self::class); // OPEN | WRITE

        Intercept::register(self::NAME, 'rewind', self::class); // TELL

        Intercept::register(self::NAME, 'fgetc', self::class); // READ
        Intercept::register(self::NAME, 'fgets', self::class); // READ
        Intercept::register(self::NAME, 'fgetss', self::class); // READ
        Intercept::register(self::NAME, 'fpassthru', self::class); // READ

        Intercept::register(self::NAME, 'fsync', self::class); // FLUSH
        Intercept::register(self::NAME, 'fdatasync', self::class); // FLUSH

        Intercept::register(self::NAME, 'fscanf', self::class); // READ

        Intercept::register(self::NAME, 'lchgrp', self::class); // META
        Intercept::register(self::NAME, 'lchown', self::class); // META

        Intercept::register(self::NAME, 'file_exists', self::class); // STAT
        Intercept::register(self::NAME, 'fileatime', self::class); // STAT
        Intercept::register(self::NAME, 'filectime', self::class); // STAT
        Intercept::register(self::NAME, 'filegroup', self::class); // STAT
        Intercept::register(self::NAME, 'fileinode', self::class); // STAT
        Intercept::register(self::NAME, 'filemtime', self::class); // STAT
        Intercept::register(self::NAME, 'fileowner', self::class); // STAT
        Intercept::register(self::NAME, 'fileperms', self::class); // STAT
        Intercept::register(self::NAME, 'filesize', self::class); // STAT
        Intercept::register(self::NAME, 'filetype', self::class); // STAT
        Intercept::register(self::NAME, 'is_dir', self::class); // STAT
        Intercept::register(self::NAME, 'is_executable', self::class); // STAT
        Intercept::register(self::NAME, 'is_file', self::class); // STAT
        Intercept::register(self::NAME, 'is_link', self::class); // STAT
        Intercept::register(self::NAME, 'is_readable', self::class); // STAT
        Intercept::register(self::NAME, 'is_uploaded_file', self::class); // STAT
        Intercept::register(self::NAME, 'is_writable', self::class); // STAT
        Intercept::register(self::NAME, 'is_writeable', self::class); // alias ^
        Intercept::register(self::NAME, 'linkinfo', self::class); // STAT
        Intercept::register(self::NAME, 'realpath', self::class); // STAT
        Intercept::register(self::NAME, 'readlink', self::class); // STAT

        Intercept::register(self::NAME, 'move_uploaded_file', self::class); // RENAME

        // misc OS commands
        Intercept::register(self::NAME, 'umask', self::class);
        Intercept::register(self::NAME, 'tempnam', self::class);
        Intercept::register(self::NAME, 'clearstatcache', self::class);
        Intercept::register(self::NAME, 'diskfreespace', self::class);
        Intercept::register(self::NAME, 'disk_free_space', self::class); // alias ^
        Intercept::register(self::NAME, 'disk_total_space', self::class);
        Intercept::register(self::NAME, 'realpath_cache_get', self::class);
        Intercept::register(self::NAME, 'realpath_cache_size', self::class);
        Intercept::register(self::NAME, 'set_file_buffer', self::class);
        */

        // sys_get_tmp_dir() - todo: are there any others?

        self::$intercept = $level;
    }

    /**
     * @param resource|null $context
     * @return resource|false
     */
    public static function fopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
    {
        [$protocol, $path, $ignored] = self::ignored(FileStreamWrapper::OPEN, $filename);

        $params = [$mode, $use_include_path, $context];
        if ($ignored || self::$intercept === Intercept::NONE || self::$intercept === Intercept::SILENT || (self::$intercept & Intercept::LOG_CALLS)) {
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
            self::redirectOrLog($protocol, StreamWrapper::OPEN, $path, microtime(true) - $start, 'fopen', $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept & Intercept::PREVENT_CALLS) {
            self::redirectOrLog($protocol, StreamWrapper::OPEN, $path, 0.0, 'fopen', $params, false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param resource|null $context
     * @param int|mixed $error_code
     * @param string|mixed $error_message
     * @return resource|false
     */
    public static function stream_socket_client(
        string $address,
        &$error_code = 0,
        &$error_message = '',
        ?float $timeout = null,
        int $flags = STREAM_CLIENT_CONNECT,
        $context = null
    )
    {
        [$protocol, $path, $ignored] = self::ignored(FileStreamWrapper::OPEN, $address);

        $params = [&$error_code, &$error_message, $timeout, $flags, $context];
        if ($ignored || self::$intercept === Intercept::NONE || self::$intercept === Intercept::SILENT || (self::$intercept & Intercept::LOG_CALLS)) {
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
            self::redirectOrLog($protocol, StreamWrapper::OPEN, $path, microtime(true) - $start, 'stream_socket_client', $params, $result, $ignored);

            return $result;
        } elseif (self::$intercept & Intercept::PREVENT_CALLS) {
            self::redirectOrLog($protocol, StreamWrapper::OPEN, $path, null, 'stream_socket_client', $params, false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param resource $stream
     */
    public static function fclose($stream): bool
    {
        return self::handle(StreamWrapper::CLOSE, $stream, 'fclose', [$stream], true);
    }

    /**
     * @param resource $stream
     */
    public static function flock($stream, int $operation, int &$would_block = 0): bool
    {
        return self::handle(StreamWrapper::LOCK, $stream, 'flock', [$stream, $operation, &$would_block], true);
    }

    /**
     * @param resource $stream
     * @return string|false
     */
    public static function fread($stream, int $length)
    {
        return self::handle(StreamWrapper::READ, $stream, 'fread', [$stream, $length], false);
    }

    /**
     * @param resource $stream
     * @return string|false
     */
    public static function fgets($stream, ?int $length = null)
    {
        if ($length !== null) {
            return self::handle(StreamWrapper::READ, $stream, 'fgets', [$stream, $length], false);
        } else {
            return self::handle(StreamWrapper::READ, $stream, 'fgets', [$stream], false);
        }
    }

    /**
     * @param resource $stream
     * @return int|false
     */
    public static function fwrite($stream, string $data, ?int $length = null)
    {
        if ($length === null) {
            return self::handle(StreamWrapper::WRITE, $stream, 'fwrite', [$stream, $data], false);
        } else {
            return self::handle(StreamWrapper::WRITE, $stream, 'fwrite', [$stream, $data, $length], false);
        }
    }

    /**
     * @param resource $stream
     */
    public static function ftruncate($stream, int $size): bool
    {
        return self::handle(StreamWrapper::WRITE, $stream, 'ftruncate', [$stream, $size], true);
    }

    /**
     * @param resource $stream
     */
    public static function fflush($stream): bool
    {
        return self::handle(StreamWrapper::FLUSH, $stream, 'fflush', [$stream], true);
    }

    /**
     * @param resource $stream
     */
    public static function fseek($stream, int $offset, int $whence = SEEK_SET): int
    {
        return self::handle(StreamWrapper::SEEK, $stream, 'fseek', [$stream, $offset, $whence], 0);
    }

    /**
     * @param resource $stream
     */
    public static function feof($stream): bool
    {
        return self::handle(StreamWrapper::INFO, $stream, 'feof', [$stream], true);
    }

    /**
     * @param resource $stream
     * @return int|false
     */
    public static function ftell($stream)
    {
        return self::handle(StreamWrapper::INFO, $stream, 'ftell', [$stream], false);
    }

    /**
     * @param resource $stream
     * @return int[]|false
     */
    public static function fstat($stream)
    {
        return self::handle(StreamWrapper::STAT, $stream, 'fstat', [$stream], false);
    }

}
