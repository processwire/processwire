<?php namespace ProcessWire;

/**
 * ProcessWire Module Interface
 *
 * Provides the base interfaces required by modules.
 * 
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Module is the primary PHP interface for module types in ProcessWire. 
 * #pw-body = 
 * The Module interface doesn't actually require any specific methods,
 * (other than the `className()` method) but is required as an interface 
 * to state your intention to ProcessWire that your class is to be used 
 * as a Module. As a result, all methods are optional, but including the 
 * Module interface is not. You must also provide a means by which ProcessWire
 * can query information about your module. More on that below. 
 * 
 * ### Implementing the Module interface
 * 
 * Below is how you indicate a PHP class is a ProcessWire Module:
 * 
 * ~~~~~
 * <?php namespace ProcessWire;
 * class HelloWorld extends WireData implements Module { 
 *   // your class implementation
 * }
 * ~~~~~
 * 
 * Modules should either extend the `WireData` class, or if they are a 
 * predefined type already recognized by ProcessWire, they should extend 
 * the base class of that type (or another module based upon it). Base 
 * module types include:
 *
 * - `AdminTheme`
 * - `Fieldtype`
 * - `FileCompilerModule`
 * - `FileValidatorModule`
 * - `Inputfield`
 * - `ModuleJS`
 * - `PageAction`
 * - `Process`
 * - `Textformatter`
 * - `WireAction`
 * - `WireMail`
 * - `WireSessionHandler`
 * 
 * ### Requirements for Modules
 * 
 * 1. Must provide a means of getting information about the module.
 *    This can be with a static `getModuleInfo()` method, or with
 *    a `ModuleName.info.php` file or a `ModuleName.info.json` file. 
 * 
 * 2. Must provide a `className()` method that returns the module 
 *    class name. All Wire derived objects already do this, so 
 *    you don't have to provide it unless your Module does not
 *    descend from a ProcessWire class. We recommend that your
 *    modules extend the Wire or WireData class. 
 * 
 * 3. If you have a `__construct()` method, it must not require any
 *    particular arguments. 
 * 
 * 4. If your module is configurable, it must also fulfill the
 *    `ConfigurableModule` interface. 
 * 
 * ### Optional methods 
 * 
 * - `__construct()` - Called before module config is populated.
 * 
 * - `init()` - Called after module config is populated.
 * 
 * - `ready()` - Called after init(), after API ready. Note that ready() applies to 'autoload' modules only. 
 * 
 * - `install()` - Called when module is installed. 
 * 
 * - `uninstall()` - Called when module is uninstalled. 
 * 
 * - `upgrade($fromVersion, $toVersion)` - Called on version change.
 * 
 * - `isAutoload()` - Returns a boolean indicating whether the module should be loaded at boot. 
 *   Can also be specified as a property in module information.
 * 
 * - `isSingular()` - Returns a boolean indicating whether the module is limited to one instance or not. 
 *   Can also be specified as a property in module information.
 * 
 * - `getModuleInfo()` - A static method that returns an array of module information. 
 * 
 * These methods are outlined in more detail further down on this page. 
 * 
 * -----------------------------------------------------------------
 * 
 * ## Module Information
 * 
 * Modules must have some way to communicate information about 
 * themselves to ProcessWire. This is done by providing an 
 * associative array containing this module information. One
 * of the following implementations is required: 
 * 
 * 1. `getModuleInfo()` static method in your module class that returns an array.
 * 2. `YourModuleClass.info.php` file that populates an `$info` array.
 * 3. `YourModuleClass.info.json` file that contains an `info` object.
 *   
 * Each of these are demonstrated below:
 *  
 * **1) Static getModuleInfo() method:**
 * ~~~~~
 * public static function getModuleInfo() {
 *   return array(
 *     'title' => 'Your Module Title',
 *     'version' => 1,
 *     'author' => 'Your Name',
 *     'summary' => 'Description of what this module does and who made it.',
 *     'href' => 'http://www.domain.com/info/about/this/module/',
 *     'autoload' => false, // set to true if module should auto-load at boot
 *     'requires' => array(
 *        'HelloWorld>=1.0.1', 
 *        'PHP>=5.4.1', 
 *        'ProcessWire>=2.4.1'
 *     ),
 *     'installs' => array('Module1', 'Module2', 'Module3'),
 *     );
 * }
 * ~~~~~
 *
 * **2) YourModuleClass.info.php file:**  
 * Your file should populate an `$info` variable with an array exactly like 
 * described for #1 above, i.e.
 * 
 * ~~~~~
 * $info = array(
 *   'title' => 'Your Module Title',
 *   'version' => 1,
 *   // and so on, like the static version above
 * );
 * ~~~~~
 *
 * **3) YourModuleClass.info.json file:**  
 * Your JSON file should contain nothing but an object/map of the module info:
 * 
 * ~~~~~
 * {
 *   "title": "Your Module Title",
 *   "version": 1
 * }
 * ~~~~~
 * Note: The example JSON above just shows "title" and "version", but you would
 * likely add more than that as needed, like shown in the static version above. 
 * 
 * -----------------------------------------------------------------
 *
 * ## Module information properties
 * 
 * ### Required info properties
 *
 * - `title` (string): The module's title.
 * 
 * - `version` (int|string): An integer or string that indicates the version number.
 * 
 * - `summary` (string): Summary text of the module (1 sentence recommended).
 * 
 * ### Optional info properties
 * 
 * - `href` (string): URL to more information about the module.
 * 
 * - `requires` (array|string): Array or CSV string of module class names that are required by this 
 *    module in order to install.
 * 
 *    - **Requires Module version:** If a particular version of the module is required, then specify an operator
 *		and version number after the module name, like this: "HelloWorld>=1.0.1". 
 * 
 *    - **Requires PHP version:** If a particular version of PHP is required, then specify "PHP" as the module name
 *		followed by an operator and required version number, like this: "PHP>=5.6.0". 
 * 
 *    - **Requires ProcessWire version:** If a particular version of ProcessWire is required, then specify
 *		ProcessWire followed by an operator and required version number, like this: "ProcessWire>=2.4.1". 
 * 
 * - `installs` (array|string): Array or CSV string of module class names that this module will handle install 
 *    and uninstall for.
 *    This causes ProcessWire's dependency checker to ignore them and it is assumed your module will handle 
 *    them. If your module does not handle them, ProcessWire will automatically install/uninstall them 
 *    immediately after your module.
 * 
 * - `permanent` (boolean): This property is intended for only for core modules. When true, a module cannot be uninstalled.
 * 
 * - `permission` (string): Name of permission required of a user before ProcessWire will load the module (for non-superusers).
 *    Note that ProcessWire will not install this permission if it doesn't yet exist. To have it installed automatically,
 *    see the _permissions_ option below this.
 * 
 * - `permissions` (array): Array of permissions that ProcessWire will install (and uninstall) automatically.
 *    Permissions should be in the format: array('permission-name' => 'Permission description'). 
 * 
 * - `icon` (string): Optional icon name to represent this module.
 *    Currently uses [font-awesome](http://fortawesome.github.io/Font-Awesome/) icon names.
 *    Omit the "fa-" part, leaving just the icon name.
 * 
 * - `singular` (boolean): Is only one instance of this module allowed? (default=auto-detect).
 *    This is good for any module that you want to eliminate the possibility of multiple instances
 *    running at once. For instance, modules that become API variables are typically singular, whereas
 *    something like an Inputfield module would not be singular. When not specified, modules that extend an 
 *    existing base type typically inherit the singular setting from the module they extend. 
 * 
 * - `autoload` (boolean|string|callable|int): Should this module load automatically at boot? (default=false).
 *    This is good for modules that attach hooks or that need to otherwise load on every single
 *    request. Autoload is typically specified as a boolean true or false. Below are the different ways
 *    autoload can be specified: 
 * 
 *    - **Boolean:** Specify true or false to indicate the module should either always autoload (true) 
 *      or never autoload (false). 
 * 
 *    - **Selector string:** The module will be automatically loaded only if the current page matches the
 *      selector string. For example, a selector string of `template=admin` would mean the module will
 *      only autoload in the admin side of ProcessWire. 
 * 
 *    - **Callable function:** The module will automatically load only if the given callable function 
 *      returns true. 
 * 
 *    - **Integer:** If given integer 2 or higher, it will autoload the module before other autoload
 *      modules (in /site/modules/). Higher numbers autoload before lower numbers. 
 * 
 * - `searchable` (string): When present, indicates that module implements a search() method 
 *    consistent with the SearchableModule interface. The value of the 'searchable' property should 
 *    be the name that the search results are referred to, using ascii characters of a-z, 0-9, and
 *    underscore. See the SearchableModule interface in this file for more details. 
 * 
 * -----------------------------------------------------------------------------------------------
 * 
 * ## Module Methods
 * 
 * ### __construct()
 * 
 * This method is called by PHP immediately when the module is instantiated, and before any 
 * configuration data has been populated to the module. This method must not have any required
 * arguments. This method is a good place for populating default configuration values or any
 * other initialization you want to occur before ProcessWire sees it or populates anything to it.
 * Your construct method should not assume that the module will actually be executed, as 
 * ProcessWire may instantiate a module for informational reasons. 
 * 
 * ### init()
 * 
 * This method is called after `__construct()` and after any configuration data has been populated
 * to the module. It is called before the module is handed over to the requester. This is a good
 * place to perform any initialization that requires configuration data and can be a good place to 
 * attach hooks. 
 * 
 * ### ready()
 * 
 * This method is used only by _autoload_ modules. It is called when the entire ProcessWire API
 * is ready to use. This may be preferable to the `init()` method for autoload modules because 
 * they are loaded and init()'d at boot, when everything else is loading too. The ready() method
 * is called once the boot has completed and all API variables are ready to use, but before any
 * page has been rendered. This makes it an excellent place to attach hooks. 
 * 
 * ### isSingular()
 *
 * Indicates whether only one instance of a module is allowed to exist in memory. 
 * If this method is not present, it will be auto-determined based on module type. If it is provided
 * in the module information array (discussed above) that will override this method. 
 * 
 * This method exists primarily so that base module types may specify a singular state and have it 
 * automatically inherit to any modules extending the type. If you are not extending a base module
 * type then you can implement this method, or you can provide it in your module info array. 
 *
 * A module that returns TRUE is referred to as a "singular" module, because there will never be any more
 * than a single instance of the module running.
 *
 * Return TRUE if this module is a single reusable instance, returning the same instance on every 
 * call from Modules. Return FALSE if this module should return a new instance on every call from Modules.
 *
 * - Singular modules will have their instance active for the entire request after instantiated.
 * - Non-singular modules return a new instance on every `$modules->get("YourModule")` call.
 * - Modules that attach hooks are usually singular.
 * - Modules that may have multiple instances (like `Inputfield` modules) should _not_ be singular.
 *
 * If you are having trouble deciding whether to make your module singular or not, be sure to read 
 * the documentation below for the `isAutoload()` method, because if your module is 'autoload' then 
 * it's probably also 'singular'.
 * 
 * ### isAutoload()
 * 
 * Should this module be automatically loaded at boot?
 * If this method is not present, it will be auto-determined based on module type. If it is provided
 * in the module information array (discussed above) that will override this method.
 *
 * This method exists primarily so that base module types may specify an autoload state and have it
 * automatically inherit to any modules extending the type. If you are not extending a base module
 * type then you can implement this method, or you can provide it in your module info array. 
 *  
 * A module that returns TRUE is referred to as an "autoload" module, because it automatically loads as
 * part of ProcessWire's boot process. Autoload modules load before PW attempts to handle the web request.
 *  
 * Return TRUE if this module is automatically loaded at runtime.
 * Return FALSE if this module must be requested via `$modules->get('ModuleName')` method before it is loaded.
 *  
 * Modules that are intended to attach hooks in the application typically should be autoload because
 * they listen in to classes rather than have classes call upon them. If they weren't autoloaded, then
 * they might never get to attach their hooks.
 *  
 * Modules that shouldn't be autoload are those that may or may not be needed at runtime, for example
 * `Fieldtype` and `Inputfield` modules.
 *  
 * _As a side note, I can't think of any reason why a non-singular module would ever be autoload. The fact that
 * an autoload module is automatically loaded as part of PW's boot process implies it's probably going to be the
 * only instance running. So if you've decided to make your module 'autoload', then is safe to assume you've
 * also decided your module will also be singular (if that helps anyone reading this)._
 * 
 * ### install()
 * 
 * This method is called when the module is first installed. If implemented, install() methods typically are
 * defined hookable as `public function ___install()`. 
 * 
 * The method should prepare the environment with anything else needed by the module, such as newly created 
 * fields, pages, templates, etc. or installation of other modules. 
 * 
 * If the install() method determines that the module cannot be installed for some reason, it should 
 * throw a `WireException.` 
 * 
 * ### uninstall()
 * 
 * This method is called when the module is uninstalled. If implemented, uninstall() methods typically are
 * defined hookable as `public function ___uninstall()`. 
 * 
 * This method should undo everything done by the install() method, or undo anything created by the module,
 * restoring the system back to the state that it was in before the module was installed. 
 * 
 * If the uninstall() method determines that it cannot proceed for some reason, it should throw 
 * a `WireException`. 
 * 
 * ### upgrade($fromVersion, $toVersion)
 * 
 * This method is called when a version change is detected. This method should make any adjustments needed
 * to support the module from one version to another. The previous known version ($fromVersion) and new
 * version ($toVersion) are provided as arguments.
 * 
 * If implemented, upgrade() methods typically are defined hookable as `public function ___upgrade(...)`. 
 * If the upgrade cannot proceed for some reason, this method should throw a `WireException`. 
 * 
 * 
 * 
 *
 * #pw-body
 * 
 * The following methods may or may not be implemented, all are optional:
 * 
 * #pw-method void install() Called when module is installed. 
 * #pw-method void uninstall() Called when module is uninstalled. 
 * #pw-method void upgrade($fromVersion, $toVersion) Called when a version change is detected for the module. 
 * #pw-method array getModuleInfo() Static method that returns array of module info (not required if module implements an info file instead). 
 * #pw-method void init() Called when the module is loaded, immediately after any configuration data has been populated to it. 
 * #pw-method void ready() For autoload modules only, called when the ProcessWire API is ready to use. 
 * #pw-method void setConfigData(array $data) Modules may optionally provide this method to receive config data from ProcessWire.
 * #pw-method bool isSingular() #pw-internal
 * #pw-method bool isAutoload() #pw-internal
 * 
 *
 */

