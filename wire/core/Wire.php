<?php namespace ProcessWire;

/**
 * ProcessWire Base Class "Wire"
 * 
 * #pw-summary Wire is the base class for most ProcessWire classes and modules. 
 * #pw-body = 
 * Wire derived classes have a `$this->wire()` method that provides access to ProcessWire's API variables.
 * API variables can also be accessed as local properties in most cases. Wire also provides basic methods 
 * for tracking changes and managing runtime notices specific to the instance. 
 * 
 * Wire derived classes can specify which methods are "hookable" by precending the method name with 
 * 3 underscores like this: `___myMethod()`. Other classes can then hook either before or after that method, 
 * modifying arguments or return values. Several other hook methods are also provided for Wire derived 
 * classes that are hooking into others. 
 * #pw-body
 * #pw-order-groups common,identification,hooks,notices,changes,hooker,api-helpers
 * #pw-summary-api-helpers Shortcuts to ProcessWire API variables. Access without any arguments returns the API variable. Some support arguments as shortcuts to methods in the API variable.
 * #pw-summary-changes Methods to support tracking and retrieval of changes made to the object.
 * #pw-summary-hooks Methods for managing hooks for an object instance or class. 
 * 
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-use-constants
 * 
 * @property string $className #pw-internal
 * @property ProcessWire $wire #pw-internal
 * @property Database $db #pw-internal
 * @property WireDatabasePDO $database #pw-internal
 * @property Session $session  #pw-internal
 * @property Notices $notices #pw-internal
 * @property Sanitizer $sanitizer #pw-internal
 * @property Fields $fields #pw-internal
 * @property Fieldtypes $fieldtypes #pw-internal
 * @property Fieldgroups $fieldgroups #pw-internal
 * @property Templates $templates #pw-internal
 * @property Pages $pages #pw-internal
 * @property Page $page #pw-internal
 * @property Process $process #pw-internal
 * @property Modules $modules #pw-internal
 * @property Permissions $permissions #pw-internal
 * @property Roles $roles #pw-internal
 * @property Users $users #pw-internal
 * @property User $user #pw-internal
 * @property WireCache $cache #pw-internal
 * @property WireInput $input #pw-internal
 * @property Languages $languages If LanguageSupport installed #pw-internal
 * @property Config $config #pw-internal
 * @property Fuel $fuel #pw-internal
 * @property WireHooks $hooks #pw-internal
 * @property WireDateTime $datetime #pw-internal
 * @property WireMailTools $mail #pw-internal
 * @property WireFileTools $files #pw-internal
 * 
 * @method changed(string $what, $old = null, $new = null) See Wire::___changed()
 * @method log($str = '', array $options = array()) See Wire::___log()
 * @method callUnknown($method, $arguments) See Wire::___callUnknown()
 * @method Wire trackException(\Exception $e, $severe = true, $text = null)
 * 
 * The following map API variables to function names and apply only if another function in the class does not 
 * already have the same name, which would override. All defined API variables can be accessed as functions 
 * that return the API variable, whether documented below or not. 
 * 
 * @method Pages|PageArray|Page|NullPage pages($selector = '') Access the $pages API variable as a function. #pw-group-api-helpers
 * @method Page|Mixed page($key = '', $value = null) Access the $page API variable as a function. #pw-group-api-helpers
 * @method Config|mixed config($key = '', $value = null) Access the $config API variable as a function. #pw-group-api-helpers
 * @method Modules|Module|ConfigurableModule|null modules($name = '') Access the $modules API variable as a function. #pw-group-api-helpers
 * @method User|mixed user($key = '', $value = null) Access the $user API variable as a function. #pw-group-api-helpers
 * @method Users|PageArray|User|mixed users($selector = '') Access the $users API variable as a function. #pw-group-api-helpers
 * @method Session|mixed session($key = '', $value = null) Access the $session API variable as a function.  #pw-group-api-helpers
 * @method Field|Fields|null fields($name = '') Access the $fields API variable as a function.  #pw-group-api-helpers
 * @method Templates|Template|null templates($name = '') Access the $templates API variable as a function. #pw-group-api-helpers
 * @method WireDatabasePDO database() Access the $database API variable as a function.  #pw-group-api-helpers
 * @method Permissions|Permission|PageArray|null|NullPage permissions($selector = '') Access the $permissions API variable as a function.  #pw-group-api-helpers
 * @method Roles|Role|PageArray|null|NullPage roles($selector = '') Access the $roles API variable as a function.  #pw-group-api-helpers
 * @method Sanitizer|string|int|array|null|mixed sanitizer($name = '', $value = '') Access the $sanitizer API variable as a function.  #pw-group-api-helpers
 * @method WireDateTime|string|int datetime($format = '', $value = '') Access the $datetime API variable as a function.  #pw-group-api-helpers
 * @method WireFileTools files() Access the $files API variable as a function.  #pw-group-api-helpers
 * @method WireCache|string|array|PageArray|null cache($name = '', $expire = null, $func = null) Access the $cache API variable as a function.  #pw-group-api-helpers
 * @method Languages|Language|NullPage|null languages($name = '') Access the $languages API variable as a function.  #pw-group-api-helpers
 * @method WireInput|WireInputData|array|string|int|null input($type = '', $key = '', $sanitizer = '') Access the $input API variable as a function.  #pw-group-api-helpers
 * @method WireInputData|string|int|array|null inputGet($key = '', $sanitizer = '') Access the $input->get() API variable as a function.  #pw-group-api-helpers
 * @method WireInputData|string|int|array|null inputPost($key = '', $sanitizer = '') Access the $input->post() API variable as a function.  #pw-group-api-helpers
 * @method WireInputData|string|int|array|null inputCookie($key = '', $sanitizer = '') Access the $input->cookie() API variable as a function.  #pw-group-api-helpers
 * 
 */

abstract class Wire implements WireTranslatable, WireFuelable, WireTrackable {

	/*******************************************************************************************************
	 * API VARIABLE/FUEL INJECTION AND ACCESS
	 * 
	 * PLEASE NOTE: All the following fuel related variables/methods will be going away in PW 3.0.
	 * You should use the $this->wire() method instead for compatibility with PW 3.0. The only methods
	 * and variables sticking around for PW 3.0 are:
	 * 
	 * $this->wire(...);
	 * $this->useFuel(bool);
	 * $this->useFuel
	 * 
	 */

	/**
	 * Whether this class may use fuel variables in local scope, like $this->item
	 * 
	 * @var bool
	 *
	 */
	protected $useFuel = true;
	
	/**
	 * Total number of Wire class instances
	 *
	 * @var int
	 *
	 */
	static private $_instanceTotal = 0;

	/**
	 * ID of this Wire class instance
	 *
	 * @var int
	 *
	 */
	private $_instanceNum = 0;

	public function __construct() {}

	/**
	 * Clone this Wire instance
	 * 
	 */
	public function __clone() {
		$this->_instanceNum = 0;
		$this->getInstanceNum();
	}
	
	/**
	 * Get this Wire object’s instance number
	 * 
	 * - This is a unique number among all other Wire (or derived) instances in the system.
	 * - If this instance ID has not yet been set, this will set it. 
	 * - Note that this is different from the ProcessWire instance ID.
	 * 
	 * #pw-group-identification
	 *
	 * @param bool $getTotal Specify true to get the total quantity of Wire instances rather than this instance number. 
	 * @return int Instance number
	 *
	 */
	public function getInstanceNum($getTotal = false) {
		if(!$this->_instanceNum) {
			self::$_instanceTotal++;
			$this->_instanceNum = self::$_instanceTotal;
		}
		if($getTotal) return self::$_instanceTotal;
		return $this->_instanceNum;
	}

	/**
	 * Add fuel to all classes descending from Wire
	 * 
	 * #pw-internal
	 *
	 * @param string $name 
	 * @param mixed $value 
	 * @param bool $lock Whether the API value should be locked (non-overwritable)
	 * @internal Fuel is an internal-only keyword.
	 * 	Unless static needed, use $this->wire($name, $value) instead.
	 * @deprecated Use $this->wire($name, $value, $lock) instead.
	 *
	 */
	public static function setFuel($name, $value, $lock = false) {
		$wire = ProcessWire::getCurrentInstance();
		if($wire->wire('log')) $wire->wire('log')->deprecatedCall();
		$wire->fuel()->set($name, $value, $lock);
	}

