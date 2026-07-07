# WireTests / WireTest

`WireTests` is the ProcessWire test runner module. `WireTest` is the base class that
individual test classes extend.

Run tests from the command line at the ProcessWire root:

```bash
php index.php test ClassName                            # Run one test by name
php index.php test all                                  # Run all discovered tests
php index.php test ClassName --json                     # JSON output for CI/agents
php index.php test site/modules/MyModule/MyTest.test.php  # Specific external file
php index.php test site/modules/MyModule/               # All tests in a directory
```

## Writing a test

For auto-discovery, create `ClassName.test.php` in the same directory as the class
being tested. Define a class named `WireTest_ClassName` that extends `WireTest`:

```php
<?php namespace ProcessWire;

class WireTest_MyClass extends WireTest {

    public function init() {
        // Optional: setup before execute()
    }

    public function execute() {
        $obj = new MyClass();
        $this->check('greet() returns expected string', 'hello', $obj->greet());
        $this->check('count() is positive', true, $obj->count() > 0);
    }

    public function finish() {
        // Optional: cleanup; runs even if execute() throws
    }
}
```

## WireTest lifecycle methods

| Method        | When called                              | Notes                                           |
|---------------|------------------------------------------|-------------------------------------------------|
| `allow()`     | Before `init()` — return `false` to skip | Override to check version or feature support    |
| `init()`      | Before `execute()`                       | Setup: create fields, fixtures, etc.            |
| `execute()`   | Main test body                           | Run assertions with `check()`, `ok()`, `fail()` |
| `finish()`    | After `execute()`, even on failure       | Restore settings, remove temporary state        |
| `uninstall()` | When WireTests module is uninstalled     | Permanent teardown: delete created fields/pages |

## Assertion methods

```php
// Strict equality (default operator is ===)
$this->check('test name', $expected, $actual);

// Specify an operator
$this->check('test name', $expected, $actual, $operator);
```

Supported operators:

| Operator | Meaning                       |
|----------|-------------------------------|
| `===`    | Strict equality (default)     |
| `!==`    | Strict inequality             |
| `==`     | Loose equality                |
| `!=`     | Loose inequality              |
| `<`      | `$expected < $actual`         |
| `<=`     | `$expected <= $actual`        |
| `>`      | `$expected > $actual`         |
| `>=`     | `$expected >= $actual`        |
| `*=`     | `$actual` contains `$expected`|
| `^=`     | `$actual` starts with `$expected` |
| `$=`     | `$actual` ends with `$expected`   |

On failure, `check()` throws `WireTestException` with a message showing expected vs. actual.

```php
$this->check('value is hello', 'hello', $result);             // ===
$this->check('output contains keyword', 'error', $log, '*='); // contains
$this->check('count is positive', 0, $count, '<');            // 0 < $count
```

## Output methods

```php
$this->ok('message');    // Output an OK status line
$this->fail('reason');   // Fail the test (throws WireTestException)
$this->li('message');    // Output a plain status line (no pass/fail implied)
```

## Other helpers

```php
$page = $this->getTestPage();                        // Shared /wire-test/ page
$fresh = $this->wire()->pages->getFresh($page->id);  // Reload page from database
$page->of(false);                                    // Turn off output formatting before saving
```

## Fieldtype test pattern

Tests that store and retrieve field values should use `getTestPage()`, create the field
in `init()`, and restore any modified field settings in `finish()`:

```php
<?php namespace ProcessWire;

class WireTest_FieldtypeMyModule extends WireTest {

    protected $name = 'wire_test_myfield';

    public function init() {
        $page    = $this->getTestPage();
        $fields  = $this->wire()->fields;
        $field   = $fields->get($this->name);
        if(!$field) {
            $field = $fields->new('FieldtypeMyModule', $this->name, 'My Field');
            $this->ok("Created field: $this->name");
        }
        $fieldgroup = $page->template->fieldgroup;
        if(!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
        }
    }

    public function execute() {
        $page  = $this->getTestPage();
        $pages = $this->wire()->pages;
        $name  = $this->name;

        $page->of(false);
        $page->set($name, 'test value');
        $page->save($name);

        $fresh = $pages->getFresh($page->id);
        $this->check('value round-trip', 'test value', $fresh->get($name));

        $match = $pages->findOne("template=wire-test, $name='test value'");
        $this->check('selector finds test page', $page->id, $match->id);
    }
}
```

## Notes

- Test files are auto-discovered in `wire/core/`, `wire/modules/`, and `site/`. Matching
  `ClassName.test.php` or `WireTest_ClassName.php` files are found automatically when
  they contain a `WireTest_ClassName` class.
- Direct file paths may point to any file that defines a `WireTest_*` class; directory
  paths are searched recursively, subject to WireTests' built-in exclusions.
- If multiple tests have the same name, run the desired test by path rather than name.
- Tests for uninstalled modules are skipped automatically unless the target class exists
  as a core/PHP class (for example, `Sanitizer`).
- Field names in Fieldtype tests should use `WireTests::fieldPrefix` (`wire_test_`) to
  avoid collisions.
- Make tests idempotent: guard field/option creation so it's safe to run more than once.
- `finish()` is always called, even when `execute()` throws — use it for cleanup.
- Append `--json` to any run for machine-readable output suitable for CI pipelines.
- For comprehensive guide on installation, discovery, best practices, and examples, see
  the [README](README.md).
- **Source files:** `wire/modules/System/WireTests/WireTests.module.php`,
  `wire/modules/System/WireTests/WireTest.php`
