<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Attribute;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionExtension;
use ReflectionFiber;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionReference;
use ReflectionType;
use ReflectionUnionType;
use ReflectionZendExtension;
use Throwable;
use function array_filter;
use function array_keys;
use function array_map;
use function get_class;
use function implode;
use function in_array;
use function ini_get_all;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function strtolower;
use const PHP_VERSION_ID;

class FormattersReflection
{

    public const INI_PERMISSION_LEVELS = [
        1 => 'PHP_INI_USER',
        2 => 'PHP_INI_PERDIR',
        4 => 'PHP_INI_SYSTEM',
        7 => 'PHP_INI_ALL',
    ];

    public const ATTRIBUTE_TARGETS = [
        Attribute::TARGET_CLASS => 'class',
        Attribute::TARGET_FUNCTION => 'function',
        Attribute::TARGET_METHOD => 'method',
        Attribute::TARGET_PROPERTY => 'property',
        Attribute::TARGET_CLASS_CONSTANT => 'constant',
        Attribute::TARGET_PARAMETER => 'parameter',
    ];

    /** @var bool */
    public static $showInheritedItems = false;

    /** @var bool */
    public static $showTraitItems = true;

    public static function register(): void
    {
        Dumper::$objectFormatters[ReflectionExtension::class] = [self::class, 'dumpReflectionExtension'];
        Dumper::$objectFormatters[ReflectionZendExtension::class] = [self::class, 'dumpReflectionZendExtension'];

        Dumper::$objectFormatters[ReflectionObject::class] = [self::class, 'dumpReflectionObject'];
        Dumper::$objectFormatters[ReflectionEnum::class] = [self::class, 'dumpReflectionEnum'];
        Dumper::$objectFormatters[ReflectionClass::class] = [self::class, 'dumpReflectionClass'];

        Dumper::$objectFormatters[ReflectionClassConstant::class] = [self::class, 'dumpReflectionClassConstant'];
        //Dumper::$objectFormatters[ReflectionEnumUnitCase::class] = [self::class, 'dumpReflectionEnumUnitCase'];
        //Dumper::$objectFormatters[ReflectionEnumBackedCase::class] = [self::class, 'dumpReflectionEnumBackedCase'];

        Dumper::$objectFormatters[ReflectionMethod::class] = [self::class, 'dumpReflectionMethod'];
        Dumper::$objectFormatters[ReflectionFunction::class] = [self::class, 'dumpReflectionFunction'];
        //Dumper::$objectFormatters[ReflectionGenerator::class] = [self::class, 'dumpReflectionGenerator'];
        //Dumper::$objectFormatters[ReflectionFiber::class] = [self::class, 'dumpReflectionFiber'];

        Dumper::$objectFormatters[ReflectionProperty::class] = [self::class, 'dumpReflectionProperty'];
        Dumper::$objectFormatters[ReflectionParameter::class] = [self::class, 'dumpReflectionParameter'];

        Dumper::$objectFormatters[ReflectionType::class] = [self::class, 'dumpReflectionType'];

        Dumper::$objectFormatters[ReflectionReference::class] = [self::class, 'dumpReflectionReference'];

        Dumper::$objectFormatters[ReflectionAttribute::class] = [self::class, 'dumpReflectionAttribute'];
    }

