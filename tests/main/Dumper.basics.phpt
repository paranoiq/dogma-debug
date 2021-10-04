<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use DateTime;
use DateTimeZone;
use Dogma\Debug\Assert;
use Dogma\Debug\Ansi;
use Dogma\Debug\DebugClient;
use Dogma\Debug\Dumper;
use Dogma\Debug\Packet;
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
Dumper::$useHandlers = false;


recursion:
Assert::dump($GLOBALS, null);


null:
Assert::dump(null, '<null>');


booleans:
Assert::dump(false, '<false>');
Assert::dump(true, '<true>');


integers:
Assert::dump(0, '<0>');
Assert::dump(-1, '<-1>');
Assert::dump(64, '<64>');

// powers of two
Assert::dump(512, '<512> <// 2^9>');
Assert::dump(-512, '<-512> <// 2^9>');
Assert::dump(65536, '<65536> <// 2^16>');
Assert::dump(65535, '<65535> <// 2^16-1>');

// bytes
Assert::dump(["size" => 23], '<[>
   <size> <=>> <23><,>
<]> <// 1 item>');
Assert::dump(["size" => 23000], '<[>
   <size> <=>> <23000><,> <// 22.5 KB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000], '<[>
   <size> <=>> <23000000><,> <// 21.9 MB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000], '<[>
   <size> <=>> <23000000000><,> <// 21.4 GB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000000], '<[>
   <size> <=>> <23000000000000><,> <// 20.9 TB>
<]> <// 1 item>');

// flags
Assert::dump(["flags" => 23], '<[>
   <flags> <=>> <23><,> <// 16|4|2|1>
<]> <// 1 item>');

// timestamps
Assert::dump(["REQUEST_TIME" => 1623018172], '<[>
   <REQUEST_TIME> <=>> <1623018172><,> <// 2021-06-07 00:22:52+02:00>
<]> <// 1 item>');

Dumper::$infoTimeZone = new DateTimeZone('Z');
Assert::dump(["REQUEST_TIME" => 1623018172], '<[>
   <REQUEST_TIME> <=>> <1623018172><,> <// 2021-06-06 22:22:52+00:00>
<]> <// 1 item>');


floats:
Assert::dump(0.0, '<0.0>');
Assert::dump(1.23, '<1.23>');
Assert::dump(-1.23, '<-1.23>');
Assert::dump(INF, '<INF>');
Assert::dump(-INF, '<-INF>');
Assert::dump(NAN, '<NAN>');


strings:
Assert::dump('', '<""> <// 0 B>');
Assert::dump('abc', '<"abc"> <// 3 B>');
Assert::dump('áčř', '<"áčř"> <// 6 B, 3 ch>');

// escaping
Assert::dump('"', '<"<\"<"> <// 1 B>');
Assert::dump("\n", '<"<\n<"> <// 1 B>');

// color codes
Assert::dump('orange', '<"orange"> <// <<     >>');
Assert::dump('#5F9EA0', '<"#5F9EA0"> <// <<     >>');
Assert::dump(["color" => '00FF7F'], '<[>
   <color> <=>> <"00FF7F"><,> <// <<     >>
<]> <// 1 item>');

// callables
Assert::dump('strlen', '<"strlen"> <// 6 B, callable>');

// limit
Dumper::$maxLength = 10;
Assert::dump('příliš žluťoučký kůň úpěl ďábelské ódy', '<"příliš žlu…"> <// 53 B, 38 ch, trimmed>');
Dumper::$maxLength = 10000;


arrays:
Assert::dump([], '<[]>');

// short
Assert::dump([1, 2, 3, 4, 5], '<[><1><,> <2><,> <3><,> <4><,> <5><]> <// 5 items>');

// long
Assert::dump(
    range(100000001, 100000010),
    '<[>
   <100000001><,>
   <100000002><,>
   <100000003><,>
   <100000004><,>
   <100000005><,>
   <100000006><,>
   <100000007><,>
   <100000008><,>
   <100000009><,>
   <100000010><,>
<]> <// 10 items>'
);

// nested
Assert::dump(
    [1, 2, 3, [4, 5, 6, 'long line long line long line long line long line long line long line long line long line']],
    '<[>
   <1><,>
   <2><,>
   <3><,>
   <[>
   <|>  <4><,>
   <|>  <5><,>
   <|>  <6><,>
   <|>  <"long line long line long line long line long line long line long line long line long line"><,> <// 89 B>
   <]><,> <// 4 items>
<]> <// 4 items>'
);

// keys
Assert::dump(
    [1 => 1, 2, 3, ['long line long line long line long line long line long line long line long line', 'foo' => 4, 'bar' => 5, 'baz' => 6]],
    '<[>
   <1> <=>> <1><,>
   <2> <=>> <2><,>
   <3> <=>> <3><,>
   <4> <=>> <[>
   <|>  <0> <=>> <"long line long line long line long line long line long line long line long line"><,> <// 79 B>
   <|>  <foo> <=>> <4><,>
   <|>  <bar> <=>> <5><,>
   <|>  <baz> <=>> <6><,>
   <]><,> <// 4 items>
<]> <// 4 items>'
);


