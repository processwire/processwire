<?php namespace ProcessWire;

/**
 * ProcessWire Pages ($pages API variable)
 * 
 * #pw-headline Pages 
 * #pw-summary The $pages API variable enables loading and manipulation of Page objects, to and from the database.
 * #pw-summary-helpers Methods that point to dedicated Pages helper classes. Use methods from `$pages` rather than the helpers when there is crossover.
 * #pw-summary-add-hooks Methods called automatically when a page is added. You can hook these methods but should not call them directly.
 * #pw-summary-save-hooks Methods called automatically when a page is saved. You can hook these methods but should not call them directly.
 * #pw-summary-move-hooks Methods called automatically when a page is moved, sorted or renamed. You can hook these methods but should not call them directly.
 * #pw-summary-trash-hooks Methods called automatically when a page is trashed or restored. You can hook these methods but should not call them directly.
 * #pw-summary-delete-hooks Methods called automatically when a page is deleted. You can hook these methods but should not call them directly.
 * #pw-summary-status-hooks Methods called automatically when a page status changes. You can hook these methods but should not call them directly.
 * #pw-summary-find-hooks Method called automatically when pages are found. You can hook this method but should not call it directly.
 * #pw-order-groups retrieve,save,add,trash,move,advanced,cache,helpers,hooker
 * #pw-body =
 * Manages Page instances, providing find, load, save and delete capabilities.
 * The implementation for most of the methods in this class are delegated to other classes (helpers)
 * but this class provides the common and hookable interface to all of them. 
 * The `$pages` API variable is the most used object in the ProcessWire API. 
 * The most commonly used API methods include: 
 * - `$pages->find($selector);` Finds and returns multiple pages.
 * - `$pages->get($selector);` Finds a single page with no exclusions. 
 * - `$pages->save($page);` Saves given page. 
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 * @link http://processwire.com/api/variables/pages/ Offical $pages Documentation
 * @link http://processwire.com/api/selectors/ Official Selectors Documentation
 * 
 * 
 * PROPERTIES
 * ==========
 * @property-read bool $autojoin Whether or not autojoin is allowed (typically true) #pw-internal
 * @property-read bool $cloning Whether or not a clone() operation is currently active #pw-internal
 * @property-read bool $outputFormatting Current default output formatting mode. #pw-internal
 * @property-read bool $of Current default output formatting mode, alias of outputFormatting property. #pw-internal 3.0.191+
 * @property-read PagesLoader $loader PagesLoader instance #pw-internal 3.0.191+
 * @property-read PagesEditor $editor PagesEditor instance #pw-internal 3.0.191+
 * @property-read PagesNames $names PagesNames instance #pw-internal 3.0.191+
 * @property-read PagesLoaderCache $cacher PagesLoaderCache instance #pw-internal 3.0.191+
 * @property-read PagesTrash $trasher PagesTrash instance #pw-internal 3.0.191+
 * @property-read PagesRaw $raw PagesRaw instance #pw-internal 3.0.191+
 * @property-read PagesRequest $request PagesRequest instance #pw-internal 3.0.191+
 * @property-read PagesPathFinder $pathFinder PagesPathFinder instance #pw-internal 3.0.191+
 * @property-read PagesType[] $types Array of all pages type managers. #pw-internal 3.0.191+
 * @property-read Page $newPage Returns new Page instance. #pw-internal 3.0.191+
 * @property-read Page $newPageArray Returns new PageArray instance. #pw-internal 3.0.191+
 * @property-read Page $newNullPage Returns new NullPage instance. #pw-internal 3.0.191+
 * 
 * HOOKABLE METHODS
 * ================
 * @method PageArray find($selectorString, array $options = array()) Find and return all pages matching the given selector string. Returns a PageArray. #pw-group-retrieve
 * @method bool save(Page $page, $options = array()) Save any changes made to the given $page. Same as $page->save(); Returns true on success. #pw-group-save
 * @method bool saveField(Page $page, $field, array $options = array()) Save just the named field from $page. Same as: $page->save('field') #pw-group-save
 * @method array saveFields(Page $page, $fields, array $options = array()) Saved multiple named fields for $page. @since 3.0.242 #pw-group-save
 * @method bool trash(Page $page, $save = true) Move a page to the trash. If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again. #pw-group-trash
 * @method bool restore(Page $page, $save = true) Restore a trashed page to its original location. #pw-group-trash
 * @method int|array emptyTrash(array $options = array()) Empty the trash and return number of pages deleted. #pw-group-trash
 * @method bool delete(Page $page, $recursive = false, array $options = array()) Permanently delete a page and it's fields. Unlike trash(), pages deleted here are not restorable. If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown. #pw-group-trash
 * @method Page|NullPage clone(Page $page, Page $parent = null, $recursive = true, $options = array()) Clone an entire page, it's assets and children and return it. #pw-group-save
 * @method Page|NullPage add($template, $parent, $name = '', array $values = array()) #pw-group-add
 * @method int sort(Page $page, $value = false) Set the “sort” value for given $page while adjusting siblings, or re-build sort for its children. #pw-group-save
 * @method setupNew(Page $page) Setup new page that does not yet exist by populating some fields to it. #pw-internal
 * @method string setupPageName(Page $page, array $options = array()) Determine and populate a name for the given page. #pw-internal
 * @method void insertBefore(Page $page, Page $beforePage) Insert one page as a sibling before another. #pw-advanced
 * @method void insertAfter(Page $page, Page $afterPage) Insert one page as a sibling after another. #pw-advanced
 * @method bool touch($pages, $options = null, $type = 'modified') Update page modification time to now (or the given modification time). #pw-group-save
 * 
 * METHODS PURELY FOR HOOKS
 * ========================
 * You can hook these methods, but you should not call them directly. 
 * See the phpdoc in the actual methods for more details about arguments and additional properties that can be accessed.
 * 
 * @method array saveReady(Page $page) Hook called just before a page is saved. 
 * @method saved(Page $page, array $changes = array(), $values = array()) Hook called after a page is successfully saved. 
 * @method addReady(Page $page)
 * @method added(Page $page) Hook called when a new page has been added. 
 * @method moveReady(Page $page) Hook called when a page is about to be moved to another parent. 
 * @method moved(Page $page) Hook called when a page has been moved from one parent to another. 
 * @method templateChanged(Page $page) Hook called when a page template has been changed. 
 * @method trashReady(Page $page) Hook called when a page is about to be moved to the trash.
 * @method trashed(Page $page) Hook called when a page has been moved to the trash. 
 * @method restoreReady(Page $page) Hook called when a page is about to be restored out of the trash.
 * @method restored(Page $page) Hook called when a page has been moved OUT of the trash. 
 * @method deleteReady(Page $page, array $options) Hook called just before a page is deleted. 
 * @method deleted(Page $page, array $options) Hook called after a page has been deleted. 
 * @method deleteBranchReady(Page $page, array $options) Hook called before a branch of pages deleted, on initiating page only. 
 * @method deletedBranch(Page $page, array $options, $numDeleted) Hook called after branch of pages deleted, on initiating page only.
 * @method cloneReady(Page $page, Page $copy) Hook called just before a page is cloned. 
 * @method cloned(Page $page, Page $copy) Hook called after a page has been successfully cloned. 
 * @method renameReady(Page $page) Hook called when a page is about to be renamed. 
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
	 * @var PagesParents
	 * 
	 */
	protected $parents;

	/**
	 * @var PagesRaw
	 *
	 */
	protected $raw;
	
	/**
	 * @var PagesRequest
	 *
	 */
	protected $request;
	
	/**
	 * @var PagesPathFinder
	 *
	 */
	protected $pathFinder;

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
		parent::__construct();
		$this->setWire($wire);
		$this->debug = $wire->config->debug === Config::debugVerbose ? true : false;
		$this->sortfields = $this->wire(new PagesSortfields());
		$this->loader = $this->wire(new PagesLoader($this));
		$this->cacher = $this->wire(new PagesLoaderCache($this));
		$this->trasher = null;
		$this->editor = null;
		$this->raw = null;
	}
	
	/**
	 * Initialize $pages API var by preloading some pages
	 * 
	 * #pw-internal
	 *
	 */
	public function init() {
		$this->loader->getById($this->wire()->config->preloadPageIDs);
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
	 * #pw-group-retrieve
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
	 * #pw-group-retrieve
	 * 
	 * @param string|int|array|Selectors $selector Specify selector (standard usage), but can also accept page ID or array of page IDs.
	 * @param array|string $options One or more options that can modify certain behaviors. May be associative array or "key=value" selector string.
	 *  - `findOne` (bool): Apply optimizations for finding a single page (default=false).
	 *  - `findAll` (bool): Find all pages with no exclusions, same as "include=all" option (default=false). 
	 *  - `findIDs` (bool|int): 1 to get array of page IDs, true to return verbose array, 2 to return verbose array with all cols in 3.0.153+. (default=false).
	 *  - `getTotal` (bool): Whether to set returning PageArray's "total" property (default=true) except when findOne=true.
	 *  - `loadPages` (bool): Whether to populate the returned PageArray with found pages (default=true). 
	 *	   The only reason why you'd want to change this to false would be if you only needed the count details from 
	 *	   the PageArray: getTotal(), getStart(), getLimit, etc. This is intended as an optimization for $pages->count().
	 * 	   Does not apply if $selector argument is an array. 
	 *  - `cache` (bool): Allow caching of selectors and loaded pages? (default=true). Also sets loadOptions[cache].
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
	 * #pw-group-retrieve
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
	 * #pw-group-retrieve
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
	 * Find pages and specify which fields to join (overriding configured autojoin settings)
	 * 
	 * This is a useful optimization when you know exactly which fields you will be using from the returned
	 * pages and you want to have their values joined into the page loading query to reduce overhead. Note
	 * that this overrides the configured autojoin settings in ProcessWire fields. 
	 * 
	 * If a particular page in the returned set of pages was already loaded before this method call,
	 * then the one already in memory will be used rather than this method loading another copy of it. 
	 * 
	 * ~~~~~
	 * // 1. Example of loading blog posts where we want to join title, date, summary:
	 * $posts = $pages->findJoin("template=blog-post", [ 'title', 'date', 'summary' ]); 
	 * 
	 * // 2. You can also specify the join fields as a CSV string:
	 * $posts = $pages->findJoin("template=blog-post", 'title, date, summary'); 
	 * 
	 * // 3. You can also use the join functionality on a regular $pages->find() by specifying 
	 * // property 'join' or 'field' in the selector. The words 'join' and 'field' are aliases
	 * // of each other here, just in case you have an existing field with one of those names. 
	 * // Otherwise, use whichever makes more sense to you. The following examples demonstrate
	 * // this and all do exactly the same thing as examples 1 and 2 above:
	 * $posts = $pages->find("template=blog-post, join=title|date|summary");
	 * $posts = $pages->find("template=blog-post, field=title|date|summary");
	 * $posts = $pages->find("template=blog-post, join=title, join=date, join=summary");
	 * $posts = $pages->find("template=blog-post, field=title, field=date, field=summary");
	 *
	 * // 4. Let’s say you want to load pages with NO autojoin fields, here is how.  
	 * // The following loads all blog-post pages and prevents ANY fields from being joined,
	 * // even if they are configured to be autojoin in ProcessWire:
	 * $posts = $pages->findJoin("template=blog-post", false);
	 * $posts = $pages->find("template=blog-post, join=none"); // same as above
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param string|array|Selectors $selector
	 * @param array|string|bool $joinFields Array or CSV string of field names to autojoin, or false to join none.
	 * @param array $options
	 * @return PageArray
	 * @since 3.0.172
	 * 
	 */
	public function findJoin($selector, $joinFields, $options = array()) {
		
		$fields = $this->wire()->fields;
	
		if($joinFields === false) {
			$name = 'none';
			while($fields->get($name)) $name .= 'X';
			$joinFields = array($name);
		} else if(empty($joinFields)) {
			$joinFields = array();
		} else if(!is_array($joinFields)) {
			$joinFields = (string) $joinFields;
			if(strpos($joinFields, ',') !== false) {
				$joinFields = explode(',', $joinFields);
			} else if(strpos($joinFields, '|') !== false) {
				$joinFields = explode('|', $joinFields);
			} else {
				$joinFields = array($joinFields);
			}
		}
		
		foreach($joinFields as $key => $name) {
			if(is_int($name) || ctype_digit($name)) {
				$field = $fields->get($name);
				if(!$field) continue;
				$name = $field->name;
			} else if(strpos($name, '.') !== false) {
				list($name,) = explode('.', $name, 2); // subfields not allowed
			}
			$joinFields[$key] = trim($name);
		}
		
		$options['joinFields'] = $joinFields;
		
		return $this->find($selector, $options);
	}

	/**
	 * Like find() except returns array of IDs rather than Page objects
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
	 * #pw-group-retrieve
	 * 
	 * @param string|array|Selectors $selector Selector to find page IDs. 
	 * @param array|bool|int|string $options Options to modify behavior. 
	 *  - `verbose` (bool|int|string): Specify true to make return value array of associative arrays, each with id, parent_id, templates_id. 
	 *    Specify integer `2` or string `*` to return verbose array of associative arrays, each with all columns from pages table. 
	 *  - `indexed` (bool): Index by page ID? (default=false) Added 3.0.172
	 *  - The verbose option above can also be specified as alternative to the $options argument.
	 *  - See `Pages::find()` $options argument for additional options. 
	 * @return array Array of page IDs, or in verbose mode: array of arrays, each with id, parent_id and templates_id keys.
	 * @since 3.0.46
	 * 
	 */
	public function findIDs($selector, $options = array()) {
		$verbose = false;
		if(!is_array($options)) {
			// verbose option specified in $options array
			$verbose = $options;
			$options = array();
		}
		if(isset($options['verbose'])) {
			$verbose = $options['verbose'];
			unset($options['verbose']);
		}
		if($verbose === 2 || $verbose === '*') {
			$options['findIDs'] = 2;
		} else {
			$options['findIDs'] = $verbose ? true : 1;
		}
		/** @var array $ids */
		$ids = $this->find($selector, $options);
		if(!empty($options['indexed'])) {
			$a = array();
			foreach($ids as $value) {
				$id = $verbose ? $value['id'] : $value;
				$a[$id] = $value;
			}
			$ids = $a;
		}
		return $ids;
	}
	
	/**
	 * Find pages and return raw data from them in a PHP array 
	 * 
	 * Note that the data returned from this method is raw and unformatted, directly
	 * as it exists in the database. In most cases you should use `$pages->find()` instead,
	 * but this method provides a convenient alternative for some cases. 
	 * 
	 * The `$selector` argument can be any page-finding selector that you would provide
	 * to a regular `$pages->find()` call. The most interesting stuff relates to the 
	 * `$field` argument though, which is what the rest of this section looks at: 
	 * 
	 * If you omit the `$field` argument, it will return all data for the found pages in 
	 * an array where the keys are the page IDs and the values are associative arrays 
	 * containing all of each page raw field and property values indexed by name…
	 * `$a = $pages->findRaw("template=blog");` …but findRaw() is more useful for cases 
	 * where you want to retrieve specific things without having to load the entire page 
	 * (or its data). Below are a few examples of how you can do this. 
	 * 
	 * ~~~~~
	 * // If you provide a string (field name) for `$field`, then it will return an 
	 * // array with the values of the `data` column of that field. The `$field` can 
	 * // also be the name of a native pages table property like `id` or `name`. 
	 * $a = $pages->findRaw("template=blog", "title"); 
	 * 
	 * // The above would return an array of blog page titles indexed by page ID. If 
	 * // you provide an array for `$field` then it will return an array for each page, 
	 * // where each of those arrays is indexed by the field names you requested.
	 * $a = $pages->findRaw("template=blog", [ "title", "date" ]); 
	 * 
	 * // You may specify field name(s) like `field.subfield` to retrieve a specific 
	 * // column/subfield. When it comes to Page references or Repeaters, the subfield 
	 * // can also be the name of a field that exists on the Page reference or repeater. 
	 * $a = $pages->findRaw("template=blog", [ "title", "categories.title" ]); 
	 * 
	 * // You can also use this format below to get multiple subfields from one field:
	 * $a = $pages->findRaw("template=blog", [ "title", "categories" => [ "id", "title" ] ]); 
	 * 
	 * // You can optionally rename fields in the returned value like this below, which
	 * // asks the 'title' field to have the name 'headline' in return value (3.0.176+):
	 * $a = $pages->findRaw("template=blog", [ "title" => "headline" ]); 
	 * 
	 * // You may specify wildcard field name(s) like `field.*` to return all columns 
	 * // for `field`. This retrieves all columns from the field’s table. This is 
	 * // especially useful with fields like Table or Combo that might have several 
	 * // different columns: 
	 * $a = $pages->findRaw("template=villa", "rates_table.*" ); 
	 * 
	 * // If you prefer, you can specify the field name(s) in the selector (3.0.173+): 
	 * $a = $pages->findRaw("template=blog, field=title");
	 * $a = $pages->findRaw("template=blog, field=title|categories.title"); 
	 * 
	 * // Specify “objects=1” in selector to use objects rather than associative arrays 
	 * // for pages and fields (3.0.174+):
	 * $a = $pages->findRaw("template=blog, field=title|categories.title, objects=1"); 
	 * 
	 * // Specify “entities=1” to entity encode all string values:
	 * $a = $pages->findRaw("template=blog, field=title|summary, entities=1");
	 * 
	 * // Specify “entities=field” or “entities=field1|field2” to entity encode just 
	 * // the specific fields that you name (3.0.174+): 
	 * $a = $pages->findRaw("template=blog, entities=title|summary"); 
	 * 
	 * // If you prefer, options can also be enabled this way (3.0.174+):
	 * $a = $pages->findRaw("template=blog, options=objects|entities"); 
	 * ~~~~~
	 * 
	 * In 3.0.193 the following additional options were added for the `$field` argument:
	 * 
	 *  - Specify `parent.field_name` or `parent.parent.field_name`, etc. to return values from parent(s).
	 *  - Specify `references` or `references.field_name`, etc. to also return values from pages referencing found pages.
	 *  - Specify `meta` or `meta.name` to also return values from page meta data.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|array|Selectors|int $selector Page matching selector or page ID
	 * @param string|array|Field $field Name of field/property to get, or array of them, CSV string, or omit to get all (default='')
	 *  - Optionally use associative array to rename fields in returned value, i.e. `['title' => 'label']` returns 'title' as 'label' in return value.
	 *  - Note: this argument may also be specified in the $selector argument as "field=foo" or "field=foo|bar|baz" (3.0.173+).
	 * @param array $options Options to adjust behavior (may also be specified in selector, i.e. “objects=1, entities=foo|bar”)
	 *  - `objects` (bool): Use objects rather than associative arrays? (default=false) 3.0.174+
	 *  - `entities` (bool|array): Entity encode string values? True or 1 to enable, or specify array of field names. (default=false) 3.0.174+
	 *  - `nulls` (bool): Populate nulls for field values that are not present, rather than omitting them? (default=false) 3.0.198+
	 *  - `indexed` (bool): Index by page ID? (default=true)
	 *  - `flat` (bool|string): Flatten return value as `["field.subfield" => "value"]` rather than `["field" => ["subfield" => "value"]]`?
	 *     Optionally specify field delimiter for the value, otherwise a period `.` will be used as the delimiter. (default=false) 3.0.193+
	 *  - Any of these options above can be specified in the $selector argument as a string, i.e. `…, flat=1, entities=1`. 
	 *  - Note the `objects` and `flat` options are not meant to be used together.
	 * @return array
	 * @since 3.0.172
	 *
	 */
	public function findRaw($selector, $field = '', $options = array()) {
		return $this->raw()->find($selector, $field, $options); 
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
	 * #pw-group-retrieve
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
	 * Get single page and return raw data in an associative array
	 *
	 * Note that the data returned from this method is raw and unformatted, directly as it exists in the database. 
	 * In most cases you should use `$pages->get()` instead, but this method is a convenient alternative for some cases. 
	 *
	 * Please see the documentation for the `$pages->findRaw()` method, which all applies to this method as well.
	 * The biggest difference is that this method returns data for just 1 page, unlike `$pages->findRaw()` which can
	 * return data for many pages at once. 
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|array|Selectors|int $selector Page matching selector or page ID
	 * @param string|array|Field $field Name of field/property to get, or array of them, or omit to get all (default='')
	 * @param array $options
	 * @return array
	 *
	 */
	public function getRaw($selector, $field = '', $options = array()) {
		return $this->raw()->get($selector, $field, $options);
	}
	
	/**
	 * Get a fresh, non-cached copy of a Page from the database
	 *
	 * This method is the same as `$pages->get()` except that it skips over all memory caches when loading a Page.
	 * Meaning, if the Page is already in memory, it doesn’t use the one in memory and instead reloads from the DB.
	 * Nor does it place the Page it loads in any memory cache. Use this method to load a fresh copy of a page
	 * that you might need to compare to an existing loaded copy, or to load a copy that won’t be seen or touched
	 * by anything in ProcessWire other than your own code.
	 *
	 * ~~~~~
	 * $p1 = $pages->get(1234);
	 * $p2 = $pages->get($p1->path);
	 * $p1 === $p2; // true: same Page instance
	 *
	 * $p3 = $pages->getFresh($p1);
	 * $p1 === $p3; // false: same Page but different instance
	 * ~~~~~
	 *
	 * #pw-group-retrieve
	 *
	 * @param Page|string|array|Selectors|int $selectorOrPage Specify Page to get copy of, selector or ID
	 * @param array $options Options to modify behavior
	 * @return Page|NullPage
	 * @since 3.0.172
	 *
	 */
	public function getFresh($selectorOrPage, $options = array()) {
		return $this->loader()->getFresh($selectorOrPage, $options);
	}

	/**
	 * Get one ID of page matching given selector with no exclusions, like get() but returns ID rather than a Page
	 *
	 * This method is an alias of the has() method, and depending on what you are after, may make more
	 * or less sense with your code readability. Use whichever better suits your case. 
	 * 
	 * #pw-advanced
	 *
	 * @param string|array|Selectors $selector Specify selector to find first matching page ID
	 * @param bool|array $options Specify boolean true to return all pages columns rather than just IDs.
	 *   Or specify array of options (see find method for details), `verbose` option can optionally be in array. 
	 * @return int|array
	 * @see Pages::get(), Pages::has(), Pages::findIDs()
	 * @since 3.0.156
	 *
	 */
	public function getID($selector, $options = array()) {
		if(is_array($options)) {
			if(empty($options['caller'])) $options['caller'] = 'pages.getID';
			$verbose = false;
			if(isset($options['verbose'])) {
				$verbose = $options['verbose'];
				unset($options['verbose']);
			}
		} else {
			$verbose = $options;
			$options = array();
		}
		return $this->loader->has($selector, $verbose, $options);
	}

	/**
	 * Given array or CSV string of Page IDs, return a PageArray
	 *
	 * #pw-group-retrieve
	 *
	 * @param array|string|WireArray $ids Any one of the following:
	 *  - Single page ID (string or int)
	 *  - Array of page IDs
	 *  - Comma or pipe-separated string of page IDs
	 *  - Array of associative arrays having id and templates_id: [ [ 'id' => 1, 'templates_id' => 2], [ 'id' => 3, 'templates_id' => 4 ] ]
	 * @param array $options Options to affect behavior. The 'template' option is recommended when you have this info available.
	 * - `template` (Template|int|string): Template object, name or ID to use for loaded pages. (default=null)
	 * - `parent` (Page|int|string): Parent Page object, ID, or path to use for loaded pages. (default=null)
	 * - `cache` (bool): Place loaded pages in memory cache? (default=true)
	 * - `getFromCache` (bool): Allow use of previously cached pages in memory (rather than re-loading it from DB)? (default=true)
	 * - `getNumChildren` (bool): Specify false to disable retrieval and population of 'numChildren' Page property. (default=true)
	 * - `getOne` (bool): Specify true to return just one Page object, rather than a PageArray. (default=false)
	 * - `autojoin` (bool): Allow use of autojoin option? (default=true)
	 * - `joinFields` (array): Autojoin the field names specified in this array, regardless of field settings (requires autojoin=true). (default=empty)
	 * - `joinSortfield` (bool): Whether the 'sortfield' property will be joined to the page. (default=true)
	 * - `findTemplates` (bool): Determine which templates will be used (when no template specified) for more specific autojoins. (default=true)
	 * - `pageClass` (string): Class to instantiate Page objects with. Leave blank to determine from template. (default=auto-detect)
	 * - `pageArrayClass` (string): PageArray-derived class to store pages in (when 'getOne' is false). (default=PageArray)
	 * - `pageArray` (PageArray|null): Populate this existing PageArray rather than creating a new one. (default=null)
	 * - `page` (Page|null): Existing Page object to populate (also requires the getOne option to be true). (default=null)
	 * @return PageArray|Page Returns PageArray unless the getOne option was specified in which case a Page is returned. 
	 * @since 3.0.156 Previous versions can use $pages->getById() for similar behavior 
	 *
	 */
	public function getByIDs($ids, array $options = array()) {
		
		$template = empty($options['template']) ? null : $options['template'];
		$parent = empty($options['parent']) ? null : $options['parent'];
		$parent_id = null;
		
		if($template) {
			unset($options['template']);
			if($template instanceof Template) {
				// cool, cool
			} else if(is_int($template) || is_string($template)) {
				$template = $this->wire()->templates->get($template);
			} else {
				$template = null;
			}
		}
	
		if(!empty($options['parent_id'])) {
			$parent_id = (int) $options['parent_id'];
			unset($options['parent_id']);
		} else if($parent) {
			unset($options['parent']);
			if($parent instanceof Page) {
				$parent_id = $parent->id;
			} else if(is_int($parent) || ctype_digit("$parent")) {
				$parent_id = (int) "$parent";
			} else if(is_string($parent)) {
				$parent_id = $this->has($parent);
			}
			if(!$parent_id) $parent_id = null;
		}
		
		if(count($options)) {
			$options['template'] = $template instanceof Template ? $template : null;
			$options['parent_id'] = $parent_id;
			return $this->loader->getById($ids, $options);
		} else {
			return $this->loader->getById($ids, $template, $parent_id); 
		}
	}

	/**
	 * Is there any page that matches the given $selector in the system? (with no exclusions)
	 *
	 * - This can be used as an “exists” type of method.
	 * - Returns ID of first matching page if any exist, or 0 if none exist (returns array if `$verbose` is true). 
	 * - Like with the `get()` method, no pages are excluded, so an `include=all` is not necessary in selector.
	 * - If you need to quickly check if something exists, this method is preferable to using a count() or get().
	 *
	 * When `$verbose` option is used, an array is returned instead. Verbose return array includes page `id`, 
	 * `parent_id` and `templates_id` indexes.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|int|array|Selectors $selector
	 * @param bool $verbose Return verbose array with page id, parent_id, templates_id rather than just page id? (default=false)
	 * @return array|int
	 * @since 3.0.153
	 * @see Pages::count(), Pages::get()
	 *
	 */
	public function has($selector, $verbose = false) {
		return $this->loader->has($selector, $verbose); 
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
	 * #pw-group-save
	 *
	 * @param Page $page Page object to save
	 * @param array $options Optional array to modify default behavior, with one or more of the following:
	 * - `uncacheAll` (boolean): Whether the memory cache should be cleared (default=true).
	 * - `resetTrackChanges` (boolean): Whether the page's change tracking should be reset (default=true).
	 * - `quiet` (boolean): When true, modified date and modified_users_id won't be updated (default=false).
	 * - `adjustName` (boolean): Adjust page name to ensure it is unique within its parent (default=true).
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
	 * #pw-group-save
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
	 * Save multiple named fields from given page
	 *
	 * ~~~~~
	 * // you can specify field names as array…
	 * $a = $pages->saveFields($page, [ 'title', 'body', 'summary' ]);
	 *
	 * // …or a CSV string of field names:
	 * $a = $pages->saveFields($page, 'title, body, summary');
	 *
	 * // return value is array of saved field/property names
	 * print_r($a); // outputs: array( 'title', 'body', 'summary' )
	 * ~~~~~
	 * 
	 * #pw-group-save
	 *
	 * @param Page $page Page to save
	 * @param array|string|string[]|Field[] $fields Array of field names to save or CSV/space separated field names to save.
	 *   These should only be Field names and not native page property names.
	 * @param array|string $options Optionally specify one or more of the following to modify default behavior:
	 *  - `quiet` (boolean): Specify true to bypass updating of modified user and time (default=false).
	 *  - `noHooks` (boolean): Prevent before/after save hooks (default=false), please also use $pages->___saveField() for call.
	 *  - See $options argument for Pages::save() for additional options
	 * @return array Array of saved field names (may also include property names if they were modified)
	 * @throws WireException
	 * @since 3.0.242
	 *
	 */
	public function ___saveFields(Page $page, $fields, array $options = array()) {
		return $this->editor()->saveFields($page, $fields, $options); 
	}

	/**
	 * Add a new page using the given template and parent
	 *
	 * If no page “name” is specified, one will be automatically assigned. 
	 * 
	 * For an alternate interface for adding new pages, see the `$pages->new()` method. 
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
	 * #pw-group-add
	 *
	 * @param string|Template $template Template name or Template object
	 * @param string|int|Page $parent Parent path, ID or Page object
	 * @param string $name Optional name or title of page. If none provided, one will be automatically assigned.
	 * 	If you want to specify a different name and title then specify the $name argument, and $values['title'].
	 * @param array $values Field values to assign to page (optional). If $name is omitted, this may also be 3rd param.
	 * @return Page New page ready to populate. Note that this page has output formatting off.
	 * @throws WireException When some criteria prevents the page from being saved.
	 * @see Pages::new(), Pages::newPage()
	 *
	 */
	public function ___add($template, $parent, $name = '', array $values = array()) {
		return $this->editor()->add($template, $parent, $name, $values);
	}

	/**
	 * Create a new Page populated from selector string or array and save to database
	 * 
	 * This is similar to the `$pages->add()` method but with a simpler 1-argument (selector) interface. 
	 * This method can also auto-detect some properties that the add() method cannot. 
	 * 
	 * To create a new page without saving to the database use the `$pages->newPage()` method instead. 
	 * It accepts the same arguments as this method. 
	 * 
	 * Minimum requirements to create a new page that is saved in database:
	 * 
	 * - A `template` must be specified, unless it can be auto-detected from a given `parent`. 
	 * - A `parent` must be specified, unless it can be auto-detected from a given `template` or `path`.
	 * 
	 * Please note the following: 
	 *
	 * - If a `path` is specified but not a `name` or `parent` then both will be derived from the `path`. 
	 * - If a `title` is specified but not a `name` or `path` then the `name` will be derived from the `title`.
	 * - If given `parent` or `path` only allows one template (via family settings) then `template` becomes optional.
	 * - If given `template` only allows one parent (via family settings) then `parent` becomes optional. 
	 * - If given selector string starts with a `/` it is assumed to be the `path` property. 
	 * - If new page has name that collides with an existing page (i.e. “foo”), new page name will increment (i.e. “foo-1”).
	 * - If no `name`, `path` or `title` is given (that name can be derived from) then an “untitled-page” name will be used. 
	 * 
	 * ~~~~~
	 * // Creating a page via selector string
	 * $p = $pages->new("template=category, parent=/categories/, title=New Category");
	 * 
	 * // Creating a page via selector using path, which implies parent and name
	 * $p = $pages->new("template=category, path=/categories/new-category");
	 * 
	 * // Creating a page via array
	 * $p = $pages->new([
	 *   'template' => 'category',
	 *   'parent' => '/categories/',
	 *   'title' => 'New Category'
	 * ]);
	 * 
	 * // Parent and name can be auto-detected when you specify path…
	 * $p = $pages->new('path=/blog/posts/foo-bar-baz');
	 * 
	 * // …and even 'path=' is optional if slash '/' is at beginning
	 * $p = $pages->new('/blog/posts/foo-bar-baz');
	 * ~~~~~
	 * 
	 * #pw-group-add
	 * 
	 * @param string|array $selector Selector string or array of properties to set
	 * @return Page
	 * @since 3.0.191
	 * @see Pages::add(), Pages::newPage()
	 * 
	 */
	public function ___new($selector = '') {
	
		$options = $this->editor()->newPageOptions($selector);
		$error = 'Cannot save new page without a %s';
		$template = isset($options['template']) ? $options['template'] : null;
		$parent = isset($options['parent']) ? $options['parent'] : null;
		$name = isset($options['name']) ? $options['name'] : '';
		
		if(!$template) throw new WireException(sprintf($error, 'template'));
		if(!$parent) throw new WireException(sprintf($error, 'parent'));
		
		unset($options['template'], $options['parent'], $options['name']);
		
		$page = $this->editor()->add($template, $parent, $name, $options);
		
		return $page;
	}
	
	/**
	 * Clone entire page and return it
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
	 * #pw-group-add
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
	public function ___clone(Page $page, ?Page $parent = null, $recursive = true, $options = array()) {
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
	 * #pw-group-trash
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
	 * #pw-group-trash
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
	 * #pw-group-trash
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
	 * #pw-group-trash
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
	 * Given an array or CSV string of Page IDs, return a PageArray (internal API)
	 * 
	 * Note that this method is primarily for internal use and most of the options available are specific to the needs
	 * of core methods that utilize them. All pages loaded by ProcessWire pass through this method. 
	 *
	 * Optionally specify an `$options` array rather than a template for argument 2. When present, the `template` and `parent_id` 
	 * arguments may be provided in the given $options array. These options may be specified: 
	 * 
	 * **LOAD OPTIONS (argument 2 array):** 
	 * 
	 * - `cache` (bool): Place loaded pages in memory cache? (default=true)
	 * - `getFromCache` (bool): Allow use of previously cached pages in memory (rather than re-loading it from DB)? (default=true)
	 * - `template` (Template): Instance of Template, see the $template argument for details.
	 * - `parent_id` (int): Parent ID, see $parent_id argument for details.
	 * - `getNumChildren` (bool): Specify false to disable retrieval and population of 'numChildren' Page property. (default=true)
	 * - `getOne` (bool): Specify true to return just one Page object, rather than a PageArray. (default=false)
	 * - `autojoin` (bool): Allow use of autojoin option? (default=true)
	 * - `joinFields` (array): Autojoin the field names specified in this array, regardless of field settings (requires autojoin=true). (default=empty)
	 * - `joinSortfield` (bool): Whether the 'sortfield' property will be joined to the page. (default=true)
	 * - `findTemplates` (bool): Determine which templates will be used (when no template specified) for more specific autojoins. (default=true)
	 * - `pageClass` (string): Class to instantiate Page objects with. Leave blank to determine from template. (default=auto-detect)
	 * - `pageArrayClass` (string): PageArray-derived class to store pages in (when 'getOne' is false). (default=PageArray)
	 * - `pageArray` (PageArray): Populate this existing PageArray rather than creating a new one. (default=null)
	 * - `page` (Page): Existing Page object to populate (also requires the getOne option to be true). (default=null)
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
	 * @param array|WireArray|string|int $_ids Array of Page IDs, CSV string of Page IDs, or single page ID. 
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
	 * Get one page by ID
	 * 
	 * This is the same as `getById()` with the `getOne` option. 
	 * 
	 * #pw-advanced
	 * 
	 * @param int $id
	 * @param array $options
	 * @return Page|NullPage
	 * @since 3.0.186
	 * 
	 */
	public function getOneById($id, array $options = array()) {
		$options['getOne'] = true;
		return $this->loader->getById((int) $id, $options);
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
	 *  - `allowUrl` (bool): Allow getting page by path OR url? Specify false to find only by path. This option only applies if
	 *     the site happens to run from a subdirectory. (default=true) 3.0.184+
	 *  - `allowPartial` (bool): Allow partial paths to match? (default=true) 3.0.184+
	 *  - `allowUrlSegments` (bool): Allow paths with URL segments to match? When true and page match cannot be found, the closest
	 *     parent page that allows URL segments will be returned. Found URL segments are populated to a `_urlSegments` array
	 *     property on the returned page object. This also cancels the allowPartial setting. (default=false) 3.0.184+
	 * @return Page|int
	 * @since 3.0.6
	 *
	 */
	public function getByPath($path, $options = array()) {
		return $this->loader->getByPath($path, $options);
	}

	/**
	 * Get verbose array of info about a given page path
	 * 
	 * This method accepts a page path (not URL) with optional language segment
	 * prefix, additional URL segments and/or pagination number. It translates
	 * the given path to information about what page it matches, what type of 
	 * request it would result in.
	 *
	 * If the `response` property in the return value is 301 or 302, then the
	 * `redirect` property will be populated with a recommend redirect path. 
	 * 
	 * If given a `$path` argument of `/en/foo/bar/page3` on a site that has default
	 * language homepage segment of `en`, a page living at `/foo/` that accepts 
	 * URL segment `bar` and has pagination enabled, it will return the following:
	 * ~~~~~
	 * [
	 *   'request' => '/en/foo/bar/page3', 
	 *   'response' => 200, // one of 200, 301, 302, 404, 414
	 *   'type' => 'ok', // response type name
	 *   'errors' => [], // array of error messages indexed by error name
	 *   'redirect' => '/redirect/path/', // suggested path to redirect to or blank
	 *   'page' => [
	 *      'id' => 123, // ID of the page that was found
	 *      'parent_id' => 456,
	 *      'templates_id' => 12,
	 *      'status' => 1, 
	 *      'name' => 'foo',
	 *   ],
	 *   'language' => [
	 *      'name' => 'default', // name of language detected
	 *      'segment' => 'en', // segment prefix in path (if any)
	 *      'status' => 1, // language status where 1 is on, 0 is off
	 *    ],
	 *   'parts' => [ // all the parts of the path identified
	 *     [
	 *       'type' => 'language', 
	 *       'value' => 'en',
	 *       'language' => 'default' 
	 *     ],
	 *     [
	 *       'type' => 'pageName',
	 *       'value' => 'foo',
	 *       'language' => 'default'
	 *     ],
	 *     [
	 *       'type' => 'urlSegment',
	 *       'value' => 'bar',
	 *       'language' => ''
	 *     ],
	 *     [
	 *       'type' => 'pageNum',
	 *       'value' => 'page3',
	 *       'language' => 'default'
	 *     ],
	 *   ],
	 *   'urlSegments' => [ // URL segments identified in order
	 *      'bar',
	 *   ],
	 *   'urlSegmentStr' => 'bar', // URL segment string
	 *   'pageNum' => 3, // requested pagination number
	 *   'pageNumPrefix' => 'page', // prefix used in pagination number
	 *   'scheme' => 'https', // blank if no scheme required, https or http if one of those is required
	 *   'method' => 'pagesRow', // method(s) that were used to find the page
	 * ]
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param string $path Page path optionally including URL segments, language prefix, pagination number
	 * @param array $options
	 *  - `useLanguages` (bool): Allow use of multi-language page names? (default=true)
	 *     Requires LanguageSupportPageNames module installed.
	 *  - `useShortcuts` (bool): Allow use of shortcut methods for optimization? (default=true)
	 *     Recommend PagePaths module installed.
	 *  - `useHistory` (bool): Allow use historical path names? (default=true)
	 *     Requires PagePathHistory module installed.
	 *  - `verbose` (bool): Return verbose array of information? (default=true)
	 *     If false, some optional information will be omitted in return value. 
	 * @return array
	 * 
	 */
	public function getInfoByPath($path, array $options = array()) {
		return $this->pathFinder()->get($path, $options);
	}
	
	/**
	 * Auto-populate some fields for a new page that does not yet exist
	 *
	 * Currently it does this: 
	 * - Sets up a unique page->name based on the format or title if one isn't provided already. 
	 * - Assigns a 'sort' value'. 
	 * 
	 * #pw-internal
	 * #pw-group-save
	 * 
	 * @param Page $page
	 *
	 */
	public function ___setupNew(Page $page) {
		$this->editor()->setupNew($page);
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
	 * #pw-group-save
	 * 
	 * @param Page|PageArray|array $pages May be Page, PageArray or array of page IDs (integers)
	 * @param null|int|string|array $options Omit (null) to update to now, or unix timestamp or strtotime() recognized time string,
	 *  or if you do not need this argument, you may optionally substitute the $type argument here,
	 *  or in 3.0.183+ you can also specify array of options here instead:
	 *  - `time` (string|int|null): Unix timestamp or strtotime() recognized string to use, omit for use current time (default=null)
	 *  - `type` (string): One of 'modified', 'created', 'published' (default='modified')
	 *  - `user` (bool|User): True to also update modified/created user to current user, or specify User object to use (default=false)
	 * @param string $type Date type to update, one of 'modified', 'created' or 'published' (default='modified') Added 3.0.147
	 *  Skip this argument if using options array for previous argument or if using the default type 'modified'.
	 * @throws WireException|\PDOException if given invalid format for $modified argument or failed database query
	 * @return bool True on success, false on fail
	 * @since 3.0.0
	 *
	 */
	public function ___touch($pages, $options = null, $type = 'modified') {
		return $this->editor()->touch($pages, $options, $type);
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
	 * #pw-group-move
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
	 * #pw-group-move
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
	 * #pw-group-move
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
	 * #pw-group-cache
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
	 * When no $page argument is given, this method behaves the same as `$pages->uncacheAll()`.
	 * When any $page argument is given, this does not remove pages from selectorCache.
	 * 
	 * #pw-group-cache
	 *
	 * @param Page|PageArray|int|null $page Page to uncache, PageArray of pages to uncache, ID of page to uncache (3.0.153+), or omit to uncache all.
	 * @param array $options Additional options to modify behavior: 
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent that call, set this to true. 
	 * @return int Number of pages uncached
	 *
	 */
	public function uncache($page = null, array $options = array()) {
		$cnt = 0;
		if(is_null($page)) {
			$cnt = $this->cacher->uncacheAll(null, $options);
		} else if($page instanceof Page || is_int($page)) {
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
	 * #pw-group-cache
	 * 
	 * @param Page|null $page Optional Page that initiated the uncacheAll
	 * @param array $options Options to modify default behavior: 
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent that call, set this to true.
	 * @return int Number of pages uncached
	 *
	 */
	public function uncacheAll(?Page $page = null, array $options = array()) {
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
	 * @param string $name
	 * @return mixed
	 *
	 */
	public function __get($name) {
		switch($name) {
			// A-Z
			case 'autojoin': return $this->loader->getAutojoin();
			case 'cacher': return $this->cacher();
			case 'cloning': return $this->editor()->isCloning();
			case 'editor': return $this->editor();
			case 'loader': return $this->loader();
			case 'names': return $this->names();
			case 'newNullPage': return $this->newNullPage();
			case 'newPage': return $this->newPage();
			case 'newPageArray': return $this->newPageArray();
			case 'of': return $this->of();
			case 'outputFormatting': return $this->loader->getOutputFormatting();
			case 'parents': return $this->parents();
			case 'pathFinder': return $this->pathFinder();
			case 'porter': return $this->porter();
			case 'raw': return $this->raw();
			case 'request': return $this->request();
			case 'trasher': return $this->trasher();
			case 'types': return $this->types();
		}
		return parent::__get($name); 
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
		if(!$this->wire()->config->debug) return array();
		if(!$action) return $this->debugLog; 
		$debugLog = array();
		foreach($this->debugLog as $item) if($item['action'] == $action) $debugLog[] = $item; 
		return $debugLog; 
	}

	/**
	 * Return a new PageFinder object, ready to use
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
	 *  - `class` (string): Alias of pageArrayClass supported in 3.0.191+.
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
		if(!empty($options['pageArrayClass'])) {
			$class = $options['pageArrayClass'];
		} else if(!empty($options['class'])) {
			$class = $options['class'];
		}
		$class = wireClassName($class, true);
		$pageArray = $this->wire(new $class());
		if(!$pageArray instanceof PageArray) $pageArray = $this->wire(new PageArray());
		return $pageArray;
	}

	/**
	 * Return a new Page object without saving it to the database
	 * 
	 * To create a new Page object and save it the database, use the `$pages->new()` or `$pages->add()` methods, 
	 * or call `save()` on the Page object returned from this method. 
	 *
	 *  - When a template is specified, the `pageClass` can be auto-detected from the template. 
	 *  - In 3.0.152+ you may specify the Template object, name or ID instead of an $options array.
	 *  - In 3.0.191+ you may specify a selector string for the $options argument (alternative to array),
	 *    see the `$pages->new()` method `$selector` argument for details. 
	 *  - In 3.0.191+ the `pageClass` can also be specified as `class`, assuming that doesn’t collide 
	 *    with an existing field name. 
	 * 
	 * ~~~~~
	 * // Create a new blank Page object
	 * $p = $pages->newPage();
	 * 
	 * // Create a new Page object and specify properties to set with an array 
	 * $p = $pages->newPage([
	 *   'template' => 'blog-post',
	 *   'parent' => '/blog/posts/',
	 *   'title' => 'Hello world', 
	 * ]);
	 * 
	 * // Same as above but using selector string (3.0.191+)
	 * $p = $pages->newPage('template=blog-post, parent=/blog/posts, title=Hello world'); 
	 * 
	 * // Create new Page object using 'blog-post' template
	 * $p = $pages->newPage('blog-post'); 
	 * 
	 * // Create new Page object with parent and name implied by given path (3.0.191+)
	 * $p = $pages->newPage('/blog/posts/hello-world'); 
	 * ~~~~~
	 * 
	 * #pw-group-add
	 * 
	 * @param array|string|Template $options Optionally specify array (or selector string in 3.0.191+) with any of the following:
	 *  - `template` (Template|id|string): Template to use via object, ID or name. The `pageClass` will be auto-detected. 
	 *  - `parent` (Page|int|string): Parent page object, ID or path. 
	 *  - `name` (string): Name of page. 
	 *  - `path` (string): Specify /path/for/page/, name and parent (and maybe template) can be auto-detected. 3.0.191+
	 *  - `pageClass` (string): Class to use for Page. If not specified, default is from template setting, or `Page` if no template.
	 *  - Specify any other Page properties or fields you want to set (name, title, etc.). Note that most page fields will need to
	 *    have a `template` set first, so make sure to include it in your options array when providing other fields.
	 * @return Page
	 *
	 */
	public function newPage($options = array()) {
		
		if(empty($options)) return $this->wire(new Page());

		$options = $this->editor()->newPageOptions($options);
		$template = isset($options['template']) ? $options['template'] : null;
		$parent = isset($options['parent']) ? $options['parent'] : null;
		$class = empty($options['pageClass']) ? 'Page' : $options['pageClass'];

		unset($options['template'], $options['parent'], $options['pageClass']); 
		
		if($template && !$template instanceof Template) {
			$template = $this->wire()->templates->get($template);
			if(!$template instanceof Template) $template = null;
		}
	
		if(strpos($class, "\\") === false) $class = wireClassName($class, true);
		
		$page = $this->wire(new $class($template));
		
		if(!$page instanceof Page) $page = $this->wire(new Page($template));
		if($parent) $page->parent = $parent;
		if(count($options)) $page->setArray($options);
		
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
		return $this->wire()->database->execute($query, $throw, $maxTries);
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
		// no argument
		if(empty($key)) return $this;
		
		// page ID
		if(is_int($key)) return $this->get($key);
		
		// array of page IDs
		if(is_array($key) && ctype_digit(implode('', $key))) {
			return $this->getById($key);
		}
		
		// page path
		if(is_string($key) && strpos($key, '/') !== false) { 
			if($this->wire()->sanitizer->pagePathName($key) === $key) {
				return $this->get($key);
			}
		}
		
		// selector string or array
		return $this->find($key);
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
		if(!in_array('pages', $this->wire()->config->logs)) return parent::___log();
		if($this->wire()->process != 'ProcessPageEdit') $str .= " [From URL: " . $this->wire()->input->url() . "]";
		$options = array('name' => 'pages', 'url' => $page->path); 
		return parent::___log($str, $options); 
	}

	/**
	 * Get PagesLoader instance which provides methods for finding and loading pages
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesLoader
	 * 
	 * @return PagesLoader
	 * 
	 *
	 */
	public function loader() {
		return $this->loader;
	}

	/**
	 * Get PagesEditor instance which provides methods for saving pages to the database
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesEditor
	 * 
	 * @return PagesEditor
	 * 
	 *
	 */
	public function editor() {
		if(!$this->editor) $this->editor = $this->wire(new PagesEditor($this));
		return $this->editor;
	}
	
	/**
	 * Get PagesNames instance which provides API methods specific to generating and modifying page names
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesNames
	 * 
	 * @return PagesNames
	 *
	 */
	public function names() {
		if(!$this->names) $this->names = $this->wire(new PagesNames($this)); 
		return $this->names;
	}

	/**
	 * Get PagesLoaderCache instance which provides methods for caching pages in memory
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesLoaderCache
	 * 
	 * @return PagesLoaderCache
	 * 
	 *
	 */
	public function cacher() {
		return $this->cacher;
	}
	
	/**
	 * Get PagesTrash instance which provides methods for managing the Pages trash
	 *
	 * #pw-group-helpers
	 * #pw-redirect PagesTrash
	 * 
	 * @return PagesTrash
	 * 
	 *
	 */
	public function trasher() {
		if(!$this->trasher) $this->trasher = $this->wire(new PagesTrash($this));
		return $this->trasher;
	}

	/**
	 * Get PagesParents instance which provides methods for managing parent/child relationships in the pages_parents table
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesParents
	 * 
	 * @return PagesParents
	 *
	 *
	 */
	public function parents() {
		if(!$this->parents) $this->parents = $this->wire(new PagesParents($this));
		return $this->parents;
	}

	/**
	 * Get new instance of PagesExportImport for exporting and importing pages
	 * 
	 * Please note that unlike most other helper methods, this method returns a new instance on every call.
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesExportImport
	 * 
	 * @return PagesExportImport
	 * @since 3.0.253
	 * 
	 */
	public function porter() {
		$porter = new PagesExportImport();
		$this->wire($porter);
		return $porter; 
	}

	/**
	 * Get the PagesRaw instance which provides methods for findind and loading raw pages data
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesRaw
	 * 
	 * @return PagesRaw
	 * @since 3.0.172
	 *
	 */
	public function raw() {
		if(!$this->raw) $this->raw = $this->wire(new PagesRaw($this));
		return $this->raw;
	}
	
	/**
	 * Get the PagesRequest instance which provides methods for identifying and loading page from current request URL
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesRequest
	 * 
	 * @return PagesRequest
	 * @since 3.0.186
	 *
	 */
	public function request() {
		if(!$this->request) $this->request = $this->wire(new PagesRequest($this));
		return $this->request;
	}

	/**
	 * Get the PagesPathFinder instance which provides methods for finding pages by paths
	 * 
	 * #pw-group-helpers
	 * #pw-redirect PagesPathFinder
	 * 
	 * @return PagesPathFinder
	 * @since 3.0.186
	 *
	 */
	public function pathFinder() {
		if(!$this->pathFinder) $this->pathFinder = $this->wire(new PagesPathFinder($this));
		return $this->pathFinder;
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
	 * #pw-group-save-hooks
	 * 
	 * @param Page $page The page that was saved
	 * @param array $changes Array of field names that changed
	 * @param array $values Array of values that changed, if values were being recorded, see Wire::getChanges(true) for details.
	 * @see Pages::savedPageOrField(), Pages::savedField()
	 *
	 */
	public function ___saved(Page $page, array $changes = array(), $values = array()) { 
		$str = "Saved page";
		if(count($changes)) $str .= " (Changes: " . implode(', ', $changes) . ")";
		$this->log($str, $page);
		$this->wire()->cache->maintenance($page);
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->saved($page, $changes, $values);
		}
	}

	/**
	 * Hook called when a new page is about to be added and saved to the database
	 * 
	 * #pw-group-add-hooks
	 * 
	 * @param Page $page
	 * @since 3.0.253
	 * 
	 */
	public function ___addReady(Page $page) {
	}

	/**
	 * Hook called after a new page has been added
	 * 
	 * #pw-group-add-hooks
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
	 * Hook called when a page is about to be moved to another parent
	 *
	 * Note the previous parent is accessible in the `$page->parentPrevious` property.
	 *
	 * #pw-group-move-hooks
	 *
	 * @param Page $page Page that is about to be moved. 
	 * @since 3.0.235
	 *
	 */
	public function ___moveReady(Page $page) {
	}

	/**
	 * Hook called when a page has been moved from one parent to another
	 *
	 * Note the previous parent is accessible in the `$page->parentPrevious` property.
	 * 
	 * #pw-group-move-hooks
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
	 * #pw-group-save-hooks
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
	 * Hook called when a Page is about to be trashed
	 * 
	 * #pw-group-trash-hooks
	 * 
	 * @param Page $page
	 * @since 3.0.163
	 * 
	 */
	public function ___trashReady(Page $page) {
	}
	
	/**
	 * Hook called when a page has been moved to the trash
	 * 
	 * #pw-group-trash-hooks
	 * 
	 * @param Page $page Page that was moved to the trash
	 *
	 */
	public function ___trashed(Page $page) { 
		$this->log("Trashed page", $page);
	}
	
	/**
	 * Hook called when a page is about to be moved OUT of the trash (restored)
	 *
	 * #pw-group-trash-hooks
	 *
	 * @param Page $page Page that is about to be restored
	 * @since 3.0.235
	 *
	 */
	public function ___restoreReady(Page $page) {
	}

	/**
	 * Hook called when a page has been moved OUT of the trash (restored)
	 * 
	 * #pw-group-trash-hooks
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
	 * #pw-group-save-hooks
	 *
	 * @param Page $page The page about to be saved
	 * @return array Optional extra data to add to pages save query, which the hook can populate. 
	 * @see Pages::savePageOrFieldReady(), Pages::saveFieldReady()
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
	 * #pw-group-delete-hooks
	 * 
	 * @param Page $page Page that is about to be deleted. 
	 * @param array $options Options passed to delete method (since 3.0.163)
	 *
	 */
	public function ___deleteReady(Page $page, array $options = array()) {
		if($options) {} // ignore
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->deleteReady($page);
		}
	}

	/**
	 * Hook called after a page and its data have been deleted
	 * 
	 * #pw-group-delete-hooks
	 * 
	 * @param Page $page Page that was deleted
	 * @param array $options Options passed to delete method (since 3.0.163)
	 *
	 */
	public function ___deleted(Page $page, array $options = array()) { 
		if($options) {}
		if(empty($options['_deleteBranch'])) $this->log("Deleted page", $page); 
		$this->wire()->cache->maintenance($page);
		foreach($this->types as $manager) {
			if($manager->hasValidTemplate($page)) $manager->deleted($page);
		}
	}
	
	/**
	 * Hook called before a branch of pages is about to be deleted, called on root page of branch only
	 *
	 * Note: this is called only on deletions that had 'recursive' option true and 1+ children.
	 *
	 * #pw-group-delete-hooks
	 *
	 * @param Page $page Page that was deleted
	 * @param array $options Options passed to delete method
	 * @since 3.0.163
	 *
	 */
	public function ___deleteBranchReady(Page $page, array $options) {
	}
	
	/**
	 * Hook called after a a branch of pages has been deleted, called on root page of branch only
	 * 
	 * Note: this is called only on deletions that had 'recursive' option true and 1+ children. 
	 *
	 * #pw-group-delete-hooks
	 *
	 * @param Page $page Page that was the root of the branch
	 * @param array $options Options passed to delete method
	 * @param int $numDeleted Number of pages deleted
	 * @since 3.0.163
	 *
	 */
	public function ___deletedBranch(Page $page, array $options, $numDeleted) {
		$this->log("Deleted branch with $numDeleted page(s)", $page);
	}

	/**
	 * Hook called when a page is about to be cloned, but before data has been touched
	 * 
	 * #pw-group-add-hooks
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The actual clone about to be saved
	 *
	 */
	public function ___cloneReady(Page $page, Page $copy) { }

	/**
	 * Hook called when a page has been cloned
	 * 
	 * #pw-group-add-hooks
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The completed cloned version of the page
	 *
	 */
	public function ___cloned(Page $page, Page $copy) { 
		$this->log("Cloned page to $copy->path", $page); 
	}
	
	/**
	 * Hook called when a page is about to be renamed i.e. had its name field change)
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
	 * #pw-group-move-hooks
	 *
	 * @param Page $page The $page that was renamed
	 * @since 3.0.235
	 *
	 */
	public function ___renameReady(Page $page) {
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
	 * #pw-group-move-hooks
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
	 * #pw-group-move-hooks
	 * 
	 * @param Page $page Page given to have sort adjusted
	 * @param bool $children If true, children of $page have been all been re-sorted
	 * @param int $total Total number of pages that had sort adjusted as a result
	 * 
	 */
	public function ___sorted(Page $page, $children = false, $total = 0) {
	}

	/**
	 * Hook called when a page status has been changed and saved
	 *
	 * Previous status may be accessed at `$page->statusPrevious`.
	 * 
	 * #pw-group-status-hooks
	 *
	 * @param Page $page 
	 *
	 */
	public function ___statusChanged(Page $page) {
		$status = $page->status; 
		$statusPrevious = $page->statusPrevious; 
		$isPublished = !$page->isUnpublished();
		$isInserted = $page->_inserted > 0;
		$wasPublished = !($statusPrevious & Page::statusUnpublished);
		
		if($isPublished && (!$wasPublished || $isInserted)) $this->published($page);
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
	 * #pw-group-status-hooks
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
	 * #pw-group-status-hooks
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
	 * #pw-group-status-hooks
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
	 * #pw-group-status-hooks
	 *
	 * @param Page $page 
	 *
	 */
	public function ___publishReady(Page $page) { }

	/**
	 * Hook called right before a published page is unpublished and saved
	 * 
	 * #pw-group-status-hooks
	 *
	 * @param Page $page 
	 *
	 */
	public function ___unpublishReady(Page $page) { }

	/**
	 * Hook called at the end of a $pages->find(), includes extra info not seen in the resulting PageArray
	 * 
	 * #pw-group-find-hooks
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
	 * #pw-group-save-hooks
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @see Pages::savePageOrFieldReady()
	 * 
	 */
	public function ___saveFieldReady(Page $page, Field $field) { }

	/**
	 * Hook called after Pages::saveField successfully executes
	 * 
	 * #pw-group-save-hooks
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @see Pages::savedPageOrField()
	 * 
	 */
	public function ___savedField(Page $page, Field $field) { 
		$this->log("Saved page field '$field->name'", $page); 
	}

	/**
	 * Hook called when either of Pages::save or Pages::saveField is ready to execute
	 * 
	 * #pw-group-save-hooks
	 *
	 * @param Page $page
	 * @param string $fieldName Populated only if call originates from saveField
	 * @see Pages::saveReady(), Pages::saveFieldReady()
	 *
	 */
	public function ___savePageOrFieldReady(Page $page, $fieldName = '') { }

	/**
	 * Hook called after either of Pages::save or Pages::saveField successfully executes
	 *
	 * #pw-group-save-hooks
	 *
	 * @param Page $page
	 * @param array $changes Names of fields
	 * @see Pages::saved(), Pages::savedField()
	 *
	 */
	public function ___savedPageOrField(Page $page, array $changes = array()) { }

}
