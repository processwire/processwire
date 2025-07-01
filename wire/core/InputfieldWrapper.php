<?php namespace ProcessWire;

/**
 * ProcessWire InputfieldWrapper
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 * About InputfieldWrapper
 * =======================
 * A type of Inputfield that is designed specifically to wrap other Inputfields.
 * The most common example of an InputfieldWrapper is a <form>.
 * 
 * #pw-summary A type of Inputfield that contains other Inputfield objects as children. Commonly a form or a fieldset.  
 *
 * InputfieldWrapper is not designed to render an Inputfield specifically, but you can set a value attribute
 * containing content that will be rendered before the wrapper.
 * 
 * #pw-summary-properties Access any common Inputfield type class name from an InputfieldWrapper and it will return a new instance of that Inputfield, i.e. `$f = $inputfields->InputfieldText;` Below are several examples.
 *
 * @property bool $renderValueMode True when only rendering values, i.e. no inputs (default=false). #pw-internal
 * @property bool $quietMode True to suppress label, description and notes, often combined with renderValueMode (default=false). #pw-internal
 * @property int $columnWidthSpacing Percentage spacing between columns or 0 for none. Default pulled from `$config->inputfieldColumnWidthSpacing`. #pw-internal
 * @property bool $useDependencies Whether or not to consider `showIf` and `requiredIf` dependencies during processing (default=true). #pw-internal
 * @property bool|null $InputfieldWrapper_isPreRendered Whether or not children have been pre-rendered (internal use only) #pw-internal
 * @property InputfieldsArray|null $children Inputfield instances that are direct children of this InputfieldWrapper.  #pw-group-properties
 * 
 * @method string renderInputfield(Inputfield $inputfield, $renderValueMode = false) #pw-group-output
 * @method Inputfield new($typeName, $name = '', $label = '', array $settings = []) #pw-group-manipulation
 * @method bool allowProcessInput(Inputfield $inputfield) Allow Inputfield to have input processed? (3.0.207+) #pw-internal
 * 
 * @property InputfieldAsmSelect $InputfieldAsmSelect Create new asmSelect Inputfield #pw-group-properties
 * @property InputfieldButton $InputfieldButton Create new button Inputfield #pw-group-properties
 * @property InputfieldCheckbox $InputfieldCheckbox Create new checkbox Inputfield #pw-group-properties
 * @property InputfieldCheckboxes $InputfieldCheckboxes Create new checkboxes Inputfield #pw-group-properties
 * @property InputfieldCKEditor $InputfieldCKEditor Create new CKEditor Inputfield #pw-group-properties
 * @property InputfieldCommentsAdmin $InputfieldCommentsAdmin #pw-internal
 * @property InputfieldDatetime $InputfieldDatetime Create new date/time Inputfield #pw-group-properties
 * @property InputfieldEmail $InputfieldEmail Create new email Inputfield #pw-group-properties
 * @property InputfieldFieldset $InputfieldFieldset Create new Fieldset InputfieldWrapper #pw-group-properties
 * @property InputfieldFieldsetClose $InputfieldlFieldsetClose #pw-internal
 * @property InputfieldFieldsetOpen $InputfieldFieldsetOpen #pw-internal
 * @property InputfieldFieldsetTabOpen $InputfieldFieldsetTabOpen #pw-internal
 * @property InputfieldFile $InputfieldFile Create new file Inputfield #pw-group-properties
 * @property InputfieldFloat $InputfieldFloat Create new float Inputfield #pw-group-properties
 * @property InputfieldForm $InputfieldForm Create new form InputfieldWrapper #pw-group-properties
 * @property InputfieldHidden $InputfieldHidden Create new hidden Inputfield #pw-group-properties
 * @property InputfieldIcon $InputfieldIcon Create new icon Inputfield #pw-group-properties
 * @property InputfieldImage $InputfieldImage Create new image Inputfield #pw-group-properties
 * @property InputfieldInteger $InputfieldInteger Create new integer Inputfield #pw-group-properties
 * @property InputfieldMarkup $InputfieldMarkup Create new markup Inputfield #pw-group-properties
 * @property InputfieldName $InputfieldName #pw-internal
 * @property InputfieldPage $InputfieldPage Create new Page selection Inputfield #pw-group-properties
 * @property InputfieldPageAutocomplete $InputfieldPageAutocomplete Create new Page selection autocomplete Inputfield #pw-group-properties
 * @property InputfieldPageListSelect $InputfieldPageListSelect Create new PageListSelect Inputfield #pw-group-properties
 * @property InputfieldPageListSelectMultiple $InputfieldPageListSelectMultiple Create new multiple PageListSelect Inputfield #pw-group-properties
 * @property InputfieldPageName $InputfieldPageName #pw-internal
 * @property InputfieldPageTable $InputfieldPageTable #pw-internal
 * @property InputfieldPageTitle $InputfieldPageTitle #pw-internal
 * @property InputfieldPassword $InputfieldPassword #pw-internal
 * @property InputfieldRadios $InputfieldRadios Create new radio buttons Inputfield #pw-group-properties
 * @property InputfieldRepeater $InputfieldRepeater #pw-internal
 * @property InputfieldSelect $InputfieldSelect Create new <select> Inputfield #pw-group-properties
 * @property InputfieldSelectMultiple $InputfieldSelectMultiple Create new <select multiple> Inputfield #pw-group-properties
 * @property InputfieldSelector $InputfieldSelector #pw-internal
 * @property InputfieldSubmit $InputfieldSubmit Create new submit button Inputfield #pw-group-properties
 * @property InputfieldText $InputfieldText Create new single-line text Inputfield #pw-group-properties
 * @property InputfieldTextarea $InputfieldTextarea Create new multi-line <textarea> Inputfield #pw-group-properties
 * @property InputfieldTextTags $InputfieldTextTags Create new text tags Inputfield #pw-group-properties
 * @property InputfieldToggle $InputfieldToggle Create new toggle Inputfield #pw-group-properties
 * @property InputfieldURL $InputfieldURL Create new URL Inputfield #pw-group-properties
 * @property InputfieldWrapper $InputfieldWrapper Create new generic InputfieldWrapper #pw-group-properties
 *
 */

class InputfieldWrapper extends Inputfield implements \Countable, \IteratorAggregate {

	/**
	 * Set to true for debugging optimization of property accesses
	 * 
	 * #pw-internal
	 * 
	 */
	const debugPropertyAccess = false;
	
	/**
	 * Markup used during the render() method - customize with InputfieldWrapper::setMarkup($array)
	 *
	 */
	static protected $defaultMarkup = array(
		'list' => "<ul {attrs}>{out}</ul>",
		'item' => "<li {attrs}>{out}</li>", 
		'item_label' => "<label class='InputfieldHeader ui-widget-header{class}' for='{for}'>{out}</label>",
		'item_label_hidden' => "<label class='InputfieldHeader InputfieldHeaderHidden ui-widget-header{class}' for='{for}'><span>{out}</span></label>",
		'item_content' => "<div class='InputfieldContent ui-widget-content{class}'>{out}</div>", 
		'item_error' => "<p class='InputfieldError ui-state-error'><i class='fa fa-fw fa-flash'></i><span>{out}</span></p>",
		'item_description' => "<p class='description'>{out}</p>", 
		'item_head' => "<h2>{out}</h2>", 
		'item_notes' => "<p class='notes'>{out}</p>",
		'item_detail' => "<p class='detail'>{out}</p>", 
		'item_icon' => "<i class='fa fa-fw fa-{name}'></i> ",
		'item_toggle' => "<i class='toggle-icon fa fa-fw fa-angle-down' data-to='fa-angle-down fa-angle-right'></i>", 
		// ALSO: 
		// InputfieldAnything => array(any of the properties above to override on a per-Inputfield basis)
	);

	static protected $markup = array();

	/**
	 * Classes used during the render() method - customize with InputfieldWrapper::setClasses($array)
	 *
	 */
	static protected $defaultClasses = array(
		'form' => '', // additional clases for InputfieldForm (optional)
		'list' => 'Inputfields',
		'list_clearfix' => 'ui-helper-clearfix', 
		'item' => 'Inputfield {class} Inputfield_{name} ui-widget',
		'item_label' => '', // additional classes for InputfieldHeader (optional)
		'item_content' => '',  // additional classes for InputfieldContent (optional)
		'item_required' => 'InputfieldStateRequired', // class is for Inputfield
		'item_error' => 'ui-state-error InputfieldStateError', // note: not the same as markup[item_error], class is for Inputfield
		'item_collapsed' => 'InputfieldStateCollapsed',
		'item_column_width' => 'InputfieldColumnWidth',
		'item_column_width_first' => 'InputfieldColumnWidthFirst',
		'item_show_if' => 'InputfieldStateShowIf',
		'item_required_if' => 'InputfieldStateRequiredIf'
		// ALSO: 
		// InputfieldAnything => array(any of the properties above to override on a per-Inputfield basis)
	);

	static protected $classes = array();

	/**
	 * Instance of InputfieldsArray, if this Inputfield contains child Inputfields
	 *
	 * @var InputfieldsArray
	 * 
	 */
	protected $children;

	/**
	 * Array of Inputfields that had their processing delayed by dependencies. 
	 *
	 */
	protected $delayedChildren = array();

	/**
	 * Label displayed when a value is required but missing
	 *
	 */
	protected $requiredLabel = 'Missing required value';

	/**
	 * Whether or not column width is handled internally
	 * 
	 * @var bool
	 * 
	 */
	protected $useColumnWidth = true;

