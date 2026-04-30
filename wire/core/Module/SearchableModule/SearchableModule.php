<?php namespace ProcessWire;

/**
 * Searchable Module interface
 *
 * #pw-summary Interface for modules that implement a method and expected array return value for completing basic text searches (primarily for admin search engine).
 * #pw-body =
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
 * #pw-body
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
