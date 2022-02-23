<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\FileStreamWrapper;

require_once __DIR__ . '/../bootstrap.php';

FileStreamWrapper::enable();


require __DIR__ . '/../data/test1.php';

require_once __DIR__ . '/../data/test2.php';

include __DIR__ . '/../data/test3.php';

include_once __DIR__ . '/../data/test4.php';

Assert::true(true);
