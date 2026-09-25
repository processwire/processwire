<?php namespace ProcessWire;

/**
 * ProcessWire database schema replay
 *
 * Replays a sequence of MySQL schema statements (CREATE, ALTER, DROP and RENAME of tables and indexes),
 * such as the entries of the schema log (see WireDatabaseSchemaLog), into the current MySQL definition
 * of each table, as a single CREATE TABLE statement per table. No database is needed: statements are
 * applied to an in-memory model of each table's columns, keys and options.
 *
 * Only the forms of these statements that ProcessWire and common modules use are understood. When a
 * statement can't be applied with certainty, the table it affects is marked as failed rather than
 * guessed at, and is left out of getCreateTables(), so that the caller can fall back to another way of
 * getting that table's definition.
 *
 * This class has no dependencies on the rest of ProcessWire.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 * @since 3.0.273
 *
 */

class WireDatabaseSchemaReplay {

	/**
	 * Tables, indexed by lowercase name
	 *
	 * Each [ 'name' => string, 'columns' => [ lowercase name => [name, definition] ], 'keys' => [ lowercase name => [name, definition] ], 'options' => string ]
	 *
	 * @var array
	 *
	 */
	protected $tables = [];

	/**
	 * Tables that could not be replayed with certainty [ lowercase name => reason ]
	 *
	 * @var array
	 *
	 */
	protected $failed = [];

	/**
	 * Apply a schema statement
	 *
	 * @param string $sql
	 * @return bool False if the statement could not be applied with certainty
	 *
	 */
	public function apply($sql) {
		$sql = trim(rtrim(trim($sql), ';'));
		if(preg_match('/^CREATE\s+(TEMPORARY\s+)?TABLE\s+(IF\s+NOT\s+EXISTS\s+)?/i', $sql, $m)) {
			if(!empty($m[1])) return true; // temporary tables are not part of the schema
			return $this->create(substr($sql, strlen($m[0])), !empty($m[2]));
		}
		if(preg_match('/^ALTER\s+TABLE\s+/i', $sql, $m)) {
			return $this->alter(substr($sql, strlen($m[0])));
		}
		if(preg_match('/^DROP\s+TABLE\s+(IF\s+EXISTS\s+)?(.+)$/is', $sql, $m)) {
			foreach($this->splitTopLevel($m[2]) as $item) {
				$name = $this->unquote(trim($item));
				unset($this->tables[strtolower($name)], $this->failed[strtolower($name)]);
			}
			return true;
		}
		if(preg_match('/^RENAME\s+TABLE\s+(.+)$/is', $sql, $m)) {
			foreach($this->splitTopLevel($m[1]) as $pair) {
				if(!preg_match('/^(\S+)\s+TO\s+(\S+)$/i', trim($pair), $p)) return $this->fail('', "unrecognized RENAME TABLE: $sql");
				$this->renameTable($this->unquote($p[1]), $this->unquote($p[2]));
			}
			return true;
		}
		if(preg_match('/^CREATE\s+(UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX\s+(\S+)\s+ON\s+(\S+)\s*(\(.*\))\s*$/is', $sql, $m)) {
			$table = $this->unquote($m[3]);
			if(!$this->has($table)) return $this->fail($table, 'CREATE INDEX on an unknown table');
			$type = strtoupper(trim($m[1]));
			$name = $this->unquote($m[2]);
			$this->setKey($table, $name, trim(($type ? "$type " : '') . 'KEY ' . $this->quote($name) . ' ' . trim($m[4])));
			return true;
		}
		if(preg_match('/^DROP\s+INDEX\s+(\S+)\s+ON\s+(\S+)\s*$/i', $sql, $m)) {
			$table = $this->unquote($m[2]);
			if(!$this->has($table)) return $this->fail($table, 'DROP INDEX on an unknown table');
			unset($this->tables[strtolower($table)]['keys'][strtolower($this->unquote($m[1]))]);
			return true;
		}
		return $this->fail('', "unrecognized statement: " . substr($sql, 0, 60));
	}

	/**
	 * Get the current CREATE TABLE statement of every table that was replayed with certainty
	 *
	 * @return array [ table => CREATE TABLE statement ]
	 *
	 */
	public function getCreateTables() {
		$creates = [];
		foreach($this->tables as $key => $table) {
			if(isset($this->failed[$key])) continue;
			$lines = [];
			foreach($table['columns'] as $column) $lines[] = $column[1];
			foreach($table['keys'] as $k) $lines[] = $k[1];
			$creates[$table['name']] = "CREATE TABLE " . $this->quote($table['name']) . " (\n  " .
				implode(",\n  ", $lines) . "\n)" . ($table['options'] !== '' ? ' ' . $table['options'] : '');
		}
		return $creates;
	}

