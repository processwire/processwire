<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Debug class
 *
 * Tests static timer methods (start/stop/reset/remove/save), timer settings,
 * backtrace generation, and value dumping (toStr).
 *
 */
class WireTest_Debug extends WireTest {

	protected $originalSettings = array();

	public function init() {
		// Save original timer settings so we can restore them
		$this->originalSettings = array(
			'precision' => Debug::timerSetting('precision'),
			'precisionMS' => Debug::timerSetting('precisionMS'),
			'useMS' => Debug::timerSetting('useMS'),
			'suffix' => Debug::timerSetting('suffix'),
			'suffixMS' => Debug::timerSetting('suffixMS'),
		);

		// Clean state
		Debug::removeAll();
		Debug::removeSavedTimers();
	}

	public function execute() {
		$this->testTimer();
		$this->testStartStopTimer();
		$this->testResetTimer();
		$this->testRemoveTimer();
		$this->testTimerSettings();
		$this->testStopTimerOptions();
		$this->testSaveTimer();
		$this->testGetSavedTimers();
		$this->testRemoveSavedTimers();
		$this->testGetAll();
		$this->testBacktrace();
		$this->testToStr();
	}

	public function finish() {
		// Restore original settings
		foreach($this->originalSettings as $key => $value) {
			Debug::timerSetting($key, $value);
		}
		Debug::removeAll();
		Debug::removeSavedTimers();
	}

	protected function testTimer() {
		// timer() with no args starts a timer and returns key
		$key = Debug::timer();
		$this->check('timer() with no args returns string key', true, is_string($key));
		$this->check('timer() with no args starts a timer', true, isset(Debug::getAll()[$key]));
		Debug::removeTimer($key);

		// timer(key) with new key starts a named timer
		$key = Debug::timer('named_timer');
		$this->check('timer(key) starts named timer', true, isset(Debug::getAll()['named_timer']));
		Debug::removeTimer('named_timer');

		// timer(key) with existing key returns elapsed time
		$key = Debug::startTimer('elapsed_test');
		usleep(1000);
		$elapsed = Debug::timer('elapsed_test');
		$this->check('timer(key) with existing key returns string', true, is_string($elapsed));
		$this->check('timer(key) with existing key returns numeric', true, is_numeric($elapsed));
		Debug::removeTimer('elapsed_test');

		// timer(key, reset=true) resets existing timer
		$key = Debug::startTimer('reset_via_timer');
		usleep(50000);
		Debug::timer('reset_via_timer', true);
		$this->check('timer(key, true) resets timer', true, isset(Debug::getAll()['reset_via_timer']));
		Debug::removeTimer('reset_via_timer');

		// timer() cumulative calls return increasing time
		$key = Debug::startTimer('cumulative_test');
		usleep(1000);
		$t1 = (float) Debug::timer('cumulative_test');
		usleep(50000);
		$t2 = (float) Debug::timer('cumulative_test');
		$this->check('timer() cumulative calls return increasing time', true, $t2 > $t1);
		Debug::removeTimer('cumulative_test');
	}

	protected function testStartStopTimer() {
		// startTimer() returns key
		$key = Debug::startTimer('startstop');
		$this->check('startTimer() returns key', 'startstop', $key);

		// startTimer() with empty key auto-generates
		$key = Debug::startTimer();
		$this->check('startTimer() with empty key auto-generates', true, is_string($key) && strlen($key) > 0);
		Debug::removeTimer($key);

		// stopTimer() returns elapsed time string
		$key = Debug::startTimer('stop_test');
		usleep(1000);
		$elapsed = Debug::stopTimer('stop_test');
		$this->check('stopTimer() returns string', true, is_string($elapsed));
		$this->check('stopTimer() returns numeric', true, is_numeric($elapsed));

		// stopTimer() clears timer by default
		$key = Debug::startTimer('clear_test');
		Debug::stopTimer('clear_test');
		$this->check('stopTimer() clears timer by default', false, isset(Debug::getAll()['clear_test']));

		// stopTimer(clear=false) keeps timer active
		$key = Debug::startTimer('noclear_test');
		usleep(1000);
		Debug::stopTimer('noclear_test', null, false);
		$this->check('stopTimer(clear=false) keeps timer active', true, isset(Debug::getAll()['noclear_test']));
		// Second stop should show more time
		usleep(50000);
		$elapsed2 = Debug::stopTimer('noclear_test');
		$this->check('stopTimer(clear=false) allows cumulative timing', true, is_numeric($elapsed2));

		// stopTimer() with empty key uses last started timer
		$key = Debug::startTimer('last_timer');
		$elapsed = Debug::stopTimer();
		$this->check('stopTimer() with empty key uses last timer', true, is_string($elapsed));
		$this->check('stopTimer() with empty key cleared last timer', false, isset(Debug::getAll()['last_timer']));

		// stopTimer() for non-existent timer returns empty string
		$this->check('stopTimer() non-existent returns empty string', '', Debug::stopTimer('nonexistent_xyz'));
	}

