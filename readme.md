Dogma - Debug
=============

Remote console debugger

- outputs beautifully formatted debugging data to debug server running in console
- Tracy/Symfony debug bar alternative for debugging CLI scripts, background tasks, many concurrent requests etc.

includes:
- remote dumper with a lot of useful shortcut functions (`Dogma\Debug\Dumper`)
- exception handler (`..\ExceptionHandler`)
- error handler and statistics (`ErrorHandler`)
- process signals and termination tracker (`ShutdownHandler`)
- request info (`RequestHandler`)
- resources usage tracker (`ResourcesHandler`)
- std output tracker and statistics (`OutputHandler`)
- SQL io tracker and statistics (`SqlHandler`)
- Redis io tracker and statistics (`RedisHandler`)
- stream handlers for debugging stream io operations (data, file, ftp, http, phar, zlib - `*StreamHandler`)
- handler for debugging io operations on stream transports (tcp, udp, unix, udg, ssl, tls - `FilesHandler`)
- handlers to track various groups of system functions (curl, dns, mail, settings, syslog)


### requirements:

- **PHP 7.1+**
- **ext-sockets** (also runs locally without sockets)


### installation:

Run `composer create-project dogma/dogma-debug`.


### usage:

WARNING: Dogma - Debug is for development use only.

It does pretty nasty things in the background (like rewriting source code) to accomplish what it does, which you should never do in a production environment with live data.

It is not a production debugging logger like Tracy/Symfony/Laravel error loggers, and it does not aim to replace them.

1) Run `php server.php` in some local console for displaying outputs (starts a socket server on port `1729`).
2) Include `shortcuts.php` or `client.php` in your code or use it in `auto_prepend_file` directive in your `php.ini`.
3) You can create `debug-config.php` in the same directory as client to configure it.


Dumper
------

Dumper has a lot of features and configuration options to make dump outputs more compact, readable and usable - for example:
- rich information about dumped values (sizes, lengths, formatted time for timestamps, binary components of flag integers...)
- custom and built-in formatters for types
- custom and built-in formatters for types in single-line mode
- dumping static members of classes and functions (call as `rd(Foo::class)` or `rd([Foo::class, 'method'])`)
- dumping bind values in closures
- custom timer and measuring time and resources used in given request
- trimming known prefixes from file paths and namespaces
- single-line array dumps for arrays with few items
- configurable callstack info for dumps
- custom colors configuration

function shortcuts:
- `ld($value, ...)` - Local dump
- `lvd($value, ...)` - Local dump implemented with native var_dump() + some colors
- `rd($value, ...);` - Remote dump
- `rvd($value, ...);` - Remote dump implemented with native var_dump() + some colors
- `rc($callable, ...)` - Remote capture dump (dumps outputted result of a callable)
- `rb($length, $argsDepth, $codeLines, $codeDepth, $callstack)` - Remote backtrace dump
- `rf()` - Remotely print function/method name
- `rl($label, [$name])` - Remotely print user defined label
- `rt([$name])` - Remote timer/counter. Shows time since previous event
- `rm([$name])` - Remote memory report. Shows memory consumed/freed from previous event
- `rw($string)` - Remote write. Write raw formatted string to debug output

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

