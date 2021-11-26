<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Socket;

/**
 * Tracks communication over socket functions
 */
class SocketsHandler
{

    public const NAME = 'socket';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over socket functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSockets(int $level = Intercept::LOG_CALLS): void
    {
        /*Intercept::register(self::NAME, 'socket_create', [self::class, 'fakeCreate']);
        Intercept::register(self::NAME, 'socket_set_option', [self::class, 'fakeSetOption']);
        Intercept::register(self::NAME, 'socket_set_block', [self::class, 'fakeSetBlock']);
        Intercept::register(self::NAME, 'socket_set_nonblock', [self::class, 'fakeSetNonblock']);
        Intercept::register(self::NAME, 'socket_connect', [self::class, 'fakeConnect']);
        Intercept::register(self::NAME, 'socket_select', [self::class, 'fakeSelect']);
        Intercept::register(self::NAME, 'socket_write', [self::class, 'fakeWrite']);
        Intercept::register(self::NAME, 'socket_recv', [self::class, 'fakeReceive']);
        Intercept::register(self::NAME, 'socket_close', [self::class, 'fakeClose']);*/
        self::$intercept = $level;
    }

    /**
     * @return resource|Socket|false
     */
    public static function fakeCreate(int $domain, int $type, int $protocol)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'socket_create', [$domain, $type, $protocol], false);
    }

}
