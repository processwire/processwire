<?php namespace ProcessWire;

/**
 * ProcessWire Mail Tools ($mail API variable)
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Provides an API interface to email and WireMail. 
 * #pw-body = 
 * ~~~~~
 * // Simple usage example
 * $message = $mail->new();
 * $message->subject('Hello world')
 *   ->to('user@domain.com')
 *   ->from('you@company.com')
 *   ->body('Hello there big world')
 *   ->bodyHTML('<h2>Hello there big world</h2>');
 * $numSent = $message->send();
 * 
 * // ProcessWire 3.0.113 lets you skip the $mail->new() call if you want:
 * $numSent = $mail->subject('Hello world')
 *   ->to('user@domain.com')
 *   ->from('you@company.com')
 *   ->body('Hello there big world')
 *   ->bodyHTML('<h2>Hello there big world</h2>')
 *   ->send();
 * ~~~~~
 * #pw-body
 * 
 * @method WireMail new($options = array()) Create a new WireMail() instance
 * @method bool|string isBlacklistEmail($email, array $options = array())
 * @property WireMail new Get a new WireMail() instance (same as method version)
 *
 *
 */

class WireMailTools extends Wire {

	/**
	 * Get a new WireMail instance for sending email
	 * 
	 * Note: The `$options` argument added in 3.0.123, previous versions had no $options argument.
	 * 
	 * ~~~~~
	 * $message = $mail->new();
	 * $message->to('user@domain.com')->from('you@company.com');
	 * $message->subject('Mail Subject')->body('Mail Body Text')->bodyHTML('Body HTML');
	 * $numSent = $message->send();
	 * ~~~~~
	 * 
	 *
	 * @param array|string $options Optional settings to override defaults, or string for `module` option:
	 *  - `module` (string): Class name of WireMail module you want to use rather than auto detect, or 'WireMail' to force using default PHP mail. 
	 *    If requested module is not available, it will fall-back to one that is (or PHP mail), so check class name of returned value if there
	 *    is any doubt about what WireMail module is being used. 
	 *  - You may also specify: subject, from, fromName, to, toName, subject or any other WireMail property and it will be populated. 
	 * @return WireMail
	 *
	 */
	public function ___new($options = array()) {
	
		if(is_string($options) && !empty($options)) $options = array('module' => $options); 
		if(!is_array($options)) $options = array();

		/** @var WireMail|null $mail */
		$mail = null;
		
		/** @var Modules $modules */
		$modules = $this->wire('modules');
	
		// merge config settings with requested options
		$settings = $this->wire('config')->wireMail;
		if(!is_array($settings)) $settings = array();
		if(count($options)) $settings = array_merge($settings, $options);
		
		// see if a specific WireMail module is requested
		if(!empty($settings['module'])) {
			if(strtolower($settings['module']) === 'wiremail') {
				$mail = $this->wire(new WireMail()); 
			} else {
				$mail = $modules->getModule($settings['module']);
			}
			unset($settings['module']); 
		}

		if(!$mail) {
			// attempt to locate an installed module that overrides WireMail
			foreach($modules->findByPrefix('WireMail') as $module) {
				$parents = wireClassParents("$module");
				if(in_array('WireMail', $parents) && $modules->isInstalled("$module")) {
					$mail = $modules->get("$module");
					break;
				}
			}
		}
		
		// if no module found, default to WireMail base class
		if(!$mail) {
			$mail = $this->wire(new WireMail());
		}
	
		// if anything left in settings, apply as a default setting
		if(!empty($settings)) {
			foreach($settings as $key => $value) {
				$mail->set($key, $value); 
			}
		}

		// reset just in case module was not singular
		$mail->to();

		return $mail;
	}

