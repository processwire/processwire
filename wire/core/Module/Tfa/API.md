# Tfa

Base class for ProcessWire's two-factor authentication (TFA) system. Concrete
TFA modules — such as **TfaEmail** (code sent via email) and **TfaTotp** (time-based
one-time passwords) — extend this class and implement the module-specific
validation and configuration logic.

The base class handles the login flow: credential verification, session
management, the authentication-code form, browser "remember me" tracking,
automatic TFA enforcement, and integration into the user profile editor.

```php
$tfa = $modules->get('Tfa');

if($tfa->success()) {
    // TFA completed — user is now logged in
    $session->redirect('/after/login/url/');

} elseif($tfa->active()) {
    // TFA is in progress — render the code entry form
    echo $tfa->render();

} elseif($input->post('submit_login')) {
    // User submitted login form — start TFA if applicable
    $name = $input->post('name');
    $pass = $input->post('pass');
    $tfa->start($name, $pass);
    // If TFA is not active for this user, $tfa->start() returns true
    // and you proceed with your normal login here

} else {
    // Render your login form
}
```

Extends [[WireData]]. Implements `Module` and `ConfigurableModule`.

## Login flow

The TFA login flow follows a sequence of redirect-based steps:

1. **User submits credentials** → call `start($name, $pass)`. The method
   authenticates credentials via `Session::authenticate()`, then checks whether
   TFA is enabled for the user. If it is, `start()` redirects to the code-entry
   step. If not, `start()` returns `true` and the caller proceeds with normal login.

2. **Code entry** → `active()` returns `true` when the URL contains the `tfa` GET
   parameter matching the session key. The caller renders the code form via
   `render()`.

3. **Code submission** → `success()` returns `true` when the user has submitted
   a valid code and is now logged in. If the code is invalid, expired, or max
   attempts exceeded, `process()` will redirect back to the code entry step.

4. **Completed** → `success()` returns `true`; caller redirects to the post-login
   destination.

```
start() ──► (TFA enabled) ──► redirect to code entry
         └─► (TFA not enabled) ──► returns true, normal login proceeds

active()? ──► render() ──► user enters code
success()? ──► true: logged in, redirect to destination
            └─► false: code invalid/expired, redirect back to code entry
```

## Constants

| Constant        | Value       | Description                                                        |
|-----------------|-------------|--------------------------------------------------------------------|
| `userFieldName` | `'tfa_type'`| Name of the FieldtypeModule field added to user templates on install |

## Properties

All properties are read/write via `$tfa->set('key', $value)`, `$tfa->key = $value`,
or `$tfa->setArray([...])`. The module configuration screen in the admin provides
defaults; runtime overrides take priority.

### Settings

| Property              | Type    | Default                                        | Description |
|-----------------------|---------|------------------------------------------------|-------------|
| `codeLength`          | `int`   | `6`                                            | Required length for authentication codes (set by subclass) |
| `codeExpire`          | `int`   | `180`                                          | Seconds before a pending code entry times out |
| `codeType`            | `int`   | `0`                                            | Code type (subclass-specific; see constants) |
| `startUrl`            | `string`| `'./'`                                         | Base URL for the login/TFA page |
| `rememberDays`        | `int`   | `0`                                            | Days to remember a browser, `0` to disable, `-1` for no limit |
| `rememberFingerprints`| `array` | `['agentVL','accept','scheme','host']`         | Browser fingerprint attributes to use for "remember me" |
| `autoType`            | `string`| `''`                                           | Force a TFA module name for users who haven't enabled one |
| `autoRoleIDs`         | `array` | `[]`                                           | Role IDs to apply `autoType` to (empty = all roles) |
| `showCancel`          | `bool`  | `true`                                         | Show a cancel link below the code entry form |
| `cancelMarkup`        | `string`| `"<p><a href='{url}'>{label}</a></p>"`         | Markup template for cancel link (`{url}` and `{label}` placeholders) |
| `formAttrs`          | `array` | `['id'=>'ProcessLoginForm','class'=>'pw-tfa']`| `<form>` element attributes for the code entry form |
| `inputAttrs`         | `array` | `['id'=>'login_name','autofocus'=>'autofocus']`| `<input>` element attributes for the code field |
| `submitAttrs`        | `array` | `['id'=>'Inputfield_login_submit']`            | Submit button attributes |

