<?php namespace ProcessWire;

/**
 * Loads file/image fields to ensure their schema is up-to-date and avoid an error message
 * 
 *
 */
class SystemUpdate18 extends SystemUpdateAtReady {
	public function update() {
		foreach($this->wire()->fields as $field) {
			if($field->type instanceof FieldtypeFile) {
				try {
					$field->type->getDatabaseSchema($field);
				} catch(\Exception $e) { }
			}
		}
		return true;
	}
}
