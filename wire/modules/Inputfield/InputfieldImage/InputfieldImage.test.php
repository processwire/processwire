<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldImage module.
 *
 */
class WireTest_InputfieldImage extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'input_image';

	/**
	 * @var User|null
	 *
	 */
	protected $previousUser = null;

	public function init() {
		$users = $this->wire()->users;
		$config = $this->wire()->config;
		$this->previousUser = $this->wire()->user;
		$users->setCurrentUser($users->get($config->superUserPageID));
		$this->ensureField();
		$this->resetImages();
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testRenderReadyAndUpload();
		$this->testRenderListItemAndThumb();
		$this->testButtonsActionsAndTooltip();
		$this->testProcessFocusAndActions();
		$this->testFileAddedValidation();
		$this->testConfigInputfields();
	}

	public function finish() {
		$this->resetImages();
		if($this->previousUser && $this->previousUser->id) {
			$this->wire()->users->setCurrentUser($this->previousUser);
		}
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$field = $fields->get($this->fieldName);

		if(!$field) {
			$field = new ImageField();
			$field->name = $this->fieldName;
			$field->type = $modules->get('FieldtypeImage');
			$field->label = 'Test Inputfield Image';
			$field->extensions = 'jpg jpeg png gif';
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

	protected function resetImages() {
		$page = $this->getTestPage();
		$page->of(false);
		$value = $page->get($this->fieldName);
		if($value instanceof Pageimages && $value->count()) {
			$value->deleteAll();
			$page->save($this->fieldName);
		}
	}

	protected function fixtureImage($basename = 'test1.jpg') {
		$file = $this->wire()->config->paths->root . "wire/modules/Fieldtype/FieldtypeImage/tests/images/$basename";
		if(!is_file($file)) $this->fail("Test image not found: $file");
		return $file;
	}

	protected function addFixtureImage($basename = 'test1.jpg') {
		$this->resetImages();
		$page = $this->getTestPage();
		$page->of(false);
		$value = $page->get($this->fieldName);
		$value->add($this->fixtureImage($basename));
		$page->save($this->fieldName);
		$page = $this->wire()->pages->getFresh($page->id);
		$page->of(false);
		$this->page = $page;
		return $page->get($this->fieldName)->first();
	}

	protected function newInputfield() {
		$page = $this->getTestPage();
		$page->of(false);
		$f = $page->getInputfield($this->fieldName);
		if(!$f instanceof InputfieldImage) $this->fail("Unable to create InputfieldImage for $this->fieldName");
		return $f;
	}

	protected function pagefileInputId(Pageimage $pageimage) {
		return $this->fieldName . '_' . $pageimage->hash;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldImage', true, $f instanceof InputfieldImage);
		$this->check('extends InputfieldFile', true, $f instanceof InputfieldFile);
		$this->check('implements item list interface', true, $f instanceof InputfieldItemList);
		$this->check('implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('extensions copied from image field', 'jpg jpeg png gif', $f->extensions);
		$this->check('default grid size', InputfieldImage::defaultGridSize, $f->gridSize);
		$this->check('default grid mode', 'grid', $f->gridMode);
		$this->check('default focus mode', 'on', $f->focusMode);
		$this->check('default client quality', 90, $f->clientQuality);
		$this->check('default item class', 'gridImage ui-widget', $f->itemClass);
	}

	protected function testRenderReadyAndUpload() {
		$f = $this->newInputfield();
		$f->maxWidth = 1200;
		$f->maxHeight = 800;
		$f->maxSize = 1.5;
		$f->clientQuality = 80;
		$f->renderReady();

		$this->check('renderReady adds client resize data attr', '1200;800;1.5;0.8', $f->wrapAttr('data-resize'));

		$html = $f->renderUpload($f->val());
		$this->check('renderUpload returns image upload wrapper', 'InputfieldImageUpload', $html, '*=');
		$this->check('renderUpload appends array brackets to name', 'name="' . $this->fieldName . '[]"', $html, '*=');
		$this->check('renderUpload includes image extension data', 'data-extensions="jpg jpeg png gif"', $html, '*=');
		$this->check('renderUpload includes image max files input', 'InputfieldImageMaxFiles', $html, '*=');
		$this->check('renderUpload includes ajax drop target', 'AjaxUploadDropHere', $html, '*=');

		$f = $this->newInputfield();
		$f->maxFiles = 1;
		$html = $f->renderUpload($f->val());
		$this->check('maxFiles=1 omits multiple attribute', false, strpos($html, 'multiple=') !== false);
	}

	protected function testRenderListItemAndThumb() {
		$image = $this->addFixtureImage();
		$f = $this->newInputfield();
		$id = $this->pagefileInputId($image);
		$list = $f->renderList($f->val());

		$this->check('renderList returns image list', 'InputfieldImageList', $list, '*=');
		$this->check('renderList includes grid size data', "data-gridSize='$f->gridSize'", $list, '*=');
		$this->check('renderList includes edit overlay', 'InputfieldImageEdit', $list, '*=');
		$this->check('renderList includes focus input', "name='focus_$id'", $list, '*=');
		$this->check('renderList includes rename input', "name='rename_$id'", $list, '*=');
		$this->check('renderList includes action select', "name='act_$id'", $list, '*=');

		$thumb = $f->getAdminThumb($image, true);
		$this->check('getAdminThumb returns image markup', '<img ', $thumb['markup'], '*=');
		$this->check('getAdminThumb attr includes original dimensions', $image->width, $thumb['attr']['data-w']);
		$this->check('getAdminThumb attr includes original URL', $image->URL, $thumb['attr']['data-original']);
		$this->check('getAdminThumb attr includes focus string', true, isset($thumb['attr']['data-focus']));
		$this->check('getAdminThumb title includes basename', $image->basename, $thumb['title'], '*=');

		$valueHtml = $f->renderValue();
		$this->check('renderValue includes image markup', '<img ', $valueHtml, '*=');
		$this->check('renderValue omits edit overlay controls', false, strpos($valueHtml, 'gridImage__hover') !== false);
	}

	protected function testButtonsActionsAndTooltip() {
		$image = $this->addFixtureImage();
		$f = $this->newInputfield();
		$id = $this->pagefileInputId($image);

		$buttons = $f->getImageEditButtons($image, $id, 0, 'test-button');
		$this->check('edit buttons include crop', true, isset($buttons['crop']));
		$this->check('edit buttons include focus', true, isset($buttons['focus']));
		$this->check('edit buttons include variations', true, isset($buttons['variations']));
		$this->check('renderButtons includes crop class', 'InputfieldImageButtonCrop', $f->renderButtons($image, $id, 0), '*=');

		$f->useImageEditor = 0;
		$this->check('renderButtons returns empty when editor disabled', '', $f->renderButtons($image, $id, 0));
		$f->useImageEditor = 1;

		$actions = $f->getFileActions($image);
		$this->check('file actions include duplicate', true, isset($actions['dup']));
		$this->check('file actions include hide', true, isset($actions['hide']));
		$this->check('file actions include rotate', true, isset($actions['r90']));
		$this->check('thumbnail actions default empty array', array(), $f->getImageThumbnailActions($image, $id, 0, 'thumb-action'));

		$image->description = 'Has description';
		$image->tags = 'alpha';
		$f->useTags = FieldtypeFile::useTagsNormal;
		$data = $f->buildTooltipData($image);
		$flat = array();
		foreach($data as $row) $flat[] = implode(':', $row);
		$tooltip = implode('|', $flat);
		$this->check('tooltip includes dimensions', $image->width . 'x' . $image->height, $tooltip, '*=');
		$this->check('tooltip includes description indicator', 'Description', $tooltip, '*=');
		$this->check('tooltip includes tags indicator', 'Tags', $tooltip, '*=');
	}

	protected function testProcessFocusAndActions() {
		$image = $this->addFixtureImage();
		$f = $this->newInputfield();
		$id = $this->pagefileInputId($image);
		$data = array(
			"focus_$id" => '25 75 0',
			"sort_$id" => 0,
		);
		$input = new WireInputData($data);

		$this->check('processInputFile detects focus change', true, $f->processInputFile($input, $image, 0));
		$focus = $image->focus();
		$this->check('focus top updated', 25, (int) $focus['top']);
		$this->check('focus left updated', 75, (int) $focus['left']);

		$f = $this->newInputfield();
		$image = $f->val()->first();
		$id = $this->pagefileInputId($image);
		$data = array(
			"act_$id" => 'hide',
			"sort_$id" => 0,
		);
		$input = new WireInputData($data);
		$f->processInput($input);
		$this->check('hide action marks image hidden', true, $image->hidden());

		$actions = $f->getFileActions($image);
		$this->check('hidden image actions include unhide', true, isset($actions['unhide']));
	}

	protected function testFileAddedValidation() {
		$image = $this->addFixtureImage();
		$f = $this->newInputfield();
		$f->minWidth = $image->width + 1;
		$threw = false;

		try {
			$f->fileAdded($image);
		} catch(WireException $e) {
			$threw = true;
			$this->check('minimum dimension exception mentions minimum size', 'minimum size', $e->getMessage(), '*=');
		}

		$this->check('fileAdded refuses image below minimum width', true, $threw);
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$config = $f->getConfigInputfields();

		$this->check('getConfigInputfields returns wrapper', true, $config instanceof InputfieldWrapper);
		$this->check('config contains image features fieldset', true, $config->getChildByName('_image_features') instanceof InputfieldFieldset);
		$this->check('config contains gridMode input', true, $config->getChildByName('gridMode') instanceof InputfieldRadios);
		$this->check('config contains focusMode input', true, $config->getChildByName('focusMode') instanceof InputfieldRadios);
	}
}
