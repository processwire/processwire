# DatabaseQuery

An abstract base class for building SQL queries with parameterized bind values. Provides a fluent interface for composing query clauses that can be safely passed between methods and objects without knowledge of what other methods have added. Subclasses include `DatabaseQuerySelect` for SELECT queries and `DatabaseQuerySelectFulltext` for fulltext search queries.

You do not typically instantiate `DatabaseQuery` directly—use one of its concrete subclasses instead.

```php
$query = new DatabaseQuerySelect();
$query->select("id, name")->from("pages")->where("status>?", 0)->orderby("name")->limit(25);
```

## Building queries (fluent interface)

All registered query methods (such as `select`, `from`, `where`, `join`, `leftjoin`, `orderby`, `groupby`, `limit`) are available as chainable methods via `__call()`. Each call appends to the existing clause, allowing multiple calls to build up complex queries incrementally.

```php
$query = new DatabaseQuerySelect();
$query->select("id, name, status");
$query->from("pages");
$query->from("pages_sortfields"); // adds another table to FROM
$query->where("pages.parent_id=?", 10);
$query->where("pages.status>?", 0); // appends AND clause
$query->orderby("sortfield");
$query->limit(50);
```

### Named parameters

Pass bind values as an associative array in the second argument. Keys must start with `:` (the colon is auto-prepended if omitted):

```php
$query->where("name=:name AND status=:status", [':name' => 'about', ':status' => 1]);
```

### Implied parameters (positional)

Use `?` placeholders with a regular (numeric-keyed) array of values:

```php
$query->where("name=? AND status=?", ['about', 1]);
```

When there is only one `?` placeholder, the value does not need to be wrapped in an array:

```php
$query->where("parent_id=?", 10);
```

### Resetting a clause

Pass `null` to clear an existing clause (since 3.0.257):

```php
$query->where(null); // clears all WHERE conditions
```

### Combining queries

Use `copyTo()` to merge clauses from one query into another:

```php
$queryA = new DatabaseQuerySelect();
$queryA->where("status>?", 0);
$queryA->orderby("name");

$queryB = new DatabaseQuerySelect();
$queryA->copyTo($queryB);  // copies all clauses to $queryB
$queryA->copyBindValuesTo($queryB); // copies bind values too
```

## Bind values

### bindValue($key, $value, $type = null)

Bind a named parameter to a value. The key is auto-prefixed with `:` if not already present.

```php
$query->bindValue(':name', 'hello');
$query->bindValue('status', 1);  // colon auto-prepended
```

The optional `$type` argument accepts `'string'`, `'int'`, `'bool'`, `'null'`, or a `PDO::PARAM_*` constant.

### bindValueGetKey($value, $type = null)

Bind a value and return a unique auto-generated key name in one step. Useful for building dynamic queries:

```php
$key = $query->bindValueGetKey('hello'); // returns a unique key like ":s0X"
$query->where("name=$key");
```

### bindValues($bindValues = null)

Get or set multiple bind values at once. When called with no arguments, returns the current bind values array. When given an array, merges those values into the existing bind values (does not replace).

```php
// Get all bind values
$values = $query->bindValues();

// Set multiple bind values
$query->bindValues([':name' => 'about', ':status' => 1]);
```

### getBindValues($options = array())

Get bind values with options. Supports the following options:

- `query` (\PDOStatement|DatabaseQuery): Copy bind values to this query object.
- `count` (bool): Return count instead of array (since 3.0.157).
- `inSQL` (string): Only return bind values referenced in the given SQL string.

```php
// Copy to a PDOStatement
$stmt = $pdo->prepare($query->getQuery());
$query->getBindValues(['query' => $stmt]);

// Count bind values
$count = $query->getBindValues(['count' => true]);
```

### copyBindValuesTo($query, $options = [])

Copy bind values from this query to another `DatabaseQuery` or `PDOStatement`. Returns the number of values copied.

```php
$numCopied = $queryA->copyBindValuesTo($queryB);
```

### bindOption($optionName, $optionValue = null)

Get or set bind options. Options control how auto-generated bind keys are named:

- `prefix` (string): Prefix for auto-generated keys (default: `'pw'`).
- `suffix` (string): Suffix character (default: `'X'`).
- `global` (bool): Make keys globally unique across all instances (default: `false`).

```php
// Set global uniqueness for keys
$query->bindOption('global', true);

// Get all options
$allOptions = $query->bindOption(true);
```

## Generating SQL

### getQuery()

Returns the fully assembled SQL string. Also accessible via the `$query->query` or `$query->sql` property.

```php
$sql = $query->getQuery();
// or
$sql = $query->query;
```

### getSQL($method = '')

Alias for `getQuery()`. If `$method` is specified, returns the SQL for only that clause:

```php
echo $query->getSQL('where'); // just the WHERE clause SQL
echo $query->getSQL('orderby'); // just the ORDER BY clause
```

### getQueryMethod($method)

Returns the generated SQL for a specific query method/clause:

```php
$whereSQL = $query->getQueryMethod('where');
```

