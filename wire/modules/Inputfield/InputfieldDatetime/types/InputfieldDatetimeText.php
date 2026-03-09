<?php namespace ProcessWire;

/**
 * Text date input types with optional jQuery UI datepicker
 *
 */
class InputfieldDatetimeText extends InputfieldDatetimeType {
	
	/**
	 * jQuery UI datepicker: None
	 *
	 */
	const datepickerNo = 0;

	/**
	 * jQuery UI datepicker: Click button to show
	 *
	 */
	const datepickerClick = 1;

	/**
	 * jQuery UI datepicker: Inline datepicker always visible (no timepicker support)
	 *
	 */
	const datepickerInline = 2;

	/**
	 * jQuery UI datepicker: Show when input focused (recommend option when using datepicker)
	 *
	 */
	const datepickerFocus = 3;

	/**
	 * @return array
	 * 
	 */
	public function getDefaultSettings() {
		
		$languages = $this->wire()->languages;

		$a = array(
			'datepicker' => self::datepickerNo,
			'dateInputFormat' => InputfieldDatetime::defaultDateInputFormat,
			'timeInputFormat' => '',
			'timeInputSelect' => 0,
			'yearRange' => '',
			'showAnim' => 'fade',
			'changeMonth' => true, 
			'changeYear' => true,
			'showButtonPanel' => false, 
			'numberOfMonths' => 1, 
			'showMonthAfterYear' => false,
			'showOtherMonths' => false, 
		);
		
		if($languages) {
			foreach($languages as $language) {
				/** @var Language $language */
				// account for alternate formats in other languages
				if($language->isDefault()) continue;
				$a["dateInputFormat$language"] = '';
				$a["timeInputFormat$language"] = '';
			}
		}
		
		return $a;
	}
	
	public function getTypeLabel() {
		return $this->_('Text input with jQuery UI datepicker'); 
	}

	/**
	 * Render ready
	 *
	 */
	public function renderReady() {
	
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;

		// this method only needs to run if datepicker is in use
		$datepicker = (int) $this->getSetting('datepicker');
		if(!$datepicker) return;

		list($dateFormat, $timeFormat) = $this->getInputFormat(true);
		if($dateFormat) {} // not used here

		$useTime = false;
		$language = $this->wire()->languages ? $this->wire()->user->language : null;

		$modules->get('JqueryCore'); // Jquery Core required before Jquery UI
		$modules->get('JqueryUI');
		$this->inputfield->addClass("InputfieldDatetimeDatepicker InputfieldDatetimeDatepicker{$datepicker}");

		if(strlen($timeFormat) && $datepicker != self::datepickerInline) {
			// add in the timepicker script, if applicable
			$useTime = true;
			$url = $config->urls->get('InputfieldDatetime');
			$config->scripts->add($url . 'timepicker/jquery-ui-timepicker-addon.min.js');
			$config->styles->add($url . 'timepicker/jquery-ui-timepicker-addon.min.css');
		}

		if($language) {
			// include i18n support for the datepicker 
			// note that the 'xx' in the filename is just a placeholder to indicate what should be replaced for translations, as that file doesn't exist
			$langFile = ltrim($this->_('/wire/modules/Jquery/JqueryUI/i18n/jquery.ui.datepicker-xx.js'), '/'); // Datepicker translation file // Replace 'xx' with jQuery UI language code or specify your own js file
			if(is_file($config->paths->root . $langFile)) {
				// add a custom language file
				$config->scripts->add($config->urls->root . $langFile);
			} else {
				// attempt to auto-find one based on the language name (which are often 2 char language codes)
				$langFile = "wire/modules/Jquery/JqueryUI/i18n/jquery.ui.datepicker-{$language->name}.js";
				if(is_file($config->paths->root . $langFile)) $config->scripts->add($config->urls->root . $langFile);
			}
			if($useTime) {
				$langFile = $this->_('timepicker/i18n/jquery-ui-timepicker-xx.js'); // Timepicker translation file // Replace 'xx' with jQuery UI language code or specify your own js file. Timepicker i18n files are located in /wire/modules/Inputfield/InputfieldDatetime/timepicker/i18n/.
				$path = $config->paths->get('InputfieldDatetime');
				$url = $config->urls->get('InputfieldDatetime');
				if(is_file($path . $langFile)) {
					// add a custom language file
					$config->scripts->add($url . $langFile);
				} else {
					// attempt to auto-find one based on the language name (which are often 2 char language codes)
					$langFile = str_replace('-xx.', "-$language->name.", $langFile);
					if(is_file($path . $langFile)) {
						$config->scripts->add($url . $langFile);
					}
				}
			}
		}
	}

