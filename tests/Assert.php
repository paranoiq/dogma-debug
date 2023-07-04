<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Dogma\Tester\Assert as DogmaAssert;
use function preg_replace;

class Assert extends DogmaAssert
{

    /** @var bool */
    public static $dump = true;

    /**
     * @param mixed $value
     */
    public static function dump($value, ?string $expected): void
    {
        if (self::$dump) {
            rd($value);
        }

        if ($expected === null) {
            parent::truthy(Dumper::dump($value, null, 0));

            return;
        }

        $result = Dumper::dump($value, null, 0);

        $result = self::normalize($result);

        parent::same($result, $expected);
    }

    public static function normalize(string $string): string
    {
        // replace ansi markers with "<" (start) and ">" (back to default)
        $string = preg_replace('/\\x1B\\[0;37m/U', '>', $string);
        $string = preg_replace('/\\x1B\\[[^m]+m/U', '<', $string);

        // replace object and resource ids
        $string = preg_replace('/#[0-9a-f]{4}/', '#?id', $string);
        $string = preg_replace('/#[0-9]+(?![0-9a-fA-F])/', '#?id', $string);

        // replace file paths
        $string = preg_replace('~<[^>]+><([A-Za-z]+(\\.[a-z]+)?\\.phpt?)><:>~U', '<?path><\\1><:>', $string);
        $string = preg_replace('~"[^"]+([A-Za-z]+(\\.[a-z]+)?\\.phpt?)">; <// [0-9]+ B>~U', '"?path\\1">; <// ?bytes B>', $string);
        $string = preg_replace('~"[^"]+\\.tmp">~', '"?path?file">', $string);
        $string = preg_replace('~<// [0-9]+ B, [</:A-Za-z0-9]+?\\.tmp>~', '<// ?bytes B, ?path?file>', $string);
        $string = preg_replace('~[/:A-Za-z0-9-]+\\.php:\\d+~', '?path?file:?line', $string);

        $string = preg_replace('~\\(stream ([0-9]+)\\)~', '(stream ?id)', $string);

        return $string;
    }

}
