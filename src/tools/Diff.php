<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Generic.NamingConventions.ConstructorName.OldStyle

namespace Dogma\Debug;

use function array_keys;
use function array_merge;
use function array_slice;
use function implode;
use function is_array;
use function preg_split;

/**
 * @see https://github.com/paulgb/simplediff/blob/master/php/simplediff.php
 */
class Diff
{

    public static function htmlDiff(string $old, string $new): string
    {
        /** @var string[] $olds */
        $olds = preg_split("/\s+/", $old);
        /** @var string[] $news */
        $news = preg_split("/\s+/", $new);
        $diff = self::diff($olds, $news);

        $result = '';
        foreach ($diff as $k) {
            if (is_array($k)) {
                $result .= (!empty($k['d']) ? "<del>" . implode(' ', $k['d']) . "</del> " : '')
                    . (!empty($k['i']) ? "<ins>" . implode(' ', $k['i']) . "</ins> " : '');
            } else {
                $result .= $k . ' ';
            }
        }

        return $result;
    }

    public static function cliDiff(string $old, string $new): string
    {
        /** @var string[] $olds */
        $olds = preg_split("/\s+/", $old);
        /** @var string[] $news */
        $news = preg_split("/\s+/", $new);
        $diff = self::diff($olds, $news);

        $result = '';
        foreach ($diff as $k) {
            if (is_array($k)) {
                $result .= (!empty($k['d']) ? Ansi::lred(implode(' ', $k['d'])) . ' ' : '')
                    . (!empty($k['i']) ? Ansi::lgreen(implode(' ', $k['i'])) . ' ' : '');
            } else {
                $result .= $k . ' ';
            }
        }

        return $result;
    }

    /**
     * @param string[] $old
     * @param string[] $new
     * @return mixed[]
     */
    public static function diff(array $old, array $new): array
    {
        $matrix = [];
        $maxLen = 0;
        foreach ($old as $oldIndex => $oldValue) {
            $newKeys = array_keys($new, $oldValue, true);
            foreach ($newKeys as $newIndex) {
                $matrix[$oldIndex][$newIndex] = isset($matrix[$oldIndex - 1][$newIndex - 1]) ? $matrix[$oldIndex - 1][$newIndex - 1] + 1 : 1;

                if ($matrix[$oldIndex][$newIndex] > $maxLen) {
                    $maxLen = $matrix[$oldIndex][$newIndex];
                    $oldMax = $oldIndex + 1 - $maxLen;
                    $newMax = $newIndex + 1 - $maxLen;
                }
            }
        }
        if ($maxLen === 0) {
            return [['d' => $old, 'i' => $new]];
        }

        return array_merge(
            self::diff(array_slice($old, 0, $oldMax ?? null), array_slice($new, 0, $newMax ?? null)),
            array_slice($new, $newMax ?? null, $maxLen),
            self::diff(array_slice($old, ($oldMax ?? 0) + $maxLen), array_slice($new, ($newMax ?? 0) + $maxLen))
        );
    }

}
