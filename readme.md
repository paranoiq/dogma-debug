Dogma - Debug
=============

Remote console debugger

- outputs beautifully formatted debugging data to debug server running in console
- Tracy/Symfony debug bar alternative for debugging CLI scripts, background tasks, many concurrent requests etc.

includes:
- remote dumper (`Dogma\Debug\Dumper`)
- exception handler (`Dogma\Debug\ExceptionHandler`)
- error handler and statistics (`Dogma\Debug\ErrorHandler`)
- std io tracker and statistics (`Dogma\Debug\IoHandler`)
- file io tracker and statistics (`Dogma\Debug\FileHandler`)
- SQL io tracker and statistics (`Dogma\Debug\SqlHandler`)


### requirements:

- **PHP 7.1+**
- **ext-sockets**


### installation:

Run `composer create-project dogma/dogma-debug`.


### usage:

1) Run `php server.php` in some local console for displaying outputs (starts a socket server on port `1729`).
2) Include `client.php` in your code or use it in `auto_prepend_file` directive in your `php.ini`.
3) You can create `config.php` in the same directory as client to configure it.


Dumper
------

Dumper has a lot of features and configuration options to make dump outputs more compact, readable and usable - for example:
- rich information about dumped values (sizes, lengths, formatted time for timestamps, binary components of flag integers...)
- custom and built-in handlers for types
- custom and built-in handlers for types in single-line mode
- dumping static members of classes and functions (call as `rd(Foo::class)` or `rd([Foo::class, 'method'])`)
- dumping bind values in closures
- custom timer and measuring time and resources used in given request
- trimming known prefixes from file paths and namespaces
- single-line array dumps for arrays with few items
- configurable callstack info for dumps
- custom colors configuration

function shortcuts:
- `ld($value, ...)` - dump value locally (`echo`es the output)
- `rd($value, ...);` - dump value remotely
- `rc(callable, ...)` - capture and dump result of a callable
- `rf()` - print name of current function/method
- `rl($label)` - print label
- `rt([$label])` - print time since request start or last `rt()`

other:
- `Dumper::varDump()` - better formatted and colorized `var_dump()`

### configuration:

common:
- `int Dumper::$maxDepth` - max depth of the structure to traverse (default `3`)
- `int Dumper::$maxLength` - max length of strings to output in characters (default `1000`)
- `int Dumper::$shortArraysMaxLength` - max length of short array output on single line - eg `[1, 2, 3]` (default `100`)
- `int Dumper::$shortArrayMaxItems` - max count of items for short array output on single line (default `20`)
- `bool Dumper::$alwaysShowArrayKeys` - set to true to show keys for lists with numeric keys from 0 (default `false`)
- `string Dumper::$stringsEncoding` - encoding of dumped strings. output is always UTF-8 (default `'utf-8'`)
- `string[] Dumper::$hiddenFields` - names of fields hidden from output - eg `password` etc.
- `bool Dumper::$showInfo` - show extended info for dumped values - eg `"ÁČŘ" // 3 ch, 6 B`
- `string Dumper::$infoTimeZone` - timezone for readable time info formatted from dumped int/float timestamps
- `int Dumper::$propertyOrder` - ordering of properties in object dumps. (default: `Dumper::ORDER_VISIBILITY_ALPHABETIC`)
- `array<string, string> Dumper::$namespaceReplacements` - map of regexps and replacements for shortening dumped class namespaces

traces:
- `int Dumper::$traceLength` - count of lines of call stack for dumped values (default `1`)
- `bool Dumper::$traceDetails` - displaying class and method in call stack (default `true`)
- `int Dumper::$traceArgsDepth` - depth of function/method arguments in call stack (default `0`)
- `int[] Dumper::$traceCodeLines` - show n lines of code for each item in call stack (default `[5]` - meaning 5 lines for firs item, 0 for all others)
- `array{0: string|null $class, 1: string|null $method} Dumper::$traceSkip` - methods/functions to skip in call stack
- `string[] Dumper::$trimPathPrefix` - list of prefixes of file paths to trim
  - configurable by `Dumper::trimPathPrefixBefore()` and `Dumper::trimPathPrefixAfter()`

handlers:
- `bool Dumper::$useHandlers` - switch to use custom and built-in type handlers (default `true`)
- `array<class-string, callable> Dumper::$handlers` - list of custom and built-in type handlers
- `array<class-string, callable> Dumper::$shortHandlers` - list of custom and built-in type handlers for single line dumps when $maxDepth is reached
- `class-string[] Dumper::$doNotTraverse` - list of class names forbidden from traversing (won't pollute output)

output:
- `array<string, string> Dumper::$colors` - definition of output coloring. see trait `DumperFormatters`


ExceptionHandler
----------------

prints formatted unhandled exceptions with call stack

activate by calling `ExceptionHandler::enable()`


ErrorHandler
------------

prints formatted errors/warnings/notices with call stack and error statistics when request is finished

activate by calling `ErrorHandler::enable($types)`

### configuration:

- `int $types` - types of handled errors (default `E_ALL`)
- `bool ErrorHandler::$catch` - switch to catch errors - prevents running default PHP error handler (default `false`)
- `int ErrorHandler::$printLimit` - count of errors to display in remote console (default `0`)
- `bool ErorrHandler::$uniqueOnly` - display only errors of different type (default `true`)
- `bool ErrorHandler::$listErrors` - list errors on end of request
- `bool ErrorHandler::$showLastError` - show last error which could have been hidden by another error handler
- `string[][] ErrorHandler::$ignore` - list of errors to ignore (keys are concatenated types and messages, items are concatenated file path suffixes and line)
  - eg `ErrorHandler::$ignore = ['Notice: Undefined index "x".' => ['some-file.php:17', 'other-file.php:29']]`


IoHandler
---------

reports std io operations, request headers and body, response headers, output start

activate by calling `IoHandler::enable()`

### configuration

- `bool IoHandler::$printOutput` - Print output samples (default `false`)
- `int IoHandler::$maxLength` - max length of printed output samples (default `100`)
- `bool IoHandler::$responseHeaders` - print response headers (default `false`)
- `bool IoHandler::$requestHeaders` - print request headers (default `false`)
- `bool IoHandler::$requestBody` - print request body (default `false`)


FileHandler
-----------

reports file io operations (like `fopen()`, `fread()`, `fwrite()` etc.)

activate by calling `FileHandler::enable()`

### configuration

- `int FileHandler::$log` - types of io operations to log (default `FileStreamWrapper::ALL & ~FileStreamWrapper::INFO`)
- `bool FileHandler::$logIncludes` - switch to log io operations from PHP include and require (default `true`)
- `callable FileHandler::$logFilter` - custom filter for logging io operations


SqlHandler
----------

reports SQL database queries and events

activate by registering a callback to `SqlHandler::log()` in your DB layer

### configurations
- `int SqlHandler::$log` - Types of events to log (default `SqlHandler::ALL`)


Todo:
-----
- indication of process/thread when debugging concurrent tasks
- moar cool tiny QoL features
- HTTP/FTP handler for Dogma\Io and Guzzle
- SQL handler for SqlFtw, Nette\Database and Doctrine
- ...
- profit!


Author:
-------
Vlasta Neubauer, https://twitter.com/paranoiq
