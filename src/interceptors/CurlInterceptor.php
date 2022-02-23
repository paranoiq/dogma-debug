<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use CurlHandle;
use CurlMultiHandle;
use function fseek;
use const CURLE_OK;
use const CURLM_OK;
use const CURLOPT_ACCEPT_ENCODING;
use const CURLOPT_ACCEPTTIMEOUT_MS;
use const CURLOPT_ADDRESS_SCOPE;
use const CURLOPT_APPEND;
use const CURLOPT_BINARYTRANSFER;
use const CURLOPT_BUFFERSIZE;
use const CURLOPT_CERTINFO;
use const CURLOPT_CONNECT_ONLY;
use const CURLOPT_CONNECT_TO;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_COOKIE;
use const CURLOPT_COOKIEFILE;
use const CURLOPT_COOKIEJAR;
use const CURLOPT_COOKIELIST;
use const CURLOPT_COOKIESESSION;
use const CURLOPT_CRLF;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_DIRLISTONLY;
use const CURLOPT_DNS_CACHE_TIMEOUT;
use const CURLOPT_DNS_SERVERS;
use const CURLOPT_DNS_USE_GLOBAL_CACHE;
use const CURLOPT_EGDSOCKET;
use const CURLOPT_EXPECT_100_TIMEOUT_MS;
use const CURLOPT_FAILONERROR;
use const CURLOPT_FILE;
use const CURLOPT_FILETIME;
use const CURLOPT_FNMATCH_FUNCTION;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_FORBID_REUSE;
use const CURLOPT_FRESH_CONNECT;
use const CURLOPT_FTP_ACCOUNT;
use const CURLOPT_FTP_CREATE_MISSING_DIRS;
use const CURLOPT_FTP_FILEMETHOD;
use const CURLOPT_FTP_RESPONSE_TIMEOUT;
use const CURLOPT_FTP_SKIP_PASV_IP;
use const CURLOPT_FTP_SSL_CCC;
use const CURLOPT_FTP_USE_EPRT;
use const CURLOPT_FTP_USE_EPSV;
use const CURLOPT_FTP_USE_PRET;
use const CURLOPT_FTPPORT;
use const CURLOPT_FTPSSLAUTH;
use const CURLOPT_GSSAPI_DELEGATION;
use const CURLOPT_HEADER;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HEADEROPT;
use const CURLOPT_HTTP_CONTENT_DECODING;
use const CURLOPT_HTTP_TRANSFER_DECODING;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_HTTPGET;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_HTTPPROXYTUNNEL;
use const CURLOPT_IGNORE_CONTENT_LENGTH;
use const CURLOPT_INFILE;
use const CURLOPT_INFILESIZE;
use const CURLOPT_INTERFACE;
use const CURLOPT_IPRESOLVE;
use const CURLOPT_KEYPASSWD;
use const CURLOPT_KRBLEVEL;
use const CURLOPT_LOCALPORT;
use const CURLOPT_LOCALPORTRANGE;
use const CURLOPT_LOW_SPEED_LIMIT;
use const CURLOPT_LOW_SPEED_TIME;
use const CURLOPT_MAIL_AUTH;
use const CURLOPT_MAIL_FROM;
use const CURLOPT_MAIL_RCPT;
use const CURLOPT_MAX_RECV_SPEED_LARGE;
use const CURLOPT_MAX_SEND_SPEED_LARGE;
use const CURLOPT_MAXCONNECTS;
use const CURLOPT_MAXFILESIZE;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_NETRC;
use const CURLOPT_NETRC_FILE;
use const CURLOPT_NEW_DIRECTORY_PERMS;
use const CURLOPT_NEW_FILE_PERMS;
use const CURLOPT_NOBODY;
use const CURLOPT_NOPROGRESS;
use const CURLOPT_NOSIGNAL;
use const CURLOPT_PATH_AS_IS;
use const CURLOPT_PIPEWAIT;
use const CURLOPT_PORT;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_POSTQUOTE;
use const CURLOPT_POSTREDIR;
use const CURLOPT_PREQUOTE;
use const CURLOPT_PRIVATE;
use const CURLOPT_PROGRESSFUNCTION;
use const CURLOPT_PROTOCOLS;
use const CURLOPT_PROXY;
use const CURLOPT_PROXY_TRANSFER_MODE;
use const CURLOPT_PROXYAUTH;
use const CURLOPT_PROXYPORT;
use const CURLOPT_PROXYTYPE;
use const CURLOPT_PROXYUSERNAME;
use const CURLOPT_PROXYUSERPWD;
use const CURLOPT_PUT;
use const CURLOPT_QUOTE;
use const CURLOPT_RANDOM_FILE;
use const CURLOPT_RANGE;
use const CURLOPT_READFUNCTION;
use const CURLOPT_REDIR_PROTOCOLS;
use const CURLOPT_REFERER;
use const CURLOPT_RESUME_FROM;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_RTSP_CLIENT_CSEQ;
use const CURLOPT_RTSP_REQUEST;
use const CURLOPT_RTSP_SERVER_CSEQ;
use const CURLOPT_SASL_IR;
use const CURLOPT_SHARE;
use const CURLOPT_SOCKS5_GSSAPI_NEC;
use const CURLOPT_SOCKS5_GSSAPI_SERVICE;
use const CURLOPT_SSH_AUTH_TYPES;
use const CURLOPT_SSH_HOST_PUBLIC_KEY_MD5;
use const CURLOPT_SSH_KNOWNHOSTS;
use const CURLOPT_SSH_PUBLIC_KEYFILE;
use const CURLOPT_SSL_CIPHER_LIST;
use const CURLOPT_SSL_ENABLE_ALPN;
use const CURLOPT_SSL_ENABLE_NPN;
use const CURLOPT_SSL_FALSESTART;
use const CURLOPT_SSL_OPTIONS;
use const CURLOPT_SSL_SESSIONID_CACHE;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_SSL_VERIFYSTATUS;
use const CURLOPT_SSLCERT;
use const CURLOPT_SSLCERTTYPE;
use const CURLOPT_SSLENGINE;
use const CURLOPT_SSLENGINE_DEFAULT;
use const CURLOPT_SSLKEY;
use const CURLOPT_SSLKEYTYPE;
use const CURLOPT_SSLVERSION;
use const CURLOPT_STDERR;
use const CURLOPT_STREAM_WEIGHT;
use const CURLOPT_TCP_FASTOPEN;
use const CURLOPT_TCP_KEEPALIVE;
use const CURLOPT_TCP_KEEPIDLE;
use const CURLOPT_TCP_KEEPINTVL;
use const CURLOPT_TCP_NODELAY;
use const CURLOPT_TELNETOPTIONS;
use const CURLOPT_TFTP_BLKSIZE;
use const CURLOPT_TFTP_NO_OPTIONS;
use const CURLOPT_TIMECONDITION;
use const CURLOPT_TIMEOUT;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_TIMEVALUE;
use const CURLOPT_TLSAUTH_USERNAME;
use const CURLOPT_TRANSFER_ENCODING;
use const CURLOPT_TRANSFERTEXT;
use const CURLOPT_UNRESTRICTED_AUTH;
use const CURLOPT_UPLOAD;
use const CURLOPT_URL;
use const CURLOPT_USE_SSL;
use const CURLOPT_USERAGENT;
use const CURLOPT_USERNAME;
use const CURLOPT_USERPWD;
use const CURLOPT_VERBOSE;
use const CURLOPT_WILDCARDMATCH;
use const CURLOPT_WRITEFUNCTION;
use const CURLOPT_WRITEHEADER;
use const CURLOPT_XOAUTH2_BEARER;

