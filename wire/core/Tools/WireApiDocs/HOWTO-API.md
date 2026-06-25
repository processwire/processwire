# How to write API.md documentation files

This guide covers writing API.md documentation for ProcessWire core classes,
core modules, and third-party modules. API.md files are the primary reference
for developers and AI agents using the ProcessWire API.

---

## Where API.md files live

Every API.md file lives in the same directory as the class or module it documents:

```
wire/core/Pages/API.md              → documents the Pages class
wire/core/Field/API.md              → documents the Field class
wire/modules/Fieldtype/FieldtypeText/API.md → documents FieldtypeText module
wire/modules/Inputfield/InputfieldText/API.md → documents InputfieldText module
```

One file per class. If a directory contains multiple classes (like
`wire/core/DatabaseQuery/` with `DatabaseQuery`, `DatabaseQuerySelect`, and
`DatabaseQuerySelectFulltext`), document them all in a single API.md separated
by `---` and `#` headings.

---

## How API.md files are used

API.md files are retrieved by:

- **Agents or developers** via the `php index.php docs` CLI commands
- **Agents** via AgentTools CLI commands (`--at-engineer-api-docs-get`, `--at-engineer-api-docs-search`)
- **The `$wire->docs()` API** at runtime
- **Directly** by file (e.g. `wire/core/Fields/API.md`)

They are written in GitHub-flavored Markdown and should be clear, accurate, and
focused on the API surface — not internal implementation details.

---

## Structure

There is no rigid template — the structure should fit the class. A typical API.md
includes some combination of:

1. **Title** — "ClassName" for most, or "ClassName / $varName" (if an API var)
2. **Intro** — What is for, how to get an instance
3. **Properties** — table of properties with types and descriptions
4. **Methods** — grouped by category (retrieval, manipulation, access, etc.)
5. **Constants** — if the class defines public constants 
6. **Hooks** — hookable methods, especially for classes that are commonly hooked
7. **Notes** — usage tips, gotchas, cross-references, source file path

Not every section is needed for every class, and some classes may have additional
sections not mentioned here. A simple utility class might only need
an intro, properties, and methods. A complex core class might need all sections plus
sub-groups.

*Note the order of items 3-5 above may vary.*

---

## Writing the intro

Start with a `#` heading using the class name. Include the API variable name if there
is one. Show how to get an instance.

~~~markdown
# Pages / $pages

The Pages class (`$pages` API variable) loads, creates, saves and 
deletes Page objects to and from the database. It is the most 
frequently used API variable in ProcessWire. 

```php
$pages->find('template=blog-post, sort=-created, limit=10');
$page = $pages->get('/about/');
```
~~~

If the class is instantiated with `new ClassName()` rather than an API variable,
show that instead…

~~~markdown
# WireHttp

HTTP client for sending GET, POST, PUT, DELETE, PATCH, HEAD, 
and OPTIONS requests to URLs, downloading files, and sending 
files to the browser. Supports CURL, fopen, and socket transports 
with automatic fallback. Instantiate with `new WireHttp()`.

```php
$http = new WireHttp();
$response = $http->get('https://example.com/');
```
~~~

…Or if the class is a Module then demonstrate retrieving an instance of the module:

~~~markdown
# InputfieldText 

Single-line text input. The most commonly used Inputfield — renders an 
`<input type="text">` element and provides server-side validation for 
length, pattern, and content.

```php
$f = $modules->get('InputfieldText');
$f->name = 'first_name';
$f->label = 'First name';
$form->add($f);
```
~~~

---

## Using #pw-tags from PHPDoc

Many ProcessWire classes use special `#pw-` tags in their PHPDoc headers. These tags
group methods and properties, mark visibility, and provide summaries. They are a
valuable guide when writing API.md files.

### #pw-group-* tags

Methods or properties tagged `#pw-group-*`, i.e. `#pw-group-retrieval`, 
`#pw-group-manipulation`, `#pw-group-access`, etc. indicate a suggested grouping 
of like methods/properties, and may optionally be grouped under corresponding 
headings in the API.md. Common groups:

| Tag                       | Suggested heading      |
|---------------------------|------------------------|
| `#pw-group-retrieval`     | Retrieval              |
| `#pw-group-manipulation`  | Manipulation           |
| `#pw-group-access`        | Access control         |
| `#pw-group-flags`         | Flags                  |
| `#pw-group-settings`      | Settings               |
| `#pw-group-HTTP-requests` | HTTP requests          |

