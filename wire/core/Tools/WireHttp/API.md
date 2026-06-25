# WireHttp

HTTP client for sending GET, POST, PUT, DELETE, PATCH, HEAD, and OPTIONS requests
to URLs, downloading files, and sending files to the browser. Supports CURL, fopen,
and socket transports with automatic fallback.

```php
// Instantiate and use
$http = new WireHttp();
$response = $http->get('https://example.com/api/');
if($response !== false) {
    echo "Response: " . $sanitizer->entities($response);
} else {
    echo "Request failed: " . $http->getError();
}

// POST with data
$response = $http->post('https://example.com/api/', [
    'name' => 'value',
]);
```

## HTTP requests

### get($url, $data = [], $options = [])

Send a GET request. Returns the response body as a string, or `false` on failure.
See `send()` for available `$options`.

```php
$response = $http->get('https://example.com/path/');

// With query parameters
$response = $http->get('https://example.com/path/', [
    'foo' => 'bar',
    'page' => 2,
]);
```

### post($url, $data = [], $options = [])

Send a POST request. Sets a default `application/x-www-form-urlencoded` content-type
header. Returns the response body as a string, or `false` on failure.
See `send()` for available `$options`.

```php
$response = $http->post('https://example.com/api/', [
    'name' => 'value',
]);

// POST raw JSON
$http->setHeader('content-type', 'application/json');
$response = $http->post('https://example.com/api/', json_encode($data));
```

### put($url, $data = [], $options = [])

Send a PUT request. Returns the response body as a string, or `false` on failure.
See `send()` for available `$options`.

```php
$response = $http->put('https://example.com/api/123', ['status' => 'published']);
```

### delete($url, $data = [], $options = [])

Send a DELETE request. Returns the response body as a string, or `false` on failure.
See `send()` for available `$options`.

```php
$response = $http->delete('https://example.com/api/123');
```

### patch($url, $data = [], $options = [])

Send a PATCH request. Returns the response body as a string, or `false` on failure.
See `send()` for available `$options`.

```php
$response = $http->patch('https://example.com/api/123', ['title' => 'Updated']);
```

### head($url, $data = [], $options = [])

Send a HEAD request. Returns an associative array of response headers, or `false` on failure.
See `send()` for available `$options`.

```php
$headers = $http->head('https://example.com/path/');
if(is_array($headers)) {
    echo $headers['content-type'];
}
```

### status($url, $data = [], $textMode = false, $options = [])

Send a HEAD request and return the HTTP status code as an integer. When `$textMode`
is true, returns a string like `"200 OK"`. See `send()` for available `$options`.

```php
$code = $http->status('https://example.com/path/');     // 200
$text = $http->status('https://example.com/path/', [], true); // "200 OK"
```

### statusText($url, $data = [], $options = [])

Send a HEAD request and return the status code and text as a string like `"200 OK"`.
See `send()` for available `$options`.

```php
echo $http->statusText('https://example.com/path/'); // "200 OK"
```

### getJSON($url, $assoc = true, $data = [], $options = [])

Send a GET request and `json_decode()` the response. Returns an array (when `$assoc`
is true) or object, or `false` on failure. See `send()` for available `$options`.

```php
$data = $http->getJSON('https://example.com/api/data.json');
if(is_array($data)) {
    print_r($data);
}
```

### send($url, $data = [], $method = 'POST', $options = [])

The underlying method that handles all request types. Prefer the dedicated methods
(`get()`, `post()`, etc.) over calling this directly.

Options:

| Option          | Default                  | Description                                              |
|-----------------|--------------------------|----------------------------------------------------------|
| `use`           | `['curl', 'fopen', 'socket']` | Transport methods to try, in order                |
| `headers`       | `[]`                     | Additional headers to add to the request                 |
| `resetRequest`  | `false`                  | Reset request data after completing? (since 3.0.253)    |
| `proxy`         | `''`                     | Proxy server URL                                         |

```php
// Force a specific transport
$response = $http->send($url, $data, 'POST', ['use' => 'curl']);
```

## File operations

### download($fromURL, $toFile, $options = [])

Download a file from a URL and save it locally. Returns the filename on success.
Throws `WireException` on any error.

```php
$filename = $http->download('https://example.com/file.zip', '/tmp/file.zip');
echo "Downloaded to: $filename";
```

Options:

| Option              | Default  | Description                                          |
|---------------------|----------|------------------------------------------------------|
| `use` / `useMethod` | auto     | Force method: `'curl'`, `'fopen'`, or `'socket'`    |
| `timeout`           | `50`     | Timeout in seconds                                   |
| `fopen_bufferSize`  | `1048576`| Buffer size for fopen method (bytes)                |

### sendFile($filename, $options = [], $headers = [])

Send the contents of a file (or a data string) to the connected HTTP client with
appropriate headers. Uses `$config->fileContentTypes` to determine content-type.
Throws `WireException` if the file doesn't exist.

```php
// Send a file to the browser
$http->sendFile('/path/to/document.pdf');

// Send with a custom download filename
$http->sendFile('/path/to/file.zip', [
    'downloadFilename' => 'archive.zip',
    'forceDownload' => true,
]);

// Send raw data instead of a file
$http->sendFile(false, [
    'data' => $csvContent,
    'downloadFilename' => 'export.csv',
]);
```

Options:

| Option             | Default | Description                                                       |
|--------------------|---------|-------------------------------------------------------------------|
| `exit`             | `true`  | Halt execution after sending?                                     |
| `partial`          | `true`  | Allow partial downloads via HTTP_RANGE?                           |
| `forceDownload`    | `null`  | Force download? `null` = let content-type decide                  |
| `downloadFilename` | `''`    | Filename to show to user                                          |
| `headers`          | `[]`    | Headers to send (can also be passed as 3rd argument)              |
| `data`             | `null`  | String of data to send (only when `$filename` is `false`)         |

