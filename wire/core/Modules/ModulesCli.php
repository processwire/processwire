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
		/** @var Modules $modules */
		$modules = $this->modules;

		$aliases = [
			'getmoduleinfo' => 'info',
			'getmoduleinfoverbose' => 'info',
			'getconfig' => 'config',
			'xlist' => 'unlist',
			'ismodule' => 'exists',
			'isinstalled' => 'installed',
			'directory' => 'dir',
			'remote' => 'dir',
			'lookup' => 'dir',
			'upgrades' => 'updates',
			'upgradeable' => 'updates',
			'upgrade' => 'update',
			'erase' => 'delete',
		];

		if(empty($args)) {
			echo 'No command specified';
			return;
		}

		$sanitizer = $this->wire()->sanitizer;
		$command = strtolower($args[0]);
		$getJson = in_array('--json', $args);

		if(isset($aliases[$command])) $command = $aliases[$command];

		if($command === 'list' || $command === 'unlist') {
			$a = $command === 'list' ? $modules->getArray() : $modules->getInstallable();
			$listType = isset($args[1]) ? strtolower($args[1]) : '';
			if($listType !== 'core' && $listType !== 'site') $listType = '';
			ksort($a);
			$list = [];
			foreach($a as $item) {
				if($item instanceof Module) {
					$version = $getJson ? $modules->getModuleInfoProperty($item, 'versionStr') : '';
					$name = $item->className();
				} else {
					$name = basename(basename($item, '.module'), '.php');
					$version = '?';
				}
				if($listType) {
					$isCore = $modules->getModuleInfoProperty($name, 'core');
					if($listType === 'core' && !$isCore) continue;
					if($listType === 'site' && $isCore) continue; 
				}
				if($getJson) {
					$list[] = [ 'name' => $name, 'version' => $version ];
				} else {
					$list[] = $name;
				}
			}
			echo $getJson ? $sanitizer->json($list) : implode("\n", $list);
			return;
		} else if($command === 'refresh') {
			$modules->refresh(true);
			echo $this->wire()->notices->renderText();
			return;
		} else if($command === 'updates') {
			$name = $this->getNameArg($args);
			echo $this->updates($name, $getJson);
			return;
		}

		$name = $this->getNameArg($args);
		if(empty($name)) {
			echo "Missing required module <name>";
			return;
		}

		switch($command) {
			case 'install':
				echo $this->installOrUninstall($name, true, $getJson);
				break;
			case 'uninstall':
				echo $this->installOrUninstall($name, false, $getJson);
				break;
			case 'config':
				$property = $args[2] ?? '';
				$v = $modules->getConfig($name, $property);
				if($getJson) $v = [ 'module' => $name, 'property' => $property, 'value' => $v ];
				echo $sanitizer->json($v);
				break;
			case 'exists':
				$v = $modules->isModule($name) ? 'Yes' : 'No';
				if($getJson) $v = $sanitizer->json(['module' => $name, 'exists' => $v === 'Yes']);
				echo $v;
				break;
			case 'installed':
				$v = $modules->isInstalled($name) ? "Yes" : "No";
				if($getJson) $v = $sanitizer->json(['module' => $name, 'installed' => $v === 'Yes']);
				echo $v;
				break;
			case 'info':
				$property = $args[2] ?? '';
				if($property) {
					$v = $modules->getModuleInfoProperty($name, $property);
					if($getJson) $v = [ 'module' => $name, 'property' => $property, 'value' => $v ];
				} else {
					$v = $modules->getModuleInfoVerbose($name);
				}
				echo $sanitizer->json($v);
				break;
			case 'dir':
				echo $this->dir($name, $getJson);
				break;
			case 'download':
				$install = in_array('--install', $args);
				echo $this->download($name, $install, $getJson);
				break;
			case 'update':
				$force = in_array('--force', $args);
				echo $this->update($name, $force, $getJson);
				break;
			case 'applyup':
				$users = $this->wire()->users;
				$u = $users->get($this->wire()->config->superUserPageID); 
				$users->setCurrentUser($u);
				$this->modules->getModule($name, [ 'configOnly' => true ]);
				echo $this->wire()->notices->renderText();
				break;
			case 'delete':
				$this->deleteModule($name, $getJson);
				break;
		default:
			if($command) $this->fail("Unknown modules command: $command", $getJson);
		}
	}

	protected function deleteModule($name, $getJson) {
		$modules = $this->modules;
		$deleteable = $modules->installer()->isDeleteable($name, true);
		if(is_string($deleteable) && strlen($deleteable) > 0) {
			$this->fail($deleteable, $getJson);
		}

		if(!$deleteable) {
			$this->fail("Module is not deleteable: $name", $getJson);
		}

		$fail = false;
		$a = [];
		if($modules->installer()->delete($name)) {
			$a[] = "Module deleted from file system: $name";
		} else {
			$a[] = "Unable to delete module from file system: $name";
		}

		$noticesText = $this->wire()->notices->renderText();
		$a = array_merge($a, explode("\n", $noticesText));

		if($getJson) {
			echo $this->wire()->sanitizer->json($a);
		} else {
			echo implode("\n", $a);
		}

		if($fail) exit(1);
	}

	/**
	 * Get first non-flag argument after command name
	 *
	 * @param array $args
	 * @return string
	 *
	 */
	protected function getNameArg(array $args) {
		foreach($args as $n => $arg) {
			if(!$n) continue;
			if(strpos($arg, '--') === 0) continue;
			return $arg;
		}
		return '';
	}

	/**
	 * Install or uninstall module
	 *
	 * @param string $name
	 * @param bool $install
	 * @param bool $getJson
	 * @return string
	 *
	 */
	protected function installOrUninstall($name, $install, $getJson) {

		if($this->modules->isInstalled($name)) {
			// module is installed
			if($install) {
				// attempted install
				$message = "Already installed";
				$result = true;
			} else {
				// uninstall
				$result = $this->modules->uninstall($name);
				$message = $result ? 'Success' : 'Fail';
			}
		} else {
			// module is NOT installed
			if($install) {
				// install module
				$result = $this->modules->install($name);
				$message = $result ? 'Success' : 'Fail';
			} else {
				// attempted uninstall module
				$message = "Not installed";
				$result = true;
			}
		}

		if($getJson) {
			$result = [
				'action' => ($install ? 'install' : 'uninstall'),
				'module' => $name,
				'success' => (bool) $result,
				'message' => $message
			];
			$result = $this->wire()->sanitizer->json($result);
		} else if($install) {
			$result = "Install: $name ($message)";
		} else {
			$result = "Uninstall: $name ($message)";
		}

		return $result;
	}

	/**
	 * Download module
	 *
	 * @param string $name
	 * @param bool $install
	 * @param bool $getJson
	 * @return string
	 *
	 */
	protected function download($name, $install, $getJson) {
		$result = $this->modules->downloader()->download($name, array(
			'install' => $install
		));

		if($getJson) {
			return $this->wire()->sanitizer->json($result);
		}

		if(count($result['errors'])) {
			return "FAIL: " . implode("\nFAIL: ", $result['errors']) . "\n";
		}

		$out = array();
		foreach($result['messages'] as $message) $out[] = $message;
		if($result['downloaded']) $out[] = "Downloaded: $result[module] => $result[destination]";
		if($install) $out[] = ($result['installed'] ? 'Installed: ' : 'Install failed: ') . $result['module'];

		return implode("\n", $out);
	}

	/**
	 * Get module directory info
	 *
	 * @param string $name
	 * @param bool $getJson
	 * @return string
	 *
	 */
	protected function dir($name, $getJson) {
		$result = $this->modules->downloader()->getModuleDirectoryInfo($name);

		if($getJson) {
			return $this->wire()->sanitizer->json($result);
		}

		if(empty($result['status']) || $result['status'] !== 'success') {
			$error = empty($result['error']) ? "Unable to retrieve module directory info for: $name" : $result['error'];
			return "FAIL: $error\n";
		}

		$out = array();
		$out[] = "Module: " . ($result['class_name'] ?? $name);
		if(!empty($result['title'])) $out[] = "Title: $result[title]";
		if(!empty($result['module_version'])) $out[] = "Version: $result[module_version]";
		if(!empty($result['summary'])) $out[] = "Summary: $result[summary]";
		if(!empty($result['download_url'])) $out[] = "Download: $result[download_url]";

		return implode("\n", $out);
	}

	/**
	 * Get module updates
	 *
	 * @param string $name Optional module name, or omit for all installed site modules
	 * @param bool $getJson
	 * @return string
	 *
	 */
	protected function updates($name, $getJson) {
		$result = $this->modules->downloader()->getModuleUpdates($name);

		if($getJson) {
			return $this->wire()->sanitizer->json($result);
		}

		if($name) {
			if(count($result['errors'])) {
				return "FAIL: " . implode("\nFAIL: ", $result['errors']) . "\n";
			}
			$answer = $result['hasUpdate'] ? 'Yes' : 'No';
			return "$answer: $result[module] ($result[localVersion] => $result[remoteVersion])";
		}

		if(!count($result)) return "No module updates available";

		$out = array();
		foreach($result as $info) {
			$note = empty($info['downloadUrl']) ? ' (no download URL)' : '';
			$out[] = "$info[module] $info[localVersion] => $info[remoteVersion]$note";
		}

		return implode("\n", $out);
	}

	/**
	 * Update module
	 *
	 * @param string $name
	 * @param bool $force
	 * @param bool $getJson
	 * @return string
	 *
	 */
	protected function update($name, $force, $getJson) {
		$result = $this->modules->downloader()->updateModule($name, array(
			'force' => $force
		));

		if($getJson) {
			return $this->wire()->sanitizer->json($result);
		}

		if(count($result['errors'])) {
			return "FAIL: " . implode("\nFAIL: ", $result['errors']) . "\n";
		}

		return implode("\n", $result['messages']);
	}

	protected function fail($error, $getJson = false) {
		if($getJson) {
			echo $this->wire()->sanitizer->json([ 'status' => 'error', 'error' => $error]);
		} else {
			echo "FAIL: $error";
		}
		echo "\n";
		exit(1);
	}

	/**
	 * Get array of allowed commands
	 *
	 * @return array
	 *
	 */
	public function getCliCommands() {
		return [
			':title' => 'Modules API', 
			':summary' => 'Management of modules',
			'list [site|core]' => 'List installed modules, optionally limited to site or core modules',
			'unlist [site|core]' => 'List uninstalled modules, optionally limited to site or core modules',
			'refresh' => 'Refresh modules list and caches (also detects new modules and versions)',
			'info <name> [property]' => 'Get JSON of all info for module or optionally info property',
			'install <name>' => 'Install module',
			'uninstall <name>' => 'Uninstall module',
			'exists <name>' => 'Does given class name resolve to a module? (Yes/No)',
			'installed <name>' => 'Is module installed? (Yes/No)',
			'config <name>' => 'Get configuration data for module as JSON',
			'config <name> <property>' => 'Get value for property in module config',
			'dir <name>' => 'Query ProcessWire modules directory for module info',
			'updates [name]' => 'List available updates for installed site modules, or check one module',
			'applyup <name>' => 'Apply module upgrade when file system version of module has changed',
			'download <name> [--install]' => 'Download module from PW modules directory (+ optionally install)',
			'download <url> [--install]' => 'Download module ZIP file from https URL (+ optionally install)',
			'update <name> [--force]' => 'Download and apply an available module update',
			'delete <name>' => 'Delete/erase uninstalled module from file system',
			':note' => [ 'Optionally append --json to any of the above commands for more verbose JSON output' ],
		];
	}

}
