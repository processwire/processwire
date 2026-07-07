# TextformatterMarkdownExtra

Converts Markdown text to HTML using [Parsedown](https://parsedown.org) by Emanuil Rusev. 
A ProcessWire **Textformatter** module — apply it to any `FieldtypeTextarea` field in the
page editor, or call the `markdown()` method directly from code. Supports Parsedown Extra
features (footnotes, attribute syntax, definition lists, abbreviations, table of contents) 
in addition to standard Markdown.

Inherited from [[Textformatter]]: `format($str)` and `formatValue($page, $field, $value)`,
both of which ProcessWire calls automatically whenever this Textformatter is attached to a
field. This class adds a public `markdown()` helper plus configuration for the Markdown flavor
and safe mode.

```php
// Apply to a field via the admin (Setup > Fields > your_field > Details, Textformatter).
// Or call directly:
$t = $modules->get('TextformatterMarkdownExtra');
$html = $t->markdown("# Headline

This is **bold** and _italic_.");
// <h1>Headline</h1><p>This is <strong>bold</strong> and <em>italic</em>.</p>
```

## Properties

| Property    | Type       | Default | Description                                                                                |
|-------------|------------|---------|--------------------------------------------------------------------------------------------|
| `flavor`    | `int`      | `2`     | Bitmask selecting which Parsedown engine and extensions to use (see Constants below).     |
| `safeMode`  | `bool`     | `false` | When enabled, Parsedown escapes untrusted HTML in untrusted input (see `safeMode()`).     |

## Constants

You may pass any flavor constant directly to `markdown()` or `getParsedown()` to override
the module's configured `flavor` for a single call. The `flavorParsedown` bit (4) is an
ON/OFF switch — set it to choose `Parsedown` over `ParsedownExtra`. The `flavorRCD` bit
(16) is a modifier that may be OR-ed onto any base flavor to enable the deprecated
`markdownExtensions()` post-processing.

| Constant              | Value | Description                                                                                             |
|-----------------------|-------|---------------------------------------------------------------------------------------------------------|
| `flavorDefault`       | 2     | Default flavor — Parsedown Extra.                                                                       |
| `flavorParsedownExtra`| 2     | Parsedown Extra — adds footnotes, abbreviations, definition lists, custom block classes, and more.     |
| `flavorParsedown`     | 4     | Plain Parsedown — standard Markdown only, no extra syntax.                                               |
| `flavorMarkdownExtra` | 2     | Alias of `flavorParsedownExtra` (kept for backwards compatibility).                                     |
| `flavorRCD`           | 16    | Optional modifier bit. OR with a base flavor to apply `markdownExtensions()` post-processing (@deprecated). |

## Methods

### format($str)

Format a string _in place_ — the argument is passed by reference. Inherited from
[[Textformatter]] and implemented here simply by calling `markdown()` with the configured
flavor. ProcessWire invokes this automatically when the Textformatter is attached to a field
and output is rendered.

```php
$str = "# Title

Hello **world**.";
$t->format($str);
echo $str;
// <h1>Title</h1><p>Hello <strong>world</strong>.</p>
```

### formatValue(Page $page, Field $field, $value)

Like `format()`, but with the owning `Page` and `Field` provided. The `$value` is modified
in place. Both `Page` and `Field` are ignored by this implementation — they are present only
to satisfy the [[Textformatter]] contract and are passed through to `markdown()`. Like
`format()`, ProcessWire calls this on your behalf when this Textformatter is attached to a
field and the field is output-formatted.

```php
// You rarely call this yourself — ProcessWire handles it during output formatting:
$textformatter = $modules->get('TextformatterMarkdownExtra');
$textformatter->formatValue($page, $page->field, $page->getUnformatted('body'));
```

### markdown($str, $flavor = null, $safeMode = null)

Primary entry point — converts a Markdown string to HTML and returns it. All other
formatting methods ultimately call this one.

- **$str** (`string`) — Markdown text to convert.
- **$flavor** (`int|null`) — Flavor bitmask. `null` uses the module's configured `flavor`. 
  Pass `flavorParsedown` (4) for plain Markdown, or OR with `flavorRCD` (16) to apply the 
  deprecated post-processing extensions on top of the default.
- **$safeMode** (`bool|null`) — When `true`, escapes untrusted HTML in untrusted input. 
  `null` uses the module's configured `safeMode` setting. Pass an explicit bool to override
  the setting for this single call without changing the module state.
- **Returns** `string` — the converted HTML.

```php
// Use configured flavor and safe mode:
$html = $t->markdown('# Hello');

// Plain Parsedown (no footnote support), bypassing safe mode for this call:
$html = $t->markdown('[^a]

[^a]: note', $t::flavorParsedown, false);
// <p>[^a]</p><p>[^a]: note</p>

// Default flavor (Parsedown Extra) renders footnotes:
$html = $t->markdown('[^a]

[^a]: note', $t::flavorParsedownExtra);
// <p><sup>...footnote link...</sup></p><div class="footnotes">…</div>
```

### markdownSafe($str, $flavor = 0)

Convenience wrapper around `markdown()` that forces safe mode on for this call. Equivalent to
`markdown($str, $flavor, true)`. Unlike `markdown()`, the `$flavor` default is `0` (not `null`),
which always selects ParsedownExtra regardless of the module's configured flavor — pass an
explicit `$flavor` to override.

```php
// Always escapes untrusted HTML, regardless of module's safeMode setting:
$html = $t->markdownSafe("[click](http://x.com)
<script>alert(1)</script>");
// <p><a href="http://x.com">click</a> &lt;script&gt;alert(1)&lt;/script&gt;</p>
```

### safeMode($safeMode = null)

Getter/setter for the module's persistent `safeMode` setting. When called with an argument,
stores `true`/`false` and returns the new value. When called with `null` (or no argument),
returns the current setting without modifying it. The setting takes effect on subsequent
calls to `markdown()` and `getParsedown()` that don't pass an explicit `$safeMode`.

```php
$t->safeMode(true);   // enable safe mode for future markdown() calls
$on = $t->safeMode(); // retrieve current setting (returns true)
```

### getParsedown($flavor = null)

Returns a fresh `\ParsedownExtra` or `\Parsedown` instance suitable for conversion. Behind
the scenes this require_once's the bundled `parsedown` and `parsedown-extra` PHP source
directories on first use. When the module's `safeMode` setting is enabled, the returned
instance has safe mode pre-applied (`$parsedown->setSafeMode(true)`). You normally don't
need to call this directly — `markdown()` does it for you — but it is useful if you want to
configure a Parsedown instance beyond what this module exposes.

- **$flavor** (`int|null`) — Parsedown (`flavorParsedown`) or Parsedown Extra (default).
- **Returns** `\ParsedownExtra|\Parsedown`.

```php
$p = $t->getParsedown($t::flavorParsedown); // plain \Parsedown
echo get_class($p); // 'Parsedown'

$p = $t->getParsedown(); // default
echo get_class($p); // 'ParsedownExtra'
```

### markdownExtensions($str) — @deprecated

Applies a set of "RCD" Markdown extensions _after_ the text has been converted to HTML
(post-processing). Only invoked when `flavorRCD` is OR-ed onto the active flavor; reach it
directly only if you want to apply the same transforms to existing HTML. Treat this method as
deprecated — prefer standard Markdown/Extension syntax instead.

The post-processing adds:

- **id attributes** — a `#id` immediately after an HTML close tag adds `id="..."` to the
  preceding opening tag.
- **class attributes** — a `.class` immediately after an HTML close tag adds
  `class="..."` to the preceding opening tag.
- **target=_blank** — a trailing `+` after a Markdown link makes it open in a new window.
- **comment stripping** — removes C-style `/* … */` and line-start `//` comments from the
  converted HTML.

```php
// Reachable directly (rarely needed):
$t->markdownExtensions($html);
// Or via the flavor flag, which markdown() calls internally:
$t->markdown($md, $t::flavorParsedownExtra | $t::flavorRCD);
```

### getModuleConfigInputfields(InputfieldWrapper $inputfields)

Implements [[ConfigurableModule]]. Provides the module configuration form shown in
**Setup > Modules > Markdown/Parsedown Extra**:

- **Markdown flavor to use** — radio: _Parsedown Extra_ (`flavorParsedownExtra`) or
  _Parsedown_ (`flavorParsedown`).
- **Safe mode?** — toggle. When enabled, Parsedown escapes untrusted HTML in untrusted input.
  See [the Parsedown security note](https://github.com/erusev/parsedown#security).
- **Test markdown** — a textarea where you can paste Markdown, save the module config, and see
  the rendered HTML result as a system notice. Purely a testing convenience — no value is
  stored permanently.

You typically do not call this directly; ProcessWire invokes it when rendering module
configuration.

## Notes

- **Access:** Get an instance with `$modules->get('TextformatterMarkdownExtra')`. Like all
  Textformatters, it is **singular** (`isSingular()` returns true) — ProcessWire loads only
  one shared instance per request. It is **not autoload** — the module is not loaded until
  requested or until a field that uses it is rendered.
- **Applying to a field:** In the admin, edit the field's _Details_ settings and select this
  Textformatter from the _Textformatters_ list. Multiple Textformatters can be chained.
- **Safe mode and untrusted input:** When the module's `safeMode` setting (or the per-call
  argument to `markdown()`) is enabled, Parsedown escapes inline and block HTML rather than
  passing it through. Use this any time the Markdown source comes from untrusted users.
- **The `flavorRCD` bit and `markdownExtensions()`** are legacy. Prefer the native
  Parsedown Extra attribute syntax (`{#id}`, `{.class}`) introduced by the library rather than
  the RCD post-processing syntax.
- **`__toString()`:** Not overridden by this class; see [[WireData]] for inherited
  conversion behavior.
- **Source file:** `wire/modules/Textformatter/TextformatterMarkdownExtra/TextformatterMarkdownExtra.module.php`
- **See also:** [[Textformatter]] for the shared text formatter contract, [[FieldtypeTextarea]] 
  for the field type where Textformatters are most commonly attached.

