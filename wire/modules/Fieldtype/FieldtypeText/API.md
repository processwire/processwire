# FieldtypeText

Stores a single line of plain text. 

---

## Value type

Always a string, whether blank or populated. No newlines. 

---

## Getting and setting values

```php
// Get
$page->text_field; // string
$page->get('text_field'); // string
$page->getUnformatted('text_field'); // get without Textformatters applied
$page->getFormatted('text_field'); // get with Textformatters applied

// Set 
$page->text_field = 'Hello World'; // set value
$page->text_field = '';  // set blank
$page->save('text_field'); // save
$page->setAndSave('text_field', 'Foo bar'); // set and save together
```
Note that output formatting should be OFF when saving text fields (or any 
fields for that matter):
```php
$page->of(false); // turn off output formatting
$page->text_field = 'foobar';
$page->save();
$page->of(true); // turn back on when applicable
```
The `$page->setAndSave()` does not require that output formatting is off,
so be careful not to set and save an already formatted value with it:
```php
// output formatting on and value is entity encoded
$value = $page->get('text_field'); // value='This &amp; That'
$page->setAndSave('text_field', "$value oops!"); 
echo $page->get('text_field'); // outputs corrupted value: This &amp;amp; That oops!'
```
---

## Selectors

```php
// Find pages where text equals value 'foobar'
$pages->find('text_field=foobar');

// Find pages where text_field is empty
$pages->find('text_field=""'); 

// Find pages where text contains 'foobar' 
$pages->find('text_field*=foobar'); // fulltext match
$pages->find('text_field%=foobar'); // LIKE match

// Find pages where text starts with 'foobar'
$pages->find('text_field^=foobar'); 

// Find pages where text has independent word 'foobar'
$pages->find('text_field~=foobar'); 

// Find pages where text has word 'foo' or word 'bar'
$pages->find('text_field~=foo|bar'); 

// Find pages where text has word 'foo' AND word 'bar' in the value
$pages->find('text_field~=foo, text_field~=bar'); 

// Find pages where $value originated from user input
$value = $sanitizer->selectorValue($input->get('foo')); 
$pages->find("text_field=$value"); 
```
Above are just a few examples. Many other operator usage cases are possible with 
FieldtypeText. Please see [ProcessWire Selector Operators](https://processwire.com/docs/selectors/operators/)
for details on all the operators that can be used with FieldtypeText. 

## Output / markup

When outputting a text field in HTML please make sure that the text field has the `TextformatterEntities`
or `TextformatterMarkdownExtra` Textformatter and that the accessed `$page` output formatting is ON.
Though note that output formatting is ON by default when responding to a non-admin HTTP request. 

```php
$page->of(true);  // output formatting ON (usually on by default)
echo $page->text_field; // outputs: This &amp; That

$page->of(false);
echo $page->text_field; // outputs: This & That
```

---

## Text field settings 

### textformatters

The `textformatters` setting is an array of Textformatter module class names thare applied
to the text_field value during output formatting.

```php
/** @var Field $field */
$field = $fields->get('text_field');

/** @var array $textformatters */
$textformatters = $field->get('textformatters'); 
// This example adds TextformatterEntities if not already in field setting:
if(!in_array('TextformatterEntities', $textformatters)) {
  $textformatters[] = 'TextformatterEntities';
  $field->set('textformatters', $textformatters); 
  $field->save();
}
```
In the above example `$textformatters` is an array of `Textformatter` module names.
When output formatting is ON, these Textformatter modules are applied to `text_field` value 
every time `$page->text_field` or `$page->get('text_field')` is accessed.  All of the core
Textformatter modules can be found in: `/wire/modules/Textformatter/`

### inputfieldClass
```php
/** @var Field $field */
$field = $fields->get('text_field');
// optionally tell it to use Inputfield other than InputfieldText
$field->set('inputfieldClass', 'InputfieldName'); 
```
### Other settings (via InputfieldText)

```php
/** @var Field $field */
$field = $fields->get('text_field');
$field->set('size', 30); // sets <input> size attribute
$field->set('maxlength', 128); // sets <input> maxlength attribute
$field->set('minlength', 0); // sets <input> minlength attribute
$field->set('placeholder', 'Hello world'); // sets <input> placeholder attribute
$field->set('pattern', '^[a-zA-Z0-9]*$'); // sets <input> pattern attribute
$field->set('required', true); // makes field required
$field->set('requiredAttr', 1); // makes it use HTML5 required attribute
$field->set('stripTags', true); // makes it strip tags from input
$field->set('noTrim', true); // tells it not to trim whitespace from input value
$field->set('showCount', 1); // makes input show a character counter
$field->set('showCount', 2); // makes input show a word counter
$field->save();
```
---

## Notes

- The blank/default value is an empty string. 
- Compatible fieldtypes: Any Fieldtype that extends FieldtypeText. 
- Database column: `text NOT NULL`, indexed.
