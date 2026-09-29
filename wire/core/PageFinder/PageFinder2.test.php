<?php namespace ProcessWire;

/**
 * Tests for PageFinder2, the opt-in PageFinder ($config->PageFinder = ['version' => 2]), and its optimizations
 *
 */
class WireTest_PageFinder2 extends WireTest {

	protected $childTemplateName = 'wire-test-pagefinder2';
	protected $createdTemplate = false;
	protected $addedTitleField = false;
	protected $numFieldName = 'wire_test_pagefinder2_num';
	protected $createdNumField = false;

	public function init() {
		$this->createdTemplate = false;
		$this->addedTitleField = false;
		$this->createdNumField = false;
		$this->ensureChildTemplate();
		$this->cleanupPages();
		$this->createChildPages();
	}

	public function execute() {
		$this->testRangeOnSingleValueField();
	}

	public function finish() {
		$this->cleanupPages();
		$this->cleanupNumField();
		$this->cleanupTemplate();
	}

	/**
	 * @return PageFinder2
	 *
	 */
	protected function finder() {
		return PageFinder2::getInstance($this, true);
	}

	/**
	 * Find page IDs with PageFinder2
	 *
	 * @param string $selector
	 * @param array $options
	 * @return array
	 *
	 */
	protected function findIDs($selector, array $options = array()) {
		return $this->finder()->findIDs(new Selectors($selector), $options);
	}

	/**
	 * Conditions on one single-value field share its join, so that a range uses the field’s index
	 *
	 * A “<” (or “<=”) condition also matches pages without a value, which counted as 0, so it is checked in a
	 * LEFT JOIN that allows for no row. When another condition on the same field needs a row anyway, that is
	 * not needed, and both conditions go in one join: “num>=a, num<b” is then one index range rather than a
	 * scan of every page.
	 *
	 */
	protected function testRangeOnSingleValueField() {
		$pages = $this->wire()->pages;
		$name = $this->numFieldName;
		$field = $this->ensureNumField();
		$parent = $this->getTestPage();
		$ids = array();
		foreach(array('a' => 10, 'b' => 20, 'c' => '') as $n => $value) {
			$page = $pages->get("parent=$parent, name=pagefinder2-test-$n, include=all");
			$page->of(false);
			$page->set($name, $value);
			$page->save($name);
			$ids[$n] = $page->id;
		}
		$pages->uncacheAll();
		$sel = "parent=$parent, template=$this->childTemplateName, include=all";
		$table = $field->getTable();
		$find = function($selector) { $a = $this->findIDs("$selector, sort=id"); sort($a); return $a; };
		$sql = function($selector) { return $this->finder()->find(new Selectors($selector), array('returnQuery' => true))->getQuery(); };
		$joins = function($selector) use($sql, $table) { return preg_match_all("/JOIN $table AS {$table}[0-9]* ON/", $sql($selector)); };

		$this->check('range: pages within it', array($ids['b']), $find("$sel, $name>=15, $name<25"));
		$this->check('range, upper bound first', array($ids['b']), $find("$sel, $name<25, $name>=15"));
		$this->check('range with <= and >', array($ids['b']), $find("$sel, $name>10, $name<=20"));
		$this->check('range: one join for both bounds', 1, $joins("$sel, $name>=15, $name<25"));
		$this->check('range, upper bound first: one join for both bounds', 1, $joins("$sel, $name<25, $name>=15"));
		$this->check('range: no LEFT JOIN for a page without a value', false, strpos($sql("$sel, $name>=15, $name<25"), '__blank') !== false);
		$this->check('two lower bounds: pages above both', array($ids['b']), $find("$sel, $name>=15, $name>12"));
		$this->check('two lower bounds: one join (as for a date range)', 1, $joins("$sel, $name>=15, $name>12"));
		$this->check('< alone still matches a page without a value (as 0)', array($ids['a'], $ids['b'], $ids['c']), $find("$sel, $name<25"));
		$this->check('< alone still checks for no value', true, strpos($sql("$sel, $name<25"), '__blank') !== false);
		$this->check('< with a condition that allows no value', array($ids['a'], $ids['c']), $find("$sel, $name<15, $name!=20"));
		$this->check('OR-group conditions keep their own joins', array($ids['a'], $ids['b']), $find("$sel, ($name<15, $name!=''), ($name>15)"));
	}

	protected function ensureNumField() {
		$fields = $this->wire()->fields;
		$field = $fields->get($this->numFieldName);
		if(!$field) {
			$field = new Field();
			$field->type = $this->wire()->modules->get('FieldtypeInteger');
			$field->name = $this->numFieldName;
			$field->label = 'PageFinder2 test number';
			$field->save();
			$this->createdNumField = true;
		}
		$template = $this->wire()->templates->get($this->childTemplateName);
		if(!$template->hasField($field)) {
			$template->fieldgroup->add($field);
			$template->fieldgroup->save();
		}
		return $field;
	}

	protected function cleanupNumField() {
		if(!$this->createdNumField) return;
		$fields = $this->wire()->fields;
		$field = $fields->get($this->numFieldName);
		if(!$field) return;
		$template = $this->wire()->templates->get($this->childTemplateName);
		if($template && $template->hasField($field)) {
			$template->fieldgroup->remove($field);
			$template->fieldgroup->save();
		}
		$fields->delete($field);
	}
	protected function ensureChildTemplate() {
		$templates = $this->wire()->templates;
		$template = $templates->get($this->childTemplateName);
		if(!$template) {
			$template = $templates->new($this->childTemplateName);
			$template->save();
			$this->createdTemplate = true;
		} else if(!$template->fieldgroup) {
			$template->save();
		}
		$title = $this->wire()->fields->get('title');
		if($title && !$template->hasField($title)) {
			$template->fieldgroup->add($title);
			$template->fieldgroup->save();
			$this->addedTitleField = true;
		}
	}

	protected function createChildPages() {
		$pages = $this->wire()->pages;
		$parent = $this->getTestPage();
		$sort = 0;
		foreach(array('a' => 'PageFinder2 Test Alpha', 'b' => 'PageFinder2 Test Bravo', 'c' => 'PageFinder2 Test Charlie') as $suffix => $title) {
			$pages->new(array(
				'template' => $this->childTemplateName,
				'parent' => $parent,
				'name' => "pagefinder2-test-$suffix",
				'title' => $title,
				'sort' => $sort++,
				'status' => 1,
			));
		}
		$pages->uncacheAll();
	}

	protected function cleanupPages() {
		$pages = $this->wire()->pages;
		foreach($pages->find("template=$this->childTemplateName, name^=pagefinder2-test-, include=all") as $page) {
			$pages->delete($page, true);
		}
		$pages->uncacheAll();
	}

	protected function cleanupTemplate() {
		if(!$this->createdTemplate && !$this->addedTitleField) return;
		$templates = $this->wire()->templates;
		$template = $templates->get($this->childTemplateName);
		if(!$template) return;
		if($this->createdTemplate) {
			$templates->delete($template);
		} else {
			$title = $this->wire()->fields->get('title');
			if($title && $template->hasField($title) && !$title->hasFlag(Field::flagGlobal)) {
				$template->fieldgroup->remove($title);
				$template->fieldgroup->save();
			}
		}
	}
}
