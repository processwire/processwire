<?php namespace ProcessWire;

/**
 * ProcessWire Pageimage
 *
 * #pw-summary Represents an image item attached to a page, typically via an Image Fieldtype. 
 * #pw-summary-variations A variation refers to an image that is based upon another (like a resized or cropped version for example). 
 * #pw-order-groups common,resize-and-crop,variations,other
 * #pw-use-constructor
 * #pw-body = 
 * Pageimage objects are usually contained by a `Pageimages` object, which is a type of `Pagefiles` and `WireArray`. 
 * In addition to the methods and properties below, you'll also want to look at `Pagefile` which this class inherits
 * several important methods and properties from. 
 * 
 * ~~~~~
 * // Example of outputting a thumbnail gallery of Pageimage objects
 * foreach($page->images as $image) {
 *   // $image and $thumb are both Pageimage objects
 *   $thumb = $image->size(200, 200);    
 *   echo "<a href='$image->url'>"; 
 *   echo "<img src='$thumb->url' alt='$image->description' />";
 *   echo "</a>";
 * }
 * ~~~~~
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @property int $width Width of image, in pixels.
 * @property int $height Height of image, in pixels.
 * @property int $hidpiWidth HiDPI width of image, in pixels. #pw-internal
 * @property int $hidpiHeight HiDPI heigh of image, in pixels. #pw-internal
 * @property string $error Last image resizing error message, when applicable. #pw-group-resize-and-crop
 * @property Pageimage $original Reference to original $image, if this is a resized version. #pw-group-variations
 * @property string $url
 * @property string $basename
 * @property string $filename
 * 
 * @method bool|array isVariation($basename, $allowSelf = false)
 * @method Pageimage crop($x, $y, $width, $height, $options = array())
 * @method array rebuildVariations($mode = 0, array $suffix = array(), array $options = array())
 * @method install($filename)
 *
 */

class Pageimage extends Pagefile {

	/**
	 * Reference to the collection of Pageimages that this Pageimage belongs to
	 * 
	 * @var Pageimages
	 *
	 */
	protected $pageimages; 

	/**
	 * Reference to the original image this variation was created from
	 *
	 * Applicable only if this image is a variation (resized version). It will be null in all other instances. 
	 * 
	 * @var Pageimage
	 *
	 */
	protected $original = null;

	/**
	 * Cached result of the getVariations() method
	 *
	 * Don't reference this directly, because it won't be loaded unless requested, instead use the getVariations() method
	 *
	 */
	private $variations = null; 

	/**
	 * Cached result of the getImageInfo() method
	 *
	 * Don't reference this directly, because it won't be loaded unless requested, instead use the getImageInfo() method
	 *
	 * @var array
	 * 
	 */
	private $imageInfo = array(
		'width' => 0, 
		'height' => 0, 
		); 

	/**
	 * Last size error, if one occurred. 
	 * 
	 * @var string
	 *
	 */
	protected $error = '';

	/**
	 * Construct a new Pageimage
	 * 
	 * ~~~~~
	 * // Construct a new Pageimage, assumes that $page->images is a FieldtypeImage Field
	 * $pageimage = new Pageimage($page->images, '/path/to/file.png');
	 * ~~~~~
	 *
	 * @param Pageimages|Pagefiles $pagefiles 
	 * @param string $filename Full path and filename to this pagefile
	 * @throws WireException
	 *
	 */
	public function __construct(Pagefiles $pagefiles, $filename) {

		if(!$pagefiles instanceof Pageimages) throw new WireException("Pageimage::__construct requires instance of Pageimages"); 
		$this->pageimages = $pagefiles; 
		parent::__construct($pagefiles, $filename); 
	}

	/**
	 * When a Pageimage is cloned, we reset it's width and height to force them to reload in the clone
	 * 
	 * #pw-internal
	 *
	 */
	public function __clone() {
		$this->imageInfo['width'] = 0; 
		$this->imageInfo['height'] = 0;
		parent::__clone();
	}

	/**
	 * Return the web accessible URL to this image file
	 * 
	 * #pw-hooks
	 * 
	 * @return string
	 *
	 */
	public function url() {
		if($this->wire('hooks')->isHooked('Pagefile::url()') || $this->wire('hooks')->isHooked('Pageimage::url()')) { 
			return $this->__call('url', array()); 
		} else { 
			return $this->___url();
		}
	}

	/**
	 * Returns the full disk path to the image file
	 * 
	 * #pw-hooks
	 * 
	 * @return string
	 *
	 */
	public function filename() {
		if($this->wire('hooks')->isHooked('Pagefile::filename()') || $this->wire('hooks')->isHooked('Pageimage::filename()')) { 
			return $this->__call('filename', array()); 
		} else { 
			return $this->___filename();
		}
	}

	/**
	 * Returns array of suffixes for this file, or true/false if this file has the given suffix.
	 * 
	 * When providing a suffix, this method can be thought of: hasSuffix(suffix)
	 * 
	 * #pw-group-other
	 * 
	 * @param string $s Optionally provide suffix to return true/false if file has the suffix
	 * @return array|bool Returns array of suffixes, or true|false if given a suffix in the arguments.
	 * 
	 */
	public function suffix($s = '') {
		$info = $this->isVariation(parent::get('basename')); 
		if(strlen($s)) {
			return $info ? in_array($s, $info['suffix']) : false;
		} else {
			return $info ? $info['suffix'] : array();
		}
	}

