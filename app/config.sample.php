<?php

/**
 * KP Memos Configuration Sample
 *
 * Copy this file to app/config.php and fill in the values. app/config.php
 * is git-ignored and must never be committed.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// return the configuration array
return [

    // application settings
    'app' => [

        // the name shown in the ui
        'name' => 'KP Memos',

        // the public base url, no trailing slash
        'url' => 'https://memos.example.com',

        // php timezone identifier
        'timezone' => 'America/New_York',

        // 64 hex characters (32 bytes); generate with: php bin/keygen.php
        // changing this invalidates every enrolled totp secret and recovery code
        'key' => '',

        // show error details in responses; never enable in production
        'debug' => false,
    ],

    // mariadb connection for the restricted application account
    'db' => [
        'server' => 'localhost',
        'schema' => 'kp_memos',
        'username' => 'kp_memos_app',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // redis connection, used for login throttling
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'timeout' => 1.0,
        'prefix' => 'kpm:',
    ],

    // security settings
    'security' => [

        // ip addresses of reverse proxies allowed to set X-Forwarded-For
        // leave empty when php-fpm receives connections directly from clients
        'trusted_proxies' => [],

        // minutes of inactivity before a session is ended
        'idle_timeout' => 30,

        // hours before a session is ended regardless of activity
        'absolute_timeout' => 12,

        // failed attempts allowed per account / per ip before lockout
        'max_attempts' => 5,

        // minutes an account / ip stays locked out
        'lockout_minutes' => 15,
    ],
];
