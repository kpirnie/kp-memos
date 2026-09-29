<?php

/**
 * KP Memos Routes
 *
 * Every route the application answers, and the access middleware guarding
 * them.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the router and controllers
use KPM\Controllers\AccountController;
use KPM\Controllers\AdminController;
use KPM\Controllers\AttachmentsController;
use KPM\Controllers\AuthController;
use KPM\Controllers\NotesController;
use KPM\Controllers\ShareController;
use KPM\Controllers\TaxonomyController;
use KPT\Router;

// if the class does not exist already
if (! class_exists('\KPM\Core\Routes')) {

    /**
     * Routes
     *
     * Route and middleware registration.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Routes
    {
        /**
         * Register the middleware and routes
         *
         * @since 8.5
         * @access public
         *
         * @param  Router $router The application router
         * @return void
         */
        public static function register(Router $router): void
        {

            // access middleware
            $router->registerMiddlewareDefinitions([

                // only for visitors who are not signed in
                'guest' => static function (): bool {
                    if (Auth::user() !== null) {
                        Response::redirect('/notes');
                    }
                    return true;
                },

                // fully signed in, allowed while a password change is pending
                'auth_any' => static function (): bool {
                    if (Auth::user() === null) {
                        self::deny();
                    }
                    return true;
                },

                // fully signed in with nothing pending
                'auth' => static function (): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        self::deny();
                    }
                    if ((int) $user->must_change_password === 1) {
                        if (Request::wantsJson()) {
                            Response::abort(403, 'You must change your password first.');
                        }
                        Response::redirect('/account/password');
                    }
                    return true;
                },

                // administrators
                'admin' => static function (): bool {
                    $user = Auth::user();
                    if ($user === null || ($user->role ?? '') !== 'admin') {
                        Response::abort(404);
                    }
                    return true;
                },
            ]);

            // home
            $router->get('/', static function (): void {
                Response::redirect(Auth::user() !== null ? '/notes' : '/login');
            });

            // the route table: method, path, controller, action, middleware
            $table = [

                // sign in
                ['GET', '/login', AuthController::class, 'loginForm', ['guest']],
                ['POST', '/login', AuthController::class, 'login', ['guest']],
                ['GET', '/login/verify', AuthController::class, 'verifyForm', ['guest']],
                ['POST', '/login/verify', AuthController::class, 'verify', ['guest']],
                ['GET', '/login/enroll', AuthController::class, 'enrollForm', ['guest']],
                ['POST', '/login/enroll', AuthController::class, 'enroll', ['guest']],
                ['GET', '/login/recovery-codes', AuthController::class, 'recoveryCodes', ['auth_any']],
                ['POST', '/logout', AuthController::class, 'logout', []],

                // account
                ['GET', '/account', AccountController::class, 'index', ['auth']],
                ['POST', '/account/profile', AccountController::class, 'profile', ['auth']],
                ['GET', '/account/password', AccountController::class, 'passwordForm', ['auth_any']],
                ['POST', '/account/password', AccountController::class, 'password', ['auth_any']],
                ['POST', '/account/recovery-codes', AccountController::class, 'regenerateCodes', ['auth']],
                ['POST', '/account/totp-reset', AccountController::class, 'resetTotp', ['auth']],

                // notes
                ['GET', '/notes', NotesController::class, 'index', ['auth']],
                ['POST', '/notes/pin-order', NotesController::class, 'pinOrder', ['auth']],
                ['GET', '/notes/new', NotesController::class, 'create', ['auth']],
                ['POST', '/notes', NotesController::class, 'store', ['auth']],
                ['GET', '/notes/{id}', NotesController::class, 'show', ['auth']],
                ['GET', '/notes/{id}/edit', NotesController::class, 'edit', ['auth']],
                ['POST', '/notes/{id}', NotesController::class, 'update', ['auth']],
                ['POST', '/notes/{id}/delete', NotesController::class, 'destroy', ['auth']],
                ['POST', '/notes/{id}/attachments', NotesController::class, 'upload', ['auth']],
                ['POST', '/notes/{id}/pin', NotesController::class, 'pin', ['auth']],

                // sharing
                ['POST', '/notes/{id}/share', ShareController::class, 'update', ['auth']],
                ['POST', '/notes/{id}/share/regenerate', ShareController::class, 'regenerate', ['auth']],
                ['GET', '/s/{token}', ShareController::class, 'show', []],
                ['POST', '/s/{token}', ShareController::class, 'unlock', []],
                ['GET', '/s/{token}/files/{id}', ShareController::class, 'file', []],

                // attachments
                ['GET', '/attachments/{id}', AttachmentsController::class, 'download', ['auth']],
                ['POST', '/attachments/{id}/delete', AttachmentsController::class, 'destroy', ['auth']],

                // categories and tags
                ['GET', '/categories', TaxonomyController::class, 'categories', ['auth']],
                ['POST', '/categories', TaxonomyController::class, 'createCategory', ['auth']],
                ['POST', '/categories/{id}', TaxonomyController::class, 'updateCategory', ['auth']],
                ['POST', '/categories/{id}/delete', TaxonomyController::class, 'deleteCategory', ['auth']],
                ['GET', '/tags', TaxonomyController::class, 'tags', ['auth']],
                ['POST', '/tags', TaxonomyController::class, 'createTag', ['auth']],
                ['POST', '/tags/{id}', TaxonomyController::class, 'updateTag', ['auth']],
                ['POST', '/tags/{id}/delete', TaxonomyController::class, 'deleteTag', ['auth']],

                // user administration
                ['GET', '/admin/users', AdminController::class, 'index', ['auth', 'admin']],
                ['POST', '/admin/users', AdminController::class, 'create', ['auth', 'admin']],
                ['GET', '/admin/users/{id}', AdminController::class, 'edit', ['auth', 'admin']],
                ['POST', '/admin/users/{id}', AdminController::class, 'update', ['auth', 'admin']],
                ['POST', '/admin/users/{id}/password', AdminController::class, 'resetPassword', ['auth', 'admin']],
                ['POST', '/admin/users/{id}/totp', AdminController::class, 'resetTotp', ['auth', 'admin']],
                ['POST', '/admin/users/{id}/delete', AdminController::class, 'delete', ['auth', 'admin']],
            ];

            // register them; controllers are built lazily, only for the matched route
            $routes = [];
            foreach ($table as [$method, $path, $class, $action, $middleware]) {
                $routes[] = [
                    'method' => $method,
                    'path' => $path,
                    'handler' => static fn (...$args) => (new $class())->$action(...$args),
                    'middleware' => $middleware,
                ];
            }
            $router->registerRoutes($routes);
        }

        /**
         * Refuse an unauthenticated request
         *
         * @since 8.5
         * @access private
         *
         * @return never
         */
        private static function deny(): never
        {
            if (Request::wantsJson()) {
                Response::abort(401, 'Please sign in.');
            }
            Response::redirect('/login');
        }
    }
}
