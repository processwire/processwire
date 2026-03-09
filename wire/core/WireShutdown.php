<?php namespace ProcessWire;

/**
 * ProcessWire shutdown handler
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 *  
 * Look for errors at shutdown and log them, plus echo the error if the page is editable
 *
 * https://processwire.com
 * 
 * @method void fatalError(array $error)
 *
 */

class WireShutdown extends Wire {

	/**
	 * Associative array of [ PHP E_* constants (i.e. E_ERROR) => Translated description ]
	 * 
	 * @var array
	 * 
	 */
	protected $types = array();

	/**
	 * Regular array of PHP E_* constants that are considered fatal (i.e. E_ERROR)
	 * 
	 * @var array
	 * 
	 */
	protected $fatalTypes = array(
		E_ERROR,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_PARSE,
		E_RECOVERABLE_ERROR,
	);

	/**
	 * Fatal error response info, not used unless set manually by $shutdown->setFatalErrorResponse()
	 * 
	 * - `code` (int): Fatal error http status code (0=use $config->fatalErrorCode instead)
	 * - `headers` (array): Any additional headers to include in fatal error, in format [ "Header-Name: Header-Value" ]
	 * - `emailTo` (string): Administrator email address to send error to (default is $config->adminEmail)
	 * - `emailFrom` (string): From email address for email to administrator (default=$config->wireMail['from'])
	 * - `emailFromName` (string): From name for email to administrator (default=$config->wireMail['fromName'])
	 * - `emailSubject` (string): Override email subject (default=use built-in translatable subject) 
	 * - `emailBody` (string): Override default email body (text-only). Should have {url}, {user} and {message} placeholders.
	 * - `emailBodyHTML` (string): Override default email body (HTML-only). Should have {url}, {user} and {message} placeholders.
	 * - `emailModule` (string): Name of WireMail module to use, leave blank for automatic, or 'WireMail' to force default.
	 * - `words` (array): Spicy but calming words to prepend to visible error messages. 
	 * 
	 * @var array
	 * 
	 */
	protected $fatalErrorResponse = array(
		'code' => 0,
		'headers' => array(),
		'emailTo' => '',
		'emailFrom' => '',
		'emailFromName' => '',
		'emailSubject' => '',
		'emailBody' => '',
		'emailBodyHTML' => '',
		'emailModule' => '', 
		'words' => array(), 
	);

	/**
	 * Associative array of phrase translations for this module
	 * 
	 * @var array
	 * 
	 */
	protected $labels = array();

	/**
	 * @var Config
	 * 
	 */
	protected $config;

	/**
	 * Contents of last error_get_last() call
	 * 
	 * @var array
	 * 
	 */
	protected $error = array();

	/**
	 * Methods that should have their arguments suppressed from PHP backtraces
	 * 
	 * - Each method must include a `->`. 
	 * - Methods should not include parenthesis. 
	 * - If for specific class, include the class name before the `->`.
	 * 
	 * @var string[] 
	 * 
	 */
	protected $banBacktraceMethods = array(
		'->___login', // Session or ProcessLogin
		'->___start', // i.e. Tfa
		'->___setPass', // Password.php
		'Session->___authenticate',
		'Password->matches',
		'Password->hash',
	);

	/**
	 * Default HTML to use for error message
	 * 
	 * Can be overridden with $config->fatalErrorHTML in /site/config.php
	 * 
	 */
	const defaultFatalErrorHTML = '<p><b>{message}</b><br /><small>{why}</small></p>';

	/**
	 * Default email body for emailed fatal errors
	 * 
	 */
	const defaultEmailBody = "URL: {url}\nUser: {user}\nVersion: {version}\n\n{message}";
	
	/**
	 * Construct and register shutdown function
	 * 
	 * @param Config $config
	 * 
	 */
	public function __construct(Config $config) {
		$this->config = $config;
		register_shutdown_function(array($this, 'shutdown'));
		// If script is being called externally, add an extra shutdown function 
		if(!$config->internal) register_shutdown_function(array($this, 'shutdownExternal'));
		parent::__construct();
	}

