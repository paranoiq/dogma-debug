<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Dogma\Debug\Colors as C;
use function array_slice;
use function closedir;
use function debug_backtrace;
use function feof;
use function fflush;
use function ftell;
use function ftruncate;
use function func_get_args;
use function fwrite;
use function readdir;
use function rewinddir;
use function str_replace;
use function stream_wrapper_register;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;
use function time;
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
use const STREAM_REPORT_ERRORS;
use const STREAM_URL_STAT_LINK;
use const STREAM_URL_STAT_QUIET;
use const STREAM_USE_PATH;

/**
 * Based on dg/bypass-finals (https://github.com/dg/bypass-finals/blob/master/src/BypassFinals.php)
 */
class FileStreamWrapper
{

    public const OPEN = 1;
    public const CLOSE = 2;
    public const LOCK = 4;
    public const READ = 8;
    public const WRITE = 16;
    public const FLUSH = 32;
    public const SEEK = 64;
    public const SET_OPTION = 128;
    public const REMOVE = 256;
    public const RENAME = 512;
    public const STAT = 1024;
    public const META = 2048;

    public const ALL = 4095;

    private const PROTOCOL = 'file';

    public static $log = self::OPEN;

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle;

    /** @var string|null */
    private $path;

    public static function enable(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function disable(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_restore(self::PROTOCOL);
    }

    private static function log($action, $path): void
    {
        $path = Dumper::file($path);

        $message = C::color(' ' . self::PROTOCOL . ': ', C::WHITE, C::DGREEN)
            . ' ' . $path . ' ' . C::lgreen($action) . "\n";

        $message .= Dumper::formatTrace(debug_backtrace(), null);

        DebugClient::remoteWrite($message);
    }

    // file handle -----------------------------------------------------------------------------------------------------

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->path = str_replace('\\', '/', $path);
        //rl($options);
        //rl(STREAM_USE_PATH);
        //rl(STREAM_REPORT_ERRORS);

        if (self::$log & self::OPEN) {
            self::log("open($mode)", $this->path);
        }
        // todo: remove?
        //if ($mode === 'c') {
        //    DebugClient::remoteWrite(Dumper::dumpBacktrace(debug_backtrace()) . "\n");
        //}

        $usePath = (bool) ($options & STREAM_USE_PATH);
        $this->handle = $this->context
            ? $this->native('fopen', $this->path, $mode, $usePath, $this->context)
            : $this->native('fopen', $this->path, $mode, $usePath);

        return (bool) $this->handle;
    }

    public function stream_close(): void
    {
        if (self::$log & self::CLOSE) {
            self::log('close', $this->path);
        }

        fclose($this->handle);
    }

    public function stream_lock(int $operation): bool
    {
        if (self::$log & self::LOCK) {
            self::log("lock($operation)", $this->path);
        }

        return $operation ? flock($this->handle, $operation) : true;
    }

    public function stream_read(int $count)
    {
        if (self::$log & self::READ) {
            self::log("read($count)", $this->path);
        }

        return fread($this->handle, $count);
    }

    public function stream_write(string $data)
    {
        if (self::$log & self::WRITE) {
            self::log('write', $this->path);
        }

        return fwrite($this->handle, $data);
    }

    public function stream_truncate(int $newSize): bool
    {
        if (self::$log & self::WRITE) {
            self::log("truncate($newSize)", $this->path);
        }

        return ftruncate($this->handle, $newSize);
    }

