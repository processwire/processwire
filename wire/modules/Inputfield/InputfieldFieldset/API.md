# InputfieldFieldset

`InputfieldFieldset` groups one or more Inputfields together in a container. It
extends `InputfieldWrapper`, so it supports the same child-management, rendering,
processing, attribute, and conditional-visibility APIs as other wrappers.

Fieldsets are commonly used to organize related fields in ProcessWire forms,
page editors, and module configuration screens.

```php
$fieldset = $modules->get('InputfieldFieldset');
$fieldset->label = 'Address';
$fieldset->description = 'Enter your address details';
$fieldset->icon = 'home';
$fieldset->collapsed = Inputfield::collapsedYes;

$street = $modules->get('InputfieldText');
$street->name = 'street';
$street->label = 'Street';
$fieldset->add($street);

$city = $modules->get('InputfieldText');
$city->name = 'city';
$city->label = 'City';
$fieldset->add($city);
```

For the shared `Inputfield` and `InputfieldWrapper` APIs, see the main
`Inputfield` API documentation.

## Properties

`InputfieldFieldset` adds no unique public properties. Common inherited
properties include:

| Property | Type | Description |
| --- | --- | --- |
| `label` | `string` | Header label for the fieldset. |
| `description` | `string` | Description text shown with the fieldset. |
| `collapsed` | `int` | Collapsed state, usually one of the `Inputfield::collapsed*` constants. |
| `icon` | `string` | Optional icon name shown in supported admin themes. |

## Methods

### render()

Renders the fieldset child contents. As with other Inputfields, the fieldset
wrapper markup, label, description, and collapsed state are applied by the parent
`InputfieldWrapper` or `InputfieldForm` when it renders this fieldset as a child.

```php
$form = $modules->get('InputfieldForm');
$form->add($fieldset);
echo $form->render();
```

The implementation appends an extra newline to the parent wrapper output. This
is intentional: it prevents parent wrappers from skipping an otherwise empty
fieldset. Empty fieldsets are used by modules such as `InputfieldRepeater` to
display label and description text.

This method is hookable as `InputfieldFieldset::render`.

### getModuleInfo()

Returns module metadata for ProcessWire module discovery. It is not normally
called by site code.

## Nested Fieldsets

Fieldsets can contain other fieldsets:

```php
$personal = $modules->get('InputfieldFieldset');
$personal->label = 'Personal Information';

$address = $modules->get('InputfieldFieldset');
$address->label = 'Address';
$address->collapsed = Inputfield::collapsedYes;

$street = $modules->get('InputfieldText');
$street->name = 'street';
$street->label = 'Street';
$address->add($street);

$personal->add($address);
$form->add($personal);
```

Deep nesting can make admin forms harder to scan, so use nested fieldsets only
when the grouping helps the user.

## Empty Fieldsets

Unlike a plain `InputfieldWrapper`, an empty `InputfieldFieldset` is not skipped
entirely by parent wrappers. Its direct `render()` result is a newline, so parent
wrappers still treat it as output and can render the fieldset label/description.

If you need an empty fieldset hidden, hide it explicitly:

```php
if(!count($fieldset->children())) {
	$fieldset->collapsed = Inputfield::collapsedHidden;
}
```

## Hooks

```php
$wire->addHookBefore('InputfieldFieldset::render', function(HookEvent $event) {
	$fieldset = $event->object; /** @var InputfieldFieldset $fieldset */
	$fieldset->addClass('my-custom-fieldset', 'wrapClass');
});

$wire->addHookAfter('InputfieldFieldset::render', function(HookEvent $event) {
	$event->return .= '<div class="fieldset-footer">End of section</div>';
});
```

Inherited wrapper hooks, such as `InputfieldWrapper::renderInputfield`, are also
available when working with child Inputfields.

## Notes

- Access this inputfield with `$modules->get('InputfieldFieldset')`.
- Each `$modules->get('InputfieldFieldset')` call returns a new instance.
- The module is permanent and cannot be uninstalled.
- The empty-fieldset rendering behavior is intentional and should not be removed
  without checking modules that rely on it, especially repeaters.

**Source file:** `wire/modules/Inputfield/InputfieldFieldset/InputfieldFieldset.module`
