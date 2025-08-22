<?php namespace ProcessWire;

/**
 * ProcessWire Page
 *
 * Page is the class used by all instantiated pages and it provides functionality for:
 *
 * 1. Providing get/set access to the Page's properties
 * 2. Accessing the related hierarchy of pages (i.e. parents, children, sibling pages)
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Class used by all Page objects in ProcessWire.
 * #pw-summary-languages Multi-language methods require these core modules: `LanguageSupport`, `LanguageSupportFields`, `LanguageSupportPageNames`. 
 * #pw-summary-system Most system properties directly correspond to columns in the `pages` database table. 
 * #pw-summary-previous Provides access to the previously set runtime value of some Page properties. 
 * #pw-order-groups common,traversal,manipulation,date-time,access,output-rendering,status,constants,languages,system,advanced,hooks
 * #pw-use-constants
 * #pw-var $page
 * #pw-body = 
 * The `$page` API variable represents the current page being viewed. However, the documentation 
 * here also applies to all Page objects that you may work with in the API. We use `$page` as the most common example
 * throughout the documentation, but you can substitute that with any variable name representing a Page.
 * #pw-body
 *
 * @link http://processwire.com/api/ref/page/ Offical $page Documentation
 * @link http://processwire.com/api/selectors/ Official Selectors Documentation
 *
 * @property int $id The numbered ID of the current page #pw-group-system #pw-group-common
 * @property string $name The name assigned to the page, as it appears in the URL #pw-group-system #pw-group-common
 * @property string|null $namePrevious Previous name, if changed. Null or blank string if not. #pw-group-previous
 * @property string $title The page’s title (headline) text
 * @property string $path The page’s URL path from the homepage (i.e. /about/staff/ryan/) 
 * @property string $url The page’s URL path from the server's document root
 * @property array $urls All URLs the page is accessible from, whether current, former and multi-language. #pw-group-urls
 * @property string $httpUrl Same as $page->url, except includes scheme (http or https) and hostname.
 * @property Page|string|int $parent The parent Page object or a NullPage if there is no parent. For assignment, you may also use the parent path (string) or id (integer). #pw-group-traversal
 * @property Page|null $parentPrevious Previous parent, if parent was changed. Null if not. #pw-group-previous
 * @property int $parent_id The numbered ID of the parent page or 0 if homepage or not assigned. #pw-group-system
 * @property int $templates_id The numbered ID of the template usedby this page. #pw-group-system
 * @property PageArray $parents All the parent pages down to the root (homepage). Returns a PageArray. #pw-group-common #pw-group-traversal
 * @property Page $rootParent The parent page closest to the homepage (typically used for identifying a section) #pw-group-traversal
 * @property Template|string $template The Template object this page is using. The template name (string) may also be used for assignment.
 * @property Template|null $templatePrevious Previous template, if template was changed. Null if not. #pw-group-previous
 * @property Fieldgroup $fields All the Fields assigned to this page (via its template). Returns a Fieldgroup. #pw-advanced
 * @property int $numChildren The number of children (subpages) this page has, with no exclusions (fast). #pw-group-traversal
 * @property int $hasChildren The number of visible children this page has. Excludes unpublished, no-access, hidden, etc. #pw-group-traversal
 * @property int $numVisibleChildren Verbose alias of $hasChildren #pw-internal
 * @property int $numDescendants Number of descendants (quantity of children, and their children, and so on). @since 3.0.116 #pw-group-traversal
 * @property int $numParents Number of parent pages (i.e. depth) @since 3.0.117 #pw-group-traversal
 * @property PageArray $children All the children of this page. Returns a PageArray. See also $page->children($selector). #pw-group-traversal
 * @property Page|NullPage $child The first child of this page. Returns a Page. See also $page->child($selector). #pw-group-traversal
 * @property PageArray $siblings All the sibling pages of this page. Returns a PageArray. See also $page->siblings($selector). #pw-group-traversal
 * @property Page $next This page's next sibling page, or NullPage if it is the last sibling. See also $page->next($pageArray). #pw-group-traversal
 * @property Page $prev This page's previous sibling page, or NullPage if it is the first sibling. See also $page->prev($pageArray). #pw-group-traversal
 * @property int $created Unix timestamp of when the page was created. #pw-group-date-time #pw-group-system
 * @property string $createdStr Date/time when the page was created (formatted date/time string). #pw-group-date-time
 * @property int $modified Unix timestamp of when the page was last modified. #pw-group-date-time #pw-group-system
 * @property string $modifiedStr Date/time when the page was last modified (formatted date/time string). #pw-group-date-time
 * @property int $published Unix timestamp of when the page was published. #pw-group-date-time #pw-group-system
 * @property string $publishedStr Date/time when the page was published (formatted date/time string). #pw-group-date-time
 * @property int $created_users_id ID of created user. #pw-group-system #pw-group-users
 * @property User|NullPage $createdUser The user that created this page. Returns a User or a NullPage. #pw-group-users
 * @property int $modified_users_id ID of last modified user. #pw-group-system #pw-group-users
 * @property User|NullPage $modifiedUser The user that last modified this page. Returns a User or a NullPage. #pw-group-users
 * @property PagefilesManager $filesManager The object instance that manages files for this page. #pw-group-files
 * @property string $filesPath Get the disk path to store files for this page, creating it if it does not exist. #pw-group-files
 * @property string $filesUrl Get the URL to store files for this page, creating it if it does not exist. #pw-group-files
 * @property bool $hasFiles Does this page have one or more files in its files path? #pw-group-files
 * @property bool $outputFormatting Whether output formatting is enabled or not. Same as calling $page->of() with no arguments. #pw-advanced
 * @property int $sort Sort order of this page relative to siblings (applicable when manual sorting is used). #pw-group-system
 * @property int|null $sortPrevious Previous sort order, if changed (3.0.235+) #pw-group-system
 * @property int $index Index of this page relative to its siblings, regardless of sort (starting from 0). #pw-group-traversal
 * @property string $sortfield Field that a page is sorted by relative to its siblings (default="sort", which means drag/drop manual) #pw-group-system
 * @property null|array _statusCorruptedFields Field names that caused the page to have Page::statusCorrupted status. #pw-internal
 * @property int $status Page status flags. #pw-group-system #pw-group-status
 * @property int|null $statusPrevious Previous status, if status was changed. Null if not. #pw-group-status #pw-group-previous
 * @property string statusStr Returns space-separated string of status names active on this page. #pw-group-status
 * @property Fieldgroup $fieldgroup Fieldgroup used by page template. Shorter alias for $page->template->fieldgroup (same as $page->fields) #pw-advanced
 * @property string $editUrl URL that this page can be edited at. #pw-group-urls
 * @property string $editURL Alias of $editUrl. #pw-internal
 * @property PageRender $render May be used for field markup rendering like $page->render->title. #pw-advanced
 * @property bool|string $loaderCache Whether or not pages loaded as a result of this one may be cached by PagesLoaderCache. #pw-internal
 * @property PageArray $references Return pages that are referencing the given one by way of Page references. #pw-group-traversal
 * @property int $numReferences Total number of pages referencing this page with Page reference fields. #pw-group-traversal
 * @property int $hasReferences Number of visible pages (to current user) referencing this page with page reference fields. #pw-group-traversal
 * @property PageArray $referencing Return pages that this page is referencing by way of Page reference fields. #pw-group-traversal
 * @property int $numReferencing Total number of other pages this page is pointing to (referencing) with Page fields. #pw-group-traversal
 * @property PageArray $links Return pages that link to this one contextually in Textarea/HTML fields. #pw-group-traversal
 * @property int $numLinks Total number of pages manually linking to this page in Textarea/HTML fields. #pw-group-traversal
 * @property int $hasLinks Number of visible pages (to current user) linking to this page in Textarea/HTML fields. #pw-group-traversal
 * @property int $instanceID #pw-internal
 * @property bool $quietMode #pw-internal
 * @property WireData|null $_meta #pw-internal
 * @property WireData $meta #pw-internal
 * @property array $wakeupNameQueue #pw-internal
 * 
 * 
 * @property Page|null $_cloning Internal runtime use, contains Page being cloned (source), when this Page is the new copy (target). #pw-internal
 * @property int |null $_inserted Populated with time() value of when new page was inserted into DB, only for page created in this request. #pw-internal
 * @property bool|null $_hasAutogenName Internal runtime use, set by Pages class when page as auto-generated name. #pw-internal
 * @property bool|null $_forceSaveParents Internal runtime/debugging use, force a page to refresh its pages_parents DB entries on save(). #pw-internal
 * @property float|null $_pfscore Internal PageFinder fulltext match score when page found/loaded from relevant query. #pw-internal
 * 
 * Methods added by PageRender.module: 
 * -----------------------------------
 * @method string|mixed render($arg1 = null, $arg2 = null) Returns rendered page markup. Please see the `PageRender::renderPage()` method for arguments and usage details. #pw-group-output-rendering
 * 
 * Methods added by PagePermissions.module: 
 * ----------------------------------------
 * @method bool viewable($field = '', $checkTemplateFile = true) Returns true if the page (and optionally field) is viewable by the current user, false if not. #pw-group-access
 * @method bool editable($field = '', $checkPageEditable = true) Returns true if the page (and optionally field) is editable by the current user, false if not. #pw-group-access
 * @method bool publishable() Returns true if the page is publishable by the current user, false if not. #pw-group-access
 * @method bool listable() Returns true if the page is listable by the current user, false if not. #pw-group-access
 * @method bool deleteable() Returns true if the page is deleteable by the current user, false if not. #pw-group-access
 * @method bool deletable() Alias of deleteable(). #pw-group-access
 * @method bool trashable($orDeleteable = false) Returns true if the page is trashable by the current user, false if not. #pw-group-access
 * @method bool restorable() Returns true if page is in the trash and is capable of being restored to its original location. @since 3.0.107 #pw-group-access
 * @method bool addable($pageToAdd = null) Returns true if the current user can add children to the page, false if not. Optionally specify the page to be added for additional access checking. #pw-group-access
 * @method bool moveable($newParent = null) Returns true if the current user can move this page. Optionally specify the new parent to check if the page is moveable to that parent. #pw-group-access
 * @method bool sortable() Returns true if the current user can change the sort order of the current page (within the same parent). #pw-group-access
 * @method bool cloneable($recursive = null) Can current user clone this page? Specify false for $recursive argument to ignore whether children are cloneable. @since 3.0.239 #pw-group-access
 * @property bool $viewable #pw-group-access
 * @property bool $editable #pw-group-access
 * @property bool $publishable #pw-group-access
 * @property bool $deleteable #pw-group-access
 * @property bool $deletable #pw-group-access
 * @property bool $trashable #pw-group-access
 * @property bool $addable #pw-group-access
 * @property bool $moveable #pw-group-access
 * @property bool $sortable #pw-group-access
 * @property bool $listable #pw-group-access
 * @property bool $cloneable @since 3.0.239 #pw-group-access 
 * 
 * Methods added by PagePathHistory.module (installed by default)
 * --------------------------------------------------------------
 * @method bool addUrl($url, $language = null) Add a new URL that redirects to this page and save immediately (returns false if already taken). #pw-group-urls #pw-group-manipulation
 * @method bool removeUrl($url) Remove a URL that redirects to this page and save immediately. #pw-group-urls #pw-group-manipulation
 * Note: you can use the $page->urls() method to get URLs added by PagePathHistory.
 * 
 * Methods added by LanguageSupport.module (not installed by default) 
 * -----------------------------------------------------------------
 * @method Page setLanguageValue($language, $field, $value) Set value for field in language (requires LanguageSupport module). $language may be ID, language name or Language object. Field should be field name (string). #pw-group-languages
 * @method Page setLanguageValues($field, array $values) Set value for field in one or more languages (requires LanguageSupport module). $field should be field/property name (string), $values should be array of values indexed by language name. @since 3.0.236 #pw-group-languages
 * @method mixed getLanguageValue($language, $field) Get value for field in language (requires LanguageSupport module). $language may be ID, language name or Language object. Field should be field name (string). #pw-group-languages
 * @method array getLanguageValues($field, array $langs = []) Get values for field or one or more languages (requires LanguageSupport module). $field should be field/property name (string), $langs should be array of language names, or omit for all languages. Returns array of values indexed by language name. @since 3.0.236 #pw-group-languages
 * 
 * Methods added by LanguageSupportPageNames.module (not installed by default)
 * ---------------------------------------------------------------------------
 * @method string localName($language = null, $useDefaultWhenEmpty = false) Return the page name in the current user’s language, or specify $language argument (Language object, name, or ID), or TRUE to use default page name when blank (instead of 2nd argument). #pw-group-languages
 * @method string localPath($language = null) Return the page path in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 * @method string localUrl($language = null) Return the page URL in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 * @method string localHttpUrl($language = null) Return the page URL (including scheme and hostname) in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 * @method Page setLanguageStatus($language, $status = null) Set active status for language(s), can be called as `$page->setLanguageStatus('es', true);` or `$page->setLanguageStatus([ 'es' => true, 'br' => false ]);` to set multiple. @since 3.0.236 #pw-group-languages 
 * @method array|bool getLanguageStatus($language = []) Get active status for language(s). If given a $language (Language or name of language) it returns a boolean. If given multiple language names (array), or argument omitted, it returns array like `[ 'default' => true, 'fr' => false ];`. @since 3.0.236 #pw-group-languages 
 * @method Page setLanguageName($language, $name = null) Set page name for language with `$page->setLanguageName('es', 'hola');` or set multiple with `$page->setLanguageName([ 'default' => 'hello', 'es' => 'hola' ]);` @since 3.0.236 #pw-group-languages 
 * @method array|string getLanguageName($language = []) Get page name for language(s). If given a Language object, it returns a string. If given array of language names, or argument omitted, it returns an array like `[ 'default' => 'hello', 'es' => 'hola' ];`. @since 3.0.236 #pw-group-languages 
 *
 * Methods added by PageFrontEdit.module (not always installed by default)
 * -----------------------------------------------------------------------
 * @method string|bool|mixed edit($key = null, $markup = null, $modal = null) Get front-end editable field output or get/set status.
 * 
 * Methods added by ProDrafts.module (if installed)
 * ------------------------------------------------
 * @method ProDraft|int|string|Page|array draft($key = null, $value = null) Helper method for drafts (added by ProDrafts). #pw-advanced
 * 
 * Hookable methods
 * ----------------
 * @method mixed getUnknown($key) Last stop to find a property that we haven't been able to locate. Hook this method to provide a handler. #pw-hooker
 * @method Page rootParent() Get parent closest to homepage. #pw-internal
 * @method void loaded() Called when page is loaded. #pw-internal
 * @method void setEditor(WirePageEditor $editor) #pw-internal
 * @method string getIcon() #pw-internal
 * @method string getMarkup($key) Return the markup value for a given field name or {tag} string. #pw-internal
 * @method string|mixed renderField($fieldName, $file = '') Returns rendered field markup, optionally with file relative to templates/fields/. #pw-internal
 * @method string|mixed renderValue($value, $file) Returns rendered markup for $value using $file relative to templates/fields/. #pw-internal
 * @method PageArray references($selector = '', $field = '') Return pages that are pointing to this one by way of Page reference fields. #pw-group-traversal
 * @method PageArray links($selector = '', $field = '') Return pages that link to this one contextually in Textarea/HTML fields. #pw-group-traversal
 * @method string|mixed if($key, $yes, $no = '') If value is available for $key return or call $yes condition (with optional $no condition)
 * 
 * Alias/alternate methods
 * -----------------------
 * @method PageArray descendants($selector = '', array $options = array()) Find descendant pages, alias of `Page::find()`, see that method for details. @since 3.0.116 #pw-group-traversal
 * @method Page|NullPage descendant($selector = '', array $options = array()) Find one descendant page, alias of `Page::findOne()`, see that method for details. @since 3.0.116 #pw-group-traversal
 * 
 */

class Page extends WireData implements \Countable, WireMatchable {

	/*
	 * The following constant flags are specific to a Page's 'status' field. A page can have 1 or more flags using bitwise logic. 
	 * Status levels 1024 and above are excluded from search by the core. Status levels 16384 and above are runtime only and not 
	 * stored in the DB unless for logging or page history.
	 *
	 * The status levels 16384 and above can safely be changed as needed as they are runtime only. 
	 * 
	 * Please note that all other statuses are reserved for future use.
	 *
	 */

	/**
	 * Base status for pages, represents boolean true (1) or false (0) as flag with other statuses, for internal use purposes only
	 * #pw-internal
	 * 
	 */
	const statusOn = 1;
	
	/**
	 * Reserved status (internal use)
	 * #pw-internal
	 * 
	 */
	const statusReserved = 2;

	/**
	 * Indicates page is locked for changes (name: "locked")
	 * 
	 */
	const statusLocked = 4;

	/**
	 * Page is for the system and may not be deleted or have its id changed (name: "system-id").
	 * #pw-internal
	 * 
	 */
	const statusSystemID = 8;

	/**
	 * Page is for the system and may not be deleted or have its id, name, template or parent changed (name: "system"). 
	 * #pw-internal
	 * 
	 */
	const statusSystem = 16;

	/**
	 * Page has a globally unique name and no other pages may have the same name
	 * #pw-internal
	 * 
	 */
	const statusUnique = 32;

	/**
	 * Page has pending draft changes (name: "draft"). 
	 * #pw-internal
	 * 
	 */
	const statusDraft = 64;

	/**
	 * Page is flagged as incomplete, needing review, or having some issue
	 * ProcessPageEdit uses this status to indicate an error message occurred during last internactive save
	 * #pw-internal
	 * @since 3.0.127
	 * 
	 */
	const statusFlagged = 128;
	const statusIncomplete = 128; // alias of statusFlagged
	
	/**
	 * Deprecated, was never used, but kept in case any modules referenced it
	 * #pw-internal
	 * @deprecated
	 * 
	 */
	const statusVersions = 128; 
	
	/**
	 * Reserved for internal use 
	 * #pw-internal
	 * @since 3.0.127
	 *
	 */
	const statusInternal = 256;

	/**
	 * Page is temporary. 1+ day old unpublished pages with this status may be automatically deleted (name: "temp"). 
	 * Applies only if this status is combined with statusUnpublished. 
	 * #pw-internal
	 * 
	 */
	const statusTemp = 512;

	/**
	 * Page is hidden and excluded from page finding methods unless overridden by selector (name: "hidden"). 
	 * 
	 */
	const statusHidden = 1024;

