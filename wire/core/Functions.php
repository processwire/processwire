<?php namespace ProcessWire;

/**
 * ProcessWire Functions
 *
 * Common API functions useful outside of class scope
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary-arrays These shortcuts for creating WireArray types are available in ProcessWire 3.0.123 and newer. 
 * #pw-summary-files These file system functions are procedural versions of some of those provided by the `$files` API variable.
 *
 */

/**
 * Return an API variable, or return current ProcessWire instance if given no arguments 
 *
 * - Call `wire()` with no arguments returns the current ProcessWire instance. 
 * - Call `wire('var')` to return the API variable represented by 'var', or null if not present.
 * - Call `wire('all')` or `wire('*')` to return an iterable object of all API variables. 
 * - Call `wire($object)` to attach $object to the current instance ($object must be Wire-derived object). 
 * 
 * #pw-group-common
 * #pw-group-Functions-API
 *
 * @param string $name If omitted, returns current ProcessWire instance. 
 * @return null|ProcessWire|Wire|Session|Page|Pages|Modules|User|Users|Roles|Permissions|Templates|Fields|Fieldtypes|Sanitizer|Config|Notices|WireDatabasePDO|WireHooks|WireDateTime|WireFileTools|WireMailTools|WireInput|string|mixed Requested API variable or null if it does not exist
 *
 */
function wire($name = 'wire') {
	return ProcessWire::getCurrentInstance()->wire($name); 
}

/**
 * Get or set the current ProcessWire instance
 * 
 * #pw-group-common
 * 
 * @param Wire|null $wire To set specify ProcessWire instance or any Wire-derived object in it, or omit to get current instance.
 * @return ProcessWire
 * @since 3.0.125
 * 
 */
function wireInstance(?Wire $wire = null) {
	if($wire === null) return ProcessWire::getCurrentInstance();
	if(!$wire instanceof ProcessWire) $wire = $wire->wire();
	ProcessWire::setCurrentInstance($wire);
	return $wire;
}

/**
 * Return all Fuel, or specified ProcessWire API variable, or NULL if it doesn't exist.
 *
 * Same as Wire::getFuel($name) and Wire::getAllFuel();
 * When a $name is specified, this function is identical to the wire() function.
 * Both functions exist more for consistent naming depending on usage. 
 * 
 * #pw-internal
 *
 * @deprecated
 * @param string $name If omitted, returns a Fuel object with references to all the fuel.
 * @return mixed Fuel value if available, NULL if not. 
 *
 */
function fuel($name = '') {
	return wire($name);
}


if(!function_exists("tabIndent")):
	/**
	 * Indent the given string with $numTabs tab characters
	 *
	 * Newlines are assumed to be \n
	 *
	 * Watch out when using this function with strings that have a <textarea>, you may want to have it use \r newlines, at least temporarily.
	 *
	 * #pw-internal
	 *
	 * @param string $str String that needs the tabs
	 * @param int $numTabs Number of tabs to insert per line (note any existing tabs are left as-is, so indentation is retained)
	 * @param string $str The provided string but with tabs inserted
	 * @return string
	 * @deprecated
	 *
	 */
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
 * #pw-internal
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
 * #pw-internal
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
 * #pw-internal 
 *
 * @param array $data Array to reduce
 * @param bool|array $allowEmpty Should empty values be allowed in the encoded data?
 *	- Specify false to exclude all empty values (this is the default if not specified).
 * 	- Specify true to allow all empty values to be retained (thus no point in calling this function). 
 * 	- Specify an array of keys (from data) that should be retained if you want some retained and not others.
 * 	- Specify the digit 0 to retain values that are 0, but not other types of empty values.
 * @param bool $convert Perform type conversions where appropriate: i.e. convert digit-only string to integer
 * @return array
 * @deprecated Use $sanitizer->minArray() instead
 *
 */
function wireMinArray(array $data, $allowEmpty = false, $convert = false) {
	/** @var Sanitizer $sanitizer */
	$sanitizer = wire('sanitizer');
	return $sanitizer->minArray($data, $allowEmpty, $convert);
}

/**
 * Create a directory (optionally recursively) that is writable to ProcessWire and uses the $config chmod settings
 * 
 * This is procedural version of the `$files->mkdir()` method.
 * 
 * #pw-group-files
 * 
 * @param string $path
 * @param bool $recursive If set to true, all directories will be created as needed to reach the end. 
 * @param string $chmod Optional mode to set directory to (default: $config->chmodDir), format must be a string i.e. "0755"
 * 	If omitted, then ProcessWire’s $config->chmodDir setting is used instead. 
 * @return bool
 * @see WireFileTools::mkdir()
 *
 */ 
function wireMkdir($path, $recursive = false, $chmod = null) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->mkdir($path, $recursive, $chmod);
}

/**
 * Remove a directory (optionally recursively)
 * 
 * This is procedural version of the `$files->rmdir()` method. See that method for more options. 
 * 
 * #pw-group-files
 * 
 * @param string $path
 * @param bool $recursive If set to true, all files and directories in $path will be recursively removed as well.
 * @return bool
 * @see WireFileTools::rmdir()
 *
 */ 
function wireRmdir($path, $recursive = false) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->rmdir($path, $recursive);
}

/**
 * Change the mode of a file or directory (optionally recursive)
 * 
 * If no `$chmod` mode argument is specified the `$config->chmodFile` or $config->chmodDir` settings will be used.
 * 
 * This is procedural version of the `$files->chmod()` method.
 * 
 * #pw-group-files
 * 
 * @param string $path May be a directory or a filename
 * @param bool $recursive If set to true, all files and directories in $path will be recursively set as well.
 * @param string $chmod If you want to set the mode to something other than PW's chmodFile/chmodDir settings, 
 *   you may override it by specifying it here. Ignored otherwise. Format should be a string, like "0755".
 * @return bool Returns true if all changes were successful, or false if at least one chmod failed. 
 * @throws WireException when it receives incorrect chmod format
 * @see WireFileTools::chmod()
 *
 */ 
