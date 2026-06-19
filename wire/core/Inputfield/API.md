# Inputfield / InputfieldWrapper

`Inputfield` is the abstract base class for all form input widgets in ProcessWire.
`InputfieldWrapper` extends it to hold child Inputfields (forms, fieldsets). Together
they are the backbone of ProcessWire's form rendering and input processing.

Inputfields are modules — get a new instance via `$modules->get()`:

```php
$f = $modules->get('InputfieldText');
$f->label = 'Your Name';
$f->attr('name', 'your_name');
$f->attr('value', '');
$f->required = true;
```

---

## Getting instances

### Via $modules->get()

```php
$f      = $modules->get('InputfieldText');   /** @var InputfieldText $f */
$form   = $modules->get('InputfieldForm');   /** @var InputfieldForm $form */
$select = $modules->get('InputfieldSelect'); /** @var InputfieldSelect $select */
```

Each call returns a fresh instance. This is the standard approach when building forms manually.

### Via InputfieldWrapper magic property

One benefit of this approach is that it enables IDEs to identify the Inputfield type without 
a `/** @var InputfieldText $f */` type hint. 

```php
$f = $wrapper->InputfieldText;       // new InputfieldText, not yet added to wrapper
$f = $wrapper->InputfieldSelect;
```
Note: `InputfieldForm` is a type of `InputfieldWrapper`. 

### Via $wrapper->new()

Creates an Inputfield, sets basic properties, **adds it to the wrapper**, and returns it.
Short type names are accepted (without the "Inputfield" prefix).

```php
/** @var InputfieldText $f */
$f = $wrapper->new('InputfieldText', 'phone', 'Phone Number');
$f = $wrapper->new('text', 'email', 'Email', ['required' => true]);
/** @var InputfieldSelect $f */
$f = $wrapper->new('select', 'color', 'Color', ['description' => 'Choose one']);
```

Arguments: `$typeName, $name = '', $label = '', $settings = []`. Any of `$name`,
`$label`, or `$settings` can be replaced with an array to be treated as `$settings`.

---

## Inputfield

### Attributes

#### attr($key, $value)

Get or set an HTML attribute. The primary method for working with input attributes.

```php
$f->attr('name', 'email');
$f->attr('value', 'default@example.com');
$name = $f->attr('name'); // get

// Set multiple attributes at once
$f->attr([
    'name' => 'email',
    'value' => 'default@example.com',
    'placeholder' => 'you@example.com',
]);

// Set name and id to the same value
$f->attr('name+id', 'email');

// Get all attributes
$attrs = $f->attr(true);
```

Passing `false` as the value removes most boolean attributes when setting them. To
remove any attribute explicitly, use `$f->removeAttr('attributeName')`.

#### val($value)

Shortcut getter/setter for the `value` attribute.

```php
$current = $f->val();      // get
$f->val('new value');      // set
```

#### name() / id() / class()

Chainable getter/setter methods:

```php
$f->name('email')->id('input-email')->class('uk-input');
$name = $f->name(); // get
```

---

### Labels and text

| Property        | Description                                                      |
|-----------------|------------------------------------------------------------------|
| `label`         | Primary label (appears above input)                              |
| `description`   | Detailed description (appears under label)                       |
| `notes`         | Notes (appears under input)                                      |
| `detail`        | Additional detail text (appears under notes, 3.0.140+)           |
| `icon`          | Font Awesome icon name, without "fa-" prefix                     |
| `head`          | Text between label and description                               |
| `tabLabel`      | Label when rendered as its own tab                               |
| `prependMarkup` | Markup prepended inside the content container                    |
| `appendMarkup`  | Markup appended inside the content container                     |
| `requiredLabel` | Custom label for the required-field validation message           |

All support magic property set/get (this is the most commonly used syntax):

```php
$f->label = 'Email Address';
$f->description = 'Enter your primary email';
$f->notes = 'We will not share your email';
$f->icon = 'envelope';
```

---

### Collapsed state

Controls how the Inputfield renders in the admin. Set via the `collapsed` property
using one of the constants below:

