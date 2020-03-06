<?php namespace ProcessWire;

/**
 * HTML5 date/time input types
 * 
 */
class InputfieldDatetimeHtml extends InputfieldDatetimeType {

	public function getDefaultSettings() {
		return array(
			'htmlType' => 'date',
			'dateStep' => 0,
			'dateMin' => '',
			'dateMax' => '',
			'timeStep' => 0,
			'timeMin' => '',
			'timeMax' => '',
		);
	}

	public function getTypeLabel() {
		return $this->_('HTML5 browser native date, time or both');
	}

	/**
	 * Render ready
	 *
	 */
	public function renderReady() {
		if($this->getSetting('htmlType') === 'datetime') {
			$this->inputfield->addClass('InputfieldDatetimeMulti', 'wrapClass'); // multiple unputs
		}
	}

	/**
	 * @return string
	 *
	 */
	public function render() {
		$out = '';
		switch($this->getSetting('htmlType')) {
			case 'date': $out = $this->renderDate(); break;
			case 'time': $out = $this->renderTime(); break;
			case 'datetime': $out = $this->renderDate() . '&nbsp;' . $this->renderTime(); break;
		}
		return $out; 
	}

	/**
	 * Render date input
	 * 
	 * @return string
	 * 
	 */
	protected function renderDate() {
		
		$format = InputfieldDatetime::defaultDateInputFormat;
		$dateStep = (int) $this->getSetting('dateStep');
		$attrs = $this->inputfield->getAttributes();

		unset($attrs['size']);

		$value = $attrs['value'];
		if(!$value && $this->getSetting('defaultToday')) $value = time();
		$value = $value ? date($format, $value) : '';

		$attrs['type'] = 'date';
		$attrs['value'] = $value;
		$attrs['placeholder'] = 'yyyy-mm-dd'; // placeholder and pattern...
		$attrs['pattern'] = '[0-9]{4}-[0-9]{2}-[0-9]{2}'; // ...used only if browser does not support HTML5 date

		if($dateStep > 1) {
			$attrs['step'] = $dateStep;
		}

		foreach(array('min' => 'dateMin', 'max' => 'dateMax') as $attrName => $propertyName) {
			$attrValue = $this->getSetting($propertyName);
			if(!$attrValue || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attrValue)) continue;
			$attrs[$attrName] = $attrValue;
		}

