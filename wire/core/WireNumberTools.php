<?php namespace ProcessWire;

/**
 * Tools for working with numbers
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.213
 *
 */
class WireNumberTools extends Wire {

	/**
	 * Caches for methods in this class
	 * 
	 * @var array 
	 * 
	 */
	protected $caches = array();

	/**
	 * Generate and return an installation unique number/ID (integer)
	 *
	 * - Numbers returned by this method are incrementing, starting from 1.
	 * - Unique number counter stored in the database so is unique aross all time/requests.
	 * - Returned number is guaranteed to be unique among other calls to this method.
	 * - When using the `namespace` option, it will generate a new DB table for that namespace.
	 * - Use the `reset` option to delete a namespace when no longer needed.
	 * - You cannot reset the default namespace, so any caller is always assured a unique number.
	 * - This method creates table names that begin with `unique_num`.
	 *
	 * @param array|string $options Array of options or string for the namespace option.
	 *  - `namespace` (string): Optional namespace for unique numbers, in table name format [_a-zA-Z0-9] (default='')
	 *  - `getLast` (bool): Get last unique number rather than generating new one? (default=false)
	 *  - `reset` (bool): Reset numbers in namespace by deleting its table? Namespace required (default=false)
	 * @return int Returns unique number,
	 *  or returns 0 if `reset` option is used,
	 *  or returns 0 if `getLast` option is used and no numbers exist.
	 * @throws WireException
	 * @since 3.0.213
	 *
	 */
	public function uniqueNumber($options = array()) {

		$defaults = array(
			'namespace' => (is_string($options) ? $options : ''),
			'getLast' => false,
			'reset' => false,
		);

		$database = $this->wire()->database;
		$config = $this->wire()->config;
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$table = 'unique_num';

		if($options['namespace']) {
			$table .= '_' . $this->wire()->sanitizer->fieldName($options['namespace']);
		}

		if($options['reset']) {
			if(!$options['namespace']) throw new WireException('Namespace required for reset');
			if($database->tableExists($table)) $database->exec("DROP TABLE $table");
			return 0;
		}

		if($options['getLast']) try {
			$query = $database->query("SELECT MAX(id) FROM $table");
			$uniqueNum = (int) $query->fetchColumn();
			$query->closeCursor();
			return $uniqueNum;
		} catch(\Exception $e) {
			return 0;
		}

		try {
			$database->query("INSERT INTO $table SET id=null");
			$uniqueNum = (int) $database->lastInsertId();
		} catch(\Exception $e) {
			$uniqueNum = 0;
		}

		if(!$uniqueNum && !$database->tableExists($table) && empty($options['recursive'])) {
			$idSchema = "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
			$database->exec("CREATE TABLE $table ($idSchema) ENGINE=$config->dbEngine");
			return $this->uniqueNumber(array_merge($options, array('recursive' => true)));
		}

		if(!$uniqueNum) throw new WireException('Unable to generate unique number');

		if(($uniqueNum % 10 === 0) && $uniqueNum >= 10) {
			// maintain only 10 unique IDs in the DB table at a time
			$query = $database->prepare("DELETE FROM $table WHERE id<:id");
			$query->bindValue(':id', $uniqueNum, \PDO::PARAM_INT);
			$query->execute();
		}

		return $uniqueNum;
	}

	/**
	 * Return a random integer (cryptographically secure when available)
	 * 
	 * @param int $min Minimum value (default=0)
	 * @param int $max Maximum value (default=PHP_INT_MAX)
	 * @param bool $throw Throw WireException if we cannot achieve a cryptographically secure random number? (default=false)
	 * @return int
	 * @since 3.0.214
	 * 
	 */
	public function randomInteger($min, $max, $throw = false) {
		$rand = new WireRandom();
		return $rand->integer($min, $max, array('cryptoSecure' => $throw));
	}