```php
$f->collapsed = Inputfield::collapsedYes;
$f->collapsed = Inputfield::collapsedBlankAjax;
```

| Constant                | Value | Description                                             |
|-------------------------|-------|---------------------------------------------------------|
| `collapsedNo`           | 0     | Open (default)                                          |
| `collapsedYes`          | 1     | Collapsed; user can open                                |
| `collapsedBlank`        | 2     | Collapsed only when blank/empty                         |
| `collapsedHidden`       | 4     | Hidden; not rendered at all                             |
| `collapsedPopulated`    | 5     | Collapsed only when populated                           |
| `collapsedNoLocked`     | 6     | Visible but not editable                                |
| `collapsedBlankLocked`  | 7     | Collapsed when blank; visible-locked when populated     |
| `collapsedYesLocked`    | 8     | Collapsed; locked when visible                          |
| `collapsedLocked`       | 8     | Alias for `collapsedYesLocked`                          |
| `collapsedNever`        | 9     | Cannot be collapsed by the user                         |
| `collapsedYesAjax`      | 10    | Collapsed; content loaded via Ajax when opened          |
| `collapsedBlankAjax`    | 11    | Collapsed when blank; content loaded via Ajax           |
| `collapsedTab`          | 20    | Rendered as its own tab                                 |
| `collapsedTabAjax`      | 21    | Rendered as its own tab; content loaded via Ajax        |
| `collapsedTabLocked`    | 22    | Rendered as its own tab; locked (not editable)          |

---

### Visibility and layout

`showIf` and `requiredIf` enable the conditional visibility and required state of
an Inputfield. These are often referred to as Inputfield dependencies. These properties
accept ProcessWire selector strings referencing other field names in the same form. 
Note that they support a reduced set of selector features, so test and verify before assuming
a showIf/requiredIf condition works properly. Supported operators include
`=`, `!=`, `<`', `<=`, `>`, `>=`, `*=`. OR conditions are also supported for field name and value.

```php
// show only when Inputfield `info_type` field has value 'contact'.
$f->showIf = 'info_type=contact';

// required only when condition is met ($f->required must be true)
$f->requiredIf = 'info_type=contact'; 
$f->required = true;

// percentage width for 2+ Inputfields in a row (10-100; 0 treated as 100)
$f->columnWidth = 50;
```
When matching Page reference fields in a showIf or requiredIf selector, note 
that you are matching by page ID. 

---

### Skip label

Controls label rendering. Use `skipLabel` constants:

| Constant           | Value   | Description                              |
|--------------------|---------|------------------------------------------|
| `skipLabelNo`      | `false` | Render label normally (default)          |
| `skipLabelFor`     | `true`  | Render label without `for` attribute     |
| `skipLabelHeader`  | 2       | Don't render the label element at all    |
| `skipLabelBlank`   | 4       | Skip label if it is blank                |
| `skipLabelMarkup`  | 8       | Allow HTML markup in the label string    |

```php
$f->skipLabel = Inputfield::skipLabelHeader; // no label rendered
$f->skipLabel = Inputfield::skipLabelMarkup; // allow <strong> etc. in label
```

---

### Class manipulation

```php
$f->addClass('uk-input uk-form-large');      // add to input element
$f->addClass('highlight', 'wrapClass');      // add to .Inputfield wrapper element
$f->addClass('wrap:card, header:card-head'); // formatted element-specific classes

if($f->hasClass('required')) { ... }

$f->removeClass('old-class');
```

The `$property` argument accepts: `'class'`, `'wrapClass'`, `'headerClass'`, `'contentClass'`.
The short names `'input'`, `'wrap'`, `'header'`, and `'content'` are also accepted
where class-property names are parsed.

You can also use fluent class methods for common wrapper elements:

```php
$f->wrapClass('card');          // add class to outer .Inputfield wrapper
$f->headerClass('card-head');   // add class to .InputfieldHeader
$f->contentClass('card-body');  // add class to .InputfieldContent

$wrapClass = $f->wrapClass();   // get current wrapper classes
```

---

### Processing and validation

Common form-handling pattern:

