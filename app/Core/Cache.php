<?php

/**
 * KP Memos Redis Store
 *
 * The shared redis connection used for throttling.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Cache')) {

    /**
     * Cache
     *
     * Lazily connected phpredis handle.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Cache
    {
        /** @var \Redis|null the connection */
        private static ?\Redis $redis = null;

        /**
         * Get the redis connection
         *
         * @since 8.5
         * @access public
         *
         * @return \Redis
         * @throws \RuntimeException When redis is unreachable
         */
        public static function redis(): \Redis
        {

            // reuse it
            if (self::$redis !== null) {
                return self::$redis;
            }

            // connect
            try {
                $redis = new \Redis();
                $redis->connect(
                    (string) Config::get('redis.host', '127.0.0.1'),
                    (int) Config::get('redis.port', 6379),
                    (float) Config::get('redis.timeout', 1.0)
                );

                // authenticate when configured
                $password = Config::get('redis.password');
                if (is_string($password) && $password !== '') {
                    // phpredis returns false on a bad password instead of throwing
                    if ($redis->auth($password) !== true) {
                        throw new \RuntimeException('redis rejected the password in redis.password');
                    }
                }

                // select the database and namespace our keys
                $redis->select((int) Config::get('redis.database', 0));
                $redis->setOption(\Redis::OPT_PREFIX, (string) Config::get('redis.prefix', 'kpm:'));
                $redis->ping();
            } catch (\Throwable $e) {
                throw new \RuntimeException('Redis is unavailable', 503, $e);
            }

            // hold and return it
            self::$redis = $redis;
            return $redis;
        }
    }
}
