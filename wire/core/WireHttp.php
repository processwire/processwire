<?php namespace ProcessWire;

/**
 * ProcessWire HTTP tools
 *
 * Provides capability for sending POST/GET requests to URLs
 * 
 * #pw-summary WireHttp enables you to send HTTP requests to URLs, download files, and more.
 * #pw-var $http
 * #pw-instantiate $http = new WireHttp();
 * #pw-body = 
 * ~~~~~
 * // Get the contents of a URL
 * $response = $http->get("http://domain.com/path/");
 * if($response !== false) {
 *   echo "Successful response: " . $sanitizer->entities($response);
 * } else {
 *   echo "HTTP request failed: " . $http->getError();
 * }
 * ~~~~~
 * #pw-body
 * 
 * Thanks to @horst for his assistance with several methods in this class.
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 * @method bool|string send($url, $data = array(), $method = 'POST', array $options = array())
 * @method int sendFile($filename, array $options = array(), array $headers = array())
 * @method string download($fromURL, $toFile, array $options = array())
 * 
 *
 */

class WireHttp extends Wire {
	
	const debug = false;

	/**
	 * Default timeout seconds for send() methods: GET, POST, etc.
	 * 
	 * #pw-internal
	 * 
	 */
	const defaultTimeout = 4.5;

	/**
	 * Default timeout seconds for download() methods. 
	 * 
	 * #pw-internal
	 *
	 */
	const defaultDownloadTimeout = 50; 

	/**
	 * Default value for request $headers, when reset
	 *
	 */
	protected $defaultHeaders = array(
		'charset' => 'utf-8',
	);

	/**
	 * Schemes we are allowed to use
	 *
	 */
	protected $allowSchemes = array('http', 'https');

	/**
	 * HTTP methods we are allowed to use
	 *
	 */
	protected $allowHttpMethods = array('GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH'); 

	/**
	 * Headers to include in the request
	 *
	 */
	protected $headers = array();

