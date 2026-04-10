# FieldtypePassword

Stores a hashed and salted password. The value is always a `Password` object — the raw hash is
never exposed. Setting a new password is done by assigning a plain-text string.

---

## Value type

`Password` object. The `Password` class provides:
- `$field->pass` (write-only) — set a new plain-text password
- `$field->hash` — the stored hash (read-only)
- `$field->matches('plain')` — verify a plain-text password against the stored hash

---

## Getting and setting values

```php
// Set a new password
$user->pass = 'NewSecurePassword123!';
$user->save('pass');

// Verify a password
if($user->pass->matches('enteredPassword')) {
    // correct password
}

// Check if a password has been set
if($user->pass->hash) {
    // password is set
}
```

Do not read `$user->pass` and re-assign it — doing so may corrupt or double-hash the stored value.

---

## Selectors

Password fields cannot be used in selectors. The hash is not searchable and the plain-text
value is never stored.

---

## Notes

- The password is hashed using bcrypt. The plain-text value is never stored.
- No compatible fieldtypes (password cannot be converted to another type).
- Database columns: `char(40)` for hash, `char(32)` for salt.
