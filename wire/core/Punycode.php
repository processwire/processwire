<?php namespace ProcessWire;

/**
 * Punycode implementation as described in RFC 3492
 *
 * @link http://tools.ietf.org/html/rfc3492
 * @link https://github.com/true/php-punycode
 * 
 * Copyright (c) 2014 TrueServer B.V.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */
class Punycode {
	/**
	 * Bootstring parameter values
	 *
	 */
	const BASE = 36;
	const TMIN = 1;
	const TMAX = 26;
	const SKEW = 38;
	const DAMP = 700;
	const INITIAL_BIAS = 72;
	const INITIAL_N = 128;
	const PREFIX = 'xn--';
	const DELIMITER = '-';
	/**
	 * Encode table
	 *
	 * @param array
	 */
	protected static $encodeTable = array(
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
		'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
		'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
	);
	/**
	 * Decode table
	 *
	 * @param array
	 */
	protected static $decodeTable = array(
		'a' => 0, 'b' => 1, 'c' => 2, 'd' => 3, 'e' => 4, 'f' => 5,
		'g' => 6, 'h' => 7, 'i' => 8, 'j' => 9, 'k' => 10, 'l' => 11,
		'm' => 12, 'n' => 13, 'o' => 14, 'p' => 15, 'q' => 16, 'r' => 17,
		's' => 18, 't' => 19, 'u' => 20, 'v' => 21, 'w' => 22, 'x' => 23,
		'y' => 24, 'z' => 25, '0' => 26, '1' => 27, '2' => 28, '3' => 29,
		'4' => 30, '5' => 31, '6' => 32, '7' => 33, '8' => 34, '9' => 35
	);
	/**
	 * Character encoding
	 *
	 * @param string
	 */
	protected $encoding;

	/**
	 * PHP mb string functions supported?
	 * 
	 * @var bool 
	 * 
	 */
	protected $mb = false;

	/**
	 * Constructor
	 *
	 * @param string $encoding Character encoding
	 */
	public function __construct($encoding = 'UTF-8') {
		$this->encoding = $encoding;
		$this->mb = function_exists("mb_internal_encoding");
	}
	
	public function strtolower($str) { return $this->mb ? mb_strtolower($str, $this->encoding) : strtolower($str); }
	public function strlen($str) { return $this->mb ? mb_strlen($str, $this->encoding) : strlen($str); }
	public function substr($str, $a, $b) { return $this->mb ? mb_substr($str, $a, $b, $this->encoding) : substr($str, $a, $b); }

	/**
	 * Encode a domain to its Punycode version
	 *
	 * @param string $input Domain name in Unicode to be encoded
	 *
	 * @return string Punycode representation in ASCII
	 */
	public function encode($input) {
		$input = $this->strtolower($input);
		$parts = explode('.', $input);
		foreach($parts as &$part) {
			$part = $this->encodePart($part);
		}
		return implode('.', $parts);
	}

	/**
	 * Encode a part of a domain name, such as tld, to its Punycode version
	 *
	 * @param string $input Part of a domain name
	 *
	 * @return string Punycode representation of a domain part
	 */
	protected function encodePart($input) {
		$codePoints = $this->listCodePoints($input);
		$n = static::INITIAL_N;
		$bias = static::INITIAL_BIAS;
		$delta = 0;
		$h = $b = count($codePoints['basic']);
		$output = '';
		foreach($codePoints['basic'] as $code) {
			$output .= $this->codePointToChar($code);
		}
		if($input === $output) {
			return $output;
		}
		if($b > 0) {
			$output .= static::DELIMITER;
		}
		$codePoints['nonBasic'] = array_unique($codePoints['nonBasic']);
		sort($codePoints['nonBasic']);
		$i = 0;
		$length = $this->strlen($input);
		while($h < $length) {
			$m = $codePoints['nonBasic'][$i++];
			$delta = $delta + ($m - $n) * ($h + 1);
			$n = $m;
			foreach($codePoints['all'] as $c) {
				if($c < $n || $c < static::INITIAL_N) {
					$delta++;
				}
				if($c === $n) {
					$q = $delta;
					for($k = static::BASE; ; $k += static::BASE) {
						$t = $this->calculateThreshold($k, $bias);
						if($q < $t) {
							break;
						}
						$code = $t + ((floor($q) - $t) % (static::BASE - $t));
						$output .= static::$encodeTable[$code];
						$q = ($q - $t) / (static::BASE - $t);
					}
					$output .= static::$encodeTable[floor($q)];
					$bias = $this->adapt($delta, $h + 1, ($h === $b));
					$delta = 0;
					$h++;
				}
			}
			$delta++;
			$n++;
		}
		return static::PREFIX . $output;
	}

