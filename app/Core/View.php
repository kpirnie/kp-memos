<?php

/**
 * KP Memos View Rendering
 *
 * Renders templates through the router's view engine and wraps them in
 * the shared layout.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the router
use KPT\Router;

// if the class does not exist already
if (! class_exists('\KPM\Core\View')) {

    /**
     * View
     *
     * Template rendering with a layout wrapper.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class View
    {
        /** @var Router|null the router whose view engine we use */
        private static ?Router $router = null;

        /**
         * Attach the router
         *
         * @since 8.5
         * @access public
         *
         * @param  Router $router The application router
         * @return void
         */
        public static function setRouter(Router $router): void
        {
            self::$router = $router;
        }

        /**
         * Render a template inside a layout
         *
         * @since 8.5
         * @access public
         *
         * @param  string               $template Template path relative to app/views
         * @param  array<string, mixed> $data     Template variables
         * @param  string|null          $layout   Layout template, or null for none
         * @return string
         */
        public static function render(string $template, array $data = [], ?string $layout = 'layout.php'): string
        {

            // make sure we have a router to render with
            if (self::$router === null) {
                throw new \RuntimeException('View router has not been set');
            }

            // render the content
            $content = self::$router->view($template, $data);

            // wrap it if we have a layout
            if ($layout === null) {
                return $content;
            }
            return self::$router->view($layout, array_merge($data, ['content' => $content]));
        }

        /**
         * Get a cache-busted asset url
         *
         * @since 8.5
         * @access public
         *
         * @param  string $path Path relative to the site root, e.g. "assets/css/app.css"
         * @return string
         */
        public static function asset(string $path): string
        {

            // version by modification time so the immutable cache headers are safe
            $path = ltrim($path, '/');
            $file = KPM_PATH . '/' . $path;
            $version = is_file($file) ? (string) filemtime($file) : '0';
            return '/' . $path . '?v=' . $version;
        }
    }
}
