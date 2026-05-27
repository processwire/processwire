<?php namespace ProcessWire;

/**
 * Tests for ProcessWire LanguageSupport and $languages API variable
 *
 */
class WireTest_LanguageSupport extends WireTest {

	/**
	 * Original user language
	 *
	 * @var Language|null
	 *
	 */
	protected $originalLanguage = null;

	/**
	 * Temporary language created by this test
	 *
	 * @var Language|null
	 *
	 */
	protected $testLanguage = null;

	/**
	 * Name used for temporary language
	 *
	 */
	const testLanguageName = 'wiretests-language';

	/**
	 * Temporary PHP file used for LanguagePorter export discovery
	 *
	 */
	const testExportFile = 'site/templates/wiretests-language-export.php';

	/**
	 * Allow this test only when LanguageSupport is installed
	 *
	 * @return bool
	 *
	 */
	public function allow() {
		return $this->wire()->languages instanceof Languages;
	}

	/**
	 * Setup before test
	 *
	 */
	public function init() {
		$this->originalLanguage = $this->wire()->user->language;
		if(!$this->getOtherLanguage()) $this->createTestLanguage();
	}

	/**
	 * Run test
	 *
	 */
	public function execute() {
		$this->testLanguagesApi();
		$this->testLanguageObject();
		$this->testLanguageSwitching();
		$this->testLanguagesPageFieldValue();
		$this->testInputfieldLanguageValues();
		$this->testPageLanguageHooks();
		$this->testTranslator();
		$this->testPorter();
		$this->testPageNameHooks();
		$this->testLocaleHelpers();
	}

	/**
	 * Restore original user language
	 *
	 */
	public function finish() {
		if($this->originalLanguage && $this->originalLanguage->id) {
			$this->wire()->user->setQuietly('language', $this->originalLanguage);
		}
		$this->deleteTestExportFile();
		$this->deleteTestLanguage();
	}

	/**
	 * Test $languages API variable
	 *
	 */
	protected function testLanguagesApi() {
		$languages = $this->wire()->languages;
		$default = $languages->getDefault();
		$current = $languages->getLanguage();

		$this->check('$languages is Languages', true, $languages instanceof Languages);
		$this->check('$languages->default is default Language', $default->id, $languages->default->id);
		$this->check('getDefault() returns Language', true, $default instanceof Language);
		$this->check('getLanguage() returns current user language', $this->wire()->user->language->id, $current->id);
		$this->check('getLanguage(name) returns named language', $default->id, $languages->getLanguage($default->name)->id);
		$this->check('getLanguage(id) returns language by ID', $default->id, $languages->getLanguage($default->id)->id);
		$this->check('getLanguage(object) returns same Language', $default->id, $languages->getLanguage($default)->id);
		$this->check('getLanguage(missing) returns null', null, $languages->getLanguage('wiretests-missing-language'));
		$this->check('$languages is iterable', true, $this->countLanguages($languages) >= 1);
		$this->check('getAll() includes default language', true, $languages->getAll()->has($default));
		$this->check('findNonDefault() count matches non-default languages', $this->countNonDefaultLanguages(), count($languages->findNonDefault()));
		$this->check('findOther(default) excludes default language', false, $languages->findOther($default)->has($default));
	}

	/**
	 * Test Language object
	 *
	 */
	protected function testLanguageObject() {
		$languages = $this->wire()->languages;
		$default = $languages->getDefault();

		$this->check('Default language is Language', true, $default instanceof Language);
		$this->check('Language extends Page', true, $default instanceof Page);
		$this->check('Language::isDefault() true for default', true, $default->isDefault());
		$this->check('Language isDefault property true for default', true, $default->isDefault);
		$this->check('Language::getPagesManager() returns $languages', true, $default->getPagesManager() === $languages);
		$this->check('Language translator property returns LanguageTranslator', true, $default->translator instanceof LanguageTranslator);
	}

