<?php namespace ProcessWire;

/**
 * ProcessWire PageimageVariations
 * 
 * Helper class for Pageimage that handles variation collection methods
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.137
 * 
 */

class PageimageVariations extends Wire implements \IteratorAggregate, \Countable {

	/**
	 * @var Pageimage
	 * 
	 */
	protected $pageimage;

	/**
	 * @var Pagefiles|Pageimages
	 * 
	 */
	protected $pagefiles;

	/**
	 * @var Pageimages|null
	 * 
	 */
	protected $variations = null;

	/**
	 * Construct
	 *
	 * @param Pageimage $pageimage
	 * 
	 */
	public function __construct(Pageimage $pageimage) {
		$this->pageimage = $pageimage;
		$this->pagefiles = $pageimage->pagefiles;
		$pageimage->wire($this);
		parent::__construct();
	}

	#[\ReturnTypeWillChange] 
	public function getIterator() {
		return $this->find();
	}

	/**
	 * Return a total or filtered count of variations
	 * 
	 * This method is also here to implement the Countable interface. 
	 * 
	 * @param array $options See options for find() method
	 * @return int
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function count($options = array()) {
		if($this->variations) {
			$count = $this->variations->count();
		} else {
			$options['count'] = true;
			$count = $this->find($options);
		}
		return $count;
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
	 * @param string $basename Filename to check (basename, which excludes path)
	 * @param array|bool $options Array of options to modify behavior, or boolean to only specify `allowSelf` option.
	 *  - `allowSelf` (bool): When true, it will return variation info even if same as current Pageimage. (default=false)
	 *  - `verbose` (bool): Return verbose array of info? If false, just returns basename (string) or false. (default=true)
	 * @return bool|string|array Returns false if not a variation, or array (verbose) or string (non-verbose) of info if it is.
	 *
	 */
	public function getInfo($basename, $options = array()) {

		$defaults = array(
			'allowSelf' => false,
			'verbose' => true,
		);

		if(!is_array($options)) $options = array('allowSelf' => (bool) $options);
		$options = array_merge($defaults, $options);

		static $level = 0;
		$variationName = basename($basename);
		$originalName = $this->pageimage->basename;

		// that that everything from the beginning up to the first period is exactly the same
		// otherwise, they are different source files
		$test1 = substr($variationName, 0, strpos($variationName, '.'));
		$test2 = substr($originalName, 0, strpos($originalName, '.'));
		if($test1 !== $test2) return false;

		// remove extension from originalName
		$originalName = basename($originalName, "." . $this->pageimage->ext());

		// if originalName is already a variation filename, remove the variation info from it.
		// reduce to original name, i.e. all info after (and including) a period
		if(strpos($originalName, '.') && preg_match('/^([^.]+)\.(?:\d+x\d+|-[_a-z0-9]+)/', $originalName, $matches)) {
			$originalName = $matches[1];
		}

		// if file is the same as the original, then it's not a variation
		if(!$options['allowSelf'] && $variationName == $this->pageimage->basename) return false;

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
			$parent = "$base." . $this->pageimage->ext();
		} else {
			$meat = $variationName;
			$parent = null;
		}

		// identify parent and any parent suffixes
		$suffixAll = array();
		if($options['verbose']) {
			while(($pos = strrpos($base, '.')) !== false) {
				$part = substr($base, $pos + 1);
				$base = substr($base, 0, $pos);
				while(($rpos = strrpos($part, '-')) !== false) {
					$suffixAll[] = substr($part, $rpos + 1);
					$part = substr($part, 0, $rpos);
				}
			}
		}

		// variation name with size dimensions and optionally suffix
		$re1 = '/^'  .
			'(\d+)x(\d+)' .					// 50x50	
			'([pd]\d+x\d+|[a-z]{1,2})?' . 	// nw or p30x40 or d30x40
			'(?:-([-_a-z0-9]+))?' . 		// -suffix1 or -suffix1-suffix2, etc.
			'\.' . $this->pageimage->ext() .
			'$/';

