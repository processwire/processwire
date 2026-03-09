<?php namespace ProcessWire;

/**
 * Class MarkupFieldtype
 * 
 * Provides pre-packaged markup rendering for Fieldtypes
 * and potentially serves as a module type. This base class
 * just provides generic rendering for various differnet types,
 * accommodating just about any Fieldtype. But it is built to be
 * extended for more specific needs in various Fieldtypes. 
 * 
 * USAGE:
 * 
 * $m = new MarkupFieldtype($page, $field, $value);
 * echo $m->render();
 * 
 * // Alternate usage:
 * $m = new MarkupFieldtype();
 * $m->setPage($page);
 * $m->setField($field);
 * $m->setValue($value); 
 * echo $m->render();
 *
 * // Render just a specific property: 
 * echo $m->render('property'); 
 * 
 */

class MarkupFieldtype extends WireData implements Module {

	/**
	 * @var Page|null
	 * 
	 */
	protected $_page = null;

	/**
	 * @var Field|null
	 * 
	 */
	protected $_field = null;

	/**
	 * Formatted value that will be used for rendering
	 * 
	 * If not set, it will be pulled from $page->getFormatted($field->name) automatically. 
	 * 
	 * @var mixed
	 * 
	 */
	protected $_value = null;

	/**
	 * True when we are unable to render and should delegate to Inputfield::renderValue instead
	 * 
	 * @var bool
	 * 
	 */
	protected $renderIsUseless = false;

	/**
	 * Properties that are potentially linkable to source page in markup
	 * 
	 * @var array
	 * 
	 */
	protected $linkableProperties = array(
		'name', 'url', 'httpUrl', 'path', 'title',
	);

	/**
	 * Construct the MarkupFieldtype
	 * 
	 * If you construct without providing page and field, please populate them
	 * separately with the setPage and setField methods before calling render().
	 * 
	 * @param Page|null $page
	 * @param Field|null $field
	 * @param mixed $value
	 * 
	 */
	public function __construct(?Page $page = null, ?Field $field = null, $value = null) {
		parent::__construct();
		if($page) $this->setPage($page);
		if($field) $this->setField($field); 
		if(!is_null($value)) $this->setValue($value); 
	}
	
	/**
	 * Render markup for the field or for the property from field
	 * 
	 * @param string $property Optional property (for object or array field values)
	 * @return string
	 * 
	 */
	public function render($property = '') {
	
		$value = $this->getValue(); 
		
		if($property) {
			// render specific property requested
			
			if($property == 'count' && WireArray::iterable($value)) {
				return count($value); 
			}
			
			if(is_array($value) && isset($value[$property])) {
				// array 
				$value = $value[$property];

			} else if(is_object($value)) { 
				// object
				$valid = false;
				if($value instanceof PageArray) {
					// PageArray object: get array of property value from each item
					$field = $this->wire()->fields->get($property);
					if(is_object($field) && $field->type) {
						$a = array();
						foreach($value as $page) {
							$v = $page->getFormatted($property);
							$v = $field->type->markupValue($page, $field, $v);
							if($this->isLinkablePageProperty($page, $property)) {
								$a[] = "<a href='$page->url'>$v</a>";
							} else {
								$a[] = $v;
							}
						}
						return $this->arrayToString($a, false);
					} else {
						$getMethod = strpos($property, '}') ? 'getText' : 'getFormatted';
						$value = $value->explode($property, array('getMethod' => $getMethod));
					}
					$valid = true;
					
				} else if($value instanceof WireArray) {
					// WireArray object: get array of property value from each item
					$value = $value->explode($property);
					$valid = true;

				} else if($value instanceof Page) {
					// Page object
					$page = $value;
					$value = $page->getFormatted($property);
					$field = $this->wire()->fields->get($property);
					if(is_object($field) && $field->type) return $field->type->markupValue($page, $field, $value);
					$valid = true;
				} else if($value instanceof LanguagesValueInterface) {
					/** @var LanguagesValueInterface $value */
					/** @var Languages $languages */
					$languages = $this->wire()->languages;
					if($property === 'data') {
						$languageID = $languages->getDefault()->id;	
					} else if(is_string($property) && preg_match('/^data(\d+)$/', $property, $matches)) {
						$languageID = (int) $matches[1];
					} else {
						$languageID = 0;
					}
					$value = $languageID ? $value->getLanguageValue($languageID) : (string) $value; 
					
				} else if($value instanceof WireData) {
					// WireData object
					$value = $value->get($property);
					$valid = true;
					
				} else if($value instanceof Wire) {
					// Wire object
					$value = $value->$property;
					$valid = true; 
				}
			
				// make sure the property returned is a safe one
				if(!is_null($value) && parent::get($property) !== null) {
					// this is an API variable or something that we don't want to allow
					$this->warning("Disallowed property: $property", Notice::debug); 
					$value = null;
				}
				
				if($valid) $property = ''; // already retrieved
				
			} else {
				// something we don't know how to retrieve a property from
				$value = null;
			}
			
			$out = $this->renderProperty($property, $value); 
			
		} else {
			// render entire value requested
			$out = $this->renderValue($value); 
		}
		
		if($this->renderIsUseless && $field = $this->getField()) {
			// if we detected that we're rendering something useless (like a list of class names)
			// then attempt to delegate to Inputfield::renderValue() instead. 
			$in = $field->getInputfield($this->getPage()); 
			if($in) $out = $in->renderValue();
		}
		
		return $out; 
	}

