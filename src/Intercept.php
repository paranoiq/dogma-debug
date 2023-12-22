<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Exception;
use LogicException;
use function call_user_func_array;
use function implode;
use function in_array;
use function ini_get;
use function is_array;
use function is_int;
use function is_resource;
use function is_scalar;
use function is_string;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * Using stream handlers, this class rewrites loaded PHP code on-the-fly to take control of various
 * functions (like register_error_handler etc.). This can be used to prevent other tools from changing
 * the environment and potentially silence some errors or just to track which tools are doing what
 *
 * FileHandler (and PharHandler if you are debugging a packed application) must be enabled for this
 *
 * You can add a custom handler via Intercept::registerXyz() methods and overload any system functions. use:
 * - registerFunction() to replace functions
 * - registerMethod() to replace methods
 * - registerClass() to replace classes
 * - registerNoCatch() to prevent catching exceptions
 * - inspectCaughtException() to inspect exceptions caught by application
 * - strictTypes() to turn strict_types on/off regardless of declarations in source code
 * - insertDeclareTicks() to setup ticks regardless of declarations in source code
 *
 * Supported handlers so far and what functions they overload:
 *
 * - AutoloadInterceptor:
 *      spl_autoload_*(), user function __autoload(), user function registered via ini directive 'unserialize_callback_func'
 *      todo: set_include_path(), get_include_path()
 *      todo: enable_dl(), dl() ???
 * - BuffersInterceptor:
 *      flush(), ob_*() (except ob_gz_handler()), output_add_rewrite_var(), output_reset_rewrite_vars()
 * - CurlInterceptor:
 *      curl_*(),
 *      todo: curl_share_*()
 * - DnsInterceptor:
 *      checkdnsrr(), dns_check_record(), dns_get_mx(), dns_get_record(), gethostbyaddr(), gethostbyname(), gethostbynamel(), gethostname(), getmxrr()
 * - ErrorInterceptor:
 *      set_error_handler(), restore_error_handler()
 *      error_reporting(), display_errors()
 *      set_exception_handler(), restore_exception_handler()
 * - ExecInterceptor:
 *      exec(), passthru(), pcntl_exec(), shell_exec(), system(), proc_open(),
 *      proc_close(), proc_terminate(), proc_get_status()
 *      todo: `...`
 * - HeadersInterceptor:
 *      header(), header_remove(), header_register_callback(), http_response_code()
 *      setcookie(), setrawcookie()
 * - MailInterceptor:
 *      mail()
 * - MysqliInterceptor:
 *      mysqli_*()
 * - ProcessInterceptor:
 *      pcntl_signal(), pcntl_async_signals(), pcntl_signal_dispatch(), pcntl_sigprocmask(), pcntl_sigwaitinfo(), pcntl_sigtimedwait()
 *      sapi_windows_set_ctrl_handler()
 *      pcntl_alarm()
 *      pcntl_fork(), pcntl_unshare(), pcntl_wait(), pcntl_waitpid()
 * - ResourcesInterceptor:
 *      register_tick_function(), unregister_tick_function()
 *      set_time_limit()
 *      sleep(), usleep(), time_nanosleep(), time_sleep_until()
 *      gc_disable(), gc_enable(), gc_collect_cycles(), gc_mem_caches()
 * - SessionInterceptor:
 *      session_*()
 * - SettingsInterceptor:
 *      ini_set(), ini_alter(), ini_restore()
 *      putenv()
 * - ShutdownInterceptor:
 *      exit(), die()
 *      ignore_user_abort()
 *      register_shutdown_function()
 * - SocketInterceptor:
 *      todo: socket_*()
 *      todo: fsockopen(), pfsockopen(), stream_socket_client(), stream_socket_server()...
 * - StreamInterceptor:
 *      fopen(), fclose(), flock(), fread(), fwrite(), ftruncate(), fflush(), fseek(), feof(), ftell(), fstat(), fgets()
 *      stream_socket_client()
 *      todo: all other fs functions...
 * - StreamWrapperInterceptor:
 *      stream_wrapper_register(), stream_wrapper_unregister(), stream_wrapper_restore()
 *      stream_filter_register(), stream_filter_remove(), stream_filter_append(), stream_filter_prepend()
 * - SyslogInterceptor:
 *      openlog(), closelog(), syslog()
 */
