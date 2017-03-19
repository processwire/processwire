<?php namespace ProcessWire;

/**
 * ProcessWire Functions
 *
 * Common API functions useful outside of class scope
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Return a ProcessWire API variable, or NULL if it doesn't exist
 *
 * And the wire() function is the recommended way to access the API when included from other PHP scripts.
 * Like the fuel() function, except that ommitting $name returns the current ProcessWire instance rather than the fuel.
 * The distinction may not matter in most cases.
 *
 * @param string $name If omitted, returns a Fuel object with references to all the fuel.
 * @return null|ProcessWire|Wire|Session|Page|Pages|Modules|User|Users|Roles|Permissions|Templates|Fields|Fieldtypes|Sanitizer|Config|Notices|WireDatabasePDO|WireHooks|WireDateTime|WireFileTools|WireMailTools|WireInput|string|mixed
 *
 */
function wire($name = 'wire') {
	return ProcessWire::getCurrentInstance()->wire($name); 
}

/**
 * Return all Fuel, or specified ProcessWire API variable, or NULL if it doesn't exist.
 *
 * Same as Wire::getFuel($name) and Wire::getAllFuel();
 * When a $name is specified, this function is identical to the wire() function.
 * Both functions exist more for consistent naming depending on usage. 
 *
 * @deprecated
 * @param string $name If omitted, returns a Fuel object with references to all the fuel.
 * @return mixed Fuel value if available, NULL if not. 
 *
 */
function fuel($name = '') {
	return wire($name);
}


/**
 * Indent the given string with $numTabs tab characters
 *
 * Newlines are assumed to be \n
 * 
 * Watch out when using this function with strings that have a <textarea>, you may want to have it use \r newlines, at least temporarily. 
 *
 * @param string $str String that needs the tabs
 * @param int $numTabs Number of tabs to insert per line (note any existing tabs are left as-is, so indentation is retained)
 * @param string $str The provided string but with tabs inserted
 *
 */
if(!function_exists("tabIndent")): 
	function tabIndent($str, $numTabs) {
		$tabs = str_repeat("\t", $numTabs);
		$str = str_replace("\n", "\n$tabs", $str);
		return $str;
	}
endif; 

/**
 * Encode array for storage and remove empty values
 *
 * Uses json_encode and works the same way except this function clears out empty root-level values.
 * It also forces number strings that can be integers to be integers. 
 *
 * The end result of all this is more optimized JSON.
 *
 * Use json_encode() instead if you don't want any empty values removed. 
 *
 * @param array $data Array to be encoded to JSON
 * @param bool|array $allowEmpty Should empty values be allowed in the encoded data? 
 *	- Specify false to exclude all empty values (this is the default if not specified). 
 * 	- Specify true to allow all empty values to be retained.
 * 	- Specify an array of keys (from data) that should be retained if you want some retained and not others.
 *  - Specify array of literal empty value types to retain, i.e. [ 0, '0', array(), false, null ].
 * 	- Specify the digit 0 to retain values that are 0, but not other types of empty values.
 * @param bool $beautify Beautify the encoded data when possible for better human readability? (requires PHP 5.4+)
 * @return string String of JSON data
 *
 */
function wireEncodeJSON(array $data, $allowEmpty = false, $beautify = false) {
	if($allowEmpty !== true) {
		/** @var Sanitizer $sanitizer */
		$sanitizer = wire('sanitizer');
		$data = $sanitizer->minArray($data, $allowEmpty, true);
	}
	if(!count($data)) return '';
	$flags = 0; 
	if($beautify && defined("JSON_PRETTY_PRINT")) $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
	return json_encode($data, $flags);
}


/**
 * Decode JSON to array
 *
 * Uses json_decode and works the same way except that arrays are forced.
 * This is the counterpart to the wireEncodeJSON() function.
 *
 * @param string $json A JSON encoded string
 * @return array
 *
 */
