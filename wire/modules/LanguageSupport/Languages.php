<?php namespace ProcessWire;

/**
 * ProcessWire Languages (plural) Class
 * 
 * #pw-summary API variable $languages enables access to all Language pages and various helper methods. 
 * #pw-body =
 * The $languages API variable is most commonly used for iteration of all installed languages.
 * ~~~~~
 * foreach($languages as $language) {
 *   echo "<li>$language->title ($language->name) ";
 *   if($language->id == $user->language->id) {
 *     echo "current"; // the user's current language
 *   }
 *   echo "</li>";
 * }
 * ~~~~~
 * 
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property LanguageTabs|null $tabs Current LanguageTabs module instance, if installed #pw-internal
 * @property Language $default Get default language
 * @property Language $getDefault Get default language (alias of $default)
 * @property LanguageSupport $support Instance of LanguageSupport module #pw-internal
 * @property LanguageSupportPageNames|false $pageNames Instance of LanguageSupportPageNames module or false if not installed 3.0.186+ #pw-internal
 * 
 * @method added(Page $language) Hook called when Language is added #pw-hooker
 * @method deleted(Page $language) Hook called when Language is deleted #pw-hooker
 * @method updated(Page $language, $what) Hook called when Language is added or deleted #pw-hooker
 * @method languageChanged($fromLanguage, $toLanguage) Hook called when User language is changed #pw-hooker
 *
 */

class Languages extends PagesType {

	/**
	 * Reference to LanguageTranslator instance
	 * 
	 * @var LanguageTranslator
	 *
	 */
	protected $translator = null;

	/**
	 * Cached all published languages (for getIterator)
	 *
	 * We cache them so that the individual language pages persist through saves.
	 *
	 */
	protected $languages = null;

	/**
	 * Cached all languages including unpublished (for getAll)
	 *
	 */
	protected $languagesAll = null;

	/**
	 * Saved reference to default language
	 * 
	 * @var Language|null
	 * 
	 */
	protected $defaultLanguage = null;

	/**
	 * Saved language from a setDefault() call
	 * 
	 * @var Language|null
	 * 
	 */
	protected $savedLanguage = null;

	/**
	 * Saved language from a setLanguage() call
	 * 
	 * @var Language|null
	 * 
	 */
	protected $savedLanguage2 = null;

	/**
	 * Language-specific page-edit permissions, if installed (i.e. page-edit-lang-es, page-edit-lang-default, etc.)
	 *
	 * @var null|array Becomes an array once its been populated
	 *
	 */
	protected $pageEditPermissions = null;

	/**
	 * Cached results from editable() method, indexed by user_id.language_id
	 * 
	 * @var array
	 * 
	 */
	protected $editableCache = array();

	/**
	 * LanguageSupportPageNames module instance or boolean install state
	 * 
	 * Populated as a cache by the pageNames() or hasPageNames() methods
	 * 
	 * @var LanguageSupportPageNames|null|bool
	 * 
	 */
	protected $pageNames = null;

	/**
	 * Construct
	 *
	 * @param ProcessWire $wire
	 * @param array $templates
	 * @param array $parents
	 * 
	 */
	public function __construct(ProcessWire $wire, $templates = array(), $parents = array()) {
		parent::__construct($wire, $templates, $parents);
		$this->wire()->database->addHookAfter('unknownColumnError', $this, 'hookUnknownColumnError');
	}

	/**
	 * Return the LanguageTranslator instance for the given language
	 * 
	 * @param Language $language
	 * @return LanguageTranslator
	 *
	 */
	public function translator(Language $language) {
		/** @var LanguageTranslator $translator */
		$translator = $this->translator;
		if(is_null($translator)) {
			$translator = $this->wire(new LanguageTranslator($language));
			$this->translator = $translator;
		} else {
			$translator->setCurrentLanguage($language);
		}
		return $translator; 
	}

