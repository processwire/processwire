<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $input API variable
 *
 */
class WireTest_WireInput extends WireTest {

	protected $originalGet = array();
	protected $originalPost = array();
	protected $originalCookie = array();
	protected $originalWhitelist = array();
	protected $originalUrlSegments = array();
	protected $originalPageNum = 1;
	protected $originalWireInputOrder = '';
	protected $originalRequestMethod = null;
	protected $originalRequestUri = null;
	protected $originalHttps = false;
	protected $hadRequestMethod = false;
	protected $hadRequestUri = false;

	public function init() {
		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$page = $this->getTestPage();

		$this->originalGet = $input->get()->getArray();
		$this->originalPost = $input->post()->getArray();
		$this->originalCookie = $input->cookie()->getArray();
		$this->originalWhitelist = $input->whitelist()->getArray();
		$this->originalUrlSegments = $input->urlSegments();
		$this->originalPageNum = $input->pageNum();
		$this->originalWireInputOrder = $config->wireInputOrder;
		$this->hadRequestMethod = isset($_SERVER['REQUEST_METHOD']);
		$this->hadRequestUri = isset($_SERVER['REQUEST_URI']);
		$this->originalRequestMethod = $this->hadRequestMethod ? $_SERVER['REQUEST_METHOD'] : null;
		$this->originalRequestUri = $this->hadRequestUri ? $_SERVER['REQUEST_URI'] : null;
		$this->originalHttps = $config->https;

		$input->get()->removeAll();
		$input->post()->removeAll();
		$this->replaceInputData($input->cookie(), array());
		$input->whitelist()->removeAll();
		$input->setUrlSegments(array());
		$input->setPageNum(1);

		$input->get()->setArray(array(
			'q' => '<b>Hello</b> World',
			'qty' => '42',
			'badQty' => 'not-a-number',
			'color' => 'blue',
			'badColor' => 'purple',
			'ids' => array('1', '2', 'three'),
			'empty' => '',
			'title_one' => '<b>One</b>',
			'title_two' => '<i>Two</i>',
			'nested' => array('a' => array('b' => 'too-deep')),
			'unsafe name' => 'remove',
			'clean' => 'safe value',
			'markup' => '<b>remove</b>',
			'long' => str_repeat('x', 40),
		));

		$input->post()->setArray(array(
			'comments' => "Line 1\n<b>Line 2</b>",
			'price' => '12.50',
			'qty' => '7',
			'title_en' => '<b>English</b>',
			'title_es' => '<i>Spanish</i>',
			'summary' => 'ProcessWire CMS',
			'ids' => '1,2,foo',
		));

		$this->replaceInputData($input->cookie(), array(
			'foo' => '<b>bar</b>',
			'color' => 'red',
		));

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = rtrim($page->url, '/') . '/photos/large/page3/?q=<b>x</b>&clean=safe';
	}

	public function execute() {
		$this->testQuickAccessProperties();
		$this->testGetPostCookieAccess();
		$this->testInlineSanitization();
		$this->testWhitelist();
		$this->testDirectInputOrderLookup();
		$this->testUrlSegments();
		$this->testPagination();
		$this->testUrlsAndRequestInfo();
		$this->testCookieManagement();
		$this->testWireInputDataSearchingAndIteration();
	}

	public function finish() {
		$input = $this->wire()->input;
		$config = $this->wire()->config;

		$input->get()->removeAll()->setArray($this->originalGet);
		$input->post()->removeAll()->setArray($this->originalPost);
		$this->replaceInputData($input->cookie(), $this->originalCookie);
		$input->whitelist()->removeAll()->setArray($this->originalWhitelist);
		$input->setUrlSegments(array_values($this->originalUrlSegments));
		$input->setPageNum($this->originalPageNum);
		$config->wireInputOrder = $this->originalWireInputOrder;
		$config->https = $this->originalHttps;

		if($this->hadRequestMethod) {
			$_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
		} else {
			unset($_SERVER['REQUEST_METHOD']);
		}

		if($this->hadRequestUri) {
			$_SERVER['REQUEST_URI'] = $this->originalRequestUri;
		} else {
			unset($_SERVER['REQUEST_URI']);
		}
	}

