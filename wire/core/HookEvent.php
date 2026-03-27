<?php namespace ProcessWire;

/**
 * ProcessWire HookEvent
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 * 
 * Instances of HookEvent are passed to Hook handlers when their requested method has been called.
 * 
 * #pw-summary HookEvent is a type provided to hook functions with information about the event.
 * #pw-var $event
 * #pw-body =
 * ~~~~~~
 * // Example
 * $wire->addHookAfter('Pages::saved', function(HookEvent $event) {
 *   $page = $event->arguments(0);
 *   $event->message("You saved page $page->path"); 
 * }); 
 * ~~~~~~
 * #pw-body
 *
 * HookEvents have the following properties available: 
 *
 * @property-read Wire|WireData|WireArray|Module $object Instance of the object where the Hook event originated. 
 * @property-read string $method The name of the method that was called to generate the Hook event. 
 * @property array $arguments A numerically indexed array of the arguments sent to the above mentioned method. 
 * @property mixed $return Applicable only for 'after' or ('replace' + 'before' hooks), contains the value returned by the above mentioned method. The hook handling method may modify this return value. 
 * @property bool $replace Set to boolean true in a 'before' hook if you want to prevent execution of the original hooked function. In such a case, your hook is replacing the function entirely. Not recommended, so be careful with this.
 * @property array $options An optional array of user-specified data that gets sent to the hooked function. The hook handling method may access it from $event->data. Also includes all the default hook properties. 
 * @property-read string $id A unique identifier string that may be used with a call to `Wire::removeHook()`.
 * @property-read int $eid Hook event id, a unique number for this instance of HookEvent, @since 3.0.258.
 * @property-read string $when In an active hook, contains either the string 'before' or 'after', indicating whether it is executing before or after the hooked method. 
 * @property bool|string $cancelHooks If boolean true all remaining hooks in method call are cancelled. If 'before' or 'after' (in 3.0.258+) then only hooks of that type are cancelled. (default=false)
 *
 */
class HookEvent extends WireData {

	/**
	 * Cached argument names indexed by "className.method"
	 *
	 */
	static protected $argumentNames = array();
	
	/**
	 * Total quantity of HookEvent instances, used to assign each a unique event ID (eid)
	 * 
	 * @var int 
	 * 
	 */
	static private $eids = 0;
	
	/**
	 * Names of custom keys set to HookEvent that should carry to related HookEvent instances
	 * 
	 * @var array 
	 * 
	 */
	protected $customKeys = [];
	
	/**
	 * Default values for new HookEvent 
	 * 
	 * @var array 
	 * 
	 */
	static protected $defaults = [
		'object' => null,
		'method' => '',
		'arguments' => array(),
		'return' => null,
		'replace' => false,
		'options' => array(),
		'when' => '',
		'id' => '',
		'eid' => 0,
		'cancelHooks' => false
	];
	
	/**
	 * Construct the HookEvent and establish default values
	 * 
	 * Constructor should only be used for setting properties that appear in 
	 * `self::$defaults` and should not be used for setting custom data. 
	 * 
	 * @param array $eventData Optional event data to start with
	 *
	 */
	public function __construct(array $eventData = array()) {
		$data = self::$defaults;
		$data['eid'] = ++self::$eids;
		if(!empty($eventData)) $data = array_merge($data, $eventData);
		$this->data = $data;
		parent::__construct();
	}

	/**
	 * Retrieve or set a hooked function argument
	 * 
	 * ~~~~~
	 * // Retrieve first argument by index (0=first)
	 * $page = $event->arguments(0);
	 * 
	 * // Retrieve array of all arguments
	 * $arguments = $event->arguments();
	 * 
	 * // Retrieve argument by name
	 * $page = $event->arguments('page'); 
	 * 
	 * // Set first argument by index
	 * $event->arguments(0, $page);
	 * 
	 * // Set first argument by name
	 * $event->arguments('page', $page); 
	 * ~~~~~
	 *
	 * @param int $n Zero based number of the argument you want to retrieve, where 0 is the first.
	 *	 May also be a string containing the argument name. 
	 *   Omit to return array of all arguments. 
	 * @param mixed $value Value that you want to set to this argument, or omit to only return the argument.
	 * @return array|null|mixed 
	 *
	 */
	public function arguments($n = null, $value = null) {
		if($n === null) return $this->data['arguments'];
		if(func_num_args() > 1) {
			$this->setArgument($n, $value); 
			return $value;
		}
		if(array_key_exists($n, $this->data['arguments'])) return $this->data['arguments'][$n];
		if(is_string($n)) return $this->argumentsByName($n);
		return null;
	}

