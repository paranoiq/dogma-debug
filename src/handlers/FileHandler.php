<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Dogma\Debug;

use const STREAM_META_ACCESS;
use const STREAM_META_GROUP;
use const STREAM_META_GROUP_NAME;
use const STREAM_META_OWNER;
use const STREAM_META_OWNER_NAME;
use const STREAM_META_TOUCH;
use const STREAM_MKDIR_RECURSIVE;
use const STREAM_OPTION_READ_BUFFER;
use const STREAM_OPTION_READ_TIMEOUT;
use const STREAM_OPTION_WRITE_BUFFER;
use const STREAM_URL_STAT_LINK;
use const STREAM_URL_STAT_QUIET;
use const STREAM_USE_PATH;
use function array_slice;
use function array_sum;
use function closedir;
use function fclose;
use function feof;
use function fflush;
use function flock;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function ftruncate;
use function func_get_args;
use function fwrite;
use function getcwd;
use function implode;
use function is_callable;
use function is_int;
use function is_scalar;
use function microtime;
use function readdir;
use function rewinddir;
use function round;
use function str_replace;
use function stream_set_blocking;
use function stream_set_read_buffer;
use function stream_set_timeout;
use function stream_set_write_buffer;
use function stream_wrapper_register;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;
use function strlen;
use function time;

/**
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 */
class FileHandler
{

    public const OPEN = 0x1;
    public const CLOSE = 0x2;
    public const LOCK = 0x4;
    public const READ = 0x8;
    public const WRITE = 0x10;
    public const TRUNCATE = 0x20;
    public const FLUSH = 0x40;
    public const SEEK = 0x80;
    public const UNLINK = 0x100;
    public const RENAME = 0x200;
    public const SET = 0x400;
    public const STAT = 0x800;
    public const INFO = 0x1000;
    public const META = 0x2000;

    public const OPEN_DIR = 0x4000;
    public const READ_DIR = 0x8000;
    public const REWIND_DIR = 0x10000;
    public const CLOSE_DIR = 0x20000;
    public const MAKE_DIR = 0x40000;
    public const REMOVE_DIR = 0x80000;
    public const CHANGE_DIR = 0x100000;

    public const DIRS = self::OPEN_DIR | self::READ_DIR | self::REWIND_DIR | self::CLOSE_DIR | self::MAKE_DIR | self::REMOVE_DIR | self::CHANGE_DIR;
    public const ALL = 0x200000 - 1;
    public const NONE = 0;

    private const PROTOCOL = 'file';
    private const INCLUDE_FLAGS = 16512;

    /** @var int Types of events to log */
    public static $log = self::ALL & ~self::INFO;

    /** @var bool Log io operations from include/require statements */
    public static $logIncludes = true;

    /** @var callable(int $event, float $time, string $message, string $path, bool $isInclude): bool User log filter */
    public static $logFilter;

    /** @var int[] */
    private static $userEvents = [];

    /** @var float[] */
    private static $userTime = [];

    /** @var int[] */
    private static $includeEvents = [];

    /** @var float[] */
    private static $includeTime = [];

    /** @var string */
    private static $workingDirectory;

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle;

    /** @var string|null */
    private $path;

    /** @var int */
    private $options;

