<?php

/**
 * KP Memos Admin: Manage User
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object $user    The signed-in admin
 * @var object $account The account being managed
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Format;

// hold the state
$self = (int) $account->id === (int) $user->id;
$base = '/admin/users/' . (int) $account->id;
?>
<div class="kpm-page-head">
    <div>
        <a class="kpm-back" href="/admin/users">&larr; Users</a>
        <h1><?php echo e($account->username) ?></h1>
        <p>Created <?php echo e(Format::datetime($account->created_at)) ?> &middot;
            Last sign in <?php echo e(Format::datetime($account->last_login_at)) ?>
            <?php if (! empty($account->last_login_ip)) : ?>from <span class="kpm-mono"><?php echo e($account->last_login_ip) ?></span><?php endif; ?></p>
    </div>
</div>

<div class="kpm-grid-cards">
    <section class="kpm-card">
        <h2 class="kpm-card-title">Details</h2>
        <form method="post" action="<?php echo e($base) ?>">
            <?php echo Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="display_name">Display name</label>
                <input class="kpm-input" id="display_name" name="display_name" maxlength="128" value="<?php echo e($account->display_name) ?>">
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="email">Email</label>
                <input class="kpm-input" id="email" name="email" type="email" maxlength="255" value="<?php echo e($account->email ?? '') ?>">
            </div>
            <div class="kpm-grid-2">
                <div class="kpm-form-group">
                    <label class="kpm-label" for="role">Role</label>
                    <select class="kpm-select" id="role" name="role" <?php echo $self ? ' disabled' : '' ?>>
                        <option value="user" <?php echo $account->role === 'user' ? ' selected' : '' ?>>User</option>
                        <option value="admin" <?php echo $account->role === 'admin' ? ' selected' : '' ?>>Admin</option>
                    </select>
                    <?php if ($self) : ?>
                        <input type="hidden" name="role" value="admin">
                    <?php endif; ?>
                </div>
                <div class="kpm-form-group">
                    <span class="kpm-label">Status</span>
                    <label class="kpm-check">
                        <input type="checkbox" name="is_active" value="1" <?php echo (int) $account->is_active === 1 ? ' checked' : '' ?><?php echo $self ? ' disabled' : '' ?>>
                        Active
                    </label>
                    <?php if ($self) : ?>
                        <input type="hidden" name="is_active" value="1">
                    <?php endif; ?>
                </div>
            </div>
            <button class="kpm-btn kpm-btn-primary" type="submit">Save</button>
        </form>
    </section>

    <section class="kpm-card">
        <h2 class="kpm-card-title">Security</h2>
        <p class="kpm-small kpm-muted">Two-factor:
            <span class="kpm-badge <?php echo (int) $account->totp_enabled === 1 ? 'kpm-badge-green' : 'kpm-badge-orange' ?>"><?php echo (int) $account->totp_enabled === 1 ? 'on' : 'pending setup' ?></span>
            <?php if ((int) $account->must_change_password === 1) : ?>
                <span class="kpm-badge kpm-badge-orange">password change pending</span>
            <?php endif; ?>
        </p>
        <?php if ($self) : ?>
            <p class="kpm-help">Manage your own password and two-factor from your <a href="/account">account page</a>.</p>
        <?php else : ?>
            <div class="kpm-stack kpm-mt">
                <form method="post" action="<?php echo e($base) ?>/password" data-confirm="Reset this user's password and end their sessions?">
                    <?php echo Csrf::field() ?>
                    <button class="kpm-btn kpm-btn-secondary kpm-btn-block" type="submit">Reset password</button>
                </form>
                <form method="post" action="<?php echo e($base) ?>/totp" data-confirm="Remove this user's two-factor? They will set it up again at next sign in.">
                    <?php echo Csrf::field() ?>
                    <button class="kpm-btn kpm-btn-secondary kpm-btn-block" type="submit">Reset two-factor</button>
                </form>
            </div>
            <details class="kpm-details">
                <summary>Delete user</summary>
                <p class="kpm-help">Permanently deletes the account, all of its notes, and all attachments. This cannot be undone.</p>
                <form method="post" action="<?php echo e($base) ?>/delete" autocomplete="off">
                    <?php echo Csrf::field() ?>
                    <div class="kpm-form-group">
                        <label class="kpm-label" for="confirm">Type <span class="kpm-mono"><?php echo e($account->username) ?></span> to confirm</label>
                        <input class="kpm-input" id="confirm" name="confirm" autocapitalize="none" spellcheck="false" required>
                    </div>
                    <button class="kpm-btn kpm-btn-danger" type="submit">Delete permanently</button>
                </form>
            </details>
        <?php endif; ?>
    </section>
</div>