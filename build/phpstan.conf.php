<?php declare(strict_types=1);

$ignore = [];
$paths = [];

if (PHP_VERSION_ID < 70400) {
    // ctrl-c handler (since 7.4)
    $ignore[] = '~Constant PHP_WINDOWS_EVENT_CTRL_(C|BREAK) not found~';
    $ignore[] = '~Used function sapi_windows_set_ctrl_handler not found~';
    $ignore[] = '~expects callable\\(\\): mixed&string, \'sapi_windows_set…\' given~';
} else {
    $paths[] = 'tests/php74';
}
if (PHP_VERSION_ID < 80000) {
    // Socket (since 8.0)
    $ignore[] = '~has invalid return type Socket~';
    $ignore[] = '~has unknown class Socket as its type~';
    $ignore[] = '~expects resource, resource\\|Socket given~';

    // CurlHandle (since 8.0)
    $ignore[] = '~invalid return type Curl(Multi)?Handle~';
    $ignore[] = '~has invalid type Curl(Multi)?Handle~';

    // enums (since 8.1)
    $ignore[] = '~unknown class (Unit|Backed)Enum~';
    $ignore[] = '~has invalid type (Unit|Backed)Enum~';
    $ignore[] = '~Class (Unit|Backed)Enum not found~';

    //$ignore[] = '~Method Dogma\\\\Debug\\\\ShutdownHandler::fakeRegister\\(\\) should return bool\\|null but returns void~';
    //$ignore[] = '~Result of function register_shutdown_function \\(void\\) is used~';
}
if (PHP_VERSION_ID >= 80000) {
    $ignore[] = '~Parameter #1 \\$socket of function socket_.* expects Socket, resource\\|Socket given~';
}
if (PHP_VERSION_ID >= 80100) {
    $paths[] = 'tests/php81';
    $ignore[] = '~Access to an undefined property BackedEnum::\\$name~'; // PHPStan bug
    $ignore[] = '~Call to function is_int\\(\\) with string will always evaluate to false~';
} elseif (PHP_VERSION_ID >= 80000) {
    $ignore[] = '~Access to an undefined property UnitEnum::\\$name~'; // non-existing enums
    $ignore[] = '~Access to an undefined property BackedEnum::\\$(name|value)~';
}

return [
    'parameters' => [
        'paths' => $paths,
        'ignoreErrors' => $ignore,
    ],
];