    public static function dumpReflectionExtension(ReflectionExtension $extension, int $depth = 0): string
    {
        /** @var bool $persistent */
        $persistent = $extension->isPersistent(); // @phpstan-ignore-line returns bool!
        $type = $persistent ? ' (persistent)' : ($extension->isTemporary() ? ' (temporary)' : '');

        $result = Dumper::class(get_class($extension))
            . ' of ' . Dumper::value($extension->getName()) . ' ' . Dumper::value2($extension->getVersion()) . $type
            . ' ' . Dumper::bracket('{');

        $dependencies = $extension->getDependencies();
        $required = $optional = $conflicts = [];
        foreach ($dependencies as $name => $relation) {
            if ($relation === 'Required') {
                $required[] = Dumper::value($name);
            } elseif ($relation === 'Optional') {
                $optional[] = Dumper::value($name);
            } elseif ($relation === 'Conflicts') {
                $conflicts[] = Dumper::value($name);
            } else {
                throw new LogicException("Unknown dependency relation {$relation} for {$extension->getName()}'s dependency {$name}.");
            }
        }
        if ($required !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'required dependencies: ' . implode(', ', $required);
        }
        if ($optional !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'optional dependencies: ' . implode(', ', $optional);
        }
        if ($conflicts !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'conflicting dependencies: ' . implode(', ', $conflicts);
        }

        $iniValues = ini_get_all(strtolower($extension->getName())) ?: [];
        $iniEntries = $extension->getINIEntries();
        if ($iniEntries !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'ini entries: ' . Dumper::info('// local_value ; access (global_value)');
            foreach ($iniEntries as $entry => $vals) {
                $values = $iniValues[$entry];
                $access = [];
                foreach (self::INI_PERMISSION_LEVELS as $perm => $name) {
                    if (($values['access'] & $perm) === $perm) {
                        $access[] = $name;
                    }
                }
                if (in_array('PHP_INI_ALL', $access, true)) {
                    $access = ['PHP_INI_ALL'];
                } elseif ($access === []) {
                    $access = ['--'];
                }
                $global = $values['global_value'];
                if (is_string($global) && preg_match('~-?\d+~', $global) === 1) {
                    $global = (int) $global;
                } elseif (is_numeric($global)) {
                    $global = (float) $global;
                }
                $local = $values['local_value'];
                if (is_string($local) && preg_match('~-?\d+~', $local) === 1) {
                    $local = (int) $local;
                } elseif (is_numeric($local)) {
                    $local = (float) $local;
                }

                [$localValue, $localInfo] = Dumper::splitInfo(Dumper::dumpValue($local, $depth + 2));
                $globalValue = '';
                if ($global === $local) {
                    $globalValue = Dumper::dumpValue($global, $depth + 2);
                }

                $info = '; ' . ($localInfo !== '' ? $localInfo . ', ' : '')
                    . implode('|', $access)
                    . ($global === $local ? '' : ' (global: ' . $globalValue . ')');

                $result .= "\n" . Dumper::indent($depth + 2) . Dumper::key($entry, true) . ' = '
                    . $localValue . ' ' . Dumper::info($info);
            }
        }

        $constants = $extension->getConstants();
        if ($constants !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'constants: ';
            foreach ($constants as $name => $value) {
                $result .= "\n" . Dumper::indent($depth + 2) . Dumper::key($name, true) . ': ' . Dumper::dumpValue($value, $depth + 2);
            }
        }

        $functions = $extension->getFunctions();
        if ($functions !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'functions: ';
            foreach ($functions as $function) {
                $result .= "\n" . Dumper::indent($depth + 2) . self::dumpReflectionFunction($function, $depth + 2);
            }
        }

        $classes = $extension->getClasses();
        if ($classes !== []) {
            $result .= "\n" . Dumper::indent($depth + 1) . 'classes: ';
            foreach ($classes as $class) {
                $result .= "\n" . Dumper::indent($depth + 2) . self::dumpReflectionClass($class, $depth + 2);
            }
        }

        return $result . "\n" . Dumper::indent($depth) . Dumper::bracket('}');
    }

    public static function dumpReflectionZendExtension(ReflectionZendExtension $extension, int $depth = 0): string
    {
        return Dumper::class(get_class($extension))
            . ' of ' . Dumper::value($extension->getName()) . ' ' . Dumper::value2($extension->getVersion())
            . ' ' . $extension->getAuthor() . ' ' . $extension->getURL() . ' ' . $extension->getCopyright();
    }

    public static function dumpReflectionObject(ReflectionObject $ref, int $depth = 0): string
    {
        /// todo
        return self::dumpReflectionClass($ref, $depth);
    }

    public static function dumpReflectionEnum(ReflectionEnum $enum, int $depth = 0): string
    {
        return self::dumpReflectionClass($enum, $depth);
    }