	/**
	 * Get a property from this Pageimage
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		switch($key) {
			case 'width':
			case 'height':
				$value = $this->$key();
				break;
			case 'hidpiWidth':
			case 'retinaWidth':
				$value = $this->hidpiWidth();
				break;
			case 'hidpiHeight':
			case 'retinaHeight':
				$value = $this->hidpiHeight();
				break;
			case 'original':
				$value = $this->getOriginal();
				break;
			case 'error':
				$value = $this->error;
				break;
			default: 
				$value = parent::get($key); 
		}
		return $value; 
	}

	/**
	 * Gets the image information with PHP’s getimagesize function and caches the result
	 * 
	 * #pw-internal
	 * 
	 * @param bool $reset
	 * @return array
	 * 
	 */
	public function getImageInfo($reset = false) {

		if($reset) $checkImage = true; 
			else if($this->imageInfo['width']) $checkImage = false; 
			else $checkImage = true; 

		if($checkImage) { 
			if($this->ext == 'svg') {
				$xml = @file_get_contents($this->filename);
				if($xml) {
					$a = @simplexml_load_string($xml)->attributes();
					$this->imageInfo['width'] = (int) $a->width > 0 ? (int) $a->width : '100%';
					$this->imageInfo['height'] = (int) $a->height > 0 ? (int) $a->height : '100%';
				}
			} else {
				$info = @getimagesize($this->filename);
				if($info) {
					$this->imageInfo['width'] = $info[0];
					$this->imageInfo['height'] = $info[1];
				}
			}
		}

		return $this->imageInfo; 
	}
	
	/**
	 * Return an image (Pageimage) sized/cropped to the specified dimensions. 
	 * 
	 * `$thumb = $image->size($width, $height, $options);`
	 * 
	 * The default behavior of this method is to simply create and return a new resized version of the image,
	 * leaving the original in tact. Width and height of the target size are the the first two arguments. 
	 * The third argument called `$options` enables you to modify the default behavior of the size() method 
	 * in various ways. This method only creates the newly sized image once, and then it caches it. Future 
	 * calls simply refer back to the previously resized image. 
	 * 
	 * ~~~~~
	 * // Get an image to resize
	 * $image = $page->images->first();
	 * 
	 * // Create 400x300 thumbnail cropped to center
	 * $thumb = $image->size(400, 300); 
	 * 
	 * // Create thumbnail with cropping to top
	 * $thumb = $image->size(400, 300, 'north'); 
	 * 
	 * // Create thumbnail while specifying $options
	 * $thumb = $image->size(400, 300, [
	 *   'cropping' => 'north',
	 *   'quality' => 60, 
	 *   'upscaling' => false,
	 *   'sharpening' => 'medium'
	 * ]); 
	 * 
	 * // Output thumbnail
	 * echo "<img src='$thumb->url' />";
	 * ~~~~~
	 * 
	 * **About the $options argument**
	 * 
	 * - If given a *string*, it is assumed to be the value for the "cropping" option. 
	 * - If given an *integer*, it is assumed you are specifying a "quality" value (1-100). 
	 * - If given a *boolean*, it is assumed you are specifying whether or not you want to allow "upscaling".
	 * - If given an *array*, you may specify any of the options below together: 
	 * 
	 * **All available $options**
	 * 
	 *  - `quality` (int): Quality setting 1-100 (default=90, or as specified in /site/config.php).
	 *  - `upscaling` (bool): Allow image to be upscaled? (default=true).
	 *  - `cropping` (string|bool): Cropping mode, see possible values in "cropping" section below (default=center).
	 *  - `suffix` (string|array): Suffix word to identify the new image, or use array of words for multiple (default=none).
	 *  - `forceNew` (bool): Force re-creation of the image even if it already exists? (default=false).
	 *  - `sharpening` (string): Sharpening mode: "none", "soft", "medium", or "strong" (default=soft).
	 *  - `autoRotation` (bool): Automatically correct rotation of images that provide this info? (default=true)
	 *  - `rotate` (int): Rotate the image this many degrees, specify one of: 0, -270, -180, -90, 90, 180, or 270 (default=0).
	 *  - `flip` (string): To flip, specify either "vertical" or "horizontal" (default=none).
	 *  - `hidpi` (bool): Use HiDPI/retina pixel doubling? (default=false).
	 *  - `hidpiQuality` (bool): Quality setting for HiDPI (default=40, typically lower than regular quality setting). 
	 *  - `cleanFilename` (bool): Clean filename of historical resize information for shorter filenames? (default=false).
	 * 
	 * **Possible values for "cropping" option**  
	 * 
	 *  - `center` (string): to crop to center of image, default behavior.
	 *  - `x111y222` (string): to crop by pixels, 111px from left and 222px from top (replacing 111 and 222 with your values).
	 *  - `north` (string): Crop North (top), may also be just "n".
	 *  - `northwest` (string): Crop from Northwest (top left), may also be just "nw".
	 *  - `northeast` (string): Crop from Northeast (top right), may also be just "ne".
	 *  - `south` (string): Crop South (bottom), may also be just "s".
	 *  - `southwest` (string): Crop Southwest (bottom left), may also be just "sw".
	 *  - `southeast` (string): Crop Southeast (bottom right), may also be just "se".
	 *  - `west` (string): Crop West (left), may also be just "w".
	 *  - `east` (string): Crop East (right), may alos be just "e".
	 *  - `blank` (string): Specify a blank string to disallow cropping during resize.
	 *
	 * **Note about "quality" and "upscaling" options** 
	 * 
	 * ProcessWire doesn't keep separate copies of images with different "quality" or "upscaling" values. 
	 * If you change these and a variation image at the existing dimensions already exists, then you'll still get the old version. 
	 * To clear out an old version of an image, use the `Pageimage::removeVariations()` method in this class before calling 
	 * size() with new quality or upscaling settings.
	 * 
	 * #pw-group-resize-and-crop
	 * #pw-group-common
	 * #pw-hooks
	 *
	 * @param int $width Target width of new image
	 * @param int $height Target height of new image
	 * @param array|string|int $options Array of options to override default behavior: 
	 *  - Specify `array` of options as indicated in the section above. 
	 *  - Or you may specify type `string` containing "cropping" value.
	 *  - Or you may specify type `int` containing "quality" value.
	 *  - Or you may specify type `bool` containing "upscaling" value.
	 * @return Pageimage Returns a new Pageimage object that is a variation of the original. 
	 *  If the specified dimensions/options are the same as the original, then the original then the original will be returned.
	 *
	 */
	public function size($width, $height, $options = array()) {

		if($this->wire('hooks')->isHooked('Pageimage::size()')) {
			return $this->__call('size', array($width, $height, $options)); 
		} else { 
			return $this->___size($width, $height, $options);
		}
	}

