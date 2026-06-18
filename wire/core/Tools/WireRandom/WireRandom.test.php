<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireRandom
 *
 */
class WireTest_WireRandom extends WireTest {

	public function execute() {
		$random = $this->wire(new WireRandom());

		$this->check('WireRandom class can be constructed', true, $random instanceof WireRandom);
		$this->testAlphanumeric($random);
		$this->testStringAndInteger($random);
		$this->testArrayAndShuffle($random);
		$this->testPassword($random);
		$this->testBase64($random);
	}

	/**
	 * Test alphanumeric(), alpha() and numeric()
	 *
	 * @param WireRandom $random
	 *
	 */
	protected function testAlphanumeric(WireRandom $random) {
		$value = $random->alphanumeric(16);
		$this->check('alphanumeric() returns requested length', 16, strlen($value));
		$this->check('alphanumeric() returns only ASCII letters and digits', 1, preg_match('/^[A-Za-z0-9]+$/', $value));

		$value = $random->alphanumeric(0, array('minLength' => 5, 'maxLength' => 5));
		$this->check('alphanumeric(0) uses min/max length options', 5, strlen($value));

		$value = $random->alpha(12);
		$this->check('alpha() returns requested length', 12, strlen($value));
		$this->check('alpha() returns only ASCII letters', 1, preg_match('/^[A-Za-z]+$/', $value));

		$value = $random->numeric(10);
		$this->check('numeric() returns requested length', 10, strlen($value));
		$this->check('numeric() returns only digits', 1, preg_match('/^[0-9]+$/', $value));

		$value = $random->alphanumeric(12, array(
			'allow' => 'ABC',
			'require' => 'AB',
			'noRepeat' => true,
			'noStart' => 'A',
			'noEnd' => 'C',
		));
		$this->check('alphanumeric(allow) limits output to allowed chars', 1, preg_match('/^[ABC]+$/', $value));
		$this->check('alphanumeric(require) includes required A', true, strpos($value, 'A') !== false);
		$this->check('alphanumeric(require) includes required B', true, strpos($value, 'B') !== false);
		$this->check('alphanumeric(noRepeat) prevents adjacent repeats', 0, preg_match('/(.)\1/', $value));
		$this->check('alphanumeric(noStart) prevents disallowed start', false, $value[0] === 'A');
		$this->check('alphanumeric(noEnd) prevents disallowed end', false, substr($value, -1) === 'C');

		$value = $random->alphanumeric(20, array(
			'strict' => true,
			'upper' => true,
			'lower' => true,
			'numeric' => true,
		));
		$this->check('alphanumeric(strict) includes uppercase', 1, preg_match('/[A-Z]/', $value));
		$this->check('alphanumeric(strict) includes lowercase', 1, preg_match('/[a-z]/', $value));
		$this->check('alphanumeric(strict) includes digits', 1, preg_match('/[0-9]/', $value));

		$value = $random->alphanumeric(12, array('extras' => '-_', 'require' => '-'));
		$this->check('alphanumeric(extras) can include extra chars', true, strpos($value, '-') !== false);
		$this->check('alphanumeric(extras) limits output to allowed chars plus extras', 1, preg_match('/^[A-Za-z0-9_-]+$/', $value));

		try {
			$random->alphanumeric(5, array('alpha' => false, 'numeric' => false));
			$this->fail('alphanumeric() should throw when options allow no characters');
		} catch(WireException $e) {
			$this->ok('alphanumeric() throws when options allow no characters');
		}
	}

	/**
	 * Test string() and integer()
	 *
	 * @param WireRandom $random
	 *
	 */
	protected function testStringAndInteger(WireRandom $random) {
		$value = $random->string(18, 'ABC123');
		$this->check('string() returns requested length', 18, strlen($value));
		$this->check('string() uses requested character set', 1, preg_match('/^[ABC123]+$/', $value));

		$value = $random->string(0, 'XY', array('minLength' => 7, 'maxLength' => 7));
		$this->check('string(0) uses min/max length options', 7, strlen($value));
		$this->check('string(0) uses requested chars', 1, preg_match('/^[XY]+$/', $value));

		$value = $random->integer(5, 5);
		$this->check('integer(min=max) returns fixed value', 5, $value);

		$value = $random->integer(10, 20);
		$this->check('integer() returns value above or equal min', true, $value >= 10);
		$this->check('integer() returns value below or equal max', true, $value <= 20);

		$value = $random->integer(array('min' => 3, 'max' => 3));
		$this->check('integer(array options) accepts min/max', 3, $value);

		$info = $random->integer(1, 3, array('info' => true));
		$this->check('integer(info) returns array', true, is_array($info));
		$this->check('integer(info) returns value and source type', 2, count($info));
		$this->check('integer(info) source type is known', true, in_array($info[1], array('random_int', 'mcrypt', 'mt_rand'), true));

		try {
			$random->integer(10, 1);
			$this->fail('integer() should throw when max is less than min');
		} catch(WireException $e) {
			$this->ok('integer() throws when max is less than min');
		}

		$this->check('cryptoSecure() returns boolean', true, is_bool($random->cryptoSecure()));
	}