interface Module {

	/**
	 * Return an array of module information
	 * 
	 * @return array
	 *
	 * public static function getModuleInfo(); 
	 * 
	 */

	/**
	 * Method to initialize the module. 
	 *
	 * While the method is required, if you don't need it, then just leave the implementation blank.
	 *
	 * This is called after ProcessWire's API is fully ready for use and hooks. It is called at the end of the 
	 * bootstrap process. This is before PW has started retrieving or rendering a page. If you need to have the
	 * API ready with the $page ready as well, then see the ready() method below this one. 
	 *
	 * public function init();
	 * 
	 */

	/**
	 * Method called when API is fully ready and the $page is determined and set, but before a page is rendered.
	 *
	 * Optional and only called if it exists in the module. 
	 *
	 * public function ready();
	 * 
	 */

	/**
	 * Return this object’s class name
	 * 
	 * If your Module descends from Wire, or any of it's derivatives (as would usually be the case),
	 * then you don't need to implement this method as it's already present. 
	 *
	 * @param array|bool|null $options Optionally an option or boolean for 'namespace' option:
	 * - `lowercase` (bool): Specify true to make it return hyphenated lowercase version of class name
	 * - `namespace` (bool): Specify false to omit namespace from returned class name. Default=true.
	 * - Note: when lowercase=true option is specified, the namespace=false option is required.
	 * @return string
	 * @see Wire::className()
	 *
	 */
	public function className($options = null);

