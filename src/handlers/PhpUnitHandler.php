<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use PHPUnit\Framework\TestCase;
use function end;
use function explode;
use function is_a;
use function ltrim;
use function method_exists;
use function rtrim;
use function str_contains;
use function str_starts_with;

class PhpUnitHandler
{

    /** @var bool */
    public static $announceTestCaseName = true;

    /** @var bool */
    public static $useFullTestCaseName = false;

    /** @var string|null */
    private static $currentTestCaseName;

    public static function announceTestCaseName(): void
    {
        Debugger::guarded(static function (): void {
            // not officially hooked into PHPUnit
            // just fetches test case object from backtrace method call params and gets current test case from there
            foreach (Callstack::get()->frames as $frame) {
                if (is_a($frame->class, TestCase::class, true) && str_starts_with($frame->function, 'test')) {
                    if (self::$useFullTestCaseName) {
                        $class = $frame->class;
                    } else {
                        $parts = explode('\\', $frame->class);
                        $class = end($parts);
                    }

                    /** @var TestCase $testCase */
                    $testCase = $frame->object;
                    if (method_exists($testCase, 'dataSetAsString')) {
                        $dataSetName = $testCase->dataSetAsString();
                    } else {
                        $dataSetName = $testCase->getDataSetAsString();
                    }

                    $args = Dumper::dumpArguments($frame->getNamedArgs(), Dumper::$config);
                    $args = ltrim(rtrim($args, ','));
                    $message = Ansi::white(" Test case{$dataSetName}: ", Ansi::LBLUE) . ' ' . Dumper::class($class, Dumper::$config) . '::' . Dumper::function($frame->function) . '(' . $args . '): ';

                    if ($message !== self::$currentTestCaseName && !str_contains($message, 'recurrence of')) {
                        Debugger::send(Message::CALLSTACK, $message);
                    }

                    if (!str_contains($message, 'recurrence of')) {
                        self::$currentTestCaseName = $message;
                    }
                }
            }
        }, __CLASS__, __FUNCTION__);
    }

}
