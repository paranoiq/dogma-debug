<?php

namespace Dogma\Debug;

use function array_merge;

class Sql
{

    public static function getKeywords(): array
    {
        return array_merge(self::getReserved(), self::getNonReserved());
    }

    public static function getReserved(): array
    {
        return [
            'ACCESSIBLE', 'ADD', 'ADMIN', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'ARRAY', 'AS', 'ASC', 'ASENSITIVE',
            'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BY',
            'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK', 'COLLATE', 'COLUMN', 'CONDITION', 'CONSTRAINT', 'CONTINUE',
            'CONVERT', 'CREATE', 'CROSS', 'CUBE', 'CUME_DIST', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR',
            'DATABASE', 'DATABASES', 'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DEFAULT',
            'DELAYED', 'DELETE', 'DENSE_RANK', 'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DROP', 'DUAL',
            'EACH', 'ELSE', 'ELSEIF', 'EMPTY', 'ENCLOSED', 'ESCAPED', 'EXCEPT', 'EXISTS', 'EXIT', 'EXPLAIN',
            'FALSE', 'FETCH', 'FIRST_VALUE', 'FLOAT', 'FLOAT4', 'FLOAT8', 'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT', 'FUNCTION',
            'GENERAL', 'GENERATED', 'GET', 'GET_MASTER_PUBLIC_KEY', 'GRANT', 'GROUP', 'GROUPING', 'GROUPS',
            'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND',
            'IF', 'IGNORE', 'IGNORE_SERVER_IDS', 'IN', 'INDEX', 'INFILE', 'INNER', 'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2',
            'INT3', 'INT4', 'INT8', 'INTEGER', 'INTERVAL', 'INTERSECT', 'INTO', 'IO_AFTER_GTIDS', 'IO_BEFORE_GTIDS', 'IS', 'ITERATE',
            'JOIN', 'JSON_TABLE',
            'KEY', 'KEYS', 'KILL',
            'LAG', 'LAST_VALUE', 'LATERAL', 'LEAD', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINEAR', 'LINES', 'LOAD',
            'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'LOW_PRIORITY',
            'MASTER_BIND', 'MASTER_HEARTBEAT_PERIOD', 'MASTER_HEARTBEAT_PERIOD', 'MASTER_SSL_VERIFY_SERVER_CERT', 'MATCH', 'MEDIUMBLOB',
            'MEDIUMINT', 'MEDIUMTEXT', 'MEMBER', 'MIDDLEINT', 'MINUTE_MICROSECOND', 'MINUTE_SECOND', 'MOD', 'MODIFIES',
            'NATURAL', 'NO_WRITE_TO_BINLOG', 'NOT', 'NTH_VALUE', 'NTILE', 'NULL', 'NUMERIC',
            'OF', 'ON', 'OPTIMIZE', 'OPTIMIZER_COSTS', 'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUT', 'OUTER', 'OUTFILE', 'OVER',
            'PARSE_GCOL_EXPR', 'PARTITION', 'PERCENT_RANK', 'PERSIST', 'PERSIST_ONLY', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'PURGE',
            'RANGE', 'RANK', 'READ', 'READ_ONLY', 'READ_WRITE', 'READS', 'REAL', 'RECURSIVE', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME',
            'REPEAT', 'REPLACE', 'REQUIRE', 'RESIGNAL', 'RESTRICT', 'RETURN', 'REVOKE', 'RIGHT', 'RLIKE', 'ROLE', 'ROW', 'ROW_NUMBER', 'ROWS',
            'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR', 'SET', 'SHOW', 'SCHEMA', 'SCHEMAS', 'SIGNAL', 'SLOW', 'SMALLINT',
            'SPATIAL', 'SPECIFIC', 'SQL', 'SQL_AFTER_GTIDS', 'SQL_BEFORE_GTIDS', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT',
            'SQLEXCEPTION', 'SQLSTATE', 'SQLWARNING', 'SSL', 'STARTING', 'STORED', 'STRAIGHT_JOIN', 'SYSTEM',
            'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE',
            'UNDO', 'UNION', 'UNIQUE', 'UNLOCK', 'UNSIGNED', 'UPDATE', 'UPGRADE', 'USAGE', 'USE', 'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP',
            'VALUES', 'VARBINARY', 'VARCHAR', 'VARCHARACTER', 'VARYING', 'VIRTUAL',
            'WHEN', 'WHERE', 'WHILE', 'WINDOW', 'WITH', 'WRITE',
            'XOR',
            'YEAR_MONTH',
            'ZEROFILL',
        ];
    }

