<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireMarkupRegions
 *
 */
class WireTest_WireMarkupRegions extends WireTest {

	public function execute() {
		$regions = $this->wire(new WireMarkupRegions());

		$this->check('WireMarkupRegions class can be constructed', true, $regions instanceof WireMarkupRegions);
		$this->testFind($regions);
		$this->testUpdateAndWrappers($regions);
		$this->testPopulate($regions);
		$this->testTagUtilities($regions);
		$this->testStripAndDetection($regions);
	}

	/**
	 * Test find()
	 *
	 * @param WireMarkupRegions $regions
	 *
	 */
	protected function testFind(WireMarkupRegions $regions) {
		$html = '<main id="content"><p>Body</p></main>' .
			'<aside pw-id="sidebar" class="box side-large">Side</aside>' .
			'<section data-pw-id="promo" class="card feature-one">Promo</section>' .
			'<div data-role="hero">Hero</div>' .
			'<footer pw-append="content">Footer</footer>';

		$found = $regions->find('#content', $html);
		$this->check('find(#id) returns inner markup', '<p>Body</p>', reset($found));

		$found = $regions->find('#sidebar', $html);
		$this->check('find(#id) matches pw-id', 'Side', reset($found));

		$found = $regions->find('#promo', $html);
		$this->check('find(#id) matches data-pw-id', 'Promo', reset($found));

		$found = $regions->find('.box', $html);
		$this->check('find(.class) wraps class matches by default', '<aside pw-id="sidebar" class="box side-large">Side</aside>', reset($found));

		$found = $regions->find('.side-*', $html);
		$this->check('find(.prefix*) matches class prefix', 1, count($found));

		$found = $regions->find('section.card', $html);
		$this->check('find(tag.class) limits class match to tag', '<section data-pw-id="promo" class="card feature-one">Promo</section>', reset($found));

		$found = $regions->find('<footer>', $html, array('wrap' => true));
		$this->check('find(<tag>) finds tag', '<footer pw-append="content">Footer</footer>', reset($found));

		$found = $regions->find('data-role=hero', $html);
		$this->check('find(attribute=value) finds attribute value', 'Hero', reset($found));

		$found = $regions->find('div[data-role=hero]', $html);
		$this->check('find(tag[attribute=value]) finds attribute value on tag', 'Hero', reset($found));

		$found = $regions->find('[pw-action]', $html, array('verbose' => true));
		$first = reset($found);
		$this->check('find([pw-action]) finds pw action attributes', 'append', $first['action']);
		$this->check('find([pw-action]) reports action target', 'content', $first['actionTarget']);

		$this->check('find(single) returns string', 'Body', $regions->find('#content', $html, array('single' => true)), '*=');

		$found = $regions->find('#content', $html, array('leftover' => true));
		$this->check('find(leftover) includes leftover key', true, array_key_exists('leftover', $found));
		$this->check('find(leftover) removes matched region from leftover', false, strpos($found['leftover'], 'id="content"'));

		$found = $regions->find('#content, #sidebar', $html);
		$this->check('find(CSV) returns multiple matches', 2, count($found));
	}