	/**
	 * Set fatal error response info including http code, optional extra headers, and more
	 * 
	 * @param array $options
	 *  - `code` (int): http code to send, or omit to use default (500)
	 *  - `headers` (array): Optional additional headers to send, in format [ "Header-Name: Header-Value" ]
	 *  - `emailTo` (string): Administrator email address to send error to (default=$config->adminEmail)
	 *  - `emailFrom` (string): From email address for email to administrator (default=$config->wireMail['from'])
	 *  - `emailFromName` (string): From “name” for email to administrator (default=$config->wireMail['fromName'])
	 *  - `emailSubject` (string): Override email subject (default=use built-in translatable subject) 
	 *  - `emailBody` (string): Override default email body (text-only). Should have {url}, {user} and {message} placeholders.
	 *  - `emailModule` (string): Name of WireMail module to use or leave blank for automatic.
	 *  - `words` (array): Spicy but calming words to prepend to visible error messages. 
	 * @since 3.0.166
	 * 
	 */
	public function setFatalErrorResponse(array $options) {
		// account for renamed properties so that older property names continue to work
		if(!empty($options['adminEmail']) && empty($options['emailTo'])) $options['emailTo'] = $options['adminEmail'];
		if(!empty($options['fromEmail']) && empty($options['emailFrom'])) $options['emailFrom'] = $options['fromEmail']; 
		$this->fatalErrorResponse = array_merge($this->fatalErrorResponse, $options);
	}

	/**
	 * Setup our translation labels
	 * 
	 */
	protected function prepareLabels() {
		$this->types = array(
			E_ERROR             => $this->_('Fatal Error'),
			E_WARNING           => $this->_('Warning'),
			E_PARSE             => $this->_('Parse Error'),
			E_NOTICE            => $this->_('Notice'),
			E_CORE_ERROR        => $this->_('Core Error'),
			E_CORE_WARNING      => $this->_('Core Warning'),
			E_COMPILE_ERROR     => $this->_('Compile Error'),
			E_COMPILE_WARNING   => $this->_('Compile Warning'),
			E_USER_ERROR        => $this->_('Error'),
			E_USER_WARNING      => $this->_('User Warning'),
			E_USER_NOTICE       => $this->_('User Notice'),
			2048 => $this->_('Strict Warning'), // 2048=E_STRICT (deprecated in PHP 8.4)
			E_RECOVERABLE_ERROR => $this->_('Recoverable Fatal Error')
		);

		$this->labels = array(
			'error-logged' => $this->_('Error has been logged.'),
			'admin-notified' => $this->_('Administrator has been notified.'),
			'debug-mode' => $this->_('site is in debug mode.'),
			'cli-mode' => $this->_('you are using the command line API'),
			'you-superuser' => $this->_('you are logged in as a Superuser.'),
			'install-php' => $this->_('install.php still exists.'),
			'superuser-never' => $this->_('Superuser has never logged in.'),
			'shown-because' => $this->_('This error message was shown because:'),
			'unable-complete' => $this->_('Unable to complete this request due to an error.'),
			'email-subject' => $this->_('ProcessWire Error Notification'), // email subject
			'line-of-file' => $this->_('(line %d of %s)'), // Example: Line [123] of [file.php]
		);

	}

	/**
	 * Create more informative error message from $error array
	 * 
	 * @param array $error Error array from PHP’s error_get_last()
	 * @return string
	 * 
	 */
	protected function getErrorMessage(array $error) {
		
		$config = $this->config;
		$message = str_replace("\t", ' ', $error['message']);
		
		if(PROCESSWIRE < 302) {
			$type = $error['type'];
			if(isset($this->types[$type])) {
				$errorType = $this->types[$type];
			} else {
				$errorType = $this->types[E_USER_ERROR];
			}
			if($type != E_USER_ERROR) {
				$detail = sprintf($this->labels['line-of-file'], $error['line'], $error['file']) . ' ';
			} else {
				$detail = '';
			}
			$message = "$errorType: \t$message $detail ";
		}

		if(strpos($message, '#1') !== false && stripos($message, '):')) {
			// backtrace likely present in $message
			// methods that should have their arguments excluded from backtrace
			foreach($this->banBacktraceMethods as $name) {
				if(strpos($message, "$name(") === false) continue;
				if(!preg_match_all('!' . $name . '\([^\n]+\)!', $message, $matches)) continue;
				foreach($matches[0] as $match) {
					$message = str_replace($match, '->' . $name . '(...)', $message);
				}
			}
		}
	
		if(strlen((string) $config->dbPass) > 4) {
			$message = str_replace((string) $config->dbPass, '[...]', $message);
		}
		
		return $message;
	}

