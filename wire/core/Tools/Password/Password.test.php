<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Password
 *
 */
class WireTest_Password extends WireTest {

	public function execute() {
		$password = $this->wire(new Password());

		$this->testSetAndMatches($password);
		$this->testBlowfishHelpers($password);
		$this->testRandomHelpers($password);
		$this->testStringConversion();
	}

	/**
	 * Test setting and matching passwords.
	 *
	 * @param Password $password
	 *
	 */
	protected function testSetAndMatches(Password $password) {
		$this->check('new Password does not match empty password', false, $password->matches(''));
		$this->check('new Password does not match arbitrary password', false, $password->matches('secret123'));

		$password->setTrackChanges(true);
		$password->pass = 'secret123';

		$this->check('setting pass stores salt', true, strlen($password->salt) > 0);
		$this->check('setting pass stores hash', true, strlen($password->hash) > 0);
		$this->check('setting pass tracks pass change', true, in_array('pass', $password->getChanges(), true));
		$this->check('matches() accepts correct password', true, $password->matches('secret123'));
		$this->check('matches() rejects wrong password', false, $password->matches('wrong-secret'));

		$salt = $password->salt;
		$hash = $password->hash;
		$password->resetTrackChanges();
		$password->pass = 'secret123';

		$this->check('setting same pass preserves salt', $salt, $password->salt);
		$this->check('setting same pass preserves hash', $hash, $password->hash);
		$this->check('setting same pass records no change', array(), $password->getChanges());

		$password->pass = '';
		$this->check('setting empty pass preserves hash', $hash, $password->hash);
	}

	/**
	 * Test Blowfish helpers.
	 *
	 * @param Password $password
	 *
	 */
	protected function testBlowfishHelpers(Password $password) {
		$this->check('isBlowfish() accepts $2y prefix', true, $password->isBlowfish('$2y$11$abcdefghijklmnopqrstuv$'));
		$this->check('isBlowfish() accepts $2a prefix', true, $password->isBlowfish('$2a$11$abcdefghijklmnopqrstuv$'));
		$this->check('isBlowfish() accepts $2x prefix', true, $password->isBlowfish('$2x$11$abcdefghijklmnopqrstuv$'));
		$this->check('isBlowfish() rejects non-blowfish salt', false, $password->isBlowfish('not-a-blowfish-salt'));
		$this->check('supportsBlowfish() returns boolean', true, is_bool($password->supportsBlowfish()));

		if($password->supportsBlowfish()) {
			$password->pass = 'blowfish-check';
			$this->check('generated salt uses Blowfish when supported', true, $password->isBlowfish());
			$this->check('generated Blowfish salt length', 29, strlen($password->salt));
		}
	}

	/**
	 * Test random helper methods.
	 *
	 * @param Password $password
	 *
	 */
	protected function testRandomHelpers(Password $password) {
		$value = $password->randomBase64String(22);
		$this->check('randomBase64String() returns requested length', 22, strlen($value));
		$this->check('randomBase64String() returns base64-ish characters', 1, preg_match('/^[A-Za-z0-9\.\/]+$/', $value));

		$value = $password->randomAlpha(8);
		$this->check('randomAlpha() returns requested length', 8, strlen($value));
		$this->check('randomAlpha() returns only letters', 1, preg_match('/^[A-Za-z]+$/', $value));

		$value = $password->randomAlpha(8, true, array('A', 'B'));
		$this->check('randomAlpha(alphanumeric) returns letters/digits', 1, preg_match('/^[A-Za-z0-9]+$/', $value));
		$this->check('randomAlpha(disallow) omits disallowed A/B', 0, preg_match('/[AB]/', $value));

		$value = $password->randomAlnum(10);
		$this->check('randomAlnum() returns requested length', 10, strlen($value));
		$this->check('randomAlnum() returns only letters/digits', 1, preg_match('/^[A-Za-z0-9]+$/', $value));

		$value = $password->randomLetters(9);
		$this->check('randomLetters() returns requested length', 9, strlen($value));
		$this->check('randomLetters() returns only letters', 1, preg_match('/^[A-Za-z]+$/', $value));

		$value = $password->randomDigits(7);
		$this->check('randomDigits() returns requested length', 7, strlen($value));
		$this->check('randomDigits() returns only digits', 1, preg_match('/^[0-9]+$/', $value));

		$value = $password->randomPass(array('minLength' => 12, 'maxLength' => 12));
		$this->check('randomPass() respects requested length', 12, strlen($value));
	}

	/**
	 * Test __toString().
	 *
	 */
	protected function testStringConversion() {
		$password = $this->wire(new Password());
		$password->pass = 'string-check';
		$this->check('__toString() returns stored hash', $password->hash, (string) $password);
	}
}
