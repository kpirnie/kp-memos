<?php

/**
 * KP Memos Formatting
 *
 * Display formatting for dates and sizes. Every datetime in the database is
 * utc; it is shown in the configured application timezone.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the number helpers
use KPT\Num;

// if the class does not exist already
if (! class_exists('\KPM\Core\Format')) {

    /**
     * Format
     *
     * Display helpers.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Format
    {
        /**
         * Convert a utc database datetime to a local DateTimeImmutable
         *
         * @since 8.5
         * @access public
         *
         * @param  string|null $utc The utc datetime
         * @return \DateTimeImmutable|null
         */
        public static function local(?string $utc): ?\DateTimeImmutable
        {
            if ($utc === null || $utc === '') {
                return null;
            }
            try {
                return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Throwable) {
                return null;
            }
        }

        /**
         * Format a utc database datetime for display
         *
         * @since 8.5
         * @access public
         *
         * @param  string|null $utc    The utc datetime
         * @param  string      $format The display format
         * @return string
         */
        public static function datetime(?string $utc, string $format = 'M j, Y g:i A'): string
        {
            $local = self::local($utc);
            return $local !== null ? $local->format($format) : '-';
        }

        /**
         * Convert a local date or datetime string to a utc database datetime
         *
         * @since 8.5
         * @access public
         *
         * @param  string $local The local value, e.g. "2026-09-28" or "2026-09-28T14:30"
         * @return string|null
         */
        public static function toUtc(string $local): ?string
        {
            $local = trim($local);
            if ($local === '') {
                return null;
            }
            try {
                return (new \DateTimeImmutable($local, new \DateTimeZone(date_default_timezone_get())))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        /**
         * Format a byte count
         *
         * @since 8.5
         * @access public
         *
         * @param  int|string $bytes The size
         * @return string
         */
        public static function bytes(int|string $bytes): string
        {
            return Num::formatBytes((int) $bytes);
        }
    }
}
