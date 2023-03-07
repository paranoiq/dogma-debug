<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

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
use Dogma\Time\IntervalData\DateIntervalDataSet;
use Dogma\Time\IntervalData\NightIntervalData;
use Dogma\Time\IntervalData\NightIntervalDataSet;
use Dogma\Time\Time;
use Throwable;
use function count;
use function get_class;
use function implode;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;

class FormattersDogma
{

    public static function register(): void
    {
        Dumper::$objectFormatters[Date::class] = [self::class, 'dumpDate'];
        Dumper::$objectFormatters[Time::class] = [self::class, 'dumpTime'];

        Dumper::$objectFormatters[DateTimeInterval::class] = [self::class, 'dumpDateTimeInterval'];
        Dumper::$objectFormatters[TimeInterval::class] = [self::class, 'dumpTimeInterval'];
        Dumper::$objectFormatters[DateInterval::class] = [self::class, 'dumpDateOrNightInterval'];
        Dumper::$objectFormatters[NightInterval::class] = [self::class, 'dumpDateOrNightInterval'];
        Dumper::$objectFormatters[DateIntervalData::class] = [self::class, 'dumpDateOrNightIntervalData'];
        Dumper::$objectFormatters[NightIntervalData::class] = [self::class, 'dumpDateOrNightIntervalData'];

        Dumper::$objectFormatters[IntervalSet::class] = [self::class, 'dumpIntervalSet'];
        Dumper::$objectFormatters[ModuloIntervalSet::class] = [self::class, 'dumpIntervalSet'];
        Dumper::$objectFormatters[DateIntervalDataSet::class] = [self::class, 'dumpIntervalSet'];
        Dumper::$objectFormatters[NightIntervalDataSet::class] = [self::class, 'dumpIntervalSet'];

        Dumper::$objectFormatters[IntEnum::class] = [self::class, 'dumpIntEnum'];
        Dumper::$objectFormatters[StringEnum::class] = [self::class, 'dumpStringEnum'];
        Dumper::$objectFormatters[IntSet::class] = [self::class, 'dumpIntSet'];
        Dumper::$objectFormatters[StringSet::class] = [self::class, 'dumpStringSet'];
    }

    public static function dumpDate(Date $date): string
    {
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($date)) : '';

