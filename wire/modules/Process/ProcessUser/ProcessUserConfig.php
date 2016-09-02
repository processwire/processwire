<?php namespace ProcessWire;

class ProcessUserConfig extends ModuleConfig {
	public function __construct() {
		$this->add(array(
			array(
				'name' => 'maxAjaxQty',
				'type' => 'integer', 
				'label' => $this->_('Max users to show in AJAX drop down menu'), 
				'description' => $this->_('When the number of users in the system exceeds this amount, the drop-down navigation will instead show roles with user counts rather than users.'), 
				'notes' => $this->_('Specify a value between 1 and 100 (default=25)'), 
				'value' => 25, 
				'min' => 1, 
				'max' => 100, 
				'required' => true
			)
		));
	}
}