<?php namespace ProcessWire;

/**
 * Image Inspector
 * 
 * Upgrades ImageSizer and ImageSizerEngines with more in depth information of imagefiles and -formats.
 * 
 * Copyright (C) 2016 by Horst Nogajski and Ryan Cramer
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 * 
 * For modules that extend this, use: autoload=false, singular=false. 
 *
 */
class ImageInspector extends WireData {

	/**
	 * Filename to be inspected
	 *
	 * @var string
	 *
	 */
	protected $filename;

	/**
	 * Extension of filename
	 *
	 * @var string
	 *
	 */
	protected $extension;

	/**
	 * Information about the image
	 *
	 * @var array|null
	 *
	 */
	protected $info = null;

	/**
	 * Supported image types
	 *
	 * @var array
	 *
	 */
	protected $supportedImageTypes = array(
		'gif' => \IMAGETYPE_GIF,
		'jpg' => \IMAGETYPE_JPEG,
		'jpeg' => \IMAGETYPE_JPEG,
		'png' => \IMAGETYPE_PNG
	);

	/**
	 * Supported mime types
	 *
	 * @var array
	 *
	 */
	protected $supportedMimeTypes = array(
		\IMAGETYPE_GIF => 'image/gif',
		\IMAGETYPE_JPEG => 'image/jpeg',
		\IMAGETYPE_PNG => 'image/png'
	);

	/**
	 * Construct
	 * 
	 * @param string $filename
	 * 
	 */
	public function __construct($filename = '') {
		parent::__construct();
		if($filename && is_readable($filename)) {
			$this->filename = $filename;
		}
	}

	/**
	 * parse Image and return information
	 *
	 * @param string $filename the file we want to inspect
	 * @param bool $parseAppmarker (IPTC), default is FALSE
	 * @return null|false|array
	 *
	 */
	public function inspect($filename = '', $parseAppmarker = false) {
		if($filename) $this->filename = $filename;
		if(!$this->filename || !is_readable($this->filename)) return null;
		if(!$this->extension) $this->extension = pathinfo($this->filename, \PATHINFO_EXTENSION);
		$this->extension = strtolower($this->extension);

		$additionalInfo = array();
		$info = @getimagesize($filename, $additionalInfo);
		if($info === false) return false;

		// read basic data
		if(isset($info[2])) {
			$imageType = $info[2];
		} else if(function_exists("exif_imagetype")) {
			$imageType = exif_imagetype($this->filename);
		} else if(isset($this->supportedImageTypes[$this->extension])) {
			$imageType = $this->supportedImageTypes[$this->extension];
		} else {
			// default fallback so that $imageType is defined
			$imageType = \IMAGETYPE_JPEG;
		}
		$this->info['width'] = $info[0];
		$this->info['height'] = $info[1];
		$this->info['imageType'] = $imageType;
		$this->info['mime'] = isset($this->supportedMimeTypes[$imageType]) ? $this->supportedMimeTypes[$imageType] : 'unsupported';

		// Infos about Orientation and optional corrections for rotate and flip
		$tmp = $this->checkOrientation($this->filename);
		$this->info['orientation'] = $tmp['orientation'];  // Exif-Orientation value :: integer range 1 - 8, 0 on failure
		$this->info['rotate'] = $tmp['rotate'];            // empty or 0 or degrees :: 0 | 90 | 180 | 270
		$this->info['flip'] = $tmp['flip'];                // empty | horizontal | vertical :: 0 | 1 | 2

		// additional, more indepth data
		$this->info['channels'] = isset($info['channels']) ? $info['channels'] : -1;
		$this->info['bits'] = isset($info['bits']) ? $info['bits'] : -1;
		switch($imageType) {
			case \IMAGETYPE_GIF:
				$this->loadImageInfoGif();
				break;
			case \IMAGETYPE_JPEG:
				$this->loadImageInfoJpg();
				break;
			case \IMAGETYPE_PNG:
				$this->loadImageInfoPng();
				break;
		}

		// read appmarker metadata if present
		$this->info['appmarker'] = $iptcRaw = null;
		if(is_array($additionalInfo) && $parseAppmarker) {
			$appmarker = array();
			foreach($additionalInfo as $k => $v) {
				$appmarker[$k] = substr($v, 0, strpos($v, chr(0)));
			}
			$this->info['appmarker'] = $appmarker;
			if(isset($additionalInfo['APP13'])) {
				$iptc = iptcparse($additionalInfo['APP13']);
				if(is_array($iptc)) $iptcRaw = $iptc;
			}
		}

		// return the result
		return array(
			'filename' => $this->filename,
			'extension' => $this->extension,
			'imageType' => $imageType,
			'info' => $this->info,
			'iptcRaw' => $iptcRaw
		);
	}