	/**
	 * Send an email 
	 *
	 * - Note that the order of arguments is slightly different from PHP's `mail()` function.
	 * - If no arguments are specified it simply returns a `WireMail` object (see #5 in examples).
	 * - This function will attempt to use an installed module that extends `WireMail`.
	 *   If no module is installed, `WireMail` (which uses PHP mail) will be used instead.
	 *
	 * ~~~~~
	 * // 1. Default usage:
	 * $mail->send($to, $from, $subject, $body);
	 * 
	 * // 2. Default usage with options array:
	 * $mail->send($to, $from, $subject, $body, $options); 
	 *  
	 * // 3. Specify body and/or bodyHTML in $options array (perhaps with other options):
	 * $mail->send($to, $from, $subject, $options);
	 *  
	 * // 4. Specify both $body and $bodyHTML as arguments, but no $options:
	 * $mail->send($to, $from, $subject, $body, $bodyHTML);
	 *  
	 * // 5. Specify a blank call to wireMail() to get the WireMail sending module:
	 * $wireMail = $mail->send();
	 * ~~~~~
	 *
	 * @param string|array $to Email address TO. For multiple, specify CSV string or array.
	 * @param string $from Email address FROM. This may be an email address, or a combined name and email address.
	 *   Example of combined name and email: `Karen Cramer <karen@processwire.com>`
	 * @param string $subject Email subject
	 * @param string|array $body Email body or omit to move straight to $options
	 * @param array|string $options Array of options OR the $bodyHTML string. Array $options are:
	 *  - `body` (string): Email body (text)
	 *  - `bodyHTML` (string): Email body (HTML)
	 *  - `replyTo` (string): Reply-to email address
	 *  - `headers` (array): Associative array of header name => header value
	 *  - Any additional options will be sent along to the WireMail module or class, in tact.
	 * @return int|WireMail Returns number of messages sent or WireMail object if no arguments specified.
	 *
	 */
	public function send($to = '', $from = '', $subject = '', $body = '', $options = array()) {

		$mail = $this->new();
	
		// if no $to address specified, return WireMail object
		if(empty($to)) return $mail;

		$defaults = array(
			'body' => is_string($body) ? $body : '',
			'bodyHTML' => '',
			'replyTo' => '', // email address
			'headers' => array(),
		);

		if(is_array($body)) {
			// use case #2: body is provided in $options
			$options = $body;
		} else if(is_string($options)) {
			// use case #3: body and bodyHTML are provided, but no $options
			$options = array('bodyHTML' => $options);
		} else {
			// use case #1: default behavior
		}

		$options = array_merge($defaults, $options);
		
		if(!empty($options['replyTo'])) {
			$replyTo = $this->wire('sanitizer')->email($options['replyTo']);
			if($replyTo) $options['headers']['Reply-to'] = $replyTo;
			unset($options['replyTo']);
		}

		try {
			// configure the mail
			$mail->to($to)->subject($subject);
			if(strlen($from)) $mail->from($from);
			if(strlen($options['bodyHTML'])) $mail->bodyHTML($options['bodyHTML']);
			if(strlen($options['body'])) $mail->body($options['body']);
			if(count($options['headers'])) foreach($options['headers'] as $k => $v) $mail->header($k, $v);
			// send along any options we don't recognize
			foreach($options as $key => $value) {
				if(!array_key_exists($key, $defaults)) $mail->$key = $value;
			}
			$numSent = $mail->send();

		} catch(\Exception $e) {
			if($this->wire('config')->debug) $mail->error($e->getMessage());
			$mail->trackException($e, false);
			$numSent = 0;
		}

		return $numSent;
	}

	/**
	 * Send an email with given message text assumed to be HTML 
	 * 
	 * This is just like the `$mail->send()` method with the exception that the body argument 
	 * is assumed to be HTML rather than text. Note that the text version of the email is auto
	 * generated from the HTML, unless a `body` is provided in the `$options` array.
	 *
	 * @param string|array $to Email address TO. For multiple, specify CSV string or array.
	 * @param string $from Email address FROM. This may be an email address, or a combined name and email address.
	 *   Example of combined name and email: `Karen Cramer <karen@processwire.com>`
	 * @param string $subject Email subject
	 * @param string $bodyHTML Email body in HTML
	 * @param array|string $options Array of options OR the $bodyHTML string. Array $options are:
	 *  - `body` (string): Email body (text)
	 *  - `replyTo` (string): Reply-to email address
	 *  - `headers` (array): Associative array of header name => header value
	 *  - Any additional options will be sent along to the WireMail module or class, in tact.
	 * @return int|WireMail Returns number of messages sent or WireMail object if no arguments specified.
	 * 
	 */
	public function sendHTML($to = '', $from = '', $subject = '', $bodyHTML = '', $options = array()) {
		$options['bodyHTML'] = $bodyHTML;
		return $this->send($to, $from, $subject, $options);
	}