	/**
	 * Page is unpublished (not publicly visible) and excluded from page finding methods unless overridden (name: "unpublished").
	 * 
	 */
	const statusUnpublished = 2048;

	/**
	 * Page is in the trash.
	 * #pw-internal
	 * 
	 */
	const statusTrash = 8192; 		// page is in the trash

	/**
	 * Page is deleted (runtime status only, as deleted pages aren't saved in the database)
	 * #pw-internal
	 * 
	 */
	const statusDeleted = 16384;

	/**
	 * Page is in a state where system flags may be overridden (runtime only)
	 * #pw-internal
	 * 
	 */
	const statusSystemOverride = 32768;

	/**
	 * Page was corrupted at runtime and is NOT saveable.
	 * #pw-internal
	 * 
	 */
	const statusCorrupted = 131072;

	/**
	 * Maximum possible page status, to use only for runtime comparisons - do not assign this to a page.
	 * #pw-internal
	 * 
	 */
	const statusMax = 9999999;
	
	/**
	 * The Template this page is using (object)
	 *
	 * @var Template|null
	 * 
	 */
	protected $template;

	/**
	 * The previous template used by the page, if it was changed during runtime. 	
	 *
	 * Allows Pages::save() to delete data that's no longer used. 
	 * 
	 * @var Template|null
	 *
	 */
	private $templatePrevious; 

	/**
	 * Parent Page - Instance of Page
	 * 
	 * @var Page|null
	 *
	 */
	protected $_parent = null;

	/**
	 * Parent ID for lazy loading purposes
	 * 
	 * @var int
	 * 
	 */
	protected $_parent_id = 0;

	/**
	 * Traversal siblings/items set by setTraversalItems() to force usage in some page traversal calls
	 * 
	 * @var PageArray|null
	 * 
	 */
	protected $traversalPages = null;

	/**
	 * The previous parent used by the page, if it was changed during runtime. 	
	 *
	 * Allows Pages::save() to identify when the parent has changed
	 * 
	 * @var Page|null
	 *
	 */
	private $parentPrevious; 

	/**
	 * The previous name used by this page, if it changed during runtime.
	 * 
	 * @var string
	 *
	 */
	private $namePrevious;

	/**
	 * The previous sort value used by page, if changed during runtime.
	 * 
	 * @var int
	 * 
	 */
	private $sortPrevious;

	/**
	 * The previous status used by this page, if it changed during runtime.
	 * 
	 * @var int
	 *
	 */
	private $statusPrevious; 

	/**
	 * Reference to the Page's template file, used for output. Instantiated only when asked for. 
	 * 
	 * @var TemplateFile|null
	 *
	 */
	private $output; 

	/**
	 * Instance of PagefilesManager, which manages and migrates file versions for this page
	 *
	 * Only instantiated upon request, so access only from filesManager() method in Page class. 
	 * Outside API can use $page->filesManager.
	 * 
	 * @var PagefilesManager|null
	 *
	 */
	private $filesManager = null;

	/**
	 * Field data that queues while the page is loading. 
	 *
	 * Once setIsLoaded(true) is called, this data is processed and instantiated into the Page and 
	 * the fieldDataQueue is emptied (and no longer relevant)	
	 * 
	 * @var array
	 *
	 */
	protected $fieldDataQueue = array();

	/**
	 * Field names that should wakeup and sanitize on first access (populated when isLoaded==false)
	 * 
	 * These are most likely field names designated as autoload for this page. 
	 * 
	 * @var array of (field name => true)
	 * 
	 */
	protected $wakeupNameQueue = array();

	/**
	 * Is this a new page (not yet existing in the database)?
	 * 
	 * @var bool
	 *
	 */
	protected $isNew = true; 

	/**
	 * Is this Page finished loading from the DB (i.e. Pages::getById)?
	 *
	 * When false, it is assumed that any values set need to be woken up. 
	 * When false, it also assumes that built-in properties (like name) don't need to be sanitized. 
	 *
	 * Note: must be kept in the 'true' state. Pages::getById sets it to false before populating data and then back to true when done.
	 * 
	 * @var bool
	 *
	 */
	protected $isLoaded = true;

	/**
	 * Lazy load state of page
	 * 
	 * - int: Page is pending lazy loading, and not yet populated.
	 * - false: Page is not lazy loading.
	 * - true: Page was lazy loading and has already loaded. 
	 * 
	 * @var bool|int
	 * 
	 */
	protected $lazyLoad = false;

	/**
	 * Whether or not pages loaded by this one are allowed to be cached by PagesLoaderCache class
	 * 
	 * @var bool|string Bool for yes/no or string for yes w/group name where page cached/cleared with others having same group name.
	 * 
	 */
	protected $loaderCache = true;

	/**
	 * Is this page allowing it's output to be formatted?
	 *
	 * If so, the page may not be saveable because calls to $page->get(field) are returning versions of 
	 * variables that may have been formatted at runtime for output. An exception will be thrown if you
	 * attempt to set the value of a formatted field when $outputFormatting is on. 
	 *
	 * Output formatting should be turned off for pages that you are manipulating and saving. 
	 * Whereas it should be turned on for pages that are being used for output on a public site. 
	 * Having it on means that Textformatters and any other output formatters will be executed
	 * on any values returned by this page. Likewise, any values you set to the page while outputFormatting
	 * is set to true are considered potentially corrupt. 
	 * 
	 * @var bool
	 *
	 */
	protected $outputFormatting = false; 

	/**
	 * A unique instance ID assigned to the page at the time it's loaded (for debugging purposes only)
	 * 
	 * @var int
	 *
	 */
	protected $instanceID = 0; 

	/**
	 * IDs for all the instances of pages, used for debugging and testing.
	 *
	 * Indexed by $instanceID => $pageID
	 * 
	 * @var array
	 *
	 */
	static public $instanceIDs = array();

	/**
	 * Stack of ID indexed Page objects that are currently in the loading process. 
	 *
	 * Used to avoid possible circular references when multiple pages referencing each other are being populated at the same time.
	 * 
	 * @var array
	 *
	 */
	static public $loadingStack = array();

	/**
	 * Controls the behavior of Page::__isset function (no longer in use)
	 * 
	 * @var bool
	 * @deprecated No longer in use
	 * 
	 */
	static public $issetHas = false; 

	/**
	 * The current page number, starting from 1
	 *
	 * @deprecated, use $input->pageNum instead. 
	 * 
	 * @var int
	 *
	 */
	protected $pageNum = 1; 

	/**
	 * When true, exceptions not thrown when values set before templates, and changes not tracked
	 * 
	 * @var bool
	 *
	 */
	protected $quietMode = false;

	/**
	 * Cached User that created this page
	 * 
	 * @var User|null
	 * 
	 */
	protected $_createdUser = null;

	/**
	 * Cached User that last modified the page
	 * 
	 * @var User|null
	 * 
	 */
	protected $_modifiedUser = null;

	/**
	 * Page-specific settings which are either saved in pages table, or generated at runtime.
	 * 
	 * @var array
	 *
	 */
	protected $settings = array(
		'id' => 0, 
		'name' => '', 
		'status' => 1, 
		'numChildren' => 0, 
		'sort' => -1, 
		'sortfield' => 'sort', 
		'modified_users_id' => 0, 
		'created_users_id' => 0,
		'created' => 0,
		'modified' => 0,
		'published' => 0,
	);

	/**
	 * Page meta data
	 * 
	 * @var null|WireDataDB
	 * 
	 */
	protected $_meta = null;

	/**
	 * Create a new page in memory. 
	 *
	 * @param Template|null $tpl Template object this page should use. 
	 *
	 */
	public function __construct(?Template $tpl = null) {
		parent::__construct();
		if($tpl !== null) {
			$tpl->wire($this);
			$this->template = $tpl;
		} 
		$this->useFuel(false); // prevent fuel from being in local scope
		$this->parentPrevious = null;
		$this->templatePrevious = null;
		$this->statusPrevious = null;
		$this->sortPrevious = null;
	}

	/**
	 * Destruct this page instance
	 *
	 */
	public function __destruct() {
		if($this->instanceID) {
			// remove from the record of instanceID, so that we have record of page's that HAVEN'T been destructed. 
			unset(self::$instanceIDs[$this->instanceID]); 
		}
	}

	/**
	 * Clone this page instance
	 *
	 */
	public function __clone() {
		$track = $this->trackChanges();
		$this->setTrackChanges(false); 
		if($this->filesManager) {
			$this->filesManager = clone $this->filesManager; 
			$this->filesManager->setPage($this);
		}
		$this->_meta = null;
		$template = $this->template();
		if($template) {
			foreach($template->fieldgroup as $field) {
				/** @var Field $field */
				$name = $field->name;
				if(!$field->type) continue;
				if(!$field->type->isAutoload() && !isset($this->data[$name])) continue; // important for draft loading
				$value = $this->get($name);
				// no need to clone non-objects, as they've already been cloned
				// no need to clone Page objects as we still want to reference the original page
				if(!is_object($value) || $value instanceof Page) continue;
				$value2 = clone $value;
				$this->set($name, $value2); // commit cloned value
				// if value is Pagefiles, then tell it the new page
				if($value2 instanceof PageFieldValueInterface) $value2->setPage($this);
			}
		}
		$this->instanceID .= ".clone";
		if($track) $this->setTrackChanges(true); 
		parent::__clone();
	}

	/**
	 * Set the value of a page property
	 * 
	 * You can set properties to a page using either `$page->set('property', $value);` or `$page->property = $value;`. 
	 * 
	 * ~~~~~
	 * // Set the page title using set() method
	 * $page->set('title', 'About Us'); 
	 * 
	 * // Set the page title directly (equivalent to the above)
	 * $page->title = 'About Us';
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-manipulation
	 *
	 * @param string $key Name of property to set
	 * @param mixed $value Value to set
	 * @return Page|WireData Reference to this Page
	 * @see __set
	 * @throws WireException
	 *
	 */
	public function set($key, $value) {
		
		if(isset(PageProperties::$basePropertiesAlternates[$key])) $key = PageProperties::$basePropertiesAlternates[$key];

		if($this->isLoaded && ($key === 'id' || $key === 'name') && $this->settings[$key] && $value != $this->settings[$key]) {
			$sys = $this->settings['status'] & Page::statusSystem;
			$sysID = $this->settings['status'] & Page::statusSystemID;
			if(($key === 'id' && ($sys || $sysID)) || ($key === 'name' && $sys)) {
				throw new WireException("You may not modify '$key' on page #$this->id ($this->path) because it is a system page");
			}
		}

		switch($key) {
			/** @noinspection PhpMissingBreakStatementInspection */
			case 'id':
				if(!$this->isLoaded) Page::$loadingStack[(int) $value] = $this;
				// no break is intentional
			case 'sort': 
			case 'numChildren':
			case 'created_users_id':
			case 'modified_users_id':
				$value = (int) $value; 
				if($this->isLoaded && $this->settings[$key] !== $value) $this->trackChange($key, $this->settings[$key], $value);
				$this->settings[$key] = $value; 
				break;
			case 'status':
				$this->setStatus($value); 
				break;
			case 'statusPrevious':
			case 'sortPrevious':	
				$this->$key = is_null($value) ? null : (int) $value; 
				break;
			case 'name':
				$this->setName($value);
				break;
			case 'parent': 
			case 'parent_id':
				if($value instanceof Page) {
					// ok
					$this->setParent($value);
				} else if($value && !$this->_parent && (!$this->_parent_id || !$this->isLoaded) && 	
					($key == 'parent_id' || is_int($value) || (is_string($value) && ctype_digit("$value")))) {
					// store only parent ID so that parent is lazy loaded,
					// but only if parent hasn't already been previously loaded
					$this->_parent_id = (int) $value;	
				} else if($value && (is_string($value) || is_int($value))) {
					$value = $this->_pages('get', $value);
					$this->setParent($value);
				}
				break;
			case 'parentPrevious':
				if(is_null($value) || $value instanceof Page) $this->parentPrevious = $value; 
				break;
			case 'template': 
			case 'templates_id':
				if($key === 'templates_id' && $this->template && $this->template->id == $value) break;
				if($key === 'templates_id') $value = $this->wire()->templates->get((int) $value); 
				$this->setTemplate($value); 
				break;
			case 'created': 
			case 'modified':
			case 'published':
				if($value === null) $value = 0;
				if($value && !ctype_digit("$value")) $value = $this->wire()->datetime->strtotime($value); 
				$value = (int) $value; 
				if($this->isLoaded && $this->settings[$key] !== $value) $this->trackChange($key, $this->settings[$key], $value); 
				$this->settings[$key] = $value;
				break;
			case 'createdUser':
			case 'modifiedUser':
				$this->setUser($value, str_replace('User', '', $key));
				break;
			case 'sortfield':
				$template = $this->template();
				if($template && $template->sortfield) break;
				$value = $this->wire()->pages->sortfields()->decode($value); 
				if($this->isLoaded && $this->settings[$key] != $value) $this->trackChange($key, $this->settings[$key], $value); 
				$this->settings[$key] = $value; 
				break;
			case 'isLoaded': 
				$this->setIsLoaded($value); 
				break;
			case 'pageNum':
				// note: pageNum is deprecated, use $input->pageNum instead
				/** @noinspection PhpDeprecationInspection */
				$this->pageNum = ((int) $value) > 1 ? (int) $value : 1; 
				break;
			case 'instanceID': 
				$this->instanceID = $value; 
				self::$instanceIDs[$value] = $this->settings['id']; 
				break;
			case 'loaderCache':
				$this->loaderCache = is_bool($value) || ctype_digit("$value") ? (bool) $value : (string) $value;	
				break;
			default:
				if(isset(PageProperties::$languageProperties[$key])) {
					list($property, $languageId) = PageProperties::$languageProperties[$key];
					if($property === 'name') {
						$this->setName($value, $languageId); // i.e. name1234
					} else if($property === 'status') {
						parent::set($key, (int) $value); // i.e. status1234
					}
				} else {
					if($this->quietMode && !$this->template) return parent::set($key, $value);
					$this->values()->setFieldValue($this, $key, $value, $this->isLoaded);
				}
		}
		return $this; 
	}

	/**
	 * Quietly set the value of a page property. 
	 * 
	 * Set a value to a page without tracking changes and without exceptions.
	 * Otherwise same as set().
	 * 
	 * #pw-advanced
	 * #pw-group-manipulation
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 *
	 */
	public function setQuietly($key, $value) {
		if(isset($this->settings[$key]) && is_int($value)) {
			// allow integer-only values in $this->settings to be set directly in quiet mode
			$this->settings[$key] = $value;
		} else {
			$quietMode = $this->quietMode;
			if(!$quietMode) $this->quietMode = true; 
			parent::setQuietly($key, $value);
			if(!$quietMode) $this->quietMode = false;
		}
		return $this; 
	}

	/**
	 * Force setting a value, skipping over any checks or errors
	 * 
	 * Enables setting a value when page has no template assigned, for example. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $key Name of field/property to set
	 * @param mixed $value Value to set
	 * @return Page|WireData Returns reference to this page
	 * 
	 */
	public function setForced($key, $value) {
		if(isset($this->settings[$key])) {
			$this->settings[$key] = $value;
		} else {
			parent::set($key, $value);
		}
		return $this;
	}

	/**
	 * Set the value of a field that is defined in the page's Fieldgroup
	 *
	 * This may not be called when outputFormatting is on. 
	 *
	 * This is for internal use. API should generally use the set() method, but this is kept public for the minority of instances where it's useful.
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param bool $load Should the existing value be loaded for change comparisons? (applicable only to non-autoload fields)
	 * @return Page|WireData Returns reference to this Page
	 * @throws WireException
	 *
	 */
	public function setFieldValue($key, $value, $load = true) {
		return $this->values()->setFieldValue($this, $key, $value, $load);
	}

