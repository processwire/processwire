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
	 * Prefix of the comment that records the MySQL name of an index whose name was shortened (see indexName())
	 *
	 */
	const indexCommentPrefix = 'pw_index:';

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
	 * Is the PDO connection a PostgreSQL one? (null until checked)
	 *
	 * @var bool|null
	 *
	 */
	protected $pdoIsPgsql = null;

	/**
	 * Do the pw_fold() and pw_unaccent() functions exist, so that text can be compared as MySQL does?
	 *
	 * @var bool
	 *
	 */
	protected $foldAvailable = false;

	/**
	 * Do the pw_json_*() functions exist, so that MySQL's JSON functions can be translated? (see setupJson())
	 *
	 * @var bool
	 *
	 */
	protected $jsonAvailable = false;

	/**
	 * Suffix of the folded companion index of a unique or primary key on text columns
	 *
	 */
	const foldIndexSuffix = '__fold';

	/**
	 * Suffix of the GIN index that a jsonb column gets (see jsonIndexSql())
	 *
	 */
	const jsonIndexSuffix = '__json';

	/**
	 * Suffix of the partial index of a jsonb column's documents with nested arrays (see jsonIndexSql())
	 *
	 */
	const jsonNestedIndexSuffix = '__jsonnest';

	/**
	 * Do the pw_tsvector() and pw_tsquery() functions exist, so that MATCH ... AGAINST can be full text search?
	 *
	 * @var bool
	 *
	 */
	protected $fulltextAvailable = false;

	/**
	 * Suffix of the full text search (tsvector) index that accompanies a FULLTEXT key's trigram index
	 *
	 */
	const fulltextIndexSuffix = '__fts';

	/**
	 * Suffix of the stored tsvector column of a FULLTEXT-indexed column (`data__tsv`, generated from pw_tsvector(data))
	 *
	 */
	const vectorColumnSuffix = '__tsv';

	/**
	 * Marker function that setupFulltext() creates last: bump its version when the functions change, so that sites set up again
	 *
	 */
	const fulltextMarker = 'pw_fulltext_v1';

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
		if($this->pdoIsPgsql === null) {
			try {
				$this->pdoIsPgsql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
			} catch(\PDOException $e) {
				$this->pdoIsPgsql = false;
			}
		}
		// another database (i.e. a translator constructed on a MySQL connection for tests): no schema knowledge
		if(!$this->pdoIsPgsql) return [];
		$was = $this->introspecting;
		$this->introspecting = true;
		try {
			// errors are not swallowed: a failed lookup would otherwise silently change the translation
			$query = $pdo->prepare($sql);
			$query->execute($params);
			return $query->fetchAll($mode);
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
		if(preg_match('/^\s*(ALTER|RENAME|TRUNCATE|INSERT|REPLACE|CREATE|DROP)\b/i', $sql)) return $result; // depends on or changes the schema
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
	 * Quoting the definition alone would break its references, so both are quoted, with the case of the
	 * definition. Applies to the whole statement, subqueries included, with one exception taken from
	 * MySQL's scoping: WHERE and ON cannot see the select-list aliases of their own query, so a bare
	 * name there is a column (i.e. `SELECT id AS ID ... WHERE ID > 5`) and is left alone.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function quoteCaseAliases(array $tokens) {
		// walk the tokens tracking the query scope (one per SELECT) and clause, calling $visit for each word
		$walk = function(callable $visit) use($tokens) {
			$frames = [['scope' => 0, 'clause' => '']];
			$scopes = 0;
			foreach($tokens as $i => $t) {
				$f = count($frames) - 1;
				if($t[0] === 'punct') {
					if($t[1] === '(') $frames[] = $frames[$f];
					if($t[1] === ')' && $f > 0) array_pop($frames);
					continue;
				}
				if($t[0] !== 'word') continue;
				$w = strtoupper($t[1]);
				if($w === 'SELECT') {
					$frames[$f] = ['scope' => ++$scopes, 'clause' => 'select'];
				} else if($w === 'WHERE' || $w === 'ON') {
					$frames[$f]['clause'] = 'cond';
				} else if(in_array($w, ['FROM', 'JOIN', 'GROUP', 'ORDER', 'HAVING', 'SET', 'LIMIT', 'UNION'], true)) {
					$frames[$f]['clause'] = $w === 'JOIN' ? 'from' : strtolower($w);
				}
				$visit($i, $t, $frames[$f]);
			}
		};
		$aliases = []; // lowercase name => [ 'name' => as defined, 'selectScopes' => [ scope => true ] ]
		$walk(function($i, $t, $frame) use($tokens, &$aliases) {
			if(!$this->isWord($t, 'AS')) return;
			$j = $this->next($tokens, $i + 1);
			if($j < 0 || $tokens[$j][0] !== 'word' || !preg_match('/[A-Z]/', $tokens[$j][1])) return;
			$key = strtolower($tokens[$j][1]);
			if(!isset($aliases[$key])) $aliases[$key] = ['name' => $tokens[$j][1], 'selectScopes' => []];
			if($frame['clause'] === 'select') $aliases[$key]['selectScopes'][$frame['scope']] = true;
		});
		if(!count($aliases)) return $tokens;
		$out = $tokens;
		$walk(function($i, $t, $frame) use($tokens, $aliases, &$out) {
			$key = strtolower($t[1]);
			if(!isset($aliases[$key])) return;
			$j = $this->next($tokens, $i + 1);
			$next = $j > -1 && $tokens[$j][0] === 'punct' ? $tokens[$j][1] : '';
			if($next === '(') return; // function call
			$p = $this->prev($tokens, $i - 1);
			$qualified = $next === '.' || ($p > -1 && $tokens[$p][0] === 'punct' && $tokens[$p][1] === '.');
			$definition = $p > -1 && $this->isWord($tokens[$p], 'AS');
			if(!$qualified && !$definition && isset($aliases[$key]['selectScopes'][$frame['scope']])
				&& !in_array($frame['clause'], ['group', 'order', 'having'], true)) {
				return; // its own query's select list and WHERE/ON see columns, not select aliases
			}
			$out[$i] = ['id', $aliases[$key]['name']];
		});
		return $out;
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
			return $this->nativeInsert($sql);
		}

		$tokens = $this->quoteCaseAliases($this->tokenize($sql));
		$words = $this->leadingWords($tokens, 4);
		$first = isset($words[0]) ? $words[0] : '';
		$second = isset($words[1]) ? $words[1] : '';

		switch($first) {
			case 'INSERT':
			case 'REPLACE':
				return $this->insert($this->zeroDates($tokens));
			case 'DELETE':
			case 'UPDATE':
				break; // rewritten below, after the expression passes (which need the statement's own shape)
			case 'SHOW':
				return $this->show($tokens);
			case 'DESCRIBE':
			case 'DESC':
			case 'EXPLAIN':
				// DESCRIBE table [column] is equivalent to SHOW COLUMNS FROM table [LIKE column]
				if($first === 'EXPLAIN' && in_array($second, ['SELECT', 'UPDATE', 'DELETE', 'INSERT', 'REPLACE', 'WITH'])) {
					$first = $second; // translated as the statement it explains
					break;
				}
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
				if($second === 'TABLE') {
					// forget what we know about the dropped tables (a table of the same name may be created next)
					$this->schema = [];
					$this->cache = [];
				}
				break;
			case 'DO':
				// DO expr (MySQL: evaluate without returning a result)
				$i = $this->next($tokens, 0);
				$tokens[$i] = ['word', 'SELECT'];
				$first = 'SELECT';
				break;
		}

		if($this->jsonAvailable) $tokens = $this->jsonContainment($tokens);
		$tokens = $this->expressions($tokens);
		if($this->jsonAvailable) $tokens = $this->jsonComparisons($tokens);
		$tokens = $this->booleanContext($tokens);
		$tokens = $this->subqueries($tokens);
		$tokens = $this->typedComparisons($tokens);
		if($first === 'SELECT') $tokens = $this->selectPasses($tokens);
		if($first === 'DELETE') $tokens = $this->deleteLimit($tokens);
		if($first === 'UPDATE') $tokens = $this->updateOrderLimit($this->updateJoin($tokens));

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
		return self::pgIndexName($table, $index);
	}

	/**
	 * Get the PostgreSQL name of an index (see indexName())
	 *
	 * @param string $table
	 * @param string $index Index name without table prefix
	 * @return string
	 *
	 */
	public static function pgIndexName($table, $index) {
		$name = $table . self::indexSeparator . $index;
		// PostgreSQL truncates names to 63 bytes, which could make two long ones the same: a hashed tail keeps
		// them distinct, and indexCommentSql() records the MySQL name, which getIndexes() reports
		if(strlen($name) > 63) $name = substr($name, 0, 54) . '_' . substr(md5($name), 0, 8);
		return $name;
	}

	/**
	 * Get the MySQL name of an index from its PostgreSQL name and comment (see indexName())
	 *
	 * @param string $table
	 * @param string $pgName
	 * @param string|null $comment
	 * @return string|null Null when the index was not named by ProcessWire (no table prefix)
	 *
	 */
	public static function mysqlIndexName($table, $pgName, $comment) {
		if(is_string($comment) && strpos($comment, self::indexCommentPrefix) === 0) return substr($comment, strlen(self::indexCommentPrefix));
		$prefix = $table . self::indexSeparator;
		return strpos($pgName, $prefix) === 0 ? substr($pgName, strlen($prefix)) : null;
	}

	/**
	 * Get a statement that records an index's MySQL name when its PostgreSQL name had to be shortened
	 *
	 * @param string $table
	 * @param string $index Index name without table prefix
	 * @return string Blank when the name was not shortened
	 *
	 */
	protected function indexCommentSql($table, $index) {
		$name = $this->indexName($table, $index);
		if($name === $table . self::indexSeparator . $index) return '';
		return 'COMMENT ON INDEX ' . $this->quoteId($name) . ' IS ' . "'" . self::indexCommentPrefix . str_replace("'", "''", $index) . "'";
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
				$open = $k > -1 && $this->isWord($tokens[$k], 'AGAINST') ? $this->next($tokens, $k + 1) : -1;
				if($open > -1 && $tokens[$open][0] === 'punct' && $tokens[$open][1] === '(') {
					if(!$this->fulltextAvailable) {
						throw new \PDOException(
							'PostgreSQL translator: MATCH ... AGAINST (fulltext search) needs the pw_search text search functions, which are not set up. ' .
							'Use $database->dialect()->supportsFulltext() to detect this and use LIKE or REGEXP instead.'
						);
					}
					$close = $this->matchParen($tokens, $open);
					$condition = $this->matchIsCondition($tokens, $i, $close);
					$p = count($out) - 1;
					while($p >= 0 && $out[$p][0] === 'ws') $p--;
					if(!$condition && $p >= 0 && $this->isWord($out[$p], 'NOT')) {
						// NOT MATCH as a value (a negated operator's score): MySQL's NOT of a relevance is 0 or 1
						$out = array_slice($out, 0, $p);
						$out[] = ['word', '(NOT ' . $this->matchAgainst($tokens, $j, $open, $close, true) . ')::int'];
					} else {
						$out[] = ['word', $this->matchAgainst($tokens, $j, $open, $close, $condition)];
					}
					$i = $close;
					continue;
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
	 * Translate MATCH(cols) AGAINST(expr [mode]) to full text search
	 *
	 * As a condition it is `(pw_tsvector(col) @@ pw_tsquery(expr, boolean))`, which a FULLTEXT key's tsvector
	 * index serves; as a value (a score in SELECT, a comparison, arithmetic) it is the relevance, `ts_rank(...)`.
	 * IN BOOLEAN MODE reads MySQL's boolean syntax (see tsqueryFunctionSql()); natural language mode and
	 * WITH QUERY EXPANSION match any of the words.
	 *
	 * @param array $tokens
	 * @param int $matchOpen Index of the paren after MATCH
	 * @param int $open Index of the paren after AGAINST
	 * @param int $close Index of its closing paren
	 * @param bool $condition Used as a condition (see matchIsCondition())?
	 * @return string
	 *
	 */
	protected function matchAgainst(array $tokens, $matchOpen, $open, $close, $condition) {
		$docs = [];
		foreach($this->callArgTokens($tokens, $matchOpen) as $col) {
			$docs[] = 'pw_tsvector(' . $this->join($this->expressions($this->trimTokens($col))) . ')';
		}
		$doc = count($docs) > 1 ? '(' . implode(' || ', $docs) . ')' : $docs[0];

		$against = array_slice($tokens, $open + 1, $close - $open - 1);
		$boolean = false;
		$depth = 0;
		foreach($against as $n => $t) {
			if($t[0] === 'punct' && $t[1] === '(') $depth++;
			if($t[0] === 'punct' && $t[1] === ')') $depth--;
			if($depth === 0 && $this->isWord($t, ['IN', 'WITH'])) {
				$boolean = stripos($this->join(array_slice($against, $n)), 'BOOLEAN') !== false;
				$against = array_slice($against, 0, $n);
				break;
			}
		}
		$query = 'pw_tsquery(' . $this->join($this->expressions($this->trimTokens($against))) . ', ' . ($boolean ? 'true' : 'false') . ')';

		return $condition ? "($doc @@ $query)" : "ts_rank($doc, $query)";
	}

	/**
	 * Is the MATCH ... AGAINST from $i to $close used as a condition (rather than as a value)?
	 *
	 * MySQL's MATCH is a relevance number that is also true when nonzero; PostgreSQL needs one or the other.
	 *
	 * @param array $tokens
	 * @param int $i Index of MATCH
	 * @param int $close Index of the paren closing AGAINST
	 * @return bool
	 *
	 */
	protected function matchIsCondition(array $tokens, $i, $close) {
		$k = $this->next($tokens, $close + 1);
		if($k > -1) {
			$t = $tokens[$k];
			if($t[0] === 'punct' && in_array($t[1], ['+', '-', '*', '/', '=', '<', '>', '<=', '>=', '<>', '!='], true)) return false;
			if($this->isWord($t, ['AS', 'ASC', 'DESC'])) return false;
		}
		for($p = $this->prev($tokens, $i - 1); $p > -1; $p = $this->prev($tokens, $p - 1)) {
			$t = $tokens[$p];
			if($t[0] === 'punct' && $t[1] === '(') continue; // look past grouping parens
			if($this->isWord($t, 'NOT')) continue; // NOT is a condition or a value as what precedes it is
			return $this->isWord($t, ['WHERE', 'AND', 'OR', 'ON', 'HAVING', 'WHEN']);
		}
		return false;
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
	 * JSON_CONTAINS(col, candidate[, '$.key.path']) used as a condition on a jsonb column: indexed, then exact
	 *
	 * pw_json_contains() gives MySQL's result, but as a function call no index can serve it. jsonb's `@>` is
	 * stricter than MySQL (which also finds a candidate inside the elements of an array at any level, i.e.
	 * {"a":1} in {"a":[1,2]}, [1,2] in [[1,2],[3,4]]), so it cannot replace it. Instead the GIN index (see
	 * jsonIndexSql()) narrows the rows with a lax jsonpath of the candidate's values (pw_json_contains_path(),
	 * which ignores one level of arrays at each step), the partial index adds documents with nested arrays
	 * (pw_json_nested()), and pw_json_contains() decides on those rows. A key path becomes a nested object
	 * around the candidate for the index. Not used after NOT, since MySQL's function returns NULL for a missing
	 * path, which NOT keeps.
	 *
	 * Runs on the MySQL tokens, before expressions(), so that the call is still recognizable.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function jsonContainment(array $tokens) {
		$aliases = null;
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			if(!$this->isWord($tokens[$i], 'JSON_CONTAINS')) continue;
			$open = $this->next($tokens, $i + 1);
			if($open < 0 || $tokens[$open][1] !== '(') continue;
			$close = $this->matchParen($tokens, $open);
			if($close < 0) continue;
			// a condition: after WHERE/AND/OR/ON/HAVING/NOT/(, and not followed by an operator
			$p = $this->prev($tokens, $i - 1);
			$q = $this->next($tokens, $close + 1);
			$before = $p < 0 ? false : ($this->isWord($tokens[$p], ['WHERE', 'AND', 'OR', 'ON', 'HAVING']) || ($tokens[$p][0] === 'punct' && $tokens[$p][1] === '('));
			for($b = $p; $before && $b > -1 && $tokens[$b][0] === 'punct' && $tokens[$b][1] === '('; $b = $this->prev($tokens, $b - 1)) {
				$w = $this->prev($tokens, $b - 1);
				if($w > -1 && $this->isWord($tokens[$w], 'NOT')) $before = false;
			}
			$after = $q < 0 || $this->isWord($tokens[$q], ['AND', 'OR', 'ORDER', 'GROUP', 'LIMIT', 'HAVING', 'UNION']) || ($tokens[$q][0] === 'punct' && in_array($tokens[$q][1], [')', ';'], true));
			if(!$before || !$after) continue;
			$args = $this->callArgTokens($tokens, $open);
			if(count($args) < 2 || count($args) > 3) continue;
			// the document must be a jsonb column
			$col = $args[0];
			$table = null;
			$column = '';
			if($aliases === null) {
				$aliases = $this->tableAliases($tokens);
				$tables = array_values(array_unique(array_values($aliases)));
			}
			if(count($col) === 3 && $col[1][0] === 'punct' && $col[1][1] === '.' && isset($aliases[$this->name($col[0])])) {
				$table = $aliases[$this->name($col[0])];
				$column = $this->name($col[2]);
			} else if(count($col) === 1 && in_array($col[0][0], ['word', 'id']) && count($tables) === 1) {
				$table = $tables[0];
				$column = $this->name($col[0]);
			}
			if($table === null) continue;
			$schema = $this->tableSchema($table);
			if(!isset($schema['columns'][$column]) || $schema['columns'][$column] !== 'jsonb') continue;
			// an optional literal path of object keys only
			$keys = [];
			if(count($args) === 3) {
				if(count($args[2]) !== 1 || $args[2][0][0] !== 'str') continue;
				$path = str_replace("''", "'", substr($args[2][0][1], 1, -1));
				if(!preg_match('/^\$((?:\.(?:[A-Za-z_$][A-Za-z0-9_$]*|"(?:[^"\\\\]|\\\\.)*"))*)$/', $path, $m)) continue;
				preg_match_all('/\.(?:([A-Za-z_$][A-Za-z0-9_$]*)|"((?:[^"\\\\]|\\\\.)*)")/', $m[1], $parts, PREG_SET_ORDER);
				foreach($parts as $part) $keys[] = isset($part[2]) && $part[2] !== '' ? stripcslashes($part[2]) : $part[1];
			}
			$doc = trim($this->join($this->expressions($col)));
			$candidate = 'pw_json(' . trim($this->join($this->expressions($this->trimTokens($args[1])))) . ')';
			$wrapped = $candidate;
			foreach(array_reverse($keys) as $key) $wrapped = "jsonb_build_object('" . str_replace("'", "''", $key) . "', $wrapped)";
			$exact = "pw_json_contains($doc, $candidate" . (count($args) === 3 ? ', ' . $args[2][0][1] : '') . ')';
			$sql = "(($doc @@ pw_json_contains_path($wrapped) OR pw_json_nested($doc)) AND $exact = 1)";
			$tokens = array_merge(array_slice($tokens, 0, $i), [['word', $sql]], array_slice($tokens, $close + 1));
			$n = count($tokens);
		}
		return $tokens;
	}

	/**
	 * Get the statements for the indexes of a jsonb column, which serve JSON_CONTAINS() (see jsonContainment())
	 *
	 * A GIN index, and a partial index of the (usually few) documents with nested arrays, which the GIN index
	 * lookup does not find. MySQL cannot index a JSON column, so ProcessWire and modules never ask for one.
	 *
	 * @param string $table
	 * @param string $column
	 * @return array
	 *
	 */
	protected function jsonIndexSql($table, $column) {
		$statements = self::jsonIndexStatements('', $table, $column, false);
		foreach([self::jsonIndexSuffix, self::jsonNestedIndexSuffix] as $suffix) {
			$comment = $this->indexCommentSql($table, $column . $suffix);
			if($comment !== '') $statements[] = $comment;
		}
		return $statements;
	}

	/**
	 * Get the statements for the indexes of a jsonb column (see jsonIndexSql())
	 *
	 * @param string $schema Schema of the table, or blank for unqualified
	 * @param string $table
	 * @param string $column
	 * @param bool $existing For a table with rows: CONCURRENTLY and IF NOT EXISTS, and only the partial index
	 * @return array
	 *
	 */
	public static function jsonIndexStatements($schema, $table, $column, $existing) {
		$q = function($id) { return '"' . str_replace('"', '""', $id) . '"'; };
		$on = ($schema !== '' ? $q($schema) . '.' : '') . $q($table);
		$create = 'CREATE INDEX ' . ($existing ? 'CONCURRENTLY IF NOT EXISTS ' : '');
		$statements = [];
		if(!$existing) $statements[] = $create . $q(self::pgIndexName($table, $column . self::jsonIndexSuffix)) . " ON $on USING gin (" . $q($column) . ' jsonb_path_ops)';
		$statements[] = $create . $q(self::pgIndexName($table, $column . self::jsonNestedIndexSuffix)) . " ON $on ((1)) WHERE pw_json_nested(" . $q($column) . ')';
		return $statements;
	}

	/**
	 * Compare JSON_EXTRACT() results with SQL values as MySQL does: a string (or bound value) is a JSON string
	 *
	 * `JSON_EXTRACT(data, '$.color') = :value` with 'green' matches {"color":"green"} in MySQL, which converts
	 * the string. PostgreSQL would parse it as JSON (and reject it). Numbers compare as JSON numbers either way.
	 *
	 * @param array $tokens After expressions()
	 * @return array
	 *
	 */
	protected function jsonComparisons(array $tokens) {
		$ops = ['=', '!=', '<>', '<', '>', '<=', '>=', '<=>'];
		$n = count($tokens);
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] !== 'word' || strpos($t[1], 'pw_json_extract(') !== 0) continue;
			foreach([1, -1] as $dir) {
				$o = $dir > 0 ? $this->next($tokens, $i + 1) : $this->prev($tokens, $i - 1);
				if($o < 0 || $tokens[$o][0] !== 'punct' || !in_array($tokens[$o][1], $ops, true)) continue;
				$v = $dir > 0 ? $this->next($tokens, $o + 1) : $this->prev($tokens, $o - 1);
				if($v < 0 || !in_array($tokens[$v][0], ['param', 'str'], true)) continue;
				if($dir > 0) {
					$c = $this->next($tokens, $v + 1);
					if($c > -1 && $tokens[$c][0] === 'punct' && $tokens[$c][1] === '::') continue; // already typed
				}
				$tokens[$v] = ['word', 'pw_json_value((' . $tokens[$v][1] . ')::text)'];
			}
		}
		return $tokens;
	}

	/**
	 * Translate a MySQL JSON function call to the pw_json_*() functions (see setupJson())
	 *
	 * Documents are passed through pw_json(), which takes jsonb or text, and values through
	 * pw_json_value(), which makes SQL strings JSON strings and keeps JSON (i.e. from JSON_EXTRACT()) as
	 * JSON, as MySQL does. Paths use MySQL's syntax, which pw_json_path() maps to jsonpath.
	 *
	 * @param string $name Uppercase function name
	 * @param array $args Translated arguments
	 * @return string|null Null to leave the call alone (unsupported number of arguments)
	 *
	 */
	protected function jsonCall($name, array $args) {
		$qty = count($args);
		$doc = function($x) { return "pw_json($x)"; }; // a document as jsonb: jsonb as is, text parsed (invalid gives NULL)
		$value = function($x) { return "pw_json_value($x)"; }; // an SQL value as JSON: strings become JSON strings
		switch($name) {
			case 'JSON_EXTRACT':
				return $qty === 2 ? 'pw_json_extract(' . $doc($args[0]) . ", $args[1])" : null;
			case 'JSON_UNQUOTE':
				return $qty === 1 ? "pw_json_unquote($args[0])" : null;
			case 'JSON_CONTAINS':
				if($qty === 2) return 'pw_json_contains(' . $doc($args[0]) . ', ' . $doc($args[1]) . ')';
				if($qty === 3) return 'pw_json_contains(' . $doc($args[0]) . ', ' . $doc($args[1]) . ", $args[2])";
				return null;
			case 'JSON_LENGTH':
				if($qty === 1) return 'pw_json_length(' . $doc($args[0]) . ')';
				if($qty === 2) return 'pw_json_length(' . $doc($args[0]) . ", $args[1])";
				return null;
			case 'JSON_SET':
			case 'JSON_INSERT':
			case 'JSON_REPLACE':
				// (doc, path, value[, path, value ...]): applied pair by pair, as MySQL does
				if($qty < 3 || $qty % 2 === 0) return null;
				$fn = 'pw_json_' . strtolower(substr($name, 5));
				$sql = $args[0];
				for($x = 1; $x < $qty; $x += 2) $sql = "$fn(" . $doc($sql) . ", {$args[$x]}, " . $value($args[$x + 1]) . ')';
				return $sql;
			case 'JSON_REMOVE':
				if($qty < 2) return null;
				$sql = $args[0];
				for($x = 1; $x < $qty; $x++) $sql = 'pw_json_remove(' . $doc($sql) . ", {$args[$x]})";
				return $sql;
			case 'JSON_ARRAY':
				return 'jsonb_build_array(' . implode(', ', array_map($value, $args)) . ')';
			case 'JSON_OBJECT':
				if($qty % 2 !== 0) return null;
				$parts = [];
				for($x = 0; $x < $qty; $x += 2) $parts[] = "({$args[$x]})::text, " . $value($args[$x + 1]);
				return 'jsonb_build_object(' . implode(', ', $parts) . ')';
			case 'JSON_QUOTE':
				return $qty === 1 ? "to_jsonb(($args[0])::text)::text" : null;
			case 'JSON_VALID':
				return $qty === 1 ? "pw_json_valid($args[0])" : null;
		}
		return null;
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
			case 'JSON_EXTRACT':
			case 'JSON_UNQUOTE':
			case 'JSON_CONTAINS':
			case 'JSON_LENGTH':
			case 'JSON_SET':
			case 'JSON_INSERT':
			case 'JSON_REPLACE':
			case 'JSON_REMOVE':
			case 'JSON_ARRAY':
			case 'JSON_OBJECT':
			case 'JSON_QUOTE':
			case 'JSON_VALID':
				return $this->jsonAvailable ? $this->jsonCall($name, $args) : null;
			case 'LOWER':
			case 'UPPER':
				// MySQL applies them to JSON as text (i.e. JSON_UNQUOTE(LOWER(JSON_EXTRACT(...)))); jsonb needs the cast
				if($qty === 1 && preg_match('/^(pw_json_\w+|jsonb_build_\w+)\(/', $args[0])) return strtolower($name) . "(($args[0])::text)";
				return null;
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
		$simpleWhens = $this->simpleCaseWhens($tokens);
		$i = 0;
		while($i < $n) {
			$t = $tokens[$i];
			if($t[0] === 'punct' && $t[1] === '(' && $this->startsSelect($tokens, $i)) {
				// a subquery has conditions of its own
				$end = $this->matchParen($tokens, $i);
				if($end > $i) {
					$out[] = $t;
					foreach($this->booleanContext(array_slice($tokens, $i + 1, $end - $i - 1)) as $tc) $out[] = $tc;
					$out[] = $tokens[$end];
					$i = $end + 1;
					continue;
				}
			}
			if(isset($simpleWhens[$i])) {
				// CASE x WHEN value: a value, not a condition
				$out[] = $t;
				$i++;
				continue;
			}
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
	 * Is the paren at $i the start of a subquery: ( SELECT ...
	 *
	 * @param array $tokens
	 * @param int $i
	 * @return bool
	 *
	 */
	protected function startsSelect(array $tokens, $i) {
		$j = $this->next($tokens, $i + 1);
		return $j > -1 && $this->isWord($tokens[$j], 'SELECT');
	}

	/**
	 * Indexes of the WHEN tokens that belong to a simple CASE (CASE x WHEN value ...)
	 *
	 * @param array $tokens
	 * @return array [ index => true ]
	 *
	 */
	protected function simpleCaseWhens(array $tokens) {
		$whens = [];
		$stack = []; // true for a simple CASE
		foreach($tokens as $x => $t) {
			if($t[0] !== 'word') continue;
			$w = strtoupper($t[1]);
			if($w === 'CASE') {
				$y = $this->next($tokens, $x + 1);
				$stack[] = !($y > -1 && $this->isWord($tokens[$y], 'WHEN'));
			} else if($w === 'END' && count($stack)) {
				array_pop($stack);
			} else if($w === 'WHEN' && count($stack) && end($stack)) {
				$whens[$x] = true;
			}
		}
		return $whens;
	}

	/**
	 * Apply booleanContext() inside each subquery of a condition: ( SELECT ... )
	 *
	 * @param array $seg
	 * @return array
	 *
	 */
	protected function conditionSubqueries(array $seg) {
		$out = [];
		$n = count($seg);
		for($x = 0; $x < $n; $x++) {
			$t = $seg[$x];
			if($t[0] === 'punct' && $t[1] === '(' && $this->startsSelect($seg, $x)) {
				$end = $this->matchParen($seg, $x);
				if($end > $x) {
					$out[] = $t;
					foreach($this->booleanContext(array_slice($seg, $x + 1, $end - $x - 1)) as $tc) $out[] = $tc;
					$out[] = $seg[$end];
					$x = $end;
					continue;
				}
			}
			$out[] = $t;
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
		$between = false; // inside "x BETWEEN a AND b", before its AND
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
			if($depth !== 0 || $t[0] !== 'word') continue;
			$w = strtoupper($t[1]);
			if($w === 'BETWEEN') {
				$between = true;
			} else if($w === 'AND' && $between) {
				$between = false; // the AND of BETWEEN is part of the condition
			} else if(in_array($w, $stops, true)) {
				return $j;
			}
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

		if($n === 1 && $seg[0][0] === 'word' && preg_match('/^pw_json_(contains|valid)\(/', $seg[0][1])) {
			// JSON_CONTAINS() and JSON_VALID() return 1/0 in MySQL, and are used as conditions
			return array_merge($lead, [['punct', '('], $seg[0], ['punct', ')'], ['ws', ' '], ['punct', '<>'], ['ws', ' '], ['num', '0']], $trail);
		}

		if($n > 1 && $this->isWord($seg[0], 'NOT')) {
			// NOT <condition>, i.e. NOT (status & 1024)
			return array_merge($lead, [$seg[0]], $this->condition(array_slice($seg, 1)), $trail);
		}

		$seg = $this->conditionSubqueries($seg);
		$n = count($seg);

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
			// balanced paren tokens, so that later passes still see the statement's depth correctly
			return array_merge($lead, [['punct', '(']], $seg, [['punct', ')'], ['ws', ' '], ['punct', '<>'], ['ws', ' '], ['num', '0']], $trail);
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
		$tokens = $this->anyValueGroupBy($tokens);
		$tokens = $this->storedVectors($tokens);
		$tokens = $this->selectStar($tokens);
		return $this->foldOrderBy($tokens);
	}

	/**
	 * Sort text columns by their folded value, as MySQL's case- and accent-insensitive collations sort
	 *
	 * `ORDER BY t.data` becomes `ORDER BY pw_fold(t.data)`, which a btree index on the folded value can serve.
	 * Terms that are a text column, or any_value() of one, are folded;
	 * other expressions are left alone. SELECT DISTINCT is left alone, since PostgreSQL requires its
	 * ORDER BY terms to appear in the select list.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function foldOrderBy(array $tokens) {
		if(!$this->foldAvailable) return $tokens;
		$orderPos = -1;
		$depth = 0;
		$n = count($tokens);
		$first = true;
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0 || $t[0] !== 'word') continue;
			if($this->isWord($t, 'SELECT') && $first) {
				$first = false;
				$j = $this->next($tokens, $i + 1);
				if($j > -1 && $this->isWord($tokens[$j], 'DISTINCT')) return $tokens;
			} else if($this->isWord($t, 'ORDER')) {
				$j = $this->next($tokens, $i + 1);
				if($j > -1 && $this->isWord($tokens[$j], 'BY')) $orderPos = $j + 1;
			}
		}
		if($orderPos < 0) return $tokens;
		$aliases = $this->tableAliases($tokens);
		if(!count($aliases)) return $tokens;
		$tables = array_values(array_unique(array_values($aliases)));
		$single = count($tables) === 1 ? $tables[0] : null;
		$orderEnd = $this->clauseEnd($tokens, $orderPos);
		$terms = [];
		foreach($this->splitCommas(array_slice($tokens, $orderPos, $orderEnd - $orderPos)) as $part) {
			$lead = [];
			$trail = [];
			while(count($part) && $part[0][0] === 'ws') $lead[] = array_shift($part);
			while(count($part) && $part[count($part) - 1][0] === 'ws') array_unshift($trail, array_pop($part));
			$suffix = [];
			if(count($part) && $this->isWord($part[count($part) - 1], ['ASC', 'DESC'])) {
				$suffix = [['ws', ' '], array_pop($part)];
				$part = $this->trimTokens($part);
			}
			$sql = trim($this->join($part));
			$ref = $sql;
			if(preg_match('/^any_value\((.+)\)$/i', $sql, $m)) $ref = $m[1];
			$table = null;
			$column = '';
			if(preg_match('/^("?)([A-Za-z0-9_]+)\1\.("?)([A-Za-z0-9_]+)\3$/', $ref, $m)) {
				if(isset($aliases[$m[2]])) {
					$table = $aliases[$m[2]];
					$column = $m[4];
				}
			} else if($single !== null && preg_match('/^("?)([A-Za-z0-9_]+)\1$/', $ref, $m)) {
				$table = $single;
				$column = $m[2];
			}
			if($table !== null) {
				$schema = $this->tableSchema($table);
				if(isset($schema['columns'][$column]) && $this->typeClass($schema['columns'][$column]) === 'text') {
					$part = [['word', "pw_fold($sql)"]];
				}
			}
			$terms[] = array_merge($lead, $part, $suffix, $trail);
		}
		$rebuilt = [];
		foreach($terms as $x => $term) {
			if($x) $rebuilt[] = ['punct', ','];
			foreach($term as $t) $rebuilt[] = $t;
		}
		return array_merge(array_slice($tokens, 0, $orderPos), $rebuilt, array_slice($tokens, $orderEnd));
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
	 * Set whether the pw_fold() and pw_unaccent() functions exist (see WireDatabaseDialectPgsql::foldFunctionsSql())
	 *
	 * When they do, text columns are compared, searched, sorted and indexed on their folded value
	 * (lowercase, without accents), as with MySQL's default case- and accent-insensitive collations.
	 *
	 * @param bool $available
	 *
	 */
	public function setFoldAvailable($available) {
		$this->foldAvailable = (bool) $available;
		$this->cache = [];
	}

	/**
	 * Are text comparisons folded?
	 *
	 * @return bool
	 *
	 */
	public function foldAvailable() {
		return $this->foldAvailable;
	}

	/**
	 * Name of the function whose existence says that the current pw_json_*() functions are installed
	 *
	 * Bumped when the functions change, so that existing sites get the new versions on their next connection.
	 *
	 */
	const jsonVersionFunction = 'pw_json_v2';

	/**
	 * Create the pw_json_*() functions, MySQL-compatible versions of MySQL's JSON functions over jsonb
	 *
	 * MySQL's JSON_EXTRACT(), JSON_UNQUOTE(), JSON_CONTAINS(), JSON_LENGTH(), JSON_SET(), JSON_INSERT(),
	 * JSON_REPLACE(), JSON_REMOVE() and JSON_VALID() are translated to these (see jsonCall()), and
	 * JSON_ARRAY(), JSON_OBJECT() and JSON_QUOTE() to PostgreSQL's own functions. Documents may be jsonb
	 * or text (pw_json() parses text, and invalid JSON gives NULL, as the SQLite versions do), paths use
	 * MySQL's syntax (pw_json_path() maps it to a strict jsonpath, so that arrays are not unwrapped), and
	 * results follow MySQL's, as checked against the MySQL 8 results in WireDatabaseSQLiteTranslator.test.php.
	 * Calls between the functions are schema-qualified, so that they do not depend on search_path.
	 *
	 * Used on connect and by the installer, so it takes callables rather than a connection.
	 *
	 * @param callable $exec function(string $sql): executes a statement (throws on error)
	 * @param callable $fetchColumn function(string $sql): returns the first column of the first row
	 * @return array [ 'json' => bool, 'error' => string ]
	 *
	 */
	public static function setupJson(callable $exec, callable $fetchColumn, $fetchAll = null) {
		$errors = [];
		try {
			$schema = (string) $fetchColumn('SELECT current_schema()');
			$functions = self::jsonFunctionsSql($schema);
			$marker = array_pop($functions);
			foreach($functions as $sql) $exec($sql);
			if(is_callable($fetchAll)) {
				// jsonb columns made before the partial index of nested documents existed get it (see jsonContainment())
				$literal = str_replace("'", "''", $schema);
				$rows = $fetchAll(
					"SELECT c.relname AS tablename, a.attname AS columnname FROM pg_attribute a " .
					"JOIN pg_class c ON c.oid = a.attrelid JOIN pg_namespace n ON n.oid = c.relnamespace " .
					"WHERE n.nspname = '$literal' AND c.relkind = 'r' AND a.atttypid = 'jsonb'::regtype AND a.attnum > 0 AND NOT a.attisdropped"
				);
				foreach($rows as $row) {
					try {
						foreach(self::jsonIndexStatements($schema, $row['tablename'], $row['columnname'], true) as $sql) $exec($sql);
					} catch(\Exception $e) {
						$errors[] = $e->getMessage();
					}
				}
			}
			$exec($marker);
		} catch(\Exception $e) {
			return ['json' => false, 'error' => 'JSON functions could not be created, so MySQL JSON functions are unavailable: ' . $e->getMessage()];
		}
		$error = count($errors) ? 'JSON functions are set up, but some jsonb columns have no index of nested documents: ' . implode('; ', $errors) : '';
		return ['json' => true, 'error' => $error];
	}

	/**
	 * Get the CREATE FUNCTION statements for the pw_json_*() functions
	 *
	 * @param string $schema Schema the functions are created in (for the calls between them)
	 * @return array
	 *
	 */
	public static function jsonFunctionsSql($schema) {
		$s = '"' . str_replace('"', '""', $schema) . '".';
		$immutable = 'LANGUAGE sql IMMUTABLE PARALLEL SAFE';
		$stable = 'LANGUAGE sql STABLE PARALLEL SAFE'; // pg_input_is_valid() is stable
		$fn = function($signature, $returns, $options, $body) use($s) {
			return "CREATE OR REPLACE FUNCTION {$s}$signature RETURNS $returns $options AS \$fn\$ $body \$fn\$";
		};
		// path elements of a MySQL path ($.key, $."quoted key", $[0]), for jsonb_set() and #-
		$pathElements = "'\\.(?:\"((?:[^\"\\\\]|\\\\.)*)\"|([A-Za-z_\$][A-Za-z0-9_\$]*))|\\[([0-9]+)\\]'";
		return [
			// documents and values
			// (plpgsql, so that it is not inlined: the planner would fold a constant's cast before the validity check)
			$fn('pw_json(text)', 'jsonb', 'LANGUAGE plpgsql STABLE PARALLEL SAFE',
				"BEGIN IF pg_input_is_valid(\$1, 'jsonb') THEN RETURN \$1::jsonb; END IF; RETURN NULL; END"),
			$fn('pw_json(jsonb)', 'jsonb', "$immutable", 'SELECT $1'),
			$fn('pw_json_value(text)', 'jsonb', "$immutable", "SELECT COALESCE(to_jsonb(\$1), 'null'::jsonb)"),
			$fn('pw_json_value(numeric)', 'jsonb', "$immutable", "SELECT COALESCE(to_jsonb(\$1), 'null'::jsonb)"),
			$fn('pw_json_value(boolean)', 'jsonb', "$immutable", "SELECT COALESCE(to_jsonb(\$1), 'null'::jsonb)"),
			$fn('pw_json_value(jsonb)', 'jsonb', "$immutable", "SELECT COALESCE(\$1, 'null'::jsonb)"),
			// paths: MySQL's $**.x is jsonpath's $.**.x; strict, so that $.a.b does not unwrap an array a
			$fn('pw_json_path(text)', 'jsonpath', "$immutable STRICT", "SELECT ('strict ' || regexp_replace(\$1, '^\\\$\\*\\*', '\$.**'))::jsonpath"),
			$fn('pw_json_path_array(text)', 'text[]', "$immutable STRICT",
				"SELECT array_agg(COALESCE(m[1], m[2], m[3]) ORDER BY o) FROM regexp_matches(\$1, $pathElements, 'g') WITH ORDINALITY AS r(m, o)"),
			// JSON_EXTRACT: one value, or an array of all matches for a wildcard path (NULL when none)
			$fn('pw_json_extract(jsonb, text)', 'jsonb', "$immutable STRICT",
				"SELECT CASE WHEN position('*' in \$2) > 0 THEN NULLIF(jsonb_path_query_array(\$1, {$s}pw_json_path(\$2), '{}', true), '[]'::jsonb) " .
				"ELSE jsonb_path_query_first(\$1, {$s}pw_json_path(\$2), '{}', true) END"),
			$fn('pw_json_unquote(jsonb)', 'text', "$immutable STRICT", "SELECT CASE WHEN jsonb_typeof(\$1) = 'string' THEN \$1 #>> '{}' ELSE \$1::text END"),
			$fn('pw_json_unquote(text)', 'text', 'LANGUAGE plpgsql STABLE PARALLEL SAFE STRICT',
				"BEGIN IF \$1 ~ '^\\s*\"' AND pg_input_is_valid(\$1, 'jsonb') THEN RETURN \$1::jsonb #>> '{}'; END IF; RETURN \$1; END"),
			$fn('pw_json_length(jsonb)', 'integer', "$immutable STRICT",
				"SELECT CASE jsonb_typeof(\$1) WHEN 'array' THEN jsonb_array_length(\$1) WHEN 'object' THEN (SELECT count(*)::integer FROM jsonb_object_keys(\$1)) ELSE 1 END"),
			$fn('pw_json_length(jsonb, text)', 'integer', "$immutable STRICT", "SELECT {$s}pw_json_length({$s}pw_json_extract(\$1, \$2))"),
			// JSON_CONTAINS, with MySQL's rules: a candidate array is contained in a target array when each of its elements
			// is contained in some element of it; any other candidate in a target array when it is contained in some element;
			// an object when each key is there with a value containing the candidate's; a scalar when equal
			$fn('pw_json_contains(jsonb, jsonb)', 'integer', 'LANGUAGE plpgsql IMMUTABLE STRICT PARALLEL SAFE',
				"BEGIN RETURN (CASE " .
				"WHEN jsonb_typeof(\$2) = 'array' THEN jsonb_typeof(\$1) = 'array' AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(\$2) c " .
					"WHERE NOT EXISTS (SELECT 1 FROM jsonb_array_elements(\$1) t WHERE {$s}pw_json_contains(t, c) = 1)) " .
				"WHEN jsonb_typeof(\$1) = 'array' THEN EXISTS (SELECT 1 FROM jsonb_array_elements(\$1) t WHERE {$s}pw_json_contains(t, \$2) = 1) " .
				"WHEN jsonb_typeof(\$2) = 'object' THEN jsonb_typeof(\$1) = 'object' AND NOT EXISTS (SELECT 1 FROM jsonb_each(\$2) c " .
					"WHERE NOT (\$1 ? c.key AND {$s}pw_json_contains(\$1 -> c.key, c.value) = 1)) " .
				"ELSE jsonb_typeof(\$1) NOT IN ('array', 'object') AND \$1 = \$2 END)::integer; END"),
			// for the GIN index: the candidate's scalar values at their key paths, as a lax jsonpath (arrays at any step are
			// searched), which every document containing the candidate matches, unless it has arrays within arrays
			$fn('pw_json_contains_path(jsonb)', 'jsonpath', 'LANGUAGE plpgsql IMMUTABLE STRICT PARALLEL SAFE',
				"DECLARE clauses text; BEGIN " .
				"WITH RECURSIVE leaves(path, v) AS (SELECT '\$'::text, \$1 UNION ALL " .
					"SELECT CASE WHEN e.key IS NULL THEN l.path ELSE l.path || '.' || to_jsonb(e.key)::text END, e.value FROM leaves l " .
					"CROSS JOIN LATERAL (SELECT key, value FROM jsonb_each(CASE WHEN jsonb_typeof(l.v) = 'object' THEN l.v END) " .
					"UNION ALL SELECT NULL, value FROM jsonb_array_elements(CASE WHEN jsonb_typeof(l.v) = 'array' THEN l.v END)) e) " .
				"SELECT string_agg(DISTINCT path || ' == ' || v::text, ' && ') INTO clauses FROM leaves WHERE jsonb_typeof(v) NOT IN ('object', 'array'); " .
				"RETURN COALESCE(clauses, 'exists(\$)')::jsonpath; END"),
			$fn('pw_json_nested(jsonb)', 'boolean', "$immutable STRICT",
				"SELECT jsonb_path_exists(\$1, 'strict \$.** ? (@.type() == \"array\") [*] ? (@.type() == \"array\")')"),
			$fn('pw_json_contains(jsonb, jsonb, text)', 'integer', "$immutable STRICT", "SELECT {$s}pw_json_contains({$s}pw_json_extract(\$1, \$3), \$2)"),
			// JSON_SET/INSERT/REPLACE/REMOVE: '$' alone is the whole document
			$fn('pw_json_set(jsonb, text, jsonb)', 'jsonb', "$immutable STRICT",
				"SELECT CASE WHEN p IS NULL THEN \$3 ELSE jsonb_set(\$1, p, \$3, true) END FROM (SELECT {$s}pw_json_path_array(\$2) AS p) x"),
			$fn('pw_json_insert(jsonb, text, jsonb)', 'jsonb', "$immutable STRICT",
				"SELECT CASE WHEN p IS NULL OR \$1 #> p IS NOT NULL THEN \$1 ELSE jsonb_set(\$1, p, \$3, true) END FROM (SELECT {$s}pw_json_path_array(\$2) AS p) x"),
			$fn('pw_json_replace(jsonb, text, jsonb)', 'jsonb', "$immutable STRICT",
				"SELECT CASE WHEN p IS NULL THEN \$3 WHEN \$1 #> p IS NULL THEN \$1 ELSE jsonb_set(\$1, p, \$3, false) END FROM (SELECT {$s}pw_json_path_array(\$2) AS p) x"),
			$fn('pw_json_remove(jsonb, text)', 'jsonb', "$immutable STRICT",
				"SELECT CASE WHEN p IS NULL THEN NULL ELSE \$1 #- p END FROM (SELECT {$s}pw_json_path_array(\$2) AS p) x"),
			$fn('pw_json_valid(text)', 'integer', "$stable STRICT", "SELECT pg_input_is_valid(\$1, 'jsonb')::integer"),
			$fn('pw_json_valid(jsonb)', 'integer', "$immutable STRICT", 'SELECT 1'),
			// last, so that it exists only when all of the above do
			$fn(self::jsonVersionFunction . '()', 'integer', "$immutable", 'SELECT 1'),
		];
	}

	/**
	 * Set whether the pw_json_*() functions exist (see setupJson())
	 *
	 * @param bool $available
	 *
	 */
	public function setJsonAvailable($available) {
		$this->jsonAvailable = (bool) $available;
		$this->cache = [];
	}

	/**
	 * Are MySQL's JSON functions translated?
	 *
	 * @return bool
	 *
	 */
	public function jsonAvailable() {
		return $this->jsonAvailable;
	}

	/**
	 * Set whether the full text search functions exist (see setupFulltext())
	 *
	 * When they do, MATCH ... AGAINST translates to full text search and FULLTEXT keys also get a tsvector index.
	 *
	 * @param bool $available
	 *
	 */
	public function setFulltextAvailable($available) {
		$this->fulltextAvailable = (bool) $available;
		$this->cache = [];
	}

	/**
	 * Does MATCH ... AGAINST translate to full text search?
	 *
	 * @return bool
	 *
	 */
	public function fulltextAvailable() {
		return $this->fulltextAvailable;
	}

	/**
	 * Create the pw_search text search configuration and the pw_tsvector() and pw_tsquery() functions
	 *
	 * pw_search is the `simple` configuration (no stemming or stopwords, as MySQL's FULLTEXT) with unaccent
	 * before it when the unaccent extension is installed, so that searches ignore case and accents as MySQL's
	 * do. pw_tsvector(text) is the indexed document and pw_tsquery(text, boolean) reads a MySQL AGAINST
	 * value. FULLTEXT keys made before this was set up (trigram indexes only) get their tsvector index, built
	 * CONCURRENTLY so that writes continue; one that fails is logged and skipped. The marker function
	 * (see fulltextMarker) is created last and is how a connection tells that this version's setup
	 * completed. An advisory lock keeps two connections from setting up at once: the one that does not
	 * get it uses LIKE and REGEXP for that request.
	 *
	 * If the unaccent rules change, tsvector indexes are stale as pw_fold() indexes are: rebuild them with REINDEX.
	 *
	 * Used on connect and by the installer, so it takes callables rather than a connection.
	 *
	 * @param callable $exec function(string $sql): executes a statement (throws on error)
	 * @param callable $fetchColumn function(string $sql): returns the first column of the first row
	 * @param callable|null $fetchAll function(string $sql): returns all rows as associative arrays (to index existing FULLTEXT keys)
	 * @return array [ 'fulltext' => bool, 'error' => string ]
	 *
	 */
	public static function setupFulltext(callable $exec, callable $fetchColumn, $fetchAll = null) {
		$result = ['fulltext' => false, 'error' => ''];
		$isTrue = function($v) { return in_array($v, [true, 't', '1', 1], true); };
		// one connection sets up at a time; others use LIKE/REGEXP until it is done
		$lock = "hashtext('pw_fulltext_setup')";
		try {
			if(!$isTrue($fetchColumn("SELECT pg_try_advisory_lock($lock)"))) return $result;
		} catch(\Exception $e) {
			$result['error'] = 'full text search could not be set up, so fulltext operators use LIKE and REGEXP: ' . $e->getMessage();
			return $result;
		}
		$errors = [];
		try {
			$schema = (string) $fetchColumn('SELECT current_schema()');
			$unaccent = (string) $fetchColumn(
				"SELECT n.nspname FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'unaccent'"
			);
			foreach(self::fulltextSetupSql($schema, $unaccent) as $sql) $exec($sql);
			if(is_callable($fetchAll)) {
				$literal = str_replace("'", "''", $schema);
				$indexes = $fetchAll(
					"SELECT n.nspname AS schemaname, t.relname AS tablename, i.relname AS indexname, " .
					"pg_get_indexdef(i.oid) AS indexdef, ix.indisvalid AS valid FROM pg_index ix " .
					"JOIN pg_class i ON i.oid = ix.indexrelid JOIN pg_class t ON t.oid = ix.indrelid " .
					"JOIN pg_namespace n ON n.oid = t.relnamespace WHERE n.nspname = '$literal'"
				);
				// an index that cannot be built (i.e. a table owned by another role) is logged, and MATCH on it still works, unindexed
				foreach(self::fulltextIndexBackfillSql($indexes) as $group) {
					try {
						foreach($group as $sql) $exec($sql);
					} catch(\Exception $e) {
						$errors[] = $e->getMessage();
					}
				}
			}
			$exec(self::tsqueryFunctionSql($schema));
			$s = '"' . str_replace('"', '""', $schema) . '"';
			$exec("CREATE OR REPLACE FUNCTION $s." . self::fulltextMarker . "() RETURNS int LANGUAGE sql IMMUTABLE AS 'SELECT 1'");
			$result['fulltext'] = true;
			if(count($errors)) $result['error'] = 'full text search is set up, but some FULLTEXT keys have no tsvector index: ' . implode('; ', $errors);
		} catch(\Exception $e) {
			$result['error'] = 'full text search could not be set up, so fulltext operators use LIKE and REGEXP: ' . $e->getMessage();
		}
		try {
			$fetchColumn("SELECT pg_advisory_unlock($lock)");
		} catch(\Exception $e) {
			// the lock ends with the session
		}
		return $result;
	}

	/**
	 * Get the statements that create pw_search and pw_tsvector() (pw_tsquery() is tsqueryFunctionSql())
	 *
	 * pw_tsvector() replaces the characters that PostgreSQL's parser would otherwise join words with
	 * (host names, email addresses, paths, hyphenated words) by spaces, since MySQL splits words there;
	 * the / of a closing tag stays, so that the parser still skips markup.
	 *
	 * @param string $schema Schema to create them in
	 * @param string $unaccentSchema Schema of the unaccent extension, or blank when it is not installed
	 * @return array
	 *
	 */
	public static function fulltextSetupSql($schema, $unaccentSchema) {
		$s = '"' . str_replace('"', '""', $schema) . '"';
		$config = str_replace("'", "''", "$s.pw_search");
		$literal = str_replace("'", "''", $schema);
		$mapping = $unaccentSchema === '' ? 'simple' : '"' . str_replace('"', '""', $unaccentSchema) . '".unaccent, simple';
		return [
			"DO \$do\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_ts_config c JOIN pg_namespace n ON n.oid = c.cfgnamespace " .
				"WHERE c.cfgname = 'pw_search' AND n.nspname = '$literal') THEN " .
				"CREATE TEXT SEARCH CONFIGURATION $s.pw_search (COPY = pg_catalog.simple); END IF; END \$do\$",
			"ALTER TEXT SEARCH CONFIGURATION $s.pw_search ALTER MAPPING FOR asciiword, asciihword, hword_asciipart, word, hword, hword_part, numword, numhword, hword_numpart WITH $mapping",
			"CREATE OR REPLACE FUNCTION $s.pw_tsvector(text) RETURNS tsvector LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS " .
				"\$fn\$ SELECT to_tsvector('$config'::regconfig, regexp_replace(\$1, '[-.@:]+|(?<!<)/+', ' ', 'g')) \$fn\$",
		];
	}

	/**
	 * Get the statement that creates pw_tsquery(query, boolean_mode): a MySQL AGAINST value as a tsquery
	 *
	 * In boolean mode: +word is required, -word excluded, word* a prefix, "a phrase" a phrase (its @distance
	 * is ignored) and (...) a group, read the same way. Words without an operator are alternatives, unless there is a required word: then MySQL
	 * only uses them for relevance, so they are left out here. The weight operators > < ~ are ignored. In natural
	 * language mode, any of the words match. Words are split and folded as pw_tsvector() does. A query with no
	 * words is an empty tsquery, which matches nothing.
	 *
	 * @param string $schema
	 * @return string
	 *
	 */
	public static function tsqueryFunctionSql($schema) {
		$s = '"' . str_replace('"', '""', $schema) . '"';
		$config = str_replace("'", "''", "$s.pw_search");
		return <<<SQL
CREATE OR REPLACE FUNCTION $s.pw_tsquery(query text, boolean_mode boolean) RETURNS tsquery
LANGUAGE plpgsql IMMUTABLE STRICT PARALLEL SAFE AS \$fn\$
DECLARE
	cfg regconfig := '$config'::regconfig;
	n int := length(query);
	pos int := 1;
	c text; op text; term text; tq tsquery; e int; depth int;
	must tsquery; should tsquery; mustnot tsquery; result tsquery;
BEGIN
	WHILE pos <= n LOOP
		c := substr(query, pos, 1);
		IF c ~ '[[:space:])]' THEN pos := pos + 1; CONTINUE; END IF;
		op := '';
		WHILE pos <= n AND substr(query, pos, 1) IN ('+', '-', '~', '<', '>') LOOP
			IF op NOT IN ('+', '-') THEN op := substr(query, pos, 1); END IF;
			pos := pos + 1;
		END LOOP;
		IF pos > n THEN EXIT; END IF;
		c := substr(query, pos, 1);
		tq := NULL;
		IF c = '(' THEN
			-- a group: find its end and read it on its own
			e := pos; depth := 0;
			WHILE e <= n LOOP
				IF substr(query, e, 1) = '(' THEN depth := depth + 1;
				ELSIF substr(query, e, 1) = ')' THEN depth := depth - 1; EXIT WHEN depth = 0;
				END IF;
				e := e + 1;
			END LOOP;
			tq := $s.pw_tsquery(substr(query, pos + 1, e - pos - 1), boolean_mode);
			pos := e + 1;
		ELSIF c = '"' THEN
			e := strpos(substr(query, pos + 1), '"');
			IF e = 0 THEN e := n - pos + 1; END IF;
			tq := phraseto_tsquery(cfg, regexp_replace(substr(query, pos + 1, e - 1), '[-.@/:]+', ' ', 'g'));
			pos := pos + e + 1;
		ELSE
			term := substring(substr(query, pos) from '^[^[:space:]()"]+');
			-- an operator without a word (i.e. "+ word" or "+)") applies to nothing
			IF term IS NULL THEN pos := pos + 1; CONTINUE; END IF;
			pos := pos + length(term);
			-- a phrase's @distance is not a word
			CONTINUE WHEN term ~ '^@[0-9]+$';
			IF right(term, 1) = '*' THEN
				-- prefix: a term that splits into several words is a phrase of prefixes (to_tsquery's 'term':*)
				term := btrim(regexp_replace(term, '[-.@/:*]+', ' ', 'g'));
				IF term <> '' THEN
					tq := to_tsquery(cfg, '''' || replace(replace(term, '\\', '\\\\'), '''', '''''') || ''':*');
				END IF;
			ELSE
				tq := plainto_tsquery(cfg, regexp_replace(term, '[-.@/:]+', ' ', 'g'));
			END IF;
		END IF;
		CONTINUE WHEN tq IS NULL OR numnode(tq) = 0;
		IF NOT boolean_mode THEN op := ''; END IF;
		IF op = '+' THEN must := CASE WHEN must IS NULL THEN tq ELSE must && tq END;
		ELSIF op = '-' THEN mustnot := CASE WHEN mustnot IS NULL THEN tq ELSE mustnot || tq END;
		ELSE should := CASE WHEN should IS NULL THEN tq ELSE should || tq END;
		END IF;
	END LOOP;
	result := coalesce(must, should);
	IF result IS NOT NULL AND mustnot IS NOT NULL THEN result := result && !! mustnot; END IF;
	IF result IS NULL THEN RETURN ''::tsquery; END IF;
	RETURN result;
END
\$fn\$
SQL;
	}

	/**
	 * Get statements for FULLTEXT keys (trigram indexes) that have no valid tsvector index yet
	 *
	 * Grouped per index: an interrupted concurrent build leaves an invalid index, which is dropped and built again.
	 *
	 * @param array $indexes Rows with schemaname, tablename, indexname, indexdef and valid (pg_index.indisvalid)
	 * @return array Array of arrays of statements, one per index
	 *
	 */
	public static function fulltextIndexBackfillSql(array $indexes) {
		$valid = [];
		foreach($indexes as $row) $valid[$row['indexname']] = !isset($row['valid']) || in_array($row['valid'], [true, 't', '1', 1], true);
		$q = function($id) { return '"' . str_replace('"', '""', $id) . '"'; };
		$groups = [];
		foreach($indexes as $row) {
			if(strpos($row['indexdef'], 'gin_trgm_ops') === false) continue;
			$name = $row['indexname'] . self::fulltextIndexSuffix;
			if(strlen($name) > 63 || (isset($valid[$name]) && $valid[$name])) continue;
			if(!preg_match_all('/("(?:[^"]|"")+"|[a-z_][a-z0-9_$]*)\)? gin_trgm_ops/', $row['indexdef'], $m)) continue;
			$docs = [];
			foreach($m[1] as $col) $docs[] = 'pw_tsvector(' . ($col[0] === '"' ? $col : $q($col)) . ')';
			$doc = count($docs) > 1 ? '(' . implode(' || ', $docs) . ')' : $docs[0];
			$group = [];
			if(isset($valid[$name])) $group[] = 'DROP INDEX CONCURRENTLY IF EXISTS ' . $q($row['schemaname']) . '.' . $q($name);
			$group[] = 'CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . $q($name) . ' ON ' . $q($row['schemaname']) . '.' . $q($row['tablename']) . " USING gin ($doc)";
			$groups[] = $group;
		}
		return $groups;
	}

	/**
	 * Create the pw_fold() and pw_unaccent() functions (and the unaccent extension when possible)
	 *
	 * pw_fold(text) is lower(unaccent(text)): text columns are compared, searched, sorted and indexed on
	 * it, so that comparisons ignore case and accents as MySQL's default collations do. pw_unaccent(text)
	 * removes accents only, for REGEXP patterns. Both are IMMUTABLE so that they can be indexed, and call
	 * unaccent() schema-qualified so that they do not depend on search_path. Without the unaccent
	 * extension they fold case only.
	 *
	 * If the unaccent rules change (i.e. an upgraded unaccent.rules file), indexes on pw_fold() are
	 * stale: rebuild them with REINDEX.
	 *
	 * Used on connect and by the installer, so it takes callables rather than a connection.
	 *
	 * @param callable $exec function(string $sql): executes a statement (throws on error)
	 * @param callable $fetchColumn function(string $sql): returns the first column of the first row
	 * @return array [ 'fold' => bool, 'accents' => bool, 'error' => string ]
	 *
	 */
	public static function setupFold(callable $exec, callable $fetchColumn) {
		$result = ['fold' => false, 'accents' => false, 'error' => ''];
		$schema = '';
		try {
			$exec('CREATE EXTENSION IF NOT EXISTS unaccent');
		} catch(\Exception $e) {
			$result['error'] = 'unaccent extension unavailable, so comparisons ignore case but not accents: ' . $e->getMessage();
		}
		try {
			$schema = (string) $fetchColumn(
				"SELECT n.nspname FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'unaccent'"
			);
		} catch(\Exception $e) {
			$schema = '';
		}
		foreach(self::foldFunctionsSql($schema) as $sql) {
			try {
				$exec($sql);
			} catch(\Exception $e) {
				$result['error'] = 'pw_fold() could not be created, so comparisons are case- and accent-sensitive: ' . $e->getMessage();
				return $result;
			}
		}
		$result['fold'] = true;
		$result['accents'] = $schema !== '';
		return $result;
	}

	/**
	 * Get the CREATE FUNCTION statements for pw_unaccent() and pw_fold()
	 *
	 * @param string $unaccentSchema Schema of the unaccent extension, or blank when it is not installed
	 * @return array
	 *
	 */
	public static function foldFunctionsSql($unaccentSchema) {
		$options = 'RETURNS text LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE';
		if($unaccentSchema === '') {
			$unaccent = '$1';
		} else {
			$s = '"' . str_replace('"', '""', $unaccentSchema) . '"';
			$dictionary = str_replace("'", "''", "$s.unaccent");
			$unaccent = "$s.unaccent('$dictionary'::regdictionary, \$1)";
		}
		return [
			"CREATE OR REPLACE FUNCTION pw_unaccent(text) $options AS \$fn\$ SELECT $unaccent \$fn\$",
			"CREATE OR REPLACE FUNCTION pw_fold(text) $options AS \$fn\$ SELECT lower($unaccent) \$fn\$",
		];
	}

	/**
	 * Forget cached schema facts for a table (after DDL changes it)
	 *
	 * @param string $table
	 *
	 */
	protected function clearSchemaCache($table) {
		unset($this->schema[$table]);
		$this->cache = []; // cached translations may depend on the old schema (typed comparisons)
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
		$this->cache = []; // cached translations may depend on the old schema
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
			// a table that does not exist (yet) is not remembered, so that it is looked up again once created
			if(count($facts['columns'])) $this->schema[$table] = $facts;
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
		// assignment targets of an UPDATE's SET clause (col = value) are not comparisons to fold
		$assignments = [];
		$depth = 0;
		$inSet = false;
		$expectTarget = false;
		foreach($tokens as $x => $t) {
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				if($inSet && $depth === 0 && $t[1] === ',') $expectTarget = true;
				continue;
			}
			if($depth !== 0 || $t[0] === 'ws') continue;
			if($this->isWord($t, 'SET')) {
				$inSet = true;
				$expectTarget = true;
			} else if($this->isWord($t, ['WHERE', 'FROM', 'ORDER', 'LIMIT', 'RETURNING'])) {
				$inSet = false;
				$expectTarget = false;
			} else if($inSet && $expectTarget) {
				$assignments[$x] = true;
				$expectTarget = false;
			}
		}
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			// SET col = value: an assignment, so only number coercion (below) applies, not folding or zero-date tests
			$assignment = isset($assignments[$i]);
			$foldHere = !$assignment;
			if($t[0] === 'punct' && $t[1] === '(' && $this->startsSelect($tokens, $i)) {
				// a subquery has tables of its own and is handled by subqueries()
				$end = $this->matchParen($tokens, $i);
				if($end > $i) {
					for($x = $i; $x <= $end; $x++) $out[] = $tokens[$x];
					$i = $end;
					continue;
				}
			}
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
			$isValue = $r > -1 && ($tokens[$r][0] === 'str' || ($tokens[$r][0] === 'param' && $tokens[$r][1] !== '?'));
			if($class === 'datetime' && $r > -1 && ($tokens[$r][0] === 'param' || ($assignment && $tokens[$r][0] === 'str' && self::isZeroDate($tokens[$r][1])))) {
				// SET d = '0000-00-00' is NULL; a bound value for a date column is NULL when it is a zero date
				foreach($colTokens as $ct) $out[] = $ct;
				for($x = $end; $x < $r; $x++) $out[] = $tokens[$x];
				$out[] = ['word', $tokens[$r][0] === 'param' ? self::zeroDateParam($tokens[$r][1], $schema['columns'][$column]) : 'NULL'];
				$i = $r;
				continue;
			}
			if($class === 'datetime' && !$assignment && $r > -1 && $tokens[$r][0] === 'str' && self::isZeroDate($tokens[$r][1])) {
				// MySQL's zero date is NULL here: = and <= match it, != and > match every other date
				$map = ['=' => 'IS NULL', '<=' => 'IS NULL', '!=' => 'IS NOT NULL', '<>' => 'IS NOT NULL', '>' => 'IS NOT NULL'];
				if(isset($map[$op[1]])) {
					foreach($colTokens as $ct) $out[] = $ct;
					$out[] = ['ws', ' '];
					$out[] = ['word', $map[$op[1]]];
				} else {
					$out[] = ['word', $op[1] === '<' ? 'false' : 'true']; // nothing is before it; everything is on or after it
				}
				$i = $r;
				continue;
			}
			if($class === 'number' && $isValue) {
				// (a positional ? is left alone: the conversion repeats its parameter, which would shift the others)
				foreach($colTokens as $ct) $out[] = $ct;
				for($x = $end; $x < $r; $x++) $out[] = $tokens[$x];
				$out[] = ['word', $this->mysqlNumber($tokens[$r], $schema['columns'][$column])];
				$i = $r;
				continue;
			}
			if($class === 'text' && $this->foldAvailable && $foldHere) {
				$folded = $this->foldComparison($tokens, $colTokens, $end, $k);
				if($folded !== null) {
					foreach($folded[0] as $ft) $out[] = $ft;
					$i = $folded[1];
					continue;
				}
			}
			$out[] = $t;
		}
		return $out;
	}

	/**
	 * Fold a comparison of a text column with a value, as MySQL's case- and accent-insensitive collations compare
	 *
	 * `col = v`, `col LIKE v` and `col IN (v, ...)` become `pw_fold(col) = pw_fold(v)` and so on, where v is a
	 * string literal or a named parameter. REGEXP keeps MySQL's behaviour (case-insensitive, accent-sensitive) and
	 * gets a looser folded match in front of it for the trigram index. Comparisons
	 * with other columns (joins) and with '' are left alone.
	 *
	 * @param array $tokens
	 * @param array $colTokens Tokens of the column reference
	 * @param int $end Index after the column tokens
	 * @param int $k Index of the operator
	 * @return array|null [ tokens to output, index of the last token consumed ], or null to leave it alone
	 *
	 */
	protected function foldComparison(array $tokens, array $colTokens, $end, $k) {
		$isValue = function($x) use($tokens) {
			if($x < 0) return false;
			$t = $tokens[$x];
			if($t[0] === 'param') return $t[1] !== '?';
			return $t[0] === 'str' && $t[1] !== "''";
		};
		$fold = function(array $ts, $fn = 'pw_fold') { return ['word', "$fn(" . trim($this->join($ts)) . ')']; };
		$out = [$fold($colTokens)];
		$op = $tokens[$k];
		$opEnd = $k; // last token of the operator
		if($op[0] === 'punct' && in_array($op[1], ['=', '!=', '<>', '<', '>', '<=', '>='], true)) {
			// comparison
		} else if($op[0] === 'punct' && $op[1] === '~*') {
			// MySQL's REGEXP follows the collation for case but not for accents, which ~* (case-insensitive)
			// already matches. The folded match first is a looser test that the trigram index on pw_fold()
			// can answer, so that only its rows get the exact test.
			$r = $this->next($tokens, $k + 1);
			if(!$isValue($r)) return null;
			$col = trim($this->join($colTokens));
			$p = $tokens[$r][1];
			return [[['word', "(pw_fold($col) ~* pw_unaccent($p) AND $col ~* $p)"]], $r];
		} else if($op[0] === 'punct' && $op[1] === '!~*') {
			return null; // NOT REGEXP: exact, as MySQL (a looser match cannot narrow a negation)
		} else if($this->isWord($op, ['LIKE', 'ILIKE'])) {
			$op = ['word', 'LIKE'];
		} else if($this->isWord($op, 'NOT')) {
			$k2 = $this->next($tokens, $k + 1);
			if($k2 < 0 || !$this->isWord($tokens[$k2], ['LIKE', 'ILIKE'])) return null;
			$op = ['word', 'NOT LIKE'];
			$opEnd = $k2;
		} else if($this->isWord($op, 'IN')) {
			$open = $this->next($tokens, $k + 1);
			if($open < 0 || $tokens[$open][1] !== '(' || $tokens[$open][0] !== 'punct') return null;
			$close = $this->matchParen($tokens, $open);
			if($close < 0) return null;
			$items = [];
			foreach($this->splitCommas(array_slice($tokens, $open + 1, $close - $open - 1)) as $item) {
				$item = $this->trimTokens($item);
				if(count($item) !== 1 || !in_array($item[0][0], ['str', 'param']) || $item[0][1] === '?') return null;
				$items[] = "pw_fold({$item[0][1]})";
			}
			for($x = $end; $x < $k; $x++) $out[] = $tokens[$x];
			$out[] = $op;
			for($x = $k + 1; $x < $open; $x++) $out[] = $tokens[$x];
			$out[] = ['word', '(' . implode(', ', $items) . ')'];
			return [$out, $close];
		} else {
			return null;
		}
		$r = $this->next($tokens, $opEnd + 1);
		if(!$isValue($r)) return null;
		for($x = $end; $x < $k; $x++) $out[] = $tokens[$x];
		$out[] = $op;
		for($x = $opEnd + 1; $x < $r; $x++) $out[] = $tokens[$x];
		$out[] = $fold([$tokens[$r]]);
		return [$out, $r];
	}

	/**
	 * Get an expression that converts a string to a number the way MySQL does ('' and 'abc' are 0, '12abc' is 12)
	 *
	 * For a parameter, the result has the column's own type family (bigint for integer columns), so
	 * that PostgreSQL can still use an index on the column, and NULL stays NULL. Against an integer
	 * column only the integer part of a bound value is used ('1.5' compares as 1, where MySQL
	 * compares 1.5).
	 *
	 * @param array $t String literal or parameter token
	 * @param string $pgType Type of the column compared to
	 * @return string
	 *
	 */
	protected function mysqlNumber(array $t, $pgType = '') {
		if($t[0] === 'str') {
			$value = str_replace("''", "'", substr($t[1], 1, -1));
			if(!preg_match('/^\s*([-+]?(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][-+]?[0-9]+)?)/', $value, $m)) return '0';
			// an integer as written stays a string literal (any column type reads it); anything else is a number,
			// so that an integer column compares it numerically ('1.5' matches nothing, as in MySQL) rather than failing
			return $m[1] === $value && preg_match('/^-?[0-9]+$/', $value) ? $t[1] : '(' . $m[1] . ')';
		}
		$p = "($t[1])::text";
		$pgType = strtolower((string) $pgType);
		if(in_array($pgType, ['smallint', 'integer', 'bigint', 'int', 'int2', 'int4', 'int8'], true)) {
			return "(CASE WHEN $p IS NULL THEN NULL WHEN $p ~ '^\\s*[-+]?[0-9]' THEN substring($p from '[-+]?[0-9]+')::bigint ELSE 0 END)";
		}
		$cast = in_array($pgType, ['real', 'double precision', 'float4', 'float8'], true) ? 'double precision' : 'numeric';
		return "(CASE WHEN $p IS NULL THEN NULL ELSE " . self::mysqlNumberSql($p) . "::$cast END)";
	}

	/**
	 * Get an expression for the number MySQL reads from a string: its leading number, with fraction and exponent
	 *
	 * `'12.5'` is 12.5, `'-3.75x'` is -3.75, `'.5'` is 0.5, `'1e3'` is 1000, and a string without a leading
	 * number (`''`, `'abc'`) is 0. Casting the result to an integer type rounds, as MySQL does.
	 *
	 * @param string $text Expression of type text
	 * @return string Expression of type numeric
	 *
	 */
	protected static function mysqlNumberSql($text) {
		return "(CASE WHEN $text ~ '^\\s*[-+]?([0-9]+\\.?[0-9]*|\\.[0-9]+)' " .
			"THEN substring($text from '^\\s*([-+]?([0-9]+\\.?[0-9]*|\\.[0-9]+)([eE][-+]?[0-9]+)?)')::numeric ELSE 0 END)";
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
	protected function nativeInsert($sql) {
		// INSERT INTO "table" ("col", ...): an explicit identity value leaves the sequence behind, as in insert()
		if(!preg_match('/^\s*INSERT\s+INTO\s+"((?:[^"]|"")+)"\s*\(([^)]*)\)/i', $sql, $m)) return $sql;
		$table = str_replace('""', '"', $m[1]);
		$schema = $this->tableSchema($table);
		if($schema['identity'] === null) return $sql;
		preg_match_all('/"((?:[^"]|"")+)"/', $m[2], $matches);
		$columns = array();
		foreach($matches[1] as $column) $columns[] = str_replace('""', '"', $column);
		if(!in_array($schema['identity'], $columns, true)) return $sql;
		return [$sql, $this->setvalSql($table, $schema['identity'])];
	}

	/**
	 * Is the SQL an INSERT that is already PostgreSQL syntax (has an ON CONFLICT clause)?
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
			"ARRAY(SELECT pg_get_indexdef(ix.indexrelid, k + 1, true) FROM generate_subscripts(ix.indkey, 1) AS k ORDER BY k) AS cols, " .
			"obj_description(i.oid, 'pg_class') AS comment " .
			"FROM pg_index ix JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid " .
			"JOIN pg_namespace n ON n.oid = t.relnamespace " .
			"WHERE t.relname = ? AND n.nspname = current_schema() ORDER BY i.relname",
			[$table]
		);
		$indexes = [];
		foreach($rows as $row) {
			$cols = trim((string) $row['cols'], '{}');
			$indexes[] = [
				'pgName' => $row['name'],
				'name' => self::mysqlIndexName($table, (string) $row['name'], $row['comment']),
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
				$m = count($bodyTokens);
				$valuesPos = -1;
				for($x = 0; $x < $m; $x++) {
					if($this->isWord($bodyTokens[$x], 'VALUES')) { $valuesPos = $x; break; }
				}
				// INSERT ... SELECT gives values we cannot see; VALUES rows are checked one by one below
				$explicitIdentity = $valuesPos < 0;
				for($x = $valuesPos > -1 ? $valuesPos + 1 : $m; $x < $m; $x++) {
					if($bodyTokens[$x][0] !== 'punct' || $bodyTokens[$x][1] !== '(') continue;
					$end = $this->matchParen($bodyTokens, $x);
					$parts = $this->splitCommas(array_slice($bodyTokens, $x + 1, $end - $x - 1));
					if(isset($parts[$identityPos])) {
						$value = $this->trimTokens($parts[$identityPos]);
						if(count($value) === 1 && $this->isWord($value[0], ['NULL', 'DEFAULT'])) {
							$parts[$identityPos] = [['word', 'DEFAULT']];
						} else {
							$explicitIdentity = true; // at least one row gives its own id
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
		if($explicitIdentity && $identity !== null) $statements[] = $this->setvalSql($table, $identity);

		return $statements;
	}

	/**
	 * Get a statement that moves an identity column's sequence past the highest id in the table
	 *
	 * The sequence never moves backwards (i.e. after the rows with the highest ids were deleted), so
	 * ids of deleted rows are not handed out again, as with MySQL's AUTO_INCREMENT.
	 *
	 * @param string $table
	 * @param string $column
	 * @return string
	 *
	 */
	protected function setvalSql($table, $column) {
		$qt = $this->quoteId($table);
		$qc = $this->quoteId($column);
		$lt = str_replace("'", "''", $qt);
		$lc = str_replace("'", "''", $column);
		return "SELECT setval(s::regclass, GREATEST((SELECT MAX($qc) FROM $qt), pg_sequence_last_value(s::regclass), 1)) " .
			"FROM pg_get_serial_sequence('$lt', '$lc') AS s";
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
	protected function upsertExpression(array $tokens, $table, array $columns, $quote = false) {
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
					$col = $quote ? $this->quoteId($this->name($inner[0])) : $this->join([$inner[0]]);
					$out[] = ['word', 'excluded.' . $col];
					$y = $end;
					continue;
				}
			}
			// (string literals are 'str' tokens, so column names inside them are never touched)
			if(($t[0] === 'word' || $t[0] === 'id') && in_array(strtolower($this->name($t)), $lower, true)) {
				$prev = $this->prev($tokens, $y - 1);
				$next = $this->next($tokens, $y + 1);
				$qualified = $prev > -1 && $tokens[$prev][0] === 'punct' && $tokens[$prev][1] === '.';
				$call = $next > -1 && $tokens[$next][0] === 'punct' && ($tokens[$next][1] === '(' || $tokens[$next][1] === '.');
				if(!$qualified && !$call) {
					$out[] = ['word', $quote ? $this->quoteId($table) . '.' . $this->quoteId($this->name($t)) : $table . '.' . $this->join([$t])];
					continue;
				}
			}
			$out[] = $t;
		}
		return trim($this->join($this->expressions($out)));
	}

	/**
	 * Translate a MySQL value expression for an upsert() column (i.e. `NOW()`, `:name`, a quoted literal)
	 *
	 * upsert() output is PostgreSQL SQL that is not translated again, so the expressions a caller
	 * gives it (MySQL syntax, like all SQL in ProcessWire) are translated here.
	 *
	 * @param string $expr
	 * @return string
	 *
	 */
	public function upsertValueSql($expr) {
		return trim($this->join($this->expressions($this->tokenize((string) $expr))));
	}

	/**
	 * Translate a MySQL update expression for upsert(): VALUES(col) is the inserted value, and a bare
	 * column name is the existing row's value (qualified with the table, as PostgreSQL requires)
	 *
	 * @param string $expr
	 * @param string $table
	 * @param array $columns Column names of the table that may appear bare in the expression
	 * @return string
	 *
	 */
	public function upsertUpdateSql($expr, $table, array $columns) {
		return $this->upsertExpression($this->tokenize((string) $expr), $table, $columns, true);
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
		$rest = $this->join(array_slice($tokens, $k + 1)); // expression passes already applied
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
				$using[] = trim($this->join(array_slice($tokens, $x + 1, $onPos - $x - 1)));
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
				$conds[] = trim($this->join(array_slice($tokens, $onPos + 1, $condEnd - $onPos - 1)));
				$x = $condEnd;
				continue;
			}
			if($this->isWord($t, 'WHERE')) {
				$where = trim($this->join(array_slice($tokens, $x + 1)));
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
	 * Get the kind of a constraint definition: CONSTRAINT [name] (FOREIGN|UNIQUE|PRIMARY|CHECK) ...
	 *
	 * @param array $def
	 * @return string Uppercase kind, or blank
	 *
	 */
	protected function constraintKind(array $def) {
		foreach($def as $t) {
			if($this->isWord($t, ['FOREIGN', 'UNIQUE', 'PRIMARY', 'CHECK'])) return strtoupper($t[1]);
			if($t[0] === 'punct') break;
		}
		return '';
	}

	/**
	 * Get a UNIQUE KEY definition from CONSTRAINT name UNIQUE [KEY|INDEX] [index_name] (cols)
	 *
	 * @param array $def
	 * @return array
	 *
	 */
	protected function constraintAsIndexDef(array $def) {
		$name = null;
		$x = $this->next($def, 0); // CONSTRAINT
		$y = $this->next($def, $x + 1);
		if($y > -1 && !$this->isWord($def[$y], 'UNIQUE')) {
			$name = $this->name($def[$y]);
			$y = $this->next($def, $y + 1);
		}
		$rest = array_slice($def, $y + 1); // after UNIQUE
		$z = $this->next($rest, 0);
		if($z > -1 && $this->isWord($rest[$z], ['KEY', 'INDEX'])) $rest = array_slice($rest, $z + 1);
		$z = $this->next($rest, 0);
		if($z > -1 && $rest[$z][1] !== '(') $name = $this->name($rest[$z]); // an index name of its own
		if($z > -1 && $rest[$z][1] !== '(') $rest = array_slice($rest, $z + 1);
		$out = [['word', 'UNIQUE'], ['ws', ' '], ['word', 'KEY']];
		if($name !== null) {
			$out[] = ['ws', ' '];
			$out[] = ['id', $name];
		}
		$out[] = ['ws', ' '];
		return array_merge($out, $this->trimTokens($rest));
	}

	/**
	 * Multi-table UPDATE: UPDATE t [AS a] [INNER] JOIN u ON cond SET a.x=u.y [WHERE ...], or UPDATE t, u SET ...
	 *
	 * Translated to UPDATE t [AS a] SET x=u.y FROM u WHERE cond [AND ...]. Only the first table can be
	 * updated (PostgreSQL updates one table), and LEFT/RIGHT joins have no FROM equivalent.
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function updateJoin(array $tokens) {
		$n = count($tokens);
		$i = $this->next($tokens, 0); // UPDATE
		$setPos = -1;
		$depth = 0;
		for($x = $i + 1; $x < $n; $x++) {
			$t = $tokens[$x];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth === 0 && $this->isWord($t, 'SET')) { $setPos = $x; break; }
		}
		if($setPos < 0) return $tokens;
		$refs = $this->trimTokens(array_slice($tokens, $i + 1, $setPos - $i - 1));
		$isJoin = false;
		foreach($refs as $t) {
			if(($t[0] === 'punct' && $t[1] === ',') || $this->isWord($t, ['JOIN', 'STRAIGHT_JOIN'])) $isJoin = true;
			if($this->isWord($t, ['LEFT', 'RIGHT'])) {
				throw new \PDOException('PostgreSQL translator: UPDATE with a LEFT JOIN or RIGHT JOIN is not supported (UPDATE ... FROM has no outer join)');
			}
		}
		if(!$isJoin) return $tokens;

		// the target table and its alias, then the other tables and their join conditions
		$split = function(array $list) {
			// split at top-level JOIN keywords and commas, keeping ON conditions with their table
			$parts = [];
			$current = [];
			$depth = 0;
			foreach($list as $t) {
				if($t[0] === 'punct' && $t[1] === '(') $depth++;
				if($t[0] === 'punct' && $t[1] === ')') $depth--;
				$sep = $depth === 0 && (($t[0] === 'punct' && $t[1] === ',') || $this->isWord($t, ['JOIN', 'STRAIGHT_JOIN']));
				if($sep) {
					$parts[] = $current;
					$current = [];
					continue;
				}
				if($depth === 0 && $this->isWord($t, ['INNER', 'CROSS'])) continue;
				$current[] = $t;
			}
			$parts[] = $current;
			return $parts;
		};
		$parts = $split($refs);
		$target = $this->trimTokens(array_shift($parts));
		$targetWords = array_values(array_filter($target, function($t) { return $t[0] !== 'ws' && !$this->isWord($t, 'AS'); }));
		$names = [strtolower($this->name($targetWords[0]))];
		if(isset($targetWords[1])) $names[] = strtolower($this->name($targetWords[1]));
		$from = [];
		$conds = [];
		foreach($parts as $part) {
			$part = $this->trimTokens($part);
			$on = -1;
			foreach($part as $x => $t) if($this->isWord($t, 'ON')) { $on = $x; break; }
			if($on < 0) {
				$from[] = trim($this->join($part));
			} else {
				$from[] = trim($this->join(array_slice($part, 0, $on)));
				$conds[] = trim($this->join(array_slice($part, $on + 1)));
			}
		}

		// SET assignments: target columns may be qualified by the target table only
		$end = $n;
		$wherePos = -1;
		$depth = 0;
		for($x = $setPos + 1; $x < $n; $x++) {
			$t = $tokens[$x];
			if($t[0] === 'punct') {
				if($t[1] === '(') $depth++;
				if($t[1] === ')') $depth--;
				continue;
			}
			if($depth !== 0) continue;
			if($this->isWord($t, 'WHERE') && $wherePos < 0) $wherePos = $x;
			if($this->isWord($t, ['ORDER', 'LIMIT'])) { $end = $x; break; }
		}
		$setEnd = $wherePos > -1 ? $wherePos : $end;
		$sets = [];
		foreach($this->splitCommas(array_slice($tokens, $setPos + 1, $setEnd - $setPos - 1)) as $assign) {
			$assign = $this->trimTokens($assign);
			if(count($assign) >= 3 && $assign[1][0] === 'punct' && $assign[1][1] === '.') {
				if(!in_array(strtolower($this->name($assign[0])), $names, true)) {
					throw new \PDOException('PostgreSQL translator: UPDATE with a join can set columns of one target table only (the first)');
				}
				$assign = array_slice($assign, 2); // PostgreSQL's SET names columns of the target table unqualified
			}
			$sets[] = trim($this->join($assign));
		}
		if($wherePos > -1) {
			$where = $this->trimTokens(array_slice($tokens, $wherePos + 1, $end - $wherePos - 1));
			$sql = trim($this->join($where));
			$conds[] = $this->hasTopLevelWord($where, 'OR') ? "($sql)" : $sql;
		}
		$sql = 'UPDATE ' . trim($this->join($target)) . ' SET ' . implode(', ', $sets) . ' FROM ' . implode(', ', $from);
		if(count($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);
		return array_merge([['word', $sql]], array_slice($tokens, $end));
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
		$sub = 'SELECT ctid FROM ' . $table . ' ' . trim($this->join(array_slice($tokens, $setEnd)));
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
					"FROM information_schema.columns WHERE table_schema=current_schema() AND table_name=$qt " .
					"AND NOT (data_type = 'tsvector' AND is_generated = 'ALWAYS') ORDER BY ordinal_position";
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
		$onUpdate = false; // ON UPDATE CURRENT_TIMESTAMP

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
				// ON UPDATE CURRENT_TIMESTAMP[()]: a trigger (see onUpdateSql())
				$y = $this->next($def, $x + 1); // UPDATE
				$z = $y > -1 ? $this->next($def, $y + 1) : -1; // CURRENT_TIMESTAMP
				if($z === -1) break;
				$onUpdate = true;
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

		if($default !== null && self::isZeroDate($default)) {
			// MySQL's zero date is NULL here, so the column must accept it (and cannot default to it)
			$default = 'NULL';
			$nullSpec = 'NULL';
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
			'onUpdate' => $onUpdate,
		];
	}

	/**
	 * Is the value (a SQL literal or a string) one of MySQL's zero dates ('0000-00-00', '0000-00-00 00:00:00')?
	 *
	 * @param string $value
	 * @return bool
	 *
	 */
	public static function isZeroDate($value) {
		return (bool) preg_match("/^'?0000-00-00(?:[ T]00:00:00(?:\\.0+)?)?'?\$/", (string) $value);
	}

	/**
	 * Get an expression for a bound value meant for a date column: NULL when it is a zero date, else the value as the column's type
	 *
	 * MySQL's zero dates are NULL here (PostgreSQL rejects them). The parameter is used once, so that a positional `?`
	 * keeps its place, and the expression is constant for a given value, so that an index on the column still serves it.
	 *
	 * @param string $param Placeholder (`:name` or `?`)
	 * @param string $pgType The column's type
	 * @return string
	 *
	 */
	public static function zeroDateParam($param, $pgType) {
		return "NULLIF(regexp_replace(($param)::text, '^\\s*0000-00-00([ T]00:00:00(\\.0+)?)?\\s*$', ''), '')::$pgType";
	}

	/**
	 * Replace MySQL's zero dates with NULL where an INSERT or REPLACE gives them to a date column
	 *
	 * Values in VALUES rows (by the column list, or the table's column order), and assignments in `SET` and
	 * `ON DUPLICATE KEY UPDATE`. Literal zero dates become NULL, and bound values get zeroDateParam(). Other
	 * columns keep the value: text that happens to be a zero date is text. Statements other than INSERT are
	 * handled by typedComparisons().
	 *
	 * @param array $tokens
	 * @return array
	 *
	 */
	protected function zeroDates(array $tokens) {
		$n = count($tokens);
		$into = -1;
		for($i = 0; $i < $n; $i++) {
			if($this->isWord($tokens[$i], 'INTO')) { $into = $i; break; }
		}
		$ti = $into > -1 ? $this->next($tokens, $into + 1) : -1;
		if($ti < 0 || !in_array($tokens[$ti][0], ['word', 'id'], true)) return $tokens;
		$schema = $this->tableSchema($this->name($tokens[$ti]));
		if(!count($schema['columns'])) return $tokens;
		$fix = function($k, $col) use(&$tokens, $schema) {
			if($k < 0 || $col === null || !isset($schema['columns'][$col]) || $this->typeClass($schema['columns'][$col]) !== 'datetime') return;
			$t = $tokens[$k];
			if($t[0] === 'str' && self::isZeroDate($t[1])) {
				$tokens[$k] = ['word', 'NULL'];
			} else if($t[0] === 'param') {
				$tokens[$k] = ['word', self::zeroDateParam($t[1], $schema['columns'][$col])];
			}
		};
		// the one token of a value, or -1 when it is an expression
		$single = function($from, $to) use(&$tokens) {
			$found = -1;
			for($x = $from; $x <= $to; $x++) {
				if($tokens[$x][0] === 'ws') continue;
				if($found > -1) return -1;
				$found = $x;
			}
			return $found;
		};
		$j = $this->next($tokens, $ti + 1);
		$cols = null;
		if($j > -1 && $tokens[$j][0] === 'punct' && $tokens[$j][1] === '(' && !$this->startsSelect($tokens, $j)) {
			$close = $this->matchParen($tokens, $j);
			$cols = [];
			foreach($this->splitCommas(array_slice($tokens, $j + 1, $close - $j - 1)) as $c) {
				$c = $this->trimTokens($c);
				$cols[] = count($c) === 1 ? $this->name($c[0]) : null;
			}
			$j = $this->next($tokens, $close + 1);
		}
		if($j > -1 && $this->isWord($tokens[$j], ['VALUES', 'VALUE'])) {
			if($cols === null) $cols = array_keys($schema['columns']);
			$k = $this->next($tokens, $j + 1);
			while($k > -1 && $tokens[$k][0] === 'punct' && $tokens[$k][1] === '(') {
				$close = $this->matchParen($tokens, $k);
				if($close < 0) break;
				$depth = 0;
				$start = $k + 1;
				$arg = 0;
				for($x = $k + 1; $x <= $close; $x++) {
					$t = $tokens[$x];
					if($t[0] === 'punct' && $t[1] === '(') $depth++;
					if($t[0] === 'punct' && $t[1] === ')' && $x < $close) $depth--;
					if($x === $close || ($depth === 0 && $t[0] === 'punct' && $t[1] === ',')) {
						$fix($single($start, $x - 1), isset($cols[$arg]) ? $cols[$arg] : null);
						$arg++;
						$start = $x + 1;
					}
				}
				$k = $this->next($tokens, $close + 1);
				if($k > -1 && $tokens[$k][0] === 'punct' && $tokens[$k][1] === ',') {
					$k = $this->next($tokens, $k + 1);
				} else {
					break;
				}
			}
			$j = $k;
		}
		// assignments: INSERT ... SET a = v, and ON DUPLICATE KEY UPDATE a = v
		for($x = max($j, 0); $x > -1 && $x < $n; $x++) {
			if(!in_array($tokens[$x][0], ['word', 'id'], true) || $this->isWord($tokens[$x], ['SET', 'ON', 'DUPLICATE', 'KEY', 'UPDATE'])) continue;
			$e = $this->next($tokens, $x + 1);
			if($e < 0 || $tokens[$e][0] !== 'punct' || $tokens[$e][1] !== '=') continue;
			$v = $this->next($tokens, $e + 1);
			$after = $v > -1 ? $this->next($tokens, $v + 1) : -1;
			if($v > -1 && ($after < 0 || ($tokens[$after][0] === 'punct' && $tokens[$after][1] === ','))) $fix($v, $this->name($tokens[$x]));
		}
		return $tokens;
	}

	/**
	 * Get statements for a column's ON UPDATE CURRENT_TIMESTAMP (a trigger), or to remove it
	 *
	 * MySQL sets such a column to the current time when an UPDATE changes the row and does not set the
	 * column itself. One trigger function does that for any column named by its trigger's argument.
	 *
	 * @param string $table
	 * @param string $column
	 * @param bool $add Add (true) or remove (false)
	 * @param bool $replace When adding, remove an existing trigger first (i.e. for MODIFY)
	 * @return array
	 *
	 */
	protected function onUpdateSql($table, $column, $add = true, $replace = false) {
		$trigger = self::onUpdateTriggerName($column);
		$drop = 'DROP TRIGGER IF EXISTS ' . $this->quoteId($trigger) . ' ON ' . $this->quoteId($table);
		if(!$add) return [$drop];
		$statements = [
			"CREATE OR REPLACE FUNCTION pw_on_update_now() RETURNS trigger LANGUAGE plpgsql AS \$pw\$ BEGIN " .
				"IF NEW IS DISTINCT FROM OLD AND (to_jsonb(NEW) -> TG_ARGV[0]) IS NOT DISTINCT FROM (to_jsonb(OLD) -> TG_ARGV[0]) THEN " .
				"NEW := jsonb_populate_record(NEW, jsonb_build_object(TG_ARGV[0], localtimestamp)); " .
				"END IF; RETURN NEW; END \$pw\$",
		];
		if($replace) $statements[] = $drop;
		$statements[] = 'CREATE TRIGGER ' . $this->quoteId($trigger) . ' BEFORE UPDATE ON ' . $this->quoteId($table) .
				" FOR EACH ROW EXECUTE FUNCTION pw_on_update_now('" . str_replace("'", "''", $column) . "')";
		return $statements;
	}

	/**
	 * Get the name of a column's ON UPDATE CURRENT_TIMESTAMP trigger (see onUpdateSql())
	 *
	 * @param string $column
	 * @return string
	 *
	 */
	protected static function onUpdateTriggerName($column) {
		$trigger = 'pw_on_update__' . $column;
		if(strlen($trigger) > 63) $trigger = substr($trigger, 0, 54) . '_' . substr(md5($trigger), 0, 8);
		return $trigger;
	}

	/**
	 * Get a statement that moves a column's ON UPDATE CURRENT_TIMESTAMP trigger to its new name, if it has one
	 *
	 * The trigger names its column in its argument, so a renamed column needs a new trigger to keep updating.
	 * Checked when it runs, since the statement alone does not say whether the column has one.
	 *
	 * @param string $table
	 * @param string $from
	 * @param string $to
	 * @return string
	 *
	 */
	protected function onUpdateRenameSql($table, $from, $to) {
		$q = function($sql) { return "'" . str_replace("'", "''", $sql) . "'"; };
		$old = self::onUpdateTriggerName($from);
		$drop = 'DROP TRIGGER IF EXISTS ' . $this->quoteId($old) . ' ON ' . $this->quoteId($table);
		$statements = $this->onUpdateSql($table, $to);
		$create = end($statements);
		return "DO \$pw\$ BEGIN IF EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = " . $q($old) . " AND tgrelid = to_regclass(" . $q($this->quoteId($table)) . ")) " .
			"THEN EXECUTE " . $q($drop) . "; EXECUTE " . $q($create) . "; END IF; END \$pw\$";
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
	 * @return array Statements (none when the index is skipped)
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
		if(!count($cols)) return [];
		$name = $this->indexDefName($def);
		if($name === null) $name = $cols[0];
		$statements = $this->createIndexSql($table, $name, $cols, $lens, $unique, $fulltext, $ifNotExists, $columnTypes);
		if(!count($statements)) return [];
		// long names are shortened (see indexName()), so record the MySQL names, the folded companion's included
		$names = count($statements) > 1 ? [$name, $name . self::foldIndexSuffix] : [$name];
		foreach($names as $indexName) {
			$comment = $this->indexCommentSql($table, $indexName);
			if($comment !== '') $statements[] = $comment;
		}
		return $statements;
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
		$isText = function($col) use($columnTypes) {
			return isset($columnTypes[$col]) && !empty($columnTypes[$col]['isText']);
		};
		$isUnbounded = function($col) use($columnTypes, $isText) {
			return $isText($col) && strpos($columnTypes[$col]['pgType'], 'text') === 0;
		};
		$fold = $this->foldAvailable && count(array_filter($cols, $isText));
		$head = function($indexName, $unique) use($table, $ifNotExists) {
			return 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') .
				$this->quoteId($this->indexName($table, $indexName)) . ' ON ' . $this->quoteId($table);
		};
		if($fulltext) {
			// a tsvector index serves MATCH ... AGAINST (see matchAgainst()), and a trigram index (requires pg_trgm)
			// serves the LIKE and REGEXP searches that ProcessWire also runs on FULLTEXT-indexed columns
			$statements = [];
			if($this->trigramAvailable) {
				$gin = [];
				foreach($cols as $col) {
					$q = $this->quoteId($col);
					$gin[] = ($fold && $isText($col) ? "pw_fold($q)" : $q) . ' gin_trgm_ops';
				}
				$statements[] = $head($name, $unique) . ' USING gin (' . implode(', ', $gin) . ')';
			}
			if($this->fulltextAvailable) {
				// a stored tsvector per column, so that ranking (ts_rank) reads it rather than parsing every matching row again
				foreach($cols as $col) {
					$statements[] = 'ALTER TABLE ' . $this->quoteId($table) . ' ADD COLUMN IF NOT EXISTS ' . $this->vectorColumnSql($col);
				}
				$statements[] = $head($name . self::fulltextIndexSuffix, false) . ' USING gin (' . $this->tsvectorSql($cols) . ')';
				$this->clearSchemaCache($table);
				$this->cache = [];
			}
			return $statements;
		}
		$parts = [];
		foreach($cols as $n => $col) {
			$q = $this->quoteId($col);
			// a prefix on an unbounded text column becomes an expression index, since btree entries are limited in size
			$parts[] = isset($prefixLens[$n]) && $isUnbounded($col) ? "left($q, $prefixLens[$n])" : $q;
		}
		$plain = ' (' . implode(', ', $parts) . ')';
		if(!$fold) return [$head($name, $unique) . $plain];

		// text is compared on its folded value (see foldComparison()), so index that
		if(count($cols) === 1 && $isUnbounded($cols[0])) {
			// equality only: a btree cannot hold arbitrarily long values, a hash index can (LIKE uses the trigram index)
			$folded = ' USING hash (pw_fold(' . $this->quoteId($cols[0]) . '))';
		} else {
			$foldedParts = [];
			foreach($cols as $n => $col) {
				$q = $this->quoteId($col);
				if(!$isText($col)) {
					$foldedParts[] = $q;
				} else if($isUnbounded($col)) {
					$len = isset($prefixLens[$n]) ? $prefixLens[$n] : 250;
					$foldedParts[] = "pw_fold(left($q, $len))";
				} else {
					$foldedParts[] = "pw_fold($q)";
				}
			}
			$folded = ' (' . implode(', ', $foldedParts) . ')';
		}
		// a unique key keeps its exact index, so that uniqueness is as before, and gets a folded companion
		if($unique) return [$head($name, true) . $plain, $head($name . self::foldIndexSuffix, false) . $folded];
		return [$head($name, false) . $folded];
	}

	/**
	 * Get the indexed tsvector of FULLTEXT columns: their stored vector columns, as storedVectors() writes MATCH(cols)
	 *
	 * @param array $cols Column names
	 * @return string
	 *
	 */
	protected function tsvectorSql(array $cols) {
		$docs = [];
		foreach($cols as $col) $docs[] = $this->quoteId($col . self::vectorColumnSuffix);
		return count($docs) > 1 ? '(' . implode(' || ', $docs) . ')' : $docs[0];
	}

	/**
	 * Get the definition of the stored tsvector column of a column (for ADD COLUMN)
	 *
	 * @param string $col
	 * @return string
	 *
	 */
	protected function vectorColumnSql($col) {
		return $this->quoteId($col . self::vectorColumnSuffix) . ' tsvector GENERATED ALWAYS AS (pw_tsvector(' . $this->quoteId($col) . ')) STORED';
	}

	/**
	 * Does a column have a stored tsvector column (see vectorColumnSql())?
	 *
	 * @param string $table
	 * @param string $col
	 * @return bool
	 *
	 */
	protected function hasVectorColumn($table, $col) {
		if(!$this->fulltextAvailable) return false;
		$schema = $this->tableSchema($table);
		return isset($schema['columns'][$col . self::vectorColumnSuffix]) && $schema['columns'][$col . self::vectorColumnSuffix] === 'tsvector';
	}

	/**
	 * Get the columns of a table that queries see: all but stored tsvector columns
	 *
	 * @param string $table
	 * @return array Column names in table order
	 *
	 */
	protected function visibleColumns($table) {
		$cols = [];
		foreach($this->tableSchema($table)['columns'] as $name => $pgType) {
			if($pgType === 'tsvector' && substr($name, -strlen(self::vectorColumnSuffix)) === self::vectorColumnSuffix) continue;
			$cols[] = $name;
		}
		return $cols;
	}

	/**
	 * Statements that keep a column's stored tsvector column in step when the column is dropped, renamed or changed
	 *
	 * PostgreSQL does not allow dropping or changing the type of a column that a generated column is made from,
	 * so the vector column is dropped first (with its index) and, unless the column is dropped, made again after.
	 *
	 * @param string $table
	 * @param string $col Column before the change
	 * @param string|null $newName Column after the change, or null when it is dropped
	 * @param bool $typeChange Does its type (or definition) change? (a rename alone keeps the vector column)
	 * @return array [ 'before' => [...], 'after' => [...] ]
	 *
	 */
	protected function vectorColumnChanges($table, $col, $newName, $typeChange) {
		$out = ['before' => [], 'after' => []];
		if(!$this->hasVectorColumn($table, $col)) return $out;
		$qTable = $this->quoteId($table);
		$vector = $col . self::vectorColumnSuffix;
		if($newName !== null && !$typeChange) {
			if($newName !== $col) {
				$out['after'][] = "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($vector) . ' TO ' . $this->quoteId($newName . self::vectorColumnSuffix);
			}
			return $out;
		}
		$indexes = $newName === null ? [] : $this->catalogRows(
			"SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexdef ~ ?",
			[$table, '\m' . preg_quote($vector) . '\M'], \PDO::FETCH_COLUMN
		);
		$out['before'][] = "ALTER TABLE $qTable DROP COLUMN IF EXISTS " . $this->quoteId($vector);
		if($newName !== null) {
			$out['after'][] = "ALTER TABLE $qTable ADD COLUMN IF NOT EXISTS " . $this->vectorColumnSql($newName);
			foreach($indexes as $def) {
				$out['after'][] = preg_replace('/\b' . preg_quote($vector, '/') . '\b/', $newName . self::vectorColumnSuffix, $def);
			}
		}
		return $out;
	}

	/**
	 * Use stored tsvector columns in MATCH translations: pw_tsvector(t.data) becomes t.data__tsv when it exists
	 *
	 * The FULLTEXT index is on the stored column, and ts_rank() then reads it rather than parsing every
	 * matching row's text again. Tables without one (FULLTEXT keys made before stored columns) keep the
	 * expression, which their expression index serves.
	 *
	 * @param array $tokens One SELECT (see selectPasses())
	 * @return array
	 *
	 */
	protected function storedVectors(array $tokens) {
		if(!$this->fulltextAvailable) return $tokens;
		$aliases = null;
		foreach($tokens as $n => $t) {
			if($t[0] !== 'word' || strpos($t[1], 'pw_tsvector(') === false) continue;
			if($aliases === null) $aliases = $this->tableAliases($tokens);
			$tables = array_values(array_unique($aliases));
			$tokens[$n][1] = preg_replace_callback('/pw_tsvector\((?:("?)([A-Za-z0-9_$]+)\1\.)?("?)([A-Za-z0-9_$]+)\3\)/', function($m) use($aliases, $tables) {
				$qualifier = $m[2];
				$table = $qualifier !== '' ? (isset($aliases[$qualifier]) ? $aliases[$qualifier] : null) : (count($tables) === 1 ? $tables[0] : null);
				if($table === null || !$this->hasVectorColumn($table, $m[4])) return $m[0];
				return ($qualifier !== '' ? $m[1] . $qualifier . $m[1] . '.' : '') . $this->quoteId($m[4] . self::vectorColumnSuffix);
			}, $t[1]);
		}
		return $tokens;
	}

	/**
	 * Expand `*` and `t.*` in a select list when a table has stored tsvector columns, leaving those out
	 *
	 * So that `SELECT * FROM field_body` returns the same columns as on MySQL (i.e. PagesVersions copies rows by it).
	 *
	 * @param array $tokens One SELECT (see selectPasses())
	 * @return array
	 *
	 */
	protected function selectStar(array $tokens) {
		if(!$this->fulltextAvailable) return $tokens;
		$n = count($tokens);
		$start = -1;
		$end = -1;
		$depth = 0;
		$stars = [];
		for($i = 0; $i < $n; $i++) {
			$t = $tokens[$i];
			if($t[0] === 'punct' && $t[1] === '(') $depth++;
			if($t[0] === 'punct' && $t[1] === ')') $depth--;
			if($depth !== 0) continue;
			if($start < 0) {
				if($this->isWord($t, 'SELECT')) $start = $i;
				continue;
			}
			if($this->isWord($t, 'FROM')) { $end = $i; break; }
			if($t[0] === 'punct' && $t[1] === '*') {
				// * alone (after SELECT, DISTINCT or a comma), or qualified: t.*
				$p = $this->prev($tokens, $i - 1);
				if($p > -1 && $tokens[$p][0] === 'punct' && $tokens[$p][1] === '.') {
					$q = $this->prev($tokens, $p - 1);
					if($q > -1 && in_array($tokens[$q][0], ['word', 'id'], true)) $stars[] = [$q, $i, $this->name($tokens[$q])];
				} else if($p > -1 && ($this->isWord($tokens[$p], ['SELECT', 'DISTINCT']) || ($tokens[$p][0] === 'punct' && $tokens[$p][1] === ','))) {
					$stars[] = [$i, $i, null];
				}
			}
		}
		if($end < 0 || !count($stars)) return $tokens;
		$aliases = $this->tableAliases($tokens);
		$hidden = function($table) {
			return count($this->visibleColumns($table)) !== count($this->tableSchema($table)['columns']);
		};
		// qualifier => table, in FROM order (a table's alias when it has one)
		$sources = [];
		foreach($aliases as $name => $table) {
			if($name !== $table) unset($sources[$table]);
			$sources[$name] = $table;
		}
		foreach(array_reverse($stars) as $star) {
			list($from, $to, $qualifier) = $star;
			$list = [];
			if($qualifier !== null) {
				if(!isset($aliases[$qualifier]) || !$hidden($aliases[$qualifier])) continue;
				foreach($this->visibleColumns($aliases[$qualifier]) as $col) $list[] = $this->quoteId($qualifier) . '.' . $this->quoteId($col);
			} else {
				$any = false;
				foreach($sources as $table) if($hidden($table)) $any = true;
				if(!$any || count($sources) !== count(array_unique($sources))) continue;
				foreach($sources as $name => $table) {
					foreach($this->visibleColumns($table) as $col) {
						$list[] = (count($sources) > 1 ? $this->quoteId($name) . '.' : '') . $this->quoteId($col);
					}
				}
			}
			if(!count($list)) continue;
			$tokens = array_merge(array_slice($tokens, 0, $from), [['word', implode(', ', $list)]], array_slice($tokens, $to + 1));
		}
		return $tokens;
	}

	/**
	 * Get statements for the folded companion index of a primary key on text columns (none when there is none)
	 *
	 * @param string $table
	 * @param array $cols
	 * @param array $columnTypes
	 * @return array
	 *
	 */
	protected function primaryFoldIndexSql($table, array $cols, array $columnTypes) {
		if(!$this->foldAvailable) return [];
		$statements = $this->createIndexSql($table, 'primary', $cols, [], true, false, false, $columnTypes);
		if(count($statements) < 2) return [];
		$comment = $this->indexCommentSql($table, 'primary' . self::foldIndexSuffix);
		return $comment === '' ? [$statements[1]] : [$statements[1], $comment];
	}

	/**
	 * Get column types of a table for index definitions, from the database or the schema cache
	 *
	 * @param string $table
	 * @return array [ column => [ 'pgType' => ..., 'isText' => bool ] ]
	 *
	 */
	protected function indexColumnTypes($table) {
		$types = $this->getColumnTypes($table);
		if(count($types)) return $types;
		$schema = $this->tableSchema($table);
		foreach($schema['columns'] as $name => $pgType) {
			$types[$name] = ['pgType' => $pgType, 'isText' => $this->typeClass($pgType) === 'text'];
		}
		return $types;
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
			} else if($this->isWord($first, 'CONSTRAINT') && $this->constraintKind($def) === 'UNIQUE') {
				// CONSTRAINT name UNIQUE [KEY] (cols): a unique index, as MySQL makes it
				$indexDefs[] = $this->constraintAsIndexDef($def);
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

		if(count($primaryCols)) {
			foreach($this->primaryFoldIndexSql($table, $primaryCols, $columns) as $sql) $statements[] = $sql;
		}
		foreach($indexDefs as $def) {
			foreach($this->indexDef($table, $def, $columns, $ifNotExists) as $sql) $statements[] = $sql;
		}
		foreach($columns as $name => $col) {
			if($col['pgType'] === 'jsonb') foreach($this->jsonIndexSql($table, $name) as $sql) $statements[] = $sql;
			if($col['onUpdate']) foreach($this->onUpdateSql($table, $name) as $sql) $statements[] = $sql;
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
		$statements = $this->indexDef($table, $def, $this->indexColumnTypes($table), $ifNotExists);
		return count($statements) ? $statements : 'SELECT 1';
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
		return $this->dropIndexStatements($table, $index);
	}

	/**
	 * Get statements that drop an index and its folded companion (see createIndexSql())
	 *
	 * @param string $table
	 * @param string $index Index name without table prefix
	 * @return array
	 *
	 */
	protected function dropIndexStatements($table, $index) {
		$statements = ['DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $index))];
		if($this->foldAvailable) {
			$statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $index . self::foldIndexSuffix));
		}
		if($this->fulltextAvailable) {
			$ftsName = $this->indexName($table, $index . self::fulltextIndexSuffix);
			$statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($ftsName);
			// and its stored vector columns, unless another index uses them
			$defs = $this->pdo() ? $this->catalogRows(
				"SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?", [$table]
			) : [];
			$vectors = [];
			foreach($defs as $row) {
				if($row['indexname'] !== $ftsName) continue;
				if(preg_match_all('/\b([A-Za-z0-9_$]+' . self::vectorColumnSuffix . ')\b/', $row['indexdef'], $m)) $vectors = $m[1];
			}
			foreach($vectors as $vector) {
				$used = false;
				foreach($defs as $row) {
					if($row['indexname'] !== $ftsName && preg_match('/\b' . preg_quote($vector, '/') . '\b/', $row['indexdef'])) $used = true;
				}
				if(!$used) $statements[] = 'ALTER TABLE ' . $this->quoteId($table) . ' DROP COLUMN IF EXISTS ' . $this->quoteId($vector);
			}
			if(count($vectors)) {
				$this->clearSchemaCache($table);
				$this->cache = [];
			}
		}
		return $statements;
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
				$comment = $this->indexCommentSql($to, $index['name']);
				if($comment !== '') $statements[] = $comment;
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
					if($columnTypes === null) $columnTypes = $this->indexColumnTypes($table);
					foreach($this->indexDef($table, $rest, $columnTypes) as $sql) $statements[] = $sql;
				} else if($w2 === 'PRIMARY') {
					list($cols) = $this->indexColumns($rest);
					$statements[] = "ALTER TABLE $qTable ADD PRIMARY KEY (" . implode(', ', array_map([$this, 'quoteId'], $cols)) . ')';
					if($columnTypes === null) $columnTypes = $this->indexColumnTypes($table);
					foreach($this->primaryFoldIndexSql($table, $cols, $columnTypes) as $sql) $statements[] = $sql;
				} else if($w2 === 'CONSTRAINT' || $w2 === 'FOREIGN' || $w2 === 'CHECK') {
					$kind = $w2 === 'CONSTRAINT' ? $this->constraintKind($rest) : $w2;
					if($kind === 'UNIQUE') {
						// a unique key, as MySQL makes it
						if($columnTypes === null) $columnTypes = $this->getColumnTypes($table);
						foreach($this->indexDef($table, $this->constraintAsIndexDef($rest), $columnTypes) as $sql) $statements[] = $sql;
					} else if($kind === 'PRIMARY') {
						list($cols) = $this->indexColumns($rest);
						$statements[] = "ALTER TABLE $qTable ADD PRIMARY KEY (" . implode(', ', array_map([$this, 'quoteId'], $cols)) . ')';
					} else if($kind === 'FOREIGN' || $kind === 'CHECK') {
						// the same syntax in PostgreSQL, with identifiers and expressions translated
						$statements[] = "ALTER TABLE $qTable ADD " . trim($this->join($this->expressions($this->trimTokens($rest))));
					} else {
						throw new \PDOException("PostgreSQL translator: unsupported ALTER TABLE for $table: " . $this->join($spec));
					}
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
						if($col['pgType'] === 'jsonb') foreach($this->jsonIndexSql($table, $col['name']) as $sql) $statements[] = $sql;
						if($col['onUpdate']) foreach($this->onUpdateSql($table, $col['name']) as $sql) $statements[] = $sql;
					}
				}

			} else if($w === 'DROP') {
				if($w2 === 'INDEX' || $w2 === 'KEY') {
					$k = $this->next($rest, $j + 1);
					foreach($this->dropIndexStatements($table, $this->name($rest[$k])) as $sql) $statements[] = $sql;
				} else if($w2 === 'FOREIGN' || $w2 === 'CHECK' || $w2 === 'CONSTRAINT') {
					// DROP FOREIGN KEY name, DROP CHECK name, DROP CONSTRAINT name
					$k = $this->next($rest, $j + 1);
					if($w2 === 'FOREIGN' && $k > -1 && $this->isWord($rest[$k], 'KEY')) $k = $this->next($rest, $k + 1);
					$constraint = $this->name($rest[$k]);
					$statements[] = "ALTER TABLE $qTable DROP CONSTRAINT IF EXISTS " . $this->quoteId($constraint);
					// MySQL 8's DROP CONSTRAINT also drops a unique key, which is an index here
					if($w2 === 'CONSTRAINT') foreach($this->dropIndexStatements($table, $constraint) as $sql) $statements[] = $sql;
				} else if($w2 === 'PRIMARY') {
					$statements[] = "ALTER TABLE $qTable DROP CONSTRAINT IF EXISTS " . $this->quoteId("{$table}_pkey");
					if($this->foldAvailable) $statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, 'primary' . self::foldIndexSuffix));
				} else {
					if($w2 === 'COLUMN') $j = $this->next($rest, $j + 1);
					$dropCol = $this->name($rest[$j]);
					foreach($this->vectorColumnChanges($table, $dropCol, null, true)['before'] as $sql) $statements[] = $sql;
					$statements[] = "ALTER TABLE $qTable DROP COLUMN " . $this->quoteId($dropCol);
					foreach($this->onUpdateSql($table, $dropCol, false) as $sql) $statements[] = $sql;
				}

			} else if($w === 'RENAME') {
				if($w2 === 'COLUMN') {
					$parts = array_values(array_filter(array_slice($rest, $j + 1), function($t) { return $t[0] !== 'ws'; }));
					$statements[] = "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($this->name($parts[0])) . ' TO ' . $this->quoteId($this->name($parts[2]));
					$statements[] = $this->onUpdateRenameSql($table, $this->name($parts[0]), $this->name($parts[2]));
					foreach($this->vectorColumnChanges($table, $this->name($parts[0]), $this->name($parts[2]), false)['after'] as $sql) $statements[] = $sql;
				} else if($w2 === 'INDEX' || $w2 === 'KEY') {
					$parts = array_values(array_filter(array_slice($rest, $j + 1), function($t) { return $t[0] !== 'ws'; }));
					$from = $this->name($parts[0]);
					$to = $this->name($parts[2]);
					$statements[] = 'ALTER INDEX ' . $this->quoteId($this->indexName($table, $from)) . ' RENAME TO ' . $this->quoteId($this->indexName($table, $to));
					$comment = $this->indexCommentSql($table, $to);
					if($comment !== '') $statements[] = $comment;
					if($this->foldAvailable) {
						$statements[] = 'ALTER INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $from . self::foldIndexSuffix)) .
							' RENAME TO ' . $this->quoteId($this->indexName($table, $to . self::foldIndexSuffix));
						$comment = $this->indexCommentSql($table, $to . self::foldIndexSuffix);
						if($comment !== '') {
							// only a unique key has a companion, and COMMENT ON has no IF EXISTS
							$regclass = str_replace("'", "''", $this->quoteId($this->indexName($table, $to . self::foldIndexSuffix)));
							$statements[] = "DO \$pw\$ BEGIN IF to_regclass('$regclass') IS NOT NULL THEN EXECUTE " .
								"'" . str_replace("'", "''", $comment) . "'; END IF; END \$pw\$";
						}
					}
					if($this->fulltextAvailable) {
						$statements[] = 'ALTER INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $from . self::fulltextIndexSuffix)) .
							' RENAME TO ' . $this->quoteId($this->indexName($table, $to . self::fulltextIndexSuffix));
					}
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
				// a jsonb column's GIN index cannot index another type, so it goes first (and comes back for jsonb)
				$oldSchema = $this->tableSchema($table);
				if(isset($oldSchema['columns'][$oldName]) && $oldSchema['columns'][$oldName] === 'jsonb') {
					$statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $oldName . self::jsonIndexSuffix));
					$statements[] = 'DROP INDEX IF EXISTS ' . $this->quoteId($this->indexName($table, $oldName . self::jsonNestedIndexSuffix));
				}
				$vectorChanges = $this->vectorColumnChanges($table, $oldName, $col['name'], true);
				foreach($vectorChanges['before'] as $sql) $statements[] = $sql;
				if($col['name'] !== $oldName) {
					$statements[] = "ALTER TABLE $qTable RENAME COLUMN " . $this->quoteId($oldName) . ' TO ' . $this->quoteId($col['name']);
				}
				$qCol = $this->quoteId($col['name']);
				// MySQL's MODIFY redefines the column: an unspecified NULL means nullable and an unspecified
				// DEFAULT means none, except for key and AUTO_INCREMENT columns, which stay NOT NULL
				$schema = $this->tableSchema($table);
				$oldType = isset($schema['columns'][$oldName]) ? $schema['columns'][$oldName] : null;
				$identity = $col['autoIncrement'] || in_array($schema['identity'], [$oldName, $col['name']], true);
				$primary = $col['primary'] || in_array($oldName, $schema['primary'], true);
				if($oldType !== null && $this->typeClass($oldType) === 'text' && $this->typeClass($col['pgType']) === 'number') {
					// text to number: convert as MySQL does ('' and 'abc' become 0, '12abc' is 12) rather than fail
					$using = self::mysqlNumberSql("$qCol::text") . "::$col[pgType]";
				} else {
					$using = "$qCol::$col[pgType]";
				}
				// the old default goes first, since it may not convert to the new type (i.e. '' to integer)
				$actions = $identity ? [] : ["ALTER COLUMN $qCol DROP DEFAULT"];
				$actions[] = "ALTER COLUMN $qCol TYPE $col[pgType] USING $using";
				if($col['nullSpec'] === 'NOT NULL') {
					$actions[] = "ALTER COLUMN $qCol SET NOT NULL";
				} else if(!$primary && !$identity) {
					$actions[] = "ALTER COLUMN $qCol DROP NOT NULL";
				}
				if($col['default'] !== null) $actions[] = "ALTER COLUMN $qCol SET DEFAULT $col[default]";
				$statements[] = "ALTER TABLE $qTable " . implode(', ', $actions);
				if($col['pgType'] === 'jsonb') foreach($this->jsonIndexSql($table, $col['name']) as $sql) $statements[] = $sql;
				// MODIFY redefines ON UPDATE too: keep, add or remove the trigger (under the new name after CHANGE)
				if($col['name'] !== $oldName || !$col['onUpdate']) {
					foreach($this->onUpdateSql($table, $oldName, false) as $sql) $statements[] = $sql;
				}
				if($col['onUpdate']) foreach($this->onUpdateSql($table, $col['name'], true, true) as $sql) $statements[] = $sql;
				foreach($vectorChanges['after'] as $sql) $statements[] = $sql;

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