	/**
	 * Hookable version of size() with implementation
	 *	
	 * See comments for size() method above.
	 * 
	 * #pw-internal
	 * 
	 * @param int $width
	 * @param int $height
	 * @param array|string|int $options
	 * @return Pageimage
	 *
	 */
	protected function ___size($width, $height, $options) {
	
		// I was getting unnecessarily resized images without this code below,
		// but this may be better solved in ImageSizer?
		/*
		$w = $this->width();
		$h = $this->height();
		if($w == $width && $h == $height) return $this; 
		if(!$height && $w == $width) return $this; 
		if(!$width && $h == $height) return $this; 
		*/
		
		if($this->ext == 'svg') return $this; 

		if(!is_array($options)) { 
			if(is_string($options)) {
				// optionally allow a string to be specified with crop direction, for shorter syntax
				if(strpos($options, ',') !== false) $options = explode(',', $options); // 30,40
				$options = array('cropping' => $options); 
			} else if(is_int($options)) {
				// optionally allow an integer to be specified with quality, for shorter syntax
				$options = array('quality' => $options);
			} else if(is_bool($options)) {
				// optionally allow a boolean to be specified with upscaling toggle on/off
				$options = array('upscaling' => $options); 
			} else { 
				// unknown options type
				$options = array();
			}
		}
		
		// originally requested options
		$requestOptions = $options;
	
		// default options
		$defaultOptions = array(
			'upscaling' => true,
			'cropping' => true,
			'quality' => 90,
			'hidpiQuality' => 40, 
			'suffix' => array(), // can be array of suffixes or string of 1 suffix
			'forceNew' => false,  // force it to create new image even if already exists
			'hidpi' => false, 
			'cleanFilename' => false, // clean filename of historial resize information
			'rotate' => 0,
			'flip' => '', 
			);

		$this->error = '';
		$debug = $this->wire('config')->debug;
		$configOptions = $this->wire('config')->imageSizerOptions; 
		if(!is_array($configOptions)) $configOptions = array();
		$options = array_merge($defaultOptions, $configOptions, $options); 

		$width = (int) $width;
		$height = (int) $height;

		if(is_string($options['cropping'])
			&& strpos($options['cropping'], 'x') === 0
			&& preg_match('/^x(\d+)[yx](\d+)/', $options['cropping'], $matches)) {
			$options['cropping'] = true; 
			$options['cropExtra'] = array((int) $matches[1], (int) $matches[2], $width, $height); 
			$crop = '';
		} else {
			$crop = ImageSizer::croppingValueStr($options['cropping']);
		}
	
		if(!is_array($options['suffix'])) {
			// convert to array
			$options['suffix'] = empty($options['suffix']) ? array() : explode(' ', $options['suffix']); 
		}

		if($options['rotate'] && !in_array(abs((int) $options['rotate']), array(90, 180, 270))) $options['rotate'] = 0;
		if($options['rotate']) $options['suffix'][] = ($options['rotate'] > 0 ? "rot" : "tor") . abs($options['rotate']); 
		if($options['flip']) $options['suffix'][] = strtolower(substr($options['flip'], 0, 1)) == 'v' ? 'flipv' : 'fliph';
		
		$suffixStr = '';
		if(!empty($options['suffix'])) {
			$suffix = $options['suffix'];
			sort($suffix); 
			foreach($suffix as $key => $s) {
				$s = strtolower($this->wire('sanitizer')->fieldName($s)); 
				if(empty($s)) unset($suffix[$key]); 
					else $suffix[$key] = $s; 
			}
			if(count($suffix)) $suffixStr = '-' . implode('-', $suffix); 
		}
		
		if($options['hidpi']) {
			$suffixStr .= '-hidpi';
			if($options['hidpiQuality']) $options['quality'] = $options['hidpiQuality'];
		}

		//$basename = $this->pagefiles->cleanBasename($this->basename(), false, false, false);
		// cleanBasename($basename, $originalize = false, $allowDots = true, $translate = false) 
		$originalName = $this->basename();
		$basename = basename($originalName, "." . $this->ext());        // i.e. myfile
		$originalSize = $debug ? @filesize($this->filename) : 0;
		if($options['cleanFilename'] && strpos($basename, '.') !== false) {
			$basename = substr($basename, 0, strpos($basename, '.')); 
		}
		$basename .= '.' . $width . 'x' . $height . $crop . $suffixStr . "." . $this->ext();	// i.e. myfile.100x100.jpg or myfile.100x100nw-suffix1-suffix2.jpg
		$filenameFinal = $this->pagefiles->path() . $basename;
		$filenameUnvalidated = '';
		$exists = file_exists($filenameFinal);

		if(!$exists || $options['forceNew']) {
			$filenameUnvalidated = $this->pagefiles->page->filesManager()->getTempPath() . $basename;
			if($exists && $options['forceNew']) @unlink($filenameFinal);
			if(file_exists($filenameUnvalidated)) @unlink($filenameUnvalidated);
			if(@copy($this->filename(), $filenameUnvalidated)) {
				try { 
					
					$timer = $debug ? Debug::timer() : null;
					
					/** @var ImageSizer $sizer */
					$sizer = $this->wire(new ImageSizer($filenameUnvalidated, $options));
					
					/** @var ImageSizerEngine $engine */
					$engine = $sizer->getEngine();
					
					// allow for ImageSizerEngine module settings for quality and sharpening to override system defaults
					// when they are not specified as an option to this resize() method
					$engineConfigData = $engine->getConfigData();
					if(!empty($engineConfigData)) {
						if(!empty($engineConfigData['quality']) && empty($options['hidpi']) && empty($requestOptions['quality'])) {
							$engine->setQuality($engineConfigData['quality']);
							$options['quality'] = $engineConfigData['quality'];
						}
						if(!empty($engineConfigData['sharpening']) && empty($requestOptions['sharpening'])) {
							$engine->setSharpening($engineConfigData['sharpening']);
							$options['sharpening'] = $engineConfigData['sharpening'];
						}
					}
					
					if($sizer->resize($width, $height) && @rename($filenameUnvalidated, $filenameFinal)) {
						$this->wire('files')->chmod($filenameFinal);
					} else {
						$this->error = "ImageSizer::resize($width, $height) failed for $filenameUnvalidated";
					}

					$timer = $debug ? Debug::timer($timer) : null;
					if($debug) $this->wire('log')->save('image-sizer',
						str_replace('ImageSizerEngine', '', $sizer->getEngine()) . ' ' . 
						($this->error ? "FAILED Resize: " : "Resized: ") . 
						"$originalName => " . 
						basename($filenameFinal) . " " .
						"({$width}x{$height}) $timer secs " . 
						"$originalSize => " . filesize($filenameFinal) . " bytes " . 
						"(quality=$options[quality], sharpening=$options[sharpening]) "
					);
					
				} catch(\Exception $e) {
					$this->trackException($e, false); 
					$this->error = $e->getMessage(); 
				}
			} else {
				$this->error = "Unable to copy $this->filename => $filenameUnvalidated"; 
			}
		}

		$pageimage = clone $this; 

		// if desired, user can check for property of $pageimage->error to see if an error occurred. 
		// if an error occurred, that error property will be populated with details
		if($this->error) { 
			// error condition: unlink copied file 
			if(is_file($filenameFinal)) @unlink($filenameFinal);
			if($filenameUnvalidated && is_file($filenameUnvalidated)) @unlink($filenameUnvalidated);

			// write an invalid image so it's clear something failed
			// todo: maybe return a 1-pixel blank image instead?
			$data = "This is intentionally invalid image data.\n";
			if(file_put_contents($filenameFinal, $data) !== false) $this->wire('files')->chmod($filenameFinal);

			// we also tell PW about it for logging and/or admin purposes
			$this->error($this->error); 
			if($debug) $this->wire('log')->save('image-sizer', "$filenameFinal - $this->error");
		}

		$pageimage->setFilename($filenameFinal); 	
		$pageimage->setOriginal($this); 

		return $pageimage; 
	}
	
