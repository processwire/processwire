<?php namespace ProcessWire;

/**
 * Tests for PagePathHistory
 *
 */
class WireTest_PagePathHistory extends WireTest {

	protected $parentName = 'wire-test-pagepathhistory';

	public function init() {
		// nothing to set up
	}

	public function execute() {
		if(!$this->wire()->modules->isInstalled('PagePathHistory')) {
			$this->li('Skipped: PagePathHistory is not installed');
			return;
		}
		$this->testNeverPublicPaths();
	}

	/**
	 * Paths that were publicly visible are recorded when a page is renamed or moved, while paths
	 * that never were are not, such as a page copy's temporary name (processwire/processwire-issues#1024)
	 *
	 */
	protected function testNeverPublicPaths() {
		$pages = $this->wire()->pages;
		/** @var PagePathHistory $pph */
		$pph = $this->wire()->modules->get('PagePathHistory');
		$minimumAge = $pph->minimumAge;
		$pph->minimumAge = 0; // record changes to pages that were just created
		$template = $this->getTestTemplate();
		$parents = array();

		$newPage = function($parent, $name, $unpublished) use($pages, $template) {
			$p = $pages->newPage(array('template' => $template, 'parent' => $parent, 'name' => $name, 'title' => $name));
			if($unpublished) $p->addStatus(Page::statusUnpublished);
			$p->save();
			return $pages->getFresh($p->id);
		};
		$history = function(Page $p) use($pph) {
			return implode(',', array_map('basename', $pph->getPathHistory($p)));
		};

		try {
			foreach(array('', '-b') as $suffix) {
				$p = $pages->get("parent=1, name=$this->parentName$suffix, include=all");
				if($p->id) $pages->delete($p, true); // left by an interrupted run
				$parents[] = $newPage(1, "$this->parentName$suffix", false);
			}
			$parent = $parents[0];

			$p = $newPage($parent, 'published', false);
			$p->name = 'published-renamed';
			$p->save();
			$this->check('published page renamed: previous path recorded', 'published', $history($p));

			$source = $newPage($parent, 'source', false);
			$copy = $pages->clone($source, null, true, array('set' => array('status' => Page::statusUnpublished)));
			$copy = $pages->getFresh($copy->id);
			$copyName = $copy->name;
			$copy->name = 'copy-renamed';
			$copy->save();
			$this->check("unpublished copy renamed: temporary path ($copyName) not recorded", '', $history($copy));

			$copy = $pages->clone($source, null, true, array('set' => array('status' => Page::statusUnpublished)));
			$copy->name = 'copy-renamed-2'; // same object returned by clone, not reloaded
			$copy->save();
			$this->check('unpublished copy renamed without reloading: no path recorded', '', $history($copy));

			$p = $newPage($parent, 'draft', true);
			$p->name = 'draft-renamed';
			$p->removeStatus(Page::statusUnpublished);
			$p->save();
			$this->check('never-published page renamed and published in one save: not recorded', '', $history($p));

			$p = $newPage($parent, 'was-published', false);
			$p->addStatus(Page::statusUnpublished);
			$p->save();
			$p = $pages->getFresh($p->id);
			$p->name = 'was-published-renamed';
			$p->save();
			$this->check('previously published page renamed while unpublished: recorded', 'was-published', $history($p));

			$p = $newPage($parent, 'publish-then-rename', true);
			$p->removeStatus(Page::statusUnpublished);
			$p->save();
			$p->name = 'publish-then-renamed'; // separate save, same object
			$p->save();
			$this->check('published then renamed in a separate save: recorded', 'publish-then-rename', $history($p));

			$p = $newPage($parent, 'draft-moved', true);
			$p->parent = $parents[1];
			$p->save();
			$this->check('never-published page moved: not recorded', '', $history($p));

		} finally {
			$pph->minimumAge = $minimumAge;
			foreach($parents as $p) $pages->delete($pages->getFresh($p->id), true);
			$pages->uncacheAll();
		}
	}
}