	/**
	 * Get the Fuel specified by $name or NULL if it doesn't exist
	 * 
	 * #pw-internal
	 *
	 * @param string $name
	 * @return mixed|null
	 * @internal Fuel is an internal-only keyword.  
	 * 	Use $this->wire(name) or $this->wire()->name instead, unless static is required.
	 * @deprecated
	 *
	 */
	public static function getFuel($name = '') {
		$wire = ProcessWire::getCurrentInstance();
		if($wire->wire('log')) $wire->wire('log')->deprecatedCall();
		if(empty($name)) return $wire->fuel();	
		return $wire->fuel()->$name;
	}

	/**
	 * Returns an iterable Fuel object of all Fuel currently loaded
	 * 
	 * #pw-internal
	 *
	 * @return Fuel
	 * @deprecated This method will be going away. 
	 * 	Use $this->wire() instead, or if static required use: Wire::getFuel() with no arguments
	 *
	 */
	public static function getAllFuel() {
		$wire = ProcessWire::getCurrentInstance();
		if($wire->wire('log')) $wire->wire('log')->deprecatedCall();
		return $wire->fuel();	
	}

	/**
	 * Get the Fuel specified by $name or NULL if it doesn't exist (DEPRECATED)
	 * 
	 * #pw-internal
	 * 
	 * DO NOT USE THIS METHOD: It is deprecated and only used by the ProcessWire class. 
	 * It is here in the Wire class for legacy support only. Use the wire() method instead.
	 *
	 * @param string $name
	 * @return mixed|null
	 *
	 */
	public function fuel($name = '') {
		$wire = $this->wire();
		if($wire->wire('log')) $wire->wire('log')->deprecatedCall();
		return $wire->fuel($name);
	}
	
	/**
	 * Should fuel vars be scoped locally to this class instance? (internal use only)
	 *
	 * If so, you can do things like $this->apivar.
	 * If not, then you'd have to do $this->wire('apivar').
	 *
	 * If you specify a value, it will set the value of useFuel to true or false.
	 * If you don't specify a value, the current value will be returned.
	 *
	 * Local fuel scope should be disabled in classes where it might cause any conflict with class vars.
	 * 
	 * #pw-internal
	 *
	 * @param bool $useFuel Optional boolean to turn it on or off.
	 * @return bool Current value of $useFuel
	 *
	 */
	public function useFuel($useFuel = null) {
		if(!is_null($useFuel)) $this->useFuel = $useFuel ? true : false;
		return $this->useFuel;
	}


	/*******************************************************************************************************
	 * IDENTIFICATION
	 *
	 */
	
	/**
	 * Return this object’s class name
	 * 
	 * By default, this method returns the class name without namespace. To include the namespace, call it
	 * with boolean true as the first argument. 
	 * 
	 * ~~~~~
	 * echo $page->className(); // outputs: Page
	 * echo $page->className(true); // outputs: ProcessWire\Page
	 * ~~~~~
	 * 
	 * #pw-group-identification
	 *
	 * @param array|bool|null $options Specify boolean `true` to return class name with namespace, or specify an array of
	 *  one or more options:
	 * 	- `lowercase` (bool): Specify true to make it return hyphenated lowercase version of class name (default=false).
	 * 	- `namespace` (bool): Specify true to include namespace from returned class name (default=false). 
	 * 	- *Note: The lowercase and namespace options may not both be true at the same time.*
	 * @return string String with class name
	 *
	 */
	public function className($options = null) {
		
		if(is_bool($options)) {
			$options = array('namespace' => $options);
		} else if(is_array($options)) {
			if(!empty($options['lowercase'])) $options['namespace'] = false;
		} else {
			$options = array();
		}

		if(isset($options['namespace']) && $options['namespace'] === true) {
			$className = get_class($this);
			if(strpos($className, '\\') === false) $className = "\\$className";
		} else {
			$className = wireClassName($this, false);
		}

		if(!empty($options['lowercase'])) {
			static $cache = array();
			if(isset($cache[$className])) {
				$className = $cache[$className];
			} else {
				$_className = $className;
				$part = substr($className, 1);
				if(strtolower($part) != $part) {
					// contains more than 1 uppercase character, convert to hyphenated lowercase
					$className = substr($className, 0, 1) . preg_replace('/([A-Z])/', '-$1', $part);
				}
				$className = strtolower($className);
				$cache[$_className] = $className;
			}
		}
		
		return $className;
	}


	/**
	 * Unless overridden, classes descending from Wire return their class name when typecast as a string
	 * 
	 * @return string
	 *
	 */
	public function __toString() {
		return $this->className();
	}


	/*******************************************************************************************************
	 * HOOKS
	 *
	 */
	
	/**
	 * Hooks that are local to this instance of the class only.
	 *
	 */
	protected $localHooks = array();

	/**
	 * Return all local hooks for this instance
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getLocalHooks() {
		return $this->localHooks;
	}

	/**
	 * Set local hooks for this instance
	 * 
	 * #pw-internal
	 * 
	 * @param array $hooks
	 * 
	 */
	public function setLocalHooks(array $hooks) {
		$this->localHooks = $hooks;
	}

	/**
	 * Call a method in this object, for use by WireHooks
	 * 
	 * #pw-internal
	 * 
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 * 
	 */
	public function _callMethod($method, $arguments) {
		$qty = $arguments ? count($arguments) : 0;
		$result = null;
		switch($qty) {
			case 0:
				$result = $this->$method();
				break;
			case 1:
				$result = $this->$method($arguments[0]);
				break;
			case 2:
				$result = $this->$method($arguments[0], $arguments[1]);
				break;
			case 3:
				$result = $this->$method($arguments[0], $arguments[1], $arguments[2]);
				break;
			default:
				$result = call_user_func_array(array($this, $method), $arguments);
		}
		return $result;
	}

	/**
	 * Call a hook method (optimization when it's known for certain the method exists)
	 * 
	 * #pw-internal
	 * 
	 * @param string $method Method name, without leading "___"
	 * @param array $arguments
	 * @return mixed
	 * 
	 */
	public function _callHookMethod($method, array $arguments = array()) {
		if(method_exists($this, $method)) {
			return $this->_callMethod($method, $arguments);
		}
		$hooks = $this->wire('hooks');
		if($hooks->isMethodHooked($this, $method)) {
			$result = $hooks->runHooks($this, $method, $arguments);
			return $result['return'];
		} else {
			return $this->_callMethod("___$method", $arguments);
		}
	}

	/**
	 * Provides the gateway for calling hooks in ProcessWire
	 * 
	 * When a non-existant method is called, this checks to see if any hooks have been defined and sends the call to them. 
	 * 
	 * Hooks are defined by preceding the "hookable" method in a descending class with 3 underscores, like __myMethod().
	 * When the API calls $myObject->myMethod(), it gets sent to $myObject->___myMethod() after any 'before' hooks have been called. 
	 * Then after the ___myMethod() call, any "after" hooks are then called. "after" hooks have the opportunity to change the return value.
	 *
	 * Hooks can also be added for methods that don't actually exist in the class, allowing another class to add methods to this class. 
	 *
	 * See the Wire::runHooks() method for the full implementation of hook calls.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 * @throws WireException
	 *
	 */ 
	public function __call($method, $arguments) {
		$hooks = $this->wire('hooks');
		if($hooks) {
			$result = $hooks->runHooks($this, $method, $arguments);
			if(!$result['methodExists'] && !$result['numHooksRun']) {
				$result = $this->_callWireAPI($method, $arguments);
				if(!$result) return $this->callUnknown($method, $arguments);
			}
		} else {
			$result = $this->_callWireAPI($method, $arguments);
			if(!$result) return $this->___callUnknown($method, $arguments);
		}
		return $result['return'];
	}

