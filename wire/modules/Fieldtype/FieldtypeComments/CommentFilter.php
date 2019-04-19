<?php namespace ProcessWire;

/**
 * ProcessWire FieldtypeComments > CommentFilter
 *
 * A base class for filtering comments from an external service.
 *
 * Primarily for Akismet (and CommentFilterAkismet), but kept as a base abstract class to 
 * serve as an interface for adding more in the future.
 *
 * Note that portions of code in here arefrom Akismet API examples. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property string $appUserAgent
 * @property string $charset
 * @property string $homeURL @deprecated
 * @property string $apiKey
 *
 *
 */

abstract class CommentFilter extends WireData {

	/**
	 * @var Comment
	 * 
	 */
	protected $comment; 

	public function __construct() {
		$this->set('appUserAgent', 'ProcessWire'); 
		$this->set('charset', 'utf-8'); 
		$this->set('apiKey', ''); 
	}

	public function init() {
		/** @var Paths $urls */
		$urls = $this->wire('config')->urls;
		$this->set('homeURL', $urls->httpRoot);
	}

	public function setComment(Comment $comment) {
		$this->comment = $comment; 
		$page = $comment->getPage();
		if(!$page || !$page->id) $page = $this->wire('page');
		$this->set('pageUrl', $page->httpUrl); 
		if(!$comment->ip) $comment->ip = $this->wire('session')->getIP();
		if(!$comment->user_agent) $comment->user_agent = $_SERVER['HTTP_USER_AGENT']; 
	}

	/**
	 * Send an HTTP POST request
	 * 
	 * @param $request
	 * @param $host
	 * @param $path
	 * @param int $port
	 * @return array|string
	 * @deprecated no longer in use (replaced with WireHttp)
	 * 
	 */
	protected function httpPost($request, $host, $path, $port = 80) {
		// from ksd_http_post() - http://akismet.com/development/api/

		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset={$this->charset}\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		$http_request .= "User-Agent: {$this->appUserAgent}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;

		$response = '';
		if(false !== ($fs = @fsockopen($host, $port, $errno, $errstr, 3))) {
			fwrite($fs, $http_request);
			while (!feof($fs)) $response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);
		}
		return $response;
	}  

	protected function setIsSpam($isSpam) {
		if($isSpam) $this->comment->status = Comment::statusSpam; 
			else $this->comment->status = Comment::statusPending; 
	}

	abstract public function checkSpam(); // check if spam

	abstract public function submitSpam(); // unidentified spam

	abstract public function submitHam(); // false positive
	
}


