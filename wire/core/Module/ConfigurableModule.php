<?php namespace ProcessWire;

/**
 * ProcessWire ConfigurableModule and ConfigModule Interfaces
 *
 * Provides the base interfaces required by modules.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 * 
 * About the ConfigurableModule interface
 * ======================================
 * ConfigurableModule is an interface that indicates the module is configurable by providing
 * `__get()` and `__set()` methods for getting and setting config values. Modules implementing 
 * this interface are assumed to also implement the `Module` interface.
 * 
 * The module must also provide one (1) of the following:
 *
 * 1. A `getModuleConfigInputfields([$data])` method (static or non-static); OR
 * 2. A separate `ModuleName.config.php` file that just populates $config array; OR
 * 3. A separate `ModuleNameConfig.php` file that contains a ModuleConfig class.
 *
 * For more details about the above options, see the commented methods within
 * the interface.
 *
 * When you use this as an interface, you MUST also use `Module` as an interface,
 * i.e. `class Something implements Module, ConfigurableModule`
 *
 * Hint: Make your ConfigurableModule classes inherit from `WireData`, which already has
 * the get/set required methods.
 *
 * You may optionally specify a handler method for configuration data: `setConfigData()`.
 * If present, it will be used. See commented function reference in the interface below.
 * 
 * 
 * About the ConfigModule interface (3.0.179+)
 * ===========================================
 * This interface indicates the module can receive config settings, but is not
 * interactively configurable. Use this for modules where configuration will
 * only be managed from the API/code side. Config settings must be saved using
 * `$modules->saveConfig()`. Settings will be automatically populated to the module
 * when it is loaded, or may be retrieved with `$modules->getConfig()`.
 *
 * Beyond the difference mentioned above, this interface is identical to the
 * ConfigurableModule interface except that it needs no getModuleConfigInputfields()
 * method nor will it use a configuration php or json file. 
 * 
 * A module *must not* contain both the ConfigModule and ConfigurableModule interfaces 
 * in their implements definition at the same time, so choose just one. 
 * 
 *
 */
interface ConfigurableModule {

	/**********************************************************************************
	 * getModuleConfigInputfields method (static or non-static)
	 *
	 * Return an InputfieldWrapper of Inputfields used to configure the class. This may
	 * be specified either as a static method or a non-static method.
	 *
	 * Benefits of static version
	 * ===========================
	 * 1. The module does not need to be instantiated in order to configure it, which
	 *    means that unnecessary hooks won't get attached and unnecessary assets won't
	 *    be triggered to load.
	 * 2. It is supported by all versions of ProcessWire.
	 *
	 * Drawbacks of static version
	 * ===========================
	 * 1. You cannot pull config values directly from the module since it isn't
	 *    instantiated, and thus you must use the provided $data array. This $data array
	 *    only contains values if the module has been configured before.
	 * 2. You can't access $this or anything you'd typically pull from it, like API vars
	 *    or translation methods.
	 *
	 * Benefits of non-static version
	 * ==============================
	 * 1. You are working with the module in the same context that it is when running,
	 *    thus you can pull config values and API vars directly from $this->something.
	 * 2. It can be extended in descending classes.
	 * 3. The $data argument can be omitted, as you don't need it since all config
	 *    properties can be accessed directly from $this->[any property]. 
	 * 4. You can specify an optional $inputfields argument in your function definition
	 *    and if present, ProcessWire will prepare an InputfieldWrapper for you, saving
	 *    a step. When present, you can optionally omit the return statement at the 
	 *    bottom of the method as well. 
	 *
	 * Drawbacks of non-static version
	 * ================================
	 * 1. It is supported only in ProcessWire versions 2.5.27 or newer.
	 * 2. The module must be instantiated in order to configure it, so it may trigger
	 *    load of any used assets or attachment of any hooks unnecessarily.
	 *
	 * @param array $data Array of config values indexed by field name (static version only)
	 * 	Note that this array will be empty if the module has not been configured before.
	 * @return InputfieldWrapper
	 *
	 *  
	 * // static version
	 * public static function getModuleConfigInputfields(array $data); 
	 * 
	 * // non-static version
	 * public function getModuleConfigInputfields(); 
	 *  
	 * // non-static version with optional $data array, if you want it for some reason
	 * public function getModuleConfigInputfields(array $data); 
	 *  
	 * // non-static version with optional InputfieldWrapper as a convenience
	 * // note that the "return" statement may be omitted when using the $inputfields param.
	 * public function getModuleConfigInputfields($inputfields); 
	 * 
	 */

	/*********************************************************************************
	 * Return an array defining Inputfields (static or non-static)
	 * 
	 * You should use either getModuleConfigArray() or getModuleConfigInputfields(),
	 * do not use both, as ProcessWire will only recognize one or the other. Likewise,
	 * you should either use the static version of non-static version, not both. 
	 *
	 * See notes for getModuleConfigInputfields() above for benefits and drawbacks
	 * of static vs. non-static versions. The primary difference between this method
	 * and that method is that this one returns an array. The format of the array should
	 * be as shown in InputfieldWrapper::importArray (see InputfieldWrapper.php).
	 * 
	 * Whether static or non-static, your 'value' attributes in the array need only 
	 * represent the default values. ProcessWire will populate the actual values 
	 * to the resulting Inputfields after the method has been called. This is a benefit
	 * over the getModuleConfigInputfields() methods. 
	 *
	 * @return array
	 *
	 * public static function getModuleConfigArray(); // static version
	 * public function getModuleConfigArray(); // non-static version
	 */

	/**
	 * Get a module config property
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __get($key);

	/**
	 * Set a module config property
	 *
	 * @param $key
	 * @param $value
	 * @return mixed
	 *
	 */
	public function __set($key, $value);

	/**
	 * An optional method you may include in your ConfigurableModule to have ProcessWire
	 * send the configuration data to it rather than populating the properties individually.
	 *
	 * @param array $data Array of data in $key => $value format.
	 *
	 * public function setConfigData(array $data);
	 *
	 */

}

/**
 * ProcessWire ConfigModule interface
 * 
 * See notes about this interface and its differences in the ConfigurableModule documentation.
 * 
 * @since 3.0.179
 *
 */
interface ConfigModule {
	public function __get($key);
	public function __set($key, $value);
}
