<?php namespace ProcessWire;

/**
 * ProcessWire ProcessController
 *
 * Loads and executes Process Module instance and determines access.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Exception thrown when a requested Process or Process method is requested that doesn't exist
 *
 */
class ProcessController404Exception extends Wire404Exception { }

/**
 * Exception thrown when the user doesn't have access to execute the requested Process
 *
 */
class ProcessControllerPermissionException extends WirePermissionException { } 

/**
 * A Controller for Process* Modules
 *
 * Intended to be used by templates that call upon Process objects
 * 
 * @method string execute()
 *
 */
class ProcessController extends Wire {

	/**
	 * The default method called upon when no method is specified in the request
	 *
	 */
	 const defaultProcessMethodName = 'execute';

	/**
	 * The Process instance to execute
	 * 
	 * @var Process
	 *
	 */
	protected $process; 

	/**
	 * The name of the Process to execute (string)
	 * 
	 * @var string
	 *
	 */
	protected $processName; 

	/**
	 * The name of the method to execute in this process
	 * 
	 * @var string
	 *
	 */ 
	protected $processMethodName; 

	/**
	 * The prefix to apply to the Process name
	 *
	 * All related Processes would use the same prefix, i.e. "Admin"
	 * 
	 * @var string
	 *
	 */
	protected $prefix; 

	/**
	 * Construct the ProcessController
	 *
	 */
	public function __construct() {
		$this->prefix = 'Process';
		$this->processMethodName = ''; // blank indicates default/index method
	}

	/**
	 * Set the Process to execute. 
	 * 
	 * @param Process $process
	 *
	 */
	public function setProcess(Process $process) {
		$this->process = $process; 
	}

	/**
	 * Set the name of the Process to execute. 
	 *
	 * No need to call this unless you want to override the one auto-determined from the URL.
	 *
	 * If overridden, then make sure the name includes the prefix, and don't bother calling the setPrefix() method. 
	 * 
	 * @param string $processName
	 *
	 */
	public function setProcessName($processName) {
		$this->processName = $this->sanitizer->name($processName); 
	}

	/**
	 * Set the name of the method to execute in the Process
	 *
	 * It is only necessary to call this if you want to override the default behavior. 
	 * The default behavior is to execute a method called "execute()" OR "executeSegment()" where "Segment" is 
	 * the last URL segment in the request URL. 
	 * 
	 * @param string $processMethod
	 *
	 */
	public function setProcessMethodName($processMethod) {
		$this->processMethodName = $this->sanitizer->name($processMethod); 
	}

	/**
	 * Set the class name prefix used by all related Processes
	 *
	 * This is prepended to the class name determined from the URL. 
	 * For example, if the URL indicates a process name is "PageEdit", then we would need a prefix of "Admin" 
	 * to fully resolve the class name. 
	 * 
	 * @param string $prefix
	 *
	 */
	public function setPrefix($prefix) {
		$this->prefix = $this->sanitizer->name($prefix); 
	}

	/**
	 * Determine and return the Process to execute
	 * 
	 * @return Process
	 *
	 */
	public function getProcess() {

		if($this->process) $processName = $this->process->className();
			else if($this->processName) $processName = $this->processName; 
			else return null; 

		// verify that there is adequate permission to execute the Process
		$permissionName = '';
		$info = $this->wire('modules')->getModuleInfo($processName, array('verbose' => false)); 
		if(!empty($info['permission'])) $permissionName = $info['permission']; 

		$this->hasPermission($permissionName, true); // throws exception if no permission
		if(!$this->process) {
			$this->process = $this->modules->getModule($processName);
		}

		// set a proces fuel, primarily so that certain Processes can determine if they are the root Process 
		// example: PageList when in PageEdit
		$this->wire('process', $this->process);
	
		return $this->process; 
	}

	/**
	 * Does the current user have permission to execute the given process name?
	 *
	 * Note: an empty permission name is accessible only by the superuser
	 * 
	 * @todo: This may now be completely unnecessary since permission checking is built into Modules.php
	 *
	 * @param string $permissionName
	 * @param bool $throw Whether to throw an Exception if the user does not have permission
	 * @return bool
	 * @throws ProcessControllerPermissionException
	 *
	 */
	protected function hasPermission($permissionName, $throw = true) {
		$user = $this->wire('user'); 
		if($user->isSuperuser()) return true; 
		if($permissionName && $user->hasPermission($permissionName)) return true; 
		if($throw) throw new ProcessControllerPermissionException("You don't have $permissionName permission"); 
		return false; 
	}

	/**
	 * Get the name of the method to execute with the Process
	 * 
	 * @param Process @process
	 * @return string
	 *
	 */
	public function getProcessMethodName(Process $process) {

		$method = $this->processMethodName;

		if(!$method) {
			$method = self::defaultProcessMethodName; 
			// urlSegment as given by ProcessPageView 
			$urlSegment1 = $this->input->urlSegment1; 
			if($urlSegment1 && !$this->user->isGuest()) {
				if(strpos($urlSegment1, '-')) {
					// urlSegment1 has multiple hyphenated parts: convert hello-world to HelloWorld
					foreach(explode('-', $urlSegment1) as $v) $method .= ucfirst($v);
				} else {
					// just one part
					$method .= ucfirst($urlSegment1);
				}
			}
		}
		
		if($method === 'executed') return '';

		$hookedMethod = "___$method";

		if(method_exists($process, $method) 
			|| method_exists($process, $hookedMethod) 
			|| $process->hasHook($method . '()')) {
			return $method;
		} else {
			return '';
		}
	}