	/**
	 * Test update() and convenience wrappers
	 *
	 * @param WireMarkupRegions $regions
	 *
	 */
	protected function testUpdateAndWrappers(WireMarkupRegions $regions) {
		$html = '<div id="main" class="old">Body</div><aside id="side">Side</aside>';

		$this->check('update(replace) replaces inner content', '<div id="main" class="old">New</div><aside id="side">Side</aside>', $regions->update('#main', 'New', $html, array('action' => 'replace')));
		$this->check('update(append) appends content', '<div id="main" class="old">Body+After</div><aside id="side">Side</aside>', $regions->update('#main', '+After', $html, array('action' => 'append')));
		$this->check('update(prepend) prepends content', '<div id="main" class="old">Before+Body</div><aside id="side">Side</aside>', $regions->update('#main', 'Before+', $html, array('action' => 'prepend')));
		$this->check('update(before) inserts before region', '<p>Before</p><div id="main" class="old">Body</div>', $regions->update('#main', '<p>Before</p>', '<div id="main" class="old">Body</div>', array('action' => 'before')), '*=');
		$this->check('update(after) inserts after region', '</div><p>After</p>', $regions->update('#main', '<p>After</p>', '<div id="main" class="old">Body</div>', array('action' => 'after')), '*=');
		$this->check('update(remove) removes region', '<aside id="side">Side</aside>', $regions->update('#main', '', $html, array('action' => 'remove')));

		$updated = $regions->update('#main', '+After', $html, array(
			'action' => 'append',
			'mergeAttr' => array('class' => 'new -old', 'title' => 'Hello'),
		));
		$this->check('update(mergeAttr) merges attributes', '<div id="main" class="new" title="Hello">Body+After</div><aside id="side">Side</aside>', $updated);

		$this->check('replace() wrapper delegates to update()', '<div id="main" class="old">New</div><aside id="side">Side</aside>', $regions->replace('#main', 'New', $html));
		$this->check('append() wrapper delegates to update()', '<div id="main" class="old">Body+After</div><aside id="side">Side</aside>', $regions->append('#main', '+After', $html));
		$this->check('prepend() wrapper delegates to update()', '<div id="main" class="old">Before+Body</div><aside id="side">Side</aside>', $regions->prepend('#main', 'Before+', $html));
		$this->check('before() wrapper delegates to update()', '<p>Before</p><div id="main"', $regions->before('#main', '<p>Before</p>', $html), '*=');
		$this->check('after() wrapper delegates to update()', '</div><p>After</p><aside', $regions->after('#main', '<p>After</p>', $html), '*=');
		$this->check('remove() wrapper delegates to update()', '<aside id="side">Side</aside>', $regions->remove('#main', $html));
	}

	/**
	 * Test populate()
	 *
	 * @param WireMarkupRegions $regions
	 *
	 */
	protected function testPopulate(WireMarkupRegions $regions) {
		$document = '<!DOCTYPE html><html><head><title>Old</title></head><body><main id="main" class="old"><p>Old</p></main><aside id="side" pw-optional></aside></body></html>';
		$template = '<main id="main" class="new -old"><p>New</p></main>' .
			'<p pw-append="main">After</p>' .
			'<h2 pw-before="main">Before</h2>' .
			'<title>New title</title>';

		$count = $regions->populate($document, $template);
		$this->check('populate() reports updates', true, $count >= 4);
		$this->check('populate() replaces matching id region', '<main id="main" class="new"><p>New</p><p>After</p></main>', $document, '*=');
		$this->check('populate() inserts before target', '<h2>Before</h2><main', $document, '*=');
		$this->check('populate() updates single-use title tag', '<title>New title</title>', $document, '*=');
		$this->check('populate() strips pw action attributes', false, strpos($document, 'pw-append'));
		$this->check('populate() removes empty optional region', false, strpos($document, 'id="side"'));

		$document = '<main id="main">Old</main><footer>Foot</footer>';
		$template = '<p pw-after="^footer">After footer</p>';
		$regions->populate($document, $template);
		$this->check('populate() supports ^tag action targets', '<footer>Foot</footer><p>After footer</p>', $document, '*=');
	}