### getDebugQuery()

Returns the SQL query with bind parameters populated inline (for debugging only—not suitable for execution):

```php
echo $query->getDebugQuery();
// SELECT id,name FROM `pages` WHERE status>0 ORDER BY name
```

## Execution

### prepare()

Prepares and returns a `\PDOStatement` with all bind values applied:

```php
$stmt = $query->prepare();
$stmt->execute();
$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

### execute($options = [])

Prepares and executes the query in one step. Handles connection loss with automatic retry.

Options:

- `throw` (bool): Throw exceptions on error (default: `true`).
- `maxTries` (int): Max retries on connection loss (default: `3`).
- `returnQuery` (bool): Return `\PDOStatement` if true, `bool` result of execute if false (default: `true`).

```php
// Execute and get PDOStatement
$stmt = $query->execute();

// Execute and get bool result
$ok = $query->execute(['returnQuery' => false]);
```

## Properties

| Property        | Type      | Description                                              |
|-----------------|-----------|----------------------------------------------------------|
| `query` / `sql` | `string`  | The generated SQL query string                           |
| `bindValues`    | `array`   | Associative array of `[':key' => value]` bind parameters |
| `bindKeys`      | `array`   | Array of all bind key names                              |
| `bindOptions`   | `array`   | Bind key generation options                              |

---

# DatabaseQuerySelect

A concrete `DatabaseQuery` subclass for building SELECT queries. This is the most commonly used query class in ProcessWire.

```php
$query = new DatabaseQuerySelect();
$query->select("*")->from("pages")->where("status>?", 0);
$sql = $query->getQuery(); // SELECT * FROM `pages` WHERE status>0
```

## Query clauses

All clauses are available as fluent methods (inherited from `DatabaseQuery.__call()`):

### select($columns, $params = [])

Add columns to the SELECT clause:

```php
$query->select("id, name, status");
$query->select("count(*) AS total"); // append
```

### from($table)

Add a table to the FROM clause. Tables are automatically backtick-quoted:

```php
$query->from("pages");  // FROM `pages`
$query->from("pages_sortfields"); // adds second table
```

### join($table, $params = [])

Add a JOIN clause:

```php
$query->join("templates ON templates.id=pages.templates_id");
```

### leftjoin($table, $params = [])

Add a LEFT JOIN:

```php
$query->leftjoin("field_title ON field_title.pages_id=pages.id");
```

### where($condition, $params = [])

Add a WHERE condition (first call) or AND condition (subsequent calls). Supports both named and implied bind parameters:

```php
$query->where("status>?", 0);
$query->where("parent_id=:pid", [':pid' => 10]);
```

### orderby($value, $prepend = false)

Add ORDER BY. Can be called multiple times. Pass `true` as second argument to prepend rather than append:

```php
$query->orderby("name");          // ORDER BY name
$query->orderby("created DESC");  // ORDER BY name, created DESC
$query->orderby("priority", true); // ORDER BY priority, name, created DESC
```

### groupby($columns)

Add GROUP BY. Supports HAVING as a shortcut:

```php
$query->groupby("parent_id");
// HAVING can be added as a "column" starting with HAVING:
$query->groupby("HAVING count>1");
```

### limit($limit)

Set the LIMIT clause:

```php
$query->limit(25);      // LIMIT 25
$query->limit("0, 25"); // LIMIT 0,25
```

## SQL caching

When `$config->dbCache` is `false`, `SQL_NO_CACHE` is prepended to the SELECT clause. This setting is static and shared across all instances.

## Comments

Set a `comment` property to add a SQL comment (only in debug mode, as PDO misinterprets `?` and `:param` patterns in comments):

```php
$query->set('comment', 'Fetch homepage children');
```

## Properties

| Property   | Type      | Description               |
|------------|-----------|---------------------------|
| `select`   | `array`   | SELECT column expressions |
| `from`     | `array`   | FROM table names          |
| `join`     | `array`   | JOIN clauses              |
| `leftjoin` | `array`   | LEFT JOIN clauses         |
| `where`    | `array`   | WHERE/AND conditions      |
| `orderby`  | `array`   | ORDER BY expressions      |
| `groupby`  | `array`   | GROUP BY expressions      |
| `limit`    | `array`   | LIMIT values              |
| `comment`  | `string`  | Debug comment             |

---

## Notes

- `DatabaseQuery` extends `WireData`, so all WireData methods (including `get()`, `set()`, `getArray()`) are available.
- Bind keys auto-generated by `getUniqueBindKey()` are guaranteed unique. The format varies based on value type and options (e.g. `:s0X` for strings, `:i0X` for integers, `:pw0X` when no value is given). When the `global` bind option is enabled, keys also incorporate the instance number (e.g. `:pw19s0X`) to ensure uniqueness across all DatabaseQuery instances.
- The `global` bind option ensures keys are unique across all DatabaseQuery instances (useful when combining queries).
- The `execute()` method automatically retries up to 3 times if a MySQL "server has gone away" (error 2006) occurs.
- The `__call()` method handles all registered query methods. It throws a `WireException` if named and implied parameters are mixed in a single clause.
- Implied parameters (`?`) were added in ProcessWire 3.0.157.
- The `merge()` method is deprecated; use `copyTo()` and `copyBindValuesTo()` instead.

---

# DatabaseQuerySelectFulltext


A class for building fulltext search queries using MySQL's `MATCH...AGAINST` syntax. It decorates a `DatabaseQuerySelect` object, adding WHERE and ORDER BY clauses for fulltext matching. Used internally by `PageFinder` to handle fulltext selector operators.

```php
$query = new DatabaseQuerySelect();
$query->select("pages.id")->from("pages");
$query->leftjoin("field_body ON field_body.pages_id=pages.id");

