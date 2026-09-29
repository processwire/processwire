<?php namespace ProcessWire;

/**
 * Tests for PageFinder2, the opt-in PageFinder ($config->PageFinder = ['version' => 2]), and its optimizations
 *
 */
class WireTest_PageFinder2 extends WireTest {

	protected $childTemplateName = 'wire-test-pagefinder2';
	protected $createdTemplate = false;
	protected $addedTitleField = false;

	public function init() {
		$this->createdTemplate = false;
		$this->addedTitleField = false;
		$this->ensureChildTemplate();
		$this->cleanupPages();
		$this->createChildPages();
	}

	public function execute() {
		$this->testUngroupedQueries();
	}

	public function finish() {
		$this->cleanupPages();
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
	 * Finds whose joins give at most one row per page have no GROUP BY (and so no aggregates for it)
	 *
	 * The GROUP BY pages.id only removes duplicates that a join to several rows per page makes. Without one, MySQL
	 * need not build a temporary table to group and sort, i.e. sort=title with a large start is about 3 times faster.
	 *
	 */
	protected function testUngroupedQueries() {
		$parent = $this->getTestPage();
		$sel = "parent=$parent, template=$this->childTemplateName, include=hidden";
		$sql = function($selector, array $options = array()) {
			return $this->finder()->find(new Selectors($selector), array_merge(array('returnQuery' => true), $options))->getQuery();
		};
		$ungrouped = function($selector) use($sql) {
			$s = $sql($selector);
			return stripos($s, 'GROUP BY') === false && stripos($s, 'MIN(') === false && stripos($s, 'MAX(') === false;
		};

		$this->check('no GROUP BY: a find on pages columns only', true, $ungrouped("$sel, sort=name"));
		$this->check('no GROUP BY: sort by a single-value field', true, $ungrouped("$sel, sort=title"));
		$this->check('no GROUP BY: sort by a single-value field descending', true, $ungrouped("$sel, sort=-title"));
		$this->check('no GROUP BY: a condition on a single-value field', true, $ungrouped("$sel, title%=PageFinder2"));
		$this->check('no GROUP BY: parent and template joins', true, $ungrouped("$sel, parent.name!=x, sort=parent.name"));
		$this->check('GROUP BY kept: a multi-value field condition', false, $ungrouped('template=user, roles=superuser, include=all'));
		$this->check('GROUP BY kept: sort by a multi-value field', false, $ungrouped('template=user, sort=roles, include=all'));
		$this->check('GROUP BY kept: returnParentIDs (grouped by parent)', true, stripos($sql($sel, array('returnParentIDs' => true)), 'GROUP BY pages.parent_id') !== false);
		$this->check('GROUP BY kept: the ungroup option off', false, stripos($sql("$sel, sort=title", array('ungroup' => false)), 'GROUP BY') === false);

		// same pages, in the same order, either way
		foreach(array("$sel, sort=title", "$sel, sort=-title", "$sel, sort=title, limit=2, start=1", "$sel, title%=PageFinder2, sort=-created, sort=id") as $selector) {
			$this->check("same results with and without GROUP BY: $selector", $this->findIDs($selector, array('ungroup' => false)), $this->findIDs($selector));
		}
		$finder = $this->finder();
		$finder->findIDs(new Selectors("$sel, sort=title, limit=1"), array('getTotal' => true));
		$this->check('the total of a paginated find without GROUP BY', 3, $finder->getTotal());
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