	/**
	 * Perform any installation procedures specific to this module, if needed. 
	 *
	 * The Modules class calls this install method right after performing the install. 
	 * 
	 * If this method throws an exception, PW will catch it, remove it from the installed module list, and
	 * report that the module installation failed. You may specify details about why with the exception, i.e.
	 * throw new WireException("Can't install because of ..."); 
	 *
	 * This method is OPTIONAL, which is why it's commented out below. 
	 *
	 * public function ___install();
	 * 
	 */

	/**
	 * Perform any uninstall procedures specific to this module, if needed. 
	 *
	 * It calls this uninstall method right before completing the uninstall. 
	 *
	 * This method is OPTIONAL, which is why it's commented out below. 
	 *
	 * public function ___uninstall();
	 * 
	 */
	
	/**
	 * Called when a version change is detected on the module
	 * 
	 * public function ___upgrade($fromVersion, $toVersion);
	 * 
	 */ 

	/**
	 * Is this module intended to be only a single instance?
	 *
	 * @return bool
	 * 
	 * public function isSingular();
	 * 
	 */

	/**
	 * Is this module automatically loaded at runtime?
	 * 
	 * @return bool
	 *	
	 * public function isAutoload(); 
	 * 
	 */ 
}

/**
 * Standard module interface with all methods. 
 * 
 * This interface is not intended to be used for anything other than for code hinting purposes. 
 *
 */
