<?php namespace ProcessWire;

/**
 * InputfieldTinyMCETools
 * 
 * Helper tools for InputfieldTinyMCE module.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property array $jsonBlankObjectProperties
 *
 */ 
class InputfieldTinyMCETools extends InputfieldTinyMCEClass {

	/**
	 * Image fields indexed by template ID
	 * 
	 * @var array 
	 * 
	 */
	static protected $imageFields = array();

	/**
	 * Cache for linkConfig method
	 * 
	 * @var null 
	 * 
	 */
	static protected $linkConfig = null;

	/**
	 * @var MarkupHTMLPurifier|null 
	 * 
	 */
	static protected $purifier = null;
	
	/**
	 * Properties found in decoded JSON that were blank objects and should remain when encoded
	 *
	 * @var array
	 *
	 */
	protected $jsonBlankObjectProperties = array();

	/**
	 * Sanitize toolbar or plugin names
	 *
	 * @param string|array $value
	 * @return string
	 *
	 */
	public function sanitizeNames($value) {
		if(!is_array($value)) {
			$value = str_replace(array("\n", "\r", "\t"), ' ', $value);
			$value = explode(' ', $value);
		}
		foreach($value as $k => $v) {
			$v = trim($v);
			if((empty($v) || !ctype_alnum($v)) && $v !== '|') {
				unset($value[$k]);
			} else {
				$value[$k] = $v;
			}
		}
		return implode(' ', $value);
	}

	/**
	 * Get field that images can be uploaded to or null if none found
	 *
	 * @return Field|null
	 *
	 */
	public function getImageField() {
		$page = $this->inputfield->hasPage;
		if(!$page || !$page->id) return null;

		$template = $page->template;
		$alternates = array();
		$imageField = null;
		$imageFields = $this->inputfield->imageFields;
		
		if(!is_array($imageFields)) $imageFields = array();
		if(in_array('x', $imageFields)) return null; // x disables imageField
		
		foreach($imageFields as $fieldName) {
			$imageField = $page->getField($fieldName);
			if($imageField) break;
		}
		
		if($imageField) {
			// use configured imageField found above
		} else if(isset(self::$imageFields[$template->id])) {
			$imageField = self::$imageFields[$template->id];
			if($imageField === false) $imageField = null;
		} else {
			foreach($template->fieldgroup as $field) {
				if(!$field->type instanceof FieldtypeImage) continue;
				$maxFiles = (int) $field->get('maxFiles');
				if(!$maxFiles) {
					// found our image field
					$imageField = $field;
					break;
				}
				// do not allow 1-image fields
				if($maxFiles === 1) continue; 
			
				// check if image field supports more items
				$value = $page->get($field->name);
				if($value && $value->count() >= (int) $field->get('maxFiles')) continue;
				$alternates[] = $field;
			}
			// use an alternate that had a maxFiles value, if none could be found without a limit
			if(!$imageField && count($alternates)) $imageField = reset($alternates);
			self::$imageFields[$template->id] = ($imageField ? $imageField : false);
		}
		
		return $imageField;
	}

	/**
	 * Clean up a value that will be sent to/from the editor
	 *
	 * This is primarily for HTML Purifier
	 *
	 * @param string $value
	 * @return string
	 *
	 */
	public function purifyValue($value) {

		$value = (string) $value;
		if(strpos($value, "\r") !== false) $value = str_replace(array("\r\n", "\r"), "\n", $value);
		if(!strlen($value)) return '';

		$sanitizer = $this->wire()->sanitizer;

		if($this->inputfield->useFeature('purifier') && ($purifier = $this->purifier())) {
			$enableId = stripos($this->inputfield->toolbar, 'anchor') !== false;
			$purifier->set('Attr.AllowedFrameTargets', $this->linkConfig('targetOptions')); // allow links opened in new window/tab
			$purifier->set('Attr.EnableID', $enableId); // for anchor plugin use of id and name attributes
			$value = $purifier->purify($value);
		}

		$value = $this->purifyValueToggles($value);

		// remove UTF-8 line separator characters
		$value = str_replace($sanitizer->unentities('&#8232;'), '', $value);

		return $value;
	}

