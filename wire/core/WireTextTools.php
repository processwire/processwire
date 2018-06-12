<?php namespace ProcessWire;

/**
 * ProcessWire Text Tools
 *
 * #pw-summary Specific text and markup tools for ProcessWire $sanitizer and elsewhere.
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.101
 *
 *
 */

class WireTextTools extends Wire {
	
	/**
	 * Convert HTML markup to readable text
	 * 
	 * Like PHP’s strip_tags but with some small improvements in HTML-to-text conversion that
	 * improves the readability of the text. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $str String to convert to text
	 * @param array $options 
	 *  - `keepTags` (array): Tag names to keep in returned value, i.e. [ "em", "strong" ]. (default=none)
	 *  - `splitBlocks` (string): String to split paragraph and header elements. (default="\n\n")
	 *  - `convertEntities` (bool): Convert HTML entities to plain text equivalents? (default=true)
	 *  - `listItemPrefix` (string): Prefix for converted list item `<li>` elements. (default='• ')
	 *  - `replacements` (array): Associative array of strings to manually replace. (default=['&nbsp;' => ' '])
	 * @return string
	 *
	 */
	public function markupToText($str, array $options = array()) {

		$defaults = array(
			'keepTags' => array(), 
			'splitBlocks' => "\n\n",
			'convertEntities' => true, 
			'listItemPrefix' => '• ', 
			'replacements' => array(
				'&nbsp;' => ' '
			),
		);

		$options = array_merge($defaults, $options);

		if(strpos($str, '>') !== false) {

			// strip out everything up to and including </head>, if present
			if(strpos($str, '</head>') !== false) list(, $str) = explode('</head>', $str); 

			// ensure tags are separated by whitespace
			$str = str_replace('><', '> <', $str);

			// normalize newlines
			if(strpos($str, "\r") !== false) {
				$str = str_replace(array("\r\n", "\r"), "\n", $str);
			}

			// normalize tabs to spaces
			if(strpos($str, "\t") !== false) {
				$str = str_replace("\t", " ", $str);
			}

			// ensure paragraphs and headers are followed by two newlines
			if(stripos($str, '</p>') || stripos($str, '</h')) {
				$str = preg_replace('!(</(?:p|h\d)>)!i', '$1' . $options['splitBlocks'], $str);
			}

			// ensure list items are on their own line and prefixed with a bullet
			if(stripos($str, '<li') !== false) {
				$prefix = in_array('li', $options['keepTags']) ? '' : $options['listItemPrefix']; 
				$str = preg_replace('![\s\r\n]+<li[^>]*>!i', "\n<li>$prefix", $str);
			}

			// convert <br> tags to be just a single newline
			if(stripos($str, '<br') !== false) {
				$str = str_replace(array('<br>', '<br/>', '<br />'), "<br>\n", $str);
				while(stripos($str, "\n<br>") !== false) $str = str_replace("\n<br>", "<br>", $str);
				while(stripos($str, "<br>\n\n") !== false) $str = str_replace("<br>\n\n", "<br>\n", $str);
			}
		}
		
		// normalize newlines and whitespace around newlines
		while(strpos($str, " \n") !== false) $str = str_replace(" \n", "\n", $str);
		while(strpos($str, "\n ") !== false) $str = str_replace("\n ", "\n", $str);
		while(strpos($str, "\n\n\n") !== false) $str = str_replace("\n\n\n", "\n\n", $str);

		// strip tags
		if(count($options['keepTags'])) {
			// some tags will be allowed to remain
			$keepTags = '';
			foreach($options['keepTags'] as $tag) {
				$keepTags .= "<" . trim($tag, "<>") . ">";
			}
			$str = strip_tags($str, $keepTags);

		} else {
			// not allowing any tags
			$str = strip_tags($str);
			// if any possible tag characters remain, drop them now
			$str = str_replace(array('<', '>'), ' ', $str);
		}

		// apply any other replacements
		foreach($options['replacements'] as $find => $replace) {
			$str = str_ireplace($find, $replace, $str);
		}

		// convert entities to plain text equivalents
		if($options['convertEntities'] && strpos($str, '&') !== false) {
			$str = $this->wire('sanitizer')->unentities($str);
		}

		return trim($str);
	}

