<?php namespace ProcessWire;

/**
 * ImageSizer Engine Module (Abstract)
 * 
 * Copyright (C) 2016-2019 by Horst Nogajski and Ryan Cramer
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 * @property bool $autoRotation
 * @property bool $upscaling
 * @property bool $interlace
 * @property array|string|bool $cropping
 * @property int $quality
 * @property string $sharpening
 * @property float $defaultGamma
 * @property float $scale
 * @property int $rotate
 * @property string $flip
 * @property bool $useUSM
 * @property int $enginePriority Priority for use among other ImageSizerEngine modules (0=disabled, 1=first, 2=second, 3=and so on)
 * @property bool $webpAdd
 * @property int $webpQuality
 * @property bool|null $webpResult
 * @property bool|null $webpOnly
 * 
 */
abstract class ImageSizerEngine extends WireData implements Module, ConfigurableModule {

	/**
	 * Filename to be resized
	 *
	 * @var string
	 *
	 */
	protected $filename;

	/**
	 * Temporary filename as used by the resize() method
	 * 
	 * @var string
	 * 
	 */
	protected $tmpFile;

	/**
	 * Extension of filename
	 *
	 * @var string
	 *
	 */
	protected $extension;

	/**
	 * Type of image
	 *
	 */
	protected $imageType = null;

	/**
	 * Image quality setting, 1..100
	 *
	 * @var int
	 *
	 */
	protected $quality = 90;

	/**
	 * WebP Image quality setting, 1..100
	 *
	 * @var int
	 *
	 */
	protected $webpQuality = 90;

	/**
	 * Also create a WebP Image with this variation?
	 *
	 * @var bool
	 *
	 */
	protected $webpAdd = false;

	/**
	 * Only create the webp file?
	 * 
	 * @var bool
	 * 
	 */
	protected $webpOnly = false;

	/**
	 * webp result (null=not known or not applicable)
	 * 
	 * @var bool|null
	 * 
	 */
	protected $webpResult = null;

	/**
	 * Image interlace setting, false or true
	 *
	 * @var bool
	 *
	 */
	protected $interlace = false;

	/**
	 * Information about the image (width/height)
	 *
	 * @var array
	 *
	 */
	protected $image = array(
		'width' => 0,
		'height' => 0
	);

	/**
	 * Allow images to be upscaled / enlarged?
	 *
	 * @var bool
	 *
	 */
	protected $upscaling = true;

	/**
	 * Directions that cropping may gravitate towards
	 *
	 * Beyond those included below, TRUE represents center and FALSE represents no cropping.
	 *
	 */
	static protected $croppingValues = array(
		'nw' => 'northwest',
		'n' => 'north',
		'ne' => 'northeast',
		'w' => 'west',
		'e' => 'east',
		'sw' => 'southwest',
		's' => 'south',
		'se' => 'southeast',
	);

	/**
	 * Allow images to be cropped to achieve necessary dimension? If so, what direction?
	 *
	 * Possible values: northwest, north, northeast, west, center, east, southwest, south, southeast
	 *    or TRUE to crop to center, or FALSE to disable cropping.
	 * Or array where index 0 is % or px from left, and index 1 is % or px from top. Percent is assumed if
	 *    values are number strings that end with %. Pixels are assumed of values are just integers. 
	 * Default is: TRUE
	 *
	 * @var bool|array
	 *
	 */
	protected $cropping = true;

	/**
	 * This can be populated on a per image basis. It provides cropping first and then resizing, the opposite of the
	 * default behavior
	 *
	 * It needs an array with 4 params: x y w h for the cropping rectangle
	 *
	 * Default is: null
	 *
	 * @var null|array
	 *
	 */
	protected $cropExtra = null;

	/**
	 * Was the given image modified?
	 *
	 * @var bool
	 *
	 */
	protected $modified = false;

	/**
	 * enable auto_rotation according to EXIF-Orientation-Flag
	 *
	 * @var bool
	 *
	 */
	protected $autoRotation = true;

	/**
	 * default sharpening mode: [ none | soft | medium | strong ]
	 *
	 * @var string
	 *
	 */
	protected $sharpening = 'soft';

	/**
	 * Degrees to rotate: -270, -180, -90, 90, 180, 270
	 *
	 * @var int
	 *
	 */
	protected $rotate = 0;

	/**
	 * Flip image: Specify 'v' for vertical or 'h' for horizontal
	 *
	 * @var string
	 *
	 */
	protected $flip = '';

	/**
	 * default gamma correction: 0.5 - 4.0 | -1 to disable gammacorrection, default = 2.0
	 *
	 * can be overridden by setting it to $config->imageSizerOptions['defaultGamma']
	 * or passing it along with image options array
	 *
	 */
	protected $defaultGamma = 2.2;

	/**
	 * @var bool
	 * 
	 */
	protected $useUSM = false;

	/**
	 * Other options for 3rd party use
	 *
	 * @var array
	 *
	 */
	protected $options = array();

	/**
	 * Options allowed for sharpening
	 *
	 * @var array
	 *
	 */
	static protected $sharpeningValues = array(
		0 => 'none', // none
		1 => 'soft',
		2 => 'medium',
		3 => 'strong'
	);

	/**
	 * List of valid option Names from config.php (@horst)
	 *
	 * @var array
	 *
	 */
	protected $optionNames = array(
		'autoRotation',
		'upscaling',
		'cropping',
		'interlace', 
		'quality',
		'webpQuality',
		'webpAdd',
		'sharpening',
		'defaultGamma',
		'scale',
		'rotate',
		'flip',
		'useUSM',
	);

	/**
	 * Supported image types (@teppo)
	 *
	 * @var array
	 *
	 */
	protected $supportedImageTypes = array(
		'gif' => IMAGETYPE_GIF,
		'jpg' => IMAGETYPE_JPEG,
		'jpeg' => IMAGETYPE_JPEG,
		'png' => IMAGETYPE_PNG,
	);

	/**
	 * Indicates how much an image should be sharpened
	 *
	 * @var int
	 *
	 */
	protected $usmValue = 100;

	/**
	 * Result of iptcparse(), if available
	 *
	 * @var mixed
	 *
	 */
	protected $iptcRaw = null;

	/**
	 * List of valid IPTC tags (@horst)
	 *
	 * @var array
	 *
	 */
	protected $validIptcTags = array(
		'005', '007', '010', '012', '015', '020', '022', '025', '030', '035', '037', '038', '040', '045', '047', '050', '055', '060',
		'062', '063', '065', '070', '075', '080', '085', '090', '092', '095', '100', '101', '103', '105', '110', '115', '116', '118',
		'120', '121', '122', '130', '131', '135', '150', '199', '209', '210', '211', '212', '213', '214', '215', '216', '217');

	/**
	 * Information about the image from getimagesize (width, height, imagetype, channels, etc.)
	 *
	 * @var array|null
	 *
	 */
	protected $info = null;

	/**
	 * HiDPI scale value (2.0 = hidpi, 1.0 = normal)
	 *
	 * @var float
	 *
	 */
	protected $scale = 1.0;

	/**
	 * the resize wrapper method sets this to the width of the given source image
	 *
	 * @var integer
	 *
	 */
	protected $fullWidth;

	/**
	 * the resize wrapper method sets this to the height of the given source image
	 *
	 * @var integer
	 *
	 */
	protected $fullHeight;

