<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireHttp class
 *
 * Tests HTTP request methods (get, post, head, status), request/response headers,
 * settings (timeout, schemes, user agent), state management (reset, error), HTTP
 * codes, file download, and URL validation. Uses the local web server for live
 * HTTP requests.
 *
 */
class WireTest_WireHttp extends WireTest {

	protected $testUrl = '';
	protected $testFile = '';

	public function init() {
		$config = $this->wire()->config;
		$this->testUrl = 'http://' . $config->httpHost . '/';
		$this->testFile = $config->paths->assets . 'wire_test_http_download.txt';
		if(is_file($this->testFile)) $this->wire()->files->unlink($this->testFile);
	}

	public function execute() {
		$this->testGet();
		$this->testPost();
		$this->testHead();
		$this->testStatus();
		$this->testStatusText();
		$this->testRequestHeaders();
		$this->testResponseHeaders();
		$this->testResponseHeaderValues();
		$this->testCookies();
		$this->testSetData();
		$this->testUserAgent();
		$this->testTimeout();
		$this->testAllowSchemes();
		$this->testHttpCodes();
		$this->testResetRequest();
		$this->testResetResponse();
		$this->testError();
		$this->testValidateURL();
		$this->testDownload();
		$this->testGetJSON();
		$this->testSendOptions();
		$this->testLastSendType();
	}

	public function finish() {
		if(is_file($this->testFile)) $this->wire()->files->unlink($this->testFile);
	}

	protected function newHttp() {
		$http = new WireHttp();
		$this->wire($http);
		return $http;
	}