	/**
	 * Return the Page class used by Language pages
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function getPageClass() {
		if($this->pageClass) return $this->pageClass;
		$this->pageClass = class_exists(__NAMESPACE__ . "\\LanguagePage") ? 'LanguagePage' : 'Language';
		return $this->pageClass;
	}

	/**
	 * Get options for PagesType loadOptions (override from PagesType)
	 * 
	 * #pw-internal
	 * 
	 * @param array $loadOptions
	 * @return array
	 * 
	 */
	public function getLoadOptions(array $loadOptions = array()) {
		$loadOptions = parent::getLoadOptions($loadOptions);
		$loadOptions['autojoin'] = false;
		return $loadOptions; 
	}

	/**
	 * Get join field names (override from PagesType)
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getJoinFieldNames() {
		return array();
	}

	/**
	 * Returns ALL languages (including inactive)
	 *
	 * Note: to get all active languages, just iterate the $languages API variable instead. 
	 * 
	 * #pw-internal
	 * 
	 * @return PageArray
	 *
	 */
	public function getAll() {
		if($this->languagesAll) return $this->languagesAll;
		$template = $this->getTemplate();
		$parent_id = $this->getParentID();
		$selector = "parent_id=$parent_id, template=$template, include=all, sort=sort";
		$languagesAll = $this->wire()->pages->find($selector, array(
				'loadOptions' => $this->getLoadOptions(), 
				'caller' => $this->className() . '.getAll()'
			)
		); 
		if(count($languagesAll)) $this->languagesAll = $languagesAll;
		return $languagesAll;
	}

	/**
	 * Find and return all languages except current user language
	 * 
	 * @param string|Language $selector Optionally filter by a selector string
	 * @param Language|null $excludeLanguage optionally specify language to exclude, if not user language (can also be 1st arg)
	 * @return PageArray
	 * 
	 */
	public function findOther($selector = '', $excludeLanguage = null) {
		if(is_null($excludeLanguage)) {
			if($selector instanceof Language) {
				$excludeLanguage = $selector;
				$selector = '';
			} else {
				$excludeLanguage = $this->wire()->user->language;
			}
		}
		$languages = $this->wire()->pages->newPageArray();
		foreach($this as $language) {
			if($language->id == $excludeLanguage->id) continue;
			if($selector && !$language->matches($selector)) continue;
			$languages->add($language);
		}
		return $languages;
	}

	/**
	 * Find and return all languages except default language
	 * 
	 * @param string $selector Optionally filter by a selector string
	 * @return PageArray
	 * 
	 */
	public function findNonDefault($selector = '') {
		$defaultLanguage = $this->getDefault();
		return $this->findOther($selector, $defaultLanguage);
	}

	/**
	 * Enable iteration of this class
	 * 
	 * #pw-internal
	 * 
	 * @return PageArray
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		if($this->languages && count($this->languages)) return $this->languages; 
		$languages = $this->wire()->pages->newPageArray();
		foreach($this->getAll() as $language) { 
			if($language->hasStatus(Page::statusUnpublished) || $language->hasStatus(Page::statusHidden)) continue; 
			$languages->add($language); 
		}
		if(count($languages)) $this->languages = $languages;
		return $languages; 
	}

	/**
	 * Get the default language
	 * 
	 * The default language can also be accessed from property `$languages->default`. 
	 * 
	 * ~~~~~
	 * if($user->language->id == $languages->getDefault()->id) {
	 *   // user has the default language
	 * }
	 * ~~~~~
	 * 
	 * @return Language
	 * @throws WireException when default language hasn't yet been set
	 * 
	 */
	public function getDefault() {
		if(!$this->defaultLanguage) throw new WireException('Default language not yet set');
		return $this->defaultLanguage; 	
	}

