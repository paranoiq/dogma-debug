<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

class ReadonlyProperties
{
    public int $a = 1;
    protected int $b = 2;
    private int $c = 3;
    public readonly int $d;
    protected readonly int $e;
    private readonly int $f;

    public function __construct()
    {
        $this->d = 4;
        $this->e = 5;
        $this->f = 6;
    }

    public function f(): int
    {
        return $this->a + $this->b + $this->c + $this->e + $this->f;
    }

}

$readonly = new ReadonlyProperties();

Dumper::$config->propertyOrder = Dumper::ORDER_ORIGINAL;

// todo: implement <readonly>
Assert::dump($readonly, '<$readonly>: <Dogma><\><Tests><\><Debug><\><ReadonlyProperties> <{> <// #?id>
    <public> <$a> = <1>;
    <protected> <$b> = <2>;
    <private> <$c> = <3>;
    <public> <$d> = <4>;
    <protected> <$e> = <5>;
    <private> <$f> = <6>;
<}>');
