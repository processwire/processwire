# Session / $session

`$session` is the API variable for ProcessWire's session management. It handles reading
and writing session variables, authentication (login/logout), redirects, queued notices,
CSRF protection, and session-related request information.

`$session` is accessible in template files as `$session`, `wire()->session`, 
or just `session()` (if functions API enabled); and in modules or other Wire-derived 
objects as`$this->wire()->session`.

*Note: in this document the PHP type `mixed` is referring to `string|array|int|float`,
rather than a broader definition.*

---

## Getting and setting values

### $session->get($key) or $session->$key

Get a session variable.

- **Arguments:** `get(string|object $key, string $_key = null)`
- **Returns:** `mixed` — value, or `null` if not set
- Pass a namespace as the first argument and a key as the second to read a namespaced
  value (see [Namespaced values](#namespaced-values) below).

~~~~~php
// Set a session variable
$session->set('firstName', 'Bob');

// Get it back (current or any future request)
$name = $session->get('firstName'); // "Bob"

// Property-style access is also supported
$session->firstName = 'Bob';
$name = $session->firstName;
~~~~~

---

### $session->getVal($key)

Get a session variable, with a fallback value when not present. Available since 3.0.133.

- **Arguments:** `getVal(string $key, mixed $val = null)`
- **Returns:** `mixed` — the session value, or `$val` if not found

~~~~~php
// Returns "guest" if 'userName' is not in the session
$name = $session->getVal('userName', 'guest');
~~~~~

---

### $session->getAll()

Get all session variables as an associative array.

- **Arguments:** `getAll(string|object $ns = null)`
- **Returns:** `array`

~~~~~php
$vars = $session->getAll();
foreach($vars as $key => $value) {
    echo "$key: $value\n";
}
~~~~~

---

### $session->set($key, $value) or $session->$key = $value;

Set a session variable.

- **Arguments:** `set(string|object $key, mixed $value, mixed $_value = null)`
- **Returns:** `$this`
- To write a namespaced value: pass a namespace as the first argument, a key as the second, and the value as the
  third.

~~~~~php
$session->set('userName', 'bob');
$session->userName = 'bob'; // property-style equivalent

// Chaining is supported
$session->set('a', 1)->set('b', 2);
~~~~~

---

### $session->remove($key)

Remove a session variable.

- **Arguments:** `remove(string|object $key, string|bool $_key = null)`
- **Returns:** `$this`

~~~~~php
// Remove a single variable
$session->remove('firstName');

// Remove a single variable in a namespace
$session->remove('my_namespace', 'firstName');

// Remove all variables in a namespace
$session->remove('my_namespace', true);
~~~~~

---

## Namespaced values

Namespaced session values are stored under a namespace key, preventing collisions with
variables set by other modules or templates. The namespace can be a string or an object
(the class name is used automatically).

~~~~~php
// Set namespaced values (using $this as the namespace)
$session->setFor($this, 'cart', [123, 456]);
$session->setFor('MyModule', 'token', 'abc123');

// Get namespaced values
$cart  = $session->getFor($this, 'cart');
$token = $session->getFor('MyModule', 'token');

// Get with fallback (3.0.133+)
$cart = $session->getValFor($this, 'cart', []);

// Get all variables in a namespace
$all = $session->getAllFor($this);  // returns array
$all = $session->getFor($this, ''); // equivalent

// Remove one variable from a namespace
$session->removeFor($this, 'cart');

// Remove all variables in a namespace  
$session->removeAllFor($this);
~~~~~

| Method | Description |
|---|---|
| `setFor($ns, $key, $value)` | Set a namespaced session variable |
| `getFor($ns, $key)` | Get a namespaced session variable (blank `$key` returns all) |
| `getValFor($ns, $key, $val)` | Get namespaced value with fallback (3.0.133+) |
| `getAllFor($ns)` | Get all variables for a namespace as an array (3.0.141+) |
| `removeFor($ns, $key)` | Remove a single namespaced variable |
| `removeAllFor($ns)` | Remove all variables for a namespace |

---

## Iteration

`$session` implements `IteratorAggregate`, so you can iterate all non-namespaced
session variables with `foreach`:

~~~~~php
foreach($session as $key => $value) {
    echo "<li>$key: $value</li>";
}
~~~~~

---

## Redirects

### $session->redirect($url)

Redirect to a URL and halt execution. Pending notices are automatically queued and will
appear on the next request.

- **Arguments:** `redirect(string $url, bool|int $status = 301)`
- **Returns:** `never` — execution halts

~~~~~php
// Permanent redirect (301)
$session->redirect('/new-page/');

// Temporary redirect (302)
$session->redirect('/login/', false);
$session->redirect('/login/', 302);   // equivalent

// 303 See Other (forces GET after a POST)
$session->redirect('/thank-you/', 303);

// 307 Temporary Redirect (repeats the original request method)
$session->redirect('/retry/', 307);
~~~~~

**Status codes:**

| Value | Description |
|---|---|
| `true` or `301` | Permanent redirect (default) |
| `false` or `302` | Temporary redirect using GET |
| `303` | See Other — temporary, always switches to GET (3.0.166+) |
| `307` | Temporary redirect repeating the original method, e.g. POST (3.0.166+) |

---

### $session->location($url)

Convenience alias for a temporary redirect. Equivalent to `redirect($url, 302)`.

- **Arguments:** `location(string $url, int $status = 302)`
- **Returns:** `never`
- `$status` may be 302 (default), 303, or 307 (3.0.192+)

~~~~~php
$session->location('/next-step/');
~~~~~

---

## Authentication

### $session->login($name, $pass)

Log in a user by name and password.

- **Arguments:** `login(string|User $name, string $pass, bool $force = false)`
- **Returns:** `User|null` — the logged-in `User` on success, `null` on failure
- Automatically sets the current user on success.
- Specify `$force = true` to skip password check (or use `forceLogin()` instead).

~~~~~php
$user = $session->login('bob', 's3cr3t');

if($user) {
    $session->redirect('/dashboard/');
} else {
    echo "Invalid username or password.";
}
~~~~~

---

### $session->logout()

Log out the current user and clear all session variables.

- **Arguments:** `logout(bool $startNew = true)`
- **Returns:** `$this`
- Passing `false` for `$startNew` skips starting a new session after logout.

~~~~~php
$session->logout();
$session->redirect('/login/');
~~~~~

---

### $session->forceLogin($user)

Log in a user without requiring a password. Useful for admin tools, programmatic
user-switching, etc.

- **Arguments:** `forceLogin(string|User $user)`
- **Returns:** `User|null`

~~~~~php
$user = $session->forceLogin('bob');
~~~~~

---

## CSRF protection

The `$session->CSRF` property returns a `SessionCSRF` instance for cross-site request
forgery protection. Add a hidden token to any form, then verify it when the form is
submitted.

~~~~~php
// 1. Render a hidden CSRF token input inside <form>
echo $session->CSRF->renderInput();

// 2a. Check the token on submission (returns bool)
if($session->CSRF->hasValidToken()) {
    // process form
} else {
    throw new WireException("Invalid form submission");
}

// 2b. Alternatively, validate() throws WireCSRFException on failure
$session->CSRF->validate();
~~~~~

### Single-use tokens

Use these for one-time actions such as delete confirmations.

~~~~~php
// Generate a single-use token
$token = $session->CSRF->getSingleUseToken(); // ['id', 'name', 'value', 'time']

// Check it (automatically invalidates the token on first check)
if($session->CSRF->hasValidToken($token['id'])) {
    // valid — token is now invalidated
}
~~~~~

### SessionCSRF / $session->CSRF method reference

| Method | Description |
|---|---|
| `renderInput($id='')` | Render `<input type="hidden">` with token name and value |
| `hasValidToken($id='')` | Check if POST or AJAX request has a valid token; returns bool |
| `validate($id='')` | Like `hasValidToken()` but throws `WireCSRFException` on failure |
| `getToken($id='')` | Get token as array with `name`, `value`, and `time` keys |
| `getSingleUseToken($id='')` | Get a single-use token (invalidated on first `hasValidToken()` check) |
| `getTokenName($id='')` | Get the token name only |
| `getTokenValue($id='')` | Get the token value only |
| `resetToken($id='')` | Clear a single token |
| `resetAll()` | Clear all CSRF tokens |

*Note: ProcessWire InputfieldForm forms with the POST method automatically include SessionCSRF protection
unless disabled with the InputfieldForm `protectCSRF` property being set set to false.*
---

## Queued notices

Notices queued via `$session` persist across a redirect and appear on the next pageview
via the `$notices` system. This is most useful in admin modules and after `redirect()`.

~~~~~php
// Queue a notice before redirecting
$session->message("Changes saved.");
$session->warning("Some items were skipped.");
$session->error("Save failed.");
$session->redirect('./');

// After displaying queued notices, remove them to prevent re-display.
// ProcessWire’s admin themes do this automatically.
$session->removeNotices();
~~~~~

| Method | Description |
|---|---|
| `message(string $text, int $flags = 0)` | Queue a message notice for the next pageview |
| `warning(string $text, int $flags = 0)` | Queue a warning notice for the next pageview |
| `error(string $text, int $flags = 0)` | Queue an error notice for the next pageview |
| `removeNotices()` | Remove all queued notices (call after displaying them) |

*Note that the `removeNotices()` is called automatically by ProcessWire's admin themes after 
rendering notifications.*

---

## Session info

### $session->getIP()

Get the IP address of the current user.

- **Arguments:** `getIP(bool $int = false, bool|int $useClient = false, int $numParts = 0)`
- **Returns:** `string|int`

~~~~~php
$ip = $session->getIP();            // "1.2.3.4" or IPv6 string
$n  = $session->getIP(true);        // as integer (crc32 for IPv6)

// Use X-Forwarded-For / HTTP_CLIENT_IP (useful behind a proxy)
$ip = $session->getIP(false, true);

// Partial IP for privacy-preserving logging (3.0.258+)
$ip = $session->getIP(false, false, 3);  // "1.2.3" (first 3 octets)
$ip = $session->getIP(false, false, 2);  // "1.2"
~~~~~

- If running behind a load balancer or reverse proxy, use `$useClient = true` to read
  the forwarded IP. Be aware this header can be spoofed.
- IP-based session fingerprinting is unreliable on mobile networks (carrier-grade NAT)
  and cellular connections where IPs change between towers.

---

### $session->hasCookie()

Check whether a session cookie is present.

- **Arguments:** `hasCookie(bool $checkLogin = false)`
- **Returns:** `bool`
- Pass `true` to check for the challenge cookie instead (indicates login may be active).

~~~~~php
if($session->hasCookie()) {
    // session cookie is present
}
~~~~~

---

### $session->hasLoginCookie()

Check whether a login challenge cookie is present, indicating the user was logged in
at some point. Does not verify that the session is currently valid.

- **Returns:** `bool`
- Available since 3.0.175. Equivalent to `hasCookie(true)`.

~~~~~php
if($session->hasLoginCookie()) {
    // likely a logged-in or recently logged-in user
}
~~~~~

---

### $session->getHistory()

Get the session history (previous URLs visited). Requires `$config->sessionHistory > 0`.
Each entry is an array with `url`, `page` (ID), and `time` (unix timestamp) keys.

- **Returns:** `array`

~~~~~php
$history = $session->getHistory();
foreach($history as $entry) {
    echo $entry['time'] . ': ' . $entry['url'] . "\n";
}
~~~~~

---

## Advanced

### $session->close()

Close the session early, releasing the session lock. Useful for long-running requests
(sitemap generation, exports, etc.) that don't need to read or write session data, so
the user can navigate other pages concurrently.

~~~~~php
$session->close();
// ... long-running render follows
~~~~~

---

## Hookable methods

Hook before or after any of these methods using
`$wire->addHookBefore('Session::methodName', …)` or `addHookAfter(…)`.

| Method | When to hook |
|---|---|
| `login($name, $pass, $force)` | Before/after login attempt |
| `logout($startNew)` | Before/after logout |
| `redirect($url, $status)` | Before a redirect is issued |
| `allowLogin($name, $user)` | Return `false` to block a login; filter by role, IP, etc. |
| `allowLoginAttempt($name)` | Return `false` to block before user object is loaded |
| `authenticate(User $user, $pass)` | Return bool to override password verification |
| `loginSuccess(User $user)` | After a successful login |
| `loginFailure($name, $reason)` | After a failed login attempt |
| `logoutSuccess(User $user)` | After a successful logout |

~~~~~php
// Example: block logins from a specific IP range
$wire->addHookAfter('Session::allowLogin', function(HookEvent $e) {
    if(!$e->return) return; // login already disallowed
    $session = $e->object; // Session is the hooked object
    $ip = $session->getIP();
    if(strpos($ip, '10.') === 0) {
        // disallow login for IPs starting with '10.'
        $e->return = false;
    }
});

// Example: log all login attempts
$wire->addHookAfter('Session::loginFailure', function(HookEvent $e) {
    $name   = $e->arguments(0);
    $reason = $e->arguments(1);
    $e->wire->log->save('login-failures', "$name: $reason");
});
~~~~~

---

## Notes

- Session variables set via `$session` are stored in a dedicated namespace within
  `$_SESSION` — they do not appear in a plain `$_SESSION` read and vice versa.
- Values must be serializable PHP types (strings, numbers, arrays). Objects are not
  recommended unless they are fully serializable.
- To persist non-user data across requests independently of the session lifecycle, 
  and independent of any specific sessions, use `$page->meta()` (page-scoped) or `$cache` (global).
- Source file: `wire/core/Session/Session.php`.
