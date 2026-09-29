<?php

/**
 * KP Memos Database Installer
 *
 * Creates the schema, installs or upgrades every table and stored
 * procedure, and creates the restricted application account with EXECUTE
 * as its only privilege. Safe to re-run after pulling updates.
 *
 * usage: php bin/install.php
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects

// we need the database wrapper and config
use KPM\Core\Cli;
use KPM\Core\Config;
use KPT\Database;

// cli only
if (PHP_SAPI !== 'cli') {
    exit(1);
}

// hold the application root and load the dependencies
define('KPM_PATH', dirname(__DIR__));
require KPM_PATH . '/vendor/autoload.php';

// load the configuration
Config::load(KPM_PATH . '/app/config.php');

// hold the target settings
$schema = (string) Config::get('db.schema', '');
$appUser = (string) Config::get('db.username', '');
$appPass = (string) Config::get('db.password', '');
$server = (string) Config::get('db.server', 'localhost');

// validate them
if (! preg_match('/^[A-Za-z0-9_$\-]{1,64}$/', $schema)) {
    Cli::fail("db.schema '{$schema}' may only contain letters, digits, _, \$ and - (max 64).");
}
// the username is only ever quoted or bound, so any printable ascii works except quotes and backslashes
if (! preg_match('/^[\x21-\x7E]{1,80}$/', $appUser) || strpbrk($appUser, "'\"`\\") !== false) {
    Cli::fail('db.username must be 1-80 printable characters with no spaces, quotes, backticks, or backslashes.');
}
if (strlen($appPass) < 16) {
    Cli::fail('db.password in app/config.php must be at least 16 characters.');
}

// intro
Cli::line('KP Memos installer');
Cli::line("Schema: {$schema} on {$server}; application account: {$appUser}");
Cli::line('Enter a MariaDB account that can create databases, routines, and users.');
Cli::line('It becomes the definer of every stored procedure, so it must remain in place.');

// gather the privileged credentials and the app account host
$adminUser = Cli::ask('Admin username', 'root');
$adminPass = Cli::secret('Admin password (blank for socket auth)');
$appHost = Cli::ask('Host the application connects from', 'localhost');

// validate the host part
if (! preg_match('/^[A-Za-z0-9_.%:\-]{1,255}$/', $appHost)) {
    Cli::fail('Invalid host.');
}

// refuse to reuse the admin account as the application account
if (strcasecmp($adminUser, $appUser) === 0) {
    Cli::fail('The application account must not be the admin account.');
}

// connect without a schema so we can create it
try {
    $db = new Database((object) [
        'driver' => 'mysql',
        'server' => $server,
        'schema' => '',
        'username' => $adminUser,
        'password' => $adminPass,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
    $db->query("CREATE DATABASE IF NOT EXISTS `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")->execute();
    $db->query("USE `{$schema}`")->execute();
    $db->query("SET time_zone = '+00:00'")->execute();
} catch (\Throwable $e) {
    Cli::fail('Connection failed: ' . $e->getMessage());
}
Cli::line("Schema {$schema} ready.");

// run every sql file in order
$files = glob(KPM_PATH . '/database/*.sql') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    // split the file into statements, honoring DELIMITER directives
    $statements = Cli::splitSql((string) file_get_contents($file));

    // run them
    try {
        foreach ($statements as $statement) {
            $db->query($statement)->execute();
        }
    } catch (\Throwable $e) {
        Cli::fail(basename($file) . ': ' . $e->getMessage());
    }
    Cli::line(sprintf('  %-32s %d statements', basename($file), count($statements)));
}

// create or update the application account with execute only
try {
    $account = $db->quote($appUser) . '@' . $db->quote($appHost);
    $password = $db->quote($appPass);
    $db->query("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY {$password}")->execute();
    $db->query("ALTER USER {$account} IDENTIFIED BY {$password}")->execute();

    // strip anything it already had on this schema, then grant execute only
    $existing = $db->query('SELECT COUNT(*) AS c FROM information_schema.SCHEMA_PRIVILEGES '
        . 'WHERE GRANTEE = ? AND TABLE_SCHEMA = ?')
        ->bind(["'{$appUser}'@'{$appHost}'", $schema])
        ->first();
    if ($existing && (int) $existing->c > 0) {
        $db->query("REVOKE ALL PRIVILEGES ON `{$schema}`.* FROM {$account}")->execute();
    }
    $db->query("GRANT EXECUTE ON `{$schema}`.* TO {$account}")->execute();
    $db->query('FLUSH PRIVILEGES')->execute();
} catch (\Throwable $e) {
    Cli::fail('Account setup failed: ' . $e->getMessage());
}
Cli::line("Account {$appUser}@{$appHost} granted EXECUTE on {$schema}.* only.");

// done
Cli::line('Done. Create the first admin with: php bin/create-admin.php');
