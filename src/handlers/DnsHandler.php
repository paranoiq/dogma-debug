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

/**
 * Tracks calls to dns related functions
 */
class DnsHandler
{

    /** @var int */
    private static $takeover = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over checkdnsrr(), dns_check_record(), dns_get_mx(), dns_get_record(), gethostbyaddr(), gethostbyname(), gethostbynamel(), gethostname(), getmxrr()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverDns(int $level): void
    {
        Takeover::register('dns', 'gethostbyaddr', [self::class, 'fakeGetHostByAddr']);
        Takeover::register('dns', 'gethostbyname', [self::class, 'fakeGetHostByName']);
        Takeover::register('dns', 'gethostbynamel', [self::class, 'fakeGetHostByNamel']);
        Takeover::register('dns', 'gethostname', [self::class, 'fakeHostname']);
        Takeover::register('dns', 'dns_check_record', [self::class, 'fakeCheck']);
        Takeover::register('dns', 'checkdnsrr', [self::class, 'fakeCheck']); // alias ^
        Takeover::register('dns', 'dns_get_mx', [self::class, 'fakeGetMx']);
        Takeover::register('dns', 'getmxrr', [self::class, 'fakeGetMx']); // alias ^
        Takeover::register('dns', 'dns_get_record', [self::class, 'fakeGetRecord']);
        self::$takeover = $level;
    }

    /**
     * @return string|false
     */
    public static function fakeGetHostByAddr(string $ip)
    {
        return Takeover::handle('dns', self::$takeover, 'gethostbyaddr', [$ip], false);
    }

    public static function fakeGetHostByName(string $hostname): string
    {
        return Takeover::handle('dns', self::$takeover, 'gethostbyname', [$hostname], $hostname);
    }

    /**
     * @return string[]|false
     */
    public static function fakeGetHostByNamel(string $hostname)
    {
        return Takeover::handle('dns', self::$takeover, 'gethostbynamel', [$hostname], false);
    }

    /**
     * @return string|false
     */
    public static function fakeHostname()
    {
        return Takeover::handle('dns', self::$takeover, 'gethostname', [], false);
    }

    public static function fakeCheck(string $hostname, string $type = 'MX'): bool
    {
        return Takeover::handle('dns', self::$takeover, 'dns_check_record', [$hostname, $type], false);
    }

    public static function fakeGetMx(string $hostname, &$hosts = [], &$weights = []): bool
    {
        if (self::$takeover === Takeover::NONE) {
            return dns_get_mx($hostname, $hosts, $weights);
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $result = dns_get_mx($hostname, $hosts, $weights);
            Takeover::log('dns', self::$takeover, 'dns_get_mx', [$hostname, $hosts, $weights], $result);

            return $result;
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            Takeover::log('dns', self::$takeover, 'dns_get_mx', [$hostname, $hosts, $weights], false);

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
        &$authoritative_name_servers = [],
        &$additional_records = [],
        bool $raw = false
    )
    {
        if (self::$takeover === Takeover::NONE) {
            return dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $result = dns_get_record($hostname, $type, $authoritative_name_servers, $additional_records, $raw);
            Takeover::log('dns', self::$takeover, 'dns_get_record', [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], $result);

            return $result;
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            Takeover::log('dns', self::$takeover, 'dns_get_record', [$hostname, $type, $authoritative_name_servers, $additional_records, $raw], false);

            return false;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

}
