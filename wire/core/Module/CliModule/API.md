# CliModule

PHP interface that adds command-line support to a ProcessWire module. Implement it to
expose your module as a CLI command invoked from the ProcessWire root:

```bash
php index.php <cli-name> [args...]
```

## Implementing CliModule

The `CliModule` interface can be implemented directly with a PHP `implements`
statement in the class definition, or it can be implemented without it.
ProcessWire core modules should implement it directly. But 3rd party modules
may want to implement without the `implements CliModule` so that they remain
compatible with ProcessWire versions prior to the addition of the `CliModule`
interface.

If implementing without `implements CliModule` skip step #1 below and proceed
to step #2. ProcessWire will recognize it as a CLI supporting module either way.

1. Optional: Add `implements CliModule` to the class definition (alongside `Module`)
2. Add `'cli' => 'commandname'` to `getModuleInfo()`
3. Implement `executeCli()` and `getCliCommands()`

```php
class HelloWorldCli extends WireData implements Module, CliModule {

    public static function getModuleInfo() {
        return [
            'title'   => 'Hello World CLI',
            'version' => 1,
            'cli'     => 'hello', // php index.php hello
            'requires' => 'ProcessWire>=3.0.259',
        ];
    }

    public function executeCli(array $args) {
        $command = $args[0] ?? '';
        $name    = $args[1] ?? 'friend';
        if($command === 'hi') {
            echo "Hello, $name!";
        } else if($command === 'bye') {
            echo "Goodbye, $name!";
        } else {
            echo "Specify 'hi' or 'bye', optionally followed by a name";
        }
    }

    public function getCliCommands() {
        return [
            'hi'  => 'Say hello',
            'bye' => 'Say goodbye',
        ];
    }
}
```

Usage:
```bash
php index.php hello
php index.php hello hi Ryan
php index.php hello bye
```

## Methods

### executeCli(array $args)

Handle a CLI invocation. `$args` contains everything typed after the CLI name.

```bash
# php index.php hello hi Ryan  →  executeCli(['hi', 'Ryan'])
# php index.php hello          →  renders help from getCliCommands()
```

Output directly with `echo` or `print`. ProcessWire appends a trailing newline
automatically — no need to add `\n` at the end.

### getCliCommands()

Return the list of supported sub-commands. Used only for help output when the user runs
the module with no arguments or requests a command listing.

Three supported return formats:

```php
// Option 1: Associative: keys=command names, values=1-line descriptions (recommended)
return [
    'sync' => 'Synchronize data', 
    'status' => 'Show status'
];

// Option 2: Indexed: command names only
return ['sync', 'status'];

// Option 3: String: freeform help text output as-is
return "Usage: php index.php myjob [sync|status] [--verbose]";
```
If using Option 1 above, the returned array can also have these special keys:

- `:description` (array): One or more texts that appear above the commands list.
- `:note` (array): One or more texts that appear below the commands list.

For example:

```php
public function getCliCommands() {
    return [
        'hi'  => 'Say hello',
        'bye' => 'Say goodbye',
        ':description' => [
            'This is a description of the module',
            'It can span multiple lines'
        ],
        ':note' => [
            'This is a note that appears below the commands list',
            'It can also span multiple lines'
        ]
    ];
}
```

Resulting output when you type `php index.php hello`:

```
  HelloWorldCli
  =============
  This is a description of the module
  It can span multiple lines
  
  php index.php hello hi        Say hello
  php index.php hello bye       Say goodbye
  
  This is a note that appears below the commands list
  It can also span multiple lines
```

## getModuleInfo() required keys

| Key         | Type     | Description                                                      |
|-------------|----------|------------------------------------------------------------------|
| `cli`       | `string` | CLI command name; invoked with `php index.php <cli>`             |

## Notes

- CliModule modules do **not** need `'autoload' => true` — ProcessWire loads them on demand.
- When a user runs `php index.php <cli>` with no arguments, ProcessWire renders help from
  `getCliCommands()` and does not call `executeCli()`.
- `implements CliModule` is not strictly required: a module with `'cli'`
  in `getModuleInfo()` and `executeCli()` / `getCliCommands()` defined will also work.
- See [[WireTests]] for a real-world example of CliModule in the core.
- **Source file:** `wire/core/Module/CliModule/CliModule.php`
