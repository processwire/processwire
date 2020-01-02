<?php namespace ProcessWire;

/**
 * Class to check for potential issues in the system that may require updates from the admin
 * 
 * All check* methods in this class return true if the check was a success and there are no problem,
 * or they return false if the check failed and there is a potential issue to resolve. 
 * 
 */
class SystemUpdaterChecks extends Wire {

	/**
	 * Show warning notices to user?
	 * 
	 * @var bool
	 * 
	 */
	protected $showNotices = false;

	/**
	 * Test all to force warning conditions?
	 * 
	 * @var bool
	 * 
	 */
	protected $testAll = false;

	/**
	 * Has execute been called to check all?
	 * 
	 * @var bool
	 * 
	 */
	protected $checkAll = false;

	/**
	 * @var SystemUpdater
	 * 
	 */
	protected $systemUpdater;

	/**
	 * @var array
	 * 
	 */
	protected $warnings = array();

	/**
	 * Set whether or not to show verbose notices
	 * 
	 * @param bool $showNotices
	 * 
	 */
	public function setShowNotices($showNotices = true) {
		$this->showNotices = $showNotices;
	}

	/**
	 * Set whether or not to test all checks (as if all checks failed)
	 *
	 * @param bool $testAll
	 *
	 */
	public function setTestAll($testAll = true) {
		$this->testAll = $testAll;
	}
	
	/**
	 * Run all system checks and return array of results
	 *
	 * @return array
	 *
	 */
	public function execute() {

		$this->checkAll = true;
		$results = array();
		$checks = array(
			'checkWelcome',
			'checkIndexFile',
			'checkHtaccessFile',
			'checkOtherHtaccessFiles',
			'checkInstallerFiles',
			'checkFilePermissions',
			'checkPublishedField',
			'checkLocale',
			'checkDebugMode',
		);

		foreach($checks as $method) {
			try {
				$results[$method] = $this->$method();
			} catch(\Exception $e) {
				if($this->showNotices) $this->warning("$method: " . $e->getMessage());
				$results[$method] = false;
			}
		}
		
		$this->checkAll = false;
	
		$numWarnings = count($this->warnings);
		if($this->showNotices && $numWarnings) {
			if($numWarnings > 1) $this->warning($this->_('Multiple issues detected, please review:'));
			foreach($this->warnings as $warning) {
				$this->warning($warning[0], $warning[1]); 
			}
		}
		
		$this->warnings = array();

		return $results;
	}

	/**
	 * Check that index.php file is the correct version
	 * 
	 * @return bool
	 * 
	 */
	public function checkIndexFile() {
		
		$requiredVersion = ProcessWire::indexVersion;
		$actualVersion = PROCESSWIRE;
		
		if(PROCESSWIRE < $requiredVersion || $this->testAll) {
			if($this->showNotices) {
				$warning = sprintf(
					$this->_('Please note that your root %s file is not up-to-date with this ProcessWire version, please update it when possible.'),
					$this->location('index.php')
				);
				$details = $this->versionsLabel($requiredVersion, $actualVersion);
				$this->warning($warning . $this->small($details), Notice::log | Notice::allowMarkup);
			}
			return false;
		}
		
		return true;
	}

	/**
	 * Check that main htaccess file is the correct version
	 * 
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function checkHtaccessFile() {
		
		$requiredVersion = ProcessWire::htaccessVersion;
		$htaccessFile = $this->wire('config')->paths->root . '.htaccess';
		
		if(is_readable($htaccessFile)) {
			$data = file_get_contents($htaccessFile);
			if(!preg_match('/@(?:htaccess|index)Version\s+(\d+)\b/', $data, $matches) || ((int) $matches[1]) < $requiredVersion || $this->testAll) {
				if($this->showNotices) {
					$foundVersion = isset($matches[1]) ? (int) $matches[1] : '?';
					$warning = sprintf(
						$this->_('Please note that your root %s file is not up-to-date with this ProcessWire version, please update it when possible.'),
						$this->location('.htaccess')
					);
					$details = $this->small(
						$this->versionsLabel($requiredVersion, $foundVersion) . ' ' . 
						$this->_('To suppress this warning, replace or add the following in the top of your existing .htaccess file:') . 
						$this->code("# @htaccessVersion $requiredVersion")
					);
					$this->warning("$warning$details", Notice::log | Notice::allowMarkup);
				}
				return false;
			}
		} else {
			// if .htaccess not present then this is likely an IIS or other not-offically supported server software
			// if($this->showNotices) $this->warning($this->fileNotFoundLabel($htaccessFile));
			return false;
		}
		
		return true;
	}

	/**
	 * Check that other useful htaccess files are present
	 * 
	 * @return bool
	 * 
	 */
	public function checkOtherHtaccessFiles() {
		/** @var SystemUpdater $systemUpdater */
		$systemUpdater = $this->wire('modules')->get('SystemUpdater');
		if(!$systemUpdater) return false;
		$result = true;

		/** @var SystemUpdate17 $update */
		// update 17 verifies that fallback .htaccess files are in place for 2nd layer protections
		$update = $systemUpdater->getUpdate(17);
		if($update) {
			if($update->isUseful() || $this->testAll) $result = $update->update();
		}

		return $result;
	}

