<?php namespace ProcessWire;

/**
 * ProcessWire Language Translator 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */
class LanguageTranslator extends Wire {

	/**
	 * Language (Page) instance of the current language
	 * 
	 * @var Language
	 *
	 */
	protected $currentLanguage;

	/**
	 * Path where language files are stored
	 *
	 * i.e. language_files path for current $language
	 * 
	 * @var string
 	 *
	 */
	protected $path;

	/**
	 * Root path of installation, same as wire('config')->paths->root
	 * 
	 * @var string
	 *
	 */
	protected $rootPath; 

	/**
	 * Alternate root path for systems where there might be symlinks
	 * 
	 * @var string
	 *
	 */
	protected $rootPath2; 

	/**
	 * Translations for current language, in this format (consistent with the JSON): 
	 *
	 * array(
	 *	'textdomain' => array( 
	 *		'file' => 'filename',
	 * 		'translations' => array(
	 *			'[hash]' => array(
	 *				'text' => string, 	// translated version of the text 
	 *				)
	 *			)
	 * 		)
	 *	); 
	 * 
	 * @var array
	 *
	 */
	protected $textdomains = array();

	/**
	 * Cache of class names and the resulting textdomains
	 * 
	 * @var array
	 *
	 */
	protected $classNamesToTextdomains = array();

	/**
	 * Textdomains of parent classes that can be checked where applicable
	 * 
	 * @var array
	 *
	 */
	protected $parentTextdomains = array(
	// 'className' => array('parent textdomain 1', 'parent textdomain 2', 'etc.') 
	);

	/**
	 * Is current language the default language?
	 * 
	 * @var bool
	 * 
	 */
	protected $isDefaultLanguage = false;

	/**
	 * Construct the translator and set the current language
	 * 
	 * @param Language $currentLanguage
	 *
	 */
	public function __construct(Language $currentLanguage) {
		$currentLanguage->wire($this);
		$this->setCurrentLanguage($currentLanguage);
		$this->rootPath = $this->wire('config')->paths->root; 
		$file = __FILE__; 
		$pos = strpos($file, '/wire/modules/LanguageSupport/'); 
		$this->rootPath2 = $pos ? substr($file, 0, $pos+1) : '';
	}

	/**
	 * Set the current language and reset current stored textdomains
	 * 
	 * @param Language $language
	 * @return $this
	 *
	 */
	public function setCurrentLanguage(Language $language) {

		if($this->currentLanguage && $language->id == $this->currentLanguage->id) return $this; 
		$this->path = $language->filesManager->path(); 

		// we only keep translations for one language in memory at once, 
		// so if the language is changing, we clear out what's already in memory.
		if($this->currentLanguage && $language->id != $this->currentLanguage->id) $this->textdomains = array();
		$this->currentLanguage = $language; 
		$this->isDefaultLanguage = $language->isDefault();

		return $this;
	}

	/**
	 * Return the array template for a textdomain, optionally populating it with data
	 * 
	 * @param string $file
	 * @param string $textdomain
	 * @param array $translations
	 * @return array
	 *
	 */
	protected function textdomainTemplate($file = '', $textdomain = '', array $translations = array()) {
		foreach($translations as $hash => $translation) {
			if(!strlen($translation['text'])) unset($translations[$hash]); 
		}
		return array(
			'file' => $file, 
			'textdomain' => $textdomain, 
			'translations' => $translations
			);
	}

