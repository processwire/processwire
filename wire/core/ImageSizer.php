<?php namespace ProcessWire;

/**
 * ProcessWire ImageSizer with Engines for ProcessWire 3.x
 *
 * ImageSizer handles resizing of a single JPG, GIF, or PNG image using GD2
 * or another supported and configured engine. (Imagick, ImageMagick, Netpbm)
 *
 * Code for IPTC, auto rotation and sharpening by Horst Nogajski.
 * http://nogajski.de/
 *
 * Other user contributions as noted.
 *
 * Copyright (C) 2016 by Horst Nogajski and Ryan Cramer
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 * 
 * @method bool resize($targetWidth, $targetHeight = 0)
 *
 */
class ImageSizer extends Wire {

	/**
	 * @var ImageSizerEngine|null
	 *
	 */
	protected $engine = null;

	/**
	 * Default/fallback image sizer engine name
	 *
	 * @var string
	 *
	 */
	protected $defaultEngineName = 'ImageSizerEngineGD';

	/**
	 * @var string|null
	 *
	 */
	protected $filename = null;

	/**
	 * @var array
	 *
	 */
	protected $initialOptions = array();

	/**
	 * Known ImageSizer engine modules (class names) excluding ImageSizerEngineGD (the default)
	 * 
	 * Cached result of getEngines() call
	 * 
	 * @var null|array
	 * 
	 */
	static protected $knownEngines = null;

	/**
	 * Names of engines that failed the supported() checks
	 * 
	 * @var array
	 * 
	 */
	protected $failedEngineNames = array();

	/**
	 * Module/class name of engine that must only be used (for cases where you want to force a specific engine)
	 * 
	 * If the $options array contained a 'forceEngine' property, this contains the value.
	 * 
	 * @var string
	 * 
	 */
	protected $forceEngineName = '';

	/**
	 * Whether or not ImageSizer has an engine ready to use
	 * 
	 * @var bool
	 * 
	 */
	protected $initialized = false;

	/**
	 * @var null|false|array
	 *
	 */
	protected $inspectionResult = null;

	/**
	 * Construct the ImageSizer for a single image
	 * 
	 * @param string $filename Filename to resize. Omit only if instantiating class for a getEngines() call.
	 * @param array $options Initial options to the engine.
	 *
	 */
	public function __construct($filename = '', $options = array()) {
		if(!empty($options)) $this->setOptions($options); 
		if(!empty($filename)) $this->setFilename($filename);
	}
	
	/**
	 * Get array of all available ImageSizer engine names in order of priority
	 * 
	 * Note that the returned value excludes the default engine (ImageSizerEngineGD).
	 * 
	 * @param bool $forceReload Specify true only if you want to prevent it from using cached result from previous call.
	 * @return array of module names 
	 * 
	 */
	public function getEngines($forceReload = false) {
		
		if(!$forceReload && is_array(self::$knownEngines)) return self::$knownEngines;
		
		self::$knownEngines = array();
		
		$modules = $this->wire('modules');
		$engines = $modules->find("className^=ImageSizerEngine");
		
		foreach($engines as $module) {
			$moduleName = $module->className();
			if(!$modules->isInstalled($moduleName)) continue;
			if(count($engines) > 1) {
				$configData = $modules->getModuleConfigData($moduleName);
				$priority = isset($configData['enginePriority']) ? (int) $configData['enginePriority'] : 0;
				while(isset(self::$knownEngines[$priority])) $priority++;
			} else {
				$priority = 0;
			}
			self::$knownEngines[$priority] = $moduleName;
		}
		
		if(count(self::$knownEngines) > 1) ksort(self::$knownEngines);
		
		return self::$knownEngines;
	}
	
