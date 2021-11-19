<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use DateTimeInterface;
use Dogma\Enum\IntEnum;
use Dogma\Enum\IntSet;
use Dogma\Enum\StringEnum;
use Dogma\Enum\StringSet;
use Dogma\Math\Interval\IntervalSet;
use Dogma\Math\Interval\ModuloIntervalSet;
use Dogma\Time\Date;
use Dogma\Time\Interval\DateInterval;
use Dogma\Time\Interval\DateTimeInterval;
use Dogma\Time\Interval\NightInterval;
use Dogma\Time\Interval\TimeInterval;
use Dogma\Time\IntervalData\DateIntervalData;
use Dogma\Time\IntervalData\NightIntervalData;
use Dogma\Time\Time;
use function count;
use function get_class;
use function implode;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;

trait DumperFormattersDogma
{

    public static function dumpCallstack(Callstack $callstack, int $depth = 0): string
    {
        return self::name(get_class($callstack)) . ' ' . self::dumpValue($callstack->frames, $depth);
    }

    public static function dumpDateTimeInterface(DateTimeInterface $dt): string
    {
        $value = str_replace('.000000', '', $dt->format('Y-m-d H:i:s.u'));
        $timeZone = $dt->format('P') === $dt->getTimezone()->getName() ? '' : ' ' . self::value($dt->getTimezone()->getName());
        $dst = $dt->format('I') ? ' ' . self::value2('DST') : '';
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($dt)) : '';

