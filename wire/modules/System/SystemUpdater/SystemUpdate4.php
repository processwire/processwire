<?php namespace ProcessWire;

/**
 * Adds a 'data' column to the fieldgroups_fields table
 *
 */
class SystemUpdate4 extends SystemUpdate {

	public function execute() {
		$this->modules->resetCache();
		$this->modules->install('AdminThemeDefault'); 
		$this->message("Added new default admin theme. To configure or remove this theme, see Modules > Core > Admin > Default Admin Theme."); 
		return true;
	}
}
