<?php namespace ProcessWire;

/**
 * Inputfield to edit and/or view JSON
 * 
 * ProcessWire 3.x
 * Copyright (C) 2026 by Ryan Cramer
 * 
 * #pw-summary Inputfield module that enables the user to view and/or edit JSON. 
 * #pw-body =
 * ~~~php
 * $json = json_encode([
 *   'array' => [1, 2, 3],
 *   'boolean' => true,
 *   'null' => null,
 *   'number' => 123,
 *   'object' => ['a' => 'b', 'c' => 'd'],
 *   'string' => 'Hello World'
 * ]);
 * 
 * // Example of viewable JSON
 * $f = $modules->get('InputfieldJson');
 * $f->attr('name', 'view_json');
 * $f->label = 'View JSON';
 * $f->mode = 'view'; // options: tree, form, text, code, view
 * $f->val($json);
 * $form->add($f);
 * 
 * // Example of editable JSON
 * $f = $modules->get('InputfieldJson');
 * $f->attr('name', 'edit_json');
 * $f->label = 'Edit JSON';
 * $f->mode = 'tree'; // options: tree, form, text, code, view
 * $f->val($json);
 * $form->add($f);
 * ~~~
 * 
 * Warning: editable JSON can contain anything. This Inputfield only validates
 * that it is valid JSON, not that it is safe JSON for your intended purpose. 
 * #pw-body
 * 
 * @property string|array $value JSON value always returned as a string, but may be optionally set as an array.
 * @property string $mode One of: 'tree', 'form', 'text', 'code', 'view'. See mode* constants. (default=tree)
 * @property bool|int $useMainMenuBar Show a main menu bar? (default=false)
 * @property bool|int $useNavigationBar Show a navigation bar? (default=false)
 * @property bool|int $useSearch Enable search function? Requires $useMainMenuBar, doesn't work well in PW admin. (default=false)
 * 
 */
class InputfieldJson extends Inputfield {
	
	public static function getModuleInfo() {
		return [
			'title' =>'JSON',
			'summary' => 'Enables viewing and editing of JSON',
			'icon' => 'js',
			'version' => 1,
		];
	}
	
	const modeTree = 'tree';
	const modeForm = 'form';
	const modeText = 'text';
	const modeCode = 'code';
	const modeView = 'view';
	
	/**
	 * Default mode if none specified
	 * 
	 */
	const defaultMode = 'tree';
	
	/**
	 * Is the renderValue() method currently executing?
	 * 
	 * @var bool 
	 * 
	 */
	protected $renderValueMode = false;
	
	/**
	 * Are we currently processing the input?
	 * 
	 * @var bool 
	 * 
	 */
	protected $processingMode = false;
	