### Text labels (translatable)

| Property                 | Default | Description |
|--------------------------|---------|-------------|
| `cancelLabel`            | `'Cancel'` | Cancel link text |
| `configureLabel`         | `'Please configure'` | Notice shown when TFA type selected but not yet configured |
| `enabledLabel`           | `'ENABLED'` | Badge appended to the TFA fieldset when already enabled |
| `enabledDescLabel`       | | Description when TFA is enabled, explaining how to disable/change |
| `expiredCodeLabel`       | `'Expired code'` | Error for an expired authentication code |
| `fieldTfaTypeLabel`      | `'2-factor authentication type'` | Label for the `tfa_type` field in the user profile |
| `fieldTfaTypeDescLabel`  | | Description for the `tfa_type` field |
| `inputLabel`             | `'Authentication Code'` | Label for the code input field |
| `invalidCodeLabel`       | `'Invalid code'` | Error for an incorrect code |
| `maxAttemptsLabel`       | `'Max attempts reached'` | Error when 3 failed code attempts exceeded |
| `rememberLabel`          | `'Remember this computer?'` | Label for the remember-me checkbox |
| `rememberSuccessLabel`   | | Success message (uses `%d` for days) |
| `rememberSkipLabel`      | | Notice in debug mode when code was skipped |
| `rememberClearLabel`     | | Label for clearing remembered browsers |
| `rememberClearedLabel`   | `'Cleared remembered browsers'` | Message after clearing |
| `sendCodeErrorLabel`     | | Error when `startUser()` fails |
| `submitLabel`            | `''` | Override label for submit button (empty = default) |
| `timeLimitLabel`         | `'Time limit reached'` | Error when `codeExpire` timeout hit |

## Methods

### Flow control

#### start($name, $pass)

Begins the TFA process. Authenticates the given name/password, then checks
whether TFA is enabled for the user. If it is, redirects to the code-entry step.
If the user isn't found, can't log in, or credentials fail, returns `false`. If
TFA is not enabled (or the browser is remembered), returns `true` — the caller
should then proceed with normal login.

```php
$tfa->start($input->post('name'), $input->post('pass'));
// If TFA is active, a redirect happens here and the line below is never reached
```

#### active()

Returns `true` when a TFA process is in progress — i.e., the current URL contains
the `tfa` GET parameter matching the session key. When this returns `true`, render
the code form via `render()`.

```php
if($tfa->active()) {
    echo $tfa->render();
}
```

#### render()

Builds and renders the authentication code entry form as HTML. Delegates to the
specific TFA module instance if called on the base `Tfa` class.

```php
echo $tfa->render();
```

#### success()

Returns `true` when TFA has completed and the user is logged in. Calls
`process()` internally, which may redirect. Check this *before* checking `active()`.

```php
if($tfa->success()) {
    $session->redirect('/dashboard/');
}
```

#### process()

Processes a submitted authentication code. Validates the code via
`isValidUserCode()`, enforces the max-attempts limit (3) and code expiry
(`codeExpire`), and performs a forced login when the code is valid. Redirects
back to the code entry step on failure. Returns the `User` object on success or
`false` otherwise.

```php
$user = $tfa->process();
```

### Methods to implement in subclasses

Concrete TFA modules must implement these methods:

#### isValidUserCode($user, $code, $settings)

Validates an authentication code submitted by the user. **Modules must
implement this method** — the base class throws `WireException` if called directly.

Returns `true` if valid, `false` if invalid, or `0` (int) if the code was valid but
has expired.

```php
// Example from a hypothetical TfaEmail module
public function isValidUserCode(User $user, $code, array $settings) {
    $storedCode = $this->sessionGet('code');
    if(empty($storedCode)) return false;
    if($code !== $storedCode) return false;
    return true;
}
```

#### startUser($user, $settings)

Generates and/or sends the authentication code to the user, then calls
`parent::startUser($user)` to save session state. Returns `true` on success.

For modules that generate their own codes (like TfaEmail): create the code, save
it to session, send it via the appropriate channel, call parent, return `true`.

For modules that validate but don't send codes (like TfaTotp): the default
implementation suffices — it saves session state and returns `true`.

```php
// TfaEmail-style implementation
public function startUser(User $user, array $settings) {
    $code = sprintf('%06d', random_int(0, 999999));
    $this->sessionSet('code', $code);
    // Send $code to user via email...
    return parent::startUser($user);
}
```

