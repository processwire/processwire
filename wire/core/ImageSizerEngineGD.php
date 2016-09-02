<?php namespace ProcessWire;

/**
 * ProcessWire ImageSizerGD
 *
 * Code for IPTC, auto rotation and sharpening by Horst Nogajski.
 * http://nogajski.de/
 *
 * Other user contributions as noted.
 *
 * Copyright (C) 2016 by Horst Nogajski and Ryan Cramer
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 * https://processwire.com
 *
 */
class ImageSizerEngineGD extends ImageSizerEngine {

	/**
	 * @var string
	 * 
	 */
	protected $imageFormat;

	/**
	 * @var int ?
	 *
	 */
	protected $imageDepth;

	/**
	 * @var bool
	 * 
	 */
	protected $gammaLinearized;

	/**
	 * Get formats GD and resize
	 * 
	 * @return array
	 * 
	 */
	protected function validSourceImageFormats() {
		return array('JPG', 'JPEG', 'PNG', 'GIF');
	}

	/**
	 * Return whether or not GD can proceed - Is the current image(sub)format supported?
	 *
	 * @param string $action
	 * @return bool
	 *
	 */
	public function supported($action = 'imageformat') {
		// first we check parts that are mandatory for all $actions
		if(!function_exists('gd_info')) return false;
		// and if it passes the mandatory requirements, we check particularly aspects here
		
		switch($action) {
			case 'imageformat':
				// compare current imagefile infos fetched from ImageInspector
				$requested = $this->getImageInfo(false);
				switch($requested) {
					case 'gif-anim':
					case 'gif-trans-anim':
						// Animated GIF images are not supported, but GD renders the first image of the animation
						#return false;
					default:
						return true;
				}
				break;

			case 'install':
				/*
				$gd  = gd_info();
				$jpg = isset($gd['JPEG Support']) ? $gd['JPEG Support'] : false;
				$png = isset($gd['PNG Support']) ? $gd['PNG Support'] : false;
				$gif = isset($gd['GIF Read Support']) && isset($gd['GIF Create Support']) ? $gd['GIF Create Support'] : false;
				$freetype = isset($gd['FreeType Support']) ? $gd['FreeType Support'] : false;
				$this->config->gdReady = true;
				*/
				return true;

			default:
				return false;
		}
	}

