<?php namespace ProcessWire;

/**
 * ProcessWire Page
 *
 * Page is the class used by all instantiated pages and it provides functionality for:
 *
 * 1. Providing get/set access to the Page's properties
 * 2. Accessing the related hierarchy of pages (i.e. parents, children, sibling pages)
 * 
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
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
 * @property int $id The numbered ID of the current page #pw-group-system
 * @property string $name The name assigned to the page, as it appears in the URL #pw-group-system #pw-group-common
 * @property string $namePrevious Previous name, if changed. Blank if not. #pw-group-previous
 * @property string $title The page’s title (headline) text
 * @property string $path The page’s URL path from the homepage (i.e. /about/staff/ryan/) 
 * @property string $url The page’s URL path from the server's document root
 * @property array $urls All URLs the page is accessible from, whether current, former and multi-language. #pw-group-urls
 * @property string $httpUrl Same as $page->url, except includes scheme (http or https) and hostname.
 * @property Page|string|int $parent The parent Page object or a NullPage if there is no parent. For assignment, you may also use the parent path (string) or id (integer). #pw-group-traversal
 * @property Page|null $parentPrevious Previous parent, if parent was changed. #pw-group-previous
 * @property int $parent_id The numbered ID of the parent page or 0 if homepage or not assigned. #pw-group-system
 * @property int $templates_id The numbered ID of the template usedby this page. #pw-group-system
 * @property PageArray $parents All the parent pages down to the root (homepage). Returns a PageArray. #pw-group-common #pw-group-traversal
 * @property Page $rootParent The parent page closest to the homepage (typically used for identifying a section) #pw-group-traversal
 * @property Template|string $template The Template object this page is using. The template name (string) may also be used for assignment.
 * @property Template|null $templatePrevious Previous template, if template was changed. #pw-group-previous
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
 * @property int $created Unix timestamp of when the page was created. #pw-group-common #pw-group-date-time #pw-group-system
 * @property string $createdStr Date/time when the page was created (formatted date/time string). #pw-group-date-time
 * @property int $modified Unix timestamp of when the page was last modified. #pw-group-common #pw-group-date-time #pw-group-system
 * @property string $modifiedStr Date/time when the page was last modified (formatted date/time string). #pw-group-date-time
 * @property int $published Unix timestamp of when the page was published. #pw-group-common #pw-group-date-time #pw-group-system
 * @property string $publishedStr Date/time when the page was published (formatted date/time string). #pw-group-date-time
 * @property int $created_users_id ID of created user. #pw-group-system
 * @property User $createdUser The user that created this page. Returns a User or a NullUser.
 * @property int $modified_users_id ID of last modified user. #pw-group-system
 * @property User $modifiedUser The user that last modified this page. Returns a User or a NullUser.
 * @property PagefilesManager $filesManager The object instance that manages files for this page. #pw-advanced
 * @property bool $outputFormatting Whether output formatting is enabled or not. #pw-advanced
 * @property int $sort Sort order of this page relative to siblings (applicable when manual sorting is used). #pw-group-system
 * @property int $index Index of this page relative to its siblings, regardless of sort (starting from 0). #pw-group-traversal
 * @property string $sortfield Field that a page is sorted by relative to its siblings (default="sort", which means drag/drop manual) #pw-group-system
 * @property null|array _statusCorruptedFields Field names that caused the page to have Page::statusCorrupted status. #pw-internal
 * @property int $status Page status flags. #pw-group-system #pw-group-status
 * @property int|null $statusPrevious Previous status, if status was changed. #pw-group-status #pw-group-previous
 * @property string statusStr Returns space-separated string of status names active on this page. #pw-group-status
 * @property Fieldgroup $fieldgroup Fieldgroup used by page template. Shorter alias for $page->template->fieldgroup (same as $page->fields) #pw-advanced
 * @property string $editUrl URL that this page can be edited at. #pw-group-urls
 * @property string $editURL Alias of $editUrl. #pw-internal
 * @property PageRender $render May be used for field markup rendering like $page->render->title. #pw-advanced
 * @property bool $loaderCache Whether or not pages loaded as a result of this one may be cached by PagesLoaderCache. #pw-internal
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
 * 
 * @property Page|null $_cloning Internal runtime use, contains Page being cloned (source), when this Page is the new copy (target). #pw-internal
 * @property bool|null $_hasAutogenName Internal runtime use, set by Pages class when page as auto-generated name. #pw-internal
 * @property bool|null $_forceSaveParents Internal runtime/debugging use, force a page to refresh its pages_parents DB entries on save(). #pw-internal
 * 
 * Methods added by PageRender.module: 
 * -----------------------------------
 * @method string|mixed render($fieldName = '') Returns rendered page markup. If given a $fieldName argument, it behaves same as the renderField() method. #pw-group-output-rendering
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
 * @method Page getLanguageValue($language, $field) Get value for field in language (requires LanguageSupport module). $language may be ID, language name or Language object. Field should be field name (string). #pw-group-languages
 * 
 * Methods added by LanguageSupportPageNames.module (not installed by default)
 * ---------------------------------------------------------------------------
 * @method string localName($language = null, $useDefaultWhenEmpty = false) Return the page name in the current user’s language, or specify $language argument (Language object, name, or ID), or TRUE to use default page name when blank (instead of 2nd argument). #pw-group-languages
 * @method string localPath($language = null) Return the page path in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 * @method string localUrl($language = null) Return the page URL in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 * @method string localHttpUrl($language = null) Return the page URL (including scheme and hostname) in the current user's language, or specify $language argument (Language object, name, or ID). #pw-group-languages #pw-group-urls
 *
 * Methods added by PageFrontEdit.module (not always installed by default)
 * -----------------------------------------------------------------------
 * @method string|bool|mixed edit($key = null, $markup = null, $modal = null) Get front-end editable field output or get/set status.
 * 
 * Methods added by ProDrafts.module (if installed)
 * ------------------------------------------------
 * @method ProDraft|\ProDraft|int|string|Page|array draft($key = null, $value = null) Helper method for drafts (added by ProDrafts). #pw-advanced
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
	 * Status string shortcuts, so that status can be specified as a word
	 * 
	 * See also: self::getStatuses() method. 
	 * 
	 * @var array
	 * 
	 */
	static protected $statuses = array(
		'reserved' => self::statusReserved,
		'locked' => self::statusLocked,
		'systemID' => self::statusSystemID,
		'system' => self::statusSystem,
		'unique' => self::statusUnique,
		'draft' => self::statusDraft,
		'flagged' => self::statusFlagged, 
		'internal' => self::statusInternal,
		'temp' => self::statusTemp,
		'hidden' => self::statusHidden,
		'unpublished' => self::statusUnpublished,
		'trash' => self::statusTrash,
		'deleted' => self::statusDeleted,
		'systemOverride' => self::statusSystemOverride, 
		'corrupted' => self::statusCorrupted, 
		'max' => self::statusMax,
		'on' => self::statusOn,
		);

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
	 * Once setIsLoaded(true) is called, this data is processed and instantiated into the Page and the fieldDataQueue is emptied (and no longer relevant)	
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
	 * @var array of (field name => raw field value)
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
	 * @var bool
	 * 
	 */
	protected $lazyLoad = false;

	/**
	 * Whether or not pages loaded by this one are allowed to be cached by PagesLoaderCache class
	 * 
	 * @var bool
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
	 * Reference to main config, optimization so that get() method doesn't get called
	 * 
	 * @var Config|null
	 *
	 */
	protected $config = null; 

	/**
	 * When true, exceptions won't be thrown when values are set before templates
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
	protected $createdUser = null;

	/**
	 * Cached User that last modified the page
	 * 
	 * @var User|null
	 * 
	 */
	protected $modifiedUser = null;

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
	 * Properties that can be accessed, mapped to method of access (excluding custom fields of course)
	 * 
	 * Keys are base property name, values are one of:
	 *  - [methodName]: method name that it maps to ([methodName]=actual method name)
	 *  - "s": property name is accessible in $this->settings using same key
	 *  - "p": Property name maps to same property name in $this
	 *  - "m": Property name maps to same method name in $this
	 *  - "n": Property name maps to same method name in $this, but may be overridden by custom field
	 *  - "t": Property name maps to PageTraversal method with same name, if not overridden by custom field
	 *  - [blank]: needs additional logic to be handled ([blank]='')
	 * 
	 * @var array
	 * 
	 */
	static $baseProperties = array(
		'accessTemplate' => 'getAccessTemplate',
		'addable' => 'm',
		'child' => 'm',
		'children' => 'm',
		'created' => 's', 
		'createdStr' => '',
		'createdUser' => '',
		'created_users_id' => 's', 
		'deletable' => 'm',
		'deleteable' => 'm',
		'editable' => 'm',
		'editUrl' => 'm',
		'fieldgroup' => '',
		'filesManager' => 'm',
		'hasChildren' => 'm',
		'hasLinks' => 't',
		'hasParent' => 'parents',
		'hasReferences' => 't',
		'httpUrl' => 'm',
		'id' => 's',
		'index' => 'n',
		'instanceID' => 'p',
		'isHidden' => 'm',
		'isLoaded' => 'm',
		'isLocked' => 'm',
		'isNew' => 'm',
		'isPublic' => 'm',
		'isTrash' => 'm',
		'isUnpublished' => 'm',
		'links' => 'n',
		'listable' => 'm',
		'modified' => 's',
		'modifiedStr' => '',
		'modifiedUser' => '',
		'modified_users_id' => 's',
		'moveable' => 'm',
		'name' => 's',
		'namePrevious' => 'p',
		'next' => 'm',
		'numChildren' => 's',
		'numParents' => 'm',
		'numDescendants' => 'm',
		'numLinks' => 't',
		'numReferences' => 't',
		'output' => 'm',
		'outputFormatting' => 'p',
		'parent' => 'm',
		'parent_id' => '',
		'parentPrevious' => 'p',
		'parents' => 'm',
		'path' => 'm',
		'prev' => 'm',
		'publishable' => 'm',
		'published' => 's',
		'publishedStr' => '',
		'quietMode' => 'p',
		'references' => 'n',
		'referencing' => 't',
		'render' => '',
		'rootParent' => 'm',
		'siblings' => 'm',
		'sort' => 's',
		'sortable' => 'm',
		'sortfield' => 's',
		'status' => 's',
		'statusPrevious' => 'p',
		'statusStr' => '',
		'template' => 'p',
		'templates_id' => '',
		'templatePrevious' => 'p',
		'trashable' => 'm',
		'url' => 'm',
		'urls' => 'm',
		'viewable' => 'm'
	);

	/**
	 * Alternate names accepted for base properties
	 * 
	 * Keys are alternate property name and values are base property name
	 * 
	 * @var array
	 * 
	 */
	static $basePropertiesAlternates = array(
		'createdUserID' => 'created_users_id', 
		'createdUsersID' => 'created_users_id',
		'created_user_id' => 'created_users_id',
		'editURL' => 'editUrl',
		'fields' => 'fieldgroup',
		'has_parent' => 'hasParent',
		'httpURL' => 'httpUrl',
		'modifiedUserID' => 'modified_users_id',
		'modifiedUsersID' => 'modified_users_id',
		'modified_user_id' => 'modified_users_id',
		'num_children' => 'numChildren',
		'numChildrenVisible' => 'hasChildren',
		'numVisibleChildren' => 'hasChildren',
		'of' => 'outputFormatting',
		'out' => 'output',
		'parentID' => 'parent_id',
		'subpages' => 'children',
		'template_id' => 'templates_id',
		'templateID' => 'templates_id',
		'templatesID' => 'templates_id',
	);

	/**
	 * Method alternates/aliases (alias => actual)
	 * 
	 * @var array
	 * 
	 */
	static $baseMethodAlternates = array(
		'descendants' => 'find',
		'descendant' => 'findOne',
	);

	/**
	 * Create a new page in memory. 
	 *
	 * @param Template $tpl Template object this page should use. 
	 *
	 */
	public function __construct(Template $tpl = null) {

		if(!is_null($tpl)) {
			$tpl->wire($this);
			$this->template = $tpl;
		} 
		$this->useFuel(false); // prevent fuel from being in local scope
		$this->parentPrevious = null;
		$this->templatePrevious = null;
		$this->statusPrevious = null;
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
		foreach($this->template->fieldgroup as $field) {
			$name = $field->name; 
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
		
		if(isset(self::$basePropertiesAlternates[$key])) $key = self::$basePropertiesAlternates[$key];

		if(($key == 'id' || $key == 'name') && $this->settings[$key] && $value != $this->settings[$key]) 
			if(	($key == 'id' && (($this->settings['status'] & Page::statusSystem) || ($this->settings['status'] & Page::statusSystemID))) ||
				($key == 'name' && (($this->settings['status'] & Page::statusSystem)))) {
					throw new WireException("You may not modify '$key' on page '{$this->path}' because it is a system page"); 
		}

		switch($key) {
			/** @noinspection PhpMissingBreakStatementInspection */
			case 'id':
				if(!$this->isLoaded) Page::$loadingStack[(int) $value] = $this;
				// no break is intentional
			case 'sort': 
			case 'numChildren': 
				$value = (int) $value; 
				if($this->settings[$key] !== $value) $this->trackChange($key, $this->settings[$key], $value); 
				$this->settings[$key] = $value; 
				break;
			case 'status':
				$this->setStatus($value); 
				break;
			case 'statusPrevious':
				$this->statusPrevious = is_null($value) ? null : (int) $value; 
				break;
			case 'name':
				$this->setName($value);
				break;
			case 'parent': 
			case 'parent_id':
				if(is_object($value) && $value instanceof Page) {
					// ok
					$this->setParent($value);
				} else if($value && !$this->_parent && 
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
				if($key == 'templates_id' && $this->template && $this->template->id == $value) break;
				if($key == 'templates_id') $value = $this->wire('templates')->get((int)$value); 
				$this->setTemplate($value); 
				break;
			case 'created': 
			case 'modified':
			case 'published':
				if(is_null($value)) $value = 0;
				if(!ctype_digit("$value")) $value = strtotime($value); 
				$value = (int) $value; 
				if($this->settings[$key] !== $value) $this->trackChange($key, $this->settings[$key], $value); 
				$this->settings[$key] = $value;
				break;
			case 'created_users_id':
			case 'modified_users_id':
				$value = (int) $value;
				if($this->settings[$key] !== $value) $this->trackChange($key, $this->settings[$key], $value); 
				$this->settings[$key] = $value; 
				break;
			case 'createdUser':
			case 'modifiedUser':
				$this->setUser($value, str_replace('User', '', $key));
				break;
			case 'sortfield':
				if($this->template && $this->template->sortfield) break;
				$value = $this->wire('pages')->sortfields()->decode($value); 
				if($this->settings[$key] != $value) $this->trackChange($key, $this->settings[$key], $value); 
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
				$this->loaderCache = (bool) $value;	
				break;
			default:
				if(strpos($key, 'name') === 0 && ctype_digit(substr($key, 5)) && $this->wire('languages')) {
					// i.e. name1234
					$this->setName($value, $key);
				} else {
					if($this->quietMode && !$this->template) return parent::set($key, $value);
					$this->setFieldValue($key, $value, $this->isLoaded);
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
		$this->quietMode = true; 
		parent::setQuietly($key, $value);
		$this->quietMode = false;
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
		return parent::set($key, $value); 
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

		if(!$this->template) {
			throw new WireException("You must assign a template to the page before setting custom field values ($key)");
		}

		// if the page is not yet loaded and a '__' field was set, then we queue it so that the loaded() method can 
		// instantiate all those fields knowing that all parts of them are present for wakeup. 
		if(!$this->isLoaded && strpos($key, '__')) {
			list($key, $subKey) = explode('__', $key, 2); 
			if(!isset($this->fieldDataQueue[$key])) $this->fieldDataQueue[$key] = array();
			$this->fieldDataQueue[$key][$subKey] = $value; 
			return $this;
		}

		// check if the given key resolves to a Field or not
		if(!$field = $this->getField($key)) {
			// not a known/saveable field, let them use it for runtime storage
			$valPrevious = parent::get($key);	
			if($valPrevious !== null && is_null(parent::get("-$key")) && $valPrevious !== $value) {
				// store previous value (if set) in a "-$key" version
				parent::setQuietly("-$key", $valPrevious);
			}
			return parent::set($key, $value); 
		}

		// if a null value is set, then ensure the proper blank type is set to the field
		if(is_null($value)) {
			return parent::set($key, $field->type->getBlankValue($this, $field)); 
		}

		// if the page is currently loading from the database, we assume that any set values are 'raw' and need to be woken up
		if(!$this->isLoaded) {
			// queue for wakeup and sanitize on first field access
			$this->wakeupNameQueue[$key] = $key;
			// page is currently loading, so we don't need to continue any further
			return parent::set($key, $value); 
		}

		// check if the field hasn't been already loaded
		if(is_null(parent::get($key))) {
			// this field is not currently loaded. if the $load param is true, then ...
			// retrieve old value first in case it's not autojoined so that change comparisons and save's work 
			if($load) $this->get($key);
			
		} else if(isset($this->wakeupNameQueue[$key])) {
			// autoload value: we don't yet have a "woke" value suitable for change detection, so let it wakeup
			if($this->trackChanges() && $load) {
				// if changes are being tracked, load existing value for comparison
				$this->getFieldValue($key);
			} else {
				// if changes aren't being tracked, the existing value can be discarded
				unset($this->wakeupNameQueue[$key]); 
			}

		} else {
			// check if the field is corrupted
			$isCorrupted = false;
			if(is_object($value) && $value instanceof PageFieldValueInterface) {
				if($value->formatted()) $isCorrupted = true;
			} else if($this->outputFormatting) {
				$result = $field->type->_callHookMethod('formatValue', array($this, $field, $value));
				if($result != $value) $isCorrupted = true; 
			}
			if($isCorrupted) {
				// The field has been loaded or dereferenced from the API, and this field changes when formatters are applied to it. 
				// There is a good chance they are trying to set a formatted value, and we don't allow this situation because the 
				// possibility of data corruption is high. We set the Page::statusCorrupted status so that Pages::save() can abort.
				$this->set('status', $this->status | self::statusCorrupted);
				$corruptedFields = $this->get('_statusCorruptedFields');
				if(!is_array($corruptedFields)) $corruptedFields = array();
				$corruptedFields[$field->name] = $field->name;
				$this->set('_statusCorruptedFields', $corruptedFields);
			}
		}

		// isLoaded so sanitizeValue can determine if it can perform a typecast rather than a full sanitization (when helpful)
		// we don't use setIsLoaded() so as to avoid triggering any other functions
		$isLoaded = $this->isLoaded;
		if(!$load) $this->isLoaded = false;
		// ensure that the value is in a safe format and set it 
		$value = $field->type->sanitizeValue($this, $field, $value); 
		// Silently restore isLoaded state
		if(!$load) $this->isLoaded = $isLoaded;

		return parent::set($key, $value); 
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
	 * ~~~~~
	 *
	 * @param string $key Name of property, format string or selector, per the details above. 
	 * @return mixed Value of found property, or NULL if not found. 
	 * @see __get()
	 *
	 */
	public function get($key) {

		// if lazy load pending, load the page now
		if(is_int($this->lazyLoad) && $this->lazyLoad && $key != 'id') $this->_lazy(true);

		if(is_array($key)) $key = implode('|', $key);
		if(isset(self::$basePropertiesAlternates[$key])) $key = self::$basePropertiesAlternates[$key];
		if(isset(self::$baseProperties[$key])) {
			$type = self::$baseProperties[$key];
			if($type === 'p') {
				// local property
				return $this->$key;
			} else if($type === 'm') {
				// local method
				return $this->{$key}();
			} else if($type === 'n') {
				// local method, possibly overridden by $field
				if(!$this->wire('fields')->get($key)) return $this->{$key}();
			} else if($type === 's') {
				// settings property
				return $this->settings[$key];
			} else if($type === 't') {
				// map to method in PageTraversal, if not overridden by field
				if(!$this->wire('fields')->get($key)) return $this->traversal()->{$key}($this);
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
				$value = $this->template ? $this->template->id : 0;
				break;
			case 'fieldgroup':
				$value = $this->template ? $this->template->fieldgroup : null;
				break;
			case 'modifiedUser':
			case 'createdUser':
				if(!$this->$key) {
					$_key = str_replace('User', '', $key) . '_users_id';
					$u = $this->wire('user');
					if($this->settings[$_key] == $u->id) {
						$this->set($key, $u); // prevent possible recursion loop
					} else {
						$u = $this->wire('users')->get((int) $this->settings[$_key]);
						$this->set($key, $u);
					}
				}
				$value = $this->$key; 
				if($value) $value->of($this->of());
				break;
			case 'urlSegment':
				// deprecated, but kept for backwards compatibility
				$value = $this->wire('input')->urlSegment1; 
				break;
			case 'statusStr':
				$value = implode(' ', $this->status(true)); 
				break;
			case 'modifiedStr':
			case 'createdStr':
			case 'publishedStr':
				$value = $this->settings[str_replace('Str', '', $key)];
				$value = $value ? wireDate($this->wire('config')->dateFormat, $value) : '';
				break;
			case 'render':
				$value = $this->wire('modules')->get('PageRender');	
				$value->setPropertyPage($this);
				break;
			case 'loaderCache':
				$value = $this->loaderCache;
				break;
			
			default:
				if($key && isset($this->settings[(string)$key])) return $this->settings[$key];
				
				// populate a formatted string with {tag} vars
				if(strpos($key, '{') !== false && strpos($key, '}')) return $this->getMarkup($key);
			
				// populate a markup requested field like '_fieldName_'
				if(strpos($key, '_') === 0 && substr($key, -1) == '_' && !$this->wire('fields')->get($key)) {
					if($this->wire('sanitizer')->fieldName($key) == $key) return $this->renderField(substr($key, 1, -1));
				}

				if(($value = $this->getFieldFirstValue($key)) !== null) return $value; 
				if(($value = $this->getFieldValue($key)) !== null) return $value;
				
				// if there is a selector, we'll assume they are using the get() method to get a child
				if(Selectors::stringHasOperator($key)) return $this->child($key);

				// check if it's a field.subfield property
				if(strpos($key, '.') && ($value = $this->getFieldSubfieldValue($key)) !== null) return $value; 
				
				if(strpos($key, '_OR_')) {
					// convert '_OR_' to '|'
					$value = $this->getFieldFirstValue(str_replace('_OR_', '|', $key)); 
					if($value !== null) return $value; 
				}

				// optionally let a hook look at it
				if($this->wire('hooks')->isHooked('Page::getUnknown()')) $value = $this->getUnknown($key);
		}

		return $value; 
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
		$template = $this->template;
		$fieldgroup = $template ? $template->fieldgroup : null;
		if(!$fieldgroup) return null;
		if($this->outputFormatting && $fieldgroup->hasFieldContext($field)) {
			$value = $fieldgroup->getFieldContext($field);
		} else {
			$value = $fieldgroup->getField($field);
		}
		return $value;
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
		if(!$this->template) return new FieldsArray();
		$fields = new FieldsArray();
		/** @var Fieldgroup $fieldgroup */
		$fieldgroup = $this->template->fieldgroup;
		foreach($fieldgroup as $field) {
			if($fieldgroup->hasFieldContext($field)) {
				$field = $fieldgroup->getFieldContext($field);
			}
			if($field) $fields->add($field);
		}
		return $fields;
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
		if(!$this->template) return false;
		if(is_string($field) && strpos($field, '|') !== false) {
			$field = explode('|', $field);
		}
		if(is_array($field)) {
			$result = false;
			foreach($field as $f) {
				$f = trim($f);
				if(!empty($f) && $this->hasField($f)) $result = $f;
				if($result) break;
			}
		} else {
			$result = $this->template->fieldgroup->hasField($field);
		}
		return $result;
	}

	/**
	 * If given a field.subfield string, returns the associated value
	 * 
	 * This is like the getDot() method, but with additional protection during output formatting. 
	 * 
	 * @param $key
	 * @return mixed|null
	 * 
	 */
	protected function getFieldSubfieldValue($key) {
		$value = null;
		if(!strpos($key, '.')) return null;
		if($this->outputFormatting()) {
			// allow limited access to field.subfield properties when output formatting is on
			// we only allow known custom fields, and only 1 level of subfield
			list($key1, $key2) = explode('.', $key);
			$field = $this->getField($key1); 
			if($field && !($field->flags & Field::flagSystem)) {
				// known custom field, non-system
				// if neither is an API var, then we'll allow it
				if(!$this->wire($key1) && !$this->wire($key2)) $value = $this->getDot("$key1.$key2");
			}
		} else {
			// we allow any field.subfield properties when output formatting is off
			$value = $this->getDot($key);
		}
		return $value;
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
		// $key unused is intentional, for access by hooks
		if($key) {}
		return null;
	}

	/**
	 * Handles get() method requests for properties that include a period like "field.subfield"
	 *
	 * Typically these resolve to objects, and the subfield is pulled from the object.
	 * Currently we only allow this dot syntax when output formatting is off. This limitation may be removed
	 * but we have to consider potential security implications before doing so.
	 * 
	 * #pw-internal
	 *
	 * @param string $key Property name in field.subfield format
	 * @return null|mixed Returns null if not found or invalid. Returns property value on success.
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
	 *
	 */
	protected function getFieldFirstValue($multiKey, $getKey = false) {

		// looking multiple keys split by "|" chars, and not an '=' selector
		if(strpos($multiKey, '|') === false || strpos($multiKey, '=') !== false) return null;

		$value = null;
		$keys = explode('|', $multiKey); 

		foreach($keys as $key) {
			$value = $this->getUnformatted($key);
			
			if(is_object($value)) {
				// like LanguagesPageFieldValue or WireArray
				$str = trim((string) $value); 
				if(!strlen($str)) continue; 
				
			} else if(is_array($value)) {
				// array with no items
				if(!count($value)) continue;
				
			} else if(is_string($value)) {
				$value = trim($value); 
			}
			
			if($value) {
				if($this->outputFormatting) $value = $this->get($key);
				if($value) {
					if($getKey) $value = $key;
					break;
				}
			}
		}

		return $value;
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
		
		if(!$this->template) return parent::get($key); 
		$field = $this->getField($key);
		$value = parent::get($key); 
		if(!$field) return $value;  // likely a runtime field, not part of our data
		$invokeArgument = '';

		if($value !== null && isset($this->wakeupNameQueue[$key])) {
			$value = $field->type->_callHookMethod('wakeupValue', array($this, $field, $value));
			$value = $field->type->sanitizeValue($this, $field, $value);
			$trackChanges = $this->trackChanges(true);
			$this->setTrackChanges(false);
			parent::set($key, $value); 
			$this->setTrackChanges($trackChanges);
			unset($this->wakeupNameQueue[$key]); 
		}

		if($field->useRoles && $this->outputFormatting) {
			// API access may be limited when output formatting is ON
			if($field->flags & Field::flagAccessAPI) {
				// API access always allowed because of flag
			} else if($this->viewable($field)) {
				// User has view permission for this field
			} else {
				// API access is denied when output formatting is ON
				// so just return a blank value as defined by the Fieldtype
				// note: we do not store this blank value in the Page, so that
				// the real value can potentially be loaded later without output formatting
				$value = $field->type->getBlankValue($this, $field); 
				return $this->formatFieldValue($field, $value);
			}
		}

		if(!is_null($value) && empty($selector)) {
			// if the non-filtered value is already loaded, return it 
			return $this->formatFieldValue($field, $value);
		}
		
		$track = $this->trackChanges();
		$this->setTrackChanges(false); 
		if(!$field->type) return null;
		
		if($selector && !Selectors::stringHasSelector($selector)) {
			// if selector argument provdied, but isn't valid, we assume it 
			// to instead be an argument for the value's __invoke() method
			$invokeArgument = $selector;
			$selector = '';
		}
	
		if($selector) {
			$value = $field->type->loadPageFieldFilter($this, $field, $selector);	
		} else {
			// $value = $field->type->loadPageField($this, $field);
			$value = $field->type->_callHookMethod('loadPageField', array($this, $field));
		}
		
		if(is_null($value)) {
			$value = $field->type->getDefaultValue($this, $field);
		} else {
			$value = $field->type->_callHookMethod('wakeupValue', array($this, $field, $value)); 
			//$value = $field->type->wakeupValue($this, $field, $value);
		}

		// turn off output formatting and set the field value, which may apply additional changes
		$outputFormatting = $this->outputFormatting;
		if($outputFormatting) $this->setOutputFormatting(false);
		$this->setFieldValue($key, $value, false);
		if($outputFormatting) $this->setOutputFormatting(true);
		$value = parent::get($key);
		
		// prevent storage of value if it was filtered when loaded
		if(!empty($selector)) $this->__unset($key);
		
		if(is_object($value) && $value instanceof Wire) $value->resetTrackChanges(true);
		if($track) $this->setTrackChanges(true); 
	
		$value = $this->formatFieldValue($field, $value);
		
		if($invokeArgument && is_object($value) && method_exists($value, '__invoke')) {
			$value = $value->__invoke($invokeArgument);
		}
		
		return $value;
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
	
		$hasInterface = is_object($value) && $value instanceof PageFieldValueInterface;
		
		if($hasInterface) {
			$value->setPage($this);
			$value->setField($field);
		}

		if($this->outputFormatting) {
			// output formatting is enabled so return a formatted value
			//$value = $field->type->formatValue($this, $field, $value);
			$value = $field->type->_callHookMethod('formatValue', array($this, $field, $value));
			// check again for interface since value may now be different
			if($hasInterface) $hasInterface = is_object($value) && $value instanceof PageFieldValueInterface;
			if($hasInterface) $value->formatted(true);
		} else if($hasInterface && $value->formatted()) {
			// unformatted requested, and value is already formatted so load a fresh copy
			$this->__unset($field->name);
			$value = $this->getFieldValue($field->name);
		}
		
		return $value;
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
		
		if(strpos($key, '{') !== false && strpos($key, '}')) {
			// populate a string with {tags}
			// note that the wirePopulateStringTags() function calls back on this method
			// to retrieve the markup values for each of the found field names
			return wirePopulateStringTags($key, $this);
		}

		if(strpos($key, '|') !== false) {
			$key = $this->getFieldFirstValue($key, true);
			if(!$key) return '';
		}
		
		if($this->wire('sanitizer')->name($key) != $key) {
			// not a possible field name
			return '';
		}

		$parts = strpos($key, '.') ? explode('.', $key) : array($key);
		$value = $this;
		
		do {
			
			$name = array_shift($parts);
			$field = $this->getField($name);
			
			if(!$field && $this->wire($name)) {
				// disallow API vars
				$value = '';
				break;
			}
			
			if($value instanceof Page) {
				$value = $value->getFormatted($name);
			} else if($value instanceof WireData) {
				$value = $value->get($name);
			} else {
				$value = $value->$name;
			}
			
			if($field && count($parts) < 2) {
				// this is a field that will provide its own formatted value
				$subname = count($parts) == 1 ? array_shift($parts) : '';
				if(!$subname || !$this->wire($subname)) {
					$value = $field->type->markupValue($this, $field, $value, $subname);
				}
			}
			
		} while(is_object($value) && count($parts));
		
		if(is_object($value)) {
			if($value instanceof Page) $value = $value->getFormatted('title|name');
			if($value instanceof PageArray) $value = $value->getMarkup();
		}
		
		if(!is_string($value)) $value = (string) $value;
		
		return $value;
	}
	
	/**
	 * Same as getMarkup() except returned value is plain text
	 * 
	 * Returned value is entity encoded, unless $entities argument is false. 
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
		$value = $this->getMarkup($key);
		$length = strlen($value);
		if(!$length) return '';
		$options = array(
			'entities' => (is_null($entities) ? $this->outputFormatting() : (bool) $entities)
		);
		if($oneLine) {
			$value = $this->wire('sanitizer')->markupToLine($value, $options);
		} else {
			$value = $this->wire('sanitizer')->markupToText($value, $options);
		}
		// if stripping tags from non-empty value made it empty, just indicate that it was markup and length
		if(!strlen(trim($value))) $value = "markup($length)";
		return $value; 	
	}

	/**
	 * Get the unformatted value of a field, regardless of output formatting state
	 * 
	 * When a page's output formatting state is off, `$page->get('property')` or `$page->property` will
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
	 * @see Page::getFormatted(), Page::of()
	 *
	 */
	public function getUnformatted($key) {
		$outputFormatting = $this->outputFormatting; 
		if($outputFormatting) $this->setOutputFormatting(false); 
		$value = $this->get($key); 
		if($outputFormatting) $this->setOutputFormatting(true); 
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
		$outputFormatting = $this->outputFormatting;
		if(!$outputFormatting) $this->setOutputFormatting(true);
		$value = $this->get($key);
		if(!$outputFormatting) $this->setOutputFormatting(false);
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
				return $this->getFieldValue($method, $arguments[0]);
			} else {
				return $this->get($method);
			}
		} else if(isset(self::$baseMethodAlternates[$method])) { 
			return call_user_func_array(array($this, self::$baseMethodAlternates[$method]), $arguments);
		} else {
			return parent::___callUnknown($method, $arguments);
		}
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
	 * @see Page::addStatus(), Page::removeStatus()
	 *
	 */
	protected function setStatus($value) {
		
		if(!is_int($value)) {
			// status provided as something other than integer
			if(is_string($value) && !ctype_digit($value)) {
				// string of one or more status names
				if(strpos($value, ',') !== false) $value = str_replace(array(', ', ','), ' ', $value);
				$value = explode(' ', strtolower($value));
			} 
			if(is_array($value)) {
				// array of status names or numbers
				$status = 0;
				foreach($value as $v) {
					if(is_int($v) || ctype_digit("$v")) { // integer
						$status = $status | ((int) $v);
					} else if(is_string($v) && isset(self::$statuses[$v])) { // string (status name)
						$status = $status | self::$statuses[$v];
					}
				}
				if($status) $value = $status; 
			}
			// note if $value started as an integer string, i.e. "123", it gets passed through to below
		}
		
		$value = (int) $value; 
		$override = $this->settings['status'] & Page::statusSystemOverride; 
		if(!$override) { 
			if($this->settings['status'] & Page::statusSystemID) $value = $value | Page::statusSystemID;
			if($this->settings['status'] & Page::statusSystem) $value = $value | Page::statusSystem; 
		}
		if($this->settings['status'] != $value && $this->isLoaded) {
			$this->trackChange('status', $this->settings['status'], $value);
			if($this->statusPrevious === null) {
				$this->statusPrevious = $this->settings['status'];
			}
		}
		$this->settings['status'] = $value;
		if($value & Page::statusDeleted) {
			// disable any instantiated filesManagers after page has been marked deleted
			// example: uncache method polls filesManager
			$this->filesManager = null; 
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
		
		$key = 'name';
		$charset = $this->wire('config')->pageNameCharset;
		$sanitizer = $this->wire('sanitizer');
		
		if($language) {
			// update $key to contain language ID when applicable
			$languages = $this->wire('languages');
			if($languages) {
				if(!is_object($language)) {
					if(strpos($language, 'name') === 0) $language = (int) substr($language, 4);
					$language = $languages->get($language);
					if(!$language || !$language->id || $language->isDefault()) $language = '';
				}
				if(!$language) return $this;
				$key .= $language->id;
			}
			$existingValue = $this->get($key);
		} else {
			$existingValue = isset($this->settings[$key]) ? $this->settings[$key] : '';
		}
	
		if($this->isLoaded) {
			// name is being set after page has already been loaded
			if($charset === 'UTF8') {
				// UTF8 page names allowed but decoding not allowed
				$value = $sanitizer->pageNameUTF8($value);
				
			} else if(empty($existingValue)) {
				// ascii, and beautify if there is no existing value
				$value = $sanitizer->pageName($value, true);
				
			} else {
				// ascii page name and do not beautify
				$value = $sanitizer->pageName($value, false);
			}
			if($existingValue !== $value && !$this->quietMode) {
				// set the namePrevious property when the main 'name' has changed
				if($key === 'name' && $existingValue && empty($this->namePrevious)) {
					$this->namePrevious = $existingValue;
				}
				// track the change 
				$this->trackChange($key, $existingValue, $value);
			}
		} else {
			// name being set while page is loading
			if($charset === 'UTF8' && strpos($value, 'xn-') === 0) {
				// allow decode of UTF8 name while page is loading
				$value = $sanitizer->pageName($value, Sanitizer::toUTF8);
			} else {
				// regular ascii page name while page is loading, do nothing to it
			}
		}
		
		if($key === 'name') {
			$this->settings[$key] = $value;
		} else if($this->quietMode) {
			parent::set($key, $value);
		} else {
			$this->setFieldValue($key, $value, $this->isLoaded); // i.e. name1234
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
		if(!is_object($tpl)) $tpl = $this->wire('templates')->get($tpl); 
		if(!$tpl instanceof Template) throw new WireException("Invalid value sent to Page::setTemplate"); 
		if($this->template && $this->template->id != $tpl->id && $this->isLoaded) {
			if($this->settings['status'] & Page::statusSystem) {
				throw new WireException("Template changes are disallowed on this page");
			}
			if(is_null($this->templatePrevious)) $this->templatePrevious = $this->template; 
			$this->trackChange('template', $this->template, $tpl); 
		}
		if($tpl->sortfield) $this->settings['sortfield'] = $tpl->sortfield; 
		$this->template = $tpl; 
		return $this;
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
		if($this->isLoaded) {
			if(!$this->_parent) $this->parent(); // force it to load
			$this->trackChange('parent', $this->_parent, $parent);
			if(($this->_parent && $this->_parent->id) && $this->_parent->id != $parent->id) {
				if($this->settings['status'] & Page::statusSystem) {
					throw new WireException("Parent changes are disallowed on this page");
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
				$user = $this->wire('users')->get($user);
			}
		}

		// if they are setting an invalid user or unknown user, then the Page defaults to the super user
		if(!$user || !$user->id || !$user instanceof User) {
			$user = $this->wire('users')->get($this->wire('config')->superUserPageID);
		}

		if($userType == 'created') {
			$field = 'created_users_id';
			$this->createdUser = $user; 
		} else if($userType == 'modified') {
			$field = 'modified_users_id';
			$this->modifiedUser = $user;
		} else {
			throw new WireException("Unknown user type in Page::setUser(user, type)"); 
		}

		$existingUserID = $this->settings[$field]; 
		if($existingUserID != $user->id) $this->trackChange($field, $existingUserID, $user->id); 
		$this->settings[$field] = $user->id; 
		return $this; 	
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
		if(!$this->numChildren) return $this->wire('pages')->newPageArray();
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
		if(!$this->numChildren) return $this->wire('pages')->newNullPage();
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
	 * @return int Number of children
	 * @see Page::hasChildren(), Page::children(), Page::child()
	 *
	 */
	public function numChildren($selector = null) {
		if(!$this->settings['numChildren'] && is_null($selector)) return $this->settings['numChildren']; 
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
				return $this->wire('pages')->newNullPage();
			}
		}
		if(empty($selector)) return $this->_parent; 
		if($this->_parent->matches($selector)) return $this->_parent; 
		if($this->_parent->parent_id) return $this->_parent->parent($selector); // recursive, in a way
		return $this->wire('pages')->newNullPage();
	}

	/**
	 * Return this page’s parent pages, or the parent pages matching the given selector.
	 * 
	 * This method returns all parents of this page, in order. If a selector is specified, they
	 * will be filtered by the selector. 
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
	 * 
	 * #pw-group-common
	 * #pw-group-traversal
	 *
	 * @param string|array $selector Optional selector string to filter parents by.
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
	public function next($selector = '', PageArray $siblings = null) {
		if(is_object($selector) && $selector instanceof PageArray) {
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
		if(is_object($selector) && $selector instanceof PageArray) {
			$siblings = $selector;
			$selector = '';
		}
		if(is_object($getQty) && $getQty instanceof PageArray) {
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
	public function nextUntil($selector = '', $filter = '', PageArray $siblings = null) {
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
	public function prev($selector = '', PageArray $siblings = null) {
		if(is_object($selector) && $selector instanceof PageArray) {
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
		return $this->nextAll($selector, $getQty, true);
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
	public function prevUntil($selector = '', $filter = '', PageArray $siblings = null) {
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
		$languages = $this->wire('pages')->newPageArray();
		$templateLanguages = $this->template->getLanguages();
		if(!$templateLanguages) return null;
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
	 * @see Pages::save(), Pages::saveField(), Pages::saveReady(), Pages::saveFieldReady(), Pages::saved(), Pages::fieldSaved()
	 *
	 */
	public function save($field = null, array $options = array()) {
		
		if(is_array($field) && empty($options)) {
			$options = $field;
			$field = null;
		}
		
		if(empty($field)) {
			return $this->wire('pages')->save($this, $options);
		}
		
		if($this->hasField($field)) {
			// save field
			return $this->wire('pages')->saveField($this, $field, $options);
		}

		// save only native properties
		$options['noFields'] = true; 
		return $this->wire('pages')->save($this, $options);
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
	 * @return bool True on success, false on failure.
	 * @throws WireException when attempting to delete a page with children and $recursive option is not specified.
	 * @see Pages::delete()
	 *
	 */
	public function delete($recursive = false) {
		return $this->wire('pages')->delete($this, $recursive); 
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
		return $this->wire('pages')->trash($this); 
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
	public function getIterator() {
		$a = $this->settings; 
		if($this->template && $this->template->fieldgroup) {
			foreach($this->template->fieldgroup as $field) {
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
		foreach($data as $key => $value) {
			if(is_object($value) && $value instanceof Wire) {
				$changed = $value->isChanged();
			}
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
		foreach($this->data as $key => $value) {
			if(is_object($value) && $value instanceof Wire && $value !== $this) $value->resetTrackChanges($trackChanges); 
		}
		return $this; 
	}

	/**
	 * Returns the Page's ID in a string
	 * 
	 * @return string
	 *
	 */
	public function __toString() {
		return "{$this->id}"; 
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
		return $this->wire('hooks')->isHooked('Page::path()') ? $this->__call('path', array()) : $this->___path();
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
	 * - `pageNum` (int|string): Specify pagination number, or "+" for next pagination, or "-" for previous pagination.
	 * - `urlSegmentStr` (string): Specify a URL segment string to append.
	 * - `urlSegments` (array): Specify array of URL segments to append (may be used instead of urlSegmentStr).
	 * - `data` (array): Array of key=value variables to form a query string.
	 * - `http` (bool): Specify true to make URL include scheme and hostname (default=false).
	 * - `language` (Language): Specify Language object to return URL in that Language.
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
		$url = rtrim($this->wire('config')->urls->root, "/") . $this->path();
		if($this->template->slashUrls === 0 && $this->settings['id'] > 1) $url = rtrim($url, '/'); 
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
		$template = $this->template;
		if(!$template) return '';
		/** @var Config $config */
		$config = $this->wire('config');
		$mode = $template->https;
		if($mode > 0 && $config->noHTTPS) $mode = 0;
		switch($mode) {
			case -1: $protocol = 'http'; break;
			case 1: $protocol = 'https'; break;
			default: $protocol = $config->https ? 'https' : 'http';
		}
		if(is_array($options)) {
			unset($options['http']);
		} else if(is_bool($options)) {
			$options = array();
		}
		return "$protocol://" . $config->httpHost . $this->url($options);
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
	 * @param array|bool $options Specify boolean true to force URL to include scheme and hostname, or use $options array:
	 *  - `http` (bool): True to force scheme and hostname in URL (default=auto detect).
	 *  - `language` (Language|bool): Optionally specify Language to start editor in, or boolean true to force current user language.
	 * @return string URL for editing this page
	 * 
	 */
	public function editUrl($options = array()) {
		/** @var Config $config */
		$config = $this->wire('config');
		/** @var Template $adminTemplate */
		$adminTemplate = $this->wire('templates')->get('admin');
		$https = $adminTemplate && ($adminTemplate->https > 0) && !$config->noHTTPS;
		$url = ($https && !$config->https) ? 'https://' . $config->httpHost : '';
		$url .= $config->urls->admin . "page/edit/?id=$this->id";
		if($options === true || (is_array($options) && !empty($options['http']))) {
			if(strpos($url, '://') === false) {
				$url = ($https ? 'https://' : 'http://') . $config->httpHost . $url;
			}
		}
		if($this->wire('languages')) { 
			$language = $this->wire('user')->language;
			if(empty($options['language'])) {
				if($this->wire('page')->template->id == $adminTemplate->id) $language = null;
			} else if($options['language'] instanceof Page) {
				$language = $options['language'];
			} else if($options['language'] !== true) {
				$language = $this->wire('languages')->get($options['language']);
			}
			if($language && $language->id) $url .= "&language=$language->id";
		}
		$append = $this->wire('session')->getFor($this, 'appendEditUrl'); 
		if($append) $url .= $append;
		return $url;
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
		$sortfield = $this->template ? $this->template->sortfield : '';
		if(!$sortfield) $sortfield = $this->sortfield;
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
		if(!$this->template) return null;
		$this->output = $this->wire(new TemplateFile());
		$this->output->setThrowExceptions(false); 
		$this->output->setFilename($this->template->filename); 
		$fuel = $this->wire('fuel')->getArray();
		$this->output->set('wire', $this->wire()); 
		foreach($fuel as $key => $value) $this->output->set($key, $value); 
		$this->output->set('page', $this); 
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
	 * This method expects that there is a file in `/site/templates/fields/` to render the field with:
	 * 
	 * - `/site/templates/fields/fieldName.php`
	 * - `/site/templates/fields/fieldName.templateName.php`
	 * - `/site/templates/fields/fieldName/$file.php` (using $file argument)
	 * - `/site/templates/fields/$file.php` (using $file argument)
	 * - `/site/templates/fields/$file/fieldName.php` (using $file argument, must have trailing slash)
	 * - `/site/templates/fields/$file.fieldName.php` (using $file argument, must have trailing period)
	 * 
	 * Note that the examples above showing $file require that the `$file` argument is specified. 
	 * 
	 * ~~~~~
	 * // Render output for the 'images' field (assumes you have implemented an output file)
	 * echo $page->renderField('images');
	 * ~~~~~
	 * 
	 * #pw-group-output-rendering
	 * 
	 * @param string $fieldName May be any custom field name or native page property.
	 * @param string $file Optionally specify file (in site/templates/fields/) to render with (may omit .php extension).
	 * @param mixed|null $value Optionally specify value to render, otherwise it will be pulled from this $page. 
	 * @return mixed|string Returns the rendered value of the field
	 * @see Page::render(), Page::renderValue()
	 * 
	 */
	public function ___renderField($fieldName, $file = '', $value = null) {
		/** @var PageRender $pageRender */
		$pageRender = $this->wire('modules')->get('PageRender');
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
		$of = $this->of();
		if($of) $this->of(false);
		if($this->template) {
			if(is_array($fieldName) && !ctype_digit(implode('', array_keys($fieldName)))) {
				// fieldName is an associative array of options for Fieldgroup::getPageInputfields
				$wrapper = $this->template->fieldgroup->getPageInputfields($this, $fieldName);
			} else {
				$wrapper = $this->template->fieldgroup->getPageInputfields($this, '', $fieldName);
			}
		} else {
			$wrapper = null;
		}
		if($of) $this->of(true);
		return $wrapper;
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
		$inputfields = $this->getInputfields($fieldName);
		if($inputfields) {
			$field = $this->wire('fields')->get($fieldName);
			if($field && $field instanceof FieldtypeFieldsetOpen) {
				// requested field name is a fieldset, returns InputfieldWrapper
				return $inputfields;
			} else {
				// requested field name is a single field, return Inputfield
				return $inputfields->children()->first();
			}
		} else {
			// requested field name is not applicable to this page
			return null;
		}
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
	 * #pw-group-common
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
		if(is_string($status) && isset(self::$statuses[$status])) $status = self::$statuses[$status]; 
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
		if(is_string($statusFlag) && isset(self::$statuses[$statusFlag])) $statusFlag = self::$statuses[$statusFlag]; 
		$statusFlag = (int) $statusFlag; 
		$this->setStatus($this->status | $statusFlag); 
		return $this;
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
		if(is_string($statusFlag) && isset(self::$statuses[$statusFlag])) $statusFlag = self::$statuses[$statusFlag]; 
		$statusFlag = (int) $statusFlag; 
		$override = $this->settings['status'] & Page::statusSystemOverride; 
		if($statusFlag == Page::statusSystem || $statusFlag == Page::statusSystemID) {
			if(!$override) throw new WireException(
				"You may not remove the 'system' status from a page unless it also has system override " . 
				"status (Page::statusSystemOverride)"
			); 
		}
		$this->status = $this->status & ~$statusFlag; 
		return $this;
	}
	
	/**
	 * Given a selector, return whether or not this Page matches it
	 *
	 * ~~~~~
	 * if($page->matches("created>=" . strtotime("today"))) {
	 *   echo "This page was created today";
	 * }
	 * ~~~~~
	 * 
	 * @param string|Selectors|array $selector Selector to compare against (string, Selectors object, or array).
	 * @return bool Returns true if this page matches, or false if it doesn't. 
	 *
	 */
	public function matches($selector) {
		// This method implements the WireMatchable interface
		return $this->comparison()->matches($this, $selector);
	}

	/**
	 * Does this page have the specified status number or template name?
	 *
	 * See status flag constants at top of Page class.
	 * You may also use status names: hidden, locked, unpublished, system, systemID
	 * 
	 * #pw-internal
	 *
	 * @param int|string|Selectors $status Status number, status name, or Template name or selector string/object
	 * @return bool
	 *
	 */
	public function is($status) {
		if(is_string($status) && isset(self::$statuses[$status])) $status = self::$statuses[$status]; 
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
		if($fieldName) return parent::get($fieldName) !== null;
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
		$trashPageID = $this->wire('config')->trashPageID; 
		if($this->id == $trashPageID) return true; 
		// this is so that isTrash() still returns the correct result, even if the page was just trashed and not yet saved
		foreach($this->parents() as $parent) if($parent->id == $trashPageID) return true; 
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
		return $this->wire('hooks')->isHooked('Page::isPublic()') ? $this->__call('isPublic', array()) : $this->___isPublic();
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
		if(!is_bool($value)) {
			$this->setStatus($value);
			return $this;
		}
		if(is_null($status)) $status = $this->status; 
		if($value === false) return $status; 
		$names = array();
		$remainder = $status;
		foreach(self::$statuses as $name => $value) {
			if($value <= self::statusOn || $value >= self::statusMax) continue;
			if($status & $value) {
				$names[$value] = $name;
				$remainder = $remainder & ~$value;
			}
		}
		if($remainder > 1) $names[$remainder] = "unknown-$remainder";
		return $names; 
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
	 * @return $this
	 *
	 */
	public function setIsLoaded($isLoaded) {
		$isLoaded = !$isLoaded || $isLoaded === 'false' ? false : true; 
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
		
		$template = $this->template;
		if(!$template) return;
		$fieldgroup = $template->fieldgroup;
		if(!$fieldgroup) return;

		foreach($this->fieldDataQueue as $key => $value) {

			$field = $fieldgroup->get($key); 
			if(!$field) continue;

			// check for autojoin multi fields, which may have multiple values bundled into one string
			// as a result of an sql group_concat() function
			if($field->type instanceof FieldtypeMulti && ($field->flags & Field::flagAutojoin)) {
				foreach($value as $k => $v) {	
					if(is_string($v) && strpos($v, FieldtypeMulti::multiValueSeparator) !== false) {
						$value[$k] = explode(FieldtypeMulti::multiValueSeparator, $v); 	
					}
				}
			}

			// if all there is in the array is 'data', then we make that the value rather than keeping an array
			// this is so that Fieldtypes that only need to interact with a single value don't have to receive an array of data
			if(count($value) == 1 && array_key_exists('data', $value)) $value = $value['data']; 

			$this->setFieldValue($key, $value, false);
		}
		$this->fieldDataQueue = array(); // empty it out, no longer needed
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
	 * have it off. 
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
	 * #pw-group-advanced
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
	 * Prepare the page and it's fields for removal from runtime memory, called primarily by Pages::uncache()
	 * 
	 * #pw-internal
	 *
	 */
	public function uncache() {
		$trackChanges = $this->trackChanges();
		if($trackChanges) $this->setTrackChanges(false); 
		if($this->template) {
			foreach($this->template->fieldgroup as $field) {
				$value = parent::get($field->name);
				if($value != null && is_object($value)) {
					if(method_exists($value, 'uncache') && $value !== $this) $value->uncache(); 
					parent::set($field->name, null); 
					if(isset($this->wakeupNameQueue[$field->name])) unset($this->wakeupNameQueue[$field->name]); 
				}
			}
		}
		if($this->filesManager) $this->filesManager->uncache(); 
		$this->filesManager = null;
		if($trackChanges) $this->setTrackChanges(true); 
	}

	/**
	 * Ensures that isset() and empty() work for this classes properties. 
	 *
	 * @param string $key
	 * @return bool
	 *
	 */
	public function __isset($key) {
		if($this->isLoaded) {
			return $this->get($key) !== null;
		} else {
			if(isset(self::$baseProperties[$key])) return true;
			if(isset(self::$basePropertiesAlternates[$key])) return true;
			if($this->hasField($key)) return true;
			return false;
		}
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
	 * Export the page's data to an array
	 * 
	 * @return array
	 * 
	public function ___export() {
		$exporter = new PageExport();
		return $exporter->export($this);
	}
	 */

	/**
	 * Export the page's data from an array
	 *
	 * @param array $data Data to import, in the format from the export() function
	 * @return $this
	 *
	public function ___import(array $data) {
		$importer = new PageExport();
		return $importer->import($this, $data); 
	}
	 */

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
		
		$isEqual = $value1 === $value2;
		
		if(!$isEqual && $value1 instanceof WireArray && $value2 instanceof WireArray) {
			// ask WireArray to compare itself to another
			$isEqual = $value1->isIdentical($value2, true);
		}
		
		if($isEqual) {
			if(is_object($value1) && $value1 instanceof Wire && ($value1->isChanged() || $value2->isChanged())) {
				$this->trackChange($key, $value1, $value2);
			}
		}
		
		return $isEqual;
	}

	/**
	 * Return a Page helper class instance that's common among all Page objects in this ProcessWire instance
	 * 
	 * @param $className
	 * @return object|PageComparison|PageAccess|PageTraversal
	 * 
	 */
	protected function getHelperInstance($className) {
		static $helpers = array();
		$instanceID = $this->wire()->getProcessWireInstanceID();
		if(!isset($helpers[$instanceID])) {
			$helpers[$instanceID] = array();
		}
		if(!isset($helpers[$instanceID][$className])) {
			$nsClassName = __NAMESPACE__ . "\\$className";
			$helper = new $nsClassName();
			if($helper instanceof WireFuelable) $this->wire($helper);
			$helpers[$instanceID][$className] = $helper;
		} else {
			$helper = $helpers[$instanceID][$className];
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
		return self::$statuses;
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
	 * @todo add recognized page icon field to core
	 * 
	 * @return string
	 * 
	 */
	public function ___getIcon() {
		if(!$this->template) return '';
		if($this->hasField('process')) {
			$process = $this->getUnformatted('process'); 
			if($process) {
				$info = $this->wire('modules')->getModuleInfoVerbose($process);
				if(!empty($info['icon'])) return $info['icon'];
			}
		}
		return $this->template->getIcon();
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
		return $this->wire('pages');
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
		
		if(is_null($lazy)) {
			// return current state
			return $this->lazyLoad;
			
		} else if(is_int($lazy)) {
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
			$pages = $this->wire('pages');
			$page = $pages->getById($this->id, array(
				'cache' => false,
				'getOne' => true,
				'page' => $this // This. Just This.
			));
			if(!$page->id) return false;
			return true;
			
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
		if(empty($method)) return $this->wire('pages');
		if(!isset($options['cache'])) $options['cache'] = $this->loaderCache;
		if(!isset($options['caller'])) $options['caller'] = "page._pages.$method";
		$result = $this->wire('pages')->$method($selector, $options);
		return $result;
	}

	/*
	public function remove($key) {
		parent::remove($key);
		if(isset($this->data[$key])) {
			$a = parent::get('_statusCorruptedFields');
			if(!is_array($a)) $a = array();
			$k = array_search($key, $a);
			if($k !== false) {
				unset($a[$k]); 
				if(empty($a)) $this->removeStatus(self::statusCorrupted);
				parent::set('_statusCorruptedFields', $a);
			}
		}
		return $this;
	}
	*/
	
}

