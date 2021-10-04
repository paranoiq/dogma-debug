<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Dogma\Debug\Assert;
use Dogma\Time\Date;
use Dogma\Time\DateTime as DogmaDateTime;
use Dogma\Time\Interval\DateInterval;
use Dogma\Time\Interval\DateIntervalSet;
use Dogma\Time\Interval\DateTimeInterval;
use Dogma\Time\Interval\NightInterval;
use Dogma\Time\Interval\TimeInterval;
use Dogma\Time\Time;
use function fopen;
use function rd;

require_once __DIR__ . '/../bootstrap.php';

// todo remove
rd($GLOBALS);
Assert::$dump = true;


$timeZone = new DateTimeZone('Europe/Prague');


DateTimeInterface:
// offset
$dt = new DateTime('2001-02-03 04:05:06+01:00');
Assert::dump($dt, '<DateTime><(><2001-02-03 04:05:06><+01:00><)> <// #?id>');

// microseconds
$dt = new DateTime('2001-02-03 04:05:06.123456+01:00');
Assert::dump($dt, '<DateTime><(><2001-02-03 04:05:06.123456><+01:00><)> <// #?id>');

// old tz
$dt = new DateTime('2001-02-03 04:05:06Z');
Assert::dump($dt, '<DateTime><(><2001-02-03 04:05:06><+00:00> <Z><)> <// #?id>');

// location tz
$dt = new DateTime('2001-02-03 04:05:06', $timeZone);
Assert::dump($dt, '<DateTime><(><2001-02-03 04:05:06><+01:00> <Europe/Prague><)> <// #?id>');

// daylight saving time
$dt = new DateTime('2001-06-01 04:05:06', $timeZone);
Assert::dump($dt, '<DateTime><(><2001-06-01 04:05:06><+02:00> <Europe/Prague> <DST><)> <// #?id>');

// DateTimeImmutable
$dt = new DateTimeImmutable('2001-02-03 04:05:06', $timeZone);
Assert::dump($dt, '<DateTimeImmutable><(><2001-02-03 04:05:06><+01:00> <Europe/Prague><)> <// #?id>');

// Dogma\Time\DateTime
$dt = new DogmaDateTime('2001-02-03 04:05:06', $timeZone);
Assert::dump($dt, '<Dogma><\><Time><\><DateTime><(><2001-02-03 04:05:06><+01:00> <Europe/Prague><)> <// #?id>');


Date:
$date = new Date('2001-02-03');
Assert::dump($date, '<Dogma><\><Time><\><Date><(><2001-02-03> </> <2451944><)> <// #?id>');


Time:
$time = new Time('23:59:59');
Assert::dump($time, '<Dogma><\><Time><\><Time><(><23:59:59> </> <86399000000><)> <// #?id>');

$time = new Time('23:59:59.999999');
Assert::dump($time, '<Dogma><\><Time><\><Time><(><23:59:59.999999> </> <86399999999><)> <// #?id>');


DateTimeInterval:
$dti = DateTimeInterval::empty();
Assert::dump($dti, '<Dogma><\><Time><\><Interval><\><DateTimeInterval><(><empty><)> <// #?id>');
$dti = DateTimeInterval::createFromString('2001-02-03 04:05:06 - 2011-12-13 14:15:16');
Assert::dump($dti, '<Dogma><\><Time><\><Interval><\><DateTimeInterval><(><2001-02-03 04:05:06><+01:00> <-> <2011-12-13 14:15:16><+01:00> <Europe/Prague><)> <// #?id, length: 10-10-10 10:10:10>');
$dti = DateTimeInterval::createFromString('2001-02-03 04:05:06 - 2001-06-03 14:15:16');
Assert::dump($dti, '<Dogma><\><Time><\><Interval><\><DateTimeInterval><(><2001-02-03 04:05:06><+01:00> <-> <2001-06-03 14:15:16><+02:00> <Europe/Prague> <-> <Europe/Prague> <DST><)> <// #?id, length: 0-4-0 10:10:10>');
$dti = new DateTimeInterval($dt, new DogmaDateTime('2011-12-13 14:15:16', new DateTimeZone('Z')));
Assert::dump($dti, '<Dogma><\><Time><\><Interval><\><DateTimeInterval><(><2001-02-03 04:05:06><+01:00> <-> <2011-12-13 14:15:16><+00:00> <Europe/Prague> <-> <Z><)> <// #?id, length: 10-10-10 11:10:10>');


