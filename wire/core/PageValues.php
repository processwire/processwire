<?php namespace ProcessWire;

/**
 * ProcessWire Page Values
 *
 * Provides implementation for several Page value get() functions.
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.205
 *
 */

class PageValues extends Wire {
	
	/**
	 * Given a 'field.subfield' type string traverse properties and return value
	 *
	 * @param Page $page
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function getDotValue(Page $page, $key) {

		$keys = explode('.', $key);
		if(count($keys) === 1) return $page->get($key);

		// test if any parts of key can potentially refer to API variables
		foreach($keys as $key) {
			if($page->wire($key) || $key === 'pass') return null;
		}

		$value = $page;
		$values = array();

		$wireArrayProperties = array('first', 'last', 'count', 'keys', 'values');

		do {
			$key = array_shift($keys);
			$k = $key;
			$index = '';

			// k is key without brackets (if any were present) 
			if(strpos($k, '[')) {
				list($k, $index) = explode('[', $k, 2);
				$index = rtrim($index, ']');
				if(ctype_digit($index)) $index = (int) $index;
			}
			if($value instanceof Page && !in_array($key, $wireArrayProperties)) {
				// value is a Page
				if(isset(PageProperties::$traversalReturnTypes[$k])) {
					// traversal property: Page or PageArray
					// parent, rootParent, child, next, prev, children, parents, siblings
					$value = $value->$key();
				} else {
					// native base property or custom field value
					$value = $value->get($key);
				}
			} else if($value instanceof WireArray) {
				if(in_array($k, $wireArrayProperties)) {
					$value = $value->getProperty($k);
				} else {
					$value = $value->each($k);
					// convert PHP array to WireArray if there are keys remaining (for next round)
					if(count($keys)) $value = $page->wire(WireArray($value));
				}
				if(is_int($index)) {
					// index is integer
					if(WireArray::iterable($value)) {
						$value = isset($value[$index]) ? $value[$index] : null;
					}
				} else if($index) {
					// index is selector
					if($value instanceof WireArray) $value = $value->find($index);
				}
			} else if($value instanceof WireData) {
				$v = $value->get($key);
				if($v === null) switch($key) {
					// self-generated equivalents for WireArray properties/methods
					case 'first':
					case 'last': $v = $value; break;
					case 'count': $v = 1; break;
					case 'values': $v = array($value); break;
					case 'keys': $v = ("$value" === $value->className() ? array(0) : array("$value")); break;
				}
				$value = $v;
			} else if(is_array($value)) {
				foreach($value as $kk => $vv) {
					$value[$kk] = $vv instanceof Wire ? $vv->$k : $vv;
				}
			} else {
				$value = null;
			}

			$values[] = $value;

		} while($value !== null && count($keys));

		// if($value === null && count($values) && $values[0] !== null) {
		// if first key didn't return null then try again with WireData native method
		// $value = $page->getDot($_key);
		// }

		return $value;
	}

	/**
	 * Get value that ends with square brackets to get iterable value, filtered value or property value
	 *
	 * ~~~~~
	 * $iterableValue = $page->get('field_name[]');
	 * ~~~~~
	 * Note: When requesting an iterable value, this method will return an empty array in cases where
	 * the Page::get() method would return null.
	 *
	 * @param Page $page
	 * @param string $key
	 * @param mixed $value Value to use rather than pulling from $page
	 * @return array|mixed|Page|PageArray|Wire|WireArray|WireData|string|\Traversable
	 *
	 */
	public function getBracketValue(Page $page, $key, $value = null) {

		if(strpos($key, '.')) return $this->getDotValue($page, $key);
		if(substr($key, -1) !== ']') return null;

		$property = '';
		$selector = '';
		$getIterable = true;

		$key = rtrim($key, ']');
		list($key, $index) = explode('[', $key, 2);

		if(strpos($index, '][')) {
			// i.e. field[selector][0]
			list($selector, $index) = explode('][', $index);
		}

		if(ctype_digit($index)) {
			$index = (int) $index;
			$getIterable = false;
		}

		if($index !== '' && !is_int($index)) {
			if(ctype_alnum(str_replace('_', '', $index))) {
				$property = $index;
			} else {
				$selector = $index;
			}
			$index = '';
		}

		if($value === null) {
			if($selector) {
				// filter FieldtypeMulti values at DB level
				// using $page->field_name($selector) method feature
				$value = $page->$key($selector);
				$selector = '';
			} else {
				$value = $page->get($key);
			}
		}

		if($value === null) {
			return $getIterable ? array() : null;
		}

		if(is_object($value)) {
			if($value instanceof Page) {
				$value = $page->wire()->pages->newPageArray()->add($value);
			} else if($value instanceof WireArrayItem) {
				$value = $value->getWireArray();
			} else if($value instanceof WireData) {
				$value = $page->wire(WireArray([$value]));
			} else if($value instanceof \Traversable) {
				// WireArray or other
			} else {
				$value = array($value);
			}
		} else if($getIterable) {
			$value = is_array($value) ? $value : array($value);
		}

		if($property !== '') {
			if($value instanceof WireArray) {
				$value = $value->each($property);
			}
		} else if($selector !== '') {
			if($value instanceof WireArray) {
				$value = $value->find($selector);
				$value->resetTrackChanges();
			}
		} else if(is_int($index)) {
			if($value instanceof WireArray){
				$value = $value->eq($index);
			} else if(is_array($value) || $value instanceof \ArrayAccess) {
				$value = isset($value[$index]) ? $value[$index] : null;
			} else if(WireArray::iterable($value)) {
				$n = 0;
				$found = false;
				foreach($value as $v) {
					if($n === $index) {
						$value = $v;
						$found = true;
						break;
					}
					$n++;
				}
				if(!$found) $value = null;
			}
		}

		return $value;
	}


