<?php

/**
 * KP Memos Audit Trail
 *
 * Writes security events to the audit log.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Audit')) {

    /**
     * Audit
     *
     * Security event logging.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Audit
    {
        /**
         * Record an event
         *
         * @since 8.5
         * @access public
         *
         * @param  int    $userId The user the event concerns, or 0
         * @param  string $event  The event name, e.g. "login.success"
         * @param  string $detail Optional detail, never secrets
         * @return void
         */
        public static function log(int $userId, string $event, string $detail = ''): void
        {

            // auditing must never break the request
            try {
                Db::value('audit_add', [
                    $userId,
                    mb_substr($event, 0, 64),
                    Request::ip(),
                    Request::userAgent(),
                    $detail !== '' ? mb_substr($detail, 0, 1024) : null,
                ]);
            } catch (\Throwable $e) {
                error_log('kpm audit write failed: ' . $e->getMessage());
            }
        }
    }
}
