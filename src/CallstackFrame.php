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
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;
use RuntimeException;
use function array_key_exists;
use function array_slice;
use function array_values;
use function count;
use function error_clear_last;
use function error_get_last;
use function file;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use const FILE_IGNORE_NEW_LINES;

class CallstackFrame
{

    public const INSTANCE = '->';
    public const STATIC = '::';

    /** @var string|null */
    public $file;

    /** @var int|null */
    public $line;

    /** @var string|null */
    public $function;

    /** @var class-string|null */
    public $class;

    /** @var string|null self::INSTANCE | self::STATIC */
    public $type;

    /** @var object|null */
    public $object;

    /** @var mixed[]|false */
    public $args;

    /** @var int|null */
    public $number;

    /** @var float|null */
    public $time;

    /** @var int|null */
    public $memory;

    /**
     * @param class-string|null $class
     * @param self::INSTANCE|self::STATIC|null $type
     * @param object|null $object
     * @param mixed[]|false $args (false means unknown)
     */
    public function __construct(
        ?string $file,
        ?int $line,
        ?string $class = null,
        ?string $function = null,
        ?string $type = null,
        $object = null,
        $args = false,
        ?int $number = null,
        ?float $time = null,
        ?int $memory = null
    ) {
        if ($class !== null && $type === null) {
            throw new LogicException('When $class is set, then $type must also be set.');
        }
        if ($file === '-') {
            $file = null;
        }

        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
        $this->type = $type;
        $this->object = $object;
        $this->args = $args;
        $this->number = $number;
        $this->time = $time;
        $this->memory = $memory;
    }

    /**
     * @param string|array{string, string} $callable
     */
    public function is($callable): bool
    {
        if (is_array($callable)) {
            return $this->class === $callable[0] && $this->function === $callable[1];
        } else {
            return $this->class === null && $this->function === $callable;
        }
    }

    public function getFullName(): string
    {
        return $this->class ? ($this->class . $this->type . $this->function) : $this->function;
    }

    public function isClosure(): bool
    {
        return $this->class === null && str_ends_with($this->function, '{closure}');
    }

    public function isFunction(): bool
    {
        return $this->class === null && !str_ends_with($this->function, '{closure}')
            && !in_array($this->function, Callstack::INCLUDES, true);
    }

    public function isInclude(): bool
    {
        return $this->class === null && in_array($this->function, Callstack::INCLUDES, true);
    }

    public function isMethod(): bool
    {
        return $this->class !== null && !str_ends_with($this->function, '{closure}');
    }

    public function isStatic(): bool
    {
        return $this->type === self::STATIC;
    }

    public function isAnonymous(): bool
    {
        return $this->class !== null && str_starts_with($this->class, 'class@anonymous');
    }

    /**
     * @return mixed[]
     */
    public function getNamedArgs(): array
    {
        if ($this->args === [] || $this->args === false) {
            return [];
        }

        $reflection = $this->getCallableReflection();
        if ($reflection === null) {
            return $this->args;
        }

        $names = [];
        foreach ($reflection->getParameters() as $param) {
            $names[] = $param->getName();
        }

        // todo: how will this cope with PHP 'named arguments'?
        $args = $this->args;
        $named = [];
        foreach ($names as $n => $param) {
            if ($args === []) {
                // all used
                return $named;
            } elseif (strpos($param, '.') !== false) {
                // ... variadic
                $named[$param] = array_values($args);

                return $named;
            } elseif (array_key_exists($n, $args)) {
                // named
                $named[$param] = $args[$n];
                unset($args[$n]);
            }
        }
        // other
        foreach ($args as $n => $value) {
            $named[$n] = $value;
        }

        return $named;
    }

    // reflection ------------------------------------------------------------------------------------------------------

    public function getCallableReflection(): ?ReflectionFunctionAbstract
    {
        if ($this->isFunction()) {
            return new ReflectionFunction($this->function);
        } elseif ($this->isMethod()) {
            /** @var string $object */
            $object = $this->object ?? $this->class;

            return new ReflectionMethod($object, $this->function);
        } elseif ($this->isClosure()) {
            // todo: this does not work. investigate
            //return new ReflectionFunction($this->function);
            return null;
        }

        return null;
    }

