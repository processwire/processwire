<?php namespace ProcessWire;

/**
 * Tests for PagesRaw ($pages->findRaw() and $pages->getRaw())
 *
 */
class WireTest_PagesRaw extends WireTest {

	protected $prefix = 'wiretest-pages-raw';

	public function execute() {
		$this->testPageReferenceStatus();
	}

	/**
	 * Remove fixtures from this or an earlier (interrupted) run
	 *
	 */
	protected function cleanup() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$templates = $this->wire()->templates;
		$fieldgroups = $this->wire()->fieldgroups;
		$name = str_replace('-', '_', $this->prefix);
		foreach($pages->find("name^=$this->prefix-, include=all") as $page) {
			$pages->delete($page);
		}
		$template = $templates->get($name);
		if($template) $templates->delete($template);
		$fieldgroup = $fieldgroups->get($name);
		if($fieldgroup) $fieldgroups->delete($fieldgroup);
		$field = $fields->get("{$name}_ref");
		if($field) $fields->delete($field);
	}

	/**
	 * Page references exclude unpublished pages unless allowed, and page_reference_status adjusts that
	 *
	 * processwire/processwire-issues#1969
	 *
	 */
	protected function testPageReferenceStatus() {
		$wire = $this->wire();
		$pages = $wire->pages;
		$fields = $wire->fields;
		$prefix = $this->prefix;
		$name = str_replace('-', '_', $prefix);
		$refName = "{$name}_ref";

		$this->cleanup();

		try {
			$field = new Field();
			$field->type = $wire->modules->get('FieldtypePage');
			$field->name = $refName;
			$field->set('derefAsPage', 0);
			$fields->save($field);

			$fieldgroup = new Fieldgroup();
			$fieldgroup->name = $name;
			$fieldgroup->add($fields->get('title'));
			$fieldgroup->add($field);
			$fieldgroup->save();

			$template = new Template();
			$template->name = $name;
			$template->fieldgroup = $fieldgroup;
			$template->save();

			$home = $pages->get(1);
			$pub = $pages->add($template, $home, "$prefix-pub", array('title' => 'Published'));
			$hidden = $pages->add($template, $home, "$prefix-hidden", array('title' => 'Hidden'));
			$hidden->addStatus(Page::statusHidden);
			$hidden->save();
			$unpub = $pages->add($template, $home, "$prefix-unpub", array('title' => 'Unpublished'));
			$unpub->addStatus(Page::statusUnpublished);
			$unpub->save();
			$host = $pages->add($template, $home, "$prefix-host", array('title' => 'Host'));
			$host->of(false);
			$host->set($refName, array($pub, $hidden, $unpub));
			$host->save();
			$pages->uncacheAll();

			$sel = "id=$host->id";
			$ids = array($pub->id, $hidden->id);
			$allIds = array($pub->id, $hidden->id, $unpub->id);
			$titles = array($pub->id => 'Published', $hidden->id => 'Hidden');

			// default: same as the unformatted field value, which excludes unpublished pages
			$value = $pages->getRaw($sel, $refName);
			$this->check('IDs exclude unpublished reference', $ids, array_map('intval', $value));
			$expected = $pages->get($sel)->getUnformatted($refName)->explode('id');
			$this->check('IDs match unformatted field value', $expected, array_map('intval', $value));
			$value = $pages->getRaw($sel, "$refName.title");
			$this->check('subfield excludes unpublished reference', $titles, $value);
			$value = $pages->getRaw($sel, "$refName.*");
			$this->check('all columns exclude unpublished reference', $ids, array_map('intval', array_column($value, 'data')));
			$value = $pages->getRaw($sel, "$refName.sort");
			$this->check('sort column excludes unpublished reference', 2, count($value));
			$value = $pages->getRaw($sel, array("$refName.id", "$refName.title"));
			$this->check('multiple subfields exclude unpublished reference', $ids, array_keys($value[$refName]));
			$value = $pages->getRaw($sel);
			$this->check('all fields exclude unpublished reference', $ids, array_map('intval', $value[$refName]));

			// page_reference_status in selector
			$tests = array(
				'<=max' => $allIds,
				'<unpublished' => $ids,
				'=unpublished' => array($unpub->id),
				'!=unpublished' => $ids,
				'>=unpublished' => array($unpub->id),
				'<hidden' => array($pub->id),
				'=hidden' => array($hidden->id),
				'!=hidden' => array($pub->id, $unpub->id),
			);
			foreach($tests as $status => $expected) {
				$value = $pages->getRaw("$sel, page_reference_status$status", "$refName.title");
				$this->check("page_reference_status$status", $expected, array_keys($value));
			}

			// page_reference_status as option
			$value = $pages->findRaw($sel, $refName, array('page_reference_status' => '<=max'));
			$this->check('page_reference_status option', $allIds, array_map('intval', $value[$host->id]));

			$exception = '';
			try {
				$pages->getRaw("$sel, page_reference_status<bogus", $refName);
			} catch(WireException $e) {
				$exception = 'unknown';
			}
			$this->check('unknown status throws exception', 'unknown', $exception);
			$exception = '';
			try {
				$pages->getRaw("$sel, page_reference_status%=hidden", $refName);
			} catch(WireException $e) {
				$exception = 'operator';
			}
			$this->check('unsupported operator throws exception', 'operator', $exception);

			// allowUnpub field setting includes unpublished references, as the unformatted value does
			$field->set('allowUnpub', 1);
			$fields->save($field);
			$value = $pages->getRaw($sel, $refName);
			$this->check('allowUnpub includes unpublished reference', $allIds, array_map('intval', $value));
			$value = $pages->getRaw("$sel, page_reference_status<unpublished", $refName);
			$this->check('page_reference_status overrides allowUnpub', $ids, array_map('intval', $value));

		} finally {
			$this->cleanup();
		}
	}
}
