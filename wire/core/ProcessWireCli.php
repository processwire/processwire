<?php namespace ProcessWire;

/**
 * ProcessWireCli
 * 
 * #pw-summary ProcessWire command line module handler
 * 
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * @method array ready($name, $args)
 * @method string renderHelp($cliName = '', array $args = [])
 * 
 * @see CliModule See the `CliModule` interface in wire/core/CliModule.php
 * @since 3.0.259
 *
 */
class ProcessWireCli extends Wire {
	
	/**
	 * Requested command name
	 * 
	 * ```
	 * php index.php command-name 
	 * ```
	 * 
	 * @var string 
	 * 
	 */
	protected $commandName = '';
	
	/**
	 * Arguments after command
	 * 
	 * ```
	 * php index.php command-name argument-one argument-two
	 * ```
	 *
	 * @var array
	 *
	 */
	protected $args = [];
	
	/**
	 * Construct
	 * 
	 * @param ProcessWire $wire
	 * 
	 */
	public function __construct(ProcessWire $wire) {
		$wire->wire($this);
		parent::__construct();
		if(empty($_SERVER['argv']) || php_sapi_name() !== 'cli') return;
		$argv = $_SERVER['argv'];
		$this->commandName = $argv[1] ?? 'help';
		$this->commandName = $this->wire()->sanitizer->name($this->commandName);
		$this->args = array_values(array_slice($argv, 2));
		$this->ready($this->commandName, $this->args);
	}
	
	/**
	 * Command line interface ready
	 *
	 * @param string $name Requested module name or cli=[name] module info key
	 * @param array $args Arguments passed to command
	 * @return array Names of cli module(s) executed 
	 *
	 */
	public function ___ready($name, array $args) {
		$executed = [];
		
		if($name === 'help') {
			$cliName = reset($args); 
			$args = array_slice($args, 1);
			echo $this->renderHelp($cliName, $args);
			return [];
		}
		
		$cliModules = $this->getCliModules($name);
		
		foreach($cliModules as $mod) {
			if(!wireInstanceOf($mod, 'CliModule') && !method_exists($mod, 'executeCli')) {
				// module likely has its own CLI handler from a ProcessWire::ready hook
				continue;
			}
			try {
				$mod->executeCli($args);
				echo "\n";
				$executed[] = $mod->className();
				
			} catch(\Exception $e) {
				$this->trackException($e);
				$cls = wireClassName($e); 
				$msg = $e->getMessage() . ' (' . $e->getFile() . ":" . $e->getLine() . ')';
				echo "\n$cls: $name: $msg\n";
			}
		}
		
		return $executed;
	}
	
	/**
	 * Get CLI modules matching requested name
	 * 
	 * @param string $name Requested command name or module name
	 * @return array|CliModule[]
	 * 
	 */
	protected function getCliModules($name) {
		
		$modules = $this->wire()->modules;
		
		$cliModules = $modules->findByFlag(Modules::flagsCli);
		foreach($cliModules as $key => $cliModule) {
			$info = $modules->getModuleInfo($cliModule);
			if(!empty($info['cli']) && $info['cli'] === $name) {
				$cliModules[$key] = $modules->getModule($cliModule);
			} else {
				unset($cliModules[$key]);
			}
		}
		
		if(!count($cliModules)) {
			// case where $name is the actual module name
			$cliModule = $modules->getModule($name);
			if($cliModule) $cliModules = [$cliModule];
		}
		
		return $cliModules;
	}
	
	/**
	 * Show available CLI commands and usage
	 *
	 * @param string $cliName "cli" name of module requesting help for or empty (or 'all') for all modules
	 * @param array $args Arguments passed to command line in this request
	 * @return string 
	 *
	 */
	public function ___renderHelp($cliName = '', array $args = []) {
	
		$modules = $this->wire()->modules;
		$out = $this->renderProcessWire();
		$commandItems = [];
		$moduleInfos = [];
		$max = 0; // max command line item length
	
		// find modules with Cli flag and place their commands into $commandItems array
		foreach($modules->findByFlag(Modules::flagsCli, false) as $moduleName) {
			
			$info = $modules->getModuleInfo($moduleName);
			if($cliName && empty($info['cli'])) continue;
			if($cliName && $info['cli'] !== $cliName) continue;
			$commands = [];
			
			if(wireInstanceOf($moduleName, 'CliModule') || wireMethodExists($moduleName, 'getCliCommands')) {
				$module = $modules->getModule($moduleName, ['noInit' => true, 'noCache' => true]);
				/** @var CliModule $module */
				if($module && method_exists($module, 'getCliCommands')) {
					$commands = $module->getCliCommands();
				}
			}
			
			$items = null;
			
			if(is_array($commands)) {
				// commands array: [ 'command syntax' => 'description ] or [ 0 => 'command syntax' ]
				foreach($commands as $cmd => $label) {
					if(is_int($cmd)) [$cmd, $label] = [$label, ''];
					$line = strpos($cmd, 'index.php') === false ? "php index.php $info[cli] $cmd " : "$cmd ";
					$items[$line] = $label;
					$len = strlen($line);
					if($len > $max) $max = $len+1;
				}
				
			} else if(is_string($commands)) {
				// commands is free-form string where developers uses their own format
				$items = $commands;
			}
			
			if($items !== null) {
				$commandItems[$moduleName] = $items;
				$moduleInfos[$moduleName] = $info;
			}
		}
	
		foreach($commandItems as $moduleName => $commands) {
			
			$info = $moduleInfos[$moduleName];
			$header = ($moduleName ? "$moduleName: " : "") . "$info[title]"; // module title header
			$sep = str_repeat('=', strlen($header)); // line under title header
			$newline = "\n  ";
			$out .= "$newline$header$newline$sep";
			
			if(is_string($commands)) {
				$out .= $newline . str_replace("\n", $newline, $commands);
			} else {
				foreach($commands as $line => $label) {
					if($label) $line .= str_repeat(' ', $max - strlen($line)) . $label;
					$out .= "$newline$line";
				}
			}
		
			$out .= "\n";
		}
		
		return "$out\n";
	}
	
	/**
	 * Render ProcessWire version header
	 * 
	 * @return string
	 * 
	 */
	public function renderProcessWire() {
		$version = $this->wire()->config->versionName;
		return "\n" .
			"    ____                                _       ___          \n" .
			"   / __ \_________  ________  _________| |     / (_)_______  \n" .
			"  / /_/ / ___/ __ \/ ___/ _ \/ ___/ ___/ | /| / / / ___/ _ \ \n" .
			" / ____/ /  / /_/ / /__/  __(__  |__  )| |/ |/ / / /  /  __/ \n" .
			"/_/   /_/   \____/\___/\___/____/____/ |__/|__/_/_/   \___/ $version \n";
	}
	
	/**
	 * Requested module name or requested `cli=[name]` module info key
	 *
	 * @return string
	 *
	 */
	public function getCommandName() {
		return $this->commandName;
	}
	
	/**
	 * Additional arguments passed to command
	 *
	 * @return array Regular PHP array (non-associative)
	 *
	 */
	public function getArgs() {
		return $this->args;
	}
	
}