	/**
	 * Get multiple Page property/field values in an array
	 *
	 * This method works exactly the same as the `get()` method except that it accepts an
	 * array (or CSV string) of properties/fields to get, and likewise returns an array
	 * of those property/field values. By default it returns a regular (non-indexed) PHP
	 * array in the same order given. To instead get an associative array indexed by the
	 * property/field names given, specify `true` for the `$assoc` argument.
	 *
	 * ~~~~~
	 * // returns regular array i.e. [ 'foo val', 'bar val' ]
	 * $a = $page->getMultiple([ 'foo', 'bar' ]);
	 * list($foo, $bar) = $a;
	 *
	 * // returns associative array i.e. [ 'foo' => 'foo val', 'bar' => 'bar val' ]
	 * $a = $page->getMultiple([ 'foo', 'bar' ], true);
	 * $foo = $a['foo'];
	 * $bar = $a['bar'];
	 *
	 * // CSV string can also be used instead of array
	 * $a = $page->getMultiple('foo,bar');
	 * ~~~~~
	 *
	 * @param page $page
	 * @param array|string $keys Array or CSV string of properties to get.
	 * @param bool $assoc Get associative array indexed by given properties? (default=false)
	 * @return array
	 *
	 */
	public function getMultiple(Page $page, $keys, $assoc = false) {
		if(!is_array($keys)) {
			$keys = (string) $keys;
			if(strpos($keys, ',') !== false) {
				$keys = explode(',', $keys);
			} else {
				$keys = array($keys);
			}
		}
		$values = array();
		foreach($keys as $key) {
			$key = trim("$key");
			$value = strlen($key) ? $page->get($key) : null;
			if($assoc) {
				$values[$key] = $value;
			} else {
				$values[] = $value;
			}
		}
		return $values;
	}

	/**
	 * Given a Multi Key, determine if there are multiple keys requested and return the first non-empty value
	 *
	 * A Multi Key is a string with multiple field names split by pipes, i.e. headline|title
	 *
	 * Example: browser_title|headline|title - Return the value of the first field that is non-empty
	 *
	 * @param page $page
	 * @param string $multiKey
	 * @param bool $getKey Specify true to get the first matching key (name) rather than value
	 * @return null|mixed Returns null if no values match, or if there aren't multiple keys split by "|" chars
	 *
	 */
	public function getFieldFirstValue(Page $page, $multiKey, $getKey = false) {

		// looking multiple keys split by "|" chars, and not an '=' selector
		if(strpos($multiKey, '|') === false || strpos($multiKey, '=') !== false) return null;

		$value = null;
		$keys = explode('|', $multiKey);

		foreach($keys as $key) {
			$v = $page->getUnformatted($key);

			if(is_array($v) || $v instanceof WireArray) {
				// array or WireArray
				if(!count($v)) continue;

			} else if(is_object($v)) {
				// like LanguagesPageFieldValue 
				$str = trim((string) $v);
				if(!strlen($str)) continue;

			} else if(is_string($v)) {
				$v = trim($v);
			}

			if($v) {
				if($page->of()) {
					$v = $page->get($key);
				}
				if($v) {
					$value = $getKey ? $key : $v;
					break;
				}
			}
		}

		return $value;
	}
	
