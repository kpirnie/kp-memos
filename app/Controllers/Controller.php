<?php

/**
 * KP Memos Base Controller
 *
 * Shared rendering helpers for every controller.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Controllers;

// we need the core
use KPM\Core\Auth;
use KPM\Core\Flash;
use KPM\Core\View;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\Controller')) {

    /**
     * Controller
     *
     * Base controller.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    abstract class Controller
    {
        /**
         * Render a page with the signed-in user and flash messages
         *
         * @since 8.5
         * @access protected
         *
         * @param  string               $template Template path relative to app/views
         * @param  array<string, mixed> $data     Template variables
         * @return string
         */
        protected function render(string $template, array $data = []): string
        {
            return View::render($template, array_merge([
                'user' => Auth::user(),
                'flashes' => Flash::take(),
            ], $data));
        }

        /**
         * Get the signed-in user; only call behind the auth middleware
         *
         * @since 8.5
         * @access protected
         *
         * @return object
         */
        protected function user(): object
        {
            $user = Auth::user();
            if ($user === null) {
                throw new \RuntimeException('No signed-in user', 401);
            }
            return $user;
        }
    }
}
