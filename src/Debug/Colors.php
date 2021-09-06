<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint

namespace Dogma\Debug;

use function preg_replace;

final class Colors
{

	/** @var bool */
	public static $off = false;

	public const WHITE = 'W';
	public const LGRAY = 'w';
	public const DGRAY = 'K';
	public const BLACK = 'k';
	public const LRED = 'R';
	public const DRED = 'r';
	public const LGREEN = 'G';
	public const DGREEN = 'g';
	public const LBLUE = 'B';
	public const DBLUE = 'b';
	public const LCYAN = 'C';
	public const DCYAN = 'c';
	public const LMAGENTA = 'M';
	public const DMAGENTA = 'm';
	public const LYELLOW = 'Y';
	public const DYELLOW = 'y';

	// alias to MAGENTA
	public const LPURPLE = 'M';
	public const DPURPLE = 'm';

	public const RESET = "\x1B[0m";

	/** @var string[] */
	private static $fg = [
		self::WHITE => '1;37',
		self::LGRAY => '0;37',
		self::DGRAY => '1;30',
		self::BLACK => '0;30',

		self::DRED => '0;31',
		self::LRED => '1;31',
		self::DGREEN => '0;32',
		self::LGREEN => '1;32',
		self::DBLUE => '0;34',
		self::LBLUE => '1;34',

		self::DCYAN => '0;36',
		self::LCYAN => '1;36',
		self::DMAGENTA => '0;35',
		self::LMAGENTA => '1;35',
		self::DYELLOW => '0;33',
		self::LYELLOW => '1;33',
	];

	/** @var string[] */
	private static $bg = [
		self::LGRAY => '47',
		self::BLACK => '40',

		self::DRED => '41',
		self::DGREEN => '42',
		self::DBLUE => '44',

		self::DYELLOW => '43',
		self::DMAGENTA => '45',
		self::DCYAN => '46',
	];

	public static function color($string, ?string $foreground = null, ?string $background = null): string
	{
		$string = (string) $string;

		if (self::$off) {
			return $string;
		}

		$out = '';
		if (isset(self::$fg[$foreground])) {
			$out .= "\x1B[" . self::$fg[$foreground] . 'm';
		}
		if (isset(self::$bg[$background])) {
			$out .= "\x1B[" . self::$bg[$background] . 'm';
		}

		return $out . $string . "\x1B[0m";
	}

	public static function between($string, string $color, string $after): string
	{
		if (self::$off) {
			return (string) $string;
		}

		return"\x1B[" . self::$fg[$color] . 'm' . $string . "\x1B[" . self::$fg[$after] . 'm';
	}

	public static function background(string $string, string $background): string
	{
		return self::color($string, null, $background);
	}

	public static function length(string $string, string $encoding = 'utf-8'): int
	{
		return Str::length(self::remove($string), $encoding);
	}

	public static function pad(string $string, int $length, string $with = ' ', int $type = STR_PAD_RIGHT): string
	{
		$original = self::remove($string);

		return str_pad($string, $length + strlen($string) - strlen($original) + 1, $with, $type);
	}

	public static function remove(string $string): string
	{
		return (string) preg_replace('/\\x1B\\[[^m]+m/U', '', $string);
	}

	public static function ansiValue(string $color): string
	{
		return self::$fg[$color];
	}

	public static function test(): string
	{
		return self::white('white') . "\n"
			. self::lgray('light gray') . "\n"
			. self::dgray('dark gray') . "\n"
			. self::black('black') . "\n"
			. self::lred('light red') . "\n"
			. self::dred('dark red') . "\n"
			. self::lgreen('light green') . "\n"
			. self::dgreen('dark green') . "\n"
			. self::lblue('light blue') . "\n"
			. self::dblue('dark blue') . "\n"
			. self::lcyan('light cyan') . "\n"
			. self::dcyan('dark cyan') . "\n"
			. self::lmagenta('light magenta') . "\n"
			. self::dmagenta('dark magenta') . "\n"
			. self::lyellow('light yellow') . "\n"
			. self::dyellow('dark yellow') . "\n";
	}

	// shortcuts -------------------------------------------------------------------------------------------------------

	public static function white($string, ?string $background = null): string
	{
		return self::color($string, self::WHITE, $background);
	}

	public static function lgray($string, ?string $background = null): string
	{
		return self::color($string, self::LGRAY, $background);
	}

	public static function dgray($string, ?string $background = null): string
	{
		return self::color($string, self::DGRAY, $background);
	}

	public static function black($string, ?string $background = null): string
	{
		return self::color($string, self::BLACK, $background);
	}

	public static function lred($string, ?string $background = null): string
	{
		return self::color($string, self::LRED, $background);
	}

	public static function dred($string, ?string $background = null): string
	{
		return self::color($string, self::DRED, $background);
	}

	public static function lgreen($string, ?string $background = null): string
	{
		return self::color($string, self::LGREEN, $background);
	}

	public static function dgreen($string, ?string $background = null): string
	{
		return self::color($string, self::DGREEN, $background);
	}

	public static function lblue($string, ?string $background = null): string
	{
		return self::color($string, self::LBLUE, $background);
	}

	public static function dblue($string, ?string $background = null): string
	{
		return self::color($string, self::DBLUE, $background);
	}

	public static function lcyan($string, ?string $background = null): string
	{
		return self::color($string, self::LCYAN, $background);
	}

	public static function dcyan($string, ?string $background = null): string
	{
		return self::color($string, self::DCYAN, $background);
	}

	public static function lmagenta($string, ?string $background = null): string
	{
		return self::color($string, self::LMAGENTA, $background);
	}

	public static function dmagenta($string, ?string $background = null): string
	{
		return self::color($string, self::DMAGENTA, $background);
	}

	public static function lyellow($string, ?string $background = null): string
	{
		return self::color($string, self::LYELLOW, $background);
	}

	public static function dyellow($string, ?string $background = null): string
	{
		return self::color($string, self::DYELLOW, $background);
	}


	// alias to lmagenta
	public static function lpurple($string, ?string $background = null): string
	{
		return self::color($string, self::LMAGENTA, $background);
	}

	// alias to magenta
	public static function purple($string, ?string $background = null): string
	{
		return self::color($string, self::DMAGENTA, $background);
	}

}
