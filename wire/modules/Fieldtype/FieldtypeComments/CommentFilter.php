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
 *
 */

abstract class CommentFilter extends WireData {

	protected $comment; 

	public function __construct() {
		$this->set('appUserAgent', 'ProcessWire'); 
		$this->set('charset', 'utf-8'); 
		$this->set('homeURL', 'http://' . $this->config->httpHost); 
		$this->set('apiKey', ''); 
	}

	public function init() {
	}

	public function setComment(Comment $comment) {
		$this->comment = $comment; 
		$this->set('pageUrl', $this->homeURL . $this->wire('page')->url); 
		if(!$comment->ip) $comment->ip = $_SERVER['REMOTE_ADDR']; 
		if(!$comment->user_agent) $comment->user_agent = $_SERVER['HTTP_USER_AGENT']; 
	}

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


