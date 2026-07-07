# MarkupHTMLPurifier

Front-end to the [HTML Purifier](http://htmlpurifier.org) library, providing standards-compliant HTML sanitization and validation for ProcessWire. It removes malicious code (XSS) via a secure whitelist and ensures documents are standards-compliant.

```php
$purifier = $modules->get('MarkupHTMLPurifier');
$cleanHTML = $purifier->purify($dirtyHTML);
```

HTML Purifier config options can be set through `set()` before calling `purify()`. The module creates a dedicated cache directory automatically and registers HTML5 definitions for `<figure>` and `<figcaption>`.

## Methods

### purify($html)

Purify the given HTML string and return sanitized HTML.

```php
$dirty = '<p onclick="alert(\'xss\')">Hello</p>';
$clean = $purifier->purify($dirty); // <p>Hello</p>
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$html` | `string` | HTML to sanitize |
| **Returns** | `string` | Sanitized HTML |

### set($key, $value)

Set an HTML Purifier configuration option or a WireData property.

If `$key` contains a dot, it is forwarded to HTML Purifier's config. Any other key is stored as a regular WireData property. Changing an HTML Purifier setting clears the cached purifier instance so the new value takes effect on the next `purify()` call.

```php
$purifier->set('HTML.Allowed', 'p,b,a[href]');
$purifier->set('AutoFormat.AutoParagraph', true);
$purifier->set('Core.Encoding', 'ISO-8859-1');
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Config key; dotted keys are HTML Purifier settings |
| `$value` | `mixed` | Value to set |
| **Returns** | `$this` | Returns the purifier instance for method chaining |

### get($key)

Get an HTML Purifier configuration option or a WireData property.

If `$key` contains a dot, the value is read from HTML Purifier's config; otherwise the parent [[WireData]] `get()` behavior is used.

```php
$encoding = $purifier->get('Core.Encoding');
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Config or property key |
| **Returns** | `mixed` | The current value, or `null` if not set |

### getConfig()

Return the underlying `HTMLPurifier_Config` instance.

```php
$config = $purifier->getConfig();
$config->set('HTML.Allowed', 'p,b,strong,em,i');
```

| **Returns** | `\HTMLPurifier_Config` |

### getDef()

Return the raw HTML definition object (`HTMLPurifier_HTMLDefinition`) used to register custom elements and attributes. This is only available after `init()` has run, which happens automatically when the module is loaded.

```php
$def = $purifier->getDef();
if($def) {
    $def->addAttribute('a', 'data-ext', 'Text');
}
```

| **Returns** | `\HTMLPurifier_HTMLDefinition|null` |

### getPurifier()

Return the cached `HTMLPurifier` instance, creating it on first call.

```php
$htmlpurifier = $purifier->getPurifier();
$clean = $htmlpurifier->purify($html);
```

| **Returns** | `\HTMLPurifier` |

### clearCache()

Remove all cached HTML Purifier serializer files.

```php
$purifier->clearCache();
```

| **Returns** | `void` |

## Hooks

`MarkupHTMLPurifier` extends [[WireData]] and inherits its hooks. The following hook is commonly used to customize the HTML Purifier configuration.

| Hook | When | Arguments |
|------|------|-----------|
| `MarkupHTMLPurifier::initConfig` | After default config and HTML definition are set, before the purifier is created | `$settings` (`\HTMLPurifier_Config`), `$def` (`\HTMLPurifier_HTMLDefinition`) |

```php
$wire->addHookAfter('MarkupHTMLPurifier::initConfig', function(HookEvent $event) {
    $settings = $event->arguments(0); /** @var \HTMLPurifier_Config $settings */
    $def = $event->arguments(1); /** @var \HTMLPurifier_HTMLDefinition $def */
    $def->addAttribute('a', 'data-download', 'Text');
});
```

## Notes

- Access the module through `$modules->get('MarkupHTMLPurifier')`.
- For the full list of HTML Purifier configuration options, see [htmlpurifier.org/live/configdoc/plain.html](http://htmlpurifier.org/live/configdoc/plain.html).
- The module defaults to UTF-8 encoding, allows `nofollow`, `noopener`, and `noreferrer` in `rel` attributes, and registers `<figure>` and `<figcaption>` elements.
- Extends [[WireData]] and implements `Module`.
- **Source file:** `wire/modules/Markup/MarkupHTMLPurifier/MarkupHTMLPurifier.module`

