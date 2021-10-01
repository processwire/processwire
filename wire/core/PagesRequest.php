<?php namespace ProcessWire;

/**
 * ProcessWire Pages Request
 *
 * Identifies page from current request URL and loads it
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 * 
 * @method Page|null getPage()
 * @method Page|null getPageForUser(Page $page, User $user)
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
	 * Response http code
	 * 
	 * @var int
	 * 
	 */
	protected $responseCode = 0;
	
	/**
	 * Response type name
	 * 
	 * @var string
	 *
	 */
	protected $responseName = '';

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
	 * Error from getPage() method, if it could not identify a valid Page
	 * 
	 * @var string
	 * 
	 */
	protected $error = '';

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
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
	 * Get the requested page and populate identified urlSegments or page numbers
	 *
	 * @return Page|NullPage
	 *
	 */
	public function ___getPage() {

		// perform this work only once unless reset by setPage or setRequestPath
		if($this->page && $this->requestPath === $this->processedPath) return $this->page;

		$input = $this->wire()->input;
		$page = null;

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
				if($page->id) {
					$this->setResponse(200, 'fileOk');
				} else {
					$this->setResponse(404, 'fileNotFound');
				}
				return $this->setPage($page);
			}
		}
		
		// populate request path to class as other methods will now use it
		$this->setRequestPath($path);
	
		// determine if index.php is referenced in URL
		if(stripos($this->dirtyUrl, 'index.php') !== false && stripos($path, 'index.php') === false) {
			// this will force pathFinder to detect a redirect condition
			$path = rtrim($path, '/') . '/index.php';
		}

		// get info about requested path
		$info = $this->pages->pathFinder()->get($path, array('verbose' => false));
		$this->pageInfo = &$info;
		$this->languageName = $info['language']['name'];
		$this->setResponse($info['response'], $info['type']); 
	
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
		if($info['page']['id']) {
			$page = $this->pages->getOneById($info['page']['id'], array(
				'template' => $info['page']['templates_id'],
				'parent_id' => $info['page']['parent_id'],
			));
		} else {
			$page = $this->pages->newNullPage();
		}
	
		// just in case (not likely)
		if(!$page->id && $this->responseCode < 300) $this->responseCode = 404;
		
		// the first version of PW populated first URL segment to $page 
		if($page->id && !empty($info['urlSegments'])) {
			// undocumented behavior retained for backwards compatibility
			$page->setQuietly('urlSegment', $input->urlSegment1);
		}

		if($this->responseCode < 300) {
			// 200 ok
			
		} else if($this->responseCode >= 300 && $this->responseCode < 400) {
			// 301 permRedirect or 302 tempRedirect 
			$this->setRedirectPath($info['redirect'], $info['response']);
			
		} else if($this->responseCode >= 400) {
			// 404 pageNotFound or 414 pathTooLong
			if(!empty($info['redirect'])) {} // todo: pathFinder suggests a redirect may still be possible
			if($page->id) $this->wire('closestPage', $page); // set a $closestPage API in case 404 page wants it
			$page = $this->pages->newNullPage();
		}
			
		if($page->id) $this->setPage($page);
	
		return $page;
	}
	
	/**
	 * Update/get page for given user
	 * 
	 * Must be called once the current $user is known as it may change the $page.
	 * Returns NullPage or login page if user lacks access.
	 *
	 * @param Page $page
	 * @param User $user
	 * @return Page|NullPage|null
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
		}

		if($page && $page->id) {
			$this->checkScheme($page);
			$this->setPage($page);
			$page->of(true);
		}
		
		return $page;
	}

	/**
	 * Get the requested path
	 *
	 * @return bool|string Return false on fail, path on success
	 *
	 */
	protected function getPageRequestPath() {
		
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
					$this->setResponse(404, 'pageNotFound', 'Request URL outside of our web root');
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
				$this->setResponse(400, 'pagePathError', 'Request URL contains invalid/unsupported characters');
				return false;
			}
		}

		$maxUrlDepth = $config->maxUrlDepth;
		if($maxUrlDepth > 0 && substr_count($it, '/') > $config->maxUrlDepth) {
			$this->setResponse(414, 'pathTooLong', 'Request URL exceeds max depth set in $config->maxUrlDepth');
			return false;
		}

		if(!isset($it[0]) || $it[0] != '/') $it = "/$it";
		
		if(strpos($it, '//') !== false) {
			$this->setResponse(400, 'pagePathError', 'Request URL contains a blank segment “//”');
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
	 * - Returns true, and updates $it, when pagefile was found using old/deprecated method.
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
		if(strpos($url, $config->urls->files) === 0) {
			// request is for file in site/assets/files/...
			$idAndFile = substr($url, strlen($config->urls->files));

			// matching in $idAndFile: 1234/file.jpg, 1/2/3/4/file.jpg, 1234/subdir/file.jpg, 1/2/3/4/subdir/file.jpg, etc. 
			if(preg_match('{^(\d[\d\/]*)/([-_a-zA-Z0-9][-_./a-zA-Z0-9]+)$}', $idAndFile, $matches) && strpos($matches[2], '.')) {
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

			} else {
				// request was to something in /site/assets/files/ but we don't recognize it
				// tell caller that this should be a 404
				return $pages->newNullPage();
			}
		}

		// check for secured filename: method 2 (deprecated), used only if $config->pagefileUrlPrefix is defined
		$filePrefix = $config->pagefileUrlPrefix;
		if($filePrefix && strpos($path, '/' . $filePrefix) !== false) {
			if(preg_match('{^(.*/)' . $filePrefix . '([-_.a-zA-Z0-9]+)$}', $path, $matches) && strpos($matches[2], '.')) {
				$path = $matches[1];
				$this->requestFile = $matches[2];
				return true;
			}
		}

		return false;
	}

	/**
	 * Check that the current user has access to the page and return it
	 *
	 * If the user doesn't have access, then a login Page or NULL (for 404) is returned instead.
	 * 
	 * @param Page $page
	 * @param User $user
	 * @return Page|null
	 * 
	 *
	 */
	protected function checkAccess(Page $page, User $user) {

		if($this->requestFile) {
			// if a file was requested, we still allow view even if page doesn't have template file
			if($page->viewable($this->requestFile) === false) return null;
			if($page->viewable(false)) return $page;
			if($this->checkAccessDelegated($page)) return $page;
			if($page->status < Page::statusUnpublished && $user->hasPermission('page-view', $page)) return $page;

		} else if($page->viewable()) {
			return $page;

		} else if($page->parent_id && $page->parent->template->name === 'admin' && $page->parent->viewable()) {
			// check for special case in admin when Process::executeSegment() collides with page name underneath
			// example: a role named "edit" is created and collides with ProcessPageType::executeEdit()
			$input = $this->wire()->input;
			if($user->isLoggedin() && $page->editable() && !strlen($input->urlSegmentStr())) {
				$input->setUrlSegment(1, $page->name);
				return $page->parent;
			}
		}

		$accessTemplate = $page->getAccessTemplate();
		$redirectLogin = $accessTemplate ? $accessTemplate->redirectLogin : false;

		// if we won’t be presenting a login form then $page converts to null (404)
		if(!$redirectLogin) return null;

		$config = $this->config;
		$session = $this->wire()->session;
		$input = $this->wire()->input;
		
		$disallowIDs = array($config->trashPageID); // don't allow login redirect for these pages
		$loginRequestURL = $this->redirectUrl;
		$loginPageID = $config->loginPageID;
		$requestPage = $page;
		$ns = 'ProcessPageView';

		if($page->id && in_array($page->id, $disallowIDs)) {
			// don't allow login redirect when matching disallowIDs
			$page = null;

		} else if(ctype_digit("$redirectLogin")) {
			// redirect login provided as a page ID
			$redirectLogin = (int) $redirectLogin;
			// if given ID 1 then this maps to the admin login page
			if($redirectLogin === 1) $redirectLogin = $loginPageID;
			$page = $this->pages->get($redirectLogin);

		} else {
			// redirect login provided as a URL, optionally with an {id} tag for requested page ID
			$redirectLogin = str_replace('{id}', $page->id, $redirectLogin);
			$this->setRedirectUrl($redirectLogin);
		}

		if(empty($loginRequestURL) && $session) {
			$loginRequestURL = $session->getFor($ns, 'loginRequestURL');
		}

		// in case anything after login needs to know the originally requested page/URL
		if(empty($loginRequestURL) && $page && $requestPage && $requestPage->id && $session) {
			if($requestPage->id != $loginPageID && !$input->get('loggedout')) {
				$loginRequestURL = $input->url(array('page' => $requestPage));
				if(!empty($_GET)) {
					$queryString = $input->queryStringClean(array(
						'maxItems' => 10,
						'maxLength' => 500,
						'maxNameLength' => 20,
						'maxValueLength' => 200,
						'sanitizeName' => 'fieldName',
						'sanitizeValue' => 'name',
						'entityEncode' => false,
					));
					if(strlen($queryString)) $loginRequestURL .= "?$queryString";
				}
				$session->setFor($ns, 'loginRequestPageID', $requestPage->id);
				$session->setFor($ns, 'loginRequestURL', $loginRequestURL);
			}
		}

		return $page;
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
	 * @param string $name
	 * @param string $error Optional error string
	 * 
	 */
	protected function setResponse($code, $name , $error = '') {
		$this->responseCode = (int) $code;
		$this->responseName = $name;
		if($error) $this->error = $error;
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
	 * - permRedirect: permanent redirect (301)
	 * - tempRedirect: temporary redirect (302)
	 * - pagePathError: page path error (400)
	 * - pageNotFound: page not found (404)
	 * - pathTooLong: path too long or segment too long
	 * 
	 * @return string
	 * 
	 */
	public function getResponseName() {
		if(!$this->responseName) return 'unknown';
		return $this->responseName;
	}
	
	/**
	 * Get response http code for this request
	 * 
	 * Returns integer, one of:
	 * 
	 * - 0: request not yet analyzed
	 * - 200: successful request
	 * - 301: permanent redirect
	 * - 302: temporary redirect
	 * - 400: page path error
	 * - 404: page not found
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
		if(empty($this->requestPath)) $this->requestPath = $this->getPageRequestPath();
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
	 * Get the redirect type (0, 301 or 302)
	 * 
	 * @return int
	 * 
	 */
	public function getRedirectType() {
		return $this->redirectType;
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
	 * @return null
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
	 * Get error message
	 * 
	 * @return string
	 * 
	 */
	public function getPageError() {
		return $this->error;
	}

}