	/**
	 * Return the markup value for a given field name or {tag} string
	 *
	 * 1. If given a field name (or `name.subname` or `name1|name2|name3`) it will return the
	 *    markup value as defined by the fieldtype.
	 * 2. If given a string with field names referenced in `{tags}`, it will populate those
	 *    tags and return the populated string.
	 *
	 * @param Page $page
	 * @param string $key Field name or markup string with field {name} tags in it
	 * @return string
	 * @see Page::getText()
	 *
	 */
	public function getMarkup(Page $page, $key) {

		if(strpos($key, '{') !== false && strpos($key, '}')) {
			// populate a string with {tags}
			// note that the wirePopulateStringTags() function calls back on this method
			// to retrieve the markup values for each of the found field names
			return wirePopulateStringTags($key, $page);
		}

		if(strpos($key, '|') !== false) {
			$key = $this->getFieldFirstValue($page, $key, true);
			if(!$key) return '';
		}

		if($this->wire()->sanitizer->name($key) != $key) {
			// not a possible field name
			return '';
		}

		$parts = strpos($key, '.') ? explode('.', $key) : array($key);
		$value = $page;

		do {

			$name = array_shift($parts);
			$field = $page->getField($name);

			if(!$field && $this->wire($name)) {
				// disallow API vars
				$value = '';
				break;
			}

			if($value instanceof Page) {
				$value = $value->getFormatted($name);
			} else if($value instanceof WireData) {
				$value = $value->get($name);
			} else {
				$value = $value->$name;
			}

			if($field && count($parts) < 2) {
				// this is a field that will provide its own formatted value
				$subname = count($parts) == 1 ? array_shift($parts) : '';
				if(!$subname || !$this->wire($subname)) {
					$value = $field->type->markupValue($page, $field, $value, $subname);
				}
			}

		} while(is_object($value) && count($parts));

		if(is_object($value)) {
			if($value instanceof Page) $value = $value->getFormatted('title|name');
			if($value instanceof PageArray) $value = $value->getMarkup();
		}

		if(!is_string($value)) $value = (string) $value;

		return $value;
	}
	
	/**
	 * Same as getMarkup() except returned value is plain text
	 *
	 * If no `$entities` argument is provided, returned value is entity encoded when output formatting
	 * is on, and not entity encoded when output formatting is off.
	 *
	 * @param Page $page
	 * @param string $key Field name or string with field {name} tags in it.
	 * @param bool $oneLine Specify true if returned value must be on single line.
	 * @param bool|null $entities True to entity encode, false to not. Null for auto, which follows page's outputFormatting state.
	 * @return string
	 * @see Page::getMarkup()
	 *
	 */
	public function getText(Page $page, $key, $oneLine = false, $entities = null) {
		$value = $page->getMarkup($key);
		$length = strlen($value);
		if(!$length) return '';
		$options = array(
			'entities' => ($entities === null ? $page->of() : (bool) $entities)
		);
		$sanitizer = $this->wire()->sanitizer;
		if($oneLine) {
			$value = $sanitizer->markupToLine($value, $options);
		} else {
			$value = $sanitizer->markupToText($value, $options);
		}
		// if stripping tags from non-empty value made it empty, just indicate that it was markup and length
		if(!strlen(trim($value))) $value = "markup($length)";
		return $value;
	}

	/**
	 * Set the status setting, with some built-in protections
	 *
	 * This method is also used when you set status directly, i.e. `$page->status = $value;`.
	 *
	 * ~~~~~
	 * // set status to unpublished
	 * $page->setStatus('unpublished');
	 *
	 * // set status to hidden and unpublished
	 * $page->setStatus('hidden, unpublished');
	 *
	 * // set status to hidden + unpublished using Page constant bitmask
	 * $page->setStatus(Page::statusHidden | Page::statusUnpublished);
	 * ~~~~~
	 *
	 * @param Page $page
	 * @param int|array|string Status value, array of status names or values, or status name string.
	 * @return Page
	 * @see Page::addStatus(), Page::removeStatus()
	 *
	 */
	public function setStatus(Page $page, $value) {

		if(!is_int($value)) {
			// status provided as something other than integer
			if(is_string($value) && !ctype_digit($value)) {
				// string of one or more status names
				if(strpos($value, ',') !== false) $value = str_replace(array(', ', ','), ' ', $value);
				$value = explode(' ', strtolower($value));
			}
			if(is_array($value)) {
				// array of status names or numbers
				$status = 0;
				foreach($value as $v) {
					if(is_int($v) || ctype_digit("$v")) { // integer
						$status = $status | ((int) $v);
					} else if(is_string($v) && isset(PageProperties::$statuses[$v])) { // string (status name)
						$status = $status | PageProperties::$statuses[$v];
					}
				}
				if($status) $value = $status;
			}
			// note if $value started as an integer string, i.e. "123", it gets passed through to below
		}

		$value = (int) $value;
		$status = $page->_getSetting('status');
		$override = $status & Page::statusSystemOverride;
		if(!$override) {
			if($status & Page::statusSystemID) $value = $value | Page::statusSystemID;
			if($status & Page::statusSystem) $value = $value | Page::statusSystem;
		}
		$page->_setSetting('status', $value);
		if($value & Page::statusDeleted) {
			// disable any instantiated filesManagers after page has been marked deleted
			// example: uncache method polls filesManager
			$page->__unset('filesManager');
		}
		return $page;
	}