	/**
	 * Get the column names of every table that was replayed with certainty
	 *
	 * @return array [ table => [ column, ... ] ]
	 *
	 */
	public function getColumnNames() {
		$names = [];
		foreach($this->tables as $key => $table) {
			if(isset($this->failed[$key])) continue;
			$names[$table['name']] = [];
			foreach($table['columns'] as $column) $names[$table['name']][] = $column[0];
		}
		return $names;
	}

	/**
	 * Add prefix lengths to indexes of text and blob columns that have none
	 *
	 * MySQL requires a prefix length when indexing a TEXT or BLOB column (error 1170). A CREATE TABLE
	 * rebuilt from a database that has no such requirement (i.e. SQLite) may lack them. FULLTEXT keys
	 * are left alone, since they take no prefix length.
	 *
	 * @param string $createTable A single CREATE TABLE statement
	 * @param int $length Prefix length to add
	 * @return string The statement, with prefix lengths added where needed
	 *
	 */
	public static function addIndexPrefixLengths($createTable, $length = 191) {
		$replay = new self();
		if(!$replay->apply($createTable)) return $createTable;
		$tables = $replay->tables;
		$table = reset($tables);
		if(!$table) return $createTable;
		$textColumns = [];
		foreach($table['columns'] as $key => $column) {
			$type = strtolower($replay->firstWord(trim(substr($column[1], strlen($replay->firstWord($column[1]))))));
			if(preg_match('/^(tiny|medium|long)?(text|blob)$/', $type)) $textColumns[$key] = true;
		}
		if(!count($textColumns)) return $createTable;
		$changed = false;
		foreach($table['keys'] as $k => $v) {
			if(preg_match('/^(FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN|CHECK)\b/i', $v[1])) continue;
			$open = $replay->keyColumnsStart($v[1]);
			if($open === false) continue;
			$close = $replay->matchParen($v[1], $open);
			if($close === false) continue;
			$items = $replay->splitTopLevel(substr($v[1], $open + 1, $close - $open - 1));
			foreach($items as $i => $item) {
				$item = trim($item);
				$name = strtolower($replay->unquote($replay->firstWord($item)));
				if(!isset($textColumns[$name]) || strpos($item, '(') !== false) continue;
				$items[$i] = $replay->quote($name) . "($length)" . substr($item, strlen($replay->firstWord($item)));
				$changed = true;
			}
			$table['keys'][$k][1] = substr($v[1], 0, $open + 1) . implode(',', array_map('trim', $items)) . substr($v[1], $close);
		}
		if(!$changed) return $createTable;
		$replay->tables = [strtolower($table['name']) => $table];
		$creates = $replay->getCreateTables();
		return reset($creates);
	}

	/**
	 * Get the tables that could not be replayed with certainty
	 *
	 * @return array [ table => reason ]
	 *
	 */
	public function getFailedTables() {
		$failed = [];
		foreach($this->failed as $key => $reason) {
			$name = isset($this->tables[$key]) ? $this->tables[$key]['name'] : $key;
			$failed[$name] = $reason;
		}
		return $failed;
	}

	/**
	 * CREATE TABLE (after "CREATE TABLE [IF NOT EXISTS] ")
	 *
	 * @param string $sql
	 * @param bool $ifNotExists
	 * @return bool
	 *
	 */
	protected function create($sql, $ifNotExists) {
		$open = strpos($sql, '(');
		if($open === false) return $this->fail('', 'CREATE TABLE without a definition');
		$name = $this->unquote(trim(substr($sql, 0, $open)));
		if(preg_match('/\s/', $name)) return $this->fail($name, 'CREATE TABLE ... LIKE/SELECT is not supported');
		$close = $this->matchParen($sql, $open);
		if($close === false) return $this->fail($name, 'unbalanced CREATE TABLE');
		if($ifNotExists && $this->has($name)) return true;
		$key = strtolower($name);
		unset($this->failed[$key]);
		$this->tables[$key] = ['name' => $name, 'columns' => [], 'keys' => [], 'options' => trim(substr($sql, $close + 1))];
		foreach($this->splitTopLevel(substr($sql, $open + 1, $close - $open - 1)) as $part) {
			$part = trim($part);
			if($part === '') continue;
			if($this->isKeyDefinition($part)) {
				$keyName = $this->keyName($name, $part);
				if($keyName === '') return $this->fail($name, "unrecognized key: $part");
				$this->setKey($name, $keyName, $part);
			} else {
				$column = $this->unquote($this->firstWord($part));
				$this->tables[$key]['columns'][strtolower($column)] = [$column, $part];
			}
		}
		return true;
	}

