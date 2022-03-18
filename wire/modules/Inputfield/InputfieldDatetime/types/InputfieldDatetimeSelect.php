<?php namespace ProcessWire;

/**
 * 
 */
class InputfieldDatetimeSelect extends InputfieldDatetimeType {
	
	public function getDefaultSettings() {
		$year = (int) date('Y');
		return array(
			'dateSelectFormat' => 'yMd',
			'timeSelectFormat' => '',
			'yearFrom' => $year - 100, 
			'yearTo' => $year + 20,
			'yearLock' => false,
		);
	}
	
	public function getTypeLabel() {
		return $this->_('Separate select inputs for month, day, and year (and optionally time)'); 
	}

	/**
	 * Get years range
	 * 
	 * @param int $valueYear
	 * @return array of [ $yearFrom, $yearTo ] 
	 * 
	 */
	protected function getYearsRange($valueYear) {
		
		$defaults = $this->getDefaultSettings();
		$yearFrom = $this->getSetting('yearFrom');
		$yearTo = $this->getSetting('yearTo');
		$yearLock = (int) $this->getSetting('yearLock');
		
		if(!$yearFrom) $yearFrom = $defaults['yearFrom'];
		if(!$yearTo) $yearTo = $defaults['yearTo'];

		if($yearFrom > $yearTo) {
			list($yearFrom, $yearTo) = array($yearTo, $yearFrom);
		}

		if($valueYear && !$yearLock) {
			// there is already a year value present
			$numYears = $yearTo > $yearFrom ? ceil(($yearTo - $yearFrom) / 2) : 1;
			if($valueYear > $yearTo || $valueYear < $yearFrom) {
				// year is before or after that accounted for in selectable range, so change the range
				$yearTo = $valueYear + $numYears;
				$yearFrom = $valueYear - $numYears;
			}
		}
		
		return array($yearFrom, $yearTo); 
	}
	
	/**
	 * Render ready
	 *
	 */
	public function renderReady() {
		// "Multi" indicates multiple inputs constructing date/time
		$this->inputfield->addClass('InputfieldDatetimeSelect InputfieldDatetimeMulti', 'wrapClass');
	}

	/**
	 * @return string
	 *
	 */
	public function render() {

		$name = $this->getAttribute('name');
		$value = (int) $this->getAttribute('value');
		$valueYear = $value ? date('Y', $value) : 0;
		$yearLock = $this->getSetting('yearLock');
		$format = $this->getSetting('dateSelectFormat');
		$select = $this->modules->get('InputfieldSelect'); /** @var InputfieldSelect $select */
		$sanitizer = $this->wire()->sanitizer;
		$datetime = $this->wire()->datetime;
		$monthLabel = $this->_('Month');
		$yearLabel = $this->_('Year');
		$dayLabel = $this->_('Day');
		$select->addClass('InputfieldSetWidth');

		$months = clone $select;
		$months->attr('id+name', $name . '__m');
		$months->attr('title', $monthLabel);
		$months->addClass('InputfieldDatetimeMonth');
		$months->addOption('', $monthLabel);
		$abbreviate = strpos($format, 'M') === false;

		for($n = 1; $n <= 12; $n++) {
			$monthFormat = $abbreviate ? '%b' : '%B';
			$monthLabel = $sanitizer->entities($datetime->strftime($monthFormat, mktime(0, 0, 0, $n, 1)));
			$months->addOption($n, $monthLabel);
		}

		list($yearFrom, $yearTo) = $this->getYearsRange($valueYear); 

		$years = clone $select;
		$years->attr('id+name', $name . '__y');
		$years->attr('title', $yearLabel);
		$years->attr('data-from-year', $yearFrom);
		$years->attr('data-to-year', $yearTo);
		$years->addClass('InputfieldDatetimeYear');
		$years->addOption('', $yearLabel);
		if(!$yearLock) $years->addOption("-", "< $yearFrom"); 
		
		for($n = $yearFrom; $n <= $yearTo; $n++) {
			$years->addOption($n, $n);
		}
		
		if(!$yearLock) $years->addOption("+", "> $yearTo"); 
	
		$days = clone $select;
		$days->attr('id+name', $name . '__d');
		$days->attr('title', $dayLabel);
		$days->addClass('InputfieldDatetimeDay');
		$days->addOption('', $dayLabel);

		for($n = 1; $n <= 31; $n++) {
			$days->addOption($n, $n);
		}

		if($value) {
			$months->val(date('n', $value));
			$days->val(date('j', $value));
			$years->val($valueYear);
		}

		$a = array();
		for($n = 0; $n < strlen($format); $n++) {
			switch(strtolower($format[$n])) {
				case 'm': $a[] = $months->render(); break;
				case 'y': $a[] = $years->render(); break;
				case 'd': $a[] = $days->render(); break;
			}
		}
		
		$attrs = $this->inputfield->getAttributes();
		$attrs['type'] = 'hidden';
		if($value) $attrs['value'] = date(InputfieldDatetime::defaultDateInputFormat . ' ' . InputfieldDatetime::defaultTimeInputFormat, $value);
		unset($attrs['size'], $attrs['placeholder'], $attrs['class'], $attrs['required']);
		$attrs['class'] = 'InputfieldDatetimeValue';
		$attrStr = $this->inputfield->getAttributesString($attrs);
		$out = implode('&nbsp;', $a) . "<input $attrStr />"; // hidden input for dependencies if needed

		return $out; 
	}