	/**
	 * Remove the specified status from this page
	 *
	 * This is the preferred way to remove a status from a page. There is also a corresponding `Page::addStatus()` method.
	 *
	 * ~~~~~
	 * // Remove hidden status from the page using status name
	 * $page->removeStatus('hidden');
	 *
	 * // Remove hidden status from the page using status constant
	 * $page->removeStatus(Page::statusHidden);
	 * ~~~~~
	 *
	 * @param Page $page
	 * @param int|string $statusFlag Status flag constant or string representation (hidden, locked, unpublished, etc.)
	 * @return Page
	 * @throws WireException If you attempt to remove `Page::statusSystem` or `Page::statusSystemID` statuses without first adding `Page::statusSystemOverride` status.
	 * @see Page::addStatus(), Page::hasStatus()
	 *
	 */
	public function removeStatus(Page $page, $statusFlag) {
		if(is_string($statusFlag) && isset(PageProperties::$statuses[$statusFlag])) {
			$statusFlag = PageProperties::$statuses[$statusFlag];
		}
		$statusFlag = (int) $statusFlag;
		$status = $page->_getSetting('status');
		$override = $status & Page::statusSystemOverride;
		if($statusFlag == Page::statusSystem || $statusFlag == Page::statusSystemID) {
			if(!$override) throw new WireException('Cannot remove statusSystem from page without statusSystemOverride');
		}
		$page->status = $status & ~$statusFlag; 
		return $page;
	}

	/**
	 * Set the page name, optionally for specific language
	 *
	 * ~~~~~
	 * // Set page name (default language)
	 * $page->setName('my-page-name');
	 *
	 * // This is equivalent to the above
	 * $page->name = 'my-page-name';
	 *
	 * // Set page name for Spanish language
	 * $page->setName('la-cerveza', 'es');
	 * ~~~~~
	 *
	 * @param string $value Page name that you want to set
	 * @param Language|string|int|null $language Set language for name (can also be language name or string in format "name1234")
	 * @return Page
	 *
	 */
	public function setName(Page $page, $value, $language = null) {

		$key = 'name';
		$charset = $this->wire()->config->pageNameCharset;
		$sanitizer = $this->wire()->sanitizer;
		$isLoaded = $page->isLoaded();

		if($isLoaded) {
			if(is_int($language)) {
				$key .= $language;
				$existingValue = $page->get($key);
			} else if($language && $language !== 'name') {
				// update $key to contain language ID when applicable
				$languages = $this->wire()->languages;
				if($languages) {
					if(!is_object($language)) {
						if(strpos($language, 'name') === 0) $language = (int) substr($language, 4);
						$language = $languages->getLanguage($language);
						if(!$language || !$language->id || $language->isDefault()) $language = '';
					}
					if(!$language) return $page;
					$key .= $language->id;
				}
				$existingValue = $page->get($key);
			} else {
				$existingValue = $page->_getSetting($key);
				if($existingValue === null) $existingValue = '';
			}

			// name is being set after page has already been loaded
			if($charset === 'UTF8') {
				// UTF8 page names allowed but decoding not allowed
				$value = $sanitizer->pageNameUTF8($value);

			} else if(empty($existingValue)) {
				// ascii, and beautify if there is no existing value
				$value = $sanitizer->pageName($value, true);

			} else {
				// ascii page name and do not beautify
				$value = $sanitizer->pageName($value, false);
			}
			
		} else {
			// name being set while page is loading
			if($charset === 'UTF8' && strpos("$value", 'xn-') === 0) {
				// allow decode of UTF8 name while page is loading
				$value = $sanitizer->pageName($value, Sanitizer::toUTF8);
			} else {
				// regular ascii page name while page is loading, do nothing to it
			}
			if($language) {
				if(ctype_digit("$language")) {
					$key = "name$language";
				} else if(is_string($language)) {
					$key = $language; // i.e. name1234
				}
			}
		}

		if($key === 'name') {
			$page->_setSetting($key, $value);
		} else if(!$isLoaded || $page->_getSetting('quietMode')) { 
			$page->_parentSet($key, $value);
		} else {
			$this->setFieldValue($page, $key, $value, $isLoaded); // i.e. name1234
		}

		return $page;
	}
	