	/**
	 * Same as size() but with width/height assumed to be hidpi width/height
	 * 
	 * #pw-internal
	 * 
	 * @param $width
	 * @param $height
	 * @param array $options See options in size() method. 
	 * @return Pageimage
	 *
	 */
	public function hidpiSize($width, $height, $options = array()) {
		$options['hidpi'] = true; 
		return $this->size($width, $height, $options); 
	}

	/**
	 * Create a crop and return it as a new Pageimage.
	 * 
	 * ~~~~~
	 * // Create a crop starting 100 pixels from left, 200 pixels from top
	 * // at 150 pixels wide and 100 pixels tall
	 * $image = $page->images->first();
	 * $crop = $image->crop(100, 200, 150, 100); 
	 * 
	 * // Output the crop
	 * echo "<img src='$crop->url' />";
	 * ~~~~~
	 * 
	 * #pw-group-resize-and-crop
	 * 
	 * @param int $x Starting X position (left) in pixels
	 * @param int $y Starting Y position (top) in pixels
	 * @param int $width Width of crop in pixels
	 * @param int $height Height of crop in pixels
	 * @param array $options See options array for `Pageimage::size()` method. 
	 *   Avoid setting crop properties in $options since we are overriding them.
	 * @return Pageimage
	 *
	 */
	public function ___crop($x, $y, $width, $height, $options = array()) {
		
		$x = (int) $x;
		$y = (int) $y;
		$width = (int) $width;
		$height = (int) $height;
		
		if(empty($options['suffix'])) {
			$options['suffix'] = array();
		} else if(!is_array($options['suffix'])) {
			$options['suffix'] = array($options['suffix']); 
		}
		
		$options['suffix'][] = "cropx{$x}y{$y}"; 
		$options['cropExtra'] = array($x, $y, $width, $height);
		$options['cleanFilename'] = true; 
		
		return $this->size($width, $height, $options);
	}

