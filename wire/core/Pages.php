<?php namespace ProcessWire;

/**
 * ProcessWire Pages ($pages API variable)
 *
 * Manages Page instances, providing find, load, save and delete capabilities, most of 
 * which are delegated to other classes but this provides the common interface to them.
 *
 * This is the most used object in the ProcessWire API. 
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 * @link http://processwire.com/api/variables/pages/ Offical $pages Documentation
 * @link http://processwire.com/api/selectors/ Official Selectors Documentation
 * 
 * #pw-summary Enables loading and manipulation of Page objects, to and from the database. 
 * 
 * PROPERTIES
 * ==========
 * @property bool $cloning Whether or not a clone() operation is currently active #pw-internal
 * @property bool $outputFormatting Current default output formatting mode. #pw-internal
 * @property bool $autojoin Whether or not autojoin is allowed (typically true) #pw-internal
 * 
 * HOOKABLE METHODS
 * ================
 * @method PageArray find($selectorString, array $options = array()) Find and return all pages matching the given selector string. Returns a PageArray. #pw-group-retrieval
 * @method bool save(Page $page, $options = array()) Save any changes made to the given $page. Same as : $page->save() Returns true on success. #pw-group-manipulation
 * @method bool saveField(Page $page, $field, array $options = array()) Save just the named field from $page. Same as: $page->save('field') #pw-group-manipulation
 * @method bool trash(Page $page, $save = true) Move a page to the trash. If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again. #pw-group-manipulation
 * @method bool restore(Page $page, $save = true) Restore a trashed page to its original location. #pw-group-manipulation
 * @method int|array emptyTrash(array $options = array()) Empty the trash and return number of pages deleted. #pw-group-manipulation
 * @method bool delete(Page $page, $recursive = false, array $options = array()) Permanently delete a page and it's fields. Unlike trash(), pages deleted here are not restorable. If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown. #pw-group-manipulation
 * @method Page|NullPage clone(Page $page, Page $parent = null, $recursive = true, $options = array()) Clone an entire page, it's assets and children and return it. #pw-group-manipulation
 * @method Page|NullPage add($template, $parent, $name = '', array $values = array()) #pw-group-manipulation
 * @method int sort(Page $page, $value = false) Set the “sort” value for given $page while adjusting siblings, or re-build sort for its children. #pw-group-manipulation
 * @method setupNew(Page $page) Setup new page that does not yet exist by populating some fields to it. #pw-internal
 * @method string setupPageName(Page $page, array $options = array()) Determine and populate a name for the given page. #pw-internal
 * @method void insertBefore(Page $page, Page $beforePage) Insert one page as a sibling before another. #pw-advanced
 * @method void insertAfter(Page $page, Page $afterPage) Insert one page as a sibling after another. #pw-advanced
 * 
 * METHODS PURELY FOR HOOKS
 * ========================
 * You can hook these methods, but you should not call them directly. 
 * See the phpdoc in the actual methods for more details about arguments and additional properties that can be accessed.
 * 
 * @method saveReady(Page $page) Hook called just before a page is saved. 
 * @method saved(Page $page, array $changes = array(), $values = array()) Hook called after a page is successfully saved. 
 * @method added(Page $page) Hook called when a new page has been added. 
 * @method moved(Page $page) Hook called when a page has been moved from one parent to another. 
 * @method templateChanged(Page $page) Hook called when a page template has been changed. 
 * @method trashed(Page $page) Hook called when a page has been moved to the trash. 
 * @method restored(Page $page) Hook called when a page has been moved OUT of the trash. 
 * @method deleteReady(Page $page) Hook called just before a page is deleted. 
 * @method deleted(Page $page) Hook called after a page has been deleted. 
 * @method cloneReady(Page $page, Page $copy) Hook called just before a page is cloned. 
 * @method cloned(Page $page, Page $copy) Hook called after a page has been successfully cloned. 
 * @method renamed(Page $page) Hook called after a page has been successfully renamed. 
 * @method sorted(Page $page, $children = false, $total = 0) Hook called after $page has been sorted.
 * @method statusChangeReady(Page $page) Hook called when a page's status has changed and is about to be saved.
 * @method statusChanged(Page $page) Hook called after a page status has been changed and saved. 
 * @method publishReady(Page $page) Hook called just before an unpublished page is published. 
 * @method published(Page $page) Hook called after an unpublished page has just been published. 
 * @method unpublishReady(Page $page) Hook called just before a pubished page is unpublished. 
 * @method unpublished(Page $page) Hook called after a published page has just been unpublished. 
 * @method saveFieldReady(Page $page, Field $field) Hook called just before a saveField() method saves a page fied. 
 * @method savedField(Page $page, Field $field) Hook called after saveField() method successfully executes. 
 * @method savePageOrFieldReady(Page $page, $fieldName = '') Hook inclusive of both saveReady() and saveFieldReady().
 * @method savedPageOrField(Page $page, array $changes) Hook inclusive of both saved() and savedField().
 * @method found(PageArray $pages, array $details) Hook called at the end of a $pages->find().
 *
 * TO-DO
 * =====
 * @todo Add a getCopy method that does a getById($id, array('cache' => false) ?
 * @todo Update saveField to accept array of field names as an option. 
 *
 */

class Pages extends Wire {

	/**
	 * Max length for page name
	 * 
	 */
	const nameMaxLength = 128;

	/**
	 * Default name for the root/home page
	 * 
	 */
	const defaultRootName = 'home';

	/**
	 * Instance of PagesSortfields
	 *
	 */
	protected $sortfields;

	/**
	 * Current debug state
	 * 
	 * @var bool
	 * 
	 */
	protected $debug = false;

	/**
	 * Runtime debug log of Pages class activities, see getDebugLog()
	 *
	 */
	protected $debugLog = array();

	/**
	 * @var PagesLoader
	 * 
	 */
	protected $loader;

	/**
	 * @var PagesEditor
	 * 
	 */
	protected $editor;

	/**
	 * @var PagesNames
	 * 
	 */
	protected $names;

	/**
	 * @var PagesLoaderCache
	 * 
	 */
	protected $cacher;

	/**
	 * @var PagesTrash
	 * 
	 */
	protected $trasher;

	/**
	 * Array of PagesType managers
	 * 
	 * @var PagesType[]
	 * 
	 */
	protected $types = array();

	/**
	 * Create the Pages object
	 * 
	 * @param ProcessWire $wire
	 *
	 */
	public function __construct(ProcessWire $wire) {
		$this->setWire($wire);
		$this->debug = $wire->config->debug === Config::debugVerbose ? true : false;
		$this->sortfields = $this->wire(new PagesSortfields());
		$this->loader = $this->wire(new PagesLoader($this));
		$this->cacher = $this->wire(new PagesLoaderCache($this));
		$this->trasher = null;
		$this->editor = null;
	}
	
	/**
	 * Initialize $pages API var by preloading some pages
	 * 
	 * #pw-internal
	 *
	 */
	public function init() {
		$this->loader->getById($this->wire('config')->preloadPageIDs);
	}

	/****************************************************************************************************************
	 * BASIC PUBLIC PAGES API METHODS
	 * 
	 */

	/**
	 * Count and return how many pages will match the given selector. 
	 * 
	 * If no selector provided, it returns count of all pages in site. 
	 * 
	 * ~~~~~~~~~
	 * // Return count of how may pages in the site use the blog-post template
	 * $numBlogPosts = $pages->count("template=blog-post");
	 * ~~~~~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|array|Selectors $selector Specify selector, or omit to retrieve a site-wide count.
	 * @param array|string $options See $options for $pages->find().
	 * @return int
	 * @see Pages::find()
	 *
	 */
	public function count($selector = '', $options = array()) {
		return $this->loader->count($selector, $options);
	}