    public static function dumpReflectionClass(ReflectionClass $class, int $depth = 0): string
    {
        $doc = self::formatDocComment($class->getDocComment() ?: '', $depth);
        $attrs = self::formatAttributes(PHP_VERSION_ID >= 80000 ? $class->getAttributes() : [], $depth);
        if ($attrs !== '') {
            $attrs .= "\n";
        }

        // todo: colors
        $abstract = $class->isAbstract() ? 'abstract ' : '';
        $final = $class->isFinal() ? 'final ' : '';
        $readonly = PHP_VERSION_ID >= 80200 && $class->isReadOnly() ? 'readonly ' : '';
        $type = $class->isInterface() ? 'interface' : ($class->isTrait() ? 'trait' : ($class instanceof ReflectionEnum ? 'enum' : 'class'));
        $name = Dumper::class($class->getName());

        $backingType = $backingTypeName = null;
        if ($class instanceof ReflectionEnum && $class->isBacked()) {
            /** @var ReflectionNamedType $backingType */
            $backingType = $class->getBackingType();
            $backingTypeName = $backingType->getName();
            $backingType = ' : ' . Dumper::type($backingTypeName);
        }

        $extends = $class->getParentClass();
        if ($extends !== false) {
            $extends = ' extends ' . Dumper::class($extends->getName());
        }

        $interfaces = $class->getInterfaces();
        $implements = null;
        if ($interfaces !== []) {
            $implements = ' implements ';
            foreach (array_keys($interfaces) as $i => $interface) {
                $implements .= ($i !== 0 ? ', ' : '') . Dumper::class($interface);
            }
        }

        $result = $depth === 0 ? Dumper::class(get_class($class)) . ' of ' : '';
        $result .= $depth === 0 ? "\n" . $doc . $attrs : $doc . $attrs;
        $result .= $abstract . $final . $readonly . $type . ' ' . $name . $backingType . $extends . $implements . ' ' . Dumper::bracket('{');

        if ($depth === 0) {
            if ($class->isInternal()) {
                $result .= Dumper::info(' // internal(' . $class->getExtensionName() . ')');
            } else {
                $file = $class->getFileName();
                if ($file !== false) {
                    $result .= Dumper::info(' // defined in ' . Dumper::fileLine($file, (int) $class->getStartLine()));
                }
            }
        }

        // isAnonymous()
        // isCloneable()

        $indent = Dumper::indent($depth + 1);

        $traits = $class->getTraits();
        if ($traits !== []) {
            // todo
            $traitAliases = $class->getTraitAliases();
            foreach ($traits as $trait) {
                $result .= "\n" . $indent . 'use ' . Dumper::class($trait->getName()) . ';';
            }
        }

        if ($class instanceof ReflectionEnum) {
            if ($traits !== []) {
                $result .= "\n" . $indent;
            }
            foreach ($class->getCases() as $case) {
                try {
                    $name = $case->getValue()->name;
                    $value = $case->getValue()->value;
                } catch (Throwable $e) {
                    // not implemented in BetterReflection
                    $name = '???';
                    $value = '???';
                }
                $case = $case instanceof ReflectionEnumUnitCase
                    ? 'case ' . $name . ';'
                    : 'case ' . $name . ' = ' . ($backingTypeName === 'int' ? Dumper::int($value) : Dumper::string($value)) . ';';
                $result .= "\n" . $indent . $case;
            }
        }

        $constants = $class->getReflectionConstants();
        $constants = array_filter($constants, static function (ReflectionClassConstant $constant) use ($class): bool {
            return $constant->getDeclaringClass()->getName() === $class->getName();
        });
        if ($constants !== []) {
            if ($traits !== [] || $class instanceof ReflectionEnum) {
                $result .= "\n" . $indent;
            }
            foreach ($constants as $constant) {
                $result .= "\n" . $indent . self::dumpReflectionClassConstant($constant, $depth + 1);
            }
        }

        $properties = $class->getProperties();
        $staticProperties = array_filter($properties, static function (ReflectionProperty $property) use ($class): bool {
            return $property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName();
        });
        if ($staticProperties !== []) {
            if ($traits !== [] || $class instanceof ReflectionEnum || $constants !== []) {
                $result .= "\n" . $indent;
            }
            foreach ($staticProperties as $property) {
                $result .= "\n" . $indent . self::dumpReflectionProperty($property, $depth + 1);
            }
        }

        $instanceProperties = array_filter($properties, static function (ReflectionProperty $property) use ($class): bool {
            return !$property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName();
        });
        if ($instanceProperties !== []) {
            if ($traits !== [] || $class instanceof ReflectionEnum || $constants !== [] || $staticProperties !== []) {
                $result .= "\n" . $indent;
            }
            foreach ($instanceProperties as $property) {
                $result .= "\n" . $indent . self::dumpReflectionProperty($property, $depth + 1);
            }
        }

        $methods = $class->getMethods();
        $staticMethods = array_filter($methods, static function (ReflectionMethod $method) use ($class): bool {
            return $method->isStatic() && $method->getDeclaringClass()->getName() === $class->getName();
        });
        if ($staticMethods !== []) {
            if ($traits !== [] || $class instanceof ReflectionEnum || $constants !== [] || $staticProperties !== [] || $instanceProperties !== []) {
                $result .= "\n" . $indent;
            }
            foreach ($staticMethods as $method) {
                $result .= "\n" . $indent . self::dumpReflectionMethod($method, $depth + 1);
            }
        }

        $instanceMethods = array_filter($methods, static function (ReflectionMethod $method) use ($class): bool {
            return !$method->isStatic() && $method->getDeclaringClass()->getName() === $class->getName();
        });
        if ($instanceMethods !== []) {
            if ($traits !== [] || $class instanceof ReflectionEnum || $constants !== [] || $staticProperties !== [] || $instanceProperties !== [] || $staticMethods !== []) {
                $result .= "\n" . $indent;
            }
            foreach ($instanceMethods as $method) {
                $result .= "\n" . $indent . self::dumpReflectionMethod($method, $depth + 1);
            }
        }

        return $result . "\n" . Dumper::indent($depth) . Dumper::bracket('}');
    }

