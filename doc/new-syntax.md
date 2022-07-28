
Debugger actions syntax
=======================

for most actions, there are three alternatives how to call them:
- directly calling the method on Debugger (e.g. `Debugger::dump($value);`) or shortcut functions (e.g. `rd($value);`)
    - pros:
        - autocomplete
        - returns value
    - cons:
        - pollute imports
        - must be removed from production code
        - the first option is quite wordy
- using comment syntax with `##` - e.g. `## $value`
    - pros:
        - minimal syntax
        - not actual code
        - allows easy usage of additional debugger variables and operators in conditions
        - for some actions the only way to do them (return points etc.)
    - cons:
        - no autocomplete
        - implemented with generated code, so more awareness is advised

Actions
-------

*note: parts in square brackets are optional*

### Dumps:

#### Dump
dump variable/expression
- `Debugger::dump($value, [$maxDepth, $traceLength])`
- `rd($value, [$maxDepth, $traceLength]);`
- `## [label:] expression`

#### Native var_dump()
ordinary var_dump() with better formatting and colors
- `Debugger::varDump($value, [$colors]);`
- `rvd($value, [$colors]);`
- `##vd [label:] expression`

#### Grid dump
dump tabular data as grid instead of nested arrays dump
- `Debugger::grid($value, [$maxDepth, $traceLength])`
- `rg($value, [$maxDepth, $traceLength]);`
- `##g [label:] expression`

#### Output dump
capture and dump callback output

- `Debugger::output($callback, [$maxDepth, $traceLength]);`
- `ro($callback, [$maxDepth, $traceLength]);`
- `##o [label:] callback`

#### Structure size
calculate and dump approximate object or array memory requirements

- `Debugger::size($value);`
- `rs($value);`
- `##s [label:] $value`

#### Diff dump
diff two variables/expressions
- `Debugger::diff($first, $second, [$maxDepth, $traceLength]);`
- `rdiff($first, $second, [$maxDepth, $traceLength]);`
- `##d [label:] first second`

#### Memory diff dump
print diff of process memory (globals and static members of classes, functions, closures etc., does not see local variables except for arguments in current callstack)

(on the first call only saves a snapshot)
- `Debugger::memoryDiff();`
- `rmd();`
- `##md [label]`


### Labels and resources:

#### Label
set and print a label (scalar value) for better structured debug output

label value can be used in conditions (`@l`, or `@l[name]`)
- `Debugger::label($value, [$name]);`
- `rl($value, [$name]);`
- `##l [name:] value` - quotes around string literals are not required

#### Variable
set a debugger variable (similarly to `##l`), but do not print anything

variable value can be used in conditions (`@v[name]`)
- `##v name: expression`

#### Timer / event counter
print time spent since last event of the same name (or since program start) and increment event counter
- `Debugger::timer([$name]);`
- `rt([$name]);`
- `##t [name]`

#### Event counter
increment event counter (same as `##t`), but do not print anything
- `Debugger::event([$name]);`
- `re([$name]);`
- `##e [name]`

#### Memory usage
print currently used memory and change since last event of the same name
- `Debugger::memory([$name]);`
- `rm([$name]);`
- `##m [name]`


### Program flow:

#### Callstack
print formatted callstack
- `Debugger::callstack($length, [$argsDepth, $codeLines, $codeDepth, $callstack]);`
- `rc($length, [$argsDepth, $codeLines, $codeDepth, $callstack]);`
- `##c`

#### Function/method name
print current function/method name when entered

(when `##f` is used above a class, it affects all methods)
- `Debugger::function();`
- `rf();`
- `##f`

#### Return points
print places where function returned, optionally with returned value

(when used above a class, affects all methods)
- `##r`
- `##rv` - with returned/yielded values

#### Program path
print program path - return points (`return`, `yield`), optionally with returned value, 
visited code blocks (`if`, `elseif`, `else`, `for`, `foreach`, `do`, `while`) 
and exits from them (`break`, `continue`)

(when used above a class, affects all methods)
- `##p`
- `##pv` - with returned/yielded values


Options in comment syntax
-------------------------

basics:
- when dumping an expression, options should be separated by `;` - e.g. `## $a + $b; options...` 
- options may be separated by a space - e.g. `## $foo 3 1 1`
- ...but it is not necessary when they do not interfere - e.g. `## $foo 3^1R/w`

