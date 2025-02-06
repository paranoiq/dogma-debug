<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use DateTimeInterface;
use function array_fill;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_sum;
use function array_values;
use function bin2hex;
use function count;
use function is_float;
use function is_int;
use function key;
use function ksort;
use function max;
use function mb_strlen;
use function mb_substr;
use function min;
use function preg_match;
use function preg_replace;
use function preg_split;
use function round;
use function str_repeat;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function strval;
use function substr;
use function trim;
use const PHP_INT_MAX;

class TableDumper
{

    private const NUMBER = 1;
    private const TEXT = 2;
    private const BINARY = 3;
    private const DATE = 4;
    private const TIME = 5;
    private const DATETIME = 6;
    private const DATETIME_US = 7;

    /**
     * @param iterable<array<string, string> $source
     */
    public static function dump(iterable $source): string
    {
        if (key($source) === null) {
            // todo
            return 'empty';
        }
        $oldInfo = Dumper::$showInfo;
        $oldHex = Dumper::$binaryWithHexadecimal;
        Dumper::$showInfo = false;
        Dumper::$binaryWithHexadecimal = false;

        $s = [];
        foreach ($source as $row) {
            $s[] = (array) $row;
        }
        $source = $s;

        $result = '';
        $formats = [];
        $columns = [];
        $textRows = [];
        foreach ($source as $j => $row) {
            if ($j === 0) {
                foreach ($row as $column => $value) {
                    $columns[] = $column;
                }
                $textRows[] = $columns;
            }
            $values = [];
            $i = 0;
            /** @var mixed $value */
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'null';
                } elseif ($value === true) {
                    $values[] = 'true';
                } elseif ($value === false) {
                    $values[] = 'false';
                } elseif (is_int($value)) {
                    $values[] = strval($value);
                } elseif (is_float($value)) {
                    $f = Dumper::float($value);
                    $s = Ansi::removeColors($f);
                    $values[] = $s;
                } elseif ($value instanceof DateTimeInterface) {
                    if ($value->format('His') === '000000') {
                        $formats[$i] = self::DATE;
                        $values[] = $value->format('Y-m-d');
                    } else {
                        $formats[$i] = self::DATETIME;
                        $values[] = $value->format('Y-m-d H:i:s');
                    }
                } elseif (preg_match('/\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}/', $value)) {
                    $values[] = $value;
                    $formats[$i] = self::DATETIME;
                } elseif (preg_match('/\\d{4}-\\d{2}-\\d{2}/', $value)) {
                    $values[] = $value;
                    $formats[$i] = self::DATE;
                } elseif (preg_match('/\\d{2}:\\d{2}:\\d{2}/', $value)) {
                    $values[] = $value;
                    $formats[$i] = self::TIME;
                } elseif (strlen($value) === 16 && Str::isBinary($value)) {
                    $values[] = bin2hex($value);
                    $formats[$i] = self::BINARY;
                } else {
                    $f = Dumper::string((string) $value);
                    $s = Ansi::removeColors(Dumper::stripInfo($f));
                    $values[] = substr($s, 1, -1);
                    $formats[$i] = self::TEXT;
                }
                $i++;
            }
            $textRows[] = $values;
        }

        for ($n = 0; $n < count($columns); $n++) {
            if (!isset($formats[$n])) {
                $formats[$n] = self::TEXT;
            }
        }

        $padding = (Debugger::$outputWidth / count($columns) < 5) ? 0 : 2;
        $availableWidth = Debugger::$outputWidth - count($columns) * ($padding + 1) - 2;

        if (count($columns) > $availableWidth) {
            return Dumper::exceptions("Data too wide for table view:") . "\n" . Dumper::dump($source);
        }
        $columnWidths = self::calculateColumnWidths($textRows, $availableWidth, count($columns), $formats);

        if (min($columnWidths) < 1) {
            return Dumper::exceptions("Data too wide for table view:") . "\n" . Dumper::dump($source);
        }

        $result .= self::renderDivider($columnWidths, $padding);
        foreach ($source as $j => $row) {
            if ($j === 0) {
                $result .= self::renderRow(0, array_keys($row), $columnWidths, $formats, $padding);
                $result .= self::renderDivider($columnWidths, $padding);
            }
            $result .= self::renderRow($j + 1, array_values($row), $columnWidths, $formats, $padding);
        }
        $result .= self::renderDivider($columnWidths, $padding);

        Dumper::$showInfo = $oldInfo;
        Dumper::$binaryWithHexadecimal = $oldHex;

        return trim($result);
    }

    private static function renderDivider(array $columnWidths, int $padding): string
    {
        $row = '+';
        foreach ($columnWidths as $i => $columnWidth) {
            if ($i !== 0) {
                $row .= '+';
            }
            $width = $columnWidth + $padding;
            if ($width > 0) {
                $row .= str_repeat('-', $columnWidth + $padding);
            }
        }

        return self::formatLayout($row . "+\n");
    }

    /**
     * @param list<mixed> $row
     * @param list<int> $columnWidths
     * @param list<int> $formats
     */
    private static function renderRow(int $n, array $row, array $columnWidths, array $formats, int $padding): string
    {
        $result = self::formatLayout($padding ? '| ' : '|');
        $remainders = [];
        foreach ($row as $i => $value) {
            if ($i !== 0) {
                $result .= self::formatLayout($padding ? ' | ' : '|');
            }
            $columnWidth = $columnWidths[$i];

            if ($value === null) {
                $result .= str_repeat(' ', max($columnWidth - 4, 0)) . Dumper::null(substr('null', 0, $columnWidth));
                $remainders[$i] = '';
                continue;
            } elseif ($value === true) {
                $result .= str_repeat(' ', max($columnWidth - 4, 0)) . Dumper::bool(substr('true', 0, $columnWidth));
                $remainders[$i] = '';
                continue;
            } elseif ($value === false) {
                $result .= str_repeat(' ', max($columnWidth - 5, 0)) . Dumper::bool(substr('false', 0, $columnWidth));
                $remainders[$i] = '';
                continue;
            } elseif (is_int($value)) {
                $result .= str_repeat(' ', max($columnWidth - strlen($value), 0)) . Dumper::int($value);
                $remainders[$i] = '';
                continue;
            } elseif (is_float($value)) {
                $formatted = Dumper::float($value);
                $length = Ansi::length($formatted);
                $result .= str_repeat(' ', $columnWidth - $length) . $formatted;
                $remainders[$i] = '';
                continue;
            }

            if ($formats[$i] === self::BINARY && $n !== 0) {
                $formatted = Dumper::value(bin2hex($value));
                $length = Ansi::length($formatted);
                if ($length <= $columnWidth) {
                    $result .= $formatted . str_repeat(' ', $columnWidth - $length);
                    $remainders[$i] = '';
                } else {
                    $wrapPosition = self::getWordWrapPosition($value, $columnWidth);
                    $words = Str::substring($value, 0, $wrapPosition);
                    $remainders[$i] = trim(Str::substring($value, $wrapPosition));
                    $result .= self::formatValue($words, $formats[$i], str_repeat(' ', $columnWidth - $wrapPosition));
                }
            } else {
                $formatted = Dumper::string($value, null, null, true);
                $length = Ansi::length($formatted);
                if ($length <= $columnWidth) {
                    $result .= $formatted . str_repeat(' ', $columnWidth - $length);
                    $remainders[$i] = '';
                } else {
                    $wrapPosition = self::getWordWrapPosition($value, $columnWidth);
                    $words = Str::substring($value, 0, $wrapPosition);
                    $remainders[$i] = trim(Str::substring($value, $wrapPosition));
                    $result .= self::formatValue($words, $formats[$i], str_repeat(' ', $columnWidth - $wrapPosition));
                }
            }
        }
        $result .= self::formatLayout($padding ? " |\n" : "|\n");
        if (array_filter($remainders)) {
            $result .= self::renderRow($n, $remainders, $columnWidths, $formats, $padding);
        }

        return $result;
    }

    private static function formatLayout(string $layout): string
    {
        return Ansi::dgray($layout);
    }

    private static function formatValue(string $value, int $format, string $padding): string
    {
        if ($format === self::TEXT) {
            // escape new lines and tabulators
            $value = str_replace(["\n", "\t"], [Ansi::lcyan('↓'), Ansi::lcyan('→')], $value);
            // highlight html markup
            $value = preg_replace('/(<[^>]+(\\>|$)|^[^<]+>)/', Ansi::dgray('\\0'), $value);
            // highlight html entities
            return preg_replace('/&[a-z]+;/', Ansi::dgray('\\0'), $value) . $padding;
        } elseif ($format === self::NUMBER) {
            return $padding . $value;
        } else {
            return $value . $padding;
        }
    }

    private static function getWordWrapPosition(string $value, int $length): int
    {
        $pos = strrpos(mb_substr($value, 0, $length), ' ');
        if ($pos) {
            $pos = mb_strlen(substr($value, 0, $pos));
        }
        // break words if row is less than 70% full
        if ($pos < $length * 0.70) {
            $pos = $length;
        }

        return $pos ?: $length;
    }

    /**
     * @param iterable<array<string, string>> $rows
     * @param list<int> $formats
     * @return list<int>
     */
    private static function calculateColumnWidths(array $rows, int $tableWidth, int $columnsCount, array $formats): array
    {
        $zeroes = array_fill(0, $columnsCount, 0);
        $maxes = array_fill(0, $columnsCount, PHP_INT_MAX);

        $headLengths = $zeroes;
        $minLengths = $maxes;
        $maxLengths = $zeroes;
        $maxWordLengths = $zeroes;
        $flexible = [];
        $wrapable = [];
        $columnWidths = [];

        foreach ($rows as $j => $row) {
            for ($i = 0; $i < $columnsCount; $i++) {
                $cell = $row[$i];
                $length = mb_strlen($cell);

                if ($j === 0) {
                    $headLengths[$i] = $length;
                } else {
                    $maxLengths[$i] = max($maxLengths[$i], $length);
                    $minLengths[$i] = min($minLengths[$i], $length);
                }

                if (strpos($cell, ' ') !== false) {
                    $wrapable[$i] = true;
                    $maxWordLengths[$i] = max($maxWordLengths[$i], self::getMaxWordLength($cell));
                } else {
                    $wrapable[$i] = false;
                    $maxWordLengths[$i] = $maxLengths[$i];
                }
            }
        }

        $left = $tableWidth;
        $avg = $left / $columnsCount;
        $flexibleColumnsCount = 0;

        // determine whether columns should be flexible and assign width of non-flexible columns
        foreach ($maxLengths as $i => $maxLength) {
            $flexible[$i] = ($maxLength > 2 * $avg);
            if ($flexible[$i]) {
                $flexibleColumnsCount++;
            } else {
                $columnWidths[$i] = max($maxLength, $headLengths[$i]);
                $left -= $columnWidths[$i];
            }
        }

        // wrap all wrapable columns
        if (array_sum($maxLengths) > $tableWidth) {
            foreach ($maxWordLengths as $i => $maxWordLength) {
                if ($wrapable[$i] && !$flexible[$i]) {
                    $left += $columnWidths[$i] - $maxWordLength;
                    $columnWidths[$i] = $maxWordLength;
                }
            }
        }

        // wrap headers
        if (array_sum($maxLengths) > array_sum($columnWidths)) {
            foreach ($maxLengths as $i => $maxLength) {
                if (!$wrapable[$i] && !$flexible[$i] && $maxLength < $headLengths[$i]) {
                    $left += $columnWidths[$i] - $maxLength;
                    $columnWidths[$i] = $maxLength;
                }
            }
        }

        // calculate weights for flexible columns. the max width is capped at triple of page width
        $totalWidth = 0;
        for ($i = 0; $i < $columnsCount; $i++) {
            if ($flexible[$i]) {
                $maxLengths[$i] = min($maxLengths[$i], $tableWidth * 3);
                $totalWidth += $maxLengths[$i];
            }
        }

        // assign width for flexible columns
        foreach ($maxLengths as $i => $maxLength) {
            if ($flexible[$i]) {
                $columnWidths[$i] = min((int) round($left * $maxLength / $totalWidth), $maxLength);
            }
        }

        // tweak widths of datetime columns
        $dateTimeColumnWidths = [];
        $nonDateTimeColumnWidths = [];
        foreach ($columnWidths as $i => $width) {
            if ($formats[$i] === self::DATE || $formats[$i] === self::TIME || $formats[$i] === self::DATETIME) {
                $dateTimeColumnWidths[$i] = $width;
            } else {
                $nonDateTimeColumnWidths[$i] = $width;
            }
        }
        foreach ($dateTimeColumnWidths as $i => $width) {
            if (($formats[$i] === self::DATE || $formats[$i] === self::DATETIME) && ($width === 8 || $width === 9)) {
                $columnWidths[$i] = 10;
                $columnWidths[array_search(max($nonDateTimeColumnWidths), $nonDateTimeColumnWidths, true)] -= (10 - $width);
            } elseif (($formats[$i] === self::TIME || $formats[$i] === self::DATETIME) && $width === 6) {
                $columnWidths[$i] = 7;
                $columnWidths[array_search(max($nonDateTimeColumnWidths), $nonDateTimeColumnWidths, true)]--;
            } elseif ($formats[$i] === self::TIME && ($width === 6 || $width === 7)) {
                $columnWidths[$i] = 8;
                $columnWidths[array_search(max($nonDateTimeColumnWidths), $nonDateTimeColumnWidths, true)] -= (8 - $width);
            }
        }

        // fix columns with zero width on extreme conditions
        foreach ($columnWidths as $i => $width) {
            if ($width === 0) {
                $columnWidths[$i] = 1;
                $columnWidths[array_search(max($columnWidths), $columnWidths, true)]--;
            }
        }

        // fix too wide table due to rounding errors
        while (array_sum($columnWidths) > $tableWidth) {
            $columnWidths[array_search(max($columnWidths), $columnWidths, true)]--;
        }

        ksort($columnWidths);

        /*rd($flexible);
        rd($wrapable);
        rd($headLengths);
        rd($maxLengths);
        rd($maxWordLengths);
        rd($columnWidths);*/

        return $columnWidths;
    }

    private static function getMaxWordLength(string $string): int
    {
        $words = preg_split('~ |\\\\n~', $string);

        return min(max(array_map('mb_strlen', $words)), 30);
    }

}
