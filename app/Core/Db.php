<?php

/**
 * KP Memos Database Gateway
 *
 * The only way the application talks to MariaDB. Every call is a stored
 * procedure; no table or column names live in the PHP codebase.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the database wrapper
use KPT\Database;

// if the class does not exist already
if (! class_exists('\KPM\Core\Db')) {

    /**
     * Db
     *
     * Thin stored procedure gateway over KPT\Database.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Db
    {
        /** @var string the stored procedure prefix */
        private const PREFIX = 'kpm_';

        /** @var string the named connection */
        private const CONNECTION = 'kpm';

        /** @var bool whether the connection session has been initialized */
        private static bool $initialized = false;

        /**
         * Get the shared connection
         *
         * @since 8.5
         * @access private
         *
         * @return Database
         */
        private static function connection(): Database
        {

            // create or reuse the named connection
            $db = Database::getInstance(self::CONNECTION, (object) [
                'driver' => 'mysql',
                'server' => (string) Config::get('db.server', 'localhost'),
                'schema' => (string) Config::get('db.schema', ''),
                'username' => (string) Config::get('db.username', ''),
                'password' => (string) Config::get('db.password', ''),
                'charset' => (string) Config::get('db.charset', 'utf8mb4'),
                'collation' => (string) Config::get('db.collation', 'utf8mb4_unicode_ci'),
                'persistent' => false,
            ]);

            // pin the session to utc once; every stored datetime is utc
            if (! self::$initialized) {
                self::$initialized = true;
                $db->query('CALL `' . self::PREFIX . 'session_init`()')->many()->fetch();
            }
            return $db;
        }

        /**
         * Build the CALL statement for a procedure
         *
         * @since 8.5
         * @access private
         *
         * @param  string $procedure Procedure name without the prefix
         * @param  int    $count     Number of parameters
         * @return string
         * @throws \InvalidArgumentException When the name is not a plain identifier
         */
        private static function statement(string $procedure, int $count): string
        {

            // procedure names are code constants, but never trust them into sql
            if (! preg_match('/^[a-z][a-z0-9_]{1,56}$/', $procedure)) {
                throw new \InvalidArgumentException('Invalid procedure name');
            }

            // build the placeholders
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';

            // return the statement
            return 'CALL `' . self::PREFIX . $procedure . '`(' . $placeholders . ')';
        }

        /**
         * Call a procedure and return every row of its result set
         *
         * @since 8.5
         * @access public
         *
         * @param  string       $procedure Procedure name without the prefix
         * @param  list<mixed>  $params    Positional parameters
         * @return list<object>
         */
        public static function rows(string $procedure, array $params = []): array
        {

            // run it
            $result = self::connection()
                ->query(self::statement($procedure, count($params)))
                ->bind(array_values($params))
                ->many()
                ->fetch();

            // always hand back a list
            return is_array($result) ? array_values($result) : [];
        }

        /**
         * Call a procedure and return the first row of its result set
         *
         * @since 8.5
         * @access public
         *
         * @param  string       $procedure Procedure name without the prefix
         * @param  list<mixed>  $params    Positional parameters
         * @return object|null
         */
        public static function row(string $procedure, array $params = []): ?object
        {

            // run it and return the first row
            $rows = self::rows($procedure, $params);
            return $rows[0] ?? null;
        }

        /**
         * Call a procedure and return the first column of its first row
         *
         * @since 8.5
         * @access public
         *
         * @param  string       $procedure Procedure name without the prefix
         * @param  list<mixed>  $params    Positional parameters
         * @return mixed
         */
        public static function value(string $procedure, array $params = []): mixed
        {

            // run it and return the first column
            $row = self::row($procedure, $params);
            if ($row === null) {
                return null;
            }
            $values = get_object_vars($row);
            return $values === [] ? null : reset($values);
        }
    }
}
