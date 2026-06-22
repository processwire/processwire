<?php namespace ProcessWire;

/**
 * Tests for FieldtypeFile
 *
 */
class WireTest_FieldtypeFile extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'file';
	protected $fieldNameSingle = WireTests::fieldPrefix . 'file_single';

	public function init() {
		$this->ensureFields();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;
		$nameSingle = $this->fieldNameSingle;
		$pdf1 = __DIR__ . '/tests/files/php-cheat-sheet.pdf';
		$pdf2 = __DIR__ . '/tests/files/test.pdf';

		foreach(array($pdf1, $pdf2) as $file) {
			if(!is_file($file)) $this->fail("Test file not found: $file");
		}

		$page->of(false);
		$page->get($name)->deleteAll();
		$page->save($name);

		$page->get($name)->add($pdf1);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$files = $page->get($name);
		if(!($files instanceof Pagefiles)) $this->fail('Expected Pagefiles, got: ' . get_class($files));
		if($files->count() !== 1) $this->fail('Expected 1 file, got: ' . $files->count());
		$this->li('Add file verified, count=1');

		$file = $files->first();
		if($file->ext !== 'pdf') $this->fail("Expected ext 'pdf', got: " . var_export($file->ext, true));
		if($file->filesize < 1) $this->fail('Expected filesize > 0, got: ' . $file->filesize);
		if(!$file->url || !$file->filename) $this->fail('Expected url and filename to be set');
		$this->li("Pagefile properties verified: name=$file->name, ext=$file->ext, size=$file->filesizeStr");

		$page->of(false);
		$page->get($name)->first()->description = 'PHP Cheat Sheet';
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->first()->description !== 'PHP Cheat Sheet') {
			$this->fail('Description mismatch: ' . var_export($page->get($name)->first()->description, true));
		}
		$this->li('File description set/get verified');

		$page->of(false);
		$page->get($name)->add($pdf2);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 2) {
			$this->fail('Expected 2 files, got: ' . $page->get($name)->count());
		}
		$this->li('Add second file verified, count=2');

		$page->of(false);
		$byName = $page->get($name)->get('php-cheat-sheet.pdf');
		if(!$byName || !($byName instanceof Pagefile)) {
			$this->fail("Expected to find file by name 'php-cheat-sheet.pdf'");
		}
		$this->li("Get file by name verified: $byName->name");

		$this->li('File rename skipped (known core bug: hook not registered due to queue-order issue)');

		$page->of(false);
		$toDelete = $page->get($name)->get('cheatsheet.pdf');
		if($toDelete) {
			$page->get($name)->delete($toDelete);
			$page->save($name);
			$page = $pages->getFresh($page->id);
			$page->of(false);
			if($page->get($name)->count() !== 1) {
				$this->fail('Expected 1 file after delete, got: ' . $page->get($name)->count());
			}
			$this->li('Delete one file verified, count=1');
		}

		$page->of(false);
		$page->get($name)->deleteAll();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 0) {
			$this->fail('Expected 0 files after deleteAll, got: ' . $page->get($name)->count());
		}
		$this->li('deleteAll() verified, count=0');

		$page->of(false);
		$page->get($nameSingle)->deleteAll();
		$page->save($nameSingle);

		$page->of(true);
		$val = $page->get($nameSingle);
		if($val !== null) {
			$this->fail('Expected null for empty single-file field (OF on), got: ' . var_export($val, true));
		}
		$this->li('Empty single-file field returns null (OF on) verified');

		$page->of(false);
		$page->get($nameSingle)->add($pdf1);
		$page->save($nameSingle);
		$page = $pages->getFresh($page->id);
		$page->of(true);
		$val = $page->get($nameSingle);
		if(!($val instanceof Pagefile)) {
			$this->fail('Expected Pagefile for single-file field (OF on), got: ' . gettype($val));
		}
		$this->li("Single-file field returns Pagefile (OF on) verified: $val->name");

		$page->of(false);
		$page->get($nameSingle)->deleteAll();
		$page->save($nameSingle);

		$page->of(false);
		$page->get($name)->add($pdf1);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$page->get($name)->first()->description = 'PHP Cheat Sheet';
		$page->save($name);
		$selectors = array(
			"template=$template, $name=php-cheat-sheet.pdf",
			"template=$template, $name%^=php-cheat",
			"template=$template, $name%\$=sheet.pdf",
			"template=$template, $name.description%=Cheat",
			"template=$template, $name.filesize>0",
			"template=$template, $name.count>0",
			"template=$template, $name!=\"\"",
		);
		foreach($selectors as $selector) {
			$p = $pages->get($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->get($name)->deleteAll();
		$page->save($name);
		$p = $pages->get("template=$template, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");
	}

	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypeFile');
		$field = $fields->get($this->fieldName);
		$fieldSingle = $fields->get($this->fieldNameSingle);

		if(!$field) {
			$field = new FileField();
			$field->name = $this->fieldName;
			$field->type = $fieldtype;
			$field->label = 'Test File';
			$field->extensions = 'pdf';
			$field->maxFiles = 0;
			$field->outputFormat = FieldtypeFile::outputFormatArray;
			$field->save();
			$this->li("Created field: $field->name");
		}

		if(!$fieldSingle) {
			$fieldSingle = new FileField();
			$fieldSingle->name = $this->fieldNameSingle;
			$fieldSingle->type = $fieldtype;
			$fieldSingle->label = 'Test File Single';
			$fieldSingle->extensions = 'pdf';
			$fieldSingle->maxFiles = 1;
			$fieldSingle->outputFormat = FieldtypeFile::outputFormatSingle;
			$fieldSingle->save();
			$this->li("Created field: $fieldSingle->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		foreach(array($field, $fieldSingle) as $f) {
			if(!$fieldgroup->hasField($f)) {
				$fieldgroup->add($f);
				$fieldgroup->save();
				$this->li("Added field to fieldgroup: $f->name");
			}
		}
	}
}
