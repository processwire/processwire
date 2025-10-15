<?php namespace ProcessWire;

/**
 * ProcessWire Pages Request
 *
 * #pw-headline Pages Request
 * #pw-var $pages->request
 * #pw-breadcrumb Pages
 * #pw-summary Methods for identifying and loading page from current request URL.
 * #pw-body =
 * Methods in this class should be accessed from `$pages->request()`, i.e. 
 * ~~~~~
 * $page = $pages->request()->getPage();
 * ~~~~~
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 * 
 * @method Page|NullPage getPage()
 * @method Page|null getPageForUser(Page $page, User $user)
 * @method Page|NullPage getClosestPage()
 * @method Page|string getLoginPageOrUrl(Page $page)
 *
 */

class PagesRequest extends Wire {

	/**
	 * @var Pages
	 *
	 */
	protected $pages;

	/**
	 * @var Config
	 * 
	 */
	protected $config;

	/**
	 * @var Page|null|bool Page when found, NullPage when 404, null when not yet known
	 *
	 */
	protected $page = null;

	/**
	 * Closest page to one requested, when getPage() didn’t resolve
	 * 
	 * @var null|Page
	 * 
	 */
	protected $closestPage = null;

	/**
	 * Page that access was requested to and denied
	 * 
	 * @var Page|null
	 * 
	 */
	protected $requestPage = null;

	/**
	 * @var array
	 * 
	 */
	protected $pageInfo = array();

	/**
	 * Sanitized path that generated this request
	 *
	 * Set by the getPage() method and passed to the pageNotFound function.
	 *
	 */
	protected $requestPath = '';

	/**
	 * Processed request path (for identifying if setRequestPath called manually)
	 * 
	 * @var string
	 * 
	 */
	protected $processedPath = '';

	/**
	 * Unsanitized URL from $_SERVER['REQUEST_URI']
	 *
	 * @var string 
	 *
	 */
	protected $dirtyUrl = '';

	/**
	 * Requested filename, if URL in /path/to/page/-/filename.ext format
	 *
	 */
	protected $requestFile = '';

	/**
	 * Page number found in the URL or null if not found
	 *
	 */
	protected $pageNum = null;

	/**
	 * Page number prefix found in the URL or null if not found
	 *
	 */
	protected $pageNumPrefix = null;
	
	/**
	 * Response type codes to response type names
	 *
	 * @var array
	 *
	 */
	protected $responseCodeNames = array(
		0 => 'unknown',
		200 => 'ok',
		300 => 'maybeRedirect',
		301 => 'permRedirect',
		302 => 'tempRedirect',
		307 => 'tempRedo',
		308 => 'permRedo',
		400 => 'badRequest',
		401 => 'unauthorized',
		403 => 'forbidden',
		404 => 'pageNotFound',
		405 => 'methodNotAllowed',
		414 => 'pathTooLong',
	);

	/**
	 * Response http code
	 * 
	 * @var int
	 * 
	 */
	protected $responseCode = 0;
	
	/**
	 * URL that should be redirected to for this request
	 *
	 * Set by other methods in this class, and checked by the execute method before rendering.
	 *
	 */
	protected $redirectUrl = '';

	/**
	 * @var int 301 or 302
	 * 
	 */
	protected $redirectType = 0;

	/**
	 * @var string
	 * 
	 */
	protected $languageName = '';

	/**
	 * Previous value of $_GET[it] when used for something non PW
	 * 
	 * @var mixed
	 * 
	 */
	protected $prevGetIt = null;

	/**
	 * Optional message provided to setResponseCode() with additional detail
	 * 
	 * @var string
	 * 
	 */
	protected $responseMessage = '';
	
