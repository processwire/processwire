<?php namespace ProcessWire;

/**
 * ProcessWire shutdown handler
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 *  
 * Look for errors at shutdown and log them, plus echo the error if the page is editable
 *
 * https://processwire.com
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
	 * Default HTML to use for error message
	 * 
	 * Can be overridden with $config->fatalErrorHTML in /site/config.php
	 * 
	 */
	const defaultFatalErrorHTML = '<p><b>{message}</b><br /><small>{why}</small></p>';

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
			E_STRICT            => $this->_('Strict Warning'),
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
		
		$type = $error['type'];
		
		if(isset($this->types[$type])) {
			$errorType = $this->types[$type];
		} else {
			$errorType = $this->types[E_USER_ERROR];
		}
		
		$message = str_replace("\t", ' ', $error['message']);
		
		if($type != E_USER_ERROR) {
			$detail = sprintf($this->labels['line-of-file'], $error['line'], $error['file']) . ' ';
		} else {
			$detail = '';
		}
		
		return "$errorType: \t$message $detail ";
	}

	/**
	 * Get WireInput instance and create it if not already present in the API
	 * 
	 * @return WireInput
	 * 
	 */
	protected function getWireInput() {
		/** @var WireInput $input */
		$input = $this->wire('input');
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
		
		/** @var Page|null $page */
		$page = $this->wire('page');
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
		
		$this->sendExistingOutput();
		
		// return text-only error
		if(!$useHTML) {
			echo "\n\n$message\n\n$why\n\n";
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
		
		// make a prettier looking debug backtrace, when applicable
		$html = preg_replace('!(<br[^>]*>\s*)(#\d+\s+[^<]+)!is', '$1<code>$2</code>', $html);
		
		// reference original file rather than compiled version, when applicable
		$html = str_replace('assets/cache/FileCompiler/site/', '', $html);
		
		// output the error message
		echo "\n\n$html\n\n";
	}

	/**
	 * Send a 500 internal server error
	 * 
	 * This is a public fatal error that doesn’t reveal anything specific.
	 * 
	 * @param string $message Message to indicate who error was also sent to 
	 * @param bool $useHTML Output for a web browser?
	 * 
	 */
	protected function sendError500($message, $useHTML) {
		
		if($useHTML) {
			header("HTTP/1.1 500 Internal Server Error");
			$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
			// file that error message will be output in, when available
			$file = $this->config->paths->templates . 'errors/500.html';
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
	 */
	protected function sendExistingOutput() {
		
		$files = TemplateFile::getRenderStack();
		if(!count($files)) return;
		
		$out = ob_get_clean();
		if(!strlen($out)) return;
		
		// if error message isn't in existing output, then reutrn as-is
		if(empty($this->error['message']) || strpos($out, $this->error['message']) === false) {
			echo $out;
			return;
		}

		$token = '';
		do {
			$token .= 'xPW' . mt_rand() . 'SD';
		} while(strpos($out, $token) !== false);
		
		// replace error message with token
		$out = str_replace($this->error['message'], $token, $out);
		
		// replace anything else on the same line as the PHP error (error type, file, line-number)
		$out = preg_replace('/([\r\n]|^)[^\r\n]+' . $token . '[^\r\n]*/', '', $out);
		
		echo $out;
	}

	/**
	 * Shutdown function registered with PHP
	 * 
	 * @return bool
	 * 
	 */
	public function shutdown() {
		
		/** @var Config|null $config */
		/** @var User|null $user */
		/** @var Page|null $page */

		$error = error_get_last();
		
		if(!$error) return true;
		if(!in_array($error['type'], $this->fatalTypes)) return true;
		
		$this->error = $error; 
		$this->prepareLabels();
		$config = $this->config;
		$user = $this->wire('user'); // current user, if present
		$useHTML = isset($_SERVER['HTTP_HOST']); // is this an HTTP request where we can output HTML?
		$name = $user ? $user->name : '?'; // user name
		$why = ''; // reason why error is being shown, when access allows
		$who = ''; // who/where the error message has been sent
		$message = $this->getErrorMessage($error);
		$url = $this->getCurrentUrl();
		$sendOutput = $config->allowExceptions !== true;
	
		// use text-only output if an http request that is ajax
		if($useHTML && $config->ajax) $useHTML = false;

		// include IP address is user name if configured to do so
		if($config->logIP && $this->wire('session')) {
			$ip = $this->wire('session')->getIP();
			if(strlen($ip)) $name = "$name ($ip)";
		}

		// send error email if applicable
		if($config->adminEmail && $sendOutput && $this->wire('mail')) {
			$n = $this->wire('mail')->new()
				->to($config->adminEmail)
				->from($config->adminEmail)
				->subject($this->labels['email-subject'])
				->body("Page: $url\nUser: $name\n\n" . str_replace("\t", "\n", $message))
				->send();
			if($n) $who .= $this->labels['admin-notified'];
		}
		
		// save to errors.txt log file if applicable
		if($config->paths->logs) {
			$log = $this->wire(new FileLog($config->paths->logs . 'errors.txt'));
			$log->setDelimeter("\t");
			$log->save("$name\t$url\t" . str_replace("\n", " ", $message));
			$who .= ($who ? ' ' : '') . $this->labels['error-logged'];
		}

		// if not allowed to send output, then do nothing further
		if(!$sendOutput) return true;

		// we populate $why if we're going to show error details for any of the following reasons: 
		// otherwise $why will NOT be populated with anything
		if($config->debug) {
			$why = $this->labels['debug-mode'] . " (\$config->debug = true; => /site/config.php).";
		} else if(!$useHTML) {
			$why = $this->labels['cli-mode'];
		} else if($user && $user->isSuperuser()) {
			$why = $this->labels['you-superuser'];
		} else if($config && is_file($config->paths->root . "install.php")) {
			$why = $this->labels['install-php'];
		} else if($config && $config->paths->assets && !is_file($config->paths->assets . "active.php")) {
			// no login has ever occurred or user hasn't logged in since upgrade before this check was in place
			// check the date the site was installed to ensure we're not dealing with an upgrade
			$installed = $config->paths->assets . "installed.php";
			if(!is_file($installed) || (filemtime($installed) > (time() - 21600))) {
				// site was installed within the last 6 hours, safe to assume it's a new install
				$why = $this->labels['superuser-never'];
			}
		} 
		
		if($why) {
			$why = $this->labels['shown-because'] . " $why $who";
			$message = $this->amendErrorMessage($message);
			$this->sendErrorMessage($message, $why, $useHTML);
		} else {
			$this->sendError500($who, $useHTML);
		}

		return true;
	}

	/**
	 * Secondary shutdown call when ProcessWire booted externally
	 * 
	 */
	public function shutdownExternal() {
		if(error_get_last()) return;
		$process = $this->wire('process');
		if($process == 'ProcessPageView') $process->finished();
	}
}