	/**
	 * Remove (or close) unclosed HTML tags from given string
	 *
	 * Remove unclosed tags:
	 * ---------------------
	 * At present, if it finds an unclosed tag, it removes all tags of the same kind.
	 * This is in order to keep the function fast, by delegating what it can to strip_tags().
	 * This is sufficient for our internal use here, but may not be ideal for all situations.
	 * 
	 * Fix/close unclosed tags:
	 * ------------------------
	 * When the remove option is false, it will attempt to close unclosed tags rather than 
	 * remove them. It doesn't know exactly where they should be closed, so it appends the 
	 * close tags to the end of the string. 
	 * 
	 * @param string $str
	 * @param bool $remove Remove unclosed tags? If false, it will attempt to close them instead. (default=true)
	 * @param array $options
	 *  - `ignoreTags` (array): Tags that can be ignored because they close themselves. (default=per HTML spec)
	 * @return string
	 *
	 */
	public function fixUnclosedTags($str, $remove = true, $options = array()) {
		
		$defaults = array(
			'ignoreTags' => array(
				'area','base','br','col','command','embed','hr','img','input',
				'keygen','link','menuitem','meta','param','source','track','wbr',
			),
		);

		if(isset($options['ignoreTags'])) {
			// merge user specified ignoreTags with our defaults so that both are used
			$options['ignoreTags'] = array_merge($defaults['ignoreTags'], $options['ignoreTags']);
		}
		
		$options = array_merge($defaults, $options);
		$tags = array();
		$unclosed = array();

		$n1 = substr_count($str, '>');
		$n2 = substr_count($str, '</');

		if($n1) $n1 = $n1 / 2;
		
		// if the quantity of ">" is equal to double the quantity of "</" then early exit
		if($n1 === $n2) return $str;
	
		// now check for string possibly ending with a partial tag, and remove if present
		$n1 = strrpos($str, '<');
		$n2 = strrpos($str, '>');
		if($n1 > $n2) {
			// string might end with a partial tag, i.e. "<span"
			$test = substr($str, $n1 + 1, 1); // i.e. "s" from "<span", or "<" is last char in the string
			if(ctype_alpha($test) || $test === false || $test === '') {
				// going to assume this is a tag, so trucate 
				$str = substr($str, 0, $n1 - 1);
			}
		}

		// find all open tags
		if(!preg_match_all('!<([a-z]+[a-z0-9]*)(>|\s*/>|\s[^>]+>)!i', $str, $matches)) return $str;

		foreach($matches[1] as $key => $tag) {
			if(strpos($matches[2][$key], '/>') !== false) continue; // ignore self closing tags
			if(in_array(strtolower($tag), $options['ignoreTags'])) continue; 
			$tags[$tag] = $tag;
		}

		// count appearances of found tags
		foreach($tags as $tag) {
			// count number of open tags of this type
			$openQty = substr_count($str, "<$tag>") + substr_count($str, "<$tag ");
			// count number of closing tags of this type
			$closeQty = substr_count($str, "</$tag>");
			// if quantities do not match, mark tag for deletion
			if($openQty !== $closeQty) {
				unset($tags[$tag]);
				$unclosed[] = $tag;
			}
		}
		

		if(count($unclosed)) {
			if($remove) {
				// strip all tags except those where open/close quantity matched
				$keepTags = count($tags) ? '<' . implode('><', $tags) . '>' : '';
				$str = strip_tags($str, $keepTags);
			} else {
				foreach($unclosed as $tag) {
					$str .= "</$tag>";
				}
			}
		}

		return $str;
	}

