<?php

namespace Dogma\Tests\Debug;

use Dogma\Debug\Ansi;
use Dogma\Debug\Assert;
use Dogma\Debug\Debugger;
use Dogma\Debug\Message;

require_once __DIR__ . '/../bootstrap.php';

Debugger::send(Message::DUMP,
    Ansi::white('white') . "\n"
    . Ansi::lgray('light gray') . "\n"
    . Ansi::dgray('dark gray') . "\n"
    . Ansi::black('black') . "\n"
    . Ansi::lred('light red') . "\n"
    . Ansi::dred('dark red') . "\n"
    . Ansi::lgreen('light green') . "\n"
    . Ansi::dgreen('dark green') . "\n"
    . Ansi::lblue('light blue') . "\n"
    . Ansi::dblue('dark blue') . "\n"
    . Ansi::lcyan('light cyan') . "\n"
    . Ansi::dcyan('dark cyan') . "\n"
    . Ansi::lmagenta('light magenta') . "\n"
    . Ansi::dmagenta('dark magenta') . "\n"
    . Ansi::lyellow('light yellow') . "\n"
    . Ansi::dyellow('dark yellow') . "\n"
    . "\n"
    . Ansi::rgb('orange', '#FFA500') . "\n"
    . Ansi::rgb('khaki', '#F0E68C') . "\n"
    . Ansi::rgb('spring green', '#00FF7F') . "\n"
    . Ansi::rgb('cadet blue', '#5F9EA0') . "\n"
    . Ansi::rgb('sienna', '#A0522D') . "\n"
    . "\n"
    . Ansi::rgb('orange', null, '#FFA500') . "\n"
    . Ansi::rgb('khaki', null, '#F0E68C') . "\n"
    . Ansi::rgb('spring green', null, '#00FF7F') . "\n"
    . Ansi::rgb('cadet blue', null, '#5F9EA0') . "\n"
    . Ansi::rgb('sienna', null, '#A0522D')
);

Assert::true(true);
