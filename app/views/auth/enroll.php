<?php

/**
 * KP Memos TOTP Enrollment
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var string $qr     Inline svg qr code, generated server-side
 * @var string $secret The base32 secret, grouped for manual entry
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-auth-box">
    <?php echo $this->view('partials/brand.php') ?>
    <div class="kpm-card">
        <span class="kpm-eyebrow">Required</span>
        <h1>Set up two-factor authentication</h1>
        <p class="kpm-lead">Scan this code with an authenticator app, then enter the 6 digit code it shows.</p>
        <?php echo $this->view('partials/flash.php', ['flashes' => $flashes ?? [], 'error' => $error ?? null]) ?>
        <div class="kpm-qr"><?php echo $qr ?></div>
        <p class="kpm-help">Can't scan it? Enter this key manually:</p>
        <p class="kpm-secret kpm-mono"><?php echo e($secret) ?></p>
        <form method="post" action="/login/enroll" autocomplete="off">
            <?php echo Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="code">Code</label>
                <input class="kpm-input kpm-input-code" id="code" name="code" type="text" inputmode="numeric"
                    pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="8" required autofocus>
            </div>
            <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit">Enable and continue</button>
        </form>
    </div>
</div>