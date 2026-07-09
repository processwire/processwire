<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireHttpMulti.
 *
 */
class WireTest_WireHttpMulti extends WireTest {

	protected $testDir = '';
	protected $routerFile = '';
	protected $serverLog = '';
	protected $serverUrl = '';
	protected $serverProcess = null;
	protected $serverPipes = array();

	public function allow() {
		if(!extension_loaded('curl')) {
			$this->li('curl extension is not installed');
			return false;
		}
		if(!function_exists('proc_open')) {
			$this->li('proc_open() is not available');
			return false;
		}
		if(!function_exists('stream_socket_server')) {
			$this->li('stream_socket_server() is not available');
			return false;
		}
		return true;
	}

	public function init() {
		$files = $this->wire()->files;
		$config = $this->wire()->config;
		$this->testDir = $config->paths->cache . 'WireTests/WireHttpMulti/';
		if(!$files->mkdir($this->testDir, true)) {
			$this->fail("Unable to create test directory: $this->testDir");
		}
		$this->routerFile = $this->testDir . 'router.php';
		$this->serverLog = $this->testDir . 'server.log';
		$this->writeRouter();
		$this->startServer();
	}

	public function execute() {
		$this->testRequestSpecFactories();
		$this->testRequestResultToArray();
		$this->testFluentConfigurationAndEmptyExecute();
		$this->testGetMultiPreservesKeysAndQueue();
		$this->testGetJSONMulti();
		$this->testExecuteMixedRequests();
		$this->testDownloadOpenFailure();
	}

	public function finish() {
		$this->stopServer();
		if(!$this->testDir || !is_dir($this->testDir)) return;
		$files = $this->wire()->files;
		foreach(glob($this->testDir . '*') as $file) {
			if(is_file($file)) $files->unlink($file);
		}
	}

	protected function http() {
		$http = new WireHttpMulti();
		$http->setTimeout(3);
		$http->setUserAgent('WireTests/WireHttpMulti');
		return $http;
	}

	protected function url($path) {
		return $this->serverUrl . $path;
	}

	protected function writeRouter() {
		$router = <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('X-Wire-Route: ' . $path);

if($path === '/text') {
	$name = isset($_GET['name']) ? $_GET['name'] : '';
	header('Content-Type: text/plain');
	echo 'text:' . $name;
	return;
}

if($path === '/json') {
	header('Content-Type: application/json');
	echo json_encode(array(
		'ok' => true,
		'item' => isset($_GET['item']) ? $_GET['item'] : null,
	));
	return;
}

if($path === '/invalid-json') {
	header('Content-Type: application/json');
	echo 'not json';
	return;
}

if($path === '/post') {
	header('Content-Type: application/json');
	echo json_encode(array(
		'method' => $_SERVER['REQUEST_METHOD'],
		'post' => $_POST,
		'body' => file_get_contents('php://input'),
		'contentType' => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '',
	));
	return;
}

if($path === '/headers') {
	header('Content-Type: application/json');
	echo json_encode(array(
		'wireTest' => isset($_SERVER['HTTP_X_WIRE_TEST']) ? $_SERVER['HTTP_X_WIRE_TEST'] : '',
		'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
	));
	return;
}

if($path === '/download') {
	header('Content-Type: text/plain');
	echo 'download-body';
	return;
}

if($path === '/status/404') {
	http_response_code(404);
	header('Content-Type: text/plain');
	echo 'not found';
	return;
}

http_response_code(500);
echo 'unexpected route: ' . $path;
PHP;
		if(file_put_contents($this->routerFile, $router) === false) {
			$this->fail("Unable to write router file: $this->routerFile");
		}
	}

	protected function startServer() {
		$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if(!is_resource($server)) {
			$this->fail("Unable to find test server port: $errstr");
		}
		$name = stream_socket_get_name($server, false);
		fclose($server);
		if(!is_string($name) || strpos($name, ':') === false) {
			$this->fail('Unable to determine test server port');
		}
		$port = (int) substr(strrchr($name, ':'), 1);
		$this->serverUrl = "http://127.0.0.1:$port";

		$cmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' ' . escapeshellarg($this->routerFile);
		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('file', $this->serverLog, 'a'),
			2 => array('file', $this->serverLog, 'a'),
		);
		$this->serverProcess = proc_open($cmd, $descriptors, $this->serverPipes, $this->testDir);
		if(!is_resource($this->serverProcess)) {
			$this->fail('Unable to start PHP test server');
		}
		if(isset($this->serverPipes[0]) && is_resource($this->serverPipes[0])) {
			fclose($this->serverPipes[0]);
		}