	/**
	 * Set current user to have default language temporarily
	 * 
	 * If given no arguments, it sets the current `$user` to have the default language temporarily. It is
	 * expected you will follow it up with a later call to `$languages->unsetDefault()` to restore the 
	 * previous language the user had. 
	 * 
	 * If given a Language object, it sets that as the default language (for internal use only). 
	 * 
	 * ~~~~~
	 * // set current user to have default language
	 * $languages->setDefault();
	 * // perform some operation that has a default language dependency ...
	 * // then restore the user's previous language with unsetDefault()
	 * $languages->unsetDefault();
	 * ~~~~~
	 * 
	 * @param Language|null $language
	 * @return void
	 * @see Languages::unsetDefault(), Languages::setLanguage()
	 * 
	 */
	public function setDefault(?Language $language = null) {
		if(is_null($language)) {
			// save current user language setting and make current language default
			if(!$this->defaultLanguage) return;
			$user = $this->wire()->user;
			if($user->language->id == $this->defaultLanguage->id) return; // already default
			$this->savedLanguage = $user->language;
			$previouslyChanged = $user->isChanged('language');	
			$user->language = $this->defaultLanguage; 
			if(!$previouslyChanged) $user->untrackChange('language');
		} else {
			// set what language is the default
			$this->defaultLanguage = $language; 
		}
	}

	/**
	 * Restores whatever previous language a user had prior to a setDefault() call
	 * 
	 * @return void
	 * @see Languages::setDefault()
	 * 
	 */
	public function unsetDefault() { 
		if(!$this->savedLanguage || !$this->defaultLanguage) return;
		$user = $this->wire()->user;
		$previouslyChanged = $user->isChanged('language');
		$user->language = $this->savedLanguage; 
		if(!$previouslyChanged) $user->untrackChange('language');
	}

	/**
	 * Set the current user language for the current request
	 * 
	 * This also remembers the previous Language setting which can be restored with
	 * a `$languages->unsetLanguage()` call.
	 * 
	 * ~~~~~
	 * $languages->setLanguage('de');
	 * ~~~~~
	 * 
	 * @param int|string|Language $language Language id, name or Language object
	 * @return bool Returns false if no change necessary, true if language was changed
	 * @throws WireException if given $language argument doesn't resolve
	 * @see Languages::unsetLanguage()
	 * 
	 */
	public function setLanguage($language) {
		if(is_int($language)) {
			$language = $this->get($language);
		} else if(is_string($language)) {
			$language = $this->get($this->wire()->sanitizer->pageNameUTF8($language));	
		} 
		if(!$language instanceof Language || !$language->id) throw new WireException("Unknown language");
		$user = $this->wire()->user;
		$this->savedLanguage2 = null;
		if($user->language && $user->language->id) {
			if($language->id == $user->language->id) return false; // no change necessary
			$this->savedLanguage2 = $user->language;
		}
		$user->setQuietly('language', $language);
		return true;
	}

	/**
	 * Get the current language or optionally a specific named language
	 * 
	 * - This method is not entirely necessary but is here to accompany the setLanguage() method for syntax convenience. 
	 * - If you specify a `$name` argument, this method works the same as the `$languages->get($name)` method.
	 * - If you call with no arguments, it returns the current user language, same as `$user->language`, but using this
	 *   method may be preferable in some contexts, depending on how your IDE understands API calls. 
	 * 
	 * @param string|int $name Specify language name (or ID) to get a specific language, or omit to get current language
	 * @return Language|null
	 * @since 3.0.127 
	 * 
	 */
	public function getLanguage($name = '') {
		if(empty($name)) return $this->wire()->user->language;
		if($name instanceof Language) return $name; 
		$language = parent::get($name);
		return ($language instanceof Language ? $language : null);
	}

	/**
	 * Undo a previous setLanguage() call, restoring the previous user language
	 * 
	 * @return bool Returns true if language restored, false if no restore necessary
	 * @see Languages::setLanguage()
	 * 
	 */
	public function unsetLanguage() {
		$user = $this->wire()->user;
		if(!$this->savedLanguage2) return false;
		if($user->language && $user->language->id == $this->savedLanguage2->id) return false;
		$user->setQuietly('language', $this->savedLanguage2);
		return true;
	}
	