	/**
	 * Given a Selector string, return the Page objects that match in a PageArray.
	 * 
	 * - This is one of the most commonly used API methods in ProcessWire. 
	 * - If you only need to find one page, use the `Pages::get()` or `Pages::findOne()` method instead (and note the difference). 
	 * - If you need to find a huge quantity of pages (like thousands) without limit or pagination, look at the `Pages::findMany()` method. 
	 * 
	 * ~~~~~
	 * // Find all pages using template "building" with 25 or more floors
	 * $skyscrapers = $pages->find("template=building, floors>=25");
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string|int|array|Selectors $selector Specify selector (standard usage), but can also accept page ID or array of page IDs.
	 * @param array|string $options One or more options that can modify certain behaviors. May be associative array or "key=value" selector string.
	 *  - `findOne` (boolean): Apply optimizations for finding a single page (default=false).
	 *  - `findAll` (boolean): Find all pages with no exclusions, same as "include=all" option (default=false). 
	 *  - `findIDs` (boolean|int): Specify 1 to return array of only page IDs, or true to return verbose array (default=false).
	 *  - `getTotal` (boolean): Whether to set returning PageArray's "total" property (default=true, except when findOne=true).
	 *  - `loadPages` (boolean): Whether to populate the returned PageArray with found pages (default=true). 
	 *	   The only reason why you'd want to change this to false would be if you only needed the count details from 
	 *	   the PageArray: getTotal(), getStart(), getLimit, etc. This is intended as an optimization for $pages->count().
	 * 	   Does not apply if $selector argument is an array. 
	 *  - `cache` (boolean): Allow caching of selectors and loaded pages? (default=true). Also sets loadOptions[cache].
	 *  - `allowCustom` (boolean): Allow use of _custom="another selector" in given $selector? For specific uses. (default=false)
	 *  - `caller` (string): Optional name of calling function, for debugging purposes, i.e. "pages.count" (default=blank).
	 *  - `include` (string): Optional inclusion mode of 'hidden', 'unpublished' or 'all'. (default=none). Typically you would specify this 
	 *     directly in the selector string, so the option is mainly useful if your first argument is not a string. 
	 *  - `stopBeforeID` (int): Stop loading pages once page matching this ID is found (default=0).
	 *  - `startAfterID` (int): Start loading pages once page matching this ID is found (default=0). 
	 *  - `lazy` (bool): Specify true to force lazy loading. This is the same as using the Pages::findMany() method (default=false).
	 *  - `loadOptions` (array): Optional associative array of options to pass to getById() load options.
	 * @return PageArray|array PageArray of that matched the given selector, or array of page IDs (if using findIDs option).
	 * 
	 * Non-visible pages are excluded unless an "include=x" mode is specified in the selector
	 * (where "x" is "hidden", "unpublished" or "all"). If "all" is specified, then non-accessible
	 * pages (via access control) can also be included.
	 * @see Pages::findOne(), Pages::findMany(), Pages::get()
	 *
	 */
	public function ___find($selector, $options = array()) {
		return $this->loader->find($selector, $options);
	}

	/**
	 * Like find() but returns only the first match as a Page object (not PageArray).
	 * 
	 * This is functionally similar to the `get()` method except that its default behavior is to
	 * filter for access control and hidden/unpublished/etc. states, in the same way that the
	 * `find()` method does. You can add an `include=...` to your selector string to bypass. 
	 * This method also accepts an `$options` array, whereas `get()` does not. 
	 * 
	 * ~~~~~~ 
	 * // Find the newest page using the blog-post template
	 * $blogPost = $pages->findOne("template=blog-post, sort=-created");
	 * ~~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|array|Selectors $selector Selector string, array or Selectors object
	 * @param array|string $options See $options for $pages->find()
	 * @return Page|NullPage Returns a Page on success, or a NullPage (having id=0) on failure
	 * @since 3.0.0
	 * @see Pages::get(), Pages::find(), Pages::findMany()
	 *
	 */
	public function findOne($selector, $options = array()) {
		return $this->loader->findOne($selector, $options);
	}

	/**
	 * Like find(), but with “lazy loading” to support giant result sets without running out of memory.
	 * 
	 * When using this method, you can retrieve tens of thousands, or hundreds of thousands of pages 
	 * or more, without needing a pagination "limit" in your selector. Individual pages are loaded
	 * and unloaded in chunks as you iterate them, making it possible to iterate all pages without
	 * running out of memory. This is useful for performing some kind of calculation on all pages or
	 * other tasks like that. Note however that if you are building something from the result set
	 * that consumes more memory for each page iterated (like concatening a string of page titles 
	 * or something), then you could still run out of memory there. 
	 *
	 * The example below demonstrates use of this method. Note that attempting to do the same using
	 * the regular `$pages->find()` would run out of memory, as it's unlikely the server would have
	 * enough memory to store 20k pages in memory at once. 
	 * 
	 * ~~~~~
	 * // Calculating a total from 20000 pages
	 * $totalCost = 0;
	 * $items = $pages->findMany("template=foo"); // 20000 pages
	 * foreach($items as $item) {
	 *   $totalCost += $item->cost; 
	 * }
	 * echo "Total cost is: $totalCost";
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|array|Selectors $selector Selector to find pages
	 * @param array $options Options to modify behavior. See `Pages::find()` $options argument for details. 
	 * @return PageArray
	 * @since 3.0.19
	 * @see Pages::find(), Pages::findOne()
	 *
	 */
	public function findMany($selector, $options = array()) {
		$debug = $this->debug;
		if($debug) $this->debug(false);
		$options['lazy'] = true;
		$options['caller'] = 'pages.findMany';
		if(!isset($options['cache'])) $options['cache'] = false;
		$matches = $this->find($selector, $options);
		if($debug) $this->debug($debug);
		return $matches;
	}

	/**
	 * Like $pages->find() except returns array of IDs rather than Page objects.
	 * 
	 * - This is a faster method to use when you only need to know the matching page IDs. 
	 * - The default behavior is to simply return a regular PHP array of matching page IDs in order. 
	 * - The alternate behavior (verbose) returns more information for each match, as outlined below. 
	 * 
	 * **Verbose option:**  
	 * When specifying boolean true for the `$options` argument (or using the `verbose` option), 
	 * the return value is an array of associative arrays, with each of those associative arrays
	 * containing `id`, `parent_id` and `templates_id` keys for each page. 
	 * 
	 * ~~~~~
	 * // returns array of page IDs (integers) like [ 1234, 1235, 1236 ]
	 * $a = $pages->findIDs("foo=bar");
	 * 
	 * // verbose option: returns array of associative arrays, each with id, parent_id and templates_id 
	 * $a = $pages->findIDs("foo=bar", true);
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string|array|Selectors $selector Selector to find page IDs. 
	 * @param array|bool $options Options to modify behavior. 
	 *  - `verbose` (bool): Specify true to make return value array of associative arrays, each with verbose info. 
	 *  - The verbose option above can also be specified by providing boolean true as the $options argument.
	 *  - See `Pages::find()` $options argument for additional options. 
	 * @return array Array of page IDs, or in verbose mode: array of arrays, each with id, parent_id and templates_id keys.
	 * @since 3.0.46
	 * 
	 */
	public function findIDs($selector, $options = array()) {
		$verbose = false;
		if($options === true) $verbose = true;
		if(!is_array($options)) $options = array();
		if(isset($options['verbose'])) {
			$verbose = $options['verbose'];
			unset($options['verbose']);
		}
		$options['findIDs'] = $verbose ? true : 1;
		/** @var array $ids */
		$ids = $this->find($selector, $options);
		return $ids;
	}

