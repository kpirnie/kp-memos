<?php

/**
 * KP Memos Sign In
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var string|null $username
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-auth-box">
    <?php echo $this->view('partials/brand.php') ?>
    <div class="kpm-card">
        <h1>Sign in</h1>
        <p class="kpm-lead">Private notes, protected by two-factor authentication.</p>
        <?php echo $this->view('partials/flash.php', ['flashes' => $flashes ?? [], 'error' => $error ?? null]) ?>
        <form method="post" action="/login" autocomplete="on">
            <?php echo Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="username">Username</label>
                <input class="kpm-input" id="username" name="username" type="text" value="<?php echo e($username ?? '') ?>"
                    autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="64" required autofocus>
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="password">Password</label>
                <input class="kpm-input" id="password" name="password" type="password"
                    autocomplete="current-password" maxlength="1024" required>
            </div>
            <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit">Continue</button>
        </form>
    </div>
</div>