	/**
	 * Collapse string to plain text that all exists on a single long line without destroying words/punctuation.
	 *
	 * @param string $str String to collapse
	 * @param array $options
	 *  - `stripTags` (bool): Strip markup tags? (default=true)
	 *  - `keepTags` (array): Array of tag names to keep, if stripTags==true. (default=[])
	 *  - `collapseLinesWith` (string): String to collapse newlines with. (default=' ')
	 *  - `endBlocksWith` (string): Character or string to insert to identify paragraph/header separation (default='')
	 *  - `convertEntities` (bool): Convert entity-encoded characters to text? (default=true)
	 * @return mixed|string
	 *
	 */
	public function collapse($str, array $options = array()) {

		$defaults = array(
			'stripTags' => true,
			'keepTags' => array(),
			'collapseLinesWith' => ' ',
			'endBlocksWith' => '',
			'convertEntities' => true,
		);

		$options = array_merge($defaults, $options);

		if($options['stripTags']) {
			$str = $this->markupToText($str, array(
				'convertEntities' => $options['convertEntities'],
				'keepTags' => $options['keepTags'],
			));
			if(!strlen($str)) return $str;
		}

		// character that we collapse lines with
		$r = $options['collapseLinesWith'];

		// convert any tabs to space
		if(strpos($str, "\t") !== false) {
			$str = str_replace("\t", " ", $str);
		}

		// convert CRs to LFs
		if(strpos($str, "\r") !== false) {
			$str = str_replace(array("\r\n", "\r"), "\n", $str);
		}

		// collapse whitespace that appears before or after newlines
		while(strpos($str, " \n") !== false) $str = str_replace(" \n", "\n", $str);
		while(strpos($str, "\n ") !== false) $str = str_replace("\n ", "\n", $str);

		// convert redundant LFs to no more than double LFs
		while(strpos($str, "\n\n\n") !== false) {
			$str = str_replace("\n\n\n", "\n\n", $str);
		}

		// add character to indicate blocks, when asked for
		if(!empty($options['endBlocksWith'])) {
			$str = str_replace("\n\n", "$options[endBlocksWith]\n\n", $str);
		}

		// replace all types of newlines
		$str = str_replace(array("\r\n", "\r", "\n\n", "\n"), $r, $str);

		// while there are consecutives of our collapse string, reduce them to one
		while(strpos($str, "$r$r") !== false) {
			$str = str_replace("$r$r", $r, $str);
		}

		if($r !== $defaults['collapseLinesWith']) {
			// replacement of whitespace with something other than another single whitespace
			// so collapse consecutive spaces to one space, since this would not be already done
			while(strpos($str, "  ") !== false) {
				$str = str_replace("  ", " ", $str);
			}
			// use space rather than replacement char when left side already ends with punctuation
			foreach($this->getPunctuationChars() as $c) {
				if(strpos($str, "$c$r")) $str = str_replace("$c$r", "$c ", $str);
			}
		}

		return trim($str);
	}