    public static function enable(?int $log = null, bool $logIncludes = true): void
    {
        if ($log !== null) {
            self::$log = $log;
        }
        self::$logIncludes = $logIncludes;

        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function disable(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_restore(self::PROTOCOL);
    }

    private function log(int $event, float $time, string $message, string $path): void
    {
        // detect and log working directory change
        // todo: is this thread safe?
        $cwd = str_replace('\\', '/', (string) getcwd());
        if ((self::$log & self::CHANGE_DIR) && $cwd !== self::$workingDirectory) {
            $message = self::formatCall('chdir', [], (int) $this->handle);
            $message = Ansi::color(' ' . self::PROTOCOL . ': ', Ansi::WHITE, Ansi::DGREEN)
                . ' ' . Dumper::file($cwd) . ' ' . $message;

            $backtrace = Dumper::formatCallstack(Callstack::get()->filter(Dumper::$traceSkip), 1, 0, []);

            DebugClient::send(Packet::FILE_IO, $message, $backtrace);

            self::$workingDirectory = $cwd;
        }

        // log event counts and times
        $isInclude = ($this->options & self::INCLUDE_FLAGS) !== 0;
        if ($isInclude) {
            self::$includeEvents[$event] = isset(self::$includeEvents[$event]) ? self::$includeEvents[$event] + 1 : 1;
            self::$includeTime[$event] = isset(self::$includeTime[$event]) ? self::$includeTime[$event] + $time : $time;
        } else {
            self::$userEvents[$event] = isset(self::$userEvents[$event]) ? self::$userEvents[$event] + 1 : 1;
            self::$userTime[$event] = isset(self::$userTime[$event]) ? self::$userTime[$event] + $time : $time;
        }

        // events display filtering
        if ((self::$log & $event) === 0) {
            return;
        }
        if (!self::$logIncludes && $isInclude) {
            return;
        }
        if (is_callable(self::$logFilter) && !(self::$logFilter)($event, $time, $message, $path, $isInclude)) {
            return;
        }

        $path = Dumper::file($path);

        $timeFormatted = Ansi::color('(' . round($time * 1000000) . ' μs)', Dumper::$colors['time']);
        $message = Ansi::color(' ' . self::PROTOCOL . ': ', Ansi::WHITE, Ansi::DGREEN)
            . ' ' . $path . ' ' . $message . ' ' . $timeFormatted;

        $backtrace = Dumper::formatCallstack(Callstack::get()->filter(Dumper::$traceSkip), 1, 0, []);

        DebugClient::send(Packet::FILE_IO, $message, $backtrace, $time);
    }

    // file handle -----------------------------------------------------------------------------------------------------

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->path = str_replace('\\', '/', $path);
        $this->options = $options;

        $usePath = (bool) ($options & STREAM_USE_PATH);
        $time = microtime(true);
        try {
            $this->handle = $this->context
                ? $this->native('fopen', $this->path, $mode, $usePath, $this->context)
                : $this->native('fopen', $this->path, $mode, $usePath);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('open', [$mode, $options], (int) $this->handle);
            $this->log(self::OPEN, $time, $message, $this->path);
        }

        return (bool) $this->handle;
    }

    public function stream_close(): void
    {
        $time = microtime(true);
        try {
            fclose($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('close');
            $this->log(self::CLOSE, $time, $message, $this->path);
        }
    }

    public function stream_lock(int $operation): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = $operation ? flock($this->handle, $operation) : true;
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('lock', [$operation], $result);
            $this->log(self::LOCK, $time, $message, $this->path);
        }