/**
 * Tracks HTTP requests via Curl
 */
class CurlInterceptor
{

    public const NAME = 'curl';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /** @var resource[] */
    private static $files = [];

    private static $opt = [
        CURLOPT_PORT => 3,
        CURLOPT_TIMEOUT => 13,
        CURLOPT_INFILESIZE => 14,
        CURLOPT_LOW_SPEED_LIMIT => 19,
        CURLOPT_LOW_SPEED_TIME => 20,
        CURLOPT_RESUME_FROM => 21,
        CURLOPT_CRLF => 27,
        CURLOPT_SSLVERSION => 32,
        CURLOPT_TIMECONDITION => 33,
        CURLOPT_TIMEVALUE => 34,
        CURLOPT_VERBOSE => 41,
        CURLOPT_HEADER => 42,
        CURLOPT_NOPROGRESS => 43,
        CURLOPT_NOBODY => 44,
        CURLOPT_FAILONERROR => 45,
        CURLOPT_UPLOAD => 46,
        CURLOPT_POST => 47,
        CURLOPT_DIRLISTONLY => 48,
        CURLOPT_APPEND => 50,
        CURLOPT_NETRC => 51,
        CURLOPT_FOLLOWLOCATION => 52,
        CURLOPT_TRANSFERTEXT => 53,
        CURLOPT_PUT => 54,
        CURLOPT_AUTOREFERER => 58,
        CURLOPT_PROXYPORT => 59,
        CURLOPT_HTTPPROXYTUNNEL => 61,
        CURLOPT_SSL_VERIFYPEER => 64,
        CURLOPT_MAXREDIRS => 68,
        CURLOPT_FILETIME => 69,
        CURLOPT_MAXCONNECTS => 71,
        CURLOPT_FRESH_CONNECT => 74,
        CURLOPT_FORBID_REUSE => 75,
        CURLOPT_CONNECTTIMEOUT => 78,
        CURLOPT_HTTPGET => 80,
        CURLOPT_SSL_VERIFYHOST => 81,
        CURLOPT_HTTP_VERSION => 84,
        CURLOPT_FTP_USE_EPSV => 85,
        CURLOPT_SSLENGINE_DEFAULT => 90,
        CURLOPT_DNS_USE_GLOBAL_CACHE => 91,
        CURLOPT_DNS_CACHE_TIMEOUT => 92,
        CURLOPT_COOKIESESSION => 96,
        CURLOPT_BUFFERSIZE => 98,
        CURLOPT_NOSIGNAL => 99,
        CURLOPT_PROXYTYPE => 101,
        CURLOPT_UNRESTRICTED_AUTH => 105,
        CURLOPT_FTP_USE_EPRT => 106,
        CURLOPT_HTTPAUTH => 107,
        CURLOPT_FTP_CREATE_MISSING_DIRS => 110,
        CURLOPT_PROXYAUTH => 111,
        CURLOPT_FTP_RESPONSE_TIMEOUT => 112,
        CURLOPT_IPRESOLVE => 113,
        CURLOPT_MAXFILESIZE => 114,
        CURLOPT_USE_SSL => 119,
        CURLOPT_TCP_NODELAY => 121,
        CURLOPT_FTPSSLAUTH => 129,
        CURLOPT_IGNORE_CONTENT_LENGTH => 136,
        CURLOPT_FTP_SKIP_PASV_IP => 137,
        CURLOPT_FTP_FILEMETHOD => 138,
        CURLOPT_LOCALPORT => 139,
        CURLOPT_LOCALPORTRANGE => 140,
        CURLOPT_CONNECT_ONLY => 141,
        CURLOPT_SSL_SESSIONID_CACHE => 150,
        CURLOPT_SSH_AUTH_TYPES => 151,
        CURLOPT_FTP_SSL_CCC => 154,
        CURLOPT_TIMEOUT_MS => 155,
        CURLOPT_CONNECTTIMEOUT_MS => 156,
        CURLOPT_HTTP_TRANSFER_DECODING => 157,
        CURLOPT_HTTP_CONTENT_DECODING => 158,
        CURLOPT_NEW_FILE_PERMS => 159,
        CURLOPT_NEW_DIRECTORY_PERMS => 160,
        CURLOPT_POSTREDIR => 161,
        CURLOPT_PROXY_TRANSFER_MODE => 166,
        CURLOPT_ADDRESS_SCOPE => 171,
        CURLOPT_CERTINFO => 172,
        CURLOPT_TFTP_BLKSIZE => 178,
        CURLOPT_SOCKS5_GSSAPI_NEC => 180,
        CURLOPT_PROTOCOLS => 181,
        CURLOPT_REDIR_PROTOCOLS => 182,
        CURLOPT_FTP_USE_PRET => 188,
        CURLOPT_RTSP_REQUEST => 189,
        CURLOPT_RTSP_CLIENT_CSEQ => 193,
        CURLOPT_RTSP_SERVER_CSEQ => 194,
        CURLOPT_WILDCARDMATCH => 197,
        CURLOPT_TRANSFER_ENCODING => 207,
        CURLOPT_GSSAPI_DELEGATION => 210,
        CURLOPT_ACCEPTTIMEOUT_MS => 212,
        CURLOPT_TCP_KEEPALIVE => 213,
        CURLOPT_TCP_KEEPIDLE => 214,
        CURLOPT_TCP_KEEPINTVL => 215,
        CURLOPT_SSL_OPTIONS => 216,
        CURLOPT_SASL_IR => 218,
        CURLOPT_SSL_ENABLE_ALPN => 226,
        CURLOPT_SSL_ENABLE_NPN => 225,
        CURLOPT_EXPECT_100_TIMEOUT_MS => 227,
        CURLOPT_HEADEROPT => 229,
        CURLOPT_SSL_VERIFYSTATUS => 232,
        CURLOPT_SSL_FALSESTART => 233,
        CURLOPT_PATH_AS_IS => 234,
        CURLOPT_PIPEWAIT => 237,
        CURLOPT_STREAM_WEIGHT => 239,
        CURLOPT_TFTP_NO_OPTIONS => 242,
        CURLOPT_TCP_FASTOPEN => 244,

        CURLOPT_FILE => 10001,
        CURLOPT_URL => 10002,
        CURLOPT_PROXY => 10004,
        CURLOPT_USERPWD => 10005,
        CURLOPT_PROXYUSERPWD => 10006,
        CURLOPT_RANGE => 10007,
        CURLOPT_INFILE => 10009,
        //CURLOPT_READDATA => 10009,
        CURLOPT_POSTFIELDS => 10015,
        CURLOPT_REFERER => 10016,
        CURLOPT_FTPPORT => 10017,
        CURLOPT_USERAGENT => 10018,
        CURLOPT_COOKIE => 10022,
        CURLOPT_HTTPHEADER => 10023,
        CURLOPT_SSLCERT => 10025,
        //CURLOPT_SSLCERTPASSWD => 10026,
        //CURLOPT_SSLKEYPASSWD => 10026,
        CURLOPT_KEYPASSWD => 10026,
        CURLOPT_QUOTE => 10028,
        CURLOPT_WRITEHEADER => 10029,
        CURLOPT_COOKIEFILE => 10031,
        CURLOPT_CUSTOMREQUEST => 10036,
        CURLOPT_STDERR => 10037,
        CURLOPT_POSTQUOTE => 10039,
        CURLOPT_INTERFACE => 10062,
        //CURLOPT_KRB4LEVEL => 10063,
        CURLOPT_KRBLEVEL => 10063,
        CURLOPT_CAINFO => 10065,
        CURLOPT_TELNETOPTIONS => 10070,
        CURLOPT_RANDOM_FILE => 10076,
        CURLOPT_EGDSOCKET => 10077,
        CURLOPT_COOKIEJAR => 10082,
        CURLOPT_SSL_CIPHER_LIST => 10083,
        CURLOPT_SSLCERTTYPE => 10086,
        CURLOPT_SSLKEY => 10087,
        CURLOPT_SSLKEYTYPE => 10088,
        CURLOPT_SSLENGINE => 10089,
        CURLOPT_PREQUOTE => 10093,
        CURLOPT_CAPATH => 10097,
        CURLOPT_SHARE => 10100,
        CURLOPT_ACCEPT_ENCODING => 10102,
        //CURLOPT_ENCODING => 10102,
        CURLOPT_PRIVATE => 10103,
        CURLOPT_HTTP200ALIASES => 10104,
        CURLOPT_NETRC_FILE => 10118,
        CURLOPT_FTP_ACCOUNT => 10134,
        CURLOPT_COOKIELIST => 10135,
        CURLOPT_FTP_ALTERNATIVE_TO_USER => 10147,
        CURLOPT_SSH_PUBLIC_KEYFILE => 10152,
        CURLOPT_SSH_PRIVATE_KEYFILE => 10153,
        CURLOPT_SSH_HOST_PUBLIC_KEY_MD5 => 10162,
        CURLOPT_CRLFILE => 10169,
        CURLOPT_ISSUERCERT => 10170,
        CURLOPT_USERNAME => 10173,
        CURLOPT_PASSWORD => 10174,
        CURLOPT_PROXYUSERNAME => 10175,
        CURLOPT_PROXYPASSWORD => 10176,
        CURLOPT_NOPROXY => 10177,
        CURLOPT_SOCKS5_GSSAPI_SERVICE => 10179,
        CURLOPT_SSH_KNOWNHOSTS => 10183,
        CURLOPT_MAIL_FROM => 10186,
        CURLOPT_MAIL_RCPT => 10187,
        CURLOPT_RTSP_SESSION_ID => 10190,
        CURLOPT_RTSP_STREAM_URI => 10191,
        CURLOPT_RTSP_TRANSPORT => 10192,
        CURLOPT_RESOLVE => 10203,
        CURLOPT_TLSAUTH_USERNAME => 10204,
        CURLOPT_TLSAUTH_PASSWORD => 10205,
        CURLOPT_TLSAUTH_TYPE => 10206,
        CURLOPT_DNS_SERVERS => 10211,
        CURLOPT_MAIL_AUTH => 10217,
        CURLOPT_XOAUTH2_BEARER => 10220,
        CURLOPT_DNS_INTERFACE => 10221,
        CURLOPT_DNS_LOCAL_IP4 => 10222,
        CURLOPT_DNS_LOCAL_IP6 => 10223,
        CURLOPT_LOGIN_OPTIONS => 10224,
        CURLOPT_PROXYHEADER => 10228,
        CURLOPT_PINNEDPUBLICKEY => 10230,
        CURLOPT_UNIX_SOCKET_PATH => 10231,
        CURLOPT_PROXY_SERVICE_NAME => 10235,
        CURLOPT_SERVICE_NAME => 10236,
        CURLOPT_DEFAULT_PROTOCOL => 10238,
        CURLOPT_CONNECT_TO => 10243,

        CURLOPT_RETURNTRANSFER => 19913,
        CURLOPT_BINARYTRANSFER => 19914,

        CURLOPT_WRITEFUNCTION => 20011,
        CURLOPT_READFUNCTION => 20012,
        CURLOPT_PROGRESSFUNCTION => 20056,
        CURLOPT_HEADERFUNCTION => 20079,
        CURLOPT_FNMATCH_FUNCTION => 20200,

        CURLOPT_MAX_RECV_SPEED_LARGE => 30146,
        CURLOPT_MAX_SEND_SPEED_LARGE => 30145,
    ];