	/**
	 * Returns the first page matching the given selector with no exclusions
	 * 
	 * Use this method when you need to retrieve a specific page without exclusions for access control or page status.
	 * 
	 * ~~~~~~
	 * // Get a page by ID
	 * $p = $pages->get(1234);
	 * 
	 * // Get a page by path
	 * $p = $pages->get('/about/contact/');
	 * 
	 * // Get a random 'skyscraper' page by selector string
	 * $p = $pages->get('template=skyscraper, sort=random'); 
	 * ~~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|array|Selectors|int $selector Selector string, array or Selectors object. May also be page path or ID. 
	 * @param array $options See `Pages::find()` for extra options that may be specified. 
	 * @return Page|NullPage Always returns a Page object, but will return NullPage (with id=0) when no match found.
	 * @see Pages::findOne(), Pages::find()
	 * 
	 */
	public function get($selector, $options = array()) {
		return $this->loader->get($selector, $options); 
	}
	
	/**
	 * Save a page object and its fields to database.
	 *
	 * If the page is new, it will be inserted. If existing, it will be updated.
	 * This is the same as calling `$page->save()`. If you want to just save a particular field 
	 * in a Page, use `$page->save($fieldName)` instead.
	 * 
	 * ~~~~~~
	 * // Modify a page and save it
	 * $p = $pages->get('/festivals/decatur/beer/'); 
	 * $p->of(false); // turn off output formatting, if it's on
	 * $p->title = "Decatur Beer Festival";
	 * $p->summary = "Come and enjoy fine beer and good company at the Decatur Beer Festival.";
	 * $pages->save($p); 
	 * ~~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Page $page Page object to save
	 * @param array $options Optional array to modify default behavior, with one or more of the following:
	 * - `uncacheAll` (boolean): Whether the memory cache should be cleared (default=true).
	 * - `resetTrackChanges` (boolean): Whether the page's change tracking should be reset (default=true).
	 * - `quiet` (boolean): When true, modified date and modified_users_id won't be updated (default=false).
	 * - `adjustName` (boolean): Adjust page name to ensure it is unique within its parent (default=false).
	 * - `forceID` (integer): Use this ID instead of an auto-assigned one (new page) or current ID (existing page).
	 * - `ignoreFamily` (boolean): Bypass check of allowed family/parent settings when saving (default=false).
	 * - `noHooks` (boolean): Prevent before/after save hooks (default=false), please also use $pages->___save() for call.
	 * - `noFields` (boolean): Bypass saving of custom fields, leaving only native properties to be saved (default=false).
	 * @return bool True on success, false on failure
	 * @throws WireException
	 * @see Page::save(), Pages::saveField()
	 *
	 */
	public function ___save(Page $page, $options = array()) {
		return $this->editor()->save($page, $options);
	}

	/**
	 * Save only a field from the given page 
	 *
	 * This is the same as calling `$page->save($field)`.
	 * 
	 * ~~~~~
	 * // Update the summary field on $page and save it
	 * $page->summary = "Those who know do not speak. Those who speak do not know.";
	 * $pages->saveField($page, 'summary');
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Page $page Page to save
	 * @param string|Field $field Field object or name (string)
	 * @param array|string $options Optionally specify one or more of the following to modify default behavior:
	 * - `quiet` (boolean): Specify true to bypass updating of modified user and time (default=false). 
	 * - `noHooks` (boolean): Prevent before/after save hooks (default=false), please also use $pages->___saveField() for call.
	 * @return bool True on success, false on failure
	 * @throws WireException
	 * @see Page::save(), Page::setAndSave(), Pages::save()
	 *
	 */
	public function ___saveField(Page $page, $field, $options = array()) {
		return $this->editor()->saveField($page, $field, $options);
	}

	/**
	 * Add a new page using the given template and parent
	 *
	 * If no page "name" is specified, one will be automatically assigned. 
	 * 
	 * ~~~~~
	 * // Add new page using 'skyscraper' template into Atlanta
	 * $building = $pages->add('skyscraper', '/skyscrapers/atlanta/');
	 * 
	 * // Same as above, but with specifying a name/title as well:
	 * $building = $pages->add('skyscraper', '/skyscrapers/atlanta/', 'Symphony Tower');
	 * 
	 * // Same as above, but with specifying several properties: 
	 * $building = $pages->add('skyscraper', '/skyscrapers/atlanta/', [
	 *   'title' => 'Symphony Tower',
	 *   'summary' => 'A 41-story skyscraper located at 1180 Peachtree Street', 
	 *   'height' => 657,
	 *   'floors' => 41
	 * ]);
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string|Template $template Template name or Template object
	 * @param string|int|Page $parent Parent path, ID or Page object
	 * @param string $name Optional name or title of page. If none provided, one will be automatically assigned.
	 * 	If you want to specify a different name and title then specify the $name argument, and $values['title'].
	 * @param array $values Field values to assign to page (optional). If $name is omitted, this may also be 3rd param.
	 * @return Page New page ready to populate. Note that this page has output formatting off.
	 * @throws WireException When some criteria prevents the page from being saved.
	 *
	 */
	public function ___add($template, $parent, $name = '', array $values = array()) {
		return $this->editor()->add($template, $parent, $name, $values);
	}
	
	/**
	 * Clone entire page return it.
	 * 
	 * This also clones any file assets assets associated with the page. The clone is recursive
	 * by default, cloning children (and so on) as well. To clone only the page without children,
	 * specify false for the `$recursive` argument. 
	 * 
	 * Warning: this method can fail when recursive and cloning a page with huge amounts of 
	 * children (or descendent family), and adequate resources (like memory or time limit) are
	 * not available.
	 * 
	 * ~~~~~
	 * // Clone the Westin Peachtree skyscraper page
	 * $building = $pages->get('/skyscrapers/atlanta/westin-peachtree/');
	 * $copy = $pages->clone($building); 
	 * 
	 * // Bonus: Now that the clone exists, lets move and rename it 
	 * $copy->parent = '/skyscrapers/detroit/';
	 * $copy->title = 'Renaissance Center';
	 * $copy->name = 'renaissance-center';
	 * $copy->save();
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Page $page Page that you want to clone
	 * @param Page|null $parent New parent, if different (default=null, which implies same parent)
	 * @param bool $recursive Clone the children too? (default=true)
	 * @param array|string $options Options that can be passed to modify default behavior of clone or save:
	 *  - `forceID` (int): force a specific ID.
	 *  - `set` (array): Array of properties to set to the clone (you can also do this later).
	 *  - `recursionLevel` (int): recursion level, for internal use only.
	 * @return Page|NullPage The newly cloned Page or a NullPage() with id=0 if unsuccessful.
	 * @throws WireException|\Exception on fatal error
	 *
	 */
	public function ___clone(Page $page, Page $parent = null, $recursive = true, $options = array()) {
		return $this->editor()->_clone($page, $parent, $recursive, $options);
	}

	/**
	 * Permanently delete a page, its fields and assets. 
	 *
	 * Unlike trash(), pages deleted here are not restorable. If you attempt to delete a page with children, 
	 * and don't specifically set the `$recursive` argument to `true`, then this method will throw an exception. 
	 * If a recursive delete fails for any reason, an exception will also will be thrown.
	 * 
	 * ~~~~~
	 * // Delete a product page
	 * $product = $pages->get('/products/foo-bar-widget/'); 
	 * $pages->delete($product); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Page $page Page to delete
	 * @param bool|array $recursive If set to true, then this will attempt to delete all children too.
	 *   If you don't need this argument, optionally provide $options array instead.
	 * @param array $options Optional settings to change behavior:
	 *   - uncacheAll (bool): Whether to clear memory cache after delete (default=false)
	 *   - recursive (bool): Same as $recursive argument, may be specified in $options array if preferred.
	 * @return bool|int Returns true (success), or integer of quantity deleted if recursive mode requested.
	 * @throws WireException on fatal error
	 * @see Pages::trash()
	 * 
	 */
	public function ___delete(Page $page, $recursive = false, array $options = array()) {
		return $this->editor()->delete($page, $recursive, $options);
	}

