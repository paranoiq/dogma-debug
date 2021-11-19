<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use const DNS_ANY;
use function dns_get_mx;
use function dns_get_record;

/**
 * Tracks calls to dns related functions
 */
class DnsHandler
{

    public const NAME = 'dns';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over DNS related functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptDns(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'gethostbyaddr', [self::class, 'fakeGetHostByAddr']);
        Intercept::register(self::NAME, 'gethostbyname', [self::class, 'fakeGetHostByName']);
        Intercept::register(self::NAME, 'gethostbynamel', [self::class, 'fakeGetHostByNamel']);
        Intercept::register(self::NAME, 'gethostname', [self::class, 'fakeHostname']);
        Intercept::register(self::NAME, 'dns_check_record', [self::class, 'fakeCheck']);
        Intercept::register(self::NAME, 'checkdnsrr', [self::class, 'fakeCheck']); // alias ^
        Intercept::register(self::NAME, 'dns_get_mx', [self::class, 'fakeGetMx']);
        Intercept::register(self::NAME, 'getmxrr', [self::class, 'fakeGetMx']); // alias ^
        Intercept::register(self::NAME, 'dns_get_record', [self::class, 'fakeGetRecord']);
        self::$intercept = $level;
    }

    /**
     * @return string|false
     */
    public static function fakeGetHostByAddr(string $ip)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gethostbyaddr', [$ip], false);
    }

    public static function fakeGetHostByName(string $hostname): string
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gethostbyname', [$hostname], $hostname);
    }

    /**
     * @return string[]|false
     */
    public static function fakeGetHostByNamel(string $hostname)
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gethostbynamel', [$hostname], false);
    }

    /**
     * @return string|false
     */
    public static function fakeHostname()
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gethostname', [], false);
    }

    public static function fakeCheck(string $hostname, string $type = 'MX'): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'dns_check_record', [$hostname, $type], false);
    }

    /**
     * @param string[] $hosts
     * @param int[] $weights
     */
    public static function fakeGetMx(string $hostname, array &$hosts = [], array &$weights = []): bool
    {
        if (self::$intercept === Intercept::SILENT) {
            return dns_get_mx($hostname, $hosts, $weights);
        } elseif (self::$intercept === Intercept::LOG_CALLS) {
            $result = dns_get_mx($hostname, $hosts, $weights);
            Intercept::log(self::NAME, self::$intercept, 'dns_get_mx', [$hostname, $hosts, $weights], $result);

            return $result;
        } elseif (self::$intercept === Intercept::PREVENT_CALLS) {
            Intercept::log(self::NAME, self::$intercept, 'dns_get_mx', [$hostname, $hosts, $weights], false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed[] $authoritative_name_servers
     * @param mixed[] $additional_records
     * @return mixed[]|false
     */
    public static function fakeGetRecord(
        string $hostname,
        int $type = DNS_ANY,
        array &$authoritative_name_servers = [],
        array &$additional_records = [],
        bool $raw = false
    )
    {
        if (self::$intercept === Intercept::SILENT) {
            return dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
        } elseif (self::$intercept === Intercept::LOG_CALLS) {
            $result = dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
            Intercept::log(self::NAME, self::$intercept, 'dns_get_record', [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], $result);

            return $result;
        } elseif (self::$intercept === Intercept::PREVENT_CALLS) {
            Intercept::log(self::NAME, self::$intercept, 'dns_get_record', [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

}