	/**
	 * Return all Inputfield objects necessary to edit this page
	 *
	 * This method returns an InputfieldWrapper object that contains all the custom Inputfield objects
	 * required to edit this page. You may also specify a `$fieldName` argument to limit what is contained
	 * in the returned InputfieldWrapper.
	 *
	 * Please note this method deals only with custom fields, not system fields name 'name' or 'status', etc.,
	 * as those are exclusive to the ProcessPageEdit page editor.
	 *
	 * #pw-advanced
	 *
	 * @param string|array $fieldName Optional field to limit to, typically the name of a fieldset or tab.
	 *  - Or optionally specify array of $options (See `Fieldgroup::getPageInputfields()` for options).
	 * @return null|InputfieldWrapper Returns an InputfieldWrapper array of Inputfield objects, or NULL on failure.
	 *
	 */
	public function getInputfields(Page $page, $fieldName = '') {

		$of = $page->of();
		if($of) $page->of(false);

		$template = $page->template();
		$fieldgroup = $template ? $template->fieldgroup : null;

		if($fieldgroup) {
			if(is_array($fieldName) && !ctype_digit(implode('', array_keys($fieldName)))) {
				// fieldName is an associative array of options for Fieldgroup::getPageInputfields
				$wrapper = $fieldgroup->getPageInputfields($page, $fieldName);
			} else {
				$wrapper = $fieldgroup->getPageInputfields($page, '', $fieldName);
			}
		} else {
			$wrapper = null;
		}

		if($of) $page->of(true);

		return $wrapper;
	}

	/**
	 * Get a single Inputfield for the given field name
	 *
	 * - If requested field name refers to a single field, an Inputfield object is returned.
	 * - If requested field name refers to a fieldset or tab, then an InputfieldWrapper representing will be returned.
	 * - Returned Inputfield already has values populated to it.
	 * - Please note this method deals only with custom fields, not system fields name 'name' or 'status', etc.,
	 *   as those are exclusive to the ProcessPageEdit page editor.
	 *
	 * #pw-advanced
	 *
	 * @param string $fieldName
	 * @return Inputfield|InputfieldWrapper|null Returns Inputfield, or null if given field name doesn't match field for this page.
	 *
	 */
	public function getInputfield(Page $page, $fieldName) {
		$inputfields = $this->getInputfields($page, $fieldName);
		if($inputfields) {
			$field = $this->wire()->fields->get($fieldName);
			if($field && $field->type instanceof FieldtypeFieldsetOpen) {
				// requested field name is a fieldset, returns InputfieldWrapper
				return $inputfields;
			} else {
				// requested field name is a single field, return Inputfield
				return $inputfields->children()->first();
			}
		} else {
			// requested field name is not applicable to this page
			return null;
		}
	}
	
	/**
	 * Get the icon name associated with this Page (if applicable)
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return string
	 *
	 */
	public function getIcon(Page $page) {
		$template = $page->template();
		if(!$template) return '';
		if($page->hasField('process')) {
			$process = $page->getUnformatted('process');
			if($process) {
				$info = $this->wire()->modules->getModuleInfoVerbose($process);
				if(!empty($info['icon'])) return $info['icon'];
			}
		}
		return $template->getIcon();
	}

	/**
	 * Process and instantiate any data in the fieldDataQueue
	 *
	 * This happens after setIsLoaded(true) is called
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param array $fieldDataQueue
	 * @return bool
	 *
	 */
	public function processFieldDataQueue(Page $page, array $fieldDataQueue) {

		$template = $page->template();
		if(!$template) return false;

		$fieldgroup = $template->fieldgroup;
		if(!$fieldgroup) return false;

		foreach($fieldDataQueue as $key => $value) {

			$field = $fieldgroup->get($key);
			if(!$field) continue;

			// check for autojoin multi fields, which may have multiple values bundled into one string
			// as a result of an sql group_concat() function
			if($field->type instanceof FieldtypeMulti && ($field->flags & Field::flagAutojoin)) {
				foreach($value as $k => $v) {
					if(is_string($v) && strpos($v, FieldtypeMulti::multiValueSeparator) !== false) {
						$value[$k] = explode(FieldtypeMulti::multiValueSeparator, $v);
					}
				}
			}

			// if all there is in the array is 'data', then we make that the value rather than keeping an array
			// this is so that Fieldtypes that only need to interact with a single value don't have to receive an array of data
			if(count($value) == 1 && array_key_exists('data', $value)) $value = $value['data'];

			$this->setFieldValue($page, $key, $value, false);
		}
		
		$page->fieldDataQueue(array());

		return true;
	}

