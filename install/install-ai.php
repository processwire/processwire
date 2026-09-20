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
 * Provider credentials are kept in the installer session until the end of installation,
 * then written to the AgentTools module configuration and removed from the session. If
 * AgentTools can't be installed, they are kept in the ProcessWire cache for a week instead,
 * for AgentTools to apply when it is installed manually. They are never written to any
 * file by this class.
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
	 * Site profile used by every AI-assisted installation
	 *
	 * The Site Builder is designed around this profile's conventions (its AGENTS.md,
	 * markup regions and image helper), so AI mode does not offer a profile choice.
	 *
	 */
	const profileName = 'site-ai';

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
	 * Cache name for provider settings kept when AgentTools could not be installed
	 *
	 * AgentTools applies and deletes these when it is installed.
	 *
	 */
	const pendingSettingsCacheName = 'AgentTools.installerSettings';

	/**
	 * Seconds to keep those settings (7 days)
	 *
	 */
	const pendingSettingsExpire = 604800;

	/**
	 * Output token limit for the provider test request
	 *
	 * Small, but not 1: a reasoning model spends its budget thinking before it answers, and
	 * some providers fail the request outright when the limit leaves no room for an answer.
	 *
	 */
	const testMaxTokens = 256;

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
		if(!$this->hasProfile()) return false;
		return true;
	}

	/**
	 * Is the AI Starter profile available to install, or already in place as /site/?
	 *
	 * An existing /site/ counts because the profile is renamed to /site/ before the
	 * compatibility check runs, and because a profile placed there by hand is respected.
	 *
	 * @return bool
	 *
	 */
	protected function hasProfile() {
		$root = dirname(__DIR__) . '/'; // this file lives in /install/
		return is_dir($root . self::profileName) || is_dir($root . 'site');
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
		if(!$this->hasProfile()) {
			return "AI-assisted installation requires the AI Starter site profile in /" . self::profileName . "/.";
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
	 * Begin a new installation, discarding any AI state from a previous one
	 *
	 * The installer session outlives an installation, so without this a second install in
	 * the same browser would reuse the previous provider settings and skip the provider step.
	 *
	 * @param bool $enabled Whether the new installation is AI-assisted
	 *
	 */
	public function reset($enabled) {
		$this->sessionWrite(['enabled' => (bool) $enabled]);
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
	 * Render the AI-assisted option description on the welcome screen
	 *
	 * Called from Installer::welcome() before the buttons, so that the standard and
	 * AI-assisted buttons appear together at the bottom of the screen.
	 *
	 */
	public function welcomeText() {

		$installer = $this->installer;

		if(!$this->isAvailable()) {
			$installer->p(htmlentities($this->getUnavailableReason(), ENT_QUOTES, 'UTF-8'), 'pwi-detail');
			return;
		}

		$installer->p(
			"You can install ProcessWire the standard way, or let an AI agent build your site for you. " .
			"For AI-assisted installation you'll need an API key from an AI provider such as Anthropic, " .
			"or any OpenAI-compatible provider. After installation, you'll be asked what you would like " .
			"to build, and an AI agent will create the fields, templates, pages and files for your site."
		);
	}

	/**
	 * Render the AI-assisted install button on the welcome screen
	 *
	 * Called from Installer::welcome() after the Standard Install button.
	 *
	 */
	public function welcomeButton() {
		if(!$this->isAvailable()) return;
		$this->installer->btn("AI-Assisted Install", ['name' => 'step_ai', 'value' => '0', 'icon' => 'magic']);
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
			"Your key is kept in this installer session until the end of installation, " .
			"then saved to the AgentTools module configuration."
		);

		if($error) $installer->err($error);

		$installer->sectionStart('fa-plug AI Provider');

		// all four fields share one width, in two rows: format and model, then endpoint and key
		$width = 320;

		$installer->select('ai_provider', 'Request format', $values['provider'], [
			self::providerAnthropic => 'Anthropic (Claude)',
			self::providerOpenAI => 'OpenAI-compatible',
		], $width);

		$installer->input('ai_model', 'Model ID (e.g. claude-opus-5)', $values['model'], ['width' => $width]);
		$installer->clear();

		$installer->input('ai_endpoint', 'Endpoint URL', $values['endpoint'], ['width' => $width, 'required' => false]);
		$savedKey = $this->getEnteredKey() !== '';
		$installer->input('ai_api_key', $savedKey ? 'API key (blank keeps the one you entered)' : 'API key', '', [
			'type' => 'password',
			'width' => $width,
			'required' => !$savedKey,
		]);
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

		$installer->p("Continuing tests your key and downloads the AgentTools module (about 2 MB).", 'detail');

		$installer->btn('Test key and continue', ['value' => self::stepProvider, 'icon' => 'angle-right']);
		// formnovalidate so that the required provider fields do not block skipping
		$installer->btn('Skip AI setup', [
			'value' => 2,
			'icon' => 'angle-right',
			'secondary' => true,
			'name' => 'step_skip_ai',
			'novalidate' => true,
		]);
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
			// retrying: reuse the key entered on the previous attempt
			$values['apiKey'] = $this->getEnteredKey();
		}

		if($values['apiKey'] === '') {
			$this->providerStep($values, 'Please enter your API key.');
			return false;
		}

		if($values['model'] === '') {
			$this->providerStep($values, 'Please enter the model ID to use, for example claude-opus-5.');
			return false;
		}

		if(stripos($values['endpoint'], 'https://') !== 0) {
			$this->providerStep($values, 'The endpoint URL must begin with https://');
			return false;
		}

		$this->setEnteredKey($values['apiKey']);

		$result = $this->testProvider($values);

		if(empty($result['ok'])) {
			$this->providerStep($values, $result['error']);
			return false;
		}

		$this->setValues($values);

		// Download AgentTools now rather than at the end of installation, so that a server
		// that can't download it finds out here, while skipping AI setup is still an option
		$error = $this->prefetchAgentTools();
		if($error !== '') {
			$this->providerStep($values,
				"Your API key works, but the AgentTools module could not be downloaded, and AI-assisted " .
				"installation needs it. $error Try again, or choose Skip AI setup to install ProcessWire without AI."
			);
			return false;
		}

		$host = (string) parse_url($values['endpoint'], PHP_URL_HOST);
		$message = "Successfully connected to model <b>" . $this->entities($values['model']) . "</b>";
		if($host !== '') $message .= " at " . $this->entities($host);
		if($result['model'] !== '' && $result['model'] !== $values['model']) {
			$message .= " (reported by the provider as <b>" . $this->entities($result['model']) . "</b>)";
		}
		$this->connectedMessages = ["$message.", "Downloaded the AgentTools module."];

		return true;
	}

	/**
	 * Get confirmations from the last successful provider step, for display on the next step
	 *
	 * @return array Markup for each confirmation, or empty if the provider step hasn't succeeded in this request
	 *
	 */
	public function getConnectedMessages() {
		return $this->connectedMessages;
	}

	/**
	 * Entity-encode a value for output
	 *
	 * @param string $value
	 * @return string
	 *
	 */
	protected function entities($value) {
		return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Confirmations from the last successful provider step in this request
	 *
	 * @var array
	 *
	 */
	protected $connectedMessages = [];

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
			'messages' => [['role' => 'user', 'content' => 'Hi']],
		];

		if($anthropic) {
			$payload['max_tokens'] = 1;
			$headers = [
				'x-api-key: ' . $values['apiKey'],
				'anthropic-version: 2023-06-01',
				'content-type: application/json',
			];
		} else {
			// Newer OpenAI models reject max_tokens and require max_completion_tokens. Most
			// OpenAI-compatible providers accept either, and those that don't are retried below.
			$payload['max_completion_tokens'] = self::testMaxTokens;
			$headers = [
				'Authorization: Bearer ' . $values['apiKey'],
				'content-type: application/json',
			];
		}

		$headers = array_merge($headers, $this->getSessionHeaders($values['endpoint']));

		$response = $this->httpPost($values['endpoint'], $headers, json_encode($payload));
		$status = (int) $response['status'];

		if($status === 400 && !$anthropic && stripos((string) $response['body'], 'max_completion_tokens') !== false) {
			// an OpenAI-compatible provider that only knows the older parameter name
			unset($payload['max_completion_tokens']);
			$payload['max_tokens'] = self::testMaxTokens;
			$response = $this->httpPost($values['endpoint'], $headers, json_encode($payload));
			$status = (int) $response['status'];
		}

		if($status >= 200 && $status < 300) {
			// Both API formats report the model that answered, which can differ from the one
			// requested when a provider resolves an alias or routes to another model
			$data = json_decode((string) $response['body'], true);
			$model = is_array($data) && isset($data['model']) && is_string($data['model']) ? $data['model'] : '';
			return ['ok' => true, 'error' => '', 'status' => $status, 'model' => $model];
		}

		$message = $this->getResponseError($response['body']);

		if($response['error'] !== '') {
			$error = "Could not reach the provider: " . $this->truncate($response['error']);
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
	 * Truncate and entity-encode a message for display
	 *
	 * @param string $str
	 * @param int $maxLength
	 * @return string Markup
	 *
	 */
	protected function truncate($str, $maxLength = 300) {
		return htmlentities($this->shorten($str, $maxLength), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Collapse whitespace and truncate a message, without encoding it
	 *
	 * For text that will be encoded later, such as exception messages.
	 *
	 * @param string $str
	 * @param int $maxLength
	 * @return string Plain text
	 *
	 */
	protected function shorten($str, $maxLength = 300) {
		$str = trim(preg_replace('/\s+/', ' ', (string) $str));
		if(strlen($str) > $maxLength) $str = substr($str, 0, $maxLength) . '…';
		return $str;
	}

	/**
	 * Send a GET request, optionally saving the response body to a file
	 *
	 * Used before ProcessWire is booted, when WireHttp isn't available. Redirects are
	 * followed, but only to HTTPS URLs when cURL is available.
	 *
	 * @param string $url
	 * @param string $file Save the response body to this file rather than returning it
	 * @return array [ status, body, error ]
	 *
	 */
	protected function httpGet($url, $file = '') {

		$fp = $file !== '' ? @fopen($file, 'wb') : null;
		if($file !== '' && !$fp) return ['status' => 0, 'body' => '', 'error' => 'Unable to write the downloaded file.'];

		if(function_exists('curl_init')) {
			$ch = curl_init($url);
			$options = [
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 5,
				CURLOPT_TIMEOUT => 120,
				CURLOPT_CONNECTTIMEOUT => 10,
			];
			// only follow redirects to HTTPS URLs
			if(defined('CURLOPT_REDIR_PROTOCOLS')) $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
			if($fp) $options[CURLOPT_FILE] = $fp; else $options[CURLOPT_RETURNTRANSFER] = true;
			curl_setopt_array($ch, $options);
			$result = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = $result === false ? curl_error($ch) : '';
			curl_close($ch);
			if($fp) fclose($fp);
			return ['status' => $status, 'body' => $fp || $result === false ? '' : (string) $result, 'error' => $error];
		}

		$context = stream_context_create(['http' => [
			'timeout' => 120,
			'follow_location' => 1,
			'max_redirects' => 5,
			'ignore_errors' => true,
		]]);
		$result = @file_get_contents($url, false, $context);
		$status = 0;
		if(isset($http_response_header) && is_array($http_response_header)) {
			foreach($http_response_header as $header) {
				if(preg_match('!^HTTP/\S+\s+(\d{3})!', $header, $matches)) $status = (int) $matches[1];
			}
		}
		if($fp) {
			if($result !== false) fwrite($fp, $result);
			fclose($fp);
		}
		return [
			'status' => $status,
			'body' => $fp || $result === false ? '' : (string) $result,
			'error' => $result === false ? 'The request failed.' : '',
		];
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
			"Site building works best with a recent, capable coding model. We recommend a current " .
			"<b>OpenAI GPT</b> model (OpenAI-compatible format), or <b>Claude</b> Sonnet, Opus or " .
			"Fable (Anthropic format). Any other recent and capable model available through either " .
			"format may also work, such as GLM, DeepSeek, Kimi, MiniMax or Qwen. You can change the " .
			"model later in the AgentTools module settings.";
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

		$stage = 'download';

		try {
			if(!$modules->isInstalled('AgentTools')) {
				// normally downloaded at the provider step; download now only if that copy is missing
				$file = $this->getPrefetchFile();
				if(!$this->isZipFile($file)) $file = $this->downloadAgentTools($wire);
				if($file === '') throw new \Exception("Unable to download the AgentTools module.");
				$stage = 'extract';
				$this->extractAgentTools($wire, $file);
				@unlink($file);
				$modules->refresh();
			}

			$stage = 'install';
			if(!$modules->isInstalled('AgentTools')) $modules->install('AgentTools');
			if(!$modules->isInstalled('AgentTools')) throw new \Exception("Unable to install the AgentTools module.");

			$installer->ok("Installed the AgentTools module");

			$stage = 'configure';
			$this->configureAgentTools($wire, $values);
			$installer->ok("Saved your AI provider settings to AgentTools");

			$this->clearCredentials();

		} catch(\Throwable $e) {
			$this->discardPrefetch();
			$this->installAgentToolsFailed($wire, $stage, $e->getMessage(), $values);
			$installer->sectionStop();
			$this->clearCredentials();
			return false;
		}

		$installer->sectionStop();

		return true;
	}

	/**
	 * Explain an AgentTools installation failure, and keep the provider settings for later
	 *
	 * ProcessWire itself is installed at this point, so the failure only affects the AI
	 * Site Builder. Unless AgentTools was already installed, the provider settings are kept
	 * in the cache for a week, for AgentTools to apply when it is installed manually, so the
	 * user doesn't have to enter them again.
	 *
	 * @param ProcessWire $wire
	 * @param string $stage One of: download, extract, install, configure
	 * @param string $error Technical error message
	 * @param array $values Provider settings
	 *
	 */
	protected function installAgentToolsFailed($wire, $stage, $error, array $values) {

		$installer = $this->installer;

		$reasons = [
			'download' => "AgentTools could not be downloaded. This server may not have access to the internet, " .
				"or it may have been a temporary network problem.",
			'extract' => "AgentTools was downloaded but could not be unpacked into /site/modules/.",
			'install' => "AgentTools was downloaded but could not be installed.",
			'configure' => "AgentTools was installed, but your AI provider settings could not be saved to it.",
		];

		$installer->err(isset($reasons[$stage]) ? $reasons[$stage] : $reasons['install']);
		$installer->p("Details: " . $this->truncate($error), 'detail');

		if($stage === 'configure') {
			$installer->p(
				"ProcessWire is installed and ready to use. To build your site with AI, enter your " .
				"AI provider settings in the AgentTools module settings.",
				'detail'
			);
			return;
		}

		$kept = false;
		try {
			$cache = $wire->wire('cache');
			if($cache) $kept = (bool) $cache->save(self::pendingSettingsCacheName, $this->getAgentToolsSettings($values), self::pendingSettingsExpire);
		} catch(\Throwable $e) {
			$kept = false;
		}

		$installer->p(
			"ProcessWire is installed and ready to use. To build your site with AI, install the " .
			"<a target='_blank' href='https://processwire.com/modules/agent-tools/'>AgentTools</a> " .
			"module from Modules &gt; New in your admin. " .
			($kept
				? "Your AI provider settings have been kept, and will be applied automatically if you install it within the next 7 days."
				: "Then enter your AI provider settings in its module settings."),
			'detail'
		);
	}

	/**
	 * Download AgentTools ahead of time, during the provider step
	 *
	 * ProcessWire isn't booted yet, so this can't use WireHttp or $config. It reads the
	 * modules directory settings from the config files and uses plain HTTP requests. The ZIP
	 * is saved where installAgentTools() looks for it at the end of installation.
	 *
	 * @return string Error message, or blank on success
	 *
	 */
	public function prefetchAgentTools() {

		$file = $this->getPrefetchFile();
		$dir = dirname($file);

		if(!is_dir($dir)) @mkdir($dir, 0755, true);
		if(!is_dir($dir) || !is_writable($dir)) return "The directory /site/assets/cache/ is not writable.";

		list($serviceUrl, $serviceKey) = $this->getModuleService();
		$url = rtrim($serviceUrl, '/') . '/AgentTools/?apikey=' . $serviceKey;
		if(stripos($url, 'https://') !== 0) return "The modules directory URL must use HTTPS.";

		$response = $this->httpGet($url);
		$data = $response['status'] === 200 ? json_decode($response['body'], true) : null;
		if(!is_array($data)) {
			return "The ProcessWire modules directory could not be reached" .
				($response['error'] !== '' ? " (" . $this->truncate($response['error']) . ")." : ".");
		}

		$zipUrl = (string) ($data['download_url'] ?? '');
		if(stripos($zipUrl, 'https://') !== 0) return "The modules directory has no secure download URL for AgentTools.";

		$response = $this->httpGet($zipUrl, $file);
		if($response['status'] !== 200 || !$this->isZipFile($file)) {
			@unlink($file);
			return "The download from " . htmlentities((string) parse_url($zipUrl, PHP_URL_HOST), ENT_QUOTES, 'UTF-8') .
				" failed" . ($response['error'] !== '' ? " (" . $this->truncate($response['error']) . ")." : ".");
		}

		return '';
	}

	/**
	 * Remove a downloaded AgentTools ZIP that won't be used, e.g. when AI setup is skipped
	 *
	 */
	public function discardPrefetch() {
		$file = $this->getPrefetchFile();
		if(is_file($file)) @unlink($file);
	}

	/**
	 * Path to the AgentTools ZIP downloaded at the provider step
	 *
	 * This is the same file downloadAgentTools() writes at the end of installation.
	 *
	 * @return string
	 *
	 */
	protected function getPrefetchFile() {
		return dirname(__DIR__) . '/site/assets/cache/AgentTools-install.zip'; // this file lives in /install/
	}

	/**
	 * Is the given file a complete, readable ZIP archive?
	 *
	 * @param string $file
	 * @return bool
	 *
	 */
	protected function isZipFile($file) {
		if(!is_file($file) || filesize($file) < 1000 || !class_exists('\ZipArchive')) return false;
		$zip = new \ZipArchive();
		if($zip->open($file) !== true) return false;
		$zip->close();
		return true;
	}

	/**
	 * Get the modules directory URL and key before ProcessWire is booted
	 *
	 * Reads them from /wire/config.php, and from /site/config.php when it overrides them,
	 * falling back to the known defaults.
	 *
	 * @return array [ url, key ]
	 *
	 */
	protected function getModuleService() {
		$url = 'https://modules.processwire.com/export-json/';
		$key = 'pw301';
		$root = dirname(__DIR__) . '/';
		foreach([$root . 'wire/config.php', $root . 'site/config.php'] as $configFile) {
			$source = is_file($configFile) ? (string) @file_get_contents($configFile) : '';
			if(preg_match('/^\s*\$config->moduleServiceURL\s*=\s*[\'"]([^\'"]+)[\'"]/m', $source, $m)) $url = $m[1];
			if(preg_match('/^\s*\$config->moduleServiceKey\s*=\s*[\'"]([^\'"]+)[\'"]/m', $source, $m)) $key = $m[1];
		}
		return [$url, $key];
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
		if(($data['status'] ?? '') === 'error') throw new \Exception("Modules directory: " . $this->shorten($data['error'] ?? 'unknown error'));

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

		$settings = $this->getAgentToolsSettings($values);

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
	 * Get provider settings in the form AgentTools::configurePrimaryAgent() accepts
	 *
	 * @param array $values Provider values from the installer
	 * @return array
	 *
	 */
	protected function getAgentToolsSettings(array $values) {
		return [
			'provider' => $values['provider'],
			'apiKey' => $values['apiKey'],
			'model' => $values['model'],
			'endpoint' => $values['endpoint'],
			'label' => 'Installed with ProcessWire',
		];
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
	 * Get the API key entered on a previous attempt in this installer session
	 *
	 * Kept so that a retry after a failed test or download doesn't need it typed again.
	 * Not proof of a working key: only setValues() records that.
	 *
	 * @return string
	 *
	 */
	protected function getEnteredKey() {
		$values = $this->getValues();
		if($values['apiKey'] !== '') return $values['apiKey'];
		$data = $this->sessionRead();
		return isset($data['enteredKey']) ? (string) $data['enteredKey'] : '';
	}

	/**
	 * Remember the API key just entered, before it is known to work
	 *
	 * @param string $key
	 *
	 */
	protected function setEnteredKey($key) {
		$data = $this->sessionRead();
		$data['enteredKey'] = (string) $key;
		$this->sessionWrite($data);
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
	 * Remove the provider settings, including the API key, from the installer session
	 *
	 */
	public function clearCredentials() {
		$data = $this->sessionRead();
		unset($data['values'], $data['enteredKey']);
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
		$restore = $this->sessionOpen();
		$data = isset($_SESSION[self::sessionKey]) && is_array($_SESSION[self::sessionKey])
			? $_SESSION[self::sessionKey]
			: [];
		$this->sessionClose($restore);
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
		$name = session_name();
		$id = (string) session_id();
		if($id === '' && isset($_COOKIE[$name]) && is_string($_COOKIE[$name])) $id = $_COOKIE[$name];
		$this->installerSession = [$name, $id, (string) session_save_path()];
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
		$restore = $this->sessionOpen();
		$_SESSION[self::sessionKey] = $data;
		$this->sessionClose($restore);
		$this->state = $data; // keep the request cache in step, so a later read sees this write
	}

	/**
	 * Name, ID and save path of the installer's own session, captured by loadState()
	 * before ProcessWire boots
	 *
	 * @var array
	 *
	 */
	protected $installerSession = ['', '', ''];

	/**
	 * Open the installer's own session, closing any other session that is active
	 *
	 * At the final step ProcessWire has started its own session, and moved PHP's session
	 * save path to /site/assets/sessions/, so opening "the" session would reach ProcessWire's
	 * instead, and a write meant for the installer session would land in the wrong place. This
	 * closes ProcessWire's session, opens the installer session by name, ID and save path, and
	 * returns what sessionClose() needs to restore ProcessWire's session afterwards.
	 *
	 * @return array|null Name, ID and save path of the session to restore, or null if none
	 *
	 */
	protected function sessionOpen() {
		list($name, $id, $path) = $this->installerSession;
		$restore = null;
		if(session_status() === PHP_SESSION_ACTIVE) {
			if($name === '' || (session_name() === $name && session_id() === $id)) return null;
			$restore = [session_name(), session_id(), (string) session_save_path(), ini_get('session.use_cookies')];
			session_write_close();
			// the browser already has the installer session cookie, so don't send it again
			ini_set('session.use_cookies', '0');
		}
		if($name !== '' && $id !== '') {
			if($path !== '') session_save_path($path);
			session_name($name);
			session_id($id);
		}
		@session_start();
		return $restore;
	}

	/**
	 * Close the installer session, and reopen the session sessionOpen() closed, if any
	 *
	 * @param array|null $restore Return value of sessionOpen()
	 *
	 */
	protected function sessionClose($restore) {
		session_write_close();
		if($restore === null) return;
		// session settings can only be changed while no session is active, so restore
		// use_cookies before restarting ProcessWire's session (which re-sends its cookie)
		ini_set('session.use_cookies', (string) $restore[3]);
		session_save_path($restore[2]);
		session_name($restore[0]);
		session_id($restore[1]);
		@session_start();
	}
}