    public function getFunctionReflection(): ReflectionFunction
    {
        if (!$this->isFunction()) {
            throw new LogicException($this->getFullName() . ' is not a function.');
        }

        return new ReflectionFunction($this->function);
    }

    public function getMethodReflection(): ReflectionMethod
    {
        if (!$this->isMethod()) {
            throw new LogicException($this->getFullName() . ' is not a method.');
        }
        /** @var string $object */
        $object = $this->object ?? $this->class;

        return new ReflectionMethod($object, $this->function);
    }

    public function getObjectReflection(): ReflectionObject
    {
        if ($this->object === null) {
            throw new LogicException($this->getFullName() . ' is not an instance method.');
        }

        return new ReflectionObject($this->object);
    }

    public function getClassReflection(): ReflectionClass
    {
        if ($this->class === null) {
            throw new LogicException($this->getFullName() . ' is not a class method.');
        }

        return new ReflectionClass($this->class);
    }

    // code ------------------------------------------------------------------------------------------------------------

    /**
     * @return non-empty-string|null
     */
    public function getLineCode(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        $line = self::readLines($this->file, $this->line - 1, 1)[0] ?? '';

        return $line !== '' ? $line : null;
    }

    /**
     * @return array<int,string>|null
     */
    public function getLinesAround(int $before, int $after): ?array
    {
        if ($this->file === null) {
            return null;
        }

        $lines = self::readLines($this->file);
        $start = $this->line - $before - 1;
        if ($start <= 0) {
            $start = 0;
        } elseif (count($lines) - $start < $after) {
            $start = count($lines) - $before - $after - 1;
        }

        $lines = array_slice($lines, $start, $before + $after + 1);

        $res = [];
        foreach ($lines as $i => $line) {
            $res[$start + $i + 1] = $line;
        }

        return $res;
    }

    public function getFunctionCode(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        if ($this->isFunction() || $this->isClosure()) {
            $reflection = $this->getFunctionReflection();
            if ($reflection->isInternal()) {
                return null;
            }
        } elseif ($this->isMethod()) {
            $reflection = $this->getMethodReflection();
            if ($reflection->isInternal()) {
                return null;
            }
        } else {
            return null;
        }

        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        return implode("\n", self::readLines($this->file, $start - 1, $end - $start + 1));
    }

    public function getClassCode(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        if ($this->isMethod()) {
            $reflection = $this->getClassReflection();
            if ($reflection->isInternal()) {
                return null;
            }
        } else {
            return null;
        }

        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        return implode("\n", self::readLines($this->file, $start - 1, $end - $start + 1));
    }

    public function getFileCode(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        return self::read($this->file);
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * @param positive-int|null $length
     */
    public static function read(string $file, int $offset = 0, ?int $length = null): string
    {
        error_clear_last();

        // $length cannot be of null nor negative and 0 means zero length
        if ($length !== null) {
            $result = @file_get_contents($file, false, null, $offset, $length);
        } else {
            $result = @file_get_contents($file, false, null, $offset);
        }

        if ($result === false) {
            throw new RuntimeException("Cannot read file {$file}: " . error_get_last()['message']);
        }

        return $result;
    }

    /**
     * @return array<int,string>
     */
    public static function readLines(string $file, int $start = 0, ?int $count = null): array
    {
        error_clear_last();
        if (!is_file($file)) {
            return [];
        }
        $result = @file($file, FILE_IGNORE_NEW_LINES);

        if ($result === false) {
            throw new RuntimeException("Cannot read file lines from {$file}: " . error_get_last()['message']);
        }

        return $start !== 0 || $count !== null
            ? array_slice($result, $start, (int) $count, false)
            : $result;
    }

    public function export(): string
    {
        return $this->class ? $this->class . $this->type . $this->function : (string) $this->function;
    }

}
