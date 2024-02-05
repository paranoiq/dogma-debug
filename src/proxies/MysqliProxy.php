<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint

namespace Dogma\Debug;

use mysqli;
use ReturnTypeWillChange;
use function array_filter;
use function func_get_args;
use const MYSQLI_STORE_RESULT;

class MysqliProxy extends mysqli
{

    public const NAME = 'mysqli';

    /** @var int */
    public static $intercept = Intercept::LOG_CALLS;

    /** @var bool */
    public static $logNextCall = true;

    public function __construct(
        ?string $hostname = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null
    )
    {
        if (self::$logNextCall) {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::__construct', [], null);
        }
        self::$logNextCall = true;

        parent::__construct(...array_filter(func_get_args(), static function ($value) {
            return $value !== null;
        }));
    }

    #[ReturnTypeWillChange]
    public function autocommit($enable)
    {
        $result = false;
        try {
            $result = parent::autocommit($enable);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::autocommit', [$enable], $result);
        }

        return $result;
    }

    public function begin_transaction($flags = 0, $name = null): bool
    {
        $result = false;
        try {
            $result = parent::begin_transaction($flags, $name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::begin_transaction', [$flags, $name], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function change_user($username, $password, $database)
    {
        $result = false;
        try {
            $result = parent::change_user($username, $password, $database);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::change_user', [$username, $password, $database], $result);
        }

        return $result;
    }

    public function character_set_name(): string
    {
        $result = '';
        try {
            $result = parent::character_set_name();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::character_set_name', [], $result);
        }

        return $result;
    }

    public function close()
    {
        $result = false;
        try {
            $result = parent::close();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::close', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function commit($flags = -1, $name = null)
    {
        $result = false;
        try {
            $result = parent::commit($flags, $name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::commit', [$flags, $name], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function connect(
        $hostname = null,
        $username = null,
        $password = null,
        $database = null,
        $port = null,
        $socket = null
    )
    {
        $result = null;
        try {
            $result = parent::connect($hostname, $username, $password, $database, $port, $socket);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::getAttribute', [$hostname, $username, $password, $database, $port, $socket], $result);
        }

        return $result;
    }

    public function dump_debug_info(): bool
    {
        $result = false;
        try {
            $result = parent::dump_debug_info();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::dump_debug_info', [], $result);
        }

        return $result;
    }

    public function debug($options)
    {
        $result = false;
        try {
            $result = parent::debug($options);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::debug', [$options], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function get_charset()
    {
        $result = false;
        try {
            $result = parent::get_charset();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_charset', [], $result);
        }

        return $result;
    }

    public function get_client_info(): string
    {
        $result = '';
        try {
            $result = parent::get_client_info();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_client_info', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function get_connection_stats()
    {
        $result = false;
        try {
            $result = parent::get_connection_stats();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_connection_stats', [], $result);
        }

        return $result;
    }

    public function get_server_info(): string
    {
        $result = '';
        try {
            $result = parent::get_server_info();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_server_info', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function get_warnings()
    {
        $result = false;
        try {
            $result = parent::get_warnings();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_warnings', [], $result);
        }

        return $result;
    }

    public function init()
    {
        $result = false;
        try {
            $result = parent::init();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::init', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function kill($process_id)
    {
        $result = false;
        try {
            $result = parent::kill($process_id);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::kill', [$process_id], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function multi_query($query)
    {
        $result = false;
        try {
            $result = parent::multi_query($query);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::multi_query', [$query], $result);
        }

        return $result;
    }

    public function more_results(): bool
    {
        $result = false;
        try {
            $result = parent::more_results();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::more_results', [], $result);
        }

        return $result;
    }

    public function next_result(): bool
    {
        $result = false;
        try {
            $result = parent::next_result();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::next_result', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function options($option, $value)
    {
        $result = false;
        try {
            $result = parent::options($option, $value);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::options', [$option, $value], $result);
        }

        return $result;
    }

    public function ping(): bool
    {
        $result = false;
        try {
            $result = parent::ping();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::ping', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function prepare($query)
    {
        $result = false;
        try {
            $result = parent::prepare($query);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::prepare', [$query], $result);
        }

        if ($result !== false) {
            if (MysqliInterceptor::$wrapStatements === MysqliInterceptor::STATEMENT_WRAP_EXTENDING) {
                return new MysqliStatementProxy($this, $query, $result);
            } elseif (MysqliInterceptor::$wrapStatements === MysqliInterceptor::STATEMENT_WRAP_AGGRESSIVE) {
                return new MysqliStatementWrapper($result);
            }
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function query($query, $result_mode = MYSQLI_STORE_RESULT)
    {
        $result = false;
        try {
            $result = parent::query($query, $result_mode);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::query', [$query, $result_mode], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function real_connect(
        $hostname = null,
        $username = null,
        $password = null,
        $database = null,
        $port = null,
        $socket = null,
        $flags = null
    )
    {
        $result = false;
        try {
            $result = parent::real_connect($hostname, $username, $password, $database, $port, $socket, $flags);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::real_connect', [$hostname, $username, $password, $database, $port, $socket, $flags], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function real_escape_string($string)
    {
        $result = '';
        try {
            $result = parent::real_escape_string($string);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::real_escape_string', [$string], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function reap_async_query()
    {
        $result = false;
        try {
            $result = parent::reap_async_query();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::reap_async_query', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function escape_string($string)
    {
        $result = false;
        try {
            $result = parent::escape_string($string);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::escape_string', [$string], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function real_query($query)
    {
        $result = false;
        try {
            $result = parent::real_query($query);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::real_query', [], $result);
        }

        return $result;
    }

    public function release_savepoint($name): bool
    {
        $result = false;
        try {
            $result = parent::release_savepoint($name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::release_savepoint', [$name], $result);
        }

        return $result;
    }

    public function rollback($flags = 0, $name = null): bool
    {
        $result = false;
        try {
            $result = parent::rollback($flags, $name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::rollback', [$flags, $name], $result);
        }

        return $result;
    }

    public function savepoint($name): bool
    {
        $result = false;
        try {
            $result = parent::savepoint($name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::savepoint', [$name], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function select_db($database)
    {
        $result = false;
        try {
            $result = parent::select_db($database);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::select_db', [$database], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function set_charset($charset)
    {
        $result = false;
        try {
            $result = parent::set_charset($charset);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::set_charset', [$charset], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function set_opt($option, $value)
    {
        $result = false;
        try {
            $result = parent::set_opt($option, $value);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::set_opt', [$option, $value], $result);
        }

        return $result;
    }

    public function ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos)
    {
        $result = false;
        try {
            $result = parent::ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::get_warnings', [$key, $certificate, $ca_certificate, $ca_path, $cipher_algos], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function stat()
    {
        $result = false;
        try {
            $result = parent::stat();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::stat', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function stmt_init()
    {
        $result = false;
        try {
            $result = parent::stmt_init();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::stmt_init', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function store_result($mode = null)
    {
        $result = false;
        try {
            $result = parent::store_result($mode);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::store_result', [$mode], $result);
        }

        return $result;
    }

    public function thread_safe(): bool
    {
        $result = false;
        try {
            $result = parent::thread_safe();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::thread_safe', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function use_result()
    {
        $result = false;
        try {
            $result = parent::use_result();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::use_result', [], $result);
        }

        return $result;
    }

    public function refresh($flags): bool
    {
        $result = false;
        try {
            $result = parent::refresh($flags);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::refresh', [$flags], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public static function poll(?array &$read, ?array &$error, ?array &$reject, $sec, $usec = null)
    {
        $result = false;
        try {
            $result = parent::poll($read, $error, $reject, $sec, $usec);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'mysqli::poll', [$read, $error, $reject, $sec, $usec], $result);
        }

        return $result;
    }

}
