<?php namespace ProcessWire;

class ModulesCli extends ModulesClass {
	
	/**
	 * Execute given CLI command
	 *
	 * @param array $args Command line arguments passed, excluding module/cli name
	 * @return void
	 *
	 */
	public function executeCli(array $args) {
		if(empty($args)) {
			echo 'No command specified';
			return;
		}
		
		$sanitizer = $this->wire()->sanitizer;
		$command = $args[0];
		
		if($command === 'list' || $command === 'xlist') {
			$a = $command === 'list' ? $this->modules->getArray() : $this->modules->getInstallable();
			ksort($a);
			foreach($a as $item) {
				if($item instanceof Module) {
					$name = 
						$this->modules->getModuleInfoProperty($item, 'versionStr') . " " . 
						$item->className();
				} else {
					$name = basename(basename($item, '.module'), '.php');
				}
				echo "- $name\n";
			}
			return;
		} else if($command === 'refresh') {
			$this->modules->refresh(true);
			echo $this->wire()->notices->renderText();
			return;
		}
		
		if(empty($args[1])) {
			echo "Missing required module [name]";
			return;
		}
		
		$name = $args[1];
		
		switch(strtolower($command)) {
			case 'install':
				echo "Install: $name ";
				echo $this->modules->install($name) ? "(Success)" : "(Fail)";
				break;
			case 'uninstall':
				echo "Uninstall: $name ";
				echo $this->modules->uninstall($name) ? "(Success)" : "(Fail)";
				break;
			case 'getconfig':	
				$property = $args[2] ?? '';
				echo $sanitizer->json($this->modules->getConfig($name, $property));
				break;
			case 'ismodule':
				echo $this->modules->isModule($name) ? "Yes" : "No";
				break;
			case 'isinstalled':
				echo $this->modules->isInstalled($name) ? "Yes" : "No";
				break;
			case 'getmoduleinfo':
				echo $sanitizer->json($this->modules->getModuleInfo($name));
				break;
			case 'getmoduleinfoverbose':
				echo $sanitizer->json($this->modules->getModuleInfoVerbose($name));
				break;
			default:
				echo "Unknown command: $command";
		}
	}
	
	/**
	 * Get array of allowed commands
	 *
	 * @return array
	 *
	 */
	public function getCliCommands() {
		return [
			'list' => 'List all installed modules in the system',
			'xlist' => 'List all uninstalled modules in the system',
			'install [name]' => 'Install module [name]',
			'uninstall [name]' => 'Uninstall module [name]',
			'isModule [name]' => 'Does given class name resolve to a module?', 
			'isInstalled [name]' => 'Return yes/no if module [name] is installed',
			'getConfig [name]' => 'Return configuration data for module [name]',
			'getConfig [name] [property]' => 'Return value for module [name] config [property]',
			'getModuleInfo [name]' => 'Print array of of info for module [name]',
			'getModuleInfoVerbose [name]' => 'Print array of of verbose info for module [name]',
		];
	}
	
}
