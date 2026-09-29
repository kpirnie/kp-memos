<?php

/**
 * KP Memos Authentication
 *
 * Password + TOTP authentication with a staged session:
 *
 * <code>
 * password ok, totp enrolled      -> stage "mfa"    -> code or recovery code -> "full"
 * password ok, totp not enrolled  -> stage "enroll" -> scan + confirm code   -> "full"
 * </code>
 *
 * Every authenticated request re-validates the account against the
 * database (active flag and session version), the idle and absolute
 * timeouts, and the user agent the session was created with.
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

// if the class does not exist already
if (! class_exists('\KPM\Core\Auth')) {

    /**
     * Auth
     *
     * Authentication and session state.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Auth
    {
        /** @var string the session key holding the auth state */
        private const KEY = 'kpm_auth';

        /** @var string the session key holding a pending totp enrollment secret */
        private const ENROLL_KEY = 'kpm_enroll_secret';

        /** @var string the session key holding freshly issued recovery codes */
        private const CODES_KEY = 'kpm_recovery_display';

        /** @var int seconds a half-finished login may wait for its second factor */
        private const PENDING_TIMEOUT = 300;

        /** @var int how many recovery codes are issued */
        public const RECOVERY_CODE_COUNT = 10;

        /** @var string recovery code alphabet: base32 without the look-alike characters */
        private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        /** @var string verified against when the username doesn't exist, so timing doesn't leak it */
        private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$VEd3U00zMXJxVjgudFhGZw$'
            . 'ArPYTTojLCEyC2EQgWlSpDp/7T3CTynZ7kgejSnfj+o';

        /** @var string the generic credential failure message */
        public const INVALID = 'Invalid username or password.';

        /** @var string the generic lockout message */
        public const LOCKED = 'Too many failed attempts. Please wait and try again.';

        /** @var object|null|false the request's resolved user; false when not yet resolved */
        private static object|null|false $user = false;

        /**
         * Hash a password with the current algorithm
         *
         * @since 8.5
         * @access public
         *
         * @param  string $password The plain password
         * @return string
         */
        public static function hash(string $password): string
        {
            return password_hash($password, PASSWORD_ARGON2ID);
        }

        /**
         * Check a new password against the policy
         *
         * @since 8.5
         * @access public
         *
         * @param  string $password The candidate password
         * @param  string $username The account's username
         * @return string|null An error message, or null when acceptable
         */
        public static function passwordPolicy(string $password, string $username): ?string
        {

            // length bounds; argon2 handles long input, but cap the work we accept
            if (mb_strlen($password) < 12 || strlen($password) > 1024) {
                return 'Passwords must be 12 to 1024 characters.';
            }

            // character classes
            if (! \KPT\Validate::passwordStrength($password, 12, true)) {
                return 'Passwords need an uppercase letter, a lowercase letter, a digit, and a symbol.';
            }

            // not the username
            if ($username !== '' && str_contains(mb_strtolower($password), mb_strtolower($username))) {
                return 'Passwords must not contain the username.';
            }
            return null;
        }

        /**
         * Normalize a username
         *
         * @since 8.5
         * @access public
         *
         * @param  string $username The raw username
         * @return string
         */
        public static function normalizeUsername(string $username): string
        {
            return mb_substr(mb_strtolower(trim($username)), 0, 64);
        }

        /**
         * Attempt the password step
         *
         * @since 8.5
         * @access public
         *
         * @param  string $username The submitted username
         * @param  string $password The submitted password
         * @return string|null An error message, or null when the second factor is next
         */
        public static function attempt(string $username, string $password): ?string
        {

            // setup
            $username = self::normalizeUsername($username);
            $ip = Request::ip();

            // refuse while either the ip or the account is locked out
            if (Throttle::locked('login-ip', $ip) || Throttle::locked('login-user', $username)) {
                Audit::log(0, 'login.locked', 'username: ' . $username);
                return self::LOCKED;
            }

            // look the account up; always run a verify so timing doesn't reveal whether it exists
            $row = $username !== '' && strlen($password) <= 1024 ? Db::row('user_auth_get', [$username]) : null;
            $hash = $row !== null ? (string) $row->password_hash : self::DUMMY_HASH;
            $valid = password_verify($password, $hash) && $row !== null && (int) $row->is_active === 1;

            // failed
            if (! $valid) {
                Throttle::fail('login-ip', $ip);
                Throttle::fail('login-user', $username);
                Audit::log($row !== null ? (int) $row->id : 0, 'login.failed', 'username: ' . $username);
                return self::INVALID;
            }

            // upgrade the hash when the algorithm or cost changed
            if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
                Db::value('user_password_rehash', [(int) $row->id, self::hash($password)]);
            }

            // start the second factor stage on a fresh session id
            Session::regenerate(true);
            $now = time();
            Session::set(self::KEY, [
                'uid' => (int) $row->id,
                'sv' => (int) $row->session_version,
                'stage' => (int) $row->totp_enabled === 1 ? 'mfa' : 'enroll',
                'started' => $now,
                'seen' => $now,
                'stage_started' => $now,
                'ua' => self::agentHash(),
            ]);
            Session::remove(self::ENROLL_KEY);
            Audit::log((int) $row->id, 'login.password_ok');
            return null;
        }

        /**
         * Get the user id of a half-finished login in the given stage
         *
         * @since 8.5
         * @access public
         *
         * @param  string $stage "mfa" or "enroll"
         * @return int|null
         */
        public static function pending(string $stage): ?int
        {

            // grab the state
            $state = Session::get(self::KEY);
            if (! is_array($state) || ($state['stage'] ?? '') !== $stage) {
                return null;
            }

            // enforce the pending timeout and the agent binding
            if (
                time() - (int) ($state['stage_started'] ?? 0) > self::PENDING_TIMEOUT
                || ! Crypto::timingSafeEquals((string) ($state['ua'] ?? ''), self::agentHash())
            ) {
                self::clear();
                return null;
            }
            return (int) $state['uid'];
        }

        /**
         * Verify the second factor: a totp code or a recovery code
         *
         * @since 8.5
         * @access public
         *
         * @param  string $code The submitted code
         * @return string|null An error message, or null when fully signed in
         */
        public static function verifySecondFactor(string $code): ?string
        {

            // must be mid-login
            $uid = self::pending('mfa');
            if ($uid === null) {
                return 'Your sign-in expired. Please start again.';
            }

            // locked out
            if (Throttle::locked('mfa-user', (string) $uid)) {
                self::clear();
                Audit::log($uid, 'mfa.locked');
                return self::LOCKED;
            }

            // try it as a totp code, then as a recovery code
            $method = self::checkTotp($uid, $code) ? 'totp' : (self::useRecoveryCode($uid, $code) ? 'recovery' : '');
            if ($method === '') {
                // count the failure; end the half-finished login once locked
                Throttle::fail('mfa-user', (string) $uid);
                Audit::log($uid, 'mfa.failed');
                if (Throttle::locked('mfa-user', (string) $uid)) {
                    self::clear();
                    return self::LOCKED;
                }
                return 'That code is not valid.';
            }

            // a recovery code was burned; tell them how many remain
            if ($method === 'recovery') {
                $left = (int) Db::value('recovery_codes_remaining', [$uid]);
                Audit::log($uid, 'mfa.recovery_used', 'remaining: ' . $left);
                Flash::add('warning', "You signed in with a recovery code. {$left} remain; "
                    . 'regenerate them from your account page if you are running low.');
            }

            // done
            Throttle::clear('mfa-user', (string) $uid);
            self::complete($uid, $method);
            return null;
        }

        /**
         * Check and claim a totp code for an enrolled user
         *
         * @since 8.5
         * @access public
         *
         * @param  int    $uid  The user id
         * @param  string $code The submitted code
         * @return bool
         */
        public static function checkTotp(int $uid, string $code): bool
        {

            // load and decrypt the secret
            $row = Db::row('user_totp_get', [$uid]);
            if ($row === null || (int) $row->totp_enabled !== 1 || empty($row->totp_secret)) {
                return false;
            }
            $secret = Crypto::decrypt((string) $row->totp_secret, Config::appKey(), 'totp:' . $uid);
            if ($secret === '') {
                return false;
            }

            // verify, then atomically claim the step so the code can't be replayed
            $step = Totp::verify($secret, $code);
            return $step !== null && (int) Db::value('user_totp_step_claim', [$uid, $step]) === 1;
        }

        /**
         * Consume a recovery code
         *
         * @since 8.5
         * @access private
         *
         * @param  int    $uid  The user id
         * @param  string $code The submitted code
         * @return bool
         */
        private static function useRecoveryCode(int $uid, string $code): bool
        {

            // normalize to the bare 10 characters
            $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
            if (! preg_match('/^[' . self::RECOVERY_ALPHABET . ']{10}$/', $code)) {
                return false;
            }
            return (int) Db::value('recovery_code_use', [$uid, self::recoveryHash($uid, $code)]) === 1;
        }

        /**
         * Hash a recovery code with the app key
         *
         * @since 8.5
         * @access private
         *
         * @param  int    $uid  The user id
         * @param  string $code The normalized code
         * @return string
         */
        private static function recoveryHash(int $uid, string $code): string
        {
            return Crypto::hmac('recovery|' . $uid . '|' . $code, Config::appKey());
        }

        /**
         * Issue a fresh set of recovery codes, replacing any old ones
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @return list<string> The codes, formatted for display
         */
        public static function issueRecoveryCodes(int $uid): array
        {

            // generate them
            $randomizer = new \Random\Randomizer(new \Random\Engine\Secure());
            $codes = [];
            $hashes = [];
            while (count($codes) < self::RECOVERY_CODE_COUNT) {
                $code = $randomizer->getBytesFromString(self::RECOVERY_ALPHABET, 10);
                if (isset($codes[$code])) {
                    continue;
                }
                $codes[$code] = substr($code, 0, 5) . '-' . substr($code, 5);
                $hashes[] = self::recoveryHash($uid, $code);
            }

            // store only the hashes
            Db::value('recovery_codes_replace', [$uid, implode(',', $hashes)]);
            return array_values($codes);
        }

        /**
         * Get (creating once) the secret for a pending enrollment
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function enrollmentSecret(): string
        {
            $secret = Session::get(self::ENROLL_KEY);
            if (! is_string($secret) || $secret === '') {
                $secret = Totp::secret();
                Session::set(self::ENROLL_KEY, $secret);
            }
            return $secret;
        }

        /**
         * Confirm a pending totp enrollment
         *
         * @since 8.5
         * @access public
         *
         * @param  string $code The code from the authenticator app
         * @return string|null An error message, or null when enrolled and signed in
         */
        public static function confirmEnrollment(string $code): ?string
        {

            // must be mid-login in the enroll stage
            $uid = self::pending('enroll');
            if ($uid === null) {
                return 'Your sign-in expired. Please start again.';
            }

            // locked out
            if (Throttle::locked('mfa-user', (string) $uid)) {
                self::clear();
                return self::LOCKED;
            }

            // verify against the pending secret
            $secret = self::enrollmentSecret();
            $step = Totp::verify($secret, $code);
            if ($step === null) {
                Throttle::fail('mfa-user', (string) $uid);
                return 'That code is not valid. Check your device clock and try again.';
            }

            // store it encrypted
            $encrypted = Crypto::encrypt($secret, Config::appKey(), 'totp:' . $uid);
            if ($encrypted === '' || (int) Db::value('user_totp_enable', [$uid, $encrypted, $step]) !== 1) {
                return 'Two-factor authentication could not be enabled.';
            }

            // issue recovery codes to show once
            Session::set(self::CODES_KEY, self::issueRecoveryCodes($uid));
            Session::remove(self::ENROLL_KEY);
            Throttle::clear('mfa-user', (string) $uid);
            Audit::log($uid, 'totp.enrolled');

            // done
            self::complete($uid, 'enroll');
            return null;
        }

        /**
         * Hold freshly issued recovery codes for a one-time display
         *
         * @since 8.5
         * @access public
         *
         * @param  list<string> $codes The codes
         * @return void
         */
        public static function stashRecoveryCodes(array $codes): void
        {
            Session::set(self::CODES_KEY, $codes);
        }

        /**
         * Take freshly issued recovery codes; they are shown exactly once
         *
         * @since 8.5
         * @access public
         *
         * @return list<string>
         */
        public static function takeRecoveryCodes(): array
        {
            $codes = Session::get(self::CODES_KEY, []);
            Session::remove(self::CODES_KEY);
            return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
        }

        /**
         * Finish signing in
         *
         * @since 8.5
         * @access private
         *
         * @param  int    $uid    The user id
         * @param  string $method How the second factor was satisfied
         * @return void
         */
        private static function complete(int $uid, string $method): void
        {

            // reload so the session version is current
            $user = Db::row('user_get', [$uid]);
            if ($user === null) {
                self::clear();
                return;
            }

            // fresh session id and csrf token for the privileged session
            Session::regenerate(true);
            Csrf::rotate();
            $now = time();
            Session::set(self::KEY, [
                'uid' => $uid,
                'sv' => (int) $user->session_version,
                'stage' => 'full',
                'started' => $now,
                'seen' => $now,
                'stage_started' => $now,
                'ua' => self::agentHash(),
            ]);

            // bookkeeping
            Db::value('user_login_record', [$uid, Request::ip()]);
            Throttle::clear('login-user', (string) $user->username);
            Audit::log($uid, 'login.success', 'second factor: ' . $method);
            self::$user = false;
        }

        /**
         * Get the fully authenticated user for this request
         *
         * @since 8.5
         * @access public
         *
         * @return object|null
         */
        public static function user(): ?object
        {

            // resolve once per request
            if (self::$user !== false) {
                return self::$user;
            }
            self::$user = null;

            // must be fully signed in
            $state = Session::get(self::KEY);
            if (! is_array($state) || ($state['stage'] ?? '') !== 'full') {
                return null;
            }

            // timeouts and agent binding
            $now = time();
            $idle = max(1, (int) Config::get('security.idle_timeout', 30)) * 60;
            $absolute = max(1, (int) Config::get('security.absolute_timeout', 12)) * 3600;
            if (
                $now - (int) ($state['seen'] ?? 0) > $idle
                || $now - (int) ($state['started'] ?? 0) > $absolute
                || ! Crypto::timingSafeEquals((string) ($state['ua'] ?? ''), self::agentHash())
            ) {
                self::clear();
                Flash::add('info', 'Your session ended. Please sign in again.');
                return null;
            }

            // the account must still be active and the session version current
            $user = Db::row('user_get', [(int) $state['uid']]);
            if ($user === null || (int) $user->is_active !== 1 || (int) $user->session_version !== (int) $state['sv']) {
                self::clear();
                return null;
            }

            // touch and hold it
            $state['seen'] = $now;
            Session::set(self::KEY, $state);
            self::$user = $user;
            return $user;
        }

        /**
         * Adopt a new session version after changing security settings,
         * keeping this session alive while every other one ends
         *
         * @since 8.5
         * @access public
         *
         * @param  int $version The new session version
         * @return void
         */
        public static function adoptSessionVersion(int $version): void
        {
            $state = Session::get(self::KEY);
            if (is_array($state)) {
                $state['sv'] = $version;
                Session::set(self::KEY, $state);
                Session::regenerate(true);
            }
            self::$user = false;
        }

        /**
         * Re-authenticate the signed-in user with their password and a totp code
         *
         * @since 8.5
         * @access public
         *
         * @param  object $user     The signed-in user
         * @param  string $password The current password
         * @param  string $code     A current totp code
         * @return string|null An error message, or null when confirmed
         */
        public static function reauthenticate(object $user, string $password, string $code): ?string
        {

            // throttle re-auth like logins
            $uid = (int) $user->id;
            if (Throttle::locked('reauth-user', (string) $uid)) {
                return self::LOCKED;
            }

            // check both factors
            $row = Db::row('user_password_get', [$uid]);
            $hash = $row !== null ? (string) $row->password_hash : self::DUMMY_HASH;
            $ok = password_verify(substr($password, 0, 1024), $hash) && $row !== null;
            $ok = self::checkTotp($uid, $code) && $ok;
            if (! $ok) {
                Throttle::fail('reauth-user', (string) $uid);
                Audit::log($uid, 'reauth.failed');
                return 'Your password or authentication code is not correct.';
            }

            Throttle::clear('reauth-user', (string) $uid);
            return null;
        }

        /**
         * Sign out
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public static function logout(): void
        {
            $state = Session::get(self::KEY);
            if (is_array($state) && isset($state['uid'])) {
                Audit::log((int) $state['uid'], 'logout');
            }
            Session::destroy();
            self::$user = false;
        }

        /**
         * Drop the auth state but keep the session (for flash messages)
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public static function clear(): void
        {
            Session::remove(self::KEY);
            Session::remove(self::ENROLL_KEY);
            Session::remove(self::CODES_KEY);
            Session::regenerate(true);
            self::$user = false;
        }

        /**
         * Hash the user agent the session is bound to
         *
         * @since 8.5
         * @access private
         *
         * @return string
         */
        private static function agentHash(): string
        {
            return Crypto::hmac(Request::userAgent(), Config::appKey());
        }
    }
}
