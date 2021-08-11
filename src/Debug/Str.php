<?php declare(strict_types = 1);

namespace Dogma\Debug;

use function function_exists;
use function grapheme_strlen;
use function grapheme_substr;
use function iconv_strlen;
use function iconv_substr;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function strlen;
use function substr;

class Str
{

    public static function length(string $string, $encoding = 'utf-8'): int
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

}