	/**
	 * Check if this is the first call to checkWelcome and show a welcome message and add an active.php file if so
	 * 
	 * @return bool Returns false if active.php does not yet exist or true if it does
	 * 
	 */
	public function checkWelcome() {
		
		$activeFile = $this->wire('config')->paths->assets . 'active.php';
		$exists = is_file($activeFile);
		
		if($this->showNotices && ((!$exists && !$this->wire('config')->debug) || $this->testAll)) {
			$this->message(
				$this->strong($this->_('Welcome to ProcessWire!')) . ' ' . 
				$this->_('If this installation is currently being used for development or testing, we recommend enabling debug mode.') . ' ' .
				$this->_('Debug mode ensures all errors are visible, which can be helpful during development or troubleshooting.') . ' ' .
				$this->_('It also enables additional developer information to appear here in the admin.') . ' ' .
				sprintf($this->_('You can enable debug mode by editing your %s file and adding the following:'), $this->location('/site/config.php')) . 
				$this->code('$config->debug = true;')  .
				$this->small($this->_('Please note: this notification will not be shown again. Remember to disable debug mode before going live.')),
				Notice::allowMarkup | Notice::prepend
			);
		}
			
		if(!$exists) {
			$data = 
				"<?php // Created by ProcessWire - Do not delete this file. " .
				"The existence of this file indicates the site is confirmed active " .
				"and first-time use errors may be suppressed. Installed at: " .
				"[{$this->config->paths->root}]";
			$this->wire('files')->filePutContents($activeFile, $data);
			return false;
		}
		
		return true;
	}

	/**
	 * Check if unnecessary installer files are present
	 * 
	 * @return bool
	 * 
	 */
	public function checkInstallerFiles() {
		if(is_file($this->wire('config')->paths->root . "install.php") || $this->testAll) {
			if($this->showNotices) {
				$warning = $this->_("Security Warning: file '%s' exists and should be deleted as soon as possible.");
				$this->warning(sprintf($warning, '/install.php'), Notice::log);
			}
			return false;
		}
		return true;
	}

	/**
	 * Check for insecure file permissions
	 * 
	 * @return bool
	 * 
	 */
	public function checkFilePermissions() {
		
		// warnings about 0666/0777 file permissions
		if($this->config->chmodDir != '0777' && $this->config->chmodFile != '0666' && !$this->testAll) return true;
		if(!$this->config->chmodWarn || !$this->showNotices) return false;
		
		$warning = sprintf(
			$this->_('Warning, your %s file specifies file permissions that are too loose for many environments:'),
			$this->location('/site/config.php')
		);
		
		$code = 
			$this->code("\$config->chmodFile = '{$this->config->chmodFile}';") . 
			$this->code("\$config->chmodDir = '{$this->config->chmodDir}';"); 
		
		$link = $this->link(
			'https://processwire.com/docs/security/file-permissions/', 
			$this->_('Read "Securing file permissions" for more details')
		);
		
		$details = $this->small(
			sprintf($this->_('To suppress this warning, add the following to your %s file:'), $this->location('/site/config.php')) . ' ' . 
			$this->code('$config->chmodWarn = false;')
		);
		
		$code = str_replace(array('0666', '0777'), array('<u>0666</u>', '<u>0777</u>'), $code);
		
		$this->warning("$warning$code$link$details", Notice::allowMarkup | Notice::log);
		
		return false;
	}

	/**
	 * Check if there is a field named 'published' that should not be present
	 * 
	 * @return bool
	 * 
	 */
	public function checkPublishedField() {
		if(!$this->wire('fields')->get('published') && !$this->testAll) return true;
		if($this->showNotices) $this->warning(
			$this->_('Warning: you have a field named “published” that conflicts with the page “published” property.') . ' ' . 
			$this->_('Please rename your field field to something else and update any templates referencing it.')
		);
		return false;
	}

