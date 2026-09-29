<?php

/**
 * KP Memos Second Factor
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-auth-box">
    <?= $this->view('partials/brand.php') ?>
    <div class="kpm-card">
        <span class="kpm-eyebrow">Step 2 of 2</span>
        <h1>Authentication code</h1>
        <p class="kpm-lead">Enter the 6 digit code from your authenticator app, or one of your recovery codes.</p>
        <?= $this->view('partials/flash.php', ['flashes' => $flashes ?? [], 'error' => $error ?? null]) ?>
        <form method="post" action="/login/verify" autocomplete="off">
            <?= Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="code">Code</label>
                <input class="kpm-input kpm-input-code" id="code" name="code" type="text" inputmode="text"
                    autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                    maxlength="16" required autofocus>
            </div>
            <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit">Verify</button>
        </form>
    </div>
</div>