	/**
	 * Render the entire $page->get($field->name) value. 
	 * 
	 * Classes descending from MarkupFieldtype this would implement their own method. 
	 * 
	 * @param mixed $value The unformatted value to render. 
	 * @return string
	 * 
	 */
	protected function renderValue($value) {
		return $this->valueToString($value); 
	}
	
	/**
	 * Render the just a property from the $page->get($field->name) value.
	 *
	 * Applicable only if the value of the field is an array or object.
	 * 
	 * Classes descending from MarkupFieldtype would implement their own method.
	 *
	 * @param string $property The property name being rendered.
	 * @param mixed $value The value of the property.
	 * @return string
	 *
	 */
	protected function renderProperty($property, $value) {
		
		if(empty($property)) return $this->valueToString($value);
		
		if(is_object($value)) {
			
			if($value instanceof Page) {
				$value = $value->getFormatted($property);

			} else if(WireArray::iterable($value)) {
				$values = array();
				foreach($value as $v) {
					$v = $this->renderProperty($property, $v);
					if(strlen($v)) $values[] = $v;
				}
				$value = count($values) ? $this->arrayToString($values) : '';
				
			} else {
				$value = $value->$property;
			}	
			
		} else if(is_array($value)) {
			if(isset($value[$property])) {
				$value = $value[$property];
			}
			
		} else {
			// unexpected value (not array or object)
		}
		
		return $this->valueToString($value); 
	}

	/**
	 * Convert any value to a string
	 * 
	 * @param mixed $value
	 * @param bool $encode
	 * @return string
	 * 
	 */	
	protected function valueToString($value, $encode = true) {
		$isObject = is_object($value);
		if($isObject && ($value instanceof Pagefiles || $value instanceof Pagefile)) {
			return $this->objectToString($value);
		} else if($isObject && wireInstanceOf($value, 'RepeaterPageArray')) {
			return $this->objectToString($value);
		} else if(WireArray::iterable($value)) {
			return $this->arrayToString($value);
		} else if($isObject) {
			return $this->objectToString($value);
		} else {
			return $encode ? $this->wire()->sanitizer->entities1($value) : $value;
		}
	}

	/**
	 * Render an unknown array or WireArray to a string
	 * 
	 * @param array|WireArray $value
	 * @param bool $encode
	 * @return string
	 * 
	 */
	protected function arrayToString($value, $encode = true) {
		if(!count($value)) return '';
		$out = "<ul class='MarkupFieldtype'>";
		foreach($value as $v) {
			$out .= "<li>" . $this->valueToString($v, $encode) . "</li>";
		}
		$out .= "</ul>";
		return $out; 
	}

