<?php namespace ProcessWire;

require_once(__DIR__ . '/WireHttpRequestSpec.php');
require_once(__DIR__ . '/WireHttpRequestResult.php');

/**
 * WireHttp-compatible async HTTP queue powered by curl_multi
 *
 * Extends WireHttp with concurrent request execution via curl_multi.
 * Requires PHP 8.1+.
 *
 * Thanks to Matjaž Potočnik (@matjazpotocnik) for the original implementation.
 *
 * ~~~~~
 * $http = new WireHttpMulti();
 *
 * // Fetch multiple URLs concurrently, returns body strings (or false on failure) keyed by input key
 * $bodies = $http->getMulti([
 *     'news' => 'https://api.example.com/news',
 *     'events' => 'https://api.example.com/events',
 * ]);
 * echo $bodies['news'];   // string or false
 * echo $bodies['events']; // string or false
 *
 * // Fetch and decode multiple JSON endpoints concurrently
 * $data = $http->getJSONMulti([
 *     'users' => 'https://api.example.com/users',
 *     'posts' => 'https://api.example.com/posts',
 * ]);
 * $users = $data['users']; // array or false
 *
 * // Mix GET, POST and file download requests using the enqueue/execute pattern
 * $http->enqueue(WireHttpRequestSpec::get('https://api.example.com/one'));
 * $http->enqueue(WireHttpRequestSpec::post('https://api.example.com/two', ['key' => 'value']));
 * $http->enqueue(WireHttpRequestSpec::download('https://example.com/file.zip', '/path/to/file.zip'));
 * $results = $http->execute(); // returns WireHttpRequestResult[]
 * foreach($results as $result) {
 *     if($result->success) {
 *         echo $result->body;
 *     } else {
 *         echo "Error ({$result->httpCode}): {$result->curlError}";
 *     }
 * }
 * ~~~~~
 *
 */
class WireHttpMulti extends WireHttp {

	/**
	 * @var WireHttpRequestSpec[] Pending request definitions
	 *
	 */
	protected array $queue = [];

	/**
	 * @var WireHttpRequestResult[] Ordered result list after execute()
	 *
	 */
	protected array $results = [];

	/**
	 * @var WireHttpRequestResult[] Results keyed by spawn sequence
	 *
	 */
	protected array $resultsBySeq = [];

	/**
	 * @var int Maximum concurrent requests
	 *
	 */
	protected int $maxConcurrent = 5;

	/**
	 * @var bool Whether debug logging is enabled
	 *
	 */
	protected bool $debugMode = false;

	/**
	 * @var bool Whether to verify SSL certificates
	 *
	 */
	protected bool $sslVerify = true;

	/**
	 * Set the maximum number of concurrent requests
	 *
	 * @param int $n
	 * @return self
	 *
	 */
	public function setConcurrency(int $n): self {
		$this->maxConcurrent = max(1, $n);
		return $this;
	}

	/**
	 * Set whether SSL certificates should be verified
	 *
	 * @param bool $verify
	 * @return self
	 *
	 */
	public function setSslVerify(bool $verify): self {
		$this->sslVerify = $verify;
		return $this;
	}

	/**
	 * Set whether debug logging is enabled
	 *
	 * @param bool $debug
	 * @return self
	 *
	 */
	public function setDebug(bool $debug): self {
		$this->debugMode = $debug;
		return $this;
	}

	/**
	 * Add a request to the queue
	 *
	 * @param WireHttpRequestSpec|string $item URL string or WireHttpRequestSpec
	 * @return self
	 *
	 */
	public function enqueue(WireHttpRequestSpec|string $item): self {
		$this->queue[] = $item instanceof WireHttpRequestSpec ? $item : WireHttpRequestSpec::get((string) $item);
		if($this->debugMode) {
			$s = (is_string($item)) ? $item : $item->url;
			$this->log('[WireHttpMulti] enqueued ' . $s);
		}
		return $this;
	}