	/**
	 * Returns an array of all arguments indexed by name, or the value of a single specified argument
	 *
	 * Note: `$event->arguments('name')` can also be used as a shorter synonym for `$event->argumentsByName('name')`.
	 * 
	 * ~~~~~
	 * // Get an array of all arguments indexed by name
	 * $arguments = $event->argumentsByName();
	 * 
	 * // Get a specific argument by name
	 * $page = $event->argumentsByName('page'); 
	 * ~~~~~
	 *
	 * @param string $n Optional name of argument value to return. If not specified, array of all argument values returned.
	 * @return mixed|array Depending on whether you specify $n
	 *
	 */
	public function argumentsByName($n = '') {

		$arguments = $this->data['arguments'];
		if(isset($arguments[$n])) return $arguments[$n]; 
		
		$names = $this->getArgumentNames();

		if($n) {
			$key = array_search($n, $names); 
			if($key === false) return null;	
			return array_key_exists($key, $arguments) ? $arguments[$key] : null;
		}

		$argumentsByName = array();

		foreach($names as $key => $name) {
			$value = null;
			if(isset($arguments[$key])) $value = $arguments[$key];
			$argumentsByName[$name] = $value;
		}

		return $argumentsByName;
	}


	/**
	 * Sets an argument value, handles the implementation of setting for the above arguments() function
	 *
	 * Only useful with 'before' hooks, where the argument can be manipulated before being sent to the hooked function.
	 * 
	 * #pw-internal 
	 *
	 * @param int|string Argument name or key
	 * @param mixed $value
	 * @return $this
	 * @throws WireException
	 *
	 */
	public function setArgument($n, $value) {

		if(is_string($n) && !ctype_digit($n)) {
			// convert argument name to position
			$names = $this->getArgumentNames();	
			$name = $n;
			$n = array_search($name, $names); 
			if($n === false) throw new WireException("Unknown argument name: $name"); 
		}

		$this->data['arguments'][(int)$n] = $value;
		return $this; 
	}

	/**
	 * Return an array of all argument names, indexed by their position
	 *
	 * @return array
	 *
	 */
	protected function getArgumentNames() {

		$o = $this->get('object'); 
		$m = $this->get('method');
		$key = get_class($o) . '.' . $m; 

		if(isset(self::$argumentNames[$key])) return self::$argumentNames[$key];

		$argumentNames = [];
		$arguments = [];
		
		if(method_exists($o, '___' . $m)) {
			$method = new \ReflectionMethod($o, '___' . $m);
			$arguments = $method->getParameters();
		}

		foreach($arguments as $a) {
			$pos = $a->getPosition();
			$argumentNames[$pos] = $a->getName();
		}

		self::$argumentNames[$key] = $argumentNames; 

		return $argumentNames; 
	}

	/**
	 * Remove a hook by ID
	 * 
	 * To remove the hook that this event is for, call it with the $hookId argument as null or blank.
	 * 
	 * ~~~~~
	 * // Remove this hook event, preventing it from executing again
	 * $event->removeHook(null); 
	 * ~~~~~
	 * 
	 * @param string|HookEvent|null $hookId
	 * @return HookEvent|WireData $this
	 * 
	 */
	public function removeHook($hookId) {
		if(empty($hookId) || $hookId === $this) {
			if($this->object && $this->id) {
				$this->object->removeHook($this->id);
			}
			return $this;
		} else {
			return parent::removeHook($hookId);
		}
	}

	/**
	 * Get
	 * 
	 * @param object|string $key
	 * @return mixed|null
	 * 
	 */
	public function get($key) {
		$value = parent::get($key);
		if($value === null && !ctype_digit("$key") && array_key_exists($key, $this->data['arguments'])) {
			// allow named arguments to be accessed from get()
			$value = $this->data['arguments'][$key];
		}
		return $value;
	}
	
	/**
	 * Set
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 * 
	 */
	public function set($key, $value) {
		if(!array_key_exists($key, self::$defaults)) {
			$this->customKeys[$key] = $key;
		}
		return parent::set($key, $value);
	}
	
	/**
	 * Get any custom data set to this HookEvent
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * @since 3.0.258
	 * 
	 */
	public function getCustomData() {
		if(empty($this->customKeys)) return [];
		$data = [];
		foreach($this->customKeys as $key) {
			$data[$key] = parent::get($key);
		}
		return $data;
	}
	
	/**
	 * Return a string representing the HookEvent
	 *
	 */
	public function __toString() {
		$a = [];
		foreach($this->arguments as $v) {
			if(is_object($v)) {
				$a[] = wireClassName($v);
			} else if(is_array($v)) {
				$a[] = 'array(' . count($v) . ')';
			} else if(is_int($v) || is_float($v)) {
				$a[] = "$v";
			} else if($v === null) {
				$a[] = 'null';
			} else if(is_bool($v)) {
				$a[] = $v ? 'true' : 'false';
			} else {
				$a[] = '"' . "$v" . '"';
			}
		}
		return
			$this->object->className() . '::' .
			$this->method . '(' . implode(', ', $a) . ")";
	}
	
}

