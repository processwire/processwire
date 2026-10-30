<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldDatetime
 *
 */
class WireTest_InputfieldDatetime extends WireTest {

	public function execute() {
		$this->testInputTypesAndDefaults();
		$this->testAttributeAndSettingNormalization();
		$this->testHtmlInputProcessingAndRendering();
		$this->testTextInputProcessingAndRendering();
		$this->testInvalidSubmittedValues();
		$this->testSubmittedFormats();
		$this->testLanguageInputFormats();
		$this->testDatepickerWordOverrides();
		$this->testBundledDatepickerWords();
		$this->testInputValidationBoundaries();
		$this->testValidationFeedback();
		$this->testSelectInputProcessingAndRendering();
		$this->testDatepickerOptionsAndRenderValue();
	}

	/**
	 * Test input type discovery and default fallback.
	 *
	 */
	protected function testInputTypesAndDefaults() {
		$f = $this->newInputfield('wire_test_datetime');
		$types = $f->getInputTypes();

		$this->check('getInputTypes() includes text type', true, isset($types['text']));
		$this->check('getInputTypes() includes select type', true, isset($types['select']));
		$this->check('getInputTypes() includes html type', true, isset($types['html']));
		$this->check('getInputType() defaults to text type', 'text', $f->getInputType()->getTypeName());
		$this->check('getInputType(invalid) falls back to text type', 'text', $f->getInputType('nope')->getTypeName());
		$this->check('getInputType(html) returns html type', 'html', $f->getInputType('html')->getTypeName());
		$this->check('datepickerFocus constant', 3, InputfieldDatetime::datepickerFocus);
	}

	/**
	 * Test timestamp conversion and normalized settings.
	 *
	 */
	protected function testAttributeAndSettingNormalization() {
		$f = $this->newInputfield('wire_test_datetime');
		$timestamp = strtotime('2026-07-10 14:30:00');

		$f->setAttribute('value', '2026-07-10 14:30:00');
		$this->check('setAttribute(value, ISO string) converts to timestamp', $timestamp, $f->attr('value'));

		$f->setAttribute('value', '1234567890');
		$this->check('setAttribute(value, numeric string) converts to int', 1234567890, $f->attr('value'));

		$f->setAttribute('value', '');
		$this->check('setAttribute(value, empty string) preserves empty string', '', $f->attr('value'));

		$f->set('dateMin', $timestamp);
		$f->set('dateMax', $timestamp);
		$f->set('timeMin', $timestamp);
		$f->set('timeMax', $timestamp);
		$this->check('set(dateMin, int) normalizes to date string', '2026-07-10', $f->dateMin);
		$this->check('set(dateMax, int) normalizes to date string', '2026-07-10', $f->dateMax);
		$this->check('set(timeMin, int) normalizes to time string', '14:30', $f->timeMin);
		$this->check('set(timeMax, int) normalizes to time string', '14:30', $f->timeMax);

		$f->subYear = 2026;
		$f->subMonth = 7;
		$f->subDay = 10;
		$f->subHour = 6;
		$f->subMinute = 5;
		$this->check('subDate() returns normalized substitute date', '2026-07-10', $f->subDate());
		$this->check('subTime() returns normalized substitute time', '06:05:00', $f->subTime());
	}

	/**
	 * Test HTML5 input type.
	 *
	 */
	protected function testHtmlInputProcessingAndRendering() {
		$f = $this->newInputfield('event_date');
		$f->inputType = 'html';
		$f->htmlType = 'datetime';
		$f->dateMin = '2026-01-01';
		$f->dateMax = '2026-12-31';
		$f->timeStep = 30;
		$f->timeMin = '08:00';
		$f->timeMax = '17:30';

		$data = array(
			'event_date' => '2026-07-10',
			'event_date__time' => '14:35:30',
		);
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('processInput(html datetime) combines date and time', strtotime('2026-07-10 14:35:30'), $f->val());

		$html = $f->render();
		$this->check('render(html datetime) includes date input', true, strpos($html, 'type="date"') !== false);
		$this->check('render(html datetime) includes time input', true, strpos($html, 'type="time"') !== false);
		$this->check('render(html datetime) names time companion input', true, strpos($html, 'name="event_date__time"') !== false);
		$this->check('render(html datetime) includes date min', true, strpos($html, 'min="2026-01-01"') !== false);
		$this->check('render(html datetime) includes time step', true, strpos($html, 'step="30"') !== false);

		$f = $this->newInputfield('event_time');
		$f->inputType = 'html';
		$f->htmlType = 'time';
		$f->subYear = 2026;
		$f->subMonth = 7;
		$f->subDay = 10;
		$data = array('event_time' => '09:15');
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('processInput(html time) uses substitute date', strtotime('2026-07-10 09:15:00'), $f->val());
	}

