<?php

/**
 * KP Memos Admin Bootstrap
 *
 * Creates an administrator account from the command line. Use it for the
 * first admin, or to regain access if every admin is locked out.
 *
 * usage: php bin/create-admin.php
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects

// we need the core
use KPM\Core\Auth;
use KPM\Core\Cli;
use KPM\Core\Config;
use KPM\Core\Db;
use KPT\Validate;

// cli only
if (PHP_SAPI !== 'cli') {
    exit(1);
}

// hold the application root and load the dependencies
define('KPM_PATH', dirname(__DIR__));
require KPM_PATH . '/vendor/autoload.php';

// load the configuration and make sure the key is usable before storing anything keyed by it
Config::load(KPM_PATH . '/app/config.php');
try {
    Config::appKey();
} catch (\Throwable $e) {
    Cli::fail($e->getMessage());
}

// gather the account details
Cli::line('Create a KP Memos administrator');
$username = Auth::normalizeUsername(Cli::ask('Username'));
if (! preg_match('/^[a-z0-9][a-z0-9._\-]{2,63}$/', $username)) {
    Cli::fail('Usernames are 3-64 characters: letters, digits, dot, dash, underscore.');
}
$display = mb_substr(Cli::ask('Display name', $username), 0, 128);
$email = Cli::ask('Email (optional)');
if ($email !== '' && ! Validate::email($email)) {
    Cli::fail('That email address is not valid.');
}

// the password, twice
$password = Cli::secret('Password');
if (($error = Auth::passwordPolicy($password, $username)) !== null) {
    Cli::fail($error);
}
if (Cli::secret('Confirm password') !== $password) {
    Cli::fail('The passwords do not match.');
}

// create it
try {
    $id = (int) Db::value('admin_user_create', [$username, $display, $email, Auth::hash($password), 'admin']);
    if ($id === -1) {
        Cli::fail('That username is already taken.');
    }
    if ($id < 1) {
        Cli::fail('The account could not be created.');
    }

    // they chose this password themselves, so no forced change
    Db::value('user_password_set', [$id, Auth::hash($password), 0]);
    Db::value('audit_add', [$id, 'user.created', 'cli', 'bin/create-admin.php', 'role: admin']);
} catch (\Throwable $e) {
    Cli::fail('Database error: ' . $e->getMessage());
}

// done
Cli::line("Admin {$username} created. Two-factor setup is required at first sign in.");
