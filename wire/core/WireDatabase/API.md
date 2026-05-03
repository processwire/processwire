# WireDatabasePDO / $database

`$database` is ProcessWire's PDO-based database API variable. It wraps PHP's native
`PDO` class with ProcessWire-specific helpers for queries, transactions, schema
inspection, identifier sanitization, query logging, and database metadata.

Most site code should use higher-level ProcessWire APIs such as `$pages`, `$fields`,
and `$templates` where possible. Use `$database` when you need direct SQL access.

## Common Rules

- Prefer prepared statements for values; use `quote()` only when prepared statements are not practical.
- Use `escapeTable()`, `escapeCol()` and `escapeTableCol()` for identifiers only, not values.
- Do not concatenate unsanitized input into SQL.
- Use `execute($query, false)` only when you intend to handle query failure yourself.
- Close cursors or fully consume statements before running dependent queries when needed.
- Use transactions only when `allowTransaction()` returns true.

```php
$query = $database->prepare("SELECT id, name FROM pages WHERE templates_id=:template");
$query->bindValue(':template', $template->id, \PDO::PARAM_INT);
$database->execute($query);
foreach($query as $row) {
    // ...
}
```

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
| `true` | Request a `WireDatabasePDOStatement` |
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

Transactions are available when the current database engine/table supports them.

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
| `2` | Raw MySQL column information |
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
- **Returns:** MySQL/MariaDB version string.

### getServerType()

- **Returns:** server type string such as `MySQL`, `MariaDB`, or `Percona`.

### getRegexEngine()

- **Returns:** `ICU` or `HenrySpencer`.

### getEngine()

- **Returns:** current configured database engine in lowercase.

### getCharset()

- **Returns:** current configured database charset in lowercase.

### getVariable()

- **Arguments:** `getVariable($name, $cache = true, $sub = true)`
- **Returns:** string|null
- **Purpose:** Retrieve a MySQL/MariaDB variable.

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
```

See `WireDatabaseBackup` for backup and restore operations.
