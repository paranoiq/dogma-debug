<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;

require_once __DIR__ . '/../bootstrap.php';

/*
short_closure:
$c = 1;
$d = 2;
$f = static fn ($a, $b): int => $a + $b + $c + $d;

Assert::dump($f, '<Closure> <static fn (<$a<, <$b<): int ><{>< // ><?path><?file><:><?line>
   <$c> <=> <1><;>
   <$d> <=> <2><;>
<}>');
*/

Assert::true(true);
