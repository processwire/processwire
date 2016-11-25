<?php namespace ProcessWire;

/**
 * ProcessWire WireMail
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary A module type that handles sending of email in ProcessWire
 * #pw-var $m
 * #pw-body = 
 *
 * Below are 3 different ways you can get a new instance of WireMail. 
 * When possible we recommend using option A or B below.  
 * ~~~~~
 * $m = $mail->new(); // option A
 * $m = wireMail(); // option B
 * $m = new WireMail(); // option C
 * ~~~~~
 * Once you have an instance of WireMail (`$m`), you can use it to send email like in these examples below. 
 * ~~~~~
 * // chained (fluent) method call usage
 * $m->to('user@domain.com')
 *   ->from('you@company.com')
 *   ->subject('Message Subject')
 *   ->body('Message Body')
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
 * $m->bodyHTML('<html><body><h1>Message Body</h1></body></html>'); 
 * $m->attachment('/path/to/file.ext'); 
 * $m->fromName('Mary Jane');
 * $m->toName('John Smith');
 * $m->header('X-Mailer', 'ProcessWire'); 
 * $m->param('-f you@company.com'); // PHP mail() param (envelope from example)
 *
 * // note that the send() function always returns the quantity of messages sent
 * $numSent = $m->send();
 * ~~~~~
 * #pw-body
 * 
 * @method int send()
 * @property array $to
 * @property array $toName
 * @property string $from
 * @property string $fromName
 * @property string $subject
 * @property string $body
 * @property string $bodyHTML
 * @property array $header
 * @property array $param
 * @property array $attachments Array of file attachments (if populated) #pw-advanced
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
		'subject' => '', 
		'body' => '',
		'bodyHTML' => '',
		'header' => array(),
		'param' => array(), 
		'attachments' => array(), 
		);

	public function __construct() {
		$this->mail['header']['X-Mailer'] = "ProcessWire/" . $this->className();
	}

	public function __get($key) {
		if(array_key_exists($key, $this->mail)) return $this->mail[$key]; 
		return parent::__get($key); 
	}

	public function __set($key, $value) {
		if(array_key_exists($key, $this->mail)) $this->$key($value); // function call
			else parent::__set($key, $value); 
	}

	protected function sanitizeEmail($email) {
		$email = strtolower(trim($email)); 
		$clean = $this->wire('sanitizer')->email($email); 
		if($email != $clean) {
			$clean = $this->wire('sanitizer')->entities($email); 
			throw new WireException("Invalid email address ($clean)");
		}
		return $clean;
	}

	protected function sanitizeHeader($header) {
		return $this->wire('sanitizer')->emailHeader($header); 
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
		if(strpos($email, '<') !== false && strpos($email, '>') !== false) {
			// email has separate from name and email
			if(preg_match('/^(.*?)<([^>]+)>.*$/', $email, $matches)) {
				$name = $this->sanitizeHeader($matches[1]);
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
		$name = $this->sanitizeHeader($name); 
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
	 * @throws WireException if any provided emails were invalid
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
			$this->mail['to'][$toEmail] = $toEmail;
			$this->mail['toName'][$toEmail] = $this->sanitizeHeader($toName); 
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
	 * @param string The 'to' name
	 * @return $this 
	 * @throws WireException if you attempt to set a toName before a to email. 
	 *
	 */
	public function toName($name) {
		$emails = $this->mail['to']; 
		if(!count($emails)) throw new WireException("Please set a 'to' address before setting a name."); 
		$email = end($emails); 
		$this->mail['toName'][$email] = $this->sanitizeHeader($name); 
		return $this;
	}

	/**
	 * Set the email from address
	 *
	 * @param string $email Must be a single email address or "User Name <user@example.com>" string.
	 * @param string|null An optional FROM name (same as setting/calling fromName)
	 * @return $this 
	 * @throws WireException if provided email was invalid
	 *
	 */
	public function from($email, $name = null) {
		if(is_null($name)) list($email, $name) = $this->extractEmailAndName($email); 
		if($name) $this->mail['fromName'] = $this->sanitizeHeader($name); 
		$this->mail['from'] = $email;
		return $this; 
	}

	/**
	 * Set the 'from' name
	 *
	 * It is preferable to do this with the from() method, but this is provided to ensure that 
	 * all properties can be set with direct access, i.e. $mailer->fromName = 'User Name';
	 *
	 * @param string The 'from' name
	 * @return $this 
	 *
	 */
	public function fromName($name) {
		$this->mail['fromName'] = $this->sanitizeHeader($name); 
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
		$this->mail['subject'] = $this->sanitizeHeader($subject); 	
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
	 * @param string $key Header name
	 * @param string $value Header value
	 * @return $this 
	 *
	 */
	public function header($key, $value) {
		if(is_null($value)) {
			unset($this->mail['header'][$key]); 
		} else { 
			$k = $this->wire('sanitizer')->name($this->sanitizeHeader($key)); 
			// ensure consistent capitalization for all header keys
			$k = ucwords(str_replace('-', ' ', $k)); 
			$k = str_replace(' ', '-', $k); 
			$v = $this->sanitizeHeader($value); 
			$this->mail['header'][$k] = $v; 
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
			$filename = $filename ?: basename($value);
			$this->mail['attachments'][$filename] = $value; 
		}
		return $this; 
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

		$from = $this->from;
		if(!strlen($from)) $from = $this->wire('config')->adminEmail;
		if(!strlen($from)) $from = 'processwire@' . $this->wire('config')->httpHost; 

		$header = "From: " . ($this->fromName ? $this->bundleEmailAndName($from, $this->fromName) : $from);

		foreach($this->header as $key => $value) $header .= "\r\n$key: $value";

		$param = $this->wire('config')->phpMailAdditionalParameters;
		if(is_null($param)) $param = '';
		foreach($this->param as $value) $param .= " $value";		

		$header = trim($header); 
		$param = trim($param); 
		$text = $this->body; 
		$html = $this->bodyHTML;

		if($this->bodyHTML || count($this->attachments)) {
			if(!strlen($text)) $text = strip_tags($html); 
			$contentType = count($this->attachments) ? 'multipart/mixed' : 'multipart/alternative';
			$boundary = "==Multipart_Boundary_x" . md5(time()) . "x";
			$header .= "\r\nMIME-Version: 1.0";
			$header .= "\r\nContent-Type: $contentType;\r\n  boundary=\"$boundary\"";

			// Plain Text
			$body = "This is a multi-part message in MIME format.\r\n\r\n" . 
				"--$boundary\r\n";
				
			$textbody = "Content-Type: text/plain; charset=\"utf-8\"\r\n" . 
				"Content-Transfer-Encoding: quoted-printable\r\n\r\n" . 
				quoted_printable_encode($text) . "\r\n\r\n";

			// HTML
			if($this->bodyHTML){
				$htmlbody = "Content-Type: text/html; charset=\"utf-8\"\r\n" . 
					"Content-Transfer-Encoding: quoted-printable\r\n\r\n" . 
					quoted_printable_encode($html) . "\r\n\r\n";
				
				if(count($this->attachments)) {
					$subboundary = "==Multipart_Boundary_alt_x" . md5(time()) . "x";
					
					$body .= "Content-Type: multipart/alternative;\r\n	boundary=\"$subboundary\"\r\n\r\n" .
						"--$subboundary\r\n" .
						$textbody .
						"--$subboundary\r\n" .
						$htmlbody .
						"--$subboundary--\r\n\r\n";
				} else {
					$body .= $textbody .
						"--$boundary\r\n" .
						$htmlbody;
				}
			} else {
				$body .= $textbody;
			}

			// Attachments
			foreach($this->attachments as $filename => $file) {
				$content = file_get_contents($file);
				$content = chunk_split(base64_encode($content));

				$body .= "--$boundary\r\n" .
					"Content-Type: application/octet-stream; name=\"$filename\"\r\n" . 
					"Content-Transfer-Encoding: base64\r\n" . 
					"Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n" .
					"$content\r\n\r\n";
			}

			$body .= "--$boundary--\r\n";

		} else {
			$header .= "\r\nContent-Type: text/plain; charset=UTF-8\r\n" .
				"Content-Transfer-Encoding: quoted-printable"; 
			$body = quoted_printable_encode($text); 
		}

		$numSent = 0;
		foreach($this->to as $to) {
			$toName = $this->mail['toName'][$to]; 
			if($toName) $to = $this->bundleEmailAndName($to, $toName); // bundle to "User Name <user@example.com"
			$subject = $this->encodeSubject($this->subject);
			if($param) {
				if(@mail($to, $subject, $body, $header, $param)) $numSent++;
			} else {
				if(@mail($to, $subject, $body, $header)) $numSent++;
			}
		}

		return $numSent; 
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