	/**
	 * Decode a Punycode domain name to its Unicode counterpart
	 *
	 * @param string $input Domain name in Punycode
	 *
	 * @return string Unicode domain name
	 */
	public function decode($input) {
		$input = strtolower($input);
		$parts = explode('.', $input);
		foreach($parts as &$part) {
			if(strpos($part, static::PREFIX) !== 0) {
				continue;
			}
			$part = substr($part, strlen(static::PREFIX));
			$part = $this->decodePart($part);
		}
		return implode('.', $parts);
	}

	/**
	 * Decode a part of domain name, such as tld
	 *
	 * @param string $input Part of a domain name
	 *
	 * @return string Unicode domain part
	 */
	protected function decodePart($input) {
		$n = static::INITIAL_N;
		$i = 0;
		$bias = static::INITIAL_BIAS;
		$output = '';
		$pos = strrpos($input, static::DELIMITER);
		if($pos !== false) {
			$output = substr($input, 0, $pos++);
		} else {
			$pos = 0;
		}
		$outputLength = strlen($output);
		$inputLength = strlen($input);
		while($pos < $inputLength) {
			$oldi = $i;
			$w = 1;
			for($k = static::BASE; ; $k += static::BASE) {
				$digit = static::$decodeTable[$input[$pos++]];
				$i = $i + ($digit * $w);
				$t = $this->calculateThreshold($k, $bias);
				if($digit < $t) {
					break;
				}
				$w = $w * (static::BASE - $t);
			}
			$bias = $this->adapt($i - $oldi, ++$outputLength, ($oldi === 0));
			$n = $n + (int) ($i / $outputLength);
			$i = $i % ($outputLength);
			$output = $this->substr($output, 0, $i) . 
				$this->codePointToChar($n) . 
				$this->substr($output, $i, $outputLength - 1);
			$i++;
		}
		return $output;
	}

	/**
	 * Calculate the bias threshold to fall between TMIN and TMAX
	 *
	 * @param integer $k
	 * @param integer $bias
	 *
	 * @return integer
	 */
	protected function calculateThreshold($k, $bias) {
		if($k <= $bias + static::TMIN) {
			return static::TMIN;
		} elseif($k >= $bias + static::TMAX) {
			return static::TMAX;
		}
		return $k - $bias;
	}

	/**
	 * Bias adaptation
	 *
	 * @param integer $delta
	 * @param integer $numPoints
	 * @param boolean $firstTime
	 *
	 * @return integer
	 */
	protected function adapt($delta, $numPoints, $firstTime) {
		$delta = (int) (
		($firstTime)
			? $delta / static::DAMP
			: $delta / 2
		);
		$delta += (int) ($delta / $numPoints);
		$k = 0;
		while($delta > ((static::BASE - static::TMIN) * static::TMAX) / 2) {
			$delta = (int) ($delta / (static::BASE - static::TMIN));
			$k = $k + static::BASE;
		}
		$k = $k + (int) (((static::BASE - static::TMIN + 1) * $delta) / ($delta + static::SKEW));
		return $k;
	}

	/**
	 * List code points for a given input
	 *
	 * @param string $input
	 *
	 * @return array Multi-dimension array with basic, non-basic and aggregated code points
	 */
	protected function listCodePoints($input) {
		$codePoints = array(
			'all' => array(),
			'basic' => array(),
			'nonBasic' => array(),
		);
		$length = $this->strlen($input);
		for($i = 0; $i < $length; $i++) {
			$char = $this->substr($input, $i, 1);
			$code = $this->charToCodePoint($char);
			if($code < 128) {
				$codePoints['all'][] = $codePoints['basic'][] = $code;
			} else {
				$codePoints['all'][] = $codePoints['nonBasic'][] = $code;
			}
		}
		return $codePoints;
	}

	/**
	 * Convert a single or multi-byte character to its code point
	 *
	 * @param string $char
	 *
	 * @return integer
	 */
	protected function charToCodePoint($char) {
		$code = ord($char[0]);
		if($code < 128) {
			return $code;
		} elseif($code < 224) {
			return (($code - 192) * 64) + (ord($char[1]) - 128);
		} elseif($code < 240) {
			return (($code - 224) * 4096) + ((ord($char[1]) - 128) * 64) + (ord($char[2]) - 128);
		} else {
			return (($code - 240) * 262144) + ((ord($char[1]) - 128) * 4096) + ((ord($char[2]) - 128) * 64) + (ord($char[3]) - 128);
		}
	}

	/**
	 * Convert a code point to its single or multi-byte character
	 *
	 * @param integer $code
	 *
	 * @return string
	 */
	protected function codePointToChar($code) {
		if($code <= 0x7F) {
			return chr($code);
		} elseif($code <= 0x7FF) {
			return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
		} elseif($code <= 0xFFFF) {
			return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
		} else {
			return chr(($code >> 18) + 240) . chr((($code >> 12) & 63) + 128) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
		}
	}
}
