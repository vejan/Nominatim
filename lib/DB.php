<?php

namespace Nominatim;

require_once(CONST_BasePath.'/lib/DatabaseError.php');

/**
 * Uses PDO to access the database specified in the CONST_Database_DSN
 * setting.
 */
class DB
{
    protected $connection;

    public function __construct($sDSN = CONST_Database_DSN)
    {
        $this->sDSN = $sDSN;
    }

    public function connect($bNew = false, $bPersistent = true)
    {
        if (isset($this->connection) && !$bNew) {
            return true;
        }
        $aConnOptions = array(
                         \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                         \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                         \PDO::ATTR_PERSISTENT         => $bPersistent
        );

        // https://secure.php.net/manual/en/ref.pdo-pgsql.connection.php
        try {
            $conn = new \PDO($this->sDSN, null, null, $aConnOptions);
        } catch (\PDOException $e) {
            $sMsg = 'Failed to establish database connection:' . $e->getMessage();
            throw new \Nominatim\DatabaseError($sMsg, 500, null, $e->getMessage());
        }

        $conn->exec("SET DateStyle TO 'sql,european'");
        $conn->exec("SET client_encoding TO 'utf-8'");
        $iMaxExecution = ini_get('max_execution_time');
        if ($iMaxExecution > 0) $conn->setAttribute(\PDO::ATTR_TIMEOUT, $iMaxExecution); // seconds

        $this->connection = $conn;
        return true;
    }

    // returns the number of rows that were modified or deleted by the SQL
    // statement. If no rows were affected returns 0.
    public function exec($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        $val = null;
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $val = $this->connection->exec($sSQL);
            }
        } catch (\PDOException $e) {
            $sErrMessage = $e->message();
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $val;
    }

    /**
     * Executes query. Returns first row as array.
     * Returns false if no result found.
     *
     * @param string  $sSQL
     *
     * @return array[]
     */
    public function getRow($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $stmt = $this->connection->query($sSQL);
            }
            $row = $stmt->fetch();
        } catch (\PDOException $e) {
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $row;
    }

    /**
     * Executes query. Returns first value of first result.
     * Returns false if no results found.
     *
     * @param string  $sSQL
     *
     * @return array[]
     */
    public function getOne($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $stmt = $this->connection->query($sSQL);
            }
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            if ($row === false) return false;
        } catch (\PDOException $e) {
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $row[0];
    }

    /**
     * Executes query. Returns array of results (arrays).
     * Returns empty array if no results found.
     *
     * @param string  $sSQL
     *
     * @return array[]
     */
    public function getAll($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $stmt = $this->connection->query($sSQL);
            }
            $rows = $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $rows;
    }

    /**
     * Executes query. Returns array of the first value of each result.
     * Returns empty array if no results found.
     *
     * @param string  $sSQL
     *
     * @return array[]
     */
    public function getCol($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        $aVals = array();
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $stmt = $this->connection->query($sSQL);
            }
            while ($val = $stmt->fetchColumn(0)) { // returns first column or false
                $aVals[] = $val;
            }
        } catch (\PDOException $e) {
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $aVals;
    }

    /**
     * Executes query. Returns associate array mapping first value to second value of each result.
     * Returns empty array if no results found.
     *
     * @param string  $sSQL
     *
     * @return array[]
     */
    public function getAssoc($sSQL, $aInputVars = null, $sErrMessage = 'Database query failed')
    {
        try {
            if (isset($aInputVars)) {
                $stmt = $this->connection->prepare($sSQL);
                $stmt->execute($aInputVars);
            } else {
                $stmt = $this->connection->query($sSQL);
            }
            $aList = array();
            while ($aRow = $stmt->fetch(\PDO::FETCH_NUM)) {
                $aList[$aRow[0]] = $aRow[1];
            }
        } catch (\PDOException $e) {
            throw new \Nominatim\DatabaseError($sErrMessage, 500, null, $e, $sSQL);
        }
        return $aList;
    }


    /**
     * St. John's Way => 'St. John\'s Way'
     *
     * @param string  $sVal  Text to be quoted.
     *
     * @return string
     */
    public function getDBQuoted($sVal)
    {
        return $this->connection->quote($sVal);
    }

    /**
     * Like getDBQuoted, but takes an array.
     *
     * @param array  $aVals  List of text to be quoted.
     *
     * @return array[]
     */
    public function getDBQuotedList($aVals)
    {
        return array_map(function ($sVal) {
            return $this->getDBQuoted($sVal);
        }, $aVals);
    }

    /**
     * [1,2,'b'] => 'ARRAY[1,2,'b']''
     *
     * @param array  $aVals  List of text to be quoted.
     *
     * @return string
     */
    public function getArraySQL($a)
    {
        return 'ARRAY['.join(',', $a).']';
    }

    public function getLastError()
    {
        // https://secure.php.net/manual/en/pdo.errorinfo.php
        return $this->connection->errorInfo();
    }

    /**
     * Check if a table exists in the database. Returns true if it does.
     *
     * @param string  $sTableName
     *
     * @return boolean
     */
    public function tableExists($sTableName)
    {
        $sSQL = 'SELECT count(*) FROM pg_tables WHERE tablename = :tablename';
        return ($this->getOne($sSQL, array(':tablename' => $sTableName)) == 1);
    }

    /**
     * Since the DSN includes the database name, checks if the connection works.
     *
     * @return boolean
     */
    public function databaseExists()
    {
        $bExists = true;
        try {
            $this->connect(true);
        } catch (\Nominatim\DatabaseError $e) {
            $bExists = false;
        }
        return $bExists;
    }

    /**
     * e.g. 9.6, 10, 11.2
     *
     * @return float
     */
    public function getPostgresVersion()
    {
        $sVersionString = $this->getOne('SHOW server_version_num');
        preg_match('#([0-9]?[0-9])([0-9][0-9])[0-9][0-9]#', $sVersionString, $aMatches);
        return (float) ($aMatches[1].'.'.$aMatches[2]);
    }

    /**
     * e.g. 2, 2.2
     *
     * @return float
     */
    public function getPostgisVersion()
    {
        $sVersionString = $this->getOne('select postgis_lib_version()');
        preg_match('#^([0-9]+)[.]([0-9]+)[.]#', $sVersionString, $aMatches);
        return (float) ($aMatches[1].'.'.$aMatches[2]);
    }

    public static function parseDSN($sDSN)
    {
        // https://secure.php.net/manual/en/ref.pdo-pgsql.connection.php
        $aInfo = array();
        if (preg_match('/^pgsql:(.+)/', $sDSN, $aMatches)) {
            foreach (explode(';', $aMatches[1]) as $sKeyVal) {
                list($sKey, $sVal) = explode('=', $sKeyVal, 2);
                if ($sKey == 'host') $sKey = 'hostspec';
                if ($sKey == 'dbname') $sKey = 'database';
                if ($sKey == 'user') $sKey = 'username';
                $aInfo[$sKey] = $sVal;
            }
        }
        return $aInfo;
    }
}
