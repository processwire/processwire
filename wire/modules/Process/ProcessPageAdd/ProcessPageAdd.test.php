<?php namespace ProcessWire;

/** Tests bookmark labels produced for the Add New navigation menu. */
class WireTest_ProcessPageAdd extends WireTest {

	public function execute() {
		$wire = $this->wire();
		$module = $this->wire(new WireTestable_ProcessPageAdd());
		$userPrevious = $wire->user;
		$session = $wire->session;
		$navPrevious = $session->getFor($module, 'nav');
		$countPrevious = $session->getFor($module, 'numAddable');
		$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));

		try {
			// Isolate shortcut caching and bookmark selection; exercise the real label producer.
			$session->setFor($module, 'nav', array('url' => './', 'label' => 'Add New', 'list' => array()));
			$bookmark = $this->wire(new Page($this->getTestPage()->template));
			$bookmark->id = 123456;
			$bookmark->name = 'bookmark-fallback';
			$bookmark->of(false);
			$module->bookmark = $bookmark;
			$cases = array(
				'<strong>News</strong>' => '&lt;strong&gt;News&lt;/strong&gt;',
				'News & "Updates"' => 'News &amp; &quot;Updates&quot;',
				'News &amp; Updates' => 'News &amp; Updates',
				'' => 'bookmark-fallback',
			);
			foreach($cases as $title => $expected) {
				$bookmark->title = $title;
				$data = $module->executeNavJSON(array('getArray' => true));
				$this->check('bookmark title is encoded with generated ellipsis preserved: ' . var_export($title, true), $expected . ' &hellip;', $data['list'][0]['label']);
				$json = json_decode($module->executeNavJSON(), true);
				$this->check('JSON and array navigation labels agree', $data['list'][0]['label'], $json['list'][0]['label']);
			}
		} finally {
			$wire->users->setCurrentUser($userPrevious);
			if($navPrevious === null) $session->removeFor($module, 'nav');
				else $session->setFor($module, 'nav', $navPrevious);
			if($countPrevious === null) $session->removeFor($module, 'numAddable');
				else $session->setFor($module, 'numAddable', $countPrevious);
		}
	}
}

class WireTestable_ProcessPageAdd extends ProcessPageAdd {
	public $bookmark;

	protected function getPageBookmarks() {
		return new WireTest_ProcessPageAddBookmarks($this->bookmark);
	}
}

/** Supplies a selected bookmark without changing stored bookmark configuration. */
class WireTest_ProcessPageAddBookmarks {
	protected $bookmark;

	public function __construct(Page $bookmark) {
		$this->bookmark = $bookmark;
	}

	public function initNavJSON(array $options = array()) {
		return array('items' => array($this->bookmark));
	}
}
