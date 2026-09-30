<?php namespace ProcessWire;

/**
 * Tests for the web server access rules in htaccess.txt, nginx.txt and caddy.txt
 *
 * Makes HTTP requests to this installation and verifies that files and directories which
 * must not be web accessible are blocked (403), and that regular pages and files still load.
 * The checks are the same for every web server, so they verify Apache (.htaccess), nginx
 * and Caddy configurations.
 *
 * Requests go to `$config->urls->httpRoot`. To test a different web server for the same
 * installation, set the PW_TEST_HTTP_ROOT environment variable to its URL, for example:
 * `PW_TEST_HTTP_ROOT=http://localhost:8080/ php index.php test WebAccess`
 *
 */
class WireTest_WebAccess extends WireTest {

	/**
	 * Base URL that requests are made to, with trailing slash
	 *
	 * @var string
	 *
	 */
	protected $httpRoot = '';

	/**
	 * Fixture files created by this test, to be removed in finish()
	 *
	 * @var array
	 *
	 */
	protected $files = [];

	public function allow() {
		$config = $this->wire()->config;
		$httpRoot = (string) getenv('PW_TEST_HTTP_ROOT');
		if($httpRoot === '') {
			if(!$config->httpHost) return false;
			$httpRoot = $config->urls->httpRoot;
		}
		$this->httpRoot = rtrim($httpRoot, '/') . '/';
		return true;
	}

	public function init() {
		$config = $this->wire()->config;
		$filesPath = $this->getTestPage()->filesManager()->path();
		$this->addFile($filesPath . 'wire-test-access.txt', 'wire-test-access');
		$this->addFile($filesPath . 'wire-test-access.php', "<?php echo 'wire-test-' . 'executed';");
		$this->addFile($filesPath . 'wire-test-access.sqlite', 'wire-test-access');
		$this->addFile($filesPath . 'wire-test-access.log', 'wire-test-access');
		$this->addFile($filesPath . 'wire-test-access.sql', 'wire-test-access');
		$this->addFile($config->paths->logs . 'wire-test-access.txt', 'wire-test-access');
		$this->addFile($config->paths->cache . 'wire-test-access.txt', 'wire-test-access');
	}

	public function execute() {
		$this->li("Testing: $this->httpRoot");
		$this->testAllowed();
		$this->testFileTypes();
		$this->testCoreAndSiteFiles();
		$this->testAssets();
		$this->testVersions();
	}

	public function finish() {
		$files = $this->wire()->files;
		foreach($this->files as $file) {
			if(is_file($file)) $files->unlink($file);
		}
	}

	/**
	 * Requests that must not be blocked
	 *
	 */
	protected function testAllowed() {
		$config = $this->wire()->config;
		$this->check('Homepage loads', 200, $this->status($config->urls->root));
		$this->check('Homepage with query string loads', 200, $this->status($config->urls->root . '?wire-test=1'));
		$this->check('Core JS file loads', 200, $this->status($config->urls->modules . 'Jquery/JqueryCore/JqueryCore.js'));
		$url = $this->getTestPage()->filesManager()->url() . 'wire-test-access.txt';
		$this->check('Page file loads', 'wire-test-access', trim($this->get($url)));
		$this->check('Missing page is 404', 404, $this->status($config->urls->root . 'wire-test-missing-page/'));
		$this->check('.well-known is not blocked', 403, $this->status($config->urls->root . '.well-known/acme-challenge/wire-test'), '!==');
	}

	/**
	 * File types blocked anywhere (sections 5 and 12)
	 *
	 */
	protected function testFileTypes() {
		$root = $this->wire()->config->urls->root;
		$blocked = [
			'.htaccess',
			'.git/config',
			'.env',
			'composer.json',
			'composer.lock',
			'README.md',
			'CLAUDE.md',
			'AGENTS.md',
			'wire-test.inc',
			'wire-test.sh',
			'wire-test.sql',
			'wire-test.bak',
			'wire-test.ini',
			'wire-test.log',
			'wire-test.sqlite',
			'wire-test.sqlite3-wal',
			'wire-test.SQL',
			'wire-test.Bak',
			'wire-test.SQLite',
			'wire-test.php~',
			'htaccess.txt',
			'nginx.txt',
			'caddy.txt',
		];
		foreach($blocked as $path) $this->checkBlocked($root . $path);
	}

