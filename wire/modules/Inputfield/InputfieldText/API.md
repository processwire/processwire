# InputfieldText

Single-line text input. The most commonly used Inputfield — renders an `<input type="text">`
element and provides server-side validation for length, pattern, and content.

```php
$f = $modules->get('InputfieldText');
$f->name = 'first_name';
$f->label = 'First name';
$f->placeholder = 'Enter your first name';
$f->required = true;
$f->minlength = 2;
$f->maxlength = 20;
```

For the shared Inputfield API (attributes, labels, collapsed states, showIf, rendering,
processing, etc.), see [[Inputfield]].

## Properties

| Property        | Type             | Default      | Description                                                         |
|-----------------|------------------|--------------|---------------------------------------------------------------------|
| `type`          | `string`         | `'text'`     | HTML input type attribute                                           |
| `size`          | `int`            | `0`          | Display width in characters; 0 = full width                         |
| `minlength`     | `int`            | `0`          | Minimum character length (0 = no minimum)                           |
| `maxlength`     | `int`            | `2048`       | Maximum character length; 0 = no limit (see note)                   |
| `placeholder`   | `string`         | `''`         | Placeholder text shown when input is empty                          |
| `pattern`       | `string`         | `''`         | HTML5 regex pattern for client/server validation                    |
| `initValue`     | `string`         | `''`         | Initial value shown when no value is set                            |
| `stripTags`     | `bool`           | `false`      | Strip HTML tags from the value when set                             |
| `noTrim`        | `bool`           | `false`      | Prevent whitespace trimming of the value                            |
| `useLanguages`  | `bool`           | `false`      | Provide one input per language (requires LanguageSupport)           |
| `requiredAttr`  | `bool\|int`      | `false`      | When combined with `required`, adds HTML5 `required` attribute      |
| `showCount`     | `int`            | `0`          | Show character counter (`1`) or word counter (`2`) or none (`0`)    |
| `autocomplete`  | `string\|null`   | `null`       | HTML5 autocomplete attribute (since 3.0.252)                        |

## Constants

| Constant           | Value | Description                              |
|--------------------|-------|------------------------------------------|
| `defaultMaxlength` | 2048  | Default maxlength when none specified    |
| `showCountNone`    | 0     | No counter                               |
| `showCountChars`   | 1     | Character counter                        |
| `showCountWords`   | 2     | Word counter                             |

## Validation

InputfieldText performs server-side validation during `processInput()`:

### Minimum length

When `minlength` is set to a positive value and the input is not empty, the value
must meet the minimum character length. If it doesn't, an error is recorded.

```php
$f->minlength = 5;
// Error if input has fewer than 5 characters
```

Note: minlength is only enforced when a value is present. If the field is not
required and left blank, no minlength error is triggered.

### Maximum length

When `maxlength` is set to a positive value, input exceeding it is truncated and
an error message is recorded informing the user. When `maxlength` is 0, there is
no limit. The default is 2048.

```php
$f->maxlength = 100;
// Input over 100 chars is truncated, error shown
```

### Pattern

When `pattern` is set to a valid regex, the value is validated against it both
client-side (via HTML5 `pattern` attribute) and server-side. On the server side,
the pattern is applied as a PCRE regex with `#` delimiters (escaped automatically).

```php
$f->pattern = '^[a-zA-Z0-9_]+$';
// Only alphanumeric and underscore allowed
```

### Tag stripping

When `stripTags` is `true`, HTML tags are removed from the value via PHP's
`strip_tags()` during value assignment.

```php
$f->stripTags = true;
$f->val('<b>Hello</b>'); // stores 'Hello'
```

### Whitespace trimming

By default, leading and trailing whitespace is trimmed from the value. Set
`noTrim` to `true` to preserve whitespace.

```php
$f->noTrim = true;
$f->val('  hello  '); // stores '  hello  '
```

## Character/word counter

When `showCount` is set to `showCountChars` (1) or `showCountWords` (2), a
JavaScript counter is displayed below the input showing the current character or
word count. When combined with `minlength` or `maxlength`, the counter also
displays the minimum/maximum limits.

```php
$f->showCount = InputfieldText::showCountChars;
$f->maxlength = 140;
// Shows "X characters (140 max)"
```

## Initial value

The `initValue` property provides a default value that populates the input's `value`
attribute when no actual value has been set. If the user doesn't change it, the
initValue will be submitted with the form just like any other value.

```php
$f->initValue = 'Enter your name';
// If $f->val() is empty, the input's value attribute becomes "Enter your name"
```

## Autocomplete

The `autocomplete` property sets the HTML5 autocomplete attribute. Available
options depend on the input type:

- For `type='text'`: any HTML5 autocomplete token (e.g. `'username'`, `'given-name'`, `'email'`, `'tel'`)
- For `type='email'`: `'on'`, `'off'`, or `'email'`
- For `type='tel'`: `'on'`, `'off'`, or `'tel'`

```php
$f->autocomplete = 'username';
```

## Multi-language

When `useLanguages` is `true` and the LanguageSupport module is installed, the
Inputfield renders one input per language. Each language's value is accessed via
`value[languageID]`, and placeholders can also be set per language via
`placeholder[languageID]`.

```php
// enables multi-language support for Inputfield types 
$f->useLanguages = true;

// get a non-default language
// $language can be Language (Page) object, ID or name (i.e. 'es')
$language = $languages->get("es");

// set 'value' for language
$f->setLanguageValue($language, "Spanish value");
$f->setLanguageValue("es", "Spanish value"); // same as above

// this also works if $language is not 'default' language
$f->set("value$language", "Spanish value");

// get 'value' for non-default language
$value = $f->getLanguageValue($language); 
$value = $f->getLanguageValue("es"); // same as above

// this also works if $language is not 'default 'language'
$value = $f->get("value$language");

// get or set 'default' language value using val()
$f->val("Default language value");
$value = $f->val();

// set label, description or notes
$f->set("label", "Your name"); // default language label
$f->set("label$language", "Tu nombre"); // label for other language(s)
$f->label = __('Your name'); // or make it admin translatable

// setting other properties
$f->set("placeholder", "Enter your name"); // default language
$f->set("placeholder$language", "Escribe tu nombre"); // other language(s)
$f->set("placeholder", __('Enter your name')); // admin translatable
```

## Usage with FieldtypeText

When used as a Field on a Page (via FieldtypeText), the Inputfield's settings are
configured on the Field object and applied automatically. See
[[FieldtypeText]] for details
on how text fields store and format values.

For standalone forms (module config, Process modules, front-end forms), create
and configure the Inputfield directly as shown in the examples above.

## Notes

- The default `maxlength` of 2048 is suitable for most single-line text inputs. Set to 0 for no limit.
- Values are sanitized via `$sanitizer->text()` when `maxlength` is positive, enforcing max byte length as well as character length.
- The `size` attribute only affects the visual width of the input; it does not limit the number of characters that can be entered (use `maxlength` for that).
- When `size` is 0 (default), the input uses full width with the `InputfieldMaxWidth` CSS class.
- The `minlength` attribute is converted to `data-minlength` in the rendered HTML because browser support for the native `minlength` attribute is limited.
- **Source file:** `wire/modules/Inputfield/InputfieldText/InputfieldText.module`.
