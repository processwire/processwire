<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireNumberTools class
 *
 * Tests uniqueNumber() (default, namespaced, getLast, reset, exceptions),
 * randomInteger(), strToBytes(), bytesToStr() (boundaries, options, small mode,
 * custom labels, custom formatting, forced type), and locale().
 *
 */
class WireTest_WireNumberTools extends WireTest {

	protected $testNamespaces = array(
		'wire_test_wnt', 'wire_test_wnt2', 'wire_test_wnt3',
		'wire_test_wnt_empty', 'wire_test_wnt_reset',
	);

	public function init() {
		$database = $this->wire()->database;
		foreach($this->testNamespaces as $ns) {
			$table = 'unique_num_' . $ns;
			$database->exec("DROP TABLE IF EXISTS `$table`");
		}
	}

	public function execute() {
		$this->testUniqueNumber();
		$this->testUniqueNumberNamespaced();
		$this->testUniqueNumberGetLast();
		$this->testUniqueNumberReset();
		$this->testRandomInteger();
		$this->testStrToBytes();
		$this->testBytesToStr();
		$this->testBytesToStrSmall();
		$this->testBytesToStrType();
		$this->testBytesToStrLabels();
		$this->testBytesToStrFormatting();
		$this->testBytesToStrBoundaries();
		$this->testLocale();
	}

	public function finish() {
		$database = $this->wire()->database;
		foreach($this->testNamespaces as $ns) {
			$table = 'unique_num_' . $ns;
			$database->exec("DROP TABLE IF EXISTS `$table`");
		}
	}

	protected function testUniqueNumber() {
		$wt = $this->wireNumberTools();

		// uniqueNumber() returns incrementing integers
		$n1 = $wt->uniqueNumber('wire_test_wnt');
		$n2 = $wt->uniqueNumber('wire_test_wnt');
		$this->check('uniqueNumber() returns positive integer', true, $n1 > 0);
		$this->check('uniqueNumber() returns int', true, is_int($n1));
		$this->check('uniqueNumber() second call increments', true, $n2 > $n1);

		// uniqueNumber() with string argument is namespace
		$this->check('uniqueNumber("ns") is same as options namespace', $n2, $wt->uniqueNumber(['namespace' => 'wire_test_wnt', 'getLast' => true]));
	}

	protected function testUniqueNumberNamespaced() {
		$wt = $this->wireNumberTools();

		// Namespaced counters are independent
		$a1 = $wt->uniqueNumber('wire_test_wnt2');
		$a2 = $wt->uniqueNumber('wire_test_wnt2');
		$this->check('Namespaced uniqueNumber() increments', true, $a2 > $a1);

		// Different namespace has independent counter
		$b1 = $wt->uniqueNumber('wire_test_wnt3');
		$this->check('Different namespace is independent', true, $b1 > 0);
	}

	protected function testUniqueNumberGetLast() {
		$wt = $this->wireNumberTools();

		// getLast returns last generated number without generating new one
		$n1 = $wt->uniqueNumber('wire_test_wnt');
		$last = $wt->uniqueNumber(['namespace' => 'wire_test_wnt', 'getLast' => true]);
		$this->check('getLast returns last number', $n1, $last);

		// getLast on empty namespace returns 0
		$wt->uniqueNumber(['namespace' => 'wire_test_wnt_empty', 'reset' => true]);
		$lastEmpty = $wt->uniqueNumber(['namespace' => 'wire_test_wnt_empty', 'getLast' => true]);
		$this->check('getLast on empty namespace returns 0', 0, $lastEmpty);
	}

