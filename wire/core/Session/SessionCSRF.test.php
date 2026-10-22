<?php namespace ProcessWire;

/**
 * Tests for ProcessWire SessionCSRF
 *
 */
class WireTest_SessionCSRF extends WireTest {

	/**
	 * @var mixed
	 *
	 */
	protected $originalSettings = null;

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

		$this->originalSettings = $config->SessionCSRF;
		$this->originalPost = $input->post()->getArray();
		$this->originalAjax = (bool) $config->ajax;
		$cookieName = $this->wire(new SessionCSRF())->bindingCookieName();
		$this->originalCookie = isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : null;

		try {
			$this->testSettings();
			$this->setSettings(array('mode' => 'session'));
			$this->testSessionMode();
			$this->testOriginFallback();
			$this->setSettings(array('mode' => 'signed'));
			$this->testSignedMode();
			$this->testSignedModeRotation();
			$this->testSingleUseInSignedMode();
			$this->testHostPrefixedCookie();
		} finally {
			// restore state
			$config->SessionCSRF = $this->originalSettings;
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
	 * Set $config->SessionCSRF to the given settings
	 *
	 * @param array $settings
	 *
	 */
	protected function setSettings(array $settings) {
		$this->wire()->config->SessionCSRF = $settings;
	}

	/**
	 * Test that $config->SessionCSRF is merged with the defaults
	 *
	 */
	protected function testSettings() {
		$csrf = $this->wire(new SessionCSRF());
		$this->setSettings(array());
		$this->check('mode defaults to session', 'session', $csrf->getSetting('mode'));
		$this->check('originFallback defaults to false', false, $csrf->getSetting('originFallback'));
		$this->setSettings(array('originFallback' => true));
		$this->check('overriding one key keeps the other default', 'session', $csrf->getSetting('mode'));
		$this->check('overridden key is used', true, $csrf->getSetting('originFallback'));
		$this->wire()->config->SessionCSRF = null;
		$this->check('missing $config->SessionCSRF uses defaults', 'session', $csrf->getSetting('mode'));
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
	 * Test the Origin fallback ($config->SessionCSRF['originFallback'])
	 *
	 */
	protected function testOriginFallback() {
		$config = $this->wire()->config;

		$originalServer = $_SERVER;
		$originalPost = $_POST;
		$originalHosts = $config->httpHosts;

		try {
			$this->wipeTokens();
			$csrf = $this->wire(new SessionCSRF());
			$csrf->getTokenValue(); // establish the current token

			// simulate a stale token: PW-shaped name from a session that no longer exists
			$staleName = 'TOKEN123456X123456';
			$_POST = array($staleName => 'stale-value-from-dead-session');
			$this->simulatePost($staleName, 'stale-value-from-dead-session');
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$config->httpHosts = array('example.com');
			$scheme = $config->https ? 'https' : 'http';

			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com";
			$this->setSettings(array('mode' => 'session', 'originFallback' => false));
			$this->check('fallback disabled: stale token rejected despite same-origin', false, $csrf->hasValidToken());

			$this->setSettings(array('mode' => 'session', 'originFallback' => true));
			$this->check('fallback enabled: stale token accepted with matching Origin', true, $csrf->hasValidToken());

			// ports: browsers treat a different port as a different origin
			$defaultPort = $scheme === 'https' ? 443 : 80;
			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com:$defaultPort";
			$this->check('fallback accepts explicit default port', true, $csrf->hasValidToken());

			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com:8080";
			$this->check('fallback rejects Origin on a port not in httpHosts', false, $csrf->hasValidToken());

			$config->httpHosts = array('localhost:8888');
			$_SERVER['HTTP_ORIGIN'] = "$scheme://localhost:8888";
			$this->check('fallback accepts Origin matching httpHosts host:port', true, $csrf->hasValidToken());

			$_SERVER['HTTP_ORIGIN'] = "$scheme://localhost";
			$this->check('fallback rejects default port when httpHosts names another', false, $csrf->hasValidToken());

			$_SERVER['HTTP_ORIGIN'] = "$scheme://localhost:9999";
			$this->check('fallback rejects Origin on a different port than httpHosts', false, $csrf->hasValidToken());
			$config->httpHosts = array('example.com');
			$_SERVER['HTTP_ORIGIN'] = "$scheme://example.com";

			$_SERVER['HTTP_ORIGIN'] = "$scheme://evil.example.net";
			$this->check('fallback rejects mismatched Origin host', false, $csrf->hasValidToken());

			$_SERVER['HTTP_ORIGIN'] = ($scheme === 'https' ? 'http' : 'https') . '://example.com';
			$this->check('fallback rejects mismatched Origin scheme', false, $csrf->hasValidToken());

			unset($_SERVER['HTTP_ORIGIN']);
			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
			$this->check('fallback accepts Sec-Fetch-Site same-origin when no Origin', true, $csrf->hasValidToken());

			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
			$this->check('fallback rejects Sec-Fetch-Site cross-site', false, $csrf->hasValidToken());

			unset($_SERVER['HTTP_SEC_FETCH_SITE']);
			$this->check('fallback rejects request with neither header', false, $csrf->hasValidToken());

			// tokenless POSTs are never rescued
			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
			$_POST = array('some_field' => 'some_value');
			$this->simulatePost('some_field', 'some_value');
			$this->check('fallback never rescues a tokenless POST', false, $csrf->hasValidToken());

			// single-use tokens are never rescued (would permit replay)
			$token = $csrf->getSingleUseToken();
			$_POST = array($token['name'] => $token['value']);
			$this->simulatePost($token['name'], $token['value']);
			$this->check('single-use token valid on first check (fallback on)', true, $csrf->hasValidToken($token['id']));
			$this->check('fallback does NOT rescue a replayed single-use token', false, $csrf->hasValidToken($token['id']));
		} finally {
			$_SERVER = $originalServer;
			$_POST = $originalPost;
			$config->httpHosts = $originalHosts;
			$this->setSettings(array('mode' => 'session'));
		}
	}

	/**
	 * Test signed mode basics
	 *
	 */
	protected function testSignedMode() {
		$config = $this->wire()->config;
		$cookieName = $this->wire(new SessionCSRF())->bindingCookieName();

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
		$cookieName = $this->wire(new SessionCSRF())->bindingCookieName();

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
		$cookieName = $this->wire(new SessionCSRF())->bindingCookieName();

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

	/**
	 * Test the __Host- binding cookie name prefix
	 *
	 */
	protected function testHostPrefixedCookie() {
		$config = $this->wire()->config;
		$originalHttps = $config->https;
		$originalDomain = $config->sessionCookieDomain;
		$plainName = session_name() . SessionCSRF::bindingCookieSuffix;
		$csrf = $this->wire(new SessionCSRF());

		try {
			$config->https = false;
			$config->sessionCookieDomain = null;
			$this->check('no __Host- prefix over http', $plainName, $csrf->bindingCookieName());

			$config->https = true;
			$this->check('__Host- prefix over https without cookie domain', "__Host-$plainName", $csrf->bindingCookieName());

			$config->sessionCookieDomain = '.example.com';
			$this->check('no __Host- prefix when sessionCookieDomain is set', $plainName, $csrf->bindingCookieName());

			// tokens are bound to the prefixed cookie
			$config->sessionCookieDomain = null;
			unset($_COOKIE["__Host-$plainName"]);
			$csrf = $this->wire(new SessionCSRF());
			$name = $csrf->getTokenName();
			$value = $csrf->getTokenValue();
			$this->check('signed mode mints the __Host- cookie over https', true, isset($_COOKIE["__Host-$plainName"]));
			$this->simulatePost($name, $value);
			$this->check('token bound to __Host- cookie validates', true, $this->wire(new SessionCSRF())->hasValidToken());
		} finally {
			unset($_COOKIE["__Host-$plainName"]);
			$config->https = $originalHttps;
			$config->sessionCookieDomain = $originalDomain;
		}
	}

}