	/**
	 * Construct the Inputfield, setting defaults for all properties
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->children = new InputfieldsArray();
		$this->set('skipLabel', Inputfield::skipLabelFor); 
		$this->set('useDependencies', true); // whether or not to use consider field dependencies during processing
		$this->set('renderValueMode', false); 
		$this->set('quietMode', false); // suppress label, description and notes
		$this->set('columnWidthSpacing', 0);
	}

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		
		$config = $this->wire()->config;
		
		$this->wire($this->children);
		$this->requiredLabel = $this->_('Missing required value');
		
		$columnWidthSpacing = $config->inputfieldColumnWidthSpacing;
		$columnWidthSpacing = is_null($columnWidthSpacing) ? 1 : (int) $columnWidthSpacing;
		if($columnWidthSpacing > 0) $this->set('columnWidthSpacing', $columnWidthSpacing);
	
		$settings = $config->InputfieldWrapper;
		
		if(is_array($settings)) {
			foreach($settings as $key => $value) {
				if($key == 'requiredLabel') {
					$this->requiredLabel = $value;
				} else if($key == 'useColumnWidth') {
					$this->useColumnWidth = $value;
				} else {
					$this->set($key, $value);
				}
			}
		}
		
		parent::wired();
	}

	/**
	 * Get a child Inputfield having a name attribute matching the given $key.
	 * 
	 * This method can also get settings, attributes or API variables, so long as they don't
	 * collide with an Inputfield name. For that reason, you may prefer to use the `Inputfield::getSetting()`,
	 * `Inputfield::attr()` or `Wire::wire()` methods for those other purposes. 
	 * 
	 * If you want a method that can only return a matching Inputfield object, use the 
	 * `InputfieldWrapper::getChildByName()` method .
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string $key Name of Inputfield or setting/property to retrieve. 
	 * @return Inputfield|mixed 
	 * @see InputfieldWrapper::getChildByName()
	 * @throws WireException Only in core development/debugging, otherwise does not throw exceptions.
	 *
	 */
	public function get($key) {
		$inputfield = $this->getChildByName($key);
		if($inputfield) return $inputfield;
		if(self::debugPropertyAccess) throw new WireException("Access of attribute or setting: $key");
		$value = $this->wire($key);
		if($value) return $value; 
		if($key === 'children') return $this->children(); 
		if(($value = parent::get($key)) !== null) return $value; 
		return null;
	}

	/**
	 * Provides direct reference to attributes and settings, and falls back to Inputfield children
	 * 
	 * This is different behavior from the get() method. 
	 *
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function __get($key) {
		if($key === 'children') return $this->children();
		if(strpos($key, 'Inputfield') === 0 && strlen($key) > 10) {
			if($key === 'InputfieldWrapper') return $this->wire(new InputfieldWrapper()); 
			$value = $this->wire()->modules->get($key);
			if($value instanceof Inputfield) return $value;
			if(wireClassExists($key)) return $this->wire(new $key()); 
			$value = null;
		}
		$value = parent::get($key); 
		if(is_null($value)) $value = $this->getChildByName($key);
		return $value; 
	}

	/**
	 * Add an Inputfield item as a child (also accepts array definition)
	 * 
	 * Since 3.0.110: If given a string value, it is assumed to be an Inputfield type that you 
	 * want to add. In that case, it will create the Inputfield and return it instead of $this. 
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Inputfield|array|string $item
	 * @return Inputfield|InputfieldWrapper|$this
	 * @see InputfieldWrapper::import()
	 *
	 */
	public function add($item) {
		if(is_string($item)) {
			return $this->___new($item);
		} else if(is_array($item)) {
			$this->importArray($item); 
		} else {
			$this->children()->add($item); 
			$item->setParent($this); 
		}
		return $this; 
	}

	/**
	 * Create a new Inputfield, add it to this InputfieldWrapper, and return the new Inputfield
	 * 
	 * - Only the $typeName argument is required. 
	 * - You may optionally substitute the $settings argument for the $name or $label arguments.
	 * - You may optionally substitute Inputfield “description” property for $settings argument.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $typeName Inputfield type, i.e. “InputfieldCheckbox” or just “checkbox” for short. 
	 * @param string|array $name Name of input (or substitute $settings here). 
	 * @param string|array $label Label for input (or substitute $settings here).
	 * @param array|string $settings Settings to add to Inputfield (optional). Or if string, assumed to be “description”.
	 * @return Inputfield|InputfieldSelect|InputfieldWrapper An Inputfield instance ready to populate with additional properties/attributes.
	 * @throws WireException If you request an unknown Inputfield type
	 * @since 3.0.110
	 * 
	 */
	public function ___new($typeName, $name = '', $label = '', $settings = array()) {
		
		if(is_array($name)) {
			$settings = $name;
			$name = '';
		} else if(is_array($label)) {
			$settings = $label;
			$label = '';
		} 
		
		if(strpos($typeName, 'Inputfield') !== 0) {
			$typeName = "Inputfield" . ucfirst($typeName);
		}
	
		/** @var Inputfield|InputfieldSelect|InputfieldWrapper $inputfield */
		$inputfield = $this->wire('modules')->getModule($typeName);
		
		if(!$inputfield && wireClassExists($typeName)) {
			$inputfield = $this->wire(new $typeName());
		}
		
		if(!$inputfield instanceof Inputfield) {
			throw new WireException("Unknown Inputfield type: $typeName");
		}
		
		if(strlen($name)) $inputfield->attr('name', $name);
		if(strlen($label)) $inputfield->label = $label;
	
		if(is_array($settings)) {
			foreach($settings as $key => $value) {
				$inputfield->set($key, $value);
			}
		} else if(is_string($settings)) {
			$inputfield->description = $settings;
		}
		
		$this->add($inputfield);
		
		return $inputfield;
	}

	/**
	 * Import the given Inputfield items as children
	 * 
	 * If given an `InputfieldWrapper`, it will import the children of it and
	 * exclude the wrapper itself. This is different from `InputfieldWrapper::add()` 
	 * in that add() would add the wrapper, not just the children. See also 
	 * the `InputfieldWrapper::importArray()` method. 
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param InputfieldWrapper|array|InputfieldsArray $items Wrapper containing items to add
	 * @return $this
	 * @throws WireException
	 * @see InputfieldWrapper::add(), InputfieldWrapper::importArray()
	 * 
	 */
	public function import($items) {
		if($items instanceof InputfieldWrapper || $items instanceof InputfieldsArray) {
			foreach($items as $item) {
				$this->add($item);
			}
		} else if(is_array($items)) {
			$this->importArray($items);
		} else if($items instanceof Inputfield) {
			$this->add($items);
		} else {
			throw new WireException("InputfieldWrapper::import() requires InputfieldWrapper, InputfieldsArray, array, or Inputfield");
		}
		return $this;
	}

	/**
	 * Prepend an Inputfield to this instance’s children.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield $item Item to prepend
	 * @return $this
	 *
	 */
	public function prepend(Inputfield $item) {
		$item->setParent($this); 
		$this->children()->prepend($item); 
		return $this; 
	}

	/**
	 * Append an Inputfield to this instance’s children.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield $item Item to append
	 * @return $this
	 *
	 */
	public function append(Inputfield $item) {
		$item->setParent($this); 
		$this->children()->append($item); 
		return $this; 
	}

	/**
	 * Insert new or existing Inputfield before or after another
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield|array|string $item New or existing item Inputfield, name, or new item array to insert.
	 * @param Inputfield|string $existingItem Existing item or item name you want to insert before.
	 * @param bool $before True to insert before, false to insert after (default=false).
	 * @return $this
	 * @throws WireException
	 * @since 3.0.196
	 * 
	 */
	public function insert($item, $existingItem, $before = false) {
		
		$children = $this->children();
		
		if($existingItem instanceof Inputfield) {
			// ok
		} else if(is_string($existingItem)) {
			$name = $existingItem;
			$existingItem = $this->getByName($name);
			if(!$existingItem) throw new WireException("Cannot find Inputfield[name=$name] to insert");
		} else {
			throw new WireException('Invalid value for $existingItem argument'); 
		}
		
		if(is_array($item)) {
			// new item definition
			if(isset($item['name'])) {
				// first check if there's an existing item with this name
				$f = $this->getByName($item['name']); 
				if($f) return $this->insert($f, $existingItem, $before);
			}
			$nBefore = $children->count();
			$this->add($item);
			$nAfter = $children->count();
			if($nAfter > $nBefore) {
				// new item was added by the above $this->add() call
				$item = $children->last();
				$children->remove($item);
			} else {
				throw new WireException('Unable to insert new item: ' . print_r($item, true));
			}
		} else if(!$item instanceof Inputfield) {
			// get item to insert by name
			$name = (string) $item;
			$item = $this->getByName($name);
			if(!$item) {
				// if named item isn't found, create one 
				$item = $this->___new('text', $name, $name); 
			}
		}
		
		if($children->has($existingItem)) {
			// existing item is a direct child of this InputfieldWrapper
			$item->setParent($this);
			$method = $before ? 'insertBefore' : 'insertAfter';
			$children->$method($item, $existingItem);
		} else {
			// find existing item recursively
			$f = $this->getByName($existingItem->attr('name')); 
			if($f && $f->parent) {
				// existing item was found
				$existingItem = $f;
			} else {
				// existing item not found, add it as direct child
				$this->add($existingItem);
			}
			$existingItem->parent->insert($item, $existingItem, $before);
		}
		
		return $this; 
	}