	protected function testUniqueNumberReset() {
		$wt = $this->wireNumberTools();

		// Reset clears the namespace counter
		$wt->uniqueNumber('wire_test_wnt_reset');
		$wt->uniqueNumber('wire_test_wnt_reset');
		$result = $wt->uniqueNumber(['namespace' => 'wire_test_wnt_reset', 'reset' => true]);
		$this->check('reset returns 0', 0, $result);

		// After reset, getLast returns 0
		$last = $wt->uniqueNumber(['namespace' => 'wire_test_wnt_reset', 'getLast' => true]);
		$this->check('After reset, getLast returns 0', 0, $last);

		// Reset without namespace throws WireException
		$threw = false;
		try {
			$wt->uniqueNumber(['reset' => true]);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('reset without namespace throws WireException', true, $threw);
	}

	protected function testRandomInteger() {
		$wt = $this->wireNumberTools();

		// randomInteger() returns int within range
		$n = $wt->randomInteger(1, 100);
		$this->check('randomInteger() returns int', true, is_int($n));
		$this->check('randomInteger() within min', true, $n >= 1);
		$this->check('randomInteger() within max', true, $n <= 100);

		// randomInteger() with same min and max returns that value
		$n = $wt->randomInteger(42, 42);
		$this->check('randomInteger(min=max) returns value', 42, $n);

		// randomInteger() with full range
		$n = $wt->randomInteger(0, PHP_INT_MAX);
		$this->check('randomInteger(0, PHP_INT_MAX) returns non-negative', true, $n >= 0);

		// randomInteger() produces different values (probabilistic)
		$n1 = $wt->randomInteger(1, 1000000);
		$n2 = $wt->randomInteger(1, 1000000);
		$this->check('randomInteger() produces different values', true, $n1 !== $n2);
	}

	protected function testStrToBytes() {
		$wt = $this->wireNumberTools();

		// strToBytes() with unit suffixes
		$this->check('strToBytes("10M")', 10485760, $wt->strToBytes('10M'));
		$this->check('strToBytes("2 GB")', 2147483648, $wt->strToBytes('2 GB'));
		$this->check('strToBytes("512kb")', 524288, $wt->strToBytes('512kb'));
		$this->check('strToBytes("1.5 GB")', 1610612736, $wt->strToBytes('1.5 GB'));

		// strToBytes() without unit returns bytes
		$this->check('strToBytes("1024")', 1024, $wt->strToBytes('1024'));
		$this->check('strToBytes(1024)', 1024, $wt->strToBytes(1024));

		// strToBytes() with explicit unit
		$this->check('strToBytes(512, "MB")', 536870912, $wt->strToBytes(512, 'MB'));
		$this->check('strToBytes("512", "k")', 524288, $wt->strToBytes('512', 'k'));

		// strToBytes() case insensitive
		$this->check('strToBytes("10m") case insensitive', 10485760, $wt->strToBytes('10m'));
		$this->check('strToBytes("10Mb") case insensitive', 10485760, $wt->strToBytes('10Mb'));

		// strToBytes() with commas
		$this->check('strToBytes("1,024") with comma', 1024, $wt->strToBytes('1,024'));

		// strToBytes() terabytes
		$this->check('strToBytes("2T")', 2199023255552, $wt->strToBytes('2T'));

		// strToBytes() negative
		$this->check('strToBytes("-5M")', -5242880, $wt->strToBytes('-5M'));

		// strToBytes() float value
		$this->check('strToBytes("0.5M")', 524288, $wt->strToBytes('0.5M'));

		// strToBytes() bytes unit
		$this->check('strToBytes("100b")', 100, $wt->strToBytes('100b'));
	}

	protected function testBytesToStr() {
		$wt = $this->wireNumberTools();

		// bytesToStr() basic conversions
		$this->check('bytesToStr(0)', '0 bytes', $wt->bytesToStr(0));
		$this->check('bytesToStr(1)', '1 byte', $wt->bytesToStr(1));
		$this->check('bytesToStr(512)', '512 bytes', $wt->bytesToStr(512));
		$this->check('bytesToStr(1024)', '1 kB', $wt->bytesToStr(1024));
		$this->check('bytesToStr(1536)', '1.5 kB', $wt->bytesToStr(1536));
		$this->check('bytesToStr(1048576)', '1.0 MB', $wt->bytesToStr(1048576));
		$this->check('bytesToStr(1073741824)', '1.0 GB', $wt->bytesToStr(1073741824));

		// bytesToStr() with string input (converts via strToBytes)
		$this->check('bytesToStr("1M")', '1.0 MB', $wt->bytesToStr('1M'));

		// bytesToStr() with decimals option
		$result = $wt->bytesToStr(1048576, ['decimals' => 2, 'decimal_point' => '.', 'thousands_sep' => '']);
		$this->check('bytesToStr(decimals=2)', '1.00 MB', $result);

		// bytesToStr() with int as options assumes decimals
		$result = $wt->bytesToStr(1048576, 2);
		$this->check('bytesToStr(int) treats as decimals', true, strpos($result, 'MB') !== false);
	}

	protected function testBytesToStrSmall() {
		$wt = $this->wireNumberTools();

		// small=true removes space
		$this->check('bytesToStr(small=true) removes space', '1MB', $wt->bytesToStr(1048576, ['small' => true]));

		// small=true with decimals that are non-zero
		$this->check('bytesToStr(small=true) keeps non-zero decimals', '1.5kB', $wt->bytesToStr(1536, ['small' => true]));

		// small=true with decimals that are zero removes them
		$this->check('bytesToStr(small=true) removes zero decimals', '2kB', $wt->bytesToStr(2048, ['small' => true]));

		// small=1 keeps space but trims zero decimals
		$this->check('bytesToStr(small=1) keeps space', '1 MB', $wt->bytesToStr(1048576, ['small' => 1]));

		// small=1 with non-zero decimals
		$this->check('bytesToStr(small=1) keeps non-zero decimals', '1.5 kB', $wt->bytesToStr(1536, ['small' => 1]));

		// small=true for bytes
		$this->check('bytesToStr(small=true) for bytes', '512B', $wt->bytesToStr(512, ['small' => true]));
	}

	protected function testBytesToStrType() {
		$wt = $this->wireNumberTools();

		// type=k forces kilobytes
		$this->check('bytesToStr(type=k) forces kB', '1024 kB', $wt->bytesToStr(1048576, ['type' => 'k', 'thousands_sep' => '']));

		// type=b forces bytes
		$this->check('bytesToStr(type=b) forces bytes', '1048576 bytes', $wt->bytesToStr(1048576, ['type' => 'b', 'thousands_sep' => '']));

		// type=m forces megabytes (forced type uses 0 decimals by default)
		$this->check('bytesToStr(type=m) forces MB', '1 MB', $wt->bytesToStr(1048576, ['type' => 'm']));

		// type=g forces gigabytes
		$this->check('bytesToStr(type=g) forces GB', '1 GB', $wt->bytesToStr(1073741824, ['type' => 'g']));
	}

	protected function testBytesToStrLabels() {
		$wt = $this->wireNumberTools();

		// Custom labels
		$labels = ['m' => 'MB', 'k' => 'KB', 'bytes' => 'B', 'byte' => 'B', 'b' => 'B'];
		$this->check('bytesToStr(custom labels) MB', '1.0 MB', $wt->bytesToStr(1048576, ['labels' => $labels]));
		$this->check('bytesToStr(custom labels) kB', '1.5 KB', $wt->bytesToStr(1536, ['labels' => $labels]));
		$this->check('bytesToStr(custom labels) bytes', '512 B', $wt->bytesToStr(512, ['labels' => $labels]));
		$this->check('bytesToStr(custom labels) byte', '1 B', $wt->bytesToStr(1, ['labels' => $labels]));
	}

	protected function testBytesToStrFormatting() {
		$wt = $this->wireNumberTools();

		// Custom decimal point and thousands separator
		$result = $wt->bytesToStr(1048576, ['decimals' => 2, 'decimal_point' => ',', 'thousands_sep' => '.']);
		$this->check('bytesToStr(custom formatting)', '1,00 MB', $result);

		// Thousands separator for large values
		$result = $wt->bytesToStr(1500000, ['type' => 'b', 'thousands_sep' => ',']);
		$this->check('bytesToStr(thousands_sep) for large value', true, strpos($result, ',') !== false);
	}

	protected function testBytesToStrBoundaries() {
		$wt = $this->wireNumberTools();

		// Binary boundaries (1024-based)
		$this->check('1023 bytes stays as bytes', '1023 bytes', $wt->bytesToStr(1023, ['thousands_sep' => '']));
		$this->check('1024 bytes becomes 1 kB', '1 kB', $wt->bytesToStr(1024));
		$this->check('1048575 bytes stays as kB', '1024.0 kB', $wt->bytesToStr(1048575, ['thousands_sep' => '']));
		$this->check('1048576 bytes becomes 1.0 MB', '1.0 MB', $wt->bytesToStr(1048576));
		$this->check('1073741823 bytes stays as MB', '1024.0 MB', $wt->bytesToStr(1073741823, ['thousands_sep' => '']));
		$this->check('1073741824 bytes becomes 1.0 GB', '1.0 GB', $wt->bytesToStr(1073741824));
	}

	protected function testLocale() {
		$wt = $this->wireNumberTools();

		// locale() with no key returns full array
		$wt->locale('clear');
		$all = $wt->locale();
		$this->check('locale() returns array', true, is_array($all));

		// locale() with specific key returns value
		$dp = $wt->locale('decimal_point');
		$this->check('locale("decimal_point") returns string', true, is_string($dp) || $dp === '');

		// locale() with unknown key returns null
		$unknown = $wt->locale('nonexistent_key_xyz');
		$this->check('locale(unknown key) returns null', null, $unknown);

		// locale('clear') clears cache
		$wt->locale('clear');
		$this->check('locale("clear") executes without error', true, true);
	}

	protected function wireNumberTools() {
		$wt = $this->wire()->wireNumberTools;
		if(!$wt) {
			$wt = new WireNumberTools();
			$this->wire($wt);
		}
		return $wt;
	}
}