	/**
	 * Truncate string to given maximum length without breaking words
	 * 
	 * This method can truncate between words, sentences, punctuation or blocks (like paragraphs). 
	 * See the `type` option for details on how it should truncate. By default it truncates between
	 * words. Description of types:
	 * 
	 * - word: truncate to closest word.
	 * - punctuation: truncate to closest punctuation within sentence. 
	 * - sentence: truncate to closest sentence.
	 * - block: truncate to closest block of text (like a paragraph or headline). 
	 * 
	 * Note that if your specified `type` is something other than “word”, and it cannot be matched 
	 * within the maxLength, then it will attempt a different type. For instance, if you specify
	 * “sentence” as the type, and it cannot match a sentence, it will try to match to “punctuation”
	 * instead. If it cannot match that, then it will attempt “word”. 
	 * 
	 * HTML will be stripped from returned string. If you want to keep some tags use the `keepTags` or `keepFormatTags`
	 * options to specify what tags are allowed to remain. The `keepFormatTags` option that, when true, will make it
	 * retain all HTML inline text formatting tags. 
	 * 
	 * ~~~~~~~
	 * // Truncate string to closest word within 150 characters
	 * $s = $sanitizer->truncate($str, 150); 
	 * 
	 * // Truncate string to closest sentence within 300 characters
	 * $s = $sanitizer->truncate($str, 300, 'sentence'); 
	 * 
	 * // Truncate with options
	 * $s = $sanitizer->truncate($str, [
	 *   'type' => 'punctuation',
	 *   'maxLength' => 300,
	 *   'visible' => true,
	 *   'more' => '…'
	 * ]);
	 * ~~~~~~~
	 *
	 * @param string $str String to truncate
	 * @param int|array $maxLength Maximum length of returned string, or specify $options array here.
	 * @param array|string $options Options array, or specify `type` option (string).
	 *  - `type` (string): Preferred truncation type of word, punctuation, sentence, or block. (default='word')
	 *       This is a “preferred type”, not an absolute one, because it will adjust to match what it can within your maxLength.
	 *  - `maxLength` (int): Max characters for truncation, used only if $options array substituted for $maxLength argument.
	 *  - `maximize` (bool): Include as much as possible within specified type and max-length? (default=true)
	 *       If you specify false for the maximize option, it will truncate to first word, puncutation, sentence or block.
	 *  - `visible` (bool): When true, invisible text (markup, entities, etc.) does not count towards string length. (default=false)
	 *  - `trim` (string): Characters to trim from returned string. (default=',;/ ')
	 *  - `noTrim` (string): Never trim these from end of returned string. (default=')]>}”»')
	 *  - `more` (string): Append this to truncated strings that do not end with sentence punctuation. (default='…')
	 *  - `keepTags` (array): HTML tags that should be kept in returned string. (default=[])
	 *  - `keepFormatTags` (bool): Keep HTML text-formatting tags? Simpler alternative to keepTags option. (default=false)
	 *  - `collapseLinesWith` (string): String to collapse lines with where the first is not punctuated. (default=' … ')
	 *  - `convertEntities` (bool): Convert HTML entities to non-entity characters? (default=false)
	 *  - `noEndSentence` (string): Strings that sentence may not end with, space-separated values (default='Mr. Mrs. …')
	 * @return string
	 *
	 */
	function truncate($str, $maxLength, $options = array()) {

		$defaults = array(
			'type' => 'word', // word, punctuation, sentence, or block
			'maximize' => true, // include as much as possible within the type and maxLength (false=include as little as possible)
			'visible' => false, // when true, invisible text (markup, entities, etc.) does not count towards string length. (default=false)
			'trim' => $this->_(',;/') . ' ', // Trim these characters from the end of the returned string
			'noTrim' => $this->_(')]>}”»'), // Never trim these characters from end of returned string
			'more' => '…', // Append to truncated strings that do not end with sentence punctuation
			'stripTags' => true, // strip HTML tags? (currently required, see keepTags to keep some)
			'keepTags' => array(), // if strip HTML tags is true, optional array of tag names you want to keep
			'keepFormatTags' => false, // alternative to keepTags: keep just inline text format tags like strong, em, etc. 
			'collapseWhitespace' => true, // collapsed whitespace (currently required)
			'collapseLinesWith' => ' ' . $this->_('…') . ' ', // String placed between joined lines (like from paragraphs)
			'convertEntities' => false, // convert entity encoded characters to non-entity equivalents? (default=false)
			'noEndSentence' => $this->_('Mr. Mrs. Ms. Dr. Hon. PhD. i.e. e.g.'), // When in sentence type, words that do not end the sentence (space-separated)
		);

		if(!strlen($str)) return '';

		if(is_string($options) && ctype_alpha($options)) {
			$defaults['type'] = $options;
			$options = array();
		}

		if(is_array($maxLength)) {
			$options = $maxLength;
			if(!isset($options['maxLength'])) $options['maxLength'] = 0;
			$maxLength = $options['maxLength'];
		} else if(is_string($maxLength) && ctype_alpha($maxLength)) {
			$options['type'] = $maxLength;
			$maxLength = isset($options['maxLength']) ? $options['maxLength'] : mb_strlen($str);
		}

		if(!$maxLength) $maxLength = 255;
		$options = array_merge($defaults, $options);
		$type = $options['type'];
		$str = trim($str);
		$blockEndChar = '¶';
		$tests = array();
		$punctuationChars = $this->getPunctuationChars();
		$endSentenceChars = $this->getPunctuationChars(true);

		if($options['keepFormatTags']) {
			$options['keepTags'] = array_merge($options['keepTags'], array(
				'abbr','acronym','b','big','cite','code','em','i','kbd', 'q','samp','small','span','strong','sub','sup','time','var',
			));
		}

		if($type === 'block') {
			if(mb_strpos($str, $blockEndChar) !== false) $str = str_replace($blockEndChar, ' ', $str);
			$options['endBlocksWith'] = $blockEndChar;
		}

		// collapse whitespace and strip tags
		$str = $this->collapse($str, $options);
		
		if(trim($options['collapseLinesWith']) && mb_strpos($str, $options['collapseLinesWith'])) {
			// if lines are collapsed with something other than whitespace, avoid using that string
			// when the line already ends with sentence punctuation
			foreach($endSentenceChars as $c) {
				$str = str_replace("$c$options[collapseLinesWith]", "$c ", $str); 
			}
		}

		// if anything above reduced the length of the string enough, return it now
		if(mb_strlen($str) <= $maxLength) return $str;

		// get string at maximum possible length
		if($options['visible']) {
			// adjust for only visible length
			$_str = $str;
			$str = mb_substr($str, 0, $maxLength);
			$len = $this->getVisibleLength($str);
			if($len < $maxLength) {
				$maxLength += ($maxLength - $len);
				$str = mb_substr($_str, 0, $maxLength);
			}
			unset($_str);
		} else {
			$str = mb_substr($str, 0, $maxLength);
		}

		// match to closest blocks, like paragraph(s)
		if($type === 'block') {
			$pos = $options['maximize'] ? mb_strrpos($str, $blockEndChar) : mb_strpos($str, $blockEndChar);
			if($pos === false) {
				$type = 'sentence';
			} else {
				$tests[] = $pos;
				$options['trim'] .= $blockEndChar;
			}
		}

		// find sentences closest to end	
		if($type === 'sentence') {
			$this->truncateSentenceTests($str, $tests, $endSentenceChars, $options);
			if(!count($tests)) $type = 'punctuation';
		}

		// find punctuation closes to end of string
		if($type === 'punctuation') {
			foreach($punctuationChars as $find) {
				$pos = $options['maximize'] ? mb_strrpos($str, $find) : mb_strpos($str, $find);
				if($pos) $tests[] = $pos;
			}
			if(!count($tests)) $type = 'word';
		}

		// find whitespace and last word closest to end of string
		if($type === 'word' || !count($tests)) {
			$pos = $options['maximize'] ? mb_strrpos($str, ' ') : mb_strpos($str, ' ');
			if($pos) $tests[] = $pos;
		}

		// if we didn't find any place to truncate, just return exact truncated string
		if(!count($tests)) {
			return trim($str, $options['trim']) . $options['more'];
		}

		// we found somewhere to truncate, so truncate at the longest one possible
		if($options['maximize']) {
			sort($tests);
		} else {
			rsort($tests);
		}

		// process our tests
		do {
			$pos = array_pop($tests);
			$result = trim(mb_substr($str, 0, $pos + 1));
			$lastChar = mb_substr($result, -1);
			$result = rtrim($result, $options['trim']);

			if($type === 'sentence' || $type === 'block') {
				// good to go with result as is
			} else if(in_array($lastChar, $endSentenceChars)) {
				// good, end with sentence ending punctuation
			} else if(in_array($lastChar, $punctuationChars)) {
				$trims = ' ';
				foreach($punctuationChars as $c) {
					if(mb_strpos($options['noTrim'], $c) !== false) continue;
					if(in_array($c, $endSentenceChars)) continue;
					$trims .= $c;
				}
				$result = rtrim($result, $trims) . $options['more'];
			} else {
				$result .= $options['more'];
			}

		} while(!strlen($result) && count($tests));

		// make sure we didn't break any HTML tags as a result of truncation
		if(strlen($result) && count($options['keepTags']) && strpos($result, '<') !== false) {
			$result = $this->fixUnclosedTags($result);
		}
		
		return $result;
	}

