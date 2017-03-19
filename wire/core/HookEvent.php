<?php namespace ProcessWire;

/**
 * ProcessWire HookEvent
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
 * @property-read string $when In an active hook, contains either the string 'before' or 'after', indicating whether it is executing before or after the hooked method. 
 * @property bool $cancelHooks When true, all remaining hooks will be cancelled, making this HookEvent the last one (be careful with this).
 *
 */
class HookEvent extends WireData {

	/**
	 * Cached argument names indexed by "className.method"
	 *
	 */
	static protected $argumentNames = array();

	/**
	 * Construct the HookEvent and establish default values
	 * 
	 * @param array $eventData Optional event data to start with
	 *
	 */
	public function __construct(array $eventData = array()) {
		$data = array(
			'object' => null,
			'method' => '',
			'arguments' => array(),
			'return' => null,
			'replace' => false,
			'options' => array(),
			'id' => '',
			'cancelHooks' => false
		);
		if(!empty($eventData)) $data = array_merge($data, $eventData);
		$this->data = $data;
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
		if(is_null($n)) return $this->arguments; 
		if(!is_null($value)) {
			$this->setArgument($n, $value); 
			return $value;
		}
		if(is_string($n)) return $this->argumentsByName($n);
		$arguments = $this->arguments; 
		return isset($arguments[$n]) ? $arguments[$n] : null; 
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

		$names = $this->getArgumentNames();
		$arguments = $this->arguments();

		if($n) {
			$key = array_search($n, $names); 
			if($key === false) return null;	
			return array_key_exists($key, $arguments) ? $arguments[$key] : null;
		}

		$value = null;
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
			$n = array_search($n, $names); 
			if($n === false) throw new WireException("Unknown argument name: $n"); 
		}

		$arguments = $this->arguments; 
		$arguments[(int)$n] = $value; 
		$this->set('arguments', $arguments); 
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

		$argumentNames = array();
		$method = new \ReflectionMethod($o, '___' . $m); 
		$arguments = $method->getParameters();

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
	 * @param string|null $hookId
	 * @return HookEvent|WireData $this
	 * 
	 */
	public function removeHook($hookId) {
		if(empty($hookId)) {
			if($this->object && $this->id) {
				$this->object->removeHook($this->id);
			}
			return $this;
		} else {
			return parent::removeHook($hookId);
		}
	}

	/**
	 * Return a string representing the HookEvent
	 *
	 */
	public function __toString() {
		$s = $this->object->className() . '::' . $this->method . '(';
		foreach($this->arguments as $a) $s .= is_string($a) ? '"' . $a . '", ' : "$a, ";
		$s = rtrim($s, ", ") . ")";
		return $s; 	
	}

}

