<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldToggle module.
 *
 */
class WireTest_InputfieldToggle extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testSanitizeValue();
		$this->testLabelsAndOptions();
		$this->testCustomLabels();
		$this->testCustomOptions();
		$this->testProcessInput();
		$this->testRenderToggle();
		$this->testDelegatedRendering();
		$this->testRenderReadyDeselect();
		$this->testConstants();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'active') {
		$f = $this->wire()->modules->get('InputfieldToggle');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldToggle $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default value is unknown', InputfieldToggle::valueUnknown, $f->val());
		$this->check('default labelType is yes/no', InputfieldToggle::labelTypeYes, $f->labelType);
		$this->check('default yesLabel is check mark', '✓', $f->yesLabel);
		$this->check('default noLabel is x mark', '✗', $f->noLabel);
		$this->check('default otherLabel is question mark', '?', $f->otherLabel);
		$this->check('default useOther is false', 0, $f->useOther);
		$this->check('default useReverse is false', 0, $f->useReverse);
		$this->check('default useVertical is false', 0, $f->useVertical);
		$this->check('default useDeselect is false', 0, $f->useDeselect);
		$this->check('default defaultOption is none', 'none', $f->defaultOption);
		$this->check('default inputfieldClass renders as toggle', true, empty($f->inputfieldClass));
		$this->check('unknown value is empty', true, $f->isEmpty());
		$f->val(InputfieldToggle::valueNo);
		$this->check('valueNo is not empty', false, $f->isEmpty());
	}

	protected function testSanitizeValue() {
		$f = $this->newInputfield();

		$this->check('sanitize true returns yes', InputfieldToggle::valueYes, $f->sanitizeValue(true));
		$this->check('sanitize false returns no', InputfieldToggle::valueNo, $f->sanitizeValue(false));
		$this->check('sanitize int 1 returns yes', InputfieldToggle::valueYes, $f->sanitizeValue(1));
		$this->check('sanitize int 0 returns no', InputfieldToggle::valueNo, $f->sanitizeValue(0));
		$this->check('sanitize int 2 returns other value', InputfieldToggle::valueOther, $f->sanitizeValue(2));
		$this->check('sanitize yes string returns yes', InputfieldToggle::valueYes, $f->sanitizeValue('yes'));
		$this->check('sanitize on string returns yes', InputfieldToggle::valueYes, $f->sanitizeValue('on'));
		$this->check('sanitize true string returns yes', InputfieldToggle::valueYes, $f->sanitizeValue('true'));
		$this->check('sanitize no string returns no', InputfieldToggle::valueNo, $f->sanitizeValue('no'));
		$this->check('sanitize off string returns no', InputfieldToggle::valueNo, $f->sanitizeValue('off'));
		$this->check('sanitize false string returns no', InputfieldToggle::valueNo, $f->sanitizeValue('false'));
		$this->check('sanitize unknown string returns unknown', InputfieldToggle::valueUnknown, $f->sanitizeValue('unknown'));
		$this->check('sanitize empty string returns unknown', InputfieldToggle::valueUnknown, $f->sanitizeValue(''));
		$this->check('sanitize null returns unknown', InputfieldToggle::valueUnknown, $f->sanitizeValue(null));
		$this->check('sanitize unrecognized string returns unknown', InputfieldToggle::valueUnknown, $f->sanitizeValue('maybe'));

		$this->check('sanitize getName true returns yes', 'yes', $f->sanitizeValue(true, true));
		$this->check('sanitize getName false returns no', 'no', $f->sanitizeValue(false, true));
		$this->check('sanitize getName null returns unknown', 'unknown', $f->sanitizeValue(null, true));
	}

	protected function testLabelsAndOptions() {
		$f = $this->newInputfield();

		$labels = $f->getLabels(InputfieldToggle::labelTypeYes);
		$this->check('yes label type returns Yes', 'Yes', $labels['yes']);
		$this->check('yes label type returns No', 'No', $labels['no']);
		$this->check('yes label type returns Unknown', 'Unknown', $labels['unknown']);

		$labels = $f->getLabels(InputfieldToggle::labelTypeTrue);
		$this->check('true label type returns True', 'True', $labels['yes']);
		$this->check('true label type returns False', 'False', $labels['no']);

		$labels = $f->getLabels(InputfieldToggle::labelTypeOn);
		$this->check('on label type returns On', 'On', $labels['yes']);
		$this->check('on label type returns Off', 'Off', $labels['no']);

		$labels = $f->getLabels(InputfieldToggle::labelTypeEnabled);
		$this->check('enabled label type returns Enabled', 'Enabled', $labels['yes']);
		$this->check('enabled label type returns Disabled', 'Disabled', $labels['no']);

		$options = $f->getOptions();
		$this->check('default options start with yes', InputfieldToggle::valueYes, key($options));
		$this->check('default options include yes/no only', array(1, 0), array_keys($options));

		$f->useReverse = true;
		$this->check('useReverse puts no first', array(0, 1), array_keys($f->getOptions()));

		$f->useOther = true;
		$this->check('useOther includes other value', true, array_key_exists(InputfieldToggle::valueOther, $f->getOptions()));
		$this->check('getValueLabel other returns other label', '?', $f->getValueLabel(InputfieldToggle::valueOther));
	}

	protected function testCustomLabels() {
		$f = $this->newInputfield();
		$f->labelType = InputfieldToggle::labelTypeCustom;
		$f->yesLabel = 'Yes please';
		$f->noLabel = 'No thanks';
		$f->otherLabel = 'Not sure';
		$f->useOther = true;

		$this->check('custom yes label returned', 'Yes please', $f->getValueLabel(InputfieldToggle::valueYes));
		$this->check('custom no label returned', 'No thanks', $f->getValueLabel(InputfieldToggle::valueNo));
		$this->check('custom other label returned', 'Not sure', $f->getValueLabel(InputfieldToggle::valueOther));
		$this->check('sanitize matches custom yes label', InputfieldToggle::valueYes, $f->sanitizeValue('Yes please'));
		$this->check('sanitize matches custom no label', InputfieldToggle::valueNo, $f->sanitizeValue('No thanks'));

		$f->yesLabel = 'icon-check Yes please';
		$this->check('formatLabel converts icon name', 'fa-check', $f->formatLabel($f->yesLabel), '*=');
		$this->check('formatLabel can suppress icon markup', false, strpos($f->formatLabel($f->yesLabel, false), 'fa-check') !== false);
	}

	protected function testCustomOptions() {
		$f = $this->newInputfield();
		$this->check('addOption returns same instance', true, $f === $f->addOption(1, 'Approved'));
		$f->addOption(2, 'Pending');
		$f->addOption(0, 'Rejected');

		$this->check('custom options replace built-in options', array(1 => 'Approved', 2 => 'Pending', 0 => 'Rejected'), $f->getOptions());
		$this->check('custom option value is accepted', 2, $f->sanitizeValue(2));
		$this->check('missing custom option returns unknown', InputfieldToggle::valueUnknown, $f->sanitizeValue(3));
		$f->val(0);
		$this->check('custom option zero is not empty', false, $f->isEmpty());

		$f = $this->newInputfield();
		$f->setOptions(array(-1 => 'Rejected', 0 => 'Pending', 1 => 'Approved'));
		$this->check('setOptions accepts integer custom options', array(-1 => 'Rejected', 0 => 'Pending', 1 => 'Approved'), $f->getOptions());

		$f = $this->newInputfield();
		$threw = false;
		try {
			$f->addOption(-129, 'Too low');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('custom option below range throws', true, $threw);

		$threw = false;
		try {
			$f->addOption(128, 'Too high');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('custom option above range throws', true, $threw);

		$threw = false;
		try {
			$f->addOption('abc', 'Not integer');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('custom option non-integer throws', true, $threw);
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('active');
		$f->val(InputfieldToggle::valueUnknown);
		$this->processInput($f, array('active' => '1', '_active_' => '1'));
		$this->check('processInput selects yes', InputfieldToggle::valueYes, $f->val());

		$this->processInput($f, array('active' => '0', '_active_' => '1'));
		$this->check('processInput selects no', InputfieldToggle::valueNo, $f->val());

		$f->useOther = true;
		$this->processInput($f, array('active' => '2', '_active_' => '1'));
		$this->check('processInput selects other when enabled', InputfieldToggle::valueOther, $f->val());

		$f->useOther = false;
		$f->val(InputfieldToggle::valueYes);
		$this->processInput($f, array('active' => '2', '_active_' => '1'));
		$this->check('processInput ignores other when disabled', InputfieldToggle::valueYes, $f->val());

		$this->processInput($f, array('_active_' => '1'));
		$this->check('processInput helper without value sets unknown', InputfieldToggle::valueUnknown, $f->val());

		$f->val(InputfieldToggle::valueYes);
		$this->processInput($f, array());
		$this->check('processInput ignored when input was not rendered', InputfieldToggle::valueYes, $f->val());

		$f = $this->newInputfield('status');
		$f->setOptions(array(-1 => 'Rejected', 0 => 'Pending', 1 => 'Approved'));
		$this->processInput($f, array('status' => '-1', '_status_' => '1'));
		$this->check('processInput accepts negative custom option', -1, $f->val());
	}

	protected function testRenderToggle() {
		$f = $this->newInputfield('active');
		$f->val(InputfieldToggle::valueYes);
		$html = $f->render();

		$this->check('render returns toggle group', 'InputfieldToggleGroup', $html, '*=');
		$this->check('render includes yes radio', "value='1'", $html, '*=');
		$this->check('render includes no radio', "value='0'", $html, '*=');
		$this->check('render marks current value checked', 'InputfieldToggleChecked', $html, '*=');
		$this->check('render includes helper field', 'name="_active_"', $html, '*=');

		$f = $this->newInputfield('active');
		$f->defaultOption = 'yes';
		$f->render();
		$this->check('render applies default yes option', InputfieldToggle::valueYes, $f->val());

		$f = $this->newInputfield('active');
		$f->useOther = true;
		$f->defaultOption = 'other';
		$f->render();
		$this->check('render applies default other option when enabled', InputfieldToggle::valueOther, $f->val());
	}

	protected function testDelegatedRendering() {
		$f = $this->newInputfield('active');
		$f->inputfieldClass = 'InputfieldRadios';
		$f->useVertical = false;
		$inputfield = $f->getInputfield();
		$this->check('getInputfield delegates to radios', true, $inputfield instanceof InputfieldRadios);
		$this->check('radios delegate uses same name', 'active', $inputfield->attr('name'));
		$this->check('horizontal radios use one option column', 1, $inputfield->optionColumns);
		$this->check('delegated radios render radio inputs', "type='radio'", $f->render(), '*=');

		$f = $this->newInputfield('active');
		$f->inputfieldClass = 'InputfieldSelect';
		$inputfield = $f->getInputfield();
		$this->check('getInputfield delegates to select', true, $inputfield instanceof InputfieldSelect);
		$this->check('delegated select render returns select element', '<select', $f->render(), '*=');
	}

	protected function testRenderReadyDeselect() {
		$f = $this->newInputfield();
		$f->useDeselect = true;
		$f->defaultOption = 'none';
		$f->renderReady();
		$this->check('useDeselect adds wrapper class when default none', true, $f->hasClass('InputfieldToggleUseDeselect', 'wrapClass'));
	}

	protected function testConstants() {
		$this->check('labelTypeYes constant', 0, InputfieldToggle::labelTypeYes);
		$this->check('labelTypeTrue constant', 1, InputfieldToggle::labelTypeTrue);
		$this->check('labelTypeOn constant', 2, InputfieldToggle::labelTypeOn);
		$this->check('labelTypeEnabled constant', 3, InputfieldToggle::labelTypeEnabled);
		$this->check('labelTypeCustom constant', 100, InputfieldToggle::labelTypeCustom);
		$this->check('valueNo constant', 0, InputfieldToggle::valueNo);
		$this->check('valueYes constant', 1, InputfieldToggle::valueYes);
		$this->check('valueOther constant', 2, InputfieldToggle::valueOther);
		$this->check('valueUnknown constant', '', InputfieldToggle::valueUnknown);
	}
}