    /**
     * Take control over majority of curl_*() functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptCurl(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'curl_init', self::class);
        Intercept::registerFunction(self::NAME, 'curl_close', self::class);
        Intercept::registerFunction(self::NAME, 'curl_exec', self::class);
        Intercept::registerFunction(self::NAME, 'curl_getinfo', self::class);
        Intercept::registerFunction(self::NAME, 'curl_pause', self::class);
        Intercept::registerFunction(self::NAME, 'curl_reset', self::class);
        Intercept::registerFunction(self::NAME, 'curl_setopt_array', self::class);
        Intercept::registerFunction(self::NAME, 'curl_setopt', self::class);

        // info
        Intercept::registerFunction(self::NAME, 'curl_error', self::class);
        Intercept::registerFunction(self::NAME, 'curl_errno', self::class);

        // curl_strerror
        // curl_file_create

        Intercept::registerFunction(self::NAME, 'curl_multi_init', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_close', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_add_handle', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_remove_handle', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_exec', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_select', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_getcontent', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_info_read', self::class);
        Intercept::registerFunction(self::NAME, 'curl_multi_setopt', self::class);

        // todo: curl_share_*

        self::$intercept = $level;
    }

    /**
     * @return resource|CurlHandle|false
     */
    public static function curl_init(?string $url = null)
    {
        if ($url) {
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$url], false);
        } else {
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], false);
        }
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_close($handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|bool
     */
    public static function curl_exec($handle)
    {
        $result = Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], false);

        $id = Dumper::objectId($handle);
        if (isset(self::$files[$id])) {
            fseek(self::$files[$id], 0);
            $contents = fread(self::$files[$id], 2000);
            fseek(self::$files[$id], 0);
            // todo: better response visualisation
            if (!(self::$intercept & Intercept::SILENT)) {
                Debugger::send(Packet::DUMP, Ansi::white("response:") . ' ' . Dumper::dumpString($contents));
            }
        }

        return $result;
    }

    /**
     * @param resource|CurlHandle $handle
     * @return string|string[]
     */
    public static function curl_getinfo($handle, ?int $option = null)
    {
        if ($option) {
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $option], $option ? '' : []);
        } else {
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], $option ? '' : []);
        }
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_pause($handle, int $flags): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $flags], CURLE_OK);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_reset($handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], null);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed $value
     */
    public static function curl_setopt($handle, int $option, $value): bool
    {
        if ($option === CURLOPT_FILE) {
            self::$files[Dumper::objectId($handle)] = $value;
        }

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $option, $value], true);
    }

    /**
     * @param resource|CurlHandle $handle
     * @param mixed[] $options
     */
    public static function curl_setopt_array($handle, array $options): bool
    {
        foreach ($options as $option => $value) {
            if ($option === CURLOPT_FILE) {
                self::$files[Dumper::objectId($handle)] = $value;
            }
        }

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $options], true);
    }

    // info ------------------------------------------------------------------------------------------------------------

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_error($handle): string
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], '');
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_errno($handle): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], 0);
    }

    // multi -----------------------------------------------------------------------------------------------------------

    /**
     * @return resource|CurlMultiHandle|false
     */
    public static function curl_multi_init()
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function curl_multi_close($multi_handle): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     */
    public static function curl_multi_add_handle($multi_handle, $handle): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, $handle], 0);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param resource|CurlHandle $handle
     * @return int|false
     */
    public static function curl_multi_remove_handle($multi_handle, $handle)
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, $handle], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function curl_multi_exec($multi_handle, int &$still_running): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, &$still_running], CURLM_OK);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     */
    public static function curl_multi_select($multi_handle, float $timeout = 1.0): int
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, $timeout], -1);
    }

    /**
     * @param resource|CurlHandle $handle
     */
    public static function curl_multi_getcontent($handle): ?string
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], null);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @return string[]|false
     */
    public static function curl_multi_info_read($multi_handle, int &$queued_messages = 0)
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, &$queued_messages], false);
    }

    /**
     * @param resource|CurlMultiHandle $multi_handle
     * @param mixed $value
     */
    public static function curl_multi_setopt($multi_handle, int $option, $value): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$multi_handle, $option, $value], true);
    }

}
