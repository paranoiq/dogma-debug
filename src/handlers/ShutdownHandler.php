<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function function_exists;
use function in_array;
use function pcntl_async_signals;
use function pcntl_signal;
use function sapi_windows_set_ctrl_handler;
use function spl_object_hash;
use const PHP_WINDOWS_EVENT_CTRL_BREAK;
use const PHP_WINDOWS_EVENT_CTRL_C;
use const SIG_DFL;

/**
 * Tracks signals, exit() and die() and tries to determine what lead to process termination
 *
 * PHP request shutdown steps:
 * - call all functions registered via register_shutdown_function()
 * - call all __destruct() methods
 * - empty all output buffers
 * - end all PHP extensions (e.g. sessions)
 * - turn off output layer (send HTTP headers, terminate output handlers etc.)
 *
 * @see https://phpfashion.com/jak-probiha-shutdown-v-php-a-volani-destruktoru
 * @see https://www.phpinternalsbook.com/php7/extensions_design/php_lifecycle.html
 * @see https://abhishekjakhotiya.medium.com/php-fpm-shutdown-behavior-814e49308ae0
 *
 * @see https://man7.org/linux/man-pages/man7/signal.7.html
 * @see https://stackoverflow.com/questions/3333276/signal-handling-on-windows
 */
class ShutdownHandler
{

    public const NAME = 'shutdown';

    /** @var bool - announce when event happens in destructor call after shutdown handlers has finished */
    public static $announceDestructorCallsAfterShutdown = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled = false;

    /** @var string */
    private static $currentDestructorObject;

    /**
     * @param int[] $ignoreSignals
     */
    public static function enable(array $ignoreSignals = []): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if (function_exists('pcntl_signal')) {
            // cannot set handler
            $ignoreSignals[] = Signals::$all['kill'];
            $ignoreSignals[] = Signals::$all['stop'];

            // handled by other handler
            if (ResourcesHandler::enabled()) {
                $ignoreSignals[] = Signals::$all['alarm'];
            }

            foreach (Signals::$all as $signal) {
                if (in_array($signal, $ignoreSignals, true)) {
                    continue;
                }
                pcntl_signal($signal, [self::class, 'signal']);
            }
        } elseif (function_exists('sapi_windows_set_ctrl_handler') && Request::isCli()) {
            sapi_windows_set_ctrl_handler([self::class, 'winSignal'], true);
        } else {
            return;
        }

        self::$enabled = true;
    }

    public static function disable(): void
    {
        if (function_exists('pcntl_signal')) {
            foreach (Signals::$all as $signal) {
                // set back to default
                pcntl_signal($signal, SIG_DFL);
            }
        } elseif (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler([self::class, 'winSignal'], false);
        } else {
            return;
        }

        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @param mixed $info
     */
    public static function signal(int $signal, $info): void
    {
        $name = Signals::getSignalName($signal);

        if (Signals::isTerminating($signal)) {
            Debugger::setTermination("signal ({$name})");
            exit;
        } elseif (!in_array($signal, Signals::$ignore, true)) {
            Debugger::send(Message::ERROR, Ansi::white(" Signal {$name} received. ", Ansi::DMAGENTA));
        }
    }

    /**
     * @param int $signal (PHP_WINDOWS_EVENT_CTRL_C | PHP_WINDOWS_EVENT_CTRL_BREAK)
     */
    public static function winSignal(int $signal): void
    {
        $name = $signal === PHP_WINDOWS_EVENT_CTRL_C ? 'ctrl-c'
            : ($signal === PHP_WINDOWS_EVENT_CTRL_BREAK ? 'ctrl-break' : 'unknown');

        Debugger::setTermination("signal ({$name})");
        exit;
    }

    public static function announceDestructorCallAfterShutdown(): void
    {
        Debugger::guarded(static function (): void {
            foreach (Callstack::get()->frames as $frame) {
                if ($frame->object !== null && $frame->class !== null && $frame->function === '__destruct') {
                    $object = $frame->object;
                    $oid = spl_object_hash($object);

                    if (self::$currentDestructorObject === $oid) {
                        return;
                    }
                    self::$currentDestructorObject = $oid;

                    $prevDepth = Dumper::$config->maxDepth;
                    Dumper::$config->maxDepth = 2;
                    $message = Ansi::white(" Called destructor of: ", Ansi::DBLUE) . ' ' . Dumper::dumpValue($object, 0);
                    Dumper::$config->maxDepth = $prevDepth;

                    Debugger::send(Message::EVENT, $message);
                }
            }
        }, __CLASS__, __FUNCTION__);
    }

}
