<?php namespace ProcessWire;

/**
 * Tests for ProcessWire MarkupHTMLPurifier.
 *
 */
class WireTest_MarkupHTMLPurifier extends WireTest {

	public function execute() {
		$this->testFreshInstancesAndDefaults();
		$this->testPurifyDefaults();
		$this->testConfigSetGetAndCache();
		$this->testInitConfigHook();
	}

	protected function purifier() {
		return $this->wire()->modules->get('MarkupHTMLPurifier');
	}

	protected function testFreshInstancesAndDefaults() {
		$purifier1 = $this->purifier();
		$purifier2 = $this->purifier();
		$config = $purifier1->getConfig();
		$def = $purifier1->getDef();

		$this->check('module returns MarkupHTMLPurifier', true, $purifier1 instanceof MarkupHTMLPurifier);
		$this->check('module returns fresh instances', false, $purifier1 === $purifier2);
		$this->check('getConfig() returns HTMLPurifier_Config', true, $config instanceof \HTMLPurifier_Config);
		$this->check('getDef() returns HTMLPurifier_HTMLDefinition or null', true, $def === null || $def instanceof \HTMLPurifier_HTMLDefinition);
		$this->check('default encoding is UTF-8', 'utf-8', strtolower($purifier1->get('Core.Encoding')));
		$this->check('default rel allows noopener', true, isset($purifier1->get('Attr.AllowedRel')['noopener']));
		$this->check('cache path is module-specific', 'MarkupHTMLPurifier', $purifier1->get('Cache.SerializerPath'), '*=');
	}

	protected function testPurifyDefaults() {
		$purifier = $this->purifier();
		$dirty = '<figure><figcaption onclick="alert(1)">Caption</figcaption>' .
			'<p onclick="alert(1)">Hello <strong>world</strong><script>alert(2)</script></p></figure>';
		$clean = $purifier->purify($dirty);

		$this->check('purify() keeps allowed paragraph content', '<p>Hello <strong>world</strong></p>', $clean, '*=');
		$this->check('purify() removes script tag', false, strpos($clean, '<script'));
		$this->check('purify() removes event handler attributes', false, strpos($clean, 'onclick'));
		$this->check('purify() allows figure element', '<figure>', $clean, '*=');
		$this->check('purify() allows figcaption element', '<figcaption>Caption</figcaption>', $clean, '*=');

		$htmlPurifier1 = $purifier->getPurifier();
		$htmlPurifier2 = $purifier->getPurifier();
		$this->check('getPurifier() caches purifier instance', true, $htmlPurifier1 === $htmlPurifier2);
	}

	protected function testConfigSetGetAndCache() {
		$purifier = $this->purifier();

		$this->check('set(normal key) returns purifier', true, $purifier->set('wireTestProperty', 'ok') === $purifier);
		$this->check('get(normal key) reads WireData property', 'ok', $purifier->get('wireTestProperty'));
		$this->check('set(dotted key) returns purifier', true, $purifier->set('HTML.Allowed', 'p,b,a[href]') === $purifier);
		$this->check('get(dotted key) reads purifier config', 'p,b,a[href]', $purifier->get('HTML.Allowed'));

		$clean = $purifier->purify('<p>Hello <em>there</em> <b>friend</b> <a href="/x" onclick="x()">link</a></p>');
		$this->check('custom HTML.Allowed keeps allowed b tag', '<b>friend</b>', $clean, '*=');
		$this->check('custom HTML.Allowed removes disallowed em tag', false, strpos($clean, '<em>'));
		$this->check('custom HTML.Allowed keeps allowed link href', '<a href="/x">link</a>', $clean, '*=');

		$cachePath = $purifier->get('Cache.SerializerPath');
		if(is_dir($cachePath) || $this->wire()->files->mkdir($cachePath)) {
			$testFile = $cachePath . 'wire-test-cache.tmp';
			file_put_contents($testFile, 'test');
			$this->check('cache fixture file exists before clearCache()', true, is_file($testFile));
			$purifier->clearCache();
			$this->check('clearCache() removes cache directory', false, is_dir($cachePath));
		} else {
			$this->fail("Unable to create cache directory: $cachePath");
		}
	}

	protected function testInitConfigHook() {
		$called = false;
		$hookId = $this->wire()->addHookAfter('MarkupHTMLPurifier::initConfig', function(HookEvent $event) use(&$called) {
			$called = true;
			$def = $event->arguments(1);
			if($def instanceof \HTMLPurifier_HTMLDefinition) {
				$def->addAttribute('a', 'data-wire-test', 'Text');
			}
		});

		$purifier = $this->purifier();
		$clean = $purifier->purify('<p><a href="/" data-wire-test="ok" onclick="alert(1)">Link</a></p>');
		$this->wire()->removeHook($hookId);

		$this->check('initConfig hook is called during init()', true, $called);
		$this->check('initConfig hook can add custom attribute', 'data-wire-test="ok"', $clean, '*=');
		$this->check('custom attribute test still strips event handler', false, strpos($clean, 'onclick'));
	}
}