function wireChmod($path, $recursive = false, $chmod = null) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->chmod($path, $recursive, $chmod);
}

/**
 * Copy all files recursively from one directory to another 
 * 
 * This is procedural version of the `$files->copy()` method.
 * 
 * #pw-group-files
 * 
 * @param string $src Path to copy files from
 * @param string $dst Path to copy files to. Directory is created if it doesn’t already exist.
 * @param bool|array Array of options: 
 * 	- `recursive` (bool): Whether to copy directories within recursively. (default=true)
 * 	- `allowEmptyDirs` (bool): Copy directories even if they are empty? (default=true)
 * 	- If a boolean is specified for $options, it is assumed to be the 'recursive' option. 
 * @return bool True on success, false on failure.
 * @see WireFileTools::copy()
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
 * This is procedural version of the `$files->unzip()` method. See that method for more details.
 * 
 * #pw-group-files
 * 
 * @param string $file ZIP file to extract
 * @param string $dst Directory where files should be unzipped into. Directory is created if it doesn’t exist.
 * @return array Returns an array of filenames (excluding $dst) that were unzipped.
 * @throws WireException All error conditions result in WireException being thrown.
 * @see WireFileTools::unzip()
 * 
 */
function wireUnzipFile($file, $dst) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->unzip($file, $dst);
}

/**
 * Create a ZIP file from given files
 * 
 * This is procedural version of the `$files->zip()` method. See that method for all options. 
 * 
 * #pw-group-files
 * 
 * @param string $zipfile Full path and filename to create or update (i.e. /path/to/myfile.zip)
 * @param array|string $files Array of files to add (full path and filename), or directory (string) to add.
 * 	If given a directory, it will recursively add everything in that directory.
 * @param array $options Options modify default behavior:
 * 	- `allowHidden` (bool|array): allow hidden files? May be boolean, or array of hidden files (basenames) you allow. (default=false)
 * 		Note that if you actually specify a hidden file in your $files argument, then that overrides this. 
 * 	- `allowEmptyDirs` (bool): allow empty directories in the ZIP file? (default=true)
 * 	- `overwrite` (bool): Replaces ZIP file if already present (rather than adding to it) (default=false)
 * 	- `exclude` (array): Files or directories to exclude
 * 	- `dir` (string): Directory name to prepend to added files in the ZIP
 * @return array Returns associative array of:
 * 	- `files` (array): all files that were added
 * 	- `errors` (array): files that failed to add, if any
 * @throws WireException Original ZIP file creation error conditions result in WireException being thrown.
 * @see WireFileTools::zip()
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
 * This function utilizes the `$config->fileContentTypes` to match file extension
 * to content type headers and force-download state. 
 *
 * This function throws a WireException if the file can’t be sent for some reason.
 * 
 * This is procedural version of the `$files->send()` method. See that method for all options. 
 * 
 * #pw-group-files
 *
 * @param string $filename Full path and filename to send
 * @param array $options Optional options that you may pass in (see `WireHttp::sendFile()` for details)
 * @param array $headers Optional headers that are sent (see `WireHttp::sendFile()` for details)
 * @throws WireException
 * @see WireHttp::sendFile(), WireFileTools::send()
 *
 */
function wireSendFile($filename, array $options = array(), array $headers = array()) {
	/** @var WireFileTools $fileTools */
	$files = wire('files');
	$files->send($filename, $options, $headers);
}

/**
 * Given a unix timestamp (or date string), returns a formatted string indicating the time relative to now
 *
 * Examples: “1 day ago”, “30 seconds ago”, “just now”, etc.  
 * 
 * This is the procedural version of `$datetime->relativeTimeStr()`.
 *
 * Based upon: http://www.php.net/manual/en/function.time.php#89415
 * 
 * #pw-group-strings
 *
 * @param int|string $ts Unix timestamp or date string
 * @param bool|int|array $abbreviate Whether to use abbreviations for shorter strings. 
 * 	- Specify boolean TRUE for abbreviations (abbreviated where common, not always different from non-abbreviated)
 * 	- Specify integer 1 for extra short abbreviations (all terms abbreviated into shortest possible string)
 * 	- Specify boolean FALSE or omit for no abbreviations.
 * 	- Specify associative array of key=value pairs of terms to use for abbreviations. The possible keys are:
 * 	  just now, ago, from now, never, second, minute, hour, day, week, month, year, decade, seconds, minutes, 
 *    hours, days, weeks, months, years, decades
 * @param bool $useTense Whether to append a tense like “ago” or “from now”,
 * 	May be ok to disable in situations where all times are assumed in future or past.
 * 	In abbreviate=1 (shortest) mode, this removes the leading "+" or "-" from the string. 
 * @return string
 * @see WireDateTime::relativeTimeStr()
 *
 */
function wireRelativeTimeStr($ts, $abbreviate = false, $useTense = true) {
	/** @var WireDateTime $datetime */
	$datetime = wire('datetime');
	return $datetime->relativeTimeStr($ts, $abbreviate, $useTense);
}