	/**
	 * Set the current locale
	 *
	 * This function behaves exactly the same way as [PHP setlocale](http://php.net/manual/en/function.setlocale.php) except
	 * for the following:
	 *
	 * - If the $locale argument is omitted, it uses the locale setting translated for the current user language.
	 * - You can optionally specify a CSV string of locales to try for the $locale argument. 
	 * - You can optionally or a “category=locale;category=locale;category=locale” string for the $locale argument.
	 *   When this type of string is used, the $category argument is ignored. 
	 * - This method does not accept more than the 3 indicated arguments. 
	 * - Any of the arguments may be swapped.
	 * 
	 * See the PHP setlocale link above for a list of constants that can be used for the `$category` argument. 
	 * 
	 * Note that the locale is set once at bootup by ProcessWire, and does not change after that unless you call this
	 * method. Meaning, a change to `$user->language` does not automatically change the locale. If you want to change
	 * the locale, you would have to call this method after changing the user’s language from the API side.
	 * 
	 * ~~~~~
	 * // Set locale to whatever settings defined for current $user language
	 * $languages->setLocale(); 
	 * 
	 * // Set all locale categories 
	 * $languages->setLocale(LC_ALL, 'en_US.UTF-8'); 
	 * 
	 * // Set locale for specific category (CTYPE)
	 * $languages->setLocale(LC_CTYPE, 'en_US.UTF-8'); 
	 * 
	 * // Try multiple locales till one works (in order) using array
	 * $languages->setLocale(LC_ALL, [ 'en_US.UTF-8', 'en_US', 'en' ]);
	 * 
	 * // Same as above, except using CSV string
	 * $languages->setLocale(LC_ALL, 'en_US.UTF-8, en_US, en'); 
	 * 
	 * // Set multiple categories and locales (first argument ignored)
	 * $languages->setLocale(null, 'LC_CTYPE=en_US;LC_NUMERIC=de_DE;LC_TIME=es_ES'); 
	 * ~~~~~
	 * 
	 * @param int|string|array|null|Language $category Specify a PHP “LC_” constant (int) or omit (or null) for default (LC_ALL).
	 * @param int|string|array|null|Language $locale Specify string, array or CSV string of locale name(s), 
	 *   omit (null) for current language locale, or specify Language object to pull locale from that language. 
	 * @return string|bool Returns the locale that was set or boolean false if requested locale cannot be set.
	 * @see Languages::getLocale()
	 *
	 */
	public function setLocale($category = LC_ALL, $locale = null) {
		
		$setLocale = ''; // return value
		
		if(!is_int($category)) {
			list($category, $locale) = array($locale, $category); // swap arguments
		}
		
		if($category === null) $category = LC_ALL;	

		if($locale === null || is_object($locale)) {
			// argument omitted means set according to language settings
			$language = $locale instanceof Language ? $locale : $this->wire()->user->language;
			$textdomain = 'wire--modules--languagesupport--languagesupport-module';
			$locale = $language->translator()->getTranslation($textdomain, 'C');
		}

		if(is_string($locale)) {
			
			if(strpos($locale, ',') !== false) {
				// convert CSV string to array of locales
				$locale = explode(',', $locale);
				foreach($locale as $key => $value) {
					$locale[$key] = trim($value);
				}
				
			} else if(strpos($locale, ';') !== false) {
				// multi-category and locale string, i.e. LC_CTYPE=en_US.UTF-8;LC_NUMERIC=C;LC_TIME=C
				foreach(explode(';', $locale) as $s) {
					// call setLocale() for each locale item present in the string
					if(strpos($s, '=') === false) continue;
					list($cats, $loc) = explode('=', $s); 
					$cat = constant($cats);
					if($cat !== null) {
						$loc = $this->setLocale($cat, $loc);
						if($loc !== false) $setLocale .= trim($cats) . '=' . trim($loc) . ";";
					}
				}
				$setLocale = rtrim($setLocale, ';');
				if(empty($setLocale)) $setLocale = false;
			}
		}

		if($setLocale === '') {
			if($locale === '0' || $locale === 0) {
				// get locale (to be consistent with behavior of PHP setlocale)
				$setLocale = $this->getLocale($category);
			} else {
				// set the locale
				$setLocale = setlocale($category, $locale);
			}
		}

		return $setLocale;
	}