	/**
	 * Send an email, drop-in replacement for PHP mail() that uses the same arguments
	 * 
	 * This is an alternative to using the `$mail->send()` method, and may be simpler for those converting
	 * existing PHP `mail()` calls to WireMail calls. 
	 * 
	 * This function duplicates the same arguments as PHP’s mail function, enabling you to replace an existing 
	 * PHP `mail(…)` call with `$mail->mail(…)`. 
	 * 
	 * But unlike PHP’s mail function, this one can also send HTML (or multipart) emails if you provide 
	 * an `$options` array for the `$message` argument (rather than a string). See the options array for 
	 * the `$mail->send()` method for details. 
	 * ~~~~~
	 * // 1. Basic PHP mail() style usage
	 * $mail->mail('ryan@processwire.com', 'Subject', 'Message body');
	 * 
	 * // 2. PHP mail() style usage with with $headers argument
	 * $mail->mail('ryan@processwire.com', 'Subject', 'Message body', 'From: hello@world.com'); 
	 * 
	 * // 3. Alternate usage with html and text body
	 * $mail->mail('ryan@processwire.com', 'Subject', [
	 *   'bodyHTML' => '<html><body><h1>Message HTML body</h1></body</html>',
	 *   'body' => 'Message text body',
	 *   'from' => 'hello@world.com',
	 * ]);
	 * ~~~~~
	 * 
	 * @param string|array $to Email address TO. For multiple, specify CSV string or array. 
	 * @param string $subject Email subject
	 * @param string|array $message Email body (PHP mail style), OR specify $options array with any of the following:
	 *  - `bodyHTML` (string): Email body (HTML)
	 *  - `body` (string): Email body (text). If not specified, and bodyHTML is, then text body will be auto-generated.
	 *  - `from` (string): From email address
	 *  - `replyTo` (string): Reply-to email address
	 *  - `headers` (array): Associative array of header name => header value
	 * @param array $headers Optional additional headers as [name=value] array or "Name: Value" newline-separated string. 
	 *   Use this argument to duplicate PHP mail() style arguments. No need to use if you used $options array for the $message argument.
	 * @return bool True on success, false on fail.
	 * 
	 */
	public function mail($to, $subject, $message, $headers = array()) {
		$from = '';
		
		if(is_string($headers)) {
			$_headers = explode("\n", $headers); 
			$headers = array();
			foreach($_headers as $header) {
				if(!strpos($header, ':')) continue;
				list($key, $val) = explode(':', $header, 2);
				$headers[trim($key)] = trim($val);
			}
		}
		
		foreach($headers as $key => $val) {
			if(strtolower($key) !== 'from') continue;
			$from = $val;
			unset($headers[$key]); 
			break;
		}
	
		if(is_array($message)) {
			// message is $options array
			$options = $message;
			if(!empty($options['headers'])) $headers = array_merge($headers, $options['headers']);
			$options['headers'] = $headers;
			if(isset($options['from'])) {
				if(empty($from)) $from = $options['from'];
				unset($options['from']);
			}
			$qty = $this->send($to, $from, $subject, $options); 
			
		} else {
			// regular PHP style mail() call converted to $mail->send() call
			$qty = $this->send($to, $from, $subject, $message, $headers);
		}
		
		return $qty > 0;
	}

	/**
	 * Send an email with message assumed to be in HTML
	 * 
	 * This is the same as the `$mail->mail()` method except that the message argument is
	 * assumed to be HTML rather than text. The text version of the email will be auto-generated
	 * from the given HTML.
	 * 
	 * @param string|array $to Email address TO. For multiple, specify CSV string or array.
	 * @param string $subject Email subject
	 * @param string|array Email message in HTML
	 * @param array $headers Optional additional headers as [name=value] array or "Name: Value" newline-separated string.
	 *   Use this argument to duplicate PHP mail() style arguments. No need to use if you used $options array for the $message argument.
	 * @return bool True on success, false on fail.
	 * @since 3.0.109
	 * 
	 */
	public function mailHTML($to, $subject, $messageHTML, $headers = array()) {
		if(is_array($messageHTML)) {
			$options = $messageHTML;
			if(!empty($headers) && empty($options['headers'])) $options['headers'] = $headers;
		} else {
			$options = array(
				'bodyHTML' => $messageHTML,
				'headers' => $headers
			);
		}
		return $this->mail($to, $subject, $options); 
	}

	/**
	 * Return new WireMail instance populated with “to” email
	 * 
	 * @param string|array $email Email to send to–specify any one of the following:
	 * - Single email address 
	 * - String like: "John Smith <user@example.com>"
	 * - CSV string of either of the above. 
	 * - Regular PHP array of email addresses.
	 * - Associative array of ['user@xample.com' => 'John Smith'].
	 * @param string $name An optional TO name, applies only if your $email argument was just an email address. 
	 * @return WireMail
	 * @throws WireException if given invalid email address
	 * @since 3.0.113
	 * 
	 */
	public function to($email, $name = null) {
		return $this->new()->to($email, $name);
	}
	
	/**
	 * Return new WireMail instance populated with “from” email
	 *
	 * @param string $email Must be a single email address or "User Name <user@example.com>" string.
	 * @param string|null An optional FROM name
	 * @return WireMail
	 * @since 3.0.113
	 *
	 */
	public function from($email, $name = null) {
		return $this->new()->from($email, $name);
	}
	
	/**
	 * Return new WireMail instance populated with subject
	 *
	 * @param string $subject
	 * @return WireMail
	 * @since 3.0.113
	 *
	 */
	public function subject($subject) {
		return $this->new()->subject($subject);
	}
	
