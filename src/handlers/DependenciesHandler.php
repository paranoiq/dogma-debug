<?php

namespace Dogma\Debug;

use function dirname;
use function file_exists;
use function file_get_contents;
use function implode;
use function json_decode;
use function str_ends_with;
use function str_replace;

class DependenciesHandler
{

    /** @var list<string> - names of packages to be reported in intro */
    public static $reportPackageVersions = [];

    /** @var mixed[] */
    private static $composerData;

    public static function getPackagesInfo(): string
    {
        $packages = [];
        foreach (self::$reportPackageVersions as $name) {
            $version = self::getPackageVersion($name);
            if ($version !== null) {
                $packages[] = $name . ' ' . $version;
            }
        }
        if ($packages !== []) {
            return '| ' . implode(' | ', $packages) . ' ';
        } else {
            return '';
        }
    }

    public static function getPackageVersion(string $name): ?string
    {
        if (self::$composerData === null) {
            self::loadDependencies();
        }

        if (isset(self::$composerData['packages'][$name])) {
            return self::$composerData['packages'][$name]['version'];
        }
        if (isset(self::$composerData['packages-dev'][$name])) {
            return self::$composerData['packages-dev'][$name]['version'];
        }

        return null;
    }

    private static function loadDependencies(): void
    {
        $path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
        do {
            if (str_ends_with($path, 'vendor/phpunit/phpunit')) {
                // skip when running from tests
            } elseif (file_exists($path . '/composer.lock')) {
                // todo: of course .lock can lie if composer install has not been run yet
                try {
                    self::$composerData = json_decode(file_get_contents($path . '/composer.lock'), true);
                } catch (\Throwable $e) {
                    self::$composerData = [];
                    return;
                }
                break;
            }
            if ($path === dirname($path)) {
                return;
            }
            $path = dirname($path);
        } while ($path !== '/' && !str_ends_with($path, ':'));

        if (isset(self::$composerData['packages'])) {
            $packages = [];
            foreach (self::$composerData['packages'] as $package) {
                $packages[$package['name']] = $package;
            }
            self::$composerData['packages'] = $packages;
        }

        if (isset(self::$composerData['packages-dev'])) {
            $packages = [];
            foreach (self::$composerData['packages-dev'] as $package) {
                $packages[$package['name']] = $package;
            }
            self::$composerData['packages-dev'] = $packages;
        }
    }

}
