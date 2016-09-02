<?php namespace ProcessWire;

/**
 * Force modules refresh for moved modules
 *
 */
class SystemUpdate15 extends SystemUpdate {
	public function execute() {
		$this->wire('modules')->refresh();
		return true; 
	}
}

