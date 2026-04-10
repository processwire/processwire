# FieldtypeOptions

Field that stores one or more selected options from a predefined list. Options have a numeric ID,
a display title, and an optional text value. The input type (single vs. multi-select) is determined
by the Inputfield class configured on the field.

## Value type

Always a `SelectableOptionArray` (extends `WireArray`), containing zero or more `SelectableOption`
objects. When the Inputfield only accepts a single selection, the array will contain at most one item,
and properties can be accessed directly on the array (proxied to the first item).

## Getting and setting values

```php
// Single-select field — access properties directly on the array
echo $page->color->title;  // 'Red'
echo $page->color->value;  // optional text value, e.g. '#ff0000'
echo $page->color->id;     // numeric ID, e.g. 3

// Check if any option is selected
if($page->color->count()) { ... }
if($page->color->id) { ... }

// Multi-select field — iterate the array
foreach($page->colors as $option) {
    echo "<li>$option->title</li>";
}

// Alternate syntax for above
echo $page->colors->each('<li>{title}</li>'); 

// Check if option is selected (following 3 lines are equivalent)
if($page->colors->hasID(3)) { /* Red is selected */ }
if($page->colors->hasValue('#ff0000')) { /* Red is selected */ }
if($page->colors->hasTitle('Red')) { /* Red is selected */ }

// Get option if selected (following 3 lines are equivalent)
$option = $page->colors->getByID(3); 
$option = $page->colors->getByValue('#ff0000'); 
$option = $page->colors->getByTitle('Red'); 
if($option !== null) {
    echo "$option->title is selected"; // i.e. Red is selected
}

// Set a single option by title (exact match)
$page->color = 'Red';

// Set a single option by numeric ID
$page->color = 3;

// Set a single option by option value
$page->color = '#ff0000';

// Set multiple options by array of IDs (multi-select)
$page->colors = [3, 5, 7];

// Set multiple options by pipe-separated IDs
$page->colors = '3|5|7';

// Add an option to the current selection (output formatting must be off)
// The following 3 lines are equivalent
$page->colors->addByID(3); // add by option ID
$page->colors->addByValue('#ff0000'); // add by option value
$page->colors->addByTitle('Red'); // add by option title
$page->save('colors');

// Remove an option from the current selection
// The following 3 lines are equivalent
$page->colors->removeByID(3);
$page->colors->removeByValue('#ff0000');
$page->colors->removeByTitle('Red');
$page->save('colors');
```

## SelectableOption properties

Each item in a `SelectableOptionArray` is a `SelectableOption` object:

```php
$option->id      // Permanent numeric ID assigned when option was created
$option->title   // Display label (HTML-entity-encoded when output formatting is on)
$option->value   // Optional text value, separate from title (may be empty)
$option->sort    // Sort position
```

## SelectableOptionArray methods

```php
$options->count()            // Number of selected options
$options->first()            // First SelectableOption, or false if empty
$options->last()             // Last SelectableOption, or false if empty
$options->addByID($id)       // Add option by numeric ID
$options->addByTitle($title) // Add option by title
$options->addByValue($value) // Add option by value string
$options->getByID($id)       // Get option by ID, or null
$options->getByTitle($title) // Get option by title, or null
$options->getByValue($value) // Get option by value string, or null
$options->removeByID($id)    // Remove option by ID, returns bool
$options->removeByTitle($t)  // Remove option by title, returns bool
$options->removeByValue($v)  // Remove option by value, returns bool
$options->hasID($id)         // Does selection contain this ID? Returns SelectableOption or false
$options->hasTitle($title)   // Does selection contain this title?
$options->hasValue($value)   // Does selection contain this value?
$options->implode('|', 'title') // Join titles into a string
$options->render()           // Render as <ul><li> list (output formatting on)
```

Cast to string returns pipe-separated IDs: `echo $page->colors; // '3|5|7'`

## Selectors

```php
// Pages where a color option is selected (any)
$pages->find('color.count>0');

// Pages with a specific option by title (or value if title not matched)
$pages->find('color=Red');

// Match by option title explicitly
$pages->find('color.title=Red');

// Match by option value text
$pages->find('color.value="#ff0000"');

// Match by option ID
$pages->find('color.id=3');

// Pages NOT having a specific option
$pages->find('color!=Red');

// Pages with any of several options selected
$pages->find('color=Red|Blue|Green');
```

Usable subfields: `data` (option ID), `title`, `value`, `id`, `count`.

## Output / markup

```php
// Render selected options as a comma-separated list of titles
echo $page->colors->implode(', ', 'title');

// Render with links using each option's value as a URL
foreach($page->colors as $option) {
    echo "<a href='$option->value'>$option->title</a> ";
}

// Render all options as an HTML list
echo $page->colors->render(); // <ul><li>...</li></ul>

// Single-select: output just the title
echo $page->color->title;
```

## Managing options via API

The available options for a field (the defined list of choices, as opposed to the selected values on
a page) are managed directly on the `OptionsField` object. All write operations should be followed
by `$field->save()`.

```php
/** @var OptionsField $field */
$field = $fields->get('color');

// Get all available options as a SelectableOptionArray
$allOptions = $field->getOptions();
foreach($allOptions as $option) {
    echo "$option->id: $option->title ($option->value)\n";
}

// Get the options definition string 
// (useful for reading, creating, updating or cloning)
echo $field->getOptionsString();
// Outputs something like:
// 1=Red
// 2=#00ff00|Green
// 3=Blue

// Set/replace options using a definition string
// Format: one option per line as 'title' (new) or 'id=title' (existing) or 'id=value|title'
$field->setOptionsString("1=Red\n2=#00ff00|Green\n3=Blue\nYellow");
$field->save();

// Add new options manually
$newOptions = $field->newSelectableOptionArray();
$opt = $field->newSelectableOption();
$opt->title = 'Purple';
$opt->value = '#800080';
$newOptions->add($opt);
$field->addOptions($newOptions);
$field->save();

// Update existing options
$options = $field->getOptions();
$red = $options->getByTitle('Red');
if($red) {
    $red->value = '#ff0000';
    $field->updateOptions($options);
    $field->save();
}

// Delete specific options
$options = $field->getOptions(['title' => 'Yellow']);
$field->deleteOptions($options);
$field->save();

// Delete all options for the field
$field->deleteAllOptions();
$field->save();
```

## Notes

- Options are stored in a separate `fieldtype_options` database table, not in the page field table.
- Each option receives a permanent numeric `id` when first saved — IDs never change, even if the title is edited.
- The `title` is the display label; the `value` is an optional separate text string (useful for slugs, color codes, etc.).
- Both `title` and `value` support multi-language if LanguageSupport is installed.
- The Inputfield class determines whether one or multiple options can be selected: `InputfieldSelect` and `InputfieldRadios` are single-select; `InputfieldCheckboxes`, `InputfieldSelectMultiple`, `InputfieldAsmSelect`, and `InputfieldTextTags` are multi-select.
- `InputfieldAsmSelect` and `InputfieldTextTags` additionally supports user-sortable selections.
- Selector matching on the default subfield (`color=Red`) checks both `title` and `value`, matching either.