	/**
	 * ALTER TABLE (after "ALTER TABLE ")
	 *
	 * @param string $sql
	 * @return bool
	 *
	 */
	protected function alter($sql) {
		$name = $this->unquote($this->firstWord($sql));
		$specs = trim(substr($sql, strlen($this->firstWord($sql))));
		if(!$this->has($name)) return $this->fail($name, 'ALTER TABLE on an unknown table');
		foreach($this->splitTopLevel($specs) as $spec) {
			$spec = trim($spec);
			if($spec === '') continue;
			if(!$this->alterSpec($name, $spec)) return $this->fail($name, "unsupported ALTER TABLE: $spec");
			// RENAME TO changes the name that later specs of the same statement refer to
			if(preg_match('/^RENAME\s+(?:TO\s+|AS\s+)?(\S+)$/i', $spec, $m)) $name = $this->unquote($m[1]);
		}
		return true;
	}

	/**
	 * Apply one specification of an ALTER TABLE
	 *
	 * @param string $table
	 * @param string $spec
	 * @return bool
	 *
	 */
	protected function alterSpec($table, $spec) {
		$key = strtolower($table);
		$t = &$this->tables[$key];

		if(preg_match('/^ADD\s+(COLUMN\s+)?(.+)$/is', $spec, $m)) {
			$def = trim($m[2]);
			if(empty($m[1]) && $this->isKeyDefinition($def)) {
				$keyName = $this->keyName($table, $def);
				if($keyName === '') return false;
				$this->setKey($table, $keyName, $def);
				return true;
			}
			if(strpos($def, '(') === 0) return false; // ADD (col, col, ...)
			list($def, $position) = $this->splitPosition($def);
			$column = $this->unquote($this->firstWord($def));
			$this->setColumn($table, strtolower($column), [$column, $def], $position);
			return true;
		}

		if(preg_match('/^MODIFY\s+(?:COLUMN\s+)?(.+)$/is', $spec, $m)) {
			list($def, $position) = $this->splitPosition(trim($m[1]));
			$column = $this->unquote($this->firstWord($def));
			if(!isset($t['columns'][strtolower($column)])) return false;
			$this->setColumn($table, strtolower($column), [$column, $def], $position, true);
			return true;
		}

		if(preg_match('/^CHANGE\s+(?:COLUMN\s+)?(\S+)\s+(.+)$/is', $spec, $m)) {
			$old = $this->unquote($m[1]);
			if(!isset($t['columns'][strtolower($old)])) return false;
			list($def, $position) = $this->splitPosition(trim($m[2]));
			$new = $this->unquote($this->firstWord($def));
			$this->setColumn($table, strtolower($old), [$new, $def], $position, true, strtolower($new));
			$this->renameColumnInKeys($table, $old, $new);
			return true;
		}

		if(preg_match('/^RENAME\s+COLUMN\s+(\S+)\s+TO\s+(\S+)$/i', $spec, $m)) {
			$old = $this->unquote($m[1]);
			$new = $this->unquote($m[2]);
			$oldKey = strtolower($old);
			if(!isset($t['columns'][$oldKey])) return false;
			$def = $this->quote($new) . substr($t['columns'][$oldKey][1], strlen($this->firstWord($t['columns'][$oldKey][1])));
			$this->setColumn($table, $oldKey, [$new, $def], '', true, strtolower($new));
			$this->renameColumnInKeys($table, $old, $new);
			return true;
		}

		if(preg_match('/^RENAME\s+(INDEX|KEY)\s+(\S+)\s+TO\s+(\S+)$/i', $spec, $m)) {
			$old = strtolower($this->unquote($m[2]));
			$new = $this->unquote($m[3]);
			if(!isset($t['keys'][$old])) return false;
			$def = preg_replace('/(KEY|INDEX)\s+`?' . preg_quote($t['keys'][$old][0], '/') . '`?/i', '$1 ' . $this->quote($new), $t['keys'][$old][1], 1);
			$keys = [];
			foreach($t['keys'] as $k => $v) $keys[$k === $old ? strtolower($new) : $k] = $k === $old ? [$new, $def] : $v;
			$t['keys'] = $keys;
			return true;
		}

		if(preg_match('/^RENAME\s+(?:TO\s+|AS\s+)?(\S+)$/i', $spec, $m)) {
			$this->renameTable($table, $this->unquote($m[1]));
			return true;
		}

		if(preg_match('/^DROP\s+PRIMARY\s+KEY$/i', $spec)) {
			unset($t['keys']['primary']);
			return true;
		}

		if(preg_match('/^DROP\s+(INDEX|KEY|FOREIGN\s+KEY|CHECK|CONSTRAINT)\s+(\S+)$/i', $spec, $m)) {
			unset($t['keys'][strtolower($this->unquote($m[2]))]);
			return true;
		}

		if(preg_match('/^DROP\s+(?:COLUMN\s+)?(\S+)$/i', $spec, $m)) {
			$column = strtolower($this->unquote($m[1]));
			if(!isset($t['columns'][$column])) return false;
			unset($t['columns'][$column]);
			// MySQL removes the column from its indexes, and drops an index that is left with no columns
			foreach($t['keys'] as $k => $v) {
				$columns = $this->keyColumns($v[1]);
				if($columns === null) return false;
				if(!in_array($column, array_map('strtolower', $columns), true)) continue;
				if(count($columns) > 1) return false; // removing one column of several: not modeled
				unset($t['keys'][$k]);
			}
			return true;
		}

		if(preg_match('/^(ENGINE|(DEFAULT\s+)?(CHARSET|CHARACTER\s+SET|COLLATE)|AUTO_INCREMENT|COMMENT|ROW_FORMAT)\s*=?\s*\S+/i', $spec)) {
			// table options: update or add the option in the table's options
			$optionName = strtoupper(preg_replace('/\s*=.*$|\s+\S+$/s', '', $spec));
			$pattern = '/(^|\s)' . preg_quote($optionName, '/') . '\s*=?\s*\S+/i';
			if(preg_match($pattern, $t['options'])) {
				$t['options'] = trim(preg_replace($pattern, '$1' . $spec, $t['options'], 1));
			} else {
				$t['options'] = trim($t['options'] . ' ' . $spec);
			}
			return true;
		}

		return false; // i.e. ALTER COLUMN ... SET DEFAULT, CONVERT TO, partitioning
	}

