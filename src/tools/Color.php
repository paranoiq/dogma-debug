<?php

// spell-check-ignore: XYZ adff2f afeeee aliceblue antiquewhite blanchedalmond blueviolet burlywood cadetblue cornflowerblue cornsilk darkblue darkcyan darkgray darkgreen darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkturquoise darkviolet dcdcdc dda0dd deeppink deepskyblue dimgray dodgerblue e0ffff eee8aa f0ffff f5fffa faebd7 fafad2 ffc0cb ffdab9 ffdead ffebcd ffefd5 fff5ee fff8dc fffacd fffaf0 fffafa ffffe0 fffff0 ffffff floralwhite forestgreen gainsboro ghostwhite greenyellow hotpink indianred ish lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightpink lightsalmon lightseagreen lightskyblue lightslategray lightsteelblue lightyellow limegreen mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose navajowhite oldlace olivedrab orangered palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru powderblue rosybrown royalblue saddlebrown sandybrown seagreen skyblue slateblue slategray springgreen steelblue whitesmoke yellowgreen

namespace Dogma\Debug;

use function array_keys;
use function array_search;
use function dechex;
use function hexdec;
use function in_array;
use function intval;
use function max;
use function preg_match;
use function sqrt;
use function str_pad;
use function strlen;
use function strtolower;
use function substr;
use function user_error;
use const E_USER_NOTICE;
use const PHP_INT_MAX;
use const STR_PAD_LEFT;

class Color
{

    public const NAMED_COLORS_4BIT = [
        'white' => 'ffffff', // W
        'silver' => 'c0c0c0', // w
        'red' => 'ff0000', // R
        'maroon' => '800000', // r
        'lime' => '00ff00', // G
        'green' => '008000', // g
        'blue' => '0000ff', // B
        'navy' => '000080', // b
        'cyan' => '00ffff', // C
        'teal' => '008080', // c
        'magenta' => 'ff00ff', // M
        'purple' => '800080', // m
        'yellow' => 'ffff00', // Y
        'olive' => '808000', // y
        'gray' => '808080', // K
        'black' => '000000', // k

        'aqua' => '00ffff', // alias
        'fuchsia' => 'ff00ff', // alias
    ];

