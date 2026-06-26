# WireInput / $input

`$input` provides access to GET, POST, COOKIE and whitelist variables, plus URL segments,
page numbers, and request details. It is available as the `$input` API variable, which is also accessible
as `$this->wire()->input` (in Wire-derived objects), `wire()->input` and `input()` (when the functions
API is enabled). 

---

## Quick-access properties

| Property                  | Type | Description                            |
|---------------------------| --- |----------------------------------------|
| `$input->get`             | WireInputData | All GET variables                      |
| `$input->post`            | WireInputData | All POST variables                     |
| `$input->cookie`          | WireInputDataCookie | All COOKIE variables                   |
| `$input->whitelist`       | WireInputData | Whitelist variables                    |
| `$input->urlSegments`     | array | All URL segments                       |
| `$input->urlSegmentStr`   | string | URL segments as slash-separated string |
| `$input->urlSegment1`     | string | URL segment by index (1, 2, 3, etc.)   |
| `$input->urlSegmentFirst` | string | Alias of `urlSegment1` (3.0.155+)      |
| `$input->urlSegmentLast`  | string | Last URL segment (3.0.155+)            |
| `$input->pageNum`         | int | Current pagination number (1 = first)  |
| `$input->url`             | string | Current URL without query string       |
| `$input->httpUrl`         | string | Current URL with scheme and hostname   |
| `$input->queryString`     | string | Unsanitized query string               |
| `$input->scheme`          | string | Current scheme: `"http"` or `"https"`  |

---

## Getting input

### $input->get()

- **Arguments:** `get($key = '', $valid = null, $fallback = null)`
- **Returns:** `string|int|array|null|WireInputData` — the value, or `WireInputData` when called with no key
- **Purpose:** Get GET variable(s) from the URL query string. Always sanitize values before use.

~~~~~php
// Raw value — you must sanitize it yourself
$q = $input->get('q');
$q = $sanitizer->text($q);

// Sanitizer name as second argument (3.0.125+)
$q = $input->get('q', 'text');

// Multiple sanitizers as CSV string (3.0.125+)
$q = $input->get('q', 'text,entities');

// Whitelist of allowed values (3.0.125+)
$color = $input->get('color', ['red', 'blue', 'green']);

// Fallback value when not present or invalid (3.0.125+)
$qty   = $input->get('qty', 'int', 1);
$color = $input->get('color', ['red', 'blue', 'green'], 'red');

// Callback for custom validation (3.0.125+)
$active = $input->get('active', function($val) { return $val ? true : false; });

// Force array return value by appending "[]" to key (3.0.125+)
$ids = $input->get('ids[]', 'int');

