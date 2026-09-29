<?php

/**
 * KP Memos Admin: Users
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var string          $search
 * @var \KPT\Paginator  $pager
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Format;
?>
<div class="kpm-page-head">
    <div>
        <h1>Users</h1>
        <p><?= e($pager->total()) ?> account<?= $pager->total() === 1 ? '' : 's' ?>. Admins manage accounts; nobody can read another user's notes.</p>
    </div>
    <form class="kpm-row" method="get" action="/admin/users" role="search">
        <input class="kpm-input kpm-input-sm" type="search" name="q" value="<?= e($search) ?>" placeholder="Search users" maxlength="128">
        <button class="kpm-btn kpm-btn-secondary kpm-btn-sm" type="submit">Search</button>
    </form>
</div>

<details class="kpm-card kpm-card-sm kpm-collapsible"<?= $pager->total() <= 1 ? ' open' : '' ?>>
    <summary>New user</summary>
    <form method="post" action="/admin/users" autocomplete="off">
        <?= Csrf::field() ?>
        <div class="kpm-grid-2">
            <div class="kpm-form-group">
                <label class="kpm-label" for="nu-username">Username</label>
                <input class="kpm-input" id="nu-username" name="username" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,63}"
                    autocapitalize="none" spellcheck="false" required>
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="nu-display">Display name</label>
                <input class="kpm-input" id="nu-display" name="display_name" maxlength="128">
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="nu-email">Email</label>
                <input class="kpm-input" id="nu-email" name="email" type="email" maxlength="255">
            </div>
            <div class="kpm-form-group">
                <label class="kpm-label" for="nu-role">Role</label>
                <select class="kpm-select" id="nu-role" name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <p class="kpm-help">A temporary password is generated and shown once. The user sets up two-factor and a new password at first sign in.</p>
        <button class="kpm-btn kpm-btn-primary kpm-mt" type="submit">Create user</button>
    </form>
</details>

<section class="kpm-card kpm-mt">
    <div class="kpm-table-wrap">
        <table class="kpm-table">
            <thead>
                <tr><th>User</th><th>Role</th><th>Status</th><th>Two-factor</th><th>Last sign in</th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($pager->items() as $row) : ?>
                <tr>
                    <td>
                        <a href="/admin/users/<?= e($row->id) ?>"><strong><?= e($row->username) ?></strong></a>
                        <div class="kpm-muted kpm-small"><?= e($row->display_name) ?></div>
                    </td>
                    <td><span class="kpm-badge <?= $row->role === 'admin' ? 'kpm-badge-orange' : 'kpm-badge-blue' ?>"><?= e($row->role) ?></span></td>
                    <td><span class="kpm-badge <?= (int) $row->is_active === 1 ? 'kpm-badge-green' : 'kpm-badge-red' ?>"><?= (int) $row->is_active === 1 ? 'active' : 'disabled' ?></span></td>
                    <td><span class="kpm-badge <?= (int) $row->totp_enabled === 1 ? 'kpm-badge-green' : 'kpm-badge-orange' ?>"><?= (int) $row->totp_enabled === 1 ? 'on' : 'pending' ?></span></td>
                    <td class="kpm-nowrap kpm-muted"><?= e(Format::datetime($row->last_login_at)) ?></td>
                    <td class="kpm-right"><a class="kpm-btn kpm-btn-ghost kpm-btn-sm" href="/admin/users/<?= e($row->id) ?>">Manage</a></td>
                </tr>
<?php endforeach; ?>
<?php if ($pager->count() === 0) : ?>
                <tr><td colspan="6" class="kpm-muted">No users match.</td></tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= $this->view('partials/pager.php', ['pager' => $pager]) ?>
</section>