	/**
	 * @return string
	 *
	 */
	public function render() {
	
		$sanitizer = $this->wire()->sanitizer;
		$datetime = $this->wire()->datetime;
		
		$datepicker = (int) $this->getSetting('datepicker');
		
		list($dateFormat, $timeFormat) = $this->getInputFormat(true);
		$useTime = false;
		if(strlen($timeFormat) && $datepicker && $datepicker != self::datepickerInline) $useTime = true;

		$attrs = $this->inputfield->getAttributes();
		$value = $attrs['value'];
		$valueTS = (int) $value*1000; // TS=for datepicker/javascript, which uses milliseconds rather than seconds
		unset($attrs['value']);

		if(!$value && $this->inputfield->getSetting('defaultToday')) {
			$value = date($dateFormat);
			if($timeFormat) $value .= ' ' . date($timeFormat);
			$valueTS = time()*1000;

		} else if($value) {
			$value = trim(date($dateFormat . ' ' . $timeFormat, (int) $value));
		}

		$value = $sanitizer->entities($value);

		$dateFormatJS = $sanitizer->entities($datetime->convertDateFormat($dateFormat, 'js'));
		$timeFormatJS = $useTime ? $datetime->convertDateFormat($timeFormat, 'js') : '';

		if(strpos($timeFormatJS, 'h24') !== false) {
			// 24 hour format
			$timeFormatJS = str_replace(array('hh24', 'h24'), array('HH', 'H'), $timeFormatJS);
			$ampm = 0;
		} else {
			$ampm = 1;
		}

		if(strlen($timeFormatJS)) $timeFormatJS = $sanitizer->entities($timeFormatJS);
		if(empty($value)) $value = '';
		
		$yearRange = $sanitizer->entities($this->getSetting('yearRange'));
		$timeInputSelect = $this->getSetting('timeInputSelect');
	
		$datepickerSettings = array();
		$settingNames = array(
			'showAnim', 'changeMonth', 'changeYear', 'showButtonPanel', 
			'numberOfMonths', 'showMonthAfterYear', 'showOtherMonths',
		);
		foreach($settingNames as $name) {
			$val = $this->inputfield->getSetting($name);
			if($name === 'showAnim') {
				$val = (string) $val;
				if($val === 'none') $val = '';
			} else if($name === 'numberOfMonths') {
				$val = (int) $val;
				if($val < 1) $val = 1;
			} else {
				$val = (bool) ((int) $val);
			}
			$datepickerSettings[$name] = $val;
		}
	
		// merge in any custom settings 
		$datepickerSettings = array_merge($datepickerSettings, $this->inputfield->datepickerOptions());

		$out =
			"<input " . $this->inputfield->getAttributesString($attrs) . " " .
			"value='$value' " .
			"autocomplete='off' " . 
			"data-dateformat='$dateFormatJS' " .
			"data-timeformat='$timeFormatJS' " .
			"data-timeselect='$timeInputSelect' " .
			"data-ts='$valueTS' " .
			"data-ampm='$ampm' " .
			(strlen($yearRange) ? "data-yearrange='$yearRange' " : '') .
			"data-datepicker='" . htmlspecialchars(json_encode($datepickerSettings)) . "' " . 
			"/>";

		return $out;
	}

	/**
	 * Render value
	 * 
	 * @return string
	 * 
	 */
	public function renderValue() {
		$value = $this->getAttribute('value');
		$format = $this->getSetting('dateInputFormat') . ' ' . $this->getSetting('timeInputFormat');
		return $format && $value ? $this->wire()->datetime->formatDate($value, trim($format)) : '';
	}

	/**
	 * @param WireInputData $input
	 * @return int|string|bool
	 *
	 */
	public function processInput(WireInputData $input) {
		return false; // tell InputfieldDatetime to process the input instead
	}
	
