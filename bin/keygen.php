<?php

/**
 * KP Memos Key Generator
 *
 * Prints a new random application key for app.key in app/config.php.
 *
 * usage: php bin/keygen.php
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// cli only
if (PHP_SAPI !== 'cli') {
    exit(1);
}

// load the dependencies
require dirname(__DIR__) . '/vendor/autoload.php';

// print a 32 byte hex key
fwrite(STDOUT, \KPT\Crypto::generateKey(32) . PHP_EOL);