	/**
	 * Given an object instance, return the resulting textdomain string
	 *
 	 * This is accomplished with PHP's ReflectionClass to determine the file where the class lives	
	 * and then convert that to a textdomain string. Once determined, we cache it so that we 
	 * don't have to do this again. 
	 * 
	 * @param Wire|object $o
	 * @return string
	 *
	 */
	protected function objectToTextdomain($o) {

		$class = wireClassName($o, false); 

		if(isset($this->classNamesToTextdomains[$class])) {
			$textdomain = $this->classNamesToTextdomains[$class]; 			

		} else {

			$reflection = new \ReflectionClass($o); 	
			$filename = $reflection->getFileName(); 		
			$textdomain = $this->filenameToTextdomain($filename); 
			$this->classNamesToTextdomains[$class] = $textdomain;
			$parentTextdomains = array();

			// core classes at which translations are no longer applicable
			// $stopClasses = array('Wire', 'WireData', 'WireArray', 'Fieldtype', 'FieldtypeMulti', 'Inputfield', 'Process');
			$stopClasses = array(
				'Wire', 
				'WireData', 
				'WireArray', 
				'Process'
			);
			
			if(__NAMESPACE__) {
				foreach($stopClasses as $class) {
					$stopClass[] = __NAMESPACE__ . "\\$class";
				}
			}

			/** @var \ReflectionClass $parentClass */
			while($parentClass = $reflection->getParentClass()) { 
				if(in_array($parentClass->getShortName(), $stopClasses)) break;
				$parentTextdomains[] = $this->filenameToTextdomain($parentClass->getFileName()); 
				$reflection = $parentClass; 
			}

			$this->parentTextdomains[$textdomain] = $parentTextdomains; 
		}

		return $textdomain;
	}

	/**
	 * Given a filename, convert it to a textdomain string
	 *
	 * @param string $filename
	 * @return string 
	 *
	 */
	public function filenameToTextdomain($filename) {

		if(DIRECTORY_SEPARATOR != '/') $filename = str_replace(DIRECTORY_SEPARATOR, '/', $filename); 

		if(strpos($filename, $this->rootPath) === 0) {
			$filename = str_replace($this->rootPath, '', $filename); 

		} else if($this->rootPath2 && strpos($filename, $this->rootPath2) === 0) {
			// in case /wire/ or /site/ is a symlink
			$filename = str_replace($this->rootPath2, '', $filename); 

		} else { 
			// last resort, may not ever occur, but here anyway
			$pos = strrpos($filename, '/wire/'); 
			if($pos === false) $pos = strrpos($filename, '/site/'); 
			if($pos !== false) $filename = substr($filename, $pos+1);
		}

		// convert FileCompiler paths
		$pos = stripos($filename, '/cache/FileCompiler/');
		if($pos) $filename = substr($filename, $pos+20);

		$textdomain = str_replace(array('/', '\\'), '--', ltrim($filename, '/')); 
		$textdomain = str_replace('.', '-', $textdomain); 

		return strtolower($textdomain);
	}

	/**
	 * Given a textdomain string, convert it to a filename (relative to site root)
	 *
	 * This is determined by loading the textdomain and then grabbing the filename stored in the JSON properties
	 * 
	 * @param string $textdomain
	 * @return string
	 *
	 */
	public function textdomainToFilename($textdomain) {
		if(!isset($this->textdomains[$textdomain])) $this->loadTextdomain($textdomain); 
		return $this->textdomains[$textdomain]['file']; 
	}

	/**
	 * Normalize a string, filename or object to be a textdomain string
	 *
	 * @param string|object $textdomain
	 * @return string
	 *
	 */
	protected function textdomainString($textdomain) {

		if(is_string($textdomain) && (strpos($textdomain, DIRECTORY_SEPARATOR) !== false || strpos($textdomain, '/') !== false)) $textdomain = $this->filenameToTextdomain($textdomain); // @werker #424
			else if(is_object($textdomain)) $textdomain = $this->objectToTextdomain($textdomain); 
			else $textdomain = strtolower($textdomain); 

		// just in case there is an extension on it, remove it 
		if(strpos($textdomain, '.')) $textdomain = basename($textdomain, '.json'); 

		return $textdomain;
	}

	/**
	 * Perform a translation in the given $textdomain for $text to the current language
	 *
	 * @param string|object $textdomain Textdomain string, filename, or object. 
	 * @param string $text Text in default language (EN) that needs to be converted to current language. 
	 * @param string $context Optional context label for the text, to differentiate from others that may be the same in English, but not other languages.
 	 * @return string Translation if available, or original EN version if translation not available.
	 *
	 */
	public function getTranslation($textdomain, $text, $context = '') {
		if($this->wire('hooks')->isHooked('LanguageTranslator::getTranslation()')) {
			// if method has hooks, we let them run
			return $this->__call('getTranslation', array($textdomain, $text, $context));
		} else { 
			// if method has no hooks, we avoid any overhead
			return $this->___getTranslation($textdomain, $text, $context);
		}
	}

