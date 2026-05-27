<?php namespace ProcessWire;

/** @var AdminThemeUikit $adminTheme */
/** @var WireInput $input */
/** @var Modules $modules */
/** @var Config $config */
/** @var Page $page */
/** @var User $user */

$toggles = $adminTheme->defaultToggles;
$settings = $config->AdminThemeUikit; 
$themeUrl = $config->urls('AdminThemeUikit') . 'themes/default/';

$useDarkModeSwitcher = 
	$user->isLoggedin() 
	&& !in_array('noUserMenu', $toggles) 
	&& empty($settings['noDarkMode'])
	&& $user->hasPermission('page-edit');

/**
 * Update TinyMCE to use our custom skin and content_css
 * 
 */
$adminTheme->addHookAfter('InputfieldTinyMCESettings::prepareSettingsForOutput', function(HookEvent $e) use($themeUrl) {
	
	$f = $e->object->__get('inputfield'); /** @var InputfieldTinyMCE $f */
	
	static $contentCss = '';
	
	$settings = $e->return;
	$css = isset($settings['content_css']) ? $settings['content_css'] : $f->content_css;

	if(strpos($css, 'document')) {
		// keep
		return;
	} else if(strpos($css, '/content_css/wire.css') || empty($css)) {
		// replace
	} else {
		// leave custom
		return;
	}

	if(empty($contentCss)) {
		$contentCss = $e->wire()->config->versionUrl($themeUrl . 'content.css');
	}
	
	$a = [
		'content_css' => $contentCss,
		'content_css_url' => $contentCss,
		// 'toolbar_sticky_offset' => 55, // removed: was causing issues with toolbar placement
	];
	
	if($f->skin === 'oxide' || !$f->skin) {
		$a['skin_url'] = rtrim($themeUrl, '/');
		$a['skin'] = 'custom';
	}
	
	$settings = array_merge($settings, $a);
	$e->return = $settings;
	
});

/**
 * Add a light/dark toggle to the user tools menu
 * 
 */ 
if($useDarkModeSwitcher) {
	$adminTheme->addHookAfter('getUserNavArray', function(HookEvent $e) {
		$adminTheme = $e->object; /** @var AdminThemeUikit $adminTheme */
		$navArray = $e->return; /** @var array $navArray */
		$lightLabel = __('Light mode', __FILE__);
		$darkLabel = __('Dark mode', __FILE__); 
		$autoLabel = __('Auto', __FILE__);
		$cancelLabel = $adminTheme->_('Cancel');
		$okLabel = $adminTheme->_('Ok');
		$dialogTitle = __('Light/dark mode');
		array_unshift($navArray, [
			'url' => '#toggle-light-dark-mode',
			'title' => __('Light/dark', __FILE__),
			'target' => '_top',
			'icon' => 'adjust',
			'class' => 'toggle-light-dark-mode',
			'onclick' => 'return AdminDarkMode.toggleDialog();',
			'data-label-light' => $lightLabel,
			'data-label-dark' => $darkLabel,
			'data-label-auto' => $dialogTitle,
			'data-icon-light' => 'sun-o',
			'data-icon-dark' => 'moon-o',
			'data-icon-auto' => 'adjust', 
		]);
		$e->return = $navArray;
		$adminTheme->addExtraMarkup('body', '
			<div id="light-dark-mode-dialog" hidden>
				<div class="uk-modal-body">
					<p>
						<label><input type="radio" class="uk-radio" name="mode" data-name="light" value="0"> ' . $lightLabel . '</label>&nbsp;&nbsp;
						<label><input type="radio" class="uk-radio" name="mode" data-name="dark" value="1"> ' . $darkLabel . '</label>&nbsp;&nbsp;
						<label><input type="radio" class="uk-radio" name="mode" data-name="auto" value="-1"> ' . $autoLabel . '</label>
					</p>	
				</div>
				<div class="uk-modal-footer uk-text-right">
					<button class="uk-button uk-button-default uk-modal-close" type="button">' . $cancelLabel . '</button>
					<button class="uk-button uk-button-primary uk-modal-close" autofocus>' . $okLabel . '</button>
				</div>
			</div>	
		');	
	});
	
	$setDarkMode = $input->post('set_admin_dark_mode');
	if($setDarkMode !== null && $config->ajax && $page->process == 'ProcessHome') {
		$setDarkMode = (int) $setDarkMode;
		if($setDarkMode === 0 || $setDarkMode === 1 || $setDarkMode === -1) {
			$user->meta('adminDarkMode', (int) $setDarkMode);
			header('content-type: application/json');
			return die(json_encode([
				'status' => 'ok',
				'adminDarkMode' => (int) $setDarkMode
			]));
		}
	}
}

/**
 * Add notes to InputfieldTinyMCE module config indicating which settings are overridden
 * 
 */ 
if($page->process == 'ProcessModule' && $input->get('name') === 'InputfieldTinyMCE') {
	$page->wire()->addHookAfter('InputfieldTinyMCE::getModuleConfigInputfields', function(HookEvent $e) {
		$inputfields = $e->arguments(0); /** @var InputfieldWrapper $inputfields */
		$a = [ 
			'skin' => 'oxide', 
			'content_css' => 'wire', 
			'content_css_url' => '' 
		];
		$note = __('PLEASE NOTE: this setting is currently overridden by AdminThemeUikit “default” theme.', __FILE__);
		foreach($a as $name => $default) {
			$f = $inputfields->get($name);	
			if($f && !$f->val() || $f->val() === $default) $f->notes = $note;
		}
	}); 
}