	/**
	 * Test request-scoped language switching
	 *
	 */
	protected function testLanguageSwitching() {
		$languages = $this->wire()->languages;
		$user = $this->wire()->user;
		$default = $languages->getDefault();
		$other = $this->getOtherLanguage();

		$languages->setLanguage($default);
		$this->check('setLanguage(default) makes default current', true, $default->isCurrent());
		$this->check('setLanguage(same) returns false', false, $languages->setLanguage($default));

		if($other) {
			$changed = $languages->setLanguage($other);
			$this->check('setLanguage(other) returns true when changed', true, $changed);
			$this->check('setLanguage(other) changes current user language', $other->id, $user->language->id);
			$this->check('Other language isCurrent() true after setLanguage()', true, $other->isCurrent());
			$this->check('unsetLanguage() restores previous language', true, $languages->unsetLanguage());
			$this->check('unsetLanguage() restored default language', $default->id, $user->language->id);

			$languages->setLanguage($other);
			$languages->setDefault();
			$this->check('setDefault() changes current user language to default', $default->id, $user->language->id);
			$languages->unsetDefault();
			$this->check('unsetDefault() restores language before setDefault()', $other->id, $user->language->id);
			$languages->unsetLanguage();
		}
	}

	/**
	 * Test LanguagesPageFieldValue behavior
	 *
	 */
	protected function testLanguagesPageFieldValue() {
		$languages = $this->wire()->languages;
		$user = $this->wire()->user;
		$default = $languages->getDefault();
		$other = $this->getOtherLanguage();
		$page = $this->getTestPage();
		$field = $this->wire()->fields->get('title');

		$value = new LanguagesPageFieldValue($page, $field, array(
			'default' => 'Default value',
		));

		$this->check('LanguagesPageFieldValue created', true, $value instanceof LanguagesPageFieldValue);
		$this->check('getDefaultValue() returns default value', 'Default value', $value->getDefaultValue());
		$this->check('getLanguageValue(default object) returns default value', 'Default value', $value->getLanguageValue($default));
		$this->check('getLanguageValue(default name) returns default value', 'Default value', $value->getLanguageValue($default->name));
		$this->check('getLanguageValue(default ID) returns default value', 'Default value', $value->getLanguageValue($default->id));
		$this->check('__toString() returns current language value', 'Default value', (string) $value);

		if($other) {
			$value->setLanguageValue($other, 'Other value');
			$this->check('setLanguageValue(object) sets other language value', 'Other value', $value->getLanguageValue($other));
			$value->setLanguageValues(array($other->name => 'Other value 2'));
			$this->check('setLanguageValues(name) sets other language value', 'Other value 2', $value->getLanguageValue($other));
			$user->setQuietly('language', $other);
			$this->check('__toString() resolves to current language value', 'Other value 2', (string) $value);
			$value->setLanguageValue($other, '');
			$this->check('Blank language value inherits default by default', 'Default value', (string) $value);
			$user->setQuietly('language', $default);
		}

		$value->setLanguageValues(array('default' => '', $default->id => 'Default by ID'), true);
		$this->check('setLanguageValues(id) sets default value', 'Default by ID', $value->getDefaultValue());
		$this->check('getNonEmptyValue() returns non-empty value', 'Default by ID', $value->getNonEmptyValue('fallback'));
	}

	/**
	 * Test Inputfield language value hooks
	 *
	 */
	protected function testInputfieldLanguageValues() {
		$languages = $this->wire()->languages;
		$modules = $this->wire()->modules;
		$default = $languages->getDefault();
		$other = $this->getOtherLanguage();

		$inputfield = $modules->get('InputfieldText');
		$inputfield->name = 'wiretests_language';
		$inputfield->value = 'Default input';

		$this->check('Inputfield getLanguageValue(default) returns value', 'Default input', $inputfield->getLanguageValue($default));
		$inputfield->setLanguageValue($default, 'Default input 2');
		$this->check('Inputfield setLanguageValue(default) updates value', 'Default input 2', $inputfield->value);

		if($other) {
			$inputfield->setLanguageValue($other, 'Other input');
			$this->check('Inputfield setLanguageValue(other) stores value{id}', 'Other input', $inputfield->get("value$other"));
			$this->check('Inputfield getLanguageValue(other) returns other value', 'Other input', $inputfield->getLanguageValue($other));
		}
	}

