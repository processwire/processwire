<?php namespace ProcessWire;

/**
 * WireAction
 *
 * Base class for actions in ProcessWire
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @method bool action($item)
 * @method int executeMultiple($items)
 * @method InputfieldWrapper getConfigInputfields()
 *
 */
abstract class WireAction extends WireData implements Module {

	/**
	 * Return array of module information
	 *
	 * @return array
	 *
	public static function getModuleInfo() {
		return array(
			'title' => 'WireAction (abstract)', 
			'summary' => 'Base class for WireActions',
			'version' => 0
			);
	}
	 */

	/**
	 * Instance of the object that is running this action
	 *
	 */
	protected $runner = null;

	/**
	 * Text summary of what the action did (for logging, notifications, output)
	 *
	 */
	protected $summary = '';

	/**
	 * Define any default values for configuration
	 *
	 */
	public function __construct() {
		parent::__construct();
		// $this->set('key', 'value'); 
	}

	/**
	 * Module initialization
	 *
	 */
	public function init() { }

	/**
	 * Return the string type (class name) of items that this action operates upon
	 *
	 * @return string
	 *
	 */
	public function getItemType() {
		return strlen(__NAMESPACE__) ? __NAMESPACE__ . '\\Wire' : 'Wire';
	}

	/**
	 * Is the given item valid for use by this action?
	 *
	 * @param object $item
	 * @return bool True if valid, false if not
	 *
	 */
	public function isValidItem($item) {
		$type = $this->getItemType();
		return $item instanceof $type;
	}

	/**
	 * Perform the action on the given item
	 *
	 * @param Wire $item Item to operate upon
	 * @return bool True if the item was successfully operated upon, false if not. 
	 *
	 */
	abstract protected function ___action($item);

	/**
	 * Execute the action for the given item
	 *
	 * @param Wire $item Item to operate upon
	 * @return bool True if the item was successfully operated upon, false if not. 
	 *
	 */
	public function execute($item) {
		
		if(!$this->isValidItem($item)) {
			$this->error("Invalid item: $item", Notice::debug); 
			return false;
		}

		try {
			$result = $this->action($item); 

		} catch(\Exception $e) {
			$this->trackException($e, true);
			$result = false; 
			$this->error($e->getMessage()); 
		}

		return $result; 
	}

	/**
	 * Execute the action for multiple items at once
	 *
	 * @param array|WireArray $items Items to operate upon
	 * @return int Returns quantity of items successfully operated upon
	 * @throws WireException when it receives an unexpected type for $items
	 *
	 */
	public function ___executeMultiple($items) {
		if(!is_array($items) && !$items instanceof WireArray) {
			throw new WireException("Expected an array or WireArray!"); 
		}
		$result = 0; 
		foreach($items as $item) {
			if($this->execute($item)) $result++;
		}
		return $result;
	}

	/**
	 * Return any Inputfields needed to configure this action
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields() {
		$info = $this->wire('modules')->getModuleInfoVerbose($this->className(), array('noCache' => true));
		$fieldset = $this->wire('modules')->get('InputfieldFieldset');
		$fieldset->label = $info['title'];
		$fieldset->description = $info['summary'];
		return $fieldset; 
	}

	/**
	 * Set the object instance that is running this action
	 *
	 * If an action knows that it only accepts a certain type of runner, then 
	 * it should throw a WireException if the given runner is not valid.
	 *
	 * @param Wire $runner
	 *
	 */
	public function setRunner(Wire $runner) {
		$this->runner = $runner; 
	}

	/**
	 * Get the object instance that is running this action
	 *
	 * Actions should not generally depend on a particular runner, but should take advantage
	 * of a specific runner if it benefits the action. 
	 *
	 * @return Wire|null Returns null if no runner has been set
	 *
	 */
	public function getRunner() {
		return $this->runner; 
	}

	public function message($text, $flags = 0) {
		$runner = $this->getRunner(); 
		if($runner) $runner->message($text, $flags); 
			else parent::message($text, $flags); 
		return $this; 
	}

	public function error($text, $flags = 0) {
		$runner = $this->getRunner(); 
		if($runner) $runner->error($text, $flags); 
			else parent::error($text, $flags); 
		return $this; 
	}

	/**
	 * Get or set a text summary of what this action did
	 *
	 * @param string|null $text Set the summary or omit to only retrieve the summary
	 * @return string Always returns the current summary text or blank string if not set
	 *
	 */
	public function summary($text = null) {
		if(!is_null($text)) $this->summary = $text;
		return $this->summary;
	}

	public function isSingular() {
		return true; 
	}

	public function isAutoload() {
		return false;
	}
}
