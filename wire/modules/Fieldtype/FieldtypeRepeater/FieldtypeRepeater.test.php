<?php namespace ProcessWire;

/**
 * Tests for FieldtypeRepeater
 *
 */
class WireTest_FieldtypeRepeater extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'repeater';
	protected $subFieldName = WireTests::fieldPrefix . 'headline';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;
		$subTextField = $fields->get($this->subFieldName);

		$page->of(false);
		foreach($page->get($name) as $item) {
			$page->get($name)->remove($item);
		}
		$page->save($name);

		$val = $page->get($name);
		if(!($val instanceof RepeaterPageArray)) $this->fail('Expected RepeaterPageArray, got: ' . get_class($val));
		$this->li('Empty value is RepeaterPageArray verified');

		$item1 = $page->get($name)->getNewItem();
		if(!($item1 instanceof RepeaterPage)) $this->fail('Expected RepeaterPage from getNewItem(), got: ' . get_class($item1));
		$item1->set($subTextField->name, 'First Item');
		$item1->save();
		$page->save($name);
		$this->li('getNewItem() returns RepeaterPage verified');

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$items = $page->get($name);
		if($items->count() !== 1) $this->fail('Expected 1 item after add, got: ' . $items->count());
		if($items->first()->get($subTextField->name) !== 'First Item') {
			$this->fail("Expected 'First Item', got: " . var_export($items->first()->get($subTextField->name), true));
		}
		$this->li("Item value '$subTextField->name' = 'First Item' verified");

		$item2 = $page->get($name)->getNewItem();
		$item2->set($subTextField->name, 'Second Item');
		$item2->save();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 2) {
			$this->fail('Expected 2 items, got: ' . $page->get($name)->count());
		}
		$this->li('Two items added, count=2 verified');

		$item = $page->get($name)->first();
		if($item->getForPage()->id !== $page->id) {
			$this->fail("Expected getForPage() to return page id=$page->id, got: " . $item->getForPage()->id);
		}
		if($item->getForField()->name !== $name) {
			$this->fail("Expected getForField() to return field '$name', got: " . $item->getForField()->name);
		}
		$this->li('getForPage() and getForField() verified');

		$item = $page->get($name)->first();
		$item->addStatus(Page::statusUnpublished);
		$item->save();

		// unpublishing via addStatus() must not leave the item in the "new item pending publish"
		// state (statusOn + statusUnpublished), which the page editor would auto-publish on save
		$item = $pages->getFresh($item->id);
		if(!$item->hasStatus(Page::statusUnpublished)) {
			$this->fail('Expected unpublished status after addStatus(statusUnpublished)');
		}
		if($item->hasStatus(Page::statusOn)) {
			$this->fail('Expected statusOn to be removed when unpublishing item via addStatus(), got status: ' . $item->status);
		}
		$this->li('addStatus(statusUnpublished) removes statusOn (item unpublished rather than pending publish) verified');

		$page = $pages->getFresh($page->id);
		$page->of(true);
		$countFormatted = $page->get($name)->count();

		$page->of(false);
		$countUnformatted = $page->get($name)->count();

		if($countFormatted >= $countUnformatted) {
			$this->fail("Expected OF=on to exclude unpublished items (got $countFormatted), OF=off to include all (got $countUnformatted)");
		}
		$this->li("OF=on excludes unpublished: count=$countFormatted; OF=off includes all: count=$countUnformatted");

		$page->of(false);
		$item = $page->get($name)->first();
		$item->removeStatus(Page::statusUnpublished);
		$item->save();

		// publishing via removeStatus() must restore statusOn, consistent with items
		// published from the page editor
		$item = $pages->getFresh($item->id);
		if($item->hasStatus(Page::statusUnpublished)) {
			$this->fail('Expected published status after removeStatus(statusUnpublished)');
		}
		if(!$item->hasStatus(Page::statusOn)) {
			$this->fail('Expected statusOn to be restored when publishing item via removeStatus(), got status: ' . $item->status);
		}
		$this->li('removeStatus(statusUnpublished) restores statusOn verified');

		// string status names should behave the same as the constants
		$item->addStatus('unpublished');
		if($item->hasStatus(Page::statusOn) || !$item->hasStatus(Page::statusUnpublished)) {
			$this->fail("Expected addStatus('unpublished') to match addStatus(statusUnpublished), got status: " . $item->status);
		}
		$item->removeStatus('unpublished');
		if(!$item->hasStatus(Page::statusOn) || $item->hasStatus(Page::statusUnpublished)) {
			$this->fail("Expected removeStatus('unpublished') to match removeStatus(statusUnpublished), got status: " . $item->status);
		}
		$this->li('addStatus/removeStatus by string status name verified');

		// ready pages must keep their statusOn + statusHidden + statusUnpublished combination
		$field = $fields->get($name);
		/** @var FieldtypeRepeater $fieldtype */
		$fieldtype = $field->type;
		$readyPage = $fieldtype->getBlankRepeaterPage($page, $field);
		foreach(array('statusOn' => Page::statusOn, 'statusHidden' => Page::statusHidden, 'statusUnpublished' => Page::statusUnpublished) as $statusName => $status) {
			if(!$readyPage->hasStatus($status)) {
				$this->fail("Expected ready page to have $statusName, got status: " . $readyPage->status);
			}
		}
		$this->li('Ready page keeps statusOn + statusHidden + statusUnpublished verified');

		// setting status directly must remain available for the "pending publish" state
		$item->status = Page::statusOn | Page::statusUnpublished;
		if(!$item->hasStatus(Page::statusOn) || !$item->hasStatus(Page::statusUnpublished)) {
			$this->fail('Expected direct status set to allow statusOn + statusUnpublished, got status: ' . $item->status);
		}
		$item->status = Page::statusOn;
		$item->save();
		$this->li('Direct status set can still express the pending publish state verified');

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$toRemove = $page->get($name)->first();
		$page->get($name)->remove($toRemove);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 1) {
			$this->fail('Expected 1 item after remove, got: ' . $page->get($name)->count());
		}
		$this->li('remove() item verified, count=1');

		$byIndex = $page->get($name)->eq(0);
		if(!($byIndex instanceof RepeaterPage)) $this->fail('Expected RepeaterPage from eq(0), got: ' . get_class($byIndex));
		$this->li("eq(0) index access verified: '" . $byIndex->get($subTextField->name) . "'");

		$page->of(false);
		$items = $page->get($name);
		foreach($items as $item) $items->remove($item);
		$page->save($name);
		$item = $page->get($name)->getNewItem();
		$item->set($subTextField->name, 'Selector Test Item');
		$item->save();
		$page->save($name);
		$sub = $subTextField->name;
		$selectors = array(
			"template=$template, $name.count>0",
			"template=$template, $name.count=1",
			"template=$template, $name.$sub*=Selector Test",
			"template=$template, $name.$sub~=Item",
			"template=$template, $name.$sub^=Selector",
		);
		foreach($selectors as $selector) {
			$p = $pages->get($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$items = $page->get($name);
		foreach($items as $item) $items->remove($item);
		$page->save($name);
		$p = $pages->get("template=$template, $name.count=0");
		if($p->id !== $page->id) $this->fail("Selector failed: $name.count=0");
		$this->li("Selector passed: $name.count=0");

		$field = $fields->get($name);
		$repeaterTitle = $field->repeaterTitle;
		try {
			$field->repeaterTitle = "{{$subTextField->name}} #f5f5f5";
			$field->save();
			$item = $page->get($name)->getNewItem();
			$item->set($subTextField->name, 'Light color test');
			$item->save();
			$page->save($name);

			$this->wire()->wire('adminTheme', $this->wire()->modules->get('AdminThemeUikit'));
			$page = $pages->getFresh($page->id);
			$page->of(false);
			$inputfield = $field->type->getInputfield($page, $field);
			$inputfield->render();
			$styles = $this->wire()->adminTheme->getExtraMarkup();
			if(strpos($styles['head'], 'background-color: #f5f5f5; outline-color: #f5f5f5; color: #111;') === false) {
				$this->fail('Expected light custom repeater color to use dark header text');
			}
			if(strpos($styles['head'], '--pw-text-color: #111;') === false) {
				$this->fail('Expected light custom repeater color to use a dark text color variable');
			}
			$this->li('Light custom repeater colors use dark header text verified');
		} finally {
			$field->repeaterTitle = $repeaterTitle;
			$field->save();
			$page = $pages->getFresh($page->id);
			$page->of(false);
			foreach($page->get($name) as $item) $page->get($name)->remove($item);
			$page->save($name);
		}

		// verify that a "ready" item added to satisfy repeaterMinItems is rendered fully loaded
		// and open, rather than as a collapsed ajax placeholder, so that its required field
		// states are visible without first having to click to open/load it
		$minItems = $field->repeaterMinItems;
		$loading = $field->repeaterLoading;
		$collapse = $field->repeaterCollapse;
		$required = $subTextField->required;
		try {
			$field->repeaterMinItems = 1;
			$field->repeaterLoading = FieldtypeRepeater::loadingAll;
			$field->repeaterCollapse = FieldtypeRepeater::collapseExisting;
			$field->save();
			$subTextField->required = 1;
			$subTextField->save();

			$page = $pages->getFresh($page->id);
			$page->of(false);
			foreach($page->get($name) as $item) $page->get($name)->remove($item);
			$page->save($name);

			$page = $pages->getFresh($page->id);
			$page->of(false);
			$inputfield = $field->type->getInputfield($page, $field);
			$out = $inputfield->render();

			if(strpos($out, 'InputfieldRepeaterMinItem') === false) {
				$this->fail('Expected a min item to be present in rendered markup');
			} else if(strpos($out, 'InputfieldRepeaterMinItem') !== false
				&& preg_match('/InputfieldRepeaterMinItem[^"\']*InputfieldStateCollapsed|InputfieldStateCollapsed[^"\']*InputfieldRepeaterMinItem/', $out)) {
				$this->fail('Expected min item to not be collapsed');
			} else {
				$this->li('Min item not rendered collapsed, verified');
			}

			if(strpos($out, 'InputfieldStateRequired') === false) {
				$this->fail('Expected required field state to be present in min item markup (not ajax deferred)');
			} else {
				$this->li('Min item required field state visible without ajax load, verified');
			}
		} finally {
			$field->repeaterMinItems = $minItems;
			$field->repeaterLoading = $loading;
			$field->repeaterCollapse = $collapse;
			$field->save();
			$subTextField->required = $required;
			$subTextField->save();
			$page = $pages->getFresh($page->id);
			$page->of(false);
			foreach($page->get($name) as $item) $page->get($name)->remove($item);
			$page->save($name);
		}
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypeRepeater');
		$fieldtype->getFieldClass();
		$modules->get('FieldtypeText');

		$subTextField = $this->ensureTextField();
		$field = $fields->get($this->fieldName);

		if(!$field) {
			$field = new RepeaterField();
			$field->name = $this->fieldName;
			$field->type = $fieldtype;
			$field->label = 'Test Repeater';
			$field->save();
			$this->li("Created field: $field->name");
		}

		$repeaterTemplate = $fieldtype->_getRepeaterTemplate($field);
		$repeaterFieldgroup = $repeaterTemplate->fieldgroup;
		if(!$repeaterFieldgroup->hasField($subTextField)) {
			$repeaterFieldgroup->add($subTextField);
			$repeaterFieldgroup->save();
		}

		$repeaterFields = is_array($field->repeaterFields) ? $field->repeaterFields : array();
		if(!in_array($subTextField->id, $repeaterFields)) {
			$repeaterFields[] = $subTextField->id;
			$field->repeaterFields = $repeaterFields;
			$field->save();
			$this->li("Repeater template: $repeaterTemplate->name, sub-fields: $subTextField->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $field->name");
		}
	}

	protected function ensureTextField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$field = $fields->get($this->subFieldName);

		if($field) return $field;

		$field = new TextField();
		$field->name = $this->subFieldName;
		$field->type = $modules->get('FieldtypeText');
		$field->label = 'Headline';
		$field->textformatters = array('TextformatterEntities');
		$field->save();
		$this->li("Created sub-field: $field->name");

		return $field;
	}
}