	/**
	 * Core and site files blocked by section 15
	 *
	 */
	protected function testCoreAndSiteFiles() {
		$urls = $this->wire()->config->urls;
		$blocked = [
			$urls->site . 'config.php',
			$urls->site . 'config.php.bak',
			$urls->site . 'ready.php',
			$urls->site . 'install/',
			$urls->wire . 'config.php',
			$urls->wire . 'index.config.php',
			$urls->core . 'ProcessWire.php',
			$urls->modules . 'Process/ProcessPageEdit/ProcessPageEdit.module',
			$urls->templates,
			$urls->templates . 'admin.php',
			$urls->site . 'classes/HomePage.php',
			$urls->siteModules . 'wire-test/wire-test.module',
			$urls->siteModules . 'wire-test/wire-test.info.json',
			$urls->root . 'site-default/',
		];
		foreach($blocked as $url) $this->checkBlocked($url);
	}

	/**
	 * Assets directories and files blocked by section 15
	 *
	 */
	protected function testAssets() {
		$config = $this->wire()->config;
		$urls = $config->urls;
		$filesUrl = $this->getTestPage()->filesManager()->url();
		$blocked = [
			$urls->logs,
			$urls->logs . 'wire-test-access.txt',
			$urls->cache . 'wire-test-access.txt',
			$urls->assets . 'sessions/',
			$urls->assets . 'backups/',
			$urls->assets . 'database/wire-test.sqlite',
			$urls->assets . 'wire-test.php',
			$urls->files . '-1/wire-test.txt',
			$filesUrl . 'wire-test-access.php',
			$filesUrl . 'wire-test-access.sqlite',
			$filesUrl . 'wire-test-access.log',
			$filesUrl . 'wire-test-access.sql',
		];
		foreach($blocked as $url) $this->checkBlocked($url);
		$body = $this->get($filesUrl . 'wire-test-access.txt/x.php');
		$this->check('PHP not executed via path info', false, strpos($body, 'wire-test-executed') !== false);
	}

	/**
	 * Versions in htaccess.txt, nginx.txt and caddy.txt match ProcessWire::htaccessVersion
	 *
	 */
	protected function testVersions() {
		$root = $this->wire()->config->paths->root;
		foreach([ 'htaccess.txt' => 'htaccessVersion', 'nginx.txt' => 'nginxVersion', 'caddy.txt' => 'caddyVersion' ] as $name => $tag) {
			if(!is_file($root . $name)) continue;
			$version = preg_match("/@$tag\s+(\d+)/", (string) file_get_contents($root . $name), $m) ? (int) $m[1] : 0;
			$this->check("$name @$tag matches ProcessWire::htaccessVersion", ProcessWire::htaccessVersion, $version);
		}
	}

	/**
	 * Check that a URL is blocked with a 403
	 *
	 * @param string $url URL relative to the domain, i.e. /site/config.php
	 *
	 */
	protected function checkBlocked($url) {
		$this->check("Blocked: $url", 403, $this->status($url));
	}

	/**
	 * Get the HTTP status code for a URL
	 *
	 * @param string $url URL relative to the domain, i.e. /site/config.php
	 * @return int
	 *
	 */
	protected function status($url) {
		$http = $this->request($url);
		return (int) $http->getHttpCode();
	}

	/**
	 * Get the response body for a URL
	 *
	 * @param string $url URL relative to the domain, i.e. /site/config.php
	 * @return string
	 *
	 */
	protected function get($url) {
		$body = '';
		$this->request($url, $body);
		return $body;
	}

	/**
	 * Make a GET request without following redirects
	 *
	 * @param string $url URL relative to the domain, i.e. /site/config.php
	 * @param string $body Receives the response body
	 * @return WireHttp
	 *
	 */
	protected function request($url, &$body = '') {
		$rootUrl = $this->wire()->config->urls->root;
		if(strpos($url, $rootUrl) === 0) $url = substr($url, strlen($rootUrl));
		$http = new WireHttp();
		$this->wire($http);
		$body = (string) $http->get($this->httpRoot . ltrim($url, '/'), [], [ 'followRedirects' => false ]);
		return $http;
	}

	/**
	 * Create a fixture file that is removed in finish()
	 *
	 * @param string $file
	 * @param string $contents
	 *
	 */
	protected function addFile($file, $contents) {
		$files = $this->wire()->files;
		$dir = dirname($file);
		if(!is_dir($dir)) $files->mkdir($dir, true);
		$files->filePutContents($file, $contents);
		$this->files[] = $file;
	}
}
