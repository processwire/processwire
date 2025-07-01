<?php namespace ProcessWire;

/**
 * ProcessWire Inputfield - base class for Inputfield modules.
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 * An Inputfield for an actual form input field widget, and this is provided as the base class
 * for different kinds of form input widgets provided as modules. 
 *
 * The class supports a parent/child hierarchy so that a given Inputfield can contain Inputfields
 * below it. An example would be the relationship between fieldsets and fields in a form. 
 * Parent Inputfields are almost always of type InputfieldWrapper. 
 *
 * An Inputfield is typically associated with a Fieldtype module when used for ProcessWire fields. 
 * Most Inputfields can also be used on their own. 
 *
 * #pw-order-groups attribute-methods,attribute-properties,settings,traversal,labels,appearance,uikit,behavior,other,output,input,states
 * #pw-use-constants
 * #pw-summary Inputfield is the base class for modules that collect user input for fields.
 * #pw-summary-attribute-properties These properties are retrieved or manipulated via the attribute methods above.
 * #pw-summary-textFormat-constants Constants for formats allowed in description, notes, label.
 * #pw-summary-collapsed-constants Constants allowed for the `Inputfield::collapsed` property.
 * #pw-summary-skipLabel-constants Constants allowed for the `Inputfield::skipLabel` property.
 * #pw-summary-renderValue-constants Options for `Inputfield::renderValueFlags` property, applicable `Inputfield::renderValue()` method call.
 * #pw-summary-module Methods primarily of interest during module development. 
 * #pw-summary-uikit Settings for Inputfields recognized and used by AdminThemeUikit. 
 * 
 * #pw-body =
 * ~~~~~
 * // Create an Inputfield
 * $inputfield = $modules->get('InputfieldText');
 * $inputfield->label = 'Your Name';
 * $inputfield->attr('name', 'your_name');
 * $inputfield->attr('value', 'Roderigo');
 * // Add to a $form (InputfieldForm or InputfieldWrapper)
 * $form->add($inputfield); 
 * ~~~~~
 * #pw-body
 * 
 * ATTRIBUTES
 * ==========
 * @property string $name HTML 'name' attribute for Inputfield (required). #pw-group-attribute-properties
 * @property string $id HTML 'id' attribute for the Inputfield (if not yet, determined automatically). #pw-group-attribute-properties
 * @property mixed $value HTML 'value' attribute for the Inputfield. #pw-group-attribute-properties
 * @property string $class HTML 'class' attribute for the Inputfield. #pw-group-attribute-properties
 * 
 * @method string|Inputfield name($name = null) Get or set the name attribute. @since 3.0.110 #pw-group-attribute-methods
 * @method string|Inputfield id($id = null) Get or set the id attribute. @since 3.0.110 #pw-group-attribute-methods
 * @method string|Inputfield class($class = null) Get class attribute or add a class to the class attribute. @since 3.0.110 #pw-group-attribute-methods
 * 
 * LABELS & CONTENT
 * ================
 * @property string $label Primary label text that appears above the input. #pw-group-labels
 * @property string $description Optional description that appears under label to provide more detailed information. #pw-group-labels
 * @property string $notes Optional notes that appear under input area to provide additional notes. #pw-group-labels
 * @property string $detail Optional text details that appear under notes. @since 3.0.140 #pw-group-labels
 * @property string $icon Optional font-awesome icon name to accompany label (excluding the "fa-") part). #pw-group-labels
 * @property string $requiredLabel Optional custom label to display when missing required value. @since 3.0.98 #pw-group-labels 
 * @property string $head Optional text that appears below label but above description (only used by some Inputfields). #pw-internal
 * @property string $tabLabel Label for tab if Inputfield rendered in its own tab via Inputfield::collapsedTab* setting. @since 3.0.201 #pw-group-labels
 * @property string|null $prependMarkup Optional markup to prepend to the Inputfield content container. #pw-group-other
 * @property string|null $appendMarkup Optional markup to append to the Inputfield content container. #pw-group-other
 * @property string|null $footerMarkup Optional markup to add to the '.Inputfield' container, after '.InputfieldContent'. @since 3.0.241 #pw-advanced
 * 
 * @method string|Inputfield label($label = null) Get or set the 'label' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield description($description = null) Get or set the 'description' property via method. @since 3.0.110  #pw-group-labels
 * @method string|Inputfield notes($notes = null) Get or set the 'notes' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield icon($icon = null) Get or set the 'icon' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield requiredLabel($requiredLabel = null) Get or set the 'requiredLabel' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield head($head = null) Get or set the 'head' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield prependMarkup($markup = null) Get or set the 'prependMarkup' property via method. @since 3.0.110 #pw-group-labels
 * @method string|Inputfield appendMarkup($markup = null) Get or set the 'appendMarkup' property via method. @since 3.0.110 #pw-group-labels
 * 
 * APPEARANCE
 * ==========
 * @property int $collapsed Whether the field is collapsed or visible, using one of the "collapsed" constants. #pw-group-appearance
 * @property string $showIf Optional conditions under which the Inputfield appears in the form (selector string).  #pw-group-appearance
 * @property int $columnWidth Width of column for this Inputfield 10-100 percent. 0 is assumed to be 100 (default). #pw-group-appearance
 * @property int $skipLabel Skip display of the label? See the "skipLabel" constants for options. #pw-group-appearance
 * 
 * @method int|Inputfield collapsed($collapsed = null) Get or set collapsed property via method. @since 3.0.110 #pw-group-appearance
 * @method string|Inputfield showIf($showIf = null) Get or set showIf selector property via method. @since 3.0.110 #pw-group-appearance
 * @method int|Inputfield columnWidth($columnWidth = null) Get or set columnWidth property via method. @since 3.0.110 #pw-group-appearance
 * @method int|Inputfield skipLabel($skipLabel = null) Get or set the skipLabel constant property via method. @since 3.0.110 #pw-group-appearance
 *
 * UIKIT THEME 
 * ===========
 * @property bool|string $themeOffset Offset/margin for Inputfield, one of 's', 'm', or 'l'. #pw-group-uikit
 * @property string $themeBorder Border style for Inputfield, one of 'none', 'card', 'hide' or 'line'. #pw-group-uikit
 * @property string $themeInputSize Input size height/font within Inputfield, one of 's', 'm', or 'l'. #pw-group-uikit
 * @property string $themeInputWidth Input width for text-type inputs, one of 'xs', 's', 'm', 'l', or 'f' (for full-width). #pw-group-uikit
 * @property string $themeColor Color theme for Inputfield, one of 'primary', 'secondary', 'warning', 'danger', 'success', 'highlight', 'none'. #pw-group-uikit
 * @property bool $themeBlank Makes <input> element display with no minimal container / no border when true. #pw-group-uikit
 * 
 * SETTINGS & BEHAVIOR
 * ===================
 * @property int|bool $required Set to true (or 1) to make input required, or false (or 0) to make not required (default=0). #pw-group-behavior
 * @property string $requiredIf Optional conditions under which input is required (selector string). #pw-group-behavior
 * @property int|bool|null $requiredAttr Use HTML5 “required” attribute when used by Inputfield and $required is true? Default=null. #pw-group-behavior
 * @property InputfieldWrapper|null $parent The parent InputfieldWrapper for this Inputfield or null if not set. #pw-internal
 * @property null|bool|Fieldtype $hasFieldtype The Fieldtype using this Inputfield, or boolean false when known not to have a Fieldtype, or null when not known. #pw-group-other
 * @property null|Field $hasField The Field object associated with this Inputfield, or null when not applicable or not known. #pw-group-other
 * @property null|Page $hasPage The Page object associated with this Inputfield, or null when not applicable or not known. #pw-group-other
 * @property null|Inputfield $hasInputfield If this Inputfield is owned/managed by another (other than parent/child relationship), it may be set here. @since 3.0.176 #pw-group-other 
 * @property bool|null $useLanguages When multi-language support active, can be set to true to make it provide inputs for each language, where supported (default=false). #pw-group-behavior #pw-group-languages
 * @property null|bool|int $entityEncodeLabel Set to boolean false to specifically disable entity encoding of field header/label (default=true). #pw-group-output
 * @property null|bool $entityEncodeText Set to boolean false to specifically disable entity encoding for other text: description, notes, etc. (default=true). #pw-group-output
 * @property int $renderFlags Options that can be applied to render, see "render*" constants (default=0). @since 3.0.204 #pw-group-output 
 * @property int $renderValueFlags Options that can be applied to renderValue mode, see "renderValue" constants (default=0). #pw-group-output
 * @property string $wrapClass Optional class name (CSS) to apply to the HTML element wrapping the Inputfield. #pw-group-other
 * @property string $headerClass Optional class name (CSS) to apply to the InputfieldHeader element #pw-group-other
 * @property string $contentClass Optional class name (CSS) to apply to the InputfieldContent element #pw-group-other
 * @property string $addClass Formatted class string letting you add class to any of the above (see addClass method). @since 3.0.204 #pw-group-other 
 * @property int|null $textFormat Text format to use for description/notes text in Inputfield (see textFormat constants) #pw-group-output
 * 
 * @method string|Inputfield required($required = null) Get or set required state. @since 3.0.110 #pw-group-behavior
 * @method string|Inputfield requiredIf($requiredIf = null) Get or set required-if selector. @since 3.0.110 #pw-group-behavior
 *
 * @method string|Inputfield wrapClass($class = null) Get wrapper class attribute or add a class to it. @since 3.0.110 #pw-group-other
 * @method string|Inputfield headerClass($class = null) Get header class attribute or add a class to it. @since 3.0.110 #pw-group-other
 * @method string|Inputfield contentClass($class = null) Get content class attribute or add a class to it. @since 3.0.110 #pw-group-other
 * 
 * MULTI-LANGUAGE METHODS (requires LanguageSupport module to be installed)
 * ======================
 * @method void setLanguageValue($language, $value) Set language value for Inputfield that supports it. Requires LanguageSupport module. $language can be Language, id (int) or name (string). @since 3.0.238 #pw-group-languages 
 * @method string|mixed getLanguageValue($language) Get language value for Inputfield that supports it. Requires LanguageSupport module. $language can be Language, id (int) or name (string). @since 3.0.238  #pw-group-languages 
 * 
 * HOOKABLE METHODS
 * ================
 * @method string render()
 * @method string renderValue()
 * @method void renderReadyHook(Inputfield $parent, $renderValueMode)
 * @method Inputfield processInput(WireInputData $input)
 * @method InputfieldWrapper getConfigInputfields()
 * @method array getConfigArray()
 * @method array getConfigAllowContext(Field $field)
 * @method array exportConfigData(array $data) #pw-internal
 * @method array importConfigData(array $data) #pw-internal
 * 
 */
