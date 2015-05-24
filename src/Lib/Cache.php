<?php

namespace ProjectRena\Lib;

use Closure;
use ProjectRena\Model\Config;
use Redis;

/**
 * Class Cache
 * @package ProjectRena\Lib
 */
class Cache
{
    /**
     * Instantiates the `Redis` object and connects it to the configured server.
     *
     */
    protected static function init()
    {
        $redis = new Redis();
        $redis->pconnect(Config::getConfig("host", "redis", "127.0.0.1"), Config::getConfig("port", "redis", 6379));

        return $redis;
    }

    /**
     * Sets expiration time for cache key
     *
     * @param string $key The key to uniquely identify the cached item
     * @param mixed $timeout A `strtotime()`-compatible string or a Unix timestamp.
     * @return boolean
     */
    protected static function expireAt($key, $timeout)
    {
        $redis = self::init();
        return $redis->expireAt($key, is_int($timeout) ? $timeout : strtotime($timeout));
    }

    /**
     * Read value from the cache
     *
     * @param string $key The key to uniquely identify the cached item
     * @return mixed
     */
    public static function get($key)
    {
        $redis = self::init();
        return $redis->get($key);
    }

    /**
     * Write value to the cache
     *
     * @param string $key The key to uniquely identify the cached item
     * @param mixed $value The value to be cached
     * @param null|string $timeout A strtotime() compatible cache time.
     * @return boolean
     */
    public static function set($key, $value, $timeout = 0)
    {
        $redis = self::init();
        $result = $redis->set($key, $value);

        if($timeout > 0)
            return $result ? self::expireAt($key, $timeout) : $result;
        return $result;
    }

    /**
     * Override value in the cache
     *
     * @param string $key The key to uniquely identify the cached item
     * @param mixed $value The value to be cached
     * @param null|string $timeout A strtotime() compatible cache time.
     * @return boolean
     */
    public static function replace($key, $value, $timeout)
    {
        $redis = self::init();
        return $redis->set($key, $value, $timeout);
    }

    /**
     * Delete value from the cache
     *
     * @param string $key The key to uniquely identify the cached item
     * @return bool
     */
    public static function delete($key)
    {
        $redis = self::init();
        return (boolean)$redis->del($key);
    }

    /**
     * Performs an atomic increment operation on specified numeric cache item.
     *
     * Note that if the value of the specified key is *not* an integer, the increment
     * operation will have no effect whatsoever. Redis chooses to not typecast values
     * to integers when performing an atomic increment operation.
     *
     * @param string $key Key of numeric cache item to increment
     * @param int $step
     * @param int $timeout
     * @return callable Function returning item's new value on successful increment, else `false`
     */
    public static function increment($key, $step = 1, $timeout = 0)
    {
        $redis = self::init();
        if ($timeout)
            self::expireAt($key, $timeout);

        return $redis->incr($key, $step);
    }

    /**
     * Performs an atomic decrement operation on specified numeric cache item.
     *
     * Note that if the value of the specified key is *not* an integer, the decrement
     * operation will have no effect whatsoever. Redis chooses to not typecast values
     * to integers when performing an atomic decrement operation.
     *
     * @param string $key Key of numeric cache item to decrement
     * @param integer $step Offset to decrement - defaults to 1
     * @param integer $timeout A strtotime() compatible cache time.
     * @return Closure Function returning item's new value on successful decrement, else `false`
     */
    public static function decrement($key, $step = 1, $timeout = 0)
    {
        $redis = self::init();
        if ($timeout)
            self::expireAt($key, $timeout);

        return $redis->decr($key, $step);
    }

    /**
     * Clears user-space cache
     *
     * @return boolean|null
     */
    public static function flush()
    {
        $redis = self::init();
        $redis->flushdb();
    }
}