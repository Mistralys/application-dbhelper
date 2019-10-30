[![Build Status](https://travis-ci.com/Mistralys/application-dbhelper.svg?branch=master)](https://travis-ci.com/Mistralys/application-dbhelper)

# DBHelper

PHP Database abstraction layer.

## Installation

Simply require the package via composer:

```
"require": {
   "mistralys/application-dbhelper": "dev-master"
}
```

## Configuration

To get started, at least one database connection has to be added:

```php
$database = DBHelper::addDatabase('identifier', 'database_name')
->setHost('somehost') // default is localhost
->setCredentials('username', 'password')
->setPort(1234); // omit to use default port
```
Once all databases and optional event handlers have been added, the helper has to be initialized manually once:

```php
DBHelper::init()
```

### Database init command

A database connection can be configured further with an init command, which is run when
the connection is established. Typically, this is used to set the encoding of the connection.

```php
$database->setInitCommand('SET NAMES latin1');
```

## Methods overview

### Fetching single records

- **fetch**: Gets a record's data from a custom SQL statement.
- **fetchData**: Gets a record's data, building the SQL statement dynamically.
- **fetchKey**: Gets a record's data, and returns the specified column value.

### Fetching multiple records

- **fetchAll**: Gets all entries from a custom SQL statement.
- **fetchAllKey**: Gets an indexed array with a single column's values from a custom SQL statement.

### Deleting records

- **delete**: Deletes records using a custom SQL statement.
- **deleteRecords**: Deletes records, building the SQL statement dynamically.

### Transactions

- **startTransaction**: Starts a transaction.
- **commitTransaction**: Commits an active transaction.
- **rollbackTransaction**: Rolls back an active transaction.

### Table-related

- **columnExists**: Check if a specific column exists in a table.
- **fetchTableNames**: Retrieves the names of all tables in the database.
- **dropTables**: Drops all tables in the database.
- **tableExists**: Checks whether the specified table exists in the database.
- **isAutoincrementColumn**: Checks whether a column is an auto increment column.

### Debugging and logging

- **countQueries**: Counts the amount of queries executed up to this point (requires query tracking).
- **countSelectQueries**: Counts the amount of SELECT queries executed up to this point (requires query tracking).
- **countWriteQueries**: Counts the amount of database write operations executed up to this point (requires query tracking).
- **enableDebugging**: Enables query debugging, which will echo all SQL statements after this call.
- **enableQueryTracking**: Enables saving all queries to memory to be able to access them later.
- **disableDebugging**: Disable debugging again after enabling it.
- **disableQueryTracking**: Disables query tracking again after enabling it.
- **getSelectQueries**: Retrieves all SELECT SQL statements executed up to this point (requires query tracking).
- **getWriteQueries**: Retrieves all write operation SQL statements executed up to this point (requires query tracking).
- **getQueryCount**: Returns the total amount of queries executed up to this point.
- **getQueries**: Retrieves all SQL statements executed up to this point (requires query tracking).
- **setLogCallback**: Sets a callback to call for handling log messages.

## Event handling

There are currently two events that listeners can be added to: 

1. `Init`: Called when initialization is complete, a connection to the database was successful, and queries can be run.
2. `OnBeforeWriteOperation`: Called whenever a write operation is about to be executed. Allows cancelling the operation.

Both events have their own method to add callback functions or methods as event listeners.

```php
DBHelper::onInit('handle_initDatabase');
DBHelper::onBeforeWriteOperation('handle_beforeWriteOperation');
```

The callback function always gets the event object as first parameter.
Additional arguments can optionally be specified in the arguments parameter:

```php
 DBHelper::onInit(
     'handle_initDatabase', 
     array(
         'foo', 
         'bar'
     )
);
 
function handle_initDatabase(\AppDB\DBHelper_Event $event, $param1, $param2)
{
    // $param1 = 'foo'
    // $param2 = 'bar'
}
```

## Origin

Historically, these classes were integrated in several legacy applications. This repository aims to centralize the code and to make it easier to test and maintain them.
