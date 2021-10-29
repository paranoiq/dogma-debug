<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;

require_once __DIR__ . '/../bootstrap.php';

first_class_functions:
$closure = date(...);

Assert::dump($closure, '<$closure>: <Closure> function <date>(<$format>, <$timestamp>) <{><}>');
