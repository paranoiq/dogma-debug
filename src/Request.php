<?php
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

    public const APPLICATION_SELF_TEST = 'self-test';
    public const APPLICATION_PHPSTAN = 'phpstan';
    public const APPLICATION_RECTOR = 'rector';
    public const APPLICATION_PHPUNIT = 'phpunit';
    public const APPLICATION_CODECEPTION = 'codeception';
    public const APPLICATION_NETTE_TESTER = 'nette-tester';
    public const APPLICATION_NETTE_TEST = 'nette-test';
    public const APPLICATION_COMPOSER = 'composer';
    public const APPLICATION_REQUIRE_CHECKER = 'require-checker';
    public const APPLICATION_PHPCS = 'phpcs';
    public const APPLICATION_PARALLEL_LINT = 'parallel-lint';
    public const APPLICATION_SPELL_CHECKER = 'spell-checker';
    public const APPLICATION_ADMINER = 'adminer';
    public const APPLICATION_ROUNDCUBE = 'roundcube';

    /** @var Sapi::* - Through which SAPI is PHP request initiated? ('cli', 'fpm-fcgi', 'apache'...) */
    public static $sapi;

    /** @var string|null - On which environment your project runs (custom name or auto-detected 'local', 'wsl', 'docker', etc.) */
    public static $environment;

    /** @var string|null - Name of your project (custom name or auto-detected from path. e.g. 'my-homepage'). used for editor links */
    public static $project;

    /** @var string|null - Name of application running (the project itself or a dev tool like 'phpstan', 'phpcs', etc.)*/
    public static $application;

    /** @var array<string, string> */
    public static $appCommandMatches = [
        '~dogma-debug/tests/.*\.phpt~' => self::APPLICATION_SELF_TEST,
        '~(?:phpstan/phpstan|vendor/bin)/phpstan~' => self::APPLICATION_PHPSTAN,
        '~phpstan (?:analyse|analyze|worker)~' => self::APPLICATION_PHPSTAN,
        '~vendor/bin/rector (?:process|worker)~' => self::APPLICATION_RECTOR,
        '~phpunit/phpunit/phpunit~' => self::APPLICATION_PHPUNIT,
        '~codeception/codeception/codecept~' => self::APPLICATION_CODECEPTION,
        '~nette/tester/src/tester~' => self::APPLICATION_NETTE_TESTER,
        '~nette/tester/src/Runner/info.php~' => self::APPLICATION_NETTE_TESTER,
        '~tests/.*\.phpt~' => self::APPLICATION_NETTE_TEST,
        '~composer[^/]*.phar~' => self::APPLICATION_COMPOSER,
        '~update --dry-run~' => self::APPLICATION_COMPOSER,
        '~validate --no-check-publish~' => self::APPLICATION_COMPOSER,
        '~show --format=json -a --name-only~' => self::APPLICATION_COMPOSER,
        '~composer-require-checker~' => self::APPLICATION_REQUIRE_CHECKER,
        '~squizlabs/php_codesniffer/bin/phpcs~' => self::APPLICATION_PHPCS,
        '~vendor/bin/phpcs~' => self::APPLICATION_PHPCS,
        '~php-parallel-lint/php-parallel-lint/parallel-lint~' => self::APPLICATION_PARALLEL_LINT,
        '~vendor/spell-checker/spell-checker~' => self::APPLICATION_SPELL_CHECKER,
    ];

    /** @var array<string, string> */
    public static $appUrlMatches = [
        '~/adminer/adminer~' => self::APPLICATION_ADMINER,
    ];

    /** @var array<string, string> */
    public static $appFileMatches = [
        '~/var/www/roundcube/~' => self::APPLICATION_ROUNDCUBE,
    ];

    public static function init(): void
    {
        self::autodetectApps();

        self::$sapi = Sapi::get();
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

            return "{$scheme}://{$host}{$uri}";
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
        foreach (self::$appCommandMatches as $pattern => $app) {
            if (self::commandMatches($pattern)) {
                self::$application = $app;
                return;
            }
        }
        foreach (self::$appUrlMatches as $pattern => $app) {
            if (self::urlMatches($pattern)) {
                self::$application = $app;
                return;
            }
        }
        foreach (self::$appFileMatches as $pattern => $app) {
            if (self::fileMatches($pattern)) {
                self::$application = $app;
                return;
            }
        }
    }

    public static function isProject(string $name): bool
    {
        return self::$project === $name;
    }

    /**
     * @param string[] $names
     */
    public static function isAnyProject(array $names): bool
    {
        return in_array(self::$project, $names, true);
    }

    public static function isApp(string $name): bool
    {
        return self::$application === $name;
    }

    /**
     * @param string[] $names
     */
    public static function isAnyApp(array $names): bool
    {
        return in_array(self::$application, $names, true);
    }

    public static function isOnEnv(string $environment): bool
    {
        return self::$environment === $environment;
    }

    /**
     * @param string[] $environments
     */
    public static function isOnAnyEnv(array $environments): bool
    {
        return in_array(self::$environment, $environments, true);
    }

}
