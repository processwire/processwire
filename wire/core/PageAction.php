<?php namespace ProcessWire;

/**
 * PageAction
 *
 * Base class for Page actions in ProcessWire
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @method bool action(Page $item)
 * @method executeMultiple(PageArray $items)
 *
 */

abstract class PageAction extends WireAction implements Module {

	/**
	 * Return array of module information
	 *
	 * @return array
	 *
	public static function getModuleInfo() {
		return array(
			'title' => 'PageAction (abstract)', 
			'summary' => 'Base class for PageActions',
			'version' => 0
			);
	}
	 */

	/**
	 * Return the string type (class name) of items that this action operates upon
	 *
	 * @return string
	 *
	 */
	public function getItemType() {
		return strlen(__NAMESPACE__) ? __NAMESPACE__ . '\\Page' : 'Page';
	}

	/**
	 * Execute the action for the given page
	 *
	 * @param Page $item Item to operate upon
	 * @return bool True if the item was successfully operated upon, false if not.
	 *
	 */
	public function execute($item) {
		return parent::execute($item);
	}

	/**
	 * Perform the action on the given item
	 *
	 * @param Page $item Page item to operate upon
	 * @return bool True if the item was successfully operated upon, false if not. 
	 *
	abstract protected function ___action($item);
	 */
}
