<?php namespace ProcessWire;

/**
 * Immutable result object populated after a WireHttpMulti request completes
 *
 */
class WireHttpRequestResult {

	/**
	 * @param string $url Request URL
	 * @param string $method HTTP method used
	 * @param bool $success Whether the request succeeded (2xx response, no curl error)
	 * @param int $httpCode HTTP response code
	 * @param int $curlErrorCode cURL error code (0 means no error)
	 * @param string|null $curlError cURL error message, or null if none
	 * @param string|null $body Response body, or null for download requests
	 * @param array<string, string>|null $headers Parsed response headers (lowercase keys), or null if unavailable
	 * @param string|null $toFile Destination file path for download requests, or null
	 * @param array<int|string, mixed> $specOptions cURL options from the originating WireHttpRequestSpec
	 *
	 */
	public function __construct(
		public readonly string $url,
		public readonly string $method,
		public readonly bool $success,
		public readonly int $httpCode,
		public readonly int $curlErrorCode,
		public readonly ?string $curlError,
		public readonly ?string $body,
		public readonly ?array $headers,
		public readonly ?string $toFile,
		public readonly array $specOptions,
	) {
	}

	/**
	 * Return result data as an associative array
	 *
	 * @return array<string, mixed>
	 *
	 */
	public function toArray(): array {
		return [
			'url' => $this->url,
			'method' => $this->method,
			'success' => $this->success,
			'httpCode' => $this->httpCode,
			'curlErrorCode' => $this->curlErrorCode,
			'curlError' => $this->curlError,
			'body' => $this->body,
			'headers' => $this->headers,
			'toFile' => $this->toFile,
			'specOptions' => $this->specOptions,
		];
	}
}
