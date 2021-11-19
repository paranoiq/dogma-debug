<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

use Dogma\Debug\Dumper;
use Tracy\Debugger;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/nette/tester/src/bootstrap.php';
require_once __DIR__ . '/Assert.php';

require_once __DIR__ . '/../shortcuts.php';

Dumper::trimPathPrefixAfter('dogma-debug');

if (!empty($_SERVER['argv'])) {
    // may be running from command line, but under 'cgi-fcgi' SAPI
    header('Content-Type: text/plain');
} elseif (PHP_SAPI !== 'cli') {
    // running from browser
    header('Content-Type: text/html');
    Debugger::enable(Debugger::DEVELOPMENT, dirname(__DIR__, 2) . '/log/');
    Debugger::$strictMode = true;
}