	/**
	 * Test tag utility methods
	 *
	 * @param WireMarkupRegions $regions
	 *
	 */
	protected function testTagUtilities(WireMarkupRegions $regions) {
		$info = $regions->getTagInfo('<div id="main" class="wrap pw-append" data-x="1">');
		$this->check('getTagInfo() parses tag name', 'div', $info['name']);
		$this->check('getTagInfo() parses id', 'main', $info['id']);
		$this->check('getTagInfo() detects action class', 'append', $info['action']);
		$this->check('getTagInfo() uses id as action target for boolean action', 'main', $info['actionTarget']);
		$this->check('getTagInfo() removes action class from classes', false, in_array('pw-append', $info['classes'], true));

		$info = $regions->getTagInfo('<section data-pw-id="hero" data-pw-replace="main">');
		$this->check('getTagInfo() parses data-pw-id as pwid', 'hero', $info['pwid']);
		$this->check('getTagInfo() detects data-pw action', 'replace', $info['action']);
		$this->check('getTagInfo() detects data-pw action target', 'main', $info['actionTarget']);

		$tag = $regions->mergeTags('<div id="main" class="old col-6 keep">', '<section class="+forced new -old -col-*" title="Hello">');
		$this->check('mergeTags() keeps original tag name', '<div ', $tag, '^=');
		$this->check('mergeTags() adds class', 'new', $tag, '*=');
		$this->check('mergeTags() force-adds class', 'forced', $tag, '*=');
		$this->check('mergeTags() removes exact class', false, strpos($tag, 'old'));
		$this->check('mergeTags() removes wildcard class', false, strpos($tag, 'col-6'));
		$this->check('mergeTags() adds non-class attribute', 'title="Hello"', $tag, '*=');

		$attrs = $regions->renderAttributes(array('id' => 'main', 'class' => array('a', 'b'), 'checked' => true, 'title' => 'A&B'));
		$this->check('renderAttributes() renders id', 'id="main"', $attrs, '*=');
		$this->check('renderAttributes() joins array values', 'class="a b"', $attrs, '*=');
		$this->check('renderAttributes() renders boolean attributes', 'checked', $attrs, '*=');
		$this->check('renderAttributes() entity-encodes values', 'A&amp;B', $attrs, '*=');

		$html = '<div id="main" class="foo bar" data-active>Body</div>';
		$this->check('hasAttribute(id) detects id', true, $regions->hasAttribute('id', 'main', $html));
		$this->check('hasAttribute(class) detects class token', true, $regions->hasAttribute('class', 'bar', $html));
		$this->check('hasAttribute(boolean) detects boolean attribute', true, $regions->hasAttribute('data-active', true, $html));
		$this->check('hasAttribute(tag.class) detects tag/class', true, $regions->hasAttribute('tag', 'div.foo', $html));
	}

	/**
	 * Test stripping and detection helpers
	 *
	 * @param WireMarkupRegions $regions
	 *
	 */
	protected function testStripAndDetection(WireMarkupRegions $regions) {
		$html = '<p>A</p><!-- remove --><div id="x">X</div><!--#x--><script>alert(1)</script>';
		$this->check('stripRegions(comments) removes normal comments', false, strpos($regions->stripRegions('<!--', $html), 'remove'));
		$this->check('stripRegions(comments) preserves close hints', '<!--#x-->', $regions->stripRegions('<!--', $html), '*=');
		$this->check('stripRegions(script) removes scripts', false, strpos($regions->stripRegions('<script', $html), 'alert'));
		$this->check('stripRegions(getRegions) extracts regions', true, count($regions->stripRegions('<script', $html, true)) === 1);

		$html = '<div id="empty" pw-optional></div><div id="full" pw-optional>Text</div>';
		$stripped = $regions->stripOptional($html);
		$this->check('stripOptional() removes empty optional region', false, strpos($stripped, 'id="empty"'));
		$this->check('stripOptional() keeps non-empty optional region', 'id="full"', $stripped, '*=');
		$this->check('stripOptional() removes optional attribute from kept region', false, strpos($stripped, 'pw-optional'));

		$html = '<region data-pw-id="x"><p>X</p></region><div pw-id="y">Y</div>';
		$this->check('removeRegionTags() reports update', true, $regions->removeRegionTags($html));
		$this->check('removeRegionTags() removes region wrapper', false, strpos($html, '<region'));
		$this->check('removeRegionTags() removes pw-id attrs', false, strpos($html, 'pw-id='));

		$html = '<div id="main">Body</div>';
		$this->check('hasRegions() detects id regions', true, $regions->hasRegions($html));
		$html = '<p pw-append="main">Body</p>';
		$this->check('hasRegionActions() detects pw actions', true, $regions->hasRegionActions($html));
	}
}