	/**
	 * Return the width of this image OR return an image sized with a given width (and proportional height).
	 *
	 * - If given a width, it'll return a new Pageimage object sized to that width. 
	 * - If not given a width, it'll return the current width of this Pageimage.
	 * 
	 * #pw-group-resize-and-crop
	 * #pw-group-common
	 * 
	 * ~~~~~
	 * // Get width of image
	 * $px = $image->width();
	 * 
	 * // Create a new variation at 200px width
	 * $thumb = $image->width(200); 
	 * ~~~~~
	 *
	 * @param int $n Optional width if you are creating a new size. 
	 * @param array|string|int|bool $options See `Pageimage::size()` $options argument for details. 
	 * @return int|Pageimage Returns width (in px) when given no arguments, or Pageimage when given a width argument.
	 *
	 */
	public function width($n = 0, $options = array()) {
		if($n) return $this->size($n, 0, $options); 	
		$info = $this->getImageInfo();
		return $info['width']; 
	}

	/**
	 * Return the height of this image OR return an image sized with a given height (and proportional width).
	 *
	 * - If given a height, it'll return a new Pageimage object sized to that height. 
	 * - If not given a height, it'll return the height of this Pageimage.
	 * 
	 * #pw-group-resize-and-crop
	 * #pw-group-common
	 * 
	 * ~~~~~
	 * // Get height of image
	 * $px = $image->height();
	 *
	 * // Create a new variation at 200px height
	 * $thumb = $image->height(200);
	 * ~~~~~
	 *
	 * @param int $n Optional height if you are creating a new size.
	 * @param array|string|int|bool $options See `Pageimage::size()` $options argument for details. 
	 * @return int|Pageimage Returns height (in px) when given no arguments, or Pageimage when given a height argument.
	 *
	 */
	public function height($n = 0, $options = array()) {
		if($n) return $this->size(0, $n, $options); 	
		$info = $this->getImageInfo();
		return $info['height']; 
	}

	/**
	 * Return width for hidpi/retina use, or resize an image for desired hidpi width
	 * 
	 * If the $width argument is omitted or provided as a float, hidpi width (int) is returned (default scale=0.5)
	 * If $width is provided (int) then a new Pageimage is returned at that width x 2 (for hidpi use).
	 * 
	 * #pw-internal
	 * 
	 * @param int|float $width Specify int to return resized image for hidpi, or float (or omit) to return current width at hidpi.
	 * @param array $options Optional options for use when resizing, see size() method for details.
	 * 	Or you may specify an int as if you want to return a hidpi width and want to calculate with that width rather than current image width.
	 * @return int|Pageimage|string
	 * 
	 */	
	public function hidpiWidth($width = 0, $options = array()) {
		
		if(is_string($width)) {
			if(ctype_digit("$width")) {
				$width = (int) $width;
			} else if($width === "100%") {
				return $this;
			} else if(ctype_digit(str_replace(".", "", $width))) {
				$width = (float) $width;
			}
		}
		
		if(is_float($width) || $width < 1) {
			// return hidpi width intended: scale omitted or provided in $width argument
			$scale = $width;
			if(!$scale || $scale < 0 || $scale > 1) $scale = 0.5;
			if(is_string($options) && $options === "100%") return $options;
			$width = is_array($options) ? 0 : (int) $options;
			if($width < 1) $width = $this->width();
			if($width === "100%") return $width;
			return ceil($width * $scale); 
		} else if($width && is_int($width) && $width > 0) {
			// resize intended
			if(!is_array($options)) $options = array();
			return $this->hidpiSize((int) $width, 0, $options);
		}
		
		return 0; // not possible to reach, but pleases the inspection
	}

	/**
	 * Return height for hidpi/retina use, or resize an image for desired hidpi height
	 *
	 * If the $height argument is omitted or provided as a float, hidpi height (int) is returned (default scale=0.5)
	 * If $height is provided (int) then a new Pageimage is returned at that height x 2 (for hidpi use).
	 * 
	 * #pw-internal
	 *
	 * @param int|float $height Specify int to return resized image for hidpi, or float (or omit) to return current width at hidpi.
	 * @param array|int $options Optional options for use when resizing, see size() method for details.
	 * 	Or you may specify an int as if you want to return a hidpi height and want to calculate with that height rather than current image height.
	 * @return int|Pageimage
	 *
	 */	
	public function hidpiHeight($height = 0, $options = array()) {
		if(is_float($height) || $height < 1) {
			// return hidpi height intended: scale omitted or provided in $height argument
			$scale = $height;
			if(!$scale || $scale < 0 || $scale > 1) $scale = 0.5;
			$height = is_array($options) ? 0 : (int) $options;
			if($height < 1) $height = $this->height();
			return ceil($height * $scale);
		} else if($height) {
			// resize intended
			if(!is_array($options)) $options = array();
			return $this->hidpiSize(0, (int) $height, $options);
		}
		return 0; // not possible to reach but pleases the inspection
	}

	/**
	 * Return an image no larger than the given width.
	 *
	 * If source image is equal to or smaller than the requested dimension, 
	 * then it will remain that way and the source image is returned (not a copy).
	 * 
	 * If the source image is larger than the requested dimension, then a new copy
	 * will be returned at the requested dimension.
	 * 
	 * #pw-group-resize-and-crop
	 *
 	 * @param int $n Maximum width
	 * @param array $options See `Pageimage::size()` method for options
	 * @return Pageimage
	 *
	 */
	public function maxWidth($n, array $options = array()) {
		$options['upscaling'] = false;
		if($this->width() > $n) return $this->width($n, $options); 
		return $this;
	}

	/**
	 * Return an image no larger than the given height.
	 *
	 * If source image is equal to or smaller than the requested dimension, 
	 * then it will remain that way and the source image is returned (not a copy).
	 * 
	 * If the source image is larger than the requested dimension, then a new copy
	 * will be returned at the requested dimension.
	 * 
	 * #pw-group-resize-and-crop
	 *
 	 * @param int $n Maximum height
	 * @param array $options See `Pageimage::size()` method for options
	 * @return Pageimage
	 *
	 */
	public function maxHeight($n, array $options = array()) {
		$options['upscaling'] = false;
		if($this->height() > $n) return $this->height($n, $options); 
		return $this;
	}