	/**
	 * Render an object to a string
	 * 
	 * @param Wire|object $value
	 * @return string
	 * 
	 */
	protected function objectToString($value) {
		if($value instanceof WireArray) {
			if(!$value->count()) return '';
			if(wireInstanceOf($value, 'RepeaterPageArray')) {
				return $this->renderInputfieldValue($value);
			}
		}
		if($value instanceof Page) {
			if(wireInstanceOf($value, 'FieldsetPage')) {
				return $this->renderInputfieldValue($value);
			} else if($value->viewable()) {
				return "<a href='$value->url'>" . $value->get('title|name') . "</a>";
			} else {
				return $value->get('title|name');
			}
		}
		if($value instanceof Pagefiles || $value instanceof Pagefile) {
			$out = $this->renderInputfieldValue($value);
		} else {
			$out = (string) $value;
			if($out === wireClassName($value, false) || $out === wireClassName($value, true)) {
				// just the class name probably isn't useful here, see if we can do do something else with it
				$this->renderIsUseless = true;
			}
		}
		return $out; 
	}

	/**
	 * Render a value using an Inputfield's renderValue() method
	 * 
	 * @param $value
	 * @return string
	 * 
	 */
	protected function renderInputfieldValue($value) {
		$field = $this->getField();
		$page = $this->getPage();
		if(!$page->id || !$field) return (string) $value;
		/** @var Inputfield $inputfield */
		$inputfield = $field->getInputfield($page);	
		if(!$inputfield) return (string) $value;
		$inputfield->columnWidth = 100;
		$inputfield->attr('value', $value);
		if(method_exists($inputfield, 'setField')) $inputfield->setField($field);
		if(method_exists($inputfield, 'setPage')) $inputfield->setPage($page);
		if($inputfield->renderValueFlags & Inputfield::renderValueNoWrap) {
			$inputfield->renderReady(null, true);
			$out = $inputfield->renderValue();
			$inputfield->addClass('InputfieldRenderValueMode', 'wrapClass');
			if($inputfield->renderValueFlags & Inputfield::renderValueMinimal) {
				$inputfield->addClass('InputfieldRenderValueMinimal', 'wrapClass');
			}
			if($inputfield->renderValueFlags & Inputfield::renderValueFirst) {
				$inputfield->addClass('InputfieldRenderValueFirst', 'wrapClass');
			}
			$out = "<div class='$inputfield->wrapClass'>$out</div>";
		} else {
			/** @var InputfieldWrapper $wrapper */
			$wrapper = $this->wire(new InputfieldWrapper());
			$wrapper->quietMode = true;
			$wrapper->add($inputfield);
			$out = $wrapper->renderValue();
		}
		return $out; 	
	}

	/**
	 * Is the given page property/field name one that should be linked to the source page in output?
	 * 
	 * @param Page $page
	 * @param $property
	 * @return bool
	 * 
	 */
	protected function isLinkablePageProperty(Page $page, $property) {
		if(!in_array($property, $this->linkableProperties)) return false;
		if(!$page->viewable($property)) return false;
		return true;
	}

	/**
	 * The string value of a MarkupFieldtype is always the fully rendered field
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return (string) $this->render();
	}
	
	public function setPage(Page $page) { $this->_page = $page;  }
	public function setField(Field $field) { $this->_field = $field;  }
	public function getPage() { return $this->_page ? $this->_page : $this->wire()->pages->newNullPage(); }
	public function getField() { return $this->_field; }

	/**
	 * Set the value
	 * 
	 * It is not necessary to call this as the value will be determined automatically from $page and $field.
	 * If you set this, it should be a formatted value. 
	 * 
	 * @param $value
	 * 
	 */	
	public function setValue($value) { 
		$this->_value = $value; 
	}

	/**
	 * Get the value
	 * 
	 * @return mixed
	 * 
	 */
	public function getValue() {
		
		$value = $this->_value;
		
		if(is_null($this->_value)) {
			$page = $this->getPage();
			$fieldName = $this->getField()->name;
			$value = $page->getFormatted($fieldName);
			$this->_value = $value; 
		}
		
		return $value;
	}

	public function get($key) {
		if($key == 'page') return $this->getPage();
		if($key == 'field') return $this->getField();
		if($key == 'value') return $this->getValue();
		return parent::get($key); 
	}

}
