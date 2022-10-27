<?php declare(strict_types = 1);

namespace Dogma\Debug;

error_reporting(E_ALL);
ini_set('display_errors', 'on');
set_time_limit(0);

// application and environment -----------------------------------------------------------------------------------------

// less spam from OS
cleanupServerVariables();

Request::autodetectApps();

Dumper::$alwaysShowArrayKeys = true;

// missing files on Vagrant
if (Request::urlMatches('~\\.(jpg|png)$~')) {
    Request::$application = 'images';
}
if (isset($_SERVER['VG_ENV'])) {
    Request::$environment = 'vagrant';
}

// ignored sources - not configuring or starting debugger
if (Request::isAny(['roundcube', 'require-checker', 'adminer', 'self-tests', 'images'])) {
    //return;
} elseif (Request::isAny(['composer', 'phpstan', 'phpcs', 'phplint', 'nette-tester', 'nette-test'])) {
    return;
} elseif (Request::commandMatches('~amphp/parallel/lib/Context/Internal/process-runner.php~')) {
    //return;
}

// debugger info -------------------------------------------------------------------------------------------------------

Debugger::$connection = Debugger::CONNECTION_FILE;
Debugger::$maxMessageLength = 10000000000;

Dumper::$escapeAllNonAscii = true;

// report PHP files, which has been modified to enable tracking
Intercept::$logReplacements = false;
Intercept::$logAttempts = true;
//Intercept::$logReplacementsForHandlers = ['file', 'phar'];

// logging file i/o operations, rewriting code, redirecting files (should be before first Intercept)
FileStreamWrapper::enable(StreamWrapper::NONE);
PharStreamWrapper::enable(StreamWrapper::NONE);

// environment specific ------------------------------------------------------------------------------------------------

// hacking the environment/filesystem
if (!Request::isOn('vagrant')) {
    // hack dibi loading via readlink() in constants.php
    FileStreamWrapper::$pathRedirects['/dibi/dibi/src/'] = 'c:/http/shoptet/forge/vagrant/src/libraries/dibi/dibi/src/';

    // hack absolute path to realpaths file
    FileStreamWrapper::addVirtualFile('/var/www/src/cms4.master.setup.realpaths.json', '{
        "SOURCE_ROOT": "c:/http/shoptet/forge/vagrant/src/cms4",
        "VENDOR_PATH": "c:/http/shoptet/forge/vagrant/src/libraries",
        "TEMPLATES_ROOT": "c:/http/shoptet/forge/vagrant/src/templates",
        "CUSTOM_TEMPLATES_ROOT": "c:/http/shoptet/forge/vagrant/src/templates-custom",
        "RELEASE_HASH": "vgrlshash",
        "RELEASE_TEMP": "c:/http/shoptet/_temp"
    }');

    // show what is being logged manually
    Intercept::registerMethod('logException', ['Debugging_Helper_DebuggerConfigurator', 'logException'], [ExceptionHandler::class, 'log']);

    // prevent mails outside vagrant
    MailInterceptor::interceptMail(Intercept::PREVENT_CALLS);
}

Debugger::$beforeStart[] = static function (): void {
    // marker for next test run
    if (Request::is('codeception')) {
        rl(str_repeat(' ', 196));
    }

    //rd($_SERVER);
};
Debugger::$beforeShutdown[] = static function (): void {
    //rd(ResourcesHandler::class);
    //rd(ini_get_all());
    //Ascii::print();
};

// debugger settings ---------------------------------------------------------------------------------------------------

// showing HTTP request/response headers etc. (must be before init)
RequestHandler::$requestHeaders = false;
//RequestHandler::$requestBody = true;
//RequestHandler::$responseHeaders = true;
//RequestHandler::interceptHeaders(Intercept::LOG_CALLS);
//RequestHandler::interceptCookies(Intercept::LOG_CALLS);

// starts debugger and renders header immediately
// (otherwise it is started when event happens and is completely silent on requests without any events)
Debugger::init();
Dumper::$trimPathPrefixes[] = '~^C:/http/sqlftw/~';
Dumper::$trimPathPrefixes[] = '~^C:/http/shoptet/forge/vagrant/src/~';
Dumper::$trimPathPrefixes[] = '~^/vagrant-src/~';

// logging uncatched exceptions
ExceptionHandler::enable();
/*ExceptionHandler::inspectThrownExceptions([], [
    \Symfony\Component\Console\Exception\RuntimeException::class,
    \PHPUnit\Framework\ExpectationFailedException::class
]);*/
ExceptionHandler::$filterTrace = true;

// logging errors, warnings and notices
ErrorHandler::enable(E_ALL | ErrorHandler::E_UNCATCHABLE_ERROR, true, 30);
ErrorHandler::$filterTrace = true;

// force error handlers
ErrorInterceptor::interceptErrorHandlers(Intercept::PREVENT_CALLS | Intercept::SILENT);
ErrorInterceptor::interceptExceptionHandlers(Intercept::PREVENT_CALLS | Intercept::SILENT);

// resources
//ResourcesHandler::enable(1, 2000);
//ResourcesHandler::interceptAlarm();

// signals, exit(), shutdown handlers
ShutdownHandler::enable();
//ShutdownHandler::interceptSignals();
//ShutdownHandler::interceptShutdown(Intercept::PREVENT_CALLS);

// measuring and logging output
//OutputHandler::enable();
//OutputHandler::$printOutput = true;

// MySQL (initialized in DebuggerConfigurator.php)
SqlHandler::$logEvents = SqlHandler::NONE;

