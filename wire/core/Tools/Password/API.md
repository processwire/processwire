# Password

Represents a hashed password for the ProcessWire `FieldtypePassword` field. Each `Password`
instance stores a cryptographic salt and hash, provides methods to compare and set
passwords, and supports Blowfish (bcrypt) hashing when the PHP environment allows.

A `Password` object is typically obtained from a Page field using `FieldtypePassword`:

```php
// Get the Password object from a page field
$password = $page->get('pass');        // returns Password|string
$password = $page->getUnformatted('pass'); // returns Password

// Check if it matches a given plain-text password
if($password->matches('secret123')) {
    echo "Matches!";
}
```

You can also create standalone instances for custom password storage or testing:

```php
// Create a new Password object and set a password
$password = new Password();
$password->pass = 'mySecurePassword'; // triggers hashing
```

Password objects are used by the `$users` API — each `User` page has a `pass` field
managed by `FieldtypePassword`, and the `$session->login()` and `$users->get()` methods
use `Password::matches()` internally for authentication.

**Extends:** `Wire`

---

## Properties

Password properties are accessed directly as object properties. The `pass` property
is write-only (sets a new password and re-hashes), while `salt` and `hash` are
readable representations of the stored password data.

| Property | Type     | Access | Description |
|----------|----------|--------|-------------|
| `pass`   | `string` | write  | Set a new plain-text password. Triggers salt generation and hashing via `setPass()`. |
| `salt`   | `string` | read   | The cryptographic salt used for hashing. Blowfish salts are 29 characters and begin with `$2y$`, `$2a$`, or `$2x$`. |
| `hash`   | `string` | read   | The computed password hash (without the salt prefix for Blowfish). |

**Note:** The `__get()` and `__set()` magic methods that back these properties are
tagged `#pw-internal`. Access the properties directly as shown above — do not call
the magic methods.

---

## Setting and comparing passwords

### Setting a password: `$password->pass = '...'`

Assign a plain-text password string to the `pass` property. This internally calls the
hookable `setPass()` method, which generates a salt when needed, hashes the password
with that salt, and stores the result. If the new password matches the existing stored
hash when checked with the existing salt, no change is recorded:

```php
$password = $page->pass;
$password->pass = 'newSecret456';       // hash is computed and stored
$password->pass = 'newSecret456';       // no change — matches existing stored hash
```

If the property is set to an empty string, the operation is silently ignored and the
existing password remains unchanged.

### Comparing a password: `matches($pass)`

Check whether a plain-text password matches the stored hash:

```php
$password = $page->pass;

if($password->matches('attemptedPassword')) {
    echo "Password is correct";
} else {
    echo "Wrong password";
}
```

`matches()` hashes the given password with the stored salt and compares the result
using `hash_equals()` (timing-attack-safe comparison) when available, falling back
to strict string comparison on older PHP versions.

When the system supports Blowfish but the stored password was hashed with an older
algorithm, `matches()` returns `true` (if the password matches) and sets a notice
message prompting the user to change their password to upgrade to Blowfish hashing.

---

## Hashing information

### `isBlowfish($str = '')`

Check whether a given salt string (or the stored salt, if no argument is passed) uses
the Blowfish algorithm. Blowfish salts begin with `$2a$`, `$2x$`, or `$2y$`:

```php
// Check the stored salt
if($password->isBlowfish()) {
    echo "Password uses Blowfish hashing";
}

// Check an arbitrary string
if($password->isBlowfish($someOtherSalt)) {
    // ...
}
```

### `supportsBlowfish()`

Check whether the current PHP environment supports Blowfish hashing. Returns `true`
when PHP is version 5.3.0 or higher and the `CRYPT_BLOWFISH` constant is defined:

```php
if($password->supportsBlowfish()) {
    echo "Blowfish is available";
}
```

On any modern PHP installation (7.0+), this method returns `true`.

---

## Random value generation

The `Password` class provides several methods for generating random strings. Most of
these methods delegate to a `WireRandom` instance. Some older methods are retained for
backward compatibility but are deprecated in favor of `WireRandom` methods.

