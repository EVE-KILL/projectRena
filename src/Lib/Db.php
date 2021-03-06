<?php

namespace ProjectRena\Lib;

use Exception;
use PDO;
use ProjectRena\RenaApp;

/**
 * Class Database.
 */
class Db
{
    /**
     * @var int
     */
    protected $queryCount = 0;
    /**
     * @var int
     */
    protected $queryTime = 0;
    /**
     * @var RenaApp
     */
    private $app;
    /**
     * @var PDO
     */
    private $pdo;
    /**
     * @var \ProjectRena\Lib\Cache
     */
    private $cache;
    /**
     * @var Logging
     */
    private $logger;
    /**
     * @var \ProjectRena\Lib\Timer
     */
    private $timer;

    /**
     * @var StatsD
     */
    private $statsd;

    /**
     * @var bool
     */
    public $persistence = true;

    /**
     * @param RenaApp $app
     *
     * @throws Exception
     */
    function __construct(RenaApp $app)
    {
        $this->app = $app;
        $this->cache = $app->Cache;
        $this->logger = $app->Logging;
        $this->timer = $app->Timer;
        $this->statsd = $app->StatsD;

        if ($this->persistence === false) $this->cache->persistence = false;
        
        $host = $app->baseConfig->getConfig("unixSocket", "database", null) ? ";unix_socket=" . $app->baseConfig->getConfig("unixSocket", "database", "/var/run/mysqld/mysqld.sock") : ";host=" . $app->baseConfig->getConfig("host", "database", "127.0.0.1");
        $dsn = 'mysql:dbname=' . $app->baseConfig->getConfig('name', 'database') . "$host;charset=utf8";
        try {
            $this->pdo = new PDO($dsn, $app->baseConfig->getConfig('username', 'database'), $app->baseConfig->getConfig('password', 'database'), array(
                PDO::ATTR_PERSISTENT => $this->persistence,
                PDO::ATTR_EMULATE_PREPARES => $app->baseConfig->getConfig('emulatePrepares', 'database'),
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $app->baseConfig->getConfig('useBufferedQuery', 'database', true),
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00',NAMES utf8;",
            ));
        } catch (Exception $e) {
            $logMessage = 'Unable to connect to the database: ' . $e->getMessage();
            $this->app->Logging->log("DEBUG", $logMessage);
            throw new Exception($logMessage);
        }
    }

    /**
     * @param string $query
     * @param array $parameters
     * @param int $cacheTime
     *
     * @return array|bool
     *
     * @throws Exception
     */
    public function query($query, $parameters = array(), $cacheTime = 30)
    {
        // Sanity check
        if (strpos($query, ';') !== false) {
            throw new Exception('Semicolons are not allowed in queries. Use parameters instead.');
        }

        // Cache time of 0 seconds means skip all caches. and just do the query
        $key = $this->getKey($query, $parameters);

        // If cache time is above 0 seconds, lets try and get it from that.
        if ($cacheTime > 0) {
            // Try the cache system
            $result = !empty($this->cache->get($key)) ? unserialize($this->cache->get($key)) : null;
            if (!empty($result)) {
                return $result;
            }
        }

        try {
            // Start the timer
            $timer = new Timer();

            // Increment the queryCounter
            $this->queryCount++;

            // Prepare the query
            $stmt = $this->pdo->prepare($query);

            // Execute the query, with the parameters
            $stmt->execute($parameters);

            // Check for errors
            if ($stmt->errorCode() != 0) {
                return false;
            }

            // Fetch an associative array
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Close the cursor
            $stmt->closeCursor();

            // Stop the timer
            $duration = $timer->stop();
            $this->queryTime += $timer->stop();

            // If cache time is above 0 seconds, lets store it in the cache.
            if ($cacheTime > 0)
                $this->cache->set($key, serialize($result), min(3600, $cacheTime));

            // Log the query
            $this->logQuery($query, $parameters, $duration);

            // now to return the result
            return $result;
        } catch (Exception $e) {
            // There was some sort of nasty nasty nasty error..
            throw $e;
        }
    }

    /**
     * @param string $query
     * @param array $parameters
     * @param int $cacheTime
     *
     * @return array
     *
     * @throws Exception
     */
    public function queryRow($query, $parameters = array(), $cacheTime = 30)
    {
        // Get the result
        $result = $this->query($query, $parameters, $cacheTime);

        // Figure out if it has more than one result and return it
        if (sizeof($result) >= 1) {
            return $result[0];
        }

        // No results at all
        return array();
    }

