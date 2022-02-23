<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_map;
use function explode;
use function implode;
use function strtolower;
use function substr;

class Http
{

    public const RESPONSE_MESSAGES = [
        100 => 'Continue',
        101 => 'Switching protocols',
        102 => 'Processing',
        103 => 'Checkpoint',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non authoritative information',
        204 => 'No content',
        205 => 'Reset content',
        206 => 'Partial content',
        207 => 'Multi status',
        208 => 'Already reported',
        226 => 'Im user',

        300 => 'Multiple choices',
        301 => 'Moved permanently',
        302 => 'Found',
        303 => 'See other',
        304 => 'Not modified',
        305 => 'Use proxy',
        306 => 'Switch proxy',
        307 => 'Temporary redirect',
        308 => 'Resume incomplete',

        400 => 'Bad request',
        401 => 'Unauthorized',
        402 => 'Payment required',
        403 => 'Forbidden',
        404 => 'Not found',
        405 => 'Method not allowed',
        406 => 'Not acceptable',
        407 => 'Proxy authentication required',
        408 => 'Request timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length required',
        412 => 'Precondition failed',
        413 => 'Requested entity too large',
        414 => 'Request uri too long',
        415 => 'Unsupported media type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation failed',
        418 => 'Im a teapot',
        419 => 'Authentication timeout',
        420 => 'Enhance your calm',
        421 => 'Misdirected request',
        422 => 'Unprocessable entity',
        423 => 'Locked',
        424 => 'Failed dependency',
        425 => 'Too early',
        426 => 'Upgrade required',
        428 => 'Precondition required',
        429 => 'Too many requests',
        431 => 'Request header fields too large',
        449 => 'Retry with',
        450 => 'Blocked by Windows parental controls',
        451 => 'Unavailable for legal reasons',

        500 => 'Internal server error',
        501 => 'Not implemented',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
        504 => 'Gateway timeout',
        505 => 'Http version not supported',
        506 => 'Variant also negotiates',
        507 => 'Insufficient storage',
        508 => 'Loop detected',
        509 => 'Bandwidth limit exceeded',
        510 => 'Not extended',
        511 => 'Network authentication required',
    ];

    public const HEADERS = [
        // IETF
        'Accept',
        'Accept-Charset',
        'Accept-Datetime',
        'Accept-Encoding',
        'Accept-Language',
        'Accept-Patch',
        'Accept-Ranges',
        'Access-Control-Allow-Origin',
        'Age',
        'Allow',
        'Alt-Svc',
        'Authorization',
        'Cache-Control',
        'Connection',
        'Cookie',
        'Content-Disposition',
        'Content-Encoding',
        'Content-Language',
        'Content-Length',
        'Content-Location',
        'Content-MD5',
        'Content-Range',
        'Content-Security-Policy',
        'Content-Type',
        'Date',
        'DNT',
        'Expect',
        'ET',
        'ETag',
        'Expires',
        'Forwarded',
        'From',
        'Host',
        'If-Match',
        'If-Modified-Since',
        'If-None-Match',
        'If-Range',
        'If-Unmodified-Since',
        'Last-Modified',
        'Link',
        'Location',
        'Max-Forwards',
        'Origin',
        'P3P',
        'Pragma',
        'Proxy-Authenticate',
        'Proxy-Authorization',
        'Public-Key-Pins',
        'Range',
        'Referer',
        'Refresh',
        'Retry-After',
        'Save-Data',
        'Server',
        'Set-Cookie',
        'Status',
        'Strict-Transport-Security',
        'TE',
        'Trailer',
        'Transfer-Encoding',
        'TSV',
        'User-Agent',
        'Upgrade',
        'Vary',
        'Via',
        'Warning',
        'WWW-Authenticate',
        'X-Frame-Options',

        // non-standard
        'Content-Charset',
        'Front-End-Https',
        'Http-Version',
        'Proxy-Connection',
        'Upgrade-Insecure-Requests',
        'X-ATT-DeviceId',
        'X-Content-Duration',
        'X-Content-Security-Policy',
        'X-Content-Type-Options',
        'X-Correlation-ID',
        'X-Csrf-Token',
        'X-Do-Not-Track',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'X-Http-Method-Override',
        'X-Powered-By',
        'X-Request-ID',
        'X-Requested-With',
        'X-UA-Compatible',
        'X-UIDH',
        'X-XSS-Protection',
        'X-Wap-Profile',
        'X-WebKit-CSP',
    ];

    /** @var string[] */
    private static $headerExceptions = [
        'et' => 'ET',
        'etag' => 'ETag',
        'te' => 'TE',
        'dnt' => 'DNT',
        'tsv' => 'TSV',
        'x-att-deviceid' => 'X-ATT-DeviceId',
        'x-correlation-id' => 'X-Correlation-ID',
        'x-request-id' => 'X-Request-ID',
        'x-ua-compatible' => 'X-UA-Compatible',
        'x-uidh' => 'X-UIDH',
        'x-xss-protection' => 'X-XSS-Protection',
        'x-webkit-csp' => 'X-WebKit-CSP',
        'www-authenticate' => 'WWW-Authenticate',
    ];

    public static function normalizeHeaderName(string $name): string
    {
        if (Str::startsWith($name, 'HTTP_')) {
            $name = substr($name, 5);
        }
        $name = str_replace('_', '-', strtolower($name));

        return self::$headerExceptions[$name] ?? implode('-', array_map('ucfirst', explode('-', $name)));
    }

}
