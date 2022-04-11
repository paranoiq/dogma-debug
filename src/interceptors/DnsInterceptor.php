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
use function dns_get_mx;
use function dns_get_record;
use const DNS_ANY;

/**
 * Tracks calls to dns related functions
 */
class DnsInterceptor
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
        Intercept::registerFunction(self::NAME, 'gethostbyaddr', self::class);
        Intercept::registerFunction(self::NAME, 'gethostbyname', self::class);
        Intercept::registerFunction(self::NAME, 'gethostbynamel', self::class);
        Intercept::registerFunction(self::NAME, 'gethostname', self::class);
        Intercept::registerFunction(self::NAME, 'dns_check_record', self::class);
        Intercept::registerFunction(self::NAME, 'checkdnsrr', [self::class, 'dns_check_record']); // alias ^
        Intercept::registerFunction(self::NAME, 'dns_get_mx', self::class);
        Intercept::registerFunction(self::NAME, 'getmxrr', [self::class, 'dns_get_mx']); // alias ^
        Intercept::registerFunction(self::NAME, 'dns_get_record', self::class);
        self::$intercept = $level;
    }

    /**
     * @return string|false
     */
    public static function gethostbyaddr(string $ip)
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$ip], false);
    }

    public static function gethostbyname(string $hostname): string
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$hostname], $hostname);
    }

    /**
     * @return string[]|false
     */
    public static function gethostbynamel(string $hostname)
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$hostname], false);
    }

    /**
     * @return string|false
     */
    public static function gethostname()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], false);
    }

    public static function dns_check_record(string $hostname, string $type = 'MX'): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$hostname, $type], false);
    }

    /**
     * @param string[] $hosts
     * @param int[] $weights
     */
    public static function dns_get_mx(string $hostname, array &$hosts = [], array &$weights = []): bool
    {
        if (self::$intercept & Intercept::SILENT) {
            return dns_get_mx($hostname, $hosts, $weights);
        } elseif (self::$intercept & Intercept::LOG_CALLS) {
            $result = dns_get_mx($hostname, $hosts, $weights);
            Intercept::log(self::NAME, self::$intercept, __FUNCTION__, [$hostname, $hosts, $weights], $result);

            return $result;
        } elseif (self::$intercept & Intercept::PREVENT_CALLS) {
            Intercept::log(self::NAME, self::$intercept, __FUNCTION__, [$hostname, $hosts, $weights], false);

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
    public static function dns_get_record(
        string $hostname,
        int $type = DNS_ANY,
        array &$authoritative_name_servers = [],
        array &$additional_records = [],
        bool $raw = false
    )
    {
        if (self::$intercept & Intercept::SILENT) {
            return dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
        } elseif (self::$intercept & Intercept::LOG_CALLS) {
            $result = dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
            Intercept::log(self::NAME, self::$intercept, __FUNCTION__, [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], $result);

            return $result;
        } elseif (self::$intercept & Intercept::PREVENT_CALLS) {
            Intercept::log(self::NAME, self::$intercept, __FUNCTION__, [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

}
