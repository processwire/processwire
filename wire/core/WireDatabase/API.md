# WireDatabasePDO / $database

`$database` is ProcessWire's PDO-based database API variable. It wraps PHP's native
`PDO` class with ProcessWire-specific helpers for queries, transactions, schema
inspection, identifier sanitization, query logging, and database metadata.

Most site code should use higher-level ProcessWire APIs such as `$pages`, `$fields`,
and `$templates` where possible. Use `$database` when you need direct SQL access.

ProcessWire uses MySQL/MariaDB by default. SQLite (3.0.273+) and PostgreSQL are also supported
(both experimental). Whatever the database, write SQL in MySQL syntax: with SQLite and PostgreSQL,
ProcessWire translates it. See [Database types](#database-types).

## Common Rules

- Prefer prepared statements for values; use `quote()` only when prepared statements are not practical.
- Use `escapeTable()`, `escapeCol()` and `escapeTableCol()` for identifiers only, not values.
- Do not concatenate unsanitized input into SQL.
- Use `execute($query, false)` only when you intend to handle query failure yourself.
- Close cursors or fully consume statements before running dependent queries when needed.
- Use transactions only when `allowTransaction()` returns true.
- Write SQL in MySQL syntax, even when the site uses SQLite or PostgreSQL (it is translated).
- To support every database, check capabilities with `$database->dialect()` rather than
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
| `$config->dbType` | `'mysql'` (default) for MySQL/MariaDB, `'sqlite'`, or `'pgsql'` for PostgreSQL |
| `$config->dbFile` | SQLite only: database file (see below) |
| `$config->dbOptions` | PDO driver options, and per-type ProcessWire settings indexed by database type (see the SQLite and PostgreSQL sections) |

MySQL uses the `dbName`, `dbUser`, `dbPass`, `dbHost`, `dbPort` etc. settings. PostgreSQL uses the same
settings. Set `dbPort` to 5432 (the installer does), since the default is MySQL's 3306, and use
`dbSocket` for the directory that holds the server's socket file rather than the file itself:

```php
// /site/config.php
$config->dbType = 'pgsql';
$config->dbHost = 'localhost';
$config->dbPort = '5432';
$config->dbName = 'mysite';
$config->dbUser = 'mysite';
$config->dbPass = 'secret';
$config->dbSocket = '/var/run/postgresql';  // optional: connect through the socket in this directory instead of dbHost
$config->dbOptions = ['pgsql' => ['schema' => 'processwire']];  // optional: schema other than public
```

SQLite uses only `dbFile`:

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
- For additional protection, the database file may be placed outside the web root by using an absolute path.
- The installer names the database file randomly (i.e. `site-3f9a2c7e.sqlite`) unless another file is
  specified, so that its URL cannot be guessed on servers where `.htaccess` files do not apply. The web
  installer also checks that the file cannot be downloaded, and warns if it can.

### dialect()

- **Returns:** `WireDatabaseDialect` (`WireDatabaseDialectMySQL`, `WireDatabaseDialectSQLite` or
  `WireDatabaseDialectPgsql`)
- **Purpose:** Database-specific behavior, including capability checks.

```php
$dialect = $database->dialect();
echo $dialect->name(); // 'mysql', 'sqlite' or 'pgsql'
```

Capability methods (all return bool):

| Method | MySQL | SQLite | PostgreSQL | Meaning |
|--------|-------|--------|------------|---------|
| `supportsFulltext()` | true | true* | true* | FULLTEXT indexes and `MATCH ... AGAINST` (*SQLite: with FTS5, in databases created with it; PostgreSQL: when full text search is set up; see below) |
| `supportsFoundRows()` | true | false | false | `SQL_CALC_FOUND_ROWS` and `FOUND_ROWS()` |
| `supportsUpdateOrderBy()` | true | false | false | `ORDER BY` in `UPDATE`, applied row by row for unique key checks |
| `supportsTransaction()` | InnoDB only | true | true | Transactions (also available on `$database`) |
| `supportsJson()` | 5.7.8+ / MariaDB 10.2.7+ | 3.38+ or a build with JSON | when its functions exist (see below) | JSON functions such as `JSON_EXTRACT()` |

Two more dialect methods return a collation name (or a blank string when none is needed, as on MySQL and
PostgreSQL): `compareCollation($value)` for text comparisons and `sortCollation()` for `ORDER BY` terms.
See the SQLite section below.

### SQL helpers

Where a construct differs between databases, the dialect can build the statement in its own syntax,
so that nothing has to be translated (and nothing has to be guessed by the translator). Helper output
is executed as-is by `prepare()`, `query()` and `exec()`.

#### upsert()

- **Signature:** `upsert(string $table, array $columns, array $update, array $options = []): string`
- **Purpose:** An INSERT that updates the existing row when it would violate a unique key. MySQL:
  `INSERT ... ON DUPLICATE KEY UPDATE`. SQLite and PostgreSQL: `INSERT ... ON CONFLICT (...) DO UPDATE SET`.
- `$columns`: columns to insert, as a list of names (each bound as `:name`) and/or `name => expression`.
- `$update`: columns to update when the row exists, as a list of names (set to the inserted value; the
  column must be in `$columns`) and/or `name => expression` (i.e. `'NOW()'`, `'qty+1'`, `':bindName'`).
  Expressions are MySQL syntax on every database, as elsewhere: in an update expression a bare column
  name is the existing row's value and `VALUES(col)` is the inserted value. PostgreSQL translates them
  when building the statement. Prefer bound values over quoted literals in expressions.
- `$options['conflict']`: column names of the primary or unique key the insert conflicts on. MySQL does
  not need it and SQLite can do without it, but PostgreSQL requires a conflict target: without this option
  it uses the table's primary key, and throws `WireDatabaseException` when the table has none. Pass it
  whenever you know it.
- `$options['rows']`: insert several rows at once, each a list of scalar values in `$columns` order. Numbers
  are inserted as given, strings are quoted, booleans become 1/0, null becomes NULL. Values are not SQL
  expressions: use `$columns` with bound parameters for anything else.
- Throws `WireDatabaseException` when there is nothing to insert or update, when a listed update column is
  not being inserted, when a row does not have one value per column, or when a row value is not a scalar.

```php
$dialect = $database->dialect();

$sql = $dialect->upsert('caches', ['name', 'data', 'expires'], ['data', 'expires'], ['conflict' => ['name']]);
// MySQL:  INSERT INTO `caches` (`name`, `data`, `expires`) VALUES (:name, :data, :expires)
//         ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `expires`=VALUES(`expires`)
// SQLite: INSERT INTO `caches` (`name`, `data`, `expires`) VALUES (:name, :data, :expires)
//         ON CONFLICT (`name`) DO UPDATE SET `data`=excluded.`data`, `expires`=excluded.`expires`
// PostgreSQL: the same as SQLite, with "double-quoted" identifiers
$query = $database->prepare($sql);
$query->bindValue(':name', $name);
$query->bindValue(':data', $data);
$query->bindValue(':expires', $expires);
$query->execute();

// custom expressions, and several rows of literal values
$sql = $dialect->upsert('sessions', ['id', 'data'], ['data', 'ts' => 'NOW()'], ['conflict' => ['id']]);
$sql = $dialect->upsert('pages', ['id', 'sort'], ['sort'], ['conflict' => ['id'], 'rows' => [[1001, 0], [1002, 1]]]);
$database->exec($sql);
```

Helper output goes through `prepare()`, `query()` and `exec()` like any other SQL. A translating dialect's
translator recognizes statements that are already in its own syntax and leaves them unchanged.

### Writing SQL that works with every database

Most MySQL syntax that ProcessWire and modules commonly use works on SQLite and PostgreSQL unchanged, including
`INSERT ... SET`, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE ... VALUES()`, backtick identifiers,
`LIMIT offset,count`, `REGEXP`/`RLIKE`, `GROUP_CONCAT(... ORDER BY ... SEPARATOR ...)`,
`CREATE TABLE` with `KEY`/`UNIQUE KEY`/`FULLTEXT KEY` definitions, most `ALTER TABLE` operations,
`SHOW TABLES`/`SHOW COLUMNS`/`SHOW INDEX`/`DESCRIBE` (and `SHOW CREATE TABLE` on SQLite), and common functions
such as `NOW()`, `UNIX_TIMESTAMP()`, `FROM_UNIXTIME()`, `DATE_FORMAT()`, `DATE_ADD()`/`DATE_SUB()`, `IF()`,
`FIELD()`, `CONCAT()`, `CONCAT_WS()`, `LOCATE()`, `SUBSTRING_INDEX()`, `GREATEST()`, `LEAST()` and `RAND()`.

```php
// works on MySQL, SQLite and PostgreSQL
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

- Use bound values or `quote()`/`escapeStr()` for values. With SQLite and PostgreSQL these escape MySQL-style,
  which the translator expects. Do not escape values with `''` quoting.
- Get row counts with `COUNT(*)` or by counting fetched rows. With SQLite, `rowCount()` after a `SELECT`
  runs an additional `COUNT(*)` query (PDO's SQLite driver does not provide it).
- Close cursors (`closeCursor()`) or fetch all rows before changing the schema of a table you just read from.
- Use `$pages->find()` and other ProcessWire APIs for selector-based queries: they handle the database
  differences for you (for example, fulltext operators on SQLite).
- Compare numbers with numbers. MySQL silently converts `qty = ''` or `qty = 'abc'` to `qty = 0`; the PostgreSQL
  translator does the same for columns it knows the type of, but a bound value compared to a column of a derived
  table or a subquery is passed through, and PostgreSQL then reports the type mismatch.

### SQLite

SQLite support is experimental. The installer offers it as a database type when PHP's `pdo_sqlite`
extension and SQLite 3.35+ are available (CLI installer: `'dbType' => 'sqlite'`, optionally `'dbFile'`).

- **Requirements:** PHP's `pdo_sqlite` extension and SQLite 3.35.0 or newer (checked on connect).
  On PHP 8.4+, connections use `Pdo\Sqlite`.
- **How it works:** ProcessWire's SQL (MySQL syntax) is translated by `WireDatabaseSQLiteTranslator` and
  MySQL-compatible functions are registered with SQLite. Schema introspection (`getTables()`, `getColumns()`,
  `getIndexes()`, `tableExists()`, `columnExists()`, `indexExists()`) queries SQLite's PRAGMA functions
  directly and returns the same values the MySQL dialect returns, while `SHOW` and `DESCRIBE` queries
  issued by module code continue to be emulated by the translator. Statements that translate to multiple SQLite
  statements (such as `CREATE TABLE` with indexes, or an `ALTER TABLE` that requires rebuilding the table)
  are executed atomically.
- **Settings:** `$config->dbOptions` may contain an array indexed by database type, holding PDO driver
  options and ProcessWire settings that apply only to that type. For SQLite the settings are currently
  `unicodeSort` (see the table below), `fulltext` (see Fulltext search below) and `foldIndex` (see Fold indexes
  below). Assign the whole array, as in `$config->dbOptions = ['sqlite' => ['unicodeSort' => true]];`
- **Connection:** WAL journal mode, 5 second busy timeout. Errors for unknown tables and columns use MySQL's
  SQLSTATE codes (`42S02`, `42S22`) and are reported at `execute()` rather than `prepare()`, as with MySQL.
- **Planner statistics:** without them SQLite often leaves unused the index that would serve a query (i.e. a
  `title=` lookup scans the template's pages). A database that has none (one just installed, or made before
  ProcessWire gathered them) gets a full `ANALYZE` on connecting, once: about 0.7 seconds per 20,000 pages. After
  that, `PRAGMA optimize` runs when each request ends (normally a fraction of a millisecond), analyzing again
  tables whose size has changed a lot, which SQLite does from a sample of their rows. `ANALYZE TABLE` (MySQL syntax)
  gathers full statistics for the tables it names at any time, i.e. after a large import.

Behavior differences from MySQL:

| Area | SQLite behavior |
|------|-----------------|
| Case- and accent-insensitive matching | Selector searches (`%=`, `*=`, `~=`, `^=`, `$=`, etc.) and `=` comparisons fold case and accents as MySQL does, so `title%=apfel` matches "Äpfel". This is done in PHP rather than in the collation, at a cost of roughly 1.5µs per row compared. Two limits: an `=` comparison whose value is ASCII-only uses the built-in `NOCASE` collation so that it can still use an index, meaning `title=apfel` does not match "Äpfel" (`title=äpfel` does), and one whose value is not compares every row unless fold indexes are on (see below); and ligature expansions follow MySQL 8 (`ß` as `ss`, `æ` as `ae`) rather than MySQL 5.7/MariaDB. |
| Sorting text | Sorts use `COLLATE NOCASE`, which folds ASCII letters only, so accented letters sort after `z` (`apple, zebra, Äpfel`) rather than with their base letter as MySQL does. Enable `$config->dbOptions['sqlite']['unicodeSort']` to match MySQL's ordering, at the cost of sorting in PHP — SQLite then calls back into PHP for every comparison and cannot use an index to avoid the sort. |
| Fulltext search | With FTS5 (see Fulltext search below), as MySQL, except: every word is indexed (no stopwords, no minimum word length, so `~=ab` can match where MySQL matches nothing); relevance values differ in scale (only their order matters); query expansion (`~+=`, `*+=`, `**+=`) matches as natural language; and a few folding differences (`strasse` does not match "Straße"). Without FTS5 (a database created before it, or `fulltext` turned off), FULLTEXT keys are regular indexes and the fulltext operators use `LIKE`/`REGEXP`: no relevance ordering; stopwords are not ignored; word operators such as `~=` also match partial words; query expansion and boolean commands (`#=`) are approximated. |
| Column types | Not enforced (SQLite type affinity). `UNSIGNED`, display widths, `CHARACTER SET` and `COLLATE` are ignored, `ENUM`/`SET` become `TEXT`, and `VARCHAR` lengths are not enforced. |
| Times | `NOW()`, `UNIX_TIMESTAMP()` and similar functions use PHP's time zone. `DEFAULT CURRENT_TIMESTAMP` uses the system's local time (MySQL uses the server's time zone). `ON UPDATE CURRENT_TIMESTAMP` is ignored. |
| `GROUP_CONCAT()` | No length limit (MySQL's `group_concat_max_len` does not apply). |
| JSON functions | `JSON_EXTRACT()`, `JSON_SET()`, `JSON_INSERT()`, `JSON_REPLACE()`, `JSON_REMOVE()`, `JSON_ARRAY()`, `JSON_OBJECT()`, `JSON_QUOTE()` and `JSON_VALID()` are provided by SQLite under the same names and use the same `$.path` syntax. `JSON_UNQUOTE()`, `JSON_LENGTH()` and `JSON_CONTAINS()` are emulated (results verified against MySQL 8). Other MySQL JSON functions (`JSON_KEYS()`, `JSON_SEARCH()`, `JSON_CONTAINS_PATH()`, `JSON_DEPTH()`, `JSON_TYPE()` and others) are not available, and path wildcards (`$.a[*]`, `$**.b`) are not supported by the emulated functions. Check `$database->dialect()->supportsJson()` before using any of them. |
| `TRUNCATE` | Deletes all rows and resets the auto-increment counter. |
| Unfinished `SELECT` | SQLite cannot drop or rebuild a table while a `SELECT` on it is unfinished. ProcessWire closes such cursors and retries, so further fetches from them return nothing. |
| Concurrency | One writer at a time. Other writers wait (up to 5 seconds). Transactions start with `BEGIN IMMEDIATE` (taking the write lock up front), so a transaction that reads before it writes waits for other writers rather than failing with "database is locked". |
| No-op statements | `LOCK TABLES`, `UNLOCK TABLES`, `SET ...` (i.e. `SET NAMES`), `OPTIMIZE`/`REPAIR`/`CHECK TABLE`. (`ANALYZE TABLE` gathers SQLite's planner statistics.) |

Not supported (throws an exception):

- `MATCH ... AGAINST` without FTS5 (an exception says so): check `supportsFulltext()` and use `LIKE` or `REGEXP`
  instead, or let `DatabaseQuerySelectFulltext` handle it. With FTS5, `MATCH` on columns that no FULLTEXT key
  has exactly raises MySQL's error 1191, as MySQL does. SQLite's own `x MATCH y` operator is passed through.
- `FOUND_ROWS()` (`SQL_CALC_FOUND_ROWS` is ignored): use `COUNT(*)`, or check `supportsFoundRows()`.
- `UPDATE` with `JOIN`, and multi-table `DELETE` with more than one target table
  (`DELETE t FROM t JOIN ...` with one target is supported).
- `SELECT ... FOR UPDATE` / `LOCK IN SHARE MODE`, user variables (`@var`), stored procedures.
- `ALTER TABLE ... ADD CONSTRAINT` and `RENAME INDEX`.
- `ALTER TABLE` operations that require rebuilding a table (`MODIFY`, `CHANGE`, primary key changes) on
  tables with triggers (other than those of FULLTEXT keys), CHECK/FOREIGN KEY/UNIQUE constraints, or indexes
  not created through ProcessWire.

#### Fulltext search

When SQLite has FTS5 (standard in PHP's builds), each MySQL `FULLTEXT KEY name (columns)` on table `t`
becomes an FTS5 table `t__fts_name`, holding the text and the row's primary key, kept in sync by the
triggers `t__fts_name_ai`, `_ad` and `_au`. `MATCH ... AGAINST` is translated to a lookup in it (boolean
and natural language mode, with MySQL's boolean syntax), so the selector fulltext operators work as on
MySQL, with relevance ordering.

- Words are matched case- and accent-insensitively, and `_` is part of a word (as InnoDB).
- `getIndexes()` and `SHOW INDEX` report the key as `FULLTEXT`, `SHOW CREATE TABLE` (and so backups)
  include it, and `getTables()`/`SHOW TABLES` do not list the FTS5 tables.
- Only databases created with FTS5 support use it: a new database gets a marker table, `pw_fulltext_v1`.
  Databases created before it keep using `LIKE`/`REGEXP` for the fulltext operators until reinstalled.
- To turn it off, set `$config->dbOptions = ['sqlite' => ['fulltext' => false]];` before the database is
  created.
- Differences from MySQL are listed in the table above.

#### Fold indexes

An `=` (or `!=`, `<`, `>`...) comparison of text with a value that is not ASCII-only (i.e. `title=Crème brûlée`) is
folded in PHP (`COLLATE pw_ci`), so it compares every row: about 17 ms rather than 0.3 ms at 20,000 pages. With
`$config->dbOptions = ['sqlite' => ['foldIndex' => true]];` each index on a single text column gets a companion
index on `pw_fold(column)` (named `table__index__fold`, and not reported by `getIndexes()`, `SHOW INDEX` or
`SHOW CREATE TABLE`), and such comparisons become `pw_fold(column) = pw_fold(value)`, which can use it.

- The companion indexes are added on the next connection after turning it on, and dropped on the next one after
  turning it off (a marker table, `pw_fold_index_v1`, records that they are there).
- **Off by default**, because the database file then needs ProcessWire's `pw_fold()` function: other tools, i.e.
  the `sqlite3` command line or a database browser, can still read it, but cannot insert, update or delete rows in
  those tables, `VACUUM` it or run `PRAGMA integrity_check`. Turn it off (and connect once) before doing those.

### PostgreSQL

PostgreSQL support is experimental. The installer offers it as a database type when PHP's `pdo_pgsql`
extension is available (CLI installer: `'dbType' => 'pgsql'` with `dbName`, `dbUser`, `dbPass`, `dbHost`,
`dbPort` or `dbSocket`). The installer creates the database when it does not exist and the user may do so.
Text comparisons ignore case and accents as on MySQL, using the `unaccent` extension (see "Case- and
accent-insensitive matching" below).

- **Requirements:** PHP's `pdo_pgsql` extension and PostgreSQL 16.0 or newer (checked on connect). The
  `pg_trgm` extension is recommended: the installer creates it when it can, and `FULLTEXT` indexes then
  also get trigram indexes that speed up `LIKE`/`REGEXP` searches.
  On PHP 8.4+, connections use `Pdo\Pgsql`.
- **How it works:** ProcessWire's SQL (MySQL syntax) is translated by `WireDatabasePgsqlTranslator`. The
  translator reads column types from the catalog (cached per connection) so that it can reproduce MySQL's
  loose comparisons, and every table's indexes are named `table__index` because PostgreSQL index names are
  unique per schema rather than per table. Schema introspection (`getTables()`, `getColumns()`, `getIndexes()`,
  `tableExists()`, `columnExists()`, `indexExists()`) queries `information_schema` and `pg_catalog` directly
  and returns the same values the MySQL dialect returns (MySQL type names, `auto_increment` for identity
  columns, `PRIMARY` for the primary key), while `SHOW` and `DESCRIBE` queries issued by module code are
  emulated by the translator. Statements that translate to several PostgreSQL statements (`CREATE TABLE`
  with indexes, `RENAME TABLE`, some `ALTER TABLE` forms) are executed together when prepared.
- **Settings:** `$config->dbOptions['pgsql']` may contain `schema` (a schema other than `public`, added to
  `search_path`), `trigram` (set to false when `pg_trgm` cannot be installed, so `FULLTEXT` indexes are
  skipped rather than failing), `savepoints`, `fold`, `fulltext`, `emulatePrepares`, `randomPageCost`, `translationCache` and `translationCacheMax` (see the table below). Assign the whole array, as in
  `$config->dbOptions = ['pgsql' => ['schema' => 'processwire']];`
- **Connection:** the session time zone is set to PHP's, so `NOW()` matches PHP's time as it does with MySQL.
  Errors for unknown tables and columns and for duplicate keys use MySQL's SQLSTATE codes (`42S02`, `42S22`,
  `23000`) and are reported at `execute()` rather than `prepare()`, as with MySQL; `errorInfo()` carries
  MySQL's error numbers (1146, 1054, 1062) and the original PostgreSQL message. In debug mode, failed
  queries are logged with their translation to `site/assets/logs/pgsql-errors.txt`.
- **Values:** `pdo_pgsql` returns integer columns as PHP ints and everything else as strings, which is what
  `pdo_mysql` does on PHP 8.1+. Booleans come back as PHP `true`/`false` only from expressions that are
  boolean in PostgreSQL (i.e. `SELECT 1=1`); `TINYINT(1)` columns are `smallint` and return ints.

Behavior differences from MySQL:

| Area | PostgreSQL behavior |
|------|---------------------|
| Case- and accent-insensitive matching | Comparisons of text columns ignore case and accents as MySQL's default collations do: `title=hello world` matches "Hello World", `title%=apfel` matches "Äpfel", `title*=creme` matches "Crème", and `$users->get("email=admin@example.com")` finds "Admin@Example.com". The translator compares `pw_fold(column)` with `pw_fold(value)` for `=`, `!=`, `<`, `>`, `IN` and `LIKE` whenever a text column is compared with a string or a bound value, in any SQL, core or module. `REGEXP` ignores case but not accents, as MySQL's does (the folded form is added only so that the trigram index can narrow the rows). Selector `^=` and `$=` use `REGEXP`, as on MySQL, so `title^=zurich` does not match "Zürich" on either. `pw_fold()` is `lower(unaccent(text))`, created in your schema at install or on the first connection (the `unaccent` extension is a trusted extension, so a database owner can create it; without it, comparisons ignore case only and the reason is logged to `pgsql-errors`). Folding covers ligatures such as `ß` as `ss`. Limits: comparisons between two columns (joins) are not folded; `UNIQUE` keys stay exact, so "Home" and "home" can both exist where MySQL would reject the second; `lower()` follows the database's `LC_CTYPE`, so on a database created with the `C` locale only ASCII letters and accented letters (after `unaccent`) are lowercased. Set `$config->dbOptions['pgsql']['fold']` to false for exact comparisons. If `unaccent`'s rules change, rebuild the folded indexes with `REINDEX`. |
| Sorting text | `ORDER BY` a text column sorts by `pw_fold(column)`, so case and accents are ignored as on MySQL; the order of folded values follows the database's collation. Expressions (such as the multi-language fallback `IF(data1012 != '', data1012, data)`) are sorted as they are, without folding, and `SELECT DISTINCT` queries are not folded.  NULLs sort first ascending and last descending, as in MySQL: `NULLS FIRST`/`NULLS LAST` is added to terms that can be NULL (expressions, nullable columns, and columns of `LEFT JOIN`ed tables, i.e. a field some pages have no row for); a `NOT NULL` column of an inner table is left alone, so that an index can still serve the sort. |
| Fulltext search | `MATCH ... AGAINST` translates to PostgreSQL full text search, so selector fulltext operators take the same paths as on MySQL, with relevance ordering. As a condition, `MATCH(data) AGAINST(:v IN BOOLEAN MODE)` becomes `pw_tsvector(data) @@ pw_tsquery(:v, true)`; as a value (a score), `ts_rank(...)`. A `FULLTEXT` key gets a stored tsvector column for each of its columns (`data__tsv`, generated from `pw_tsvector(data)`) and a GIN index on it (`name__fts`), besides its trigram index, so that ranking reads the stored vector rather than parsing every matching row again (about 50 times slower). Neither is reported by `getColumns()`, `getIndexes()` or `SHOW`, `SELECT *` and `t.*` leave the column out, and `DROP`/`MODIFY`/`CHANGE`/`RENAME COLUMN` and `DROP INDEX` keep it in step. The column takes about 1.5 times the space of the text it indexes. `pw_tsvector()` uses the `pw_search` text search configuration: `simple` (no stemming or stopwords, as MySQL's FULLTEXT) with `unaccent`, so searches ignore case and accents; words are split at `-`, `.`, `@`, `/` and `:` as MySQL splits them, and markup is skipped rather than indexed. `pw_tsquery()` reads MySQL's boolean syntax: `+word`, `-word`, `word*`, `"phrase"` and `(...)` groups; the relevance operators `>`, `<` and `~` are ignored, and words without an operator are left out when a query has required words, since MySQL uses those only for ranking. Natural language mode and `WITH QUERY EXPANSION` match any of the words (PostgreSQL has no query expansion). Differences: stopwords are indexed and there is no minimum word length, so short words and stopwords match; relevance values differ from MySQL's, so only the order they give is comparable, and not always that: MySQL weighs a word by how rare it is in each table, `ts_rank()` does not, so with matches in different fields (`a|b~=`) or with a common word the order can differ. The functions and configuration are created at install or on the first connection, and that first connection also adds a tsvector index on `pw_tsvector(column)` to existing `FULLTEXT` keys (not the stored column, which would rewrite the table under an exclusive lock: re-add a key to get it), built `CONCURRENTLY` so that writes continue (this takes a while on large tables; an advisory lock keeps other connections from doing the same, and they use `LIKE`/`REGEXP` until it is done). An index that cannot be built, i.e. on a table the database user does not own, is logged to `pgsql-errors` and `MATCH` on it works without an index. Sites whose `FULLTEXT` keys were skipped (`trigram` false) get no tsvector index from this: re-add those keys. If setup fails, the reason is logged and fulltext operators use `LIKE`/`REGEXP` as on SQLite. Setup runs once: if the `unaccent` extension is added later, drop the `pw_fulltext_v1()` marker function so that the next connection sets up again, then `REINDEX` the tsvector indexes. Set `$config->dbOptions['pgsql']['fulltext']` to false to use `LIKE`/`REGEXP` always. Rebuild the tsvector indexes with `REINDEX` if `unaccent`'s rules change. |
| Performance settings | Statements are prepared by PDO (`ATTR_EMULATE_PREPARES`), as with pdo_mysql, rather than on the server, which would be one more round trip per statement; set `$config->dbOptions['pgsql']['emulatePrepares']` to false for server-side prepares. The connection sets `random_page_cost` to 1.1 when the server has not set it: PostgreSQL's default (4) assumes spinning disks and makes the planner read whole tables where an index scan is several times faster on SSD or cached data, i.e. fulltext searches on common words. Set `$config->dbOptions['pgsql']['randomPageCost']` to another value, or to false to keep the server's. Translated SQL is kept between requests when OPcache is enabled (`translationCache`, true/false to force), since translating costs about 0.1 ms per statement: in a PHP file in `/site/assets/cache/WireDatabasePgsql/` per schema version, the `pw_schema_version` sequence, which moves on after every schema change made through ProcessWire (at commit, inside a transaction), so that all requests on all servers then translate against the new schema. A request that changes the schema neither uses nor adds kept translations after the change. Up to `translationCacheMax` (2000) are kept. After changing the schema outside ProcessWire (i.e. in psql), call `$database->dialect()->clearTranslationCache()`. |
| Transactions | A failed statement inside a PostgreSQL transaction normally aborts the whole transaction. ProcessWire wraps each statement in a savepoint while a transaction is open so that a caught error does not abort it, as with MySQL. This costs two extra round trips per statement inside a transaction (`SAVEPOINT` and `RELEASE`), and each savepoint that writes uses a subtransaction: a transaction with more than 64 of them overflows PostgreSQL's per-session subtransaction cache, which slows snapshots for all sessions until it ends. Set `$config->dbOptions['pgsql']['savepoints']` to false to skip savepoints when your code does not rely on continuing after a failed statement inside a transaction (large imports, high-latency database hosts). |
| Column types | `TINYINT`/`SMALLINT` become `smallint`, `INT` becomes `integer`, `BIGINT` becomes `bigint`, `FLOAT` is `real`, `DOUBLE` is `double precision`, `DECIMAL` is `numeric`, `CHAR(n)` is `varchar(n)` (PostgreSQL pads `char(n)`), all `TEXT` types are `text`, `DATETIME`/`TIMESTAMP` are `timestamp`, `BLOB` types are `bytea`, `JSON` is `jsonb`, `ENUM`/`SET` are `text` (values are not restricted). `UNSIGNED` is ignored, so `INT UNSIGNED` is the signed 32-bit `integer` (maximum 2,147,483,647 rather than 4,294,967,295); display widths, `CHARACTER SET` and `COLLATE` are ignored too. `AUTO_INCREMENT` columns are identity columns; inserting an explicit id moves the sequence past it (never backwards), and `lastInsertId()` then returns the sequence position, which is the inserted id unless that id is lower than one already in the table. `ALTER TABLE ... MODIFY` redefines the column as MySQL does: without `NOT NULL` it becomes nullable, without `DEFAULT` it has none, and text changed to a number converts as MySQL does: the leading number, with its fraction and exponent (`'12.5'`, `'-3.75x'`, `'.5'`, `'1e3'`), rounded for an integer type, and `''` and `'abc'` become 0. |
| Times | `NOW()`, `UNIX_TIMESTAMP()` and similar functions use PHP's time zone (set on the connection). `DEFAULT CURRENT_TIMESTAMP` works, and `ON UPDATE CURRENT_TIMESTAMP` is a trigger (`pw_on_update_now()`) that sets the column when an `UPDATE` changes the row without changing the column, as MySQL does; `CHANGE`, `MODIFY` and `RENAME COLUMN` keep it in step. One difference: setting the column explicitly to the value it already has bumps it, where MySQL keeps it, since the trigger cannot tell that apart from not setting it. MySQL's zero dates (`'0000-00-00'`, `'0000-00-00 00:00:00'`) are NULL in date columns: given to a date column (literal or bound, in `INSERT` values and `SET`) they are stored as NULL, a column defaulting to one is nullable with no default, and a literal compared with a date column works (`= '0000-00-00 00:00:00'` is `IS NULL`, `!=` and `>` are `IS NOT NULL`). A bound zero date compared with a date column matches nothing: compare with `IS NULL`, or a literal. Text columns keep such values as text. Rows read back have NULL where MySQL would return the zero date. `DATETIME`, `TIMESTAMP` and `TIME` hold whole seconds, as in MySQL, rounding a fraction (`DATETIME(6)` keeps its precision); columns made before this hold microseconds. |
| Comparisons | MySQL compares a number column to a string by converting the string to its leading number (`'12.5x'` is 12.5, `'.5'` is 0.5, `'1e3'` is 1000, `''` and `'abc'` are 0). The translator does the same for columns whose type it knows (`table.column`, or a bare column in a single-table statement); comparisons against columns of derived tables are passed through and PostgreSQL reports a type mismatch. A text column compared with a number column (i.e. a repeater's item ids, `'1234,1235'`, joined to `pages_id`) compares the text's leading number, as MySQL does. `LIKE`/`REGEXP` on number and date columns compare the value as text, as MySQL does. |
| `GROUP BY` | PostgreSQL requires every selected column to be grouped or aggregated (like MySQL's `ONLY_FULL_GROUP_BY`). The translator wraps ungrouped `table.column` terms in `SELECT` and `ORDER BY` with `any_value()` and lets `HAVING` reference select-list aliases; other ungrouped expressions are reported by PostgreSQL. |
| Locks | `GET_LOCK()`, `RELEASE_LOCK()` and `IS_FREE_LOCK()` use advisory locks; `GET_LOCK()` does not wait for its timeout (it returns 0 at once when the lock is taken). `LOCK TABLES`/`UNLOCK TABLES` are no-ops. |
| `GROUP_CONCAT()` | Becomes `string_agg()`, with no length limit. |
| JSON functions | `JSON_EXTRACT()`, `JSON_UNQUOTE()`, `JSON_CONTAINS()`, `JSON_LENGTH()`, `JSON_SET()`, `JSON_INSERT()`, `JSON_REPLACE()`, `JSON_REMOVE()` and `JSON_VALID()` are translated to `pw_json_*()` functions over `jsonb`, created at install or on the first connection; `JSON_ARRAY()`, `JSON_OBJECT()` and `JSON_QUOTE()` become PostgreSQL's own. Documents may be `JSON` (`jsonb`) or text columns, paths use MySQL's syntax (`$.a.b`, `$."a key"`, `$[0]`, `$.a[*]`, `$**.b`), and the results match MySQL 8's for the cases in the SQLite tests (JSON results use MySQL's formatting, with a space after `:` and `,`). `supportsJson()` is true once the functions exist. `JSON_CONTAINS()` follows MySQL's rules, which also find a candidate inside the elements of arrays at any level (`{"a":1}` in `{"a":[1,2]}`, `[1,2]` in `[[1,2],[3,4]]`), where `jsonb`'s `@>` does not. `JSON_EXTRACT()` compared with a string or bound value compares it as a JSON string, as MySQL converts it (`JSON_EXTRACT(data, '$.color') = 'green'`). A `JSON` column (`jsonb`) also gets indexes, which MySQL has no equivalent of: a GIN index, and a partial index of the documents that have arrays within arrays. `JSON_CONTAINS(column, value[, '$.key.path'])` used as a condition (not after `NOT`) is narrowed by them, with a lax jsonpath of the value's contents and the documents with nested arrays, and decided by the MySQL-rule function on those rows only (about 50 times faster than scanning on 50,000 rows). The indexes are internal and not reported by `getIndexes()`; the connection after an upgrade adds the partial index to existing `JSON` columns. Not translated: `JSON_EXTRACT()` with several paths, `JSON_KEYS()`, `JSON_SEARCH()`, `JSON_CONTAINS_PATH()`, `JSON_TYPE()`, `JSON_DEPTH()` and others, and MySQL's `->`/`->>` shorthand, which PostgreSQL reads as its own key operators (write `JSON_EXTRACT()` and `JSON_UNQUOTE()` instead). |
| `TRUNCATE` | Deletes all rows and restarts the identity sequence. |
| Identifiers | Unquoted names are folded to lowercase by PostgreSQL. ProcessWire's names are lowercase already; the translator quotes aliases that contain uppercase letters (i.e. `AS numChildren`) and every reference to them so that result keys keep their case. |
| Indexes | Named `table__index` in PostgreSQL; `getIndexes()` and `SHOW INDEX` strip the table prefix and report expression indexes by their column. A name over PostgreSQL's 63 bytes gets a hashed tail, and its MySQL name is kept in the index comment, which `getIndexes()` reports. Indexes on text columns are built on `pw_fold(column)`: a hash index for an unbounded `TEXT` column (equality), a btree for `VARCHAR`, and a trigram GIN index for a `FULLTEXT` key. A `UNIQUE` or primary key on text keeps its exact index and gets a folded companion (`name__fold`, not reported by `getIndexes()`), which `DROP INDEX` and renames handle too. Without folding, a prefix length on a text column (`KEY name (data(191))`) becomes an expression index on `left(data, 191)`. Sites installed before folding was added have unfolded text indexes: results are correct, but text lookups do not use an index until the table's indexes are rebuilt. |
| Multi-statement DDL | A `CREATE TABLE` or `ALTER TABLE` that becomes several statements cannot take bound parameters: `execute()` with parameters throws. |
| No-op statements | `SET ...` (i.e. `SET NAMES`), `LOCK TABLES`, `UNLOCK TABLES`, `OPTIMIZE`/`ANALYZE`/`REPAIR`/`CHECK TABLE`, and `ALTER TABLE ... ENGINE/AUTO_INCREMENT/COMMENT/CHARSET`. |

Not supported (throws an exception):

- `MATCH ... AGAINST` when full text search is not set up (an exception says so): check `supportsFulltext()`
  and use `LIKE` or `REGEXP` instead, or let `DatabaseQuerySelectFulltext` handle it.
- `FOUND_ROWS()` (`SQL_CALC_FOUND_ROWS` is ignored): use `COUNT(*)`, or check `supportsFoundRows()`.
- `SHOW CREATE TABLE`. Backups and site profile exports (`WireDatabaseBackup`) take each table's `CREATE TABLE`
  from the schema log instead (see below), which keeps what translation loses. For a table's current structure
  use `getColumns($table, 3)` and `getIndexes($table, true)`. A site installed on PostgreSQL before the schema log
  existed has no log entries for its tables, so its export stops with an error naming such a table: reinstall it.
- `UPDATE` with `LEFT`/`RIGHT JOIN`, `UPDATE` that sets columns of more than the first table, and multi-table
  `DELETE` with more than one target table. (`UPDATE t JOIN u ON ... SET t.x=u.y` and `UPDATE t, u SET ...`
  become `UPDATE ... FROM`; `DELETE t FROM t JOIN ...` with one target, `UPDATE ... ORDER BY ... LIMIT` and
  `DELETE ... LIMIT` are supported.)
- `LOCK IN SHARE MODE`, user variables (`@var`, `@@var`), stored procedures, `SOUNDS LIKE`, `<=>`, and
  MySQL collation names in `COLLATE` clauses. `SELECT ... FOR UPDATE` is passed through and works.
- Nothing in `ALTER TABLE` beyond these, which are supported: `ADD/DROP/MODIFY/CHANGE COLUMN`, `ADD/DROP INDEX`,
  `ADD/DROP PRIMARY KEY`, `RENAME COLUMN`, `RENAME INDEX`, `ADD [CONSTRAINT name] FOREIGN KEY ... REFERENCES ...`,
  `ADD [CONSTRAINT name] CHECK (...)`, `ADD CONSTRAINT name UNIQUE` (a unique index, as MySQL makes it),
  `DROP FOREIGN KEY`, `DROP CHECK` and `DROP CONSTRAINT`. A foreign key violation reports MySQL's SQLSTATE
  (`23000`, error 1452). Unlike MySQL, PostgreSQL does not create an index for a foreign key's columns.
- MySQL functions without a translation are passed through and fail unless PostgreSQL has a function of the
  same name. Translated, with MySQL's results (checked against MariaDB):
  - times: `NOW()`, `SYSDATE()`, `CURRENT_TIMESTAMP()`, `LOCALTIME()` (the local time in whole seconds, as MySQL
    gives it), `CURDATE()`, `CURTIME()`, `UTC_TIMESTAMP()`, `UTC_DATE()`, `UNIX_TIMESTAMP()`, `FROM_UNIXTIME()`,
    `DATE_FORMAT()` and `STR_TO_DATE()` (literal formats; `STR_TO_DATE()` gives a date or a datetime as the format
    has time parts), `DATE_ADD()`/`DATE_SUB()` with `INTERVAL`, `DATE()`, `YEAR()`, `MONTH()`, `DAY()`,
    `DAYOFMONTH()`, `HOUR()`, `MINUTE()`, `SECOND()`, `QUARTER()`, `DAYOFYEAR()`, `DAYOFWEEK()` (1 is Sunday),
    `WEEKDAY()` (0 is Monday), `DAYNAME()`, `MONTHNAME()`, `LAST_DAY()`, `DATEDIFF()`, `TIMESTAMPDIFF()`
    (`SECOND` to `YEAR`, truncated as MySQL does);
  - strings: `CONCAT()` (NULL when an argument is NULL, as in MySQL), `LENGTH()` (bytes, as in MySQL),
    `FIND_IN_SET()` and `INSTR()` (ignoring case), `LOCATE()`, `SUBSTRING_INDEX()` (literal count), `MID()`,
    `LCASE()`, `UCASE()`, `SPACE()`, `GROUP_CONCAT()`, `CONVERT(x USING charset)` and `CONVERT(x, type)`;
  - others: `IF()`, `IFNULL()`, `ISNULL()`, `FIELD()`, `GREATEST()`/`LEAST()` (NULL when an argument is NULL),
    `LOG()` (natural logarithm, as in MySQL), `LOG2()`, `TRUNCATE()`, `SHA2()`, `UUID()`, `RAND()`, `DATABASE()`,
    `LAST_INSERT_ID()`, `CAST()` type names and the lock functions.

  `CONCAT_WS()`, `COALESCE()`, `NULLIF()`, `CHAR_LENGTH()`, `LOWER()`, `UPPER()`, `TRIM()`, `REPLACE()`, `LEFT()`,
  `RIGHT()`, `SUBSTRING()` (with a positive start), `LPAD()`, `RPAD()`, `REPEAT()`, `REVERSE()`, `MD5()`, `ABS()`,
  `ROUND()`, `FLOOR()`, `CEIL()`, `MOD()`, `POW()`, `LN()`, `LOG10()` and `EXP()` exist in PostgreSQL. Missing, for
  example: `SHA1()` (PostgreSQL has no built-in SHA-1; use `SHA2()` or `MD5()`), `WEEK()`, `YEARWEEK()`,
  `ROW_COUNT()`, `SUBSTRING()` with a negative start, and `HOUR()`/`MINUTE()` on a `TIME` value.

---

### Schema log

Every change to the schema (`CREATE`, `ALTER`, `DROP` and `RENAME` of tables and indexes) made through
`$database` is recorded in the `schema_log` table, on every database type and always in MySQL syntax. On SQLite
and PostgreSQL that is the SQL as ProcessWire issued it, before translation, so the log keeps what translation
loses (column types, `ENUM` values, `UNSIGNED`, index prefix lengths). Replaying it on an empty MySQL database
reproduces the site's schema, i.e. when converting a site to MySQL.

- The first change on a site starts the log with a baseline: the `CREATE TABLE` of every existing table (from
  `SHOW CREATE TABLE`). After that, each successful change is added. Failed statements are not recorded.
- Dropping a table removes its entries. Renaming one moves its entries to the new name.
- `$database->schemaLog()->getEntries()` returns the entries in replay order.
- Restoring a backup with `WireDatabaseBackup` is not recorded, since the dump carries its own `schema_log`.
- Changes made outside `$database` (i.e. by a module using PDO directly, or in a database client) are not
  recorded, and neither are temporary tables.
- `$database->schemaLog()->getCreateTables()` replays the log into one MySQL `CREATE TABLE` per table (see
  `WireDatabaseSchemaReplay`). A table whose history has a statement the replay can't apply with certainty is
  listed under `failed` rather than guessed.
- On SQLite and PostgreSQL, `WireDatabaseBackup` (and so a site profile export) takes each table's `CREATE TABLE`
  from the replay, so the dump installs on MySQL as the original schema. A table the log doesn't cover, or whose
  columns no longer match it, falls back to `SHOW CREATE TABLE`, with index prefix lengths added for MySQL.
- On SQLite and PostgreSQL the installer sets `$config->dbCharset = 'utf8mb4'` and `$config->dbEngine = 'InnoDB'`,
  which are the table options new tables are created with in the log.

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
| `true` | Request a `WireDatabasePDOStatement` (with SQLite and PostgreSQL, always a `WireDatabaseSQLiteStatement` or `WireDatabasePgsqlStatement`, which extend it) |
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
(MySQL InnoDB tables, and always with SQLite and PostgreSQL).

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
| `2` | Raw column information in MySQL `SHOW COLUMNS` format (emulated with SQLite and PostgreSQL) |
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
