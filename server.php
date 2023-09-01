<?php
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
    require_once __DIR__ . '/src/Message.php';
    require_once __DIR__ . '/src/DebugServer.php';
}

System::setProcessName('Dogma Debug Server (php-cli)');

echo Ansi::lgreen("Dogma-Debug by @paranoiq") . " - Remote console dumper/debugger\n\n";
echo "Usage: " . Ansi::dyellow("php server.php [port] [address] [file]") . "\n\n";

$config = [
    'port' => 1729,
    'host' => '127.0.0.1',
    'log-file' => __DIR__ . '/debugger.log',
    'keep-old-log' => false,
];
$key = null;
foreach ($argv as $i => $arg) {
    if ($key === 'port' || ($i === 0 && is_numeric($arg))) {
        $config['port'] = (int) $arg;
    } elseif ($key === 'host' || ($i === 1 && preg_match('~\d+.\d+.\d+.\d+~', $arg))) {
        $config['host'] = $arg;
    } elseif ($key === 'log-file' || ($i === 2 && preg_match('~.log$~', $arg))) {
        $config['log-file'] = $arg;
    } elseif ($arg === '--keep-old-log') {
        $config['keep-old-log'] = true;
    } elseif (array_key_exists(ltrim($arg, '-'), $config)) {
        $key = ltrim($arg, '-');
    } elseif ($i === 0) {
        continue;
    } else {
        exit("Unknown argument: {$arg}\n");
    }
}

if (file_exists($config['log-file']) && !$config['keep-old-log']) {
    unlink($config['log-file']);
}

$server = new DebugServer($config['port'], $config['host'], $config['log-file']);
$server->run();