	/**
	 * Process the image resize
	 * 
	 * @param string $srcFilename Source file
	 * @param string $dstFilename Destination file
	 * @param int $fullWidth Current width
	 * @param int $fullHeight Current height
	 * @param int $finalWidth Requested final width
	 * @param int $finalHeight Requested final height
	 * @return bool
	 * @throws WireException
	 * 
	 */
	protected function processResize($srcFilename, $dstFilename, $fullWidth, $fullHeight, $finalWidth, $finalHeight) {
		
		$this->modified = false;
		if(isset($this->info['bits'])) $this->imageDepth = $this->info['bits'];
		$this->imageFormat = strtoupper(str_replace('image/', '', $this->info['mime']));

		if(!in_array($this->imageFormat, $this->validSourceImageFormats())) {
			throw new WireException(sprintf($this->_("loaded file '%s' is not in the list of valid images", basename($dstFilename))));
		}

		$image = null;
		$orientations = null; // @horst
		$needRotation = $this->autoRotation !== true ? false : ($this->checkOrientation($orientations) &&
		(!empty($orientations[0]) || !empty($orientations[1])) ? true : false);

		// check if we can load the sourceimage into ram
		if(self::checkMemoryForImage(array($this->info['width'], $this->info['height'], $this->info['channels'])) === false) {
			throw new WireException(basename($srcFilename) . " - not enough memory to load");
		}

		switch($this->imageType) { // @teppo
			case \IMAGETYPE_GIF:
				$image = @imagecreatefromgif($srcFilename);
				break;
			case \IMAGETYPE_PNG:
				$image = @imagecreatefrompng($srcFilename);
				break;
			case \IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg($srcFilename);
				break;
		}

		if(!$image) return false;

		if($this->imageType != \IMAGETYPE_PNG || !$this->hasAlphaChannel()) {
			// @horst: linearize gamma to 1.0 - we do not use gamma correction with pngs containing alphachannel, because GD-lib doesn't respect transparency here (is buggy)
			$this->gammaCorrection($image, true);
		}

		if($this->rotate || $needRotation) { // @horst
			$degrees = $this->rotate ? $this->rotate : $orientations[0];
			$image = $this->imRotate($image, $degrees);
			if(abs($degrees) == 90 || abs($degrees) == 270) {
				// we have to swap width & height now!
				$tmp = array($this->getWidth(), $this->getHeight());
				$this->setImageInfo($tmp[1], $tmp[0]);
			}
		}

		if($this->flip || $needRotation) {
			$vertical = null;
			if($this->flip) {
				$vertical = $this->flip == 'v';
			} else if($orientations[1] > 0) {
				$vertical = $orientations[1] == 2;
			}
			if(!is_null($vertical)) $image = $this->imFlip($image, $vertical);
		}

		// if there is requested to crop _before_ resize, we do it here @horst
		if(is_array($this->cropExtra)) {
			// check if we can load a second copy from sourceimage into ram
			if(self::checkMemoryForImage(array($this->info['width'], $this->info['height'], 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to load a copy for cropExtra");
			}

			$imageTemp = imagecreatetruecolor(imagesx($image), imagesy($image));  // create an intermediate memory image
			$this->prepareImageLayer($imageTemp, $image);
			imagecopy($imageTemp, $image, 0, 0, 0, 0, imagesx($image), imagesy($image)); // copy our initial image into the intermediate one
			imagedestroy($image); // release the initial image

			// get crop values and create a new initial image
			list($x, $y, $w, $h) = $this->cropExtra;

			// check if we can load a cropped version into ram
			if(self::checkMemoryForImage(array($w, $h, 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to load a cropped version for cropExtra");
			}

			$image = imagecreatetruecolor($w, $h);
			$this->prepareImageLayer($image, $imageTemp);
			imagecopy($image, $imageTemp, 0, 0, $x, $y, $w, $h);
			unset($x, $y, $w, $h);

			// now release the intermediate image and update settings
			imagedestroy($imageTemp);
			$this->setImageInfo(imagesx($image), imagesy($image));
			// $this->cropping = false; // ?? set this to prevent overhead with the following manipulation ??
		}

		// here we check for cropping, upscaling, sharpening
		// we get all dimensions at first, before any image operation !
		$bgX = $bgY = 0;
		$bgWidth = $fullWidth;
		$bgHeight = $fullHeight;
		$resizeMethod = $this->getResizeMethod($bgWidth, $bgHeight, $finalWidth, $finalHeight, $bgX, $bgY);
		$thumb = null;

		// now lets check what operations are necessary:
		if(0 == $resizeMethod) {

			// this is the case if the original size is requested or a greater size but upscaling is set to false

			// the current version is allready the desired result, we only may have to apply compression where possible
			$this->sharpening = 'none'; // we set sharpening to none

			if(self::checkMemoryForImage(array(imagesx($image), imagesy($image), 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to copy the final image");
			}
			$thumb = imagecreatetruecolor(imagesx($image), imagesy($image));          // create the final memory image
			$this->prepareImageLayer($thumb, $image);
			imagecopy($thumb, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));  // copy our intermediate image into the final one

		} else if(2 == $resizeMethod) { // 2 = resize with aspect ratio

			// this is the case if we scale up or down _without_ cropping

			if(self::checkMemoryForImage(array($finalWidth, $finalHeight, 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to resize to the final image");
			}

			$thumb = imagecreatetruecolor($finalWidth, $finalHeight);
			$this->prepareImageLayer($thumb, $image);
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $finalWidth, $finalHeight, $this->image['width'], $this->image['height']);

		} else if(4 == $resizeMethod) { // 4 = resize and crop with aspect ratio

			// we have to scale up or down and to _crop_

			if(self::checkMemoryForImage(array($bgWidth, $bgHeight, 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to resize to the intermediate image");
			}

			$thumb2 = imagecreatetruecolor($bgWidth, $bgHeight);
			$this->prepareImageLayer($thumb2, $image);
			imagecopyresampled($thumb2, $image, 0, 0, 0, 0, $bgWidth, $bgHeight, $this->image['width'], $this->image['height']);

			if(self::checkMemoryForImage(array($finalWidth, $finalHeight, 3)) === false) {
				throw new WireException(basename($srcFilename) . " - not enough memory to crop to the final image");
			}

			$thumb = imagecreatetruecolor($finalWidth, $finalHeight);
			$this->prepareImageLayer($thumb, $image);
			imagecopyresampled($thumb, $thumb2, 0, 0, $bgX, $bgY, $finalWidth, $finalHeight, $finalWidth, $finalHeight);
			imagedestroy($thumb2);
		}

		// early release of obsolete GD image object(s) to free memory before processing sharpening
		if(isset($image) && is_resource($image)) @imagedestroy($image); // @horst
		if(isset($thumb2) && is_resource($thumb2)) @imagedestroy($thumb2);
		if(isset($image)) $image = null;
		if(isset($thumb2)) $thumb2 = null;

		// optionally apply sharpening to the final thumb
		if($this->sharpening && $this->sharpening != 'none') { // @horst
			if(\IMAGETYPE_PNG != $this->imageType || !$this->hasAlphaChannel()) {
				$w = imagesx($thumb);
				$h = imagesy($thumb);
				if($this->useUSM) {
					// calculate if there is enough memory available to apply the USM algorithm, if enabled
					if(true === ($this->useUSM = self::checkMemoryForImage(array($w, $h, 3), array($w, $h, 3)))) {
						// is needed for the USM sharpening function to calculate the best sharpening params
						$this->usmValue = $this->calculateUSMfactor($finalWidth, $finalHeight);
						$thumb = $this->imSharpen($thumb, $this->sharpening);
					}
				}
				if(!$this->useUSM) {
					if(false !== self::checkMemoryForImage(array($w, $h, 3))) {
						$thumb = $this->imSharpen($thumb, $this->sharpening);
					}
				}
			}
		}

		// write to file
		$result = false;
		switch($this->imageType) {
			case \IMAGETYPE_GIF:
				// correct gamma from linearized 1.0 back to 2.0
				$this->gammaCorrection($thumb, false);
				$result = imagegif($thumb, $dstFilename);
				break;
			case \IMAGETYPE_PNG:
				if(!$this->hasAlphaChannel()) $this->gammaCorrection($thumb, false);
				// always use highest compression level for PNG (9) per @horst
				$result = imagepng($thumb, $dstFilename, 9);
				break;
			case \IMAGETYPE_JPEG:
				// correct gamma from linearized 1.0 back to 2.0
				$this->gammaCorrection($thumb, false);
				$result = imagejpeg($thumb, $dstFilename, $this->quality);
				break;
		}

		// release the last GD image object
		if(isset($thumb) && is_resource($thumb)) @imagedestroy($thumb);
		if(isset($thumb)) $thumb = null;

		return $result;
	}

	/**
	 * Rotate image (@horst)
	 *
	 * @param resource $im
	 * @param int $degree
	 *
	 * @return resource
	 *
	 */
	protected function imRotate($im, $degree) {
		$degree = (is_float($degree) || is_int($degree)) && $degree > -361 && $degree < 361 ? $degree : false;
		if($degree === false) return $im;
		if(in_array($degree, array(-360, 0, 360))) return $im;
		return @imagerotate($im, $degree, imagecolorallocate($im, 0, 0, 0));
	}

	/**
	 * Flip image (@horst)
	 *
	 * @param resource $im
	 * @param bool $vertical (default = false)
	 *
	 * @return resource
	 *
	 */
	protected function imFlip($im, $vertical = false) {
		$sx = imagesx($im);
		$sy = imagesy($im);
		$im2 = @imagecreatetruecolor($sx, $sy);
		if($vertical === true) {
			@imagecopyresampled($im2, $im, 0, 0, 0, ($sy - 1), $sx, $sy, $sx, 0 - $sy);
		} else {
			@imagecopyresampled($im2, $im, 0, 0, ($sx - 1), 0, $sx, $sy, 0 - $sx, $sy);
		}
		return $im2;
	}

	/**
	 * Sharpen image (@horst)
	 *
	 * @param resource $im
	 * @param string $mode May be: none | soft | medium | strong
	 *
	 * @return resource|bool
	 *
	 */
	protected function imSharpen($im, $mode) {

		// due to a bug in PHP's bundled GD-Lib with the function imageconvolution in some PHP versions
		// we have to bypass this for those who have to run on this PHP versions
		// see: https://bugs.php.net/bug.php?id=66714
		// and here under GD: http://php.net/ChangeLog-5.php#5.5.11
		$buggyPHP = (version_compare(phpversion(), '5.5.8', '>') && version_compare(phpversion(), '5.5.11', '<')) ? true : false;
		if($buggyPHP && !$this->useUSM
			&& self::checkMemoryForImage(array(imagesx($im), imagesy($im), 3), array(imagesx($im), imagesy($im), 3)) !== true
		) {
			// we have not enough memory available for USM and cannot use the other algorithm because of the buggy PHP version
			return $im;
		}

		// USM method is used for buggy PHP versions
		// for regular versions it can be omitted per: useUSM = false passes as pageimage option
		// or set in the site/config.php under $config->imageSizerOptions: 'useUSM' => false | true
		if($buggyPHP || $this->useUSM) {

			switch($mode) {

				case 'none':
					return $im;
					break;

				case 'strong':
					$amount = 160;
					$radius = 1.0;
					$threshold = 7;
					break;

				case 'medium':
					$amount = 130;
					$radius = 0.75;
					$threshold = 7;
					break;

				case 'soft':

				default:
					$amount = 100;
					$radius = 0.5;
					$threshold = 7;
			}

			// calculate the final amount according to the usmValue
			$this->usmValue = $this->usmValue < 0 ? 0 : ($this->usmValue > 100 ? 100 : $this->usmValue);
			if(0 == $this->usmValue) return $im;
			$amount = intval($amount / 100 * $this->usmValue);

			// apply unsharp mask filter
			return $this->UnsharpMask($im, $amount, $radius, $threshold);
		}

		// if we do not use USM, we use our default sharpening method,
		// entirely based on GDs imageconvolution
		switch($mode) {

			case 'none':
				return $im;
				break;

			case 'strong':
				$sharpenMatrix = array(
					array(-1.2, -1, -1.2),
					array(-1, 16, -1),
					array(-1.2, -1, -1.2)
				);
				break;

			case 'medium':
				$sharpenMatrix = array(
					array(-1.1, -1, -1.1),
					array(-1, 20, -1),
					array(-1.1, -1, -1.1)
				);
				break;

			case 'soft':

			default:
				$sharpenMatrix = array(
					array(-1, -1, -1),
					array(-1, 24, -1),
					array(-1, -1, -1)
				);
		}

		// calculate the sharpen divisor
		$divisor = array_sum(array_map('array_sum', $sharpenMatrix));
		$offset = 0;

		// TODO 4 -c errorhandling: Throw WireException?
		if(!imageconvolution($im, $sharpenMatrix, $divisor, $offset)) return false;

		return $im;
	}


	/**
	 * apply GammaCorrection to an image (@horst)
	 *
	 * with mode = true it linearizes an image to 1
	 * with mode = false it set it back to the originating gamma value
	 *
	 * @param GD -image-resource $image
	 * @param bool $mode
	 *
	 */
	protected function gammaCorrection(&$image, $mode) {
		if(-1 == $this->defaultGamma || !is_bool($mode)) return;
		if($mode) {
			// linearizes to 1.0
			if(imagegammacorrect($image, $this->defaultGamma, 1.0)) $this->gammaLinearized = true;
		} else {
			if(!isset($this->gammaLinearized) || !$this->gammaLinearized) return;
			// switch back to original Gamma
			if(imagegammacorrect($image, 1.0, $this->defaultGamma)) unset($this->gammaLinearized);
		}
	}

	/**
	 * Unsharp Mask for PHP - version 2.1.1
	 *
	 * Unsharp mask algorithm by Torstein Hønsi 2003-07.
	 * thoensi_at_netcom_dot_no.
	 * Please leave this notice.
	 *
	 * http://vikjavev.no/computing/ump.php
	 * 
	 * @param resource $img
	 * @param int $amount
	 * @param int $radius
	 * @param int $threshold
	 * @return resource
	 *
	 */
	protected function unsharpMask($img, $amount, $radius, $threshold) {
		// Attempt to calibrate the parameters to Photoshop:
		if($amount > 500) $amount = 500;
		$amount = $amount * 0.016;
		if($radius > 50) $radius = 50;
		$radius = $radius * 2;
		if($threshold > 255) $threshold = 255;

		$radius = abs(round($radius));     // Only integers make sense.
		if($radius == 0) {
			return $img;
		}
		$w = imagesx($img);
		$h = imagesy($img);
		$imgCanvas = imagecreatetruecolor($w, $h);
		$imgBlur = imagecreatetruecolor($w, $h);

		// due to a bug in PHP's bundled GD-Lib with the function imageconvolution in some PHP versions
		// we have to bypass this for those who have to run on this PHP versions
		// see: https://bugs.php.net/bug.php?id=66714
		// and here under GD: http://php.net/ChangeLog-5.php#5.5.11
		$buggyPHP = (version_compare(phpversion(), '5.5.8', '>') && version_compare(phpversion(), '5.5.11', '<')) ? true : false;

		// Gaussian blur matrix:
		//
		//    1    2    1
		//    2    4    2
		//    1    2    1
		//
		//////////////////////////////////////////////////
		if(function_exists('imageconvolution') && !$buggyPHP) {
			$matrix = array(
				array(1, 2, 1),
				array(2, 4, 2),
				array(1, 2, 1)
			);
			imagecopy($imgBlur, $img, 0, 0, 0, 0, $w, $h);
			imageconvolution($imgBlur, $matrix, 16, 0);
		} else {
			// Move copies of the image around one pixel at the time and merge them with weight
			// according to the matrix. The same matrix is simply repeated for higher radii.
			for($i = 0; $i < $radius; $i++) {
				imagecopy($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left
				imagecopymerge($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right
				imagecopymerge($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center
				imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

				imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333); // up
				imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
			}
		}

		if($threshold > 0) {
			// Calculate the difference between the blurred pixels and the original
			// and set the pixels
			for($x = 0; $x < $w - 1; $x++) { // each row
				for($y = 0; $y < $h; $y++) { // each pixel

					$rgbOrig = ImageColorAt($img, $x, $y);
					$rOrig = (($rgbOrig >> 16) & 0xFF);
					$gOrig = (($rgbOrig >> 8) & 0xFF);
					$bOrig = ($rgbOrig & 0xFF);

					$rgbBlur = ImageColorAt($imgBlur, $x, $y);

					$rBlur = (($rgbBlur >> 16) & 0xFF);
					$gBlur = (($rgbBlur >> 8) & 0xFF);
					$bBlur = ($rgbBlur & 0xFF);

					// When the masked pixels differ less from the original
					// than the threshold specifies, they are set to their original value.
					$rNew = (abs($rOrig - $rBlur) >= $threshold)
						? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))
						: $rOrig;
					$gNew = (abs($gOrig - $gBlur) >= $threshold)
						? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))
						: $gOrig;
					$bNew = (abs($bOrig - $bBlur) >= $threshold)
						? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))
						: $bOrig;

					if(($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
						$pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);
						ImageSetPixel($img, $x, $y, $pixCol);
					}
				}
			}
		} else {
			for($x = 0; $x < $w; $x++) { // each row
				for($y = 0; $y < $h; $y++) { // each pixel
					$rgbOrig = ImageColorAt($img, $x, $y);
					$rOrig = (($rgbOrig >> 16) & 0xFF);
					$gOrig = (($rgbOrig >> 8) & 0xFF);
					$bOrig = ($rgbOrig & 0xFF);

					$rgbBlur = ImageColorAt($imgBlur, $x, $y);

					$rBlur = (($rgbBlur >> 16) & 0xFF);
					$gBlur = (($rgbBlur >> 8) & 0xFF);
					$bBlur = ($rgbBlur & 0xFF);

					$rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
					if($rNew > 255) {
						$rNew = 255;
					} else if($rNew < 0) {
						$rNew = 0;
					}
					$gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
					if($gNew > 255) {
						$gNew = 255;
					} else if($gNew < 0) {
						$gNew = 0;
					}
					$bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
					if($bNew > 255) {
						$bNew = 255;
					} else if($bNew < 0) {
						$bNew = 0;
					}
					$rgbNew = ($rNew << 16) + ($gNew << 8) + $bNew;
					ImageSetPixel($img, $x, $y, $rgbNew);
				}
			}
		}
		imagedestroy($imgCanvas);
		imagedestroy($imgBlur);

		return $img;
	}


	/**
	 * Calculate USM factor
	 *
	 * Return an integer value indicating how much an image should be sharpened
	 * according to resizing scalevalue and absolute target dimensions
	 *
	 * @param mixed $targetWidth width of the targetimage
	 * @param mixed $targetHeight height of the targetimage
	 * @param mixed $origWidth
	 * @param mixed $origHeight
	 *
	 * @return int
	 *
	 */
	protected function calculateUSMfactor($targetWidth, $targetHeight, $origWidth = null, $origHeight = null) {

		if(null === $origWidth) $origWidth = $this->getWidth();
		if(null === $origHeight) $origHeight = $this->getHeight();
		
		$w = ceil($targetWidth / $origWidth * 100);
		$h = ceil($targetHeight / $origHeight * 100);
		
		$resizingScalevalue = null;
		$target = null;
		$res = null;

		// select the resizing scalevalue with check for crop images
		if($w == $h || ($w - 1) == $h || ($w + 1) == $h) {  // equal, no crop
			$resizingScalevalue = $w;
			$target = $targetWidth;
		} else { // crop
			if(($w < $h && $w < 100) || ($w > $h && $h >= 100)) {
				$resizingScalevalue = $w;
				$target = $targetWidth;
			} elseif(($w < $h && $w >= 100) || ($w > $h && $h < 100)) {
				$resizingScalevalue = $h;
				$target = $targetHeight;
			}
		}

		// adjusting with respect to the scalefactor
		$resizingScalevalue = ($resizingScalevalue * -1) + 100;
		$resizingScalevalue = $resizingScalevalue < 0 ? $resizingScalevalue * -1 : $resizingScalevalue;

		if($resizingScalevalue > 0 && $resizingScalevalue < 10) $resizingScalevalue += 15;
			else if($resizingScalevalue > 9 && $resizingScalevalue < 25) $resizingScalevalue += 20;
			else if($resizingScalevalue > 24 && $resizingScalevalue < 40) $resizingScalevalue += 35;
			else if($resizingScalevalue > 39 && $resizingScalevalue < 55) $resizingScalevalue += 20;
			else if($resizingScalevalue > 54 && $resizingScalevalue < 70) $resizingScalevalue += 5;
			else if($resizingScalevalue > 69 && $resizingScalevalue < 80) $resizingScalevalue -= 10;

		// adjusting with respect to absolute dimensions
		if($target < 50) $res = intval($resizingScalevalue / 18 * 3);
			else if($target < 100) $res = intval($resizingScalevalue / 18 * 4);
			else if($target < 200) $res = intval($resizingScalevalue / 18 * 6);
			else if($target < 300) $res = intval($resizingScalevalue / 18 * 8);
			else if($target < 400) $res = intval($resizingScalevalue / 18 * 10);
			else if($target < 500) $res = intval($resizingScalevalue / 18 * 12);
			else if($target < 600) $res = intval($resizingScalevalue / 18 * 15);
			else if($target > 599) $res = $resizingScalevalue;

		$res = $res < 0 ? $res * -1 : $res; // avoid negative numbers

		return $res;
	}


	/**
	 * Prepares a new created GD image resource according to the IMAGETYPE
	 *
	 * Intended for use by the resize() method
	 *
	 * @param GD -resource $im, destination resource needs to be prepared
	 * @param GD -resource $image, with GIF we need to read from source resource
	 *
	 */
	protected function prepareImageLayer(&$im, &$image) {

		if($this->imageType == IMAGETYPE_PNG) {
			// @adamkiss PNG transparency
			imagealphablending($im, false);
			imagesavealpha($im, true);

		} else if($this->imageType == IMAGETYPE_GIF) {
			// @mrx GIF transparency
			$transparentIndex = imagecolortransparent($image);
			$transparentColor = $transparentIndex != -1 ? @imagecolorsforindex($image, $transparentIndex) : 0;
			if(!empty($transparentColor)) {
				$transparentNew = imagecolorallocate($im, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
				$transparentNewIndex = imagecolortransparent($im, $transparentNew);
				imagefill($im, 0, 0, $transparentNewIndex);
			}

		} else {
			$bgcolor = imagecolorallocate($im, 0, 0, 0);
			imagefilledrectangle($im, 0, 0, imagesx($im), imagesy($im), $bgcolor);
			imagealphablending($im, false);
		}
	}


	/**
	 * calculation if there is enough memory available at runtime for loading and resizing an given imagefile
	 *
	 * @param array $sourceDimensions - array with three values: width, height, number of channels
	 * @param array|bool $targetDimensions - optional - mixed: bool true | false or array with three values:
	 *  width, height, number of channels
	 *
	 * @return bool|null if a calculation was possible (true|false), or null if the calculation could not be done
	 *
	 */
	static public function checkMemoryForImage($sourceDimensions, $targetDimensions = false) {

		// with this static we only once need to read from php.ini and calculate phpMaxMem,
		// regardless how often this function is called in a request
		static $phpMaxMem = null;

		if(null === $phpMaxMem) {
			$sMem = trim(strtoupper(ini_get('memory_limit')), ' B'); // trim B just in case it has Mb rather than M
			switch(substr($sMem, -1)) {
				case 'M':
					$phpMaxMem = ((int) $sMem) * 1048576;
					break;
				case 'K':
					$phpMaxMem = ((int) $sMem) * 1024;
					break;
				case 'G':
					$phpMaxMem = ((int) $sMem) * 1073741824;
					break;
				default:
					$phpMaxMem = (int) $sMem;
			}
		}

		if($phpMaxMem <= 0) {
			// we couldn't read the MaxMemorySetting or there isn't one set,
			// so in both cases we do not know if there is enough or not
			return null;
		}

		// calculate $sourceDimensions
		if(!isset($sourceDimensions[0]) || !isset($sourceDimensions[1])
			|| !isset($sourceDimensions[2]) || !is_int($sourceDimensions[0])
			|| !is_int($sourceDimensions[1]) || !is_int($sourceDimensions[2])
		) {
			return null;
		}

		//            width             *        height        *       channels
		$imgMem = ($sourceDimensions[0] * $sourceDimensions[1] * $sourceDimensions[2]);

		if(true === $targetDimensions) {
			// we have to add ram for a copy of the sourceimage
			$imgMem += $imgMem;

		} else if(is_array($targetDimensions)) {
			// we have to add ram for a targetimage
			if(!isset($targetDimensions[0]) || !isset($targetDimensions[1])
				|| !isset($targetDimensions[2]) || !is_int($targetDimensions[0])
				|| !is_int($targetDimensions[1]) || !is_int($targetDimensions[2])
			) {
				return null;
			}

			$imgMem += ($targetDimensions[0] * $targetDimensions[1] * $targetDimensions[2]);
		}

		// read current allocated memory
		$curMem = memory_get_usage(true);  // memory_get_usage() is always available with PHP since 5.2.1

		// check if there is enough RAM loading the image(s), plus 3 MB for GD to use for calculations/transforms
		return ($phpMaxMem - $curMem >= $imgMem + (3 * 1048576)) ? true : false;
	}

}