	/**
	 * Mark a table as not replayable with certainty
	 *
	 * @param string $table Blank if the statement's table is not known
	 * @param string $reason
	 * @return bool Always false
	 *
	 */
	protected function fail($table, $reason) {
		$key = strtolower($table === '' ? '*' : $table);
		if(!isset($this->failed[$key])) $this->failed[$key] = $reason;
		return false;
	}

	/**
	 * @param string $table
	 * @return bool
	 *
	 */
	protected function has($table) {
		return isset($this->tables[strtolower($table)]);
	}

	/**
	 * @param string $from
	 * @param string $to
	 *
	 */
	protected function renameTable($from, $to) {
		$fromKey = strtolower($from);
		$toKey = strtolower($to);
		if(!isset($this->tables[$fromKey])) {
			$this->fail($to, "RENAME of unknown table $from");
			return;
		}
		$this->tables[$toKey] = $this->tables[$fromKey];
		$this->tables[$toKey]['name'] = $to;
		if($fromKey !== $toKey) unset($this->tables[$fromKey]);
		if(isset($this->failed[$fromKey])) {
			$this->failed[$toKey] = $this->failed[$fromKey];
			if($fromKey !== $toKey) unset($this->failed[$fromKey]);
		}
	}

	/**
	 * Add or replace a column, honoring FIRST / AFTER
	 *
	 * @param string $table
	 * @param string $key Lowercase name of the column to add or replace
	 * @param array $column [name, definition]
	 * @param string $position Blank, 'FIRST' or 'AFTER name'
	 * @param bool $replace Replace the existing column (keeping its position unless one is given)
	 * @param string $newKey Lowercase new name when the column is renamed
	 *
	 */
	protected function setColumn($table, $key, array $column, $position = '', $replace = false, $newKey = '') {
		$t = &$this->tables[strtolower($table)];
		$newKey = $newKey !== '' ? $newKey : $key;
		$columns = [];
		if($replace && $position === '') {
			foreach($t['columns'] as $k => $v) $columns[$k === $key ? $newKey : $k] = $k === $key ? $column : $v;
			$t['columns'] = $columns;
			return;
		}
		unset($t['columns'][$key]);
		if($position === '') {
			$t['columns'][$newKey] = $column;
			return;
		}
		if(strtoupper($position) === 'FIRST') {
			$t['columns'] = array_merge([$newKey => $column], $t['columns']);
			return;
		}
		$after = strtolower($this->unquote(trim(substr($position, 5))));
		foreach($t['columns'] as $k => $v) {
			$columns[$k] = $v;
			if($k === $after) $columns[$newKey] = $column;
		}
		if(!isset($columns[$newKey])) $columns[$newKey] = $column;
		$t['columns'] = $columns;
	}

