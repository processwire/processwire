<?php namespace ProcessWire;

/**
 * Tests for ProcessWire SessionCSRF
 *
 */
class WireTest_SessionCSRF extends WireTest {

	/**
	 * @var string
	 *
	 */
	protected $originalMode = '';

	/**
	 * @var array
	 *
	 */
	protected $originalPost = array();

	/**
	 * @var bool
	 *
	 */
	protected $originalAjax = false;

	/**
	 * @var string|null
	 *
	 */
	protected $originalCookie = null;

	public function execute() {
		$config = $this->wire()->config;
		$input = $this->wire()->input;

		$this->originalMode = (string) $config->csrfMode;
		$this->originalPost = $input->post()->getArray();
		$this->originalAjax = (bool) $config->ajax;
		$cookieName = session_name() . SessionCSRF::bindingCookieSuffix;
		$this->originalCookie = isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : null;

		try {
			$config->csrfMode = 'session';
			$this->testSessionMode();
			$config->csrfMode = 'signed';
			$this->testSignedMode();
			$this->testSignedModeRotation();
			$this->testSingleUseInSignedMode();
		} finally {
			// restore state
			$config->csrfMode = $this->originalMode;
			$config->ajax = $this->originalAjax;
			$input->post()->removeAll()->setArray($this->originalPost);
			if($this->originalCookie === null) {
				unset($_COOKIE[$cookieName]);
			} else {
				$_COOKIE[$cookieName] = $this->originalCookie;
			}
			$this->wipeTokens();
		}
	}

	/**
	 * Remove all CSRF token state from the session (simulates a re-minted/expired session)
	 *
	 */
	protected function wipeTokens() {
		$csrf = $this->wire(new SessionCSRF());
		$this->wire()->session->remove($csrf, true);
	}

	/**
	 * Simulate a form POST carrying the given token
	 *
	 * @param string $name
	 * @param string $value
	 *
	 */
	protected function simulatePost($name, $value) {
		$this->wire()->input->post()->removeAll()->setArray(array($name => $value));
	}

	/**
	 * Test session mode (the default)
	 *
	 */
	protected function testSessionMode() {
		$this->wipeTokens();
		$csrf = $this->wire(new SessionCSRF());

		$name = $csrf->getTokenName();
		$value = $csrf->getTokenValue();

		$this->check('session-mode token name has TOKEN123X456 format', 1, preg_match('/^TOKEN\d+X\d+$/', $name));
		$this->check('session-mode token name is stable across calls', $name, $csrf->getTokenName());
		$this->check('session-mode token is shared across instances', $value, $this->wire(new SessionCSRF())->getTokenValue());

		$html = $csrf->renderInput();
		$this->check('renderInput() contains token name', true, strpos($html, $name) !== false);
		$this->check('renderInput() contains token value', true, strpos($html, $value) !== false);

		$this->simulatePost('other_field', 'other_value');
		$this->check('hasValidToken() false without token in POST', false, $csrf->hasValidToken());

		$this->simulatePost($name, $value);
		$this->check('hasValidToken() true with valid token in POST', true, $csrf->hasValidToken());

		$this->simulatePost($name, 'tampered');
		$this->check('hasValidToken() false with wrong token value', false, $csrf->hasValidToken());

		// a session-mode token does not survive loss of the session
		$this->simulatePost($name, $value);
		$this->wipeTokens();
		$csrf = $this->wire(new SessionCSRF());
		$this->check('session-mode token does NOT survive session loss', false, $csrf->hasValidToken());

		// single-use tokens
		$token = $csrf->getSingleUseToken();
		$this->simulatePost($token['name'], $token['value']);
		$this->check('session-mode single-use token valid on first check', true, $csrf->hasValidToken($token['id']));
		$this->check('session-mode single-use token invalid on second check', false, $csrf->hasValidToken($token['id']));
	}