	/**
	 * Return an image no larger than the given width and height
	 * 
	 * #pw-group-resize-and-crop
	 * 
	 * @param int $width Max allowed width
	 * @param int $height Max allowed height
	 * @param array $options See `Pageimage::size()` method for options
	 * @return Pageimage
	 * 
	 */
	public function maxSize($width, $height, $options = array()) {
		$w = $this->width();
		$h = $this->height();
		if($w >= $h) {
			return $this->maxWidth($width, $options);
		} else {
			return $this->maxHeight($height, $options);
		}
	}

	/**
	 * Get all size variations of this image
	 *
	 * This is useful after a delete of an image (for example). This method can be used to track down all the 
	 * child files that also need to be deleted. 
	 * 
	 * #pw-group-variations
	 *
	 * @param array $options Optional, one or more options in an associative array of the following: 
	 * 	- `info` (bool): when true, method returns variation info arrays rather than Pageimage objects
	 * 	- `width` (int): only variations with given width will be returned
	 * 	- `height` (int): only variations with given height will be returned
	 * 	- `width>=` (int): only variations with width greater than or equal to given will be returned
	 * 	- `height>=` (int): only variations with height greater than or equal to given will be returned
	 * 	- `width<=` (int): only variations with width less than or equal to given will be returned
	 * 	- `height<=` (int): only variations with height less than or equal to given will be returned
	 * 	- `suffix` (string): only variations having the given suffix will be returned
	 * @return Pageimages|array Returns Pageimages array of Pageimage instances. 
	 *  Only returns regular array if provided `$options['info']` is true.
	 *
	 */
	public function getVariations(array $options = array()) {

		if(!is_null($this->variations)) return $this->variations; 

		$variations = $this->wire(new Pageimages($this->pagefiles->page)); 
		$dir = new \DirectoryIterator($this->pagefiles->path); 
		$infos = array();

		foreach($dir as $file) {
			if($file->isDir() || $file->isDot()) continue; 			
			$info = $this->isVariation($file->getFilename());
			if(!$info) continue; 
			$allow = true;
			if(count($options)) foreach($options as $option => $value) {
				switch($option) {
					case 'width': $allow = $info['width'] == $value; break;
					case 'width>=': $allow = $info['width'] >= $value; break;
					case 'width<=': $allow = $info['width'] <= $value; break;
					case 'height': $allow = $info['height'] == $value; break;
					case 'height>=': $allow = $info['height'] >= $value; break;
					case 'height<=': $allow = $info['height'] <= $value; break;
					case 'suffix': $allow = in_array($value, $info['suffix']); break;
				}
			}
			if(!$allow) continue; 
			if(!empty($options['info'])) {
				$infos[$file->getBasename()] = $info;
			} else {
				$pageimage = clone $this; 
				$pathname = $file->getPathname();
				if(DIRECTORY_SEPARATOR != '/') $pathname = str_replace(DIRECTORY_SEPARATOR, '/', $pathname);
				$pageimage->setFilename($pathname); 
				$pageimage->setOriginal($this); 
				$variations->add($pageimage); 
			}
		}

		if(!empty($options['info'])) {
			return $infos;
		} else {
			$this->variations = $variations;
			return $variations; 
		}
	}

