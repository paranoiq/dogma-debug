<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Dogma\Debug;

use function array_slice;
use function array_sum;
use function fopen;
use function func_get_args;
use function getcwd;
use function in_array;
use function is_callable;
use function microtime;
use function str_replace;
use function str_starts_with;
use function stream_wrapper_register;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;
use function strlen;
use function substr;
use function time;
use function user_error;
use const E_USER_WARNING;
use const SEEK_SET;
use const STREAM_META_ACCESS;
use const STREAM_META_GROUP;
use const STREAM_META_GROUP_NAME;
use const STREAM_META_OWNER;
use const STREAM_META_OWNER_NAME;
use const STREAM_META_TOUCH;
use const STREAM_MKDIR_RECURSIVE;
use const STREAM_OPTION_BLOCKING;
use const STREAM_OPTION_READ_BUFFER;
use const STREAM_OPTION_READ_TIMEOUT;
use const STREAM_OPTION_WRITE_BUFFER;
use const STREAM_REPORT_ERRORS;
use const STREAM_URL_STAT_LINK;
use const STREAM_URL_STAT_QUIET;
use const STREAM_USE_PATH;

/**
 * Common implementation for stream wrappers (file, phar, http...)
 * (trait is used because abstract class would share static members)
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 * @see https://github.com/dg/bypass-finals/blob/master/src/BypassFinals.php
 *
 * @phpstan-type PhpStatResult array{dev: int, ino: int, mode: int, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}
 */
trait StreamWrapperMixin
{

    /** @var int Types of events to log */
    public static $logActions = self::ALL & ~self::INFO;

    /** @var bool Log io operations from include/require statements */
    public static $logIncludes = true;

    /** @var callable(int $event, float $time, string $path, bool $isInclude, string $call, mixed[] $params, mixed $return): bool User log filter */
    public static $logFilter;

    /** @var bool */
    public static $filterTrace = true;

    /** @var array<string, string> Redirect file access to another location. Full path matches only. All paths must use forward slashes, no backslashes! */
    public static $pathRedirects = [];

    /** @var bool Try to workaround PHAR bug with including files more than once on include_once or require_once due to bad path normalization when custom stream handler is used by returning empty resource for any already included files (even for include and require) */
    public static $experimentalPharRequireOnceBugWorkAround = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled = false;

    /** @var int[] */
    private static $events = [];

    /** @var int[] */
    private static $data = [];

    /** @var float[] */
    private static $time = [];

    /** @var int[] */
    private static $includeEvents = [];

    /** @var int[] */
    private static $includeData = [];

    /** @var float[] */
    private static $includeTime = [];

    /** @var string */
    private static $workingDirectory;

    /** @var array<string, VirtualFile> */
    private static $virtualFiles = [];

    /** @var array<string, bool> */
    private static $openedPaths = [];

    // stream handler internals ----------------------------------------------------------------------------------------

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle;

    /** @var string|null */
    private $path;

    /** @var int */
    private $options;

    /** @var string */
    private $readBuffer;

    /** @var int */
    private $bufferSize;

    /** @var float */
    private $duration;

    public static function enable(?int $logActions = null, ?bool $logIncludes = null): void
    {
        if ($logActions !== null) {
            self::$logActions = $logActions;
        }
        if ($logIncludes !== null) {
            self::$logIncludes = $logIncludes;
        }

        stream_wrapper_unregister(self::PROTOCOL);
        $result = stream_wrapper_register(self::PROTOCOL, self::class);
        if ($result === false) {
            user_error("Cannot register protocol handler: " . self::PROTOCOL, E_USER_WARNING);
        }

        self::$enabled = true;
    }

