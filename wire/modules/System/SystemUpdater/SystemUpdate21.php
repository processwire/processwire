<?php namespace ProcessWire;

/**
 * Force modules refresh and install FileValidatorZip
 *
 */
class SystemUpdate21 extends SystemUpdate {
	public function execute() {
		$modules = $this->wire()->modules;
		$modules->refresh();
		if(!$modules->isInstalled('FileValidatorZip')) {
			if($modules->install('FileValidatorZip')) {
				$this->message('Installed FileValidatorZip module');
			} else {
				return false;
			}
		}
		return true;
	}
}