	/**
	 * Given a value like "1M", "2MB", "3 kB", "4 GB", "5tb" etc. return quantity of bytes
	 * 
	 * Spaces, commas and case in given value do not matter. Only the first character of the unit is
	 * taken into account, whether it appears in the given value, or is given in the $unit argument.
	 * Meaning a unit like megabytes (for example) can be specified as 'm', 'mb', 'megabytes', etc. 
	 * 
	 * @param string|int|float $value
	 * @param string|null $unit Optional unit that given value is in (b, kb, mb, gb, tb), or omit to auto-detect
	 * @return int
	 * @since 3.0.214
	 * 
	 */
	public function strToBytes($value, $unit = null) {
		
		if(is_int($value) && $unit === null) return $value;
	
		$value = str_replace(array(' ', ','), '', "$value");
		
		if(ctype_digit("$value")) {
			$value = (int) $value;
		} else {
			$value = trim("$value");
			$negative = strpos($value, '-') === 0; 
			if($negative) $value = ltrim($value, '-');
			if(preg_match('/^([\d.]+)([bkmgt])/i', $value, $matches)) {
				$value = strpos($matches[1], '.') !== false ? (float) $matches[1] : (int) $matches[1];
				if($unit === null) $unit = $matches[2];
			}
			if($negative) $value *= -1;
		}
		
		if(is_string($unit)) switch(substr(strtolower($unit), 0, 1)) {
			case 'b': $value *= 1; break; // bytes
			case 'k': $value *= 1024; break; // kilobytes
			case 'm': $value *= (1024 * 1024); break; // megabytes
			case 'g': $value *= (1024 * 1024 * 1024); break; // gigabytes
			case 't': $value *= (1024 * 1024 * 1024 * 1024); break; // terabytes
		}
		
		if(is_float($value)) $value = (int) round($value);
		
		return (int) $value;
	}

