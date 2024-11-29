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
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 * @property-read int $width Width of image, in pixels.
 * @property-read int $height Height of image, in pixels.
 * @property-read int $hidpiWidth HiDPI width of image, in pixels. #pw-internal
 * @property-read int $hidpiHeight HiDPI heigh of image, in pixels. #pw-internal
 * @property-read string $error Last image resizing error message, when applicable. #pw-group-resize-and-crop
 * @property-read Pageimage $original Reference to original $image, if this is a resized version. #pw-group-variations
 * @property-read array $focus Focus array contains 'top' (float), 'left' (float), 'zoom' (int), and 'default' (bool) properties.
 * @property-read string $focusStr Readable string containing focus information.
 * @property-read bool $hasFocus Does this image have custom focus settings? (i.e. $focus['default'] == true)
 * @property-read array $suffix Array containing file suffix(es).
 * @property-read string $suffixStr String of file suffix(es) separated by comma.
 * @property-read string $alt Convenient alias for the 'description' property, unless overridden (since 3.0.125).
 * @property-read string $src Convenient alias for the 'url' property, unless overridden (since 3.0.125).
 * @property-read PagefileExtra $webp Access webp version of image (since 3.0.132)
 * @property-read float $ratio Image ratio where 1.0 is square, >1 is wider than tall, >2 is twice as wide as well, <1 is taller than wide, etc. (since 3.0.154+)
 *
 * Properties inherited from Pagefile
 * ==================================
 * @property-read string $url URL to the file on the server.
 * @property-read string $httpUrl URL to the file on the server including scheme and hostname.
 * @property-read string $URL Same as $url property but with browser cache busting query string appended. #pw-group-other
 * @property-read string $HTTPURL Same as the cache-busting uppercase “URL” property, but includes scheme and hostname. #pw-group-other
 * @property-read string $filename Full disk path to the file on the server.
 * @property-read string $name Returns the filename without the path, same as the "basename" property.
 * @property-read string $hash Get a unique hash (for the page) representing this Pagefile.
 * @property-read array $tagsArray Get file tags as an array. #pw-group-tags @since 3.0.17
 * @property string $basename Returns the filename without the path.
 * @property string $description Value of the file’s description field (string), if enabled. Note you can also set this property directly.
 * @property string $tags Value of the file’s tags field (string), if enabled. #pw-group-tags
 * @property string $ext File’s extension (i.e. last 3 or so characters)
 * @property-read int $filesize File size (number of bytes).
 * @property int $modified Unix timestamp of when Pagefile (file, description or tags) was last modified. #pw-group-date-time
 * @property-read string $modifiedStr Readable date/time string of when Pagefile was last modified. #pw-group-date-time
 * @property-read int $mtime Unix timestamp of when file (only) was last modified. #pw-group-date-time
 * @property-read string $mtimeStr Readable date/time string when file (only) was last modified. #pw-group-date-time
 * @property int $created Unix timestamp of when file was created. #pw-group-date-time
 * @property-read string $createdStr Readable date/time string of when Pagefile was created #pw-group-date-time
 * @property string $filesizeStr File size as a formatted string, i.e. “123 Kb”.
 * @property Pagefiles $pagefiles The Pagefiles WireArray that contains this file. #pw-group-other
 * @property Page $page The Page object that this file is part of. #pw-group-other
 * @property Field $field The Field object that this file is part of. #pw-group-other
 * @property PageimageDebugInfo $debugInfo
 *
 * Hookable methods 
 * ================
 * @method bool|array isVariation($basename, $options = array())
 * @method Pageimage crop($x, $y, $width, $height, $options = array())
 * @method array rebuildVariations($mode = 0, array $suffix = array(), array $options = array())
 * @method install($filename)
 * @method render($markup = '', $options = array())
 * @method void createdVariation(Pageimage $image, array $data) Called after new image variation created (3.0.180+)
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
	 * Cached result of the variations() method
	 *
	 * Don't reference this directly, because it won't be loaded unless requested, instead use the variations() method
	 * 
	 * @var PageimageVariations
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
	 * @var PageimageDebugInfo|null
	 *
	 */
	private $pageimageDebugInfo = null; 

	/**
	 * Last size error, if one occurred. 
	 * 
	 * @var string
	 *
	 */
	protected $error = '';

	/**
	 * Last used Pageimage::size() $options argument
	 * 
	 * @var array
	 * 
	 */
	protected $sizeOptions = array();

	/**
	 * Construct a new Pageimage
	 * 
	 * ~~~~~
	 * // Construct a new Pageimage, assumes that $page->images is a FieldtypeImage Field
	 * $pageimage = new Pageimage($page->images, '/path/to/file.png');
	 * ~~~~~
	 *
	 * @param Pagefiles $pagefiles 
	 * @param string $filename Full path and filename to this pagefile
	 * @throws WireException
	 *
	 */
	public function __construct(Pagefiles $pagefiles, $filename) {

		if(!$pagefiles instanceof Pageimages) throw new WireException("Pageimage::__construct requires instance of Pageimages"); 
		$pagefiles->wire($this);
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
		$this->pageimageDebugInfo = null;
		$this->variations = null;
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
		$hooks = $this->wire()->hooks;
		if($hooks->isHooked('Pagefile::url()') || $hooks->isHooked('Pageimage::url()')) { 
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
		$hooks = $this->wire()->hooks;
		if($hooks->isHooked('Pagefile::filename()') || $hooks->isHooked('Pageimage::filename()')) { 
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
	 * Get or set focus area for crops to use
	 * 
	 * These settings are used by $this->size() calls that specify BOTH width AND height. Focus helps to 
	 * ensure that the important subject of the photo is not cropped out when the requested size proportion
	 * differs from the original image proportion. For example, not chopping off someone’s head in a photo.
	 * 
	 * Default behavior is to return an array containing "top" and "left" indexes, representing percentages
	 * from top and left. When arguments are specified, you are either setting the top/left percentages, or
	 * unsetting focus, or getting focus in different ways, described in arguments below. 
	 * 
	 * A zoom argument/property is also present here for future use, but not currently supported. 
	 * 
	 * #pw-group-other
	 * 
	 * @param null|float|int|array|false $top Omit to get focus array, or specify one of the following:
	 *   - GET: Omit all arguments to get focus array (default behavior). 
	 *   - GET: Specify boolean TRUE to return TRUE if focus data is present or FALSE if not. 
	 *   - GET: Specify integer 1 to make this method return pixel dimensions rather than percentages. 
	 *   - SET: Specify both $top and $left arguments to set (values assumed to be percentages).
	 *   - SET: Specify array containing "top" and "left" indexes to set (percentages). 
	 *   - SET: Specify array where index 0 is top and index 1 is left (percentages). 
	 *   - SET: Specify string in the format "top left", i.e. "25 70" or "top left zoom", i.e. "25 70 30" (percentages).
	 *   - SET: Specify CSV key=value string in the format "top=25%, left=70%, zoom=30%" in any order
	 *   - UNSET: Specify boolean false to remove any focus values. 
	 * @param null|float|int $left Set left value (when $top value is float|int) 
	 *   - This argument is only used when setting focus and should be omitted otherwise. 
	 * @param null|int $zoom Zoom percent (not currently supported)
	 * @return array|bool|Pageimage Returns one of the following: 
	 *   - When getting returns array containing top, left and default properties. 
	 *   - When TRUE was specified for the $top argument, it returns either TRUE (has focus) or FALSE (does not have). 
	 *   - When setting or unsetting returns $this.
	 * 
	 */
	public function focus($top = null, $left = null, $zoom = null) {
		
		if(is_string($top) && $left === null) { 
			if(strpos($top, '=')) {
				// SET string "top=25%, left=70%, zoom=0%"
				$a = array('top' => 50, 'left' => 50, 'zoom' => 0);
				$parts = explode(',', str_replace(array(' ', '%'), '', $top));
				foreach($parts as $part) {
					if(!strpos($part, '=')) continue;
					list($name, $pct) = explode('=', $part);
					$a[$name] = strpos($pct, '.') !== false ? (float) $pct : (int) $pct;
				}
				$top = $a; // for later setting by array
				unset($a);
			} else if(strpos($top, ' ')) {
				// SET string like "25 70 0" (representing "top left zoom")
				if(strpos($top, ' ') != strrpos($top, ' ')) {
					// with zoom
					list($top, $left, $zoom) = explode(' ', $top, 3);
				} else {
					// without zoom
					list($top, $left) = explode(' ', $top, 2);
					$zoom = 0;
				}
			}
		}
		
		if($top === null || $top === true || ($top === 1 && $left === null)) {
			// GET
			$focus = $this->filedata('focus');
			if(!is_array($focus) || empty($focus)) {
				// use default
				if($top === true) return false;
				$focus = array(
					'top' => 50, 
					'left' => 50,
					'zoom' => 0,
					'default' => true, 
					'str' => '50 50 0',
				);
			} else {
				// use custom
				if($top === true) return true;
				if(!isset($focus['zoom'])) $focus['zoom'] = 0;
				$focus['default'] = false;
				$focus['str'] = "$focus[top] $focus[left] $focus[zoom]";
			}
			if($top === 1) {
				// return pixel dimensions rather than percentages
				$centerX = ($focus['left'] / 100) * $this->width(); // i.e. (50 / 100) * 500 = 250;
				$centerY = ($focus['top'] / 100) * $this->height();
				$focus['left'] = $centerX;
				$focus['top'] = $centerY;
			}
			return $focus;
			
		} else if($top === false) {
			// UNSET
			$this->filedata(false, 'focus');
			
		} else if($left !== null) {
			// SET
			if(is_array($top)) {
				if(isset($top['left'])) {
					$left = $top['left'];
					$top = $top['top'];
					$zoom = isset($top['zoom']) ? $top['zoom'] : 0;
				} else {
					$top = $top[0];
					$left = $top[1];
					$zoom = isset($top[2]) ? $top[2] : 0;
				}
			}
			
			$top = (float) $top;
			$left = (float) $left;
			$zoom = (int) $zoom;
			
			if(((int) $top) == 50 && ((int) $left) == 50 && ($zoom < 2)) {
				// if matches defaults, then no reason to store in filedata
				$this->filedata(false, 'focus');
			} else {
				$this->filedata('focus', array(
					'top' => round($top, 1),
					'left' => round($left, 1),
					'zoom' => $zoom
				));
			}
		}
		
		return $this;
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return Pageimage|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'sizeOptions' && is_array($value)) {
			$this->sizeOptions = $value;
			return $this;
		} else {
			return parent::set($key, $value);
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
			case 'focus':
				$value = $this->focus();
				break;
			case 'focusStr':
				$focus = $this->focus();
				$value = "top=$focus[top]%,left=$focus[left]%,zoom=$focus[zoom]%" . ($focus['default'] ? " (default)" : "");
				break;
			case 'hasFocus': 
				$value = $this->focus(true);
				break;
			case 'suffix':
				$value = $this->suffix();
				break;
			case 'suffixStr':
				$value = implode(',', $this->suffix());
				break;
			case 'alt':
				$value = parent::get('alt');
				if($value === null) $value = $this->description();
				break;
			case 'src':
				$value = parent::get('src');
				if($value === null) $value = $this->url();
				break;
			case 'webp':	
				$value = $this->webp();
				break;
			case 'hasWebp':	
				$value = $this->webp()->exists();
				break;
			case 'webpUrl': 
				$value = $this->webp()->url();
				break;
			case 'webpFilename': 
				$value = $this->webp()->filename();
				break;
			case 'debugInfo':
				if(!$this->pageimageDebugInfo) $this->pageimageDebugInfo = new PageimageDebugInfo($this);
				$value = $this->pageimageDebugInfo;
				break;
			case 'sizeOptions':	
				$value = $this->sizeOptions;
				break;
			case 'ratio':
				$value = $this->ratio();
				break;
			default: 
				$value = parent::get($key); 
		}
		return $value; 
	}

	/**
	 * Set image info (internal use)
	 * 
	 * #pw-internal
	 * 
	 * @param array $info
	 * 
	 */
	public function setImageInfo(array $info) {
		// width and height less than 0 indicate percentage rather than pixels
		if(isset($info['width']) && $info['width'] < 0) $info['width'] = abs($info['width']) . '%';
		if(isset($info['height']) && $info['height'] < 0) $info['height'] = abs($info['height']) . '%';
		$this->imageInfo = array_merge($this->imageInfo, $info);
	}
	
	/**
	 * Gets the image information with PHP’s getimagesize function and caches the result
	 * 
	 * #pw-internal
	 * 
	 * @param bool|string $reset Specify true to retrieve info fresh, or filename to check and return info for. 
	 *   When specifying a filename, the info is only returned (not populated with this object). 
	 * @return array
	 * 
	 */
	public function getImageInfo($reset = false) {

		$imageInfo = $this->imageInfo;
		$filename = is_string($reset) && file_exists($reset) ? $reset : ''; 
		$ext = $this->ext;
	
		if(!$reset && $imageInfo['width'] && !$filename) {
			return $imageInfo;
		}

		if($ext == 'svg') {
			$imageInfo = array_merge($imageInfo, $this->getImageInfoSVG($filename));
		} else {
			if($filename) {
				$info = @getimagesize($filename);
			} else {
				$info = @getimagesize($this->filename);
			}
			if((!$info || empty($info[0])) && !empty($this->sizeOptions['_width'])) {
				// on fail, fallback to size options that were requested for the image (if available)
				$imageInfo['width'] = $this->sizeOptions['_width'];
				$imageInfo['height'] = $this->sizeOptions['_height'];
			} else if($info) {
				$imageInfo['width'] = $info[0];
				$imageInfo['height'] = $info[1];
				if(function_exists('exif_read_data') && ($ext === 'jpg' || $ext === 'jpeg')) {
					$exif = $filename ? @exif_read_data($filename) : @exif_read_data($this->filename);
					if(!empty($exif['Orientation']) && (int) $exif['Orientation'] > 4) {
						// Image has portrait orientation so reverse width and height info
						$imageInfo['width'] = $info[1];
						$imageInfo['height'] = $info[0];
					}
				}
			}
		}
		
		if(!$filename) $this->imageInfo = $imageInfo;

		return $imageInfo; 
	}

	/**
	 * Gets the image info/size of an SVG
	 *
	 * Returned width and height values may be integers OR percentage strings.
	 *
	 * #pw-internal
	 *
	 * @param string $filename Optional filename to check
	 * @return array of width and height
	 *
	 */
	protected function getImageInfoSVG($filename = '') {
		$width = 0;
		$height = 0;
		if(!$filename) $filename = $this->filename;
		$xml = @file_get_contents($filename);
		
		if($xml && false !== ($a = @simplexml_load_string($xml))) {
			$a = $a->attributes();
			if((int) $a->width > 0) $width = (int) $a->width;
			if((int) $a->height > 0) $height = (int) $a->height;
			if((!$width || !$height) && $a->viewBox) {
				$values = explode(' ', $a->viewBox);
				if(count($values) === 4) {
					$width = (int) round($values[2]);
					$height = (int) round($values[3]);
				}
			}
		}
		
		if((!$width || !$height) && (extension_loaded('imagick') || class_exists('\IMagick'))) {
			try {
				$imagick = new \Imagick();
				$imagick->readImage($filename);
				$width = $imagick->getImageWidth();
				$height = $imagick->getImageHeight();
			} catch(\Exception $e) {
				// fallback to 100%
			}
		}
		
		if($width < 1) $width = '100%';
		if($height < 1) $height = '100%';
		
		return array(
			'width' => $width, 
			'height' => $height
		); 
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
	 * 
	 * // Create image of size predefined in $config->imageSizes (3.0.151+)
	 * $photo = $image->size('landscape'); 
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
	 *  - `cropping` (string|bool|array): Cropping mode, see possible values in "cropping" section below (default=true).
	 *  - `suffix` (string|array): Suffix word to identify the new image, or use array of words for multiple (default=none).
	 *  - `forceNew` (bool): Force re-creation of the image even if it already exists? (default=false).
	 *  - `sharpening` (string): Sharpening mode: "none", "soft", "medium", or "strong" (default=soft).
	 *  - `autoRotation` (bool): Automatically correct rotation of images that provide this info? (default=true)
	 *  - `rotate` (int): Rotate the image this many degrees, specify one of: 0, -270, -180, -90, 90, 180, or 270 (default=0).
	 *  - `flip` (string): To flip, specify either "vertical" or "horizontal" (default=none).
	 *  - `hidpi` (bool): Use HiDPI/retina pixel doubling? (default=false).
	 *  - `hidpiQuality` (bool): Quality setting for HiDPI (default=40, typically lower than regular quality setting). 
	 *  - `cleanFilename` (bool): Clean filename of historical resize information for shorter filenames? (default=false).
	 *  - `nameWidth` (int): Width to use for filename (default is to use specified $width argument).
	 *  - `nameHeight` (int): Height to use for filename (default is to use specified $height argument). 
	 *  - `focus` (bool): Should resizes that result in crop use focus area if available? (default=true). 
	 *     In order for focus to be applicable, resize must include both width and height. 
	 *  - `allowOriginal` (bool): Return original if already at width/height? May not be combined with other options. (default=false)
	 *  - `webpAdd` (bool): Also create a secondary .webp image variation? (default=false)
	 *  - `webpQuality` (int): Quality setting for extra webp images (default=90). 
	 * 
	 * **Possible values for "cropping" option**  
	 * 
	 *  - `true` (bool): Auto detect and allow use of focus (default).
	 *  - `false` (bool): Disallow cropping. 
	 *  - `center` (string): to crop to center of image.
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
	 *  - `array(111,222)` (array): Array of integers index 0 is left pixels and index 1 is top pixels.
	 *  - `array('11%','22%')` (array): Array of '%' appended strings where index 0 is left percent and index 1 is top percent.
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
	 * @param int|string $width Target width of new image or (3.0.151+) specify prefined image size name
	 * @param int|array $height Target height of new image or (3.0.151+) options array if no height argument needed
	 * @param array|string|int $options Array of options to override default behavior: 
	 *  - Specify `array` of options as indicated in the section above. 
	 *  - Or you may specify type `string` containing "cropping" value.
	 *  - Or you may specify type `int` containing "quality" value.
	 *  - Or you may specify type `bool` containing "upscaling" value.
	 * @return Pageimage Returns a new Pageimage object that is a variation of the original. 
	 *  If the specified dimensions/options are the same as the original, then the original will be returned.
	 *
	 */
	public function size($width, $height = 0, $options = array()) {
		
		if(is_array($height)) {
			$options = $height;
			$height = 0;
		}
		
		if(!is_array($options)) {
			$options = $this->sizeOptionsToArray($options);
		}
		
		if(is_string($width) && $width && !ctype_digit($width)) {
			// named image size
			return $this->sizeName($width, $options);
		}

		if($this->wire()->hooks->isHooked('Pageimage::size()')) {
			$result = $this->__call('size', array($width, $height, $options)); 
		} else {  
			$result = $this->___size($width, $height, $options);
		}

		if($result && $result !== $this) {
			$options['_width'] = $width;
			$options['_height'] = $height;
			$result->set('sizeOptions', $options);
		}
		
		return $result;
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

		$this->error = '';
		if($this->ext === 'svg') return $this; 
		if(!is_array($options)) $options = $this->sizeOptionsToArray($options);
		
		// originally requested options
		$requestOptions = $options;
	
		// default options
		$defaultOptions = array(
			'upscaling' => true,
			'cropping' => true,
			'interlace' => false, 
			'sharpening' => 'soft',
			'quality' => 90,
			'hidpiQuality' => 40, 
			'webpQuality' => 90,
			'webpAdd' => false,
			'webpName' => '', // use this for the webp file basename rather than mirroring from the jpg/png
			'webpOnly' => false, // only keep the webp version (requires webpAdd option)
			'suffix' => array(), // can be array of suffixes or string of 1 suffix
			'forceNew' => false,  // force it to create new image even if already exists
			'hidpi' => false, 
			'cleanFilename' => false, // clean filename of historial resize information
			'rotate' => 0,
			'flip' => '', 
			'nameWidth' => null, // override width to use for filename, int when populated
			'nameHeight' => null,  // override height to use for filename, int when populated
			'focus' => true, // allow single dimension resizes to use focus area?
			'zoom' => null, // zoom override, used only if focus is applicable, int when populated
			'allowOriginal' => false, // Return original image if already at requested dimensions? (must be only specified option)
		);

		$files = $this->wire()->files;
		$config = $this->wire()->config;
		
		$debug = $config->debug;
		$configOptions = $config->imageSizerOptions; 
		$webpOptions = $config->webpOptions;
		$createdVariationHookData = null; // populated as array only when new variation created (for createdVariation hook)
		
		if(!empty($webpOptions['quality'])) $defaultOptions['webpQuality'] = $webpOptions['quality'];
		
		if(!is_array($configOptions)) $configOptions = array();
		$options = array_merge($defaultOptions, $configOptions, $options); 
		if($options['cropping'] === 1) $options['cropping'] = true;

		$width = (int) $width;
		$height = (int) $height;
		
		if($options['allowOriginal'] && count($requestOptions) === 1) {
			if((!$width || $this->width() == $width) && (!$height || $this->height() == $height)) {
				// return original image if already at requested width/height
				return $this;
			}
		}
	
		if($options['cropping'] === true 
			&& empty($options['cropExtra']) 
			&& $options['focus'] && $this->hasFocus 
			&& $width && $height) {
			// crop to focus area
			$focus = $this->focus();
			if(is_int($options['zoom'])) $focus['zoom'] = $options['zoom']; // override
			$options['cropping'] = array("$focus[left]%", "$focus[top]%", "$focus[zoom]"); 
			$crop = ''; // do not add suffix	
			
		} else if(is_string($options['cropping'])
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

		if($options['rotate'] && !in_array(abs((int) $options['rotate']), array(90, 180, 270))) {
			$options['rotate'] = 0;
		}
		if($options['rotate']) {
			$options['suffix'][] = ($options['rotate'] > 0 ? "rot" : "tor") . abs($options['rotate']);
		}
		if($options['flip']) {
			$options['suffix'][] = strtolower(substr($options['flip'], 0, 1)) == 'v' ? 'flipv' : 'fliph';
		}
		
		$suffixStr = '';
		if(!empty($options['suffix'])) {
			$suffix = $options['suffix'];
			sort($suffix); 
			foreach($suffix as $key => $s) {
				$s = strtolower($this->wire()->sanitizer->fieldName($s)); 
				if(empty($s)) {
					unset($suffix[$key]);
				} else {
					$suffix[$key] = $s;
				}
			}
			if(count($suffix)) $suffixStr = '-' . implode('-', $suffix); 
		}
		
		if($options['hidpi']) {
			$suffixStr .= '-hidpi';
			if($options['hidpiQuality']) $options['quality'] = $options['hidpiQuality'];
		}

		$originalName = $this->basename();
		// determine basename without extension, i.e. myfile
		$basename = basename($originalName, "." . $this->ext()); 
		$originalSize = $debug ? @filesize($this->filename) : 0;
		
		if($options['cleanFilename'] && strpos($basename, '.') !== false) {
			$basename = substr($basename, 0, strpos($basename, '.')); 
		}
		
		// filename uses requested width/height unless another specified via nameWidth or nameHeight options
		$nameWidth = is_int($options['nameWidth']) ? $options['nameWidth'] : $width;
		$nameHeight = is_int($options['nameHeight']) ? $options['nameHeight'] : $height;
		
		// i.e. myfile.100x100.jpg or myfile.100x100nw-suffix1-suffix2.jpg
		$basenameNoExt = $basename . '.' . $nameWidth . 'x' . $nameHeight . $crop . $suffixStr;  // basename without ext
		$basename = $basenameNoExt . '.' . $this->ext(); // basename with ext
		
		$filenameUnvalidated = '';
		$filenameUnvalidatedWebp = '';
		
		$filenameFinal = $this->pagefiles->path() . $basename;
		$filenameFinalExists = file_exists($filenameFinal);

		if(!empty($options['webpName'])) {
			$filenameFinalWebp = $this->pagefiles->path() . basename($options['webpName'], '.webp') . '.webp';
		} else if(!empty($webpOptions['useSrcExt'])) {
			$filenameFinalWebp = $this->pagefiles->path() . $basename . '.webp'; // file.jpg.webp
		} else {
			$filenameFinalWebp = $this->pagefiles->path() . $basenameNoExt . '.webp'; // file.webp
		}
		
		// force new creation if requested webp copy doesn't exist, (regardless if regular variation exists or not)
		if($options['webpAdd'] && !file_exists($filenameFinalWebp)) $options['forceNew'] = true;
		
		// create a new resize if it doesn't already exist or forceNew option is set
		if(!$filenameFinalExists && !file_exists($this->filename())) {
			// no original file exists to create variation from 
			$this->error = "Original image does not exist to create size variation: " . $this->url();
			
		} else if(!$filenameFinalExists || $options['forceNew']) {

			// filenameUnvalidated is temporary filename used for resize
			$tempDir = $this->pagefiles->page->filesManager()->getTempPath();
			$filenameUnvalidated = $tempDir . $basename;
			$filenameUnvalidatedWebp = $tempDir . $basenameNoExt . '.webp';
			
			if($filenameFinalExists && $options['forceNew']) $files->unlink($filenameFinal, true);
			if(file_exists($filenameFinalWebp) && $options['forceNew']) $files->unlink($filenameFinalWebp, true);
			
			if(file_exists($filenameUnvalidated)) $files->unlink($filenameUnvalidated, true);
			if(file_exists($filenameUnvalidatedWebp)) $files->unlink($filenameUnvalidatedWebp, true);

			if($files->copy($this->filename(), $filenameUnvalidated)) {
				try { 
					
					$timer = $debug ? Debug::timer() : null;
					
					/** @var ImageSizer $sizer */
					$sizer = $this->wire(new ImageSizer($filenameUnvalidated, $options));
					
					/** @var ImageSizerEngine $engine */
					$engine = $sizer->getEngine();

					/* if the current engine installation does not support webp, modify the options param */
					if(!empty($options['webpAdd']) && !$engine->supported('webp')) {
						// no engines support webp
						$options['webpAdd'] = false;
						$options['webpOnly'] = false;
						$engine->setOptions($options);
					}

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
					
					if($sizer->resize($width, $height)) {
						if($options['webpAdd'] && $options['webpOnly']) {
							if(is_file($filenameUnvalidated)) $files->unlink($filenameUnvalidated);
						} else {
							clearstatcache();
							if(!$files->rename($filenameUnvalidated, $filenameFinal)) {
								if($files->exists($filenameFinal)) {
									// potential race condition: another request won
								} else {
									$this->error = "Rename failed: $filenameUnvalidated => $filenameFinal";
								}
							}
						}
						if($options['webpAdd'] && file_exists($filenameUnvalidatedWebp)) { 
							$files->rename($filenameUnvalidatedWebp, $filenameFinalWebp);
						}
					} else {
						$this->error = "ImageSizer::resize($width, $height) failed for $filenameUnvalidated";
					}
					
					if($debug && empty($options['webpOnly'])) $this->wire()->log->save('image-sizer',
						str_replace('ImageSizerEngine', '', $sizer->getEngine()) . ' ' . 
						($this->error ? "FAILED Resize: " : "Resized: ") . "$originalName => " . basename($filenameFinal) . " " . 
						"({$width}x{$height}) " . Debug::timer($timer) . " secs $originalSize => " . filesize($filenameFinal) . " bytes " . 
						"(quality=$options[quality], sharpening=$options[sharpening]) "
					);
					
					if(!$this->error) {
						$createdVariationHookData = array(
							'width' => $width,
							'height' => $height,
							'options' => $options,
							'filenameUnvalidated' => $filenameUnvalidated,
							'filenameFinal' => $filenameFinal,
							'filenameUnvalidatedWebp' => $filenameUnvalidatedWebp,
							'filenameFinalWebp' => $filenameFinalWebp,
						);
					}
					
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
			// error condition: unlink copied files
			if($filenameFinal && $files->exists($filenameFinal)) $files->unlink($filenameFinal, true);
			if($filenameUnvalidated && $files->exists($filenameUnvalidated)) $files->unlink($filenameUnvalidated);
			if($filenameFinalWebp && $files->exists($filenameFinalWebp)) $files->unlink($filenameFinalWebp, true);
			if($filenameUnvalidatedWebp && $files->exists($filenameUnvalidatedWebp)) $files->unlink($filenameUnvalidatedWebp);

			// we also tell PW about it for logging and/or admin purposes
			$this->error($this->error);
			$logError = str_replace($config->paths('root'), $config->urls('root'), $filenameFinal)  . " - $this->error";
			$this->wire()->log->save('image-sizer', $logError);
		}

		$pageimage->setFilename($filenameFinal); 	
		$pageimage->setOriginal($this); 
		
		if($createdVariationHookData) $this->createdVariation($pageimage, $createdVariationHookData); 

		return $pageimage; 
	}

	protected function sizeOptionsToArray($options) {
		if(is_array($options)) return $options;
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
		return $options;
	}
	
	/**
	 * Same as size() but with width/height assumed to be hidpi width/height
	 * 
	 * #pw-internal
	 * 
	 * @param int|string $width
	 * @param int|array $height
	 * @param array $options See options in size() method. 
	 * @return Pageimage
	 *
	 */
	public function hidpiSize($width, $height = 0, $options = array()) {
		if(is_array($height)) {
			$height['hidpi'] = true;
		} else {
			$options['hidpi'] = true;
		}
		return $this->size($width, $height, $options);
	}

	/**
	 * Return image of size indicated by predefined setting
	 * 
	 * Settings for predefined sizes can be specified in `$config->imageSizes` array. 
	 * Each named item in this array must contain at least 'width' and 'height, but can also
	 * contain any other option from the `Pageimage::size()` argument `$options`. 
	 * 
	 * @param string $name Image size name
	 * @param array $options Optionally add or override options defined for size. 
	 * @return Pageimage
	 * @since 3.0.151
	 * @throws WireException If given a $name that is not present in $config->imageSizes
	 * 
	 */
	public function sizeName($name, array $options = array()) {
		$sizes = $this->wire()->config->imageSizes; 
		if(!isset($sizes[$name])) throw new WireException("Unknown image size '$name' (not in \$config->imageSizes)"); 
		$size = $sizes[$name];
		$options = array_merge($size, $options);
		unset($options['width'], $options['height']); 
		if(!isset($size['width'])) $size['width'] = 0;
		if(!isset($size['height'])) $size['height'] = 0;
		return $this->size((int) $size['width'], (int) $size['height'], $options);
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
			if($options === "100%") return $options;
			$width = is_array($options) ? 0 : (int) $options;
			if($width < 1) $width = $this->width();
			if($width === "100%") return $width;
			return ceil($width * $scale); 
		} else if($width && is_int($width)) {
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
	 * @param array $options See `Pageimage::size()` method for options, or these additional options:
	 *  - `allowOriginal` (bool): Allow original image to be returned if already within max requested dimensions? (default=false)
	 * @return Pageimage
	 * 
	 */
	public function maxSize($width, $height, $options = array()) {
		
		$defaults = array(
			'allowOriginal' => false,
			'upscaling' => false,
			'cropping' => false
		);
		
		$options = array_merge($defaults, $options);
		$adjustedWidth = $width < 1 || $this->width() <= $width ? 0 : $width;
		$adjustedHeight = $height < 1 || $this->height() <= $height ? 0 : $height;

		// if already within maxSize dimensions then do nothing
		if(!$adjustedWidth && !$adjustedHeight) {
			if($options['allowOriginal']) return $this; // image already within target
			$adjustedWidth = $width;
			$options['nameHeight'] = $height;
		} else if(!$adjustedWidth) {
			$options['nameWidth'] = $width;
		} else if(!$adjustedHeight) {
			$options['nameHeight'] = $height;
		}
		
		if($this->wire()->config->installed > 1513336849) { 
			// New installations from 2017-12-15 forward use an "ms" suffix for images from maxSize() method
			$suffix = isset($options['suffix']) ? $options['suffix'] : array();
			if(!is_array($suffix)) $suffix = array();
			$suffix[] = 'ms';
			$options['suffix'] = $suffix;
		}
		
		return $this->size($adjustedWidth, $adjustedHeight, $options);
	}

	/**
	 * Get ratio of width divided by height
	 * 
	 * @param int $precision Optionally specify a value >2 for custom precision (default=2) 3.0.211+
	 * @return float
	 * @since 3.0.154
	 * 
	 */
	public function ratio($precision = 2) {
		$width = $this->width();
		$height = $this->height();
		if($width === $height) return 1.0;
		$ratio = $width / $height;
		$ratio = round($ratio, max(2, (int) $precision));
		if($ratio > 99.99) $ratio = 99.99; // max allowed width>height ratio
		if($ratio < 0.01) $ratio = 0.01; // min allowed height>width ratio
		return $ratio;
	}

	/**
	 * Get the PageimageVariations helper instancd
	 * 
	 * #pw-internal
	 * 
	 * @return PageimageVariations
	 * 
	 */
	public function variations() {
		if($this->variations === null) $this->variations = new PageimageVariations($this);
		return $this->variations;
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
	 * 	- `info` (bool): when true, method returns variation info arrays rather than Pageimage objects (default=false).
	 *  - `verbose` (bool|int): Return verbose array of info. If false, returns only filenames (default=true). 
	 *     This option does nothing unless the `info` option is true. Also note that if verbose is false, then all options
	 *     following this one no longer apply (since it is no longer returning width/height info).
	 *     When integer 1, returned info array also includes Pageimage variation options in 'pageimage' index of
	 *     returned arrays (since 3.0.137).
	 * 	- `width` (int): only variations with given width will be returned
	 * 	- `height` (int): only variations with given height will be returned
	 * 	- `width>=` (int): only variations with width greater than or equal to given will be returned
	 * 	- `height>=` (int): only variations with height greater than or equal to given will be returned
	 * 	- `width<=` (int): only variations with width less than or equal to given will be returned
	 * 	- `height<=` (int): only variations with height less than or equal to given will be returned
	 * 	- `suffix` (string): only variations having the given suffix will be returned
	 *  - `suffixes` (array): only variations having one of the given suffixes will be returned
	 *  - `noSuffix` (string): exclude variations having this suffix
	 *  - `noSuffixes` (array): exclude variations having any of these suffixes
	 *  - `name` (string): only variations containing this text in filename will be returned (case insensitive)
	 *  - `noName` (string): only variations NOT containing this text in filename will be returned (case insensitive)
	 *  - `regexName` (string): only variations that match this PCRE regex will be returned
	 * @return Pageimages|array Returns Pageimages array of Pageimage instances. 
	 *  Only returns regular array if provided `$options['info']` is true.
	 *
	 */
	public function getVariations(array $options = array()) {
		return $this->variations()->find($options);
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
	 * - `4` (int): Rebuild only non-proportional, non-crop variations (variations that specify both width and height)
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
		return $this->variations()->rebuild($mode, $suffix, $options);
	}

	/**
	 * Given a file name (basename), return array of info if this is a variation for this instance’s file, or false if not.
	 *
	 * Returned array includes the following indexes: 
	 * 
	 * - `original` (string): Original basename
	 * - `url` (string): URL to image
	 * - `path` (string): Full path + filename to image
	 * - `width` (int): Specified width in filename
	 * - `height` (int): Specified height in filename
	 * - `actualWidth` (int): Actual width when checked manually
	 * - `actualHeight` (int): Acual height when checked manually
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
	 * @param array|bool $options Array of options to modify behavior, or boolean to only specify `allowSelf` option.
	 *  - `allowSelf` (bool): When true, it will return variation info even if same as current Pageimage. (default=false)
	 *  - `verbose` (bool): Return verbose array of info? If false, just returns basename (string) or false. (default=true)
	 * @return bool|string|array Returns false if not a variation, or array (verbose) or string (non-verbose) of info if it is.
	 *
	 */
	public function ___isVariation($basename, $options = array()) {
		return $this->variations()->getInfo($basename, $options);
	}

	/**
	 * Delete all the alternate sizes associated with this Pageimage
	 * 
	 * #pw-group-variations
	 *
	 * @param array $options See options for getVariations() method to limit what variations are removed, plus these:
	 *  - `dryRun` (bool): Do not remove now and instead only return the filenames of variations that would be deleted (default=false).
	 *  - `getFiles` (bool): Return deleted filenames? Also assumed if the test option is used (default=false). 
	 * @return PageImageVariations|array Returns $this by default, or array of deleted filenames if the `getFiles` option is specified
	 *
	 */
	public function removeVariations(array $options = array()) {
		return $this->variations()->remove($options);
	}

	/**
	 * Hook called after successful creation of image variation
	 * 
	 * @param Pageimage $image The variation image that was created
	 * @param array $data Verbose associative array of data used to create the variation 
	 * @since 3.0.180
	 * 
	 */
	protected function ___createdVariation(Pageimage $image, array $data) { }

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
	 * Copy this Pageimage and any of its variations to another path
	 * 
	 * #pw-internal
	 *
	 * @param string $path
	 * @return bool True if successful
	 *
	 */
	public function copyToPath($path) {
		$files = $this->wire()->files;
		if(parent::copyToPath($path)) {
			foreach($this->getVariations() as $variation) {
				/** @var Pageimage $variation */
				if(!is_file($variation->filename)) continue;
				$files->copy($variation->filename, $path); 
			}
			return true; 
		}
		return false; 
	}

	/**
	 * Render markup for this image (optionally using a provided markup template string and/or image size options)
	 * 
	 * Given template string can contain any of the placeholders, which will be replaced: 
	 *  - `{url}` or `{src}` Image URL (typically used for src attribute)
	 *  - `{httpUrl}` File URL with scheme and hostname (alternate for src attribute)
	 *  - `{URL}` Same as url but with cache busting query string
	 *  - `{HTTPURL}` Same as httpUrl but with cache busting query string
	 *  - `{description}` or `{alt}` Image description (typically used in alt attribute)
	 *  - `{tags}` File tags (might be useful in class attribute)
	 *  - `{width}` Width of image
	 *  - `{height}` Height of image
	 *  - `{hidpiWidth}` HiDPI width of image
	 *  - `{hidpiHeight}` HiDPI height of image
	 *  - `{ext}` File extension
	 *  - `{original.name}` Replace “name” with any of the properties above to refer to original image.
	 *     If there is no original image then these just refer back to the current image. 
	 * 
	 * ~~~~~
	 * $image = $page->images->first();
	 * if($image) {
	 *   // default output
	 *   echo $image->render(); 
	 * 
	 *   // custom output
	 *   echo $image->render("<img class='pw-image' src='{url}' alt='{alt}'>");
	 * 
	 *   // custom output with options
	 *   echo $image->render("<img src='{url}' alt='{alt}'>", [ 'width' => 300 ]);
	 *
	 *   // options can go in first argument if you prefer
	 *   echo $image->render([ 'width' => 300, 'height' => 200 ]);
	 *
	 *   // if only width/height are needed, they can also be specified as a string (1st or 2nd arg)
	 *   echo $image->render('300x200');
	 * 
	 *   // custom output with link to original/full-size and square crop of 300x300 for thumbnail
	 *   echo $image->render([
	 *     'markup' => "<a href='{original.url}'><img src='{url}' alt='{alt}'></a>",
	 *     'width' => 300,
	 *     'height' => 300
	 *   ]);
	 * }
	 * ~~~~~
	 * 
	 * @param string|array $markup Markup template string or optional $options array if you do not want the template string here.
	 * @param array|string $options Optionally resize image with these options sent to size() method:
	 *  - `width` (int): Target width or 0 for current image size (or proportional if height specified).
	 *  - `height` (int): Target height or 0 for current image size (or proportional if width specified).
	 *  - `markup` (string): Markup template string (same as $markup argument), or omit for default (same as $markup argument).
	 *  - `link` (bool): Link image to original size? Though you may prefer to do this with your own $markup (see examples). (default=false)
	 *  - `alt` (string): Text to use for “alt” attribute (default=text from image description).
	 *  - `class` (string): Text to use for “class” attribute, if `{class}` present in markup (default='').
	 *  - Plus any option available to the $options argument on the `Pageimage::size()` method. 
	 *  - If you only need width and/or height, you can specify a width x height string, i.e. 123x456 (use 0 for proportional).
	 * @return string
	 * @see Pageimages::render()
	 * @since 3.0.126
	 * 
	 */
	public function ___render($markup = '', $options = array()) {
		
		if(is_array($markup) || ($markup && strpos($markup, '}') === false)) {
			$options = $markup;
			$markup = isset($options['markup']) ? $options['markup'] : '';
		} 
		
		if(empty($markup)) {
			// provide default markup
			if(empty($options['class'])) {
				$markup = "<img src='{url}' alt='{alt}' />";
			} else {
				$markup = "<img src='{url}' alt='{alt}' class='{class}' />";
			}
		}
		
		if(is_string($options)) {
			if(ctype_digit(str_ireplace('x', '', $options))) {
				if(stripos($options, 'x') === false) $options .= 'x0';
				list($w, $h) = explode('x', strtolower($options));
				$options = array('width' => (int) $w, 'height' => (int) $h);
			} else {
				$options = array();
			}
		}

		$sanitizer = $this->wire()->sanitizer;
		$image = $this;
		$original = null;
		$replacements = array();
		$properties = array(
			'url', 'src', 'httpUrl', 'URL', 'HTTPURL',
			'description', 'alt', 'tags', 'ext', 'class',
			'width', 'height', 'hidpiWidth', 'hidpiHeight',
		);
		
		if(!empty($options['width']) || !empty($options['height'])) {
			$w = isset($options['width']) ? (int) $options['width'] : 0;
			$h = isset($options['height']) ? (int) $options['height'] : 0;
			$original = $this;
			$image = $this->size($w, $h, $options);
		}
		
		if(!empty($options['link']) && strpos($markup, '<a ') === false) {
			$markup = "<a href='{original.url}'>$markup</a>";
		}
		
		foreach($properties as $property) {
			$tag = '{' . $property . '}';
			if(strpos($markup, $tag) === false) continue;
			if(($property === 'alt' || $property === 'class') && isset($options[$property])) {
				$value = $sanitizer->entities($options[$property]);
			} else {
				$value = $sanitizer->entities1($image->get($property));
			}
			$replacements[$tag] = $value;
		}
		
		if(strpos($markup, '{class}')) {
			$class = isset($options['class']) ? $sanitizer->entities($options['class']) : 'pw-pageimage';
			$replacements["{class}"] = $class; 
		}
		
		if(strpos($markup, '{original.') !== false) {
			if(!$original) $original = $image->getOriginal();
			if(!$original) $original = $image;
			foreach($properties as $property) {
				$tag = '{original.' . $property . '}';
				if(strpos($markup, $tag) === false) continue;
				$value = $sanitizer->entities1($original->get($property));
				$replacements[$tag] = $value;
			}
		}
		
		return str_replace(array_keys($replacements), array_values($replacements), $markup);
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

	/**
	 * Get WebP "extra" version of this Pageimage
	 *
	 * @param array $webpOptions Optionally override certain defaults from `$config->webpOptions` (requires 3.0.229+):
	 *  - `useSrcUrlOnSize` (bool): Fallback to source file URL when webp file is larger than source? (default=true)
	 *  - `useSrcUrlOnFail` (bool): Fallback to source file URL when webp file fails for some reason? (default=true)
	 *  - `quality' (int): Quality setting of 1-100 where higher is better but larger in file size (default=90)
	 *     Note that his quality setting is only used if the .webp file does not already exist. 
	 * @return PagefileExtra
	 * @since 3.0.132
	 *
	 */
	public function webp(array $webpOptions = array()) {
		$webp = $this->extras('webp');
		if(!$webp) {
			$webp = new PagefileExtra($this, 'webp');
			$webpOptions = array_merge($this->wire()->config->webpOptions, $webpOptions);
			$webp->setArray($webpOptions);
			$this->extras('webp', $webp);
			$webp->addHookAfter('create', $this, 'hookWebpCreate'); 
		} else if(count($webpOptions)) {
			/** @var PagefileExtra $webp */
			$webp->setArray($webpOptions);
		}
		return $webp;
	}

	/**
	 * Hook to PageimageExtra (.webp) create method
	 * 
	 * #pw-internal
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookWebpCreate(HookEvent $event) {
		$original = $this->original;
		/** @var PagefileExtra $webp */
		$webp = $event->object;
		$webp->unlink();
		if($original && isset($this->sizeOptions['_width'])) {
			// we are in an image resized from an original
			$options = $this->sizeOptions;
			$width = $options['_width'];
			$height = $options['_height'];
		} else {
			// we are the original
			// create a file with same name as original but with .webp extension
			$original = $this;
			$options = array(
				'allowOriginal' => false, 
				'webpName' => $webp->useSrcExt ? $this->basename() : basename($this->basename(), ".$this->ext"),
				'webpOnly' => true
			);
			$width = $this->width;
			$height = 0;
		}
		$quality = (int) $webp->get('quality');
		if($quality > 0) $options['webpQuality'] = $quality;
		$options['webpAdd'] = true;
		try {
			$original->size($width, $height, $options);
		} catch(\Exception $e) {
			$this->error = ($this->error ? "$this->error - " : "") . $e->getMessage();
		}
		
		$error = $this->error;
		$event->return = empty($error); 
	}
	
	/**
	 * Get all extras, add an extra, or get an extra 
	 *
	 * #pw-internal
	 *
	 * @param string $name
	 * @param PagefileExtra|null $value
	 * @return PagefileExtra[]
	 * @since 3.0.132
	 *
	 */
	public function extras($name = null, ?PagefileExtra $value = null) {
		if($name) return parent::extras($name, $value); 
		$extras = parent::extras();
		$extras['webp'] = $this->webp();
		return $extras;
	}
	
	/**
	 * Rename this file
	 *
	 * Remember to follow this up with a `$page->save()` for the page that the file lives on.
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $basename New name to use. Must be just the file basename (no path).
	 * @return string|bool Returns new name (basename) on success, or boolean false if rename failed.
	 *
	 */
	public function rename($basename) {
		
		$variations = $this->getVariations();
		$oldBasename = $this->basename;
		$newBasename = parent::rename($basename);
		
		if($newBasename === false) return false;
		
		$ext = '.' . $this->ext();
		$oldName = basename($oldBasename, $ext);
		$newName = basename($newBasename, $ext); 
		
		foreach($variations as $pageimage) {
			/** @var Pageimage $pageimage */
			if(strpos($pageimage->basename, $oldName) !== 0) continue;
			$newVariationName = $newName . substr($pageimage->basename, strlen($oldName));
			$pageimage->rename($newVariationName);
		}
		
		return $newBasename;
	}
	
	/**
	 * Replace file with another
	 *
	 * Should be followed up with a save() to ensure related properties are also committed to DB.
	 *
	 * #pw-internal
	 *
	 * @param string $filename File to replace current one with
	 * @param bool $move Move given $filename rather than copy? (default=true)
	 * @return bool
	 * @throws WireException
	 * @since 3.0.154
	 *
	 */
	public function replaceFile($filename, $move = true) {
		if(!parent::replaceFile($filename, $move)) return false;
		$this->getImageInfo(true);
		return true;
	}

	public function __isset($key) {
		if($key === 'original') return $this->original !== null;
		return parent::__isset($key);
	}

	/**
	 * Get all filenames associated with this image
	 * 
	 * @return array
	 * @since 3.0.233
	 * 
	 */
	public function getFiles() {
		$filenames = parent::getFiles();
		foreach($this->extras() as $extra) {
			if($extra->exists()) $filenames[] = $extra->filename();
		}
		foreach($this->getVariations() as $pagefile) {
			/** @var Pagefile $pagefile */
			$filenames[] = $pagefile->filename();
			foreach($pagefile->extras() as $extra) {
				if($extra->exists()) $filenames[] = $extra->filename();
			}
		}
		return $filenames;
	}

	/**
	 * Basic debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		return $this->debugInfo->getBasicDebugInfo();
	}

	/**
	 * Verbose debug info (via @horst)
	 * 
	 * Optionally with individual options array.
	 *
	 * @param array $options The individual options you also passes with your image variation creation
	 * @param string $returnType 'string'|'array'|'object', default is 'string' and returns markup or plain text
	 * @return array|object|string
	 * @since 3.0.132
	 *
	 */
	public function getDebugInfo($options = array(), $returnType = 'string') {
		return $this->debugInfo->getVerboseDebugInfo($options, $returnType);
	}

	/**
	 * Get debug info from parent class
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * @since 3.0.132
	 * 
	 */
	public function _parentDebugInfo() {
		return parent::__debugInfo();
	}

}