	/**
	 * Helper to __call() method that maps a call to an API variable when appropriate
	 * 
	 * @param string $method
	 * @param array $arguments
	 * @return array|bool
	 * @internal
	 * 
	 */
	protected function _callWireAPI($method, $arguments) {
		$var = $this->_wire ? $this->_wire->fuel()->$method : null;
		if(!$var) return false;
		// requested method maps to an API variable
		$result = array('return' => null);
		$funcName = 'wire' . ucfirst($method);
		if(__NAMESPACE__) $funcName = __NAMESPACE__ . "\\$funcName";
		if(count($arguments) && function_exists($funcName)) {
			// a function exists with this API var name
			$wire = ProcessWire::getCurrentInstance();
			// ensure function call maps to this PW instance
			if($wire !== $this->_wire) ProcessWire::setCurrentInstance($this->_wire);
			$result['return'] = call_user_func_array($funcName, $arguments);
			if($wire !== $this->_wire) ProcessWire::setCurrentInstance($wire);
		} else {
			// if no arguments provided, just return API var
			$result['return'] = $var;
		}
		return $result;
	}

	/**
	 * If method call resulted in no handler, this hookable method is called. 
	 * 
	 * This standard implementation just throws an exception. This is a template method, so the reason it
	 * exists is so that other classes can override and provide their own handler. Classes that provide
	 * their own handler should not do a `parent::__callUnknown()` unless they also fail, as that will 
	 * cause an exception to be thrown. 
	 * 
	 * If you want to override this method with a hook, see the example below. 
	 * ~~~~~
	 * $wire->addHookBefore('Wire::callUnknown', function(HookEvent $event) {
	 *   // Get information about unknown method that was called
	 *   $methodObject = $event->object; 
	 *   $methodName = $event->arguments(0); // string
	 *   $methodArgs = $event->arguments(1); // array
	 *   // The replace option replaces the method and blocks the exception
	 *   $event->replace = true; 
	 *   // Now do something with the information you have, for example
	 *   // you might want to populate a value to $event->return if 
	 *   // you want the unknown method to return a value. 
	 * }); 
	 * ~~~~~
	 * 
	 * #pw-hooker
	 * 
	 * @param string $method Requested method name
	 * @param array $arguments Arguments provided
	 * @return null|mixed Return value of method (if applicable)
	 * @throws WireException
	 * 
	 */
	protected function ___callUnknown($method, $arguments) {
		if($arguments) {} // intentional to avoid unused argument notice
		$config = $this->wire('config');
		if($config && $config->disableUnknownMethodException) return null;
		throw new WireException("Method " . $this->className() . "::$method does not exist or is not callable in this context"); 
	}

	/**
	 * Provides the implementation for calling hooks in ProcessWire
	 *
	 * Unlike __call, this method won't trigger an Exception if the hook and method don't exist. 
	 * Instead it returns a result array containing information about the call. 
	 * 
	 * #pw-internal
	 *
	 * @param string $method Method or property to run hooks for.
	 * @param array $arguments Arguments passed to the method and hook. 
	 * @param string|array $type May be either 'method', 'property' or array of hooks (from getHooks) to run. Default is 'method'.
	 * @return array Returns an array with the following information: 
	 * 	[return] => The value returned from the hook or NULL if no value returned or hook didn't exist. 
	 *	[numHooksRun] => The number of hooks that were actually run. 
	 *	[methodExists] => Did the hook method exist as a real method in the class? (i.e. with 3 underscores ___method).
	 *	[replace] => Set by the hook at runtime if it wants to prevent execution of the original hooked method.
	 *
	 */
	public function runHooks($method, $arguments, $type = 'method') {
		return $this->wire('hooks')->runHooks($this, $method, $arguments, $type);
	}

	/**
	 * Return all hooks associated with this class instance or method (if specified)
	 * 
	 * #pw-group-hooks
	 *
	 * @param string $method Optional method that hooks will be limited to. Or specify '*' to return all hooks everywhere.
	 * @param int $type Type of hooks to return, specify one of the following constants (from the WireHooks class):
	 * 	- `WireHooks::getHooksAll` returns all hooks (default).
	 * 	- `WireHooks::getHooksLocal` returns local hooks only.
	 * 	- `WireHooks::getHooksStatic` returns static hooks only.
	 * @return array
	 *
	 */
	public function getHooks($method = '', $type = 0) {
		return $this->wire('hooks')->getHooks($this, $method, $type); 
	}
	
	/**
	 * Returns true if the method/property is hooked, false if it isn’t.
	 * 
	 * This is for optimization use. It does not distinguish about class instance. 
	 * It only distinguishes about class if you provide a class with the `$method` argument (i.e. `Class::`).
	 * As a result, a true return value indicates something "might" be hooked, as opposed to be 
	 * being definitely hooked. 
	 *
	 * If checking for a hooked method, it should be in the form "Class::method()" or "method()". 
	 * If checking for a hooked property, it should be in the form "Class::property" or "property". 
	 * 
	 * #pw-internal
	 * 
	 * @param string $method Method or property name in one of the following formats:
	 * 	Class::method()
	 * 	Class::property
	 * 	method()
	 * 	property
	 * @param Wire|null $instance Optional instance to check against (see hasHook method for details)
	 * 	Note that if specifying an $instance, you may not use the Class::method() or Class::property options for $method argument.
	 * @return bool
	 * @deprecated 
	 *
	 */
	static public function isHooked($method, Wire $instance = null) {
		$wire = $instance ? $instance->wire() : ProcessWire::getCurrentInstance();
		if($instance) return $instance->wire('hooks')->hasHook($instance, $method);
		return $wire->hooks->isHooked($method);
	}

	/**
	 * Returns true if the method or property is hooked, false if it isn’t.
	 *
	 * - This method checks for both static hooks and local hooks.
	 * - Accepts a `method()` or `property` name as an argument. 
	 * - Class context is assumed to be the current class this method is called on. 
	 * - Also considers the class parents for hooks. 
	 * 
	 * ~~~~~
	 * if($pages->hasHook('find()')) {
	 *   // the Pages::find() method is hooked
	 * }
	 * ~~~~~
	 *
	 * #pw-group-hooks
	 *
	 * @param string $method Method() or property name:
	 *   - If checking for a hooked method, it should be in the form `method()`.
	 *   - If checking for a hooked property, it should be in the form `property`.
	 * @return bool True if this class instance has the hook, false if not. 
	 * @throws WireException When you try to call it with a Class::something() type method, which is not supported. 
	 *
	 */
	public function hasHook($method) {
		// Accomplishes the same thing as the static isHooked() method, but this is non-static, more accruate, 
	    // potentially slower than isHooked(). Less for optimization use, more for accuracy use. 
		return $this->wire('hooks')->hasHook($this, $method);
	}

	/**
	 * Hook a function/method to a hookable method call in this object
	 *
	 * - This method provides the implementation for addHookBefore(), addHookAfter(), addHookProperty(), addHookMethod()
	 * - Hookable method calls are methods preceded by three underscores. 
	 * - You may also specify a method that doesn't exist already in the class.
	 * - The hook method that you define may be part of a class or a globally scoped function. 
	 * 
	 * #pw-internal
	 *
	 * @param string $method Method name to hook into, NOT including the three preceding underscores. 
	 * 	May also be Class::Method for same result as using the fromClass option.
	 * @param object|null|callable $toObject Object to call $toMethod from,
	 * 	Or null if $toMethod is a function outside of an object,
	 * 	Or function|callable if $toObject is not applicable or function is provided as a closure.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event, or $options array. Optional.
	 * @param array $options Options that can modify default behaviors: 
	 *  - `type` (string): May be 'method', 'property' or 'either'. If property, then it will respond to $obj->property
	 *     rather than $obj->method(). If 'either' it will respond to both. The default type is 'method'.
	 *  - `before` (bool): Execute the hook before the method call? (allows modification of arguments).
	 *     Not applicable if 'type' is 'property'.
	 *  - `after` (bool): Execute the hook after the method call? (allows modification of return value).
	 *     Not applicable if 'type' is 'property'.
	 *  - `priority` (int): A number determining the priority of a hook, where lower numbers are executed before
	 *     higher numbers. The default priority is 100.
	 *  - `allInstances` (bool): attach the hook to all instances of this object? Set automatically, but you may
	 *     still use in some instances.
	 *  - `fromClass` (string): The name of the class containing the hooked method, if not the object where addHook
	 *     was called. Set automatically, but you may still use in some instances.
	 *  - `argMatch` (array|null): An array of Selectors objects where the indexed argument (n) to the hooked method
	 *     must match, in order to execute hook. Default is null.
	 *  - `objMatch` (array|null): Selectors object that the current object must match in order to execute hook.
	 *     Default is null. 
	 * @return string A special Hook ID that should be retained if you need to remove the hook later
	 * @throws WireException
	 *
	 */
	public function addHook($method, $toObject, $toMethod = null, $options = array()) {
		return $this->wire('hooks')->addHook($this, $method, $toObject, $toMethod, $options);
	}

