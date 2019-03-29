<?php namespace ProcessWire;

/**
 * ProcessWire Hooks Manager
 * 
 * This class is for internal use. You should manipulate hooks from Wire-derived classes instead. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireHooks {

	/**
	 * Debug hooks
	 *
	 */
	const ___debug = false;

	/**
	 * Refers to ALL hooks
	 * 
	 */
	const getHooksAll = 0;
	
	/**
	 * Refers only to LOCAL hooks
	 *
	 */
	const getHooksLocal = 1;
	
	/**
	 * Refers only to STATIC hooks
	 *
	 */
	const getHooksStatic = 2;

	/**
	 * When a hook is specified, there are a few options which can be overridden: This array outlines those options and the defaults.
	 *
	 * - type: may be either 'method' or 'property'. If property, then it will respond to $obj->property rather than $obj->method().
	 * - before: execute the hook before the method call? Not applicable if 'type' is 'property'.
	 * - after: execute the hook after the method call? (allows modification of return value). Not applicable if 'type' is 'property'.
	 * - priority: a number determining the priority of a hook, where lower numbers are executed before higher numbers.
	 * - allInstances: attach the hook to all instances of this object? (store in staticHooks rather than localHooks). Set automatically, but you may still use in some instances.
	 * - fromClass: the name of the class containing the hooked method, if not the object where addHook was executed. Set automatically, but you may still use in some instances.
	 * - argMatch: array of Selectors objects where the indexed argument (n) to the hooked method must match, order to execute hook.
	 * - objMatch: Selectors object that the current object must match in order to execute hook
	 * - public: auto-assigned to true or false by addHook() as to whether the method is public or private/protected.
	 *
	 */
	protected $defaultHookOptions = array(
		'type' => 'method',
		'before' => false,
		'after' => true,
		'priority' => 100,
		'allInstances' => false,
		'fromClass' => '',
		'argMatch' => null,
		'objMatch' => null,
	);

	/**
	 * Static hooks are applicable to all instances of the descending class.
	 *
	 * This array holds references to those static hooks, and is shared among all classes descending from Wire.
	 * It is for internal use only. See also $defaultHookOptions[allInstances].
	 *
	 */
	protected $staticHooks = array();

	/**
	 * A cache of all hook method/property names for an optimization.
	 *
	 * Hooked methods end with '()' while hooked properties don't.
	 *
	 * This does not distinguish which instance it was added to or whether it was removed.
	 * This cache exists primarily to gain some speed in our __get and __call methods.
	 *
	 */
	protected $hookMethodCache = array();

	/**
	 * Same as hook method cache but for "Class::method"
	 * 
	 * @var array
	 * 
	 */
	protected $hookClassMethodCache = array();

	/**
	 * Cache of all local hooks combined, for debugging purposes
	 *
	 */
	protected $allLocalHooks = array();

	/**
	 * Cached parent classes and interfaces
	 * 
	 * @var array of class|interface => [ 'parentClass', 'parentClass', 'interface', 'interface', 'etc.' ]
	 * 
	 */
	protected $parentClasses = array();
	
	/**
	 * @var Config
	 * 
	 */
	protected $config;

	/**
	 * @var array
	 * 
	 */
	protected $debugTimers = array();

	/**
	 * @var ProcessWire
	 * 
	 */
	protected $wire;

	/**
	 * Construct WireHooks
	 * 
	 * @param ProcessWire $wire
	 * @param Config $config
	 * 
	 */
	public function __construct(ProcessWire $wire, Config $config) {
		$this->wire = $wire;
		$this->config = $config;
	}
	
	/**
	 * Return all hooks associated with $object or method (if specified)
	 *
	 * @param Wire $object
	 * @param string $method Optional method that hooks will be limited to. Or specify '*' to return all hooks everywhere.
	 * @param int $getHooks Get hooks of type, specify one of the following constants:
	 * 	- WireHooks::getHooksAll returns all hooks [0] (default)
	 * 	- WireHooks::getHooksLocal returns local hooks [1] only
	 * 	- WireHooks::getHooksStatic returns static hooks [2] only
	 * @return array
	 *
	 */
	public function getHooks(Wire $object, $method = '', $getHooks = self::getHooksAll) {

		$hooks = array();

		// see if we can do a quick exit
		if($method && $method !== '*' && !$this->isHookedOrParents($object, $method)) return $hooks;

		// first determine which local hooks when should include
		if($getHooks !== self::getHooksStatic) {
			$localHooks = $object->getLocalHooks();
			if($method && $method !== '*') {
				// populate all local hooks for given method
				if(isset($localHooks[$method])) $hooks = $localHooks[$method];
			} else {
				// populate all local hooks, regardless of method
				// note: sort of return hooks is no longer priority based
				// @todo account for '*' method, which should return all hooks regardless of instance
				foreach($localHooks as $method => $methodHooks) {
					$hooks = array_merge(array_values($hooks), array_values($methodHooks));
				}
			}
		}

		// if only local hooks requested, we can return them now
		if($getHooks === self::getHooksLocal) return $hooks;

		$needSort = false;
		$namespace = __NAMESPACE__ ? __NAMESPACE__ . "\\" : "";
		$objectParentNamespaces = array();

		// join in static hooks
		foreach($this->staticHooks as $className => $staticHooks) {
			$_className = $namespace . $className;
			if(!$object instanceof $_className && $method !== '*') {
				$_namespace = wireClassName($object, 1) . "\\";
				if($_namespace !== $namespace) {
					// objects in other namespaces
					$_className = $_namespace . $className;
					if(!$object instanceof $_className && $method !== '*') {
						// object likely extends a class not in PW namespace, so check class parents instead
						if(empty($objectParentNamespaces)) {
							foreach(wireClassParents($object) as $nscn => $cn) {
								list($ns,) = explode("\\", $nscn); 
								$objectParentNamespaces[$ns] = $ns;	
							}
						}
						$nsok = false;
						foreach($objectParentNamespaces as $ns) {
							$_className = "$ns\\$className";
							if(!$object instanceof $_className) continue;
							$nsok = true;
							break;
						}
						if(!$nsok) continue;
					}
				} else {
					continue;
				}
			}
			// join in any related static hooks to the local hooks
			if($method && $method !== '*') {
				// retrieve all static hooks for method
				if(!empty($staticHooks[$method])) {
					if(count($hooks)) {
						$collisions = array_intersect_key($hooks, $staticHooks[$method]);
						$hooks = array_merge($hooks, $staticHooks[$method]);
						if(count($collisions)) {
							// identify and resolve priority collisions
							foreach($collisions as $priority => $hook) {
								$n = 0;
								while(isset($hooks["$priority.$n"])) $n++;
								$hooks["$priority.$n"] = $hook;
							}
						}
						$needSort = true;
					} else {
						$hooks = $staticHooks[$method];
					}
				}
			} else {
				// no method specified, retrieve all for class
				// note: priority-based array indexes are no longer in tact
				$hooks = array_values($hooks);
				foreach($staticHooks as $_method => $methodHooks) {
					$hooks = array_merge($hooks, array_values($methodHooks));
				}
			}
		}

		if($needSort && count($hooks) > 1) {
			defined("SORT_NATURAL") ? ksort($hooks, SORT_NATURAL) : uksort($hooks, "strnatcmp");
		}
		
		return $hooks;
	}

	/**
	 * Returns true if the method/property hooked, false if it isn't.
	 *
	 * This is for optimization use. It does not distinguish about class instance.
	 * It only distinguishes about class if you provide a class with the $method argument (i.e. Class::).
	 * As a result, a true return value indicates something "might" be hooked, as opposed to be
	 * being definitely hooked.
	 *
	 * If checking for a hooked method, it should be in the form `Class::method()` or `method()` (with parenthesis).
	 * If checking for a hooked property, it should be in the form `Class::property` or `property`.
	 * 
	 * If you need to check if a method/property is hooked, including any of its parent classes, use
	 * the `WireHooks::isMethodHooked()`, `WireHooks::isPropertyHooked()`, or `WireHooks::hasHook()` methods instead. 
	 *
	 * @param string $method Method or property name in one of the following formats:
	 * 	Class::method()
	 * 	Class::property
	 * 	method()
	 * 	property
	 * @param Wire|null $instance Optional instance to check against (see hasHook method for details)
	 * 	Note that if specifying an $instance, you may not use the Class::method() or Class::property options for $method argument.
	 * @return bool
	 * @see WireHooks::isMethodHooked(), WireHooks::isPropertyHooked(), WireHooks::hasHook()
	 *
	 */
	public function isHooked($method, Wire $instance = null) {
		if($instance) return $this->hasHook($instance, $method);
		if(strpos($method, ':') !== false) {
			$hooked = isset($this->hookClassMethodCache[$method]); // fromClass::method() or fromClass::property
		} else {
			$hooked = isset($this->hookMethodCache[$method]); // method() or property
		}
		return $hooked;
	}

	/**
	 * Similar to isHooked() method but also checks parent classes for the hooked method as well 
	 * 
	 * This method is designed for fast determinations of whether something is hooked
	 * 
	 * @param string|Wire $class
	 * @param string $method Name of method or property
	 * @param string $type May be either 'method', 'property' or 'either'
	 * @return bool
	 * 
	 */
	protected function isHookedOrParents($class, $method, $type = 'either') {
		
		$property = '';
		if(is_object($class)) {
			$className = wireClassName($class);
			$object = $class;
		} else {
			$className = $class;
			$object = null;
		}
		
		if($object) {
			// first check local hooks attached to this instance
			$localHooks = $object->getLocalHooks();
			if(!empty($localHooks[rtrim($method, '()')])) {
				return true;
			}
		}

		if($type == 'method' || $type == 'either') {
			if(strpos($method, '(') === false) $method .= '()';
			if($type == 'either') $property = rtrim($method, '()');
		} else {
			$property = rtrim($method, '()');
		}

		if($type == 'method') {
			if(!isset($this->hookMethodCache[$method])) return false; // not hooked for any class
			$hooked = isset($this->hookClassMethodCache["$className::$method"]);
		} else if($type == 'property') {
			if(!isset($this->hookMethodCache[$property])) return false; // not hooked for any class
			$hooked = isset($this->hookClassMethodCache["$className::$property"]);
		} else {
			if(!isset($this->hookMethodCache[$method]) 
				&& !isset($this->hookMethodCache[$property])) return false;
			$hooked = isset($this->hookClassMethodCache["$className::$property"]) || 
				isset($this->hookClassMethodCache["$className::$method"]);
		}
		
		if(!$hooked) {
			foreach($this->getClassParents($class) as $parentClass) {
				if($type == 'method') {
					if(isset($this->hookClassMethodCache["$parentClass::$method"])) {
						$hooked = true;
						$this->hookClassMethodCache["$class::$method"] = true;
					}
				} else if($type == 'property') {
					if(isset($this->hookClassMethodCache["$parentClass::$property"])) {
						$hooked = true;
						$this->hookClassMethodCache["$class::$property"] = true;
					}
				} else {
					if(isset($this->hookClassMethodCache["$parentClass::$method"])) {
						$hooked = true;
						$this->hookClassMethodCache["$class::$method"] = true;
					}
					if(!$hooked && isset($this->hookClassMethodCache["$parentClass::$property"])) {
						$hooked = true;
						$this->hookClassMethodCache["$class::$property"] = true;
					}
				}
				if($hooked) break;	
			}
		}
		
		return $hooked;
	}

	/**
	 * Similar to isHooked() method but also checks parent classes for the hooked method as well
	 *
	 * This method is designed for fast determinations of whether something is hooked
	 *
	 * @param string|Wire $class
	 * @param string $method Name of method
	 * @return bool
	 *
	 */
	public function isMethodHooked($class, $method) {
		return $this->isHookedOrParents($class, $method, 'method');
	}

	/**
	 * Similar to isHooked() method but also checks parent classes for the hooked property as well
	 *
	 * This method is designed for fast determinations of whether something is hooked
	 *
	 * @param string|Wire $class
	 * @param string $property Name of property
	 * @return bool
	 *
	 */
	public function isPropertyHooked($class, $property) {
		return $this->isHookedOrParents($class, $property, 'property');
	}
	
	/**
	 * Similar to isHooked(), returns true if the method or property hooked, false if it isn't.
	 *
	 * Accomplishes the same thing as the isHooked() method, but this is more accurate,
	 * and potentially slower than isHooked(). Less for optimization use, more for accuracy use.
	 *
	 * It checks for both static hooks and local hooks, but only accepts a method() or property
	 * name as an argument (i.e. no Class::something) since the class context is assumed from the current
	 * instance. Unlike isHooked() it also analyzes the instance's class parents for hooks, making it
	 * more accurate. As a result, this method works well for more than just optimization use.
	 *
	 * If checking for a hooked method, it should be in the form "method()".
	 * If checking for a hooked property, it should be in the form "property".
	 *
	 * @param Wire $object
	 * @param string $method Method() or property name
	 * @return bool
	 * @throws WireException whe you try to call it with a Class::something() type method.
	 * @todo differentiate between "method()" and "property"
	 *
	 */
	public function hasHook(Wire $object, $method) {

		$hooked = false;
		if(strpos($method, '::') !== false) {
			throw new WireException("You may only specify a 'method()' or 'property', not 'Class::something'.");
		}

		// quick exit when possible
		if(!isset($this->hookMethodCache[$method])) return false;

		$_method = rtrim($method, '()');
		$localHooks = $object->getLocalHooks();

		if(!empty($localHooks[$_method])) {
			// first check local hooks attached to this instance
			$hooked = true;
		} else if(!empty($this->staticHooks[$object->className()][$_method])) {
			// now check if hooked in this class
			$hooked = true;
		} else {
			// check parent classes and interfaces
			foreach($this->getClassParents($object) as $class) {
				if(!empty($this->staticHooks[$class][$_method])) {
					$hooked = true;
					$this->hookClassMethodCache["$class::$method"] = true;
					break;
				}
			}
		}

		return $hooked;
	}

	/**
	 * Get an array of parent classes and interfaces for the given object
	 * 
	 * @param Wire|string $object Maybe either object instance or class name
	 * @param bool $cache Allow use of cache for getting or storing? (default=true)
	 * @return array
	 * 
	 */
	public function getClassParents($object, $cache = true) {
		if(is_string($object)) {
			$className = $object;
		} else {
			$className = $object->className();
		}
		if($cache && isset($this->parentClasses[$className])) {
			$classes = $this->parentClasses[$className];
		} else {
			$classes = wireClassParents($object, false);
			$interfaces = wireClassImplements($object);
			if(is_array($interfaces)) $classes = array_merge($interfaces, $classes);
			if($cache) $this->parentClasses[$className] = $classes;
		}
		return $classes;
	}


	/**
	 * Hook a function/method to a hookable method call in this object
	 *
	 * Hookable method calls are methods preceded by three underscores.
	 * You may also specify a method that doesn't exist already in the class
	 * The hook method that you define may be part of a class or a globally scoped function.
	 *
	 * If you are hooking a procedural function, you may omit the $toObject and instead just call via:
	 * $this->addHook($method, 'function_name'); or $this->addHook($method, 'function_name', $options);
	 *
	 * @param Wire $object
	 * @param string $method Method name to hook into, NOT including the three preceding underscores.
	 * 	May also be Class::Method for same result as using the fromClass option.
	 * @param object|null|callable $toObject Object to call $toMethod from,
	 * 	Or null if $toMethod is a function outside of an object,
	 * 	Or function|callable if $toObject is not applicable or function is provided as a closure.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event, or $options array. 
	 * @param array $options See $defaultHookOptions at the beginning of this class. Optional.
	 * @return string A special Hook ID that should be retained if you need to remove the hook later
	 * @throws WireException
	 *
	 */
	public function addHook(Wire $object, $method, $toObject, $toMethod = null, $options = array()) {
		
		if(is_array($toMethod)) {
			// $options array specified as 3rd argument
			if(count($options)) {
				// combine $options from addHookBefore/After and user specified options
				$options = array_merge($toMethod, $options);
			} else {
				$options = $toMethod;
			}
			$toMethod = null;
		}

		if(is_null($toMethod)) {
			// $toObject has been omitted and a procedural function specified instead
			// $toObject may also be a closure
			$toMethod = $toObject;
			$toObject = null;
		}
		
		$options = array_merge($this->defaultHookOptions, $options);

		if(is_null($toMethod)) throw new WireException("Method to call is required and was not specified (toMethod)");
		if(strpos($method, '___') === 0) $method = substr($method, 3);
		if(method_exists($object, $method)) throw new WireException("Method " . $object->className() . "::$method is not hookable");
	
		// determine whether the hook handling method is public or private/protected
		$toPublic = true; 
		if($toObject) {
			if(method_exists($toObject, $toMethod)) $_toMethod = $toMethod;
				else if(method_exists($toObject, "___$toMethod")) $_toMethod = "___$toMethod";
				else $_toMethod = null;
			if($_toMethod) {
				try {
					$ref = new \ReflectionMethod($toObject, $_toMethod);
					$toPublic = $ref->isPublic();
				} catch(\Exception $e) {
					$toPublic = false;
				}
			}
			unset($ref);
		}
		
		if(strpos($method, '::')) {
			list($fromClass, $method) = explode('::', $method, 2);
			if(strpos($fromClass, '(') !== false) {
				// extract object selector match string
				list($fromClass, $objMatch) = explode('(', $fromClass, 2);
				$objMatch = trim($objMatch, ') ');
				if(Selectors::stringHasSelector($objMatch)) {
					$selectors = $this->wire->wire(new Selectors());
					$selectors->init($objMatch);
					$objMatch = $selectors;
				}
				if($objMatch) $options['objMatch'] = $objMatch;
			}
			$options['fromClass'] = $fromClass;
		}

		$argOpen = strpos($method, '(');
		if($argOpen) { 
			// arguments to match may be specified in method name
			$argClose = strpos($method, ')'); 
			if($argClose === $argOpen+1) {
				// method just has a "()" which can be discarded
				$method = rtrim($method, '() ');
			} else if($argClose > $argOpen+1) {
				// extract argument selector match string(s), arg 0: Something::something(selector_string)
				// or: Something::something(1:selector_string, 3:selector_string) matches arg 1 and 3. 
				list($method, $argMatch) = explode('(', $method, 2);
				$argMatch = trim($argMatch, ') ');
				if(strpos($argMatch, ':') !== false) {
					// zero-based argument indexes specified, i.e. 0:template=product, 1:order_status
					$args = preg_split('/\b([0-9]):/', trim($argMatch), -1, PREG_SPLIT_DELIM_CAPTURE);
					if(count($args)) {
						$argMatch = array();
						array_shift($args); // blank
						while(count($args)) {
							$argKey = (int) trim(array_shift($args));
							$argVal = trim(array_shift($args), ', ');
							$argMatch[$argKey] = $argVal;
						}
					}
				} else {
					// just single argument specified, so argument 0 is assumed
				}
				if(is_string($argMatch)) $argMatch = array(0 => $argMatch);
				foreach($argMatch as $argKey => $argVal) {
					if(Selectors::stringHasSelector($argVal)) {
						$selectors = $this->wire->wire(new Selectors());
						$selectors->init($argVal);
						$argMatch[$argKey] = $selectors;
					}
				}
				if(count($argMatch)) $options['argMatch'] = $argMatch;
			}
		}
		
		$localHooks = $object->getLocalHooks();
		
		if($options['allInstances'] || $options['fromClass']) {
			// hook all instances of this class
			$hookClass = $options['fromClass'] ? $options['fromClass'] : $object->className();
			if(!isset($this->staticHooks[$hookClass])) $this->staticHooks[$hookClass] = array();
			$hooks =& $this->staticHooks[$hookClass];
			$options['allInstances'] = true;
			$local = 0;

		} else {
			// hook only this instance
			$hookClass = '';
			$hooks =& $localHooks;
			$local = 1;
		}

		$priority = (string) $options['priority'];
		
		if(!isset($hooks[$method])) {
			if(ctype_digit($priority)) $priority = "$priority.0";
			
		} else {
			if(strpos($priority, '.')) {
				// priority already specifies a sub value: extract it
				list($priority, $n) = explode('.', $priority);
				$options['priority'] = $priority; // without $n
				$priority .= ".$n";
			} else {
				$n = 0;
				$priority .= ".0";
			}
			// come up with a priority that is unique for this class/method across both local and static hooks
			while(($hookClass && isset($this->staticHooks[$hookClass][$method][$priority]))
				|| isset($localHooks[$method][$priority])) {
				$n++;
				$priority = "$options[priority].$n";
			}
		}
	
		// Note hookClass is always blank when this is a local hook
		$id = "$hookClass:$priority:$method";
		$options['priority'] = $priority;
		
		$hook = array(
			'id' => $id,
			'method' => $method,
			'toObject' => $toObject,
			'toMethod' => $toMethod,
			'toPublic' => $toPublic, 
			'options' => $options,
		);
		$hooks[$method][$priority] = $hook;

		// cache record known hooks so they can be detected quickly
		$cacheValue = $options['type'] == 'method' ? "$method()" : "$method";
		if($options['fromClass']) $this->hookClassMethodCache["$options[fromClass]::$cacheValue"] = true;
		$this->hookMethodCache[$cacheValue] = true;
		if($options['type'] === 'either') {
			$cacheValue = "$cacheValue()";
			$this->hookMethodCache[$cacheValue] = true;
			if($options['fromClass']) $this->hookClassMethodCache["$options[fromClass]::$cacheValue"] = true;
		}

		// keep track of all local hooks combined when debug mode is on
		if($local && $this->config->debug) {
			$debugClass = $object->className();
			$debugID = ($local ? $debugClass : '') . $id;
			while(isset($this->allLocalHooks[$debugID])) $debugID .= "_";
			$debugHook = $hooks[$method][$priority];
			$debugHook['method'] = $debugClass . "->" . $debugHook['method'];
			$this->allLocalHooks[$debugID] = $debugHook;
		}

		// sort by priority, if more than one hook for the method
		if(count($hooks[$method]) > 1) {
			if(defined("SORT_NATURAL")) {
				ksort($hooks[$method], SORT_NATURAL);
			} else {
				uksort($hooks[$method], "strnatcmp");
			}
		}
		
		if($local) {
			$object->setLocalHooks($hooks);
		}

		return $id;
	}


	/**
	 * Provides the implementation for calling hooks in ProcessWire
	 *
	 * Unlike __call, this method won't trigger an Exception if the hook and method don't exist.
	 * Instead it returns a result array containing information about the call.
	 *
	 * @param Wire $object
	 * @param string $method Method or property to run hooks for.
	 * @param array $arguments Arguments passed to the method and hook.
	 * @param string|array $type May be any one of the following: 
	 *  - method: for hooked methods (default)
	 *  - property: for hooked properties
	 *  - before: only run before hooks and do nothing else
	 *  - after: only run after hooks and do nothing else
	 *  - Or array[] of hooks (from getHooks method) to run (does not call hooked method)
	 * @return array Returns an array with the following information:
	 * 	[return] => The value returned from the hook or NULL if no value returned or hook didn't exist.
	 *	[numHooksRun] => The number of hooks that were actually run.
	 *	[methodExists] => Did the hook method exist as a real method in the class? (i.e. with 3 underscores ___method).
	 *	[replace] => Set by the hook at runtime if it wants to prevent execution of the original hooked method.
	 *
	 */
	public function runHooks(Wire $object, $method, $arguments, $type = 'method') {

		$hookTimer = self::___debug ? $this->hookTimer($object, $method, $arguments) : null;
		$realMethod = "___$method";
		$cancelHooks = false;
		$profiler = $this->wire->wire('profiler');
		$hooks = null;
		
		if(is_array($type)) {
			// array of hooks to run provided in $type argument
			$hooks = $type;
			$type = 'custom';
		}

		$result = array(
			'return' => null,
			'numHooksRun' => 0,
			'methodExists' => ($type === 'method' ? method_exists($object, $realMethod) : false),
			'replace' => false,
		);
		
		if($type === 'method' || $type === 'property' || $type === 'either') {
			if(!$result['methodExists'] && !$this->isHookedOrParents($object, $method, $type)) {
				return $result; // exit quickly when we can
			}
		}
		
		if($hooks === null) $hooks = $this->getHooks($object, $method);
	
		foreach(array('before', 'after') as $when) {

			if($type === 'method') {
				if($when === 'after' && $result['replace'] !== true) {
					if($result['methodExists']) {
						$result['return'] = $object->_callMethod($realMethod, $arguments);
					} else {
						$result['return'] = null;
					}
				}
			} else if($type === 'after') {
				if($when === 'before') continue;
			} else if($type === 'before') {
				if($when === 'after') break;
			}

			foreach($hooks as $priority => $hook) {

				if(!$hook['options'][$when]) continue;
				if($type === 'property' && $hook['options']['type'] === 'method') continue;
				if($type === 'method' && $hook['options']['type'] === 'property') continue;

				if(!empty($hook['options']['objMatch'])) {
					/** @var Selectors $objMatch */
					$objMatch = $hook['options']['objMatch'];
					// object match comparison to determine at runtime whether to execute the hook
					if(is_object($objMatch)) {
						if(!$objMatch->matches($object)) continue;
					} else {
						if(((string) $object) != $objMatch) continue;
					}
				}

				if($type == 'method' && !empty($hook['options']['argMatch'])) {
					// argument comparison to determine at runtime whether to execute the hook
					$argMatches = $hook['options']['argMatch'];
					$matches = true;
					foreach($argMatches as $argKey => $argMatch) {
						/** @var Selectors $argMatch */
						$argVal = isset($arguments[$argKey]) ? $arguments[$argKey] : null;
						if(is_object($argMatch)) {
							// Selectors object
							if(is_object($argVal)) {
								$matches = $argMatch->matches($argVal);
							} else {
								// we don't work with non-object here
								$matches = false;
							}
						} else {
							if(is_array($argVal)) {
								// match any array element
								$matches = in_array($argMatch, $argVal);
							} else {
								// exact string match
								$matches = $argMatch == $argVal;
							}
						}
						if(!$matches) break;
					}
					if(!$matches) continue; // don't run hook
				}

				$event = new HookEvent(array(
					'object' => $object,
					'method' => $method,
					'arguments' => $arguments,
					'when' => $when,
					'return' => $result['return'],
					'id' => $hook['id'],
					'options' => $hook['options']
				));
				$this->wire->wire($event);

				$toObject = $hook['toObject'];
				$toMethod = $hook['toMethod'];
			
				if($profiler) {
					$profilerEvent = $profiler->start($hook['id'], $this, array(
						'event' => $event, 
						'hook' => $hook,
					));
				} else {
					$profilerEvent = false;
				}

				if(is_null($toObject)) {
					$toMethodCallable = is_callable($toMethod);
					if(!$toMethodCallable && strpos($toMethod, "\\") === false && __NAMESPACE__) {
						$_toMethod = $toMethod;
						$toMethod = "\\" . __NAMESPACE__ . "\\$toMethod";
						$toMethodCallable = is_callable($toMethod);
						if(!$toMethodCallable) {
							$toMethod = "\\$_toMethod";
							$toMethodCallable = is_callable($toMethod);
						}
					}
					if($toMethodCallable) {
						$returnValue = $toMethod($event);
					} else {
						// hook fail, not callable
						$returnValue = null;
					}
				} else {
					/** @var Wire $toObject */
					if($hook['toPublic']) {
						// public
						$returnValue = $toObject->$toMethod($event);
					} else {
						// protected or private
						$returnValue = $toObject->_callMethod($toMethod, array($event));
					}
					$toMethodCallable = true; 
				}

				if($returnValue !== null) {
					// hook method/func had an explicit return statement with a value
					// allow for use of $returnValue as alternative to $event->return?
				}
				
				if($profilerEvent) $profiler->stop($profilerEvent);
				
				if(!$toMethodCallable) continue;

				$result['numHooksRun']++;
				
				if($event->cancelHooks === true) $cancelHooks = true;

				if($when == 'before') {
					$arguments = $event->arguments;
					$result['replace'] = $event->replace === true || $result['replace'] === true;
					if($result['replace']) $result['return'] = $event->return;
				}

				if($when == 'after') $result['return'] = $event->return;
				if($cancelHooks) break;
			}
			if($cancelHooks) break;
		}
		
		if($hookTimer) Debug::saveTimer($hookTimer);

		return $result;
	}

	/**
	 * Filter and return hooks matching given property and value
	 * 
	 * @param array $hooks Hooks from getHooks() method
	 * @param string $property Property name from hook (or hook options)
	 * @param string|bool|int $value Value to match
	 * @return array
	 * 
	 */
	public function filterHooks(array $hooks, $property, $value) {
		foreach($hooks as $key => $hook) {
			if(array_key_exists($property, $hook)) {
				if($hook[$property] !== $value) unset($hooks[$key]); 
			} else if(array_key_exists($property, $hook['options'])) {
				if($hook['options'][$property] !== $value) unset($hooks[$key]); 
			}
		}
		return $hooks;	
	}

	/**
	 * Start timing a hook and return the timer name
	 * 
	 * @param Wire $object
	 * @param String $method
	 * @param array $arguments
	 * @return string
	 * 
	 */
	protected function hookTimer($object, $method, $arguments) {
		$timerName = $object->className() . "::$method";
		$notes = array();
		foreach($arguments as $argument) {
			if(is_object($argument)) $notes[] = get_class($argument);
			else if(is_array($argument)) $notes[] = "array(" . count($argument) . ")";
			else if(strlen($argument) > 20) $notes[] = substr($argument, 0, 20) . '...';
		}
		$timerName .= "(" . implode(', ', $notes) . ")";
		if(isset($this->debugTimers[$timerName])) {
			$this->debugTimers[$timerName]++;
			$timerName .= " #" . $this->debugTimers[$timerName];
		} else {
			$this->debugTimers[$timerName] = 1;
		}
		Debug::timer($timerName);
		return $timerName;
	}

	/**
	 * Given a Hook ID provided by addHook() this removes the hook
	 *
	 * To have a hook function remove itself within the hook function, say this is your hook function:
	 * function(HookEvent $event) {
	 *   $event->removeHook(null); // remove self
	 * }
	 *
	 * @param Wire $object
	 * @param string|null $hookID
	 * @return Wire
	 *
	 */
	public function removeHook(Wire $object, $hookID) {
		if(!empty($hookID) && strpos($hookID, ':')) {
			list($hookClass, $priority, $method) = explode(':', $hookID);
			if(empty($hookClass)) {
				$localHooks = $object->getLocalHooks();
				unset($localHooks[$method][$priority]);
				$object->setLocalHooks($localHooks);
			} else {
				unset($this->staticHooks[$hookClass][$method][$priority]);
				if(empty($this->staticHooks[$hookClass][$method])) {
					unset($this->hookClassMethodCache["$hookClass::$method"]);
				}
			}
		}
		return $object;
	}

	/**
	 * Return the "all local hooks" cache
	 * 
	 * @return array
	 * 
	 */
	public function getAllLocalHooks() {
		return $this->allLocalHooks;
	}

	/**
	 * @return string
	 * 
	 */
	public function className() {
		return wireClassName($this, false);
	}
	
	public function __toString() {
		return $this->className();
	}

}