class Intercept
{

    public const NONE = 0;
    public const SILENT = 1; // do not change or log functionality (but still can track some stats etc.)
    public const LOG_CALLS = 2; // log calls to intercepted functions
    public const PREVENT_CALLS = 4; // prevent calls to intercepted functions
    //public const ALWAYS_LAST = 6; // for register_x_handler() - process own handling after native functionality
    //public const ALWAYS_FIRST = 8; // for register_x_handler() - process own handling before native functionality

    /** @var bool Report files where code has been modified */
    public static $logReplacements = true;

    /** @var string[] Array of handler name to filter */
    public static $logReplacementsForHandlers = [];

    /** @var bool Report when app code tries to call overloaded functions */
    public static $logAttempts = true;

    // trace settings --------------------------------------------------------------------------------------------------

    /** @var bool */
    public static $filterTrace = true;

    /** @var int */
    public static $traceLength = 1;

    /** @var int */
    public static $traceArgsDepth = 0;

    /** @var int */
    public static $traceCodeLines = 0;

    /** @var int */
    public static $traceCodeDepth = 0;

    // call graph settings ---------------------------------------------------------------------------------------------

    /** @var bool Log names of all called functions/methods */
    public static $callGraph = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var array<string, array{string, array{class-string, string}}> */
    private static $functions = [];

    /** @var array<string, array<string, array{string, array{class-string, string}}>> */
    private static $methods = [];

    /** @var array<class-string, array{string, class-string}> */
    private static $classes = [];

    /** @var array<class-string, string> */
    private static $exceptions = [];

    /** @var array{string, array{class-string, string}}|null */
    private static $exceptionCallable;

    /** @var bool */
    private static $removeSensitiveParameter = false;

    /** @var bool|null */
    private static $strictTypes;

    /** @var int|null */
    private static $ticks;

    public static function enabled(): bool
    {
        return self::$functions !== []
            || self::$methods !== []
            || self::$classes !== []
            || self::$exceptions !== []
            || self::$exceptionCallable !== null
            || self::$strictTypes !== null
            || self::$ticks !== null;
    }

    /**
     * Register function to be overloaded with a static call
     * Implementation: debugger will replace function name with supplied callable in loaded code
     *
     * @param array{class-string, string}|class-string $callable
     */
    public static function registerFunction(string $handler, string $function, $callable): void
    {
        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$functions[$function] = is_string($callable) ? [$handler, [$callable, $function]] : [$handler, $callable];
    }

    /**
     * Register static method to be overloaded with another
     * Implementation: debugger will replace class+method pairs with supplied callable in loaded code
     *
     * Todo: for now only works with classes without namespace
     *
     * @param array{class-string, string} $method
     * @param array{class-string, string}|class-string $callable
     */
    public static function registerMethod(string $handler, array $method, $callable): void
    {
        if (strpos($method[0], '\\') !== false) {
            throw new Exception('Replacing classes with namespace is not implemented yet.');
        }

        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$methods[$method[0]][$method[1]] = is_string($callable) ? [$handler, [$callable, $method[1]]] : [$handler, $callable];
    }

    /**
     * Register class to be overloaded with another
     * Implementation: debugger will replaces class name occurrences after `new`, `extends` etc. in loaded code
     *
     * Todo: for now only works with classes without namespace
     *
     * @param class-string $class
     * @param class-string $replace
     */
    public static function registerClass(string $handler, string $class, string $replace): void
    {
        if (strpos($class, '\\') !== false) {
            throw new Exception('Replacing classes with namespace is not implemented yet.');
        }

        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$classes[$class] = [$handler, $replace];
    }