	/**
	 * Execute the process and return the resulting content generated by the process
	 * 
	 * @return string
	 * @throws ProcessController404Exception
	 *	
	 */
	public function ___execute() {

		$content = '';
		$method = '';
		$debug = $this->wire('config')->debug; 
		$breadcrumbs = $this->wire('breadcrumbs'); 
		$headline = $this->wire('processHeadline'); 
		$numBreadcrumbs = $breadcrumbs ? count($breadcrumbs) : null;
		if($process = $this->getProcess()) { 
			if($method = $this->getProcessMethodName($this->process)) {
				$className = $this->process->className();
				if($debug) Debug::timer("$className.$method()"); 
				$content = $this->process->$method();
				if($debug) Debug::saveTimer("$className.$method()"); 
				if($method != 'execute') {
					// some method other than the main one
					if(!is_null($numBreadcrumbs) && $numBreadcrumbs === count($breadcrumbs)) {
						// process added no breadcrumbs, but there should be more
						if($headline === $this->wire('processHeadline')) $process->headline(str_replace('execute', '', $method)); 
						$moduleInfo = $this->wire('modules')->getModuleInfo($process);
						$href = substr($this->wire('input')->url(), -1) == '/' ? '../' : './';
						$process->breadcrumb($href, $moduleInfo['title']); 
					}
				}
				$this->process->executed($method);
			} else {
				throw new ProcessController404Exception("Unrecognized path");
			}

		} else {
			throw new ProcessController404Exception("The requested process does not exist");
		}
	
		if(empty($content) || is_bool($content)) {
			$content = $this->process->getViewVars();
		}
		if(is_array($content)) {
			// array of returned content indicates variables to send to a view
			if(count($content)) {
				$viewFile = $this->getViewFile($this->process, $method); 
				if($viewFile) {
					// get output from a separate view file
					$template = $this->wire(new TemplateFile($viewFile));	
					foreach($content as $key => $value) {
						$template->set($key, $value);
					}
					$content = $template->render();
				}
			} else {
				$content = '';
			}
		}

		return $content; 
	}

	/**
	 * Given a process and method name, return the first matching valid view file for it
	 * 
	 * @param Process $process
	 * @param string $method If omitted, 'execute' is assumed
	 * @return string
	 * 
	 */
	protected function getViewFile(Process $process, $method = '') {
		
		$viewFile = $process->getViewFile();
		if($viewFile) return $viewFile;
	
		if(empty($method)) $method = 'execute';
		$className = $process->className();
		$viewPath = $this->wire('config')->paths->$className;
		$method2 = ''; // lowercase hyphenated version
		$method3 = ''; // lowercase hyphenated, without leading execute
		if(strtolower($method) != $method) {
			// lowercase hyphenated version
			$method2 = trim(strtolower(preg_replace('/([A-Z]+)/', '-$1', $method)), '-');
			// without a leading 'execute-' or 'execute'
			$method3 = str_replace(array('execute-', 'execute'), '', $method2);
		}
		
		if(is_dir($viewPath . 'views')) {
			// check in a /ModuleName/views/ directory for one of the following:
			// views/execute.php (only if method name is 'execute')
			// views/executeSomeMethod.php
			// views/execute-some-method.php
			// views/some-method.php (preferable)
			$_viewPath = $viewPath;
			$viewPath .= 'views/';
			$viewFile = $viewPath . $method . '.php'; // i.e. views/execute.php or views/executeSomething.php
			if(is_file($viewFile)) return $viewFile;
			if($method2) {
				// convert executeSomething to execute-something or thisThat to this-that
				$viewFile = $viewPath . $method2 . '.php'; // i.e. execute-something.php
				if(is_file($viewFile)) return $viewFile;
			}
			if($method != 'execute' && $method3) {
				$viewFile = $viewPath . $method3 . '.php'; // i.e. something.php or some-method.php
				if(is_file($viewFile)) return $viewFile;
			}
			$viewPath = $_viewPath; // restore, since didn't find it in /views/ 
		} 
	
		// look for view file in same dir as module
		if($method == 'execute') {
			$viewFiles = array(
				"$className.view.php", // ModuleName.view.php
				"$className-execute.view.php", // alt1: ModuleName-execute.view.php
				"execute.view.php", // alt2: just execute.view.php (no ModuleName)
			);
		} else {
			$viewFiles = array(
				"$className-$method.view.php", // ModuleName.executeSomething.view.php
				"$method.view.php", // executeSomething.view.php
			);
			if($method2) {
				$viewFiles[] = "$className-$method2.view.php"; // ModuleName-execute-something.view.php
				$viewFiles[] = "$method2.view.php"; // execute-something.view.php
			}
			if($method3) {
				$viewFiles[] = "$className-$method3.view.php"; // ModuleName-something.view.php
				$viewFiles[] = "$method3.view.php"; // something.view.php
			}
		}

		// now determine which of the possible view files actually exists
		$viewFile = '';
		foreach($viewFiles as $file) {
			if(is_file($viewPath . $file)) {
				$viewFile = $viewPath . $file;
				break;
			}
		}
		
		return $viewFile;
	}

	/**
	 * Generate a message in JSON format, for use with AJAX output
	 * 
	 * @param string $msg
	 * @param bool $error
	 * @return string JSON encoded string
	 *
	 */
	public function jsonMessage($msg, $error = false) {
		return json_encode(array(
			'error' => $error, 
			'message' => $msg
		)); 
	}

	/**
	 * Is this an AJAX request?
	 *
	 * @return bool
	 * 
	 */
	public function isAjax() {
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
	}

}	