function wireDecodeJSON($json) {
	if(empty($json) || $json == '[]') return array();
	return json_decode($json, true);
}


/**
 * Minimize an array to remove empty values
 *
 * @param array $data Array to reduce
 * @param bool|array $allowEmpty Should empty values be allowed in the encoded data?
 *	- Specify false to exclude all empty values (this is the default if not specified).
 * 	- Specify true to allow all empty values to be retained (thus no point in calling this function). 
 * 	- Specify an array of keys (from data) that should be retained if you want some retained and not others.
 * 	- Specify the digit 0 to retain values that are 0, but not other types of empty values.
 * @param bool $convert Perform type conversions where appropriate: i.e. convert digit-only string to integer
 * @return array
 *
 */
function wireMinArray(array $data, $allowEmpty = false, $convert = false) {
	/** @var Sanitizer $sanitizer */
	$sanitizer = wire('sanitizer');
	return $sanitizer->minArray($data, $allowEmpty, $convert);
}


/**
 * Create a directory that is writable to ProcessWire and uses the $config chmod settings
 * 
 * @param string $path
 * @param bool $recursive If set to true, all directories will be created as needed to reach the end. 
 * @param string $chmod Optional mode to set directory to (default: $config->chmodDir), format must be a string i.e. "0755"
 * 	If omitted, then ProcessWire's $config->chmodDir setting is used instead. 
 * @return bool
 *
 */ 
function wireMkdir($path, $recursive = false, $chmod = null) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->mkdir($path, $recursive, $chmod);
}

/**
 * Remove a directory
 * 
 * @param string $path
 * @param bool $recursive If set to true, all files and directories in $path will be recursively removed as well.
 * @return bool
 *
 */ 
function wireRmdir($path, $recursive = false) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->rmdir($path, $recursive);
}

/**
 * Change the mode of a file or directory, consistent with PW's chmodFile/chmodDir settings
 * 
 * @param string $path May be a directory or a filename
 * @param bool $recursive If set to true, all files and directories in $path will be recursively set as well.
 * @param string $chmod If you want to set the mode to something other than PW's chmodFile/chmodDir settings, 
 *   you may override it by specifying it here. Ignored otherwise. Format should be a string, like "0755".
 * @return bool Returns true if all changes were successful, or false if at least one chmod failed. 
 * @throws WireException when it receives incorrect chmod format
 *
 */ 
function wireChmod($path, $recursive = false, $chmod = null) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->chmod($path, $recursive, $chmod);
}

/**
 * Copy all files in directory $src to directory $dst
 * 
 * The default behavior is to also copy directories recursively. 
 * 
 * @param string $src Path to copy files from
 * @param string $dst Path to copy files to. Directory is created if it doesn't already exist.
 * @param bool|array Array of options: 
 * 	- recursive (boolean): Whether to copy directories within recursively. (default=true)
 * 	- allowEmptyDirs (boolean): Copy directories even if they are empty? (default=true)
 * 	- If a boolean is specified for $options, it is assumed to be the 'recursive' option. 
 * @return bool True on success, false on failure.
 * 
 */
function wireCopy($src, $dst, $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->copy($src, $dst, $options);
}

/**
 * Unzips the given ZIP file to the destination directory
 * 
 * @param string $file ZIP file to extract
 * @param string $dst Directory where files should be unzipped into. Directory is created if it doesn't exist.
 * @return array Returns an array of filenames (excluding $dst) that were unzipped.
 * @throws WireException All error conditions result in WireException being thrown.
 * 
 */
function wireUnzipFile($file, $dst) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->unzip($file, $dst);
}

