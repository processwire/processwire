# InputfieldDatetime

Date and time input. Collects date, time, or date+time values through a text field with jQuery UI datepicker/timepicker, separate month/day/year selects, or HTML5 native date/time inputs. Values are stored internally as UNIX timestamps.

```php
$f = $modules->get('InputfieldDatetime');
$f->name = 'event_date';
$f->label = 'Event date and time';
$f->val(time());
$form->add($f);
```

For the shared Inputfield API (attributes, labels, collapsed states, showIf, rendering, processing, etc.), see [[Inputfield]] API.

## Input types

InputfieldDatetime supports three input types controlled by the `inputType` property:

| Type | Class | Description |
|------|-------|-------------|
| `text` | `InputfieldDatetimeText` | Single text input with optional jQuery UI datepicker and timepicker (default) |
| `select` | `InputfieldDatetimeSelect` | Separate `<select>` elements for month, day, and year |
| `html` | `InputfieldDatetimeHtml` | HTML5 date or time input, or a paired date-and-time input |

```php
// HTML5 date+time inputs
$f->inputType = 'html';
$f->htmlType = 'datetime';

// Separate select inputs
$f->inputType = 'select';
$f->dateSelectFormat = 'Mdy'; // "September 15 2024"
```

## Constants

Use these constants when configuring the jQuery UI datepicker for the `text` input type.

| Constant | Value | Description |
|---|---|---|
| `InputfieldDatetime::datepickerNo` | `0` | No datepicker |
| `InputfieldDatetime::datepickerClick` | `1` | Show datepicker on button click |
| `InputfieldDatetime::datepickerInline` | `2` | Inline datepicker always visible (no timepicker support) |
| `InputfieldDatetime::datepickerFocus` | `3` | Show datepicker when input is focused (recommended) |

```php
$f->datepicker = InputfieldDatetime::datepickerFocus;
```

## Properties

### Common properties

| Property | Type | Description |
|---|---|---|
| `value` | `int\|string` | UNIX timestamp integer, or empty string for no value |
| `inputType` | `string` | Input type: `text`, `select`, or `html` |
| `defaultToday` | `bool\|int` | With `text` or `html`, display the current date/time when the value is blank |
| `subYear` | `int` | Substitute year for month/day or time-only input (default `2010`) |
| `subMonth` | `int` | Substitute month for time-only selections (default `4`) |
| `subDay` | `int` | Substitute day for month/year or time-only input (default `8`) |
| `subHour` | `int` | Substitute hour for date-only selections (default `0`) |
| `subMinute` | `int` | Substitute minute for date-only selections (default `0`) |
| `requiredAttr` | `bool\|int` | Also add HTML5 `required` attribute when field is required |

### Text input type properties

| Property | Type | Description |
|---|---|---|
| `datepicker` | `int` | Datepicker mode, one of the `datepicker*` constants |
| `dateInputFormat` | `string` | PHP date format for date input (default `Y-m-d`) |
| `timeInputFormat` | `string` | PHP date format for time input (default none) |
| `timeInputSelect` | `int` | Use `<select>` for time input (`1`) or slider (`0`) |
| `yearRange` | `string` | Datepicker year range, e.g. `-30:+20` |
| `placeholder` | `string` | Placeholder text |
| `showAnim` | `string` | Datepicker animation: `fade`, `show`, `clip`, `drop`, `puff`, `scale`, `slide`, or `none` |
| `changeMonth` | `bool\|int` | Render month as dropdown |
| `changeYear` | `bool\|int` | Render year as dropdown |
| `showButtonPanel` | `bool\|int` | Show Today/Done buttons |
| `numberOfMonths` | `int` | Number of month calendars shown side-by-side |
| `showMonthAfterYear` | `bool\|int` | Show month after year in header |
| `showOtherMonths` | `bool\|int` | Show non-selectable dates from adjacent months |

### HTML5 input type properties

| Property | Type | Description |
|---|---|---|
| `htmlType` | `string` | `date`, `time`, or `datetime`; `datetime` renders paired native date and time inputs |
| `dateStep` | `int` | `step` attribute for date input |
| `dateMin` | `string` | `min` attribute for date input (`YYYY-MM-DD`) |
| `dateMax` | `string` | `max` attribute for date input (`YYYY-MM-DD`) |
| `timeStep` | `int` | `step` attribute for time input, in seconds |
| `timeMin` | `string` | `min` attribute for time input (`HH:MM`) |
| `timeMax` | `string` | `max` attribute for time input (`HH:MM`) |

### Select input type properties

| Property | Type | Description |
|---|---|---|
| `dateSelectFormat` | `string` | Order/format of month/day/year selects, e.g. `mdy`, `Mdy`, `dmy`, `yMd` |
| `timeSelectFormat` | `string` | Time select format (reserved, currently unused) |
| `yearFrom` | `int` | First selectable year |
| `yearTo` | `int` | Last selectable year |
| `yearLock` | `bool\|int` | Disallow years outside yearFrom/yearTo range |

## Methods

### getInputTypes()

Return all available input type instances keyed by type name.

```php
$types = $f->getInputTypes();
foreach($types as $name => $type) {
    echo $name; // text, select, or html
}
```

### getInputType($typeName = '')

Return the active input type instance. Defaults to `text` if `$typeName` is omitted or invalid.

```php
$type = $f->getInputType(); // InputfieldDatetimeText, etc.
```

### setAttribute($key, $value)

Sets an attribute. When `$key` is `value`, strings are converted to UNIX timestamps when possible.

```php
$f->setAttribute('value', '2024-09-15 14:30:00');
echo $f->attr('value'); // unix timestamp
```

### datepickerOptions($options = [])

Get or set custom jQuery UI datepicker options. Also supports timepicker options from the jQuery UI Timepicker Addon.

```php
$f->datepickerOptions(['showButtonPanel' => true]);

// Set defaults for all datetime fields via config
$config->js('InputfieldDatetimeDatepickerDefaults', ['showButtonPanel' => true]);
```

### processInput(WireInputData $input)

Processes submitted input and updates the value. Usually called automatically by forms.

## Configuration

When used as part of a [[FieldtypeDatetime]] field, configuration options are presented in the field editor. The available options depend on the selected `inputType`.

## Notes

- InputfieldDatetime extends [[Inputfield]] and inherits all its methods and properties.
- The corresponding Fieldtype is [[FieldtypeDatetime]].
- Values are stored and retrieved as UNIX timestamps. Use `$datetime->formatDate()` or PHP's `date()` to format for display.
- For date/time formatting reference, see [[WireDateTime]].
- **Source file:** `wire/modules/Inputfield/InputfieldDatetime/InputfieldDatetime.module`
