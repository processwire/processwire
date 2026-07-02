<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Page class
 *
 */
class WireTest_Page extends WireTest {

	protected $prefix = 'wiretests_page_meta_';

	public function init() {
		$this->cleanup();
	}

	public function execute() {
		$page = $this->getTestPage();

		$this->check('$page is Page', true, $page instanceof Page);
		$this->testMetaValues();
		$this->testMetaCache();
		$this->testMetaStorageBoundaries();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testMetaValues() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		$page->meta($this->key('view_count'), 42);
		$page->meta($this->key('last_synced'), '2026-06-03');
		$page->meta($this->key('colors'), array('red', 'green', 'blue'));
		$page->meta()->set($this->key('priority'), 'high');

		$this->check('meta(key, value) persists integer value', 42, $page->meta($this->key('view_count')));
		$this->check('meta(key, value) persists string value', '2026-06-03', $page->meta($this->key('last_synced')));
		$this->check('meta(key, value) persists array value', array('red', 'green', 'blue'), $page->meta($this->key('colors')));
		$this->check('meta()->set() persists value', 'high', $page->meta()->get($this->key('priority')));
		$this->check('meta() returns WireDataDB instance', true, $page->meta() instanceof WireDataDB);

		$fresh = $pages->getFresh($page->id);
		$this->check('meta values persist independently of page save', 42, $fresh->meta($this->key('view_count')));
		$this->check('meta array persists independently of page save', array('red', 'green', 'blue'), $fresh->meta($this->key('colors')));

		$all = $fresh->meta()->getArray();
		$this->check('meta()->getArray() returns associative array', true, is_array($all));
		$this->check('meta()->getArray() includes saved key', true, array_key_exists($this->key('priority'), $all));
		$this->check('count(meta()) counts stored rows', true, count($fresh->meta()) >= 4);

		$fresh->meta()->remove($this->key('colors'));
		$this->check('meta()->remove() removes single key', null, $fresh->meta($this->key('colors')));
	}

	protected function testMetaCache() {
		$page = $this->getTestPage();
		$key = $this->key('cached_value');
		$calls = 0;
		$func = function() use(&$calls) {
			$calls++;
			return 'computed-' . $calls;
		};

		$first = $page->meta()->getCache($key, 3600, $func);
		$second = $page->meta()->getCache($key, 3600, $func);

		$this->check('meta()->getCache() computes initial value', 'computed-1', $first);
		$this->check('meta()->getCache() reuses unexpired value', 'computed-1', $second);
		$this->check('meta()->getCache() did not call callback for unexpired value', 1, $calls);

		$refresh = $page->meta()->getCache($key, -1, $func);

		$this->check('meta()->getCache() refreshes expired value', 'computed-2', $refresh);
		$this->check('meta()->getCache() called callback after expiration', 2, $calls);
	}

	protected function testMetaStorageBoundaries() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		$page->meta($this->key('bool_true'), true);
		$page->meta($this->key('bool_false'), false);
		$page->meta($this->key('nested'), array(
			'ids' => array(1, 2, 3),
			'flags' => array('enabled' => true),
		));

		$fresh = $pages->getFresh($page->id);
		$this->check('meta stores boolean true', true, $fresh->meta($this->key('bool_true')));
		$this->check('meta stores boolean false', false, $fresh->meta($this->key('bool_false')));
		$this->check('meta stores nested arrays', true, $fresh->meta($this->key('nested'))['flags']['enabled']);

		$fresh->meta($this->key('nullable'), 'remove me');
		$this->check('meta(key, null) gets existing value', 'remove me', $fresh->meta($this->key('nullable'), null));
		$fresh->meta()->set($this->key('nullable'), null);
		$this->check('meta()->set(key, null) removes value', null, $fresh->meta($this->key('nullable')));

		$objectKey = $this->key('object');
		$fresh->meta($objectKey, new WireData());
		$fresh->meta()->reset();
		$this->check('meta does not persist object values', null, $fresh->meta($objectKey));

		$unsaved = $pages->newPage();
		$unsaved->meta($this->key('unsaved'), 'value');
		$unsaved->meta()->reset();
		$this->check('unsaved pages do not persist meta values', null, $unsaved->meta($this->key('unsaved')));
	}

	protected function cleanup() {
		$page = $this->getTestPage();
		foreach($page->meta()->getArray() as $key => $value) {
			if(strpos($key, $this->prefix) === 0) $page->meta()->remove($key);
		}
	}

	protected function key($name) {
		return $this->prefix . $name;
	}
}