	/**
	 * Rebuilds variations of this image
	 * 
	 * By default, this excludes crops and images with suffixes, but can be overridden with the `$mode` and `$suffix` arguments. 
	 * 
	 * **Options for $mode argument**
	 * 
	 * - `0` (int): Rebuild only non-suffix, non-crop variations, and those w/suffix specified in $suffix argument. ($suffix is INCLUSION list)
	 * - `1` (int): Rebuild all non-suffix variations, and those w/suffix specifed in $suffix argument. ($suffix is INCLUSION list)
	 * - `2` (int): Rebuild all variations, except those with suffix specified in $suffix argument. ($suffix is EXCLUSION list)
	 * - `3` (int): Rebuild only variations specified in the $suffix argument. ($suffix is ONLY-INCLUSION list)
	 * 
	 * Mode 0 is the only truly safe mode, as in any other mode there are possibilities that the resulting
	 * rebuild of the variation may not be exactly what was intended. The issues with other modes primarily
	 * arise when the suffix means something about the technical details of the produced image, or when 
	 * rebuilding variations that include crops from an original image that has since changed dimensions or crops. 
	 *
	 * #pw-group-variations
	 * 
	 * @param int $mode See the options for $mode argument above (default=0).
	 * @param array $suffix Optional argument to specify suffixes to include or exclude (according to $mode). 
	 * @param array $options See $options for `Pageimage::size()` for details. 
	 * @return array Returns an associative array with with the following indexes: 
	 *  - `rebuilt` (array): Names of files that were rebuilt.
	 *  - `skipped` (array): Names of files that were skipped. 
	 *  - `errors` (array): Names of files that had errors. 
	 *  - `reasons` (array): Reasons why files were skipped or had errors, associative array indexed by file name. 
	 * 
	 */
	public function ___rebuildVariations($mode = 0, array $suffix = array(), array $options = array()) {
		
		$skipped = array();
		$rebuilt = array();
		$errors = array();
		$reasons = array();
		$options['forceNew'] = true; 
		
		foreach($this->getVariations(array('info' => true)) as $info) {
			
			$o = $options;
			unset($o['cropping']); 
			$skip = false; 
			$name = $info['name'];
			
			if($info['crop'] && !$mode) {
				// skip crops when mode is 0
				$reasons[$name] = "$name: Crop is $info[crop] and mode is 0";
				$skip = true; 
				
			} else if(count($info['suffix'])) {
				// check suffixes 
				foreach($info['suffix'] as $k => $s) {
					if($s === 'hidpi') {
						// allow hidpi to passthru
						$o['hidpi'] = true;
					} else if($s == 'is') {
						// this is a known core suffix that we allow
					} else if(strpos($s, 'cropx') === 0) {
						// skip cropx suffix (already known from $info[crop])
						unset($info['suffix'][$k]);
						continue; 
					} else if(strpos($s, 'pid') === 0 && preg_match('/^pid\d+$/', $s)) {
						// allow pid123 to pass through 
					} else if(in_array($s, $suffix)) {
						// suffix is one provided in $suffix argument
						if($mode == 2) {
							// mode 2 where $suffix is an exclusion list
							$skip = true;
							$reasons[$name] = "$name: Suffix '$s' is one provided in exclusion list (mode==true)";
						} else {
							// allowed suffix
						}
					} else {
						// image has suffix not specified in $suffix argument
						if($mode == 0 || $mode == 1 || $mode == 3) {
							$skip = true;
							$reasons[$name] = "$name: Image has suffix '$s' not provided in allowed list: " . implode(', ', $suffix);
						}
					}
				}
			}
			
			if($skip) {
				$skipped[] = $name; 
				continue; 
			}
		
			// rebuild the variation
			$o['forceNew'] = true; 
			$o['suffix'] = $info['suffix'];
			if(is_file($info['path'])) unlink($info['path']); 
			
			if($info['crop'] && preg_match('/^x(\d+)y(\d+)$/', $info['crop'], $matches)) {
				$cropX = (int) $matches[1];
				$cropY = (int) $matches[2];
				$variation = $this->crop($cropX, $cropY, $info['width'], $info['height'], $options); 
			} else {
				if($info['crop']) $options['cropping'] = $info['crop'];
				$variation = $this->size($info['width'], $info['height'], $options);
			}
			
			if($variation) {
				if($variation->name != $name) rename($variation->filename(), $info['path']); 
				$rebuilt[] = $name;
			} else {
				$errors[] = $name;
			}
		}
		
		return array(
			'rebuilt' => $rebuilt, 
			'skipped' => $skipped, 
			'reasons' => $reasons, 
			'errors' => $errors
		); 
	}