        return $result;
    }

    /**
     * @return string|false
     */
    public function stream_read(int $count)
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = fread($this->handle, $count);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('read', [$count], $result === false ? false : strlen($result));
            $this->log(self::READ, $time, $message, $this->path);
        }

        return $result;
    }

    /**
     * @return int|false
     */
    public function stream_write(string $data)
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = fwrite($this->handle, $data);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('write', [strlen($data)], $result);
            $this->log(self::WRITE, $time, $message, $this->path);
        }

        return $result;
    }

    public function stream_truncate(int $newSize): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = ftruncate($this->handle, $newSize);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('truncate', [$newSize], $result);
            $this->log(self::TRUNCATE, $time, $message, $this->path);
        }

        return $result;
    }

    public function stream_flush(): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = fflush($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('flush', [], $result);
            $this->log(self::FLUSH, $time, $message, $this->path);
        }

        return $result;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = fseek($this->handle, $offset, $whence) === 0;
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('seek', [$offset], $result);
            $this->log(self::SEEK, $time, $message, $this->path);
        }

        return $result;
    }

    public function stream_eof(): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = feof($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('eof', [], $result);
            $this->log(self::INFO, $time, $message, $this->path);
        }

        return $result;
    }

    /**
     * @return int|false
     */
    public function stream_tell()
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = ftell($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('tell', [], $result);
            $this->log(self::INFO, $time, $message, $this->path);
        }

        return $result;
    }

    /**
     * @return mixed[]|false
     */
    public function stream_stat()
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = fstat($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('stat', [], $this->formatStat($result));
            $this->log(self::STAT, $time, $message, $this->path);
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        $result = false;
        $time = microtime(true);
        switch ($option) {
            case STREAM_META_TOUCH:
                $t1 = $value[0] ?? time();
                $t2 = $value[1] ?? time();
                try {
                    $result = $this->native('touch', $path, $t1, $t2);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('touch', [$t1, $t2], $result);
                    $this->log(self::META, $time, $message, $path);
                }

                return $result;
            case STREAM_META_OWNER:
            case STREAM_META_OWNER_NAME:
                try {
                    $result = $this->native('chown', $path, $value);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('chown', [$value], $result);
                    $this->log(self::META, $time, $message, $path);
                }

                return $result;
            case STREAM_META_GROUP:
            case STREAM_META_GROUP_NAME:
                try {
                    $result = $this->native('chgrp', $path, $value);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('chgrp', [$value], $result);
                    $this->log(self::META, $time, $message, $path);
                }

                return $result;
            case STREAM_META_ACCESS:
                try {
                    $result = $this->native('chmod', $path, $value);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('chmod', [$value], $result);
                    $this->log(self::META, $time, $message, $path);
                }

                return $result;
            default:
                return false;
        }
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        $result = false;
        $time = microtime(true);
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                try {
                    $result = stream_set_blocking($this->handle, (bool) $arg1);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('set_blocking', [$option, $arg1], $result);
                    $this->log(self::SET, $time, $message, $this->path);
                }
                return $result;
            case STREAM_OPTION_READ_BUFFER:
                try {
                    $result = stream_set_read_buffer($this->handle, $arg1);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('set_read_buffer', [$option, $arg1], (bool) $result);
                    $this->log(self::SET, $time, $message, $this->path);
                }
                return (bool) $result;
            case STREAM_OPTION_WRITE_BUFFER:
                $time = microtime(true);
                try {
                    $result = stream_set_write_buffer($this->handle, $arg1);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('set_write_buffer', [$option, $arg1], (bool) $result);
                    $this->log(self::SET, $time, $message, $this->path);
                }
                return (bool) $result;
            case STREAM_OPTION_READ_TIMEOUT:
                try {
                    $result = stream_set_timeout($this->handle, $arg1, $arg2);
                } finally {
                    $time = microtime(true) - $time;
                    $message = self::formatCall('set_read_timeout', [$option, $arg1, $arg2], $result);
                    $this->log(self::SET, $time, $message, $this->path);
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
        $this->path = $path;

        $time = microtime(true);
        try {
            $this->handle = $this->context
                ? $this->native('opendir', $this->path, $this->context)
                : $this->native('opendir', $this->path);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('opendir', [], (int) $this->handle);
            $this->log(self::OPEN_DIR, $time, $message, $this->path);
        }

        return (bool) $this->handle;
    }

    /**
     * @return string|false
     */
    public function dir_readdir()
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = readdir($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('readdir', [], $result);
            $this->log(self::READ_DIR, $time, $message, $this->path);
        }

        return $result;
    }

    public function dir_rewinddir(): bool
    {
        $time = microtime(true);
        try {
            rewinddir($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('rewinddir');
            $this->log(self::REWIND_DIR, $time, $message, $this->path);
        }

        return true;
    }

    public function dir_closedir(): void
    {
        $time = microtime(true);
        try {
            closedir($this->handle);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('closedir');
            $this->log(self::CLOSE_DIR, $time, $message, $this->path);
        }
    }

    // other -----------------------------------------------------------------------------------------------------------

    public function mkdir(string $path, int $permissions, int $options): bool
    {
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

        $result = false;
        $time = microtime(true);
        try {
            $result = $this->context
                ? $this->native('mkdir', $path, $permissions, $recursive, $this->context)
                : $this->native('mkdir', $path, $permissions, $recursive);
        } finally {
            $time = microtime(true) - $time;
            // todo: argument hints
            $hints = ['options' => [STREAM_MKDIR_RECURSIVE => 'STREAM_MKDIR_RECURSIVE', STREAM_REPORT_ERRORS => 'STREAM_REPORT_ERRORS']];
            $message = self::formatCall('mkdir', ['permissions' => $permissions, 'mkdir.options' => $options], $result);
            $this->log(self::MAKE_DIR, $time, $message, $path);
        }

        return $result;
    }

    public function rmdir(string $path, int $options): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = $this->context
                ? $this->native('rmdir', $path, $this->context)
                : $this->native('rmdir', $path);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('rmdir', [], $result);
            $this->log(self::REMOVE_DIR, $time, $message, $path);
        }

        return $result;
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = $this->context
                ? $this->native('rename', $pathFrom, $pathTo, $this->context)
                : $this->native('rename', $pathFrom, $pathTo);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('rename', [$pathTo], $result);
            $this->log(self::RENAME, $time, $message, $pathFrom);
        }

        return $result;
    }

    public function unlink(string $path): bool
    {
        $result = false;
        $time = microtime(true);
        try {
            $result = $this->native('unlink', $path);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('unlink', [], $result);
            $this->log(self::UNLINK, $time, $message, $path);
        }

        return $result;
    }

    /**
     * @return mixed[]|false
     */
    public function url_stat(string $path, int $flags)
    {
        $func = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';

        $result = false;
        $time = microtime(true);
        try {
            $result = $flags & STREAM_URL_STAT_QUIET
                ? @$this->native($func, $path)
                : $this->native($func, $path);
        } finally {
            $time = microtime(true) - $time;
            $message = self::formatCall('url_stat', [$flags], $this->formatStat($result));
            $this->log(self::STAT, $time, $message, $path);
        }

        return $result;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * @return mixed
     */
    private function native(callable $function)
    {
        stream_wrapper_restore(self::PROTOCOL);
        try {
            return $function(...array_slice(func_get_args(), 1));
        } finally {
            stream_wrapper_unregister(self::PROTOCOL);
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    /**
     * @param array<int|string|null> $params
     * @param int|string|mixed[]|bool|null $return
     */
    private static function formatCall(string $name, array $params = [], $return = null/*, array $hints = []*/): string
    {
        $info = Dumper::$showInfo;
        Dumper::$showInfo = null;

        $formatted = [];
        foreach ($params as $key => $value) {
            $key = is_int($key) ? null : $key;
            $formatted[] = Dumper::dumpValue($value, 0, $key);
        }
        $params = implode(Ansi::color(', ', Dumper::$colors['function']), $formatted);

        if ($return === null) {
            $output = '';
            $end = ')';
        } elseif (is_scalar($return)) {
            $output = ' ' . Dumper::dumpValue($return);
            $end = '):';
        } else {
            $output = [];
            foreach ($return as $k => $v) {
                if (is_int($k)) {
                    $output[] = Dumper::dumpValue($v);
                } else {
                    $output[] = Ansi::color($k . ':', Dumper::$colors['function']) . ' ' . Dumper::dumpValue($v);
                }
            }
            $output = ' ' . implode(' ', $output);
            $end = '):';
        }

        Dumper::$showInfo = $info;

        return Ansi::color($name . '(', Dumper::$colors['function']) . $params . Ansi::color($end, Dumper::$colors['function']) . $output;
    }

    /**
     * @param mixed[]|false $stat
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
     * @return array<int[]|float[]>
     */
    public static function getStats(): array
    {
        $stats = [
            'userEvents' => [
                'open' => self::$userEvents[self::OPEN] ?? 0,
                'close' => self::$userEvents[self::CLOSE] ?? 0,
                'lock' => self::$userEvents[self::LOCK] ?? 0,
                'read' => self::$userEvents[self::READ] ?? 0,
                'write' => self::$userEvents[self::WRITE] ?? 0,
                'truncate' => self::$userEvents[self::TRUNCATE] ?? 0,
                'flush' => self::$userEvents[self::FLUSH] ?? 0,
                'seek' => self::$userEvents[self::SEEK] ?? 0,
                'unlink' => self::$userEvents[self::UNLINK] ?? 0,
                'rename' => self::$userEvents[self::RENAME] ?? 0,
                'set' => self::$userEvents[self::SET] ?? 0,
                'stat' => self::$userEvents[self::STAT] ?? 0,
                'info' => self::$userEvents[self::INFO] ?? 0,
                'meta' => self::$userEvents[self::META] ?? 0,
                'opendir' => self::$userEvents[self::OPEN_DIR] ?? 0,
                'readdir' => self::$userEvents[self::READ_DIR] ?? 0,
                'rewinddir' => self::$userEvents[self::REWIND_DIR] ?? 0,
                'closedir' => self::$userEvents[self::CLOSE_DIR] ?? 0,
                'mkdir' => self::$userEvents[self::MAKE_DIR] ?? 0,
                'rmdir' => self::$userEvents[self::REMOVE_DIR] ?? 0,
            ],
            'userTime' => [
                'open' => self::$userTime[self::OPEN] ?? 0.0,
                'close' => self::$userTime[self::CLOSE] ?? 0.0,
                'lock' => self::$userTime[self::LOCK] ?? 0.0,
                'read' => self::$userTime[self::READ] ?? 0.0,
                'write' => self::$userTime[self::WRITE] ?? 0.0,
                'truncate' => self::$userTime[self::TRUNCATE] ?? 0.0,
                'flush' => self::$userTime[self::FLUSH] ?? 0.0,
                'seek' => self::$userTime[self::SEEK] ?? 0.0,
                'unlink' => self::$userTime[self::UNLINK] ?? 0.0,
                'rename' => self::$userTime[self::RENAME] ?? 0.0,
                'set' => self::$userTime[self::SET] ?? 0.0,
                'stat' => self::$userTime[self::STAT] ?? 0.0,
                'info' => self::$userTime[self::INFO] ?? 0.0,
                'meta' => self::$userTime[self::META] ?? 0.0,
                'opendir' => self::$userTime[self::OPEN_DIR] ?? 0.0,
                'readdir' => self::$userTime[self::READ_DIR] ?? 0.0,
                'rewinddir' => self::$userTime[self::REWIND_DIR] ?? 0.0,
                'closedir' => self::$userTime[self::CLOSE_DIR] ?? 0.0,
                'mkdir' => self::$userTime[self::MAKE_DIR] ?? 0.0,
                'rmdir' => self::$userTime[self::REMOVE_DIR] ?? 0.0,
            ],
            'includeEvents' => [
                'open' => self::$includeEvents[self::OPEN] ?? 0,
                'close' => self::$includeEvents[self::CLOSE] ?? 0,
                'lock' => self::$includeEvents[self::LOCK] ?? 0,
                'read' => self::$includeEvents[self::READ] ?? 0,
                'write' => self::$includeEvents[self::WRITE] ?? 0,
                'truncate' => self::$includeEvents[self::TRUNCATE] ?? 0,
                'flush' => self::$includeEvents[self::FLUSH] ?? 0,
                'seek' => self::$includeEvents[self::SEEK] ?? 0,
                'unlink' => self::$includeEvents[self::UNLINK] ?? 0,
                'rename' => self::$includeEvents[self::RENAME] ?? 0,
                'set' => self::$includeEvents[self::SET] ?? 0,
                'stat' => self::$includeEvents[self::STAT] ?? 0,
                'info' => self::$includeEvents[self::INFO] ?? 0,
                'meta' => self::$includeEvents[self::META] ?? 0,
                'opendir' => self::$includeEvents[self::OPEN_DIR] ?? 0,
                'readdir' => self::$includeEvents[self::READ_DIR] ?? 0,
                'rewinddir' => self::$includeEvents[self::REWIND_DIR] ?? 0,
                'closedir' => self::$includeEvents[self::CLOSE_DIR] ?? 0,
                'mkdir' => self::$includeEvents[self::MAKE_DIR] ?? 0,
                'rmdir' => self::$includeEvents[self::REMOVE_DIR] ?? 0,
            ],
            'includeTime' => [
                'open' => self::$includeTime[self::OPEN] ?? 0.0,
                'close' => self::$includeTime[self::CLOSE] ?? 0.0,
                'lock' => self::$includeTime[self::LOCK] ?? 0.0,
                'read' => self::$includeTime[self::READ] ?? 0.0,
                'write' => self::$includeTime[self::WRITE] ?? 0.0,
                'truncate' => self::$includeTime[self::TRUNCATE] ?? 0.0,
                'flush' => self::$includeTime[self::FLUSH] ?? 0.0,
                'seek' => self::$includeTime[self::SEEK] ?? 0.0,
                'unlink' => self::$includeTime[self::UNLINK] ?? 0.0,
                'rename' => self::$includeTime[self::RENAME] ?? 0.0,
                'set' => self::$includeTime[self::SET] ?? 0.0,
                'stat' => self::$includeTime[self::STAT] ?? 0.0,
                'info' => self::$includeTime[self::INFO] ?? 0.0,
                'meta' => self::$includeTime[self::META] ?? 0.0,
                'opendir' => self::$includeTime[self::OPEN_DIR] ?? 0.0,
                'readdir' => self::$includeTime[self::READ_DIR] ?? 0.0,
                'rewinddir' => self::$includeTime[self::REWIND_DIR] ?? 0.0,
                'closedir' => self::$includeTime[self::CLOSE_DIR] ?? 0.0,
                'mkdir' => self::$includeTime[self::MAKE_DIR] ?? 0.0,
                'rmdir' => self::$includeTime[self::REMOVE_DIR] ?? 0.0,
            ],
        ];

        $stats['userEvents']['total'] = array_sum($stats['userEvents']);
        $stats['userTime']['total'] = array_sum($stats['userTime']);
        $stats['includeEvents']['total'] = array_sum($stats['includeEvents']);
        $stats['includeTime']['total'] = array_sum($stats['includeTime']);

        return $stats;
    }

}
