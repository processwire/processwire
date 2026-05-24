<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $datetime API variable
 *
 */
class WireTest_WireDateTime extends WireTest {

	public function execute() {
		$datetime = $this->wire()->datetime;

		$this->check('$datetime is WireDateTime', true, $datetime instanceof WireDateTime);
		$this->testFormatLists();
		$this->testDateFormatting();
		$this->testStringParsing();
		$this->testRelativeTime();
		$this->testElapsedTime();
		$this->testFormatConversion();
	}

	protected function testFormatLists() {
		$datetime = $this->wire()->datetime;
		$dateFormats = $datetime->getDateFormats();
		$timeFormats = $datetime->getTimeFormats();

		$this->check('getDateFormats() returns array', true, is_array($dateFormats));
		$this->check('getDateFormats() includes ISO date format', true, in_array('Y-m-d', $dateFormats, true));
		$this->check('_getDateFormats() matches getDateFormats()', $dateFormats, WireDateTime::_getDateFormats());
		$this->check('getTimeFormats() returns array', true, is_array($timeFormats));
		$this->check('getTimeFormats() includes 24-hour time format', true, in_array('H:i', $timeFormats, true));
		$this->check('getTimeFormats() includes relative keyword', true, in_array('!relative', $timeFormats, true));
		$this->check('_getTimeFormats() matches getTimeFormats()', $timeFormats, WireDateTime::_getTimeFormats());
	}

	protected function testDateFormatting() {
		$datetime = $this->wire()->datetime;
		$ts = mktime(0, 5, 6, 4, 1, 2024);

		$this->check('date() formats PHP date format', '2024-04-01 00:05', $datetime->date('Y-m-d H:i', $ts));
		$this->check('date() formats digit-string timestamp', '2024-04-01', $datetime->date('Y-m-d', (string) $ts));
		$this->check('date() accepts strtotime-compatible timestamp string', '2024-04-08', $datetime->date('Y-m-d', '2024-04-08'));
		$this->check('date(ts) returns integer timestamp', $ts, $datetime->date('ts', $ts));
		$this->check('date(timestamp-only) uses config date format', date($this->wire()->config->dateFormat, $ts), $datetime->date($ts));
		$this->check('date(strftime format) supports ISO date', '2024-04-01', $datetime->date('%F', $ts));
		$this->check('strftime() supports leading-zero %R', '00:05', $datetime->strftime('%R', $ts));
		$this->check('strftime() supports leading-zero %T', '00:05:06', $datetime->strftime('%T', $ts));
		$this->check('strftime() supports leading-zero %X', '00:05:06', $datetime->strftime('%X', $ts));
		$this->check('formatDate() formats PHP date format', '2024-04-01', $datetime->formatDate($ts, 'Y-m-d'));
		$this->check('formatDate() formats strftime format', '2024-04-01 00:05', $datetime->formatDate($ts, '%F %R'));
		$this->check('formatDate() returns timestamp for U', $ts, $datetime->formatDate($ts, 'U'));
		$this->check('formatDate() returns blank for empty value', '', $datetime->formatDate(0, 'Y-m-d'));
		$this->check('formatDate() can embed relative placeholder', true, strpos($datetime->formatDate(time() - 3600, 'Y-m-d !relative'), 'ago') !== false);
	}

	protected function testStringParsing() {
		$datetime = $this->wire()->datetime;
		$base = mktime(12, 0, 0, 4, 1, 2024);

		$this->check('stringToTimestamp() parses known format', '2024-04-01', date('Y-m-d', $datetime->stringToTimestamp('01/04/2024', 'd/m/Y')));
		$this->check('stringToTimestamp() returns integer timestamp as-is', $base, $datetime->stringToTimestamp((string) $base, 'Y-m-d'));
		$this->check('stringToTimestamp() returns blank for empty input', '', $datetime->stringToTimestamp('', 'Y-m-d'));
		$this->check('strtotime() parses normal date string', strtotime('2024-04-01'), $datetime->strtotime('2024-04-01'));
		$this->check('strtotime() returns null for empty string', null, $datetime->strtotime(''));
		$this->check('strtotime() returns null for zero date', null, $datetime->strtotime('0000-00-00'));
		$this->check('strtotime() supports custom empty return value', 0, $datetime->strtotime('', array('emptyReturnValue' => 0)));
		$this->check('strtotime() supports baseTimestamp option', strtotime('+7 days', $base), $datetime->strtotime('+7 days', array('baseTimestamp' => $base)));
		$this->check('strtotime() supports integer baseTimestamp shortcut', strtotime('+7 days', $base), $datetime->strtotime('+7 days', $base));
		$this->check('strtotime(inputFormat) parses date-only at midnight', '2024-04-01 00:00:00', date('Y-m-d H:i:s', $datetime->strtotime('01/04/2024', array('inputFormat' => 'd/m/Y'))));
		$this->check('strtotime(outputFormat) returns formatted date', '2024-04-01', $datetime->strtotime('April 1, 2024', array('outputFormat' => 'Y-m-d')));
		$this->check('strtodate() default output format', '2024-04-01 00:00:00', $datetime->strtodate('April 1, 2024'));
		$this->check('strtodate() supports output format', 'April 1, 2024', $datetime->strtodate('04/01/2024', 'F j, Y'));
		$this->check('strtodate() supports inputFormat option', 'April 1, 2024', $datetime->strtodate('01/04/2024', 'F j, Y', array('inputFormat' => 'd/m/Y')));
		$this->check('strtodate() expands four-digit year', '2024-01-01', $datetime->strtodate('2024', 'Y-m-d'));
		$this->check('strtodate() returns blank for invalid date', '', $datetime->strtodate('not-a-date'));
		$this->check('strtodate(format=false) returns timestamp string', (string) $base, $datetime->strtodate((string) $base, false));
	}