	/**
	 * Return the current locale setting
	 * 
	 * If using LC_ALL category and locales change by category, the returned string will be in 
	 * the format: “category=locale;category=locale”, and so on. 
	 * 
	 * The first and second arguments may optionally be swapped and either can be omitted. 
	 * 
	 * @param int|Language|string|null $category Optionally specify a PHP LC constant (default=LC_ALL)
	 * @param Language|string|int|null $language Optionally return locale for specific language (default=current locale, regardless of language)
	 * @return string|bool Locale(s) string or boolean false if not supported by the system. 
	 * @see Languages::setLocale()
	 * @throws WireException if given a $language argument that is invalid
	 *
	 */
	public function getLocale($category = LC_ALL, $language = null) {
		if(is_int($language)) list($category, $language) = array($language, $category);	// argument swap
		if($category === null) $category = LC_ALL;
		if($language) {
			if(!$language instanceof Language) {
				$language = $this->get($language);
				if(!$language instanceof Language) throw new WireException("Invalid getLocale() language");
			}
			$locale = $language->translator()->getTranslation('wire--modules--languagesupport--languagesupport-module', 'C');
		} else {
			$locale = setlocale($category, '0');
		}
		return $locale;
	}

	/**
	 * Hook called when a language is deleted
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $language
	 *
	 */
	public function ___deleted(Page $language) {
		$this->updated($language, 'deleted'); 
		parent::___deleted($language);
	}

	/**
	 * Hook called when a language is added
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $language
	 *
	 */
	public function ___added(Page $language) {
		$this->updated($language, 'added'); 
		parent::___added($language);
	}

	/**
	 * Hook called when a language is added or deleted
	 * 
	 * #pw-hooker
	 *
	 * @param Page $language
	 * @param string $what What occurred? ('added' or 'deleted')
	 *
	 */
	public function ___updated(Page $language, $what) {
		$this->reloadLanguages();
		$this->message("Updated language $language->name ($what)", Notice::debug); 
	}

	/**
	 * Reload all languages
	 * 
	 * #pw-internal
	 *
	 */
	public function reloadLanguages() {
		$this->languages = null;
		$this->languagesAll = null;
	}

	/**
	 * Override getParent() from PagesType
	 * 
	 * #pw-internal
	 * 
	 * @return Page
	 * 
	 */
	public function getParent() {
		return $this->wire()->pages->get($this->parent_id, array('loadOptions' => array('autojoin' => false)));
	}

	/**
	 * Override getParents() from PagesType
	 * 
	 * #pw-internal
	 * 
	 * @return PageArray
	 * 
	 */
	public function getParents() {
		if(count($this->parents)) {
			return $this->wire()->pages->getById($this->parents, array('autojoin' => false));
		} else {
			return parent::getParents();
		}
	}

	/**
	 * Get LanguageSupportPageNames module if installed, false if not
	 * 
	 * @return LanguageSupportPageNames|false
	 * @since 3.0.186
	 * 
	 */
	public function pageNames() {
		// null when not known, true when previously detected as installed but instance not yet loaded
		if($this->pageNames === null || $this->pageNames === true) {
			$modules = $this->wire()->modules;
			if($modules->isInstalled('LanguageSupportPageNames')) {
				// installed: load instance
				$this->pageNames = $modules->getModule('LanguageSupportPageNames');
			} else {
				// not installed
				$this->pageNames = false;
			}
		}
		// object instance or boolean false
		return $this->pageNames;
	}

	/**
	 * Is LanguageSupportPageNames installed?
	 * 
	 * @return bool
	 * @since 3.0.186
	 * 
	 */
	public function hasPageNames() {
		// if previously identified as installed or instance loaded, return true
		if($this->pageNames) return true;
		// if previously identified as NOT installed, return false
		if($this->pageNames === false) return false;
		// populate with installed status boolean and return it
		$this->pageNames = $this->wire()->modules->isInstalled('LanguageSupportPageNames');
		return $this->pageNames;
	}

