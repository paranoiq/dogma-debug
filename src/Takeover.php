<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use function call_user_func_array;
use function in_array;
use function preg_replace_callback;
use function strlen;
use function substr;

/**
 * Using stream handlers, this class rewrites loaded PHP code on-the-fly to take control of various
 * functions (like register_error_handler etc.). This can be used to prevent other tools from changing
 * the environment and potentially silence some errors or just to track which tools are doing what
 *
 * FileHandler (and PharHandler if you are debugging a packed application) must be enabled for this
 *
 * You can add a custom handler via Takeover::register() and overload any system function
 *
 * Supported handlers so far and what functions they overload:
 * - ErrorHandler:
 *      set_error_handler(), restore_error_handler()
 *      error_reporting(), display_errors()
 * - ExceptionHandler:
 *      set_exception_handler(), restore_exception_handler()
 * - ShutdownHandler:
 *      pcntl_signal(), pcntl_async_signals(), sapi_windows_set_ctrl_handler(),
 *      exit(), die()
 *      ignore_user_abort()
 *      register_shutdown_function()
 * - ResourcesHandler:
 *      pcntl_alarm()
 *      set_time_limit()
 *      sleep(), usleep(), time_nanosleep(), time_sleep_until()
 * - RequestHandler:
 *      header(), header_remove(), header_register_callback(), http_response_code()
 *      setcookie(), setrawcookie()
 * - MemoryHandler:
 *      gc_disable(), gc_enable(), gc_collect_cycles(), gc_mem_caches()
 * - OutputHandler:
 *      todo: ob_*()
 * - SettingsHandler:
 *      ini_set(), ini_alter(), ini_restore()
 *      putenv()
 * - todo: SessionHandler:
 *      session_*()
 * - todo: ProcessHandler:
 *      exec(), passthru(), shell_exec(), system()
 *      proc_open(), proc_close(), proc_terminate(), proc_get_status()
 * - SyslogHandler:
 *      openlog(), closelog(), syslog()
 * - MailHandler:
 *      mail()
 * - CurlHandler:
 *      curl_*()
 * - todo: SocketHandler:
 *      socket_*()
 *      fsockopen(), pfsockopen(), stream_socket_client(), stream_socket_server()...
 * - DnsHandler:
 *      checkdnsrr(), dns_check_record(), dns_get_mx(), dns_get_record(), gethostbyaddr(), gethostbyname(), gethostbynamel(), gethostname(), getmxrr()
 * - StreamHandler:
 *      stream_wrapper_register(), stream_wrapper_unregister(), stream_wrapper_restore()
 *      stream_filter_register(), stream_filter_remove(), stream_filter_append(), stream_filter_prepend()
 */
class Takeover
{

    public const NONE = 0; // allow other handlers to register
    public const LOG_OTHERS = 1; // log other handler attempts to register
    //public const ALWAYS_LAST = 2; // always process events after other/native handlers
    //public const ALWAYS_FIRST = 3; // always process events before other/native handlers
    public const PREVENT_OTHERS = 4; // do not pass events to other/native handlers

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
    private static $replacements = [];

    /**
     * Register system function to be overloaded with a static call
     *
     * @param array{class-string, string} $callable
     */
    public static function register(string $handler, string $function, array $callable): void
    {
        self::$replacements[$function] = [$handler, $callable];
    }

    public static function clean(): void
    {
        self::$replacements = [];
    }

    public static function enabled(): bool
    {
        return self::$replacements !== [];
    }

    public static function hack(string $code, string $file): string
    {
        $replaced = [];
        foreach (self::$replacements as $function => [$handler, $callable]) {
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

        if (self::$logReplacements && $replaced !== []) {
            foreach ($replaced as $handler => $functions) {
                if (self::$logReplacementsForHandlers !== [] && !in_array($handler, self::$logReplacementsForHandlers, true)) {
                    continue;
                }
                $functions = Str::join($functions, ', ', ' and ');
                $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler]) . ' '
                    . Ansi::lmagenta("Overloaded $functions in: ") . Dumper::file($file);
                Debugger::send(Packet::TAKEOVER, $message);
            }
        }

        return $code;
    }

    /**
     * Default implementation of an overloaded function handler
     *
     * @param mixed[] $params
     * @param mixed $defaultReturn
     * @return mixed
     */
    public static function handle(
        string $handler,
        int $level,
        string $function,
        array $params,
        $defaultReturn,
        bool $allowed = false
    )
    {
        if ($allowed || $level === self::NONE) {
            return call_user_func_array($function, $params);
        } elseif ($level === self::LOG_OTHERS) {
            $result = call_user_func_array($function, $params);
            self::log($handler, $level, $function, $params, $result);

            return $result;
        } elseif ($level === self::PREVENT_OTHERS) {
            self::log($handler, $level, $function, $params, $defaultReturn);

            return $defaultReturn;
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed[] $params
     * @param mixed|null $return
     */
    public static function log(string $handler, int $level, string $function, array $params, $return): void
    {
        if (!self::$logAttempts) {
            return;
        }

        $message = ($level === self::PREVENT_OTHERS ? ' Prevented ' : ' Called ')
            . Dumper::call($function, $params, $return);

        $message = Ansi::white(" $handler: ", Debugger::$handlerColors[$handler]) . $message;
        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::TAKEOVER, $message, $trace);
    }

}
