<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function func_get_args;
use function session_cache_expire;
use function session_cache_limiter;
use function session_id;
use function session_module_name;
use function session_name;
use function session_save_path;

/**
 * Tracks PHP sessions and session handlers
 */
class SessionHandler
{

    public const NAME = 'session';

    /** @var bool Terminate session before connection to debug server is closed. Otherwise, session writes might not be logged properly */
    public static $terminateSessionInShutdownHandler = true;

    /** @var int */
    private static $interceptSessions = Intercept::NONE;

    /** @var int */
    private static $interceptHandlers = Intercept::NONE;

    /**
     * Take control over session functions (except handlers and read only functions)
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSessions(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'session_abort', [self::class, 'fakeAbort']);
        Intercept::register(self::NAME, 'session_cache_expire', [self::class, 'fakeCacheExpire']);
        Intercept::register(self::NAME, 'session_cache_limiter', [self::class, 'fakeCacheLimiter']);
        //Intercept::register(self::NAME, 'session_create_id', [self::class, 'fakeCreateId']);
        //Intercept::register(self::NAME, 'session_decode', [self::class, 'fakeDecode']);
        Intercept::register(self::NAME, 'session_destroy', [self::class, 'fakeDestroy']);
        //Intercept::register(self::NAME, 'session_encode', [self::class, 'fakeEncode']);
        Intercept::register(self::NAME, 'session_gc', [self::class, 'fakeGc']);
        //Intercept::register(self::NAME, 'session_get_cookie_params', [self::class, 'fakeGetCookieParams']);
        Intercept::register(self::NAME, 'session_id', [self::class, 'fakeId']);
        Intercept::register(self::NAME, 'session_module_name', [self::class, 'fakeModuleName']);
        Intercept::register(self::NAME, 'session_name', [self::class, 'fakeName']);
        Intercept::register(self::NAME, 'session_regenerate_id', [self::class, 'fakeRegenerateId']);
        Intercept::register(self::NAME, 'session_reset', [self::class, 'fakeReset']);
        Intercept::register(self::NAME, 'session_save_path', [self::class, 'fakeSavePath']);
        Intercept::register(self::NAME, 'session_set_cookie_params', [self::class, 'fakeSetCookieParams']);
        Intercept::register(self::NAME, 'session_start', [self::class, 'fakeStart']);
        //Intercept::register(self::NAME, 'session_status', [self::class, 'fakeStatus']);
        Intercept::register(self::NAME, 'session_unset', [self::class, 'fakeUnset']);
        Intercept::register(self::NAME, 'session_write_close', [self::class, 'fakeWriteClose']);
        Intercept::register(self::NAME, 'session_commit', [self::class, 'fakeWriteClose']); // alias ^
        self::$interceptSessions = $level;
    }

    /**
     * Take control over session_register_shutdown() and session_set_save_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptHandler(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'session_register_shutdown', [self::class, 'fakeShutdown']);
        Intercept::register(self::NAME, 'session_set_save_handler', [self::class, 'fakeHandler']);
        self::$interceptHandlers = $level;
    }

    public static function fakeAbort(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_abort', [], true);
    }

    /**
     * @return int|false
     */
    public static function fakeCacheExpire(?int $value = null)
    {
        $expire = session_cache_expire();
        if ($value === null) {
            return $expire;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_cache_expire', [$value], $expire);
    }

    /**
     * @return string|false
     */
    public static function fakeCacheLimiter(?string $value = null)
    {
        $limiter = session_cache_limiter();
        if ($value === null) {
            return $limiter;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_cache_limiter', [$value], $limiter);
    }

    public static function fakeDestroy(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_destroy', [], true);
    }

    /**
     * @return int|false
     */
    public static function fakeGc()
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_gc', [], 0);
    }

    /**
     * @return string|false
     */
    public static function fakeId(?string $id = null)
    {
        $res = session_id();
        if ($id === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_id', [$id], $res);
    }

    /**
     * @return string|false
     */
    public static function fakeModuleName(?string $module)
    {
        $res = session_module_name();
        if ($module === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_module_name', [$module], $res);
    }

    /**
     * @return string|false
     */
    public static function fakeName(?string $name)
    {
        $res = session_name();
        if ($name === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_name', [$name], $res);
    }

    public static function fakeRegenerateId(bool $delete_old_session = false): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_regenerate_id', [$delete_old_session], true);
    }

    public static function fakeReset(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_reset', [], true);
    }

    /**
     * @return string|false
     */
    public static function fakeSavePath(?string $path = null)
    {
        $res = session_save_path();
        if ($path === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_save_path', [$path], $res);
    }

    public static function fakeSetCookieParams(): ?bool
    {
        // old: array|int $lifetime_or_options, ?string $path, ?string $domain, ?bool $secure = false, ?bool $httponly = false
        // new: array $options

        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_set_cookie_params', func_get_args(), true);
    }

    /**
     * @param mixed[] $options
     */
    public static function fakeStart(array $options = []): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_start', [$options], true);
    }

    public static function fakeUnset(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_unset', [], true);
    }

    public static function fakeWriteClose(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, 'session_write_close', [], true);
    }

    public static function fakeShutdown(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, 'session_register_shutdown', [], true);
    }

    public static function fakeHandler(): bool
    {
        // old: callable $open, callable $close, callable $read, callable $write, callable $destroy, callable $gc, $create_sid, $validate_sid, $update_timestamp
        // new: SessionHandlerInterface $session_handler, $register_shutdown = true

        return Intercept::handle(self::NAME, self::$interceptHandlers, 'session_set_save_handler', func_get_args(), true);
    }

}
