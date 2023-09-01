<?php

use Dogma\Debug\FormattersReflection;

require_once __DIR__ . '/bootstrap.php';

FormattersReflection::register();

foreach (get_loaded_extensions() as $ext) {
    rd(new ReflectionExtension($ext));
}

foreach (get_loaded_extensions(true) as $ext) {
    rd(new ReflectionZendExtension($ext));
}

foreach (get_defined_functions()['user'] as $function) {
    rd(new ReflectionFunction($function));
}

foreach (get_declared_classes() as $class) {
    rd(new ReflectionClass($class));
}
