<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

/**
 * Displays request/response headers, body etc.
 */
class RequestHandler
{

    public const NAME = 'request';

    /** @var bool Show index file name after URL (e.g. /var/www/foo/bar/index.php)*/
    public static $showIndex = true;

    /** @var bool Print request headers */
    public static $requestHeaders = false;

    /** @var bool Print request cookies @deprecated @todo */
    public static $requestCookies = false;

    /** @var bool Print request body @deprecated @todo */
    public static $requestBody = false;

    /** @var bool Print data received through STDIN (e.g. PUT method data) @deprecated @todo */
    public static $stdinData = false;

    /** @var bool Print response headers */
    public static $responseHeaders = false;

    /** @var bool Print response cookies @deprecated @todo */
    public static $responseCookies = false;

    /** @var string[] */
    public static $methodColors = [
        'get' => Ansi::DCYAN,
        'head' => Ansi::DCYAN,
        'post' => Ansi::DMAGENTA,
        'put' => Ansi::DMAGENTA,
        'patch' => Ansi::DMAGENTA,
        'delete' => Ansi::DMAGENTA,
        'connect' => Ansi::DGREEN,
        'options' => Ansi::DGREEN,
        'trace' => Ansi::DGREEN,
        'ajax' => Ansi::DRED,
    ];

    /** @var string[] */
    public static $responseColors = [
        1 => Ansi::DYELLOW,
        2 => Ansi::DGREEN,
        3 => Ansi::DYELLOW,
        4 => Ansi::DMAGENTA,
        5 => Ansi::DRED,
    ];

}
