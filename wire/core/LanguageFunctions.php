<?php namespace ProcessWire;

/**
 * ProcessWire Language Functions
 *
 * #pw-summary-translation Provides GetText-like language translation functions to ProcessWire.
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 */

/**
 * Perform a language translation
 * 
 * This function enables you to specify static text as the argument, and that text becomes translatable (for each language) 
 * with ProcessWire’s built-in language translation tools. This function also works just fine if you do not have multi-language 
 * support installed, though in that case it just returns the text that it is given. 
 * 
 * For full documentation, please see the [Code Internationalization (i18n)](https://processwire.com/docs/multi-language-support/code-i18n/)
 * documentation page at ProcessWire.com.
 *
 * This function is very similar to the GNU gettext `_('text');` function and essentially does the same thing, except that it is
 * native to ProcessWire and not using GNU gettext. 
 * 
 * Use this `__('text')` function for common translations, use the `_x('text', 'context')` function for translations
 * that also require additional context, and use the `_n('singular', 'plural', $n)` function for translations that should
 * changed based on whether a value `$n` would require a singular or plural tense. 
 * 
 * ### Additional behaviors 
 * 
 * - You can optionally specify a “textdomain” as the second argument. The textdomain represents the source file of the 
 *   translation. Most often it would be the current file, so the argument can be omitted, or you can specify `__FILE__`. 
 *   But in some cases you may want to use a translation from another file, and the textdomain argument enables you to. 
 * 
 * - When in a class (or module) it is preferable to use `$this->_('text');` rather than `__('text');`, as it is slightly
 *   more efficient to do so. 
 * 
 * - A PHP comment, i.e. `// comment` that appears somewhere after a translation call (on the same line) is used as an
 *   additional description for the person translating text. Another comment after that, i.e. `// comment1 // comment2`
 *   is used as an additional secondary note for the person translating the text. 
 *   
 * ### Limitations
 * 
 * - The function call (and translatable text within it) cannot span more than one line. If your translatable text is long 
 *   enough to require multiple lines, split them into multiple calls (like one per sentence). 
 * 
 * - There cannot be more than one `__('text')` function call per line in the PHP code. 
 * 
 * - The provided text argument must be one string of static text. It cannot contain PHP variables or concatenation. To populate
 *   dynamic values you should use PHP’s `sprintf()` (see examples below).
 * 
 * ### Advanced features 
 * 
 * *The following features are for specific cases and supported in ProcessWire 3.0.125+ only.*
 *
 * You can use this function to get or set specific options that affect future calls by specifying boolean true
 * for the first argument, option name for the second argument, and option value for the third argument.
 * Currently there are just two options, “entityEncode” and “translations”. The “entityEncode” option enables
 * you to control whether future calls return entity encoded text or not (see advanced examples).
 *
 * If given an array for the second argument then it is assumed that it is a list of predefined fallback
 * translations you want to define. These translations will be used if the text is not translated in the
 * admin. The translations are not specific to any textdomain and thus can serve as a fallback for any file.
 * These are intended for front-end site usage and not recommended for use in modules. As such, they are not
 * supported by the `$this->_('text');` function either. The array you provide should be associative, where the
 * keys contain the text to translate, and the values contain the translation (see advanced examples).
 * 
 * 
 * ~~~~~~
 * // COMMON USAGE EXAMPLES --------------------------------------------------------------
 * 
 * echo __('This is translatable text');
 * echo __('Translatable with current file as textdomain (optional)', __FILE__);  
 * echo __('Translatable with other file as textdomain', '/site/templates/_init.php');
 * 
 * // using placeholders to populate dynamic values in translatable text:
 * echo sprintf(__('You are reading the %s page'), $page->title); 
 * echo sprintf(__('%d is the current page ID'), $page->id); 
 * echo sprintf(__('Today is %1$s and the time is %2$s'), date('l'), date('g:i a'));
 * 
 * // providing a note via PHP comment to the person doing translation
 * echo __('Welcome friend!'); // A friendly welcome message for new users
 * 
 * // ADVANCED EXAMPLES (3.0.125+) -------------------------------------------------------
 * 
 * // using the entityEncode option
 * // true=always encode, 1=encode only if not already, false=never encode, null=undefined
 * __(true, 'entityEncode', true);
 * 
 * // get current entityEncode option value
 * $val = __(true, 'entityEncode');
 * 
 * // Setting predefined translations
 * if($user->language->name == 'es') {
 *   __(true, [
 *     'Hello' => 'Hola',
 *     'World' => 'Mundo'
 *   ]);
 * }
 * 
 * // Setting predefined translations with context
 * __(true, [
 *   // would apply only to a _x('Search', 'nav'); call (context)
 *   'Search' => [ 'Buscar', 'nav' ]
 * ]); 
 * ~~~~~~
 * 
 * #pw-group-translation
 * 
 * @param string|bool $text Text for translation.
 * @param string|array $textdomain Textdomain for the text, may be class name, filename, or something made up by you. 
 *   If omitted, a debug backtrace will attempt to determine it automatically.
 * @param string|bool|array $context Name of context - DO NOT USE with this function for translation as it will not be parsed for translation. 
 *   Use only with the `_x()` function, which will be parsed. 
 * @return string|array Translated text or original text if translation not available. Returns array only if getting/setting options.
 * @see _x(), _n()  
 * @link https://processwire.com/docs/multi-language-support/code-i18n/
 *
 */