	/**
	 * Test text input type.
	 *
	 */
	protected function testTextInputProcessingAndRendering() {
		$f = $this->newInputfield('event_text');
		$f->inputType = 'text';
		$f->dateInputFormat = 'Y-m-d';
		$f->timeInputFormat = 'H:i';
		$f->datepicker = InputfieldDatetime::datepickerFocus;
		$f->datepickerOptions(array('showButtonPanel' => true, 'numberOfMonths' => 2));

		$data = array('event_text' => '2026-07-10 16:45');
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('processInput(text) parses configured date/time format', strtotime('2026-07-10 16:45:00'), $f->val());

		$html = $f->render();
		$this->check('render(text) includes formatted value', true, strpos($html, "value='2026-07-10 16:45'") !== false);
		$this->check('render(text) includes JS date format data', true, strpos($html, "data-dateformat='yy-mm-dd'") !== false);
		$this->check('render(text) includes custom datepicker option', true, strpos($html, '&quot;numberOfMonths&quot;:2') !== false);
	}

	/**
	 * Invalid submissions must report an error without replacing a valid existing value.
	 *
	 */
	protected function testInvalidSubmittedValues() {
		$previous = strtotime('2026-07-10 14:30:00');
		$cases = [];
		foreach([InputfieldDatetime::datepickerNo, InputfieldDatetime::datepickerFocus] as $picker) {
			foreach(['2015', 'October', 'Friday', 'check with Bob', '2026-02-31', '2026-02-28 trailing', '26-10-05', '999-10-05', '0', ['2026-02-28']] as $value) {
				$cases[] = [['datepicker' => $picker], ['event_invalid' => $value]];
			}
		}
		$cases[] = [['timeInputFormat' => 'H:i'], ['event_invalid' => '2026-02-28 25:00']];
		$cases[] = [['dateInputFormat' => 'j F Y'], ['event_invalid' => '31 February 2026']];
		$cases[] = [['dateInputFormat' => 'l, F j, Y'], ['event_invalid' => 'Friday, February 28, 2026']];
		foreach(['2015', '2026-02-31', '26-10-05', 'check with Bob', ['2026-02-28']] as $value) {
			$cases[] = [['inputType' => 'html', 'htmlType' => 'date'], ['event_invalid' => $value]];
		}
		foreach(['25:00', 'check with Bob', ['09:30']] as $value) {
			$cases[] = [['inputType' => 'html', 'htmlType' => 'time'], ['event_invalid' => $value]];
		}
		foreach([
			['event_invalid' => '2026-02-31', 'event_invalid__time' => '09:30'],
			['event_invalid' => '26-10-05', 'event_invalid__time' => '09:30'],
			['event_invalid' => '2026-02-28', 'event_invalid__time' => '25:00'],
			['event_invalid' => '2026-02-28', 'event_invalid__time' => ['09:30']],
		] as $data) {
			$cases[] = [['inputType' => 'html', 'htmlType' => 'datetime'], $data];
		}
		foreach($cases as $n => $case) {
			list($settings, $data) = $case;
			$f = $this->newInputfield('event_invalid');
			$f->setArray($settings);
			$f->val($previous);
			$f->resetTrackChanges();
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Invalid submission $n retains previous value", $previous, $f->val());
			$this->check("Invalid submission $n reports a field error", true, count($f->getErrors(true)) > 0);
			$this->check("Invalid submission $n does not track a value change", false, $f->isChanged('value'));
		}
		foreach(['text', 'date', 'time', 'datetime'] as $type) {
			$f = $this->newInputfield('event_invalid');
			if($type !== 'text') $f->setArray(['inputType' => 'html', 'htmlType' => $type]);
			$f->val($previous);
			$data = [];
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Omitted $type input retains previous value", $previous, $f->val());
			$this->check("Omitted $type input has no field error", [], $f->getErrors(true));
			$data = ['event_invalid' => '', 'event_invalid__time' => ''];
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Blank $type input clears previous value", '', $f->val());
			$this->check("Blank $type input has no field error", [], $f->getErrors(true));
		}
	}

