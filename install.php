<?php namespace ProcessWire;

/**
 * ProcessWire Installer
 *
 * Because this installer runs before PW is installed, it is largely self contained.
 * It's a quick-n-simple single purpose script that's designed to run once, and it should be deleted after installation.
 * This file self-executes using code found at the bottom of the file, under the Installer class. 
 *
 * Note that it creates this file once installation is completed: /site/assets/installed.php
 * If that file exists, the installer will not run. So if you need to re-run this installer for any
 * reason, then you'll want to delete that file. This was implemented just in case someone doesn't delete the installer.
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 * 
 * @todo 3.0.190: provide option for command-line options to install
 * @todo have installer set session name
 * 
 */

define("PROCESSWIRE_INSTALL", "3.x"); 

/**
 * class Installer
 *
 * Self contained class to install ProcessWire 3.x
 *
 */
class Installer {

	/**
	 * Whether or not we force installed files to be copied. 
	 *
	 * If false, we attempt a faster rename of directories instead.
	 *
	 */
	const FORCE_COPY = true; 

	/**
	 * Replace existing database tables if already present?
	 *
	 */
	const REPLACE_DB = true; 

	/**
	 * Minimum required PHP version to install ProcessWire
	 *
	 */
	const MIN_REQUIRED_PHP_VERSION = '7.1.0';

	/**
	 * Test mode for installer development, non destructive
	 *
	 */
	const TEST_MODE = false;

	/**
	 * Default profile name
	 * 
	 */
	const DEFAULT_PROFILE = 'site-blank';

	/**
	 * File permissions, determined in the dbConfig function
	 *
	 * Below are worst case scenario, last resort defaults
	 *
	 */
	protected $chmodDir = "0777";
	protected $chmodFile = "0666";

	/**
	 * Number of errors that occurred during the request
	 *
	 */
	protected $numErrors = 0;

	/**
	 * True when we are in a section
	 * 
	 * @var bool
	 * 
	 */
	protected $inSection = false;

	/**
	 * Execution controller
	 *
	 */
	public function execute() {
		
		if(self::TEST_MODE) {
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
		}

		// these two vars used by install-head.inc
		$title = "ProcessWire " . PROCESSWIRE_INSTALL . " Installer";
		$formAction = "./install.php";
		$step = $this->post('step');
		
		if($step === '5') require('./index.php');
		
		require("./wire/modules/AdminTheme/AdminThemeUikit/install-head.inc");

		if($step === null) {
			$this->welcome();
		} else {
			$step = (int) $step;
			switch($step) {
				case 0: $this->initProfile(); break;
				case 1: $this->compatibilityCheck(); break;
				case 2: $this->dbConfig();  break;
				case 4: $this->dbSaveConfig();  break;
				case 5: 
					/** @var ProcessWire $wire */
					$wire->modules->refresh();
					$this->adminAccountSave($wire);
					break;
				default:
					$this->welcome();
			} 
		}

		require("./wire/modules/AdminTheme/AdminThemeUikit/install-foot.inc"); 
	}


	/**
	 * Welcome/Intro screen
	 *
	 */
	protected function welcome() {
		$this->h("Welcome. This tool will guide you through the installation process."); 
		$this->p(
			"Thanks for choosing ProcessWire! " . 
			"If you downloaded this copy of ProcessWire from somewhere other than " . 
			"<a target='_blank' href='https://processwire.com/'>processwire.com</a> or " . 
			"<a href='https://github.com/processwire/processwire' target='_blank'>our GitHub page</a>, " . 
			"please download a fresh copy before installing. " . 
			"If you need help or have questions during installation, please stop by our " . 
			"<a href='https://processwire.com/talk/' target='_blank'>support board</a> and we'll be glad to help."
		);
		$this->btn("Get Started", array('icon' => 'sign-in')); 
	}


	/**
	 * Check if the given function $name exists and report OK or fail with $label
	 * 
	 * @param string $name
	 * @param string $label
	 *
	 */
	protected function checkFunction($name, $label) {
		if(function_exists($name)) {
			$this->ok("$label");
		} else {
			$this->err("Fail: $label");
		}
	}

	/**
	 * Find all profile directories (site-*) in the current dir and return info array for each
	 * 
	 * @return array
	 * 
	 */
	protected function findProfiles() {
		$profiles = array(
			//'site-blank' => null,
			//'site-default' => null, // preferred starting order
			//'site-beginner' => null,
			//'site-languages' => null, 
		); 
		$dirTests = array(
			'install', 
			'templates',
			'assets',
		);
		$fileTests = array(
			'config.php',
			'templates/admin.php',
			'install/install.sql',
		);
		foreach(new \DirectoryIterator(dirname(__FILE__)) as $dir) {
			if($dir->isDot() || !$dir->isDir()) continue; 
			$name = $dir->getBasename();
			$path = rtrim($dir->getPathname(), '/') . '/';
			if(strpos($name, 'site-') !== 0 && $name !== 'site') continue;
			$passed = true;
			foreach($dirTests as $test) if(!is_dir($path . $test)) $passed = false;
			foreach($fileTests as $test) if(!file_exists($path . $test)) $passed = false; 
			if(!$passed) continue;
			$profile = array('name' => str_replace('site-', '', $name));
			$infoFile = $path . 'install/info.php';
			if(file_exists($infoFile)) {
				/** @noinspection PhpIncludeInspection */
				include($infoFile);
				if(isset($info) && is_array($info)) {
					$profile = array_merge($profile, $info); 
				}
			}
			$profiles[$name] = $profile;
		}
		// remove any preferred starting order profiles that weren't present
		foreach($profiles as $name => $profile) {
			if(is_null($profile)) unset($profiles[$name]); 	
		}
		return $profiles; 
	}

	/**
	 * Select profile
	 * 
	 */
	protected function selectProfile() {
		$options = '';
		$out = '';
		$profiles = $this->findProfiles();
		if(!count($profiles)) $this->err("No profiles found!");
		
		foreach($profiles as $name => $profile) {
			$title = empty($profile['title']) ? ucfirst($profile['name']) : $profile['title'];
			$options .= "<option value='$name'>$title</option>"; 
			$out .= "<div class='profile-preview' id='$name' style='display: none;'>";
			if(!empty($profile['summary'])) $out .= "<p>$profile[summary]</p>";
				else $out .= "<p class='detail'>No summary.</p>";
			if(!empty($profile['screenshot'])) {
				$file = $profile['screenshot'];
				if(strpos($file, '/') === false) $file = "$name/install/$file";
				$out .= "<p><img src='$file' alt='$name screenshot' style='max-width: 100%;' /></p>";
			} else {
				$out .= "<p class='detail'>No screenshot.</p>";
			}
			$out .= "</div>";
		}
	
		$path = rtrim(str_replace('install.php', '', $_SERVER['REQUEST_URI']), '/') . '/';
		$url = htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . 'site-name/';
		$angleUpIcon = $this->icon('angle-up', false);
			
		echo "
			<p>
			A site installation profile is a ready-to-use and modify site for ProcessWire.
			</p>
			<p>
			If you want something other than the included “blank” profile, please 
			<a target='_blank' href='https://processwire.com/download/site-profiles/'>download another site profile</a>, 
			unzip and place its files in <code>$url</code> (replacing <code>name</code> with the profile name) 
			and click the “Refresh” button to make it available here.
			</p> 
			<p style='width: 240px;'>
				<select class='uk-select' name='profile' id='select-profile'>
					<option value=''>Installation Profiles</option>
					$options
				</select>
			</p>
			<p class='detail'>
				$angleUpIcon
				Select an installation profile to see more information.
			</p>
			$out
			<script type='text/javascript'>
			$('#select-profile').change(function() {
				$('.profile-preview').hide();	
				$('#' + $(this).val()).fadeIn('fast');
			}).change();
			</script>
		";
		
	}
	
	/**
	 * Step 1a: Determine profile
	 *
	 */
	protected function initProfile() {
	
		$this->h('Site Installation Profile', 'building-o'); 
		
		if(is_file("./site/install/install.sql")) {
			$this->alertOk("Found installation profile in /site/install/");

		} else if(is_dir("./site/")) {
			$this->alertOk("Found /site/ — already installed? ");

		} else if($this->post('profile') && $this->post('step') !== '000') {
			
			$profiles = $this->findProfiles();
			$profile = $this->post('profile', 'name'); 
			if(empty($profile) || !isset($profiles[$profile]) || !is_dir(dirname(__FILE__) . "/$profile")) {
				$this->alertErr("Profile not found");
				$this->selectProfile();
				$this->btnContinue();
				return;
			}
			
			if(@rename("./$profile", "./site")) {
				$this->alertOk("Renamed /$profile => /site");
			} else {
				$this->alertErr("File system is not writable by this installer. Before continuing, please rename '/$profile' to '/site'");
				$this->btnContinue();
				return;
			}

		} else {
			if($this->post('step') === '000') $this->alertOk('Refreshed profiles');
			$this->selectProfile();
			$this->btn('Refresh', array('value' => '000', 'icon' => 'refresh', 'secondary' => true, 'float' => true));
			$this->btnContinue();
			return;
		}
		
		$this->compatibilityCheck();
	}

