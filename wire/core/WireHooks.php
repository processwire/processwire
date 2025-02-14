<?php namespace ProcessWire;

/**
 * ProcessWire Hooks Manager
 * 
 * This class is for internal use. You should manipulate hooks from Wire-derived classes instead. 
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
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
	 * - retMatch: Selectors object that must match the return value, or a match string to match return value
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
		'argMatchType' => [], 
		'objMatch' => null,
		'retMatch' => null,
		'retMatchType' => '', 
	);

	/**
	 * Static hooks are applicable to all instances of the descending class.
	 *
	 * This array holds references to those static hooks, and is shared among all classes descending from Wire.
	 * It is for internal use only. See also $defaultHookOptions[allInstances].
	 *
	 */
	protected $staticHooks = array(
		// 'SomeClass' => [
		//   'someMethod' => [ hooks ],
		//   'someOtherMethod' => [ hooks ]
		// ],
		// 'AnotherClass' => [
		//   'anotherMethod' => [ hooks ] 
		// ]
	);

	/**
	 * @var array
	 * 
	 */
	protected $pathHooks = array(
		// 'HookID' => [
		//    'match' => '/foo/bar/{baz}/(.+)/', 
		//    'filters' => [ 0 => '/foo/', 2 => '/bar/' ], 
		//   ], ... 
		// ]
	);

	/**
	 * A cache of all hook method/property names for an optimization.
	 *
	 * Hooked methods end with '()' while hooked properties don't.
	 *
	 * This does not distinguish which instance it was added to or whether it was removed.
	 * This cache exists primarily to gain some speed in our __get and __call methods.
	 *
	 */
	protected $hookMethodCache = array(
		// 'method()' => true,
		// 'property' => true, 
	);

	/**
	 * Same as hook method cache but for "Class::method"
	 * 
	 * @var array
	 * 
	 */
	protected $hookClassMethodCache = array(
		// 'Class::method()' => true, 
		// 'Class::property' => true, 
	);

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
	 * Characters that can begin a path hook definition (i.e. '/path/' or '!regex!', etc.)
	 * 
	 * @var string
	 * 
	 */
	protected $pathHookStarts = '/!@#%.([^';

	/**
	 * Allow use of path hooks?
	 * 
	 * This should be set to false once reaching the boot stage where it no longer applies. 
	 * 
	 * @var bool
	 * 
	 */
	protected $allowPathHooks = true;

	/**
	 * Populated when a path hook requires a redirect
	 * 
	 * @var string
	 * 
	 */
	protected $pathHookRedirect = '';

	/**
	 * @var ProcessWire
	 * 
	 */
	protected $wire;

	/**
	 * Conditional argument match types and the PHP function to detect them
	 * 
	 * @var string[] 
	 * 
	 */
	protected $argMatchTypes = array(
		'array' => 'is_array',
		'bool' => 'is_bool',
		'float' => 'is_float',
		'int' => 'is_int',
		'null' => 'is_null',
		'object' => 'is_object',
		'string' => 'is_string',
	);

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
					if(!$object instanceof $_className) { // && $method !== '*') {
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
				foreach($staticHooks as /* $_method => */ $methodHooks) {
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
	public function isHooked($method, ?Wire $instance = null) {
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
	 * @param string|array $method Method name to hook into, NOT including the three preceding underscores.
	 * 	May also be Class::Method for same result as using the fromClass option.
	 *  May also be array OR CSV string of either of the above to add multiple (since 3.0.137). 
	 * @param object|null|callable $toObject Object to call $toMethod from,
	 * 	Or null if $toMethod is a function outside of an object,
	 * 	Or function|callable if $toObject is not applicable or function is provided as a closure.
	 * @param string|array $toMethod Method from $toObject, or function name to call on a hook event, or $options array. 
	 * @param array $options See $defaultHookOptions at the beginning of this class. Optional.
	 * @return string A special Hook ID that should be retained if you need to remove the hook later.
	 *  If the $method argument was a CSV string or array of multiple methods to hook, then CSV string of hook IDs 
	 *  will be returned, and the same CSV string can be used with removeHook() calls. (since 3.0.137). 
	 * @throws WireException
	 *
	 */
	public function addHook(Wire $object, $method, $toObject, $toMethod = null, $options = array()) {
		
		if(empty($options['noAddHooks']) && (is_array($method) || strpos($method, ',') !== false)) {
			// potentially multiple methods to hook in $method argument
			return $this->addHooks($object, $method, $toObject, $toMethod, $options);
		}
		
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

		if($toMethod === null) {
			// $toObject has been omitted and a procedural function specified instead
			// $toObject may also be a closure
			$toMethod = $toObject;
			$toObject = null;
		}
		
		if($toMethod === null) {
			throw new WireException("Method to call is required and was not specified (toMethod)");
		}
		
		if(strpos($method, '___') === 0) {
			$method = substr($method, 3);
		} else if(strpos($this->pathHookStarts, $method[0]) !== false) {
			return $this->addPathHook($object, $method, $toObject, $toMethod, $options);
		}
		
		if(method_exists($object, $method)) {
			throw new WireException("Method " . $object->className() . "::$method is not hookable");
		}
		
		$options = array_merge($this->defaultHookOptions, $options);
	
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
					/** @var Selectors $selectors */
					$selectors = $this->wire->wire(new Selectors());
					$selectors->init($objMatch);
					$objMatch = $selectors;
				}
				if($objMatch) $options['objMatch'] = $objMatch;
			}
			$options['fromClass'] = $fromClass;
		}

		$retMatch = '';
		$argOpen = strpos($method, '(');
	
		if($argOpen) {
			if(strpos($method, ':(')) {
				list($method, $retMatch) = explode(':(', $method, 2);
				$retMatch = rtrim($retMatch, ') ');
			} else if(strpos($method, ':<') && substr(trim($method), -1) === '>') {
				list($method, $retMatch) = explode(':<', $method, 2);
				$retMatch = "<$retMatch";
			}
			$argOpen = strpos($method, '(');
		}
		
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
				$argMatchType = [];
				foreach($argMatch as $argKey => $argVal) {
					list($argVal, $argValType) = $this->prepareArgMatch($argVal);
					$argMatch[$argKey] = $argVal;
					$argMatchType[$argKey] = $argValType;
				}
				if(count($argMatch)) {
					$options['argMatch'] = $argMatch;
					$options['argMatchType'] = $argMatchType;
				}
			}
		} else if(strpos($method, ':')) {
			list($method, $retMatch) = explode(':', $method, 2);
		}
		
		if($retMatch) {
			// match return value
			if($options['before'] && !$options['after']) {
				throw new WireException('You cannot match return values with “before” hooks'); 
			}
			list($retMatch, $retMatchType) = $this->prepareArgMatch($retMatch);
			$options['retMatch'] = $retMatch;
			$options['retMatchType'] = $retMatchType;
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
			$debugID = $debugClass . $id;
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
	 * Add a hooks to multiple methods at once
	 *
	 * This is the same as addHook() except that the $method argument is an array or CSV string of hook definitions.
	 * See the addHook() method for more detailed info on arguments.
	 *
	 * @param Wire $object
	 * @param array|string $methods Array of one or more strings hook definitions, or CSV string of hook definitions
	 * @param object|null|callable $toObject
	 * @param string|array|null $toMethod
	 * @param array $options
	 * @return string CSV string of hook IDs that were added
	 * @throws WireException
	 * @since 3.0.137
	 *
	 */
	protected function addHooks(Wire $object, $methods, $toObject, $toMethod = null, $options = array()) {
		
		if(!is_array($methods)) {
			// potentially multiple methods defined in a CSV string
			// could also be a single method with CSV arguments
			
			$str = (string) $methods;
			$argSplit = '|';

			// skip optional useless parenthesis in definition to avoid unnecessary iterations
			if(strpos($str, '()') !== false) $str = str_replace('()', '', $str); 
			
			if(strpos($str, '(') === false) {
				// If there is a parenthesis then it is multi-method definition without arguments
				// Example: "Pages::saveReady, Pages::saved" 
				$methods = explode(',', $str);
				
			} else {
				// Single or multi-method definitions, at least one with arguments
				// Isolate commas that are for arguments versus comments that separate multiple hook methods: 
				// Single method example: "Page(template=order)::changed(0:order_status, 1:name=pending)"
				// Multi method example: "Page(template=order)::changed(0:order_status, 1:name=pending), Page::saved"
				
				while(strpos($str, $argSplit) !== false) $argSplit .= '|';
				$strs = explode('(', $str);
				
				foreach($strs as $key => $val) {
					if(strpos($val, ')') === false) continue;
					list($a, $b) = explode(')', $val, 2);
					if(strpos($a, ',') !== false) $a = str_replace(array(', ', ','), $argSplit, $a);
					$strs[$key] = "$a)$b";
				}
				
				$str = implode('(', $strs);
				$methods = explode(',', $str);
				
				foreach($methods as $key => $method) {
					if(strpos($method, $argSplit) === false) continue;
					$methods[$key] = str_replace($argSplit, ', ', $method);
				}
			}
		}
		
		$result = array();
		$options['noAddHooks'] = true; // prevent addHook() from calling addHooks() again
		
		foreach($methods as $method) {
			$method = trim($method);
			$hookID = $this->addHook($object, $method, $toObject, $toMethod, $options);
			$result[] = $hookID;
		}
	
		$result = implode(',', $result);
		
		return $result;
	}

	/**
	 * Add a hook that handles a request path
	 * 
	 * @param Wire $object
	 * @param string $path
	 * @param Wire|null|callable $toObject
	 * @param string $toMethod
	 * @param array $options
	 * @return string
	 * @throws WireException
	 * 
	 */
	protected function addPathHook(Wire $object, $path, $toObject, $toMethod, $options = array()) {
		
		if(!$this->allowPathHooks) {
			throw new WireException('Path hooks must be attached during init or ready states');
		}
		
		$method = 'ProcessPageView::pathHooks';
		$id = $this->addHook($object, $method, $toObject, $toMethod, $options); 
		$filters = array();
		$path = trim($path);
		$pathParts = explode('/', trim($path, '/'));
		$key = null;
		
		foreach($pathParts as $index => $filter) {

			// see if it is alphanumeric, other than dash or underscore
			if(!ctype_alnum($filter) && !ctype_alnum(str_replace(array('-', '_'), '', $filter))) {
				// likely a regex pattern or named argument, see if we can use some from beginning
				$filterNew = '';
				for($n = 0; $n < strlen($filter); $n++) {
					$test = substr($filter, 0, $n+1);
					if(!ctype_alnum($test)) break;
					$filterNew = $test;
				}
				if(!strlen($filterNew)) continue;
				$filter = $filterNew;
			}
			
			// test the filter to see which one will match
			foreach(array("/$filter/", "/$filter", "$filter/") as $test) {
				$pos = strpos($path, $test); 
				if($pos === false) continue;
				$filter = $test;
				break;
			}
	
			// ensure array index 0 only ever refers to match at beginning
			$key = $pos === 0 && $index === 0 ? 0 : $index + 1;
			$filters[$key] = $filter;
		}
	
		// trailing slash on last filter is optional
		if($key !== null) $filters[$key] = rtrim($filters[$key], '/');
		
		$this->pathHooks[$id] = array(
			'match' => $path,
			'filters' => $filters, 
		);
		
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
		$profiler = $this->wire->wire()->profiler;
		$hooks = null;
		$methodExists = false;
		$useHookReturnValue = false; // allow use of "return $value;" in hook in addition to $event->return ?
		
		if($type === 'method') {
			$methodExists = method_exists($object, $realMethod); 
			if(!$methodExists && method_exists($object, $method)) {
				// non-hookable method exists, indicating we may be in a manually called runHooks()
				$methodExists = true;
				$realMethod = $method;
			}
		}
		
		if(is_array($type)) {
			// array of hooks to run provided in $type argument
			$hooks = $type;
			$type = 'custom';
		}

		$result = array(
			'return' => null,
			'numHooksRun' => 0,
			'methodExists' => $methodExists,
			'replace' => false,
		);
		
		if($type === 'method' || $type === 'property' || $type === 'either') {
			if(!$methodExists && !$this->isHookedOrParents($object, $method, $type)) {
				return $result; // exit quickly when we can
			}
		}
		
		if($hooks === null) $hooks = $this->getHooks($object, $method);
	
		foreach(array('before', 'after') as $when) {

			if($type === 'method') {
				if($when === 'after' && $result['replace'] !== true) {
					if($methodExists) {
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

			foreach($hooks as /* $priority => */ $hook) {

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
					$argMatchTypes = $hook['options']['argMatchType'];
					$matches = true;
					foreach($argMatches as $argKey => $argMatch) {
						/** @var Selectors $argMatch */
						$argMatchType = isset($argMatchTypes[$argKey]) ? $argMatchTypes[$argKey] : '';
						$argVal = isset($arguments[$argKey]) ? $arguments[$argKey] : null;
						$matches = $this->conditionalArgMatch($argMatch, $argVal, $argMatchType);
						if(!$matches) break;
					}
					if(!$matches) continue; // don't run hook
				}
				
				if($type === 'method' && $when === 'after' && !empty($hook['options']['retMatch'])) {
					if(!$this->conditionalArgMatch(
						$hook['options']['retMatch'], 
						$result['return'], 
						$hook['options']['retMatchType'])) continue;
				}
				
				if($this->allowPathHooks && isset($this->pathHooks[$hook['id']])) {
					$allowRunPathHook = $this->allowRunPathHook($hook['id'], $arguments);
					$this->removeHook($object, $hook['id']); // once only
					if(!$allowRunPathHook) continue;
					$useHookReturnValue = true;
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
					// hook method/func had an explicit 'return $value;' statement 
					// we can optionally use this rather than $event->return. Can be useful
					// in cases where a return value doesn’t need to be passed around to
					// more than one hook
					if($useHookReturnValue) {
						$event->return = $returnValue;
					}
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
	 * Prepare argument match
	 * 
	 * @param string $argMatch
	 * @return array
	 * @since 3.0.247
	 * 
	 */
	protected function prepareArgMatch($argMatch) {
		$argMatch = trim($argMatch, '()');
		$argMatchType = '';
		
		list($c1, $c2, $c3) = [ substr($argMatch, 0, 1), substr($argMatch, -1), substr($argMatch, 0, 2) ];
		
		if($c1 === '<' && $c2 === '>') {
			// i.e. <WireArray> or <ThisPage|ThatPage>
			$argMatchType = 'instanceof';
			$argMatch = trim($argMatch, '<>');
			
		} else if($c1 === '=' || $c1 === '<' || $c1 === '>' || Selectors::isOperator($c3)) {
			// selector that starts with operator and translates to "argVal matches argMatch"
			$argMatch = "___val$argMatch"; // i.e. ___val=something
			$argMatchType = 'selector';
		}
		
		if($argMatchType === 'instanceof') {
			// ok
			$argMatch = strpos($argMatch, '|') ? explode('|', $argMatch) : [ $argMatch ];
		} else if(Selectors::stringHasSelector($argMatch)) {
			/** @var Selectors $selectors */
			$selectors = $this->wire->wire(new Selectors());
			$selectors->init($argMatch);
			$argMatch = $selectors;
			$argMatchType = 'selector';
		} else {
			$argMatchType = 'equals';
		}
		
		return [ $argMatch, $argMatchType ];
	}

	/**
	 * Does given value match given match condition?
	 * 
	 * @param Selectors|string $argMatch
	 * @param mixed $argVal
	 * @return bool
	 * @since 3.0.247
	 * 
	 */
	protected function conditionalArgMatch($argMatch, $argVal, $argMatchType) {
		
		$matches = false;
		
		if($argMatch instanceof Selectors) {
			// Selectors object
			/** @var Selector $s */
			$s = $argMatch->first();
			if($s instanceof Selector && $s->field() === '___val') {
				$o = WireData();
				$o->set('value', $argVal);
				$s->field = 'value';
				$argVal = $o;
			} else if(is_array($argVal)) {
				$argVal = count($argVal) && is_string(key($argVal)) ? WireData($argVal) : WireArray($argVal);
			}
			if(is_object($argVal)) {
				$matches = $argMatch->matches($argVal);
			}

		} else if($argMatchType === 'instanceof') {
			if(!is_array($argMatch)) $argMatch = [ $argMatch ];
			foreach($argMatch as $type) {
				if(isset($this->argMatchTypes[$type])) {
					$argMatchFunc = $this->argMatchTypes[$type];
					$matches = $argMatchFunc($argVal);
				} else {
					$matches = wireInstanceOf($argVal, $type);
				}
				if($matches) break;
			}
			
		} else if(is_array($argVal)) {
			// match any array element
			$matches = in_array($argMatch, $argVal);

		} else {
			// exact match
			$matches = $argMatch == $argVal;
		}

		return $matches;
		
	}

	/**
	 * Allow given path hook to run?
	 *
	 * This checks if the hook’s path matches the request path, allowing for both
	 * regular and regex matches and populating parenthesized portions to arguments
	 * that will appear in the HookEvent.
	 * 
	 * @param string $id Hook ID
	 * @param array $arguments
	 * @return bool
	 * @since 3.0.173
	 * 
	 */
	protected function allowRunPathHook($id, array &$arguments) {
		
		$pathHook = $this->pathHooks[$id];
		$requestPath = $arguments[0];
		$filterFail = false;
		
		// first pre-filter the requestPath against any words matchPath (filters)
		foreach($pathHook['filters'] as $key => $filter) {
			$pos = strpos($requestPath, $filter); 
			if($pos === false || ($key === 0 && $pos !== 0)) $filterFail = true;
			if($filterFail) break;
		}
		
		if($filterFail) return false;
		
		// at this point the path hook passed pre-filters and might match
		
		$pageNum = $this->wire->wire()->input->pageNum();
		$slashed = substr($requestPath, -1) === '/' && strlen($requestPath) > 1;
		$matchPath = $pathHook['match'];
		$regexDelim = ''; // populated only for user-specified regex
		$pageNumArgument = 0; // populate in $arguments when {pageNum} present in match pattern
	
		if(strpos('!@#%', $matchPath[0]) !== false) {
			// already in delimited regex format
			$regexDelim = $matchPath[0];
		} else {
			// needs to be in regex format
			if(strpos($matchPath, '.') !== false) {
				// preserve some regex sequences containing periods
				$r = [ '.+' => '•+', '.*' => '•*', '\\.' => '\\•' ];
				$matchPath = str_replace(array_keys($r), array_values($r), $matchPath);
				// force any remaining periods to be taken literally
				$matchPath = str_replace('.', '\\.', $matchPath);
				// restore regex sequences containing periods
				$matchPath = str_replace(array_values($r), array_keys($r), $matchPath);
			}
			if(strpos($matchPath, '/') === 0) $matchPath = "^$matchPath";
			$matchPath = "#$matchPath$#";
		}

		if(strpos($matchPath, '{pageNum}') !== false) {
			// the {pageNum} named argument maps to $input->pageNum. remove the {pageNum} argument
			// from the match path since it is handled differently from other named arguments
			$find = array('/{pageNum}/', '/{pageNum}', '{pageNum}');
			$matchPath = str_replace($find, '/', $matchPath);
			$pathHook['match'] = str_replace($find, '/', $pathHook['match']); 
			$pageNumArgument = $pageNum;
		} else if($pageNum > 1) {
			// hook does not handle pagination numbers above 1
			return false;
		}

		if(strpos($matchPath, ':') && strpos($matchPath, '(') !== false) {
			// named arguments in format “(name: value)” converted to named PCRE capture groups
			$matchPath = preg_replace('#\(([-_a-z0-9]+):#i', '(?P<$1>', $matchPath);
		}
		
		if(strpos($matchPath, '{') !== false) {
			// named arguments in format “{name}” converted to named PCRE capture groups
			// note that the match pattern of any URL segment is assumed for this case
			$matchPath = preg_replace('#\{([_a-z][-_a-z0-9]*)\}#i', '(?P<$1>[^/]+)', $matchPath); 
		}

		if(!preg_match($matchPath, $requestPath, $matches)) {
			// if match fails, try again with trailing slash state reversed
			if($slashed) {
				$requestPath2 = rtrim($requestPath, '/');
			} else {
				$requestPath2 = "$requestPath/";
			}
			if(!preg_match($matchPath, $requestPath2, $matches)) return false;
		}
		
		// check on trailing slash
		if(strpos($matchPath, '/?') === false) {
			// either slash or no-slash is required, depending on whether match pattern ends with one
			$slashRequired = substr(rtrim($pathHook['match'], $regexDelim . '$)+'), -1) === '/';
			$this->pathHookRedirect = '';
			if($slashRequired && !$slashed) {
				// trailing slash required and not present
				$this->pathHookRedirect = $requestPath . '/';
				return false;
			} else if(!$slashRequired && $slashed) {
				// lack of trailing slash required and one is present
				$this->pathHookRedirect = rtrim($requestPath, '/');
				return false;
			}
		}
		
		// success: at this point the requestPath has matched
		$arguments['path'] = $arguments[0];
		if($pageNumArgument) $arguments['pageNum'] = $pageNumArgument;

		foreach($matches as $key => $value) {
			// populate requested arguments
			if($key !== 0) $arguments[$key] = $value;
		}
		
		return true;
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
	 * @param string|array|null $hookID Can be single hook ID, array of hook IDs, or CSV string of hook IDs
	 * @return Wire
	 *
	 */
	public function removeHook(Wire $object, $hookID) {
		if(is_array($hookID) || strpos($hookID, ',')) {
			return $this->removeHooks($object, $hookID);
		}
		if(!empty($hookID) && substr_count($hookID, ':') === 2) {
			// local hook ID ":100.0:methodName" or static hook ID "ClassName:100.0:methodName"
			list($hookClass, $priority, $method) = explode(':', $hookID);
			if(empty($hookClass)) {
				// local hook
				$localHooks = $object->getLocalHooks();
				unset($localHooks[$method][$priority]);
				$object->setLocalHooks($localHooks);
			} else {
				// static hook
				unset($this->staticHooks[$hookClass][$method][$priority], $this->pathHooks[$hookID]);
				if(empty($this->staticHooks[$hookClass][$method])) {
					unset($this->hookClassMethodCache["$hookClass::$method"]);
				}
			}
		}
		return $object;
	}

	/**
	 * Given a hook ID or multiple hook IDs (in array or CSV string) remove the hooks
	 * 
	 * @param Wire $object
	 * @param array|string $hookIDs
	 * @return Wire
	 * @since 3.0.137
	 * 
	 */
	protected function removeHooks(Wire $object, $hookIDs) {
		if(!is_array($hookIDs)) $hookIDs = explode(',', $hookIDs); 
		foreach($hookIDs as $hookID) {
			$this->removeHook($object, $hookID);
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
	 * Return all pending path hooks
	 *
	 * @return array
	 * @since 3.0.173
	 *
	 */
	public function getAllPathHooks() {
		return $this->pathHooks;
	}

	/**
	 * Return whether or not any path hooks are pending
	 *
	 * @param string $requestPath Optionally provide request path to determine if any might match (3.0.174+)
	 * @return bool
	 * @since 3.0.173
	 *
	 */
	public function hasPathHooks($requestPath = '') {
		// first pre-filter the requestPath against any words matchPath (filters)
		if(strlen($requestPath)) return $this->filterPathHooks($requestPath, true);
		return count($this->pathHooks) > 0;
	}

	/**
	 * Return path hooks that have potential to match given request path
	 * 
	 * @param string $requestPath
	 * @param bool $has Specify true to change return value to boolean as to whether any can match (default=false)
	 * @return array|bool
	 * @since 3.0.174
	 * 
	 */
	public function filterPathHooks($requestPath, $has = false) {
		$pathHooks = array();
		foreach($this->pathHooks as $id => $pathHook) {
			$fail = false;
			foreach($pathHook['filters'] as $filter) {
				$fail = strpos($requestPath, $filter) === false;
				if($fail) break;
			}
			if(!$fail) {
				$pathHooks[$id] = $pathHook;
				if($has) break;
			}
		}
		return $has ? count($pathHooks) > 0 : $pathHooks;
	}

	/**
	 * Get or set whether path hooks are allowed
	 * 
	 * @param bool|null $allow
	 * @return bool
	 * @since 3.0.173
	 * 
	 */
	public function allowPathHooks($allow = null) {
		if($allow !== null) $this->allowPathHooks = (bool) $allow;
		return $this->allowPathHooks;
	}

	/**
	 * Return redirect URL required by an applicable path hook, or blank otherwise
	 * 
	 * @return string
	 * @since 3.0.173
	 * 
	 */
	public function getPathHookRedirect() {
		return $this->pathHookRedirect;
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