		$ready = false;
		$deadline = microtime(true) + 4.0;
		while(microtime(true) < $deadline) {
			$fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
			if(is_resource($fp)) {
				fclose($fp);
				$ready = true;
				break;
			}
			usleep(50000);
		}
		if(!$ready) {
			$log = is_file($this->serverLog) ? file_get_contents($this->serverLog) : '';
			$this->fail('PHP test server did not become ready' . ($log ? ": $log" : ''));
		}
	}

	protected function stopServer() {
		if(is_resource($this->serverProcess)) {
			$status = proc_get_status($this->serverProcess);
			if(!empty($status['running'])) proc_terminate($this->serverProcess);
			proc_close($this->serverProcess);
		}
		$this->serverProcess = null;
		$this->serverPipes = array();
	}

	protected function testRequestSpecFactories() {
		$get = WireHttpRequestSpec::get($this->url('/text?name=a'), array(CURLOPT_TIMEOUT => 7));
		$this->check('get() factory sets method', 'GET', $get->method);
		$this->check('get() factory sets URL', $this->url('/text?name=a'), $get->url);
		$this->check('get() factory stores options', array(CURLOPT_TIMEOUT => 7), $get->options);

		$post = WireHttpRequestSpec::post($this->url('/post'), array('a' => 'b'));
		$this->check('post() factory sets method', 'POST', $post->method);
		$this->check('post() factory stores fields', array('a' => 'b'), $post->post);
		$this->check('post() factory leaves body null', null, $post->body);

		$file = $this->testDir . 'factory-download.txt';
		$download = WireHttpRequestSpec::download($this->url('/download'), $file);
		$this->check('download() factory uses GET method', 'GET', $download->method);
		$this->check('download() factory stores destination file', $file, $download->toFile);
	}

	protected function testRequestResultToArray() {
		$result = new WireHttpRequestResult(
			url: 'http://example.test/',
			method: 'GET',
			success: true,
			httpCode: 200,
			curlErrorCode: 0,
			curlError: null,
			body: 'body',
			headers: array('content-type' => 'text/plain'),
			toFile: null,
			specOptions: array(CURLOPT_TIMEOUT => 3)
		);
		$array = $result->toArray();
		$this->check('toArray() includes URL', 'http://example.test/', $array['url']);
		$this->check('toArray() includes method', 'GET', $array['method']);
		$this->check('toArray() includes success', true, $array['success']);
		$this->check('toArray() includes headers', array('content-type' => 'text/plain'), $array['headers']);
		$this->check('toArray() includes spec options', array(CURLOPT_TIMEOUT => 3), $array['specOptions']);
	}

	protected function testFluentConfigurationAndEmptyExecute() {
		$http = $this->http();
		$this->check('setConcurrency() returns same instance', true, $http === $http->setConcurrency(0));
		$this->check('setSslVerify() returns same instance', true, $http === $http->setSslVerify(false));
		$this->check('setDebug() returns same instance', true, $http === $http->setDebug(false));
		$this->check('empty execute() returns empty array', array(), $http->execute());
		$this->check('empty execute() resets HTTP code', 0, $http->getHttpCode());
		$this->check('empty execute() resets errors', array(), $http->getError(true));
	}

	protected function testGetMultiPreservesKeysAndQueue() {
		$http = $this->http();
		$http->enqueue($this->url('/text?name=pending'));

		$out = $http->getMulti(array(
			'alpha' => $this->url('/text?name=alpha'),
			'beta' => WireHttpRequestSpec::get($this->url('/text?name=beta'), array(CURLOPT_TIMEOUT => 3)),
			'ignored' => array('not a request'),
		), 1);

		$this->check('getMulti() preserves associative keys for valid requests', array('alpha', 'beta'), array_keys($out));
		$this->check('getMulti() returns first body', 'text:alpha', $out['alpha']);
		$this->check('getMulti() returns second body', 'text:beta', $out['beta']);

		$results = $http->execute();
		$this->check('getMulti() restores previously queued request', 1, count($results));
		$this->check('restored queued request succeeds', true, $results[0]->success);
		$this->check('restored queued request body', 'text:pending', $results[0]->body);
	}

	protected function testGetJSONMulti() {
		$http = $this->http();
		$out = $http->getJSONMulti(array(
			'good' => $this->url('/json?item=one'),
			'bad' => $this->url('/invalid-json'),
			'missing' => $this->url('/status/404'),
		), 2);

		$this->check('getJSONMulti() decodes valid JSON', array('ok' => true, 'item' => 'one'), $out['good']);
		$this->check('getJSONMulti() returns false for invalid JSON', false, $out['bad']);
		$this->check('getJSONMulti() returns false for HTTP failure', false, $out['missing']);
	}

	protected function testExecuteMixedRequests() {
		$downloadFile = $this->testDir . 'download.txt';
		$http = $this->http();
		$http->setHeader('X-Wire-Test', 'yes');

		$jsonSpec = WireHttpRequestSpec::post($this->url('/post'), array());
		$jsonSpec->body = json_encode(array('raw' => true));

		$http
			->enqueue($this->url('/text?name=queued'))
			->enqueue(WireHttpRequestSpec::post($this->url('/post'), array('field' => 'value')))
			->enqueue($jsonSpec)
			->enqueue(WireHttpRequestSpec::download($this->url('/download'), $downloadFile))
			->enqueue(WireHttpRequestSpec::get($this->url('/headers'), array(CURLOPT_TIMEOUT => 3)))
			->enqueue($this->url('/status/404'));

		$results = $http->execute();
		$this->check('execute() returns all results in enqueue order', 6, count($results));

		$this->check('string enqueue creates successful GET result', true, $results[0]->success);
		$this->check('string enqueue body is returned', 'text:queued', $results[0]->body);
		$this->check('GET result parsed response header', '/text', $results[0]->headers['x-wire-route']);

		$postData = json_decode($results[1]->body, true);
		$this->check('POST fields request succeeds', true, $results[1]->success);
		$this->check('POST fields request uses POST method', 'POST', $postData['method']);
		$this->check('POST fields request sends field value', 'value', $postData['post']['field']);

		$rawData = json_decode($results[2]->body, true);
		$this->check('raw body POST request succeeds', true, $results[2]->success);
		$this->check('raw body POST request sends JSON body', array('raw' => true), json_decode($rawData['body'], true));
		$this->check('raw body POST request defaults content type to JSON', 'application/json', $rawData['contentType'], '*=');

		$this->check('download request succeeds', true, $results[3]->success);
		$this->check('download request stores destination path', $downloadFile, $results[3]->toFile);
		$this->check('download request has null body', null, $results[3]->body);
		$this->check('download request wrote file contents', 'download-body', file_get_contents($downloadFile));

		$headerData = json_decode($results[4]->body, true);
		$this->check('custom header reaches request', 'yes', $headerData['wireTest']);
		$this->check('user agent reaches request', 'WireTests/WireHttpMulti', $headerData['userAgent']);
		$this->check('spec options are preserved in result', array(CURLOPT_TIMEOUT => 3), $results[4]->specOptions);

		$this->check('HTTP 404 result is unsuccessful', false, $results[5]->success);
		$this->check('HTTP 404 result stores code', 404, $results[5]->httpCode);
		$this->check('execute() updates inherited HTTP code from failure', 404, $http->getHttpCode());
		$this->check('execute() updates inherited error text from failure', '404 Not Found', $http->getError(), '*=');
	}

	protected function testDownloadOpenFailure() {
		$http = $this->http();
		$http->enqueue(WireHttpRequestSpec::download($this->url('/download'), $this->testDir));
		$results = $http->execute();

		$this->check('download open failure returns one result', 1, count($results));
		$this->check('download open failure is unsuccessful', false, $results[0]->success);
		$this->check('download open failure uses write error code', 23, $results[0]->curlErrorCode);
		$this->check('download open failure does not hit HTTP endpoint', 0, $results[0]->httpCode);
		$this->check('download open failure reports destination', $this->testDir, $results[0]->toFile);
		$this->check('download open failure populates inherited error', 'Cannot open for writing', $http->getError(), '*=');
	}
}
