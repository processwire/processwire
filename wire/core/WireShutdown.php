<?php namespace ProcessWire;

/**
 * ProcessWire shutdown handler
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 *  
 * Look for errors at shutdown and log them, plus echo the error if the page is editable
 *
 * https://processwire.com
 *
 */

class WireShutdown extends Wire {
	
	protected $types = array();
	protected $fatalTypes = array();
	protected $labels = array();
	
	public function __construct() {
		register_shutdown_function(array($this, 'shutdown'));
		$this->fatalTypes = array(
			E_ERROR,
			E_CORE_ERROR,
			E_COMPILE_ERROR,
			E_USER_ERROR,
			E_PARSE,
			E_RECOVERABLE_ERROR,
		);
	}
	
	protected function prepareLabels() {
		$this->types = array(
			E_ERROR             => $this->_('Error'),
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

	public function shutdown() {
		$error = error_get_last();
		if(!$error) return true;
		$type = $error['type'];
		if(!in_array($type, $this->fatalTypes)) return true;
		
		$this->prepareLabels();
		$http = isset($_SERVER['HTTP_HOST']);
		$config = $this->wire('config');
		$user = $this->wire('user');
		$userName = $user ? $user->name : '?';
		$page = $this->wire('page');
		$path = ($config ? $config->httpHost : '') . ($page ? $page->url : '/?/');
		if($config && $http) $path = ($config->https ? 'https://' : 'http://') . $path;
		$line = $error['line'];
		$file = $error['file'];
		$message = isset($this->types[$type]) ? $this->types[$type] : $this->types[E_ERROR];
		if(strpos($error['message'], "\t") !== false) $error['message'] = str_replace("\t", ' ', $error['message']);
		$message .= ": \t$error[message]";
		if($type != E_USER_ERROR) $message .= ' ' . sprintf($this->labels['line-of-file'], $line, $file) . ' ';
		$debug = false;
		$log = null;
		$why = '';
		$who = '';
		$sendOutput = true;

		if($config) {
			$debug = $config->debug;
			$sendOutput = $config->allowExceptions !== true;
			if($config->ajax) $http = false;
			if((function_exists("\\ProcessWire\\wireMail") || function_exists("wireMail")) && $config->adminEmail && $sendOutput) {
				$logMessage = "Page: $path\nUser: $userName\n\n" . str_replace("\t", "\n", $message);
				wireMail($config->adminEmail, $config->adminEmail, $this->labels['email-subject'], $logMessage);
			}
			if($config->paths->logs) {
				$logMessage = "$userName\t$path\t" . str_replace("\n", " ", $message);
				$log = $this->wire(new FileLog($config->paths->logs . 'errors.txt'));
				$log->setDelimeter("\t");
				$log->save($logMessage);
			}
		}

		if(!$sendOutput) return true;

		// we populate $who to give an ambiguous indication where the full error message has been sent
		if($log) $who .= $this->labels['error-logged'] . ' ';
		if($config && $config->adminEmail) $who .= $this->labels['admin-notified'];

		// we populate $why if we're going to show error details for any of the following reasons: 
		// otherwise $why will NOT be populated with anything
		if($debug) $why = $this->labels['debug-mode'] . " (\$config->debug = true; => /site/config.php).";
		else if(!$http) $why = $this->labels['cli-mode'];
		else if($user && $user->isSuperuser()) $why = $this->labels['you-superuser'];
		else if($config && is_file($config->paths->root . "install.php")) $why = $this->labels['install-php'];
		else if($config && !is_file($config->paths->assets . "active.php")) {
			// no login has ever occurred or user hasn't logged in since upgrade before this check was in place
			// check the date the site was installed to ensure we're not dealing with an upgrade
			$installed = $config->paths->assets . "installed.php";
			if(!is_file($installed) || (filemtime($installed) > (time() - 21600))) {
				// site was installed within the last 6 hours, safe to assume it's a new install
				$why = $this->labels['superuser-never'];
			}
		}

		if($why) {
			// when in debug mode, we can assume the message was already shown, so we just say why.
			// when not in debug mode, we display the full error message since error_reporting and display_errors are off.
			$why = $this->labels['shown-because'] . " $why $who";
			$html = "<p><b>{message}</b><br /><small>{why}</small></p>";
			if($http) {
				if($config && $config->fatalErrorHTML) $html = $config->fatalErrorHTML;
				$html = str_replace(array('{message}', '{why}'), array(
					nl2br(htmlspecialchars($message, ENT_QUOTES, "UTF-8", false)),
					htmlspecialchars($why, ENT_QUOTES, "UTF-8", false)), $html);
				// make a prettier looking debug backtrace, when applicable
				$html = preg_replace('!(<br[^>]*>\s*)(#\d+\s+[^<]+)!is', '$1<code>$2</code>', $html);
				$html = str_replace('assets/cache/FileCompiler/site/', '', $html);
				echo "\n\n$html\n\n";
			} else {
				echo "\n\n$message\n\n$why\n\n";
			}
		} else {
			// public fatal error that doesn't reveal anything specific
			if($http) header("HTTP/1.1 500 Internal Server Error");
			// file that error message will be output in, when available
			$file = $config && $http ? $config->paths->templates . 'errors/500.html' : '';
			if($file && is_file($file)) {
				// use defined /site/templates/errors/500.html file
				echo str_replace('{message}', $who, file_get_contents($file));
			} else {
				// use generic error message, since no 500.html available
				echo "\n\n" . $this->labels['unable-complete'] . ($who ? " - $who" : "") . "\n\n";
			}
		}

		return true;
	}
}

