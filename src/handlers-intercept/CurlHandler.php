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
 * Tracks HTTP requests via Curl
 */
class CurlHandler
{

    public const NAME = 'curl';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over majority of curl_*() functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptCurl(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'curl_init', [self::class, 'fakeInit']);
        Intercept::register(self::NAME, 'curl_close', [self::class, 'fakeClose']);
        Intercept::register(self::NAME, 'curl_exec', [self::class, 'fakeExec']);
        Intercept::register(self::NAME, 'curl_getinfo', [self::class, 'fakeInfo']);
        Intercept::register(self::NAME, 'curl_pause', [self::class, 'fakePause']);
        Intercept::register(self::NAME, 'curl_reset', [self::class, 'fakeReset']);
        Intercept::register(self::NAME, 'curl_setopt_array', [self::class, 'fakeSetoptArray']);
        Intercept::register(self::NAME, 'curl_setopt', [self::class, 'fakeSetopt']);

        Intercept::register(self::NAME, 'curl_multi_init', [self::class, 'fakeMultiInit']);
        Intercept::register(self::NAME, 'curl_multi_close', [self::class, 'fakeMultiClose']);
        Intercept::register(self::NAME, 'curl_multi_add_handle', [self::class, 'fakeMultiAdd']);
        Intercept::register(self::NAME, 'curl_multi_remove_handle', [self::class, 'fakeMultiRemove']);
        Intercept::register(self::NAME, 'curl_multi_exec', [self::class, 'fakeMultiExec']);
        Intercept::register(self::NAME, 'curl_multi_select', [self::class, 'fakeMultiSelect']);
        Intercept::register(self::NAME, 'curl_multi_getcontent', [self::class, 'fakeMultiContent']);
        Intercept::register(self::NAME, 'curl_multi_info_read', [self::class, 'fakeMultiInfo']);
        Intercept::register(self::NAME, 'curl_multi_setopt', [self::class, 'fakeMultiSetopt']);

        self::$intercept = $level;
    }

    /**
     * @return resource|CurlHandle|false
     */
    public static function fakeInit(?string $url = null)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_init', [$url], false);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeClose($handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, 'curl_close', [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|bool
     */
    public static function fakeExec($handle)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_exec', [$handle], false);
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|string[]
     */
    public static function fakeGetInfo($handle, ?int $option = null)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_getinfo', [$handle, $option], $option ? '' : []);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakePause($handle, int $flags): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_pause', [$handle, $flags], CURLE_OK);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeReset($handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, 'curl_reset', [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed[] $options
     */
    public static function fakeSetoptArray($handle, array $options): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_setopt_array', [$handle, $options], true);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed $value
     */
    public static function fakeSetopt($handle, int $option, $value): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_setopt', [$handle, $option, $value], true);
    }

    // multi -----------------------------------------------------------------------------------------------------------

    /**
     * @return resource|CurlMultiHandle|false
     */
    public static function fakeMultiInit()
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_init', [], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiClose($multi_handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, 'curl_multi_close', [$multi_handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     */
    public static function fakeMultiAdd($multi_handle, $handle): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_add_handle', [$multi_handle, $handle], 0);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     * @return int|false
     */
    public static function fakeMultiRemove($multi_handle, $handle)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_remove_handle', [$multi_handle, $handle], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiExec($multi_handle, int &$still_running): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_exec', [$multi_handle, &$still_running], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function fakeMultiSelect($multi_handle, float $timeout = 1.0): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_select', [$multi_handle, $timeout], -1);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function fakeMultiContent($handle): ?string
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_getcontent', [$handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @return string[]|false
     */
    public static function fakeMultiInfo($multi_handle, int &$queued_messages = 0)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_info_read', [$multi_handle, &$queued_messages], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param mixed $value
     */
    public static function fakeMultiSetopt($multi_handle, int $option, $value): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'curl_multi_setopt', [$multi_handle, $option, $value], true);
    }

}
