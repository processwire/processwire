# Templates / $templates

`$templates` is the API variable that manages all `Template` instances. A `Template`
connects a set of fields (via a `Fieldgroup`) to pages, determines how those pages are
rendered (via a template file), and controls URL behavior and access.

`$templates` is accessible in templates as `$templates` or `wire()->templates`; and in
modules as `$this->wire()->templates`.

---

## Getting templates

### $templates->get($name)

Get a template by name or ID.

- **Arguments:** `get(string|int $name)`
- **Returns:** `Template|null`

~~~~~php
$template = $templates->get('basic-page');
$template = $templates->get(12); // by ID
~~~~~

`$templates` is also directly iterable:

~~~~~php
foreach($templates as $template) {
    echo "$template->name (" . $template->getNumPages() . " pages)\n";
}
~~~~~

---

### $templates->getFresh($key)

Load and return a fresh `Template` instance directly from the database, bypassing any
in-memory cache. The returned template is not added to the `$templates` collection.

Useful when you need to verify the current database state of a template without affecting
the cached instance, or when another process may have modified the template.

- **Arguments:** `getFresh(int|string $key)` — template ID or name
- **Returns:** `Template|null`

~~~~~php
$template = $templates->getFresh('basic-page');  // by name
$template = $templates->getFresh(2);             // by ID
~~~~~

---

### $templates->getRaw($key)

Get the raw database row for a template as an associative array, bypassing any cache.
The `data` column is returned as the raw encoded JSON string (not decoded).

- **Arguments:** `getRaw(int|string $key)` — template ID or name
- **Returns:** `array|null`

~~~~~php
$row = $templates->getRaw('basic-page');
// ['id' => 2, 'name' => 'basic-page', 'fieldgroups_id' => 5, 'data' => '{...}', ...]
~~~~~

---

## Creating templates

### $templates->add($name, $properties)

Add and save a new template (and its fieldgroup) with the given name.

- **Arguments:** `add(string $name, array $properties = [])`
- **Returns:** `Template`
- Throws `WireException` if the name is invalid or a template with that name already exists.

~~~~~php
$template = $templates->add('product');

// Add with initial properties
$template = $templates->add('event', [
    'label' => 'Event',
    'urlSegments' => 1,
]);
~~~~~

---

### $templates->new($name, $settings)

Create, save, and return a new Template and Fieldgroup. Available since 3.0.258.

- **Arguments:** `new(string|array $name, array|string $settings = [])`
- **Returns:** `Template`
- Throws `WireException` if the name already exists.
- `$settings` may be a string (used as label) or an array of template property settings.
- `$settings` may have a `fields` property of field names, IDs, or Field objects to add
  when a new matching fieldgroup is created.
- `$name` may alternatively be an array of settings including a `name` key.

~~~~~php
// Create with just a name
$template = $templates->new('product');

// Create with a label string
$template = $templates->new('product', 'Product page');

// Create with an array of settings
$template = $templates->new('product', [
    'label' => 'Product page', 
    'fields' => [ 'title', 'body', 'price' ], 
]);

// Name and settings in a single array
$template = $templates->new([
    'name' => 'product', 
    'label' => 'Product page',
    'fields' => [ 'title', 'body', 'price' ], 
]);
~~~~~

---

### $templates->newTemplate($name, $settings)

Create a new Template in memory without saving it. Available since 3.0.258.

- **Arguments:** `newTemplate(string|array $name, array|string $settings = [])`
- **Returns:** `Template` (unsaved)
- Accepts the same arguments as `new()`.

~~~~~php
$template = $templates->newTemplate('product', 'Product page');
$template->fieldgroup->add('title');
$template->fieldgroup->save();
$template->save();
~~~~~

When using the `fields` setting, those fields are added only when ProcessWire creates a
new matching fieldgroup for the template. If a fieldgroup with the template name already
exists, the template uses that existing fieldgroup as-is.

---

### $templates->save($template)

Save a template to the database.

- **Arguments:** `save(Template $template)`
- **Returns:** `bool`
- This is a hookable method (`___save()`).

~~~~~php
$templates->save($template);
// or equivalently:
$template->save();
~~~~~

---

## Deleting and cloning templates

### $templates->delete($template)

Delete a template. Throws a `WireException` if the template is a system template or is
in use by any pages. Also deletes the template's fieldgroup if no other template uses it.

- **Arguments:** `delete(Template $template)`
- **Returns:** `bool`
- This is a hookable method (`___delete()`).

~~~~~php
if(!$template->getNumPages()) {
    $templates->delete($template);
}
~~~~~

---

### $templates->clone($template, $name)