	protected function testRelativeTime() {
		$datetime = $this->wire()->datetime;
		$past = time() - 2 * 86400;
		$future = time() + 5 * 86400;

		$this->check('relativeTimeStr() includes ago tense', 'ago', $datetime->relativeTimeStr($past), '$=');
		$this->check('relativeTimeStr() future includes from now tense', 'from now', $datetime->relativeTimeStr($future), '$=');
		$this->check('relativeTimeStr(true) future prepends in', 'in ', $datetime->relativeTimeStr($future, true), '^=');
		$this->check('relativeTimeStr(1) past prepends minus sign', '-', $datetime->relativeTimeStr($past, 1), '^=');
		$this->check('relativeTimeStr(1) abbreviates one month as mo', '+1mo', $datetime->relativeTimeStr(strtotime('+1 month'), 1));
		$this->check('relativeTimeStr(1) abbreviates multiple months as mo', '+5mo', $datetime->relativeTimeStr(strtotime('+5 months'), 1));
		$this->check('relativeTimeStr(1, no tense) omits minus sign', false, strpos($datetime->relativeTimeStr($past, 1, false), '-') === 0);
		$this->check('relativeTimeStr() supports custom substitutions', 'back', $datetime->relativeTimeStr($past, array('ago' => 'back')), '$=');
		$this->check('relativeTimeStr(empty) returns Never', 'Never', $datetime->relativeTimeStr(0));
		$this->check('date(relative-) omits tense', false, strpos($datetime->date('relative-', $past), 'ago') !== false);
		$this->check('date(r) returns compact signed value', '-', $datetime->date('r', $past), '^=');
	}

	protected function testElapsedTime() {
		$datetime = $this->wire()->datetime;
		$start = strtotime('2024-01-01 08:00:00');
		$stop = strtotime('2024-01-02 10:30:00');

		$this->check('elapsedTimeStr() returns verbose elapsed time', '1 day 2 hours 30 minutes', $datetime->elapsedTimeStr($start, $stop));
		$this->check('elapsedTimeStr(true) returns medium abbreviations', '1 day 2 hrs 30 mins', $datetime->elapsedTimeStr($start, $stop, true));
		$this->check('elapsedTimeStr(1) returns short abbreviations', '1d 2hr 30m', $datetime->elapsedTimeStr($start, $stop, 1));
		$this->check('elapsedTimeStr(0) returns digital hours over 24', '26:30:00', $datetime->elapsedTimeStr($start, $stop, 0));
		$this->check('elapsedTimeStr(exclude) omits excluded periods', '1 day 2 hrs', $datetime->elapsedTimeStr($start, $stop, true, array('exclude' => 'minutes seconds')));
		$this->check('elapsedTimeStr(include) includes only requested periods', '26 hours 30 minutes', $datetime->elapsedTimeStr($start, array('stop' => $stop, 'include' => 'hours minutes')));
		$this->check('elapsedTimeStr(delimiter) uses custom delimiter', '1 day, 2 hours, 30 minutes', $datetime->elapsedTimeStr($start, $stop, false, array('delimiter' => ', ')));
		$this->check('elapsedTimeStr(reversed) prefixes negative elapsed time', '-1 day 2 hours 30 minutes', $datetime->elapsedTimeStr($stop, $start));

		$data = $datetime->elapsedTimeStr($start, $stop, false, array('getArray' => true));
		$this->check('elapsedTimeStr(getArray) returns array', true, is_array($data));
		$this->check('elapsedTimeStr(getArray) includes days', 1, $data['days']);
		$this->check('elapsedTimeStr(getArray) includes hoursText', '2 hours', $data['hoursText']);
		$this->check('elapsedTimeStr(getArray) includes text', '1 day 2 hours 30 minutes', $data['text']);
		$this->check('elapsedTimeStr(getArray) marks positive range', false, $data['negative']);
	}

	protected function testFormatConversion() {
		$datetime = $this->wire()->datetime;

		$this->check('convertDateFormat(js) converts ISO date', 'yy-mm-dd', $datetime->convertDateFormat('Y-m-d', 'js'));
		$this->check('convertDateFormat(strftime) converts ISO date', '%Y-%m-%d', $datetime->convertDateFormat('Y-m-d', 'strftime'));
		$regex = $datetime->convertDateFormat('Y-m-d', 'regex');
		$this->check('convertDateFormat(regex) includes year capture', true, strpos($regex, '(?<Y>\\d{4})') !== false);
		$this->check('convertDateFormat(regex) validates matching date', 1, preg_match("!^$regex$!", '2024-04-01'));
		$this->check('convertDateFormat(unknown type) returns original format', 'Y-m-d', $datetime->convertDateFormat('Y-m-d', 'date'));
	}
}