	/**
	 * Implementation for the getTranslation() function - you should call getTranslation() without underscores instead.
	 *
	 * @param string|object $textdomain Textdomain string, filename, or object. 
	 * @param string $text Text in default language (EN) that needs to be converted to current language. 
	 * @param string $context Optional context label for the text, to differentiate from others that may be the same in English, but not other languages.
 	 * @return string Translation if available, or original EN version if translation not available.
	 *
	 */
	public function ___getTranslation($textdomain, $text, $context = '') {

		// normalize textdomain to be a string, converting from filename or object if necessary
		$textdomain = $this->textdomainString($textdomain); 
		$_text = $text;

		// if the text is already provided in the proper language then no reason to go further
		// if($this->currentLanguage->id == $this->defaultLanguagePageID) return $text; 

		// hash of original text
		$hash = $this->getTextHash($text . $context);

		// translation textdomain hasn't yet been loaded, so load it
		if(!isset($this->textdomains[$textdomain])) $this->loadTextdomain($textdomain); 

		// see if this translation exists
		if(isset($this->textdomains[$textdomain]['translations'][$hash]['text']) 
			&& strlen($this->textdomains[$textdomain]['translations'][$hash]['text'])) { 

			// translation found
			$text = $this->textdomains[$textdomain]['translations'][$hash]['text'];	

		} else if(!empty($this->parentTextdomains[$textdomain])) { 

			// check parent class textdomains
			foreach($this->parentTextdomains[$textdomain] as $td) { 
				if(!isset($this->textdomains[$td])) $this->loadTextdomain($td); 
				if(!empty($this->textdomains[$td]['translations'][$hash]['text'])) { 
					$text = $this->textdomains[$td]['translations'][$hash]['text'];	
					break;
				}
			}
		}
	
		// see if text is available as a common translation
		if($text === $_text && !$this->isDefaultLanguage) {
			$_text = $this->commonTranslation($text);
			if(!empty($_text)) $text = $_text;
		}

		// if text hasn't changed at this point, we'll be returning it in the provided language since we have no translation

		return $text;
	}


	/**
	 * Return ALL translations for the given textdomain
	 * 
	 * @param string $textdomain
	 * @return array
	 *
	 */
	public function getTranslations($textdomain) {

		// normalize to string
		$textdomain = $this->textdomainString($textdomain); 

		// translation textdomain hasn't yet been loaded, so load it
		if(!isset($this->textdomains[$textdomain])) $this->loadTextdomain($textdomain); 

		// return the translations array
		return $this->textdomains[$textdomain]['translations']; 
		
	}

	/**
	 * Set a translation
	 * 
	 * @param string $textdomain
	 * @param string $text
	 * @param string $translation
	 * @param string $context
	 * @return string
	 *
	 */
	public function setTranslation($textdomain, $text, $translation, $context = '') {

		// get the unique hash identifier for the $text
		$hash = $this->getTextHash($text . $context); 

		return $this->setTranslationFromHash($textdomain, $hash, $translation); 
	} 

	/**
	 * Set a translation using an already known hash
	 * 
	 * @param string $textdomain
	 * @param string $hash
	 * @param string $translation
	 * @return string
	 *
	 */
	public function setTranslationFromHash($textdomain, $hash, $translation) {

		// if the textdomain isn't yet setup, then set it up
		if(!isset($this->textdomains[$textdomain]) || !is_array($this->textdomains[$textdomain])) {
			$this->textdomains[$textdomain] = $this->textdomainTemplate();
		}

		// populate the new translation
		if(strlen($translation)) $this->textdomains[$textdomain]['translations'][$hash] = array('text' => $translation); 
			else unset($this->textdomains[$textdomain]['translations'][$hash]); 

		// return the unique hash used to identify the translation
		return $hash; 
	}

