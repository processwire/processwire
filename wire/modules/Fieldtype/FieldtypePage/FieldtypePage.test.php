<?php namespace ProcessWire;

/**
 * Tests for FieldtypePage
 *
 */
class WireTest_FieldtypePage extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'page';
	protected $fieldNameOrFalse = WireTests::fieldPrefix . 'page_or_false';
	protected $fieldNameOrNull = WireTests::fieldPrefix . 'page_or_null';

	public function init() {
		$this->ensureFields();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$refPage = $pages->get(1);
		if(!$refPage->id) $this->fail('Could not load reference page (id=1)');

		$name = $this->fieldName;
		$template = WireTests::templateName;
		$page->of(false);
		$page->set($name, $refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if(!($val instanceof PageArray)) $this->fail('Expected PageArray, got: ' . get_class($val));
		if($val->count() !== 1 || $val->first()->id !== $refPage->id) {
			$this->fail("Expected PageArray with id=$refPage->id, got count=$val->count()");
		}
		$this->li('derefAsPage=0: set by Page object, got PageArray with 1 item verified');

		$page->set($name, $refPage->id);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if($val->count() !== 1 || $val->first()->id !== $refPage->id) {
			$this->fail("Expected page id=$refPage->id, got: " . $val->count());
		}
		$this->li('derefAsPage=0: set by page ID verified');

		$page->set($name, null);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if(!($val instanceof PageArray) || $val->count() !== 0) {
			$this->fail('Expected empty PageArray, got: ' . var_export($val, true));
		}
		$this->li('derefAsPage=0: empty value returns empty PageArray verified');

		$page->of(false);
		$page->get($name)->add($refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 1) {
			$this->fail('Expected 1 after add(), got: ' . $page->get($name)->count());
		}
		$this->li('derefAsPage=0: add() verified');

		$page->of(false);
		$page->get($name)->remove($refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 0) {
			$this->fail('Expected 0 after remove(), got: ' . $page->get($name)->count());
		}
		$this->li('derefAsPage=0: remove() verified');

		$name1 = $this->fieldNameOrFalse;
		$page->set($name1, $refPage->id);
		$page->save($name1);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name1);
		if(!($val instanceof Page) || $val->id !== $refPage->id) {
			$this->fail("Expected Page with id=$refPage->id, got: " . var_export($val, true));
		}
		$this->li('derefAsPage=1: populated returns Page verified');

		$page->set($name1, null);
		$page->save($name1);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name1);
		if($val !== false) {
			$this->fail('Expected false when empty with derefAsPage=1, got: ' . var_export($val, true));
		}
		$this->li('derefAsPage=1: empty returns false verified');

		$name2 = $this->fieldNameOrNull;
		$page->set($name2, $refPage->id);
		$page->save($name2);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name2);
		if(!($val instanceof Page) || $val->id !== $refPage->id) {
			$this->fail("Expected Page with id=$refPage->id, got: " . var_export($val, true));
		}
		$this->li('derefAsPage=2: populated returns Page verified');

		$page->set($name2, null);
		$page->save($name2);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name2);
		if(!($val instanceof NullPage)) {
			$this->fail('Expected NullPage when empty with derefAsPage=2, got: ' . get_class($val));
		}
		$this->li('derefAsPage=2: empty returns NullPage verified');

		$name = $this->fieldName;
		$page->of(false);
		$page->set($name, $refPage);
		$page->save($name);
		$refTemplateName = $refPage->template->name;
		$selectors = array(
			"template=$template, $name=$refPage->id",
			"template=$template, $name=$refPage->name",
			"template=$template, $name=$refPage->path",
			"template=$template, $name.count>0",
			"template=$template, $name!=\"\"",
			"template=$template, $name.template=$refTemplateName",
		);
		foreach($selectors as $selector) {
			$p = $pages->get($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, null);
		$page->save($name);
		$p = $pages->get("template=$template, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");

		$this->testTrashPageRefs();
		$this->testTrashPageRefsChunkBoundary();
	}

	/**
	 * Test optional removal and restoration of references to trashed pages
	 *
	 */
	protected function testTrashPageRefs() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$source = $this->getTestPage();
		$template = $source->template;
		$fieldMulti = $fields->get($this->fieldName);
		$fieldSingle = $fields->get($this->fieldNameOrFalse);
		$settings = array(
			$fieldMulti->id => (int) $fieldMulti->get('trashPageRefs'),
			$fieldSingle->id => (int) $fieldSingle->get('trashPageRefs'),
		);
		$targetParent = null;
		$targetChild = null;

		try {
			$fieldMulti->trashPageRefs = FieldtypePage::trashPageRefsRemove;
			$fieldSingle->trashPageRefs = FieldtypePage::trashPageRefsRemove;
			$fieldMulti->save();
			$fieldSingle->save();

			$targetParent = $pages->new(array(
				'template' => $template,
				'parent' => $source,
				'title' => 'FieldtypePage trash reference parent',
			));
			$targetChild = $pages->new(array(
				'template' => $template,
				'parent' => $targetParent,
				'title' => 'FieldtypePage trash reference child',
			));

			$source->of(false);
			$source->set($fieldMulti->name, null);
			$source->get($fieldMulti->name)->add($targetParent)->add($targetChild);
			$source->set($fieldSingle->name, $targetParent);
			$source->save($fieldMulti->name);
			$source->save($fieldSingle->name);

			$this->check('Trash branch succeeds', true, $pages->trash($targetParent));
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$this->check('Trashed branch references removed from multi field', 0, $source->get($fieldMulti->name)->count());
			$this->check('Trashed page reference removed from single field', false, $source->get($fieldSingle->name));
			$this->check(
				'Empty-count selector matches after references are removed',
				1,
				$pages->count("id=$source->id, $fieldMulti->name.count=0, include=all")
			);

			$current = $pages->get(1);
			$source->get($fieldMulti->name)->add($current);
			$source->set($fieldSingle->name, $current);
			$source->save($fieldMulti->name);
			$source->save($fieldSingle->name);

			// Defining a parent also supports test pages whose initial sort value cannot
			// be parsed from their temporary trash name by getRestoreInfo().
			$targetParent->parent = $source;
			$this->check('Restore branch succeeds', true, $pages->restore($targetParent));
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$this->check(
				'Restored branch references retain order before current multi value',
				array($targetParent->id, $targetChild->id, $current->id),
				$source->get($fieldMulti->name)->explode('id')
			);
			$this->check(
				'Current single value takes precedence over restored reference',
				$current->id,
				$source->get($fieldSingle->name)->id
			);
			$this->check(
				'Restored parent reference metadata removed',
				null,
				$targetParent->meta(FieldtypePageTrash::metaKey)
			);
			$this->check(
				'Restored child reference metadata removed',
				null,
				$targetChild->meta(FieldtypePageTrash::metaKey)
			);

		} finally {
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$source->set($fieldMulti->name, null);
			$source->set($fieldSingle->name, null);
			$source->save($fieldMulti->name);
			$source->save($fieldSingle->name);

			foreach(array($targetChild, $targetParent) as $target) {
				if(!$target instanceof Page || !$target->id) continue;
				$target = $pages->get($target->id);
				if($target->id) $pages->delete($target, true);
			}

			$fieldMulti->trashPageRefs = $settings[$fieldMulti->id];
			$fieldSingle->trashPageRefs = $settings[$fieldSingle->id];
			$fieldMulti->save();
			$fieldSingle->save();
		}
	}

	/**
	 * Test trash reference handling across the 500-page chunk boundary
	 *
	 */
	protected function testTrashPageRefsChunkBoundary() {
		$pages = $this->wire()->pages;
		$database = $this->wire()->database;
		$source = $this->getTestPage();
		$field = $this->wire()->fields->get($this->fieldName);
		$setting = (int) $field->get('trashPageRefs');
		$numTargets = FieldtypePage::deleteChunkSize + 1;
		$branch = null;
		$targetIds = array();

		try {
			$field->trashPageRefs = FieldtypePage::trashPageRefsRemove;
			$field->save();
			$branch = $pages->new(array(
				'template' => $source->template,
				'parent' => $source,
				'title' => 'FieldtypePage trash chunk branch',
			));
			$value = $pages->newPageArray();
			for($n = 1; $n <= $numTargets; $n++) {
				$target = $pages->new(array(
					'template' => $source->template,
					'parent' => $branch,
					'name' => "wire-test-trash-ref-$n",
					'title' => "FieldtypePage trash reference $n",
				));
				$value->add($target);
				$targetIds[] = $target->id;
			}

			$source->of(false);
			$source->set($field->name, $value);
			$source->save($field->name);
			$this->check('Chunk-boundary branch trash succeeds', true, $pages->trash($branch));
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$this->check('Chunk-boundary references removed', 0, $source->get($field->name)->count());

			$ids = implode(',', $targetIds);
			$query = $database->prepare(
				"SELECT COUNT(*) FROM pages_meta WHERE name=:name AND source_id IN($ids)"
			);
			$query->bindValue(':name', FieldtypePageTrash::metaKey);
			$query->execute();
			$this->check('Chunk-boundary metadata saved for every target', $numTargets, (int) $query->fetchColumn());

			$branch->parent = $source;
			$this->check('Chunk-boundary branch restore succeeds', true, $pages->restore($branch));
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$value = $source->get($field->name);
			$this->check('Chunk-boundary references restored', $numTargets, $value->count());
			$this->check('Chunk-boundary first reference retains order', reset($targetIds), $value->first()->id);
			$this->check('Chunk-boundary last reference retains order', end($targetIds), $value->last()->id);
			$query->execute();
			$this->check('Chunk-boundary metadata removed after restore', 0, (int) $query->fetchColumn());

		} finally {
			$source = $pages->getFresh($source->id);
			$source->of(false);
			$source->set($field->name, null);
			$source->save($field->name);
			if($branch instanceof Page && $branch->id) {
				$branch = $pages->get($branch->id);
				if($branch->id) $pages->delete($branch, true);
			}
			$field->trashPageRefs = $setting;
			$field->save();
		}
	}

	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypePage');
		$this->ensureField($fields, $fieldtype, $this->fieldName, 'Test Page', FieldtypePage::derefAsPageArray);
		$this->ensureField($fields, $fieldtype, $this->fieldNameOrFalse, 'Test Page Or False', FieldtypePage::derefAsPageOrFalse);
		$this->ensureField($fields, $fieldtype, $this->fieldNameOrNull, 'Test Page Or NullPage', FieldtypePage::derefAsPageOrNullPage);

		$fieldgroup = $page->template->fieldgroup;
		foreach(array($this->fieldName, $this->fieldNameOrFalse, $this->fieldNameOrNull) as $name) {
			$field = $fields->get($name);
			if(!$fieldgroup->hasField($field)) {
				$fieldgroup->add($field);
				$fieldgroup->save();
				$this->li("Added field to fieldgroup: $field->name");
			}
		}
	}

	protected function ensureField(Fields $fields, Fieldtype $fieldtype, $name, $label, $derefAsPage) {
		$field = $fields->get($name);
		if($field) return;

		$field = new PageField();
		$field->name = $name;
		$field->type = $fieldtype;
		$field->label = $label;
		$field->derefAsPage = $derefAsPage;
		$field->save();
		$this->li("Created field: $field->name");
	}
}
