<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

use Dogma\Debug\Ansi;
use Dogma\Debug\Debugger;
use Dogma\Debug\DebugServer;
use Dogma\Debug\System;

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

// client may have been loaded earlier by auto_prepend_file directive
if (!class_exists(Debugger::class)) {
    require_once __DIR__ . '/src/tools/Str.php';
    require_once __DIR__ . '/src/tools/Ansi.php';
    require_once __DIR__ . '/src/tools/System.php';
    require_once __DIR__ . '/src/tools/Units.php';
    require_once __DIR__ . '/src/Packet.php';
}
require_once __DIR__ . '/src/DebugServer.php';

System::setProcessName('Dogma Debug Server (php-cli)');

echo Ansi::lgreen("Dogma-Debug by @paranoiq") . " - Remote console dumper/debugger\n\n";
echo "Usage: " . Ansi::dyellow("php server.php [port] [address]") . "\n\n";

$address = $argv[2] ?? '127.0.0.1';
$port = (int) ($argv[1] ?? 1729);

$server = new DebugServer($port, $address);
$server->run();
