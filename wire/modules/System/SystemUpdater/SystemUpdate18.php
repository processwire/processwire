<?php namespace ProcessWire;

/**
 * Loads file/image fields to ensure their schema is up-to-date and avoid an error message
 * 
 *
 */
class SystemUpdate18 extends SystemUpdate {
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	public function executeAtReady() {
		foreach($this->wire()->fields as $field) {
			if($field->type instanceof FieldtypeFile) {
				try {
					$field->type->getDatabaseSchema($field);
				} catch(\Exception $e) { }
			}
		}
		$this->updater->saveSystemVersion(18);
	}
}