    /**
     * Register non-catchable exception
     * Implementation: debugger will rewrite catch statements in loaded code to prevent catching it
     *
     * Todo: for now only works with exceptions without namespace and first exception in catch (only replaces Foo in `catch (Foo|Bar $e)`)
     *
     * @param class-string $exception
     */
    public static function registerNoCatch(string $handler, string $exception): void
    {
        if (strpos($exception, '\\')) {
            throw new Exception('Replacing exceptions with namespace is not implemented yet.');
        }

        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$exceptions[$exception] = $handler;
    }

    /**
     * Register callable, that can inspect all thrown exceptions before they are caught by application. Callable cannot change or catch the exceptions
     * Implementation: debugger will insert call to supplied callable to all catch statements in loaded code
     *
     * @param array{class-string, string} $callback
     */
    public static function inspectCaughtExceptions(string $handler, array $callback): void
    {
        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$exceptionCallable = [$handler, $callback];
    }

    /**
     * Remove #[SensitiveParameter] attributes to allow debugging them
     */
    public static function removeSensitiveParameterAttributes(bool $remove): void
    {
        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$removeSensitiveParameter = $remove;
    }

    /**
     * Turn strict type on/off on
     * Implementation: debugger will insert/remove `declare(strict_types=1)` in all loaded php files
     */
    public static function strictTypes(?bool $on): void
    {
        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$strictTypes = $on;
    }

    /**
     * Turn ticks on
     * Implementation: debugger will insert `declare(ticks=n)` in all loaded php files
     */
    public static function insertDeclareTicks(int $ticks): void
    {
        if (!self::enabled()) {
            self::startStreamHandlers();
        }

        self::$ticks = $ticks;
    }

    private static function startStreamHandlers(): void
    {
        if (!FileStreamWrapper::enabled()) {
            FileStreamWrapper::enable();
            $activated = FileStreamWrapper::class;
            $by = __CLASS__ . '::' . __METHOD__;
            Debugger::dependencyInfo("{$activated} activated by {$by}() to allow code rewriting.");
        }
        if (!PharStreamWrapper::enabled()) {
            PharStreamWrapper::enable();
            $activated = PharStreamWrapper::class;
            $by = __CLASS__ . '::' . __METHOD__;
            Debugger::dependencyInfo("{$activated} activated by {$by}() to allow code rewriting.");
        }
        if (ini_get('allow_url_include')) {
            if (!HttpStreamWrapper::enabled()) {
                HttpStreamWrapper::enable();
                $activated = HttpStreamWrapper::class;
                $by = __CLASS__ . '::' . __METHOD__;
                Debugger::dependencyInfo("{$activated} activated by {$by}() to allow code rewriting.");
            }
            if (!FtpStreamWrapper::enabled()) {
                FtpStreamWrapper::enable();
                $activated = FtpStreamWrapper::class;
                $by = __CLASS__ . '::' . __METHOD__;
                Debugger::dependencyInfo("{$activated} activated by {$by}() to allow code rewriting.");
            }
        }
    }

