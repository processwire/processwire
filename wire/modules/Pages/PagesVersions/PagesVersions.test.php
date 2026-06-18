<?php namespace ProcessWire;

/**
 * Tests for ProcessWire PagesVersions
 *
 */
class WireTest_PagesVersions extends WireTest {

	/**
	 * @var string
	 *
	 */
	protected $pageName = 'wiretest-pagesversions';

	/**
	 * Allow test only when PagesVersions can be loaded and its tables exist
	 *
	 * @return bool
	 *
	 */
	public function allow() {
		$modules = $this->wire()->modules;
		$database = $this->wire()->database;
		$pagesVersions = $modules->get('PagesVersions');
		return $pagesVersions instanceof PagesVersions &&
			$database->tableExists(PagesVersions::versionsTable) &&
			$database->tableExists(PagesVersions::valuesTable);
	}

	/**
	 * Setup
	 *
	 */
	public function init() {
		$this->cleanup();
	}

	/**
	 * Execute test
	 *
	 */
	public function execute() {
		$pagesVersions = $this->wire()->modules->get('PagesVersions');
		$this->check('$pagesVersions is PagesVersions', true, $pagesVersions instanceof PagesVersions);
		$this->check('pagesVersions API variable is PagesVersions', true, $this->wire('pagesVersions') instanceof PagesVersions);

		$page = $this->createPage();

		$this->testCreateAndReadVersions($pagesVersions, $page);
		$this->testSaveRenameAndLoadVersions($pagesVersions, $page);
		$this->testRestoreAndDeleteVersions($pagesVersions, $page);
		$this->testDisallowedPages($pagesVersions);
	}

	/**
	 * Cleanup
	 *
	 */
	public function finish() {
		$this->cleanup();
	}

	/**
	 * Test creating and reading versions
	 *
	 * @param PagesVersions $pagesVersions
	 * @param Page $page
	 *
	 */
	protected function testCreateAndReadVersions(PagesVersions $pagesVersions, Page $page) {
		$page->of(false);
		$page->title = 'Version one title';
		$page->headline = 'Version one headline';

		$v1 = $pagesVersions->addPageVersion($page, array(
			'name' => 'first',
			'description' => 'First <b>version</b>',
		));

		$this->check('addPageVersion() returns first public version number', 2, $v1);
		$this->check('hasPageVersion(number) detects version', true, $pagesVersions->hasPageVersion($page, $v1));
		$this->check('hasPageVersion(name) detects named version', true, $pagesVersions->hasPageVersion($page, 'first'));
		$this->check('hasPageVersions() returns quantity', 1, $pagesVersions->hasPageVersions($page));

		$page->title = 'Version two title';
		$page->headline = 'Version two headline';
		$page->save();

		$v2 = $pagesVersions->addPageVersion($page, array(
			'name' => 'second',
			'description' => 'Second version',
		));

		$this->check('second addPageVersion() returns next version number', 3, $v2);
		$this->check('hasPageVersions() counts two versions', 2, $pagesVersions->hasPageVersions($page));

		$versionPage = $pagesVersions->getPageVersion($page, $v1);
		$info = $versionPage->get('_version');

		$this->check('getPageVersion() returns Page', true, $versionPage instanceof Page && $versionPage->id === $page->id);
		$this->check('getPageVersion() loads version field value', 'Version one headline', $versionPage->headline);
		$this->check('getPageVersion() sets PageVersionInfo', true, $info instanceof PageVersionInfo);
		$this->check('PageVersionInfo version matches requested version', $v1, $info->version);
		$this->check('PageVersionInfo name is sanitized name', 'first', $info->name);
		$this->check('PageVersionInfo description is plain text', 'First <b>version</b>', $info->description);
		$this->check('PageVersionInfo descriptionHtml is entity encoded', '&lt;b&gt;', $info->descriptionHtml, '*=');
		$this->check('PageVersionInfo createdUser is User', true, $info->createdUser instanceof User);
		$this->check('PageVersionInfo modifiedUser is User', true, $info->modifiedUser instanceof User);
		$this->check('PageVersionInfo page is live page', $page->id, $info->page->id);
		$this->check('PageVersionInfo fieldNames includes headline', true, in_array('headline', $info->fieldNames, true));

		$missing = $pagesVersions->getPageVersion($page, 9999);
		$this->check('getPageVersion(missing) returns NullPage', true, $missing instanceof NullPage);

		$versions = $pagesVersions->getPageVersions($page, array('sort' => 'version'));
		$this->check('getPageVersions() returns array keyed by version', array(2, 3), array_keys($versions));
		$this->check('getPageVersions() first item is Page', true, reset($versions) instanceof Page);

		$infos = $pagesVersions->getPageVersionInfos($page, array('sort' => 'version'));
		$this->check('getPageVersionInfos() returns two infos', 2, count($infos));
		$this->check('getPageVersionInfos() first item is PageVersionInfo', true, reset($infos) instanceof PageVersionInfo);
		$this->check('getPageVersionInfo() returns requested version', $v2, $pagesVersions->getPageVersionInfo($page, $v2)->version);

		$pages = $pagesVersions->getAllPagesWithVersions();
		$this->check('getAllPagesWithVersions() includes test page', true, $pages->has($page));
	}