abstract class Inputfield extends WireData implements Module {

	/**
	 * Not collapsed (display as "open", default)
	 * #pw-group-collapsed-constants
	 * 
	 */	
	const collapsedNo = 0;

	/**
	 * Collapsed unless opened
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedYes = 1; 

	/**
	 * Collapsed only when blank
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedBlank = 2;

	/**
	 * Hidden, not rendered in the form at all
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedHidden = 4;

	/**
	 * Collapsed only when populated
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedPopulated = 5;
	
	/**
	 * Not collapsed, value visible but not editable
	 * #pw-group-collapsed-constants
	 *
	 */
	const collapsedNoLocked = 6;

	/**
	 * Collapsed when blank, value visible but not editable
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedBlankLocked = 7;

	/**
	 * Collapsed unless opened (value becomes visible but not editable)
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedYesLocked = 8;

	/**
	 * Same as collapsedYesLocked, for backwards compatibility
	 * #pw-internal
	 * 
	 */
	const collapsedLocked = 8;

	/**
	 * Never collapsed, and not collapsible
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedNever = 9;

	/**
	 * Collapsed and dynamically loaded by AJAX when opened
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedYesAjax = 10; 

	/**
	 * Collapsed only when blank, and dynamically loaded by AJAX when opened
	 * #pw-group-collapsed-constants
	 * 
	 */
	const collapsedBlankAjax = 11;

	/**
	 * Collapsed into a separate tab
	 * #pw-group-collapsed-constants
	 * @since 3.0.201
	 *
	 */
	const collapsedTab = 20;
	
	/**
	 * Collapsed into a separate tab and AJAX loaded
	 * #pw-group-collapsed-constants
	 * @since 3.0.201
	 *
	 */
	const collapsedTabAjax = 21;

	/**
	 * Collapsed into a separate tab and locked (not editable)
	 * #pw-group-collapsed-constants
	 * @since 3.0.201
	 *
	 */
	const collapsedTabLocked = 22;
	
	/**
	 * Don't skip the label (default)
	 * #pw-group-skipLabel-constants
	 *
	 */
	const skipLabelNo = false;

	/**
	 * Don't use a "for" attribute with the <label>
	 * #pw-group-skipLabel-constants
	 *
	 */
	const skipLabelFor = true;
	
	/**
	 * Don't show a visible header (likewise, do not show the label)
	 * #pw-group-skipLabel-constants
	 *
	 */
	const skipLabelHeader = 2;
	
	/**
	 * Skip rendering of the label when it is blank
	 * #pw-group-skipLabel-constants
	 *
	 */
	const skipLabelBlank = 4;

	/**
	 * Do not render any markup for the header/label at all 
	 * #pw-group-skipLabel-constants
	 * @since 3.0.139
	 * 
	 */
	const skipLabelMarkup = 8;

	/**
	 * Plain text: no type of markdown or HTML allowed
	 * #pw-group-textFormat-constants
	 * 
	 */
	const textFormatNone = 2;

	/**
	 * Only allow basic/inline markdown, and no HTML (default)
	 * #pw-group-textFormat-constants
	 *
	 */
	const textFormatBasic = 4;
	
	/**
	 * Full markdown support with HTML also allowed
	 * #pw-group-textFormat-constants
	 *
	 */
	const textFormatMarkdown = 8;
	
	/**
	 * Render flags: place first in render
	 * #pw-group-render-constants
	 *
	 */
	const renderFirst = 1;

	/**
	 * Render flags: place last in render
	 * #pw-group-render-constants
	 *
	 */
	const renderLast = 2;

	/**
	 * Render only the minimum output when in "renderValue" mode.
	 * #pw-group-renderValue-constants
	 * 
	 */
	const renderValueMinimal = 2;
	
	/**
	 * Indicates a parent InputfieldWrapper is not required when rendering value. 
	 * #pw-group-renderValue-constants
	 *
	 */
	const renderValueNoWrap = 4;
	
	/**
	 * If there are multiple items, only render the first (where supported by the Inputfield). 
	 * #pw-group-renderValue-constants
	 *
	 */
	const renderValueFirst = 8;

	/**
	 * The total number of Inputfield instances, kept as a way of generating unique 'id' attributes
	 * 
	 * #pw-internal
	 *
	 */
	static protected $numInstances = 0;

	/**
	 * Custom html for Inputfield output, if supported, and default overridden
	 * 
	 * In the string specify {attr} to substitute a string of all attributes, or to
	 * specify attributes individually, specify name="{name}" replacing "name" in both
	 * cases with the actual name of the attribute. 
	 * 
	 * @var string
	 * 
	private $html = '';
	 */

	/**
	 * Attributes specified for the HTML output, like class, rows, cols, etc. 
	 *
	 */
	protected $attributes = array();

	/**
	 * Attributes that accompany this Inputfield's wrapping element
	 * 
	 * @var array
	 * 
	 */
	protected $wrapAttributes = array();

	/**
	 * The parent Inputfield, if applicable
	 *
	 */
	protected $parent = null; 

	/**
	 * The default ID attribute assigned to this field
	 *
	 */
	protected $defaultID = '';

	/**
	 * Whether this Inputfield is editable
	 * 
	 * When false, its processInput method won't be called by InputfieldWrapper's processInput
	 * 
	 * @var bool
	 * 
	 */
	protected $editable = true;

	/**
	 * Header icon definitions
	 * 
	 * @var array 
	 * 
	 */
	protected $headerActions = array();

	/**
	 * Construct the Inputfield, setting defaults for all properties
	 *
	 */
	public function __construct() {

		self::$numInstances++; 

		$this->set('label', ''); // primary clickable label
		$this->set('description', ''); // descriptive copy, below label
		$this->set('icon', ''); // optional icon name to accompany label
		$this->set('notes', ''); // highlighted descriptive copy, below output of input field
		$this->set('detail', ''); // text details that appear below notes
		$this->set('head', ''); // below label, above description
		$this->set('tabLabel', ''); // alternate label for tab when Inputfield::collapsedTab* in use
		$this->set('required', 0); // set to 1 to make value required for this field
		$this->set('requiredIf', ''); // optional conditions to make it required
		$this->set('collapsed', ''); // see the collapsed* constants at top of class (use blank string for unset value)
		$this->set('showIf', ''); // optional conditions selector
		$this->set('columnWidth', ''); // percent width of the field. blank or 0 = 100.
		$this->set('skipLabel', self::skipLabelNo); // See the skipLabel constants
		$this->set('wrapClass', ''); // optional class to apply to the Inputfield wrapper (contains InputfieldHeader + InputfieldContent)
		$this->set('headerClass', ''); // optional class to apply to InputfieldHeader wrapper
		$this->set('contentClass', ''); // optional class to apply to InputfieldContent wrapper
		$this->set('addClass', ''); // space-separated classes to add, optionally specifying element (see addClassString method)
		$this->set('textFormat', self::textFormatBasic); // format applied to description and notes
		$this->set('renderFlags', 0);  // See render* constants
		$this->set('renderValueFlags', 0); // see renderValue* constants, applicable to renderValue mode only
		$this->set('prependMarkup', ''); // markup to prepend to InputfieldContent output
		$this->set('appendMarkup', ''); // markup to append to InputfieldContent output
		$this->set('footerMarkup', ''); // markup to add to end of Inputfield output

		// default ID attribute if no 'id' attribute set
		$this->defaultID = $this->className() . self::$numInstances; 

		$this->setAttribute('id', $this->defaultID); 
		$this->setAttribute('class', ''); 
		$this->setAttribute('name', ''); 

		$value = $this instanceof InputfieldHasArrayValue ? array() : null;
		$this->setAttribute('value', $value); 
		
		parent::__construct();
	}

	/**
	 * Per the Module interface, init() is called after any configuration data has been populated to the Inputfield, but before render. 
	 * 
	 * #pw-group-module
	 *
	 */
	public function init() { }

	/**
	 * Per the Module interface, this method is called when this Inputfield is installed
	 * 
	 * #pw-group-module
	 * 
	 */
	public function ___install() { }

	/**
	 * Per the Module interface, uninstall() is called when this Inputfield is uninstalled
	 * 
	 * #pw-group-module
	 *
	 */
	public function ___uninstall() { }

	/**
	 * Multiple instances of a given Inputfield may be needed
	 * 
	 * #pw-internal
	 *
	 */
	public function isSingular() {
		return false; 
	}

	/**
	 * Inputfields are not loaded until requested
	 * 
	 * #pw-internal
	 *
	 */
	public function isAutoload() {
		return false; 
	}

	/**
	 * Set a property or attribute to the Inputfield
	 * 
	 * - Use this for setting properties like parent, collapsed, required, columnWidth, etc. 
	 * - You can also set properties directly via `$inputfield->property = $value`.
	 * - If setting an attribute (like name, id, etc.) this will work, but it is preferable to use the `Inputfield::attr()` method. 
	 * - If setting any kind of "class" it is preferable to use the `Inputfield::addClass()` method. 
	 * 
	 * #pw-group-settings
	 * 
	 * @param string $key Name of property to set
	 * @param mixed $value Value of property
	 * @return Inputfield|WireData
	 *
	 */
	public function set($key, $value) {
		
		if($key === 'parent') { 
			if($value instanceof InputfieldWrapper) return $this->setParent($value);

		} else if($key === 'collapsed') {
			if($value === true) $value = self::collapsedYes; 
			$value = (int) $value;
			
		} else if(array_key_exists($key, $this->attributes) && $key !== 'required') {
			return $this->setAttribute($key, $value);
			
		} else if($key === 'required' && $value && !is_object($value)) {
			$this->addClass('required');
			
		} else if($key === 'columnWidth') {
			$value = (int) $value; 
			if($value < 10 || $value > 99) $value = '';
			
		} else if($key === 'addClass') {
			if(is_string($value) && !ctype_alnum($value)) {
				$test = str_replace(array(' ', ':', ',', '-', '+', '=', '!', '_', '.', '@', "\n"), '', $value);
				if(!ctype_alnum($test)) $value = preg_replace('/[^-+_:=@!,. a-zA-Z0-9\n]/', '', $value);
			}
			$this->addClass($value);
		}
		
		return parent::set($key, $value); 
	}