	/**
	 * @param WireInputData $input
	 * @return string
	 *
	 */
	public function processInput(WireInputData $input) {
		
		$name = $this->getAttribute('name');
		
		$a = array(
			'second' => 0,
			'hour' => 0,
			'minute' => 0,
			'month' => (int) $input[$name . '__m'],
			'year' => (int) $input[$name . '__y'],
			'day' => (int) $input[$name . '__d'],
		);

		if(!strlen(trim("$a[month]$a[day]$a[year]", "0"))) {
			// empty value
			$this->setAttribute('value', '');
			return '';
		}

		if(empty($a['month'])) $a['month'] = 1;
		if($a['month'] > 12) $a['month'] = 12;
		if(empty($a['year'])) $a['year'] = date('Y');
		if(empty($a['day'])) $a['day'] = 1;
		if($a['day'] > 31) $a['day'] = 31;
		
		if((int) $this->getSetting('yearLock')) {
			list($yearFrom, $yearTo) = $this->getYearsRange($a['year']); 
			if($a['year'] < $yearFrom || $a['year'] > $yearTo) {
				// year is outside selectable range
				$this->setAttribute('value', '');
				return '';
			}
		}

		$value = mktime($a['hour'], $a['minute'], $a['second'], $a['month'], $a['day'], $a['year']);

		foreach($a as $k => $v) {
			if($k === 'year') continue;
			if(strlen("$v") === 1) $a[$k] = "0$v";
		}

		$test1 = "$a[year]-$a[month]-$a[day]"; // $a[hour]:$a[minute]";
		$test2 = date('Y-m-d', $value);
		if($test1 !== $test2) {
			$this->inputfield->error(sprintf($this->_('Invalid date “%1$s” changed to “%2$s”'), $test1, $test2));
		}

		if($value) $this->setAttribute('value', $value); 
		
		return $value;
	}

	/**
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {
		
		list($y, $d, $h, $hh, $i, $a) = explode(' ', date('Y d h H i A'));
		// list($m, $mm) = explode(' ', strftime('%b %B')); // strftime deprecated
		list($m, $mm) = explode(' ', date('M F'));
		$none = $this->_('None');
		if($m === $mm && $m === 'May') list($m, $mm) = array('Apr', 'April');

		$dateOptions = array(
			'' => $none,
			'mdy' => "$m $d $y",
			'Mdy' => "$mm $d $y",
			'dmy' => "$d $m $y",
			'dMy' => "$d $mm $y",
			'ymd' => "$y $m $d",
			'yMd' => "$y $mm $d",
			// @todo: add options for 2-part dates (month year, day month, etc.)
			//'md_' => "$m $d", 
			//'dm_' => "$d $m", 
			//'my.' => "$m $y", 
			//'ym.' => "$y $m",
			//'Md_' => "$mm $d", 
			//'dM_' => "$d $mm", 
			//'My.' => "$mm $y", 
			//'yM.' => "$y $mm"
		);

		if($mm === $m) {
			$abbrLabel = $this->_('(abbreviated)');
			foreach($dateOptions as $key => $value) {
				if(stripos($key, 'm') !== false) {
					$dateOptions[$key] .= " $abbrLabel";
				}
			}
		}

		/** @var InputfieldSelect $f */
		$f = $this->modules->get('InputfieldSelect');
		$f->attr('name', 'dateSelectFormat');
		$f->label = $this->_('Date select format to use');
		$f->addOptions($dateOptions);
		$f->val($this->getSetting('dateSelectFormat'));
		$f->notes = $this->_('Month names are language/locale based');
		$inputfields->add($f);

		// @todo add time select option
		//$f->columnWidth = 50;
		$timeOptions = array(
			'' => $none,
			'hia' => "$h:$i $a",
			'Hi' => "$hh:$i"
		);
		/*
		$f = $this->modules->get('InputfieldSelect');
		$f->attr('name', 'timeSelectFormat');
		$f->label = $this->_('Time select format to use');
		$f->addOptions($timeOptions);
		$f->val($this->timeSelectFormat);
		$f->columnWidth = 50;
		$inputfields->add($f);
		*/
	
		/** @var InputfieldInteger $f */
		$f = $this->modules->get('InputfieldInteger');
		$f->attr('name', 'yearFrom');
		$f->label = $this->_('First selectable year');
		$f->val($this->getSetting('yearFrom'));
		$f->columnWidth = 33;
		$inputfields->add($f);
		
		/** @var InputfieldInteger $f */
		$f = $this->modules->get('InputfieldInteger');
		$f->attr('name', 'yearTo');
		$f->label = $this->_('Last selectable year');
		$f->val($this->getSetting('yearTo'));
		$f->columnWidth = 33;
		$inputfields->add($f);
	
		/** @var InputfieldToggle $f */
		$f = $this->modules->get('InputfieldToggle');
		$f->attr('name', 'yearLock'); 
		$f->label = $this->_('Limit selection to these years?');
		$f->val((int) $this->getSetting('yearLock')); 
		$f->columnWidth = 34; 
		$inputfields->add($f);
	}


}