Returns the number of bytes sent (only when `exit` is `false`).

## Request headers

### setHeader($key, $value)

Set a single request header. Header names are stored lowercase. Pass `null` as
`$value` to remove the header.

```php
$http->setHeader('accept', 'application/json');
$http->setHeader('authorization', 'Bearer abc123');
$http->setHeader('x-custom', null); // remove header
```

### setHeaders(array $headers, $options = [])

Set multiple request headers at once. Merges with existing headers by default.

```php
$http->setHeaders([
    'accept' => 'application/json',
    'authorization' => 'Bearer abc123',
]);

// Reset and replace all headers
$http->setHeaders(['accept' => 'text/html'], ['reset' => true]);
```

Options:

| Option         | Default | Description                                          |
|----------------|---------|------------------------------------------------------|
| `reset`        | `false` | Clear all existing headers first?                    |
| `replacements` | `[]`    | `[ find => replace ]` values to substitute in values |

### getHeaders()

Get all currently set request headers as an associative array (lowercase keys).

```php
$headers = $http->getHeaders();
```

### setCookie($name, $value)

Set a cookie for the next CURL request. Pass `null` as `$value` to remove.

```php
$http->setCookie('PHPSESSID', 'abc123');
$http->post('https://example.com/', [], ['use' => 'curl']);
```

### setUserAgent($userAgent) / getUserAgent()

Set or get the user-agent header.

```php
$http->setUserAgent('MyApp/1.0');
echo $http->getUserAgent();
```

### setData($data)

Set the data to send with the next request (overwrites existing data). Accepts an
associative array or a raw string (for JSON, XML, etc.).

```php
$http->setData(['name' => 'value']);
$http->setData(json_encode($data)); // raw string
```

## Response headers

### getResponseHeaders($key = '')

Get response headers from the last request as an associative array (lowercase keys,
string values). If `$key` is specified, returns just that header's value (or `null`).

```php
$headers = $http->getResponseHeaders();
echo $headers['content-type'];

// Get a specific header
$ct = $http->getResponseHeaders('content-type');
```

### getResponseHeaderValues($key = '', $forceArrays = false)

Like `getResponseHeaders()` but multi-value headers are returned as arrays. Use this
when a header may appear multiple times (e.g. `Set-Cookie`).

```php
$values = $http->getResponseHeaderValues('set-cookie');
// Returns string for single-value headers, array for multi-value
```

### getResponseHeader($key = '')

Legacy method — returns all response headers as a flat array, or a specific header
value. Prefer `getResponseHeaders()` for new code.

## HTTP codes

### getHttpCode($withText = false)

Get the HTTP status code from the last request. When `$withText` is true, returns
a string like `"200 OK"`.

```php
$code = $http->getHttpCode();       // 200
$text = $http->getHttpCode(true);   // "200 OK"
```

### getHttpCodes()

Get all known HTTP status codes as an associative array `[ code => description ]`.

```php
$codes = $http->getHttpCodes();
echo $codes[404]; // "Not Found"
```

### getSuccessCodes() / getErrorCodes()

Get HTTP codes below 400 (success) or 400+ (error) as associative arrays.

```php
$success = $http->getSuccessCodes();
$errors = $http->getErrorCodes();
```

## Settings

### setTimeout($seconds) / getTimeout()

Set or get the timeout in seconds (default: 4.5). Downloads use a separate default
of 50 seconds.

```php
$http->setTimeout(10);
echo $http->getTimeout(); // 10
```

### setAllowSchemes($schemes, $replace = false) / getAllowSchemes()

Control which URL schemes are allowed (default: `['http', 'https']`).

```php
$http->setAllowSchemes('ftp', true); // replace with only ftp
$http->setAllowSchemes('https');     // add https
```

### setValidateURLOptions($options = [])

Get or set the options passed to `$sanitizer->url()` during URL validation.

```php
$options = $http->setValidateURLOptions(); // get current
$http->setValidateURLOptions(['allowRelative' => true]); // set
```

## State management

### resetRequest()

Clear all request data, raw data, and headers (restores default headers).
By default the request headers and data will remain in the WireHttp instance 
for re-use by the next request. If this is not your desired behavior then 
either call `$http->resetRequest()` or create a new WireHttp instance for 
each request. 

```php
$http->resetRequest();
```

### resetResponse()

Clear all response data — response headers, HTTP code, and errors.

```php
$http->resetResponse();
```

### getError($getArray = false)

Get the last error message as a string, or as an array when `$getArray` is true.

```php
echo $http->getError();
$errors = $http->getError(true);
```

### validateURL($url, $throw = false)

Validate a URL for WireHttp use. Returns the validated URL or empty string on failure.
When `$throw` is true, throws `WireException` on invalid URLs.

```php
$url = $http->validateURL('https://example.com/path/');
```

### getLastSendType()

Get the transport method used for the last request: `'curl'`, `'fopen'`, or `'socket'`.

```php
echo $http->getLastSendType(); // "curl"
```

## Notes

- WireHttp is instantiated with `new WireHttp()` and is not a registered API variable.
- Transport fallback order defaults to `['curl', 'fopen', 'socket']`.
- All request headers are stored with lowercase keys.
- The `post()` method sets a default `application/x-www-form-urlencoded` content-type.
- The `download()` method throws `WireException` on failure (unlike `send()` which returns `false`).
- `sendFile()` sends real HTTP headers via PHP's `header()` function and is intended for web responses, not CLI.
- CURL follows redirects by default (unless `open_basedir` is set).
- The socket transport manually follows 301/302 redirects up to 5 levels.
- **Source file:** `wire/core/Tools/WireHttp/WireHttp.php`.
