<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;

require_once __DIR__ . '/../bootstrap.php';

short_closure:
$c = 1;
$d = 2;
$fn = static fn ($a, $b): int => $a + $b + $c + $d;

Assert::dump($fn, '<$fn>: <Closure> static fn (<$a>, <$b>): int <{>< // ><tests/php74/>Dumper.fn.phpt<:><12>
    <static> <$c> = <1>;
    <static> <$d> = <2>;
<}>');
