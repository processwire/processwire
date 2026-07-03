# InputfieldTextTags

Tags input with predefined and/or user-entered values. It renders a
Selectize-powered tag/token widget that can select from a predefined list, accept
free-form user tags, or search a remote URL by ajax. The stored value is a
single delimited string, but values can also be read and written as arrays.

```php
$f = $modules->get('InputfieldTextTags');
$f->name = 'tags';
$f->label = 'Tags';
$f->allowUserTags = true;

$f->addTag('foo');
$f->addTag('bar', 'This is Bar');
$f->addTag('baz', 'This is Baz');

$f->val(['foo', 'bar']);
$form->add($f);
```

For the shared Inputfield API, including attributes, labels, collapsed states,
`showIf`, rendering, and form processing, see [[Inputfield]].

## Operating Modes

1. Predefined list: populate selectable tags with `addTag()` or
   `setTagsList()`. Users select from the list. Set `allowUserTags = 1` to also
   permit new user-entered tags.
2. Ajax/remote URL: set `tagsUrl` to a URL containing `{q}`. The widget queries
   that URL as the user types. This is useful for large selectable sets.
3. Free input: with no predefined list and `allowUserTags = 1`, the field acts
   as a free-form tag input.

`InputfieldTextTags` is also used by [[InputfieldPage]] in some Page reference
contexts, where tag values represent page IDs.

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `tagsList` | `array|string` | `[]` | Predefined tags as `[tag => label]`, or newline `tag=label` definitions. |
| `tagsUrl` | `string` | `''` | Remote ajax URL containing a `{q}` placeholder. |
| `allowUserTags` | `int|bool` | `0` | Allow tags not present in the predefined list. Stored as `0` or `1`. |
| `closeAfterSelect` | `int|bool` | `1` | Close the dropdown after each selection. Stored as `0` or `1`. |
| `maxItems` | `int` | `0` | Maximum selected tags. `0` means no limit; `1` is single-select style. |
| `maxSelectedItems` | `int` | `0` | Alias of `maxItems`, used by [[InputfieldPage]]. |
| `delimiter` | `string` | `'s'` | `s` = space, `c` = comma, `p` = pipe. |
| `value` | `string` | `''` | Selected tags as a delimiter-separated string. |
| `placeholder` | `string` | `''` | Placeholder text when no tags are selected. |
| `arrayValue` | `array` | `[]` | Read-only selected tags array. |
| `pageSelector` | `string|null` | `null` | Selector used by the `InputfieldSupportsPageSelector` interface. |

## Tag List

### addTag($tag, $label = '', $language = null)

Add one predefined selectable tag. If `$label` is blank, the tag is also used as
the label.

```php
$f->addTag('news');
$f->addTag('events', 'Events and Classes');
```

When LanguageSupport is installed, pass a `Language` object, ID, or name to set a
language-specific label.

### setTagsList($tags, $language = null)

Replace the predefined tags list. Accepts an associative array or a newline
string.

```php
$f->setTagsList([
	'foo' => 'Foo',
	'bar' => 'This is Bar',
]);

$f->setTagsList("foo\nbar=This is Bar\nbaz=This is Baz");
```

### getTagsList($language = null, $getArray = true)

Return predefined tags as `[tag => label]`, or as a newline string when
`$getArray` is `false`.

```php
$tags = $f->getTagsList();
$text = $f->getTagsList(null, false);
```

### removeTag($tag)

Remove a predefined tag and its language-specific labels.

```php
$f->removeTag('foo');
```

### getTagLabel($tag, $language = null)

Return the label for a tag. If the tag has no distinct label, the tag itself is
returned. If the tag is unknown and user tags are not allowed, an empty string is
returned.

```php
echo $f->getTagLabel('bar');
```

### setTagLabel($tag, $label, $language = null)

Set a label for an existing or new tag. This is equivalent to `addTag()`.

## Selectable Options Interface

These methods map onto the tag API:

```php
$f->addOption('foo', 'Foo');             // same as addTag('foo', 'Foo')
$f->addOptions(['a' => 'A', 'b' => 'B']); // associative array required
$f->addOptionLabel('foo', 'Fou', 'fr');  // language-specific label
```

Use associative arrays with `addOptions()`. Numeric arrays use their numeric keys
as tag values.

## Values

The stored value is a string, joined by the configured delimiter:

```php
$f->val('foo bar');
echo $f->val(); // foo bar
```

Array assignment is supported:

```php
$f->val(['foo', 'bar']);
echo $f->val(); // foo bar
```

Read the selected tags as an array with `arrayValue` or `getArrayValue()`:

```php
$tags = $f->arrayValue;      // ['foo' => 'foo', 'bar' => 'bar']
$tags = $f->getArrayValue(); // same
```

`setArrayValue()` is the explicit array setter:

```php
$f->setArrayValue(['foo', 'bar']);
```

When setting the `value` attribute, arrays, `WireArray` values, and `Page`
objects are normalized to the delimiter-separated string form.

## Delimiter

| Value | Delimiter | Typical use |
|-------|-----------|-------------|
| `s` | space | Single-word tags |
| `c` | comma | Multi-word tags |
| `p` | pipe `|` | Multi-word tags or Page IDs |

```php
$f->delimiter = 'c';
$f->val(['New York', 'Los Angeles']);
echo $f->val(); // New York,Los Angeles
```

When used for a Page field, the delimiter is always pipe internally.

## Processing Input

During `processInput()`, submitted values are normalized and validated.

In predefined-list mode without ajax, unknown tags are removed and an error is
recorded unless `allowUserTags` is enabled:

```php
$f->setTagsList(['red' => 'Red']);
$f->allowUserTags = 0;
$form->processInput($input->post);
```

In ajax mode, submitted values may come from a remote source and cannot always be
validated against the local predefined list, so they are preserved for later
handling.

`maxItems` limits the number of selected tags retained during processing.

## Ajax URL

For ajax mode, provide a URL containing `{q}`:

```php
$f->tagsUrl = '/find-tags/?q={q}';
```

The URL endpoint should return matching tags for the typed query. A relative URL
has the current scheme, host, and root URL prepended at render time.

Example URL hook:

```php
$wire->addHook('/find-tags/', function($event) {
	$q = $event->input->get('q', 'text,selectorValue');
	if(strlen($q) < 3) return [];
	return array_values($event->pages->findRaw('parent=/tags/, title%=' . $q . ', field=title'));
});
```

## Static Helper

### InputfieldTextTags::tagsArray(Field $field, $tags = null)

Convert a stored tags string into `[tag => label]` using a Field's configured tag
list. Pass `null` for `$tags` to return all configured tags.

```php
$field = $fields->get('tags');
$labels = InputfieldTextTags::tagsArray($field, $page->tags);
foreach($labels as $tag => $label) {
	echo "<li>$tag: $label</li>";
}
```

When the current `$page` has output formatting on, returned tags and labels are
entity-encoded.

## Notes

- Get an instance with `$modules->get('InputfieldTextTags')`.
- The rendered widget loads Selectize through the `JqueryUI` module.
- Numeric-only tags are prefixed with an underscore for JSON/JavaScript
  transport and unprefixed when converted back to PHP values.
- Multi-language tag labels and placeholders are supported when LanguageSupport
  is installed.
- Related: [[Inputfield]], [[InputfieldPage]], [[FieldtypeText]],
  [[InputfieldSelect]].
- Source file: `wire/modules/Inputfield/InputfieldTextTags/InputfieldTextTags.module`.

