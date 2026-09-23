<?php namespace ProcessWire;

/**
 * ProcessWire SQLite SQL translator
 *
 * Translates the MySQL syntax used by ProcessWire (and commonly by its modules) into
 * equivalent SQLite syntax. This is intentionally narrow: it handles the constructs
 * that ProcessWire actually uses rather than attempting to parse all of MySQL.
 *
 * This class has no dependencies on the rest of ProcessWire so that it can also be
 * used by the installer before ProcessWire is booted.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabaseSQLiteTranslator {

	/**
	 * Separator between table name and index name in SQLite index names
	 *
	 * SQLite index names are unique per database rather than per table, so we prefix them.
	 *
	 */
	const indexSeparator = '__';

	/**
	 * SQLite default for MySQL DEFAULT CURRENT_TIMESTAMP (local rather than UTC time)
	 *
	 */
	const currentTimestampDefault = "(datetime('now','localtime'))";

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
	 * Optional. When present, ALTER TABLE operations that SQLite cannot perform natively (MODIFY,
	 * CHANGE, PRIMARY KEY) are translated to table rebuilds, table renames also rename indexes,
	 * and TRUNCATE resets AUTOINCREMENT sequences. Without it, those operations throw.
	 *
	 * @var \PDO|callable|null
	 *
	 */
	protected $pdo = null;

	/**
	 * Database name used for SHOW TABLES column name (Tables_in_name)
	 *
	 * @var string
	 *
	 */
	protected $databaseName = 'main';

	/**
	 * Construct
	 *
	 * @param \PDO|callable|null $pdo PDO connection or callable that returns it (for schema-aware translations)
	 * @param string $databaseName Name used for SHOW TABLES column (Tables_in_name)
	 *
	 */
	public function __construct($pdo = null, $databaseName = 'main') {
		$this->pdo = $pdo;
		$this->databaseName = $databaseName;
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
	 * Translate MySQL SQL to SQLite SQL, returning a list of one or more SQLite statements
	 *
	 * Some MySQL statements require multiple SQLite statements (i.e. CREATE TABLE with indexes,
	 * or an ALTER TABLE that requires a table rebuild). Multiple statements must be executed
	 * in order and atomically (see WireDatabaseDialectSQLite::execStatements()).
	 *
	 * String literals in given SQL are always interpreted with MySQL rules (backslash escapes),
	 * so values must be quoted/escaped MySQL-style, i.e. with WireDatabasePDO::quote() or escapeStr(),
	 * which produce MySQL-style escaping when using SQLite (see the quote() method in this class).
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
		if(preg_match('/^\s*(ALTER|RENAME|TRUNCATE)\b/i', $sql)) return $result; // depends on current schema
		if(count($this->cache) >= $this->cacheMax) $this->cache = [];
		$this->cache[$sql] = $result;
		return $result;
	}

	/**
	 * Translate MySQL SQL to SQLite SQL, returning a string
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
	 * Equivalent to PDO::quote() for MySQL. The translator converts it to an SQLite literal.
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
		$second = isset($words[1]) ? $words[1] : '';

		switch($first) {
			case 'CREATE':
				if($second === 'TABLE' || ($second === 'TEMPORARY' && isset($words[2]) && $words[2] === 'TABLE')) {
					return $this->createTable($tokens);
				}
				if(in_array($second, ['INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL'])) {
					return $this->createIndex($tokens);
				}
				break;
			case 'RENAME':
				if($second === 'TABLE') return $this->renameTable($tokens);
				break;
			case 'UPDATE':
				$tokens = $this->updateOrderLimit($tokens);
				break;
			case 'ALTER':
				if($second === 'TABLE') return $this->alterTable($tokens);
				break;
			case 'SHOW':
				return $this->show($tokens);
			case 'DESCRIBE':
			case 'DESC':
			case 'EXPLAIN':
				// DESCRIBE table [column] is equivalent to SHOW COLUMNS FROM table [LIKE column]
				if($first === 'EXPLAIN' && in_array($second, ['SELECT', 'UPDATE', 'DELETE', 'INSERT', 'REPLACE', 'WITH'])) break;
				$i = $this->next($tokens, $this->next($tokens, 0) + 1);
				if($i < 0) break;
				$show = $this->tokenize('SHOW COLUMNS FROM ');
				$show[] = $tokens[$i];
				$j = $this->next($tokens, $i + 1);
				if($j > -1) {
					$show[] = ['ws', ' '];
					$show[] = ['word', 'LIKE'];
					$show[] = ['ws', ' '];
					$show[] = $tokens[$j][0] === 'str' ? $tokens[$j] : ['str', "'" . str_replace("'", "''", $this->name($tokens[$j])) . "'"];
				}
				return $this->show($show);
			case 'DO':
				// DO expr (MySQL: evaluate without returning a result)
				$i = $this->next($tokens, 0);
				$tokens[$i] = ['word', 'SELECT'];
				break;
			case 'SET':
				// SET NAMES, SET sql_mode, SET SESSION, etc. have no SQLite equivalent
				return 'SELECT 1';
			case 'LOCK':
			case 'UNLOCK':
				// @todo map to BEGIN IMMEDIATE / COMMIT where appropriate
				return 'SELECT 1';
			case 'TRUNCATE':
				return $this->truncate($tokens);
			case 'OPTIMIZE':
			case 'ANALYZE':
			case 'REPAIR':
			case 'CHECK':
				return 'SELECT 1';
			case 'INSERT':
			case 'REPLACE':
				$tokens = $this->insert($tokens);
				break;
			case 'DELETE':
				$tokens = $this->deleteLimit($tokens);
				break;
			case 'DROP':
				if($second === 'INDEX') return $this->dropIndex($tokens);
				break;
		}

		$tokens = $this->expressions($tokens);

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
	 * 'ws' (whitespace/comment), 'str' (string literal, value already in SQLite form),
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
					// SQL text cannot contain NUL bytes, so use a hex literal
					$tokens[] = ['str', "CAST(X'" . bin2hex($value) . "' AS TEXT)"];
				} else {
					$tokens[] = ['str', "'" . str_replace("'", "''", $value) . "'"];
				}
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
			$str = $t[0] === 'id' ? '`' . $t[1] . '`' : $t[1];
			// never produce "--" from separate tokens (i.e. "5 - -2"), since SQLite treats it as a comment
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
	 * Get plain name from identifier or word token
	 *
	 * @param array $t
	 * @return string
	 *
	 */
	protected function name($t) {
		return $t[0] === 'str' ? trim($t[1], "'") : $t[1];
	}

	/**
	 * Quote identifier for SQLite
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function quoteId($name) {
		return '`' . str_replace('`', '', $name) . '`';
	}

	/**
	 * Get SQLite index name for given table and MySQL index name
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

			if($w === 'SQL_CALC_FOUND_ROWS' || $w === 'SQL_NO_CACHE' || $w === 'SQL_CACHE' || $w === 'STRAIGHT_JOIN' || $w === 'HIGH_PRIORITY') {
				// remove (note: FOUND_ROWS() must be avoided by caller)
				$j = $this->next($tokens, $i + 1);
				if($j > $i + 1) $i = $j - 1; // skip following whitespace
				continue;

			} else if($w === 'RLIKE') {
				$out[] = ['word', 'REGEXP'];

			} else if($w === 'LIKE') {
				// MySQL LIKE uses backslash as the default escape character, SQLite has none
				$out[] = $t;
				$j = $this->next($tokens, $i + 1);
				if($j > -1 && in_array($tokens[$j][0], ['param', 'str'])) {
					$k = $this->next($tokens, $j + 1);
					if($k === -1 || !$this->isWord($tokens[$k], 'ESCAPE')) {
						for($x = $i + 1; $x <= $j; $x++) $out[] = $tokens[$x];
						$out[] = ['ws', ' '];
						$out[] = ['word', "ESCAPE '\\'"];
						$i = $j;
					}
				}

			} else if($w === 'INTERVAL') {
				// INTERVAL 5 DAY => '+5 day' string modifier consumed by date_add/date_sub functions
				$j = $this->next($tokens, $i + 1);
				$k = $j > -1 ? $this->next($tokens, $j + 1) : -1;
				if($j > -1 && $k > -1 && $tokens[$k][0] === 'word') {
					$unit = strtolower($tokens[$k][1]);
					$amount = $tokens[$j];
					// quoted amount like INTERVAL '5' DAY: unquote only when numeric, otherwise keep as quoted string
					if($amount[0] === 'str' && is_numeric(trim($amount[1], "'"))) $amount = ['num', trim($amount[1], "'")];
					$out[] = ['word', "interval_str(" . ($amount[0] === 'num' ? $amount[1] : $this->join([$amount])) . ", '$unit')"];
					$i = $k;
					// "expr - INTERVAL" or "expr + INTERVAL" is converted to date_add(expr, ...)
					$this->intervalArithmetic($out);
				} else {
					$out[] = $t;
				}

			} else if($w === 'GROUP_CONCAT' && ($j = $this->next($tokens, $i + 1)) > -1 && $tokens[$j][1] === '(') {
				$end = $this->matchParen($tokens, $j);
				$out[] = ['word', $this->groupConcat(array_slice($tokens, $j + 1, $end - $j - 1))];
				$i = $end;

			} else if($w === 'CAST' || $w === 'CONVERT') {
				// CAST(x AS UNSIGNED) etc.
				$out[] = $t;
				$j = $this->next($tokens, $i + 1);
				if($j > -1 && $tokens[$j][1] === '(') {
					$end = $this->matchParen($tokens, $j);
					for($x = $i + 1; $x <= $end; $x++) {
						$tx = $tokens[$x];
						if($this->isWord($tx, 'AS')) {
							$y = $this->next($tokens, $x + 1);
							if($y > -1) {
								$type = strtoupper($tokens[$y][1]);
								$map = [
									'UNSIGNED' => 'INTEGER', 'SIGNED' => 'INTEGER', 'CHAR' => 'TEXT',
									'DECIMAL' => 'NUMERIC', 'DATETIME' => 'TEXT', 'DATE' => 'TEXT', 'BINARY' => 'BLOB',
								];
								if(isset($map[$type])) {
									$out[] = $tx;
									$out[] = ['ws', ' '];
									$out[] = ['word', $map[$type]];
									// skip optional INTEGER / (n) / (n,n) following type
									$x = $y;
									$z = $this->next($tokens, $x + 1);
									if($z > -1 && $this->isWord($tokens[$z], 'INTEGER')) $x = $z;
									$z = $this->next($tokens, $x + 1);
									if($z > -1 && $tokens[$z][1] === '(' && $z < $end) $x = $this->matchParen($tokens, $z);
									continue;
								}
							}
						}
						$out[] = $tx;
					}
					$i = $end;
				}

			} else if($w === 'BINARY') {
				// "BINARY x" (case-sensitive comparison) => "x COLLATE BINARY", overriding the column's NOCASE collation
				$j = $this->next($tokens, $i + 1);
				$end = $j > -1 ? $this->operandEnd($tokens, $j) : -1;
				if($end > -1) {
					foreach($this->expressions(array_slice($tokens, $j, $end - $j + 1)) as $tx) $out[] = $tx;
					$out[] = ['word', ' COLLATE BINARY'];
					$i = $end;
					continue;
				}
				$out[] = $t;

			} else {
				$out[] = $t;
			}
		}

		return $out;
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
	 * Translate the arguments of GROUP_CONCAT([DISTINCT] expr [ORDER BY ...] [SEPARATOR 'x'])
	 *
	 * The plain form uses SQLite's group_concat(expr, separator). DISTINCT and ORDER BY use the
	 * pw_group_concat() aggregate function (see registerFunctions()), since SQLite's group_concat()
	 * does not support DISTINCT with a separator, and supports ORDER BY only in SQLite 3.44+.
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
		$expr = count($exprs) > 1 ? 'concat(' . implode(', ', $exprs) . ')' : $exprs[0];
		$separator = "','";
		if($sepPos > -1) {
			$y = $this->next($tokens, $sepPos + 1);
			if($y > -1) $separator = $this->join([$tokens[$y]]);
		}
		if(!$distinct && $orderPos < 0) {
			return "group_concat($expr, $separator)";
		}
		$args = [$expr, $separator, $distinct ? '1' : '0'];
		if($orderPos > -1) {
			$y = $this->next($tokens, $orderPos + 1); // BY
			$orderEnd = $sepPos > $orderPos ? $sepPos : count($tokens);
			foreach($this->splitCommas(array_slice($tokens, $y + 1, $orderEnd - $y - 1)) as $term) {
				$term = $this->trimTokens($term);
				$desc = '0';
				$last = count($term) ? $term[count($term) - 1] : null;
				if($this->isWord($last, ['ASC', 'DESC'])) {
					$desc = $this->isWord($last, 'DESC') ? '1' : '0';
					$term = $this->trimTokens(array_slice($term, 0, -1));
				}
				$args[] = trim($this->join($this->expressions($term)));
				$args[] = $desc;
			}
		}
		return 'pw_group_concat(' . implode(', ', $args) . ')';
	}

	/**
	 * Convert "expr - interval_str(...)" at end of $out to "date_sub(expr, interval_str(...))"
	 *
	 * Only handles simple expressions: a function call, a column name, or a literal/param.
	 *
	 * @param array $out
	 *
	 */
	protected function intervalArithmetic(array &$out) {
		$interval = array_pop($out);
		$out = $this->trimTokens($out);
		$n = count($out);
		$op = $n ? $out[$n - 1] : null;
		if(!$op || $op[0] !== 'punct' || ($op[1] !== '-' && $op[1] !== '+')) {
			$out[] = ['ws', ' '];
			$out[] = $interval;
			return;
		}
		array_pop($out);
		$out = $this->trimTokens($out);
		// find start of preceding operand
		$end = count($out) - 1;
		$start = $end;
		if($end >= 0 && $out[$end][0] === 'punct' && $out[$end][1] === ')') {
			$depth = 0;
			for($x = $end; $x >= 0; $x--) {
				if($out[$x][0] === 'punct' && $out[$x][1] === ')') $depth++;
				if($out[$x][0] === 'punct' && $out[$x][1] === '(') $depth--;
				if($depth === 0) break;
			}
			$start = $x;
			if($start > 0 && $out[$start - 1][0] === 'word') $start--;
		} else {
			// table.column
			while($start >= 2 && $out[$start - 1][1] === '.' && in_array($out[$start - 2][0], ['word', 'id'])) $start -= 2;
		}
		$operand = array_splice($out, $start);
		$func = $op[1] === '-' ? 'date_sub' : 'date_add';
		$out[] = ['word', "$func(" . $this->join($operand) . ', ' . $interval[1] . ')'];
	}

	/*********************************************************************************
	 * Statement-level translations
	 *
	 */

	/**
	 * INSERT/REPLACE: INSERT IGNORE, INSERT ... SET, ON DUPLICATE KEY UPDATE
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function insert(array $tokens) {

		$n = count($tokens);
		$i = $this->next($tokens, 0);
		$out = array_slice($tokens, 0, $i + 1); // INSERT or REPLACE
		$i++;

		// INSERT [LOW_PRIORITY|DELAYED|HIGH_PRIORITY] [IGNORE] [INTO]
		while(($j = $this->next($tokens, $i)) > -1) {
			$t = $tokens[$j];
			if($this->isWord($t, ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY'])) {
				$i = $j + 1;
			} else if($this->isWord($t, 'IGNORE')) {
				$out[] = ['ws', ' '];
				$out[] = ['word', 'OR IGNORE'];
				$i = $j + 1;
			} else {
				break;
			}
		}

		// find SET and ON DUPLICATE positions at top level
		$setPos = -1;
		$dupPos = -1;
		$depth = 0;
		for($x = $i; $x < $n; $x++) {
			$t = $tokens[$x];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0) continue;
			if($setPos === -1 && $dupPos === -1 && $this->isWord($t, 'SET')) {
				// make sure this is the INSERT ... SET form: the preceding word should be the table name
				$setPos = $x;
			} else if($this->isWord($t, 'ON')) {
				$y = $this->next($tokens, $x + 1);
				if($y > -1 && $this->isWord($tokens[$y], 'DUPLICATE')) {
					$dupPos = $x;
					break;
				}
			}
		}

		$bodyEnd = $dupPos > -1 ? $dupPos : $n;

		if($setPos > -1) {
			// INSERT INTO table SET a=1, b=2 => INSERT INTO table (a, b) VALUES (1, 2)
			for($x = $i; $x < $setPos; $x++) $out[] = $tokens[$x];
			$assigns = $this->splitCommas(array_slice($tokens, $setPos + 1, $bodyEnd - $setPos - 1));
			$cols = [];
			$vals = [];
			foreach($assigns as $assign) {
				$assign = $this->trimTokens($assign);
				$eq = -1;
				foreach($assign as $k => $t) {
					if($t[0] === 'punct' && $t[1] === '=') { $eq = $k; break; }
				}
				if($eq < 0) continue;
				$cols[] = trim($this->join(array_slice($assign, 0, $eq)));
				$vals[] = trim($this->join($this->expressions(array_slice($assign, $eq + 1))));
			}
			$out[] = ['word', '(' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'];
		} else {
			$body = $this->expressions(array_slice($tokens, $i, $bodyEnd - $i));
			$selectPos = -1;
			if($dupPos > -1) {
				$depth = 0;
				foreach($body as $x => $t) {
					if($t[0] === 'punct') {
						if($t[1] === '(') $depth++;
						if($t[1] === ')') $depth--;
					} else if($depth === 0 && $this->isWord($t, 'SELECT')) {
						$selectPos = $x;
						break;
					}
				}
			}
			if($selectPos > -1) {
				// INSERT ... SELECT ... ON CONFLICT needs a WHERE clause directly before ON CONFLICT to avoid
				// parsing ambiguity, so wrap the SELECT (which may end with ORDER BY, LIMIT, etc.)
				foreach(array_slice($body, 0, $selectPos) as $t) $out[] = $t;
				$select = trim($this->join(array_slice($body, $selectPos)));
				$out[] = ['word', "SELECT * FROM ($select) WHERE true"];
			} else {
				foreach($body as $t) $out[] = $t;
			}
		}

		if($dupPos > -1) {
			// ON DUPLICATE KEY UPDATE a=VALUES(a), b=b+1 => ON CONFLICT DO UPDATE SET a=excluded.a, b=b+1
			$x = $this->next($tokens, $dupPos + 1); // DUPLICATE
			$x = $this->next($tokens, $x + 1); // KEY
			$x = $this->next($tokens, $x + 1); // UPDATE
			$updates = array_slice($tokens, $x + 1);
			$u = [];
			$m = count($updates);
			for($y = 0; $y < $m; $y++) {
				$t = $updates[$y];
				if($this->isWord($t, 'VALUES')) {
					$z = $this->next($updates, $y + 1);
					if($z > -1 && $updates[$z][1] === '(') {
						$end = $this->matchParen($updates, $z);
						$inner = $this->trimTokens(array_slice($updates, $z + 1, $end - $z - 1));
						$u[] = ['word', 'excluded.' . $this->quoteId($this->name($inner[0]))];
						$y = $end;
						continue;
					}
				}
				$u[] = $t;
			}
			$out[] = ['ws', ' '];
			$out[] = ['word', 'ON CONFLICT DO UPDATE SET'];
			foreach($this->expressions($u) as $t) $out[] = $t;
		}

		return $out;
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
	 * DELETE ... LIMIT n (SQLite typically compiled without support for it)
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function deleteLimit(array $tokens) {
		$i = $this->next($tokens, 0); // DELETE
		$j = $this->next($tokens, $i + 1);
		if($j > -1 && !$this->isWord($tokens[$j], ['FROM', 'LOW_PRIORITY', 'QUICK', 'IGNORE'])) {
			return $this->deleteMulti($tokens, $j);
		}
		if(!$this->hasTopLevelWord($tokens, 'LIMIT')) return $tokens;
		// DELETE FROM t WHERE x ORDER BY y LIMIT n => DELETE FROM t WHERE rowid IN (SELECT rowid FROM t WHERE x ORDER BY y LIMIT n)
		$i = $this->next($tokens, 0); // DELETE
		$j = $this->next($tokens, $i + 1); // FROM
		if(!$this->isWord($tokens[$j], 'FROM')) return $tokens; // multi-table DELETE not handled
		$k = $this->next($tokens, $j + 1); // table
		$table = $this->join([$tokens[$k]]);
		$rest = array_slice($tokens, $k + 1);
		return [['word', "DELETE FROM $table WHERE rowid IN (SELECT rowid FROM $table" . $this->join($this->expressions($rest)) . ')']];
	}

	/**
	 * Multi-table DELETE: DELETE t FROM t [AS a] JOIN ... WHERE ...
	 *
	 * Translated to DELETE FROM t WHERE rowid IN (SELECT t.rowid FROM t JOIN ... WHERE ...).
	 * Only a single target table is supported.
	 *
	 * @param array $tokens
	 * @param int $j Index of first target token
	 * @return array
	 *
	 */
	protected function deleteMulti(array $tokens, $j) {
		$fromPos = -1;
		foreach($tokens as $x => $t) {
			if($x > $j && $this->isWord($t, 'FROM')) { $fromPos = $x; break; }
		}
		$targets = $fromPos > -1 ? $this->splitCommas(array_slice($tokens, $j, $fromPos - $j)) : [];
		if(count($targets) !== 1) throw new \PDOException('SQLite translator: unsupported multi-table DELETE (one target table is supported)');
		$target = $this->trimTokens($targets[0]);
		$target = $this->name($target[count($target) - 1]); // i.e. "t" or "t.*"
		if($target === '*' && count($targets[0]) > 2) $target = $this->name($this->trimTokens($targets[0])[0]);
		// find the table the target refers to (by name or alias) in the FROM clause
		$k = $this->next($tokens, $fromPos + 1);
		$table = $this->name($tokens[$k]);
		$alias = $table;
		$a = $this->next($tokens, $k + 1);
		if($a > -1 && $this->isWord($tokens[$a], 'AS')) $a = $this->next($tokens, $a + 1);
		if($a > -1 && in_array($tokens[$a][0], ['word', 'id']) && !$this->isWord($tokens[$a], ['JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'WHERE', 'NATURAL', 'STRAIGHT_JOIN'])) {
			$alias = $this->name($tokens[$a]);
		}
		if($target !== $table && $target !== $alias) {
			throw new \PDOException('SQLite translator: unsupported multi-table DELETE (target must be the first table in FROM)');
		}
		$from = $this->join($this->expressions(array_slice($tokens, $fromPos + 1)));
		return [['word', 'DELETE FROM ' . $this->quoteId($table) . ' WHERE rowid IN (SELECT ' . $this->quoteId($alias) . ".rowid FROM$from)"]];
	}

	/**
	 * UPDATE ... ORDER BY ... [LIMIT n] (not supported by typical SQLite builds)
	 *
	 * ORDER BY is removed. When LIMIT is present, rows are selected by rowid subquery.
	 * Note that MySQL code using ORDER BY to avoid unique key collisions during an update
	 * (i.e. SET sort=sort+1 ... ORDER BY sort DESC) needs a different approach in SQLite.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function updateOrderLimit(array $tokens) {
		$orderPos = -1;
		$limitPos = -1;
		$wherePos = -1;
		$depth = 0;
		foreach($tokens as $x => $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0) continue;
			if($this->isWord($t, 'WHERE') && $wherePos < 0) $wherePos = $x;
			if($this->isWord($t, 'ORDER') && $orderPos < 0) $orderPos = $x;
			if($this->isWord($t, 'LIMIT')) $limitPos = $x;
		}
		if($orderPos < 0 && $limitPos < 0) return $tokens;
		$end = $orderPos > -1 ? $orderPos : $limitPos;
		if($limitPos < 0) return array_slice($tokens, 0, $orderPos);
		// UPDATE t SET ... WHERE w ORDER BY o LIMIT n => UPDATE t SET ... WHERE rowid IN (SELECT rowid FROM t WHERE w ORDER BY o LIMIT n)
		$i = $this->next($tokens, $this->next($tokens, 0) + 1); // table
		$table = $this->join([$tokens[$i]]);
		$setEnd = $wherePos > -1 ? $wherePos : $end;
		$head = array_slice($tokens, 0, $setEnd);
		$sub = 'SELECT rowid FROM ' . $table . ' ' . $this->join(array_slice($tokens, $setEnd));
		$head[] = ['word', " WHERE rowid IN ($sub)"];
		return $head;
	}

	/**
	 * CREATE [UNIQUE|FULLTEXT] INDEX name ON table (cols)
	 *
	 * @param array $tokens
	 * @return string
	 *
	 */
	protected function createIndex(array $tokens) {
		$onPos = -1;
		foreach($tokens as $x => $t) {
			if($this->isWord($t, 'ON')) { $onPos = $x; break; }
		}
		if($onPos < 0) return $this->join($tokens);
		$ifNotExists = $this->hasTopLevelWord(array_slice($tokens, 0, $onPos), 'EXISTS');
		$def = array_slice($tokens, 0, $onPos);
		$def = array_values(array_filter($def, function($t) {
			return !($t[0] === 'word' && in_array(strtoupper($t[1]), ['CREATE', 'IF', 'NOT', 'EXISTS']));
		}));
		$i = $this->next($tokens, $onPos + 1);
		$table = $this->name($tokens[$i]);
		foreach(array_slice($tokens, $i + 1) as $t) $def[] = $t;
		return $this->indexDef($table, $def, $ifNotExists);
	}

	/**
	 * RENAME TABLE a TO b [, c TO d ...]
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function renameTable(array $tokens) {
		$i = $this->next($tokens, $this->next($tokens, 0) + 1); // TABLE
		$statements = [];
		foreach($this->splitCommas(array_slice($tokens, $i + 1)) as $pair) {
			$pair = array_values(array_filter($pair, function($t) { return $t[0] !== 'ws'; }));
			if(count($pair) !== 3 || !$this->isWord($pair[1], 'TO')) {
				throw new \PDOException('SQLite translator: unsupported RENAME TABLE syntax');
			}
			foreach($this->renameTableStatements($this->name($pair[0]), $this->name($pair[2])) as $sql) {
				$statements[] = $sql;
			}
		}
		return $statements;
	}

	/**
	 * Get statements to rename a table, including its (table-prefixed) index names
	 *
	 * SQLite keeps index names when a table is renamed, so indexes are recreated with the new
	 * table prefix (requires a PDO connection, see constructor).
	 *
	 * @param string $from
	 * @param string $to
	 * @return array
	 *
	 */
	protected function renameTableStatements($from, $to) {
		$statements = ['ALTER TABLE ' . $this->quoteId($from) . ' RENAME TO ' . $this->quoteId($to)];
		foreach($this->getIndexes($from) as $index) {
			if($index['name'] === null) continue; // not a table-prefixed index created by this translator
			$statements[] = 'DROP INDEX ' . $this->quoteId($index['sqliteName']);
			$statements[] = $this->createIndexSql($to, $index['name'], $index['columns'], $index['unique']);
		}
		return $statements;
	}

	/**
	 * Get explicitly created indexes for a table (requires PDO connection)
	 *
	 * @param string $table
	 * @return array Each with 'sqliteName', 'name' (without table prefix, or null if not prefixed), 'unique', 'columns'
	 *
	 */
	protected function getIndexes($table) {
		$pdo = $this->pdo();
		if(!$pdo) return [];
		$indexes = [];
		$prefix = $table . self::indexSeparator;
		foreach($pdo->query('SELECT * FROM pragma_index_list(' . $pdo->quote($table) . ')')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			if($row['origin'] !== 'c') continue;
			$columns = $pdo->query('SELECT name FROM pragma_index_info(' . $pdo->quote($row['name']) . ') ORDER BY seqno')->fetchAll(\PDO::FETCH_COLUMN);
			$indexes[] = [
				'sqliteName' => $row['name'],
				'name' => strpos($row['name'], $prefix) === 0 ? substr($row['name'], strlen($prefix)) : null,
				'unique' => (bool) $row['unique'],
				'columns' => $columns,
			];
		}
		return $indexes;
	}

	/**
	 * Get CREATE INDEX statement
	 *
	 * @param string $table
	 * @param string $name Index name without table prefix
	 * @param array $columns
	 * @param bool $unique
	 * @param bool $ifNotExists
	 * @return string
	 *
	 */
	protected function createIndexSql($table, $name, array $columns, $unique = false, $ifNotExists = false) {
		return 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') .
			$this->quoteId($this->indexName($table, $name)) . ' ON ' . $this->quoteId($table) .
			' (' . implode(', ', array_map([$this, 'quoteId'], $columns)) . ')';
	}

	/**
	 * TRUNCATE [TABLE] t
	 *
	 * Deletes all rows and (when a PDO connection is available) resets the AUTOINCREMENT sequence.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function truncate(array $tokens) {
		$i = $this->next($tokens, $this->next($tokens, 0) + 1);
		if($this->isWord($tokens[$i], 'TABLE')) $i = $this->next($tokens, $i + 1);
		$table = $this->name($tokens[$i]);
		$statements = ['DELETE FROM ' . $this->quoteId($table)];
		$pdo = $this->pdo();
		if($pdo && $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'")->fetchColumn()) {
			$statements[] = 'DELETE FROM sqlite_sequence WHERE name=' . $pdo->quote($table);
		}
		return $statements;
	}

	/**
	 * DROP INDEX name ON table
	 *
	 * @param array $tokens
	 * @return string
	 *
	 */
	protected function dropIndex(array $tokens) {
		$i = $this->next($tokens, $this->next($tokens, $this->next($tokens, 0) + 1) + 1);
		$index = $this->name($tokens[$i]);
		$j = $this->next($tokens, $i + 1);
		$k = $this->next($tokens, $j + 1);
		$table = $this->name($tokens[$k]);
		return 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $index));
	}

	/**
	 * SHOW statements
	 *
	 * @param array $tokens
	 * @return string
	 *
	 */
	protected function show(array $tokens) {

		$words = [];
		$rest = [];
		$table = '';
		foreach($tokens as $x => $t) {
			if($t[0] === 'ws') continue;
			if($words === ['SHOW', 'CREATE', 'TABLE']) {
				// SHOW CREATE TABLE name: table name follows directly (no FROM)
				$table = $this->name($t);
				$rest = array_slice($tokens, $x + 1);
				break;
			}
			if($t[0] === 'word' && count($rest) === 0 && !in_array(strtoupper($t[1]), ['FROM', 'IN', 'LIKE', 'WHERE'])) {
				$words[] = strtoupper($t[1]);
			} else {
				$rest = array_slice($tokens, $x);
				break;
			}
		}

		array_shift($words); // SHOW
		$what = implode(' ', array_diff($words, ['FULL', 'GLOBAL', 'SESSION', 'EXTENDED']));

		// parse FROM table, LIKE x, WHERE ...
		$like = null;
		$where = '';
		$n = count($rest);
		for($x = 0; $x < $n; $x++) {
			$t = $rest[$x];
			if($this->isWord($t, ['FROM', 'IN']) && $table === '') {
				$y = $this->next($rest, $x + 1);
				$table = $this->name($rest[$y]);
				$x = $y;
			} else if($this->isWord($t, 'LIKE')) {
				$y = $this->next($rest, $x + 1);
				$like = $this->join([$rest[$y]]);
				$x = $y;
			} else if($this->isWord($t, 'WHERE')) {
				$where = $this->join($this->expressions(array_slice($rest, $x + 1)));
				break;
			}
		}

		$qt = "'" . str_replace("'", "''", $table) . "'";
		$sql = '';

		switch($what) {
			case 'TABLES':
				$sql = "SELECT name AS " . $this->quoteId('Tables_in_' . $this->databaseName) . " FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\'";
				if($like !== null) $sql .= " AND name LIKE $like ESCAPE '\\'";
				$sql .= " ORDER BY name";
				return $sql;

			case 'COLUMNS':
			case 'FIELDS':
				$sql =
					"SELECT name AS `Field`, type AS `Type`, " .
					"CASE WHEN \"notnull\" THEN 'NO' ELSE 'YES' END AS `Null`, " .
					"CASE WHEN pk > 0 THEN 'PRI' ELSE '' END AS `Key`, " .
					"dflt_value AS `Default`, " .
					"CASE WHEN pk > 0 AND lower(type)='integer' AND (SELECT sql FROM sqlite_master WHERE name=$qt) LIKE '%AUTOINCREMENT%' THEN 'auto_increment' ELSE '' END AS `Extra` " .
					"FROM pragma_table_info($qt)";
				if($like !== null) $sql = "SELECT * FROM ($sql) WHERE `Field` LIKE $like";
				if($where !== '') $sql = "SELECT * FROM ($sql) WHERE $where";
				return $sql;

			case 'INDEX':
			case 'INDEXES':
			case 'KEYS':
				$prefixLen = strlen($table . self::indexSeparator) + 1;
				$sql =
					"SELECT $qt AS `Table`, " .
					"CASE WHEN il.origin='pk' THEN 'PRIMARY' WHEN il.origin='c' THEN substr(il.name, $prefixLen) ELSE il.name END AS `Key_name`, " .
					"CASE WHEN il.\"unique\" THEN 0 ELSE 1 END AS `Non_unique`, " .
					"ii.seqno + 1 AS `Seq_in_index`, ii.name AS `Column_name`, 'BTREE' AS `Index_type` " .
					"FROM pragma_index_list($qt) il JOIN pragma_index_info(il.name) ii " .
					"UNION ALL " .
					"SELECT $qt, 'PRIMARY', 0, 1, ti.name, 'BTREE' FROM pragma_table_info($qt) ti " .
					"WHERE ti.pk > 0 AND NOT EXISTS (SELECT 1 FROM pragma_index_list($qt) WHERE origin='pk')";
				$sql = "SELECT * FROM ($sql) ORDER BY `Key_name`, `Seq_in_index`";
				if($where !== '') $sql = "SELECT * FROM ($sql) WHERE $where";
				return $sql;

			case 'CREATE TABLE':
				// MySQL-syntax CREATE TABLE (with indexes) rebuilt from the SQLite schema, see mysqlCreateTable()
				return "SELECT name AS `Table`, pw_show_create_table(name) AS `Create Table` FROM sqlite_master WHERE type='table' AND name=$qt";

			case 'TABLE STATUS':
				$sql =
					"SELECT name AS `Name`, 'SQLite' AS `Engine`, NULL AS `Rows`, NULL AS `Data_length`, " .
					"NULL AS `Index_length`, NULL AS `Auto_increment`, NULL AS `Collation` " .
					"FROM sqlite_master WHERE type='table'";
				if($like !== null) $sql .= " AND name LIKE $like";
				if($where !== '') $sql = "SELECT * FROM ($sql) WHERE " . str_ireplace('name', '`Name`', $where);
				return $sql;

			case 'VARIABLES':
			case 'STATUS':
			case 'WARNINGS':
			case 'ENGINES':
			case 'CHARACTER SET':
			case 'COLLATION':
				return "SELECT NULL AS `Variable_name`, NULL AS `Value` WHERE 0";
		}

		return 'SELECT NULL WHERE 0';
	}

	/*********************************************************************************
	 * DDL
	 *
	 */

	/**
	 * CREATE TABLE
	 *
	 * @param array $tokens
	 * @return string|array Array when there are indexes (CREATE TABLE followed by CREATE INDEX statements)
	 *
	 */
	protected function createTable(array $tokens) {

		// locate table name and definition parens
		$open = -1;
		$ifNotExists = false;
		$temporary = false;
		$table = '';
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] === 'punct' && $t[1] === '(') { $open = $i; break; }
			if($this->isWord($t, 'TEMPORARY')) $temporary = true;
			if($this->isWord($t, 'EXISTS')) $ifNotExists = true;
			if($t[0] === 'id' || ($t[0] === 'word' && !$this->isWord($t, ['CREATE', 'TABLE', 'TEMPORARY', 'IF', 'NOT', 'EXISTS']))) {
				$table = $this->name($t);
			}
		}

		if($open < 0) {
			// CREATE TABLE x LIKE y, CREATE TABLE x SELECT ...: pass through with expression translation
			return $this->join($this->expressions($tokens));
		}

		$close = $this->matchParen($tokens, $open);
		$defs = $this->splitCommas(array_slice($tokens, $open + 1, $close - $open - 1));

		$columns = [];
		$constraints = [];
		$indexes = [];
		$autoIncrementCol = '';
		$primaryCols = [];

		foreach($defs as $def) {
			$def = $this->trimTokens($def);
			if(!count($def)) continue;
			$first = $def[0];

			if($this->isWord($first, 'PRIMARY')) {
				$primaryCols = $this->indexColumns($def);

			} else if($this->isWord($first, ['KEY', 'INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL'])) {
				$indexes[] = $this->indexDef($table, $def, $ifNotExists);

			} else if($this->isWord($first, ['CONSTRAINT', 'FOREIGN', 'CHECK'])) {
				$constraints[] = $this->join($def);

			} else {
				$col = $this->columnDef($def);
				if($col['autoIncrement']) $autoIncrementCol = $col['name'];
				if($col['primary']) $primaryCols = [$col['name']];
				$columns[$col['name']] = $col;
			}
		}

		$lines = [];
		foreach($columns as $name => $col) {
			$line = $this->quoteId($name) . ' ' . $col['sql'];
			if($autoIncrementCol === $name && count($primaryCols) <= 1) {
				// only way to get auto-increment in SQLite
				$line = $this->quoteId($name) . ' INTEGER PRIMARY KEY AUTOINCREMENT' . ($col['extra'] ? " $col[extra]" : '');
				$primaryCols = [];
			} else if($col['primary']) {
				$line .= ' PRIMARY KEY';
				$primaryCols = [];
			}
			$lines[] = $line;
		}

		if(count($primaryCols)) {
			$lines[] = 'PRIMARY KEY (' . implode(', ', array_map([$this, 'quoteId'], $primaryCols)) . ')';
		}

		foreach($constraints as $c) $lines[] = $c;

		$statements = [
			'CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') .
			$this->quoteId($table) . " (\n  " . implode(",\n  ", $lines) . "\n)"
		];

		foreach($indexes as $index) $statements[] = $index;

		return $statements;
	}

	/**
	 * Parse a column definition
	 *
	 * @param array $def Tokens
	 * @return array
	 *
	 */
	protected function columnDef(array $def) {

		$name = $this->name($def[0]);
		$parts = [];
		$extra = [];
		$type = '';
		$autoIncrement = false;
		$primary = false;
		$isText = false;
		$n = count($def);

		// type: word plus optional (n) or enum(...)
		$i = $this->next($def, 1);
		$typeWord = strtoupper($def[$i][1]);
		$typeArgs = '';
		$j = $this->next($def, $i + 1);
		if($j > -1 && $def[$j][1] === '(') {
			$end = $this->matchParen($def, $j);
			$typeArgs = $this->join(array_slice($def, $j, $end - $j + 1));
			$i = $end;
		}

		if(in_array($typeWord, ['ENUM', 'SET'])) {
			$type = 'TEXT';
			$isText = true;
		} else if(in_array($typeWord, ['BOOL', 'BOOLEAN'])) {
			$type = 'TINYINT';
		} else {
			$type = $typeWord . ($typeArgs && !preg_match('/INT$/', $typeWord) ? $typeArgs : '');
			$isText = (bool) preg_match('/CHAR|TEXT|CLOB/', $typeWord);
		}

		for($x = $i + 1; $x < $n; $x++) {
			$t = $def[$x];
			if($t[0] === 'ws') continue;
			$w = $t[0] === 'word' ? strtoupper($t[1]) : '';

			if($w === 'UNSIGNED' || $w === 'SIGNED' || $w === 'ZEROFILL') {
				continue;
			} else if($w === 'AUTO_INCREMENT') {
				$autoIncrement = true;
			} else if($w === 'PRIMARY') {
				$primary = true;
				$x = $this->next($def, $x + 1); // KEY
			} else if($w === 'UNIQUE') {
				$extra[] = 'UNIQUE';
				$y = $this->next($def, $x + 1);
				if($y > -1 && $this->isWord($def[$y], 'KEY')) $x = $y;
			} else if($w === 'CHARACTER' || $w === 'CHARSET' || $w === 'COLLATE') {
				// CHARACTER SET x, CHARSET x, COLLATE x
				$y = $this->next($def, $x + 1);
				if($w === 'CHARACTER') $y = $this->next($def, $y + 1);
				$x = $y;
			} else if($w === 'COMMENT') {
				$x = $this->next($def, $x + 1);
			} else if($w === 'ON') {
				// ON UPDATE CURRENT_TIMESTAMP
				$y = $this->next($def, $x + 1); // UPDATE
				$z = $y > -1 ? $this->next($def, $y + 1) : -1; // CURRENT_TIMESTAMP
				if($z === -1) break;
				$p = $this->next($def, $z + 1);
				if($p > -1 && $def[$p][1] === '(') $z = $this->matchParen($def, $p);
				$x = $z;
				// @todo emulate with trigger
			} else if($w === 'DEFAULT') {
				$y = $this->next($def, $x + 1);
				$v = $def[$y];
				if($this->isWord($v, ['CURRENT_TIMESTAMP', 'NOW', 'LOCALTIME', 'LOCALTIMESTAMP'])) {
					$z = $this->next($def, $y + 1);
					if($z > -1 && $def[$z][1] === '(') $y = $this->matchParen($def, $z);
					// SQLite's CURRENT_TIMESTAMP is UTC, whereas MySQL's is in the server's (system) time zone
					$extra[] = 'DEFAULT ' . self::currentTimestampDefault;
					$nonConstantDefault = true;
				} else if($v[1] === '(') {
					$end = $this->matchParen($def, $y);
					$extra[] = 'DEFAULT ' . $this->join(array_slice($def, $y, $end - $y + 1));
					$y = $end;
				} else if($v[0] === 'punct' && $v[1] === '-') {
					$z = $this->next($def, $y + 1);
					$extra[] = 'DEFAULT -' . $def[$z][1];
					$y = $z;
				} else {
					$extra[] = 'DEFAULT ' . $this->join([$v]);
				}
				$x = $y;
			} else if($w === 'NOT') {
				$y = $this->next($def, $x + 1);
				$extra[] = 'NOT NULL';
				$x = $y;
			} else if($w === 'NULL') {
				// nullable is the default
			} else if($w === 'AFTER' || $w === 'FIRST') {
				if($w === 'AFTER') $x = $this->next($def, $x + 1);
			} else {
				$extra[] = $this->join([$t]);
			}
		}

		$sql = $type;
		if($isText) $sql .= ' COLLATE NOCASE';
		$extraSql = implode(' ', $extra);
		if(!isset($nonConstantDefault)) $nonConstantDefault = false;
		if($extraSql !== '') $sql .= " $extraSql";

		return [
			'name' => $name,
			'sql' => $sql,
			'type' => $type,
			'extra' => $extraSql,
			'autoIncrement' => $autoIncrement,
			'primary' => $primary,
			'nonConstantDefault' => $nonConstantDefault,
		];
	}

	/**
	 * Get column names from an index definition: KEY name (a, b(10))
	 *
	 * @param array $def Tokens
	 * @return array
	 *
	 */
	protected function indexColumns(array $def) {
		$open = -1;
		foreach($def as $k => $t) {
			if($t[0] === 'punct' && $t[1] === '(') { $open = $k; break; }
		}
		if($open < 0) return [];
		$close = $this->matchParen($def, $open);
		$cols = [];
		foreach($this->splitCommas(array_slice($def, $open + 1, $close - $open - 1)) as $part) {
			$part = $this->trimTokens($part);
			if(!count($part)) continue;
			$cols[] = $this->name($part[0]); // drops prefix length (n) and ASC/DESC
		}
		return $cols;
	}

	/**
	 * Get index name from index definition (or null if not named)
	 *
	 * @param array $def
	 * @return string|null
	 *
	 */
	protected function indexDefName(array $def) {
		foreach($def as $t) {
			if($t[0] === 'ws') continue;
			if($t[0] === 'punct') return null;
			if($t[0] === 'id') return $t[1];
			if($t[0] === 'word' && !$this->isWord($t, ['KEY', 'INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL', 'ADD', 'CONSTRAINT'])) return $t[1];
		}
		return null;
	}

	/**
	 * Get CREATE INDEX statement from MySQL index definition
	 *
	 * @param string $table
	 * @param array $def
	 * @param bool $ifNotExists
	 * @return string
	 *
	 */
	protected function indexDef($table, array $def, $ifNotExists = false) {
		$unique = false;
		foreach($def as $t) {
			if($this->isWord($t, 'UNIQUE')) $unique = true;
			if($t[0] === 'punct') break;
		}
		$cols = $this->indexColumns($def);
		$name = $this->indexDefName($def);
		if($name === null) $name = $cols[0];
		// note: FULLTEXT indexes become regular indexes (fulltext queries use LIKE/REGEXP)
		return $this->createIndexSql($table, $name, $cols, $unique, $ifNotExists);
	}

	/**
	 * ALTER TABLE
	 *
	 * Operations SQLite supports natively (ADD/DROP/RENAME COLUMN, index changes, RENAME TO) are
	 * translated directly. When any operation requires a table rebuild (MODIFY, CHANGE, ADD/DROP
	 * PRIMARY KEY), all column operations in the statement are applied by one rebuild, so that
	 * they are all based on the same schema, and index operations follow it.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function alterTable(array $tokens) {

		$i = $this->next($tokens, $this->next($tokens, 0) + 1); // after ALTER, at TABLE
		$i = $this->next($tokens, $i + 1);
		$table = $this->name($tokens[$i]);
		$qTable = $this->quoteId($table);
		$specs = $this->splitCommas(array_slice($tokens, $i + 1));
		$columnOps = []; // column operations: [ 'action' => ..., 'sql' => native ALTER statement, ... ]
		$otherOps = []; // index and table rename statements
		$renameTo = '';
		$rebuild = false;

		foreach($specs as $spec) {
			$spec = $this->trimTokens($spec);
			if(!count($spec)) continue;
			$w = strtoupper($spec[0][1]);
			$rest = array_slice($spec, 1);
			$w2 = '';
			$j = $this->next($rest, 0);
			if($j > -1 && $rest[$j][0] === 'word') $w2 = strtoupper($rest[$j][1]);

			if($w === 'ADD') {
				if(in_array($w2, ['INDEX', 'KEY', 'UNIQUE', 'FULLTEXT', 'SPATIAL'])) {
					$otherOps[] = $this->indexDef($table, $rest);
				} else if($w2 === 'PRIMARY') {
					$columnOps[] = ['action' => 'primary', 'columns' => $this->indexColumns($rest)];
					$rebuild = true;
				} else if($w2 === 'CONSTRAINT') {
					throw new \PDOException("SQLite translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
				} else {
					if($w2 === 'COLUMN') $rest = array_slice($rest, $j + 1);
					$rest = $this->trimTokens($rest);
					$colDefs = [$rest];
					if(count($rest) && $rest[0][1] === '(') {
						// ADD (col1 def, col2 def)
						$end = $this->matchParen($rest, 0);
						$colDefs = $this->splitCommas(array_slice($rest, 1, $end - 1));
					}
					foreach($colDefs as $colDef) {
						$col = $this->columnDef($this->trimTokens($colDef));
						// SQLite cannot ADD COLUMN with a non-constant default to a table that has rows
						if($col['nonConstantDefault']) $rebuild = true;
						$columnOps[] = [
							'action' => 'add',
							'def' => $col,
							'sql' => "ALTER TABLE $qTable ADD COLUMN " . $this->quoteId($col['name']) . ' ' . $this->addColumnSql($col),
						];
					}
				}

			} else if($w === 'DROP') {
				if($w2 === 'INDEX' || $w2 === 'KEY') {
					$k = $this->next($rest, $j + 1);
					$otherOps[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $this->name($rest[$k])));
				} else if($w2 === 'PRIMARY') {
					$columnOps[] = ['action' => 'dropPrimary'];
					$rebuild = true;
				} else {
					if($w2 === 'COLUMN') $j = $this->next($rest, $j + 1);
					$column = $this->name($rest[$j]);
					// SQLite cannot drop an indexed column, whereas MySQL removes it from its indexes
					foreach($this->getIndexes($table) as $index) {
						if(in_array($column, $index['columns'], true)) $rebuild = true;
					}
					$columnOps[] = [
						'action' => 'drop',
						'column' => $column,
						'sql' => "ALTER TABLE $qTable DROP COLUMN " . $this->quoteId($column),
					];
				}

			} else if($w === 'RENAME') {
				if($w2 === 'COLUMN') {
					// RENAME COLUMN old TO new
					$parts = array_values(array_filter(array_slice($rest, $j + 1), function($t) { return $t[0] !== 'ws'; }));
					$old = $this->name($parts[0]);
					$new = $this->name($parts[2]);
					$columnOps[] = [
						'action' => 'rename',
						'column' => $old,
						'to' => $new,
						'sql' => "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($old) . ' TO ' . $this->quoteId($new),
					];
				} else if($w2 === 'INDEX' || $w2 === 'KEY') {
					throw new \PDOException("SQLite translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
				} else {
					// RENAME [TO|AS] new_name
					if($w2 === 'TO' || $w2 === 'AS') $j = $this->next($rest, $j + 1);
					$renameTo = $this->name($rest[$j]);
				}

			} else if($w === 'MODIFY' || $w === 'CHANGE') {
				if($w2 === 'COLUMN') $rest = array_slice($rest, $j + 1);
				$rest = $this->trimTokens($rest);
				$oldName = $this->name($rest[0]);
				if($w === 'CHANGE') {
					$rest = $this->trimTokens(array_slice($rest, 1));
				}
				$columnOps[] = ['action' => 'modify', 'column' => $oldName, 'def' => $this->columnDef($rest)];
				$rebuild = true;

			} else if(in_array($w, ['ENGINE', 'DEFAULT', 'CHARACTER', 'CHARSET', 'COLLATE', 'CONVERT', 'AUTO_INCREMENT', 'COMMENT', 'ORDER', 'ALGORITHM', 'LOCK'])) {
				// table options: no SQLite equivalent
				continue;

			} else {
				throw new \PDOException("SQLite translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
			}
		}

		$statements = [];

		if($rebuild) {
			$rebuildStatements = $this->rebuildTableStatements($table, $columnOps);
			if($rebuildStatements === null) {
				throw new \PDOException("SQLite translator: unable to rebuild table $table for ALTER TABLE: " . $this->join($tokens));
			}
			$statements = $rebuildStatements;
		} else {
			foreach($columnOps as $op) $statements[] = $op['sql'];
		}

		foreach($otherOps as $sql) $statements[] = $sql;

		if($renameTo !== '') {
			foreach($this->renameTableStatements($table, $renameTo) as $sql) $statements[] = $sql;
		}

		if(!count($statements)) return 'SELECT 1';

		return $statements;
	}

	/**
	 * Get statements that rebuild a table with column changes SQLite cannot make with ALTER TABLE
	 *
	 * Creates a new table with the changed columns, copies the rows, drops the old table, renames the
	 * new one and recreates indexes. Requires a PDO connection. Returns null (so the ALTER fails before
	 * changing anything) when the table has anything a rebuild would not preserve: triggers, table-level
	 * constraints (CHECK, FOREIGN KEY, UNIQUE constraints, etc.) or indexes not created by this translator.
	 *
	 * The returned statements must be executed atomically (see WireDatabaseDialectSQLite::execStatements()).
	 *
	 * @param string $table
	 * @param array $changes Column operations, each with 'action': modify, add, drop, rename, primary, dropPrimary
	 * @return array|null
	 *
	 */
	protected function rebuildTableStatements($table, array $changes) {

		$pdo = $this->pdo();
		if(!$pdo) return null;
		$qt = $pdo->quote($table);
		$createSql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=$qt")->fetchColumn();
		if(!$createSql) return null;

		// refuse anything a rebuild would not preserve
		if($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='trigger' AND tbl_name=$qt")->fetchColumn()) return null;
		$bareSql = preg_replace('/`[^`]*`|"[^"]*"|\'(?:[^\']|\'\')*\'/', '', $createSql); // without identifiers and strings
		if(preg_match('/\b(CHECK|CONSTRAINT|FOREIGN|REFERENCES|UNIQUE|GENERATED|WITHOUT)\b/i', $bareSql)) return null;
		$indexes = $this->getIndexes($table);
		foreach($pdo->query("SELECT origin FROM pragma_index_list($qt)")->fetchAll(\PDO::FETCH_COLUMN) as $origin) {
			if($origin === 'u') return null; // column-level UNIQUE constraint
		}
		foreach($indexes as $index) {
			if($index['name'] === null) return null; // index not created by this translator
		}

		$autoIncrement = stripos($bareSql, 'AUTOINCREMENT') !== false;
		$cols = []; // [ name => [ 'sql' => column definition, 'from' => source column or null ] ]
		$pk = [];
		foreach($pdo->query("SELECT * FROM pragma_table_info($qt)")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$sql = $row['type'];
			if(preg_match('/CHAR|TEXT|CLOB/i', $row['type'])) $sql .= ' COLLATE NOCASE';
			if($row['notnull']) $sql .= ' NOT NULL';
			if($row['dflt_value'] !== null) $sql .= ' DEFAULT ' . self::defaultSql($row['dflt_value']);
			$cols[$row['name']] = ['sql' => $sql, 'from' => $row['name']];
			if($row['pk']) $pk[(int) $row['pk']] = $row['name'];
		}
		ksort($pk);
		$pk = array_values($pk);
		$renames = []; // old name => new name
		$dropped = [];

		$renameColumn = function($old, $new) use(&$cols, &$pk, &$renames) {
			$replaced = [];
			foreach($cols as $name => $col) $replaced[$name === $old ? $new : $name] = $col;
			$cols = $replaced;
			foreach($pk as $k => $name) if($name === $old) $pk[$k] = $new;
			foreach($renames as $from => $to) if($to === $old) $renames[$from] = $new;
			if(!in_array($new, $renames, true)) $renames[$old] = $new;
		};

		foreach($changes as $change) {
			switch($change['action']) {
				case 'modify':
					$old = $change['column'];
					$def = $change['def'];
					if(!isset($cols[$old])) return null;
					if($def['name'] !== $old) $renameColumn($old, $def['name']);
					$cols[$def['name']]['sql'] = $def['sql'];
					if($def['primary']) $pk = [$def['name']];
					break;
				case 'add':
					$def = $change['def'];
					if(isset($cols[$def['name']])) return null;
					$cols[$def['name']] = ['sql' => $this->addColumnSql($def), 'from' => null];
					if($def['primary']) $pk = [$def['name']];
					break;
				case 'drop':
					if(!isset($cols[$change['column']])) return null;
					unset($cols[$change['column']]);
					$pk = array_values(array_diff($pk, [$change['column']]));
					$dropped[] = $change['column'];
					break;
				case 'rename':
					if(!isset($cols[$change['column']])) return null;
					$renameColumn($change['column'], $change['to']);
					break;
				case 'primary':
					$pk = $change['columns'];
					break;
				case 'dropPrimary':
					$pk = [];
					$autoIncrement = false;
					break;
				default:
					return null;
			}
		}

		$lines = [];
		foreach($cols as $name => $col) {
			if($autoIncrement && count($pk) === 1 && $pk[0] === $name) {
				$lines[] = $this->quoteId($name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
			} else {
				$lines[] = $this->quoteId($name) . ' ' . $col['sql'];
			}
		}
		if(count($pk) && !($autoIncrement && count($pk) === 1)) {
			$lines[] = 'PRIMARY KEY (' . implode(', ', array_map([$this, 'quoteId'], $pk)) . ')';
		}

		$tmp = $this->quoteId("_rebuild_$table");
		$qTable = $this->quoteId($table);
		$copyTo = [];
		$copyFrom = [];
		foreach($cols as $name => $col) {
			if($col['from'] === null) continue; // added column: uses its default
			$copyTo[] = $this->quoteId($name);
			$copyFrom[] = $this->quoteId($col['from']);
		}

		$statements = [
			"DROP TABLE IF EXISTS $tmp",
			"CREATE TABLE $tmp (\n  " . implode(",\n  ", $lines) . "\n)",
			"INSERT INTO $tmp (" . implode(', ', $copyTo) . ') SELECT ' . implode(', ', $copyFrom) . " FROM $qTable",
			"DROP TABLE $qTable",
			"ALTER TABLE $tmp RENAME TO $qTable",
		];

		// recreate indexes, applying column renames; indexes on dropped columns are removed (as in MySQL)
		foreach($indexes as $index) {
			$columns = [];
			foreach($index['columns'] as $column) {
				if(in_array($column, $dropped, true)) continue;
				$columns[] = isset($renames[$column]) ? $renames[$column] : $column;
			}
			if(!count($columns)) continue;
			$statements[] = $this->createIndexSql($table, $index['name'], $columns, $index['unique']);
		}

		return $statements;
	}

	/**
	 * Get column SQL suitable for ALTER TABLE ADD COLUMN
	 *
	 * SQLite requires a non-null default for NOT NULL columns added to existing tables.
	 *
	 * @param array $col
	 * @return string
	 *
	 */
	protected function addColumnSql(array $col) {
		$sql = $col['sql'];
		if(stripos($sql, 'NOT NULL') !== false && stripos($sql, 'DEFAULT') === false) {
			$sql .= preg_match('/INT|DEC|NUM|FLOAT|DOUBLE|REAL/i', $col['type']) ? " DEFAULT 0" : " DEFAULT ''";
		}
		return $sql;
	}

	/*********************************************************************************
	 * Functions
	 *
	 */

	/**
	 * Fold a string for case- and accent-insensitive comparison
	 *
	 * This is what makes SQLite comparisons behave like MySQL's case-insensitive collations,
	 * where "apfel", "Apfel" and "Äpfel" are all considered the same. MySQL applies this in the
	 * collation itself (in C), while here it has to be done in PHP, so this method is written to
	 * return quickly for the common case of ASCII-only text.
	 *
	 * Note that MySQL's own behavior varies by server: utf8mb4_general_ci (MySQL 5.7, MariaDB)
	 * and utf8mb4_0900_ai_ci (MySQL 8) agree on accented letters like "ä" but not on expansions
	 * like "ß". This follows the MySQL 8 behavior of expanding them ("ß" to "ss", "æ" to "ae").
	 *
	 * @param string $value
	 * @param bool $lower Also convert to lowercase? (default=true)
	 * @return string
	 *
	 */
	public static function fold($value, $lower = true) {

		static $from = null, $to = null;

		if($value === null) return '';
		$value = (string) $value;

		// fast path: text without any high bytes needs no accent folding
		if(!preg_match('/[\x80-\xFF]/', $value)) return $lower ? strtolower($value) : $value;

		if($from === null) {
			$map = [
				'A' => 'ÀÁÂÃÄÅĀĂĄ', 'a' => 'àáâãäåāăą',
				'C' => 'ÇĆĈĊČ', 'c' => 'çćĉċč',
				'D' => 'ĎĐ', 'd' => 'ďđ',
				'E' => 'ÈÉÊËĒĔĖĘĚ', 'e' => 'èéêëēĕėęě',
				'G' => 'ĜĞĠĢ', 'g' => 'ĝğġģ',
				'H' => 'ĤĦ', 'h' => 'ĥħ',
				'I' => 'ÌÍÎÏĨĪĬĮİ', 'i' => 'ìíîïĩīĭįı',
				'J' => 'Ĵ', 'j' => 'ĵ',
				'K' => 'Ķ', 'k' => 'ķ',
				'L' => 'ĹĻĽĿŁ', 'l' => 'ĺļľŀł',
				'N' => 'ÑŃŅŇ', 'n' => 'ñńņňŉ',
				'O' => 'ÒÓÔÕÖØŌŎŐ', 'o' => 'òóôõöøōŏő',
				'R' => 'ŔŖŘ', 'r' => 'ŕŗř',
				'S' => 'ŚŜŞŠ', 's' => 'śŝşš',
				'T' => 'ŢŤŦ', 't' => 'ţťŧ',
				'U' => 'ÙÚÛÜŨŪŬŮŰŲ', 'u' => 'ùúûüũūŭůűų',
				'W' => 'Ŵ', 'w' => 'ŵ',
				'Y' => 'ÝŶŸ', 'y' => 'ýÿŷ',
				'Z' => 'ŹŻŽ', 'z' => 'źżž',
				'AE' => 'Æ', 'ae' => 'æ',
				'OE' => 'Œ', 'oe' => 'œ',
				'ss' => 'ß',
				'TH' => 'Þ', 'th' => 'þ',
			];
			$from = [];
			$to = [];
			foreach($map as $replace => $chars) {
				$len = mb_strlen($chars);
				for($n = 0; $n < $len; $n++) {
					$from[] = mb_substr($chars, $n, 1);
					$to[] = $replace;
				}
			}
		}

		$value = str_replace($from, $to, $value);

		// mb_strtolower() also covers scripts not in the map above (Greek, Cyrillic, etc.)
		return $lower ? mb_strtolower($value) : $value;
	}

	/**
	 * Compare two strings the way MySQL's case-insensitive collation would (for pw_ci)
	 *
	 * @param string $a
	 * @param string $b
	 * @return int
	 *
	 */
	public static function compareCI($a, $b) {
		return strcmp(self::fold($a), self::fold($b));
	}

	/**
	 * Compile a MySQL LIKE pattern into a form that can be matched against folded values
	 *
	 * Returns array of [ type, argument ] where type is one of: equals, contains, prefix,
	 * suffix, regex. The first four avoid a regular expression for the patterns that
	 * ProcessWire uses most (%word%, word%, %word).
	 *
	 * @param string $pattern
	 * @param string $escape Escape character or blank string for none
	 * @return array
	 *
	 */
	protected static function compileLike($pattern, $escape) {

		$parts = []; // alternating literals and wildcards
		$literal = '';
		$len = strlen($pattern);

		for($n = 0; $n < $len; $n++) {
			$c = $pattern[$n];
			if($escape !== '' && $c === $escape && $n + 1 < $len) {
				$literal .= $pattern[++$n];
			} else if($c === '%' || $c === '_') {
				if($literal !== '') $parts[] = ['literal', $literal];
				$parts[] = [$c === '%' ? 'any' : 'one', ''];
				$literal = '';
			} else {
				$literal .= $c;
			}
		}

		if($literal !== '') $parts[] = ['literal', $literal];

		$types = [];
		foreach($parts as $part) $types[] = $part[0];
		$literals = [];
		foreach($parts as $part) if($part[0] === 'literal') $literals[] = self::fold($part[1]);

		if($types === []) return ['equals', ''];
		if($types === ['literal']) return ['equals', $literals[0]];
		if($types === ['any', 'literal', 'any']) return ['contains', $literals[0]];
		if($types === ['literal', 'any']) return ['prefix', $literals[0]];
		if($types === ['any', 'literal']) return ['suffix', $literals[0]];
		if($types === ['any']) return ['contains', ''];

		$regex = '';
		$literal = 0;
		foreach($parts as $part) {
			if($part[0] === 'any') {
				$regex .= '.*';
			} else if($part[0] === 'one') {
				$regex .= '.';
			} else {
				$regex .= preg_quote($literals[$literal++], '~');
			}
		}

		return ['regex', "~^$regex$~us"];
	}

	/**
	 * Register MySQL-compatible functions on given SQLite PDO connection
	 *
	 * Static so that it can also be used by the installer before ProcessWire is booted.
	 *
	 * @param \PDO $pdo
	 * @param string $databaseFile Database file path (namespace for GET_LOCK() and name returned by DATABASE())
	 *
	 */
	public static function registerFunctions(\PDO $pdo, $databaseFile = '') {

		if(class_exists('\Pdo\Sqlite', false) && defined('\Pdo\Sqlite::DETERMINISTIC')) {
			$det = constant('\Pdo\Sqlite::DETERMINISTIC'); // PHP 8.4+
		} else {
			$det = defined('\PDO::SQLITE_DETERMINISTIC') ? constant('\PDO::SQLITE_DETERMINISTIC') : 0;
		}
		$create = function($name, $callback, $numArgs = -1, $flags = 0) use($pdo) {
			if(method_exists($pdo, 'createFunction')) {
				$pdo->createFunction($name, $callback, $numArgs, $flags); // PHP 8.4+ Pdo\Sqlite
			} else {
				$pdo->sqliteCreateFunction($name, $callback, $numArgs, $flags);
			}
		};

		$toTime = function($value) {
			if($value === null || $value === '') return null;
			if(ctype_digit((string) $value)) return (int) $value;
			$time = strtotime((string) $value);
			return $time === false ? null : $time;
		};

		// date/time
		$create('now', function() { return date('Y-m-d H:i:s'); }, 0);
		$create('sysdate', function() { return date('Y-m-d H:i:s'); }, 0);
		$create('curdate', function() { return date('Y-m-d'); }, 0);
		$create('curtime', function() { return date('H:i:s'); }, 0);
		$create('utc_timestamp', function() { return gmdate('Y-m-d H:i:s'); }, 0);
		$create('unix_timestamp', function() use($toTime) {
			if(func_num_args() === 0) return time();
			return $toTime(func_get_arg(0));
		}, -1);
		$create('from_unixtime', function($ts, $format = null) {
			if($ts === null) return null;
			if($format === null) return date('Y-m-d H:i:s', (int) $ts);
			return self::dateFormat((int) $ts, $format);
		}, -1);
		$create('date_format', function($value, $format) use($toTime) {
			$ts = $toTime($value);
			return $ts === null ? null : self::dateFormat($ts, $format);
		}, 2);
		$create('interval_str', function($amount, $unit) {
			return ((float) $amount) . ' ' . strtolower($unit);
		}, 2, $det);
		$dateAdd = function($value, $interval, $sign) use($toTime) {
			if($value === null || $interval === null) return null;
			$ts = $toTime($value);
			if($ts === null) return null;
			$parts = explode(' ', (string) $interval, 2);
			$amount = ((int) round((float) $parts[0])) * $sign;
			$unit = rtrim(strtolower(isset($parts[1]) ? $parts[1] : 'day'), 's');
			// MySQL returns a DATE (no time) for DATE input and units of a day or larger
			$dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value)) && in_array($unit, ['day', 'week', 'month', 'quarter', 'year']);
			if($unit === 'week') { $amount *= 7; $unit = 'day'; }
			if($unit === 'quarter') { $amount *= 3; $unit = 'month'; }
			if($unit === 'year') { $amount *= 12; $unit = 'month'; }
			if($unit === 'month') {
				// like MySQL, clamp to the last day of the resulting month (i.e. Jan 31 + 1 month = Feb 28/29)
				$month = (int) date('n', $ts) - 1 + $amount;
				$year = (int) date('Y', $ts) + (int) floor($month / 12);
				$month = ($month % 12 + 12) % 12 + 1;
				$day = min((int) date('j', $ts), (int) date('t', mktime(0, 0, 0, $month, 1, $year)));
				$result = mktime((int) date('G', $ts), (int) date('i', $ts), (int) date('s', $ts), $month, $day, $year);
			} else {
				$result = strtotime(($amount >= 0 ? '+' : '') . $amount . " $unit", $ts);
			}
			return date($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', $result);
		};
		$create('date_add', function($value, $interval) use($dateAdd) { return $dateAdd($value, $interval, 1); }, 2);
		$create('date_sub', function($value, $interval) use($dateAdd) { return $dateAdd($value, $interval, -1); }, 2);

		// control flow and lists
		$create('if', function($cond, $a, $b) { return $cond ? $a : $b; }, 3, $det);
		$create('field', function($value) {
			$args = func_get_args();
			array_shift($args);
			foreach($args as $k => $v) if($v == $value) return $k + 1;
			return 0;
		}, -1, $det);
		$create('greatest', function() {
			$args = func_get_args();
			return in_array(null, $args, true) ? null : max($args); // MySQL: NULL if any argument is NULL
		}, -1, $det);
		$create('least', function() {
			$args = func_get_args();
			return in_array(null, $args, true) ? null : min($args);
		}, -1, $det);

		// strings (MySQL semantics)
		$create('concat', function() {
			$s = '';
			foreach(func_get_args() as $v) {
				if($v === null) return null;
				$s .= $v;
			}
			return $s;
		}, -1, $det);
		$create('concat_ws', function($sep) {
			if($sep === null) return null; // MySQL: NULL separator returns NULL
			$args = func_get_args();
			array_shift($args);
			return implode((string) $sep, array_filter($args, function($v) { return $v !== null; }));
		}, -1, $det);
		$create('lower', function($s) { return $s === null ? null : mb_strtolower((string) $s); }, 1, $det);
		$create('upper', function($s) { return $s === null ? null : mb_strtoupper((string) $s); }, 1, $det);
		$create('lcase', function($s) { return $s === null ? null : mb_strtolower((string) $s); }, 1, $det);
		$create('ucase', function($s) { return $s === null ? null : mb_strtoupper((string) $s); }, 1, $det);
		$create('char_length', function($s) { return $s === null ? null : mb_strlen((string) $s); }, 1, $det);
		$create('locate', function($needle, $haystack, $pos = 1) {
			if($needle === null || $haystack === null) return null;
			$p = mb_stripos((string) $haystack, (string) $needle, max(0, $pos - 1));
			return $p === false ? 0 : $p + 1;
		}, -1, $det);
		$create('left', function($s, $n) { return $s === null ? null : mb_substr((string) $s, 0, (int) $n); }, 2, $det);
		$create('right', function($s, $n) { return $s === null ? null : ((int) $n ? mb_substr((string) $s, -((int) $n)) : ''); }, 2, $det);
		$create('substring_index', function($s, $delim, $count) {
			if($s === null || $delim === null || $count === null) return null;
			if((string) $delim === '') return '';
			$parts = explode((string) $delim, (string) $s);
			$count = (int) $count;
			if($count > 0) return implode($delim, array_slice($parts, 0, $count));
			if($count < 0) return implode($delim, array_slice($parts, $count));
			return '';
		}, 3, $det);
		$create('md5', function($s) { return $s === null ? null : md5((string) $s); }, 1, $det);
		$create('sha1', function($s) { return $s === null ? null : sha1((string) $s); }, 1, $det);

		// GROUP_CONCAT with DISTINCT and/or ORDER BY (see groupConcat() method):
		// pw_group_concat(value, separator, distinct, [orderKey1, desc1, orderKey2, desc2, ...])
		$aggregate = function($name, $step, $final, $numArgs = -1) use($pdo) {
			if(method_exists($pdo, 'createAggregate')) {
				$pdo->createAggregate($name, $step, $final, $numArgs); // PHP 8.4+ Pdo\Sqlite
			} else {
				$pdo->sqliteCreateAggregate($name, $step, $final, $numArgs);
			}
		};
		$aggregate('pw_group_concat', function($context, $rowNumber, $value, $separator = ',', $distinct = 0) {
			if(!is_array($context)) $context = ['rows' => [], 'separator' => (string) $separator, 'distinct' => (bool) $distinct];
			if($value === null) return $context; // like MySQL, NULL values are skipped
			$context['rows'][] = [(string) $value, array_slice(func_get_args(), 5), count($context['rows'])];
			return $context;
		}, function($context, $rowNumber) {
			if(!is_array($context) || !count($context['rows'])) return null;
			$rows = $context['rows'];
			usort($rows, function($a, $b) {
				for($n = 0; $n + 1 < count($a[1]); $n += 2) {
					$x = $a[1][$n];
					$y = $b[1][$n];
					if($x === $y) continue;
					if($x === null) {
						$c = -1; // NULLs sort first, as in MySQL
					} else if($y === null) {
						$c = 1;
					} else if(is_numeric($x) && is_numeric($y)) {
						$c = $x <=> $y;
					} else {
						$c = strcasecmp((string) $x, (string) $y);
					}
					if($c !== 0) return empty($a[1][$n + 1]) ? $c : -$c;
				}
				return $a[2] <=> $b[2]; // stable
			});
			$values = [];
			$seen = [];
			foreach($rows as $row) {
				if($context['distinct']) {
					$key = mb_strtolower($row[0]);
					if(isset($seen[$key])) continue;
					$seen[$key] = true;
				}
				$values[] = $row[0];
			}
			return implode($context['separator'], $values);
		}, -1);

		// JSON functions that MySQL has and SQLite does not. SQLite provides json_extract(),
		// json_set(), json_insert(), json_replace(), json_remove(), json_valid(), json_quote(),
		// json_array() and json_object() under the same names, and understands the same "$.path"
		// syntax, so those need no emulation.

		$jsonDecode = function($value) {
			// returns array(isValid, value)
			if($value === null) return [false, null];
			$value = trim((string) $value);
			$data = json_decode($value, true);
			if($data === null && strtolower($value) !== 'null') return [false, null];
			return [true, $data];
		};

		$jsonPath = function($data, $path) {
			// returns array(found, value) for a MySQL JSON path like "$.a.b" or "$[0]"
			$path = trim((string) $path);
			if($path === '' || $path[0] !== '$') return [false, null];
			$n = 1;
			$len = strlen($path);
			while($n < $len) {
				$c = $path[$n];
				if($c === '.') {
					$n++;
					if($n < $len && $path[$n] === '"') {
						$end = strpos($path, '"', $n + 1);
						if($end === false) return [false, null];
						$key = substr($path, $n + 1, $end - $n - 1);
						$n = $end + 1;
					} else {
						$end = $n;
						while($end < $len && $path[$end] !== '.' && $path[$end] !== '[') $end++;
						$key = substr($path, $n, $end - $n);
						$n = $end;
					}
					if($key === '' || !is_array($data) || !array_key_exists($key, $data)) return [false, null];
					$data = $data[$key];
				} else if($c === '[') {
					$end = strpos($path, ']', $n);
					if($end === false) return [false, null];
					$index = trim(substr($path, $n + 1, $end - $n - 1));
					$n = $end + 1;
					if(!ctype_digit($index) || !is_array($data)) return [false, null]; // no wildcard support
					$index = (int) $index;
					if(!array_key_exists($index, $data)) return [false, null];
					$data = $data[$index];
				} else {
					return [false, null];
				}
			}
			return [true, $data];
		};

		$isList = function($value) {
			return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
		};

		$jsonEquals = function($a, $b) {
			if(is_bool($a) || is_bool($b) || $a === null || $b === null) return $a === $b;
			if(is_int($a) || is_float($a)) return (is_int($b) || is_float($b)) && $a == $b;
			return $a === $b;
		};

		$jsonContains = null;
		$jsonContains = function($target, $candidate) use(&$jsonContains, $isList, $jsonEquals) {
			if(is_array($target) && is_array($candidate)) {
				if($isList($target) && $isList($candidate)) {
					// every candidate element must be contained in the target array
					foreach($candidate as $c) {
						$found = false;
						foreach($target as $t) {
							if(!$jsonContains($t, $c)) continue;
							$found = true;
							break;
						}
						if(!$found) return false;
					}
					return true;
				}
				if(!$isList($target) && !$isList($candidate)) {
					// every candidate key must exist in the target with a contained value
					foreach($candidate as $key => $c) {
						if(!array_key_exists($key, $target)) return false;
						if(!$jsonContains($target[$key], $c)) return false;
					}
					return true;
				}
				if($isList($target)) {
					// object candidate against an array target: must match an element
					foreach($target as $t) if($jsonContains($t, $candidate)) return true;
				}
				return false;
			}
			if($isList($target)) {
				// scalar candidate is contained if it matches any element
				foreach($target as $t) if($jsonContains($t, $candidate)) return true;
				return false;
			}
			if(is_array($target) || is_array($candidate)) return false;
			return $jsonEquals($target, $candidate);
		};

		$create('json_unquote', function($value) {
			if($value === null) return null;
			$value = (string) $value;
			if(strlen($value) < 2 || $value[0] !== '"' || substr($value, -1) !== '"') return $value;
			$decoded = json_decode($value);
			return is_string($decoded) ? $decoded : $value;
		}, 1, $det);

		$create('json_length', function($doc, $path = null) use($jsonDecode, $jsonPath) {
			if($doc === null) return null;
			list($valid, $data) = $jsonDecode($doc);
			if(!$valid) return null;
			if($path !== null) {
				list($found, $data) = $jsonPath($data, $path);
				if(!$found) return null;
			}
			return is_array($data) ? count($data) : 1; // scalars have a length of 1 in MySQL
		}, -1, $det);

		$create('json_contains', function($target, $candidate, $path = null) use($jsonDecode, $jsonPath, $jsonContains) {
			if($target === null || $candidate === null) return null;
			list($validTarget, $t) = $jsonDecode($target);
			list($validCandidate, $c) = $jsonDecode($candidate);
			if(!$validTarget || !$validCandidate) return null;
			if($path !== null) {
				list($found, $t) = $jsonPath($t, $path);
				if(!$found) return null; // MySQL returns NULL when the path is not found
			}
			return $jsonContains($t, $c) ? 1 : 0;
		}, -1, $det);

		// regular expressions: "x REGEXP y" calls regexp(y, x)
		// accents are folded on both sides (case is handled by the "i" modifier) so that
		// word matches behave like MySQL, where the collation ignores accents
		$create('regexp', function($pattern, $value) {
			if($pattern === null || $value === null) return null;
			// chr(1) delimiter cannot be escaped or closed by a pattern that does not contain it
			$pattern = self::fold((string) $pattern, false);
			if(strpos($pattern, "\x01") !== false) return 0;
			$result = @preg_match("\x01$pattern\x01iu", self::fold((string) $value, false));
			return $result ? 1 : 0;
		}, 2, $det);

		// LIKE with MySQL collation behavior: case- and accent-insensitive for all of Unicode
		// rather than SQLite's built-in like(), which folds case for ASCII only
		$create('like', function($pattern, $value, $escape = '') {
			if($pattern === null || $value === null) return null;
			static $cache = [];
			$key = "$escape\x00$pattern";
			if(!isset($cache[$key])) {
				if(count($cache) > 200) $cache = [];
				$cache[$key] = self::compileLike((string) $pattern, (string) $escape);
			}
			list($type, $arg) = $cache[$key];
			$value = self::fold((string) $value);
			if($type === 'contains') return $arg === '' || strpos($value, $arg) !== false ? 1 : 0;
			if($type === 'prefix') return strncmp($value, $arg, strlen($arg)) === 0 ? 1 : 0;
			if($type === 'suffix') return $arg === '' || substr($value, -strlen($arg)) === $arg ? 1 : 0;
			if($type === 'equals') return $value === $arg ? 1 : 0;
			return preg_match($arg, $value) ? 1 : 0;
		}, -1, $det);

		// numbers
		$create('rand', function() { return mt_rand() / mt_getrandmax(); }, -1);
		$create('floor', function($n) { return $n === null ? null : (int) floor((float) $n); }, 1, $det);
		$create('ceil', function($n) { return $n === null ? null : (int) ceil((float) $n); }, 1, $det);
		$create('ceiling', function($n) { return $n === null ? null : (int) ceil((float) $n); }, 1, $det);

		// server
		$databaseName = $databaseFile === '' ? 'main' : pathinfo($databaseFile, PATHINFO_FILENAME);
		$create('database', function() use($databaseName) { return $databaseName; }, 0);
		$create('pw_show_create_table', function($table) use($pdo) { return self::mysqlCreateTable($pdo, (string) $table); }, 1);
		$create('version', function() use($pdo) { return 'SQLite ' . $pdo->query('SELECT sqlite_version()')->fetchColumn(); }, 0);
		$create('last_insert_id', function() use($pdo) { return (int) $pdo->lastInsertId(); }, 0);

		// pw_ci collation: case- and accent-insensitive ordering and comparison, like MySQL.
		// It is used only in queries (COLLATE pw_ci) and never in the schema, so that the
		// database file remains readable by other SQLite tools.
		if(method_exists($pdo, 'createCollation')) {
			$pdo->createCollation('pw_ci', [__CLASS__, 'compareCI']); // PHP 8.4+ Pdo\Sqlite
		} else {
			$pdo->sqliteCreateCollation('pw_ci', [__CLASS__, 'compareCI']);
		}

		// named locks: GET_LOCK(name, timeout), RELEASE_LOCK(name), IS_FREE_LOCK(name)
		// emulated with flock(), which (like MySQL named locks) is released when the process ends
		$lockFile = function($name) use($databaseFile) {
			$dir = rtrim(sys_get_temp_dir(), '/\\') . '/pw-sqlite-locks';
			if(!is_dir($dir)) @mkdir($dir, 0700, true);
			return $dir . '/' . md5($databaseFile . "\0" . $name) . '.lock';
		};
		$create('get_lock', function($name, $timeout = 0) use($lockFile) {
			if($name === null) return null;
			$name = (string) $name;
			if(isset(self::$locks[$name])) {
				self::$locks[$name]['count']++; // re-entrant, as in MySQL 5.7+
				return 1;
			}
			$fp = @fopen($lockFile($name), 'c');
			if(!$fp) return null;
			$timeout = (float) $timeout;
			$until = microtime(true) + ($timeout < 0 ? 31536000 : $timeout);
			do {
				if(flock($fp, LOCK_EX | LOCK_NB)) {
					self::$locks[$name] = ['fp' => $fp, 'count' => 1];
					return 1;
				}
				usleep(50000);
			} while(microtime(true) < $until);
			fclose($fp);
			return 0;
		}, -1);
		$create('release_lock', function($name) {
			$name = (string) $name;
			if(!isset(self::$locks[$name])) return null;
			if(--self::$locks[$name]['count'] > 0) return 1;
			flock(self::$locks[$name]['fp'], LOCK_UN);
			fclose(self::$locks[$name]['fp']);
			unset(self::$locks[$name]);
			return 1;
		}, 1);
		$create('is_free_lock', function($name) use($lockFile) {
			$name = (string) $name;
			if(isset(self::$locks[$name])) return 0;
			$fp = @fopen($lockFile($name), 'c');
			if(!$fp) return null;
			$free = flock($fp, LOCK_EX | LOCK_NB);
			if($free) flock($fp, LOCK_UN);
			fclose($fp);
			return $free ? 1 : 0;
		}, 1);
	}

	/**
	 * Get SQL for a column default as reported by PRAGMA table_info (expressions need parentheses)
	 *
	 * @param string $default
	 * @return string
	 *
	 */
	protected static function defaultSql($default) {
		$default = (string) $default;
		if(preg_match('/^(\'.*\'|-?[\d.]+|NULL|CURRENT_TIMESTAMP|CURRENT_DATE|CURRENT_TIME|\(.*\))$/is', $default)) return $default;
		return "($default)";
	}

	/**
	 * Get a MySQL-syntax CREATE TABLE statement (including indexes) for an SQLite table
	 *
	 * Used to emulate SHOW CREATE TABLE, for example so that WireDatabaseBackup produces
	 * MySQL-syntax dumps, which restore through this translator. Column types are as stored
	 * in SQLite (i.e. without UNSIGNED), and FULLTEXT indexes are exported as regular indexes.
	 *
	 * @param \PDO $pdo
	 * @param string $table
	 * @return string|null
	 *
	 */
	public static function mysqlCreateTable(\PDO $pdo, $table) {
		$qt = $pdo->quote($table);
		$sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=$qt")->fetchColumn();
		if(!$sql) return null;
		$autoIncrement = stripos($sql, 'AUTOINCREMENT') !== false;
		$q = function($name) { return '`' . str_replace('`', '', $name) . '`'; };
		$lines = [];
		$pk = [];
		foreach($pdo->query("SELECT * FROM pragma_table_info($qt)")->fetchAll(\PDO::FETCH_ASSOC) as $col) {
			$type = strtolower($col['type']);
			if($type === '' || $type === 'integer') $type = 'int';
			$line = $q($col['name']) . " $type";
			if($col['notnull'] || $col['pk']) $line .= ' NOT NULL';
			if($col['dflt_value'] !== null) {
				$default = $col['dflt_value'];
				if(preg_match("/^\\(?datetime\\('now'(,\\s*'localtime')?\\)\\)?$/i", $default)) $default = 'CURRENT_TIMESTAMP';
				$line .= " DEFAULT $default";
			}
			if($col['pk']) $pk[(int) $col['pk']] = $col['name'];
			$lines[$col['name']] = $line;
		}
		ksort($pk);
		if($autoIncrement && count($pk) === 1) $lines[reset($pk)] .= ' AUTO_INCREMENT';
		$lines = array_values($lines);
		if(count($pk)) $lines[] = 'PRIMARY KEY (' . implode(',', array_map($q, $pk)) . ')';
		$prefix = $table . self::indexSeparator;
		foreach($pdo->query("SELECT * FROM pragma_index_list($qt)")->fetchAll(\PDO::FETCH_ASSOC) as $index) {
			if($index['origin'] === 'pk') continue;
			$name = strpos($index['name'], $prefix) === 0 ? substr($index['name'], strlen($prefix)) : $index['name'];
			$cols = $pdo->query("SELECT name FROM pragma_index_info(" . $pdo->quote($index['name']) . ") ORDER BY seqno")->fetchAll(\PDO::FETCH_COLUMN);
			$lines[] = ($index['unique'] ? 'UNIQUE KEY ' : 'KEY ') . $q($name) . ' (' . implode(',', array_map($q, $cols)) . ')';
		}
		return 'CREATE TABLE ' . $q($table) . " (\n  " . implode(",\n  ", $lines) . "\n)";
	}

	/**
	 * Named locks held by this process for GET_LOCK() emulation
	 *
	 * @var array Indexed by lock name, each with 'fp' (file handle) and 'count'
	 *
	 */
	protected static $locks = [];

	/**
	 * Format a timestamp using a MySQL DATE_FORMAT() format string
	 *
	 * @param int $ts
	 * @param string $format
	 * @return string
	 *
	 */
	public static function dateFormat($ts, $format) {
		$map = [
			'%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%c' => 'n', '%d' => 'd', '%e' => 'j',
			'%H' => 'H', '%k' => 'G', '%h' => 'h', '%I' => 'h', '%l' => 'g', '%i' => 'i', '%s' => 's', '%S' => 's',
			'%p' => 'A', '%M' => 'F', '%b' => 'M', '%W' => 'l', '%a' => 'D', '%j' => 'z', '%T' => 'H:i:s',
			'%r' => 'h:i:s A', '%u' => 'W', '%%' => '%',
		];
		$out = '';
		$len = strlen($format);
		for($i = 0; $i < $len; $i++) {
			$two = substr($format, $i, 2);
			if(isset($map[$two])) {
				$out .= $two === '%j' ? sprintf('%03d', date('z', $ts) + 1) : date($map[$two], $ts);
				$i++;
			} else {
				$out .= $format[$i];
			}
		}
		return $out;
	}
}
