# FileValidatorModule

Base class for FileValidator modules. ProcessWire discovers modules whose class name
begins with `FileValidator` and automatically invokes them when files are validated
through `$sanitizer->validateFile()`. Built-in examples include [[FileValidatorImage]]
and [[FileValidatorZip]].

A FileValidator module is a small class that answers one question: is a given file on
disk acceptable for its type? You create one by extending `FileValidatorModule`,
advertising the extensions you support via `getModuleInfo()['validates']`, and
implementing the abstract `isValidFile()` method.

```php
// Use an installed validator directly
$validator = $modules->get('FileValidatorImage');
$validator->minWidth = 100;
$validator->minHeight = 100;

if($validator->isValid('/path/to/photo.jpg')) {
    echo "Valid image";
} else {
    echo "Invalid: " . $validator->errors('last string');
}
```

Most of the time you will not call FileValidator modules yourself. Instead, ProcessWire
calls them automatically as part of `$sanitizer->validateFile()`:

```php
$valid = $sanitizer->validateFile('/path/to/upload.jpg');
if(!$valid) {
    foreach($sanitizer->errors() as $error) {
        echo "<p>$error</p>";
    }
}
```

Because `FileValidatorModule` extends [[WireData]], it inherits `get()`, `set()`,
`setArray()`, and the rest of the WireData API. See [[WireData]] for details.

---

## Module info

Every FileValidator module must provide its own static `getModuleInfo()` method.
The only ProcessWire-specific requirement is the `validates` key: an array of
lowercase file extensions that the module handles. Extensions may also be regular
expressions when wrapped in `/…/`.

| Key         | Type     | Required | Description |
|-------------|----------|----------|-------------|
| `title`     | `string` | yes      | Human-readable module title |
| `version`   | `int`    | yes      | Module version number |
| `summary`   | `string` | yes      | Short summary for the modules list |
| `validates` | `array`  | yes      | Extensions this module validates |
| `singular`  | `bool`   | no       | Whether only one instance may exist (default: `false`) |
| `autoload`  | `bool`   | no       | Whether to autoload the module (default: `false`) |
| `requires`  | `string` | no       | Minimum ProcessWire version or required modules |

```php
public static function getModuleInfo() {
    return [
        'title' => 'Validate HTML files',
        'version' => 1,
        'summary' => 'Validates uploaded HTML files',
        'validates' => ['html', 'htm'],
    ];
}
```

To validate every file type regardless of extension, specify `'/.*/'` in the
`validates` array.

---

## Context properties

When ProcessWire validates a file that belongs to a Page/Field/Pagefile context, it
populates the following context on the validator before calling `isValid()`:

| Property    | Type                  | Description |
|-------------|-----------------------|-------------|
| `_page`     | `Page\|NullPage`      | Page associated with the file, if any |
| `_field`    | `Field\|null`         | Field associated with the file, if any |
| `_pagefile` | `Pagefile\|Pageimage\|null` | Pagefile or Pageimage associated with the file, if any |

Use the public getter methods below to read them. The `_pagefile` getter will fall
back to the page/field objects it carries when no direct context was set.

---

## Methods

### isValidFile($filename)

`abstract protected function isValidFile($filename)`

The one method every FileValidator module must implement. Receives the full disk
path to the file and must return one of the following:

| Return value | Meaning |
|--------------|---------|
| `true`       | The file is valid as-is. |
| `false`      | The file is not valid. Call `$this->error('reason')` to explain why. |
| `1`          | The file is valid because the validator sanitized it in place. |

```php
protected function isValidFile($filename) {
    $html = file_get_contents($filename);
    if($html === false) {
        $this->error('Unable to read HTML file');
        return false;
    }
    if(strpos($html, '<script') !== false) {
        $this->error('HTML file contains script tags');
        return false;
    }
    return true;
}
```

### isValid($filename)

`final public function isValid($filename)`

Public entry point that ProcessWire calls. You should not override this method. It
invokes `isValidFile()`, logs the result, and returns the same boolean or integer
value. Errors recorded with `$this->error()` inside `isValidFile()` remain available
through `$validator->errors()`.

### getPage()

`public function getPage()`

Returns the Page associated with the current validation, or `null` if none.
If no page was set directly but a `Pagefile` context was set, this returns the
page from that Pagefile.

### getField()

`public function getField()`

Returns the Field associated with the current validation, or `null` if none.
If no field was set directly but a `Pagefile` context was set, this returns the
field from that Pagefile.

### getPagefile()

`public function getPagefile()`

Returns the Pagefile or Pageimage associated with the current validation, or
`null` if none. See [[Pagefile]] and [[Pageimage]].

### setPage(Page $page)

`public function setPage(Page $page)`

Sets the Page context for the next `isValid()` call.

### setField(Field $field)

`public function setField(Field $field)`

Sets the Field context for the next `isValid()` call.

### setPagefile(Pagefile $pagefile)

`public function setPagefile(Pagefile $pagefile)`

Sets the Pagefile (or Pageimage) context for the next `isValid()` call.

### log($str = '', array $options = [])

`public function ___log($str = '', array $options = array())`

Logs a message to the `file-validator` log. This is the hookable log method used
by `isValid()` to record validation outcomes. You can also call it from your own
`isValidFile()` implementation if you want custom log entries.

```php
$this->log("Validated $filename");
```

---

## Creating a custom FileValidator module

1. Create a class named `FileValidatorSomething` that extends `FileValidatorModule`.
2. Place it in `/site/modules/FileValidatorSomething.module` or its own directory.
3. Implement `getModuleInfo()` with a `validates` array.
4. Implement `isValidFile($filename)`.

```php
<?php namespace ProcessWire;

class FileValidatorHtml extends FileValidatorModule {

    public static function getModuleInfo() {
        return [
            'title' => 'HTML File Validator',
            'version' => 1,
            'summary' => 'Validates uploaded HTML files',
            'validates' => ['html', 'htm'],
        ];
    }

    protected function isValidFile($filename) {
        $html = file_get_contents($filename);
        if($html === false) {
            $this->error('Unable to read file');
            return false;
        }
        if(stripos($html, '<script') !== false) {
            $this->error('HTML contains script tags');
            return false;
        }
        return true;
    }
}
```

Install the module via **Modules > Refresh**, then upload a matching file to trigger
validation automatically.

---

## Hooks

`FileValidatorModule` inherits all hooks from [[WireData]] and [[Wire]]. The only
hookable method defined in this class is `log`.

| Hook                       | When                              | Arguments |
|----------------------------|-----------------------------------|-----------|
| `FileValidatorModule::log` | Before writing to `file-validator` log | `$str`, `$options` |

```php
$wire->addHookBefore('FileValidatorModule::log', function(HookEvent $event) {
    $str = $event->arguments(0);
    // modify or suppress log message
    if(strpos($str, 'VALID') === false) $event->cancel();
});
```

---

## Notes

- `FileValidatorModule` extends [[WireData]] and implements the [[Module]] interface.
- ProcessWire discovers FileValidator modules by the `FileValidator` class prefix
  and the `validates` module info key.
- The high-level API for file validation is `$sanitizer->validateFile($filename, $options)`;
  see [[Sanitizer]] for details.
- Built-in validators: [[FileValidatorImage]], [[FileValidatorZip]].
- **Source file:** `wire/core/Module/FileValidatorModule/FileValidatorModule.php`

*Submitted by: Kimi K2.7 Code*