/**
 * Creates a ZIP file
 * 
 * @param string $zipfile Full path and filename to create or update (i.e. /path/to/myfile.zip)
 * @param array|string $files Array of files to add (full path and filename), or directory (string) to add.
 * 	If given a directory, it will recursively add everything in that directory.
 * @param array $options Associative array of:
 * 	- allowHidden (boolean or array): allow hidden files? May be boolean, or array of hidden files (basenames) you allow. (default=false)
 * 		Note that if you actually specify a hidden file in your $files argument, then that overrides this. 
 * 	- allowEmptyDirs (boolean): allow empty directories in the ZIP file? (default=true)
 * 	- overwrite (boolean): Replaces ZIP file if already present (rather than adding to it) (default=false)
 * 	- exclude (array): Files or directories to exclude
 * 	- dir (string): Directory name to prepend to added files in the ZIP
 * @return array Returns associative array of:
 * 	- files => array(all files that were added), 
 * 	- errors => array(files that failed to add, if any)
 * @throws WireException Original ZIP file creation error conditions result in WireException being thrown.
 * 
 */
function wireZipFile($zipfile, $files, array $options = array()) {
	/** @var WireFileTools $fileTools */
	$fileTools = wire('files');
	return $fileTools->zip($zipfile, $files, $options);
}

/**
 * Send the contents of the given filename via http
 *
 * This function utilizes the $content->fileContentTypes to match file extension
 * to content type headers and force-download state. 
 *
 * This function throws a WireException if the file can't be sent for some reason.
 *
 * @param string $filename Filename to send
 * @param array $options Options that you may pass in, see $_options in function for details.
 * @param array $headers Headers that are sent, see $_headers in function for details. 
 *	To remove a header completely, make its value NULL and it won't be sent.
 * @throws WireException
 *
 */
function wireSendFile($filename, array $options = array(), array $headers = array()) {
	$http = new WireHttp();
	$http->sendFile($filename, $options, $headers);
}

/**
 * Given a unix timestamp (or date string), returns a formatted string indicating the time relative to now
 *
 * Example: 1 day ago, 30 seconds ago, etc. 
 *
 * Based upon: http://www.php.net/manual/en/function.time.php#89415
 *
 * @param int|string $ts Unix timestamp or date string
 * @param bool|int|array $abbreviate Whether to use abbreviations for shorter strings. 
 * 	Specify boolean TRUE for abbreviations (abbreviated where common, not always different from non-abbreviated)
 * 	Specify integer 1 for extra short abbreviations (all terms abbreviated into shortest possible string)
 * 	Specify boolean FALSE or omit for no abbreviations.
 * 	Specify associative array of key=value pairs of terms to use for abbreviations. The possible keys are:
 * 		just now, ago, from now, never
 * 		second, minute, hour, day, week, month, year, decade
 * 		seconds, minutes, hours, days, weeks, months, years, decades
 * @param bool $useTense Whether to append a tense like "ago" or "from now",
 * 	May be ok to disable in situations where all times are assumed in future or past.
 * 	In abbreviate=1 (shortest) mode, this removes the leading "+" or "-" from the string. 
 * @return string
 *
 */
function wireRelativeTimeStr($ts, $abbreviate = false, $useTense = true) {
	/** @var WireDateTime $datetime */
	$datetime = wire('datetime');
	return $datetime->relativeTimeStr($ts, $abbreviate, $useTense);
}


