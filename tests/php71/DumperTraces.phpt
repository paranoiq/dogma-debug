<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Callstack;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

function a(int $length): string
{
    return b($length);
}

function b(int $length): string
{
    return c($length);
}

function c(int $length): string
{
    return Dumper::dump(true, 1, $length);
}

function d(int $length, int $depth, array $lines): string
{
    return e($length, $depth, $lines);
}

function e(int $length, int $depth, array $lines): string
{
    return f($length, $depth, $lines);
}

function f(int $length, int $depth, array $lines): string
{
    return Dumper::formatCallstack(Callstack::get(), $length, $depth, $lines);
}


formatCallstack:
Assert::same(Assert::normalize(d(0, 0, [])), '');
Assert::same(Assert::normalize(d(1, 0, [])), '<^--- in ><tests/php71/>DumperTraces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>');
Assert::same(Assert::normalize(d(2, 0, [])), '<^--- in ><tests/php71/>DumperTraces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>');
Assert::same(Assert::normalize(d(3, 0, [])), '<^--- in ><tests/php71/>DumperTraces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>');
Assert::same(Assert::normalize(d(4, 0, [])), '<^--- in ><tests/php71/>DumperTraces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><50>');


findExpression:
// simple literals
Assert::same(Dumper::getExpression("rd(null);"), null);
Assert::same(Dumper::getExpression("rd(false);"), null);
Assert::same(Dumper::getExpression("rd(true);"), null);
Assert::same(Dumper::getExpression("rd(123);"), null);
Assert::same(Dumper::getExpression("rd(-12);"), null);
Assert::same(Dumper::getExpression("rd(1.2);"), null);
Assert::same(Dumper::getExpression("rd(NAN);"), null);
Assert::same(Dumper::getExpression("rd(INF);"), null);
Assert::same(Dumper::getExpression("rd(-INF);"), null);
Assert::same(Dumper::getExpression('rd("foo");'), null);
Assert::same(Dumper::getExpression("rd('foo');"), null);

// expressions
Assert::same(Dumper::getExpression("rd(\$last !== false ? \$last->patch : null)"), '$last !== false ? $last->patch : null');
Assert::same(Dumper::getExpression("rd(\$last !== false ? \$last->patch : null, 3)"), '$last !== false ? $last->patch : null');

// arrays
Assert::same(Dumper::getExpression("rd([1, 2]);"), '[1, 2]');
Assert::same(Dumper::getExpression("rd([1, 2], 3);"), '[1, 2]');
Assert::same(Dumper::getExpression("rd([1, 2] + [1, 2], 3);"), '[1, 2] + [1, 2]');

// calls
Assert::same(Dumper::getExpression("rd(foo(1, 2), 3);"), 'foo(1, 2)');
Assert::same(Dumper::getExpression("rd(foo(1, 2) + bar(1, 2), 3);"), 'foo(1, 2) + bar(1, 2)');

// variables
Assert::same(Dumper::getExpression('rd($foo);'), '$foo');
Assert::same(Dumper::getExpression('rd($foo->bar);'), '$foo->bar');
Assert::same(Dumper::getExpression('rd($foo->bar());'), '$foo->bar()');

// class variables
Assert::same(Dumper::getExpression('rd(Foo::$bar);'), 'Foo::$bar');
Assert::same(Dumper::getExpression('rd(Foo::bar());'), 'Foo::bar()');

// callbacks
Assert::same(Dumper::getExpression("rd([Foo::class, 'bar']);"), "[Foo::class, 'bar']");
Assert::same(Dumper::getExpression("rd([\$foo, 'bar']);"), "[\$foo, 'bar']");

// dump with trace
Assert::same(Assert::normalize(a(0)), '<literal>: <true>');
Assert::same(Assert::normalize(a(1)), '<literal>: <true>
<^--- in ><tests/php71/>DumperTraces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>');
Assert::same(Assert::normalize(a(2)), '<literal>: <true>
<^--- in ><tests/php71/>DumperTraces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>');
Assert::same(Assert::normalize(a(3)), '<literal>: <true>
<^--- in ><tests/php71/>DumperTraces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>');
Assert::same(Assert::normalize(a(4)), '<literal>: <true>
<^--- in ><tests/php71/>DumperTraces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>
<^--- in ><tests/php71/>DumperTraces.phpt<:><107>');
