<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

readonly class ReadonlyClass
{
    public int $a;
    protected int $b;
    private int $c;

    public function __construct()
    {
        $this->a = 1;
        $this->b = 2;
        $this->c = 3;
    }

    public function f(): int
    {
        return $this->a + $this->b + $this->c + $this->e + $this->f;
    }

}

$readonly = new ReadonlyClass();

Dumper::$propertyOrder = Dumper::ORDER_ORIGINAL;

// todo: implement <readonly>
Assert::dump($readonly, '<$readonly>: <Dogma><\><Tests><\><Debug><\><ReadonlyClass> <{> <// #?id>
    <public> <$a> = <1>;
    <protected> <$b> = <2>;
    <private> <$c> = <3>;
<}>');
