<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldTinyMCE module.
 *
 */
class WireTest_InputfieldTinyMCE extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testSettingNames();
		$this->testInvalidStylesParser();
		$this->testRenderAndProcess();
		$this->testConfigInputfields();
	}

	protected function newInputfield($name = 'body') {
		$f = $this->wire()->modules->get('InputfieldTinyMCE');
		$f->attr('name', $name);
		$f->attr('id', "Inputfield_$name");
		return $f;
	}

	protected function processInput(InputfieldTinyMCE $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		$input = new WireInputData($data);
		return $f->processInput($input);
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldTinyMCE', true, $f instanceof InputfieldTinyMCE);
		$this->check('extends InputfieldTextarea', true, $f instanceof InputfieldTextarea);
		$this->check('TinyMCE version constant', '6.8.2', InputfieldTinyMCE::mceVersion);
		$this->check('initialized property true after module init', true, $f->initialized);
		$this->check('default inlineMode', 0, $f->inlineMode);
		$this->check('default lazyMode', 1, $f->lazyMode);
		$this->check('extPluginOpts is string', true, is_string($f->extPluginOpts));
		$this->check('default headlines are h1-h6 once each', array('h1','h2','h3','h4','h5','h6'), $f->headlines);
		$this->check('settings helper available', true, $f->settings instanceof InputfieldTinyMCESettings);
		$this->check('configs helper available', true, $f->configs instanceof InputfieldTinyMCEConfigs);
		$this->check('tools helper available', true, $f->tools instanceof InputfieldTinyMCETools);
		$this->check('formats helper available', true, $f->formats instanceof InputfieldTinyMCEFormats);
		$this->check('useFeature detects default purifier feature', true, $f->useFeature('purifier'));
		$this->check('useFeature inline false by default', false, $f->useFeature('inline'));

		$f->inlineMode = 1;
		$this->check('useFeature inline true when inlineMode enabled', true, $f->useFeature('inline'));

		$f->setConfigName('custom_body');
		$this->check('setConfigName/getConfigName round trip', 'custom_body', $f->getConfigName());
		$this->check('configurable defaults true', true, $f->configurable());
		$f->configurable(false);
		$this->check('configurable false after set', false, $f->configurable());
		$this->check('mcePath returns TinyMCE directory', 'tinymce-' . InputfieldTinyMCE::mceVersion . '/', $f->mcePath(), '$=');
		$this->check('mcePath path exists', true, is_dir($f->mcePath()));
		$this->check('getDirectionality returns ltr or rtl', true, in_array($f->getDirectionality(), array('ltr', 'rtl')));
	}

	protected function testSettingNames() {
		$f = $this->newInputfield();

		$tinymce = $f->getSettingNames('tinymce');
		$this->check('tinymce setting names include toolbar', true, in_array('toolbar', $tinymce));
		$this->check('tinymce setting names include external_plugins once', 1, count(array_keys($tinymce, 'external_plugins')));

		$module = $f->getSettingNames('module');
		$this->check('module setting names include extPluginOpts', true, in_array('extPluginOpts', $module));
		$this->check('module setting names omit typo extPluginOptions', false, in_array('extPluginOptions', $module));

		$combined = $f->getSettingNames('tinymce field');
		$this->check('combined setting names include field setting', true, in_array('inlineMode', $combined));

		$threw = false;
		try {
			$f->getSettingNames('not_a_group');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('getSettingNames throws for unknown group', true, $threw);
	}

	protected function testInvalidStylesParser() {
		$f = $this->newInputfield();
		$formats = $f->formats;

		$parsed = $formats->invalidStylesStrToArray('a=background|background-color line-height color');
		$this->check('tag-specific invalid styles parsed', 'background background-color', $parsed['a']);
		$this->check('global invalid styles do not inherit tag-specific values', 'line-height color', $parsed['*']);

		$parsed = $formats->invalidStylesStrToArray('table|td=height td=width color');
		$this->check('multi-element invalid style applies to first element', 'height', $parsed['table']);
		$this->check('same element styles merge rather than overwrite', 'height width', $parsed['td']);
		$this->check('bare style parsed globally', 'color', $parsed['*']);

		$parsed = $formats->invalidStylesStrToArray('color', array('*' => array('font-size')));
		$this->check('merge argument preserves existing global styles', 'font-size color', $parsed['*']);
	}

	protected function testRenderAndProcess() {
		$f = $this->newInputfield('tinymce_body');
		$f->val('<p>Hello</p>');
		$html = $f->render();

		$this->check('render outputs textarea editor', '<textarea ', $html, '*=');
		$this->check('render adds TinyMCE editor class', 'InputfieldTinyMCEEditor', $html, '*=');
		$this->check('render includes init script in normal lazy mode', 'InputfieldTinyMCE.init', $html, '*=');

		$this->processInput($f, '<p>Updated</p>');
		$this->check('processInput updates value', '<p>Updated</p>', $f->val());

		$f = $this->newInputfield('inline_body');
		$f->inlineMode = 2;
		$f->height = 220;
		$f->val('<p>Inline</p>');
		$html = $f->render();
		$this->check('inline render outputs content body div', 'InputfieldTinyMCEInline', $html, '*=');
		$this->check('fixed inline mode includes height style', 'height:220px', $html, '*=');

		$f = $this->newInputfield('readonly_body');
		$f->val('<p>Read</p><script>alert(1)</script>');
		$html = $f->renderValue();
		$this->check('renderValue wraps sanitized content', 'mce-content-body', $html, '*=');
		$this->check('renderValue removes script tag', false, strpos($html, '<script') !== false);
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$config = $f->getConfigInputfields();

		$this->check('getConfigInputfields returns wrapper', true, $config instanceof InputfieldWrapper);
		$this->check('config contains inlineMode input', true, $config->getChildByName('inlineMode') instanceof InputfieldRadios);
		$this->check('config contains features input', true, $config->getChildByName('features') instanceof InputfieldCheckboxes);
	}
}