#### enabledForUser($user, $settings)

Returns `true` if TFA is enabled for the given user. The default checks the
`enabled` key in `$settings`. Subclasses may override for custom logic.

#### getUserSettingsInputfields($user, $fieldset, $settings)

Provides Inputfields for a user to configure and confirm TFA from their user
profile. Called when the user has selected a TFA type but not yet configured it.
Subclasses add their fields to the given `$fieldset` (an [[InputfieldWrapper]]).

```php
public function ___getUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings) {
    parent::___getUserSettingsInputfields($user, $fieldset, $settings);
    $f = $this->wire()->modules->get('InputfieldText');
    $f->attr('name', 'secret');
    $f->label = 'Secret Key';
    $fieldset->add($f);
}
```

#### processUserSettingsInputfields($user, $fieldset, $settings, $settingsPrev)

Called after the user config form is processed but before settings are saved.
Subclasses can modify `$settings` and return the updated array.

```php
public function ___processUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings, $settingsPrev) {
    $settings = parent::___processUserSettingsInputfields($user, $fieldset, $settings, $settingsPrev);
    $settings['enabled'] = true; // enable after initial configuration
    return $settings;
}
```

#### processUserEnabledInputfields($user, $fieldset, $settings, $settingsPrev)

Called after the TFA-enabled user's form is processed. The base implementation
handles the "clear remembered browsers" checkbox.

### Module identification

#### getTfaTypeName()

Returns a short name for the TFA type (e.g., `'Email'` for `TfaEmail`). Subclasses
should not call `parent::getTfaTypeName()`.

#### getTfaTypeTitle()

Returns the longer, translatable title — derived from the module's registered
title. Subclasses should not call `parent::getTfaTypeTitle()`.

#### getTfaTypeSummary()

Returns a translatable summary — derived from the module's registered summary.

### User settings

#### getUserSettings($user)

Retrieves per-user TFA settings as an associative array from the database. Throws
`WireException` if called on the base `Tfa` class instead of a concrete module.

```php
$settings = $tfaModule->getUserSettings($user);
if($settings['enabled']) { /* user has TFA enabled */ }
```

#### saveUserSettings($user, $settings)

Saves per-user TFA settings to the database. Returns `true` on success.
Throws `WireException` if called on the base `Tfa` class.

```php
$tfaModule->saveUserSettings($user, ['enabled' => true, 'secret' => 'ABC123']);
```

#### getDefaultUserSettings($user)

Returns the default settings array (`protected`, used internally). Subclasses
override to add their own default settings.

#### getUser()

Resolves the current user context — either the logged-in user, or the user being
edited in the profile (when in a Process module like ProcessUser or ProcessProfile).

#### static getUserTfaType($user, $getInstance = false)

Static utility to check whether a user has TFA enabled. Returns the TFA module
name (string), a `Tfa` instance (if `$getInstance` is `true`), or `false` if TFA is
not enabled. Use this instead of accessing `$user->tfa_type` directly — it avoids
unnecessarily loading the TFA module and accounts for unconfigured selections.

```php
$tfaType = Tfa::getUserTfaType($user);
if($tfaType) {
    echo "TFA enabled via: $tfaType";
}
```

### Auto-enable

#### autoEnableSupported($user = null)

Returns `true` if this TFA module supports being enabled for a user without their
input (e.g., TfaEmail if the email is already known). TfaTotp returns `false` because
it requires manual setup.

#### autoEnableUser($user, $settings = [])

Enables this TFA module for the given user automatically. Throws `WireException` on
all error conditions.

```php
if($module->autoEnableSupported($user)) {
    $module->autoEnableUser($user);
}
```

### Install / uninstall

#### install()

Creates the `tfa_type` field (FieldtypeModule, system flag), adds a `settings`
text column to its database table, adds the field to all user templates, and
registers it as an editable profile field in ProcessProfile. Subclasses with their
own `install()` must call `parent::___install()`.

#### uninstall()

Removes the `tfa_type` field and its assets only when no other TFA modules remain
installed. Subclasses with their own `uninstall()` must call `parent::___uninstall()`.

## Hooks

The Tfa system hooks into [[InputfieldForm]] to inject TFA configuration fields
into the user profile editor automatically.

