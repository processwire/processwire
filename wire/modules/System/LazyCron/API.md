# LazyCron

Run scheduled jobs at fixed intervals without setting up a system cron job. LazyCron is
triggered by normal page views (or by a real cron job/CLI call), checks whether any
registered interval has elapsed, and fires the matching hook.

Because execution is piggy-backed on regular page views, intervals are guaranteed to be
**at least** as long as specified — never shorter. Accuracy depends on site traffic. For
precise timing, trigger LazyCron from a real cron job or the CLI.

```php
$lazyCron = $modules->get('LazyCron');
```

## How it works

When LazyCron is installed and autoloaded, it hooks `ProcessPageView::finished` and runs
pending jobs after the page response has been sent. This keeps cron overhead from slowing
down the visitor experience.

If the module's **CLI mode only** setting is enabled (`cliOnly`), LazyCron does not run
during HTTP requests. You then trigger it manually with `php index.php lazycron run` or
with a system cron job such as:

```bash
wget --quiet --no-cache -O - https://www.example.com/ > /dev/null
```

## Usage

### In a module

Attach a hook to one of the interval methods in your module's `init()` or `ready()` method:

```php
public function init() {
    $this->addHook('LazyCron::everyDay', $this, 'cleanupOldLogs');
}

public function cleanupOldLogs(HookEvent $event) {
    $secondsElapsed = $event->arguments(0);
    // run daily cleanup
}
```

### In templates or site hooks

Use `$wire->addHook()` (or `wire()->addHook()`) with a globally defined callback function:

```php
$wire->addHook('LazyCron::every30Minutes', null, 'myCronJob');

function myCronJob(HookEvent $event) {
    $secondsElapsed = $event->arguments(0);
    // runs roughly every 30 minutes
}
```

## Intervals

All interval methods are hookable. The first time LazyCron runs it fires every interval
to establish baselines, so expect all hooks to execute once immediately after installation.

| Method | Seconds | Approximate interval |
|--------|---------|----------------------|
| `every30Seconds` | 30 | 30 seconds |
| `everyMinute` | 60 | 1 minute |
| `every2Minutes` | 120 | 2 minutes |
| `every3Minutes` | 180 | 3 minutes |
| `every4Minutes` | 240 | 4 minutes |
| `every5Minutes` | 300 | 5 minutes |
| `every10Minutes` | 600 | 10 minutes |
| `every15Minutes` | 900 | 15 minutes |
| `every30Minutes` | 1800 | 30 minutes |
| `every45Minutes` | 2700 | 45 minutes |
| `everyHour` | 3600 | 1 hour |
| `every2Hours` | 7200 | 2 hours |
| `every4Hours` | 14400 | 4 hours |
| `every6Hours` | 21600 | 6 hours |
| `every12Hours` | 43200 | 12 hours |
| `everyDay` | 86400 | 1 day |
| `every2Days` | 172800 | 2 days |
| `every4Days` | 345600 | 4 days |
| `everyWeek` | 604800 | 1 week |
| `every2Weeks` | 1209600 | 2 weeks |
| `every4Weeks` | 2419200 | 4 weeks |

## Methods

### execute()

`execute(): void`

Run all pending LazyCron jobs immediately. This is called automatically after page views
unless `cliOnly` is enabled. You can also call it directly if you have a reference to the
module instance.

```php
$modules->get('LazyCron')->execute();
```

### getTimeFuncs()

`getTimeFuncs(): array`

Return all supported interval methods keyed by seconds.

```php
$funcs = $modules->get('LazyCron')->getTimeFuncs();
// [30 => 'every30Seconds', 60 => 'everyMinute', ...]
```

### getTimeFuncName($seconds)

`getTimeFuncName(int $seconds): string`

Return the interval method whose period is closest to the given number of seconds.

```php
$name = $modules->get('LazyCron')->getTimeFuncName(400); // 'every5Minutes'
```

### getTimeFuncSeconds($timeFuncName)

`getTimeFuncSeconds(string $timeFuncName): int`

Return the number of seconds associated with an interval method name. The method name is
case-sensitive.

```php
$seconds = $modules->get('LazyCron')->getTimeFuncSeconds('everyHour'); // 3600
```

### getCliCommands()

`getCliCommands(): array`

Return the CLI commands provided by this module (used by ProcessWire's CLI router).

```php
$commands = $modules->get('LazyCron')->getCliCommands();
// ['run' => 'Run pending attached LazyCron jobs', 'list' => 'List current attached jobs']
```

### executeCli(array $args)

`executeCli(array $args): void`

Dispatch a CLI command. Usually called by `php index.php lazycron <command>`.

```php
$modules->get('LazyCron')->executeCli(['run']);
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `cliOnly` | `bool` | When true, LazyCron does not run during HTTP requests. Use CLI/cron instead. |
| `lastTime` | `int` | Unix timestamp of the most recent LazyCron execution. |
| `lastResult` | `string` | Summary output from the most recent execution. |

## CLI

LazyCron registers the `lazycron` CLI namespace:

```bash
# Run pending jobs immediately
php index.php lazycron run

# List hooks currently attached to LazyCron intervals
php index.php lazycron list
```

When `cliOnly` is enabled, `run` is the only way (other than a real cron job hitting the
site) to trigger LazyCron jobs.

## Template-file jobs

As a convenience, LazyCron will include any PHP file placed in
`/site/templates/LazyCron/` whose name matches an interval method. For example,
`/site/templates/LazyCron/everyDay.php` runs with the `everyDay` interval. All API
variables (`$pages`, `$config`, etc.) are extracted into the file's scope.

```php
// /site/templates/LazyCron/everyDay.php
$old = $pages->find('template=temp, created<' . (time() - 86400));
$pages->delete($old);
```

This is useful for quick, template-level cron jobs that do not warrant a custom module.

## Notes

- LazyCron uses a lock file (`/site/assets/cache/LazyCronLock.cache`) to prevent concurrent
  runs. The lock expires automatically after one hour if a previous run failed fatally.
- State is stored in `/site/assets/cache/LazyCron.cache`.
- Intervals are approximate when triggered by page views; use a real cron job or the CLI
  for precise scheduling.
- The callback receives the actual elapsed seconds as its first argument.
- LazyCron is an autoload module and is obtained with `$modules->get('LazyCron')`.
- **Source file:** `wire/modules/System/LazyCron/LazyCron.module`.