/**
 * Send an email or get a WireMail object to populate before send
 *
 * - Please note that the order of arguments is different from PHP’s mail() function. 
 * - If no arguments are specified it simply returns a WireMail object (see #4 below).
 * - This is a procedural version of functions provided by the `$mail` API variable (see that for more options).
 * - This function will attempt to use an installed module that extends WireMail.
 * - If no other WireMail module is installed, the default `WireMail` (which uses PHP mail) will be used instead.
 *
 * ~~~~~~
 * // Default usage: 
 * wireMail($to, $from, $subject, $body, $options); 
 * 
 * // Specify body and/or bodyHTML in $options array (perhaps with other options): 
 * $options = [ 'body' => $body, 'bodyHTML' => $bodyHTML ];
 * wireMail($to, $from, $subject, $options); 
 *
 * // Specify both $body and $bodyHTML as arguments, but no $options: 
 * wireMail($to, $from, $subject, $body, $bodyHTML); 
 * 
 * // Specify a blank call to wireMail() to get the WireMail sending object. This can
 * // be either WireMail() or a class descending from it. If a WireMail descending
 * // module is installed, it will be returned rather than WireMail():
 * $mail = wireMail(); 
 * $mail->to('user@domain.com')->from('you@company.com'); 
 * $mail->subject('Mail Subject')->body('Mail Body Text')->bodyHTML('Body HTML'); 
 * $numSent = $mail->send();
 * 
 * #pw-group-common
 *
 * @param string|array $to Email address TO. For multiple, specify CSV string or array. 
 * @param string $from Email address FROM. This may be an email address, or a combined name and email address. 
 *	Example of combined name and email: `Karen Cramer <karen@processwire.com>`
 * @param string $subject Email subject
 * @param string|array $body Email body or omit to move straight to $options array
 * @param array|string $options Array of options OR the $bodyHTML string. Array $options are:
 *  - `bodyHTML` (string): Email body as HTML. 
 * 	- `body` (string): Email body as plain text. This is created automatically if you only provide $bodyHTML.
 * 	- `headers` (array): Associative array of ['header name' => 'header value']
 *	- Any additional options you provide will be sent along to the WireMail module or class, in tact.
 * @return int|WireMail Returns number of messages sent or WireMail object if no arguments specified. 
 *
 */
function wireMail($to = '', $from = '', $subject = '', $body = '', $options = array()) { 
	/** @var WireMailTools $mail */
	$mail = wire('mail');
	return $mail->send($to, $from, $subject, $body, $options);
}


/**
 * Given a string ($str) and values ($vars), replace “{tags}” in the string with the values
 *
 * - The `$vars` should be an associative array of `[ 'tag' => 'value' ]`.
 * - The `$vars` may also be an object, in which case values will be pulled as properties of the object. 
 *
 * By default, tags are specified in the format: {first_name} where first_name is the name of the
 * variable to pull from $vars, `{` is the opening tag character, and `}` is the closing tag char.
 *
 * The tag parser can also handle subfields and OR tags, if `$vars` is an object that supports that.
 * For instance `{products.title}` is a subfield, and `{first_name|title|name}` is an OR tag. 
 * 
 * ~~~~~
 * $vars = [ 'foo' => 'FOO!', 'bar' => 'BAR!' ];
 * $str = 'This is a test: {foo}, and this is another test: {bar}';
 * echo wirePopulateStringTags($str, $vars); 
 * // outputs: This is a test: FOO!, and this is another test: BAR!
 * ~~~~~
 * 
 * #pw-group-strings
 *
 * @param string $str The string to operate on (where the {tags} might be found)
 * @param WireData|object|array $vars Object or associative array to pull replacement values from. 
 * @param array $options Array of optional changes to default behavior, including: 
 * 	- `tagOpen` (string): The required opening tag character(s), default is '{'
 *	- `tagClose` (string): The optional closing tag character(s), default is '}'
 *	- `recursive` (bool): If replacement value contains tags, populate those too? (default=false)
 *	- `removeNullTags` (bool): If a tag resolves to a NULL, remove it? If false, tag will remain. (default=true)
 *	- `entityEncode` (bool): Entity encode the values pulled from $vars? (default=false)
 *	- `entityDecode` (bool): Entity decode the values pulled from $vars? (default=false)
 * @return string String with tags populated. 
 *
 */
function wirePopulateStringTags($str, $vars, array $options = array()) {
	return wire('sanitizer')->getTextTools()->populatePlaceholders($str, $vars, $options);
}


/**
 * Return a new temporary directory/path ready to use for files
 * 
 * - The directory will be automatically removed after a set period of time (default=120s).
 * - This is a procedural version of the `$files->tempDir()` method. 
 * 
 * ~~~~~
 * $td = wireTempDir('hello-world');
 * $path = (string) $td; // or use $td->get();
 * file_put_contents($path . 'some-file.txt', 'Hello world');
 * ~~~~~
 * 
 * #pw-group-files
 *
 * @param Object|string $name Provide the object that needs the temp dir, or name your own string
 * @param array|int $options Options array to modify default behavior:
 *  - `maxAge` (integer): Maximum age of temp dir files in seconds (default=120)
 *  - `basePath` (string): Base path where temp dirs should be created. Omit to use default (recommended).
 *  - Note: if you specify an integer for $options, then 'maxAge' is assumed.
 * @return WireTempDir If you typecast return value to a string, it is the temp dir path (with trailing slash).
 * @see WireFileTools::tempDir(), WireTempDir
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
 * This is a shortcut to using the `TemplateFile` class, as well as the procedural version of `$files->render()`.
 * 
 * File is assumed relative to `/site/templates/` (or a directory within there) unless you specify a full path.
 * If you specify a full path, it will accept files in or below any of the following:
 *
 * - /site/templates/
 * - /site/modules/
 * - /wire/modules/
 *
 * Note this function returns the output to you, so that you can send the output wherever you want (delayed output).
 * For direct output, use the `wireIncludeFile()` or `$files->include()` function instead.
 *
 * #pw-group-files
 *
 * @param string $filename Assumed relative to /site/templates/ unless you provide a full path name with the filename.
 *  If you provide a path, it must resolve somewhere in site/templates/, site/modules/ or wire/modules/.
 * @param array $vars Optional associative array of variables to send to template file.
 *  Please note that all template files automatically receive all API variables already (you don't have to provide them).
 * @param array $options Associative array of options to modify behavior:
 *  - `defaultPath` (string): Path where files are assumed to be when only filename or relative filename is specified (default=/site/templates/)
 *  - `autoExtension` (string): Extension to assume when no ext in filename, make blank for no auto assumption (default=php)
 *  - `allowedPaths` (array): Array of paths that are allowed (default is templates, core modules and site modules)
 *  - `allowDotDot` (bool): Allow use of ".." in paths? (default=false)
 *  - `throwExceptions` (bool): Throw exceptions when fatal error occurs? (default=true)
 * @return string|bool Rendered template file or boolean false on fatal error (and throwExceptions disabled)
 * @throws WireException if template file doesn’t exist
 * @see wireIncludeFile(), WireFileTools::render(), WireFileTools::include()
 * 
 */
