<?php

namespace Dogma\Debug;

use ReflectionClass;
use ReflectionFunction;
use function ltrim;
use function str_replace;
use function strtolower;

/**
 * "Hyperlinks in Terminal Emulators"
 * https://gist.github.com/egmontkob/eb114294efbcd5adb1944c9f3cb5feda
 */
class Links
{

    /** @var bool - Add editor links to backtraces */
    public static $linksInBacktraces = false;

    /** @var bool - Add editor links to custom classes, properties etc. in dumps */
    public static $linksToProjectSymbols = false;

    /** @var bool - Add editor links to vendor classes, properties etc. in dumps */
    public static $linksToVendorSymbols = false;

    /** @var bool - Add http links to documentation of native PHP classes, properties etc. in dumps */
    public static $linksToPhpDoc = false;

    /** @var array<string, string> - Editor links configuration */
    public static $editorLinksByProject = [
        '*' => 'file://%filePath%', // fallback
    ];

    /** @var array<string, array{string, string}> ($env => ($project => ($reFrom => $to))) - File path translations in between environments */
    //public static $pathTranslations = [];

    public static function link(string $string, string $url): string
    {
        return "\e]8;;{$url}\e\\{$string}\e]8;;\e\\";
    }

    public static function editorLink(string $string, string $file, string $line): string
    {
        // todo
        return $string;
    }

    public static function class(string $string, string $name): string
    {
        //return $string;
        // todo
        $ref = new ReflectionClass($name);
        $userDefined = $ref->isUserDefined();
        if ($userDefined && (self::$linksToProjectSymbols || self::$linksToVendorSymbols)) {
            $file = $ref->getFileName();
            $line = $ref->getStartLine();
            if ($file !== false && $line !== false) {
                return self::editorLink($string, $file, $line);
            }
        } elseif (!$userDefined && self::$linksToPhpDoc) {
            $name = strtolower(str_replace('\\', '-', ltrim($name, '\\')));
            $url = "https://www.php.net/manual/en/class.{$name}.php";

            return self::link($string, $url);
        }

        return $string;
    }

    public static function function(string $string, string $name): string
    {
        //return $string;
        // todo
        $ref = new ReflectionFunction($name);
        $userDefined = $ref->isUserDefined();
        if ($userDefined && (self::$linksToProjectSymbols || self::$linksToVendorSymbols)) {
            $file = $ref->getFileName();
            $line = $ref->getStartLine();
            if ($file !== false && $line !== false) {
                return self::editorLink($string, $file, $line);
            }
        } elseif (!$userDefined && self::$linksToPhpDoc) {
            $name = strtolower(str_replace(['\\', '_'], '-', ltrim($name, '\\')));
            $url = "https://www.php.net/manual/en/function.{$name}.php";

            return self::link($string, $url);
        }

        return $string;
    }

}