	/**
	 * Helper to truncate() method, generate tests/positions for where sentences end
	 * 
	 * @param string $str
	 * @param array $tests Tests to append found positions to
	 * @param array $endSentenceChars
	 * @param array $options Options provided to truncate method
	 * 
	 */
	protected function truncateSentenceTests($str, array &$tests, array $endSentenceChars, array $options) {
		
		$chars = $endSentenceChars;
		$thisStr = $str;
		$nextStr = '';
		$nextOffset = 0;
		$offset = 0; // offset used for maximize==false mode only
		$n = 0;
		
		// regex matches specified words, plus digits or single letters followed by period
		$noEndRegex = '!\b(' . str_replace(' ', '|', preg_quote($options['noEndSentence'])) . '|\d+\.|\w\.)$!';
		
		do {
			
			if($nextStr) {
				$offset = $nextOffset;
				$thisStr = $nextStr;
				$nextStr = '';
				$chars = array('.');
			}
			
			foreach($chars as $find) {
				
				$pos = $options['maximize'] ? mb_strrpos($thisStr, "$find ") : mb_strpos($thisStr, "$find ", $offset);
				
				if(!$pos) continue;
				
				if($find === '.') {
					$testStr = mb_substr($thisStr, 0, $pos + 1);
					if(preg_match($noEndRegex, $testStr, $matches)) {
						// ends with a disallowed word, next time try to match with a shorter string
						if($options['maximize']) {
							$nextStr = mb_substr($testStr, 0, mb_strlen($testStr) - mb_strlen($matches[1]) - 1);
						} else {
							$nextOffset = mb_strlen($testStr);
						}
						continue;
					}
				}
				
				$tests[] = $pos;
			}
			
		} while(strlen($nextStr) && ++$n < 3);
	}

	/**
	 * Return visible length of string, which is length not counting markup or entities
	 * 
	 * @param string $str
	 * @return int
	 * 
	 */
	public function getVisibleLength($str) {
		if(strpos($str, '>')) {
			$str = strip_tags($str);
		}
		if(strpos($str, '&') !== false && strpos($str, ';')) {
			$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		}
		return mb_strlen($str);
	}

	/**
	 * Get array of punctuation characters
	 * 
	 * @param bool $sentence Get only sentence-ending punctuation
	 * @return array|string
	 * 
	 */
	public function getPunctuationChars($sentence = false) {
		if($sentence) {
			$s = $this->_('. ? !'); // Sentence ending punctuation characters (must be space-separated)
		} else {
			$s = $this->_(', : . ? ! “ ” „ " – -- ( ) [ ] { } « »'); // All punctuation characters (must be space-separated)
		}
		return explode(' ', $s); 
	}

}