TimeInterval:
$ti = TimeInterval::empty();
Assert::dump($ti, '<Dogma><\><Time><\><Interval><\><TimeInterval><(><empty><)> <// #?id>');
$ti = TimeInterval::createFromString('04:05:06 - 14:15:16');
Assert::dump($ti, '<Dogma><\><Time><\><Interval><\><TimeInterval><(><04:05:06> <-> <14:15:16><)> <// #?id, length: 10:10:10>');


DateInterval:
$di = DateInterval::empty();
Assert::dump($di, '<Dogma><\><Time><\><Interval><\><DateInterval><(><empty><)> <// #?id>');
$di = DateInterval::createFromString('2001-02-03 - 2001-02-03');
Assert::dump($di, '<Dogma><\><Time><\><Interval><\><DateInterval><(><2001-02-03> <-> <2001-02-03><)> <// #?id, 1 day>');
$di = DateInterval::createFromString('2001-02-03 - 2011-12-13');
Assert::dump($di, '<Dogma><\><Time><\><Interval><\><DateInterval><(><2001-02-03> <-> <2011-12-13><)> <// #?id, 3966 days>');


NightInterval:
$ni = NightInterval::empty();
Assert::dump($ni, '<Dogma><\><Time><\><Interval><\><NightInterval><(><empty><)> <// #?id>');
$ni = NightInterval::createFromString('2001-02-03 - 2001-02-04');
Assert::dump($ni, '<Dogma><\><Time><\><Interval><\><NightInterval><(><2001-02-03> <-> <2001-02-04><)> <// #?id, 1 night>');
$ni = NightInterval::createFromString('2001-02-03 - 2011-12-13');
Assert::dump($ni, '<Dogma><\><Time><\><Interval><\><NightInterval><(><2001-02-03> <-> <2011-12-13><)> <// #?id, 3965 nights>');


IntervalSet:
ModuloIntervalSet:
$set = new DateIntervalSet([
    DateInterval::createFromString('2001-01-01 - 2001-01-02'),
    DateInterval::createFromString('2001-01-04 - 2001-01-05'),
    DateInterval::createFromString('2001-01-07 - 2001-01-08'),
]);
Assert::dump($set, '<Dogma><\><Time><\><Interval><\><DateIntervalSet><[>
   <Dogma><\><Time><\><Interval><\><DateInterval><(><2001-01-01> <-> <2001-01-02><)><,> <// #?id, 2 days>
   <Dogma><\><Time><\><Interval><\><DateInterval><(><2001-01-04> <-> <2001-01-05><)><,> <// #?id, 2 days>
   <Dogma><\><Time><\><Interval><\><DateInterval><(><2001-01-07> <-> <2001-01-08><)><,> <// #?id, 2 days>
<]> <// #?id, 3 items>');


stream:
$file = fopen(__FILE__, 'r');
Assert::dump($file, '<stream resource><(> <#?id>
   <$blocked> <=> <true><;>
   <$eof> <=> <false><;>
   <$mode> <=> <"r"><;> <// 1 B>
   <$seekable> <=> <true><;>
   <$stream_type> <=> <"STDIO"><;> <// 5 B>
   <$timed_out> <=> <false><;>
   <$unread_bytes> <=> <0><;>
   <$uri> <=> <"?pathDumper.handlers.phpt"><;> <// ?bytes B>
   <$wrapper_type> <=> <"plainfile"><;> <// 9 B>
<)>');