interface _Module {
	
	public function install();
	public function uninstall();
	public function upgrade($fromVersion, $toVersion);
	
	/** @return array */
	public static function getModuleInfo();
	
	public function init();
	
	public function ready();
	
	public function setConfigData(array $data);
	
	/** @return bool */
	public function isSingular();
	
	/** @return bool */
	public function isAutoload();

	/**
	 * @param InputfieldWrapper|array|null $data
	 * @return InputfieldWrapper
	 * 
	 */
	public function getModuleConfigInputfields($data = null);

	/**
	 * @return array
	 * 
	 */
	public function getModuleConfigArray();
}

/**
 * Interface SearchableModule
 *
 * Interface for modules that implement a method and expected array return value
 * for completing basic text searches (primarily for admin search engine).
 * 
 * It is optional to add this interface to "implements" section of the module class definition.
 * However, you must specify a "searchable: name" property in your getModuleInfo() method in 
 * order for ProcessWire to recognize the module is searchable. See below for more info:
 *
 * ~~~~~~
 * public static function getModuleInfo() {
 *   return array(
 *     'searchable' => 'name', 
 * 
 *     // You'll need the above 'searchable' property returned by your getModuleInfo(). 
 *     // The value of 'name' should be the name by which search results should be referred to	
 *     // if the user wants to limit the search to this module. For instance, if your module 
 *     // was called “ProcessWidgets”, you’d probably choose the name “widgets” for this. 
 *     // If the module represents an API variable, the name should be the same as the API variable. 
 *     // ...
 *   );
 * }
 * ~~~~~
 * 
 */
