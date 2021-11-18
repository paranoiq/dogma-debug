<?php declare(strict_types = 1);

require_once dirname(__DIR__, 2) . '/client.php';

$n = 0;
while (true) {
    $n++;
    if (($n % 1000000) === 0) {
        echo '.';
    }
}
