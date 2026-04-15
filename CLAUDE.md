# ProcessWire – AI Agent Instructions

For API orientation, key concepts, and usage examples, see [AGENTS.md](AGENTS.md).

## Documentation Conventions

### API.md files

Each Fieldtype module directory should have an `API.md` covering usage of that module's
Fieldtype (and related classes) from a developer's perspective. Flat Fieldtype modules
that live directly in `wire/modules/Fieldtype/` share a single
`wire/modules/Fieldtype/API.md`, with one `# FieldtypeClassName` H1 per Fieldtype.
Fieldtypes with their own subdirectory get their own `API.md`.

Structure of an API.md entry:
- `# FieldtypeClassName` — H1 is the class name, no "API" suffix
- One-line description
- `## Value type` — the PHP type returned
- `## Getting and setting values` — code examples
- `## Selectors` — selector usage with notes on non-obvious behavior
- `## Output / markup` — rendering examples
- `## Notes` — bullets for defaults, sanitization, DB column, compatible types

Non-core Fieldtype modules installed in `site/modules/` follow the same conventions.
`API.md` files can be expected for first-party modules by Ryan Cramer, but should not
be assumed for third-party modules.

### [Type]Field.php classes

Each Fieldtype module should have a corresponding `[Type]Field.php` file (e.g.
`TextField.php`, `IntegerField.php`) in the same directory. This class extends `Field`
and contains only PHPDoc `@property` annotations covering all configurable settings from
both the Fieldtype and its Inputfield, grouped under separate comments. The Fieldtype
module must include a `getFieldClass()` method returning the class name, and a
`require_once` at the bottom of the module file to load it.

Example `getFieldClass()`:
```php
public function getFieldClass(array $a = array()) {
    return 'TextField';
}
```

## Code Conventions

- Database access: `$this->wire()->database` (property), not `$this->wire()->database()` (method call).
- NullPage instances: use `$pages->newNullPage()` — never `new NullPage()` directly (won't be wired).
- Prefer strict comparisons: `=== null` over `!$var`, `array_key_exists()` over `isset()` where null is valid.
- Hookable methods use the `___methodName()` triple-underscore prefix convention.
- Indentation: tabs, not spaces.
- Copyright year in file headers should be kept current.