	/**
	 * Get a property or attribute from the Inputfield
	 *
	 * - This can also be accessed directly, i.e. `$value = $inputfield->property;`. 
	 * 
	 * - For getting attribute values, this will work, but it is preferable to use the `Inputfield::attr()` method. 
	 * 
	 * - For getting non-attribute values that have potential name conflicts with attributes (or just as a 
	 *   reliable alternative), use the `Inputfield::getSetting()` method instead, which excludes the possibility
	 *   of overlap with attributes. 
	 * 
	 * #pw-group-settings
	 *
	 * @param string $key Name of property or attribute to retrieve. 
	 * @return mixed|null Value of property or attribute, or NULL if not found. 
	 *
	 */ 
	public function get($key) {	
		if($key === 'label') { 
			$value = parent::get('label');
			if(strlen($value)) return $value;
			if($this->skipLabel & self::skipLabelBlank) return '';
			return $this->attributes['name']; 
		} 
		if($key === 'description' || $key === 'notes') return parent::get($key);
		if($key === 'name' || $key === 'value' || $key === 'id') return $this->getAttribute($key);
		if($key === 'attributes') return $this->attributes; 
		if($key === 'parent') return $this->parent; 
		if(($value = $this->wire($key)) !== null) return $value; 
		if(array_key_exists($key, $this->attributes)) return $this->attributes[$key]; 
		return parent::get($key); 
	}

	/**
	 * Gets a setting (or API variable) from the Inputfield, while ignoring attributes.
	 *
	 * This is good to use in cases where there are potential name conflicts, like when there is a field literally 
	 * named "collapsed" or "required".
	 * 
	 * #pw-group-settings
	 * 
	 * @param string $key Name of setting or API variable to retrieve.
	 * @return mixed Value of setting or API variable, or NULL if not found. 
	 *
	 */
	public function getSetting($key) {
		return parent::get($key); 
	}

	/**
	 * Set the parent (InputfieldWrapper) of this Inputfield.
	 * 
	 * #pw-group-traversal
	 *
	 * @param InputfieldWrapper $parent
	 * @return $this
	 * @see Inputfield::getParent()
	 *
	 */
	public function setParent(InputfieldWrapper $parent) {
		$this->parent = $parent; 
		return $this; 
	}

	/**
	 * Unset any previously set parent
	 * 
	 * #pw-internal
	 * @return $this
	 * 
	 */
	public function unsetParent() {
		$this->parent = null;
		return $this;
	}

	/**
	 * Get this Inputfield’s parent InputfieldWrapper, or NULL if it doesn’t have one.
	 * 
	 * #pw-group-traversal
	 *
	 * @return InputfieldWrapper|null
	 * @see Inputfield::setParent()
	 *
	 */
	public function getParent() {
		return $this->parent; 
	}

	/**
	 * Get array of all parents of this Inputfield.
	 * 
	 * #pw-group-traversal
	 * 
	 * @return array of InputfieldWrapper elements.
	 * @see Inputfield::getParent(), Inputfield::setParent()
	 * 
	 */
	public function getParents() {
		/** @var InputfieldWrapper|null $parent */
		$parent = $this->getParent();
		if(!$parent) return array();
		$parents = array($parent);
		foreach($parent->getParents() as $p) $parents[] = $p;
		return $parents; 
	}

	/**
	 * Get or set parent of Inputfield 
	 * 
	 * This convenience method performs the same thing as getParent() and setParent().
	 * 
	 * To get parent, specify no arguments. It will return null if no parent assigned, or an 
	 * InputfieldWrapper instance of the parent. 
	 * 
	 * To set parent, specify an InputfieldWrapper for the $parent argument. The return value
	 * is the current Inputfield for fluent interface.
	 * 
	 * #pw-group-traversal
	 * 
	 * @param null|InputfieldWrapper $parent
	 * @return null|Inputfield|InputfieldWrapper
	 * @since 3.0.110
	 * 
	 */
	public function parent($parent = null) {
		if($parent === null) {
			return $this->getParent();
		} else {
			return $this->setParent($parent);
		}
	}

	/**
	 * Get array of all parents of this Inputfield
	 * 
	 * This is identical to and an alias of the getParents() method.
	 * 
	 * #pw-group-traversal
	 * 
	 * @return array
	 * @since 3.0.110
	 * 
	 */
	public function parents() {
		return $this->getParents();
	}

	/**
	 * Get the root parent InputfieldWrapper element (farthest parent, commonly InputfieldForm)
	 * 
	 * This returns null only if Inputfield it is called from has not yet been added to an InputfieldWrapper.
	 * 
	 * #pw-group-traversal
	 *
	 * @return InputfieldForm|InputfieldWrapper|null
	 * @since 3.0.106
	 * 
	 */
	public function getRootParent() {
		$parents = $this->getParents();
		return count($parents) ? end($parents) : null;
	}

	/**
	 * Get the InputfieldForm element that contains this field or null if not yet defined
	 * 
	 * This is the same as the `getRootParent()` method except that it returns null if root parent 
	 * is not an InputfieldForm. 
	 * 
	 * #pw-group-traversal
	 * 
	 * @return InputfieldForm|null
	 * @since 3.0.106
	 * 
	 */
	public function getForm() {
		$form = $this instanceof InputfieldForm ? $this : $this->getRootParent();
		return ($form instanceof InputfieldForm ? $form : null);
	}

	/**
	 * Set an attribute 
	 *
	 * - For most public API use, you might consider using the shorter `Inputfield::attr()` method instead. 
	 * 
	 * - When setting the `class` attribute it is preferable to use the `Inputfield::addClass()` method. 
	 * 
	 * - The `$key` argument may contain multiple keys by being specified as an array, or by being a string with multiple 
	 *   keys separated by "+" or "|", for example: `$inputfield->setAttribute("id+name", "template")`.
	 * 
	 * - If the `$value` argument is an array, it will instruct the attribute to hold multiple values. 
	 *   Future calls to setAttribute() will enforce the array type for that attribute. 
	 * 
	 * ~~~~~
	 * // Set the name attribute
	 * $inputfield->setAttribute('name', 'my_field_name'); 
	 * 
	 * // Set the name and id attributes at the same time
	 * $inputfield->setAttribute('name+id', 'my_field_name'); 
	 * ~~~~~
	 * 
	 * #pw-internal Giving public API preference to the attr() method instead
	 *
	 * @param string|array $key Specify one of the following:
	 *   - Name of attribute (string)
	 *   - Names of attributes (array)
	 *   - String with names of attributes split by "+" or "|"
	 * @param string|int|array|bool $value Value of attribute to set. 
	 * @return $this
	 * @see Inputfield::attr(), Inputfield::removeAttr(), Inputfield::addClass()
	 *
	 */
	public function setAttribute($key, $value) {
		
		if(is_array($key)) {
			$keys = $key;
		} else if(strpos($key, '+') !== false) {
			$keys = explode('+', $key);
		} else if(strpos($key, '|') !== false) {
			$keys = explode('|', $key);
		} else {
			$keys = array($key);
		}
	
		if(is_bool($value) && !in_array($key, array('name', 'id', 'class', 'value', 'type'))) {
			$booleanValue = $value;
		} else {
			$booleanValue = null;
		}

		foreach($keys as $key) {
			
			if(!ctype_alpha("$key")) $key = $this->wire('sanitizer')->attrName($key);
			if(empty($key)) continue;
		
			if($booleanValue !== null) {
				if($booleanValue === true) {
					// boolean true attribute sets value as attribute name (i.e. checked='checked')
					$value = $key; 
				} else if($booleanValue === false) {
					// boolean false attribute implies remove attribute
					$this->removeAttribute($key);
					continue;
				}
			}
			
			if($key === 'name' && strlen($value)) {
				$idAttr = $this->getAttribute('id'); 
				$nameAttr = $this->getAttribute('name'); 
				if($idAttr == $this->defaultID || $idAttr == $nameAttr || $idAttr == "Inputfield_$nameAttr") {
					// formulate an ID attribute that consists of the className and name attribute
					$this->setAttribute('id', "Inputfield_$value");
				}
			}

			if(!array_key_exists($key, $this->attributes)) {
				$this->attributes[$key] = '';
			}

			if(is_array($this->attributes[$key]) && !is_array($value)) {

				// If the attribute is already established as an array, then we'll keep it as an array
				// and stack any newly added values into the array.
				// Examples would be stacking of class attributes, or stacking of value attributes for 
				// an Inputfield that carries multiple values
				
				$this->attributes[$key][] = $value; 

			} else {
				$this->attributes[$key] = $value; 
			}
		}

		return $this; 
	}

	/**
	 * Remove an attribute
	 * 
	 * #pw-group-attribute-methods
	 *
	 * @param string $key Name of attribute to remove.
	 * @return $this
	 * @see Inputfield::attr(), Inputfield::removeClass()
	 *
	 */ 
	public function removeAttr($key) {
		unset($this->attributes[$key]); 
		return $this;
	}

	/**
	 * Remove an attribute (alias)
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return $this
	 *
	 */ 
	public function removeAttribute($key) {
		return $this->removeAttr($key);
	}

	/**
	 * Set multiple attributes at once with an associative array.
	 * 
	 * #pw-internal
	 * 
	 * @param array $attributes Associative array of attributes to set. 
	 * @return $this
	 *
	 */
	public function setAttributes(array $attributes) {
		foreach($attributes as $key => $value) $this->setAttribute($key, $value); 
		return $this; 
	}