	protected function testQuickAccessProperties() {
		$input = $this->wire()->input;

		$this->check('$input->get is WireInputData', true, $input->get instanceof WireInputData);
		$this->check('$input->post is WireInputData', true, $input->post instanceof WireInputData);
		$this->check('$input->cookie is WireInputDataCookie', true, $input->cookie instanceof WireInputDataCookie);
		$this->check('$input->whitelist is WireInputData', true, $input->whitelist instanceof WireInputData);
		$this->check('$input->pageNum defaults to 1', 1, $input->pageNum);
		$this->check('$input->scheme returns http or https', true, in_array($input->scheme, array('http', 'https'), true));
	}

	protected function testGetPostCookieAccess() {
		$input = $this->wire()->input;

		$this->check('get() with no key returns WireInputData', true, $input->get() instanceof WireInputData);
		$this->check("get('q') returns raw unsanitized value", '<b>Hello</b> World', $input->get('q'));
		$this->check("get('q', 'text') sanitizes with named sanitizer", 'Hello World', $input->get('q', 'text'));
		$this->check("get('q', 'text,entities') chains sanitizers", 'Hello World', $input->get('q', 'text,entities'));
		$this->check("get('color', whitelist) returns allowed value", 'blue', $input->get('color', array('red', 'blue', 'green')));
		$this->check("get('badColor', whitelist, fallback) returns fallback", 'red', $input->get('badColor', array('red', 'blue', 'green'), 'red'));
		$this->check("get('missing', 'int', fallback) returns fallback", 25, $input->get('missing', 'int', 25));
		$this->check("get('active', callback) returns callback result", true, $input->get('qty', function($value) { return ((int) $value) > 0; }));
		$this->check("get('ids[]', 'int') forces array and sanitizes values", array(1, 2, 0), $input->get('ids[]', 'int'));
		$this->check("get('qty', 42) accepts matching integer valid argument", 42, $input->get('qty', 42));
		$this->check("get('qty', 99, fallback) rejects non-matching integer valid argument", 1, $input->get('qty', 99, 1));

		$this->check('post() with no key returns WireInputData', true, $input->post() instanceof WireInputData);
		$this->check("post('comments') returns raw value", "Line 1\n<b>Line 2</b>", $input->post('comments'));
		$this->check("post('comments', 'textarea') sanitizes textarea", "Line 1\nLine 2", $input->post('comments', 'textarea'));
		$this->check("post('qty', 'int', fallback) returns sanitized int", 7, $input->post('qty', 'int', 1));
		$this->check("post('missing', null, fallback) returns fallback without sanitizer", 'fallback', $input->post('missing', null, 'fallback'));

		$this->check('cookie() with no key returns WireInputDataCookie', true, $input->cookie() instanceof WireInputDataCookie);
		$this->check("cookie('foo') returns raw cookie value", '<b>bar</b>', $input->cookie('foo'));
		$this->check("cookie('foo', 'text') sanitizes cookie value", 'bar', $input->cookie('foo', 'text'));
		$this->check("cookie('color', whitelist) returns allowed cookie value", 'red', $input->cookie('color', array('red', 'blue')));
		$this->check("cookie('missing', 'text', fallback) returns fallback", 'fallback', $input->cookie('missing', 'text', 'fallback'));
	}

	protected function testInlineSanitization() {
		$input = $this->wire()->input;

		$this->check('$input->get->text(\'q\') sanitizes text', 'Hello World', $input->get->text('q'));
		$this->check('$input->post->textarea(\'comments\') sanitizes textarea', "Line 1\nLine 2", $input->post->textarea('comments'));
		$this->check('$input->post->float(\'price\') sanitizes float', 12.5, $input->post->float('price'));
		$this->check('$input->post->float(\'price\', min, max, precision) applies numeric args', 10.0, $input->post->float('price', 0, 10, 2));
		$this->check('$input->post->int(\'qty\', 1, 5) applies min/max args', 5, $input->post->int('qty', 1, 5));
		$this->check('$input->post->intArray(\'ids\') sanitizes CSV to int array', array(1, 2, 0), $input->post->intArray('ids'));
		$this->check('$input->cookie->text(\'foo\') sanitizes cookie text', 'bar', $input->cookie->text('foo'));
	}

