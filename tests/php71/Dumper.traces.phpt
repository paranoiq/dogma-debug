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
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>');
Dumper::$traceLength = 2;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>');
Dumper::$traceLength = 3;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>');
Dumper::$traceLength = 4;
Assert::same(Assert::normalize(d(0, 0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <...> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <...> <)>
<^--- 1 in ><tests/php71/>Dumper.traces.phpt<:><61>');

// 1 level params with repeating
Dumper::$traceArgsDepth = 1;
Dumper::$traceLength = 1;
Assert::same(Assert::normalize(d(0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>');
Dumper::$traceLength = 2;
Assert::same(Assert::normalize(d(0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>');
Dumper::$traceLength = 3;
Assert::same(Assert::normalize(d(0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>');
Dumper::$traceLength = 4;
Assert::same(Assert::normalize(d(0, 0)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
<)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>
<^--- 1 in ><tests/php71/>Dumper.traces.phpt<:><87>');

// variadic params
Assert::same(Assert::normalize(d(0, 0, 1, 2, 3)), '<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><38> -- <Dogma><\><Tests><\><Debug><\>f<(>
   <$a> => <0>,
   <$b> => <0>,
   <...$c> => <[><1>, <2>, <3><]>, <// 3 items>
<)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><33> -- <Dogma><\><Tests><\><Debug><\>e<(> <^ same> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><28> -- <Dogma><\><Tests><\><Debug><\>d<(> <^ same> <)>
<^--- 1 in ><tests/php71/>Dumper.traces.phpt<:><96>');

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
<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>');
Assert::same(Assert::normalize(a(2)), '<literal>: <true>
<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>');
Assert::same(Assert::normalize(a(3)), '<literal>: <true>
<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>');
Assert::same(Assert::normalize(a(4)), '<literal>: <true>
<^--- 4 in ><tests/php71/>Dumper.traces.phpt<:><23> -- <Dogma><\><Tests><\><Debug><\>c<(> <...> <)>
<^--- 3 in ><tests/php71/>Dumper.traces.phpt<:><18> -- <Dogma><\><Tests><\><Debug><\>b<(> <...> <)>
<^--- 2 in ><tests/php71/>Dumper.traces.phpt<:><13> -- <Dogma><\><Tests><\><Debug><\>a<(> <...> <)>
<^--- 1 in ><tests/php71/>Dumper.traces.phpt<:><159>');


fromOutOfMemoryMessage:
Dumper::$trimPathPrefixes[] = '~^C:/http/sqlftw/~';
$callstack = Callstack::fromOutOfMemoryMessage('
Fatal error: Allowed memory size of 268435456 bytes exhausted (tried to allocate 4096 bytes) in C:\http\sqlftw\sqlftw\vendor\dogma\dogma\src\Enum\EnumSetMixin.php on line 58

Call Stack:
    0.0274    2861584   1. {main}() C:\http\sqlftw\sqlftw\tests\export-test.php:0
    0.0703    4313704   2. SqlFtw\Tests\Assert::validCommands() C:\http\sqlftw\sqlftw\tests\export-test.php:25
    0.0839    4892248   3. SqlFtw\Parser\Parser->parse() C:\http\sqlftw\sqlftw\tests\Assert.php:113
    0.1347    6693272   4. SqlFtw\Parser\Parser->parseTokenList() C:\http\sqlftw\sqlftw\sources\Parser\Parser.php:64
    0.1451    7090224   5. SqlFtw\Parser\Ddl\RoutineCommandsParser->parseCreateFunction() C:\http\sqlftw\sqlftw\sources\Parser\Parser.php:255
    0.1615    7825368   6. SqlFtw\Parser\Ddl\CompoundStatementParser->parseCompoundStatement() C:\http\sqlftw\sqlftw\sources\Parser\Ddl\RoutineCommandsParser.php:188
    0.1616    7825368   7. SqlFtw\Parser\Ddl\CompoundStatementParser->parseBlock() C:\http\sqlftw\sqlftw\sources\Parser\Ddl\CompoundStatementParser.php:94
    0.1616    7825368   8. SqlFtw\Parser\Ddl\CompoundStatementParser->parseStatementList() C:\http\sqlftw\sqlftw\sources\Parser\Ddl\CompoundStatementParser.php:179
    0.1617    7846496   9. SqlFtw\Parser\Ddl\CompoundStatementParser->parseStatement() C:\http\sqlftw\sqlftw\sources\Parser\Ddl\CompoundStatementParser.php:108
    0.1636    7873968  10. SqlFtw\Parser\ExpressionParser->parseExpression() C:\http\sqlftw\sqlftw\sources\Parser\Ddl\CompoundStatementParser.php:167
    0.1636    7874344  11. SqlFtw\Parser\ExpressionParser->parseBooleanPrimary() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:112
    0.1636    7874344  12. SqlFtw\Parser\ExpressionParser->parsePredicate() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:167
    0.1636    7939880  13. SqlFtw\Parser\ExpressionParser->parseBitExpression() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:205
    0.1636    7939880  14. SqlFtw\Parser\ExpressionParser->parseSimpleExpression() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:287
    0.1645    8059016  15. SqlFtw\Parser\ExpressionParser->parseCase() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:379
    1.5649  252536904  16. SqlFtw\Parser\ExpressionParser->parseExpression() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:496
    1.5691  253241592  17. SqlFtw\Parser\TokenList->hasKeyword() C:\http\sqlftw\sqlftw\sources\Parser\ExpressionParser.php:118
    1.5691  253241592  18. SqlFtw\Parser\TokenList->expectKeyword() C:\http\sqlftw\sqlftw\sources\Parser\TokenList.php:498
    1.5691  253255984  19. SqlFtw\Parser\UnexpectedTokenException->__construct() C:\http\sqlftw\sqlftw\sources\Parser\TokenList.php:479
    1.5691  253256304  20. Dogma\Arr::map() C:\http\sqlftw\sqlftw\sources\Parser\exceptions\UnexpectedTokenException.php:32
    1.5691  253256304  21. array_map() C:\http\sqlftw\sqlftw\vendor\dogma\dogma\src\common\lists\Arr.php:1143
    1.5691  253256360  22. SqlFtw\Parser\UnexpectedTokenException::SqlFtw\Parser\{closure:C:\http\sqlftw\sqlftw\sources\Parser\exceptions\UnexpectedTokenException.php:30-32}() C:\http\sqlftw\sqlftw\vendor\dogma\dogma\src\common\lists\Arr.php:1143
    1.5691  253256360  23. Dogma\Enum\IntSet::get() C:\http\sqlftw\sqlftw\sources\Parser\exceptions\UnexpectedTokenException.php:31
    1.5691  253256816  24. SqlFtw\Parser\TokenType->__construct() C:\http\sqlftw\sqlftw\vendor\dogma\dogma\src\Enum\IntSet.php:67
    1.5691  253257216  25. Dogma\Enum\IntSet::checkValues() C:\http\sqlftw\sqlftw\vendor\dogma\dogma\src\Enum\IntSet.php:57

');
Assert::same(
    Assert::normalize(Dumper::formatCallstack($callstack, 1000, null, 0, 50)),
    '<^--- 26 in ><vendor/dogma/dogma/src/Enum/>EnumSetMixin.php<:><51> -- <Dogma><\><Enum><\>IntSet::checkValues<(> <???> <)> <1.57 s> <242 MB>
<^--- 24 in ><sqlftw/vendor/dogma/dogma/src/Enum/>IntSet.php<:><57> -- <SqlFtw><\><Parser><\>TokenType::__construct<(> <???> <)> <1.57 s> <242 MB>
<^--- 23 in ><sqlftw/vendor/dogma/dogma/src/Enum/>IntSet.php<:><67> -- <Dogma><\><Enum><\>IntSet::get<(> <???> <)> <1.57 s> <242 MB>
<^--- 22 in ><sqlftw/sources/Parser/exceptions/>UnexpectedTokenException.php<:><31> -- <SqlFtw><\><Parser><\>UnexpectedTokenException::{closure:30-32}<(> <???> <)> <1.57 s> <242 MB>
<^--- 21 in ><sqlftw/vendor/dogma/dogma/src/common/lists/>Arr.php<:><1143> -- array_map<(> <???> <)> <1.57 s> <242 MB>
<^--- 20 in ><sqlftw/vendor/dogma/dogma/src/common/lists/>Arr.php<:><1143> -- <Dogma><\>Arr::map<(> <???> <)> <1.57 s> <242 MB>
<^--- 19 in ><sqlftw/sources/Parser/exceptions/>UnexpectedTokenException.php<:><32> -- <SqlFtw><\><Parser><\>UnexpectedTokenException::__construct<(> <???> <)> <1.57 s> <242 MB>
<^--- 18 in ><sqlftw/sources/Parser/>TokenList.php<:><479> -- <SqlFtw><\><Parser><\>TokenList::expectKeyword<(> <???> <)> <1.57 s> <242 MB>
<^--- 17 in ><sqlftw/sources/Parser/>TokenList.php<:><498> -- <SqlFtw><\><Parser><\>TokenList::hasKeyword<(> <???> <)> <1.57 s> <242 MB>
<^--- 16 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><118> -- <SqlFtw><\><Parser><\>ExpressionParser::parseExpression<(> <???> <)> <1.56 s> <241 MB>
<^--- 15 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><496> -- <SqlFtw><\><Parser><\>ExpressionParser::parseCase<(> <???> <)> <165 ms> <7.69 MB>
<^--- 14 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><379> -- <SqlFtw><\><Parser><\>ExpressionParser::parseSimpleExpression<(> <???> <)> <164 ms> <7.57 MB>
<^--- 13 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><287> -- <SqlFtw><\><Parser><\>ExpressionParser::parseBitExpression<(> <???> <)> <164 ms> <7.57 MB>
<^--- 12 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><205> -- <SqlFtw><\><Parser><\>ExpressionParser::parsePredicate<(> <???> <)> <164 ms> <7.51 MB>
<^--- 11 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><167> -- <SqlFtw><\><Parser><\>ExpressionParser::parseBooleanPrimary<(> <???> <)> <164 ms> <7.51 MB>
<^--- 10 in ><sqlftw/sources/Parser/>ExpressionParser.php<:><112> -- <SqlFtw><\><Parser><\>ExpressionParser::parseExpression<(> <???> <)> <164 ms> <7.51 MB>
<^--- 9 in ><sqlftw/sources/Parser/Ddl/>CompoundStatementParser.php<:><167> -- <SqlFtw><\><Parser><\><Ddl><\>CompoundStatementParser::parseStatement<(> <???> <)> <162 ms> <7.48 MB>
<^--- 8 in ><sqlftw/sources/Parser/Ddl/>CompoundStatementParser.php<:><108> -- <SqlFtw><\><Parser><\><Ddl><\>CompoundStatementParser::parseStatementList<(> <???> <)> <162 ms> <7.46 MB>
<^--- 7 in ><sqlftw/sources/Parser/Ddl/>CompoundStatementParser.php<:><179> -- <SqlFtw><\><Parser><\><Ddl><\>CompoundStatementParser::parseBlock<(> <???> <)> <162 ms> <7.46 MB>
<^--- 6 in ><sqlftw/sources/Parser/Ddl/>CompoundStatementParser.php<:><94> -- <SqlFtw><\><Parser><\><Ddl><\>CompoundStatementParser::parseCompoundStatement<(> <???> <)> <162 ms> <7.46 MB>
<^--- 5 in ><sqlftw/sources/Parser/Ddl/>RoutineCommandsParser.php<:><188> -- <SqlFtw><\><Parser><\><Ddl><\>RoutineCommandsParser::parseCreateFunction<(> <???> <)> <145 ms> <6.76 MB>
<^--- 4 in ><sqlftw/sources/Parser/>Parser.php<:><255> -- <SqlFtw><\><Parser><\>Parser::parseTokenList<(> <???> <)> <135 ms> <6.38 MB>
<^--- 3 in ><sqlftw/sources/Parser/>Parser.php<:><64> -- <SqlFtw><\><Parser><\>Parser::parse<(> <???> <)> <83.9 ms> <4.67 MB>
<^--- 2 in ><sqlftw/tests/>Assert.php<:><113> -- <SqlFtw><\><Tests><\>Assert::validCommands<(> <???> <)> <70.3 ms> <4.11 MB>
<^--- 1 in ><sqlftw/tests/>export-test.php<:><25> <27.4 ms> <2.73 MB>');

// uncomment for visual check
//Debugger::callstack(1000, null, 0, 50, $callstack);