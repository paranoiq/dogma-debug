<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use const PREG_OFFSET_CAPTURE;
use function array_values;
use function function_exists;
use function grapheme_strlen;
use function grapheme_substr;
use function iconv_strlen;
use function iconv_substr;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function str_replace;
use function strlen;
use function strncmp;
use function strpos;
use function substr;

class Str
{

    public static function length(string $string, string $encoding = 'utf-8'): int
    {
        if (!preg_match('##u', $string)) {
            // not utf-8
            return strlen($string);
        } elseif (function_exists('mb_strlen')) {
            return mb_strlen($string, $encoding);
        } elseif (function_exists('iconv_strlen')) {
            return iconv_strlen($string, $encoding);
        } elseif (function_exists('grapheme_strlen')) {
            return grapheme_strlen($string);
        } else {
            return strlen($string);
        }
    }

    public static function trim(string $string, int $length, string $encoding = 'utf-8'): string
    {
        if (!preg_match('##u', $string)) {
            // not utf-8
            return substr($string, 0, $length);
        } elseif (function_exists('mb_substr')) {
            return mb_substr($string, 0, $length, $encoding);
        } elseif (function_exists('iconv_substr')) {
            return iconv_substr($string, 0, $length, $encoding);
        } elseif (function_exists('grapheme_substr')) {
            return grapheme_substr($string, 0, $length);
        } else {
            return substr($string, 0, $length);
        }
    }

    public static function contains(string $string, string $find): bool
    {
        return strpos($string, $find) !== false;
    }

    public static function startsWith(string $string, string $find): bool
    {
        return strncmp($string, $find, strlen($find)) === 0;
    }

    public static function endsWith(string $string, string $find): bool
    {
        return $find === '' || substr($string, -strlen($find)) === $find;
    }

    /**
     * @param string[] $replacements
     */
    public static function replaceKeys(string $string, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    /**
     * @return int|false|null
     */
    public static function matchPos(string $string, string $pattern)
    {
        $result = preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE);
        if ($result === false) {
            return false;
        } elseif ($result === 0) {
            return null;
        }

        return $matches[0][1];
    }

}
