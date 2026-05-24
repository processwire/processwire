<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $sanitizer API variable
 *
 */
class WireTest_Sanitizer extends WireTest {
	
	public function execute() {
		$t = $this;
		$s = $t->wire()->sanitizer;
		
		$t->check("text() strips tags and replaces newlines with space", 'Hello World Newline', $s->text("Hello <b>World</b>\nNewline"));
		$t->check("text() default maxLength=255 enforced", 255, strlen($s->text(str_repeat('x', 300))));
		$t->check("text() custom maxLength option", 'Hello', $s->text("Hello World", ['maxLength' => 5]));
		
		$r = $s->textarea("Line 1\nLine 2<b>bold</b>");
		$t->check("textarea() preserves newlines", true, strpos($r, "\n") !== false);
		$t->check("textarea() strips tags", false, strpos($r, '<b>') !== false);
		
		$t->check("line() has no built-in max length", 500, strlen($s->line(str_repeat('x', 500))));
		$t->check("line() respects explicit maxLength argument", 100, strlen($s->line(str_repeat('x', 500), 100)));
		$t->check("lines() preserves newlines", true, strpos($s->lines("Line 1\nLine 2", 0), "\n") !== false);
		
		// ===== NAMES AND IDENTIFIERS =====
		
		$t->check("name() replaces invalid chars with underscore, keeps hyphen", 'Foo_Bar_Baz-123', $s->name("Foo+Bar Baz-123"));
		$t->check("fieldName() replaces spaces with underscore", 'Hello_World', $s->fieldName("Hello World"));
		$t->check("fieldName() replaces hyphens with underscore (no hyphens allowed)", 'hello_world', $s->fieldName("hello-world"));
		$t->check("pageName() beautified: lowercase, spaces to hyphens, strips punctuation", 'hello-world', $s->pageName("Hello World!", true));
		$t->check("pageName() always lowercases", 'hello', $s->pageName("HELLO"));
		$t->check("fieldSubfield() default limit=1 returns 'a.b'", 'a.b', $s->fieldSubfield('a.b.c'));
		$t->check("fieldSubfield() limit=2 returns 'a.b.c'", 'a.b.c', $s->fieldSubfield('a.b.c', 2));
		$t->check("fieldSubfield() limit=0 returns field only 'a'", 'a', $s->fieldSubfield('a.b.c', 0));
		$t->check("templateName() strips periods and punctuation", 'Hello-World', $s->templateName('Hello.World!', true));
		$t->check("pageNameTranslate() transliterates non-ASCII letters", 'hello-world', $s->pageNameTranslate('Héllo Wörld'));
		$t->check("pageNameUTF8() sanitizes ASCII page names", 'hello-world-', $s->pageNameUTF8('Hello World!'));
		$t->check("pagePathName() sanitizes each path segment", '/products/blue-widget', $s->pagePathName('/Products/Blue Widget!', true));
		$t->check("attrName() sanitizes HTML attribute names", 'data-Foo-', $s->attrName('data Foo!'));
		$t->check("htmlClass() sanitizes one CSS class", 'foo@bar-', $s->htmlClass('foo@bar!'));
		$t->check("htmlClasses() removes duplicates and sanitizes class list", 'foo bar bad-', $s->htmlClasses('foo bar foo bad!'));
		$t->check("htmlClasses(getArray=true) returns array", ['foo', 'bar'], $s->htmlClasses('foo bar foo', true));
		
		$r = $s->filename("©My File.jpg");
		$t->check("filename() strips non-ASCII", false, strpos($r, '©') !== false);
		$t->check("filename() keeps extension", true, str_ends_with($r, '.jpg'));
		
		// ===== CHARACTER FILTERING =====
		
		$t->check("alpha() keeps only a-zA-Z", 'HelloWorld', $s->alpha("Hello 123 World!"));
		$t->check("alphanumeric() keeps a-zA-Z and 0-9", 'Hello123World', $s->alphanumeric("Hello 123 World!"));
		$t->check("digits() keeps 0-9 only", '8005551234', $s->digits("(800) 555-1234"));
		$t->check("chars() keeps only characters in the allow list", '1baraz', $s->chars('foo123barBaz456', 'barz1'));
		$t->check("chars() [digit] alias with collapse and trim", '800.555.1234', $s->chars('(800) 555-1234', '[digit]', '.'));
		$t->check("chars() [alpha] alias with collapse and trim", 'Decatur-GA', $s->chars('Decatur, GA 30030', '[alpha]', '-'));
		$t->check("word() returns first word only", 'hello', $s->word("hello world"));
		$t->check("word() separator option joins all words", 'hello-world', $s->word("hello world", ['separator' => '-']));
		$t->check("words() strips markup and returns space-separated words", 'Hello World', $s->words('<p>Hello <em>World</em>!</p>'));
		
		// ===== CASE CONVERSION =====
		
		$t->check("hyphenCase() = 'hello-world'", 'hello-world', $s->hyphenCase('Hello World'));
		$t->check("kebabCase() = 'hello-world' (alias of hyphenCase)", 'hello-world', $s->kebabCase('Hello World'));
		$t->check("snakeCase() = 'hello_world'", 'hello_world', $s->snakeCase('Hello World'));
		$t->check("camelCase() = 'helloWorld'", 'helloWorld', $s->camelCase('Hello World'));
		$t->check("pascalCase() = 'HelloWorld'", 'HelloWorld', $s->pascalCase('Hello World'));
		
		// ===== HTML AND ENTITIES =====
		
		$t->check("entities() encodes tags, quotes, and ampersands",
			'&lt;b&gt;Hello&lt;/b&gt; &quot;World&quot; &amp; more',
			$s->entities('<b>Hello</b> "World" & more'));
		
		$t->check("entities1() does not double-encode existing entities", '&lt;b&gt; &amp; test', $s->entities1('&lt;b&gt; &amp; test'));
		$t->check("entities() does double-encode (contrast with entities1)", true, strpos($s->entities('&lt;b&gt;'), '&amp;lt;') !== false);
		$t->check("entitiesA() encodes strings in nested arrays", ['a' => '&lt;b&gt;', 'b' => ['&amp;']], $s->entitiesA(['a' => '<b>', 'b' => ['&']]));
		$t->check("entitiesA1() does not double-encode strings in arrays", ['a' => '&lt;b&gt;'], $s->entitiesA1(['a' => '&lt;b&gt;']));
		$t->check("unentities() decodes HTML entities", '<b>Hello</b>', $s->unentities('&lt;b&gt;Hello&lt;/b&gt;'));
		
		$r = $s->entitiesMarkdown('**bold** and *em* and `code`');
		$t->check("entitiesMarkdown() converts **bold**", '<strong>bold</strong>', $r, '*=');
		$t->check("entitiesMarkdown() converts *em*", '<em>em</em>', $r, '*=');
		$t->check("entitiesMarkdown() converts backtick code", '<code>code</code>', $r, '*=');
		
		$r = $s->markupToText('<p>Hello <strong>World</strong></p>');
		$t->check("markupToText() strips tags", false, strpos($r, '<') !== false);
		$t->check("markupToText() keeps text content", 'Hello', $r, '*=');
		$t->check("markupToLine() converts markup to one line", 'HelloWorld', $s->markupToLine('<p>Hello</p><p>World</p>'));
		
		// ===== NUMBERS =====
		
		$t->check("int() returns integer", 42, $s->int('42'));
		$t->check("int() default min=0 clamps negative to 0", 0, $s->int(-5));
		$t->check("int() clamps value to max", 100, $s->int(200, ['max' => 100]));
		$t->check("int() clamps value to min", 10, $s->int(5, ['min' => 10]));
		$t->check("intUnsigned() clamps negative to 0", 0, $s->intUnsigned(-5));
		$t->check("intSigned() allows negative values", -42, $s->intSigned(-42));
		$t->check("float() parses comma-formatted numbers", 1234.56, $s->float('1,234.56'));
		$t->check("float() precision option rounds to 2 decimal places", 3.14, $s->float('3.14159', ['precision' => 2]));
		$t->check("float() max option clamps value", 10.0, $s->float('12.5', ['max' => 10.0]));
		$t->check("float() blankValue option handles blank input", null, $s->float('', ['blankValue' => null]));
		$t->check("range() clamps over-max value to max", 100, $s->range(150, 0, 100));
		$t->check("range() clamps under-min value to min", 0, $s->range(-10, 0, 100));
		$t->check("range() returns float when bounds are float", true, is_float($s->range(0.5, 0.0, 1.0)));
		$t->check("min() returns minimum when value is below it", 10, $s->min(5, 10));
		$t->check("min() returns value when value is above minimum", 15, $s->min(15, 10));
		$t->check("max() returns maximum when value exceeds it", 100, $s->max(150, 100));
		$t->check("max() returns value when value is below maximum", 50, $s->max(50, 100));
		
		// ===== BOOLEANS =====
		
		$t->check("bool('false') === false", false, $s->bool('false'));
		$t->check("bool('0') === false", false, $s->bool('0'));
		$t->check("bool('') === false", false, $s->bool(''));
		$t->check("bool('1') === true", true, $s->bool('1'));
		$t->check("bool('true') === true", true, $s->bool('true'));
		$t->check("bool('yes') === true (any non-empty non-false string)", true, $s->bool('yes'));
		$t->check("bool([]) === false (empty array)", false, $s->bool([]));
		$t->check("bool(['a']) === true (non-empty array)", true, $s->bool(['a']));
		$t->check("bit('1') returns int 1", 1, $s->bit('1'));
		$t->check("bit('0') returns int 0", 0, $s->bit('0'));
		$t->check("checkbox(1) returns true", true, $s->checkbox(1));
		$t->check("checkbox(0) returns false", false, $s->checkbox(0));
		$t->check("checkbox('', false, true) returns \$no value", true, $s->checkbox('', false, true));
		$t->check("checkbox(1, 'yes', 'no') returns 'yes'", 'yes', $s->checkbox(1, 'yes', 'no'));
		
		// ===== URL AND EMAIL =====
		
		$t->check("url() accepts valid https URL", 'https://processwire.com/', $s->url('https://processwire.com/'));
		$t->check("url() allows relative paths by default", '/path/to/page', $s->url('/path/to/page'));
		$t->check("url() rejects relative when allowRelative=false", '', $s->url('/path/to/page', ['allowRelative' => false]));
		$t->check("url() strips disallowed javascript: scheme", false, stripos($s->url('javascript:alert(1)'), 'javascript') !== false);
		$t->check("httpUrl() accepts https URL", 'https://processwire.com/', $s->httpUrl('https://processwire.com/'));
		$t->check("httpUrl() rejects relative paths", '', $s->httpUrl('/relative/path'));
		$t->check("email() accepts valid address", 'user@example.com', $s->email('user@example.com'));
		$t->check("email() returns blank for invalid address", '', $s->email('not-an-email'));
		$t->check("email() accepts plus-tag and subdomain addresses", 'user+tag@sub.example.com', $s->email('user+tag@sub.example.com'));
		$t->check("emailHeader() removes newline header injection", 'Subject  Bcc: test@example.com', $s->emailHeader("Subject\r\nBcc: test@example.com"));
		$t->check("emailHeader(headerName=true) sanitizes header name", 'X-Test--bad', $s->emailHeader('X-Test: bad', true));
		$t->check("path() accepts safe ASCII path", '/foo/bar.txt', $s->path('/foo/bar.txt'));
		$t->check("path() rejects traversal path", false, $s->path('/foo/../bar.txt'));
		
		// ===== ARRAYS =====
		
		$t->check("array() splits comma-delimited string", ['foo', 'bar', 'baz'], $s->array('foo,bar,baz'));
		$t->check("array() splits pipe-delimited string", ['foo', 'bar', 'baz'], $s->array('foo|bar|baz'));
		$t->check("array() sanitizes items with 'int'", [1, 2, 3], $s->array('1,2,3', 'int'));
		$t->check("array() with pageName sanitizer", ['foo', 'bar', 'baz'], $s->array('foo,bar,baz', 'pageName'));
		$t->check("array() maxItems option limits items", ['a', 'b'], $s->array('a,b,c', null, ['maxItems' => 2]));
		$t->check("arrayVal() keeps CSV string as one item", ['foo,bar'], $s->arrayVal('foo,bar'));
		$t->check("intArray() converts CSV, non-integers become 0", [1, 2, 3, 0], $s->intArray('1,2,3,foo'));
		$t->check("intArray(strict=true) removes non-integers", [1, 2], $s->intArray('1,2,foo', true));
		$t->check("intArrayVal() sanitizes arrays without CSV conversion", [1, 2], $s->intArrayVal(['1', '2', 'foo']));
		$t->check("textArray() recursively sanitizes text values", ['Hello', ['World']], $s->textArray(['<b>Hello</b>', ['<i>World</i>']]));
		$t->check("flatArray() flattens nested arrays", ['a', 'b', 'c', 'd'], $s->flatArray([['a', 'b'], ['c', ['d']]], ['maxDepth' => 3]));
		$t->check("wordsArray() extracts words", ['Hello', 'brave', 'world'], $s->wordsArray('Hello, brave-world!'));
		
		$data = ['a' => 'foo', 'b' => '', 'c' => 0, 'd' => 'bar'];
		$r = $s->minArray($data);
		$t->check("minArray() removes empty string", false, isset($r['b']));
		$t->check("minArray() removes 0", false, isset($r['c']));
		$t->check("minArray() keeps non-empty values", true, isset($r['a']) && isset($r['d']));
		
		$data = ['a' => 'foo', 'b' => '', 'c' => 0];
		$r = $s->minArray($data, 0);
		$t->check("minArray(data, 0) removes empty string", false, isset($r['b']));
		$t->check("minArray(data, 0) keeps integer 0", true, isset($r['c']) && $r['c'] === 0);
		
		$data = ['a' => 'foo', 'b' => '', 'c' => 0];
		$r = $s->minArray($data, ['b', 'c']);
		$t->check("minArray(data, [keys]) keeps listed empty keys", true, isset($r['b']) && isset($r['c']));
		
		$t->check("option() returns value when in whitelist", 'red', $s->option('red', ['red', 'green', 'blue']));
		$t->check("option() returns null when not in whitelist", null, $s->option('purple', ['red', 'green', 'blue']));
		$t->check("options() filters array to only allowed values", ['red', 'blue'], array_values($s->options(['red', 'purple', 'blue'], ['red', 'green', 'blue'])));
		
		// ===== SELECTOR VALUE =====
		
		$r = $s->selectorValue("O'Brien");
		$t->check("selectorValue() wraps in double-quotes when value contains single quote", '"', $r, '^=');
		$t->check("selectorValue() plain value passes through", 'hello', $s->selectorValue('hello'));
		$r = $s->selectorValue(['foo', 'bar']);
		$t->check("selectorValue() array becomes OR value containing 'foo'", 'foo', $r, '*=');
		$t->check("selectorValue() array becomes OR value containing '|'", '|', $r, '*=');
		
		// ===== VALIDATION =====
		
		$t->check("validate() returns value unchanged by sanitizer", 'user@example.com', $s->validate('user@example.com', 'email'));
		$t->check("validate() returns null when sanitizer changes value", null, $s->validate('not-an-email', 'email'));
		$t->check("validate() passes clean alpha string", 'hello', $s->validate('hello', 'alpha'));
		$t->check("valid() returns true for valid value", true, $s->valid('hello', 'alpha'));
		$t->check("valid() returns false for invalid value", false, $s->valid('hello 123', 'alpha'));
		$t->check("valid(strict=true) rejects string int", false, $s->valid('1', 'int', true));
		$t->check("valid(strict=true) accepts integer int", true, $s->valid(1, 'int', true));
		
		// ===== WHITESPACE =====
		
		$t->check("trim() removes leading/trailing whitespace", 'hello', $s->trim("  hello  "));
		$t->check("trim() trims custom characters", 'hello', $s->trim("--hello--", '-'));
		$t->check("removeNewlines() removes newline types", false, strpos($s->removeNewlines("Line1\nLine2\r\nLine3"), "\n") !== false);
		$t->check("removeNewlines() with '' removes newlines entirely", 'Line1Line2', $s->removeNewlines("Line1\nLine2", ''));
		$t->check("removeWhitespace() removes spaces and tabs", 'foobarbaz', $s->removeWhitespace("foo bar\tbaz"));
		$t->check("removeWhitespace() replace option replaces whitespace", 'foo bar', $s->removeWhitespace("foo\tbar", ['replace' => ' ']));
		$t->check("removeMB4() replaces 4-byte UTF-8 characters", 'a?b', $s->removeMB4("a\xF0\x9F\x98\x80b", ['replaceWith' => '?']));
		
		// ===== TRUNCATION AND LENGTH =====
		
		$str = "The quick brown fox jumps over the lazy dog. It was a beautiful day.";
		$t->check("truncate() respects maxLength", true, strlen($s->truncate($str, 30)) <= 30);
		$r = $s->trunc($str, 30);
		$t->check("trunc() respects maxLength", true, strlen($r) <= 30);
		$t->check("trunc() does not append ellipsis", false, strpos($r, '…') !== false);
		$t->check("maxLength() truncates string to N chars", 'Hello', $s->maxLength("Hello World", 5));
		$t->check("maxLength() limits array to N items", 3, count($s->maxLength([1, 2, 3, 4, 5], 3)));
		$t->check("maxBytes() truncates by byte length", 'abc', $s->maxBytes('abcdef', 3));
		$t->check("minLength() returns blank when value is shorter than minimum", '', $s->minLength("Hi", 5));
		$t->check("minLength() pads right with pad character", 'Hi000', $s->minLength("Hi", 5, '0'));
		$t->check("minLength() pads left with pad character", '000Hi', $s->minLength("Hi", 5, '0', true));
		
		// ===== CHAINING AND SHORTHAND =====
		
		$t->check("text20() shorthand limits to 20 chars", true, strlen($s->text20("This string is longer than twenty characters long")) <= 20);
		$t->check("text_entities() chains text() then entities()", 'Tom &amp; Jerry', $s->text_entities('<b>Tom & Jerry</b>'));
		$t->check("sanitize() calls sanitizer by name", 'Hello World', $s->sanitize("Hello <b>World</b>", 'text'));
		$t->check("sanitize() chained 'text,entities'", 'Hello World', $s->sanitize("Hello <b>World</b>", 'text,entities'));
		$t->check("sanitize() shorthand 'text5' limits length", 'Hello', $s->sanitize('Hello World', 'text5'));
		
		// ===== STRING UTILITY =====
		
		$t->check("string() converts int to string", '42', $s->string(42));
		$t->check("string() converts bool true to '1'", '1', $s->string(true));
		$t->check("string() converts bool false to ''", '', $s->string(false));
		$t->check("string() converts null to ''", '', $s->string(null));
		$t->check("string() with sanitizer argument sanitizes after casting", 'Hello World', $s->string('<b>Hello World</b>', 'text'));
		
		// ===== DATE, JSON AND DISCOVERY =====
		
		$t->check("date() parses date string to timestamp", '2025-06-01', date('Y-m-d', $s->date('2025-06-01')));
		$t->check("json() pretty-prints arrays by default", "{\n    \"a\": 1\n}", $s->json(['a' => 1]));
		$t->check("json(pretty=false) returns compact JSON", '{"a":1}', $s->json(['a' => 1], ['pretty' => false]));
		$t->check("getAll() includes text sanitizer", true, in_array('text', $s->getAll(), true));
		$t->check("getAll(true) includes return type codes", 'i', $s->getAll(true)['int']);
		$t->check("getTextTools() returns WireTextTools", true, $s->getTextTools() instanceof WireTextTools);
		$t->check("getNumberTools() returns WireNumberTools", true, $s->getNumberTools() instanceof WireNumberTools);

	}
}


