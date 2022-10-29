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

Assert::dump($types, '<$readonly>: <Dogma><\><Tests><\><Debug><\><PropertyTypes> <{> <// #?id>
   <public> <$a> = <1>;
   <protected> <$b> = <2>;
   <private> <$c> = <3>;
   <public> <readonly> <$d> = <4>;
   <protected> <readonly> <$e> = <5>;
   <private> <readonly> <$f> = <6>;
<}>');
