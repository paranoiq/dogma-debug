<?php

// spell-check-ignore: xff

namespace Dogma\Tests\Debug;

use DateTimeZone;
use Dogma\Debug\Ansi;
use Dogma\Debug\Assert;
use Dogma\Debug\Dumper;
use function range;
use const INF;
use const NAN;
use const PHP_VERSION_ID;

require_once __DIR__ . '/../bootstrap.php';


Assert::$dump = true;
Dumper::$useFormatters = false;


null:
Assert::dump(null, '<literal>: <null>');


booleans:
Assert::dump(false, '<literal>: <false>');
Assert::dump(true, '<literal>: <true>');


integers:
Assert::dump(0, '<literal>: <0>');
Assert::dump(-1, '<literal>: <-1>');
Assert::dump(64, '<literal>: <64>');

// powers of two
Assert::dump(512, '<literal>: <512> <// 2^9>');
Assert::dump(-512, '<literal>: <-512> <// 2^9>');
Assert::dump(65536, '<literal>: <65536> <// 2^16>');
Assert::dump(65535, '<literal>: <65535> <// 2^16-1>');

// bytes
Assert::dump(["size" => 23], '<literal>: <[>
    <size> => <23>,
<]> <// 1 item>');
Assert::dump(["size" => 23000], '<literal>: <[>
    <size> => <23000>, <// 22.5 kB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000], '<literal>: <[>
    <size> => <23000000>, <// 21.9 MB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000], '<literal>: <[>
    <size> => <23000000000>, <// 21.4 GB>
<]> <// 1 item>');
Assert::dump(["size" => 23000000000000], '<literal>: <[>
    <size> => <23000000000000>, <// 20.9 TB>
<]> <// 1 item>');

// flags
Assert::dump(["flags" => 23], '<literal>: <[>
    <flags> => <23>, <// 16|4|2|1>
<]> <// 1 item>');

// timestamps
$time = 1623018172;
Dumper::$config->infoTimeZone = new DateTimeZone('Z');
Assert::dump($time, '<$time>: <1623018172> <// 2021-06-06 22:22:52+00:00>');

Dumper::$config->infoTimeZone = new DateTimeZone('Europe/Prague');
Assert::dump($time, '<$time>: <1623018172> <// 2021-06-07 00:22:52+02:00>');


floats:
Assert::dump(0.0, '<literal>: <0.0>');
Assert::dump(1.23, '<literal>: <1.23>');
Assert::dump(-1.23, '<literal>: <-1.23>');
Assert::dump(INF, '<literal>: <INF>');
Assert::dump(-INF, '<literal>: <-INF>');
Assert::dump(NAN, '<literal>: <NAN>');


strings:
Assert::dump('', '<literal>: <"">');
Assert::dump('abcdef', '<literal>: <"abcdef"> <// 6 B>');
Dumper::$config->escapeAllNonAscii = false;
Assert::dump('áčř', '<literal>: <"áčř"> <// 6 B, 3 ch>');

// hidden
Dumper::$config->hiddenFields = ['secret'];
$secret = 'foo';
Assert::dump($secret, '<$secret>: <"><*****><"> <// hidden>');

// color codes
Assert::dump('orange', '<literal>: <"orange"> <// <<     <>');
Assert::dump('#5F9EA0', '<literal>: <"#5F9EA0"> <// <<     <>');
$color = '00FF7F';
Assert::dump($color, '<$color>: <"00FF7F"> <// <<     <>');

// callables
Assert::dump('strlen', '<literal>: <"strlen"> <// 6 B, callable from ext-core>');
Assert::dump('rd', '<literal>: <"rd"> <// callable defined in ?path?file:?line>');

// limit
Dumper::$config->maxLength = 10;
Assert::dump('příliš žluťoučký kůň úpěl ďábelské ódy', '<literal>: <"příliš žlu<...<"> <// 53 B, 38 ch, trimmed>');
Dumper::$config->maxLength = 10000;