	/**
	 * Get WireInput instance and create it if not already present in the API
	 * 
	 * @return WireInput
	 * 
	 */
	protected function getWireInput() {
		$input = $this->wire()->input;
		if($input) return $input;
		$input = $this->wire(new WireInput());
		return $input;
	}

	/**
	 * Get the current request URL or "/?/" if it cannot be determined
	 * 
	 * @return string
	 * 
	 */
	protected function getCurrentUrl() {
		
		$page = $this->wire()->page;
		$input = $this->getWireInput();
		$http = isset($_SERVER['HTTP_HOST']) || isset($_SERVER['REQUEST_URI']); 
		
		if($http) {
			// best case, everything available. getting httpUrl requires that $config API var is available...
			$url = $input->httpUrl(); 
		} else if($page) {
			// this can occur for non-http request like command line
			$url = $page->url();
		} else {
			// unable to determine url
			$url = '/?/';
		}
		
		return $url;
	}

	/**
	 * Add helpful info or replace error message with something better, when possible
	 * 
	 * @param string $message
	 * @return string
	 * 
	 */
	protected function amendErrorMessage($message) {
		if(!$this->config->useFunctionsAPI && strpos($message, "undefined function ")) {
			$names = _wireFunctionsAPI();
			$names = implode('|', array_keys($names));
			if(preg_match('/undefined function (ProcessWire.|\b)(' . $names . ')\(/', $message, $matches)) {
				$name = $matches[2];
				$message = // replace error message with the following
					"You have attempted to access function $name(); that is only available if the ProcessWire Functions API is enabled. " .
					"Enable it by setting \$config->useFunctionsAPI = true; in your /site/config.php file, and then this " .
					"error message should no longer appear.";
			}
		}
		return $message;
	}

	/**
	 * Render an error message and reason why
	 * 
	 * @param string $message
	 * @param string $why
	 * @param bool $useHTML
	 * 
	 */
	protected function sendErrorMessage($message, $why, $useHTML) {
		
		$hadOutput = $this->sendExistingOutput();
		if($hadOutput) echo "\n\n";

		if($this->config && $this->config->debug) {
			$message = $this->seasonErrorMessage($message);
		}
		
		// return text-only error
		if(!$useHTML) {
			$message = $this->simplifyErrorMessageText($message);
			echo "$message\n\n$why\n\n";
			return;
		}

		// output HTML error
		$html = $this->config->fatalErrorHTML ? $this->config->fatalErrorHTML : self::defaultFatalErrorHTML;
		$html = str_replace(array(
			'{message}',
			'{why}'
		), array(
			nl2br(htmlspecialchars($message, ENT_QUOTES, "UTF-8", false)),
			htmlspecialchars($why, ENT_QUOTES, "UTF-8", false)
		), $html);
		
		$html = $this->simplifyErrorMessageHTML($html);
		
		// output the error message
		echo "$html\n\n";
	}

	/**
	 * Simplify error message HTML for output (inclusive of simplifyErrorMessageText also)
	 * 
	 * @param string $html
	 * @return string
	 * @since 3.0.175
	 * 
	 */
	protected function simplifyErrorMessageHTML($html) {
		// make a prettier looking debug backtrace, when applicable
		$style = 'font-family:monospace;font-size:14px';
		$html = preg_replace('!(<br[^>]*>\s*)(#\d+\s+[^<]+)!is', '$1<span style="' . $style . '">$2</span>', $html);
		
		$html = $this->simplifyErrorMessageText($html);

		// underline filenames
		$html = preg_replace('!(\s)/([^\s:(]+?)\.(php|module|inc)!', '$1<u>$2.$3</u>', $html);

		// improving spacing between filename and line number (123)
		$html = str_replace('</u>(', '</u> (', $html);

		// ProcessWire namespace is assumed so does not need to add luggage to output
		$html = str_replace('ProcessWire\\', '', $html);
		
		return $html;
	}

