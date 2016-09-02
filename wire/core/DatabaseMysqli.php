<?php namespace ProcessWire;
/**
 * Temporary wrapper to mysqli Database class for mysqli => PDO transition
 * 
 * This is for temporary use while transitioning from mysqli to PDO
 * 
 * It's entire purpose is to ensure that a $db API variable is available, while not 
 * actually instantiating mysqli (and forming a mysql connection) until the $db
 * variable is called upon to do something.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 */

class DatabaseMysqli {

	protected $config = null;	
	protected $db = null;
	protected $callers = array();
	
	public function __construct(Config $config) {
		$this->config = $config;
	}

	protected function db($key, $type) {
		if(is_null($this->db)) $this->db = new Database($this->config);
		if($this->config->debug) $this->recordCaller($key, $type);
		return $this->db;
	}
	
	public function __get($key) {
		return $this->db($key, 'property')->$key;
	}
	
	public function __call($method, $arguments) {
		$db = $this->db($method, 'method');
		return call_user_func_array(array($db, $method), $arguments);
	}
	
	public function isInstantiated() {
		return $this->db !== null;
	}
	
	public function getCallers() {
		return $this->callers;
	}

	protected function recordCaller($key, $type) {
		$caller = 'unknown';
		if(defined('DEBUG_BACKTRACE_IGNORE_ARGS')) $traces = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			else $traces = @debug_backtrace();
		if(isset($traces[1]) && isset($traces[1]['file']) && $traces[1]['file'] != __FILE__) $caller = $traces[1]['file'];
			else if(isset($traces[2]) && isset($traces[2]['file']) && $traces[2]['file'] != __FILE__) $caller = $traces[2]['file'];
		$this->callers[] = "$caller ($type: $key)";
	}
}
