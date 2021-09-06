<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

use Dogma\Debug\Colors as C;
use Dogma\Debug\DebugServer;

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/Debug/Colors.php';
require_once __DIR__ . '/Debug/Packet.php';
require_once __DIR__ . '/Debug/DebugServer.php';

echo C::lgreen("Dogma-Debug by @paranoiq") . " - Remote console dumper/debugger\n\n";
echo "Usage: " . C::dyellow("php debug-server.php [port] [address]") . "\n\n";

$address = $argv[2] ?? '127.0.0.1';
$port = (int) ($argv[1] ?? 1729);

$server = new DebugServer($port, $address);
$server->run();