	/**
	 * Test array helpers and shuffle()
	 *
	 * @param WireRandom $random
	 *
	 */
	protected function testArrayAndShuffle(WireRandom $random) {
		$items = array('a' => 'red', 'b' => 'green', 'c' => 'blue');
		$value = $random->arrayValue($items);
		$this->check('arrayValue() returns one of the values', true, in_array($value, $items, true));

		$key = $random->arrayKey($items);
		$this->check('arrayKey() returns one of the keys', true, array_key_exists($key, $items));

		$values = $random->arrayValues($items, 2);
		$this->check('arrayValues(qty) returns requested count', 2, count($values));
		$this->check('arrayValues(qty) preserves original keys', true, count(array_intersect(array_keys($values), array_keys($items))) === 2);
		$this->check('arrayValues(empty) returns empty array', array(), $random->arrayValues(array()));
		$this->check('arrayValue(empty) returns null', null, $random->arrayValue(array()));

		$keys = $random->arrayKeys($items, 2);
		$this->check('arrayKeys(qty) returns requested count', 2, count($keys));
		$this->check('arrayKeys(qty) returns only source keys', 0, count(array_diff($keys, array_keys($items))));

		$string = 'abcdef';
		$shuffledString = $random->shuffle($string);
		$this->check('shuffle(string) returns string', true, is_string($shuffledString));
		$this->check('shuffle(string) preserves length', strlen($string), strlen($shuffledString));
		$this->check('shuffle(string) preserves characters', str_split($string), $this->sortedChars($shuffledString));

		$array = array('one' => 1, 'two' => 2, 'three' => 3);
		$shuffledArray = $random->shuffle($array);
		$this->check('shuffle(array) returns array', true, is_array($shuffledArray));
		$this->check('shuffle(array) preserves count', count($array), count($shuffledArray));
		$this->check('shuffle(array) preserves keys', $this->sortedValues(array_keys($array)), $this->sortedValues(array_keys($shuffledArray)));
		$this->check('shuffle(array) preserves values', array_values($array), $this->sortedValues(array_values($shuffledArray)));
		$this->check('shuffle(array) does not modify original', array('one' => 1, 'two' => 2, 'three' => 3), $array);
	}

	/**
	 * Test pass()
	 *
	 * @param WireRandom $random
	 *
	 */
	protected function testPassword(WireRandom $random) {
		$password = $random->pass(array(
			'minLength' => 12,
			'maxLength' => 12,
			'minLower' => 2,
			'minUpper' => 2,
			'maxUpper' => 4,
			'minDigits' => 2,
			'maxDigits' => 4,
			'minSymbols' => 2,
			'maxSymbols' => 2,
			'useSymbols' => array('!', '?'),
			'disallow' => array('O', '0', 'I', '1', 'l'),
		));

		$this->check('pass() respects requested max length', 12, strlen($password));
		$this->check('pass() includes minimum lowercase chars', true, preg_match_all('/[a-z]/', $password) >= 2);
		$this->check('pass() includes minimum uppercase chars', true, preg_match_all('/[A-Z]/', $password) >= 2);
		$this->check('pass() includes minimum digits', true, preg_match_all('/[0-9]/', $password) >= 2);
		$this->check('pass() includes requested symbols', 2, preg_match_all('/[!?]/', $password));
		$this->check('pass() omits disallowed confusing chars', 0, preg_match('/[O0I1l]/', $password));

		$password = $random->pass(array(
			'minLength' => 3,
			'maxLength' => 3,
			'minLower' => 2,
			'minUpper' => 2,
			'minDigits' => 2,
			'minSymbols' => 0,
		));
		$this->check('pass() increases impossible maxLength to fit minimums', true, strlen($password) >= 6);
	}

	/**
	 * Test base64()
	 *
	 * @param WireRandom $random
	 *
	 */
	protected function testBase64(WireRandom $random) {
		$value = $random->base64(22);
		$this->check('base64() returns requested length', 22, strlen($value));
		$this->check('base64() uses bcrypt64 alphabet', 1, preg_match('!^[./A-Za-z0-9]+$!', $value));

		$value = $random->base64(16, true);
		$this->check('base64(true) accepts fast boolean option', 16, strlen($value));
		$this->check('base64(true) uses bcrypt64 alphabet', 1, preg_match('!^[./A-Za-z0-9]+$!', $value));

		$test = $random->base64(12, array('test' => true));
		$this->check('base64(test=true) returns diagnostic string', true, is_string($test));
		$this->check('base64(test=true) includes randomInteger diagnostic', 'randomInteger', $test, '*=');

		$test = $random->base64(12, array('test' => array(true)));
		$this->check('base64(test=array) returns diagnostics array', true, is_array($test));
		$this->check('base64(test=array) includes randomInteger key', true, array_key_exists('randomInteger', $test));
	}

	/**
	 * Return sorted characters in a string
	 *
	 * @param string $value
	 * @return array
	 *
	 */
	protected function sortedChars($value) {
		return $this->sortedValues(str_split($value));
	}

	/**
	 * Return sorted values
	 *
	 * @param array $values
	 * @return array
	 *
	 */
	protected function sortedValues(array $values) {
		sort($values);
		return $values;
	}
}
