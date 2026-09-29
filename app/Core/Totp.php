<?php

/**
 * KP Memos TOTP
 *
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 6 digits, 30 second
 * steps), RFC 4648 base32, and otpauth provisioning uris.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the crypto helpers
use KPT\Crypto;

// if the class does not exist already
if (! class_exists('\KPM\Core\Totp')) {

    /**
     * Totp
     *
     * One-time password generation and verification.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Totp
    {
        /** @var string the rfc 4648 base32 alphabet */
        private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        /** @var int seconds per step */
        private const PERIOD = 30;

        /** @var int code length */
        private const DIGITS = 6;

        /** @var int steps of clock drift tolerated either side */
        private const WINDOW = 1;

        /**
         * Generate a new base32 secret (160 bits, per rfc 4226's recommendation)
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function secret(): string
        {
            return self::base32Encode(random_bytes(20));
        }

        /**
         * Get the current time step
         *
         * @since 8.5
         * @access public
         *
         * @param  int|null $time Unix time, or null for now
         * @return int
         */
        public static function step(?int $time = null): int
        {
            return intdiv($time ?? time(), self::PERIOD);
        }

        /**
         * Compute the code for a step
         *
         * @since 8.5
         * @access public
         *
         * @param  string $secret Base32 secret
         * @param  int    $step   Time step
         * @return string
         */
        public static function code(string $secret, int $step): string
        {

            // hmac the big-endian 64 bit counter
            $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);

            // dynamic truncation
            $offset = ord($hash[19]) & 0x0f;
            $binary = ((ord($hash[$offset]) & 0x7f) << 24)
                | ((ord($hash[$offset + 1]) & 0xff) << 16)
                | ((ord($hash[$offset + 2]) & 0xff) << 8)
                | (ord($hash[$offset + 3]) & 0xff);

            // pad to the digit count
            return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
        }

        /**
         * Verify a code, returning the step it matched
         *
         * Every candidate step is checked so the timing doesn't reveal which
         * one matched. The caller must claim the returned step so the code
         * can't be replayed.
         *
         * @since 8.5
         * @access public
         *
         * @param  string   $secret Base32 secret
         * @param  string   $code   The submitted code
         * @param  int|null $time   Unix time, or null for now
         * @return int|null The matched step, or null
         */
        public static function verify(string $secret, string $code, ?int $time = null): ?int
        {

            // normalize the submitted code
            $code = preg_replace('/\s+/', '', $code) ?? '';
            if (! preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
                return null;
            }

            // check every step in the window
            $current = self::step($time);
            $matched = null;
            for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
                if (Crypto::timingSafeEquals(self::code($secret, $current + $i), $code)) {
                    $matched = $current + $i;
                }
            }
            return $matched;
        }

        /**
         * Build the otpauth provisioning uri
         *
         * @since 8.5
         * @access public
         *
         * @param  string $secret  Base32 secret
         * @param  string $account The account label
         * @param  string $issuer  The issuer label
         * @return string
         */
        public static function uri(string $secret, string $account, string $issuer): string
        {
            return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
                . '?secret=' . $secret
                . '&issuer=' . rawurlencode($issuer)
                . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
        }

        /**
         * Base32 encode
         *
         * @since 8.5
         * @access public
         *
         * @param  string $data Raw bytes
         * @return string
         */
        public static function base32Encode(string $data): string
        {

            // convert to a bit string, then take 5 bits at a time
            $bits = '';
            foreach (str_split($data) as $char) {
                $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
            }
            $out = '';
            foreach (str_split($bits, 5) as $chunk) {
                $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
            }
            return $out;
        }

        /**
         * Base32 decode
         *
         * @since 8.5
         * @access public
         *
         * @param  string $data Base32 text
         * @return string
         */
        public static function base32Decode(string $data): string
        {

            // normalize and convert to a bit string
            $data = strtoupper((string) preg_replace('/[\s=]/', '', $data));
            $bits = '';
            foreach (str_split($data) as $char) {
                $pos = strpos(self::ALPHABET, $char);
                if ($pos === false) {
                    return '';
                }
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }

            // take 8 bits at a time, dropping the padding remainder
            $out = '';
            foreach (str_split($bits, 8) as $byte) {
                if (strlen($byte) === 8) {
                    $out .= chr((int) bindec($byte));
                }
            }
            return $out;
        }
    }
}
