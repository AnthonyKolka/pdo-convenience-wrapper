# _pdo-convenience-wrapper_
A simple wrapper class for PDO bringing some MDB2-esque convenience functions and more

## _Usage_
Optional args are wrapped in brackets _[]_

### initilization
```php
require_once 'DB.class.php'
$dbo = new DB('Host', 'User', 'Password', 'database', 'driver');
```

### basic CRUD type queries with no result set expected, returns affected row count if possible.
```php
$count = $dbo->exec($sql[, $data]);
```

#### example
```php
$count = $dbo->exec("DELETE FROM bar WHERE id = :id", [':id' => 1]);
```

### arbitrary query returning all results as an array of associative arrays
```php
$result = $dbo->queryAll($sql [, $values = null, $mode = PDO::FETCH_ASSOC]);
```

### arbitrary query returning the first column's values as an array
```php
$result = $dbo->queryCol($sql [, $values = null]);
```

### arbitrary query returning a multidimensional associative array where each row is grouped by the specified column, that column becomes the first level
```php
$associativeArray = $dbo->queryObj($sql, $key[, $values = null]);
```

### delete data from a specified table.
Columns and values for where clause are specified by an associative array. Returns row count. Only equality is supported at this time.
```php
$count = $dbo->delete($table, $data);
```

#### example
*note that the ':' character is not used in the key*
```php
$count = $dbo->delete('users', ['id' => 200]);
```

### insert data into a specified table.

Columns and values for where clause are specified by an associative array Returns rowcount.
```php
$count = $dbo->insert($table, $data);
```
#### example
*note that the ':' character is not used in the key*
```php
$count = $dbo->insert('users', ['id' => 201, 'name' => 'Monkey']);
```

### update data in a pecified table.
Returns row count. Only equality is supported at this time.
Columns and values for where clause can be specified by a string or and an array, and must exist within data.
```php
$count = $dbo->update($table, $data[, $where = 'id']);
```

#### example
```php
$count = $dbo->update('users', ['id' => 201, 'name' => 'Frank']);
```

## Testing for an error after running a statement
```php
if($dbo->error)
{
    echo $dbo->errmsg;
}
```