/**
 * Send an email or retrieve the mailer object
 *
 * Note 1: The order of arguments is different from PHP's mail() function. 
 * Note 2: If no arguments are specified it simply returns a WireMail object (see #4 below).
 *
 * This function will attempt to use an installed module that extends WireMail.
 * If no module is installed, WireMail (which uses PHP mail) will be used instead.
 *
 * This function can be called in these ways:
 *
 * 1. Default usage: 
 * 
 *    wireMail($to, $from, $subject, $body, $options); 
 * 
 *
 * 2. Specify body and/or bodyHTML in $options array (perhaps with other options): 
 * 
 *    wireMail($to, $from, $subject, $options); 
 *
 *
 * 3. Specify both $body and $bodyHTML as arguments, but no $options: 
 * 
 *    wireMail($to, $from, $subject, $body, $bodyHTML); 
 * 
 *
 * 4. Specify a blank call to wireMail() to get the WireMail sending object. This can
 *    be either WireMail() or a class descending from it. If a WireMail descending
 *    module is installed, it will be returned rather than WireMail():
 * 
 *    $mail = wireMail(); 
 *    $mail->to('user@domain.com')->from('you@company.com'); 
 *    $mail->subject('Mail Subject')->body('Mail Body Text')->bodyHTML('Body HTML'); 
 *    $numSent = $mail->send();
 * 
 *
 * @param string|array $to Email address TO. For multiple, specify CSV string or array. 
 * @param string $from Email address FROM. This may be an email address, or a combined name and email address. 
 *	Example of combined name and email: Karen Cramer <karen@processwire.com>
 * @param string $subject Email subject
 * @param string|array $body Email body or omit to move straight to $options
 * @param array|string $options Array of options OR the $bodyHTML string. Array $options are:
 * 	body: string
 * 	bodyHTML: string
 * 	headers: associative array of header name => header value
 *	Any additional options will be sent along to the WireMail module or class, in tact.
 * @return int|WireMail Returns number of messages sent or WireMail object if no arguments specified. 
 *
 */
function wireMail($to = '', $from = '', $subject = '', $body = '', $options = array()) { 
	/** @var WireMail $mail */
	$mail = wire('mail');
	return $mail->send($to, $from, $subject, $body, $options);
}


/**
 * Given a string $str and values $vars, replace tags in the string with the values
 *
 * The $vars may also be an object, in which case values will be pulled as properties of the object. 
 *
 * By default, tags are specified in the format: {first_name} where first_name is the name of the
 * variable to pull from $vars, '{' is the opening tag character, and '}' is the closing tag char.
 *
 * The tag parser can also handle subfields and OR tags, if $vars is an object that supports that.
 * For instance {products.title} is a subfield, and {first_name|title|name} is an OR tag. 
 *
 * @param string $str The string to operate on (where the {tags} might be found)
 * @param WireData|object|array Object or associative array to pull replacement values from. 
 * @param array $options Array of optional changes to default behavior, including: 
 * 	- tagOpen: The required opening tag character(s), default is '{'
 *	- tagClose: The optional closing tag character(s), default is '}'
 *	- recursive: If replacement value contains tags, populate those too? Default=false. 
 *	- removeNullTags: If a tag resolves to a NULL, remove it? If false, tag will remain. Default=true. 
 *	- entityEncode: Entity encode the values pulled from $vars? Default=false. 
 *	- entityDecode: Entity decode the values pulled from $vars? Default=false.
 * @return string String with tags populated. 
 *
 */