    public static function dumpReflectionClassConstant(ReflectionClassConstant $constant, int $depth = 0): string
    {
        $doc = self::formatDocComment($constant->getDocComment() ?: '', $depth);
        $attrs = self::formatAttributes(PHP_VERSION_ID >= 80000 ? $constant->getAttributes() : [], $depth);

        $result = $depth === 0 ? Dumper::class(get_class($constant)) . ' of ' . "\n" : '';

        // todo: colors
        $final = PHP_VERSION_ID > 80100 && $constant->isFinal() ? 'final ' : '';
        $access = $constant->isPrivate() ? 'private ' : ($constant->isProtected() ? 'protected ' : 'public ');
        $class = $depth === 0 ? Dumper::class($constant->getDeclaringClass()->getName()) . '::' : '';
        $name = Dumper::constant($constant->name);
        try {
            $value = Dumper::dumpValue($constant->getValue(), $depth + 1);
        } catch (Throwable $e) {
            // not implemented in better reflection
            $value = '?';
        }

        $result .= $doc . $attrs;
        $result .= $final . $access . 'const ' . $class . $name . ' = ' . $value . ';';

        return $result;
    }

    public static function dumpReflectionEnumUnitCase(ReflectionEnumUnitCase $case, int $depth = 0): string
    {
        ///
        return '';
    }

    public static function dumpReflectionEnumBackedCase(ReflectionEnumBackedCase $case, int $depth = 0): string
    {
        ///
        return '';
    }

    public static function dumpReflectionMethod(ReflectionMethod $method, int $depth = 0): string
    {
        // todo: colors
        $access = $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected');
        $name = $access . ' function ';
        if ($depth === 0) {
            $name .= Dumper::class($method->getDeclaringClass()->getName()) . ($method->isStatic() ? '::' : '->');
        }
        $name .= Dumper::function($method->getName());

        return self::dumpReflectionFunctionAbstract($method, $name, $depth);
    }

    public static function dumpReflectionFunction(ReflectionFunction $function, int $depth = 0): string
    {
        $name = Dumper::class($function->getName());

        return self::dumpReflectionFunctionAbstract($function, $name, $depth);
    }

    private static function dumpReflectionFunctionAbstract(ReflectionFunctionAbstract $function, string $name, int $depth = 0): string
    {
        $doc = self::formatDocComment($function->getDocComment() ?: '', $depth);
        $attrs = self::formatAttributes(PHP_VERSION_ID >= 80000 ? $function->getAttributes() : [], $depth);

        // todo: colors
        $generator = $function->isGenerator() ? 'generator ' : '';
        $closure = $function->isClosure() ? 'closure ' : '';

        $params = [];
        foreach ($function->getParameters() as $param) {
            $params[] = self::dumpReflectionParameter($param, $depth + 1);
        }
        $params = implode(', ', $params);

        $return = $function->hasReturnType() ? ': ' . self::formatType($function->getReturnType()) : '';
        if (PHP_VERSION_ID >= 80100) {
            // todo:
            $type = $function->getTentativeReturnType();
        }
        if ($function->returnsReference()) {
            $return = Dumper::reference('&') . $return;
        }

        $deprecated = $function->isDeprecated() ? ' ' . Dumper::exceptions('DEPRECATED') : '';
        //$disabled = $ref->isDisabled() ? ' ' . Dumper::exceptions('disabled') : ''; // deprecated

        $result = $depth === 0 ? Dumper::class(get_class($function)) . ' of ' : '';
        $result .= $depth === 0 ? "\n" . $doc . $attrs : $doc . $attrs;
        $result .= $generator . $closure . $name . Dumper::bracket('(') . $params . Dumper::bracket(')') . $return . $deprecated; // . $disabled

        if ($depth === 0) {
            if ($function->isInternal()) {
                $result .= Dumper::info(' // internal(' . $function->getExtensionName() . ')');
            } else {
                $file = $function->getFileName();
                if ($file !== false) {
                    $result .= Dumper::info(' // defined in ' . Dumper::fileLine($file, (int) $function->getStartLine()));
                }
            }
        }

        try {
            $vars = $function->getStaticVariables();
            // todo:
        } catch (Throwable $e) {
            // not implemented in BetterReflection
        }

        return $result;
    }