function wireRenderFile($filename, array $vars = array(), array $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->render($filename, $vars, $options);
}

/**
 * Include a PHP file passing it all API variables and optionally your own specified variables
 
 * This is the procedural version of the `$files->include()` method. 
 * 
 * This is the same as PHP’s `include()` function except for the following:
 *
 * - It receives all API variables and optionally your custom variables
 * - If your filename is not absolute, it doesn’t look in PHP’s include path, only in the current dir.
 * - It only allows including files that are part of the PW installation: templates, core modules or site modules
 * - It will assume a “.php” extension if filename has no extension.
 *
 * Note this function produces direct output. To retrieve output as a return value, use the
 * `wireRenderFile()` or `$files->render()` function instead.
 *
 * #pw-group-files
 *
 * @param string $filename Filename to include
 * @param array $vars Optional variables you want to hand to the include (associative array)
 * @param array $options Array of options to modify behavior:
 *  - `func` (string): Function to use: include, include_once, require or require_once (default=include)
 *  - `autoExtension` (string): Extension to assume when no ext in filename, make blank for no auto assumption (default=php)
 *  - `allowedPaths` (array): Array of start paths include files are allowed from. Note current dir is always allowed.
 * @return bool Always returns true
 * @throws WireException if file doesn’t exist or is not allowed
 * @see wireRenderFile(), WireFileTools::include(), WireFileTools::render()
 *
 * 
 */
function wireIncludeFile($filename, array $vars = array(), array $options = array()) {
	/** @var WireFileTools $files */
	$files = wire('files');
	return $files->___include($filename, $vars, $options);
}

/**
 * Format a date, using PHP date(), strftime() or other special strings (see arguments).
 * 
 * This is designed to work the same wa as PHP’s `date()` but be able to accept any common format
 * used in ProcessWire. This is helpful in reducing code in places where you might have logic 
 * determining when to use `date()`, `strftime()`, or `wireRelativeTimeStr()`. 
 * 
 * This is the procedural version of the `$datetime->date()` method. 
 * 
 * ~~~~~
 * echo wireDate('Y-m-d H:i:s'); // Outputs: 2019-01-20 06:48:11
 * echo wireDate('relative', '2019-01-20 06:00'); // Outputs: 48 minutes ago
 * ~~~~~
 * 
 * #pw-group-strings
 * #pw-group-common
 * 
 * @param string|int $format Use any PHP date() or strftime() format, or one of the following:
 * 	- `relative` for a relative date/time string.
 *  - `relative-` for a relative date/time string with no tense. 
 * 	- `rel` for an abbreviated relative date/time string.
 * 	- `rel-` for an abbreviated relative date/time string with no tense.
 * 	- `r` for an extra-abbreviated relative date/time string.
 * 	- `r-` for an extra-abbreviated relative date/time string with no tense.
 * 	- `ts` makes it return a unix timestamp.
 * 	- Specify blank string to make it use the system date format ($config->dateFormat) .
 * 	- If given an integer and no second argument specified, it is assumed to be the second ($ts) argument. 
 * @param int|string|null $ts Optionally specify the date/time stamp or strtotime() compatible string. If not specified, current time is used.
 * @return string|bool Formatted date/time, or boolean false on failure
 * 
 */
function wireDate($format = '', $ts = null) {
	/** @var WireDateTime $datetime */
	$datetime = wire('datetime');
	return $datetime->date($format, $ts);
}


/**
 * Render markup for a system icon
 *
 * It is NOT necessary to specify an icon prefix like “fa-” with the icon name.
 * 
 * Modifiers recognized in the class attribute:
 * lg, fw, 2x, 3x, 4x, 5x, spin, spinner, li, border, inverse,
 * rotate-90, rotate-180, rotate-270, flip-horizontal, flip-vertical,
 * stack, stack-1x, stack-2x
 * 
 * ~~~~~
 * // Outputs: "<i class='fa fa-home'></i>"
 * echo wireIconMarkup('home');
 * 
 * // Outputs: "<i class='fa fa-home fa-fw fa-lg my-class'></i>"
 * echo wireIconMarkup('home', 'fw lg my-class');
 * 
 * // Outputs "<i class='fa fa-home fa-fw' id='root-icon'></i>" (3.0.229+ only)
 * echo wireIconMarkup('home', 'fw id=root-icon');
 * echo wireIconMarkup('home fw id=root-icon'); // same as above
 * ~~~~~
 * 
 * #pw-group-markup
 * 
 * @param string $icon Icon name (currently a font-awesome icon name)
 * @param string $class Any of the following: 
 *  - Additional attributes for class (example: "fw" for fixed width)
 *  - Your own custom class(es) separated by spaces
 *  - Any additional attributes in format `key="val" key='val' or key=val` string (3.0.229+)
 *  - An optional trailing space to append an `&nbsp;` to the return icon markup (3.0.229+)
 *  - Any of the above may also be specified in the $icon argument in 3.0.229+. 
 * @return string
 * 
 */
