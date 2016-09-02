<?php namespace ProcessWire;

/**
 * Adds a 'data' column to the fieldgroups_fields table
 *
 */
class SystemUpdate1 extends SystemUpdate {

	public function execute() {
		$result = $this->db->query("SHOW COLUMNS FROM fieldgroups_fields WHERE field='data'");
		if(!$result->num_rows) { 
			$this->db->query("ALTER TABLE fieldgroups_fields ADD data TEXT"); 
			$this->message("Added field template context support"); 
		}
		return true;
	}
}
