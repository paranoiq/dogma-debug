<?php declare(strict_types=1);

// 7.1 - 7.4
$ignore = [
    '~Property Dogma\\\\Debug\\\\Debug(Client|Server)::\\$socket has unknown class Socket as its type~',
    '~Parameter \\$connection of method Dogma\\\\Debug\\\\DebugServer::processRequest\\(\\) has invalid typehint type Socket~',
    '~Parameter #1 \\$socket of function socket_.* expects resource, resource\\|Socket given~',
];
if (PHP_VERSION_ID >= 80000) {
    $ignore = [
        '~Parameter #1 \\$socket of function socket_.* expects Socket, resource\\|Socket given~',
    ];
}

$paths = [];
if (PHP_VERSION_ID >= 74000) {
    $paths[] = 'tests/php74';
}
if (PHP_VERSION_ID >= 81000) {
    $paths[] = 'tests/php81';
}

return [
    'parameters' => [
        'paths' => $paths,
        'ignoreErrors' => $ignore,
    ],
];
