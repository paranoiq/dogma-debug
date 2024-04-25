<?php

// spell-check-ignore: xff

namespace Dogma\Tests\Debug;

use Closure;
use DateTime;
use DateTimeZone;
use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;
use Dogma\Debug\System;
use function range;
use function tmpfile;

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


arrays:
Assert::dump([], '<literal>: <[]>');

// short
Dumper::$alwaysShowArrayKeys = false;
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
Assert::dump([1, 2, 3, [4, 5, 6, 'long line long line long line long line long line long line long line long line long line']], '<literal>: <[>
    <1>,
    <2>,
    <3>,
    <[>
    <|>   <4>,
    <|>   <5>,
    <|>   <6>,
    <|>   <"long line long line long line long line long line long line long line long line long line">, <// 89 B>
    <]>, <// 4 items>
<]> <// 4 items>'
);

// keys
Assert::dump([1 => 1, 2, 3, ['long line long line long line long line long line long line long line long line', 'foo' => 4, 'bar' => 5, 'baz' => 6]], '<literal>: <[>
    <1> => <1>,
    <2> => <2>,
    <3> => <3>,
    <4> => <[>
    <|>   <0> => <"long line long line long line long line long line long line long line long line">, <// 79 B>
    <|>   <foo> => <4>,
    <|>   <bar> => <5>,
    <|>   <baz> => <6>,
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

$anon = new class extends Foo {
    private $c = 2;
};

$dump = Assert::normalize(Dumper::dump($anon));
Assert::contains($dump, ': <Dogma><\><Tests><\><Debug><\><Foo@anonymous¤');
Assert::contains($dump, '<{> <// #?id>
    <private> <Dogma><\><Tests><\><Debug><\><Foo>::<$a> = <1>;
    <private> <$c> = <2>;
    <protected> <$b> = <"bar">;
<}>');


classes:
Assert::dump(Bar::class, '<Bar::class>: <Dogma><\><Tests><\><Debug><\><Bar>::class <{>
    <private static> <$a> = <1>;
    <protected static> <$b> = <"bar">;
<}>');

Dumper::$dumpClassesWithStaticMethodVariables = true;
Assert::dump(Bar::class, '<Bar::class>: <Dogma><\><Tests><\><Debug><\><Bar>::class <{>
    <private static> <$a> = <1>;
    <protected static> <$b> = <"bar">;
    <public static function ><bar><()> <{>
    <|>   <static> <$x> = <42>;
    <}>
<}>');


closures:
$closure = static function ($a, $b) use ($dateTime): int {
    return $a + $b + $dateTime->getTimestamp();
};
Assert::dump($closure, '<$closure>: <Closure> static function (<$a>, <$b>) use (<$dateTime>): int <{>< // ><tests/php71/>Dumper.objects.phpt<:><' . (__LINE__ - 3) . '>
    <static> <$dateTime> = <DateTime> <{> <// #?id>
    <|>   <public> <$date> = <"2001-02-03 04:05:06.000000">; <// 26 B>
    <|>   <public> <$timezone> = <"Europe/Prague">; <// 13 B>
    <|>   <public> <$timezone_type> = <3>;
    <}>;
<}>');

// todo $this

$closure = Closure::fromCallable('date');
Assert::dump($closure, '<$closure>: <Closure> function <date>(<$format>, <$timestamp>) <{><}>');

$closure = Closure::fromCallable([$foo, 'bar']);
Assert::dump($closure, '<$closure>: <Closure> function <bar>(int <$c>): int <{>< // ><tests/php71/>Dumper.objects.phpt<:><27>
    <static> <$x> = <42>;
<}>');

$closure = Closure::fromCallable([Bar::class, 'bar']);
Assert::dump($closure, '<$closure>: <Closure> static function <bar>(int <$c>): int <{>< // ><tests/php71/>Dumper.objects.phpt<:><38>
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
if (System::isWindows()) {
    Assert::dump($file, '<$file>: <(stream ?id> <STDIO> <r+b> <seekable> <blocked> position:<0><)>');
    /* todo: uri etc.?
     <{>
    <$blocked> = <true>;
    <$eof> = <false>;
    <$mode> = <"r+b">;
    <$seekable> = <true>;
    <$stream_type> = <"STDIO">;
    <$timed_out> = <false>;
    <$unread_bytes> = <0>;
    <$uri> = <"?path?file">; <// ?bytes B, ?path?file>
    <$wrapper_type> = <"plainfile">; <// 9 B>
    <}>
    */
} else {
    Assert::dump($file, '<$file>: <(stream ?id> <STDIO> <r+b> <seekable> <blocked> position:<0><)>');
    /* todo: uri etc.?
    <{>
    <$blocked> = <true>;
    <$eof> = <false>;
    <$mode> = <"r+b">;
    <$seekable> = <true>;
    <$stream_type> = <"STDIO">;
    <$timed_out> = <false>;
    <$unread_bytes> = <0>;
    <$uri> = <"?path?file">; <// ?bytes B>
    <$wrapper_type> = <"plainfile">; <// 9 B>
    <}>
     */
}
