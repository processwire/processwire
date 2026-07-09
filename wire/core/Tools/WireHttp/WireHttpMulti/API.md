# WireHttpMulti

Extends `WireHttp` to execute multiple HTTP requests concurrently using `curl_multi`.
Inherits all configuration methods from `WireHttp` (headers, timeout, user-agent, etc.).
Requires PHP 8.1+.

```php
$http = new WireHttpMulti();

// Fetch multiple URLs concurrently
$bodies = $http->getMulti([
    'news'   => 'https://api.example.com/news',
    'events' => 'https://api.example.com/events',
]);
echo $bodies['news'];   // string body or false on failure
echo $bodies['events']; // string body or false on failure

// Fetch and decode multiple JSON endpoints concurrently
$data = $http->getJSONMulti([
    'users' => 'https://api.example.com/users',
    'posts' => 'https://api.example.com/posts',
]);
$users = $data['users']; // array or false

// Mix GET, POST and download requests using enqueue/execute
$http->enqueue(WireHttpRequestSpec::get('https://api.example.com/one'));
$http->enqueue(WireHttpRequestSpec::post('https://api.example.com/two', ['key' => 'value']));
$http->enqueue(WireHttpRequestSpec::download('https://example.com/file.zip', '/path/to/file.zip'));
$results = $http->execute(); // WireHttpRequestResult[]
foreach($results as $result) {
    if($result->success) {
        echo $result->body;
    } else {
        echo "Error ({$result->httpCode}): {$result->curlError}";
    }
}
```

## Simple concurrent requests

### getMulti($requests, $concurrency = null)

Fetch multiple URLs concurrently. Mirrors `WireHttp::get()` but operates on an array
of URLs. Returns an array keyed by the same keys as `$requests`, where each value is
the response body string, `true` for successful downloads, or `false` on failure.

This method saves and restores the queue and concurrency state around its execution,
so it is safe to call even if requests have already been enqueued via `enqueue()`.

Because the return value mixes strings and bools, use strict comparison (`=== false`,
`=== true`) when checking results rather than truthiness.

- **`$requests`** `array` — Associative or indexed array of URL strings or
  `WireHttpRequestSpec` objects.
- **`$concurrency`** `int|null` — Max concurrent requests for this call only.
  Defaults to the value set by `setConcurrency()` (default 5).

```php
$http = new WireHttpMulti();

// Indexed array — results keyed 0, 1, 2
$bodies = $http->getMulti([
    'https://api.example.com/a',
    'https://api.example.com/b',
    'https://api.example.com/c',
]);

// Associative array — results keyed by name
$bodies = $http->getMulti([
    'alpha' => 'https://api.example.com/a',
    'beta'  => 'https://api.example.com/b',
]);
echo $bodies['alpha']; // response body or false

// WireHttpRequestSpec objects allow per-request options
$bodies = $http->getMulti([
    WireHttpRequestSpec::get('https://api.example.com/a', [CURLOPT_TIMEOUT => 10]),
    WireHttpRequestSpec::get('https://api.example.com/b'),
]);
```

### getJSONMulti($requests, $concurrency = null)

Fetch multiple URLs concurrently and `json_decode()` each response. Mirrors
`WireHttp::getJSON()` but operates on an array of URLs. Returns an array keyed by
the same keys as `$requests`, where each value is an associative array or `false`
on failure or invalid JSON.

Like `getMulti()`, this method saves and restores queue and concurrency state, so it
is safe to call alongside enqueued requests.

```php
$http = new WireHttpMulti();
$data = $http->getJSONMulti([
    'users' => 'https://api.example.com/users',
    'posts' => 'https://api.example.com/posts',
]);
if(is_array($data['users'])) {
    foreach($data['users'] as $user) echo $user['name'];
}
```

## Enqueue/execute pattern

Use `enqueue()` and `execute()` when you need mixed request types (GET, POST, download)
or want full access to result details like HTTP codes, headers, and curl errors.

### enqueue($item)

Add a request to the queue. Accepts a URL string (treated as GET) or a
`WireHttpRequestSpec` object for full control over method, body, and options.
Returns `$this` for chaining.

```php
$http = new WireHttpMulti();

// Simple URL string (GET)
$http->enqueue('https://api.example.com/data');

// Using WireHttpRequestSpec for more control
$http->enqueue(WireHttpRequestSpec::get('https://api.example.com/data'));
$http->enqueue(WireHttpRequestSpec::post('https://api.example.com/submit', ['key' => 'value']));
$http->enqueue(WireHttpRequestSpec::download('https://example.com/file.zip', '/path/to/file.zip'));

$results = $http->execute();
```

### execute()

Execute all queued requests concurrently. Clears the queue and returns an array of
`WireHttpRequestResult` objects in the same order as the requests were enqueued.
Also populates the inherited `getError()` and `getHttpCode()` state from any failures.