	/**
	 * Get a Field object in context or NULL if not valid for this page
	 *
	 * Field in context is only returned when output formatting is on.
	 *
	 * #pw-advanced
	 *
	 * @param Page $page
	 * @param string|int|Field $field
	 * @return Field|null
	 * @todo determine if we can always retrieve in context regardless of output formatting.
	 *
	 */
	public function getField(Page $page, $field) {
		$template = $page->template();
		$fieldgroup = $template ? $template->fieldgroup : null;
		if(!$fieldgroup) return null;
		if($page->of() && $fieldgroup->hasFieldContext($field)) {
			$value = $fieldgroup->getFieldContext($field);
		} else {
			$value = $fieldgroup->getField($field);
		}
		return $value;
	}

	/**
	 * Returns a FieldsArray of all Field objects in the context of this Page
	 *
	 * Unlike $page->fieldgroup (or its alias $page->fields), the fields returned from
	 * this method are in the context of this page/template. Meaning returned Field
	 * objects may have some properties that are different from the Field outside of
	 * the context of this page.
	 *
	 * #pw-advanced
	 *
	 * @param Page $page
	 * @return FieldsArray of Field objects
	 *
	 */
	public function getFields(Page $page) {
		$template = $page->template();
		$fields = new FieldsArray();
		$this->wire($fields);
		if(!$template) return $fields;
		$fieldgroup = $template->fieldgroup;
		foreach($fieldgroup as $field) {
			if($fieldgroup->hasFieldContext($field)) {
				$field = $fieldgroup->getFieldContext($field);
			}
			if($field) $fields->add($field);
		}
		return $fields;
	}

	/**
	 * Returns whether or not given $field name, ID or object is valid for this Page
	 *
	 * Note that this only indicates validity, not whether the field is populated.
	 *
	 * #pw-advanced
	 *
	 * @param Page $page
	 * @param int|string|Field|array $field Field name, object or ID to check.
	 *  - In 3.0.126+ this may also be an array or pipe "|" separated string of field names to check.
	 * @return bool|string True if valid, false if not.
	 *  - In 3.0.126+ returns first matching field name if given an array of field names or pipe separated string of field names.
	 *
	 */
	public function hasField(Page $page, $field) {
		$template = $page->template();
		if(!$template) return false;
		if(is_string($field) && strpos($field, '|') !== false) {
			$field = explode('|', $field);
		}
		if(is_array($field)) {
			$result = false;
			foreach($field as $f) {
				$f = trim($f);
				if(!empty($f) && $this->hasField($page, $f)) $result = $f;
				if($result) break;
			}
		} else {
			$result = $template->fieldgroup->hasField($field);
		}
		return $result;
	}

	/**
	 * Get the output TemplateFile object for rendering this page (internal use only)
	 *
	 * You can retrieve the results of this by calling $page->out or $page->output
	 *
	 * #pw-internal
	 *
	 * @param bool $forceNew Forces it to return a new (non-cached) TemplateFile object (default=false)
	 * @return TemplateFile|null
	 *
	 */
	public function output(Page $page, $forceNew = false) {
		$template = $page->template();
		if(!$template) return null;
		/** @var TemplateFile $output */
		$output = $this->wire(new TemplateFile());
		$output->setThrowExceptions(false);
		$output->setFilename($template->filename);
		$fuel = $this->wire()->fuel->getArray();
		$output->set('wire', $this->wire());
		foreach($fuel as $key => $value) $output->set($key, $value);
		$output->set('page', $page);
		return $output;
	}

