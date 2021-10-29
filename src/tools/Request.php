<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.PHP.GlobalKeyword.NotAllowed
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

namespace Dogma\Debug;

use function implode;
use function preg_match;

class Request
{

    public static function urlMatches(string $pattern): bool
    {
        if (!isset($_SERVER['SCRIPT_URL'])) {
            return false;
        }

        return (bool) preg_match($pattern, $_SERVER['SCRIPT_URL']);
    }

    public static function fileMatches(string $pattern): bool
    {
        return (bool) preg_match($pattern, $_SERVER['SCRIPT_FILENAME']);
    }

    public static function commandMatches(string $pattern): bool
    {
        return (bool) preg_match($pattern, self::getCommandLine());
    }

    public static function getCommandLine(): string
    {
        global $argv;

        return implode(' ', $argv ?? []);
    }

}
