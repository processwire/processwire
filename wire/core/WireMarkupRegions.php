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
	 * Debug during development of this class
	 * 
	 */
	const debug = false;

	/**
	 * @var array
	 * 
	 */
	protected $debugNotes = array();
	
	/**
	 * HTML tag names that require no closing tag
	 * 
	 * @var array
	 * 
	 */
	protected $selfClosingTags = array(
		'link',
		'area',
		'base',
		'br',
		'col',
		'command',
		'embed',
		'hr',
		'img',
		'input',
		'keygen',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	);

	/**
	 * Supported markup region actions
	 * 
	 * @var array
	 * 
	 */
	protected $actions = array(
		'prepend',
		'append',
		'before',
		'after',
		'replace',
		'remove',
	);

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
	 *  - Regular array of markup regions when finding regions having a specific class attribute. 
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
		$_markup = self::debug ? $markup : '';
		
		if(self::debug) {
			$options['debugNote'] = (empty($options['debugNote']) ? "" : "$options[debugNote] => ") . "find($selector)";
		}
		
		// strip out comments if markup isn't required to stay the same
		if(!$options['exact']) $markup = $this->stripRegions('<!--', $markup);
		
		// determine auto value for wrap option
		if(is_null($options['wrap'])) $options['wrap'] = $hasClass ? true : false;

		$startPos = 0;
		$whileCnt = 0; // number of do-while loop completions
		$iterations = 0; // total number of iterations, including continue statements

		do {
			if(++$iterations >= $options['max'] * 2) break;
			
			// find all the positions where each test appears
			$positions = array();
			foreach($tests as $str) {
				$pos = stripos($markup, $str, $startPos);
				if($pos === false) continue;
				if($selector === '.pw-*') {
					// extra validation for .pw-* selectors to confirm they match a pw-[action] (legacy support)
					$testAction = '';
					$testPW = substr($markup, $pos, 20);
					foreach($this->actions as $testAction) {
						if(strpos($testPW, $testAction) !== false) {
							$testAction = true;
							break;
						}
					}	
					if($testAction !== true) continue; // if not a pw-[action] then skip
				}
				if($str[0] == '<') $pos++; // HTML tag match, bump+1 to enable match
				$positions[$pos] = $pos;
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
			$startPos += $closeOpenTagPos; // for next iteration, if a continue occurs
		
			// if the orders of "<" and ">" aren't what's expected in our markupBefore and markupAfter, then abort
			$testPos = strpos($markupAfter, '<');
			if($testPos !== false && $testPos < $closeOpenTagPos) continue;
			if(strrpos($markupBefore, '>') > $openTagPos) continue; 
	
			// build the HTML tag by combining the halfs from markupBefore and markupAfter
			$tag = substr($markupBefore, $openTagPos) . substr($markupAfter, 0, $closeOpenTagPos + 1);
			$tagInfo = $this->getTagInfo($tag);
		
			// pre-checks to make sure this iteration is allowed
			if($findTag && $tagInfo['name'] !== $findTag) continue;
			if(!$tagInfo['action'] && $hasClass && !$this->hasClass($hasClass, $tagInfo['classes'])) continue;

			// build the region (everything in $markupAfter that starts after the ">")
			$regionMarkup = empty($tagInfo['close']) ? '' : substr($markupAfter, $closeOpenTagPos + 1);
			$region = $this->getTagRegion($regionMarkup, $tagInfo, $options);
			$region['startPos'] = $openTagPos;

			if($options['single']) {
				// single mode means we just return the markup
				$regions = $region;
				break;
				
			} else {
				while(isset($regions[$pos])) $pos++;
				$regions[$pos] = $region;
			}

			$replaceQty = 0;
			$markup = str_replace($region['html'], '', $markup, $replaceQty);
			if(!$replaceQty) {
				// region html was not present, try replacement without closing tag (which might be missing)
				$markup = str_replace($region['open'] . $region['region'], '', $markup, $replaceQty);
				if($replaceQty) {
					$this->debugNotes[] = "ERROR: missing closing tag for: $region[open]";
				} else {
					$this->debugNotes[] = "ERROR: unable to populate: $region[open]";
				}
			}

			if($replaceQty) $startPos = 0;
			$markup = trim($markup);
			if(empty($markup)) break;
			
		} while(++$whileCnt < $options['max']);

		/*
		foreach($regions as $regionKey => $region) {
			foreach($regions as $rk => $r) {
				if($rk === $regionKey) continue;
				if(strpos($region['html'], $r['html']) !== false) {
					$regions[$regionKey]['region'] = str_replace($r['html'], '', $region['region']); 
					//$k = 'html';
					//$this->debugNotes[] = 
					//	"\nREPLACE “" . htmlentities($r[$k]) . "”". 
					//	"\nIN      “" . htmlentities($region[$k]) . "”" . 
					//	"\nRESULT  “" . htmlentities($regions[$regionKey][$k]) . "”";
				}
			}
		}
		*/
		
		if($options['leftover']) $regions["leftover"] = trim($markup);
		
		if(self::debug && $options['verbose']) {
			$debugNote = "$options[debugNote] in [sm]" . $this->debugNoteStr($_markup, 50) . " …[/sm] => ";
			$numRegions = 0;
			foreach($regions as $key => $region) {
				if($key == 'leftover') continue;
				$details = empty($region['details']) ? '' : " ($region[details]) ";
				$debugNote .= "$region[name]#$region[pwid]$details, ";
				$numRegions++;
			}
			if(!$numRegions) $debugNote .= 'NONE';
			$this->debugNotes[] = rtrim($debugNote, ", ") . " [sm](iterations=$iterations)[/sm]";
		}

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
					while(isset($regions[$key])) $key++;
					$regions[$key] = $region;
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
		
		ksort($regions);
		
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
	 *   'hasClass' => '',
	 * ] 
	 * 
	 */
	protected function parseFindSelector($find) {
		
		$findTag = '';
		$hasClass = '';
		$finds = array();
		
		if(strpos($find, '.') > 0) {
			// i.e. "div.myclass"
			list($findTag, $_find) = explode('.', $find, 2);
			if($this->wire('sanitizer')->alphanumeric($findTag) === $findTag) {
				$find = ".$_find";
			} else {
				$findTag = '';
			}
		}
		
		$c = substr($find, 0, 1); 
		$z = substr($find, -1);
		
		if($c === '#') {
			// match an id, pw-id or data-pw-id attribute
			$find = trim($find, '# ');
			foreach(array('pw-id', 'data-pw-id', 'id') as $attr) {
				$finds[] = " $attr=\"$find\"";
				$finds[] = " $attr='$find'";
				$finds[] = " $attr=$find ";
				$finds[] = " $attr=$find>";
			}

		} else if($find === '[pw-action]') {
			// find pw-[action] boolean attributes
			foreach($this->actions as $action) {
				$finds[] = " data-pw-$action ";
				$finds[] = " data-pw-$action>";
				$finds[] = " data-pw-$action=";
				$finds[] = " pw-$action ";
				$finds[] = " pw-$action>";
				$finds[] = " pw-$action=";
			}

		/*	
		} else if($c === '[' && $z === ']') {
			// find any attribute (not currently used by markup regions)
			
			if(strpos($find, '=') === false) {
				// match an attribute only
				$attr = trim($find, '[]*+');
				$tail = substr($find, -2);
				if($tail === '*]') {
					// match an attribute prefix
					$finds = array(" $attr");
				} else {
					// match a whole attribute name
					$finds = array(
						" $attr ",
						" $attr>",
						" $attr=",
					);
				}
			} else {
				// match attribute and value (not yet implemented)
			}
		*/
			
		} else if($c === '.' && $z === '*') {
			// match a class name prefix or action prefix
			$find = trim($find, '.*');
			$finds = array(
				' class="' . $find,
				" class='$find",
				" class=$find",
				" $find",
				"'$find",
				"\"$find",
			);
			if(strpos($find, 'pw-') !== false) $finds[] = " data-$find";
			$hasClass = "$find*";

		} else if($c === '.') {
			// match a class name or action
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
			if(strpos($find, 'pw-') !== false) $finds[] = " data-$find";
			$hasClass = $find;
			
		} else if($c === '<') {
			// matching a single-use HTML tag
			$finds = array(
				$find,
				rtrim($find, '>') . ' ',
			);
			
		} else if(strpos($find, '=') !== false) {
			// some other specified attribute in attr=value format
			if(strpos($find, '[') !== false && $z === ']') {
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
			
			if(strpos($find, 'id=') === 0) {
				// when finding an id attribute, also allow for "pw-id=" and "data-pw-id="
				foreach($finds as $find) {
					$find = ltrim($find);
					$finds[] = " pw-id=";
					$finds[] = " data-pw-id=";
				}
			}

		} else if($z === '*') {
			// data-pw-* matches non-value attribute starting with a prefix
			$finds = array(
				" $find",
			);

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
	 *  - `verbose` (bool): Verbose mode (default=false)
	 *  - `wrap` (bool): Whether or not wrapping markup should be included (default=false)
	 * @return array|string Returns string except when verbose mode enabled it returns array.
	 * 
	 */
	protected function getTagRegion($region, array $tagInfo, array $options) {
		
		// options
		$verbose = empty($options['verbose']) ? false : true;
		$wrap = empty($options['wrap']) ? false : $options['wrap'];
		
		if($verbose) $verboseRegion = array(
			'name' => "$tagInfo[name]",
			'pwid' => "$tagInfo[pwid]", 
			'open' => "$tagInfo[src]", 
			'close' => "$tagInfo[close]",
			'attrs' => $tagInfo['attrs'], 
			'classes' => $tagInfo['classes'], 
			'action' => $tagInfo['action'],
			'actionType' => $tagInfo['actionType'], 
			'actionTarget' => $tagInfo['actionTarget'],
			'error' => false, 
			'details' => '',
			'region' => '', // region without wrapping tags
			'html' => '', // region with wrapping tags
		);
	
		if($tagInfo['pwid']) $tagID = $tagInfo['pwid'];
			else if(!empty($tagInfo['id'])) $tagID = $tagInfo['id'];
			else if(!empty($tagInfo['attrs']['id'])) $tagID = $tagInfo['attrs']['id']; 
			else if(!empty($tagInfo['actionTarget'])) $tagID = $tagInfo['actionTarget'];	
			else $tagID = '';
	
		$selfClose = empty($tagInfo['close']);
		$closeHint = "$tagInfo[close]<!--#$tagID-->";
		$closeQty = $selfClose ? 1 : substr_count($region, $tagInfo['close']);

		if(!$closeQty) {
			// there is no close tag, meaning all of markupAfter is the region
			if($verbose) $verboseRegion['details'] = 'No closing tag, matched rest of document';
			
		} else if($selfClose) {
			$region = '';
			if($verbose) $verboseRegion['details'] = 'Self closing tag (empty region)';
			
		} else if($tagID && false !== ($pos = strpos($region, $closeHint))) {
			// close tag indicates what it closes, i.e. “</div><!--#content-->”
			$region = substr($region, 0, $pos);
			$tagInfo['close'] = $closeHint;
			if($verbose) $verboseRegion['details'] = "Fast match with HTML comment hint";
			
		} else if($closeQty === 1) {
			// just one close tag present, making our job easy
			$region = substr($region, 0, strrpos($region, $tagInfo['close']));
			if($verbose) $verboseRegion['details'] = "Only 1 possible closing tag: $tagInfo[close]";

		} else {
			// multiple close tags present, must figure out which is the right one
			$testStart = 0;
			$doCnt = 0;
			$maxDoCnt = 100000;
			$openTag = "<$tagInfo[name]";
			$openTags = array("$openTag>", "$openTag ", "$openTag\n", "$openTag\r", "$openTag\t"); 
			$fail = false;
			
			do {
				$doCnt++;
				$testPos = stripos($region, $tagInfo['close'], $testStart);
				if($testPos === false) {
					$fail = true;
					break;
				}
				$test = substr($region, 0, $testPos);
				$openCnt = 0;
				foreach($openTags as $openTag) {
					if(strpos($test, $openTag) !== false) $openCnt += substr_count($test, $openTag);
				}
				$closeCnt = substr_count($test, $tagInfo['close']);
				if($openCnt == $closeCnt) {
					// open and close tags balance, meaning we've found our region
					$region = $test;
					break;
				} else {
					// tags within don't balance, so try again
					$testStart = $testPos + strlen($tagInfo['close']);
				}
			} while($doCnt < $maxDoCnt && $testStart < strlen($region));
			
			if($fail) {
				if($verbose) {
					$verboseRegion['error'] = true;
					$verboseRegion['details'] = "Failed to find closing tag $tagInfo[close] after $doCnt iterations";
				} else {
					$region = 'error';
				}
			} else if($doCnt >= $maxDoCnt) {
				if($verbose) {
					$verboseRegion['error'] = true;
					$verboseRegion['details'] = "Failed region match after $doCnt tests for <$tagInfo[name]> tag(s)";
				} else {
					$region = 'error';
				}
			} else if($verbose) {
				$verboseRegion['details'] = "Matched region after testing $doCnt <$tagInfo[name]> tag(s)";
			}
		}
	
		// region with wrapping tag
		$wrapRegion = $selfClose ? "$tagInfo[src]" : "$tagInfo[src]$region$tagInfo[close]";
		
		if($verbose) {
			$verboseRegion['region'] = $region;
			$verboseRegion['html'] = $wrapRegion;
			/*
			if(self::debug) {
				$debugNote = (isset($options['debugNote']) ? "$options[debugNote] => " : "") . 
					"getTagRegion() => $verboseRegion[name]#$verboseRegion[pwid] " . 
					"[sm]$verboseRegion[details][/sm]";
				$this->debugNotes[] = $debugNote;
			}
			*/
			return $verboseRegion;
		}
		
		// include wrapping markup if asked for
		if($wrap) $region = $wrapRegion;

		return $region;
	}

	/**
	 * Given HTML tag like “<div id='foo' class='bar baz'>” return associative array of info about it
	 * 
	 * Returned info includes:
	 *  - `name` (string): Tag name
	 *  - `id` (string): Value of id attribute
	 *  - `pwid` (string): PW region ID from 'pw-id' or 'data-pw-id', or if not present, then same as 'id'
	 *  - `action` (string): Action for this region, without “pw-” prefix.
	 *  - `actionTarget` (string): Target id for the action, if applicable.
	 *  - `actionType` (string): "class" if action specified as class name, "attr" if specified as a pw- or data-pw attribute.
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
		$tag .= ' '; // extra space for loop below

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
		$tag = rtrim($tag); // remove extra space we added
		$tagName = strtolower($tagName);
		$selfClosing = in_array($tagName, $this->selfClosingTags);
		$classes = isset($attrs['class']) ? explode(' ', $attrs['class']) : array();
		$id = isset($attrs['id']) ? $attrs['id'] : '';
		$pwid = '';
		$action = '';
		$actionTarget = '';
		$actionType = '';

		// determine action and action target from attributes
		foreach($attrs as $name => $value) {
			$pwpos = strpos($name, 'pw-');
			if($pwpos === false) continue;
			if($name === 'pw-id' || $name === 'data-pw-id') {
				// id attribute
				if(!$pwid) $pwid = $value; 
				unset($attrs[$name]);
			} else if($pwpos === 0) {
				// action attribute
				list($prefix, $action) = explode('-', $name, 2); 
				if($prefix) {} // ignore
			} else if(strpos($name, 'data-pw-') === 0) {
				// action data attribute
				list($ignore, $prefix, $action) = explode('-', $name, 3); 
				if($ignore && $prefix) {} // ignore
			}
			if($action && !$actionTarget) {
				if(strpos($action, '-')) {
					list($action, $actionTarget) = explode('-', $action, 2);
				} else {
					$actionTarget = $value;
				}
				if($actionTarget && in_array($action, $this->actions)) {
					// found a valid action and target
					unset($attrs[$name]);
					$actionType = $actionTarget === true ? 'bool' : 'attr';
				} else {
					// unrecognized action
					$action = '';
					$actionTarget = '';
				}
			}
		}
		
		// if action was not specified as an attribute, see if action is specified as a class name
		if(!$action) foreach($classes as $key => $class) {
			if(strpos($class, 'pw-') !== 0) continue;
			list($prefix, $action) = explode('-', $class, 2);
			if(strpos($action, '-')) list($action, $actionTarget) = explode('-', $action, 2);
			if($prefix && $actionTarget) {} // ignore
			if(in_array($action, $this->actions)) {
				// valid action, remove action from classes and class attribute
				unset($classes[$key]); 
				$attrs['class'] = implode(' ', $classes);	
				$actionType = 'class';
				break;
			} else {
				// unrecognized action
				$action = '';
				$actionTarget = '';
			}
		}
	
		if(!$pwid) $pwid = $id;

		// if there's an action, but no target, the target is assumed to be the pw-id or id
		if($action && (!$actionTarget || $actionTarget === true)) $actionTarget = $pwid; 
		
		$info = array(
			'id' => $id,
			'pwid' => $pwid ? $pwid : $id, 
			'name' => $tagName, 
			'classes' => $classes,
			'attrs' => $attrs, 
			'attrStr' => $attrStr,
			'src' => $originalTag,
			'tag' => "<$tag>", 
			'close' => $selfClosing ? "" : "</$tagName>",
			'action' => $action,
			'actionTarget' => $actionTarget,
			'actionType' => $actionType, 
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
	
		// keep comments that start with <!--#
		if($tag == "<!--" && strpos($markup, "<!--#") !== false) {
			$hasHints = true;
			$markup = str_replace("<!--#", "<!~~#", $markup);
			$markup = preg_replace('/<!--#([-_.a-zA-Z0-9]+)-->/', '<!~~$1~~>', $markup);
		} else {
			$hasHints = false;
		}
		
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
		
		if($hasHints) {
			// keep comments that start with <!--#
			$markup = str_replace(array("<!~~#", "~~>"), array("<!--#", "-->"), $markup); 
		}
		
		return $markup;
	}

	/**
	 * Strip optional tags/comments from given markup
	 * 
	 * @param string $markup
	 * @return string
	 * 
	 */
	public function stripOptional($markup) {
		$attrs = array('data-pw-optional', 'pw-optional'); 
		foreach($attrs as $attrName) {
			if(strpos($markup, " $attrName") === false) continue;
			$regions = $this->find($attrName, $markup, array('verbose' => true));
			foreach($regions as $region) {
				$pos = strpos($markup, $region['html']);
				if($pos === false) continue;
				$content = trim($region['region']);
				if(strlen($content)) {
					// not empty, remove just the pw-optional attribute
					$open = $region['open'];
					$open = str_ireplace(" $attrName", '', $open);
					$markup = substr($markup, 0, $pos) . $open . substr($markup, $pos + strlen($region['open']));
				} else {
					// empty optional region, can be removed
					$markup = substr($markup, 0, $pos) . substr($markup, $pos + strlen($region['html']));
				}
			}
		}
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
	 * @param string|bool $value Value to find, or specify boolean true for boolean attribute without value
	 * @param string $html
	 * @return bool Returns false if it doesn't appear, true if it does
	 * 
	 */
	public function hasAttribute($name, $value, &$html) {

		$pos = null;
		
		if($value === true) {
			$tests = array(
				" $name ",
				" $name>", 
				" $name=", 
				" $name/", 
			);
			
		} else {
			$tests = array(
				" $name=\"$value\"",
				" $name='$value'",
			);
			
			// if there's no space in value, we also check non-quoted values
			if(strpos($value, ' ') === false) {
				$tests[] = " $name=$value ";
				$tests[] = " $name=$value>";
			}
		}
		
		if($name == 'id') {
			foreach($tests as $test) {
				$test = ltrim($test);
				$tests[] = " pw-id-$test";
				$tests[] = " data-pw-id-$test";
			}
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
			if($name == 'id') {
				$names = '(id|pw-id|data-pw-id)';
			} else {
				$names = preg_quote($name); 
			}
			if($value === true) {
				$regex = '!<[^<>]*\s' . $names . '[=\s/>]!i';
			} else {
				$regex = '/<[^<>]*\s' . $names . '\s*=\s*["\']?' . preg_quote($value) . '(?:["\']|[\s>])/i';
			}
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
	 *  - `action` (string): May be 'replace', 'append', 'prepend', 'before', 'after', 'remove', or 'auto'.
	 *  - `mergeAttr` (array): Array of attrs to add/merge to the wrapping element, or HTML tag with attrs to merge.
	 * @return string
	 * 
	 */
	public function update($selector, $content, $markup, array $options = array()) {
		
		$defaults = array(
			'action' => 'auto',
			'mergeAttr' => array(), 
		);
		
		$options = array_merge($defaults, $options);
		
		$findOptions = array(
			'verbose' => true,
			'exact' => true, 
		);
		
		if(self::debug) {
			$findOptions['debugNote'] = "update.$options[action]($selector)";
		}
		
		$findRegions = $this->find($selector, $markup, $findOptions);
		
		foreach($findRegions as $region) {
	
			$action = $options['action'];
			
			if(count($options['mergeAttr'])) {
				$region['open'] = $this->mergeTags($region['open'], $options['mergeAttr']); 
			}
			
			if($action == 'auto') {
				// auto mode delegates to the region action
				$action = '';
				if(in_array($region['action'], $this->actions)) $action = $region['action'];
			}
			
			switch($action) {
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
					// replace
					$replacement = $region['open'] . $content . $region['close']; 
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
		$options['action'] = 'replace';
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
		$options['action'] = 'append';
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
		$options['action'] = 'prepend';
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
		$options['action'] = 'before';
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
		$options['action'] = 'after';
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
		$options['action'] = 'after'; // after intended
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
	 * supported via “pw-” prefix attributes that you can add. The attributes do not appear in the final output
	 * markup. When performing replacements or modifications to elements, PW will merge the attributes
	 * so that attributes present in the final output are present, plus any that were added by the markup
	 * regions. See the examples for more details.
	 *
	 * Examples
	 * ========
	 * Below are some examples. Note that “main” is used as an example “id” attribute of an element that
	 * appears in the main document markup, and the examples below focus on manipulating it. The examples
	 * assume there is a `<div id=main>` in the _main.php file (appendTemplateFile), and the lines in the
	 * examples would be output from a template file, which manipulates what would ultimately be output
	 * when the page is rendered.
	 * 
	 * In the examples, a “pw-id” or “data-pw-id” attribute may be used instead of an “id” attribute, when
	 * or if preferred. In addition, any “pw-” attribute may be specified as a “data-pw-” attribute if you
	 * prefer it. 
	 * ~~~~~~
	 * Replacing and removing elements
	 * 
	 *   <div id='main'>This replaces the #main div and merges any attributes</div>
	 *   <div pw-replace='main'>This does the same as above</div>
	 *   <div id='main' pw-replace>This does the same as above</div>
	 *   <div pw-remove='main'>This removes the #main div</div>
	 *   <div id='main' pw-remove>This removes the #main div (same as above)</div>
	 *
	 * Prepending and appending elements
	 * 
	 *   <div id='main' class='pw-prepend'><p>This prepends #main with this p tag</p></div>
	 *   <p pw-prepend='main'>This does the same as above</p>
	 *   <div id='main' pw-append><p>This appends #main with this p tag</p></div>
	 *   <p pw-append='main'>Removes the #main div</p>
	 * 
	 * Modifying attributes on an existing element
	 * 
	 *   <div id='main' class='bar' pw-prepend><p>This prepends #main and adds "bar" class to main</p></div>
	 *   <div id='main' class='foo' pw-append><p>This appends #main and adds a "foo" class to #main</p></div>
	 *   <div id='main' title='hello' pw-append>Appends #main with this text + adds title attribute to #main</div>
	 *   <div id='main' class='-baz' pw-append>Appends #main with this text + removes class “baz” from #main</div>
	 *
	 * Inserting new elements
	 * 
	 *   <h2 pw-before='main'>This adds an h2 headline with this text before #main</h2>
	 *   <footer pw-after='main'><p>This adds a footer element with this text after #main</p></footer>
	 *   <div pw-append='main' class='foo'>This appends a div.foo to #main with this text</div>
	 *   <div pw-prepend='main' class='bar'>This prepends a div.bar to #main with this text</div>
	 * 
	 * ~~~~~~
	 *
	 * @param string $htmlDocument Document to populate regions to
	 * @param string|array $htmlRegions Markup containing regions (or regions array from a find call)
	 * @param array $options Options to modify behavior: 
	 *  - `useClassActions` (bool): Allow "pw-*" actions to be specified in class names? Per original/legacy spec. (default=false)
	 * @return int Number of updates made to $htmlDocument
	 *
	 */
	public function populate(&$htmlDocument, $htmlRegions, array $options = array()) {
		
		static $recursionLevel = 0;
		static $callQty = 0;
		
		$recursionLevel++;
		$callQty++;

		$defaults = array(
			'useClassActions' => false // allow use of "pw-*" class actions? (legacy)
		);
		
		$options = array_merge($defaults, $options);
		$leftoverMarkup = '';
		$debugLandmark = "<!--PW-REGION-DEBUG-->";
		$hasDebugLandmark = strpos($htmlDocument, $debugLandmark) !== false;
		$debug = $hasDebugLandmark && $this->wire('config')->debug;
		$debugTimer = $debug ? Debug::timer() : 0;
		
		if(is_array($htmlRegions)) {
			$regions = $htmlRegions;
			$leftoverMarkup = '';
			
		} else if($this->hasRegions($htmlRegions)) {
			$htmlRegions = $this->stripRegions('<!--', $htmlRegions);
			$selector = $options['useClassActions'] ? ".pw-*, id=" : "[pw-action], id=";
			$regions = $this->find($selector, $htmlRegions, array(
				'verbose' => true,
				'leftover' => true,
				'debugNote' => (isset($options['debugNote']) ? "$options[debugNote] => " : "") . "populate($callQty)", 
			));
			$leftoverMarkup = trim($regions["leftover"]);
			unset($regions["leftover"]);
			
		} else {
			$regions = array();
		}

		if(!count($regions)) {
			if($debug) $htmlDocument = str_replace($debugLandmark, "<pre>No regions</pre>$debugLandmark", $htmlDocument);
			$recursionLevel--;
			if(!$recursionLevel) {
				$this->removeRegionTags($htmlDocument);
				if(strpos($htmlDocument, 'pw-optional')) $htmlDocument = $this->stripOptional($htmlDocument);
			}
			return 0;
		}

		$xregions = array(); // regions that weren't populated
		$populatedNotes = array();
		$rejectedNotes = array();
		$updates = array();
		$numUpdates = 0;

		foreach($regions as $regionKey => $region) {

			if(empty($region['action'])) $region['action'] = 'auto'; // replace
			if(empty($region['actionTarget'])) $region['actionTarget'] = $region['pwid']; // replace
			
			// $xregion = $region;
			$action = $region['action'];
			$actionTarget = $region['actionTarget'];
			$regionHTML = $region['region'];
			$mergeAttr = $region['attrs'];
			
			unset($mergeAttr['id']);
			$documentHasTarget = $this->hasAttribute('id', $actionTarget, $htmlDocument); 
			$isNew = ($region['actionType'] == 'attr' && $region['action'] != 'replace'); 
			if(!$isNew) $isNew = $action == 'before' || $action == 'after';

			if($isNew) {
				// element is newly added element not already present
				$mergeAttr = array();
				$regionHTML = $region['html'];
				$attrs = $region['attrs'];
				$attrStr = count($attrs) ? ' ' . $this->renderAttributes($attrs, false) : '';
				if(!strlen(trim($attrStr))) $attrStr = '';
				if($region['actionType'] == 'bool') {
					$regionHTML = $region['region'];
				} else {
					$regionHTML = str_replace($region['open'], "<$region[name]$attrStr>", $regionHTML);
				}
			}
			
			if($debug) {
				$debugAction = $region['action'];
				if($debugAction == 'auto') $debugAction = $isNew ? 'insert' : 'replace';
				if($debugAction == 'replace' && empty($region['close'])) $debugAction = 'attr-update';
				$pwid = empty($region['pwid']) ? $region['actionTarget'] : $region['pwid']; 
				$open = $region['open'];
				$openLen = strlen($open);
				if($openLen > 50) $open = substr($open, 0, 30) . '[sm]... +' . ($openLen - 30) . ' bytes[/sm]>';
				$debugRegionStart = "[sm]" . trim(substr($region['region'], 0, 80));  
				$pos = strrpos($debugRegionStart, '>');
				if($pos) $debugRegionStart = substr($debugRegionStart, 0, $pos+1);
				$debugRegionStart .= " … [b]" . strlen($region['region']) . " bytes[/b][/sm]";
				//$debugRegionEnd = substr($region['region'], -30); 
				//$pos = strpos($debugRegionEnd, '</'); 
				//if($pos !== false) $debugRegionEnd = substr($debugRegionEnd, $pos);
				$region['note'] = strtoupper($debugAction) . " [b]#{$pwid}[/b] " .
					($region['actionTarget'] != $pwid ? "(target=$region[actionTarget])" : "") . 
					"[sm]with[/sm] $open"; 
				if($region['close']) {
					$region['note'] .= $this->debugNoteStr($debugRegionStart) . $region['close'];
				}
				$regionNote = $region['note'];  // [sm](position=$regionKey)[/sm]";
				
			} else {
				$regionNote = '';
			}	

			if(!$documentHasTarget) {
				// if the id attribute doesn't appear in the html, skip it
				// $xregions[$regionKey] = $xregion;
				if(self::debug) $rejectedNotes[] = $regionNote;
				
			} else {
				// update the markup
				$updates[] = array(
					'actionTarget' => "#$actionTarget", 
					'regionHTML' => $regionHTML, 
					'action' => $action, 
					'mergeAttr' => $mergeAttr,
				);
				if(is_string($htmlRegions)) {
					// remove region markup from $htmlRegions so we can later determine what’s left
					$htmlRegions = str_replace($region['html'], '', $htmlRegions);
				}
				$populatedNotes[] = $regionNote;
				$numUpdates++;
			}
		}

		foreach($updates as $u) {
			$htmlDocument = $this->update($u['actionTarget'], $u['regionHTML'], $htmlDocument, array(
				'action' => $u['action'],
				'mergeAttr' => $u['mergeAttr'],
			));
		}

		$htmlRegions = trim($htmlRegions);
			
		if($debug) {
			$leftoverBytes = strlen($leftoverMarkup);
			$htmlRegionsLen = strlen($htmlRegions); 
			$debugNotes = array(); 
			if($recursionLevel > 1) $debugNotes[] = "PASS: $recursionLevel";
			if(count($populatedNotes)) $debugNotes = array_merge($debugNotes, $populatedNotes); // implode($bull, $populatedNotes);
			if(count($rejectedNotes)) foreach($rejectedNotes as $note) $debugNotes[] = "SKIPPED: $note";
			if($leftoverBytes) $debugNotes[] = "$leftoverBytes non-region bytes skipped: [sm]{$leftoverMarkup}[/sm]";
			if($htmlRegionsLen) {
				if($recursionLevel > 1) {
					$debugNotes[] = "$htmlRegionsLen HTML-region bytes found no home after 2nd pass: [sm]{$htmlRegions}[/sm]";
				} else if($this->hasRegions($htmlRegions)) {
					$debugNotes[] = "$htmlRegionsLen HTML-region bytes remaining for 2nd pass: [sm]{$htmlRegions}[/sm]";
				} else {
					$debugNotes[] = "$htmlRegionsLen HTML bytes remaining, but no regions present: [sm]{$htmlRegions}[/sm]";
				}
			}
			if(count($this->debugNotes)) {
				$this->debugNotes = array_unique($this->debugNotes); 
				$debugNotes[] = "---------------";
				foreach($this->debugNotes as $n => $s) {
					$debugNotes[] = $this->debugNoteStr($s);
				}
			}
			if(!count($debugNotes)) $debugNotes[] = "Nothing found";
			$debugNotes = "• " . implode("\n• ", $debugNotes);
			$debugNotes = $this->wire('sanitizer')->entities($debugNotes);
			$debugNotes .= "\n[sm]" . Debug::timer($debugTimer) . " seconds[/sm]";
			$debugNotes = str_replace(array('[sm]', '[/sm]'), array('<small style="opacity:0.7">', '</small>'), $debugNotes);
			$debugNotes = str_replace(array('[b]', '[/b]'), array('<strong>', '</strong>'), $debugNotes);
			$debugNotes = "<pre class='pw-debug pw-region-debug'>$debugNotes</pre>$debugLandmark";
			$htmlDocument = str_replace($debugLandmark, $debugNotes, $htmlDocument); 
		} else if($hasDebugLandmark) {
			$htmlDocument = str_replace($debugLandmark, '', $htmlDocument); 
		}
		
		if(count($xregions) && $recursionLevel < 3) {
			// see if they can be populated now
			$numUpdates += $this->populate($htmlDocument, $xregions, $options);
		}
		
		if($recursionLevel === 1 && strlen($htmlRegions) && $this->hasRegions($htmlRegions)) {
			// see if more regions can be pulled from leftover $htmlRegions
			$numUpdates += $this->populate($htmlDocument, $htmlRegions, $options);
		}

		// remove region tags and pw-id attributes
		if($recursionLevel === 1 && $this->removeRegionTags($htmlDocument)) $numUpdates++;
	
		// if there is any leftover markup, place it above the HTML where it would usually go
		if(strlen($leftoverMarkup)) {
			$htmlDocument = $leftoverMarkup . $htmlDocument;
			$numUpdates++;
		}
		
		$recursionLevel--;
		
		if(!$recursionLevel && strpos($htmlDocument, 'pw-optional')) {
			$htmlDocument = $this->stripOptional($htmlDocument);
		}

		return $numUpdates; 
	}

	/**
	 * Remove any <region> or <pw-region> tags present in the markup, leaving their innerHTML contents
	 * 
	 * Also removes data-pw-id and pw-id attributes
	 * 
	 * @param string $html
	 * @return bool True if tags or attributes were removed, false if not
	 * 
	 */
	public function removeRegionTags(&$html) {
		
		$updated = false;
		
		if(stripos($html, '</region>') !== false || strpos($html, '</pw-region>') !== false) {
			$html = preg_replace('!</?(?:region|pw-region)(?:\s[^>]*>|>)!i', '', $html);
			$updated = true;
		}
		
		if(stripos($html, ' data-pw-id=') || stripos($html, ' pw-id=')) {
			$html = preg_replace('/(<[^>]+)(?: data-pw-id| pw-id)=["\']?[^>\s"\']+["\']?/i', '$1', $html);
			$updated = true;
		}
		
		return $updated; 
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

	/**
	 * Does the given HTML markup have references to any pw-actions?
	 * 
	 * Note: not currently used by this class. 
	 * 
	 * @param string $html
	 * @return bool
	 * 
	 */
	public function hasRegionActions(&$html) {
		$has = false;
		foreach($this->actions as $action) {
			if(strpos($html, "pw-$action") !== false) {
				// found pw-action, now perform a more thorough check
				if(preg_match('![="\'\s]pw-' . $action . '(?:["\'\s=][^<>]*>|>)!', $html)) {
					$has = true;
					break;
				}
			}
		}
		return $has;
	}
	
	protected function debugNoteStr($str, $maxLength = 0) {
		$str = str_replace(array("\r", "\n", "\t"), ' ', $str);
		while(strpos($str, '  ') !== false) $str= str_replace('  ', ' ', $str);
		if($maxLength) $str  = substr($str, 0, $maxLength);
		return trim($str);
	}

}