	/**
	 * Check locale setting
	 * 
	 * Warning about servers with locales that break UTF-8 strings called by basename
	 * and other file functions, due to a long running PHP bug 
	 * 
	 * @return bool
	 * 
	 */
	public function checkLocale() {
		
		if(basename("§") !== "" && !$this->testAll) return true;
		if(!$this->showNotices) return false;
		
		$example = stripos(PHP_OS, 'WIN') === 0 ? 'en-US' : 'en_US.UTF-8';
		$localeLabel = $this->_('Your current locale setting is “%s”.') . ' ';
		$warning = $this->_('Note: your current server locale setting isn’t working as expected with the UTF-8 charset and may cause minor issues.');
		$msg = '';
		
		if($this->wire('modules')->isInstalled('LanguageSupport')) {
			// language support installed
			$textdomain = 'wire--modules--languagesupport--languagesupport-module';
			$locale = __('C', $textdomain);
			if(empty($locale)) $locale = setlocale(LC_CTYPE, 0);
			$msg .= ' ' . 
				sprintf($localeLabel, $locale) . ' ' . 
				sprintf(
					$this->_('Please translate the “C” locale setting for each language to the compatible locale in %s'),
					$this->location('/wire/modules/LanguageSupport/LanguageSupport.module') . ':'
				);
			
			foreach($this->wire('languages') as $language) {
				$url = $this->wire('config')->urls->admin . 
					"setup/language-translator/edit/?" . 
					"language_id=$language->id&" .
					"textdomain=$textdomain&" .
					"filename=wire/modules/LanguageSupport/LanguageSupport.module";
				$msg .= "<div>" . $this->link($url, $language->get('title|name')) . "</div>";
			}
			
			$msg .= $this->small(
				sprintf(
					$this->_('For example, the locale setting for US English might be: %s'), 
					$this->strong($example)
				)
			);
			
		} else {
			// no language support installed
			$locale = setlocale(LC_CTYPE, 0);
			$msg .=
				sprintf($localeLabel, $locale) .
				sprintf(
					$this->_('Please add this to your %1$s file (adjust “%2$s” as needed):'), 
					$this->location('/site/config.php'), 
					$example
				) . 
				$this->code("setlocale(LC_ALL, '$example');");
		}
		
		$this->warning("$warning $msg", Notice::allowMarkup);
		
		return false;
	
	}

	/**
	 * Check for debug mode
	 * 
	 * return bool Always returns true, as there is no way to fail this test
	 * 
	 */
	public function checkDebugMode() {
		if(!$this->wire('config')->debug && !$this->testAll) return true;
		if($this->showNotices) $this->message('icon-bug '  .
			$this->_('The site is in debug mode, suitable for sites in development') . 
			$this->small(
				sprintf(
					$this->_('If this is a live/production site, you should disable debug mode in %1$s with: %2$s'), 
					$this->location('/site/config.php'),
					'<u>$config->debug = false;</u>'
				)
			),
			Notice::allowMarkup | Notice::anonymous
		);
		return true;
	}
	
	/*********************************************************************************************/
	
	public function warning($text, $flags = 0) {
		if($this->checkAll && $this->showNotices) {
			$this->warnings[] = array($text, $flags);
		} else {
			parent::warning($text, $flags);
		}
		return $this;
	}

	protected function versionsLabel($requiredVersion, $foundVersion) {
		return sprintf($this->_('Required version: %1$s, Found version: %2$s.'), "$requiredVersion", "$foundVersion");
	}

	protected function fileNotFoundLabel($file) {
		return sprintf($this->_('Unable to locate required file: %s'), $file);
	}

	protected function code($str, $block = true) {
		$style = 'font-family:monospace;font-size:14px;';
		if($block) $style .= "background:rgba(255,255,255,0.3);padding:5px 7px;border:1px solid rgba(0,0,0,0.1)";
		$out = "<span style='$style'>$str</span>";
		if($block) $out = "<div style='margin:5px 0'>$out</div>";
		return $out;
	}

	protected function small($str, $block = true) {
		$tag = $block ? 'div' : 'span';
		return "<$tag><small class='ui-priority-secondary'>$str</small></$tag>";
	}

	protected function strong($str) {
		return "<strong>$str</strong>";
	}

	protected function link($href, $label, $newWindow = true) {
		$target = $newWindow ? "target='_blank'" : "";
		return wireIconMarkup('angle-right') . " <a href='$href' $target>$label</a> ";
	}

	protected function location($str) {
		return "<u>$str</u>";
	}

	
}