	/**
	 * Test Page language value hooks without saving
	 *
	 */
	protected function testPageLanguageHooks() {
		$languages = $this->wire()->languages;
		$default = $languages->getDefault();
		$other = $this->getOtherLanguage();
		$page = $this->getTestPage();
		$field = $this->wire()->fields->get('title');
		$original = $page->getUnformatted('title');
		$value = new LanguagesPageFieldValue($page, $field, array('default' => 'Default page title'));

		$page->set('title', $value);
		$page->setLanguageValue($default, 'title', 'Default hook title');
		$this->check('Page::setLanguageValue(default, field) sets value', 'Default hook title', $page->getLanguageValue($default, 'title'));
		$this->check('Page::getLanguageValues(field) includes default', 'Default hook title', $page->getLanguageValues('title')[$default->name]);

		if($other) {
			$page->setLanguageValues('title', array($other->name => 'Other hook title'));
			$this->check('Page::setLanguageValues(name) sets other language value', 'Other hook title', $page->getLanguageValue($other, 'title'));
			$values = $page->getLanguageValues('title', array($default->name, $other->name));
			$this->check('Page::getLanguageValues(languages) includes requested other language', 'Other hook title', $values[$other->name]);
		}

		$page->set('title', $original);
	}

	/**
	 * Test LanguageTranslator basics without writing files
	 *
	 */
	protected function testTranslator() {
		$language = $this->wire()->languages->getDefault();
		$translator = $language->translator;
		$file = 'site/templates/wiretests-language.php';
		$textdomain = $translator->filenameToTextdomain($file);
		$hash = $translator->setTranslation($textdomain, 'Hello language', 'Translated language');

		$this->check('filenameToTextdomain() converts site path', 'site--templates--wiretests-language-php', $textdomain);
		$this->check('setTranslation() returns hash', true, strlen($hash) === 32);
		$this->check('getTranslation() returns in-memory translation', 'Translated language', $translator->getTranslation($textdomain, 'Hello language'));
		$this->check('getTranslationOrFalse() returns false on miss', false, $translator->getTranslationOrFalse($textdomain, 'Missing language text'));
		$this->check('getTranslations() includes set translation', true, isset($translator->getTranslations($textdomain)[$hash]));
	}

	/**
	 * Test LanguagePorter availability
	 *
	 */
	protected function testPorter() {
		$language = $this->wire()->languages->getDefault();
		$this->check('Language::porter() returns LanguagePorter', true, $language->porter() instanceof LanguagePorter);
		$this->check('Language porter property returns LanguagePorter', true, $language->porter instanceof LanguagePorter);

		$this->writeTestExportFile();
		$csv = $language->porter->exportCsv(array(
			'source' => 'site/templates/',
			'scope' => 'all',
			'exportTo' => 'string',
		));

		$this->check('LanguagePorter exportCsv(scope=all) returns CSV header', true, strpos($csv, 'en,default,description,file,hash') === 0);
		$this->check('LanguagePorter exportCsv(scope=all) discovers source file', true, strpos($csv, self::testExportFile) !== false);
		$this->check('LanguagePorter exportCsv(scope=all) exports phrase text', true, strpos($csv, 'WireTests porter export') !== false);

		$other = $this->getOtherLanguage();
		$csv = $this->translateCsvString($csv, 'WireTests porter export', 'WireTests porter translated');
		$count = $other->porter->importCsvStr($csv, array('quiet' => true));
		$textdomain = $other->translator->filenameToTextdomain(self::testExportFile);

		$this->check('LanguagePorter importCsvStr() imports CSV string rows', true, $count > 0);
		$this->check('LanguagePorter importCsvStr() saves imported translation', 'WireTests porter translated', $other->translator->getTranslation($textdomain, 'WireTests porter export'));

		$csv = $this->translateCsvString($csv, 'WireTests porter export', 'WireTests porter translated again');
		$count = $other->porter->importCsv($csv, array('quiet' => true));
		$other->translator->unloadTextdomain($textdomain);

		$this->check('LanguagePorter importCsv() detects CSV string input', true, $count > 0);
		$this->check('LanguagePorter importCsv() saves detected CSV string', 'WireTests porter translated again', $other->translator->getTranslation($textdomain, 'WireTests porter export'));
	}