	/**
	 * the resize wrapper method sets this to the width of the target image
	 * therefore it also may calculate it in regard of hiDpi
	 *
	 * @var integer
	 *
	 */
	protected $finalWidth;

	/**
	 * the resize wrapper method sets this to the height of the target image
	 * therefor it also may calculate it in regard of hiDpi
	 *
	 * @var integer
	 *
	 */
	protected $finalHeight;

	/**
	 * Data received from the setConfigData() method
	 * 
	 * @var array
	 * 
	 */
	protected $moduleConfigData = array();

	/**
	 * Collection of Imagefile and -format Informations from ImageInspector
	 * 
	 * @var null|array
	 *
	 */
	protected $inspectionResult = null;

	/******************************************************************************************************/
	
	public function __construct() {
		$this->set('enginePriority', 1);
		parent::__construct();
	}

	/**
	 * Prepare the ImageSizer (this should be the first method you call)
	 * 
	 * This is used as a replacement for __construct() since modules can't have required arguments
	 * to their constructor. 
	 *
	 * @param string $filename
	 * @param array $options
	 * @param null|array $inspectionResult
	 *
	 */
	public function prepare($filename, $options = array(), $inspectionResult = null) {

		// ensures the resize doesn't timeout the request (with at least 30 seconds)
		$this->setTimeLimit();

		// when invoked from Pageimage, $inspectionResult holds the InfoCollection from ImageInspector, otherwise NULL
		// if it is invoked manually and its value is NULL, the method loadImageInfo() will fetch the infos
		$this->inspectionResult = $inspectionResult;

		// filling all options with global custom values from config.php
		$options = array_merge($this->wire()->config->imageSizerOptions, $options);
		$this->setOptions($options);
		$this->loadImageInfo($filename, false);
	}
	
	/*************************************************************************************************
	 * ABSTRACT AND TEMPLATE METHODS
	 *
	 */

	/**
	 * Is this ImageSizer class ready only means: does the server / system provide all Requirements!
	 *
	 * @param string $action Optional type of action supported.
	 * @return bool
	 *
	 */
	abstract public function supported($action = 'imageformat');

	/**
	 * Process the image resize
	 *
	 * Processing is as follows:
	 *    1. first do a check if the given image(type) can be processed, if not do an early return false
	 *    2. than (try) to process all required steps, if one failes, return false
	 *    3. if all is successful, finally return true
	 *
	 * @param string $srcFilename Source file
	 * @param string $dstFilename Destination file
	 * @param int $fullWidth Current width
	 * @param int $fullHeight Current height
	 * @param int $finalWidth Requested final width
	 * @param int $finalHeight Requested final height
	 * @return bool True if successful, false if not
	 * @throws WireException
	 *
	 */
	abstract protected function processResize($srcFilename, $dstFilename, $fullWidth, $fullHeight, $finalWidth, $finalHeight);

	/**
	 * Process rotate of an image
	 * 
	 * @param string $srcFilename
	 * @param string $dstFilename
	 * @param int $degrees Clockwise degrees, i.e. 90, 180, 270, -90, -180, -270
	 * @return bool
	 * 
	 */
	protected function processRotate($srcFilename, $dstFilename, $degrees) { 
		if($srcFilename && $dstFilename && $degrees) {}
		$this->error('rotate not implemented for ' . $this->className());
		return false;
	}

	/**
	 * Process vertical or horizontal flip of an image
	 * 
	 * @param string $srcFilename
	 * @param string $dstFilename
	 * @param bool $flipVertical True if flip is vertical, false if flip is horizontal
	 * @return bool
	 * 
	 */
	protected function processFlip($srcFilename, $dstFilename, $flipVertical) {
		if($srcFilename && $dstFilename && $flipVertical) {}
		$this->error('flip not implemented for ' . $this->className());
		return false;
	}

	/**
	 * Get array of image file extensions this ImageSizerModule can process
	 *
	 * @return array of uppercase file extensions, i.e. ['PNG', 'JPG']
	 *
	 */
	abstract protected function validSourceImageFormats();

	/**
	 * Get an array of image file extensions this ImageSizerModule can create
	 *
	 * @return array of uppercase file extensions, i.e. ['PNG', 'JPG']
	 *
	 */
	protected function validTargetImageFormats() {
		return $this->validSourceImageFormats();
	}

	/**
	 * Get an array of image file formats this ImageSizerModule can use as source or target
	 * 
	 * Unless using the $type argument, returned array contains 'source' and 'target' indexes, 
	 * each an array of image file types/extensions in uppercase. 
	 * 
	 * @param string $type Specify 'source' or 'target' to get just those formats, or omit to get all.
	 * @return array 
	 * @since 3.0.138
	 * 
	 */
	public function getSupportedFormats($type = '') {
		$a = array(
			'source' => $this->validSourceImageFormats(),
			'target' => $this->validTargetImageFormats()
		);
		return $type && isset($a[$type]) ? $a[$type] : $a;
	}

	/**
	 * Get array of information about this engine
	 * 
	 * @return array
	 * @since 3.0.138
	 * 
	 */
	public function getEngineInfo() {
		
		$formats = $this->getSupportedFormats();
		$moduleName = $this->className();
		$className = $this->className(true);

		if(is_callable("$className::getModuleInfo")) {
			$moduleInfo = $className::getModuleInfo();
		} else {
			$moduleInfo = $this->wire()->modules->getModuleInfoVerbose($className);
		}

		if(!is_array($moduleInfo)) $moduleInfo = array();

		$info = array(
			'name' => str_replace('ImageSizerEngine', '', $moduleName),
			'title' => isset($moduleInfo['title']) ? $moduleInfo['title'] : '',
			'class' => $moduleName,
			'summary' => isset($moduleInfo['summary']) ? $moduleInfo['summary'] : '',
			'author' => isset($moduleInfo['author']) ? $moduleInfo['author'] : '',
			'moduleVersion' => isset($moduleInfo['version']) ? $moduleInfo['version'] : '',
			'libraryVersion' => $this->getLibraryVersion(),
			'priority' => $this->enginePriority,
			'sources' => $formats['source'],
			'targets' => $formats['target'],
			'quality' => $this->quality,
			'sharpening' => $this->sharpening,
		);

		return $info;
	}

	/*************************************************************************************************
	 * COMMON IMPLEMENTATION METHODS
	 *
	 */

	/**
	 * Load all image information from ImageInspector (Module)
	 *
	 * @param string $filename
	 * @param bool $reloadAll
	 * @throws WireException
	 *
	 */
	protected function loadImageInfo($filename, $reloadAll = false) {
		// if the engine is invoked manually, we need to inspect the image first
		if(empty($this->inspectionResult) || $reloadAll) {
			$imageInspector = new ImageInspector($filename);
			$this->inspectionResult = $imageInspector->inspect($filename, true);
		}
		if(null === $this->inspectionResult) throw new WireException("no valid filename passed to image inspector");
		if(false === $this->inspectionResult) throw new WireException(basename($filename) . " - not a recognized image");

		$this->filename = $this->inspectionResult['filename'];
		$this->extension = $this->inspectionResult['extension'];
		$this->imageType = $this->inspectionResult['imageType'];

		if(!in_array($this->imageType, $this->supportedImageTypes)) {
			throw new WireException(basename($filename) . " - not a supported image type");
		}

		$this->info = $this->inspectionResult['info'];
		$this->iptcRaw = $this->inspectionResult['iptcRaw'];
		// set width & height
		$this->setImageInfo($this->info['width'], $this->info['height']);
	}