	protected function testWhitelist() {
		$input = $this->wire()->input;

		$input->whitelist('limit', 25);
		$this->check("whitelist('limit', value) stores value", 25, $input->whitelist('limit'));
		$input->whitelist(array('sort' => 'title', 'dir' => 'asc'));
		$this->check('whitelist(array) stores multiple values', 'title', $input->whitelist('sort'));
		$this->check('whitelist() returns WireInputData', true, $input->whitelist() instanceof WireInputData);
		$this->check('whitelist queryString() includes stored values', 'limit=25&sort=title&dir=asc', $input->whitelist()->queryString());
	}

	protected function testDirectInputOrderLookup() {
		$input = $this->wire()->input;
		$config = $this->wire()->config;

		$config->wireInputOrder = 'get post cookie whitelist';
		$this->check('direct property lookup checks GET first', 'blue', $input->color);
		$config->wireInputOrder = 'cookie post get whitelist';
		$this->check('direct property lookup respects wireInputOrder', 'red', $input->color);
		$this->check('__isset() true when direct property exists', true, isset($input->color));
		$this->check('__isset() false when direct property missing', false, isset($input->does_not_exist));
	}

	protected function testUrlSegments() {
		$input = $this->wire()->input;

		$input->setUrlSegments(array('photos', 'sort-date', 'page-name'));
		$this->check('urlSegments() returns 1-indexed URL segment array', array(1 => 'photos', 2 => 'sort-date', 3 => 'page-name'), $input->urlSegments());
		$this->check('urlSegment(1) returns first segment', 'photos', $input->urlSegment(1));
		$this->check('urlSegment() defaults to first segment', 'photos', $input->urlSegment());
		$this->check('urlSegment(2) returns second segment', 'sort-date', $input->urlSegment(2));
		$this->check('urlSegment(-1) returns last segment', 'page-name', $input->urlSegment(-1));
		$this->check("urlSegment('photos') returns matching index", 1, $input->urlSegment('photos'));
		$this->check("urlSegment('missing') returns 0 when not found", 0, $input->urlSegment('missing'));
		$this->check("urlSegment('photos=') returns following segment", 'sort-date', $input->urlSegment('photos='));
		$this->check("urlSegment('=sort-date') returns previous segment", 'photos', $input->urlSegment('=sort-date'));
		$this->check("urlSegment('sort-*') returns wildcard match", 'sort-date', $input->urlSegment('sort-*'));
		$this->check("urlSegment('sort-(*)') returns wildcard capture", 'date', $input->urlSegment('sort-(*)'));
		$this->check("urlSegment('/^sort-(.+)$/') returns regex capture", 'date', $input->urlSegment('/^sort-(.+)$/'));
		$this->check("urlSegment1('photos') tests only first segment", true, $input->urlSegment1('photos'));
		$this->check("urlSegment2('photos') returns false when focused segment does not match", false, $input->urlSegment2('photos'));
		$this->check('urlSegmentFirst() returns first segment', 'photos', $input->urlSegmentFirst());
		$this->check('urlSegmentLast() returns last segment', 'page-name', $input->urlSegmentLast());
		$this->check('urlSegmentStr() joins current segments', 'photos/sort-date/page-name', $input->urlSegmentStr());
		$this->check("urlSegmentStr(['segments' => ...]) uses override segments", 'alpha/beta', $input->urlSegmentStr(array('segments' => array('alpha', 'beta'))));
		$this->check("urlSegmentStr(['values' => ...]) converts key/value pairs", 'sort/date/page/2', $input->urlSegmentStr(array('values' => array('sort' => 'date', 'page' => 2))));
		$this->check('$input->urlSegmentStr property joins segments', 'photos/sort-date/page-name', $input->urlSegmentStr);
		$this->check('$input->urlSegment1 property returns first segment', 'photos', $input->urlSegment1);
		$this->check('$input->urlSegmentLast property returns last segment', 'page-name', $input->urlSegmentLast);
		$input->setUrlSegment(2, null);
		$this->check('setUrlSegment(num, null) removes and reindexes segments', array(1 => 'photos', 2 => 'page-name'), $input->urlSegments());
		$input->setUrlSegment(2, 'Needs Sanitizing!');
		$this->check('setUrlSegment() sanitizes segment names', 'Needs_Sanitizing_', $input->urlSegment(2));
	}