// for Redis and Rabbit
FilesystemInterceptor::interceptFileFunctions(Intercept::SILENT);

// Redis
//RedisHandler::enableForPredis();
RedisHandler::$traceFilters[] = '~Cache_Redis~';
RedisHandler::$traceFilters[] = '~^Shoptet\\\\Session\\\\Handler~';
RedisHandler::$traceFilters[] = '~^session_start$~';

// Rabbit
AmqpHandler::enableForAmqp();
AmqpHandler::$traceFilters[] = '~Cache_Redis~';

//Intercept::registerClass('user', \PDO::class, FakePdo::class);
//Intercept::registerNoCatch('user', PDOException::class);
//Intercept::registerNoCatch('user', Exception::class);

//CurlInterceptor::interceptCurl();
//DnsInterceptor::interceptDns();
//MysqliInterceptor::interceptMysqli();

// ignored errors ------------------------------------------------------------------------------------------------------

ErrorHandler::$ignore = [
    'Notice: Trying to access array offset on value of type null' => [
        '/Dibi/Fluent.php:466', // new
        '/Dibi/Fluent.php:467', // old
    ],
    'Notice: session_start(): A session had already been started - ignoring' => [
        '/cms/libs/filemanager/config/config.php:2',
        '/cms/index.php:115',
    ],
    'Warning: stat(): stat failed for' => [
        '/smarty/distribution/libs/sysplugins/smarty_internal_template.php:202',
    ],
    'Warning: filemtime(): stat failed for' => [
        '/smarty/distribution/libs/sysplugins/smarty_resource.php:349',
        '/smarty/distribution/libs/sysplugins/smarty_resource.php:720'
    ],
    'Warning: "continue" targeting switch is equivalent to "break". Did you mean to use "continue 2"?' => [
        '/phpoffice/phpexcel/Classes/PHPExcel/Shared/OLE.php:290',
    ],
    'Notice: Undefined offset' => [
        'mpdf/mpdf/src/TTFontFile.php:4536',
        'mpdf/mpdf/src/TTFontFile.php:4627',
    ],

    // WTF?
    'Warning: Use of undefined constant SMARTSUPP_KEY - assumed \'SMARTSUPP_KEY\'' => [
        '/admin/controllers/SmartsuppChat.php:30',
    ],

    // PHP 7.4
    'Deprecated: The each() function is deprecated. This message will be suppressed on further calls' => [
        '/smarty/distribution/libs/sysplugins/smarty_internal_compilebase.php:75',
    ],
    'Deprecated: Array and string offset access syntax with curly braces is deprecated' => [
        '/phpoffice/phpexcel/Classes/PHPExcel/Shared/OLE.php:450',
    ],

    // magic tracking
    'User Notice: Undefined property via __get()' => [
        '/cms/packages/Core/Model.php:26',
    ],

    // poking project lock
    'Warning: stat(): stat failed for /var/www/projects/46/46/user/temp/project.lock' => [
        '/dogma-debug/src/handlers/FileHandler.php:623',
    ],

    // PHPStan 0.12 on PHP 8.1
    'Return type of Hoa\Iterator\Buffer::next() should either be compatible with Iterator::next' => [
        '/hoa/iterator/Buffer.php:191',
    ],
    'Deprecated: strpos(): Passing null to parameter #1 ($haystack) of type string is deprecated' => [
        '/hoa/compiler/Llk/Rule/Rule.php:163',
    ],

    // some Codeception bullshit
    'Warning: preg_match(): Delimiter must not be alphanumeric or backslash' => [
        '/phpunit/phpunit/src/Util/RegularExpression.php:22',
    ],
];

function cleanupServerVariables(): void
{
    // Windows bullshit
    foreach ([
        'ChocolateyInstall', 'ChocolateyLastPathUpdate', 'ChocolateyToolsLocation',
        'FPS_BROWSER_APP_PROFILE_STRING', 'FPS_BROWSER_USER_PROFILE_STRING',
        'GPU_MAX_ALLOC_PERCENT', 'GPU_MAX_HEAP_SIZE', 'GPU_SINGLE_ALLOC_PERCENT',
        'MOZ_PLUGIN_PATH',
        'OneDrive', 'OneDriveConsumer',
        'SAN_DIR',
        'VBOX_MSI_INSTALL_PATH',

        // 'APPDATA' - used by Composer
        'ALLUSERSPROFILE', 'HOMEPATH', 'LOCALAPPDATA', 'LOGONSERVER', 'HOMEDRIVE', 'SystemDrive',
        'configsetroot', 'DriverData', 'CommonProgramFiles', 'CommonProgramFiles(x86)', 'CommonProgramW6432',
        'ProgramData', 'ProgramFiles', 'ProgramFiles(x86)', 'ProgramW6432', 'SystemRoot', 'windir',
        'USERDOMAIN', 'USERDOMAIN_ROAMINGPROFILE', 'USERNAME', 'USERPROFILE',
        'ComSpec', 'PSModulePath', 'PROMPT', 'PUBLIC',
    ] as $key) {
        unset($_SERVER[$key]);
    }

    // Linux cli bullshit
    foreach ([
        'MAIL', 'USER', 'HOME', 'LOGNAME', 'CDPATH',
        'SSH_TTY', 'SSH_CLIENT', 'SSH_AUTH_SOCK', 'SSH_CONNECTION',
        'GIT_ASKPASS', 'OLDPWD', 'SHLVL', 'LS_COLORS', 'LS_OPTIONS',
        'COMPOSER_BINARY', '_',
    ] as $key) {
        unset($_SERVER[$key]);
    }
}
