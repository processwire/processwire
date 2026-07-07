<?php namespace ProcessWire;

/**
 * Tests for ProcessWire TextformatterMarkdownExtra.
 *
 */
class WireTest_TextformatterMarkdownExtra extends WireTest {

	public function execute() {
		$this->testInstancesAndParsedown();
		$this->testMarkdownConversion();
		$this->testSafeMode();
		$this->testFormatMethods();
		$this->testMarkdownExtensions();
	}

	protected function formatter() {
		return $this->wire()->modules->get('TextformatterMarkdownExtra');
	}

	protected function testInstancesAndParsedown() {
		$t1 = $this->formatter();
		$t2 = $this->formatter();

		$this->check('module returns TextformatterMarkdownExtra', true, $t1 instanceof TextformatterMarkdownExtra);
		$this->check('module is singular', true, $t1 === $t2);
		$this->check('default flavor is ParsedownExtra', TextformatterMarkdownExtra::flavorParsedownExtra, (int) $t1->flavor);
		$this->check('default safeMode is false', false, $t1->safeMode());
		$this->check('getParsedown(Parsedown) returns Parsedown', 'Parsedown', get_class($t1->getParsedown(TextformatterMarkdownExtra::flavorParsedown)));
		$this->check('getParsedown(default) returns ParsedownExtra', 'ParsedownExtra', get_class($t1->getParsedown(TextformatterMarkdownExtra::flavorParsedownExtra)));
	}

	protected function testMarkdownConversion() {
		$t = $this->formatter();
		$html = $t->markdown("# Headline\n\nThis is **bold** and _italic_.");

		$this->check('markdown() renders headline', '<h1>Headline</h1>', $html, '*=');
		$this->check('markdown() renders strong', '<strong>bold</strong>', $html, '*=');
		$this->check('markdown() renders emphasis', '<em>italic</em>', $html, '*=');

		$extra = $t->markdown("[^a]\n\n[^a]: note", TextformatterMarkdownExtra::flavorParsedownExtra);
		$plain = $t->markdown("[^a]\n\n[^a]: note", TextformatterMarkdownExtra::flavorParsedown);

		$this->check('ParsedownExtra renders footnotes', 'footnote', $extra, '*=');
		$this->check('Parsedown plain does not render footnote block', false, strpos($plain, 'class="footnotes"'));
	}

	protected function testSafeMode() {
		$t = $this->formatter();
		$wasSafe = $t->safeMode();

		$t->safeMode(false);
		$unsafe = $t->markdown('<script>alert(1)</script>');
		$safe = $t->markdownSafe('<script>alert(1)</script>');

		$this->check('markdown() unsafe mode leaves script markup', '<script>alert(1)</script>', $unsafe, '*=');
		$this->check('markdownSafe() escapes script markup', '&lt;script&gt;alert(1)&lt;/script&gt;', $safe, '*=');

		$t->safeMode(true);
		$this->check('safeMode(true) enables safe mode', true, $t->safeMode());
		$this->check('markdown() uses configured safe mode', '&lt;script&gt;', $t->markdown('<script>alert(1)</script>'), '*=');
		$this->check('markdown(..., safeMode=false) overrides configured safe mode', '<script>alert(1)</script>', $t->markdown('<script>alert(1)</script>', null, false), '*=');
		$t->safeMode($wasSafe);
	}

	protected function testFormatMethods() {
		$t = $this->formatter();
		$str = "Hello **world**";
		$t->format($str);
		$this->check('format() updates string by reference', '<strong>world</strong>', $str, '*=');

		$page = $this->getTestPage();
		$field = $this->wire()->fields->get('body');
		if(!$field) $field = $this->wire()->fields->get('title');
		$value = "Value **bold**";
		$t->formatValue($page, $field, $value);
		$this->check('formatValue() updates value by reference', '<strong>bold</strong>', $value, '*=');
	}

	protected function testMarkdownExtensions() {
		$t = $this->formatter();
		$wasFlavor = (int) $t->flavor;

		$html = '<p>Hello</p>#greeting';
		$t->markdownExtensions($html);
		$this->check('markdownExtensions() adds id attribute', '<p id="greeting">Hello</p>', $html);

		$html = '<p>Hello</p>.intro';
		$t->markdownExtensions($html);
		$this->check('markdownExtensions() adds class attribute', '<p class="intro">Hello</p>', $html);

		$html = $t->markdown('[Example](https://example.com)+', TextformatterMarkdownExtra::flavorParsedownExtra | TextformatterMarkdownExtra::flavorRCD);
		$this->check('flavorRCD link plus adds target blank', 'target="_blank"', $html, '*=');

		$t->flavor = TextformatterMarkdownExtra::flavorParsedownExtra | TextformatterMarkdownExtra::flavorRCD;
		$html = $t->markdown('[Configured](https://example.com)+');
		$this->check('configured flavorRCD applies markdownExtensions()', 'target="_blank"', $html, '*=');
		$t->flavor = $wasFlavor;
	}
}
