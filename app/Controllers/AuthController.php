<?php

/**
 * KP Memos Authentication Controller
 *
 * Sign in, second factor, totp enrollment, and sign out.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Controllers;

// we need the core
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use KPM\Core\Auth;
use KPM\Core\Config;
use KPM\Core\Db;
use KPM\Core\Flash;
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Totp;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\AuthController')) {

    /**
     * AuthController
     *
     * Authentication screens.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class AuthController extends Controller
    {
        /**
         * Show the sign in form
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function loginForm(): string
        {
            return $this->render('auth/login.php', ['title' => 'Sign in', 'bare' => true]);
        }

        /**
         * Handle the password step
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function login(): string
        {

            // try it
            $username = Request::post('username');
            $error = Auth::attempt($username, Request::post('password'));
            if ($error !== null) {
                http_response_code($error === Auth::LOCKED ? 429 : 401);
                return $this->render('auth/login.php', [
                    'title' => 'Sign in',
                    'bare' => true,
                    'error' => $error,
                    'username' => Auth::normalizeUsername($username),
                ]);
            }

            // on to the second factor
            Response::redirect(Auth::pending('mfa') !== null ? '/login/verify' : '/login/enroll');
        }

        /**
         * Show the second factor form
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function verifyForm(): string
        {
            if (Auth::pending('mfa') === null) {
                Response::redirect('/login');
            }
            return $this->render('auth/verify.php', ['title' => 'Verify', 'bare' => true]);
        }

        /**
         * Handle the second factor
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function verify(): string
        {

            // check it
            $error = Auth::verifySecondFactor(Request::post('code'));
            if ($error === null) {
                Response::redirect('/notes');
            }

            // the half-finished login may have ended
            if (Auth::pending('mfa') === null) {
                Flash::add('error', $error);
                Response::redirect('/login');
            }
            http_response_code(401);
            return $this->render('auth/verify.php', ['title' => 'Verify', 'bare' => true, 'error' => $error]);
        }

        /**
         * Show the totp enrollment form
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function enrollForm(): string
        {

            // must be mid-login and not yet enrolled
            $uid = Auth::pending('enroll');
            if ($uid === null) {
                Response::redirect('/login');
            }
            return $this->render('auth/enroll.php', $this->enrollData($uid));
        }

        /**
         * Confirm totp enrollment
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function enroll(): string
        {

            // confirm it
            $error = Auth::confirmEnrollment(Request::post('code'));
            if ($error === null) {
                Response::redirect('/login/recovery-codes');
            }

            // the half-finished login may have ended
            $uid = Auth::pending('enroll');
            if ($uid === null) {
                Flash::add('error', $error);
                Response::redirect('/login');
            }
            http_response_code(422);
            return $this->render('auth/enroll.php', array_merge($this->enrollData($uid), ['error' => $error]));
        }

        /**
         * Show freshly issued recovery codes, once
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function recoveryCodes(): string
        {
            $codes = Auth::takeRecoveryCodes();
            if ($codes === []) {
                Response::redirect('/notes');
            }
            return $this->render('auth/recovery-codes.php', ['title' => 'Recovery codes', 'codes' => $codes]);
        }

        /**
         * Sign out
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function logout(): void
        {
            Auth::logout();
            Response::redirect('/login');
        }

        /**
         * Build the enrollment view data
         *
         * @since 8.5
         * @access private
         *
         * @param  int $uid The enrolling user id
         * @return array<string, mixed>
         */
        private function enrollData(int $uid): array
        {

            // the account label and provisioning uri
            $user = Db::row('user_get', [$uid]);
            $secret = Auth::enrollmentSecret();
            $issuer = (string) Config::get('app.name', 'KP Memos');
            $uri = Totp::uri($secret, (string) ($user->username ?? 'user'), $issuer);

            // render the qr code as inline svg
            $writer = new Writer(new ImageRenderer(new RendererStyle(232, 2), new SvgImageBackEnd()));
            $qr = $writer->writeString($uri);
            $qr = (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $qr);

            return [
                'title' => 'Set up two-factor authentication',
                'bare' => true,
                'qr' => $qr,
                'secret' => trim(chunk_split($secret, 4, ' ')),
            ];
        }
    }
}
