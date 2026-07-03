# InputfieldMarkup

`InputfieldMarkup` renders arbitrary markup or text inside a ProcessWire form
without collecting user input. Use it for help text, instructions, previews,
dividers, informational notices, or other display-only form content.

Because it extends `InputfieldWrapper`, it can also contain child Inputfields.

```php
$f = $modules->get('InputfieldMarkup');
$f->label = 'Instructions';
$f->markupText = '<p>Please fill in all required fields below.</p>';
$form->add($f);
```

For shared Inputfield behavior such as attributes, labels, collapsed states,
`showIf`, rendering, processing, and child management, see the main `Inputfield`
API documentation.

## Providing Content

`InputfieldMarkup` can render content from three sources. They are concatenated in
this order:

1. `value` attribute
2. `markupFunction` return value
3. `markupText`

```php
$f = $modules->get('InputfieldMarkup');
$f->attr('value', '<p>First</p>');
$f->markupFunction = function(InputfieldMarkup $f) {
	return '<p>Second</p>';
};
$f->markupText = '<p>Third</p>';
echo $f->render();
```

### value Attribute

Set the `value` attribute directly when you want to provide markup in the same
way other Inputfields receive values.

```php
$f = $modules->get('InputfieldMarkup');
$f->attr('value', '<hr><p class="notes">Fields marked with * are required.</p>');
$form->add($f);
```

### markupFunction

The `markupFunction` property accepts a closure or callable function name. The
callback receives the `InputfieldMarkup` instance as its first argument.

```php
$f = $modules->get('InputfieldMarkup');
$f->markupFunction = function(InputfieldMarkup $f) {
	$page = $f->wire()->page;
	return "<p>Editing page: <strong>{$page->title}</strong></p>";
};
$form->add($f);
```

### markupText

Use `markupText` for static markup or plain text. This is also the property shown
in the module configuration screen.

```php
$f = $modules->get('InputfieldMarkup');
$f->markupText = '<h3>Important Notice</h3><p>Please read the guidelines before proceeding.</p>';
$form->add($f);
```

## Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `markupText` | `string` | `''` | Static markup or text to display. |
| `markupFunction` | `callable|string|null` | `null` | Closure or callable that returns markup. Receives this `InputfieldMarkup` as the first argument. |
| `textformatters` | `array` | `[]` | Textformatter module names to apply to the rendered output in order. |

## Child Inputfields

Since this class extends `InputfieldWrapper`, child Inputfields can be added and
will render after the markup.

```php
$markup = $modules->get('InputfieldMarkup');
$markup->markupText = '<p>Enter your preferences below:</p>';

$child = $modules->get('InputfieldCheckbox');
$child->name = 'newsletter';
$child->label = 'Subscribe to newsletter';
$markup->add($child);

$form->add($markup);
```

## Methods

### render()

Renders the markup content, applies configured Textformatters, and then appends
rendered child Inputfields.

If a `description` is set, it is rendered above the markup content and then
cleared so the parent wrapper does not render it again below.

```php
echo $f->render();
```

This method is hookable as `InputfieldMarkup::render`.

### renderValue()

Renders the same display content as `render()`. `InputfieldMarkup` has no editable
input, so edit-mode and value-mode rendering are intentionally similar.

```php
echo $f->renderValue();
```

This method is hookable as `InputfieldMarkup::renderValue`.

### renderReady($parent = null, $renderValueMode = false)

Prepares the Inputfield before rendering. When there is no label and `skipLabel`
is `Inputfield::skipLabelBlank`, it adds the `InputfieldHeaderHidden` CSS class.

### getConfigInputfields()

Returns configuration fields for `markupText` and `textformatters`. When this
Inputfield is associated with a Fieldtype through `hasFieldtype`, these custom
configuration fields are skipped because the Fieldtype manages its own settings.

## Textformatters

The `textformatters` property accepts Textformatter module names. Each formatter
is applied to the rendered output in order.

```php
$f = $modules->get('InputfieldMarkup');
$f->markupText = "Hello <b>World</b>";
$f->textformatters = [ 'TextformatterEntities' ];
echo $f->render(); // HTML entities encoded by the formatter
```

Available Textformatters can be discovered at runtime:

```php
foreach($modules->findByPrefix('Textformatter') as $name) {
	echo "$name\n";
}
```

## Hooks

```php
$wire->addHookAfter('InputfieldMarkup::render', function(HookEvent $event) {
	$f = $event->object; /** @var InputfieldMarkup $f */
	if($f->name === 'help_banner') {
		$event->return = '<div class="banner">' . $event->return . '</div>';
	}
});
```

## Notes

- Access this inputfield with `$modules->get('InputfieldMarkup')`.
- It does not render an `<input>` element and does not process user input.
- It is display-only unless child Inputfields are added.
- Its description renders above the markup content rather than below it.
- The module is permanent and cannot be uninstalled.

**Source file:** `wire/modules/Inputfield/InputfieldMarkup/InputfieldMarkup.module`
