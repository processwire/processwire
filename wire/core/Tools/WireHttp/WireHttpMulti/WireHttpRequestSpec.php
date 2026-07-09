<?php namespace ProcessWire;

/**
 * Value object representing a single queued HTTP request
 *
 */
class WireHttpRequestSpec {

	/**
	 * @var string HTTP method, default GET
	 *
	 */
	public string $method = 'GET';

	/**
	 * @var string Target URL
	 *
	 */
	public string $url = '';

	/**
	 * @var array<string, mixed>|null Associative POST fields, used when body is null
	 *
	 */
	public ?array $post = null;

	/**
	 * @var string|null Destination file path for download requests
	 *
	 */
	public ?string $toFile = null;

	/**
	 * @var array<int|string, mixed>|null Per-request cURL option overrides
	 *
	 */
	public ?array $options = null;

	/**
	 * @var string|null Raw POST body; takes precedence over post, Content-Type defaults to application/json
	 *
	 */
	public ?string $body = null;

	/**
	 * Create a GET request spec
	 *
	 * @param string $url
	 * @param array<int|string, mixed>|null $options
	 * @return self
	 *
	 */
	public static function get(string $url, ?array $options = null): self {
		$s = new self();
		$s->url = $url;
		$s->options = $options;
		return $s;
	}

	/**
	 * Create a POST request spec
	 *
	 * @param string $url
	 * @param array<string, mixed> $postFields
	 * @param array<int|string, mixed>|null $options
	 * @return self
	 *
	 */
	public static function post(string $url, array $postFields, ?array $options = null): self {
		$s = new self();
		$s->method = 'POST';
		$s->url = $url;
		$s->post = $postFields;
		$s->options = $options;
		return $s;
	}

	/**
	 * Create a download request spec
	 *
	 * @param string $url
	 * @param string $toFile Destination file path
	 * @param array<int|string, mixed>|null $options
	 * @return self
	 *
	 */
	public static function download(string $url, string $toFile, ?array $options = null): self {
		$s = new self();
		$s->method = 'GET';
		$s->url = $url;
		$s->toFile = $toFile;
		$s->options = $options;
		return $s;
	}
}
