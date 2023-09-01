<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_values;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function strlen;
use function substr;
use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

class VirtualFile
{

    /** @var string|null */
    private $contents;

    /** @var string|null */
    private $mode;

    /** @var bool */
    private $read = false;

    /** @var bool */
    private $write = false;

    /** @var int */
    private $position = 0;

    public function __construct(?string $contents)
    {
        $this->contents = $contents;
    }

    public function open(string $mode): bool
    {
        $mode = str_replace(['b', 'e'], '', $mode);

        $this->mode = $mode;
        switch ($mode) {
            case 'r':
            case 'r+':
                // open
                $this->read = true;
                $this->write = str_ends_with($mode, '+');

                return $this->contents !== null;
            case 'w':
            case 'w+':
                // open & truncate
                if ($this->contents === null) {
                    ///
                    return false;
                } else {
                    $this->write = true;
                    $this->read = str_ends_with($mode, '+');
                    $this->contents = '';

                    return true;
                }
            case 'a':
            case 'a+':
                // open/create & seek end
                $this->write = true;
                $this->read = str_ends_with($mode, '+');
                if ($this->contents === null) {
                    $this->contents = '';
                }
                $this->position = strlen($this->contents);

                return true;
            case 'x':
            case 'x+':
                // create
                $this->write = true;
                $this->read = str_ends_with($mode, '+');
                if ($this->contents !== null) {
                    /////
                    return false;
                } else {
                    $this->contents = '';

                    return true;
                }
            case 'c':
            case 'c+':
                // open/create
                $this->write = true;
                $this->read = str_ends_with($mode, '+');
                if ($this->contents === null) {
                    $this->contents = '';
                }

                return true;
        }

        return true;
    }

    public function close(): void
    {
        $this->mode = null;
        $this->read = false;
        $this->write = false;
    }

    /**
     * @return string|false
     */
    public function read(int $length)
    {
        if (!$this->read) {
            ///
            return false;
        }

        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * @return false|int
     */
    public function write(string $data)
    {
        if (!$this->write) {
            ///
            return false;
        }

        // splice data from current positions (does not brake things when writing in the middle or appending)
        $this->contents = substr($this->contents, 0, $this->position) . $data . substr($this->contents, $this->position + strlen($data));

        return strlen($data);
    }

    public function truncate(int $newSize): bool
    {
        if (!$this->write) {
            return false;
        } else {
            $this->contents = substr($this->contents, 0, $newSize);
            if (strlen($this->contents) < $newSize) {
                // filling skipped space with NUL bytes
                $this->contents .= str_repeat("\x00", $newSize - strlen($this->contents));
            }
            $this->position = $newSize;

            return true;
        }
    }

    public function seek(int $offset, int $whence): bool
    {
        if ($this->mode[0] === 'a') {
            return true;
        } elseif ($whence === SEEK_SET) {
            $this->position = $offset;
        } elseif ($whence === SEEK_CUR) {
            $this->position += $offset;
        } elseif ($whence === SEEK_END) {
            $this->position = strlen($this->contents) + $offset;
            if ($this->write) {
                // filling skipped space with NUL bytes
                $this->contents .= str_repeat("\x00", $offset);
            }
        } else {
            return false;
        }

        return true;
    }

    public function eof(): bool
    {
        return strlen($this->contents) === $this->position;
    }

    public function tell(): int
    {
        return $this->position;
    }

    /**
     * @return int[]|false
     */
    public function stat()
    {
        if ($this->contents === null) {
            return false;
        }

        $stat = [
            'dev' => 69,
            'ino' => 0,
            'mode' => 0100777,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => -1,
            'size' => strlen($this->contents),
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];

        return array_values($stat) + $stat;
    }

    public function unlink(): void
    {
        $this->contents = null;
        $this->mode = null;
        $this->write = false;
        $this->read = false;
        $this->position = 0;
    }

}
