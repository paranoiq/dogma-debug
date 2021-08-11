Dogma - debug
=============

Remote console dumper/debugger

- outputs beautifully formatted debugging data to another console on localhost
- Tracy/Symfony debug bar alternative for debugging CLI scripts, background tasks, many concurrent requests etc.


Requirements:
-------------

- **PHP 7.1+**
- **ext-sockets**


Installation:
-------------

Run `composer create-project dogma/dogma-debug`.


Usage:
------

1) Run `php src/debug-server.php` in some local console for displaying outputs (starts a socket server on port 6666).
2) Include `src/debug-client.php` in your code or use it in `auto_prepend_file` directive in your `php.ini`.

Functions:
- `d($value, ...)` - dump value in local console (where your app runs)
- `rd($value, ...);` - dump value to remote console (another console on localhost)
- `rc(callable, ...)` - capture and dump result of a callable to remote console
- `rf()` - print name of current function/method
- `rl($label)` - print label
- `t([$label])` - print time since request start or last `t()`

Dumper has a lot of features and configuration options to make dump outputs more compact, readable and usable - for example:
- rich information about dumped values (sizes, lengths, formatted time for timestamps, binary components of flag integers...)
- custom and built-in handlers for types
- custom and built-in handlers for types in single-line mode
- dumping static members of classes and functions (call as `rd(Foo::class)` or `rd([Foo::class, 'method'])`)
- dumping bind values in closures
- custom timer and measuring time and resources used in given request
- trimming known prefixes from file paths and namespaces
- single-line array dumps for arrays with few items
- configurable backtrace output for dumps
- custom color configuration
- debugging filesystem operations (work in progress) - use `FileStreamWrapper::enable()`


Dumper configuration:
---------------------

- `int Dumper::$maxDepth` - max depth of the structure to traverse (default `3`)
- `int Dumper::$maxLength` - max length of strings to output in characters (default `1000`)
- `int Dumper::$shortArraysMaxLength` - max length of short array output on single line - eg `[1, 2, 3]` (default `100`)
- `int Dumper::$shortArrayMaxItems` - max count of items for short array output on single line (default `6`)
- `string Dumper::$stringsEncoding` - encoding of dumped strings. output is always UTF-8 (default `'utf-8'`)
- `string[] Dumper::$hiddenFields` - names of fields hidden from output - eg `password` etc.
- `bool Dumper::$showInfo` - show extended info for dumped values - eg `"ÁČŘ" // 3 ch, 6 B`
- `string Dumper::$infoTimeZone` - timezone for readable time info formatted from dumped int/float timestamps
- `int Dumper::$propertyOrder` - ordering of properties in dumped objects. values: ORDER_ORIGINAL, ORDER_ALPHABETIC or ORDER_VISIBILITY_ALPHABETIC
- `array<string, string> Dumper::$namespaceReplacements` - map of regexps and replacements for shortening dumped class namespaces

- `int Dumper::$traceLength` - count of lines of backtrace for dumped values
- `bool Dumper::$traceDetails` - displaying class and method in backtraces
- `array{0: string|null $class, 1: string|null $method} Dumper::$traceSkip` - methods/functions to skip in backtraces
- `string[] Dumper::$trimPathPrefix` - list of prefixes of file paths to trim

- `bool Dumper::$useHandlers` - switch to use custom and built-in type handlers (Dogma types, enums...)
- `array<class-string, callable>` - list of custom and built-in type handlers
- `array<class-string, callable> Dumper::$shortHandlers` - list of custom and built-in type handlers for single line dumps when $maxDepth is reached
- `class-string[] Dumper::$doNotTraverse` - list of class names forbidden from traversing

- `array<string, string> Dumper::$colors` - definition of output coloring. see trait `DumperFormatters`


Todo:
-----
- indication of process/thread when debugging concurrent tasks
- moar stream wrappers for debugging HTTP, MySQL etc. communication
- moar cool tiny QoL features
- ...
- profit!


Author:
-------
Vlasta Neubauer, https://twitter.com/paranoiq
