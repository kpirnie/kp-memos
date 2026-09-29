<?php

/**
 * KP Memos Shared Note Password
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var string      $token
 * @var string|null $error
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-auth-box">
    <?php echo $this->view('partials/brand.php') ?>
    <div class="kpm-card">
        <span class="kpm-eyebrow">Protected</span>
        <h1>This note is password protected</h1>
        <p class="kpm-lead">Enter the password you were given to view it.</p>
        <?php echo $this->view('partials/flash.php', ['flashes' => [], 'error' => $error ?? null]) ?>
        <form method="post" action="/s/<?php echo e($token) ?>" autocomplete="off">
            <?php echo Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="password">Password</label>
                <input class="kpm-input" id="password" name="password" type="password" maxlength="1024" required autofocus>
            </div>
            <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit">View note</button>
        </form>
    </div>
</div>