	/**
	 * Get the value for a non-native page field, and call upon Fieldtype to join it if not autojoined
	 *
	 * @param string $key Name of field to get
	 * @param string $selector Optional selector to filter load by...
	 *   ...or, if not in selector format, it becomes an __invoke() argument for object values .
	 * @return null|mixed
	 *
	 */
	public function getFieldValue(Page $page, $key, $selector = '') {

		$template = $page->template();
		if(!$template) return $page->_parentGet($key);

		$field = $page->getField($key);
		$value = $page->_parentGet($key);

		if(!$field) return $value;  // likely a runtime field, not part of our data

		/** @var Fieldtype $fieldtype */
		$fieldtype = $field->type;
		$invokeArgument = '';

		if($value !== null && $page->wakeupNameQueue($key)) {
			$value = $fieldtype->_callHookMethod('wakeupValue', array($page, $field, $value));
			$value = $fieldtype->sanitizeValue($page, $field, $value);
			$trackChanges = $page->trackChanges(true);
			$page->setTrackChanges(false);
			$page->_parentSet($key, $value);
			$page->setTrackChanges($trackChanges);
			$page->wakeupNameQueue($key, false);
		}

		if($field->useRoles && $page->of()) {
			// API access may be limited when output formatting is ON
			if($field->flags & Field::flagAccessAPI) {
				// API access always allowed because of flag
			} else if($page->viewable($field)) {
				// User has view permission for this field
			} else {
				// API access is denied when output formatting is ON
				// so just return a blank value as defined by the Fieldtype
				// note: we do not store this blank value in the Page, so that
				// the real value can potentially be loaded later without output formatting
				$value = $fieldtype->getBlankValue($page, $field);
				return $this->formatFieldValue($page, $field, $value);
			}
		}

		if($value !== null && empty($selector)) {
			// if the non-filtered value is already loaded, return it 
			return $this->formatFieldValue($page, $field, $value);
		}

		$track = $page->trackChanges();
		$page->setTrackChanges(false);

		if(!$fieldtype) return null;

		if($selector && !Selectors::stringHasSelector($selector)) {
			// if selector argument provided, but isn't valid, we assume it 
			// to instead be an argument for the value's __invoke() method
			$invokeArgument = $selector;
			$selector = '';
		}

		if($selector) {
			$value = $fieldtype->loadPageFieldFilter($page, $field, $selector);
		} else {
			$value = $fieldtype->_callHookMethod('loadPageField', array($page, $field));
		}

		if($value === null) {
			$value = $fieldtype->getDefaultValue($page, $field);
		} else {
			$value = $fieldtype->_callHookMethod('wakeupValue', array($page, $field, $value));
		}

		// turn off output formatting and set the field value, which may apply additional changes
		$of = $page->of();
		if($of) $page->of(false);
		$this->setFieldValue($page, $key, $value, false);
		if($of) $page->of(true);
		$value = $page->_parentGet($key);

		// prevent storage of value if it was filtered when loaded
		if(!empty($selector)) $page->__unset($key);

		if($value instanceof Wire && !$value instanceof Page) $value->resetTrackChanges(true);
		if($track) $page->setTrackChanges(true);

		$value = $this->formatFieldValue($page, $field, $value);

		if($invokeArgument && is_object($value) && method_exists($value, '__invoke')) {
			$value = $value->__invoke($invokeArgument);
		}

		return $value;
	}

	/**
	 * Return a value consistent with the page’s output formatting state
	 *
	 * This is primarily for use as a helper to the getFieldValue() method.
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param mixed $value
	 * @return mixed
	 *
	 */
	public function formatFieldValue(Page $page, Field $field, $value) {

		$hasInterface = $value instanceof PageFieldValueInterface;

		if($hasInterface) {
			$value->setPage($page);
			$value->setField($field);
		}

		if($page->of()) {
			// output formatting is enabled so return a formatted value
			//$value = $field->type->formatValue($this, $field, $value);
			$value = $field->type->_callHookMethod('formatValue', array($page, $field, $value));
			// check again for interface since value may now be different
			if($hasInterface) $hasInterface = $value instanceof PageFieldValueInterface;
			if($hasInterface) $value->formatted(true);
		} else if($hasInterface && $value->formatted()) {
			// unformatted requested, and value is already formatted so load a fresh copy
			$page->__unset($field->name);
			$value = $this->getFieldValue($page, $field->name);
		}

		return $value;
	}