	/**
	 * Test LanguageSupportPageNames hooks when installed
	 *
	 */
	protected function testPageNameHooks() {
		$modules = $this->wire()->modules;
		if(!$modules->isInstalled('LanguageSupportPageNames')) {
			$this->ok('LanguageSupportPageNames not installed, skipping page name hooks');
			return;
		}

		$languages = $this->wire()->languages;
		$default = $languages->getDefault();
		$other = $this->getOtherLanguage();
		$page = $this->wire()->pages->newPage(array(
			'template' => $this->getTestPage()->template,
			'parent' => $this->getTestPage()->parent,
			'name' => 'wiretests-language-name',
			'title' => 'WireTests language name',
		));

		$page->setLanguageName($default, 'wiretests-language-name-default');
		$this->check('Page::setLanguageName(default) sets name', 'wiretests-language-name-default', $page->getLanguageName($default));
		$this->check('Page::getLanguageName() includes default', 'wiretests-language-name-default', $page->getLanguageName()[$default->name]);
		$page->setLanguageStatus($default, true);
		$this->check('Page::getLanguageStatus(default) returns true', true, $page->getLanguageStatus($default));

		if($other) {
			$page->setLanguageName($other, 'wiretests-language-name-other');
			$this->check('Page::setLanguageName(other) sets language name', 'wiretests-language-name-other', $page->getLanguageName($other));
			$page->setLanguageStatus($other, false);
			$this->check('Page::setLanguageStatus(other,false) stores inactive status', false, $page->getLanguageStatus($other));
			$page->setLanguageStatus(array($other->name => true));
			$this->check('Page::setLanguageStatus(array) stores active status', true, $page->getLanguageStatus($other));
		}
	}

	/**
	 * Test locale helpers in read-only/get mode
	 *
	 */
	protected function testLocaleHelpers() {
		$languages = $this->wire()->languages;
		$default = $languages->getDefault();
		$locale = $languages->getLocale();

		$this->check('Languages::getLocale() returns locale string or false', true, is_string($locale) || $locale === false);
		$this->check('Languages::getLocale(category, language) returns locale string or false', true, is_string($languages->getLocale(LC_ALL, $default)) || $languages->getLocale(LC_ALL, $default) === false);
		$this->check('Language::getLocale() proxies Languages::getLocale()', $languages->getLocale(LC_ALL, $default), $default->getLocale());
	}

	/**
	 * Get first non-default language, if present
	 *
	 * @return Language|null
	 *
	 */
	protected function getOtherLanguage() {
		if($this->testLanguage && $this->testLanguage->id) return $this->testLanguage;
		foreach($this->wire()->languages as $language) {
			if(!$language->isDefault()) return $language;
		}
		return null;
	}

	/**
	 * Create temporary non-default language if needed
	 *
	 */
	protected function createTestLanguage() {
		$languages = $this->wire()->languages;
		$language = $languages->getLanguage(self::testLanguageName);
		if($language) return;

		$language = $languages->add(self::testLanguageName);
		if(!$language || !$language->id) $this->fail('Unable to create temporary language');

		$language->title = 'WireTests Language';
		$languages->save($language);
		$languages->reloadLanguages();
		$this->testLanguage = $languages->getLanguage(self::testLanguageName);

		$this->check('Temporary test language created', true, $this->testLanguage instanceof Language);
	}