	/**
	 * Apply markup cleaning toggles
	 *
	 * @param string $value
	 * @return string
	 *
	 */
	public function purifyValueToggles($value) {
		// convert <div> to paragraphs
		$toggles = $this->inputfield->toggles;
		if(!is_array($toggles)) return $value;
		
		foreach($toggles as $toggle) {
			switch($toggle) {
				case InputfieldTinyMCE::toggleCleanDiv: 
					// convert <div> to <p>
					if(strpos($value, '<div') !== false) {
						$value = preg_replace('{\s*(</?)div[^><]*>\s*}is', '$1' . 'p>', $value);
						while(strpos($value, '<p><p>') !== false) {
							$value = str_replace(array('<p><p>', '</p></p>'), array('<p>', '</p>'), $value);
						}
					}
					break;
				case InputfieldTinyMCE::toggleCleanP:
					// remove empty paragraphs
					$value = str_replace(array('<p><br /></p>', '<p>&nbsp;</p>', "<p>\xc2\xa0</p>", '<p></p>', '<p> </p>'), '', $value);
					break;
				case InputfieldTinyMCE::toggleCleanNbsp:
					// convert non-breaking space to regular space
					$value = str_ireplace('&nbsp;', ' ', $value);
					$value = str_replace("\xc2\xa0",' ', $value);
					break;
				case InputfieldTinyMCE::toggleRemoveStyles:
					// remove all style attributes
					if(strpos($value, 'style=')) {
						if(preg_match_all('!(<.+?)\sstyle=(["\']).*?\2!i', $value, $matches)) {
							foreach($matches[0] as $key => $fullMatch) {
								$startMatch = $matches[1][$key];
								$value = str_replace($fullMatch, $startMatch, $value);
							}
						}
					}
					break;
			}
		}

		return $value;
	}
	
	/**
	 * @return MarkupHTMLPurifier
	 *
	 */
	public function purifier() {
		if(self::$purifier === null) {
			self::$purifier = $this->wire()->modules->get('MarkupHTMLPurifier');
			if(!self::$purifier) {
				$this->error("Unable to load required MarkupHTMLPurifier module");
			}
		}
		return self::$purifier;
	}

	/**
	 * Get config for ProcessPageEditLink module
	 *
	 * @param string $key
	 * @return array|string
	 *
	 */
	public function linkConfig($key = '') {
		$sanitizer = $this->wire()->sanitizer;

		if(self::$linkConfig === null) {
			self::$linkConfig = $this->wire()->modules->getModuleConfigData('ProcessPageEditLink');
		}

		$value = &self::$linkConfig;

		if($key === 'targetOptions') {
			$value = isset($value['targetOptions']) ? $value['targetOptions'] : '_blank';
			$value = explode("\n", $value);
			foreach($value as $k => $v) {
				$v = trim(trim($v), '+');
				if($sanitizer->name($v) !== $v) continue;
				$value[$k] = $v;
			}

		} else if($key === 'classOptions') {
			$value = isset($value[$key]) ? $value[$key] : '';
			$options = array();
			foreach(explode("\n", $value) as $option) {
				$option = trim($option, '+ ');
				if($sanitizer->nameFilter($option, array('-', '_', ':'), '-') !== $option) continue;
				$options[] = $option;
			}
			$value = implode(',', $options);
		}

		return $value;
	}

	/**
	 * Decode JSON
	 * 
	 * @param string $json JSON string
	 * @param string $propertyName Name of property JSON is for
	 * @return array
	 * 
	 */
	public function jsonDecode($json, $propertyName) {
		$json = trim((string) $json);
		if(!strlen($json)) return array();
		$a = json_decode($json, true);
		if(!is_array($a)) {
			$this->warning(sprintf(
				$this->_('Error decoding JSON for TinyMCE property "%1$s" - %2$s'),
				$propertyName, json_last_error_msg()
			)); 
			$a = array();
		} else if(strpos($json, '{}') !== false) {
			if(preg_match_all('/"([_a-z0-9]+)":\s*[{][}]/i', $json, $matches)) {
				foreach($matches[1] as $name) {
					$this->jsonBlankObjectProperties[$name] = $name;
				}
			}
			
		}
		return $a;	
	}

	/**
	 * Decode JSON file
	 * 
	 * @param string $file
	 * @param string $propertyName
	 * @return array
	 * 
	 */
	public function jsonDecodeFile($file, $propertyName) {
		if(empty($file)) return array();
		if(!file_exists($file)) {
			$this->warning($propertyName . ' - ' . $this->_('File does not exist') . " - $file"); 
			return array();
		}
		if(strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json') {
			$this->warning($propertyName . ' - ' . $this->_('File extension is not .json') . " - $file");
			return array();
		}
		return $this->jsonDecode(file_get_contents($file), $propertyName);
	}

