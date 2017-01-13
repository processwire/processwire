<?php namespace ProcessWire;

/**
 * ProcessWire Markup Regions
 * 
 * Supportings finding and manipulating of markup regions in an HTML document. 
 *
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireMarkupRegions extends Wire {

	/**
	 * Locate and return all regions of markup having the given attribute
	 * 
	 * @param string $selector Specify one of the following:
	 *  - Name of an attribute that must be present, i.e. "data-region", or "attribute=value" or "tag[attribute=value]".
	 *  - Specify `#name` to match a specific `id='name'` attribute.
	 *  - Specify `.name` or `tag.name` to match a specific `class='name'` attribute (class can appear anywhere in class attribute).
	 *  - Specify `.name*` to match class name starting with a name prefix. 
	 *  - Specify `<tag>` to match all of those HTML tags (i.e. `<head>`, `<body>`, `<title>`, `<footer>`, etc.)
	 * @param string $markup HTML document markup to perform the find in.
	 * @param array $options Optional options to modify default behavior: 
	 *  - `single` (bool): Return just the markup from the first matching region (default=false).
	 *  - `verbose` (bool): Specify true to return array of verbose info detailing what was found (default=false). 
	 *  - `wrap` (bool): Include wrapping markup? Default is false for id or attribute matches, and true for class matches.
	 *  - `max` (int): Maximum allowed regions to return (default=500).
	 *  - `exact` (bool): Return region markup exactly as-is? (default=false). Specify true when using return values for replacement.
	 *  - `leftover` (bool): Specify true if you want to return a "leftover" key in return value with leftover markup. 
	 * @return array Returns one of the following: 
	 *  - Associative array of [ 'id' => 'markup' ] when finding specific attributes or #id attributes. 
	 *  - Regular array of markup regions when finding region shaving a specific class attribute. 
	 *  - Associative array of verbose information when the verbose option is used. 
	 * @throws WireException if given invalid $find string
	 * 
	 */
	public function find($selector, $markup, array $options = array()) {
	
		if(strpos($selector, ',')) return $this->findMulti($selector, $markup, $options);
		
		$defaults = array(
			'single' => false, 
			'verbose' => false, 
			'wrap' => null,
			'max' => 500, 
			'exact' => false, 
			'leftover' => false,
		);

		$options = array_merge($defaults, $options);
		$selectorInfo = $this->parseFindSelector($selector);
		$tests = $selectorInfo['tests'];
		$findTag = $selectorInfo['findTag'];
		$hasClass = $selectorInfo['hasClass'];
		$regions = array();
		$removeMarkups = array();
		
		// strip out comments if markup isn't required to stay the same
		if(!$options['exact']) $markup = $this->stripRegions('<!--', $markup);
		
		// determine auto value for wrap option
		if(is_null($options['wrap'])) $options['wrap'] = $hasClass ? true : false;

		$startPos = 0;
		$whileCnt = 0;

		do {
			// find all the positions where each test appears
			$positions = array();
			foreach($tests as $str) {
				$pos = stripos($markup, $str, $startPos);
				if($pos !== false) {
					if($str[0] == '<') $pos++; // HTML tag match, bump+1 to enable match
					$positions[$pos] = $pos;
				}
			}
			
			// if no tests matched, we can abort now
			if(empty($positions)) break;
			
			// sort the matching test positions closest to furthest and get the first match
			ksort($positions);
			$pos = reset($positions);	
			$startPos = $pos;
			$markupBefore = substr($markup, 0, $pos);
			$markupAfter = substr($markup, $pos);
			$openTagPos = strrpos($markupBefore, '<');
			$closeOpenTagPos = strpos($markupAfter, '>');
			$startPos += $closeOpenTagPos; // for next iteration
		
			// if the orders of "<" and ">" aren't what's expected in our markupBefore and markupAfter, then abort
			$testPos = strpos($markupAfter, '<');
			if($testPos !== false && $testPos < $closeOpenTagPos) continue;
			if(strrpos($markupBefore, '>') > $openTagPos) continue; 
	
			// build the HTML tag by combining the halfs from markupBefore and markupAfter
			$tag = substr($markupBefore, $openTagPos) . substr($markupAfter, 0, $closeOpenTagPos + 1);
			$tagInfo = $this->getTagInfo($tag);
		
			// pre-checks to make sure this iteration is allowed
			if($findTag && $tagInfo['name'] !== $findTag) continue;
			if($hasClass && !$this->hasClass($hasClass, $tagInfo['classes'])) continue;

			// build the region (everything in $markupAfter that starts after the ">")
			$region = substr($markupAfter, $closeOpenTagPos + 1);
			$region = $this->getTagRegion($region, $tagInfo, $options);

			if($options['single']) {
				// single mode means we just return the markup
				$regions = $region;
				break;
				
			} else if($hasClass) {
				// add to regions array as regions having class
				$regions[] = $region;
				
			} else if($tagInfo['id']) {
				// add to regions array as region with id
				$id = $tagInfo['id'];
				while(isset($regions["#$id"])) $id .= '-';
				$regions["#$id"] = $region;
				
			} else {
				// unknown identity for region, add to regions array with "tag[n]" keys
				$cnt = 1;
				while(isset($regions["$tagInfo[name]-$cnt"])) $cnt++;
				$regions["$tagInfo[name]-$cnt"] = $region;
			}
			
			if($options['leftover']) {
				$removeMarkups[] = $region['html'];
			}
			
		} while(++$whileCnt < $options['max']);
	
		if(count($removeMarkups)) {
			$markup = str_replace($removeMarkups, '', $markup);
		}
		if($options['leftover']) $regions["leftover"] = $markup;

		return $regions;
	}
	
	/**
	 * Multi-selector version of find(), where $selector contains CSV
	 *
	 * @param string $selector
	 * @param string $markup
	 * @param array $options
	 * @return array
	 *
	 */
	protected function findMulti($selector, $markup, array $options = array()) {
		
		$regions = array();
		$o = $options;
		$o['leftover'] = true;
		$leftover = '';
		
		foreach(explode(',', $selector) as $s) {
			foreach($this->find(trim($s), $markup, $o) as $key => $region) {
				if($key === 'leftover') {
					$leftover .= $region;
				} else {
					$regions[] = $region;
				}
			}
			$markup = $leftover;
		}
		
		if(!empty($options['leftover'])) {
			if(!empty($leftover)) {
				foreach($regions as $key => $region) {
					if(strpos($leftover, $region['html']) !== false) {
						$leftover = str_replace($region['html'], '', $leftover);	
					}
				}
			}
			$regions['leftover'] = $leftover;
		}
		
		return $regions;
	}


	/**
	 * Does the given class exist in given $classes array?
	 * 
	 * @param string $class May be class name, or class prefix if $class has "*" at end. 
	 * @param array $classes
	 * @return bool|string Returns false if no match, or class name if matched
	 * 
	 */
	protected function hasClass($class, array $classes) {
		$has = false;
		if(strpos($class, '*')) {
			// partial class match
			$class = rtrim($class, '*');
			foreach($classes as $c) {
				if(strpos($c, $class) === 0) {
					$has = $c;
					break;
				}
			}
		} else {
			// exact class match
			$key = array_search($class, $classes);
			if($key !== false) $has = $classes[$key];
		}
		return $has; 
	}
	
	/**
	 * Given a $find selector return array with tests and other meta info
	 * 
	 * @param string $find
	 * @return array Returns array of [
	 *   'tests' => [ 0 => '', 1 => '', ...], 
	 *   'find' => '', 
	 *   'findTag' => '', 
	 *   'hasClass' => ''
	 * ] 
	 * 
	 */
	protected function parseFindSelector($find) {
		
		$findTag = '';
		$hasClass = '';
		
		if(strpos($find, '.') > 0) {
			// i.e. "div.myclass"
			list($findTag, $_find) = explode('.', $find, 2);
			if($this->wire('sanitizer')->alphanumeric($findTag) === $findTag) {
				$find = ".$_find";
			} else {
				$findTag = '';
			}
		}
		
		if(strpos($find, '#') === 0) {
			// match an id attribute
			$find = trim($find, '# ');
			$finds = array(
				" id=\"$find\"",
				" id='$find'",
				" id=$find ",
				" id=$find>"
			);

		} else if(strpos($find, '.') === 0 && substr($find, -1) == '*') {
			// match a class name prefix
			$find = trim($find, '.*');
			$finds = array(
				' class="' . $find,
				" class='$find",
				" class=$find",
				" $find",
				"'$find",
				"\"$find",
			);
			$hasClass = "$find*";
			
		} else if(strpos($find, '.') === 0) {
			// match a class name
			$find = trim($find, '.');
			$finds = array(
				' class="' . $find . '"',
				" class='$find'",
				" class=$find ",
				" class=$find>",
				" $find'",
				"'$find ",
				" $find\"",
				"\"$find ",
				" $find ",
			);
			$hasClass = $find;
			
		} else if(strpos($find, '<') === 0) {
			// matching a single-use HTML tag
			$finds = array(
				$find,
				rtrim($find, '>') . ' ',
			);
			
		} else if(strpos($find, '=') !== false) {
			// some other specified attribute in attr=value format
			if(strpos($find, '[') !== false && substr($find, -1) === ']') {
				// i.e. div[attr=value]
				list($findTag, $find) = explode('[', $find, 2);
				$find = rtrim($find, ']');
			}
			list($attr, $val) = explode('=', $find);
			if(strlen($val)) {
				$finds = array(
					" $attr=\"$val\"",
					" $attr='$val'",
					" $attr=$val",
					" $attr=$val>"
				);
			} else {
				$finds = array(" $attr=");
			}

		} else {
			// "data-something" matches attributes with no value, like "<div data-something>"
			$finds = array(
				" $find ",
				" $find>",
				" $find"
			);
		}
		
		return array(
			'tests' => $finds, 
			'selector' => $find,
			'findTag' => $findTag, 
			'hasClass' => $hasClass
		);
	}

	/**
	 * Given all markup after a tag, return just the portion that is the tag body/region
	 * 
	 * @param string $region Markup that occurs after the ">" of the tag you want to get the region of.
	 * @param array $tagInfo Array returned by getTagInfo method.
	 * @param array $options Options to modify behavior, see getMarkupRegions $options argument. 
	 * @return array|string Returns string except when verbose mode enabled it returns array.
	 * 
	 */
	protected function getTagRegion($region, array $tagInfo, array $options) {
		
		$closeQty = substr_count($region, $tagInfo['close']);
		$verboseRegion = array();
		
		if($options['verbose']) $verboseRegion = array(
			'name' => "$tagInfo[name]",
			'open' => "$tagInfo[src]", 
			'close' => "$tagInfo[close]",
			'attrs' => $tagInfo['attrs'], 
			'classes' => $tagInfo['classes'], 
			'details' => '',
			'region' => '', // region without wrapping tags
			'html' => '', // region with wrapping tags
		);

		if(!$closeQty) {
			// there is no close tag, meaning all of markupAfter is the region
			if($options['verbose']) {
				$verboseRegion['details'] = 'No closing tag, matched rest of document';
			}

		} else if($closeQty === 1) {
			// just one close tag present, making our job easy
			$region = substr($region, 0, strrpos($region, $tagInfo['close']));
			if($options['verbose']) {
				$verboseRegion['details'] = 'Only 1 possible closing tag';
			}

		} else {
			// multiple close tags present, must figure out which is the right one
			$testStart = 0;
			$doCnt = 0;
			$openTag = "<$tagInfo[name] ";
			do {
				$doCnt++;
				$testPos = stripos($region, $tagInfo['close'], $testStart);
				$test = substr($region, 0, $testPos);
				$openCnt = substr_count($test, $openTag);
				$closeCnt = substr_count($test, $tagInfo['close']);
				if($openCnt == $closeCnt) {
					// open and close tags balance, meaning we've found our region
					$region = $test;
					break;
				} else {
					// tags within don't balance, so try again
					$testStart = $testPos + strlen($tagInfo['close']);
				}
			} while($doCnt < 200 && $testStart < strlen($region));

			if($options['verbose']) {
				$verboseRegion['details'] = "Matched region after testing $doCnt <$tagInfo[name]> tag(s)";
			}
		}
		
		if($options['verbose']) {
			$verboseRegion['region'] = $region;
			$verboseRegion['html'] = $tagInfo['src'] . $region . $tagInfo['close'];
			return $verboseRegion;
		}
		
		// include wrapping markup if asked for
		if($options['wrap']) {
			$region = $tagInfo['src'] . $region . $tagInfo['close'];
		}

		return $region;
	}

	/**
	 * Given HTML tag like “<div id='foo' class='bar baz'>” return associative array of info about it
	 * 
	 * Returned info includes:
	 *  - `name` (string): Tag name
	 *  - `id` (string): Value of id attribute
	 *  - `classes` (array): Array of class names (from class attribute). 
	 *  - `attrs` (array): Associative array of all attributes, all values are strings.
	 *  - `attrStr` (string): All attributes in a string
	 *  - `tag` (string): The entire tag as given
	 *  - `close` (string): The HTML string that would close this tag
	 * 
	 * @param string $tag Must be a tag in format “<tag attrs>”
	 * @return array
	 * 
	 */
	public function getTagInfo($tag) {
		
		$attrs = array();
		$attrStr = '';
		$name = '';
		$tagName = '';
		$val = '';
		$inVal = false;
		$inTagName = true;
		$inQuote = '';
		$originalTag = $tag;
	
		// normalize tag to include only what's between "<" and ">" and remove unnecessary whitespace 
		$tag = str_replace(array("\r", "\n", "\t"), ' ', $tag); 
		$tag = trim($tag, '</> ');
		$pos = strpos($tag, '>'); 
		if($pos) $tag = substr($tag, 0, $pos);
		while(strpos($tag, '  ') !== false) $tag = str_replace('  ', ' ', $tag); 
		$tag = str_replace(array(' =', '= '), '=', $tag);

		// iterate through each character in the tag
		for($n = 0; $n < strlen($tag); $n++) {
			
			$c = $tag[$n];
			
			if($c == '"' || $c == "'") {
				if($inVal) {
					// building a value
					if($inQuote === $c) {
						// end of value, populate to attrs and reset val
						$attrs[$name] = $val;
						$inQuote = false;
						$inVal = false;
						$name = '';
						$val = '';
					} else if(!strlen($val)) {
						// starting a value
						$inQuote = $c;
					} else {
						// continue appending value
						$val .= $c;
					}
				} else {
					// not building a value, but found a quote, not sure what it's for, so skip it
				}
				
			} else if($c === ' ') {
				// space can either separate attributes or be part of a quoted value
				if($inVal) {
					if($inQuote) {
						// quoted space is part of value
						$val .= $c;
					} else {
						// unquoted space ends the attribute value
						if($name) $attrs[$name] = $val;
						$inVal = false;
						$name = '';
						$val = '';
					}
				} else {
					if($name && !isset($attrs[$name])) {
						// attribute without a value
						$attrs[$name] = true;
					}
					// start of a new attribute name
					$name = '';
					$inTagName = false;
				}
				
			} else if($c === '=') {
				// equals separates attribute names from values, or can appear in an attribute value
				if($inVal && $inQuote) {
					// part of a value
					$val .= $c;
				} else {
					// start new value
					$inVal = true;
				}
			} else if($inVal) {
				// append attribute value
				$val .= $c; 
			} else if(trim($c)) {
				// tag name or attribute name
				if($inTagName) {
					$tagName .= $c;
				} else {
					$name .= $c;
				}
			}
		
			if(!$inTagName) $attrStr .= $c;	
		}
		
		if($name && !isset($attrs[$name])) $attrs[$name] = $val;
		
		$info = array(
			'id' => isset($attrs['id']) ? $attrs['id'] : '',
			'name' => $tagName, 
			'classes' => isset($attrs['class']) ? explode(' ', $attrs['class']) : array(), 
			'attrs' => $attrs, 
			'attrStr' => $attrStr,
			'src' => $originalTag,
			'tag' => "<$tag>", 
			'close' => "</$tagName>",
		);
		
		return $info;
	}

	/**
	 * Strip the given region non-nested tags from the document
	 * 
	 * Note that this only works on non-nested tags like HTML comments, script or style tags. 
	 * 
	 * @param string $tag Specify "<!--" to remove comments or "<script" to remove scripts, or "<tag" for any other tags.
	 * @param string $markup Markup to remove the tags from
	 * @param bool $getRegions Specify true to return array of the strip regions rather than the updated markup
	 * @return string|array
	 * 
	 */
	public function stripRegions($tag, $markup, $getRegions = false) {
		
		$startPos = 0;
		$regions = array();
		
		$open = strpos($tag, '<') === 0 ? $tag : "<$tag";
		$close = $tag == '<!--' ? '-->' : '</' . trim($tag, '<>') . '>';
		
		do {
			$pos = stripos($markup, $open, $startPos);
			if($pos === false) break;	
			$endPos = stripos($markup, $close, $pos); 		
			if($endPos === false) {
				$endPos = strlen($markup);
			} else {
				$endPos += strlen($close);
			}
			$regions[] = substr($markup, $pos, $endPos - $pos);
			$startPos = $endPos; 
		} while(1);
		
		if($getRegions) return $regions;
		if(count($regions)) $markup = str_replace($regions, '', $markup); 
		
		return $markup;
	}
	
	/**
	 * Merge attributes from one HTML tag to another
	 * 
	 * - Attributes (except class) that appear in $mergeTag replace those in $htmlTag.
	 * - Attributes in $mergeTag not already present in $htmlTag are added to it.
	 * - Class attribute is combined with all classes from $htmlTag and $mergeTag.
	 * - The tag name from $htmlTag is used, and the one from $mergeTag is ignored.
	 *
	 * @param string $htmlTag HTML tag string, optionally containing attributes
	 * @param array|string $mergeTag HTML tag to merge (or attributes array)
	 * @return string Updated HTML tag string with merged attributes
	 *
	 */
	public function mergeTags($htmlTag, $mergeTag) {

		if(is_string($mergeTag)) {
			$mergeTagInfo = $this->getTagInfo($mergeTag);
			$mergeAttrs = $mergeTagInfo['attrs'];
		} else {
			$mergeAttrs = $mergeTag;
		}

		$tagInfo = $this->getTagInfo($htmlTag);
		$attrs = $tagInfo['attrs'];
		$changes = 0;

		foreach($mergeAttrs as $name => $value) {
			if(isset($attrs[$name])) {
				// attribute is already present
				if($attrs[$name] === $value) continue;
				if($name === 'class') {
					// merge classes
					$classes = explode(' ', $value);
					$classes = array_merge($tagInfo['classes'], $classes);
					$classes = array_unique($classes);
					// identify remove classes
					foreach($classes as $key => $class) {
						if(strpos($class, '-') !== 0) continue;
						$removeClass = ltrim($class, '-');
						unset($classes[$key]);
						while(false !== ($k = array_search($removeClass, $classes))) unset($classes[$k]);
					}
					$attrs['class'] = implode(' ', $classes);
				} else {
					// replace
					$attrs[$name] = $value;
				}
			} else {
				// add attribute not already present
				$attrs[$name] = $value;
			}
			$changes++;
		}

		if($changes) {
			$htmlTag = "<$tagInfo[name] " . $this->renderAttributes($attrs, false);
			$htmlTag = trim($htmlTag) . '>';
		}

		return $htmlTag;
	}

	/**
	 * Given an associative array of “key=value” attributes, render an HTML attribute string of them
	 * 
	 * - For boolean attributes without value (like "checked" or "selected") specify boolean true as the value. 
	 * - If value of any attribute is an array, it will be converted to a space-separated string. 
	 * - Values get entity encoded, unless you specify false for the second argument. 
	 *
	 * @param array $attrs Associative array of attributes. 
	 * @param bool $encode Entity encode attribute values? Default is true, so if they are already encoded, specify false.
	 * @param string $quote Quote style, specify double quotes, single quotes, or blank for none except when required (default=")
	 * @return string
	 *
	 */
	public function renderAttributes(array $attrs, $encode = true, $quote = '"') {
		
		$str = '';
		
		foreach($attrs as $name => $value) {
			
			if(!ctype_alnum($name)) {
				// only allow [-_a-zA-Z] attribute names
				$name = $this->wire('sanitizer')->name($name);
			}
			
			// convert arrays to space separated string
			if(is_array($value)) $value = implode(' ', $value);
			
			if($value === true) {
				// attribute without value, i.e. "checked" or "selected" or "data-uk-grid", etc. 
				$str .= "$name ";
				continue;
			} else if($name == 'class' && !strlen($value)) {
				continue;
			}
				
			$q = $quote;
			if(!$q && !ctype_alnum($value)) $q = '"';
			
			if($encode) {
				// entity encode value
				$value = $this->wire('sanitizer')->entities($value);
				
			} else if(strpos($value, '"') !== false && strpos($value, "'") === false) {
				// if value has a quote in it, use single quotes rather than double quotes
				$q = "'";
			}
			
			$str .= "$name=$q$value$q ";
		}
		
		return trim($str);
	}

	/**
	 * Does the given attribute name and value appear somewhere in the given html?
	 * 
	 * @param string $name
	 * @param string $value
	 * @param string $html
	 * @return bool Returns false if it doesn't appear, true if it does
	 * 
	 */
	public function hasAttribute($name, $value, &$html) {

		$pos = null;
		$tests = array(
			" $name=\"$value\"",
			" $name='$value'",
			" $name=$value ",
		);
	
		// if there's no space in value, we also check non-quoted values
		if(strpos($value, ' ') === false) {
			$tests[] = " $name=$value ";
			$tests[] = " $name=$value>";
		}
		
		foreach($tests as $test) {
			$pos = stripos($html, $test);
			if($pos === false) continue;
			// if another tag starts before the one in the attribute closes
			// then the matched attribute is apparently not part of an HTML tag
			$close = strpos($html, '>', $pos);
			$open = strpos($html, '<', $pos);
			if($close > $open) $pos = false; 
			if($pos !== false) break;
		}
		
		if($pos === false && stripos($html, $name) !== false && stripos($html, $value) !== false) {
			// maybe doesn't appear due to some whitespace difference, check again using a regex
			$regex = '/<[^<>]*\s' . preg_quote($name) . '\s*=\s*["\']?' . preg_quote($value) . '(?:["\']|[\s>])/i';
			if(preg_match($regex, $html)) $pos = true;
		}
		
		return $pos !== false;
	}

	/**
	 * Update the region(s) that match the given $selector with the given $content (markup/text)
	 * 
	 * @param string $selector Specify one of the following:
	 *  - Name of an attribute that must be present, i.e. "data-region", or "attribute=value" or "tag[attribute=value]".
	 *  - Specify `#name` to match a specific `id='name'` attribute.
	 *  - Specify `.name` or `tag.name` to match a specific `class='name'` attribute (class can appear anywhere in class attribute).
	 *  - Specify `<tag>` to match all of those HTML tags (i.e. `<head>`, `<body>`, `<title>`, `<footer>`, etc.)
	 * @param string $content Markup/text to update with
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options Specify any of the following:
	 *  - `mode` (string): May be 'replace', 'append', 'prepend', 'before', 'after', 'remove', or 'auto'.
	 *  - `mergeAttr` (array): Array of attrs to add/merge to the wrapping element, or HTML tag with attrs to merge.
	 * @return string
	 * 
	 */
	public function update($selector, $content, $markup, array $options = array()) {
		
		$defaults = array(
			'mode' => 'auto',
			'mergeAttr' => array(), 
		);
		
		$options = array_merge($defaults, $options);
		
		$findOptions = array(
			'verbose' => true,
			'exact' => true, 
		);
		
		$findRegions = $this->find($selector, $markup, $findOptions);
		
		foreach($findRegions as $region) {
	
			$mode = $options['mode'];
			
			if(count($options['mergeAttr'])) {
				$region['open'] = $this->mergeTags($region['open'], $options['mergeAttr']); 
			}
			
			if($mode == 'auto') {
				// auto mode delegates to the source markup class name of pw-append, pw-prepend, etc.
				$mode = '';
				foreach(array('append', 'prepend', 'replace', 'before', 'after', 'remove') as $m) {
					if(in_array("pw-$m", $region['classes'])) {
						$mode = $m;
						break;
					}
				}
			}
			
			switch($mode) {
				case 'append':
					$replacement = $region['open'] . $region['region'] . $content . $region['close'];
					break;
				case 'prepend':
					$replacement = $region['open'] . $content . $region['region'] . $region['close'];
					break;
				case 'before':
					$replacement = $content . $region['html'];
					break;
				case 'after':
					$replacement = $region['html'] . $content;
					break;
				case 'remove': 
					$replacement = '';
					break;
				default:
					$replacement = $region['open'] . $content. $region['close']; // replace
			}
	
			$markup = str_replace($region['html'], $replacement, $markup); 
		}
		
		return $markup; 
	}
	
	/**
	 * Replace the region(s) that match the given $selector with the given $replace markup
	 *
	 * @param string $selector See the update() method $selector argument for supported formats
	 * @param string $replace Markup to replace with
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See $options argument for update() method
	 * @return string
	 *
	 */
	public function replace($selector, $replace, $markup, array $options = array()) {
		$options['mode'] = 'replace';
		return $this->replace($selector, $replace, $markup, $options);
	}

	/**
	 * Append the region(s) that match the given $selector with the given $content markup
	 *
	 * @param string $selector See the update() method $selector argument for details
	 * @param string $content Markup to append
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See the update() method $options argument for details
	 * @return string
	 *
	 */
	public function append($selector, $content, $markup, array $options = array()) {
		$options['mode'] = 'append';
		return $this->replace($selector, $content, $markup, $options);
	}

	/**
	 * Prepend the region(s) that match the given $selector with the given $content markup
	 *
	 * @param string $selector See the update() method for details
	 * @param string $content Markup to prepend
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See the update() method for details
	 * @return string
	 *
	 */
	public function prepend($selector, $content, $markup, array $options = array()) {
		$options['mode'] = 'prepend';
		return $this->replace($selector, $content, $markup, $options);
	}
	
	/**
	 * Insert region(s) that match the given $selector before the given $content markup
	 *
	 * @param string $selector See the update() method for details
	 * @param string $content Markup to prepend
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See the update() method for details
	 * @return string
	 *
	 */
	public function before($selector, $content, $markup, array $options = array()) {
		$options['mode'] = 'before';
		return $this->replace($selector, $content, $markup, $options);
	}
	
	/**
	 * Insert the region(s) that match the given $selector after the given $content markup
	 *
	 * @param string $selector See the update() method for details
	 * @param string $content Markup to prepend
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See the update() method for details
	 * @return string
	 *
	 */
	public function after($selector, $content, $markup, array $options = array()) {
		$options['mode'] = 'after';
		return $this->replace($selector, $content, $markup, $options);
	}
	
	/**
	 * Remove the region(s) that match the given $selector 
	 *
	 * @param string $selector See the update() method for details
	 * @param string $markup Document markup where region(s) exist
	 * @param array $options See the update() method for details
	 * @return string
	 *
	 */
	public function remove($selector, $markup, array $options = array()) {
		$options['mode'] = 'after';
		return $this->replace($selector, '', $markup, $options);
	}
	
	/**
	 * Identify and populate markup regions in given HTML
	 *
	 * To use this, you must set `$config->useMarkupRegions = true;` in your /site/config.php file.
	 * In the future it may be enabled by default for any templates with text/html content-type.
	 *
	 * This takes anything output before the opening `<!DOCTYPE` and connects it to the right places
	 * within the `<html>` that comes after it. For instance, if there's a `<div id='content'>` in the
	 * document, then a #content element output prior to the doctype will replace it during page render.
	 * This enables one to use delayed output as if it’s direct output. It also makes every HTML element
	 * in the output with an “id” attribute a region that can be populated from any template file. It’s
	 * a good pairing with a `$config->appendTemplateFile` that contains the main markup and region
	 * definitions, though can be used with or without it.
	 *
	 * Beyond replacement of elements, append, prepend, insert before, insert after, and remove are also
	 * supported via “pw-” prefix classes that you can add. The classes do not appear in the final output
	 * markup. When performing replacements or modifications to elements, PW will merge the attributes
	 * so that attributes present in the final output are present, plus any that were added by the markup
	 * regions. See the examples for more details.
	 *
	 * Below are some examples. Note that “main” is used as an example “id” attribute of an element that
	 * appears in the main document markup, and the examples below focus on manipulating it. The examples
	 * assume there is a `<div id=main>` in the _main.php file (appendTemplateFile), and the lines in the
	 * examples would be output from a template file, which manipulates what would ultimately be output
	 * when the page is rendered.
	 * ~~~~~~
	 * Replacing and removing elements
	 *   <div id='main'>This replaces the #main div and merges any attributes</div>
	 *   <div id='main' class='pw-replace'>This does the same as above</div>
	 *   <div id='main' class='pw-remove'>This removes #main completely</div>
	 *
	 * Prepending and appending elements
	 *   <div id='main' class='pw-prepend'><p>This prepends #main with this p tag</p></div>
	 *   <div id='main' class='pw-prepend bar'><p>This prepends #main and adds "bar" class to main</p></div>
	 *   <div id='main' class='pw-append'><p>This appends #main with this p tag</p></div>
	 *   <div id='main' class='pw-append foo'><p>This appends #main and adds a "foo" class to #main</p></div>
	 *   <div id='main' class='pw-append' title='hello'>Appends #main with this text + adds title attribute to #main</div>
	 *   <div id='main' class='pw-append -baz'>Appends #main with this text + removes class “baz” from #main</div>
	 *
	 * Inserting new elements
	 *   <h2 class='pw-before-main'>This adds an h2 headline with this text before #main</h2>
	 *   <footer class='pw-after-main'><p>This adds a footer element with this text after #main</p></footer>
	 *   <div class='pw-append-main foo'>This appends a div.foo to #main with this text</div>
	 *   <div class='pw-prepend-main bar'>This prepends a div.bar to #main with this text</div>
	 * ~~~~~~
	 *
	 * @param string $htmlDocument Document to populate regions to
	 * @param string|array $htmlRegions Markup containing regions (or regions array from a find call)
	 * @return int Number of updates made to $htmlDocument
	 *
	 */
	public function populate(&$htmlDocument, $htmlRegions) {
		
		static $recursionLevel = 0;
		$recursionLevel++;

		if(strpos($htmlDocument, ' id=') === false) return 0;
		if(is_array($htmlRegions)) {
			$regions = $htmlRegions;
			$leftoverMarkup = '';
		} else {
			if(!$this->hasRegions($htmlRegions)) return 0;
			$regions = $this->find(".pw-*, id=", $htmlRegions, array(
				'verbose' => true,
				'leftover' => true
			));
			$leftoverMarkup = trim($regions["leftover"]);
			unset($regions["leftover"]);
		}

		if(!count($regions)) return 0;

		$xregions = array(); // regions that weren't populated
		$populatedNotes = array();
		$rejectedNotes = array();
		$numUpdates = 0;
		$debugLandmark = "<!--PW-REGION-DEBUG-->";
		$debug = ($this->wire('config')->debug || strpos($htmlDocument, $debugLandmark)) && $this->wire('user')->isSuperuser();

		foreach($regions as $regionKey => $region) {

			$xregion = $region;
			$this->populateRegionDetails($region);
			$mode = $region['mode'];
			$id = $region['attrs']['id'];
			$regionHTML = $region['region'];
			$mergeAttr = $region['attrs'];
			$regionNote = "$regionKey. $region[note]";
			unset($mergeAttr['id']);

			if($region['new']) {
				// element is newly added element not already present
				$mergeAttr = array();
				$regionHTML = $region['html'];
				$attrs = $region['attrs'];
				unset($attrs['id']);
				if(isset($attrs['data-id'])) {
					$attrs['id'] = $attrs['data-id'];
					unset($attrs['data-id']);
				}
				$attrStr = count($attrs) ? ' ' . $this->renderAttributes($attrs, false) : '';
				if(!strlen(trim($attrStr))) $attrStr = '';
				$regionHTML = str_replace($region['open'], "<$region[name]$attrStr>", $regionHTML);
			}

			// if the id attribute doesn't appear in the html, skip it
			if(!$this->hasAttribute('id', $id, $htmlDocument)) {
				$xregions[$regionKey] = $xregion;
				$rejectedNotes[] = $regionNote;
				
			} else {
				// update the markup
				$htmlDocument = $this->update("#$id", $regionHTML, $htmlDocument, array(
					'mode' => $mode,
					'mergeAttr' => $mergeAttr,
				));

				$populatedNotes[] = $regionNote;
				$numUpdates++;
			}
		}
			
		if($debug) {
			$bull = "\n    ";
			$n = $recursionLevel;
			$leftoverBytes = strlen($leftoverMarkup);
			$debugNotes = "\nPW markup regions run #$n";
			if(count($populatedNotes)) $debugNotes .= "\n  Populated: $bull" . implode($bull, $populatedNotes);
			if(count($rejectedNotes)) $debugNotes .= "\n  Skipped: $bull" . implode($bull, $rejectedNotes);
			if($leftoverBytes) $debugNotes .= "\n  $leftoverBytes non-region bytes skipped";
			if(strpos($htmlDocument, $debugLandmark) !== false) {
				$debugNotes = "<pre>" . $this->wire('sanitizer')->entities($debugNotes) . "</pre>" . $debugLandmark;
				$htmlDocument = str_replace($debugLandmark, $debugNotes, $htmlDocument); 
			} else {
				$htmlDocument .= "<!--" . $debugNotes . "\n-->";
			}
		}
		
		if(count($xregions) && $recursionLevel < 3) {
			// see if they can be populated now
			$numUpdates += $this->populate($htmlDocument, $xregions);
		}

		// if there is any leftover markup, place it above the HTML where it would usually go
		if(strlen($leftoverMarkup)) {
			$htmlDocument = $leftoverMarkup . $htmlDocument;
			$numUpdates++;
		}

		return $numUpdates; 
	}

	/**
	 * Determine the and populate 'mode' and 'new' properties for the given region
	 * 
	 * Modifications are made directly to the given $region. 
	 * 
	 * The 'mode' property can be any of the following values:
	 * before, after, prepend, append, replace, remove.
	 * 
	 * The 'new' property will be boolean true if the region is a new element that
	 * should be added to the document. It will be false if the region is to update
	 * an existing element in the document. 
	 *
	 * The 'id' and 'class' attributes of the region may also be modified as well.
	 *
	 * @param array $region
	 * @return string
	 *
	 */
	protected function populateRegionDetails(&$region) {

		$modes = array('before', 'after', 'prepend', 'append', 'replace', 'remove');
		$mode = '';
		$isNewElement = false;
		$id = isset($region['attrs']['id']) ? $region['attrs']['id'] : '';
		$_id = $id;
		$prefix = 'pw-';

		if(strpos($id, $prefix) === 0) {
			// i.e. pw-before-something
			foreach($modes as $m) {
				if(strpos($id, "$prefix$m-") === 0) {
					list($pw, $mode, $id) = explode('-', $id, 3);
					if($pw) {} // ignore
					break;
				}
			}
		}

		if(!$mode) {
			// attempt to get mode from class attribute
			$replaceID = '';
			foreach($region['classes'] as $key => $class) {
				$replaceID = '';
				if(strpos($class, $prefix) !== 0) continue;
				list($pw, $m) = explode('-', $class, 2);
				if($pw) {} // ignore
				if(strpos($m, '-')) {
					// i.e. pw-append-content
					$replaceID = $id;
					list($m, $id) = explode('-', $m, 2);
					$isNewElement = true;
				}
				if(in_array($m, $modes)) {
					$mode = $m;
					// remove the class so it doesn't appear in original markup
					unset($region['classes'][$key]);
					$region['attrs']['class'] = implode(' ', $region['classes']);
					break;
				}
			}
			if($replaceID && ($isNewElement || $mode == 'before' || $mode == 'after')) {
				$region['attrs']['data-id'] = $replaceID;
			}
		}

		if(!$mode || !in_array($mode, $modes)) $mode = 'auto';

		if(!$isNewElement) {
			$isNewElement = empty($_id) || $mode == 'before' || $mode == 'after';
		}

		$region['new'] = $isNewElement;
		$region['mode'] = $mode;
		$region['note'] = "#$id ($mode" . ($isNewElement ? "+new" : "") . ")";
		$region['attrs']['id'] = $id;
		
		if(count($region['classes'])) $region['note'] .= "." . implode('.', $region['classes']);

		return $mode;
	}

	/**
	 * Is the given HTML markup likely to have regions?
	 * 
	 * @param string $html
	 * @return bool
	 * 
	 */
	public function hasRegions(&$html) {
		if(strpos($html, ' id=') === false && strpos($html, 'pw-') === false) return false;
		return true;
	}


}