	/**
	 * Check orientation (@horst)
	 *
	 * @param array
	 * @return array
	 * @todo there is already a checkOrientation method in ImageSizerEngine - do we need both?
	 *
	 */
	protected function checkOrientation($filename) {
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
		$result = array('orientation' => 0, 'rotate' => 0, 'flip' => 0);
		$supportedExifMimeTypes = array('image/jpeg', 'image/tiff'); // hardcoded by PHP
		$mime = isset($this->info['mime']) ? $this->info['mime'] : 'no';

		if(!function_exists('exif_read_data') || !in_array($mime, $supportedExifMimeTypes)) {
			return $result;
		}

		$exif = @exif_read_data($filename, 'IFD0');
		if(!is_array($exif)
			|| !isset($exif['Orientation'])
			|| !in_array(strval($exif['Orientation']), array_keys($corrections))
		) {
			return $result;
		}
		$correctionArray = $corrections[strval($exif['Orientation'])];
		$result = array(
			'orientation' => $exif['Orientation'],
			'rotate' => $correctionArray[0],
			'flip' => $correctionArray[1]
		);
		return $result;
	}

	/**
	 * parse PNG Image and collect information into $this->info
	 *
	 * @return bool
	 *
	 */
	protected function loadImageInfoPng() {
		$png = new PWPNG();
		if(!$png->loadFile($this->filename)) {
			return false;
		}
		$this->info = array_merge($this->info, $png->info);
		unset($png);
		return true;
	}

	/**
	 * parse GIF Image and collect information into $this->info
	 *
	 * @return bool
	 *
	 */
	protected function loadImageInfoGif() {
		$gif = new PWGIF(false);  // passing true also loads BitmapData
		$iIndex = 0;
		if(!$gif->loadFile($this->filename, $iIndex)) {
			return false;
		}
		$gi  = $gif->m_img;			// = CGIFIMAGE
		$gfh = $gif->m_gfh;			// = CGIFFILEHEADER
		$gih = $gif->m_img->m_gih;	// = CGIFIMAGEHEADER
		$i = $this->info;
		$i['width']			= $gfh->m_nWidth;
		$i['height']		= $gfh->m_nHeight;
		$i['gifversion']	= $gfh->m_lpVer;
		$i['animated']		= $gfh->m_bAnimated;
		$i['delay']         = isset($gi->m_nDelay) ? $gi->m_nDelay : '';
		$i['trans']         = isset($gi->m_bTrans) ? $gi->m_bTrans : false;
		$i['transcolor']    = isset($gi->m_nTrans) ? $gi->m_nTrans : '';
		$i['bgcolor']		= $gfh->m_nBgColor;
		$i['numcolors']		= isset($gfh->m_colorTable->m_nColors) ? $gfh->m_colorTable->m_nColors : 0;
		$i['interlace']		= $gih->m_bInterlace;
		$this->info = $i;
		unset($gif, $gih, $gfh, $gi, $i);
		return true;
		//    CGIFFILEHEADER
		//        numColors = m_colorTable->m_nColors
		//        m_lpVer
		//        m_nWidth
		//        m_nHeight
		//        m_bGlobalClr
		//        m_nColorRes
		//        m_bSorted
		//        m_nTableSize
		//        m_nBgColor
		//        m_nPixelRatio
		//        m_bAnimated
		//
		//    CGIFIMAGEHEADER
		//        m_nLeft
		//        m_nTop
		//        m_nWidth
		//        m_nHeight
		//        m_bLocalClr
		//        m_bInterlace
		//        m_bSorted
		//        m_nTableSize
		//        m_colorTable
		//
		//    CGIFIMAGE
		//        m_disp
		//        m_bUser
		//        m_bTrans
		//        m_nDelay
		//        m_nTrans
		//        m_lpComm
	}

	/**
	 * parse JPEG Image and collect information into $this->info
	 *
	 */
	protected function loadImageInfoJpg() {
	}

}