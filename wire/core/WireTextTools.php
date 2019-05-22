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
	 * mbstring support?
	 * 
	 * @var bool
	 * 
	 */
	protected $mb;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->mb = function_exists("mb_internal_encoding");
		parent::__construct();
	}
	
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
	 *  - `linksToUrls` (bool): Convert links to "(url)" rather than removing entirely? (default=true) Since 3.0.132
	 *  - `uppercaseHeadlines` (bool): Convert headline tags to uppercase? (default=false) Since 3.0.132
	 *  - `underlineHeadlines` (bool): Underline headlines with "=" or "-"? (default=true) Since 3.0.132
	 *  - `collapseSpaces` (bool): Collapse extra/redundant extra spaces to single space? (default=true) Since 3.0.132
	 *  - `replacements` (array): Associative array of strings to manually replace. (default=['&nbsp;' => ' '])
	 * @return string
	 *
	 */
	public function markupToText($str, array $options = array()) {
		
		$defaults = array(
			'keepTags' => array(), 
			'linksToUrls' => true, // convert links to just URL rather than removing entirely
			'splitBlocks' => "\n\n",
			'uppercaseHeadlines' => false, 
			'underlineHeadlines' => true, 
			'convertEntities' => true, 
			'listItemPrefix' => '• ', 
			'preIndent' => '', // indent for text within a <pre>
			'collapseSpaces' => true,
			'replacements' => array(
				'&nbsp;' => ' '
			),
			'finishReplacements' => array(), // replacements applied at very end (internal)
		);

		// merge options using arrays
		foreach(array('replacements') as $key) {
			if(!isset($options[$key])) continue;
			$options[$key] = array_merge($defaults[$key], $options[$key]);
		}
		
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
			if(stripos($str, '</p') || stripos($str, '</h') || stripos($str, '</li') || stripos($str, '</bl') || stripos($str, '</div')) {
				$str = preg_replace('!(</(?:p|h\d|ul|ol|pre|blockquote|div)>)!i', '$1' . $options['splitBlocks'], $str);
			}

			// ensure list items are on their own line and prefixed with a bullet
			if(stripos($str, '<li') !== false) {
				$prefix = in_array('li', $options['keepTags']) ? '' : $options['listItemPrefix'];
				$str = preg_replace('![\s\r\n]+<li[^>]*>[\s\r\n]*!i', "\n<li>$prefix", $str);
				if($prefix) $options['replacements']["\n$prefix "] = "\n$prefix"; // prevent extra space
			}

			// convert <br> tags to be just a single newline
			if(stripos($str, '<br') !== false) {
				$str = str_replace(array('<br>', '<br/>', '<br />', '</li>'), "<br>\n", $str);
				while(stripos($str, "\n<br>") !== false) $str = str_replace("\n<br>", "<br>", $str);
				while(stripos($str, "<br>\n\n") !== false) $str = str_replace("<br>\n\n", "<br>\n", $str);
			}

			// make headlines more prominent with underlines or uppercase
			if(($options['uppercaseHeadlines'] || $options['underlineHeadlines']) && stripos($str, '<h') !== false) {
				$topHtag = '';
				if($options['underlineHeadlines']) {
					// determine which is the top level headline tag 
					for($n = 1; $n <= 6; $n++) {
						if(stripos($str, "<h$n") === false) continue;
						$topHtag = "h$n";
						break;
					}
				}
				if(preg_match_all('!<(h[123456])[^>]*>(.+?)</\1>!is', $str, $matches)) {
					foreach($matches[2] as $key => $headline) {
						$fullMatch = $matches[0][$key];
						$tagName = strtolower($matches[1][$key]);
						$underline = '';
						if($options['underlineHeadlines']) {
							$char = $tagName === $topHtag ? '=' : '-';
							$underline = "\n" . str_repeat($char, $this->strlen($headline));
						}
						if($options['uppercaseHeadlines']) $headline = strtoupper($headline);
						$str = str_replace($fullMatch, "<$tagName>$headline</$tagName>$underline", $str);
					}
				}
			}
		
			// convert "<a href='url'>text</a>" tags to "text (url)"
			if($options['linksToUrls'] && stripos($str, '<a ') !== false) {
				if(preg_match_all('!<a\s[^<>]*href=([^\s>]+)[^<>]*>(.+?)</a>!is', $str, $matches)) {
					$links = array();
					foreach($matches[0] as $key => $fullMatch) {
						$href = trim($matches[1][$key], '"\'');
						if(strpos($href, '#') === 0) continue; // do not convert jumplinks
						$anchorText = $matches[2][$key];
						$links[$fullMatch] = "$anchorText ($href)";
					}
					if(count($links)) {
						$str = str_replace(array_keys($links), array_values($links), $str); 
					}
				}
			}
		
			// indent within <pre>...</pre> sections
			if(strlen($options['preIndent']) && strpos($str, '<pre') !== false) {
				if(preg_match_all('!<pre(?:>|\s[^>]*>)(.+?)</pre>!is', $str, $matches)) {
					foreach($matches[0] as $key => $fullMatch) {
						$lines = explode("\n", $matches[1][$key]);
						foreach($lines as $k => $line) {
							$lines[$k] = ':preIndent:' . rtrim($line); 
						}
						$str = str_replace($fullMatch, implode("\n", $lines), $str); 
						$options['finishReplacements'][':preIndent:'] = $options['preIndent'];
					}
				}
			}
		}
		
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
	
		// collapse any redundant/extra whitespace
		if($options['collapseSpaces']) {
			while(strpos($str, '  ') !== false) $str = str_replace('  ', ' ', $str);
		}
		
		// normalize newlines and whitespace around newlines
		while(strpos($str, " \n") !== false) $str = str_replace(" \n", "\n", $str);
		while(strpos($str, "\n ") !== false) $str = str_replace("\n ", "\n", $str);
		while(strpos($str, "\n\n\n") !== false) $str = str_replace("\n\n\n", "\n\n", $str);
		
		if(count($options['finishReplacements'])) {
			$str = str_replace(array_keys($options['finishReplacements']), array_values($options['finishReplacements']), $str); 
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
			$maxLength = isset($options['maxLength']) ? $options['maxLength'] : $this->strlen($str);
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
			if($this->strpos($str, $blockEndChar) !== false) $str = str_replace($blockEndChar, ' ', $str);
			$options['endBlocksWith'] = $blockEndChar;
		}

		// collapse whitespace and strip tags
		$str = $this->collapse($str, $options);
		
		if(trim($options['collapseLinesWith']) && $this->strpos($str, $options['collapseLinesWith'])) {
			// if lines are collapsed with something other than whitespace, avoid using that string
			// when the line already ends with sentence punctuation
			foreach($endSentenceChars as $c) {
				$str = str_replace("$c$options[collapseLinesWith]", "$c ", $str); 
			}
		}

		// if anything above reduced the length of the string enough, return it now
		if($this->strlen($str) <= $maxLength) return $str;

		// get string at maximum possible length
		if($options['visible']) {
			// adjust for only visible length
			$_str = $str;
			$str = $this->substr($str, 0, $maxLength);
			$len = $this->getVisibleLength($str);
			if($len < $maxLength) {
				$maxLength += ($maxLength - $len);
				$str = $this->substr($_str, 0, $maxLength);
			}
			unset($_str);
		} else {
			$str = $this->substr($str, 0, $maxLength);
		}

		// match to closest blocks, like paragraph(s)
		if($type === 'block') {
			$pos = $options['maximize'] ? $this->strrpos($str, $blockEndChar) : $this->strpos($str, $blockEndChar);
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
				$pos = $options['maximize'] ? $this->strrpos($str, $find) : $this->strpos($str, $find);
				if($pos) $tests[] = $pos;
			}
			if(!count($tests)) $type = 'word';
		}

		// find whitespace and last word closest to end of string
		if($type === 'word' || !count($tests)) {
			$pos = $options['maximize'] ? $this->strrpos($str, ' ') : $this->strpos($str, ' ');
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
			$result = trim($this->substr($str, 0, $pos + 1));
			$lastChar = $this->substr($result, -1);
			$result = rtrim($result, $options['trim']);

			if($type === 'sentence' || $type === 'block') {
				// good to go with result as is
			} else if(in_array($lastChar, $endSentenceChars)) {
				// good, end with sentence ending punctuation
			} else if(in_array($lastChar, $punctuationChars)) {
				$trims = ' ';
				foreach($punctuationChars as $c) {
					if($this->strpos($options['noTrim'], $c) !== false) continue;
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
				
				$pos = $options['maximize'] ? $this->strrpos($thisStr, "$find ") : $this->strpos($thisStr, "$find ", $offset);
				
				if(!$pos) continue;
				
				if($find === '.') {
					$testStr = $this->substr($thisStr, 0, $pos + 1);
					if(preg_match($noEndRegex, $testStr, $matches)) {
						// ends with a disallowed word, next time try to match with a shorter string
						if($options['maximize']) {
							$nextStr = $this->substr($testStr, 0, $this->strlen($testStr) - $this->strlen($matches[1]) - 1);
						} else {
							$nextOffset = $this->strlen($testStr);
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
		return $this->strlen($str);
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
	
	/**
	 * Find and return all {placeholder} tags found in given string
	 *
	 * @param string $str String that might contain field {tags}
	 * @param array $options
	 *  - `has` (bool): Specify true to only return true or false if it has tags (default=false).
	 * 	- `tagOpen` (string): The required opening tag character(s), default is '{'
	 *	- `tagClose` (string): The required closing tag character(s), default is '}'
	 * @return array|bool
	 * @since 3.0.126
	 *
	 */
	public function findPlaceholders($str, array $options = array()) {
		
		$defaults = array(
			'has' => false,
			'tagOpen' => '{',
			'tagClose' => '}', 
		);
		
		$options = array_merge($defaults, $options);
		$tags = array();
		$pos1 = strpos($str, $options['tagOpen']);
		
		if($pos1 === false) return $options['has'] ? false : $tags;
		
		if(strlen($options['tagClose'])) {
			$pos2 = strpos($str, $options['tagClose']);
			if($pos2 === false) return $options['has'] ? false : $tags;
		}

		$regex = '/' . preg_quote($options['tagOpen']) . '([-_.|a-zA-Z0-9]+)' . preg_quote($options['tagClose']) . '/';
		if($options['has']) return (bool) preg_match($regex, $str);
		if(!preg_match_all($regex, $str, $matches)) return $tags;
		
		foreach($matches[0] as $key => $tag) {
			$name = $matches[1][$key];
			$tags[$name] = $tag;
		}
			
		return $tags;
	}

	/**
	 * Does the string have any {placeholder} tags in it?
	 *
	 * @param string $str
	 * @param array $options
	 * 	- `tagOpen` (string): The required opening tag character(s), default is '{'
	 *	- `tagClose` (string): The required closing tag character(s), default is '}'
	 * @return bool
	 * @since 3.0.126
	 *
	 */
	public function hasPlaceholders($str, array $options = array()) {
		$options['has'] = true;
		return $this->findPlaceholders($str, $options);
	}
	
	/**
	 * Given a string ($str) and values ($vars), populate placeholder “{tags}” in the string with the values
	 *
	 * - The `$vars` should be an associative array of `[ 'tag' => 'value' ]`.
	 * - The `$vars` may also be an object, in which case values will be pulled as properties of the object.
	 *
	 * By default, tags are specified in the format: {first_name} where first_name is the name of the
	 * variable to pull from $vars, `{` is the opening tag character, and `}` is the closing tag char.
	 *
	 * The tag parser can also handle subfields and OR tags, if `$vars` is an object that supports that.
	 * For instance `{products.title}` is a subfield, and `{first_name|title|name}` is an OR tag.
	 *
	 * ~~~~~
	 * $vars = [ 'foo' => 'FOO!', 'bar' => 'BAR!' ];
	 * $str = 'This is a test: {foo}, and this is another test: {bar}';
	 * echo $sanitizer->getTextTools()->populatePlaceholders($str, $vars);
	 * // outputs: This is a test: FOO!, and this is another test: BAR!
	 * ~~~~~
	 *
	 * @param string $str The string to operate on (where the {tags} might be found)
	 * @param WireData|object|array $vars Object or associative array to pull replacement values from.
	 * @param array $options Array of optional changes to default behavior, including:
	 * 	- `tagOpen` (string): The required opening tag character(s), default is '{'
	 *	- `tagClose` (string): The optional closing tag character(s), default is '}'
	 *	- `recursive` (bool): If replacement value contains tags, populate those too? (default=false)
	 *	- `removeNullTags` (bool): If a tag resolves to a NULL, remove it? If false, tag will remain. (default=true)
	 *	- `entityEncode` (bool): Entity encode the values pulled from $vars? (default=false)
	 *	- `entityDecode` (bool): Entity decode the values pulled from $vars? (default=false)
	 *  - `allowMarkup` (bool): Allow markup to appear in populated variables? (default=true)
	 * @return string String with tags populated.
	 * @since 3.0.126 Use wirePopulateStringTags() function for older versions
	 *
	 */
	public function populatePlaceholders($str, $vars, array $options = array()) {
		
		$defaults = array(
			'tagOpen' => '{', // opening tag (required)
			'tagClose' => '}', // closing tag (optional)
			'recursive' => false, // if replacement value contains tags, populate those too?
			'removeNullTags' => true, // if a tag value resolves to a NULL, remove it? If false, tag will be left in tact.
			'entityEncode' => false, // entity encode values pulled from $vars?
			'entityDecode' => false, // entity decode values pulled from $vars?
			'allowMarkup' => true, // allow markup to appear in populated variables?
		);

		$options = array_merge($defaults, $options);
		$optionsNoRecursive = $options['recursive'] ? array_merge($options, array('recursive' => false)) : $options;
		$replacements = array();
		$tags = $this->findPlaceholders($str, $options);

		// create a list of replacements by finding replacement values in $vars
		foreach($tags as $fieldName => $tag) {
			
			if(isset($replacements[$tag])) continue; // if already found, do not do it again
			$fieldValue = null;

			if(is_object($vars)) {
				if($vars instanceof Page) {
					$fieldValue = $options['allowMarkup'] ? $vars->getMarkup($fieldName) : $vars->getText($fieldName);
				} else if($vars instanceof WireData) {
					$fieldValue = $vars->get($fieldName);
				} else {
					$fieldValue = $vars->$fieldName;
				}
			} else if(is_array($vars)) {
				$fieldValue = isset($vars[$fieldName]) ? $vars[$fieldName] : null;
			}
			
			// if value resolves to null and we are not removing null tags, then do not add to replacements
			if($fieldValue === null && !$options['removeNullTags']) continue;
			
			$fieldValue = (string) $fieldValue;

			if(!$options['allowMarkup'] && strpos($fieldValue, '<') !== false) $fieldValue = strip_tags($fieldValue);
			if($options['entityEncode']) $fieldValue = htmlentities($fieldValue, ENT_QUOTES, 'UTF-8', false);
			if($options['entityDecode']) $fieldValue = html_entity_decode($fieldValue, ENT_QUOTES, 'UTF-8');
			
			if($options['recursive'] && strpos($fieldValue, $options['tagOpen']) !== false) {
				$fieldValue = $this->populatePlaceholders($fieldValue, $vars, $optionsNoRecursive);
			}
		
			$replacements[$tag] = $fieldValue;
		}

		// replace the tags 
		if(count($tags)) {
			$str = str_replace(array_keys($replacements), array_values($replacements), $str);
		}

		return $str; 
	}

	
	/***********************************************************************************************************
	 * MULTIBYTE PHP STRING FUNCTIONS THAT FALLBACK WHEN MBSTRING NOT AVAILABLE
	 * 
	 * These duplicate the equivalent PHP string methods and use exactly the same arguments
	 * and exhibit exactly the same behavior. The only difference is that these methods using
	 * the multibyte string versions when they are available, and fallback to the regular PHP 
	 * string methods when not. Use these functions only when that behavior is okay. 
	 * 
	 */

	/**
	 * Get part of a string
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $str
	 * @param int $start
	 * @param int|null $length Max chars to use from str. If omitted or NULL, extract all characters to the end of the string.
	 * @return string
	 * @see https://www.php.net/manual/en/function.substr.php
	 * 
	 */
	public function substr($str, $start, $length = null) {
		return $this->mb ? mb_substr($str, $start, $length) : substr($start, $start, $length);
	}

	/**
	 * Find position of first occurrence of string in a string
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $haystack
	 * @param string $needle
	 * @param int $offset
	 * @return bool|false|int
	 * @see https://www.php.net/manual/en/function.strpos.php
	 * 
	 */
	public function strpos($haystack, $needle, $offset = 0) {
		return $this->mb ? mb_strpos($haystack, $needle, $offset) : strpos($haystack, $needle, $offset);
	}

	/**
	 * Find the position of the first occurrence of a case-insensitive substring in a string
	 * 
	 * #pw-group-PHP-function-alternates
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int $offset
	 * @return bool|false|int
	 * @see https://www.php.net/manual/en/function.stripos.php
	 *
	 */
	public function stripos($haystack, $needle, $offset = 0) {
		return $this->mb ? mb_stripos($haystack, $needle, $offset) : stripos($haystack, $needle, $offset);
	}

	/**
	 * Find the position of the last occurrence of a substring in a string
	 * 
	 * #pw-group-PHP-function-alternates
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int $offset
	 * @return bool|false|int
	 * @see https://www.php.net/manual/en/function.strrpos.php
	 *
	 */
	public function strrpos($haystack, $needle, $offset = 0) {
		return $this->mb ? mb_strrpos($haystack, $needle, $offset) : strrpos($haystack, $needle, $offset);
	}

	/**
	 * Find the position of the last occurrence of a case-insensitive substring in a string
	 * 
	 * #pw-group-PHP-function-alternates
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int $offset
	 * @return bool|false|int
	 * @see https://www.php.net/manual/en/function.strripos.php
	 *
	 */
	public function strripos($haystack, $needle, $offset = 0) {
		return $this->mb ? mb_strripos($haystack, $needle, $offset) : strripos($haystack, $needle, $offset);
	}

	/**
	 * Get string length
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $str
	 * @return int
	 * @see https://www.php.net/manual/en/function.strlen.php
	 * 
	 */
	public function strlen($str) {
		return $this->mb ? mb_strlen($str) : strlen($str);
	}

	/**
	 * Make a string lowercase
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $str
	 * @return string
	 * @see https://www.php.net/manual/en/function.strtolower.php
	 * 
	 */
	public function strtolower($str) {
		return $this->mb ? mb_strtolower($str) : strtolower($str);
	}

	/**
	 * Make a string uppercase
	 * 
	 * #pw-group-PHP-function-alternates
	 *
	 * @param string $str
	 * @return string
	 * @see https://www.php.net/manual/en/function.strtoupper.php
	 *
	 */
	public function strtoupper($str) {
		return $this->mb ? mb_strtoupper($str) : strtoupper($str);
	}

	/**
	 * Count the number of substring occurrences
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $haystack
	 * @param string $needle
	 * @return int
	 * @see https://www.php.net/manual/en/function.substr-count.php
	 * 
	 */
	public function substrCount($haystack, $needle) {
		return $this->mb ? mb_substr_count($haystack, $needle) : substr_count($haystack, $needle); 
	}

	/**
	 * Find the first occurrence of a string
	 * 
	 * #pw-group-PHP-function-alternates
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param bool $beforeNeedle Return part of haystack before first occurrence of the needle? (default=false)
	 * @return false|string
	 * @see https://www.php.net/manual/en/function.strstr.php
	 *
	 */
	public function strstr($haystack, $needle, $beforeNeedle = false) {
		return $this->mb ? mb_strstr($haystack, $needle, $beforeNeedle) : strstr($haystack, $needle, $beforeNeedle);
	}

	/**
	 * Find the first occurrence of a string (case insensitive)
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $haystack
	 * @param string $needle
	 * @param bool $beforeNeedle Return part of haystack before first occurrence of the needle? (default=false)
	 * @return false|string
	 * @see https://www.php.net/manual/en/function.stristr.php
	 * 
	 */
	public function stristr($haystack, $needle, $beforeNeedle = false) {
		return $this->mb ? mb_stristr($haystack, $needle, $beforeNeedle) : stristr($haystack, $needle, $beforeNeedle); 
	}


	/**
	 * Find the last occurrence of a character in a string
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $haystack
	 * @param string $needle Only first given character used
	 * @return false|string
	 * @see https://www.php.net/manual/en/function.strrchr.php
	 * 
	 */
	public function strrchr($haystack, $needle) {
		return $this->mb ? mb_strrchr($haystack, $needle) : strrchr($haystack, $needle); 
	}

	/**
	 * Strip whitespace (or other characters) from the beginning and end of a string
	 * 
	 * #pw-group-PHP-function-alternates
	 * 
	 * @param string $str
	 * @param string $chars Omit for default
	 * @return string
	 * 
	 */
	public function trim($str, $chars = '') {
		if(!$this->mb) return $chars === '' ? trim($str) : trim($str, $chars);
		return $this->wire('sanitizer')->trim($str, $chars);
	}
	

}