	/**
	 * Get or set an attribute (or multiple attributes)
	 *
	 * - To get an attribute call this method with just the attribute name. 
	 * - To set an attribute call this method with the attribute name and value to set.
	 * - You can also set multiple attributes at once, see examples below. 
	 * - To get all attributes, just specify boolean true as first argument (since 3.0.16).
	 * 
	 * ~~~~~
	 * // Get the "value" attribute
	 * $value = $inputfield->attr('value');
	 * 
	 * // Set the "value" attribute
	 * $inputfield->attr('value', 'Foo and Bar'); 
	 * 
	 * // Set multiple attributes
	 * $inputfield->attr([
	 *   'name' => 'foobar', 
	 *   'value' => 'Foo and Bar',
	 *   'class' => 'foo-bar', 
	 * ]);
	 * 
	 * // Set name and id attribute to "foobar"
	 * $inputfield->attr("name+id", "foobar"); 
	 * 
	 * // Get all attributes in associative array (since 3.0.16)
	 * $attrs = $inputfield->attr(true); 
	 * ~~~~~
	 * 
	 * #pw-group-attribute-methods
	 * 
	 * @param string|array|bool $key Specify one of the following: 
	 *   - Name of attribute to get (if getting an attribute). 
	 *   - Name of attribute to set (if setting an attribute, and also specifying a value). 
	 *   - Aassociative array to set multiple attributes. 
	 *   - String with attributes split by "+" or "|" to set them all to have the same value. 
	 *   - Specify boolean true to get all attributes in an associative array.
	 * @param string|int|bool|null $value Value to set (if setting), omit otherwise. 
	 * @return Inputfield|array|string|int|object|float If setting an attribute, it returns this instance. If getting an attribute, the attribute is returned. 
	 * @see Inputfield::removeAttr(), Inputfield::addClass(), Inputfield::removeClass()
	 *
	 */
	public function attr($key, $value = null) {
		if(is_null($value)) {
			if(is_array($key)) {
				return $this->setAttributes($key);
			} else if(is_bool($key)) {
				return $this->getAttributes();
			} else {
				return $this->getAttribute($key);
			}
		}
		return $this->setAttribute($key, $value); 
	}

	/**
	 * Shortcut for getting or setting “value” attribute 
	 * 
	 * When setting a value, it returns $this (for fluent interface).
	 * 
	 * ~~~~~
	 * $value = $inputfield->val(); * // Getting
	 * $inputfield->val('foo'); * // Setting
	 * ~~~~~
	 * 
	 * @param string|null $value
	 * @return string|int|float|array|object|Wire|WireData|WireArray|Inputfield
	 * 
	 */
	public function val($value = null) {
		if($value === null) return $this->getAttribute('value');
		return $this->setAttribute('value', $value);
	}
	
	/**
	 * If method call resulted in no handler, this hookable method is called.
	 * 
	 * We use this to allow for attributes and properties to be set via method, useful primarily
	 * for fluent interface calls. 
	 *
	 * #pw-internal
	 *
	 * @param string $method Requested method name
	 * @param array $arguments Arguments provided
	 * @return null|mixed Return value of method (if applicable)
	 * @throws WireException
	 * @since 3.0.110
	 *
	 */
	protected function ___callUnknown($method, $arguments) {
		$arg = isset($arguments[0]) ? $arguments[0] : null;
		if(isset($this->attributes[$method]) && $method !== 'required') {
			// get or set an attribute
			return $arg === null ? $this->getAttribute($method) : $this->setAttribute($method, $arg);
		} else if(($value = $this->getSetting($method)) !== null) {
			// get or set a setting
			if($arg === null) return $value;
			if(stripos($method, 'class') !== false) { 
				// i.e. class, wrapClass, contentClass, etc.
				return $this->addClass($arg, $method);
			} else {
				return $this->set($method, $arg);
			}
		}
		return parent::___callUnknown($method, $arguments);
	}
	
	/**
	 * Get all attributes specified for this Inputfield
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 *
	 */
	public function getAttributes() {
		$attrs = $this->attributes;
		if(!isset($attrs['required']) && $this->getSetting('required') && $this->getSetting('requiredAttr')) { 
			if(!$this->getSetting('showIf') && !$this->getSetting('requiredIf')) {
				$attrs['required'] = 'required';
			}
		}
		return $attrs; 
	}

	/**
	 * Get a specified attribute for this Inputfield
	 * 
	 * #pw-internal Public API should use the attr() method instead, but this is here for consistency with setAttribute()
	 * 
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function getAttribute($key) {
		return isset($this->attributes[$key]) ? $this->attributes[$key] : null; 
	}

	/**
	 * Get or set attribute for the element wrapping this Inputfield
	 * 
	 * Use this method when you need to assign some attribute to the outer wrapper of the Inputfield. 
	 * 
	 * #pw-group-attribute-methods
	 * 
	 * @param string|null|bool $key Specify one of the following for $key: 
	 *   - Specify string containing name of attribute to set.
	 *   - Omit (or null or true) to get all wrap attributes as associative array.
	 * @param string|null|bool $value Specify one of the following for $value:
	 *   - Omit if getting an attribute. 
	 *   - Value to set for $key of setting. 
	 *   - Boolean false to remove the attribute specified for $key. 
	 * @return Inputfield|string|array|null Returns one of the following: 
	 *   - If getting, returns attribute value of NULL if not present. 
	 *   - If setting, returns $this.
	 * @see Inputfield::attr(), Inputfield::addClass()
	 * 
	 */
	public function wrapAttr($key = null, $value = null) {
		if(is_null($value)) {
			if(is_null($key) || is_bool($key)) {
				return $this->wrapAttributes;
			} else {
				return isset($this->wrapAttributes[$key]) ? $this->wrapAttributes[$key] : null;
			}
		} else if($value === false) {
			unset($this->wrapAttributes[$key]);
			return $this;
		} else {
			if(strlen($key)) $this->wrapAttributes[$key] = $value;
			return $this;
		}
	}

	/**
	 * Add a class or classes to this Inputfield (or a wrapping element)
	 * 
	 * If given a class name that’s already present, it won’t be added again. 
	 * 
	 * ~~~~~
	 * // Add class "foobar" to input element
	 * $inputfield->addClass('foobar'); 
	 * 
	 * // Add three classes to input element
	 * $inputfield->addClass('foo bar baz'); 
	 * 
	 * // Add class "foobar" to .Inputfield wrapping element
	 * $inputfield->addClass('foobar', 'wrapClass'); 
	 * 
	 * // Add classes while specifying Inputfield element (3.0.204+)
	 * $inputfield->addClass('wrap:card, header:card-header, content:card-body'); 
	 * ~~~~~
	 * 
	 * **Formatted string option (3.0.204+):**  
	 * Classes can be added by formatted string that dictates what Inputfield element they 
	 * should be added to, in the format `element:classNames` like in this example below: 
	 * ~~~~~
	 * wrap:card card-default
	 * header:card-header
	 * content:card-body
	 * input:form-input input-checkbox
	 * ~~~~~
	 * Each line represents a group containing an element name and one or more space-separated
	 * classes. Groups may be separated by newline (like above) or with a comma. The element
	 * name may be any one of the following:
	 *
	 *  - `wrap`: The .Inputfield element that wraps the header and content
	 *  - `header`: The .InputfieldHeader element, typically a `<label>`.
	 *  - `content`: The .InputfieldContent element that wraps the input(s), typically a `<div>`.
	 *  - `input`: The primary `<input>` element(s) that accept input for the Inputfield.
	 *  - `class`: This is the same as the 'input' type, just an alias.
	 *
	 * Class names prefixed with a minus sign i.e. `-class` will be removed rather than added.
	 *
	 * #pw-group-attribute-methods
	 * 
	 * @param string|array $class Specify one of the following:
	 *   - Class name you want to add.
	 *   - Multiple space-separated class names you want to add.
	 *   - Array of class names you want to add (since 3.0.16).
	 *   - Formatted string of classes as described in method description (since 3.0.204+).
	 * @param string $property Optionally specify the type of class you want to add: 
	 *   - Omit for the default (which is "class"). 
	 *   - `class` (string): Add class to the input element (or whatever the Inputfield default is). 
	 *   - `wrapClass` (string): Add class to ".Inputfield" wrapping element, the most outer level element used for this Inputfield. 
	 *   - `headerClass` (string): Add class to ".InputfieldHeader" label element. 
	 *   - `contentClass` (string): Add class to ".InputfieldContent" wrapping element. 
	 *   - Or some other named class attribute designated by a descending Inputfield.
	 *   - You can optionally omit the `Class` suffix in 3.0.204+, i.e. `wrap` rather than `wrapClass`. 
	 * @return $this
	 * @see Inputfield::hasClass(), Inputfield::removeClass()
	 * 
	 */
	public function addClass($class, $property = 'class') {

		$force = strpos($property, '=') === 0; // force set, skip processing by addClassString
		if($force) $property = ltrim($property, '='); 
		
		if(is_string($class) && !ctype_alnum($class) && !$force) { 
			if(strpos($class, ':') || strpos($class, "\n") || strpos($class, ",")) {
				return $this->addClassString($class, $property);
			}
		}

		$property = $this->getClassProperty($property);
		$classes = $this->getClassArray($property, true);
		
		// addClasses is array of classes being added
		$addClasses = is_array($class) ? $class : explode(' ', $class); 
	
		// add to $classes array
		foreach($addClasses as $addClass) {
			$addClass = trim($addClass); 
			if(strlen($addClass)) $classes[$addClass] = $addClass;
		}
		
		// convert back to string
		$value = trim(implode(' ', $classes)); 
	
		// set back to Inputfield
		if($property === 'class') {
			$this->attributes['class'] = $value;
		} else {
			$this->set($property, $value); 
		}
		
		return $this;
	}

