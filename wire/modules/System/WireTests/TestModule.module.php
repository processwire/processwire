<?php namespace ProcessWire;

/**
 * TestModule
 * 
 * This is for the WireTests module to test the $modules API
 * and it uses this module to install and uninstall, or any
 * other tests where it would need a module. 
 * 
 * CLI usage:
 * `php index.php testy cli`         - Generic CLI test
 * `php index.php testy set FooBar`  - Set module 'testValue' to 'FooBar'
 * `php index.php testy get`         - Get value in 'testValue'
 * 
 * @property string $testValue
 * 
 */
class TestModule extends WireData implements Module, CliModule, ConfigurableModule {
	
	public static function getModuleInfo() {
		return [
			'title' => 'Test module for WireTests',
			'description' => "This module doesn't do anything and is only used for testing purposes.",
			'version' => 1,
			'cli' => 'testy',
		];
	}
	
	public function __construct() {
		parent::__construct();
		$this->set('testValue', 'Hello World'); 
	}
	
	public function executeCli(array $args) {
		$command = $args[0] ?? '';
		if($command === 'cli') {
			echo "Success: module works in CLI mode";
		} else if($command === 'set' && isset($args[1])) {
			echo "Setting testValue=" . $args[1];
			$this->wire()->modules->saveConfig($this, 'testValue', $args[1]); 
		} else if($command === 'get') {
			echo "Getting testValue=$this->testValue";
		}
	}
	
	public function getCliCommands() {
		return [
			'cli' => 'CLI mode test',
			'set' => 'Set config value',
			'get' => 'Get config value',
		];
	}
	
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$f = $inputfields->InputfieldText; 
		$f->attr('name', 'testValue');
		$f->val($this->testValue);
		$f->label = 'Test configuration value';
		$inputfields->add($f);
	}
}