	/**
	 * Move a page to the trash
	 *
	 * When a page is moved to the trash, it is in a "delete pending" state. Once trashed, the page can be either restored 
	 * to its original location, or permanently deleted (when the trash is emptied). 
	 * 
	 * ~~~~~
	 * // Trash a product page
	 * $product = $pages->get('/products/foo-bar-widget/');
	 * $pages->trash($product); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Page $page Page to trash
	 * @param bool $save Set to false if you will perform your own save() call afterwards to complete the operation. Omit otherwise. Primarily for internal use.
	 * @return bool Returns true on success, false on failure.
	 * @throws WireException
	 * @see Pages::restore(), Pages::emptyTrash(), Pages::delete()
	 *
	 */
	public function ___trash(Page $page, $save = true) {
		// If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again.
		return $this->trasher()->trash($page, $save);
	}

	/**
	 * Restore a page in the trash back to its original location and state
	 *
	 * If you want to restore the page to some location other than its original location, set the `$page->parent` property
	 * of the page to contain the location you want it to restore to. Otherwise the page will restore to its original location,
	 * when possible to do so. 
	 * 
	 * ~~~~~
	 * // Grab a page from the trash and restore it
	 * $trashedPage = $pages->get(1234); 
	 * $pages->restore($trashedPage); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Page $page Page that is in the trash that you want to restore
	 * @param bool $save Set to false if you only want to prep the page for restore (i.e. you will save the page yourself later). Primarily for internal use.
	 * @return bool True on success, false on failure.
	 * @see Pages::trash()
	 *
	 */
	public function ___restore(Page $page, $save = true) {
		return $this->trasher()->restore($page, $save);
	}

	/****************************************************************************************************************
	 * ADVANCED PAGES API METHODS (more for internal use)
	 *
	 */
	
	/**
	 * Delete all pages in the trash
	 *
	 * Note that once the trash is emptied, pages in the trash are permanently deleted. 
	 * This method populates error notices when there are errors deleting specific pages.
	 * 
	 * ~~~~~
	 * // Empty the trash
	 * $pages->emptyTrash();
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param array $options See PagesTrash::emptyTrash() for advanced options
	 * @return int|array Returns total number of pages deleted from trash, or array if verbose option specified.
	 * 	This number is negative or 0 if not all pages could be deleted and error notices may be present.
	 * @see Pages::trash(), Pages::restore()
	 *
	 */
	public function ___emptyTrash(array $options = array()) {
		return $this->trasher()->emptyTrash($options);
	}
	
	/**
	 * Given an array or CSV string of Page IDs, return a PageArray 
	 * 
	 * Note that this method is primarily for internal use and most of the options available are specific to the needs
	 * of core methods that utilize them. All pages loaded by ProcessWire pass through this method. 
	 *
	 * Optionally specify an `$options` array rather than a template for argument 2. When present, the `template` and `parent_id` 
	 * arguments may be provided in the given $options array. These options may be specified: 
	 * 
	 * **LOAD OPTIONS (argument 2 array):** 
	 * 
	 * - `cache` (boolean): Place loaded pages in memory cache? (default=true)
	 * - `getFromCache` (boolean): Allow use of previously cached pages in memory (rather than re-loading it from DB)? (default=true)
	 * - `template` (Template): Instance of Template, see the $template argument for details.
	 * - `parent_id` (integer): Parent ID, see $parent_id argument for details.
	 * - `getNumChildren` (boolean): Specify false to disable retrieval and population of 'numChildren' Page property. (default=true)
	 * - `getOne` (boolean): Specify true to return just one Page object, rather than a PageArray. (default=false)
	 * - `autojoin` (boolean): Allow use of autojoin option? (default=true)
	 * - `joinFields` (array): Autojoin the field names specified in this array, regardless of field settings (requires autojoin=true). (default=empty)
	 * - `joinSortfield` (boolean): Whether the 'sortfield' property will be joined to the page. (default=true)
	 * - `findTemplates` (boolean): Determine which templates will be used (when no template specified) for more specific autojoins. (default=true)
	 * - `pageClass` (string): Class to instantiate Page objects with. Leave blank to determine from template. (default=auto-detect)
	 * - `pageArrayClass` (string): PageArray-derived class to store pages in (when 'getOne' is false). (default=PageArray)
	 * - `pageArray` (PageArray|null): Populate this existing PageArray rather than creating a new one. (default=null)
	 * - `page` (Page|null): Existing Page object to populate (also requires the getOne option to be true). (default=null)
	 * 
	 * **Use the `$options` array for potential speed optimizations:**
	 * 
	 * - Specify a `template` with your call, when possible, so that this method doesn't have to determine it separately. 
	 * - Specify false for `getNumChildren` for potential speed optimization when you know for certain pages will not have children. 
	 * - Specify false for `autojoin` for potential speed optimization in certain scenarios (can also be a bottleneck, so be sure to test). 
	 * - Specify false for `joinSortfield` for potential speed optimization when you know the Page will not have children or won't need to know the order.
	 * - Specify false for `findTemplates` so this method doesn't have to look them up. Potential speed optimization if you have few autojoin fields globally.
	 * - Note that if you specify false for `findTemplates` the pageClass is assumed to be 'Page' unless you specify something different for the 'pageClass' option.
	 * 
	 * ~~~~~
	 * // Retrieve pages by IDs in CSV string
	 * $items = $pages->getById("1111,2222,3333");
	 * 
	 * // Retrieve pages by IDs in PHP array
	 * $items = $pages->getById([1111,2222,3333]);
	 * 
	 * // Specify that retrieved pages are using template 'skyscraper' as an optimization
	 * $items = $pages->getById([1111,2222,3333], $templates->get('skyscraper')); 
	 * 
	 * // Retrieve pages with $options array
	 * $items = $pages->getById([1111,2222,3333], [
	 *   'template' => $templates->get('skyscraper'), 
	 *   'parent_id' => 1024
	 * ]);
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @param array|WireArray|string $_ids Array of Page IDs or CSV string of Page IDs.
	 * @param Template|array|null $template Specify a template to make the load faster, because it won't have to attempt to join all possible fields... just those used by the template. 
	 *	Optionally specify an $options array instead, see the method notes above. 
	 * @param int|null $parent_id Specify a parent to make the load faster, as it reduces the possibility for full table scans. 
	 *	This argument is ignored when an options array is supplied for the $template. 
	 * @return PageArray|Page Returns Page only if the 'getOne' option is specified, otherwise always returns a PageArray.
	 * @throws WireException
	 * 
	 */
	public function getById($_ids, $template = null, $parent_id = null) {
		return $this->loader->getById($_ids, $template, $parent_id);
	}
	