		return "<input " . $this->inputfield->getAttributesString($attrs) . " />";
	}

	/**
	 * Render time input
	 * 
	 * @return string
	 * 
	 */
	protected function renderTime() {
		
		$timeStep = (int) $this->getSetting('timeStep');
		$useSeconds = $timeStep > 0 && $timeStep < 60;
		$format = $useSeconds ? InputfieldDatetime::secondsTimeInputFormat : InputfieldDatetime::defaultTimeInputFormat;
		$attrs = $this->inputfield->getAttributes();

		unset($attrs['size']);

		$value = $attrs['value'];
		if(!$value && $this->getSetting('defaultToday')) $value = time();
		$value = $value ? date($format, $value) : '';

		$attrs['type'] = 'time';
		$attrs['value'] = $value;

		// placeholder and pattern used only if browser does not support HTML5 time
		$attrs['placeholder'] = 'hh:mm' . ($useSeconds ? ':ss' : '');
		$attrs['pattern'] = '[0-9]{2}:[0-9]{2}' . ($useSeconds ? ':[0-9]{2}' : '');

		if($timeStep > 0) {
			$attrs['step'] = $timeStep;
		}

		foreach(array('min' => 'timeMin', 'max' => 'timeMax') as $attrName => $propertyName) {
			$attrValue = $this->getSetting($propertyName);
			if(!$attrValue || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $attrValue)) continue;
			$attrs[$attrName] = $attrValue;
		}

		if($this->getSetting('htmlType') == 'datetime') {
			$attrs['name'] .= '__time';
			$attrs['id'] .= '__time';
		}

		return "<input " . $this->inputfield->getAttributesString($attrs) . " />";
	}

	/**
	 * @param WireInputData $input
	 * @return string
	 *
	 */
	public function processInput(WireInputData $input) {
		
		$name = $this->getAttribute('name');
		$value = $input->$name;
		
		switch($this->getSetting('htmlType')) {
			case 'datetime':
				$dateValue = trim($input->$name);
				$timeName = $name . '__time';
				$timeValue = trim($input->$timeName);
				// if time present but no date, substitute today's date
				if(!strlen($dateValue) && strlen($timeValue)) $dateValue = date('Y-m-d'); 
				$value = strlen($dateValue) ? strtotime(trim("$dateValue $timeValue")) : '';
				break;
			case 'date':
			case 'time':
				$value = $this->sanitizeValue($value);
				break;
			default:
				$value = $value ? strtotime($value) : '';
		}
		
		return $value; 
	}
	
	public function sanitizeValue($value) {
	
		$htmlType = $this->getSetting('htmlType');
		$value = trim($value);
		
		if(!strlen($value)) return '';
		if(ctype_digit($value)) return (int) $value;
		
		if($htmlType === 'time' && !strpos($value, '-') && preg_match('/^\d+:/', $value)) {
			// hh:mm:ss
			$subDate = $this->inputfield->subDate();
			$value = strtotime("$subDate $value");
			if($value === false) $value = '';
		} else if($htmlType === 'date') {
			$subTime = $this->inputfield->subTime();
			$value = strtotime("$value $subTime"); 
		} else {
			$value = parent::sanitizeValue($value);
		}
			
		return $value;
	}

	/**
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {
	
		/** @var Modules $modules */
		$modules = $this->wire('modules');
	
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'htmlType'); 
		$f->label = $this->_('HTML input type'); 
		$f->addOption('date', $this->_('Date'));
		$f->addOption('time', $this->_('Time'));
		$f->addOption('datetime', $this->_('Both date and time'));
		$f->val($this->getSetting('htmlType'));
		$inputfields->add($f);
		
		/** @var InputfieldInteger $f */
		/*
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'dateStep');
		$f->label = $this->_('Step days for date input');
		if((int) $this->getSetting('dateStep') > 0) $f->attr('value', (int) $this->getSetting('dateStep'));
		$f->columnWidth = 33;
		$f->showIf = 'htmlType=date|datetime';
		$inputfields->add($f);
		*/

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('type', 'date');
		$f->attr('name', 'dateMin');
		$f->label = $this->_('Minimum allowed date');
		if($this->getSetting('dateMin')) $f->val($this->getSetting('dateMin'));
		$f->showIf = 'htmlType=date|datetime';
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('type', 'date');
		$f->inputType = 'html';
		$f->attr('name', 'dateMax');
		$f->label = $this->_('Maximum allowed date');
		if($this->getSetting('dateMax')) $f->val($this->getSetting('dateMax'));
		$f->showIf = 'htmlType=date|datetime';
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'timeStep');
		$f->label = $this->_('Step seconds for time input');
		if((int) $this->getSetting('timeStep') > 0) $f->attr('value', (int) $this->getSetting('timeStep'));
		$f->showIf = 'htmlType=time|datetime';
		$f->columnWidth = 33;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('type', 'time');
		$f->attr('name', 'timeMin');
		$f->label = $this->_('Minimum allowed time');
		if($this->getSetting('timeMin')) $f->val($this->getSetting('timeMin'));
		$f->showIf = 'htmlType=time|datetime';
		$f->columnWidth = 33;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('type', 'time');
		$f->attr('name', 'timeMax');
		$f->label = $this->_('Maximum allowed time');
		if($this->getSetting('timeMax')) $f->val($this->getSetting('timeMax'));
		$f->showIf = 'htmlType=time|datetime';
		$f->columnWidth = 34;
		$inputfields->add($f);
	}


}