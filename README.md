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

### Event handling

There are currently two events that listeners can be added to: 

1. `Init`: Called when initialization is complete, and queries can be run.
2. `OnBeforeWriteOperation`: Called whenever a write operation is about to be executed.

Both events have their own method to add callabacks as event listeners.

```php
DBHelper::onInit('handle_initDatabase');
DBHelper::onBeforeWriteOperation('handle_beforeWriteOperation');
```

## Methods overview

### Table-related

- **columnExists**: Check if a specific column exists in a table.
- **fetchTableNames**: Retrieves the names of all tables in the database.
- **dropTables**: Drops all tables in the database.
- **tableExists**: Checks whether the specified table exists in the database.
- **isAutoincrementColumn**: Checks whether a column is an auto increment column.

## Origin

Historically, these classes were integrated in several legacy applications. This repository aims to centralize the code and to make it easier to test and maintain them.
