<?php declare(strict_types = 1);

require_once dirname(__DIR__, 2) . '/client.php';

$classes = [

];

$n = 0;
while ($n <= PHP_INT_MAX) {
    $n++;
    if (($n % 1000000) === 0) {
        echo '.';
    }
}
