<?php namespace ProcessWire;

/**
 * Tests for ProcessPageClone
 *
 */
class WireTest_ProcessPageClone extends WireTest {

	protected $field;
	protected $textField;
	protected $fieldgroup;
	protected $root;
	protected $clone;

	public function allow() {
		return $this->wire()->modules->isInstalled('FieldtypeRepeater');
	}

	public function init() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$template = $this->getTestTemplate();
		$this->fieldgroup = $template->fieldgroup;

		$this->textField = $fields->new(
			'FieldtypeText',
			'wire_test_page_clone_repeater_text',
			'Page clone repeater test text'
		);
		$this->textField->save();

		$fieldtype = $modules->get('FieldtypeRepeater');
		$fieldtype->getFieldClass();
		$this->field = new RepeaterField();
		$this->field->name = 'wire_test_page_clone_repeater';
		$this->field->label = 'Page clone repeater test';
		$this->field->type = $fieldtype;
		$this->field->save();

		$repeaterTemplate = $fieldtype->_getRepeaterTemplate($this->field);
		$repeaterFieldgroup = $repeaterTemplate->fieldgroup;
		$repeaterFieldgroup->add($this->textField);
		$repeaterFieldgroup->save();
		$this->field->repeaterFields = array($this->textField->id);
		$this->field->save();

		$this->fieldgroup->add($this->field);
		$this->fieldgroup->save();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$template = $this->getTestTemplate();
		$this->root = $pages->new(array(
			'template' => $template,
			'parent' => 1,
			'name' => 'wire-test-page-clone-root',
			'title' => 'Page clone root',
		));
		$child = $pages->new(array(
			'template' => $template,
			'parent' => $this->root,
			'name' => 'wire-test-page-clone-child',
			'title' => 'Page clone child',
		));
		$child->of(false);
		$item = $child->get($this->field->name)->getNewItem();
		$item->set($this->textField->name, 'Repeater content');
		$item->save();
		$child->save($this->field->name);

		$module = $this->wire(new WireTestable_ProcessPageClone());
		$module->addCloneTreeUnpublishedHookForTest($this->root);
		$this->clone = $pages->clone($this->root, $this->root->parent, true);
		$cloneChild = $this->clone->child('include=all');

		$this->check('Tree clone root remains published', false, $this->clone->isUnpublished());
		$this->check('Tree clone child is unpublished', true, $cloneChild->isUnpublished());
		$cloneChild->of(true);
		$this->check('Tree clone retains formatted repeater items', 1, $cloneChild->get($this->field->name)->count());
		$this->check(
			'Tree clone retains repeater item content',
			'Repeater content',
			$cloneChild->get($this->field->name)->first()->get($this->textField->name)
		);
	}

	public function finish() {
		$pages = $this->wire()->pages;
		foreach(array($this->clone, $this->root) as $page) {
			if(!$page instanceof Page || !$page->id) continue;
			$page = $pages->get($page->id);
			if($page->id) $pages->delete($page, true);
		}
		if($this->field && $this->field->id) {
			if($this->fieldgroup && $this->fieldgroup->hasField($this->field)) {
				$this->fieldgroup->remove($this->field);
				$this->fieldgroup->save();
			}
			$this->wire()->fields->delete($this->field);
		}
		if($this->textField && $this->textField->id) {
			$this->wire()->fields->delete($this->textField);
		}
	}
}

/**
 * Exposes the clone-tree hook setup required by this test.
 *
 */
class WireTestable_ProcessPageClone extends ProcessPageClone {

	public function addCloneTreeUnpublishedHookForTest(Page $page) {
		$this->addCloneTreeUnpublishedHook($page);
	}
}
