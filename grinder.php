<?php

$name = $argv[1];
$file = fopen($name, 'r');
if (!$file) {
    echo "File $name not found.\n";
    exit;
}

// filter out files:
// cat t3.prof | grep -v -E "^(fl|cfl|su|ve|cr|cm|pa|po|ev)" -m 80

// extract functions:
// cat t3.prof | grep -E "^(fn|cfn)=\([0-9]+\) "

// fl=({id}) {filename}
// fn=({id}) {class::function}
// cfl=({id}) {filename}
// cfn=({id}) {class::function}
// calls={count} {line}
// {line} {time} {memory}

/** @var array<int, string> $functions */
$functions = [];

$i = 0;
$function = '';
$calls = [];
/** @var string $line */
while ($line = fgets($file)) {
    rd($line);
    $i++;
    if ($i < 8) {
        // skip header
        continue;
    }

    if ($line === "\n") {
        // empty
        continue;
    } elseif ($line[1] === 'l') {
        // fl=({id}) {filename}
        continue;
    } elseif ($line[1] === 'n') {
        // fn=({id}) {class::function}
        // {line} {time} {memory}
        [$fid, $function] = explode(')', substr($line, 3));
        if ($function !== '') {
            $function = trim($function);
            $functions[$fid] = $function;
        }

    }
    if ($i > 50) {
        break;
    }
}
