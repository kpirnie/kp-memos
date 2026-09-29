<?php

/**
 * KP Memos Account
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object       $user
 * @var int          $remaining Unused recovery codes
 * @var list<object> $activity  Recent audit events
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Format;
?>
<div class="kpm-page-head">
    <div>
        <h1>Account</h1>
        <p>Signed in as <span class="kpm-mono"><?= e($user->username) ?></span></p>
    </div>
</div>

<div class="kpm-grid-cards">
    <section class="kpm-card">
        <h2 class="kpm-card-title">Profile</h2>
        <form method="post" action="/account/profile">
            <?= Csrf::field() ?>
            <div class="kpm-form-group">
                <label class="kpm-label" for="display_name">Display name</label>
                <input class="kpm-input" id="display_name" name="display_name" maxlength="128"
                    value="<?= e($user->display_name) ?>">
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="email">Email</label>
                <input class="kpm-input" id="email" name="email" type="email" maxlength="255"
                    value="<?= e($user->email ?? '') ?>">
            </div>
            <button class="kpm-btn kpm-btn-primary" type="submit">Save profile</button>
        </form>
    </section>

    <section class="kpm-card">
        <h2 class="kpm-card-title">Security</h2>
        <p class="kpm-muted kpm-small">Two-factor authentication is on.
            You have <strong><?= e($remaining) ?></strong> unused recovery codes.</p>
        <p class="kpm-muted kpm-small">Last sign in: <?= e(Format::datetime($user->last_login_at)) ?>
            from <span class="kpm-mono"><?= e($user->last_login_ip ?? '-') ?></span></p>
        <div class="kpm-row kpm-mt">
            <a class="kpm-btn kpm-btn-secondary" href="/account/password">Change password</a>
        </div>

        <details class="kpm-details">
            <summary>Regenerate recovery codes</summary>
            <form method="post" action="/account/recovery-codes" autocomplete="off">
                <?= Csrf::field() ?>
                <?= $this->view('account/reauth.php') ?>
                <button class="kpm-btn kpm-btn-secondary" type="submit">Issue new codes</button>
            </form>
        </details>

        <details class="kpm-details">
            <summary>Move two-factor to a new device</summary>
            <p class="kpm-help">This removes your authenticator and recovery codes and signs you out.
                You will set up a new authenticator at your next sign in.</p>
            <form method="post" action="/account/totp-reset" autocomplete="off"
                data-confirm="Remove two-factor and sign out now?">
                <?= Csrf::field() ?>
                <?= $this->view('account/reauth.php') ?>
                <button class="kpm-btn kpm-btn-danger" type="submit">Reset two-factor</button>
            </form>
        </details>
    </section>
</div>

<section class="kpm-card kpm-mt">
    <h2 class="kpm-card-title">Recent activity</h2>
    <div class="kpm-table-wrap">
        <table class="kpm-table">
            <thead><tr><th>When</th><th>Event</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
<?php foreach ($activity as $event) : ?>
                <tr>
                    <td class="kpm-nowrap"><?= e(Format::datetime($event->created_at)) ?></td>
                    <td class="kpm-mono"><?= e($event->event) ?></td>
                    <td class="kpm-mono"><?= e($event->ip) ?></td>
                    <td class="kpm-muted kpm-truncate" title="<?= e($event->user_agent) ?>"><?= e($event->user_agent) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
