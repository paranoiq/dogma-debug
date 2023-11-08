<?php

namespace Dogma\Debug;

use Closure;
use Countable;
use Dogma\Equalable;
use Exception;
use SplObjectStorage;
use Tester\Assert as NetteAssert;
use Tester\Expect;
use Throwable;
use function abs;
use function array_keys;
use function current;
use function get_class;
use function is_array;
use function is_finite;
use function is_float;
use function is_object;
use function ksort;
use function max;
use function next;
use function preg_replace;
use const SORT_STRING;

class Assert
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
            self::truthy(Dumper::dump($value, null, 0));

            return;
        }

        $result = Dumper::dump($value, null, 0);

        $result = self::normalize($result);

        self::same($result, $expected);
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
        // (win)
        $string = preg_replace('~"[^"]+\\.tmp">~', '"?path?file">', $string);
        $string = preg_replace('~<// [0-9]+ B, [</:A-Za-z0-9]+?\\.tmp>~', '<// ?bytes B, ?path?file>', $string);
        // (lin)
        $string = preg_replace('~"/tmp/[^"]+">~', '"?path?file">', $string);
        $string = preg_replace('~<\\$uri> = <"\\?path\\?file">; <// [0-9]+ B>~', '<$uri> = <"?path?file">; <// ?bytes B>', $string);

        $string = preg_replace('~[/:A-Za-z0-9-]+\\.php:\\d+~', '?path?file:?line', $string);

        $string = preg_replace('~\\(stream ([0-9]+)\\)~', '(stream ?id)', $string);

        return $string;
    }

    // from dogma-dev Assert -------------------------------------------------------------------------------------------

    private const EPSILON = 1e-10;

    /**
     * @param mixed $actual
     * @param mixed $expected
     */
    public static function same($actual, $expected, ?string $description = null): void
    {
        NetteAssert::same($expected, $actual, $description);
    }

    /**
     * @param mixed $actual
     * @param mixed $expected
     */
    public static function notSame($actual, $expected, ?string $description = null): void
    {
        NetteAssert::notSame($expected, $actual, $description);
    }

    /**
     * Added support for comparing object with Equalable interface
     * @param mixed $actual
     * @param mixed $expected
     */
    public static function equal($actual, $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && get_class($actual) === get_class($expected)) {
            NetteAssert::$counter++;
            if (!$actual->equals($expected)) {
                self::fail(self::describe('%1 should be equal to %2', $description), $expected, $actual);
            }
        } else {
            NetteAssert::$counter++;
            if (!self::isEqual($expected, $actual)) {
                self::fail(self::describe('%1 should be equal to %2', $description), $expected, $actual);
            }
        }
    }

    /**
     * Added support for comparing object with Equalable interface
     * @param mixed $actual
     * @param mixed $expected
     */
    public static function notEqual($actual, $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && get_class($actual) === get_class($expected)) {
            NetteAssert::$counter++;
            if ($actual->equals($expected)) {
                self::fail(self::describe('%1 should not be equal to %2', $description), $expected, $actual);
            }
        } else {
            NetteAssert::$counter++;
            if (self::isEqual($expected, $actual)) {
                self::fail(self::describe('%1 should not be equal to %2', $description), $expected, $actual);
            }
        }
    }

    /**
     * @param mixed[]|string $haystack
     * @param mixed $needle
     */
    public static function contains($haystack, $needle, ?string $description = null): void
    {
        NetteAssert::contains($needle, $haystack, $description);
    }

    /**
     * @param mixed[]|string $haystack
     * @param mixed $needle
     */
    public static function notContains($haystack, $needle, ?string $description = null): void
    {
        NetteAssert::notContains($needle, $haystack, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function true($actual, ?string $description = null): void
    {
        NetteAssert::true($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function false($actual, ?string $description = null): void
    {
        NetteAssert::false($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function null($actual, ?string $description = null): void
    {
        NetteAssert::null($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function nan($actual, ?string $description = null): void
    {
        NetteAssert::nan($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function truthy($actual, ?string $description = null): void
    {
        NetteAssert::truthy($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function falsey($actual, ?string $description = null): void
    {
        NetteAssert::falsey($actual, $description);
    }

    /**
     * @param mixed[]|Countable $actualValue
     */
    public static function count($actualValue, int $expectedCount, ?string $description = null): void
    {
        NetteAssert::count($expectedCount, $actualValue, $description);
    }

    /**
     * @param mixed $actualValue
     * @param string|object $expectedType
     */
    public static function type($actualValue, $expectedType, ?string $description = null): void
    {
        NetteAssert::type($expectedType, $actualValue, $description);
    }

    /**
     * @param mixed|int|null $code
     */
    public static function exception(callable $function, string $class, ?string $message = null, $code = null): ?Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    /**
     * @param mixed|int|null $code
     */
    public static function throws(callable $function, string $class, ?string $message = null, $code = null): ?Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    /**
     * @param int|string|mixed[] $expectedType
     */
    public static function error(callable $function, $expectedType, ?string $expectedMessage = null): ?Throwable
    {
        return NetteAssert::error($function, $expectedType, $expectedMessage);
    }

    public static function noError(callable $function): void
    {
        NetteAssert::error($function, []);
    }

    /**
     * @param mixed $actualValue
     */
    public static function match($actualValue, string $pattern, ?string $description = null): void
    {
        NetteAssert::match($pattern, $actualValue, $description);
    }

    /**
     * @param mixed $actualValue
     */
    public static function matchFile($actualValue, string $file, ?string $description = null): void
    {
        NetteAssert::matchFile($file, $actualValue, $description);
    }

    /**
     * @param mixed|null $actual
     * @param mixed|null $expected
     */
    public static function fail(string $message, $actual = null, $expected = null): void
    {
        NetteAssert::fail($message, $expected, $actual);
    }

    /**
     * Added support for comparing object with Equalable interface
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param SplObjectStorage<object, mixed>|null $objects
     * @internal
     */
    public static function isEqual($expected, $actual, int $level = 0, ?SplObjectStorage $objects = null): bool
    {
        switch (true) {
            case $level > 10:
                throw new Exception('Nesting level too deep or recursive dependency.');
            case $expected instanceof Expect:
                $expected($actual);

                return true;
            case is_float($expected) && is_float($actual) && is_finite($expected) && is_finite($actual):
                $diff = abs($expected - $actual);

                return ($diff < self::EPSILON) || ($diff / max(abs($expected), abs($actual)) < self::EPSILON);
            case is_object($expected) && is_object($actual) && get_class($expected) === get_class($actual):
                /* start */
                if ($expected instanceof Equalable && $actual instanceof Equalable) {
                    return $expected->equals($actual);
                }
                /* end */
                $objects = $objects ? clone $objects : new SplObjectStorage();
                if (isset($objects[$expected])) {
                    return $objects[$expected] === $actual;
                } elseif ($expected === $actual) {
                    return true;
                }

                $objects[$expected] = $actual;
                $objects[$actual] = $expected;
                $expected = (array) $expected;
                $actual = (array) $actual;
            // break omitted

            case is_array($expected) && is_array($actual):
                ksort($expected, SORT_STRING);
                ksort($actual, SORT_STRING);
                if (array_keys($expected) !== array_keys($actual)) {
                    return false;
                }

                foreach ($expected as $value) {
                    if (!self::isEqual($value, current($actual), $level + 1, $objects)) {
                        return false;
                    }

                    next($actual);
                }

                return true;
            default:
                return $expected === $actual;
        }
    }

    private static function describe(string $reason, ?string $description): string
    {
        return ($description ? $description . ': ' : '') . $reason;
    }

    /**
     * @param mixed $obj
     */
    public static function with($obj, Closure $closure): void
    {
        NetteAssert::with($obj, $closure);
    }

}
