<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

enum Suit
{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;
}

enum SuitInt : int
{
    case Hearts = 1;
    case Diamonds = 2;
    case Clubs = 3;
    case Spades = 4;
}

enum SuitString : string
{
    case Hearts = 'H';
    case Diamonds = 'D';
    case Clubs = 'C';
    case Spades = 'S';
}

$hearts = Suit::Hearts;
$heartsInt = SuitInt::Hearts;
$heartsString = SuitString::Hearts;


plain:
Dumper::$useFormatters = false;
Assert::dump($hearts, '<$hearts>: <Dogma><\><Tests><\><Debug><\><Suit> <{> <// #?id>
    <public> <$name> = <"Hearts">; <// 6 B>
<}>');
Assert::dump($heartsInt, '<$heartsInt>: <Dogma><\><Tests><\><Debug><\><SuitInt> <{> <// #?id>
    <public> <$name> = <"Hearts">; <// 6 B>
    <public> <$value> = <1>;
<}>');
Assert::dump($heartsString, '<$heartsString>: <Dogma><\><Tests><\><Debug><\><SuitString> <{> <// #?id>
    <public> <$name> = <"Hearts">; <// 6 B>
    <public> <$value> = <"H">;
<}>');


formatted:
Dumper::$useFormatters = true;
Assert::dump($hearts, '<$hearts>: <Dogma><\><Tests><\><Debug><\><Suit>::<Hearts>');
Assert::dump($heartsInt, '<$heartsInt>: <Dogma><\><Tests><\><Debug><\><SuitInt>::<Hearts><(><1><)>');
Assert::dump($heartsString, '<$heartsString>: <Dogma><\><Tests><\><Debug><\><SuitString>::<Hearts><(><"H"><)>');