	/**
	 * Insert one Inputfield before one that’s already there.
	 * 
	 * Note: string or array values for either argument require 3.0.196+. 
	 * 
	 * ~~~~~
	 * // example 1: Get existing Inputfields and insert first_name before last_name
	 * $firstName = $form->getByName('first_name');
	 * $lastName = $form->getByName('last_name'); 
	 * $form->insertBefore($firstName, $lastName); 
	 * 
	 * // example 2: Same as above but use Inputfield names (3.0.196+)
	 * $form->insertBefore('first_name', 'last_name'); 
	 * 
	 * // example 3: Create new Inputfield and insert before last_name (3.0.196+)
	 * $form->insertBefore([ 'type' => 'text', 'name' => 'first_name' ], 'last_name'); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield|array|string $item Item to insert 
	 * @param Inputfield|string $existingItem Existing item you want to insert before.
	 * @return $this
	 *
	 */
	public function insertBefore($item, $existingItem) {
		return $this->insert($item, $existingItem, true);
	}

	/**
	 * Insert one Inputfield after one that’s already there.
	 * 
	 * Note: string or array values for either argument require 3.0.196+.
	 * 
	 * ~~~~~
	 * // example 1: Get existing Inputfields, insert last_name after first_name
	 * $lastName = $form->getByName('last_name');
	 * $firstName = $form->getByName('first_name');
	 * $form->insertAfter($lastName, $firstName);
	 *
	 * // example 2: Same as above but use Inputfield names (3.0.196+)
	 * $form->insertBefore('last_name', 'first_name');
	 *
	 * // example 3: Create new Inputfield and insert after first_name (3.0.196+)
	 * $form->insertAfter([ 'type' => 'text', 'name' => 'last_name' ], 'first_name');
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield|array|string $item Item to insert
	 * @param Inputfield|string $existingItem Existing item you want to insert after.
	 * @return $this
	 *
	 */
	public function insertAfter($item, $existingItem) {
		return $this->insert($item, $existingItem, false);
	}

	/**
	 * Remove an Inputfield from this instance’s children.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Inputfield|string $key Inputfield object or name
	 * @return $this
	 *
	 */
	public function remove($key) {
		$item = $key;
		if(!$item) return $this;
		if(!$item instanceof Inputfield) {
			if(!is_string($item)) return $this;
			$item = $this->getChildByName($item);	
			if(!$item) return $this;
		}
		if($this->children()->has($item)) {
			$this->children()->remove($item);
		} else if($this->getChildByName($item->attr('name')) && $item->parent) {
			$item->parent->remove($item);
		}
		return $this; 
	}

	/**
	 * Remove an Inputfield from the form by name
	 * 
	 * Note that this works the same as the getByName/getChildByName methods in that it
	 * will find (and remove) the field by name, even if nested within other wrappers
	 * or fieldsets. It returns the removed Inputfield when found, or null if not. 
	 * 
	 * @param string $name
	 * @return Inputfield|null Removed Inputfield object on success, or null if not found
	 * @since 3.0.250
	 * 
	 */
	public function removeByName($name) {
		$f = $this->getByName((string) $name);
		if(!$f) return null;
		$parent = $f->getParent();
		if(!$parent instanceof InputfieldWrapper) return null;
		$parent->remove($f);
		return $f;
	}

	/**
	 * Prepare children for rendering by creating any fieldset groups
	 * 
	 */
	protected function preRenderChildren() {

		if($this->getSetting('InputfieldWrapper_isPreRendered')) return $this->children(); 

		$children = $this->wire(new InputfieldWrapper()); 
		$wrappers = array($children);
		$prepend = array();
		$append = array();
		$numMove = 0;

		foreach($this->children() as $inputfield) {

			$wrapper = end($wrappers); 

			if($inputfield instanceof InputfieldFieldsetClose) {
				if(count($wrappers) > 1) array_pop($wrappers); 
				continue; 

			} else if($inputfield instanceof InputfieldFieldsetOpen) {
				$inputfield->set('InputfieldWrapper_isPreRendered', true); 
				$wrappers[] = $inputfield; 
			} 

			$inputfield->unsetParent();
			$wrapper->add($inputfield);
			
			$flags = $inputfield->renderFlags;
			if($flags & Inputfield::renderFirst) {
				$prepend[] = $inputfield;
				$numMove++;
			} else if($flags & Inputfield::renderLast) {
				$append[] = $inputfield;
				$numMove++;
			}
		}
		
		if($numMove) {
			foreach($prepend as $f) {
				/** @var Inputfield $f */
				$f->getParent()->prepend($f);
			}
			foreach($append as $f) {
				/** @var Inputfield $f */
				$f->getParent()->append($f);
			}
		}

		return $children;
	}

	/**
	 * Cached class parents indexed by Inputfield class name
	 * 
	 * @var array
	 * 
	 */
	static protected $classParents = array();

	/**
	 * Get array of parent Inputfield classes for given Inputfield (excluding the base Inputfield class)
	 * 
	 * @param Inputfield|string $inputfield
	 * @return array
	 * 
	 */
	protected function classParents($inputfield) {
		$p = &self::$classParents;
		$c = is_object($inputfield) ? $inputfield->className() : $inputfield;
		if(!isset($p[$c])) {
			$p[$c] = array();
			foreach(wireClassParents($inputfield) as $parentClass) {
				if(strpos($parentClass, 'Inputfield') !== 0 || $parentClass === 'Inputfield') break;
				$p[$c][] = $parentClass;
			}
		}
		return $p[$c];	
	}

	/**
	 * Prepare Inputfield for attributes used during rendering
	 * 
	 * #pw-internal
	 * 
	 * @param Inputfield $inputfield
	 * @param array $markup
	 * @param array $classes
	 * @since 3.0.144
	 * 
	 */
	private function attributeInputfield(Inputfield $inputfield, &$markup, &$classes) {
		
		$inputfieldClass = $inputfield->className();
		$markupTemplate = array('attr' => array(), 'wrapAttr' => array(), 'set' => array());
		$markupKeys = array($inputfieldClass, "name=$inputfield->name", "id=$inputfield->id");
		$classKeys = array('class', 'wrapClass', 'headerClass', 'contentClass');
		$addClasses = array();
		$attr = array();
		$wrapAttr = array();
		$sets = array();

		foreach($markupKeys as $key) {
			if(isset($markup[$key])) $markup = array_merge($markup, $markup[$key]);
			if(isset($classes[$key])) $classes = array_merge($classes, $classes[$key]);
		}
		
		foreach(array_merge($this->classParents($inputfield), $markupKeys) as $key) {
			if(!isset($markup[$key])) continue;
			$markupParent = array_merge($markupTemplate, $markup[$key]);
			foreach($classKeys as $classKey) {
				if(!empty($markupParent[$classKey])) {
					$addClasses[$classKey] = $markupParent[$classKey];
				}
			}
			foreach($markupParent['attr'] as $k => $v) {
				$attr[$k] = $v;
			}
			foreach($markupParent['wrapAttr'] as $k => $v) {
				$wrapAttr[$k] = $v;
			}
			foreach($markupParent['set'] as $k => $v) {
				$sets[$k] = $v;
			}
		}

		foreach($attr as $attrName => $attrVal) {
			$inputfield->attr($attrName, $attrVal);
		}
		foreach($wrapAttr as $attrName => $attrVal) {
			$inputfield->wrapAttr($attrName, $attrVal);
		}
		foreach($addClasses as $classKey => $class) {
			$inputfield->addClass($class, $classKey);
		}
		foreach($sets as $setName => $setVal) {
			$inputfield->set($setName, $setVal); 
		}
	}