    public static function hack(string $code, string $file): string
    {
        $replaced = [];

        if (self::$callGraph) {
            $result = preg_replace("~((?:function|fn)(?:\\s+[a-z0-9_]+)?\\s*\\(.*\\)\\s*(:[^{]+)?\\{)~isU", '\\1rf(true, true);', $code);
            if ($result !== $code) {
                $code = $result;
            }
            $result = preg_replace("~(.*((?:include|require)(?:_once)?)(?:\\s|\\())~iU", 'rf(true, true, "\\2");\\1', $code);
            if ($result !== $code) {
                $code = $result;
            }
        }

        foreach (self::$functions as $function => [$handler, $callable]) {
            // must not be preceded by: other name characters, namespace, `::`, `->`, `$` or `function `
            // todo: may be preceded by: whitespace, block comment or line comment and new line
            // may be followed by: whitespace, block comment or line comment and new line
            // does not care about occurrences inside strings (replaces them anyway)
            if ($function === 'exit' || $function === 'die') {
                $pattern = "~(?<![a-zA-Z0-9_\\\\>:$])(?<!function )\\\\?{$function}((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*[(;])~i";
            } else {
                $pattern = "~(?<![a-zA-Z0-9_\\\\>:$])(?<!function )\\\\?{$function}((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*\()~i";
            }

            $result = preg_replace_callback($pattern, static function (array $m) use ($callable): string {
                $r = '\\' . $callable[0] . '::' . $callable[1] . $m[1];
                // fix missing ()
                if ($r[strlen($r) - 1] === ';') {
                    $r = substr($r, 0, -1) . '();';
                }

                return $r;
            }, $code);

            if ($result !== $code) {
                $replaced[$handler][] = $function . '()';
            }

            $code = $result;
        }

        foreach (self::$methods as $class => $methods) {
            foreach ($methods as $method => [$handler, $callable]) {
                // may be followed by: whitespace, block comment or line comment and new line
                // does not care about occurrences inside strings (replaces them anyway)
                $pattern = "~\\\\?{$class}::{$method}((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*\()~i";

                $result = preg_replace_callback($pattern, static function (array $m) use ($callable): string {
                    $r = '\\' . $callable[0] . '::' . $callable[1] . $m[1];
                    // fix missing ()
                    if ($r[strlen($r) - 1] === ';') {
                        $r = substr($r, 0, -1) . '();';
                    }

                    return $r;
                }, $code);

                if ($result !== $code) {
                    $replaced[$handler][] = "{$class}::{$method}()";
                }

                $code = $result;
            }
        }

        foreach (self::$classes as $class => [$handler, $replace]) {
            $result1 = preg_replace("~new\s+\\\\?{$class}(?![A-Za-z0-9_])~", "new \\$replace", $code);
            if ($result1 !== $code) {
                $replaced[$handler][] = "new {$class}";
            }

            $result2 = preg_replace("~extends\s+\\\\?{$class}(?![A-Za-z0-9_])~", "extends \\$replace", $result1);
            if ($result2 !== $result1) {
                $replaced[$handler][] = "extends {$class}";
            }

            $code = $result2;
        }

        foreach (self::$exceptions as $exception => $handler) {
            $result = preg_replace("~catch\s+\\(\\\\?{$exception}(?![A-Za-z0-9_])~", "catch (\\Dogma\\Debug\\NoCatchException", $code);
            if ($result !== $code) {
                $replaced[$handler][] = "catch ({$exception})";
            }

            $code = $result;
        }

        if (self::$exceptionCallable) {
            [$handler, [$class, $method]] = self::$exceptionCallable;
            $result = preg_replace('~([ \t]*)(}\s*catch\s*\([^)]+\s+)(\\$[A-Za-z0-9_]+)(\s*\)\s*{)~', "\\1\\2\\3\\4\n\\1\t\\\\{$class}::{$method}(\\3);", $code);
            if ($result !== $code) {
                $replaced[$handler][] = "catch (...)";
            }

            $code = $result;
        }

        if (self::$logReplacements && $replaced !== []) {
            foreach ($replaced as $handler => $items) {
                if (self::$logReplacementsForHandlers !== [] && !in_array($handler, self::$logReplacementsForHandlers, true)) {
                    continue;
                }
                $items = Str::join($items, ', ', ' and ');
                $message = Ansi::white(" {$handler}: ", Debugger::$handlerColors[$handler] ?? Debugger::$handlerColors['default'])
                    . ' ' . Ansi::lmagenta("Overloaded {$items} in: ") . Dumper::file($file);
                Debugger::send(Message::INTERCEPT, $message);
            }
        }

        if (self::$ticks) {
            $ticks = self::$ticks;
            $result = str_replace('<?php', "<?php declare(ticks = {$ticks});", $code);

            /*if ($code !== $result) {
                $message = Ansi::lmagenta("Inserted ticks in: ") . Dumper::file($file);
                Debugger::send(Message::INTERCEPT, $message);
            }*/

            $code = $result;
        }

        if (self::$removeSensitiveParameter) {
            $code = preg_replace('~#\\[\\\\?SensitiveParameter]~', '', $code);
        }

        return $code;
    }