    public static function disable(): void
    {
        if (self::$enabled) {
            stream_wrapper_restore(self::PROTOCOL);
        }

        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Prevents infinite recursion
     */
    private static function runNativeIfNeeded(callable $fn): void
    {
        // @phpstan-ignore-next-line
        $restore = static::class === FileStreamWrapper::class && Debugger::$connection === Debugger::CONNECTION_FILE;
        if ($restore) { // @phpstan-ignore-line
            self::disable();
        }

        $fn();

        if ($restore) { // @phpstan-ignore-line
            self::enable();
        }
    }

    public static function addVirtualFile(string $path, string $content): void
    {
        // todo: alternative for BIG files via php://temp
        self::$virtualFiles[$path] = new VirtualFile($content);
    }

    /**
     * @param mixed[] $params
     * @param mixed $return
     */
    private function log(
        int $event,
        float $duration,
        int $data,
        string $path,
        string $function,
        array $params = [],
        $return = null
    ): void
    {
        // detect and log working directory change
        // todo: is this thread safe?
        $cwd = str_replace('\\', '/', (string) getcwd());
        if ((self::$logActions & self::CHANGE_DIR) && $cwd !== self::$workingDirectory) {
            $message = Dumper::call('chdir', [], (int) $this->handle);
            $message = Ansi::white(' ' . self::PROTOCOL . ': ', Ansi::DGREEN) . ' ' . Dumper::file($cwd) . ' ' . $message;

            $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
            $backtrace = Dumper::formatCallstack($callstack, 1, 0, 0);

            self::runNativeIfNeeded(static function () use ($message, $backtrace): void {
                Debugger::send(Message::STREAM_IO, $message, $backtrace);
            });

            self::$workingDirectory = $cwd;
        }

        // log event counts and times
        $isInclude = ($this->options & self::STREAM_OPEN_FOR_INCLUDE) !== 0;
        if ($isInclude) {
            self::$includeEvents[$event] = isset(self::$includeEvents[$event]) ? self::$includeEvents[$event] + 1 : 1;
            self::$includeData[$event] = isset(self::$includeData[$event]) ? self::$includeData[$event] + $data : $data;
            self::$includeTime[$event] = isset(self::$includeTime[$event]) ? self::$includeTime[$event] + $duration : $duration;
        } else {
            self::$events[$event] = isset(self::$events[$event]) ? self::$events[$event] + 1 : 1;
            self::$data[$event] = isset(self::$data[$event]) ? self::$data[$event] + $data : $data;
            self::$time[$event] = isset(self::$time[$event]) ? self::$time[$event] + $duration : $duration;
        }

        // events display filtering
        if ((self::$logActions & $event) === 0) {
            return;
        }
        if (!self::$logIncludes && $isInclude) {
            return;
        }
        if (is_callable(self::$logFilter) && !(self::$logFilter)($event, $duration, $path, $isInclude, $function, $params, $return)) {
            return;
        }

        $path = Dumper::file($path);
        $isInclude = ($this->options & self::STREAM_OPEN_FOR_INCLUDE) !== 0;
        $options = Ansi::lred($this->options) . ($isInclude ? ' include' : ' read');

        $message = Dumper::call($function, $params, $return);
        $message = Ansi::white(' ' . self::PROTOCOL . ': ', Ansi::DGREEN) . ' ' . $path . ' ' . $message . ' ' . $options;

        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $backtrace = Dumper::formatCallstack($callstack, 1, 0, 0);

        self::runNativeIfNeeded(static function () use ($message, $backtrace, $duration): void {
            Debugger::send(Message::STREAM_IO, $message, $backtrace, $duration);
        });
    }

    // file handle -----------------------------------------------------------------------------------------------------

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $path = str_replace('\\', '/', $path);
        $this->path = $path;
        foreach (self::$pathRedirects as $from => $to) {
            if (str_starts_with($path, $from)) {
                $this->path = $to . substr($path, strlen($from));
                break;
            }
        }
        $this->options = $options;

        if (self::$experimentalPharRequireOnceBugWorkAround && static::class === PharStreamWrapper::class) {
            // skip open if include and already handled (might fix PHAR issue?)
            $isInclude = ($this->options & self::STREAM_OPEN_FOR_INCLUDE) !== 0;
            $normalizedPath = Dumper::normalizePath($path);
            if ($isInclude && isset(self::$openedPaths[$normalizedPath])) {
                $this->handle = fopen('php://memory', 'rb');

                return true;
            }
            self::$openedPaths[$normalizedPath] = true;
        }

        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->open($mode);
            $this->log(self::OPEN, 0.0, 0, $this->path, 'fopen', [$mode, $options], (int) $this->handle);

            return $result;
        }

