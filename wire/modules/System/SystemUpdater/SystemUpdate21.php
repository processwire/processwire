<?php namespace ProcessWire;

/**
 * Force modules refresh and install FileValidatorZip
 *
 */
class SystemUpdate21 extends SystemUpdateAtReady {
	public function update() {
		$modules = $this->wire()->modules;
		if(!$modules->isInstalled('FileValidatorZip')) {
			$modules->refresh();
			if($modules->install('FileValidatorZip')) {
				$this->message('Installed FileValidatorZip module');
			} else {
				$this->warning('Failed to install FileValidatorZip module');
				return false;
			}
		}
		return true;
	}
}
