<?php namespace ProcessWire;

/**
 * ProcessWire PostgreSQL SQL translator
 *
 * Translates the MySQL syntax used by ProcessWire (and commonly by its modules) into
 * equivalent PostgreSQL syntax. Like WireDatabaseSQLiteTranslator, this is intentionally
 * narrow: it handles the constructs that ProcessWire actually uses rather than attempting
 * to parse all of MySQL. SQL that is already PostgreSQL syntax (i.e. produced by the
 * dialect's own helpers) passes through unchanged.
 *
 * This class has no dependencies on the rest of ProcessWire so that it can also be
 * used by the installer before ProcessWire is booted.
 *
 * The tokenizer and token helper methods are adapted from WireDatabaseSQLiteTranslator.
 * The duplication is deliberate for now: a shared base class is to be extracted once both
 * translators have settled, rather than designed from one of them.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabasePgsqlTranslator {

	/**
	 * Separator between table name and index name in PostgreSQL index names
	 *
	 * PostgreSQL index names are unique per schema rather than per table, so we prefix them.
	 *
	 */
	const indexSeparator = '__';

	/**
	 * Cache of translated SQL, indexed by original SQL
	 *
	 * @var array
	 *
	 */
	protected $cache = [];

	/**
	 * Max number of items to keep in cache
	 *
	 * @var int
	 *
	 */
	protected $cacheMax = 500;

	/**
	 * Max length of SQL to cache translations for
	 *
	 * @var int
	 *
	 */
	protected $cacheMaxLength = 8192;

	/**
	 * PDO connection (or callable that returns it) for schema-aware translations
	 *
	 * Optional. When present, ON DUPLICATE KEY UPDATE can find the table's primary key for its
	 * conflict target, explicit inserts into identity columns can advance the sequence, table
	 * renames also rename indexes, and prefix indexes know which columns are text. Without it,
	 * those translations throw or fall back to schema-unaware forms.
	 *
	 * @var \PDO|callable|null
	 *
	 */
	protected $pdo = null;

	/**
	 * Construct
	 *
	 * @param \PDO|callable|null $pdo PDO connection or callable that returns it (for schema-aware translations)
	 *
	 */
	public function __construct($pdo = null) {
		$this->pdo = $pdo;
	}

	/**
	 * Get PDO connection for schema-aware translations, or null if not available
	 *
	 * @return \PDO|null
	 *
	 */
	protected function pdo() {
		if($this->pdo instanceof \PDO) return $this->pdo;
		if(is_callable($this->pdo)) return call_user_func($this->pdo);
		return null;
	}

	/**
	 * Translate MySQL SQL to PostgreSQL SQL, returning a list of one or more statements
	 *
	 * Some MySQL statements require multiple PostgreSQL statements (i.e. CREATE TABLE with indexes,
	 * or an INSERT with an explicit identity value followed by a sequence update). Multiple
	 * statements must be executed in order and atomically (see WireDatabaseDialectPgsql::execStatements()).
	 *
	 * String literals in given SQL are always interpreted with MySQL rules (backslash escapes),
	 * so values must be quoted/escaped MySQL-style, i.e. with WireDatabasePDO::quote() or escapeStr(),
	 * which produce MySQL-style escaping when using PostgreSQL (see the quote() method in this class).
	 *
	 * @param string $sql
	 * @return array
	 *
	 */
	public function translateStatements($sql) {
		if(isset($this->cache[$sql])) return $this->cache[$sql];
		$result = $this->translateStatement($sql);
		$result = is_array($result) ? array_values($result) : [$result];
		if(strlen($sql) > $this->cacheMaxLength) return $result; // avoid caching large statements (i.e. bulk inserts)
		if(preg_match('/^\s*(ALTER|RENAME|TRUNCATE|INSERT|REPLACE)\b/i', $sql)) return $result; // depends on current schema
		if(count($this->cache) >= $this->cacheMax) $this->cache = [];
		$this->cache[$sql] = $result;
		return $result;
	}

	/**
	 * Translate MySQL SQL to PostgreSQL SQL, returning a string
	 *
	 * When translation results in multiple statements, they are separated by a semicolon and newline.
	 * Use translateStatements() to get them separately.
	 *
	 * @param string $sql
	 * @return string
	 *
	 */
	public function translate($sql) {
		return implode(";\n", $this->translateStatements($sql));
	}

	/**
	 * Quote a string value MySQL-style, for use in SQL that will be translated
	 *
	 * Equivalent to PDO::quote() for MySQL. The translator converts it to a PostgreSQL literal.
	 *
	 * @param string $str
	 * @return string
	 *
	 */
	public static function quote($str) {
		return "'" . strtr((string) $str, [
			'\\' => '\\\\',
			"'" => "\\'",
			'"' => '\\"',
			"\0" => '\\0',
			"\n" => '\\n',
			"\r" => '\\r',
			"\x1a" => '\\Z',
		]) . "'";
	}

	/**
	 * Translate a single statement
	 *
	 * @param string $sql
	 * @return string|array String, or array when multiple statements are needed
	 *
	 */
	protected function translateStatement($sql) {

		$tokens = $this->tokenize($sql);
		$words = $this->leadingWords($tokens, 4);
		$first = isset($words[0]) ? $words[0] : '';

		switch($first) {
			case 'DO':
				// DO expr (MySQL: evaluate without returning a result)
				$i = $this->next($tokens, 0);
				$tokens[$i] = ['word', 'SELECT'];
				$first = 'SELECT';
				break;
		}

		$tokens = $this->expressions($tokens);
		$tokens = $this->booleanContext($tokens);
		if($first === 'SELECT') $tokens = $this->anyValueOrderBy($tokens);

		return $this->join($tokens);
	}

	/*********************************************************************************
	 * Tokenizer
	 *
	 */

	/**
	 * Tokenize SQL
	 *
	 * String literals are interpreted with MySQL rules: backslash escapes apply in both single
	 * and double quoted strings, and double quoted strings are strings (not identifiers).
	 *
	 * Each token is array(type, value) where type is one of:
	 * 'ws' (whitespace/comment), 'str' (string literal, value already in PostgreSQL form),
	 * 'id' (backtick identifier, value without backticks), 'word', 'num', 'param', 'punct'
	 *
	 * @param string $sql
	 * @return array
	 *
	 */
	protected function tokenize($sql) {
		$tokens = [];
		$len = strlen($sql);
		$i = 0;
		while($i < $len) {
			$c = $sql[$i];
			if(ctype_space($c)) {
				$j = $i;
				while($j < $len && ctype_space($sql[$j])) $j++;
				$tokens[] = ['ws', substr($sql, $i, $j - $i)];
				$i = $j;
			} else if($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $len || ord($sql[$i + 2]) <= 32)) {
				// MySQL: "--" starts a comment only when followed by whitespace or a control character
				$j = strpos($sql, "\n", $i);
				if($j === false) $j = $len;
				$tokens[] = ['ws', ' '];
				$i = $j;
			} else if($c === '/' && substr($sql, $i, 2) === '/*') {
				$j = strpos($sql, '*/', $i + 2);
				$j = $j === false ? $len : $j + 2;
				$tokens[] = ['ws', ' '];
				$i = $j;
			} else if($c === "'" || $c === '"') {
				// string literal (MySQL treats double-quoted as string too, unless ANSI_QUOTES)
				$value = '';
				$j = $i + 1;
				while($j < $len) {
					$d = $sql[$j];
					if($d === $c) {
						if($j + 1 < $len && $sql[$j + 1] === $c) {
							$value .= $c;
							$j += 2;
							continue;
						}
						break;
					} else if($d === '\\' && $j + 1 < $len) {
						$e = $sql[$j + 1];
						$map = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08"];
						if(isset($map[$e])) {
							$value .= $map[$e];
						} else if($e === '%' || $e === '_') {
							// MySQL keeps the backslash on these two, so that LIKE can use it as an escape
							$value .= '\\' . $e;
						} else {
							$value .= $e;
						}
						$j += 2;
						continue;
					}
					$value .= $d;
					$j++;
				}
				if(strpos($value, "\0") !== false) {
					// PostgreSQL text cannot contain NUL bytes: drop them (MySQL would store them)
					$value = str_replace("\0", '', $value);
				}
				// with standard_conforming_strings (the default), only quotes need doubling
				$tokens[] = ['str', "'" . str_replace("'", "''", $value) . "'"];
				$i = $j + 1;
			} else if($c === '`') {
				$j = strpos($sql, '`', $i + 1);
				if($j === false) $j = $len;
				$tokens[] = ['id', substr($sql, $i + 1, $j - $i - 1)];
				$i = $j + 1;
			} else if($c === ':' && $i + 1 < $len && (ctype_alnum($sql[$i + 1]) || $sql[$i + 1] === '_')) {
				$j = $i + 1;
				while($j < $len && (ctype_alnum($sql[$j]) || $sql[$j] === '_')) $j++;
				$tokens[] = ['param', substr($sql, $i, $j - $i)];
				$i = $j;
			} else if($c === '?') {
				$tokens[] = ['param', '?'];
				$i++;
			} else if(ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($sql[$i + 1]))) {
				$j = $i;
				while($j < $len && (ctype_alnum($sql[$j]) || $sql[$j] === '.')) $j++;
				$tokens[] = ['num', substr($sql, $i, $j - $i)];
				$i = $j;
			} else if(ctype_alpha($c) || $c === '_' || $c === '@' || $c === '$' || ord($c) > 127) {
				$j = $i + 1;
				while($j < $len && (ctype_alnum($sql[$j]) || $sql[$j] === '_' || $sql[$j] === '$' || $sql[$j] === '@' || ord($sql[$j]) > 127)) $j++;
				$tokens[] = ['word', substr($sql, $i, $j - $i)];
				$i = $j;
			} else {
				// multi-char operators
				$two = substr($sql, $i, 2);
				if(in_array($two, ['<=', '>=', '<>', '!=', '||', '&&', '<<', '>>', ':='])) {
					$tokens[] = ['punct', $two];
					$i += 2;
				} else {
					$tokens[] = ['punct', $c];
					$i++;
				}
			}
		}
		return $tokens;
	}

	/**
	 * Join tokens back into SQL
	 *
	 * @param array $tokens
	 * @return string
	 *
	 */
	protected function join(array $tokens) {
		$sql = '';
		foreach($tokens as $t) {
			$str = $t[0] === 'id' ? $this->quoteId($t[1]) : $t[1];
			// never produce "--" from separate tokens (i.e. "5 - -2"), since SQL treats it as a comment
			if($str !== '' && $str[0] === '-' && substr($sql, -1) === '-') $sql .= ' ';
			$sql .= $str;
		}
		return $sql;
	}

	/**
	 * Get first N uppercase words (ignoring whitespace)
	 *
	 * @param array $tokens
	 * @param int $qty
	 * @return array
	 *
	 */
	protected function leadingWords(array $tokens, $qty) {
		$words = [];
		foreach($tokens as $t) {
			if($t[0] === 'ws') continue;
			if($t[0] !== 'word') break;
			$words[] = strtoupper($t[1]);
			if(count($words) >= $qty) break;
		}
		return $words;
	}

	/**
	 * Is token the given keyword (case-insensitive)?
	 *
	 * @param array|null $t
	 * @param string|array $word
	 * @return bool
	 *
	 */
	protected function isWord($t, $word) {
		if(!$t || $t[0] !== 'word') return false;
		$w = strtoupper($t[1]);
		return is_array($word) ? in_array($w, $word, true) : $w === $word;
	}

	/**
	 * Index of next non-whitespace token at or after $i, or -1
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function next(array $tokens, $i) {
		$n = count($tokens);
		while($i < $n && $tokens[$i][0] === 'ws') $i++;
		return $i < $n ? $i : -1;
	}

	/**
	 * Index of previous non-whitespace token at or before $i, or -1
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function prev(array $tokens, $i) {
		while($i >= 0 && $tokens[$i][0] === 'ws') $i--;
		return $i;
	}

	/**
	 * Given index of an opening paren, return index of matching closing paren (or -1)
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function matchParen(array $tokens, $i) {
		$depth = 0;
		$n = count($tokens);
		for(; $i < $n; $i++) {
			if($tokens[$i][0] !== 'punct') continue;
			if($tokens[$i][1] === '(') $depth++;
			if($tokens[$i][1] === ')') {
				$depth--;
				if($depth === 0) return $i;
			}
		}
		return -1;
	}

	/**
	 * Split tokens on top-level commas
	 *
	 * @param array $tokens
	 * @return array Array of token arrays
	 *
	 */
	protected function splitCommas(array $tokens) {
		$parts = [];
		$part = [];
		$depth = 0;
		foreach($tokens as $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				if($t[1] === ',' && $depth === 0) {
					$parts[] = $part;
					$part = [];
					continue;
				}
			}
			$part[] = $t;
		}
		if(count($part)) $parts[] = $part;
		return $parts;
	}

	/**
	 * Trim whitespace tokens from both ends
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function trimTokens(array $tokens) {
		while(count($tokens) && $tokens[0][0] === 'ws') array_shift($tokens);
		while(count($tokens) && $tokens[count($tokens) - 1][0] === 'ws') array_pop($tokens);
		return array_values($tokens);
	}

	/**
	 * Get plain name from identifier, word or string token
	 *
	 * @param array $t
	 * @return string
	 *
	 */
	protected function name($t) {
		return $t[0] === 'str' ? trim($t[1], "'") : $t[1];
	}

	/**
	 * Does token list contain given word at top level (paren depth 0)?
	 *
	 * @param array $tokens
	 * @param string $word
	 * @return bool
	 *
	 */
	protected function hasTopLevelWord(array $tokens, $word) {
		$depth = 0;
		foreach($tokens as $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
			} else if($depth === 0 && $this->isWord($t, $word)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Given index of the start of a simple operand, return index of its last token (or -1)
	 *
	 * Operand is a literal, parameter, (optionally table-qualified) column, function call or parenthesized expression.
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function operandEnd(array $tokens, $i) {
		$t = $tokens[$i];
		if($t[0] === 'punct') return $t[1] === '(' ? $this->matchParen($tokens, $i) : -1;
		if($t[0] === 'str' || $t[0] === 'num' || $t[0] === 'param') return $i;
		// word or identifier: function call or (qualified) column name
		$n = count($tokens);
		if($i + 1 < $n && $tokens[$i + 1][1] === '(') return $this->matchParen($tokens, $i + 1);
		while($i + 2 < $n && $tokens[$i + 1][0] === 'punct' && $tokens[$i + 1][1] === '.' && in_array($tokens[$i + 2][0], ['word', 'id'])) {
			$i += 2;
		}
		return $i;
	}

	/**
	 * Quote identifier for PostgreSQL
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function quoteId($name) {
		return '"' . str_replace('"', '""', $name) . '"';
	}

	/**
	 * Get PostgreSQL index name for given table and MySQL index name
	 *
	 * @param string $table
	 * @param string $index
	 * @return string
	 *
	 */
	public function indexName($table, $index) {
		return $table . self::indexSeparator . $index;
	}

	/*********************************************************************************
	 * Expression-level translations (applies to all statements)
	 *
	 */

	/**
	 * Translate expression-level MySQL syntax
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function expressions(array $tokens) {

		$out = [];
		$n = count($tokens);

		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];

			if($t[0] !== 'word') {
				$out[] = $t;
				continue;
			}

			$w = strtoupper($t[1]);
			$j = $this->next($tokens, $i + 1); // next non-whitespace token index, or -1
			$paren = $j > -1 && $tokens[$j][0] === 'punct' && $tokens[$j][1] === '(';

			if(in_array($w, ['SQL_CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'SQL_CACHE', 'STRAIGHT_JOIN', 'HIGH_PRIORITY'])) {
				// remove, along with following whitespace
				if($j > $i + 1) $i = $j - 1;
				continue;

			} else if($w === 'FOUND_ROWS' && $paren) {
				throw new \PDOException(
					'PostgreSQL translator: FOUND_ROWS() is not supported. ' .
					'Check $database->dialect()->supportsFoundRows() and use COUNT(*) instead.'
				);

			} else if($w === 'MATCH' && $paren) {
				$end = $this->matchParen($tokens, $j);
				$k = $end > -1 ? $this->next($tokens, $end + 1) : -1;
				if($k > -1 && $this->isWord($tokens[$k], 'AGAINST')) {
					throw new \PDOException(
						'PostgreSQL translator: MATCH ... AGAINST (fulltext search) is not supported by this dialect yet. ' .
						'Use $database->dialect()->supportsFulltext() to detect this and use LIKE or REGEXP instead.'
					);
				}
				$out[] = $t;

			} else if($w === 'NOT' && $j > -1 && $this->isWord($tokens[$j], ['RLIKE', 'REGEXP'])) {
				$out[] = ['punct', '!~*'];
				$i = $j;

			} else if($w === 'RLIKE' || $w === 'REGEXP') {
				// MySQL REGEXP is case-insensitive under its default collations
				$out[] = ['punct', '~*'];

			} else if($w === 'LIKE') {
				// MySQL LIKE is case-insensitive under its default collations
				$out[] = ['word', 'ILIKE'];

			} else if($w === 'BINARY' && $j > -1 && $this->operandEnd($tokens, $j) > -1) {
				// "BINARY x" forces a case-sensitive comparison, which PostgreSQL comparisons are already
				$i = $j - 1;
				continue;

			} else if($w === 'LIMIT' && $j > -1 && in_array($tokens[$j][0], ['num', 'param'])) {
				// LIMIT offset, count => LIMIT count OFFSET offset
				$k = $this->next($tokens, $j + 1);
				$l = $k > -1 && $tokens[$k][0] === 'punct' && $tokens[$k][1] === ',' ? $this->next($tokens, $k + 1) : -1;
				if($l > -1 && in_array($tokens[$l][0], ['num', 'param'])) {
					$out[] = $t;
					$out[] = ['ws', ' '];
					$out[] = $tokens[$l];
					$out[] = ['ws', ' '];
					$out[] = ['word', 'OFFSET'];
					$out[] = ['ws', ' '];
					$out[] = $tokens[$j];
					$i = $l;
				} else {
					$out[] = $t;
				}

			} else if($w === 'INTERVAL' && $j > -1) {
				// INTERVAL 5 DAY => INTERVAL '5 day'; INTERVAL :n DAY => (INTERVAL '1 day' * :n)
				$amount = $tokens[$j];
				$amountEnd = $this->operandEnd($tokens, $j);
				$k = $amountEnd > -1 ? $this->next($tokens, $amountEnd + 1) : -1;
				if($k > -1 && $tokens[$k][0] === 'word') {
					$unit = rtrim(strtolower($tokens[$k][1]), 's');
					if($amount[0] === 'str' && is_numeric(trim($amount[1], "'"))) $amount = ['num', trim($amount[1], "'")];
					if($amount[0] === 'num') {
						$out[] = ['word', "INTERVAL '$amount[1] $unit'"];
					} else {
						$expr = $this->join($this->expressions(array_slice($tokens, $j, $amountEnd - $j + 1)));
						$out[] = ['word', "(INTERVAL '1 $unit' * $expr)"];
					}
					$i = $k;
				} else {
					$out[] = $t;
				}

			} else if($w === 'GROUP_CONCAT' && $paren) {
				$end = $this->matchParen($tokens, $j);
				$out[] = ['word', $this->groupConcat(array_slice($tokens, $j + 1, $end - $j - 1))];
				$i = $end;

			} else if(($w === 'CAST' || $w === 'CONVERT') && $paren) {
				$end = $this->matchParen($tokens, $j);
				$out[] = ['word', 'CAST'];
				for($x = $j; $x <= $end; $x++) {
					$tx = $tokens[$x];
					if($this->isWord($tx, 'AS')) {
						$y = $this->next($tokens, $x + 1);
						if($y > -1) {
							$type = strtoupper($tokens[$y][1]);
							$map = [
								'UNSIGNED' => 'bigint', 'SIGNED' => 'bigint', 'CHAR' => 'text', 'DECIMAL' => 'numeric',
								'DATETIME' => 'timestamp', 'DATE' => 'date', 'BINARY' => 'bytea',
							];
							if(isset($map[$type])) {
								$out[] = $tx;
								$out[] = ['ws', ' '];
								$pgType = $map[$type];
								$x = $y;
								$z = $this->next($tokens, $x + 1);
								if($z > -1 && $this->isWord($tokens[$z], 'INTEGER')) $x = $z; // UNSIGNED INTEGER
								$z = $this->next($tokens, $x + 1);
								if($z > -1 && $z < $end && $tokens[$z][0] === 'punct' && $tokens[$z][1] === '(') {
									$zEnd = $this->matchParen($tokens, $z);
									if($type === 'DECIMAL') $pgType .= $this->join(array_slice($tokens, $z, $zEnd - $z + 1)); // numeric(p,s)
									$x = $zEnd;
								}
								$out[] = ['word', $pgType];
								continue;
							}
						}
					}
					$out[] = $tx;
				}
				$i = $end;

			} else if($paren && ($replacement = $this->functionCall($w, $tokens, $j)) !== null) {
				$out[] = ['word', $replacement];
				$i = $this->matchParen($tokens, $j);

			} else {
				$out[] = $t;
			}
		}

		return $out;
	}

	/**
	 * Split the arguments of a function call whose opening paren is at $open
	 *
	 * @param array $tokens
	 * @param int $open
	 * @return array Translated argument SQL strings
	 *
	 */
	protected function callArgs(array $tokens, $open) {
		$close = $this->matchParen($tokens, $open);
		$inner = $this->trimTokens(array_slice($tokens, $open + 1, $close - $open - 1));
		if(!count($inner)) return [];
		$args = [];
		foreach($this->splitCommas($inner) as $arg) {
			$args[] = trim($this->join($this->expressions($this->trimTokens($arg))));
		}
		return $args;
	}

	/**
	 * Translate a MySQL function call to a PostgreSQL expression, or return null to leave it alone
	 *
	 * @param string $name Uppercase function name
	 * @param array $tokens
	 * @param int $open Index of the opening paren
	 * @return string|null
	 *
	 */
	protected function functionCall($name, array $tokens, $open) {

		$args = $this->callArgs($tokens, $open);
		$qty = count($args);

		switch($name) {
			case 'NOW':
			case 'CURRENT_TIMESTAMP':
				return $qty ? null : 'now()';
			case 'UNIX_TIMESTAMP':
				return '(extract(epoch from ' . ($qty ? $args[0] : 'now()') . '))::bigint';
			case 'FROM_UNIXTIME':
				if($qty === 1) return "to_timestamp($args[0])::timestamp";
				if($qty === 2 && ($format = $this->literalValue($args[1])) !== null) {
					return "to_char(to_timestamp($args[0]), '" . $this->dateFormatPattern($format) . "')";
				}
				return null;
			case 'DATE_FORMAT':
				if($qty === 2 && ($format = $this->literalValue($args[1])) !== null) {
					return "to_char($args[0], '" . $this->dateFormatPattern($format) . "')";
				}
				return null;
			case 'DATE_ADD':
			case 'DATE_SUB':
				if($qty !== 2) return null;
				return '(' . $args[0] . ($name === 'DATE_ADD' ? ' + ' : ' - ') . $args[1] . ')';
			case 'IF':
				return $qty === 3 ? "(CASE WHEN $args[0] THEN $args[1] ELSE $args[2] END)" : null;
			case 'IFNULL':
				return $qty === 2 ? "COALESCE($args[0], $args[1])" : null;
			case 'FIELD':
				if($qty < 2) return null;
				$value = array_shift($args);
				return 'COALESCE(array_position(ARRAY[' . implode(',', $args) . "], $value), 0)";
			case 'RAND':
				return $qty ? null : 'random()';
			case 'DATABASE':
			case 'SCHEMA':
				return $qty ? null : 'current_database()';
			case 'LAST_INSERT_ID':
				return $qty ? null : 'lastval()';
			case 'GET_LOCK':
				// timeout is not supported by pg_try_advisory_lock() and is ignored
				return $qty ? "(CASE WHEN pg_try_advisory_lock(hashtext($args[0])) THEN 1 ELSE 0 END)" : null;
			case 'RELEASE_LOCK':
				return $qty === 1 ? "(CASE WHEN pg_advisory_unlock(hashtext($args[0])) THEN 1 ELSE 0 END)" : null;
			case 'IS_FREE_LOCK':
				return $qty === 1 ? "(CASE WHEN EXISTS (SELECT 1 FROM pg_locks WHERE locktype='advisory' AND objid=hashtext($args[0])::oid) THEN 0 ELSE 1 END)" : null;
		}

		return null;
	}

	/**
	 * Get the value of a translated string literal, or null if the SQL is not a plain literal
	 *
	 * @param string $sql
	 * @return string|null
	 *
	 */
	protected function literalValue($sql) {
		if(strlen($sql) < 2 || $sql[0] !== "'" || substr($sql, -1) !== "'") return null;
		return str_replace("''", "'", substr($sql, 1, -1));
	}

	/**
	 * Convert a MySQL DATE_FORMAT() format string to a PostgreSQL to_char() pattern
	 *
	 * Letters that are not part of a %-code are quoted, since to_char() treats bare letters as
	 * pattern codes. Single quotes in the result are doubled for use inside a literal.
	 *
	 * @param string $format
	 * @return string
	 *
	 */
	protected function dateFormatPattern($format) {
		$map = [
			'Y' => 'YYYY', 'y' => 'YY', 'm' => 'MM', 'c' => 'FMMM', 'd' => 'DD', 'e' => 'FMDD',
			'H' => 'HH24', 'h' => 'HH12', 'I' => 'HH12', 'i' => 'MI', 's' => 'SS', 'S' => 'SS', 'p' => 'AM',
			'j' => 'DDD', 'W' => 'FMDay', 'a' => 'Dy', 'M' => 'FMMonth', 'b' => 'Mon', 'u' => 'IW', 'w' => 'D',
			'%' => '%',
		];
		$out = '';
		$len = strlen($format);
		for($i = 0; $i < $len; $i++) {
			$c = $format[$i];
			if($c === '%' && $i + 1 < $len) {
				$code = $format[++$i];
				$out .= isset($map[$code]) ? $map[$code] : '"%' . $code . '"';
			} else if(ctype_alpha($c)) {
				$out .= '"' . $c . '"';
			} else {
				$out .= $c;
			}
		}
		return str_replace("'", "''", $out);
	}

	/**
	 * Translate the arguments of GROUP_CONCAT([DISTINCT] expr [ORDER BY ...] [SEPARATOR 'x']) to string_agg()
	 *
	 * @param array $tokens Tokens between the parentheses
	 * @return string
	 *
	 */
	protected function groupConcat(array $tokens) {
		$tokens = $this->trimTokens($tokens);
		$distinct = false;
		if(count($tokens) && $this->isWord($tokens[0], 'DISTINCT')) {
			$distinct = true;
			$tokens = $this->trimTokens(array_slice($tokens, 1));
		}
		// find top-level ORDER BY and SEPARATOR
		$orderPos = -1;
		$sepPos = -1;
		$depth = 0;
		foreach($tokens as $x => $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0) continue;
			if($orderPos < 0 && $this->isWord($t, 'ORDER')) {
				$y = $this->next($tokens, $x + 1);
				if($y > -1 && $this->isWord($tokens[$y], 'BY')) $orderPos = $x;
			}
			if($this->isWord($t, 'SEPARATOR')) $sepPos = $x;
		}
		$exprEnd = count($tokens);
		if($orderPos > -1) $exprEnd = $orderPos;
		else if($sepPos > -1) $exprEnd = $sepPos;
		$exprs = [];
		foreach($this->splitCommas(array_slice($tokens, 0, $exprEnd)) as $expr) {
			$exprs[] = trim($this->join($this->expressions($expr)));
		}
		$expr = count($exprs) > 1 ? 'concat(' . implode(', ', $exprs) . ')' : $exprs[0] . '::text';
		$separator = "','";
		if($sepPos > -1) {
			$y = $this->next($tokens, $sepPos + 1);
			if($y > -1) $separator = $this->join([$tokens[$y]]);
		}
		$order = '';
		if($orderPos > -1) {
			$y = $this->next($tokens, $orderPos + 1); // BY
			$orderEnd = $sepPos > $orderPos ? $sepPos : count($tokens);
			$order = ' ORDER BY ' . trim($this->join($this->expressions(array_slice($tokens, $y + 1, $orderEnd - $y - 1))));
		}
		return 'string_agg(' . ($distinct ? 'DISTINCT ' : '') . "$expr, $separator$order)";
	}

	/*********************************************************************************
	 * Statement-level passes
	 *
	 */

	/**
	 * Make conditions boolean where MySQL accepts an integer
	 *
	 * PostgreSQL requires a boolean in WHERE, HAVING, ON, WHEN and after AND/OR. MySQL accepts any
	 * integer, and ProcessWire uses that with bit tests (`WHERE (status & 1024)`) and constants
	 * (`WHERE 0`). Bit tests get `<> 0` and the constants become true/false.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function booleanContext(array $tokens) {
		$out = [];
		$n = count($tokens);
		$i = 0;
		while($i < $n) {
			$t = $tokens[$i];
			if($this->isWord($t, ['WHERE', 'HAVING', 'ON', 'WHEN', 'AND', 'OR'])) {
				$out[] = $t;
				$i++;
				$j = $this->conditionEnd($tokens, $i);
				foreach($this->condition(array_slice($tokens, $i, $j - $i)) as $tc) $out[] = $tc;
				$i = $j;
				continue;
			}
			$out[] = $t;
			$i++;
		}
		return $out;
	}

	/**
	 * Index of the token that ends the condition starting at $i (exclusive)
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function conditionEnd(array $tokens, $i) {
		$n = count($tokens);
		$depth = 0;
		$stops = ['AND', 'OR', 'WHERE', 'HAVING', 'ON', 'WHEN', 'THEN', 'ELSE', 'END', 'GROUP', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'DO', 'RETURNING', 'FOR', 'WINDOW', 'SET', 'FROM', 'VALUES', 'SELECT', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'CROSS'];
		for($j = $i; $j < $n; $j++) {
			$t = $tokens[$j];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') {
					if($depth === 0) return $j;
					$depth--;
				}
				continue;
			}
			if($depth === 0 && $t[0] === 'word' && in_array(strtoupper($t[1]), $stops, true)) return $j;
		}
		return $n;
	}

	/**
	 * Translate one condition (the tokens between two boolean operators) to a boolean expression
	 *
	 * @param array $seg
	 * @return array
	 *
	 */
	protected function condition(array $seg) {
		$lead = [];
		$trail = [];
		while(count($seg) && $seg[0][0] === 'ws') $lead[] = array_shift($seg);
		while(count($seg) && $seg[count($seg) - 1][0] === 'ws') array_unshift($trail, array_pop($seg));
		$n = count($seg);
		if(!$n) return array_merge($lead, $trail);

		if($n === 1 && $seg[0][0] === 'num' && ($seg[0][1] === '0' || $seg[0][1] === '1')) {
			return array_merge($lead, [['word', $seg[0][1] === '0' ? 'false' : 'true']], $trail);
		}

		if($seg[0][0] === 'punct' && $seg[0][1] === '(' && $this->matchParen($seg, 0) === $n - 1) {
			// a parenthesized group is itself a list of conditions
			$inner = array_slice($seg, 1, -1);
			$j = $this->conditionEnd($inner, 0);
			$innerOut = array_merge($this->condition(array_slice($inner, 0, $j)), $this->booleanContext(array_slice($inner, $j)));
			return array_merge($lead, [$seg[0]], $innerOut, [$seg[$n - 1]], $trail);
		}

		$hasBitwise = false;
		$hasBoolean = false;
		$depth = 0;
		foreach($seg as $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				if($depth !== 0) continue;
				if($t[1] === '&' || $t[1] === '|') $hasBitwise = true;
				if(in_array($t[1], ['=', '<', '>', '<=', '>=', '<>', '!=', '~*', '!~*'], true)) $hasBoolean = true;
			} else if($depth === 0 && $t[0] === 'word' && in_array(strtoupper($t[1]), ['IS', 'IN', 'LIKE', 'ILIKE', 'BETWEEN', 'EXISTS', 'NOT'], true)) {
				$hasBoolean = true;
			}
		}
		if($hasBitwise && !$hasBoolean) {
			return array_merge($lead, [['punct', '(']], $seg, [['word', ') <> 0']], $trail);
		}

		return array_merge($lead, $seg, $trail);
	}

	/**
	 * Wrap ORDER BY terms from tables other than the grouped one in any_value()
	 *
	 * MySQL without ONLY_FULL_GROUP_BY (as ProcessWire configures it) allows `GROUP BY pages.id`
	 * with `ORDER BY joined_table.column`. PostgreSQL accepts columns of the grouped table (they
	 * depend on its primary key) but not columns of joined tables; any_value() gives the same
	 * result MySQL returns, one arbitrary value from the group.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function anyValueOrderBy(array $tokens) {
		$groupPos = -1;
		$orderPos = -1;
		$depth = 0;
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0 || $t[0] !== 'word') continue;
			$w = strtoupper($t[1]);
			if(($w === 'GROUP' || $w === 'ORDER')) {
				$j = $this->next($tokens, $i + 1);
				if($j === -1 || !$this->isWord($tokens[$j], 'BY')) continue;
				if($w === 'GROUP' && $groupPos < 0) $groupPos = $j + 1;
				if($w === 'ORDER') $orderPos = $j + 1;
			}
		}
		if($groupPos < 0 || $orderPos < 0 || $orderPos < $groupPos) return $tokens;

		$qualifiers = [];
		$groupEnd = $this->clauseEnd($tokens, $groupPos);
		foreach($this->splitCommas(array_slice($tokens, $groupPos, $groupEnd - $groupPos)) as $term) {
			$term = $this->trimTokens($term);
			if(count($term) === 3 && $term[1][0] === 'punct' && $term[1][1] === '.') $qualifiers[$this->name($term[0])] = true;
		}
		if(!count($qualifiers)) return $tokens;

		$orderEnd = $this->clauseEnd($tokens, $orderPos);
		$terms = [];
		foreach($this->splitCommas(array_slice($tokens, $orderPos, $orderEnd - $orderPos)) as $part) {
			$lead = [];
			$trail = [];
			while(count($part) && $part[0][0] === 'ws') $lead[] = array_shift($part);
			while(count($part) && $part[count($part) - 1][0] === 'ws') array_unshift($trail, array_pop($part));
			$direction = [];
			$last = count($part) ? $part[count($part) - 1] : null;
			if($this->isWord($last, ['ASC', 'DESC'])) {
				$direction = [['ws', ' '], array_pop($part)];
				$part = $this->trimTokens($part);
			}
			if(count($part) === 3 && $part[1][0] === 'punct' && $part[1][1] === '.' && in_array($part[0][0], ['word', 'id']) && !isset($qualifiers[$this->name($part[0])])) {
				$part = [['word', 'any_value(' . $this->join($part) . ')']];
			}
			$terms[] = array_merge($lead, $part, $direction, $trail);
		}
		$rebuilt = [];
		foreach($terms as $k => $term) {
			if($k) $rebuilt[] = ['punct', ','];
			foreach($term as $t) $rebuilt[] = $t;
		}
		return array_merge(array_slice($tokens, 0, $orderPos), $rebuilt, array_slice($tokens, $orderEnd));
	}

	/**
	 * Index of the token that ends a GROUP BY or ORDER BY term list starting at $i (exclusive)
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return int
	 *
	 */
	protected function clauseEnd(array $tokens, $i) {
		$n = count($tokens);
		$depth = 0;
		for($j = $i; $j < $n; $j++) {
			$t = $tokens[$j];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth === 0 && $t[0] === 'word' && in_array(strtoupper($t[1]), ['ORDER', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'FOR', 'WINDOW'], true)) return $j;
		}
		return $n;
	}
}