	protected function testPagination() {
		$input = $this->wire()->input;
		$config = $this->wire()->config;

		$input->setPageNum(3);
		$this->check('pageNum() returns current page number', 3, $input->pageNum());
		$this->check('$input->pageNum property returns current page number', 3, $input->pageNum);
		$this->check('pageNumStr() returns current page number segment', $config->pageNumUrlPrefix . '3', $input->pageNumStr());
		$this->check('pageNumStr(1) returns blank for page 1', '', $input->pageNumStr(1));
		$this->check('pageNumStr(5) returns requested page number segment', $config->pageNumUrlPrefix . '5', $input->pageNumStr(5));
	}

	protected function testUrlsAndRequestInfo() {
		$input = $this->wire()->input;
		$config = $this->wire()->config;

		$this->check('url() includes current URL segments', 'photos/Needs_Sanitizing_', $input->url(), '*=');
		$this->check("url(['pageNum' => 2]) includes requested page number", $config->pageNumUrlPrefix . '2', $input->url(array('pageNum' => 2)), '*=');
		$this->check('url(true) includes query string', '?q=', $input->url(true), '*=');
		$this->check('httpUrl() starts with httpHostUrl()', $input->httpHostUrl(), $input->httpUrl(), '^=');
		$this->check('httpsUrl() starts with https host URL', $input->httpHostUrl(true), $input->httpsUrl(), '^=');
		$this->check('httpHostUrl(true, host) forces https', 'https://example.com', $input->httpHostUrl(true, 'example.com'));
		$this->check('httpHostUrl(false, host) forces http', 'http://example.com', $input->httpHostUrl(false, 'example.com'));
		$this->check("httpHostUrl('', host) returns protocol-relative URL", '//example.com', $input->httpHostUrl('', 'example.com'));
		$this->check('canonicalUrl() can force scheme, host, segments, pageNum, and query string', 'https://example.com', $input->canonicalUrl(array(
			'scheme' => 'https',
			'host' => 'example.com',
			'urlSegments' => array('alpha'),
			'pageNum' => 2,
			'queryString' => array('limit' => 25),
		)), '^=');
		$this->check('canonicalUrl() includes override URL segment', '/alpha', $input->canonicalUrl(array(
			'scheme' => 'https',
			'host' => 'example.com',
			'urlSegments' => array('alpha'),
			'pageNum' => false,
			'queryString' => false,
		)), '*=');
		$this->check('queryString() returns raw GET query string', 'q=%3Cb%3EHello%3C%2Fb%3E+World', $input->queryString(), '^=');
		$this->check('queryString(overrides) overrides or adds values', 'qty=100', $input->queryString(array('qty' => 100)), '*=');

		$cleanQuery = $input->queryStringClean(array(
			'values' => array(
				'sort' => 'title',
				'bad name' => 'removed',
				'markup' => '<b>removed</b>',
				'limit' => '25',
			),
			'validNames' => array('sort', 'bad name', 'markup', 'limit'),
		));
		$this->check('queryStringClean() keeps valid sanitized values', 'sort=title', $cleanQuery, '*=');
		$this->check('queryStringClean() removes names changed by sanitization', false, strpos($cleanQuery, 'bad'));
		$this->check('queryStringClean() removes values changed by sanitization', false, strpos($cleanQuery, 'markup'));
		$this->check('queryStringClean(entityEncode=false) can return unencoded separator', 'sort=title&limit=25', $input->queryStringClean(array(
			'values' => array('sort' => 'title', 'limit' => '25'),
			'entityEncode' => false,
		)));

		$config->https = false;
		$this->check('scheme() returns http when config https is false', 'http', $input->scheme());
		$config->https = true;
		$this->check('scheme() returns https when config https is true', 'https', $input->scheme());
		$config->https = $this->originalHttps;

		$this->check('requestMethod() returns current request method', 'POST', $input->requestMethod());
		$this->check("requestMethod('post') matches case-insensitively", true, $input->requestMethod('post'));
		$this->check("is('post') aliases requestMethod()", true, $input->is('post'));
		$this->check("is('get') returns false when request method differs", false, $input->is('get'));
	}

