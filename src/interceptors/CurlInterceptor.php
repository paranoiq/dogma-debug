<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: INIFILE KEYPASSWD KRBLEVEL

namespace Dogma\Debug;

use CurlHandle;
use CurlMultiHandle;
use function fread;
use function fseek;
use const CURLE_OK;
use const CURLM_OK;
use const CURLOPT_ACCEPT_ENCODING;
use const CURLOPT_ACCEPTTIMEOUT_MS;
use const CURLOPT_ADDRESS_SCOPE;
use const CURLOPT_APPEND;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_BINARYTRANSFER;
use const CURLOPT_BUFFERSIZE;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
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
use const CURLOPT_CRLFILE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_DEFAULT_PROTOCOL;
use const CURLOPT_DIRLISTONLY;
use const CURLOPT_DNS_CACHE_TIMEOUT;
use const CURLOPT_DNS_INTERFACE;
use const CURLOPT_DNS_LOCAL_IP4;
use const CURLOPT_DNS_LOCAL_IP6;
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
use const CURLOPT_FTP_ALTERNATIVE_TO_USER;
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
use const CURLOPT_HTTP200ALIASES;
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
use const CURLOPT_ISSUERCERT;
use const CURLOPT_KEYPASSWD;
use const CURLOPT_KRBLEVEL;
use const CURLOPT_LOCALPORT;
use const CURLOPT_LOCALPORTRANGE;
use const CURLOPT_LOGIN_OPTIONS;
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
use const CURLOPT_NOPROXY;
use const CURLOPT_NOSIGNAL;
use const CURLOPT_PASSWORD;
use const CURLOPT_PATH_AS_IS;
use const CURLOPT_PINNEDPUBLICKEY;
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
use const CURLOPT_PROXY_SERVICE_NAME;
use const CURLOPT_PROXY_TRANSFER_MODE;
use const CURLOPT_PROXYAUTH;
use const CURLOPT_PROXYHEADER;
use const CURLOPT_PROXYPASSWORD;
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
use const CURLOPT_RESOLVE;
use const CURLOPT_RESUME_FROM;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_RTSP_CLIENT_CSEQ;
use const CURLOPT_RTSP_REQUEST;
use const CURLOPT_RTSP_SERVER_CSEQ;
use const CURLOPT_RTSP_SESSION_ID;
use const CURLOPT_RTSP_STREAM_URI;
use const CURLOPT_RTSP_TRANSPORT;
use const CURLOPT_SASL_IR;
use const CURLOPT_SERVICE_NAME;
use const CURLOPT_SHARE;
use const CURLOPT_SOCKS5_GSSAPI_NEC;
use const CURLOPT_SOCKS5_GSSAPI_SERVICE;
use const CURLOPT_SSH_AUTH_TYPES;
use const CURLOPT_SSH_HOST_PUBLIC_KEY_MD5;
use const CURLOPT_SSH_KNOWNHOSTS;
use const CURLOPT_SSH_PRIVATE_KEYFILE;
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
use const CURLOPT_TLSAUTH_PASSWORD;
use const CURLOPT_TLSAUTH_TYPE;
use const CURLOPT_TLSAUTH_USERNAME;
use const CURLOPT_TRANSFER_ENCODING;
use const CURLOPT_TRANSFERTEXT;
use const CURLOPT_UNIX_SOCKET_PATH;
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

    /** @var int[] */
    private static $opt = [ // @phpstan-ignore-line write-only
        CURLOPT_PORT => 'CURLOPT_PORT',
        CURLOPT_TIMEOUT => 'CURLOPT_TIMEOUT',
        CURLOPT_INFILESIZE => 'CURLOPT_INFILESIZE',
        CURLOPT_LOW_SPEED_LIMIT => 'CURLOPT_LOW_SPEED_LIMIT',
        CURLOPT_LOW_SPEED_TIME => 'CURLOPT_LOW_SPEED_TIME',
        CURLOPT_RESUME_FROM => 'CURLOPT_RESUME_FROM',
        CURLOPT_CRLF => 'CURLOPT_CRLF',
        CURLOPT_SSLVERSION => 'CURLOPT_SSLVERSION',
        CURLOPT_TIMECONDITION => 'CURLOPT_TIMECONDITION',
        CURLOPT_TIMEVALUE => 'CURLOPT_TIMEVALUE',
        CURLOPT_VERBOSE => 'CURLOPT_VERBOSE',
        CURLOPT_HEADER => 'CURLOPT_HEADER',
        CURLOPT_NOPROGRESS => 'CURLOPT_NOPROGRESS',
        CURLOPT_NOBODY => 'CURLOPT_NOBODY',
        CURLOPT_FAILONERROR => 'CURLOPT_FAILONERROR',
        CURLOPT_UPLOAD => 'CURLOPT_UPLOAD',
        CURLOPT_POST => 'CURLOPT_POST',
        CURLOPT_DIRLISTONLY => 'CURLOPT_DIRLISTONLY',
        CURLOPT_APPEND => 'CURLOPT_APPEND',
        CURLOPT_NETRC => 'CURLOPT_NETRC',
        CURLOPT_FOLLOWLOCATION => 'CURLOPT_FOLLOWLOCATION',
        CURLOPT_TRANSFERTEXT => 'CURLOPT_TRANSFERTEXT',
        CURLOPT_PUT => 'CURLOPT_PUT',
        CURLOPT_AUTOREFERER => 'CURLOPT_AUTOREFERER',
        CURLOPT_PROXYPORT => 'CURLOPT_PROXYPORT',
        CURLOPT_HTTPPROXYTUNNEL => 'CURLOPT_HTTPPROXYTUNNEL',
        CURLOPT_SSL_VERIFYPEER => 'CURLOPT_SSL_VERIFYPEER',
        CURLOPT_MAXREDIRS => 'CURLOPT_MAXREDIRS',
        CURLOPT_FILETIME => 'CURLOPT_FILETIME',
        CURLOPT_MAXCONNECTS => 'CURLOPT_MAXCONNECTS',
        CURLOPT_FRESH_CONNECT => 'CURLOPT_FRESH_CONNECT',
        CURLOPT_FORBID_REUSE => 'CURLOPT_FORBID_REUSE',
        CURLOPT_CONNECTTIMEOUT => 'CURLOPT_CONNECTTIMEOUT',
        CURLOPT_HTTPGET => 'CURLOPT_HTTPGET',
        CURLOPT_SSL_VERIFYHOST => 'CURLOPT_SSL_VERIFYHOST',
        CURLOPT_HTTP_VERSION => 'CURLOPT_HTTP_VERSION',
        CURLOPT_FTP_USE_EPSV => 'CURLOPT_FTP_USE_EPSV',
        CURLOPT_SSLENGINE_DEFAULT => 'CURLOPT_SSLENGINE_DEFAULT',
        CURLOPT_DNS_USE_GLOBAL_CACHE => 'CURLOPT_DNS_USE_GLOBAL_CACHE',
        CURLOPT_DNS_CACHE_TIMEOUT => 'CURLOPT_DNS_CACHE_TIMEOUT',
        CURLOPT_COOKIESESSION => 'CURLOPT_COOKIESESSION',
        CURLOPT_BUFFERSIZE => 'CURLOPT_BUFFERSIZE',
        CURLOPT_NOSIGNAL => 'CURLOPT_NOSIGNAL',
        CURLOPT_PROXYTYPE => 'CURLOPT_PROXYTYPE',
        CURLOPT_UNRESTRICTED_AUTH => 'CURLOPT_UNRESTRICTED_AUTH',
        CURLOPT_FTP_USE_EPRT => 'CURLOPT_FTP_USE_EPRT',
        CURLOPT_HTTPAUTH => 'CURLOPT_HTTPAUTH',
        CURLOPT_FTP_CREATE_MISSING_DIRS => 'CURLOPT_FTP_CREATE_MISSING_DIRS',
        CURLOPT_PROXYAUTH => 'CURLOPT_PROXYAUTH',
        CURLOPT_FTP_RESPONSE_TIMEOUT => 'CURLOPT_FTP_RESPONSE_TIMEOUT',
        CURLOPT_IPRESOLVE => 'CURLOPT_IPRESOLVE',
        CURLOPT_MAXFILESIZE => 'CURLOPT_MAXFILESIZE',
        CURLOPT_USE_SSL => 'CURLOPT_USE_SSL',
        CURLOPT_TCP_NODELAY => 'CURLOPT_TCP_NODELAY',
        CURLOPT_FTPSSLAUTH => 'CURLOPT_FTPSSLAUTH',
        CURLOPT_IGNORE_CONTENT_LENGTH => 'CURLOPT_IGNORE_CONTENT_LENGTH',
        CURLOPT_FTP_SKIP_PASV_IP => 'CURLOPT_FTP_SKIP_PASV_IP',
        CURLOPT_FTP_FILEMETHOD => 'CURLOPT_FTP_FILEMETHOD',
        CURLOPT_LOCALPORT => 'CURLOPT_LOCALPORT',
        CURLOPT_LOCALPORTRANGE => 'CURLOPT_LOCALPORTRANGE',
        CURLOPT_CONNECT_ONLY => 'CURLOPT_CONNECT_ONLY',
        CURLOPT_SSL_SESSIONID_CACHE => 'CURLOPT_SSL_SESSIONID_CACHE',
        CURLOPT_SSH_AUTH_TYPES => 'CURLOPT_SSH_AUTH_TYPES',
        CURLOPT_FTP_SSL_CCC => 'CURLOPT_FTP_SSL_CCC',
        CURLOPT_TIMEOUT_MS => 'CURLOPT_TIMEOUT_MS',
        CURLOPT_CONNECTTIMEOUT_MS => 'CURLOPT_CONNECTTIMEOUT_MS',
        CURLOPT_HTTP_TRANSFER_DECODING => 'CURLOPT_HTTP_TRANSFER_DECODING',
        CURLOPT_HTTP_CONTENT_DECODING => 'CURLOPT_HTTP_CONTENT_DECODING',
        CURLOPT_NEW_FILE_PERMS => 'CURLOPT_NEW_FILE_PERMS',
        CURLOPT_NEW_DIRECTORY_PERMS => 'CURLOPT_NEW_DIRECTORY_PERMS',
        CURLOPT_POSTREDIR => 'CURLOPT_POSTREDIR',
        CURLOPT_PROXY_TRANSFER_MODE => 'CURLOPT_PROXY_TRANSFER_MODE',
        CURLOPT_ADDRESS_SCOPE => 'CURLOPT_ADDRESS_SCOPE',
        CURLOPT_CERTINFO => 'CURLOPT_CERTINFO',
        CURLOPT_TFTP_BLKSIZE => 'CURLOPT_TFTP_BLKSIZE',
        CURLOPT_SOCKS5_GSSAPI_NEC => 'CURLOPT_SOCKS5_GSSAPI_NEC',
        CURLOPT_PROTOCOLS => 'CURLOPT_PROTOCOLS',
        CURLOPT_REDIR_PROTOCOLS => 'CURLOPT_REDIR_PROTOCOLS',
        CURLOPT_FTP_USE_PRET => 'CURLOPT_FTP_USE_PRET',
        CURLOPT_RTSP_REQUEST => 'CURLOPT_RTSP_REQUEST',
        CURLOPT_RTSP_CLIENT_CSEQ => 'CURLOPT_RTSP_CLIENT_CSEQ',
        CURLOPT_RTSP_SERVER_CSEQ => 'CURLOPT_RTSP_SERVER_CSEQ',
        CURLOPT_WILDCARDMATCH => 'CURLOPT_WILDCARDMATCH',
        CURLOPT_TRANSFER_ENCODING => 'CURLOPT_TRANSFER_ENCODING',
        CURLOPT_GSSAPI_DELEGATION => 'CURLOPT_GSSAPI_DELEGATION',
        CURLOPT_ACCEPTTIMEOUT_MS => 'CURLOPT_ACCEPTTIMEOUT_MS',
        CURLOPT_TCP_KEEPALIVE => 'CURLOPT_TCP_KEEPALIVE',
        CURLOPT_TCP_KEEPIDLE => 'CURLOPT_TCP_KEEPIDLE',
        CURLOPT_TCP_KEEPINTVL => 'CURLOPT_TCP_KEEPINTVL',
        CURLOPT_SSL_OPTIONS => 'CURLOPT_SSL_OPTIONS',
        CURLOPT_SASL_IR => 'CURLOPT_SASL_IR',
        CURLOPT_SSL_ENABLE_ALPN => 'CURLOPT_SSL_ENABLE_ALPN',
        CURLOPT_SSL_ENABLE_NPN => 'CURLOPT_SSL_ENABLE_NPN',
        CURLOPT_EXPECT_100_TIMEOUT_MS => 'CURLOPT_EXPECT_100_TIMEOUT_MS',
        CURLOPT_HEADEROPT => 'CURLOPT_HEADEROPT',
        CURLOPT_SSL_VERIFYSTATUS => 'CURLOPT_SSL_VERIFYSTATUS',
        CURLOPT_SSL_FALSESTART => 'CURLOPT_SSL_FALSESTART',
        CURLOPT_PATH_AS_IS => 'CURLOPT_PATH_AS_IS',
        CURLOPT_PIPEWAIT => 'CURLOPT_PIPEWAIT',
        CURLOPT_STREAM_WEIGHT => 'CURLOPT_STREAM_WEIGHT',
        CURLOPT_TFTP_NO_OPTIONS => 'CURLOPT_TFTP_NO_OPTIONS',
        CURLOPT_TCP_FASTOPEN => 'CURLOPT_TCP_FASTOPEN',

        CURLOPT_FILE => 'CURLOPT_FILE',
        CURLOPT_URL => 'CURLOPT_URL',
        CURLOPT_PROXY => 'CURLOPT_PROXY',
        CURLOPT_USERPWD => 'CURLOPT_USERPWD',
        CURLOPT_PROXYUSERPWD => 'CURLOPT_PROXYUSERPWD',
        CURLOPT_RANGE => 'CURLOPT_RANGE',
        CURLOPT_INFILE => 'CURLOPT_INFILE',
        //CURLOPT_READDATA => 'CURLOPT_READDATA', // same as INIFILE
        CURLOPT_POSTFIELDS => 'CURLOPT_POSTFIELDS',
        CURLOPT_REFERER => 'CURLOPT_REFERER',
        CURLOPT_FTPPORT => 'CURLOPT_FTPPORT',
        CURLOPT_USERAGENT => 'CURLOPT_USERAGENT',
        CURLOPT_COOKIE => 'CURLOPT_COOKIE',
        CURLOPT_HTTPHEADER => 'CURLOPT_HTTPHEADER',
        CURLOPT_SSLCERT => 'CURLOPT_SSLCERT',
        //CURLOPT_SSLCERTPASSWD => 'CURLOPT_SSLCERTPASSWD', // same as KEYPASSWD
        //CURLOPT_SSLKEYPASSWD => 'CURLOPT_SSLKEYPASSWD', // same as KEYPASSWD
        CURLOPT_KEYPASSWD => 'CURLOPT_KEYPASSWD',
        CURLOPT_QUOTE => 'CURLOPT_QUOTE',
        CURLOPT_WRITEHEADER => 'CURLOPT_WRITEHEADER',
        CURLOPT_COOKIEFILE => 'CURLOPT_COOKIEFILE',
        CURLOPT_CUSTOMREQUEST => 'CURLOPT_CUSTOMREQUEST',
        CURLOPT_STDERR => 'CURLOPT_STDERR',
        CURLOPT_POSTQUOTE => 'CURLOPT_POSTQUOTE',
        CURLOPT_INTERFACE => 'CURLOPT_INTERFACE',
        //CURLOPT_KRB4LEVEL => 'CURLOPT_KRB4LEVEL', // same as KRBLEVEL
        CURLOPT_KRBLEVEL => 'CURLOPT_KRBLEVEL',
        CURLOPT_CAINFO => 'CURLOPT_CAINFO',
        CURLOPT_TELNETOPTIONS => 'CURLOPT_TELNETOPTIONS',
        CURLOPT_RANDOM_FILE => 'CURLOPT_RANDOM_FILE',
        CURLOPT_EGDSOCKET => 'CURLOPT_EGDSOCKET',
        CURLOPT_COOKIEJAR => 'CURLOPT_COOKIEJAR',
        CURLOPT_SSL_CIPHER_LIST => 'CURLOPT_SSL_CIPHER_LIST',
        CURLOPT_SSLCERTTYPE => 'CURLOPT_SSLCERTTYPE',
        CURLOPT_SSLKEY => 'CURLOPT_SSLKEY',
        CURLOPT_SSLKEYTYPE => 'CURLOPT_SSLKEYTYPE',
        CURLOPT_SSLENGINE => 'CURLOPT_SSLENGINE',
        CURLOPT_PREQUOTE => 'CURLOPT_PREQUOTE',
        CURLOPT_CAPATH => 'CURLOPT_CAPATH',
        CURLOPT_SHARE => 'CURLOPT_SHARE',
        CURLOPT_ACCEPT_ENCODING => 'CURLOPT_ACCEPT_ENCODING',
        //CURLOPT_ENCODING => 'CURLOPT_ENCODING', // same as ACCEPT_ENCODING
        CURLOPT_PRIVATE => 'CURLOPT_PRIVATE',
        CURLOPT_HTTP200ALIASES => 'CURLOPT_HTTP200ALIASES',
        CURLOPT_NETRC_FILE => 'CURLOPT_NETRC_FILE',
        CURLOPT_FTP_ACCOUNT => 'CURLOPT_FTP_ACCOUNT',
        CURLOPT_COOKIELIST => 'CURLOPT_COOKIELIST',
        CURLOPT_FTP_ALTERNATIVE_TO_USER => 'CURLOPT_FTP_ALTERNATIVE_TO_USER',
        CURLOPT_SSH_PUBLIC_KEYFILE => 'CURLOPT_SSH_PUBLIC_KEYFILE',
        CURLOPT_SSH_PRIVATE_KEYFILE => 'CURLOPT_SSH_PRIVATE_KEYFILE',
        CURLOPT_SSH_HOST_PUBLIC_KEY_MD5 => 'CURLOPT_SSH_HOST_PUBLIC_KEY_MD5',
        CURLOPT_CRLFILE => 'CURLOPT_CRLFILE',
        CURLOPT_ISSUERCERT => 'CURLOPT_ISSUERCERT',
        CURLOPT_USERNAME => 'CURLOPT_USERNAME',
        CURLOPT_PASSWORD => 'CURLOPT_PASSWORD',
        CURLOPT_PROXYUSERNAME => 'CURLOPT_PROXYUSERNAME',
        CURLOPT_PROXYPASSWORD => 'CURLOPT_PROXYPASSWORD',
        CURLOPT_NOPROXY => 'CURLOPT_NOPROXY',
        CURLOPT_SOCKS5_GSSAPI_SERVICE => 'CURLOPT_SOCKS5_GSSAPI_SERVICE',
        CURLOPT_SSH_KNOWNHOSTS => 'CURLOPT_SSH_KNOWNHOSTS',
        CURLOPT_MAIL_FROM => 'CURLOPT_MAIL_FROM',
        CURLOPT_MAIL_RCPT => 'CURLOPT_MAIL_RCPT',
        CURLOPT_RTSP_SESSION_ID => 'CURLOPT_RTSP_SESSION_ID',
        CURLOPT_RTSP_STREAM_URI => 'CURLOPT_RTSP_STREAM_URI',
        CURLOPT_RTSP_TRANSPORT => 'CURLOPT_RTSP_TRANSPORT',
        CURLOPT_RESOLVE => 'CURLOPT_RESOLVE',
        CURLOPT_TLSAUTH_USERNAME => 'CURLOPT_TLSAUTH_USERNAME',
        CURLOPT_TLSAUTH_PASSWORD => 'CURLOPT_TLSAUTH_PASSWORD',
        CURLOPT_TLSAUTH_TYPE => 'CURLOPT_TLSAUTH_TYPE',
        CURLOPT_DNS_SERVERS => 'CURLOPT_DNS_SERVERS',
        CURLOPT_MAIL_AUTH => 'CURLOPT_MAIL_AUTH',
        CURLOPT_XOAUTH2_BEARER => 'CURLOPT_XOAUTH2_BEARER',
        CURLOPT_DNS_INTERFACE => 'CURLOPT_DNS_INTERFACE',
        CURLOPT_DNS_LOCAL_IP4 => 'CURLOPT_DNS_LOCAL_IP4',
        CURLOPT_DNS_LOCAL_IP6 => 'CURLOPT_DNS_LOCAL_IP6',
        CURLOPT_LOGIN_OPTIONS => 'CURLOPT_LOGIN_OPTIONS',
        CURLOPT_PROXYHEADER => 'CURLOPT_PROXYHEADER',
        CURLOPT_PINNEDPUBLICKEY => 'CURLOPT_PINNEDPUBLICKEY',
        CURLOPT_UNIX_SOCKET_PATH => 'CURLOPT_UNIX_SOCKET_PATH',
        CURLOPT_PROXY_SERVICE_NAME => 'CURLOPT_PROXY_SERVICE_NAME',
        CURLOPT_SERVICE_NAME => 'CURLOPT_SERVICE_NAME',
        CURLOPT_DEFAULT_PROTOCOL => 'CURLOPT_DEFAULT_PROTOCOL',
        CURLOPT_CONNECT_TO => 'CURLOPT_CONNECT_TO',

        CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
        CURLOPT_BINARYTRANSFER => 'CURLOPT_BINARYTRANSFER',

        CURLOPT_WRITEFUNCTION => 'CURLOPT_WRITEFUNCTION',
        CURLOPT_READFUNCTION => 'CURLOPT_READFUNCTION',
        CURLOPT_PROGRESSFUNCTION => 'CURLOPT_PROGRESSFUNCTION',
        CURLOPT_HEADERFUNCTION => 'CURLOPT_HEADERFUNCTION',
        CURLOPT_FNMATCH_FUNCTION => 'CURLOPT_FNMATCH_FUNCTION',

        CURLOPT_MAX_RECV_SPEED_LARGE => 'CURLOPT_MAX_RECV_SPEED_LARGE',
        CURLOPT_MAX_SEND_SPEED_LARGE => 'CURLOPT_MAX_SEND_SPEED_LARGE',
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
        //Intercept::registerFunction(self::NAME, 'curl_share_init', self::class);
        //Intercept::registerFunction(self::NAME, 'curl_share_setopt', self::class);
        //Intercept::registerFunction(self::NAME, 'curl_share_close', self::class);
        // curl_share_errno
        // curl_share_strerror

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
            if ($contents === false) {
                $contents = 'ERROR: debugger could not read curl response content';
            }
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
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $option], '');
        } else {
            return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle], []);
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
        $info = ' // ' . (self::$opt[$option] ?: '???');

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$handle, $option, $value], true, false, $info);
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
