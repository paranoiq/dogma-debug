<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use const PHP_OUTPUT_HANDLER_STDFLAGS;

class BuffersInterceptor
{
    public const NAME = 'buffers';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over output buffering
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAutoload(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'flush', self::class);
        Intercept::registerFunction(self::NAME, 'ob_clean', self::class);
        Intercept::registerFunction(self::NAME, 'ob_end_clean', self::class);
        Intercept::registerFunction(self::NAME, 'ob_end_flush', self::class);
        Intercept::registerFunction(self::NAME, 'ob_flush', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_contents', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_flush', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_length', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_level', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_status', self::class);
        //Intercept::registerFunction(self::NAME, 'ob_gz_handler', self::class);
        Intercept::registerFunction(self::NAME, 'ob_implicit_flush', self::class);
        Intercept::registerFunction(self::NAME, 'ob_list_handlers', self::class);
        Intercept::registerFunction(self::NAME, 'ob_start', self::class);
        Intercept::registerFunction(self::NAME, 'ob_get_flush', self::class);
        Intercept::registerFunction(self::NAME, 'output_add_rewrite_var', self::class);
        Intercept::registerFunction(self::NAME, 'output_reset_rewrite_vars', self::class);

        self::$intercept = $level;
    }

    public static function flush(): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], null);
    }

    public static function ob_clean(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

    public static function ob_end_clean(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

    public static function ob_end_flush(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

    public static function ob_flush(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

    /**
     * @return string|false
     */
    public static function ob_get_clean()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], '');
    }

    /**
     * @return string|false
     */
    public static function ob_get_contents()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], '');
    }

    /**
     * @return string|false
     */
    public static function ob_get_flush()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], '');
    }

    /**
     * @return int|false
     */
    public static function ob_get_length()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], 0);
    }

    public static function ob_get_level(): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], 0);
    }

    /**
     * @return array<string, string|int>
     */
    public static function ob_get_status(bool $full_status = false): array
    {
        $default = [
            'chunk_size' => 0,
            'size' => 40960,
            'block_size' => 10240,
            'buffer_size' => 16384,
            'buffer_used' => 0,
            'level' => 0,
            'type' => 1,
            'status' => 0,
            'name' => 'default output handler',
            'del' => 1,
        ];

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$full_status], $full_status ? [$default] : $default);
    }

    /**
     * @param int|bool $enable
     */
    public static function ob_implicit_flush($enable): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$enable], null);
    }

    public static function ob_start(?callable $callback = null, int $chunk_size = 0, int $flags = PHP_OUTPUT_HANDLER_STDFLAGS): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$callback, $chunk_size, $flags], true);
    }

    public static function output_add_rewrite_var(string $name, string $value): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$name, $value], true);
    }

    public static function output_reset_rewrite_vars(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

}