	/**
	 * Add a hook to be executed before the hooked method 
	 * 
	 * - Use a "before" hook when you have code that should execute before a hookable method executes. 
	 * - One benefit of using a "before" hook is that you can have it modify the arguments that are sent to the hookable method. 
	 * - This type of hook can also completely replace a hookable method if hook populates an `$event->replace` property.
	 *   See the HookEvent class for details. 
	 * 
	 * ~~~~~
	 * // Attach hook to a method in current object
	 * $this->addHookBefore('Page::path', $this, 'yourHookMethodName'); 
	 *   
	 * // Attach hook to an inline function
	 * $this->addHookBefore('Page::path', function($event) { ... }); 
	 *   
	 * // Attach hook to a procedural function
	 * $this->addHookBefore('Page::path', 'your_function_name'); 
	 *   
	 * // Attach hook from single object instance ($page) to inline function
	 * $page->addHookBefore('path', function($event) { ... }); 
	 * ~~~~~
	 * 
	 * #pw-group-hooks
	 *
	 * @param string $method Method to hook in one of the following formats (please omit 3 leading underscores): 
	 *  - `Class::method` - If hooking to *all* object instances of the class. 
	 *  - `method` - If hooking to a single object instance. 
	 * @param object|null|callable $toObject Specify one of the following: 
	 *  - Object instance to call `$toMethod` from (like `$this`).
	 *  - Inline function (closure) if providing implemention inline. 
	 *  - Procedural function name, if hook is implemented by a procedural function. 
	 *  - Null if you want to use the 3rd argument and don't need this argument. 
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event. 
	 *   This argument can be sustituted as the 2nd argument when the 2nd argument isn’t needed,
	 *   or it can be the $options argument. 
	 * @param array $options Array of options that can modify behavior: 
	 *  - `type` (string): May be either 'method' or 'property'. If property, then it will respond to $obj->property 
	 *     rather than $obj->method(). The default type is 'method'.
	 *  - `priority` (int): A number determining the priority of a hook, where lower numbers are executed before 
	 *     higher numbers. The default priority is 100. 
	 * @return string A special Hook ID that should be retained if you need to remove the hook later.
	 *
	 */
	public function addHookBefore($method, $toObject, $toMethod = null, $options = array()) {
		// This is the same as calling addHook with the 'before' option set the $options array.
		$options['before'] = true; 
		if(!isset($options['after'])) $options['after'] = false; 
		return $this->wire('hooks')->addHook($this, $method, $toObject, $toMethod, $options); 
	}

	/**
	 * Add a hook to be executed after the hooked method
	 * 
	 * - Use an "after" hook when you have code that should execute after a hookable method executes.
	 * - One benefit of using an "after" hook is that you can have it modify the return value. 
	 *
	 * ~~~~~
	 * // Attach hook to a method in current object
	 * $this->addHookAfter('Page::path', $this, 'yourHookMethodName');
	 *  
	 * // Attach hook to an inline function
	 * $this->addHookAfter('Page::path', function($event) { ... });
	 *  
	 * // Attach hook to a procedural function
	 * $this->addHookAfter('Page::path', 'your_function_name');
	 *  
	 * // Attach hook from single object instance ($page) to inline function
	 * $page->addHookAfter('path', function($event) { ... });
	 * ~~~~~
	 * 
	 * #pw-group-hooks
	 *
	 * @param string $method Method to hook in one of the following formats (please omit 3 leading underscores):
	 *  - `Class::method` - If hooking to *all* object instances of the class.
	 *  - `method` - If hooking to a single object instance.
	 * @param object|null|callable $toObject Specify one of the following:
	 *  - Object instance to call `$toMethod` from (like `$this`).
	 *  - Inline function (closure) if providing implemention inline.
	 *  - Procedural function name, if hook is implemented by a procedural function.
	 *  - Null if you want to use the 3rd argument and don't need this argument.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event.
	 *   This argument can be sustituted as the 2nd argument when the 2nd argument isn't needed,
	 *   or it can be the $options argument. 
	 * @param array $options Array of options that can modify behavior:
	 *  - `type` (string): May be either 'method' or 'property'. If property, then it will respond to $obj->property
	 *     rather than $obj->method(). The default type is 'method'.
	 *  - `priority` (int): A number determining the priority of a hook, where lower numbers are executed before
	 *     higher numbers. The default priority is 100.
	 * @return string A special Hook ID that should be retained if you need to remove the hook later.
	 *
	 */
	public function addHookAfter($method, $toObject, $toMethod = null, $options = array()) {
		$options['after'] = true; 
		if(!isset($options['before'])) $options['before'] = false; 
		return $this->wire('hooks')->addHook($this, $method, $toObject, $toMethod, $options); 
	}

	/**
	 * Add a hook that will be accessible as a new object property. 
	 * 
	 * This enables you to add a new accessible property to an existing object, which will execute
	 * your hook implementation method when called upon. 
	 * 
	 * Note that adding a hook with this just makes it possible to call the hook as a property. 
	 * Any hook property you add can also be called as a method, i.e. `$obj->foo` and `$obj->foo()`
	 * are the same.
	 * 
	 * ~~~~~
	 * // Adding a hook property
	 * $wire->addHookProperty('Page::lastModifiedStr', function($event) {
	 *   $page = $event->object; 
	 *   $event->return = wireDate('relative', $page->modified); 
	 * });
	 * 
	 * // Accessing the property (from any instance)
	 * echo $page->lastModifiedStr; // outputs: "10 days ago"
	 * ~~~~~
	 * 
	 * #pw-group-hooks
	 *
	 * @param string $property Name of property you want to add, must not collide with existing property or method names:
	 *  - `Class::property` to add the property to all instances of Class. 
	 *  - `property` if just adding to a single object instance. 
	 * @param object|null|callable $toObject Specify one of the following:
	 *  - Object instance to call `$toMethod` from (like `$this`).
	 *  - Inline function (closure) if providing implemention inline.
	 *  - Procedural function name, if hook is implemented by a procedural function.
	 *  - Null if you want to use the 3rd argument and don't need this argument.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event.
	 *   This argument can be sustituted as the 2nd argument when the 2nd argument isn’t needed,
	 *   or it can be the $options argument. 
	 * @param array $options Options typically aren't used in this context, but see Wire::addHookBefore() $options if you'd like.
	 * @return string A special Hook ID that should be retained if you need to remove the hook later.
	 *
	 */
	public function addHookProperty($property, $toObject, $toMethod = null, $options = array()) {
		// This is the same as calling addHook with the 'type' option set to 'property' in the $options array. 
	    // Note that descending classes that override __get must call getHook($property) and/or runHook($property).
		$options['type'] = 'property'; 
		return $this->wire('hooks')->addHook($this, $property, $toObject, $toMethod, $options); 
	}
	
