<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldFile module.
 *
 */
class WireTest_InputfieldFile extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'input_file';

	public function init() {
		$this->ensureField();
		$this->resetFiles();
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testFormEnctype();
		$this->testRenderUpload();
		$this->testRenderListAndValue();
		$this->testDescriptionTagsSortAndDelete();
		$this->testMaxFilesize();
		$this->testConfigInputfields();
	}

	public function finish() {
		$this->resetFiles();
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$field = $fields->get($this->fieldName);

		if(!$field) {
			$field = new FileField();
			$field->name = $this->fieldName;
			$field->type = $modules->get('FieldtypeFile');
			$field->label = 'Test Inputfield File';
			$field->extensions = 'pdf txt';
			$field->maxFiles = 0;
			$field->descriptionRows = 1;
			$field->outputFormat = FieldtypeFile::outputFormatArray;
			$field->save();
			$this->li("Created field: $field->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $field->name");
		}
	}

	protected function resetFiles() {
		$page = $this->getTestPage();
		$page->of(false);
		$value = $page->get($this->fieldName);
		if($value instanceof Pagefiles && $value->count()) {
			$value->deleteAll();
			$page->save($this->fieldName);
		}
	}

	protected function fixtureFile() {
		$file = $this->wire()->config->paths->root . 'wire/modules/Fieldtype/FieldtypeFile/tests/files/php-cheat-sheet.pdf';
		if(!is_file($file)) $this->fail("Test file not found: $file");
		return $file;
	}

	protected function newInputfield() {
		$page = $this->getTestPage();
		$page->of(false);
		$f = $page->getInputfield($this->fieldName);
		if(!$f instanceof InputfieldFile) $this->fail("Unable to create InputfieldFile for $this->fieldName");
		return $f;
	}

	protected function addFixtureFile() {
		$this->resetFiles();
		$page = $this->getTestPage();
		$page->of(false);
		$value = $page->get($this->fieldName);
		$value->add($this->fixtureFile());
		$page->save($this->fieldName);
		$page = $this->wire()->pages->getFresh($page->id);
		$page->of(false);
		$this->page = $page;
		return $page->get($this->fieldName)->first();
	}

	protected function pagefileInputId(Pagefile $pagefile) {
		return $this->fieldName . '_' . $pagefile->hash;
	}

	protected function bytesFromIniValue($value) {
		$value = trim((string) $value);
		$unit = strtolower(substr($value, -1));
		$bytes = (int) $value;
		if($unit === 'g') return $bytes * 1024 * 1024 * 1024;
		if($unit === 'm') return $bytes * 1024 * 1024;
		if($unit === 'k') return $bytes * 1024;
		return $bytes;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldFile', true, $f instanceof InputfieldFile);
		$this->check('implements item list interface', true, $f instanceof InputfieldItemList);
		$this->check('implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('field context assigned', $this->fieldName, $f->hasField->name);
		$this->check('page context assigned', $this->getTestPage()->id, $f->hasPage->id);
		$this->check('extensions copied from field', 'pdf txt', $f->extensions);
		$this->check('default noAjax is 0', 0, $f->noAjax);
		$this->check('default descriptionRows is 1', 1, $f->descriptionRows);
		$this->check('getWireUpload returns WireUpload', true, $f->getWireUpload() instanceof WireUpload);
		$this->check('empty value reports empty', true, $f->isEmpty());
	}

	protected function testFormEnctype() {
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->attr('method', 'post');
		$f = $this->newInputfield();
		$form->add($f);
		$this->check('file input sets multipart form enctype', 'multipart/form-data', $form->attr('enctype'));
	}

	protected function testRenderUpload() {
		$f = $this->newInputfield();
		$html = $f->renderUpload($f->val());

		$this->check('renderUpload returns upload wrapper', 'InputfieldFileUpload', $html, '*=');
		$this->check('renderUpload appends array brackets to name', 'name="' . $this->fieldName . '[]"', $html, '*=');
		$this->check('renderUpload includes extension data', 'data-extensions="pdf txt"', $html, '*=');
		$this->check('renderUpload includes max filesize data', 'data-maxfilesize=', $html, '*=');
		$this->check('renderUpload includes ajax drop target by default', 'AjaxUploadDropHere', $html, '*=');

		$f = $this->newInputfield();
		$f->maxFiles = 1;
		$html = $f->renderUpload($f->val());
		$this->check('maxFiles=1 omits multiple attribute', false, strpos($html, 'multiple=') !== false);

		$f = $this->newInputfield();
		$f->noAjax = 1;
		$html = $f->renderUpload($f->val());
		$this->check('noAjax removes drop target', false, strpos($html, 'AjaxUploadDropHere') !== false);

		$f = $this->newInputfield();
		$f->noUpload = 1;
		$this->check('noUpload returns empty upload markup', '', $f->renderUpload($f->val()));
	}

	protected function testRenderListAndValue() {
		$pagefile = $this->addFixtureFile();
		$f = $this->newInputfield();
		$html = $f->renderList($f->val());

		$this->check('renderList returns file list', 'InputfieldFileList', $html, '*=');
		$this->check('renderList includes basename', $pagefile->basename, $html, '*=');
		$this->check('renderList includes delete checkbox', 'delete_' . $this->pagefileInputId($pagefile), $html, '*=');
		$this->check('renderList includes sort input', 'sort_' . $this->pagefileInputId($pagefile), $html, '*=');
		$this->check('non-empty value reports not empty', false, $f->isEmpty());

		$short = $f->getDisplayBasename($pagefile, 10);
		$this->check('getDisplayBasename shortens long basename', '&hellip;pdf', $short, '*=');

		$f->noShortName = 1;
		$this->check('noShortName returns full basename', $pagefile->basename, $f->getDisplayBasename($pagefile, 10));

		$valueHtml = $f->renderValue();
		$this->check('renderValue includes linked filename', $pagefile->basename, $valueHtml, '*=');
		$this->check('renderValue omits delete checkbox', false, strpos($valueHtml, 'InputfieldFileDelete') !== false);
	}

	protected function testDescriptionTagsSortAndDelete() {
		$pagefile = $this->addFixtureFile();
		$f = $this->newInputfield();
		$f->useTags = FieldtypeFile::useTagsNormal;
		$id = $this->pagefileInputId($pagefile);
		$data = array(
			"description_$id" => 'Updated description',
			"tags_$id" => 'alpha beta',
			"sort_$id" => 7,
		);
		$input = new WireInputData($data);

		$this->check('processInputFile detects metadata changes', true, $f->processInputFile($input, $pagefile, 0));
		$this->check('description input updates Pagefile', 'Updated description', $pagefile->description);
		$this->check('tags input updates Pagefile', 'alpha beta', $pagefile->tags);
		$this->check('sort input updates Pagefile sort', 7, $pagefile->sort);

		$f = $this->newInputfield();
		$pagefile = $f->val()->first();
		$id = $this->pagefileInputId($pagefile);
		$data = array(
			"delete_$id" => 1,
			"sort_$id" => 0,
		);
		$input = new WireInputData($data);
		$this->check('delete input detects change', true, $f->processInputFile($input, $pagefile, 0));
		$this->check('delete input removes file from value', 0, $f->val()->count());
	}

	protected function testMaxFilesize() {
		$f = $this->newInputfield();
		$f->setMaxFilesize('1k');
		$this->check('setMaxFilesize accepts kilobytes', 1024, $f->maxFilesize);

		$f->setMaxFilesize('2m');
		$this->check('setMaxFilesize accepts megabytes', 2 * 1024 * 1024, $f->maxFilesize);

		$f->setMaxFilesize('999g');
		$phpMax = $this->bytesFromIniValue(ini_get('upload_max_filesize'));
		$this->check('setMaxFilesize caps to PHP upload_max_filesize', $phpMax, $f->maxFilesize);
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$config = $f->getConfigInputfields();

		$this->check('getConfigInputfields returns wrapper', true, $config instanceof InputfieldWrapper);
		$this->check('config contains uploads fieldset', true, $config->getChildByName('_file_uploads') instanceof InputfieldFieldset);
	}
}