	/**
	 * Remove a translation
	 *
	 * @param string $textdomain
	 * @param string $hash May be the translation hash or the translated text. 
 	 * @return $this
	 *
	 */
	public function removeTranslation($textdomain, $hash) {

		if(empty($hash)) return $this; 

		if(isset($this->textdomains[$textdomain]['translations'][$hash])) {
			// remove by $hash
			unset($this->textdomains[$textdomain]['translations'][$hash]); 

		} else {
			// remove by given translation (in $hash)
			$text = $hash; 
			foreach($this->textdomains[$textdomain]['translations'] as $hash => $translation) {
				if($translation['text'] === $text) {
					unset($this->textdomains[$textdomain]['translations'][$hash]); 
					break;
				}
			}
		}

		return $this; 
	}

	/**
	 * Given original $text, issue a unique MD5 key used to reference it
	 * 
	 * @param string $text
	 * @return string
	 *
	 */
	protected function getTextHash($text) {
		return md5($text); 
	}

	/**
	 * Get the JSON filename where the current languages class translations are
	 * 
	 * @param string $textdomain
	 * @return string
	 *
	 */
	protected function getTextdomainTranslationFile($textdomain) {
		$textdomain = $this->textdomainString($textdomain); 
		return $this->path . $textdomain . ".json";
	}

	/**
	 * Does a json translation file exist for the given textdomain?
	 * 
	 * @param string $textdomain
	 * @return bool
	 * 
	 */
	public function textdomainFileExists($textdomain) {
		$file = $this->getTextdomainTranslationFile($textdomain);
		return is_file($file);
	}

	/**
	 * Load translation group $textdomain into the current language translations
	 * 
	 * @param string $textdomain
	 * @return $this
	 *
	 */
	public function loadTextdomain($textdomain) {

		$textdomain = $this->textdomainString($textdomain); 
		if(isset($this->textdomains[$textdomain]) && is_array($this->textdomains[$textdomain])) return $this;
		$file = $this->getTextdomainTranslationFile($textdomain);

		if(is_file($file)) {
			$data = json_decode(file_get_contents($file), true); 
			$this->textdomains[$textdomain] = $this->textdomainTemplate($data['file'], $data['textdomain'], $data['translations']); 

		} else {
			$this->textdomains[$textdomain] = $this->textdomainTemplate('', $textdomain); 
		}

		return $this; 	
	}

	/**
	 * Given a source file to translate, create a new textdomain
	 *
	 * @param string $filename Filename or textdomain that we will be translating, relative to site root.
	 * @param bool $filenameIsTextdomain Specify true if $filename is a textdomain instead.
	 * @param bool $save Whether to save the language
	 * @return string|bool Returns textdomain string if successful, or false if not. 
	 *
	 */
	public function addFileToTranslate($filename, $filenameIsTextdomain = false, $save = true) {

		if($filenameIsTextdomain) {
			$textdomain = $filename;
			$filename = $this->textdomainToFilename($textdomain);
			// $this->message($textdomain . ": " . $filename);
		} else {
			$textdomain = $this->filenameToTextdomain($filename);
		}
		$this->textdomains[$textdomain] = $this->textdomainTemplate(ltrim($filename, '/'), $textdomain); 
		$file = $this->getTextdomainTranslationFile($textdomain); 
		$result = file_put_contents($file, $this->encodeJSON($this->textdomains[$textdomain]), LOCK_EX); 
		if($result && $this->config->chmodFile) chmod($file, octdec($this->config->chmodFile));

		if($result) {
			$fieldName = 'language_files';
			if(strpos($textdomain, 'wire--') !== 0)	{
				if($this->wire('fields')->get('language_files_site')) {
					$fieldName = 'language_files_site';
				} 
			}
			$this->currentLanguage->$fieldName->add($file); 
			if($save) $this->currentLanguage->save();
		}

		return $result ? $textdomain : false;
	}

