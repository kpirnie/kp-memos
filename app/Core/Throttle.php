<?php

/**
 * KP Memos Throttle
 *
 * Fixed-window failure counters in redis, used to lock out brute force
 * attempts per account, per ip, and per share link. Fails closed: when
 * redis is unreachable every throttled action is refused.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Throttle')) {

    /**
     * Throttle
     *
     * Brute force lockouts.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Throttle
    {
        /**
         * Build a namespaced, hashed counter key
         *
         * @since 8.5
         * @access private
         *
         * @param  string $scope The counter scope, e.g. "login-user"
         * @param  string $id    The identifier within the scope
         * @return string
         */
        private static function key(string $scope, string $id): string
        {
            return 'throttle:' . $scope . ':' . hash('sha256', mb_strtolower($id));
        }

        /**
         * Check whether an identifier is currently locked out
         *
         * @since 8.5
         * @access public
         *
         * @param  string $scope The counter scope
         * @param  string $id    The identifier
         * @return bool
         */
        public static function locked(string $scope, string $id): bool
        {

            // fail closed if redis is gone, but say so instead of posing as a lockout
            try {
                $count = (int) Cache::redis()->get(self::key($scope, $id));
            } catch (\Throwable $e) {
                $cause = $e->getPrevious()?->getMessage() ?? $e->getMessage();
                $detail = 'redis is unreachable at ' . Config::get('redis.host', '127.0.0.1') . ':'
                    . Config::get('redis.port', 6379) . ' (' . $cause . ')';
                error_log('kpm throttle refused a request: ' . $detail);
                Response::abort(503, Config::get('app.debug', false)
                    ? 'Sign-in is unavailable: ' . $detail . '. Check redis in app/config.php.'
                    : 'Sign-in is temporarily unavailable. Please try again shortly.');
            }
            return $count >= self::max();
        }

        /**
         * Record a failure
         *
         * @since 8.5
         * @access public
         *
         * @param  string $scope The counter scope
         * @param  string $id    The identifier
         * @return int The failure count in the current window
         */
        public static function fail(string $scope, string $id): int
        {

            // increment and start the window on the first failure
            try {
                $redis = Cache::redis();
                $key = self::key($scope, $id);
                $count = (int) $redis->incr($key);
                if ($count === 1 || (int) $redis->ttl($key) < 0) {
                    $redis->expire($key, self::window());
                }
                return $count;
            } catch (\Throwable) {
                return PHP_INT_MAX;
            }
        }

        /**
         * Clear an identifier's failures
         *
         * @since 8.5
         * @access public
         *
         * @param  string $scope The counter scope
         * @param  string $id    The identifier
         * @return void
         */
        public static function clear(string $scope, string $id): void
        {
            try {
                Cache::redis()->del(self::key($scope, $id));
            } catch (\Throwable) {
                // nothing to clear
            }
        }

        /**
         * Seconds until an identifier's lockout lifts
         *
         * @since 8.5
         * @access public
         *
         * @param  string $scope The counter scope
         * @param  string $id    The identifier
         * @return int
         */
        public static function retryAfter(string $scope, string $id): int
        {
            try {
                return max(0, (int) Cache::redis()->ttl(self::key($scope, $id)));
            } catch (\Throwable) {
                return self::window();
            }
        }

        /**
         * The configured failure limit
         *
         * @since 8.5
         * @access private
         *
         * @return int
         */
        private static function max(): int
        {
            return max(1, (int) Config::get('security.max_attempts', 5));
        }

        /**
         * The configured lockout window in seconds
         *
         * @since 8.5
         * @access private
         *
         * @return int
         */
        private static function window(): int
        {
            return max(1, (int) Config::get('security.lockout_minutes', 15)) * 60;
        }
    }
}