	/**
	 * Instantiate an ImageSizerEngine
	 *
	 * @param string $filename
	 * @param array $options
	 * @param null|array $inspectionResult
	 * @return ImageSizerEngine|null
	 * @throws WireException
	 *
	 */
	protected function newImageSizerEngine($filename = '', array $options = array(), $inspectionResult = null) {	
		
		if(empty($filename)) $filename = $this->filename;
		if(empty($options)) $options = $this->initialOptions;
		if(empty($inspectionResult)) $inspectionResult = $this->inspectionResult;
		
		if(empty($inspectionResult) && $filename && is_readable($filename)) {
			$imageInspector = new ImageInspector($filename);
			$this->wire($imageInspector); 
			$inspectionResult = $imageInspector->inspect($filename, true);
			$this->inspectionResult = $inspectionResult;
		}
		
		$engine = null;
		$bestFallbackEngine = null; // first engine that was supported but failed webp check
		$engineNames = $this->getEngines();
		$engineNames[] = $this->defaultEngineName;
	
		// find first supported engine, according to knownEngines priority
		foreach($engineNames as $engineName) {
			
			if($this->forceEngineName && $engineName != $this->forceEngineName) continue;
			
			if($engineName === $this->defaultEngineName) {
				$engineClass = __NAMESPACE__ . "\\$engineName";
				$e = $this->wire(new $engineClass());
			} else {
				$e = $this->wire('modules')->get($engineName);
			}
			
			if(!$e) continue;
			
			/** @var ImageSizerEngine $e */
			$e->prepare($filename, $options, $inspectionResult);	
			$supported = $e->supported();
			
			if($supported && !empty($options['webpAdd']) && !$e->supported('webp')) {
				// engine does not support requested webp extra image
				if(!$bestFallbackEngine) $bestFallbackEngine = $e;
				
			} else if($supported) {
				// found supported engine
				$engine = $e;
				break;
			} 
			
			$this->failedEngineNames[$engineName] = $engineName;
		}
		
		if(!$engine) { 
			// no engine found
			if($bestFallbackEngine) {
				// if there is a next best fallback, use it
				$engine = $bestFallbackEngine;
			} else {
				// otherwise fallback to default
				$engine = $this->newDefaultImageSizerEngine($filename, $options, $inspectionResult);
			}
		}
		
		return $engine;
	}

	/**
	 * Get the default/fallback ImageSizer engine
	 * 
	 * @param string $filename
	 * @param array $options
	 * @param null|array $inspectionResult
	 * @return ImageSizerEngine|null
	 * @throws WireException
	 * 
	 */
	protected function newDefaultImageSizerEngine($filename = '', $options = array(), $inspectionResult = null) {
		
		if(empty($filename)) $filename = $this->filename;
		if(empty($options)) $options = $this->initialOptions;
		if(empty($inspectionResult)) $inspectionResult = $this->inspectionResult;
		
		if($this->forceEngineName && $this->forceEngineName != $this->defaultEngineName) {
			throw new WireException("Forced engine $this->forceEngineName is not supported for this operation");
		}
		
		$engineClass = __NAMESPACE__ . "\\$this->defaultEngineName";
		/** @var ImageSizerEngine $engine */
		$engine = new $engineClass();
		$this->wire($engine);
		$engine->prepare($filename, $options, $inspectionResult);
		if(!$engine->supported()) $engine = null;
		
		return $engine;
	}

	/**
	 * Resize the image proportionally to the given width/height
	 *
	 * @param int $targetWidth Target width in pixels, or 0 for proportional to height
	 * @param int $targetHeight Target height in pixels, or 0 for proportional to width. Optional-if not specified, 0
	 *     is assumed.
	 *
	 * @return bool True if the resize was successful, false if not
	 * @throws WireException when not enough memory to load image or missing required data
	 *
	 */
	public function ___resize($targetWidth, $targetHeight = 0) {
		
		$engine = $this->getEngine();
		$success = $engine->resize($targetWidth, $targetHeight);
		
		if(!$success) {
			// fallback to GD
			$success = $this->resizeFallback($targetWidth, $targetHeight);
		}
		
		return $success;
	}

	/**
	 * GD is the fallback ImageEngine, it gets invoked if there is no other Engine defined,
	 *
	 * or if a defined Engine is not available,
	 * or if an invoked Engine failes with the image manipulation.
	 *
	 * @param mixed $targetWidth
	 * @param mixed $targetHeight
	 *
	 * @return bool
	 *
	 */
	protected function resizeFallback($targetWidth, $targetHeight = 0) {
		$engine = $this->newDefaultImageSizerEngine();
		$success = false;
		if($engine->supported()) $success = $engine->resize($targetWidth, $targetHeight);
		return $success;
	}

	/**
	 * Set the filename 
	 * 
	 * @param $filename
	 * @return $this
	 * 
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	/**
	 * Force the use of a specific engine
	 * 
	 * @param $engineName Module name of engine you want to force
	 * @return $this
	 * 
	 */
	public function setForceEngine($engineName) {
		$this->forceEngineName = $engineName;
		return $this;
	}

	/**
	 * Set multiple resize options
	 * 
	 * @param array $options
	 * @return $this
	 * 
	 */
	public function setOptions(array $options) {
		if(isset($options['forceEngine'])) {
			$this->setForceEngine($options['forceEngine']);
			unset($options['forceEngine']);
		}
		$this->initialOptions = array_merge($this->initialOptions, $options);
		if($this->engine) $this->engine->setOptions($this->initialOptions);
		return $this;
	}

	/**
	 * Set whether a modification was made
	 * 
	 * @param $modified
	 * @return $this
	 * 
	 */
	public function setModified($modified) {
		if($this->engine) $this->engine->setModified($modified ? true : false);
		return $this;
	}

