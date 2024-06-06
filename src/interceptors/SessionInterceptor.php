<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use SessionHandlerInterface;
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
class SessionInterceptor
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
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for session related functions.");
        }

        Intercept::registerFunction(self::NAME, 'session_abort', self::class);
        Intercept::registerFunction(self::NAME, 'session_cache_expire', self::class);
        Intercept::registerFunction(self::NAME, 'session_cache_limiter', self::class);
        //Intercept::register(self::NAME, 'session_create_id', self::class);
        //Intercept::register(self::NAME, 'session_decode', self::class);
        Intercept::registerFunction(self::NAME, 'session_destroy', self::class);
        //Intercept::register(self::NAME, 'session_encode', self::class);
        Intercept::registerFunction(self::NAME, 'session_gc', self::class);
        //Intercept::register(self::NAME, 'session_get_cookie_params', self::class);
        Intercept::registerFunction(self::NAME, 'session_id', self::class);
        Intercept::registerFunction(self::NAME, 'session_module_name', self::class);
        Intercept::registerFunction(self::NAME, 'session_name', self::class);
        Intercept::registerFunction(self::NAME, 'session_regenerate_id', self::class);
        Intercept::registerFunction(self::NAME, 'session_reset', self::class);
        Intercept::registerFunction(self::NAME, 'session_save_path', self::class);
        Intercept::registerFunction(self::NAME, 'session_set_cookie_params', self::class);
        Intercept::registerFunction(self::NAME, 'session_start', self::class);
        //Intercept::register(self::NAME, 'session_status', self::class);
        Intercept::registerFunction(self::NAME, 'session_unset', self::class);
        Intercept::registerFunction(self::NAME, 'session_write_close', self::class);
        Intercept::registerFunction(self::NAME, 'session_commit', [self::class, 'session_write_close']); // alias ^
        self::$interceptSessions = $level;
    }

    /**
     * Take control over session_register_shutdown() and session_set_save_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptHandler(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'session_register_shutdown', self::class);
        Intercept::registerFunction(self::NAME, 'session_set_save_handler', self::class);
        self::$interceptHandlers = $level;
    }

    public static function session_abort(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], true);
    }

    /**
     * @return int|false
     */
    public static function session_cache_expire(?int $value = null)
    {
        $expire = session_cache_expire();
        if ($value === null) {
            return $expire;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$value], $expire);
    }

    /**
     * @return string|false
     */
    public static function session_cache_limiter(?string $value = null)
    {
        $limiter = session_cache_limiter();
        if ($value === null) {
            return $limiter;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$value], $limiter);
    }

    public static function session_destroy(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], true);
    }

    /**
     * @return int|false
     */
    public static function session_gc()
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], 0);
    }

    /**
     * @return string|false
     */
    public static function session_id(?string $id = null)
    {
        $res = session_id();
        if ($id === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$id], $res);
    }

    /**
     * @return string|false
     */
    public static function session_module_name(?string $module)
    {
        $res = session_module_name();
        if ($module === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$module], $res);
    }

    /**
     * @return string|false
     */
    public static function session_name(?string $name)
    {
        $res = session_name();
        if ($name === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$name], $res);
    }

    public static function session_regenerate_id(bool $delete_old_session = false): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$delete_old_session], true);
    }

    public static function session_reset(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], true);
    }

    /**
     * @return string|false
     */
    public static function session_save_path(?string $path = null)
    {
        $res = session_save_path();
        if ($path === null) {
            return $res;
        }

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$path], $res);
    }

    public static function session_set_cookie_params(): ?bool
    {
        // old: array|int $lifetime_or_options, ?string $path, ?string $domain, ?bool $secure = false, ?bool $httponly = false
        // new: array $options

        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, func_get_args(), true);
    }

    /**
     * @param mixed[] $options
     */
    public static function session_start(array $options = []): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [$options], true);
    }

    public static function session_unset(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], true);
    }

    public static function session_write_close(): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptSessions, __FUNCTION__, [], true);
    }

    public static function session_register_shutdown(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, __FUNCTION__, [], true);
    }

    public static function session_set_save_handler(): bool
    {
        $args = func_get_args();
        if (Intercept::$wrapEventHandlers & Intercept::EVENT_SESSION) {
            if (count($args) <= 5) {
                // new: SessionHandlerInterface $session_handler, $register_shutdown = true
                $callstack = Callstack::get();
                $args[0] = new class($args[0], $callstack) implements SessionHandlerInterface
                {
                    /** @var SessionHandlerInterface */
                    private $previous;

                    /** @var Callstack */
                    private $callstack;

                    public function __construct(SessionHandlerInterface $previous, Callstack $callstack)
                    {
                        $this->previous = $previous;
                        $this->callstack = $callstack;
                    }

                    public function open($path, $name)
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'open');
                        $result = $this->previous->open($path, $name);
                        Intercept::eventEnd($this->callstack, Intercept::EVENT_SESSION, 'open');

                        return $result;
                    }

                    public function close()
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'close');
                        $result = $this->previous->close();
                        Intercept::eventEnd($this->callstack, Intercept::EVENT_SESSION, 'close');

                        return $result;
                    }

                    public function read($id)
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'read');
                        $result = $this->previous->read($id);
                        Intercept::eventEnd($this->callstack, Intercept::EVENT_SESSION, 'read');

                        return $result;
                    }

                    public function write($id, $data)
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'write');
                        $result = $this->previous->write($id, $data);
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'write');

                        return $result;
                    }

                    public function destroy($id)
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'destroy');
                        $result = $this->previous->destroy($id);
                        Intercept::eventEnd($this->callstack, Intercept::EVENT_SESSION, 'destroy');

                        return $result;
                    }

                    public function gc($max_lifetime)
                    {
                        Intercept::eventStart($this->callstack, Intercept::EVENT_SESSION, 'gc');
                        $result = $this->previous->gc($max_lifetime);
                        Intercept::eventEnd($this->callstack, Intercept::EVENT_SESSION, 'gc');

                        return $result;
                    }
                };
            } else {
                // old: callable $open, callable $close, callable $read, callable $write, callable $destroy, callable $gc, $create_sid, $validate_sid, $update_timestamp
                $args[0] = Intercept::wrapEventHandler($args[0], Intercept::EVENT_SESSION, 'open');
                $args[1] = Intercept::wrapEventHandler($args[1], Intercept::EVENT_SESSION, 'close');
                $args[2] = Intercept::wrapEventHandler($args[2], Intercept::EVENT_SESSION, 'read');
                $args[3] = Intercept::wrapEventHandler($args[3], Intercept::EVENT_SESSION, 'write');
                $args[4] = Intercept::wrapEventHandler($args[4], Intercept::EVENT_SESSION, 'destroy');
                $args[5] = Intercept::wrapEventHandler($args[5], Intercept::EVENT_SESSION, 'gc');
                if (isset($args[6])) {
                    $args[6] = Intercept::wrapEventHandler($args[6], Intercept::EVENT_SESSION, 'create_sid');
                }
                if (isset($args[7])) {
                    $args[7] = Intercept::wrapEventHandler($args[7], Intercept::EVENT_SESSION, 'validate_sid');
                }
                if (isset($args[8])) {
                    $args[8] = Intercept::wrapEventHandler($args[8], Intercept::EVENT_SESSION, 'update_timestamp');
                }
            }
        }

        return Intercept::handle(self::NAME, self::$interceptHandlers, __FUNCTION__, $args, true);
    }

}