formatters:
- `bool Dumper::$useFormatters` - switch to use custom and built-in type formatters (default `true`)
- `array<class-string, callable> Dumper::$formatters` - list of custom and built-in type formatters
- `array<class-string, callable> Dumper::$shortFormatters` - list of custom and built-in type formatters for single line dumps when $maxDepth is reached
- `class-string[] Dumper::$doNotTraverse` - list of class names forbidden from traversing (won't pollute output)

output:
- `array<string, string> Dumper::$colors` - definition of output coloring. see trait `DumperFormatters`


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
- `bool ErrorHandler::$countMutedErrors` - count errors muted with @ operator into statistics
- `bool ErrorHandler::$showMutedErrors` - show errors muted with @ operator
- `bool ErrorHandler::$filterTrace` - turn on/off callstack filtering (configured in Dumper)
- `string[][] ErrorHandler::$ignore` - list of errors to ignore (keys are concatenated types and messages, items are concatenated file path suffixes and line)
  - eg `ErrorHandler::$ignore = ['Notice: Undefined index "x".' => ['some-file.php:17', 'other-file.php:29']]`


ExceptionHandler
----------------

prints formatted unhandled exceptions with call stack

activate by calling `ExceptionHandler::enable()`

activate inspecting caught exceptions by calling `ExceptionHandler::inspectThrownExceptions(...)`

### configuration:
- `int ExceptionHandler::$traceLength` - how many callstack frames to show (default `1000`)
- `int ExceptionHandler::$traceArgsDepth` - max depth to traverse for arguments of functions/methods called (default `1`)
- `int ExceptionHandler::$traceCodeLines` - lines of code to shown in dumped callstack frames (default `5`)
- `int ExceptionHandler::$traceCodeDepth` - for how many callstack frames to show code (default `5`)
- `class-string[] ExceptionHandler::$logExceptions` - exceptions to log when inspecting caught exceptions
- `class-string[] ExceptionHandler::$notLogExceptions` - exceptions to not log when inspecting caught exceptions
- `bool ExceptionHandler::$filterTrace` - turn on/off callstack filtering (configured in Dumper)


OutputHandler
-------------

reports output operations (echo) and output start

activate by calling `OutputHandler::enable()`

### configuration:

- `bool OutputHandler::$printOutput` - Print output samples (default `false`)
- `int OutputHandler::$maxLength` - max length of printed output samples (default `100`)


RedisHandler:
-------------

reports communication with Redis server

for now only Predis connected via io streams is supported

activate by calling `RedisHandler::enableForPredis()`

### configuration:
- `bool RedisHandler::$log` - Turn logging on/off (default `true`)
- `int RedisHandler::$maxLength` - Max length of logged message (default `2000`)
- `bool RedisHandler::$filterTrace` - turn on/off callstack filtering (configured in Dumper)
- `string[] RedisHandler::$traceFilters` - additional callstack filters specific for this handler


RequestHandler
--------------

reports request inputs and outputs

no activation needed. this handler only holds configuration for debugger headers and footer

### configuration:
- `bool RequestHandler::$showIndex` - show index file name in debugger header (default `true`)
- `bool RequestHandler::$requestHeaders` - print request headers (default `false`)
- `bool RequestHandler::$requestCookies` - print request cookies (default `false`)
- `bool RequestHandler::$requestBody` - print request body (default `false`)
- `bool RequestHandler::$stdinData` - show data received via STDIN (default `false`)
- `bool RequestHandler::$responseHeaders` - print response headers (default `false`)
- `bool RequestHandler::$responseCookies` - print response cookies (default `false`)
- `string[] RequestHandler::$methodColors` - configuration of HTTP method label colors for debugger header
- `string[] RequestHandler::$responseColors` - configuration of HTTP response status label colors for debugger footer


ShutdownHandler
---------------

reports process signals like `SIGTERM` etc. on Linux/Unix and `Ctrl-C`  signal on Windows.
together with `ResourcesHandler` and `Debugger` tries to determine what caused termination of the process

activate by calling `ShutdownHandler::enable()`


SqlHandler
----------

reports SQL database queries and events

for now only Dibi abstraction layer is supported directly, but you can register your own logging function

activate by registering a callback to `SqlHandler::log()` in your DB layer

### configuration:
- `int SqlHandler::$logEvent` - Types of events to log (default `SqlHandler::ALL`)
- `bool SqlHandler::$filterTrace` - turn on/off callstack filtering (configured in Dumper)


Stream wrappers
---------------

wrapper report io operations on PHP streams (`fopen()`, `fwrite()` etc.) by registering stream handlers on them

also enables interceptors to do their thing by rewriting source code of included files and "decorating" native PHP functions and constructs

wrappers:
- `FileStreamWrapper` - io operations on `file://` or just file names without schema prefix
- `PharStreamWrapper` - io operations on `phar://`
- `HttpStreamWrapper` - io operations on `http://` and `https://`
- `FtpStreamWrapper` - io operations on `ftp://` and `ftps://`
- `DataStreamWrapper` - io operations on `data://`
- `PhpStreamWrapper` - io operations on `php://`
- `ZlibStreamWrapper` - io operations on `zlib://` and `zip://`

activate by calling e.g. `FileStreamWrapper::enable()`

### configuration:

- `int FileStreamWrapper::$log` - types of io operations to log (default `StreamWrapper::ALL & ~StreamWrapper::INFO`)
- `bool FileStreamWrapper::$logIncludes` - switch to log io operations from PHP include and require (default `true`)
- `callable FileStreamWrapper::$logFilter` - custom filter for logging io operations

(similarly for all other stream handlers)


Interceptors
------------

by using source code rewriting witchcraft implemented in stream wrappers, we can track or even disable usage of system functions and constructs (`exit` and `die`)

implemented interceptors for now:
- `AutoloadInterceptor` - tracks usage of autoloading functions
- `CurlInterceptor` - tracks usage of Curl extension functions
- `DnsInterceptor` - tracks usage of DNS related functions
- `ErrorInterceptor` - track usage of error/exception related functions
- `StreamInterceptor` - tracks usage of file system related functions. useful on stream transport that do not support wrappers (`tcp`, `udp`, `unix`, `udg`, `ssl`, `tls`)
- `HeadersInterceptor` - tracks usage of functions handling HTTP headers
- `Mailinterceptor` - tracks usage of `mail()` function
- `MysqliInterceptor` - tracks usage of mysqli functions and logs SQL queries
- `ProcessInterceptor` - tracks usage of functions related to process termination and signals
- `Resourcesinterceptor` - tracks usage of functions related to resources (time, memory)
- `SessionInterceptor` - tracks usage of session functions
- `SettingsInterceptor` - tracks usage of function that change PHP configuration or environment configuration
- `StreamInterceptor` - tracks usage of stream wrapper and stream filter functions
- `SyslogInterceptor` - tracks usage of syslog functions

see `Intercept.php` for more complete list of intercepted native functions


with these handlers you can investigate what and when exactly any foreign library or your code is doing,
without the need for some logging support in that library and without having to use stepping debugger like XDebug

it is pretty simple to write other handlers like this to track any native php functions that are not covered yet

you can activate interception for groups of functions by calling `*Interceptor::intercept...()` methods

you might want to clear opcode caches when changing interception configuration, because PHP code is rewritten on-the-fly and PHP does not know which files should be reloaded (with new changes). you can run e.g. `service apache2 restart` to do this when using apache



Author:
-------
Vlasta Neubauer, https://twitter.com/paranoiq
