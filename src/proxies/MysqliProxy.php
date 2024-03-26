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
use function microtime;
use const MYSQLI_STORE_RESULT;

class MysqliProxy extends mysqli
{

    public const NAME = 'mysqli';

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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::__construct', [], null);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::autocommit', [$enable], $result);
        }

        return $result;
    }

    public function begin_transaction($flags = 0, $name = null): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::begin_transaction($flags, $name);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::begin_transaction', [$flags, $name], $result);
            if ($name !== null) {
                SqlHandler::log(SqlHandler::BEGIN, "mysqli::begin_transaction({$flags}, '{$name}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($flags !== 0) {
                SqlHandler::log(SqlHandler::BEGIN, "mysqli::begin_transaction({$flags})", $t, null, null, null, $this->error, $this->errno);
            } else {
                SqlHandler::log(SqlHandler::BEGIN, "mysqli::begin_transaction()", $t, null, null, null, $this->error, $this->errno);
            }
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function change_user($username, $password, $database)
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::change_user($username, $password, $database);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::change_user', [$username, $password, $database], $result);
            SqlHandler::log(SqlHandler::OTHER, "mysqli::change_user('{$username}', '*****', '{$database}')", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    public function character_set_name(): string
    {
        $result = '';
        try {
            $result = parent::character_set_name();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::character_set_name', [], $result);
        }

        return $result;
    }

    public function close()
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::close();
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::close', [], $result);
            SqlHandler::log(SqlHandler::OTHER, "mysqli::close()", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function commit($flags = -1, $name = null)
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::commit($flags, $name);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::commit', [$flags, $name], $result);
            if ($name !== null) {
                SqlHandler::log(SqlHandler::COMMIT, "mysqli::commit({$flags}, '{$name}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($flags !== 0) {
                SqlHandler::log(SqlHandler::COMMIT, "mysqli::commit({$flags})", $t, null, null, null, $this->error, $this->errno);
            } else {
                SqlHandler::log(SqlHandler::COMMIT, "mysqli::commit()", $t, null, null, null, $this->error, $this->errno);
            }
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
            $t = microtime(true);
            $result = parent::connect($hostname, $username, $password, $database, $port, $socket);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'PDO::getAttribute', [$hostname, $username, $password, $database, $port, $socket], $result);
            if ($socket !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}', '{$username}', '*****', '{$database}', {$port}, \$socket)", $t, null, null, null, $this->error, $this->errno);
            } elseif ($port !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}', '{$username}', '*****', '{$database}', {$port})", $t, null, null, null, $this->error, $this->errno);
            } elseif ($database !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}', '{$username}', '*****', '{$database}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($password !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}', '{$username}', '*****')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($username !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}', '{$username}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($hostname !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect('{$hostname}')", $t, null, null, null, $this->error, $this->errno);
            } else {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::connect()", $t, null, null, null, $this->error, $this->errno);
            }
        }

        return $result;
    }

    public function dump_debug_info(): bool
    {
        $result = false;
        try {
            $result = parent::dump_debug_info();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::dump_debug_info', [], $result);
        }

        return $result;
    }

    public function debug($options)
    {
        $result = false;
        try {
            $result = parent::debug($options);
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::debug', [$options], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_charset', [], $result);
        }

        return $result;
    }

    public function get_client_info(): string
    {
        $result = '';
        try {
            $result = parent::get_client_info();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_client_info', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_connection_stats', [], $result);
        }

        return $result;
    }

    public function get_server_info(): string
    {
        $result = '';
        try {
            $result = parent::get_server_info();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_server_info', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function get_warnings()
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::get_warnings();
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_warnings', [], $result);
            SqlHandler::log(SqlHandler::OTHER, "mysqli::get_warnings()", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    public function init()
    {
        $result = false;
        try {
            $result = parent::init();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::init', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function kill($process_id)
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::kill($process_id);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::kill', [$process_id], $result);
            SqlHandler::log(SqlHandler::CONNECT, "mysqli::kill()", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function multi_query($query)
    {
        $result = false;
        $id = null;
        try {
            $t = microtime(true);
            $result = parent::multi_query($query);
        } finally {
            $t = microtime(true) - $t;
            if ($result !== false) {
                $id = $this->insert_id;
            }
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::multi_query', [$query], $result);
            SqlHandler::logUnknown($query, $t, 0, $id,  null, $this->error, $this->errno);
        }

        return $result;
    }

    public function more_results(): bool
    {
        $result = false;
        try {
            $result = parent::more_results();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::more_results', [], $result);
        }

        return $result;
    }

    public function next_result(): bool
    {
        $result = false;
        try {
            $result = parent::next_result();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::next_result', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::options', [$option, $value], $result);
        }

        return $result;
    }

    public function ping(): bool
    {
        $result = false;
        try {
            $result = parent::ping();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::ping', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::prepare', [$query], $result);
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
        $rows = null;
        $id = null;
        try {
            $t = microtime(true);
            $result = parent::query($query, $result_mode);
        } finally {
            $t = microtime(true) - $t;
            if ($result !== false) {
                $rows = $this->affected_rows;
                $id = $this->insert_id;
            }
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::query', [$query, $result_mode], $result);
            SqlHandler::logUnknown($query, $t, $rows, $id, null, $this->error, $this->errno);
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
            $t = microtime(true);
            $result = parent::real_connect($hostname, $username, $password, $database, $port, $socket, $flags);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::real_connect', [$hostname, $username, $password, $database, $port, $socket, $flags], $result);
            if ($socket !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}', '{$username}', '*****', '{$database}', {$port}, \$socket)", $t, null, null, null, $this->error, $this->errno);
            } elseif ($port !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}', '{$username}', '*****', '{$database}', {$port})", $t, null, null, null, $this->error, $this->errno);
            } elseif ($database !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}', '{$username}', '*****', '{$database}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($password !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}', '{$username}', '*****')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($username !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}', '{$username}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($hostname !== null) {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect('{$hostname}')", $t, null, null, null, $this->error, $this->errno);
            } else {
                SqlHandler::log(SqlHandler::CONNECT, "mysqli::real_connect()", $t, null, null, null, $this->error, $this->errno);
            }
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::real_escape_string', [$string], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::reap_async_query', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::escape_string', [$string], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function real_query($query)
    {
        $result = false;
        $id = null;
        try {
            $t = microtime(true);
            $result = parent::real_query($query);
        } finally {
            $t = microtime(true) - $t;
            if ($result !== false) {
                $id = $this->insert_id;
            }
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::real_query', [], $result);
            SqlHandler::logUnknown($query, $t, 0, $id, null, $this->error, $this->errno);
        }

        return $result;
    }

    public function release_savepoint($name): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::release_savepoint($name);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::release_savepoint', [$name], $result);
            SqlHandler::log(SqlHandler::COMMIT, "mysqli::release_savepoint('{$name}')", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    public function rollback($flags = 0, $name = null): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::rollback($flags, $name);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::rollback', [$flags, $name], $result);
            if ($name !== null) {
                SqlHandler::log(SqlHandler::ROLLBACK, "mysqli::rollback({$flags}, '{$name}')", $t, null, null, null, $this->error, $this->errno);
            } elseif ($flags !== 0) {
                SqlHandler::log(SqlHandler::ROLLBACK, "mysqli::rollback({$flags})", $t, null, null, null, $this->error, $this->errno);
            } else {
                SqlHandler::log(SqlHandler::ROLLBACK, "mysqli::rollback()", $t, null, null, null, $this->error, $this->errno);
            }
        }

        return $result;
    }

    public function savepoint($name): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::savepoint($name);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::savepoint', [$name], $result);
            SqlHandler::log(SqlHandler::BEGIN, "mysqli::savepoint('{$name}')", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function select_db($database)
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::select_db($database);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::select_db', [$database], $result);
            SqlHandler::log(SqlHandler::BEGIN, "mysqli::select_db('{$database}')", $t, null, null, null, $this->error, $this->errno);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function set_charset($charset)
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::set_charset($charset);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::set_charset', [$charset], $result);
            SqlHandler::log(SqlHandler::OTHER, "mysqli::set_charset('{$charset}')", $t, null, null, null, $this->error, $this->errno);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::set_opt', [$option, $value], $result);
        }

        return $result;
    }

    public function ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos)
    {
        $result = false;
        try {
            $result = parent::ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos);
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::get_warnings', [$key, $certificate, $ca_certificate, $ca_path, $cipher_algos], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::stat', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::stmt_init', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::store_result', [$mode], $result);
        }

        return $result;
    }

    public function thread_safe(): bool
    {
        $result = false;
        try {
            $result = parent::thread_safe();
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::thread_safe', [], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::use_result', [], $result);
        }

        return $result;
    }

    public function refresh($flags): bool
    {
        $result = false;
        try {
            $result = parent::refresh($flags);
        } finally {
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::refresh', [$flags], $result);
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
            Intercept::log(self::NAME, MysqliInterceptor::$intercept, 'mysqli::poll', [$read, $error, $reject, $sec, $usec], $result);
        }

        return $result;
    }

}
