# Notices / $notices

`$notices` is the API variable that holds all notices aka notifications (messages, warnings, and errors)
generated during a request. Notices are primarily used in the admin to display feedback
to the user, but can also be iterated and rendered in front-end templates.

Every class that extends `Wire` (modules, Fieldtypes, Inputfields, Processes, and more)
inherits `message()`, `warning()`, and `error()` methods that add notices to `$notices`
automatically.

`$notices` is accessible in templates as `$notices` or `wire()->notices`; and in modules
as `$this->wire()->notices`.

---

## Adding notices

Notices are most commonly added from within a `Wire`-derived class via the inherited
`message()`, `warning()`, and `error()` methods. These are available on every module,
Fieldtype, Inputfield, Process, and other Wire-derived class.

~~~~~php
// In a module or any Wire subclass:

$this->message("Item was saved successfully");
$this->warning("Setting X is deprecated, use Y instead");
$this->error("Could not connect to the API");
~~~~~

Each method accepts an optional `$flags` argument — see the [Notice flags](#notice-flags)
section below.

~~~~~php
// Also log the notice to messages.txt / errors.txt
$this->message("Item saved", Notice::log);
$this->error("Something failed", Notice::log);

// Only log, don't display
$this->message("Background task ran", Notice::logOnly);

// Only show in debug mode
$this->warning("Slow query detected", Notice::debug);

// Allow HTML markup in the notice text
$this->message("See the <a href='/docs/'>docs</a>", Notice::allowMarkup);

// Combine flags with bitmask OR or a space-separated string (3.0.149+)
$this->message("Done", Notice::log | Notice::noGroup);
$this->message("Done", "log noGroup");

// Boolean true is a shortcut for Notice::log
$this->message("Item saved", true);
~~~~~

Notices can also be added directly to the `$notices` API variable from a template using
the same methods, since `$notices` itself extends `Wire`:

~~~~~php
$notices->message("Hello from a template");
$notices->error("Something went wrong");
~~~~~

Or by constructing a `Notice` object directly:

~~~~~php
$notices->add(new NoticeMessage("Hello world"));
$notices->add(new NoticeError("Something failed", Notice::log | Notice::noGroup));
~~~~~

### Passing arrays and objects

Notice methods accept arrays and objects in place of a string, which is useful for
debugging. The value is formatted via `Debug::toStr()` and `Notice::allowMarkup` is
set automatically.

~~~~~php
// Display a formatted dump of any array or object
$this->message($someArray);
$this->message($somePage);
~~~~~

Pass a single-key associative array to add a label before the formatted value. The key
becomes a bold `"Label: value"` prefix in the notice:

~~~~~php
$this->message(['Returned pages' => $pages->find('template=blog')]);
$this->message(['Order data' => $orderArray]);
~~~~~

In debug mode (`$config->debug = true`), the label is used as the notice's class
attribution (shown as the source) rather than a visible prefix.

---

## Notice flags

Flags control how and when a notice is displayed or logged. They can be combined as a
bitmask using `|`, or specified as a space-separated string of flag names (3.0.149+).

~~~~~php
// These are all equivalent:
$this->message("Text", Notice::log | Notice::noGroup);
$this->message("Text", "log noGroup");
$this->message("Text", Notice::log | Notice::noGroup | Notice::superuser);
$this->message("Text", "log noGroup superuser");
~~~~~

| Flag                    | String name       | Description                                               |
|-------------------------|-------------------|-----------------------------------------------------------|
| `Notice::log`           | `log`             | Also write this notice to the corresponding log file      |
| `Notice::logOnly`       | `logOnly`         | Write to log only — do not display to the user            |
| `Notice::debug`         | `debug`           | Only show when `$config->debug` is `true`                 |
| `Notice::allowMarkup`   | `allowMarkup`     | Allow HTML in the notice text (not entity-encoded)        |
| `Notice::allowMarkdown` | `allowMarkdown`   | Parse basic inline Markdown and bracket markup            |
| `Notice::noGroup`       | `noGroup`         | Do not group/collapse with other notices of the same type |
| `Notice::allowDuplicate`| `allowDuplicate`  | Show duplicate notices separately rather than collapsing  |
| `Notice::prepend`       | `prepend`         | Add to the top of the notice list rather than the bottom  |
| `Notice::anonymous`     | `anonymous`       | Do not associate notice with the calling class            |
| `Notice::login`         | `login`           | Only show to logged-in users                              |
| `Notice::admin`         | `admin`           | Only show when the current page is in the admin           |
| `Notice::superuser`     | `superuser`       | Only show to superusers                                   |

Aliases (same value, different name):

| Alias                   | Same as                  |
|-------------------------|--------------------------|
| `Notice::markup`        | `Notice::allowMarkup`    |
| `Notice::markdown`      | `Notice::allowMarkdown`  |
| `Notice::separate`      | `Notice::noGroup`        |
| `Notice::duplicate`     | `Notice::allowDuplicate` |

---

## Reading and iterating notices

`$notices` extends `WireArray`, so it can be iterated directly. Each item is a `Notice`
object — specifically a `NoticeMessage`, `NoticeWarning`, or `NoticeError` instance.

~~~~~php
foreach($notices as $notice) {
    // skip debug notices unless debug mode is on...
    if($notice->flags & Notice::debug && !$config->debug) continue;
    // ...or use if(!$notice->viewable()) continue;

    // entity-encode unless markup is allowed
    $text = ($notice->flags & Notice::allowMarkup)
        ? $notice->text
        : $sanitizer->entities($notice->text);

    if($notice instanceof NoticeError) {
        echo "<p class='error'>$text</p>";
    } else if($notice instanceof NoticeWarning) {
        echo "<p class='warning'>$text</p>";
    } else {
        echo "<p class='message'>$text</p>";
    }
}
~~~~~

### $notices->hasErrors()

Returns `true` if any `NoticeError` items are present.

~~~~~php
if($notices->hasErrors()) {
    echo "There were errors — please review.";
}
~~~~~

### $notices->hasWarnings()

Returns `true` if any `NoticeWarning` items are present.

### $notices->getVisible()

Returns a new `Notices` object containing only notices that are visible to the current
user (respects `login`, `admin`, `superuser`, `debug`, and `logOnly` flags).
Available since 3.0.252.

~~~~~php
$visible = $notices->getVisible();
foreach($visible as $notice) {
    echo $notice->text . "\n";
}
~~~~~

---

### $notices->add($notice)

Add a `NoticeMessage`, `NoticeWarning`, or `NoticeError` object directly.
Duplicate notices are collapsed by default and the retained notice's `qty` property is
incremented. Use `Notice::allowDuplicate` to keep duplicate notices as separate items.

- **Arguments:** `add(Notice $notice)`
- **Returns:** `$notices`

~~~~~php
$notices->add(new NoticeMessage("Saved"));
$notices->add(new NoticeWarning("Review this setting", "noGroup"));
$notices->add(new NoticeError("Something failed", Notice::allowDuplicate));
~~~~~

---

## Rendering notices

### $notices->render()

Render notices as HTML using the admin theme's standard notice markup. Useful for
displaying notices outside of the normal admin flow. Available since 3.0.254.

- **Returns:** `string` — HTML output
- This is a hookable method (`___render()`).

~~~~~php
echo $notices->render();
~~~~~

### $notices->renderText()

Render notices as plain text. If outputting inside HTML (e.g. a `<pre>`), pass the
result through `$sanitizer->entities()` first. For CLI the return value can be 
output directly. Available since 3.0.254.

- **Returns:** `string`
- This is a hookable method (`___renderText()`).

~~~~~php
$text = $notices->renderText();
echo '<pre>' . $sanitizer->entities($text) . '</pre>';
~~~~~

---

## Notice properties

Each `Notice` object exposes the following properties:

| Property     | Type     | Description |
|--------------|----------|---|
| `text`       | `string` | The notice message text |
| `flags`      | `int`    | Bitmask of active `Notice::*` flags |
| `flagsStr`   | `string` | Active flags as a space-separated string (read-only) |
| `flagsArray` | `array`  | Active flags as `[int => name]` array (read-only) |
| `class`      | `string` | Class name of the object that added the notice |
| `icon`       | `string` | Icon name (FontAwesome name without `fa-` prefix) |
| `timestamp`  | `int`    | Unix timestamp of when the notice was created |
| `qty`        | `int`    | Number of times this notice was added (duplicates collapsed) |
| `idStr`      | `string` | Stable ID string derived from type, flags, class, and text |

### Notice flag helpers

These methods are available on each `Notice` object.

| Method | Description                                                                              |
|--------|------------------------------------------------------------------------------------------|
| `$notice->flags()` | Get the integer flag bitmask                                                          |
| `$notice->flags($flags)` | Replace flags using an integer, array of names, or space/comma-separated names  |
| `$notice->addFlag($flag)` | Add one flag by integer or name                                                |
| `$notice->removeFlag($flag)` | Remove one flag by integer or name                                          |
| `$notice->hasFlag($flag)` | Return `true` when a flag is present                                           |
| `$notice->getName()` | Return the related log name: `messages`, `warnings`, or `errors`                    |
| `$notice->getIdStr()` | Return the same value exposed by the read-only `idStr` property                    |
| `$notice->viewable()` | Return whether this notice is viewable to the current user/request                 |

~~~~~php
$notice = new NoticeMessage("Saved", "log noGroup");

if($notice->hasFlag('log')) {
    $notice->removeFlag('log');
}

$notice->addFlag('allowMarkup');
echo $notice->flagsStr; // "noGroup allowMarkup"
~~~~~

---

## Notes

- Source files: `wire/core/Notices/Notice.php` and `wire/core/Notices/Notices.php`.
- The `Notice::log` flag writes to the log named after the notice type:
  `NoticeMessage` → `messages.txt`, `NoticeWarning` → `warnings.txt`,
  `NoticeError` → `errors.txt`.
- `Notice::allowMarkdown` automatically converts the text using
  `$sanitizer->entitiesMarkdown()` and then sets `allowMarkup` — the original
  `allowMarkdown` flag is removed before display.
- Duplicate notices (same type, text, and flags) are silently collapsed by default.
  Use `Notice::allowDuplicate` to prevent collapsing.
- An icon can be set by prefixing the message text with `icon-{name} ` (space after):
  `$this->message("icon-check Item saved")` sets the icon to `check`.
- The `$notices` API variable is shared across all Wire objects in the request.
  Notices added by any module or class accumulate in the same collection.