function wirePopulateStringTags($str, $vars, array $options = array()) {

	$defaults = array(
		// opening tag (required)
		'tagOpen' => '{', 
		// closing tag (optional)
		'tagClose' => '}', 
		// if replacement value contains tags, populate those too?
		'recursive' => false, 
		// if a tag value resolves to a NULL, remove it? If false, tag will be left in tact.
		'removeNullTags' => true, 
		// entity encode values pulled from $vars?
		'entityEncode' => false, 	
		// entity decode values pulled from $vars?
		'entityDecode' => false, 
	);

	$options = array_merge($defaults, $options); 

	// check if this string even needs anything populated
	if(strpos($str, $options['tagOpen']) === false) return $str; 
	if(strlen($options['tagClose']) && strpos($str, $options['tagClose']) === false) return $str; 

	// find all tags
	$tagOpen = preg_quote($options['tagOpen']);
	$tagClose = preg_quote($options['tagClose']); 
	$numFound = preg_match_all('/' . $tagOpen . '([-_.|a-zA-Z0-9]+)' . $tagClose . '/', $str, $matches);
	if(!$numFound) return $str; 
	$replacements = array();

	// create a list of replacements by finding replacement values in $vars
	foreach($matches[1] as $key => $fieldName) {

		$tag = $matches[0][$key];
		if(isset($replacements[$tag])) continue; // if already found, don't continue
		$fieldValue = null;
		
		if(is_object($vars)) {
			if($vars instanceof Page) {
				$fieldValue = $vars->getMarkup($fieldName);
				
			} else if($vars instanceof WireData) {
				$fieldValue = $vars->get($fieldName);
				
			} else {
				$fieldValue = $vars->$fieldName;
			}
		} else if(is_array($vars)) {
			$fieldValue = isset($vars[$fieldName]) ? $vars[$fieldName] : null;
		}

		if($options['entityEncode']) $fieldValue = htmlentities($fieldValue, ENT_QUOTES, 'UTF-8', false); 
		if($options['entityDecode']) $fieldValue = html_entity_decode($fieldValue, ENT_QUOTES, 'UTF-8'); 

		$replacements[$tag] = $fieldValue; 
	}

	// replace the tags 
	foreach($replacements as $tag => $value) {

		// populate tags recursively, if asked to do so
		if($options['recursive'] && strpos($value, $options['tagOpen'])) {
			$opt = array_merge($options, array('recursive' => false)); // don't go recursive beyond 1 level
			$value = wirePopulateStringTags($value, $vars, $opt); 
		}

		// replace tags with replacement values
		if($value !== null || $options['removeNullTags']) {
			$str = str_replace($tag, (string) $value, $str);
		}
	}

	return $str; 
}


/**
 * Return a new temporary directory/path ready to use for files
 * 
 * @param object|string $name Provide the object that needs the temp dir, or name your own string
 * @param array|int $options Options array: 
 * 	- maxAge: Maximum age of temp dir files in seconds (default=120)
 * 	- basePath: Base path where temp dirs should be created. Omit to use default (recommended).
 * 	Note: if you specify an integer for $options, then $maxAge is assumed. 
 * @return WireTempDir
 * 
 */
function wireTempDir($name, $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->tempDir($name, $options);
}

/**
 * Given a filename, render it as a ProcessWire template file
 * 
 * This is a shortcut to using the TemplateFile class. 
 * 
 * File is assumed relative to /site/templates/ (or a directory within there) unless you specify a full path. 
 * If you specify a full path, it will accept files in or below site/templates/, site/modules/, wire/modules/.
 * 
 * Note this function returns the output for you to output wherever you want (delayed output).
 * For direct output, use the wireInclude() function instead. 
 * 
 * @param string $filename Assumed relative to /site/templates/ unless you provide a full path name with the filename.
 * 	If you provide a path, it must resolve somewhere in site/templates/, site/modules/ or wire/modules/.
 * @param array $vars Optional associative array of variables to send to template file. 
 * 	Please note that all template files automatically receive all API variables already (you don't have to provide them)
 * @param array $options Associative array of options to modify behavior: 
 * 	- defaultPath: Path where files are assumed to be when only filename or relative filename is specified (default=/site/templates/)
 *  - autoExtension: Extension to assume when no ext in filename, make blank for no auto assumption (default=php) 
 * 	- allowedPaths: Array of paths that are allowed (default is templates, core modules and site modules)
 * 	- allowDotDot: Allow use of ".." in paths? (default=false)
 * 	- throwExceptions: Throw exceptions when fatal error occurs? (default=true)
 * @return string|bool Rendered template file or boolean false on fatal error (and throwExceptions disabled)
 * @throws WireException if template file doesn't exist
 * 
 */
function wireRenderFile($filename, array $vars = array(), array $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->render($filename, $vars, $options);
}