	/**
	 * Simplify error message to remove unnecessary or redundant information
	 * 
	 * @param string $text
	 * @return string
	 * @since 3.0.175
	 * 
	 */
	protected function simplifyErrorMessageText($text) {
		// reference original file rather than compiled version, when applicable
		$text = str_replace('assets/cache/FileCompiler/site/', '', $text);

		// remove unnecessary stack trace label
		$text = str_replace(array('Stack trace:<', 'Stack trace:'), array('<', ''), $text);

		// remove portions of path that are not needed in this output
		$rootPath = str_replace('/wire/core/', '/', dirname(__FILE__) . '/');
		$rootPath2 = $this->config ? $this->config->paths->root : '';
		$text = str_replace($rootPath, '/', $text);
		if($rootPath2 && $rootPath2 != $rootPath) $text = str_replace($rootPath2, '/', $text);
		
		return $text;
	}

	/**
	 * Provide additional seasoning for error message during debug mode output
	 * 
	 * @param string $message
	 * @return string
	 * 
	 */
	protected function seasonErrorMessage($message) {
		
		$spices = $this->fatalErrorResponse['words']; 
		
		if(empty($spices)) $spices = array(
			'Oops', 'Darn', 'Dangit', 'Oh no', 'Ah snap', 'So sorry', 'Well well',
			'Ouch', 'Arrgh', 'Umm', 'Snapsicles', 'Oh snizzle', 'Look', 'What the',
			'Uff da', 'Yikes', 'Aw shucks', 'Oye', 'Rats', 'Hmm', 'Yow', 'Not again',
			'Look out', 'Hey now', 'Breaking news', 'Excuse me', 
		);
		
		$spice = $spices[array_rand($spices)];
		if(!ctype_punct(substr($spice, -1))) $spice .= '…';
		
		$message = "$spice $message";
		
		return $message;
	}

	/**
	 * Send fatal error http header and return error code sent
	 * 
	 * @return int
	 * 
	 */
	protected function sendFatalHeader() {
		include_once(dirname(__FILE__) . '/WireHttp.php');
		$http = new WireHttp();
		$codes = $http->getHttpCodes();
		$code = 500;
		if($this->fatalErrorResponse['code']) {
			$code = (int) $this->fatalErrorResponse['code'];
		} else if($this->config) {
			$code = (int) $this->config->fatalErrorCode;
		}
		if(!isset($codes[$code])) $code = 500;
		$http->sendStatusHeader($code); 
		foreach($this->fatalErrorResponse['headers'] as $header) {
			$http->sendHeader($header); 
		}
		return $code;
	}

	/**
	 * Send a fatal error
	 * 
	 * This is a public fatal error that doesn’t reveal anything specific.
	 * 
	 * @param string $message Message to indicate who error was also sent to 
	 * @param bool $useHTML Output for a web browser?
	 * 
	 */
	protected function sendFatalError($message, $useHTML) {
		
		if($useHTML) {
			$code = $this->sendFatalHeader();
			$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
			// file that error message will be output in, when available
			$path = $this->config->paths->templates;
			$file = $path . "errors/$code.html";
			if(!file_exists($file) && $code !== 500) $file = $path . "errors/500.html";
		} else {
			$file = '';
		}
		
		$this->sendExistingOutput();
		
		if($file && is_file($file)) {
			// use defined /site/templates/errors/500.html file
			echo str_replace('{message}', $message, file_get_contents($file));
		} else {
			// use generic error message, since no 500.html available
			echo "\n\n" . $this->labels['unable-complete'] . ($message ? " - $message" : "") . "\n\n";
		}
	}

