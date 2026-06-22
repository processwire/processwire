# ProcessWire Test Suite

WireTests is a simple, runnable test suite for ProcessWire modules and core classes. Tests verify
that modules work as intended and as documented — covering field creation, value
storage/retrieval, output formatting, selectors, and more. Each test file corresponds
to one ProcessWire module or class, and is skipped automatically if that module is
not installed.

The module currently ships with tests focused on Fieldtype modules, but it has no
dependency on any particular module type — tests can cover Fieldtypes, Inputfields,
Process modules, core classes, or anything else in the ProcessWire API.

Designed to help core developers and module authors catch regressions, and as a
starting point for anyone who wants to contribute new tests.


## Requirements

- PHP 8.0+
- Command line access to your ProcessWire installation


## Installation

We have tested with and recommend installing this with ProcessWire's `site-blank` default
installation profile, but technically it should work with any installation profile. Note
that this module creates a template named `wire-test` and a page named `/wire-test/` so verify
that you don't already have a template/page with the same names.

## Running tests

Tests are run from the command line from your ProcessWire root directory:

```bash
# Run a single test
php index.php test FieldtypeText

# Run all tests
php index.php test all

# Run a test custom file 
php index.php test site/classes/BasicPage.test.php

# Run all tests in an external directory
php index.php test site/modules/MyModule/

# List all auto-discovered tests (command help)
php index.php
```

Test names and test file basenames match the module/class name with the `.test.php` 
extension (e.g. `FieldtypeText.test.php`, `FieldtypeOptions.test.php`). Tests for modules 
that are not installed are skipped automatically. External test files may be specified 
as absolute paths or paths relative to the ProcessWire installation root. External 
directories run all `.php` tests in that directory.

## Included tests

The core comes with 46+ tests, stored in a `ClassName.test.php` file that lives alongside 
each `ClassName.php` or `ModuleName.module` file. More tests are added regularly. 

Fieldtype tests create their own field (if not already present), add it to the `test`
template, perform read/write/selector checks, and clean up after themselves on uninstall.
Core class tests call API methods directly and verify return values.

Examples of classes that have core bundled tests:

