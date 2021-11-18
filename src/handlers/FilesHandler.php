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
use function array_shift;
use function call_user_func_array;
use function count;
use function explode;
use function fopen;
use function fseek;
use function in_array;
use function is_resource;
use function microtime;
use function preg_match;
use function round;
use function stream_get_meta_data;
use function stream_socket_client;
use const SEEK_SET;
use const STREAM_CLIENT_CONNECT;

/**
 * Tracks file operations regardless of stream protocol
 * Useful when not possible to set a stream protocol - e.g. for TCP
 */
class FilesHandler
{

    /** @var int Types of events to log */
    public static $log = StreamHandler::ALL & ~StreamHandler::INFO;

    /** @var string[] List of protocols to log - e.g. 'file', 'http', 'tcp' */
    public static $logProtocols = ['unknown'];

    /** @var array<string, array<string, array<string, array{class-string, string}>>> ($protocol => $function => $pathExpression => $callable) */
    public static $redirect = [];

    /** @var callable(int $event, float $time, string $path, bool $isInclude, string $call, mixed[] $params, mixed $return): bool User log filter */
    public static $logFilter;

    /** @var bool */
    public static $filterTrace = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private static $takeover = Takeover::NONE;

    /** @var array<int, string> ($resourceId => $uri)  */
    private static $uris = [];

    /** @var int[] */
    private static $userEvents = [];

    /** @var float[] */
    private static $userTime = [];

