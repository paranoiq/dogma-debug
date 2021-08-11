<?php declare(strict_types=1);

$ignore = PHP_VERSION_ID < 80000
    ? [
        '~Property DogmaDebugTools::\$socket has unknown class Socket as its type.~',
        '~Parameter #1 \$socket of function socket_write expects resource, resource\|Socket given.~',
    ]
    : [
        '~Parameter #1 \$socket of function socket_.* expects Socket, resource\|Socket given~',
    ];

return ['parameters' => ['ignoreErrors' => $ignore]];