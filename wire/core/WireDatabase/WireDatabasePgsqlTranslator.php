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
	 * Is the pg_trgm extension available? (FULLTEXT keys become trigram indexes when it is)
	 *
	 * @var bool
	 *
	 */
	protected $trigramAvailable = true;

	/**
	 * Cached schema facts per table, see tableSchema()
	 *
	 * @var array
	 *
	 */
	protected $schema = [];

	/**
	 * True while one of our own catalog lookups runs (see catalogRows())
	 *
	 * @var bool
	 *
	 */
	protected $introspecting = false;

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
	 * Run one of our own catalog queries and return its rows
	 *
	 * The PDO we are given may translate everything it prepares (the installer's does), so the
	 * lookup is flagged: translateStatements() returns the SQL untouched while it runs.
	 *
	 * @param string $sql PostgreSQL SQL
	 * @param array $params
	 * @param int $mode PDO fetch mode
	 * @return array Empty when no PDO connection is available
	 *
	 */
	protected function catalogRows($sql, array $params, $mode = \PDO::FETCH_ASSOC) {
		$pdo = $this->pdo();
		if(!$pdo) return [];
		$was = $this->introspecting;
		$this->introspecting = true;
		try {
			$query = $pdo->prepare($sql);
			$query->execute($params);
			return $query->fetchAll($mode);
		} catch(\PDOException $e) {
			// not a PostgreSQL connection, or no catalog access: translate without schema knowledge
			return [];
		} finally {
			$this->introspecting = $was;
		}
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
		if($this->introspecting) return [$sql]; // our own catalog lookup, already PostgreSQL SQL (see catalogRows())
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
	 * Quote aliases that contain uppercase letters, and every reference to them
	 *
	 * MySQL keeps the case of an alias (numChildren, FieldtypeFile_3) and matches it case-insensitively;
	 * PostgreSQL folds unquoted names to lowercase, so a result column would come back as numchildren.
	 * Quoting the definition alone would break its references, so both are quoted. Applies to the
	 * whole statement, subqueries included.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function quoteCaseAliases(array $tokens) {
		$names = [];
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			if(!$this->isWord($tokens[$i], 'AS')) continue;
			$j = $this->next($tokens, $i + 1);
			if($j < 0 || $tokens[$j][0] !== 'word' || !preg_match('/[A-Z]/', $tokens[$j][1])) continue;
			$names[$tokens[$j][1]] = true;
		}
		if(!count($names)) return $tokens;
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] !== 'word' || !isset($names[$t[1]])) continue;
			$j = $this->next($tokens, $i + 1);
			if($j > -1 && $tokens[$j][0] === 'punct' && $tokens[$j][1] === '(') continue; // function call
			$tokens[$i] = ['id', $t[1]];
		}
		return $tokens;
	}

	/**
	 * Translate a single statement
	 *
	 * @param string $sql
	 * @return string|array String, or array when multiple statements are needed
	 *
	 */
	protected function translateStatement($sql) {

		if($this->isNativeInsert($sql)) {
			// already PostgreSQL syntax (i.e. from WireDatabaseDialectPgsql::upsert()): its double-quoted
			// identifiers would read as MySQL string literals, so it must not go through the tokenizer
			return $sql;
		}

		$tokens = $this->quoteCaseAliases($this->tokenize($sql));
		$words = $this->leadingWords($tokens, 4);
		$first = isset($words[0]) ? $words[0] : '';
		$second = isset($words[1]) ? $words[1] : '';

		switch($first) {
			case 'INSERT':
			case 'REPLACE':
				return $this->insert($tokens);
			case 'DELETE':
				$tokens = $this->deleteLimit($tokens);
				break;
			case 'UPDATE':
				$tokens = $this->updateOrderLimit($tokens);
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
			case 'SET':
			case 'LOCK':
			case 'UNLOCK':
			case 'OPTIMIZE':
			case 'ANALYZE':
			case 'REPAIR':
			case 'CHECK':
				// SET NAMES/sql_mode/FOREIGN_KEY_CHECKS, LOCK TABLES and table maintenance have no equivalent here
				return 'SELECT 1';
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
			case 'ALTER':
				if($second === 'TABLE') return $this->alterTable($tokens);
				break;
			case 'TRUNCATE':
				return $this->truncate($tokens);
			case 'DROP':
				if($second === 'INDEX') return $this->dropIndex($tokens);
				break;
			case 'DO':
				// DO expr (MySQL: evaluate without returning a result)
				$i = $this->next($tokens, 0);
				$tokens[$i] = ['word', 'SELECT'];
				$first = 'SELECT';
				break;
		}

		$tokens = $this->expressions($tokens);
		$tokens = $this->booleanContext($tokens);
		$tokens = $this->subqueries($tokens);
		$tokens = $this->typedComparisons($tokens);
		if($first === 'SELECT') $tokens = $this->selectPasses($tokens);

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

			} else if($w === 'AS' && $j > -1 && $tokens[$j][0] === 'str') {
				// MySQL allows a quoted string as an alias; PostgreSQL needs an identifier
				$out[] = $t;
				for($x = $i + 1; $x < $j; $x++) $out[] = $tokens[$x];
				$out[] = ['id', str_replace("''", "'", substr($tokens[$j][1], 1, -1))];
				$i = $j;

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
		$args = [];
		foreach($this->callArgTokens($tokens, $open) as $arg) {
			$args[] = trim($this->join($this->expressions($arg)));
		}
		return $args;
	}

	/**
	 * Get the arguments of a function call as untranslated token lists
	 *
	 * @param array $tokens
	 * @param int $open Index of the opening paren
	 * @return array
	 *
	 */
	protected function callArgTokens(array $tokens, $open) {
		$close = $this->matchParen($tokens, $open);
		$inner = $this->trimTokens(array_slice($tokens, $open + 1, $close - $open - 1));
		if(!count($inner)) return [];
		$args = [];
		foreach($this->splitCommas($inner) as $arg) $args[] = $this->trimTokens($arg);
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
				if($qty !== 3) return null;
				// the condition may be an integer in MySQL (IF(status & 1, ...), IF(1, ...)); CASE needs a boolean
				$raw = $this->callArgTokens($tokens, $open);
				$cond = trim($this->join($this->condition($this->expressions($raw[0]))));
				return "(CASE WHEN $cond THEN $args[1] ELSE $args[2] END)";
			case 'LOCATE':
				if($qty === 2) return "position($args[0] in $args[1])";
				if($qty === 3) {
					$rest = "substring($args[1] from $args[2])";
					return "(CASE WHEN strpos($rest, $args[0]) = 0 THEN 0 ELSE strpos($rest, $args[0]) + $args[2] - 1 END)";
				}
				return null;
			case 'SUBSTRING_INDEX':
				// everything before the count-th delimiter (count > 0), or after the count-th from the end (count < 0)
				if($qty !== 3 || !preg_match('/^-?[0-9]+$/', $args[2])) return null;
				$parts = "string_to_array($args[0], $args[1])";
				$count = (int) $args[2];
				if($count === 0) return "''";
				if($count > 0) return "array_to_string(($parts)[1:$count], $args[1])";
				return "array_to_string(($parts)[cardinality($parts) - " . abs($count) . " + 1:], $args[1])";
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
			$terms = [];
			foreach($this->splitCommas(array_slice($tokens, $y + 1, $orderEnd - $y - 1)) as $term) {
				$term = trim($this->join($this->expressions($this->trimTokens($term))));
				if($distinct && count($exprs) === 1) {
					// with DISTINCT, PostgreSQL requires ORDER BY expressions to appear in the argument list as-is
					$dir = '';
					if(preg_match('/^(.*?)\s+(ASC|DESC)$/is', $term, $m)) {
						$term = $m[1];
						$dir = ' ' . strtoupper($m[2]);
					}
					if($term === $exprs[0]) $term = $expr;
					$term .= $dir;
				}
				$terms[] = $term;
			}
			$order = ' ORDER BY ' . implode(', ', $terms);
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
	 * Passes that apply to a SELECT (statement or subquery) as a whole
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function selectPasses(array $tokens) {
		$tokens = $this->typedComparisons($tokens);
		$tokens = $this->havingAliases($tokens);
		return $this->anyValueGroupBy($tokens);
	}

	/**
	 * Apply the SELECT passes to every parenthesized subquery, innermost first
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function subqueries(array $tokens) {
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] !== 'punct' || $t[1] !== '(') continue;
			$j = $this->next($tokens, $i + 1);
			if($j < 0 || !$this->isWord($tokens[$j], 'SELECT')) continue;
			$end = $this->matchParen($tokens, $i);
			if($end < 0) continue;
			$inner = $this->selectPasses($this->subqueries(array_slice($tokens, $i + 1, $end - $i - 1)));
			$tokens = array_merge(array_slice($tokens, 0, $i + 1), $inner, array_slice($tokens, $end));
			$n = count($tokens);
			$i = $i + count($inner) + 1;
		}
		return $tokens;
	}

	/**
	 * Replace select-list aliases used in HAVING with the expressions they name
	 *
	 * MySQL allows `SELECT COUNT(x) AS n ... HAVING n > 0`; PostgreSQL does not know output
	 * column names in HAVING.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function havingAliases(array $tokens) {
		$havingPos = -1;
		$selectPos = -1;
		$fromPos = -1;
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
			if($w === 'SELECT' && $selectPos < 0) $selectPos = $i + 1;
			else if($w === 'FROM' && $fromPos < 0 && $selectPos > -1) $fromPos = $i;
			else if($w === 'HAVING' && $havingPos < 0) $havingPos = $i + 1;
		}
		if($havingPos < 0 || $selectPos < 0 || $fromPos < $selectPos) return $tokens;

		$aliases = []; // lowercase alias => expression SQL
		foreach($this->splitCommas(array_slice($tokens, $selectPos, $fromPos - $selectPos)) as $part) {
			$part = $this->trimTokens($part);
			if(count($part) < 3) continue;
			$as = $this->prev($part, count($part) - 2);
			if($as <= 0 || !$this->isWord($part[$as], 'AS')) continue;
			$alias = $this->name($part[count($part) - 1]);
			$aliases[strtolower($alias)] = trim($this->join(array_slice($part, 0, $as)));
		}
		if(!count($aliases)) return $tokens;

		$havingEnd = $this->clauseEnd($tokens, $havingPos);
		for($i = $havingPos; $i < $havingEnd; $i++) {
			$t = $tokens[$i];
			if($t[0] !== 'word' && $t[0] !== 'id') continue;
			$key = strtolower($t[1]);
			if(!isset($aliases[$key])) continue;
			$prev = $this->prev($tokens, $i - 1);
			$next = $this->next($tokens, $i + 1);
			if($prev > -1 && $tokens[$prev][0] === 'punct' && $tokens[$prev][1] === '.') continue; // qualified column
			if($next > -1 && $tokens[$next][0] === 'punct' && $tokens[$next][1] === '(') continue; // function call
			$tokens[$i] = ['word', '(' . $aliases[$key] . ')'];
		}
		return $tokens;
	}

	/**
	 * Wrap columns of tables other than the grouped one in any_value(), in SELECT and ORDER BY
	 *
	 * MySQL without ONLY_FULL_GROUP_BY (as ProcessWire configures it) allows `GROUP BY pages.id`
	 * with `joined_table.column` in the select list or ORDER BY. PostgreSQL accepts columns of the
	 * grouped table (they depend on its primary key) but not columns of joined tables; any_value()
	 * gives the same result MySQL returns, one arbitrary value from the group. Select terms keep
	 * their result column name through an alias.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function anyValueGroupBy(array $tokens) {
		$groupPos = -1;
		$orderPos = -1;
		$selectPos = -1;
		$fromPos = -1;
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
			if($w === 'SELECT' && $selectPos < 0) {
				$selectPos = $i + 1;
			} else if($w === 'FROM' && $fromPos < 0 && $selectPos > -1) {
				$fromPos = $i;
			} else if($w === 'GROUP' || $w === 'ORDER') {
				$j = $this->next($tokens, $i + 1);
				if($j === -1 || !$this->isWord($tokens[$j], 'BY')) continue;
				if($w === 'GROUP' && $groupPos < 0) $groupPos = $j + 1;
				if($w === 'ORDER') $orderPos = $j + 1;
			}
		}
		if($groupPos < 0) return $tokens;

		$qualifiers = [];
		$groupEnd = $this->clauseEnd($tokens, $groupPos);
		foreach($this->splitCommas(array_slice($tokens, $groupPos, $groupEnd - $groupPos)) as $term) {
			$term = $this->trimTokens($term);
			if(count($term) === 3 && $term[1][0] === 'punct' && $term[1][1] === '.') $qualifiers[$this->name($term[0])] = true;
		}
		if(!count($qualifiers)) return $tokens;

		// ORDER BY first, since it comes after the select list and its replacement does not move the select list
		if($orderPos > $groupPos) {
			$orderEnd = $this->clauseEnd($tokens, $orderPos);
			$tokens = array_merge(
				array_slice($tokens, 0, $orderPos),
				$this->anyValueTerms(array_slice($tokens, $orderPos, $orderEnd - $orderPos), $qualifiers, false),
				array_slice($tokens, $orderEnd)
			);
		}
		if($selectPos > -1 && $fromPos > $selectPos) {
			$tokens = array_merge(
				array_slice($tokens, 0, $selectPos),
				$this->anyValueTerms(array_slice($tokens, $selectPos, $fromPos - $selectPos), $qualifiers, true),
				array_slice($tokens, $fromPos)
			);
		}
		return $tokens;
	}

	/**
	 * Wrap qualified column terms whose table is not grouped in any_value()
	 *
	 * @param array $tokens Comma-separated terms
	 * @param array $qualifiers Grouped table names/aliases, as keys
	 * @param bool $select Select list (keep result names via alias) rather than ORDER BY (keep ASC/DESC)
	 * @return array
	 *
	 */
	protected function anyValueTerms(array $tokens, array $qualifiers, $select) {
		$terms = [];
		foreach($this->splitCommas($tokens) as $part) {
			$lead = [];
			$trail = [];
			while(count($part) && $part[0][0] === 'ws') $lead[] = array_shift($part);
			while(count($part) && $part[count($part) - 1][0] === 'ws') array_unshift($trail, array_pop($part));
			$suffix = []; // ASC/DESC, or AS alias
			$last = count($part) ? $part[count($part) - 1] : null;
			if(!$select && $this->isWord($last, ['ASC', 'DESC'])) {
				$suffix = [['ws', ' '], array_pop($part)];
				$part = $this->trimTokens($part);
			} else if($select && count($part) >= 3) {
				$as = $this->prev($part, count($part) - 2);
				if($as > 0 && $this->isWord($part[$as], 'AS')) {
					$suffix = array_slice($part, $as - 1); // " AS alias"
					$part = $this->trimTokens(array_slice($part, 0, $as - 1));
				}
			}
			if(count($part) === 3 && $part[1][0] === 'punct' && $part[1][1] === '.'
				&& in_array($part[0][0], ['word', 'id']) && in_array($part[2][0], ['word', 'id'])
				&& !isset($qualifiers[$this->name($part[0])])) {
				if($select && !count($suffix)) $suffix = [['ws', ' '], ['word', 'AS'], ['ws', ' '], ['id', $this->name($part[2])]];
				$part = [['word', 'any_value(' . $this->join($part) . ')']];
			}
			$terms[] = array_merge($lead, $part, $suffix, $trail);
		}
		$rebuilt = [];
		foreach($terms as $k => $term) {
			if($k) $rebuilt[] = ['punct', ','];
			foreach($term as $t) $rebuilt[] = $t;
		}
		return $rebuilt;
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

	/*********************************************************************************
	 * Schema knowledge (requires a PDO connection, otherwise unknown)
	 *
	 */

	/**
	 * Set whether the pg_trgm extension is available
	 *
	 * Without it, FULLTEXT keys are skipped rather than becoming trigram indexes.
	 *
	 * @param bool $available
	 *
	 */
	public function setTrigramAvailable($available) {
		$this->trigramAvailable = (bool) $available;
	}

	/**
	 * Forget cached schema facts for a table (after DDL changes it)
	 *
	 * @param string $table
	 *
	 */
	protected function clearSchemaCache($table) {
		unset($this->schema[$table]);
	}

	/**
	 * Provide schema facts without a database connection (for tests and the installer)
	 *
	 * @param array $tables [ table => [ 'primary' => [ columns ], 'identity' => column or null ] ]
	 *
	 */
	public function setSchemaCache(array $tables) {
		foreach($tables as $table => $facts) {
			$this->schema[$table] = [
				'primary' => isset($facts['primary']) ? array_values($facts['primary']) : [],
				'identity' => isset($facts['identity']) ? $facts['identity'] : null,
				'columns' => isset($facts['columns']) ? $facts['columns'] : [],
			];
		}
	}

	/**
	 * Get the primary key columns, identity column and column types of a table
	 *
	 * @param string $table
	 * @return array [ 'primary' => [ columns ], 'identity' => column or null, 'columns' => [ column => pg type ] ], all empty when unknown
	 *
	 */
	protected function tableSchema($table) {
		if(isset($this->schema[$table])) return $this->schema[$table];
		$facts = ['primary' => [], 'identity' => null, 'columns' => []];
		if($this->pdo()) {
			foreach($this->getColumnTypes($table) as $name => $info) {
				$facts['columns'][$name] = $info['pgType'];
				if($info['identity'] && $facts['identity'] === null) $facts['identity'] = $name;
			}
			$facts['primary'] = $this->catalogRows(
				"SELECT a.attname FROM pg_index i " .
				"JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) " .
				"JOIN pg_class c ON c.oid = i.indrelid JOIN pg_namespace n ON n.oid = c.relnamespace " .
				"WHERE c.relname = ? AND n.nspname = current_schema() AND i.indisprimary " .
				"ORDER BY array_position(i.indkey, a.attnum)",
				[$table], \PDO::FETCH_COLUMN
			);
			$this->schema[$table] = $facts;
		}
		return $facts;
	}

	/**
	 * Classify a PostgreSQL column type: 'number', 'datetime', 'text', 'bool' or 'other'
	 *
	 * @param string $pgType
	 * @return string
	 *
	 */
	protected function typeClass($pgType) {
		$pgType = strtolower((string) $pgType);
		if(preg_match('/^(integer|smallint|bigint|numeric|real|double|int)/', $pgType)) return 'number';
		if(preg_match('/^(timestamp|date|time)/', $pgType)) return 'datetime';
		if(preg_match('/^(text|character|varchar|char)/', $pgType)) return 'text';
		if($pgType === 'boolean') return 'bool';
		return 'other';
	}

	/**
	 * Map table aliases used in a statement to their table names (top level only)
	 *
	 * @param array $tokens
	 * @return array [ alias => table ]
	 *
	 */
	protected function tableAliases(array $tokens) {
		$aliases = [];
		$n = count($tokens);
		$depth = 0;
		$skip = ['AS', 'ON', 'WHERE', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL', 'OUTER', 'STRAIGHT_JOIN', 'GROUP', 'ORDER', 'LIMIT', 'HAVING', 'SET', 'USING', 'UNION', 'FOR', 'WINDOW', 'OFFSET', 'RETURNING'];
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0 || !$this->isWord($t, ['FROM', 'JOIN', 'UPDATE', 'INTO'])) continue;
			$j = $this->next($tokens, $i + 1);
			if($j < 0 || !in_array($tokens[$j][0], ['word', 'id']) || $this->isWord($tokens[$j], $skip)) continue;
			$k = $this->next($tokens, $j + 1);
			if($k > -1 && $tokens[$k][0] === 'punct' && $tokens[$k][1] === '.') {
				// schema-qualified name (information_schema.columns, pg_catalog.pg_class): not one of ours
				$i = $k;
				continue;
			}
			$table = $this->name($tokens[$j]);
			$aliases[$table] = $table;
			if($k > -1 && $this->isWord($tokens[$k], 'AS')) $k = $this->next($tokens, $k + 1);
			if($k > -1 && in_array($tokens[$k][0], ['word', 'id']) && !$this->isWord($tokens[$k], $skip)) {
				$aliases[$this->name($tokens[$k])] = $table;
			}
			$i = $j;
		}
		return $aliases;
	}

	/**
	 * Coerce comparisons between typed columns and strings the way MySQL does
	 *
	 * MySQL compares a numeric column to a string by converting the string to a number ('' and
	 * 'abc' become 0), and applies LIKE and REGEXP to numbers and dates as text. PostgreSQL rejects
	 * both, so numeric comparisons get a converted right-hand side and LIKE/REGEXP get a text cast
	 * on the column. Needs the table's column types (see tableSchema()).
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function typedComparisons(array $tokens) {
		$aliases = $this->tableAliases($tokens);
		if(!count($aliases)) return $tokens;
		$tables = array_values(array_unique(array_values($aliases)));
		$single = count($tables) === 1 ? $tables[0] : null; // unqualified columns can be resolved
		$n = count($tokens);
		$out = [];
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if(!in_array($t[0], ['word', 'id'])) {
				$out[] = $t;
				continue;
			}
			$table = null;
			$column = '';
			$colTokens = [];
			if($i + 2 < $n && $tokens[$i + 1][0] === 'punct' && $tokens[$i + 1][1] === '.' && in_array($tokens[$i + 2][0], ['word', 'id'])) {
				// alias.column
				$alias = $this->name($t);
				if(isset($aliases[$alias])) {
					$table = $aliases[$alias];
					$column = $this->name($tokens[$i + 2]);
					$colTokens = array_slice($tokens, $i, 3);
				}
			} else if($single !== null) {
				// column, in a statement with one table
				$p = $this->prev($tokens, $i - 1);
				$q = $this->next($tokens, $i + 1);
				$afterDot = $p > -1 && $tokens[$p][0] === 'punct' && $tokens[$p][1] === '.';
				$call = $q > -1 && $tokens[$q][0] === 'punct' && ($tokens[$q][1] === '(' || $tokens[$q][1] === '.');
				if(!$afterDot && !$call) {
					$table = $single;
					$column = $this->name($t);
					$colTokens = [$t];
				}
			}
			if($table === null) {
				$out[] = $t;
				continue;
			}
			$schema = $this->tableSchema($table);
			if(!isset($schema['columns'][$column])) {
				$out[] = $t;
				continue;
			}
			$class = $this->typeClass($schema['columns'][$column]);
			$end = $i + count($colTokens); // index after the column tokens
			$k = $this->next($tokens, $end); // operator
			if($k < 0) {
				$out[] = $t;
				continue;
			}
			$op = $tokens[$k];
			$isLike = ($op[0] === 'word' && $this->isWord($op, ['ILIKE', 'LIKE'])) || ($op[0] === 'punct' && ($op[1] === '~*' || $op[1] === '!~*'));
			$notLike = false;
			if($op[0] === 'word' && $this->isWord($op, 'NOT')) {
				$k2 = $this->next($tokens, $k + 1);
				if($k2 > -1 && $this->isWord($tokens[$k2], ['ILIKE', 'LIKE'])) $notLike = true;
			}
			if(($isLike || $notLike) && $class !== 'text' && $class !== 'other') {
				// LIKE/REGEXP on a number or date: compare as text, as MySQL does
				$out[] = ['word', '(' . $this->join($colTokens) . ')::text'];
				$i = $end - 1;
				continue;
			}
			$isCompare = $op[0] === 'punct' && in_array($op[1], ['=', '!=', '<>', '<', '>', '<=', '>='], true);
			$r = $isCompare ? $this->next($tokens, $k + 1) : -1;
			if($class === 'number' && $r > -1 && in_array($tokens[$r][0], ['str', 'param'])) {
				foreach($colTokens as $ct) $out[] = $ct;
				for($x = $end; $x < $r; $x++) $out[] = $tokens[$x];
				$out[] = ['word', $this->mysqlNumber($tokens[$r])];
				$i = $r;
				continue;
			}
			$out[] = $t;
		}
		return $out;
	}

	/**
	 * Get an expression that converts a string to a number the way MySQL does ('' and 'abc' are 0, '12abc' is 12)
	 *
	 * @param array $t String literal or parameter token
	 * @return string
	 *
	 */
	protected function mysqlNumber(array $t) {
		if($t[0] === 'str') {
			$value = str_replace("''", "'", substr($t[1], 1, -1));
			if(preg_match('/^\s*(-?[0-9]+(?:\.[0-9]+)?)/', $value, $m)) return $m[1] === $value ? $t[1] : $m[1];
			return '0';
		}
		$p = $t[1];
		return "(CASE WHEN ($p)::text ~ '^\\s*-?[0-9]+(\\.[0-9]+)?' THEN substring(($p)::text from '-?[0-9]+(?:\\.[0-9]+)?')::numeric ELSE 0 END)";
	}

	/**
	 * Is the SQL an INSERT that is already PostgreSQL syntax (has an ON CONFLICT clause)?
	 *
	 * Checked before tokenizing, with string literals removed so that data containing the words
	 * does not count.
	 *
	 * @param string $sql
	 * @return bool
	 *
	 */
	protected function isNativeInsert($sql) {
		if(!preg_match('/^\s*INSERT\b/i', $sql) || stripos($sql, 'CONFLICT') === false) return false;
		// a double-quoted table name is PostgreSQL (MySQL reads it as a string, a syntax error there), as
		// upsert() writes it; decided before stripping literals, since PostgreSQL literals have no
		// backslash escapes and a value ending in a backslash would be misread below
		if(preg_match('/^\s*INSERT\s+(?:INTO\s+)?"/i', $sql)) return true;
		$bare = preg_replace('/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"/s', '', $sql);
		return (bool) preg_match('/\bON\s+CONFLICT\b/i', $bare);
	}

	/**
	 * Get column types of a table: [ name => [ 'pgType' => ..., 'isText' => bool, 'identity' => bool ] ]
	 *
	 * @param string $table
	 * @return array Empty when no PDO connection is available
	 *
	 */
	protected function getColumnTypes($table) {
		$rows = $this->catalogRows(
			"SELECT column_name, data_type, is_identity FROM information_schema.columns " .
			"WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position",
			[$table]
		);
		$types = [];
		foreach($rows as $row) {
			$types[$row['column_name']] = [
				'pgType' => $row['data_type'],
				'isText' => in_array($row['data_type'], ['text', 'character varying', 'character'], true),
				'identity' => $row['is_identity'] === 'YES',
			];
		}
		return $types;
	}

	/**
	 * Get indexes of a table (requires PDO connection)
	 *
	 * @param string $table
	 * @return array Each with 'pgName', 'name' (without table prefix, or null if not prefixed), 'unique', 'primary', 'columns'
	 *
	 */
	protected function getIndexes($table) {
		$rows = $this->catalogRows(
			"SELECT i.relname AS name, ix.indisunique AS is_unique, ix.indisprimary AS is_primary, " .
			"ARRAY(SELECT pg_get_indexdef(ix.indexrelid, k + 1, true) FROM generate_subscripts(ix.indkey, 1) AS k ORDER BY k) AS cols " .
			"FROM pg_index ix JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid " .
			"JOIN pg_namespace n ON n.oid = t.relnamespace " .
			"WHERE t.relname = ? AND n.nspname = current_schema() ORDER BY i.relname",
			[$table]
		);
		$indexes = [];
		$prefix = $table . self::indexSeparator;
		foreach($rows as $row) {
			$cols = trim((string) $row['cols'], '{}');
			$indexes[] = [
				'pgName' => $row['name'],
				'name' => strpos($row['name'], $prefix) === 0 ? substr($row['name'], strlen($prefix)) : null,
				'unique' => in_array($row['is_unique'], [true, 't', '1', 1], true),
				'primary' => in_array($row['is_primary'], [true, 't', '1', 1], true),
				'columns' => $cols === '' ? [] : array_map(function($c) { return trim($c, '"'); }, str_getcsv($cols)),
			];
		}
		return $indexes;
	}

	/*********************************************************************************
	 * Statement-level translations: INSERT, DELETE, UPDATE, SHOW
	 *
	 */

	/**
	 * INSERT/REPLACE: INSERT IGNORE, INSERT ... SET, ON DUPLICATE KEY UPDATE, explicit identity values
	 *
	 * @param array $tokens
	 * @return array One or more statements
	 *
	 */
	protected function insert(array $tokens) {

		$n = count($tokens);
		$i = $this->next($tokens, 0);
		$replace = $this->isWord($tokens[$i], 'REPLACE');
		$ignore = false;
		$i++;

		// [LOW_PRIORITY|DELAYED|HIGH_PRIORITY] [IGNORE] [INTO]
		while(($j = $this->next($tokens, $i)) > -1) {
			$t = $tokens[$j];
			if($this->isWord($t, ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY'])) {
				$i = $j + 1;
			} else if($this->isWord($t, 'IGNORE')) {
				$ignore = true;
				$i = $j + 1;
			} else if($this->isWord($t, 'INTO')) {
				$i = $j + 1;
			} else {
				break;
			}
		}

		$tablePos = $this->next($tokens, $i);
		$table = $this->name($tokens[$tablePos]);
		$qTable = $this->join([$tokens[$tablePos]]);
		$i = $tablePos + 1;

		// find top-level SET (INSERT ... SET form) and ON DUPLICATE
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

		$columns = []; // column names as written (plain), in insert order
		$columnSql = []; // column names as SQL
		$explicitIdentity = false; // is a real value (not NULL) given for the identity column?
		$schema = $this->tableSchema($table);
		$identity = $schema['identity'];

		if($setPos > -1) {
			// INSERT INTO table SET a=1, b=2 => INSERT INTO table (a, b) VALUES (1, 2)
			$vals = [];
			foreach($this->splitCommas(array_slice($tokens, $setPos + 1, $bodyEnd - $setPos - 1)) as $assign) {
				$assign = $this->trimTokens($assign);
				$eq = -1;
				foreach($assign as $k => $t) {
					if($t[0] === 'punct' && $t[1] === '=') { $eq = $k; break; }
				}
				if($eq < 0) continue;
				$colTokens = $this->trimTokens(array_slice($assign, 0, $eq));
				$column = $this->name($colTokens[0]);
				$columns[] = $column;
				$columnSql[] = $this->join($colTokens);
				$value = trim($this->join($this->expressions(array_slice($assign, $eq + 1))));
				if($identity !== null && $column === $identity) {
					// MySQL treats NULL for an AUTO_INCREMENT column as "generate one"
					if(strtoupper($value) === 'NULL') $value = 'DEFAULT';
					else $explicitIdentity = true;
				}
				$vals[] = $value;
			}
			$body = ' (' . implode(', ', $columnSql) . ') VALUES (' . implode(', ', $vals) . ')';
		} else {
			$open = $this->next($tokens, $i);
			$identityPos = -1;
			if($open > -1 && $tokens[$open][0] === 'punct' && $tokens[$open][1] === '(') {
				$close = $this->matchParen($tokens, $open);
				foreach($this->splitCommas(array_slice($tokens, $open + 1, $close - $open - 1)) as $col) {
					$col = $this->trimTokens($col);
					if(!count($col)) continue;
					$column = $this->name($col[0]);
					if($identity !== null && $column === $identity) $identityPos = count($columns);
					$columns[] = $column;
					$columnSql[] = $this->join($col);
				}
			}
			$bodyTokens = $this->expressions(array_slice($tokens, $i, $bodyEnd - $i));
			if($identityPos > -1) {
				// replace NULL with DEFAULT in the identity column's position of each VALUES row
				$explicitIdentity = true;
				$m = count($bodyTokens);
				$valuesPos = -1;
				for($x = 0; $x < $m; $x++) {
					if($this->isWord($bodyTokens[$x], 'VALUES')) { $valuesPos = $x; break; }
				}
				for($x = $valuesPos > -1 ? $valuesPos + 1 : $m; $x < $m; $x++) {
					if($bodyTokens[$x][0] !== 'punct' || $bodyTokens[$x][1] !== '(') continue;
					$end = $this->matchParen($bodyTokens, $x);
					$parts = $this->splitCommas(array_slice($bodyTokens, $x + 1, $end - $x - 1));
					if(isset($parts[$identityPos])) {
						$value = $this->trimTokens($parts[$identityPos]);
						if(count($value) === 1 && $this->isWord($value[0], 'NULL')) {
							$parts[$identityPos] = [['word', 'DEFAULT']];
							$explicitIdentity = false;
						}
					}
					$rebuilt = [];
					foreach($parts as $pn => $part) {
						if($pn) $rebuilt[] = ['punct', ','];
						foreach($part as $pt) $rebuilt[] = $pt;
					}
					$bodyTokens = array_merge(array_slice($bodyTokens, 0, $x + 1), $rebuilt, array_slice($bodyTokens, $end));
					$m = count($bodyTokens);
					$x = $x + count($rebuilt) + 1;
				}
			}
			$body = $this->join($bodyTokens);
		}

		$sql = ($replace ? 'INSERT' : $this->join([$tokens[$this->next($tokens, 0)]])) . ' INTO ' . $qTable . rtrim($body);

		if($dupPos > -1 || $replace) {
			if(!count($schema['primary'])) {
				throw new \PDOException("PostgreSQL translator: ON DUPLICATE KEY UPDATE / REPLACE on $table needs a conflict target (primary key) and none is known");
			}
			$target = implode(', ', array_map([$this, 'quoteId'], $schema['primary']));
			$sets = [];
			if($replace) {
				foreach($columns as $k => $col) {
					if(in_array($col, $schema['primary'], true)) continue;
					$sets[] = $columnSql[$k] . '=excluded.' . $columnSql[$k];
				}
				if(!count($sets)) throw new \PDOException("PostgreSQL translator: REPLACE INTO $table needs a column list with non-key columns");
			} else {
				$x = $this->next($tokens, $dupPos + 1); // DUPLICATE
				$x = $this->next($tokens, $x + 1); // KEY
				$x = $this->next($tokens, $x + 1); // UPDATE
				foreach($this->splitCommas(array_slice($tokens, $x + 1)) as $assign) {
					$assign = $this->trimTokens($assign);
					$eq = -1;
					foreach($assign as $k => $t) {
						if($t[0] === 'punct' && $t[1] === '=') { $eq = $k; break; }
					}
					if($eq < 0) continue;
					$left = $this->join(array_slice($assign, 0, $eq));
					$right = $this->upsertExpression(array_slice($assign, $eq + 1), $table, $columns);
					$sets[] = "$left=$right";
				}
			}
			$sql .= " ON CONFLICT ($target) DO UPDATE SET " . implode(', ', $sets);
		} else if($ignore) {
			$sql .= ' ON CONFLICT DO NOTHING';
		}

		$statements = [$sql];

		// an explicit value for an identity column leaves its sequence behind, so advance it
		if($explicitIdentity && $identity !== null) {
			$qt = $this->quoteId($table);
			$qc = $this->quoteId($identity);
			$statements[] = "SELECT setval(pg_get_serial_sequence('$qt', '$identity'), GREATEST((SELECT MAX($qc) FROM $qt), 1))";
		}

		return $statements;
	}

	/**
	 * Translate the right-hand side of an ON DUPLICATE KEY UPDATE assignment
	 *
	 * VALUES(col) becomes excluded.col, and bare references to the table's columns are qualified
	 * with the table name (PostgreSQL otherwise reports them as ambiguous with "excluded").
	 *
	 * @param array $tokens
	 * @param string $table
	 * @param array $columns Column names being inserted
	 * @return string
	 *
	 */
	protected function upsertExpression(array $tokens, $table, array $columns) {
		$out = [];
		$n = count($tokens);
		$lower = array_map('strtolower', $columns);
		for($y = 0; $y < $n; $y++) {
			$t = $tokens[$y];
			if($this->isWord($t, 'VALUES')) {
				$z = $this->next($tokens, $y + 1);
				if($z > -1 && $tokens[$z][1] === '(') {
					$end = $this->matchParen($tokens, $z);
					$inner = $this->trimTokens(array_slice($tokens, $z + 1, $end - $z - 1));
					$out[] = ['word', 'excluded.' . $this->join([$inner[0]])];
					$y = $end;
					continue;
				}
			}
			if(($t[0] === 'word' || $t[0] === 'id') && in_array(strtolower($t[1]), $lower, true)) {
				$prev = $this->prev($tokens, $y - 1);
				$next = $this->next($tokens, $y + 1);
				$qualified = $prev > -1 && $tokens[$prev][0] === 'punct' && $tokens[$prev][1] === '.';
				$call = $next > -1 && $tokens[$next][0] === 'punct' && $tokens[$next][1] === '(';
				if(!$qualified && !$call) {
					$out[] = ['word', $table . '.' . $this->join([$t])];
					continue;
				}
			}
			$out[] = $t;
		}
		return trim($this->join($this->expressions($out)));
	}

	/**
	 * DELETE ... [ORDER BY ...] LIMIT n, and multi-table DELETE
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
		// DELETE FROM t WHERE x ORDER BY y LIMIT n => DELETE FROM t WHERE ctid IN (SELECT ctid FROM t WHERE x ORDER BY y LIMIT n)
		$j = $this->next($tokens, $i + 1); // FROM
		if(!$this->isWord($tokens[$j], 'FROM')) return $tokens;
		$k = $this->next($tokens, $j + 1); // table
		$table = $this->join([$tokens[$k]]);
		$rest = $this->join($this->expressions(array_slice($tokens, $k + 1)));
		return [['word', "DELETE FROM $table WHERE ctid IN (SELECT ctid FROM $table" . rtrim($rest) . ')']];
	}

	/**
	 * Multi-table DELETE: DELETE t FROM t [AS a] JOIN u ON cond [JOIN ...] [WHERE ...]
	 *
	 * Translated to DELETE FROM t USING u [, ...] WHERE cond [AND ...]. Only a single target table
	 * is supported, and it must be the first table in FROM.
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
		if(count($targets) !== 1) throw new \PDOException('PostgreSQL translator: unsupported multi-table DELETE (one target table is supported)');
		$target = $this->trimTokens($targets[0]);
		$target = $this->name($target[count($target) - 1]); // i.e. "t" or "t.*"
		if($target === '*' && count($targets[0]) > 2) $target = $this->name($this->trimTokens($targets[0])[0]);

		$k = $this->next($tokens, $fromPos + 1);
		$table = $this->name($tokens[$k]);
		$alias = '';
		$a = $this->next($tokens, $k + 1);
		if($a > -1 && $this->isWord($tokens[$a], 'AS')) $a = $this->next($tokens, $a + 1);
		$joinWords = ['JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'WHERE', 'NATURAL', 'STRAIGHT_JOIN', 'ORDER', 'LIMIT'];
		if($a > -1 && in_array($tokens[$a][0], ['word', 'id']) && !$this->isWord($tokens[$a], $joinWords)) {
			$alias = $this->name($tokens[$a]);
			$k = $a;
		}
		if($target !== $table && $target !== $alias) {
			throw new \PDOException('PostgreSQL translator: unsupported multi-table DELETE (target must be the first table in FROM)');
		}

		// collect joined tables and their ON conditions
		$using = [];
		$conds = [];
		$n = count($tokens);
		$x = $k + 1;
		$where = '';
		while(($x = $this->next($tokens, $x)) > -1) {
			$t = $tokens[$x];
			if($this->isWord($t, ['INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL', 'OUTER'])) {
				$x++;
				continue;
			}
			if($this->isWord($t, ['JOIN', 'STRAIGHT_JOIN'])) {
				$onPos = -1;
				for($y = $x + 1; $y < $n; $y++) {
					if($this->isWord($tokens[$y], 'ON')) { $onPos = $y; break; }
				}
				if($onPos < 0) throw new \PDOException('PostgreSQL translator: unsupported multi-table DELETE (JOIN without ON)');
				$using[] = trim($this->join($this->expressions(array_slice($tokens, $x + 1, $onPos - $x - 1))));
				$condEnd = $n;
				$depth = 0;
				for($y = $onPos + 1; $y < $n; $y++) {
					$ty = $tokens[$y];
					if($ty[0] === 'punct') {
						if($ty[1] === '(') $depth++;
						if($ty[1] === ')') $depth--;
						continue;
					}
					if($depth === 0 && $this->isWord($ty, ['JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL', 'STRAIGHT_JOIN', 'WHERE', 'ORDER', 'LIMIT'])) { $condEnd = $y; break; }
				}
				$conds[] = trim($this->join($this->booleanContext($this->expressions(array_slice($tokens, $onPos + 1, $condEnd - $onPos - 1)))));
				$x = $condEnd;
				continue;
			}
			if($this->isWord($t, 'WHERE')) {
				$where = trim($this->join($this->booleanContext($this->expressions(array_slice($tokens, $x + 1)))));
				break;
			}
			throw new \PDOException('PostgreSQL translator: unsupported multi-table DELETE syntax near ' . $this->join([$t]));
		}
		if($where !== '') $conds[] = $where;

		$sql = 'DELETE FROM ' . $this->quoteId($table) . ($alias !== '' ? ' AS ' . $this->quoteId($alias) : '');
		if(count($using)) $sql .= ' USING ' . implode(', ', $using);
		if(count($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);
		return [['word', $sql]];
	}

	/**
	 * UPDATE ... ORDER BY ... [LIMIT n]
	 *
	 * ORDER BY is removed. When LIMIT is present, rows are selected by a ctid subquery.
	 * MySQL code using ORDER BY to avoid unique key collisions during an update needs a different
	 * approach here (see WireDatabaseDialect::supportsUpdateOrderBy()).
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
		if($limitPos < 0) return $this->trimTokens(array_slice($tokens, 0, $orderPos));
		// UPDATE t SET ... WHERE w ORDER BY o LIMIT n => UPDATE t SET ... WHERE ctid IN (SELECT ctid FROM t WHERE w ORDER BY o LIMIT n)
		$i = $this->next($tokens, $this->next($tokens, 0) + 1); // table
		$table = $this->join([$tokens[$i]]);
		$setEnd = $wherePos > -1 ? $wherePos : $end;
		$head = $this->trimTokens(array_slice($tokens, 0, $setEnd));
		$sub = 'SELECT ctid FROM ' . $table . ' ' . trim($this->join($this->expressions(array_slice($tokens, $setEnd))));
		$head[] = ['word', " WHERE ctid IN ($sub)"];
		return $head;
	}

	/**
	 * SHOW statements, emulated with information_schema and pg_catalog queries that return MySQL-shaped rows
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

		$like = null;
		$whereTokens = [];
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
				$whereTokens = array_slice($rest, $x + 1);
				break;
			}
		}

		$qt = "'" . str_replace("'", "''", $table) . "'";

		// MySQL result column names that a WHERE clause may refer to
		$columnAliases = [
			'field' => 'Field', 'type' => 'Type', 'null' => 'Null', 'key' => 'Key', 'default' => 'Default', 'extra' => 'Extra',
			'key_name' => 'Key_name', 'non_unique' => 'Non_unique', 'seq_in_index' => 'Seq_in_index', 'column_name' => 'Column_name',
			'index_type' => 'Index_type', 'table' => 'Table', 'name' => 'Name', 'engine' => 'Engine',
			'variable_name' => 'Variable_name', 'value' => 'Value',
		];
		$where = '';
		if(count($whereTokens)) {
			foreach($whereTokens as $k => $t) {
				if($t[0] === 'word' && isset($columnAliases[strtolower($t[1])])) $whereTokens[$k] = ['id', $columnAliases[strtolower($t[1])]];
			}
			$where = trim($this->join($this->expressions($whereTokens)));
		}

		switch($what) {
			case 'TABLES':
				$sql = 'SELECT table_name AS ' . $this->quoteId('Tables_in_db') . " FROM information_schema.tables WHERE table_schema=current_schema() AND table_type='BASE TABLE'";
				if($like !== null) $sql .= " AND table_name ILIKE $like";
				if($where !== '') $sql .= " AND ($where)";
				return $sql . ' ORDER BY table_name';

			case 'COLUMNS':
			case 'FIELDS':
				$pk = "SELECT a.attname FROM pg_index i JOIN pg_attribute a ON a.attrelid=i.indrelid AND a.attnum=ANY(i.indkey) " .
					"JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace " .
					"WHERE c.relname=$qt AND n.nspname=current_schema() AND i.indisprimary";
				$sql =
					'SELECT column_name AS "Field", ' .
					"CASE data_type WHEN 'character varying' THEN 'varchar(' || character_maximum_length || ')' " .
					"WHEN 'character' THEN 'char(' || character_maximum_length || ')' WHEN 'integer' THEN 'int' " .
					"WHEN 'timestamp without time zone' THEN 'datetime' WHEN 'double precision' THEN 'double' WHEN 'real' THEN 'float' " .
					'ELSE data_type END AS "Type", ' .
					"CASE WHEN is_nullable='YES' THEN 'YES' ELSE 'NO' END AS \"Null\", " .
					"CASE WHEN column_name IN ($pk) THEN 'PRI' ELSE '' END AS \"Key\", " .
					'column_default AS "Default", ' .
					"CASE WHEN is_identity='YES' THEN 'auto_increment' ELSE '' END AS \"Extra\" " .
					"FROM information_schema.columns WHERE table_schema=current_schema() AND table_name=$qt ORDER BY ordinal_position";
				if($like !== null) $sql = "SELECT * FROM ($sql) s WHERE \"Field\" ILIKE $like";
				if($where !== '') $sql = "SELECT * FROM ($sql) s WHERE $where";
				return $sql;

			case 'INDEX':
			case 'INDEXES':
			case 'KEYS':
				$prefix = $table . self::indexSeparator;
				$sql =
					"SELECT $qt AS \"Table\", " .
					"CASE WHEN ix.indisprimary THEN 'PRIMARY' WHEN left(i.relname, " . strlen($prefix) . ")='" . str_replace("'", "''", $prefix) . "' THEN substr(i.relname, " . (strlen($prefix) + 1) . ") ELSE i.relname END AS \"Key_name\", " .
					'CASE WHEN ix.indisunique THEN 0 ELSE 1 END AS "Non_unique", ' .
					'k.ord AS "Seq_in_index", a.attname AS "Column_name", \'BTREE\' AS "Index_type" ' .
					'FROM pg_index ix JOIN pg_class t ON t.oid=ix.indrelid JOIN pg_class i ON i.oid=ix.indexrelid ' .
					'JOIN pg_namespace n ON n.oid=t.relnamespace ' .
					'CROSS JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS k(attnum, ord) ' .
					'JOIN pg_attribute a ON a.attrelid=t.oid AND a.attnum=k.attnum ' .
					"WHERE t.relname=$qt AND n.nspname=current_schema()";
				$sql = "SELECT * FROM ($sql) s" . ($where !== '' ? " WHERE $where" : '') . ' ORDER BY "Key_name", "Seq_in_index"';
				return $sql;

			case 'CREATE TABLE':
				throw new \PDOException('PostgreSQL translator: SHOW CREATE TABLE is not supported yet');

			case 'TABLE STATUS':
				$sql =
					'SELECT table_name AS "Name", \'PostgreSQL\' AS "Engine", NULL AS "Rows", NULL AS "Data_length", ' .
					'NULL AS "Index_length", NULL AS "Auto_increment", NULL AS "Collation" ' .
					"FROM information_schema.tables WHERE table_schema=current_schema() AND table_type='BASE TABLE'";
				if($like !== null) $sql .= " AND table_name ILIKE $like";
				if($where !== '') $sql = "SELECT * FROM ($sql) s WHERE $where";
				return $sql;

			case 'VARIABLES':
			case 'STATUS':
			case 'WARNINGS':
			case 'ENGINES':
			case 'CHARACTER SET':
			case 'COLLATION':
				return 'SELECT NULL AS "Variable_name", NULL AS "Value" WHERE false';
		}

		return 'SELECT NULL WHERE false';
	}

	/*********************************************************************************
	 * DDL
	 *
	 */

	/**
	 * Map a MySQL column type to PostgreSQL
	 *
	 * @param string $typeWord Uppercase MySQL type word (INT, VARCHAR, ...)
	 * @param string $typeArgs Parenthesized args as written, i.e. "(10)" or "(10,2)", or blank
	 * @return string
	 *
	 */
	protected function mapType($typeWord, $typeArgs) {
		switch($typeWord) {
			case 'TINYINT':
			case 'SMALLINT':
			case 'BOOL':
			case 'BOOLEAN':
			case 'YEAR': return 'smallint';
			case 'MEDIUMINT':
			case 'INT':
			case 'INTEGER': return 'integer';
			case 'BIGINT': return 'bigint';
			case 'FLOAT': return 'real';
			case 'DOUBLE':
			case 'REAL': return 'double precision';
			case 'DECIMAL':
			case 'NUMERIC': return 'numeric' . $typeArgs;
			case 'VARCHAR': return 'varchar' . $typeArgs;
			// MySQL strips trailing spaces from CHAR on read; PostgreSQL's char(n) pads them, which would
			// corrupt fixed-width values such as password salts, so CHAR becomes varchar
			case 'CHAR': return 'varchar' . $typeArgs;
			case 'TINYTEXT':
			case 'TEXT':
			case 'MEDIUMTEXT':
			case 'LONGTEXT':
			case 'ENUM':
			case 'SET': return 'text';
			case 'DATETIME':
			case 'TIMESTAMP': return 'timestamp';
			case 'DATE': return 'date';
			case 'TIME': return 'time';
			case 'TINYBLOB':
			case 'BLOB':
			case 'MEDIUMBLOB':
			case 'LONGBLOB':
			case 'BINARY':
			case 'VARBINARY': return 'bytea';
			case 'JSON': return 'jsonb';
		}
		return strtolower($typeWord) . $typeArgs;
	}

	/**
	 * Parse a MySQL column definition
	 *
	 * @param array $def Tokens
	 * @return array
	 *
	 */
	protected function columnDef(array $def) {

		$name = $this->name($def[0]);
		$n = count($def);
		$autoIncrement = false;
		$primary = false;
		$unique = false;
		$nullSpec = ''; // 'NOT NULL', 'NULL' or blank when not specified
		$default = null;

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
		if(preg_match('/INT$/', $typeWord) || in_array($typeWord, ['ENUM', 'SET', 'FLOAT', 'DOUBLE', 'REAL', 'BOOL', 'BOOLEAN', 'YEAR'])) {
			$typeArgs = ''; // display widths and enum values do not carry over
		}
		$pgType = $this->mapType($typeWord, $typeArgs);
		$isText = (bool) preg_match('/CHAR|TEXT|ENUM|SET|JSON/', $typeWord);

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
				$unique = true;
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
				// ON UPDATE CURRENT_TIMESTAMP[()]: no PostgreSQL equivalent without a trigger, ignored
				$y = $this->next($def, $x + 1); // UPDATE
				$z = $y > -1 ? $this->next($def, $y + 1) : -1; // CURRENT_TIMESTAMP
				if($z === -1) break;
				$p = $this->next($def, $z + 1);
				if($p > -1 && $def[$p][1] === '(') $z = $this->matchParen($def, $p);
				$x = $z;
			} else if($w === 'DEFAULT') {
				$y = $this->next($def, $x + 1);
				$v = $def[$y];
				if($this->isWord($v, ['CURRENT_TIMESTAMP', 'NOW', 'LOCALTIME', 'LOCALTIMESTAMP'])) {
					$z = $this->next($def, $y + 1);
					if($z > -1 && $def[$z][1] === '(') $y = $this->matchParen($def, $z);
					$default = 'CURRENT_TIMESTAMP';
				} else if($v[1] === '(') {
					$end = $this->matchParen($def, $y);
					$default = $this->join(array_slice($def, $y, $end - $y + 1));
					$y = $end;
				} else if($v[0] === 'punct' && $v[1] === '-') {
					$z = $this->next($def, $y + 1);
					$default = '-' . $def[$z][1];
					$y = $z;
				} else {
					$default = $this->join([$v]);
				}
				$x = $y;
			} else if($w === 'NOT') {
				$x = $this->next($def, $x + 1); // NULL
				$nullSpec = 'NOT NULL';
			} else if($w === 'NULL') {
				$nullSpec = 'NULL';
			} else if($w === 'AFTER' || $w === 'FIRST') {
				if($w === 'AFTER') $x = $this->next($def, $x + 1);
			}
		}

		$sql = $pgType;
		if($autoIncrement) $sql .= ' GENERATED BY DEFAULT AS IDENTITY';
		if($nullSpec === 'NOT NULL') $sql .= ' NOT NULL';
		if($default !== null) $sql .= " DEFAULT $default";
		if($unique) $sql .= ' UNIQUE';

		return [
			'name' => $name,
			'sql' => $sql,
			'pgType' => $pgType,
			'isText' => $isText,
			'autoIncrement' => $autoIncrement,
			'primary' => $primary,
			'nullSpec' => $nullSpec,
			'default' => $default,
		];
	}

	/**
	 * Get column names and prefix lengths from an index definition: KEY name (a, b(10))
	 *
	 * @param array $def Tokens
	 * @return array [ column names, prefix lengths indexed like the names ]
	 *
	 */
	protected function indexColumns(array $def) {
		$open = -1;
		foreach($def as $k => $t) {
			if($t[0] === 'punct' && $t[1] === '(') { $open = $k; break; }
		}
		if($open < 0) return [[], []];
		$close = $this->matchParen($def, $open);
		$cols = [];
		$lens = [];
		foreach($this->splitCommas(array_slice($def, $open + 1, $close - $open - 1)) as $part) {
			$part = $this->trimTokens($part);
			if(!count($part)) continue;
			$n = count($cols);
			$cols[] = $this->name($part[0]);
			if(isset($part[1]) && $part[1][1] === '(' && isset($part[2]) && $part[2][0] === 'num') $lens[$n] = (int) $part[2][1];
		}
		return [$cols, $lens];
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
	 * Get CREATE INDEX statement from a MySQL index definition, or blank string to skip it
	 *
	 * @param string $table
	 * @param array $def Tokens of the definition (KEY name (cols), UNIQUE KEY ..., FULLTEXT KEY ...)
	 * @param array $columnTypes Column info as from columnDef() or getColumnTypes(), indexed by column name
	 * @param bool $ifNotExists
	 * @return string
	 *
	 */
	protected function indexDef($table, array $def, array $columnTypes, $ifNotExists = false) {
		$unique = false;
		$fulltext = false;
		foreach($def as $t) {
			if($this->isWord($t, 'UNIQUE')) $unique = true;
			if($this->isWord($t, ['FULLTEXT', 'SPATIAL'])) $fulltext = true;
			if($t[0] === 'punct') break;
		}
		list($cols, $lens) = $this->indexColumns($def);
		if(!count($cols)) return '';
		$name = $this->indexDefName($def);
		if($name === null) $name = $cols[0];
		return $this->createIndexSql($table, $name, $cols, $lens, $unique, $fulltext, $ifNotExists, $columnTypes);
	}

	/**
	 * Get CREATE INDEX statement, or blank string when the index cannot be created
	 *
	 * @param string $table
	 * @param string $name Index name without table prefix
	 * @param array $cols
	 * @param array $prefixLens MySQL prefix lengths indexed like $cols
	 * @param bool $unique
	 * @param bool $fulltext
	 * @param bool $ifNotExists
	 * @param array $columnTypes
	 * @return string
	 *
	 */
	protected function createIndexSql($table, $name, array $cols, array $prefixLens, $unique, $fulltext, $ifNotExists, array $columnTypes) {
		if($fulltext && !$this->trigramAvailable) return '';
		$parts = [];
		foreach($cols as $n => $col) {
			$q = $this->quoteId($col);
			// a prefix on an unbounded text column becomes an expression index, since btree entries are limited in size
			$isText = isset($columnTypes[$col]) && $columnTypes[$col]['isText'] && strpos($columnTypes[$col]['pgType'], 'text') === 0;
			$parts[] = isset($prefixLens[$n]) && $isText ? "left($q, $prefixLens[$n])" : $q;
		}
		$sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') .
			$this->quoteId($this->indexName($table, $name)) . ' ON ' . $this->quoteId($table);
		if($fulltext) {
			// FULLTEXT has no equivalent here; a trigram index accelerates the ILIKE fallback (requires pg_trgm)
			$gin = [];
			foreach($cols as $col) $gin[] = $this->quoteId($col) . ' gin_trgm_ops';
			return $sql . ' USING gin (' . implode(', ', $gin) . ')';
		}
		return $sql . ' (' . implode(', ', $parts) . ')';
	}

	/**
	 * CREATE TABLE
	 *
	 * @param array $tokens
	 * @return string|array Array when there are indexes (CREATE TABLE followed by CREATE INDEX statements)
	 *
	 */
	protected function createTable(array $tokens) {

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
			// CREATE TABLE x LIKE y, CREATE TABLE x AS SELECT ...: pass through with expression translation
			return $this->join($this->expressions($tokens));
		}

		$close = $this->matchParen($tokens, $open);
		$defs = $this->splitCommas(array_slice($tokens, $open + 1, $close - $open - 1));

		$columns = [];
		$constraints = [];
		$indexDefs = [];
		$primaryCols = [];

		foreach($defs as $def) {
			$def = $this->trimTokens($def);
			if(!count($def)) continue;
			$first = $def[0];
			if($this->isWord($first, 'PRIMARY')) {
				list($primaryCols) = $this->indexColumns($def);
			} else if($this->isWord($first, ['KEY', 'INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL'])) {
				$indexDefs[] = $def;
			} else if($this->isWord($first, ['CONSTRAINT', 'FOREIGN', 'CHECK'])) {
				$constraints[] = $this->join($this->expressions($def));
			} else {
				$col = $this->columnDef($def);
				if($col['primary']) $primaryCols = [$col['name']];
				$columns[$col['name']] = $col;
			}
		}

		$lines = [];
		foreach($columns as $name => $col) $lines[] = $this->quoteId($name) . ' ' . $col['sql'];
		if(count($primaryCols)) $lines[] = 'PRIMARY KEY (' . implode(', ', array_map([$this, 'quoteId'], $primaryCols)) . ')';
		foreach($constraints as $c) $lines[] = $c;

		$statements = [
			'CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') .
			$this->quoteId($table) . " (\n  " . implode(",\n  ", $lines) . "\n)"
		];

		foreach($indexDefs as $def) {
			$sql = $this->indexDef($table, $def, $columns, $ifNotExists);
			if($sql !== '') $statements[] = $sql;
		}

		$this->clearSchemaCache($table);

		return $statements;
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
		if($onPos < 0) return $this->join($this->expressions($tokens));
		$ifNotExists = $this->hasTopLevelWord(array_slice($tokens, 0, $onPos), 'EXISTS');
		$def = array_values(array_filter(array_slice($tokens, 0, $onPos), function($t) {
			return !($t[0] === 'word' && in_array(strtoupper($t[1]), ['CREATE', 'IF', 'NOT', 'EXISTS']));
		}));
		$i = $this->next($tokens, $onPos + 1);
		$table = $this->name($tokens[$i]);
		foreach(array_slice($tokens, $i + 1) as $t) $def[] = $t;
		$sql = $this->indexDef($table, $def, $this->getColumnTypes($table), $ifNotExists);
		return $sql === '' ? 'SELECT 1' : $sql;
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
		$j = $this->next($tokens, $i + 1); // ON
		$k = $j > -1 ? $this->next($tokens, $j + 1) : -1;
		if($k < 0) return 'DROP INDEX IF EXISTS ' . $this->quoteId($index);
		$table = $this->name($tokens[$k]);
		return 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $index));
	}

	/**
	 * TRUNCATE [TABLE] t
	 *
	 * @param array $tokens
	 * @return string
	 *
	 */
	protected function truncate(array $tokens) {
		$i = $this->next($tokens, $this->next($tokens, 0) + 1);
		if($this->isWord($tokens[$i], 'TABLE')) $i = $this->next($tokens, $i + 1);
		$table = $this->name($tokens[$i]);
		return 'TRUNCATE TABLE ' . $this->quoteId($table) . ' RESTART IDENTITY';
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
				throw new \PDOException('PostgreSQL translator: unsupported RENAME TABLE syntax');
			}
			foreach($this->renameTableStatements($this->name($pair[0]), $this->name($pair[2])) as $sql) $statements[] = $sql;
		}
		return $statements;
	}

	/**
	 * Get statements to rename a table, including its table-prefixed index and primary key names
	 *
	 * Index and constraint names are per schema in PostgreSQL and are not renamed with the table,
	 * so the ones this translator named after the table are renamed too (requires a PDO connection).
	 *
	 * @param string $from
	 * @param string $to
	 * @return array
	 *
	 */
	protected function renameTableStatements($from, $to) {
		$statements = ['ALTER TABLE ' . $this->quoteId($from) . ' RENAME TO ' . $this->quoteId($to)];
		foreach($this->getIndexes($from) as $index) {
			if($index['primary']) {
				if($index['pgName'] === "{$from}_pkey") {
					$statements[] = 'ALTER TABLE ' . $this->quoteId($to) . ' RENAME CONSTRAINT ' . $this->quoteId("{$from}_pkey") . ' TO ' . $this->quoteId("{$to}_pkey");
				}
			} else if($index['name'] !== null) {
				$statements[] = 'ALTER INDEX ' . $this->quoteId($index['pgName']) . ' RENAME TO ' . $this->quoteId($this->indexName($to, $index['name']));
			}
		}
		$this->clearSchemaCache($from);
		$this->clearSchemaCache($to);
		return $statements;
	}

	/**
	 * Get column SQL suitable for ALTER TABLE ADD COLUMN
	 *
	 * A NOT NULL column added to a table with rows needs a default; MySQL supplies an implicit one.
	 *
	 * @param array $col
	 * @return string
	 *
	 */
	protected function addColumnSql(array $col) {
		$sql = $col['sql'];
		if($col['nullSpec'] === 'NOT NULL' && $col['default'] === null) {
			$sql .= preg_match('/int|numeric|real|double/', $col['pgType']) ? ' DEFAULT 0' : " DEFAULT ''";
		}
		return $sql;
	}

	/**
	 * ALTER TABLE
	 *
	 * @param array $tokens
	 * @return string|array
	 *
	 */
	protected function alterTable(array $tokens) {

		$i = $this->next($tokens, $this->next($tokens, 0) + 1); // TABLE
		$i = $this->next($tokens, $i + 1);
		$table = $this->name($tokens[$i]);
		$qTable = $this->quoteId($table);
		$specs = $this->splitCommas(array_slice($tokens, $i + 1));
		$statements = [];
		$renameTo = '';
		$columnTypes = null; // loaded on demand

		foreach($specs as $spec) {
			$spec = $this->trimTokens($spec);
			if(!count($spec)) continue;
			$w = strtoupper($spec[0][1]);
			$rest = array_slice($spec, 1);
			$j = $this->next($rest, 0);
			$w2 = $j > -1 && $rest[$j][0] === 'word' ? strtoupper($rest[$j][1]) : '';

			if($w === 'ADD') {
				if(in_array($w2, ['INDEX', 'KEY', 'UNIQUE', 'FULLTEXT', 'SPATIAL'])) {
					if($columnTypes === null) $columnTypes = $this->getColumnTypes($table);
					$sql = $this->indexDef($table, $rest, $columnTypes);
					if($sql !== '') $statements[] = $sql;
				} else if($w2 === 'PRIMARY') {
					list($cols) = $this->indexColumns($rest);
					$statements[] = "ALTER TABLE $qTable ADD PRIMARY KEY (" . implode(', ', array_map([$this, 'quoteId'], $cols)) . ')';
				} else if($w2 === 'CONSTRAINT') {
					throw new \PDOException("PostgreSQL translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
				} else {
					if($w2 === 'COLUMN') $rest = array_slice($rest, $j + 1);
					$rest = $this->trimTokens($rest);
					$colDefs = [$rest];
					if(count($rest) && $rest[0][1] === '(') {
						$end = $this->matchParen($rest, 0);
						$colDefs = $this->splitCommas(array_slice($rest, 1, $end - 1));
					}
					foreach($colDefs as $colDef) {
						$col = $this->columnDef($this->trimTokens($colDef));
						$statements[] = "ALTER TABLE $qTable ADD COLUMN " . $this->quoteId($col['name']) . ' ' . $this->addColumnSql($col);
					}
				}

			} else if($w === 'DROP') {
				if($w2 === 'INDEX' || $w2 === 'KEY') {
					$k = $this->next($rest, $j + 1);
					$statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $this->name($rest[$k])));
				} else if($w2 === 'PRIMARY') {
					$statements[] = "ALTER TABLE $qTable DROP CONSTRAINT IF EXISTS " . $this->quoteId("{$table}_pkey");
				} else {
					if($w2 === 'COLUMN') $j = $this->next($rest, $j + 1);
					$statements[] = "ALTER TABLE $qTable DROP COLUMN " . $this->quoteId($this->name($rest[$j]));
				}

			} else if($w === 'RENAME') {
				if($w2 === 'COLUMN') {
					$parts = array_values(array_filter(array_slice($rest, $j + 1), function($t) { return $t[0] !== 'ws'; }));
					$statements[] = "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($this->name($parts[0])) . ' TO ' . $this->quoteId($this->name($parts[2]));
				} else if($w2 === 'INDEX' || $w2 === 'KEY') {
					$parts = array_values(array_filter(array_slice($rest, $j + 1), function($t) { return $t[0] !== 'ws'; }));
					$statements[] = 'ALTER INDEX ' . $this->quoteId($this->indexName($table, $this->name($parts[0]))) . ' RENAME TO ' . $this->quoteId($this->indexName($table, $this->name($parts[2])));
				} else {
					if($w2 === 'TO' || $w2 === 'AS') $j = $this->next($rest, $j + 1);
					$renameTo = $this->name($rest[$j]);
				}

			} else if($w === 'MODIFY' || $w === 'CHANGE') {
				if($w2 === 'COLUMN') $rest = array_slice($rest, $j + 1);
				$rest = $this->trimTokens($rest);
				$oldName = $this->name($rest[0]);
				if($w === 'CHANGE') $rest = $this->trimTokens(array_slice($rest, 1));
				$col = $this->columnDef($rest);
				if($col['name'] !== $oldName) {
					$statements[] = "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($oldName) . ' TO ' . $this->quoteId($col['name']);
				}
				$qCol = $this->quoteId($col['name']);
				$actions = ["ALTER COLUMN $qCol TYPE $col[pgType]"];
				if($col['nullSpec'] === 'NOT NULL') $actions[] = "ALTER COLUMN $qCol SET NOT NULL";
				if($col['nullSpec'] === 'NULL') $actions[] = "ALTER COLUMN $qCol DROP NOT NULL";
				if($col['default'] !== null) $actions[] = "ALTER COLUMN $qCol SET DEFAULT $col[default]";
				$statements[] = "ALTER TABLE $qTable " . implode(', ', $actions);

			} else if(in_array($w, ['ENGINE', 'DEFAULT', 'CHARACTER', 'CHARSET', 'COLLATE', 'CONVERT', 'AUTO_INCREMENT', 'COMMENT', 'ORDER', 'ALGORITHM', 'LOCK'])) {
				// table options: no PostgreSQL equivalent
				continue;

			} else {
				throw new \PDOException("PostgreSQL translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
			}
		}

		if($renameTo !== '') {
			foreach($this->renameTableStatements($table, $renameTo) as $sql) $statements[] = $sql;
		}

		$this->clearSchemaCache($table);

		if(!count($statements)) return 'SELECT 1';

		return $statements;
	}
}