function wireIconMarkup($icon, $class = '') {
	static $modifiers = null;
	$sanitizer = wire()->sanitizer;
	$attrs = array();
	$append = '';
	if($modifiers === null) $modifiers = array_flip(array(
		'lg', 'fw', '2x', '3x', '4x', '5x', 
		'spin', 'spinner', 'li', 'border', 'inverse', 
		'rotate-90', 'rotate-180', 'rotate-270', 
		'flip-horizontal', 'flip-vertical', 
		'stack', 'stack-1x', 'stack-2x', 
	));
	if(empty($icon)) return '';
	if(strpos($icon, ' ')) {
		// class or extras specified in $icon rather than $class
		list($icon, $extra) = explode(' ', $icon, 2);
		$class = trim("$class $extra");
	}
	if(strpos($icon, 'icon-') === 0) {
		list(,$icon) = explode('icon-', $icon, 2);
		$icon = "fa-$icon";
	} else if(strpos($icon, 'fa-') !== 0) {
		$icon = "fa-$icon";
	}
	if($class !== '') {
		$classes = array();
		if(rtrim($class) !== $class) $append = '&nbsp;';
		if(strpos($class, '=')) {
			$re = '/\b([-_a-z\d]+)=("[^"]*"|\'[^\']*\'|[-_a-z\d]+)\s*/i';
			if(preg_match_all($re, $class, $matches)) {
				foreach($matches[1] as $key => $attrName) {
					$attrVal = trim($matches[2][$key], "\"'");
					$attrVal = $sanitizer->entities($attrVal);
					$attrs[$attrName] = "$attrName='$attrVal'";
					$class = str_replace($matches[0][$key], ' ', $class);
				}
				$class = trim($class);
			}
		}
		if(isset($attrs['class'])) {
			$class = trim("$class $attrs[class]"); 
			unset($attrs['class']); 
		}
		foreach(explode(' ', $class) as $c) {
			if(empty($c)) continue;
			$classes[] = isset($modifiers[$c]) ? "fa-$c" : $c;
		}
		$class = implode(' ', $classes);
	}
	$class = $sanitizer->entities(trim("fa $icon $class"));
	$attrs['class'] = "class='$class'";
	return "<i " . implode(' ', $attrs) . "></i>$append";
}

/**
 * Get the markup or class name for an icon that can represent the given filename
 * 
 * ~~~~~
 * // Outputs: "<i class='fa fa-pdf-o'></i>"
 * echo wireIconMarkupFile('file.pdf'); 
 * ~~~~~
 * 
 * #pw-group-markup
 * #pw-group-files
 * 
 * @param string $filename Can be any type of filename (with or without path).
 * @param string|bool $class Additional class attributes, i.e. "fw" for fixed-width (optional). 
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
 * Given a quantity of bytes (int), return readable string that refers to quantity in bytes, kB, MB, GB and TB
 * 
 * #pw-group-strings
 * 
 * @param int $bytes Quantity in bytes
 * @param bool|int|array $small Make returned string as small as possible? (default=false)
 *  - `true` (bool): Yes, make returned string as small as possible. 
 *  - `1` (int): Same as `true` but with space between number and unit label.
 *  - Or optionally specify the $options argument here if you do not need the $small argument. 
 * @param array|int $options Options to modify default behavior, or if an integer then `decimals` option is assumed:
 *  - `decimals` (int|null): Number of decimals to use in returned value or NULL for auto (default=null).
 *     When null (auto) a decimal value of 1 is used when appropriate, for megabytes and higher (3.0.214+). 
 *  - `decimal_point` (string|null): Decimal point character, or null to detect from locale (default=null). 
 *  - `thousands_sep` (string|null): Thousands separator, or null to detect from locale (default=null). 
 *  - `small` (bool): If no $small argument was specified, you can optionally specify it in this $options array.
 *  - `type` (string): To force return value as specific type, specify one of: bytes, kilobytes, megabytes, 
 *     gigabytes, terabytes; or just: b, k, m, g, t. (3.0.148+ only, terabytes 3.0.214+). 
 * @return string
 * 
 */
function wireBytesStr($bytes, $small = false, $options = array()) {
	if(is_array($small)) $options = $small;
	if(!is_array($options)) {
		if(ctype_digit("$options")) {
			$options = array('decimals' => (int) $options);
		} else {
			$options = array();
		}
	}
	if(is_int($small) && !isset($options['decimals'])) {
		$options['decimals'] = $small;
	} else if(is_bool($small)) {
		$options['small'] = $small;
	}
	return wire()->sanitizer->getNumberTools()->bytesToStr($bytes, $options);
}

/**
 * Normalize a class name with or without namespace, or get namespace of class
 *
 * Default behavior is to return class name without namespace.
 *
 * #pw-group-class-helpers
 *
 * @param string|object $className Class name or object instance
 * @param bool|int|string $withNamespace Should return value include namespace? (default=false)
 *  - `false` (bool): Return only class name without namespace (default).
 *  - `true` (bool): Yes include namespace in returned value.
 *  - `1` (int): Return only namespace (i.e. “ProcessWire”, with no backslashes unless $verbose argument is true)
 * @param bool $verbose When namespace argument is true or 1, use verbose return value (added 3.0.143). This does the following:
 *  - If returning class name with namespace, this makes it include a leading backslash, i.e. `\ProcessWire\Wire`
 *  - If returning namespace only, adds leading backslash, plus trailing backslash if namespace is not root, i.e. `\ProcessWire\`
 * @return string|null Returns string or NULL if namespace-only requested and unable to determine
 *
 */
