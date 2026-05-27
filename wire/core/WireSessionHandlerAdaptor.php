<?php namespace ProcessWire;

/**
 * Adaptor between WireSessionHandler modules and PHP’s SessionHandlerInterface
 * 
 * Used on PHP 8.4+ installations only. Necessary because:
 * 
 * - PHP's SessionHandlerInterface in PHP 8.5+ is not compatible with ProcessWire’s 
 *   WireSessionHandler module type primarily because of declared union return types
 *   in the SessionHandlerInterface. 
 * 
 * - There are already other WireSessionHandler modules in existance that would require
 *   changes without this adaptor.  
 * 
 * - PHP 8.4+ has deprecated setting the save handler method-by-method and instead requires
 *   a class that implements SessionHandlerInterface. So this is that class. 
 * 
 *  - This adaptor will work on any PHP 8.x version but we use it just for PHP 8.4+ currently.
 * 
 * @since 3.0.255
 * 
 */
class WireSessionHandlerAdaptor implements \SessionHandlerInterface {
	
	/**
	 * @var WireSessionHandler 
	 * 
	 */
	protected $handler;
	
	/**
	 * Construct
	 * 
	 * @param WireSessionHandler $handler
	 * 
	 */
	public function __construct(WireSessionHandler $handler) {
		$this->handler = $handler;
	}
	
	/**
	 * Closes the current session. 
	 * 
	 * This function is automatically executed when closing the session, 
	 * or explicitly via `session_write_close()`.
	 * 
	 * @return bool
	 * 
	 */
	public function close(): bool {
		return (bool) $this->handler->close();
	}
	
	/**
	 * Destroys a session. 
	 * 
	 * Called by `session_regenerate_id()` (with `$destroy = true`), 
	 * `session_destroy()` and when `session_decode()` fails.
	 * 
	 * @param string $id
	 * @return bool
	 * 
	 */
	public function destroy(string $id): bool {
		return (bool) $this->handler->destroy($id);
	}
	
	/**
	 * Cleans up expired sessions. 
	 * 
	 * Called by `session_start()`, based on `session.gc_divisor`, 
	 * `session.gc_probability` and `session.gc_maxlifetime` settings.
	 * 
	 * @param int $max_lifetime
	 * @return int|false
	 * 
	 */
	public function gc(int $max_lifetime): int|false {
		$v = $this->handler->gc($max_lifetime);
		return $v === false ? false : (int) $v; 
	}
	
	/**
	 * Re-initialize existing session, or creates a new one. 
	 * 
	 * Called when a session starts or when `session_start()` is invoked.
	 * 
	 * @param string $path
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function open(string $path, string $name): bool {
		wire()->message("open: $name"); 
		return (bool) $this->handler->open($path, $name); 
	}
	
	/**
	 * Reads the session data from the session storage, and returns the results. 
	 * 
	 * Called right after the session starts or when `session_start()` is called. 
	 * 
	 * @param string $id
	 * @return string|false
	 * 
	 */
	public function read(string $id): string|false {
		$data = $this->handler->read($id); 
		return $data === false ? false : (string) $data;
	}
	
	/**
	 * Writes the session data to the session storage. 
	 * 
	 * Called by `session_write_close()`, when `session_register_shutdown()` fails, 
	 * or during a normal shutdown. `close()` is called right after this function.
	 * 
	 * @param string $id
	 * @param string $data
	 * @return bool
	 * 
	 */
	public function write(string $id, string $data): bool {
		return (bool) $this->handler->write($id, $data);
	}
}