/**
 * Include a PHP file passing it all API variables and optionally your own specified variables
 * 
 * This is the same as PHP's include() function except for the following: 
 * - It receives all API variables and optionally your custom variables
 * - If your filename is not absolute, it doesn't look in PHP's include path, only in the current dir.
 * - It only allows including files that are part of the PW installation: templates, core modules or site modules
 * - It will assume a ".php" extension if filename has no extension.
 * 
 * Note this function produced direct output. To retrieve output as a return value, use the 
 * wireTemplateFile function instead. 
 * 
 * @param $filename
 * @param array $vars Optional variables you want to hand to the include (associative array)
 * @param array $options Array of options to modify behavior: 
 * 	- func: Function to use: include, include_once, require or require_once (default=include)
 *  - autoExtension: Extension to assume when no ext in filename, make blank for no auto assumption (default=php) 
 * 	- allowedPaths: Array of paths include files are allowed from. Note current dir is always allowed.
 * @return bool Returns true 
 * @throws WireException if file doesn't exist or is not allowed
 * 
 */
function wireIncludeFile($filename, array $vars = array(), array $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->include($filename, $vars, $options);
}

/**
 * Format a date, using PHP date(), strftime() or other special strings (see arguments).
 * 
 * This is designed to work the same wa as PHP's date() but be able to accept any common format
 * used in ProcessWire. This is helpful in reducing code in places where you might have logic 
 * determining when to use date(), strftime(), or wireRelativeTimeStr(). 
 * 
 * @param string|int $format Use one of the following:
 *  - PHP date() format
 * 	- PHP strftime() format (detected by presence of a '%' somewhere in it)
 * 	- 'relative': for a relative date/time string.
 *  - 'relative-': for a relative date/time string with no tense. 
 * 	- 'rel': for an abbreviated relative date/time string.
 * 	- 'rel-': for an abbreviated relative date/time string with no tense.
 * 	- 'r': for an extra-abbreviated relative date/time string.
 * 	- 'r-': for an extra-abbreviated relative date/time string with no tense.
 * 	- 'ts': makes it return a unix timestamp
 * 	- '': blank string makes it use the system date format ($config->dateFormat) 
 * 	- If given an integer and no second argument specified, it is assumed to be the second ($ts) argument. 
 * @param int|string|null $ts Optionally specify the date/time stamp or strtotime() compatible string. 
 * 	If not specified, current time is used.
 * @return string|bool Formatted date/time, or boolean false on failure
 * 
 */
function wireDate($format = '', $ts = null) {
	/** @var WireDateTime $datetime */
	$datetime = wire('datetime');
	return $datetime->date($format, $ts);
}


/**
 * Render markup for an icon
 * 
 * Icon and class can be specified with or without the fa- prefix. 
 * 
 * @param string $icon Icon name (currently a font-awesome icon name, but support for more in future)
 * @param string $class Additional attributes for class (example: "fw" for fixed width)
 * @return string
 * 
 */
function wireIconMarkup($icon, $class = '') {
	if(empty($icon)) return '';
	if(strpos($icon, 'icon-') === 0) $icon = str_replace('icon-', 'fa-', $icon); 
	if(strpos($icon, 'fa-') !== 0) $icon = "fa-$icon";
	if($class) {
		$modifiers = array(
			'lg', 'fw', '2x', '3x', '4x', '5x', 'spin', 'spinner', 'li', 'border',
			'rotate-90', 'rotate-180', 'rotate-270', 'flip-horizontal', 'flip-vertical',
			'stack', 'stack-1x', 'stack-2x', 'inverse',
		);
		$classes = explode(' ', $class); 
		foreach($classes as $key => $modifier) {
			if(in_array($modifier, $modifiers)) $classes[$key] = "fa-$modifier";	
		}
		$class = implode(' ', $classes);
	}
	$class = trim("fa $icon $class"); 
	return "<i class='$class'></i>";
}

/**
 * Get the markup or class name for an icon that can represent the given filename
 * 
 * @param string $filename Can be any type of filename (with or without path)
 * @param string|bool $class Additional class attributes (optional). 
 * 	Or specify boolean TRUE to get just the icon class name (no markup). 
 * @return string 
 * 
 */
