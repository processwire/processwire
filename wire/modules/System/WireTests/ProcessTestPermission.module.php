<?php namespace ProcessWire;

/**
 * Process module fixture for ProcessController permission tests
 *
 */
class ProcessTestPermission extends Process {

	public static $allow = false;
	public static $calls = 0;

	public static function getModuleInfo() {
		return array(
			'title' => 'Process permission test',
			'version' => 1,
			'permissionMethod' => 'checkPermission',
		);
	}

	public static function checkPermission(array $data) {
		self::$calls++;
		return self::$allow;
	}

	public function ___execute() {
		return 'Process permission test';
	}
}
