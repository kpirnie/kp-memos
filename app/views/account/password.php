<?php

/**
 * KP Memos Change Password
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object $user
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-narrow">
    <div class="kpm-page-head">
        <div>
            <h1>Change password</h1>
            <?php if ((int) $user->must_change_password === 1) : ?>
                <p>Your account requires a new password before you continue.</p>
            <?php else : ?>
                <p>Changing your password signs out every other session.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="kpm-card">
        <form method="post" action="/account/password" autocomplete="off">
            <?php echo Csrf::field() ?>
            <?php echo $this->view('account/reauth.php') ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="new_password">New password</label>
                <input class="kpm-input" id="new_password" name="new_password" type="password"
                    autocomplete="new-password" minlength="12" maxlength="1024" required>
                <p class="kpm-help">At least 12 characters with an uppercase letter, a lowercase letter, a digit,
                    and a symbol.</p>
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="confirm_password">Confirm new password</label>
                <input class="kpm-input" id="confirm_password" name="confirm_password" type="password"
                    autocomplete="new-password" minlength="12" maxlength="1024" required>
            </div>
            <button class="kpm-btn kpm-btn-primary" type="submit">Change password</button>
        </form>
    </div>
</div>