	/**
	 * Send any existing output while removing PHP’s error message from it (to avoid duplication)
	 * 
	 * @return bool Returns true if there was existing output, false if not
	 * 
	 */
	protected function sendExistingOutput() {
	
		/*
		$files = TemplateFile::getRenderStack();
		if(!count($files)) {
			// existing output (if present) is not from a template file being rendered
			return false;
		}
		*/
		
		$out = ob_get_level() ? (string) ob_get_clean() : '';
		if(!strlen(trim($out))) return false;
		
		// if error message isn't in existing output, then return as-is
		if(empty($this->error['message'])) { 
			echo $out;
			return true;
		}

		// encode message the same way that PHP does by default
		$message = htmlspecialchars($this->error['message'], ENT_COMPAT | ENT_HTML401, ini_get('default_charset'), false);
		
		if(strpos($out, $message) !== false) {
			// encoded message present in output
		} else if(strpos($out, $this->error['message']) !== false) {
			// non-encoded message present in output
			$message = $this->error['message']; 
		} else {
			// error message not present in output
			echo $out;
			return true;
		}

		// generate a unique token placeholder for message
		$token = '';
		do {
			$token .= 'xPW' . mt_rand() . 'SD';
		} while(strpos($out, $token) !== false);
		
		// replace error message with token
		$out = str_replace($message, $token, $out);
		
		// replace anything else on the same line as the PHP error (error type, file, line-number)
		$out = (string) preg_replace('/([\r\n]|^)[^\r\n]+' . $token . '[^\r\n]*/', '', $out);

		// ensure certain tags that could interfere with error message output are closed
		$tags = array(
			'<pre>' => '</pre>',
			'<pre ' => '</pre>',
			'<table>' => '</table>',
			'<table ' => '</table>',
		);
		foreach($tags as $openTag => $closeTag) {
			$openPos = strripos($out, $openTag);
			if($openPos === false) continue;
			$closePos = strripos($out, $closeTag); 
			if($closePos && $closePos > $openPos) continue;
			$out .= $closeTag;
		}
		
		echo $out;
		
		return $out === $token ? false : true;
	}

	/**
	 * Hook called when fatal error received by shutdown()
	 * 
	 * @param array $error
	 * @since 3.0.173
	 * 
	 */
	protected function ___fatalError($error) { }

	/**
	 * Set shutdown fatal error
	 * 
	 * Used only for index version >= 302 
	 *
	 * @param \Throwable $e
	 * @param string $message
	 * @since 3.0.253
	 * 
	 */
	public function setFatalError(\Throwable $e, $message = '') {
		if(empty($message)) {
			$user = $this->wire()->user; 
			$message = $e->getMessage();
			if($this->config->debug || ($user && $user->isSuperuser())) {
				$longMessage = (string) $e; 
				if(strlen($longMessage) > strlen($message)) $message = $longMessage;
			}
		}
		$this->error = [
			'file' => $e->getFile(),
			'line' => $e->getLine(), 
			'message' => $message, 
			'type' => E_USER_ERROR,
			'throwable' => $e,
		];
	}
	
	/**
	 * Get last error
	 * 
	 * @return array
	 * @since 3.0.253
	 * 
	 */
	protected function getError() {
		if(empty($this->error)) {
			$this->error = error_get_last();
		}
		return $this->error;
	}

	/**
	 * Shutdown function registered with PHP
	 * 
	 * @return bool
	 * 
	 */
	public function shutdown() {
		
		$error = $this->getError();
		
		if(empty($error) || !in_array($error['type'], $this->fatalTypes)) return true;
		
		$this->fatalError($error);
		$this->prepareLabels();
		$config = $this->config;
		$user = $this->wire()->user; /** @var User|null $user */
		$useHTML = isset($_SERVER['HTTP_HOST']); // is this an HTTP request where we can output HTML?
		$name = $user && $user->id ? $user->name : '?'; // user name
		$who = array(); // who/where the error message has been sent
		$message = $this->getErrorMessage($error);
		$url = $this->getCurrentUrl();
		$sendOutput = $config->allowExceptions !== true;
	
		// use text-only output if an http request that is ajax
		if($useHTML && $config->ajax) $useHTML = false;

		// include IP address is user name if configured to do so
		if($config->logIP) { 
			$session = $this->wire()->session;
			if($session) {
				$ip = $session->getIP();
				if(strlen($ip)) $name = "$name ($ip)";
			}
		}

		// save to errors.txt log file
		if($this->saveFatalLog($url, $name, $message)) {
			$who[] = $this->labels['error-logged'];
		}

		// if not allowed to send output, then do nothing further
		if(!$sendOutput) return true;
		
		// send error email if applicable
		if($this->sendFatalEmail($url, $name, $message)) {
			$who[] = $this->labels['admin-notified'];
		}

		// we populate $why if we're going to show error details for any of the following reasons: 
		// otherwise $why will NOT be populated with anything
		$why = $this->getReasonsWhy();
		$who = implode(' ', $who); 
		
		if(count($why)) {
			$why = reset($why); // show only 1st reason
			$why = $this->labels['shown-because'] . " $why $who";
			$message = $this->amendErrorMessage($message);
			$this->sendFatalHeader();
			$this->sendErrorMessage($message, $why, $useHTML);
		} else {
			$this->sendFatalError($who, $useHTML);
		}

		return true;
	}
	