	public function setAutoRotation($value = true) { return $this->setOptions(array('autoRotation', $value)); }
	public function setCropExtra($value) { return $this->setOptions(array('cropExtra', $value)); }
	public function setCropping($cropping = true) { return $this->setOptions(array('cropping', $cropping)); }
	public function setDefaultGamma($value = 2.2) { return $this->setOptions(array('defaultGamma', $value)); }
	public function setFlip($flip) { return $this->setOptions(array('flip', $flip)); }
	public function setHidpi($hidpi = true) { return $this->setOptions(array('hidpi', $hidpi)); }
	public function setQuality($n) { return $this->setOptions(array('quality', $n)); }
	public function setRotate($degrees) { return $this->setOptions(array('rotate', $degrees)); }
	public function setScale($scale) { return $this->setOptions(array('scale', $scale)); }
	public function setSharpening($value) { return $this->setOptions(array('sharpening', $value)); }
	public function setTimeLimit($value = 30) { return $this->setOptions(array('timeLimit', $value)); }
	public function setUpscaling($value = true) { return $this->setOptions(array('upscaling', $value)); }
	public function setUseUSM($value = true) { return $this->setOptions(array('useUSM', $value)); }
	public function getWidth() { 
		$image = $this->getEngine()->get('image');
		return $image['width']; 
	}
	public function getHeight() { 
		$image = $this->getEngine()->get('image');
		return $image['height']; 
	}
	public function getFilename() { return $this->getEngine()->getFilename(); }
	public function getExtension() { return $this->getEngine()->getExtension(); }
	public function getImageType() { return $this->getEngine()->getImageType(); }
	public function isModified() { return $this->getEngine()->getModified(); }
	public function getOptions() { return $this->getEngine()->getOptions(); }
	public function getFailedEngineNames() { return $this->failedEngineNames; }

	/**
	 * Get the current ImageSizerEngine
	 * 
	 * @return ImageSizerEngine
	 * @throws WireException
	 * 
	 */
	public function getEngine() { 
		
		if($this->engine) return $this->engine;
		
		if(empty($this->filename)) {
			throw new WireException('No file to process: please call setFilename($file) before calling other methods');
		}

		$imageInspector = new ImageInspector($this->filename);
		$this->inspectionResult = $imageInspector->inspect($this->filename, true);
		$this->engine = $this->newImageSizerEngine($this->filename, $this->initialOptions, $this->inspectionResult);
		// set the engine, and check if the engine is ready to use
		if(!$this->engine) {
			throw new WireException('There seems to be no support for the GD image library on your host?');
		}

		return $this->engine;
	}
	
	public function __get($key) { return $this->getEngine()->__get($key); }

	/**
	 * ImageInformation from Image Inspector in short form or full RawInfoData
	 *
	 * @param bool $rawData
	 * @return string|array
	 *
	 */
	public function getImageInfo($rawData = false) {
	
		$this->getEngine();
		if($rawData) return $this->inspectionResult;
		$imageType = $this->inspectionResult['info']['imageType'];
		$type = '';
		$indexed = '';
		$trans = '';
		$animated = '';
		
		switch($imageType) {
			case \IMAGETYPE_JPEG:
				$type = 'jpg';
				$indexed = '';
				$trans = '';
				$animated = '';
				break;
			case \IMAGETYPE_GIF:
				$type = 'gif';
				$indexed = '';
				$trans = $this->inspectionResult['info']['trans'] ? '-trans' : '';
				$animated = $this->inspectionResult['info']['animated'] ? '-anim' : '';
				break;
			case \IMAGETYPE_PNG:
				$type = 'png';
				$indexed = 'Indexed' == $this->inspectionResult['info']['colspace'] ? '8' : '24';
				$trans = $this->inspectionResult['info']['alpha'] ? '-alpha' : '';
				$trans = is_array($this->inspectionResult['info']['trans']) ? '-trans' : $trans;
				$animated = '';
				break;
		}
		
		return $type . $indexed . $trans . $animated;
	}

	/**
	 * Given an unknown cropping value, return the validated internal representation of it
	 *
	 * Needed for backwards compatibility.
	 *
	 * @param string|bool|array $cropping
	 *
	 * @return string|bool
	 *
	 */
	static public function croppingValue($cropping) {
		return ImageSizerEngine::croppingValue($cropping);
	}

	/**
	 * Given an unknown cropping value, return the string representation of it
	 *
	 * Okay for use in filenames.
	 * Needed for backwards compatibility.
	 *
	 * @param string|bool|array $cropping
	 *
	 * @return string
	 *
	 */
	static public function croppingValueStr($cropping) {
		return ImageSizerEngine::croppingValueStr($cropping);
	}

