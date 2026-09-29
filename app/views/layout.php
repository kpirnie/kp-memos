<?php

/**
 * KP Memos Layout
 *
 * The shared html shell wrapped around every page.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var string      $content  The rendered page content
 * @var string      $appName  The application name
 * @var string      $nonce    The csp nonce
 * @var string|null $title    The page title
 * @var object|null $user     The authenticated user, if any
 * @var bool|null   $bare     Render without the navigation (auth screens)
 * @var list<string>|null $scripts Extra script paths for this page
 * @var list<string>|null $styles  Extra stylesheet paths for this page
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\View;

// hold the layout state
$user = $user ?? null;
$bare = $bare ?? false;
$scripts = $scripts ?? [];
$styles = $styles ?? [];
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $appName : $appName;
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#070e20">
    <meta name="kpm-csrf" content="<?= e(Csrf::token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <meta name="application-name" content="<?= e($appName) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="<?= e(View::asset('assets/img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= e(View::asset('assets/img/icon-192.png')) ?>" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?= e(View::asset('assets/img/apple-touch-icon.png')) ?>">
    <link rel="preload" href="/assets/fonts/sora-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/orbitron-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(View::asset('assets/css/app.css')) ?>">
<?php foreach ($styles as $style) : ?>
    <link rel="stylesheet" href="<?= e(View::asset($style)) ?>">
<?php endforeach; ?>
</head>
<body>
    <a class="kpm-sr-only" href="#kpm-main">Skip to content</a>
<?php if (! $bare) : ?>
    <header class="kpm-nav">
        <div class="kpm-container kpm-nav-inner">
            <a class="kpm-brand" href="/">
                <img src="<?= e(View::asset('assets/img/logo.svg')) ?>" alt="" width="40" height="40">
                <span class="kpm-brand-word"><span class="kpm-brand-kp">KP</span><span class="kpm-brand-memos">MEMOS</span></span>
            </a>
<?php if ($user !== null) : ?>
            <button class="kpm-nav-toggle" type="button" aria-expanded="false" aria-controls="kpm-nav-links" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <ul class="kpm-nav-links" id="kpm-nav-links">
                <li><a href="/notes"<?= str_starts_with($path, '/notes') ? ' class="kpm-active"' : '' ?>>Notes</a></li>
                <li><a href="/categories"<?= str_starts_with($path, '/categories') ? ' class="kpm-active"' : '' ?>>Categories</a></li>
                <li><a href="/tags"<?= str_starts_with($path, '/tags') ? ' class="kpm-active"' : '' ?>>Tags</a></li>
<?php if (($user->role ?? '') === 'admin') : ?>
                <li><a href="/admin/users"<?= str_starts_with($path, '/admin') ? ' class="kpm-active"' : '' ?>>Users</a></li>
<?php endif; ?>
                <li><a href="/account"<?= str_starts_with($path, '/account') ? ' class="kpm-active"' : '' ?>>Account</a></li>
                <li>
                    <form method="post" action="/logout">
                        <?= Csrf::field() ?>
                        <button type="submit">Sign out</button>
                    </form>
                </li>
                <li class="kpm-nav-cta"><a href="/notes/new">New note</a></li>
            </ul>
<?php endif; ?>
        </div>
    </header>
<?php endif; ?>
<?php if ($bare) : ?>
    <main class="kpm-auth" id="kpm-main">
        <?= $content ?>
    </main>
<?php else : ?>
    <main class="kpm-main" id="kpm-main">
        <div class="kpm-container">
            <?= $this->view('partials/flash.php', ['flashes' => $flashes ?? [], 'error' => null]) ?>
            <?= $content ?>
        </div>
    </main>
<?php endif; ?>
    <footer class="kpm-footer">
        <div class="kpm-container">
            <p>&copy; <?= e(date('Y')) ?> <?= e($appName) ?></p>
        </div>
    </footer>
    <script nonce="<?= e($nonce) ?>" src="<?= e(View::asset('assets/js/app.js')) ?>"></script>
<?php foreach ($scripts as $script) : ?>
    <script nonce="<?= e($nonce) ?>" src="<?= e(View::asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