	/**
	 * Add a hook accessible as a new public method in a class (or object) 
	 * 
	 * - This enables you to add a new accessible public method to an existing object, which will execute
	 *   your hook implementation method when called upon. 
	 *   
	 * - Hook method can accept arguments and/or populate return values, just like any other regular method 
	 *   in the class. However, methods such as this do not have access to private or protected 
	 *   properties/methods in the class. 
	 *   
	 * - Methods added like this themselves become hookable as well. 
	 * 
	 * #pw-group-hooks
	 *
	 * ~~~~~
	 * // Adds a myHasParent($parent) method to all Page objects
	 * $wire->addHookMethod('Page::myHasParent', function($event) {
	 *   $page = $event->object;
	 *   $parent = $event->arguments(0); 
	 *   if(!$parent instanceof Page) {
	 *     throw new WireException("Page::myHasParent() requires a Page argument"); 
	 *   }
	 *   if($page->parents()->has($parent)) {
	 *     // this page has the given parent
	 *     $event->return = true; 
	 *   } else {
	 *     // does not have the given parent
	 *     $event->return = false; 
	 *   }
	 * });
	 *
	 * // Calling the new method (from any instance)
	 * $parent = $pages->get('/products/'); 
	 * if($page->myHasParent($parent)) {
	 *   // $page has the given $parent
	 * } 
	 * ~~~~~
	 *
	 * @param string $method Name of method you want to add, must not collide with existing property or method names:
	 *  - `Class::method` to add the method to all instances of Class.
	 *  - `method` to just add to a single object instance.
	 * @param object|null|callable $toObject Specify one of the following:
	 *  - Object instance to call `$toMethod` from (like `$this`).
	 *  - Inline function (closure) if providing implemention inline.
	 *  - Procedural function name, if hook is implemented by a procedural function.
	 *  - Null if you want to use the 3rd argument and don't need this argument.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event.
	 *   This argument can be sustituted as the 2nd argument when the 2nd argument isn’t needed, 
	 *   or it can be the $options argument. 
	 * @param array $options Options typically aren't used in this context, but see Wire::addHookBefore() $options if you'd like.
	 * @return string A special Hook ID that should be retained if you need to remove the hook later.
	 * @since 3.0.16 Added as an alias to addHook() for syntactic clarity, previous versions can use addHook() method with same arguments. 
	 *
	 */
	public function addHookMethod($method, $toObject, $toMethod = null, $options = array()) {
		return $this->wire('hooks')->addHook($this, $method, $toObject, $toMethod, $options);
	}

	/**
	 * Given a Hook ID, remove the hook
	 * 
	 * Once a hook is removed, it will no longer execute. 
	 * 
	 * ~~~~~
	 * // Add a hook
	 * $hookID = $pages->addHookAfter('find', function($event) {
	 *   // do something
	 * });
	 * 
	 * // Remove the hook
	 * $pages->removeHook($hookID); 
	 * ~~~~~
	 * ~~~~~
	 * // Hook function that removes itself
	 * $hookID = $pages->addHookAfter('find', function($event) {
	 *   // do something
	 *   $event->removeHook(null); // note: calling removeHook on $event
	 * });
	 * ~~~~~
	 * 
	 * #pw-group-hooks
	 *
	 * @param string|null $hookId ID of hook to remove (ID is returned by the addHook() methods)
	 * @return $this
	 *
	 */
	public function removeHook($hookId) {
		return $this->wire('hooks')->removeHook($this, $hookId);
	}

	
	/*******************************************************************************************************
	 * CHANGE TRACKING
	 *
	 */

	/**
	 * For setTrackChanges() method flags: track names only (default).
	 * 
	 * #pw-group-changes
	 *
	 */
	const trackChangesOn = 2;
	
	/**
	 * For setTrackChanges() method flags: track names and values.
	 * 
	 * #pw-group-changes
	 *
	 */
	const trackChangesValues = 4;
	
	/**
	 * Track changes mode
	 * 
	 * @var int Bitmask
	 *
	 */
	private $trackChanges = 0;

	/**
	 * Array containing the names of properties (as array keys) that were changed while change tracking was ON.
	 * 
	 * Array values are insignificant unless trackChangeMode is trackChangesValues (1), in which case the values are the previous values.
	 * 
	 * @var array
	 *
	 */
	private $changes = array();
	
	/**
	 * Does the object have changes, or has the given property changed? 
	 *
	 * Applicable only when object has change tracking enabled. 
	 * 
	 * ~~~~~
	 * // Check if page has changed
	 * if($page->isChanged()) {
	 *   // Page has changes
	 * }
	 * 
	 * // Check if the page title field has changed
	 * if($page->isChanged('title')) {
	 *   // The title has changed
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-changes
	 *
	 * @param string $what Name of property, or if left blank, checks if any properties have changed. 
	 * @return bool True if property has changed, false if not. 
	 *
	 */
	public function isChanged($what = '') {
		if(!$what) return count($this->changes) > 0; 
		return array_key_exists($what, $this->changes); 
	}

	/**
	 * Hookable method that is called whenever a property has changed while change tracking is enabled. 
	 *
	 * - Enables hooks to monitor changes to the object. 
	 * - Do not call this method directly, as the `Wire::trackChange()` method already does so. 
	 * - Descending classes should call `$this->trackChange('name', $oldValue, $newValue);` when a property they are tracking has changed.
	 *
	 * #pw-group-hooker
	 * 
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change 
	 * @param mixed $new New value
	 * @see Wire::trackChange()
	 *
	 */
	public function ___changed($what, $old = null, $new = null) {
		// for hooks to listen to 
	}

	/**
	 * Track a change to a property in this object
	 *
	 * The change will only be recorded if change tracking is enabled for this object instance. 
	 * 
	 * #pw-group-changes
	 *
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change
	 * @param mixed $new New value
	 * @return $this
	 * 
	 */
	public function trackChange($what, $old = null, $new = null) {
		
		if($this->trackChanges & self::trackChangesOn) {
			
			// establish it as changed
			if(array_key_exists($what, $this->changes)) {
				// remember last value so we can avoid duplication in hooks or storage
				$lastValue = end($this->changes[$what]); 
			} else {
				$lastValue = null;
				$this->changes[$what] = array();
			}
		
			if(is_null($old) || is_null($new) || $lastValue !== $new) {
				/** @var WireHooks $hooks */
				$hooks = $this->wire('hooks');
				if(($hooks && $hooks->isHooked('changed()')) || !$hooks) {
					$this->changed($what, $old, $new); // triggers ___changed hook
				} else {
					$this->___changed($what, $old, $new); 
				}
			}
			
			if($this->trackChanges & self::trackChangesValues) {
				// track changed values, but avoid successive duplication of same value
				if(is_object($old) && $old === $new) $old = clone $old; // keep separate copy of objects for old value
				if($lastValue !== $old || !count($this->changes[$what])) $this->changes[$what][] = $old; 
				
			} else {
				// don't track changed values, just names of fields
				$this->changes[$what][] = null;
			}
			
		}
		
		return $this; 
	}

	/**
	 * Untrack a change to a property in this object
	 * 
	 * #pw-group-changes
	 *
	 * @param string $what Name of property that you want to remove its change being tracked
	 * @return $this
	 * 
	 */
	public function untrackChange($what) {
		unset($this->changes[$what]); 
		return $this; 
	}

