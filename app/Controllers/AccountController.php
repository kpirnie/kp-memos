<?php

/**
 * KP Memos Account Controller
 *
 * The signed-in user's profile, password, recovery codes, and totp.
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
use KPT\Validate;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\AccountController')) {

    /**
     * AccountController
     *
     * Self-service account settings.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class AccountController extends Controller
    {
        /**
         * Show the account page
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function index(): string
        {
            $user = $this->user();
            return $this->render('account/index.php', [
                'title' => 'Account',
                'remaining' => (int) Db::value('recovery_codes_remaining', [(int) $user->id]),
                'activity' => Db::rows('audit_list', [(int) $user->id, 15]),
            ]);
        }

        /**
         * Update the profile
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function profile(): void
        {

            // validate
            $user = $this->user();
            $name = trim(mb_substr(Request::post('display_name'), 0, 128));
            $email = trim(mb_substr(Request::post('email'), 0, 255));
            if ($email !== '' && ! Validate::email($email)) {
                Flash::add('error', 'That email address is not valid.');
                Response::redirect('/account');
            }

            // save
            Db::value('user_profile_set', [(int) $user->id, $name, $email]);
            Flash::add('success', 'Profile saved.');
            Response::redirect('/account');
        }

        /**
         * Show the password form
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function passwordForm(): string
        {
            return $this->render('account/password.php', ['title' => 'Change password']);
        }

        /**
         * Change the password; requires the current password and a totp code
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function password(): string
        {

            // setup
            $user = $this->user();
            $new = Request::post('new_password');

            // re-authenticate
            $error = Auth::reauthenticate($user, Request::post('current_password'), Request::post('code'));

            // validate the new password
            if ($error === null && $new !== Request::post('confirm_password')) {
                $error = 'The new passwords do not match.';
            }
            if ($error === null) {
                $error = Auth::passwordPolicy($new, (string) $user->username);
            }
            if ($error === null && Request::post('current_password') === $new) {
                $error = 'The new password must be different.';
            }
            if ($error !== null) {
                http_response_code(422);
                return $this->render('account/password.php', ['title' => 'Change password', 'error' => $error]);
            }

            // save; every other session ends, this one carries on
            $version = (int) Db::value('user_password_set', [(int) $user->id, Auth::hash($new), 0]);
            Auth::adoptSessionVersion($version);
            Audit::log((int) $user->id, 'password.changed');
            Flash::add('success', 'Password changed. Every other session has been signed out.');
            Response::redirect('/account');
        }

        /**
         * Regenerate recovery codes; requires the password and a totp code
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function regenerateCodes(): void
        {

            // re-authenticate
            $user = $this->user();
            $error = Auth::reauthenticate($user, Request::post('current_password'), Request::post('code'));
            if ($error !== null) {
                Flash::add('error', $error);
                Response::redirect('/account');
            }

            // issue and show once
            Auth::stashRecoveryCodes(Auth::issueRecoveryCodes((int) $user->id));
            Audit::log((int) $user->id, 'recovery.regenerated');
            Response::redirect('/login/recovery-codes');
        }

        /**
         * Remove totp so it can be set up on a new device at next sign in
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function resetTotp(): void
        {

            // re-authenticate
            $user = $this->user();
            $error = Auth::reauthenticate($user, Request::post('current_password'), Request::post('code'));
            if ($error !== null) {
                Flash::add('error', $error);
                Response::redirect('/account');
            }

            // reset and sign out everywhere, including here
            Db::value('user_totp_reset', [(int) $user->id]);
            Audit::log((int) $user->id, 'totp.reset', 'self');
            Auth::logout();
            Response::redirect('/login');
        }
    }
}