    /**
     * @param string $query
     * @param string $field
     * @param array $parameters
     * @param int $cacheTime
     *
     * @return string
     * @throws Exception
     */
    public function queryField($query, $field, $parameters = array(), $cacheTime = 30)
    {
        // Get the result
        $result = $this->query($query, $parameters, $cacheTime);

        // Figure out if it has no results
        if (sizeof($result) == 0) {
            return null;
        }

        // Bind the first result to $resultRow
        $resultRow = $result[0];

        // Return the result + the field requested
        return $resultRow[$field];
    }

    /**
     * @param string $query
     * @param array $parameters
     * @param bool $returnID
     *
     * @return bool|int|string
     *
     * @throws Exception
     */
    public function execute($query, $parameters = array(), $returnID = false)
    {
        // Init the timer
        $timer = new Timer();

        // Increment the amount of queries done
        $this->queryCount++;

        // Transaction start
        $this->pdo->beginTransaction();

        // Prepare the query
        $stmt = $this->pdo->prepare($query);

        // Multilevel array handling.. not pretty but does speedup shit!
        if (isset($parameters[0]) && is_array($parameters[0]) === true) {
            foreach ($parameters as $array) {
                foreach ($array as $key => &$value)
                    $stmt->bindParam($key, $value);

                $stmt->execute();
            }
        } else
            $stmt->execute($parameters);

        // If an error happened, rollback and return false
        if ($stmt->errorCode() != 0) {
            $this->pdo->rollBack();

            return false;
        }

        // Get the inserted id
        $returnID = $returnID ? $this->pdo->lastInsertId() : 0;

        // Commitment time
        $this->pdo->commit();

        // ProjectRena\Lib\Timer stop
        $duration = $timer->stop();

        // Log the query
        $this->logQuery($query, $parameters, $duration);

        // Get the amount of rows that was affected
        $rowCount = $stmt->rowCount();

        // Close the cursor
        $stmt->closeCursor();

        // If return ID is needed, return that
        if ($returnID) {
            return $returnID;
        }

        // Return the amount of rows that got altered
        return $rowCount;
    }

    /**
     * @param string $query
     * @param array $parameters
     * @param bool $returnID
     *
     * @return bool|int|string
     *
     * @throws Exception
     *
     **
     * Takes an insert query up to the "VALUES" string and an array with the rows to be inserted
     * The rows should be associative arrays much like when doing other db queries
     *
     * Generates a long query to insert all the rows in a single execute
     *
     * query = "INSERT INTO table (columnaes)"
     * parameters = array(array(":key" => value), array(":key" => value))
     * suffix = optional sufix to the query
     */
    public function multiInsert($query, $parameters = array(), $suffix = "", $returnID = false)
    {
        $queryIndexes = array();
        $queryValues = array();

        foreach ($parameters as $rowID => $valueRow) {
            if (!is_array($valueRow)) continue;

            $tmpQuery = array();
            foreach ($valueRow as $fieldID => $fieldValue) {
                $queryValues[$fieldID . $rowID] = $fieldValue;
                $tmpQuery[] = $fieldID . $rowID;
            }
            $queryIndexes[] = '(' . implode(",", $tmpQuery) . ')';
        }

        if (count($queryValues) > 0) {
            return $this->execute($query . ' VALUES ' . implode(",", $queryIndexes) . " " . $suffix, $queryValues, $returnID);
        } else {
            return false;
        }
    }

    /**
     * @return int
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * @return int
     */
    public function getQueryTime()
    {
        return $this->queryTime;
    }

    /**
     * @param string $query
     * @param array $parameters
     * @param int $duration
     */
    public function logQuery($query, $parameters = array(), $duration = 0)
    {
        $this->statsd->increment('website_queryCount');

        // Don't log queries taking less than 10 seconds.
        if ($duration < 10000) {
            return;
        }

        $baseAddr = '';
        foreach ($parameters as $k => $v) {
            $query = str_replace($k, "'" . $v . "'", $query);
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? "Query page: https://$baseAddr" . $_SERVER['REQUEST_URI'] . "\n" : '';
        $this->app->Logging->log('INFO', ($duration != 0 ? number_format($duration / 1000, 3) . 's ' : '') . " Query: \n$query;\n$uri");
    }

    /**
     * @param string $query
     * @param array $parameters
     *
     * @return string
     */
    public function getKey($query, $parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $query .= "|$key|$value";
        }

        return 'Db:' . sha1($query);
    }
}
