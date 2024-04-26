<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use mysqli;
use mysqli_stmt;
use function extension_loaded;
use function ini_get;

/**
 * Tracks access to MySQL via mysqli functions
 */
class MysqliInterceptor
{

    public const NAME = 'mysqli';

    // the only safe value
    public const STATEMENT_WRAP_NONE = 0;
    /** @internal - statement proxy extends mysqli_proxy, but readonly properties on it do not work (cannot unset props on extension class). EXPERIMENTAL! */
    public const STATEMENT_WRAP_EXTENDING = 1;
    /** @internal - statement proxy does not extend mysqli_proxy, so more aggressive class name replacement must be done (may fail with aliases etc). EXPERIMENTAL! */
    public const STATEMENT_WRAP_AGGRESSIVE = 2;

    /** @var int */
    public static $intercept = Intercept::NONE;

    public static $wrapStatements = self::STATEMENT_WRAP_NONE;

    /**
     * Take control over majority of mysqli_*() functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptMysqli(int $level = Intercept::LOG_CALLS, int $wrapStatements = self::STATEMENT_WRAP_NONE): void
    {
        if (!extension_loaded('mysqli')) {
            return;
        }

        Intercept::registerClass(self::NAME, mysqli::class, MysqliProxy::class);
        if ($wrapStatements === self::STATEMENT_WRAP_EXTENDING) {
            Intercept::registerClass(self::NAME, mysqli_stmt::class, MysqliStatementProxy::class);
        } elseif ($wrapStatements === self::STATEMENT_WRAP_AGGRESSIVE) {
            Intercept::registerClass(self::NAME, mysqli_stmt::class, MysqliStatementWrapper::class, true);
        }
        self::$wrapStatements = $wrapStatements;

        /*Intercept::registerFunction(self::NAME, 'mysqli_affected_rows', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_autocommit', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_begin_transaction', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_change_user', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_character_set_name', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_close', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_commit', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_connect', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_connect_errno', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_connect_error', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_data_seek', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_dump_debug_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_debug', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_errno', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_error_list', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_error_list', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_error', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_execute', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_execute', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_field', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_fields', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_field_direct', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_lengths', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_all', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_array', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_assoc', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_object', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_row', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_fetch_column', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_field_count', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_field_seek', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_field_tell', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_free_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_cache_stats', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_connection_stats', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_client_stats', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_charset', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_client_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_client_version', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_host_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_links_stats', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_proto_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_server_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_server_version', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_get_warnings', self::class);*/
        Intercept::registerFunction(self::NAME, 'mysqli_init', self::class);
        /*Intercept::registerFunction(self::NAME, 'mysqli_info', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_insert_id', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_kill', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_set_local_infile_default', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_set_local_infile_handler', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_more_results', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_multi_query', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_next_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_num_fields', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_num_rows', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_options', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_set_opt', self::class); // alias ^
        Intercept::registerFunction(self::NAME, 'mysqli_ping', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_poll', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_prepare', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_report', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_query', self::class);*/
        Intercept::registerFunction(self::NAME, 'mysqli_real_connect', self::class);
        /*Intercept::registerFunction(self::NAME, 'mysqli_real_escape_string', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_escape_string', self::class); // alias ^
        Intercept::registerFunction(self::NAME, 'mysqli_real_query', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_reap_async_query', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_release_savepoint', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_rollback', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_savepoint', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_select_db', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_set_charset', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_sqlstate', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stat', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_ssl_set', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_store_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_thread_id', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_thread_safe', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_use_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_warning_count', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_refresh', self::class);

        Intercept::registerFunction(self::NAME, 'mysqli_stmt_affected_rows', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_attr_get', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_attr_set', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_field_count', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_init', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_prepare', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_result_metadata', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_send_long_data', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_bind_param', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_bind_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_fetch', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_free_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_get_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_get_warnings', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_insert_id', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_reset', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_param_count', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_close', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_data_seek', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_errno', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_error', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_more_results', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_next_result', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_num_rows', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_sqlstate', self::class);
        Intercept::registerFunction(self::NAME, 'mysqli_stmt_store_result', self::class);
        */
        self::$intercept = $level;
    }

    public static function mysqli_init(): mysqli
    {
        MysqliProxy::$logNextCall = false;
        $mysqli = new MysqliProxy();

        Intercept::log(self::NAME, self::$intercept, __FUNCTION__, [], $mysqli);

        return $mysqli;
    }

    public static function mysqli_real_connect(
        mysqli $mysql,
        ?string $hostname = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = '',
        ?int $flags = 0
    ): bool
    {
        if ($port === null) {
            $port = (int) ini_get('mysqli.default_port');
        }
        if ($flags === null) {
            $flags = 0;
        }

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$mysql, $hostname, $username, $password, $database, $port, $socket, $flags], false);
    }

}