interface SearchableModule {

	/**
	 * Search for items containing $text and return an array representation of them
	 * 
	 * You may also implement this method as hookable, i.e. ___search(), but note that you’ll
	 * want to skip the "implements SearchableModule" in your class definition. 
	 *
	 * Must return PHP array in the format below. For each item in the 'items' array, Only the 'title' 
	 * and 'url' properties are required for each item (the rest are optional). 
	 *
	 * $result = array(
	 *   'title' => 'Title of these items',
	 *   'total' => 999, // total number of items found, or omit if pagination not supported or active
	 *   'url' => '', // optional URL to view all items, or omit for a PW-generated one
	 *   'properties' => array(), // optional list of supported search properties, only looked for if $options['info'] === true;
	 *   'items' => array(
	 *     [0] => array(
	 *       'id' => 123, // Unique ID of item (optional)
	 *       'name' => 'Name of item', // (optional)
	 *       'title' => 'Title of item', // (*required)
	 *       'subtitle' => 'Secondary/subtitle of item',  // (optional)
	 *       'summary' => 'Summary or description of item', // (optional)
	 *       'url' => 'URL to view or edit the item', // (*required)
	 *       'icon' => 'Optional icon name to represent the item, i.e. "gear" or "fa-gear"', // (optional)
	 *       'group' => 'Optionally group with other items having this group name, overrides $result[title]', // (optional)
	 *       'status' => int, // refers to Page status, omit if not a Page item (optional)
	 *       'modified' => int, // modified date of item as unix timestamp (optional)
	 *     [1] => array(
	 *       ...
	 *     ),
	 *   ),
	 * );
	 *
	 * PLEASE NOTE:  
	 * When ProcessWire calls this method, if the module is not already loaded (autoload), 
	 * it instantiates the module but DOES NOT call the init() or ready() methods. That’s because the 
	 * search method is generally self contained. If you need either of those methods to be called,
	 * and your module is not autoload, you should call the method(s) from your search() method.
	 * 
	 * About the optional “properties” index:
	 * If ProcessWire calls your search() method with $options['info'] == true; then it is likely wanting to see
	 * what properties are available for search. For instance, properties for a Module search might be: 
	 * [ 'name', 'title', 'summary' ]. Implementation of the properties index is optional, and for PW’s informational
	 * purposes only. 
	 * 
	 * @param string $text Text to search for
	 * @param array $options Options array provided to search() calls: 
	 *  - `edit` (bool): True if any 'url' returned should be to edit rather than view items, where access allows. (default=true)
	 *  - `multilang` (bool): If true, search all languages rather than just current (default=true).
	 *  - `start` (int): Start index (0-based), if pagination active (default=0).
	 *  - `limit` (int): Limit to this many items, or 0 for no limit. (default=0).
	 *  - `type` (string): If search should only be of a specific type, i.e. "pages", "modules", etc. then it is 
	 *     specified here. This corresponds with the getModuleInfo()['searchable'] name or item 'group' property. 
	 *     Note that ProcessWire won’t call your search() method if the type cannot match this search. 
	 *  - `operator` (string): Selector operator type requested, if more than one is supported (default is %=).
	 *  - `property` (string): If search should limit to a particular property/field, it is named here. 
	 *  - `verbose` (bool): True if output can optionally be more verbose, false if not. (default=false)
	 *  - `debug` (bool): True if DEBUG option was specified in query. (default=false)
	 *  - `help` (bool): True if we are just querying for help/info and are not using the search results. (default=false)
	 * @return array
	 *
	 */
	public function search($text, array $options = array()); 
}