	/**
	 * Given an ID, return a path to a page, without loading the actual page
	 *
	 * 1. Always returns path in default language, unless a language argument/option is specified.
	 * 2. Path may be different from 'url' as it doesn't include the root URL at the beginning.
	 * 3. In most cases, it's preferable to use `$page->path()` rather than this method. This method is
	 *    here just for cases where a path is needed without loading the page.
	 * 4. It's possible for there to be `Page::path()` hooks, and this method completely bypasses them,
	 *    which is another reason not to use it unless you know such hooks aren't applicable to you.
	 * 
	 * ~~~~~
	 * // Get the path for page having ID 1234
	 * $path = $pages->getPath(1234);
	 * echo "Path for page 1234 is: $path";
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @param int|Page $id ID of the page you want the path to
	 * @param null|array|Language|int|string $options Specify $options array or Language object, id or name. Allowed options include: 
	 *  - `language` (int|string|anguage): To retrieve in non-default language, specify language object, ID or name (default=null)
	 *  - `useCache` (bool): Allow pulling paths from already loaded pages? (default=true)
	 *  - `usePagePaths` (bool): Allow pulling paths from PagePaths module, if installed? (default=true)
	 * @return string Path to page or blank on error/not-found.
	 * @since 3.0.6
	 * @see Page::path()
	 *
	 */
	public function getPath($id, $options = array()) {
		return $this->loader->getPath($id, $options);
	}

	/**
	 * Alias of getPath method for backwards compatibility
	 *
	 * @param int $id
	 * @return string
	 *
	 */
	public function _path($id) {
		return $this->loader->getPath($id);
	}

	/**
	 * Get a page by its path, similar to $pages->get('/path/to/page/') but with more options
	 *
	 * 1. There are no exclusions for page status or access. If needed, you should validate access
	 *    on any page returned from this method.
	 * 2. In a multi-language environment, you must specify the `$useLanguages` option to be true, if you
	 *    want a result for a $path that is (or might be) a multi-language path. Otherwise, multi-language
	 *    paths will make this method return a NullPage (or 0 if getID option is true).
	 * 3. Partial paths may also match, so long as the partial path is completely unique in the site.
	 *    If you don't want that behavior, double check the path of the returned page.
	 * 
	 * ~~~~~
	 * // Get a page by path 
	 * $p = $pages->getByPath('/skyscrapers/atlanta/191-peachtree/');
	 * 
	 * // Now validate that the page we retrieved is valid
	 * if($p->id && $p->viewable()) {
	 *   // Page is valid to display
	 * }
	 * 
	 * // Get a page by path with options
	 * $p = $pages->getByPath('/products/widget/', [
	 *   'useLanguages' => true, 
	 *   'useHistory' => true
	 * ]);
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @param string $path Path of page you want to retrieve.
	 * @param array|bool $options array of options (below), or specify boolean for $useLanguages option only.
	 *  - `getID` (int): Specify true to just return the page ID (default=false).
	 *  - `useLanguages` (bool): Specify true to allow retrieval by language-specific paths (default=false).
	 *  - `useHistory` (bool): Allow use of previous paths used by the page, if PagePathHistory module is installed (default=false).
	 * @return Page|int
	 * @since 3.0.6
	 *
	 */
	public function getByPath($path, $options = array()) {
		return $this->loader->getByPath($path, $options);
	}
	
	/**
	 * Auto-populate some fields for a new page that does not yet exist
	 *
	 * Currently it does this: 
	 * - Sets up a unique page->name based on the format or title if one isn't provided already. 
	 * - Assigns a 'sort' value'. 
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 *
	 */
	public function ___setupNew(Page $page) {
		return $this->editor()->setupNew($page);
	}

	/**
	 * Auto-assign a page name to the given page
	 * 
	 * Typically this would be used only if page had no name or if it had a temporary untitled name.
	 * 
	 * Page will be populated with the name given. This method will not populate names to pages that
	 * already have a name, unless the name is "untitled"
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param array $options 
	 * 	- format: Optionally specify the format to use, or leave blank to auto-determine.
	 * @return string If a name was generated it is returned. If no name was generated blank is returned. 
	 * 
	 */
	public function ___setupPageName(Page $page, array $options = array()) {
		return $this->editor()->setupPageName($page, $options);
	}

	/**
	 * Update page modification time to now (or the given modification time)
	 * 
	 * This behaves essentially the same as the unix `touch` command, but for ProcessWire pages. 
	 * 
	 * ~~~~~
	 * // Touch the current $page to current date/time
	 * $pages->touch($page);
	 * 
	 * // Touch the current $page and set modification date to 2016/10/24
	 * $pages->touch($page, "2016-10-24 00:00"); 
	 * 
	 * // Touch all "skyscraper" pages in "Atlanta" to current date/time
	 * $skyscrapers = $pages->find("template=skyscraper, parent=/cities/atlanta/"); 
	 * $pages->touch($skyscrapers); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Page|PageArray|array $pages May be Page, PageArray or array of page IDs (integers).
	 * @param null|int|string $modified Omit to update to now, or specify unix timestamp or strtotime() recognized time string
	 * @throws WireException if given invalid format for $modified argument or failed database query
	 * @return bool True on success, false on fail
	 * @since 3.0.0
	 *
	 */
	public function ___touch($pages, $modified = null) {
		return $this->editor()->touch($pages, $modified);
	}
	
	/**
	 * Set the “sort” value for given $page while adjusting siblings, or re-build sort for its children
	 * 
	 * *This method is primarily applicable to manually sorted pages. If pages are automatically
	 * sorted by some other field, this method isn’t useful unless using the “re-build children” option, 
	 * which may be helpful if converting a page’s children from auto-sort to manual sort.*
	 *
	 * The default behavior of this method is to set the “sort” value for the given $page, and adjust the 
	 * sort value of sibling pages having the same or greater sort value, to ensure all are unique and in
	 * order without gaps. 
	 * 
	 * The alternate behavior of this method is to re-build the sort values of all children of the given $page. 
	 * This is done by specifying boolean true for the $value argument. When used, duplicate sort values and
	 * gaps are removed from all children. 
	 * 
	 * **Do you need this method?**  
	 * If you are wondering whether you need to use this method for something, chances are that you do not.
	 * This method is mostly applicable for internal core use, as ProcessWire manages Page sort values on its own
	 * internally for the most part. 
	 * 
	 * ~~~~~
	 * // set $page to have sort=5, moving any 5+ sort pages ahead
	 * $pages->sort($page, 5);
	 * 
	 * // re-build sort values for children of $page, removing duplicates and gaps
	 * $pages->sort($page, true);
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @param Page $page Page to sort (or parent of pages to sort, if using $value=true option)
	 * @param int|bool $value Specify one of the following:
	 *  - Omit to set and use sort value from given $page.
	 *  - Specify sort value (integer) to save that value.
	 *  - Specify boolean true to instead rebuild sort for all of $page children.
	 * @return int Number of pages that had sort values adjusted
	 * @throws WireException 
	 *
	 */
	public function ___sort(Page $page, $value = false) {
		if($value === false) $value = $page->sort;
		if($value === true) return $this->editor()->sortRebuild($page);
		return $this->editor()->sortPage($page, $value);
	}

	/**
	 * Sort/move one page above another (for manually sorted pages)
	 * 
	 * #pw-advanced
	 *
	 * @param Page $page Page you want to move/sort 
	 * @param Page $beforePage Page you want to insert before
	 * @throws WireException
	 *
	 */
	public function ___insertBefore(Page $page, Page $beforePage) {
		$this->editor()->insertBefore($page, $beforePage);
	}

	/**
	 * Sort/move one page after another (for manually sorted pages)
	 * 
	 * #pw-advanced
	 *
	 * @param Page $page Page you want to move/sort
	 * @param Page $afterPage Page you want to insert after
	 * @throws WireException
	 *
	 */
	public function ___insertAfter(Page $page, Page $afterPage) {
		$this->editor()->insertBefore($page, $afterPage, true);
	}
	