    public const NAMED_COLORS = [
        'white' => 'ffffff', // W
        'silver' => 'c0c0c0', // w
        'red' => 'ff0000', // R
        'maroon' => '800000', // r
        'lime' => '00ff00', // G
        'green' => '008000', // g
        'blue' => '0000ff', // B
        'navy' => '000080', // b
        'cyan' => '00ffff', // C
        'teal' => '008080', // c
        'magenta' => 'ff00ff', // M
        'purple' => '800080', // m
        'yellow' => 'ffff00', // Y
        'olive' => '808000', // y
        'gray' => '808080', // K
        'black' => '000000', // k

        'aqua' => '00ffff', // alias
        'fuchsia' => 'ff00ff', // alias

        // red
        'lightsalmon' => 'ffa07a',
        'salmon' => 'fa8072',
        'darksalmon' => 'e9967a',
        'lightcoral' => 'f08080',
        'indianred' => 'cd5c5c',
        'crimson' => 'dc143c',
        'firebrick' => 'b22222',
        'darkred' => '8b0000',

        // orange
        'coral' => 'ff7f50',
        'tomato' => 'ff6347',
        'orangered' => 'ff4500',
        'gold' => 'ffd700',
        'orange' => 'ffa500',
        'darkorange' => 'ff8c00',

        // yellow
        'lightyellow' => 'ffffe0',
        'lemonchiffon' => 'fffacd',
        'lightgoldenrodyellow' => 'fafad2',
        'papayawhip' => 'ffefd5',
        'moccasin' => 'ffe4b5',
        'peachpuff' => 'ffdab9',
        'palegoldenrod' => 'eee8aa',
        'khaki' => 'f0e68c',
        'darkkhaki' => 'bdb76b',

        // green
        'lawngreen' => '7cfc00',
        'chartreuse' => '7fff00',
        'limegreen' => '32cd32',
        'forestgreen' => '228b22',
        'darkgreen' => '006400',
        'greenyellow' => 'adff2f',
        'yellowgreen' => '9acd32',
        'springgreen' => '00ff7f',
        'mediumspringgreen' => '00fa9a',
        'lightgreen' => '90ee90',
        'palegreen' => '98fb98',
        'darkseagreen' => '8fbc8f',
        'mediumseagreen' => '3cb371',
        'seagreen' => '2e8b57',
        'darkolivegreen' => '556b2f',
        'olivedrab' => '6b8e23',

        // cyan
        'lightcyan' => 'e0ffff',
        'aquamarine' => '7fffd4',
        'mediumaquamarine' => '66cdaa',
        'paleturquoise' => 'afeeee',
        'turquoise' => '40e0d0',
        'mediumturquoise' => '48d1cc',
        'darkturquoise' => '00ced1',
        'lightseagreen' => '20b2aa',
        'cadetblue' => '5f9ea0',
        'darkcyan' => '008b8b',

        // blue
        'powderblue' => 'b0e0e6',
        'lightblue' => 'add8e6',
        'lightskyblue' => '87cefa',
        'skyblue' => '87ceeb',
        'deepskyblue' => '00bfff',
        'lightsteelblue' => 'b0c4de',
        'dodgerblue' => '1e90ff',
        'cornflowerblue' => '6495ed',
        'steelblue' => '4682b4',
        'royalblue' => '4169e1',
        'mediumblue' => '0000cd',
        'darkblue' => '00008b',
        'midnightblue' => '191970',
        'mediumslateblue' => '7b68ee',
        'slateblue' => '6a5acd',
        'darkslateblue' => '483d8b',

        // purple
        'lavender' => 'e6e6fa',
        'thistle' => 'd8bfd8',
        'plum' => 'dda0dd',
        'violet' => 'ee82ee',
        'orchid' => 'da70d6',
        'mediumorchid' => 'ba55d3',
        'mediumpurple' => '9370db',
        'blueviolet' => '8a2be2',
        'darkviolet' => '9400d3',
        'darkorchid' => '9932cc',
        'darkmagenta' => '8b008b',
        'indigo' => '4b0082',

        // pink
        'pink' => 'ffc0cb',
        'lightpink' => 'ffb6c1',
        'hotpink' => 'ff69b4',
        'deeppink' => 'ff1493',
        'palevioletred' => 'db7093',
        'mediumvioletred' => 'c71585',

        // white(ish)
        'snow' => 'fffafa',
        'honeydew' => 'f0fff0',
        'mintcream' => 'f5fffa',
        'azure' => 'f0ffff',
        'aliceblue' => 'f0f8ff',
        'ghostwhite' => 'f8f8ff',
        'whitesmoke' => 'f5f5f5',
        'seashell' => 'fff5ee',
        'beige' => 'f5f5dc',
        'oldlace' => 'fdf5e6',
        'floralwhite' => 'fffaf0',
        'ivory' => 'fffff0',
        'antiquewhite' => 'faebd7',
        'linen' => 'faf0e6',
        'lavenderblush' => 'fff0f5',
        'mistyrose' => 'ffe4e1',

        // gray/black
        'gainsboro' => 'dcdcdc',
        'lightgray' => 'd3d3d3',
        'darkgray' => 'a9a9a9',
        'dimgray' => '696969',
        'lightslategray' => '778899',
        'slategray' => '708090',
        'darkslategray' => '2f4f4f',

        // brown
        'cornsilk' => 'fff8dc',
        'blanchedalmond' => 'ffebcd',
        'bisque' => 'ffe4c4',
        'navajowhite' => 'ffdead',
        'wheat' => 'f5deb3',
        'burlywood' => 'deb887',
        'tan' => 'd2b48c',
        'rosybrown' => 'bc8f8f',
        'sandybrown' => 'f4a460',
        'goldenrod' => 'daa520',
        'peru' => 'cd853f',
        'chocolate' => 'd2691e',
        'saddlebrown' => '8b4513',
        'sienna' => 'a0522d',
        'brown' => 'a52a2a',
    ];

    public static function isColor(string $value, bool $requireHash = false): bool
    {
        $value = strtolower($value);
        $pattern = $requireHash ? '/^#[0-9a-f]{3}([0-9a-f]{3})?$/' : '/^#?[0-9a-f]{3}([0-9a-f]{3})?$/';

        return isset(self::NAMED_COLORS[$value]) || preg_match($pattern, $value);
    }