	/**
	 * Save the translation group given by $textdomain to disk in its translation file
	 *
 	 * @param string $textdomain
	 * @return int|bool Number of bytes written or false on failure
	 *
	 */
	public function saveTextdomain($textdomain) {
		if(empty($this->textdomains[$textdomain])) return false;
		$data = $this->textdomains[$textdomain];
		//if(empty($data['file'])) $data['file'] = $this->textdomainToFilename($textdomain);
		$json = $this->encodeJSON($data); 
		$file = $this->getTextdomainTranslationFile($textdomain); 
		$result = file_put_contents($file, $json, LOCK_EX); 
		return $result; 
	}

	/**
	 * Unload the given textdomain string from memory
	 * 
	 * @param string $textdomain
	 *
	 */
	public function unloadTextdomain($textdomain) {
		unset($this->textdomains[$textdomain]); 
	}

	/**
	 * Return the data available for the given $textdomain string
	 * 
	 * @param string $textdomain
	 * @return array
	 *
	 */
	public function getTextdomain($textdomain) { 
		$this->loadTextdomain($textdomain); 
		return isset($this->textdomains[$textdomain]) ? $this->textdomains[$textdomain] : array();
	}

	/**
	 * JSON encode language translation data
	 * 
	 * @param string $str
	 * @return string
	 *
	 */
	public function encodeJSON($str) {
		if(defined("JSON_PRETTY_PRINT")) {
			return json_encode($str, JSON_PRETTY_PRINT); 
		} else {
			return json_encode($str); 
		}
	}

	/**
	 * Get a common translation
	 * 
	 * These are commonly used translations that can be used as fallbacks.
	 * 
	 * Returns blank string if given string is not a common phrase. 
	 * Returns given $str if given string is common, but not translated here. 
	 * Returns translated $str if common and translated. 
	 * 
	 * @param string $str
	 * @return string
	 * 
	 */
	public function commonTranslation($str) {
		
		static $level = 0;
		if(strlen($str) >= 15 || $level) return ''; // 15=max length of our common phrases
		$level++;
		$v = '';

		switch(strtolower($str)) {
			case 'edit': $v = $this->_('Edit'); break;
			case 'delete': $v = $this->_('Delete'); break;
			case 'save': $v = $this->_('Save'); break;
			case 'save & exit':
			case 'save and exit':
			case 'save + exit': $v = $this->_('Save + Exit'); break;
			case 'cancel': $v = $this->_('Cancel'); break;
			case 'ok': $v = $this->_('Ok'); break;
			case 'new': $v = $this->_('New'); break;
			case 'add': $v = $this->_('Add'); break;
			case 'add new': $v = $this->_('Add New'); break;
			case 'are you sure?': $v = $this->_('Are you sure?'); break;
			case 'confirm': $v = $this->_('Confirm'); break;
			case 'import': $v = $this->_('Import'); break;
			case 'export': $v = $this->_('Export'); break;
			case 'yes': $v = $this->_('Yes'); break;
			case 'no': $v = $this->_('No'); break;
			case 'on': $v = $this->_('On'); break;
			case 'off': $v = $this->_('Off'); break;
			case 'enabled': $v = $this->_('Enabled'); break;
			case 'disabled': $v = $this->_('Disabled'); break;
			case 'example': $v = $this->_('Example'); break;
			case 'please note': $v = $this->_('Please note:'); break;
			case 'note': $v = $this->_('Note'); break;
			case 'notes': $v = $this->_('Notes'); break;
			case 'settings': $v = $this->_('Settings'); break;
			case 'type': $v = $this->_('Type'); break;
			case 'label': $v = $this->_('Label'); break;
			case 'name': $v = $this->_('Name'); break;
			case 'description': $v = $this->_('Description'); break;
			case 'details': $v = $this->_('Details'); break;
			case 'access': $v = $this->_('Access'); break;
			case 'advanced': $v = $this->_('Advanced'); break;
			case 'icon': $v = $this->_('Icon'); break;
			case 'system': $v = $this->_('System'); break;
			case 'modified': $v = $this->_('Modified'); break;
			case 'error': $v = $this->_('Error'); break;
		}
		
		$level--;
		return $v;
	}

}