objects:
$dateTime = new DateTime('2001-02-03 04:05:06', new DateTimeZone('Europe/Prague'));
Assert::dump($dateTime, '<DateTime> <{> <// #?id>
   <public> <$date> <=> <"2001-02-03 04:05:06.000000"><;> <// 26 B>
   <public> <$timezone> <=> <"Europe/Prague"><;> <// 13 B>
   <public> <$timezone_type> <=> <3><;>
<}>');

$foo = new Foo();
Assert::dump($foo, '<Dogma><\><Tests><\><Debug><\><Foo> <{> <// #?id>
   <private> <$a> <=> <1><;>
   <protected> <$b> <=> <"bar"><;> <// 3 B>
<}>');


static_properties:
Assert::dump(Bar::class, '<Dogma><\><Tests><\><Debug><\><Bar><::><class> <{>
   <private static> <$a> <=> <1><;>
   <protected static> <$b> <=> <"bar"><;> <// 3 B>
<}>');


closures:
$closure = static function ($a, $b) use ($dateTime): int {
    return $a + $b + $dateTime->getTimestamp();
};
Assert::dump($closure, '<Closure> <static function (<$a<, <$b<) use (<$dateTime<): int ><{>< // ><?path><Dumper.basics.phpt><:><' . (__LINE__ - 3) . '>
   <$dateTime> <=> <DateTime> <{> <// #?id>
   <|>  <public> <$date> <=> <"2001-02-03 04:05:06.000000"><;> <// 26 B>
   <|>  <public> <$timezone> <=> <"Europe/Prague"><;> <// 13 B>
   <|>  <public> <$timezone_type> <=> <3><;>
   <}><;>
<}>');

// todo $this


backtrace:
$bt = [
    [
        "file" => "C:\http\paranoiq\dogma-debug\src\DebugDumper.php",
        "line" => 219,
        "function" => "dumpArray",
        "class" => "Dogma\Debug\DebugDumper",
        "type" => "::",
        "args" => [["Dogma\Tests\Debug\Foo", "bar"], 0, null],
    ],
    [
        "file" => "C:\http\paranoiq\dogma-debug\\tests\Assert.php",
        "line" => 33,
        "function" => "dumpValue",
        "class" => "Dogma\Debug\DebugDumper",
        "type" => "::",
        "args" => [["Dogma\Tests\Debug\Foo", "bar"], 0, null],
    ],
    [
        "file" => "C:\http\paranoiq\dogma-debug\\tests\php71\Dumper.basics.phpt",
        "line" => 208,
        "function" => "dump",
        "class" => "Dogma\Debug\Assert",
        "type" => "::",
        "args" => [
            ["Dogma\Tests\Debug\Foo", "bar"],
            "<Dogma><\><Tests><\><Debug><\><Foo><::><bar><()> <{>
   <static> <\$x> <=> <42><;>
<}>",
        ],
    ],
];
Assert::same(Assert::normalize(Dumper::dumpBacktrace($bt)), '<^---> <in> <?path><DebugDumper.php><:><219> <-> <Dogma><\><Debug><\><DebugDumper><::><dumpArray><(><[><"Dogma<\\\\<Tests<\\\\<Debug<\\\\<Foo"><,> <"bar"><]><,> <0><,> <null><]><)>
<^---> <in> <?path><Assert.php><:><33> <-> <Dogma><\><Debug><\><DebugDumper><::><dumpValue><(><[><"Dogma<\\\\<Tests<\\\\<Debug<\\\\<Foo"><,> <"bar"><]><,> <0><,> <null><]><)>
<^---> <in> <?path><Dumper.basics.phpt><:><208> <-> <Dogma><\><Debug><\><Assert><::><dump><(>
   <[><"Dogma<\\\\<Tests<\\\\<Debug<\\\\<Foo"><,> <"bar"><]><,> <// 2 items>< (21 B, 3 B)>
   <"<Dogma><<\\\\<><Tests><<\\\\<><Debug><<\\\\<><Foo><::><bar><()> <{><\n<   <static> <$x> <=> <42><;><\n<<}>"><,> <// 85 B>
<]><)>');


callables:
// because Assert::dump() calls Dumper::dumpValue() which does not searches for dumped expression
$dump = Dumper::dump([Foo::class, 'bar']);
Assert::same(Assert::normalize($dump), '[Foo::class, \'bar\']<:> <Dogma><\><Tests><\><Debug><\><Foo><::><bar><()> <{>
   <static> <$x> <=> <42><;>
<}>
<^--- in ><?path><Dumper.basics.phpt><:><' . (__LINE__ - 4) . '>
');


resources:
$file = tmpfile();
Assert::dump($file, '<stream resource><(> <#?id>
   <$blocked> <=> <true><;>
   <$eof> <=> <false><;>
   <$mode> <=> <"r+b"><;> <// 3 B>
   <$seekable> <=> <true><;>
   <$stream_type> <=> <"STDIO"><;> <// 5 B>
   <$timed_out> <=> <false><;>
   <$unread_bytes> <=> <0><;>
   <$uri> <=> <"?path?file"><;> <// ?bytes B>
   <$wrapper_type> <=> <"plainfile"><;> <// 9 B>
<)>');
