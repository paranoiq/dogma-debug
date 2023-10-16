<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Diff;

require_once __DIR__ . '/../bootstrap.php';

// skip
Assert::true(true);

//Assert::same(Diff::diff([], []), []);
rd(Diff::diff(['1', '2', '3', '4', '5', '6', '7', '8'], ['1', '2', '3', '5', '6', '10', '11', '7']));
//Assert::same(Diff::diff(['1', '2', '3', '4', '5', '6', '7', '8'], ['1', '2', '3', '5', '6', '10', '11', '7']), []);
//Assert::same(Diff::htmlDiff("1 2 3 4 5 6 7 8", "1 2 3 5 6 10 11 7"), []);