	/**
	 * Get the value of a Page property (see details for several options)
	 * 
	 * This method can accept a simple property name, and also much more: 
	 * 
	 * - You can retrieve a value using either `$page->get('property');` or `$page->property`. 
	 * - Get the first populated property by specifying multiple properties separated by a pipe, i.e. `headline|title`. 
	 * - Get multiple properties in a string by specifying a string `{property}` tags, i.e. `{title}: {summary}`. 
	 * - Specify a selector string to get the first matching child page, i.e. `created>=today`.
	 * - This method can also retrieve sub-properties of object properties, i.e. `parent.title`.
	 * - To get a guaranteed iterable value, append `[]` to the key, i.e. `$page->get('name[]')`. 3.0.205+
	 * 
	 * ~~~~~
	 * // retrieve the title using get…
	 * $title = $page->get('title');
	 * 
	 * // …or retrieve using direct access
	 * $title = $page->title;
	 * 
	 * // retrieve headline if populated, otherwise title
	 * $headline = $page->get('headline|title'); 
	 * 
	 * // retrieve title, created date, and summary, formatted in a string
	 * $str = $page->get('{createdStr}: {title} - {summary}'); 
	 * 
	 * // example of getting a sub-property: title of parent page
	 * $title = $page->get('parent.title'); 
	 * 
	 * // all following features are supported in 3.0.205+
	 * 
	 * // get value guaranteed to be iterable (array, WireArray, or derived)
	 * $images = $page->get('image[]');
	 * $categories = $page->get('category[]');
	 * 
	 * // get item by position/index, returns 1 item whether field is single or multi value
	 * $file = $page->get('files[0]'); // get first file  (or null if files is empty)
	 * $file = $page->get('files.first); // same as above
	 * $file = $page->get('files.last'); // get last file
	 * $file = $page->get('files[1]'); // get 2nd file (or null if there isn't one)
	 * 
	 * // get titles from Page reference field categories in an array
	 * $titles = $page->get('categories.title');  // array of titles
	 * $title = $page->get('categories[0].title'); // string of just first title
	 * 
	 * // you can also use a selector in [brackets] for a filtered value
	 * // example: get categories with titles matching text 'design'
	 * $categories = $page->get('categories[title%=design]'); // PageArray
	 * $category = $page->get('categories[title%=design][0]'); // Page or null
	 * $titles = $page->get('categories[title%=design].title'); // array of strings
	 * $title = $page->get('categories[title%=design].title[0]'); // string or null
	 * ~~~~~
	 *
	 * @param string $key Name of property, format string or selector, per the details above. 
	 * @return mixed Value of found property, or NULL if not found. 
	 * @see __get()
	 *
	 */
	public function get($key) {

		// if lazy load pending, load the page now
		if($this->lazyLoad && $key !== 'id' && is_int($this->lazyLoad)) $this->_lazy(true);

		if(is_array($key)) $key = implode('|', $key);
		if(empty($key)) return null;
		if(isset(PageProperties::$basePropertiesAlternates[$key])) {
			$key = PageProperties::$basePropertiesAlternates[$key];
		}
		if(isset(PageProperties::$baseProperties[$key])) {
			$type = PageProperties::$baseProperties[$key];
			if($type === 'p') {
				// local property
				return $this->$key;
			} else if($type === 'm') {
				// local method
				return $this->{$key}();
			} else if($type === 'n') {
				// local method, possibly overridden by $field
				if(!$this->wire()->fields->get($key)) return $this->{$key}();
			} else if($type === 's') {
				// settings property
				return $this->settings[$key];
			} else if($type === 't') {
				// map to method in PageTraversal, if not overridden by field
				if(!$this->wire()->fields->get($key)) return $this->traversal()->{$key}($this);
			} else if($type) {
				// defined local method
				return $this->{$type}();
			}
		}
		
		switch($key) {
			case 'parent':
				$value = $this->_parent ? $this->_parent : $this->parent();
				break;
			case 'parent_id':
				$value = $this->_parent ? $this->_parent->id : 0; 
				if(!$value) $value = $this->_parent_id;
				break;
			case 'templates_id':
				$template = $this->template();
				$value = $template ? $template->id : 0;
				break;
			case 'fieldgroup':
				$template = $this->template();
				$value = $template ? $template->fieldgroup : null;
				break;
			case 'modifiedUser':
			case 'createdUser':
				$value = $this->getUser($key);
				if($value->id) $value->of($this->of());
				break;
			case 'urlSegment':
				// deprecated, but kept for backwards compatibility
				$value = $this->wire()->input->urlSegment1; 
				break;
			case 'statusStr':
				$value = implode(' ', $this->status(true)); 
				break;
			case 'modifiedStr':
			case 'createdStr':
			case 'publishedStr':
				$value = $this->settings[str_replace('Str', '', $key)];
				$value = $value ? wireDate($this->wire()->config->dateFormat, $value) : '';
				break;
			case 'render':
				$value = $this->wire()->modules->get('PageRender');	/** @var PageRender $value */
				$value->setPropertyPage($this);
				break;
			case 'loaderCache':
				$value = $this->loaderCache;
				break;
			case '_meta':		
				$value = $this->_meta; // null or WireDataDB
				break;
			case 'wakeupNameQueue': 	
				$value = &$this->wakeupNameQueue;
				break;
			case 'fieldDataQueue':	
				$value = &$this->fieldDataQueue;
				break;
			
			default:
				if($key && isset($this->settings[(string)$key])) return $this->settings[$key];
				if($key === 'meta' && !$this->wire()->fields->get('meta')) return $this->meta(); // always WireDataDB
			
				$ulpos = strpos($key, '_');
				
				if($ulpos === 0 && substr($key, -1) === '_' && !$this->wire()->fields->get($key)) {
					if($this->wire()->sanitizer->fieldName($key) === $key) {
						return $this->renderField(substr($key, 1, -1));
					}
				}
				
				$k = $ulpos ? str_replace('_', '', $key) : $key;
			
				if(!ctype_alnum("$k")) {
					// key has formatting beyond just a field/property name
					
					if(strpos($key, '{') !== false && strpos($key, '}')) {
						// populate a formatted string with {tag} vars
						return $this->getMarkup($key);
					}

					if(strpos($key, '|') !== false) {
						$value = $this->values()->getFieldFirstValue($this, $key);
						if($value !== null) return $value; 
					}

					if(strpos($key, '[')) { 
						return $this->values()->getBracketValue($this, $key);
					}

					$value = $this->values()->getFieldValue($this, $key); 
					if($value !== null) return $value;

					if(Selectors::stringHasOperator($key)) {
						// if there is a selector, assume they are using the get() method to get a child
						return $this->child($key);
					}

					// check if it's a field.subfield property
					if(strpos($key, '.')) {
						return $this->values()->getDotValue($this, $key);
					}
					
					if($ulpos !== false && strpos($key, '_OR_')) {
						// convert '_OR_' to '|'
						$value = $this->values()->getFieldFirstValue($this, str_replace('_OR_', '|', $key));
						if($value !== null) return $value;
					}
					
				} else {
					$value = $this->values()->getFieldValue($this, $key);
					if($value !== null) return $value;
				}

				// optionally let a hook look at it
				if($this->wire()->hooks->isHooked('Page::getUnknown()')) {
					$value = $this->getUnknown($key);
				}
		}

		return $value; 
	}

	/**
	 * Get multiple Page property/field values in an array
	 * 
	 * This method works exactly the same as the `get()` method except that it accepts an
	 * array (or CSV string) of properties/fields to get, and likewise returns an array 
	 * of those property/field values. By default it returns a regular (non-indexed) PHP
	 * array in the same order given. To instead get an associative array indexed by the
	 * property/field names given, specify `true` for the `$assoc` argument.
	 * 
	 * ~~~~~
	 * // returns regular array i.e. [ 'foo val', 'bar val' ]
	 * $a = $page->getMultiple([ 'foo', 'bar' ]);
	 * list($foo, $bar) = $a;
	 * 
	 * // returns associative array i.e. [ 'foo' => 'foo val', 'bar' => 'bar val' ]
	 * $a = $page->getMultiple([ 'foo', 'bar' ], true);
	 * $foo = $a['foo'];
	 * $bar = $a['bar'];
	 * 
	 * // CSV string can also be used instead of array
	 * $a = $page->getMultiple('foo,bar');
	 * ~~~~~
	 * 
	 * @param array|string $keys Array or CSV string of properties to get. 
	 * @param bool $assoc Get associative array indexed by given properties? (default=false)
	 * @return array
	 * @since 3.0.201
	 * 
	 */
	public function getMultiple($keys, $assoc = false) {
		return $this->values()->getMultiple($this, $keys, $assoc); 
	}

	/**
	 * Get a Field object in context or NULL if not valid for this page
	 * 
	 * Field in context is only returned when output formatting is on.
	 * 
	 * #pw-advanced
	 * 
	 * @param string|int|Field $field
	 * @return Field|null
	 * @todo determine if we can always retrieve in context regardless of output formatting.
	 * 
	 */
	public function getField($field) {
		return $this->values()->getField($this, $field);
	}

	/**
	 * Returns a FieldsArray of all Field objects in the context of this Page
	 * 
	 * Unlike $page->fieldgroup (or its alias $page->fields), the fields returned from
	 * this method are in the context of this page/template. Meaning returned Field 
	 * objects may have some properties that are different from the Field outside of 
	 * the context of this page. 
	 * 
	 * #pw-advanced
	 * 
	 * @return FieldsArray of Field objects
	 * 
	 */
	public function getFields() {
		return $this->values()->getFields($this);
	}

	/**
	 * Returns whether or not given $field name, ID or object is valid for this Page
	 * 
	 * Note that this only indicates validity, not whether the field is populated.
	 * 
	 * #pw-advanced
	 * 
	 * @param int|string|Field|array $field Field name, object or ID to check.
	 *  - In 3.0.126+ this may also be an array or pipe "|" separated string of field names to check.
	 * @return bool|string True if valid, false if not. 
	 *  - In 3.0.126+ returns first matching field name if given an array of field names or pipe separated string of field names.
	 * 
	 */
	public function hasField($field) {
		return $this->values()->hasField($this, $field);
	}

	/**
	 * If given a field.subfield string, returns the associated value
	 * 
	 * This is like the getDot() method, but with additional protection during output formatting. 
	 * 
	 * @param $key
	 * @return mixed|null
	 * @deprecated Method no longer needed
	 * 
	 */
	protected function getFieldSubfieldValue($key) {
		return $this->values()->getDotValue($this, $key);
	}

	/**
	 * Preload multiple fields together as a group (experimental)
	 * 
	 * This is an optimization that enables you to load the values for multiple fields into
	 * a page at once, and often in a single query. For fields where it is supported, and
	 * for cases where you have a lot of fields to load at once, it can be up to 50% faster 
	 * than the default of lazy-loading fields. 
	 * 
	 * To use, call `$page->preload([ 'field1', 'field2', 'etc.' ])` before accessing 
	 * `$page->field1`, `$page->field2`, etc.
	 *
	 * The more fields you give this method, the more performance improvement it can offer.
	 * As a result, don't bother if with only a few fields, as it's less likely to make 
	 * a difference at small scale. You will also see a more measurable benefit if preloading
	 * fields for lots of pages at once. 
	 * 
	 * Preload works with some Fieldtypes and not others. For details on what it is doing,
	 * specify `true` for the `debug` option which will make it return array of what it
	 * loaded and what it didn't. Have a look at this array with TracyDebugger or output
	 * a print_r() call on it, and the result is self explanatory. 
	 * 
	 * NOTE: This function is currently experimental, recommended for testing only. 
	 * 
	 * ~~~~~
	 * // Example usage
	 * $page->preload([ 'headline', 'body', 'sidebar', 'intro', 'summary' ]);
	 * echo "
	 *   <h1 id='headline'>$page->headline</h1>"; 
	 *   <div id='intro'>$page->intro</div>
	 *   <div id='body'>$page->body</div>
	 *   <aside id='sidebar' pw-append>$page->sidebar</aside>
	 *   <meta id='meta-description'>$page->summary</meta>
	 * ";
	 * ~~~~~
	 *
	 * @param array $fieldNames Names of fields to preload or omit (or blank array) 
	 *   to preload all supported fields. 
	 * @param array $options Options to modify default behavior:
	 * - `debug` (bool): Specify true to return additional info in returned array (default=false). 
	 * - See the `PagesLoader::preloadFields()` method for additional options.
	 * @return array Array of details 
	 * @since 3.0.243
	 *
	 */
	public function preload(array $fieldNames = array(), $options = array()) {
		if(empty($fieldNames)) {
			return $this->wire()->pages->loader()->preloadAllFields($this, $options);
		} else {
			return $this->wire()->pages->loader()->preloadFields($this, $fieldNames, $options);
		}
	}

	/**
	 * Hookable method called when a request to a field was made that didn't match anything
	 *
	 * Hooks that want to inject something here should hook after and modify the $event->return.
	 * 
	 * #pw-hooker
	 *
	 * @param string $key Name of property.
	 * @return null|mixed Returns null if property not known, or a value if it is.
	 *
	 */
	public function ___getUnknown($key) {
		return null;
	}

	/**
	 * For WireData::getDot() behavior 
	 *
	 * Typically these resolve to objects, and the subfield is pulled from the object.
	 * 
	 * #pw-internal
	 *
	 * @param string $key Property name in field.subfield format
	 * @return null|mixed Returns null if not found or invalid. Returns property value on success.
	 * @deprecated Use the get() method with your dotted key instead. 
	 *
	 */
	public function getDot($key) {
		if(strpos($key, '.') === false) return $this->get($key);
		$of = $this->outputFormatting();
		if($of) $this->setOutputFormatting(false);
		$value = self::_getDot($key, $this);
		if($of) $this->setOutputFormatting(true);
		return $value; 
	}

	/**
	 * Given a Multi Key, determine if there are multiple keys requested and return the first non-empty value
	 *
	 * A Multi Key is a string with multiple field names split by pipes, i.e. headline|title
	 *
	 * Example: browser_title|headline|title - Return the value of the first field that is non-empty
	 * 
	 * @param string $multiKey
	 * @param bool $getKey Specify true to get the first matching key (name) rather than value
	 * @return null|mixed Returns null if no values match, or if there aren't multiple keys split by "|" chars
	 * @deprecated Use $page->values()->getFieldFirstValue() instead
	 *
	 */
	protected function getFieldFirstValue($multiKey, $getKey = false) {
		return $this->values()->getFieldFirstValue($this, $multiKey, $getKey); 
	}

	/**
	 * Get the value for a non-native page field, and call upon Fieldtype to join it if not autojoined
	 * 
	 * @param string $key Name of field to get
	 * @param string $selector Optional selector to filter load by...
	 *   ...or, if not in selector format, it becomes an __invoke() argument for object values .
	 * @return null|mixed
	 *
	 */
	protected function getFieldValue($key, $selector = '') {
		return $this->values()->getFieldValue($this, $key, $selector); 
	}

	/**
	 * Return a value consistent with the page’s output formatting state
	 * 
	 * This is primarily for use as a helper to the getFieldValue() method. 
	 * 
	 * @param Field $field
	 * @param mixed $value
	 * @return mixed
	 * 
	 */
	protected function formatFieldValue(Field $field, $value) {
		return $this->values()->formatFieldValue($this, $field, $value);
	}

	/**
	 * If value is available for $key return or call $yes condition (with optional $no condition)
	 * 
	 * This merges the capabilities of an if() statement, get() and getMarkup() methods in one,
	 * plus some useful PW type-specific logic, providing a useful output shortcut. It many situations
	 * it enables you to accomplish on one-line of code what might have otherwise taken multiple lines 
	 * of code. Use this when looking for a useful shortcut and this one fits your need, otherwise 
	 * use a regular PHP if() statement.
	 * 
	 * This function is primarily intended for conditionally outputting some formatted string value or 
	 * markup, however its use is not limited to that, as you can specify whatever you’d like for the 
	 * $yes and $no conditions. The examples section best describes potential usages of this method, 
	 * so I recommend looking at those before reading all the details of this method. 
	 * 
	 * Note that the logic is a little bit smarter for PW than a regular PHP if() statement in these ways:
	 *
	 * - If value resolves to any kind of *empty* `WireArray` (like a `PageArray`) the NO condition is used.
	 *   If the WireArray is populated with at least one item then the YES condition is used. So this if()
	 *   method (unlike PHP if) requires that not only is the value present, but it is also populated. 
	 * 
	 * - If value resolves to a `NullPage` the NO condition is used. 
	 * 
	 * The `$key` argument may be any of the following: 
	 * 
	 * - A field name, in which case we will use the value of that field on this page. If the value is
	 *   empty the NO condition will be used, otherwise the YES condition will be used. You can use any
	 *   format for the field name that the `Page::get()` method accepts, so subfields and OR field 
	 *   statements are also okay, i.e. `categories.count`, `field1|field2|field3', etc. 
	 *   
	 * - A selector string that must match this page in order to return the YES condition. If it does not
	 *   match then the NO condition will be used. 
	 * 
	 * - A boolean, integer, digit string or PHP array. If considered empty by PHP it will return the NO
	 *   condition, otherwise it will return the YES condition. 
	 * 
	 * The `$yes` and `$no` arguments (the conditional actions) may be any of the following:
	 * 
	 * - Any string value that you’d like (HTML markup is fine too). 
	 * 
	 * - A field name that is present on this page, or optionally the word “value” to refer to the field 
	 *   specified in the `$key` argument. Either way, makes this method return the actual field value as it 
	 *   exists on the page, rather than a string/markup version of it. Note that if this word (“value”) is 
	 *   used for the argument then of course the `$key` argument must be a field name (not a selector string).
	 * 
	 * - Any callable inline function that returns the value you want this function to return. 
	 * 
	 * - A string containing one or more `{field}` placeholders, where you replace “field” with a field name.
	 *   These are in turn populated by the `Page::getMarkup()` method. You can also use `{field.subfield}`
	 *   and `{field1|field2|field3}` type placeholder strings. 
	 * 
	 * - A string containing `{val}` or `{value}` where they will be replaced with the markup value of the
	 *   field name given in the $key argument. 
	 * 
	 * - If you omit the `$no` argument an empty string is assumed. 
	 * 
	 * - If you omit both the `$yes` and `$no` arguments, then boolean is assumed (true for yes, false for no),
	 *   which makes this method likewise return a boolean. The only real reason to do this would be to take 
	 *   advantage of the method’s slightly different behavior than regular PHP if() statements (i.e. treating 
	 *   empty WireArray or NullPage objects as false conditions). 
	 * 
	 * ~~~~~
	 * // if summary is populated, output it in an paragraph
	 * echo $page->if("summary", "<p class='summary'>{summary}</p>");
	 * 
	 * // same as above, but shows you can specify {value} to assume field in $key arg
	 * echo $page->if("summary", "<p class='summary'>{value}</p>");
	 * 
	 * // if price is populated, format for output, otherwise ask them to call for price 
	 * echo $page->if("price", function($val) { return '$' . number_format($val); }, "Please call"); 
	 * 
	 * // you can also use selector strings
	 * echo $page->if("inventory>10", "In stock", "Limited availability"); 
	 * 
	 * // output an <img> tag for the first image on the page, or blank if none
	 * echo $page->if("images", function($val) { return "<img src='{$val->first->url}'>"; });
	 * ~~~~~
	 * 
	 * @param string|bool|int $key Name of field to check, selector string to evaluate, or boolean/int to evalute
	 * @param string|callable|mixed $yes If value for $key is present, return or call this
	 * @param string|callable|mixed $no If value for $key is empty, return or call this
	 * @return mixed|string|bool
	 * @since 3.0.126
	 * 
	 */
	public function ___if($key, $yes = '', $no = '') {
		return $this->comparison()->_if($this, $key, $yes, $no); 
	}
	
	/**
	 * Return the markup value for a given field name or {tag} string
	 *
	 * 1. If given a field name (or `name.subname` or `name1|name2|name3`) it will return the
	 *    markup value as defined by the fieldtype.
	 * 2. If given a string with field names referenced in `{tags}`, it will populate those
	 *    tags and return the populated string.
	 * 
	 * #pw-advanced
	 *
	 * @param string $key Field name or markup string with field {name} tags in it
	 * @return string
	 * @see Page::getText()
	 *
	 */
	public function ___getMarkup($key) {
		return $this->values()->getMarkup($this, $key);
	}
	
	/**
	 * Same as getMarkup() except returned value is plain text
	 * 
	 * If no `$entities` argument is provided, returned value is entity encoded when output formatting 
	 * is on, and not entity encoded when output formatting is off.
	 * 
	 * #pw-advanced
	 * 
	 * @param string $key Field name or string with field {name} tags in it.
	 * @param bool $oneLine Specify true if returned value must be on single line.
	 * @param bool|null $entities True to entity encode, false to not. Null for auto, which follows page's outputFormatting state.
	 * @return string
	 * @see Page::getMarkup()
	 * 
	 */
	public function getText($key, $oneLine = false, $entities = null) {
		return $this->values()->getText($this, $key, $oneLine, $entities); 
	}

