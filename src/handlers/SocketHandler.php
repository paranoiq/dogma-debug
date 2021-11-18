<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use CurlHandle;
use CurlMultiHandle;
use const CURLE_OK;
use const CURLM_OK;

/**
 * Tracks communication over socket functions
 */
class SocketHandler
{

    /** @var int */
    private static $takeover = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over socket functions
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverSockets(int $level): void
    {
        Takeover::register('socket', 'socket_create', [self::class, 'fakeCreate']);
        Takeover::register('socket', 'socket_set_option', [self::class, 'fakeSetOption']);
        Takeover::register('socket', 'socket_set_block', [self::class, 'fakeSetBlock']);
        Takeover::register('socket', 'socket_set_nonblock', [self::class, 'fakeSetNonblock']);
        Takeover::register('socket', 'socket_connect', [self::class, 'fakeConnect']);
        Takeover::register('socket', 'socket_select', [self::class, 'fakeSelect']);
        Takeover::register('socket', 'socket_write', [self::class, 'fakeWrite']);
        Takeover::register('socket', 'socket_recv', [self::class, 'fakeReceive']);
        Takeover::register('socket', 'socket_close', [self::class, 'fakeClose']);
        self::$takeover = $level;
    }

    /**
     * @return resource|CurlHandle|false
     */
    public static function fakeInit(?string $url = null)
    {
        return Takeover::handle('socket', self::$takeover, 'curl_init', [$url], false);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeClose($handle): void
    {
        Takeover::handle('curl', self::$takeover, 'curl_close', [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|bool
     */
    public static function fakeExec($handle)
    {
        return Takeover::handle('curl', self::$takeover, 'curl_exec', [$handle], false);
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|string[]
     */
    public static function fakeGetInfo($handle, ?int $option = null)
    {
        return Takeover::handle('curl', self::$takeover, 'curl_getinfo', [$handle, $option], $option ? '' : []);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakePause($handle, int $flags): int
    {
        return Takeover::handle('curl', self::$takeover, 'curl_pause', [$handle, $flags], CURLE_OK);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeReset($handle): void
    {
        Takeover::handle('curl', self::$takeover, 'curl_reset', [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed[] $options
     */
    public static function fakeSetoptArray($handle, array $options): bool
    {
        return Takeover::handle('curl', self::$takeover, 'curl_setopt_array', [$handle, $options], true);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed $value
     */
    public static function fakeSetopt($handle, int $option, $value): bool
    {
        return Takeover::handle('curl', self::$takeover, 'curl_setopt', [$handle, $option, $value], true);
    }

    // multi -----------------------------------------------------------------------------------------------------------

    /**
     * @return resource|CurlMultiHandle|false
     */
    public static function fakeMultiInit()
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_init', [], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiClose($multi_handle): void
    {
        Takeover::handle('curl', self::$takeover, 'curl_multi_close', [$multi_handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     */
    public static function fakeMultiAdd($multi_handle, $handle): int
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_add_handle', [$multi_handle, $handle], 0);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     * @return int|false
     */
    public static function fakeMultiRemove($multi_handle, $handle)
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_remove_handle', [$multi_handle, $handle], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiExec($multi_handle, int &$still_running): int
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_exec', [$multi_handle, &$still_running], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiSelect($multi_handle, float $timeout = 1.0): int
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_select', [$multi_handle, $timeout], -1);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeMultiContent($handle): ?string
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_getcontent', [$handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @return string[]|false
     */
    public static function fakeMultiInfo($multi_handle, &$queued_messages)
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_info_read', [$multi_handle, &$queued_messages], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param mixed $value
     */
    public static function fakeMultiSetopt($multi_handle, int $option, $value): bool
    {
        return Takeover::handle('curl', self::$takeover, 'curl_multi_setopt', [$multi_handle, $option, $value], true);
    }

}