// escaping
Assert::dump('"', '<literal>: <\'"\'>');
Assert::dump("\n", '<literal>: <"<\n<">');

Dumper::$config->escapeAllNonAscii = true;
Assert::dump('áčř', '<literal>: <"<\u{e1}<<\u{10d}<<\u{159}<"> <// 6 B, 3 ch>');
Assert::dump('🙈', '<literal>: <"<\u{1f648}<"> <// 4 B, 1 ch>');

Dumper::$config->stringsEscaping = Dumper::ESCAPING_JSON;
Assert::dump('áčř', '<literal>: <"<\u00e1<<\u010d<<\u0159<"> <// 6 B, 3 ch>');
Assert::dump('🙈', '<literal>: <"<\ud83d\ude48<"> <// 4 B, 1 ch>');

// binary
$bin = implode('', range("\x00", "\xff"));
// spell-check-ignore: ABCDEFGHIJKLMNO PQRSTUVWXYZ abcdefghijklmno pqrstuvwxyz Çüéâäàåçêëèïîì Ä Å Éæ Æôöòûùÿ Ö Ñªº Ü áíóúñ ƒ Γπ Θ Σσµτ Φ Ωδ αß φε ⁿ aa ae af ba bb bc bd cd ce da de df eb ec ee ef fb fc fd fe
Dumper::$config->escapeAllNonAscii = false; // todo: conflicts with normal binary escaping
Dumper::$config->binaryEscaping = Dumper::ESCAPING_CP437;
Dumper::$colors['escape_basic'] = Ansi::LCYAN;
Dumper::$colors['escape_special'] = Ansi::LCYAN;
Dumper::$colors['escape_non_ascii'] = Ansi::LCYAN;
if (PHP_VERSION_ID >= 80200) { // WTF?
    Assert::dump($bin, <<<END
<\$bin>: <binary:>
   <"¤☺☻♥♦♣♠•◘○◙♂♀♪♫☼"> <//   0: 00 01 02 03  04 05 06 07  08 09 0a 0b  0c 0d 0e 0f>
 . <"►◄↕‼¶§▬↨↑↓→←∟↔▲▼"> <//  16: 10 11 12 13  14 15 16 17  18 19 1a 1b  1c 1d 1e 1f>
 . <" !"#$%&'()*+,-./"> <//  32: 20 21 22 23  24 25 26 27  28 29 2a 2b  2c 2d 2e 2f>
 . <"0123456789:;<=>?"> <//  48: 30 31 32 33  34 35 36 37  38 39 3a 3b  3c 3d 3e 3f>
 . <"@ABCDEFGHIJKLMNO"> <//  64: 40 41 42 43  44 45 46 47  48 49 4a 4b  4c 4d 4e 4f>
 . <"PQRSTUVWXYZ[\]^_"> <//  80: 50 51 52 53  54 55 56 57  58 59 5a 5b  5c 5d 5e 5f>
 . <"`abcdefghijklmno"> <//  96: 60 61 62 63  64 65 66 67  68 69 6a 6b  6c 6d 6e 6f>
 . <"pqrstuvwxyz{|}~⌂"> <// 112: 70 71 72 73  74 75 76 77  78 79 7a 7b  7c 7d 7e 7f>
 . <"ÇüéâäàåçêëèïîìÄÅ"> <// 128: 80 81 82 83  84 85 86 87  88 89 8a 8b  8c 8d 8e 8f>
 . <"ÉæÆôöòûùÿÖÜ¢£¥₧ƒ"> <// 144: 90 91 92 93  94 95 96 97  98 99 9a 9b  9c 9d 9e 9f>
 . <"áíóúñÑªº¿⌐¬½¼¡«»"> <// 160: a0 a1 a2 a3  a4 a5 a6 a7  a8 a9 aa ab  ac ad ae af>
 . <"░▒▓│┤╡╢╖╕╣║╗╝╜╛┐"> <// 176: b0 b1 b2 b3  b4 b5 b6 b7  b8 b9 ba bb  bc bd be bf>
 . <"└┴┬├─┼╞╟╚╔╩╦╠═╬╧"> <// 192: c0 c1 c2 c3  c4 c5 c6 c7  c8 c9 ca cb  cc cd ce cf>
 . <"╨╤╥╙╘╒╓╫╪┘┌█▄▌▐▀"> <// 208: d0 d1 d2 d3  d4 d5 d6 d7  d8 d9 da db  dc dd de df>
 . <"αßΓπΣσµτΦΘΩδ∞φε∩"> <// 224: e0 e1 e2 e3  e4 e5 e6 e7  e8 e9 ea eb  ec ed ee ef>
 . <"≡±≥≤⌠⌡÷≈°∙·√ⁿ²■ "> <// 240: f0 f1 f2 f3  f4 f5 f6 f7  f8 f9 fa fb  fc fd fe ff> <// 256 B>
END
    );
} else {
    Assert::dump($bin, <<<END
<unknown>: <binary:>
   <"¤☺☻♥♦♣♠•◘○◙♂♀♪♫☼"> <//   0: 00 01 02 03  04 05 06 07  08 09 0a 0b  0c 0d 0e 0f>
 . <"►◄↕‼¶§▬↨↑↓→←∟↔▲▼"> <//  16: 10 11 12 13  14 15 16 17  18 19 1a 1b  1c 1d 1e 1f>
 . <" !"#$%&'()*+,-./"> <//  32: 20 21 22 23  24 25 26 27  28 29 2a 2b  2c 2d 2e 2f>
 . <"0123456789:;<=>?"> <//  48: 30 31 32 33  34 35 36 37  38 39 3a 3b  3c 3d 3e 3f>
 . <"@ABCDEFGHIJKLMNO"> <//  64: 40 41 42 43  44 45 46 47  48 49 4a 4b  4c 4d 4e 4f>
 . <"PQRSTUVWXYZ[\]^_"> <//  80: 50 51 52 53  54 55 56 57  58 59 5a 5b  5c 5d 5e 5f>
 . <"`abcdefghijklmno"> <//  96: 60 61 62 63  64 65 66 67  68 69 6a 6b  6c 6d 6e 6f>
 . <"pqrstuvwxyz{|}~⌂"> <// 112: 70 71 72 73  74 75 76 77  78 79 7a 7b  7c 7d 7e 7f>
 . <"ÇüéâäàåçêëèïîìÄÅ"> <// 128: 80 81 82 83  84 85 86 87  88 89 8a 8b  8c 8d 8e 8f>
 . <"ÉæÆôöòûùÿÖÜ¢£¥₧ƒ"> <// 144: 90 91 92 93  94 95 96 97  98 99 9a 9b  9c 9d 9e 9f>
 . <"áíóúñÑªº¿⌐¬½¼¡«»"> <// 160: a0 a1 a2 a3  a4 a5 a6 a7  a8 a9 aa ab  ac ad ae af>
 . <"░▒▓│┤╡╢╖╕╣║╗╝╜╛┐"> <// 176: b0 b1 b2 b3  b4 b5 b6 b7  b8 b9 ba bb  bc bd be bf>
 . <"└┴┬├─┼╞╟╚╔╩╦╠═╬╧"> <// 192: c0 c1 c2 c3  c4 c5 c6 c7  c8 c9 ca cb  cc cd ce cf>
 . <"╨╤╥╙╘╒╓╫╪┘┌█▄▌▐▀"> <// 208: d0 d1 d2 d3  d4 d5 d6 d7  d8 d9 da db  dc dd de df>
 . <"αßΓπΣσµτΦΘΩδ∞φε∩"> <// 224: e0 e1 e2 e3  e4 e5 e6 e7  e8 e9 ea eb  ec ed ee ef>
 . <"≡±≥≤⌠⌡÷≈°∙·√ⁿ²■ "> <// 240: f0 f1 f2 f3  f4 f5 f6 f7  f8 f9 fa fb  fc fd fe ff> <// 256 B>
END
    );
}
