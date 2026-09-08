# EisodosDBPDOSQLServer
Eisodos FW PDO:SQL Server Database Connector

## Prerequisites
- PHP 8.4+
  - Tested with PHP 8.5
- MS ODBC Driver 18 for SQL Server
  - Driver 18 encrypts connections by default. Either use a certificate the client
    trusts, or set `TrustServerCertificate=1` (or `Encrypt=no`) in **Options** below.
- Installed ext-pdo, ext-pdo_sqlsrv (5.13.x)
- Eisodos framework
  - Minimum version 1.1.0

## Installation
Installation via composer:
```
composer install "offsite-solutions/eisodos-db-connector-pdo-sqlsrv"
```

## Configuration

```ini
[Database]
driver=sqlsrv

```

### driver

### DBName 
Database name

### server 
Host name or IP, port number

Example: **localhost,51433**

### username
Username

### password
Password

### prefetchSize
Prefetch size for queries, example: **20**

### persistent
Peristent connection enabled, available values are **true, false(default)**

### case
Force case of database objects' name. Available values are **natural(default), lower, upper**

### stringifyFetches
Stringify fetches, values are **true, false(default)**

### autoCommit
Set auto commit, values are **true, false(default)**

### Options
List of available options: https://learn.microsoft.com/en-us/sql/connect/php/connection-options?view=sql-server-ver17

Example: **TrustServerCertificate=1;Authentication=SqlPassword**

With ODBC Driver 18 the connection is encrypted unless told otherwise, so one of
`TrustServerCertificate=1` or `Encrypt=no` is required against a server with a
self-signed certificate — omitting both fails the connection rather than falling
back to plaintext as Driver 17 did.

### ConnectSQL
Series of SQLs which will be executed right after successful connection.

Recommended: **SET ANSI_NULLS ON;SET ANSI_WARNINGS ON;SET NOCOUNT ON;**

## Initialization
```php
  use Eisodos\Connectors\ConnectorPDOSQLSrv;
  use Eisodos\Eisodos;
  
  Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOSQLSrv(), 0);
  Eisodos::$dbConnectors->db()->connect();
  
  Eisodos::$dbConnectors->db()->disconnect();
```

## Methods
See Eisodos DBConnector Interface documentation: https://github.com/offsite-solutions/Eisodos

## Examples
### Get all rows of a query
```php
Eisodos::$dbConnectors->db()->query(RT_ALL_ROWS, "select getdate()", $back);
print_r($back);
print("   Number of rows returned: " . Eisodos::$dbConnectors->db()->getLastQueryTotalRows() . "\n");
```

