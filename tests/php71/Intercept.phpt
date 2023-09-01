<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Intercept;

require_once __DIR__ . '/../bootstrap.php';

Intercept::$logReplacements = false;


Intercept::registerFunction('foo', 'bar', ['Foo', 'bar']);

Assert::same(Intercept::hack('echo bar();', 'f'), 'echo \Foo::bar();');
Assert::same(Intercept::hack('echo \bar();', 'f'), 'echo \Foo::bar();');
Assert::same(Intercept::hack('echo bar ();', 'f'), 'echo \Foo::bar ();');
Assert::same(Intercept::hack('echo bar/* xxx */();', 'f'), 'echo \Foo::bar/* xxx */();');
Assert::same(Intercept::hack('echo bar
();', 'f'), 'echo \Foo::bar
();');
Assert::same(Intercept::hack('echo bar // xxx
();', 'f'), 'echo \Foo::bar // xxx
();');
Assert::same(Intercept::hack('echo bar # xxx
();', 'f'), 'echo \Foo::bar # xxx
();');

Assert::same(Intercept::hack('echo $bar();', 'f'), 'echo $bar();');
Assert::same(Intercept::hack('echo foobar();', 'f'), 'echo foobar();');
Assert::same(Intercept::hack('echo foo\bar();', 'f'), 'echo foo\bar();');
Assert::same(Intercept::hack('echo Foo::bar();', 'f'), 'echo Foo::bar();');
Assert::same(Intercept::hack('echo $foo->bar();', 'f'), 'echo $foo->bar();');
Assert::same(Intercept::hack('function foo();', 'f'), 'function foo();');


Intercept::registerFunction('foo', 'exit', ['Foo', 'bar']);

Assert::same(Intercept::hack('exit();', 'f'), '\Foo::bar();');
Assert::same(Intercept::hack('exit(1);', 'f'), '\Foo::bar(1);');
Assert::same(Intercept::hack('exit;', 'f'), '\Foo::bar();');
Assert::same(Intercept::hack('exit
;', 'f'), '\Foo::bar
();');
