<?php declare(strict_types=1);

$ignore = [];
$paths = [];

if (PHP_VERSION_ID < 70400) {
    // ctrl-c handler
    $ignore[] = '~Constant PHP_WINDOWS_EVENT_CTRL_(C|BREAK) not found~';
    $ignore[] = '~Used constant PHP_WINDOWS_EVENT_CTRL_(C|BREAK) not found~';
    $ignore[] = '~Used function sapi_windows_set_ctrl_handler not found~';
    $ignore[] = '~expects callable\(\): mixed&string, \'sapi_windows_set…\' given~';
    $ignore[] = '~expects callable\(\): mixed&string, \'pcntl_unshare\' given~';

    // property types
    $ignore[] = '~Call to an undefined method ReflectionProperty::getType~';
    $ignore[] = '~Call to an undefined method ReflectionProperty::isInitialized~';

    // weak ref
    $ignore[] = '~WeakReference~';
    $ignore[] = '~ReflectionReference~';
}
if (PHP_VERSION_ID < 80000) {
    // Socket
    $ignore[] = '~has invalid return type Socket~';
    $ignore[] = '~has unknown class Socket as its type~';
    $ignore[] = '~expects resource, resource\\|Socket given~';

    // CurlHandle
    $ignore[] = '~invalid return type Curl(Multi)?Handle~';
    $ignore[] = '~has invalid type Curl(Multi)?Handle~';

    // attributes
    $ignore[] = '~Call to an undefined method ReflectionClass::getAttributes~';
    $ignore[] = '~Call to an undefined method ReflectionClassConstant::getAttributes~';
    $ignore[] = '~Call to an undefined method ReflectionFunctionAbstract::getAttributes~';
    $ignore[] = '~Call to an undefined method ReflectionParameter::getAttributes~';
    $ignore[] = '~Call to an undefined method ReflectionProperty::getAttributes~';

    $ignore[] = '~Call to an undefined method ReflectionProperty::getDefaultValue~';

    // promoted properties
    $ignore[] = '~Call to an undefined method ReflectionProperty::isPromoted~';
    $ignore[] = '~Call to an undefined method ReflectionParameter::isPromoted~';

    // enums
    $ignore[] = '~(Unit|Backed)Enum~';
    $ignore[] = '~ReflectionEnum(UnitCase|BackedCase)?~';
}
if (PHP_VERSION_ID < 80100) {
    // readonly properties
    $ignore[] = '~Call to an undefined method ReflectionProperty::isReadOnly~';

    // tentative return types
    $ignore[] = '~Call to an undefined method ReflectionFunctionAbstract::getTentativeReturnType~';

    // final constants
    $ignore[] = '~Call to an undefined method ReflectionClassConstant::isFinal~';

    // fibers
    $ignore[] = '~has invalid type ReflectionFiber~';
}

if (PHP_VERSION_ID >= 80000) {
    $ignore[] = '~Parameter #1 \\$socket of function socket_.* expects Socket, resource\\|Socket given~';
}

if (PHP_VERSION_ID <= 80400) { // todo: 80300 - bug in 8.3 alpha?
    $ignore[] = '~Call to an undefined method ReflectionClass::isReadOnly~';
}

// include
if (PHP_VERSION_ID >= 70400) {
    $paths[] = 'tests/php74';
}
if (PHP_VERSION_ID >= 80100) {
    $paths[] = 'tests/php81';
}

return [
    'parameters' => [
        'paths' => $paths,
        'ignoreErrors' => $ignore,
    ],
];