	/**
	 * Set the unformatted value of a field, regardless of current output formatting state
	 * 
	 * Use this when setting an unformatted value to a page that has (or might have) output formatting enabled. 
	 * This will save you the steps of checking the output formatting state, turning it off, setting the value,
	 * and turning it back on again (if it was on). Note that the output formatting distinction matters for some
	 * field types and not others, just depending on the case—this method is safe to use either way.
	 * 
	 * Make sure you do not use this to set an already formatted value to a Page (like some text that has been 
	 * entity encoded). This method skips over some of the checks that might otherwise flag the page as corrupted. 
	 * 
	 * ~~~~~
	 * // good usage
	 * $page->setUnformatted('title', 'This & That'); 
	 * 
	 * // bad usage
	 * $page->setUnformatted('title', 'This &amp; That'); 
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 * @since 3.0.169
	 * @throws WireException if given an object value that indicates it is already formatted. 
	 * @see Page::getUnformatted(), Page::of(), Page::setOutputFormatting(), Page::outputFormatting()
	 * 
	 */
	public function setUnformatted($key, $value) {
		if($value instanceof PageFieldValueInterface && $value->formatted()) {
			throw new WireException("Cannot use formatted-value with Page::setUnformatted($key, formatted-value);");
		}
		$of = $this->outputFormatting;
		if($of) $this->of(false);
		$this->set($key, $value);
		if($of) $this->of(true);
		return $this;
	}

	/**
	 * Get the unformatted value of a field, regardless of current output formatting state
	 * 
	 * When a page’s output formatting state is off, `$page->get('property')` or `$page->property` will
	 * produce the same result as this method call. 
	 * 
	 * ~~~~~
	 * // Get the 'body' field without any text formatters applied
	 * $body = $page->getUnformatted('body');
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param string $key Field or property name to retrieve
	 * @return mixed
	 * @see Page::getFormatted(), Page::of(), Page::setOutputFormatting(), Page::outputFormatting()
	 *
	 */
	public function getUnformatted($key) {
		$of = $this->outputFormatting; 
		if($of) $this->of(false); 
		$value = $this->get($key); 
		if($of) $this->of(true); 
		return $value; 
	}

	/**
	 * Get the formatted value of a field, regardless of output formatting state
	 * 
	 * When a page's output formatting state is on, `$page->get('property')` or `$page->property` will
	 * produce the same result as this method call. 
	 * 
	 * ~~~~~
	 * // Get the formatted 'body' field (text formatters applied)
	 * $body = $page->getFormatted('body');
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @param string $key Field or property name to retrieve
	 * @return mixed
	 * @see Page::getUnformatted(), Page::of()
	 *
	 */
	public function getFormatted($key) {
		$of = $this->outputFormatting;
		if(!$of) $this->of(true);
		$value = $this->get($key);
		if(!$of) $this->of(false);
		return $value;
	}

	/**
	 * Direct access get method
	 * 
	 * @param string $key
	 * @return mixed
	 * @see get()
	 *
	 */
	public function __get($key) {
		return $this->get($key); 
	}

	/**
	 * Direct access set method
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @see set()
	 *
	 */
	public function __set($key, $value) {
		$this->set($key, $value); 
	}

	/**
	 * If method call resulted in no handler, this hookable method is called.
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
	 * @see Wire::callUnknown()
	 *
	 */
	protected function ___callUnknown($method, $arguments) {
		if($this->hasField($method)) {
			if(count($arguments)) {
				return $this->values()->getFieldValue($this, $method, $arguments[0]);
			} else {
				return $this->get($method);
			}
		} else if(isset(PageProperties::$baseMethodAlternates[$method])) { 
			return call_user_func_array(array($this, PageProperties::$baseMethodAlternates[$method]), $arguments);
		} else {
			return parent::___callUnknown($method, $arguments);
		}
	}
	
	/**
	 * Set the page name, optionally for specific language
	 * 
	 * ~~~~~
	 * // Set page name (default language)
	 * $page->setName('my-page-name');
	 * 
	 * // This is equivalent to the above
	 * $page->name = 'my-page-name'; 
	 * 
	 * // Set page name for Spanish language
	 * $page->setName('la-cerveza', 'es'); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $value Page name that you want to set
	 * @param Language|string|int|null $language Set language for name (can also be language name or string in format "name1234")
	 * @return $this
	 *
	 */
	public function setName($value, $language = null) {
		
		if(!$this->isLoaded && empty($language) && is_string($value) && strpos($value, 'xn-') !== 0) {
			$this->settings['name'] = $value;
		} else {
			$this->values()->setName($this, $value, $language);
		}

		return $this;
	}

	/**
	 * Set this Page's Template 
	 * 
	 * ~~~~~
	 * // The following 3 lines are equivalent
	 * $page->setTemplate('basic-page');
	 * $page->template = 'basic-page';
	 * $page->templates = $templates->get('basic-page'); 
	 * ~~~~~
	 * 
	 * #pw-internal
	 * 
	 * @param Template|int|string $tpl May be Template object, name or ID.
	 * @return $this
	 * @throws WireException if given invalid arguments or template not allowed for page
	 *
	 */
	protected function setTemplate($tpl) {
		if(!is_object($tpl)) $tpl = $this->wire()->templates->get($tpl); 
		if(!$tpl instanceof Template) throw new WireException("Invalid value sent to Page::setTemplate"); 
		if($this->template && $this->template->id != $tpl->id && $this->isLoaded) {
			if($this->settings['status'] & Page::statusSystem) {
				throw new WireException("Template changes are disallowed on page $this->id because it has system status");
			}
			if(is_null($this->templatePrevious)) $this->templatePrevious = $this->template; 
			$this->trackChange('template', $this->template, $tpl); 
		}
		if($tpl->sortfield) $this->settings['sortfield'] = $tpl->sortfield; 
		$this->template = $tpl; 
		return $this;
	}

	/**
	 * Get or set template
	 * 
	 * @param null|Template|string|int $template
	 * @return Template|null
	 * @since 3.0.181
	 * 
	 */
	public function template($template = null) {
		if($template !== null) $this->setTemplate($template);
		return $this->template;
	}

	/**
	 * Set this page's parent Page
	 * 
	 * #pw-internal
	 * 
	 * @param Page $parent
	 * @return $this
	 * @throws WireException if given impossible $parent or parent changes aren't allowed
	 *
	 */
	public function setParent(Page $parent) {
		if($this->_parent && $this->_parent->id == $parent->id) return $this; 
		if($parent->id && $this->id == $parent->id || $parent->parents->has($this)) {
			throw new WireException("Page cannot be its own parent");
		}
		if($this->isLoaded && $this->id) {
			if(!$this->_parent) $this->parent(); // force it to load
			$this->trackChange('parent', $this->_parent, $parent);
			if(($this->_parent && $this->_parent->id) && $this->_parent->id != $parent->id) {
				if($this->settings['status'] & Page::statusSystem) {
					throw new WireException("Parent changes are disallowed on page $this->id because it has system status");
				}
				if(is_null($this->parentPrevious)) $this->parentPrevious = $this->_parent;
			}
		}
		$this->_parent = $parent; 
		$this->_parent_id = $parent->id;
		return $this; 
	}

	/**
	 * Set either the createdUser or the modifiedUser 
	 * 
	 * @param User|int|string $user User object or integer/string representation of User
	 * @param string $userType Must be either 'created' or 'modified' 
	 * @return $this
	 * @throws WireException
	 *
	 */
	protected function setUser($user, $userType) {

		if(!$user instanceof User) {
			if(is_object($user)) {
				$user = null;
			} else {
				$user = $this->wire()->users->get($user);
			}
		}

		// if they are setting an invalid user or unknown user, then the Page defaults to the super user
		if(!$user instanceof User || !$user->id) {
			$user = $this->wire()->users->get($this->wire()->config->superUserPageID);
		}

		if(strpos($userType, 'created') === 0) {
			$key = 'created_users_id';
			$this->_createdUser = $user; 
		} else if(strpos($userType, 'modified') === 0) {
			$key = 'modified_users_id';
			$this->_modifiedUser = $user;
		} else {
			throw new WireException("Unknown user type in Page::setUser(user, type)"); 
		}

		$existingUserID = $this->settings[$key]; 
		if($existingUserID != $user->id) $this->trackChange($key, $existingUserID, $user->id); 
		$this->settings[$key] = $user->id; 
		
		return $this; 	
	}

	/**
	 * Get page’s created or modified user
	 * 
	 * @param string $userType One of 'created' or 'modified'
	 * @return User|NullPage
	 * 
	 */
	protected function getUser($userType) {
		
		if(strpos($userType, 'created') === 0) {
			$userType = 'created';
		} else if(strpos($userType, 'modified') === 0) {
			$userType = 'modified';
		} else {
			return $this->wire(new NullPage());
		}
		
		$property = '_' . $userType . 'User';
		$user = $this->$property;
		
		// if we already have the user, return it now
		if($user) return $user;
		
		$key = $userType . '_users_id';
		$uid = (int) $this->settings[$key];
		if(!$uid) return $this->wire(new NullPage());
	
		$u = $this->wire()->user;
		if($u && $uid === $u->id) {
			// ok use current user $user
			$user = $u;
		} else {
			// get user
			$users = $this->wire()->users;
			if($users) $user = $users->get($uid);
		}
		
		if(!$user) $user = $this->wire(new NullPage());
		
		$this->$property = $user; // cache to _createdUser or _modifiedUser
		
		return $user;
	}

	/**
	 * Find descendant pages matching given selector 
	 *
	 * This is the same as `Pages::find()` except that the results are limited to descendents of this Page.
	 * 
	 * ~~~~~
	 * // Find all unpublished pages underneath the current page
	 * $items = $page->find("status=unpublished"); 
	 * ~~~~~
	 *
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string|array $selector Selector string or array
	 * @param array $options Same as the $options array passed to $pages->find(). 
	 * @return PageArray
	 * @see Pages::find()
	 *
	 */
	public function find($selector = '', $options = array()) {
		if(!$this->numChildren) {
			return $this->wire()->pages->newPageArray();
		}
		if(is_string($selector)) {
			$selector = trim("has_parent={$this->id}, $selector", ", ");
		} else if(is_array($selector)) {
			$selector["has_parent"] = $this->id;	
		}
		return $this->_pages('find', $selector, $options); 
	}
	
	/**
	 * Find one descendant page matching given selector
	 *
	 * This is the same as `Pages::findOne()` except that the match is always a descendant of page it is called on.
	 *
	 * ~~~~~
	 * // Find the most recently modified descendant page
	 * $item = $page->findOne("sort=-modified");
	 * ~~~~~
	 *
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string|array $selector Selector string or array
	 * @param array $options Optional options to modify default bheavior, see options for `Pages::find()`.
	 * @return Page|NullPage Returns Page when found, or NullPage when nothing found. 
	 * @see Pages::findOne(), Page::child()
	 * @since 3.0.116
	 *
	 */
	public function findOne($selector = '', $options = array()) {
		if(!$this->numChildren) {
			return $this->wire()->pages->newNullPage();
		}
		if(is_string($selector)) {
			$selector = trim("has_parent={$this->id}, $selector", ", ");
		} else if(is_array($selector)) {
			$selector["has_parent"] = $this->id;
		}
		return $this->_pages('findOne', $selector, $options);
	}

	/**
	 * Return this page’s children, optionally filtered by a selector
	 * 
	 * By default, hidden, unpublished and no-access pages are excluded unless `include=x` (where "x" is desired status) is specified. 
	 * If a selector isn't needed, children can also be accessed directly by property with `$page->children`.
	 * 
	 * ~~~~~
	 * // Render navigation for all child pages below this one
	 * foreach($page->children() as $child) {
	 *   echo "<li><a href='$child->url'>$child->title</a></li>";
	 * }
	 * ~~~~~         
	 * ~~~~~
	 * // Retrieve just the 3 newest children
	 * $newest = $page->children("limit=3, sort=-created");
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string $selector Selector to use, or omit to return all children.
	 * @param array $options Optional options to modify behavior, the same as those provided to Pages::find.
	 * @return PageArray|array Returns PageArray for most cases. Returns regular PHP array if using the findIDs option.
	 * @see Page::child(), Page::find(), Page::numChildren(), Page::hasChildren()
	 *
	 */
	public function children($selector = '', $options = array()) {
		return $this->traversal()->children($this, $selector, $options); 
	}

	/**
	 * Return number of all children, optionally with conditions
	 *
	 * Use this over the `$page->numChildren` property when you want to specify a selector, or when you want the result to
	 * include only visible children. See the options for the $selector argument. 
	 * 
	 * When you want to retrieve all children with no exclusions or conditions, use the `$page->numChildren` property instead. 
	 * 
	 * ~~~~~
	 * // Find how many children were modified in the last week
	 * $qty = $page->numChildren("modified>='-1 WEEK'");
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param bool|string|array $selector 
	 * - When not specified, result includes all children without conditions, same as $page->numChildren property.
	 * - When a string or array, a selector is assumed and quantity will be counted based on selector.
	 * - When boolean true, number includes only visible children (excludes unpublished, hidden, no-access, etc.)
	 * - When boolean false, number includes all children without conditions, including unpublished, hidden, no-access, etc.
	 * - When integer 1 number includes “viewable” children (as opposed to “visible” children, viewable children includes 
	 *   hidden pages and also includes unpublished pages if user has page-edit permission).
	 * @return int Number of children
	 * @see Page::hasChildren(), Page::children(), Page::child()
	 *
	 */
	public function numChildren($selector = null) {
		if(!$this->settings['numChildren'] && $selector === null) return 0;
		return $this->traversal()->numChildren($this, $selector); 
	}

	/**
	 * Return the number of visible children, optionally with conditions
	 * 
	 * This method is similar to `$page->numChildren()` except that the default behavior is to exclude non-visible children.
	 * 
	 * This method may be more convenient for front-end navigation use than the `$page->numChildren()` method because
	 * it only includes the count of visible children. By visible, we mean children that are not hidden, unpublished,
	 * or non-accessible due to access control. 
	 * 
	 * ~~~~~
	 * // Determine if we should show navigation to children
	 * if($page->hasChildren()) {
	 *   // Yes, we should show navigation to children
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 * 
	 * @param bool|string|array $selector
	 * - When not specified, result is quantity of visible children (excludes unpublished, hidden, no-access, etc.)
	 * - When a string or array, a selector is assumed and quantity will be counted based on selector.
	 * - When boolean true, number includes only visible children (this is the default behavior, so no need to specify this value).
	 * - When boolean false, number includes all children without conditions, including unpublished, hidden, no-access, etc.
	 * @return int Number of children
	 * 
	 */
	public function hasChildren($selector = true) {
		return $this->numChildren($selector);
	}

	/**
	 * Return the page’s first single child that matches the given selector. 
	 *
	 * Same as `$page->children()` but returns a single Page object or NullPage (with id=0) rather than a PageArray.
	 * Meaning, this method will only ever return one Page. 
	 * 
	 * ~~~~~
	 * // Get the newest created child page
	 * $newestChild = $page->child("sort=-created"); 
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string|array|int $selector Selector to use, or blank to return the first child. 
	 * @param array $options Optional options per Pages::find
	 * @return Page|NullPage
	 * @see Page::children()
	 *
	 */
	public function child($selector = '', $options = array()) {
		return $this->traversal()->child($this, $selector, $options); 
	}

	/**
	 * Return this page’s parent Page, or–if given a selector–the closest matching parent.
	 * 
	 * Omit all arguments if you just want to retrieve the parent of this page, which would be the same as the 
	 * `$page->parent` property. To retrieve the closest parent matching your selector, specify either a selector
	 * string or array. 
	 * 
	 * ~~~~~
	 * // Retrieve the parent
	 * $parent = $page->parent();
	 * 
	 * // Retrieve the closest parent using template "products"
	 * $parent = $page->parent("template=products"); 
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 * 
	 * @param string|array $selector Optional selector. When used, it returns the closest parent matching the selector. 
	 * @return Page Returns a Page or a NullPage when there is no parent or the selector string did not match any parents.
	 *
	 */
	public function parent($selector = '') {
		if(!$this->_parent) {
			if($this->_parent_id) {
				$this->_parent = $this->_pages('get', (int) $this->_parent_id);
			} else {
				return $this->wire()->pages->newNullPage();
			}
		}
		if(empty($selector)) return $this->_parent; 
		if($this->_parent->matches($selector)) return $this->_parent; 
		if($this->_parent->parent_id) return $this->_parent->parent($selector); // recursive, in a way
		return $this->wire()->pages->newNullPage();
	}

	/**
	 * Return this page’s parent pages, or the parent pages matching the given selector.
	 * 
	 * This method returns all parents of this page, in order. If a selector is specified, they
	 * will be filtered by the selector. By default, parents are returned in breadcrumb order. 
	 * In 3.0.158+ if you specify boolean true for selector argument, then it will return parents 
	 * in reverse order (closest to furthest).
	 * 
	 * ~~~~~
	 * // Render breadcrumbs 
	 * foreach($page->parents() as $parent) {
	 *   echo "<li><a href='$parent->url'>$parent->title</a></li>";
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // Return all parents, excluding the homepage
	 * $parents = $page->parents("template!=home"); 
	 * ~~~~~
	 * ~~~~~
	 * // Return parents in reverse order (closest to furthest, 3.0.158+)
	 * $parents = $page->parents(true); 
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string|array|bool $selector Optional selector string to filter parents by or boolean true for reverse order
	 * @return PageArray All parent pages, or those matching the given selector. 
	 *
	 */
	public function parents($selector = '') {
		return $this->traversal()->parents($this, $selector); 
	}

	/**
	 * Return number of parents (depth relative to homepage) that this page has, optionally filtered by a selector
	 *
	 * For example, homepage has 0 parents and root level pages have 1 parent (which is the homepage), and the
	 * number increases the deeper the page is in the pages structure.
	 *
	 * @param string $selector Optional selector to filter by (default='')
	 * @return int Number of parents
	 *
	 */
	public function numParents($selector = '') {
		return $this->traversal()->numParents($this, $selector);
	}

	/**
	 * Return all parents from current page till the one matched by $selector
	 * 
	 * This duplicates the jQuery parentsUntil() function in ProcessWire. 
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|Page|array $selector May either be a selector sor Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @return PageArray
	 *
	 */
	public function parentsUntil($selector = '', $filter = '') {
		return $this->traversal()->parentsUntil($this, $selector, $filter); 
	}