	/**
	 * Encode array to JSON
	 * 
	 * @param array $a
	 * @param string $propertyName Name of property JSON is for
	 * @param bool $pretty
	 * @return string
	 * 
	 */
	public function jsonEncode($a, $propertyName, $pretty = true) {
		if(!is_array($a)) return '';
		if($pretty) {
			$json = json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		} else {
			$json = json_encode($a);
		}
		if($json === false) {
			$this->warning(sprintf(
				$this->_('Error encoding JSON for TinyMCE property "%1$s" - %2$s'),
				$propertyName, json_last_error_msg()
			));
			$json = '';
		}
		if(count($this->jsonBlankObjectProperties) && strpos($json, '[]') !== false) {
			// convert JSON arrays [] to objects {}
			foreach($this->jsonBlankObjectProperties as $name) {
				$json = str_replace(array("\"$name\": []", "\"$name\":[]"), "\"$name\": {}", $json);
			}
		}
		return (string) $json;
	}

	/**
	 * Prepare pasteFilters string for JS
	 * 
	 * This converts the rules to a longer format that is optimized for matching from the 
	 * InputfieldTinyMCE.js pasteProcess() function.
	 * 
	 * @return string
	 * 
	 */
	public function getPasteFiltersForJS() {
		
		$pasteFilter = trim(strtolower($this->inputfield->pasteFilter));
		if($pasteFilter === 'default') $pasteFilter = InputfieldTinyMCE::defaultPasteFilter;
		
		if(strpos($pasteFilter, "\n")) $pasteFilter = str_replace("\n", ',', $pasteFilter);
		if(strpos($pasteFilter, ' ')) $pasteFilter = str_replace(' ', '', $pasteFilter);
		
		$pasteFilters = array();
		
		foreach(explode(',', $pasteFilter) as $tag) {
			$tag = trim($tag);
			if(empty($tag)) continue;
			if(strpos($tag, '[')) {
				// tag includes attributes
				list($tag, $attrs) = explode('[', $tag, 2);
				if(empty($tag) || !ctype_alnum($tag)) continue;
				$attrs = rtrim($attrs, ']');
				if(strpos($attrs, '=')) {
					// i.e. img[class=align_left|align_right]
					list($attrs, $values) = explode('=', $attrs, 2);
					$values = strpos($values, '|') ? explode('|', $values) : array($values);
				} else {
					// i.e. img[src|alt]
					$values = null;
				}
				$attrs = strpos($attrs, '|') ? explode('|', $attrs) : array($attrs);
				foreach($attrs as $attr) {
					if(!ctype_alnum(str_replace(['-', '_'], '', $attr))) continue; // invalid attribute
					if($values) {
						foreach($values as $value) {
							if(!ctype_alnum(str_replace(['-', '_', ':', '.', '@'], '', $value))) continue; // invalid value
							$pasteFilters[] = $tag . "[$attr=$value]";
						}
					} else {
						$pasteFilters[] = $tag . '[' . $attr . ']';
					}
				}
			} else {
				// tag only or 'tag=replacement'
				if(!ctype_alnum(str_replace('=', '', $tag))) continue;
				$pasteFilters[] = $tag;
			}
		}
		
		return implode(',', $pasteFilters);
	}

	/**
	 * Get content.css file contents for inline editor output
	 *
	 * @return string
	 * @deprecated
	 *
	public function getContentCssInline() {
		$file = $this->getContentCssFile();
		$css = file_get_contents($file);
		$css = str_replace(array("\n", "\t", "\r", "  "), " ", $css);
		$css = str_replace('}', "}\n", $css);
		while(strpos($css, '  ') !== false) $css = str_replace('  ', ' ', $css);
		$css = str_replace(array(' { ', ' } ', '; ', ': ', ', ', ';}'), array('{', '}', ';', ':', ',', '}'), $css);
		$lines = explode("\n", $css);
		foreach($lines as $key => $line) {
			$line = trim($line);
			if(empty($line)) {
				unset($lines[$key]);
				continue;
			}
			if(strpos($line, '{')) {
				list($a, $b) = explode('{', $line, 2);
				$a = str_replace(',', ',.mce-content-body ', $a);
				$line = $a . '{' . $b;
			}
			if(strpos($line, 'body{') === 0) $line = str_replace('body{', '{', $line);
			$lines[$key] = ".mce-content-body $line";
		}
		return implode("\n", $lines);
	}
	 */

	/**
	 * @param string $name
	 * @return array|mixed|string|null
	 * 
	 */
	public function __get($name) {
		if($name === 'jsonBlankObjectProperties') return $this->jsonBlankObjectProperties;
		return parent::__get($name);
	}

}