	/**
	 * Step 1b: Check for ProcessWire compatibility
	 *
	 */
	protected function compatibilityCheck() { 

		$this->sectionStart('fa-gears Compatibility Check');
		
		if(version_compare(PHP_VERSION, self::MIN_REQUIRED_PHP_VERSION) >= 0) {
			$this->ok("PHP version " . PHP_VERSION);
		} else {
			$this->err("ProcessWire requires PHP version " . self::MIN_REQUIRED_PHP_VERSION . " or newer. You are running PHP " . PHP_VERSION);
		}
		
		if(extension_loaded('pdo_mysql')) {
			$this->ok("PDO (mysql) database"); 
		} else {
			$this->err("PDO (pdo_mysql) is required (for MySQL database)"); 
		}

		if(self::TEST_MODE) {
			$this->err("Example error message for test mode");
			$this->warn("Example warning message for test mode"); 
		}

		$this->checkFunction("filter_var", "Filter functions (filter_var)");
		$this->checkFunction("mysqli_connect", "MySQLi (not used by core, but may still be used by some older 3rd party modules)");
		$this->checkFunction("imagecreatetruecolor", "GD 2.0 or newer"); 
		$this->checkFunction("json_encode", "JSON support");
		$this->checkFunction("preg_match", "PCRE support"); 
		$this->checkFunction("ctype_digit", "CTYPE support");
		$this->checkFunction("iconv", "ICONV support"); 
		$this->checkFunction("session_save_path", "SESSION support"); 
		$this->checkFunction("hash", "HASH support"); 
		$this->checkFunction("spl_autoload_register", "SPL support"); 

		if(function_exists('apache_get_modules')) {
			if(in_array('mod_rewrite', apache_get_modules())) $this->ok("Found Apache module: mod_rewrite"); 
				else $this->err("Apache 'mod_rewrite' module does not appear to be installed and is required by ProcessWire."); 
		} else {
			// apache_get_modules doesn't work on a cgi installation.
			// check for environment var set in htaccess file, as submitted by jmarjie. 
			$mod_rewrite = getenv('HTTP_MOD_REWRITE') == 'On' || getenv('REDIRECT_HTTP_MOD_REWRITE') == 'On' ? true : false;
			if($mod_rewrite) {
				$this->ok("Found Apache module (cgi): mod_rewrite");
			} else {
				$this->err(
					"Unable to determine if Apache mod_rewrite (required by ProcessWire) is installed. " . 
					"On some servers, we may not be able to detect it until your .htaccess file is place. " . 
					"Please click the 'check again' button at the bottom of this screen, if you haven't already."
				); 
			}
		}
		
		if(class_exists('\ZipArchive')) {
			$this->ok("ZipArchive support"); 
		} else {
			$this->warn("ZipArchive support was not found. This is recommended, but not required to complete installation."); 
		}
		
		$memoryLimit = $this->getMemoryLimit('M');
		$memoryLimitLabel = "PHP memory_limit is set to $memoryLimit MB";
		if($memoryLimit < 64) {
			$this->err("$memoryLimitLabel - At least 64 MB is strongly recommended but 128 MB or more is best");
		} else if($memoryLimit < 128) {
			$this->warn("$memoryLimitLabel - OK to continue, but at least 128 MB is recommended"); 
		} else {
			$this->ok("$memoryLimitLabel"); 
		}
	
		$dirs = array(
			// directory => required?
			'./site/assets/' => true,
			'./site/modules/' => false, 
		);
		foreach($dirs as $dir => $required) {
			$d = ltrim($dir, '.'); 
			if(!file_exists($dir)) {
				$this->err("Directory $d does not exist! Please create this and make it writable before continuing."); 
			} else if(is_writable($dir)) {
				$this->ok("$d is writable");
			} else if($required) {
				$this->err("Directory $d must be writable. Please adjust the server permissions before continuing.");
			} else {
				$this->warn("Consider making directory $d writable, at least during development."); 
			}
		}
	
		if(file_exists("./site/config.php")) {
			if(is_writable("./site/config.php")) {
				$this->ok("/site/config.php is writable");
			} else {
				$this->err("/site/config.php must be writable during installation. Please adjust the server permissions before continuing.");
			}
		} else {
			$this->err("Site profile is missing a /site/config.php file.");
		}
		
		if(!is_file("./.htaccess") || !is_readable("./.htaccess")) {
			if(@rename("./htaccess.txt", "./.htaccess")) {
				$this->ok("Installed .htaccess");
			} else {
				$this->err("/.htaccess doesn't exist. Before continuing, you should rename the included htaccess.txt file to be .htaccess (with the period in front of it, and no '.txt' at the end).");
			}

		} else if(!strpos(file_get_contents("./.htaccess"), "PROCESSWIRE")) {
			$this->err("/.htaccess file exists, but is not for ProcessWire. Please overwrite or combine it with the provided /htaccess.txt file (i.e. rename /htaccess.txt to /.htaccess, with the period in front)."); 

		} else {
			$this->ok(".htaccess looks good"); 
		}
		$this->sectionStop();

		if($this->numErrors) {
			$this->p("One or more errors were found above. We recommend you correct these issues before proceeding or <a href='https://processwire.com/talk/'>contact ProcessWire support</a> if you have questions or think the error is incorrect. But if you want to proceed anyway, click Continue below.");
			$this->btn("Check Again", array('value' => 1, 'icon' => 'refresh', 'float' => true)); 
			$this->btn("Continue to Next Step", array('value' => 2, 'icon' => 'angle-right', 'secondary' => true)); 
		} else {
			$this->btn("Continue to Next Step", array('value' => 2, 'icon' => 'angle-right')); 
		}
	}

	/**
	 * Step 2: Configure the database and file permission settings
	 * 
	 * @param array $values
	 * @param int $hasNumTables
	 *
	 */
	protected function dbConfig($values = array(), $hasNumTables = 0) {

		if(!is_file("./site/install/install.sql")) die(
			"There is no installation profile in /site/. Please place one there before continuing. " . 
			"You can get it at https://processwire.com/download/"
		);
		
		if($hasNumTables) {
			$this->sectionStart('fa-database Existing tables action'); 
			// select($name, $label, $value, array $options) {
			$this->p("What would you like to do with the existing database tables that are present?");
			$this->select('dbTablesAction', '', 0, array(
				'0' => 	"Click to select tables action",
				'ignore' => "Ignore tables*", 
				'remove' => "Remove tables", 
			), 0);
			$this->p("*When choosing “Ignore tables”, existing tables having the same name as newly imported tables will still be deleted.", 'detail'); 
			$this->sectionStop();
		}

		$this->sectionStart('fa-database MySQL Database'); 
		$this->p(
			"Please specify a MySQL 5.x+ database and user account on your server. If the database does not exist, " . 
			"we will attempt to create it. If the database already exists, the user account should have full read, " . 
			"write and delete permissions on the database (recommended permissions are select, insert, update, delete, " . 
			"create, alter, index, drop, create temporary tables, and lock tables)." 
		); 

		if(!isset($values['dbName'])) $values['dbName'] = '';
		// @todo: are there PDO equivalents for the ini_get()s below?
		if(!isset($values['dbHost'])) $values['dbHost'] = ini_get("mysqli.default_host"); 
		if(!isset($values['dbPort'])) $values['dbPort'] = ini_get("mysqli.default_port"); 
		if(!isset($values['dbUser'])) $values['dbUser'] = ini_get("mysqli.default_user"); 
		if(!isset($values['dbPass'])) $values['dbPass'] = ini_get("mysqli.default_pw");
		if(!isset($values['dbEngine'])) $values['dbEngine'] = 'InnoDB';
		if(!isset($values['dbSocket'])) $values['dbSocket'] = ini_get("mysqli.default_socket");
		if(!isset($values['dbCon'])) $values['dbCon'] = 'Hostname';

		if(!$values['dbHost']) $values['dbHost'] = 'localhost';
		if(!$values['dbPort']) $values['dbPort'] = 3306;
		if(empty($values['dbCharset'])) $values['dbCharset'] = 'utf8mb4';
		if($values['dbCharset'] != 'utf8mb4') $values['dbCharset'] = 'utf8';
		if($values['dbEngine'] != 'InnoDB') $values['dbEngine'] = 'MyISAM';

		foreach($values as $key => $value) {
			if(strpos($key, 'chmod') === 0) {
				$values[$key] = (int) $value;
			} else if($key != 'httpHosts') {
				$values[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); 
			}
		}
		
		$this->input('dbName', 'DB Name', $values['dbName']); 
		$this->input('dbUser', 'DB User', $values['dbUser']);
		$this->input('dbPass', 'DB Pass', $values['dbPass'], array('type' => 'password', 'required' => false));
		$this->select('dbCon', 'Connection', $values['dbCon'], array('Hostname', 'Socket'));
		$this->clear();
		
		$this->input('dbHost', 'DB Host', $values['dbHost']);
		$this->input('dbPort', 'DB Port', $values['dbPort']);
		$this->input('dbSocket', 'DB Socket', $values['dbSocket'], array('width' => 300));
		$this->select('dbCharset', 'DB Charset', $values['dbCharset'], array('utf8mb4', 'utf8'));
		$this->select('dbEngine', 'DB Engine', $values['dbEngine'], array('InnoDB', 'MyISAM'));
		$this->clear();
	
		// automatic required states for host, port and socket
		echo "
			<script>
				jQuery(document).ready(function($) {
					let ho = $('input[name=dbHost]'), po = $('input[name=dbPort]'), 
						so = $('input[name=dbSocket]'), co = $('select[name=dbCon]');
					co.on('change', function() {
						if(co.val() === 'Hostname') {
							ho.prop('required', true).closest('p').show();
							po.prop('required', true).closest('p').show();
							so.prop('required', false).closest('p').hide();
						} else {
							ho.prop('required', false).closest('p').hide();
							po.prop('required', false).closest('p').hide();
							so.prop('required', true).closest('p').show();
						}
					}).change();
				});
			</script>
		";
	
		$this->p(
			"The DB Engine option “InnoDB” requires MySQL 5.6.4 or newer.", 
			array('class' => 'detail', 'style' => 'margin-top:0')
		);
		$this->sectionStop();

		$cgi = false;
		$defaults = array();

		if(is_writable(__FILE__)) {
			$defaults['chmodDir'] = "755";
			$defaults['chmodFile'] = "644";
			$cgi = true;
		} else {
			$defaults['chmodDir'] = "777";
			$defaults['chmodFile'] = "666";
		}

		$timezone = isset($values['timezone']) ? $values['timezone'] : date_default_timezone_get(); 
		$timezones = $this->timezones();
		if(!$timezone || !in_array($timezone, $timezones)) {
			$timezone = ini_get('date.timezone'); 
			if(!$timezone || !in_array($timezone, $timezones)) $timezone = 'America/New_York';
		}

		$defaults['timezone'] = $timezone; 
		$defaults['httpHosts'] = strtolower(filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_URL));