	/**
	 * Allowed modes
	 * 
	 * @var string[] 
	 * 
	 */
	protected $modes = [ 
		self::modeTree, 
		self::modeForm, 
		self::modeText, 
		self::modeCode, 
		self::modeView 
	];
	
	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		parent::setArray([
			'mode' => self::defaultMode,
			'value' => '',
			'useMainMenuBar' => false,
			'useNavigationBar' => false,
			'useSearch' => false, // requires mainMenuBar (renders poorly in PW)
		]);
		parent::setAttribute('value', '');
	}
	
	/**
	 * Render Inputfield
	 *
	 * @return string
	 *
	 */
	public function ___render() {
		$config = $this->wire()->config;
		$value = $this->val();
		$attrs = $this->getAttributes();
		
		unset($attrs['value']);
		$attrStr = $this->getAttributesString($attrs);

		if($this->renderValueMode) {
			$mode = self::modeView;
		} else {
			$mode = in_array($this->mode, $this->modes, true) ? $this->mode : self::defaultMode;
		}
		
		$url = $config->urls($this) . 'jsoneditor/dist/';
		$jsUrl = json_encode($url . 'jsoneditor.' . ($config->debug ? 'js' : 'min.js'));
		$cssUrl = json_encode($url . 'jsoneditor.css');
		$id = json_encode($attrs['id']);
		$idEnt = htmlspecialchars($attrs['id']); 
		$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		
		$options = json_encode([
			'mode' => $mode,
			'search' => (bool) $this->useSearch, 
			'mainMenuBar' => (bool) $this->useMainMenuBar,
			'navigationBar' => (bool) $this->useNavigationBar,
		]);
		
		$script = "InputfieldJson.init($id, $jsUrl, $cssUrl, $options);";
		
		return "
			<div id='jsonEdit_$idEnt' class='InputfieldJsonContainer'></div>
			<textarea $attrStr hidden>$value</textarea>
			<script>$script</script>
		";
	}
	
	/**
	 * Set attribute
	 *
	 * @param string $value
	 * @return self
	 *
	 */
	public function setAttribute($key, $value) {
		if($key === 'value') {
			if($value === null) return $this;
			if(is_array($value) || is_object($value)) {
				$value = json_encode($value);
			}
			if(!is_string($value)) return $this;
			if(strlen($value) > 0 && !$this->processingMode) {
				if($value === $this->val()) return $this;
				if($this->isBadJson($value)) return $this;
			}
		}
		return parent::setAttribute($key, $value);	
	}
	
	/**
	 * Process input
	 *
	 * @param WireInputData $input
	 * @return self|Inputfield
	 *
	 */
	public function ___processInput(WireInputData $input) {
		if($this->mode === self::modeView || $this->renderValueMode) return $this;
	
		$value = $input[$this->name];
		if($value === null) return $this;
		
		if($this->required && empty($value)) {
			$requiredLabel = $this->getSetting('requiredLabel');
			if(empty($requiredLabel)) $requiredLabel = $this->_('Missing required value');
			$this->error($requiredLabel);
			
		} else if(!is_string($value)) {
			$this->error($this->_('Invalid JSON (expected string)'));
			
		} else if(strlen($value)) {
			$this->processingMode = true;
			$valuePrev = $this->val();
			if($value !== $valuePrev) {
				$error = $this->isBadJson($value);
				if($error) {
					$this->error($error . ($valuePrev ? ' - ' . $this->_('previous value restored') : ''));
				} else {
					$this->val($value);
				}
			}
			$this->processingMode = false;
			
		} else {
			$this->val('');
		}
		
		return $this;
	}
	
	/**
	 * Render just the value (not input) in text/markup for presentation purposes
	 *
	 * @return string of text or markup where applicable
	 *
	 */
	public function ___renderValue() {
		$this->renderValueMode = true;
		try {
			$out = $this->render();
		} finally {
			$this->renderValueMode = false;
		}
		return $out;
	}
	
	/**
	 * Is given JSON bad?
	 * 
	 * @param string $json
	 * @return string
	 * 
	 */
	public function isBadJson(&$json) {
		$error = '';
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			if(is_array($data)) {
				$json = json_encode($data); 
			} else {
				$error = 'Unknown decode error';
			}
		} catch(\JsonException $e) {
			$error = $e->getMessage();
			if(empty($error)) $error = 'Unknown json exception';
		}
		return $error;
	}
	
	/**
	 * Configuration
	 * 
	 * @return InputfieldWrapper
	 * 
	 */
	public function ___getConfigInputfields() {
		$inputfields = parent::___getConfigInputfields();
	
		$modes = [
			self::modeTree => $this->_('Tree | edit and add tree'),
			self::modeForm => $this->_('Form | edit tree'),
			self::modeText => $this->_('Text | edit raw'),
			self::modeCode => $this->_('Code | edit raw w/code helpers'),
			self::modeView => $this->_('View | read-only tree'),
		];
		
		$f = $inputfields->InputfieldRadios;
		$f->attr('name', 'mode');
		$f->label = $this->_('Mode');
		foreach($modes as $mode => $label) {
			list($label, $description) = explode('|', $label, 2);
			$f->addOption($mode, "$label - [span.detail] ($description) [/span]");
		}
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldCheckbox;
		$f->attr('name', 'useMainMenuBar');
		$f->label = $this->_('Enable menu bar?');
		$f->columnWidth = 50;
		if($this->useMainMenuBar) $f->attr('checked', 'checked');
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldCheckbox;
		$f->attr('name', 'useNavigationBar');
		$f->label = $this->_('Enable navigation bar?');
		$f->columnWidth = 50;
		if($this->useNavigationBar) $f->attr('checked', 'checked');
		$inputfields->add($f);
		
		return $inputfields;
	}
}