	/**
	 * Render this Inputfield and the output of its children.
	 * 
	 * #pw-group-output
	 *
	 * @todo this method has become too long/complex, move to its own pluggable class and split it up a lot
	 * @return string
	 *
	 */
	public function ___render() {
		
		$sanitizer = $this->wire()->sanitizer;
		$out = '';
		$children = $this->preRenderChildren();
		$columnWidthTotal = 0;
		$columnWidthSpacing = $this->getSetting('columnWidthSpacing');
		$quietMode = $this->getSetting('quietMode');
		$lastInputfield = null;
		$_markup = array_merge(self::$defaultMarkup, self::$markup);
		$_classes = array_merge(self::$defaultClasses, self::$classes);
		$markup = array();
		$classes = array();
		$useColumnWidth = $this->useColumnWidth;
		$renderAjaxInputfield = $this->wire()->config->ajax ? $this->wire()->input->get('renderInputfieldAjax') : null;
		$toggleLabel = $sanitizer->entities1($this->_('Toggle open/close'));
		
		$lockedStates = array(
			Inputfield::collapsedNoLocked, 
			Inputfield::collapsedYesLocked, 
			Inputfield::collapsedBlankLocked, 
			Inputfield::collapsedTabLocked
		);
		
		if($useColumnWidth === true && isset($_classes['form']) && strpos($_classes['form'], 'InputfieldFormNoWidths') !== false) {
			$useColumnWidth = false;
		}
	
		// show description for tabs
		$description = $quietMode ? '' : $this->getSetting('description'); 
		if($description && wireClassExists("InputfieldFieldsetTabOpen") && $this instanceof InputfieldFieldsetTabOpen) {
			$out .= str_replace('{out}', nl2br($this->entityEncode($description, true)), $_markup['item_head']);
		}
		
		foreach($children as $inputfield) {
			/** @var Inputfield $inputfield */
			
			if($renderAjaxInputfield && $inputfield->attr('id') !== $renderAjaxInputfield 
				&& !$inputfield instanceof InputfieldWrapper) {
				
				$skip = true;
				foreach($inputfield->getParents() as $parent) {
					/** @var InputfieldWrapper $parent */
					if($parent->attr('id') === $renderAjaxInputfield) $skip = false;
				}
				if($skip && !empty($parents)) continue;
			}
			
			list($markup, $classes) = array($_markup, $_classes);
			$this->attributeInputfield($inputfield, $markup, $classes);
			
			$renderValueMode = $this->getSetting('renderValueMode'); 
			$collapsed = (int) $inputfield->getSetting('collapsed'); 
			$required = $inputfield->getSetting('required');
			$requiredIf = $required ? $inputfield->getSetting('requiredIf') : false;
			$showIf = $inputfield->getSetting('showIf'); 
			
			if($collapsed == Inputfield::collapsedHidden) continue; 
			if(in_array($collapsed, $lockedStates)) $renderValueMode = true;

			$ffOut = $this->renderInputfield($inputfield, $renderValueMode);
			if(!strlen("$ffOut")) continue;
			$collapsed = (int) $inputfield->getSetting('collapsed');  // retrieve again after render
			$entityEncodeText = $inputfield->getSetting('entityEncodeText') === false ? false : true;
			
			$errorsOut = '';
			if(!$inputfield instanceof InputfieldWrapper) {
				$errors = $inputfield->getErrors(true);
				if(count($errors)) {
					$collapsed = $renderValueMode ? Inputfield::collapsedNoLocked : Inputfield::collapsedNo;
					$comma = $this->_(','); // Comma or other character to separate multiple error messages
					$errorsOut = implode("$comma ", $errors);
				}
			} else $errors = array();
		
			foreach(array('error', 'description', 'head', 'notes', 'detail') as $property) {
				$text = $property == 'error' ? $errorsOut : $inputfield->getSetting($property); 
				if($property === 'detail' && !is_string($text)) continue; // may not be necessary
				if(!empty($text) && !$quietMode) {
					if($entityEncodeText) {
						$text = $inputfield->entityEncode($text, true);
					}
					if($inputfield->textFormat != Inputfield::textFormatMarkdown) {
						$text = str_replace('{out}', nl2br($text), $markup["item_$property"]);
					}
				} else {
					$text = '';
				}
				$_property = '{' . $property . '}';
				if(strpos($markup['item_content'], $_property) !== false) {
					$markup['item_content'] = str_replace($_property, $text, $markup['item_content']);
				} else if(strpos($markup['item_label'], $_property) !== false) {
					$markup['item_label'] = str_replace($_property, $text, $markup['item_label']);
				} else if($text && ($property == 'notes' || $property == 'detail')) {
					$ffOut .= $text;
				} else if($text) {
					$ffOut = $text . $ffOut;
				}
			}
			
			if(!$quietMode) {
				$prependMarkup = $inputfield->getSetting('prependMarkup');
				if($prependMarkup) $ffOut = $prependMarkup . $ffOut;
				$appendMarkup = $inputfield->getSetting('appendMarkup');
				if($appendMarkup) $ffOut .= $appendMarkup;
			}
			
			// The inputfield classname is always used in its wrapping element
			$ffAttrs = array(
				'class' => str_replace(
					array('{class}', '{name}'), 
					array($inputfield->className(), $inputfield->attr('name')
				), 
				$classes['item'])
			);
			if($inputfield instanceof InputfieldItemList) $ffAttrs['class'] .= " InputfieldItemList";
			if($collapsed) $ffAttrs['class'] .= " collapsed$collapsed";

			if(count($errors)) $ffAttrs['class'] .= ' ' . $classes['item_error'];
			if($required) $ffAttrs['class'] .= ' ' . $classes['item_required']; 
			if(strlen($showIf) && !$this->getSetting('renderValueMode')) { // note: $this->renderValueMode (rather than $renderValueMode) is intentional
				$ffAttrs['data-show-if'] = $showIf;
				$ffAttrs['class'] .= ' ' . $classes['item_show_if'];
			}
			if(strlen($requiredIf)) {
				$ffAttrs['data-required-if'] = $requiredIf; 
				$ffAttrs['class'] .= ' ' . $classes['item_required_if']; 
			}

			if($collapsed && $collapsed !== Inputfield::collapsedNever) {
				$isEmpty = $inputfield->isEmpty();
				if(($isEmpty && $inputfield instanceof InputfieldWrapper && $collapsed !== Inputfield::collapsedPopulated) || 
					$collapsed === Inputfield::collapsedYes ||
					$collapsed === Inputfield::collapsedYesLocked ||
					$collapsed === true || 
					$collapsed === Inputfield::collapsedYesAjax ||
					($isEmpty && $collapsed === Inputfield::collapsedBlank) ||
					($isEmpty && $collapsed === Inputfield::collapsedBlankAjax) ||
					($isEmpty && $collapsed === Inputfield::collapsedBlankLocked) ||
					(!$isEmpty && $collapsed === Inputfield::collapsedPopulated)) {
						$ffAttrs['class'] .= ' ' . $classes['item_collapsed'];
					}
			}
			
			if($inputfield instanceof InputfieldWrapper) {
				// if the child is a wrapper, then id, title and class attributes become part of the LI wrapper
				foreach($inputfield->getAttributes() as $k => $v) {
					if(in_array($k, array('id', 'title', 'class'))) {
						$ffAttrs[$k] = isset($ffAttrs[$k]) ? $ffAttrs[$k] . " $v" : $v; 
					}
				}
			} 
		
			// if inputfield produced no output, then move to next
			if(!strlen($ffOut)) continue;

			// wrap the inputfield output
			$attrs = '';
			$label = (string) $inputfield->getSetting('label');
			$skipLabel = $inputfield->getSetting('skipLabel'); 
			$skipLabel = is_bool($skipLabel) || empty($skipLabel) ? (bool) $skipLabel : (int) $skipLabel; // force as bool or int
			if(!strlen($label) && $skipLabel !== Inputfield::skipLabelBlank && $inputfield->className() != 'InputfieldWrapper') {
				$label = $inputfield->attr('name');
			}
			if(($label || $quietMode) && $skipLabel !== Inputfield::skipLabelMarkup) {
				$for = $skipLabel || $quietMode ? '' : $inputfield->attr('id');
				// if $inputfield has a property of entityEncodeLabel with a value of boolean FALSE, we don't entity encode
				$entityEncodeLabel = $inputfield->getSetting('entityEncodeLabel');
				if(is_int($entityEncodeLabel) && $entityEncodeLabel >= Inputfield::textFormatBasic) {
					// uses an Inputfield::textFormat constant
					$label = $inputfield->entityEncode($label, $entityEncodeLabel);
				} else if($entityEncodeLabel !== false) {
					$label = $inputfield->entityEncode($label);
				}
				$icon = $inputfield->getSetting('icon');
				$icon = $icon ? str_replace('{name}', $sanitizer->name(str_replace(array('icon-', 'fa-'), '', $icon)), $markup['item_icon']) : ''; 
				$toggle = $collapsed == Inputfield::collapsedNever ? '' : $markup['item_toggle']; 
				if($toggle && strpos($toggle, 'title=') === false) {
					$toggle = str_replace("class=", "title='$toggleLabel' class=", $toggle);
				}
				$headerActions = $inputfield->addHeaderAction();
				if(count($headerActions)) {
					$label .= $this->renderHeaderActions($inputfield, $headerActions);
				}
				if($skipLabel === Inputfield::skipLabelHeader || $quietMode) {
					// label only shows when field is collapsed
					$labelHidden = $markup['item_label_hidden'];
					if(strpos($labelHidden, '{for}')) $labelHidden = str_replace('{for}', $inputfield->attr('id'), $labelHidden);
					$label = str_replace('{out}', $icon . $label . $toggle, $labelHidden); 
				} else {
					// label always visible
					$label = str_replace('{out}', $icon . $label . $toggle, $markup['item_label']);
					if($skipLabel === Inputfield::skipLabelFor) {
						$label = $this->removeAttributeFromMarkup('for', $label);
					} else {
						$label = $this->setAttributeInMarkup('for', $for, $label, true);
					}
				}
				$headerClass = trim($inputfield->getSetting('headerClass') . " $classes[item_label]");
				$label = $this->setAttributeInMarkup('class', $headerClass, $label);
				
			} else if($skipLabel === Inputfield::skipLabelMarkup) {
				// no header and no markup for header
				$label = '';
			} else {
				// no header
				// $inputfield->addClass('InputfieldNoHeader', 'wrapClass'); 
			}
			
			$columnWidth = (int) $inputfield->getSetting('columnWidth');
			$columnWidthAdjusted = $columnWidth;
			if($columnWidthSpacing) {
				$columnWidthAdjusted = $columnWidth + ($columnWidthTotal ? -1 * $columnWidthSpacing : 0);
			}
			if($columnWidth >= 9 && $columnWidth <= 100) {
				$ffAttrs['class'] .= ' ' . $classes['item_column_width'];
				if(!$columnWidthTotal) {
					$ffAttrs['class'] .= ' ' . $classes['item_column_width_first'];
				}
				$columnWidthTotal += $columnWidth;
				if(!$useColumnWidth || $useColumnWidth > 1) {
					if($columnWidthTotal >= 95 && $columnWidthTotal < 100) {
						$columnWidthAdjusted += (100 - $columnWidthTotal);
						$columnWidthTotal = 100;
					}
					$ffAttrs['data-colwidth'] = "$columnWidthAdjusted%";
				}
				if($useColumnWidth) {
					$ffAttrs['style'] = "width: $columnWidthAdjusted%;";
				}
				//if($columnWidthTotal >= 100 && !$requiredIf) $columnWidthTotal = 0; // requiredIf meant to be a showIf?
				if($columnWidthTotal >= 100) $columnWidthTotal = 0;
			} else {
				$columnWidthTotal = 0;
			}
			if(!isset($ffAttrs['id'])) $ffAttrs['id'] = 'wrap_' . $inputfield->attr('id'); 
			$ffAttrs['class'] = str_replace('Inputfield_ ', '', $ffAttrs['class']); 
			$wrapClass = $inputfield->getSetting('wrapClass');
			$fieldName = $inputfield->attr('data-field-name');
			if($fieldName && $fieldName != $inputfield->attr('name')) {
				// ensures that Inputfields renamed by context retain the original field-name based class 
				$wrapClass = "Inputfield_$fieldName $wrapClass";
				if(!isset($ffAttrs['data-id'])) $ffAttrs['data-id'] = "wrap_Inputfield_$fieldName";
			}
			if($wrapClass) $ffAttrs['class'] = trim("$ffAttrs[class] $wrapClass"); 
			foreach($inputfield->wrapAttr() as $k => $v) {
				if($k === 'class' && !empty($ffAttrs[$k])) {
					$ffAttrs[$k] .= " $v";
				} else {
					$ffAttrs[$k] = $v;
				}
			}
			foreach($ffAttrs as $k => $v) {
				$k = $this->entityEncode($k);
				$v = $this->entityEncode(trim($v));
				$attrs .= " $k='$v'";
			}
			$markupItemContent = $markup['item_content'];
			$contentClass = trim($inputfield->getSetting('contentClass') . " $classes[item_content]");
			$markupItemContent = $this->setAttributeInMarkup('class', $contentClass, $markupItemContent);
			if($inputfield->className() != 'InputfieldWrapper') $ffOut = str_replace('{out}', $ffOut, $markupItemContent);
			$ffOut .= $inputfield->getSetting('footerMarkup');
			$out .= str_replace(array('{attrs}', '{out}'), array(trim($attrs), $label . $ffOut), $markup['item']); 
			$lastInputfield = $inputfield;
		} // foreach($children as $inputfield)

		if($out) {
			$ulClass = $classes['list'];
			$lastColumnWidth = $lastInputfield ? $lastInputfield->getSetting('columnWidth') : 0;
			if($columnWidthTotal || ($lastInputfield && $lastColumnWidth >= 10 && $lastColumnWidth < 100)) {
				$ulClass .= ' ' . $classes['list_clearfix'];
			}
			$attrs = "class='$ulClass'"; // . ($this->attr('class') ? ' ' . $this->attr('class') : '') . "'";
			if(!($this instanceof InputfieldForm)) {
				foreach($this->getAttributes() as $attr => $value) {
					if(strpos($attr, 'data-') === 0) $attrs .= " $attr='" . $this->entityEncode($value) . "'";
				}
			}
			$out = $this->attr('value') . str_replace(array('{attrs}', '{out}'), array($attrs, $out), $markup['list']); 
		}

		return $out; 
	}