function wireClassName($className, $withNamespace = false, $verbose = false) {

	$bs = "\\"; // backslash

	if(is_object($className)) {
		$object = $className;
		$className = get_class($className);
	} else {
		$object = null;
	}

	$className = (string) $className;
	$pos = strrpos($className, $bs);

	if($withNamespace === true) {
		if($object) { 
			// result of get_class() is already what we want
		} else if($pos === false && __NAMESPACE__) {
			// return class with namespace, substituting ProcessWire namespace if none present
			$className = __NAMESPACE__ . $bs . $className;
		}
		if($verbose) {
			// add leading backslash
			$className = $bs . ltrim($className, $bs);
		}
		
	} else if($withNamespace === 1) {
		// return namespace only
		if($pos !== false) {
			// there is a namespace, extract it
			$className = substr($className, 0, $pos);
		} else if($object) {
			// namespace is root
			$className = $verbose ? $bs : '';
		} else {
			// there is no namespace in given className, attempt to detect in ProcessWire or root namespace
			if(class_exists(__NAMESPACE__ . $bs . $className)) {
				// class in ProcessWire namespace
				$className = __NAMESPACE__;
			} else if(class_exists($bs . $className)) {
				// class in root namespace
				$className = '';
			} else {
				// unable to determine
				$className = null;
			}
		}
		if($verbose && $className !== null) {
			$className = $bs . trim($className, $bs); // leading
			if(strlen($className) > 1) $className .= $bs; // trailing
		}

	} else {
		// return className without namespace (default behavior)
		if($pos !== false) $className = substr($className, $pos+1);
	}

	return $className;
}

/**
 * Get namespace for given class 
 * 
 * ~~~~~
 * echo wireClassNamespace('Page'); // returns: "\ProcessWire\"
 * echo wireClassNamespace('DirectoryIterator'); // returns: "\"
 * echo wireClassNamespace('UnknownClass'); // returns "" (blank)
 * 
 * // Specify true for 2nd argument to make it include class name
 * echo wireClassNamespace('Page', true); // outputs: \ProcessWire\Page
 * 
 * // Specify true for 3rd argument to find all matching classes 
 * // and return array if more than 1 matches (or string if just 1): 
 * $val = wireClassNamespace('Foo', true, true); 
 * if(is_array($val)) {
 *   // 2+ classes found, so array value is returned
 *   // $val: [ '\Bar\Foo', '\Foo', '\Baz\Foo' ]
 * } else {
 *   // string value is returned when only one class matches
 *   // $val: '\Bar\Foo'
 * }
 * ~~~~~
 * 
 * #pw-group-class-helpers
 * 
 * @param string|object $className
 * @param bool $withClass Include class name in returned namespace? (default=false)
 * @param bool $strict Return array of namespaces if multiple match? (default=false)
 * @return string|array Returns one of the following:
 *  - String of `\Namespace\` (leading+trailing backslashes) if namespace found.
 *  - String of `\` if class in root namespace.
 *  - Blank string if unable to find namespace for class.
 *  - Array of namespaces only if $strict option is true AND multiple namespaces were found for class.
 *  - If the $withClass option is true, then return value(s) have class, i.e. `\Namespace\ClassName`.
 * @since 3.0.150
 * 
 */
function wireClassNamespace($className, $withClass = false, $strict = false) {
	
	$bs = "\\";
	$ns = "";
	
	if(is_object($className)) {
		$className = get_class($className);
	}
	
	if(strpos($className, $bs) !== false) {
		// namespace is already included in class name
		$a = explode($bs, $className);
		array_pop($a); // class
		$ns = count($a) ? implode($bs, $a) : $bs;
		if(empty($ns)) $ns = $bs;
		$strict = false; // strict not necessary

	} else if(class_exists(__NAMESPACE__ . "$bs$className")) {
		// class in ProcessWire namespace
		$ns = __NAMESPACE__;
		
	} else if(class_exists("$bs$className")) {
		// class in root namespace
		$ns = $bs;
	}
	
	if(empty($ns) || $strict) {
		// hunt down namespace from declared classes
		$nsa = array();
		$name = strtolower($className); 
		foreach(get_declared_classes() as $class) {
			if(strpos($class, $bs) === false) {
				// root namespace
				if(!$strict) continue;
				$class = "$bs$class";
			}
			if(stripos($class, "$bs$className") === false) continue;
			if(strtolower(substr($class, -1 * strlen($className))) !== $name) continue;
			$a = explode($bs, trim($class, $bs));
			$cn = array_pop($a);
			$ns = count($a) ? implode($bs, $a) : $bs;
			if($ns && $ns !== $bs) $ns = $bs . trim($ns, $bs) . $bs;
			if($withClass) $ns .= $cn;
			$nsa[] = $ns;
			if(!$strict) break;
		}
		$n = count($nsa);
		// return array now for multi-match strict mode
		if($strict && $n > 1) return $nsa; 
		$ns = $n ? reset($nsa) : '';
		
	} else if($ns && $ns !== $bs) {
		// format with leading/trailing backslashes, i.e. \Namespace\
		$ns = $bs . trim($ns, $bs) . $bs;
		if($withClass) $ns .= $className;
	}
	
	return $ns;
}


/**
 * Does the given class name exist?
 * 
 * ProcessWire namespace aware version of PHP’s class_exists() function
 * 
 * If given a class name that does not include a namespace, the `\ProcessWire` namespace is assumed. 
 * 
 * #pw-group-class-helpers
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
 * Does the given class have the given method?
 * 
 * ProcessWire namespace aware version of PHP’s method_exists() function
 * 
 * If given a class name that does not include a namespace, the `\ProcessWire` namespace is assumed. 
 * 
 * #pw-group-class-helpers
 *
 * @param string $className Class name or object 
 * @param string $method Method name
 * @param bool $hookable Also return true if "method" exists in a hookable format "___method"? (default=false) 3.0.204+
 * @return bool
 *
 */
