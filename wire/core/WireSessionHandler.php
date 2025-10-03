<?php namespace ProcessWire;

/**
 * ProcessWire Session Handler 
 *
 * This is an abstract class for a session handler module to extend from.
 * It provides the interface and some basic functions. For an example, see:
 * /wire/modules/Session/SessionHandlerDB/SessionHandlerDB.module
 * 
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 */

abstract class WireSessionHandler extends WireData implements Module {

	/**
	 * Initialize the save handler when $modules sets the current instance
	 *
	 */
	public function wired() {
		if(!$this->sessionExists()) {
			$this->addHookBefore('Session::init', $this, 'hookSessionInit');
			register_shutdown_function('session_write_close');
		}
	}

	/**
	 * Initailize, called when module configuration has been populated
	 *
	 */
	public function init() { }

	/**
	 * Hook before Session::init
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookSessionInit(HookEvent $event) {
		$session = $event->object; /** @var Session $session */
		$this->attach();
		$session->sessionHandler($this);
	}

	/**
	 * Attach this as the session handler
	 *
	 */
	public function attach() {
		if(version_compare(PHP_VERSION, '8.4.0') >= 0) {
			session_set_save_handler($this);
		} else {
			session_set_save_handler(
				array($this, 'open'),
				array($this, 'close'),
				array($this, 'read'),
				array($this, 'write'),
				array($this, 'destroy'),
				array($this, 'gc')
			);
		}
	}

	/**
	 * Open the session
	 *
	 * @param string $path Save path
	 * @param string $name Name of session
	 * @return bool True on success, false on failure
	 *
	 */
	public function open($path, $name) {
		if($name && $path) {} // ignore
		return true; 
	}

	/**
	 * Close the session
	 *
	 * @return bool True on success, false on failure
	 *
	 */
	public function close() {
		return true; 
	}
	
	/**
	 * Read and return data for session indicated by $id
	 *
	 * @param string $id Session ID
	 * @return string Serialized data or blank string if none
	 *
	 */
	abstract public function read($id);

	/**
	 * Write the given $data for the given session ID
	 *
	 * @param string $id Session ID
	 * @param string Serialized data to write
	 *
	 */
	abstract public function write($id, $data); 

	/**
	 * Destroy the session indicated by the given session ID
	 *
	 * @param string $id Session ID
	 * @return bool True on success, false on failure 
	 *
	 */
	abstract public function destroy($id);

	/**
	 * Garbage collection: remove stale sessions
	 *
	 * @param int $seconds Max lifetime of a session
	 * @return bool True on success, false on failure
	 *
	 */
	abstract public function gc($seconds);

	/**
	 * Does a session currently exist? (i.e. already one started)
	 * 
	 * @return bool
	 * @since 3.0.158
	 * 
	 */
	public function sessionExists() {
		if(function_exists("\\session_status")) return session_status() === PHP_SESSION_ACTIVE;
		return session_id() !== '';
	}

	/**
	 * Tells the Modules API to only instantiate one instance of this module
	 *
	 */
	public function isSingular() {
		return true;
	}

	/**
	 * Tells the Modules API to automatically load this module at boot
	 *
	 */
	public function isAutoload() {
		return true; 
	}
	

}