	/**
	 * Is the given page in a state where it can be saved from the API?
	 *
	 * Note: this does not account for user permission checking.
	 * It only checks if the page is in a state to be saveable via the API. 
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param string $reason Text containing the reason why it can't be saved (assuming it's not saveable)
	 * @param string|Field $fieldName Optional fieldname to limit check to.
	 * @param array $options Options array given to the original save method (optional)
	 * @return bool True if saveable, False if not
	 *
	 */
	public function isSaveable(Page $page, &$reason, $fieldName = '', array $options = array()) {
		return $this->editor()->isSaveable($page, $reason, $fieldName, $options);
	}
	
	/**
	 * Is the given page deleteable from the API?
	 *
	 * Note: this does not account for user permission checking. 
	 * It only checks if the page is in a state to be deleteable via the API. 
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return bool True if deleteable, False if not
	 *
	 */
	public function isDeleteable(Page $page) {
		return $this->editor()->isDeleteable($page);
	}

	/**
	 * Given a Page ID, return it if it's cached, or NULL of it's not. 
	 *
	 * If no ID is provided, then this will return an array copy of the full cache.
	 *
	 * You may also pass in the string "id=123", where 123 is the page_id
	 * 
	 * #pw-internal
	 *
	 * @param int|string|null $id 
	 * @return Page|array|null
	 *
	 */
	public function getCache($id = null) {
		return $this->cacher->getCache($id);
	}

	/**
	 * Cache the given page. 
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return void
	 *
	 */
	public function cache(Page $page) {
		$this->cacher->cache($page);
	}

	/**
	 * Remove the given page(s) from the cache, or uncache all by omitting $page argument
	 *
	 * When no $page argument is given, this method behaves the same as $pages->uncacheAll().
	 * When any $page argument is given, this does not remove pages from selectorCache.
	 * 
	 * #pw-internal
	 *
	 * @param Page|PageArray|null $page Page to uncache, or omit to uncache all.
	 * @param array $options Additional options to modify behavior: 
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent that call, set this to true. 
	 * @return int Number of pages uncached
	 *
	 */
	public function uncache($page = null, array $options = array()) {
		$cnt = 0;
		if(is_null($page)) {
			$cnt = $this->cacher->uncacheAll(null, $options);
		} else if($page instanceof Page) {
			if($this->cacher->uncache($page, $options)) $cnt++;
		} else if($page instanceof PageArray) {
			foreach($page as $p) {
				if($this->cacher->uncache($p, $options)) $cnt++;
			}
		}
		return $cnt;
	}

	/**
	 * Remove all pages from the cache (to clear memory)
	 * 
	 * This method clears all pages that ProcessWire has cached in memory, making room for more pages to be loaded. 
	 * Use of this method (along with pagination) may be necessary when modifying or calculating from thousand of pages.
	 * 
	 * ~~~~~
	 * // calculate total dollar value of all 50000+ products in inventory
	 * $total = 0;
	 * $start = 0;
	 * $limit = 500;
	 * 
	 * do {
	 *   $products = $pages->find("template=product, start=$start, limit=$limit"); 
	 *   if(!$products->count()) break;
	 *   foreach($products as $product) {
	 *     $total += ($product->qty * $product->price); 
	 *   }
	 *   unset($products);
	 *   $start += $limit; 
	 *   // clear cache to make room for another 500 products
	 *   $pages->uncacheAll();
	 * } while(true);
	 * 
	 * echo "Total value of all products: $" . number_format($total);
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param Page $page Optional Page that initiated the uncacheAll
	 * @param array $options Options to modify default behavior: 
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent that call, set this to true.
	 * @return int Number of pages uncached
	 *
	 */
	public function uncacheAll(Page $page = null, array $options = array()) {
		return $this->cacher->uncacheAll($page, $options);
	}

	/**
	 * For internal Page instance access, return the Pages sortfields property
	 * 
	 * #pw-internal
	 *
	 * @param bool $reset Specify boolean true to reset the Sortfields instance
	 * @return PagesSortFields
	 *
	 */
	public function sortfields($reset = false) {
		if($reset) {
			unset($this->sortfields);
			$this->sortfields = $this->wire(new PagesSortfields());
		}
		return $this->sortfields; 
	}

	/**	
 	 * Return a fuel or other property set to the Pages instance
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __get($key) {
		if($key == 'outputFormatting') return $this->loader->getOutputFormatting(); 
		if($key == 'cloning') return $this->editor()->isCloning(); 
		if($key == 'autojoin') return $this->loader->getAutojoin();
		return parent::__get($key); 
	}

	/**
	 * Set whether loaded pages have their outputFormatting turn on or off
	 *
	 * This affects pages loaded after this method has been called. 
	 * By default, output formatting is turned on on the front-end of the site, 
	 * and off on the back-end (admin) of the site. 
	 * 
	 * See the Pages::of() method alias, which is preferred for the public API.
	 * 
	 * #pw-internal
	 * 
	 * @param bool $outputFormatting
	 *
	 */
	public function setOutputFormatting($outputFormatting = true) {
		$this->loader->setOutputFormatting($outputFormatting);
	}

	/**
	 * Get or set the current output formatting state
	 * 
	 * This affects pages loaded after this method has been called.
	 * By default, output formatting is turned on on the front-end of the site,
	 * and off on the back-end (admin) of the site. 
	 * 
	 * ~~~~~
	 * // Dictate that loaded pages should have output formatting enabled
	 * $pages->of(true);
	 * 
	 * // Get the output formatting state for future loaded pages
	 * if($pages->of()) {
	 *   echo "Output formatting is ON";
	 * } else {
	 *   echo "Output formatting is OFF";
	 * }
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param null|bool $of Specify boolean to set output formatting state, or omit to get output formatting state.
	 * @return bool Returns current output formatting state. 
	 * 
	 */
	public function of($of = null) {
		if($of !== null) $this->setOutputFormatting($of ? true : false);
		return $this->outputFormatting;
	}

	/**
	 * Log a Pages class event
	 *
	 * Only active in debug mode. 
	 * 
	 * #pw-internal
	 *
	 * @param string $action Name of action/function that occurred.
	 * @param string $details Additional details, like a selector string. 
	 * @param string|object The value that was returned.
	 *
	 */
	public function debugLog($action = '', $details = '', $result = '') {
		if(!$this->debug) return;
		$this->debugLog[] = array(
			'time' => microtime(),
			'action' => (string) $action, 
			'details' => (string) $details, 
			'result' => (string) $result
			);
	}

	/**
	 * Get the Pages class debug log
	 *
	 * Only active in debug mode
	 * 
	 * #pw-internal
	 *
	 * @param string $action Optional action within the debug log to find
	 * @return array
	 *
	 */
	public function getDebugLog($action = '') {
		if(!$this->wire('config')->debug) return array();
		if(!$action) return $this->debugLog; 
		$debugLog = array();
		foreach($this->debugLog as $item) if($item['action'] == $action) $debugLog[] = $item; 
		return $debugLog; 
	}

	/**
	 * Return a PageFinder object, ready to use
	 * 
	 * #pw-internal
	 *
	 * @return PageFinder
	 *
	 */
	public function getPageFinder() {
		return $this->wire(new PageFinder());
	}

	/**
	 * Enable or disable use of autojoin for all queries
	 * 
	 * Default should always be true, and you may use this to turn it off temporarily, but
	 * you should remember to turn it back on
	 * 
	 * #pw-internal
	 * 
	 * @param bool $autojoin
	 * 
	 */
	public function setAutojoin($autojoin = true) {
		$this->loader->setAutojoin($autojoin);
	}	

