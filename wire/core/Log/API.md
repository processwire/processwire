# WireLog / $log

`$log` is the API variable for reading and writing log files in ProcessWire. Logs are
plain-text files stored in `/site/assets/logs/` and viewable in the admin at
**Setup > Logs**. Each entry records a date/time, username, URL, and message.

`$log` is accessible in templates as `$log`, `wire()->log`, or `log()` (if the
functions API is enabled); and in modules as `$this->wire()->log`.

---

## Saving to a log

### $log->save($name, $text, $options)

Save an entry to a named log file. Creates the log if it doesn't exist.

- **Arguments:** `save(string $name, string|array|object $text, array $options = [])`
- **Returns:** `bool`
- `$name` must be a lowercase word using only `[-._a-z0-9]` characters, no extension.
- Passing an array or object for `$text` saves it as a JSON entry (3.0.256+).
- This is a hookable method (`___save()`).

~~~~~php
// Log a plain text message to /site/assets/logs/search.txt
$log->save('search', "User searched for: $phrase");

// Log structured data as JSON (3.0.256+)
$log->save('orders', ['id' => 123, 'total' => 49.95, 'status' => 'paid']);
~~~~~

**Key options:**

| Option           | Description                                                                         |
|------------------|-------------------------------------------------------------------------------------|
| `showUser`       | Include username in the entry? (default=`true`)                                     |
| `showURL`        | Include current URL in the entry? (default=`true`)                                  |
| `user`           | `User`, username string, or `null` to use current user (default=`null`)             |
| `url`            | URL to record; default is auto-detected from current request                        |
| `delimiter`      | Field delimiter used within the log entry (default=tab); pass the same delimiter to `getLines()`/`getEntries()` when reading |
| `maxLineLength`  | Max bytes per log line (default=8192)                                               |
| `allowNewlines`  | Preserve newlines in text? (default=`false`)                                        |
| `queue`          | Queue entry to save at end of request, grouping same-name/options entries (default=`false`) |

---

### $log->message($text)

Save a message to the built-in `messages` log (`messages.txt`). Optionally also
displays it as a notice to the admin user.

- **Arguments:** `message(string $text, bool|int $flags = 0)`
- **Returns:** `$this`
- Pass `true` for `$flags` to also display the message interactively in the admin.

~~~~~php
$log->message("User {$user->name} updated their profile");
~~~~~

---

### $log->warning($text)

Save a warning to the built-in `warnings` log (`warnings.txt`).

- **Arguments:** `warning(string $text, bool|int $flags = 0)`
- **Returns:** `$this`

~~~~~php
$log->warning("Config value 'fooSetting' is deprecated");
~~~~~

---

### $log->error($text)

Save an error to the built-in `errors` log (`errors.txt`).

- **Arguments:** `error(string $text, bool|int $flags = 0)`
- **Returns:** `$this`

~~~~~php
$log->error("Failed to connect to payment gateway");
~~~~~

---

## Logging from within a Wire class

Every class that extends `Wire` (modules, Fieldtypes, Inputfields, Processes, etc.)
inherits a `log()` method that writes to a log automatically named after the class.

### $this->log($text, $options)

Log a message using the calling class's name as the log name. A class named
`MyWidgetData` logs to `my-widget-data.txt` (class name converted to hyphenated
lowercase).

- **Arguments:** `log(string $str = '', array $options = [])`
- **Returns:** `WireLog` — the `$log` API variable
- Call with no argument to retrieve the `$log` API variable from within any Wire class.
- This is a hookable method (`___log()`).

**Options:**

| Option | Description |
|--------|---|
| `name` | Override the log name (default=auto from class name) |
| `url`  | URL to record with the entry (default=auto-detect) |
| `user` | `User`, username string, or `null` for current user (default=`null`) |

~~~~~php
// In a module or any Wire subclass:

// Log to my-module.txt (log name derived from class name)
$this->log("User saved a new item");

// Override the log name
$this->log("Something happened", ['name' => 'my-custom-log']);

// Get the $log API variable from within a Wire class
$log = $this->log();
$total = $log->getTotalEntries($this->className(true));
~~~~~

---

## Reading a log

### $log->getEntries($name, $options)

Get log entries as structured associative arrays. Most useful for programmatic access.

- **Arguments:** `getEntries(string $name, array $options = [])`
- **Returns:** `array` of associative arrays, each with keys:
  - `date` (string) — formatted date/time
  - `user` (string|false) — username, or `false` if unknown
  - `url` (string|false) — URL, or `false` if unknown
  - `text` (string) — log message
- Pagination aware (reads `$input->pageNum` automatically).

~~~~~php
$entries = $log->getEntries('search', ['limit' => 50]);

foreach($entries as $entry) {
    echo "$entry[date] — $entry[user] — $entry[text]\n";
}

// Filter by date range
$entries = $log->getEntries('errors', [
    'dateFrom' => '2025-01-01',
    'dateTo'   => '2025-01-31',
    'limit'    => 100,
]);

// Search for text
$entries = $log->getEntries('search', ['text' => 'processwire', 'limit' => 25]);
~~~~~

**Options:**

