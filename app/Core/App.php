<?php

/**
 * KP Memos Application Kernel
 *
 * Boots configuration, error handling, the session, and the router, then
 * dispatches the request.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the router and utilities
use KPT\Logger;
use KPT\Router;
use KPT\Session;

// if the class does not exist already
if (! class_exists('\KPM\Core\App')) {

    /**
     * App
     *
     * The application kernel.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class App
    {
        /** @var string the session cookie name; __Host- pins it to https, this host, and path / */
        private const SESSION_NAME = '__Host-kpm';

        /** @var Router the router */
        private Router $router;

        /**
         * Boot the application
         *
         * @since 8.5
         * @access public
         */
        public function __construct()
        {

            // load the configuration
            Config::load(KPM_PATH . '/app/config.php');

            // set the timezone
            date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

            // never leak errors to the client unless debugging
            $debug = (bool) Config::get('app.debug', false);
            ini_set('display_errors', $debug ? '1' : '0');
            ini_set('log_errors', '1');
            // deprecations (e.g. from dependencies on newer php) are never worth breaking a response over
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

            // library logging is off by default; route it to php's error log when debugging
            Logger::setEnabled($debug);
            Logger::setIncludeStackTrace(false);

            // the router's redis prefix reads this constant
            if (! defined('KPT_URI')) {
                define('KPT_URI', 'kpm');
            }

            // setup the router
            $this->router = new Router('', KPM_PATH);
            $this->router->setViewsPath(KPM_PATH . '/app/views');
            View::setRouter($this->router);
        }

        /**
         * Start the hardened session
         *
         * @since 8.5
         * @access private
         *
         * @return void
         */
        private function startSession(): void
        {

            // strict, cookie-only sessions that javascript can't read
            $started = Session::start([
                'name' => self::SESSION_NAME,
                'use_strict_mode' => true,
                'use_cookies' => true,
                'use_only_cookies' => true,
                'use_trans_sid' => false,
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'cookie_path' => '/',
                'cookie_domain' => '',
                'cookie_lifetime' => 0,
                'gc_maxlifetime' => max(1, (int) Config::get('security.absolute_timeout', 12)) * 3600,
                'cache_limiter' => '',
            ]);
            if (! $started) {
                error_log('kpm session could not start; check session.save_path (' . session_save_path() . ')');
            }
        }

        /**
         * Run the application
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function run(): void
        {

            // every dynamic response gets the security headers
            Security::sendHeaders();

            // start the session
            $this->startSession();

            // share the common view data
            $this->router->share([
                'appName' => (string) Config::get('app.name', 'KP Memos'),
                'nonce' => Security::nonce(),
            ]);

            // state-changing requests must fit php's post limit and pass the csrf check
            $this->router->addMiddleware(static function (): bool {
                if (in_array(Request::method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return true;
                }

                // php silently drops an oversized body, which would otherwise surface as a csrf failure
                $limit = Storage::maxPostBytes();
                if ($limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
                    Response::abort(413);
                }
                if (! Csrf::verify()) {
                    Response::abort(419, Config::get('app.debug', false)
                        ? 'CSRF check failed: ' . Csrf::reason()
                        : '');
                }
                return true;
            });

            // register the routes
            Routes::register($this->router);

            // not found
            $this->router->notFound(static function (): void {
                Response::abort(404);
            });

            // dispatch
            $this->router->dispatch();
        }
    }
}
