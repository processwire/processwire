<?php namespace ProcessWire;

/**
 * Tests for FieldtypeImage
 *
 */
class WireTest_FieldtypeImage extends WireTest {

	protected $fieldName = 'test_image';
	protected $fieldNameSingle = 'test_image_single';

	public function init() {
		$this->ensureFields();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$nameSingle = $this->fieldNameSingle;
		$dir = __DIR__ . '/tests/images/';
		$imgJpg = $dir . 'test1.jpg';
		$imgPng = $dir . 'test2.png';
		$imgGif = $dir . 'GIF-google.gif';
		$imgInvalid = $dir . 'invalid-image.jpg';
		$imgJpgName = basename($imgJpg);

		foreach(array($imgJpg, $imgPng, $imgGif, $imgInvalid) as $file) {
			if(!is_file($file)) $this->fail("Test image not found: $file");
		}

		$page->of(false);
		$page->get($name)->deleteAll();
		$page->save($name);

		$page->get($name)->add($imgJpg);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$images = $page->get($name);
		if(!($images instanceof Pageimages)) $this->fail('Expected Pageimages, got: ' . get_class($images));
		if($images->count() !== 1) $this->fail('Expected 1 image, got: ' . $images->count());
		$this->li('Add JPG verified, count=1');

		$img = $images->first();
		if($img->ext !== 'jpg') $this->fail("Expected ext 'jpg', got: " . var_export($img->ext, true));
		if($img->width < 1 || $img->height < 1) {
			$this->fail("Expected width/height > 0, got: $img->width" . 'x' . $img->height);
		}
		$expectedRatio = round($img->width / $img->height, 2);
		if(round($img->ratio, 2) !== $expectedRatio) {
			$this->fail("Expected ratio ~$expectedRatio, got: $img->ratio");
		}
		$this->li("Pageimage properties verified: $img->width" . 'x' . "$img->height, ratio=$img->ratio, ext=$img->ext");

		$page->of(false);
		$page->get($name)->first()->description = 'foo bar baz';
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->first()->description !== 'foo bar baz') {
			$this->fail('Description mismatch: ' . var_export($page->get($name)->first()->description, true));
		}
		$this->li('Image description verified');

		$page->of(false);
		$page->get($name)->add($imgPng);
		$page->get($name)->add($imgGif);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 3) {
			$this->fail('Expected 3 images (jpg+png+gif), got: ' . $page->get($name)->count());
		}
		$this->li('JPG + PNG + GIF added, count=3');

		$page->of(false);
		$img1 = $page->get($name)->get(basename($imgJpg));
		if(!$img1) $this->fail("Expected to find image by name '" . basename($imgJpg) . "'");
		$this->li("Get image by name verified: $img1->name");

		$variation = $img1->size(100, 100);
		if(!$variation || !is_file($variation->filename)) $this->fail('Expected size() to create a variation file');
		if($variation->width !== 100 || $variation->height !== 100) {
			$this->fail("Expected 100x100 variation, got: $variation->width" . 'x' . $variation->height);
		}
		$this->li("size(100,100) variation created: $variation->name ($variation->width" . 'x' . "$variation->height)");

		$wide = $img1->width(50);
		if(!$wide || $wide->width !== 50) {
			$this->fail('Expected width(50) variation, got: ' . ($wide ? $wide->width : 'null'));
		}
		$this->li("width(50) proportional resize verified: $wide->width" . 'x' . $wide->height);

		$page->of(false);
		$countBefore = $page->get($name)->count();
		try {
			$page->get($name)->add($imgInvalid);
			$page->save($name);
			$page = $pages->getFresh($page->id);
			$page->of(false);
			$countAfter = $page->get($name)->count();
			if($countAfter > $countBefore) {
				$this->fail("Expected invalid image to be rejected, but count went from $countBefore to $countAfter");
			}
			$this->li("Invalid image rejected (count unchanged at $countAfter)");
		} catch(WireException $e) {
			$this->li('Invalid image threw WireException (also acceptable): ' . $e->getMessage());
		}

		$page->of(false);
		$page->get($name)->deleteAll();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 0) {
			$this->fail('Expected 0 images after deleteAll, got: ' . $page->get($name)->count());
		}
		$this->li('deleteAll() verified, count=0');

		$page->of(false);
		$page->get($nameSingle)->deleteAll();
		$page->save($nameSingle);

		$page->of(true);
		$val = $page->get($nameSingle);
		if($val !== null) {
			$this->fail('Expected null for empty single-image field (OF on), got: ' . var_export($val, true));
		}
		$this->li('Empty single-image field returns null (OF on) verified');

		$page->of(false);
		$page->get($nameSingle)->add($imgJpg);
		$page->save($nameSingle);
		$page = $pages->getFresh($page->id);
		$page->of(true);
		$val = $page->get($nameSingle);
		if(!($val instanceof Pageimage)) {
			$this->fail('Expected Pageimage for single-image field (OF on), got: ' . gettype($val));
		}
		$this->li("Single-image field returns Pageimage (OF on) verified: $val->name");

		$page->of(false);
		$page->get($nameSingle)->deleteAll();
		$page->save($nameSingle);

		$page->of(false);
		$page->get($name)->add($imgJpg);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$page->get($name)->first()->description = 'foo bar baz';
		$page->save($name);
		$img = $page->get($name)->first();
		$wLess = $img->width - 1;
		$hLess = $img->height - 1;
		$selectors = array(
			"template=test, $name=$imgJpgName",
			"template=test, $name%=" . basename($imgJpgName, '.jpg'),
			"template=test, $name.description%=bar",
			"template=test, $name.width>$wLess",
			"template=test, $name.height>$hLess",
			"template=test, $name.ratio<1",
			"template=test, $name.filesize>0",
			"template=test, $name.count>0",
			"template=test, $name!=\"\"",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->get($name)->deleteAll();
		$page->save($name);
		$p = $pages->findOne("template=test, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");
	}

	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypeImage');
		$field = $fields->get($this->fieldName);
		$fieldSingle = $fields->get($this->fieldNameSingle);

		if(!$field) {
			$field = new ImageField();
			$field->name = $this->fieldName;
			$field->type = $fieldtype;
			$field->label = 'Test Image';
			$field->extensions = 'jpg jpeg png gif';
			$field->maxFiles = 0;
			$field->outputFormat = FieldtypeFile::outputFormatArray;
			$field->save();
			$this->li("Created field: $field->name");
		}

		if(!$fieldSingle) {
			$fieldSingle = new ImageField();
			$fieldSingle->name = $this->fieldNameSingle;
			$fieldSingle->type = $fieldtype;
			$fieldSingle->label = 'Test Image Single';
			$fieldSingle->extensions = 'jpg jpeg png gif';
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
