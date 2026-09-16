<?php namespace ProcessWire;

/**
 * ProcessWire AI-Assisted Installer
 *
 * Adds an optional AI-assisted mode to the ProcessWire installer (install.php).
 * All AI-specific logic lives here so that the base Installer class stays focused
 * on installing ProcessWire. When this file is not present, the installer simply
 * does not offer the AI-assisted option.
 *
 * The AI-assisted mode:
 *
 *  1. Offers a choice of standard or AI-assisted installation on the welcome screen.
 *  2. Adds a provider step (step 3) between the compatibility check and database
 *     configuration, collecting an API key, endpoint and model, and validating them
 *     with a live test request.
 *  3. After ProcessWire is installed, downloads and installs the AgentTools module
 *     and writes the provider settings to it.
 *  4. Hands off to the AgentTools Site Builder to build the site.
 *
 * Provider credentials are kept in the installer session only, and are written to
 * the AgentTools module configuration once at the end of installation. They are
 * never written to any file by this class.
 *
 * This file is removed along with install.php when the installer is finished.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

if(!defined("PROCESSWIRE_INSTALL")) die("This file may only be used by the ProcessWire installer.");

class InstallerAi {

	/**
	 * Session key holding AI installer state
	 *
	 */
	const sessionKey = 'ai_installer';

	/**
	 * Installer step number for the AI provider form
	 *
	 */
	const stepProvider = 3;

	/**
	 * Installer step number that begins an AI-assisted installation
	 *
	 */
	const stepStartAi = 6;

	/**
	 * Installer step number that continues without AI assistance
	 *
	 */
	const stepSkipAi = 7;

	/**
	 * Provider request formats
	 *
	 */
	const providerAnthropic = 'anthropic';
	const providerOpenAI = 'openai';

	/**
	 * Minimum PHP version required by AgentTools
	 *
	 */
	const minPhpVersion = '8.0.0';

	/**
	 * Default endpoints per provider format
	 *
	 */
	const defaultAnthropicEndpoint = 'https://api.anthropic.com/v1/messages';
	const defaultOpenAIEndpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Seconds to wait for the provider test request
	 *
	 */
	const testTimeout = 30;

	/**
	 * @var Installer
	 *
	 */
	protected $installer;

	/**
	 * Construct
	 *
	 * @param Installer $installer
	 *
	 */
	public function __construct(Installer $installer) {
		$this->installer = $installer;
	}

	/*** AVAILABILITY *************************************************************************/

	/**
	 * Can AI-assisted installation be offered in this environment?
	 *
	 * @return bool
	 *
	 */
	public function isAvailable() {
		if(version_compare(PHP_VERSION, self::minPhpVersion) < 0) return false;
		if(!function_exists('curl_init') && !ini_get('allow_url_fopen')) return false;
		if(!class_exists('\ZipArchive')) return false;
		if(!function_exists('json_decode')) return false;
		return true;
	}

	/**
	 * Get the reason AI-assisted installation is unavailable, or blank if available
	 *
	 * @return string
	 *
	 */
	public function getUnavailableReason() {
		if(version_compare(PHP_VERSION, self::minPhpVersion) < 0) {
			return "AI-assisted installation requires PHP " . self::minPhpVersion . " or newer (you have " . PHP_VERSION . ").";
		}
		if(!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
			return "AI-assisted installation requires either the cURL extension or allow_url_fopen, so that it can reach your AI provider.";
		}
		if(!class_exists('\ZipArchive')) {
			return "AI-assisted installation requires PHP's ZipArchive class, so that it can install the AgentTools module.";
		}
		return '';
	}

	/*** MODE *********************************************************************************/

	/**
	 * Is AI-assisted mode enabled for this installation?
	 *
	 * @return bool
	 *
	 */
	public function isEnabled() {
		$data = $this->sessionRead();
		return !empty($data['enabled']);
	}

	/**
	 * Enable or disable AI-assisted mode
	 *
	 * @param bool $enabled
	 *
	 */
	public function setEnabled($enabled) {
		$data = $this->sessionRead();
		$data['enabled'] = (bool) $enabled;
		$this->sessionWrite($data);
	}

	/**
	 * Are validated provider settings present?
	 *
	 * @return bool
	 *
	 */
	public function hasProvider() {
		$values = $this->getValues();
		return !empty($values['apiKey']) && !empty($values['provider']);
	}

	/*** INSTALLER HOOK POINTS ****************************************************************/

	/**
	 * Render the AI-assisted option on the welcome screen
	 *
	 * Called from Installer::welcome() after the standard "Get Started" button.
	 *
	 */
	public function welcomeOption() {

		$installer = $this->installer;

		if(!$this->isAvailable()) {
			$installer->p(
				"<span class='uk-text-muted'>" . htmlentities($this->getUnavailableReason(), ENT_QUOTES, 'UTF-8') . "</span>",
				'detail'
			);
			return;
		}

		$installer->p(
			"Or let an AI agent build your site for you. You'll need an API key from an AI provider " .
			"such as Anthropic, or any OpenAI-compatible provider. After installation, you'll be asked " .
			"what you would like to build, and an AI agent will create the fields, templates, pages and " .
			"files for your site."
		);

		$installer->btn("AI-Assisted Install", ['name' => 'step_ai', 'value' => '0', 'icon' => 'magic', 'secondary' => true]);
	}

	/**
	 * Additional compatibility checks for AI-assisted installation
	 *
	 * Called from Installer::compatibilityCheck() when AI mode is enabled.
	 *
	 */
	public function compatibilityCheck() {

		$installer = $this->installer;
		$reason = $this->getUnavailableReason();

		if($reason) {
			$installer->err($reason);
			return;
		}

		$installer->ok("PHP " . PHP_VERSION . " supports the AgentTools module");

		if(function_exists('curl_init')) {
			$installer->ok("cURL is available for AI provider requests");
		} else {
			$installer->warn("cURL is not available; AI provider requests will use allow_url_fopen instead");
		}

		if(is_writable("./site/modules/")) {
			$installer->ok("/site/modules/ is writable for the AgentTools module");
		} else {
			$installer->err("/site/modules/ must be writable to install the AgentTools module");
		}
	}

	/**
	 * Get the step number that the compatibility check should continue to
	 *
	 * @return int
	 *
	 */
	public function getNextStepAfterCompatibility() {
		return self::stepProvider;
	}

	/**
	 * Get removable installer items to add to Installer::getRemoveableItems()
	 *
	 * @param string $root Installation root path with trailing slash
	 * @return array
	 *
	 */
	public function getRemoveableItems($root) {
		$items = [];
		if(is_file($root . 'install-ai.php')) {
			$items['install-ai-php'] = [
				'label' => 'Remove AI installer (install-ai.php) when finished',
				'file' => '/install-ai.php',
				'path' => $root . 'install-ai.php',
			];
		}
		return $items;
	}

	/*** PROVIDER STEP ************************************************************************/

	/**
	 * Step 3: AI provider configuration form
	 *
	 * @param array $values Values to populate, or blank to use session/defaults
	 * @param string $error Error message to display from a previous attempt
	 *
	 */
	public function providerStep(array $values = [], $error = '') {

		$installer = $this->installer;
		if(empty($values)) $values = $this->getValues();

		$installer->h('Connect your AI provider', 'magic');

		$installer->p(
			"Enter the API key for the AI provider that will build your site. " .
			"Your key is kept in this installer session and saved to the AgentTools module " .
			"configuration at the end of installation. It is not stored anywhere else."
		);

		if($error) $installer->err($error);

		$installer->sectionStart('fa-plug AI Provider');

		$installer->select('ai_provider', 'Request format', $values['provider'], [
			self::providerAnthropic => 'Anthropic (Claude)',
			self::providerOpenAI => 'OpenAI-compatible',
		], 220);

		$installer->input('ai_model', 'Model', $values['model'], ['width' => 320]);
		$installer->clear();

		$installer->input('ai_endpoint', 'Endpoint URL', $values['endpoint'], ['width' => 540, 'required' => false]);
		$installer->clear();

		$installer->input('ai_api_key', 'API key', '', ['type' => 'password', 'width' => 540]);
		$installer->clear();

		$installer->p(
			"Leave the endpoint URL blank to use your provider's default. " .
			"Choose <em>Anthropic</em> or <em>OpenAI-compatible</em> to match the format your endpoint expects, " .
			"not the company hosting it &mdash; some providers host models from others.",
			'detail'
		);

		$installer->sectionStop();

		$installer->sectionStart('fa-check-circle Recommended models');
		$installer->p($this->getRecommendedModelsNote(), 'detail');
		$installer->sectionStop();

		$installer->p(
			"Your site content and code will be sent to this provider so that it can build your site. " .
			"Providers may retain or use that data under their own policies.",
			'detail'
		);

		$installer->btn('Test key and continue', ['value' => self::stepProvider, 'icon' => 'angle-right']);
		$installer->btn('Skip AI setup', ['value' => 2, 'icon' => 'angle-right', 'secondary' => true, 'name' => 'step_skip_ai']);
	}

	/**
	 * Process the provider form: validate with a live request and store in session
	 *
	 * @return bool True if validated and stored, false if the form was re-displayed
	 *
	 */
	public function providerSave() {

		$installer = $this->installer;

		$values = [
			'provider' => $installer->post('ai_provider', 'name') === self::providerOpenAI
				? self::providerOpenAI
				: self::providerAnthropic,
			'model' => trim((string) $installer->post('ai_model', 'text')),
			'endpoint' => trim((string) $installer->post('ai_endpoint', 'text')),
			'apiKey' => trim((string) (isset($_POST['ai_api_key']) ? $_POST['ai_api_key'] : '')),
		];

		if($values['endpoint'] === '') {
			$values['endpoint'] = $values['provider'] === self::providerOpenAI
				? self::defaultOpenAIEndpoint
				: self::defaultAnthropicEndpoint;
		}

		if($values['apiKey'] === '') {
			$this->providerStep($values, 'Please enter your API key.');
			return false;
		}

		if($values['model'] === '') {
			$this->providerStep($values, 'Please enter the model name to use, for example claude-sonnet-5.');
			return false;
		}

		if(stripos($values['endpoint'], 'https://') !== 0) {
			$this->providerStep($values, 'The endpoint URL must begin with https://');
			return false;
		}

		$result = $this->testProvider($values);

		if(empty($result['ok'])) {
			$this->providerStep($values, $result['error']);
			return false;
		}

		$this->setValues($values);

		return true;
	}

	/**
	 * Send a minimal request to the provider to verify the key, endpoint and model
	 *
	 * @param array $values Provider values: provider, model, endpoint, apiKey
	 * @return array Array of [ 'ok' => bool, 'error' => string, 'status' => int ]
	 *
	 */
	public function testProvider(array $values) {

		$anthropic = $values['provider'] !== self::providerOpenAI;

		$payload = [
			'model' => $values['model'],
			'max_tokens' => 1,
			'messages' => [['role' => 'user', 'content' => 'Hi']],
		];

		if($anthropic) {
			$headers = [
				'x-api-key: ' . $values['apiKey'],
				'anthropic-version: 2023-06-01',
				'content-type: application/json',
			];
		} else {
			$headers = [
				'Authorization: Bearer ' . $values['apiKey'],
				'content-type: application/json',
			];
		}

		$headers = array_merge($headers, $this->getSessionHeaders($values['endpoint']));

		$response = $this->httpPost($values['endpoint'], $headers, json_encode($payload));
		$status = (int) $response['status'];

		if($status >= 200 && $status < 300) return ['ok' => true, 'error' => '', 'status' => $status];

		$message = $this->getResponseError($response['body']);

		if($response['error'] !== '') {
			$error = "Could not reach the provider: $response[error]";
		} else if($status === 401 || $status === 403) {
			$error = "The provider rejected this API key" . ($message ? ": $message" : '.');
		} else if($status === 404) {
			$error = "The provider returned 404 for this endpoint URL. Check the URL and model name" . ($message ? ": $message" : '.');
		} else if($status === 429) {
			$error = "The provider reports this key is rate limited or out of quota" . ($message ? ": $message" : '.');
		} else if($status > 0) {
			$error = "The provider returned an error (HTTP $status)" . ($message ? ": $message" : '.');
		} else {
			$error = "No response from the provider. Check the endpoint URL and that this server can make outbound HTTPS requests.";
		}

		return ['ok' => false, 'error' => $error, 'status' => $status];
	}

	/**
	 * Get provider-specific session headers for an endpoint
	 *
	 * OpenCode requires a stable session ID per conversation. AgentTools sends this for
	 * its own requests; the installer sends one too so that OpenCode keys can be validated.
	 *
	 * @param string $endpoint
	 * @return array
	 *
	 */
	protected function getSessionHeaders($endpoint) {
		$host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
		if($host !== 'opencode.ai' && substr($host, -12) !== '.opencode.ai') return [];
		$data = $this->sessionRead();
		if(empty($data['sessionId'])) {
			$data['sessionId'] = bin2hex(random_bytes(16));
			$this->sessionWrite($data);
		}
		return ['x-opencode-session: ses_' . $data['sessionId']];
	}

	/**
	 * Extract a human-readable error message from a provider response body
	 *
	 * @param string $body
	 * @return string
	 *
	 */
	protected function getResponseError($body) {
		$body = trim((string) $body);
		if($body === '') return '';
		$data = json_decode($body, true);
		if(is_array($data)) {
			if(isset($data['error']['message'])) return $this->truncate($data['error']['message']);
			if(isset($data['error']) && is_string($data['error'])) return $this->truncate($data['error']);
			if(isset($data['message']) && is_string($data['message'])) return $this->truncate($data['message']);
		}
		return $this->truncate(strip_tags($body));
	}

	/**
	 * Truncate and entity-encode a provider message for display
	 *
	 * @param string $str
	 * @param int $maxLength
	 * @return string
	 *
	 */
	protected function truncate($str, $maxLength = 300) {
		$str = trim(preg_replace('/\s+/', ' ', (string) $str));
		if(strlen($str) > $maxLength) $str = substr($str, 0, $maxLength) . '…';
		return htmlentities($str, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * POST JSON to a URL using cURL, or streams when cURL is unavailable
	 *
	 * @param string $url
	 * @param array $headers Array of 'Name: value' strings
	 * @param string $body
	 * @return array Array of [ 'status' => int, 'body' => string, 'error' => string ]
	 *
	 */
	protected function httpPost($url, array $headers, $body) {

		if(function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => self::testTimeout,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_FOLLOWLOCATION => false,
			]);
			$responseBody = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = curl_error($ch);
			curl_close($ch);
			return [
				'status' => $status,
				'body' => $responseBody === false ? '' : (string) $responseBody,
				'error' => $responseBody === false ? $error : '',
			];
		}

		$context = stream_context_create(['http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $body,
			'timeout' => self::testTimeout,
			'ignore_errors' => true,
		]]);

		$responseBody = @file_get_contents($url, false, $context);
		$status = 0;

		if(isset($http_response_header) && is_array($http_response_header)) {
			foreach($http_response_header as $header) {
				if(preg_match('!^HTTP/\S+\s+(\d{3})!', $header, $matches)) $status = (int) $matches[1];
			}
		}

		return [
			'status' => $status,
			'body' => $responseBody === false ? '' : (string) $responseBody,
			'error' => $responseBody === false ? 'Request failed' : '',
		];
	}

	/**
	 * Get the recommended models note for the provider step
	 *
	 * @return string
	 *
	 */
	public function getRecommendedModelsNote() {
		return
			"Site building works best with a capable coding model. We recommend " .
			"<b>Claude Sonnet 5</b>, <b>Opus 4.6</b> or newer (Anthropic format); " .
			"<b>GPT 5.5</b> or newer, <b>GLM 5.2</b> or newer, or <b>Kimi K2.7 Code</b> " .
			"(OpenAI-compatible format). Smaller or older models may struggle to produce a " .
			"complete site. You can change the model later in the AgentTools module settings.";
	}

	/*** AGENTTOOLS INSTALLATION **************************************************************/

	/**
	 * Download, install and configure the AgentTools module
	 *
	 * Called from Installer::adminAccountSave() after ProcessWire is booted and the
	 * superuser account is saved.
	 *
	 * @param ProcessWire $wire
	 * @return bool
	 *
	 */
	public function installAgentTools($wire) {

		$installer = $this->installer;
		$modules = $wire->wire('modules');
		$values = $this->getValues();

		if(empty($values['apiKey'])) {
			$installer->warn("No AI provider settings were entered, so AgentTools was not installed.");
			return false;
		}

		$installer->sectionStart('fa-magic AI Site Builder');

		try {
			if(!$modules->isInstalled('AgentTools')) {
				$file = $this->downloadAgentTools($wire);
				if($file === '') throw new \Exception("Unable to download the AgentTools module.");
				$this->extractAgentTools($wire, $file);
				@unlink($file);
				$modules->refresh();
			}

			if(!$modules->isInstalled('AgentTools')) $modules->install('AgentTools');
			if(!$modules->isInstalled('AgentTools')) throw new \Exception("Unable to install the AgentTools module.");

			$installer->ok("Installed the AgentTools module");

			$this->configureAgentTools($wire, $values);
			$installer->ok("Saved your AI provider settings to AgentTools");

			$this->clearCredentials();

		} catch(\Exception $e) {
			$installer->err(htmlentities($e->getMessage(), ENT_QUOTES, 'UTF-8'));
			$installer->p(
				"ProcessWire is installed and ready to use. To build your site with AI, install the " .
				"<a target='_blank' href='https://processwire.com/modules/agent-tools/'>AgentTools</a> " .
				"module manually and enter your API key in its module settings.",
				'detail'
			);
			$installer->sectionStop();
			$this->clearCredentials();
			return false;
		}

		$installer->sectionStop();

		return true;
	}

	/**
	 * Download the AgentTools module ZIP to a temporary file
	 *
	 * @param ProcessWire $wire
	 * @return string Path to downloaded file, or blank string on failure
	 * @throws \Exception
	 *
	 */
	protected function downloadAgentTools($wire) {

		$config = $wire->wire('config');
		$http = $wire->wire(new WireHttp());

		$url = rtrim($config->moduleServiceURL, '/') . '/AgentTools/?apikey=' . $config->moduleServiceKey;
		if(stripos($url, 'https://') !== 0) throw new \Exception("The modules directory URL must use HTTPS.");

		$data = $http->getJSON($url);
		if(!is_array($data)) throw new \Exception("Unable to reach the ProcessWire modules directory.");
		if(($data['status'] ?? '') === 'error') throw new \Exception("Modules directory: " . $this->truncate($data['error'] ?? 'unknown error'));

		$zipUrl = (string) ($data['download_url'] ?? $data['project_url'] ?? '');
		if(stripos($zipUrl, 'https://') !== 0) throw new \Exception("The AgentTools module has no secure download URL.");

		$file = $config->paths->cache . 'AgentTools-install.zip';
		$http->download($zipUrl, $file);

		if(!is_file($file) || filesize($file) < 1000) throw new \Exception("The AgentTools download was empty or incomplete.");

		return $file;
	}

	/**
	 * Verify and extract the AgentTools module ZIP into /site/modules/
	 *
	 * @param ProcessWire $wire
	 * @param string $file
	 * @throws \Exception
	 *
	 */
	protected function extractAgentTools($wire, $file) {

		$config = $wire->wire('config');
		$files = $wire->wire('files');
		$zip = new \ZipArchive();

		if($zip->open($file) !== true) throw new \Exception("The AgentTools download is not a valid ZIP file.");

		$found = false;
		for($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string) $zip->getNameIndex($i);
			if(strpos($name, '..') !== false) {
				$zip->close();
				throw new \Exception("The AgentTools download contains invalid file paths.");
			}
			if(basename($name) === 'AgentTools.module.php') $found = true;
		}

		$zip->close();

		if(!$found) throw new \Exception("The download does not appear to contain the AgentTools module.");

		$path = $config->paths->siteModules . 'AgentTools/';
		if(!is_dir($path) && !$files->mkdir($path, true)) throw new \Exception("Unable to create /site/modules/AgentTools/");

		$items = $files->unzip($file, $path);
		if(empty($items)) throw new \Exception("Unable to extract the AgentTools module.");

		// modules directory ZIPs usually contain a single top-level directory
		if(!is_file($path . 'AgentTools.module.php')) {
			foreach(new \DirectoryIterator($path) as $dir) {
				if($dir->isDot() || !$dir->isDir()) continue;
				$sub = $dir->getPathname() . '/';
				if(!is_file($sub . 'AgentTools.module.php')) continue;
				foreach(new \DirectoryIterator($sub) as $item) {
					if($item->isDot()) continue;
					@rename($item->getPathname(), $path . $item->getBasename());
				}
				@rmdir($sub);
				break;
			}
		}

		if(!is_file($path . 'AgentTools.module.php')) throw new \Exception("AgentTools.module.php was not found after extraction.");
	}

	/**
	 * Write provider settings to the AgentTools module configuration
	 *
	 * @param ProcessWire $wire
	 * @param array $values
	 * @throws \Exception
	 *
	 */
	protected function configureAgentTools($wire, array $values) {

		$modules = $wire->wire('modules');
		$agentTools = $modules->get('AgentTools');

		$settings = [
			'provider' => $values['provider'],
			'apiKey' => $values['apiKey'],
			'model' => $values['model'],
			'endpoint' => $values['endpoint'],
			'label' => 'Installed with ProcessWire',
		];

		if(method_exists($agentTools, 'configurePrimaryAgent')) {
			$agentTools->configurePrimaryAgent($settings);
			return;
		}

		$data = $modules->getConfig('AgentTools');
		$data['engineer_provider'] = $settings['provider'];
		$data['engineer_api_key'] = $settings['apiKey'];
		$data['engineer_model'] = $settings['model'];
		$data['engineer_endpoint'] = $settings['endpoint'];
		$modules->saveConfig('AgentTools', $data);
	}

	/**
	 * Render the handoff to the Site Builder at the end of installation
	 *
	 * @param ProcessWire $wire
	 * @param string $adminName Admin URL segment
	 *
	 */
	public function siteBuilderLink($wire, $adminName) {

		$installer = $this->installer;
		$modules = $wire->wire('modules');

		if(!$modules->isInstalled('AgentTools')) return;

		$url = "./$adminName/setup/agent-tools/site-builder/";

		$installer->sectionStart('fa-magic Build your site');
		$installer->p(
			"ProcessWire is installed and AgentTools is ready. Next, tell the AI agent what you " .
			"would like to build and it will create the fields, templates, pages and files for your site."
		);
		$installer->btn('What would you like to build?', ['value' => 1, 'icon' => 'magic', 'href' => $url]);
		$installer->sectionStop();
	}

	/*** SESSION STORAGE **********************************************************************/

	/**
	 * Get stored provider values
	 *
	 * @return array
	 *
	 */
	public function getValues() {
		$data = $this->sessionRead();
		$values = isset($data['values']) && is_array($data['values']) ? $data['values'] : [];
		return array_merge([
			'provider' => self::providerAnthropic,
			'model' => '',
			'endpoint' => '',
			'apiKey' => '',
		], $values);
	}

	/**
	 * Store provider values in the installer session
	 *
	 * @param array $values
	 *
	 */
	protected function setValues(array $values) {
		$data = $this->sessionRead();
		$data['values'] = $values;
		$this->sessionWrite($data);
	}

	/**
	 * Remove the API key from the installer session
	 *
	 */
	public function clearCredentials() {
		$data = $this->sessionRead();
		if(isset($data['values']['apiKey'])) $data['values']['apiKey'] = '';
		$this->sessionWrite($data);
	}

	/**
	 * Read AI installer data from the session
	 *
	 * The installer closes the session after each step, so it is reopened here and
	 * closed again immediately.
	 *
	 * @return array
	 *
	 */
	protected function sessionRead() {
		if($this->state !== null) return $this->state;
		if(session_status() !== PHP_SESSION_ACTIVE) @session_start();
		$data = isset($_SESSION[self::sessionKey]) && is_array($_SESSION[self::sessionKey])
			? $_SESSION[self::sessionKey]
			: [];
		session_write_close();
		$this->state = $data;
		return $data;
	}

	/**
	 * State cached in memory for the remainder of the request
	 *
	 * At the final step the installer boots ProcessWire, which starts its own session
	 * and replaces the installer session for the rest of the request. State is therefore
	 * read once before that happens and kept here.
	 *
	 * @var array|null
	 *
	 */
	protected $state = null;

	/**
	 * Read installer session state into memory
	 *
	 * Must be called while the installer session is still the active session, i.e.
	 * before ProcessWire is booted.
	 *
	 */
	public function loadState() {
		$this->state = null;
		$this->sessionRead();
	}

	/**
	 * Write AI installer data to the session
	 *
	 * @param array $data
	 *
	 */
	protected function sessionWrite(array $data) {
		if(session_status() !== PHP_SESSION_ACTIVE) @session_start();
		$_SESSION[self::sessionKey] = $data;
		session_write_close();
	}
}