		// variation name with suffix only
		$re2 = '/^' .
			'-([-_a-z0-9]+)' . 				// suffix1 or suffix1-suffix2, etc. 
			'(?:\.' . 						// optional extras for dimensions/crop, starts with period
			'(\d+)x(\d+)' .				    // optional 50x50	
			'([pd]\d+x\d+|[a-z]{1,2})?' .   // nw or p30x40 or d30x40
			')?' .
			'\.' . $this->pageimage->ext() .
			'$/';

		// if regex does not match, return false
		if(preg_match($re1, $meat, $matches)) {

			// this is a variation with dimensions, return array of info
			$width = (int) $matches[1];
			$height = (int) $matches[2];
			$crop = isset($matches[3]) ? $matches[3] : '';
			$suffix = isset($matches[4]) ? explode('-', $matches[4]) : array();

		} else if(preg_match($re2, $meat, $matches)) {

			// this is a variation only with suffix
			$width = isset($matches[2]) ? (int) $matches[2] : 0;
			$height = isset($matches[3]) ? (int) $matches[3] : 0;
			$crop = isset($matches[4]) ? $matches[4] : '';
			$suffix = explode('-', $matches[1]);

		} else {
			return false;
		}

		// if not in verbose mode, just return variation basename
		if(!$options['verbose']) return $variationName;

		$path = $this->pagefiles->path . $basename;
		$actualInfo = $this->pageimage->getImageInfo($path);

		$info = array(
			'name' => $basename,
			'url' => $this->pagefiles->url . $basename,
			'path' => $path,
			'original' => $originalName . '.' . $this->pageimage->ext(),
			'width' => $width,
			'height' => $height,
			'crop' => $crop,
			'suffix' => $suffix,
			'suffixAll' => array(), // present only when image has a parent variation
			'actualWidth' => $actualInfo['width'],
			'actualHeight' => $actualInfo['height'],
			'hidpiWidth' => $this->pageimage->hidpiWidth(0, $actualInfo['width']),
			'hidpiHeight' => $this->pageimage->hidpiWidth(0, $actualInfo['height']),
			'parentName' => '', // present only when image has a parent variation
			'parent' => null, // present only when image has a parent variation
			'webpUrl' => '',
			'webpPath' => '',
		);

		foreach($this->pageimage->extras() as $name => $extra) {
			
			if($extra->exists()) {
				$info["{$name}Url"] = $extra->url(false);
				$info["{$name}Path"] = $extra->filename();
				continue;
			} 
			
			$f = "$basename.$extra->ext"; // useSrcExt, i.e. file.png.webp
			if(is_readable($this->pagefiles->path . $f)) {
				$info["{$name}Url"] = $this->pagefiles->url . $f;
				$info["{$name}Path"] = $this->pagefiles->path . $f;
				continue;
			}
			
			$f = basename($basename, '.' . $this->pageimage->ext()) . ".$extra->ext";
			if(is_readable($this->pagefiles->path . $f)) {
				$info["{$name}Url"] = $this->pagefiles->url . $f;
				$info["{$name}Path"] = $this->pagefiles->path . $f;
				// continue;
			}
		}

		if(empty($info['crop'])) {
			// attempt to extract crop info from suffix
			foreach($info['suffix'] as /* $key => */ $suffix) {
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
			$info['parent'] = $this->getInfo($parent);
			$level--;
		} else {
			unset($info['parent'], $info['parentName'], $info['suffixAll']);
		}

		if(!$this->pageimage->__isset('original') && $info['original']) {
			$original = $this->pagefiles->get($info['original']);
			if($original) $this->pageimage->setOriginal($original);
		}

		return $info;
	}


