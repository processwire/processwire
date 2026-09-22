# WireDatabasePDO / $database

`$database` is ProcessWire's PDO-based database API variable. It wraps PHP's native
`PDO` class with ProcessWire-specific helpers for queries, transactions, schema
inspection, identifier sanitization, query logging, and database metadata.

Most site code should use higher-level ProcessWire APIs such as `$pages`, `$fields`,
and `$templates` where possible. Use `$database` when you need direct SQL access.

ProcessWire uses MySQL/MariaDB by default. SQLite is also supported (experimental, 3.0.273+).
With either database, write SQL in MySQL syntax: with SQLite, ProcessWire translates it.
See [Database types](#database-types).

## Common Rules

- Prefer prepared statements for values; use `quote()` only when prepared statements are not practical.
- Use `escapeTable()`, `escapeCol()` and `escapeTableCol()` for identifiers only, not values.
- Do not concatenate unsanitized input into SQL.
- Use `execute($query, false)` only when you intend to handle query failure yourself.
- Close cursors or fully consume statements before running dependent queries when needed.
- Use transactions only when `allowTransaction()` returns true.
- Write SQL in MySQL syntax, even when the site uses SQLite (it is translated).
- To support both MySQL and SQLite, check capabilities with `$database->dialect()` rather than
  checking which database is in use.

```php
$query = $database->prepare("SELECT id, name FROM pages WHERE templates_id=:template");
$query->bindValue(':template', $template->id, \PDO::PARAM_INT);
$database->execute($query);
foreach($query as $row) {
    // ...
}
```

---

## Database types

### Configuration

The database type is set in `/site/config.php`:

| Setting | Description |
|---------|-------------|
| `$config->dbType` | `'mysql'` (default) for MySQL/MariaDB, or `'sqlite'` |
| `$config->dbFile` | SQLite only: database file (see below) |

MySQL uses the `dbName`, `dbUser`, `dbPass`, `dbHost`, `dbPort` etc. settings. SQLite uses only `dbFile`:

```php
// /site/config.php
$config->dbType = 'sqlite';
$config->dbFile = '';                             // site/assets/database/site.sqlite (default)
$config->dbFile = 'mysite.sqlite';                // site/assets/database/mysite.sqlite
$config->dbFile = '/home/user/data/site.sqlite';  // absolute path (i.e. outside the web root)
```

`$config->dbFile` rules:

- Blank: `site/assets/database/site.sqlite`.
- Filename or relative path: relative to `site/assets/database/`. It may not contain `..`.
- Leading slash (or Windows drive letter): absolute path.
- A database file inside the web root must be in `site/assets/database/`, otherwise a `WireException`
  is thrown. That directory is blocked by the root `.htaccess` file, and ProcessWire adds a deny-all
  `.htaccess` file to it. The root `.htaccess` file also blocks `.sqlite` and `.sqlite3` files
  (and their `-wal`, `-shm` and `-journal` files) anywhere.
- On servers that do not support `.htaccess` files (such as nginx), use an absolute path outside the web root.

### dialect()

- **Returns:** `WireDatabaseDialect` (`WireDatabaseDialectMySQL` or `WireDatabaseDialectSQLite`)
- **Purpose:** Database-specific behavior, including capability checks.

```php
$dialect = $database->dialect();
echo $dialect->name(); // 'mysql' or 'sqlite'
```

Capability methods (all return bool):

| Method | MySQL | SQLite | Meaning |
|--------|-------|--------|---------|
| `supportsFulltext()` | true | false | FULLTEXT indexes and `MATCH ... AGAINST` |
| `supportsFoundRows()` | true | false | `SQL_CALC_FOUND_ROWS` and `FOUND_ROWS()` |
| `supportsUpdateOrderBy()` | true | false | `ORDER BY` in `UPDATE`, applied row by row for unique key checks |
| `supportsTransaction()` | InnoDB only | true | Transactions (also available on `$database`) |

### Writing SQL that works with both

Most MySQL syntax that ProcessWire and modules commonly use works on SQLite unchanged, including
`INSERT ... SET`, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE ... VALUES()`, backtick identifiers,
`LIMIT offset,count`, `REGEXP`/`RLIKE`, `GROUP_CONCAT(... ORDER BY ... SEPARATOR ...)`,
`CREATE TABLE` with `KEY`/`UNIQUE KEY`/`FULLTEXT KEY` definitions, most `ALTER TABLE` operations,
`SHOW TABLES`/`SHOW COLUMNS`/`SHOW INDEX`/`SHOW CREATE TABLE`/`DESCRIBE`, and common functions such as
`NOW()`, `UNIX_TIMESTAMP()`, `FROM_UNIXTIME()`, `DATE_FORMAT()`, `DATE_ADD()`/`DATE_SUB()`, `IF()`,
`FIELD()`, `CONCAT()`, `CONCAT_WS()`, `LOCATE()`, `SUBSTRING_INDEX()`, `GREATEST()`, `LEAST()` and `RAND()`.

```php
// works on MySQL and SQLite
$query = $database->prepare(
    "INSERT INTO my_table SET name=:name, qty=:qty " .
    "ON DUPLICATE KEY UPDATE qty=VALUES(qty)"
);
$query->bindValue(':name', $name);
$query->bindValue(':qty', $qty, \PDO::PARAM_INT);
$database->execute($query);

// use a capability check where the databases differ
if($database->dialect()->supportsFulltext()) {
    $sql = "SELECT pages_id FROM field_body WHERE MATCH(data) AGAINST(:text)";
} else {
    $sql = "SELECT pages_id FROM field_body WHERE data LIKE :text";
}
```

Guidelines:

- Use bound values or `quote()`/`escapeStr()` for values. With SQLite these escape MySQL-style, which the
  translator expects. Do not escape values with SQLite-style `''` quoting.
- Get row counts with `COUNT(*)` or by counting fetched rows. With SQLite, `rowCount()` after a `SELECT`
  runs an additional `COUNT(*)` query (PDO's SQLite driver does not provide it).
- Close cursors (`closeCursor()`) or fetch all rows before changing the schema of a table you just read from.
- Use `$pages->find()` and other ProcessWire APIs for selector-based queries: they handle the database
  differences for you (for example, fulltext operators on SQLite).

### SQLite

SQLite support is experimental. The installer offers it as a database type when PHP's `pdo_sqlite`
extension and SQLite 3.35+ are available (CLI installer: `'dbType' => 'sqlite'`, optionally `'dbFile'`).

- **Requirements:** PHP's `pdo_sqlite` extension and SQLite 3.35.0 or newer (checked on connect).
  On PHP 8.4+, connections use `Pdo\Sqlite`.
- **How it works:** ProcessWire's SQL (MySQL syntax) is translated by `WireDatabaseSQLiteTranslator` and
  MySQL-compatible functions are registered with SQLite. Statements that translate to multiple SQLite
  statements (such as `CREATE TABLE` with indexes, or an `ALTER TABLE` that requires rebuilding the table)
  are executed atomically.
- **Connection:** WAL journal mode, 5 second busy timeout. Errors for unknown tables and columns use MySQL's
  SQLSTATE codes (`42S02`, `42S22`) and are reported at `execute()` rather than `prepare()`, as with MySQL.

Behavior differences from MySQL:

| Area | SQLite behavior |
|------|-----------------|
| Case-insensitive matching | Text columns use `COLLATE NOCASE`, which only folds ASCII letters. `title=äpfel` does not match `Äpfel`, and non-ASCII letters sort by byte value (after `z`). |
| Fulltext search | No FULLTEXT indexes (they become regular indexes). Selector fulltext operators use `LIKE`/`REGEXP`: no relevance ordering; stopwords are not ignored (so `title~=the home` requires "the" too, whereas MySQL ignores it); word operators such as `~=` also match partial words; query expansion (`*+=`, `**+=`) and boolean commands (`#=`) are approximated. |
| Column types | Not enforced (SQLite type affinity). `UNSIGNED`, display widths, `CHARACTER SET` and `COLLATE` are ignored, `ENUM`/`SET` become `TEXT`, and `VARCHAR` lengths are not enforced. |
| Times | `NOW()`, `UNIX_TIMESTAMP()` and similar functions use PHP's time zone. `DEFAULT CURRENT_TIMESTAMP` uses the system's local time (MySQL uses the server's time zone). `ON UPDATE CURRENT_TIMESTAMP` is ignored. |
| `GROUP_CONCAT()` | No length limit (MySQL's `group_concat_max_len` does not apply). |
| `TRUNCATE` | Deletes all rows and resets the auto-increment counter. |
| Unfinished `SELECT` | SQLite cannot drop or rebuild a table while a `SELECT` on it is unfinished. ProcessWire closes such cursors and retries, so further fetches from them return nothing. |
| Concurrency | One writer at a time. Other writers wait (up to 5 seconds). |
| No-op statements | `LOCK TABLES`, `UNLOCK TABLES`, `SET ...` (i.e. `SET NAMES`), `OPTIMIZE`/`ANALYZE`/`REPAIR`/`CHECK TABLE`. |

Not supported (throws an exception):

- `FOUND_ROWS()` (`SQL_CALC_FOUND_ROWS` is ignored): use `COUNT(*)`, or check `supportsFoundRows()`.
- `UPDATE` with `JOIN`, and multi-table `DELETE` with more than one target table
  (`DELETE t FROM t JOIN ...` with one target is supported).
- `SELECT ... FOR UPDATE` / `LOCK IN SHARE MODE`, user variables (`@var`), stored procedures.
- `ALTER TABLE ... ADD CONSTRAINT` and `RENAME INDEX`.
- `ALTER TABLE` operations that require rebuilding a table (`MODIFY`, `CHANGE`, primary key changes) on
  tables with triggers, CHECK/FOREIGN KEY/UNIQUE constraints, or indexes not created through ProcessWire.

---

## Connection

### pdo()

- **Arguments:** `pdo($type = null)`
- **Returns:** `\PDO`
- **Purpose:** Get the underlying PDO connection. Omit `$type` for the writer connection.

```php
$pdo = $database->pdo();
```

The `$database->pdo` property is an alias for `$database->pdo()`.

### getAttribute() / setAttribute()

- **Arguments:** `getAttribute($attribute)`, `setAttribute($attribute, $value)`
- **Returns:** mixed for `getAttribute()`, bool for `setAttribute()`
- **Purpose:** Proxy to PDO connection attributes.

```php
$driver = $database->getAttribute(\PDO::ATTR_DRIVER_NAME);
```

### errorCode() / errorInfo()

- **Returns:** Last PDO error code or error info from the last used connection.

### reset() / close()

- **Arguments:** `reset($type = null)`, `close($type = null)`
- **Returns:** `$database`
- **Purpose:** Close or re-create writer/reader PDO connections.
- **Types:** `$type` may be `'writer'`, `'reader'`, or omitted for both/current use.

```php
$database->reset();
$database->close('writer');
```

---

## Queries

### prepare()

- **Arguments:** `prepare($statement, $driver_options = [], $note = '')`
- **Returns:** `\PDOStatement|WireDatabasePDOStatement`
- **Purpose:** Prepare SQL for bound parameters.

```php
$query = $database->prepare("SELECT * FROM pages WHERE id=:id");
$query->bindValue(':id', 1234, \PDO::PARAM_INT);
$database->execute($query);
```

The `$driver_options` argument may be:

| Value | Behavior |
|-------|----------|
| array | Passed through as PDO driver options |
| `true` | Request a `WireDatabasePDOStatement` (with SQLite, always a `WireDatabaseSQLiteStatement`, which extends it) |
| string | Treated as the debug `$note` argument |

### execute()

- **Arguments:** `execute(\PDOStatement $query, $throw = true, $maxTries = 3)`
- **Returns:** bool
- **Purpose:** Execute a prepared statement with ProcessWire retry/error handling.

```php
$ok = $database->execute($query);
$ok = $database->execute($query, false); // return false rather than throw
```

### query()

- **Arguments:** `query($statement, $note = '')`
- **Returns:** `\PDOStatement`
- **Purpose:** Execute SQL and return a result set.

```php
$rows = $database->query("SELECT id FROM pages LIMIT 10");
foreach($rows as $row) {
    echo $row['id'];
}
```

### exec()

- **Arguments:** `exec($statement, $note = '')`
- **Returns:** int|bool
- **Purpose:** Execute SQL and return the number of affected rows when available.
If given a PDOStatement, it delegates to `execute()`.

```php
$n = $database->exec("UPDATE pages SET modified=modified WHERE id=1234");
```

### lastInsertId()

- **Arguments:** `lastInsertId($name = null)`
- **Returns:** string
- **Purpose:** Return the last auto-increment ID from the writer connection.

---

## Transactions

Transactions are available when the current database engine/table supports them
(MySQL InnoDB tables, and always with SQLite).

```php
if($database->allowTransaction()) {
    $database->beginTransaction();
    try {
        $database->exec("UPDATE ...");
        $database->commit();
    } catch(\Exception $e) {
        $database->rollBack();
        throw $e;
    }
}
```

### beginTransaction()

- **Returns:** bool
- **Purpose:** Begin a writer transaction and disable reader use while active.

### inTransaction()

- **Returns:** bool
- **Purpose:** Check whether the writer connection is currently in a transaction.

### commit()

- **Returns:** bool
- **Purpose:** Commit the active transaction. Returns false if not in a transaction.

### rollBack()

- **Returns:** bool
- **Purpose:** Roll back the active transaction. Returns false if not in a transaction.

### supportsTransaction() / allowTransaction()

- **Arguments:** `supportsTransaction($table = '')`, `allowTransaction($table = '')`
- **Returns:** bool
- **Purpose:** Determine whether transactions are supported and currently allowed.

---

## Schema

Schema methods inspect database tables, columns and indexes.

### getTables()

- **Arguments:** `getTables($allowCache = true)`
- **Returns:** array of table names

```php
$tables = $database->getTables(false); // bypass cache
```

### tableExists()

- **Arguments:** `tableExists($table)`
- **Returns:** bool

### getColumns()

- **Arguments:** `getColumns($table, $verbose = false)`
- **Returns:** array
- **Purpose:** Get column names or verbose column info.

```php
$names = $database->getColumns('pages');
$info  = $database->getColumns('pages', true);      // indexed by column name
$name  = $database->getColumns('pages', 'name');    // one column info
```

Verbose modes:

| Value | Description |
|-------|-------------|
| `false` | Column names only |
| `true` or `1` | Simplified verbose info indexed by column name |
| `2` | Raw column information in MySQL `SHOW COLUMNS` format (emulated with SQLite) |
| `3` | Column types as used in a CREATE TABLE statement |
| string | One column's verbose info |

### columnExists()

- **Arguments:** `columnExists($table, $column = '', $getInfo = false)`
- **Returns:** bool|array

```php
$ok = $database->columnExists('pages', 'name');
$ok = $database->columnExists('pages.name');
$info = $database->columnExists('pages', 'name', true);
```

### getIndexes()

- **Arguments:** `getIndexes($table, $verbose = false)`
- **Returns:** array

```php
$indexes = $database->getIndexes('pages');
$info = $database->getIndexes('pages', true);
$primary = $database->getIndexes('pages.PRIMARY', true);
```

### indexExists()

- **Arguments:** `indexExists($table, $indexName, $getInfo = false)`
- **Returns:** bool|array

### getPrimaryKey()

- **Arguments:** `getPrimaryKey($table, $verbose = false)`
- **Returns:** string|array
- **Purpose:** Return primary key column(s), or verbose primary-key info.

### renameColumn() / renameColumns()

- **Arguments:** `renameColumn($table, $oldName, $newName)`, `renameColumns($table, array $columns)`
- **Returns:** bool for `renameColumn()`, int count for `renameColumns()`
- **Purpose:** Rename columns without changing type.

```php
$database->renameColumn('my_table', 'old_name', 'new_name');
$database->renameColumns('my_table', [
    'old_a' => 'new_a',
    'old_b' => 'new_b',
]);
```

---

## Sanitization

Use these helpers when building SQL identifiers or operator strings from dynamic
values. They sanitize identifiers; they do not quote identifiers with backticks.

### escapeTable() / escapeCol()

- **Arguments:** `escapeTable($table)`, `escapeCol($col)`
- **Returns:** string containing only `_a-zA-Z0-9`

```php
$table = $database->escapeTable($inputName);
$col = $database->escapeCol($inputColumn);
```

### escapeTableCol()

- **Arguments:** `escapeTableCol($str)`
- **Returns:** sanitized `table.column`, `table`, or `column` string

```php
$field = $database->escapeTableCol('pages.name');
```

### isOperator()

- **Arguments:** `isOperator($str, $operatorType = WireDatabasePDO::operatorTypeAny, $get = false)`
- **Returns:** bool|string
- **Purpose:** Validate comparison or bitwise SQL operators.

```php
if($database->isOperator($operator, WireDatabasePDO::operatorTypeComparison)) {
    // =, !=, <, <=, >, >=, <>
}
```

Operator type constants:

| Constant | Description |
|----------|-------------|
| `WireDatabasePDO::operatorTypeComparison` | Comparison operators only |
| `WireDatabasePDO::operatorTypeBitwise` | Bitwise operators only |
| `WireDatabasePDO::operatorTypeAny` | Comparison or bitwise operators |

### escapeOperator()

- **Arguments:** `escapeOperator($operator, $operatorType = WireDatabasePDO::operatorTypeComparison, $default = '=')`
- **Returns:** valid operator or fallback

### quote()

- **Arguments:** `quote($str)`
- **Returns:** quoted and escaped string value, including surrounding quotes.

```php
$sql = "name=" . $database->quote($name);
```

Prefer prepared statements for values whenever possible.

### escapeStr()

- **Arguments:** `escapeStr($str)`
- **Returns:** escaped string without surrounding quotes.

### escapeLike()

- **Arguments:** `escapeLike($like)`
- **Returns:** escaped string suitable for SQL `LIKE` values.

```php
$like = '%' . $database->escapeLike($term) . '%';
```

---

## Info

### getVersion()

- **Arguments:** `getVersion($getNumberOnly = false)`
- **Returns:** MySQL/MariaDB version string, or SQLite version string (i.e. `3.47.0`).

### getServerType()

- **Returns:** server type string such as `MySQL`, `MariaDB`, `Percona`, or `SQLite`.

### getRegexEngine()

- **Returns:** `ICU` or `HenrySpencer`. With SQLite, `ICU` (REGEXP uses PHP's PCRE).

### getEngine()

- **Returns:** current configured database engine in lowercase (`$config->dbEngine`, not meaningful with SQLite).

### getCharset()

- **Returns:** current configured database charset in lowercase.

### getVariable()

- **Arguments:** `getVariable($name, $cache = true, $sub = true)`
- **Returns:** string|null
- **Purpose:** Retrieve a MySQL/MariaDB variable. With SQLite, only `version`, `version_comment` and the
  fulltext word length variables return a value; others return null.

```php
$version = $database->getVariable('version');
```

### getMaxIndexLength()

- **Returns:** int max length allowed for a fully indexed varchar column.

### getTime()

- **Arguments:** `getTime($getTimestamp = false)`
- **Returns:** ISO datetime string or UNIX timestamp.

```php
$now = $database->getTime();
$ts = $database->getTime(true);
```

### getStopwords() / isStopword()

- **Arguments:** `getStopwords($engine = '', $flip = false)`, `isStopword($word, $engine = '')`
- **Returns:** array or bool
- **Purpose:** Get or check fulltext stopwords for MyISAM/InnoDB.

---

## Query Log

### queryLog()

- **Arguments:** `queryLog($sql = '', $note = '')`
- **Returns:** array|bool
- **Purpose:** Start, stop, reset, retrieve, or append to the in-memory query log.

```php
$database->queryLog(true);          // reset and start
$database->query("SELECT 1", "note");
$log = $database->queryLog();       // retrieve
$database->queryLog(false);         // stop
```

Argument behavior:

| `$sql` value | Behavior |
|--------------|----------|
| omitted or `''` | Return current log array |
| `true` | Reset and start logging |
| `1` | Start logging without reset |
| `false` | Stop logging |
| string | Append SQL to log when logging is active |

Core automatically populates this log when ProcessWire debug mode is active.

---

## Backups

### backups()

- **Returns:** `WireDatabaseBackup`
- **Purpose:** Create a backup helper instance configured for this database.

```php
$backups = $database->backups();
$file = $backups->backup(['description' => 'Before upgrade']);
$backups->restore(basename($file));
```

See `WireDatabaseBackup` for backup and restore operations.

With SQLite, backups work the same way. Backup files are MySQL-syntax SQL, rebuilt from the SQLite schema
(including indexes; FULLTEXT indexes are saved as regular indexes), and restore through the SQL translator.
Backup filenames use the database file's name, i.e. `site_2026-01-01_12-00-00.sql`.

---

## Notes

- `$database->dialect()` capability checks are preferred over checking `$config->dbType` or `name()`.
- SQL with values embedded in it must be escaped with `quote()`/`escapeStr()`, never by hand.
- With SQLite, avoid `rowCount()` on `SELECT` statements where performance matters (it runs a second query).
- With SQLite, failed queries are logged to `site/assets/logs/sqlite-errors.txt` in debug mode, including
  the original and translated SQL.
- `WireDatabaseSQLiteTranslator` can also be used on its own (it has no ProcessWire dependencies), i.e.
  `(new WireDatabaseSQLiteTranslator($pdo))->translate($sql)`.