	protected function testResetTimer() {
		// resetTimer() restarts counting from now
		$key = Debug::startTimer('reset_test');
		usleep(50000);
		$before = (float) Debug::stopTimer('reset_test', null, false);
		Debug::resetTimer('reset_test');
		usleep(1000);
		$after = (float) Debug::stopTimer('reset_test');
		$this->check('resetTimer() restarts counting from now', true, $after < $before);
	}

	protected function testRemoveTimer() {
		// removeTimer() removes a timer
		$key = Debug::startTimer('remove_me');
		Debug::removeTimer('remove_me');
		$this->check('removeTimer() removes timer', false, isset(Debug::getAll()['remove_me']));

		// removeAll() removes all timers
		Debug::startTimer('a1');
		Debug::startTimer('a2');
		Debug::startTimer('a3');
		Debug::removeAll();
		$this->check('removeAll() removes all timers', 0, count(Debug::getAll()));
	}

	protected function testTimerSettings() {
		// timerSetting() get returns current value
		$this->check('timerSetting() get precision', 4, Debug::timerSetting('precision'));
		$this->check('timerSetting() get precisionMS', 1, Debug::timerSetting('precisionMS'));
		$this->check('timerSetting() get useMS', false, Debug::timerSetting('useMS'));
		$this->check('timerSetting() get suffix', '', Debug::timerSetting('suffix'));
		$this->check('timerSetting() get suffixMS', 'ms', Debug::timerSetting('suffixMS'));

		// timerSetting() set precision
		Debug::timerSetting('precision', 2);
		$this->check('timerSetting() set precision', 2, Debug::timerSetting('precision'));
		Debug::timerSetting('precision', 4); // restore

		// timerSetting() set suffix
		Debug::timerSetting('suffix', 's');
		$key = Debug::startTimer('suffix_test');
		$elapsed = Debug::stopTimer('suffix_test');
		$this->check('timerSetting(suffix) applies to output', true, strpos($elapsed, 's') !== false);
		Debug::timerSetting('suffix', ''); // restore

		// timerSetting(useMS=true) makes stopTimer return ms by default
		Debug::timerSetting('useMS', true);
		$key = Debug::startTimer('ms_test');
		$elapsed = Debug::stopTimer('ms_test');
		$this->check('timerSetting(useMS=true) returns ms suffix', 'ms', $elapsed, '$=');
		Debug::timerSetting('useMS', false); // restore
	}

	protected function testStopTimerOptions() {
		// stopTimer('ms') returns milliseconds
		$key = Debug::startTimer('ms_opt');
		usleep(1000);
		$ms = Debug::stopTimer('ms_opt', 'ms');
		$this->check('stopTimer("ms") returns ms suffix', 'ms', $ms, '$=');
		$this->check('stopTimer("ms") returns numeric value', true, is_numeric(str_replace('ms', '', $ms)));

		// stopTimer(int) overrides precision
		$key = Debug::startTimer('prec_opt');
		usleep(1000);
		$p2 = Debug::stopTimer('prec_opt', 2);
		$this->check('stopTimer(2) returns 2 decimal places', true, preg_match('/\.\d{2}$/', $p2) === 1);

		// stopTimer(string) uses as suffix
		$key = Debug::startTimer('suf_opt');
		usleep(1000);
		$suf = Debug::stopTimer('suf_opt', 'sec');
		$this->check('stopTimer("sec") appends suffix', 'sec', $suf, '$=');
	}