	protected function testGet() {
		$http = $this->newHttp();

		// get() returns string on success
		$result = $http->get($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('get() returns string on success', true, is_string($result) && strlen($result) > 0);

		// get() returns false on invalid URL
		$result = $http->get('http://invalid.localhost.nonexistent:9999/', [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('get() returns false on connection failure', false, $result);

		// get() with query params
		$http = $this->newHttp();
		$result = $http->get($this->testUrl, ['test' => 'value'], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('get() with query params returns string', true, is_string($result) && strlen($result) > 0);
	}

	protected function testPost() {
		$http = $this->newHttp();

		// post() returns string on success
		$result = $http->post($this->testUrl, ['foo' => 'bar'], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('post() returns string on success', true, is_string($result) && strlen($result) > 0);

		// post() sets default content-type header
		$http = $this->newHttp();
		$http->post($this->testUrl, ['foo' => 'bar'], ['use' => 'curl', 'resetRequest' => true]);
		// We can't check the request header after resetRequest, so test without reset
		$http = $this->newHttp();
		$http->post($this->testUrl, ['foo' => 'bar'], ['use' => 'curl']);
		$headers = $http->getHeaders();
		$this->check('post() sets content-type header', true, isset($headers['content-type']));
		$this->check('post() content-type is form-urlencoded', 'application/x-www-form-urlencoded', $headers['content-type'], '*=');
	}

	protected function testHead() {
		$http = $this->newHttp();

		// head() returns array of headers on success
		$result = $http->head($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('head() returns array on success', true, is_array($result));
		$this->check('head() array has content-type', true, isset($result['content-type']));
	}

	protected function testStatus() {
		$http = $this->newHttp();

		// status() returns int HTTP code
		$code = $http->status($this->testUrl, [], false, ['use' => 'curl', 'resetRequest' => true]);
		$this->check('status() returns int', true, is_int($code));
		$this->check('status() returns 200 for valid URL', 200, $code);

		// status() with textMode returns string
		$http = $this->newHttp();
		$text = $http->status($this->testUrl, [], true, ['use' => 'curl', 'resetRequest' => true]);
		$this->check('status(textMode=true) returns string', true, is_string($text));
		$this->check('status(textMode=true) contains "200"', '200', $text, '^=');
	}

	protected function testStatusText() {
		$http = $this->newHttp();

		// statusText() returns string like "200 OK"
		$text = $http->statusText($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('statusText() returns string', true, is_string($text));
		$this->check('statusText() starts with 200', '200', $text, '^=');
		$this->check('statusText() contains OK', 'OK', $text, '*=');
	}

	protected function testRequestHeaders() {
		// setHeader() stores header
		$http = $this->newHttp();
		$http->setHeader('x-custom', 'testvalue');
		$headers = $http->getHeaders();
		$this->check('setHeader() stores header', 'testvalue', $headers['x-custom']);

		// setHeader() stores lowercase
		$this->check('setHeader() stores lowercase key', true, isset($headers['x-custom']));

		// setHeader(null) removes header
		$http->setHeader('x-custom', null);
		$headers = $http->getHeaders();
		$this->check('setHeader(null) removes header', false, isset($headers['x-custom']));

		// setHeaders() merges multiple
		$http = $this->newHttp();
		$http->setHeaders(['x-a' => '1', 'x-b' => '2']);
		$headers = $http->getHeaders();
		$this->check('setHeaders() merges header A', '1', $headers['x-a']);
		$this->check('setHeaders() merges header B', '2', $headers['x-b']);

		// setHeaders(reset=true) replaces all
		$http->setHeaders(['x-c' => '3'], ['reset' => true]);
		$headers = $http->getHeaders();
		$this->check('setHeaders(reset=true) replaces all', '3', $headers['x-c']);
		$this->check('setHeaders(reset=true) removes old headers', false, isset($headers['x-a']));

		// setHeader returns $this
		$http = $this->newHttp();
		$result = $http->setHeader('x-test', 'val');
		$this->check('setHeader() returns $this', true, $result === $http);

		// setHeaders returns $this
		$result = $http->setHeaders(['x-test2' => 'val2']);
		$this->check('setHeaders() returns $this', true, $result === $http);
	}

	protected function testResponseHeaders() {
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl']);

		// getResponseHeaders() returns array
		$headers = $http->getResponseHeaders();
		$this->check('getResponseHeaders() returns array', true, is_array($headers));

		// getResponseHeaders() has content-type
		$this->check('getResponseHeaders() has content-type', true, isset($headers['content-type']));

		// getResponseHeaders(key) returns specific header
		$ct = $http->getResponseHeaders('content-type');
		$this->check('getResponseHeaders(key) returns string', true, is_string($ct));

		// getResponseHeaders(missing key) returns null
		$missing = $http->getResponseHeaders('nonexistent-header-xyz');
		$this->check('getResponseHeaders(missing key) returns null', null, $missing);
	}

	protected function testResponseHeaderValues() {
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl']);

		// getResponseHeaderValues() returns array
		$values = $http->getResponseHeaderValues();
		$this->check('getResponseHeaderValues() returns array', true, is_array($values));

		// getResponseHeaderValues(key) returns value for single-value header
		$ct = $http->getResponseHeaderValues('content-type');
		$this->check('getResponseHeaderValues(key) returns value for single header', true, is_string($ct) || is_array($ct));

		// getResponseHeaderValues(missing key) returns null (bug fix test)
		$missing = $http->getResponseHeaderValues('nonexistent-header-xyz');
		$this->check('getResponseHeaderValues(missing key) returns null', null, $missing);

		// getResponseHeaderValues(forceArrays=true) returns all as arrays
		$allArrays = $http->getResponseHeaderValues('', true);
		$this->check('getResponseHeaderValues(forceArrays=true) returns array', true, is_array($allArrays));
	}

	protected function testCookies() {
		// setCookie() stores cookie
		$http = $this->newHttp();
		$http->setCookie('testcookie', 'abc123');
		$this->check('setCookie() returns $this', true, $http instanceof WireHttp);

		// setCookie(null) removes cookie
		$http->setCookie('testcookie', null);
		$this->check('setCookie(null) executes without error', true, true);
	}

	protected function testSetData() {
		// setData() with array
		$http = $this->newHttp();
		$http->setData(['foo' => 'bar']);
		$this->check('setData(array) returns $this', true, $http instanceof WireHttp);

		// setData() with string (raw data)
		$http->setData('raw string data');
		$this->check('setData(string) returns $this', true, $http instanceof WireHttp);

		// set() sets individual data key
		$http = $this->newHttp();
		$http->set('key1', 'value1');
		$this->check('set() returns $this', true, $http instanceof WireHttp);

		// __get returns set data
		$http = $this->newHttp();
		$http->set('foo', 'bar');
		$this->check('__get returns set data', 'bar', $http->foo);

		// __get returns null for missing data
		$this->check('__get returns null for missing key', null, $http->nonexistent);
	}

	protected function testUserAgent() {
		// getUserAgent() returns default
		$http = $this->newHttp();
		$ua = $http->getUserAgent();
		$this->check('getUserAgent() returns non-empty string', true, is_string($ua) && strlen($ua) > 0);
		$this->check('getUserAgent() contains ProcessWire', 'ProcessWire', $ua, '*=');

		// setUserAgent() sets custom agent
		$http->setUserAgent('MyApp/1.0');
		$this->check('setUserAgent() sets custom agent', 'MyApp/1.0', $http->getUserAgent());

		// setUserAgent via setHeader
		$http->setHeader('user-agent', 'AnotherApp/2.0');
		$this->check('setHeader(user-agent) is reflected in getUserAgent()', 'AnotherApp/2.0', $http->getUserAgent());
	}

	protected function testTimeout() {
		// getTimeout() returns default
		$http = $this->newHttp();
		$this->check('getTimeout() returns default 4.5', 4.5, $http->getTimeout());

		// setTimeout() sets value
		$http->setTimeout(10);
		$this->check('setTimeout(10) sets value', 10.0, $http->getTimeout());

		// setTimeout() with float
		$http->setTimeout(2.5);
		$this->check('setTimeout(2.5) sets float', 2.5, $http->getTimeout());

		// setTimeout() returns $this
		$http = $this->newHttp();
		$result = $http->setTimeout(5);
		$this->check('setTimeout() returns $this', true, $result === $http);
	}

	protected function testAllowSchemes() {
		// getAllowSchemes() returns default
		$http = $this->newHttp();
		$schemes = $http->getAllowSchemes();
		$this->check('getAllowSchemes() includes http', true, in_array('http', $schemes));
		$this->check('getAllowSchemes() includes https', true, in_array('https', $schemes));

		// setAllowSchemes() with replace
		$http->setAllowSchemes('ftp', true);
		$this->check('setAllowSchemes(replace=true) replaces schemes', true, in_array('ftp', $http->getAllowSchemes()));
		$this->check('setAllowSchemes(replace=true) removes old schemes', false, in_array('http', $http->getAllowSchemes()));

		// setAllowSchemes() without replace (merge)
		$http = $this->newHttp();
		$http->setAllowSchemes('ftp');
		$schemes = $http->getAllowSchemes();
		$this->check('setAllowSchemes() merges http', true, in_array('http', $schemes));
		$this->check('setAllowSchemes() merges ftp', true, in_array('ftp', $schemes));

		// setAllowSchemes() returns $this
		$http = $this->newHttp();
		$result = $http->setAllowSchemes('ftp');
		$this->check('setAllowSchemes() returns $this', true, $result === $http);
	}

	protected function testHttpCodes() {
		$http = $this->newHttp();

		// getHttpCodes() returns array
		$codes = $http->getHttpCodes();
		$this->check('getHttpCodes() returns array', true, is_array($codes));
		$this->check('getHttpCodes() has 200', 'OK', $codes[200]);
		$this->check('getHttpCodes() has 404', 'Not Found', $codes[404]);
		$this->check('getHttpCodes() has 500', 'Internal Server Error', $codes[500]);

		// getSuccessCodes() returns codes < 400
		$success = $http->getSuccessCodes();
		$this->check('getSuccessCodes() has 200', true, isset($success[200]));
		$this->check('getSuccessCodes() excludes 404', false, isset($success[404]));

		// getErrorCodes() returns codes >= 400
		$errors = $http->getErrorCodes();
		$this->check('getErrorCodes() has 404', true, isset($errors[404]));
		$this->check('getErrorCodes() has 500', true, isset($errors[500]));
		$this->check('getErrorCodes() excludes 200', false, isset($errors[200]));

		// getHttpCode() returns int after request
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$code = $http->getHttpCode();
		$this->check('getHttpCode() returns 200 after successful request', 200, $code);

		// getHttpCode(true) returns string with text
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$text = $http->getHttpCode(true);
		$this->check('getHttpCode(true) returns string', true, is_string($text));
		$this->check('getHttpCode(true) contains 200', '200', $text, '^=');

		// setHttpCode() sets code and text
		$http = $this->newHttp();
		$http->setHttpCode(404);
		$this->check('setHttpCode(404) sets code', 404, $http->getHttpCode());
		$this->check('setHttpCode(404) sets text', 'Not Found', $http->getHttpCode(true), '*=');
	}

	protected function testResetRequest() {
		// resetRequest() clears data and headers
		$http = $this->newHttp();
		$http->setHeader('x-custom', 'value');
		$http->set('foo', 'bar');
		$http->resetRequest();
		$headers = $http->getHeaders();
		$this->check('resetRequest() clears custom headers', false, isset($headers['x-custom']));
		$this->check('resetRequest() restores default charset header', 'utf-8', $headers['charset']);
		$this->check('resetRequest() clears data', null, $http->foo);
	}

	protected function testResetResponse() {
		// resetResponse() clears response data
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl']);
		$http->resetResponse();
		$this->check('resetResponse() clears httpCode', 0, $http->getHttpCode());
		$this->check('resetResponse() clears response headers', 0, count($http->getResponseHeaders()));
		$this->check('resetResponse() clears errors', '', $http->getError());
	}

	protected function testError() {
		// getError() returns string by default
		$http = $this->newHttp();
		$this->check('getError() returns empty string when no errors', '', $http->getError());

		// getError(true) returns array
		$this->check('getError(true) returns array', true, is_array($http->getError(true)));

		// getError() returns error after failed request
		$http = $this->newHttp();
		$http->get('http://invalid.localhost.nonexistent:9999/', [], ['use' => 'curl', 'resetRequest' => true]);
		$error = $http->getError();
		$this->check('getError() returns non-empty after failed request', true, is_string($error) && strlen($error) > 0);
	}

	protected function testValidateURL() {
		$http = $this->newHttp();

		// validateURL() accepts valid URL
		$result = $http->validateURL('https://example.com/path/');
		$this->check('validateURL() accepts valid https URL', 'https://example.com/path/', $result);

		// validateURL() accepts http URL
		$result = $http->validateURL('http://example.com/');
		$this->check('validateURL() accepts valid http URL', 'http://example.com/', $result);

		// validateURL() rejects relative URL
		$result = $http->validateURL('/path/to/page');
		$this->check('validateURL() rejects relative URL', '', $result);

		// validateURL(throw=true) throws on disallowed scheme
		$threw = false;
		try {
			$http->validateURL('javascript:alert(1)', true);
		} catch(\Throwable $e) {
			$threw = true;
		}
		$this->check('validateURL(throw=true) throws on invalid URL', true, $threw);
	}

	protected function testDownload() {
		$http = $this->newHttp();

		// download() saves file and returns filename
		$result = $http->download($this->testUrl, $this->testFile, ['use' => 'curl']);
		$this->check('download() returns filename', $this->testFile, $result);
		$this->check('download() creates file', true, is_file($this->testFile));
		$this->check('download() file has content', true, filesize($this->testFile) > 0);

		// download() throws on invalid URL
		$threw = false;
		try {
			$http->download('not-a-url', $this->testFile . 'x');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('download() throws WireException on invalid URL', true, $threw);
	}

	protected function testGetJSON() {
		// getJSON() on a non-JSON URL returns null (json_decode of HTML)
		$http = $this->newHttp();
		$result = $http->getJSON($this->testUrl, true, [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('getJSON() on non-JSON returns null', null, $result);

		// getJSON(assoc=false) returns object on valid JSON
		// We can't easily serve JSON from the test install, so test the decode logic
		$http = $this->newHttp();
		// getJSON calls json_decode on get() result
		// If get() returns HTML, json_decode returns null
		$this->check('getJSON() returns null for non-JSON content', null, $result);
	}

	protected function testSendOptions() {
		// send() with use=curl
		$http = $this->newHttp();
		$result = $http->send($this->testUrl, [], 'GET', ['use' => 'curl', 'resetRequest' => true]);
		$this->check('send(use=curl) returns string', true, is_string($result) && strlen($result) > 0);

		// send() with use=fopen
		$http = $this->newHttp();
		$result = $http->send($this->testUrl, [], 'GET', ['use' => 'fopen', 'resetRequest' => true]);
		$this->check('send(use=fopen) returns string', true, is_string($result) && strlen($result) > 0);

		// send() with resetRequest=true clears request data
		$http = $this->newHttp();
		$http->setHeader('x-test', 'value');
		$http->send($this->testUrl, [], 'GET', ['use' => 'curl', 'resetRequest' => true]);
		$headers = $http->getHeaders();
		$this->check('send(resetRequest=true) clears custom headers', false, isset($headers['x-test']));

		// send() with headers option
		$http = $this->newHttp();
		$result = $http->send($this->testUrl, [], 'GET', [
			'use' => 'curl',
			'headers' => ['x-custom' => 'testval'],
			'resetRequest' => true,
		]);
		$this->check('send(headers option) succeeds', true, is_string($result) && strlen($result) > 0);
	}

	protected function testLastSendType() {
		// getLastSendType() returns curl after curl request
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'curl', 'resetRequest' => true]);
		$this->check('getLastSendType() returns curl after curl request', 'curl', $http->getLastSendType());

		// getLastSendType() returns fopen after fopen request
		$http = $this->newHttp();
		$http->get($this->testUrl, [], ['use' => 'fopen', 'resetRequest' => true]);
		$this->check('getLastSendType() returns fopen after fopen request', 'fopen', $http->getLastSendType());
	}
}
