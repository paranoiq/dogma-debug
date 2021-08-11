<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

function a(int $traceLength): string
{
    return b($traceLength);
}

function b(int $traceLength): string
{
    return c($traceLength);
}

function c(int $traceLength): string
{
    return Dumper::dump(true, 1, $traceLength);
}


formatTrace:
Assert::same(Assert::normalize(a(0)), 'literal<:> <true>
');
Assert::same(Assert::normalize(a(1)), 'literal<:> <true>
<^--- in ><?path><Dumper.helpers.phpt><:><22> </> <Dogma><\><Tests><\><Debug><\><c><()>
');
Assert::same(Assert::normalize(a(2)), 'literal<:> <true>
<^--- in ><?path><Dumper.helpers.phpt><:><22> </> <Dogma><\><Tests><\><Debug><\><c><()>
<^--- in ><?path><Dumper.helpers.phpt><:><17> </> <Dogma><\><Tests><\><Debug><\><b><()>
');
Assert::same(Assert::normalize(a(3)), 'literal<:> <true>
<^--- in ><?path><Dumper.helpers.phpt><:><22> </> <Dogma><\><Tests><\><Debug><\><c><()>
<^--- in ><?path><Dumper.helpers.phpt><:><17> </> <Dogma><\><Tests><\><Debug><\><b><()>
<^--- in ><?path><Dumper.helpers.phpt><:><12> </> <Dogma><\><Tests><\><Debug><\><a><()>
');
Assert::same(Assert::normalize(a(4)), 'literal<:> <true>
<^--- in ><?path><Dumper.helpers.phpt><:><22> </> <Dogma><\><Tests><\><Debug><\><c><()>
<^--- in ><?path><Dumper.helpers.phpt><:><17> </> <Dogma><\><Tests><\><Debug><\><b><()>
<^--- in ><?path><Dumper.helpers.phpt><:><12> </> <Dogma><\><Tests><\><Debug><\><a><()>
<^--- in ><?path><Dumper.helpers.phpt><:><41>
');


findExpression:
Assert::same(Dumper::findExpression("rd(null);"), null);
Assert::same(Dumper::findExpression("rd(false);"), null);
Assert::same(Dumper::findExpression("rd(true);"), null);
Assert::same(Dumper::findExpression("rd(123);"), null);
Assert::same(Dumper::findExpression("rd(-12);"), null);
Assert::same(Dumper::findExpression("rd(1.2);"), null);
Assert::same(Dumper::findExpression("rd(NAN);"), null);
Assert::same(Dumper::findExpression("rd(INF);"), null);
Assert::same(Dumper::findExpression("rd(-INF);"), null);
Assert::same(Dumper::findExpression('rd("foo");'), null);
Assert::same(Dumper::findExpression("rd('foo');"), null);

Assert::same(Dumper::findExpression("rd([1, 2]);"), '[1, 2]');
Assert::same(Dumper::findExpression("rd([1, 2], 3);"), '[1, 2]');
Assert::same(Dumper::findExpression("rd([1, 2] + [1, 2], 3);"), '[1, 2] + [1, 2]');

Assert::same(Dumper::findExpression("rd(foo(1, 2), 3);"), 'foo(1, 2)');
Assert::same(Dumper::findExpression("rd(foo(1, 2) + bar(1, 2), 3);"), 'foo(1, 2) + bar(1, 2)');

Assert::same(Dumper::findExpression('rd($foo);'), '$foo');
Assert::same(Dumper::findExpression('rd($foo->bar);'), '$foo->bar');
Assert::same(Dumper::findExpression('rd($foo->bar());'), '$foo->bar()');
Assert::same(Dumper::findExpression('rd(Foo::$bar);'), 'Foo::$bar');
Assert::same(Dumper::findExpression('rd(Foo::bar());'), 'Foo::bar()');

Assert::same(Dumper::findExpression("rd([Foo::class, 'bar']);"), "[Foo::class, 'bar']");
Assert::same(Dumper::findExpression("rd([\$foo, 'bar']);"), "[\$foo, 'bar']");