	/**
	 * Get all language specific page-edit permissions, or individually one of them
	 * 
	 * #pw-internal
	 *
	 * @param string $name Optionally specify a permission or language name to change return value.
	 * @return array|string|bool Array of Permission names indexed by language name, or:
	 *  - If given a language name, it will return permission name (if exists) or false if not. 
	 *  - If given a permission name, it will return the language name (if exists) or false if not. 
	 *
	 */
	public function getPageEditPermissions($name = '') {
		
		$prefix = "page-edit-lang-";
		
		if(!is_array($this->pageEditPermissions)) {
			$this->pageEditPermissions = array();
			$langNames = array();
			foreach($this as $language) {
				$langNames[$language->name] = $language->name;
			}
			foreach($this->wire()->permissions as $permission) {
				if(strpos($permission->name, $prefix) !== 0) continue;
				if($permission->name === $prefix . 'none') {
					$this->pageEditPermissions['none'] = $permission->name;
					continue;
				}
				foreach($langNames as $langName) {
					$permissionName = $prefix . $langName;
					if($permission->name === $permissionName) {
						$this->pageEditPermissions[$langName] = $permissionName;
						break;
					}
				}
			}
		}
		
		if($name) {
			if(strpos($name, $prefix) === 0) {
				// permission name specified: will return language name or false
				return array_search($name, $this->pageEditPermissions);
			} else {
				// language name specified: will return permission name or false
				return isset($this->pageEditPermissions[$name]) ? $this->pageEditPermissions[$name] : false;
			}
			
		} else {
			return $this->pageEditPermissions;
		}
	}

	/**
	 * Return applicable page-edit permission name for given language
	 * 
	 * A blank string is returned if there is no applicable permission
	 * 
	 * #pw-internal
	 * 
	 * @param int|string|Language $language
	 * @return string
	 * 
	 */
	public function getPageEditPermission($language) {
		$permissions = $this->getPageEditPermissions();
		if($language === 'none' && isset($permissions['none'])) return $permissions['none'];
		if(!$language instanceof Language) {
			$language = $this->get($this->wire()->sanitizer->pageNameUTF8($language));
		}
		if(!$language || !$language->id) return '';
		return isset($permissions[$language->name]) ? $permissions[$language->name] : '';
	}

	/**
	 * Does current user have edit access for page fields in given language?
	 * 
	 * @param Language|int|string $language Language id, name or object, or string "none" to refer to non-multi-language fields
	 * @return bool True if editable, false if not
	 * 
	 */
	public function editable($language) {
		
		$user = $this->wire()->user;
		if($user->isSuperuser()) return true; 
		if(empty($language)) return false;
		$cacheKey = "$user->id.$language";
		
		if(array_key_exists($cacheKey, $this->editableCache)) {
			// accounts for 'none', or language ID
			return $this->editableCache[$cacheKey];
		}
		
		if($language === 'none') {
			// page-edit-lang-none permission applies to non-multilanguage fields, if present
			$permissions = $this->getPageEditPermissions();
			if(isset($permissions['none'])) {
				// if the 'none' permission exists, then the user must have it in order to edit non-multilanguage fields
				$has = $user->hasPermission('page-edit') && $user->hasPermission($permissions['none']); 
			} else {
				// if the page-edit-lang-none permission doesn't exist, then it's not applicable
				$has = $user->hasPermission('page-edit');
			}
			
		} else {
			
			if(!$language instanceof Language) $language = $this->get($this->wire()->sanitizer->pageNameUTF8($language));
			if(!$language || !$language->id) return false;
		
			$cacheKey = "$user->id.$language->id";
			
			if(array_key_exists($cacheKey, $this->editableCache)) {
				return $this->editableCache[$cacheKey];
			}
			
			if(!$user->hasPermission('page-edit')) {
				// page-edit is a pre-requisite permission
				$has = false;
			} else {
				$permissionName = $this->getPageEditPermission($language);
				// if a language-specific page-edit permission doesn't exist, then fallback to regular page-edit permission
				if(!$permissionName) {
					$has = true;
				} else {
					$has = $user->hasPermission($permissionName);
				}
			}
		}
		
		$this->editableCache[$cacheKey] = $has;

		return $has; 
	}

