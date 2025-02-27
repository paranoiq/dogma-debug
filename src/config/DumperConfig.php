<?php

namespace Dogma\Debug;

use DateTimeZone;

/**
 * Unnamed params:
 * - int: maxDepth
 * - int, int: maxDepth, traceLength
 *
 * Shortcuts:
 * - a: maxArrayLength
 * - b: binaryEscaping
 * - c: colors
 * - d: maxDepth
 * - e: stringsEscaping
 * - ea: escapeAllNonAscii
 * - f: floatFormatting
 * - g: groupNullAndUninitialized
 * - h: hidden
 * - hex: binaryWithHexadecimal
 * - i: showInfo
 * - js: jsonStrings
 * - k: alwaysShowArrayKeys
 * - l: maxLength
 * - n: name
 * - o: propertyOrdering
 * - q: alwaysQuoteStringKeys
 * - s: dumpClassesWithStaticMethodVariables
 * - tz: infoTimeZone
 * - u: numbersWithUnderscore
 * - w: escapeWhitespace
 *
 * Trace shortcuts:
 * - t: traceLength
 * - ad: traceArgsDepth
 * - cd: traceCodeDepth
 * - cl: traceCodeLines
 * - tf: traceFilters
 */
class DumperConfig
{

    private const SHORTS = [
        'a' => 'maxArrayLength',
        'b' => 'binaryEscaping',
        'c' => 'colors',
        'd' => 'maxDepth',
        'e' => 'stringsEscaping',
        'ea' => 'escapeAllNonAscii',
        'f' => 'floatFormatting',
        'g' => 'groupNullAndUninitialized',
        'h' => 'hidden',
        'hex' => 'binaryWithHexadecimal',
        'i' => 'showInfo',
        'js' => 'jsonStrings',
        'k' => 'alwaysShowArrayKeys',
        'l' => 'maxLength',
        'o' => 'propertyOrdering',
        'n' => 'name',
        'q' => 'alwaysQuoteStringKeys',
        's' => 'dumpStaticVariablesOfMethods',
        'tz' => 'infoTimeZone',
        'u' => 'numbersWithUnderscore',
        'w' => 'escapeWhitespace',
        't' => 'traceLength',
        'tf' => 'traceFilters',
        'ad' => 'traceArgsDepth',
        'cd' => 'traceCodeDepth',
        'cl' => 'traceCodeLines',
    ];

    public function update(array $params): self
    {
        $that = clone $this;
        foreach ($params as $key => $value) {
            if ($value instanceof self) {
                return $that;
            } elseif ($key === 0) {
                $that->maxDepth = $value;
            } elseif ($key === 1) {
                $that->traceLength = $value;
            } elseif (isset(self::SHORTS[$key])) {
                $property = self::SHORTS[$key];
                $that->$property = $value;
            } else {
                $that->$key = $value;
            }
        }

        return $that;
    }

    /** @var string|null - dump name */
    public $name = null;

    /** @var bool - colorize var_dump() output */
    public $colors = true;

    // common settings -------------------------------------------------------------------------------------------------

    /** @var bool - prefix dumps with expression being dumped (e.g. "$foo->bar(): [1, 2, 3, 4, 5, 6] // 6 items") */
    public $dumpExpressions = true;

    /** @var bool - show indentation line */
    public $indentLines = true;

    /** @var int - count of spaces for each indentation level (including indentation lines) */
    public $indentSpaces = 4;

    /** @var int - max depth of dumped structures [d] */
    public $maxDepth = 3;

    /** @var string[] - list of fields that are hidden from dumps [h] */
    public $hiddenFields = [];

    // numeric settings ------------------------------------------------------------------------------------------------

    /** @var bool|null - render long integers and floats with "_" dividing digits into groups of 3, null for auto on PHP >= 7.4 [u] */
    public $numbersWithUnderscore = false;

    /** @var int - how floats will be rendered (decimals or scientific notation); does not affect precision [f] */
    public $floatFormatting = Dumper::FLOATS_SCIENTIFIC_3;

    // string settings -------------------------------------------------------------------------------------------------

    /** @var int - max length of dumped strings [l] */
    public $maxLength = 10000;

    /** @var string - encoding of dumped strings (todo: output encoding should be always utf-8) */
    public $inputEncoding = 'utf-8';

    /** @var string - string escaping for strings without control characters (allowed only \n, \r, \t etc.) [e] */
    public $stringsEscaping = Dumper::ESCAPING_PHP;

    /** @var string - string escaping for binary strings containing control characters (except \n, \r, \t etc.) [b] */
    public $binaryEscaping = Dumper::ESCAPING_CP437;

    /** @var string - string escaping for labels and raw output (only escapes control characters) */
    public $rawEscaping = Dumper::ESCAPING_CP437;