	/**
	 * Add class(es) by formatted string that lets you specify where class should be added
	 * 
	 * To use this in the public API use `addClass()` method or set the `addClass` property
	 * with a formatted string value as indicated here. 
	 * 
	 * Allows for setting via formatted string like:
	 * ~~~~~
	 * wrap:card card-default
	 * header:card-header
	 * content:card-body
	 * input:form-input input-checkbox
	 * ~~~~~
	 * Each line represents a group containing a element type, colon, and one or more space-
	 * separated classes. Groups may be separated by newline (like above) or with a comma. 
	 * The element type may be any one of the following: 
	 * 
	 *  - `wrap`: The .Inputfield element that wraps the header and content
	 *  - `header`: The .InputfieldHeader element, typically a `<label>`. 
	 *  - `content`: The .InputfieldContent element that wraps the input(s), typically a `<div>`.
	 *  - `input`: The primary `<input>` element(s) that accept input for the Inputfield.
	 *  - `class`: This is the same as the 'input' type, just an alias. 
	 *  - `+foo`: Force adding your own new element type (i.e. “foo”) that is not indicated above.
	 * 
	 * Class names prefixed with a minus sign i.e. `-class` will be removed rather than added.
	 * 
	 * A string like `hello:world` where `hello` is not one of those element types listed above,
	 * and is not prefixed with a plus sign `+`, will be added as a literal class name with the 
	 * colon in it (such as those used by Tailwind). 
	 * 
	 * @param string $class Formatted class string to parse class types and names from
	 * @param string $property Default/fallback element/property if not indicated in string
	 * @return self
	 * @since 3.0.204
	 *
	 * 
	 */
	protected function addClassString($class, $property = 'class') {
		
		if(ctype_alnum($class)) return $this->addClass($class, $property);
		
		$typeNames = array('wrap', 'header', 'content', 'input', 'class'); 
		$class = trim($class);
		if(strpos($class, "\n")) $class = str_replace("\n", ",", $class);
		$groups = strpos($class, ',') ? explode(',', $class) : array($class);
		
		foreach($groups as $group) {
			
			$type = $property;
			$group = trim($group);
			$classes = explode(' ', $group);
			
			foreach($classes as $class) {
				if(empty($class)) continue;
				if(strpos($class, ':')) {
					// setting new element type i.e. wrap:myclass or +foo:myclass
					list($typeName, $className) = explode(':', $class, 2);
					$typeName = trim($typeName);
					if(in_array($typeName, $typeNames) || strpos($typeName, '+') === 0) {
						// accepted as element/type for adding classes
						$type = ltrim($typeName, '+');
						$class = trim($className);
					} else {
						// literal class name with a colon in it such as "lg:bg-red-400'
					}
				}
				if(strpos($class, '-') === 0) {
					$this->removeClass(ltrim($class, '-'), $type);
				} else {
					$this->addClass($class, "=$type"); // "=type" prevents further processing
				}
			}
		}
		
		return $this;
	}

	/**
	 * Does this Inputfield have the given class name (or names)?
	 * 
	 * ~~~~~
	 * if($inputfield->hasClass('foo')) {
	 *   // This Inputfield has a class attribute with "foo"
	 * }
	 * 
	 * if($inputfield->hasClass('bar', 'wrapClass')) {
	 *   // This .Inputfield wrapper has a class attribute with "bar"
	 * }
	 * 
	 * if($inputfield->hasClass('foo bar')) {
	 *   // This Inputfield has both "foo" and "bar" classes (Since 3.0.16)
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-attribute-methods
	 * 
	 * @param string|array $class Specify class name or one of the following: 
	 *   - String containing name of class you want to check (string). 
	 *   - String containing Space separated string class names you want to check, all must be present for 
	 *     this method to return true. (Since 3.0.16)
	 *   - Array of class names you want to check, all must be present for this method to return true. (Since 3.0.16)
	 * @param string $property Optionally specify property you want to pull class from:
	 *   - `class` (string): Default setting. Class for the input element (or whatever the Inputfield default is).
	 *   - `wrapClass` (string): Class for the ".Inputfield" wrapping element, the most outer level element used for this Inputfield.
	 *   - `headerClass` (string): Class for the ".InputfieldHeader" label element.
	 *   - `contentClass` (string): Class for the ".InputfieldContent" wrapping element. 
	 *   - Or some other class property defined by a descending Inputfield class. 
	 * @return bool
	 * @see Inputfield::addClass(), Inputfield::removeClass()
	 * 
	 */
	public function hasClass($class, $property = 'class') {
		
		if(is_string($class)) $class = trim($class);

		// checking multiple classes
		if(is_array($class) || strpos($class, ' ')) {
			$classes = is_string($class) ? explode(' ', $class) : $class;
			if(!count($classes)) return false;
			$n = 0;
			foreach($classes as $c) {
				$c = trim($c);
				if(empty($c) || $this->hasClass($c, $property)) $n++;
			}
			// return whether it had all the given classes
			return $n === count($classes); 	
		}
	
		// checking single class
		$classes = $this->getClassArray($property, true);
	
		return isset($classes[$class]); 
	}

	/**
	 * Get classes in array for given class property
	 * 
	 * @param string $property One of 'wrap', 'header', 'content' or 'input' (or alias 'class')
	 * @param bool $assoc Return as associative array where both keys and values are class names? (default=false)
	 * @return array
	 * @since 3.0.204
	 * 
	 */
	public function getClassArray($property = 'class', $assoc = false) {
		$property = $this->getClassProperty($property);
		$value = ($property === 'class' ? $this->attr('class') : $this->getSetting($property));
		$value = trim("$value"); 
		while(strpos($value, '  ') !== false) $value = str_replace('  ', ' ', $value);
		$classes = strlen($value) ? explode(' ', $value) : array();
		if($assoc) {
			$a = array();
			foreach($classes as $class) $a[$class] = $class;
			$classes = $a;
		}
		return $classes;
	}

	/**
	 * Get the internal property name for given class property
	 * 
	 * This converts things like 'wrap' to 'wrapClass', 'header' to 'headerClass', etc. 
	 * 
	 * @param string $property
	 * @return string
	 * @since 3.0.204
	 * 
	 */
	protected function getClassProperty($property) {
		if($property === 'class' || $property === 'input' || empty($property)) {
			$property = 'class';
		} else if(strpos($property, 'Class') === false) {
			if(in_array($property, array('wrap', 'header', 'content'))) $property .= 'Class';
		}
		return $property;
	}

	/**
	 * Remove the given class (or classes) from this Inputfield
	 * 
	 * ~~~~~
	 * // Remove the "foo" class
	 * $inputfield->removeClass('foo'); 
	 * 
	 * // Remove both the "foo" and "bar" classes (since 3.0.16)
	 * $inputfield->removeClass('foo bar'); 
	 * 
	 * // Remove the "bar" class from the wrapping .Inputfield element
	 * $inputfield->removeClass('bar', 'wrapClass'); 
	 * ~~~~~
	 * 
	 * #pw-group-attribute-methods
	 *
	 * @param string|array $class Class name you want to remove or specify one of the following:
	 *   - Single class name to remove.
	 *   - Space-separated class names you want to remove (Since 3.0.16).
	 *   - Array of class names you want to remove (Since 3.0.16).
	 * @param string $property Optionally specify the property you want to remove class from:
	 *   - `class` (string): Default setting. Class for the input element (or whatever the Inputfield default is).
	 *   - `wrapClass` (string): Class for the ".Inputfield" wrapping element, the most outer level element used for this Inputfield.
	 *   - `headerClass` (string): Class for the ".InputfieldHeader" label element.
	 *   - `contentClass` (string): Class for the ".InputfieldContent" wrapping element.
	 *   - Or some other class property defined by a descending Inputfield class. 
	 * @return $this
	 * @see Inputfield::addClass(), Inputfield::hasClass()
	 *
	 */
	public function removeClass($class, $property = 'class') {
	
		$property = $this->getClassProperty($property);
		$classes = $this->getClassArray($property, true);
		$removeClasses = is_array($class) ? $class : explode(' ', $class); 
		
		foreach($removeClasses as $removeClass) {
			if(strlen($removeClass)) unset($classes[$removeClass]);
		}
		
		if($property === 'class') {
			$this->attributes['class'] = implode(' ', $classes);
		} else {
			$this->set($property, implode(' ', $classes));
		}
		
		return $this;
	}

	/**
	 * Get an HTML-ready string of all this Inputfield’s attributes
	 * 
	 * ~~~~~
	 * // Outputs: name="foo" value="bar" class="baz"
	 * echo $inputfield->getAttributesString([
	 *   'name' => 'foo',
	 *   'value' => 'bar',
	 *   'class' => 'baz'
	 * ]);
	 * 
	 * // Outputs actual attributes specified with this Inputfield
	 * echo $inputfield->getAttributesString();
	 * ~~~~~
	 * 
	 * #pw-internal
	 *
	 * @param array|null $attributes Associative array of attributes to build the string from, or omit to use this Inputfield's attributes.
	 * @return string
	 *
	 */
	public function getAttributesString(?array $attributes = null) {

		$str = '';

		// if no attributes provided then use the ones for this Inputfield by default
		if(is_null($attributes)) $attributes = $this->getAttributes();

		if($this instanceof InputfieldHasArrayValue) {
			// fields that use arrays as values aren't going to be using a value attribute in this string, so skip it
			unset($attributes['value']); 

			// tell PHP to return an array by adding [] to the name attribute, i.e. "myfield[]"
			if(isset($attributes['name']) && substr($attributes['name'], -1) != ']') $attributes['name'] .= '[]';
		}

		foreach($attributes as $attr => $value) {
			
			if(is_array($value)) {
				// if an attribute has multiple values (like class), then bundle them into a string separated by spaces
				$value = implode(' ', $value);
				
			} else if(is_bool($value)) {
				// boolean attribute uses only attribute name when true, or omit when false
				if($value === true) $str .= "$attr ";
				continue;
				
			} else if(!strlen("$value") && strpos($attr, 'data-') !== 0) {
				// skip over empty non-data attributes that are not arrays
				// if(!$value = $this->attr($attr))) continue; // was in 3.0.132 and earlier
				continue;
			}

			$str .= "$attr=\"" . htmlspecialchars("$value", ENT_QUOTES, "UTF-8") . '" ';
		}

		return trim($str); 
	}

	/**
	 * Render the HTML input element(s) markup, ready for insertion in an HTML form. 
	 *
	 * This is an abstract method that descending Inputfield module classes are required to implement.
	 * 
	 * #pw-group-output
	 *
	 * @return string
	 *
	 */
	abstract public function ___render();

