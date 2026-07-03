# InputfieldForm

Top-level container for building and processing ProcessWire forms. It extends
[[InputfieldWrapper]] and adds form-specific behavior: `<form>` rendering,
automatic CSRF token rendering/validation, submission detection, and high-level
processing helpers.

```php
$form = $modules->get('InputfieldForm');
$form->attr('id', 'contact-form');

$f = $form->InputfieldText;
$f->attr('name', 'name');
$f->label = 'Your name';
$f->required = true;
$form->add($f);

$f = $form->InputfieldSubmit;
$f->attr('name', 'submit_contact');
$f->val('Send');
$form->add($f);

if($form->isSubmitted('submit_contact')) {
	if($form->process()) {
		$name = $form->getValueByName('name');
		// handle successful submission
	} else {
		echo $form->render();
	}
} else {
	echo $form->render();
}
```

For child management, wrapper rendering, `showIf`, `requiredIf`, column widths,
and inherited Inputfield behavior, see [[InputfieldWrapper]] and [[Inputfield]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `method` | `string` | `'post'` | Form method. Use `'post'` or `'get'`. |
| `action` | `string` | `'./'` | Form action URL. |
| `protectCSRF` | `bool` | `true` | Render and validate CSRF tokens for POST forms. |
| `prependMarkup` | `string` | `''` | Markup inserted immediately after the opening `<form>` tag. |
| `appendMarkup` | `string` | `''` | Markup inserted before the landmark and closing `</form>` tag. |
| `description` | `string` | `''` | Optional form description/headline rendered above child fields. |
| `columnWidthSpacing` | `int` | auto | Pixel spacing between column-width children. |
| `confirmText` | `string` | `'There are unsaved changes:'` | Text used with CSS class `InputfieldFormConfirm`. |

## Rendering

### render()

Render the complete `<form>` element, including child inputfields, optional
description, `prependMarkup`, `appendMarkup`, CSRF token, and the form landmark.

```php
echo $form->render();
```

For POST forms, `render()` outputs:

- A CSRF token when `protectCSRF` is true.
- A hidden landmark named `_InputfieldForm`.

The landmark lets `isSubmitted()` distinguish this form from other forms on the
same request.

For GET forms, CSRF token and landmark markup are omitted.

If the current request includes `modal=1`, the form action is adjusted to retain
that modal state.

## Submission

### isSubmitted($submitName = '')

Return whether the current request submitted this form. Build the full form
before calling this method so it can inspect submit buttons and child names.

```php
if($form->isSubmitted()) {
	// this form was submitted
}

if($form->isSubmitted('submit_save')) {
	// submitted by the save button
}

$button = $form->isSubmitted(true);
if($button === 'submit_save') {
	// save button clicked
}
```

Argument behavior:

| Argument | Return behavior |
|----------|-----------------|
| omitted, `''`, or `false` | `true` if the form was submitted, otherwise `false`. |
| `true` | Name of the clicked `InputfieldSubmit`, or `false`. |
| string | That name when its input was submitted; otherwise `false`. |
| `Inputfield` | Same as string, using the inputfield's name. |

For POST forms, `isSubmitted()` checks the request method, the form landmark, and
the CSRF token when `protectCSRF` is true. CSRF failure returns `false` here;
`process()` / `processInput()` throw on invalid CSRF tokens.

## Processing

### process()

Process the form using the configured request method and return `true` when no
errors were found.

```php
if($form->process()) {
	$email = $form->getValueByName('email');
} else {
	$errors = $form->getErrors();
	echo $form->render();
}
```

`process()` calls `processInput()` with `$input->post` or `$input->get` depending
on `method`.

### processInput(WireInputData $input)

Lower-level processing method for supplied input data.

```php
$form->processInput($input->post);
if(!count($form->getErrors())) {
	// success
}
```

For POST forms with `protectCSRF` enabled, invalid CSRF tokens throw a
`WireException`. Disable CSRF only for trusted/internal forms:

```php
$form->protectCSRF = false;
```

`processInput()` also resolves `showIf` and `requiredIf` dependencies so delayed
children are processed in dependency order.

### getInput()

Return the `WireInputData` passed to the most recent `processInput()` call, or
`null` before processing.

```php
$inputData = $form->getInput();
```

### getErrors($clear = false)

Return child inputfield errors.

```php
$errors = $form->getErrors();
$errors = $form->getErrors(true); // get and clear
$errors = $form->getErrors(null); // clear cache and re-check children
```

Results are cached. Pass `null` to force a fresh check, available in
ProcessWire 3.0.223 and newer.

## Form Name

### getFormName()

Return the value used in the hidden form landmark. Order of preference:

1. Form `name` attribute.
2. Form `id` attribute.
3. Class name `InputfieldForm`.

```php
$form->attr('name', 'profile_form');
echo $form->getFormName(); // profile_form
```

In normal rendered forms, an id is usually present or generated, so the fallback
is commonly an id such as `InputfieldForm1`.

## Layout Classes

Add these CSS classes to alter form behavior:

| Class | Effect |
|-------|--------|
| `InputfieldFormNoHeights` | Do not equalize vertical heights across columns. |
| `InputfieldFormNoWidths` | Use a label/input layout where child column widths are ignored. |
| `InputfieldFormConfirm` | Warn about unsaved changes when leaving the form. |

```php
$form->addClass('InputfieldFormNoHeights');
$form->addClass('InputfieldFormConfirm');
$form->confirmText = 'You have unsaved changes.';
```

If the `FormSaveReminder` module is installed, it controls unsaved-change
behavior instead of `InputfieldFormConfirm`.

## Hook

### renderOrProcessReady($type)

Hook called before rendering or processing. `$type` is `'render'` or `'process'`.

```php
$wire->addHookBefore('InputfieldForm::renderOrProcessReady', function(HookEvent $event) {
	$form = $event->object;
	$type = $event->arguments(0);
	if($type === 'process') {
		// inspect or adjust the form before processing
	}
});
```

## Notes

- Get a fresh form with `$modules->get('InputfieldForm')`.
- `InputfieldForm` is a permanent core module.
- It extends [[InputfieldWrapper]], so child methods like `add()`,
  `getChildByName()`, `getValueByName()`, and `getErrorInputfields()` are
  inherited.
- CSRF protection applies to POST forms only.
- `process()` is available in ProcessWire 3.0.205 and newer.
- Source file: `wire/modules/Inputfield/InputfieldForm/InputfieldForm.module`.
