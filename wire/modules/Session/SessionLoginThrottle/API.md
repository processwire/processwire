# SessionLoginThrottle

Throttles login attempts by imposing a linearly increasing delay after each
failed attempt. Helps prevent dictionary and brute-force attacks against user
accounts.

SessionLoginThrottle is an autoload module that hooks into the `Session::allowLoginAttempt`
hook. On every login attempt, it checks a database table (`session_login_throttle`)
for previous failed attempts by the same username (and optionally IP address). If a
retry arrives too soon, the login is blocked and an error is shown.

The delay is calculated as `(attempts - 1) × seconds`, capped at `maxSeconds`. After
`maxSeconds` of inactivity, the attempt counter resets.

```php
// The module is installed and configured in the admin.
// Once configured, it protects logins automatically — no API calls needed.

// Check if a specific name is currently blocked (unusual, but available):
$module = $modules->get('SessionLoginThrottle');
// There is no public allowLogin() — throttling is automatic via hooks.
```

---

## Configuration

Configuration is managed in the admin at **Modules > Configure > SessionLoginThrottle**,
or programmatically:

```php
$module = $modules->get('SessionLoginThrottle');
$module->set('seconds', 10);
$module->set('maxSeconds', 300);
$module->set('checkIP', true);
$module->set('logFails', true);
$modules->saveModuleConfigData($module, $module->getArray());
```

| Property    | Type        | Default | Description                                                                                    |
|-------------|-------------|---------|------------------------------------------------------------------------------------------------|
| `seconds`   | `int`       | `5`     | Base wait time in seconds per failed attempt. Multiplied by `(attempts - 1)`.                  |
| `maxSeconds`| `int`       | `60`    | Maximum wait time cap in seconds. Also resets the attempt counter after this many idle seconds. |
| `checkIP`   | `bool\|int` | `false` | Throttle by IP address in addition to username. Recommended unless users share IPs.            |
| `logFails`  | `bool\|int` | `false` | Write a log entry to `login-throttle` when a name/IP is blocked.                               |

### Wait time calculation

After `N` failed attempts, the required wait before the next attempt is:

```
wait = min((N - 1) × seconds, maxSeconds)
```

| Attempt | Wait (with `seconds=5`, `maxSeconds=60`) |
|---------|------------------------------------------|
| 1st     | 0 seconds (no wait)                      |
| 2nd     | 5 seconds                                |
| 3rd     | 10 seconds                               |
| 4th     | 15 seconds                               |
| …       | …                                        |
| 13th    | 60 seconds (capped)                      |

If `maxSeconds` have elapsed since the last attempt, the counter resets and the next
attempt is treated as the first.

---

## How it works

### Hook into Session

The module hooks `Session::allowLoginAttempt` in its `init()` method. This hook fires
before a user object is loaded — the module only needs the login name, not the user.

```php
// In SessionLoginThrottle::init():
$this->wire()->session->addHookAfter(
    'allowLoginAttempt',
    $this,
    'hookSessionAllowLoginAttempt'
);
```

The hook checks whether the login name (and optionally IP) has exceeded the allowed
attempt frequency. If another module has already disallowed the login, this module
does nothing (it only acts when `$event->return` is `true`).

### Database table

On install, the module creates the `session_login_throttle` table:

| Column        | Type                   | Description                         |
|---------------|------------------------|-------------------------------------|
| `name`        | `varchar(128)`         | Username or IP address (primary key)|
| `attempts`    | `int(10) unsigned`     | Number of consecutive failed attempts|
| `last_attempt`| `int(10) unsigned`     | Unix timestamp of last attempt      |

Expired rows (`last_attempt` older than `maxSeconds`) are deleted on each check.

### Error behavior

When a login is blocked:

- **In the admin login (`ProcessLogin`)**: An error notice is shown to the user.
- **In other contexts (API usage)**: A `SessionLoginThrottleException` is thrown
  to ensure the error cannot be missed.

### Request caching

The `allowLogin()` method caches results per name for the lifetime of the request.
This prevents multiple hook invocations from double-counting an attempt within a
single request.

---

## Hooks

SessionLoginThrottle does not expose its own hookable methods. It works by hooking
into Session:

| Session Hook              | Purpose                                            |
|---------------------------|----------------------------------------------------|
| `Session::allowLoginAttempt` | The module hooks *after* this to check attempt frequency |

If you need custom throttling logic, hook `Session::allowLoginAttempt` yourself.
SessionLoginThrottle respects the existing return value — if another module has
already set it to `false`, this module does nothing.

```php
// Custom IP blocklist, runs before SessionLoginThrottle
$wire->addHookBefore('Session::allowLoginAttempt', function(HookEvent $event) {
    $name = $event->arguments(0);
    $ip = $event->wire()->session->getIP();
    $blocked = ['10.0.0.1', '192.168.1.100'];
    if(in_array($ip, $blocked)) {
        $event->return = false;
    }
});
```

---

## SessionLoginThrottleException

A custom exception thrown when a non-ProcessLogin context triggers a throttle block.

```php
try {
    $session->login('username', 'password');
} catch(SessionLoginThrottleException $e) {
    // Login blocked by throttle
    echo $e->getMessage();
}
```

This exception extends `WireException`.

---

## Notes

- The module does **not** run when `$config->demo` is enabled (prevents interference
  with the demo site).
- The module is `singular: true` — no more than one instance can be loaded.
- `autoload` is conditional: the module only loads when `$_POST` is not empty
  (a form submission is in progress).
- The database table is indexed by `name` (primary key), so lookups are fast even
  with many tracked usernames.
- Expired entries are cleaned up on every check, so the table stays small.
- IP-based throttling is unreliable on mobile/cellular networks where IPs change
  frequently. Use it as a secondary signal, not a primary defense.
- See [[Session]] for the full authentication API, including `login()`, `logout()`,
  and the complete list of hookable methods.
- **Source file:** `wire/modules/Session/SessionLoginThrottle/SessionLoginThrottle.module`