	/**
	 * Get reasons why a fatal error message is shown
	 * 
	 * If error details should not be shown then return a blank array
	 *
	 * @return array
	 *
	 */
	protected function getReasonsWhy() {

		$config = $this->config;
		$user = $this->wire()->user;
		$why = array();

		if($user && $user->isSuperuser()) {
			$why[] = $this->labels['you-superuser'];
		}

		if(!$config) return $why;

		if($config->debug) {
			$why[] = $this->labels['debug-mode'] . " (\$config->debug = true; => /site/config.php).";
		}

		if($config->cli) {
			$why[] = $this->labels['cli-mode'];
		}

		if(is_file($config->paths->root . 'install.php')) {
			$why[] = $this->labels['install-php'];
		}

		$path = $config->paths->assets;

		if($path && !is_file($path . 'active.php')) {
			// no login has ever occurred or user hasn’t logged in since upgrade before this check was in place
			// check the date the site was installed to ensure we're not dealing with an upgrade
			$installed = $path . 'installed.php';
			$ts = time() - 21600;
			if(!is_file($installed) || (filemtime($installed) > $ts)) {
				// site was installed within the last 6 hours, safe to assume it’s a new install
				$why[] = $this->labels['superuser-never'];
			}
		}

		return $why;
	}

	/**
	 * Save fatal error to log
	 * 
	 * @param string $url
	 * @param string $userName
	 * @param string $message
	 * @return bool
	 * 
	 */
	protected function saveFatalLog($url, $userName, $message) {
		// save to errors.txt log file if applicable
		$config = $this->config;
		if(!$config->paths->logs) return false;
		$message = str_replace(array("\n", "\t"), " ", $message);
		try {
			/** @var FileLog $log */
			$log = $this->wire(new FileLog($config->paths->logs . 'errors.txt'));
			$log->setDelimeter("\t");
			$saved = $log->save("$userName\t$url\t$message"); 
		} catch(\Exception $e) {
			$saved = false;
		}
		return $saved;
	}

