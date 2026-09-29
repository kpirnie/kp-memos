<?php

/**
 * KP Memos CSRF Protection
 *
 * One synchronizer token per session, verified on every state-changing
 * request, plus a same-origin check on the Origin / Referer headers.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the utilities
use KPT\Crypto;
use KPT\Session;
use KPT\Token;

// if the class does not exist already
if (! class_exists('\KPM\Core\Csrf')) {

    /**
     * Csrf
     *
     * Session-bound csrf token generation and verification.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Csrf
    {
        /** @var string the token name */
        private const NAME = 'kpm_csrf';

        /** @var string the session key holding the issued token */
        private const SESSION_KEY = 'kpm.csrf';

        /** @var string the form field / header name */
        public const FIELD = '_csrf';

        /** @var string why the last verification failed, for logging and debug output */
        private static string $reason = '';

        /**
         * Get the session's token, issuing one when needed
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function token(): string
        {

            // reuse the issued token while it's still valid
            $token = Session::get(self::SESSION_KEY);
            if (is_string($token) && $token !== '' && Token::has(self::NAME)) {
                return $token;
            }

            // issue a new one that lives as long as the longest session
            $lifetime = max(1, (int) Config::get('security.absolute_timeout', 12)) * 3600;
            $token = Token::generate(self::NAME, $lifetime);
            Session::set(self::SESSION_KEY, $token);
            return $token;
        }

        /**
         * Rotate the token, used on privilege changes
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public static function rotate(): void
        {
            Token::invalidate(self::NAME);
            Session::remove(self::SESSION_KEY);
        }

        /**
         * Render the hidden form field
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function field(): string
        {
            return '<input type="hidden" name="' . self::FIELD . '" value="'
                . htmlspecialchars(self::token(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
        }

        /**
         * Verify the current request
         *
         * @since 8.5
         * @access public
         *
         * @return bool
         */
        public static function verify(): bool
        {

            // the request must come from our own origin
            if (! self::sameOrigin()) {
                return false;
            }

            // grab the submitted token from the form field or the header
            $submitted = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if (! is_string($submitted) || $submitted === '') {
                return self::reject('no token was submitted');
            }

            // the session must have survived since the form was issued
            $issued = Session::get(self::SESSION_KEY);
            if (! is_string($issued) || $issued === '') {
                return self::reject(isset($_COOKIE[session_name()])
                    ? 'the session cookie arrived but its session holds no token; check that session.save_path ('
                    . session_save_path() . ') exists and is writable by the php-fpm user'
                    : 'no session cookie arrived; the site must be browsed over https for the __Host- cookie');
            }

            // compare against the issued token without consuming it
            if (! Crypto::timingSafeEquals($issued, $submitted) || ! Token::verify($submitted, self::NAME, false)) {
                return self::reject('the submitted token does not match the session\'s token');
            }
            return true;
        }

        /**
         * Check the Origin / Referer headers against our own host
         *
         * @since 8.5
         * @access private
         *
         * @return bool
         */
        private static function sameOrigin(): bool
        {

            // browsers send Sec-Fetch-Site on every request; cross-site is always refused
            $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
            if ($site !== '' && ! in_array($site, ['same-origin', 'none'], true)) {
                return self::reject("Sec-Fetch-Site is '{$site}'");
            }

            // hold the expected host from the configured url
            $expected = strtolower((string) parse_url((string) Config::get('app.url', ''), PHP_URL_HOST));

            // prefer origin, fall back to referer
            $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
            if ($expected === '') {
                return self::reject('app.url is not set in app/config.php');
            }
            if ($source === '') {
                return $site !== '' ? true : self::reject('no Origin, Referer, or Sec-Fetch-Site header was sent');
            }

            // compare the hosts
            $host = strtolower((string) parse_url($source, PHP_URL_HOST));
            if ($host !== $expected) {
                return self::reject("request came from host '{$host}' but app.url's host is '{$expected}'");
            }
            return true;
        }

        /**
         * Why the last verification failed
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function reason(): string
        {
            return self::$reason;
        }

        /**
         * Log why a request was refused, then refuse it
         *
         * @since 8.5
         * @access private
         *
         * @param  string $reason The reason; never includes token values
         * @return bool Always false
         */
        private static function reject(string $reason): bool
        {
            self::$reason = $reason;
            error_log('kpm csrf rejected ' . Request::method() . ' '
                . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) . ': ' . $reason);
            return false;
        }
    }
}
