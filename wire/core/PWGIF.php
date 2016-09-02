<?php namespace ProcessWire;
///////////////////////////////////////////////////////////////////////////////////////////////////
// GIF Util - (C) 2003 Yamasoft (S/C)
// http://www.yamasoft.com
// All Rights Reserved
// This file can be freely copied, distributed, modified, updated by anyone under the only
// condition to leave the original address (Yamasoft, http://www.yamasoft.com) and this header.
///////////////////////////////////////////////////////////////////////////////////////////////////
// GIF Inspector - April 2016 - Horst Nogajski
// Original code by Fabien Ezber, modified by Horst Nogajski, to be used for image inspection only
// for ProcessWire 3+ (http://processwire.com/)
///////////////////////////////////////////////////////////////////////////////////////////////////
class PWGIFLZW {
	public $MAX_LZW_BITS;
	public $Fresh, $CodeSize, $SetCodeSize, $MaxCode, $MaxCodeSize, $FirstCode, $OldCode;
	public $ClearCode, $EndCode, $Next, $Vals, $Stack, $sp, $Buf, $CurBit, $LastBit, $Done, $LastByte;
	public function __construct() {
		$this->MAX_LZW_BITS = 12;
		unSet($this->Next);
		unSet($this->Vals);
		unSet($this->Stack);
		unSet($this->Buf);
		$this->Next  = range(0, (1 << $this->MAX_LZW_BITS)	   - 1);
		$this->Vals  = range(0, (1 << $this->MAX_LZW_BITS)	   - 1);
		$this->Stack = range(0, (1 << ($this->MAX_LZW_BITS + 1)) - 1);
		$this->Buf   = range(0, 279);
	}
	function deCompress($data, &$datLen) {
		$stLen  = strlen($data);
		$datLen = 0;
		$ret	= '';
		// INITIALIZATION
		$this->LZWCommand($data, true);
		while(($iIndex = $this->LZWCommand($data, false)) >= 0) {
			$ret .= chr($iIndex);
		}
		$datLen = $stLen - strlen($data);
		if($iIndex != -2) {
			return false;
		}
		return $ret;
	}
	function LZWCommand(&$data, $bInit) {
		if($bInit) {
			$this->SetCodeSize = ord($data{0});
			$data = substr($data, 1);
			$this->CodeSize	= $this->SetCodeSize + 1;
			$this->ClearCode   = 1 << $this->SetCodeSize;
			$this->EndCode	 = $this->ClearCode + 1;
			$this->MaxCode	 = $this->ClearCode + 2;
			$this->MaxCodeSize = $this->ClearCode << 1;
			$this->GetCode($data, $bInit);
			$this->Fresh = 1;
			for($i = 0; $i < $this->ClearCode; $i++) {
				$this->Next[$i] = 0;
				$this->Vals[$i] = $i;
			}
			for(; $i < (1 << $this->MAX_LZW_BITS); $i++) {
				$this->Next[$i] = 0;
				$this->Vals[$i] = 0;
			}
			$this->sp = 0;
			return 1;
		}
		if($this->Fresh) {
			$this->Fresh = 0;
			do {
				$this->FirstCode = $this->GetCode($data, $bInit);
				$this->OldCode   = $this->FirstCode;
			}
			while($this->FirstCode == $this->ClearCode);
			return $this->FirstCode;
		}
		if($this->sp > 0) {
			$this->sp--;
			return $this->Stack[$this->sp];
		}
		while(($Code = $this->GetCode($data, $bInit)) >= 0) {
			if($Code == $this->ClearCode) {
				for($i = 0; $i < $this->ClearCode; $i++) {
					$this->Next[$i] = 0;
					$this->Vals[$i] = $i;
				}
				for(; $i < (1 << $this->MAX_LZW_BITS); $i++) {
					$this->Next[$i] = 0;
					$this->Vals[$i] = 0;
				}
				$this->CodeSize	= $this->SetCodeSize + 1;
				$this->MaxCodeSize = $this->ClearCode << 1;
				$this->MaxCode	 = $this->ClearCode + 2;
				$this->sp		  = 0;
				$this->FirstCode   = $this->GetCode($data, $bInit);
				$this->OldCode	 = $this->FirstCode;
				return $this->FirstCode;
			}
			if($Code == $this->EndCode) {
				return -2;
			}
			$InCode = $Code;
			if($Code >= $this->MaxCode) {
				$this->Stack[$this->sp] = $this->FirstCode;
				$this->sp++;
				$Code = $this->OldCode;
			}
			while($Code >= $this->ClearCode) {
				$this->Stack[$this->sp] = $this->Vals[$Code];
				$this->sp++;
				if($Code == $this->Next[$Code]) // Circular table entry, big GIF Error!
					return -1;
				$Code = $this->Next[$Code];
			}
			$this->FirstCode = $this->Vals[$Code];
			$this->Stack[$this->sp] = $this->FirstCode;
			$this->sp++;
			if(($Code = $this->MaxCode) < (1 << $this->MAX_LZW_BITS)) {
				$this->Next[$Code] = $this->OldCode;
				$this->Vals[$Code] = $this->FirstCode;
				$this->MaxCode++;
				if(($this->MaxCode >= $this->MaxCodeSize) && ($this->MaxCodeSize < (1 << $this->MAX_LZW_BITS))) {
					$this->MaxCodeSize *= 2;
					$this->CodeSize++;
				}
			}
			$this->OldCode = $InCode;
			if($this->sp > 0) {
				$this->sp--;
				return $this->Stack[$this->sp];
			}
		}
		return $Code;
	}
	function GetCode(&$data, $bInit) {
		if($bInit) {
			$this->CurBit   = 0;
			$this->LastBit  = 0;
			$this->Done	 = 0;
			$this->LastByte = 2;
			return 1;
		}
		if(($this->CurBit + $this->CodeSize) >= $this->LastBit) {
			if($this->Done) {
				if($this->CurBit >= $this->LastBit) {
					// Ran off the end of my bits
					return 0;
				}
				return -1;
			}
			$this->Buf[0] = $this->Buf[$this->LastByte - 2];
			$this->Buf[1] = $this->Buf[$this->LastByte - 1];
			$Count = ord($data{0});
			$data  = substr($data, 1);
			if($Count) {
				for($i = 0; $i < $Count; $i++) {
					$this->Buf[2 + $i] = ord($data{$i});
				}
				$data = substr($data, $Count);
			} else {
				$this->Done = 1;
			}
			$this->LastByte = 2 + $Count;
			$this->CurBit   = ($this->CurBit - $this->LastBit) + 16;
			$this->LastBit  = (2 + $Count) << 3;
		}
		$iRet = 0;
		for($i = $this->CurBit, $j = 0; $j < $this->CodeSize; $i++, $j++) {
			$iRet |= (($this->Buf[intval($i / 8)] & (1 << ($i % 8))) != 0) << $j;
		}
		$this->CurBit += $this->CodeSize;
		return $iRet;
	}
}
class PWGIFCOLORTABLE {
	public $m_nColors;
	public $m_arColors;
	protected $extended;
	public function __construct($extended = false) {
		unSet($this->m_nColors);
		unSet($this->m_arColors);
		$this->extended = $extended;
	}
	public function load($lpData, $num) {
		$this->m_nColors  = 0;
		$this->m_arColors = array();
		for($i = 0; $i < $num; $i++) {
			$rgb = substr($lpData, $i * 3, 3);
			if(strlen($rgb) < 3) {
				return false;
			}
			if($this->extended) {
				$this->m_arColors[] = (ord($rgb{2}) << 16) + (ord($rgb{1}) << 8) + ord($rgb{0});
			}
			$this->m_nColors++;
		}
		return true;
	}
	public function toString() {
		$ret = '';
		for($i = 0; $i < $this->m_nColors; $i++) {
			$ret .=
				chr(($this->m_arColors[$i] & 0x000000FF))	   . // R
				chr(($this->m_arColors[$i] & 0x0000FF00) >>  8) . // G
				chr(($this->m_arColors[$i] & 0x00FF0000) >> 16);  // B
		}
		return $ret;
	}
	public function toRGBQuad() {
		$ret = '';
		for($i = 0; $i < $this->m_nColors; $i++) {
			$ret .=
				chr(($this->m_arColors[$i] & 0x00FF0000) >> 16) . // B
				chr(($this->m_arColors[$i] & 0x0000FF00) >>  8) . // G
				chr(($this->m_arColors[$i] & 0x000000FF))	   . // R
				"\x00";
		}
		return $ret;
	}
}
class PWGIFFILEHEADER {
	public $m_lpVer;
	public $m_nWidth;
	public $m_nHeight;
	public $m_bGlobalClr;
	public $m_nColorRes;
	public $m_bSorted;
	public $m_nTableSize;
	public $m_nBgColor;
	public $m_nPixelRatio;
	public $m_colorTable;
	public $m_bAnimated;   // @Horst: added property
	protected $extended;
	public function __construct($extended = false) {
		unSet($this->m_lpVer);
		unSet($this->m_nWidth);
		unSet($this->m_nHeight);
		unSet($this->m_bGlobalClr);
		unSet($this->m_nColorRes);
		unSet($this->m_bSorted);
		unSet($this->m_nTableSize);
		unSet($this->m_nBgColor);
		unSet($this->m_nPixelRatio);
		unSet($this->m_colorTable);
		unSet($this->m_bAnimated);
		$this->extended = $extended;
	}
	public function load($lpData, &$hdrLen) {
		$hdrLen = 0;
		$this->m_lpVer = substr($lpData, 0, 6);
		if(($this->m_lpVer <> 'GIF87a') && ($this->m_lpVer <> 'GIF89a')) {
			return false;
		}
		// @Horst: store if we have more then one animation frames
		$this->m_bAnimated = 1 < preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $lpData);
		$this->m_nWidth  = $this->w2i(substr($lpData, 6, 2));
		$this->m_nHeight = $this->w2i(substr($lpData, 8, 2));
		if(!$this->m_nWidth || !$this->m_nHeight) {
			return false;
		}
		$b = ord(substr($lpData, 10, 1));
		$this->m_bGlobalClr  = ($b & 0x80) ? true : false;
		$this->m_nColorRes   = ($b & 0x70) >> 4;
		$this->m_bSorted	 = ($b & 0x08) ? true : false;
		$this->m_nTableSize  = 2 << ($b & 0x07);
		$this->m_nBgColor	= ord(substr($lpData, 11, 1));
		$this->m_nPixelRatio = ord(substr($lpData, 12, 1));
		$hdrLen = 13;
		if($this->m_bGlobalClr) {
			$this->m_colorTable = new PWGIFCOLORTABLE($this->extended);
			$tmp1 = $this->m_nTableSize;
			if(!$this->m_colorTable->load(substr($lpData, $hdrLen), $tmp1)) {
				return false;
			}
			$this->m_nTableSize = $tmp1;
			$hdrLen += 3 * $this->m_nTableSize;
		}
		return true;
	}
	private function w2i($str) {
		return ord(substr($str, 0, 1)) + (ord(substr($str, 1, 1)) << 8);
	}
}
class PWGIFIMAGEHEADER {
	public $m_nLeft;
	public $m_nTop;
	public $m_nWidth;
	public $m_nHeight;
	public $m_bLocalClr;
	public $m_bInterlace;
	public $m_bSorted;
	public $m_nTableSize;
	public $m_colorTable;
	protected $extended;
	public function __construct($extended = false) {
		unSet($this->m_nLeft);
		unSet($this->m_nTop);
		unSet($this->m_nWidth);
		unSet($this->m_nHeight);
		unSet($this->m_bLocalClr);
		unSet($this->m_bInterlace);
		unSet($this->m_bSorted);
		unSet($this->m_nTableSize);
		unSet($this->m_colorTable);
		$this->extended = $extended;
	}
	public function load($lpData, &$hdrLen) {
		$hdrLen = 0;
		$this->m_nLeft   = $this->w2i(substr($lpData, 0, 2));
		$this->m_nTop	= $this->w2i(substr($lpData, 2, 2));
		$this->m_nWidth  = $this->w2i(substr($lpData, 4, 2));
		$this->m_nHeight = $this->w2i(substr($lpData, 6, 2));
		if(!$this->m_nWidth || !$this->m_nHeight) {
			return false;
		}
		$b = ord($lpData{8});
		$this->m_bLocalClr  = ($b & 0x80) ? true : false;
		$this->m_bInterlace = ($b & 0x40) ? true : false;
		$this->m_bSorted	= ($b & 0x20) ? true : false;
		$this->m_nTableSize = 2 << ($b & 0x07);
		$hdrLen = 9;
		if($this->m_bLocalClr) {
			$this->m_colorTable = new PWGIFCOLORTABLE($this->extended);
			if(!$this->m_colorTable->load(substr($lpData, $hdrLen), $this->m_nTableSize)) {
				return false;
			}
			$hdrLen += 3 * $this->m_nTableSize;
		}
		return true;
	}
	private function w2i($str) {
		return ord(substr($str, 0, 1)) + (ord(substr($str, 1, 1)) << 8);
	}
}
class PWGIFIMAGE {
	public $m_disp;
	public $m_bUser;
	public $m_bTrans;
	public $m_nDelay;
	public $m_nTrans;
	public $m_lpComm;
	public $m_gih;
	public $m_data;
	public $m_lzw;
	protected $extended;	  // @Horst: added flag
	public function __construct($extended = false) {
		unSet($this->m_disp);
		unSet($this->m_bUser);
		unSet($this->m_bTrans);
		unSet($this->m_nDelay);
		unSet($this->m_nTrans);
		unSet($this->m_lpComm);
		unSet($this->m_data);
		$this->m_gih = new PWGIFIMAGEHEADER($extended);
		if($extended) $this->m_lzw = new PWGIFLZW();
		$this->extended = $extended;
	}
	public function load($data, &$datLen) {
		$datLen = 0;
		while(true) {
			$b = ord($data{0});
			$data = substr($data, 1);
			$datLen++;
			switch($b) {
				case 0x21: // Extension
					$len = 0;
					if(!$this->skipExt($data, $len)) {
						return false;
					}
					$datLen += $len;
					break;
				case 0x2C: // Image
					// LOAD HEADER & COLOR TABLE
					$len = 0;
					if(!$this->m_gih->load($data, $len)) {
						return false;
					}
					$data = substr($data, $len);
					$datLen += $len;
					// @Horst: early return, because we only want to inspect the image,
					// not alter its bitmap data
					if(!$this->extended) {
						return true;
					}
					// ALLOC BUFFER
					$len = 0;
					if(!($this->m_data = $this->m_lzw->deCompress($data, $len))) {
						return false;
					}
					$data = substr($data, $len);
					$datLen += $len;
					if($this->m_gih->m_bInterlace) {
						$this->deInterlace();
					}
					return true;
				case 0x3B: // EOF
				default:
					return false;
			}
		}
		return false;
	}
	function skipExt(&$data, &$extLen) {
		$extLen = 0;
		$b = ord($data{0});
		$data = substr($data, 1);
		$extLen++;
		switch($b) {
			case 0xF9: // Graphic Control
				$b = ord($data{1});
				$this->m_disp   = ($b & 0x1C) >> 2;
				$this->m_bUser  = ($b & 0x02) ? true : false;
				$this->m_bTrans = ($b & 0x01) ? true : false;
				$this->m_nDelay = $this->w2i(substr($data, 2, 2));
				$this->m_nTrans = ord($data{4});
				break;
			case 0xFE: // Comment
				$this->m_lpComm = substr($data, 1, ord($data{0}));
				break;
			case 0x01: // Plain text
				break;
			case 0xFF: // Application
				break;
		}
		// SKIP DEFAULT AS DEFS MAY CHANGE
		$b = ord($data{0});
		$data = substr($data, 1);
		$extLen++;
		while($b > 0) {
			$data = substr($data, $b);
			$extLen += $b;
			$b	= ord($data{0});
			$data = substr($data, 1);
			$extLen++;
		}
		return true;
	}
	private function w2i($str) {
		return ord(substr($str, 0, 1)) + (ord(substr($str, 1, 1)) << 8);
	}
	function deInterlace() {
		$data = $this->m_data;
		for($i = 0; $i < 4; $i++) {
			switch($i) {
				case 0:
					$s = 8;
					$y = 0;
					break;
				case 1:
					$s = 8;
					$y = 4;
					break;
				case 2:
					$s = 4;
					$y = 2;
					break;
				case 3:
					$s = 2;
					$y = 1;
					break;
			}
			for(; $y < $this->m_gih->m_nHeight; $y += $s) {
				$lne = substr($this->m_data, 0, $this->m_gih->m_nWidth);
				$this->m_data = substr($this->m_data, $this->m_gih->m_nWidth);
				$data =
					substr($data, 0, $y * $this->m_gih->m_nWidth) .
					$lne .
					substr($data, ($y + 1) * $this->m_gih->m_nWidth);
			}
		}
		$this->m_data = $data;
	}
}
class PWGIF {
	public $m_gfh;
	public $m_lpData;
	public $m_img;
	public $m_bLoaded;
	// @Horst: added param $extended
	//  - true = it also loads and parse Bitmapdata
	//  - false = it only loads Headerdata
	public function __construct($extended = false) {
		$this->m_gfh	 = new PWGIFFILEHEADER($extended);
		$this->m_img	 = new PWGIFIMAGE($extended);
		$this->m_lpData  = '';
		$this->m_bLoaded = false;
	}
	public function loadFile($lpszFileName, $iIndex) {
		if($iIndex < 0) {
			return false;
		}
		// READ FILE
		if(!($fh = @fopen($lpszFileName, 'rb'))) {
			return false;
		}
		$this->m_lpData = @fRead($fh, @fileSize($lpszFileName));
		fclose($fh);
		// GET FILE HEADER
		$len = 0;
		if(!$this->m_gfh->load($this->m_lpData, $len)) {
			return false;
		}
		$this->m_lpData = substr($this->m_lpData, $len);
		do {
			$imgLen = 0;
			if(!$this->m_img->load($this->m_lpData, $imgLen)) {
				return false;
			}
			$this->m_lpData = substr($this->m_lpData, $imgLen);
		}
		while($iIndex-- > 0);
		$this->m_bLoaded = true;
		return true;
	}
}