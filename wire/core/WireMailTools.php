<?php namespace ProcessWire;

/**
 * ProcessWire Mail Tools ($mail API variable)
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
 * ~~~~~
 * #pw-body
 * 
 * @method WireMail new() Create a new WireMail() instance
 * @property WireMail new Get a new WireMail() instance (same as method version)
 *
 *
 */

class WireMailTools extends Wire {

	/**
	 * Get a new WireMail instance for sending email
	 * 
	 * ~~~~~
	 * $message = $mail->new();
	 * $message->to('user@domain.com')->from('you@company.com');
	 * $message->subject('Mail Subject')->body('Mail Body Text')->bodyHTML('Body HTML');
	 * $numSent = $message->send();
	 * ~~~~~
	 *
	 * @return WireMail
	 *
	 */
	public function ___new() {

		$mail = null;
		$modules = $this->wire('modules');

		// attempt to locate an installed module that overrides WireMail
		foreach($modules as $module) {
			$parents = wireClassParents("$module");
			if(in_array('WireMail', $parents) && $modules->isInstalled("$module")) {
				$mail = $modules->get("$module");
				break;
			}
		}
		// if no module found, default to WireMail
		if(is_null($mail)) $mail = $this->wire(new WireMail());
		/** @var WireMail $mail */

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
			'body' => $body,
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
			$mail->to($to)->from($from)->subject($subject)->body($options['body']);
			if(strlen($options['bodyHTML'])) $mail->bodyHTML($options['bodyHTML']);
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

	public function __get($key) {
		if($key === 'new') return $this->new();
		return parent::__get($key);
	}

}