<?php namespace ProcessWire;

/**
 * Tests for FieldtypeOptions
 *
 */
class WireTest_FieldtypeOptions extends WireTest {

	protected $fieldName = 'test_options';
	protected $fieldNameMulti = 'test_options_multi';

	public function init() {
		$this->ensureFields();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$nameMulti = $this->fieldNameMulti;
		$field = $fields->get($name);
		$fieldMulti = $fields->get($nameMulti);

		$allOptions = $field->getOptions();
		if($allOptions->count() !== 3) {
			$field->setOptionsString("Red\n#00ff00|Green\nBlue");
			$field->save();
			$allOptions = $field->getOptions();
		}
		$this->li('Options defined: ' . $field->getOptionsString());

		if($allOptions->count() !== 3) {
			$this->fail('Expected 3 options, got: ' . $allOptions->count());
		}
		$this->li('Option count (3) verified');

		$redOption = $allOptions->getByTitle('Red');
		$greenOption = $allOptions->getByTitle('Green');
		$blueOption = $allOptions->getByTitle('Blue');

		if(!$redOption || !$greenOption || !$blueOption) {
			$this->fail('Could not find expected options by title');
		}

		$page->set($name, $redOption->id);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(!$val->count() || $val->first()->title !== 'Red') {
			$this->fail('Expected Red selected by ID, got: ' . var_export($val->first(), true));
		}
		$this->li("Set by ID verified: selected '{$val->first()->title}'");

		$page->set($name, 'Green');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(!$val->count() || $val->first()->title !== 'Green') {
			$this->fail('Expected Green selected by title, got: ' . var_export((string) $val, true));
		}
		$this->li("Set by title verified: selected '{$val->first()->title}'");

		$page->set($name, '#00ff00');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(!$val->count() || $val->first()->title !== 'Green') {
			$this->fail("Expected Green selected by value '#00ff00', got: " . var_export((string) $val, true));
		}
		$this->li("Set by value ('#00ff00') verified: selected '{$val->first()->title}'");

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if($val->count() !== 0) $this->fail('Expected empty selection, got count: ' . $val->count());
		$this->li('Clear selection (empty) verified');

		if(!($val instanceof SelectableOptionArray)) {
			$this->fail('Expected SelectableOptionArray, got: ' . get_class($val));
		}
		$this->li('Value type is SelectableOptionArray verified');

		$multiOptions = $fieldMulti->getOptions();
		if($multiOptions->count() !== 3) {
			$fieldMulti->setOptionsString("Red\n#00ff00|Green\nBlue");
			$fieldMulti->save();
			$multiOptions = $fieldMulti->getOptions();
		}
		$redM = $multiOptions->getByTitle('Red');
		$greenM = $multiOptions->getByTitle('Green');
		$blueM = $multiOptions->getByTitle('Blue');

		$page->set($nameMulti, array($redM->id, $blueM->id));
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$val = $page->get($nameMulti);
		if($val->count() !== 2) $this->fail('Expected 2 selected options, got: ' . $val->count());
		if(!$val->hasTitle('Red') || !$val->hasTitle('Blue')) {
			$this->fail('Expected Red and Blue selected, got: ' . $val->implode('|', 'title'));
		}
		$this->li('Multi-select by array of IDs verified: ' . $val->implode(', ', 'title'));

		$page->set($nameMulti, $redM->id . '|' . $greenM->id . '|' . $blueM->id);
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$val = $page->get($nameMulti);
		if($val->count() !== 3) $this->fail('Expected 3 selected options, got: ' . $val->count());
		$this->li('Multi-select by pipe-separated IDs verified: ' . $val->implode(', ', 'title'));

		$page->of(false);
		$page->set($nameMulti, array($redM->id));
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$page->get($nameMulti)->addByTitle('Blue');
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$val = $page->get($nameMulti);
		if(!$val->hasTitle('Blue')) {
			$this->fail('Expected Blue added by title, got: ' . $val->implode('|', 'title'));
		}
		$this->li("addByTitle('Blue') verified");

		$page->of(false);
		$page->get($nameMulti)->removeByTitle('Blue');
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$val = $page->get($nameMulti);
		if($val->hasTitle('Blue')) {
			$this->fail('Expected Blue removed by title, got: ' . $val->implode('|', 'title'));
		}
		$this->li("removeByTitle('Blue') verified");

		$page->set($nameMulti, array($redM->id, $greenM->id));
		$page->save($nameMulti);
		$page = $pages->getFresh($page->id);
		$str = (string) $page->get($nameMulti);
		$ids = explode('|', $str);
		if(count($ids) !== 2) {
			$this->fail('Expected pipe-separated string of 2 IDs, got: ' . var_export($str, true));
		}
		$this->li("Cast to string returns pipe-separated IDs: '$str'");

		$page->set($name, 'Green');
		$page->save($name);
		$greenId = $field->getOptions()->getByTitle('Green')->id;
		$selectors = array(
			"template=test, $name=Green",
			"template=test, $name.title=Green",
			"template=test, $name.value=\"#00ff00\"",
			"template=test, $name.id=$greenId",
			"template=test, $name.count>0",
			"template=test, $name!=Red",
			"template=test, $name!=\"\"",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, '');
		$page->save($name);
		$p = $pages->findOne("template=test, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");

		$redId = $fieldMulti->getOptions()->getByTitle('Red')->id;
		$blueId = $fieldMulti->getOptions()->getByTitle('Blue')->id;
		$page->set($nameMulti, array($redId, $blueId));
		$page->save($nameMulti);
		$selectors = array(
			"template=test, $nameMulti=Red",
			"template=test, $nameMulti=Blue",
			"template=test, $nameMulti=Red|Blue|Green",
			"template=test, $nameMulti.count>1",
			"template=test, $nameMulti!=Green",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}
	}

	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypeOptions');
		$field = $fields->get($this->fieldName);
		$fieldMulti = $fields->get($this->fieldNameMulti);

		if(!$field) {
			$field = new OptionsField();
			$field->name = $this->fieldName;
			$field->type = $fieldtype;
			$field->label = 'Test Options';
			$field->inputfieldClass = 'InputfieldSelect';
			$field->save();
			$this->li("Created field: $field->name");
		}

		if(!$fieldMulti) {
			$fieldMulti = new OptionsField();
			$fieldMulti->name = $this->fieldNameMulti;
			$fieldMulti->type = $fieldtype;
			$fieldMulti->label = 'Test Options Multi';
			$fieldMulti->inputfieldClass = 'InputfieldCheckboxes';
			$fieldMulti->save();
			$this->li("Created field: $fieldMulti->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		foreach(array($field, $fieldMulti) as $f) {
			if(!$fieldgroup->hasField($f)) {
				$fieldgroup->add($f);
				$fieldgroup->save();
				$this->li("Added field to fieldgroup: $f->name");
			}
		}
	}
}