$ft = new DatabaseQuerySelectFulltext($query);
$ft->match('field_body', 'data', '*=', 'hello world');
// Now $query has MATCH/AGAINST WHERE clause and ORDER BY score
```

## match($tableName, $fieldName, $operator, $value)

The primary method. Updates the decorated query with the appropriate MATCH...AGAINST WHERE clause and ORDER BY score. This is called by Fieldtype modules and PageFinder.

Parameters:

- `$tableName` (string): Database table name.
- `$fieldName` (string): Column/field name (or array of names for multi-field search).
- `$operator` (string): Search operator (see table below).
- `$value` (string|int|array): Value to search for.

### Supported operators

| Operator                | Description                            | Method              |
|-------------------------|----------------------------------------|---------------------|
| `*=`                    | Contains phrase (partial last word)    | `matchPhrase`       |
| `*+=`                   | Contains phrase with query expansion   | `matchPhraseExpand` |
| `**=`                   | Contains match (non-boolean scored)    | `matchRegular`      |
| `**+=`                  | Contains match with query expansion    | `matchRegular`      |
| `^=`                    | Starts with                            | `matchStartEnd`     |
| `$=`                    | Ends with                              | `matchStartEnd`     |
| `~=`                    | Contains all full words                | `matchWords`        |
| `~+=`                   | Contains all full words + expand       | `matchWords`        |
| `~*=`                   | Contains all partial words             | `matchWords`        |
| `~~=`                   | Contains all words live (partial last) | `matchWords`        |
| `~\|=`                  | Contains any full words                | `matchWords`        |
| `~\|*=`                 | Contains any partial words             | `matchWords`        |
| `~\|+=`                 | Contains any words + expand            | `matchWords`        |
| `~%=`                   | Contains all words LIKE                | `matchLikeWords`    |
| `~\|%=`                 | Contains any words LIKE                | `matchLikeWords`    |
| `%=`                    | Contains phrase LIKE                   | `matchLikePhrase`   |
| `%^=`                   | Starts with LIKE                       | `matchLikeStartEnd` |
| `%$=`                   | Ends with LIKE                         | `matchLikeStartEnd` |
| `#=`                    | Boolean mode commands                  | `matchCommands`     |
| `=`                     | Equals                                 | `matchEquals`       |
| `!=`                    | Not equals                             | `matchEquals`       |
| `>` / `<` / `>=` / `<=` | Comparison                             | `matchEquals`       |

### Negation

Prefix the operator with `!` to negate the match (e.g., `!*=` for "does not contain phrase"):

```php
$ft->match('field_body', 'data', '!~=', 'spam');
```

## Configuration methods

### allowOrder($allow = null)

Get or set whether ORDER BY score statements are added. When `false`, no ORDER BY is added (useful when the calling object will negate results):

```php
$ft->allowOrder(false); // no ORDER BY score
```

### allowStopwords($allow = null)

Get or set whether fulltext searches fallback to LIKE for stopwords. Default is `true`:

```php
$ft->allowStopwords(false); // ignore stopwords completely
```

### forceLike($forceLike = null)

Force LIKE-based matching for operators that have LIKE equivalents (`*=`, `^=`, `$=`, `~=`, `~|=`):

```php
$ft->forceLike(true);
```

### getQuery()

Returns the `DatabaseQuerySelect` object being decorated:

```php
$query = $ft->getQuery();
```

## Notes

- `DatabaseQuerySelectFulltext` extends `Wire`, not `DatabaseQuery`. It decorates a `DatabaseQuerySelect` rather than extending it.
- The constant `maxQueryValueLength` (500) limits search value length.
- The class automatically handles stopwords, minimum word lengths, and MySQL fulltext index limitations.
- Word alternates (singular/plural, lemmas) are generated via `WireTextTools::getWordAlternates()`.
- Boolean mode uses `IN BOOLEAN MODE` for word-level matching and scoring.
- Query expansion uses `WITH QUERY EXPANSION` for related-word discovery.
- The `matchStartEnd()` method uses RLIKE regex with support for ICU (MySQL 8+) and HenrySpencer regex engines.
- Score fields are uniquely named to avoid conflicts when multiple fulltext queries are combined.
- The `$fieldName` parameter of `match()` can be an array for searching across multiple columns (since 3.0.169).