| Test file                 | What it covers                                                                                              |
|---------------------------|-------------------------------------------------------------------------------------------------------------|
| `Modules`                 | get, install, uninstall, findByPrefix/Flag/Info, getModuleInfo, config get/save, helper classes             |
| `Notices`                 | Notice flags, duplicate handling, logging flags, visibility, formatting, rendering, movement                |
| `Pages`                   | get, find, findIDs, getRaw, findRaw, getFresh, add, new, save, clone, cache, sort, trash, restore, delete   |
| `Sanitizer`               | Text, names, numbers, booleans, URLs, arrays, HTML entities, validation, truncation, chaining               |
| `TemplateFile`            | Render variables, prepend/append order, trim, return values, halt, chdir, render stack, file failures       |
| `Users`                   | Users, roles, permissions, PagesType lookup/creation, role assignment, permission aggregation, admin theme  |
| `WireCache`               | Save/get/delete, generated values, arrays, PageArrays, expiration modes, preloading, renderFile             |
| `WireClassLoader`         | Namespace registration/removal, class maps, extensions, prefix/suffix fallback paths, file lookup           |
| `WireDatabasePDO`         | Connection access, queries, transactions, schema inspection, sanitization, info, query log, backups         |
| `WireDateTime`            | Date/strftime formatting, string parsing, relative time, elapsed time, format conversion                    |
| `WireHooks`               | Hook timing, return replacement, properties/methods, removal, priority, HookEvent data, conditional hooks   |
| `WireInput`               | GET/POST/COOKIE/whitelist input, inline sanitization, URL segments, page numbers, URLs, query strings       |
| `WireLog`                 | Save/read/delete logs, metadata, queued entries, disabled logs, pruning, FileLog backend behavior           |
| `WireMailTools`           | WireMail builder, quick send methods, PHP-style mail helpers, headers, attachments, blacklist checks        |
| `FieldtypeCheckbox`       | Boolean 0/1 storage, output formatting                                                                      |
| `FieldtypeDatetime`       | Date/time storage, PHP date strings, timestamp input, selectors                                             |
| `FieldtypeDecimal`        | Decimal storage, precision, comparison selectors                                                            |
| `FieldtypeEmail`          | Email storage, sanitization, selectors                                                                      |
| `FieldtypeFieldsetOpen`   | Fieldset open/tab/close creation, auto close-field repair, fieldgroup ordering                              |
| `FieldtypeFile`           | File upload/storage/retrieval                                                                               |
| `FieldtypeFloat`          | Float storage, precision, comparison selectors                                                              |
| `FieldtypeImage`          | Image upload/storage/retrieval                                                                              |
| `FieldtypeInteger`        | Integer storage, comparison selectors                                                                       |
| `FieldtypeOptions`        | Single/multi-select options, set by ID/title/value, selectors                                               |
| `FieldtypePage`           | Page references (single and multiple), selectors                                                            |
| `FieldtypePageTable`      | PageTable child page creation and retrieval                                                                 |
| `FieldtypeRepeater`       | Repeater item creation, value storage, retrieval                                                            |
| `FieldtypeRepeaterMatrix` | RepeaterMatrix types, item creation, retrieval                                                              |
| `FieldtypeSelector`       | Selector field storage and retrieval                                                                        |
| `FieldtypeTable`          | Table row storage, column types, retrieval                                                                  |
| `FieldtypeText`           | Text storage, textformatters, selectors                                                                     |
| `FieldtypeTextarea`       | Textarea storage, selectors                                                                                 |
| `FieldtypeToggle`         | Toggle (0/1) storage, output formatting                                                                     |
| `FieldtypeURL`            | URL storage, scheme sanitization, `noRelative` setting, selectors                                           |
| `FieldtypeCustom`         | Subfield definition file, JSON storage, rename migration, selectors                                         |
| `FieldtypeCombo`          | Typed subfields, select formatting, field config API, subfield CRUD                                         |
| `Fields`                  | Field lookup, creation, save/clone/delete, tags, type finders, usage counts, context, field helpers         |
| `Fieldgroups`             | Fieldgroup lookup, creation, membership, template usage, context, namespaces, clone/import/export           |


## Writing your own test

### File naming and location

If you are developing a module that you want to test, then the site/modules/MyModule/ directory
is a fine place. If you are developing a site or application in ProcessWire, then you may want
to create a dedicated `/tests/` directory in `/site/classes/tests/`, `/site/templates/tests/`
or another location of your choice.

The test filename should mirror the class being tested in one of the following formats
(replacing `ClassName` with the actual class name being tested):

- `ClassName.test.php` (recommended)
- `WireTest_ClassName.php`

Module tests are skipped automatically if the module is not installed, so it is safe to
include tests for optional or third-party modules. Core ProcessWire classes (such as
`Sanitizer`) are detected by class name and run without requiring a module install.

### File structure

New tests should extend the `WireTest` base class. The test class name must be
`WireTest_` followed by the test name (typically class name), regardless of which
format the test file basename uses (`Name.test.php` or `WireTest_Name.php`)

```php
<?php namespace ProcessWire;

class WireTest_MyClass extends WireTest {

    public function init() {
        // Optional setup before execute()
    }

    public function execute() {
        $a = 1;
        $b = 2;

        $this->check("1 is less than 2", true, $a < $b);

        if($a > $b) {
            $this->fail("Unexpected comparison result");
        }

        $this->ok("Custom status line");
    }

    public function finish() {
        // Optional cleanup; runs even when execute() fails
    }
}
```

The example below demonstrates the file structure for a Fieldtype test.
For more and better examples, see the numerous test files included with the core.

