<?php

/**
 * KP Memos Flash Messages
 *
 * One-request status messages carried through the session.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the session
use KPT\Session;

// if the class does not exist already
if (! class_exists('\KPM\Core\Flash')) {

    /**
     * Flash
     *
     * Flash message helpers.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Flash
    {
        /** @var string the session key */
        private const KEY = 'kpm_flash';

        /**
         * Queue a message
         *
         * @since 8.5
         * @access public
         *
         * @param  string $type    success, error, info, or warning
         * @param  string $message The message
         * @return void
         */
        public static function add(string $type, string $message): void
        {
            $messages = Session::get(self::KEY, []);
            $messages = is_array($messages) ? $messages : [];
            $messages[] = ['type' => $type, 'message' => $message];
            Session::set(self::KEY, $messages);
        }

        /**
         * Take every queued message
         *
         * @since 8.5
         * @access public
         *
         * @return list<array{type: string, message: string}>
         */
        public static function take(): array
        {
            $messages = Session::get(self::KEY, []);
            Session::remove(self::KEY);
            return is_array($messages) ? $messages : [];
        }
    }
}