| Hook                                | When                          | Purpose |
|-------------------------------------|-------------------------------|---------|
| `InputfieldForm::render` (before)   | Form with `tfa_type` field is rendered | Injects TFA config/enabled fieldset, updates option labels |
| `InputfieldForm::processInput` (before) | Form with `tfa_type` field is processed | Inserts TFA settings fieldset for processing |
| `InputfieldForm::processInput` (after)  | After processing above form | Saves TFA settings from submitted fields |

### Hookable methods

The following methods are hookable (prefixed `___` in source):

| Method | Description |
|--------|-------------|
| `start($name, $pass)` | TFA login start |
| `buildAuthCodeForm()` | Build the code entry form |
| `render()` | Render the code entry form |
| `process()` | Process submitted code |
| `getUserSettingsInputfields(...)` | Add config fields for user profile |
| `getUserEnabledInputfields(...)` | Add fields for already-enabled user |
| `processUserSettingsInputfields(...)` | Process config submission |
| `processUserEnabledInputfields(...)` | Process enabled-user submission |
| `install()` / `uninstall()` | Setup/teardown |

```php
$wire->addHookAfter('Tfa::process', function(HookEvent $event) {
    $tfa = $event->object;  /** @var Tfa $tfa */
    $user = $event->return; /** @var User|bool */
    if($user instanceof User) {
        // Log successful TFA login
        $log->save('tfa-log', "TFA login: $user->name");
    }
});
```

## "Remember this browser"

When `rememberDays` is greater than `0`, the code entry form includes a
"Remember this computer?" checkbox. If checked, the browser is fingerprinted and
stored so that subsequent logins within the configured days skip the code entry
step.

Fingerprints are derived from configurable browser characteristics: `agentVL`
(versionless user agent), `accept` (HTTP Accept header), `scheme` (http/https),
`host`, `ip`, and `fwip` (forwarded IP). The combination is salted and hashed,
providing a secondary security layer on top of a random cookie value.

Up to 10 browsers are remembered per user. Cookies are HttpOnly and Secure
(when site uses HTTPS). Users can clear remembered browsers from their profile.

```php
$tfa->rememberDays = 30;
$tfa->rememberFingerprints = ['agentVL', 'accept', 'scheme', 'host', 'ip'];
```

## Writing a TFA module

To create a custom TFA module:

1. **Extend `Tfa`** and implement `Module`, `ConfigurableModule`.
2. **Implement `isValidUserCode()`** — validate the submitted code.
3. **Override `startUser()`** if your module generates/sends codes.
4. **Override `getUserSettingsInputfields()`** to collect initial configuration.
5. **Override `getDefaultUserSettings()`** to add module-specific defaults.
6. **Call `parent::___install()` and `parent::___uninstall()`** if overriding.

```php
class TfaMyModule extends Tfa implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return [
            'title' => 'My TFA Module',
            'summary' => 'Custom two-factor authentication.',
            'version' => 1,
            'requires' => 'Tfa',
        ];
    }

    public function isValidUserCode(User $user, $code, array $settings) {
        $stored = $this->sessionGet('code');
        return $code && $stored && $code === $stored;
    }

    public function startUser(User $user, array $settings) {
        $code = sprintf('%06d', random_int(0, 999999));
        $this->sessionSet('code', $code);
        // Send $code to user...
        return parent::startUser($user);
    }
}
```

## Notes

- Always call `exit` after rendering the code form in a template file if no further
  output is expected.
- The `tfa` GET parameter name and the session namespace are both `'tfa'` by
  default.
- Max failed code attempts is 3; after which the user must start over.
- The `codeExpire` setting (default 180 seconds = 3 minutes) limits how long a
  pending code entry remains valid.
- Code reuse prevention: `last_code` is stored in user settings and compared
  against submitted codes to prevent replay attacks.
- The base `Tfa` class itself is installed as a module. Descending modules
  (`TfaEmail`, `TfaTotp`, etc.) are selected per-user via the `tfa_type` field.
- **Source file:** `wire/core/Module/Tfa/Tfa.php`
- **Extends:** [[WireData]] — all WireData property access (`get`, `set`, `setArray`)
  is available.
- The companion class `RememberTfa` is defined in the same file and manages the
  "remember this browser" feature. It is accessed internally via
  `$tfa->remember($user, $settings)`.

*Submitted by: GLM-5.2*