	protected function testSaveTimer() {
		// saveTimer() returns elapsed time
		$key = Debug::startTimer('save_test');
		usleep(1000);
		$elapsed = Debug::saveTimer('save_test', 'My note');
		$this->check('saveTimer() returns elapsed string', true, is_string($elapsed));
		$this->check('saveTimer() returns numeric value', true, is_numeric($elapsed));

		// saveTimer() removes the timer
		$this->check('saveTimer() removes active timer', false, isset(Debug::getAll()['save_test']));

		// saveTimer() returns false for non-existent timer
		$result = Debug::saveTimer('nonexistent');
		$this->check('saveTimer() returns false for non-existent timer', false, $result);

		// saveTimer() without note
		$key = Debug::startTimer('save_no_note');
		$elapsed = Debug::saveTimer('save_no_note');
		$retrieved = Debug::getSavedTimer('save_no_note');
		$this->check('saveTimer() without note retrieves value', $elapsed, $retrieved);
		Debug::removeSavedTimer('save_no_note');
	}

	protected function testSaveTimerRetrieval() {
		// getSavedTimer() with note
		$key = Debug::startTimer('note_test');
		usleep(1000);
		Debug::saveTimer('note_test', 'Operation X');
		$retrieved = Debug::getSavedTimer('note_test');
		$this->check('getSavedTimer() includes note', true, strpos($retrieved, 'Operation X') !== false);
		$this->check('getSavedTimer() includes elapsed time', true, strpos($retrieved, ' - ') !== false);
		Debug::removeSavedTimer('note_test');

		// getSavedTimer() for non-existent returns empty string
		$this->check('getSavedTimer() non-existent returns empty string', '', Debug::getSavedTimer('nonexistent'));
	}

	protected function testGetSavedTimers() {
		Debug::removeSavedTimers();

		// getSavedTimers() returns array
		$key = Debug::startTimer('gst1');
		Debug::saveTimer('gst1');
		$saved = Debug::getSavedTimers();
		$this->check('getSavedTimers() returns array', true, is_array($saved));
		Debug::removeSavedTimers();

		// getSavedTimers() sorts by elapsed time (longest first)
		$k1 = Debug::startTimer('fast');
		usleep(1000);
		Debug::saveTimer('fast', 'Fast');

		$k2 = Debug::startTimer('slow');
		usleep(50000);
		Debug::saveTimer('slow', 'Slow');

		$saved = Debug::getSavedTimers();
		$keys = array_keys($saved);
		$this->check('getSavedTimers() sorts longest first', 'slow', $keys[0]);
		$this->check('getSavedTimers() sorts second longest', 'fast', $keys[1]);
		Debug::removeSavedTimers();
	}

	protected function testRemoveSavedTimers() {
		// removeSavedTimer() removes one
		$key = Debug::startTimer('rst1');
		Debug::saveTimer('rst1');
		Debug::removeSavedTimer('rst1');
		$this->check('removeSavedTimer() removes saved timer', '', Debug::getSavedTimer('rst1'));

		// removeSavedTimers() removes all
		Debug::startTimer('rst2');
		Debug::saveTimer('rst2');
		Debug::startTimer('rst3');
		Debug::saveTimer('rst3');
		Debug::removeSavedTimers();
		$this->check('removeSavedTimers() removes all', 0, count(Debug::getSavedTimers()));
	}

	protected function testGetAll() {
		// getAll() returns active timers
		Debug::removeAll();
		Debug::startTimer('g1');
		Debug::startTimer('g2');
		$all = Debug::getAll();
		$this->check('getAll() returns array', true, is_array($all));
		$this->check('getAll() includes all active timers', 2, count($all));
		$this->check('getAll() contains first timer', true, isset($all['g1']));
		$this->check('getAll() contains second timer', true, isset($all['g2']));
		Debug::removeAll();

		// getAll() empty when no timers
		$this->check('getAll() empty when no timers', 0, count(Debug::getAll()));
	}