	/**
	 * Set the value of a field that is defined in the page's Fieldgroup
	 *
	 * This may not be called when outputFormatting is on.
	 *
	 * This is for internal use. API should generally use the set() method, but this is kept public for the minority of instances where it's useful.
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param string $key
	 * @param mixed $value
	 * @param bool $load Should the existing value be loaded for change comparisons? (applicable only to non-autoload fields)
	 * @return Page Returns reference to this Page
	 * @throws WireException
	 *
	 */
	public function setFieldValue(Page $page, $key, $value, $load = true) {

		if(!$page->template()) {
			$config = $page->wire()->config;
			$name = strpos($key, '__') ? substr($key, 0, strpos($key, '__')) : $key;
			$error = "You must assign a template to page $page before setting '$name' field.";
			if($config->debug) {
				// allow page to proceed in debug mode so that it's possible to delete it if needed
				$page->error($error);
				$page->template($page->wire()->pages->get($config->http404PageID)->template);
			} else {
				throw new WireException($error);
			}
		}
		
		$isLoaded = $page->isLoaded();

		// if the page is not yet loaded and a '__' field was set, then we queue it so that the loaded() method can 
		// instantiate all those fields knowing that all parts of them are present for wakeup. 
		if(!$isLoaded && strpos($key, '__')) {
			list($key, $subKey) = explode('__', $key, 2);
			$fieldData = $page->fieldDataQueue($key);
			if($fieldData === null) $fieldData = array();
			$fieldData[$subKey] = $value;
			$page->fieldDataQueue($key, $fieldData);
			return $page;
		}

		// check if the given key resolves to a Field or not
		$field = $page->getField($key);
		if(!$field) {
			// not a known/saveable field, let them use it for runtime storage
			$valPrevious = $page->_parentGet($key);
			if($valPrevious !== null && $page->_parentGet("-$key") === null && $valPrevious !== $value) {
				// store previous value (if set) in a "-$key" version
				$page->setQuietly("-$key", $valPrevious);
			}
			$page->_parentSet($key, $value);
			return $page;
		}
	
		/** @var Fieldtype $fieldtype */
		$fieldtype = $field->type;

		// if a null value is set, then ensure the proper blank type is set to the field
		if($value === null) {
			$page->_parentSet($key, $fieldtype->getBlankValue($page, $field));
			return $page;
		}

		// if the page is currently loading from the database, we assume that any set values are 'raw' and need to be woken up
		if(!$page->isLoaded()) {
			// queue for wakeup and sanitize on first field access
			$page->wakeupNameQueue($key, true);
			// page is currently loading, so we don't need to continue any further
			$page->_parentSet($key, $value);
			return $page;
		}

		// check if the field hasn't been already loaded
		if($page->_parentGet($key) === null) {
			// this field is not currently loaded. if the $load param is true, then ...
			// retrieve old value first in case it's not autojoined so that change comparisons and save's work 
			if($load) $page->get($key);
		} else if($page->wakeupNameQueue($key)) {
			// autoload value: we don't yet have a "woke" value suitable for change detection, so let it wakeup
			if($page->trackChanges() && $load) {
				// if changes are being tracked, load existing value for comparison
				$this->getFieldValue($page, $key);
			} else {
				// if changes aren't being tracked, the existing value can be discarded
				$page->wakeupNameQueue($key, false);
			}

		} else {
			// check if the field is corrupted
			$isCorrupted = false;
			if($value instanceof PageFieldValueInterface) {
				// value indicates it is already formatted, so would corrupt the page for saving
				if($value->formatted()) $isCorrupted = true;
			} else if($page->of()) {
				// check if value is modified by being formatted
				$result = $fieldtype->_callHookMethod('formatValue', array($page, $field, $value));
				if($result != $value) $isCorrupted = true;
			}
			if($isCorrupted) {
				// The field has been loaded or dereferenced from the API, and this field changes when formatters are applied to it. 
				// There is a good chance they are trying to set a formatted value, and we don't allow this situation because the 
				// possibility of data corruption is high. We set the Page::statusCorrupted status so that Pages::save() can abort.
				$page->set('status', $page->status | Page::statusCorrupted);
				$corruptedFields = $page->get('_statusCorruptedFields');
				if(!is_array($corruptedFields)) $corruptedFields = array();
				$corruptedFields[$field->name] = $field->name;
				$page->set('_statusCorruptedFields', $corruptedFields);
			}
		}

		// isLoaded so sanitizeValue can determine if it can perform a typecast rather than a full sanitization (when helpful)
		// we don't use setIsLoaded() so as to avoid triggering any other functions
		$isLoaded = $page->isLoaded();
		if(!$load) $page->setIsLoaded(false, true); // true=set quietly
		// ensure that the value is in a safe format and set it 
		$value = $fieldtype->sanitizeValue($page, $field, $value);
		// Silently restore isLoaded state
		if(!$load) $page->setIsLoaded($isLoaded, true);

		$page->_parentSet($key, $value);
		
		return $page;
	}

}