	/**
	 * Validate configured formats without changing timestamp assignment through the API.
	 *
	 */
	protected function testSubmittedFormats() {
		$timestamp = strtotime('2026-07-10 14:35:30');
		$datetime = $this->wire()->datetime;
		foreach($datetime->getDateFormats() as $format) {
			$f = $this->newInputfield('event_format');
			$f->dateInputFormat = $format;
			$data = ['event_format' => date($format, $timestamp)];
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Submitted date format $format parses correctly", date('Y-m-d', $timestamp), date('Y-m-d', (int) $f->val()));
			$this->check("Submitted date format $format has no field error", [], $f->getErrors(true));
		}
		foreach($datetime->getTimeFormats() as $format) {
			if(strpos($format, '!') === 0) continue;
			$f = $this->newInputfield('event_format');
			$f->timeInputFormat = $format;
			$data = ['event_format' => date("Y-m-d $format", $timestamp)];
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Submitted time format $format parses correctly", date("Y-m-d $format", $timestamp), date("Y-m-d $format", (int) $f->val()));
			$this->check("Submitted time format $format has no field error", [], $f->getErrors(true));
		}
		foreach([
			['Y-m-d', '', '2026-2-8', strtotime('2026-02-08')],
			['Y-m-d', 'H:i', '2026-02-28', strtotime('2026-02-28')],
			['Y-m-d', 'H:i:s', '2026-01-05 14:30', strtotime('2026-01-05 14:30:00')],
			['Y-m-d', 'g:i:s A', '2026-01-05 2:30 PM', strtotime('2026-01-05 14:30:00')],
			['', 'H:i:s', '14:30', strtotime(date('Y-m-d') . ' 14:30:00')],
			['y-m-d', '', '26-10-05', strtotime('2026-10-05')],
			['Y-m-d', '', '1000-10-05', strtotime('1000-10-05')],
			['Ymd', '', '20260228', strtotime('2026-02-28')],
			['Y', '', '2015', strtotime('2015-01-01')],
			['U', '', '2015', 2015],
			['U', '', '0', 0],
			['U', '', '-2015', -2015],
			['Y-m-d\\T', 'H:i', '2026-02-28T 09:30', strtotime('2026-02-28 09:30')],
			['', 'H:i', '09:30', strtotime(date('Y-m-d') . ' 09:30')],
			['n/j', '', '2/28', strtotime(date('Y') . '-02-28')],
			['Y-m', '', '2026-02', strtotime('2026-02-01')],
			['c', '', date('c', $timestamp), $timestamp],
			['r', '', date('r', $timestamp), $timestamp],
		] as $case) {
			list($dateFormat, $timeFormat, $value, $expected) = $case;
			$f = $this->newInputfield('event_format');
			$f->dateInputFormat = $dateFormat;
			$f->timeInputFormat = $timeFormat;
			$data = ['event_format' => $value];
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Custom format $dateFormat $timeFormat accepts $value", $expected, $f->val());
			$this->check("Custom format $dateFormat $timeFormat has no field error", [], $f->getErrors(true));
		}
		$f = $this->newInputfield('event_format');
		foreach([2015, '2015', -2015, 0] as $value) {
			$f->val($value);
			$this->check("API timestamp assignment still accepts $value", (int) $value, $f->val());
		}
	}

