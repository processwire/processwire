<?php namespace ProcessWire;

/** Tests the CSRF check on page list AJAX actions (unpublish, trash, and the like). */
class WireTest_ProcessPageList extends WireTest {

	public function execute() {
		$wire = $this->wire();
		$config = $wire->config;
		$originalUser = $wire->user;
		$originalSettings = $config->SessionCSRF;
		$originalHosts = $config->httpHosts;
		$originalServer = $_SERVER;
		$page = null;
		try {
			$wire->users->setCurrentUser($wire->users->get($config->superUserPageID));
			$page = $wire->pages->newPage(array(
				'template' => $wire->templates->get('basic-page') ?: $wire->pages->get(1)->template,
				'parent' => 1,
				'name' => uniqid('wiretest_pagelist_'),
			));
			$page->save();
			$csrf = $wire->session->CSRF;
			$config->httpHosts = array('example.com');
			$scheme = $config->https ? 'https' : 'http';
			$_SERVER['REQUEST_METHOD'] = 'POST';
			unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE']);

			$this->post($page, $csrf->getTokenName(), $csrf->getTokenValue());
			$this->check('current token: action runs', true, $this->unpublish($page));

			// a token from before the session's tokens were reset, as in a tab left open across a login
			$this->post($page, 'TOKEN123456X123456', 'stale-value-from-before-login');
			$config->SessionCSRF = array('originFallback' => false);
			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com";
			$this->check('stale token, fallback off: rejected', false, $this->unpublish($page));

			$config->SessionCSRF = array('originFallback' => true);
			unset($_SERVER['HTTP_ORIGIN']);
			$this->check('stale token, fallback on, no Origin: rejected', false, $this->unpublish($page));

			$_SERVER['HTTP_ORIGIN'] = "$scheme://evil.example.net";
			$this->check('stale token, fallback on, cross-site Origin: rejected', false, $this->unpublish($page));

			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com";
			$this->check('stale token, fallback on, same-site Origin: action runs', true, $this->unpublish($page));

			$this->post($page, null, null);
			$this->check('no token, fallback on, same-site Origin: rejected', false, $this->unpublish($page));
		} finally {
			$_SERVER = $originalServer;
			$config->SessionCSRF = $originalSettings;
			$config->httpHosts = $originalHosts;
			$wire->input->post()->removeAll();
			if($page && $page->id) $wire->pages->delete($page, true);
			$wire->users->setCurrentUser($originalUser);
		}
	}

	/**
	 * Set the POST data a page list action sends
	 *
	 */
	protected function post(Page $page, $tokenName, $tokenValue) {
		$post = array('id' => $page->id, 'action' => 'unpub');
		if($tokenName !== null) $post[$tokenName] = $tokenValue;
		$this->wire()->input->post()->removeAll()->setArray($post);
		$_POST = $post;
	}

	/**
	 * Publish the page, run the 'unpub' action on it, and return whether it ran
	 *
	 * @return bool True if the action unpublished the page, false if the CSRF check stopped it
	 *
	 */
	protected function unpublish(Page $page) {
		$pages = $this->wire()->pages;
		$page->of(false);
		$page->removeStatus(Page::statusUnpublished);
		$page->save();
		$module = $this->wire(new WireTestable_ProcessPageList());
		$rejected = false;
		try {
			@$module->ajaxActionForTest($page, 'unpub'); // @: it sends a JSON header after the test's output
		} catch(WireException $e) {
			if($e->getMessage() !== 'CSRF token does not match') throw $e;
			$rejected = true;
		}
		$fresh = $pages->getFresh($page->id);
		$unpublished = $fresh->hasStatus(Page::statusUnpublished);
		if($rejected === $unpublished) throw new WireException("CSRF result and page status disagree");
		return $unpublished;
	}
}

class WireTestable_ProcessPageList extends ProcessPageList {
	public function ajaxActionForTest(Page $page, $action) {
		$this->page = $page;
		return $this->ajaxAction($action);
	}
}
