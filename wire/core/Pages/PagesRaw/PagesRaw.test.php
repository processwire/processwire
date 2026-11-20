<?php namespace ProcessWire;

/**
 * Tests for PagesRaw ($pages->findRaw() and $pages->getRaw())
 *
 */
class WireTest_PagesRaw extends WireTest {

	protected $prefix = 'wiretest-pages-raw';

	public function execute() {
		$this->testPageReferenceStatus();
		$this->testFieldtypeRawValue();
		$this->testPaths();
	}

	/**
	 * Remove fixtures from this or an earlier (interrupted) run
	 *
	 */
	protected function cleanup() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$templates = $this->wire()->templates;
		$fieldgroups = $this->wire()->fieldgroups;
		$name = str_replace('-', '_', $this->prefix);
		foreach($pages->find("name^=$this->prefix-, include=all", array('cache' => false)) as $page) {
			$pages->delete($page);
		}
		$template = $templates->get($name);
		if($template && $template->id) $templates->delete($template);
		$fieldgroup = $fieldgroups->get($name);
		if($fieldgroup && $fieldgroup->id) $fieldgroups->delete($fieldgroup);
		foreach(array("{$name}_ref", "{$name}_raw") as $fieldName) {
			$field = $fields->get($fieldName);
			if($field && $field->id) $fields->delete($field);
		}
	}

	/**
	 * Create a template (and fieldgroup) for test pages having title and given field
	 * 
	 * @param Field|null $field
	 * @return Template
	 *
	 */
	protected function createTemplate(?Field $field = null) {
		$name = str_replace('-', '_', $this->prefix);
		$fieldgroup = new Fieldgroup();
		$fieldgroup->name = $name;
		$fieldgroup->add($this->wire()->fields->get('title'));
		if($field) $fieldgroup->add($field);
		$fieldgroup->save();
		$template = new Template();
		$template->name = $name;
		$template->fieldgroup = $fieldgroup;
		$template->save();
		return $template;
	}

	/**
	 * Page references exclude unpublished pages unless allowed, and page_reference_status adjusts that
	 *
	 * processwire/processwire-issues#1969
	 *
	 */
	protected function testPageReferenceStatus() {
		$wire = $this->wire();
		$pages = $wire->pages;
		$fields = $wire->fields;
		$prefix = $this->prefix;
		$name = str_replace('-', '_', $prefix);
		$refName = "{$name}_ref";

		$this->cleanup();

		try {
			$field = new Field();
			$field->type = $wire->modules->get('FieldtypePage');
			$field->name = $refName;
			$field->set('derefAsPage', 0);
			$fields->save($field);

			$template = $this->createTemplate($field);

			$home = $pages->get(1);
			$pub = $pages->add($template, $home, "$prefix-pub", array('title' => 'Published'));
			$hidden = $pages->add($template, $home, "$prefix-hidden", array('title' => 'Hidden'));
			$hidden->addStatus(Page::statusHidden);
			$hidden->save();
			$unpub = $pages->add($template, $home, "$prefix-unpub", array('title' => 'Unpublished'));
			$unpub->addStatus(Page::statusUnpublished);
			$unpub->save();
			$host = $pages->add($template, $home, "$prefix-host", array('title' => 'Host'));
			$host->of(false);
			$host->set($refName, array($pub, $hidden, $unpub));
			$host->save();
			$pages->uncacheAll();

			$sel = "id=$host->id";
			$ids = array($pub->id, $hidden->id);
			$allIds = array($pub->id, $hidden->id, $unpub->id);
			$titles = array($pub->id => 'Published', $hidden->id => 'Hidden');

			// default: same as the unformatted field value, which excludes unpublished pages
			$value = $pages->getRaw($sel, $refName);
			$this->check('IDs exclude unpublished reference', $ids, array_map('intval', $value));
			$expected = $pages->get($sel)->getUnformatted($refName)->explode('id');
			$this->check('IDs match unformatted field value', $expected, array_map('intval', $value));
			$value = $pages->getRaw($sel, "$refName.title");
			$this->check('subfield excludes unpublished reference', $titles, $value);
			$value = $pages->getRaw($sel, "$refName.*");
			$this->check('all columns exclude unpublished reference', $ids, array_map('intval', array_column($value, 'data')));
			$value = $pages->getRaw($sel, "$refName.sort");
			$this->check('sort column excludes unpublished reference', 2, count($value));
			$value = $pages->getRaw($sel, array("$refName.id", "$refName.title"));
			$this->check('multiple subfields exclude unpublished reference', $ids, array_keys($value[$refName]));
			$value = $pages->getRaw($sel);
			$this->check('all fields exclude unpublished reference', $ids, array_map('intval', $value[$refName]));

			// page_reference_status in selector
			$tests = array(
				'<=max' => $allIds,
				'<unpublished' => $ids,
				'=unpublished' => array($unpub->id),
				'!=unpublished' => $ids,
				'>=unpublished' => array($unpub->id),
				'<hidden' => array($pub->id),
				'=hidden' => array($hidden->id),
				'!=hidden' => array($pub->id, $unpub->id),
			);
			foreach($tests as $status => $expected) {
				$value = $pages->getRaw("$sel, page_reference_status$status", "$refName.title");
				$this->check("page_reference_status$status", $expected, array_keys($value));
			}

			// page_reference_status as option
			$value = $pages->findRaw($sel, $refName, array('page_reference_status' => '<=max'));
			$this->check('page_reference_status option', $allIds, array_map('intval', $value[$host->id]));

			$exception = '';
			try {
				$pages->getRaw("$sel, page_reference_status<bogus", $refName);
			} catch(WireException $e) {
				$exception = 'unknown';
			}
			$this->check('unknown status throws exception', 'unknown', $exception);
			$exception = '';
			try {
				$pages->getRaw("$sel, page_reference_status%=hidden", $refName);
			} catch(WireException $e) {
				$exception = 'operator';
			}
			$this->check('unsupported operator throws exception', 'operator', $exception);

			// allowUnpub field setting includes unpublished references, as the unformatted value does
			$field->set('allowUnpub', 1);
			$fields->save($field);
			$value = $pages->getRaw($sel, $refName);
			$this->check('allowUnpub includes unpublished reference', $allIds, array_map('intval', $value));
			$value = $pages->getRaw("$sel, page_reference_status<unpublished", $refName);
			$this->check('page_reference_status overrides allowUnpub', $ids, array_map('intval', $value));

		} finally {
			$this->cleanup();
		}
	}

	/**
	 * Fieldtype::getRawValue() converts encoded values from the field's table
	 *
	 * processwire/processwire-issues#1968
	 *
	 */
	protected function testFieldtypeRawValue() {
		$wire = $this->wire();
		$pages = $wire->pages;
		$fields = $wire->fields;
		$prefix = $this->prefix;
		$name = str_replace('-', '_', $prefix);
		$rawName = "{$name}_raw";

		$this->cleanup();

		try {
			$field = new Field();
			$field->type = $wire->wire(new FieldtypeWireTestRawValue());
			$field->name = $rawName;
			$fields->save($field);
			$template = $this->createTemplate($field);
			$page = $pages->add($template, $pages->get(1), "$prefix-raw", array('title' => 'Raw'));
			$page->of(false);
			$page->set($rawName, 'pid2,pid3');
			$page->save();
			$pages->uncacheAll();
			FieldtypeWireTestRawValue::$pageIds = array();

			$sel = "id=$page->id";
			$expected = array(2, 3);
			$this->check('stored value is encoded', 'pid2,pid3', $wire->database->query("SELECT data FROM field_$rawName WHERE pages_id=$page->id")->fetchColumn());
			$this->check('getRaw(field) uses getRawValue()', $expected, $pages->getRaw($sel, $rawName));
			$this->check('getRaw([field]) uses getRawValue()', array($rawName => $expected), $pages->getRaw($sel, array($rawName)));
			$this->check('getRaw(field.data) uses getRawValue()', $expected, $pages->getRaw($sel, "$rawName.data"));
			$value = $pages->getRaw($sel);
			$this->check('getRaw() all fields uses getRawValue()', $expected, $value[$rawName]);
			$value = $pages->findRaw($sel, "$rawName.*");
			$this->check('findRaw(field.*) uses getRawValue()', $expected, $value[$page->id]['data']);
			$this->check('getRawValue() receives pages_id', array($page->id), array_values(array_unique(FieldtypeWireTestRawValue::$pageIds)));
			$this->check('getRawValue() does not affect unformatted value', 'pid2,pid3', $pages->get($sel)->getUnformatted($rawName));

		} finally {
			$this->cleanup();
		}
	}

	/**
	 * Path and url follow the user language, and paths and urls return all languages
	 *
	 * processwire/processwire-issues#1964
	 *
	 */
	protected function testPaths() {
		$wire = $this->wire();
		$pages = $wire->pages;
		$languages = $wire->languages;
		$prefix = $this->prefix;

		if(!$wire->modules->isInstalled('PagePaths')) {
			$this->li('Skipping path tests because PagePaths module is not installed');
			return;
		}

		$multi = $languages && $wire->modules->isInstalled('LanguageSupportPageNames');
		$user = $wire->user;
		$userLanguage = $languages ? $user->language : null;

		$this->cleanup();

		try {
			$template = $this->createTemplate();
			$page = $pages->add($template, $pages->get(1), "$prefix-paths", array('title' => 'Paths'));
			if($multi) {
				$page->of(false);
				foreach($languages as $language) {
					if($language->isDefault()) continue;
					$page->set("name$language->id", "$prefix-paths-$language->name");
					$page->set("status$language->id", 1);
				}
				$page->save();
			}
			$pages->uncacheAll();
			$page = $pages->get($page->id);
			$sel = "id=$page->id, include=all";

			$this->check('path matches $page->path', $page->path, $pages->getRaw($sel, 'path'));
			$this->check('url matches $page->url', $page->url, $pages->getRaw($sel, 'url'));
			$value = $pages->findRaw(array($page->id), array('path', 'url'));
			$this->check('path for page ID array matches $page->path', $page->path, $value[$page->id]['path']);

			$paths = $pages->getRaw($sel, 'paths');
			$urls = $pages->getRaw($sel, 'urls');

			if(!$multi) {
				$this->check('paths without multi-language page names', array(0 => $page->path), $paths);
				$this->check('urls without multi-language page names', array(0 => $page->url), $urls);
				return;
			}

			$expectPaths = array();
			$expectUrls = array();
			$otherLanguage = null;
			foreach($languages as $language) {
				$expectPaths[$language->id] = $page->localPath($language);
				$expectUrls[$language->id] = $page->localUrl($language);
				if(!$language->isDefault() && !$otherLanguage) $otherLanguage = $language;
			}
			$this->check('paths indexed by language ID', $expectPaths, $paths);
			$this->check('urls indexed by language ID', $expectUrls, $urls);
			if(!$otherLanguage) return;

			$user->language = $otherLanguage;
			$expectPath = $page->localPath($otherLanguage);
			$this->check('path follows user language', $expectPath, $pages->getRaw($sel, 'path'));
			$this->check('url follows user language', $page->localUrl($otherLanguage), $pages->getRaw($sel, 'url'));
			$value = $pages->findRaw(array($page->id), 'path');
			$this->check('path for page ID array follows user language', $expectPath, $value[$page->id]);
			$this->check('paths do not depend on user language', $expectPaths, $pages->getRaw($sel, 'paths'));

		} finally {
			if($userLanguage) $user->language = $userLanguage;
			$this->cleanup();
		}
	}
}

/**
 * Fieldtype for testing Fieldtype::getRawValue(), which stores lists of page IDs like "pid2,pid3"
 *
 */
class FieldtypeWireTestRawValue extends FieldtypeText {

	/**
	 * @var array Page IDs that getRawValue() was called for
	 *
	 */
	public static $pageIds = array();

	public function getRawValue(Field $field, array $row, array $options = array()) {
		if(isset($row['pages_id'])) self::$pageIds[] = (int) $row['pages_id'];
		if(isset($row['data']) && is_string($row['data']) && strpos($row['data'], 'pid') === 0) {
			$row['data'] = array_map('intval', explode(',', str_replace('pid', '', $row['data'])));
		}
		return $row;
	}
}
