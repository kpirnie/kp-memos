<?php

/**
 * KP Memos Share Controller
 *
 * The owner's share settings for a note, and the public, token-addressed
 * view of shared notes and their attachments.
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
use KPM\Core\Config;
use KPM\Core\Db;
use KPM\Core\Flash;
use KPM\Core\Format;
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Storage;
use KPM\Core\Throttle;
use KPM\Core\View;
use KPT\Crypto;
use KPT\Session;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\ShareController')) {

    /**
     * ShareController
     *
     * Public note links.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class ShareController extends Controller
    {
        /** @var string the session key holding unlocked password-protected shares */
        private const UNLOCKED_KEY = 'kpm_share_unlocked';

        /**
         * Update a note's share settings
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return void
         */
        public function update(string $id): void
        {

            // setup
            $uid = (int) $this->user()->id;
            $noteId = (int) $id;
            $back = '/notes/' . $noteId;
            $public = Request::post('is_public') === '1' ? 1 : 0;

            // expiry: optional, local time from the form, stored utc, must be in the future
            $expires = null;
            if (Request::post('expires_at') !== '') {
                $expires = Format::toUtc(Request::post('expires_at'));
                if ($expires === null || strtotime($expires . ' UTC') <= time()) {
                    Flash::add('error', 'The expiry must be a date and time in the future.');
                    Response::redirect($back);
                }
            }

            // password: keep, set, or remove
            $mode = 0;
            $hash = null;
            $password = Request::post('share_password');
            if (Request::post('remove_password') === '1') {
                $mode = 2;
            } elseif ($password !== '') {
                if (mb_strlen($password) < 8 || strlen($password) > 1024) {
                    Flash::add('error', 'Share passwords must be 8 to 1024 characters.');
                    Response::redirect($back);
                }
                $mode = 1;
                $hash = password_hash($password, PASSWORD_ARGON2ID);
            }

            // save; a token is only created the first time
            $token = Crypto::generateToken(24);
            $row = Db::row('note_share_set', [$uid, $noteId, $public, $token, $expires, $mode, $hash]);
            if ($row === null) {
                Response::abort(404);
            }
            Audit::log($uid, 'share.updated', "note #{$noteId}, public: {$public}");
            Flash::add('success', $public === 1 ? 'Sharing settings saved. The link is live.' : 'The note is private.');
            Response::redirect($back);
        }

        /**
         * Replace a note's share token, killing the old link
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return void
         */
        public function regenerate(string $id): void
        {
            $uid = (int) $this->user()->id;
            $row = Db::row('note_share_regenerate', [$uid, (int) $id, Crypto::generateToken(24)]);
            if ($row === null) {
                Response::abort(404);
            }
            Audit::log($uid, 'share.regenerated', 'note #' . (int) $id);
            Flash::add('success', 'A new link was generated. The old link no longer works.');
            Response::redirect('/notes/' . (int) $id);
        }

        /**
         * Show a shared note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $token The share token
         * @return string
         */
        public function show(string $token): string
        {

            // resolve it, or show the password form
            $note = $this->resolve($token);
            if (! $this->unlocked($token, $note)) {
                return $this->passwordForm($token);
            }

            return View::render('share/show.php', [
                'title' => $note->title,
                'note' => $note,
                'token' => $token,
                'attachments' => Db::rows('share_attachments', [$token]),
                'bare' => true,
                'public' => true,
            ]);
        }

        /**
         * Unlock a password-protected share
         *
         * @since 8.5
         * @access public
         *
         * @param  string $token The share token
         * @return string
         */
        public function unlock(string $token): string
        {

            // resolve it
            $note = $this->resolve($token);
            $key = $token . '|' . Request::ip();

            // throttled per link and client
            if (Throttle::locked('share-pw', $key)) {
                http_response_code(429);
                return $this->passwordForm($token, 'Too many attempts. Please wait and try again.');
            }

            // check it
            $password = Request::post('password');
            $hash = (string) ($note->share_password_hash ?? '');
            if ($hash === '' || strlen($password) > 1024 || ! password_verify($password, $hash)) {
                Throttle::fail('share-pw', $key);
                http_response_code(401);
                return $this->passwordForm($token, 'That password is not correct.');
            }

            // remember it for this browser session, bound to this password
            Throttle::clear('share-pw', $key);
            $unlocked = Session::get(self::UNLOCKED_KEY, []);
            $unlocked = is_array($unlocked) ? array_slice($unlocked, -49, null, true) : [];
            $unlocked[$token] = $this->passwordMark($hash);
            Session::set(self::UNLOCKED_KEY, $unlocked);
            Response::redirect('/s/' . $token);
        }

        /**
         * Download an attachment of a shared note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $token The share token
         * @param  string $id    The attachment id
         * @return void
         */
        public function file(string $token, string $id): void
        {
            $note = $this->resolve($token);
            if (! $this->unlocked($token, $note)) {
                Response::redirect('/s/' . $token);
            }
            $file = Db::row('share_attachment_get', [$token, (int) $id]);
            if ($file === null) {
                Response::abort(404);
            }
            Storage::send($file, Request::query('download') === '1');
        }

        /**
         * Resolve a token to a live shared note, or 404
         *
         * Misses are throttled per client so tokens can't be enumerated.
         *
         * @since 8.5
         * @access private
         *
         * @param  string $token The share token
         * @return object
         */
        private function resolve(string $token): object
        {

            // shared pages are never indexed
            header('X-Robots-Tag: noindex, nofollow, noarchive');

            // refuse clients that keep missing
            $ip = Request::ip();
            if (Throttle::locked('share-miss', $ip)) {
                Response::abort(429);
            }

            // only well-formed tokens reach the database
            $note = preg_match('/^[A-Za-z0-9_-]{32}$/', $token) ? Db::row('share_get', [$token]) : null;
            if ($note === null) {
                Throttle::fail('share-miss', $ip);
                Response::abort(404, 'This link is invalid, expired, or no longer shared.');
            }
            return $note;
        }

        /**
         * Whether this browser session may see a share
         *
         * @since 8.5
         * @access private
         *
         * @param  string $token The share token
         * @param  object $note  The shared note
         * @return bool
         */
        private function unlocked(string $token, object $note): bool
        {
            $hash = (string) ($note->share_password_hash ?? '');
            if ($hash === '') {
                return true;
            }
            $unlocked = Session::get(self::UNLOCKED_KEY, []);
            $mark = is_array($unlocked) ? (string) ($unlocked[$token] ?? '') : '';
            return $mark !== '' && Crypto::timingSafeEquals($mark, $this->passwordMark($hash));
        }

        /**
         * Bind an unlock to the current password, so changing it relocks every session
         *
         * @since 8.5
         * @access private
         *
         * @param  string $hash The share password hash
         * @return string
         */
        private function passwordMark(string $hash): string
        {
            return Crypto::hmac('share|' . $hash, Config::appKey());
        }

        /**
         * Render the password form
         *
         * @since 8.5
         * @access private
         *
         * @param  string      $token The share token
         * @param  string|null $error An error message
         * @return string
         */
        private function passwordForm(string $token, ?string $error = null): string
        {
            return View::render('share/password.php', [
                'title' => 'Protected note',
                'token' => $token,
                'error' => $error,
                'bare' => true,
            ]);
        }
    }
}
