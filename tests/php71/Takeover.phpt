<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Takeover;

require_once __DIR__ . '/../bootstrap.php';

Takeover::$logReplacements = false;


Takeover::register('bar', ['Foo', 'bar']);

Assert::same(Takeover::hack('echo bar();', 'f'), 'echo \Foo::bar();');
Assert::same(Takeover::hack('echo \bar();', 'f'), 'echo \Foo::bar();');
Assert::same(Takeover::hack('echo bar ();', 'f'), 'echo \Foo::bar ();');
Assert::same(Takeover::hack('echo bar/* xxx */();', 'f'), 'echo \Foo::bar/* xxx */();');
Assert::same(Takeover::hack('echo bar
();', 'f'), 'echo \Foo::bar
();');
Assert::same(Takeover::hack('echo bar // xxx
();', 'f'), 'echo \Foo::bar // xxx
();');
Assert::same(Takeover::hack('echo bar # xxx
();', 'f'), 'echo \Foo::bar # xxx
();');

Assert::same(Takeover::hack('echo $bar();', 'f'), 'echo $bar();');
Assert::same(Takeover::hack('echo foobar();', 'f'), 'echo foobar();');
Assert::same(Takeover::hack('echo foo\bar();', 'f'), 'echo foo\bar();');
Assert::same(Takeover::hack('echo Foo::bar();', 'f'), 'echo Foo::bar();');
Assert::same(Takeover::hack('echo $foo->bar();', 'f'), 'echo $foo->bar();');
Assert::same(Takeover::hack('function foo();', 'f'), 'function foo();');


Takeover::register('exit', ['Foo', 'bar']);

Assert::same(Takeover::hack('exit();', 'f'), '\Foo::bar();');
Assert::same(Takeover::hack('exit(1);', 'f'), '\Foo::bar(1);');
Assert::same(Takeover::hack('exit;', 'f'), '\Foo::bar();');
Assert::same(Takeover::hack('exit
;', 'f'), '\Foo::bar
();');
