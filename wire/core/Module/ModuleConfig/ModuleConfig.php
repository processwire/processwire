<?php namespace ProcessWire;

/**
 * ModuleConfig class
 * 
 * Serves as the base for classes dedicated to configuring modules. 
 * 
 * Descending class name should follow the format: [ModuleName]Config and file [ModuleName]Config.php
 * located in the same directory as the module it is configuring. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 */

class ModuleConfig extends WireData {

	/**
	 * Used when inputfields are defined by array
	 * 
	 * @var array
	 * 
	 */
	protected $inputfieldsArray = array();

	/**
	 * Use the construct method if you are defining your module config fields as an array
	 * 
	 * Example for method body:
	 * 
	 * $this->add(array(
	 *   array(
	 *     'name' => 'fullname'
	 *     'type' => 'text',
	 *     'label' => 'Full Name',
	 *     'value' => '', 
	 *   ),
	 *   array(
	 *     'name' => 'email', 
	 *     'type' => 'email',
	 *     'label' => 'Email Address', 
	 *     'placeholder' => 'you@company.com',
	 *     'value' => '', 
	 *   ), 
	 * ));
	 * 
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Return associative array of property name => default value
	 * 
	 * No need to implement this method in your class if defining the config as an array. 
	 * If implementing a getInputfields() method then you'll want to implement this one as well
	 * 
	 * @return array of 'fieldName' => 'default value'
	 * 
	 */
	public function getDefaults() {
		if(count($this->inputfieldsArray)) {
			$defaults = $this->identifyDefaults($this->inputfieldsArray); 
		} else {
			$defaults = array();
		}
		return $defaults;
	}

	/**
	 * Return an InputfieldWrapper of Inputfields necessary to configure this module
	 * 
	 * Values will be populated to the Inputfields automatically. However, you may also retrieve
	 * any of the values from $this->[property]; as needed. 
	 * 
	 * Descending classes should call this method at the top of their getInputfields() method. 
	 * 
	 * Use this method only if defining Inputfield objects programatically. If definining via
	 * an array then you should not implement this method. 
	 * 
	 * @return InputfieldWrapper
	 * 
	 */
	public function getInputfields() {
		foreach($this->getDefaults() as $key => $value) {
			$this->set($key, $value);
		}
		$inputfields = $this->wire(new InputfieldWrapper());
		if(count($this->inputfieldsArray)) {
			$inputfields->add($this->inputfieldsArray); 
		}
		return $inputfields;
	}

	/**
	 * Set an array that defines Inputfields
	 * 
	 * @param array $a
	 * @return self
	 * 
	 */
	public function add(array $a) {
		if(count($this->inputfieldsArray)) { 
			$this->inputfieldsArray = array_merge($this->inputfieldsArray, $a); 
		} else {
			$this->inputfieldsArray = $a;
		}
		return $this; 
	}
	
	/**
	 * Identify defaults from the given Inputfield definition array (internal use)
	 *
	 * This is used only when getDefaults() is not implemented by descending class,
	 * and inputfields use an array definition.
	 *
	 * @param array $a
	 * @return array
	 *
	 */
	private function identifyDefaults($a) {
		$defaults = array();
		foreach($a as $name => $info) {
			if(isset($info['name'])) $name = $info['name'];
			$value = isset($info['value']) ? $info['value'] : '';
			if(is_string($name)) $defaults[$name] = $value;
			if(!empty($info['children'])) {
				$defaults2 = $this->identifyDefaults($info['children']);
				$defaults = array_merge($defaults, $defaults2);
			}
		}
		return $defaults;
	}

	
}
