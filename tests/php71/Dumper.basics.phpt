<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Closure;
use DateTime;
use DateTimeZone;
use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;
use function range;
use function tmpfile;
use const INF;
use const NAN;

require_once __DIR__ . '/../bootstrap.php';


class Foo {
    private $a = 1;
    protected $b = "bar";

    public function bar(int $c): int {
        static $x = 42;

        return $this->a + $c + $x;
    }
}

class Bar {
    private static $a = 1;
    protected static $b = "bar";

    public static function bar(int $c): int {
        static $x = 42;

        return self::$a + $c + $x;
    }
}

Assert::$dump = true;
Dumper::$useFormatters = false;


recursion:
Assert::dump($GLOBALS, null);


null:
Assert::dump(null, '<literal>: <null>');


booleans:
Assert::dump(false, '<literal>: <false>');
Assert::dump(true, '<literal>: <true>');


integers:
Assert::dump(0, '<literal>: <0>');
Assert::dump(-1, '<literal>: <-1>');
Assert::dump(64, '<literal>: <64>');

// powers of two
Assert::dump(512, '<literal>: <512> <// 2^9>');
Assert::dump(-512, '<literal>: <-512> <// 2^9>');
Assert::dump(65536, '<literal>: <65536> <// 2^16>');
Assert::dump(65535, '<literal>: <65535> <// 2^16-1>');

// bytes
Assert::dump(["size" => 23], '<literal>: <[>
   <size> => <23>,
<]> <// 1 item>');
Assert::dump(["size" => 23000], '<literal>: <[>
   <size> => <23000>, <// 22.5 KB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000], '<literal>: <[>
   <size> => <23000000>, <// 21.9 MB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000], '<literal>: <[>
   <size> => <23000000000>, <// 21.4 GB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000000], '<literal>: <[>
   <size> => <23000000000000>, <// 20.9 TB>
<]> <// 1 item>');

// flags
Assert::dump(["flags" => 23], '<literal>: <[>
   <flags> => <23>, <// 16|4|2|1>
<]> <// 1 item>');

// timestamps
$time = 1623018172;
Dumper::$infoTimeZone = new DateTimeZone('Z');
Assert::dump($time, '<$time>: <1623018172> <// 2021-06-06 22:22:52+00:00>');

Dumper::$infoTimeZone = new DateTimeZone('Europe/Prague');
Assert::dump($time, '<$time>: <1623018172> <// 2021-06-07 00:22:52+02:00>');


floats:
Assert::dump(0.0, '<literal>: <0.0>');
Assert::dump(1.23, '<literal>: <1.23>');
Assert::dump(-1.23, '<literal>: <-1.23>');
Assert::dump(INF, '<literal>: <INF>');
Assert::dump(-INF, '<literal>: <-INF>');
Assert::dump(NAN, '<literal>: <NAN>');


strings:
Assert::dump('', '<literal>: <"">');
Assert::dump('abcdef', '<literal>: <"abcdef"> <// 6 B>');
Assert::dump('áčř', '<literal>: <"áčř"> <// 6 B, 3 ch>');

// escaping
Assert::dump('"', '<literal>: <"<\"<">');
Assert::dump("\n", '<literal>: <"<\n<">');

// color codes
Assert::dump('orange', '<literal>: <"orange"> <// <<     <>');
Assert::dump('#5F9EA0', '<literal>: <"#5F9EA0"> <// <<     <>');
$color = '00FF7F';
Assert::dump($color, '<$color>: <"00FF7F"> <// <<     <>');

// callables
Assert::dump('strlen', '<literal>: <"strlen"> <// 6 B, callable>');

// limit
Dumper::$maxLength = 10;
Assert::dump('příliš žluťoučký kůň úpěl ďábelské ódy', '<literal>: <"příliš žlu…"> <// 53 B, 38 ch, trimmed>');
Dumper::$maxLength = 10000;


arrays:
Assert::dump([], '<literal>: <[]>');

// short
Assert::dump([1, 2, 3, 4, 5], '<literal>: <[><1>, <2>, <3>, <4>, <5><]> <// 5 items>');

// long
Assert::dump(range(100000001, 100000010), '<range(100000001, 100000010)>: <[>
   <100000001>,
   <100000002>,
   <100000003>,
   <100000004>,
   <100000005>,
   <100000006>,
   <100000007>,
   <100000008>,
   <100000009>,
   <100000010>,
<]> <// 10 items>');