    public static function invert(string $rgb): string
    {
        if (strlen($rgb) !== 6) {
            user_error("Invalid color: #{$rgb}", E_USER_NOTICE);
        }

        $r = hexdec(substr($rgb, 0, 2));
        $g = hexdec(substr($rgb, 2, 2));
        $b = hexdec(substr($rgb, 4, 2));

        return str_pad(dechex(intval(255 - $r)), 2, '0', STR_PAD_LEFT)
            . str_pad(dechex(intval(255 - $g)), 2, '0', STR_PAD_LEFT)
            . str_pad(dechex(intval(255 - $b)), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string> $colors
     * @param array<string> $filters
     * @return array<string>
     */
    public static function filterByMinDistance(array $colors, array $filters, int $minDistance): array
    {
        foreach ($colors as $i => $color) {
            foreach ($filters as $filter) {
                if (self::distance($color, $filter) < $minDistance) {
                    unset($colors[$i]);
                    continue 2;
                }
            }
        }

        return $colors;
    }

    /**
     * @param array<string> $colors
     * @return array<string>
     */
    public static function filterByLightness(array $colors, int $min, int $max): array
    {
        foreach ($colors as $i => $color) {
            $lightness = self::getLightness($color);
            if ($lightness < $min || $lightness > $max) {
                unset($colors[$i]);
            }
        }

        return $colors;
    }

    public static function getLightness(string $rgb): float
    {
        if (strlen($rgb) !== 6) {
            user_error("Invalid color: #{$rgb}", E_USER_NOTICE);
        }

        $r = hexdec(substr($rgb, 0, 2));
        $g = hexdec(substr($rgb, 2, 2));
        $b = hexdec(substr($rgb, 4, 2));

        // normalize to [0, 1]
        $r /= 255.0;
        $g /= 255.0;
        $b /= 255.0;

        // convert RGB to sRGB
        $r = ($r <= 0.04045) ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
        $g = ($g <= 0.04045) ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
        $b = ($b <= 0.04045) ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;

        // convert sRGB to XYZ
        $r *= 100;
        $g *= 100;
        $b *= 100;

        $y = $r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750;
        $y /= 100.000;
        $y = ($y > 0.008856) ? $y ** (1 / 3) : (903.3 * $y + 16) / 116;

        return max(0, (116 * $y) - 16);
    }

    /**
     * @param array<string> $unused
     * @param array<string> $used
     * @return array{string, int|string, int} ($color, $key, $distance)
     */
    public static function pickMostDistant(array $unused, array $used, string $default): array
    {
        if (empty($used)) {
            if (in_array($default, $unused, true)) {
                /** @var int|string $key */
                $key = array_search($default, $unused, true);

                return [$default, $key, -1];
            } else {
                $key = array_keys($unused)[0];

                return [$unused[$key], $key, -1];
            }
        }
        if (empty($unused)) {
            return [$default, -1, -1];
        }

        $selectedKey = '';
        $maxDistance = -1;
        foreach ($unused as $unusedName => $unusedRgb) {
            $minDistance = PHP_INT_MAX;
            foreach ($used as $usedRgb) {
                $distance = self::distance($unusedRgb, $usedRgb);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                }
            }

            if ($minDistance > $maxDistance) {
                $maxDistance = $minDistance;
                $selectedKey = $unusedName;
            }
        }

        $selectedRgb = $unused[$selectedKey];

        return [$selectedRgb, $selectedKey, $maxDistance];
    }

    public static function distance(string $rgb1, string $rgb2): int
    {
        if (strlen($rgb1) !== 6) {
            user_error("Invalid color: #{$rgb1}", E_USER_NOTICE);
        } elseif (strlen($rgb2) !== 6) {
            user_error("Invalid color: #{$rgb2}", E_USER_NOTICE);
        }

        $r1 = hexdec(substr($rgb1, 0, 2));
        $g1 = hexdec(substr($rgb1, 2, 2));
        $b1 = hexdec(substr($rgb1, 4, 2));
        $r2 = hexdec(substr($rgb2, 0, 2));
        $g2 = hexdec(substr($rgb2, 2, 2));
        $b2 = hexdec(substr($rgb2, 4, 2));

        return intval(sqrt(($r1 - $r2) ** 2 + ($g1 - $g2) ** 2 + ($b1 - $b2) ** 2));
    }

}