    public function stream_flush(): bool
    {
        if (self::$log & self::FLUSH) {
            self::log('flush', $this->path);
        }

        return fflush($this->handle);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if (self::$log & self::SEEK) {
            self::log("seek($offset)", $this->path);
        }

        return fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_tell(): int
    {
        return ftell($this->handle);
    }

    public function stream_stat()
    {
        if (self::$log & self::OPEN) {
            self::log('stat', $this->path);
        }

        return fstat($this->handle);
    }

    public function stream_metadata(string $path, int $option, $value): bool
    {
        switch ($option) {
            case STREAM_META_TOUCH:
                if (self::$log & self::META) {
                    self::log('touch', $path);
                }

                return $this->native('touch', $path, $value[0] ?? time(), $value[1] ?? time());
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                if (self::$log & self::META) {
                    self::log("chown($value)", $path);
                }

                return $this->native('chown', $path, $value);
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                if (self::$log & self::META) {
                    self::log("chgrp($value)", $path);
                }

                return $this->native('chgrp', $path, $value);
            case STREAM_META_ACCESS:
                if (self::$log & self::META) {
                    self::log("chmod($value)", $path);
                }

                return $this->native('chmod', $path, $value);
        }

        return false;
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        if (self::$log & self::SET_OPTION) {
            switch ($option) {
                case STREAM_OPTION_BLOCKING:
                    $option = 'BLOCKING';
                    break;
                case STREAM_OPTION_READ_BUFFER:
                    $option = 'READ_BUFFER';
                    break;
                case STREAM_OPTION_WRITE_BUFFER:
                    $option = 'WRITE_BUFFER';
                    break;
                case STREAM_OPTION_READ_TIMEOUT:
                    $option = 'READ_TIMEOUT';
                    break;
            }
            self::log("set($option, $arg1, $arg2)", $this->path);
        }

        return false;
    }

    public function stream_cast(int $castAs)
    {
        return $this->handle;
    }

    // directory handle ------------------------------------------------------------------------------------------------

    public function dir_opendir(string $path, int $options): bool
    {
        $this->path = $path;

        if (self::$log & self::OPEN) {
            self::log('opendir', $this->path);
        }

        $this->handle = $this->context
            ? $this->native('opendir', $this->path, $this->context)
            : $this->native('opendir', $this->path);

        return (bool) $this->handle;
    }

    public function dir_readdir()
    {
        if (self::$log & self::READ) {
            self::log('readdir', $this->path);
        }

        return readdir($this->handle);
    }

    public function dir_rewinddir(): bool
    {
        if (self::$log & self::SEEK) {
            self::log('rewinddir', $this->path);
        }

        rewinddir($this->handle);

        return true;
    }

    public function dir_closedir(): void
    {
        if (self::$log & self::CLOSE) {
            self::log('closedir', $this->path);
        }

        closedir($this->handle);
    }

    // other -----------------------------------------------------------------------------------------------------------

    public function mkdir(string $path, int $mode, int $options): bool
    {
        if (self::$log & self::OPEN) {
            self::log('mkdir', $path);
        }

        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

        return $this->context
            ? $this->native('mkdir', $path, $mode, $recursive, $this->context)
            : $this->native('mkdir', $path, $mode, $recursive);
    }

    public function rmdir(string $path, int $options): bool
    {
        if (self::$log & self::REMOVE) {
            self::log('rmdir', $path);
        }

        return $this->context
            ? $this->native('rmdir', $path, $this->context)
            : $this->native('rmdir', $path);
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        if (self::$log & self::RENAME) {
            self::log("rename($pathTo)", $pathFrom);
        }

        return $this->context
            ? $this->native('rename', $pathFrom, $pathTo, $this->context)
            : $this->native('rename', $pathFrom, $pathTo);
    }

    public function unlink(string $path): bool
    {
        if (self::$log & self::REMOVE) {
            self::log('unlink', $path);
        }

        return $this->native('unlink', $path);
    }

    public function url_stat(string $path, int $flags)
    {
        if (self::$log & self::STAT) {
            self::log('url_stat', $path);
        }

        $func = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';

        return $flags & STREAM_URL_STAT_QUIET
            ? @$this->native($func, $path)
            : $this->native($func, $path);
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    private function native(string $func)
    {
        stream_wrapper_restore(self::PROTOCOL);
        try {
            return $func(...array_slice(func_get_args(), 1));
        } finally {
            stream_wrapper_unregister(self::PROTOCOL);
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

}