	/**
	 * Natively mimics WireHttp::get(), but concurrently across an array of URLs.
	 *
	 * @param array $requests URL strings or WireHttpRequestSpec objects, preserves input keys in result
	 * @param int|null $concurrency Overrides class maxConcurrent for this call only
	 * @return array Keyed same as $requests; values are body strings, true for successful downloads, or false on failure
	 *
	 */
	public function getMulti(array $requests, ?int $concurrency = null): array {
		$savedQueue = $this->queue;
		$savedConcurrency = $this->maxConcurrent;
		$this->queue = [];
		$keys = [];

		foreach($requests as $key => $item) {
			if(is_string($item) || $item instanceof WireHttpRequestSpec) {
				$this->enqueue($item);
				$keys[] = $key;
			}
		}

		$this->setConcurrency($concurrency ?? $this->maxConcurrent);

		try {
			$results = $this->execute();

			$out = [];
			foreach($results as $index => $res) {
				$key = $keys[$index] ?? $index;
				if(!$res->success) {
					$out[$key] = false;
				} else if($res->toFile !== null) {
					$out[$key] = true;
				} else {
					$out[$key] = is_string($res->body) ? $res->body : '';
				}
			}
		} finally {
			$this->queue = $savedQueue;
			$this->maxConcurrent = $savedConcurrency;
		}
		return $out;
	}

	/**
	 * Natively mimics WireHttp::getJSON(), concurrently.
	 *
	 * @param array $requests URL strings or WireHttpRequestSpec objects, preserves input keys in result
	 * @param int|null $concurrency Overrides class maxConcurrent for this call only
	 * @return array Keyed same as $requests; values are decoded JSON arrays or false on failure
	 *
	 */
	public function getJSONMulti(array $requests, ?int $concurrency = null): array {
		$bodies = $this->getMulti($requests, $concurrency);
		$out = [];

		foreach($bodies as $key => $body) {
			if(!is_string($body)) {
				$out[$key] = false;
			} else {
				$decoded = json_decode($body, true);
				$out[$key] = is_array($decoded) ? $decoded : false;
			}
		}
		return $out;
	}

	/**
	 * Execute all queued requests concurrently and return result objects
	 *
	 * @return WireHttpRequestResult[]
	 *
	 */
	public function execute(): array {
		$this->results = [];
		$this->resultsBySeq = [];
		$this->resetResponse();
		if(empty($this->queue)) return [];

		$mh = curl_multi_init();

		/** @var array $active */
		$active = [];
		$pool = $this->queue;
		$this->queue = [];
		$nextSeq = 0;

		$ramp = min($this->maxConcurrent, count($pool));
		for($i = 0; $i < $ramp; $i++) {
			/** @var WireHttpRequestSpec $nextSpec */
			$nextSpec = array_shift($pool);
			$seq = $nextSeq++;
			$row = $this->spawn($mh, $nextSpec, $seq);
			if($row !== null) {
				$active[(int) $row['handle']] = $row;
			} else {
				$this->resultsBySeq[$seq] ??= $this->buildSpawnFailureResult($nextSpec);
			}
		}

		$running = 0;
		do {
			curl_multi_exec($mh, $running);

			while(($info = curl_multi_info_read($mh)) !== false) {
				if(isset($info['handle']) && $info['handle'] instanceof \CurlHandle) {
					$ch = $info['handle'];
					$id = (int) $ch;
					if(isset($active[$id])) {
						$this->resultsBySeq[$active[$id]['seq']] ??= $this->finalize($active[$id]);
						curl_multi_remove_handle($mh, $ch);
						if(PHP_VERSION_ID < 80400) {
							/** @phpstan-ignore-next-line function.deprecated */
							curl_close($ch);
						}
						if(is_resource($active[$id]['fileHandle'])) {
							fclose($active[$id]['fileHandle']);
						}
						unset($active[$id]);
					}
				}
				if(!empty($pool)) {
					/** @var WireHttpRequestSpec $nextSpec */
					$nextSpec = array_shift($pool);
					$seq = $nextSeq++;
					$row = $this->spawn($mh, $nextSpec, $seq);
					if($row !== null) {
						$active[(int) $row['handle']] = $row;
					} else {
						$this->resultsBySeq[$seq] ??= $this->buildSpawnFailureResult($nextSpec);
					}
				}
			}

			if($running > 0) {
				$selected = curl_multi_select($mh, 1.0);
				if($selected === -1) {
					usleep(10000);
				}
				if($this->debugMode) {
					$this->log(sprintf('[WireHttpMulti] active=%d, queued=%d', count($active), count($pool)));
				}
			}
		} while($running > 0 || !empty($active));

		/** @var array $active */
		foreach($active as $row) {
			$this->resultsBySeq[$row['seq']] ??= $this->finalize($row);
			curl_multi_remove_handle($mh, $row['handle']);
			if(PHP_VERSION_ID < 80400) {
				/** @phpstan-ignore-next-line function.deprecated */
				curl_close($row['handle']);
			}
			if(is_resource($row['fileHandle'])) {
				fclose($row['fileHandle']);
			}
		}

		// curl_multi_close() deprecated in PHP 8.4; CurlMultiHandle GC'd automatically.
		// Kept for PHP < 8.4 compatibility.
		if(PHP_VERSION_ID < 80400) {
			curl_multi_close($mh);
		}

		ksort($this->resultsBySeq);

		$errorMessages = [];
		foreach($this->resultsBySeq as $result) {
			if($result->success) continue;

			if($result->curlError !== null) {
				$errorMessages[] = $result->curlError;
			} else if($result->httpCode >= 400) {
				/** @var array $httpCodes */
				$httpCodes = $this->httpCodes;
				$errorMessages[] = ($httpCodes[$result->httpCode] ?? "HTTP {$result->httpCode}");
			}

			if($result->httpCode > 0) {
				$this->setHttpCode($result->httpCode); // last failed code wins, mirrors WireHttp
			}
		}

		// Limit error detail to first 3, summarize the rest
		if(count($errorMessages) > 3) {
			$extra = count($errorMessages) - 3;
			$errorMessages = array_slice($errorMessages, 0, 3);
			$errorMessages[] = sprintf('… and %d more request(s) failed', $extra);
		}
		$this->error = array_merge($this->error, $errorMessages);

		$this->results = array_values($this->resultsBySeq);

		return $this->results;
	}