// nested
Assert::dump(
    [1, 2, 3, [4, 5, 6, 'long line long line long line long line long line long line long line long line long line']],
    '<literal>: <[>
   <1>,
   <2>,
   <3>,
   <[>
   <|>  <4>,
   <|>  <5>,
   <|>  <6>,
   <|>  <"long line long line long line long line long line long line long line long line long line">, <// 89 B>
   <]>, <// 4 items>
<]> <// 4 items>'
);

// keys
Assert::dump(
    [1 => 1, 2, 3, ['long line long line long line long line long line long line long line long line', 'foo' => 4, 'bar' => 5, 'baz' => 6]],
    '<literal>: <[>
   <1> => <1>,
   <2> => <2>,
   <3> => <3>,
   <4> => <[>
   <|>  <0> => <"long line long line long line long line long line long line long line long line">, <// 79 B>
   <|>  <foo> => <4>,
   <|>  <bar> => <5>,
   <|>  <baz> => <6>,
   <]>, <// 4 items>
<]> <// 4 items>'
);


objects:
$dateTime = new DateTime('2001-02-03 04:05:06', new DateTimeZone('Europe/Prague'));
Assert::dump($dateTime, '<$dateTime>: <DateTime> <{> <// #?id>
   <public> <$date> = <"2001-02-03 04:05:06.000000">; <// 26 B>
   <public> <$timezone> = <"Europe/Prague">; <// 13 B>
   <public> <$timezone_type> = <3>;
<}>');

$foo = new Foo();
Assert::dump($foo, '<$foo>: <Dogma><\><Tests><\><Debug><\><Foo> <{> <// #?id>
   <private> <$a> = <1>;
   <protected> <$b> = <"bar">;
<}>');


static_properties:
Assert::dump(Bar::class, '<Bar::class>: <Dogma><\><Tests><\><Debug><\><Bar>::<class> <{>
   <private static> <$a> = <1>;
   <protected static> <$b> = <"bar">;
<}>');


closures:
$closure = static function ($a, $b) use ($dateTime): int {
    return $a + $b + $dateTime->getTimestamp();
};
Assert::dump($closure, '<$closure>: <Closure> static function (<$a>, <$b>) use (<$dateTime>): int <{>< // ><tests/php71/>Dumper.basics.phpt<:><' . (__LINE__ - 3) . '>
   <static> <$dateTime> = <DateTime> <{> <// #?id>
   <|>  <public> <$date> = <"2001-02-03 04:05:06.000000">; <// 26 B>
   <|>  <public> <$timezone> = <"Europe/Prague">; <// 13 B>
   <|>  <public> <$timezone_type> = <3>;
   <}>;
<}>');

// todo $this

$closure = Closure::fromCallable('date');
Assert::dump($closure, '<$closure>: <Closure> function <date>(<$format>, <$timestamp>) <{><}>');

$closure = Closure::fromCallable([$foo, 'bar']);
Assert::dump($closure, '<$closure>: <Closure> function <bar>(int <$c>): int <{>< // ><tests/php71/>Dumper.basics.phpt<:><22>
   <static> <$x> = <42>;
<}>');

$closure = Closure::fromCallable([Bar::class, 'bar']);
Assert::dump($closure, '<$closure>: <Closure> static function <bar>(int <$c>): int <{>< // ><tests/php71/>Dumper.basics.phpt<:><33>
   <static> <$x> = <42>;
<}>');


callables:
Assert::dump([$foo, 'bar'], '<[$foo, \'bar\']>: <Dogma><\><Tests><\><Debug><\><Foo>::<bar><()> <{>
   <static> <$x> = <42>;
<}>');

Assert::dump([Bar::class, 'bar'], '<[Bar::class, \'bar\']>: <Dogma><\><Tests><\><Debug><\><Bar>::<bar><()> <{>
   <static> <$x> = <42>;
<}>');


stream:
$file = tmpfile();
Assert::dump($file, '<$file>: <resource (stream)> <{> <#?id>
   <$blocked> = <true>;
   <$eof> = <false>;
   <$mode> = <"r+b">;
   <$seekable> = <true>;
   <$stream_type> = <"STDIO">;
   <$timed_out> = <false>;
   <$unread_bytes> = <0>;
   <$uri> = <"?path?file">; <// ?bytes B, ?path?file>
   <$wrapper_type> = <"plainfile">; <// 9 B>
<}>');
