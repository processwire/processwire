<?php namespace ProcessWire;

/**
 * Tests for ProcessWire PageFrontEdit.
 *
 */
class WireTest_PageFrontEdit extends WireTest {

	public function allow() {
		if(!$this->wire()->modules->isInstalled('PageFrontEdit')) {
			$this->li('PageFrontEdit is not installed');
			return false;
		}
		return true;
	}

	public function execute() {
		$this->testPageStateAndAjaxUrl();
		$this->testInlineSupported();
		$this->testHookPageEditorBasics();
		$this->testNoEditMarkupCleanup();
		$this->testRenderAssets();
	}

	protected function editor() {
		return $this->wire()->modules->get('PageFrontEdit');
	}

	protected function testPageStateAndAjaxUrl() {
		$pfe = $this->editor();
		$page = $this->getTestPage();

		$pfe->setPage($page);
		$this->check('setPage() stores edited page', $page->id, $pfe->getPage()->id);
		$this->check('setPage() marks page for front editing', true, (bool) $page->get('_PageFrontEdit'));
		$this->check('getAjaxPostUrl() returns current page URL by default', $this->wire()->page->url, $pfe->getAjaxPostUrl());

		$hookId = $this->wire()->addHookAfter('PageFrontEdit::getAjaxPostUrl', function(HookEvent $event) {
			$event->return .= '?wire_test=1';
		});
		$this->check('getAjaxPostUrl() is hookable', '?wire_test=1', $pfe->getAjaxPostUrl(), '$=');
		$this->wire()->removeHook($hookId);
	}

	protected function testInlineSupported() {
		$pfe = $this->editor();
		$fields = $this->wire()->fields;
		$title = $fields->get('title');
		$images = $fields->get('images');

		$this->check('inlineSupported() supports title/text field', true, $pfe->inlineSupported($title));
		if($images) {
			$this->check('inlineSupported() rejects object-value image field', false, $pfe->inlineSupported($images));
		} else {
			$this->ok('images field not present, skipping image inlineSupported() check');
		}

		$allowed = $pfe->inlineAllowFieldtypes;
		$pfe->inlineAllowFieldtypes = array('FieldtypeInteger');
		$this->check('inlineSupported() honors inlineAllowFieldtypes setting', false, $pfe->inlineSupported($title));
		$pfe->inlineAllowFieldtypes = $allowed;
	}

	protected function testHookPageEditorBasics() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$page->set('wire_test_runtime', 'Runtime value');

		$event = $this->event($page, array());
		$pfe->hookPageEditor($event);
		$this->check('Page::edit() with no args returns active state', true, is_bool($event->return));

		$event = $this->event($page, array(false));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(false) returns page', $page->id, $event->return->id);

		$event = $this->event($page, array());
		$pfe->hookPageEditor($event);
		$this->check('Page::edit() reflects disabled state', false, $event->return);

		$event = $this->event($page, array(true));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(true) returns page', $page->id, $event->return->id);

		$event = $this->event($page, array('title', false));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(field, false) returns formatted value', $page->getFormatted('title'), $event->return);

		$event = $this->event($page, array('wire_test_runtime'));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(non-field) delegates to page value', 'Runtime value', $event->return);

		$event = $this->event($page, array(array('bad')));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(invalid arg) reports invalid argument', 'Invalid argument', $event->return, '*=');
	}

	protected function testNoEditMarkupCleanup() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$html = '<html><body><edit title><h1>Title</h1></edit><div class="x" edit="title">Body</div></body></html>';
		$event = new HookEvent(array(
			'object' => $page,
			'method' => 'render',
			'return' => $html,
		));

		$pfe->hookPageRenderNoEdit($event);
		$this->check('hookPageRenderNoEdit() removes opening edit tag', false, strpos($event->return, '<edit'));
		$this->check('hookPageRenderNoEdit() removes closing edit tag', false, strpos($event->return, '</edit>'));
		$this->check('hookPageRenderNoEdit() preserves wrapped markup', '<h1>Title</h1>', $event->return, '*=');
		$this->check('hookPageRenderNoEdit() removes edit attribute', false, strpos($event->return, ' edit='));
	}

	protected function testRenderAssets() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$pfe->setPage($page);

		$out = $pfe->renderAssets();

		$this->check('renderAssets() includes PageFrontEdit loader', 'PageFrontEditLoad.js', $out, '*=');
		$this->check('renderAssets() includes edited page id', "value='{$page->id}'", $out, '*=');
		$this->check('renderAssets() includes CSRF token field', $this->wire()->session->CSRF->getTokenName(), $out, '*=');
		$this->check('renderAssets() includes save button markup', 'pw-edit-save', $out, '*=');
	}

	protected function event(Page $page, array $arguments) {
		return new HookEvent(array(
			'object' => $page,
			'method' => 'edit',
			'arguments' => $arguments,
		));
	}
}
