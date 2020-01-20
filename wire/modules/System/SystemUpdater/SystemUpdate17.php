<?php namespace ProcessWire;

/**
 * Apply secondary layer of .htaccess protections for various site directories
 * as a fallback in case root .htaccess ever becomes corrupted or goes missing
 *
 */
class SystemUpdate17 extends SystemUpdate {

	/**
	 * Is this update being applied automatically by SystemUpdater?
	 * 
	 * @var bool
	 * 
	 */
	protected $auto = false;
	
	protected $detailsUrl = 'https://processwire.com/blog/posts/pw-3.0.135/';
	
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	
	public function executeAtReady() {
		$this->auto = true;
		if(!$this->update()) return;
		$this->message(
			"Details: <a href='$this->detailsUrl' target='_blank'>$this->detailsUrl</a>",
			Notice::allowMarkup
		);
		$this->updater->saveSystemVersion(17);
	}

	/**
	 * Apply the update
	 * 
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function update() {
		
		if(!$this->isApache()) {
			$this->warning(
				"Update skipped because Apache not detected. " . 
				"Please <a href='$this->detailsUrl' target='_blank'>see this post</a> for details on how to apply this update manually.",
				Notice::allowMarkup | Notice::noGroup
			);
			return true;
		}
	
		$blockAll = 
			"\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>" .  // Apache 2.4+
			"\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>"; // Prior to Apache 2.4
	
		$denyAll = str_replace("\n", "\n  ", $blockAll); // indented blockAll
		
		$rules = array(
			'all' => array(
				'label' => 'block all access',
				'content' => ltrim($blockAll), 
			),
			'php' => array(
				'label' => 'block all PHP files',
				'content' => "<FilesMatch \"\.(php|module|inc)$\">$denyAll\n</FilesMatch>",
			),	
			'rifc' => array(
				'label' => 'block some PHP files',
				'content' => "<FilesMatch \"^(ready|init|finished|config)\.php$\">$denyAll\n</FilesMatch>",
			)
		);

		$cachePath = $this->wire('config')->paths->cache;
		$siteRootDir = rtrim($this->wire('config')->paths->site, '/');
		$siteRootUrl = rtrim($this->wire('config')->urls->site, '/');
		$sitePathRules = array(
			'/' => 'rifc',
			'/assets/' => 'php',
			'/assets/cache/' => 'all',
			'/assets/backups/' => 'all',
			'/assets/logs/' => 'all',
			'/templates/' => 'php',
			'/modules/' => 'php',
		);
	
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$numErrors = 0;
		
		foreach($sitePathRules as $dir => $ruleName) {
		
			$rule = $rules[$ruleName];
			$header = "# Start ProcessWire:pwb$ruleName (update 17)";
			$footer = "# End ProcessWire:pwb$ruleName";
			$summary = "# $rule[label] (optional fallback if root .htaccess missing)";
			$location = "$siteRootUrl$dir.htaccess";
			$content = "$header\n$summary\n$rule[content]\n$footer";
			$file = $siteRootDir . $dir . '.htaccess';
			$url = $siteRootUrl . $dir . '.htaccess';
			$path = dirname($file);
			
			if(!is_dir($path)) {
				if($this->auto) $this->message("Skipped $url (directory not present)"); 
				continue;
			}
			
			if(file_exists($file)) {
				// existing .htaccess file already present
				if($this->auto) $this->message("Skipped: $url (already exists)");
				continue;
			} 
			
			// no .htaccess file currently present
			$writable = is_writable(dirname($file)); 
			$data = $content;
			$actionLabel = 'Created';
			
			if(!$writable) {
				// file not writable, so we will create a temporary file in cache instead (for optional manual copy)
				$file = $cachePath . str_replace(array('/', "\\", '.', '--'), '-', trim(str_replace($siteRootUrl, 'site', $url), '/')) . '.txt';
				$tmpUrl = str_replace($siteRootDir, $siteRootUrl, $file);
				if(file_exists($file)) {
					// file already exists so we can skip
					if($this->auto) $this->message("Ignored: $tmpUrl (already exists)");
					continue;
				}
				$writable = is_writable(dirname($file));
				if($writable) {
					$this->warning("Unable to write updates to '$url', so writing to '$tmpUrl' instead, in case you want to copy manually.");
					$data = "# Intended location of this file: $location\n$data";
					$actionLabel = 'Created';
				}
				$url = $tmpUrl;
			}
			
			if(!$writable) {
				if($ruleName !== 'all') {
					if($this->auto) $this->message("Ignored: $url");
				} else {
					$this->error("Unable to write: $url");
					$numErrors++;
				}
				continue;
			}
			
			try {
				if($files->filePutContents($file, "$data\n", LOCK_EX)) {
					$this->message("$actionLabel: $url"); 
				} else {
					throw new WireException("Unable to write: $url"); 
				}
			} catch(\Exception $e) {
				$this->error($e->getMessage());
				$numErrors++;
			}
		}
		
		return $numErrors === 0;
	}

	/**
	 * Are we running under Apache?
	 * 
	 * @return bool
	 * 
	 */
	public function isApache() {

		$software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';

		if(stripos($software, 'microsoft-iis') !== false) return false;
		if(stripos($software, 'nginx') !== false) return false;
		if(stripos($software, 'apache') !== false) return true;

		if(function_exists('apache_get_version') && stripos(apache_get_version(), 'Apache') !== false) return true;
		if(function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) return true;
		if(getenv('HTTP_MOD_REWRITE') == 'On') return true;

		$rootPath = $this->wire('config')->paths->root;
		if(file_exists($rootPath . 'Web.config') || file_exists($rootPath . 'web.config')) return false; // IIS
		if(file_exists($rootPath . '.htaccess')) return true;

		return false;
	}

	/**
	 * Is this update useful for this installation?
	 * 
	 * @return bool
	 * 
	 */
	public function isUseful() {
		if(!$this->isApache()) return false;
		$f = '.htaccess';
		$paths = $this->wire('config')->paths;
		$assets = $paths->assets;
		if(!file_exists($assets . $f)) return true;
		if(!file_exists($assets . "logs/$f")) return true;
		if(!file_exists($assets . "cache/$f")) return true;
		if(!file_exists($assets . "backups/$f")) return true;
		if(!file_exists($paths->templates . $f)) return true;
		if(!file_exists($paths->site . $f)) return true;
		if(!file_exists($paths->site . "modules/$f")) return true;
		return false;
	}

}

