<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Ansi;
use Dogma\Debug\Assert;
use Dogma\Debug\Debugger;
use Dogma\Debug\Message;
use Dogma\Debug\Color;
use function array_diff_key;

require_once __DIR__ . '/../bootstrap.php';


$named = '';
foreach (Color::NAMED_COLORS as $name => $code) {
    $named .= "\n" . Ansi::rgb($name, $code) . ' ' . Ansi::rgb(" {$name} ",null, $code) . ' ' . Ansi::rgb(" {$name} ",'white', $code);
}

Debugger::send(Message::DUMP, "named colors:\n" . $named);


$default = Color::NAMED_COLORS['tomato'];
$unused = array_diff_key(Color::NAMED_COLORS, Color::NAMED_COLORS_4BIT);
$unused = Color::filterByLightness($unused, 25, 90);
$used = [];

$ordered = '';
while ($unused) {
    [$rgb, $name, $distance] = Color::pickMostDistant($unused, $used, $default);
    unset($unused[$name]);
    $used[$name] = $rgb;
    $ordered .= "\n" . Ansi::rgb($name, $rgb) . ' ' . Ansi::rgb(" {$name} ",null, $rgb) . ' ' . Ansi::rgb(" {$name} ",'white', $rgb) . " ($distance)";
}

Debugger::send(Message::DUMP, "ordered colors:\n" . $ordered);

Assert::true(true);
