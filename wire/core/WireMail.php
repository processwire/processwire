<?php namespace ProcessWire;

/**
 * ProcessWire WireMail
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary A module type that handles sending of email in ProcessWire
 * #pw-var $m
 * #pw-body = 
 *
 * Below are 2 different ways you can get a new instance of WireMail. 
 * ~~~~~
 * $m = $mail->new(); // option A: use $mail API variable
 * $m = wireMail(); // option B: use wireMail() function
 * ~~~~~
 * Once you have an instance of WireMail (`$m`), you can use it to send email like in these examples below. 
 * ~~~~~
 * // chained (fluent) method call usage
 * $m->to('user@domain.com')
 *   ->from('you@company.com')
 *   ->subject('Message Subject')
 *   ->body('Optional message body in plain text')
 *   ->bodyHTML('<html><body><p>Optional message body in HTML</p></body></html>')
 *   ->send();
 *
 * // separate method call usage
 * $m->to('user@domain.com'); // specify CSV string or array for multiple addresses
 * $m->from('you@company.com'); 
 * $m->subject('Message Subject'); 
 * $m->body('Message Body'); 
 * $m->send();
 *
 * // optionally specify “from” or “to” names as 2nd argument 
 * $m->to('user@domain.com', 'John Smith');
 * $m->from('you@company.com', 'Mary Jane'); 
 *
 * // other methods or properties you might set (or get)
 * $m->fromName('Mary Jane');
 * $m->toName('John Smith');
 * $m->replyTo('somebody@somewhere.com'); 
 * $m->replyToName('Joe Somebody');
 * $m->attachment('/path/to/file.ext'); 
 * $m->header('X-Mailer', 'ProcessWire'); 
 * $m->param('-f you@company.com'); // PHP mail() param (envelope from example)
 *
 * // note that the send() function always returns the quantity of messages sent
 * $numSent = $m->send();
 * ~~~~~
 * #pw-body
 * 
 * @method int send() Send email. 
 * @method string htmlToText($html) Convert HTML email body to TEXT email body. 
 * @method string sanitizeHeaderName($name) #pw-internal
 * @method string sanitizeHeaderValue($value) #pw-internal
 * 
 * @property array $to To email address.
 * @property array $toName Optional person’s name to accompany “to” email address
 * @property string $from From email address. 
 * @property string $fromName Optional person’s name to accompany “from” email address. 
 * @property string $replyTo Reply-to email address (where supported). #pw-advanced
 * @property string $replyToName Optional person’s name to accompany “reply-to” email address. #pw-advanced
 * @property string $subject Subject line of email.
 * @property string $body Plain text body of email.
 * @property string $bodyHTML HTML body of email. 
 * @property array $header Associative array of additional headers.
 * @property array $headers Alias of $header
 * @property array $param Associative array of aditional params (likely not applicable to most WireMail modules). 
 * @property array $attachments Array of file attachments (if populated and where supported) #pw-advanced
 * @property string $newline Newline character, populated only if different from CRLF. #pw-advanced
 * 
 *
 */

class WireMail extends WireData implements WireMailInterface {