	/**
	 * Set attribute value in markup, optionally replacing a {placeholder} tag
	 * 
	 * When a placeholder is present in the given $markup, it should be the 
	 * attribute name wrapped in `{}`, i.e. `{class}`
	 * 
	 * Note that class attributes are appended while other attributes are replaced.
	 * 
	 * @param string $name Attribute name (i.e. "class", "for", etc.)
	 * @param string $value Value to set for the attribute
	 * @param string $markup Markup where the attribute or placeholder exists
	 * @param bool $removeEmpty Remove attribute if it resolves to empty value?
	 * @return string Updated markup
	 * @since 3.0.242
	 * 
	 */
	protected function setAttributeInMarkup($name, $value, $markup, $removeEmpty = false) {
		
		$placeholder = '{' . $name . '}';
		$hasPlaceholder = strpos($markup, $placeholder) !== false; 
		
		if(strlen("$value")) {
			if($hasPlaceholder) {
				// replace existing class="… with class="… value
				$replacement = $name === 'class' ? " $value" : $value;
				$markup = str_replace($placeholder, $replacement, $markup);
				
			} else if(strpos($markup, " $name=") !== false) {
				// update existing attribute value without a {class} being present
				// for class attribute it appends existing, for others it replaces
				$replacement = $name === 'class' ? "$1 $value" : $value;
				$markup = preg_replace('/(\s' . $name . '=[\'"][^\'"]*)/', $replacement, $markup, 1);
				
			} else {
				// insert attribute where it doesn't currently exist
				$markup = preg_replace('!(<[a-z0-9]+)(\s*)!i', "$1 $name='$value'$2", $markup, 1);
			}
			
			// remove unnecessary whitespace in class attribute values
			if($name === 'class') {
				foreach(array(" $name=' ", " $name=\" ") as $find) {
					while(strpos($markup, $find)) {
						$markup = str_replace($find, rtrim($find), $markup);
					}
				}
			}
			
		} else if($hasPlaceholder) {
			if($removeEmpty) {
				// remove name="{name}"
				$markup = str_replace(array(" $name='{" . $name . "}'", " $name=\"{" . $name . "}\""), '', $markup);
			} else {
				// replace {name} with blank string
				$markup = str_replace($placeholder, '', $markup);
			}
			
		} else {
			// $value is empty and there is no placeholder, leave $markup as-is
		}
		
		return $markup;
	}

	/**
	 * Remove named attribute from given markup
	 * 
	 * @param string $name
	 * @param string $markup
	 * @return string
	 * @since 3.0.250
	 * 
	 */
	protected function removeAttributeFromMarkup($name, $markup) {
		if(stripos($markup, " $name=") === false) return $markup;
		return preg_replace('!\s' . $name . '=["\'][^"\']*["\']!i', '', $markup);
	}

	/**
	 * Render Inputfield header actions
	 * 
	 * @param Inputfield $inputfield
	 * @param array $actions
	 * @return string
	 * @since 3.0.240
	 * 
	 */
	protected function renderHeaderActions(Inputfield $inputfield, array $actions) {
		$sanitizer = $this->wire()->sanitizer;
		$out = '';
		$modal = false;
		foreach($actions as $a) {
			$icon = '';
			$type = '';
			if(isset($a['icon'])) {
				$icon = $a['icon'];
				if(isset($a['href'])) {
					$type = 'link';
					if(!empty($a['modal'])) $modal = true;
				} else {
					$type = 'click';
				}
			} else if(isset($a['offIcon'])) {
				$type = 'toggle';
				if(!isset($a['onIcon'])) $a['onIcon'] = $a['offIcon'];
			} else if(isset($a['onIcon'])) {
				$type = 'toggle';
				$a['offIcon'] = $a['onIcon']; 
			}
			if($type === 'toggle') $icon = !empty($a['on']) ? $a['onIcon'] : $a['offIcon'];
			if(empty($icon) || empty($type)) continue;
			$a['type'] = $type;
			if(strpos($icon, 'fa-') !== 0) $icon = "fa-$icon";
			$data = $sanitizer->entities(json_encode($a));
			$out .= "<i class='_InputfieldHeaderAction fa fa-fw $icon' data-action='$data' hidden></i>";
		}
		if($modal) {
			/** @var JqueryUI $jQueryUI */
			$jQueryUI = $this->wire()->modules->get('JqueryUI');
			$jQueryUI->use('modal');
		}
		return $out;
	}

	/**
	 * Render the output of this Inputfield and its children, showing values only (no inputs)
	 * 
	 * #pw-group-output
	 * 
	 * @return string
	 * 
	 */
	public function ___renderValue() {
		if(!count($this->children())) return '';
		$this->addClass('InputfieldRenderValueMode');
		$this->set('renderValueMode', true); 
		$out = $this->render(); 
		$this->set('renderValueMode', false); 
		return $out; 
	}