    public static function getNonReserved(): array
    {
        return [
            'ACCOUNT', 'ACTION', 'ACTIVE', 'ADMIN', 'AFTER', 'AGAINST', 'AGGREGATE', 'ALGORITHM', 'ALWAYS', 'ANALYSE', 'ARRAY', 'ASCII',
            'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS', 'AT', 'ATTRIBUTE', 'AUTHENTICATION', 'AUTHORS', 'AUTOEXTEND_SIZE', 'AVG', 'AVG_ROW_LENGTH',
            'BACKUP', 'BDB', 'BEGIN', 'BERKELEYDB', 'BINLOG', 'BIT', 'BLOCK', 'BOOL', 'BOOLEAN', 'BTREE', 'BUCKETS', 'BULK', 'BYTE',
            'CACHE', 'CASCADED', 'CATALOG_NAME', 'CHAIN', 'CHALLENGE_RESPONSE', 'CHANGED', 'CHANNEL', 'CHARSET', 'CHECKSUM',
            'CIPHER', 'CLASS_ORIGIN', 'CLIENT', 'CLONE', 'CLOSE', 'COALESCE', 'CODE', 'COLLATION', 'COLUMN_FORMAT', 'COLUMN_NAME',
            'COLUMNS', 'COMMENT', 'COMMIT', 'COMMITTED', 'COMPACT', 'COMPLETION', 'COMPONENT', 'COMPRESSED', 'COMPRESSION',
            'CONCURRENT', 'CONNECTION', 'CONSISTENT', 'CONSTRAINT_CATALOG', 'CONSTRAINT_NAME', 'CONSTRAINT_SCHEMA', 'CONTAINS',
            'CONTEXT', 'CONTRIBUTORS', 'CUBE', 'CURRENT', 'CURSOR_NAME',
            'DATA', 'DATAFILE', 'DATE', 'DATETIME', 'DAY', 'DEALLOCATE', 'DEFAULT_AUTH', 'DEFINER', 'DEFINITION', 'DELAY_KEY_WRITE',
            'DES_KEY_FILE', 'DESCRIPTION', 'DES_KEY_FILE', 'DIRECTORY', 'DISABLE', 'DISCARD', 'DISK', 'DO', 'DUMPFILE', 'DUPLICATE', 'DYNAMIC',
            'ENABLE', 'ENCRYPTION', 'END', 'ENDS', 'ENFORCED', 'ENGINE', 'ENGINE_ATTRIBUTE', 'ENGINES', 'ENUM', 'ERROR', 'ERRORS',
            'ESCAPE', 'EVENT', 'EVENTS', 'EVERY', 'EXCEPT', 'EXCLUDE', 'EXECUTE', 'EXPANSION', 'EXPIRE', 'EXPORT', 'EXTENDED', 'EXTENT_SIZE',
            'FACTOR', 'FAILED_LOGIN_ATTEMPTS', 'FAST', 'FAULTS', 'FIELDS', 'FILE', 'FILE_BLOCK_SIZE', 'FILTER', 'FINISH',
            'FIRST', 'FIXED', 'FLUSH', 'FOLLOWING', 'FOLLOWS', 'FORMAT', 'FOUND', 'FRAC_SECOND', 'FUNCTION',
            'GENERAL', 'GENERAL', 'GENERATE', 'GEOMCOLLECTION', 'GEOMETRY', 'GEOMETRYCOLLECTION', 'GET_FORMAT',
            'GET_MASTER_PUBLIC_KEY', 'GET_SOURCE_PUBLIC_KEY', 'GLOBAL', 'GOTO', 'GRANTS', 'GROUP_REPLICATION', 'GTID_ONLY',
            'HANDLER', 'HASH', 'HELP', 'HISTOGRAM', 'HISTORY', 'HOST', 'HOSTS', 'HOUR',
            'IDENTIFIED', 'IGNORE_SERVER_IDS', 'IGNORE_SERVER_IDS', 'IGNORE_SERVER_IDS', 'IMPORT', 'INACTIVE', 'INDEXES',
            'INITIAL', 'INITIAL_SIZE', 'INITIATE', 'INNOBASE', 'INNODB', 'INSERT_METHOD', 'INSTALL', 'INSTANCE', 'INVISIBLE',
            'INVOKER', 'IO', 'IO_THREAD', 'IPC', 'ISOLATION', 'ISSUER',
            'JSON', 'JSON_VALUE',
            'KEY_BLOCK_SIZE', 'KEYRING',
            'LABEL', 'LANGUAGE', 'LAST', 'LEAVES', 'LESS', 'LEVEL', 'LINESTRING', 'LIST', 'LOCAL', 'LOCKED', 'LOCKS', 'LOGFILE', 'LOGS',
            'MASTER', 'MASTER_AUTO_POSITION', 'MASTER_COMPRESSION_ALGORITHMS', 'MASTER_CONNECT_RETRY', 'MASTER_DELAY',
            'MASTER_HEARTBEAT_PERIOD', 'MASTER_HOST', 'MASTER_LOG_FILE', 'MASTER_LOG_POS', 'MASTER_PASSWORD', 'MASTER_PORT',
            'MASTER_PUBLIC_KEY_PATH', 'MASTER_RETRY_COUNT', 'MASTER_SERVER_ID', 'MASTER_SSL', 'MASTER_SSL_CA', 'MASTER_SSL_CAPATH',
            'MASTER_SSL_CERT', 'MASTER_SSL_CIPHER', 'MASTER_SSL_CRL', 'MASTER_SSL_CRLPATH', 'MASTER_SSL_KEY', 'MASTER_TLS_CIPHERSUITES',
            'MASTER_TLS_VERSION', 'MASTER_USER', 'MASTER_ZSTD_COMPRESSION_LEVEL', 'MAX_CONNECTIONS_PER_HOUR', 'MAX_QUERIES_PER_HOUR',
            'MAX_ROWS', 'MAX_SIZE', 'MAX_STATEMENT_TIME', 'MAX_USER_CONNECTIONS', 'MAXVALUE', 'MEDIUM', 'MEMBER', 'MEMORY',
            'MERGE', 'MESSAGE_TEXT', 'MICROSECOND', 'MIGRATE', 'MIN_ROWS', 'MINUTE', 'MODE', 'MODIFY', 'MONTH', 'MULTILINESTRING',
            'MULTIPOINT', 'MULTIPOLYGON', 'MUTEX', 'MYSQL_ERRNO',
            'NAME', 'NAMES', 'NATIONAL', 'NDB', 'NDBCLUSTER', 'NESTED', 'NETWORK_NAMESPACE', 'NEVER', 'NEW', 'NEXT',
            'NCHAR', 'NO', 'NO_WAIT', 'NODEGROUP', 'NONBLOCKING', 'NONE', 'NOWAIT', 'NULLS', 'NUMBER', 'NVARCHAR',
            'OFF', 'OFFSET', 'OJ', 'OLD', 'OLD_PASSWORD', 'ONE', 'ONE_SHOT', 'ONLINE', 'ONLY', 'OPEN', 'OPTIONAL', 'OPTIONS',
            'ORDINALITY', 'ORGANIZATION', 'OTHERS', 'OWNER',
            'PACK_KEYS', 'PAGE', 'PAGE_CHECKSUM', 'PARSER', 'PARTIAL', 'PARTITIONING', 'PARTITIONS', 'PASSWORD', 'PASSWORD_LOCK_TIME',
            'PATH', 'PERSIST', 'PERSIST_ONLY', 'PHASE', 'PLUGIN', 'PLUGIN_DIR', 'PLUGINS', 'POINT', 'POLYGON', 'PORT', 'PRECEDES', 'PRECEDING',
            'PREPARE', 'PRESERVE', 'PREV', 'PRIVILEGE_CHECKS_USER', 'PRIVILEGES', 'PROCESS', 'PROCESSLIST', 'PROFILE', 'PROFILES', 'PROXY', 'PROXY',
            'QUARTER', 'QUERY', 'QUICK',
            'RAID_CHUNKS', 'RAID_CHUNKINESS', 'RAID_TYPE', 'RAID0', 'RANDOM', 'READ_ONLY', 'REBUILD', 'RECOVER', 'REDO_BUFFER_SIZE',
            'REDO_LOG', 'REDOFILE', 'REFERENCE', 'REGISTRATION', 'RELAY', 'RELAY_LOG_FILE', 'RELAY_LOG_POS', 'RELAY_THREAD',
            'RELAYLOG', 'RELOAD', 'REMOVE', 'REMOTE', 'REORGANIZE', 'REPAIR', 'REPEATABLE', 'REPLICA', 'REPLICAS', 'REPLICATE_DO_DB',
            'REPLICATE_DO_TABLE', 'REPLICATE_IGNORE_DB', 'REPLICATE_IGNORE_TABLE', 'REPLICATE_REWRITE_DB', 'REPLICATE_WILD_DO_TABLE',
            'REPLICATE_WILD_IGNORE_TABLE', 'REPLICATION', 'REQUIRE_ROW_FORMAT', 'REQUIRE_TABLE_PRIMARY_KEY_CHECK', 'RESET',
            'RESOURCE', 'RESPECT', 'RESTART', 'RESTORE', 'RESUME', 'RETAIN', 'RETURNED_SQLSTATE', 'RETURNING', 'RETURNS', 'REUSE',
            'REVERSE', 'ROLE', 'ROLLBACK', 'ROLLUP', 'ROTATE', 'ROUTINE', 'ROW', 'ROW_COUNT', 'ROW_FORMAT', 'ROWS', 'RTREE',
            'SAVEPOINT', 'SECOND', 'SECONDARY', 'SECONDARY_ENGINE', 'SECONDARY_ENGINE_ATTRIBUTE', 'SECONDARY_LOAD', 'SECONDARY_UNLOAD',
            'SECURITY', 'SERIAL', 'SERIALIZABLE', 'SERVER', 'SESSION', 'SHARE', 'SHUTDOWN', 'SCHEDULE', 'SCHEDULER', 'SIGNED',
            'SIMPLE', 'SKIP', 'SLAVE', 'SLOW', 'SLOW', 'SNAPSHOT', 'SOCKET', 'SOME', 'SONAME', 'SOUNDS', 'SOURCE',
            'SOURCE_AUTO_POSITION', 'SOURCE_BIND', 'SOURCE_COMPRESSION_ALGORITHMS', 'SOURCE_CONNECT_RETRY', 'SOURCE_CONNECTION_AUTO_FAILOVER',
            'SOURCE_DELAY', 'SOURCE_HEARTBEAT_PERIOD', 'SOURCE_HOST', 'SOURCE_LOG_FILE', 'SOURCE_LOG_POS', 'SOURCE_PASSWORD',
            'SOURCE_PORT', 'SOURCE_PUBLIC_KEY_PATH', 'SOURCE_RETRY_COUNT', 'SOURCE_SSL', 'SOURCE_SSL_CA', 'SOURCE_SSL_CAPATH',
            'SOURCE_SSL_CERT', 'SOURCE_SSL_CIPHER', 'SOURCE_SSL_CRL', 'SOURCE_SSL_CRLPATH', 'SOURCE_SSL_KEY', 'SOURCE_SSL_VERIFY_SERVER_CERT',
            'SOURCE_TLS_CIPHERTEXTS', 'SOURCE_TLS_VERSION', 'SOURCE_USER', 'SOURCE_ZSTD_COMPRESSION_LEVEL',
            'SQL_AFTER_GTIDS', 'SQL_AFTER_MTS_GAPS', 'SQL_BEFORE_GTIDS', 'SQL_BUFFER_RESULT', 'SQL_CACHE', 'SQL_NO_CACHE',
            'SQL_THREAD', 'SQL_TSI_DAY', 'SQL_TSI_FRAC_SECOND', 'SQL_TSI_MINUTE', 'SQL_TSI_MONTH', 'SQL_TSI_QUARTER',
            'SQL_TSI_SECOND', 'SQL_TSI_WEEK', 'SQL_TSI_YEAR', 'SRID', 'STACKED', 'START', 'STARTS', 'STATS_AUTO_RECALC',
            'STATS_PERSISTENT', 'STATS_SAMPLE_PAGES', 'STATUS', 'STOP', 'STORAGE', 'STREAM', 'STRING', 'STRIPED',
            'SUBCLASS_ORIGIN', 'SUBJECT', 'SUBPARTITION', 'SUBPARTITIONS', 'SUPER', 'SUSPEND', 'SWAPS', 'SWITCHES',
            'TABLE_CHECKSUM', 'TABLE_NAME', 'TABLES', 'TABLESPACE', 'TEMPORARY', 'TEMPTABLE', 'TEXT', 'THAN', 'THREAD_PRIORITY', 'TIES',
            'TIME', 'TIMESTAMP', 'TIMESTAMPADD', 'TIMESTAMPDIFF', 'TLS', 'TRANSACTION', 'TRANSACTIONAL', 'TRUNCATE', 'TYPE', 'TYPES',
            'UNBOUNDED', 'UNCOMMITTED', 'UNDEFINED', 'UNDO_BUFFER_SIZE', 'UNDOFILE', 'UNICODE', 'UNINSTALL', 'UNKNOWN',
            'UNREGISTER', 'UNTIL', 'UPGRADE', 'URL', 'USE_FRM', 'USER', 'USER_RESOURCES',
            'VALIDATION', 'VALUE', 'VARIABLES', 'VCPU', 'VIEW', 'VISIBLE',
            'WAIT', 'WARNINGS', 'WEEK', 'WEIGHT_STRING', 'WITHOUT', 'WORK', 'WRAPPER',
            'X509', 'XA', 'XID', 'XML',
            'YEAR',
            'ZONE',
        ];
    }

}