	/**
	 * Get all size variations of this image
	 *
	 * This is useful after a delete of an image (for example). This method can be used to track down all the
	 * child files that also need to be deleted.
	 *
	 * @param array $options Optional, one or more options in an associative array of the following:
	 * 	- `info` (bool): when true, method returns variation info arrays rather than Pageimage objects (default=false).
	 *  - `count` (bool): when true, only a count of variations is returned (default=false). 
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
	 * @return Pageimages|array|int Returns Pageimages array of Pageimage instances.
	 *  Only returns regular array if provided `$options['info']` is true.
	 *  Returns integer if count option is specified. 
	 *
	 */
	public function find(array $options = array()) {

		if(!is_null($this->variations) && empty($options)) return $this->variations;

		$defaults = array(
			'info' => false,
			'verbose' => true,
			'count' => false, 
		);

		$options = array_merge($defaults, $options);
		if($options['count']) {
			$options['verbose'] = false;
			$options['info'] = false;
		} else if(!$options['verbose'] && !$options['info']) {
			$options['verbose'] = true; // non-verbose only allowed if info==true
		}

		$variations = null;
		$dir = new \DirectoryIterator($this->pagefiles->path);
		$infos = array();
		$count = 0;
		
		if(!$options['info'] && !$options['count']) {
			/** @var Pageimages $variations */
			$variations = $this->wire(new Pageimages($this->pagefiles->page));
		}

		// if suffix or noSuffix option contains space, convert it to suffixes or noSuffixes array option
		foreach(array('suffix', 'noSuffix') as $key) {
			if(!isset($options[$key])) continue;
			if(strpos(trim($options[$key]), ' ') === false) continue;
			$keyPlural = $key . 'es';
			$value = isset($options[$keyPlural]) ? $options[$keyPlural] : array();
			$options[$keyPlural] = array_merge($value, explode(' ', trim($options[$key])));
			unset($options[$key]);
		}

		foreach($dir as $file) {

			if($file->isDir() || $file->isDot()) continue;
			
			$info = $this->getInfo($file->getFilename(), array('verbose' => $options['verbose']));
			if(!$info) continue;
			
			if($options['info'] && !$options['verbose']) {
				$infos[] = $info;
				continue;
			}

			$allow = true;

			foreach($options as $option => $value) {
				switch($option) {
					case 'width': $allow = $info['width'] == $value; break;
					case 'width>=': $allow = $info['width'] >= $value; break;
					case 'width<=': $allow = $info['width'] <= $value; break;
					case 'height': $allow = $info['height'] == $value; break;
					case 'height>=': $allow = $info['height'] >= $value; break;
					case 'height<=': $allow = $info['height'] <= $value; break;
					case 'name': $allow = stripos($file->getBasename(), $value) !== false; break;
					case 'noName': $allow = stripos($file->getBasename(), $value) === false; break;
					case 'regexName': $allow = preg_match($value, $file->getBasename()); break;
					case 'suffix': $allow = in_array($value, $info['suffix']); break;
					case 'noSuffix': $allow = !in_array($value, $info['suffix']); break;
					case 'suffixes':
						// any one of given suffixes will allow the variation
						$allow = false;
						foreach($value as $suffix) {
							$allow = in_array($suffix, $info['suffix']);
							if($allow) break;
						}
						break;
					case 'noSuffixes':
						// any one of the given suffixes will disallow the variation
						$allow = true;
						foreach($value as $noSuffix) {
							if(!in_array($noSuffix, $info['suffix'])) continue;
							$allow = false;
							break;
						}
						break;
				}
				if(!$allow) break;
			}

			if(!$allow) continue;
			
			$basename = $file->getBasename();
			
			if($options['count']) {
				$count++;
				continue;
			}
			
			if(empty($options['info']) || $options['verbose'] === 1) {
				$pageimage = clone $this->pageimage;
				$pathname = $file->getPathname();
				if(DIRECTORY_SEPARATOR != '/') $pathname = str_replace(DIRECTORY_SEPARATOR, '/', $pathname);
				$pageimage->setFilename($pathname);
				$pageimage->setOriginal($this->pageimage);
				if($options['verbose'] === 1) {
					$info['pageimage'] = $pageimage;
				} else if($variations) {
					$variations->add($pageimage);
				}
			}
			if(!empty($options['info'])) {
				$infos[$basename] = $info;
			}
		}
		
		if($options['count']) return $count;
		if(!empty($options['info'])) return $infos;
		if(empty($options)) $this->variations = $variations;

		return $variations;
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
	public function rebuild($mode = 0, array $suffix = array(), array $options = array()) {
		
		$files = $this->wire()->files;

		$skipped = array();
		$rebuilt = array();
		$errors = array();
		$reasons = array();
		$options['forceNew'] = true;

		foreach($this->find(array('info' => true)) as $info) {

			$o = $options;
			unset($o['cropping']);
			$skip = false;
			$name = $info['name'];
			$hadWebp = false;

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
						// continue;
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

			if($mode == 4 && ($info['width'] == 0 || $info['height'] == 0)) {
				// skip images that don't specify both width and height
				$skip = true;
			}

			if($skip) {
				$skipped[] = $name;
				continue;
			}

			// rebuild the variation
			$o['forceNew'] = true;
			$o['suffix'] = $info['suffix'];

			if(is_file($info['path'])) {
				$files->unlink($info['path'], true);
				if(!empty($info['webpPath']) && $files->exists($info['webpPath'])) {
					$files->unlink($info['webpPath'], true);
					$hadWebp = true;
				}
			}

			/*
			if(!$info['width'] && $info['actualWidth']) {
				$info['width'] = $info['actualWidth'];
				$o['nameWidth'] = 0;
			}
			if(!$info['height'] && $info['actualHeight']) {
				$info['height'] = $info['actualHeight'];
				$o['nameHeight'] = 0;
			}
			*/

			if($info['crop'] && preg_match('/^x(\d+)y(\d+)$/', $info['crop'], $matches)) {
				// dimensional cropping info contained in filename
				$cropX = (int) $matches[1];
				$cropY = (int) $matches[2];
				$variation = $this->pageimage->crop($cropX, $cropY, $info['width'], $info['height'], $o);

			} else if($info['crop']) {
				// direct cropping info contained in filename
				$options['cropping'] = $info['crop'];
				$variation = $this->pageimage->size($info['width'], $info['height'], $o);

			} else if($this->pageimage->hasFocus) {
				// crop to focus area, which the size() method will determine on its own
				$variation = $this->pageimage->size($info['width'], $info['height'], $o);

			} else {
				// no crop, no focus, just resize
				$variation = $this->pageimage->size($info['width'], $info['height'], $o);
			}

			if($variation) {
				if($variation->name != $name) {
					$files->rename($variation->filename(), $info['path']);
					$variation->data('basename', $name);
				}
				$rebuilt[] = $name;
				if($hadWebp) {
					// forces create of webp version
					$webpName = basename($variation->webp()->url());
					if($webpName) $rebuilt[] = $webpName;
				}
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
	 * Delete all the alternate sizes associated with this Pageimage
	 *
	 * @param array $options See options for getVariations() method to limit what variations are removed, plus these:
	 *  - `dryRun` (bool): Do not remove now and instead only return the filenames of variations that would be deleted (default=false).
	 *  - `getFiles` (bool): Return deleted filenames? Also assumed if the test option is used (default=false).
	 * @return $this|array Returns $this by default, or array of deleted filenames if the `getFiles` option is specified
	 *
	 */
	public function remove(array $options = array()) {
		
		$files = $this->wire()->files;

		$defaults = array(
			'dryRun' => false,
			'getFiles' => false
		);

		$variations = $this->find($options);
		if(!empty($options['dryrun'])) $defaults['dryRun'] = $options['dryrun']; // case insurance
		$options = array_merge($defaults, $options); // placement after getVariations() intended

		$deletedFiles = array();
		
		$this->removeExtras($this->pageimage, $deletedFiles, $options);

		foreach($variations as $variation) {
			/** @var Pageimage $variation */
			$filename = $variation->filename;
			if(!is_file($filename)) continue;
			if($options['dryRun']) {
				$success = true;
			} else {
				$success = $files->unlink($filename, true);
			}
			if($success) $deletedFiles[] = $filename;
			$this->removeExtras($variation, $deletedFiles, $options);
		}

		if(!$options['dryRun']) $this->variations = null;

		return ($options['dryRun'] || $options['getFiles'] ? $deletedFiles : $this);
	}

	/**
	 * Remove extras
	 * 
	 * @param Pageimage $pageimage
	 * @param array $deletedFiles
	 * @param array $options See options for remove() method
	 * 
	 */
	protected function removeExtras(Pageimage $pageimage, array &$deletedFiles, array $options) {
		foreach($pageimage->extras() as $extra) {
			if(!$extra->exists()) {
				// nothing to do
			} else if(!empty($options['dryRun'])) {
				$deletedFiles[] = $extra->filename();
			} else if($extra->unlink()) {
				$deletedFiles[] = $extra->filename();
			}
		}
	}
}
