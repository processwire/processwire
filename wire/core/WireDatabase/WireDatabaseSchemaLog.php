<?php namespace ProcessWire;

/**
 * ProcessWire database schema log
 *
 * Records every change to the database schema (CREATE, ALTER, DROP and RENAME of tables and indexes)
 * in the `schema_log` table, in MySQL syntax on every database type. On SQLite and PostgreSQL that is
 * the SQL as ProcessWire issued it, before translation, which keeps what translation loses (column
 * types, ENUM values, UNSIGNED, index prefix lengths). Replaying the log on an empty MySQL database
 * reproduces the site's schema, which is what makes converting a site back to MySQL possible.
 *
 * When the log is first used it records the CREATE TABLE of every existing table as a baseline,
 * so the log is always the baseline followed by every change made since. Dropping a table removes
 * its earlier entries, so the log only describes tables that exist.
 *
 * Changes made outside of ProcessWire's database API (i.e. by a module using PDO directly, or in a
 * database client) are not recorded.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * @since 3.0.273
 *
 */

class WireDatabaseSchemaLog extends Wire {

	/**
	 * Name of the log table
	 *
	 */
	const table = 'schema_log';

	/**
	 * @var WireDatabasePDO
	 *
	 */
	protected $database;

	/**
	 * Has the log table been confirmed to exist in this request?
	 *
	 * @var bool
	 *
	 */
	protected $started = false;

	/**
	 * Number of active suspend() calls
	 *
	 * @var int
	 *
	 */
	protected $suspended = 0;

	/**
	 * Currently recording? (prevents recording the log's own statements)
	 *
	 * @var bool
	 *
	 */
	protected $recording = false;

	/**
	 * @param WireDatabasePDO $database
	 *
	 */
	public function __construct(WireDatabasePDO $database) {
		parent::__construct();
		$this->database = $database;
	}

	/**
	 * Is the given SQL a schema change that should be recorded?
	 *
	 * Temporary tables are not schema changes and are not matched.
	 *
	 * @param string|mixed $sql
	 * @return bool
	 *
	 */
	public static function isSchemaStatement($sql) {
		if(!is_string($sql)) return false;
		$sql = ltrim($sql);
		// fast rejection of everything else, since this is checked for every exec(), query() and prepare()
		$c = strtoupper(substr($sql, 0, 1));
		if($c !== 'C' && $c !== 'A' && $c !== 'D' && $c !== 'R') return false;
		return (bool) preg_match(
			'/^(CREATE\s+(UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(TABLE|INDEX)|ALTER\s+TABLE|DROP\s+(TABLE|INDEX)|RENAME\s+TABLE)\s/i',
			$sql
		);
	}

	/**
	 * Record a schema change that has just been successfully executed
	 *
	 * Never throws: a failure to record is reported to the error log, since it must not affect the
	 * schema change itself.
	 *
	 * @param string $sql Schema change in MySQL syntax
	 * @return bool True if recorded (or accounted for by the baseline)
	 *
	 */
	public function record($sql) {
		if($this->suspended || $this->recording) return false;
		$change = $this->parse($sql);
		if(!$change) return false;
		if(in_array(self::table, $change['tables'], true)) return false;
		$this->recording = true;
		try {
			if($this->start() === 'created') return true; // the baseline already includes this change
			if($change['type'] === 'drop') {
				// nothing about a dropped table is needed to rebuild the schema
				foreach($change['tables'] as $table) $this->forget($table);
			} else if($change['type'] === 'rename') {
				// keep earlier entries under the new name, so a later DROP of it removes them too
				foreach($change['renames'] as $from => $to) $this->rename($from, $to);
				$this->insert(end($change['renames']), $sql);
			} else {
				$this->insert($change['tables'][0], $sql);
			}
			return true;
		} catch(\Exception $e) {
			$this->error("Unable to record schema change in " . self::table . ": " . $e->getMessage(), Notice::logOnly);
			return false;
		} finally {
			$this->recording = false;
		}
	}

	/**
	 * Stop recording until resume() is called, i.e. while restoring a backup
	 *
	 * Calls may be nested: recording resumes when every suspend() has a matching resume().
	 *
	 */
	public function suspend() {
		$this->suspended++;
	}

	/**
	 * Resume recording after suspend()
	 *
	 */
	public function resume() {
		if($this->suspended > 0) $this->suspended--;
		$this->started = false; // i.e. a restored backup may have replaced the log table
	}