	/**
	 * Render just the value (not input) in text/markup for presentation purposes.
	 * 
	 * #pw-group-output
	 * 
 	 * @return string Text or markup where applicable
	 *
	 */
	public function ___renderValue() {
		// This is within the context of an InputfieldForm, where the rendered markup can have
		// external CSS or JS dependencies (in Inputfield[Name].css or Inputfield[Name].js)
		$value = $this->attr('value');
		if(is_array($value)) {
			if(!count($value)) return '';
			$out = "<ul>";
			foreach($value as $v) $out .= "<li>" . $this->wire()->sanitizer->entities($v) . "</li>";
			$out .= "</ul>";
		} else {
			$out = $this->wire()->sanitizer->entities($value); 
		}
		return $out; 
	}
	
	/**
	 * Method called right before Inputfield markup is rendered, so that any dependencies can be loaded as well.
	 * 
	 * Called before `Inputfield::render()` or `Inputfield::renderValue()` method by the parent `InputfieldWrapper`
	 * instance. If you are calling either of those methods yourself for some reason, make sure that you call this 
	 * `renderReady()` method first. 
	 * 
	 * The default behavior of this method is to populate Inputfield-specific CSS and JS file assets into 
	 * `$config->styles` and `$config->scripts`. 
	 * 
	 * The return value is true if assets were just added, and false if assets have already been added in a previous 
	 * call. This distinction probably doesn't matter in most usages, but here just in case a descending class needs 
	 * to know when/if to add additional assets (i.e. when this method returns true). 
	 * 
	 * #pw-group-output
	 * 
	 * @param Inputfield|null The parent InputfieldWrapper that is rendering it, or null if no parent.
	 * @param bool $renderValueMode Specify true only if this is for `Inputfield::renderValue()` rather than `Inputfield::render()`. 
	 * @return bool True if assets were just added, false if already added. 
	 *
	 */
	public function renderReady(?Inputfield $parent = null, $renderValueMode = false) {
		if($this->className() === 'InputfieldWrapper') {
			$result = false;
		} else {
			$result = $this->wire()->modules->loadModuleFileAssets($this) > 0;
		}
		if($this->wire()->hooks->isMethodHooked($this, 'renderReadyHook')) {
			$this->renderReadyHook($parent, $renderValueMode);
		}
		return $result;
	}

	/**
	 * Hookable version of renderReady(), not called unless 'renderReadyHook' is hooked
	 * 
	 * Hook this method instead if you want to hook renderReady().
	 * 
	 * @param Inputfield|null $parent
	 * @param bool $renderValueMode
	 * 
	 */
	public function ___renderReadyHook(?Inputfield $parent = null, $renderValueMode = false) { }

	/**
	 * This hook was replaced by renderReady
	 * 
	 * #pw-internal
	 * 
	 * @param $event
	 * @deprecated
	 *
	 */
	public function hookRender($event) {  }
	
	/**
	 * Process input for this Inputfield directly from the POST (or GET) variables 
	 * 
	 * This method should pull the value from the given `$input` argument, sanitize/validate it, and 
	 * populate it to the `value` attribute of this Inputfield. 
	 * 
	 * Inputfield modules should implement this method if the built-in one here doesn't solve their need.
	 * If this one does solve their need, then they should add any additional sanitization or validation
	 * to the `Inputfield::setAttribute('value', $value)` method to occur when given the `value` attribute. 
	 * 
	 * #pw-group-input
	 * 
	 * @param WireInputData $input User input where value should be pulled from (typically `$input->post`)
	 * @return $this
	 * 
	 */
	public function ___processInput(WireInputData $input) {

		if(isset($input[$this->name])) {
			$value = $input[$this->name]; 

		} else if($this instanceof InputfieldHasArrayValue) {
			$value = array();
		} else {
			$value = $input[$this->name];
		}

		$changed = false; 

		if($this instanceof InputfieldHasArrayValue && !is_array($value)) {
			/** @var Inputfield $this */
			$this->error("Expected an array value and did not receive it"); 
			return $this;
		}
		
		$previousValue = $this->attr('value');

		if(is_array($value)) {
			// an array value was provided in the input
			// note: only arrays one level deep are allowed
			
			if(!$this instanceof InputfieldHasArrayValue) {
				$this->error("Received an unexpected array value"); 
				return $this; 
			}

			$values = array();
			foreach($value as $v) {
				if(is_array($v)) continue; // skip over multldimensional arrays, not allowed
				if(ctype_digit("$v") && (((int) $v) <= PHP_INT_MAX)) $v = (int) "$v"; // force digit strings as integers
				$values[] = $v; 
			}

			if($previousValue !== $values) { 
				// If it has changed, then update for the changed value
				$changed = true;
				/** @var Inputfield $this */
				$this->setAttribute('value', $values); 
			}

		} else { 
			// string value provided in the input
			$this->setAttribute('value', $value); 
			$value = $this->attr('value'); 
			if("$value" !== (string) $previousValue) {
				$changed = true; 
			}
		}

		if($changed) { 
			$this->trackChange('value', $previousValue, $value); 

			// notify the parent of the change
			$parent = $this->getParent();
			if($parent) $parent->trackChange($this->name); 
		}

		return $this; 
	}

	/**
	 * Is this Inputfield empty? (Value attribute reflects an empty state)
	 * 
	 * Return true if this field is empty (no value or blank value), or false if it’s not empty.
	 *
	 * Used by the 'required' check to see if the field is populated, and descending Inputfields may 
	 * override this according to their own definition of 'empty'.
	 * 
	 * #pw-group-attribute-methods
	 *
	 * @return bool
	 *
	 */
	public function isEmpty() {
		$value = $this->attr('value'); 
		if(is_array($value)) return count($value) == 0;
		if(!strlen("$value")) return true; 
		// if($value === 0) return true; 
		return false; 
	}

	/**
	 * Get any custom configuration fields for this Inputfield
	 *
	 * - Intended to be extended by each Inputfield as needed to add more config options.
	 * 
	 * - The returned InputfieldWrapper ultimately ends up as the "Input" tab in the fields editor (admin). 
	 * 
	 * - Descending Inputfield classes should first call this method from the parent class to get the
	 *   default configuration fields and the InputfieldWrapper they can add to.
	 * 
	 * - Returned Inputfield instances with a name attribute that starts with an underscore, i.e. "_myname" 
	 *   are assumed to be for runtime use and are NOT stored in the database.
	 * 
	 * - If you prefer, you may instead implement the `Inputfield::getConfigArray()` method as an alternative.
	 * 
	 * ~~~~
	 * // Example getConfigInputfields() implementation
	 * public function ___getConfigInputfields() {
	 *   // Get the defaults and $inputfields wrapper we can add to
	 *   $inputfields = parent::___getConfigInputfields();
	 *   // Add a new Inputfield to it
	 *   $f = $this->wire('modules')->get('InputfieldText'); 
	 *   $f->attr('name', 'first_name');
	 *   $f->attr('value', $this->get('first_name')); 
	 *   $f->label = 'Your First Name';
	 *   $inputfields->add($f); 
	 *   return $inputfields; 
	 * }
	 * ~~~~
	 * 
	 * #pw-group-module
	 *
	 * @return InputfieldWrapper Populated with Inputfield instances
	 * @see Inputfield::getConfigArray()
	 *
	 */
	public function ___getConfigInputfields() {

		$conditionsText = $this->_('Conditions are expressed with a "field=value" selector containing fields and values to match. Multiple conditions should be separated by a comma.');
		$conditionsNote = $this->_('Read more about [how to use this](https://processwire.com/api/selectors/inputfield-dependencies/).'); 

		/** @var InputfieldWrapper $inputfields */
		$inputfields = $this->wire(new InputfieldWrapper());

		$fieldset = $inputfields->InputfieldFieldset;
		$fieldset->label = $this->_('Visibility'); 
		$fieldset->attr('name', 'visibility'); 
		$fieldset->icon = 'eye';
		if($this->collapsed == Inputfield::collapsedNo && !$this->getSetting('showIf')) {
			$fieldset->collapsed = Inputfield::collapsedYes;
		}
		$inputfields->append($fieldset);

		$f = $inputfields->InputfieldSelect;
		$f->attr('name', 'collapsed'); 
		$f->label = $this->_('Presentation'); 
		$f->icon = 'eye-slash';
		$f->description = $this->_("How should this field be displayed in the editor?");
		$f->addOption(self::collapsedNo, $this->_('Open'));
		$f->addOption(self::collapsedNever, $this->_('Open + Cannot be closed'));
		$f->addOption(self::collapsedNoLocked, $this->_('Open + Locked (not editable)'));
		$f->addOption(self::collapsedBlank, $this->_('Open when populated + Closed when blank'));
		if($this->hasFieldtype !== false) {
			$f->addOption(self::collapsedBlankAjax, $this->_('Open when populated + Closed when blank + Load only when opened (AJAX)') . " †");
		}
		$f->addOption(self::collapsedBlankLocked, $this->_('Open when populated + Closed when blank + Locked (not editable)'));
		$f->addOption(self::collapsedPopulated, $this->_('Open when blank + Closed when populated'));
		$f->addOption(self::collapsedYes, $this->_('Closed')); 
		$f->addOption(self::collapsedYesLocked, $this->_('Closed + Locked (not editable)'));
		if($this->hasFieldtype !== false) {
			$f->addOption(self::collapsedYesAjax, $this->_('Closed + Load only when opened (AJAX)') . " †");
			$f->notes = sprintf($this->_('Options indicated with %s may not work with all input types or placements, test to ensure compatibility.'), '†');
			$f->addOption(self::collapsedTab, $this->_('Tab'));
			$f->addOption(self::collapsedTabAjax, $this->_('Tab + Load only when clicked (AJAX)') . " †");
			$f->addOption(self::collapsedTabLocked, $this->_('Tab + Locked (not editable)'));
		}
		$f->addOption(self::collapsedHidden, $this->_('Hidden (not shown in the editor)'));
		$f->attr('value', (int) $this->collapsed); 
		$fieldset->append($f); 

		$f = $inputfields->InputfieldText;
		$f->label = $this->_('Show this field only if');
		$f->description = $this->_('Enter the conditions under which the field will be shown.') . ' ' . $conditionsText; 
		$f->notes = $conditionsNote; 
		$f->icon = 'question-circle';
		$f->attr('name', 'showIf'); 
		$f->attr('value', $this->getSetting('showIf')); 
		$f->collapsed = Inputfield::collapsedBlank;
		$f->showIf = "collapsed!=" . self::collapsedHidden;
		$fieldset->append($f);

		$value = (int) $this->getSetting('columnWidth');
		if($value < 10 || $value >= 100) $value = 100;
		
		$f = $inputfields->InputfieldInteger;
		$f->label = sprintf($this->_('Column width (%d%%)'), $value);
		$f->icon = 'arrows-h';
		$f->attr('id+name', 'columnWidth'); 
		$f->addClass('columnWidthInput');
		$f->attr('type', 'text');
		$f->attr('maxlength', 4); 
		$f->attr('size', 4); 
		$f->attr('max', 100); 
		$f->attr('value', $value . '%'); 
		$f->description = $this->_("The percentage width of this field's container (10%-100%). If placed next to other fields with reduced widths, it will create floated columns."); // Description of colWidth option
		$f->notes = $this->_("Note that not all fields will work at reduced widths, so you should test the result after changing this."); // Notes for colWidth option
		if(!$this->wire()->input->get('process_template') && $value == 100) $f->collapsed = Inputfield::collapsedYes; 
		$inputfields->append($f); 

		if(!$this instanceof InputfieldWrapper) {
			$f = $inputfields->InputfieldCheckbox;
			$f->label = $this->_('Required?');
			$f->icon = 'asterisk';
			$f->attr('name', 'required'); 
			$f->attr('value', 1); 
			$f->attr('checked', $this->getSetting('required') ? 'checked' : ''); 
			$f->description = $this->_("If checked, a value will be required for this field.");
			$f->collapsed = $this->getSetting('required') ? Inputfield::collapsedNo : Inputfield::collapsedYes; 
			$inputfields->add($f);
	
			$requiredAttr = $this->getSetting('requiredAttr'); 
			if($requiredAttr !== null) {
				// Inputfield must have set requiredAttr to some non-null value before this will appear as option in config
				$f->columnWidth = 50; // required checkbox
				$f = $inputfields->InputfieldCheckbox;
				$f->attr('name', 'requiredAttr');
				$f->label = $this->_('Also use HTML5 “required” attribute?');
				$f->showIf = "required=1, showIf='', requiredIf=''";
				$f->description = $this->_('Use only on fields *always* visible to the user.');
				$f->icon = 'html5';
				$f->columnWidth = 50;
				if($requiredAttr) $f->attr('checked', 'checked');
				$inputfields->add($f);
			}
		
			$f = $inputfields->InputfieldText;
			$f->label = $this->_('Required only if');
			$f->icon = 'asterisk';
			$f->description = $this->_('Enter the conditions under which a value will be required for this field.') . ' ' . $conditionsText; 
			$f->notes = $conditionsNote; 
			$f->attr('name', 'requiredIf'); 
			$f->attr('value', $this->getSetting('requiredIf')); 
			$f->collapsed = $f->attr('value') ? Inputfield::collapsedNo : Inputfield::collapsedYes; 
			$f->showIf = "required>0"; 
			$inputfields->add($f); 
		}
		
		if($this->hasFieldtype === false || $this->wire()->config->advanced) {
			$f = $inputfields->InputfieldTextarea;
			$f->attr('name', 'addClass');
			$f->label = $this->_('Custom class attributes');
			$f->description =
				$this->_('Optionally add to the class attribute for specific elements in this Inputfield.') . ' ' .
				$this->_('Format is one per line of `element:class` where `element` is one of: “wrap”, “header”, “content” or “input” and `class` is one or more class names.') . ' ' .
				$this->_('If no element is specified then the “input” element is assumed.');
			$f->notes = $this->_('Example:') . "`" .
				"\nwrap:card card-default" .
				"\nheader:card-header" .
				"\ncontent:card-body" .
				"\ninput:form-input input-checkbox" .
				"`";
			$f->collapsed = Inputfield::collapsedBlank;
			$f->renderFlags = self::renderLast;
			$f->val($this->getSetting('addClass'));
			$inputfields->add($f);
		}
	
		return $inputfields; 
	}