	/**
	 * Turn change tracking ON or OFF
	 * 
	 * ~~~~~
	 * // Enable change tracking
	 * $page->setTrackChanges(true);
	 * 
	 * // Disable change tracking
	 * $page->setTrackChanges(false);
	 * 
	 * // Enable change tracking and remember values
	 * $page->setTrackChanges(Wire::trackChangesValues); 
	 * $page->setTrackChanges(true);
	 * ~~~~~
	 * 
	 * #pw-group-changes
	 *
	 * @param bool|int $trackChanges Specify one of the following: 
	 *   - `true` (bool): Enables change tracking. 
	 *   - `false` (bool): Disables change tracking
	 *   - `Wire::trackChangesOn` (constant): Enables change tracking (same as specifying boolean true).
	 *   - `Wire::trackChangesValues` (constant): Enables tracking of changed values when change tracking is already on. 
	 *     This uses more memory since it keeps previous values, so it is not enabled by default. Once enabled, the 
	 *     setting will persist through boolean true|false arguments. 
	 * @return $this
	 *
	 */
	public function setTrackChanges($trackChanges = true) {
		if(is_bool($trackChanges) || !$trackChanges) {
			// turn change track on or off
			if($trackChanges) {
				$this->trackChanges = $this->trackChanges | self::trackChangesOn; // add bit
			} else {
				$this->trackChanges = $this->trackChanges & ~self::trackChangesOn; // remove bit
			}
		} else if(is_int($trackChanges)) {
			// set bitmask
			$allowed = array(
				self::trackChangesOn, 
				self::trackChangesValues, 
				self::trackChangesOn | self::trackChangesValues
			); 
			if(in_array($trackChanges, $allowed)) $this->trackChanges = $trackChanges; 
		}
		return $this; 
	}

	/**
	 * Returns true or 1 if change tracking is on, or false or 0 if it is not, or mode bitmask (int) if requested. 
	 * 
	 * #pw-group-changes
	 *
	 * @param bool $getMode When true, the track changes mode bitmask will be returned 
	 * @return int|bool 0/false if off, 1/true if On, or mode bitmask if requested 
	 * 
	 */
	public function trackChanges($getMode = false) {
		if($getMode) return $this->trackChanges; 
		return $this->trackChanges & self::trackChangesOn;	
	}

	/**
	 * Clears out any tracked changes and turns change tracking ON or OFF
	 * 
	 * ~~~~
	 * // Clear any changes that have been tracked and start fresh
	 * $page->resetTrackChanges();
	 * ~~~~
	 * 
	 * #pw-group-changes
	 *
	 * @param bool $trackChanges True to turn change tracking ON, or false to turn OFF. Default of true is assumed.
	 * @return $this
	 *
	 */
	public function resetTrackChanges($trackChanges = true) {
		$this->changes = array();
		return $this->setTrackChanges($trackChanges); 
	}

	/**
	 * Return an array of properties that have changed while change tracking was on. 
	 * 
	 * ~~~~~
	 * // Get an array of changed field names
	 * $changes = $page->getChanges();
	 * ~~~~~
	 * 
	 * #pw-group-changes
	 *
	 * @param bool $getValues Specify one of the following, or omit for default setting. 
	 *  - `false` (bool): return array of changed property names (default setting).
	 *  - `true` (bool): return an associative array containing an array of previous values, indexed by 
	 *     property name, oldest to newest. Requires Wire::trackChangesValues mode to be enabled. 
	 *  - `2` (int): Return array where both keys and values are changed property names. 
	 * @return array
	 *
	 */
	public function getChanges($getValues = false) {
		if($getValues === 2) {
			$changes = array();
			foreach($this->changes as $name => $value) {
				if($value) {} // value ignored
				$changes[$name] = $name;
			}
			return $changes;
		} else if($getValues) {
			return $this->changes;
		} else {
			return array_keys($this->changes);
		}
	}

	
	/*******************************************************************************************************
	 * NOTICES AND LOGS
	 *
	 */

	/**
	 * @var Notices[]
	 * 
	 */
	protected $_notices = array(
		'errors' => null, 
		'warnings' => null, 
		'messages' => null
	);

	/**
	 * Record a Notice, internal use (contains the code for message, warning and error methods)
	 * 
	 * @param string $text|array|Wire Title of notice
	 * @param int $flags Flags bitmask
	 * @param string $name Name of container
	 * @param string $class Name of Notice class
	 * @return $this
	 * 
	 */
	protected function _notice($text, $flags, $name, $class) {
		if($flags === true) $flags = Notice::log;
		$class = wireClassName($class, true);
		$notice = $this->wire(new $class($text, $flags));
		$notice->class = $this->className();
		if(is_null($this->_notices[$name])) $this->_notices[$name] = $this->wire(new Notices());
		$this->wire('notices')->add($notice);
		if(!($notice->flags & Notice::logOnly)) $this->_notices[$name]->add($notice);
		return $this; 
	}

	/**
	 * Record an informational or “success” message in the system-wide notices. 
	 *
	 * This method automatically identifies the message as coming from this class.
	 * 
	 * ~~~~~
	 * $this->message("This is the notice text");
	 * $this->message("This notice is also logged", true);
	 * $this->message("This notice is only shown in debug mode", Notice::debug);
	 * $this->message("This notice allows <em>markup</em>", Notice::allowMarkup);
	 * $this->message("Notice using multiple flags", Notice::debug | Notice::logOnly);
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 *
	 * @param string|array|Wire $text Text to include in the notice
	 * @param int|bool $flags Optional flags to alter default behavior: 
	 *  - `Notice::debug` (constant): Indicates notice should only be shown when debug mode is active.
	 *  - `Notice::log` (constant): Indicates notice should also be logged.
	 *  - `Notice::logOnly` (constant): Indicates notice should only be logged.
	 *  - `Notice::allowMarkup` (constant): Indicates notice should allow the use of HTML markup tags.
	 *  - `true` (boolean): Shortcut for the `Notice::log` constant.
	 * @return $this
	 * @see Wire::messages(), Wire::warning(), Wire::error()
	 *
	 */
	public function message($text, $flags = 0) {
		return $this->_notice($text, $flags, 'messages', 'NoticeMessage'); 
	}
	
	/**
	 * Record a warning error message in the system-wide notices.
	 *
	 * This method automatically identifies the warning as coming from this class.
	 * 
	 * ~~~~~
	 * $this->warning("This is the notice text");
	 * $this->warning("This notice is also logged", true);
	 * $this->warning("This notice is only shown in debug mode", Notice::debug);
	 * $this->warning("This notice allows <em>markup</em>", Notice::allowMarkup);
	 * $this->warning("Notice using multiple flags", Notice::debug | Notice::logOnly);
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 *
	 * @param string|array|Wire $text Text to include in the notice
	 * @param int|bool $flags Optional flags to alter default behavior:
	 *  - `Notice::debug` (constant): Indicates notice should only be shown when debug mode is active.
	 *  - `Notice::log` (constant): Indicates notice should also be logged.
	 *  - `Notice::logOnly` (constant): Indicates notice should only be logged.
	 *  - `Notice::allowMarkup` (constant): Indicates notice should allow the use of HTML markup tags.
	 *  - `true` (boolean): Shortcut for the `Notice::log` constant.
	 * @return $this
	 * @see Wire::warnings(), Wire::message(), Wire::error()
	 *
	 *
	 */
	public function warning($text, $flags = 0) {
		return $this->_notice($text, $flags, 'warnings', 'NoticeWarning'); 
	}

	/**
	 * Record an non-fatal error message in the system-wide notices. 
	 *
	 * - This method automatically identifies the error as coming from this class. 
	 * - You should still make fatal errors throw a `WireException` (or class derived from it).
	 * 
	 * ~~~~~
	 * $this->error("This is the notice text"); 
	 * $this->error("This notice is also logged", true);
	 * $this->error("This notice is only shown in debug mode", Notice::debug);
	 * $this->error("This notice allows <em>markup</em>", Notice::allowMarkup);
	 * $this->error("Notice using multiple flags", Notice::debug | Notice::logOnly);
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 *
	 * @param string|array|Wire $text Text to include in the notice
	 * @param int|bool $flags Optional flags to alter default behavior:
	 *  - `Notice::debug` (constant): Indicates notice should only be shown when debug mode is active.
	 *  - `Notice::log` (constant): Indicates notice should also be logged.
	 *  - `Notice::logOnly` (constant): Indicates notice should only be logged.
	 *  - `Notice::allowMarkup` (constant): Indicates notice should allow the use of HTML markup tags.
	 *  - `true` (boolean): Shortcut for the `Notice::log` constant.
	 * @return $this
	 * @see Wire::errors(), Wire::message(), Wire::warning()
	 *
	 */
	public function error($text, $flags = 0) {
		return $this->_notice($text, $flags, 'errors', 'NoticeError'); 
	}

