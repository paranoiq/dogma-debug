<?php

namespace Dogma\Debug;

use function str_replace;
use const PHP_SAPI;

class Sapi
{

    public const AOL_SERVER = 'aolserver';
    public const APACHE = 'apache';
    public const APACHE_2 = 'apache2'; // 'apache2handler' originally
    public const APACHE_2_FILTER = 'apache2filter';
    public const CAUDIUM = 'caudium';
    public const CGI = 'cgi'; // (until PHP 5.3)
    public const CGI_FCGI = 'cgi-fcgi';
    public const CLI = 'cli';
    public const CLI_SERVER = 'cli-server';
    public const CONTINUITY = 'continuity';
    public const EMBED = 'embed';
    public const FPM_FCGI = 'fpm-fcgi';
    public const ISAPI = 'isapi';
    public const LITESPEED = 'litespeed';
    public const MILTER = 'milter';
    public const NSAPI = 'nsapi';
    public const PHTTPD = 'phttpd';
    public const PI3WEB = 'pi3web';
    public const ROXEN = 'roxen';
    public const THTTPD = 'thttpd';
    public const TUX = 'tux';
    public const WEBJAMES = 'webjames';

    /**
     * @return self::*
     */
    static function get(): string
    {
        // @phpstan-ignore-next-line "... returns string"
        return str_replace('handler', '', PHP_SAPI);
    }

}
