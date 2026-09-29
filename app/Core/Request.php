<?php

/**
 * KP Memos Request Helpers
 *
 * Request inspection that is safe to use for security decisions.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the http helpers
use KPT\Http;

// if the class does not exist already
if (! class_exists('\KPM\Core\Request')) {

    /**
     * Request
     *
     * Client ip resolution, input access, and request type checks.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Request
    {
        /**
         * Get the client ip address
         *
         * Forwarding headers are only honored when the direct peer is a
         * configured trusted proxy, so the address cannot be spoofed.
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function ip(): string
        {

            // the direct peer is the only thing we can trust outright
            $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (filter_var($remote, FILTER_VALIDATE_IP) === false) {
                return '0.0.0.0';
            }

            // hold the trusted proxies
            $trusted = (array) Config::get('security.trusted_proxies', []);
            if ($trusted === [] || ! self::isTrusted($remote, $trusted)) {
                return $remote;
            }

            // walk the forwarded chain right to left, skipping trusted hops
            $chain = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
            for ($i = count($chain) - 1; $i >= 0; $i--) {
                $hop = $chain[$i];
                if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                    break;
                }
                if (! self::isTrusted($hop, $trusted)) {
                    return $hop;
                }
            }

            // everything was a proxy
            return $remote;
        }

        /**
         * Check an ip against the trusted proxy list
         *
         * @since 8.5
         * @access private
         *
         * @param  string       $ip      The ip address
         * @param  list<string> $trusted Addresses or cidr ranges
         * @return bool
         */
        private static function isTrusted(string $ip, array $trusted): bool
        {

            // check each entry
            foreach ($trusted as $entry) {
                $entry = (string) $entry;
                if ($entry === $ip || (str_contains($entry, '/') && Http::cidrMatch($ip, $entry))) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Get the client user agent, truncated for storage
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function userAgent(): string
        {
            return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        }

        /**
         * Get the request method
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function method(): string
        {
            return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        }

        /**
         * Get a raw POST string value
         *
         * @since 8.5
         * @access public
         *
         * @param  string $key     The field name
         * @param  string $default Returned when missing or not a string
         * @return string
         */
        public static function post(string $key, string $default = ''): string
        {
            $value = $_POST[$key] ?? $default;
            return is_string($value) ? $value : $default;
        }

        /**
         * Get a raw POST array value
         *
         * @since 8.5
         * @access public
         *
         * @param  string $key The field name
         * @return list<string>
         */
        public static function postArray(string $key): array
        {
            $value = $_POST[$key] ?? [];
            if (! is_array($value)) {
                return [];
            }
            return array_values(array_filter($value, 'is_string'));
        }

        /**
         * Get a raw GET string value
         *
         * @since 8.5
         * @access public
         *
         * @param  string $key     The parameter name
         * @param  string $default Returned when missing or not a string
         * @return string
         */
        public static function query(string $key, string $default = ''): string
        {
            $value = $_GET[$key] ?? $default;
            return is_string($value) ? $value : $default;
        }

        /**
         * Decode a JSON request body
         *
         * @since 8.5
         * @access public
         *
         * @param  int $maxBytes Maximum accepted body size
         * @return array<string, mixed>
         */
        public static function json(int $maxBytes = 65536): array
        {

            // read no more than we allow
            $raw = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);
            if ($raw === '' || strlen($raw) > $maxBytes) {
                return [];
            }

            // decode it
            try {
                $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
            return is_array($data) ? $data : [];
        }

        /**
         * Check whether the request expects json back
         *
         * @since 8.5
         * @access public
         *
         * @return bool
         */
        public static function wantsJson(): bool
        {
            return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || Http::isAjax();
        }
    }
}