function wireMethodExists($className, $method, $hookable = false) {
	if(!is_object($className)) $className = wireClassName($className, true);
	$exists = method_exists($className, $method);
	if(!$exists && $hookable) $exists = method_exists($className, "___$method"); 
	return $exists;
}

/**
 * Get an array of all the interfaces that the given class implements
 *
 * - ProcessWire namespace aware version of PHP’s class_implements() function.
 * - Return value has array keys as class name with namespace and array values as class name without namespace.
 * 
 * #pw-group-class-helpers
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
 * Return array of all parent classes for given class/object
 *
 * ProcessWire namespace aware version of PHP’s class_parents() function
 * 
 * Returns associative array where array keys are full namespaced class name, and 
 * values are the non-namespaced classname.
 * 
 * #pw-group-class-helpers
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
			if(class_exists("\\$_className")) {
				$className = $_className;
			} else {
				$ns = wireClassNamespace($_className); 
				if($ns) $className = $ns . $_className;
			}
		}
		$parents = @class_parents(ltrim($className, "\\"), $autoload);
	}
	$a = array();
	if(is_array($parents)) foreach($parents as $k => $v) {
		$v = wireClassName($k, false);
		$a[$k] = $v; // values have no namespace
	}
	return $a; 
}

/**
 * Does given instance (or class) represent an instance of the given className (or class names)?
 * 
 * Since version 3.0.108 the $className argument may also represent an interface, 
 * array of interfaces, or mixed array of interfaces and class names. Previous versions did
 * not support interfaces unless the $instance argument was an object.
 * 
 * #pw-group-class-helpers
 * 
 * @param object|string $instance Object instance to test (or string of its class name).
 * @param string|array $className Class/interface name or array of class/interface names to test against. 
 * @param bool $autoload Allow PHP to autoload the class? (default=true)
 * @return bool|string Returns one of the following:
 *  - `false` (bool): if not an instance (whether $className argument is string or array). 
 *  - `true` (bool): if given a single $className (string) and $instance is an instance of it. 
 *  - `ClassName` (string): first matching class/interface name if $className was an array of classes to test.
 * 
 */
function wireInstanceOf($instance, $className, $autoload = true) {
	
	if(is_array($className)) {
		$returnClass = true; 
		$classNames = $className;
	} else {
		$returnClass = false;
		$classNames = array($className);
	}

	$matchClass = null;
	$instanceIsObject = is_object($instance);
	$instanceParents = null;
	$instanceInterfaces = null;
	$instanceClass = null;
	
	if($instanceIsObject) {
		// instance is an object
	} else if(is_string($instance)) {
		// instance is a class name, make sure it has namespace
		$instanceClass = wireClassName($instance, true);
		if($instanceClass === null) $instanceClass = $instance; // if above failed
		$instance = $instanceClass;
	} else {
		// unrecognized instance value
		return false;
	}

	foreach($classNames as $className) {
		$className = wireClassName($className, true); // with namespace
		if($instanceIsObject && (class_exists($className, $autoload) || interface_exists($className, $autoload))) {
			if($instance instanceof $className) {
				$matchClass = $className;
			}
		} else {
			if($instanceClass === null) {
				$instanceClass = wireClassName($instance, true);
				if($instanceClass === null) break;
			}
			if($instanceParents === null) {
				$instanceParents = wireClassParents($instance, $autoload);
				$instanceParents[$instanceClass] = 1;
			}
			if(isset($instanceParents[$className])) {
				$matchClass = $className;
			} else {
				if($instanceInterfaces === null) {
					$instanceInterfaces = wireClassImplements($instance, $autoload);
				}
				if(isset($instanceInterfaces[$className])) {
					$matchClass = $className;
				}
			}
		}
		if($matchClass !== null) break;
	}
	
	return $returnClass ? $matchClass : ($matchClass !== null); 
}

/**
 * Is the given $var callable as a function?
 * 
 * ProcessWire namespace aware version of PHP’s is_callable() function
 * 
 * #pw-group-class-helpers
 *
 * @param string|callable $var
 * @param bool $syntaxOnly
 * @var string $callableName
 * @return bool
 *
 */
function wireIsCallable($var, $syntaxOnly = false, &$callableName = '') {
	if(is_string($var)) $var = wireClassName($var, true);
	return is_callable($var, $syntaxOnly, $callableName);
}

/**
 * Return the count of item(s) present in the given value
 * 
 * Duplicates behavior of PHP count() function prior to PHP 7.2, which states:
 * 
 * > Returns the number of elements in $value. When the parameter is neither an array nor an
 * object with implemented Countable interface, 1 will be returned. There is one exception,
 * if $value is NULL, 0 will be returned.
 * 
 * #pw-group-common
 * 
 * @param mixed $value
 * @return int
 * 
 */
function wireCount($value) {
	if($value === null) return 0; 
	if(is_array($value)) return count($value); 
	if($value instanceof \Countable) return count($value);
	return 1;
}

/**
 * Returns string length of any type (string, array, object, bool, int, etc.)
 * 
 * - If given a string it returns the multibyte string length. 
 * - If given a bool, returns 1 for true or 0 for false.
 * - If given an int or float, returns its length when typecast to string.
 * - If given array or object it duplicates the behavior of `wireCount()`. 
 * - If given null returns 0.
 * 
 * @param string|array|object|int|bool|null $value
 * @param bool $mb Use multibyte string length when available (default=true)
 * @return int
 * @since 3.0.192
 * 
 */