	/**
	 * Delete temporary language created by this test
	 *
	 */
	protected function deleteTestLanguage() {
		if(!$this->testLanguage || !$this->testLanguage->id) return;

		$languages = $this->wire()->languages;
		$language = $languages->get($this->testLanguage->id);
		if($language instanceof Language && $language->id) {
			$languageID = $language->id;
			$languages->delete($language);
			$this->wire()->pages->uncacheAll();
			$languages->reloadLanguages();
			$this->cleanupLanguageRuntimeReferences($languageID);
			if($this->originalLanguage && $this->originalLanguage->id) {
				$this->wire()->user->setQuietly('language', $this->originalLanguage);
			}
		}

		$this->testLanguage = null;
	}

	/**
	 * Write temporary source file used by LanguagePorter discovery test
	 *
	 */
	protected function writeTestExportFile() {
		$filename = $this->wire()->config->paths->root . self::testExportFile;
		$data = "<?php namespace ProcessWire;\n__('WireTests porter export', __FILE__); // Porter export test\n";
		file_put_contents($filename, $data);
	}

	/**
	 * Delete temporary source file used by LanguagePorter discovery test
	 *
	 */
	protected function deleteTestExportFile() {
		$filename = $this->wire()->config->paths->root . self::testExportFile;
		if(is_file($filename)) unlink($filename);
	}

	/**
	 * Replace one translation cell in a CSV string
	 *
	 * @param string $csv
	 * @param string $original
	 * @param string $translated
	 * @return string
	 *
	 */
	protected function translateCsvString($csv, $original, $translated) {
		$input = fopen('php://temp', 'r+');
		$output = fopen('php://temp', 'r+');
		fwrite($input, $csv);
		rewind($input);

		while(($row = fgetcsv($input, 8192, ',')) !== false) {
			if(isset($row[0]) && $row[0] === $original) $row[1] = $translated;
			fputcsv($output, $row);
		}

		rewind($output);
		$csv = stream_get_contents($output);
		fclose($input);
		fclose($output);

		return $csv;
	}

	/**
	 * Remove request-local references for a deleted temporary language
	 *
	 * @param int $languageID
	 *
	 */
	protected function cleanupLanguageRuntimeReferences($languageID) {
		$languageID = (int) $languageID;
		unset(PageProperties::$languageProperties["name$languageID"]);
		unset(PageProperties::$languageProperties["status$languageID"]);

		$fields = $this->wire()->fields;
		$property = new \ReflectionProperty($fields, 'nativeNamesLocal');
		$property->setAccessible(true);
		$nativeNamesLocal = $property->getValue($fields);
		unset($nativeNamesLocal["name$languageID"]);
		unset($nativeNamesLocal["status$languageID"]);
		$property->setValue($fields, $nativeNamesLocal);

		foreach(array('PageFinder', 'PageFinder2') as $className) {
			$class = __NAMESPACE__ . "\\$className";
			$property = new \ReflectionProperty($class, 'pagesColumns');
			$property->setAccessible(true);
			$pagesColumns = $property->getValue();
			foreach($pagesColumns as $key => $columns) {
				unset($pagesColumns[$key]["name$languageID"]);
				unset($pagesColumns[$key]["status$languageID"]);
			}
			$property->setValue(null, $pagesColumns);
		}
	}

	/**
	 * Count iterable languages
	 *
	 * @param Languages $languages
	 * @return int
	 *
	 */
	protected function countLanguages(Languages $languages) {
		$n = 0;
		foreach($languages as $language) {
			if($language instanceof Language) $n++;
		}
		return $n;
	}

	/**
	 * Count non-default languages
	 *
	 * @return int
	 *
	 */
	protected function countNonDefaultLanguages() {
		$n = 0;
		foreach($this->wire()->languages as $language) {
			if(!$language->isDefault()) $n++;
		}
		return $n;
	}
}