```php
<?php namespace ProcessWire;

class WireTest_FieldtypeMyModule extends WireTest {

    protected $name = 'my_field_name';

    public function init() {
        $page = $this->getTestPage();
        $fields = $this->wire()->fields;
        $field = $fields->get($this->name);

        // Create the field if it does not already exist
        if(!$field) {
            $field = $fields->new('FieldtypeMyModule', $this->name, 'My Field');
            $this->ok("Created field: $this->name");
        }

        // Add field to the test template if not already there
        $fieldgroup = $page->template->fieldgroup;
        if(!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
            $this->ok("Added field to fieldgroup: $fieldgroup->name");
        }
    }

    public function execute() {
        $page = $this->getTestPage();
        $pages = $this->wire()->pages;
        $name = $this->name;

        // Write a value
        $page->of(false);
        $page->set($name, 'some value');
        $page->save($name);

        // Read it back from a fresh page load
        $fresh = $pages->getFresh($page->id);
        $this->check("Value round-trip", 'some value', $fresh->get($name));

        // Test a selector
        $match = $pages->findOne("template=test, $name='some value'");
        $this->check("Selector finds test page", $page->id, $match->id);
    }
}
```

### Key conventions

| Thing                     | Convention                                                                                               |
|---------------------------|----------------------------------------------------------------------------------------------------------|
| **Test class**            | `class WireTest_TestName extends WireTest`                                                               |
| **Run test logic**        | Implement `execute()`                                                                                    |
| **Setup**                 | Implement `init()` when setup is needed                                                                  |
| **Cleanup**               | Implement `finish()`; it runs even when `execute()` fails                                                |
| **Fail**                  | `$this->fail('reason')` or throw `WireTestException('reason')`                                           |
| **Check**                 | `$this->check('description', $expected, $actual)`                                                        |
| **Pass**                  | `execute()` and `finish()` complete without throwing                                                     |
| **Status output**         | `$this->ok('message')`, `$this->li('message')`, or `wireTests()->li('message')`                          |
| **Fresh page load**       | `$this->wire()->pages->getFresh($page->id)`                                                              |
| **Output formatting off** | `$page->of(false)` before setting/saving values                                                          |
| **Field already exists**  | Check with `$this->wire()->fields->get($name)` and skip creation                                         |
| **Idempotent setup**      | Guard any one-time setup (adding options, creating child pages, etc.) so it's safe to run more than once |

### Available helpers

```php
$this->check('description', $expected, $actual); // assert strict equality by default
$this->check('description', $expected, $actual, '>='); // supported operators: ===, !==, ==, !=, <, <=, >, >=, *=, ^=, $=
$this->fail('reason');   // fail this test
$this->ok('message');    // output an OK status line
$this->li('message');    // output a status line

wireTests()->li('message');   // legacy/global helper
wireTests()->note('message'); // output a plain note
```

### Running custom tests from the command line

To run a custom test use the following command from your ProcessWire installation
root directory:

```
# Run just the MyModule.test.php
php index.php test site/modules/MyModule/MyModule.test.php

# Run all tests in the tests/ directory or directories below it
php index.php test tests/*
```

### Tips

- **Make tests idempotent.** A test may run many times against the same database.
  Skip setup steps (field creation, option population) if they already exist.
- **Restore state when you change field settings.** If a test modifies a field setting
  (e.g. `$field->noRelative = 1`), restore the original value before the test ends.
- **Test the documented API, not just the happy path.** Include edge cases like empty
  values, sanitization, and selector operators.
- **Use `$page->of(false)` before modifying and saving.** Output formatting should be off when
  writing values, and explicitly turned on when testing formatted output.


## How the test runner works

1. `runTests()` iterates every `.test.php` file it finds in the installation. 
2. Tests for optional modules are skipped when the module is not installed. Core class tests
   run when the core class exists.
3. The runner includes the test file inside a `try/catch` block.
4. If the file defines `WireTest_TestName`, the runner creates it, calls `allow()`,
   `init()`, `execute()`, and then `finish()`.
5. `finish()` is called even when `execute()` fails, so it is the right place for cleanup.
6. `WireTestException` → test fails (message shown). Any other `Throwable` → test fails.
7. A summary line is printed at the end showing passed/failed counts.