	/**
	 * Find the closest parent page matching your selector
	 * 
	 * This is like `$page->parent()` but includes the current Page in the possible pages that can be matched,
	 * and the $selector argument is required. 
	 * 
	 * #pw-group-traversal
	 * 
	 * @param string|array $selector Selector string to match. 
	 * @return Page|NullPage $selector Returns the current Page or closest parent matching the selector. Returns NullPage when no match.
	 *
	 */
	public function closest($selector) {
		if(empty($selector) || $this->matches($selector)) return $this; 
		return $this->parent($selector); 
	}

	/**
	 * Get the lowest-level, non-homepage parent of this page
	 *
	 * The rootParents typically comprise the first level of navigation on a site, and in many cases are considered
	 * the "section" pages of the site. 
	 * 
	 * ~~~~~
	 * // Determine if we are in the "products" section of the site
	 * if($page->rootParent()->template == 'products') {
	 *   // we are in the products section
	 * } else {
	 *   // we are in some other section of the site
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @return Page 
	 *
	 */
	public function ___rootParent() {
		return $this->traversal()->rootParent($this); 
	}

	/**
	 * Return this Page’s sibling pages, optionally filtered by a selector. 
	 *
	 * To exclude the current page in list of siblings, specify boolean false for first or second argument. 
	 * 
	 * ~~~~~
	 * // Get all sibling pages 
	 * $siblings = $page->siblings();
	 * 
	 * // Get all sibling pages, and exclude current page from the returned value
	 * $siblings = $page->siblings(false); 
	 * 
	 * // Get all siblings having the "product-featured" template, sorted by name
	 * $featured = $page->siblings("template=product-featured, sort=name");
	 * 
	 * // Same as above, while excluding current page
	 * $featured = $page->siblings("template=product-featured, sort=name", false);
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|array|bool $selector Optional selector to filter siblings by, or omit for all siblings. 
	 * @param bool $includeCurrent Specify false to exclude current page in the returned siblings (default=true). 
	 *   If no $selector argument is given, this argument may optionally be specified as the first argument. 
	 * @return PageArray
	 *
	 */
	public function siblings($selector = '', $includeCurrent = true) {
		if(is_bool($selector)) {
			$includeCurrent = $selector; 
			$selector = '';
		}
		if(!$includeCurrent) {
			if(is_array($selector)) {
				$selector[] = array('id', '!=', $this->id);
			} else {
				if(strlen($selector)) $selector .= ", ";	
				$selector .= "id!=$this->id";
			}
		}
		return $this->traversal()->siblings($this, $selector); 
	}
	
	/**
	 * Return number of descendants (children, grandchildren, great-grandchildren, …), optionally with conditions
	 *
	 * Use this over the `$page->numDescendants` property when you want to specify a selector or apply
	 * some other filter to the result (see options for `$selector` argument). If you want to include only
	 * visible descendants specify a selector (string or array) or boolean true for the `$selector` argument,
	 * if you don’t need a selector. 
	 *
	 * If you want to find descendant pages (rather than count), use the `Page::find()` method.
	 *
	 * ~~~~~
	 * // Find how many descendants were modified in the last week
	 * $qty = $page->numDescendants("modified>='-1 WEEK'");
	 * ~~~~~
	 *
	 * #pw-group-traversal
	 *
	 * @param bool|string|array $selector
	 * - When not specified, result includes all descendants without conditions, same as $page->numDescendants property.
	 * - When a string or array, a selector is assumed and quantity will be counted based on selector.
	 * - When boolean true, number includes only visible descendants (excludes unpublished, hidden, no-access, etc.)
	 * @return int Number of descendants
	 * @see Page::numChildren(), Page::find()
	 *
	 */
	public function numDescendants($selector = null) {
		return $this->traversal()->numDescendants($this, $selector);
	}

	/**
	 * Return the next sibling page
	 * 
	 * By default, hidden, unpublished and non-viewable pages are excluded. If you want them included, 
	 * be sure to specify `include=` with hidden, unpublished or all, in your selector.
	 * 
	 * ~~~~~
	 * // Get the next sibling
	 * $sibling = $page->next();
	 * 
	 * // Get the next newest sibling
	 * $sibling = $page->next("created>$page->created"); 
	 * 
	 * // Get the next sibling, even if it isn't viewable
	 * $sibling = $page->next("include=all");
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|array $selector Optional selector. When specified, will find nearest next sibling that matches. 
	 * @param PageArray $siblings Optional siblings to use instead of the default. Avoid using this argument
	 *   as it forces this method to use the older/slower functions. 
	 * @return Page|NullPage Returns the next sibling page, or a NullPage if none found. 
	 *
	 */
	public function next($selector = '', ?PageArray $siblings = null) {
		if($selector instanceof PageArray) {
			$siblings = $selector;
			$selector = '';
		}
		if($siblings === null && $this->traversalPages) $siblings = $this->traversalPages;
		if($siblings) return $this->traversal()->nextSibling($this, $selector, $siblings);
		return $this->traversal()->next($this, $selector);
	}

	/**
	 * Return all sibling pages after this one, optionally matching a selector
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|array|bool $selector Optional selector. When specified, will filter the found siblings.
	 * @param bool|PageArray $getQty Return a count instead of PageArray? (boolean)
	 *   - If no $selector argument is needed, this may be specified as the first argument.
	 *   - Legacy support: You may specify a PageArray of siblings to use instead of the default (deprecated, avoid it).
	 * @param bool $getPrev For internal use, makes this method implement the prevAll() behavior instead.
	 * @return PageArray|int Returns all matching pages after this one, or integer if $count option specified.
	 *
	 */
	public function nextAll($selector = '', $getQty = false, $getPrev = false) {
		$siblings = null;
		if($selector instanceof PageArray) {
			$siblings = $selector;
			$selector = '';
		}
		if($getQty instanceof PageArray) {
			$siblings = $getQty;
			$getQty = false;
		}
		if(is_bool($selector)) {
			$getQty = $selector;
			$selector = '';
		}
		if($siblings === null && $this->traversalPages) $siblings = $this->traversalPages;
		if($getPrev) {
			if($siblings) return $this->traversal()->prevAllSiblings($this, $selector, $siblings);
			return $this->traversal()->prevAll($this, $selector, array('qty' => $getQty));
		}
		if($siblings) return $this->traversal()->nextAllSiblings($this, $selector, $siblings);
		return $this->traversal()->nextAll($this, $selector, array('qty' => $getQty));
	}

	/**
	 * Return all sibling pages after this one until matching the one specified 
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @param PageArray $siblings Optional PageArray of siblings to use instead (avoid).
	 * @return PageArray
	 *
	 */
	public function nextUntil($selector = '', $filter = '', ?PageArray $siblings = null) {
		if($siblings === null && $this->traversalPages) $siblings = $this->traversalPages;
		if($siblings) return $this->traversal()->nextUntilSiblings($this, $selector, $filter, $siblings); 
		return $this->traversal()->nextUntil($this, $selector, $filter); 
	}

	/**
	 * Return the previous sibling page
	 * 
	 * ~~~~~
	 * // Get the previous sibling
	 * $sibling = $page->prev();
	 * 
	 * // Get the previous sibling having field "featured" with value of "1"
	 * $sibling = $page->prev("featured=1"); 
	 * ~~~~~
	 *
	 * #pw-group-traversal
	 *
	 * @param string|array $selector Optional selector. When specified, will find nearest previous sibling that matches. 
	 * @param PageArray|null $siblings Optional siblings to use instead of the default.
	 * @return Page|NullPage Returns the previous sibling page, or a NullPage if none found. 
	 *
	 */
	public function prev($selector = '', ?PageArray $siblings = null) {
		if($selector instanceof PageArray) {
			$siblings = $selector;
			$selector = '';
		}
		if($siblings === null && $this->traversalPages) $siblings = $this->traversalPages;
		if($siblings) return $this->traversal()->prevSibling($this, $selector, $siblings);
		return $this->traversal()->prev($this, $selector);
	}

	/**
	 * Return all sibling pages before this one, optionally matching a selector
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|array|bool $selector Optional selector. When specified, will filter the found siblings.
	 * @param bool|PageArray $getQty Return a count instead of PageArray? (boolean)
	 *   - If no $selector argument is needed, this may be specified as the first argument.
	 *   - Legacy support: You may specify a PageArray of siblings to use instead of the default (deprecated, avoid it).
	 * @return Page|NullPage|int Returns all matching pages before this one, or integer if $getQty requested.
	 *
	 */
	public function prevAll($selector = '', $getQty = false) {
		/** @var Page|NullPage|int $value */
		$value = $this->nextAll($selector, $getQty, true);
		return $value;
	}

	/**
	 * Return all sibling pages before this one until matching the one specified 
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @param PageArray|null $siblings Optional PageArray of siblings to use instead of default. 
	 * @return PageArray
	 *
	 */
	public function prevUntil($selector = '', $filter = '', ?PageArray $siblings = null) {
		if($siblings === null && $this->traversalPages) $siblings = $this->traversalPages;
		if($siblings) return $this->traversal()->prevUntilSiblings($this, $selector, $filter, $siblings);
		return $this->traversal()->prevUntil($this, $selector, $filter); 
	}
	
	/**
	 * Return pages that have Page reference fields pointing to this one (references)
	 * 
	 * By default this excludes pages that are hidden, unpublished and pages excluded due to access control for the current user. 
	 * To prevent these exclusions specify an include mode in the selector, i.e. `include=all`, or you can use
	 * boolean `true` as a shortcut to specify that you do not want any exclusions. 
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|bool $selector Optional selector to filter results by, or boolean true as shortcut for `include=all`.
	 * @param Field|string|bool $field Optionally limit to pages using specified field (name or Field object),
	 *  - OR specify boolean TRUE to return array of PageArrays indexed by field names.
	 *  - If $field argument not specified, it searches all applicable Page fields. 
	 * @return PageArray|array
	 * @since 3.0.107
	 *
	 */
	public function ___references($selector = '', $field = '') {
		return $this->traversal()->references($this, $selector, $field); 
	}

	/**
	 * Return pages linking to this one (in Textarea/HTML fields)
	 * 
	 * Applies only to Textarea fields with “html” content-type and link abstraction enabled. 
	 * 
	 * #pw-group-traversal
	 * 
	 * @param string|bool $selector Optional selector to filter by or boolean true for “include=all”. (default='')
	 * @param string|Field $field Optionally limit results to specified field. (default=all applicable Textarea fields)
	 * @return PageArray
	 * @since 3.0.107
	 * 
	 */
	public function ___links($selector = '', $field = '') {
		return $this->traversal()->links($this, $selector, $field); 
	}

	/**
	 * Get languages active for this page and viewable by current user
	 * 
	 * #pw-group-languages
	 * 
	 * @return PageArray|null Returns PageArray of languages, or null if language support is not active.
	 * 
	 */
	public function getLanguages() {
		$template = $this->template();
		if(!$template) return null;
		$templateLanguages = $template->getLanguages();
		if(!$templateLanguages) return null;
		$languages = $this->wire()->pages->newPageArray();
		foreach($templateLanguages as $language) {
			if($this->viewable($language, false)) $languages->add($language);
		}
		return $languages;
	}

	/**
	 * Save the entire page to the database, or just a field from it
	 * 
	 * This is the same as calling `$pages->save($page);` or `$pages->saveField($page, $field)`, but calling directly
	 * on the $page like this may be more convenient in many instances.
	 * 
	 * If you want to hook into the save operation, hook into one of the many Pages class hooks referenced in the 'See Also' section.
	 * 
	 * ~~~~~
	 * // Save the page
	 * $page->save();
	 * 
	 * // Save just the 'title' field from the page
	 * $page->save('title');
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-manipulation
	 *
	 * @param Field|string $field Optional field to save (name of field or Field object)
	 * @param array $options See Pages::save() documentation for options. You may also specify $options as the first argument if no $field is needed.
	 * @return bool Returns true on success false on fail
	 * @throws WireException on database error
	 * @see Pages::save(), Page::saveFields(), Pages::saveField(), Pages::saveReady(), Pages::saveFieldReady(), Pages::saved(), Pages::fieldSaved()
	 *
	 */
	public function save($field = null, array $options = array()) {
		
		$pages = $this->wire()->pages;
		
		if(is_array($field) && empty($options)) {
			$options = $field;
			$field = null;
		}
		
		if(empty($field)) {
			return $pages->save($this, $options);
		}
		
		if($this->hasField($field)) {
			// save field
			return $pages->saveField($this, $field, $options);
		}

		// save only native properties
		$options['noFields'] = true; 
		
		return $pages->save($this, $options);
	}

	/**
	 * Save only the given named fields for this page
	 * 
	 * @param array|string $fields Array of field name(s) or string (CSV or space separated)
	 * @param array $options See Pages::save() documentation for options.
	 * @return array Names of fields that were saved
	 * @throws WireException on database error
	 * @see Page::save()
	 * @since 3.0.242
	 * 
	 */
	public function saveFields($fields, array $options = array()) {
		return $this->wire()->pages->saveFields($this, $fields, $options);
	}
	
	/**
	 * Quickly set field value(s) and save to database 
	 * 
	 * You can specify a single field and value, or an array of fields and values. 
	 *
	 * This method does not need output formatting to be turned off first, so make sure that whatever
	 * value(s) you set are not formatted values.
	 * 
	 * ~~~~~
	 * // Set and save the summary field
	 * $page->setAndSave('summary', 'When nothing is done, nothing is left undone.');
	 * ~~~~~
	 * ~~~~~
	 * // Set and save multiple fields
	 * $page->setAndSave([
	 *   'title' => 'It is Friday again',
	 *   'subtitle' => 'Here is another new blog post',
	 *   'body' => 'Hope you all have a great weekend!'
	 * ]);
	 * ~~~~~
	 * ~~~~~
	 * // Update a 'last_login' field after every user login
	 * $session->addHookAfter('loginSuccess', function($event) {
	 *   $user = $event->arguments(0);
	 *   $user->setAndSave('last_login', time());
	 * });
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * #pw-links [Blog post about setAndSave](https://processwire.com/blog/posts/processwire-2.6.9-core-updates-and-new-procache-version/)
	 *
	 * @param array|string $key Field or property name to set, or array of one or more ['property' => $value].
	 * @param string|int|bool|object $value Value to set, or omit if you provided an array in first argument.
	 * @param array $options See Pages::save() for additional $options that may be specified. 
	 * @return bool Returns true on success, false on failure
	 * @see Pages::save()
	 *
	 */
	public function setAndSave($key, $value = null, array $options = array()) {
		if(is_array($key)) {
			$values = $key;
			$property = count($values) == 1 ? key($values) : '';
		} else {
			$property = $key;
			$values = array($key => $value);
		}
		$of = $this->of();
		if($of) $this->of(false);
		foreach($values as $k => $v) {
			$this->set($k, $v);
			if(!$property) $this->trackChange($k);
		}
		if($property) {
			$result = $this->save($property, $options);
		} else {
			$result = $this->save($options);
		}
		if($of) $this->of(true);
		return $result;
	}

	/**
	 * Delete this page from the database
	 * 
	 * This is the same as calling `$pages->delete($page)`.
	 *
	 * ~~~~~
	 * // Delete pages named "delete-me" that don't have children
	 * $items = $pages->find("name=delete-me, numChildren=0");
	 * foreach($items as $item) {
	 *   $item->delete();
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // Delete a page and recursively all of its children, grandchildren, etc. 
	 * $item = $pages->get('/some-page/'); 
	 * $item->delete(true);
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param bool $recursive If set to true, then this will attempt to delete all children too.
	 * @return bool|int True on success, false on failure, or int quantity of pages deleted when recursive option is true.
	 * @throws WireException when attempting to delete a page with children and $recursive option is not specified.
	 * @see Pages::delete()
	 *
	 */
	public function delete($recursive = false) {
		/** @var bool|int $value */
		$value = $this->wire()->pages->delete($this, $recursive); 
		return $value;
	}