	/**
	 * @param string $table
	 * @param string $name
	 * @param string $definition
	 *
	 */
	protected function setKey($table, $name, $definition) {
		$re = '/^((?:UNIQUE|FULLTEXT|SPATIAL)?\s*(?:KEY|INDEX)?)\s*(`[^`]+`|[A-Za-z0-9_$]+)?\s*\(/i';
		if($name !== 'PRIMARY' && preg_match($re, $definition, $m)) {
			$typed = !empty($m[2]) && preg_match('/^(KEY|INDEX)$/i', $m[2]);
			if(empty($m[2]) || $typed) {
				// write the name MySQL gave an unnamed key, so it keeps it if its first column is renamed later
				$prefix = trim($m[1] . ($typed ? $m[2] : ''));
				if(!preg_match('/\b(KEY|INDEX)$/i', $prefix)) $prefix = trim("$prefix KEY");
				$definition = "$prefix " . $this->quote($name) . ' ' . substr($definition, strlen($m[0]) - 1);
			}
		}
		$this->tables[strtolower($table)]['keys'][strtolower($name)] = [$name, $definition];
	}

	/**
	 * Rename a column in the column lists of a table's keys
	 *
	 * @param string $table
	 * @param string $old
	 * @param string $new
	 *
	 */
	protected function renameColumnInKeys($table, $old, $new) {
		$t = &$this->tables[strtolower($table)];
		foreach($t['keys'] as $k => $v) {
			$open = strrpos($v[1], '(') !== false ? $this->keyColumnsStart($v[1]) : false;
			if($open === false) continue;
			$close = $this->matchParen($v[1], $open);
			if($close === false) continue;
			$list = substr($v[1], $open + 1, $close - $open - 1);
			$list = preg_replace('/(^|,)\s*`?' . preg_quote($old, '/') . '`?(?=\s*(\(|,|$|\s))/i', '$1' . $this->quote($new), $list);
			$t['keys'][$k][1] = substr($v[1], 0, $open + 1) . $list . substr($v[1], $close);
		}
	}

	/**
	 * Does the given CREATE TABLE part define a key or constraint rather than a column?
	 *
	 * @param string $part
	 * @return bool
	 *
	 */
	protected function isKeyDefinition($part) {
		return (bool) preg_match('/^(PRIMARY\s+KEY|UNIQUE|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN\s+KEY|CHECK)\b/i', $part);
	}

	/**
	 * Get the name of a key definition, as MySQL names it
	 *
	 * @param string $table
	 * @param string $definition
	 * @return string Blank if not recognized
	 *
	 */
	protected function keyName($table, $definition) {
		if(preg_match('/^PRIMARY\s+KEY/i', $definition)) return 'PRIMARY';
		if(preg_match('/^CONSTRAINT\s+(`[^`]+`|[A-Za-z0-9_$]+)/i', $definition, $m)) return $this->unquote($m[1]);
		if(preg_match('/^(?:UNIQUE|FULLTEXT|SPATIAL)?\s*(?:KEY|INDEX)?\s*(`[^`]+`|[A-Za-z0-9_$]+)?\s*\(/i', $definition, $m)) {
			if(!empty($m[1]) && !preg_match('/^(KEY|INDEX)$/i', $m[1])) return $this->unquote($m[1]);
			// unnamed: MySQL names it after its first column, adding _2, _3... when that name is taken
			$columns = $this->keyColumns($definition);
			if(!$columns) return '';
			$name = $columns[0];
			$keys = $this->tables[strtolower($table)]['keys'];
			for($n = 2; isset($keys[strtolower($name)]); $n++) $name = $columns[0] . "_$n";
			return $name;
		}
		return '';
	}

