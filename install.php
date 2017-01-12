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
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
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
	const MIN_REQUIRED_PHP_VERSION = '5.3.8';

	/**
	 * Test mode for installer development, non destructive
	 *
	 */
	const TEST_MODE = false;

	/**
	 * File permissions, determined in the dbConfig function
	 *
	 * Below are last resort defaults
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
	 * Available color themes
	 *
	 */
	protected $colors = array(
		'classic',
		'warm',
		);


	/**
	 * Execution controller
	 *
	 */
	public function execute() {
		
		if(self::TEST_MODE) {
			error_reporting(E_ALL | E_STRICT);
			ini_set('display_errors', 1);
		}

		// these two vars used by install-head.inc
		$title = "ProcessWire " . PROCESSWIRE_INSTALL . " Installation";
		$formAction = "./install.php";
		if($title && $formAction) {} // ignore
		
		require("./wire/modules/AdminTheme/AdminThemeDefault/install-head.inc"); 

		if(isset($_POST['step'])) switch($_POST['step']) {
			
			case 0: $this->initProfile(); break;

			case 1: $this->compatibilityCheck(); break;

			case 2: $this->dbConfig();  break;

			case 4: $this->dbSaveConfig();  break;

			case 5: require("./index.php");
				/** @var ProcessWire $wire */
				$this->adminAccountSave($wire); 
				break;

			default: 
				$this->welcome();

		} else $this->welcome();

		require("./wire/modules/AdminTheme/AdminThemeDefault/install-foot.inc"); 
	}


	/**
	 * Welcome/Intro screen
	 *
	 */
	protected function welcome() {
		$this->h("Welcome. This tool will guide you through the installation process."); 
		$this->p("Thanks for choosing ProcessWire! If you downloaded this copy of ProcessWire from somewhere other than <a href='https://processwire.com/'>processwire.com</a> or <a href='https://github.com/processwire/processwire' target='_blank'>our GitHub page</a>, please download a fresh copy before installing. If you need help or have questions during installation, please stop by our <a href='https://processwire.com/talk/' target='_blank'>support board</a> and we'll be glad to help.");
		$this->btn("Get Started", 0, 'sign-in'); 
	}


	/**
	 * Check if the given function $name exists and report OK or fail with $label
	 * 
	 * @param string $name
	 * @param string $label
	 *
	 */
	protected function checkFunction($name, $label) {
		if(function_exists($name)) $this->ok("$label"); 
			else $this->err("Fail: $label"); 
	}

	/**
	 * Find all profile directories (site-*) in the current dir and return info array for each
	 * 
	 * @return array
	 * 
	 */
	protected function findProfiles() {
		$profiles = array(
			'site-beginner' => null,
			'site-default' => null, // preferred starting order
			'site-languages' => null, 
			'site-blank' => null
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
			if(strpos($name, 'site-') !== 0) continue;
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
	
	protected function selectProfile() {
		$options = '';
		$out = '';
		$profiles = $this->findProfiles();
		if(!count($profiles)) $this->err("No profiles found!");
		foreach($profiles as $name => $profile) {
			$title = empty($profile['title']) ? ucfirst($profile['name']) : $profile['title'];
			//$selected = $name == 'site-default' ? " selected='selected'" : "";
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
		
		echo "
			<p>A site installation profile is a ready-to-use and modify site for ProcessWire. 
			If you are just getting started with ProcessWire, we recommend choosing
			the <em>Default</em> site profile. If you already know what you are doing,
			you might prefer the <em>Blank</em> site profile. 
			<p>
			<select name='profile' id='select-profile'>
			<option value=''>Installation Profiles</option>
			$options
			</select>
			<span class='detail'><i class='fa fa-angle-left'></i> Select each installation profile to see more information and a preview.</span>
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
	
		$this->h('Site Installation Profile'); 
		
		if(is_file("./site/install/install.sql")) {
			$this->ok("Found installation profile in /site/install/");

		} else if(is_dir("./site/")) {
			$this->ok("Found /site/ -- already installed? ");

		} else if(isset($_POST['profile'])) {
			
			$profiles = $this->findProfiles();
			$profile = preg_replace('/[^-a-zA-Z0-9_]/', '', $_POST['profile']);
			if(empty($profile) || !isset($profiles[$profile]) || !is_dir(dirname(__FILE__) . "/$profile")) {
				$this->err("Profile not found");
				$this->selectProfile();
				$this->btn("Continue", 0);
				return;
			}
			// $info = $profiles[$profile];
			// $this->h(empty($info['title']) ? ucfirst($info['name']) : $info['title']);
			
			if(@rename("./$profile", "./site")) {
				$this->ok("Renamed /$profile => /site");
			} else {
				$this->err("File system is not writable by this installer. Before continuing, please rename '/$profile' to '/site'");
				$this->btn("Continue", 0);
				return;
			}

		} else {
			$this->selectProfile();
			$this->btn("Continue", 0);
			return;
		}
		
		$this->compatibilityCheck();
	}

	/**
	 * Step 1b: Check for ProcessWire compatibility
	 *
	 */
	protected function compatibilityCheck() { 

		$this->h("Compatibility Check"); 
		
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
		$this->checkFunction("mysqli_connect", "MySQLi (not required by core, but may be required by some 3rd party modules)");
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
				else $this->err("Apache mod_rewrite does not appear to be installed and is required by ProcessWire."); 
		} else {
			// apache_get_modules doesn't work on a cgi installation.
			// check for environment var set in htaccess file, as submitted by jmarjie. 
			$mod_rewrite = getenv('HTTP_MOD_REWRITE') == 'On' || getenv('REDIRECT_HTTP_MOD_REWRITE') == 'On' ? true : false;
			if($mod_rewrite) {
				$this->ok("Found Apache module (cgi): mod_rewrite");
			} else {
				$this->err("Unable to determine if Apache mod_rewrite (required by ProcessWire) is installed. On some servers, we may not be able to detect it until your .htaccess file is place. Please click the 'check again' button at the bottom of this screen, if you haven't already."); 
			}
		}
		
		if(class_exists('\ZipArchive')) {
			$this->ok("ZipArchive support"); 
		} else {
			$this->warn("ZipArchive support was not found. This is recommended, but not required to complete installation."); 
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
				$this->warn("We recommend that directory $d be made writable before continuing."); 
			}
		}
		
		if(is_writable("./site/config.php")) $this->ok("/site/config.php is writable"); 
			else $this->err("/site/config.php must be writable. Please adjust the server permissions before continuing."); 
		
		if(!is_file("./.htaccess") || !is_readable("./.htaccess")) {
			if(@rename("./htaccess.txt", "./.htaccess")) $this->ok("Installed .htaccess"); 
				else $this->err("/.htaccess doesn't exist. Before continuing, you should rename the included htaccess.txt file to be .htaccess (with the period in front of it, and no '.txt' at the end)."); 

		} else if(!strpos(file_get_contents("./.htaccess"), "PROCESSWIRE")) {
			$this->err("/.htaccess file exists, but is not for ProcessWire. Please overwrite or combine it with the provided /htaccess.txt file (i.e. rename /htaccess.txt to /.htaccess, with the period in front)."); 

		} else {
			$this->ok(".htaccess looks good"); 
		}

		if($this->numErrors) {
			$this->p("One or more errors were found above. We recommend you correct these issues before proceeding or <a href='http://processwire.com/talk/'>contact ProcessWire support</a> if you have questions or think the error is incorrect. But if you want to proceed anyway, click Continue below.");
			$this->btn("Check Again", 1, 'refresh', false, true); 
			$this->btn("Continue to Next Step", 2, 'angle-right', true); 
		} else {
			$this->btn("Continue to Next Step", 2, 'angle-right', false); 
		}
	}

	/**
	 * Step 2: Configure the database and file permission settings
	 * 
	 * @param array $values
	 *
	 */
	protected function dbConfig($values = array()) {

		if(!is_file("./site/install/install.sql")) die("There is no installation profile in /site/. Please place one there before continuing. You can get it at processwire.com/download"); 

		
		$this->h("MySQL Database"); 
		$this->p("Please specify a MySQL 5.x database and user account on your server. If the database does not exist, we will attempt to create it. If the database already exists, the user account should have full read, write and delete permissions on the database.*"); 
		$this->p("*Recommended permissions are select, insert, update, delete, create, alter, index, drop, create temporary tables, and lock tables.", "detail"); 

		if(!isset($values['dbName'])) $values['dbName'] = '';
		// @todo: are there PDO equivalents for the ini_get()s below?
		if(!isset($values['dbHost'])) $values['dbHost'] = ini_get("mysqli.default_host"); 
		if(!isset($values['dbPort'])) $values['dbPort'] = ini_get("mysqli.default_port"); 
		if(!isset($values['dbUser'])) $values['dbUser'] = ini_get("mysqli.default_user"); 
		if(!isset($values['dbPass'])) $values['dbPass'] = ini_get("mysqli.default_pw");
		if(!isset($values['dbEngine'])) $values['dbEngine'] = 'MyISAM';

		if(!$values['dbHost']) $values['dbHost'] = 'localhost';
		if(!$values['dbPort']) $values['dbPort'] = 3306; 
		if(empty($values['dbCharset'])) $values['dbCharset'] = 'utf8';

		foreach($values as $key => $value) {
			if(strpos($key, 'chmod') === 0) {
				$values[$key] = (int) $value;
			} else if($key != 'httpHosts') {
				$values[$key] = htmlspecialchars($value, ENT_QUOTES, 'utf-8'); 
			}
		}
		

		$this->input('dbName', 'DB Name', $values['dbName']); 
		$this->input('dbUser', 'DB User', $values['dbUser']);
		$this->input('dbPass', 'DB Pass', $values['dbPass'], false, 'password', false); 
		$this->input('dbHost', 'DB Host', $values['dbHost']); 
		$this->input('dbPort', 'DB Port', $values['dbPort'], true);
		
		echo 
			"<div id='dbAdvancedToggle'><small>" . 
			"<a class='ui-priority-secondary' href='#' onclick='$(\"#dbAdvanced\").slideDown();$(\"#dbAdvancedToggle\").slideUp();'>" .
			"<i class='fa fa-wrench'></i> Advanced: Charset &amp; Engine &hellip;</a>" . 
			"</small></div>";
		
		echo "<div id='dbAdvanced' style='display: none'>";
		$this->h('Advanced Database Options'); 
		$this->p(
			"The 'utf8' and 'MyISAM' options are known to work across the broadest range of servers and 3rd party modules, " . 
			"so you should not change these settings unless you know what you are doing. " . 
			"The 'utf8mb4' (charset) and/or 'InnoDB' (engine) may be preferable for some installations. " . 
			"*Please note the 'InnoDB' option requires MySQL 5.6.4 or newer."
		);
		echo "<p style='width: 135px; float: left; margin-top: 0;'><label>DB Charset</label><br />";
		echo "<select name='dbCharset'>";
		echo "<option value='utf8'" . ($values['dbCharset'] != 'utf8mb4' ? " selected" : "") . ">utf8</option>";
		echo "<option value='utf8mb4'" . ($values['dbCharset'] == 'utf8mb4' ? " selected" : "") . ">utf8mb4</option>";
		echo "</select></p>";
		// $this->input('dbCharset', 'DB Charset', $values['dbCharset']); 
		echo "<p style='width: 135px; float: left; margin-top: 0;'><label>DB Engine</label><br />"; 
		echo "<select name='dbEngine'>";
		echo "<option value='MyISAM'" . ($values['dbEngine'] != 'InnoDB' ? " selected" : "") . ">MyISAM</option>"; 
		echo "<option value='InnoDB'" . ($values['dbEngine'] == 'InnoDB' ? " selected" : "") . ">InnoDB*</option>";
		echo "</select></p>";
		echo "</div>";

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

		$this->h("Default Time Zone"); 
		echo "<p><select name='timezone'>"; 
		foreach($this->timezones() as $key => $timezone) {
			$label = $timezone; 
			if(strpos($label, '|')) list($label, $timezone) = explode('|', $label); 
			$selected = $timezone == $values['timezone'] ? "selected='selected'" : '';
			$label = str_replace('_', ' ', $label); 
			echo "<option value=\"$key\" $selected>$label</option>";
		}
		echo "</select></p>";

		$this->h("File Permissions"); 
		$this->p(
			"When ProcessWire creates directories or files, it assigns permissions to them. " . 
			"Enter the most restrictive permissions possible that give ProcessWire (and you) read and write access to the web server (Apache). " . 
			"The safest setting to use varies from server to server. " . 
			"If you are not on a dedicated or private server, or are in any kind of shared environment, you may want to contact your web host to advise on what are the best permissions to use in your environment. " . 
			"<a target='_blank' href='https://processwire.com/docs/security/file-permissions/'>Read more about securing file permissions</a>"
			);

		$this->p("Permissions must be 3 digits each. Should you opt to use the defaults provided, you can also adjust these permissions later if desired by editing <u>/site/config.php</u>.", "detail");

		$this->input('chmodDir', 'Directories', $values['chmodDir']); 
		$this->input('chmodFile', 'Files', $values['chmodFile'], true); 

		if($cgi) {
			echo "<p class='detail' style='margin-top: 0;'>We detected that this file (install.php) is writable. That means Apache may be running as your user account. Given that, we populated the permissions above (755 &amp; 644) as possible starting point.</p>";
		} else {
			echo "<p class='detail' style='margin-top: 0;'>WARNING: 777 and 666 permissions mean that directories and files are readable and writable to everyone on the server (and thus not particularly safe). If in any kind of shared hosting environment, please consult your web host for their recommended permission settings for Apache readable/writable directories and files before proceeding. <a target='_blank' href='https://processwire.com/docs/security/file-permissions/'>More</a></p>";
		}

		$this->h("HTTP Host Names"); 
		$this->p("What host names will this installation run on now and in the future? Please enter one host per line. You may also choose to leave this blank to auto-detect on each request, but we recommend using this whitelist for the best security in production environments."); 
		$this->p("This field is recommended but not required. You can set this later by editing the file <u>/site/config.php</u> (setting \$config->httpHosts).", "detail"); 
		$rows = substr_count($values['httpHosts'], "\n") + 2; 
		echo "<p><textarea name='httpHosts' rows='$rows' style='width: 100%;'>" . htmlentities($values['httpHosts'], ENT_QUOTES, 'UTF-8') . "</textarea></p>";

		$this->btn("Continue", 4); 

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
			$value = (int) $_POST[$field];
			if(strlen("$value") !== 3) $this->err("Value for '$field' is invalid");
			else $this->$field = "0$value";
			$values[$field] = $value;
		}

		$timezone = (int) $_POST['timezone'];
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

		$values['httpHosts'] = array();
		$httpHosts = trim($_POST['httpHosts']);
		if(strlen($httpHosts)) {
			$httpHosts = str_replace(array("'", '"'), '', $httpHosts);
			$httpHosts = explode("\n", $httpHosts);
			foreach($httpHosts as $key => $host) {
				$host = strtolower(trim(filter_var($host, FILTER_SANITIZE_URL)));
				$httpHosts[$key] = $host;
			}
			$values['httpHosts'] = $httpHosts;
		}

		// db configuration
		$fields = array('dbUser', 'dbName', 'dbPass', 'dbHost', 'dbPort', 'dbEngine', 'dbCharset');
		foreach($fields as $field) {
			$value = get_magic_quotes_gpc() ? stripslashes($_POST[$field]) : $_POST[$field]; 
			$value = substr($value, 0, 255); 
			if(strpos($value, "'") !== false) $value = str_replace("'", "\\" . "'", $value); // allow for single quotes (i.e. dbPass)
			$values[$field] = trim($value); 
		}
	
		$values['dbCharset'] = ($values['dbCharset'] === 'utf8mb4' ? 'utf8mb4' : 'utf8'); 
		$values['dbEngine'] = ($values['dbEngine'] === 'InnoDB' ? 'InnoDB' : 'MyISAM'); 
		// if(!ctype_alnum($values['dbCharset'])) $values['dbCharset'] = 'utf8';

		if(!$values['dbUser'] || !$values['dbName'] || !$values['dbPort']) {
			
			$this->err("Missing database configuration fields"); 
			
		} else {
	
			error_reporting(0); 
			
			$dsn = "mysql:dbname=$values[dbName];host=$values[dbHost];port=$values[dbPort]";
			$driver_options = array(
				\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
				);
			
			try {
				$database = new \PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options);
				
			} catch(\Exception $e) {
				
				if($e->getCode() == 1049) {
					// If schema does not exist, try to create it
					$database = $this->dbCreateDatabase($dsn, $values, $driver_options); 
					
				} else {
					$this->err("Database connection information did not work.");
					$this->err($e->getMessage());
				}
			}
		}

		if($this->numErrors || !$database) {
			$this->dbConfig($values);
			return;
		}

		$this->h("Test Database and Save Configuration");
		$this->ok("Database connection successful to " . htmlspecialchars($values['dbName'])); 
		$options = array(
			'dbCharset' => strtolower($values['dbCharset']), 
			'dbEngine' => $values['dbEngine']
		);
	
		if($options['dbEngine'] == 'InnoDB') {
			$query = $database->query("SELECT VERSION()");
			list($dbVersion) = $query->fetch(\PDO::FETCH_NUM);
			if(version_compare($dbVersion, "5.6.4", "<")) {
				$options['dbEngine'] = 'MyISAM';
				$values['dbEngine'] = 'MyISAM';
				$this->err("Your MySQL version is $dbVersion and InnoDB requires 5.6.4 or newer. Engine changed to MyISAM.");
			}
		}

		if($this->dbSaveConfigFile($values)) {
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
				if($database) $this->ok("Created database: $dbName"); 

			} catch(\Exception $e) {
				$this->err("Failed to create database with name $dbName");
				$this->err($e->getMessage()); 
				$database = null;
			}
			
		} else {
			$database = null;
			$this->err("Unable to create database with that name. Please create the database with another tool and try again."); 
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

		$salt = md5(mt_rand() . microtime(true)); 

		$cfg = 	"\n/**" . 
			"\n * Installer: Database Configuration" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->dbHost = '$values[dbHost]';" . 
			"\n\$config->dbName = '$values[dbName]';" . 
			"\n\$config->dbUser = '$values[dbUser]';" . 
			"\n\$config->dbPass = '$values[dbPass]';" . 
			"\n\$config->dbPort = '$values[dbPort]';";
		
		if(!empty($values['dbCharset']) && strtolower($values['dbCharset']) != 'utf8') $cfg .= "\n\$config->dbCharset = '$values[dbCharset]';";
		if(!empty($values['dbEngine']) && $values['dbEngine'] == 'InnoDB') $cfg .= "\n\$config->dbEngine = 'InnoDB';";
		
		$cfg .= 
			"\n" . 
			"\n/**" . 
			"\n * Installer: User Authentication Salt " . 
			"\n * " . 
			"\n * Must be retained if you migrate your site from one server to another" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->userAuthSalt = '$salt'; " . 
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
			"\n" . 
			"\n/**" .
			"\n * Installer: Unix timestamp of date/time installed" .
			"\n * " .
			"\n * This is used to detect which when certain behaviors must be backwards compatible." .
			"\n * Please leave this value as-is." .
			"\n * " .
			"\n */" .
			"\n\$config->installed = " . time() . ";" .
			"\n\n";

		if(!empty($values['httpHosts'])) {
			$cfg .= "" . 
			"\n/**" . 
			"\n * Installer: HTTP Hosts Whitelist" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->httpHosts = array("; 
			foreach($values['httpHosts'] as $host) $cfg .= "'$host', ";
			$cfg = rtrim($cfg, ", ") . ");\n\n";
		}
		
		if(($fp = fopen("./site/config.php", "a")) && fwrite($fp, $cfg)) {
			fclose($fp); 
			$this->ok("Saved configuration to ./site/config.php"); 
			return true; 
		} else {
			$this->err("Error saving configuration to ./site/config.php. Please make sure it is writable."); 
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
			$this->ok("TEST MODE: Skipping profile import"); 
			$this->adminAccount();
			return;
		}

		$profile = "./site/install/";
		if(!is_file("{$profile}install.sql")) die("No installation profile found in {$profile}"); 

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
			
			if(is_dir($profile . "files")) $this->profileImportFiles($profile);
				else $this->mkdir("./site/assets/files/"); 
			
			$this->mkdir("./site/assets/cache/"); 
			$this->mkdir("./site/assets/logs/"); 
			$this->mkdir("./site/assets/sessions/"); 
			
		} else {
			$this->ok("A profile is already imported, skipping..."); 
		}

		// copy default site modules /site-default/modules/ to /site/modules/
		$dir = "./site/modules/";
		$defaultDir = "./site-default/modules/"; 
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
			// they are installing site-default already 
		}

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

			if($result) $this->ok("Imported: $pathname => ./site/assets/$dirname/"); 
				else $this->err("Error Importing: $pathname => ./site/assets/$dirname/"); 
			
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
			'dbEngine' => 'MyISAM',
			'dbCharset' => 'utf8', 
			);
		$options = array_merge($defaults, $options); 
		if(self::TEST_MODE) return;
		$restoreOptions = array();
		$replace = array();
		if($options['dbEngine'] != 'MyISAM') {
			$replace['ENGINE=MyISAM'] = "ENGINE=$options[dbEngine]";
			$this->warn("Engine changed to '$options[dbEngine]', please keep an eye out for issues."); 
		}
		if($options['dbCharset'] != 'utf8') {
			$replace['CHARSET=utf8'] = "CHARSET=$options[dbCharset]";
			if(strtolower($options['dbCharset']) === 'utf8mb4') {
				if(strtolower($options['dbEngine']) === 'innodb') {
					$replace['(255)'] = '(191)'; 
					$replace['(250)'] = '(191)'; 
				} else {
					$replace['(255)'] = '(250)'; // max ley length in utf8mb4 is 1000 (250 * 4)
				}
			}
			$this->warn("Character set has been changed to '$options[dbCharset]', please keep an eye out for issues."); 
		}
		if(count($replace)) $restoreOptions['findReplaceCreateTable'] = $replace; 
		require("./wire/core/WireDatabaseBackup.php"); 
		$backup = new WireDatabaseBackup(); 
		$backup->setDatabase($database);
		if($backup->restoreMerge($file1, $file2, $restoreOptions)) {
			$this->ok("Imported database file: $file1");
			$this->ok("Imported database file: $file2"); 
		} else {
			foreach($backup->errors() as $error) $this->err($error); 
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
			if($wire && $wire->input->post->$key) $value = $wire->input->post->$key;
			$value = htmlentities($value, ENT_QUOTES, "UTF-8"); 
			$clean[$key] = $value;
		}

		$this->h("Admin Panel Information");
		$this->input("admin_name", "Admin Login URL", $clean['admin_name'], false, "name"); 
		$js = "$('link#colors').attr('href', $('link#colors').attr('href').replace(/main-.*$/, 'main-' + $(this).val() + '.css'))";
		echo "<p class='ui-helper-clearfix'><label>Color Theme<br /><select name='colors' id='colors' onchange=\"$js\">";
		foreach($this->colors as $color) echo "<option value='$color'>" . ucfirst($color) . "</option>";
		echo "</select></label> <span class='detail'><i class='fa fa-angle-left'></i> Change for a live preview</span></p>";
		
		$this->p("<i class='fa fa-info-circle'></i> You can change the admin URL later by editing the admin page and changing the name on the settings tab.<br /><i class='fa fa-info-circle'></i> You can change the colors later by going to Admin <i class='fa fa-angle-right'></i> Modules <i class='fa fa-angle-right detail'></i> Core <i class='fa fa-angle-right detail'></i> Admin Theme <i class='fa fa-angle-right'></i> Settings.", "detail"); 
		$this->h("Admin Account Information");
		$this->p("You will use this account to login to your ProcessWire admin. It will have superuser access, so please make sure to create a <a target='_blank' href='http://en.wikipedia.org/wiki/Password_strength'>strong password</a>.");
		$this->input("username", "User", $clean['username'], false, "name"); 
		$this->input("userpass", "Password", $clean['userpass'], false, "password"); 
		$this->input("userpass_confirm", "Password <small class='detail'>(again)</small>", $clean['userpass_confirm'], true, "password"); 
		$this->input("useremail", "Email Address", $clean['useremail'], true, "email"); 
		$this->p("<i class='fa fa-warning'></i> Please remember the password you enter above as you will not be able to retrieve it again.", "detail");
		
		$this->h("Cleanup");
		$this->p("Directories and files listed below are no longer needed and should be removed. If you choose to leave any of them in place, you should delete them before migrating to a production environment.", "detail"); 
		$this->p($this->getRemoveableItems($wire, true)); 
			
		$this->btn("Continue", 5); 
	}
	
	protected function getRemoveableItems($wire, $getMarkup = false, $removeNow = false) {

		$root = dirname(__FILE__) . '/';
		$isPost = $wire->input->post->remove_items !== null;
		$postItems = $isPost ? $wire->input->post->remove_items : array();
		if(!is_array($postItems)) $postItems = array();
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
				"<input type='checkbox' $checked $disabled name='remove_items[]' value='$name' /> $item[label] $note" .
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
						$this->ok("Completed: " . $item['label']); 
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

		$this->h("Admin Account Saved");
		$this->ok("User account saved: <b>{$user->name}</b>"); 

		$colors = $wire->sanitizer->pageName($input->post('colors')); 
		if(!in_array($colors, $this->colors)) $colors = reset($this->colors); 
		$theme = $wire->modules->getInstall('AdminThemeDefault'); 
		if($theme) {} // ignore
		$configData = $wire->modules->getModuleConfigData('AdminThemeDefault'); 
		$configData['colors'] = $colors;
		$wire->modules->saveModuleConfigData('AdminThemeDefault', $configData); 
		$this->ok("Saved admin color set <b>$colors</b> - you will see this when you login."); 

		$this->h("Complete &amp; Secure Your Installation");
		$this->getRemoveableItems($wire, false, true); 

		$this->ok("Note that future runtime errors are logged to <b>/site/assets/logs/errors.txt</b> (not web accessible).");
		$this->ok("For more configuration options see <b>/wire/config.php</b>.");
		$this->warn("Please make your <b>/site/config.php</b> file non-writable, and readable only to you and Apache.");
		$this->p("<a target='_blank' href='https://processwire.com/docs/security/file-permissions/#securing-your-site-config.php-file'>How to secure your /site/config.php file <i class='fa fa-angle-right'></i></a>");
		
		if(is_writable("./site/modules/")) wireChmod("./site/modules/", true); 

		$this->h("Use The Site!");
		$this->ok("Your admin URL is <a href='./$adminName/'>/$adminName/</a>"); 
		$this->p("If you'd like, you may change this later by editing the admin page and changing the name.", "detail"); 
		$this->btn("Login to Admin", 1, 'sign-in', false, true, "./$adminName/"); 
		$this->btn("View Site ", 1, 'angle-right', true, false, "./"); 

		// set a define that indicates installation is completed so that this script no longer runs
		if(!self::TEST_MODE) {
			file_put_contents("./site/assets/installed.php", "<?php // The existence of this file prevents the installer from running. Don't delete it unless you want to re-run the install or you have deleted ./install.php."); 
		}

	}

	/******************************************************************************************************************
	 * OUTPUT FUNCTIONS
	 *
	 */
	
	/**
	 * Report and log an error
	 * 
	 * @param string $str
	 * @return bool
	 *
	 */
	protected function err($str) {
		$this->numErrors++;
		echo "\n<li class='ui-state-error'><i class='fa fa-exclamation-triangle'></i> $str</li>";
		return false;
	}

	/**
	 * Action/warning
	 * 
	 * @param string $str
	 * @return bool
	 *
	 */
	protected function warn($str) {
		$this->numErrors++;
		echo "\n<li class='ui-state-error ui-priority-secondary'><i class='fa fa-asterisk'></i> $str</li>";
		return false;
	}
	
	/**
	 * Report success
	 * 
	 * @param string $str
	 * @return bool
	 *
	 */
	protected function ok($str) {
		echo "\n<li class='ui-state-highlight'><i class='fa fa-check-square-o'></i> $str</li>";
		return true; 
	}

	/**
	 * Output a button 
	 * 
	 * @param string $label
	 * @param string $value
	 * @param string $icon
	 * @param bool $secondary
	 * @param bool $float
	 * @param string $href
	 *
	 */
	protected function btn($label, $value, $icon = 'angle-right', $secondary = false, $float = false, $href = '') {
		$class = $secondary ? 'ui-priority-secondary' : '';
		if($float) $class .= " floated";
		$type = 'submit';
		if($href) $type = 'button';
		if($href) echo "<a href='$href'>";
		echo "\n<p><button name='step' type='$type' class='ui-button ui-widget ui-state-default $class ui-corner-all' value='$value'>";
		echo "<span class='ui-button-text'><i class='fa fa-$icon'></i> $label</span>";
		echo "</button></p>";
		if($href) echo "</a>";
		echo " ";
	}

	/**
	 * Output a headline
	 * 
	 * @param string $label
	 *
	 */
	protected function h($label) {
		echo "\n<h2>$label</h2>";
	}

	/**
	 * Output a paragraph 
	 * 
	 * @param string $text
	 * @param string $class
	 *
	 */
	protected function p($text, $class = '') {
		if($class) echo "\n<p class='$class'>$text</p>";
			else echo "\n<p>$text</p>";
	}

	/**
	 * Output an <input type='text'>
	 * 
	 * @param string $name
	 * @param string $label
	 * @param string $value
	 * @param bool $clear
	 * @param string $type
	 * @param bool $required
	 *
	 */
	protected function input($name, $label, $value, $clear = false, $type = "text", $required = true) {
		$width = 135; 
		$required = $required ? "required='required'" : "";
		$pattern = '';
		$note = '';
		if($type == 'email') {
			$width = ($width*2); 
			$required = '';
		} else if($type == 'name') {
			$type = 'text';
			$pattern = "pattern='[-_a-z0-9]{2,50}' ";
			if($name == 'admin_name') $width = ($width*2);
			$note = "<small class='detail' style='font-weight: normal;'>(a-z 0-9)</small>";
		}
		$inputWidth = $width - 15; 
		$value = htmlentities($value, ENT_QUOTES, "UTF-8"); 
		echo "\n<p style='width: {$width}px; float: left; margin-top: 0;'><label>$label $note<br /><input type='$type' name='$name' value='$value' $required $pattern style='width: {$inputWidth}px;' /></label></p>";
		if($clear) echo "\n<br style='clear: both;' />";
	}


	/******************************************************************************************************************
	 * FILE FUNCTIONS
	 *
	 */

	/**
	 * Create a directory and assign permission
	 * 
	 * @param string $path
	 * @param bool $showNote
	 * @return bool
	 *
	 */
	protected function mkdir($path, $showNote = true) {
		if(self::TEST_MODE) return true;
		if(is_dir($path) || mkdir($path)) {
			chmod($path, octdec($this->chmodDir));
			if($showNote) $this->ok("Created directory: $path"); 
			return true; 
		} else {
			if($showNote) $this->err("Error creating directory: $path"); 
			return false; 
		}
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
	protected function copyRecursive($src, $dst, $overwrite = true) {

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
	
	protected function timezones() {
		$timezones = timezone_identifiers_list();
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


}

/****************************************************************************************************/

if(!Installer::TEST_MODE && is_file("./site/assets/installed.php")) die("This installer has already run. Please delete it."); 
error_reporting(E_ALL | E_STRICT); 
$installer = new Installer();
$installer->execute();

