<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Dogma\Tester\Assert as DogmaAssert;
use function class_exists;
use function is_string;
use function preg_replace;

class Assert extends DogmaAssert
{

    public static $dump = false;

    public static function dump($value, ?string $expected, ?string $key = null): void
    {
        if (self::$dump) {
            rd($value);
        }

        if ($expected === null) {
            parent::truthy(Dumper::dumpValue($value));

            return;
        }

        if (is_string($value) && class_exists($value)) {
            $string = Dumper::dumpClass($value);
        } else {
            $string = Dumper::dumpValue($value, 0, $key);
        }

        $string = self::normalize($string);

        parent::same($string, $expected);
    }

    public static function normalize(string $string): string
    {
        // replace ansi markers with "<>"
        $string = preg_replace('/\\x1B\\[0m/U', '>', $string);
        $string = preg_replace('/\\x1B\\[[^m]+m/U', '<', $string);

        // replace object and resource ids
        $string = preg_replace('/#[0-9a-f]+/', '#?id', $string);

        // replace file paths
        $string = preg_replace('~<[^>]+><([A-Za-z]+(\\.[a-z]+)?\\.phpt?)><:>~U', '<?path><\\1><:>', $string);
        $string = preg_replace('~"[^"]+([A-Za-z]+(\\.[a-z]+)?\\.phpt?)"><;> <// [0-9]+ B>~U', '"?path\\1"><;> <// ?bytes B>', $string);
        $string = preg_replace('~"[^"]+([A-Za-z]+\\.[a-z]+)?\\.tmp"><;> <// [0-9]+ B>~U', '"?path?file"><;> <// ?bytes B>', $string);

        return $string;
    }

}