	/**
	 * Hookable method called when an Exception occurs
	 * 
	 * - It will log Exception to `exceptions.txt` log if 'exceptions' is in `$config->logs`. 
	 * - It will re-throw Exception if `$config->allowExceptions` is true. 
	 * - If additional `$text` is provided, it will be sent to notice method call. 
	 * 
	 * #pw-hooker
	 * 
	 * @param \Exception|WireException $e Exception object that was thrown.
	 * @param bool|int $severe Whether or not it should be considered severe (default=true).
	 * @param string|array|object|true $text Additional details (optional):
	 * 	- When provided, it will be sent to `$this->error($text)` if $severe is true, or `$this->warning($text)` if $severe is false.
	 * 	- Specify boolean `true` to just send the `$e->getMessage()` to `$this->error()` or `$this->warning()`. 
	 * @return $this
	 * @throws \Exception If `$severe==true` and `$config->allowExceptions==true`
	 * 
	 */
	public function ___trackException(\Exception $e, $severe = true, $text = null) {
		$config = $this->wire('config');
		$log = $this->wire('log');
		$msg = $e->getMessage();
		if($text !== null) {
			if($text === true) $text = $msg;
			$severe ? $this->error($text) : $this->warning($text);
			if(strpos($text, $msg) === false) $msg = "$text - $msg";
		}
		if(in_array('exceptions', $config->logs) && $log) {
			$msg .= " (in " . str_replace($config->paths->root, '/', $e->getFile()) . " line " . $e->getLine() . ")";
			$log->save('exceptions', $msg);
		}
		if($severe && $this->wire('config')->allowExceptions) {
			throw $e; // re-throw, if requested
		}
		return $this;
	}

	/**
	 * Return or manage errors recorded by just this object or all Wire objects
	 * 
	 * This method returns and manages errors that were previously set by `Wire::error()`. 
	 * 
	 * ~~~~~
	 * // Get errors for one object
	 * $errors = $obj->errors();
	 * 
	 * // Get first error in object
	 * $error = $obj->errors('first');
	 * 
	 * // Get errors for all Wire objects
	 * $errors = $obj->errors('all'); 
	 * 
	 * // Get and clear all errors for all Wire objects
	 * $errors = $obj->errors('clear all'); 
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 * 
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	- `first` - only first item will be returned 
	 * 	- `last` - only last item will be returned 
	 * 	- `all` - include all errors, including those beyond the scope of this object
	 * 	- `clear` - clear out all items that are returned from this method
	 * 	- `array` - return an array of strings rather than series of Notice objects.
	 * 	- `string` - return a newline separated string rather than array/Notice objects. 
	 * @return Notices|array|string Array of `NoticeError` errors, or string if last, first or str option was specified.
	 * 
	 */
	public function errors($options = array()) {
		if(!is_array($options)) $options = explode(' ', strtolower($options)); 
		$options[] = 'errors';
		return $this->messages($options); 
	}

	/**
	 * Return or manage warnings recorded by just this object or all Wire objects
	 * 
	 * This method returns and manages warnings that were previously set by `Wire::warning()`. 
	 * 
	 * ~~~~~
	 * // Get warnings for one object
	 * $warnings = $obj->warnings();
	 *
	 * // Get first warning in object
	 * $warning = $obj->warnings('first');
	 *
	 * // Get warnings for all Wire objects
	 * $warnings = $obj->warnings('all');
	 *
	 * // Get and clear all warnings for all Wire objects
	 * $warnings = $obj->warnings('clear all');
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 *
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	- `first` - only first item will be returned
	 * 	- `last` - only last item will be returned
	 * 	- `all` - include all errors, including those beyond the scope of this object
	 * 	- `clear` - clear out all items that are returned from this method
	 * 	- `array` - return an array of strings rather than series of Notice objects.
	 * 	- `string` - return a newline separated string rather than array/Notice objects.
	 * @return Notices|array|string Array of `NoticeWarning` warnings, or string if last, first or str option was specified.
	 * 
	 *
	 */
	public function warnings($options = array()) {
		if(!is_array($options)) $options = explode(' ', strtolower($options));
		$options[] = 'warnings';
		return $this->messages($options); 
	}

	/**
	 * Return or manage messages recorded by just this object or all Wire objects
	 * 
	 * This method returns and manages messages that were previously set by `Wire::message()`. 
	 *
	 * ~~~~~
	 * // Get messages for one object
	 * $messages = $obj->messages();
	 *
	 * // Get first message in object
	 * $message = $obj->messages('first');
	 *
	 * // Get messages for all Wire objects
	 * $messages = $obj->messages('all');
	 *
	 * // Get and clear all messages for all Wire objects
	 * $messages = $obj->messages('clear all');
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 *
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	- `first` - only first item will be returned
	 * 	- `last` - only last item will be returned
	 * 	- `all` - include all errors, including those beyond the scope of this object
	 * 	- `clear` - clear out all items that are returned from this method
	 * 	- `array` - return an array of strings rather than series of Notice objects.
	 * 	- `string` - return a newline separated string rather than array/Notice objects.
	 * @return Notices|array|string Array of `NoticeMessage` messages, or string if last, first or str option was specified.
	 *
	 */
	public function messages($options = array()) {
		if(!is_array($options)) $options = explode(' ', strtolower($options)); 
		if(in_array('errors', $options)) $type = 'errors'; 
			else if(in_array('warnings', $options)) $type = 'warnings';
			else $type = 'messages';
		$clear = in_array('clear', $options); 
		if(in_array('all', $options)) {
			// get all of either messages, warnings or errors (either in or out of this object instance)
			$value = $this->wire(new Notices());
			foreach($this->wire('notices') as $notice) {
				if($notice->getName() != $type) continue;
				$value->add($notice);
				if($clear) $this->wire('notices')->remove($notice); // clear global
			}
			if($clear) $this->_notices[$type] = null; // clear local
		} else {
			// get messages, warnings or errors specific to this object instance
			$value = is_null($this->_notices[$type]) ? $this->wire(new Notices()) : $this->_notices[$type];
			if(in_array('first', $options)) $value = $clear ? $value->shift() : $value->first();
				else if(in_array('last', $options)) $value = $clear ? $value->pop() : $value->last(); 
				else if($clear) $this->_notices[$type] = null;
			if($clear && $value) $this->wire('notices')->removeItems($value); // clear from global notices
		}
		if(in_array('array', $options) || in_array('string', $options)) {
			if($value instanceof Notice) {
				$value = array($value->text);
			} else {
				$_value = array();
				foreach($value as $notice) $_value[] = $notice->text; 
				$value = $_value; 
			}
			if(in_array('string', $options)) {
				$value = implode("\n", $value); 
			}
		}
		return $value; 
	}

	/**
	 * Log a message for this class
	 * 
	 * Message is saved to a log file in ProcessWire's logs path to a file with 
	 * the same name as the class, converted to hyphenated lowercase. For example, 
	 * a class named `MyWidgetData` would have a log named `my-widget-data.txt`.
	 * 
	 * ~~~~~
	 * $this->log("This message will be logged"); 
	 * ~~~~~
	 * 
	 * #pw-group-notices
	 * 
	 * @param string $str Text to log, or omit to return the `$log` API variable.
	 * @param array $options Optional extras to include: 
	 *  - `url` (string): URL to record the with the log entry (default=auto-detect)
	 *  - `name` (string): Name of log to use (default=auto-detect)
	 *  - `user` (User|string|null): User instance, user name, or null to log for current User. (default=null)
	 * @return WireLog
	 *
	 */
	public function ___log($str = '', array $options = array()) {
		$log = $this->wire('log');
		if($log && strlen($str)) {
			if(isset($options['name'])) {
				$name = $options['name'];
				unset($options['name']);
			} else {
				$name = $this->className(array('lowercase' => true));
			}
			$log->save($name, $str, $options);
		}
		return $log; 
	}
	
	/*******************************************************************************************************
	 * TRANSLATION 
	 * 
	 */