	public function __get($key) {
		if($key === 'new') return $this->new();
		return parent::__get($key);
	}
	
	/**
	 * Is given email address in the blacklist?
	 *
	 * - Returns boolean false if not blacklisted, true if it is.
	 * - Uses `$config->wireMail['blacklist']` array unless given another blacklist array in $options.
	 * - Always independently verify that your blacklist rules are working before assuming they do.
	 * - Specify true for the `why` option if you want to return the matching rule when email is in blacklist.
	 * - Specify true for the `throw` option if you want a WireException thrown when email is blacklisted.
	 *
	 * ~~~~~
	 * // Define blacklist in /site/config.php
	 * $config->wireMail('blacklist', [
	 *   'email@domain.com', // blacklist this email address
	 *   '@host.domain.com', // blacklist all emails ending with @host.domain.com
	 *   '@domain.com', // blacklist all emails ending with @domain.com
	 *   'domain.com', // blacklist any email address ending with domain.com (would include mydomain.com too).
	 *   '.domain.com', // blacklist any email address at any host off domain.com (domain.com, my.domain.com, but NOT mydomain.com).
	 *   '/something/', // blacklist any email containing "something". PCRE regex assumed when "/" is used as opening/closing delimiter.
	 *   '/.+@really\.bad\.com$/', // another example of using a PCRE regular expression (blocks all "@really.bad.com").
	 * ]);
	 *
	 * // Test if email in blacklist
	 * $email = 'somebody@domain.com';
	 * $result = $mail->isBlacklistEmail($email, [ 'why' => true ]);
	 * if($result === false) {
	 *   echo "<p>Email address is not blacklisted</p>";
	 * } else {
	 *   echo "<p>Email is blacklisted by rule: $result</p>";
	 * }
	 * ~~~~~
	 *
	 * @param string $email Email to check
	 * @param array $options
	 *  - `blacklist` (array): Use this blacklist rather than `$config->emailBlacklist` (default=[])
	 *  - `throw` (bool): Throw WireException if email is blacklisted? (default=false)
	 *  - `why` (bool): Return string containing matching rule when email is blacklisted? (default=false)
	 * @return bool|string Returns true if email is blacklisted, false if not. Returns string if `why` option specified + email blacklisted.
	 * @throws WireException if given a blacklist that is not an array, or if requested to via `throw` option.
	 * @since 3.0.129
	 *
	 */
	public function ___isBlacklistEmail($email, array $options = array()) {

		$defaults = array(
			'blacklist' => array(),
			'throw' => false,
			'why' => false,
		);

		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		$blacklist = $options['blacklist'];
		if(empty($blacklist)) $blacklist = $this->wire('config')->wireMail('blacklist');
		if(empty($blacklist)) return false;
		if(!is_array($blacklist)) throw new WireException("Email blacklist must be array");

		$inBlacklist = false;
		$tt = $this->wire('sanitizer')->getTextTools();
		$email = trim($tt->strtolower($email));
		
		if(strpos($email, '@') === false) {
			return $options['why'] ? "Invalid email address" : true;
		}

		foreach($blacklist as $line) {
			$line = $tt->strtolower(trim($line));
			if(!strlen($line)) continue;
			if(strpos($line, '/') === 0) {
				// perform a regex match
				if(preg_match($line, $email)) $inBlacklist = $line;
			} else if(strpos($line, '@')) {
				// full email (@ is present and is not first char)
				if($email === $line) $inBlacklist = $line;
			} else if(strpos($line, '.') === 0) {
				// any hostname at domain (.domain.com)
				list(,$emailDomain) = explode('@', $email);
				if($emailDomain === ltrim($line, '.')) {
					$inBlacklist = $line;
				} else if($tt->substr($emailDomain, -1 * $tt->strlen($line)) === $line ) {
					$inBlacklist = $line;
				}
			} else {
				// match ending string, host or domain name (host.domain.com, domain.com)
				if($tt->substr($email, -1 * $tt->strlen($line)) === $line) $inBlacklist = $line;
			}
			if($inBlacklist) break;
		}

		if(!$inBlacklist && strpos($email, '+')) {
			// leading part of email contains a plus, so check again without the "+portion"
			// i.e. ryan+test@domain.com
			list($prefix, $rest) = explode('+', $email, 2);
			list(,$hostname) = explode('@', $rest, 2);
			$email = "$prefix@$hostname";
			$inBlacklist = $this->isBlacklistEmail($email, $options);
		}

		if($inBlacklist !== false && $options['throw']) {
			throw new WireException("Email matches blacklist" . ($options['why'] ? " ($inBlacklist)" : ""));
		}

		if(!$options['why'] && $inBlacklist !== false) $inBlacklist = true;

		return $inBlacklist;
	}


}