options:
- `n` - max depth for values - e.g. `## $val 3` (must precede trace options)
- `^n` - trace trace length - e.g. `## $val ^3`
- `^n n n n` - more arguments for formatting trace in this order: `$traceLength`, `$argsDepth`, `$codeLines`, `$codeDepth`
- `R`, `G`, `B`, `C`, `M`, `Y`, `K`, `W`, `r`, `g`, `b`, `c`, `m`, `y`, `k`, `w` - label foreground color (upper case = bright, lower case = dark)
- `/R`, `/G`, ..., `/w` - label background color (upper case = bright, lower case = dark)
- `#tag` - tags can be used to switch on/off groups of debug actions
- `@sql` - strings escaping (`php`, `js`, `json`, `mysql`, `pgsql`, `names`, `symbols`, `cp437`)

conditions:
- `? expression` - positive condition (condition must be the last option)
- `! expression` - negative condition (condition must be the last option)
- expression is just php expression, but may use debugger variables and operators listed below

### Condition operators:
- `value =~ pattern` - short for `preg_match()`
- `value !~ pattern` - short for `!preg_match()`
- `value in array` - short for `in_array()`
- `value !in array` - short for `!in_array()`
- `value in~ patterns` - value matches any of the patterns
- `value !in~ patterns` - values does not match any of the patterns
- `pattern ~in values` - pattern matches any of the values
- `pattern !~in values` - pattern does not match any of the values
- `pattern ~all values` - pattern matches all the values
- `pattern !~all values` - pattern does not match all the values

### Condition variables:
- `@v[.name]`, `@value[.name]` - current value in `##` or `##o` (without name) or named value from `##v`
- `@l[.name]`, `@label[.name]` - last label set with `##l`
- `@e[.name]`, `@event[.name]` - event counter value set with `##t` or `##e`
- `@t[.name]`, `@time[.name]` - last timer value from `##t`
- `@m[.name]`, `@memory[.name]` - last memory consumption change from `##m`
- `@ct[.name]`, `@ctime[.name]` - current time (since process start)
- `@cm[.name]`, `@cmemory[.name]` - current memory consumption
- `@c[[filter]]`, `@class[[filter]]` - class name in callstack
- `@f[[filter]]`, `@function[[filter]]` - function/class+method name in callstack
- `@p[[filter]]`, `@path[[filter]]` - file path and line in callstack (e.g. `foo/bar.php:123`)
- `@i[level]`, `@iteration[level]` - number of iteration in a loop (`for`, `foreach`, `do`, `while`). starts from `0`. level `0` means current level, higher numbers are parent loops (e.g. nested `foreach` loops)
    - e.g. `## $foo ? @i[1] == 0` means dump `$foo` only in first iteration of parent loop 

callstack filter can contain:
- a numeric index `[n]` - `0` is last (which means previous function/method which called the current one. callstack items in dumps are numbered in the opposite way to show how deep the callstack is when only the last item is printed)
- a range `[from..to]` - e.g. `[0..5]`
- other class/file filter `[!]` - exclude previous instances of the same class/file from callstack
- other namespace/directory filter `[!~]`, `[!~~]` etc. - exclude classes/files sharing same namespace/directory from callstack. one `~` for each namespace level that must differ
- same class/file filter `[=]` - include previous instances of the same class/file from callstack
- same namespace/directory filter `[=~]`, `[=~~]` etc. - include classes/files sharing same namespace/directory from callstack. one `~` for each namespace level that must not differ
- include name filter `[=~pattern]` - only keep instances matching given class/file name pattern. pattern may contain `/`, `\`, `^`, `$`, `.`, `*`, `?`
- exclude name filter `[!~pattern]` - filter instances matching given class/file name pattern. pattern may contain `/`, `\`, `^`, `$`, `.`, `*`, `?`
- filters can be combined - e.g. `@c[!0]` - means first instance of other class in callstack
- filters are applied from left to right, so `@c[!0]` is not the same as `@c[0!]`

more complex example of conditions:
- `## $var ? '~Foo/Bar~' ~in @c[!0..3] && @cm > 2^20` - dump variable `$var`, if some of the last three class names in callstack (filtered for other classes only) matches given pattern and current memory consumption exceeds 1MB
