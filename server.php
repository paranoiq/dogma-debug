<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

use Dogma\Debug\Ansi;
use Dogma\Debug\DebugServer;

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/src/Debug/Ansi.php';
require_once __DIR__ . '/src/Debug/Packet.php';
require_once __DIR__ . '/src/Debug/System.php';
require_once __DIR__ . '/src/Debug/DebugServer.php';

echo Ansi::lgreen("Dogma-Debug by @paranoiq") . " - Remote console dumper/debugger\n\n";
echo "Usage: " . Ansi::dyellow("php server.php [port] [address]") . "\n\n";

$address = $argv[2] ?? '127.0.0.1';
$port = (int) ($argv[1] ?? 1729);

$server = new DebugServer($port, $address);
$server->run();
