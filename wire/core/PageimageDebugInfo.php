<?php namespace ProcessWire;

/**
 * Debug info for Pageimage 
 * 
 * By Horst Nogajski for ProcessWire
 *
 * @property string $url
 * @property string $filename
 * @property string $basename
 * @property Pageimage $original
 * @property int $width
 * @property int $height
 * @property bool $hasFocus
 * @property string $focusStr
 * @property string $suffixStr
 * 
 */
class PageimageDebugInfo extends WireData {

	/**
	 * @var Pageimage
	 * 
	 */
	protected $pageimage;

	/**
	 * Construct
	 *
	 * @param Pageimage $pageimage
	 * 
	 */
	public function __construct(Pageimage $pageimage) {
		$pageimage->wire($this);
		$this->pageimage = $pageimage;
		parent::__construct();
	}

	/**
	 * Get property
	 * 
	 * This primarily delegates to the Pageimage object so that its properties can be accessed
	 * directly from this class. 
	 * 
	 * @param string $key
	 * @return mixed|null
	 * 
	 */
	public function get($key) {
		$value = $this->pageimage->get($key);
		if($value === null) $value = parent::get($key);
		return $value;
	}

	/**
	 * Get basic debug info, like that used for Pageimage::__debugInfo()
	 * 
	 * @return array
	 * 
	 */
	public function getBasicDebugInfo() {
		static $depth = 0;
		$depth++;
		$info = $this->pageimage->_parentDebugInfo(); 
		$info['width'] = $this->pageimage->width();
		$info['height'] = $this->pageimage->height();
		$info['suffix'] = $this->pageimage->suffixStr;
		if($this->pageimage->hasFocus) $info['focus'] = $this->pageimage->focusStr;
		if(isset($info['filedata']) && isset($info['filedata']['focus'])) unset($info['filedata']['focus']);
		if(empty($info['filedata'])) unset($info['filedata']);
		$original = $this->original;
		if($original && $original !== $this) $info['original'] = $original->basename;
		if($depth < 2) {
			$info['variations'] = array();
			$variations = $this->pageimage->getVariations(array('info' => true, 'verbose' => false));
			foreach($variations as $name) {
				$info['variations'][] = $name;
			}
			if(empty($info['variations'])) unset($info['variations']);
		}
		$depth--;
		return $info;
	}
	