	/**
	 * Test save, rename and load operations
	 *
	 * @param PagesVersions $pagesVersions
	 * @param Page $page
	 *
	 */
	protected function testSaveRenameAndLoadVersions(PagesVersions $pagesVersions, Page $page) {
		$versionPage = $pagesVersions->getPageVersion($page, 2);
		$versionPage->of(false);
		$versionPage->headline = 'Version one headline updated';
		$versionPage->save();

		$updated = $pagesVersions->getPageVersion($page, 2);
		$this->check('saving version page updates stored version', 'Version one headline updated', $updated->headline);
		$this->check('saving version page does not update live page', 'Version two headline', $this->wire()->pages->get($page->id)->headline);

		$this->check('renamePageVersion(number) returns true', true, $pagesVersions->renamePageVersion($page, 2, 'renamed'));
		$this->check('hasPageVersion(new name) detects renamed version', true, $pagesVersions->hasPageVersion($page, 'renamed'));
		$this->check('hasPageVersion(old name) no longer matches', false, $pagesVersions->hasPageVersion($page, 'first'));
		$this->check('renamePageVersion(name) returns true', true, $pagesVersions->renamePageVersion($page, 'renamed', 'renamed-again'));
		$this->check('pageVersionNumber(name) resolves version name', 2, $pagesVersions->pageVersionNumber($page, 'renamed-again'));
		$this->check('renamePageVersion(missing) returns false', false, $pagesVersions->renamePageVersion($page, 'missing-version', 'nope'));
		$this->check('renamePageVersion(null) clears name', true, $pagesVersions->renamePageVersion($page, 2, null));
		$this->check('hasPageVersion(cleared name) returns false', false, $pagesVersions->hasPageVersion($page, 'renamed-again'));

		$loaded = $this->wire()->pages->getFresh($page->id);
		$loaded->headline = 'Live headline before partial load';
		$loaded->title = 'Live title before partial load';
		$loaded->save();
		$this->check('loadPageVersion(partial) returns true', true, $pagesVersions->loadPageVersion($loaded, 2, array('names' => array('headline'))));
		$this->check('loadPageVersion(partial) loads requested field', 'Version one headline updated', $loaded->headline);
		$this->check('loadPageVersion(partial) leaves unrequested field alone', 'Live title before partial load', $loaded->title);

		$names = $pagesVersions->savePageVersion($loaded, 4, array(
			'names' => array('headline'),
			'returnNames' => true,
		));
		$this->check('savePageVersion(returnNames) includes requested field', true, in_array('headline', $names, true));
		$this->check('savePageVersion(explicit new version) creates version', true, $pagesVersions->hasPageVersion($page, 4));
	}

	/**
	 * Test restore and delete operations
	 *
	 * @param PagesVersions $pagesVersions
	 * @param Page $page
	 *
	 */
	protected function testRestoreAndDeleteVersions(PagesVersions $pagesVersions, Page $page) {
		$live = $this->wire()->pages->getFresh($page->id);
		$live->of(false);
		$live->title = 'Live title before restore';
		$live->headline = 'Live headline before restore';
		$live->save();

		$restored = $pagesVersions->restorePageVersion($live, 2, array('names' => array('headline')));
		$this->check('restorePageVersion(partial) returns Page', true, $restored instanceof Page);

		$fresh = $this->wire()->pages->getFresh($page->id);
		$this->check('restorePageVersion(partial) restores requested field', 'Version one headline updated', $fresh->headline);
		$this->check('restorePageVersion(partial) leaves unrequested field alone', 'Live title before restore', $fresh->title);

		$restored = $pagesVersions->restorePageVersion($fresh, 3);
		$this->check('restorePageVersion(full) returns Page', true, $restored instanceof Page);

		$fresh = $this->wire()->pages->getFresh($page->id);
		$this->check('restorePageVersion(full) restores title', 'Version two title', $fresh->title);
		$this->check('restorePageVersion(full) restores headline', 'Version two headline', $fresh->headline);

		$this->check('deletePageVersion() returns deleted row count', true, $pagesVersions->deletePageVersion($page, 4) > 0);
		$this->check('deletePageVersion() removes version', false, $pagesVersions->hasPageVersion($page, 4));
		$this->check('deleteAllPageVersions() returns quantity', 2, $pagesVersions->deleteAllPageVersions($page));
		$this->check('deleteAllPageVersions() removes all versions', 0, $pagesVersions->hasPageVersions($page));

		try {
			$pagesVersions->deleteAllVersions();
			$this->fail('deleteAllVersions() should require explicit true');
		} catch(WireException $e) {
			$this->ok('deleteAllVersions() requires explicit true');
		}
	}

	/**
	 * Test disallowed page types
	 *
	 * @param PagesVersions $pagesVersions
	 *
	 */
	protected function testDisallowedPages(PagesVersions $pagesVersions) {
		$user = $this->wire()->users->getGuestUser();
		$this->check('allowPageVersions() rejects User pages', false, $pagesVersions->allowPageVersions($user));
		$this->check('addPageVersion() returns 0 for disallowed page', 0, $pagesVersions->addPageVersion($user));
	}

	/**
	 * Create temporary test page
	 *
	 * @return Page
	 *
	 */
	protected function createPage() {
		$pages = $this->wire()->pages;
		$page = new Page();
		$page->template = 'basic-page';
		$page->parent = $pages->get('/');
		$page->name = $this->pageName;
		$page->title = 'Live title';
		$page->headline = 'Live headline';
		$page->save();
		return $page;
	}

	/**
	 * Delete temporary test page and versions
	 *
	 */
	protected function cleanup() {
		$pages = $this->wire()->pages;
		$pagesVersions = $this->wire()->modules->get('PagesVersions');
		$page = $pages->get("include=all, parent=1, name=$this->pageName");

		if($page->id) {
			if($pagesVersions instanceof PagesVersions) $pagesVersions->deleteAllPageVersions($page);
			$pages->delete($page, true);
		}
	}
}