function wireLength($value, $mb = true) {
	if($value === null || $value === '' || $value === false) return 0; 
	if($value === true) return 1;
	if(is_string($value)) return ($mb && function_exists('mb_strlen') ? mb_strlen($value) : strlen($value));
	if(is_array($value) || is_object($value)) return wireCount($value);
	return strlen("$value"); // int, float, other: returns length of string typecast
}

/**
 * Returns string byte length of any type (string, array, object, bool, int, etc.)
 * 
 * This is identical to the `wireLength()` function except that it does not use
 * multibyte string lengths on strings, so it returns a byte length when given
 * a multibyte string rather than a visual character length. So on strings
 * it uses strlen() rather than mb_strlen(). 
 *
 * @param string|array|object|int|bool|null $value
 * @return int
 * @since 3.0.192
 *
 */
function wireLen($value) {
	return wireLength($value, false);
}

/**
 * Is the given value empty according to ProcessWire standards?
 * 
 * This works the same as PHP’s empty() function except for the following: 
 * 
 * - It returns true for Countable objects that have 0 items. 
 * - It considers whitespace-only strings to be empty.
 * - It considers WireNull objects (like NullPage or any others) to be empty (3.0.149+).
 * - It uses the string value of objects that can be typecast strings (3.0.150+).
 * - You cannot pass it an undefined variable without triggering a PHP warning. 
 * 
 * ~~~~~
 * // behavior with Countable objects
 * $a = new WireArray();
 * empty($a); // PHP’s function returns false 
 * wireEmpty($a); // PW’s function returns true
 * $a->add('item');
 * wireEmpty($a); // returns false, since there is now an item
 * 
 * // behavior with whitespace-only string
 * $s = '  ';
 * empty($s); // PHP’s function returns false
 * wireEmpty($s); // PW’s function returns true
 * 
 * // behavior with undefined variable $v
 * isset($v); // returns false
 * empty($v); // returns true
 * wireEmpty($v); // returns true but with PHP’s warning triggered
 * ~~~~~
 * 
 * @param mixed $value Value to test if empty
 * @return bool
 * @since 3.0.143
 * 
 */
function wireEmpty($value) {
	if(empty($value)) return true;
	if(is_object($value)) {
		if($value instanceof \Countable && !count($value)) return true;
		if($value instanceof WireNull) return true; // 3.0.149+
		if(method_exists($value, '__toString')) $value = (string) $value;
	}
	if(is_string($value)) {
		$value = trim($value);
		if(empty($value)) return true;
	}
	return false;
}

/**
 * Get or set an output region (primarily for front-end output usage)
 * 
 * This function is an convenience for storing markup that ultimately gets output in a _main.php file 
 * (or whatever file `$config->appendTemplateFile` is set to). It is an alternative to passing variables
 * between included files and provides an interface for setting, appending, prepending and ultimately
 * getting markup (or other strings) for output. It’s designed for use the the “Delayed Output” strategy,
 * though does not necessarily require it. 
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
 * #pw-internal
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

/**
 * Stop execution with a 404 unless redirect URL available (for front-end use)
 * 
 * This is an alternative to using a manual `throw new Wire404Exception()` and is recognized by
 * PW as a front-end 404 where PagePathHistory (or potentially other modules) are still allowed
 * to change the behavior of the request from a 404 to something else (like a 301 redirect). 
 * 
 * #pw-group-common
 * 
 * @param string $message Optional message to send to Exception message argument (not used in output by default)
 * @throws Wire404Exception
 * @since 3.0.146
 * 
 */
function wire404($message = '') {
	throw new Wire404Exception($message, Wire404Exception::codeFunction); 
}

/**
 * Create new WireArray, add given $items to it, and return it
 *
 * This is the same as creating a `new WireArray()` and then adding items to it with separate `add()` calls,
 * except that this function enables you to do it all in one shot. 
 * 
 * ~~~~~~
 * $a = WireArray(); // create empty WireArray
 * $a = WireArray('foo'); // create WireArray with one "foo" string
 * $a = WireArray(['foo', 'bar', 'baz']); // create WireArray with 3 strings
 * ~~~~~~
 * 
 * #pw-group-arrays
 * 
 * @param array|WireArray|mixed $items
 * @return WireArray
 * @since 3.0.123
 * 
 */
function WireArray($items = array()) {
	return WireArray::newInstance($items);
}

/**
 * Create a new WireData instance and optionally add given associative array of data to it
 * 
 * ~~~~~
 * $data = WireData([ 'hello' => 'world', 'foo' => 'bar' ]); 
 * ~~~~~
 * 
 * #pw-group-arrays
 * 
 * @param array|\Traversable $data Can be an associative array or Traversable object of data to set, or omit if not needed
 * @return WireData
 * @since 3.0.126
 * 
 */
function WireData($data = array()) {
	$wireData = new WireData();
	if(is_array($data)) {
		if(!empty($data)) $wireData->setArray($data);
	} else if($data instanceof \Traversable) {
		foreach($data as $k => $v) $wireData->set($k, $v);
	}
	$wireData->resetTrackChanges(true);
	return $wireData;
}

/**
 * Create new PageArray, add given $items (pages) to it, and return it
 * 
 * This is the same as creating a `new PageArray()` and then adding items to it with separate `add()` calls, 
 * except that this function enables you to do it all in one shot. 
 * 
 * ~~~~~
 * $a = PageArray(); // create empty PageArray
 * $a = PageArray($page); // create PageArray with one page
 * $a = PageArray([ $page1, $page2, $page3 ]); // create PageArray with multiple items 
 * ~~~~~
 * 
 * #pw-group-arrays
 *
 * @param array|PageArray $items
 * @return PageArray
 * @since 3.0.123
 *
 */
function PageArray($items = array()) {
	/** @var PageArray $pa */
	$pa = PageArray::newInstance($items);
	return $pa;
}
