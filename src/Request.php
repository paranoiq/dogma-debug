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
use function in_array;
use function preg_match;
use function str_replace;
use const PHP_SAPI;

/**
 * Helpers for detecting which application and in which environment is running
 * Useful when you want to change behavior of debugger (or disable it entirely) from app to app in your bootstrap file
 * You can run Request::autodetect() or simply assign to $application and $environment variables
 */
class Request
{

    /** @var string */
    public static $sapi;

    /** @var string|null */
    public static $application;

    /** @var string|null */
    public static $environment;

    public static function init(): void
    {
        self::$sapi = str_replace('handler', '', PHP_SAPI);
    }

    // index -----------------------------------------------------------------------------------------------------------

    public static function getFile(): ?string
    {
        if (!isset($_SERVER['SCRIPT_FILENAME'])) {
            return null;
        }

        return str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
    }

    public static function fileMatches(string $pattern): bool
    {
        $file = self::getFile();
        if ($file === null) {
            return false;
        }

        return (bool) preg_match($pattern, $file);
    }

    /**
     * @param string[] $patterns
     */
    public static function fileMatchesAny(array $patterns): bool
    {
        $file = self::getFile();
        if ($file === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    // cli -------------------------------------------------------------------------------------------------------------

    public static function isCli(): bool
    {
        return self::$sapi === 'cli';
    }

    public static function getCommand(): string
    {
        global $argv;

        $args = $argv;
        if (isset($args[0])) {
            $args[0] = str_replace('\\', '/', $args[0]);
        }

        return implode(' ', $args ?? []);
    }

    public static function commandMatches(string $pattern): bool
    {
        return (bool) preg_match($pattern, self::getCommand());
    }

    /**
     * @param string[] $patterns
     */
    public static function commandMatchesAny(array $patterns): bool
    {
        $cl = self::getCommand();

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cl)) {
                return true;
            }
        }

        return false;
    }

    // http ------------------------------------------------------------------------------------------------------------

    public static function isHttp(?string $method = null): bool
    {
        if (self::$sapi === 'cli') {
            return false;
        }
        if (isset($method, $_SERVER['REQUEST_METHOD'])) {
            return $_SERVER['REQUEST_METHOD'] === $method;
        }

        return true;
    }

    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    public static function getMethod(): ?string
    {
        return $_SERVER['REQUEST_METHOD'] ?? null;
    }

    public static function getUrl(): ?string
    {
        if (!empty($_SERVER['SCRIPT_URI'])) {
            return $_SERVER['SCRIPT_URI'];
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
            $host = $_SERVER['HTTP_HOST'];
            $uri = $_SERVER['REQUEST_URI'] ?? '';

            return "$scheme://$host$uri";
        } else {
            return null;
        }
    }

    public static function urlMatches(string $pattern): bool
    {
        $url = self::getUrl();
        if ($url === null) {
            return false;
        }

        return (bool) preg_match($pattern, $url);
    }

    /**
     * @param string[] $patterns
     */
    public static function urlMatchesAny(array $patterns): bool
    {
        $url = self::getUrl();
        if ($url === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $_SERVER['SCRIPT_URL'])) {
                return true;
            }
        }

        return false;
    }

    // apps and environments -------------------------------------------------------------------------------------------

    /**
     * Detects some of common tools and apps
     */
    public static function autodetectApps(): void
    {
        if (self::commandMatches('~dogma-debug/tests/.*\.phpt~')) {
            self::$application = 'self-tests';
        } elseif (self::commandMatches('~/phpstan/phpstan/phpstan~')) {
            self::$application = 'phpstan';
        } elseif (self::commandMatches('~tests/.*\.phpt~')) {
            self::$application = 'nette-tests';
        } elseif (self::commandMatchesAny(['~composer[^/]*.phar~', '~update --dry-run~', '~validate --no-check-publish~', '~show --format=json -a --name-only~'])) {
            self::$application = 'composer';
        } elseif (self::commandMatches('~composer-require-checker~')) {
            self::$application = 'require-checker';
        } elseif (self::commandMatches('~/squizlabs/php_codesniffer/bin/phpcs~')) {
            self::$application = 'phpcs';
        } elseif (self::commandMatches('~/php-parallel-lint/parallel-lint~')) {
            self::$application = 'phplint';
        } elseif (self::commandMatches('~/codeception/codeception/codecept~')) {
            self::$application = 'codeception';
        } elseif (self::urlMatches('~/adminer/adminer~')) {
            self::$application = 'adminer';
        } elseif (self::fileMatches('~/var/www/roundcube/~')) {
            self::$application = 'roundcube';
        }
    }

    public static function is(string $name): bool
    {
        return self::$application === $name;
    }

    /**
     * @param string[] $names
     */
    public static function isAny(array $names): bool
    {
        return in_array(self::$application, $names, true);
    }

    public static function isOn(string $environment): bool
    {
        return self::$environment === $environment;
    }

    /**
     * @param string[] $environments
     */
    public static function isOnAny(array $environments): bool
    {
        return in_array(self::$environment, $environments, true);
    }

}