	/**
	 * Test signed mode basics
	 *
	 */
	protected function testSignedMode() {
		$config = $this->wire()->config;
		$cookieName = session_name() . SessionCSRF::bindingCookieSuffix;

		$this->wipeTokens();
		unset($_COOKIE[$cookieName]);
		$csrf = $this->wire(new SessionCSRF());

		$name = $csrf->getTokenName();
		$value = $csrf->getTokenValue();

		$this->check('signed-mode mints a binding cookie', true, isset($_COOKIE[$cookieName]));
		$this->check('binding cookie has expected format', 1, preg_match('/^\d+x[A-Za-z0-9]{40}$/', (string) $_COOKIE[$cookieName]));
		$this->check('signed-mode token name keeps TOKEN123X456 format', 1, preg_match('/^TOKEN\d+X\d+$/', $name));
		$this->check('signed-mode token name is stable across instances', $name, $this->wire(new SessionCSRF())->getTokenName());
		$this->check('signed-mode token value is stable across instances', $value, $this->wire(new SessionCSRF())->getTokenValue());
		$this->check('signed-mode token stores nothing in the session', null, $this->wire()->session->get($csrf, 'name'));

		$this->simulatePost($name, $value);
		$this->check('signed-mode token valid via POST', true, $csrf->hasValidToken());

		$this->simulatePost($name, 'tampered');
		$this->check('signed-mode token invalid with wrong value', false, $csrf->hasValidToken());

		// THE point of signed mode: token survives session loss
		$this->simulatePost($name, $value);
		$this->wipeTokens();
		$csrf = $this->wire(new SessionCSRF());
		$this->check('signed-mode token SURVIVES session loss', true, $csrf->hasValidToken());

		// AJAX header validation
		$originalAjax = (bool) $config->ajax;
		$config->ajax = true;
		$this->simulatePost('other_field', 'other_value');
		$_SERVER["HTTP_X_$name"] = $value;
		$this->check('signed-mode token valid via AJAX header', true, $csrf->hasValidToken());
		unset($_SERVER["HTTP_X_$name"]);
		$config->ajax = $originalAjax;

		// named tokens differ from the default token
		$this->check('signed-mode named token differs from default', true, $csrf->getTokenValue('test') !== $value);

		// a different binding cookie produces a different token
		$oldCookie = $_COOKIE[$cookieName];
		$_COOKIE[$cookieName] = time() . 'x' . str_repeat('a', 40);
		$other = $this->wire(new SessionCSRF());
		$this->check('different binding cookie produces different token value', true, $other->getTokenValue() !== $value);
		$_COOKIE[$cookieName] = $oldCookie;
	}

	/**
	 * Test that resets rotate the binding cookie in signed mode
	 *
	 */
	protected function testSignedModeRotation() {
		$cookieName = session_name() . SessionCSRF::bindingCookieSuffix;

		$this->wipeTokens();
		$csrf = $this->wire(new SessionCSRF());
		$name = $csrf->getTokenName();
		$value = $csrf->getTokenValue();
		$cookieBefore = $_COOKIE[$cookieName];

		$csrf->resetAll();
		$this->check('resetAll() rotates the binding cookie', true, $_COOKIE[$cookieName] !== $cookieBefore);
		$this->simulatePost($name, $value);
		$this->check('token from before resetAll() no longer validates', false, $this->wire(new SessionCSRF())->hasValidToken());

		$csrf = $this->wire(new SessionCSRF());
		$cookieBefore = $_COOKIE[$cookieName];
		$csrf->resetToken();
		$this->check('resetToken() rotates the binding cookie', true, $_COOKIE[$cookieName] !== $cookieBefore);
	}

	/**
	 * Test that single-use tokens remain session-based (and single-use) in signed mode
	 *
	 */
	protected function testSingleUseInSignedMode() {
		$cookieName = session_name() . SessionCSRF::bindingCookieSuffix;

		$this->wipeTokens();
		$csrf = $this->wire(new SessionCSRF());
		$csrf->getTokenValue(); // ensure binding cookie exists
		$cookieBefore = $_COOKIE[$cookieName];

		$token = $csrf->getSingleUseToken();
		$this->check('signed-mode single-use token is session-based', $token['name'], $this->wire()->session->get($csrf, "name" . $token['id']));

		$this->simulatePost($token['name'], $token['value']);
		$this->check('signed-mode single-use token valid on first check', true, $csrf->hasValidToken($token['id']));
		$this->check('signed-mode single-use token invalid on second check', false, $csrf->hasValidToken($token['id']));
		$this->check('single-use validation does not rotate the binding cookie', $cookieBefore, $_COOKIE[$cookieName]);
	}

}