    public static function dumpReflectionGenerator(ReflectionGenerator $generator, int $depth = 0): string
    {
        ///
        return '';
    }

    public static function dumpReflectionFiber(ReflectionFiber $fiber, int $depth = 0): string
    {
        ///
        return '';
    }

    public static function dumpReflectionProperty(ReflectionProperty $property, int $depth = 0): string
    {
        $doc = self::formatDocComment($property->getDocComment() ?: '', $depth);
        $attrs = self::formatAttributes(PHP_VERSION_ID >= 80000 ? $property->getAttributes() : [], $depth);
        $type = PHP_VERSION_ID >= 70400 ? $property->getType() : null;
        if ($type !== null) {
            $type = self::formatType($type) . ' ';
        }

        $result = $depth === 0 ? Dumper::class(get_class($property)) . ' of ' . "\n" : '';

        // todo: colors
        $access = $property->isPrivate() ? 'private ' : ($property->isProtected() ? 'protected ' : 'public ');
        $static = $property->isStatic() ? 'static ' : '';
        $readonly = PHP_VERSION_ID >= 80100 && $property->isReadOnly() ? 'readonly ' : '';
        $class = $depth === 0 ? Dumper::class($property->getDeclaringClass()->getName()) . ($static ? '::' : '->') : '';
        $name = Dumper::property('$' . $property->name);
        $value = (PHP_VERSION_ID < 70400 || $type === null || ($property->isStatic() && $property->isInitialized()))
            ? Dumper::dumpValue($property->isStatic() ? $property->getValue() : (PHP_VERSION_ID >= 80000 ? $property->getDefaultValue() : null), $depth + 1)
            : '(undefined)';

        $result .= $doc . $attrs;
        $result .= $access . $static . $readonly . $type . $class . $name . ' = ' . $value . ';';

        $info = [];
        if ($property->isDefault()) {
            $info[] = 'default';
        }
        // todo: getDefaultValue()
        if (PHP_VERSION_ID >= 80000 && $property->isPromoted()) {
            $info[] = 'promoted';
        }
        if ($info !== []) {
            $result .= Dumper::info(' // ' . implode(', ', $info));
        }

        return $result;
    }

    public static function dumpReflectionParameter(ReflectionParameter $param, int $depth = 0): string
    {
        $result = $depth === 0 ? Dumper::class(get_class($param)) . ' of ' . "\n" : '';
        if ($depth === 0) {
            $class = $param->getDeclaringClass();
            $function = $param->getDeclaringFunction();
            $position = $param->getPosition();

            $result .= "parameter #{$position} of "
                . ($class !== null ? Dumper::class($class->getName()) . '::' : '')
                . Dumper::function($function->getName()) . "\n";
        }

        $attrs = self::formatAttributes(PHP_VERSION_ID >= 80000 ? $param->getAttributes() : [], $depth);
        if ($attrs !== '') {
            $result .= $attrs . "\n";
        }

        $promoted = PHP_VERSION_ID >= 80000 && $param->isPromoted();
        if ($promoted) {
            $property = null;
            $class = $param->getDeclaringClass();
            if ($class === null) {
                throw new LogicException('Parameter should have declaring class, when it is promoted to property.');
            }
            foreach ($class->getProperties() as $prop) {
                if ($prop->getName() === $param->getName()) {
                    $property = $prop;
                    break;
                }
            }
            if ($property === null) {
                throw new LogicException('Promoted property not found.');
            }
            $result .= $property->isPublic() ? 'public ' : ($property->isProtected() ? 'protected ' : 'private ');
        }

        $type = $param->getType();
        if ($type !== null) {
            $result .= self::formatType($type) . ' ';
        }

        if ($param->isPassedByReference()) {
            $result .= Dumper::reference('&');
        }

        if ($param->isVariadic()) {
            $result .= '...';
        }

        $result .= Dumper::parameter('$' . $param->getName());

        if ($param->isDefaultValueAvailable()) {
            if ($param->isDefaultValueConstant()) {
                $const = $param->getDefaultValueConstantName();
                $result .= ' = ' . Dumper::constant($const);
            } else {
                $default = $param->getDefaultValue();
                $result .= ' = ' . Dumper::dumpValue($default, $depth + 1);
            }
        }

        return $result;
    }