	/**
	 * Get all log entries in the order they should be replayed
	 *
	 * @return array Each entry: [ 'id' => int, 'created' => string, 'table' => string, 'baseline' => bool, 'sql' => string ]
	 *
	 */
	public function getEntries() {
		$entries = [];
		if(!$this->database->tableExists(self::table)) return $entries;
		$query = $this->database->query("SELECT id, created, table_name, baseline, ddl FROM " . self::table . " ORDER BY id");
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$entries[] = [
				'id' => (int) $row['id'],
				'created' => $row['created'],
				'table' => $row['table_name'],
				'baseline' => (bool) $row['baseline'],
				'sql' => $row['ddl'],
			];
		}
		$query->closeCursor();
		return $entries;
	}

	/**
	 * Create the log table and record the baseline if the log does not exist yet
	 *
	 * @return bool|string True if the log already existed, 'created' if it was just created
	 *
	 */
	protected function start() {
		if($this->started) return true;
		if($this->database->tableExists(self::table)) {
			$this->started = true;
			return true;
		}
		$config = $this->wire()->config;
		$engine = $this->database->escapeStr($config->dbEngine ? $config->dbEngine : 'InnoDB');
		$charset = $this->database->escapeStr($config->dbCharset ? $config->dbCharset : 'utf8mb4');
		$this->database->exec(
			"CREATE TABLE IF NOT EXISTS `" . self::table . "` (" .
				"`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, " .
				"`created` DATETIME NOT NULL, " .
				"`table_name` VARCHAR(128) NOT NULL, " .
				"`baseline` TINYINT NOT NULL DEFAULT 0, " .
				"`ddl` MEDIUMTEXT NOT NULL, " .
				"PRIMARY KEY (`id`), " .
				"KEY `table_name` (`table_name`)" .
			") ENGINE=$engine DEFAULT CHARSET=$charset"
		);
		$this->baseline();
		$this->started = true;
		return 'created';
	}

	/**
	 * Record the CREATE TABLE of every existing table
	 *
	 */
	protected function baseline() {
		$failed = [];
		foreach($this->database->getTables(false) as $table) { // not cached: may have changed this request
			if($table === self::table) continue;
			try {
				$query = $this->database->query("SHOW CREATE TABLE `" . $this->database->escapeTable($table) . "`");
				$row = $query->fetch(\PDO::FETCH_NUM);
				$query->closeCursor();
				if($row && !empty($row[1])) {
					$this->insert($table, $row[1], true);
				} else {
					$failed[] = $table;
				}
			} catch(\Exception $e) {
				$failed[] = $table;
			}
		}
		if(count($failed)) {
			$this->error(
				"Unable to record the schema of these tables in " . self::table . ": " . implode(', ', $failed),
				Notice::logOnly
			);
		}
	}

	/**
	 * Add an entry to the log
	 *
	 * @param string $table
	 * @param string $sql
	 * @param bool $baseline
	 *
	 */
	protected function insert($table, $sql, $baseline = false) {
		$query = $this->database->prepare(
			"INSERT INTO " . self::table . " (created, table_name, baseline, ddl) " .
			"VALUES(:created, :table_name, :baseline, :ddl)"
		);
		$query->bindValue(':created', date('Y-m-d H:i:s'));
		$query->bindValue(':table_name', $table);
		$query->bindValue(':baseline', $baseline ? 1 : 0, \PDO::PARAM_INT);
		$query->bindValue(':ddl', rtrim(trim($sql), ';'));
		$query->execute();
	}

	/**
	 * Remove all entries for a table
	 *
	 * @param string $table
	 *
	 */
	protected function forget($table) {
		$query = $this->database->prepare("DELETE FROM " . self::table . " WHERE table_name=:table_name");
		$query->bindValue(':table_name', $table);
		$query->execute();
	}

	/**
	 * Move a table's entries to its new name
	 *
	 * @param string $from
	 * @param string $to
	 *
	 */
	protected function rename($from, $to) {
		$query = $this->database->prepare("UPDATE " . self::table . " SET table_name=:to WHERE table_name=:from");
		$query->bindValue(':to', $to);
		$query->bindValue(':from', $from);
		$query->execute();
	}

	/**
	 * Identify the kind of change and the tables it affects
	 *
	 * @param string $sql
	 * @return array|null [ 'type' => 'create'|'alter'|'drop'|'rename'|'index', 'tables' => [...], 'renames' => [from => to] ]
	 *
	 */
	protected function parse($sql) {
		$name = '(`[^`]+`|[A-Za-z0-9_$]+)(?:\.(`[^`]+`|[A-Za-z0-9_$]+))?'; // table or db.table, optionally quoted
		$clean = function($match) {
			$parts = array_values(array_filter(array_slice($match, 1), 'strlen'));
			return str_replace('`', '', end($parts)); // table part of db.table
		};
		$sql = ltrim($sql);
		$m = [];

		if(preg_match("/^RENAME\s+TABLE\s+(.+)$/is", $sql, $m)) {
			$renames = [];
			foreach(preg_split('/\s*,\s*/', trim($m[1])) as $pair) {
				if(!preg_match("/^$name\s+TO\s+$name$/i", trim($pair), $p)) return null;
				$renames[$clean([0, $p[1], isset($p[2]) ? $p[2] : ''])] = $clean([0, $p[3], isset($p[4]) ? $p[4] : '']);
			}
			return ['type' => 'rename', 'tables' => array_merge(array_keys($renames), array_values($renames)), 'renames' => $renames];
		}

		if(preg_match("/^ALTER\s+TABLE\s+$name\s+RENAME\s+(?:TO\s+|AS\s+)?$name\s*$/i", $sql, $m)) {
			$from = $clean([0, $m[1], $m[2]]);
			$to = $clean([0, $m[3], isset($m[4]) ? $m[4] : '']);
			return ['type' => 'rename', 'tables' => [$from, $to], 'renames' => [$from => $to]];
		}

		if(preg_match("/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(.+?)(?:\s+(?:RESTRICT|CASCADE))?\s*$/is", $sql, $m)) {
			$tables = [];
			foreach(preg_split('/\s*,\s*/', trim($m[1])) as $item) {
				if(!preg_match("/^$name$/", trim($item), $p)) return null;
				$tables[] = $clean($p);
			}
			return ['type' => 'drop', 'tables' => $tables, 'renames' => []];
		}

		if(preg_match("/^(?:CREATE\s+(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX|DROP\s+INDEX)\s+\S+\s+ON\s+$name/i", $sql, $m)) {
			return ['type' => 'index', 'tables' => [$clean($m)], 'renames' => []];
		}

		if(preg_match("/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?$name/i", $sql, $m)) {
			return ['type' => 'create', 'tables' => [$clean($m)], 'renames' => []];
		}

		if(preg_match("/^ALTER\s+TABLE\s+$name/i", $sql, $m)) {
			return ['type' => 'alter', 'tables' => [$clean($m)], 'renames' => []];
		}

		return null;
	}
}
