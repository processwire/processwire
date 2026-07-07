<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireTextTools
 *
 */
class WireTest_WireTextTools extends WireTest {

	public function execute() {
		$tools = $this->wire()->sanitizer->getTextTools();

		$this->check('getTextTools() returns WireTextTools', true, $tools instanceof WireTextTools);
		$this->testMarkupToText($tools);
		$this->testCollapseAndTruncate($tools);
		$this->testPlaceholders($tools);
		$this->testVisibleLengthDiffAndTags($tools);
		$this->testWordAlternatesAndEscapes($tools);
		$this->testStringWrappers($tools);
	}

	/**
	 * Test markupToText()
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testMarkupToText(WireTextTools $tools) {
		$html = '<h1>Hello</h1><p>Text&nbsp;<strong>bold</strong> <a href="/about/">About</a></p>' .
			'<ul><li>One</li><li>Two</li></ul><script>alert("x")</script>';

		$text = $tools->markupToText($html);
		$this->check('markupToText() includes headline text', 'Hello', $text, '*=');
		$this->check('markupToText() converts entity', 'Text bold', $text, '*=');
		$this->check('markupToText() converts links to URL form by default', 'About (/about/)', $text, '*=');
		$this->check('markupToText() prefixes list items', '• One', $text, '*=');
		$this->check('markupToText() clears script contents', false, strpos($text, 'alert'));

		$text = $tools->markupToText($html, array('linksToMarkdown' => true));
		$this->check('markupToText(linksToMarkdown) converts link to Markdown', '[About](/about/)', $text, '*=');

		$text = $tools->markupToText($html, array('linksToUrls' => false, 'linksToMarkdown' => false));
		$this->check('markupToText(links disabled) keeps anchor text', 'About', $text, '*=');
		$this->check('markupToText(links disabled) omits URL', false, strpos($text, '/about/'));

		$text = $tools->markupToText('<p>A <em>quiet</em> note</p>', array('keepTags' => array('em')));
		$this->check('markupToText(keepTags) preserves requested tag', '<em>quiet</em>', $text, '*=');
	}

	/**
	 * Test collapse() and truncate()
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testCollapseAndTruncate(WireTextTools $tools) {
		$text = $tools->collapse("<p>Hello</p>\n<p>World</p>", array('collapseLinesWith' => ' | '));
		$this->check('collapse() flattens blocks with custom separator', 'Hello | World', $text);

		$text = $tools->collapse('<p>Hello <a href="/about/">About</a></p>', array('linksToUrls' => true));
		$this->check('collapse(linksToUrls) includes URL', 'About (/about/)', $text, '*=');

		$str = 'The quick brown fox jumps over the lazy dog.';
		$this->check('truncate() respects max length at word boundary', 'The quick…', $tools->truncate($str, 12));

		$str = 'First sentence. Second sentence. Third sentence.';
		$this->check('truncate(sentence) ends at sentence boundary', 'First sentence.', $tools->truncate($str, 25, 'sentence'));

		$html = '<p><strong>Hello</strong> wide world</p>';
		$this->check('truncate(keepFormatTags) keeps inline formatting tag', '<strong>Hello</strong>', $tools->truncate($html, 24, array('keepFormatTags' => true)), '*=');
		$this->check('truncate(keepFormatTags) repairs partial tags by closing', '</strong>', $tools->truncate($html, 18, array('keepFormatTags' => true)), '*=');
		$this->check('truncate(visible) counts visible chars', true, $tools->getVisibleLength($tools->truncate($html, 12, array('visible' => true))) <= 13);
	}

	/**
	 * Test placeholders
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testPlaceholders(WireTextTools $tools) {
		$str = 'Hello {first_name}, welcome to {site}.';
		$vars = array('first_name' => 'Ryan', 'site' => 'ProcessWire');
		$this->check('populatePlaceholders(array) replaces tags', 'Hello Ryan, welcome to ProcessWire.', $tools->populatePlaceholders($str, $vars));

		$vars = array(
			'name' => '<b>Ryan</b>',
			'title' => '{name} writes',
			'empty' => '',
		);
		$this->check('populatePlaceholders(entityEncode) encodes values', 'Hello &lt;b&gt;Ryan&lt;/b&gt;', $tools->populatePlaceholders('Hello {name}', $vars, array('entityEncode' => true)));
		$this->check('populatePlaceholders(recursive) resolves nested tags', '<b>Ryan</b> writes', $tools->populatePlaceholders('{title}', $vars, array('recursive' => true)));
		$this->check('populatePlaceholders(removeEmptyTags=false) leaves empty tag', 'Value: {empty}', $tools->populatePlaceholders('Value: {empty}', $vars, array('removeEmptyTags' => false)));

		$page = $this->wire()->pages->get('/');
		$this->check('populatePlaceholders(Page OR tags) uses first non-empty value', $page->getFormatted('title'), $tools->populatePlaceholders('{missing_field|title|name}', $page));

		$tags = $tools->findPlaceholders('Hello {name}, {site_name}.');
		$this->check('findPlaceholders() returns tag names', array('name', 'site_name'), array_keys($tags));
		$this->check('findPlaceholders(has=true) returns bool', true, $tools->findPlaceholders('Hello {name}', array('has' => true)));
		$this->check('hasPlaceholders() returns false when none present', false, $tools->hasPlaceholders('Hello name'));
		$this->check('findPlaceholders(custom tags) detects tags', true, $tools->hasPlaceholders('Hello [[name]]', array('tagOpen' => '[[', 'tagClose' => ']]')));
	}

	/**
	 * Test visible length, diff and tag repair
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testVisibleLengthDiffAndTags(WireTextTools $tools) {
		$this->check('getVisibleLength() excludes markup tags', 11, $tools->getVisibleLength('Hello <strong>world</strong>'));
		$this->check('getVisibleLength() decodes entities before counting', 10, $tools->getVisibleLength('Price: &pound;10'));

		$diff = $tools->diffMarkup('The quick brown fox', 'The slow brown fox');
		$this->check('diffMarkup() marks deleted text', '<del>quick</del>', $diff, '*=');
		$this->check('diffMarkup() marks inserted text', '<ins>slow</ins>', $diff, '*=');
		$this->check('diffMarkup() entity encodes unchanged text', '&lt;tag&gt;', $tools->diffMarkup('<tag> old', '<tag> new'), '*=');

		$this->check('fixUnclosedTags(remove=true) strips unclosed tag type', 'Hello world', $tools->fixUnclosedTags('Hello <em>world'));
		$this->check('fixUnclosedTags(remove=false) appends closing tag', 'Hello <em>world</em>', $tools->fixUnclosedTags('Hello <em>world', false));
	}

	/**
	 * Test word alternates and escape placeholders
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testWordAlternatesAndEscapes(WireTextTools $tools) {
		$hookId = $tools->addHookAfter('wordAlternates', function(HookEvent $event) {
			$event->return = array('cat', 'cats', 'Cat', 'x', 'feline', 'feline');
		});

		$this->check('getWordAlternates() filters hook results', array('cats', 'feline'), $tools->getWordAlternates('cat', array('lowercase' => true, 'minLength' => 3)));
		$tools->removeHook($hookId);

		$str = 'Hello \*world\* and \?';
		$map = $tools->findReplaceEscapeChars($str, array('*'));
		$this->check('findReplaceEscapeChars() returns placeholder map', 1, count($map));
		$this->check('findReplaceEscapeChars() replaces escaped chars', false, strpos($str, '\*'));
		$str = str_replace(array_keys($map), array_values($map), $str);
		$this->check('findReplaceEscapeChars() map restores chars without escapes', 'Hello *world* and \?', $str);

		$str = 'Hello \?';
		$tools->findReplaceEscapeChars($str, array('*'), array('unescapeUnknown' => true));
		$this->check('findReplaceEscapeChars(unescapeUnknown) removes escape prefix only', 'Hello ?', $str);
	}

	/**
	 * Test PHP string wrapper methods
	 *
	 * @param WireTextTools $tools
	 *
	 */
	protected function testStringWrappers(WireTextTools $tools) {
		$this->check('strlen() counts multibyte chars when available', 4, $tools->strlen('café'));
		$this->check('substr() extracts substring', 'fé', $tools->substr('café', 2));
		$this->check('strpos() finds substring position', 2, $tools->strpos('café', 'f'));
		$this->check('strtolower() lowercases string', 'hello', $tools->strtolower('HELLO'));
		$this->check('strtoupper() uppercases string', 'HELLO', $tools->strtoupper('hello'));
		$this->check('trim() trims custom chars', 'hello', $tools->trim('--hello--', '-'));
		$this->check('ltrim() trims custom chars from left', 'hello--', $tools->ltrim('--hello--', '-'));
		$this->check('rtrim() trims custom chars from right', '--hello', $tools->rtrim('--hello--', '-'));
	}
}