	/**
	 * Get the position of the opening parenthesis of a key's column list
	 *
	 * @param string $definition
	 * @return int|false
	 *
	 */
	protected function keyColumnsStart($definition) {
		if(!preg_match('/^(?:PRIMARY\s+KEY|UNIQUE|FULLTEXT|SPATIAL|KEY|INDEX|CONSTRAINT\s+\S+\s+(?:PRIMARY\s+KEY|UNIQUE|FOREIGN\s+KEY)|FOREIGN\s+KEY)?[^(]*\(/i', $definition, $m)) return false;
		return strlen($m[0]) - 1;
	}

	/**
	 * Get the column names of a key definition
	 *
	 * @param string $definition
	 * @return array|null Null if not recognized
	 *
	 */
	protected function keyColumns($definition) {
		$open = $this->keyColumnsStart($definition);
		if($open === false) return null;
		$close = $this->matchParen($definition, $open);
		if($close === false) return null;
		$columns = [];
		foreach($this->splitTopLevel(substr($definition, $open + 1, $close - $open - 1)) as $item) {
			$columns[] = $this->unquote($this->firstWord(trim($item)));
		}
		return $columns;
	}

	/**
	 * Split a trailing FIRST or AFTER clause from a column definition
	 *
	 * @param string $def
	 * @return array [definition, position]
	 *
	 */
	protected function splitPosition($def) {
		if(preg_match('/\s+(FIRST|AFTER\s+(`[^`]+`|[A-Za-z0-9_$]+))\s*$/i', $def, $m)) {
			return [trim(substr($def, 0, -strlen($m[0]))), $m[1]];
		}
		return [$def, ''];
	}

	/**
	 * Get the first word of a string: a backtick-quoted identifier or a run of non-space characters
	 *
	 * @param string $s
	 * @return string
	 *
	 */
	protected function firstWord($s) {
		$s = ltrim($s);
		if(strpos($s, '`') === 0) {
			$end = strpos($s, '`', 1);
			return $end === false ? $s : substr($s, 0, $end + 1);
		}
		return preg_match('/^[^\s(,]+/', $s, $m) ? $m[0] : '';
	}

	/**
	 * Remove identifier quotes (and a database prefix)
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function unquote($name) {
		$name = trim($name);
		if(preg_match('/^(?:`[^`]+`|[A-Za-z0-9_$]+)\.(`[^`]+`|[A-Za-z0-9_$]+)$/', $name, $m)) $name = $m[1];
		return str_replace('`', '', $name);
	}

	/**
	 * @param string $name
	 * @return string
	 *
	 */
	protected function quote($name) {
		return '`' . str_replace('`', '', $name) . '`';
	}

	/**
	 * Find the parenthesis that closes the one at $open, ignoring any within quotes
	 *
	 * @param string $s
	 * @param int $open
	 * @return int|false
	 *
	 */
	protected function matchParen($s, $open) {
		$depth = 0;
		$quote = '';
		$len = strlen($s);
		for($i = $open; $i < $len; $i++) {
			$c = $s[$i];
			if($quote !== '') {
				if($c === '\\' && $quote !== '`') {
					$i++;
				} else if($c === $quote) {
					$quote = '';
				}
			} else if($c === "'" || $c === '"' || $c === '`') {
				$quote = $c;
			} else if($c === '(') {
				$depth++;
			} else if($c === ')') {
				$depth--;
				if($depth === 0) return $i;
			}
		}
		return false;
	}

	/**
	 * Split on commas that are not within parentheses or quotes
	 *
	 * @param string $s
	 * @return array
	 *
	 */
	protected function splitTopLevel($s) {
		$parts = [];
		$depth = 0;
		$quote = '';
		$start = 0;
		$len = strlen($s);
		for($i = 0; $i < $len; $i++) {
			$c = $s[$i];
			if($quote !== '') {
				if($c === '\\' && $quote !== '`') {
					$i++;
				} else if($c === $quote) {
					$quote = '';
				}
			} else if($c === "'" || $c === '"' || $c === '`') {
				$quote = $c;
			} else if($c === '(') {
				$depth++;
			} else if($c === ')') {
				$depth--;
			} else if($c === ',' && $depth === 0) {
				$parts[] = substr($s, $start, $i - $start);
				$start = $i + 1;
			}
		}
		$parts[] = substr($s, $start);
		return $parts;
	}
}