	/**
	 * Create a cURL handle and return the active row array
	 *
	 * @param \CurlMultiHandle $mh
	 * @param WireHttpRequestSpec $spec
	 * @param int $seq
	 * @return array{handle: \CurlHandle, spec: WireHttpRequestSpec, fileHandle: resource|null, seq: int}|null
	 *
	 */
	protected function spawn(\CurlMultiHandle $mh, WireHttpRequestSpec $spec, int $seq): ?array {
		$ch = curl_init();
		if(!$ch instanceof \CurlHandle) return null;

		$fileHandle = null;

		$timeout = (int) $this->getTimeout();

		/** @var array $opts */
		$opts = [
			CURLOPT_URL => $spec->url,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
			CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
		];
		$opts[CURLOPT_USERAGENT] = $this->getUserAgent();

		$headerLines = [];
		if(is_array($this->headers)) {
			/** @var string $value */
			foreach($this->headers as $name => $value) {
				if($name === 'user-agent') continue;
				$headerLines[] = trim((string) $name) . ': ' . trim($value);
			}
		}

		if($spec->method === 'POST') {
			$opts[CURLOPT_POST] = true;
			$hasContentType = array_key_exists('content-type', $this->getHeaders());
			if($spec->body !== null) {
				$opts[CURLOPT_POSTFIELDS] = $spec->body;
				if(!$hasContentType) $headerLines[] = 'Content-Type: application/json';
			} else if(is_array($spec->post)) {
				$opts[CURLOPT_POSTFIELDS] = $spec->post;
			}
		} else if($spec->method !== 'GET') {
			$opts[CURLOPT_CUSTOMREQUEST] = strtoupper($spec->method);
		}

		if($spec->toFile !== null) {
			$openedFile = @fopen($spec->toFile, 'w');
			if(is_resource($openedFile)) {
				$fileHandle = $openedFile;
				$opts[CURLOPT_RETURNTRANSFER] = false;
				$opts[CURLOPT_FILE] = $fileHandle;
			} else {
				// Fail fast: Record failure immediately, don't hit the network
				$this->resultsBySeq[$seq] = new WireHttpRequestResult(
					url: $spec->url,
					method: $spec->method,
					success: false,
					httpCode: 0,
					curlErrorCode: 23, // CURLE_WRITE_ERROR
					curlError: "Cannot open for writing: {$spec->toFile}",
					body: null,
					headers: null,
					toFile: $spec->toFile,
					specOptions: is_array($spec->options) ? $spec->options : []
				);
				return null;
			}
		} else {
			$opts[CURLOPT_RETURNTRANSFER] = true;
			$opts[CURLOPT_HEADER] = true;
		}

		if(!empty($spec->options) && is_array($spec->options)) {
			$opts = (array) array_replace($opts, $spec->options);
		}

		if(!empty($headerLines)) {
			$opts[CURLOPT_HTTPHEADER] = $headerLines;
		}

		curl_setopt_array($ch, $opts);
		curl_multi_add_handle($mh, $ch);

		return [
			'handle' => $ch,
			'spec' => $spec,
			'fileHandle' => $fileHandle,
			'seq' => $seq,
		];
	}