	/**
	 * Given an unknown sharpening value, return the string representation of it
	 *
	 * Okay for use in filenames. Method added by @horst
	 * Needed for backwards compatibility.
	 *
	 * @param string|bool $value
	 * @param bool $short
	 *
	 * @return string
	 *
	 */
	static public function sharpeningValueStr($value, $short = false) {
		return ImageSizerEngine::sharpeningValueStr($value, $short);
	}

	/**
	 * Check orientation (@horst)
	 *
	 * @param mixed $image Pageimage or filename
	 * @param mixed $correctionArray Null or array by reference
	 * @return bool|null
	 *
	 */
	static public function imageIsRotated($image, &$correctionArray = null) {
		if($image instanceof Pageimage) {
			$filename = $image->filename;
		} else if(is_readable($image)) {
			$filename = $image;
		} else {
			return null;
		}
		// first value is rotation-degree and second value is flip-mode: 0=NONE | 1=HORIZONTAL | 2=VERTICAL
		$corrections = array(
			'1' => array(0, 0),
			'2' => array(0, 1),
			'3' => array(180, 0),
			'4' => array(0, 2),
			'5' => array(270, 1),
			'6' => array(270, 0),
			'7' => array(90, 1),
			'8' => array(90, 0)
		);
		if(!function_exists('exif_read_data')) return null;
		$exif = @exif_read_data($filename, 'IFD0');
		if(!is_array($exif)
			|| !isset($exif['Orientation'])
			|| !in_array(strval($exif['Orientation']), array_keys($corrections))
		) {
			return null;
		}
		$correctionArray = $corrections[strval($exif['Orientation'])];
		return $correctionArray[0] > 0;
	}

	/**
	 * Check if GIF-image is animated or not (@horst)
	 *
	 * @param mixed $image Pageimage or filename
	 * @return bool|null
	 *
	 */
	static public function imageIsAnimatedGif($image) {
		if($image instanceof Pageimage) {
			$filename = $image->filename;
		} elseif(is_readable($image)) {
			$filename = $image;
		} else {
			return null;
		}
		$info = getimagesize($filename);
		if(\IMAGETYPE_GIF != $info[2]) return false;
		if(ImageSizerEngineGD::checkMemoryForImage(array($info[0], $info[1]))) {
			return (bool) preg_match('/\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', file_get_contents($filename));
		}
		// we have not enough free memory to load the complete image at once, so we do it in chunks
		if(!($fh = @fopen($filename, 'rb'))) {
			return null;
		}
		$count = 0;
		while(!feof($fh) && $count < 2) {
			$chunk = fread($fh, 1024 * 100); //read 100kb at a time
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk, $matches);
		}
		fclose($fh);
		return $count > 1;
	}

	/**
	 * Possibility to clean IPTC data, also for original images (@horst)
	 *
	 * @param mixed $image Pageimage or filename
	 *
	 * @return mixed|null|bool
	 *
	 */
	static public function imageResetIPTC($image) {
		$wire = null;
		if($image instanceof Pageimage) {
			$wire = $image;
			$filename = $image->filename;
		} else if(is_readable($image)) {
			$filename = $image;
		} else {
			return null;
		}
		$sizer = new ImageSizerEngineGD();
		if($wire) $wire->wire($sizer);
		$result = false !== $sizer->writeBackIPTC($filename) ? true : false;
		return $result;
	}
	
	/**
	 * Rotate image by given degrees
	 *
	 * @param int $degrees
	 * @return bool
	 *
	 */
	public function rotate($degrees) {
		return $this->getEngine()->rotate($degrees);
	}

	/**
	 * Flip image vertically
	 *
	 * @return bool
	 *
	 */
	public function flipVertical() {
		return $this->getEngine()->flipVertical();
	}

	/**
	 * Flip image horizontally
	 *
	 * @return bool
	 *
	 */
	public function flipHorizontal() {
		return $this->getEngine()->flipHorizontal();
	}

	/**
	 * Flip both vertically and horizontally
	 *
	 * @return bool
	 *
	 */
	public function flipBoth() {
		return $this->getEngine()->flipBoth();
	}

	/**
	 * Convert image to greyscale (black and white)
	 *
	 * @return bool
	 *
	 */
	public function convertToGreyscale() {
		return $this->getEngine()->convertToGreyscale();
	}

	/**
	 * Convert image to sepia tone
	 *
	 * @param int $sepia Sepia amount
	 * @return bool
	 *
	 */
	public function convertToSepia($sepia = 55) {
		return $this->getEngine()->convertToSepia('', $sepia);
	}


}