		if(strpos($defaults['httpHosts'], 'www.') === 0) {
			$defaults['httpHosts'] .= "\n" . substr($defaults['httpHosts'], 4); 
		} else if(substr_count($defaults['httpHosts'], '.') == 1) {
			$defaults['httpHosts'] .= "\n" . "www.$defaults[httpHosts]";
		}
		if($_SERVER['SERVER_NAME'] && $_SERVER['SERVER_NAME'] != $_SERVER['HTTP_HOST']) {
			$defaults['httpHosts'] .= "\n" . $_SERVER['SERVER_NAME']; 
		}
		
		if(isset($values['httpHosts']) && is_array($values['httpHosts'])) $values['httpHosts'] = implode("\n", $values['httpHosts']); 

		$values = array_merge($defaults, $values); 

		$this->sectionStart('fa-globe Time Zone');
		$this->p('The time zone selection should be consistent with the time zone of the web server you are installing to.'); 
		$this->selectTimezone($values['timezone']); 
		$this->sectionStop();

		$this->sectionStart("fa-key File Permissions"); 
		$this->p(
			"When ProcessWire creates directories or files, it assigns permissions to them. " . 
			"Enter the most restrictive permissions possible that give ProcessWire (and you) read and write access to the web server (Apache). " . 
			"The safest setting to use varies from server to server. " . 
			"If you are not on a dedicated or private server, or are in any kind of shared environment, you may want to contact your web host to advise on what are the best permissions to use in your environment. " . 
			"<a target='_blank' href='https://processwire.com/docs/security/file-permissions/'>Read more about securing file permissions</a>"
		);

		$this->p("Permissions must be 3 digits each. Should you opt to use the defaults provided, you can also adjust these permissions later if desired by editing <u>/site/config.php</u>.", "detail");

		$this->input('chmodDir', 'Directories', $values['chmodDir']); 
		$this->input('chmodFile', 'Files', $values['chmodFile'], array('clear' => true)); 

		if($cgi) {
			$this->p(
				"We detected that this file (install.php) is writable. That means Apache may be running as your user account. Given that, we populated the permissions above (755 &amp; 644) as possible starting point.", 
				array('class' => 'detail', 'style' => 'margin-top:0')
			);
		} else {
			$this->p(
				"WARNING: 777 and 666 permissions mean that directories and files are readable and writable to everyone on the server (and thus not particularly safe). If in any kind of shared hosting environment, please consult your web host for their recommended permission settings for Apache readable/writable directories and files before proceeding. " . 
				"<a target='_blank' href='https://processwire.com/docs/security/file-permissions/'>More</a>",
				array('class' => 'detail', 'style' => 'margin-top:0')
			);
		}
		
		$this->sectionStop();

		$this->sectionStart('fa-server HTTP Host Names');
		$this->p(
			"What host names will this installation run on now and in the future? Please enter one host per line. " . 
			"You can also modify this setting later by editing the <code>\$config->httpHosts</code> setting in the <u>/site/config.php</u> file."
		);
		$rows = substr_count($values['httpHosts'], "\n") + 2; 
		$this->textarea('httpHosts', '', $values['httpHosts'], $rows); 
		$this->sectionStop();
		
		$this->sectionStart('fa-bug Debug mode?');
		$this->p(
			"When debug mode is enabled, errors and exceptions are visible in ProcessWire’s output. This is helpful when developing a website or testing ProcessWire. " . 
			"When debug mode is NOT enabled, fatal errors/exceptions halt the request with an ambiguous http 500 error, and non-fatal errors are not shown. " . 
			"Regardless of debug mode, fatal errors are always logged and always visible to superusers. " . 
			"Debug mode should not be enabled for live or production sites, but at this stage (installation) it is worthwhile to have it enabled. " 
		);
		$noChecked = empty($values['debugMode']) ? "checked='checked'" : "";
		$yesChecked = empty($noChecked) ? "checked='checked'" : "";
		$this->p(
			"<label>" . 
				"<input type='radio' class='uk-radio' name='debugMode' $yesChecked value='1'> <strong>Enabled</strong> " . 
				"<span class='uk-text-small uk-text-muted'>(recommended while sites are in development or while testing ProcessWire)</span>" . 
			"</label><br />" .
			"<label>" . 
				"<input type='radio' class='uk-radio' name='debugMode' $noChecked value='0'> <strong>Disabled</strong> " . 
				"<span class='uk-text-small uk-text-muted'>(recommended once a site goes live or becomes publicly accessible)</span>" . 
			"</label> " 
		);
		$this->p(
			"You can also enable or disable debug mode at any time by editing the <u>/site/config.php</u> file and setting " .
			"<code>\$config->debug = true;</code> or <code>\$config->debug = false;</code>"
		);
		$this->sectionStop();
		