	/**
	 * Return a new/blank PageArray
	 * 
	 * #pw-internal
	 * 
	 * @param array $options Optionally specify ONE of the following: 
	 *  - `pageArrayClass` (string): Name of PageArray class to use (if not “PageArray”).
	 *  - `pageArray` (PageArray): Wire and return this given PageArray, rather than instantiating a new one. 
	 * @return PageArray
	 * 
	 */
	public function newPageArray(array $options = array()) {
		if(!empty($options['pageArray']) && $options['pageArray'] instanceof PageArray) {
			$this->wire($options['pageArray']);
			return $options['pageArray'];
		}
		$class = 'PageArray';
		if(!empty($options['pageArrayClass'])) $class = $options['pageArrayClass'];
		$class = wireClassName($class, true);
		$pageArray = $this->wire(new $class());
		if(!$pageArray instanceof PageArray) $pageArray = $this->wire(new PageArray());
		return $pageArray;
	}

	/**
	 * Return a new/blank Page object (in memory only)
	 * 
	 * #pw-internal
	 *
	 * @param array $options Optionally specify array of any of the following:
	 *   - `pageClass` (string): Class to use for Page object (default='Page').
	 *   - `template` (Template|id|string): Template to use. 
	 *   - Plus any other Page properties or fields you want to set at this time
	 * @return Page
	 *
	 */
	public function newPage(array $options = array()) {
		$class = 'Page';
		if(!empty($options['pageClass'])) $class = $options['pageClass'];
		if(isset($options['template'])) {
			$template = $options['template'];
			if(!is_object($template)) {
				$template = empty($template) ? null : $this->wire('templates')->get($template);
			}
			if($template && empty($options['pageClass']) && $template->pageClass) {
				$class = $template->pageClass;
				if(!wireClassExists($class)) $class = 'Page';
			}	
		} else {
			$template = null;
		}
		
		$class = wireClassName($class, true);
		$page = $this->wire(new $class($template));
		if(!$page instanceof Page) $page = $this->wire(new Page($template));
		
		unset($options['pageClass'], $options['template']); 
		foreach($options as $name => $value) {
			$page->set($name, $value);
		}
		
		return $page;
	}

	/**
	 * Return a new NullPage
	 * 
	 * #pw-internal
	 * 
	 * @return NullPage
	 * 
	 */
	public function newNullPage() {
		$page = new NullPage();
		$this->wire($page);
		return $page;
	}

	/**
	 * Execute a PDO statement, with retry and error handling (deprecated)
	 * 
	 * #pw-internal
	 *
	 * @param \PDOStatement $query
	 * @param bool $throw Whether or not to throw exception on query error (default=true)
	 * @param int $maxTries Max number of times it will attempt to retry query on error
	 * @return bool
	 * @throws \PDOException
	 * @deprecated Use $database->execute() instead
	 *
	 */
	public function executeQuery(\PDOStatement $query, $throw = true, $maxTries = 3) {
		return $this->wire('database')->execute($query, $throw, $maxTries);
	}

	/**
	 * Enables use of $pages(123), $pages('/path/') or $pages('selector string')
	 * 
	 * When given an integer or page path string, it calls $pages->get(key); 
	 * When given a string, it calls $pages->find($key);
	 * When given an array, it calls $pages->getById($key);
	 * 
	 * @param string|int|array $key
	 * @return Page|Pages|PageArray
	 *
	 */
	public function __invoke($key) {
		if(empty($key)) return $this; // no argument
		if(is_int($key)) return $this->get($key); // page ID
		if(is_array($key) && ctype_digit(implode('', $key))) return $this->getById($key); // array of page IDs
		if(is_string($key) && strpos($key, '/') !== false && $this->sanitizer->pagePathName($key) === $key) return $this->get($key); // page path
		return $this->find($key); // selector string or array
	}

	/**
	 * Save to pages activity log, if enabled in config
	 * 
	 * #pw-internal
	 * 
	 * @param $str
	 * @param Page|null Page to log
	 * @return WireLog
	 * 
	 */
	public function log($str, Page $page) {
		if(!in_array('pages', $this->wire('config')->logs)) return parent::___log();
		if($this->wire('process') != 'ProcessPageEdit') $str .= " [From URL: " . $this->wire('input')->url() . "]";
		$options = array('name' => 'pages', 'url' => $page->path); 
		return parent::___log($str, $options); 
	}

	/**
	 * @return PagesLoader
	 * 
	 * #pw-internal
	 *
	 */
	public function loader() {
		return $this->loader;
	}

	/**
	 * @return PagesEditor
	 * 
	 * #pw-internal
	 *
	 */
	public function editor() {
		if(!$this->editor) $this->editor = $this->wire(new PagesEditor($this));
		return $this->editor;
	}
	
	/**
	 * Get Pages API methods specific to generating and modifying page names
	 * 
	 * @return PagesNames
	 *
	 * #pw-advanced
	 *
	 */
	public function names() {
		if(!$this->names) $this->names = $this->wire(new PagesNames($this)); 
		return $this->names;
	}

	/**
	 * @return PagesLoaderCache
	 * 
	 * #pw-internal
	 *
	 */
	public function cacher() {
		return $this->cacher;
	}
	
	/**
	 * @return PagesTrash
	 * 
	 * #pw-internal
	 *
	 */
	public function trasher() {
		if(is_null($this->trasher)) $this->trasher = $this->wire(new PagesTrash($this));
		return $this->trasher;
	}

	/**
	 * Get array of all PagesType managers
	 * 
	 * #pw-internal
	 * 
	 * @param PagesType|string Specify a PagesType object to add a Manager, or specify class name to retrieve manager
	 * @return array|PagesType|null|bool Returns requested type, null if not found, or boolean true if manager added. 
	 * 
	 */
	public function types($type = null) {
		if(!$type) return $this->types;
		if(is_string($type)) return isset($this->types[$type]) ? $this->types[$type] : null;
		if(!$type instanceof PagesType) return null;
		$name = $type->className();
		$this->types[$name] = $type;
		return true;
	}

	/**
	 * Get or set debug state
	 * 
	 * #pw-internal
	 *
	 * @param bool|null $debug
	 * @return bool
	 *
	 */
	public function debug($debug = null) {
		$value = $this->debug;
		if(!is_null($debug)) {
			$this->debug = (bool) $debug;
			$this->loader->debug($debug);
		}
		return $value;
	}

	/***********************************************************************************************************************
	 * COMMON PAGES HOOKS
	 * 
	 */