```php
/** @var InputfieldForm $form */
$form = $modules->get('InputfieldForm'); 

/** @var InputfieldEmail $f */
$f = $modules->get('InputfieldEmail');
$f->attr('name', 'email');
$f->label = 'Your email';
$f->required = true;
$form->add($f);

/** @var InputfieldSubmit $f */
$f = $modules->get('InputfieldSubmit');
$f->attr('name', 'submit_send'); 
$f->value = 'Send';
$form->add($f);

if($input->post('submit_send')) {
    $form->processInput($input->post);
    if(count($form->getErrors())) {
        // re-render form with errors
    } else {
        /** @var InputfieldEmail $f */
        $f = $form->getChildByName('email');
        $email = $f->value;
        // handle submission
        // ...
        $session->location('/success/page/url');
    }
}

echo $form->render();
```

More modern form handling pattern: 

```php
/** @var InputfieldForm $form */
$form = $modules->get('InputfieldForm'); 

$f = $form->InputfieldEmail;
$f->attr('name', 'email');
$f->label = 'Your email';
$f->required = true;
$form->add($f);

$f = $form->InputfieldSubmit;
$f->attr('name', 'submit_send'); 
$f->val('Send');
$form->add($f);

if($form->isSubmitted('submit_send')) {
    if($form->process()) {
        $email = $form->getValueByName('email');
        // handle submission
        $session->location('/success/page/url');
    } else {
        // re-render form with errors
    }
}

echo $form->render();
```

#### process()

Process an `InputfieldForm` using the form's configured method (`post` by default,
or `get` when `$form->attr('method', 'get')`). Returns `true` when processing
completed with no errors, or `false` when validation errors were recorded.

```php
if($form->process()) {
    // form processed successfully
} else {
    $errors = $form->getErrors();
    $errorFields = $form->getErrorInputfields();
}
```

This is often more convenient than `processInput()` at the form level because it
does not require passing `$input->post` or `$input->get`, and it gives you a
success/failure boolean directly.

#### CSRF protection

`InputfieldForm` has CSRF protection enabled by default for POST forms.

```php
$form = $modules->get('InputfieldForm');
$form->protectCSRF = true; // default
```

When the form renders, it automatically includes the CSRF token input. When you
call `$form->process()` or `$form->processInput($input->post)`, the token is
validated. If the token is invalid, ProcessWire throws a `WireException`.

Disable CSRF protection only for trusted/internal forms:

```php
$form->protectCSRF = false;
```

CSRF protection is applied to POST forms, not GET forms.

#### processInput(WireInputData $input)

Process submitted input data for this Inputfield and populate its value.

```php
$f->processInput($input->post);
$value = $f->val(); // now contains the processed value
```

#### isEmpty()

Returns `true` if the Inputfield's current value is considered empty.

#### error($text) / getErrors($clear) / clearErrors()

```php
$f->error('Value is not valid');
$errors = $f->getErrors();       // array of error strings
$errors = $f->getErrors(true);   // get and clear
$f->clearErrors();
```

---

### Rendering

```php
echo $f->render();        // render as an editable input
echo $f->renderValue();   // render as a value display (no input)
```

#### editable($setEditable)

Get or set whether the Inputfield renders in editable or value-display mode.

```php
$f->editable(false); // lock; renderValue() used instead of render()
$isEditable = $f->editable();
```

---

### Hierarchy

```php
$parent = $f->parent();       // get parent InputfieldWrapper
$parents = $f->getParents();  // all ancestor wrappers
$root = $f->getRootParent();  // top-level wrapper
$form = $f->getForm();        // nearest InputfieldForm ancestor
```

---

## InputfieldWrapper

Extends `Inputfield` to hold an ordered set of child Inputfields. Base class for
forms (`InputfieldForm`) and fieldsets (`InputfieldFieldset`).

### Adding children

```php
$wrapper->add($f);              // append an Inputfield
$wrapper->add('InputfieldText'); // shortcut: create and add by type name
$wrapper->prepend($header);
$wrapper->append($submit);
$wrapper->insertBefore($phone, $email);
$wrapper->insertAfter($address, $phone);
```