### `randomPass(array $options = [])`

Generate a cryptographically secure random password. Delegates to `WireRandom::pass()`.
Options control length, character sets, and other parameters:

```php
// Generate a 16-character random password
$pass = $password->randomPass(['length' => 16]);

// Use only letters and digits (default includes symbols)
$pass = $password->randomPass(['length' => 12, 'alnum' => true]);
```

See [[WireRandom]] for the full list of options.

### `randomBase64String($requiredLength = 22, $options = [])`

Generate a cryptographically secure random base64 string of a specified length.
Delegates to `WireRandom::base64()`:

```php
// Generate a 44-character base64 string for a custom salt
$base64 = $password->randomBase64String(44);
```

For fast (non-cryptographic) generation, pass the `fast` option:

```php
$base64 = $password->randomBase64String(22, ['fast' => true]);
```

See [[WireRandom]] for all options.

### Deprecated random methods

The following methods are retained for backward compatibility but are deprecated.
Use the corresponding methods on [[WireRandom]] instead:

| Method              | Replacement                     | Since |
|---------------------|---------------------------------|-------|
| `randomAlpha()`     | `WireRandom::alpha()`           | 3.0.109 |
| `randomAlnum()`     | `WireRandom::alphanumeric()`    | 3.0.109 |
| `randomLetters()`   | `WireRandom::alpha()`           | 3.0.109 |
| `randomDigits()`    | `WireRandom::numeric()`         | 3.0.109 |

```php
// Deprecated usage — prefer wire('random')->alpha(10) instead
$letters = $password->randomLetters(10);  // still works, but deprecated
```

---

## Hooks

### `Password::setPass($value)`

Called when a new password is assigned via `$password->pass = '...'`. Receives the
plain-text password string and generates the salt and hash.

```php
/**
 * Hook before a password is hashed.
 *
 * @param HookEvent $event
 */
$wire->addHookBefore('Password::setPass', function(HookEvent $event) {
    $password = $event->object;  /** @var Password $password */
    $pass = $event->arguments(0); // plain-text password string

    // Enforce a minimum password length
    if(strlen($pass) < 8) {
        $event->replace = true;
        throw new WireException('Password must be at least 8 characters');
    }
});
```

You can also hook after to react to password changes:

```php
$wire->addHookAfter('Password::setPass', function(HookEvent $event) {
    $password = $event->object;
    // $password->salt and $password->hash now contain the new values
    wire('log')->save('passwords', "Password updated for user");
});
```

---

## String conversion

When cast to a string, a `Password` object returns the stored hash:

```php
echo (string) $password;       // outputs the hash string
echo $password->__toString();  // equivalent
```

The `__toString()` method is useful when you need the raw hash for debugging or
logging, but it should never be exposed to users or transmitted.

---

## Notes

- **Source file:** `wire/core/Tools/Password/Password.php`
- **Extends:** `Wire` — inherits hook support and Wire instance access via `$this->wire()`
- **Used by:** `FieldtypePassword`, `InputfieldPassword`, `Session::login()`, `$users` authentication
- **Salt format:** When using Blowfish, the salt is 29 characters with the format `$2y$11$<22-base64-chars>$`. The cost parameter (11 in the example) controls the hashing complexity. Older installations may use `$2a$` or `$2x$` prefixes instead of `$2y$`.
- **Hash algorithm:** When Blowfish is not available, the hash type is determined by `$config->userAuthHashType` (e.g. `sha256`). If neither Blowfish nor a configured hash type is available, the system falls back to MD5 for backward compatibility with very old installations.
- **Change tracking:** `Password` extends `Wire` and supports change tracking. After setting a new password, `$password->isChanged('pass')` returns `true` until `resetTrackChanges()` is called. This is used by `FieldtypePassword::sanitizeValue()` to mark the parent page's field as changed.
- **Security:** `matches()` uses `hash_equals()` (when available) for timing-attack-safe string comparison.
- **Related classes:** [[FieldtypePassword]], [[InputfieldPassword]], [[WireRandom]], [[User]], [[Session]]


