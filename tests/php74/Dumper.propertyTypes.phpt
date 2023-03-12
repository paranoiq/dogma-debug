<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;

require_once __DIR__ . '/../bootstrap.php';

class PropertyTypes
{
    public int $a = 1;
    protected int $b = 2;
    private int $c = 3;
    public int $d;
    protected int $e;
    private int $f;

    public function f(): int
    {
        return $this->a + $this->b + $this->c + $this->e + $this->f;
    }

}

$types = new PropertyTypes();

Dumper::$propertyOrder = Dumper::ORDER_ORIGINAL;
Assert::dump($types, '<$types>: <Dogma><\><Tests><\><Debug><\><PropertyTypes> <{> <// #?id>
   <public> <$a> = <1>;
   <protected> <$b> = <2>;
   <private> <$c> = <3>;
   <public> <$d> = <uninitialized>;
   <protected> <$e> = <uninitialized>;
   <private> <$f> = <uninitialized>;
<}>');


class PropertyTypesA
{
    public int $a;
    private int $b;
    private int $c = 1;
}

class PropertyTypesB extends PropertyTypesA
{
    public int $d;
    private int $e;
    private int $f = 2;
}

$types = new PropertyTypesB();

Dumper::$propertyOrder = Dumper::ORDER_ALPHABETIC;
Assert::dump($types, '<$types>: <Dogma><\><Tests><\><Debug><\><PropertyTypesB> <{> <// #?id>
   <public> <$a> = <uninitialized>;
   <private> <Dogma><\><Tests><\><Debug><\><PropertyTypesA>::<$b> = <uninitialized>;
   <private> <Dogma><\><Tests><\><Debug><\><PropertyTypesA>::<$c> = <1>;
   <public> <$d> = <uninitialized>;
   <private> <$e> = <uninitialized>;
   <private> <$f> = <2>;
<}>');