### Creating and adding in one call

```php
// $wrapper->new() creates, configures, adds, and returns the Inputfield
$f = $wrapper->new('text', 'first_name', 'First Name');
$f->required = true; // chainable further configuration
```

### Removing children

```php
$wrapper->remove($f);
$wrapper->remove('phone');       // remove by name, returns the wrapper
$removed = $wrapper->removeByName('phone'); // remove by name, returns removed field or null
```

### Accessing children

```php
$all      = $wrapper->children();              // all children as InputfieldsArray
$required = $wrapper->children('required=1');  // filtered by selector
$f        = $wrapper->getChildByName('email'); // single child by name
$f        = $wrapper->getByName('email');      // alias
$f        = $wrapper->get('email');            // also works (WireData)
$value    = $wrapper->getValueByName('email'); // get a child's value directly
$found    = $wrapper->find('required=1');      // recursive selector search
```

### Batch import

```php
// Import from an array of definitions
$wrapper->importArray([
    [
        'type' => 'InputfieldText',
        'name' => 'first',
        'label' => 'First Name',
        'attr' => [
            'placeholder' => 'Ada',
        ],
    ],
    [
        'type' => 'InputfieldEmail',
        'name' => 'email',
        'label' => 'Email',
        'required' => true,
    ],
]);
```

### Processing and errors

```php
$wrapper->processInput($input->post);   // processes all children
$errors = $wrapper->getErrors();        // errors from all children
$empty  = $wrapper->getEmpty();         // required children that are empty
$badFields = $wrapper->getErrorInputfields(); // children with errors
```

`isEmpty()` on a wrapper returns `true` only if ALL children are empty.

### Rendering

```php
echo $wrapper->render();       // render all children
echo $wrapper->renderValue();  // render all children in value-display mode
```

### Markup customization

Override the default `<ul>/<li>` structure globally:

```php
InputfieldWrapper::setMarkup([
    'list' => "<div {attrs}>{out}</div>",
    'item' => "<div {attrs}>{out}</div>",
]);

InputfieldWrapper::setClasses([
    'list' => 'my-form',
    'item' => 'my-field {class}',
]);
```

Placeholders: `{attrs}`, `{out}`, `{class}`, `{name}`, `{for}`.

---

## UIKit theme settings

These properties are recognized by AdminThemeUikit to control visual presentation:

| Property          | Values                                           | Description         |
|-------------------|--------------------------------------------------|---------------------|
| `themeOffset`     | `'s'`, `'m'`, `'l'`                              | Margin/offset size  |
| `themeBorder`     | `'none'`, `'card'`, `'hide'`, `'line'`           | Border style        |
| `themeInputSize`  | `'s'`, `'m'`, `'l'`                              | Input size          |
| `themeInputWidth` | `'xs'`, `'s'`, `'m'`, `'l'`, `'f'`               | Input width         |
| `themeColor`      | `'primary'`, `'secondary'`, `'warning'`, etc.    | Color theme         |
| `themeBlank`      | `bool`                                           | No container/border |

---

## Notes

- **Source files:** `wire/core/Inputfield/Inputfield.php` and `InputfieldWrapper.php`
- `Inputfield` extends `WireData` and implements `Module`. All WireData methods are available.
- `$modules->get('InputfieldText')` and `$form->InputfieldText` return a fresh instance each call (not a singleton).
- `showIf` and `requiredIf` selectors reference other Inputfield names or IDs in the same rendered form.
- Each Inputfield `processInput()` is called automatically by `InputfieldWrapper::processInput()` — you usually call it on the form, not individual fields.
- `renderValue()` is used in contexts where editing is not allowed (locked fields, view-only displays).
- `collapsedHidden` (4) hides the field entirely from render — it won't appear in the DOM at all.
- The Tab constants (`collapsedTab`, `collapsedTabAjax`, `collapsedTabLocked`) render the field as a separate admin tab rather than a collapsible section.
- Ajax collapse constants load field content only when the user opens the collapsed section, improving page load on fields with heavy output.