	/**
	 * HTTP codes
	 * 
	 * @var array
	 * 
	 */
	protected $httpCodes = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing (WebDAV; RFC 2518)', 
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status (WebDAV; RFC 4918)',
		208 => 'Already Reported (WebDAV; RFC 5842)',
		226 => 'IM Used (RFC 3229)',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Switch Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		419 => 'Authentication Timeout (not in RFC 2616)',
		420 => 'Enhance Your Calm ',
		422 => 'Unprocessable Entity (WebDAV; RFC 4918)',
		423 => 'Locked (WebDAV; RFC 4918)',
		424 => 'Failed Dependency (WebDAV; RFC 4918)',
		426 => 'Upgrade Required',
		428 => 'Precondition Required (RFC 6585)',
		429 => 'Too Many Requests (RFC 6585)',
		431 => 'Request Header Fields Too Large (RFC 6585)',
		440 => 'Login Timeout (Microsoft)',
		444 => 'No Response (Nginx)',
		449 => 'Retry With (Microsoft)',
		450 => 'Blocked by Windows Parental Controls (Microsoft)',
		451 => 'Unavailable For Legal Reasons (Internet draft)',
		494 => 'Request Header Too Large (Nginx)',
		495 => 'Cert Error (Nginx)',
		496 => 'No Cert (Nginx)',
		497 => 'HTTP to HTTPS (Nginx)',
		498 => 'Token expired/invalid (Esri)',
		499 => 'Client Closed Request (Nginx)',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates (RFC 2295)',
		507 => 'Insufficient Storage (WebDAV; RFC 4918)',
		508 => 'Loop Detected (WebDAV; RFC 5842)',
		509 => 'Bandwidth Limit Exceeded (Apache bw/limited extension)[25]',
		510 => 'Not Extended (RFC 2774)',
		511 => 'Network Authentication Required (RFC 6585)',
		520 => 'Origin Error (Cloudflare)',
		521 => 'Web server is down (Cloudflare)',
		522 => 'Connection timed out (Cloudflare)',
		523 => 'Proxy Declined Request (Cloudflare)',
		524 => 'A timeout occurred (Cloudflare)',
		598 => 'Network read timeout error (Unknown)',
		599 => 'Network connect timeout error (Unknown)',
	);

	/**
	 * Seconds till timing out on a connection
	 * 
	 * @var float|null Contains a float value when set, or a NULL when not set (indicating default should be used)
	 * 
	 */
	protected $timeout = null;

	/**
	 * Last HTTP code
	 * 
	 * @var int
	 * 
	 */
	protected $httpCode = 0;
	
	/**
	 * Last HTTP code text
	 *
	 * @var int
	 *
	 */
	protected $httpCodeText = '';

	/**
	 * Data to send in the request
	 *
	 */
	protected $data = array();

	/**
	 * Raw data, when data is not an array
	 *
	 */
	protected $rawData = null;

	/**
	 * Last response header
	 *
	 */
	protected $responseHeader = array();

	/**
	 * Last response headers parsed into key => value properties
	 * 
	 * Note that keys are always lowercase
	 *
	 */
	protected $responseHeaders = array();
	
	/**
	 * Last response headers parsed into key => value properties, where value is always array
	 *
	 * Note that keys are always lowercase
	 *
	 */
	protected $responseHeaderArrays = array();
	
	/**
	 * Error messages
	 *
	 */
	protected $error = array();

	/**
	 * Whether the system supports CURL
	 * 
	 * @var bool
	 * 
	 */
	protected $hasCURL = false;
	
	/**
	 * Whether the system supports fopen of URLs 
	 *
	 * @var bool
	 *
	 */
	protected $hasFopen = false;

	/**
	 * Last type used for send (fopen, socket, curl)
	 * 
	 * @var string
	 * 
	 */
	protected $lastSendType = '';

	/**
	 * Options to pass to $sanitizer->url('url', $options) in WireHttp::validateURL() method
	 * 
	 * Can be modified with the setValidateURLOptions() method.
	 * 
	 * @var array
	 * 
	 */
	protected $validateURLOptions = array(
		'allowRelative' => false,
		'requireScheme' => true,
		'stripQuotes' => false,
		'encodeSpace' => true,
		'throw' => true,
	);

	/**
	 * Construct/initialize
	 * 
	 */
	public function __construct() {
		$this->hasCURL = function_exists('curl_init') && !ini_get('safe_mode') && !ini_get('open_basedir');
		$this->hasFopen = ini_get('allow_url_fopen');
		$this->resetRequest();
		$this->resetResponse();
	}

	/**
	 * Send a POST request to a URL
	 * 
	 * ~~~~~
	 * $http = new WireHttp();
	 * $response = $http->post("http://domain.com/path/", [
	 *   'foo' => 'bar',
	 * ]); 
	 * if($response !== false) {
	 *   echo "Successful response: " . $sanitizer->entities($response); 
	 * } else {
	 *   echo "HTTP request failed: " . $http->getError();
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to post to (including http:// or https://)
	 * @param mixed $data Associative array of data to send (if not already set before), or raw data to send.
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|string False on failure or string of contents received on success.
	 * @see WireHttp::send(), WireHttp::get(), WireHttp::head()
	 *
	 */
	public function post($url, $data = array(), array $options = array()) {
		if(!isset($this->headers['content-type'])) $this->setHeader('content-type', 'application/x-www-form-urlencoded; charset=utf-8');
		return $this->send($url, $data, 'POST', $options);
	}

	/**
	 * Send a GET request to URL
	 * 
	 * ~~~~~
	 * $http = new WireHttp();
	 * $response = $http->get("http://domain.com/path/", [
	 *   'foo' => 'bar',
	 * ]);
	 * if($response !== false) {
	 *   echo "Successful response: " . $sanitizer->entities($response);
	 * } else {
	 *   echo "HTTP request failed: " . $http->getError();
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to send request to (including http:// or https://)
	 * @param mixed $data Array of data to send (if not already set before) or raw data to send.
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|string False on failure or string of contents received on success.
	 * @see WireHttp::send(), WireHttp::post(), WireHttp::head(), WireHttp::getJSON()
	 *
	 */
	public function get($url, $data = array(), array $options = array()) {
		return $this->send($url, $data, 'GET', $options);
	}

	/**
	 * Send to a URL that responds with JSON (using GET request) and return the resulting array or object.
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to send request to (including http:// or https://)
	 * @param bool $assoc Default is to return an array (specified by TRUE). If you want an object instead, specify FALSE. 
	 * @param mixed $data Array of data to send (if not already set before) or raw data to send
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|array|object False on failure or an array or object on success.
	 * @see WireHttp::send(), WireHttp::get()
	 *
	 */
	public function getJSON($url, $assoc = true, $data = array(), array $options = array()) {
		return json_decode($this->get($url, $data, $options), $assoc); 
	}

	/**
	 * Send to a URL using a HEAD request
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to request (including http:// or https://)
	 * @param mixed $data Array of data to send (if not already set before) or raw data to send
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|array False on failure or Arrray with ResponseHeaders on success.
	 * @see WireHttp::send(), WireHttp::post(), WireHttp::get()
	 *
	 */
	public function head($url, $data = array(), array $options = array()) {
		$this->send($url, $data, 'HEAD', $options);
		$responseHeaders = $this->getResponseHeaders();
		return is_array($responseHeaders) ? $responseHeaders : false;
	}

	/**
	 * Send to a URL using a HEAD request and return the status code
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to request (including http:// or https://)
	 * @param mixed $data Array of data to send (if not already set before) or raw data
	 * @param bool $textMode When true function will return a string rather than integer, see the statusText() method.
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|integer|string False on failure or integer or string of status code (200|404|etc) on success.
	 * @see WireHttp::send(), WireHttp::statusText()
	 *
	 */
	 public function status($url, $data = array(), $textMode = false, array $options = array()) {
		$this->send($url, $data, 'HEAD', $options);
		return $this->getHttpCode($textMode); 
	}

	/**
	 * Send to a URL using HEAD and return the status code and text like "200 OK"
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to request (including http:// or https://)
	 * @param mixed $data Array of data to send (if not already set before) or raw data
	 * @param array $options Optional options to modify default behavior, see the send() method for details. 
	 * @return bool|string False on failure or string of status code + text on success.
	 *	Example: "200 OK', "302 Found", "404 Not Found"
	 * @see WireHttp::send(), WireHttp::status()
	 *
	 */
	public function statusText($url, $data = array(), array $options = array()) {
		return $this->status($url, $data, true, $options); 
	}

	/**
	 * Send the given $data array to a URL using given method (i.e. POST, GET, PUT, DELETE, etc.)
	 * 
	 * This method handles the implementation for the get/post/head/etc. methods. It is preferable to use one 
	 * of those dedicated request methods rather than this one.
	 * 
	 * #pw-group-HTTP-requests
	 *
	 * @param string $url URL to send to (including http:// or https://).
	 * @param array $data Array of data to send (if not already set before).
	 * @param string $method Method to use (either POST, GET, PUT, DELETE or others as needed).
	 * @param array|string $options Options to modify behavior (this argument added in 3.0.124): 
	 *  - `use` (string): What handler to use, one of 'auto', 'fopen', 'curl' or 'socket' (default='auto')
	 *    If the 'auto' option is used, the method will first try fopen and then fallback to curl and sockets unless 'fallback' is disabled.
	 *  - `fallback` (bool|string): Allow fallback to other methods? Applies only if 'use' option is 'auto'. (default=true)
	 *    For a specific fallback method specify 'socket' or 'curl'
	 * @return bool|string False on failure or string of contents received on success.
	 *
	 */
	public function ___send($url, $data = array(), $method = 'POST', array $options = array()) {
		
		$defaults = array(
			'use' => 'auto',
			'fallback' => 'auto', // false, 'auto', 'socket' or 'curl'
			'proxy' => '', 
			'_url' => $url, // original unmodified URL
		);

		$options = array_merge($defaults, $options);
		$url = $this->validateURL($url, false);
		$allowFopen = $this->hasFopen;
		$allowCURL = $this->hasCURL && (version_compare(PHP_VERSION, '5.5') >= 0 || $options['use'] === 'curl'); // #849
		$result = false;
		
		if(empty($url)) return false;
		$this->resetResponse();
		
		if(!empty($data)) $this->setData($data);
		if(!isset($this->headers['user-agent'])) $this->setHeader('user-agent', $this->getUserAgent());
		if(!in_array(strtoupper($method), $this->allowHttpMethods)) $method = 'POST';
		if($allowFopen && strpos($url, 'https://') === 0 && !extension_loaded('openssl')) $allowFopen = false;

		if($options['use'] === 'socket') {
			// force socket
			return $this->sendSocket($url, $method); 
			
		} else if($options['use'] === 'curl') {
			// force curl
			if(!$this->hasCURL) {
				$this->error[] = 'CURL is not available';
				return false;
			} else if(!$allowCURL) {
				$this->error[] = 'Using CURL requires PHP 5.5+'; 
				return false;
			} else {
				return $this->sendCURL($url, $method, $options);
			}
			
		} else if($options['use'] === 'fopen' && !$allowFopen) {
			$this->error[] = 'fopen is not available';
			return false;
		}

		if($allowFopen) {
			$result = $this->sendFopen($url, $method, $options);
		} else if($options['fallback'] === false) {
			$this->error[] = 'fopen not available and fallback option is disabled';
		}
		
		if($result === false && $options['fallback'] !== false) {
			// on fopen fail fallback to CURL then sockets
			if($allowCURL && $options['fallback'] !== 'socket') {
				$result = $this->sendCURL($url, $method, $options);
			}
			if($result === false && $options['fallback'] !== 'curl') {
				$result = $this->sendSocket($options['_url'], $method);
			}
		}

		return $result;
	}

	/**
	 * Send using fopen
	 * 
	 * @param string $url
	 * @param string $method
	 * @param array $options Options specific to fopen should be specified in [ 'fopen' => [ ... ] ]
	 *
	 * @return bool|string
	 * 
	 */
	protected function sendFopen($url, $method = 'POST', array $options = array()) {
		
		$this->resetResponse();
		$this->lastSendType = 'fopen';
		
		if(!empty($this->data)) {
			$content = http_build_query($this->data);
			if(($method === 'GET' || $method === 'HEAD') && strlen($content)) {
				$url .= (strpos($url, '?') === false ? '?' : '&') . $content;
				$content = '';
			}
		} else if(!empty($this->rawData)) {
			$content = $this->rawData;
		} else {
			$content = '';
		}

		$this->setHeader('content-length', strlen($content));

		$header = '';
		foreach($this->headers as $key => $value) {
			$header .= "$key: $value\r\n";
		}
		
		$header .= "Connection: close\r\n";
	
		$http = array(
			'method' => $method,
			'timeout' => $this->getTimeout(),
			'content' => $content,
			'header' => $header,
		);
		if(!empty($options['proxy'])) $http['proxy'] = $options['proxy'];
	
		// merge fopen http options array if present, as well as any other options specified to fopen stream_context_create
		if(isset($options['fopen']) && !empty($options['fopen']['http'])) {
			// allow adding on to http option
			$http = array_merge($options['fopen']['http'], $http);
		} else if(!empty($options['http']) && is_array($options['http'])) {
			// if http array specified outside fopen index
			$http = array_merge($options['http'], $http);
		}
		$fopenOptions = array('http' => $http); 
		if(isset($options['fopen'])) $fopenOptions = array_merge($options['fopen'], $fopenOptions);

		set_error_handler(array($this, '_errorHandler'));
		$context = stream_context_create($fopenOptions);
		$fp = fopen($url, 'rb', false, $context);
		restore_error_handler();

		if(isset($http_response_header)) $this->setResponseHeader($http_response_header);

		if($fp) {
			$result = @stream_get_contents($fp);

		} else {
			$code = $this->getHttpCode();
			if($code && $code >= 400 && isset($this->httpCodes[$code])) {
				// known http error code, no need to fallback to sockets
				$result = false;
			} else if($code && $code >= 200 && $code < 300) {
				// PR #1281: known http success status code, no need to fallback to sockets
				$result = true;
			} else {
				$result = false;
			}
		}

		return $result;
	}
	
	/**
	 * Send using CURL
	 *
	 * @param string $url 
	 * @param string $method
	 * @param array $options
	 * @return bool|string
	 *
	 */
	protected function sendCURL($url, $method = 'POST', $options = array()) {

		$this->resetResponse();
		$this->lastSendType = 'curl';
		$timeout = isset($options['timeout']) ? (float) $options['timeout'] : $this->getTimeout();
		$proxy = '';
		
		if(!empty($options['proxy'])) $proxy = $options['proxy'];
			else if(isset($options['curl']) && !empty($options['curl']['http']['proxy'])) $proxy = $options['curl']['http']['proxy'];
			else if(isset($options['http']) && !empty($options['http']['proxy'])) $proxy = $options['http']['proxy'];

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());

		if(version_compare(PHP_VERSION, '5.6') >= 0) {
			// CURLOPT_SAFE_UPLOAD value is default true (setopt not necessary)
			// and PHP 7+ removes this option
		} else if(version_compare(PHP_VERSION, '5.5') >= 0) {
			curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
		} else {
			// not reachable: version blocked before sendCURL call
		}
		
		if(count($this->headers)) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		}
		
		if($method == 'POST') {
			curl_setopt($curl, CURLOPT_POST, true);
		} else if($method == 'PUT') {
			curl_setopt($curl, CURLOPT_PUT, true);
		} else if($method == 'HEAD') {
			curl_setopt($curl, CURLOPT_NOBODY, true); 
		} else {
			curl_setopt($curl, CURLOPT_HTTPGET, true);
		}
	
		if($proxy) curl_setopt($curl, CURLOPT_PROXY, $proxy);
		
		if(!empty($this->data)) {
			if($method === 'POST') {
				curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
			} else {
				$content = http_build_query($this->data);
				if(strlen($content)) $url .= (strpos($url, '?') === false ? '?' : '&') . $content;
			}
		} else if(!empty($this->rawData)) {
			if($method === 'POST') {
				curl_setopt($curl, CURLOPT_POSTFIELDS, $this->rawData);
			} else {
				throw new WireException("Raw data option with CURL not supported for $method"); 
			}
		}

		// called by CURL for each header and populates the $responseHeaders var
		$responseHeaders = array();
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use(&$responseHeaders) {
			if($curl) { /* ignore */ }
			$length = strlen($header);
			$header = explode(':', $header, 2);
			if(count($header) < 2) return $length; // ignore invalid headers
			$name = strtolower(trim($header[0]));
			$value = trim($header[1]);
			if(!array_key_exists($name, $responseHeaders)) {
				$responseHeaders[$name] = array($value);
			} else {
				$responseHeaders[$name][] = $value;
			}
			return $length;
		});

		curl_setopt($curl, CURLOPT_URL, $url); 
		$result = curl_exec($curl);

		if($result === false) {
			$this->error[] = curl_error($curl);
			$this->httpCode = 0;
		} else {
			$this->setResponseHeaderValues($responseHeaders);
			$this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		}
		
		curl_close($curl);

		return $result;
	}

	/**
	 * Alternate method of sending when allow_url_fopen isn't allowed
	 * 
	 * @param string $url
	 * @param string $method
	 * @param array $options Optional settings: 
	 * 	- timeout: number of seconds to timeout
	 * @return bool|string
	 *
	 */
	protected function sendSocket($url, $method = 'POST', $options = array()) {
		
		static $level = 0; // recursion level

		$this->resetResponse();
		$this->lastSendType = 'socket';
		$timeout = isset($options['timeout']) ? (float) $options['timeout'] : $this->getTimeout();
		if(!in_array(strtoupper($method), $this->allowHttpMethods)) $method = 'POST';

		$info = parse_url($url);
		$host = $info['host'];
		$path = empty($info['path']) ? '/' : $info['path'];
		$query = empty($info['query']) ? '' : '?' . $info['query'];

		if($info['scheme'] == 'https') {
			$port = 443; 
			$scheme = 'ssl://';
		} else {
			$port = empty($info['port']) ? 80 : $info['port'];
			$scheme = '';
		}

		if(!empty($this->data)) {
			$content = http_build_query($this->data); 
			if($method === 'GET' && strlen($content)) { 
				$query .= (strpos($query, '?') === false ? '?' : '&') . $content; 
				$content = '';
			}
		} else if(!empty($this->rawData)) {
			$content = $this->rawData; 
		} else {
			$content = '';
		}

		$this->setHeader('content-length', strlen($content));

		$request = "$method $path$query HTTP/1.0\r\nHost: $host\r\n";

		foreach($this->headers as $key => $value) {
			$request .= "$key: $value\r\n";
		}

		$response = '';
		$errno = '';
		$errstr = '';

		set_error_handler(array($this, '_errorHandler'));
		if(false !== ($fs = fsockopen($scheme . $host, $port, $errno, $errstr, $timeout))) {
			fwrite($fs, "$request\r\n$content");
			while(!feof($fs)) {
				// get 1 tcp-ip packet per iteration
				$response .= fgets($fs, 1160); 
			}
			fclose($fs);
		}
		restore_error_handler();
		if(strlen($errstr)) $this->error[] = $errno . ': ' . $errstr; 
	
		// skip past the headers in the response, so that it is consistent with 
		// the results returned by the regular send() method
		$pos = strpos($response, "\r\n\r\n"); 
		$this->setResponseHeader(explode("\r\n", substr($response, 0, $pos))); 
		$response = substr($response, $pos+4); 

		// if response resulted in a redirect, follow it 
		if($this->httpCode == 301 || $this->httpCode == 302) {
			// follow redirects
			$location = $this->getResponseHeader('location'); 
			if(!empty($location) && ++$level <= 5) {
				if(strpos($location, '://') === false && preg_match('{(https?://[^/]+)}i', $url, $matches)) {
					// if location is relative, convert to absolute
					$location = $matches[1] . '/' . ltrim($location, '/'); 
				}
				return $this->sendSocket($location, $method); 	
			}
		}

		return $response;

	}

	/**
	 * Download a file from a URL and save it locally
	 * 
	 * First it will attempt to use CURL. If that fails, it will try `fopen()`, 
	 * unless you specify the `use` option in `$options`.
	 * 
	 * #pw-group-files
	 * 
	 * @param string $fromURL URL of file you want to download.
	 * @param string $toFile Filename you want to save it to (including full path).
	 * @param array $options Optional aptions array for PHP's stream_context_create(), plus these optional options: 
	 * 	- `use` or `useMethod` (string): Specify "curl", "fopen" or "socket" to force a specific method (default=auto-detect).
	 * 	- `timeout` (float): Number of seconds till timeout.
	 * @return string Filename that was downloaded (including full path).
	 * @throws WireException All error conditions throw exceptions. 
	 * 
	 */
	public function ___download($fromURL, $toFile, array $options = array()) {

		$fromURL = $this->validateURL($fromURL, true); 
		$http = stripos($fromURL, 'http://') === 0; 
		$https = stripos($fromURL, 'https://') === 0;
		$allowMethods = array('curl', 'fopen', 'socket');
		$triedMethods = array();
		
		if(!$http && !$https) {
			throw new WireException($this->_('Download URLs must begin with http:// or https://'));
		}
	
		if(!isset($options['timeout'])) {
			if(is_null($this->timeout)) {
				$options['timeout'] = self::defaultDownloadTimeout;
			} else {
				$options['timeout'] = $this->timeout; 
			}
		}
	
		// the 'use' option can also be specified as a 'useMethod' option
		if(isset($options['useMethod']) && !isset($options['use'])) {
			$options['use'] = $options['useMethod'];
		}
		
		if(isset($options['use'])) {
			$useMethod = $options['use'];
			unset($options['use']);
			if(!in_array($useMethod, $allowMethods)) throw new WireException("Unrecognized useMethod: $useMethod"); 
			if($useMethod == 'curl' && !$this->hasCURL) throw new WireException("System does not support CURL");
			if($useMethod == 'fopen' && !$this->hasFopen) throw new WireException("System does not support fopen"); 
		} else {
			if($this->hasCURL) $useMethod = 'curl';
				else if($this->hasFopen) $useMethod = 'fopen';
				else $useMethod = 'socket';
		}
		
		if(($fp = fopen($toFile, 'wb')) === false) {
			throw new WireException($this->_('fopen error for filename:') . ' ' . $toFile);
		}

		// CURL
		if($useMethod == 'curl') {
			$triedMethods[] = 'curl';
			$result = $this->downloadCURL($fromURL, $fp, $options);
			if($result === false && !$this->httpCode) {
				$useMethod = $this->hasFopen ? 'fopen' : 'socket'; 
			}	
		}
		
		// FOPEN 
		if($useMethod == 'fopen') {
			$triedMethods[] = 'fopen';
			if($https && !extension_loaded('openssl')) {
				// WireHttp::download-OpenSSL extension required but not available, fallback to socket
				$useMethod = 'socket';
			} else {
				$result = $this->downloadFopen($fromURL, $fp, $options);
				if($result === false && !$this->httpCode) $useMethod = 'socket'; 
			}
		}
	
		// SOCKET
		if($useMethod == 'socket') {
			$triedMethods[] = 'socket';
			$this->downloadSocket($fromURL, $fp, $options); 
		}
		
		fclose($fp); 
			
		$methods = implode(", ", $triedMethods);
		if(count($this->error) || ($this->httpCode >= 400 && isset($this->httpCodes[$this->httpCode]))) {
			$this->wire('files')->unlink($toFile);
			$error = $this->_('File could not be downloaded') . ' ' . htmlentities("($fromURL) ") . $this->getError() . " (tried: $methods)";
			throw new WireException($error); 
		} else {
			$bytes = filesize($toFile); 
			$this->message("Downloaded " . htmlentities($fromURL) . " => $toFile (using: $methods) [$bytes bytes]", Notice::debug); 
		}
	
		$this->wire('files')->chmod($toFile);
		
		return $toFile;
	}

	/**
	 * Download file using CURL 
	 * 
	 * @param string $fromURL
	 * @param resource $fp Open file pointer
	 * @param array $options
	 * @return bool True if successful false if not
	 * 
	 */
	protected function downloadCURL($fromURL, $fp, array $options) {
		
		$this->resetResponse();
		$fromURL = str_replace(' ', '%20', $fromURL);
		$proxy = '';
		
		if(!empty($options['proxy'])) $proxy = $options['proxy'];
			else if(isset($options['curl']) && !empty($options['curl']['http']['proxy'])) $proxy = $options['curl']['http']['proxy'];
			else if(isset($options['http']) && !empty($options['http']['proxy'])) $proxy = $options['http']['proxy'];
		
		$curl = curl_init($fromURL);

		if(isset($options['timeout'])) {
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, (int) $options['timeout']);
			curl_setopt($curl, CURLOPT_TIMEOUT, (int) $options['timeout']);
		}
		curl_setopt($curl, CURLOPT_FILE, $fp); // write curl response to file
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		if($proxy) curl_setopt($curl, CURLOPT_PROXY, $proxy);
		
		$result = curl_exec($curl);
		if($result) $this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if($result === false) $this->error[] = curl_error($curl);
		curl_close($curl);
		
		return $result; 
	}

	/**
	 * Download file using fopen
	 *
	 * @param string $fromURL
	 * @param resource $fp Open file pointer
	 * @param array $options
	 * @return bool True if successful false if not
	 *
	 */
	protected function downloadFopen($fromURL, $fp, array $options) {
		
		$this->resetResponse();

		// Define the options
		$defaultOptions = array(
			'max_redirects' => 3,
		);
		
		$options = array_merge($defaultOptions, $options);
		$context = stream_context_create(
			array(
				'http' => $options
			)
		);

		// download the file
		set_error_handler(array($this, '_errorHandler'));
		$content = file_get_contents($fromURL, false, $context);
		restore_error_handler();

		if(isset($http_response_header)) $this->setResponseHeader($http_response_header);

		if($content === false) {
			$result = false;
		} else {
			$result = true; 
			fwrite($fp, $content);
		}
		
		return $result; 
	}
	
	/**
	 * Download file using sockets
	 *
	 * @param string $fromURL
	 * @param resource $fp Open file pointer
	 * @param array $options
	 * @return bool True if successful false if not
	 *
	 */
	protected function downloadSocket($fromURL, $fp, array $options) {
		$this->resetResponse();
		$this->resetRequest();
	
		// download the file
		$content = $this->sendSocket($fromURL, 'GET', $options);
		fwrite($fp, $content);
		if(empty($content) && !count($this->error)) $this->error[] = 'no data received'; 
		return count($this->error) ? false : true; 
	}
	
	/**
	 * Set an array of request headers to send with GET/POST/etc. request
	 *
	 * Merges with existing headers unless you specify true for the $reset option (since 3.0.131).
	 * If you specify null for any header value, it removes the header (since 3.0.131).
	 * 
	 * #pw-group-request-headers
	 *
	 * @param array $headers Associative array of headers to set
	 * @param array $options Options to modify default behavior (since 3.0.131):
	 *  - `reset` (bool): Reset/clear all existing headers first? (default=false)
	 *  - `replacements` (array): Associative array of [ find => replace ] values to replace in header values (default=[])
	 * @return $this
	 *
	 */
	public function setHeaders(array $headers, array $options = array()) {
		$defaults = array(
			'reset' => false,
			'replacements' => array()
		);
		$options = array_merge($defaults, $options);
		if($options['reset']) $this->headers = array();
		$replacements = count($options['replacements']) ? $options['replacements'] : false;
		foreach($headers as $key => $value) {
			if(is_array($replacements)) $value = str_replace(array_keys($replacements), array_values($replacements), $value);
			$this->setHeader($key, $value);
		}
		return $this;
	}

	/**
	 * Send an individual request header to send with GET/POST/etc. request
	 * 
	 * #pw-group-request-headers
	 *
	 * @param string $key Header name
	 * @param string $value Header value to set (or specify null to remove header, since 3.0.131)
	 * @return $this
	 *
	 */
	public function setHeader($key, $value) {
		$key = strtolower($key);
		if($value === null) {
			unset($this->headers[$key]);
		} else {
			$this->headers[$key] = "$value";
		}
		return $this;
	}

	/**
	 * Get all currently set request headers in an associative array
	 *
	 * Note: To get response headers from a previously sent request, use `WireHttp::getResponseHeaders()` instead.
	 * 
	 * #pw-group-request-headers
	 *
	 * @return array
	 * @since 3.0.131
	 *
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Set an array of data, or string of raw data to send with next GET/POST/etc. request (overwriting the existing data or rawData)
	 * 
	 * #pw-advanced
	 *
	 * @param array|string $data Associative array of data or string of raw data
	 * @return $this
	 *
	 */
	public function setData($data) {
		if(is_array($data)) {
			$this->data = $data;
		} else {
			$this->rawData = $data;
		}
		return $this;
	}

	/**
	 * Set a variable to be included in the next GET/POST/etc. request
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @param string|int $value
	 * @return $this
	 *
	 */
	public function set($key, $value) {
		$this->data[$key] = $value;
		return $this;
	}

	/**
	 * Directly set a variable to be included in the next GET/POST/etc. request
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @param mixed $value
	 *
	 */
	public function __set($key, $value) {
		$this->set($key, $value);
	}

	/**
	 * Directly get a variable to be included in the next GET/POST/etc. request or NULL if not present
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __get($key) {
		return array_key_exists($key, $this->data) ? $this->data[$key] : null;
	}

	/**
	 * Get the last HTTP response headers (normal array).
	 * 
	 * #pw-group-response-headers
	 * 
	 * Useful to examine for errors if your request returned false
	 * However, the `WireHttp::getResponseHeaders()` (plural) method may be better
	 * and this one is kept primarily for backwards compatibility.
	 *
	 * @param string $key Optional header name you want to get
	 * @return array|string|null
	 *
	 */
	public function getResponseHeader($key = '') {
		if(!empty($key)) return $this->getResponseHeaders($key);
		return $this->responseHeader;
	}
	
	/**
	 * Get the last HTTP response headers (associative array)
	 *
	 * All headers are translated to `[key => value]` properties in the array. 
	 * The keys are always lowercase and the values are always strings. If you 
	 * need multi-value headers, use the `WireHttp::getResponseHeaderValues()` method
	 * instead, which returns multi-value headers as arrays. 
	 *
	 * This method always returns an associative array of strings, unless you specify the
	 * `$key` option in which case it will return a string, or NULL if the header is not present.
	 * 
	 * #pw-group-response-headers
	 *
	 * @param string $key Optional header name you want to get (if you only need one)
	 * @return array|string|null
	 * @see WireHttp::getResponseHeaderValues()
	 *
	 */
	public function getResponseHeaders($key = '') {
		if(!empty($key)) {
			$key = strtolower($key);
			return isset($this->responseHeaders[$key]) ? $this->responseHeaders[$key] : null;
		}
		return $this->responseHeaders;
	}

	/**
	 * Get last HTTP response headers with multi-value headers as arrays
	 * 
	 * Use this method when you want to retrieve headers that can potentially contain multiple-values.
	 * Note that any code that iterates these values should be able to handle them being either a string or 
	 * an array. 
	 * 
	 * This method always returns an associative array of strings and arrays, unless you specify the 
	 * `$key` option in which case it can return an array, string, or NULL if the header is not present.
	 * 
	 * #pw-group-response-headers
	 * 
	 * @param string $key Optional header name you want to get (if you only need a specific header)
	 * @param bool $forceArrays If even single-value headers should be arrays, specify true (default=false). 
	 * @return array|string|null
	 * 
	 */
	public function getResponseHeaderValues($key = '', $forceArrays = false) {
		if(!empty($key)) {
			$key = strtolower($key);
			$value = isset($this->responseHeaderArrays[$key]) ? $this->responseHeaderArrays[$key] : null;
			if(!$value !== null && count($value) === 1 && !$forceArrays) $value = reset($value);
		} else if($forceArrays) {
			$value = $this->responseHeaderArrays;
		} else {
			$value = $this->responseHeaders;
			foreach($this->responseHeaderArrays as $k => $v) {
				if(count($v) > 1) $value[$k] = $v;
			}
		}
		return $value;
	}
	
	/**
	 * Set the response header
	 *
	 * @param array
	 *
	 */
	protected function setResponseHeader(array $responseHeader) {
		
		$this->responseHeader = $responseHeader;
		$httpText = '';
		$httpCode = 0;
		
		if(!empty($responseHeader[0])) {
			list(/*HTTP*/, $httpCode) = explode(' ', trim($responseHeader[0]), 2); 
			$httpCode = trim($httpCode);
			if(strpos($httpCode, ' ')) list($httpCode, $httpText) = explode(' ', $httpCode, 2);
			$httpCode = (int) $httpCode;
			if(strlen($httpText)) $httpText = preg_replace('/[^-_.;() a-zA-Z0-9]/', ' ', $httpText); 
		}
		
		$this->httpCode = (int) $httpCode;
		$this->httpCodeText = $httpText; 
		
		if($this->httpCode >= 400 && isset($this->httpCodes[$this->httpCode])) {
			$this->error[] = $this->httpCodes[$this->httpCode];
		}

		// parsed version
		$this->responseHeaders = array();
		$this->responseHeaderArrays = array();
		
		foreach($responseHeader as $header) {
			$pos = strpos($header, ':');
			if($pos !== false) {
				$key = trim(strtolower(substr($header, 0, $pos)));
				$value = trim(substr($header, $pos+1));
			} else {
				$key = $header;
				$value = '';
			}
			if(!isset($this->responseHeaders[$key])) {
				$this->responseHeaders[$key] = $value;
				$this->responseHeaderArrays[$key] = array($value); 
			} else {
				$this->responseHeaderArrays[$key][] = $value;
			}
		}
	
		/*
		if(self::debug && count($responseHeader)) {
			$this->message("httpCode: $this->httpCode, message: $message"); 
			$this->message("<pre>" . print_r($this->getResponseHeader(true), true) . "</pre>", Notice::allowMarkup);
		}
		*/
	}

	/**
	 * Set response headers where they are provided as an associative array and values can be strings or arrays
	 * 
	 * @param array $responseHeader headers in an associative array
	 * 
	 */
	protected function setResponseHeaderValues(array $responseHeader) {
		$this->responseHeaders = array();
		$this->responseHeaderArrays = array();

		foreach($responseHeader as $key => $value) {
			$key = strtolower($key);
			if(!isset($this->responseHeaders[$key])) {
				if(is_array($value)) {
					$valueArray = $value;
					$valueStr = count($value) ? reset($value) : '';
				} else {
					$valueArray = strlen($value) ? array($value) : array();
					$valueStr = $value;
				}
				$this->responseHeaders[$key] = $valueStr;
				$this->responseHeaderArrays[$key] = $valueArray;
			} else {
				if(is_array($value)) {
					foreach($value as $k => $v) {
						$this->responseHeaderArrays[$key][] = $v;
					}
				} else {
					$this->responseHeaderArrays[$key][] = $value;
				}
			}
		}
	}

	/**
	 * Send the contents of the given filename to the current http connection.
	 *
	 * This function utilizes the `$config->fileContentTypes` to match file extension
	 * to content type headers and force-download state.
	 *
	 * This function throws a `WireException` if the file can't be sent for some reason.
	 * 
	 * #pw-group-files
	 *
	 * @param string|bool $filename Filename to send (or boolean false if sending $options[data] rather than file)
	 * @param array $options Options that you may pass in:
	 *   - `exit` (bool): Halt program executation after file send (default=true).
	 *   - `partial` (bool): Allow use of partial downloads via HTTP_RANGE requests? Since 3.0.131 (default=true)
	 *   - `forceDownload` (bool|null): Whether file should force download (default=null, i.e. let content-type header decide).
	 *   - `downloadFilename` (string): Filename you want the download to show on user's computer, or omit to use existing.
	 *   - `headers` (array): The $headers argument to this method can also be provided as an option right here, since 3.0.131 (default=[])
	 *   - `data` (string): String of data to send rather than contents of file, applicable only if $filename argument is false, Since 3.0.132.
	 * @param array $headers Headers that are sent. These are the defaults: 
	 *   - `pragma`: public
	 *   - `expires`: 0
	 *   - `cache-control`: must-revalidate, post-check=0, pre-check=0
	 *   - `content-type`: {content-type} (replaced with actual content type)
	 *   - `content-transfer-encoding`: binary
	 *   - `content-length`: {filesize} (replaced with actual filesize)
	 *   - To remove a header completely, make its value NULL and it won't be sent.
	 * @return int Returns value only if `exit` option is false (value is quantity of bytes sent)
	 * @throws WireException
	 *
	 */
	public function ___sendFile($filename, array $options = array(), array $headers = array()) {

		$defaultOptions = array(
			// boolean: halt program execution after file send
			'exit' => true,
			// allow use of partial downloads with HTTP_RANGE headers?
			'partial' => true, 
			// boolean|null: whether file should force download (null=let content-type header decide)
			'forceDownload' => null,
			// string: filename you want the download to show on the user's computer, or blank to use existing.
			'downloadFilename' => '',
			// optionally specify headers here rather than as 3rd argument
			'headers' => array(), 
			// string of data to send rather than $filename, applicable only if $filename is boolean false
			'data' => null, 
		);

		$defaultHeaders = array(
			"pragma" => "public",
			"expires" =>  "0",
			"cache-control" => "must-revalidate, post-check=0, pre-check=0",
			"content-type" => "{content-type}",
			"content-transfer-encoding" => "binary",
			"content-length" => "{filesize}",
		);

		$options = array_merge($defaultOptions, $options);
		$headers = array_merge($defaultHeaders, $options['headers'], $headers);
		$contentTypes = $this->wire('config')->fileContentTypes;
		
		if($filename === false) {
			// sending data string
			if(empty($options['downloadFilename'])) throw new WireException('The "downloadFilename" option is required'); 
			if($options['data'] === null) throw new WireException('The "data" option is required');
			$info = pathinfo($options['downloadFilename']);
			$ext = strtolower($info['extension']);
			$filesize = strlen($options['data']);
			$options['partial'] = false;
		} else {
			// sending contents of file
			if(!is_file($filename)) throw new WireException("File does not exist");
			$info = pathinfo($filename);
			$ext = strtolower($info['extension']);
			$filesize = filesize($filename);
		}

		$contentType = isset($contentTypes[$ext]) ? $contentTypes[$ext] : $contentTypes['?'];
		$forceDownload = $options['forceDownload'];
		$bytesSent = 0;
		
		if($options['exit']) $this->wire('session')->close();
		if(is_null($forceDownload)) $forceDownload = substr($contentType, 0, 1) === '+';
		if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
		$contentType = ltrim($contentType, '+');
		
		if($forceDownload) {
			$downloadFilename = empty($options['downloadFilename']) ? $info['basename'] : $options['downloadFilename'];
			$headers['content-disposition'] = "attachment; filename=\"$downloadFilename\"";
		}

		$this->setHeaders($headers, array('replacements' => array(
			'{content-type}' => $contentType,
			'{filesize}' => $filesize
		))); 
		
		if($options['partial']) {
			//$this->setHeader('accept-ranges', "0-$filesize");
			$this->setHeader('accept-ranges', 'bytes');
			if(isset($_SERVER['HTTP_RANGE'])) {
				$result = $this->sendFileRange($filename, $_SERVER['HTTP_RANGE']);
				if(is_int($result)) {
					if($options['exit']) exit();
					return $result; // success
				}
				if($result === null) { // fail
					$this->setHeader('httpcode', 416); // range cannot be satisfied
					$this->setHeader('content-range', 'bytes 0-' . ($filesize - 1) . "/$filesize");
					if($options['exit']) exit;
					return 0;
				} else if($result === false) {
					// continue with regular send
				}
			}
		}
	
		$this->sendHeaders();
		@ob_end_clean();
		@flush();
		
		if($filename === false) {
			echo $options['data'];
		} else {
			readfile($filename);
		}
		
		if($options['exit']) exit;
		
		return $bytesSent;
	}

	/**
	 * Handle an HTTP_RANGE request for sending of partial file (called by sendFile method)
	 * 
	 * @param string $filename
	 * @param string $rangeStr Range string (i.e. `bytes=0-1234`) or omit to pull from `$_SERVER['HTTP_RANGE']`
	 * @return bool|int Returns bytes sent, null if error in request or range, or false if request should be handled by sendFile() instead
	 * 
	 */
	protected function sendFileRange($filename, $rangeStr = '') {
	
		if(empty($rangeStr)) $rangeStr = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '';
		if(empty($rangeStr)) return false;
		
		$filesize = filesize($filename);
		$rangeEnd = $filesize - 1;

		// client has provided an HTTP_RANGE header containing a byte range
		list($rangeType, $rangeBytes) = explode('=', $rangeStr, 2);
		
		if(strtolower($rangeType) !== 'bytes') return null; // unrecognized range type prefix
		if(strpos($rangeBytes, ',') !== false) return null; // unsupported multibyte range
		if(strpos($rangeBytes, '-') === false) return null; // unrecognized range bytes string

		if(strpos($rangeBytes, '-') === 0) {
			// no rangeStart: rangeBytes was "-123" or just "-"
			$rangeStart = $filesize - ((int) ltrim($rangeBytes, '-'));
		} else {
			// rangeBytes was '0-1234' or '1234-5678' or '1234-'
			$rangeArray = explode('-', $rangeBytes, 2); // 0=start, 1=end
			$rangeStart = (int) $rangeArray[0];
			if(isset($rangeArray[1]) && ctype_digit($rangeArray[1])) {
				$rangeEnd = (int) $rangeArray[1];
				if($rangeEnd >= $filesize) $rangeEnd = $filesize-1; // rangeEnd must be under filesize
			} else {
				// keep existing rangeEnd at EOF
			}
		}
		if($rangeStart > $rangeEnd) return null; // do not allow start greater than end

		$this->setHeader('httpcode', 206); // 206=Partial Content
		$this->setHeader('content-range', "bytes $rangeStart-$rangeEnd/$filesize");
		$this->setHeader('content-length', $rangeEnd - $rangeStart + 1);
		$this->sendHeaders();
		
		@ob_end_clean();
		@flush();

		$fp = fopen($filename, 'rb');
		$chunkSize = 1024 * 32;
		$bytesSent = 0;
		
		fseek($fp, $rangeStart); 
		
		while(!feof($fp) && ($pos = ftell($fp)) <= $rangeEnd) {
			if($pos + $chunkSize > $rangeEnd) $chunkSize = $rangeEnd - $pos + 1;
			set_time_limit(600); 
			echo fread($fp, $chunkSize);
			$bytesSent += $chunkSize;
		}
		
		fclose($fp);
		
		return $bytesSent;
	}

	/**
	 * Send currently set HTTP request headers to connected HTTP client
	 * 
	 * This will send all HTTP headers previously set with setHeader() or setHeaders().
	 * 
	 * Note: if a header with name `httpCode` and integer value has been previously set, it will be sent as an HTTP status header
	 * before the other headers. This can also be specified with the `httpCode` in the $options argument. 
	 * 
	 * #pw-internal
	 * 
	 * @param array $options Options to modify default behavior:
	 *  - `reset` (bool): Reset/clear headers that were set to WireHttp after sending? (default=false)
	 *  - `headers` (array): Array [ name => value ] of headers to send, or omit to use headers set to WireHttp instance (default=[])
	 *  - `httpCode` (int): HTTP status code to send or omit for none (default=0, aka don’t send)
	 *  - `httpVersion` (string): HTTP version string (default='1.1')
	 *  - `replacements` (array): Associative array of [ find => replace ] strings to replace values in headers, i.e. `[ '{filesize}' => 12345 ]` (default=[])
	 * @return array Returns the headers that were sent (with duplicates removed, replacements processed, and lowercase header names)
	 * @throws WireException If given an unrecognized `$option['status']` code
	 * @since 3.0.131
	 * 
	 */
	public function sendHeaders(array $options = array()) {
		$defaults = array(
			'reset' => false, 
			'headers' => array(), 
			'httpCode' => 0, 
			'httpVersion' => '1.1',
			'replacements' => array(),
		);
		$options = array_merge($defaults, $options);
		$headers = empty($options['headers']) ? $this->headers : $options['headers'];
		$httpCode = (int) $options['httpCode'];
		if(!$httpCode && isset($headers['httpcode'])) { 
			if(ctype_digit($headers['httpcode'])) $httpCode = (int) $headers['httpcode'];
		}
		if($httpCode > 0) {
			if(!isset($this->httpCodes[$httpCode])) throw new WireException("Unrecognized http status code: $httpCode"); 
			$this->sendHeader("HTTP/$options[httpVersion] $httpCode " . $this->httpCodes[$httpCode]);
		}
		$a = array();
		foreach($headers as $key => $value) {
			$key = strtolower($key);
			if($value === null || $key === 'httpcode') continue;
			if(count($options['replacements'])) {
				$value = str_replace(array_keys($options['replacements']), array_values($options['replacements']), $value);
			}
			$a[$key] = $value;
		}
		foreach($a as $key => $value) {
			$this->sendHeader($key, $value); 
		}
		if($options['reset'] && $headers === $this->headers) $this->headers = array();
		return $a;
	}

	/**
	 * Send a specific HTTP header to currenty connected HTTP client
	 * 
	 * #pw-internal
	 * 
	 * @param string $name Header name or entire header string. 
	 * @param null|string|int $value Header value, or omit if you provided entire header string in $name argument
	 * @since 3.0.131
	 * 
	 */
	public function sendHeader($name, $value = null) {
		if($value === null) {
			header($name);
		} else {
			header("$name: $value"); 
		}
	}

	/**
	 * Validate a URL for WireHttp use
	 * 
	 * #pw-internal
	 *
	 * @param string $url URL to validate
	 * @param bool $throw Whether to throw exception on validation fail (default=false)
	 * @throws \Exception|WireException
	 * @return string $url Valid URL or blank string on failure
	 * 
	 */
	public function validateURL($url, $throw = false) {
		$options = $this->validateURLOptions;
		$options['allowSchemes'] = $this->allowSchemes;
		try {
			$url = $this->wire('sanitizer')->url($url, $options); 
		} catch(WireException $e) {
			if($throw) {
				throw $e;
			} else {
				$this->trackException($e, false);
			}
			$url = '';
		}
		return $url;
	}

	/**
	 * Reset all response properties
	 *
	 */
	protected function resetResponse() {
		$this->responseHeader = array();
		$this->responseHeaders = array();
		$this->httpCode = 0;
		$this->error = array();
	}

	/**
	 * Reset all request data
	 *
	 */
	protected function resetRequest() {
		$this->data = array();
		$this->rawData = null;
		$this->headers = $this->defaultHeaders;
	}

	/**
	 * Get a string of the last error message
	 *
	 * @param bool $getArray Specify true to receive an array of error messages, or omit for a string. 
	 * @return string|array
	 *
	 */
	public function getError($getArray = false) {
		$error = $getArray ? $this->error : implode(', ', $this->error); 
		if($this->httpCode >= 400 && isset($this->httpCodes[$this->httpCode])) {
			$httpError = "$this->httpCode " . $this->httpCodes[$this->httpCode];
			if($getArray) {
				array_unshift($error, $httpError); 
			} else {
				$error = "$httpError: $error";
			}
		}
		return $error; 
	}

	/**
	 * Get last HTTP code
	 * 
	 * #pw-group-HTTP-codes
	 *
	 * @param bool $withText Specify true to include the HTTP code text label with the code
	 * @return int|string
	 *
	 */
	public function getHttpCode($withText = false) {
		if($withText) return "$this->httpCode $this->httpCodeText";
		return $this->httpCode; 
	}

	/**
	 * Return array of all possible HTTP codes as (code => description)
	 * 
	 * #pw-group-HTTP-codes
	 *
	 * @return array
	 *
	 */
	public function getHttpCodes() {
		return $this->httpCodes;	
	}
	
	/**
	 * Return array of all possible HTTP success codes as (code => description)
	 * 
	 * #pw-group-HTTP-codes
	 *
	 * @return array
	 *
	 */
	public function getSuccessCodes() {
		$codes = array();
		foreach($this->httpCodes as $code => $text) {
			if($code < 400) $codes[$code] = $text;
		}
		return $codes;
	}

	/**
	 * Return array of all possible HTTP error codes as (code => description)
	 * 
	 * #pw-group-HTTP-codes
	 * 
	 * @return array
	 * 
	 */
	public function getErrorCodes() {
		$errorCodes = array();
		foreach($this->httpCodes as $code => $text) {
			if($code >= 400) $errorCodes[$code] = $text;
		}
		return $errorCodes;
	}

	/**
	 * Set schemes WireHttp is allowed to access (default=[http, https])
	 * 
	 * #pw-group-settings
	 *
	 * @param array|string $schemes Array of schemes or space-separated string of schemes
	 * @param bool $replace Specify true to replace any existing schemes already allowed (default=false)
	 * @return $this
	 *
	 */
	public function setAllowSchemes($schemes, $replace = false) {
		if(is_string($schemes)) {
			$str = strtolower($schemes); 
			$schemes = array();
			$str = str_replace(',', ' ', $str); 
			foreach(explode(' ', $str) as $scheme) {
				if($scheme) $schemes[] = $scheme;
			}
		}
		if(is_array($schemes)) {
			if($replace) {
				$this->allowSchemes = $schemes;
			} else {
				$this->allowSchemes = array_merge($this->allowSchemes, $schemes); 
			}
		}
		return $this;
	}
	
	/**
	 * Return array of allowed schemes
	 * 
	 * #pw-group-settings
	 *
	 * @return array
	 *
	 */
	public function getAllowSchemes() {
		return $this->allowSchemes;
	}

	/**
	 * Set options array given to $sanitizer->url() 
	 * 
	 * It should not be necessary to call this unless you are dealing with an unusual URL that is causing
	 * errors with the default options in WireHttp. Note that the “allowSchemes” option is set separately
	 * with the setAllowSchemes() method in this class. 
	 * 
	 * To return current validate URL options, omit the $options argument. 
	 * 
	 * #pw-group-advanced
	 * 
	 * @param array $options Options to set, see the $sanitizer->url() method for details on options. 
	 * @return array Always returns current options 
	 * 
	 */
	public function setValidateURLOptions(array $options = array()) {
		if(!empty($options)) $this->validateURLOptions = array_merge($this->validateURLOptions, $options);
		return $this->validateURLOptions;
	}

	/**
	 * Get the current user-agent header
	 * 
	 * To set the user agent header, use `$http->setHeader('user-agent', '...');`
	 * 
	 * #pw-group-request-headers
	 * 
	 * @return string
	 * 
	 */
	public function getUserAgent() {
		if(isset($this->headers['user-agent'])) {
			$userAgent = $this->headers['user-agent'];
		} else {
			// some web servers deliver a 400 error if no user-agent set in request header, so make sure one is set
			$userAgent = 'ProcessWire/' . ProcessWire::versionMajor . '.' . ProcessWire::versionMinor . ' (' . $this->className() . ')';
		}
		return $userAgent;
	}

	/**
	 * Set the number of seconds till connection times out 
	 * 
	 * #pw-group-settings
	 * 
	 * @param int|float $seconds
	 * @return $this
	 * 
	 */
	public function setTimeout($seconds) {
		$this->timeout = (float) $seconds; 
		return $this; 
	}

	/**
	 * Get the number of seconds till connection times out
	 *
	 * Used by send(), get(), post(), getJSON(), but not by download() methods.
	 * 
	 * #pw-group-settings
	 *
	 * @return float
	 *
	 */
	public function getTimeout() {
		return $this->timeout === null ? self::defaultTimeout : (float) $this->timeout; 
	}

	/**
	 * Get the last used internal sending type: fopen, curl or socket
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function getLastSendType() {
		return $this->lastSendType;
	}

	/**
	 * #pw-internal
	 * 
	 * @param $errno
	 * @param $errstr
	 * @param $errfile
	 * @param $errline
	 * @param $errcontext
	 * 
	 */
	public function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
		if($errfile || $errline || $errcontext) {} // ignore
		$this->error[] = "$errno: $errstr";
	}


}