	/**
	 * Mail properties
	 *
	 */
	protected $mail = array(
		'to' => array(), // to addresses - associative: both key and value are email (to prevent dups)
		'toName' => array(), // to names - associative: indexed by 'to' email address, may be blank/null for any email 
		'from' => '', 
		'fromName' => '', 
		'replyTo' => '',
		'replyToName' => '', 
		'subject' => '', 
		'body' => '',
		'bodyHTML' => '',
		'header' => array(),
		'param' => array(), 
		'attachments' => array(), 
	);

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->mail['header']['X-Mailer'] = "ProcessWire/" . $this->className();
		parent::__construct();
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed|null
	 * 
	 */
	public function get($key) {
		if($key === 'headers') $key = 'header';
		if(array_key_exists($key, $this->mail)) return $this->mail[$key]; 
		return parent::get($key);
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return $this|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'headers' || $key === 'header') {
			if(is_array($value)) $this->headers($value); 
		} else if(array_key_exists($key, $this->mail)) {
			$this->$key($value); // function call
		} else {
			parent::set($key, $value);
		}
		return $this;
	}
	
	public function __get($key) { return $this->get($key); }
	public function __set($key, $value) { return $this->set($key, $value); }

	/**
	 * Sanitize an email address or throw WireException if invalid or in blacklist
	 * 
	 * @param string $email
	 * @return string
	 * @throws WireException
	 * 
	 */
	protected function sanitizeEmail($email) {
		$email = (string) $email;
		if(!strlen($email)) return '';
		$email = strtolower(trim($email)); 
		if(strpos($email, ':') && preg_match('/^(.+):\d+$/', $email, $matches)) {
			// sending email in particular might sometimes be auto-generated from hostname
			// so remove trailing port, i.e. ':8888', if present since it will not validate
			$email = $matches[1]; 
		}
		$sanitizer = $this->wire()->sanitizer;
		$clean = $sanitizer->email($email); 
		if($email !== $clean) {
			throw new WireException("Invalid email address: " . $sanitizer->entities($email));
		}
		/** @var WireMailTools $mail */
		$mail = $this->wire('mail');
		if($mail && $mail->isBlacklistEmail($email)) {
			throw new WireException("Email address not allowed: " . $sanitizer->entities($email));
		}
		return $clean;
	}

	/**
	 * Sanitize and normalize a header name
	 *
	 * @param string $name
	 * @return string
	 * @since 3.0.132
	 *
	 */
	protected function ___sanitizeHeaderName($name) {
		$sanitizer = $this->wire()->sanitizer;
		$name = $sanitizer->emailHeader($name, true);
		// ensure consistent capitalization for header names
		$name = ucwords(str_replace('-', ' ', $name));
		$name = str_replace(' ', '-', $name);
		return $name;
	}

	/**
	 * Sanitize an email header header value
	 *
	 * @param string $value
	 * @return string
	 * @since 3.0.132
	 *
	 */
	protected function ___sanitizeHeaderValue($value) {
		return $this->wire()->sanitizer->emailHeader($value); 
	}

	/**
	 * Alias of sanitizeHeaderValue() method for backwards compatibility
	 * 
	 * #pw-internal
	 *
	 * @param string $header
	 * @return string
	 *
	 */
	protected function sanitizeHeader($header) {
		return $this->sanitizeHeaderValue($header);
	}

	/**
	 * Given an email string like "User <user@example.com>" extract and return email and username separately
	 *
	 * @param string $email
	 * @return array() Index 0 contains email, index 1 contains username or blank if not set
	 *
	 */
	protected function extractEmailAndName($email) {
		$name = '';
		$email = (string) $email;
		if(strpos($email, '<') !== false && strpos($email, '>') !== false) {
			// email has separate from name and email
			if(preg_match('/^(.*?)<([^>]+)>.*$/', $email, $matches)) {
				$name = $this->sanitizeHeaderValue($matches[1]);
				$email = $matches[2]; 
			}
		}
		$email = $this->sanitizeEmail($email); 
		return array($email, $name); 
	}

	/**
	 * Given an email and name, bundle it to an RFC 2822 string
	 *
	 * If name is blank, then just the email will be returned
	 *
	 * @param string $email
	 * @param string $name
	 * @return string
	 *
	 */
	protected function bundleEmailAndName($email, $name) {
		$email = $this->sanitizeEmail($email); 
		if(!strlen($name)) return $email;
		$name = $this->sanitizeHeaderValue($name); 
		$delim = '';
		if(strpos($name, ',') !== false) {
			// name contains a comma, so quote the value
			$name = str_replace('"', '', $name); // remove existing quotes
			$delim = '"';  // add quotes
		}
		// Encode the name part as quoted printable according to rfc2047
		return $delim . $this->quotedPrintableString($name) . $delim . " <$email>";
	}

	/**
	 * Set the email to address
	 *
	 * Each added email addresses appends to any addresses already supplied, unless
	 * you specify NULL as the email address, in which case it clears them all.
	 *
	 * @param string|array|null $email Specify any ONE of the following: 
	 * - Single email address or "User Name <user@example.com>" string.
	 * - CSV string of #1. 
	 * - Non-associative array of #1. 
	 * - Associative array of (email => name)
	 * - NULL (default value, to clear out any previously set values)
	 * @param string $name Optionally provide a TO name, applicable
	 *	only when specifying #1 (single email) for the first argument. 
	 * @return $this 
	 * @throws WireException if any provided emails were invalid or in blacklist
	 *
	 */
	public function to($email = null, $name = null) {

		if(is_null($email)) { 
			// clear existing values
			$this->mail['to'] = array(); 
			$this->mail['toName'] = array();
			return $this; 
		}

		$emails = is_array($email) ? $email : explode(',', $email); 

		foreach($emails as $key => $value) {

			$toName = '';
			if(is_string($key)) {
				// associative array
				// email provided as $key, and $toName as value 
				$toEmail = $key; 
				$toName = $value; 

			} else if(strpos($value, '<') !== false && strpos($value, '>') !== false) {
				// toName supplied as: "User Name <user@example.com"
				list($toEmail, $toName) = $this->extractEmailAndName($value); 

			} else {
				// just an email address, possibly with name as a function arg
				$toEmail = $value; 
			}

			if(empty($toName)) $toName = $name; // use function arg if not overwritten
			$toEmail = $this->sanitizeEmail($toEmail); 
			if(strlen($toEmail)) {
				$this->mail['to'][$toEmail] = $toEmail;
				$this->mail['toName'][$toEmail] = $this->sanitizeHeaderValue($toName);
			}
		}

		return $this; 
	}

	/**
	 * Set the 'to' name
	 *
	 * It is preferable to do this with the to() method, but this is provided to ensure that 
	 * all properties can be set with direct access, i.e. $mailer->toName = 'User Name';
	 *
 	 * This sets the 'to name' for whatever the last added 'to' email address was.
	 *
	 * @param string $name The 'to' name
	 * @return $this 
	 * @throws WireException if you attempt to set a toName before a to email. 
	 *
	 */
	public function toName($name) {
		$emails = $this->mail['to']; 
		if(!count($emails)) throw new WireException("Please set a 'to' address before setting a name."); 
		$email = end($emails); 
		$this->mail['toName'][$email] = $this->sanitizeHeaderValue($name); 
		return $this;
	}

	/**
	 * Set the email 'from' address and optionally name
	 *
	 * @param string $email Must be a single email address or "User Name <user@example.com>" string.
	 * @param string|null An optional FROM name (same as setting/calling fromName)
	 * @return $this 
	 * @throws WireException if provided email was invalid or in blacklist
	 *
	 */
	public function from($email, $name = null) {
		if(is_null($name)) {
			list($email, $name) = $this->extractEmailAndName($email);
		} else {
			$email = $this->sanitizeEmail($email);
		}
		if($name) $this->fromName($name); 
		$this->mail['from'] = $email;
		return $this; 
	}

	/**
	 * Set the 'from' name
	 *
	 * It is preferable to do this with the from() method, but this is provided to ensure that 
	 * all properties can be set with direct access, i.e. $mailer->fromName = 'User Name';
	 *
	 * @param string $name The 'from' name
	 * @return $this 
	 *
	 */
	public function fromName($name) {
		$this->mail['fromName'] = $this->sanitizeHeaderValue($name); 
		return $this; 
	}

	/**
	 * Set the 'reply-to' email address and optionally name (where supported)
	 *
	 * @param string $email Must be a single email address or "User Name <user@example.com>" string.
	 * @param string|null An optional Reply-To name (same as setting/calling replyToName method)
	 * @return $this
	 * @throws WireException if provided email was invalid or in blacklist
	 *
	 */
	public function replyTo($email, $name = null) {
		if(is_null($name)) {
			list($email, $name) = $this->extractEmailAndName($email);
		} else {
			$email = $this->sanitizeEmail($email);
		}
		if($name) $this->mail['replyToName'] = $this->sanitizeHeaderValue($name); 
		$this->mail['replyTo'] = $email;
		if(empty($name) && !empty($this->mail['replyToName'])) $name = $this->mail['replyToName']; 
		if(strlen($name)) $email = $this->bundleEmailAndName($email, $name); 
		$this->header('Reply-To', $email); 
		return $this; 
	}

	/**
	 * Set the 'reply-to' name (where supported)
	 * 
	 * @param string $name
	 * @return $this
	 * 
	 */
	public function replyToName($name) {
		if(strlen($this->mail['replyTo'])) return $this->replyTo($this->mail['replyTo'], $name); 
		$this->mail['replyToName'] = $this->sanitizeHeaderValue($name);
		return $this; 
	}

	/**
	 * Set the email subject
	 *
	 * @param string $subject Email subject text
	 * @return $this 
	 *
	 */
	public function subject($subject) {
		$this->mail['subject'] = $this->sanitizeHeaderValue($subject); 	
		return $this; 
	}

	/**
	 * Set the email message body (text only)
	 * 
	 * ~~~~~
	 * $m = wireMail();
	 * $m->body('Hello world');
	 * ~~~~~
	 *
	 * @param string $body Email body in text only
	 * @return $this 
	 *
	 */
	public function body($body) {
		$this->mail['body'] = $body; 
		return $this; 
	}

	/**
	 * Set the email message body (HTML only)
	 * 
	 * This should be the text from an entire HTML document, not just an element.
	 * 
	 * ~~~~~
	 * $m = wireMail();
	 * $m->bodyHTML('<html><body><h1>Hello world</h1></body></html>');
	 * ~~~~~
	 *
	 * @param string $body Email body in HTML
	 * @return $this 
	 *
	 */
	public function bodyHTML($body) {
		$this->mail['bodyHTML'] = $body; 
		return $this; 
	}

	/**
	 * Set any email header
	 *
	 * - Multiple calls will append existing headers. 
	 * - To remove an existing header, specify NULL as the value. 
	 * 
	 * #pw-advanced
	 *
	 * @param string|array $key Header name
	 * @param string $value Header value or specify null to unset
	 * @return $this 
	 *
	 */
	public function header($key, $value) {
		if(is_null($value)) {
			if(is_array($key)) {
				$this->headers($key);
			} else {
				$key = $this->sanitizeHeaderName($key);
				unset($this->mail['header'][$key]);
			}
		} else {
			$key = $this->sanitizeHeaderName($key);
			$value = $this->sanitizeHeaderValue($value); 
			if(strlen($key)) $this->mail['header'][$key] = $value; 
		}
		return $this; 
	}

	/**
	 * Set multiple email headers using associative array
	 * 
	 * @param array $headers
	 * @return $this
	 * 
	 */
	public function headers(array $headers) {
		foreach($headers as $key => $value) {
			$this->header($key, $value); 
		}
		return $this;
	}

	/**
	 * Set any email param 
	 *
	 * See `$additional_parameters` at <http://www.php.net/manual/en/function.mail.php>
	 * 
	 * - Multiple calls will append existing params. 
	 * - To remove an existing param, specify NULL as the value. 
	 * 
	 * This function may only be applicable if you don't have other WireMail modules
	 * installed as email params are only used by PHP's `mail()` function. 
	 * 
	 * #pw-advanced
	 *
	 * @param string $value
	 * @return $this 
	 *
	 */
	public function param($value) {
		if(is_null($value)) {
			$this->mail['param'] = array();
		} else { 
			$this->mail['param'][] = $value; 
		}
		return $this;
	}

	/**
	 * Add a file to be attached to the email
	 * 
	 * ~~~~~~
	 * $m = wireMail();
	 * $m->to('user@domain.com')->from('hello@world.com');
	 * $m->subject('Test attachment');
	 * $m->body('This is just a test of a file attachment');
	 * $m->attachment('/path/to/file.jpg'); 
	 * $m->send();
	 * ~~~~~~
	 * 
	 * #pw-advanced
	 *
	 * - Multiple calls will append attachments. 
	 * - To remove the supplied attachments, specify NULL as the value. 
	 * - Attachments may or may not be supported by 3rd party WireMail modules. 
	 *
	 * @param string $value Full path and filename of file attachment
	 * @param string $filename Optional different basename for file as it appears in the mail
	 * @return $this 
	 *
	 */
	public function attachment($value, $filename = '') {
		if(is_null($value)) {
			$this->mail['attachments'] = array();
		} else if(is_file($value)) { 
			$filename = $filename ? basename($filename) : basename($value);
			$this->mail['attachments'][$filename] = $value; 
		}
		return $this; 
	}

	/**
	 * Get the multipart boundary string for this email
	 * 
	 * @param string|bool $prefix Specify optional boundary prefix or boolean true to clear any existing stored boundary
	 * @return string
	 * 
	 */
	protected function multipartBoundary($prefix = '') {
		$boundary = parent::get('_multipartBoundary');
		if(empty($boundary) || $prefix === true) {
			$boundary = "==Multipart_Boundary_x" . md5(time()) . "x";
			parent::set('_multipartBoundary', $boundary);
		}
		if(is_string($prefix) && !empty($prefix)) {
			$boundary = str_replace("_Boundary_x", "_Boundary_{$prefix}_x", $boundary);
		}
		return $boundary;
	}
	
	/**
	 * Send the email
	 *
	 * Call this method only after you have specified at least the `subject`, `to` and `body`.
	 *
	 * #pw-notes This is the primary method that modules extending this class would want to replace.
	 *
	 * @return int Returns a positive number (indicating number of addresses emailed) or 0 on failure.
	 *
	 */
	public function ___send() {

		// prep header and body
		$this->multipartBoundary(true);
		$header = $this->renderMailHeader();
		$body = $this->renderMailBody();

		// adjust for the cases where people want to change RFC standard \r\n to just \n
		$newline = parent::get('newline');
		if(is_string($newline) && strlen($newline) && $newline !== "\r\n") {
			$body = str_replace("\r\n", $newline, $body);
			$header = str_replace("\r\n", $newline, $header);
		}
		
		// prep any additional PHP mail params
		$param = $this->wire()->config->phpMailAdditionalParameters;
		if(is_null($param)) $param = '';
		foreach($this->param as $value) {
			$param .= " $value";
		}

		// send email(s)
		$numSent = 0;
		$subject = $this->encodeSubject($this->subject);
		
		foreach($this->to as $to) {
			$toName = isset($this->mail['toName'][$to]) ? $this->mail['toName'][$to] : '';
			if($toName) $to = $this->bundleEmailAndName($to, $toName); // bundle to "User Name <user@example.com>"
			if($param) {
				if(@mail($to, $subject, $body, $header, $param)) $numSent++;
			} else {
				if(@mail($to, $subject, $body, $header)) $numSent++;
			}
		}

		return $numSent;
	}

	/**
	 * Render email header string
	 * 
	 * @return string
	 * 
	 */
	protected function renderMailHeader() {
		
		$config = $this->wire()->config;
		$settings = $config->wireMail;
		$from = $this->from;
		$fromName = $this->fromName;
		
		if(!strlen($from) && !empty($settings['from'])) $from = $settings['from'];
		if(!strlen($from)) $from = $config->adminEmail;
		if(!strlen($from)) $from = 'processwire@' . $config->httpHost;
		if(!strlen($fromName) && !empty($settings['fromName'])) $fromName = $settings['fromName'];
		
		$header = "From: " . ($fromName ? $this->bundleEmailAndName($from, $fromName) : $from);

		foreach($this->header as $key => $value) {
			$header .= "\r\n$key: $value";
		}
		
		$boundary = $this->multipartBoundary();
		$header = trim($this->strReplace($header, $boundary)); 
		
		if($this->bodyHTML || count($this->attachments)) {
			$contentType = count($this->attachments) ? 'multipart/mixed' : 'multipart/alternative';
			$header .= 
				"\r\nMIME-Version: 1.0" . 
				"\r\nContent-Type: $contentType;\r\n  boundary=\"$boundary\"";
		} else {
			$header .= 
				"\r\nContent-Type: text/plain; charset=UTF-8" .
				"\r\nContent-Transfer-Encoding: quoted-printable"; 
		}
		
		return $header;
	}

	/**
	 * Render mail body 
	 * 
	 * @return string
	 * 
	 */
	protected function renderMailBody() {
		
		$boundary = $this->multipartBoundary(); 
		$subboundary = $this->multipartBoundary('alt');
	
		// don’t allow boundary to appear in visible portions of email
		$text = $this->strReplace($this->body, array($boundary, $subboundary)); 
		$html = $this->strReplace($this->bodyHTML, array($boundary, $subboundary));

		// if plain text only, return now
		if(empty($html) && !count($this->attachments)) return quoted_printable_encode($text);

		// if only HTML provided, generate text version from HTML
		if(!strlen($text) && strlen($html)) $text = $this->htmlToText($html);

		$body = 
			"This is a multi-part message in MIME format.\r\n\r\n" .
			"--$boundary\r\n";

		// Plain Text
		$textbody = 
			"Content-Type: text/plain; charset=\"utf-8\"\r\n" .
			"Content-Transfer-Encoding: quoted-printable\r\n\r\n" .
			quoted_printable_encode($text) . "\r\n\r\n";

		if($this->bodyHTML) {
			// HTML
			$htmlbody = 
				"Content-Type: text/html; charset=\"utf-8\"\r\n" .
				"Content-Transfer-Encoding: quoted-printable\r\n\r\n" .
				quoted_printable_encode($html) . "\r\n\r\n";

			if(count($this->attachments)) {
				// file attachments
				$textbody = $this->strReplace($textbody, $subboundary);
				$htmlbody = $this->strReplace($htmlbody, $subboundary);

				$body .= 
					"Content-Type: multipart/alternative;\r\n	boundary=\"$subboundary\"\r\n\r\n" .
					"--$subboundary\r\n" .
					$textbody .
					"--$subboundary\r\n" .
					$htmlbody .
					"--$subboundary--\r\n\r\n";
				
			} else {
				// no file attachments
				$body .= 
					$textbody .
					"--$boundary\r\n" .
					$htmlbody;
			}
			
		} else {
			// plain text
			$body .= $textbody;
		}

		if(count($this->attachments)) {
			$body .= $this->renderMailAttachments(); 
		}

		$body .= "--$boundary--\r\n";

		return $body;
	}	
	
	/**
	 * Render mail attachments string for placement in body
	 * 
	 * @return string
	 * 
	 */
	protected function renderMailAttachments() {
		$body = '';
		$boundary = $this->multipartBoundary();
		$sanitizer = $this->wire()->sanitizer;
		
		foreach($this->attachments as $filename => $file) {
			
			$filename = $sanitizer->text($filename, array(
				'maxLength' => 512,
				'truncateTail' => false, 
				'stripSpace' => '-',
				'stripQuotes' => true
			));
			
			if(stripos($filename, $boundary) !== false) continue;
			
			$content = file_get_contents($file);
			$content = chunk_split(base64_encode($content));
	
			if(stripos($content, $boundary) !== false) continue;
			
			$body .=
				"--$boundary\r\n" .
				"Content-Type: application/octet-stream; name=\"$filename\"\r\n" .
				"Content-Transfer-Encoding: base64\r\n" .
				"Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n" .
				"$content\r\n\r\n";
		}
		
		return $body;
	}

	/**
	 * Recursive string replacement
	 * 
	 * This is better than using str_replace() because it handles cases where replacement
	 * results in the construction of a new $find that was not present in original $str.
	 * Note: this function ignores case. 
	 * 
	 * @param string $str
	 * @param string|array $find
	 * @param string $replace 
	 * @return string
	 * 
	 */
	protected function strReplace($str, $find, $replace = '') {
		if(!is_array($find)) $find = array($find);
		if(!is_string($str)) $str = (string) $str;
		foreach($find as $findStr) {
			if(is_array($findStr)) continue;
			while(stripos($str, $findStr) !== false) {
				$str = str_ireplace($findStr, $replace, $str);
			}
		}
		return $str;
	}

	/**
	 * Convert HTML mail body to TEXT mail body
	 * 
	 * @param string $html
	 * @return string
	 * 
	 */
	protected function ___htmlToText($html) {
		$text = $this->wire()->sanitizer->getTextTools()->markupToText($html);
		$text = str_replace("\n", "\r\n", $text); 
		$text = $this->strReplace($text, $this->multipartBoundary()); 
		return $text;
	}
	
	/**
	 * Encode a subject, use mbstring if available
	 * 
	 * #pw-advanced
	 *
	 * @param string $subject
	 * @return string
	 *
	 */
	public function encodeSubject($subject) {
		
		$boundary = $this->multipartBoundary();
		$subject = $this->strReplace($subject, $boundary);
		
		if(extension_loaded("mbstring")) {
			// Need to pass in the header name and subtract it afterwards,
			// otherwise the first line would grow too long
			return substr(mb_encode_mimeheader("Subject: $subject", 'UTF-8', 'Q', "\r\n"), 9);
		}

		$out = array();
		$isFirst = true;
		$n = 0;

		while(strlen($subject) > 0 && ++$n < 50) {
			$part = $this->findBestEncodePart($subject, 63, $isFirst);
			$out[] = $this->quotedPrintableString($part);
			$subject = substr($subject, strlen($part));
			$isFirst = false;
		}
		
		return implode("\r\n ", $out);
	}
	
	/**
	 * Tries to split the passed subject at a whitespace at or before $maxlen,
	 * falling back to a hard substr if none was found, and returns the
	 * left part.
	 *
	 * Makes sure that the quoted-printable encoded part is inside the 76 characters
	 * header limit (66 for first line that has the header name, minus a buffer
	 * of 2 characters for whitespace) given in rfc2047.
	 *
	 * @param string $input The subject to encode
	 * @param int $maxlen Maximum length of unencoded string, defaults to 63
	 * @param bool $isFirst Set to true for first line to account for the header name
	 * @return string
	 *
	 */
	protected function findBestEncodePart($input, $maxlen = 63, $isFirst = false) {
		$maxEffLen = $maxlen - ($isFirst ? 10 : 0);

		if(strlen($input) <= $maxEffLen) {
			$part = $input;
		} else if(strpos($input, " ") === FALSE || strrpos($input, " ") === FALSE || strpos($input, " ") > $maxEffLen) {
			// Force cutting of subject since there is no whitespace to break on
			$part = substr($input, $maxlen - $maxEffLen);
		} else {
			$searchstring = substr($input, 0, $maxEffLen);
			$lastpos = strrpos($searchstring, " ");
			$part = substr($input, 0, $lastpos);
		}

		if(strlen($this->quotedPrintableString($part)) > 74 - ($isFirst ? 10 : 0)) {
			return $this->findBestEncodePart($input, $maxlen - 1, $isFirst);
		}

		return $part;
	}
	
	/**
	 * Return the text quoted-printable encoded
	 *
	 * Uses short notation for charset and encoding suitable for email headers
	 * as laid out in rfc2047.
	 * 
	 * #pw-advanced
	 *
	 * @param string $text
	 * @return string
	 *
	 */
	public function quotedPrintableString($text) {
		return '=?utf-8?Q?' . quoted_printable_encode($text) . '?=';
	}

}
