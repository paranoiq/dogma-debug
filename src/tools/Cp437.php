<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline

namespace Dogma\Debug;

use function array_combine;
use function array_merge;
use function range;
use function strlen;

class Cp437
{

    // lower half is ascii
    private const UPPER_HALF = [
        'Ç', 'ü', 'é', 'â', 'ä', 'à', 'å', 'ç', 'ê', 'ë', 'è', 'ï', 'î', 'ì', 'Ä', 'Å',
        'É', 'æ', 'Æ', 'ô', 'ö', 'ò', 'û', 'ù', 'ÿ', 'Ö', 'Ü', '¢', '£', '¥', '₧', 'ƒ',
        'á', 'í', 'ó', 'ú', 'ñ', 'Ñ', 'ª', 'º', '¿', '⌐', '¬', '½', '¼', '¡', '«', '»',
        '░', '▒', '▓', '│', '┤', '╡', '╢', '╖', '╕', '╣', '║', '╗', '╝', '╜', '╛', '┐',
        '└', '┴', '┬', '├', '─', '┼', '╞', '╟', '╚', '╔', '╩', '╦', '╠', '═', '╬', '╧',
        '╨', '╤', '╥', '╙', '╘', '╒', '╓', '╫', '╪', '┘', '┌', '█', '▄', '▌', '▐', '▀',
        'α', 'ß', 'Γ', 'π', 'Σ', 'σ', 'µ', 'τ', 'Φ', 'Θ', 'Ω', 'δ', '∞', 'φ', 'ε', '∩',
        '≡', '±', '≥', '≤', '⌠', '⌡', '÷', '≈', '°', '∙', '·', '√', 'ⁿ', '²', '■', ' ', // todo: \xFF looks the same as space : /
    ];

    private const SPECIAL_PRINTABLE = [
        '¤', '☺', '☻', '♥', '♦', '♣', '♠', '•', '◘', '○', '◙', '♂', '♀', '♪', '♫', '☼',
        '►', '◄', '↕', '‼', '¶', '§', '▬', '↨', '↑', '↓', '→', '←', '∟', '↔', '▲', '▼',
        '⌂',
    ];

    /**
     * Binary string formatted as UTF-8 representation of original IBM PC (CP437) font
     */
    public static function toUtf8Printable(string $string): string
    {
        $keys = array_merge(range("\x00", "\x1f"), ["\x7F"], range("\x80", "\xff"));
        $values = array_merge(self::SPECIAL_PRINTABLE, self::UPPER_HALF);
        /** @var string[] $replace */
        $replace = array_combine($keys, $values);

        $res = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ch = $string[$i];
            $res .= $replace[$ch] ?? $ch;
        }

        return $res;
    }

    public static function toUtf8(string $string): string
    {
        /** @var string[] $replace */
        $replace = array_combine(range("\x80", "\xff"), self::UPPER_HALF);

        $res = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ch = $string[$i];
            $res .= $replace[$ch] ?? $ch;
        }

        return $res;
    }

}