function wireIconMarkupFile($filename, $class = '') {
	$icon = 'file-o';
	$icons = array(
		'pdf' => 'file-pdf-o',
		'doc' => 'file-word-o',
		'docx' => 'file-word-o',
		'xls' => 'file-excel-o',
		'xlsx' => 'file-excel-o',
		'xlsb' => 'file-excel-o',
		'csv' => 'file-excel-o',
		'zip' => 'file-archive-o',
		'txt' => 'file-text-o',
		'rtf' => 'file-text-o',
		'mp3' => 'file-sound-o',
		'wav' => 'file-sound-o',
		'ogg' => 'file-sound-o',
		'jpg' => 'file-image-o',
		'jpeg' => 'file-image-o',
		'png' => 'file-image-o',
		'gif' => 'file-image-o',
		'svg' => 'file-image-o',
		'ppt' => 'file-powerpoint-o',
		'pptx' => 'file-powerpoint-o',
		'mov' => 'file-video-o',
		'mp4' => 'file-video-o',
		'wmv' => 'file-video-o',
		'js' => 'file-code-o',
		'css' => 'file-code-o',
	);
	$pos = strrpos($filename, '.'); 
	$ext = $pos !== false ? substr($filename, $pos+1) : '';
	if($ext && isset($icons[$ext])) $icon = $icons[$ext];
	return $class === true ? "fa-$icon" : wireIconMarkup($icon, $class);
}

/**
 * Given a quantity of bytes, return a more readable size string
 * 
 * @param int $size
 * @return string
 * 
 */
function wireBytesStr($size) {
	if($size < 1024) return number_format($size) . ' ' . __('bytes', __FILE__);
	$kb = round($size / 1024);
	return number_format($kb) . " " . __('kB', __FILE__); // kilobytes
}

/**
 * Normalize a class name with or without namespace
 * 
 * Can also be used in an equivalent way to PHP's get_class() function. 
 * 
 * @param string|object $className
 * @param bool|int|string $withNamespace Should return value include namespace? (default=false) 
 * 	or specify integer 1 to return only namespace (i.e. "ProcessWire", no leading or trailing backslashes)
 * @return string|null Returns string or NULL if namespace-only requested and unable to determine
 * 
 */
function wireClassName($className, $withNamespace = false) {
	
	if(is_object($className)) $className = get_class($className);
	$pos = strrpos($className, "\\");
	
	if($withNamespace === true) {
		// return class with namespace, substituting ProcessWire namespace if none present
		if($pos === false && __NAMESPACE__) $className = __NAMESPACE__ . "\\$className";
		
	} else if($withNamespace === 1) {
		// return namespace only
		if($pos !== false) {
			// there is a namespace
			$className = substr($className, 0, $pos);
		} else {
			// there is no namespace in given className
			$className = null;
		}
			
	} else {
		// return className without namespace
		if($pos !== false) $className = substr($className, $pos+1);
	}
	
	return $className;
}

/**
 * ProcessWire namespace aware version of PHP's class_exists() function
 * 
 * @param string $className
 * @param bool $autoload
 * @return bool
 * 
 */
function wireClassExists($className, $autoload = true) {
	if(!is_object($className)) $className = wireClassName($className, true);
	return class_exists($className, $autoload);
}

/**
 * ProcessWire namespace aware version of PHP's method_exists() function
 *
 * @param string $className
 * @param string $method
 * @return bool
 *
 */
function wireMethodExists($className, $method) {
	if(!is_object($className)) $className = wireClassName($className, true);
	return method_exists($className, $method);
}

/**
 * ProcessWire namespace aware version of PHP's class_implements() function
 *
 * @param string|object $className
 * @param bool $autoload
 * @return array
 *
 */
function wireClassImplements($className, $autoload = true) {
	if(is_object($className)) {
		$implements = @class_implements($className, $autoload);
	} else {
		$className = wireClassName($className, true);
		if(!class_exists($className, false)) {
			$_className = wireClassName($className, false);
			if(class_exists("\\$_className")) $className = $_className;
		}
		$implements = @class_implements(ltrim($className, "\\"), $autoload);
	}
	$a = array();
	if(is_array($implements)) foreach($implements as $k => $v) {
		$v = wireClassName($k, false);
		$a[$k] = $v; // values have no namespace
	}
	return $a; 
}

