<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use const SEEK_SET;

/**
 * Common ancestor for stream handlers (file, phar, http)
 * Track stream handler and stream filter registrations
 */
abstract class StreamWrapper
{

    public const NAME = 'wrapper';

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
    public const META = 0x1000;
    public const INFO = 0x2000;

    public const OPEN_DIR = 0x4000;
    public const READ_DIR = 0x8000;
    public const REWIND_DIR = 0x10000;
    public const CLOSE_DIR = 0x20000;
    public const MAKE_DIR = 0x40000;
    public const REMOVE_DIR = 0x80000;
    public const CHANGE_DIR = 0x100000;

    public const FILES = 0x1FFF; // without info
    public const DIRS = 0x1FC000;
    public const ALL = 0x1FFFFF;
    public const NONE = 0;

    // from https://github.com/php/php-src/blob/8c442f171933b7b4430262490ad2d236b53a4137/main/php_streams.h
    protected const STREAM_IGNORE_PATH = 0x0; // IGNORE_PATH
    protected const STREAM_USE_PATH = 0x1; // USE_PATH
    protected const STREAM_IGNORE_URL = 0x2; // IGNORE_URL
    protected const STREAM_REPORT_ERRORS = 0x8; // REPORT_ERRORS
    protected const STREAM_MUST_SEEK = 0x10; // seekable, but not writeable
    protected const STREAM_WILL_CAST = 0x20; // will be cast to FILE* or socket (skips buffering)
    protected const STREAM_LOCATE_WRAPPERS_ONLY = 0x40; // applies to php_stream_locate_url_wrapper
    protected const STREAM_OPEN_FOR_INCLUDE = 0x80; // used by include/require functions
    protected const STREAM_USE_URL = 0x100; // ONLY open urls
    protected const STREAM_ONLY_GET_HEADERS = 0x200; // used when only the headers from HTTP request are to be fetched
    protected const STREAM_DISABLE_OPEN_BASEDIR = 0x400; // don't apply open_basedir checks
    protected const STREAM_OPEN_PERSISTENT = 0x800; // get (or create) a persistent version of the stream
    protected const STREAM_USE_GLOB_DIR_OPEN = 0x1000; // use glob stream for directory open in plain files stream
    protected const STREAM_DISABLE_URL_PROTECTION = 0x2000; // don't check allow_url_fopen and allow_url_include
    protected const STREAM_ASSUME_REALPATH = 0x4000; // assume the path passed in exists and is fully expanded, avoiding syscalls
    protected const STREAM_USE_BLOCKING_PIPE = 0x8000; // allow blocking reads on anonymous pipes on Windows
    protected const STREAM_OPEN_FOR_ZEND_STREAM = 0x10000; // used by include/require functions

    /**
     * @return array{events: int[], data: int[], time: float[]}
     */
    abstract public static function getStats(bool $include = false): array;

    // stream wrapper "interface". implemented by StreamWrapperMixin
    // file handle -----------------------------------------------------------------------------------------------------

    abstract public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool;

    abstract public function stream_close(): void;

    abstract public function stream_lock(int $operation): bool;

    /**
     * @return string|false
     */
    abstract public function stream_read(int $count, bool $buffer = false);

    /**
     * @return int|false
     */
    abstract public function stream_write(string $data);

    abstract public function stream_truncate(int $newSize): bool;

    abstract public function stream_flush(): bool;

    abstract public function stream_seek(int $offset, int $whence = SEEK_SET): bool;

    abstract public function stream_eof(): bool;

    /**
     * @return int|false
     */
    abstract public function stream_tell();

    /**
     * @return mixed[]|false
     */
    abstract public function stream_stat();

    /**
     * @param mixed $value
     */
    abstract public function stream_metadata(string $path, int $option, $value): bool;

    abstract public function stream_set_option(int $option, int $arg1, ?int $arg2): bool;

    /**
     * @return resource|null
     */
    abstract public function stream_cast(int $castAs);

    // directory handle ------------------------------------------------------------------------------------------------

    abstract public function dir_opendir(string $path, int $options): bool;

    /**
     * @return string|false
     */
    abstract public function dir_readdir();

    abstract public function dir_rewinddir(): bool;

    abstract public function dir_closedir(): void;

    // other -----------------------------------------------------------------------------------------------------------

    abstract public function mkdir(string $path, int $permissions, int $options): bool;

    abstract public function rmdir(string $path, int $options): bool;

    abstract public function rename(string $pathFrom, string $pathTo): bool;

    abstract public function unlink(string $path): bool;

    /**
     * @return mixed[]|false
     */
    abstract public function url_stat(string $path, int $flags);

}