Please note: 

- A method may have multiple `#pw-group-` tags. When multiple groups are present, 
  the first is the primary group. 
- If a public method has no `#pw-group-` tag then it’s considered part of 
  the "Common" group. 

### Other method-specific tags

| Tag            | Purpose                                                |
|----------------|--------------------------------------------------------|
| `#pw-internal` | Public method that is meant for internal use only.     |
| `#pw-advanced` | Public API but more for advanced users or usage cases. |
| `#pw-hooker`   | Method intended primarily for hooks.                   |

Methods tagged `#pw-internal` are public but not intended for external use. Generally
exclude them from API.md unless there's a compelling reason an agent or developer would
need to know about them.

Methods tagged `#pw-advanced` are part of the public API but intended for advanced use
cases. Include them, but consider noting that they are advanced.

### Class-specific PHPDoc tags

Many classes in ProcessWire have a PHPDoc section above the class that documents the entire class.
Below are some of the specific tags that you might find here. 

| Tag                   | Purpose                                                                   |
|-----------------------|---------------------------------------------------------------------------|
| `#pw-headline ...`    | Indicates a proposed headline for the class                               |
| `#pw-summary`         | Class summary text for the intro                                          |
| `#pw-summary-* ...`   | Text summary for a named `#pw-group-*` tag                                |
| `#pw-body ...`        | Single-line body text for class                                           |
| `#pw-body = ...`      | Multi-line markdown body text for class that ends with another `#pw-body` |
| `#pw-use-constants`   | Indicates class constants should be documented                            |
| `#pw-use-constructor` | Indicates the constructor needs to be documented for instantiation.       |
| `#pw-instantiate`     | How the class is commonly instantiated                                    |
| `#pw-var`             | Variable name to represent class in examples (e.g. `#pw-var $pages`)      |


### Example demonstrating several #pw tags
```php
<?php namespace ProcessWire;
/**
 * Hello World
 * 
 * #pw-headline Hello World demonstration module
 * #pw-summary An example of summarizing the HelloWorld class with one line.
 * #pw-summary-retrieval Methods for getting data from the class.
 * #pw-summary-manipulation Methods for creating, saving, deleting, etc.
 * #pw-instantiate $hello = $modules->get('HelloWorld');
 * #pw-var $hello
 * 
 * #pw-body = 
 * The HelloWorld plugin is an iconic, ProcessWire CMS demonstration module. 
 * Originally designed for educational purposes, the module remains incredibly 
 * useful today for exploring module development and runtime hooks. It allows 
 * administrators to attach useful actions—such as echoing hello messages on 
 * page rendering or displaying hello notifications every time a page is saved.
 * #pw-body
 * 
 * // Class properties and hookable methods are documented like this:
 * @property string $hello Example of documenting a read/write class property.
 * @property-read $world Example of documenting a read-only class property. 
 * @method string getHello() Example of documenting a hookable method. 
 * 
 */
class HelloWorld extends WireData implements Module { 
    /**
     * Get a hello message
     * 
     * #pw-group-manipulation
     * 
     * @return string
     */
    public function ___getHello() {
        return $this->_('Hello');
    }
    // ...
}
```

---

## Documenting properties

Use a Markdown table with columns for property name, type, and description. Only
document properties that are part of the API — not internal runtime state.

```markdown
| Property   | Type        | Description              |
|------------|-------------|--------------------------|
| `id`       | `int`       | Numeric ID               |
| `name`     | `string`    | Name of the field        |
| `type`     | `Fieldtype` | Fieldtype module         |
```

For readonly properties, note it in the description or mark the type as read-only.

---

## Documenting methods

Each method gets a `###` heading with its signature, followed by a description and
examples. Keep descriptions focused on what the method does and how to use it — not
how it works internally.

~~~markdown
### get($key)

Get a setting or dynamic data property.

```php
$name = $field->get('name');
```
~~~

### What to include

- The method signature (simplified — omit type hints if they clutter)
- A concise description
- At least one code example
- Parameters and return values when not obvious
- Cross-references to related methods when helpful

### What to omit

- Internal implementation details
- Parameters that have dedicated accessor methods (e.g. if `getFieldgroups(true)`
  is just a count shortcut for `numFieldgroups()`, document `getFieldgroups()`
  without the `$getCount` parameter)
- `#pw-internal` methods (usually)

---

## Documenting hooks

Many ProcessWire classes have hookable methods (prefixed with `___` in the source).
If the class is commonly hooked, include a Hooks section with a table:

