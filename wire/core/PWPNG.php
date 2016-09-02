<?php namespace ProcessWire;
///////////////////////////////////////////////////////////////////////////////////////////////////
// parsePng is a part of FPDF v1.81 - 2015-12-20
// Author: Olivier PLATHEY
// http://fpdf.org/
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software to use, copy, modify, distribute, sublicense, and/or sell
// copies of the software, and to permit persons to whom the software is furnished
// to do so.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED.
///////////////////////////////////////////////////////////////////////////////////////////////////
// PNG Inspector 
// original code from FPDF modified in April 2016 by Horst Nogajski
// to be used for png image inspection in ProcessWire 3+ (http://processwire.com/)
///////////////////////////////////////////////////////////////////////////////////////////////////
class PWPNG {
	public $info = array();
	public $errors = array();
	protected $extended;
	public function __construct($extended = false) {
		$this->extended = $extended;
	}
	public function loadFile($lpszFileName) {
		// READ FILE
		if(!($fh = @fopen($lpszFileName, 'rb'))) {
			$this->Error('Can\'t open image file: '.$file);
			return false;
		}
		$ret = (false === $this->_parsepngstream($fh, basename($lpszFileName))) ? false : true;
		fclose($fh);
		return $ret;
	}
	protected function _parsepngstream($f, $file) {
		// Check signature
		if($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
			$this->Error('Not a PNG file: '.$file);
			return false;
		}
		// Read header chunk
		$this->_readstream($f, 4);
		if($this->_readstream($f, 4) != 'IHDR') {
			$this->Error('Incorrect PNG file: '.$file);
			return false;
		}
		$w = $this->_readint($f);
		$h = $this->_readint($f);
		$bpc = ord($this->_readstream($f, 1));
		//if($bpc > 8) {
		//	$this->Error('16-bit depth not supported: '.$file);
		//}
		$ct = ord($this->_readstream($f, 1));
		if($ct == 0 || $ct == 4) {
			$colspace = 'DeviceGray';
		} else if($ct == 2 || $ct == 6) {
			$colspace = 'DeviceRGB';
		} else if($ct == 3) {
			$colspace = 'Indexed';
		} else {
			$this->Error('Unknown color type: '.$file);
		}
		if(ord($this->_readstream($f, 1)) != 0) $this->Error('Unknown compression method: '.$file);
		if(ord($this->_readstream($f, 1)) != 0) $this->Error('Unknown filter method: '.$file);
		$interlaced = ord($this->_readstream($f, 1)) != 0 ? true : false;
		$this->_readstream($f, 4);
		$dp = '/Predictor 15 /Colors '.($colspace=='DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;
		// Scan chunks looking for palette, transparency and image data
		// http://www.w3.org/TR/2003/REC-PNG-20031110/#table53
		// http://www.libpng.org/pub/png/book/chapter11.html#png.ch11.div.6
		$pal = '';
		$trns = '';
		$counter = 0;
		do {
			$n = $this->_readint($f);
			$counter += $n;
			$type = $this->_readstream($f,4);

			if($type=='PLTE') {

				// Read palette
				$pal = $this->_readstream($f, $n);
				$this->_readstream($f,4);

			} else if($type == 'tRNS') {

				// Read transparency info
				$t = $this->_readstream($f,$n);
				if($ct == 0) {
					$trns = array(ord(substr($t,1,1)));
				} else if($ct == 2) {
					$trns = array(ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1)));
				} else {
					$pos = strpos($t, chr(0));
					if($pos !== false) $trns = array($pos);
				}
				$this->_readstream($f, 4);

			} else if($type == 'IEND' || $type == 'IDAT' || $counter >= 2048) {

				break;

			} else {

				$this->_readstream($f,$n+4);
			}
		} while($n);
		if($colspace == 'Indexed' && empty($pal)) $this->Error('Missing palette in '.$file);

		$this->info = array(
			'width' => $w,
			'height' => $h,
			'colspace' => $colspace,
			'channels' => $ct,
			'bits' => $bpc,
			'dp' => $dp,
			'palette' => utf8_encode($pal),
			'trans' => $trns,
			'alpha' => $ct >= 4 ? true : false,
			'interlace' => $interlaced,
			'errors' => $this->errors
		);

		return true;
	}

	protected function _readstream($f, $n) {
		// Read n bytes from stream
		$res = '';
		while($n > 0 && !feof($f)) {
			$s = fread($f, $n);
			if($s === false) {
				$this->Error('Error while reading stream');
				return;
			}
			$n -= strlen($s);
			$res .= $s;
		}
		if($n > 0) $this->Error('Unexpected end of stream');
		return $res;
	}
	protected function _readint($f) {
		// Read a 4-byte integer from stream
		$a = unpack('Ni',$this->_readstream($f,4));
		return $a['i'];
	}
	protected function Error($msg) {
		$this->errors[] = $msg;
	}

}