	/**
	 * Direct access to certain properties
	 * 
	 * #pw-internal
	 * 
	 * @param string $name
	 * @return mixed|Language
	 * 
	 */
	public function __get($name) {
		if($name === 'tabs') {
			$ls = $this->wire()->modules->get('LanguageSupport'); /** @var LanguageSupport $ls */
			return $ls->getLanguageTabs();
		} else if($name === 'default') {
			return $this->getDefault();
		} else if($name === 'support') {
			return $this->wire()->modules->get('LanguageSupport');
		} else if($name === 'pageNames') {
			return $this->pageNames();
		} else if($name === 'hasPageNames') {
			return $this->hasPageNames();
		}
		return parent::__get($name);
	}
	
	/**
	 * Get language or property
	 * 
	 * (method repeated here for return value documentation purposes only)
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key
	 * @return Language|NullPage|null|mixed
	 *
	 */
	public function get($key) {
		return parent::get($key);
	}
	
	/**
	 * Import a language translations file
	 *
	 * @param Language|string $language
	 * @param string $file Full path to .csv translations file
	 *   The .csv file must be one generated by ProcessWire’s language translation tools. 
	 * @param bool $quiet Specify true to suppress error/success notifications being generated (default=false)
	 * @return bool|int Returns integer with number of translations imported or boolean false on error
	 * @throws WireException
	 * @since 3.0.181
	 *
	 */
	public function importTranslationsFile($language, $file, $quiet = false) {
		if(!wireInstanceOf($language, 'Language')) $language = $this->get($language);
		if(!$language || !$language->id) throw new WireException("Unknown language");
		$process = $this->wire()->modules->getModule('ProcessLanguage', array('noInit' => true)); /** @var ProcessLanguage $process */
		if(!$this->wire()->files->exists($file)) throw new WireException("Language file does not exist: $file");
		if(pathinfo($file, PATHINFO_EXTENSION) !== 'csv') throw new WireException("Language file does not have .csv extension");
		return $process->processCSV($file, $language, array('quiet' => $quiet));
	}

	/**
	 * Hook to WireDatabasePDO::unknownColumnError
	 *
	 * Provides QA to make sure any language-related columns are property setup in case
	 * something failed during the initial setup process.
	 *
	 * This is only here to repair existing installs that were missing a field for one reason or another.
	 * This method (and the call to it in Pages) can eventually be removed (?)
	 * 
	 * #pw-internal
	 *
	 * @param HookEvent $event
	 * #param string $column Argument 0 in HookEvent is unknown column name
	 *
	 */
	public function hookUnknownColumnError(HookEvent $event) {
		
		$column = $event->arguments(0);
		if(!preg_match('/^([^.]+)\.([^.\d]+)(\d+)$/', $column, $matches)) {
			return;
		}

		$table = $matches[1];
		// $col = $matches[2];
		$languageID = (int) $matches[3];
		
		$modules = $this->wire()->modules;
		$fields = $this->wire()->fields;

		foreach($this as $language) {
			if($language->id != $languageID) continue;
			$this->warning("language $language->name is missing column $column", Notice::debug);
			if($table == 'pages' && $this->hasPageNames()) { 
				$this->pageNames()->languageAdded($language);
			} else if(strpos($table, 'field_') === 0) {
				$fieldName = substr($table, strpos($table, '_')+1);
				$field = $fields->get($fieldName);
				if($field && $modules->isInstalled('LanguageSupportFields')) {
					/** @var LanguageSupportFields $module */
					$module = $modules->get('LanguageSupportFields');
					$module->fieldLanguageAdded($field, $language);
				}
			}
		}
	}
}