    /**
     * Default implementation of an overloaded function handler
     *
     * @param callable-string $function
     * @param mixed[] $params
     * @param mixed $defaultReturn
     * @return mixed
     */
    public static function handle(
        string $handler,
        int $level,
        callable $function,
        array $params,
        $defaultReturn,
        bool $allowed = false,
        string $info = ''
    )
    {
        if ($allowed || $level === self::NONE || $level === self::SILENT) {
            return call_user_func_array($function, $params);
        } elseif ($level & self::LOG_CALLS) {
            return self::callAndLog(true, $handler, $level, $function, $params, $defaultReturn, $info);
        } elseif ($level & self::PREVENT_CALLS) {
            return self::callAndLog(false, $handler, $level, $function, $params, $defaultReturn, $info);
        } else {
            throw new LogicException('Not implemented: ' . $level);
        }
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    public static function log(string $handler, int $level, string $function, array $params, $return, string $info = ''): void
    {
        self::callAndLog(false, $handler, $level, $function, $params, $return, $info);
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    private static function callAndLog(bool $call, string $handler, int $level, string $function, array $params, $return, string $info = '')
    {
        [$return, $dump] = self::callAndLogInternal($call, $function, $params, $return);

        if (!self::$logAttempts || ($level & self::SILENT)) {
            return $return;
        }

        $message = (($level & self::PREVENT_CALLS) ? ' Prevented ' : ' Called ') . $dump;

        if ($info !== '') {
            $message .= Dumper::info($info);
        }

        $message = Ansi::white(" {$handler}: ", Debugger::$handlerColors[$handler] ?? Debugger::$handlerColors['default']) . $message;
        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, self::$traceLength, self::$traceArgsDepth, self::$traceCodeLines, self::$traceCodeDepth);

        Debugger::send(Message::INTERCEPT, $message, $trace);

        return $return;
    }

    /**
     * @param array<int|string|null> $params
     * @param int|string|mixed[]|bool|null $return
     * @return array{mixed, string}
     */
    private static function callAndLogInternal(bool $call, string $function, array $params = [], $return = null): array
    {
        $info = Dumper::$showInfo;
        Dumper::$showInfo = null;

        $paramSeparator = Ansi::color(', ', Dumper::$colors['call']);

        $paramDumps = [];
        foreach ($params as $key => $value) {
            $paramDumps[$key] = Dumper::dumpValue($value, 0, "{$function}.{$key}");
        }

        if ($call) {
            $return = call_user_func_array($function, $params);

            foreach ($params as $key => $value) {
                $paramDumpAfter = Dumper::dumpValue($value, 0, "{$function}.{$key}");
                if ($paramDumpAfter !== $paramDumps[$key]) {
                    $paramDumps[$key] .= ' ' . Dumper::symbol('=>') . ' ' . $paramDumpAfter;
                }
            }
        }

        $paramsDump = implode($paramSeparator, $paramDumps);

        if ($return === null) {
            $output = '';
            $end = ')';
        } elseif (is_scalar($return) || is_resource($return)) {
            $output = ' ' . Dumper::dumpValue($return, 0);
            $end = '):';
        } elseif (is_array($return)) {
            $output = [];
            foreach ($return as $k => $v) {
                if (is_int($k)) {
                    $output[] = Dumper::dumpValue($v, 0);
                } else {
                    $output[] = Ansi::color($k . ':', Dumper::$colors['call']) . ' ' . Dumper::dumpValue($v, 0);
                }
            }
            $output = ' ' . implode(' ', $output);
            $end = '):';
        } else {
            $output = ' ' . Dumper::dumpValue($return, 0);
            $end = '):';
        }

        Dumper::$showInfo = $info;

        return [$return, Dumper::func($function . '(', $paramsDump, $end, $output)];
    }

}
