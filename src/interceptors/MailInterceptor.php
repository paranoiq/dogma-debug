<?php
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
class MailInterceptor
{

    public const NAME = 'mail';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over mail()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptMail(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for mail function.");
        }

        Intercept::registerFunction(self::NAME, 'mail', self::class);
        self::$intercept = $level;
    }

    public static function mail(
        string $to,
        string $subject,
        string $message
        //string|mixed[] $additional_headers = [],
        //string $additional_params = ''
    ): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, func_get_args(), true);
    }

}