/**
 * ProcessWire namespace aware version of PHP's class_parents() function
 * 
 * Returns associative array where array keys are full namespaced class name, and 
 * values are the non-namespaced classname.
 *
 * @param string|object $className
 * @param bool $autoload
 * @return array
 *
 */
function wireClassParents($className, $autoload = true) {
	if(is_object($className)) {
		$parents = class_parents($className, $autoload);
	} else {
		$className = wireClassName($className, true);
		if(!class_exists($className, false)) {
			$_className = wireClassName($className, false);
			if(class_exists("\\$_className")) $className = $_className;
		}
		$parents = class_parents(ltrim($className, "\\"), $autoload);
	}
	$a = array();
	if(is_array($parents)) foreach($parents as $k => $v) {
		$v = wireClassName($k, false);
		$a[$k] = $v; // values have no namespace
	}
	return $a; 
}

/**
 * ProcessWire namespace aware version of PHP's is_callable() function
 *
 * @param string|callable $var
 * @param bool $syntaxOnly
 * @var string $callableName
 * @return array
 *
 */
function wireIsCallable($var, $syntaxOnly = false, &$callableName = '') {
	if(is_string($var)) $var = wireClassName($var, true);
	return is_callable($var, $syntaxOnly, $callableName);
}

/**
 * Get or set an output region (primarily for front-end output usage)
 *
 * ~~~~~
 * // define a region
 * region('content', '<p>this is some content</p>');
 *
 * // prepend some text to region
 * region('+content', '<h2>Good morning</h2>');
 *
 * // append some text to region
 * region('content+', '<p><small>Good night</small></p>');
 *
 * // output a region
 * echo region('content');
 *
 * // get all regions in an array
 * $regions = region('*');
 *
 * // clear the 'content' region
 * region('content', '');
 *
 * // clear all regions
 * region('*', '');
 * ~~~~~
 *
 * @param string $key Name of region to get or set.
 *  - Specify "*" to retrieve all defined regions in an array.
 *  - Prepend a "+" to the region name to have it prepend your given value to any existing value.
 *  - Append a "+" to the region name to have it append your given value to any existing value.
 *  - Prepend a "++" to region name to make future calls without "+" automatically prepend. 
 *  - Append a "++" to region name to make future calls without "+" to automatically append. 
 * @param null|string $value If setting a region, the text that you want to set.
 * @return string|null|bool|array Returns string of text when getting a region, NULL if region not set, or TRUE if setting region.
 *
 */
function wireRegion($key, $value = null) {
	
	static $regions = array();
	static $locked = array();

	if(empty($key) || $key === '*') {
		// all regions
		if($value === '') $regions = array(); // clear
		return $regions;
	}

	if(is_null($value)) {
		// get region
		$result = isset($regions[$key]) ? $regions[$key] : null;

	} else {
		// set region
		$pos = strpos($key, '+');
		if($pos !== false) {
			$lock = strpos($key, '++') !== false;
			$key = trim($key, '+');
			if($lock !== false && !isset($locked[$key])) {
				$locked[$key] = $lock === 0 ? '^' : '$'; // prepend : append
			}
		}
		$lock = isset($locked[$key]) ? $locked[$key] : '';
		if(!isset($regions[$key])) $regions[$key] = '';
		if($pos === 0 || ($pos === false && $lock == '^')) {
			// prepend
			$regions[$key] = $value . $regions[$key];
		} else if($pos || ($pos === false && $lock == '$')) {
			// append
			$regions[$key] .= $value;
		} else if($value === '') {
			// clear region
			if(!$lock) unset($regions[$key]);
		} else {
			// insert/replace
			$regions[$key] = $value;
		}
		$result = true;
	}

	return $result;
}

