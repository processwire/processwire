# ProcessWire Core – AI Agent Instructions

## Repository Overview

ProcessWire is a PHP CMS/CMF created by Ryan Cramer. This is the main core repository.

- **Working branch:** `dev` (all changes go here first)
- **Main branch:** `master` (stable releases)
- **Issues repo:** `processwire/processwire-issues`
- **Feature requests repo:** `processwire/processwire-requests`

## Project Goal

A long-term goal for ProcessWire is to be the most AI-friendly open source CMS available.
ProcessWire's clean, consistent, API-first design gives it a strong foundation. Keep this
goal in mind when making API, documentation, and tooling decisions — changes that improve
discoverability and predictability for AI users are valued alongside improvements for
human developers.

## Documentation Conventions

### API.md files

Each module directory should have an `API.md` covering usage of that module's Fieldtype
(and related classes) from a developer's perspective. Flat Fieldtype modules that live
directly in `wire/modules/Fieldtype/` share a single `wire/modules/Fieldtype/API.md`,
with one `# FieldtypeClassName` H1 per Fieldtype. Fieldtypes with their own subdirectory
get their own `API.md`.

Structure of an API.md entry:
- `# FieldtypeClassName` — H1 is the class name, no "API" suffix
- One-line description
- `## Value type` — the PHP type returned
- `## Getting and setting values` — code examples
- `## Selectors` — selector usage with notes on non-obvious behavior
- `## Output / markup` — rendering examples
- `## Notes` — bullets for defaults, sanitization, DB column, compatible types

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