Clone a template, including its fieldgroup (if it shares the template's name) and
template PHP file (if it exists and the path is writable).

- **Arguments:** `clone(Template $template, string $name = '')`
- **Returns:** `Template|false`
- This is a hookable method (`___clone()`).

~~~~~php
$original = $templates->get('basic-page');
$copy = $templates->clone($original, 'basic-page-2');
~~~~~

---

## Renaming templates

### $templates->rename($template, $name)

Rename a template and, when possible, its fieldgroup and template file.

- **Arguments:** `rename(Template $template, string $name)`
- **Returns:** `void`
- Throws `WireException` if the new name is invalid or already in use.
- The fieldgroup is renamed when it shares the template's current name.
- The template file is renamed when it is readable, writable, and named after the template.
- Available since 3.0.170.

~~~~~php
$template = $templates->get('old-name');
$templates->rename($template, 'new-name');
~~~~~

---

## Tags

### $templates->getTags($getTemplateNames)

Get all tags used across all templates, sorted alphabetically.

- **Arguments:** `getTags(bool $getTemplateNames = false)`
- **Returns:** `array` — by default `[tagName => tagName, ...]`; with `$getTemplateNames = true`, `[tagName => [templateName => templateName, ...], ...]`
- Available since 3.0.176 (hookable since 3.0.179).
- This is a hookable method (`___getTags()`).

~~~~~php
// Get all tag names
foreach($templates->getTags() as $tag) {
    echo $tag . "\n";
}

// Get template names grouped by tag
foreach($templates->getTags(true) as $tag => $templateNames) {
    echo "$tag: " . implode(', ', $templateNames) . "\n";
}
~~~~~

---

## Family and page class utilities

### $templates->getParentPage($template, $checkAccess)

Return the parent page that a template assumes new pages are added to, based on family
settings.

- **Arguments:** `getParentPage(Template $template, bool $checkAccess = false)`
- **Returns:** `Page|NullPage|null` — `null` if no parent defined; `NullPage` if multiple parents match; `Page` if exactly one parent matches.

~~~~~php
$parent = $templates->getParentPage($template);
if($parent && $parent->id) {
    echo "Default parent: $parent->path";
}
~~~~~

The same result is available directly from the Template object:

~~~~~php
$parent = $template->getParentPage();
~~~~~

---

### $templates->getParentPages($template, $checkAccess)

Return all defined parent pages for the given template.

- **Arguments:** `getParentPages(Template $template, bool $checkAccess = false)`
- **Returns:** `PageArray`

~~~~~php
$parents = $templates->getParentPages($template);
foreach($parents as $parent) {
    echo $parent->path . "\n";
}
~~~~~

---

### $templates->getPageClass($template, $withNamespace)

Get the PHP class name used for pages with the given template. This may differ from
`$template->pageClass` when a custom page class is auto-detected at runtime — for
example, a class named `BlogPostPage` for a template named `blog-post`.

- **Arguments:** `getPageClass(Template $template, bool $withNamespace = true)`
- **Returns:** `string` — class name, with namespace by default
- Available since 3.0.152.

~~~~~php
$class = $templates->getPageClass($template);
// e.g. "ProcessWire\BlogPostPage"

$shortClass = $templates->getPageClass($template, false);
// e.g. "BlogPostPage"
~~~~~

---

### $templates->getNumPages($template)

Return the number of pages using the given template.

- **Arguments:** `getNumPages(Template $template)`
- **Returns:** `int`

~~~~~php
$count = $templates->getNumPages($template);
echo "Pages using $template->name: $count";
~~~~~

The same value is available directly from the Template object:

~~~~~php
$count = $template->getNumPages();
~~~~~

---

## Import and export

### $templates->getExportData($template)

Export a template's data as a portable array suitable for storage or transfer.

- **Arguments:** `getExportData(Template $template)`
- **Returns:** `array`
- Role IDs, fieldgroup IDs, and related template IDs are converted to names for portability.
- This is a hookable method (`___getExportData()`).
- Marked `#pw-advanced`.

~~~~~php
$data = $templates->getExportData($template);
file_put_contents('template-export.json', json_encode($data, JSON_PRETTY_PRINT));
~~~~~

---

### $templates->setImportData($template, $data)

Import template data from an export array (as produced by `getExportData()`). Returns an
array describing what changed and any errors encountered, without saving — you must call
`$template->save()` afterward to commit the import.

- **Arguments:** `setImportData(Template $template, array $data)`
- **Returns:** `array` — associative array of `[propertyName => ['old' => ..., 'new' => ..., 'error' => ...]]`
- This is a hookable method (`___setImportData()`).
- Marked `#pw-advanced`.

~~~~~php
$data = json_decode(file_get_contents('template-export.json'), true);
$changes = $templates->setImportData($template, $data);

foreach($changes as $property => $info) {
    if($info['error']) {
        echo "Error on $property: " . implode(', ', (array) $info['error']) . "\n";
    } else {
        echo "$property: $info[old] → $info[new]\n";
    }
}

if(!count(array_filter(array_column($changes, 'error')))) {
    $template->save();
}
~~~~~

---

## Hooks

### $templates->fileModified($template)

Hookable method called when a template detects that its PHP file has been modified (based
on file modification time). This is not checked on every request — it fires only when
something in the system asks for the template's filename (e.g. during a page render).

- **Arguments:** `fileModified(Template $template)`
- **Returns:** `void`
- This is a hookable method (`___fileModified()`).
- Marked `#pw-hooker`.

~~~~~php
$templates->addHookAfter('fileModified', function(HookEvent $event) {
    $template = $event->arguments(0);
    // react to template file change, e.g. clear a custom cache
});
~~~~~

---

## Notes

- Source files: `wire/core/Templates/Templates.php` (the `$templates` manager) and
  `wire/core/Template/Template.php` (the Template object).
- Deleting a template also deletes its fieldgroup if no other template references it.
- Cloning a template also clones the fieldgroup (when they share a name) and copies the
  template PHP file if readable and writable.
- Renaming a template also renames its fieldgroup (when they share a name) and renames
  the template PHP file when it is readable, writable, and the new filename is available.
- System templates (`Template::flagSystem`) cannot be deleted or have their `id`/`name`
  changed. Use `Template::flagSystemOverride` to override (see Template constants).