    /** @var bool - whether to escape \n, \r, \t or keep them as they are (not relevant for ESCAPING_CP437) [w] */
    public $escapeWhiteSpace = true;

    /** @var bool - escape all unicode characters outside ascii (not relevant for ESCAPING_SQL and ESCAPING_CP437) [ea] */
    public $escapeAllNonAscii = false;

    /** @var bool - dump binary strings with hexadecimal representation along [hex] */
    public $binaryWithHexadecimal = true;

    /** @var int|null - length of binary string chunks (rows) */
    public $binaryChunkLength = 16;

    /** @var int - how to format strings detected as valid JSON [js] */
    public $jsonStrings = Dumper::JSON_PRETTIFY;

    /** @var int - length from which JSON strings are reformatted */
    public $jsonPrettifyMinLength = 100;

    // array settings --------------------------------------------------------------------------------------------------

    /** @var int - max items shown in an array dump [a] */
    public $arrayMaxLength = 100;

    /** @var int - max length or array formatted to a single line */
    public $shortArrayMaxLength = 100;

    /** @var int - max items in an array to format it on single line */
    public $shortArrayMaxItems = 20;

    /** @var bool - show array keys even on lists with sequential indexes [k] */
    public $alwaysShowArrayKeys = false;

    /** @var bool - always format string keys as strings [q] */
    public $alwaysQuoteStringKeys = false;

    // object settings -------------------------------------------------------------------------------------------------

    /** @var bool - show hash uniquely identifying each object */
    public $showObjectHashes = true;

    /** @var int - ordering of dumped properties of objects [o] */
    public $propertyOrder = Dumper::ORDER_VISIBILITY_ALPHABETIC;

    /** @var bool - show uninitialized typed properties (since 7.4) */
    public $showUninitializedProperties = true;

    /** @var bool - show flag for dynamically created properties */
    //public $showDynamicPropertiesFlag = false;

    /** @var bool - show property types (since 7.4) */
    //public $showPropertyTypes = false;

    /** @var bool - show undefined typed properties (since 7.4) */
    //public $showUndefinedProperties = false;

    /** @var bool - show readonly flag on properties (since 8.1) and classes (since 8.2) */
    //public $showReadonlyPropertiesFlag = false;

    /** @var bool - group null and undefined properties of object together [g] */
    public $groupNullAndUninitialized = true;

    /** @var string[] (regexp $long => replacement $short) - replacements of namespaces for shorter class names in dumps */
    public $namespaceReplacements = [];

    /** @var bool - dump static variables from methods when dumping static members of class (e.g. `rd(Foo::class)`) [s] */
    public $dumpStaticVariablesOfMethods = false;

    /** @var bool - show contents of seekable streams (open files, php:// etc.; not usable for pipes etc.) by reading from start and then rewinding to original position */
    public $dumpContentsOfSeekableStreams = false;

    //public $dumpContentsOfSeekableIterators = false;

    // info settings ---------------------------------------------------------------------------------------------------

    /** @var bool|null - show comments with additional info (readable values, object hashes, hints...) [i] */
    public $showInfo = true;

    /** @var int - show length of strings from n characters */
    public $lengthInfoMin = 6;

    /** @var DateTimeZone|string|null - timezone used to format timestamps to readable date/time (php.ini timezone by default) [tz] */
    public $infoTimeZone;

    /** @var bool - calculate total memory size of big structures (even below $maxDepth) */
    //public $infoSize = false;

    // backtrace settings ----------------------------------------------------------------------------------------------

    /** @var bool - show class, method, arguments in backtrace */
    public $traceDetails = true;

    /** @var bool - show depth of each callstack frame */
    public $traceNumbered = true;

    /** @var int - number of trace lines below a dump [t] */
    public $traceLength = 1;

    /** @var int - depth of dumped arguments of called function in backtrace [ad] */
    public $traceArgsDepth = 0;

    /** @var int - count of lines of code shown for each filtered stack frame [cl] */
    public $traceCodeLines = 5;

    /** @var int - count of stack frames for which code should be shown [cd] */
    public $traceCodeDepth = 0;

    /** @var string[] - functions, classes and methods skipped from backtrace [tf] */
    public $traceFilters = [
        '~^Dogma\\\\Debug\\\\~', // Debugger classes
        '~^(ld|rd|rc|rb|rf|rl|rt)$~', // shortcut functions
        '~^call_user_func(_array)?$~', // call proxies
        '~^forward_static_call(_array)?$~',
        '~^(trigger|user)_error$~', // error proxies
        '~^Composer\\\\Autoload\\\\~', // composer loaders
        '~^loadClass~',
        '~^preg_match$~', // thrown from inside
    ];

    /** @var string[] - common path prefixes to remove from all paths (regexps) */
    public $trimPathPrefixes = [];

}