	/**
	 * Get verbose DebugInfo, optionally with individual options array, @horst
	 * 
	 * (without invoking the magic debug)
	 *
	 * @param array $options The individual options you also passes with your image variation creation
	 * @param string $returnType 'string'|'array'|'object', default is 'string' and returns markup or plain text
	 * @return array|object|string
	 *
	 */
	public function getVerboseDebugInfo($options = array(), $returnType = 'string') {
		static $depth = 0;
		$depth++;

		// fetch imagesizer, some infos and some options
		$oSizer = new ImageSizer($this->filename, $options);
		$this->wire($oSizer);
		$osInfo = $oSizer->getImageInfo(true);
		$finalOptions = $oSizer->getOptions();

		// build some info parts and fetch some from parent (pagefile)
		$thumbStyle = "max-width:120px; max-height:120px;";
		$thumbStyle .= $this->width >= $this->height ? 'width:100px; height:auto;' : 'height:100px; width:auto;';
		$thumb = array(
			'thumb' => "<img src='$this->url' style='$thumbStyle' alt='' />"
		);
		
		if($this->original) {
			$original = array(
				'original' => $this->original->basename, 
				'basename' => $this->basename
			); 
		} else {
			$original = array(
				'original' => '{SELF}', 
				'basename' => $this->basename
			);
		}
		
		$parent = array(
			'files' => array_merge(
				$original,
				$this->pageimage->_parentDebugInfo(),
				array(
					'suffix'    => isset($finalOptions['suffix']) ? $finalOptions['suffix'] : '',
					'extension' => $osInfo['extension']
				)
			)
		);
		
		// rearange parts
		unset($parent['files']['filesize']);
		$parent['files']['filesize'] = filesize($this->filename);

		// VARIATIONS
		$variationArray = array();
		if($depth < 2) {
			$variations = $this->pageimage->getVariations(array('info' => true, 'verbose' => false));
			foreach($variations as $name) $variationArray[] = $name;
		}
		
		$depth--;
		unset($variations, $name);

		// start collecting the $info
		$info = array_merge($thumb, $parent,
			array(
				'variations' => $variationArray
			),
			array(
				'imageinfo' => array(
					'imageType'   => $osInfo['info']['imageType'],
					'mime'        => $osInfo['info']['mime'],
					'width'       => $this->width,
					'height'      => $this->height,
					'focus'       => $this->hasFocus ? $this->focusStr : NULL,
					'description' => $parent['files']['description'],
					'tags'        => $parent['files']['tags'],
				)
			)
		);
		
		unset($info['files']['tags'], $info['files']['description']);

		// beautify the output, remove unnecessary items
		if(isset($info['files']['filedata']) && isset($info['files']['filedata']['focus'])) unset($info['files']['filedata']['focus']);
		if(empty($info['files']['filedata'])) unset($info['files']['filedata']);
		unset($osInfo['info']['mime'], $osInfo['info']['imageType']);

		// add the rest from osInfo to the final $info array
		foreach($osInfo['info'] as $k => $v) $info['imageinfo'][$k] = $v;
		$info['imageinfo']['iptcRaw'] = $osInfo['iptcRaw'];
		unset($osInfo, $thumb, $original, $parent);

		// WEBP
		$webp = $this->pageimage->webp();
		$webpSize = $webp->exists() ? filesize($webp->filename()) : 0;
		$webpInfo = array(
			'webp_copy' => array(
				'hasWebp'          => $webpSize ? true : false,
				'webpUrl'          => (!$webpSize ? NULL : $webp->url()),
				'webpQuality'      => (!isset($finalOptions['webpQuality']) ? NULL : $finalOptions['webpQuality']),
				'filesize'         => $webpSize, 
				'savings'          => (!$webpSize ? 0 : intval($info['files']['filesize'] - $webpSize)),
				'savings_percent'  => (!$webpSize ? 0 : 100 - intval($webpSize / ($info['files']['filesize'] / 100))),
			)
		);

		// ENGINES
		$a = array();
		$modules = $this->wire('modules');
		$engines = array_merge($oSizer->getEngines(), array('ImageSizerEngineGD'));
		foreach($engines as $moduleName) {
			$configData = $modules->getModuleConfigData($moduleName);
			$priority = isset($configData['enginePriority']) ? (int) $configData['enginePriority'] : 0;
			$a[$moduleName] = "priority {$priority}";
		}
		asort($a, SORT_STRING);
		$enginesArray = array(
			'neededEngineSupport' => strtoupper($oSizer->getImageInfo()),
			'installedEngines' => $a,
			'selectedEngine' => $oSizer->getEngine()->className,
			'engineWebpSupport' => $oSizer->getEngine()->supported('webp')
		);
		unset($a, $moduleName, $configData, $engines, $priority, $modules, $oSizer);

		// merge all into $info
		$info = array_merge($info, $webpInfo,
			array(
				'engines'  => $enginesArray
			),
			// OPTIONS
			array(
				'options_hierarchy' => array(
					'imageSizerOptions' => $this->wire('config')->imageSizerOptions,
					'individualOptions' => $options,
					'finalOptions' => $finalOptions
				)
			)
		);
		unset($variationArray, $webpInfo, $enginesArray, $options, $finalOptions);

		// If not in browser environment, remove the thumb image
		if($this->wire('config')->cli) unset($info['thumb']);

		if('array' == $returnType) {
			// return as array
			return $info;
		} else if('object' == $returnType) {
			// return as object
			$object = new \stdClass();
			foreach($info as $group => $array) {
				$object->$group = new \stdClass();
				if('thumb' == $group) {
					$object->$group = $array;
					continue;
				}
				$this->arrayToObject($array, $object->$group);
			}
			return $object;
		}

		// make a beautified var_dump
		$tmp = $info;
		$info = array();
		foreach($tmp as $group => $array) {
			$info[mb_strtoupper($group)] = $array;
		}
		unset($tmp, $group, $array);
		ob_start();
		var_dump($info);
		$content = ob_get_contents();
		ob_end_clean();
		$m = 0;
		preg_match_all('#^(.*)=>#mU', $content, $stack);
		$lines = $stack[1];
		$indents = array_map('strlen', $lines);
		if($indents) $m = max($indents) + 1;
		$content = preg_replace_callback(
			'#^(.*)=>\\n\s+(\S)#Um',
			function($match) use($m) {
				return $match[1] . str_repeat(' ', ($m - strlen($match[1]) > 1 ? $m - strlen($match[1]) : 1)) . $match[2];
			},
			$content
		);
		$content = preg_replace('#^((\s*).*){$#m', "\\1\n\\2{", $content);
		$content = str_replace(array('<pre>', '</pre>'), '', $content);
		
		if($this->wire('config')->cli) {
			// output for Console
			$return = $content;
		} else {
			// build output for HTML
			$return = "<pre style='overflow:auto'>$content</pre>";
		}

		return $return;
	}

	/**
	 * Helper method that converts a multidim array to a multidim object for the getDebugInfo method
	 *
	 * @param array $array the input array
	 * @param object $object the initial object, gets passed recursive by reference through all loops
	 * @param bool $multidim set this to true to avoid multidimensional object
	 * @return object the final multidim object
	 *
	 */
	private function arrayToObject($array, &$object, $multidim = true) {
		foreach($array as $key => $value) {
			if($multidim && is_array($value)) {
				$object->$key = new \stdClass();
				$this->arrayToObject($value, $object->$key, false);
			} else {
				$object->$key = $value;
			}
		}
		return $object;
	}


}