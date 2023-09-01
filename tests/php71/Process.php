<?php
declare(ticks = 1000);

require_once dirname(__DIR__, 2) . '/client.php';

$n = 0;
while ($n <= PHP_INT_MAX) {
    $n++;
    if (($n % 1000000) === 0) {
        echo '.';
    }
}