	/**
	 * Given a quantity of bytes (int), return readable string that refers to quantity in bytes, kB, MB, GB and TB
	 *
	 * @param int|string $bytes Quantity in bytes (int) or any string accepted by strToBytes method. 
	 * @param array|int $options Options to modify default behavior, or if an integer then `decimals` option is assumed:
	 *  - `decimals` (int|null): Number of decimals to use in returned value or NULL for auto (default=null).
	 *     When null (auto) a decimal value of 1 is used when appropriate, for megabytes and higher (3.0.214+).
	 *  - `decimal_point` (string|null): Decimal point character, or null to detect from locale (default=null).
	 *  - `thousands_sep` (string|null): Thousands separator, or null to detect from locale (default=null).
	 *  - `small` (bool|int): Make returned string as small as possible? false=no, true=yes, 1=yes with space (default=false)
	 *  - `labels` (array): Labels to use for units, indexed by: b, byte, bytes, k, m, g, t
	 *  - `type` (string): To force return value as specific type, specify one of: bytes, kilobytes, megabytes,
	 *     gigabytes, terabytes; or just: b, k, m, g, t. (3.0.148+ only, terabytes 3.0.214+).
	 * @return string
	 * @since 3.0.214 All versions can also use the wireBytesStr() function
	 *
	 */
	public function bytesToStr($bytes, $options = array()) {
		
		$defaults = array(
			'type' => '',
			'small' => false, 
			'decimals' => null,
			'decimal_point' => null,
			'thousands_sep' => null,
			'labels' => array(),
		);

		if(is_string($bytes) && !ctype_digit($bytes)) {
			$bytes = $this->strToBytes($bytes);
		}

		$bytes = (int) $bytes;
		$options = array_merge($defaults, $options);
		$type = empty($options['type']) ? '' : strtolower(substr($options['type'], 0, 1));
		$small = isset($options['small']) ? $options['small'] : false;
		$labels = $options['labels'];

		if($options['decimals'] === null) {
			if($bytes > 1024 && empty($options['type'])) {
				// auto decimals (use 1 decimal for megabytes and higher)
				$options['decimals'] = 1;
			} else {
				$options['decimals'] = 0;
			}
		}

		// determine size value and units label	
		if($bytes < 1024 || $type === 'b') {
			// bytes
			$val = $bytes;
			if($small) {
				$label = $val > 0 ? (isset($labels['b']) ? $labels['b'] : $this->_('B')) : ''; // bytes
			} else if($val == 1) {
				$label = isset($labels['byte']) ? $labels['byte'] : $this->_('byte'); // singular 1-byte
			} else {
				$label = isset($labels['bytes']) ? $labels['bytes'] : $this->_('bytes'); // plural 2+ bytes (or 0 bytes)
			}
		} else if($bytes < 1000000 || $type === 'k') {
			// kilobytes
			$val = $bytes / 1024;
			$label = isset($labels['k']) ? $labels['k'] : $this->_('kB');
		} else if($bytes < 1073741824 || $type === 'm') {
			// megabytes
			$val = $bytes / 1024 / 1024;
			$label = isset($labels['m']) ? $labels['m'] : $this->_('MB');
		} else if($bytes < 1099511627776 || $type === 'g') {
			// gigabytes
			$val = $bytes / 1024 / 1024 / 1024;
			$label = isset($labels['g']) ? $labels['g'] : $this->_('GB');
		} else {
			// terabytes
			$val = $bytes / 1024 / 1024 / 1024 / 1024;
			$label = isset($labels['t']) ? $labels['t'] : $this->_('TB');
		}

		// determine decimal point if not specified in $options
		if($options['decimal_point'] === null) {
			if($options['decimals'] > 0) {
				$options['decimal_point'] = $this->locale('decimal_point'); 
			} else {
				// no decimal point needed (not used)
				$options['decimal_point'] = '.';
			}
		}

		// determine thousands separator if not specified in $options
		if($options['thousands_sep'] === null) {
			if($small || $val < 1000) {
				// no thousands separator needed
				$options['thousands_sep'] = '';
			} else {
				$options['thousands_sep'] = $this->locale('thousands_sep');
			}
		}

		// format number to string
		$str = number_format($val, $options['decimals'], $options['decimal_point'], $options['thousands_sep']);

		// in small mode remove numbers with decimals that consist only of zeros "0"
		if($small && $options['decimals'] > 0) {
			$test = substr($str, -1 * $options['decimals']);
			if(((int) $test) === 0) {
				$str = substr($str, 0, strlen($str) - ($options['decimals'] + 1)); // i.e. 123.00 => 123
			} else {
				$str = rtrim($str, '0'); // i.e. 123.10 => 123.1
			}
		}

		// append units label to number
		$str .= ($small === true ? '' : ' ') . $label;

		return $str;
	}

	/**
	 * Get a number formatting property from current locale
	 * 
	 * In multi-language environments, this method’s return values are affected by the 
	 * current language locale. 
	 * 
	 * @param string $key Property to get or omit to get all properties. Properties include:
	 *  - `decimal_point`: Decimal point character
	 *  - `thousands_sep`: Thousands separator
	 *  - `currency_symbol`: Local currency symbol (i.e. $)
	 *  - `int_curr_symbol`: International currency symbol (i.e. USD)
	 *  - `mon_decimal_point`: Monetary decimal point character
	 *  - `mon_thousands_sep`: Monetary thousands separator
	 *  - `positive_sign`: Sign for positive values
	 *  - `negative_sign`: Sign for negative values
	 *  - `clear`: Clear any cached values for current language/locale.
	 *  - See <https://www.php.net/manual/en/function.localeconv.php> for more. 
	 * @return array|string|int|null
	 * 
	 */
	public function locale($key = '') {
		$lang = $this->wire()->languages ? $this->wire()->user->language->id : '';
		$locale = "locale$lang";
		if($key === 'clear') unset($this->caches[$locale]);
		if(empty($this->caches[$locale])) $this->caches[$locale] = localeconv();
		if($key === '') return $this->caches[$locale]; 
		return isset($this->caches[$locale][$key]) ? $this->caches[$locale][$key] : null;
	}

}