// Get all GET variables as WireInputData
$get = $input->get();
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/get/](https://processwire.com/api/ref/wire-input/get/)

### $input->post()

- **Arguments:** `post($key = '', $valid = null, $fallback = null)`
- **Returns:** `string|int|array|null|WireInputData` — the value, or `WireInputData` when called with no key
- **Purpose:** Get POST variable(s). Accepts the same `$valid` and `$fallback` arguments as `get()`.

~~~~~php
// Raw value
$comments = $input->post('comments');
$comments = $sanitizer->textarea($comments);

// Sanitizer shorthand (3.0.125+)
$comments = $input->post('comments', 'textarea');
$qty      = $input->post('qty', 'int', 1);
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/post/](https://processwire.com/api/ref/wire-input/post/)

### $input->cookie()

- **Arguments:** `cookie($key = '', $valid = null, $fallback = null)`
- **Returns:** `string|int|array|null|WireInputDataCookie` — the value, or `WireInputDataCookie` when called with no key
- **Purpose:** Get COOKIE variable(s). Accepts the same `$valid` and `$fallback` arguments as `get()`. For setting and removing cookies see the Cookies section below.

~~~~~php
// Raw value
$val = $input->cookie('foo');

// Sanitizer shorthand
$val = $input->cookie('foo', 'text');

// Get all COOKIE variables as WireInputDataCookie
$cookies = $input->cookie();
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/cookie/](https://processwire.com/api/ref/wire-input/cookie/)

---

## Inline sanitization

`WireInputData` proxies `$sanitizer` methods, letting you get and sanitize in one call.
The first argument to the sanitizer method is the input variable name rather than a value.
Most `$sanitizer` methods are supported; see [[Sanitizer]] for more details.

~~~~~php
// These pairs are equivalent:
$name = $input->get->text('name');
$name = $sanitizer->text($input->get('name'));

// POST
$body  = $input->post->textarea('body');
$price = $input->post->float('price', ['min' => 0]);

// COOKIE
$val   = $input->cookie->text('foo');

// Int with min/max
$qty   = $input->post->int('qty', 1, 100);  // min=1, max=100

// Array input
$ids   = $input->post->intArray('ids');     // sanitize CSV or array to int[]
~~~~~

---

## Whitelist

The whitelist is a place to store GET variables you have already sanitized and
validated. It is used by `MarkupPagerNav` (and `renderPagination()`) to carry
safe GET vars across pagination links.

### $input->whitelist()

- **Arguments:** `whitelist($key = '', $value = null)`
- **Returns:** `mixed|WireInputData` — the value, or full `WireInputData` whitelist when called with no key
- **Purpose:** Get or set a whitelist variable.

~~~~~php
// Sanitize a GET var and add to whitelist
$limit = $input->get('limit', 'int', 25);
if($limit < 10 || $limit > 100) $limit = 25;
$input->whitelist('limit', $limit);

// Retrieve from whitelist
$limit = $input->whitelist('limit');

// Set multiple values at once
$input->whitelist(['limit' => 25, 'sort' => 'title']);

// Get full whitelist as WireInputData
$wl = $input->whitelist();
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/whitelist/](https://processwire.com/api/ref/wire-input/whitelist/)

---

## URL segments

URL segments appear after the page's URL path but before the query string. They must be
enabled in the template settings and are automatically sanitized as page names.

### $input->urlSegment()

- **Arguments:** `urlSegment($get = 1)`
- **Returns:** `string|int` — the URL segment string, or integer 1-based index when given a segment name to find
- **Purpose:** Get a URL segment by index, check presence by name, or match by wildcard/regex (3.0.155+).

~~~~~php
// By 1-based index
$seg1 = $input->urlSegment(1);
$seg2 = $input->urlSegment(2);

// All following require 3.0.155+

// Negative index from end (-1 is last)
$last = $input->urlSegment(-1);

// Check presence — returns 1-based index if found, 0 if not
if($input->urlSegment('photos')) {
    // "photos" is present
}

// Key=value relationship: get segment after "sort"
$sort = $input->urlSegment('sort=');  // segment after "sort"
$prev = $input->urlSegment('=bar');   // segment before "bar"

// Wildcard: return matching segment
$sort = $input->urlSegment('sort-*'); // returns "sort-date", "sort-title", etc.

// Wildcard with parenthesis: return only captured portion
$sort = $input->urlSegment('sort-(*)'); // returns just "date" or "title"

// Regex: same as wildcard; first captured group returned if present
$sort = $input->urlSegment('/^sort-(.+)$/');

// Focus on a specific segment number with urlSegment1(), urlSegment2(), etc.
$sort = $input->urlSegment1('sort-*'); // tests only segment #1
$last = $input->urlSegmentLast();      // returns last segment (3.0.155+)
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/url-segment/](https://processwire.com/api/ref/wire-input/url-segment/)

### $input->urlSegments()

- **Returns:** `array` — all URL segments as a 1-indexed array of strings, or empty array if none
- **Purpose:** Get all URL segments as an array.
- **Details:** [processwire.com/api/ref/wire-input/url-segments/](https://processwire.com/api/ref/wire-input/url-segments/)

### $input->urlSegmentStr()

- **Arguments:** `urlSegmentStr($verbose = false, $options = [])`
- **Returns:** `string` — slash-separated URL segment string, e.g. `"photos/large"`, or blank if none
- **Purpose:** Get all URL segments joined as a single slash-separated string.

~~~~~php
$s = $input->urlSegmentStr();
if($s === 'photos/large') {
    // ...
} else if(strlen($s)) {
    throw new Wire404Exception(); // unrecognized
}

// Verbose mode includes page number and trailing slash per template settings
$s = $input->urlSegmentStr(true);
~~~~~

**Key options (in `$options` array, 3.0.106+):** `segments` (array, override URL segments), `values` (array, key/value pairs converted to `/key/value/` string, 3.0.155+), `pageNum` (int, override page number), `page` (Page)

- **Details:** [processwire.com/api/ref/wire-input/url-segment-str/](https://processwire.com/api/ref/wire-input/url-segment-str/)

---

## Pagination

### $input->pageNum()

- **Returns:** `int` — current pagination number, where 1 is the first page
- **Purpose:** Return the current page/pagination number.

~~~~~php
if($input->pageNum > 1) {
    echo "<a href='$page->url'>Return to first page</a>";
}
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/page-num/](https://processwire.com/api/ref/wire-input/page-num/)

### $input->pageNumStr()

- **Arguments:** `pageNumStr($pageNum = 0)`
- **Returns:** `string` — pagination URL segment like `"page2"`, or blank when on page 1
- **Purpose:** Return the URL segment string representing the given (or current) page number. The prefix (typically `"page"`) is controlled by `$config->pageNumUrlPrefix`. (3.0.106+)
- **Details:** [processwire.com/api/ref/wire-input/page-num-str/](https://processwire.com/api/ref/wire-input/page-num-str/)

---

## URLs and request info

### $input->url()

- **Arguments:** `url($options = [])`
- **Returns:** `string` — current URL path including URL segments and page number, without query string
- **Purpose:** Get the current request URL. Same as `$page->url` but includes URL segments and pagination when present.

~~~~~php
$url = $input->url();
echo $sanitizer->entities($url); // always entity-encode for output

// Include query string (unsanitized — always entity-encode before output)
$url = $input->url(['withQueryString' => true]);
echo $sanitizer->entities($url);
~~~~~

**Key options:** `withQueryString` (bool, default false), `page` (Page), `pageNum` (int, override pagination, 3.0.169+)

- **Details:** [processwire.com/api/ref/wire-input/url/](https://processwire.com/api/ref/wire-input/url/)

### $input->httpUrl()

- **Arguments:** `httpUrl($options = [])`
- **Returns:** `string` — same as `url()` but with scheme and hostname prepended
- **Purpose:** Get the full current URL including scheme and hostname.
- **Details:** [processwire.com/api/ref/wire-input/http-url/](https://processwire.com/api/ref/wire-input/http-url/)

### $input->httpsUrl()

- **Arguments:** `httpsUrl($options = [])`
- **Returns:** `string` — same as `httpUrl()` but always uses the `https` scheme
- **Purpose:** Get the full current URL, forcing `https` regardless of the actual request scheme.
- **Details:** [processwire.com/api/ref/wire-input/https-url/](https://processwire.com/api/ref/wire-input/https-url/)

### $input->httpHostUrl()

- **Arguments:** `httpHostUrl($scheme = null, $httpHost = '')`
- **Returns:** `string` — scheme plus hostname with no path, e.g. `"https://www.domain.com"`
- **Purpose:** Get the scheme and hostname portion of the URL only, with no path.

~~~~~php
echo $input->httpHostUrl();        // https://www.domain.com (current scheme)
echo $input->httpHostUrl(true);    // https://www.domain.com (force https)
echo $input->httpHostUrl(false);   // http://www.domain.com (force http)
echo $input->httpHostUrl('');      // //www.domain.com (protocol-relative)
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/http-host-url/](https://processwire.com/api/ref/wire-input/http-host-url/)

### $input->canonicalUrl()

- **Arguments:** `canonicalUrl($options = [])`
- **Returns:** `string` — fully qualified canonical URL for the current page and request
- **Purpose:** Build a canonical URL including scheme, host, path, and optionally URL segments, page number, and query string. Useful for `<link rel="canonical">` tags. (3.0.155+)

~~~~~php
// Basic canonical URL
echo $input->canonicalUrl();

// Customize options
echo $input->canonicalUrl([
    'scheme'      => 'https',  // force https
    'pageNum'     => false,    // exclude pagination number
    'queryString' => false,    // exclude query string
]);
~~~~~

**Key options:** `scheme` (string|bool, auto-detect by default), `host` (string, current host by default), `urlSegments` (bool|array|string, default true), `notSegments` (array|string, patterns to exclude), `pageNum` (bool|int, default true), `queryString` (bool|string|array, uses whitelist by default), `language` (bool|Language, current language by default)

- **Details:** [processwire.com/api/ref/wire-input/canonical-url/](https://processwire.com/api/ref/wire-input/canonical-url/)

### $input->queryString()

- **Arguments:** `queryString($overrides = [])`
- **Returns:** `string` — the unsanitized query string, or blank if none
- **Purpose:** Return the raw query string from the current request. Always entity-encode before using in HTML output.

~~~~~php
echo $sanitizer->entities($input->queryString());

// Override or add GET params
echo $sanitizer->entities($input->queryString(['limit' => 25]));
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/query-string/](https://processwire.com/api/ref/wire-input/query-string/)

### $input->queryStringClean()

- **Arguments:** `queryStringClean($options = [])`
- **Returns:** `string` — cleaned and sanitized query string, entity-encoded by default
- **Purpose:** Return a sanitized query string safe for HTML output. Recommended over `queryString()` when the result will be embedded in a page. (3.0.167+)

~~~~~php
// Only allow specific variable names
echo $input->queryStringClean([
    'validNames' => ['sort', 'limit'],
]);
~~~~~

**Key options:** `values` (array, use instead of current GET vars), `overrides` (array, merge into vars), `validNames` (array, only include these names), `maxItems` (int, default 20), `maxLength` (int, default 1024), `maxNameLength` (int, default 50), `maxValueLength` (int, default 255), `sanitizeName` (string, default `'fieldName'`), `sanitizeValue` (string, default `'line'`), `sanitizeRemove` (bool, remove vars changed by sanitization, default true), `entityEncode` (bool, default true), `separator` (string, default `'&'`)

- **Details:** [processwire.com/api/ref/wire-input/query-string-clean/](https://processwire.com/api/ref/wire-input/query-string-clean/)

### $input->scheme()

- **Returns:** `string` — `"https"` or `"http"`
- **Purpose:** Return the current request scheme.
- **Details:** [processwire.com/api/ref/wire-input/scheme/](https://processwire.com/api/ref/wire-input/scheme/)

### $input->requestMethod()

- **Arguments:** `requestMethod($method = '')`
- **Returns:** `string|bool` — the request method (e.g. `"GET"`, `"POST"`), or `bool` when a method name is provided to check
- **Purpose:** Return the HTTP request method, or check if the current method matches the given value.

~~~~~php
$method = $input->requestMethod();          // "GET", "POST", "PUT", etc.
if($input->requestMethod('POST')) { ... }
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/request-method/](https://processwire.com/api/ref/wire-input/request-method/)

### $input->is($method)

- **Returns:** `bool`
- **Purpose:** Check if the current HTTP request method matches the given name. Shorthand alias of `requestMethod($method)`. (3.0.145+)

~~~~~php
if($input->is('post')) {
    // handle form submission
}
~~~~~

- **Details:** [processwire.com/api/ref/wire-input/is/](https://processwire.com/api/ref/wire-input/is/)

---

## Cookies (WireInputDataCookie)

`$input->cookie` is a `WireInputDataCookie` instance, which extends `WireInputData` with
the ability to set, update, and remove cookies. Reading cookie values works the same as
reading GET/POST values (including inline sanitization via `$input->cookie->text('foo')`).

### $input->cookie->set($key, $value)

- **Arguments:** `set($key, $value, $options = [])`
- **Returns:** `$this`
- **Purpose:** Set a cookie. Pass an integer for `$options` to specify age in seconds, or pass an options array for full control.

~~~~~php
// Set with default options (expires with session)
$input->cookie->foo = 'bar';
$input->cookie->set('foo', 'bar');    // same as above

// Expire after 1 day (86400 seconds)
$input->cookie->set('foo', 'bar', 86400);

// Set with options array
$input->cookie->set('foo', 'bar', [
    'age'      => 86400,
    'path'     => $page->url,
    'httponly' => true,
]);

// Remove a cookie
$input->cookie->remove('foo');
$input->cookie->set('foo', null);     // same result
unset($input->cookie->foo);           // same result
~~~~~

**Key options:**
- `age` (int): Max age in seconds; 0 = expire with session (default=0)
- `expire` (int|string): Expiration as unix timestamp or date string, e.g. `"+1 week"` (3.0.159+)
- `path` (string|null): Cookie path; null = PW installation root URL
- `domain` (string|bool|null): null = current hostname, true = all subdomains of current domain
- `secure` (bool|null): null = auto-detect based on current HTTPS status
- `httponly` (bool): When true, cookie is visible to PHP only, not client-side JS (default=false)
- `samesite` (string): `'Lax'` (default), `'Strict'`, or `'None'` (3.0.178+)
- `fallback` (bool): Queue cookie for next request if headers already sent (default=true)

- **Details:** [processwire.com/api/ref/wire-input-data-cookie/set/](https://processwire.com/api/ref/wire-input-data-cookie/set/)

### $input->cookie->options()

- **Arguments:** `options($key = null, $value = null)`
- **Returns:** `array|string|int|float|null|$this` — all options, one option value, or `$this` when setting
- **Purpose:** Get or set default cookie options that apply to all subsequent `set()` calls. Site-wide defaults can be configured in `$config->cookieOptions` in `/site/config.php`.

~~~~~php
// Get all current options
$opts = $input->cookie->options();

// Get one option
$age = $input->cookie->options('age');

// Set one option (any future set() calls will use this age)
$input->cookie->options('age', 86400);

// Set multiple options
$input->cookie->options([
    'age'    => 604800,
    'secure' => true,
]);

// Site-wide defaults in /site/config.php
$config->cookieOptions = [
    'age'      => 604800,  // 1 week
    'httponly' => true,
    'samesite' => 'Lax',
];
~~~~~

- **Details:** [processwire.com/api/ref/wire-input-data-cookie/options/](https://processwire.com/api/ref/wire-input-data-cookie/options/)

### $input->cookie->remove($key)

- **Returns:** `$this`
- **Purpose:** Remove (expire) a cookie by name. The path, domain, secure and httponly options should match those used when the cookie was set.
- **Details:** [processwire.com/api/ref/wire-input-data-cookie/remove/](https://processwire.com/api/ref/wire-input-data-cookie/remove/)

### $input->cookie->removeAll()

- **Returns:** `$this`
- **Purpose:** Remove all cookies managed by this instance (session cookies are left alone).
- **Details:** [processwire.com/api/ref/wire-input-data-cookie/remove-all/](https://processwire.com/api/ref/wire-input-data-cookie/remove-all/)

---

## WireInputData: searching and iteration

`WireInputData` (the type of `$input->get`, `$input->post`, `$input->whitelist`, and the
base of `$input->cookie`) implements `ArrayAccess`, `IteratorAggregate`, and `Countable`.

### $input->post->find($pattern)

- **Arguments:** `find($pattern, $options = [])`
- **Returns:** `array` — associative `[name => value]` array for matching vars, or empty array if none found
- **Purpose:** Find all input variables whose names (or optionally values) match a wildcard string or PCRE regex. (3.0.163+)

~~~~~php
// Match by name — wildcard
$values = $input->post->find('title_*');   // all starting with "title_"
$values = $input->post->find('*title*');   // all containing "title"

// Match by value — regex, case-insensitive
$values = $input->post->find('/wire/i', ['type' => 'value']);

// With sanitizer applied to found values
$values = $input->post->find('title_*', ['sanitizer' => 'text']);
~~~~~

**Key options:** `type` (string, `'name'` or `'value'`, default `'name'`), `limit` (int, 0 = no limit), `sanitizer` (string, default none), `arrays` (bool, also match array vars, default false)

- **Details:** [processwire.com/api/ref/wire-input-data/find/](https://processwire.com/api/ref/wire-input-data/find/)

### $input->post->findOne($pattern)

- **Arguments:** `findOne($pattern, $options = [])`
- **Returns:** `string|int|float|array|null` — the first matching value, or null if not found
- **Purpose:** Like `find()` but returns only the first matching value rather than an array of all matches. (3.0.163+)
- **Details:** [processwire.com/api/ref/wire-input-data/find-one/](https://processwire.com/api/ref/wire-input-data/find-one/)

### Iteration and array access

~~~~~php
// foreach over all input variables
foreach($input->post as $name => $value) {
    // always sanitize $value before use
}

// Count
$n = count($input->post);

// Array-style read/write/delete
$val = $input->post['name'];
$input->post['name'] = 'value';
unset($input->post['name']);

// Get all as a plain PHP array
$arr = $input->post->getArray();

// Remove one variable
$input->post->remove('name');

// Remove all
$input->post->removeAll();
~~~~~

---

## Notes

- Always sanitize values from `$input` before using them. No automatic sanitization occurs unless you pass a `$valid` argument (3.0.125+) or use the inline sanitizer syntax (`$input->get->text('name')`).
- Accessing `$input->varName` directly (without calling `get()`, `post()`, etc.) checks all input types in the order defined by `$config->wireInputOrder` (default: `"get post cookie"`) — similar to PHP's `$_REQUEST`.
- URL segments must be enabled in the template settings to be available for the current page.
- The `urlSegment1()`, `urlSegment2()`, … `urlSegmentLast()` method forms (3.0.155+) accept the same arguments as `urlSegment()` but only test the specified segment position.
- Source files: `wire/core/WireInput/WireInput.php`, `wire/core/WireInput/WireInputData.php`, `wire/core/WireInput/WireInputDataCookie.php`.
