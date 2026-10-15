<?php namespace ProcessWire;

/**
 * Tests for ProcessPageLister bookmark ownership and sharing.
 *
 */
class WireTest_ProcessPageLister extends WireTest {

	public function execute() {
		require_once(__DIR__ . '/ListerBookmarks.php');
		$users = $this->wire()->users;
		$owner = null;
		$reader = null;

		try {
			$owner = $users->add(uniqid('wiretest_lister_owner_'));
			$reader = $users->add(uniqid('wiretest_lister_reader_'));
			$ownerBookmarks = new ListerBookmarks($this->getTestPage(), $owner);
			$readerBookmarks = new ListerBookmarks($this->getTestPage(), $reader);
			$ownerBookmarks->saveOwnedBookmarks(array(
				'O1' => array('title' => 'Shared bookmark', 'desc' => 'Shared description', 'share' => true, 'type' => ListerBookmarks::typeOwned),
				'O2' => array('title' => 'Unshared bookmark', 'share' => false, 'type' => ListerBookmarks::typeOwned),
			));

			$sharedID = $owner->id . 'O1';
			$shared = $readerBookmarks->getBookmark($sharedID);
			$this->check('shared bookmark can still be opened by another user', true, is_array($shared));
			$this->check('shared bookmark remains viewable', true, $readerBookmarks->isBookmarkViewable($shared));
			$this->check('another user cannot edit a shared bookmark', false, $readerBookmarks->isBookmarkEditable($shared));
			$this->check('another user cannot delete a shared bookmark', false, $readerBookmarks->deleteBookmarkByID($sharedID));
			$this->check('unshared bookmark is not returned to another user', null, $readerBookmarks->getBookmark($owner->id . 'O2'));

			$own = $ownerBookmarks->getBookmark('O1');
			$this->check('owner can edit their bookmark', true, $ownerBookmarks->isBookmarkEditable($own));
			$this->check('owner can use an explicitly prefixed bookmark URL', true, $ownerBookmarks->isBookmarkEditable($ownerBookmarks->getBookmark($sharedID)));
			$this->check('non-superuser cannot edit public bookmarks', false, $readerBookmarks->isBookmarkEditable(array('type' => ListerBookmarks::typePublic)));

			$superuser = $users->get($this->wire()->config->superUserPageID);
			$adminBookmarks = new ListerBookmarks($this->getTestPage(), $superuser);
			$this->check('superuser can edit public bookmarks', true, $adminBookmarks->isBookmarkEditable(array('type' => ListerBookmarks::typePublic)));
			$this->check('shared private bookmarks are not edited through another account', false, $adminBookmarks->isBookmarkEditable($adminBookmarks->getBookmark($sharedID)));

			$stored = $ownerBookmarks->getUserSettings();
			$this->check('runtime bookmark ownership is not persisted', false, isset($stored['bookmarks']['O1']['owner']));
			$this->check('shared bookmark remains unchanged', 'Shared description', $ownerBookmarks->getBookmark('O1')['desc']);
		} finally {
			if($reader && $reader->id) $users->delete($reader);
			if($owner && $owner->id) $users->delete($owner);
		}
	}
}