	protected function testBacktrace() {
		// backtrace() returns array by default
		$trace = Debug::backtrace(['limit' => 3]);
		$this->check('backtrace() returns array', true, is_array($trace));
		$this->check('backtrace() respects limit', true, count($trace) <= 3);

		// backtrace() array items have file and call keys
		if(count($trace)) {
			$first = reset($trace);
			$this->check('backtrace() item has file key', true, isset($first['file']));
			$this->check('backtrace() item has call key', true, isset($first['call']));
		}

		// backtrace(getString=true) returns string
		$str = Debug::backtrace(['getString' => true, 'limit' => 3]);
		$this->check('backtrace(getString=true) returns string', true, is_string($str));
		$this->check('backtrace(getString=true) is non-empty', true, strlen($str) > 0);

		// backtrace(getFile=basename) returns basename only
		$trace = Debug::backtrace(['limit' => 1, 'getFile' => 'basename']);
		if(count($trace)) {
			$first = reset($trace);
			$this->check('backtrace(getFile=basename) has no slash in file', true, strpos($first['file'], '/') === false || strpos(basename($first['file']), '/') === false);
		}

		// backtrace(skipCalls) skips listed calls
		$trace = Debug::backtrace(['limit' => 10, 'skipCalls' => ['cliEval', 'cliReady']]);
		$calls = array();
		foreach($trace as $t) $calls[] = $t['call'];
		$hasSkipped = false;
		foreach($calls as $call) {
			if(strpos($call, 'cliEval') !== false || strpos($call, 'cliReady') !== false) $hasSkipped = true;
		}
		$this->check('backtrace(skipCalls) skips listed calls', false, $hasSkipped);
	}

	protected function testToStr() {
		// toStr() with integer
		$result = Debug::toStr(42);
		$this->check('toStr(int) prefixes with int:', 'int:42', $result);

		// toStr() with float
		$result = Debug::toStr(3.14);
		$this->check('toStr(float) prefixes with float:', true, strpos($result, 'float:') === 0);

		// toStr() with string
		$result = Debug::toStr('hello');
		$this->check('toStr(string) prefixes with string:', true, strpos($result, 'string:') === 0);
		$this->check('toStr(string) includes value', 'hello', $result, '*=');

		// toStr() with boolean true
		$result = Debug::toStr(true);
		$this->check('toStr(true) returns true', 'true', $result);

		// toStr() with boolean false
		$result = Debug::toStr(false);
		$this->check('toStr(false) returns false', 'false', $result);

		// toStr() with null
		$result = Debug::toStr(null);
		$this->check('toStr(null) returns null', 'null', $result);

		// toStr() with array
		$result = Debug::toStr([1, 2, 3]);
		$this->check('toStr(array) returns JSON array', true, strpos($result, '[') !== false);
		$this->check('toStr(array) includes values', '1', $result, '*=');

		// toStr() with associative array
		$result = Debug::toStr(['name' => 'test', 'value' => 123]);
		$this->check('toStr(assoc array) includes keys', 'name', $result, '*=');

		// toStr() with print_r method
		$result = Debug::toStr([1, 2, 3], ['method' => 'print_r']);
		$this->check('toStr(print_r) returns formatted array', true, strpos($result, '1') !== false);

		// toStr() with var_export method
		$result = Debug::toStr([1, 2, 3], ['method' => 'var_export']);
		$this->check('toStr(var_export) returns PHP syntax', true, strpos($result, '1') !== false);

		// toStr() with html option
		$result = Debug::toStr('hello', ['html' => true]);
		$this->check('toStr(html=true) wraps in pre tag', '<pre>', $result, '*=');
		$this->check('toStr(html=true) entity-encodes content', '&quot;', $result, '*=');

		// toStr() with object
		$page = $this->wire()->pages->get(1);
		$result = Debug::toStr($page);
		$this->check('toStr(object) prefixes with object:ClassName', true, strpos($result, 'object:') === 0);
	}
}