	/**
	 * Alternative method for configuration that allows for array definition
	 * 
	 * - This method is typically used instead of the `Inputfield::getConfigInputfields` method
	 *   for module authors that prefer to use the array definition. 
	 * 
	 * - If both `getConfigInputfields()` and `getConfigArray()` are implemented, both will be used. 
	 * 
	 * - See comments for `InputfieldWrapper::importArray()` for example of array definition.
	 * 
	 * ~~~~~
	 * // Example implementation
	 * public function ___getConfigArray() {
	 *   return [
	 *     'test' => [
	 *       'type' => 'text',
	 *       'label' => 'This is a test',
	 *       'value' => 'Test'
	 *     ]
	 *   ];
	 * );
	 * ~~~~~
	 * 
	 * #pw-group-module
	 * 
	 * @return array
	 * 
	 */
	public function ___getConfigArray() {
		return array();
	}

	/**
	 * Return a list of config property names allowed for fieldgroup/template context
	 * 
	 * These should be the names of Inputfields returned by `Inputfield::getConfigInputfields()` or 
	 * `Inputfield::getConfigArray()` that are allowed in fieldgroup/template context.
	 * 
	 * The config property names specified here are allowed to be configured within the context 
	 * of a given `Fieldgroup`, enabling the user to configure them independently per template
	 * in the admin. 
	 * 
	 * This is the equivalent to the `Fieldtype::getConfigAllowContext()` method, but for the "Input" 
	 * tab rather than the "Details" tab. 
	 * 
	 * #pw-group-module
	 * 
	 * @param Field $field
	 * @return array of Inputfield names
	 * @see Fieldtype::getConfigAllowContext()
	 * 
	 */
	public function ___getConfigAllowContext($field) {
		if($field) {}
		return array(
			'visibility', 
			'collapsed', 
			'columnWidth', 
			'required', 
			'requiredIf', 
			'requiredAttr',
			'showIf'
		);
	}
	
	/**
	 * Export configuration values for external consumption
	 *
	 * Use this method to externalize any config values when necessary.
	 * For example, internal IDs should be converted to GUIDs where possible.
	 * 
	 * Most Inputfields do not need to implement this.
	 * 
	 * #pw-internal
	 * 
	 * @param array $data
	 * @return array
	 *
	 */
	public function ___exportConfigData(array $data) {
		$inputfields = $this->getConfigInputfields(); 
		if(!$inputfields || !count($inputfields)) return $data;
		foreach($inputfields->getAll() as $inputfield) {
			/** @var Inputfield $inputfield */
			$value = $inputfield->isEmpty() ? '' : $inputfield->value;
			if(is_object($value)) $value = (string) $value;
			$data[$inputfield->name] = $value;
		}
		return $data;
	}

	/**
	 * Convert an array of exported data to a format that will be understood internally (opposite of exportConfigData)
	 * 
	 * Most Inputfields do not need to implement this.
	 * 
	 * #pw-internal
	 *
	 * @param array $data
	 * @return array Data as given and modified as needed. Also included is $data[errors], an associative array
	 * 	indexed by property name containing errors that occurred during import of config data. 
	 *
	 */
	public function ___importConfigData(array $data) {
		return $data;
	}

	/**
	 * Returns a unique key variable used to store errors in the session
	 * 
	 * #pw-internal
	 *
	 */
	public function getErrorSessionKey() {
		$name = $this->attr('name'); 
		if(!$name) $name = $this->attr('id'); 
		$key = "_errors_" . $this->className() . "_$name";
		return $key;
	}

	/**
	 * Record an error for this Inputfield
	 * 
	 * The error message will be placed in the context of this Inputfield. 
	 * See the `Wire::error()` method for full details on arguments and options. 
	 * 
	 * #pw-group-states
	 * 
	 * @param string $text Text of error message
	 * @param int $flags Optional flags
	 * @return $this
	 *
	 */
	public function error($text, $flags = 0) {
		// Override Wire's error method and place errors in the context of their inputfield
		$session = $this->wire()->session;
		$key = $this->getErrorSessionKey();
		$errors = $session->$key;			
		if(!is_array($errors)) $errors = array();
		if(!in_array($text, $errors)) {
			$errors[] = $text; 
			$session->set($key, $errors); 
		}
		$label = $this->getSetting('label');
		if(empty($label)) $label = $this->attr('name');
		if(strlen($label)) $text .= " - $label"; 
		return parent::error($text, $flags); 
	}

	/**
	 * Return array of strings containing errors that occurred during input processing
	 * 
	 * Note that this is different from `Wire::errors()` in that it retrieves errors from the session
	 * rather than just the current run. 
	 * 
	 * #pw-group-states
	 *
	 * @param bool $clear Optionally clear the errors after getting them (Default=false).
	 * @return array
	 *
	 */
	public function getErrors($clear = false) {
		$session = $this->wire()->session;
		$key = $this->getErrorSessionKey();
		$errors = $session->get($key);
		if(!is_array($errors)) $errors = array();
		if($clear) {
			$session->remove($key); 
			parent::errors("clear"); 
		}
		return $errors; 
	}
	
	/**
	 * Clear errors from this Inputfield
	 * 
	 * This is the same as `$inputfield->getErrors(true);` but has no return value. 
	 * 
	 * #pw-group-states
	 *
	 * @since 3.0.205 
	 *
	 */
	public function clearErrors() {
		$this->getErrors(true);
	}

	/**
	 * Does this Inputfield have the requested property or attribute?
	 * 
	 * #pw-group-attribute-methods
	 * #pw-group-settings
	 *
	 * @param string $key Requested property or attribute.
	 * @return bool True if it has it, false if it doesn't 
	 *
	 */
	public function has($key) {
		$has = parent::has($key); 
		if(!$has) $has = isset($this->attributes[$key]); 
		return $has; 
	}

	/**
	 * Does this Inputfield have the given setting?
	 * 
	 * Return value does not indicate that it is non-empty, only that the setting is available. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $key Setting name
	 * @return bool
	 * 
	 */
	public function hasSetting($key) {
		return isset($this->data[$key]);
	}

	/**
	 * Does this Inputfield have the given attribute?
	 * 
	 * Return value does not indicate that it is non-empty, only that the setting is available. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $key Attribute name
	 * @return bool
	 * 
	 */
	public function hasAttribute($key) {
		return isset($this->attributes[$key]);
	}