        return self::name(get_class($dt)) . self::bracket('(')
            . self::value($value) . self::value2($dt->format('P')) . $timeZone . $dst
            . self::bracket(')') . $info;
    }

    public static function dumpDate(Date $date): string
    {
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($date)) : '';

        return self::name(get_class($date)) . self::bracket('(')
            . self::value($date->format('Y-m-d')) . ' ' . self::symbol('/') . ' '
            . self::value2((string) $date->getJulianDay())
            . self::bracket(')') . $info;
    }

    public static function dumpTime(Time $time): string
    {
        $value = str_replace('.000000', '', $time->format('H:i:s.u'));
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($time)) : '';

        return self::name(get_class($time)) . self::bracket('(')
            . self::value($value) . ' ' . self::symbol('/') . ' '
            . self::value2((string) $time->getMicroTime())
            . self::bracket(')') . $info;
    }

    public static function dumpDateTimeInterval(DateTimeInterval $dti): string
    {
        if ($dti->isEmpty()) {
            $value = self::value('empty');
            $length = '';
        } else {
            $start = $dti->getStart();
            $startValue = str_replace('.000000', '', $start->format('Y-m-d H:i:s.u'));
            $startOffset = $start->format('P');

            $end = $dti->getEnd();
            $endValue = str_replace('.000000', '', $end->format('Y-m-d H:i:s.u'));
            $endOffset = $end->format('P');

            $startDst = $start->format('I') ? ' ' . self::value2('DST') : '';
            $startTzName = $start->getTimezone()->getName();
            $endDst = $end->format('I') ? ' ' . self::value2('DST') : '';
            $endTzName = $end->getTimezone()->getName();

            if ($startTzName !== $endTzName || $startDst !== $endDst) {
                $offsets = $startOffset === $startTzName && $endOffset === $endTzName;
                $timeZone = ($offsets ? '' : ' ' . self::value($startTzName)) . $startDst . ' '
                    . self::symbol('-') . ($offsets ? '' : ' ' . self::value($endTzName)) . $endDst;
            } else {
                $timeZone = ' ' . self::value($startTzName) . $startDst;
            }

            $value = self::value($startValue) . self::value2($startOffset) . ' ' . self::symbol('-') . ' '
                . self::value($endValue) . self::value2($endOffset)
                . $timeZone;

            $length = ', length: ' . $dti->getSpan()->format();
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($dti) . $length) : '';

        return self::name(get_class($dti)) . self::bracket('(') . $value . self::bracket(')') . $info;
    }

    public static function dumpTimeInterval(TimeInterval $ti): string
    {
        if ($ti->isEmpty()) {
            $value = self::value('empty');
            $length = '';
        } else {
            $startValue = str_replace('.000000', '', $ti->getStart()->format('H:i:s.u'));
            $endValue = str_replace('.000000', '', $ti->getEnd()->format('H:i:s.u'));
            $value = self::value($startValue) . ' ' . self::symbol('-') . ' ' . self::value($endValue);
            $length = ', length: ' . $ti->getTimeSpan()->format();
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($ti) . $length) : '';

        return self::name(get_class($ti)) . self::bracket('(') . $value . self::bracket(')') . $info;
    }

    /**
     * @param DateInterval|NightInterval $interval
     * @return string
     */
    public static function dumpDateOrNightInterval($interval): string
    {
        if ($interval->isEmpty()) {
            $value = self::value('empty');
            $length = '';
        } else {
            $value = self::value($interval->getStart()->format()) . ' ' . self::symbol('-') . ' ' . self::value($interval->getEnd()->format());
            if ($interval instanceof DateInterval) {
                $length = $interval->getDayCount();
                $length = ', ' . $length . ($length > 1 ? ' days' : ' day');
            } else {
                $length = $interval->getNightsCount();
                $length = ', ' . $length . ($length > 1 ? ' nights' : ' night');
            }
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($interval) . $length) : '';

        return self::name(get_class($interval)) . self::bracket('(') . $value . self::bracket(')') . $info;
    }

    /**
     * @param DateIntervalData|NightIntervalData $interval
     */
    public static function dumpDateOrNightIntervalData($interval, int $depth = 0): string
    {
        return self::name(get_class($interval)) . self::bracket('(')
            . self::value($interval->getStart()->format()) . ' ' . self::symbol('-') . ' '
            . self::value($interval->getEnd()->format()) . self::bracket(')') . self::symbol(':')
            . ' ' . self::dumpValue($interval->getData(), $depth);
    }

    /**
     * @param IntervalSet<mixed>|ModuloIntervalSet<mixed> $set
     */
    public static function dumpIntervalSet($set, int $depth = 0): string
    {
        $coma = self::symbol(',');

        $items = [];
        foreach ($set->getIntervals() as $interval) {
            $item = self::indent($depth + 1) . self::dumpValue($interval, $depth + 1);
            $pos = strrpos($item, self::infoPrefix());
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
            } else {
                $item .= $coma;
            }
            $items[] = $item;
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($set) . ', ' . count($items) . ' items') : '';

        return self::name(get_class($set)) . self::bracket('[') . "\n"
            . implode("\n", $items) . "\n"
            . self::indent($depth) . self::bracket(']') . $info;
    }

    public static function dumpIntEnum(IntEnum $enum): string
    {
        return self::name(get_class($enum)) . self::bracket('(')
            . self::int((string) $enum->getValue()) . ' ' . self::symbol('/') . ' '
            . self::value2($enum->getConstantName())
            . self::bracket(')');
    }

    public static function dumpStringEnum(StringEnum $enum): string
    {
        return self::name(get_class($enum)) . self::bracket('(')
            . self::string($enum->getValue()) . ' ' . self::symbol('/') . ' '
            . self::value2($enum->getConstantName())
            . self::bracket(')');
    }

    public static function dumpIntSet(IntSet $set): string
    {
        return self::name(get_class($set)) . self::bracket('(')
            . self::int((string) $set->getValue()) . ' ' . self::symbol('/') . ' '
            . self::value2(implode('|', $set->getConstantNames()))
            . self::bracket(')');
    }

    public static function dumpStringSet(StringSet $set): string
    {
        return self::name(get_class($set)) . self::bracket('(')
            . self::string($set->getValue()) . ' ' . self::symbol('/') . ' '
            . self::value2(implode('|', $set->getConstantNames()))
            . self::bracket(')');
    }

}
