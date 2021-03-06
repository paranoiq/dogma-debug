<?php declare(strict_types = 1);
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
use function in_array;
use function ini_get;
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
 * - CurlInterceptor:
 *      curl_*()
 * - DnsInterceptor:
 *      checkdnsrr(), dns_check_record(), dns_get_mx(), dns_get_record(), gethostbyaddr(), gethostbyname(), gethostbynamel(), gethostname(), getmxrr()
 * - ErrorInterceptor:
 *      set_error_handler(), restore_error_handler()
 *      error_reporting(), display_errors()
 *      set_exception_handler(), restore_exception_handler()
 * - todo: ExecInterceptor:
 *      exec(), passthru(), shell_exec(), system()
 *      proc_open(), proc_close(), proc_terminate(), proc_get_status()
 *      `...`
 * - FilesystemInterceptor:
 *      - fopen(), fclose(), flock(), fread(), fwrite(), ftruncate(), fflush(), fseek(), feof(), ftell(), fstat(), fgets()
 *      - stream_socket_client()
 *      todo: all other fs functions...
 * - HeadersInterceptor:
 *      header(), header_remove(), header_register_callback(), http_response_code()
 *      setcookie(), setrawcookie()
 * - MailInterceptor:
 *      mail()
 * - MysqliInterceptor:
 *      mysqli_*()
 * - todo: OutputInterceptor:
 *      todo: ob_*()
 * - ProcessInterceptor:
 *      pcntl_signal(), pcntl_async_signals(), pcntl_alarm()
 *      sapi_windows_set_ctrl_handler()
 *      exit(), die()
 *      ignore_user_abort()
 *      register_shutdown_function()
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
 * - SocketInterceptor:
 *      todo: socket_*()
 *      todo: fsockopen(), pfsockopen(), stream_socket_client(), stream_socket_server()...
 * - StreamInterceptor:
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

    /** @var bool */
    public static $filterTrace = true;

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
            Debugger::dependencyInfo('FileStreamHandler activated by Intercept::register() to allow code rewriting.');
        }
        if (!PharStreamWrapper::enabled()) {
            PharStreamWrapper::enable();
            Debugger::dependencyInfo('PharStreamHandler activated by Intercept::register() to allow code rewriting.');
        }
        if (ini_get('allow_url_include')) {
            if (!HttpStreamWrapper::enabled()) {
                HttpStreamWrapper::enable();
                Debugger::dependencyInfo('HttpStreamHandler activated by Intercept::register() to allow code rewriting.');
            }
            if (!FtpStreamWrapper::enabled()) {
                FtpStreamWrapper::enable();
                Debugger::dependencyInfo('FtpStreamHandler activated by Intercept::register() to allow code rewriting.');
            }
        }
    }

    public static function hack(string $code, string $file): string
    {
        $replaced = [];

        foreach (self::$functions as $function => [$handler, $callable]) {
            // must not be preceded by: other name characters, namespace, `::`, `->`, `$` or `function `
            // todo: may be preceded by: whitespace, block comment or line comment and new line
            // may be followed by: whitespace, block comment or line comment and new line
            // does not care about occurrences inside strings (replaces them anyway)
            if ($function === 'exit' || $function === 'die') {
                $pattern = "~(?<![a-zA-Z0-9_\\\\>:$])(?<!function )\\\\?$function((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*[(;])~i";
            } else {
                $pattern = "~(?<![a-zA-Z0-9_\\\\>:$])(?<!function )\\\\?$function((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*\()~i";
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
                $pattern = "~\\\\?$class::$method((?:\s|/\*[^*]*\*/|(?://|#)[^\n]*\n)*\()~i";

                $result = preg_replace_callback($pattern, static function (array $m) use ($callable): string {
                    $r = '\\' . $callable[0] . '::' . $callable[1] . $m[1];
                    // fix missing ()
                    if ($r[strlen($r) - 1] === ';') {
                        $r = substr($r, 0, -1) . '();';
                    }

                    return $r;
                }, $code);

                if ($result !== $code) {
                    $replaced[$handler][] = "$class::$method()";
                }

                $code = $result;
            }
        }

        foreach (self::$classes as $class => [$handler, $replace]) {
            $result1 = preg_replace("~new\s+\\\\?$class(?![A-Za-z0-9_])~", "new \\$replace", $code);
            if ($result1 !== $code) {
                $replaced[$handler][] = "new $class";
            }

            $result2 = preg_replace("~extends\s+\\\\?$class(?![A-Za-z0-9_])~", "extends \\$replace", $result1);
            if ($result2 !== $result1) {
                $replaced[$handler][] = "extends $class";
            }

            $code = $result2;
        }

        foreach (self::$exceptions as $exception => $handler) {
            $result = preg_replace("~catch\s+\\(\\\\?$exception(?![A-Za-z0-9_])~", "catch (\\Dogma\\Debug\\NoCatchException", $code);
            if ($result !== $code) {
                $replaced[$handler][] = "catch ($exception)";
            }

            $code = $result;
        }

        if (self::$exceptionCallable) {
            [$handler, [$class, $method]] = self::$exceptionCallable;
            $result = preg_replace('~([ \t]*)(}\s*catch\s*\([^)]+\s+)(\\$[a-zA-Z0-9_]+)(\s*\)\s*{)~', "\\1\\2\\3\\4\n\\1\t\\\\$class::$method(\\3);", $code);
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
                $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler] ?? Debugger::$handlerColors['default'])
                    . ' ' . Ansi::lmagenta("Overloaded $items in: ") . Dumper::file($file);
                Debugger::send(Packet::INTERCEPT, $message);
            }
        }

        if (self::$ticks) {
            $ticks = self::$ticks;
            $result = str_replace('<?php', "<?php declare(ticks = $ticks);", $code);

            /*if ($code !== $result) {
                $message = Ansi::lmagenta("Inserted ticks in: ") . Dumper::file($file);
                Debugger::send(Packet::INTERCEPT, $message);
            }*/

            $code = $result;
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
        bool $allowed = false
    )
    {
        if ($allowed || $level === self::NONE || $level === self::SILENT) {
            return call_user_func_array($function, $params);
        } elseif ($level & self::LOG_CALLS) {
            $result = call_user_func_array($function, $params);
            self::log($handler, $level, $function, $params, $result);

            return $result;
        } elseif ($level & self::PREVENT_CALLS) {
            self::log($handler, $level, $function, $params, $defaultReturn);

            return $defaultReturn;
        } else {
            throw new LogicException('Not implemented: ' . $level);
        }
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    public static function log(string $handler, int $level, string $function, array $params, $return): void
    {
        if (!self::$logAttempts || ($level & self::SILENT)) {
            return;
        }

        $message = (($level & self::PREVENT_CALLS) ? ' Prevented ' : ' Called ')
            . Dumper::call($function, $params, $return);

        $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler] ?? Debugger::$handlerColors['default']) . $message;
        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, 0);

        Debugger::send(Packet::INTERCEPT, $message, $trace);
    }

}