	/**
	 * Hook called after a page is successfully saved
	 *
	 * This is the same as hooking after `Pages::save`, except that it occurs before other save-related hooks.
	 * Whereas `Pages::save` hooks occur after. In most cases, the distinction does not matter. 
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page The page that was saved
	 * @param array $changes Array of field names that changed
	 * @param array $values Array of values that changed, if values were being recorded, see Wire::getChanges(true) for details.
	 *
	 */
	public function ___saved(Page $page, array $changes = array(), $values = array()) { 
		$str = "Saved page";
		if(count($changes)) $str .= " (Changes: " . implode(', ', $changes) . ")";
		$this->log($str, $page);
		/** @var WireCache $cache */
		$cache = $this->wire('cache');
		$cache->maintenance($page);
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->saved($page, $changes, $values);
		}
	}

	/**
	 * Hook called after a new page has been added
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was added. 
	 *
	 */
	public function ___added(Page $page) { 
		$this->log("Added page", $page);
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->added($page);
		}
		$page->setQuietly('_added', true);
	}

	/**
	 * Hook called when a page has been moved from one parent to another
	 *
	 * Note the previous parent is accessible in the `$page->parentPrevious` property.
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was moved. 
	 *
	 */
	public function ___moved(Page $page) { 
		if($page->parentPrevious) {
			$this->log("Moved page from {$page->parentPrevious->path}$page->name/", $page);
		} else {
			$this->log("Moved page", $page); 
		}
	}

	/**
	 * Hook called when a page's template has been changed
	 *
	 * Note the previous template is available in the `$page->templatePrevious` property. 
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that had its template changed. 
	 *
	 */
	public function ___templateChanged(Page $page) {
		if($page->templatePrevious) {
			$this->log("Changed template on page from '$page->templatePrevious' to '$page->template'", $page);
		} else {
			$this->log("Changed template on page to '$page->template'", $page);
		}
	}

	/**
	 * Hook called when a page has been moved to the trash
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was moved to the trash
	 *
	 */
	public function ___trashed(Page $page) { 
		$this->log("Trashed page", $page);
	}

	/**
	 * Hook called when a page has been moved OUT of the trash (restored)
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was restored
	 *
	 */
	public function ___restored(Page $page) { 
		$this->log("Restored page", $page); 
	}

	/**
	 * Hook called just before a page is saved
	 *
	 * May be preferable to a before `Pages::save` hook because you know for sure a save will 
	 * be executed immediately after this is called. Whereas you don't necessarily know
 	 * that when the before `Pages::save` is called, as an error may prevent it. 
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page The page about to be saved
	 * @return array Optional extra data to add to pages save query, which the hook can populate. 
	 *
	 */
	public function ___saveReady(Page $page) {
		$data = array();
		foreach($this->types as $manager) {
			if(!$manager->hasValidTemplate($page)) continue;
			$a = $manager->saveReady($page);
			if(!empty($a) && is_array($a)) $data = array_merge($data, $a); 
		}
		return $data;
	}

	/**
	 * Hook called when a page is about to be deleted, but before data has been touched
	 *
	 * This is different from a before `Pages::delete` hook because this hook is called once it has 
	 * been confirmed that the page is deleteable and *will* be deleted. 
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that is about to be deleted. 
	 *
	 */
	public function ___deleteReady(Page $page) {
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->deleteReady($page);
		}
	}

	/**
	 * Hook called after a page and its data have been deleted
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was deleted
	 *
	 */
	public function ___deleted(Page $page) { 
		$this->log("Deleted page", $page); 
		/** @var WireCache $cache */
		$cache = $this->wire('cache');
		$cache->maintenance($page);
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->deleted($page);
		}
	}

	/**
	 * Hook called when a page is about to be cloned, but before data has been touched
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The actual clone about to be saved
	 *
	 */
	public function ___cloneReady(Page $page, Page $copy) { }

	/**
	 * Hook called when a page has been cloned
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The completed cloned version of the page
	 *
	 */
	public function ___cloned(Page $page, Page $copy) { 
		$this->log("Cloned page to $copy->path", $page); 
	}

	/**
	 * Hook called when a page has been renamed (i.e. had its name field change)
	 *
	 * The previous name can be accessed at `$page->namePrevious`. 
	 * The new name can be accessed at `$page->name`. 
	 * 
	 * This hook is only called when a page's name changes. It is not called when
	 * a page is moved unless the name was changed at the same time. 
	 * 
	 * **Multi-language note:**  
	 * Also note this hook may be called if a page's multi-language name changes.
	 * In those cases the language-specific name is stored in "name123" while the
	 * previous value is stored in "-name123" (where 123 is the language ID). 
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page The $page that was renamed
	 *
	 */
	public function ___renamed(Page $page) { 
		if($page->namePrevious && $page->namePrevious != $page->name) {
			$this->log("Renamed page from '$page->namePrevious' to '$page->name'", $page);
		}
	}

	/**
	 * Hook called after a page has been sorted, or had its children re-sorted
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page given to have sort adjusted
	 * @param bool $children If true, children of $page have been all been re-sorted
	 * @param int $total Total number of pages that had sort adjusted as a result
	 * 
	 */
	public function ___sorted(Page $page, $children = false, $total = 0) {
		if($page && $children && $total) {}
	}

	/**
	 * Hook called when a page status has been changed and saved
	 *
	 * Previous status may be accessed at `$page->statusPrevious`.
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___statusChanged(Page $page) {
		$status = $page->status; 
		$statusPrevious = $page->statusPrevious; 
		$isPublished = !$page->isUnpublished();
		$wasPublished = !($statusPrevious & Page::statusUnpublished);
		if($isPublished && !$wasPublished) $this->published($page);
		if(!$isPublished && $wasPublished) $this->unpublished($page);
	
		$from = array();
		$to = array();
		foreach(Page::getStatuses() as $name => $flag) {
			if($flag == Page::statusUnpublished) continue; // logged separately
			if($statusPrevious & $flag) $from[] = $name;
			if($status & $flag) $to[] = $name; 
		}
		if(count($from) || count($to)) {
			$added = array();
			$removed = array();
			foreach($from as $name) if(!in_array($name, $to)) $removed[] = $name;
			foreach($to as $name) if(!in_array($name, $from)) $added[] = $name;
			$str = '';
			if(count($added)) $str = "Added status '" . implode(', ', $added) . "'";
			if(count($removed)) {
				if($str) $str .= ". ";
				$str .= "Removed status '" . implode(', ', $removed) . "'";
			}
			if($str) $this->log($str, $page);
		}
	}

	/**
	 * Hook called when a page's status is about to be changed and saved
	 *
	 * Previous status may be accessed at `$page->statusPrevious`.
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___statusChangeReady(Page $page) {
		$isPublished = !$page->isUnpublished();
		$wasPublished = !($page->statusPrevious & Page::statusUnpublished);
		if($isPublished && !$wasPublished) $this->publishReady($page);
		if(!$isPublished && $wasPublished) $this->unpublishReady($page);
	}

	/**
	 * Hook called after an unpublished page has just been published
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___published(Page $page) { 
		$this->log("Published page", $page); 
	}

	/**
	 * Hook called after published page has just been unpublished
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___unpublished(Page $page) { 
		$this->log("Unpublished page", $page); 
	}

	/**
	 * Hook called right before an unpublished page is published and saved
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___publishReady(Page $page) { }

	/**
	 * Hook called right before a published page is unpublished and saved
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page 
	 *
	 */
	public function ___unpublishReady(Page $page) { }

	/**
	 * Hook called at the end of a $pages->find(), includes extra info not seen in the resulting PageArray
	 * 
	 * #pw-hooker
	 *
	 * @param PageArray $pages The pages that were found
	 * @param array $details Extra information on how the pages were found, including: 
	 *  - `pageFinder` (PageFinder): The PageFinder instance that was used.
	 *  - `pagesInfo` (array): The array returned by PageFinder.
	 *  - `options` (array): Options that were passed to $pages->find().
	 *
	 */
	public function ___found(PageArray $pages, array $details) { }

	/**
	 * Hook called when Pages::saveField is ready to execute
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page
	 * @param Field $field
	 * 
	 */
	public function ___saveFieldReady(Page $page, Field $field) { }

	/**
	 * Hook called after Pages::saveField successfully executes
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page
	 * @param Field $field
	 * 
	 */
	public function ___savedField(Page $page, Field $field) { 
		$this->log("Saved page field '$field->name'", $page); 
	}

	/**
	 * Hook called when either of Pages::save or Pages::saveField is ready to execute
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @param string $fieldName Populated only if call originates from saveField
	 *
	 */
	public function ___savePageOrFieldReady(Page $page, $fieldName = '') { }

	/**
	 * Hook called after either of Pages::save or Pages::saveField successfully executes
	 *
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @param array $changes Names of fields
	 *
	 */
	public function ___savedPageOrField(Page $page, array $changes = array()) { }

}


