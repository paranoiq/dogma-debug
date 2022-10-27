<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

if (!function_exists('str_contains')) {

    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

}

if (!function_exists('str_starts_with')) {

    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

}

if (!function_exists('str_ends_with')) {

    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '' || $needle === $haystack) {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        $needleLength = strlen($needle);

        return $needleLength <= strlen($haystack) && substr_compare($haystack, $needle, -$needleLength) === 0;
    }

}
