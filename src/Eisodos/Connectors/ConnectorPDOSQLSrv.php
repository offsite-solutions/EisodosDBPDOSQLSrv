<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Connectors;
  
  use Eisodos\Eisodos;
  use Eisodos\Interfaces\DBConnectorInterface;
  use PDO;
  use PDOException;
  use RuntimeException;
  
  /**
   * Eisodos PDO::PgSQL Connector class
   *
   * Config values:
   * [Database]
   * driver=pgsql|uri:///file/to/dsn
   * username=
   * password=
   * DBName=
   * SSLMode=require,disable,allow,prefer,require,verify-ca,verify-full
   * SSLCert=[path]/client.crt
   * SSLKey=[path]/client.key
   * SSLRootCert=[path]/ca.crt;
   * connectTimeout=
   * prefetchSize=
   * case=natural(default)|lower|upper
   * stringifyFetches=true|false(default)
   * autoCommit=true|false(default)
   * persistent=true|false(default)
   * options= list of available options: https://www.postgresql.org/docs/current/libpq-connect.html#libpq-connstring
   * connectSQL=list of query run after connection separated by ;
   */
  class ConnectorPDOSQLSrv implements DBConnectorInterface {
  
    /** @var string DB Syntax */
    private string $_dbSyntax='sqlsrv';
    
    /** @var PDO null */
    private PDO $connection;
    
    /** @var array */
    private array $lastQueryColumnNames = [];
    
    /** @var int */
    private int $lastQueryTotalRows = 0;
    
    /** @var string */
    private string $named_notation_separator = '=>';
    
    public function connected(): bool {
      return (isset($this->connection));
    }
    
    public function __destruct() {
      $this->disconnect();
    }
    
    /**
     * https://www.php.net/manual/en/pdo.connect.php
     *
     * @inheritDoc
     * throws RuntimeException
     * @throws \Exception
     */
    public function connect($databaseConfigSection_ = 'Database', $connectParameters_ = [], $persistent_ = false): void {
      if (!isset($this->connection)) {
        $databaseConfig = array_change_key_case(Eisodos::$configLoader->importConfigSection($databaseConfigSection_, '', false));
        
        $connectString = Eisodos::$utils->safe_array_value($databaseConfig, 'driver', 'sqlsrv') .
          (!str_contains(Eisodos::$utils->safe_array_value($databaseConfig, 'driver', 'sqlsrv'), ':') ? ':' : '');
        $connectParameters = [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $username = '';
        $password = '';
        
        /*
         * DSN parameters: https://learn.microsoft.com/en-us/sql/connect/php/connection-options?view=sql-server-ver17
         *
         * PDO::SQLSRV_TXN_READ_UNCOMMITTED (int)
         *     This constant is an acceptable value for the SQLSRV DSN key TransactionIsolation.
         *     This constant sets the transaction isolation level for the connection to Read Uncommitted.
         * PDO::SQLSRV_TXN_READ_COMMITTED (int)
         *     This constant is an acceptable value for the SQLSRV DSN key TransactionIsolation.
         *     This constant sets the transaction isolation level for the connection to Read Committed.
         * PDO::SQLSRV_TXN_REPEATABLE_READ (int)
         *     This constant is an acceptable value for the SQLSRV DSN key TransactionIsolation.
         *     This constant sets the transaction isolation level for the connection to Repeateable Read.
         * PDO::SQLSRV_TXN_SNAPSHOT (int)
         *     This constant is an acceptable value for the SQLSRV DSN key TransactionIsolation.
         *     This constant sets the transaction isolation level for the connection to Snapshot.
         * PDO::SQLSRV_TXN_SERIALIZABLE (int)
         *     This constant is an acceptable value for the SQLSRV DSN key TransactionIsolation.
         *     This constant sets the transaction isolation level for the connection to Serializable.
         * PDO::SQLSRV_ENCODING_BINARY (int)
         *     Specifies that data is sent/retrieved as a raw byte stream to/from the server without performing encoding or translation.
         *     This constant can be passed to PDOStatement::setAttribute, PDO::prepare, PDOStatement::bindColumn,
         *     and PDOStatement::bindParam.
         * PDO::SQLSRV_ENCODING_SYSTEM (int)
         *     Specifies that data is sent/retrieved to/from the server as 8-bit characters as specified in the code page of the
         *     Windows locale that is set on the system. Any multi-byte characters or characters that do not map into this code page are
         *     substituted with a single byte question mark (?) character.
         *     This constant can be passed to PDOStatement::setAttribute, PDO::setAttribute, PDO::prepare, PDOStatement::bindColumn,
         *     and PDOStatement::bindParam.
         * PDO::SQLSRV_ENCODING_UTF8 (int)
         *     Specifies that data is sent/retrieved to/from the server in UTF-8 encoding. This is the default encoding.
         *     This constant can be passed to PDOStatement::setAttribute, PDO::setAttribute, PDO::prepare, PDOStatement::bindColumn,
         *     and PDOStatement::bindParam.
         * PDO::SQLSRV_ENCODING_DEFAULT (int)
         *     Specifies that data is sent/retrieved to/from the server according to PDO::SQLSRV_ENCODING_SYSTEM if specified during connection.
         *     The connection's encoding is used if specified in a prepare statement.
         *     This constant can be passed to PDOStatement::setAttribute, PDO::setAttribute, PDO::prepare, PDOStatement::bindColumn, and PDOStatement::bindParam.
         * PDO::SQLSRV_ATTR_QUERY_TIMEOUT (int)
         *     A non-negative integer representing the timeout period, in seconds. Zero (0) is the default and means no timeout.
         *     This constant can be passed to PDOStatement::setAttribute, PDO::setAttribute, and PDO::prepare.
         * PDO::SQLSRV_ATTR_DIRECT_QUERY (int)
         *     Indicates that a query should be executed directly, without being prepared.
         *     This constant can be passed to PDO::setAttribute, and PDO::prepare.
         */
        
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'user') !== '') {
          $username = Eisodos::$utils->safe_array_value($databaseConfig, 'user');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'password') !== '') {
          $password = Eisodos::$utils->safe_array_value($databaseConfig, 'password');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'server') !== '') {
          $connectString .= 'server=' . Eisodos::$utils->safe_array_value($databaseConfig, 'server');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'port') !== '') {
          $connectString .= ';port=' . Eisodos::$utils->safe_array_value($databaseConfig, 'port');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'database') !== '') {
          $connectString .= ';database=' . Eisodos::$utils->safe_array_value($databaseConfig, 'database');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'options') !== '') {
          $connectString .= ";" . Eisodos::$utils->safe_array_value($databaseConfig, 'options');
        }
        
        
        // options
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'LoginTimeout') !== '') {
          $connectParameters[PDO::ATTR_TIMEOUT] = (int)Eisodos::$utils->safe_array_value($databaseConfig, 'LoginTimeout');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'prefetchSize') !== '') {
          $connectParameters[PDO::ATTR_PREFETCH] = (int)Eisodos::$utils->safe_array_value($databaseConfig, 'prefetchSize');
        }
        //$connectParameters[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
        
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'case') !== '') {
          switch (Eisodos::$utils->safe_array_value($databaseConfig, 'case')) {
            case 'natural':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_NATURAL;
              break;
            case 'lower':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_LOWER;
              break;
            case 'upper':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_UPPER;
              break;
          }
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'stringifyFetches') !== '') {
          $connectParameters[PDO::ATTR_STRINGIFY_FETCHES] = (Eisodos::$utils->safe_array_value($databaseConfig, 'stringifyFetches') === 'true');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'persistent') !== '') {
          $connectParameters[PDO::ATTR_PERSISTENT] = (Eisodos::$utils->safe_array_value($databaseConfig, 'persistent') === 'true');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'autoCommit') !== '') {
          $connectParameters[PDO::ATTR_AUTOCOMMIT] = (Eisodos::$utils->safe_array_value($databaseConfig, 'autoCommit') === 'true');
        }

        try {
          $this->connection = new PDO($connectString, $username, $password, $connectParameters);
        } catch (PDOException $e) {
          Eisodos::$parameterHandler->setParam('DBError', $e->getCode() . ' - ' . $e->getMessage());
          throw new RuntimeException('Database Open Error!');
        }
        
        Eisodos::$logger->trace('Database connected - ' . $connectString);
        
        $connectSQL = Eisodos::$utils->safe_array_value($databaseConfig, 'connectsql');
        
        foreach (explode(';', $connectSQL) as $sql) {
          if ($sql !== '') {
            $this->query(RT_FIRST_ROW_FIRST_COLUMN, $sql);
          }
        }
        
      }
    }
    
    private function _getColumnNames($resultSet): void {
      for ($i = 0; $i < $resultSet->columnCount(); $i++) {
        $this->lastQueryColumnNames[] = $resultSet->getColumnMeta($i)['name'];
      }
    }
    
    /** @inheritDoc
     * https://phpdelusions.net/pdo/fetch_modes
     * */
    public function query(
      int $resultTransformation_, string $SQL_, &$queryResult_ = NULL, $getOptions_ = [], $exceptionMessage_ = ''
    ): mixed {
      
      $this->lastQueryColumnNames = [];
      $this->lastQueryTotalRows = 0;
      $queryResult_ = NULL;
      
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$exceptionMessage_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        $resultSet['error'] = $e->getMessage();
        
        return false;
      }
      
      $resultSet->execute([]);
      $this->_getColumnNames($resultSet);
      
      if ($resultTransformation_ === RT_RAW) {
        $rows = $resultSet->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return false;
        }
        
        $queryResult_ = $rows;
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = count($queryResult_);
        
        return true;
      }
      
      if ($resultTransformation_ === RT_FIRST_ROW) {
        $row = $resultSet->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return false;
        }
        
        $queryResult_ = $row;
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = 1;
        
        return true;
      }
      
      if ($resultSet->rowCount()===0) {
        return true;
      }
      
      if ($resultTransformation_ === RT_FIRST_ROW_FIRST_COLUMN) {
        $row = $resultSet->fetch(PDO::FETCH_NUM);
        if (!$row || count($row) === 0) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return '';
        }
        
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = 1;
        
        return $row[0];
      }
      
      if ($resultTransformation_ === RT_ALL_KEY_VALUE_PAIRS
        || $resultTransformation_ === RT_ALL_FIRST_COLUMN_VALUES
        || $resultTransformation_ === RT_ALL_ROWS
        || $resultTransformation_ === RT_ALL_ROWS_ASSOC) {
        
        $queryResult_ = [];
        
        // TODO okosabban, gyorsabban
        if ($resultTransformation_ === RT_ALL_KEY_VALUE_PAIRS) {
          while (($row = $resultSet->fetch(PDO::FETCH_NUM))) {
            $queryResult_[$row[0]] = $row[1];
          }
        } else if ($resultTransformation_ === RT_ALL_FIRST_COLUMN_VALUES) {
          while (($row = $resultSet->fetch(PDO::FETCH_NUM))) {
            $queryResult_[] = $row[0];
          }
        } else if ($resultTransformation_ === RT_ALL_ROWS) {
          $queryResult_ = $resultSet->fetchAll(PDO::FETCH_ASSOC);
        } else if ($resultTransformation_ === RT_ALL_ROWS_ASSOC) {
          $indexFieldName = Eisodos::$utils->safe_array_value($getOptions_, 'indexFieldName', false);
          if (!$indexFieldName) {
            throw new RuntimeException("Index field name is mandatory on RT_ALL_ROWS_ASSOC result type");
          }
          while (($row = $resultSet->fetch(PDO::FETCH_ASSOC))) {
            $queryResult_[$row[$indexFieldName]] = $row;
          }
        }
        
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = count($queryResult_);
        
        return true;
      }
      
      throw new RuntimeException("Unknown query result type");
      
    }
    
    /** @inheritDoc */
    public function getLastQueryColumns(): array {
      return $this->lastQueryColumnNames;
    }
    
    /** @inheritDoc */
    public function getLastQueryTotalRows(): int {
      return $this->lastQueryTotalRows;
    }
    
    /** @inheritDoc */
    public function disconnect($force_ = false): void {
      /* free up the object to close connection */
    }
    
    /** @inheritDoc */
    public function startTransaction(string|null $savePoint_ = NULL): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      $this->connection->beginTransaction();
    }
    
    /** @inheritDoc */
    public function commit(): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      if ($this->connection->inTransaction()) {
        $this->connection->commit();
      }
    }
    
    /** @inheritDoc */
    public function rollback(string|null $savePoint_ = NULL): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      $this->connection->rollback();
    }
    
    /** @inheritDoc */
    public function inTransaction(): bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      $inTransaction = $this->connection->inTransaction();
      
      return ($inTransaction ?? false);
    }
    
    public function executeDML(string $SQL_, $throwException_ = true): int {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      $resultSet->execute([]);
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    private function _convertType(string $dataType_, mixed &$value_): int {
      
      $dataType_ = strtolower($dataType_);
      
      if ($dataType_ === '' || $value_ == '') {
        return PDO::PARAM_NULL;
      }
      
      $type = match ($dataType_) {
        'bool' => PDO::PARAM_BOOL,
        'int', 'integer', 'bigint' => PDO::PARAM_INT,
        default => PDO::PARAM_STR,
      };
      
      switch ($dataType_) {
        case 'int':
        case 'bigint':
        case 'integer':
          $value_ = (int)$value_;
          break;
        case 'float':
          $value_ = (float)$value_;
          break;
      }
      
      return $type;
    }
    
    public function executePreparedDML(string $SQL_, $dataTypes_ = [], &$data_ = [], $throwException_ = true): int {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      if (count($dataTypes_) !== count($data_)) {
        $_POST["__EISODOS_extendedError"] = 'executePreparedDML missing data type or data';
        throw new RuntimeException('executePreparedDML missing data type or data');
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      $countData = count($data_);
      for ($i = 0; $i < $countData; $i++) {
        $this->_convertType($dataTypes_[$i], $data_[$i]);
        $resultSet->bindParam($i + 1, $data_[$i], $dataTypes_[$i]);
      }
      
      $resultSet->execute();
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    /**
     * @inheritDoc
     */
    public function executePreparedDML2(string $SQL_, array $boundVariables_, $throwException_ = true): int|bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      foreach ($boundVariables_ as $variableName => $parameters) {
        $type = $parameters['type'];
        $type = $this->_convertType($type, $parameters['value']);
        if ($parameters['mode_'] === 'INOUT' || $parameters['mode_'] === 'OUT') {
          $type |= PDO::PARAM_INPUT_OUTPUT;
        }
        $resultSet->bindParam($variableName, $parameters['value'], $type);
      }
      
      $resultSet->execute();
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    /** @inheritDoc */
    public function bind(array &$boundVariables_, string $variableName_, string $dataType_, string $value_, $inOut_ = 'IN'): void {
      $boundVariables_[$variableName_] = array();
      if ($dataType_ === "clob" && $value_ === '') // Empty CLOB bug / invalid LOB locator specified, force type to text
      {
        $boundVariables_[$variableName_]["type"] = "text";
      } else {
        $boundVariables_[$variableName_]["type"] = $dataType_;
      }
      $boundVariables_[$variableName_]["value"] = $value_;
      $boundVariables_[$variableName_]["mode_"] = $inOut_;
    }
    
    /** @inheritDoc */
    public function bindParam(array &$boundVariables_, string $parameterName_, string $dataType_): void {
      $this->bind($boundVariables_, $parameterName_, $dataType_, Eisodos::$parameterHandler->getParam($parameterName_));
    }
    
    /** @inheritDoc */
    public function executeStoredProcedure(string $procedureName_, array $inputVariables_, array &$resultVariables_, $throwException_ = true, $case_ = CASE_UPPER): bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      /*

$out = 0;
$sth = $db->prepare("DECLARE @myout INT; EXECUTE mysp :firstparam, :secondparam, @myout OUTPUT;"); // the DECLARE trick is needed with DBLib
$sth->bindParam(':firstparam', $firstparam, PDO::PARAM_INT);
$sth->execute();
$sth->bindColumn(1, $out, PDO::PARAM_INT);
$sth->fetch(PDO::FETCH_BOUND);

var_dump($out); // works
       */
      
      $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
      
      $sql = "";
      
      foreach ($inputVariables_ as $parameterName => $parameterProperties) {
        if ($parameterProperties["mode_"] !== "OUT") {
          $sql .= ($sql ? "," : "") . " :" . $parameterName;
        }
      }
      $sql = "exec " . $procedureName_  . $sql ;
      
      
      try {
        $resultSet = $this->connection->prepare($sql);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      foreach ($inputVariables_ as $paramName => $parameterProperties) {
        $resultVariables_[$paramName] = $parameterProperties["value"];
        $type = $parameterProperties['type'];
        $type = $this->_convertType($type, $parameterProperties['value']);
        
        if ($parameterProperties['mode_'] === 'INOUT' || $parameterProperties['mode_'] === 'IN_OUT') {
          $type |= PDO::PARAM_INPUT_OUTPUT;
        }
        
        /*
        Eisodos::$logger->trace('Binding: ' . $paramName . ' - ' .
            substr($resultVariables_[$paramName], 0, 30) . ' - ' .
            $type . ' - ' .
            (($parameterProperties["type"] === "integer" || $parameterProperties["type"] === "text") ? (32766 / 2) : -1));
        */
        
        // binding parameters except OUT ones in pgsql
        if ($parameterProperties["mode_"] !== "OUT") {
          $resultSet->bindParam($paramName,
            $resultVariables_[$paramName],
            $type
          );
        } else if ($driver === 'oci8') { // out parameters must set length in oci8
          $resultSet->bindParam($paramName,
            $resultVariables_[$paramName],
            $type,
            (($parameterProperties["type"] === "integer" ||
              $parameterProperties["type"] === "int" ||
              $parameterProperties["type"] === "text") ? (32766 / 2) : -1)
          );
        }
      }
      
      try {
        $resultSet->execute();
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      // getting back OUT parameters
      
      $result = $resultSet->fetch(PDO::FETCH_ASSOC);
      if (is_array($result)) {
        $resultVariables_ = array_merge($resultVariables_, array_change_key_case($result, $case_));
      }
      
      return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getConnection(): mixed {
      return $this->connection;
    }
    
    /**
     * @inheritDoc
     */
    public function emptySQLField($value_, $isString_ = true, $maxLength_ = 0, $exception_ = "", $withComma_ = false, $keyword_ = "NULL"): string {
      if ($value_ === '') {
        if ($withComma_) {
          return "NULL, ";
        }
        
        return "NULL";
      }
      if ($isString_) {
        if ($maxLength_ > 0 && mb_strlen($value_, 'UTF-8') > $maxLength_) {
          if ($exception_) {
            throw new RuntimeException($exception_);
          }
          
          $value_ = substr($value_, 0, $maxLength_);
        }
        $result = "'" . Eisodos::$utils->replace_all($value_, "'", "''") . "'";
      } else {
        $result = $value_;
      }
      if ($withComma_) {
        $result .= ", ";
      }
      
      return $result;
    }
    
    /**
     * @inheritDoc
     */
    public function nullStr($value_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField($value_, $isString_, $maxLength_, $exception_, $withComma_);
    }
    
    /**
     * @inheritDoc
     */
    public function defaultStr($value_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField($value_, $isString_, $maxLength_, $exception_, $withComma_, 'DEFAULT');
    }
    
    /**
     * @inheritDoc
     */
    public function nullStrParam(string $parameterName_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField(Eisodos::$parameterHandler->getParam($parameterName_), $isString_, $maxLength_, $exception_, $withComma_);
    }
    
    /**
     * @inheritDoc
     */
    public function defaultStrParam(string $parameterName_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField(Eisodos::$parameterHandler->getParam($parameterName_), $isString_, $maxLength_, $exception_, $withComma_, 'DEFAULT');
    }
    
    /**
     * @inheritDoc
     */
    public function DBSyntax(): string {
      return $this->_dbSyntax;
    }
    
  }