	/**
	 * ImageInformation from Image Inspector
	 * in short form or full RawInfoData
	 *
	 * @param bool $rawData
	 * @return string|array
	 * @todo this appears to be a duplicate of what's in ImageSizer class?
	 *
	 */
	protected function getImageInfo($rawData = false) {
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
				$trans = is_array($this->inspectionResult['info']['trans']) ? '-trans' : '';
				$trans = $this->inspectionResult['info']['alpha'] ? '-alpha' : $trans;
				$animated = '';
				break;
		}
		
		return $type . $indexed . $trans . $animated;
	}
	
	/**
	 * Default IPTC Handling
	 *
	 * If we've retrieved IPTC-Metadata from sourcefile, we write it into the variation here but we omit
	 * custom tags for internal use (@horst)
	 *
	 * @param string $filename the file we want write the IPTC data to
	 * @param bool $includeCustomTags default is FALSE
	 * @return bool|null
	 *
	 */
	public function writeBackIPTC($filename, $includeCustomTags = false) {
		if($this->wire()->config->debug) {
			// add a timestamp and the name of the image sizer engine to the IPTC tag number 217
			$entry = $this->className() . '-' . date('Ymd:His');
			if(!$this->iptcRaw) $this->iptcRaw = array();
			$this->iptcRaw['2#217'] = array(0 => $entry);
		}
		if(!$this->iptcRaw) return null; // the sourceimage doesn't contain IPTC infos
		$content = iptcembed($this->iptcPrepareData($includeCustomTags), $filename);
		if(false === $content) return null;
		$extension = pathinfo($filename, \PATHINFO_EXTENSION);
		$dest = preg_replace('/\.' . $extension . '$/', '_tmp.' . $extension, $filename);
		if(strlen($content) == @file_put_contents($dest, $content, \LOCK_EX)) {
			// on success we replace the file
			$files = $this->wire()->files;
			$files->unlink($filename);
			$files->rename($dest, $filename);
			$files->chmod($filename);
			return true;
		} else {
			// it was created a temp diskfile but not with all data in it
			if(file_exists($dest)) @unlink($dest);
			return false;
		}
	}

	/**
	 * Save the width and height of the image
	 *
	 * @param int $width
	 * @param int $height
	 *
	 */
	protected function setImageInfo($width, $height) {
		$this->image['width'] = $width;
		$this->image['height'] = $height;
	}

	/**
	 * Return the image width
	 *
	 * @return int
	 *
	 */
	public function getWidth() {
		return $this->image['width'];
	}

	/**
	 * Return the image height
	 *
	 * @return int
	 *
	 */
	public function getHeight() {
		return $this->image['height'];
	}

	/**
	 * Given a target height, return the proportional width for this image
	 *
	 * @param int $targetHeight
	 *
	 * @return int
	 *
	 */
	protected function getProportionalWidth($targetHeight) {
		$img =& $this->image;
		return ceil(($targetHeight / $img['height']) * $img['width']); // @horst
	}

	/**
	 * Given a target width, return the proportional height for this image
	 *
	 * @param int $targetWidth
	 *
	 * @return int
	 *
	 */
	protected function getProportionalHeight($targetWidth) {
		$img =& $this->image;
		return ceil(($targetWidth / $img['width']) * $img['height']); // @horst
	}

	/**
	 * Get an array of the 4 dimensions necessary to perform the resize
	 *
	 * Note: Some code used in this method is adapted from code found in comments at php.net for the GD functions
	 *
	 * Intended for use by the resize() method
	 *
	 * @param int $targetWidth
	 * @param int $targetHeight
	 *
	 * @return array
	 *
	 */
	protected function getResizeDimensions($targetWidth, $targetHeight) {

		$pWidth = $targetWidth;
		$pHeight = $targetHeight;

		$img =& $this->image;

		if(!$targetHeight) $targetHeight = round(($targetWidth / $img['width']) * $img['height']);
		if(!$targetWidth) $targetWidth = round(($targetHeight / $img['height']) * $img['width']);

		$originalTargetWidth = $targetWidth;
		$originalTargetHeight = $targetHeight;

		if($img['width'] < $img['height']) {
			$pHeight = $this->getProportionalHeight($targetWidth);
		} else {
			$pWidth = $this->getProportionalWidth($targetHeight);
		}

		if($pWidth < $targetWidth) {
			// if the proportional width is smaller than specified target width
			$pWidth = $targetWidth;
			$pHeight = $this->getProportionalHeight($targetWidth);
		}

		if($pHeight < $targetHeight) {
			// if the proportional height is smaller than specified target height
			$pHeight = $targetHeight;
			$pWidth = $this->getProportionalWidth($targetHeight);
		}

		// rounding issue fix via @horst-n for #191 
		if($targetWidth == $originalTargetWidth && 1 + $targetWidth == $pWidth) $pWidth = $pWidth - 1;
		if($targetHeight == $originalTargetHeight && 1 + $targetHeight == $pHeight) $pHeight = $pHeight - 1;

		if(!$this->upscaling && ($img['width'] < $targetWidth || $img['height'] < $targetHeight)) {
			// via @horst-n PR #118: 
			// upscaling is not allowed and we have one or both dimensions to small,
			// we scale down the target dimensions to fit within the image dimensions, 
			// with respect to the target dimensions ratio
			$ratioSource = $img['height'] / $img['width'];
			$ratioTarget = !$this->cropping ? $ratioSource : $targetHeight / $targetWidth;
			if($ratioSource >= $ratioTarget) {
				// ratio is equal or target fits into source
				$pWidth = $targetWidth = $img['width'];
				$pHeight = $img['height'];
				$targetHeight = ceil($pWidth * $ratioTarget);
			} else {
				// target doesn't fit into source
				$pHeight = $targetHeight = $img['height'];
				$pWidth = $img['width'];
				$targetWidth = ceil($pHeight / $ratioTarget);
			}
			if($this->cropping) {
				// we have to disable any sharpening method here, 
				// as the source will not be resized, only cropped
				$this->sharpening = 'none';
			}
		}

		if(!$this->cropping) {
			// we will make the image smaller so that none of it gets cropped
			// this means we'll be adjusting either the targetWidth or targetHeight
			// till we have a suitable dimension

			if($pHeight > $originalTargetHeight) {
				$pHeight = $originalTargetHeight;
				$pWidth = $this->getProportionalWidth($pHeight);
				$targetWidth = $pWidth;
				$targetHeight = $pHeight;
			}
			if($pWidth > $originalTargetWidth) {
				$pWidth = $originalTargetWidth;
				$pHeight = $this->getProportionalHeight($pWidth);
				$targetWidth = $pWidth;
				$targetHeight = $pHeight;
			}
		}

		return array(
			0 => (int) $pWidth,
			1 => (int) $pHeight,
			2 => (int) $targetWidth,
			3 => (int) $targetHeight
		);
	}

	/**
	 * Was the image modified?
	 *
	 * @return bool
	 *
	 */
	public function isModified() {
		return $this->modified;
	}

	/**
	 * Given an unknown cropping value, return the validated internal representation of it
	 *
	 * @param string|bool|array $cropping
	 *
	 * @return string|bool|array
	 *
	 */
	static public function croppingValue($cropping) {

		if(is_string($cropping)) {
			$cropping = strtolower($cropping);
			if(strpos($cropping, ',')) {
				$cropping = explode(',', $cropping);
			} else if(strpos($cropping, 'x') && preg_match('/^([pd])(\d+)x(\d+)(z\d+)?/', $cropping, $matches)) {
				$cropping = array(
					0 => (int) $matches[2], // x 
					1 => (int) $matches[3] // y
				);
				if(isset($matches[4])) {
					$cropping[2] = (int) ltrim($matches[4], 'z'); // zoom
				}
				if($matches[1] == 'p') { // percent
					$cropping[0] .= '%';
					$cropping[1] .= '%';
				}
			}
		}
		if(is_array($cropping)) {
			if(strpos($cropping[0], '%') !== false) {
				$v = trim($cropping[0], '%');
				if(ctype_digit(trim($v, '-'))) $v = (int) $v;
				$cropping[0] = round(min(100, max(0, $v))) . '%';
			} else {
				$cropping[0] = (int) $cropping[0];
			}
			if(strpos($cropping[1], '%') !== false) {
				$v = trim($cropping[1], '%');
				if(ctype_digit(trim($v, '-'))) $v = (int) $v;
				$cropping[1] = round(min(100, max(0, $v))) . '%';
			} else {
				$cropping[1] = (int) $cropping[1];
			}
			if(isset($cropping[2])) { // zoom
				$cropping[2] = (int) $cropping[2]; 
				if($cropping[2] < 2 || $cropping[2] > 99) unset($cropping[2]);
			}
		}

		if($cropping === true) {
			$cropping = true; // default, crop to center
		} else if(!$cropping) {
			$cropping = false;
		} else if(is_array($cropping)) {
			// already took care of it above
		} else if(in_array($cropping, self::$croppingValues)) {
			$cropping = array_search($cropping, self::$croppingValues);
		} else if(array_key_exists($cropping, self::$croppingValues)) {
			// okay
		} else {
			$cropping = true; // unknown value or 'center', default to TRUE/center
		}

		return $cropping;
	}

	/**
	 * Given an unknown cropping value, return the string representation of it
	 *
	 * Okay for use in filenames
	 *
	 * @param string|bool|array $cropping
	 *
	 * @return string
	 *
	 */
	static public function croppingValueStr($cropping) {

		$cropping = self::croppingValue($cropping);

		// crop name if custom center point is specified
		if(is_array($cropping)) {
			// p = percent, d = pixel dimension, z = zoom
			$zoom = isset($cropping[2]) ? (int) $cropping[2] : 0;
			$cropping = 
				(strpos($cropping[0], '%') !== false ? 'p' : 'd') . 
				((int) rtrim($cropping[0], '%')) . 'x' . ((int) rtrim($cropping[1], '%'));
			if($zoom > 1 && $zoom < 100) $cropping .= "z$zoom";
		}

		// if crop is TRUE or FALSE, we don't reflect that in the filename, so make it blank
		if(is_bool($cropping)) $cropping = '';

		return $cropping;
	}

	/**
	 * Turn on/off cropping and/or set cropping direction
	 *
	 * @param bool|string|array $cropping Specify one of: northwest, north, northeast, west, center, east, southwest,
	 *     south, southeast. Or a string of: 50%,50% (x and y percentages to crop from) Or an array('50%', '50%') Or to
	 *     disable cropping, specify boolean false. To enable cropping with default (center), you may also specify
	 *     boolean true.
	 *
	 * @return self
	 *
	 */
	public function setCropping($cropping = true) {
		$this->cropping = self::croppingValue($cropping);
		return $this;
	}

	/**
	 * Set values for cropExtra rectangle, which enables cropping before resizing
	 *
	 * Added by @horst
	 *
	 * @param array $value containing 4 params (x y w h) indexed or associative
	 *
	 * @return self
	 * @throws WireException when given invalid value
	 *
	 */
	public function setCropExtra($value) {

		$this->cropExtra = null;
		$x = null;
		$y = null;
		$w = null;
		$h = null;

		if(!is_array($value) || 4 != count($value)) {
			throw new WireException('Missing or wrong param Array for ImageSizer-cropExtra!');
		}

		if(array_keys($value) === range(0, count($value) - 1)) {
			// we have a zerobased sequential array, we assume this order: x y w h
			list($x, $y, $w, $h) = $value;
		} else {
			// check for associative array
			foreach(array('x', 'y', 'w', 'h') as $v) {
				if(isset($value[$v])) $$v = $value[$v];
			}
		}
		
		foreach(array('x', 'y', 'w', 'h') as $k) {
			$v = (int) (isset($$k) ? $$k : -1);
			if(!$v && $k == 'w' && $h > 0) $v = $this->getProportionalWidth((int) $h); 
			if(!$v && $k == 'h' && $w > 0) $v = $this->getProportionalHeight((int) $w); 
			if($v < 0) throw new WireException("Missing or wrong param $k=$v for ImageSizer-cropExtra! " . print_r($value, true));
			if(('w' == $k || 'h' == $k) && 0 == $v) throw new WireException("Wrong param $k=$v for ImageSizer-cropExtra! " . print_r($value, true));
		}

		$this->cropExtra = array($x, $y, $w, $h);

		return $this;
	}

	/**
	 * Set the image quality 1-100, where 100 is highest quality
	 *
	 * @param int $n
	 *
	 * @return self
	 *
	 */
	public function setQuality($n) {
		$this->quality = $this->getIntegerValue($n, 1, 100);
		return $this;
	}

	/**
	 * Set the image quality 1-100 for WebP output, where 100 is highest quality
	 *
	 * @param int $n
	 *
	 * @return self
	 *
	 */
	public function setWebpQuality($n) {
		$this->webpQuality = $this->getIntegerValue($n, 1, 100);
		return $this;
	}

	/**
	 * Set flag to also create a webp file or not
	 *
	 * @param bool $webpAdd
	 * @param bool|null $webpOnly
	 * @return self
	 *
	 */
	public function setWebpAdd($webpAdd, $webpOnly = null) {
		$this->webpAdd = (bool) $webpAdd;
		if(is_bool($webpOnly)) $this->webpOnly = $webpOnly;
		return $this;
	}

	/**
	 * Set flag to only create a webp file
	 *
	 * @param bool value$
	 * @return self
	 *
	 */
	public function setWebpOnly($value) {
		$this->webpOnly = (bool) $value;
		if($this->webpOnly) $this->webpAdd = true; // webpAdd required for webpOnly
		return $this;
	}

	/**
	 * Given an unknown sharpening value, return the string representation of it
	 *
	 * Okay for use in filenames. Method added by @horst
	 *
	 * @param string|bool $value
	 * @param bool $short
	 *
	 * @return string
	 *
	 */
	static public function sharpeningValueStr($value, $short = false) {

		$sharpeningValues = self::$sharpeningValues;

		if(is_string($value) && in_array(strtolower($value), $sharpeningValues)) {
			$ret = strtolower($value);

		} else if(is_int($value) && isset($sharpeningValues[$value])) {
			$ret = $sharpeningValues[$value];

		} else if(is_bool($value)) {
			$ret = $value ? "soft" : "none";

		} else {
			// sharpening is unknown, return empty string
			return '';
		}

		if(!$short) return $ret;    // return name
		$flip = array_flip($sharpeningValues);
		return 's' . $flip[$ret];   // return char s appended with the numbered index
	}

	/**
	 * Set sharpening value: blank (for none), soft, medium, or strong
	 *
	 * @param mixed $value
	 *
	 * @return self
	 * @throws WireException
	 *
	 */
	public function setSharpening($value) {

		if(is_string($value) && in_array(strtolower($value), self::$sharpeningValues)) {
			$ret = strtolower($value);

		} else if(is_int($value) && isset(self::$sharpeningValues[$value])) {
			$ret = self::$sharpeningValues[$value];

		} else if(is_bool($value)) {
			$ret = $value ? "soft" : "none";

		} else {
			throw new WireException("Unknown value for sharpening");
		}

		$this->sharpening = $ret;

		return $this;
	}

	/**
	 * Turn on/off auto rotation
	 *
	 * @param bool $value Whether to auto-rotate or not (default = true)
	 *
	 * @return self
	 *
	 */
	public function setAutoRotation($value = true) {
		$this->autoRotation = $this->getBooleanValue($value);
		return $this;
	}

	/**
	 * Turn on/off upscaling
	 *
	 * @param bool $value Whether to upscale or not (default = true)
	 *
	 * @return self
	 *
	 */
	public function setUpscaling($value = true) {
		$this->upscaling = $this->getBooleanValue($value);
		return $this;
	}
	
	/**
	 * Turn on/off interlace 
	 *
	 * @param bool $value Whether to upscale or not (default = true)
	 *
	 * @return self
	 *
	 */
	public function setInterlace($value = true) {
		$this->interlace = $this->getBooleanValue($value);
		return $this;
	}

	/**
	 * Set default gamma value: 0.5 - 4.0 | -1
	 *
	 * @param float|int $value 0.5 to 4.0 or -1 to disable
	 *
	 * @return self
	 * @throws WireException when given invalid value
	 *
	 */
	public function setDefaultGamma($value = 2.2) {
		if($value === -1 || ($value >= 0.5 && $value <= 4.0)) {
			$this->defaultGamma = $value;
		} else {
			throw new WireException('Invalid defaultGamma value - must be 0.5 - 4.0 or -1 to disable gammacorrection');
		}
		return $this;
	}

	/**
	 * Set a time limit for manipulating one image (default is 30)
	 *
	 * If specified time limit is less than PHP's max_execution_time, then PHP's setting will be used instead.
	 *
	 * @param int $value 10 to 60 recommended, default is 30
	 *
	 * @return self
	 *
	 */
	public function setTimeLimit($value = 30) {
		// imagesizer can get invoked from different locations, including those that are inside of loops
		// like the wire/modules/Inputfield/InputfieldFile/InputfieldFile.module :: ___renderList() method

		$prevLimit = ini_get('max_execution_time');

		// if unlimited execution time, no need to introduce one
		if(!$prevLimit) return $this;

		// don't override a previously set high time limit, just start over with it
		$timeLimit = (int) ($prevLimit > $value ? $prevLimit : $value);

		// restart time limit
		set_time_limit($timeLimit);

		return $this;
	}

	/**
	 * Set scale for hidpi (2.0=hidpi, 1.0=normal, or other value if preferred)
	 *
	 * @param float $scale
	 *
	 * @return self
	 *
	 */
	public function setScale($scale) {
		$this->scale = (float) $scale;
		return $this;
	}

	/**
	 * Enable hidpi mode?
	 *
	 * Just a shortcut for calling $this->scale()
	 *
	 * @param bool $hidpi True or false (default=true)
	 *
	 * @return self
	 *
	 */
	public function setHidpi($hidpi = true) {
		return $this->setScale($hidpi ? 2.0 : 1.0);
	}

	/**
	 * Set rotation degrees
	 *
	 * Specify one of: -270, -180, -90, 90, 180, 270
	 *
	 * @param $degrees
	 *
	 * @return self
	 *
	 */
	public function setRotate($degrees) {
		$valid = array(-270, -180, -90, 90, 180, 270);
		$degrees = (int) $degrees;
		if(in_array($degrees, $valid)) $this->rotate = $degrees;
		return $this;
	}

	/**
	 * Set flip
	 *
	 * Specify one of: 'vertical' or 'horizontal', also accepts
	 * shorter versions like, 'vert', 'horiz', 'v', 'h', etc.
	 *
	 * @param $flip
	 *
	 * @return self
	 *
	 */
	public function setFlip($flip) {
		$flip = strtolower(substr($flip, 0, 1));
		if($flip == 'v' || $flip == 'h') $this->flip = $flip;
		return $this;
	}

	/**
	 * Toggle on/off the usage of USM algorithm for sharpening
	 *
	 * @param bool $value Whether to USM is used or not (default = true)
	 *
	 * @return self
	 *
	 */
	public function setUseUSM($value = true) {
		$this->useUSM = true === $this->getBooleanValue($value) ? true : false;
		return $this;
	}

	/**
	 * Alternative to the above set* functions where you specify all in an array
	 *
	 * @param array $options May contain the following (show with default values):
	 *    'quality' => 90,
	 *    'webpQuality' => 90,
	 *    'cropping' => true,
	 *    'upscaling' => true,
	 *    'autoRotation' => true,
	 *    'sharpening' => 'soft' (none|soft|medium|string)
	 *    'scale' => 1.0 (use 2.0 for hidpi or 1.0 for normal-default)
	 *    'hidpi' => false, (alternative to scale, specify true to enable hidpi)
	 *    'rotate' => 0 (90, 180, 270 or negative versions of those)
	 *    'flip' => '', (vertical|horizontal)
	 *
	 * @return self
	 *
	 */
	public function setOptions(array $options) {

		foreach($options as $key => $value) {
			switch($key) {

				case 'autoRotation':
					$this->setAutoRotation($value);
					break;
				case 'upscaling':
					$this->setUpscaling($value);
					break;
				case 'interlace':
					$this->setInterlace($value);
					break;
				case 'sharpening':
					$this->setSharpening($value);
					break;
				case 'quality':
					$this->setQuality($value);
					break;
				case 'webpQuality':
					$this->setWebpQuality($value);
					break;
				case 'webpAdd':
					$this->setWebpAdd($value);
					break;
				case 'webpOnly':
					$this->setWebpOnly($value);
					break;
				case 'cropping':
					$this->setCropping($value);
					break;
				case 'defaultGamma':
					$this->setDefaultGamma($value);
					break;
				case 'cropExtra':
					$this->setCropExtra($value);
					break;
				case 'scale':
					$this->setScale($value);
					break;
				case 'hidpi':
					$this->setHidpi($value);
					break;
				case 'rotate':
					$this->setRotate($value);
					break;
				case 'flip':
					$this->setFlip($value);
					break;
				case 'useUSM':
					$this->setUseUSM($value);
					break;

				default:
					// unknown or 3rd party option
					$this->options[$key] = $value;
			}
		}

		return $this;
	}

	/**
	 * Given a value, convert it to a boolean.
	 *
	 * Value can be string representations like: 0, 1 off, on, yes, no, y, n, false, true.
	 *
	 * @param bool|int|string $value
	 *
	 * @return bool
	 *
	 */
	protected function getBooleanValue($value) {
		if(in_array(strtolower($value), array('0', 'off', 'false', 'no', 'n', 'none'))) return false;
		return ((int) $value) > 0;
	}
	
	/**
	 * Get integer value within given range
	 *
	 * @param int $n Number to require in given range
	 * @param int $min Minimum allowed number
	 * @param int $max Maximum allowed number
	 * @return int
	 *
	 */
	protected function getIntegerValue($n, $min, $max) {
		$n = (int) $n;
		if($n < $min) {
			$n = $min;
		} else if($n > $max) {
			$n = $max;
		}
		return $n;
	}

	/**
	 * Return an array of the current options
	 *
	 * @return array
	 *
	 */
	public function getOptions() {

		$options = array(
			'quality' => $this->quality,
			'webpQuality' => $this->webpQuality,
			'webpAdd' => $this->webpAdd,
			'webpOnly' => $this->webpOnly,
			'cropping' => $this->cropping,
			'upscaling' => $this->upscaling,
			'interlace' => $this->interlace, 
			'autoRotation' => $this->autoRotation,
			'sharpening' => $this->sharpening,
			'defaultGamma' => $this->defaultGamma,
			'cropExtra' => $this->cropExtra,
			'scale' => $this->scale,
			'useUSM' => $this->useUSM,
		);

		$options = array_merge($this->options, $options);

		return $options;
	}

	/**
	 * Get a property
	 *
	 * @param string $key
	 *
	 * @return mixed|null
	 *
	 */
	public function get($key) {

		$keys = array(
			'filename',
			'extension',
			'imageType',
			'image',
			'modified',
			'supportedImageTypes',
			'info',
			'iptcRaw',
			'validIptcTags',
			'cropExtra',
			'options'
		);

		if($key === 'webpResult') return $this->webpResult;
		if($key === 'webpOnly') return $this->webpOnly;
		if(in_array($key, $keys)) return $this->$key;
		if(in_array($key, $this->optionNames)) return $this->$key;
		if(isset($this->options[$key])) return $this->options[$key];
		
		return parent::get($key);
	}

	/**
	 * Return the filename
	 *
	 * @return string
	 *
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * Return the file extension
	 *
	 * @return string
	 *
	 */
	public function getExtension() {
		return $this->extension;
	}

	/**
	 * Return the image type constant
	 *
	 * @return string|null
	 *
	 */
	public function getImageType() {
		return $this->imageType;
	}

	/**
	 * Prepare IPTC data (@horst)
	 *
	 * @param bool $includeCustomTags (default=false)
	 *
	 * @return string $iptcNew
	 *
	 */
	protected function iptcPrepareData($includeCustomTags = false) {
		$customTags = array('213', '214', '215', '216', '217');
		$iptcNew = '';
		foreach(array_keys($this->iptcRaw) as $s) {
			$tag = substr($s, 2);
			if(!$includeCustomTags && in_array($tag, $customTags)) continue;
			if(substr($s, 0, 1) == '2' && in_array($tag, $this->validIptcTags) && is_array($this->iptcRaw[$s])) {
				foreach($this->iptcRaw[$s] as $row) {
					$iptcNew .= $this->iptcMakeTag(2, $tag, $row);
				}
			}
		}
		return $iptcNew;
	}

	/**
	 * Make IPTC tag (@horst)
	 *
	 * @param string $rec
	 * @param string $dat
	 * @param string $val
	 *
	 * @return string
	 *
	 */
	protected function iptcMakeTag($rec, $dat, $val) {
		$len = strlen($val);
		if($len < 0x8000) {
			return @chr(0x1c) . @chr($rec) . @chr($dat) .
			chr($len >> 8) .
			chr($len & 0xff) .
			$val;
		} else {
			return chr(0x1c) . chr($rec) . chr($dat) .
			chr(0x80) . chr(0x04) .
			chr(($len >> 24) & 0xff) .
			chr(($len >> 16) & 0xff) .
			chr(($len >> 8) & 0xff) .
			chr(($len) & 0xff) .
			$val;
		}
	}

	/**
	 * Check orientation (@horst)
	 *
	 * @param array
	 *
	 * @return bool
	 *
	 */
	protected function checkOrientation(&$correctionArray) {
		// first value is rotation-degree and second value is flip-mode: 0=NONE | 1=HORIZONTAL | 2=VERTICAL
		$corrections = array(
			'1' => array(0, 0),
			'2' => array(0, 1),
			'3' => array(180, 0),
			'4' => array(0, 2),
			'5' => array(90, 1),    // OLD: 270
			'6' => array(90, 0),    // OLD: 270
			'7' => array(270, 1),   // OLD: 90
			'8' => array(270, 0)    // OLD: 90
		);
		
		if(!isset($this->info['orientation']) || !isset($corrections[strval($this->info['orientation'])])) {
			return false;
		}
		
		$correctionArray = $corrections[strval($this->info['orientation'])];
		
		return true;
	}


	/**
	 * Check for alphachannel in PNGs
	 *
	 * @return bool
	 *
	 */
	protected function hasAlphaChannel() {
		if(!isset($this->info['alpha']) && !isset($this->info['trans'])) return false;
		if(isset($this->info['alpha']) && $this->info['alpha']) return true;
		if(isset($this->info['trans']) && $this->info['trans']) return true;
		return false;
	}

	/**
	 * Set whether the image was modified
	 *
	 * Public so that other modules/hooks can adjust this property if needed.
	 * Not for general API use
	 *
	 * @param bool $modified
	 *
	 * @return self
	 *
	 */
	public function setModified($modified) {
		$this->modified = $modified ? true : false;
		return $this;
	}

	/**
	 * Get whether the image was modified
	 * 
	 * @return bool
	 * 
	 */
	public function getModified() {
		return $this->modified;
	}

	/**
	 * Check if cropping is needed, if yes, populate x- and y-position to params $w1 and $h1
	 *
	 * Intended for use by the resize() method
	 *
	 * @param int $w1 - byReference
	 * @param int $h1 - byReference
	 * @param int $gdWidth
	 * @param int $targetWidth
	 * @param int $gdHeight
	 * @param int $targetHeight
	 *
	 */
	protected function getCropDimensions(&$w1, &$h1, $gdWidth, $targetWidth, $gdHeight, $targetHeight) {

		if(is_string($this->cropping)) {
			// calculate from 8 named cropping points 
			switch($this->cropping) {
				case 'nw':
					$w1 = 0;
					$h1 = 0;
					break;
				case 'n':
					$h1 = 0;
					break;
				case 'ne':
					$w1 = $gdWidth - $targetWidth;
					$h1 = 0;
					break;
				case 'w':
					$w1 = 0;
					break;
				case 'e':
					$w1 = $gdWidth - $targetWidth;
					break;
				case 'sw':
					$w1 = 0;
					$h1 = $gdHeight - $targetHeight;
					break;
				case 's':
					$h1 = $gdHeight - $targetHeight;
					break;
				case 'se':
					$w1 = $gdWidth - $targetWidth;
					$h1 = $gdHeight - $targetHeight;
					break;
				default: // center or false, we do nothing
			}

		} else if(is_array($this->cropping)) {
			// calculate from specific percent or pixels from left and top
			// $this->cropping is an array with the following:
			// index 0 represents % or pixels from left
			// index 1 represents % or pixels from top
			// @interrobang + @u-nikos
			if(strpos($this->cropping[0], '%') === false) {
				$pointX = (int) $this->cropping[0];
			} else {
				$pointX = $gdWidth * ((int) $this->cropping[0] / 100);
			}

			if(strpos($this->cropping[1], '%') === false) {
				$pointY = (int) $this->cropping[1];
			} else {
				$pointY = $gdHeight * ((int) $this->cropping[1] / 100);
			}
		
			/*
			if(isset($this->cropping[2]) && $this->cropping[2] > 1) {
				// zoom percent (2-100)
				$zoom = (int) $this->cropping[2];
			}
			*/

			if($pointX < $targetWidth / 2) {
				$w1 = 0;
			} else if($pointX > ($gdWidth - $targetWidth / 2)) {
				$w1 = $gdWidth - $targetWidth;
			} else {
				$w1 = $pointX - $targetWidth / 2;
			}

			if($pointY < $targetHeight / 2) {
				$h1 = 0;
			} else if($pointY > ($gdHeight - $targetHeight / 2)) {
				$h1 = $gdHeight - $targetHeight;
			} else {
				$h1 = $pointY - $targetHeight / 2;
			}
		}

	}

	/*************************************************************************************************/

	/**
	 * Resize the image
	 * 
	 * The resize method does all pre and post-processing for the engines + calls the engine's 
	 * processResize() method.
	 *
	 * Pre-processing is: 
	 *   Calculate and set dimensions, create a tempfile.
	 *
	 * Post-processing is: 
	 *   Copy back and delete tempfile, write IPTC if necessary, reload imageinfo, set the modified flag.
	 *
	 * @param int $finalWidth
	 * @param int $finalHeight
	 * @return bool
	 *
	 */
	public function resize($finalWidth, $finalHeight) {

		// @todo is this call necessary, since ImageSizer.php would have already checked this?
		if(!$this->supported()) return false;

		// first prepare dimension settings for the engine(s)
		$this->finalWidth = $finalWidth;
		$this->finalHeight = $finalHeight;
		$this->fullWidth = $this->image['width'];
		$this->fullHeight = $this->image['height'];

		if(0 == $this->finalWidth && 0 == $this->finalHeight) return false;

		if($this->scale !== 1.0) { // adjust for hidpi
			if($this->finalWidth) $this->finalWidth = ceil($this->finalWidth * $this->scale);
			if($this->finalHeight) $this->finalHeight = ceil($this->finalHeight * $this->scale);
		}

		// create another temp copy so that we have the source unaltered if an engine fails
		// and we need to start another one
		$this->tmpFile = $this->filename . '-tmp.' . $this->extension;
		if(!@copy($this->filename, $this->tmpFile)) return false; // fallback or failed

		// lets the engine do the resize work
		if(!$this->processResize(
			$this->filename, $this->tmpFile, 
			$this->fullWidth, $this->fullHeight, 
			$this->finalWidth, $this->finalHeight)) {
			return false; // fallback or failed
		}
		
		$files = $this->wire()->files;

		if($this->webpOnly) {
			$files->unlink($this->tmpFile);
		} else {
			// all went well, copy back the temp file,
			if(!@copy($this->tmpFile, $this->filename)) return false; // fallback or failed 
			$files->chmod($this->filename);
			// remove the temp file
			$files->unlink($this->tmpFile);
			// post processing: IPTC, setModified and reload ImageInfo
			$this->writeBackIPTC($this->filename, false);
		}
		$this->setModified($this->modified);
		$this->loadImageInfo($this->filename, true);

		return true;
	}

	/**
	 * Just rotate image by number of degrees
	 * 
	 * @param int $degrees
	 * @param string $dstFilename Optional destination filename. If not present, source will be overwritten. 
	 * @return bool True on success, false on fail
	 * 
	 */
	public function rotate($degrees, $dstFilename = '') {

		$files = $this->wire()->files;
		$degrees = (int) $degrees;
		$srcFilename = $this->filename;
		
		if(empty($dstFilename)) $dstFilename = $srcFilename;
		
		if($degrees > 360) $degrees = 360 - $degrees;
		if($degrees < -360) $degrees = $degrees - 360;

		if($degrees == 0 || $degrees == 360 || $degrees == -360) {
			if($dstFilename != $this->filename) $files->copy($this->filename, $dstFilename);
			return true;
		}

		if($srcFilename == $dstFilename) {
			// src and dest are the same, so use a temporary file 
			$n = 1;
			do {
				$tmpFilename = dirname($dstFilename) . "/.ise$n-" . basename($dstFilename);
			} while(file_exists($tmpFilename) && $n++); 
		} else {
			// src and dest are different files
			$tmpFilename = $dstFilename;
		}
		
		$result = $this->processRotate($srcFilename, $tmpFilename, $degrees);
		
		if($result) {
			// success
			if($tmpFilename != $dstFilename) {
				if(is_file($dstFilename)) $files->unlink($dstFilename);
				$files->rename($tmpFilename, $dstFilename);
			}
			$files->chmod($dstFilename);
		} else {
			// fail
			if(is_file($tmpFilename)) $files->unlink($tmpFilename);	
		}
		
		return $result;
	}

	/**
	 * Flip vertically 
	 * 
	 * @param string $dstFilename
	 * @return bool
	 * 
	 */
	public function flipVertical($dstFilename = '') {
		if(empty($dstFilename)) $dstFilename = $this->filename;
		return $this->processFlip($this->filename, $dstFilename, 'vertical'); 
	}

	/**
	 * Flip horizontally 
	 * 
	 * @param string $dstFilename
	 * @return bool
	 * 
	 */	
	public function flipHorizontal($dstFilename = '') {
		if(empty($dstFilename)) $dstFilename = $this->filename;
		return $this->processFlip($this->filename, $dstFilename, 'horizontal'); 
	}

	/**
	 * Flip both vertically and horizontally
	 * 
	 * @param string $dstFilename
	 * @return bool
	 * 
	 */
	public function flipBoth($dstFilename = '') {
		if(empty($dstFilename)) $dstFilename = $this->filename;
		return $this->processFlip($this->filename, $dstFilename, 'both');
	}
	
	/**
	 * Convert image to greyscale
	 *
	 * @param string $dstFilename If different from source file
	 * @return bool
	 *
	 */
	public function convertToGreyscale($dstFilename = '') {
		if($dstFilename) {}
		return false;
	}

	/**
	 * Convert image to sepia
	 *
	 * @param string $dstFilename If different from source file
	 * @param float|int $sepia Sepia value
	 * @return bool
	 *
	 */
	public function convertToSepia($dstFilename = '', $sepia = 55) {
		if($dstFilename && $sepia) {}
		return false;
	}

	/**
	 * Get an integer representing the resize method to use
	 *
	 * This method calculates all dimensions at first. It is called before any of the main image operations,
	 * but after rotation and crop_before_resize. As result it returns an integer [0|2|4] that indicates which
	 * steps should be processed:
	 *
	 * 0 = this is the case if the original size is requested or a greater size but upscaling is set to false
	 * 2 = only resize with aspect ratio
	 * 4 = resize and crop with aspect ratio
	 *
	 * @param mixed $gdWidth
	 * @param mixed $gdHeight
	 * @param mixed $targetWidth
	 * @param mixed $targetHeight
	 * @param mixed $x1
	 * @param mixed $y1
	 *
	 * @return int 0|2|4
	 *
	 */
	protected function getResizeMethod(&$gdWidth, &$gdHeight, &$targetWidth, &$targetHeight, &$x1, &$y1) {
		list($gdWidth, $gdHeight, $targetWidth, $targetHeight) = $this->getResizeDimensions($targetWidth, $targetHeight);
		$x1 = ($gdWidth / 2) - ($targetWidth / 2);
		$y1 = ($gdHeight / 2) - ($targetHeight / 2);
		$this->getCropDimensions($x1, $y1, $gdWidth, $targetWidth, $gdHeight, $targetHeight);
		$x1 = intval($x1);
		$y1 = intval($y1);
		if($gdWidth == $targetWidth && $gdWidth == $this->image['width'] && $gdHeight == $this->image['height'] && $gdHeight == $targetHeight) return 0;
		if($gdWidth == $targetWidth && $gdHeight == $targetHeight) return 2;
		return 4;
	}

	/**
	 * Helper function to perform a cropExtra / cropBefore cropping
	 *
	 * Intended for use by the getFocusZoomCropDimensions() method
	 *
	 * @param string $focus (focus point in percent, like: 54.7%)
	 * @param int $sourceDimension (source image width or height)
	 * @param int $cropDimension (target crop-image width or height)
	 * @param int $zoom
	 *
	 * @return int $position (crop position x or y in pixel)
	 *
	 */
	protected function getFocusZoomPosition($focus, $sourceDimension, $cropDimension, $zoom) {
		$focus = intval($focus); // string with float value and percent char, (needs to be converted to integer)
		$scale = 1 + (($zoom / 100) * 2);
		$focusPX = ($sourceDimension / 100 * $focus);
		$posMinPX = $cropDimension / 2 / $scale;
		$posMaxPX = $sourceDimension - ($cropDimension / 2);

		// calculate the position in pixel !
		if($focusPX >= $posMaxPX) {
			$posPX = $sourceDimension - $cropDimension;
		} else if($focusPX <= $posMinPX) {
			$posPX = 0;
		} else {
			$posPX = $focusPX - ($cropDimension / 2);
			if(0 > $posPX) $posPX = 0;
		}

		return $posPX;
	}

	/**
	 * Get an array of the 4 dimensions necessary to perform a cropExtra / cropBefore cropping
	 *
	 * Intended for use by the resize() method
	 *
	 * @param int $zoom
	 * @param int $fullWidth
	 * @param int $fullHeight
	 * @param int $finalWidth
	 * @param int $finalHeight
	 * @return array
	 *
	 */
	protected function getFocusZoomCropDimensions($zoom, $fullWidth, $fullHeight, $finalWidth, $finalHeight) {
		// validate & calculate / prepare params
		$zoom = $zoom <= 70 ? $zoom : 70; // validate / correct the zoom value, it needs to be between 2 and 70
		$zoom = $zoom >= 2 ? $zoom : 2;

		// calculate the max crop dimensions
		$ratioFinal = $finalWidth / $finalHeight; // get the ratio of the requested crop
		$percentW = $finalWidth / $fullWidth * 100; // calculate percentage of the crop width in regard of the original width
		$percentH = $finalHeight / $fullHeight * 100; // calculate percentage of the crop height in regard of the original height
		if($percentW >= $percentH) { // check wich one is greater
			$maxW = $fullWidth; // if percentW is greater, maxW becomes the original Width
			$maxH = $fullWidth / $ratioFinal; // ... and maxH gets calculated via the ratio
		} else {
			$maxH = $fullHeight; // if percentH is greater, maxH becomes the original Height
			$maxW = $fullHeight * $ratioFinal; // ... and maxW gets calculated via the ratio
		}

		// calculate the zoomed dimensions
		$cropW = $maxW - ($maxW * $zoom / 100); // to get the final crop Width and Height, the amount for zoom-in
		$cropH = $maxH - ($maxH * $zoom / 100); // needs to get stripped out

		// validate against the minimal dimensions
		if(!$this->upscaling) { // if upscaling isn't allowed, we decrease the zoom, so that we get a crop with the min-Dimensions
			if($cropW < $finalWidth) {
				$cropW = $finalWidth;
				$cropH = $finalWidth / $ratioFinal;
			}
			if($cropH < $finalHeight) {
				$cropH = $finalHeight;
				$cropW = $finalHeight * $ratioFinal;
			}
		}

		// calculate the crop positions
		$posX = $this->getFocusZoomPosition($this->cropping[0], $fullWidth, $cropW, $zoom); // calculate the x-position
		$posY = $this->getFocusZoomPosition($this->cropping[1], $fullHeight, $cropH, $zoom); // calculate the y-position

		return array(
			0 => (int) $posX,
			1 => (int) $posY,
			2 => (int) $cropW,
			3 => (int) $cropH
		);
	}

	/**
	 * Get current zoom percentage setting or 0 if not set
	 * 
	 * Value is determined from the $this->cropping array index 2 and is used only if index 0 and
	 * index 1 are percentages (and indicated as such with a percent sign). 
	 * 
	 * @return int
	 * 
	 */
	protected function getFocusZoomPercent() {
		// check if we have to proceed a zoomed focal point cropping,
		// therefore we need index 0 and 1 to be strings with '%' sign included
		// and index 2 to be an integer between 2 and 70
		$a = $this->cropping;
		if(is_array($a) && isset($a[2]) && strpos($a[0], '%') !== false && strpos($a[1], '%') !== false) {
			$zoom = (int) $a[2];
			if($zoom < 2) $zoom = 0;
			if($zoom > 70) $zoom = 70;
		} else {
			$zoom = 0;
		}
		return $zoom;
	}

	/**
	 * Module info: not-autoload
	 * 
	 * @return bool
	 * 
	 */
	public function isAutoload() {
		return false;
	}

	/**
	 * Module info: not singular
	 * 
	 * @return bool
	 * 
	 */
	public function isSingular() {
		return false;
	}

	/**
	 * Set module config data for ConfigurableModule interface
	 * 
	 * @param array $data
	 * 
	 */
	public function setConfigData(array $data) {
		if(count($data)) $this->moduleConfigData = $data;
		foreach($data as $key => $value) {
			if($key == 'sharpening') {
				$this->setSharpening($value);
			} else if($key == 'quality') {
				$this->setQuality($value);
			} else {
				$this->set($key, $value);
			}
		}
	}

	/**
	 * Get module config data
	 * 
	 * @return array
	 * 
	 */
	public function getConfigData() {
		return $this->moduleConfigData;
	}

	/**
	 * Get library version string
	 * 
	 * @return string Returns version string or blank string if not applicable/available
	 * @since 3.0.138
	 * 
	 */
	public function getLibraryVersion() {
		return '';
	}

	/**
	 * Module configuration
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$f = $inputfields->InputfieldInteger;
		$f->attr('name', 'enginePriority');
		$f->label = $this->_('Engine priority');
		$f->description = $this->_('This determines what order this engine is tried in relation to other ImageSizerEngine modules.');
		$f->description .= ' ' . $this->_('The lower the number, the more preference you give it.');
		$f->description .= ' ' . $this->_('If you have other ImageSizerEngine modules installed, make sure no two have the same priority.');
		$f->attr('value', $this->enginePriority);
		$f->icon = 'sort-numeric-asc';
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldRadios;
		$f->attr('name', 'sharpening'); 
		$f->label = $this->_('Sharpening');
		$f->addOption('none', $this->_('None'));
		$f->addOption('soft', $this->_('Soft'));
		$f->addOption('medium', $this->_('Medium'));
		$f->addOption('strong', $this->_('Strong'));
		$f->optionColumns = 1;
		$f->attr('value', $this->sharpening);
		$f->icon = 'image';
		$inputfields->add($f);

		$f = $inputfields->InputfieldInteger;
		$f->attr('name', 'quality');
		$f->label = $this->_('Quality');
		$f->description = $this->_('Default quality setting from 1 to 100 where 1 is lowest quality, and 100 is highest.');
		$f->attr('value', $this->quality);
		$f->min = 0;
		$f->max = 100;
		$f->icon = 'dashboard';
		$inputfields->add($f);
	}

}
