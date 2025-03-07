<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_diff;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function count;
use function dechex;
use function function_exists;
use function grapheme_strlen;
use function grapheme_substr;
use function iconv_strlen;
use function iconv_substr;
use function implode;
use function mb_strlen;
use function mb_substr;
use function ord;
use function preg_match;
use function range;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use const PREG_OFFSET_CAPTURE;

class Str
{

    public static function normalizeLineEndings(string $string): string
    {
        return str_replace(["\r\n", "\r"], ["\n", "\n"], $string);
    }

    /**
     * Calculates string length with best algo available:
     * - counts graphemes if `intl` extension is available
     * - counts characters if `mbstring` or `iconv` is available
     * - counts characters by parsing utf-8 if $slow is true and no extension is available
     * - fallbacks to bytes if $slow is false and no extension is available
     */
    public static function length(string $string, string $encoding = 'utf-8', bool $slow = false): int
    {
        // should normalize newlines - \r\n should be counted as single grapheme, but that would break consistency with ::substring()
        // https://stackoverflow.com/questions/18896428/strange-behavior-of-grapheme-strlen-function-with-some-line-endings
        // $string = str_replace("\r\n", "\n", $string);

        if ($encoding === 'utf-8' && function_exists('grapheme_strlen')) {
            $result = grapheme_strlen($string);
            // todo: check what causes this to return null
            // https://github.com/php/php-src/blob/d23111197240849324bde644a02efc8cd9492fbf/ext/intl/grapheme/grapheme_string.c#L55
            if ($result !== null) {
                return $result;
            }
        }
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, $encoding);
        } elseif (!preg_match('~~u', $string)) {
            // not utf-8
            return strlen($string);
        } elseif (function_exists('iconv_strlen')) {
            return iconv_strlen($string, $encoding);
        } elseif ($slow) {
            return self::uft8_strlen($string);
        } else {
            // todo: single time warning
            return strlen($string);
        }
    }

    public static function substring(string $string, int $start, ?int $length = null, string $encoding = 'utf-8'): string
    {
        if ($encoding === 'utf-8' && function_exists('grapheme_substr')) {
            return grapheme_substr($string, $start, $length);
        } elseif (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length, $encoding);
        } elseif (!preg_match('~~u', $string)) {
            // not utf-8
            return substr($string, $start, $length);
        } elseif (function_exists('iconv_substr')) {
            if ($length === null) {
                $length = self::length($string, $encoding);
            } elseif ($start < 0 && $length < 0) {
                $start += self::length($string, $encoding);
            }
            return iconv_substr($string, $start, $length, $encoding);
        } else {
            // todo: single time warning
            return substr($string, $start, $length);
        }
    }

    /**
     * @return string[]
     */
    public static function splitByLast(string $string, string $search): array
    {
        $pos = strrpos($string, $search);
        if ($pos === false) {
            return [$string, ''];
        }

        return [substr($string, 0, $pos), substr($string, $pos + 1)];
    }

    /**
     * @param string[] $replacements
     */
    public static function replaceKeys(string $string, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    /**
     * @param string[] $items
     */
    public static function join(array $items, string $separator = '', ?string $lastSeparator = null): string
    {
        if (count($items) === 0) {
            return '';
        } elseif (count($items) === 1) {
            return (string) array_pop($items);
        } elseif ($lastSeparator === null) {
            return implode($separator, $items);
        } else {
            $last = array_pop($items);

            return implode($separator, $items) . $lastSeparator . $last;
        }
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

    /**
     * @param string[] $allowedChars
     */
    public static function isBinary(string $string, array $allowedChars = ["\n", "\r", "\t"]): ?string
    {
        if ($allowedChars !== []) {
            $chars = array_diff(range("\x00", "\x1f"), $allowedChars);
            $pattern = self::createCharPattern($chars);
        } else {
            $pattern = '~[\x00-\x1f]~';
        }

        if (preg_match($pattern, $string, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    /**
     * @param string[] $chars
     */
    public static function createCharPattern(array $chars): string
    {
        $chars = array_map(static function (string $ch): string {
            return '\x' . self::charToHex($ch);
        }, $chars);

		if ($chars !== []) {
			return '~[' . implode('', $chars) . ']~';
		} else {
			return '~[\x00]~';
		}
    }

    /**
     * @return string[]
     */
    public static function chunksBin(string $string, int $length): array
    {
        $chunks = [];
        for ($start = 0; $start < strlen($string); $start += $length) {
            $chunks[] = substr($string, $start, $length);
        }

        return $chunks;
    }

    public static function strToHex(string $string): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            // chars
            if ($i !== 0) {
                $hex .= ' ';
            }
            // groups of 4 chars
            if ($i !== 0 && ($i % 4) === 0) {
                $hex .= ' ';
            }

            $hex .= self::charToHex($string[$i]);
        }

        return $hex;
    }

    public static function charToHex(string $char): string
    {
        $hex = dechex(ord($char));

        return (strlen($hex) === 1 ? '0' : '') . $hex;
    }

    public static function ord(string $ch): int
    {
        $ord0 = ord($ch[0]);
        if ($ord0 <= 127) {
            return $ord0;
        }
        $ord1 = ord($ch[1]);
        if ($ord0 >= 192 && $ord0 <= 223) {
            return ($ord0 - 192) * 64 + ($ord1 - 128);
        }
        $ord2 = ord($ch[2]);
        if ($ord0 >= 224 && $ord0 <= 239) {
            return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);
        }
        $ord3 = ord($ch[3]);
        if ($ord0 >= 240 && $ord0 <= 247) {
            return ($ord0 - 240) * 262144 + ($ord1 - 128) * 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);
        }
        $ord4 = ord($ch[4]);
        if ($ord0 >= 248 && $ord0 <= 251) {
            return ($ord0 - 248) * 16777216 + ($ord1 - 128) * 262144 + ($ord2 - 128) * 4096 + ($ord3 - 128) * 64 + ($ord4 - 128);
        }
        $ord5 = ord($ch[5]);
        if ($ord0 >= 252 && $ord0 <= 253) {
            return ($ord0 - 252) * 1073741824 + ($ord1 - 128) * 16777216 + ($ord2 - 128) * 262144 + ($ord3 - 128) * 4096 + ($ord4 - 128) * 64 + ($ord5 - 128);
        }

        return -1;
    }

    private static function uft8_strlen(string $string): int
    {
        $bytes = strlen($string);
        $subtract = 0;
        for ($n = 0; $n < $bytes; $n++) {
            $byte = $string[$n];
            // does not match single-byte codes and multibyte sequence start codes, matches multibyte-sequence continuation codes
            if ((ord($byte) & 0b11000000) === 0b10000000) {
                $subtract++;
            }
        }

        return $bytes - $subtract;
    }

}