	/**
	 * Send fatal error email
	 * 
	 * @param string $url
	 * @param string $userName
	 * @param string $message
	 * @return bool
	 * 
	 */
	protected function sendFatalEmail($url, $userName, $message) {
		
		$settings = $this->config ? $this->config->wireMail : array(); 
		$options = array();
		$user = $this->wire()->user;
		$version = $this->config ? $this->config->versionName : '';
		
		if(!$this->wire()->mail || empty($message)) return false;
		
		$emailTo = $this->fatalErrorResponse['emailTo'];
		if(empty($emailTo) && $this->config) $emailTo = $this->config->adminEmail;
		if(empty($emailTo)) return false;
		if($user && $user->email === $emailTo) return false; // don't send email to admin user that saw error message
		
		$emailFrom = $this->fatalErrorResponse['emailFrom'];
		if(empty($emailFrom) && !empty($settings['from'])) $emailFrom = $settings['from'];
		
		$emailFromName = $this->fatalErrorResponse['emailFromName'];
		if(empty($emailFromName) && !empty($settings['fromName'])) $emailFromName = $settings['fromName'];
		if(empty($emailFromName)) $emailFromName = 'ProcessWire';
		
		$emailSubject = $this->fatalErrorResponse['emailSubject'];
		if(empty($emailSubject)) $emailSubject = $this->labels['email-subject'];
		if(strpos($emailSubject, '{host}') === false && $this->config) $emailSubject .= " - {host}";
		if($this->config) $emailSubject = str_replace('{host}', $this->config->httpHost, $emailSubject);
		
		$emailModule = $this->fatalErrorResponse['emailModule'];
		if(!empty($emailModule)) {
			if($emailModule !== 'WireMail') {
				$modules = $this->wire()->modules;
				if(!$modules || !$modules->isInstalled($emailModule)) $emailModule = '';
			}
			if($emailModule) $options['module'] = $emailModule;
		}

		$emailBody = $this->fatalErrorResponse['emailBody'];
		if(empty($emailBody)) $emailBody = self::defaultEmailBody;

		$message = $this->amendErrorMessage($message);
		$message = $this->seasonErrorMessage($message);

		$emailBody = str_replace(
			array('{url}', '{user}', '{message}', '{version}'),
			array($url, $userName, str_replace("\t", "\n", $message), $version),
			$emailBody
		);

		$emailBodyHTML = $this->fatalErrorResponse['emailBodyHTML'];
		if(empty($emailBodyHTML)) {
			$emailBodyHTML = $this->config ? $this->config->fatalErrorHTML : '';
			if($emailBodyHTML) {
				// use configured runtime fatal error HTML for email, replacing the {why}
				$why = "User: {user}";
				if($this->config) $why .= ", Version: {version}"; 
				$emailBodyHTML = str_replace('{why}', $why, $emailBodyHTML);
				$emailBodyHTML .= "<p><a href='{url}'>{url}</a></p>";
			} else {
				// convert text-only body to HTML
				$emailBodyHTML = trim(htmlspecialchars($emailBody, ENT_QUOTES, "UTF-8"));
				$emailBodyHTML = "<p>" . nl2br(str_replace("\n\n", "</p><p>", $emailBodyHTML)) . "</p>";
			}
		}

		$messageHTML = $message;
		if(strpos($message, '&') !== false) {
			$messageHTML = html_entity_decode($message, ENT_QUOTES, "UTF-8");
		}
		$emailBodyHTML = str_replace(
			array('{url}', '{user}', '{message}', '{version}'),
			array(
				htmlentities($url, ENT_QUOTES, "UTF-8"),
				htmlentities($userName, ENT_QUOTES, "UTF-8"),
				nl2br(htmlentities($messageHTML, ENT_QUOTES, "UTF-8")),
				htmlentities($version, ENT_QUOTES, "UTF-8"), 
			),
			$emailBodyHTML
		);

		$emailBody = $this->simplifyErrorMessageText($emailBody);
		$emailBodyHTML = $this->simplifyErrorMessageHTML($emailBodyHTML);
		$emailBodyHTML = str_replace("\t", " ", $emailBodyHTML);
		while(strpos($emailBodyHTML, '  ') !== false) $emailBodyHTML = str_replace('  ', ' ', $emailBodyHTML);
		
		if($emailBodyHTML && stripos($emailBodyHTML, "</html>") === false) {
			$emailBodyHTML = 
				"<!DOCTYPE html><html><head>" . 
				"<meta http-equiv='content-type' content='text/html; charset=utf-8' /></head>" . 
				"<body>$emailBodyHTML</body></html>";
		}
		
		try {
			$mail = $this->wire()->mail->___new($options);
			$mail->to($emailTo)->subject($emailSubject)->body($emailBody);
			if(!empty($emailBodyHTML)) $mail->bodyHTML($emailBodyHTML);
			if($emailFrom) $mail->from($emailFrom);
			if($emailFromName) $mail->fromName($emailFromName);
			$sent = $mail->send();
		} catch(\Exception $e) {
			$sent = false;
		}
		
		return $sent ? true : false;
	}

	/**
	 * Secondary shutdown call when ProcessWire booted externally
	 * 
	 */
	public function shutdownExternal() {
		$error = $this->getError();
		if(!empty($error)) return; 
		/** @var ProcessPageView $process */
		$process = $this->wire()->process;
		if($process == 'ProcessPageView') $process->finished();
	}
}
