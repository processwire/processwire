<?php namespace ProcessWire;

/**
 * ProcessWire Language Parser
 * 
 * Parses a PHP file to locate all function calls containing translatable text and their optional comments. 
 *
 * Return the results by calling $parser->getUntranslated() and $parser->getComments();
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

class LanguageParser extends Wire {

	/**
	 * Instance of LanguageTranslator
	 *
	 */
	protected $translator; 

	/**
	 * Textdomain for $file provided to this instance
	 *
	 */
	protected $textdomain = '';

	/**
	 * Array of found comments, indexed by hash of text they go with
	 *
	 */
	protected $comments = array();

	/**
	 * Array of found phrases (in English) indexed by hash
	 *
	 */
	protected $untranslated = array();

	/**
	 * Array of phrase alternates, indexed by source phrase
	 * 
	 * @var array
	 * 
	 */
	protected $alternates = array();

	/**
	 * Total number of phrases found
	 *
	 */
	protected $numFound = 0;

	/**
	 * Construct the Language Parser
	 *
	 * @param LanguageTranslator $translator
	 * @param string $file PHP filename to parse
	 *
	 */
	public function __construct(LanguageTranslator $translator, $file) {
		parent::__construct();
		$this->translator = $translator; 
		$this->textdomain = $this->translator->filenameToTextdomain($file); 
		$this->translator->loadTextdomain($this->textdomain); 
		$this->execute($file); 
	}

	/**
	 * Get phrase alternates
	 * 
	 * @param string $hash Specify phrase hash to get alternates or omit to get all alternates
	 * @return array
	 * 
	 */
	public function getAlternates($hash = '') {
		if(empty($hash)) return $this->alternates;
		return isset($this->alternates[$hash]) ? $this->alternates[$hash] : array();
	}

	/**
	 * Return all found comments, indexed by hash
	 * 
	 * @return array
	 *
	 */
	public function getComments() { return $this->comments; }

	/**
	 * Return all found phrases (in untranslated form), indexed by hash
	 * 
	 * @return array
	 *
	 */
	public function getUntranslated() { return $this->untranslated; }

	/**
	 * Return number of phrases found total
	 * 
	 * @return int
	 *
	 */
	public function getNumFound() { return $this->numFound; }

	/**
 	 * Given a hash, return the untranslated text associated with it
	 * 
	 * @param string $hash
	 * @return string|bool Returns untranslated text (string) on success or boolean false if not available
	 *
	 */
	public function getTextFromHash($hash) { 
		return isset($this->untranslated[$hash]) ? $this->untranslated[$hash] : false; 
	}

	/**
	 * Begin parsing given file
	 * 
	 * @param string $file
	 *
	 */
	protected function execute($file) {

		$matches = $this->parseFile($file);

		foreach($matches as $m) {
			// $m[3] is always the text
			if(empty($m)) continue; 
			foreach($m[3] as $key => $text) { 
				$match = $this->buildMatch($m, $key, $text); 
				$this->processMatch($match); 
				if($match['plural']) {
					$match['text'] = $match['plural'];
					$this->processMatch($match); 
				}
			}
		}
	}

	/**
	 * Find text array values and place in alternates
	 * 
	 * This method also converts the __(['a','b','c']) array calls to single value calls like __('a')
	 * as a pre-parser for all parsers that follow it, so they do not need to be * aware of array values 
	 * for translation calls. 
	 * 
	 * @param string $data
	 * 
	 */
	protected function findArrayTranslations(&$data) {
		
		if(!strpos($data, '_([')) return;
		
		$regex = 
			'/((?:->_|\b__|\b_n|\b_x)\(\[\s*)' . // "->_([" or "__([" or "_n([" or "_x(["
			'([\'"])(.+?)(?<!\\\\)\\2' . // 'text1'
			'([^\]]*?\])\s*' . // , 'text2', 'text3' ]"
			'([^)]*\))/m'; // and the remainder of the function call

		$funcTypes = array('->_(' => '>', '__(' => '_', '_n(' => 'n', '_x(' => 'x');
		
		if(!preg_match_all($regex, $data, $m)) return;
			
		foreach($m[0] as $key => $find) {
			
			$func = trim(str_replace('[', '', $m[1][$key])); // "->_([" or "__([" or "_n([" or "_x(["
			$funcType = isset($funcTypes[$func]) ? $funcTypes[$func] : '_';
			$quote = $m[2][$key]; // single quote or double quote ['"]
			$text1 = $m[3][$key]; // first text in array
			$textArrayStr = trim($m[4][$key], ' ,[]'); // the other text phrases in the array (CSV and quoted)
			$theRest = $m[5][$key]; // remainder of function call, i.e. ", __FILE__)" or ", 'context-str'"
			$context = '';

			$trimRest = ltrim($theRest, ', '); 
			if($funcType === 'x' && (strpos($trimRest, '"') === 0 || strpos($trimRest, "'") === 0)) {
				if(preg_match('/^([\'"])(.+?)(?<!\\\\)\\1/', $trimRest, $matches)) {
					$context = $matches[2];
				}
			}
			
			// Convert from: "__(['a', 'b', 'c'])" to "__('a')" and remember 'b' and 'c' alternates
			$replace = $func . $quote . $text1 . $quote . $theRest;
			$data = str_replace($find, $replace, $data);
			$text1 = $this->unescapeText($text1);
			
			// Given string "'b', 'c'" convert to array and place in alternates
			if(preg_match_all('/(^|,\s*)([\'"])(.+?)(?<!\\\\)\\2/', $textArrayStr, $matches)) {
				$hash1 = $this->getTextHash($text1, $context); 
				if(!isset($this->alternates[$hash1])) $this->alternates[$hash1] = array();
				foreach($matches[3] as $text) {
					$text2 = $this->unescapeText($text);
					$hash2 = $this->getTextHash($text, $context); 
					$this->alternates[$hash1][$hash2] = $text2;
				}
			}
		}
	}

	/**
	 * Run regexes on file contents to locate all translation functions
	 * 
	 * @param string $file
	 * @return array
	 *
	 */
	protected function parseFile($file) { 

		$matches = array(
			1 => array(), 	// $this->_('text'); 
			2 => array(),	// __('text', [textdomain]);
			3 => array(),	// _x('text', 'context', [textdomain]) or $this->_x('text', 'context'); 
			4 => array(),	// _n('singular', 'plural', $cnt, [textdomain]) or $this->_n(...); 
		);

		if(!is_file($file)) return $matches; 

		$data = file_get_contents($file); 
		$this->findArrayTranslations($data);

		// Find $this->_('text') style matches
		preg_match_all(	
			'/(>_)\(\s*' . // $this->_( 
			'([\'"])(.+?)(?<!\\\\)\\2' . // "text"
			'\s*\)+(.*)$/m', // and everything else
			$data, $matches[1]
		); 

		// Find __('text', textdomain) style matches
		preg_match_all(	
			'/([\s.=(\\\\,]__|=>__|^__)\(\s*' . // __(
			'([\'"])(.+?)(?<!\\\\)\\2\s*' . // "text"
			'(?:,\s*[^)]+)?\)+(.*)$/m', // , textdomain (optional) and everything else
			$data, $matches[2]
		); 

		// Find _x('text', 'context', textdomain) or $this->_x('text', 'context') style matches
		preg_match_all(	
			'/([\s.=>(\\\\,]_x|^_x)\(\s*' . // _x( or $this->_x(
			'([\'"])(.+?)(?<!\\\\)\\2\s*,\s*' . // "text", 
			'([\'"])(.+?)(?<!\\\\)\\4\s*' . // "context"
			'[^)]*\)+(.*)$/m', // , textdomain (optional) and everything else 
			$data, $matches[3]
		); 

		// Find _n('singular text', 'plural text', $cnt, textdomain) or $this->_n(...) style matches
		preg_match_all(	
			'/([\s.=>(\\\\,]_n|^_n)\(\s*' . // _n( or $this->_n(
			'([\'"])(.+?)(?<!\\\\)\\2\s*,\s*' . // "singular", 
			'([\'"])(.+?)(?<!\\\\)\\4\s*,\s*' . // "plural", 
			'.+?\)+(.*)$/m', // $count, optional textdomain, closing function parenthesis ) and rest of line
			$data, $matches[4]
		); 

		return $matches; 
	}

	/**
	 * Build the match abstracted away from the preg_match result
	 * 
	 * @param array $m
	 * @param int $key
	 * @param string $text
	 * @return array
	 *	
	 */
	protected function buildMatch(array $m, $key, $text) {
	
		// $match is where we store the results generated by this function
		$match = array('text' => $text, 'context' => '', 'plural' => '', 'tail' => '');

		// determine the function type
		$funcType = substr($m[1][$key], 0, 1); // '>' OR '_' , for '$this->_()' OR '__()'
		$funcType2 = substr($m[1][$key], -1); // 'x' OR 'n' OR '_'
		if($funcType2 == 'x' || $funcType2 == 'n') $funcType = $funcType2; 

		// tail, plural and context vary in position according to function type
		if($funcType == 'x') {
			// context function _x()
			$match['tail'] = $m[6][$key]; 
			$match['context'] = $m[5][$key];

		} else if($funcType == 'n') { 
			// plural function _n()
			$match['tail'] = $m[6][$key]; 
			$match['plural'] = $m[5][$key];

		} else {
			// tail containing optional label comment
			$match['tail'] = $m[4][$key]; 
		}

		return $match;
	}

	/**
	 * Process the match and populate $this->untranslated and $this->comments
	 * 
	 * @param array $match
	 *
	 */
	protected function processMatch(array $match) { 

		$text = $this->unescapeText($match['text']);
		$tail = $match['tail'];
		$context = $match['context'];
		$plural = $match['plural'];	
		$comments = '';

		// get the translation for $text in $context
		$translation = $this->translator->getTranslation($this->textdomain, $text, $context);

		// if translation == $text then that means no translation was found, make $translation blank
		if($translation == $text) $translation = ''; 

		// set a pending translation to get the hash
		$hash = $this->translator->setTranslation($this->textdomain, $text, $translation, $context); 
		if(!$hash) return;

		// store the untranslated (English) version of $hash
		$this->untranslated[$hash] = $text; 
		$this->numFound++;

		// check if there are comments in the $tail and record them if so
		if(strpos($tail, '//') !== false) {
			if(preg_match('![^:"\']//(.+)$!', $tail, $matches)) {
				$comments = $matches[1];
			}
		}

		// check if a plural was found and set an automatic comment to indicate which is which
		if($plural) {
			$note = $plural == $text ? "Plural" : "Singular";
			// force note saying Plural or Singular
			$comments = ($comments ? $comments : $text) . " // $note Version"; 

		} else if($context) { 
			$comments = ($comments ? $comments : $text) . " // Context: $context";
		}

		// save the comments indexed to the hash
		if($comments) $this->comments[$hash] = $comments; 
	}

	/**
	 * Replace any escaped characters with non-escaped versions
	 * 
	 * @param string $text
	 * @return string
	 * 
	 */
	protected function unescapeText($text) {
		if(strpos($text, '\\') !== false) {
			$text = str_replace(
				array('\\"', '\\\'', '\\$', '\\n', '\\'), 
				array('"', "'", '$', "\n", '\\'), 
				$text
			);
		}
		return $text;
	}

	/**
	 * Get hash for given text + context
	 * 
	 * @param string $text
	 * @param string $context
	 * @return string
	 * 
	 */
	protected function getTextHash($text, $context) {
		$translation = $this->translator->getTranslation($this->textdomain, $text, $context); // get the translation for $text in $context
		if($translation == $text) $translation = ''; // if translation == $text then that means no translation was found, make $translation blank
		$hash = $this->translator->setTranslation($this->textdomain, $text, $translation, $context);
		if(!$hash) $hash = $text;
		return $hash;
	}

}
