<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $templates API variable
 *
 */
class WireTest_Templates extends WireTest {

	protected $prefix = WireTests::fieldPrefix . 'templates';
	protected $templateName = '';

	public function init() {
		$this->templateName = $this->name('basic');
		$this->cleanup();
	}

	public function execute() {
		$templates = $this->wire()->templates;

		$this->check('$templates is Templates', true, $templates instanceof Templates);
		$this->check('$templates->get(home) returns Template', true, $templates->get('home') instanceof Template);
		$this->check('$templates->get(missing) returns null', null, $templates->get($this->name('missing')));
		$this->check('$templates is iterable over Template objects', true, $this->firstTemplateIsTemplate());

		$this->testCreateSaveDelete();
		$this->testRawAndFresh();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testCreateSaveDelete() {
		$templates = $this->wire()->templates;
		$template = $templates->add($this->templateName, array(
			'label' => 'WireTests Template',
			'tags' => 'wiretests',
		));

		$this->check('add() returns Template', true, $template instanceof Template);
		$this->check('add() saves template', true, $template->id > 0);
		$this->check('add() uses requested name', $this->templateName, $template->name);
		$this->check('add() creates matching fieldgroup', $this->templateName, $template->fieldgroup->name);
		$this->check('add() applies label', 'WireTests Template', $template->label);
		$this->check('add() applies tags', 'wiretests', $template->tags);

		$template->label = 'Updated WireTests Template';
		$template->cache_time = 123;
		$this->check('save() updates template', true, $templates->save($template));
		$this->check('save() persisted updated label', 'Updated WireTests Template', $templates->get($this->templateName)->label);
	}

	protected function testRawAndFresh() {
		$templates = $this->wire()->templates;
		$template = $templates->get($this->templateName);
		$savedLabel = $template->label;
		$rawByName = $templates->getRaw($template->name);
		$rawById = $templates->getRaw($template->id);
		$freshByName = $templates->getFresh($template->name);
		$freshById = $templates->getFresh($template->id);

		$this->check('getRaw(name) returns template row array', true, is_array($rawByName));
		$this->check('getRaw(name) includes template ID', $template->id, (int) $rawByName['id']);
		$this->check('getRaw(id) includes template name', $template->name, $rawById['name']);
		$this->check('getRaw() leaves data column encoded', true, is_string($rawByName['data']));
		$this->check('getRaw() includes fieldgroups_id', $template->fieldgroup->id, (int) $rawByName['fieldgroups_id']);
		$this->check('getFresh(name) returns Template', true, $freshByName instanceof Template);
		$this->check('getFresh(id) returns Template', true, $freshById instanceof Template);
		$this->check('getFresh() preserves template name', $template->name, $freshByName->name);
		$this->check('getFresh() decodes template data settings', $savedLabel, $freshByName->label);
		$this->check('getFresh() preserves native columns', 123, $freshById->cache_time);
		$this->check('getFresh() returns a separate instance', true, $freshByName !== $template);

		$template->label = 'Unsaved cached label';
		$template->cache_time = 456;
		$freshAfterMutation = $templates->getFresh($template->name);
		$this->check('getFresh() bypasses unsaved cached data changes', $savedLabel, $freshAfterMutation->label);
		$this->check('getFresh() bypasses unsaved cached column changes', 123, $freshAfterMutation->cache_time);
		$this->check('getRaw(missing) returns null', null, $templates->getRaw($this->name('missing_raw')));
		$this->check('getFresh(missing) returns null', null, $templates->getFresh($this->name('missing_fresh')));

		$template->label = $savedLabel;
		$template->cache_time = 123;
	}

	protected function firstTemplateIsTemplate() {
		foreach($this->wire()->templates as $template) return $template instanceof Template;
		return false;
	}

	protected function cleanup() {
		$templates = $this->wire()->templates;
		$template = $templates->get($this->templateName);
		if($template && $template->id) {
			try {
				$templates->delete($template);
			} catch(\Exception $e) {
				// Leave failures visible in assertions if cleanup affects the next run.
			}
		}
	}

	protected function name($suffix) {
		return $this->prefix . '_' . $suffix;
	}
}
