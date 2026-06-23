# Debug

Static utility class for timing code execution, generating debug backtraces, and
dumping variables to strings. All methods are static — no instantiation needed.

```php
// Typical timing usage
$timer = Debug::timer();
some_code();
$elapsed = Debug::timer($timer);
echo "Took $elapsed seconds\n";
```

## Timing code execution

### timer($key = '', $reset = false)

The simplest way to time code. Call with no arguments to start a timer (returns a key),
then call again with the key to get elapsed time.

```php
$timer = Debug::timer();       // start timer, returns key
some_code_that_takes_time();
$elapsed = Debug::timer($timer); // returns elapsed seconds as string

// Named timers
Debug::timer('my_op');
some_code();
$elapsed = Debug::timer('my_op');

// Reset an existing timer
$elapsed = Debug::timer('my_op', true); // resets and restarts

// Remove timer when done
Debug::removeTimer('my_op');
```

Multiple calls to `Debug::timer($key)` with the same key return cumulative elapsed
time since the original start. They do not restart the timer.

### startTimer($key = '')

Start a timer explicitly. Returns the timer key (auto-generated if `$key` is empty).

```php
$key = Debug::startTimer('db_query');
// ... code ...
```

### stopTimer($key = '', $option = null, $clear = true)

Stop a timer and return elapsed time as a formatted string. If `$key` is omitted,
the last started timer is used.

```php
$result = Debug::stopTimer('db_query');            // seconds with 4 decimal precision
$result = Debug::stopTimer('db_query', 'ms');      // milliseconds
$result = Debug::stopTimer('db_query', 2);         // seconds with 2 decimal precision
$result = Debug::stopTimer('db_query', null, false); // get time without clearing timer
```

The `$option` parameter accepts:
- An `int` — override decimal precision
- The string `'ms'` — return in milliseconds
- Any other string — use as suffix after the number

When `$clear` is true (default), the timer is removed after stopping. When false,
the timer remains active and can be stopped again for cumulative timing.

### resetTimer($key)

Reset a timer to start counting from right now.

```php
Debug::resetTimer('my_timer');
```

### removeTimer($key) / removeAll()

```php
Debug::removeTimer('my_timer');  // remove one timer
Debug::removeAll();              // remove all active timers
```

### getAll()

Returns an associative array of all active timers with their start values.

```php
$timers = Debug::getAll();
```

## Saving and retrieving timer results

Timers can be saved for later retrieval, useful for collecting timing data across
different parts of execution and reporting them all at the end.

### saveTimer($key, $note = '')

Save the elapsed time of a timer (also stops/removes it). Returns the elapsed time
string, or `false` if the timer didn't exist.

```php
Debug::saveTimer('db_query', 'Database queries');
Debug::saveTimer('template', 'Template rendering');
```

### getSavedTimer($key)

Retrieve a previously saved timer value. If a note was provided, it is appended.

```php
echo Debug::getSavedTimer('db_query'); // "0.0234 - Database queries"
```

### getSavedTimers()

Returns all saved timers as an associative array, sorted by elapsed time (longest first).

```php
foreach(Debug::getSavedTimers() as $name => $time) {
    echo "$name: $time\n";
}
```

### removeSavedTimer($key) / removeSavedTimers()

```php
Debug::removeSavedTimer('db_query');  // remove one saved timer
Debug::removeSavedTimers();           // remove all saved timers
```

## Timer settings

### timerSetting($key, $value = null)

Get or set a timer configuration value.

```php
// Get current precision
$precision = Debug::timerSetting('precision'); // default: 4

// Set precision to 2 decimal places
Debug::timerSetting('precision', 2);

// Enable millisecond mode by default
Debug::timerSetting('useMS', true);
```

Available settings:

| Setting        | Default | Description                                              |
|----------------|---------|----------------------------------------------------------|
| `precision`    | `4`     | Decimal places for seconds output                        |
| `precisionMS`  | `1`     | Decimal places for millisecond output                    |
| `useMS`        | `false` | Use milliseconds by default?                             |
| `suffix`       | `''`    | Suffix appended to seconds values                        |
| `suffixMS`     | `'ms'`  | Suffix appended to millisecond values                    |
| `useHrtime`    | `null`  | Use `hrtime()` (auto-detected based on PHP support)      |

## Debug backtrace

### backtrace(array $options = [])

Returns a simplified, ProcessWire-specific backtrace. More readable than PHP's
`debug_backtrace()` for PW development.

```php
// Get as array
$trace = Debug::backtrace();
foreach($trace as $item) {
    echo $item['file'] . ' » ' . $item['call'] . "\n";
}

// Get as string (newline-separated)
$str = Debug::backtrace(['getString' => true]);
echo $str;

// Limit depth
$trace = Debug::backtrace(['limit' => 5]);

// Show hook internals
$trace = Debug::backtrace(['showHooks' => true]);

// Skip specific calls
$trace = Debug::backtrace(['skipCalls' => ['myFunction', 'anotherFunction']]);
```

Options:

| Option        | Default                          | Description                                          |
|---------------|----------------------------------|------------------------------------------------------|
| `limit`       | `0`                              | Max trace depth (0 = no limit)                       |
| `flags`       | `DEBUG_BACKTRACE_PROVIDE_OBJECT` | Flags for PHP `debug_backtrace()`                    |
| `showHooks`   | `false`                          | Show internal Wire hook methods                      |
| `getString`   | `false`                          | Return newline-separated string instead of array     |
| `getCnt`      | `true`                           | Prefix lines with index number (string mode only)    |
| `getFile`     | `true`                           | Include file path (`true`, `false`, or `'basename'`) |
| `maxCount`    | `10`                             | Max array items shown                                |
| `maxStrlen`   | `100`                            | Max string length shown                              |
| `maxDepth`    | `5`                              | Max recursion depth for variable display              |
| `ellipsis`    | `' …'`                           | Text appended when values are truncated              |
| `skipCalls`   | `[]`                             | Function/method names to skip in the trace           |

## Dumping values

### toStr($value, array $options = [])

Convert any variable to a debug-friendly string representation.

```php
echo Debug::toStr($page);            // object info as string
echo Debug::toStr($array);           // array as JSON
echo Debug::toStr($value, ['method' => 'print_r']);  // use print_r format
echo Debug::toStr($value, ['html' => true]);          // HTML-safe <pre> output
```

Options:

| Option    | Default         | Description                                               |
|-----------|-----------------|-----------------------------------------------------------|
| `method`  | `'json_encode'` | Format method: `json_encode`, `var_dump`, `var_export`, `print_r` |
| `html`    | `false`         | Wrap output in `<pre>` with entity-encoded content         |

For `Wire` objects, `toStr()` uses `debugInfoSmall()` to produce a compact representation.
For objects implementing `__debugInfo()`, that method is called. For objects with
`__toString()`, the string representation is used.

## Notes

- All methods are static — the class is never instantiated.
- Timers use `hrtime()` when available (PHP 7.3+), falling back to `microtime()`.
- Timer keys are strings. When you pass an empty key to `startTimer()`, the start timestamp is used as the key.
- `saveTimer()` stops and removes the timer from active timers, saving it for later retrieval.
- `getSavedTimers()` sorts results by elapsed time in descending order (longest first).
- The `backtrace()` method filters out Wire/WireHooks internal calls by default for cleaner output.
- `toStr()` prefixes output with type labels like `int:`, `string:`, `object:ClassName` when applicable.
- **Source files:** `wire/core/Debug/Debug.php`, `wire/core/Debug/WireDebugInfo.php`.