	/**
	 * Translate the given text string into the current language if available. 
	 *
	 * If not available, or if the current language is the native language, then it returns the text as is. 
	 * 
	 * #pw-group-translation
	 *
	 * @param string $text Text string to translate
	 * @return string
	 *
	 */
	public function _($text) {
		return __($text, $this); 
	}

	/**
	 * Perform a language translation in a specific context
	 * 
	 * Used when to text strings might be the same in English, but different in other languages. 
	 * 
	 * #pw-group-translation
	 * 
	 * @param string $text Text for translation. 
	 * @param string $context Name of context
	 * @return string Translated text or original text if translation not available.
	 *
	 */
	public function _x($text, $context) {
		return _x($text, $context, $this); 
	}

	/**
	 * Perform a language translation with singular and plural versions
	 * 
	 * #pw-group-translation
	 * 
	 * @param string $textSingular Singular version of text (when there is 1 item).
	 * @param string $textPlural Plural version of text (when there are multiple items or 0 items).
	 * @param int $count Quantity used to determine whether singular or plural.
	 * @return string Translated text or original text if translation not available.
	 *
	 */
	public function _n($textSingular, $textPlural, $count) {
		return _n($textSingular, $textPlural, $count, $this); 
	}
	
	/*******************************************************************************************************
	 * API VARIABLE MANAGEMENT
	 * 
	 * To replace fuel in PW 3.0
	 *
	 */

	/**
	 * ProcessWire instance
	 *
	 * @var ProcessWire|null
	 *
	 */
	protected $_wire = null;

	/**
	 * Set the current ProcessWire instance for this object (PW 3.0)
	 * 
	 * Specify no arguments to get, or specify a ProcessWire instance to set.
	 * 
	 * #pw-internal
	 *
	 * @param ProcessWire $wire
	 *
	 */
	public function setWire(ProcessWire $wire) {
		$this->_wire = $wire;
		$this->getInstanceNum();
	}

	/**
	 * Get the current ProcessWire instance (PW 3.0)
	 * 
	 * You can also use the wire() method with no arguments.
	 * 
	 * #pw-internal
	 *
	 * @return null|ProcessWire
	 *
	 */
	public function getWire() {
		return $this->_wire;
	}

	/**
	 * Is this object wired to a ProcessWire instance?
	 * 
	 * #pw-internal
	 * 
	 * @return bool
	 * 
	 */
	public function isWired() {
		return $this->_wire ? true : false;
	}
	
	/**
	 * Get an API variable, create an API variable, or inject dependencies.
	 * 
	 * This method provides the following:
	 * 
	 * - Access to API variables:   
	 *   `$pages = $this->wire('pages');`
	 *   
	 * - Access to current ProcessWire instance:   
	 *   `$wire = $this->wire();`
	 *   
	 * - Creating new API variables:   
	 *   `$this->wire('widgets', $widgets);`
	 *   
	 * - Injection of dependencies to Wire derived objects:   
	 *   `$this->wire($widgets);`
	 * 
	 * Most Wire derived objects also support access to API variables directly via `$this->apiVar`. 
	 * 
	 * There is also the `wire()` procedural function, which provides the same access to get API 
	 * variables. Note however the procedural version does not support creating API variables or 
	 * injection of dependencies. 
	 * 
	 * ~~~~~
	 * // Get the 'pages' API variable
	 * $pages = $this->wire('pages');
	 *   
	 * // Get the 'pages' API variable using alternate syntax
	 * $pages = $this->wire()->pages; 
	 *  
	 * // Get all API variables (returns a Fuel object)
	 * $all = $this->wire('all');
	 *   
	 * // Get the current ProcessWire instance (no arguments)
	 * $wire = $this->wire(); 
	 *  
	 * // Create a new API variable named 'widgets'
	 * $this->wire('widgets', $widgets);
	 *  
	 * // Create new API variable and lock it so nothing can overwrite 
	 * $this->wire('widgets', $widgets, true); 
	 *   
	 * // Alternate syntax for the two above
	 * $this->wire()->set('widgets', $widgets);
	 * $this->wire()->set('widgets', $widgets, true); // lock 
	 *   
	 * // Inject dependencies into Wire derived object
	 * $this->wire($widgets); 
	 *   
	 * // Inject dependencies during construct
	 * $newPage = $this->wire(new Page());
	 * ~~~~~
	 *
	 * @param string|object $name Name of API variable to retrieve, set, or omit to retrieve the master ProcessWire object.
	 * @param null|mixed $value Value to set if using this as a setter, otherwise omit.
	 * @param bool $lock When using as a setter, specify true if you want to lock the value from future changes (default=false).
	 * @return ProcessWire|Wire|Session|Page|Pages|Modules|User|Users|Roles|Permissions|Templates|Fields|Fieldtypes|Sanitizer|Config|Notices|WireDatabasePDO|WireHooks|WireDateTime|WireFileTools|WireMailTools|WireInput|string|mixed
	 * @throws WireException
	 *
	 *
	 */
	public function wire($name = '', $value = null, $lock = false) {

		if(is_null($this->_wire)) {
			// this object has not yet been wired! use last known current instance as fallback
			// note this condition is unsafe in multi-instance mode
			$wire = ProcessWire::getCurrentInstance();
			if(!$wire) return null;
			
			// For live hunting objects that are using the fallback, uncomment the following:
			// echo "<hr /><p>Non-wired object: '$name' in " . get_class($this) . ($value ? " (value=$value)" : "") . "</p>";
			// echo "<pre>" . print_r(debug_backtrace(), true) . "</pre>";
		} else {
			// this instance is wired
			$wire = $this->_wire;
		}

		if(is_object($name)) {
			// make an object wired (inject ProcessWire instance to object)
			if($name instanceof WireFuelable) {
				if($this->_wire) $name->setWire($wire); // inject fuel, PW 3.0 
				if(is_string($value) && $value) {
					// set as new API var if API var name specified in $value
					$wire->fuel()->set($value, $name, $lock);
				}
				$value = $name; // return the provided instance
			} else {
				throw new WireException("Wire::wire(\$o) expected WireFuelable for \$o and was given " . get_class($name));
			}

		} else if($value !== null) {
			// setting a API variable/fuel value, and make it wired
			if($value instanceof WireFuelable && $this->_wire) $value->setWire($wire);
			$wire->fuel()->set($name, $value, $lock);
			
		} else if(empty($name)) {
			// return ProcessWire instance
			$value = $wire;
			
		} else if($name === '*' || $name === 'all' || $name == 'fuel') {
			// return Fuel instance
			$value = $wire->fuel();
			
		} else {
			// get API variable
			$value = $wire->fuel()->$name;
		}
		
		return $value;
	}

	/**
	 * Get an object property by direct reference or NULL if it doesn't exist
	 *
	 * If not overridden, this is primarily used as a shortcut for the fuel() method.
	 *
	 * Descending classes may have their own __get() but must pass control to this one when they can't find something.
	 *
	 * @param string $name
	 * @return mixed|null
	 *
	 */
	public function __get($name) {

		if($name == 'wire') return $this->wire();
		if($name == 'fuel') return $this->wire('fuel');
		if($name == 'className') return $this->className();

		if($this->useFuel()) {
			$value = $this->wire($name);
			if($value !== null) return $value; 
		}

		$hooks = $this->wire('hooks');
		if($hooks && $hooks->isHooked($name)) { // potential property hook
			$result = $this->runHooks($name, array(), 'property');
			return $result['return'];
		}

		return null;
	}

	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * This is used when you print_r() an object instance.
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		/** @var WireDebugInfo $debugInfo */
		$debugInfo = $this->wire(new WireDebugInfo());
		return $debugInfo->getDebugInfo($this, true);
	}

	/**
	 * Minimal/small debug info
	 * 
	 * Same as __debugInfo() but with no hooks info, no change tracking info, and less verbose 
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * @since 3.0.130
	 * 
	 */
	public function debugInfoSmall() {
		/** @var WireDebugInfo $debugInfo */
		$debugInfo = $this->wire(new WireDebugInfo());
		return $debugInfo->getDebugInfo($this, true);
	}


}