	/**
	 * Build a result object for a request where spawn() itself failed
	 *
	 * @param WireHttpRequestSpec $spec
	 * @return WireHttpRequestResult
	 *
	 */
	protected function buildSpawnFailureResult(WireHttpRequestSpec $spec): WireHttpRequestResult {
		return new WireHttpRequestResult(
			url: $spec->url,
			method: $spec->method,
			success: false,
			httpCode: 0,
			curlErrorCode: 2,
			curlError: 'curl_init() failed',
			body: null,
			headers: null,
			toFile: $spec->toFile,
			specOptions: is_array($spec->options) ? $spec->options : []
		);
	}

	/**
	 * Finalize a completed cURL handle into a result object
	 *
	 * @param array{handle: \CurlHandle, spec: WireHttpRequestSpec, fileHandle: resource|null, seq: int} $row
	 * @return WireHttpRequestResult
	 *
	 */
	protected function finalize(array $row): WireHttpRequestResult {
		$ch = $row['handle'];
		$spec = $row['spec'];

		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErrNo = curl_errno($ch);
		$curlErr = curl_error($ch);

		$body = null;
		$headers = [];
		$success = ($curlErrNo === 0) && ($httpCode >= 200) && ($httpCode < 300);

		if(is_resource($row['fileHandle'])) {
			if(!$success && $spec->toFile !== null && file_exists($spec->toFile)) {
				@unlink($spec->toFile);
			}
		} else {
			$response = curl_multi_getcontent($ch);
			$responseStr = is_string($response) ? $response : '';
			$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

			if($headerSize > 0 && strlen($responseStr) >= $headerSize) {
				$headerBlock = substr($responseStr, 0, $headerSize);
				$headers = $this->parseHeaders($headerBlock);
				$body = substr($responseStr, $headerSize);
			} else {
				$body = $responseStr;
			}
		}

		return new WireHttpRequestResult(
			url: $spec->url,
			method: $spec->method,
			success: $success,
			httpCode: $httpCode,
			curlErrorCode: $curlErrNo,
			curlError: $curlErr !== '' ? $curlErr : null,
			body: $body,
			headers: !empty($headers) ? $headers : null,
			toFile: $spec->toFile,
			specOptions: is_array($spec->options) ? $spec->options : []
		);
	}

	/**
	 * Parse raw HTTP response headers into a key => value array
	 *
	 * @param string $raw
	 * @return string[] Associative array with lowercase header names as keys
	 *
	 */
	protected function parseHeaders(string $raw): array {
		$lines = preg_split('/\r?\n/', trim($raw));
		if(!is_array($lines)) return [];

		$headers = [];
		foreach($lines as $line) {
			if(strpos($line, ':') !== false) {
				$parts = explode(':', $line, 2);
				if(count($parts) === 2) {
					$headers[trim(strtolower($parts[0]))] = trim($parts[1]);
				}
			}
		}
		return $headers;
	}
}