        return Dumper::class(get_class($date)) . Dumper::bracket('(')
            . Dumper::value($date->format('Y-m-d')) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2((string) $date->getJulianDay())
            . Dumper::bracket(')') . $info;
    }

    public static function dumpTime(Time $time): string
    {
        $value = str_replace('.000000', '', $time->format('H:i:s.u'));
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($time)) : '';

        return Dumper::class(get_class($time)) . Dumper::bracket('(')
            . Dumper::value($value) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2((string) $time->getMicroTime())
            . Dumper::bracket(')') . $info;
    }

    public static function dumpDateTimeInterval(DateTimeInterval $dti): string
    {
        if ($dti->isEmpty()) {
            $value = Dumper::value('empty');
            $length = '';
        } else {
            $start = $dti->getStart();
            $startValue = str_replace('.000000', '', $start->format('Y-m-d H:i:s.u'));
            $startOffset = $start->format('P');

            $end = $dti->getEnd();
            $endValue = str_replace('.000000', '', $end->format('Y-m-d H:i:s.u'));
            $endOffset = $end->format('P');

            $startDst = $start->format('I') ? ' ' . Dumper::value2('DST') : '';
            $startTzName = $start->getTimezone()->getName();
            $endDst = $end->format('I') ? ' ' . Dumper::value2('DST') : '';
            $endTzName = $end->getTimezone()->getName();

            if ($startTzName !== $endTzName || $startDst !== $endDst) {
                $offsets = $startOffset === $startTzName && $endOffset === $endTzName;
                $timeZone = ($offsets ? '' : ' ' . Dumper::value($startTzName)) . $startDst . ' '
                    . Dumper::symbol('-') . ($offsets ? '' : ' ' . Dumper::value($endTzName)) . $endDst;
            } else {
                $timeZone = ' ' . Dumper::value($startTzName) . $startDst;
            }

            $value = Dumper::value($startValue) . Dumper::value2($startOffset) . ' ' . Dumper::symbol('-') . ' '
                . Dumper::value($endValue) . Dumper::value2($endOffset)
                . $timeZone;

            $length = ', length: ' . $dti->getSpan()->format();
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($dti) . $length) : '';

        return Dumper::class(get_class($dti)) . Dumper::bracket('(') . $value . Dumper::bracket(')') . $info;
    }

    public static function dumpTimeInterval(TimeInterval $ti): string
    {
        if ($ti->isEmpty()) {
            $value = Dumper::value('empty');
            $length = '';
        } else {
            $startValue = str_replace('.000000', '', $ti->getStart()->format('H:i:s.u'));
            $endValue = str_replace('.000000', '', $ti->getEnd()->format('H:i:s.u'));
            $value = Dumper::value($startValue) . ' ' . Dumper::symbol('-') . ' ' . Dumper::value($endValue);
            $length = ', length: ' . $ti->getTimeSpan()->format();
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($ti) . $length) : '';

        return Dumper::class(get_class($ti)) . Dumper::bracket('(') . $value . Dumper::bracket(')') . $info;
    }

    /**
     * @param DateInterval|NightInterval $interval
     * @return string
     */
    public static function dumpDateOrNightInterval($interval): string
    {
        if ($interval->isEmpty()) {
            $value = Dumper::value('empty');
            $length = '';
        } else {
            $value = Dumper::value($interval->getStart()->format()) . ' ' . Dumper::symbol('-') . ' ' . Dumper::value($interval->getEnd()->format());
            if ($interval instanceof DateInterval) {
                $length = $interval->getDayCount();
                $length = ', ' . $length . ($length > 1 ? ' days' : ' day');
            } else {
                $length = $interval->getNightsCount();
                $length = ', ' . $length . ($length > 1 ? ' nights' : ' night');
            }
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($interval) . $length) : '';

        return Dumper::class(get_class($interval)) . Dumper::bracket('(') . $value . Dumper::bracket(')') . $info;
    }

    /**
     * @param DateIntervalData|NightIntervalData $interval
     */
    public static function dumpDateOrNightIntervalData($interval, int $depth = 0): string
    {
        return Dumper::class(get_class($interval)) . Dumper::bracket('(')
            . Dumper::value($interval->getStart()->format()) . ' ' . Dumper::symbol('-') . ' '
            . Dumper::value($interval->getEnd()->format()) . Dumper::bracket(')') . Dumper::symbol(':')
            . ' ' . Dumper::dumpValue($interval->getData(), $depth);
    }

    /**
     * @param IntervalSet<mixed>|ModuloIntervalSet<mixed> $set
     */
    public static function dumpIntervalSet($set, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $items = [];
        foreach ($set->getIntervals() as $interval) {
            $item = Dumper::indent($depth + 1) . Dumper::dumpValue($interval, $depth + 1);
            $pos = strrpos($item, Dumper::infoPrefix());
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
            } else {
                $item .= $coma;
            }
            $items[] = $item;
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($set) . ', ' . count($items) . ' items') : '';

        return Dumper::class(get_class($set)) . Dumper::bracket('[') . "\n"
            . implode("\n", $items) . "\n"
            . Dumper::indent($depth) . Dumper::bracket(']') . $info;
    }

    public static function dumpIntEnum(IntEnum $enum): string
    {
        try {
            @$const = $enum->getConstantName();
        } catch (Throwable $e) {
            // strange uninitialized enum bug : [
            $const = '__UNKNOWN__';
        }

        return Dumper::class(get_class($enum)) . Dumper::bracket('(')
            . Dumper::int((string) $enum->getValue()) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2($const)
            . Dumper::bracket(')');
    }

    public static function dumpStringEnum(StringEnum $enum): string
    {
        try {
            @$const = $enum->getConstantName();
        } catch (Throwable $e) {
            // strange uninitialized enum bug : [
            $const = '__UNKNOWN__';
        }

        return Dumper::class(get_class($enum)) . Dumper::bracket('(')
            . Dumper::string($enum->getValue()) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2($const)
            . Dumper::bracket(')');
    }

    public static function dumpIntSet(IntSet $set): string
    {
        $names = implode('|', $set->getConstantNames());
        if ($names === '') {
            $names = 'empty';
        }

        return Dumper::class(get_class($set)) . Dumper::bracket('(')
            . Dumper::int((string) $set->getValue()) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2($names)
            . Dumper::bracket(')');
    }

    public static function dumpStringSet(StringSet $set): string
    {
        return Dumper::class(get_class($set)) . Dumper::bracket('(')
            . Dumper::string($set->getValue()) . ' ' . Dumper::symbol('/') . ' '
            . Dumper::value2(implode('|', $set->getConstantNames()))
            . Dumper::bracket(')');
    }

}