function __($text, $textdomain = null, $context = '') {
	static $options = array(
		'entityEncode' => null, // true=always, false=never, 1=only if not already encoded, null=undefined (backwards compatible behavior)
		'translations' => array(), // fallback translations to use when live translation not available ['Original text' => 'Translated text']
		'_useLimit' => null, // internal use: use limit argument for debug_backtrace call
	);
	if($text === true) {
		// set and get options
		if(is_array($textdomain)) {
			// translations specified as array in $textdomain argument
			$context = $textdomain;
			$textdomain = 'translations';
		}
		// merge existing translations if specified
		if($textdomain == 'translations' && is_array($context)) $context = array_merge($options['translations'], $context);
		if($context !== '') $options[$textdomain] = $context;
		return $options[$textdomain];
	}
	if(!wire('languages') || (!$language = wire('user')->language) || !$language->id) {
		// multi-language not installed or not available
		return $options['entityEncode'] ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8', $options['entityEncode'] === true) : $text;
	}
	/** @var Language $language */
	if($options['_useLimit'] === null) {
		$options['_useLimit'] = version_compare(PHP_VERSION, '5.4.0') >= 0;
	}
	if($textdomain === null) {
		if($options['_useLimit']) {
			// PHP 5.4.0 or newer
			$traces = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		} else if(defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
			// PHP 5.3.6 or newer
			$traces = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		} else {
			// older PHP (deprecated)
			$traces = @debug_backtrace();
		}
		if(isset($traces[0]) && $traces[0]['file'] != __FILE__) {
			$textdomain = $traces[0]['file'];
		} else if(isset($traces[1]) && $traces[1]['file'] != __FILE__) {
			$textdomain = $traces[1]['file'];
		}
		if(is_null($textdomain)) $textdomain = 'site';
	} else if($textdomain === 'common') {
		// common translation
		$textdomain = 'wire/modules/LanguageSupport/LanguageTranslator.php';
	}
	$value = $language->translator()->getTranslation($textdomain, $text, $context);
	$encode = $options['entityEncode'];
	if($value === "=") {
		// translated value should be same as source value
		$value = $text;
	} else if($value === "+") {
		// use common translation value if available
		$v = $language->translator()->commonTranslation($text);
		$value = empty($v) ? $text : $v;
	} else {
		// regular translation 
		// if translated value same as original check if alternate available in pre-defined translations
		if($value === $text && isset($options['translations']["$text"])) {
			$value = $options['translations']["$text"];
			// array for translation means only apply to named context, ie. 'old text' => [ 'new text', 'context' ]
			if(is_array($value)) $value = isset($value[1]) && $value[1] === $context ? $value[0] : $text;
		}
		// force original behavior fallback if encode mode not set (i.e. encode when translation available)
		if($encode === null) $encode = 1; 
	}
	if($encode) $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $encode === true);
	return $value;
}

/**
 * Perform a language translation in a specific context
 * 
 * Used when two or more text strings might be the same in default language, but different in other languages. 
 * This enables you to limit the context of the translation to a named context, like "button" or "headline" or 
 * whatever name you decide to use. 
 * 
 * ~~~~~
 * echo _x('Click for more', 'button');
 * echo _x('Click for more', 'text-link'); 
 * ~~~~~
 * 
 * #pw-group-translation
 * 
 * @param string $text Text for translation. 
 * @param string $context Name of context
 * @param string $textdomain Textdomain for the text, may be class name, filename, or something made up by you. 
 *   If omitted, a debug backtrace will attempt to determine automatically.
 * @return string Translated text or original text if translation not available.
 * @see __(), _n()
 * @link https://processwire.com/docs/multi-language-support/code-i18n/
 *
 */
function _x($text, $context, $textdomain = null) {
	return __($text, $textdomain, $context); 	
}

/**
 * Perform a language translation with singular and plural versions
 * 
 * ~~~~~
 * $items = array(...);
 * $qty = count($items);
 * echo _n('Found one item', 'Found multiple items', $qty); 
 * echo sprintf(_n('Found one item', 'Found %d items', $qty), $qty);
 * ~~~~~
 * 
 * #pw-group-translation
 * 
 * @param string $textSingular Singular version of text (when there is 1 item)
 * @param string $textPlural Plural version of text (when there are multiple items or 0 items)
 * @param int $count Quantity of items, should be 0 or more.
 * @param string $textdomain Textdomain for the text, may be class name, filename, or something made up by you. 
 *   If omitted, a debug backtrace will attempt to determine automatically.
 * @return string Translated text or original text if translation not available.
 * @see __(), _x()
 * @link https://processwire.com/docs/multi-language-support/code-i18n/
 *
 */
function _n($textSingular, $textPlural, $count, $textdomain = null) {
	return $count == 1 ? __($textSingular, $textdomain) : __($textPlural, $textdomain); 	
}