	/**
	 * Track the change, but only if it was to the 'value' attribute.
	 *
	 * We don't track changes to any other properties of Inputfields. 
	 * 
	 * #pw-internal
	 *
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change
	 * @param mixed $new New value
	 * @return Inputfield|WireData $this
	 *
	 */
	public function trackChange($what, $old = null, $new = null) {
		if($what != 'value') return $this;
		return parent::trackChange($what, $old, $new); 
	}

	/**
	 * Entity encode a string with optional Markdown support.
	 *
	 * - Markdown support provided with second argument. 
	 * - If string is already entity-encoded it will first be decoded. 
	 * 
	 * #pw-group-output
	 *
	 * @param string $str String to encode 
	 * @param bool|int $markdown Optionally specify one of the following: 
	 *   - `true` (boolean): To allow Markdown using default "textFormat" setting (which is basic Markdown by default). 
	 *   - `false` (boolean): To disallow Markdown support (this is the default when $markdown argument omitted). 
	 *   - `Inputfield::textFormatNone` (constant): Disallow Markdown support (default). 
	 *   - `Inputfield::textFormatBasic` (constant): To support basic/inline Markdown.
	 *   - `Inputfield::textFormatMarkdown` (constant): To support full Markdown and HTML.
	 * @return string Entity encoded string or HTML string
	 *
	 */
	public function entityEncode($str, $markdown = false) {
		
		$sanitizer = $this->wire()->sanitizer;
		
		$str = (string) $str; 
		
		// if already encoded, then un-encode it
		if(strpos($str, '&') !== false && preg_match('/&(#\d+|[a-zA-Z]+);/', $str)) {
			$str = $sanitizer->unentities($str);
		}
		
		if($markdown && $markdown !== self::textFormatNone) {
			if(is_int($markdown)) {
				$textFormat = $markdown;
			} else {
				$textFormat = $this->getSetting('textFormat');
			}
			if(!$textFormat) $textFormat = self::textFormatBasic;
			if($textFormat & self::textFormatBasic) {
				// only basic markdown allowed (default behavior)
				$str = $sanitizer->entitiesMarkdown($str, array('allowBrackets' => true));
			} else if($textFormat & self::textFormatMarkdown) {
				// full markdown, plus HTML is also allowed
				$str = $sanitizer->entitiesMarkdown($str, array('fullMarkdown' => true));
			} else {
				// nothing allowed, text fully entity encoded regardless of $markdown request
				$str = $sanitizer->entities($str);
			}
			
		} else {
			$str = $sanitizer->entities($str);
		}
		
		return $str;
	}

	/**
	 * Get or set editable state for this Inputfield
	 * 
	 * When set to false, the `Inputfield::processInput()` method won't be called by parent InputfieldWrapper,
	 * effectively skipping over input processing entirely for this Inputfield. 
	 * 
	 * #pw-group-states
	 * 
	 * @param bool|null $setEditable Specify true or false to set the editable state, or omit just to get the editable state.
	 * @return bool Returns the current editable state.
	 * 
	 */
	public function editable($setEditable = null) {
		if(!is_null($setEditable)) $this->editable = (bool) $setEditable;
		return $this->editable;
	}

	/**
	 * Add header action
	 *
	 * This adds a clickable icon to the right side of the Inputfield header.
	 * There are three types of actions: 'click', 'toggle' and 'link'. The 'click' 
	 * action simply triggers your JS event whenever it is clicked. The 'toggle' action
	 * has an on/off state, and you can specify the JS event to trigger for each. 
	 * This function will automatically figure out whether you want a `click`,
	 * `toggle` or 'link' action based on what you provide in the $settings argument.
	 * Below is a summary of these settings:
	 * 
	 * Settings for 'click' or 'link' type actions
	 * -------------------------------------------
	 * - `icon` (string): Name of font-awesome icon to use.
	 * - `tooltip` (string): Optional tooltip text to display when icon hovered. 
	 * - `event` (string): Event name to trigger in JS when clicked ('click' actions only). 
	 * - `href` (string): URL to open ('link' actions only). 
	 * - `modal` (bool): Specify true to open link in modal ('link' actions only). 
	 * 
	 * Settings for 'toggle' (on/off) type actions 
	 * -------------------------------------------
	 * - `on` (bool): Start with the 'on' state? (default=false)
	 * - `onIcon` (string): Name of font-awesome icon to show for on state. 
	 * - `onEvent` (string): JS event name to trigger when toggled on. 
	 * - `onTooltip` (string): Tooltip text to show when on icon is hovered. 
	 * - `offIcon` (string): Name of font-awesome icon to show for off state. 
	 * - `offEvent` (string): JS event name to trigger when toggled off. 
	 * - `offTooltip` (string): Tooltip text to show when off icon is hovered. 
	 * 
	 * Other/optional settings (applies to all types) 
	 * ----------------------------------------------
	 * - `name` (string): Name of this action (-_a-zA-Z0-9).
	 * - `parent` (string): Name of parent action, if this action is part of a menu.
	 * - `overIcon` (string): Name of font-awesome icon to show when hovered. 
	 * - `overEvent` (string): JS event name to trigger when mouse is over the icon.
	 * - `downIcon` (string): Icon to display when mouse is down on the action icon (3.0.241+).
	 * - `downEvent` (string): JS event name to trigger when mouse is down on the icon (3.0.241+).
	 * - `cursor` (string): CSS cursor name to show when mouse is over the icon. 
	 * - `setAll` (array): Set all of the header actions in one call, replaces any existing. 
	 *    Note: to get all actions, call the method and omit the $settings argument. 
	 * 
	 * Settings for dropdown menu actions (3.0.241+)
	 * ---------------------------------------------
	 *  Note that menu type actions also require jQuery UI and /wire/templates-admin/scripts/main.js,
	 *  both of which are already present in PW’s admin themes (AdminThemeUikit recommended).
	 *  Requires ProcessWire 3.0.241 or newer.
	 *  - `icon` (string): Icon name to use for dropdown toggle, i.e. 'fa-wrench'.
	 *  - `tooltip` (string): Optional tooltip to describe what the dropdown is for.
	 *  - `menuAction` (string): Action that toggles the menu to show, one of 'click' or 'hover' (default).
	 *  - `menuItems` (array): Definition of menu items, each with one or more of the following properties.
	 *     - `label` (string): Label text for the menu item (required).
	 *     - `icon` (string): Icon name for the menu item, if desired.
	 *     - `callback` (function|null): JS callback to execute item is clicked (not applicable in PHP).*
	 *     - `event` (string): JS event name to trigger when item is clicked.
	 *     - `tooltip` (string): Tooltip text to show when hovering menu item (title attribute).
	 *     - `href` (string): URL to go to when menu item clicked.
	 *     - `target` (string): Target attribute when href is used (i.e. "_blank").
	 *     - `modal` (bool): Open href in modal window instead?
	 *     - `active` (function|bool): Callback function that returns true if menu item active, or false.*
	 *        if disabled. You can also directly specify true or false for this option.
	 *     - NOTE 1: All `menuItems` properties above are optional, except for 'label'.
	 *     - NOTE 2: To use `callback` or `active` as functions, you must define your menu in JS instead.
	 *     - NOTE 3: For examples see the addHeaderAction() method in /wire/templates-admin/scripts/inputfields.js
	 *
	 * @param array $settings Specify array containing the appropriate settings above.
	 * @return array Returns all currently added actions.
	 * @since 3.0.240
	 * 
	 */
	public function addHeaderAction(array $settings = array()) {
		if(!empty($settings['setAll'])) {
			if(is_array($settings['setAll'])) {
				$this->headerActions = array_values($settings['setAll']);
			}
		} else {
			$this->headerActions[] = $settings; // add new action
		}
		return $this->headerActions; // return all
	}

	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = array(
			'className' => $this->className(),
			'attributes' => $this->attributes,
			'wrapAttributes' => $this->wrapAttributes,
		);
		if(is_object($this->parent)) {
			$info['parent'] = array(
				'className' => $this->parent->className(),
				'name' => $this->parent->attr('name'), 
				'id' => $this->parent->attr('id'), 
			);
		}
		$info = array_merge($info, parent::__debugInfo());
		return $info;
	}
	
	/**
	 * Set custom html render, see $this->html at top for reference.
	 *
	 * @param string $html
	 *
	public function setHTML($html) { 
		$this->html = $html;
	}
	 */

	/**
	 * Get default or custom HTML for render
	 * 
	 * If $this->html is populated, it gets returned. 
	 * If not, then this should return the default HTML for the Inputfield,
	 * where supported. 
	 * 
	 * If this returns blank, then it means custom HTML is not supported.
	 *
	 * @param array $attr When populated with key=value, tags will be replaced. 
	 * @return array
	 *
	public function getHTML($attr = array()) { 
		if(!strlen($this->html) || empty($attr) || strpos($this->html, '{') === false) return $this->html;
		$html = $this->html;
		
		if(strpos($html, '{attr}')) {
			
			$html = str_replace('{attr}', $this->getAttributesString($attr), $html);	
			
			// @todo remove any other {tags} that might be present
			
		} else {
			
			// a version of html where the {tags} get replaced with blanks
			// used for testing if more attributes present without possibility
			// of those attributes being injected
			// $_html = $html;
			
			// extract value so that a substitution can't result in input-injected tags
			if(isset($attr['value'])) {
				$value = $attr['value'];
				unset($attr['value']); 
			} else {
				$value = null;
			}
			// populate attributes
			foreach($attr as $name => $v) {
				$tag = '{' . $name . '}';
				if(strpos($html, $tag) === false) continue; 
				$v = htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); 
				$html = str_replace($tag, $v, $html);
				//$_html = str_replace($tag, '', $html); 
			}
			// see if any non-value attributes are left
			$pos = strpos($html, '{'); 
			if($pos !== false && $pos != strpos($html, '{value}')) {
				// there are unpopulated tags that need to be removed
				preg_match_all('/\{[-_a-zA-Z0-9]+\}/', $html, $matches); 
			}
			// once all other attributes populated, we can populate {value}
			if($value !== null) {
				$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				$html = str_replace('{value}', $value, $html);
				$_html = str_replace('{value}', '', $html);
			}
			// if ther
		}
		return $html;	
	}
	 */ 

}
