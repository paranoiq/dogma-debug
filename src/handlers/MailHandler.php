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

/**
 * Tracks calls to mail() function
 */
class MailHandler
{

    /** @var int */
    private static $takeover = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over mail()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverMail(int $level): void
    {
        Takeover::register('mail', 'mail', [self::class, 'fakeMail']);
        self::$takeover = $level;
    }

    public static function fakeMail(
        string $to,
        string $subject,
        string $message
        //string|mixed[] $additional_headers = [],
        //string $additional_params = ''
    ): bool
    {
        return Takeover::handle('mail', self::$takeover, 'openlog', func_get_args(), true);
    }

}
