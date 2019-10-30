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
DBHelper::addDatabase('identifier', 'database_name')
->setHost('somehost'); // default is localhost
->setCredentials('username', 'password')
->setPort(1234); // omit to use default port
```
Once all databases and optional event handlers have been added, the helper has to be initialized manually once:

```php
DBHelper::init()
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