	/**
	 * Given a file name (basename), return array of info if this is a variation for this instance’s file, or false if not.
	 *
	 * Returned array includes the following indexes: 
	 * 
	 * - `original` (string): Original basename
	 * - `url` (string): URL to image
	 * - `path` (string): Full path + filename to image
	 * - `width` (int): Specified width
	 * - `height` (int): Specified height
	 * - `crop` (string): Cropping info string or blank if none
	 * - `suffix` (array): Array of suffixes
	 * 
	 * The following are only present if variation is based on another variation, and thus has a parent variation
	 * image between it and the original: 
	 * 
	 * - `suffixAll` (array): Contains all suffixes including among parent variations 
	 * - `parent` (array): Variation info array of direct parent variation file
	 * 
	 * #pw-group-variations
	 * 
	 * @param string $basename Filename to check (basename, which excludes path)
	 * @param bool $allowSelf When true, it will return variation info even if same as current Pageimage.
	 * @return bool|array Returns false if not a variation, or array of info if it is.
	 *
	 */
	public function ___isVariation($basename, $allowSelf = false) {

		static $level = 0;
		$variationName = basename($basename);
		$originalName = $this->basename; 
	
		// that that everything from the beginning up to the first period is exactly the same
		// otherwise, they are different source files
		$test1 = substr($variationName, 0, strpos($variationName, '.'));
		$test2 = substr($originalName, 0, strpos($originalName, '.')); 
		if($test1 !== $test2) return false;

		// remove extension from originalName
		$originalName = basename($originalName, "." . $this->ext());  
		
		// if originalName is already a variation filename, remove the variation info from it.
		// reduce to original name, i.e. all info after (and including) a period
		if(strpos($originalName, '.') && preg_match('/^([^.]+)\.(?:\d+x\d+|-[_a-z0-9]+)/', $originalName, $matches)) {
			$originalName = $matches[1];
		}
	
		// if file is the same as the original, then it's not a variation
		if(!$allowSelf && $variationName == $this->basename) return false;
		
		// if file doesn't start with the original name then it's not a variation
		if(strpos($variationName, $originalName) !== 0) return false; 
	
		// get down to the meat and the base
		// meat is the part of the filename containing variation info like dimensions, crop, suffix, etc.
		// base is the part before that, which may include parent meat
		$pos = strrpos($variationName, '.'); // get extension
		$ext = substr($variationName, $pos); 
		$base = substr($variationName, 0, $pos); // get without extension
		$rpos = strrpos($base, '.'); // get last data chunk after dot
		if($rpos !== false) {
			$meat = substr($base, $rpos+1) . $ext; // the part of the filename we're interested in
			$base = substr($base, 0, $rpos); // the rest of the filename
			$parent = "$base." . $this->ext();
		} else {
			$meat = $variationName;
			$parent = null;
		}

		// identify parent and any parent suffixes
		$suffixAll = array();
		while(($pos = strrpos($base, '.')) !== false) {
			$part = substr($base, $pos+1); 
			// if(is_null($parent)) {
				// $parent = substr($base, 0, $pos) . $ext;
				//$parent = $originalName . "." . $part . $ext;
			// }
			$base = substr($base, 0, $pos); 
			while(($rpos = strrpos($part, '-')) !== false) {
				$suffixAll[] = substr($part, $rpos+1); 
				$part = substr($part, 0, $rpos); 
			}
		}

		// variation name with size dimensions and optionally suffix
		$re1 = '/^'  . 
			'(\d+)x(\d+)' .					// 50x50	
			'([pd]\d+x\d+|[a-z]{1,2})?' . 	// nw or p30x40 or d30x40
			'(?:-([-_a-z0-9]+))?' . 		// -suffix1 or -suffix1-suffix2, etc.
			'\.' . $this->ext() . 			// .jpg
			'$/';
	
		// variation name with suffix only
		$re2 = '/^' . 						
			'-([-_a-z0-9]+)' . 				// suffix1 or suffix1-suffix2, etc. 
			'(?:\.' . 						// optional extras for dimensions/crop, starts with period
				'(\d+)x(\d+)' .				// optional 50x50	
				'([pd]\d+x\d+|[a-z]{1,2})?' . // nw or p30x40 or d30x40
			')?' .
			'\.' . $this->ext() . 			// .jpg
			'$/'; 

		// if regex does not match, return false
		if(preg_match($re1, $meat, $matches)) {
			// this is a variation with dimensions, return array of info
			$info = array(
				'name' => $basename, 
				'url' => $this->pagefiles->url . $basename, 
				'path' => $this->pagefiles->path . $basename, 
				'original' => $originalName . '.' . $this->ext(),
				'width' => (int) $matches[1],
				'height' => (int) $matches[2],
				'crop' => (isset($matches[3]) ? $matches[3] : ''),
				'suffix' => (isset($matches[4]) ? explode('-', $matches[4]) : array()),
				);

		} else if(preg_match($re2, $meat, $matches)) {
		
			// this is a variation only with suffix
			$info = array(
				'name' => $basename, 
				'url' => $this->pagefiles->url . $basename,
				'path' => $this->pagefiles->path . $basename, 
				'original' => $originalName . '.' . $this->ext(),
				'width' => (isset($matches[2]) ? (int) $matches[2] : 0),
				'height' => (isset($matches[3]) ? (int) $matches[3] : 0),
				'crop' => (isset($matches[4]) ? $matches[4] : ''),
				'suffix' => explode('-', $matches[1]),
				);
			
		} else {
			return false; 
		}
		
		$info['hidpiWidth'] = $this->hidpiWidth(0, $info['width']);
		$info['hidpiHeight'] = $this->hidpiWidth(0, $info['height']); 
	
		if(empty($info['crop'])) {
			// attempt to extract crop info from suffix
			foreach($info['suffix'] as $key => $suffix) {
				if(strpos($suffix, 'cropx') === 0) {
					$info['crop'] = ltrim($suffix, 'crop'); // i.e. x123y456
				}
			}
		}

		if($parent) {
			// suffixAll includes all parent suffix in addition to current suffix
			if(!$level) $info['suffixAll'] = array_unique(array_merge($info['suffix'], $suffixAll)); 
			// parent property is set with more variation info, when available
			$level++;
			$info['parentName'] = $parent; 
			$info['parent'] = $this->isVariation($parent);
			$level--;
		}

		if(!$this->original) {
			$this->original = $this->pagefiles->get($info['original']);
		}
		
		return $info;
	}

	/**
	 * Delete all the alternate sizes associated with this Pageimage
	 * 
	 * #pw-group-variations
	 *
	 * @return $this
	 *
	 */
	public function removeVariations() {

		$variations = $this->getVariations();	

		foreach($variations as $variation) {
			if(is_file($variation->filename)) unlink($variation->filename); 			
		}

		$this->variations = null;
		return $this;	
	}

	/**
	 * Identify this Pageimage as a variation, by setting the Pageimage it was resized from.
	 * 
	 * #pw-group-variations
	 *
	 * @param Pageimage $image
	 * @return $this
	 *
	 */
	public function setOriginal(Pageimage $image) {
		$this->original = $image; 
		return $this; 
	}

	/**
	 * If this image is a variation, return the original, otherwise return null.
	 * 
	 * #pw-group-variations
	 *
	 * @return Pageimage|null
	 *
	 */
	public function getOriginal() {
		if($this->original) return $this->original; 
		$info = $this->isVariation($this->basename(), true); 
		if($info === false) return null;
		$this->original = $this->pagefiles->get($info['original']); 
		return $this->original;
	}

	/**
	 * Delete the physical file(s) associated with this Pagefile
	 * 
	 * #pw-internal Public API should use delete method from Pageimages
	 *
	 */
	public function unlink() {
		parent::unlink();
		$this->removeVariations();
		return $this; 
	}

	/**
	 * Copy this Pageimage and any of it's variations to another path
	 * 
	 * #pw-internal
	 *
	 * @param string $path
	 * @return bool True if successful
	 *
	 */
	public function copyToPath($path) {
		if(parent::copyToPath($path)) {
			foreach($this->getVariations() as $variation) {
				if(is_file($variation->filename)) {
					copy($variation->filename, $path . $variation->basename); 
					if($this->config->chmodFile) chmod($path . $variation->basename, octdec($this->config->chmodFile));
				}
			}
			return true; 
		}
		return false; 
	}

	/**
	 * Install this Pagefile
	 *
	 * Implies copying the file to the correct location (if not already there), and populating it's name
	 * 
	 * #pw-internal
	 *
	 * @param string $filename Full path and filename of file to install
	 * @throws WireException
	 *
	 */
	protected function ___install($filename) {
		parent::___install($filename); 
		if(!$this->width()) {
			parent::unlink();
			throw new WireException($this->_('Unable to install invalid image')); 
		}
	}

}