	/**
	 * Get the input format string for the user's language
	 *
	 * @param bool $getArray
	 * @return string|array of dateInputFormat timeInputFormat
	 *
	 */
	protected function getInputFormat($getArray = false) {

		$inputFormats = array();
		$language = $this->wire()->user->language;
		$useLanguages = $this->wire()->languages && $language && !$language->isDefault();

		foreach(array('date', 'time') as $type) {
			$inputFormat = '';
			if($useLanguages) {
				$inputFormat = trim($this->getSetting("{$type}InputFormat{$language->id}"));
			}
			if(!strlen($inputFormat)) {
				// fallback to default language
				$inputFormat = $this->getSetting("{$type}InputFormat");
			}
			$inputFormats[] = $inputFormat;
		}
		
		if($getArray) return $inputFormats;

		return trim(implode(' ', $inputFormats));
	}

	/**
	 * Sanitize value
	 * 
	 * @param int|string $value
	 * @return int|string
	 * 
	 */
	public function sanitizeValue($value) {
		// convert date string to unix timestamp
		$format = $this->getInputFormat();
		$value = $this->wire()->datetime->stringToTimestamp($value, $format);
		return $value;
	}

	/**
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {

		$languages = $this->wire()->languages;
		$datetime = $this->wire()->datetime;
		$modules = $this->wire()->modules;
		
		$dateInputFormat = $this->getSetting('dateInputFormat');
		$timeInputFormat = $this->getSetting('timeInputFormat');
		$timeInputSelect = (int) $this->getSetting('timeInputSelect');

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->label = $this->_('Date Picker');
		$f->setAttribute('name', 'datepicker');
		$f->addOption(self::datepickerNo, $this->_('No date/time picker'));
		$f->addOption(self::datepickerFocus, $this->_('Date/time picker on field focus') . ' ' .
			$this->_('(recommended)'));
		$f->addOption(self::datepickerClick, $this->_('Date/time picker on button click'));
		// @todo this datepickerInline option displays a datepicker that is too large, not fully styled
		$f->addOption(self::datepickerInline, $this->_('Inline date picker always visible (no time picker)'));
		$f->attr('value', (int) $this->getSetting('datepicker'));
		$inputfields->append($f);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->attr('name', '_dateTimeInputFormats');
		$fieldset->label = $this->_('Date/Time Input Formats');

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', '_dateInputFormat');
		$f->label = $this->_('Date Input Format');
		$f->description = $this->_('Select the format to be used for user input to this field. Your selection will populate the field below this, which you may customize further if needed.');
		$f->icon = 'calendar';
		$date = strtotime('2016-04-08 5:10:02 PM');
		foreach($datetime->getDateFormats() as $format) {
			$dateFormatted = $datetime->formatDate($date, $format);
			if($format == 'U') $dateFormatted .= " " . $this->_('(unix timestamp)');
			$f->addOption($format, $dateFormatted);
			if($dateInputFormat == $format) $f->attr('value', $format);
		}
		$f->attr('onchange', "$('#Inputfield_dateInputFormat').val($(this).val());");
		$fieldset->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', '_timeInputFormat');
		$f->label = $this->_('Time Input Format');
		$f->addOption('', $this->_('None'));
		$f->description = $this->_('Select an optional time format to be used for input. If used, the calendar option will include a time picker.');
		$f->icon = 'clock-o';
		foreach($datetime->getTimeFormats() as $format) {
			if(strpos($format, '!') === 0) continue; // skip relative formats
			$timeFormatted = $datetime->formatDate($date, $format);
			$f->addOption($format, $timeFormatted);
			if($timeInputFormat == $format) $f->attr('value', $format);
		}
		$f->attr('onchange', "$('#Inputfield_timeInputFormat').val($(this).val());");
		// $f->collapsed = Inputfield::collapsedBlank;
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get("InputfieldRadios");
		$f->attr('name', 'timeInputSelect');
		$f->label = $this->_('Time Input Type');
		$f->description = $this->_('Sliders (default) let the user slide controls to choose the time, where as Select lets the user select the time from a drop-down select.');
		$f->icon = 'clock-o';
		$f->addOption(0, $this->_('Sliders'));
		$f->addOption(1, $this->_('Select'));
		$f->optionColumns = 1;
		$f->columnWidth = 50;
		$f->showIf = "_timeInputFormat!='', datepicker!=" . self::datepickerNo;
		$f->attr('value', $timeInputSelect);
		$fieldset->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr('name', 'dateInputFormat');
		$f->attr('value', $dateInputFormat ? $dateInputFormat : InputfieldDatetime::defaultDateInputFormat);
		$f->attr('size', 20);
		$f->label = $this->_('Date Input Format Code');
		$f->description = $this->_('This is automatically built from the date select above, unless you modify it.');
		$f->icon = 'calendar';
		$notes = $this->_('See the [PHP date](http://www.php.net/manual/en/function.date.php) function reference for more information on how to customize these formats.');
		if($languages) $notes .= "\n" . $this->_('You may optionally specify formats for other languages here as well. Any languages left blank will inherit the default setting.');
		$f->notes = $notes;
		$f->collapsed = Inputfield::collapsedYes;
		$f1 = $f;
		$fieldset->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr('name', 'timeInputFormat');
		$f->attr('value', $timeInputFormat ? $timeInputFormat : '');
		$f->attr('size', 20);
		$f->label = $this->_('Time Input Format Code');
		$f->description = $this->_('This is automatically built from the time select above, unless you modify it.');
		$f->icon = 'clock-o';
		$f->notes = $notes;
		$f->collapsed = Inputfield::collapsedYes;
		$f2 = $f;

		if($languages) {
			$f1->useLanguages = true;
			$f2->useLanguages = true;
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$f1->set("value$language", (string) $this->getSetting("dateInputFormat$language"));
				$f2->set("value$language", (string) $this->getSetting("timeInputFormat$language"));
			}
		}

		$fieldset->add($f1);
		$fieldset->add($f2);

		$inputfields->add($fieldset);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->setAttribute('name', 'placeholder');
		$f->label = $this->_('Placeholder Text');
		$f->setAttribute('value', $this->getAttribute('placeholder'));
		$f->description = $this->_('Optional placeholder text that appears in the field when blank.');
		$f->columnWidth = 50;
		$inputfields->append($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->setAttribute('name', 'size');
		$f->label = $this->_('Size');
		$f->attr('value', $this->getAttribute('size'));
		$f->attr('size', 4);
		$f->description = $this->_('The displayed width of this field (in characters).');
		$f->columnWidth = 50;
		$inputfields->append($f);

		$fieldset = $inputfields->InputfieldFieldset;
		$fieldset->attr('name', '_datepicker_settings');
		$fieldset->label = $this->_('Datepicker settings');
		$fieldset->showIf = "datepicker!=" . self::datepickerNo;
		$inputfields->add($fieldset);
		$module = $this->inputfield;
		
		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr('name', 'yearRange');
		$f->attr('value', $this->getSetting('yearRange'));
		$f->attr('size', 10);
		$f->label = $this->_('Year Range');
		$f->description =
			$this->_('When predefined year selection is possible, there is a range of plus or minus 10 years from the current year.') . ' ' .
			$this->_('To modify this range, specify number of years before and after current year in this format: `-30:+20`, which would show 30 years before now and 20 years after now.');
		$f->notes = $this->_('Default is `-10:+10` which shows a year range 10 years before and after now.');
		$f->icon = 'arrows-h';
		$f->collapsed = Inputfield::collapsedBlank;
		$fieldset->append($f);

		$f = $inputfields->InputfieldSelect;
		$f->attr('name', 'showAnim');
		$f->label = $this->_('Animation type');
		$f->addOption('none', $this->_('None'));
		$f->addOption('fade', $this->_('Fade'));
		$f->addOption('show', $this->_('Show'));
		$f->addOption('clip', $this->_('Clip'));
		$f->addOption('drop', $this->_('Drop'));
		$f->addOption('puff', $this->_('Puff'));
		$f->addOption('scale', $this->_('Scale'));
		$f->addOption('slide', $this->_('Slide'));
		$f->val($module->get('showAnim'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldInteger;
		$f->attr('name', 'numberOfMonths');
		$f->label = $this->_('Number of month to show side-by-side in datepicker');
		$f->val((int) $module->get('numberOfMonths'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'changeMonth');
		$f->label = $this->_('Render month as select rather than text?');
		$f->val($module->get('changeMonth'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'changeYear');
		$f->label = $this->_('Render year as select rather than text?');
		$f->val($module->get('changeYear'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'showButtonPanel');
		$f->label = $this->_('Display “Today” and “Done” buttons under calendar?');
		$f->val($module->get('showButtonPanel'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'showMonthAfterYear');
		$f->label = $this->_('Show month after year?');
		$f->val($module->get('showMonthAfterYear'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'showOtherMonths');
		$f->label = $this->_('Show days in other months at start/end of months?');
		$f->val($module->get('showOtherMonths'));
		$f->columnWidth = 100;
		$fieldset->add($f);
	}


}
