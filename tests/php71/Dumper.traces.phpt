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

function d($a, $b, ...$c): string
{
    return e($a, $b, ...$c);
}

function e($a, $b, ...$c): string
{
    return f($a, $b, ...$c);
}

function f($a, $b, ...$c): string
{
    return Dumper::formatCallstack(Callstack::get());
}


formatCallstack:
Dumper::$traceLength = 0;
Dumper::$traceArgsDepth = 0;
Dumper::$traceCodeLines = 0;
Dumper::$traceCodeDepth = 0;

// no params
Assert::same(Assert::normalize(d(0, 0, 0)), '');

Dumper::$traceLength = 1;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>');
Dumper::$traceLength = 2;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>');
Dumper::$traceLength = 3;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>');
Dumper::$traceLength = 4;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><61>');

// 1 level params with repeating
Dumper::$traceArgsDepth = 1;
Dumper::$traceLength = 1;
Assert::same(Assert::normalize(d(0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>');
Dumper::$traceLength = 2;
Assert::same(Assert::normalize(d(0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>');
Dumper::$traceLength = 3;
Assert::same(Assert::normalize(d(0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>');
Dumper::$traceLength = 4;
Assert::same(Assert::normalize(d(0, 0)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><87>');

// variadic params
Assert::same(Assert::normalize(d(0, 0, 1, 2, 3)), '<^--- in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
   <...$c> => <[><1>, <2>, <3><]>, <// 3 items>
<)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><96>');

// todo: trace code


findExpression:
// simple literals
Assert::same(Dumper::getExpression("rd(null);"), true);
Assert::same(Dumper::getExpression("rd(false);"), true);
Assert::same(Dumper::getExpression("rd(true);"), true);
Assert::same(Dumper::getExpression("rd(123);"), true);
Assert::same(Dumper::getExpression("rd(-12);"), true);
Assert::same(Dumper::getExpression("rd(1.2);"), true);
Assert::same(Dumper::getExpression("rd(NAN);"), true);
Assert::same(Dumper::getExpression("rd(INF);"), true);
Assert::same(Dumper::getExpression("rd(-INF);"), true);
Assert::same(Dumper::getExpression('rd("foo");'), true);
Assert::same(Dumper::getExpression("rd('foo');"), true);

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
<^--- in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>');
Assert::same(Assert::normalize(a(2)), '<literal>: <true>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>');
Assert::same(Assert::normalize(a(3)), '<literal>: <true>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>');
Assert::same(Assert::normalize(a(4)), '<literal>: <true>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>
<^--- in ><tests/php71/>Dumper.traces.phpt<:><159>');
