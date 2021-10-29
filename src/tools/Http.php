<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

class Http
{

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

}
