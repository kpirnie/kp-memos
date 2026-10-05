<?php

/**
 * KP Memos Security Headers
 *
 * Emits the hardened response headers for every dynamic response,
 * including a nonce-based Content-Security-Policy.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Security')) {

    /**
     * Security
     *
     * Per-request CSP nonce and security header emission.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Security
    {
        /** @var string|null the per-request csp nonce */
        private static ?string $nonce = null;

        /**
         * Get the per-request CSP nonce
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public static function nonce(): string
        {

            // generate once per request
            if (self::$nonce === null) {
                self::$nonce = base64_encode(random_bytes(18));
            }
            return self::$nonce;
        }

        /**
         * Send the security headers
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public static function sendHeaders(): void
        {

            // nothing to do if output already started
            if (headers_sent()) {
                return;
            }

            // hold the nonce
            $nonce = self::nonce();

            // build the content security policy
            // note bodies are purified html; external https images and media, plus youtube / vimeo embeds, are the only remote content allowed
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self'",
                "connect-src 'self'",
                "media-src 'self' blob: https:",
                "worker-src 'self'",
                "manifest-src 'self'",
                "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
                "frame-ancestors 'none'",
                "object-src 'none'",
                "base-uri 'none'",
                "form-action 'self'",
                'upgrade-insecure-requests',
            ]);

            // send them
            header('Content-Security-Policy: ' . $csp);
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Cross-Origin-Opener-Policy: same-origin');
            header('Cross-Origin-Resource-Policy: same-origin');
            header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), '
                . 'magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()');

            // dynamic responses are private and never cached
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');

            // don't advertise the stack
            header_remove('X-Powered-By');
        }
    }
}
