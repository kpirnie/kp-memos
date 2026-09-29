<?php

/**
 * KP Memos Admin Controller
 *
 * User account management. Admins manage accounts only; nothing here can
 * read another user's notes.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Controllers;

// we need the core
use KPM\Core\Audit;
use KPM\Core\Auth;
use KPM\Core\Db;
use KPM\Core\Flash;
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Storage;
use KPT\Crypto;
use KPT\Paginator;
use KPT\Validate;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\AdminController')) {

    /**
     * AdminController
     *
     * User administration.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class AdminController extends Controller
    {
        /** @var int users per page */
        private const PER_PAGE = 25;

        /**
         * List users
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function index(): string
        {

            // paging and search
            $search = trim(mb_substr(Request::query('q'), 0, 128));
            $page = max(1, (int) Request::query('page', '1'));
            $rows = Db::rows('admin_users_list', [$search, ($page - 1) * self::PER_PAGE, self::PER_PAGE]);
            $total = $rows !== [] ? (int) $rows[0]->total : 0;
            $query = $search !== '' ? '&q=' . rawurlencode($search) : '';

            return $this->render('admin/users.php', [
                'title' => 'Users',
                'search' => $search,
                'pager' => Paginator::fromTotal(
                    $rows,
                    $total,
                    self::PER_PAGE,
                    $page,
                    '/admin/users?page={page}' . $query
                ),
            ]);
        }

        /**
         * Create a user with a one-time temporary password
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function create(): void
        {

            // validate
            $username = Auth::normalizeUsername(Request::post('username'));
            $display = trim(mb_substr(Request::post('display_name'), 0, 128));
            $email = trim(mb_substr(Request::post('email'), 0, 255));
            $role = Request::post('role') === 'admin' ? 'admin' : 'user';
            if (! preg_match('/^[a-z0-9][a-z0-9._\-]{2,63}$/', $username)) {
                Flash::add('error', 'Usernames are 3-64 characters: letters, digits, dot, dash, underscore.');
                Response::redirect('/admin/users');
            }
            if ($email !== '' && ! Validate::email($email)) {
                Flash::add('error', 'That email address is not valid.');
                Response::redirect('/admin/users');
            }

            // create with a generated temporary password
            $password = $this->temporaryPassword($username);
            $id = (int) Db::value('admin_user_create', [$username, $display, $email, Auth::hash($password), $role]);
            if ($id === -1) {
                Flash::add('error', 'That username is already taken.');
                Response::redirect('/admin/users');
            }

            // done
            Audit::log($id, 'user.created', 'by admin #' . (int) $this->user()->id . ', role: ' . $role);
            Flash::add('success', "User {$username} created. Temporary password (shown once): {$password}");
            Flash::add('info', 'They must set up two-factor and change this password at first sign in.');
            Response::redirect('/admin/users/' . $id);
        }

        /**
         * Show a user
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The user id
         * @return string
         */
        public function edit(string $id): string
        {
            $account = Db::row('user_get', [(int) $id]);
            if ($account === null) {
                Response::abort(404);
            }
            return $this->render('admin/user.php', ['title' => $account->username, 'account' => $account]);
        }

        /**
         * Update a user's details, role, and status
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The user id
         * @return void
         */
        public function update(string $id): void
        {

            // setup
            $id = (int) $id;
            $email = trim(mb_substr(Request::post('email'), 0, 255));
            $role = Request::post('role') === 'admin' ? 'admin' : 'user';
            $active = Request::post('is_active') === '1' ? 1 : 0;
            $self = $id === (int) $this->user()->id;

            // admins can't lock themselves out from here
            if ($self && ($role !== 'admin' || $active !== 1)) {
                Flash::add('error', 'You cannot demote or deactivate your own account.');
                Response::redirect('/admin/users/' . $id);
            }
            if ($email !== '' && ! Validate::email($email)) {
                Flash::add('error', 'That email address is not valid.');
                Response::redirect('/admin/users/' . $id);
            }

            // save
            $status = (int) Db::value('admin_user_update', [
                $id, trim(mb_substr(Request::post('display_name'), 0, 128)), $email, $role, $active,
            ]);
            match (true) {
                $status === 0 => Response::abort(404),
                $status === -2 => Flash::add('error', 'At least one active admin must remain.'),
                default => Flash::add('success', 'User saved.'),
            };
            if ($status > 0) {
                Audit::log($id, 'user.updated', "by admin #{$this->user()->id}, role: {$role}, active: {$active}");
            }
            Response::redirect('/admin/users/' . $id);
        }

        /**
         * Reset a user's password to a one-time temporary password
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The user id
         * @return void
         */
        public function resetPassword(string $id): void
        {

            // must exist, and not be us (use the account page for that)
            $account = $this->target((int) $id);

            // reset; every session of theirs ends
            $password = $this->temporaryPassword((string) $account->username);
            Db::value('user_password_set', [(int) $account->id, Auth::hash($password), 1]);
            Audit::log((int) $account->id, 'password.reset', 'by admin #' . (int) $this->user()->id);
            Flash::add('success', "Temporary password (shown once): {$password}");
            Flash::add('info', 'They must change it at their next sign in. Their sessions have been ended.');
            Response::redirect('/admin/users/' . (int) $account->id);
        }

        /**
         * Remove a user's two-factor so they enroll again at next sign in
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The user id
         * @return void
         */
        public function resetTotp(string $id): void
        {
            $account = $this->target((int) $id);
            Db::value('user_totp_reset', [(int) $account->id]);
            Audit::log((int) $account->id, 'totp.reset', 'by admin #' . (int) $this->user()->id);
            Flash::add('success', 'Two-factor removed. They will set it up again at their next sign in.');
            Response::redirect('/admin/users/' . (int) $account->id);
        }

        /**
         * Delete a user and everything they own
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The user id
         * @return void
         */
        public function delete(string $id): void
        {

            // must exist, and not be us
            $account = $this->target((int) $id);

            // the typed username must match
            if (Request::post('confirm') !== (string) $account->username) {
                Flash::add('error', 'Type the username exactly to confirm deletion.');
                Response::redirect('/admin/users/' . (int) $account->id);
            }

            // collect their files, delete the account, then remove the files
            $files = Db::rows('admin_user_files', [(int) $account->id]);
            $status = (int) Db::value('admin_user_delete', [(int) $account->id]);
            if ($status === -2) {
                Flash::add('error', 'At least one active admin must remain.');
                Response::redirect('/admin/users/' . (int) $account->id);
            }
            foreach ($files as $file) {
                Storage::delete((string) $file->stored_name);
            }

            // done
            Audit::log(0, 'user.deleted', "#{$account->id} {$account->username} by admin #" . (int) $this->user()->id);
            Flash::add('success', "User {$account->username} deleted.");
            Response::redirect('/admin/users');
        }

        /**
         * Load a user an admin may act on, refusing their own account
         *
         * @since 8.5
         * @access private
         *
         * @param  int $id The user id
         * @return object
         */
        private function target(int $id): object
        {
            $account = Db::row('user_get', [$id]);
            if ($account === null) {
                Response::abort(404);
            }
            if ((int) $account->id === (int) $this->user()->id) {
                Flash::add('error', 'Use your account page to change your own security settings.');
                Response::redirect('/admin/users/' . $id);
            }
            return $account;
        }

        /**
         * Generate a temporary password that satisfies the policy
         *
         * @since 8.5
         * @access private
         *
         * @param  string $username The account's username
         * @return string
         */
        private function temporaryPassword(string $username): string
        {
            do {
                $password = substr(Crypto::generatePassword(20), 0, 20);
            } while (Auth::passwordPolicy($password, $username) !== null);
            return $password;
        }
    }
}
