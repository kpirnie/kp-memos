<?php

/**
 * KP Memos Front Controller
 *
 * The single public entry point for every dynamic request.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects

// hold the application root
define('KPM_PATH', __DIR__);

// load the dependencies
require KPM_PATH . '/vendor/autoload.php';

// boot and run the app
(new \KPM\Core\App())->run();