	/**
	 * Render output for an individual Inputfield
	 * 
	 * This method takes care of all the pre-and-post requisites needed for rendering an Inputfield
	 * among a group of Inputfields. It is used by the `InputfieldWrapper::render()` method for each
	 * Inputfield present in the children. 
	 * 
	 * #pw-group-output
	 * 
	 * @param Inputfield $inputfield The Inputfield to render.
	 * @param bool $renderValueMode Specify true if we are only rendering values (default=false).
	 * @return string Rendered output
	 * 
	 */
	public function ___renderInputfield(Inputfield $inputfield, $renderValueMode = false) {

		$inputfieldID = $inputfield->attr('id');
		$collapsed = (int) $inputfield->getSetting('collapsed');
		$ajaxInputfield = $collapsed == Inputfield::collapsedYesAjax || $collapsed === Inputfield::collapsedTabAjax 
			|| ($collapsed == Inputfield::collapsedBlankAjax && $inputfield->isEmpty());
		$ajaxHiddenInput = "<input type='hidden' name='processInputfieldAjax[]' value='$inputfieldID' />";
		$ajaxID = $this->wire()->config->ajax ? $this->wire()->input->get('renderInputfieldAjax') : '';
		$required = $inputfield->getSetting('required');
		
		if($ajaxInputfield && (($required && $inputfield->isEmpty()) || !$this->wire()->user->isLoggedin())) {
			// if an ajax field is empty, and is required, then we don't use ajax render mode
			// plus, we only allow ajax inputfields for logged-in users
			$ajaxInputfield = false;
			if($collapsed == Inputfield::collapsedYesAjax) $inputfield->collapsed = Inputfield::collapsedYes;
			if($collapsed == Inputfield::collapsedBlankAjax) $inputfield->collapsed = Inputfield::collapsedBlank;
			if($collapsed == Inputfield::collapsedTabAjax) $inputfield->collapsed = Inputfield::collapsedTab;
			// indicate to next processInput that this field can be processed
			$inputfield->appendMarkup .= $ajaxHiddenInput;
		}

		$restoreValue = null; // value to restore, if we happen to modify it before render (renderValueMode only)
		
		if($renderValueMode) {
			$flags = $inputfield->getSetting('renderValueFlags');
			$inputfield->addClass('InputfieldRenderValueMode', 'wrapClass');
			if($flags & Inputfield::renderValueMinimal) {
				$inputfield->addClass('InputfieldRenderValueMinimal', 'wrapClass');
			}
			if($flags & Inputfield::renderValueFirst) {
				// render only first item value
				$inputfield->addClass('InputfieldRenderValueFirst', 'wrapClass');
				$value = $inputfield->attr('value');
				if(WireArray::iterable($value) && count($value) > 1) {
					$restoreValue = $value;
					if(is_array($value)) {
						$inputfield->attr('value', array_slice($value, 0, 1));
					} else if($value instanceof WireArray) {
						$inputfield->attr('value', $value->slice(0, 1));
					}
				}
			}
		}
		
		$inputfield->renderReady($this, $renderValueMode);
		
		if($ajaxInputfield) {
			
			if($ajaxID && $ajaxID === $inputfieldID) {
				// render ajax inputfield
				$editable = $inputfield->editable();
				if($renderValueMode || !$editable) {
					echo $inputfield->renderValue();
				} else {
					echo $inputfield->render();
					echo $ajaxHiddenInput;
				}
				exit;
				
			} else if($ajaxID && $ajaxID != $inputfieldID && $inputfield instanceof InputfieldWrapper && 
				$inputfield->getChildByName(str_replace('Inputfield_', '', $ajaxID))) {
				// nested ajax inputfield, within another ajax inputfield
				$in = $inputfield->getChildByName(str_replace('Inputfield_', '', $ajaxID));
				return $this->renderInputfield($in, $renderValueMode);
				
			} else {
				// do not render ajax inputfield now, instead render placeholder
				return $this->renderInputfieldAjaxPlaceholder($inputfield, $renderValueMode);
			}
		}
		
		if(!$renderValueMode && $inputfield->editable()) return $inputfield->render();
	
		// renderValueMode
		$out = $inputfield->renderValue();
		if(!is_null($restoreValue)) {
			$inputfield->attr('value', $restoreValue);
			$inputfield->resetTrackChanges();
		}
		if(is_null($out)) return '';
		if(!strlen($out) && !$inputfield instanceof InputfieldWrapper) $out = '&nbsp;'; // prevent output from being skipped over
		return $out;
	}

	/**
	 * Render a placeholder for an ajax-loaded Inputfield
	 * 
	 * @param Inputfield $inputfield
	 * @param bool $renderValueMode
	 * @return string
	 * 
	 */
	protected function renderInputfieldAjaxPlaceholder(Inputfield $inputfield, $renderValueMode) {
	
		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;
		$inputfieldID = $inputfield->attr('id');
		$url = $input->url();
		$queryString = $input->queryString();
		
		if(strpos($queryString, 'renderInputfieldAjax=') !== false) {
			// in case nested ajax request 
			$queryString = preg_replace('/&?renderInputfieldAjax=[^&]+/', '', $queryString);
		}
		
		$url .= $queryString ? "?$queryString&" : "?";
		$url .= "renderInputfieldAjax=$inputfieldID";
		$url = $sanitizer->entities($url);
		
		$valueInput = '';
		$val = $inputfield->val();
		if(!is_array($val) && !is_object($val)) {
			$val = (string) $val;
			if(strlen("$val") <= 1024) {
				// keep value in hidden input so dependences can refer to it
				$val = $sanitizer->entities("$val");
				$valueInput = "<input type='hidden' id='$inputfieldID' value='$val' />";
			}
		}

		$out =
			"<div class='renderInputfieldAjax'>" .
				"<input type='hidden' value='$url' />" .
				$valueInput .
			"</div>";
		
		if($inputfield instanceof InputfieldWrapper) {
			// load assets they will need
			foreach($inputfield->getAll() as $in) {
				/** @var Inputfield $in */
				$in->renderReady($inputfield, $renderValueMode);
			}
		}
	
		// ensure that Inputfield::render() hooks are still called
		if($inputfield->hasHook('render()')) {
			$inputfield->runHooks('render', array(), 'before');
		}
		
		return $out; 
	}

	/**
	 * Process input for all children
	 * 
	 * #pw-group-input
	 *
	 * @param WireInputData $input
	 * @return $this
	 * 
	 */
	public function ___processInput(WireInputData $input) {
	
		if(!$this->children) return $this;
		
		$hasHook = $this->isHooked('InputfieldWrapper::allowProcessInput()');

		foreach($this->children() as $child) {
			/** @var Inputfield $child */

			// skip over the inputfield if hook tells us so
			if($hasHook && !$this->allowProcessInput($child)) continue;

			// skip over the inputfield if it is not processable
			if(!$this->isProcessable($child)) continue; 	

			// pass along the dependencies value to child wrappers
			if($child instanceof InputfieldWrapper && $this->getSetting('useDependencies') === false) {
				$child->set('useDependencies', false); 
			}

			// call the inputfield's processInput method
			$child->processInput($input); 

			// check if a value is required and field is empty, trigger an error if so
			if($child->attr('name') && $child->getSetting('required') && $child->isEmpty()) {
				$requiredLabel = $child->getSetting('requiredLabel'); 
				if(empty($requiredLabel)) $requiredLabel = $this->requiredLabel;
				$child->error($requiredLabel); 
			}
		}

		return $this; 
	}

	/**
	 * Is the given Inputfield processable for input?
	 * 
	 * Returns whether or not the given Inputfield should be processed by processInput()
	 * 
	 * When an `Inputfield` has a `showIf` property, then this returns false, but it queues
	 * the field in the delayedChildren array for later processing. The root container should
	 * temporarily remove the 'showIf' property of inputfields they want processed. 
	 * 
	 * #pw-internal
	 * 
	 * @param Inputfield $inputfield
	 * @return bool
	 * 
	 */
	public function isProcessable(Inputfield $inputfield) {
		
		if(!$inputfield->editable()) return false;
		
		// visibility settings that aren't saveable
		static $skipTypes = array(
			Inputfield::collapsedHidden,
			Inputfield::collapsedLocked,
			Inputfield::collapsedNoLocked,
			Inputfield::collapsedBlankLocked,
			Inputfield::collapsedYesLocked,
			Inputfield::collapsedTabLocked,
		);
		
		$ajaxTypes = array(
			Inputfield::collapsedYesAjax, 
			Inputfield::collapsedBlankAjax, 
			Inputfield::collapsedTabAjax,
		);
		
		$collapsed = (int) $inputfield->getSetting('collapsed');
		if(in_array($collapsed, $skipTypes)) return false;

		if(in_array($collapsed, $ajaxTypes)) {
			$processAjax = $this->wire()->input->post('processInputfieldAjax');
			if(is_array($processAjax) && in_array($inputfield->attr('id'), $processAjax)) {
				// field can be processed (convention used by InputfieldWrapper)
			} else if($collapsed == Inputfield::collapsedBlankAjax && !$inputfield->isEmpty()) {
				// field can be processed because it is only collapsed if blank
			} else if(isset($_SERVER['HTTP_X_FIELDNAME']) && $_SERVER['HTTP_X_FIELDNAME'] === $inputfield->attr('name')) {
				// field can be processed (convention used by ajax uploaded file and other ajax types)
			} else {
				// field was not rendered via ajax and thus can't be processed
				return false;
			}
			unset($processAjax);
		}

		// if dependencies aren't in use, we can skip the rest
		if($this->getSetting('useDependencies') === false) return true; 
		
		if(strlen($inputfield->getSetting('showIf')) || 
			($inputfield->getSetting('required') && strlen($inputfield->getSetting('requiredIf')))) {
			
			$name = $inputfield->attr('name'); 
			if(!$name) {
				$name = $inputfield->attr('id'); 
				if(!$name) $name = $this->wire()->sanitizer->fieldName($inputfield->getSetting('label')); 
				$inputfield->attr('name', $name); 
			}
			$this->delayedChildren[$name] = $inputfield; 
			return false;
		}
		
		return true;
	}

	/**
	 * Allow input to be processed for given Inputfield? (for hooks)
	 * 
	 * IMPORTANT: This method is not called unless it is hooked! Descending classes
	 * should instead implement the isProcessable() method (when needed) and be sure to 
	 * call the parent isProcessable() method too. 
	 * 
	 * #pw-hooker
	 * #pw-internal
	 * 
	 * @param Inputfield $inputfield
	 * @return bool
	 * @since 3.0.207
	 * 
	 */
	public function ___allowProcessInput(Inputfield $inputfield) {
		return true;
	}

