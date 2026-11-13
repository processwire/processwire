<?php namespace ProcessWire;

/**
 * Tests for the WireTests runner
 *
 */
class WireTest_WireTests extends WireTest {

	public function execute() {
		$this->testApiStateRestore();
	}

	/**
	 * Call a protected method of the WireTests runner
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 *
	 */
	protected function callRunner($method, array $args = []) {
		$call = function($method, array $args) {
			return $this->$method(...$args);
		};
		return \Closure::bind($call, $this->tests, WireTests::class)($method, $args);
	}

	/**
	 * API state changed by one test is detected and restored before the next
	 *
	 */
	protected function testApiStateRestore() {
		$wire = $this->wire();
		$pages = $wire->pages;
		$previousUser = $wire->user;
		$previousPage = $wire->page;
		$previousOutputFormatting = (bool) $pages->outputFormatting;
		$otherUserId = $previousUser->isSuperuser() ? $wire->config->guestUserPageID : $wire->config->superUserPageID;
		$otherPage = $this->getTestPage();
		$languages = $wire->languages;
		$previousLanguage = $languages ? $previousUser->language : null;
		$otherLanguage = null;
		if($languages && $previousLanguage) {
			foreach($languages as $language) {
				if($language->id !== $previousLanguage->id) $otherLanguage = $language;
			}
		}

		try {
			$state = $this->callRunner('getApiState');
			$this->check('state has no changes when nothing changed', [], $this->callRunner('getApiStateChanges', [ $state ]));

			$pages->setOutputFormatting(!$previousOutputFormatting);
			$wire->users->setCurrentUser($wire->users->get($otherUserId));
			$wire->wire('page', $otherPage);

			$changes = $this->callRunner('getApiStateChanges', [ $state ]);
			$this->check('changed output formatting is detected', true, in_array('$pages output formatting', $changes));
			$this->check('changed current user is detected', true, in_array('current user', $changes));
			$this->check('changed $page API variable is detected', $previousPage && $previousPage->id !== $otherPage->id, in_array('$page API variable', $changes));

			$this->callRunner('restoreApiState', [ $state ]);
			$this->check('output formatting is restored', $previousOutputFormatting, (bool) $pages->outputFormatting);
			$this->check('current user is restored', $previousUser->id, $wire->user->id);
			$this->check('$page API variable is restored', $previousPage, $wire->page);
			$this->check('no changes remain after restore', [], $this->callRunner('getApiStateChanges', [ $state ]));

			if($otherLanguage) {
				$wire->user->setQuietly('language', $otherLanguage);
				$this->check('changed user language is detected', [ 'user language' ], $this->callRunner('getApiStateChanges', [ $state ]));
				$this->callRunner('restoreApiState', [ $state ]);
				$this->check('user language is restored', $previousLanguage->id, $wire->user->language->id);
			}

		} finally {
			$pages->setOutputFormatting($previousOutputFormatting);
			if($wire->user->id !== $previousUser->id) $wire->users->setCurrentUser($previousUser);
			if($previousLanguage) $wire->user->setQuietly('language', $previousLanguage);
			if($previousPage) $wire->wire('page', $previousPage);
		}
	}
}
