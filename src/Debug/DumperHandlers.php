<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Consistence\Enum\Enum;
use Consistence\Enum\MultiEnum;
use DateTimeInterface;
use Dogma\Dom\Element;
use Dogma\Dom\NodeList;
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
use DOMAttr;
use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMDocumentFragment;
use DOMDocumentType;
use DOMElement;
use DOMEntity;
use DOMNodeList;
use DOMText;
use ReflectionObject;
use function array_filter;
use function array_keys;
use function array_search;
use function count;
use function get_class;
use function implode;
use function in_array;
use function is_string;
use function property_exists;
use function str_replace;
use function stream_get_meta_data;
use function strpos;
use function strrpos;
use function substr;

trait DumperHandlers
{

    /** @var bool */
    public static $useHandlers = true;

    /** @var array<class-string, callable> handlers for user-formatted dumps */
    public static $handlers = [
        Callstack::class => [self::class, 'dumpCallstack'],

        DateTimeInterface::class => [self::class, 'dumpDateTimeInterface'],
        Date::class => [self::class, 'dumpDate'],
        Time::class => [self::class, 'dumpTime'],

        DateTimeInterval::class => [self::class, 'dumpDateTimeInterval'],
        TimeInterval::class => [self::class, 'dumpTimeInterval'],
        DateInterval::class => [self::class, 'dumpDateOrNightInterval'],
        NightInterval::class => [self::class, 'dumpDateOrNightInterval'],
        DateIntervalData::class => [self::class, 'dumpDateOrNightIntervalData'],
        NightIntervalData::class => [self::class, 'dumpDateOrNightIntervalData'],

        IntervalSet::class => [self::class, 'dumpIntervalSet'],
        ModuloIntervalSet::class => [self::class, 'dumpIntervalSet'],
        DateIntervalDataSet::class => [self::class, 'dumpIntervalSet'],
        NightIntervalDataSet::class => [self::class, 'dumpIntervalSet'],

        IntEnum::class => [self::class, 'dumpIntEnum'],
        StringEnum::class => [self::class, 'dumpStringEnum'],
        IntSet::class => [self::class, 'dumpIntSet'],
        StringSet::class => [self::class, 'dumpStringSet'],
        MultiEnum::class => [self::class, 'dumpConsistenceMultiEnum'], // must precede Enum
        Enum::class => [self::class, 'dumpConsistenceEnum'],

        DOMDocument::class => [self::class, 'dumpDomDocument'],
        DOMDocumentFragment::class => [self::class, 'dumpDomDocumentFragment'],
        DOMDocumentType::class => [self::class, 'dumpDomDocumentType'],
        DOMEntity::class => [self::class, 'dumpDomEntity'],
        DOMElement::class => [self::class, 'dumpDomElement'],
        DOMNodeList::class => [self::class, 'dumpDomNodeList'],
        DOMCdataSection::class => [self::class, 'dumpDomCdataSection'],
        DOMComment::class => [self::class, 'dumpDomComment'],
        DOMText::class => [self::class, 'dumpDomText'],
        DOMAttr::class => [self::class, 'dumpDomAttr'],
        Element::class => [self::class, 'dumpDomElement'],
        NodeList::class => [self::class, 'dumpDomNodeList'],

        'stream resource' => [self::class, 'dumpStream'],
    ];

    /** @var array<class-string, callable> handlers for short dumps (single line) */
    public static $shortHandlers = [
        null => [self::class, 'dumpEntityId'],
    ];

    /** @var array<class-string> classes that are not traversed. short dumps are used if configured */
    public static $doNotTraverse = [];

    /**
     * @param object $object
     * @return string
     */
    public static function dumpEntityId($object): string
    {
        $id = '';
        if (property_exists($object, 'id')) {
            $ref = new ReflectionObject($object);
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $value = $prop->getValue($object);
            $id = self::dumpValue($value);
        }

        return $id;
    }

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

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($ti)) . $length : '';

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

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($interval)) . $length : '';

        return self::name(get_class($interval)) . self::bracket('(') . $value . self::bracket(')') . $info;
    }

    /**
     * @param DateIntervalData|NightIntervalData $interval
     * @param int $depth
     * @return string
     */
    public static function dumpDateOrNightIntervalData($interval, int $depth = 0): string
    {
        return self::name(get_class($interval)) . self::bracket('(')
            . self::value($interval->getStart()->format()) . ' ' . self::symbol('-') . ' '
            . self::value($interval->getEnd()->format()) . self::bracket(')') . self::symbol(':')
            . ' ' . self::dumpValue($interval->getData(), $depth);
    }

    /**
     * @param IntervalSet|ModuloIntervalSet $set
     * @param int $depth
     * @return string
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

    private static function dumpConsistenceMultiEnum(MultiEnum $enum): string {
        $values = $enum::getAvailableValues();
        $keys = array_keys(array_filter($values, static function ($value) use ($values): bool {
            return in_array($value, $values, true);
        }));
        $value = $enum->getValue();
        $value = is_string($value) ? self::string($value) : self::int((string) $value);

        return self::name(get_class($enum)) . self::bracket('(')
            . $value . ' ' . self::symbol('/') . ' '
            . self::value2(implode('|', $keys))
            . self::bracket(')');
    }

    private static function dumpConsistenceEnum(Enum $enum): string {
        $key = array_search($enum->getValue(), $enum::getAvailableValues() ,true);
        $value = $enum->getValue();
        $value = is_string($value) ? self::string($value) : self::int((string) $value);

        return self::name(get_class($enum)) . self::bracket('(')
            . $value . ' ' . self::symbol('/') . ' '
            . self::value2($key)
            . self::bracket(')');
    }

    public static function dumpStream($resource, int $depth = 0): string
    {
        return self::resource('stream resource') . self::bracket('(')
            . ' ' . self::info('#' . (int) $resource)
            . self::dumpVariables(stream_get_meta_data($resource), $depth)
            . self::bracket(')');
    }

}