		$this->btnContinue(array('value' => 4)); 
		$this->p("Note: After you click the button above, be patient &hellip; it may take a minute.", "detail");
	}

	/**
	 * Step 3: Save database configuration, then begin profile import
	 *
	 */
	protected function dbSaveConfig() {

		$values = array();
		$database = null;
		
		// file permissions
		$fields = array('chmodDir', 'chmodFile');
		foreach($fields as $field) {
			$value = $this->post($field, 'int');
			if(strlen("$value") !== 3) {
				$this->alertErr("Value for '$field' is invalid");
			} else {
				$this->$field = "0$value";
			}
			$values[$field] = $value;
		}

		// timezone
		$timezone = $this->post('timezone', 'int');
		$timezones = $this->timezones();
		if(isset($timezones[$timezone])) {
			$value = $timezones[$timezone]; 
			if(strpos($value, '|')) {
				list($label, $value) = explode('|', $value);
				if($label) {} // ignore
			}
			$values['timezone'] = $value; 
		} else {
			$values['timezone'] = 'America/New_York';
		}

		// http hosts
		$values['httpHosts'] = array();
		$httpHosts = $this->post('httpHosts', 'textarea');
		if(strlen($httpHosts)) {
			$httpHosts = str_replace(array("'", '"'), '', $httpHosts);
			$httpHosts = explode("\n", $httpHosts);
			foreach($httpHosts as $key => $host) {
				$host = strtolower(trim(filter_var($host, FILTER_SANITIZE_URL)));
				$httpHosts[$key] = $host;
			}
			$values['httpHosts'] = $httpHosts;
		}
		
		// debug mode
		$values['debugMode'] = $this->post('debugMode', 'int');

		// db configuration
		$fields = array('dbUser', 'dbName', 'dbPass', 'dbHost', 'dbPort', 'dbSocket', 'dbEngine', 'dbCharset', 'dbCon');
		
		foreach($fields as $field) {
			$value = $this->post($field, 'string');
			$value = substr($value, 0, 255); 
			if(strpos($value, "'") !== false) $value = str_replace("'", "\\" . "'", $value); // allow for single quotes (i.e. dbPass)
			if($field != 'dbPass') $value = str_replace(array(';', '..', '=', '<', '>', '&', '"', "\t", "\n", "\r"), '', $value);
			$values[$field] = trim($value); 
		}
	
		$values['dbCharset'] = ($values['dbCharset'] === 'utf8mb4' ? 'utf8mb4' : 'utf8'); 
		$values['dbEngine'] = ($values['dbEngine'] === 'InnoDB' ? 'InnoDB' : 'MyISAM');

		if(empty($values['dbUser']) || empty($values['dbName'])) {
			$this->alertErr("Missing database user and/or name");
			
		} else if($values['dbCon'] === 'Socket' && empty($values['dbSocket'])) {
			$this->alertErr("Missing database socket");
			
		} else if($values['dbCon'] === 'Hostname' && (empty($values['dbHost']) || empty($values['dbPort']))) {
			$this->alertErr("Missing database host and/or port");
			
		} else {
	
			error_reporting(0); 
		
			if($values['dbCon'] === 'Socket') {
				$dsn = "mysql:unix_socket=$values[dbSocket];dbname=$values[dbName]";
			} else {
				$dsn = "mysql:dbname=$values[dbName];host=$values[dbHost];port=$values[dbPort]";
			}

			if(defined("\\Pdo\\Mysql::ATTR_INIT_COMMAND")) {
				$initCommand = constant("\\PDO\\Mysql::ATTR_INIT_COMMAND");
			} else {
				$initCommand = constant("\\PDO::MYSQL_ATTR_INIT_COMMAND");
			}
			$driver_options = array(
				$initCommand => "SET NAMES 'UTF8'",
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
			);
			
			try {
				$database = new \PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options);
				
			} catch(\Exception $e) {
				
				if($e->getCode() == 1049) {
					// If schema does not exist, try to create it
					$database = $this->dbCreateDatabase($dsn, $values, $driver_options); 
					
				} else {
					$this->alertErr("Database connection information did not work.");
					$this->alertErr(htmlentities($e->getMessage(), ENT_QUOTES, 'UTF-8'));
				}
			}
		}

		if($this->numErrors || !$database) {
			$this->dbConfig($values);
			return;
		}

		$this->h("fa-database Test Database and Save Configuration");
		$this->alertOk("Database connection successful to " . htmlspecialchars($values['dbName'])); 
		
		$options = array(
			'dbCharset' => strtolower($values['dbCharset']), 
			'dbEngine' => $values['dbEngine']
		);

		// check if MySQL is new enough to support InnoDB with fulltext indexes
		if($options['dbEngine'] == 'InnoDB') {
			$query = $database->query("SELECT VERSION()");
			list($dbVersion) = $query->fetch(\PDO::FETCH_NUM);
			if(version_compare($dbVersion, "5.6.4", "<")) {
				$options['dbEngine'] = 'MyISAM';
				$values['dbEngine'] = 'MyISAM';
				$this->alertErr("Your MySQL version is $dbVersion and InnoDB requires 5.6.4 or newer. Engine changed to MyISAM.");
			}
		}
		
		// check if database already has tables present
		$query = $database->query("SHOW TABLES");
		$tables = $query->fetchAll(\PDO::FETCH_COLUMN);
		$numTables = count($tables);
		$dbTablesAction = $this->post('dbTablesAction', 'string');
		
		if($numTables && $dbTablesAction) {
			if($dbTablesAction === 'remove') {
				// remove
				foreach($tables as $table) {
					$database->exec("DROP TABLE `$table`"); 
				}
				$this->alertOk("Dropped $numTables existing table(s)"); 
				$numTables = 0;
			} else if($dbTablesAction === 'ignore') {
				// ignore
				$this->alertOk('Existing tables will be ignored'); 
			} else {
				$dbTablesAction = '';
			}
		}

		if($numTables && empty($dbTablesAction)) {
			$this->alertErr(
				"<strong>Database already has $numTables table(s) present:</strong> " . 
				implode(', ', $tables) . ". " . 
				"<strong>Please select below what you would like to do with these tables.</strong>"
			); 
			$this->dbConfig($values, $numTables);
		} else if($this->dbSaveConfigFile($values)) {
			$this->profileImport($database, $options);
		} else {
			$this->dbConfig($values);
		}
	}

	/**
	 * Create database
	 * 
	 * Note: only handles database names that stick to ascii _a-zA-Z0-9.
	 * For database names falling outside that set, they should be created
	 * ahead of time. 
	 * 
	 * Contains contributions from @plauclair PR #950
	 * 
	 * @param string $dsn
	 * @param array $values
	 * @param array $driver_options
	 * @return \PDO|null
	 * 
	 */
	protected function dbCreateDatabase($dsn, $values, $driver_options) {
		
		$dbCharset = preg_replace('/[^a-z0-9]/', '', strtolower(substr($values['dbCharset'], 0, 64)));
		$dbName = preg_replace('/[^_a-zA-Z0-9]/', '', substr($values['dbName'], 0, 64));
		$dbNameTest = str_replace('_', '', $dbName);

		if(ctype_alnum($dbNameTest) && $dbName === $values['dbName']
			&& ctype_alnum($dbCharset) && $dbCharset === $values['dbCharset']) {
			
			// valid database name with no changes after sanitization

			try {
				$dsn2 = "mysql:host=$values[dbHost];port=$values[dbPort]";
				$database = new \PDO($dsn2, $values['dbUser'], $values['dbPass'], $driver_options);
				$database->exec("CREATE SCHEMA IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET `$dbCharset`");
				// reconnect
				$database = new \PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options);
				$this->alertOk("Created database: $dbName"); 

			} catch(\Exception $e) {
				$this->alertErr("Failed to create database with name $dbName");
				$this->alertErr($e->getMessage()); 
				$database = null;
			}
			
		} else {
			$database = null;
			$this->alertErr("Unable to create database with that name. Please create the database with another tool and try again."); 
		}
		
		return $database; 
	}

	/**
	 * Save configuration to /site/config.php
	 * 
	 * @param array $values
	 * @return bool
	 *
	 */
	protected function dbSaveConfigFile(array $values) {

		if(self::TEST_MODE) return true;
		
		$file = __FILE__; 
		$time = time();
		$host = empty($values['httpHosts']) ? '' : implode(',', $values['httpHosts']);
		$s = is_file("./site/config.php") ? file_get_contents("./site/config.php") : '';

		if(function_exists('random_bytes')) {
			$authSalt = sha1(random_bytes(random_int(40, 128)));
			$tableSalt = sha1(random_int(0, 65535) . "$host$file$time"); 
		} else {
			$authSalt = md5(mt_rand() . microtime(true));
			$tableSalt = md5(mt_rand() . "$host$file$time"); 
		}
		
		$cfg =
			"\n/**" . 
			"\n * Installer: Database Configuration" . 
			"\n * " . 
			"\n */";

		if($values['dbCon'] === 'Socket') {
			$cfg .= "\n\$config->dbSocket = '$values[dbSocket]';";
		}
		
		$cfg .= 
			"\n\$config->dbHost = '$values[dbHost]';" . 
			"\n\$config->dbName = '$values[dbName]';" . 
			"\n\$config->dbUser = '$values[dbUser]';" . 
			"\n\$config->dbPass = '$values[dbPass]';" . 
			"\n\$config->dbPort = '$values[dbPort]';";
		
		if(!empty($values['dbCharset']) && strtolower($values['dbCharset']) != 'utf8') {
			$cfg .= "\n\$config->dbCharset = '$values[dbCharset]';";
		}
		if(!empty($values['dbEngine']) && $values['dbEngine'] == 'InnoDB') {
			$cfg .= "\n\$config->dbEngine = 'InnoDB';";
		}
		
		if(strpos($s, '$config->userAuthSalt') === false) $cfg .= 
			"\n" . 
			"\n/**" . 
			"\n * Installer: User Authentication Salt " . 
			"\n * " .
			"\n * This value was randomly generated for your system on " . date('Y/m/d') . "." . 
			"\n * This should be kept as private as a password and never stored in the database." . 
			"\n * Must be retained if you migrate your site from one server to another." . 
			"\n * Do not change this value, or user passwords will no longer work." .
			"\n * " . 
			"\n */" . 
			"\n\$config->userAuthSalt = '$authSalt'; ";

		if(strpos($s, '$config->tableSalt') === false) $cfg .=
			"\n" .
			"\n/**" . 
			"\n * Installer: Table Salt (General Purpose) " .
			"\n * " .
			"\n * Use this rather than userAuthSalt when a hashing salt is needed for non user " .
			"\n * authentication purposes. Like with userAuthSalt, you should never change " . 
			"\n * this value or it may break internal system comparisons that use it. " . 
			"\n * " .
			"\n */" . 
			"\n\$config->tableSalt = '$tableSalt'; ";
		
		$cfg .= 
			"\n" . 
			"\n/**" . 
			"\n * Installer: File Permission Configuration" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->chmodDir = '0$values[chmodDir]'; // permission for directories created by ProcessWire" . 	
			"\n\$config->chmodFile = '0$values[chmodFile]'; // permission for files created by ProcessWire " . 	
			"\n" . 
			"\n/**" . 
			"\n * Installer: Time zone setting" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->timezone = '$values[timezone]';" .
			"\n";

		if(strpos($s, '$config->defaultAdminTheme') === false) $cfg .=
			"\n/**" .
			"\n * Installer: Admin theme" .
			"\n * " .
			"\n */" .
			"\n\$config->defaultAdminTheme = 'AdminThemeUikit';" .
			"\n";

		if(strpos($s, '$config->installed ') === false) $cfg .=
			"\n/**" .
			"\n * Installer: Unix timestamp of date/time installed" .
			"\n * " .
			"\n * This is used to detect which when certain behaviors must be backwards compatible." .
			"\n * Please leave this value as-is." .
			"\n * " .
			"\n */" .
			"\n\$config->installed = " . time() . ";" .
			"\n\n";
		
		if(strpos($s, '$config->sessionName') === false) $cfg .=
			"\n/**" .
			"\n * Installer: Session name " . 
			"\n * " .
			"\n * Default session name as used in session cookie. " .
			"\n * Note that changing this will automatically logout any current sessions. " .
			"\n * " .
			"\n */" .
			"\n\$config->sessionName = 'pw" . mt_rand(0, 999) . "';" .
			"\n\n";

		if(!empty($values['httpHosts'])) {
			$cfg .= 
			"\n/**" . 
			"\n * Installer: HTTP Hosts Whitelist" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->httpHosts = array("; 
			foreach($values['httpHosts'] as $host) $cfg .= "'$host', ";
			$cfg = rtrim($cfg, ", ") . ");\n\n";
		}
		
		$cfg .=
			"\n/**" .
			"\n * Installer: Debug mode?" .
			"\n * " . 
			"\n * When debug mode is true, errors and exceptions are visible. " . 
			"\n * When false, they are not visible except to superuser and in logs. " . 
			"\n * Should be true for development sites and false for live/production sites. " . 
			"\n * " .
			"\n */" .
			"\n\$config->debug = " . ($values['debugMode'] ? 'true;' : 'false;') . 
			"\n\n";

		if(strpos($s, '<' . '?php') === false) $cfg = '<' . "?php namespace ProcessWire;\n\n" . $cfg; 
			
		if(($fp = fopen("./site/config.php", "a")) && fwrite($fp, $cfg)) {
			fclose($fp); 
			$this->alertOk("Saved configuration to ./site/config.php"); 
			return true; 
		} else {
			$this->alertErr("Error saving configuration to ./site/config.php. Please make sure it is writable."); 
			return false;
		}
	}

	/**
	 * Step 3b: Import profile
	 * 
	 * @param \PDO $database
	 * @param array $options
	 *
	 */
	protected function profileImport($database, array $options) {

		if(self::TEST_MODE) {
			$this->alertOk("TEST MODE: Skipping profile import"); 
			$this->adminAccount();
			return;
		}

		$profile = "./site/install/";
		if(!is_file("{$profile}install.sql")) die("No installation profile found in {$profile}");
		
		$this->sectionStart('fa-building-o Profile Import');

		// checks to see if the database exists using an arbitrary query (could just as easily be something else)
		try {
			$query = $database->prepare("SHOW COLUMNS FROM pages"); 
			$result = $query->execute();
		} catch(\Exception $e) {
			$result = false;
			$query = null;
		}

		if(self::REPLACE_DB || !$result || $query->rowCount() == 0) {

			$this->profileImportSQL($database, "./wire/core/install.sql", $profile . "install.sql", $options); 
			
			if(is_dir($profile . "files")) {
				$this->profileImportFiles($profile);
			} else {
				$this->mkdir("./site/assets/files/");
			}
			
			$this->mkdir("./site/assets/cache/", true, true); 
			$this->mkdir("./site/assets/logs/", true, true);
			$this->mkdir("./site/assets/backups/", true, true); 
			$this->mkdir("./site/assets/sessions/", true, true); 
			
		} else {
			$this->ok("A profile is already imported, skipping..."); 
		}

		// copy default site modules /site-default/modules/ to /site/modules/
		$dir = "./site/modules/";
		$defaultDir = "./" . self::DEFAULT_PROFILE . "/modules/"; 
		if(!is_dir($dir)) $this->mkdir($dir);
		if(is_dir($defaultDir)) {
			if(is_writable($dir)) {
				$result = $this->copyRecursive($defaultDir, $dir, false); 	
				if($result) {
					$this->ok("Imported: $defaultDir => $dir"); 
					
				} else {
					$this->warn("Error Importing: $defaultDir => $dir"); 
				}
			} else {
				$this->warn("$dir is not writable, unable to install default site modules (recommended, but not required)"); 
			}
		} else {
			// they are installing site-default already or site-default is not available
		}

		// install the site/.htaccess (not really required but potentially useful fallback)
		$dir = "./site/";
		$defaultDir = "./" . self::DEFAULT_PROFILE . "/"; 
		if(is_file($dir . 'htaccess.txt')) {
			$this->renameFile($dir . 'htaccess.txt', $dir . '.htaccess'); 
		} else if(is_file($defaultDir . 'htaccess.txt')) {
			$this->copyFile($defaultDir . 'htaccess.txt', $dir . '.htaccess');
		}
		
		$this->sectionStop();
		$this->adminAccount();
	}


	/**
	 * Import files to profile
	 * 
	 * @param string $fromPath
	 *
	 */
	protected function profileImportFiles($fromPath) {

		if(self::TEST_MODE) {
			$this->ok("TEST MODE: Skipping file import - $fromPath"); 
			return;
		}

		$dir = new \DirectoryIterator($fromPath);

		foreach($dir as $file) {

			if($file->isDot()) continue; 
			if(!$file->isDir()) continue; 

			$dirname = $file->getFilename();
			$pathname = $file->getPathname();

			if(is_writable($pathname) && self::FORCE_COPY == false) {
				// if it's writable, then we know all the files are likely writable too, so we can just rename it
				$result = rename($pathname, "./site/assets/$dirname/"); 

			} else {
				// if it's not writable, then we will make a copy instead, and that copy should be writable by the server
				$result = $this->copyRecursive($pathname, "./site/assets/$dirname/"); 
			}

			if($result) {
				$this->ok("Imported: $pathname => ./site/assets/$dirname/");
			} else {
				$this->err("Error Importing: $pathname => ./site/assets/$dirname/");
			}
			
		}
	}
	
	/**
	 * Import profile SQL dump
	 * 
	 * @param \PDO $database
	 * @param string $file1
	 * @param string $file2
	 * @param array $options
	 *
	 */
	protected function profileImportSQL($database, $file1, $file2, array $options = array()) {
		$defaults = array(
			'dbEngine' => 'InnoDB',
			'dbCharset' => 'utf8mb4', 
		);
		$options = array_merge($defaults, $options); 
		if(self::TEST_MODE) return;
		$restoreOptions = array();
		$replace = array();
		$replace['ENGINE=InnoDB'] = "ENGINE=$options[dbEngine]";
		$replace['ENGINE=MyISAM'] = "ENGINE=$options[dbEngine]";
		$replace['CHARSET=utf8mb4;'] = "CHARSET=$options[dbCharset];";
		$replace['CHARSET=utf8;'] = "CHARSET=$options[dbCharset];";
		$replace['CHARSET=utf8 COLLATE='] = "CHARSET=$options[dbCharset] COLLATE=";
		
		if(strtolower($options['dbCharset']) === 'utf8mb4') {
			if(strtolower($options['dbEngine']) === 'innodb') {
				$replace['(255)'] = '(191)'; 
				$replace['(250)'] = '(191)'; 
			} else {
				$replace['(255)'] = '(250)'; // max ley length in utf8mb4 is 1000 (250 * 4)
			}
		}
		
		if(count($replace)) $restoreOptions['findReplaceCreateTable'] = $replace; 
		require("./wire/core/WireDatabaseBackup.php"); 
		$backup = new WireDatabaseBackup(); 
		$backup->setDatabase($database);
		if($backup->restoreMerge($file1, $file2, $restoreOptions)) {
			$this->ok("Imported database file: $file1");
			$this->ok("Imported database file: $file2"); 
		} else {
			foreach($backup->errors() as $error) $this->alertErr($error); 
		}
	}

	/**
	 * Present form to create admin account
	 * 
	 * @param null|ProcessWire $wire
	 *
	 */
	protected function adminAccount($wire = null) {

		$values = array(
			'admin_name' => 'processwire',
			'username' => 'admin',
			'userpass' => '',
			'userpass_confirm' => '',
			'useremail' => '',
		);

		$clean = array();

		foreach($values as $key => $value) {
			if($wire && $wire->input->post($key)) $value = $wire->input->post($key);
			$value = htmlentities($value, ENT_QUOTES, "UTF-8"); 
			$clean[$key] = $value;
		}

		$this->sectionStart("fa-sign-in Admin Panel");
		$this->input("admin_name", "Admin Login URL", $clean['admin_name'], array('type' => 'name')); 
		$this->clear();
		
		$this->p(
			"fa-info-circle You can change the admin URL later by editing the admin page and changing the name on the settings tab.",
			array('class' => 'detail', 'style' => 'margin-top:0')
		); 
		$this->sectionStop();
		
		$this->sectionStart("fa-user-circle Admin Account"); 
		$this->p(
			"You will use this account to login to your ProcessWire admin. It will have superuser access, so please make sure " . 
			"to create a <a target='_blank' href='https://en.wikipedia.org/wiki/Password_strength'>strong password</a>."
		);
		$this->input("username", "User", $clean['username'], array('type' => 'name')); 
		$this->input("userpass", "Password", $clean['userpass'], array('type' => 'password')); 
		$this->input("userpass_confirm", "Password <small class='detail'>(again)</small>", $clean['userpass_confirm'], array('type' => 'password')); 
		$this->input("useremail", "Email Address", $clean['useremail'], array('clear' => true, 'type' => 'email')); 
		$this->p(
			"fa-warning Please remember the password you enter above as you will not be able to retrieve it again.", 
			array('class' => 'detail', 'style' => 'margin-top:0')
		);
		$this->sectionStop();
		
		$this->sectionStart("fa-bath Cleanup");
		$this->p("Directories and files listed below are no longer needed and should be removed. If you choose to leave any of them in place, you should delete them before migrating to a production environment.", "detail"); 
		$this->p($this->getRemoveableItems(true)); 
		$this->sectionStop();
			
		$this->btnContinue(array('value' => 5)); 
	}

	/**
	 * Get post-install optionally removable items
	 * 
	 * @param bool $getMarkup Get markup of options/form inputs rather than array of items?
	 * @param bool $removeNow Allow processing of submitted form (via getMarkup) to remove items now?
	 * @return array|string
	 * 
	 */
	protected function getRemoveableItems($getMarkup = false, $removeNow = false) {

		$root = dirname(__FILE__) . '/';
		$isPost = $this->post('remove_items') !== null;
		$postItems = $this->post('remove_items', 'array');
		$out = '';
		
		$items = array(
			'install-php' => array(
				'label' => 'Remove installer (install.php) when finished', 
				'file' => "/install.php", 
				'path' => $root . "install.php", 
			),
			'install-dir' => array(
				'label' => 'Remove installer site profile assets (/site/install/)',
				'path' => $root . "site/install/", 
				'file' => '/site/install/', 
			), 
			'gitignore' => array(
				'label' => 'Remove .gitignore file',
				'path' => $root . ".gitignore",
				'file' => '/.gitignore',
			)
		);
		
		foreach($this->findProfiles() as $name => $profile) {
			if($name === 'site') continue;
			$title = empty($profile['title']) ? $name : $profile['title'];
			$items[$name] = array(
				'label' => "Remove unused $title site profile (/$name/)", 
				'path' => $root . "$name/",
				'file' => "/$name/", 
			);
		}
		
		foreach($items as $name => $item) {
			if(!file_exists($item['path'])) continue;
			$disabled = is_writable($item['path']) ? "" : "disabled";
			$checked = !$isPost || in_array($name, $postItems) ? "checked" : "";
			$note = $disabled ? "<span class='detail'>(not writable/deletable by this installer)</span>" : "";
			$markup =
				"<label style='font-weight: normal;'>" .
				"<input class='uk-checkbox' type='checkbox' $checked $disabled name='remove_items[]' value='$name' /> $item[label] $note" .
				"</label>";
			$items[$name]['markup'] = $markup;
			$out .= $out ? "<br />$markup" : $markup; 
			
			if($removeNow && $isPost) {
				if($checked && !$disabled) {
					if(is_dir($item['path'])) {
						$success = wireRmdir($item['path'], true); 
					} else if(is_file($item['path'])) {
						$success = @unlink($item['path']); 	
					} else {
						$success = true; 
					}
					if($success) {
						// $this->ok("Completed: " . $item['label']); 
					} else {
						$this->err("Unable to remove $item[file] - please remove manually, as it is no longer needed"); 
					}
				} else if($disabled) {
					$this->warn("Please remove $item[file] from the file system as it is no longer needed"); 
				} else if(!$checked) {
					$this->warn("Remember to remove $item[file] from the file system before migrating to production use"); 
				}
			}
		}
		
		if(empty($out)) $out = "None found"; 
		if($getMarkup) return $out; 
		
		return $items; 
	}

	/**
	 * Save submitted admin account form
	 * 
	 * @param ProcessWire $wire
	 *
	 */
	protected function adminAccountSave($wire) {

		$input = $wire->input;
		$sanitizer = $wire->sanitizer;
		$adminTheme = $wire->modules->getInstall('AdminThemeUikit');

		if(!$input->post('username') || !$input->post('userpass')) $this->err("Missing account information"); 
		if($input->post('userpass') !== $input->post('userpass_confirm')) $this->err("Passwords do not match");
		if(strlen($input->post('userpass')) < 6) $this->err("Password must be at least 6 characters long"); 

		$username = $sanitizer->pageName($input->post('username')); 
		if($username != $input->post('username')) $this->err("Username must be only a-z 0-9");
		if(strlen($username) < 2) $this->err("Username must be at least 2 characters long"); 

		$adminName = $sanitizer->pageName($input->post('admin_name'));
		if($adminName != $input->post('admin_name')) $this->err("Admin login URL must be only a-z 0-9");
		if($adminName == 'wire' || $adminName == 'site') $this->err("Admin name may not be 'wire' or 'site'"); 
		if(strlen($adminName) < 2) $this->err("Admin login URL must be at least 2 characters long"); 

		$email = strtolower($sanitizer->email($input->post('useremail'))); 
		if($email != strtolower($input->post('useremail'))) $this->err("Email address did not validate");

		if($this->numErrors) {
			$this->adminAccount($wire);
			return;
		}
	
		$superuserRole = $wire->roles->get("name=superuser");
		$user = $wire->users->get($wire->config->superUserPageID); 

		if($user->id) {
			$user->of(false);
		} else {
			$user = new User(); 
			$user->id = $wire->config->superUserPageID; 
		}

		$user->name = $username;
		$user->pass = $input->post('userpass'); 
		$user->email = $email;
		$user->admin_theme = $adminTheme;

		if(!$user->roles->has("superuser")) $user->roles->add($superuserRole); 

		$admin = $wire->pages->get($wire->config->adminRootPageID); 
		$admin->of(false);
		$admin->name = $adminName;

		try {
			if(self::TEST_MODE) {
				$this->ok("TEST MODE: skipped user creation"); 
			} else {
				$wire->users->save($user); 
				$wire->pages->save($admin);
			}

		} catch(\Exception $e) {
			$this->err($e->getMessage()); 
			$this->adminAccount($wire); 
			return;
		}

		$adminName = htmlentities($adminName, ENT_QUOTES, "UTF-8");

		$this->sectionStart("fa-user-circle Admin Account Saved");
		$this->ok("User account saved: <b>{$user->name}</b>"); 

		$this->sectionStop();
		
		$this->finish($wire, $user); 

		$this->sectionStart("fa-life-buoy Complete &amp; Secure Your Installation");
		$this->getRemoveableItems(false, true); 

		$this->ok("Note that future runtime errors are logged to <b>/site/assets/logs/errors.txt</b> (not web accessible).");
		$this->ok("For more configuration options see <b>/wire/config.php</b> and place any edits in <u>/site/config.php</u>.");
		$this->ok("Consider making your <b>/site/config.php</b> file non-writable, and readable only to you and Apache.");
		$this->ok("View and edit your <b>.htaccess</b> file to force HTTPS, setup redirects, and more.");
			
		$this->p(
			"<a target='_blank' href='https://processwire.com/docs/security/'>" . 
			"Lean more about securing your ProcessWire installation " . $this->icon('angle-right', false) . "</a>"
		);
		$this->sectionStop();
		
		if(is_writable("./site/modules/")) wireChmod("./site/modules/", true); 

		$this->sectionStart("fa-coffee Get Started!");
		$this->ok(
			"Your admin URL is <a target='_blank' href='./$adminName/'>/$adminName/</a>"
		);
		$this->ok(
			"Learn more about ProcessWire in the <a target='_blank' href='https://processwire.com/docs/'>documentation</a> " . 
			"and <a target='_blank' href='https://processwire.com/api/ref/'>API reference</a>. " 
		);
		$this->ok(
			"Visit our <a target='_blank' href='https://processwire.com/talk/'>support forums</a> for friendly help and discussion."
		);
		$this->ok(
			"<a target='_blank' href='https://processwire.com/community/newsletter/subscribe/'>Subscribe to keep up-to-date</a> " . 
			"with new versions and important updates."
		);
		$this->sectionStop();

		$this->btn("Login to Admin", array('value' => 1, 'icon' => 'sign-in', 'float' => true, 'href' => "./$adminName/")); 
		$this->btn("View Site ", array('value' => 1, 'icon' => 'angle-right', 'secondary' => true, 'href' => "./")); 

		// set a define that indicates installation is completed so that this script no longer runs
		if(!self::TEST_MODE) {
			file_put_contents("./site/assets/installed.php", "<?php // The existence of this file prevents the installer from running. Don't delete it unless you want to re-run the install or you have deleted ./install.php."); 
		}

	}

	/**
	 * Process custom theme finish.php file
	 * 
	 * @param ProcessWire $wire
	 * @param User $user
	 * 
	 */
	protected function finish($wire, $user) {
		$file = __DIR__ . '/site/install/finish.php';
		if(is_file($file)) {
			$fuel = array_merge($wire->wire('all')->getArray(), array('user' => $user));
			$installer = $this;
			if($installer) {} // ignore
			extract($fuel);
			include($file);
		}
	}

	/******************************************************************************************************************
	 * OUTPUT FUNCTIONS
	 *
	 */

	/**
	 * @param string $str
	 * @param string $type
	 * @param string $icon
	 * 
	 */
	protected function alert($str, $type = 'primary', $icon = 'check') {
		$icon = $this->icon($icon);
		echo "\n<div class='uk-alert uk-alert-$type'>$icon $str</div>";
	}

	/**
	 * Status/ok alert
	 * 
	 * @param string $str
	 * @param string $icon
	 * 
	 */
	protected function alertOk($str, $icon = 'check') {
		if($this->inSection) {
			$this->ok($str);
		} else {
			$this->alert($str, 'primary', $icon);
		}
	}
	
	/**
	 * Warning alert
	 * 
	 * @param string $str
	 *
	 */
	protected function alertWarn($str) {
		if($this->inSection) {
			$this->warn($str);
		} else {
			$this->numErrors++;
			$this->alert($str, 'warning', 'exclamation-triangle');
		}
	}
	
	/**
	 * Error alert
	 * 
	 * @param string $str
	 *
	 */
	protected function alertErr($str) {
		if($this->inSection) {
			$this->err($str);
		} else {
			$this->numErrors++;
			$this->alert($str, 'danger', 'exclamation-triangle');
		}
	}
	
	/**
	 * Report and log an error
	 * 
	 * @param string $str
	 * @return bool
	 *
	 */
	public function err($str) {
		if(!$this->inSection) {
			$this->alertErr($str);
		} else {
			$this->numErrors++;
			$icon = $this->icon('exclamation-triangle');
			echo "\n<div class='uk-text-danger'>$icon $str</div>";
		}
		return false;
	}

	/**
	 * Action/warning
	 * 
	 * @param string $str
	 * @return bool
	 *
	 */
	public function warn($str) {
		if(!$this->inSection) {
			$this->alertWarn($str);
		} else {
			$this->numErrors++;
			$icon = $this->icon('asterisk');
			echo "\n<div class='uk-text-danger'>$icon $str</div>";
		}
		return false;
	}
	
	/**
	 * Report a status/ok message
	 * 
	 * @param string $str
	 * @param string $icon
	 * @return bool
	 *
	 */
	public function ok($str, $icon = 'check') {
		if(!$this->inSection) {
			$this->alertOk($str);
		} else {
			$icon = $this->icon($icon);
			echo "\n<div>$icon $str</div>";
		}
		return true; 
	}

	/**
	 * Return markup for an icon
	 * 
	 * @param string $name
	 * @param bool $fw Fixed width?
	 * @return string
	 * 
	 */
	public function icon($name, $fw = true) {
		if(strpos($name, 'icon-') === 0 || strpos($name, 'fa-') === 0) {
			list(,$name) = explode('-', $name, 2);
		}
		$class = 'fa' . ($fw ? ' fa-fw' : '');
		return "<i class='$class fa-$name'></i>";
	}

	/**
	 * Given label with 'icon-name' or 'fa-name' at the beginning convert to rendered icon with label
	 *
	 * @param string $label
	 * @param string $icon
	 * @return string
	 *
	 */
	protected function iconize($label, $icon = '') {
		if(empty($icon)) {
			if(strpos($label, 'fa-') === 0 || strpos($label, 'icon-') === 0) {
				list($icon, $label) = explode(' ', $label, 2);
			}
		}
		if($icon) {
			$label = $this->icon($icon) . ' ' . $label;
		}
		return $label;
	}

	/**
	 * Output a button
	 *
	 * @param string $label
	 * @param array $options
	 *
	 */
	public function btn($label, array $options = array()) {
		$defaults = array(
			'name' => 'step',
			'value' => '0',
			'icon' => 'angle-right',
			'secondary' => false,
			'float' => false,
			'href' => '',
			'type' => 'submit',
			'class' => '',
		);
		$options = array_merge($defaults, $options);
		$options['class'] = trim($options['class'] . ' ' . ($options['secondary'] ? 'ui-priority-secondary' : ''));
		if($options['float']) $options['class'] = trim("$options[class] uk-float-left");
		if($options['href']) {
			$options['type'] = 'button';
			echo "<a href='$options[href]' target='_blank'>";
		}
		$icon = $this->icon($options['icon'], false); 
		echo "\n" . 
			"<p>" . 
			"<button name='$options[name]' type='$options[type]' value='$options[value]' " . 
			"class='ui-button ui-widget ui-state-default $options[class] ui-corner-all'>" . 
			"<span class='ui-button-text'>$icon $label</span>" . 
			"</button>" . 
			"</p>";
		if($options['href']) echo "</a>";
		echo " ";
	}

	/**
	 * Output a continue button
	 * 
	 * @param array $options
	 * 
	 */
	public function btnContinue(array $options = array()) {
		$this->btn('Continue', $options);
	}

	/**
	 * Output a headline
	 * 
	 * @param string $label
	 * @param string $icon
	 *
	 */
	public function h($label, $icon = '') {
		$label = $this->iconize($label, $icon);
		echo "\n<h2>$label</h2>";
	}

	/**
	 * Output a paragraph 
	 * 
	 * @param string $text
	 * @param string|array $class Class name, or array of attributes
	 *
	 */
	public function p($text, $class = '') {
		$text = $this->iconize($text);
		if(is_array($class)) {
			echo "\n<p";
			foreach($class as $k => $v) echo " $k='$v'";
			echo ">$text</p>";
		} else if($class) {
			echo "\n<p class='$class'>$text</p>";
		} else {
			echo "\n<p>$text</p>";
		}
	}

	/**
	 * Output an <input type='text'>
	 *
	 * @param string $name
	 * @param string $label
	 * @param string $value
	 * @param array $options
	 *
	 */
	public function input($name, $label, $value, array $options = array()) {
		$defaults = array(
			'clear' => false, 
			'type' => 'text', 
			'required' => true,
			'width' => 150, 
		);
		$options = array_merge($defaults, $options);
		$width = $options['width'];
		$required = $options['required'] ? "required='required'" : "";
		$pattern = '';
		$note = '';
		if($options['type'] === 'email') {
			$width = ($width*2);
			$required = '';
		} else if($options['type'] === 'name') {
			$options['type'] = 'text';
			$pattern = "pattern='[-_a-z0-9]{2,50}' ";
			if($name == 'admin_name') $width = ($width*2);
			$note = "<span class='uk-text-small uk-text-muted'>(a-z 0-9)</span>";
		}
		$inputWidth = $width - 15;
		$value = htmlentities($value, ENT_QUOTES, "UTF-8");
		echo "\n<p style='width: {$width}px; float: left; margin-top: 0;'><label>$label $note<br />";
		echo "<input class='uk-input' type='$options[type]' name='$name' value='$value' $required $pattern style='width:{$inputWidth}px;' />";
		echo "</label></p>";
		if($options['clear']) $this->clear();
	}
	
	/**
	 * Output a <select>
	 *
	 * @param string $name
	 * @param string $label
	 * @param string $value
	 * @param array $options Array of selectable options in format [ 'value' => 'label' ]
	 * @param int $width
	 *
	 */
	public function select($name, $label, $value, array $options, $width = 150) {
		
		if($width) {
			$inputWidth = $width - 15;
			$inputStyle = " style='width: {$inputWidth}px'";
			echo "\n<p style='width: {$width}px; float: left; margin-top: 0;'>";
		} else {
			$inputStyle = '';
			echo "\n<p style='margin-top:0'>";
		}
		
		if($label) echo "<label>$label</label><br />";
		echo "\n\t<select class='uk-select' name='$name'$inputStyle>";
		
		foreach($options as $k => $v) {
			if(is_int($k)) $k = $v; // make non-assoc array behave same as assoc
			$selected = $k === $value ? " selected='selected'" : "";
			echo "\n\t\t<option value='$k'$selected>$v</option>";
		}
		
		echo "\n\t</select>";
		echo "\n</p>";
	}

	/**
	 * Render a timezone select
	 * 
	 * @param $value
	 * 
	 */
	protected function selectTimezone($value) {
		echo "\n<p style='width:240px'>";
		echo "\n\t<select class='uk-select' name='timezone'>";
		foreach($this->timezones() as $key => $timezone) {
			$label = $timezone;
			if(strpos($label, '|')) list($label, $timezone) = explode('|', $label);
			$selected = $timezone == $value ? "selected='selected'" : '';
			$label = str_replace('_', ' ', $label);
			echo "\n\t\t<option value=\"$key\" $selected>$label</option>";
		}
		echo "\n\t</select>\n</p>";
	}

	/**
	 * Render a textarea input
	 * 
	 * @param string $name
	 * @param string $label
	 * @param string $value
	 * @param int $rows
	 * 
	 */
	public function textarea($name, $label, $value, $rows = 0) {
		$rows = $rows ? " rows='$rows'" : "";
		$value = htmlentities($value, ENT_QUOTES, 'UTF-8');
		echo "\n<p>";
		if($label) echo "\n\t<label for='textarea_$name'>$label</label><br />";
		echo "\n\t<textarea class='uk-textarea' id='textarea_$name' name='$name'$rows style='width: 100%;'>$value</textarea>";
		echo "\n</p>";
	}

	/**
	 * Start section
	 * 
	 * @param string $headline
	 * @param string $type
	 * 
	 */
	public function sectionStart($headline = '', $type = 'muted') {
		echo "\n<div class='uk-section uk-section-small uk-section-$type uk-padding uk-margin'>";
		echo "\n\t<div class='uk-container'>";
		if($headline) {
			$headline = $this->iconize($headline);
			echo "<h2>$headline</h2>";
		}
		$this->inSection = true;
	}

	/**
	 * Stop section
	 * 
	 */
	public function sectionStop() {
		echo "\n\t</div>\n</div>";
		$this->inSection = false;
	}

	/**
	 * Clear floated elements
	 * 
	 */
	public function clear() {
		echo "\n<div style='clear: both;'></div>";
	}

	/**
	 * Get a POST variable, optionally sanitized name sanitizer
	 * 
	 * Options for $sanitizer argument:
	 * int, intSigned, text, textarea, string, pageName, name, fieldName, bool, array
	 * 
	 * @param string $key
	 * @param string $sanitizer
	 * @return int|mixed|null|string
	 * 
	 */
	public function post($key, $sanitizer = '') {
		
		$value = isset($_POST[$key]) ? $_POST[$key] : null;
		
		if($value === null && empty($sanitizer)) return null;
		
		switch($sanitizer) {
			case 'intSigned':
				$value = (int) $value;
				break;
			case 'int':	
				$value = (int) $value;
				if($value < 0) $value = 0;
				break;
			case 'text':
				$value = (string) $value;
				if(strlen($value)) {
					$value = str_replace(array("\r", "\n", "\t"), ' ', "$value");
					$value = trim(strip_tags($value));
					if(strlen($value) > 255) $value = substr($value, 0, 255);
				}
				break;
			case 'textarea':	
				$value = (string) $value;
				if(strlen($value)) {
					$value = strip_tags($value);
					$value = str_replace(array("\r\n", "\r"), "\n", $value);
					if(strlen($value) > 4096) $value = substr($value, 0, 4096);
					$value = trim($value);
				}
				break;
			case 'string':	
				$value = trim((string) $value);
				break;
			case 'pageName':	
				$value = strtolower($value); 
				// no-break: passthrough to 'name' intentional...
			case 'name':	
				$value = trim((string) $value);
				if(strlen($value)) {
					$value = preg_replace('/[^-._a-z0-9]/', '-', $value);
					while(strpos($value, '--') !== false) $value = str_replace('--', '-', $value);
					$value = trim($value, '-');
				}
				break;
			case 'fieldName':
				$value = trim((string) $value);
				if(strlen($value)) {
					$value = preg_replace('/[^_a-zA-Z0-9]/', '_', $value);
					while(strpos($value, '__') !== false) $value = str_replace('__', '_', $value);
					$value = trim($value, '_');
				}
				break;
			case 'bool':	
				$value = $value ? true : false;
				break;
			case 'array':	
				$value = is_array($value) ? $value : array();
				break;
		}
		
		return $value;
	}

	/******************************************************************************************************************
	 * FILE FUNCTIONS
	 *
	 */

	/**
	 * Create a directory and assign permission
	 * 
	 * @param string $path Path to create
	 * @param bool $showNote Show notification about what was done?
	 * @param bool $block Add an htaccess file that blocks http access? (default=false)
	 * @return bool
	 *
	 */
	public function mkdir($path, $showNote = true, $block = false) {
		if(self::TEST_MODE) return true;
		$path = rtrim($path, '/') . '/';
		$isDir = is_dir($path);
		if($isDir || mkdir($path)) {
			chmod($path, octdec($this->chmodDir));
			if($showNote && !$isDir) $this->alertOk("Created directory: $path"); 
			$result = true;
		} else {
			if($showNote) $this->alertErr("Error creating directory: $path"); 
			$result = false;
		}
		$file = $path . '.htaccess';
		if($result && $block && !file_exists($file)) {
			$data = array(
				'# Start ProcessWire:pwball (install)',
				'# Block all access (fallback if root .htaccess missing)',
				'<IfModule mod_authz_core.c>',
				'  Require all denied',
				'</IfModule>',
				'<IfModule !mod_authz_core.c>',
				'  Order allow,deny',
				'  Deny from all',
				'</IfModule>',
				'# End ProcessWire:pwball',
			);
			file_put_contents($file, implode("\n", $data));
			chmod($file, octdec($this->chmodFile));
		}
		return $result;
	}

	/**
	 * Copy a file
	 * 
	 * @param string $src
	 * @param string $dst
	 * @return bool
	 * 
	 */
	public function copyFile($src, $dst) {
		if(!@copy($src, $dst)) {
			$this->alertErr("Unable to copy $src => $dst (please copy manually if possible)"); 
			return false;
		}
		chmod($dst, octdec($this->chmodFile));
		return true;
	}

	/**
	 * Rename a file
	 * 
	 * @param string $src
	 * @param string $dst
	 * @return bool
	 * 
	 */
	public function renameFile($src, $dst) {
		if(!@rename($src, $dst)) {
			$this->alertErr("Unable to rename $src => $dst (please rename manually if possible)");
			return false;
		}
		chmod($dst, octdec($this->chmodFile));
		return true;
	}

	/**
	 * Copy directories recursively
	 * 
	 * @param string $src
	 * @param string $dst
	 * @param bool $overwrite
	 * @return bool
	 *
	 */
	public function copyRecursive($src, $dst, $overwrite = true) {

		if(self::TEST_MODE) return true;

		if(substr($src, -1) != '/') $src .= '/';
		if(substr($dst, -1) != '/') $dst .= '/';

		$dir = opendir($src);
		$this->mkdir($dst, false);

		while(false !== ($file = readdir($dir))) {
			if($file == '.' || $file == '..') continue; 
			if(is_dir($src . $file)) {
				$this->copyRecursive($src . $file, $dst . $file);
			} else {
				if(!$overwrite && file_exists($dst . $file)) {
					// don't replace existing files when $overwrite == false;
				} else {
					copy($src . $file, $dst . $file);
					chmod($dst . $file, octdec($this->chmodFile));
				}
			}
		}

		closedir($dir);
		return true; 
	}

	/**
	 * Get all timezone selections
	 * 
	 * @return array
	 * 
	 */
	protected function timezones() {
		$timezones = timezone_identifiers_list();
		if(!is_array($timezones)) return array('UTC');
		$extras = array(
			'US Eastern|America/New_York',
			'US Central|America/Chicago',
			'US Mountain|America/Denver',
			'US Mountain (no DST)|America/Phoenix',
			'US Pacific|America/Los_Angeles',
			'US Alaska|America/Anchorage',
			'US Hawaii|America/Adak',
			'US Hawaii (no DST)|Pacific/Honolulu',
		);
		foreach($extras as $t) $timezones[] = $t; 
		return $timezones; 
	}

	/**
	 * Get memory limit
	 * 
	 * @param string $getInUnit Get value in 'K' [kilobytes], 'M' [megabytes], 'G' [gigabytes] (default='M')
	 * @return int|float
	 * @since 3.0.206
	 * 
	 */
	protected function getMemoryLimit($getInUnit = 'M') {
		// $units = array('M' => 1048576, 'K' => 1024, 'G' => 1073741824);
		$units = array('M' => 1000000, 'K' => 1000, 'G' => 1000000000);
		$value = (string) ini_get('memory_limit');
		$value = trim(strtoupper($value), ' B'); // KB=K, MB=>M, GB=G, 
		$unit = substr($value, -1); // K, M, G
		$value = (int) rtrim($value, 'KMG');
		if($unit === $getInUnit) return $value; // already in correct unit
		if(isset($units[$unit])) $value = $value * $units[$unit]; // convert value to bytes
		if(isset($units[$getInUnit])) $value = round($value / $units[$getInUnit]);
		if(strpos("$value", '.') !== false) $value = round($value, 1);
		return $value;
	}


}

/****************************************************************************************************/

if(!Installer::TEST_MODE && is_file("./site/assets/installed.php")) die("This installer has already run. Please delete it."); 
error_reporting(E_ALL); 
$installer = new Installer();
$installer->execute();