	/**
	 * Use each user's configured language format, not the default-language format.
	 *
	 */
	protected function testLanguageInputFormats() {
		$languages = $this->wire()->languages;
		if(!$languages) return;
		$users = $this->wire()->users;
		$originalUser = $this->wire()->user;
		try {
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$user = clone $originalUser;
				$user->language = $language;
				$users->setCurrentUser($user);
				$f = $this->newInputfield('event_language');
				$f->set('dateInputFormat' . $language->id, 'd.m.Y');
				$data = ['event_language' => '28.02.2026'];
				$f->processInput($this->wire(new WireInputData($data)));
				$previous = strtotime('2026-02-28');
				$this->check("Language $language->name accepts its configured date format", $previous, $f->val());
				$data = ['event_language' => '31.02.2026'];
				$f->processInput($this->wire(new WireInputData($data)));
				$this->check("Language $language->name retains previous date on invalid input", $previous, $f->val());
				$this->check("Language $language->name reports invalid input", true, count($f->getErrors(true)) > 0);
				$cases = [];
				if($language->name === 'de') $cases = [
					['l, d.m.Y', 'Samstag, 28.02.2026', '2026-02-28'],
					['j F Y', '28 Februar 2026', '2026-02-28'],
					['j F Y', '1 MÄRZ 2026', '2026-03-01'],
					['j M Y', '1 Mär 2026', '2026-03-01'],
					['l, j F Y', 'Freitag, 28 Februar 2026', false],
					['j F Y', '31 Februar 2026', false],
				];
				if($language->name === 'fi') $cases = [
					['l, j F Y', 'Lauantai, 28 Helmikuu 2026', '2026-02-28'],
					['D, j M Y', 'La, 28 Helmi 2026', '2026-02-28'],
					['j F Y', '31 Helmikuu 2026', false],
				];
				foreach($cases as $case) {
					list($format, $raw, $expected) = $case;
					$f->set('dateInputFormat' . $language->id, $format);
					$f->val($previous);
					$data = ['event_language' => $raw];
					$f->processInput($this->wire(new WireInputData($data)));
					$this->check("Localized input $raw", $expected === false ? $previous : strtotime($expected), $f->val());
					$this->check("Localized input $raw reports errors only when invalid", $expected === false, count($f->getErrors(true)) > 0);
				}
			}
		} finally {
			$users->setCurrentUser($originalUser);
		}
	}

	/**
	 * Normalize only named date components, including custom calendar labels.
	 *
	 */
	protected function testDatepickerWordOverrides() {
		$f = $this->newInputfield('event_words');
		$months = [];
		$days = [];
		for($n = 1; $n <= 12; $n++) $months[] = 'Month' . $n;
		for($n = 0; $n < 7; $n++) $days[] = 'Day' . $n;
		$f->datepickerOptions(['monthNames' => $months, 'dayNames' => $days]);
		$f->dateInputFormat = 'l, j F Y \\M\\o\\n\\t\\h2';
		$data = ['event_words' => 'Day6, 28 Month2 2026 Month2'];
		$f->processInput($this->wire(new WireInputData($data)));
		$this->check('Custom calendar words parse without replacing matching literal text', strtotime('2026-02-28'), $f->val());
		$this->check('Custom calendar words have no field error', [], $f->getErrors(true));
		$data = ['event_words' => 'Day5, 28 Month2 2026 Month2'];
		$f->processInput($this->wire(new WireInputData($data)));
		$this->check('Custom calendar words do not hide weekday conflicts', true, count($f->getErrors(true)) > 0);
	}

	/**
	 * Check the shipped static language files, including punctuation and non-Latin labels.
	 *
	 */
	protected function testBundledDatepickerWords() {
		$f = $this->newInputfield('event_locale');
		$type = $f->getInputType('text');
		$method = new \ReflectionMethod($type, 'getDatepickerLabels');
		$method->setAccessible(true);
		$files = glob($this->wire()->config->paths->wire . 'modules/Jquery/JqueryUI/i18n/jquery.ui.datepicker-*.js');
		$this->check('Bundled calendar language files are available', true, count($files) > 0);
		foreach($files as $file) {
			$labels = $method->invoke($type, $file);
			$name = basename($file, '.js');
			foreach(['monthNames' => 12, 'monthNamesShort' => 12, 'dayNames' => 7, 'dayNamesShort' => 7] as $key => $count) {
				$this->check("$name provides $key labels", $count, count($labels[$key] ?? []));
			}
			foreach([
				['l, j F Y', $labels['dayNames'][5] . ', 10 ' . $labels['monthNames'][6] . ' 2026'],
				['D, j M Y', $labels['dayNamesShort'][5] . ', 10 ' . $labels['monthNamesShort'][6] . ' 2026'],
			] as $case) {
				$f = $this->newInputfield('event_locale');
				$f->dateInputFormat = $case[0];
				$f->datepickerOptions($labels);
				$data = ['event_locale' => $case[1]];
				$f->processInput($this->wire(new WireInputData($data)));
				$this->check("$name parses $case[0]", strtotime('2026-07-10'), $f->val());
				$this->check("$name accepts its localized date words", [], $f->getErrors(true));
			}
		}
	}

	/**
	 * Check calendar boundaries and integration with the normal form error renderer.
	 *
	 */
	protected function testInputValidationBoundaries() {
		$f = $this->newInputfield('event_boundary');
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->protectCSRF = false; // This test processes synthetic input, not an HTTP submission.
		$form->add($f);
		$previous = strtotime('2026-02-28');
		$f->val($previous);
		$form->resetTrackChanges();
		$f->resetTrackChanges();
		$data = ['event_boundary' => '2026-02-31'];
		$form->processInput($this->wire(new WireInputData($data)));
		$this->check('Invalid form submission retains previous value', $previous, $f->val());
		$this->check('Invalid form submission does not mark form value changed', false, $form->isChanged('event_boundary'));
		$html = $form->render();
		$this->check('Normal form renderer displays the validation error', true, strpos($html, 'Invalid date/time') !== false);
		$this->check('Normal form renderer identifies the rejected input', true, strpos($html, '2026-02-31') !== false);
		$this->check('Normal form renderer displays the retained date', true, strpos($html, '2026-02-28') !== false);
		$f->getErrors(true);

		foreach([
			['date', ['event_boundary' => '2024-02-29'], strtotime('2024-02-29')],
			['datetime', ['event_boundary' => '2026-02-28', 'event_boundary__time' => ''], strtotime('2026-02-28')],
			['datetime', ['event_boundary' => '', 'event_boundary__time' => '09:30'], strtotime(date('Y-m-d') . ' 09:30')],
			['time', ['event_boundary' => '09:30:45.123'], strtotime('2010-04-08 09:30:45')],
		] as $case) {
			list($type, $data, $expected) = $case;
			$f = $this->newInputfield('event_boundary');
			$f->inputType = 'html';
			$f->htmlType = $type;
			$f->processInput($this->wire(new WireInputData($data)));
			$this->check("Valid HTML $type boundary parses correctly", $expected, $f->val());
			$this->check("Valid HTML $type boundary has no field error", [], $f->getErrors(true));
		}

		$timezone = date_default_timezone_get();
		try {
			date_default_timezone_set('America/New_York');
			foreach(['text', 'html'] as $type) {
				$f = $this->newInputfield('event_boundary');
				$f->setArray(['inputType' => $type, 'htmlType' => 'datetime', 'timeInputFormat' => 'H:i']);
				$previous = strtotime('2026-03-07 14:30');
				$f->val($previous);
				$data = $type === 'text' ? ['event_boundary' => '2026-03-08 02:30'] : ['event_boundary' => '2026-03-08', 'event_boundary__time' => '02:30'];
				$f->processInput($this->wire(new WireInputData($data)));
				$this->check("Nonexistent DST time in $type input retains previous value", $previous, $f->val());
				$this->check("Nonexistent DST time in $type input reports an error", true, count($f->getErrors(true)) > 0);
			}
		} finally {
			date_default_timezone_set($timezone);
		}
	}

	/**
	 * Retain useful rejected input in errors without interpreting it as HTML or Markdown.
	 *
	 */
	protected function testValidationFeedback() {
		$f = $this->newInputfield('event_feedback');
		$f->dateInputFormat = 'j F Y';
		$data = ['event_feedback' => 'check with Bob'];
		$f->processInput($this->wire(new WireInputData($data)));
		$errors = implode(' ', $f->getErrors(true));
		$this->check('Validation error identifies the rejected text', true, strpos($errors, '"check with Bob"') !== false);
		$this->check('Validation error example uses the localized date API', true, strpos($errors, $this->wire()->datetime->date('j F Y')) !== false);
		$modes = [[true, Inputfield::textFormatBasic], [false, Inputfield::textFormatBasic]];
		if($this->wire()->modules->isInstalled('TextformatterMarkdownExtra')) $modes[] = [true, Inputfield::textFormatMarkdown];
		foreach($modes as $n => $mode) {
			$f = $this->newInputfield('event_feedback');
			$f->entityEncodeText = $mode[0];
			$f->textFormat = $mode[1];
			$form = $this->wire()->modules->get('InputfieldForm');
			$form->add($f);
			$data = ['event_feedback' => '<em>bad</em> [link](https://example.invalid/)'];
			$f->processInput($this->wire(new WireInputData($data)));
			$html = $form->render();
			$this->check("Rejected text is not HTML in error mode $n", false, strpos($html, '<em>bad</em>') !== false);
			$this->check("Rejected text is not a link in error mode $n", false, strpos($html, 'href="https://example.invalid/') !== false);
			$this->check("Rejected text is still represented in error mode $n", true, strpos($html, 'bad') !== false);
			$f->getErrors(true);
		}
	}

	/**
	 * Test select input type.
	 *
	 */
	protected function testSelectInputProcessingAndRendering() {
		$f = $this->newInputfield('event_select');
		$f->inputType = 'select';
		$f->dateSelectFormat = 'Mdy';
		$f->yearFrom = 2020;
		$f->yearTo = 2030;
		$f->yearLock = true;

		$data = array(
			'event_select__m' => 7,
			'event_select__d' => 10,
			'event_select__y' => 2026,
		);
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('processInput(select) builds timestamp from parts', strtotime('2026-07-10 00:00:00'), $f->val());

		$html = $f->render();
		$this->check('render(select) includes month select', true, strpos($html, 'name="event_select__m"') !== false);
		$this->check('render(select) includes day select', true, strpos($html, 'name="event_select__d"') !== false);
		$this->check('render(select) includes year select', true, strpos($html, 'name="event_select__y"') !== false);
		$this->check('render(select) includes hidden dependency input', true, strpos($html, 'class="InputfieldDatetimeValue"') !== false);

		$data = array(
			'event_select__m' => 7,
			'event_select__d' => 10,
			'event_select__y' => 2035,
		);
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('processInput(select yearLock) rejects out-of-range year', '', $f->val());
	}

	/**
	 * Test datepickerOptions() and renderValue().
	 *
	 */
	protected function testDatepickerOptionsAndRenderValue() {
		$f = $this->newInputfield('event_value');
		$this->check('datepickerOptions() initially empty', array(), $f->datepickerOptions());
		$f->datepickerOptions(array('changeMonth' => false));
		$f->datepickerOptions(array('showAnim' => 'none'));
		$this->check('datepickerOptions() merges custom options', array('changeMonth' => false, 'showAnim' => 'none'), $f->datepickerOptions());

		$f->inputType = 'html';
		$f->htmlType = 'datetime';
		$f->timeStep = 30;
		$f->val(strtotime('2026-07-10 14:35:30'));
		$this->check('renderValue() formats value with seconds when timeStep uses seconds', '2026-07-10 14:35:30', $f->renderValue());
	}

	/**
	 * Make configured InputfieldDatetime.
	 *
	 * @param string $name
	 * @return InputfieldDatetime
	 *
	 */
	protected function newInputfield($name) {
		$f = $this->wire(new InputfieldDatetime());
		$f->init();
		$f->attr('id+name', $name);
		$f->label = $name;
		return $f;
	}
}
