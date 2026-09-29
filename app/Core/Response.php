<?php

/**
 * KP Memos Response Helpers
 *
 * Redirects, json responses, and error pages.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Response')) {

    /**
     * Response
     *
     * Terminal response helpers; every method ends the request.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Response
    {
        /**
         * Redirect to a local path
         *
         * @since 8.5
         * @access public
         *
         * @param  string $path Local absolute path, e.g. "/notes"
         * @param  int    $code Redirect status code
         * @return never
         */
        public static function redirect(string $path, int $code = 303): never
        {

            // only ever redirect within the app
            if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
                $path = '/';
            }

            // send it
            header('Location: ' . $path, true, $code);
            exit;
        }

        /**
         * Send a json response
         *
         * @since 8.5
         * @access public
         *
         * @param  mixed $data The payload
         * @param  int   $code The status code
         * @return never
         */
        public static function json(mixed $data, int $code = 200): never
        {

            // send it
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            exit;
        }

        /**
         * Abort with an error page or json error
         *
         * @since 8.5
         * @access public
         *
         * @param  int    $code    The status code
         * @param  string $message The public message
         * @return never
         */
        public static function abort(int $code, string $message = ''): never
        {

            // default messages
            $message = $message !== '' ? $message : match ($code) {
                400 => 'Bad request.',
                403 => 'You do not have access to this.',
                404 => 'Not found.',
                405 => 'Method not allowed.',
                413 => 'That upload is too large.',
                419 => 'Your session expired. Please try again.',
                429 => 'Too many attempts. Please wait and try again.',
                default => 'Something went wrong.',
            };

            // json callers get json
            if (Request::wantsJson()) {
                self::json(['success' => false, 'message' => $message], $code);
            }

            // everyone else gets the error page
            http_response_code($code);
            echo View::render('error.php', ['title' => (string) $code, 'code' => $code, 'message' => $message]);
            exit;
        }
    }
}