	/**
	 * Move this page to the trash
	 * 
	 * This is the same as calling `$pages->trash($page)`.
	 * 
	 * ~~~~~
	 * // Trash a page
	 * $item = $pages->get('/some-page/');
	 * $item->trash();
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function trash() {
		return $this->wire()->pages->trash($this); 
	}
	
	/**
	 * Returns number of children page has, affected by output formatting mode.
	 * 
	 * - When output formatting is on, returns only number of visible children,
	 *   making the return value the same as the `Page::hasChildren()` method. 
	 * 
	 * - When output formatting is off, returns number of all children without exclusion,
	 *   making the return value the same as the `Page::numChildren()` method. 
	 * 
	 * ~~~~~
	 * // Get number of visible children, like $page->hasChildren()
	 * $page->of(true); // enable output formatting
	 * $numVisible = $page->count();
	 * 
	 * // Get number of all children, like $page->numChildren()
	 * $page->of(false); // disable output formatting
	 * $numTotal = $page->count();
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @return int Quantity of children
	 * @see Page::hasChildren(), Page::numChildren()
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function count() {
		if($this->outputFormatting) return $this->numChildren(true);
		return $this->numChildren(false);
	}

	/**
	 * Enables iteration of the page's properties and fields with PHP’s foreach()
	 * 
	 * This fulfills PHP's IteratorAggregate interface, enabling you to interate all of the page's properties and fields. 
	 * 
	 * ~~~~~
	 * // List all properties and fields from the page
	 * foreach($page as $name => $value) {
	 *   echo "<h3>$name</h3>";
	 *   echo "<p>$value</p>"; 
	 * }
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @return \ArrayObject
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		$a = $this->settings; 
		$template = $this->template();
		if($template && $template->fieldgroup) {
			foreach($template->fieldgroup as $field) {
				/** @var Field $field */
				$a[$field->name] = $this->get($field->name); 
			}
		}
		return new \ArrayObject($a); 	
	}

	/**
	 * Has the Page changed since it was loaded?
	 *
	 * To check if only a specific property on the page has changed, specify the property/field name as the first argument. 
	 * This method assumes that change tracking is enabled for the Page (as it is by default). 
	 * Pages that are new (i.e. don't yet exist in the database) always return true. 
	 * 
	 * #pw-group-manipulation
	 * 
	 * ~~~~~
	 * // Check if page has any changes
	 * if($page->isChanged()) {
	 *   // There are changes to this page
	 *   $changes = $page->getChanges();
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // When page is about to be saved, update summary when body has changed
	 * $this->addHookBefore('Pages::saveReady', function($event) {
	 *   $page = $event->arguments('page'); 
	 *   if($page->isChanged('body')) {
	 *     // get first 300 chars from body
	 *     $summary = substr($page->body, 0, 300);
	 *     // truncate to position of last period
	 *     $period = strrpos($summary, '.'); 
	 *     if($period) $summary = substr($summary, 0, $period);
	 *     // populate to the page, so that summary is also saved
	 *     $page->summary = $summary;
	 *   }
	 * });
	 * ~~~~~
	 * 
	 * @param string $what If specified, only checks the given property for changes rather than the whole page. 
	 * @return bool 
	 * @see Wire::setTrackChanges(), Wire::getChanges(), Wire::trackChange()
	 *
	 */
	public function isChanged($what = '') {
		if($this->isNew()) return true; 
		if(parent::isChanged($what)) return true; 
		$changed = false;
		if($what) {
			$data = array_key_exists($what, $this->data) ? array($this->data[$what]) : array();
		} else {
			$data = &$this->data;
		}
		foreach($data as $value) {
			if($value instanceof Wire) $changed = $value->isChanged();
			if($changed) break;
		}
		return $changed; 	
	}

	/**
	 * Clears out any tracked changes and turns change tracking ON or OFF
	 * 
	 * Use this method when you want to clear a list of tracked changes on the page. Note that any changes are still
	 * present, but ProcessWire no longer knows they had been changed. Meaning, the changes won't be available to 
	 * the `$page->isChanged()` and `$page->getChanges()` methods, and the changes might be skipped over if/when 
	 * the page is saved. 
	 * 
	 * #pw-group-manipulation
	 *
	 * @param bool $trackChanges True to turn change tracking ON, or false to turn OFF. Default of true is assumed. 
	 * @return $this
	 * @see Page::isChanged(), Page::getChanges(), Page::trackChanges()
	 *
	 */
	public function resetTrackChanges($trackChanges = true) {
		parent::resetTrackChanges($trackChanges);
		foreach($this->data as $value) {
			if($value instanceof Wire && !$value instanceof Page) {
				$value->resetTrackChanges($trackChanges);
			}
		}
		return $this; 
	}

	/**
	 * Returns the Page’s path from the ProcessWire installation root. 
	 * 
	 * The path is always indicated from the ProcessWire installation root. Meaning, if the installation is 
	 * running from a subdirectory, then the path does not include that subdirectory, whereas the url does. 
	 * Note that path and url are identical if installation is not running from a subdirectory. 
	 * 
	 * #pw-hookable
	 * #pw-group-common
	 * #pw-group-urls
	 * 
	 * ~~~~~
	 * // Difference between path and url on site running from subdirectory /my-site/
	 * echo $page->path(); // outputs: /about/contact/
	 * echo $page->url();  // outputs: /my-site/about/contact/
	 * ~~~~~
	 * 
	 * @return string Returns the page path, for example: `/about/contact/`
	 * @see Page::url(), Page::httpUrl()
	 *
	 */
	public function path() {
		return $this->wire()->hooks->isHooked('Page::path()') ? $this->__call('path', array()) : $this->___path();
	}

	/**
	 * Provides the hookable implementation for the path() method.
	 *
	 * The method we're using here by having a real path() function above is slightly quicker than just letting 
	 * PW's hook handler handle it all. We're taking this approach since path() is a function that can feasibly
	 * be called hundreds or thousands of times in a request, so we want it as optimized as possible.
	 * 
	 * #pw-internal
	 *
	 */
	protected function ___path() {
		if($this->id === 1) return '/';
		$path = '';
		$parents = $this->parents();
		foreach($parents as $parent) if($parent->id > 1) $path .= "/{$parent->name}";
		return $path . '/' . $this->name . '/'; 
	}

	/**
	 * Returns the URL to the page (optionally with additional $options)
	 *
	 * - This method can also be accessed by property `$page->url` (without parenthesis). 
	 * 
	 * - Like `$page->path()` but comes from server document root. Path and url are identical if 
	 *   installation is not running from a subdirectory. 
	 * 
	 * - Use `$page->httpUrl()` if you need the URL to include scheme and hostname. 
	 * 
	 * - **Need to hook this method?** While it's not directly hookable, it does use the `$page->path()`
	 *   method, which *is* hookable. As a result, you can affect the output of the url() method by
	 *   hooking the path() method instead. 
	 * 
	 * ## $options argument
	 * 
	 * You can specify an `$options` argument to this method with any of the following:
	 *
	 * - `pageNum` (int|string|bool): Specify pagination number, "+" for next pagination, "-" for previous pagination, 
	 *    or boolean true (3.0.155+) for current.
	 * - `urlSegmentStr` (string|bool): Specify a URL segment string to append, or true (3.0.155+) for current.
	 * - `urlSegments` (array|bool): Specify array of URL segments to append (may be used instead of urlSegmentStr), 
	 *    or boolean true (3.0.155+) for current. Specify associative array to use keys and values in order (3.0.155+). 
	 * - `data` (array): Array of key=value variables to form a query string.
	 * - `http` (bool): Specify true to make URL include scheme and hostname (default=false).
	 * - `language` (Language): Specify Language object to return URL in that Language.
	 * - `host` (string): Force hostname to use, i.e. 'world.com' or 'hello.world.com'. The 'http' option is implied. (3.0.178+)
	 * - `scheme` (string): Like http option, makes URL have scheme+hostname, but you specify scheme here, i.e. 'https' (3.0.178+)
	 *    Note that if you specify scheme of 'https' and $config->noHTTPS is true, the 'http' scheme will still be used.
	 * 
	 * You can also specify any of the following for `$options` as shortcuts:
	 * 
	 * - If you specify an `int` for options it is assumed to be the `pageNum` option.
	 * - If you specify `+` or `-` for options it is assumed to be the `pageNum` “next/previous pagination” option.
	 * - If you specify any other `string` for options it is assumed to be the `urlSegmentStr` option.
	 * - If you specify a `boolean` (true) for options it is assumed to be the `http` option. 
	 * 
	 * Please also note regarding `$options`:
	 * 
	 * - This method honors template slash settings for page, URL segments and page numbers. 
	 * - Any passed in URL segments are automatically sanitized with `Sanitizer::pageNameUTF8()`.
	 * - If using the `pageNum` or URL segment options please also make sure these are enabled on the page’s template.
	 * - The query string generated by any `data` variables is entity encoded when output formatting is on. 
	 * - The `language` option requires that the `LanguageSupportPageNames` module is installed. 
	 * - The prefix for page numbers honors `$config->pageNumUrlPrefix` and multi-language prefixes as well. 
	 * 
	 * ~~~~~
	 * // Using $page->url to output navigation
	 * foreach($page->children as $child) {
	 *   echo "<li><a href='$child->url'>$child->title</a></li>";
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // Difference between url() and path() on site running from subdirectory /my-site/
	 * echo $page->url();  // outputs: /my-site/about/contact/
	 * echo $page->path(); // outputs: /about/contact/
	 * ~~~~~
	 * ~~~~~
	 * // Specify that you want a specific pagination (output: /example/page2)
	 * echo $page->url(2);
	 * 
	 * // Get URL for next and previous pagination
	 * echo $page->url('+'); // next
	 * echo $page->url('-'); // prev
	 * 
	 * // Get a URL with scheme and hostname (output: http://domain.com/example/)
	 * echo $page->url(true);
	 * 
	 * // Specify a URL segment string (output: /example/photos/1)
	 * echo $page->url('photos/1');
	 *
	 * // Use a URL segment array (output: /example/photos/1)
	 * echo $page->url([
	 *   'urlSegments' => [ 'photos', '1' ]
	 * ]);
	 * 
	 * // Get URL in a specific language
	 * $fr = $languages->get('fr');
	 * echo $page->url($fr); 
	 *
	 * // Include data/query vars (output: /example/?action=view&type=photos)
	 * echo $page->url([
	 *   'data' => [
	 *     'action' => 'view',
	 *     'type' => 'photos'
	 *   ]
	 * ]);
	 * 
	 * // Specify multiple options (output: http://domain.com/example/foo/page3?bar=baz)
	 * echo $page->url([
	 *   'http' => true,
	 *   'pageNum' => 3,
	 *   'urlSegmentStr' => 'foo',
	 *   'data' => [ 'bar' => 'baz' ]
	 * ]);
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-urls
	 * 
	 * @param array|int|string|bool|Language|null $options Optionally specify options to modify default behavior (see method description). 
	 * @return string Returns page URL, for example: `/my-site/about/contact/`
	 * @see Page::path(), Page::httpUrl(), Page::editUrl(), Page::localUrl()
	 *
	 */
	public function url($options = null) {
		if($options !== null) return $this->traversal()->urlOptions($this, $options);
		$url = rtrim($this->wire()->config->urls->root, '/') . $this->path();
		$template = $this->template();
		if($template && $template->slashUrls === 0 && $this->settings['id'] > 1) $url = rtrim($url, '/'); 
		return $url;
	}

	/**
	 * Return all URLs that this page can be accessed from (excluding URL segments and pagination)
	 * 
	 * This includes the current page URL, any other language URLs (for which page is active), and 
	 * any past (historical) URLs the page was previously available at (which will redirect to it). 
	 * 
	 * - Returned URLs do not include additional URL segments or pagination numbers.
	 * - Returned URLs are indexed by language name, i.e. “default”, “fr”, “es”, etc. 
	 * - If multi-language URLs not installed, then index is just “default”. 
	 * - Past URLs are indexed by language; then ISO-8601 date, i.e. “default;2016-08-11T07:44:43-04:00”,
	 *   where the date represents the last date that URL was considered current. 
	 * - If PagePathHistory core module is not installed then past/historical URLs are excluded. 
	 * - You can disable past/historical or multi-language URLs by using the $options argument. 
	 * 
	 * #pw-group-urls
	 * 
	 * @param array $options Options to modify default behavior:
	 *  - `http` (bool): Make URLs include current scheme and hostname (default=false). 
	 *  - `past` (bool): Include past/historical URLs? (default=true)
	 *  - `languages` (bool): Include other language URLs when supported/available? (default=true).
	 *  - `language` (Language|int|string): Include only URLs for this language (default=null).
	 *     Note: the `languages` option must be true if using the `language` option.
	 * @return array
	 * @since 3.0.107
	 * @see Page::addUrl(), page::removeUrl()
	 * 
	 */
	public function urls($options = array()) {
		return $this->traversal()->urls($this, $options);	
	}

	/**
	 * Returns the URL to the page, including scheme and hostname
	 * 
	 * - This method is just like the `$page->url()` method except that it also includes scheme and hostname.
	 * 
	 * - This method can also be accessed at the property `$page->httpUrl` (without parenthesis). 
	 * 
	 * - It is desirable to use this method when some page templates require https while others don't.  
	 *   This ensures local links will always point to pages with the proper scheme. For other cases, it may
	 *   be preferable to use `$page->url()` since it produces shorter output. 
	 * 
	 * ~~~~~
	 * // Generating a link to this page using httpUrl
	 * echo "<a href='$page->httpUrl'>$page->title</a>"; 
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-urls
	 *
	 * @param array $options For details on usage see `Page::url()` options argument. 
	 * @return string Returns full URL to page, for example: `https://processwire.com/about/`
	 * @see Page::url(), Page::localHttpUrl()
	 *
	 */
	public function httpUrl($options = array()) {
		return $this->traversal()->httpUrl($this, $options);
	}

	/**
	 * Return the URL necessary to edit this page 
	 * 
	 * - We recommend checking that the page is editable before outputting the editUrl(). 
	 * - If user opens URL in their browser and is not logged in, they must login to account with edit permission.
	 * - This method can also be accessed by property at `$page->editUrl` (without parenthesis). 
	 * 
	 * ~~~~~~
	 * if($page->editable()) {
	 *   echo "<a href='$page->editUrl'>Edit this page</a>";
	 * }
	 * ~~~~~~
	 * 
	 * #pw-group-urls
	 * 
	 * @param array|bool|string $options Specify true for http option, specify name of field to find (3.0.151+), or use $options array:
	 *  - `http` (bool): True to force scheme and hostname in URL (default=auto detect).
	 *  - `language` (Language|bool): Optionally specify Language to start editor in, or boolean true to force current user language.
	 *  - `find` (string): Name of field to find in the editor (3.0.151+)
	 *  - `vars` (array): Additional variables to include in query string (3.0.239+)
	 * @return string URL for editing this page
	 * 
	 */
	public function editUrl($options = array()) {
		return $this->traversal()->editUrl($this, $options);
	}

	/**
	 * Return the field name by which children are sorted
	 * 
	 * - If sort is descending, then field name is prepended with a "-".
	 * - Returns the value "sort" if pages are unsorted or sorted manually. 
	 * - Note the return value from this method may be different from the `Page::sortfield` (lowercase) property,
	 *   as this method considers the sort field specified with the template as well. 
	 * 
	 * #pw-group-system
	 * 
	 * @return string
	 * 
	 */
	public function sortfield() {
		$template = $this->template();
		$sortfield = $template ? $template->sortfield : '';
		if(!$sortfield) $sortfield = $this->settings['sortfield'];
		if(!$sortfield) $sortfield = 'sort';
		return $sortfield;
	}

	/**
	 * Return the index/position of this page relative to siblings.
	 * 
	 * If given a hidden or unpublished page, that page would not usually be part of the group of siblings.
	 * As a result, such pages will return what the value would be if they were visible (as of 3.0.121). This
	 * may overlap with the index of other pages, since indexes are relative to visible pages, unless you
	 * specify an include mode (see next paragraph). 
	 *
	 * If you want this method to include hidden/unpublished pages as part of the index numbers, then
	 * specify boolean true for the $selector argument (which implies "include=all") OR specify a
	 * selector of "include=hidden", "include=unpublished" or "include=all".
	 * 
	 * ~~~~~
	 * $i = $page->index();
	 * $n = $page->parent->numChildren();
	 * echo "This page is $i out of $n total pages";
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * 
	 * @param bool|string|array Specify one of the following (since 3.0.121): 
	 *  - Boolean true to include hidden and unpublished pages as part of the index numbers (same as "include=all"). 
	 *  - An "include=hidden", "include=unpublished" or "include=all" selector to include them in the index numbers. 
	 *  - A string selector or selector array to filter the criteria for the returned index number. 
	 * @return int Returns index number (zero-based)
	 * @since 3.0.24
	 * 
	 */
	public function index($selector = '') {
		return $this->traversal()->index($this, $selector);
	}

	/**
	 * Get the output TemplateFile object for rendering this page (internal use only)
	 *
	 * You can retrieve the results of this by calling $page->out or $page->output
	 * 
	 * #pw-internal
	 *
	 * @param bool $forceNew Forces it to return a new (non-cached) TemplateFile object (default=false)
	 * @return TemplateFile
	 *
	 */
	public function output($forceNew = false) {
		if($this->output && !$forceNew) return $this->output; 
		$this->output = $this->values()->output($this, $forceNew);
		return $this->output; 
	}

	/**
	 * Render given $fieldName using site/templates/fields/ markup file 
	 * 
	 * Shorter aliases of this method include:
	 * 
	 * - `$page->render('fieldName', $file);`
	 * - `$page->render->fieldName;`
	 * - `$page->_fieldName_;`
	 * 
	 * This method expects that there is a file in `/site/templates/fields/` to render the field with
	 * one of the following:
	 * 
	 * - `/site/templates/fields/fieldName.php`
	 * - `/site/templates/fields/fieldName.templateName.php`
	 * - `/site/templates/fields/fieldName/$file.php`
	 * - `/site/templates/fields/$file.php`
	 * - `/site/templates/fields/$file/fieldName.php`
	 * - `/site/templates/fields/$file.fieldName.php`
	 * 
	 * Note that the examples above showing $file require that the `$file` argument is specified
	 * in the `renderField()` method call. 
	 * 
	 * ~~~~~
	 * // Render output for the 'images' field (assumes you have implemented an output file)
	 * echo $page->renderField('images');
	 * ~~~~~
	 * 
	 * #pw-group-output-rendering
	 * 
	 * @param string $fieldName May be any custom field name or native page property.
	 * @param string $file Optionally specify file (in site/templates/fields/) to render with (may optionally omit .php extension).
	 * @param mixed|null $value Optionally specify value to render, otherwise it will be pulled from this page. 
	 * @return mixed|string Returns the rendered value of the field
	 * @see Page::render(), Page::renderValue()
	 * 
	 */
	public function ___renderField($fieldName, $file = '', $value = null) {
		/** @var PageRender $pageRender */
		$pageRender = $this->wire()->modules->get('PageRender');
		return $pageRender->renderField($this, $fieldName, $file, $value);
	}

	/**
	 * Render given $value using /site/templates/fields/ markup file
	 * 
	 * See the documentation for the `Page::renderField()` method for information about the `$file` argument. 
	 * 
	 * ~~~~~
	 * // Render a value using site/templates/fields/my-images.php custom output template
	 * $images = $page->images;
	 * echo $page->renderValue($images, 'my-images'); 
	 * ~~~~~
	 * 
	 * #pw-group-output-rendering
	 *
	 * @param mixed $value Value to render
	 * @param string $file Optionally specify file (in site/templates/fields/) to render with (may omit .php extension)
	 * @return mixed|string Returns rendered value
	 *
	 */
	public function ___renderValue($value, $file = '') {
		return $this->___renderField('', $file, $value);
	}

	/**
	 * Return all Inputfield objects necessary to edit this page
	 * 
	 * This method returns an InputfieldWrapper object that contains all the custom Inputfield objects 
	 * required to edit this page. You may also specify a `$fieldName` argument to limit what is contained
	 * in the returned InputfieldWrapper. 
	 * 
	 * Please note this method deals only with custom fields, not system fields name 'name' or 'status', etc., 
	 * as those are exclusive to the ProcessPageEdit page editor. 
	 * 
	 * #pw-advanced
	 * 
	 * @param string|array $fieldName Optional field to limit to, typically the name of a fieldset or tab.
	 *  - Or optionally specify array of $options (See `Fieldgroup::getPageInputfields()` for options). 
	 * @return null|InputfieldWrapper Returns an InputfieldWrapper array of Inputfield objects, or NULL on failure. 
	 *
	 */
	public function getInputfields($fieldName = '') {
		if($this->wire()->hooks->isMethodHooked($this, 'getInputfields')) {
			return $this->__call('getInputfields', array($fieldName));
		} else {
			return $this->___getInputfields($fieldName);
		}
	}

	/**
	 * Hookable version of getInputfields() method. 
	 * 
	 * See the getInputfields() method above for documentation details. 
	 * 
	 * @param string|array $fieldName
	 * @return null|InputfieldWrapper Returns an InputfieldWrapper array of Inputfield objects, or NULL on failure.
	 * 
	 */
	protected function ___getInputfields($fieldName = '') {
		return $this->values()->getInputfields($this, $fieldName);
	}

	/**
	 * Get a single Inputfield for the given field name
	 * 
	 * - If requested field name refers to a single field, an Inputfield object is returned. 
	 * - If requested field name refers to a fieldset or tab, then an InputfieldWrapper representing will be returned.
	 * - Returned Inputfield already has values populated to it.
	 * - Please note this method deals only with custom fields, not system fields name 'name' or 'status', etc., 
	 *   as those are exclusive to the ProcessPageEdit page editor. 
	 * 
	 * #pw-advanced
	 * 
	 * @param string $fieldName
	 * @return Inputfield|InputfieldWrapper|null Returns Inputfield, or null if given field name doesn't match field for this page.
	 * 
	 */
	public function getInputfield($fieldName) {
		return $this->values()->getInputfield($this, $fieldName);
	}
	
	/**
	 * Get front-end editable output for field (requires PageFrontEdit module to be installed)
	 * 
	 * This method requires the core `PageFrontEdit` module to be installed. If it is not installed then
	 * it returns expected output but it is not front-end editable. This method corresponds to front-end 
	 * editing Option B. See the [front-end editor docs](https://processwire.com/docs/front-end/) for more details. 
	 * If the user does not have permission to front-end edit then returned output will not be editable.
	 * 
	 * Use `$page->edit('field_name');` instead of `$page->get('field_name');` to automatically return an editable
	 * field value when the user is allowed to edit, or a regular field value when not. When field is
	 * editable, hovering the value shows a different icon. **The user must double-click the area to edit.**
	 * 
	 * The 2nd and 3rd arguments are typically used only if you need to override the default presentation of 
	 * the editor or provide some kind of action or button to trigger the editor. It might also be useful if
	 * the content to edit is not visible by default. It is recommended that you specify boolean true for the
	 * `$modal` argument when using the `$markup` argument, which makes it open the editor in a modal window, 
	 * less likely to interfere with your front-end layout. 
	 *
	 * ~~~~~
	 * // retrieve editable value if field_name is editable, or just value if not
	 * $value = $page->edit('field_name'); 
	 * ~~~~~
	 * 
	 * #pw-group-output-rendering
	 * #pw-hooker
	 *
	 * @param string|bool|null $key Name of field, omit to get editor active status, or boolean true to enable editor. 
	 * @param string|bool|null $markup Markup user should click on to edit $fieldName (typically omitted). 
	 * @param bool|null $modal Specify true to force editable region to open a modal window (typically omitted).
	 * @return string|bool|mixed
	 * @see https://processwire.com/docs/front-end/
	 * @since 3.0.0 This method is added by a hook in PageFrontEdit and only shown in this class for documentation purposes.
	 *
	 */
	public function ___edit($key = null, $markup = null, $modal = null) {
		if($modal) {} // ignore
		if($key === null || is_bool($key)) return false;
		if(is_string($markup)) return $markup;
		if($markup === false) return $this->getFormatted($key);
		return $this->get($key);
	}
	
	/**
	 * Set the status setting, with some built-in protections
	 *
	 * This method is also used when you set status directly, i.e. `$page->status = $value;`.
	 *
	 * ~~~~~
	 * // set status to unpublished
	 * $page->setStatus('unpublished');
	 *
	 * // set status to hidden and unpublished
	 * $page->setStatus('hidden, unpublished');
	 *
	 * // set status to hidden + unpublished using Page constant bitmask
	 * $page->setStatus(Page::statusHidden | Page::statusUnpublished);
	 * ~~~~~
	 *
	 * #pw-advanced
	 * #pw-group-manipulation
	 *
	 * @param int|array|string Status value, array of status names or values, or status name string.
	 * @return self
	 * @see Page::addStatus(), Page::removeStatus()
	 *
	 */
	protected function setStatus($value) {
		if(!$this->isLoaded && ctype_digit("$value")) {
			$this->settings['status'] = (int) $value;
		} else {
			$this->values()->setStatus($this, $value);
		}
		return $this;
	}

	/**
	 * Does this page have the given status?
	 * 
	 * This method is the preferred way to check if a page has a particular status. 
	 * The status may be specified as one of the `Page::status` constants or a string representing
	 * one of the constants, i.e. `hidden`, `unpublished`, `locked`, and so on.
	 * 
	 * ~~~~~
	 * // check if page has hidden status using status name
	 * if($page->hasStatus('hidden')) { ... }
	 * 
	 * // check if page has hidden status using status constant
	 * if($page->hasStatus(Page::statusHidden)) { ... }
	 * 
	 * // There are also method shortcuts, i.e. 
	 * if($page->isHidden()) { ... }
	 * if($page->isUnpublished()) { ... }
	 * if($page->isLocked()) { ... }
	 * ~~~~~
	 * 
	 * #pw-group-status
	 * #pw-group-common
	 * 
	 * @param int|string $status Status flag constant or string representation (hidden, locked, unpublished, etc.)
	 * @return bool Returns true if page has the given status, or false if it doesn't. 
	 * @see Page::addStatus(), Page::removeStatus(), Page::isHidden(), Page::isUnpublished(), Page::isLocked()
	 * 
	 */
	public function hasStatus($status) {
		if(is_string($status) && isset(PageProperties::$statuses[$status])) {
			$status = PageProperties::$statuses[$status];
		}
		return (bool) ($this->status & $status);
	}

	/**
	 * Add the specified status to this page
	 * 
	 * This is the preferred way to add a new status to a page. There is also a corresponding `Page::removeStatus()` method. 
	 * 
	 * ~~~~~
	 * // Add hidden status to the page using status name
	 * $page->addStatus('hidden'); 
	 * 
	 * // Add hidden status to the page using status constant
	 * $page->addStatus(Page::statusHidden); 
	 * ~~~~~
	 * 
	 * #pw-group-status
	 * #pw-group-manipulation
	 *
	 * @param int|string $statusFlag Status flag constant or string representation (hidden, locked, unpublished, etc.)
	 * @return $this
	 * @see Page::removeStatus(), Page::hasStatus()
	 *
	 */
	public function addStatus($statusFlag) {
		if(is_string($statusFlag) && isset(PageProperties::$statuses[$statusFlag])) {
			$statusFlag = PageProperties::$statuses[$statusFlag];
		}
		$statusFlag = (int) $statusFlag; 
		return $this->setStatus($this->status | $statusFlag); 
	}

	/** 
	 * Remove the specified status from this page
	 *
	 * This is the preferred way to remove a status from a page. There is also a corresponding `Page::addStatus()` method. 
	 * 
	 * ~~~~~
	 * // Remove hidden status from the page using status name
	 * $page->removeStatus('hidden');
	 *  
	 * // Remove hidden status from the page using status constant
	 * $page->removeStatus(Page::statusHidden);
	 * ~~~~~
	 * 
	 * #pw-group-status
	 * #pw-group-manipulation
	 *
	 * @param int|string $statusFlag Status flag constant or string representation (hidden, locked, unpublished, etc.)
	 * @return $this
	 * @throws WireException If you attempt to remove `Page::statusSystem` or `Page::statusSystemID` statuses without first adding `Page::statusSystemOverride` status.
	 * @see Page::addStatus(), Page::hasStatus()
	 *
	 */
	public function removeStatus($statusFlag) {
		return $this->values()->removeStatus($this, $statusFlag);
	}
	
	/**
	 * Given a selector, return whether or not this Page matches using runtime/memory comparison
	 *
	 * ~~~~~
	 * if($page->matches("created>=" . strtotime("today"))) {
	 *   echo "This page was created today";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * 
	 * @param string|Selectors|array $s Selector to compare against (string, Selectors object, or array).
	 * @return bool Returns true if this page matches, or false if it doesn't. 
	 *
	 */
	public function matches($s) {
		// This method implements the WireMatchable interface
		return $this->comparison()->matches($this, $s);
	}
	
	/**
	 * Given a selector, return whether or not this Page matches by querying the database
	 *
	 * ~~~~~
	 * if($page->matchesDatabase("created>=today")) {
	 *   echo "This page was created today";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 *
	 * @param string|Selectors|array $s Selector to compare against (string, Selectors object, or array).
	 * @return bool Returns true if this page matches, or false if it doesn't.
	 * @since 3.0.225
	 *
	 */
	public function matchesDatabase($s) {
		return $this->comparison()->matches($this, $s, array('useDatabase' => true));
	}

	/**
	 * Does this page have the specified status number or template name?
	 *
	 * See status flag constants at top of Page class.
	 * You may also use status names: hidden, locked, unpublished, system, systemID
	 * 
	 * #pw-group-status
	 *
	 * @param int|string|Selectors $status Status number, status name, or Template name or selector string/object
	 * @return bool
	 *
	 */
	public function is($status) {
		if(is_string($status) && isset(PageProperties::$statuses[$status])) {
			$status = PageProperties::$statuses[$status];
		}
		return $this->comparison()->is($this, $status);
	}

	/**
	 * Does this page have a 'hidden' status?
	 * 
	 * #pw-group-status
	 *
	 * @return bool
	 *
	 */
	public function isHidden() {
		return $this->hasStatus(self::statusHidden); 
	}

	/**
	 * Does this page have a 'unpublished' status?
	 * 
	 * #pw-group-status
	 *
	 * @return bool
	 *
	 */
	public function isUnpublished() {
		return $this->hasStatus(self::statusUnpublished);
	}
	
	/**
	 * Does this page have a 'locked' status?
	 * 
	 * #pw-group-status
	 *
	 * @return bool
	 *
	 */
	public function isLocked() {
		return $this->hasStatus(self::statusLocked);
	}
	
	/**
	 * Is this Page new? (i.e. doesn't yet exist in DB)
	 * 
	 * #pw-internal
	 * 
	 * @return bool
	 *
	 */
	public function isNew() {
		return $this->isNew; 
	}

	/**
	 * Is the page fully loaded? (or optionally a field)
	 * 
	 * #pw-internal
	 * 
	 * @param string|null $fieldName Optionally request if a specified field is already loaded in the page
	 * @return bool
	 *
	 */
	public function isLoaded($fieldName = null) {
		if($fieldName) {
			if($this->hasField($fieldName)) return isset($this->data[$fieldName]); 
			return parent::get($fieldName) !== null;
		}
		return $this->isLoaded; 
	}

	/**
	 * Is this Page in the trash?
	 * 
	 * #pw-group-status
	 *
	 * @return bool
	 *
 	 */ 
	public function isTrash() {
		if($this->hasStatus(self::statusTrash)) return true;
		$trashPageID = (int) $this->wire()->config->trashPageID; 
		if($this->id === (int) $trashPageID) return true; 
		// this is so that isTrash() still returns the correct result, even if the page was just trashed and not yet saved
		foreach($this->parents() as $parent) if($parent->id === $trashPageID) return true; 
		return false;
	}

	/**
	 * Is this page public and viewable by all?
	 *
	 * This is a state that persists regardless of user, so has nothing to do with the current user.
	 * To be public, the page must be published and have guest view access.
	 * 
	 * #pw-advanced
	 * #pw-hookable
	 *
	 * @return bool True if public, false if not
	 *
	 */
	public function isPublic() {
		return $this->wire()->hooks->isHooked('Page::isPublic()') ? $this->__call('isPublic', array()) : $this->___isPublic();
	}

	/**
	 * Hookable implementation for the above isPublic function
	 * 
	 * @return bool
	 * 
	 */
	protected function ___isPublic() {
		if($this->status >= Page::statusUnpublished) return false;	
		$template = $this->getAccessTemplate();
		if(!$template || !$template->hasRole('guest')) return false;
		return true; 
	}

	/**
	 * Get or set current status
	 * 
	 * - When manipulating status, you may prefer to use the `$page->addStatus()` and `$page->removeStatus()` methods instead.
	 * 
	 * - Use this `status()` method when you want to set multiple statuses at once, or when you want to get status rather than set it.
	 * 
	 * - You can also get or set status directly, by manipulating the `$page->status` property. 
	 * 
	 * ~~~~~
	 * // Get the current status as bitmask
	 * $status = $page->status();
	 * 
	 * // Get an array of status names assigned to page
	 * $statuses = $page->status(true);
	 * 
	 * // Set status by Page constant bitmask
	 * $page->status(Page::statusHidden | Page::statusUnpublished); 
	 * 
	 * // Set status by name
	 * $page->status('unpublished');
	 * 
	 * // Set status by names
	 * $page->status(['hidden', 'unpublished']); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * #pw-group-status
	 * 
	 * @param bool|int $value Optionally specify one of the following:
	 *  - `true` (boolean): To return an array of status names (indexed by status number).
	 *  - `integer|string|array`: Status number(s) or status name(s) to set the current page status (same as $page->status = $value)
	 * @param int|null $status If you specified `true` for first argument, optionally specify status value you want to use (if not the current).
	 * @return int|array|Page If setting status, `$this` is returned. If getting status: current status or array of status names is returned.
	 * @see Page::addStatus(), Page::removeStatus(), Page::hasStatus()
	 * 
	 */
	public function status($value = false, $status = null) {
		if($value !== true && $value !== false) return $this->setStatus($value);
		if($status === null) $status = $this->status; 
		if($value === false) return $status; 
		return PageProperties::statusToNames($status);
	}

	/**
	 * Set the value for isNew, i.e. doesn't exist in the DB (internal use only)
	 * 
	 * #pw-internal
	 *
	 * @param bool @isNew
	 * @return $this
	 *
	 */
	public function setIsNew($isNew) {
		$this->isNew = $isNew ? true : false; 
		return $this; 
	}

	/**
	 * Set that the Page is fully loaded (internal use only)
	 *
	 * Pages::getById sets this once it has completed loading the page
	 * This method also triggers the loaded() method that hooks may listen to
	 * 
	 * #pw-internal
	 *
	 * @param bool $isLoaded
	 * @param bool $quiet Set without triggering anything else? (default=false)
	 * @return $this
	 *
	 */
	public function setIsLoaded($isLoaded, $quiet = false) {
		$isLoaded = !$isLoaded || $isLoaded === 'false' ? false : true;
		if($quiet) {
			$this->isLoaded = $isLoaded;
			return $this;
		}
		if($isLoaded) {
			$this->processFieldDataQueue();
			unset(Page::$loadingStack[$this->settings['id']]); 
		}
		$this->isLoaded = $isLoaded ? true : false; 
		if($isLoaded) {
			//$this->loaded();
			$this->_callHookMethod('loaded');
		}
		return $this; 
	}

	/**
	 * Process and instantiate any data in the fieldDataQueue
	 *
	 * This happens after setIsLoaded(true) is called
	 *
	 */
	protected function processFieldDataQueue() {
		if($this->values()->processFieldDataQueue($this, $this->fieldDataQueue)) {
			$this->fieldDataQueue = array();
		}
	}

	/**
	 * For hooks to listen to, triggered when page is loaded and ready
	 * 
	 * #pw-hooker
	 *
	 */
	public function ___loaded() { }


	/**
	 * Set output formatting state of page
	 * 
	 * The output formatting state determines if a page's output is allowed to be filtered by runtime formatters. 
	 * Pages used for output should have output formatting on. Pages you intend to manipulate and save should 
	 * have it off. See this post about [output formatting](https://processwire.com/blog/posts/output-formatting/).
	 * 
	 * ~~~~~
	 * // Set output formatting state off, for page manipulation
	 * $page->setOutputFormatting(false); 
	 * $page->title = 'About Us';
	 * $page->save();
	 *
	 * // You can also use this shorter version 
	 * $page->of(false); 
	 * ~~~~~
	 * 
	 * #pw-internal
	 *
	 * @param bool $outputFormatting Optional, default true
	 * @return $this
	 * @see Page::outputFormatting(), Page::of()
	 * @link https://processwire.com/blog/posts/output-formatting/
	 *
	 */
	public function setOutputFormatting($outputFormatting = true) {
		$this->outputFormatting = $outputFormatting ? true : false; 
		return $this; 
	}

	/**
	 * Return true if output formatting is on, false if not. 
	 * 
	 * #pw-internal
	 *
	 * @return bool True if output formatting is ON, false if OFF.
	 * @see Page::of(), Page::setOutputFormatting()
	 *
	 */
	public function outputFormatting() {
		return $this->outputFormatting; 
	}

	/**
	 * Get or set the current output formatting state of the page
	 * 
	 * - Always returns the current output formatting state: true if ON, or false if OFF.
	 * 
	 * - To set the current output formatting state, provide a boolean true to turn it ON, or boolean false to turn it OFF.
	 * 
	 * - Pages used for front-end output should have output formatting turned ON. 
	 * 
	 * - Pages that you are manipulating and saving should have output formatting turned OFF. 
	 * 
	 * See this post about [output formatting](https://processwire.com/blog/posts/output-formatting/).
	 *
	 * ~~~~~ 
	 * // Set output formatting state off, for page manipulation
	 * $page->of(false);
	 * $page->title = 'About Us';
	 * $page->save();
	 * ~~~~~
	 *
	 * #pw-group-common
	 * #pw-group-output-rendering
	 * #pw-group-manipulation
	 *
	 * @param bool $outputFormatting If specified, sets output formatting state ON or OFF. If not specified, nothing is changed. 
	 * @return bool Current output formatting state (before this function call, if it was changed)
	 * @link https://processwire.com/blog/posts/output-formatting/
	 *
	 */
	public function of($outputFormatting = null) {
		$of = $this->outputFormatting; 
		if(!is_null($outputFormatting)) $this->outputFormatting = $outputFormatting ? true : false; 
		return $of; 
	}

	/**
	 * Return instance of PagefilesManager specific to this Page
	 * 
	 * #pw-group-files
	 *
	 * @return PagefilesManager
	 *
	 */
	public function filesManager() {
		if($this->hasStatus(Page::statusDeleted)) return null;
		if(is_null($this->filesManager)) $this->filesManager = $this->wire(new PagefilesManager($this)); 
		return $this->filesManager; 
	}

	/**
	 * Does this Page use secure Pagefiles?
	 * 
	 * See also `$template->pagefileSecure` and `$config->pagefileSecure` which determine the return value. 
	 *
	 * #pw-group-files
	 *
	 * @return bool|null Returns boolean true if yes, false if no, or null if not known
	 * @since 3.0.166
	 *
	 */
	public function secureFiles() {
		if($this->wire()->config->pagefileSecure && !$this->isPublic()) return true;
		$template = $this->getAccessTemplate();
		if(!$template) return null;
		$value = $template->pagefileSecure;
		if($value < 1) return false; // 0: disabled
		if($value > 1) return true; // 2: files always secure
		return !$this->isPublic(); // 1: secure only if page not public
	}

	/**
	 * Does the page have a files path for storing files?
	 * 
	 * This will only check if files path exists, it will not create the path if it’s not already present.
	 * 
	 * #pw-group-files
	 * 
	 * @return bool
	 * @since 3.0.138 Earlier versions must use the more verbose PagefilesManager::hasPath($page)
	 * @see hasFiles(), filesManager()
	 * 
	 */
	public function hasFilesPath() {
		return PagefilesManager::hasPath($this);
	}

	/**
	 * Does the page have a files path and one or more files present in it?
	 * 
	 * This will only check if files exist, it will not create the directory if it’s not already present.
	 * 
	 * #pw-group-files
	 * 
	 * @return bool
	 * @since 3.0.138 Earlier versions must use the more verbose PagefilesManager::hasFiles($page)
	 * @see hasFilesPath(), filesPath(), filesManager()
	 * 
	 */
	public function hasFiles() {
		return PagefilesManager::hasFiles($this); 
	}

	/**
	 * Does Page have given filename in its files directory?
	 *
	 * @param string $file File basename or verbose hash
	 * @param array $options
	 *  - `getPathname` (bool): Get full path + filename when would otherwise return boolean true? (default=false)
	 *  - `getPagefile` (bool): Get Pagefile object when would otherwise return boolean true? (default=false)
	 * @return bool|string
	 * @since 3.0.166
	 *
	 */
	public function hasFile($file, array $options = array()) {
		$defaults = array(
			'getPathname' => false,
			'getPagefile' => false,
		);
		$file = basename($file);
		$options = array_merge($defaults, $options);
		$hasFile = PagefilesManager::hasFile($this, $file, $options['getPathname']);
		if($hasFile && $options['getPagefile']) {
			$hasFile = $this->wire()->fieldtypes->FieldtypeFile->getPagefile($this, $file);
		}
		return $hasFile;
	}

	/**
	 * Returns the path for files, creating it if it does not yet exist
	 * 
	 * #pw-group-files
	 * 
	 * @return string
	 * @since 3.0.138 You can also use the equivalent but more verbose `$page->filesManager()->path()` in any version
	 * @see filesUrl(), hasFilesPath(), hasFiles(), filesManager()
	 * 
	 */
	public function filesPath() {
		return $this->filesManager()->path();
	}

	/**
	 * Returns the URL for files, creating it if it does not yet exist
	 * 
	 * #pw-group-files
	 * 
	 * @return string
	 * @see filesPath(), filesManager()
	 * @since 3.0.138 You can use the equivalent but more verbose `$page->filesManager()->url()` in any version
	 * 
	 */
	public function filesUrl() {
		return $this->filesManager()->url();
	}
	
	/**
	 * Prepare the page and its fields for removal from runtime memory, called primarily by Pages::uncache()
	 * 
	 * #pw-internal
	 *
	 */
	public function uncache() {
		$trackChanges = $this->trackChanges();
		if($trackChanges) $this->setTrackChanges(false); 
		$template = $this->template();
		$fieldgroup = $template ? $template->fieldgroup : array();
		foreach($fieldgroup as $field) { /** @var Field $field */
			$value = parent::get($field->name);
			if(!is_object($value)) continue;
			parent::set($field->name, null); 
			unset($this->wakeupNameQueue[$field->name]);
		}
		if($this->filesManager) $this->filesManager->uncache(); 
		$this->filesManager = null;
		if($trackChanges) $this->setTrackChanges(true); 
	}

	/**
	 * Returns the page from which role/access settings are inherited from
	 * 
	 * #pw-group-access
	 *
	 * @param string $type Optionally specify one of 'view', 'edit', 'add', or 'create' (default='view')
	 * @return Page|NullPage Returns NullPage if none found
	 *
	 */
	public function getAccessParent($type = 'view') {
		return $this->access()->getAccessParent($this, $type);
	}

	/**
	 * Returns the template from which role/access settings are inherited from
	 * 
	 * #pw-group-access
	 *
	 * @param string $type Optionally specify one of 'view', 'edit', 'add', or 'create' (default='view')
	 * @return Template|null Returns Template object or NULL if none	
	 *
	 */
	public function getAccessTemplate($type = 'view') {
		return $this->access()->getAccessTemplate($this, $type);
	}
	
	/**
	 * Return Roles (PageArray) that have access to this page
	 *
	 * This is determined from the page's template. If the page's template has roles turned off, 
	 * then it will go down the tree till it finds usable roles to use and inherit from. 
	 * 
	 * #pw-group-access
	 *
	 * @param string $type May be 'view', 'edit', 'create' or 'add' (default='view')
	 * @return PageArray of Role objects
	 *
	 */
	public function getAccessRoles($type = 'view') {
		return $this->access()->getAccessRoles($this, $type);
	}

	/**
	 * Returns whether this page has the given access role
	 *
	 * Given access role may be a role name, role ID or Role object.
	 * 
	 * #pw-group-access
	 *
	 * @param string|int|Role $role 
	 * @param string $type May be 'view', 'edit', 'create' or 'add' (default is 'view')
	 * @return bool
	 *
	 */
	public function hasAccessRole($role, $type = 'view') {
		return $this->access()->hasAccessRole($this, $role, $type); 
	}

	/**
	 * Is $value1 equal to $value2?
	 * 
	 * @param string $key Name of the key that triggered the check (see WireData::set)
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return bool
	 *
	 */
	protected function isEqual($key, $value1, $value2) {
		return $this->comparison()->isEqual($this, $key, $value1, $value2); 
	}

	/**
	 * Return a Page helper class instance that’s common among all Page (and derived) objects in this ProcessWire instance
	 * 
	 * @param string $className
	 * @return object|PageComparison|PageAccess|PageTraversal|PageValues
	 * 
	 */
	protected function getHelperInstance($className) {
		$instanceID = $this->wire()->getProcessWireInstanceID();
		if(!isset(PageProperties::$helpers[$instanceID])) {
			// no helpers yet for this ProcessWire instance
			PageProperties::$helpers[$instanceID] = array();
		}
		if(!isset(PageProperties::$helpers[$instanceID][$className])) {
			// helper not yet loaded, so load it
			$nsClassName = __NAMESPACE__ . "\\$className";
			$helper = new $nsClassName();
			if($helper instanceof WireFuelable) $this->wire($helper);
			PageProperties::$helpers[$instanceID][$className] = $helper;
		} else {
			// helper already ready to use
			$helper = PageProperties::$helpers[$instanceID][$className];
		}
		return $helper;
	}

	/**
	 * @return PageComparison
	 * 
	 */
	protected function comparison() {
		return $this->getHelperInstance('PageComparison');
	}

	/**
	 * @return PageAccess
	 *
	 */
	protected function access() {
		return $this->getHelperInstance('PageAccess');
	}

	/**
	 * @return PageTraversal
	 *
	 */
	protected function traversal() {
		return $this->getHelperInstance('PageTraversal');
	}

	/**
	 * @return PageValues
	 * 
	 */
	protected function values() {
		return $this->getHelperInstance('PageValues');
	}
	
	/**
	 * Return a translation array of all: status name => status number
	 *
	 * This enables string shortcuts to be used for statuses elsewhere in ProcessWire
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 *
	 */
	static public function getStatuses() {
		return PageProperties::$statuses;
	}

	/**
	 * Tells the page what Process it is being edited by, or simply that it's being edited
	 * 
	 * #pw-internal
	 * 
	 * @param WirePageEditor $editor
	 * 
	 */
	public function ___setEditor(WirePageEditor $editor) {
		// $this->setQuietly('_editor', $editor); // uncomment when/if needed
	}

	/**
	 * Get or set current traversal pages (internal use)
	 * 
	 * When setting, force use of given $items (siblings) and sort order in some 
	 * traversal methods like next(), prev() and related methods. Given $items must 
	 * include this page as well before used in any traversal calls.
	 * 
	 * - To set, specify a PageArray for $items. 
	 * - To unset, specify boolean false for $items. 
	 * - To get current traversal pages omit all arguments. 
	 * 
	 * #pw-internal
	 * 
	 * @param PageArray|bool|null $items Traversal pages (PageArray), boolean false to unset, or omit to get. 
	 * @return PageArray|null
	 * @since 3.0.116
	 * 
	 */
	public function traversalPages($items = null) {
		if($items instanceof PageArray) $this->traversalPages = $items; // set
		if($items === false) $this->traversalPages = null; // unset
		return $this->traversalPages; // get
	}

	/**
	 * Get the icon name associated with this Page (if applicable)
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function ___getIcon() {
		return $this->values()->getIcon($this);
	}

	/**
	 * Get label markup to use in page-list or blank to use template/page-list defaults
	 *
	 * This method enables custom page classes to override page labels in page-list. 
	 * PLEASE NOTE: Inline markup is allowed so make sure to entity-encode any text field values. 
	 * 
	 * If you are looking for a hookable version, you should instead hook
	 * `ProcessPageListRender::getPageLabel` which receives the Page as its first argument.
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * @since 3.0.206
	 * 
	 */
	public function getPageListLabel() {
		return '';
	}
	
	/**
	 * Return the API variable used for managing pages of this type
	 * 
	 * #pw-internal
	 * 
	 * @return Pages|PagesType
	 * 
	 */
	public function getPagesManager() {
		return $this->wire()->pages;
	}
	
	/**
	 * Get lazy loading state, set lazy load state, or trigger the page to load
	 * 
	 * $page->_lazy() to return current lazy loading state (which is page ID or boolean). 
	 * $page->_lazy(123) to set the page as a lazy loading page and establish its id (replacing 123 with actual ID). 
	 * $page->_lazy(true) to trigger the page to load. 
	 * 
	 * #pw-internal
	 * 
	 * @param int|bool|null $lazy Specify one of the following: 
	 *  - Page ID to establish it as lazy loading. 
	 *  - Boolean true to trigger load of the page.
	 *  - Omit to just return the lazy load value. 
	 * @return bool|int Returns one of the following: 
	 *  - Page ID if lazy load pending.
	 *  - Boolean true if lazy loading and already loaded. 
	 *  - If load was requested in arguments, then returns true on success, false on fail.
	 * @throws WireException
	 * 
	 */
	public function _lazy($lazy = null) {
		if($lazy === null) return $this->lazyLoad; // return current state
		if(is_int($lazy)) {
			// set state (page ID)
			if($lazy > 0 && !$this->lazyLoad) {
				$this->lazyLoad = $lazy;
				$this->set('id', $lazy);
			}
			return true;
		} else if($lazy === true) {
			// load page
			if(!is_int($this->lazyLoad) || $this->lazyLoad < 1) return false;
			$this->lazyLoad = true;
			$page = $this->wire()->pages->getById($this->id, array(
				'cache' => (is_string($this->loaderCache) ? $this->loaderCache : false),
				'getOne' => true,
				'page' => $this // This. Just This.
			));
			return $page->id > 0;
		} else {
			throw new WireException("Invalid arguments to Page::lazy()");
		}
	}

	/**
	 * Handles get/find loads specific to this Page from the $pages API variable
	 * 
	 * #pw-internal
	 * 
	 * @param string $method The $pages API method to call (get, find, findOne, or count)
	 * @param string|int $selector The selector argument of the $pages call
	 * @param array $options Any additional options (see Pages::find for options). 
	 * @return Pages|Page|PageArray|NullPage|int
	 * @throws WireException
	 * 
	 */
	public function _pages($method = '', $selector = '', $options = array()) {
		if(empty($method)) return $this->wire()->pages;
		if(!isset($options['cache'])) $options['cache'] = $this->loaderCache;
		if(!isset($options['caller'])) $options['caller'] = "page._pages.$method";
		$result = $this->wire()->pages->$method($selector, $options);
		return $result;
	}

	/**
	 * Get or set page’s persistent meta data 
	 * 
	 * This meta data is managed in the DB. Setting a value immediately saves it in the DB, while 
	 * getting a value immediately loads it from the DB. As a result, this data is independent of the 
	 * usual Page load and save operations. This is primarily for internal core use, but may be 
	 * useful for other specific non-core purposes as well. 
	 * 
	 * Note that this data is tied to the page where you call it. Meta data is completely free-form 
	 * and has no connection to ProcessWire fields.
	 * 
	 * Values for meta data must be basic PHP types, whether arrays, strings, numbers, etc. Please do
	 * not use objects for meta values at this time.
	 * 
	 * ~~~~~
	 * // set and save a meta value 
	 * $page->meta()->set('colors', [ 'red', 'green', 'blue' ]); 
	 * 
	 * // get a meta value
	 * $colors = $page->meta()->get('colors');
	 * 
	 * // alternate shorter syntax for either of the above
	 * $page->meta('colors', [ 'red', 'green', 'blue' ]); // set
	 * $colors = $page->meta('colors'); // get
	 * 
	 * // delete a meta value
	 * $page->meta()->remove('colors');
	 * 
	 * // get the WireDataDB instance that stores the meta values,
	 * // it has all the same methods as WireData objects...
	 * $meta = $page->meta();
	 * 
	 * // ...such as, get all values in an array:
	 * $values = $meta->getArray();
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @param string|bool $key Omit to get the WireData instance or specify property name to get or set. 
	 * @param null|mixed $value Value to set for given $key or omit if getting a value. 
	 * @return WireDataDB|string|array|int|float
	 * @since 3.0.133
	 * 
	 */
	public function meta($key = '', $value = null) {
		/** @var Pages $pages */
		if($this->_meta === null) $this->_meta = $this->wire(new WireDataDB($this->id, 'pages_meta')); 
		if(empty($key)) return $this->_meta; // return instance
		if($value === null) return $this->_meta->get($key); // get value
		return $this->_meta->set($key, $value); // set value
	}
	
	/**
	 * Track a change to a property in this Page
	 *
	 * The change will only be recorded if change tracking is enabled for this object instance.
	 *
	 * #pw-internal
	 *
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change
	 * @param mixed $new New value
	 * @return $this
	 *
	 */
	public function trackChange($what, $old = null, $new = null) {
		if($this->isLoaded && $old !== $new) {
			if($what === 'name' && strlen("$old") && !strlen("$this->namePrevious")) {
				$this->namePrevious = $old;
			} else if($what === 'status' && $old !== null) {
				$this->statusPrevious = (int) $old;
			} else if($what === 'sort' && $old !== null && $this->sortPrevious === null) {
				$this->sortPrevious = (int) $old;
			}
		}
		return parent::trackChange($what, $old, $new);
	}

	/**
	 * Set directly to settings (for internal use)
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @param int|string|bool $value
	 * @since 3.0.205
	 * 
	 */
	public function _setSetting($key, $value) {
		if($this->isLoaded && $this->trackChanges && !$this->quietMode) {
			$valuePrevious = isset($this->settings[$key]) ? $this->settings[$key] : null;
			if($valuePrevious != $value) $this->trackChange($key, $valuePrevious, $value);
		}
		$this->settings[$key] = $value;
	}

	/**
	 * Get directly from settings (for internal use)
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return string|int|bool|null
	 * @since 3.0.205
	 * 
	 */
	public function _getSetting($key) {
		if($key === 'quietMode') return $this->quietMode;
		return isset($this->settings[$key]) ? $this->settings[$key] : null;
	}

	/**
	 * Get or set from wakeupNameQueue
	 * 
	 * #pw-internal
	 * 
	 * @param string|null $key String to get or set, omit to get all
	 * @param bool|null $set Specify true to toggle on, false to toggle off, or omit to get set state
	 * @return bool|null|array 
	 * @since 3.0.205
	 * 
	 */
	public function wakeupNameQueue($key = null, $set = null) {
		if($key === null) {
			return $this->wakeupNameQueue;
		} else if($set === null) {
			return isset($this->wakeupNameQueue[$key]); 
		} else if($set === false) {
			unset($this->wakeupNameQueue[$key]);
		} else if($set) {
			$this->wakeupNameQueue[$key] = true;
		} 
		return null;
	}
	
	/**
	 * Get or set from fieldDataQueue
	 * 
	 * #pw-internal
	 *
	 * @param string|null|array $key String to set one, array to set all, null to get all
	 * @param mixed $value Value to set if key is string
	 * @param bool $unset Specify true to unset $key while also specifying null for $value
	 * @return array|mixed|null
	 * @since 3.0.205
	 *
	 */
	public function fieldDataQueue($key = null, $value = null, $unset = false) {
		if($key === null) return $this->fieldDataQueue;
		if($unset) {
			unset($this->fieldDataQueue[$key]);
		} else if($value !== null) {
			$this->fieldDataQueue[$key] = $value;
		} else if(is_array($key)) {
			$this->fieldDataQueue = $key;
		} else if(isset($this->fieldDataQueue[$key])) {
			return $this->fieldDataQueue[$key];
		}
		return null;
	}

	/**
	 * Get directly from parent class (for internal use)
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return mixed|null
	 * @since 3.0.205
	 * 
	 */
	public function _parentGet($key) {
		return parent::get($key);
	}

	/**
	 * Set directly to parent class (for internal use)
	 * 
	 * #pw-internal
	 * 
	 * @param $key
	 * @param $value
	 * @since 3.0.205
	 * 
	 */
	public function _parentSet($key, $value) {
		parent::set($key, $value);
	}
	/**
	 * Ensures that isset() and empty() work for this classes properties.
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return bool
	 *
	 */
	public function __isset($key) {
		if($this->isLoaded) {
			return $this->get($key) !== null;
		} else {
			if(isset(PageProperties::$baseProperties[$key])) return true;
			if(isset(PageProperties::$basePropertiesAlternates[$key])) return true;
			if($this->hasField($key)) return true;
			return false;
		}
	}

	/**
	 * #pw-internal
	 *
	 * @param string $key
	 *
	 */
	public function __unset($key) {
		if($key === 'filesManager') {
			$this->filesManager = null;
		} else {
			parent::__unset($key);
		}
	}
	
	/**
	 * Returns the Page ID in a string
	 *
	 * @return string
	 *
	 */
	public function __toString() {
		return "$this->id";
	}

}
