<?php namespace ProcessWire;

/**
 * ProcessWire Session Handler 
 *
 * This is an abstract class for a session handler module to extend from.
 * It provides the interface and some basic functions. For an example, see:
 * /wire/modules/Session/SessionHandlerDB/SessionHandlerDB.module
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

abstract class WireSessionHandler extends WireData implements Module {

	/**
	 * Initialize the save handler
	 *
	 */
	public function __construct() {
		$this->addHookBefore('Session::init', $this, 'attach'); 
		register_shutdown_function('session_write_close'); 
	}

	/**
	 * Initailize the module, may not be needed here but required for module interface
	 *
	 */
	public function init() { }

	/**
	 * Attach this as the session handler
	 *
	 */
	public function attach() {
		session_set_save_handler(	
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
			);
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