    /**
     * Default implementation of an overloaded function handler
     *
     * @param string|resource $file
     * @param mixed[] $params
     * @param mixed $defaultReturn
     * @return mixed
     */
    public static function handle(int $group, $file, string $function, array $params, $defaultReturn)
    {
        [$protocol, $path, $allowed] = self::allowed($group, $file);

        if ($allowed || self::$takeover === Takeover::NONE) {
            return call_user_func_array($function, $params);
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $start = microtime(true);
            $result = call_user_func_array($function, $params);
            if ($params[0] === $path || is_resource($params[0])) {
                array_shift($params);
            }
            self::log($protocol, $path, microtime(true) - $start, $function, $params, $result);

            return $result;
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            if ($params[0] === $path || is_resource($params[0])) {
                array_shift($params);
            }
            self::log($protocol, $path, null, $function, $params, $defaultReturn);

            return $defaultReturn;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    public static function log(string $handler, string $path, ?float $duration, string $function, array $params, $return): void
    {
        if (isset(self::$redirect[$handler][$function])) {
            foreach (self::$redirect[$handler][$function] as $expression => $redirect) {
                if (preg_match($expression, $path)) {
                    $redirect($path, $duration, $params, $return);
                    return;
                }
            }
        }

        $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler])
            . ' ' . Dumper::file($path) . ' ' . Dumper::call($function, $params, $return);

        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::TAKEOVER, $message, $trace, $duration);
    }

    /**
     * @param int $action
     * @param string|resource $file
     * @return array{string, string, bool}
     */
    private static function allowed(int $action, $file): array
    {
        if ((self::$log & $action) === 0) {
            return ['', '', true];
        }

        if (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            $resourceId = (int) $file;
            $parts = explode('_', $meta['stream_type']);
            if (count($parts) > 1) {
                $protocol = $parts[0];
            } else {
                $protocol = 'unknown';
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
                $protocol = 'file';
            }
        }

        $allow = in_array($protocol, self::$logProtocols, true);

        return [$protocol, $file, $allow];
    }

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over majority of file and directory functions
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverFiles(int $level = Takeover::LOG_OTHERS): void
    {
        // as in stream wrappers
        Takeover::register('files', 'fopen', [self::class, 'fakeFopen']);
        Takeover::register('files', 'fclose', [self::class, 'fakeFclose']);
        Takeover::register('files', 'flock', [self::class, 'fakeFlock']);
        Takeover::register('files', 'fread', [self::class, 'fakeFread']);
        Takeover::register('files', 'fwrite', [self::class, 'fakeFwrite']);
        Takeover::register('files', 'fputs', [self::class, 'fakeFwrite']); // alias ^
        Takeover::register('files', 'ftruncate', [self::class, 'fakeFtruncate']);
        Takeover::register('files', 'fflush', [self::class, 'fakeFflush']);
        Takeover::register('files', 'fseek', [self::class, 'fakeFseek']);
        Takeover::register('files', 'feof', [self::class, 'fakeFeof']);
        Takeover::register('files', 'ftell', [self::class, 'fakeFtell']);
        Takeover::register('files', 'fstat', [self::class, 'fakeFstat']); // STAT

        Takeover::register('files', 'stream_socket_client', [self::class, 'fakeSocketClient']);

        Takeover::register('files', 'fgets', [self::class, 'fakeFgets']); // READ

        /*Takeover::register('touch', [self::class, 'fakeTouch']); // META
        Takeover::register('chown', [self::class, 'fakeChown']); // META
        Takeover::register('chgrp', [self::class, 'fakeChgrp']); // META
        Takeover::register('chmod', [self::class, 'fakeChmod']); // META

        Takeover::register('stream_set_blocking', [self::class, 'fakeSetBlocking']); // SET
        Takeover::register('stream_set_read_buffer', [self::class, 'fakeSetReadBuffer']); // SET
        Takeover::register('stream_set_write_buffer', [self::class, 'fakeSetWriteBuffer']); // SET
        Takeover::register('stream_set_timeout', [self::class, 'fakeSetTimeout']); // SET

        Takeover::register('opendir', [self::class, 'fakeOpendir']);
        Takeover::register('readdir', [self::class, 'fakeReaddir']);
        Takeover::register('rewinddir', [self::class, 'fakeRewinddir']);
        Takeover::register('closedir', [self::class, 'fakeClosedir']);
        Takeover::register('mkdir', [self::class, 'fakeMkdir']);
        Takeover::register('rmdir', [self::class, 'fakeRmdir']);

        Takeover::register('rename', [self::class, 'fakeRename']);
        Takeover::register('unlink', [self::class, 'fakeUnlink']);
        Takeover::register('lstat', [self::class, 'fakeLstat']); // STAT
        Takeover::register('stat', [self::class, 'fakeStat']); // STAT

        // other file functions
        Takeover::register('glob', [self::class, 'fakeUmask']); // OPENDIR | READDIR

        Takeover::register('tmpfile', [self::class, 'fakeTmpfile']); // OPEN
        Takeover::register('readfile', [self::class, 'fakeReadfile']); // OPEN | READ
        Takeover::register('file', [self::class, 'fakeFile']); // OPEN | LOCK? | READ
        Takeover::register('file_get_contents', [self::class, 'fakeGetContents']); // OPEN | LOCK? | READ
        Takeover::register('file_put_contents', [self::class, 'fakePutContents']); // OPEN | LOCK | WRITE
        Takeover::register('copy', [self::class, 'fakeCopy']); // OPEN | LOCK? | READ? | WRITE
        Takeover::register('link', [self::class, 'fakeLink']); // OPEN | WRITE
        Takeover::register('symlink', [self::class, 'fakeSymlink']); // OPEN | WRITE

        Takeover::register('rewind', [self::class, 'fakeRewind']); // TELL

        Takeover::register('fgetc', [self::class, 'fakeFgetc']); // READ
        Takeover::register('fgets', [self::class, 'fakeFgets']); // READ
        Takeover::register('fgetss', [self::class, 'fakeFgetss']); // READ
        Takeover::register('fpassthru', [self::class, 'fakePassthru']); // READ

        Takeover::register('fsync', [self::class, 'fakeSync']); // FLUSH
        Takeover::register('fdatasync', [self::class, 'fakeDatasync']); // FLUSH

        Takeover::register('fscanf', [self::class, 'fakeFscanf']); // READ

        Takeover::register('lchgrp', [self::class, 'fakeLchgrp']); // META
        Takeover::register('lchown', [self::class, 'fakeLchown']); // META

        Takeover::register('file_exists', [self::class, 'fakeExists']); // STAT
        Takeover::register('fileatime', [self::class, 'fakeAtime']); // STAT
        Takeover::register('filectime', [self::class, 'fakeCtime']); // STAT
        Takeover::register('filegroup', [self::class, 'fakeGroup']); // STAT
        Takeover::register('fileinode', [self::class, 'fakeInode']); // STAT
        Takeover::register('filemtime', [self::class, 'fakeMtime']); // STAT
        Takeover::register('fileowner', [self::class, 'fakeOwner']); // STAT
        Takeover::register('fileperms', [self::class, 'fakePerms']); // STAT
        Takeover::register('filesize', [self::class, 'fakeSize']); // STAT
        Takeover::register('filetype', [self::class, 'fakeType']); // STAT
        Takeover::register('is_dir', [self::class, 'fakeIsDir']); // STAT
        Takeover::register('is_executable', [self::class, 'fakeIsExecutable']); // STAT
        Takeover::register('is_file', [self::class, 'fakeIsFile']); // STAT
        Takeover::register('is_link', [self::class, 'fakeIsLink']); // STAT
        Takeover::register('is_readable', [self::class, 'fakeIsReadable']); // STAT
        Takeover::register('is_uploaded_file', [self::class, 'fakeIsUploaded']); // STAT
        Takeover::register('is_writable', [self::class, 'fakeIsWritable']); // STAT
        Takeover::register('is_writeable', [self::class, 'fakeIsWritable']); // alias ^
        Takeover::register('linkinfo', [self::class, 'fakeLinkinfo']); // STAT
        Takeover::register('realpath', [self::class, 'fakeRealpath']); // STAT
        Takeover::register('readlink', [self::class, 'fakeReadlink']); // STAT

        Takeover::register('move_uploaded_file', [self::class, 'fakeUmask']); // RENAME

        // misc OS commands
        Takeover::register('umask', [self::class, 'fakeUmask']);
        Takeover::register('tempnam', [self::class, 'fakeTempnam']);
        Takeover::register('clearstatcache', [self::class, 'fakeClearStatCache']);
        Takeover::register('diskfreespace', [self::class, 'fakeFreeSpace']);
        Takeover::register('disk_free_space', [self::class, 'fakeFreeSpace']); // alias ^
        Takeover::register('disk_total_space', [self::class, 'fakeTotalSpace']);
        Takeover::register('realpath_cache_get', [self::class, 'fakeRealpathCacheGet']);
        Takeover::register('realpath_cache_size', [self::class, 'fakeRealpathCacheSize']);
        Takeover::register('set_file_buffer', [self::class, 'fakeSetFileBuffer']);
        */
        self::$takeover = $level;
    }

    /**
     * @param resource|null $context
     * @return resource|false
     */
    public static function fakeFopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
    {
        [$protocol, $path, $allowed] = self::allowed(FileStreamHandler::OPEN, $filename);

        if ($allowed || self::$takeover === Takeover::NONE) {
            $result = fopen($filename, $mode, $use_include_path, $context);
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $filename;
            }

            return $result;
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $start = microtime(true);
            $result = fopen($filename, $mode, $use_include_path, $context);
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $filename;
            }
            self::log($protocol, $path, microtime(true) - $start, 'fopen', [$mode, $use_include_path, $context], $result);

            return $result;
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            self::log($protocol, $path, 0.0, 'fopen', [$mode, $use_include_path, $context], false);

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
        &$error_code = '',
        &$error_message = '',
        ?float $timeout = null,
        int $flags = STREAM_CLIENT_CONNECT,
        $context = null
    )
    {
        [$protocol, $path, $allowed] = self::allowed(FileStreamHandler::OPEN, $address);

        if ($allowed || self::$takeover === Takeover::NONE) {
            if ($context !== null) {
                $result = stream_socket_client($address, $error_code, $error_message, $timeout, $flags, $context);
            } else {
                $result = stream_socket_client($address, $error_code, $error_message, $timeout, $flags);
            }
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $address;
            }

            return $result;
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $start = microtime(true);
            if ($context !== null) {
                $result = stream_socket_client($address, $error_code, $error_message, $timeout, $flags, $context);
            } else {
                $result = stream_socket_client($address, $error_code, $error_message, $timeout, $flags);
            }
            // saving uri
            if ($result !== false) {
                self::$uris[(int) $result] = $address;
            }
            self::log($protocol, $path, microtime(true) - $start, 'stream_socket_client', [$error_code, $error_message, $timeout, $flags, $context], $result);

            return $result;
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            self::log($protocol, $path, null, 'stream_socket_client', [$error_code, $error_message, $timeout, $flags, $context], false);

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
    public static function fakeFlock($stream, int $operation, &$would_block): bool
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