    public static function dumpReflectionType(ReflectionType $type, int $depth = 0): string
    {
        $result = $depth === 0 ? Dumper::class(get_class($type)) . ' of ' . "\n" : '';

        return $result . self::formatType($type);
    }

    public static function dumpReflectionReference(ReflectionReference $reference, int $depth = 0): string
    {
        return Dumper::class(get_class($reference)) . ' of ' . Dumper::value2('#' . $reference->getId());
    }

    public static function dumpReflectionAttribute(ReflectionAttribute $attribute, int $depth = 0): string
    {
        $t = $attribute->getTarget();
        $target = [];
        foreach (self::ATTRIBUTE_TARGETS as $value => $name) {
            if (($t & $value) !== 0) {
                $target[] = $name;
            }
        }
        $target = implode('|', $target);
        if ($target !== '') {
            $target = Dumper::value($target) . ' ';
        }
        $repeated = $attribute->isRepeated() ? Dumper::value('repeated') . ' ' : '';

        $result = $depth === 0 ? Dumper::class(get_class($attribute)) . ' of ' : '';

        return $result . $repeated . $target . 'attribute ' . self::formatAttributes([$attribute], $depth);
    }

    /**
     * @param ReflectionType|ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType $type
     */
    private static function formatType(ReflectionType $type, int $level = 0): string
    {
        if ($type instanceof ReflectionUnionType) {
            $result = implode(Dumper::operator('|'), array_map(static function (ReflectionType $t) use ($level): string {
                return self::formatType($t, $level + 1);
            }, $type->getTypes()));
        } elseif ($type instanceof ReflectionIntersectionType) {
            $result = implode(Dumper::operator('|'), array_map(static function (ReflectionType $t) use ($level): string {
                return self::formatType($t, $level + 1);
            }, $type->getTypes()));

            $result = $level === 0 ? $result : Dumper::bracket('(') . $result . Dumper::bracket(')');
        } elseif ($type instanceof ReflectionNamedType) {
            $result = $type->isBuiltin() ? Dumper::type($type->getName()) : Dumper::class($type->getName());
        } else {
            $result = '';
        }

        if ($type->allowsNull() && preg_match('~null\\e\\[[0-9;]+m~', $result) === 0) {
            $result = Dumper::type('?') . $result;
        }

        return $result;
    }

    /**
     * @param list<ReflectionAttribute> $attributes
     */
    private static function formatAttributes(array $attributes, int $depth): string
    {
        $attrs = [];
        foreach ($attributes as $attribute) {
            $args = [];
            foreach (PHP_VERSION_ID >= 80000 ? $attribute->getArguments() : [] as $name => $argument) {
                $args[] = Dumper::key($name) . ': ' . Dumper::dumpValue($argument, $depth + 1);
            }

            $args = $args !== []
                ? Dumper::bracket('(') . implode(', ', $args) . Dumper::bracket(')')
                : '';

            $attrs[] = Dumper::indent($depth) . Dumper::bracket('#[')
                . Dumper::nameDim(PHP_VERSION_ID >= 80000 ? $attribute->getName() : '') . $args . Dumper::bracket(']');
        }

        return $attrs !== []
            ? implode("\n", $attrs)
            : '';
    }

    private static function formatDocComment(string $comment, int $depth): string
    {
        if ($comment === '') {
            return $comment;
        }

        $comment = Ansi::color(preg_replace('~^\\s+~m', ' ', $comment), Dumper::$colors['doc']) . "\n";

        $comment = preg_replace_callback('~(?<=\\s)@\\S+~', static function (array $m): string {
            return Ansi::between($m[0], Dumper::$colors['annotation'], Dumper::$colors['doc']);
        }, $comment);

        $comment = preg_replace_callback('~\\$\\w+~', static function (array $m): string {
            return Ansi::between($m[0], Dumper::$colors['parameter'], Dumper::$colors['doc']);
        }, $comment);

        $comment = str_replace("\n", "\n" . Dumper::indent($depth), $comment);

        return $comment;
    }

}