	/*************************************************************************************/

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
		$this->config = $pages->wire()->config;
		$this->init();
	}

	/**
	 * Initialize
	 * 
	 */
	protected function init() {
		
		$dirtyUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

		if(!strlen($dirtyUrl) && !empty($_SERVER['QUERY_STRING'])) {
			if(strlen($_SERVER['QUERY_STRING']) < 4096) {
				$dirtyUrl = '?' . $_SERVER['QUERY_STRING'];
			}
		}
		
		$this->dirtyUrl = $dirtyUrl;
	
		if(!isset($_GET['it'])) return;
	
		// check if there is an 'it' var present in the request query string, which we don’t want
		if((strpos($dirtyUrl, '?it=') !== false || strpos($dirtyUrl, '&it='))) {
			// the request URL included a user-inserted 'it' variable in query string
			// force to use path in request url rather than contents of 'it' var
			list($it, /*query-string*/) = explode('?', $dirtyUrl, 2);
			$rootUrl = $this->config->urls->root;
			if(strlen($rootUrl) > 1) {
				// root url is a subdirectory, like /pwsite/
				if(strpos($it, $rootUrl) === 0) {
					// url begins with that subdirectory, like /pwsite/
					// convert '/pwsite/path/to/page/' to just '/path/to/page/'
					$it = substr($it, strlen($rootUrl) - 1);
				} else if(strpos(ltrim($it, '/'), ltrim($rootUrl, '/')) === 0) {
					$it = substr(ltrim($it, '/'), strlen(ltrim($rootUrl, '/')));
				}
			}
			$it = str_ireplace('index.php', '', $it);
			$this->prevGetIt = $_GET['it'];
			$_GET['it'] = $it;
		}
	}

	/**
	 * Set current request page
	 * 
	 * @param Page|NullPage|null $page
	 * @return Page|NullPage|null
	 * 
	 */
	public function setPage($page) {
		$this->page = $page;
		$this->dirtyUrl = '';
		$input = $this->wire()->input;
		if($this->prevGetIt === null) {
			unset($_GET['it']);
			$input->get->offsetUnset('it');
		} else {
			$_GET['it'] = $this->prevGetIt;
			$input->get->offsetSet('it', $this->prevGetIt);
			$this->prevGetIt = null;
		}
		return $page;
	}
	
	/**
	 * Get the requested page 
	 * 
	 * - Populates identified urlSegments or page numbers to $input.
	 * - Returns NullPage for error, call getResponseCode() and/or getResponseMessage() for details.
	 * - Returned page should be validated with getPageForUser() method before rendering it. 
	 * - Call getFile() method afterwards to see if request resolved to file managed by returned page.
	 * 
	 * @param array $options
	 * @return Page|NullPage
	 *
	 */
	public function ___getPage(array $options = array()) {
		
		$defaults = array(
			'verbose' => false,
			'useHistory' => false, // disabled because redundant with hook in PagePathHistory module
			'useExcludeRoot' => false,
		);
		
		$options = empty($options) ? $defaults : array_merge($defaults, $options);

		// perform this work only once unless reset by setPage or setRequestPath
		if($this->page && $this->requestPath === $this->processedPath) return $this->page;

		$input = $this->wire()->input;
		$languages = $this->wire()->languages;

		// get the requested path
		$path = $this->getRequestPath();
		
		// request path is not one ProcessWire is allowed to handle
		if($path === false) return $this->setPage($this->pages->newNullPage());

		// SECURE-PAGEFILES: check if request is for a secure pagefile
		if($this->pagefileSecurePossibleUrl($path)) {
			// get Page (success), NullPage (404), false (no file present), true (file present old method)
			$page = $this->checkRequestFile($path); // can modify $path directly
			if(is_object($page)) {
				// Page (success) or NullPage (404)
				$this->setResponseCode($page->id ? 200 : 404, 'Secure pagefile request');
				return $this->setPage($page);
			} else if($page === false) {
				// $path is unrelated to /site/assets/files/
			} else if($page === true) {
				// $path was to a file using config.pageFileUrlPrefix prefix method
				// $this->requestFile is populated and $path is now updated to be
				// the page path without the filename in it
			}
		}
		
		// populate request path to class as other methods will now use it
		$this->setRequestPath($path);

		// determine if original URL had anything filtered out of path that will suggest a redirect
		list($dirtyUrl,) = explode('?', "$this->dirtyUrl?", 2); // exclude query string
		if(stripos($dirtyUrl, 'index.php') !== false && stripos($path, 'index.php') === false) {
			// force pathFinder to detect a redirect condition without index.php
			$dirtyUrl = strtolower(rtrim($dirtyUrl, '/'));
			if(substr("/$dirtyUrl", -10) === '/index.php') $path = rtrim($path, '/') . '/index.php';
		} else if(strpos($dirtyUrl, '//') !== false) {
			// force pathFinder to detect redirect sans double slashes, /page/path// => /page/path/
			$path = rtrim($path, '/') . '//';
		}
		
		// get info about requested path
		$info = $this->pages->pathFinder()->get($path, $options);
		$pageId = $info['page']['id'];
		$this->pageInfo = &$info;
		$this->languageName = $info['language']['name'];
		$this->setResponseCode($info['response']);
	
		// URL segments
		if(count($info['urlSegments'])) {
			$input->setUrlSegments($info['urlSegments']); 
		}

		// pagination numbers
		if($info['pageNum'] > 1) {
			$input->setPageNum($info['pageNum']);
			$this->pageNum = $info['pageNum'];
			$this->pageNumPrefix = $info['pageNumPrefix'];
		}
		
		// check if we have matched a page
		if($pageId) {
			$page = $this->pages->getOneById($pageId, array(
				'template' => $info['page']['templates_id'],
				'parent_id' => $info['page']['parent_id'],
			));
		} else {
			$page = $this->pages->newNullPage();
		}
		
		$this->requestPage = $page;
		
		if($page->id) {
			if(!empty($info['urlSegments'])) {
				// the first version of PW populated first URL segment to $page,
				// this undocumented behavior retained for backwards compatibility
				$page->setQuietly('urlSegment', $input->urlSegment1);
			}
			if(!$this->checkRequestMethod($page)) {
				// request method not allowed
				$page = $this->pages->newNullPage();
			}
		} else if($this->responseCode < 300) {
			// no page ID found but got success code (this should not be possible)
			$this->setResponseCode(404);
		}
		
		if($this->responseCode === 300) {
			// 300 “maybe” redirect: page not available in requested language
			if($languages && $languages->hasPageNames()) {
				$language = $languages->get($info['language']['name']); 
				$result = $languages->pageNames()->pageNotAvailableInLanguage($page, $language);
				if(is_array($result)) {
					// array returned where index 0=301|302, 1=redirect URL
					$this->setResponseCode($result[0]);
					$this->setRedirectUrl($result[1], $result[0]); 
				} else if(is_bool($result)) {
					// bool returned where true=200 (render anyway), false=404 (fail)
					$this->setResponseCode($result ? 200 : 404);
				}
			} else if(!empty($info['redirect'])) {
				$this->setResponseCode(301);
			}
		}

		// check for redirect
		if(empty($this->redirectUrl) && $this->responseCode >= 300 && $this->responseCode < 400) {
			// 301 permRedirect, 302 tempRedirect, 307 or 308
			$this->setRedirectPath($info['redirect'], $info['response']);
		}
		
		// check for error 
		if($this->responseCode >= 400) {
			// 400 badRequest, 401 unauthorized, 403 forbidden,
			// 404 pageNotFound, 405 methodNotallowed, 414 pathTooLong
			if(!empty($info['redirect'])) {
				// pathFinder suggests a redirect may still be possible
				// currently not implemented
			} 
			if($page->id) {
				// if a page was found but with an error code then set the
				// closestPage property for optional later inspection
				$this->closestPage = $page;
			}
			$page = $this->pages->newNullPage();
		}
			
		if($page->id) $this->setPage($page);
	
		return $page;
	}

	/**
	 * Get array of page info (as provided by PagePathFinder)
	 * 
	 * See the PagesPathFinder::get() method return value for a description of 
	 * what this method returns.
	 * 
	 * If this method returns a blank array, it means that the getPage()
	 * method has not yet been called or that it did not match a page. 
	 * 
	 * @return array
	 * @since 3.0.242
	 * 
	 */
	public function getPageInfo() {
		return $this->pageInfo;
	}
	
	/**
	 * Update/get page for given user
	 * 
	 * Must be called once the current $user is known as it may change the $page.
	 * Returns NullPage if user lacks access or page out of bounds.
	 * Returns different page if it should be substituted due to lack of access (like login page). 
	 *
	 * @param Page $page
	 * @param User $user
	 * @return Page|NullPage
	 *
	 */
	public function ___getPageForUser(Page $page, User $user) {

		$config = $this->config;
		$isGuest = $user->isGuest();

		// if no page found for guest user, check if path was in admin and map to admin root
		if(!$page->id && $isGuest) {
			// this ensures that no admin requests resolve to a 404 and instead show login form
			$adminPath = substr($config->urls->admin, strlen($config->urls->root) - 1);
			if(strpos($this->requestPath, $adminPath) === 0) {
				$page = $this->pages->get($config->adminRootPageID);
				$this->redirectUrl = '';
			}
		}
		
		$requestPage = $page;

		// enforce max pagination number when user is not logged in
		$pageNum = $this->wire()->input->pageNum();
		if($pageNum > 1 && $page->id && $isGuest) {
			$maxPageNum = $config->maxPageNum;
			if(!$maxPageNum) $maxPageNum = 999;
			if($this->pageNum > $maxPageNum) {
				$page = $this->pages->newNullPage();
			}
		}
		
		if($page->id) {
			$page = $this->checkAccess($page, $user);
			if(is_string($page)) {
				// redirect URL
				if(strlen($page)) $this->setRedirectUrl($page, 302);
				$page = $this->pages->newNullPage();
			} else if(!$page || !$page->id) {
				// 404
				$page = $this->pages->newNullPage();
			} else {
				// login Page or Page to render
			}
			if($page && $page->id) {
				// access allowed
			} else if($user->isLoggedin()) {
				$this->setResponseCode(403, 'Authenticated user lacks access');
			} else {
				$this->setResponseCode(401, 'User must login for access');
			}
		}

		if($page->id) {
			$this->checkScheme($page);
			$this->setPage($page);
			$page->of(true);
		}
	
		// if $page was changed as a result of above remember the requested one
		if($requestPage->id != $page->id) {
			$this->requestPage = $requestPage;
		}
		
		return $page;
	}

	/**
	 * Get closest matching page when getPage() returns an error/NullPage
	 * 
	 * This is useful for a 404 page to suggest if maybe the user intended a different page 
	 * and give them a link to it. For instance, you might have the following code in the 
	 * template file used by your 404 page:
	 * ~~~~~
	 * echo "<h1>404 Page Not Found</h1>";
	 * $p = $pages->request()->getClosestPage();
	 * if($p->id) {
	 *   echo "<p>Are you looking for <a href='$p->url'>$p->title</a>?</p>";
	 * }
	 * ~~~~~
	 * 
	 * @return Page|NullPage
	 * 
	 */
	public function ___getClosestPage() {
		return $this->closestPage ? $this->closestPage : $this->pages->newNullPage();
	}

	/**
	 * Get page that was requested
	 * 
	 * If this is different from the Page returned by getPageForUser() then it would
	 * represent the page that the user lacked access to. 
	 * 
	 * @return NullPage|Page
	 * 
	 */
	public function getRequestPage() {
		if($this->requestPage) return $this->requestPage;
		$page = $this->getPage();
		if($this->requestPage) return $this->requestPage; // duplication from above intentional
		return $page;
	}

	/**
	 * Get the requested path
	 *
	 * @return bool|string Return false on fail, path on success
	 *
	 */
	protected function getRequestPagePath() {
		
		$config = $this->config;
		$sanitizer = $this->wire()->sanitizer;
		
		/** @var string $shit Dirty URL */
		/** @var string $it Clean URL */

		if(isset($_GET['it'])) {
			// normal request
			$shit = trim($_GET['it']);

		} else if(isset($_SERVER['REQUEST_URI'])) {
			// abnormal request, something about request URL made .htaccess skip it, or index.php called directly
			$rootUrl = $config->urls->root;
			$shit = trim($_SERVER['REQUEST_URI']);
			if(strpos($shit, '?') !== false) list($shit,) = explode('?', $shit, 2);
			if($rootUrl != '/') {
				if(strpos($shit, $rootUrl) === 0) {
					// remove root URL from request
					$shit = substr($shit, strlen($rootUrl) - 1);
				} else {
					// request URL outside of our root directory
					$this->setResponseCode(404, 'Request URL outside of our web root');
					return false;
				}
			}
		} else {
			$shit = '/';
		}

		if($shit === '/') {
			$it = '/';
		} else {
			$it = preg_replace('{[^-_./a-zA-Z0-9]}', '', $shit); // clean
		}

		unset($_GET['it']);

		if($shit !== $it) {
			// sanitized URL does not match requested URL
			if($config->pageNameCharset === 'UTF8') {
				// test for extended page name URL
				$it = $sanitizer->pagePathNameUTF8($shit);
			}
			if($shit !== $it) {
				// if still does not match then fail
				$this->setResponseCode(400, 'Request URL contains invalid/unsupported characters');
				return false;
			}
		}

		$maxUrlDepth = $config->maxUrlDepth;
		if($maxUrlDepth > 0 && substr_count($it, '/') > $maxUrlDepth) {
			if(in_array($config->longUrlResponse, [ 302, 301 ])) {
				$parts = array_slice(explode('/', $it), 0, $maxUrlDepth);
				$it = '/' . trim(implode('/', $parts), '/') . '/';
				$this->setRedirectPath($it, $config->longUrlResponse);
			} else {
				$this->setResponseCode($config->longUrlResponse, 'Request URL exceeds max depth set in $config->maxUrlDepth');
				return false;
			}
		}

		if(!isset($it[0]) || $it[0] != '/') $it = "/$it";
		
		if(strpos($it, '//') !== false) {
			$this->setResponseCode(400, 'Request URL contains a blank segment “//”');
			return false;
		}
		
		$this->requestPath = $it;
		$this->processedPath = $it;

		return $it;
	}

	/**
	 * Check if the requested path is to a secured page file
	 *
	 * - This function sets $this->requestFile when it finds one.
	 * - Returns Page when a pagefile was found and matched to a page.
	 * - Returns NullPage when request should result in a 404.
	 * - Returns true and updates $path when pagefile was found using deprecated prefix method.
	 * - Returns false when none found.
	 *
	 * @param string $path Request path
	 * @return bool|Page|NullPage
	 *
	 */
	protected function checkRequestFile(&$path) {

		$config = $this->config;
		$pages = $this->wire()->pages;

		// request with url to root (applies only if site runs from subdirectory)
		$url = rtrim($config->urls->root, '/') . $path;

		// check for secured filename, method 1: actual file URL, minus leading "." or "-"
		if(strpos($url, $config->urls->files) !== 0) {
			// if URL is not to files, check if it might be using legacy prefix
			if($config->pagefileUrlPrefix) return $this->checkRequestFilePrefix($path);
			// request is not for a file
			return false;
		}
		
		// request is for file in site/assets/files/...
		$idAndFile = substr($url, strlen($config->urls->files));

		// matching in $idAndFile: 1234/file.jpg, 1/2/3/4/file.jpg, 1234/subdir/file.jpg, 1/2/3/4/subdir/file.jpg, etc. 
		$regex = '{^(\d[\d\/]*)/([-_a-zA-Z0-9][-_./a-zA-Z0-9]+)$}';
		if(!preg_match($regex, $idAndFile, $matches) && strpos($matches[2], '.')) {
			// request was to something in /site/assets/files/ but we don't recognize it
			// tell caller that this should be a 404
			return $pages->newNullPage();
		}
		
		// request is consistent with those that would match to a file
		$idPath = trim($matches[1], '/');
		$file = trim($matches[2], '.');

		if(!strpos($file, '.')) return $pages->newNullPage();

		if(!ctype_digit("$idPath")) {
			// extended paths where id separated by slashes, i.e. 1/2/3/4
			if($config->pagefileExtendedPaths) {
				// allow extended paths
				$idPath = str_replace('/', '', $matches[1]);
				if(!ctype_digit("$idPath")) return $pages->newNullPage();
			} else {
				// extended paths not allowed
				return $pages->newNullPage();
			}
		}

		if(strpos($file, '/') !== false) {
			// file in subdirectory (for instance ProDrafts uses subdirectories for draft files)
			list($subdir, $file) = explode('/', $file, 2);

			if(strpos($file, '/') !== false) {
				// there is more than one subdirectory, which we do not allow
				return $pages->newNullPage();

			} else if(strpos($subdir, '.') !== false || strlen($subdir) > 128) {
				// subdirectory has a "." in it or subdir length is too long
				return $pages->newNullPage();

			} else if(!preg_match('/^[a-zA-Z0-9][-_a-zA-Z0-9]+$/', $subdir)) {
				// subdirectory not in expected format  
				return $pages->newNullPage();
			}

			$file = trim($file, '.');
			$this->requestFile = "$subdir/$file";

		} else {
			// file without subdirectory
			$this->requestFile = $file;
		}

		return $pages->get((int) $idPath); // Page or NullPage
	}

	/**
	 * Check for secured filename: method 2 (deprecated)
	 * 
	 * Used only if $config->pagefileUrlPrefix is defined
	 * 
	 * @param string $path
	 * @return bool
	 * 
	 */
	protected function checkRequestFilePrefix(&$path) {
		$filePrefix = $this->wire()->config->pagefileUrlPrefix;
		if(empty($filePrefix)) return false;
		if(!strpos($path, '/' . $filePrefix)) return false;
		$regex = '{^(.*/)' . $filePrefix . '([-_.a-zA-Z0-9]+)$}';
		if(!preg_match($regex, $path, $matches)) return false; 
		if(!strpos($matches[2], '.')) return false;
		$path = $matches[1];
		$this->requestFile = $matches[2];
		return true;
	}

	/**
	 * Get login Page object or URL to redirect to for login needed to access given $page
	 * 
	 * - When a Page is returned, it is suggested the Page be rendered in this request.
	 * - When a string/URL is returned, it is suggested you redirect to it. 
	 * - When null is returned no login page or URL could be identified and 404 should render.
	 * 
	 * @param Page|null $page Page that access was requested to or omit to get admin login page
	 * @return string|Page|null Login page object or string w/redirect URL, null if 404
	 * 
	 */
	public function ___getLoginPageOrUrl(?Page $page = null) {
		
		$config = $this->wire()->config;

		// if no $page given return default login page
		if($page === null) return $this->pages->get((int) $config->loginPageID);
	
		// if NullPage given return URL to default login page
		if(!$page->id) return $this->pages->get((int) $config->loginPageID)->httpUrl();
	
		// if given page is one that cannot be accessed regardless of login return null
		if($page->id === $config->trashPageID) return null;

		// get redirectLogin setting from the template
		$accessTemplate = $page->getAccessTemplate();
		$redirectLogin = $accessTemplate ? $accessTemplate->redirectLogin : false;
		
		if(empty($redirectLogin)) {
			// no setting for template.redirectLogin means 404 
			return null;

		} else if(ctype_digit("$redirectLogin")) {
			// Page ID provided in template.redirectLogin
			$loginID = (int) $redirectLogin;
			if($loginID < 2) $loginID = (int) $config->loginPageID;
			$loginPage = $this->pages->get($loginID);
			if(!$loginPage->id && $loginID != $config->loginPageID) {
				$loginPage = $this->pages->get($config->loginPageID);
			}
			if(!$loginPage->id) $loginPage = null;
			return $loginPage;
			
		} else if(strlen($redirectLogin)) {
			// redirect URL provided in template.redirectLogin
			$redirectUrl = str_replace('{id}', $page->id, $redirectLogin);
			list($path, $query) = array($redirectUrl, '');
			if(strpos($redirectUrl, '?') !== false) list($path, $query) = explode('?', $redirectUrl, 2);
			if(strlen($path) && strpos($path, '/') === 0 && strpos($path, '//') === false) {
				// attempt to match to page so we can use URL with scheme and relative to installation url
				$p = $this->wire()->pages->get($path);
				if($p->id && $p->viewable()) {
					$redirectUrl = $p->httpUrl() . ($query ? "?$query" : "");
				}
			} else if(strpos($path, '//') === 0 && strpos($path, '://') === false) {
				// double slash at beginning force path without checking if it maps to page
				$redirectUrl = '/' . ltrim($redirectUrl, '/');
			}
			return $redirectUrl;
		}
		
		return null;
	}

	/**
	 * Check that the current user has access to the page and return it
	 *
	 * If the user doesn’t have access, then a login Page or NULL (for 404) is returned instead.
	 * 
	 * @param Page $page
	 * @param User $user
	 * @return Page|string|null Page to render, URL to redirect to, or null for 404
	 * 
	 *
	 */
	protected function checkAccess(Page $page, User $user) {

		if($this->requestFile) {
			// if a file was requested, we still allow view even if page doesn't have template file
			if($page->viewable($this->requestFile) === false) return null;
			if($page->viewable(false)) return $page; // false=viewable without template file check
			if($this->checkAccessDelegated($page)) return $page;
			// below seems to be redundant with the above $page->viewable(false) check
			// if($page->status < Page::statusUnpublished && $user->hasPermission('page-view', $page)) return $page;
			return null;
		} 
		
		if($page->viewable()) {
			// regular page view
			return $page;
		}

		if($page->parent_id && $page->parent->template->name === 'admin' && $page->parent->viewable()) {
			// check for special case in admin when Process::executeSegment() collides with page name underneath
			// example: a role named "edit" is created and collides with ProcessPageType::executeEdit()
			$input = $this->wire()->input;
			if($user->isLoggedin() && $page->editable() && !strlen($input->urlSegmentStr())) {
				$input->setUrlSegment(1, $page->name);
				return $page->parent;
			}
		}
		
		// if we reach this point, page is not viewable
		// get login Page or URL to redirect to for login (Page, string or null)
		$result = $this->getLoginPageOrUrl($page);

		// if we won’t be presenting a login or redirect then return null (404)
		if(empty($result)) return null;

		return $result;
	}

	/**
	 * Check access to a delegated page (like a repeater)
	 *
	 * Note: this should move to PagePermissions.module or FieldtypeRepeater.module
	 * if a similar check is needed somewhere else in the core.
	 *
	 * @param Page $page
	 * @return Page|null|bool
	 *
	 */
	protected function checkAccessDelegated(Page $page) {
		if(strpos($page->template->name, 'repeater_') === 0) {
			return $this->checkAccessRepeater($page);
		}
		return null;
	}

	/**
	 * Check access to a delegated repeater 
	 *
	 * @param Page $page
	 * @return Page|null|bool
	 *
	 */
	protected function checkAccessRepeater(Page $page) {
		
		if(!$this->wire()->modules->isInstalled('FieldtypeRepeater')) return false;
		
		$fieldName = substr($page->template->name, strpos($page->template->name, '_') + 1); // repeater_(fieldName)
		if(!$fieldName) return false;
		
		$field = $this->wire()->fields->get($fieldName);
		if(!$field) return false;
		
		$forPageID = substr($page->parent->name, strrpos($page->parent->name, '-') + 1); // for-page-(id)
		$forPage = $this->pages->get((int) $forPageID);
	
		if(!$forPage->id) return null;
		
		// delegate viewable check to the page the repeater lives on
		if($forPage->viewable($field)) return $page;
		
		if(strpos($forPage->template->name, 'repeater_') === 0) {
			// go recursive for nested repeaters
			$forPage = $this->checkAccessRepeater($forPage);
			if($forPage && $forPage->id) return $forPage;
		}
		
		return null;
	}

	/**
	 * If the template requires a different scheme/protocol than what is here, then redirect to it.
	 *
	 * This method just silently sets the $this->redirectUrl var if a redirect is needed.
	 * Note this does not work if GET vars are present in the URL -- they will be lost in the redirect.
	 *
	 * @param Page $page
	 *
	 */
	public function checkScheme(Page $page) {

		$config = $this->config;
		$input = $this->wire()->input;
		$requireHTTPS = $page->template->https;
		
		if($requireHTTPS == 0 || $config->noHTTPS) return; // neither HTTP or HTTPS required

		$isHTTPS = $config->https;
		$scheme = '';

		if($requireHTTPS == -1 && $isHTTPS) {
			// HTTP required: redirect to HTTP non-secure version
			$scheme = "http";

		} else if($requireHTTPS == 1 && !$isHTTPS) {
			// HTTPS required: redirect to HTTPS secure version
			$scheme = "https";
		}

		if(!$scheme) return;

		if($this->redirectUrl) {
			if(strpos($this->redirectUrl, '://') !== false) {
				$url = str_replace(array('http://', 'https://'), "$scheme://", $this->redirectUrl);
			} else {
				$url = "$scheme://$config->httpHost$this->redirectUrl";
			}
		} else {
			$url = "$scheme://$config->httpHost$page->url";
		}

		if($this->redirectUrl) {
			// existing redirectUrl will already have segments/page numbers as needed
		} else {
			$urlSegmentStr = $input->urlSegmentStr;
			if(strlen($urlSegmentStr) && $page->template->urlSegments) {
				$url = rtrim($url, '/') . '/' . $urlSegmentStr;
				if($page->template->slashUrlSegments) {
					// use defined setting for trailing slash
					if($page->template->slashUrlSegments == 1) $url .= '/';
				} else {
					// use whatever the request came with	
					if(substr($this->requestPath, -1) == '/') $url .= '/';
				}
			}

			$pageNum = (int) $this->pageNum;
			
			if($pageNum > 1 && $page->template->allowPageNum) {
				$prefix = $this->pageNumPrefix ? $this->pageNumPrefix : $config->pageNumUrlPrefix;
				if(!$prefix) $prefix = 'page';
				$url = rtrim($url, '/') . "/$prefix$pageNum";
				if($page->template->slashPageNum) {
					// defined setting for trailing slash	
					if($page->template->slashPageNum == 1) $url .= '/';
				} else {
					// use whatever setting the URL came with
					if(substr($this->requestPath, -1) == '/') $url .= '/';
				}
			}
		}

		$this->setRedirectUrl($url, 301); 
	}

	/**
	 * Check current request method
	 * 
	 * @param Page $page
	 * @return bool True if current request method allowed, false if not
	 * 
	 */
	private function checkRequestMethod(Page $page) {
		// @todo replace static allowMethods array with template setting like below
		// $allowMethods = $page->template->get('requestMethods'); 
		// $allowMethods = array('GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH');
		$allowMethods = array(); // feature disabled until further development
		if(empty($allowMethods)) return true; // all allowed when none selected
		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if(empty($method)) return true;
		if(in_array($method, $allowMethods, true)) return true;
		if($method === 'GET' || $method === 'POST') {
			if($page->template->name === 'admin') return true; 
			if($page->id == $this->wire()->config->http404PageID) return true;
		}
		$this->setResponseCode(405, "Request method $method not allowed by $page->template");
		return false;
	}

	/**
	 * Are secure pagefiles possible on this system and url?
	 *
	 * @param string $url
	 * @return bool
	 * @since 3.0.166
	 *
	 */
	protected function pagefileSecurePossibleUrl($url) {
		$config = $this->config;

		// if URL does not start from root, prepend root
		if(strpos($url, $config->urls->root) !== 0) $url = $config->urls->root . ltrim($url, '/');

		// if URL is not pointing to the files structure, then this is not a files URL
		if(strpos($url, $config->urls->files) !== 0) return false;

		// pagefileSecure option is enabled and URL pointing to files
		if($config->pagefileSecure) return true;

		// check if any templates allow pagefileSecure option
		$allow = false;
		foreach($this->wire()->templates as $template) {
			if(!$template->pagefileSecure) continue;
			$allow = true;
			break;
		}

		// if at least one template supports pagefileSecure option we will return true here
		return $allow;
	}

	/**
	 * Set response code and type
	 * 
	 * @param int $code
	 * @param string $message Optional message string
	 * 
	 */
	protected function setResponseCode($code, $message = '') {
		$this->responseCode = (int) $code;
		if($message) $this->responseMessage = $message;
	}

	/**
	 * Get all possible response code names indexed by http response code
	 *
	 * @return array
	 *
	 */
	public function getResponseCodeNames() {
		return $this->responseCodeNames;
	}

	/**
	 * Get response type name for this request
	 * 
	 * Returns string, one of:
	 * 
	 * - unknown: request not yet analyzed (0)
	 * - ok: successful request (200)
	 * - fileOk: successful file request (200) 
	 * - fileNotFound: requested file not found (404)
	 * - maybeRedirect: needs decision about whether to redirect (300)
	 * - permRedirect: permanent redirect (301)
	 * - tempRedirect: temporary redirect (302)
	 * - tempRedo: temporary redirect and redo using same method (307)
	 * - permRedo: permanent redirect and redo using same method (308)
	 * - badRequest: bad request/page path error (400)
	 * - unauthorized: login required (401)
	 * - forbidden: authenticated user lacks access (403)
	 * - pageNotFound: page not found (404)
	 * - methodNotAllowed: request method is not allowed by template (405)
	 * - pathTooLong: path too long or segment too long (414)
	 * 
	 * @return string
	 * 
	 */
	public function getResponseCodeName() {
		return $this->responseCodeNames[$this->responseCode];
	}
	
	/**
	 * Get response http code for this request
	 * 
	 * Returns integer, one of:
	 * 
	 * - 0: unknown/request not yet analyzed
	 * - 200: successful request
	 * - 300: maybe redirect (needs decision)
	 * - 301: permanent redirect
	 * - 302: temporary redirect
	 * - 307: temporary redirect and redo using same method
	 * - 308: permanent redirect and redo using same method
	 * - 400: bad request/page path error
	 * - 401: unauthorized/login required
	 * - 403: forbidden/authenticated user lacks access
	 * - 404: page not found
	 * - 405: method not allowed
	 * - 414: request path too long or segment too long
	 *
	 * @return int
	 *
	 */
	public function getResponseCode() {
		return $this->responseCode;
	}

	/**
	 * Set request path
	 * 
	 * @param string $requestPath
	 * 
	 */
	public function setRequestPath($requestPath) {
		$this->requestPath = $requestPath;
	}

	/**
	 * Get request path
	 * 
	 * @return string
	 * 
	 */
	public function getRequestPath() {
		if(empty($this->requestPath)) $this->requestPath = $this->getRequestPagePath();
		return $this->requestPath;
	}

	/**
	 * Get request language name
	 * 
	 * @return string
	 * 
	 */
	public function getLanguageName() {
		return $this->languageName;
	}

	/**
	 * Set the redirect path
	 * 
	 * @param string $redirectPath
	 * @param int $type 301 or 302
	 * 
	 */
	public function setRedirectPath($redirectPath, $type = 301) {
		$this->redirectUrl = $this->wire()->config->urls->root . ltrim($redirectPath, '/');
		$this->redirectType = (int) $type;
	}

	/**
	 * Set the redirect URL
	 * 
	 * @param string $redirectUrl
	 * @param int $type
	 * 
	 */
	public function setRedirectUrl($redirectUrl, $type = 301) {
		$this->redirectUrl = $redirectUrl;
		$this->redirectType = (int) $type;
	}

	/**
	 * Get the redirect URL
	 * 
	 * @return string
	 * 
	 */
	public function getRedirectUrl() {
		return $this->redirectUrl;
	}

	/**
	 * Get the redirect type (0, 301, 302, 307, 308)
	 * 
	 * @return int
	 * 
	 */
	public function getRedirectType() {
		return $this->redirectType === 300 ? 301 : $this->redirectType;
	}

	/**
	 * Get the requested pagination number
	 * 
	 * @return null|int
	 * 
	 */
	public function getPageNum() {
		return $this->pageNum;
	}

	/**
	 * Get the requested pagination number prefix
	 * 
	 * @return null|string
	 * 
	 */
	public function getPageNumPrefix() {
		return $this->pageNumPrefix;
	}
	
	/**
	 * Get the requested file
	 * 
	 * @return string
	 * 
	 */
	public function getFile() {
		return $this->requestFile;
	}

	/**
	 * Get the requested file (alias of getFile method)
	 *
	 * @return string
	 *
	 */
	public function getRequestFile() {
		return $this->requestFile;
	}

	/**
	 * Get message about response only if response was an error, blank otherwise
	 * 
	 * @return string
	 * 
	 */
	public function getResponseError() {
		return ($this->responseCode >= 400 ? $this->getResponseMessage() : '');
	}

	/**
	 * Set response message
	 * 
	 * @param string $message
	 * @param bool $append Append to existing message?
	 * 
	 */
	public function setResponseMessage($message, $append = false) {
		if($append && $this->responseMessage) $message = "$this->responseMessage \n$message";
		$this->responseMessage = $message;
	}

	/**
	 * Get message string about response
	 * 
	 * @return string
	 * 
	 */
	public function getResponseMessage() {
		$code = $this->getResponseCode();
		$value = $this->getResponseCodeName();
		if(empty($value)) $value = "unknown";
		$value = "$code $value";
		if($this->responseMessage) $value .= ": $this->responseMessage";
		$attrs = array();
		if(!empty($this->pageInfo['urlSegments'])) $attrs[] = 'urlSegments';
		if($this->pageNum > 1) $attrs[] = 'pageNum';
		if($this->requestFile) $attrs[] = 'file';
		return $value;
	}
}
