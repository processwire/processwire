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