	/**
	 * Returns true if all children are empty, or false if one or more is populated
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @return bool
	 * 
	 */
	public function isEmpty() {
		$empty = true; 
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			if(!$child->isEmpty()) {
				$empty = false;
				break;
			}
		}
		return $empty;
	}

	/**
	 * Return Inputfields in this wrapper that are required and have empty values
	 *
	 * This method includes all children up through the tree, not just direct children.
	 *
	 * #pw-internal
	 *
	 * @param bool $required Only include empty Inputfields that are required? (default=true)
	 * @return array of Inputfield instances indexed by name attributes
	 *
	 */
	public function getEmpty($required = true) {
		$a = array();
		static $n = 0;
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			if($child instanceof InputfieldWrapper) {
				$a = array_merge($a, $child->getEmpty($required));
			} else {
				if($required && !$child->getSetting('required')) continue;
				if(!$child->isEmpty()) continue;
				$name = $child->attr('name');
				if(empty($name)) $name = "_unknown" . (++$n);
				$a[$name] = $child;
			}
		}
		return $a;
	}

	/**
	 * Return an array of errors that occurred on any of the children during input processing.
	 *
	 * Should only be called after `InputfieldWrapper::processInput()`.
	 * 
	 * #pw-group-errors
	 *
	 * @param bool $clear Specify true to clear out the errors (default=false).
	 * @return array Array of error strings
	 *
	 */
	public function getErrors($clear = false) {
		$errors = parent::getErrors($clear); 
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			foreach($child->getErrors($clear) as $e) {
				$label = $child->getSetting('label');
				$msg = $label ? $label : $child->attr('name'); 
				$errors[] = $msg . " - $e";
			}
		}
		return $errors;
	}

	/**
	 * Get Inputfield objects that have errors
	 * 
	 * #pw-group-errors
	 * 
	 * @return array|Inputfield[] Array of Inputfield objects indexed by Inputfield name attribute
	 * @since 3.0.205
	 * 
	 */
	public function getErrorInputfields() {
		$a = array();
		if(count(parent::getErrors())) {
			$name = $this->attr('name');
			$a[$name] = $this;
		}
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			if($child instanceof InputfieldWrapper) {
				$a = array_merge($a, $child->getErrorInputfields());
			} else if(count($child->getErrors())) {
				$name = $child->attr('name');
				$a[$name] = $child;
			}
		}
		return $a;
	}

	/**
	 * Return all children Inputfield objects
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 	
	 * @param string $selector Optional selector string to filter the children by
 	 * @return InputfieldsArray
	 *
	 */
	public function children($selector = '') {
		if($selector) {
			return $this->children->find($selector);
		} else {
			return $this->children;
		}
	}

	/**
	 * Find an Inputfield below this one that has the given name
	 * 
	 * This is an alternative to the `getChildByName()` method, with more options for when you need it. 
	 * For instance, it can also accept a selector string or numeric index for the $name argument, and you
	 * can optionally disable the $recursive behavior. 
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string|int $name Name or selector string of child to find, omit for first child, or specify zero-based index of child to return.
	 * @param bool $recursive Find child recursively? Looks for child in this wrapper, and all other wrappers below it. (default=true)
	 * @return Inputfield|null Returns Inputfield instance if found, or null if not.
	 * @since 3.0.110
	 * 
	 */
	public function child($name = '', $recursive = true) {
		$child = null;
		$children = $this->children();

		if(!$children->count()) {
			// no child possible

		} else if(empty($name)) {
			// first child
			$child = $children->first();
			
		} else if(is_int($name)) {
			// number index
			$child = $children->eq($name);
			
		} else if($this->wire()->sanitizer->name($name) === $name) {
			// child by name
			$wrappers = array();
			foreach($children as $f) {
				/** @var Inputfield $f */
				if($f->getAttribute('name') === $name) {
					$child = $f;
					break;
				} else if($recursive && $f instanceof InputfieldWrapper) {
					$wrappers[] = $f;
				}
			}
			if(!$child && $recursive && count($wrappers)) {
				foreach($wrappers as $wrapper) {
					$child = $wrapper->child($name, $recursive);
					if($child) break;
				}
			}

		} else if(Selectors::stringHasSelector($name)) {
			// first child matching selector string
			$child = $children->find("$name, limit=1")->first();
		}
		
		return $child;
	}

	/**
	 * Return all children Inputfields (alias of children method)
	 *
	 * #pw-internal
	 *
	 * @param string $selector Optional selector string to filter the children by
 	 * @return InputfieldsArray
	 *
	 */
	public function getChildren($selector = '') {
		return $this->children($selector); 
	}

	/**
	 * Return array of inputfields (indexed by name) of fields that had dependencies and were not processed
	 * 
	 * The results are to be handled by the root containing element (i.e. InputfieldForm).
	 * 
	 * #pw-internal
	 *
	 * @param bool $clear Set to true in order to clear the delayed children list.
	 * @return array|Inputfield[]
	 *
	 */
	public function _getDelayedChildren($clear = false) {
		$a = $this->delayedChildren; 
		foreach($this->children() as $child) {
			if(!$child instanceof InputfieldWrapper) continue; 
			$a = array_merge($a, $child->_getDelayedChildren($clear)); 
		}
		if($clear) $this->delayedChildren = array();
		return $a; 
	}

	/**
	 * Find all children Inputfields matching a selector string
	 * 
	 * #pw-group-retrieval-and-traversal
	 *
	 * @param string $selector Required selector string to filter the children by
 	 * @return InputfieldsArray
	 *
	 */
	public function find($selector) {
		return $this->children()->find($selector); 
	}

	/**
	 * Given an Inputfield name, return the child Inputfield or NULL if not found.
	 * 
	 * This traverses all children recursively to find the requested Inputfield. 
	 * 
	 * This is the same as the `InputfieldWrapper::get()` method except that it can
	 * only return Inputfield or null, and has no crossover with other settings, 
	 * properties or API variables. 
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string $name Name of Inputfield
	 * @return Inputfield|InputfieldWrapper|null
	 * @see InputfieldWrapper::get(), InputfieldWrapper::children()
	 *
	 */
	public function getChildByName($name) {
		return strlen($name) ? $this->getByAttr('name', $name) : null;
	}

	/**
	 * Shorter alias of getChildByName()
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string $name
	 * @return Inputfield|InputfieldWrapper|null
	 * @since 3.0.172
	 * 
	 */
	public function getByName($name) {
		return strlen($name) ? $this->getByAttr('name', $name) : null;
	}

	/**
	 * Given an attribute name and value, return the first matching Inputfield or null if not found
	 * 
	 * This traverses all children recursively to find the requested Inputfield.
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string $attrName Attribute to match, such as 'id', 'name', 'value', etc.
	 * @param string $attrValue Attribute value to match
	 * @return Inputfield|InputfieldWrapper|null
	 * @since 3.0.196
	 * 
	 */
	public function getByAttr($attrName, $attrValue) {
		$inputfield = null;
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			if($child->getAttribute($attrName) === $attrValue) {
				$inputfield = $child;
			} else if($child instanceof InputfieldWrapper) {
				$inputfield = $child->getByAttr($attrName, $attrValue);
			}
			if($inputfield) break;
		}
		return $inputfield;
	}

	/**
	 * Get Inputfield by Field (hasField)
	 * 
	 * This is useful in cases where the input name may differ from the Field name
	 * that it represents, and you only know the field name. Applies only to 
	 * Inputfields connected with a Page and Field (i.e. used for page editing).
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param Field|string|int $field
	 * @return Inputfield|InputfieldWrapper|null
	 * @since 3.0.239
	 * 
	 */
	public function getByField($field) {
		if(!$field instanceof Field) $field = $this->wire()->fields->get($field);
		return $this->getByProperty('hasField', $field);
	}

	/**
	 * Get Inputfield by some other non-attribute property or setting
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @param string $property
	 * @param mixed $value
	 * @param bool $getAll Get array of all matching Inputfields rather than just first? (default=false)
	 * @return Inputfield|InputfieldWrapper|null|array
	 * @since 3.0.239
	 * 
	 */
	public function getByProperty($property, $value, $getAll = false) {
		$inputfield = null;
		$value = (string) $value;
		$a = array();
		
		foreach($this->children() as $child) {
			/** @var Inputfield $child */
			if((string) $child->getSetting($property) === $value) {
				$inputfield = $child;
			} else if($child instanceof InputfieldWrapper) {
				if($getAll) {
					$a = array_merge($a, $child->getByProperty($property, $value, true));
				} else {
					$inputfield = $child->getByProperty($property, $value);
				}
			}
			if($inputfield) {
				if($getAll) {
					$a[] = $inputfield;
				} else {
					break;
				}
			}
		}
		return $getAll ? $a : $inputfield;
	}

	/**
	 * Get value of Inputfield by name
	 * 
	 * This traverses all children recursively to find the requested Inputfield,
	 * and get the value attribute from it. A value of null is returned if the 
	 * Inputfield cannot be found. 
	 * 
	 * @param string $name
	 * @return array|float|int|object|Wire|WireArray|WireData|string|null
	 * @since 3.0.172
	 * 
	 */
	public function getValueByName($name) {
		$inputfield = $this->getByName($name);
		return $inputfield ? $inputfield->val() : null;
	}

	/**
	 * Enables foreach() of the children of this class
	 * 
	 * Per the InteratorAggregate interface, make the Inputfield children iterable.
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @return InputfieldsArray
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		return $this->children(); 
	}

	/**
	 * Return the quantity of children present
	 * 
	 * #pw-group-retrieval-and-traversal
	 * 
	 * @return int
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function count() {
		return count($this->children());
	}

	/**
	 * Get all Inputfields below this recursively in a flat InputfieldWrapper (children, and their children, etc.)
	 *
	 * Note that all InputfieldWrapper instances are removed as a result (except for the containing InputfieldWrapper).
	 * 
	 * #pw-group-retrieval-and-traversal
 	 *  
	 * @param array $options Options to modify behavior (3.0.169+)
	 *  - `withWrappers` (bool): Also include InputfieldWrapper objects? (default=false) 3.0.169+
	 * @return InputfieldsArray
	 *
	 */
	public function getAll(array $options = array()) {
		/** @var InputfieldsArray $all */
		$all = $this->wire(new InputfieldsArray());
		foreach($this->children() as $child) {
			if($child instanceof InputfieldWrapper) {
				if(!empty($options['withWrappers'])) $all->add($child);
				foreach($child->getAll($options) as $c) {
					$all->add($c); 
				}
			} else {
				$all->add($child); 
			}
		}
		return $all;
	}
	
	/**
	 * Start or stop tracking changes, applying the same to any children
	 * 
	 * #pw-internal
	 * 
	 * @param bool $trackChanges
	 * @return Inputfield|InputfieldWrapper
	 *
	 */
	public function setTrackChanges($trackChanges = true) {
		$children = $this->children();
		if(count($children)) foreach($children as $child) $child->setTrackChanges($trackChanges); 
		return parent::setTrackChanges($trackChanges); 
	}

	/**
	 * Start or stop tracking changes after clearing out any existing tracked changes, applying the same to any children
	 * 
	 * #pw-internal
	 *
	 * @param bool $trackChanges
	 * @return Inputfield|InputfieldWrapper
	 *
	 */
	public function resetTrackChanges($trackChanges = true) {
		$children = $this->children();
		if(count($children)) foreach($children as $child) $child->resetTrackChanges($trackChanges); 
		return parent::resetTrackChanges($trackChanges);
	}

	/**
	 * Get configuration Inputfields for this InputfieldWrapper
	 * 
	 * #pw-group-module
	 * 
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields() {

		$inputfields = parent::___getConfigInputfields();

		/** @var InputfieldSelect $f */
		$f = $inputfields->getChildByName('collapsed');
		if($f) {
			// whitelist of collapsed options allowed for fieldsets/wrappers
			$allow = array(
				Inputfield::collapsedNo, 
				Inputfield::collapsedYes, 
				Inputfield::collapsedYesAjax,
				Inputfield::collapsedNever,
				Inputfield::collapsedHidden,
				Inputfield::collapsedBlank,
				Inputfield::collapsedPopulated,
				Inputfield::collapsedBlankAjax,
				Inputfield::collapsedBlankLocked,
			);
			foreach($f->getOptions() as $value => $label) {
				if(!in_array($value, $allow)) $f->removeOption($value);
			}
		}

		return $inputfields;
	}

	/**
	 * Set custom markup for render, see self::$markup at top for reference.
	 * 
	 * #pw-internal
	 *
	 * @param array $markup
	 *
	 */
	public static function setMarkup(array $markup) { 
		self::$markup = array_merge(self::$markup, $markup); 
	}

	/**
	 * Get custom markup for render, see self::$markup at top for reference.
	 * 
	 * #pw-internal
	 *
	 * @return array 
	 *
	 */
	public static function getMarkup() { 
		return array_merge(self::$defaultMarkup, self::$markup); 
	}

	/**
	 * Set custom classes for render, see self::$classes at top for reference.
	 * 
	 * #pw-internal
	 * 
	 * @param array $classes
	 *
	 */
	public static function setClasses(array $classes) { 
		self::$classes = array_merge(self::$classes, $classes); 
	}

	/**
	 * Get custom classes for render, see self::$classes at top for reference.
	 * 
	 * #pw-internal
	 *
	 * @return array
	 * 
	 */
	public static function getClasses() { 
		return array_merge(self::$defaultClasses, self::$classes); 
	}

	/**
	 * Import an array of Inputfield definitions to to this InputfieldWrapper instance
	 *
	 * Your array should be an array of associative arrays, with each element describing an Inputfield.
	 * The following properties are required for each Inputfield definition: 
	 * 
	 * - `type` Which Inputfield module to use (may optionally exclude the "Inputfield" prefix). 
	 * - `name` Name attribute to use for the Inputfield. 
	 * - `label` Text label that appears above the Inputfield. 
	 * 
	 * ~~~~~
	 * // Example array for Inputfield definitions
	 * array(
	 *   array(
	 *     'name' => 'fullname',
	 *     'type' => 'text',
	 *     'label' => 'Field label'
	 *     'columnWidth' => 50,
	 *     'required' => true,
	 *   ),
	 *   array(
	 *     'name' => 'color',
	 *     'type' => 'select',
	 *     'label' => 'Your favorite color',
	 *     'description' => 'Select your favorite color or leave blank if you do not have one.',
	 *     'columnWidth' => 50,
	 *     'options' => array(
	 *        'red' => 'Brilliant Red',
	 *        'orange' => 'Citrus Orange',
	 *        'blue' => 'Sky Blue'
	 *     )
	 *   ),
	 *   // alternative usage: associative array where name attribute is specified as key
	 *   'my_fieldset' => array(
	 *     'type' => 'fieldset',
	 *     'label' => 'My Fieldset',
	 *     'children' => array(
	 *       'some_field' => array(
	 *         'type' => 'text',
	 *         'label' => 'Some Field',
	 *       )
	 *     )
	 * );
	 * // Note: you may alternatively use associative arrays where the keys are assumed to 
	 * // be the 'name' attribute.See the last item 'my_fieldset' above for an example. 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param array $a Array of Inputfield definitions
	 * @param InputfieldWrapper|null $inputfields Specify the wrapper you want them added to, or omit to use current.
	 * @return $this
	 *
	 */
	public function importArray(array $a, ?InputfieldWrapper $inputfields = null) {
		
		$modules = $this->wire()->modules;
		
		if(is_null($inputfields)) $inputfields = $this; 
		if(!count($a)) return $inputfields;
	
		// if just a single field definition rather than an array of them, normalize to array of array
		$first = reset($a); 
		if(!is_array($first)) $a = array($a); 
		
		foreach($a as $name => $info) {

			if(isset($info['name'])) {
				$name = $info['name'];
				unset($info['name']);
			}

			if(!isset($info['type'])) {
				$this->error("Skipped field '$name' because no 'type' is set");
				continue;
			}

			$type = $info['type'];
			unset($info['type']);
			if(strpos($type, 'Inputfield') !== 0) $type = "Inputfield" . ucfirst($type);
			
			/** @var Inputfield $f */
			$f = $modules->get($type);

			if(!$f) {
				$this->error("Skipped field '$name' because module '$type' does not exist");
				continue;
			}
			
			$f->attr('name', $name);
			
			if($type === 'InputfieldCheckbox') {
				// checkbox behaves a little differently, just like in HTML
				/** @var InputfieldCheckbox $f */
				if(!empty($info['attr']['value'])) {
					$f->attr('value', $info['attr']['value']);
				} else if(!empty($info['value'])) {
					$f->attr('value', $info['value']);
				}
				unset($info['attr']['value'], $info['value']);
				$f->autocheck = 1; // future value attr set triggers checked state
			}

			if(isset($info['attr']) && is_array($info['attr'])) {
				foreach($info['attr'] as $key => $value) {
					$f->attr($key, $value);
				}
				unset($a['attr']);
			}

			foreach($info as $key => $value) {
				if($key == 'children') continue;
				$f->$key = $value;
			}

			if($f instanceof InputfieldWrapper && !empty($info['children'])) {
				$this->importArray($info['children'], $f);
			}

			$inputfields->add($f);
		}

		return $inputfields;
	}

	/**
	 * Populate values for all Inputfields in this wrapper from the given $data object or array.
	 * 
	 * This iterates through every field in this InputfieldWrapper and looks for field names 
	 * that are also present in the given object or array. If present, it uses them to populate
	 * the associated Inputfield. 
	 * 
	 * If given an array, it should be an associative with the field 'name' as the keys and
	 * the field 'value' as the array value, i.e. `['field_name' => 'field_value']`.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param WireData|Wire|ConfigurableModule|array $data
	 * @return array Returns array of field names that were populated
	 * 
	 */
	public function populateValues($data) {
		$populated = array();
		foreach($this->getAll() as $inputfield) {
			/** @var Inputfield $inputfield */
			if($inputfield instanceof InputfieldWrapper) continue; 
			$name = $inputfield->attr('name');
			if(!$name) continue;
			$value = null;
			if(is_array($data)) {
				// array
				$value = isset($data[$name]) ? $data[$name] : null;
			} else if($data instanceof WireData) {
				// WireData object
				$value = $data->data($name);
			} else if(is_object($data)) {
				// Wire or other object with __get() implemented
				$value = $data->$name;
			} 
			if($value === null) continue;
			if($inputfield instanceof InputfieldCheckbox) $inputfield->autocheck = 1; 
			$inputfield->attr('value', $value);
			$populated[$name] = $name;
		}
		return $populated;
	}

	/**
	 * Get an array of all family below this (recursively) for debugging purposes
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function debugMap() {
		$a = array();
		foreach($this as $in) {
			/** @var Inputfield $in */
			$info = array(
				'id' => $in->id, 
				'name' => $in->name, 
				'type' => $in->className(), 
			);
			if($in instanceof InputfieldWrapper) {
				$info['children'] = $in->debugMap();
			}
			$a[] = $info;
		}
		return $a;
	}

	/**
	 * Debug info
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		$info['children'] = $this->debugMap();
		return $info;
	}
	
}
