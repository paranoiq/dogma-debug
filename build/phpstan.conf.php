<?php declare(strict_types=1);

// 7.1 - 7.4
$ignore = [
    '~Property Dogma\\\\Debug\\\\(Debugger|Debug(Client|Server))::\\$(socket|connections) has unknown class Socket as its type~',
    '~Parameter #1 \\$socket of function socket_.* expects resource, resource\\|Socket given~',
    '~Method Dogma\\\\Debug\\\\ShutdownHandler::fakeRegister\\(\\) should return bool\\|null but returns void~',
    '~Result of function register_shutdown_function \\(void\\) is used~'
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