	protected function testCookieManagement() {
		$input = $this->wire()->input;

		$cookieOptions = $input->cookie->options();
		$input->cookie->options('age', 86400);
		$this->check('cookie->options(key, value) stores default option', 86400, $input->cookie->options('age'));
		$input->cookie->options(array('httponly' => true, 'samesite' => 'Strict'));
		$this->check('cookie->options(array) stores multiple options', true, $input->cookie->options('httponly'));
		$this->check('cookie->options() returns all runtime options', 'Strict', $input->cookie->options()['samesite']);
		$input->cookie->options($cookieOptions);

		$seedCookie = array('managed_cookie' => 'value', 'temp_one' => '1', 'temp_two' => '2');
		$testCookie = $this->wire()->wire(new WireInputDataCookie($seedCookie));
		$this->check('WireInputDataCookie::set() stores value before init', 'updated', $testCookie->set('managed_cookie', 'updated')->get('managed_cookie'));
		$testCookie->temp_one = 'changed';
		$this->check('WireInputDataCookie property assignment stores value before init', 'changed', $testCookie->get('temp_one'));
	}

	protected function testWireInputDataSearchingAndIteration() {
		$input = $this->wire()->input;

		$foundTitles = $input->post->find('title_*');
		$this->check('WireInputData::find() wildcard finds matching names', array('title_en' => '<b>English</b>', 'title_es' => '<i>Spanish</i>'), $foundTitles);
		$foundSanitized = $input->post->find('title_*', array('sanitizer' => 'text'));
		$this->check('WireInputData::find() can sanitize found values', array('title_en' => 'English', 'title_es' => 'Spanish'), $foundSanitized);
		$foundByValue = $input->post->find('/wire/i', array('type' => 'value'));
		$this->check('WireInputData::find() can match by value regex', true, isset($foundByValue['summary']));
		$this->check('WireInputData::findOne() returns first matching value', '<b>English</b>', $input->post->findOne('title_*'));
		$this->check('WireInputData::findOne() returns null when not found', null, $input->post->findOne('does_not_exist_*'));
		$this->check('WireInputData implements Countable', 7, count($input->post));
		$this->check('WireInputData supports array-style read', '7', $input->post['qty']);
		$input->post['array_style'] = 'value';
		$this->check('WireInputData supports array-style write', 'value', $input->post('array_style'));
		unset($input->post['array_style']);
		$this->check('WireInputData supports array-style unset', null, $input->post('array_style'));
		$this->check('WireInputData getArray() returns plain PHP array', true, is_array($input->post->getArray()));
		$iterated = array();
		foreach($input->post as $name => $value) {
			$iterated[$name] = $value;
		}
		$this->check('WireInputData implements IteratorAggregate', $input->post->getArray(), $iterated);
		$input->post->remove('summary');
		$this->check('WireInputData::remove() removes one variable', null, $input->post('summary'));
		$input->post->removeAll();
		$this->check('WireInputData::removeAll() clears all variables', 0, count($input->post));
	}

	protected function replaceInputData(WireInputData $data, array $values) {
		$ref = new \ReflectionObject($data);
		$property = null;

		while($ref && !$property) {
			if($ref->hasProperty('data')) {
				$property = $ref->getProperty('data');
			} else {
				$ref = $ref->getParentClass();
			}
		}

		if($property) {
			$property->setAccessible(true);
			$property->setValue($data, $values);
		}
	}
}