```php
$http = new WireHttpMulti();
$http->enqueue('https://api.example.com/a');
$http->enqueue('https://api.example.com/b');

$results = $http->execute();

foreach($results as $result) {
    if($result->success) {
        echo $result->url . ': ' . strlen($result->body) . " bytes\n";
    } else {
        echo $result->url . ' failed: ' . $result->curlError . "\n";
    }
}
```

## Configuration

### setConcurrency($n)

Set the maximum number of requests to run simultaneously. Default is 5. Returns
`$this` for chaining.

```php
$http = new WireHttpMulti();
$http->setConcurrency(10);
```

### setSslVerify($verify)

Set whether SSL certificates are verified. Defaults to `true`. Set to `false` to
disable verification (useful for development environments with self-signed certs).
Returns `$this` for chaining.

```php
$http->setSslVerify(false);
```

### setDebug($debug)

Enable or disable debug logging via `Wire::log()`. When enabled, logs enqueued URLs
and active/queued counts during execution. Returns `$this` for chaining.

```php
$http->setDebug(true);
```

## WireHttpRequestSpec

Value object for defining a single queued request. Use the static factory methods
rather than instantiating directly.

### WireHttpRequestSpec::get($url, $options = null)

Create a GET request spec.

- **`$url`** `string` — Request URL.
- **`$options`** `array|null` — Per-request cURL option overrides (keyed by `CURLOPT_*` constant).

```php
$spec = WireHttpRequestSpec::get('https://api.example.com/data');
$spec = WireHttpRequestSpec::get('https://api.example.com/data', [CURLOPT_TIMEOUT => 30]);
```

### WireHttpRequestSpec::post($url, $postFields, $options = null)

Create a POST request spec with form fields.

- **`$url`** `string` — Request URL.
- **`$postFields`** `array` — Associative array of POST fields.
- **`$options`** `array|null` — Per-request cURL option overrides.

```php
$spec = WireHttpRequestSpec::post('https://api.example.com/submit', [
    'name'  => 'John',
    'email' => 'john@example.com',
]);
```

To POST a raw JSON body instead, set `$spec->body` after creation:

```php
$spec = WireHttpRequestSpec::post('https://api.example.com/submit', []);
$spec->body = json_encode(['name' => 'John']);
// Content-Type: application/json is set automatically when body is non-null
```

### WireHttpRequestSpec::download($url, $toFile, $options = null)

Create a file download request spec.

- **`$url`** `string` — URL to download from.
- **`$toFile`** `string` — Absolute path to save the file to.
- **`$options`** `array|null` — Per-request cURL option overrides.

```php
$spec = WireHttpRequestSpec::download(
    'https://example.com/file.zip',
    $config->paths->files . 'file.zip'
);
```

### WireHttpRequestSpec properties

| Property  | Type            | Description                                                     |
|-----------|-----------------|-----------------------------------------------------------------|
| `$method` | `string`        | HTTP method: `'GET'`, `'POST'`, etc. Default `'GET'`.          |
| `$url`    | `string`        | Target URL.                                                     |
| `$post`   | `array\|null`   | Associative POST fields (used when `$body` is null).           |
| `$body`   | `string\|null`  | Raw POST body string; takes precedence over `$post`. Content-Type defaults to `application/json`. |
| `$toFile` | `string\|null`  | Destination file path for download requests.                    |
| `$options`| `array\|null`   | Per-request cURL option overrides (`CURLOPT_*` constants).      |

## WireHttpRequestResult

Immutable result object returned by `execute()`. All properties are read-only.

| Property        | Type            | Description                                                    |
|-----------------|-----------------|----------------------------------------------------------------|
| `$url`          | `string`        | The request URL.                                               |
| `$method`       | `string`        | HTTP method used.                                              |
| `$success`      | `bool`          | `true` if the request got a 2xx response with no cURL error.  |
| `$httpCode`     | `int`           | HTTP response code (e.g. 200, 404). `0` if no response.       |
| `$curlErrorCode`| `int`           | cURL error code. `0` means no error.                          |
| `$curlError`    | `string\|null`  | cURL error message, or `null` if none.                        |
| `$body`         | `string\|null`  | Response body, or `null` for download requests.               |
| `$headers`      | `array\|null`   | Parsed response headers with lowercase keys, or `null`.       |
| `$toFile`       | `string\|null`  | Destination path for download requests, or `null`.            |
| `$specOptions`  | `array`         | cURL options from the originating `WireHttpRequestSpec`.       |

### toArray()

Return all result properties as an associative array.

```php
$result = $http->execute()[0];
$arr = $result->toArray();
// ['url' => ..., 'method' => ..., 'success' => ..., ...]
```