        $usePath = (bool) ($options & STREAM_USE_PATH);
        try {
            $this->handle = $this->context
                ? $this->previous('fopen', $this->path, $mode, $usePath, $this->context)
                : $this->previous('fopen', $this->path, $mode, $usePath);
        } finally {
            $this->log(self::OPEN, $this->duration, 0, $this->path, 'fopen', [$mode, $options], (int) $this->handle);
        }

        $isInclude = ($this->options & self::STREAM_OPEN_FOR_INCLUDE) !== 0;
        if ($this->handle && $isInclude && Intercept::enabled()) {
            $buffer = '';
            do {
                // native phar handler does not return bigger chunks than 8192, hence the loop
                $b = $this->stream_read(8192, true);
                $buffer .= $b;
            } while ($b !== false && $b !== '');

            if ($buffer) {
                $this->readBuffer = Intercept::hack($buffer, $this->path);
                $this->bufferSize = strlen($this->readBuffer);
            }
        }

        return (bool) $this->handle;
    }

    public function stream_close(): void
    {
        if (isset(self::$virtualFiles[$this->path])) {
            self::$virtualFiles[$this->path]->close();
            $this->log(self::CLOSE, 0.0, 0, $this->path, 'fclose', []);

            return;
        }

        try {
            $this->previous('fclose', $this->handle);
        } finally {
            $this->log(self::CLOSE, $this->duration, 0, $this->path, 'fclose');
        }
    }

    public function stream_lock(int $operation): bool
    {
        if ($operation === 0) {
            // PHP asks if exclusive locks are supported this pretty stupid way :/
            return true;
        }

        if (isset(self::$virtualFiles[$this->path])) {
            $this->log(self::LOCK, 0.0, 0, $this->path, 'flock', [$operation], true);

            return true;
        }

        $result = false;
        try {
             $result = $this->previous('flock', $this->handle, $operation);
        } finally {
            $this->log(self::LOCK, $this->duration, 0, $this->path, 'flock', [$operation], $result);
        }

        return $result;
    }

    /**
     * @return string|false
     */
    public function stream_read(int $count, bool $buffer = false)
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->read($count);
            $this->log(self::READ, 0.0, 0, $this->path, 'fread', [$count], $result);

            return $result;
        }

        if (!$buffer && $this->readBuffer) {
            $result = substr($this->readBuffer, 0, $count);
            $this->readBuffer = substr($this->readBuffer, $count);

            $length = strlen($result);
            $this->log(self::READ, 0.0, $length, $this->path, 'fread', [$count, 'buffered'], $length);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('fread', $this->handle, $count);
        } finally {
            $params = $buffer ? [$count, 'buffering'] : [$count];
            $length = $result === false ? 0 : strlen($result);
            $this->log(self::READ, $this->duration, $length, $this->path, 'fread', $params, $length);
        }

        return $result;
    }

    /**
     * @return int|false
     */
    public function stream_write(string $data)
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->write($data);
            $this->log(self::WRITE, 0.0, 0, $this->path, 'fwrite', [$data], $result);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('fwrite', $this->handle, $data);
        } finally {
            $this->log(self::WRITE, $this->duration, $result, $this->path, 'fwrite', [strlen($data)], $result);
        }

        return $result;
    }

    public function stream_truncate(int $newSize): bool
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->truncate($newSize);
            $this->log(self::TRUNCATE, 0.0, 0, $this->path, 'ftruncate', [$newSize], $result);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('ftruncate', $this->handle, $newSize);
        } finally {
            $this->log(self::TRUNCATE, $this->duration, 0, $this->path, 'ftruncate', [$newSize], $result);
        }

        return $result;
    }

    public function stream_flush(): bool
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $this->log(self::FLUSH, 0.0, 0, $this->path, 'fflush', [], true);

            return true;
        }

        $result = false;
        try {
            $result = $this->previous('fflush', $this->handle);
        } finally {
            $this->log(self::FLUSH, $this->duration, 0, $this->path, 'fflush', [], $result);
        }

        return $result;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->seek($offset, $whence);
            $this->log(self::SEEK, 0.0, 0, $this->path, 'fseek', [$offset, $whence], $result);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('fseek', $this->handle, $offset, $whence) === 0;
        } finally {
            $this->log(self::SEEK, $this->duration, 0, $this->path, 'fseek', [$offset], $result);
        }

        return $result;
    }

    public function stream_eof(): bool
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->eof();
            $this->log(self::INFO, 0.0, 0, $this->path, 'feof', [], $result);

            return $result;
        }

        if ($this->readBuffer) {
            $result = false;
            $this->log(self::INFO, 0.0, 0, $this->path, 'feof', ['buffered'], $result);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('feof', $this->handle);
        } finally {
            $this->log(self::INFO, $this->duration, 0, $this->path, 'feof', [], $result);
        }

        return $result;
    }

    /**
     * @return int|false
     */
    public function stream_tell()
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->tell();
            $this->log(self::INFO, 0.0, 0, $this->path, 'ftell', [], $result);

            return $result;
        }

        $result = false;
        try {
            $result = $this->previous('ftell', $this->handle);
        } finally {
            $this->log(self::INFO, $this->duration, 0, $this->path, 'ftell', [], $result);
        }

        return $result;
    }

    /**
     * @return mixed[]|false
     */
    public function stream_stat()
    {
        if (isset(self::$virtualFiles[$this->path])) {
            $result = self::$virtualFiles[$this->path]->stat();
            $this->log(self::STAT, 0.0, 0, $this->path, 'fstat', [], $result);

            return $result;
        }

        $result = false;
        try {
            /** @var positive-int[]|false $result */
            $result = $this->previous('fstat', $this->handle);
        } finally {
            $this->log(self::STAT, $this->duration, 0, $this->path, 'fstat', [], $this->formatStat($result));
        }

        // file size is changed by Intercept magic
        if ($this->bufferSize !== null && $result !== false) {
            $result['size'] = $this->bufferSize;
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        $path = self::$pathRedirects[$path] ?? $path;

        $result = false;
        switch ($option) {
            case STREAM_META_TOUCH:
                $t1 = $value[0] ?? time();
                $t2 = $value[1] ?? time();

                if (isset(self::$virtualFiles[$path])) {
                    $this->log(self::META, 0.0, 0, $path, 'touch', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('touch', $path, $t1, $t2);
                } finally {
                    $this->log(self::META, $this->duration, 0, $path, 'touch', [$t1, $t2], $result);
                }

                return $result;
            case STREAM_META_OWNER:
            case STREAM_META_OWNER_NAME:
                if (isset(self::$virtualFiles[$path])) {
                    $this->log(self::META, 0.0, 0, $path, 'chown', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('chown', $path, $value);
                } finally {
                    $this->log(self::META, $this->duration, 0, $path, 'chown', [$value], $result);
                }

                return $result;
            case STREAM_META_GROUP:
            case STREAM_META_GROUP_NAME:
                if (isset(self::$virtualFiles[$path])) {
                    $this->log(self::META, 0.0, 0, $path, 'chgrp', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('chgrp', $path, $value);
                } finally {
                    $this->log(self::META, $this->duration, 0, $path, 'chgrp', [$value], $result);
                }

                return $result;
            case STREAM_META_ACCESS:
                if (isset(self::$virtualFiles[$path])) {
                    $this->log(self::META, 0.0, 0, $path, 'chmod', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('chmod', $path, $value);
                } finally {
                    $this->log(self::META, $this->duration, 0, $path, 'chmod', [$value], $result);
                }

                return $result;
            default:
                return false;
        }
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        $result = false;
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                if (isset(self::$virtualFiles[$this->path])) {
                    $this->log(self::STAT, 0.0, 0, $this->path, 'set_blocking', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('stream_set_blocking', $this->handle, (bool) $arg1);
                } finally {
                    $this->log(self::SET, $this->duration, 0, $this->path, 'set_blocking', [$option, $arg1], $result);
                }
                return $result;
            case STREAM_OPTION_READ_BUFFER:
                if (isset(self::$virtualFiles[$this->path])) {
                    $this->log(self::STAT, 0.0, 0, $this->path, 'set_read_buffer', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('stream_set_read_buffer', $this->handle, $arg1);
                } finally {
                    $this->log(self::SET, $this->duration, 0, $this->path, 'set_read_buffer', [$option, $arg1], (bool) $result);
                }
                return (bool) $result;
            case STREAM_OPTION_WRITE_BUFFER:
                if (isset(self::$virtualFiles[$this->path])) {
                    $this->log(self::STAT, 0.0, 0, $this->path, 'set_write_buffer', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('stream_set_write_buffer', $this->handle, $arg1);
                } finally {
                    $this->log(self::SET, $this->duration, 0, $this->path, 'set_write_buffer', [$option, $arg1], (bool) $result);
                }
                return (bool) $result;
            case STREAM_OPTION_READ_TIMEOUT:
                if (isset(self::$virtualFiles[$this->path])) {
                    $this->log(self::STAT, 0.0, 0, $this->path, 'set_read_timeout', [], true);

                    return true;
                }

                try {
                    $result = $this->previous('stream_set_timeout', $this->handle, $arg1, $arg2);
                } finally {
                    $this->log(self::SET, $this->duration, 0, $this->path, 'set_read_timeout', [$option, $arg1, $arg2], $result);
                }
                return $result;
            default:
                return false;
        }
    }

    /**
     * @return resource|null
     */
    public function stream_cast(int $castAs)
    {
        return $this->handle;
    }

    // directory handle ------------------------------------------------------------------------------------------------

    public function dir_opendir(string $path, int $options): bool
    {
        $path = str_replace('\\', '/', $path);
        $this->path = self::$pathRedirects[$path] ?? $path;

        try {
            $this->handle = $this->context
                ? $this->previous('opendir', $this->path, $this->context)
                : $this->previous('opendir', $this->path);
        } finally {
            $this->log(self::OPEN_DIR, $this->duration, 0, $this->path, 'opendir', [], (int) $this->handle);
        }

        return (bool) $this->handle;
    }

    /**
     * @return string|false
     */
    public function dir_readdir()
    {
        $result = false;
        try {
            $result = $this->previous('readdir', $this->handle);
        } finally {
            $this->log(self::READ_DIR, $this->duration, 0, $this->path, 'readdir', [], $result);
        }

        return $result;
    }

    public function dir_rewinddir(): bool
    {
        try {
            $this->previous('rewinddir', $this->handle);
        } finally {
            $this->log(self::REWIND_DIR, $this->duration, 0, $this->path, 'rewinddir');
        }

        return true;
    }

    public function dir_closedir(): void
    {
        try {
            $this->previous('closedir', $this->handle);
        } finally {
            $this->log(self::CLOSE_DIR, $this->duration, 0, $this->path, 'closedir');
        }
    }

    // other -----------------------------------------------------------------------------------------------------------

    public function mkdir(string $path, int $permissions, int $options): bool
    {
        $path = str_replace('\\', '/', $path);
        $path = self::$pathRedirects[$path] ?? $path;
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

        $result = false;
        try {
            $result = $this->context
                ? $this->previous('mkdir', $path, $permissions, $recursive, $this->context)
                : $this->previous('mkdir', $path, $permissions, $recursive);
        } finally {
            // todo: argument hints
            $hints = ['options' => [STREAM_MKDIR_RECURSIVE => 'STREAM_MKDIR_RECURSIVE', STREAM_REPORT_ERRORS => 'STREAM_REPORT_ERRORS']];
            $params = ['permissions' => $permissions, 'mkdir.options' => $options];
            $this->log(self::MAKE_DIR, $this->duration, 0, $path, 'mkdir', $params, $result);
        }

        return $result;
    }

    public function rmdir(string $path, int $options): bool
    {
        $path = str_replace('\\', '/', $path);
        $this->path = self::$pathRedirects[$path] ?? $path;

        $result = false;
        try {
            $result = $this->context
                ? $this->previous('rmdir', $path, $this->context)
                : $this->previous('rmdir', $path);
        } finally {
            $this->log(self::REMOVE_DIR, $this->duration, 0, $path, 'rmdir', [], $result);
        }

        return $result;
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        $pathFrom = str_replace('\\', '//', $pathFrom);
        $pathFrom = self::$pathRedirects[$pathFrom] ?? $pathFrom;
        $pathTo = str_replace('\\', '/', $pathTo);
        $pathTo = self::$pathRedirects[$pathTo] ?? $pathTo;

        $result = false;
        try {
            $result = $this->context
                ? $this->previous('rename', $pathFrom, $pathTo, $this->context)
                : $this->previous('rename', $pathFrom, $pathTo);
        } finally {
            $this->log(self::RENAME, $this->duration, 0, $pathFrom, 'rename', [$pathTo], $result);
        }

        return $result;
    }

    public function unlink(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        $path = self::$pathRedirects[$path] ?? $path;

        if (isset(self::$virtualFiles[$path])) {
            self::$virtualFiles[$path]->unlink();
            $this->log(self::STAT, 0.0, 0, $path, 'fstat', [], true);

            return true;
        }

        $result = false;
        try {
            $result = $this->previous('unlink', $path);
        } finally {
            $this->log(self::UNLINK, $this->duration, 0, $path, 'unlink', [], $result);
        }

        return $result;
    }

    /**
     * @return mixed[]|false
     */
    public function url_stat(string $path, int $flags)
    {
        $path = str_replace('\\', '/', $path);
        $path = self::$pathRedirects[$path] ?? $path;

        if (isset(self::$virtualFiles[$path])) {
            $result = self::$virtualFiles[$path]->stat();
            $this->log(self::STAT, 0.0, 0, $path, 'stat', [], $result);

            return $result;
        }

        $func = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';

        $result = false;
        try {
            $result = $flags & STREAM_URL_STAT_QUIET
                ? @$this->previous($func, $path)
                : $this->previous($func, $path);
        } finally {
            $this->log(self::STAT, $this->duration, 0, $path, $func, [$flags], $this->formatStat($result));
        }

        return $result;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * @return mixed
     */
    private function previous(callable $function)
    {
        //static $restore = ['opendir', 'mkdir', 'rename', 'rmdir', 'touch', 'chown', 'chgrp', 'chmod', 'file_get_contents', 'fwrite', 'fseek', 'unlink', 'lstat', 'stat'];
        // WTF PHP Warning: stream_wrapper_register(): class 'Dogma\Debug\FileStreamWrapper' is undefined in /vagrant-src/dogma-debug/src/stream-wrappers/FileStreamWrapper.php
        static $noRestore = ['fstat', 'feof', 'fread'];

        if (!in_array($function, $noRestore, true)) {
            // todo: on 8.1 throws: Notice: stream_wrapper_restore(): file:// was never changed, nothing to restore
            // when shutting down, because of some changes in shutdown sequence
            stream_wrapper_restore(self::PROTOCOL);
        }

        $start = microtime(true);
        try {
            return $function(...array_slice(func_get_args(), 1));
        } finally {
            $this->duration = microtime(true) - $start;

            if (!in_array($function, $noRestore, true)) {
                stream_wrapper_unregister(self::PROTOCOL);
                stream_wrapper_register(self::PROTOCOL, self::class);
            }
        }
    }

    /**
     * @param positive-int[]|false $stat
     * @return mixed[]|false
     */
    private function formatStat($stat)
    {
        return $stat === false
            ? false
            : [
                0 => $this->formatMode($stat['mode']),
                'size' => $stat['size'],
                //'links' => $stat['nlink'],
                //'uid' => $stat['uid'],
                //'gid' => $stat['gid'],
                //'ctime' => Dumper::intToFormattedDate($stat['ctime']),
                'mtime' => Dumper::intToFormattedDate($stat['mtime']),
                //'atime' => Dumper::intToFormattedDate($stat['atime']),
            ];
    }

    private function formatMode(int $mode): string
    {
        static $letters = [
            0010000 => 'p', // pipe
            0020000 => 'c', // character device
            0040000 => 'd', // directory
            0060000 => 'b', // block device
            0100000 => '-', // file
            0120000 => 'l', // link
            0140000 => 's', // socket
        ];

        $perms = $mode & 0777;

        return $letters[$mode & 0770000]
            . (($perms & 0400) ? 'r' : '-')
            . (($perms & 0200) ? 'w' : '-')
            . (($perms & 0100) ? 'x' : '-')
            . (($perms & 0040) ? 'r' : '-')
            . (($perms & 0020) ? 'w' : '-')
            . (($perms & 0010) ? 'x' : '-')
            . (($perms & 0004) ? 'r' : '-')
            . (($perms & 0002) ? 'w' : '-')
            . (($perms & 0001) ? 'x' : '-');
    }

    /**
     * @return array{events: int[], data: int[], time: float[]}
     */
    public static function getStats(bool $include = false): array
    {
        $events = $include ? self::$includeEvents : self::$events;
        $data = $include ? self::$includeData : self::$data;
        $time = $include ? self::$includeTime : self::$time;

        $stats = [
            'events' => [
                'fopen' => $events[self::OPEN] ?? 0,
                'fclose' => $events[self::CLOSE] ?? 0,
                'flock' => $events[self::LOCK] ?? 0,
                'fread' => $events[self::READ] ?? 0,
                'fwrite' => $events[self::WRITE] ?? 0,
                'ftruncate' => $events[self::TRUNCATE] ?? 0,
                'fflush' => $events[self::FLUSH] ?? 0,
                'fseek' => $events[self::SEEK] ?? 0,
                'unlink' => $events[self::UNLINK] ?? 0,
                'rename' => $events[self::RENAME] ?? 0,
                'set' => $events[self::SET] ?? 0,
                'stat' => $events[self::STAT] ?? 0,
                'info' => $events[self::INFO] ?? 0,
                'meta' => $events[self::META] ?? 0,
                'opendir' => $events[self::OPEN_DIR] ?? 0,
                'readdir' => $events[self::READ_DIR] ?? 0,
                'rewinddir' => $events[self::REWIND_DIR] ?? 0,
                'closedir' => $events[self::CLOSE_DIR] ?? 0,
                'mkdir' => $events[self::MAKE_DIR] ?? 0,
                'rmdir' => $events[self::REMOVE_DIR] ?? 0,
            ],
            'data' => [
                'fopen' => $data[self::OPEN] ?? 0,
                'fclose' => $data[self::CLOSE] ?? 0,
                'flock' => $data[self::LOCK] ?? 0,
                'fread' => $data[self::READ] ?? 0,
                'fwrite' => $data[self::WRITE] ?? 0,
                'ftruncate' => $data[self::TRUNCATE] ?? 0,
                'fflush' => $data[self::FLUSH] ?? 0,
                'fseek' => $data[self::SEEK] ?? 0,
                'unlink' => $data[self::UNLINK] ?? 0,
                'rename' => $data[self::RENAME] ?? 0,
                'set' => $data[self::SET] ?? 0,
                'stat' => $data[self::STAT] ?? 0,
                'info' => $data[self::INFO] ?? 0,
                'meta' => $data[self::META] ?? 0,
                'opendir' => $data[self::OPEN_DIR] ?? 0,
                'readdir' => $data[self::READ_DIR] ?? 0,
                'rewinddir' => $data[self::REWIND_DIR] ?? 0,
                'closedir' => $data[self::CLOSE_DIR] ?? 0,
                'mkdir' => $data[self::MAKE_DIR] ?? 0,
                'rmdir' => $data[self::REMOVE_DIR] ?? 0,
            ],
            'time' => [
                'fopen' => $time[self::OPEN] ?? 0.0,
                'fclose' => $time[self::CLOSE] ?? 0.0,
                'flock' => $time[self::LOCK] ?? 0.0,
                'fread' => $time[self::READ] ?? 0.0,
                'fwrite' => $time[self::WRITE] ?? 0.0,
                'ftruncate' => $time[self::TRUNCATE] ?? 0.0,
                'fflush' => $time[self::FLUSH] ?? 0.0,
                'fseek' => $time[self::SEEK] ?? 0.0,
                'unlink' => $time[self::UNLINK] ?? 0.0,
                'rename' => $time[self::RENAME] ?? 0.0,
                'set' => $time[self::SET] ?? 0.0,
                'stat' => $time[self::STAT] ?? 0.0,
                'info' => $time[self::INFO] ?? 0.0,
                'meta' => $time[self::META] ?? 0.0,
                'opendir' => $time[self::OPEN_DIR] ?? 0.0,
                'readdir' => $time[self::READ_DIR] ?? 0.0,
                'rewinddir' => $time[self::REWIND_DIR] ?? 0.0,
                'closedir' => $time[self::CLOSE_DIR] ?? 0.0,
                'mkdir' => $time[self::MAKE_DIR] ?? 0.0,
                'rmdir' => $time[self::REMOVE_DIR] ?? 0.0,
            ],
        ];

        $stats['events']['total'] = array_sum($stats['events']);
        $stats['data']['total'] = array_sum($stats['data']);
        $stats['time']['total'] = array_sum($stats['time']);

        return $stats;
    }

}
