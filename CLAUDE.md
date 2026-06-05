# ProcessWire – AI Agent Instructions

For API orientation, key concepts, and usage examples, see [AGENTS.md](AGENTS.md).

## Documentation

`API.md` files document developer- and agent-facing API usage for core classes and modules.
They live alongside the code they document:

- Core classes: `wire/core/[ClassName]/API.md`
- Fieldtype modules: `wire/modules/Fieldtype/[FieldtypeName]/API.md`
- Other modules: `wire/modules/[ModuleName]/API.md` or `wire/modules/[ModuleType]/[ModuleName]/API.md`
- Non-core modules in `site/modules/`: same pattern

Most Fieldtype modules also have a `[Type]Field.php` companion class (e.g. `TextField.php`,
`ImageField.php`) with `@property` PHPDoc annotations for all configurable field settings.

## Code Conventions

- Database access: `$this->wire()->database` (property), not `$this->wire()->database()` (method call).
- NullPage instances: use `$pages->newNullPage()` — never `new NullPage()` directly (won't be wired).
- Prefer strict comparisons: `=== null` over `!$var`, `array_key_exists()` over `isset()` where null is valid.
- Hookable methods use the `___methodName()` triple-underscore prefix convention.
- Indentation: tabs, not spaces.
- Copyright year in file headers should be kept current.
- ProcessWire coding style guide: <https://processwire.com/docs/more/coding-style-guide/>