| Option     | Description                                                         |
|------------|---------------------------------------------------------------------|
| `limit`    | Max entries to return (default=100)                                 |
| `text`     | Filter to entries containing this string                            |
| `dateFrom` | Oldest date to include (unix timestamp or date string)              |
| `dateTo`   | Newest date to include (unix timestamp or date string, default=now) |
| `reverse`  | Reverse order, newest first? (default=`true`)                       |
| `pageNum`  | Pagination page number (default=auto-detect)                        |
| `delimiter`| Log entry delimiter; use the same value passed to `save()`           |

---

### $log->getLines($name, $options)

Same as `getEntries()` but returns raw tab-delimited strings rather than structured arrays.
Accepts the same options.

~~~~~php
$lines = $log->getLines('search', ['limit' => 20]);
foreach($lines as $line) echo $line . "\n";
~~~~~

---

### $log->getTotalEntries($name)

Get the total number of entries in a log.

- **Returns:** `int`

~~~~~php
$total = $log->getTotalEntries('errors');
~~~~~

---

## Inspecting logs

### $log->getLogs($sortNewest)

Get an array of all available logs.

- **Arguments:** `getLogs(bool $sortNewest = false)`
- **Returns:** `array` — indexed by log name, each value is an associative array with:
  - `name` (string) — log name without extension
  - `file` (string) — full path to log file
  - `size` (int) — file size in bytes
  - `modified` (int) — last-modified unix timestamp

~~~~~php
foreach($log->getLogs() as $name => $info) {
    echo "$name: " . number_format($info['size']) . " bytes\n";
}

// Sort by most recently modified
$logs = $log->getLogs(true);
~~~~~

---

### $log->exists($name)

Check whether a log exists.

- **Returns:** `bool`

~~~~~php
if($log->exists('search')) {
    $entries = $log->getEntries('search');
}
~~~~~

---

### $log->disable($name)

Temporarily disable a log name for the current request. Disabled logs cause matching
`save()` calls to return `false` without writing an entry. Specify `'*'` to disable
all log names.

- **Arguments:** `disable(string $name)`
- **Returns:** `$this`

~~~~~php
$log->disable('search');
$log->save('search', 'This will not be written');
~~~~~

---

### $log->enable($name)

Re-enable a log name previously disabled with `disable()`. Specify `'*'` to re-enable
all logs after `disable('*')`.

- **Arguments:** `enable(string $name)`
- **Returns:** `$this`

~~~~~php
$log->enable('search');
$log->save('search', 'This will be written again');
~~~~~

---

### $log->getFilename($name)

Get the full filesystem path to a log file.

- **Returns:** `string`

~~~~~php
$path = $log->getFilename('errors');
// e.g. /var/www/html/site/assets/logs/errors.txt
~~~~~

---

## Managing logs

### $log->prune($name, $days)

Remove all entries older than `$days` days from a log.

- **Arguments:** `prune(string $name, int $days)`
- **Returns:** `int` — number of entries remaining after pruning

~~~~~php
// Keep only the last 30 days of search logs
$log->prune('search', 30);
~~~~~

---

### $log->pruneAll($days)

Prune all log files to the given number of days.

- **Arguments:** `pruneAll(int $days)`
- **Returns:** `array` — log name => remaining entry count

~~~~~php
$log->pruneAll(90);
~~~~~

---

### $log->delete($name)

Delete a log file entirely.

- **Returns:** `bool`

~~~~~php
$log->delete('search');
~~~~~

---

### $log->deleteAll($throw)

Delete all log files.

- **Arguments:** `deleteAll(bool $throw = false)`
- **Returns:** `array` — names of deleted logs
- Pass `true` to throw `WireException` if any deletion fails.

~~~~~php
$deleted = $log->deleteAll();
~~~~~

---

## Built-in logs

ProcessWire writes to several logs automatically. These are viewable in the admin at
**Setup > Logs**:

| Log name     | Description                                                             |
|--------------|-------------------------------------------------------------------------|
| `errors`     | PHP errors and exceptions caught by ProcessWire                         |
| `exceptions` | Unhandled exceptions (enabled via `$config->logs`)                      |
| `messages`   | Entries written via `$log->message()`                                   |
| `modules`    | Module install/uninstall/upgrade activity (enabled via `$config->logs`) |
| `warnings`   | Entries written via `$log->warning()`                                   |

Additional built-in logs (`deprecated`, `404`, etc.) may appear depending on
configuration and installed modules.

Enable or disable built-in logs in `/site/config.php`:

~~~~~php
// Enable the 'deprecated' log (tracks use of deprecated methods)
$config->logs[] = 'deprecated';

// Disable the 'modules' log
$config->logs = array_diff($config->logs, ['modules']);
~~~~~

---

## Notes

- Source file: `wire/core/Log/WireLog.php` (backed by `wire/core/Log/FileLog.php`).
- Log files are plain text at `/site/assets/logs/[name].txt`, one entry per line,
  tab-delimited: `date\tuser\turl\ttext`.
- The `queue` option on `save()` defers writing until the end of the request, grouping
  multiple entries with the same name/options into one physical log entry. Embedded
  newlines are stored as `[br]` in the file and restored by `getEntries()`.
- `message()`, `warning()`, and `error()` double as both logging and ProcessWire notice
  methods — passing `true` as `$flags` displays the message interactively in the admin.
- Some logs are always enabled, such as `session` and `system-updater`. 
- `save()` is hookable (`___save()`), making it straightforward to intercept or redirect
  log entries to an external logging system.