```markdown
| Hook                | When                          | Arguments              |
|---------------------|-------------------------------|------------------------|
| `Field::viewable`   | Before checking viewability   | `$page`, `$user`       |
| `Fields::saved`     | After field has been saved    | `$field`, `$changes`   |
```

Include inherited hooks if they're relevant to the class's usage. For example,
Field's API.md documents hooks from `Fields` (WireSaveableItems) because those
hooks receive a `$field` argument.

Add a brief example of demonstrating a hook in documentation:

~~~markdown
```php
/**
 * Hook after a Field being saved 
 */ 
$wire->addHookAfter('Fields::saved', function(HookEvent $event) {
    $fields = $event->object; /** @var Fields $fields */
    $field = $event->arguments(0); /** @var Field $field */
    // ...
});
```
~~~

---

## Documenting modules (Fieldtypes, Inputfields, Process modules, etc.)

Module API.md files should focus on what's unique to that module. Shared API from
parent classes should be cross-referenced rather than duplicated.

### Inputfield modules

For Inputfield modules, cross-reference the main [Inputfield API](../../core/Inputfield/API.md)
for shared API (attributes, labels, collapsed, showIf, rendering, processing). Focus on:

- Properties unique to the Inputfield type
- Validation behavior specific to the type
- Rendering differences
- Any custom methods
- Relationship with the corresponding Fieldtype (if applicable)

```markdown
For the shared Inputfield API (attributes, labels, collapsed states, showIf,
rendering, processing, etc.), see [Inputfield API](../../core/Inputfield/API.md).
```

### Fieldtype modules

Document the value type, how values are stored, selector support, output formatting,
and field settings. Cross-reference the corresponding Inputfield if applicable.

---

## Cross-references

Use relative Markdown links to reference other API.md files:

```markdown
See [InputfieldText API](../InputfieldText/API.md) for inherited properties.
See [FieldtypeText API](../../modules/Fieldtype/FieldtypeText/API.md) for details.
```

Use cross-references generously — they help agents and developers find related
documentation without duplicating content.

---

## Code examples

- Use fenced code blocks with `php` language hint
- Keep examples short and focused on one concept
- Show realistic usage, not abstract placeholders
- Test examples mentally against the actual API — wrong examples are worse than no examples
- Use `$modules->get()`, `$fields->get()`, `$pages->find()`, etc. as appropriate

---

## Notes section

End with a Notes section covering:

- How the class/module is accessed (API variable, `new`, `$modules->get()`)
- The `__toString()` behavior if notable
- Any protections or constraints (system fields, reserved names, etc.)
- Cross-references to related classes
- Source file path: `**Source file:** wire/core/Field/Field.php`

---

## Code review

When writing an API.md, also review the source code for:

- **Bugs** — incorrect logic, wrong method calls, type errors
- **PHP 8 compatibility** — deprecated patterns, null handling
- **Copyright year** — should be current year
- **PHPDoc accuracy** — do `@property` and `@return` types match actual behavior?
- **Examples in existing docs** — verify they produce the stated output

If you find bugs, fix them before committing the API.md. Document the fixes in the
commit message.

---

## Testing

Write a WireTest class (`ClassName.test.php`) alongside the API.md to verify:

- Public API methods work as documented
- Examples in the API.md produce the expected results
- Edge cases and error handling
- Properties return the correct types and values

Test files live in the same directory as the class:

```
wire/core/Field/Field.test.php
wire/modules/Inputfield/InputfieldText/InputfieldText.test.php
```

Run tests with:

```
php index.php test ClassName
php index.php test all
```

See existing `.test.php` files for patterns. Use `$this->check()` for assertions,
`$this->ok()` for success messages, and `$this->fail()` for failures. For complete
instructions on creating WireTest classes, see the
[WireTests README](../../modules/System/WireTests/README.md).

---

## Quick checklist

- [ ] Read the source code thoroughly
- [ ] Check `#pw-` tags for grouping and visibility (if class uses them)
- [ ] Verify examples produce stated output
- [ ] Cross-reference parent class API rather than duplicating
- [ ] Document hooks if the class is commonly hooked
- [ ] Check for bugs, copyright year, PHPDoc accuracy
- [ ] Write a WireTest class to verify the API
- [ ] Run `php index.php test ClassName` and `php index.php test all`
- [ ] Test on multiple installs if available (e.g. multi-language)
- [ ] Include source file path in Notes
