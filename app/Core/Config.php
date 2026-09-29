<?php

/**
 * KP Memos Configuration
 *
 * Loads the application configuration file once and provides dot-notation
 * access to its values.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Config')) {

    /**
     * Config
     *
     * Static accessor for app/config.php.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Config
    {
        /** @var array<string, mixed> the loaded configuration */
        private static array $config = [];

        /** @var bool whether the configuration has been loaded */
        private static bool $loaded = false;

        /**
         * Load the configuration file
         *
         * @since 8.5
         * @access public
         *
         * @param  string $path Absolute path to the configuration file
         * @return void
         * @throws \RuntimeException When the file is missing or invalid
         */
        public static function load(string $path): void
        {

            // make sure the file exists
            if (! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException(
                    'Configuration file is missing: copy app/config.sample.php to app/config.php'
                );
            }

            // load it
            $config = require $path;

            // make sure it returned an array
            if (! is_array($config)) {
                throw new \RuntimeException('Configuration file must return an array');
            }

            // hold it
            self::$config = $config;
            self::$loaded = true;
        }

        /**
         * Get a configuration value
         *
         * @since 8.5
         * @access public
         *
         * @param  string $key     Dot-notation key, e.g. "db.server"
         * @param  mixed  $default Value returned when the key is not set
         * @return mixed
         */
        public static function get(string $key, mixed $default = null): mixed
        {

            // start at the root
            $value = self::$config;

            // walk the key segments
            foreach (explode('.', $key) as $segment) {
                // bail with the default if the segment is missing
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }

            // return the found value
            return $value;
        }

        /**
         * Get the raw 32 byte application key
         *
         * @since 8.5
         * @access public
         *
         * @return string The binary key
         * @throws \RuntimeException When the key is missing or malformed
         */
        public static function appKey(): string
        {

            // hold the configured hex key
            $hex = (string) self::get('app.key', '');

            // validate it strictly
            if (! preg_match('/^[a-f0-9]{64}$/i', $hex)) {
                throw new \RuntimeException('app.key must be 64 hex characters; generate one with: php bin/keygen.php');
            }

            // return the binary form
            return (string) hex2bin($hex);
        }

        /**
         * Check whether the configuration has been loaded
         *
         * @since 8.5
         * @access public
         *
         * @return bool
         */
        public static function isLoaded(): bool
        {
            return self::$loaded;
        }
    }
}
