<?php

/**
 * KP Memos Note Share Panel Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object $note
 */

declare(strict_types=1);

use KPM\Core\Config;
use KPM\Core\Csrf;
use KPM\Core\Format;

// hold the state
$public = (int) $note->is_public === 1;
$expires = Format::local($note->share_expires_at);
$expired = $expires !== null && $expires->getTimestamp() <= time();
$url = $note->share_token !== null ? rtrim((string) Config::get('app.url', ''), '/') . '/s/' . $note->share_token : '';
?>
<section class="kpm-card kpm-card-sm kpm-share">
    <h2 class="kpm-side-title">Sharing</h2>
<?php if ($public && $url !== '') : ?>
    <p class="kpm-small">
<?php if ($expired) : ?>
        <span class="kpm-badge kpm-badge-red">link expired</span>
<?php else : ?>
        <span class="kpm-badge kpm-badge-green">anyone with the link can view</span>
<?php endif; ?>
<?php if ((int) $note->share_has_password === 1) : ?>
        <span class="kpm-badge kpm-badge-orange">password</span>
<?php endif; ?>
    </p>
    <div class="kpm-share-link">
        <input class="kpm-input kpm-mono" id="kpm-share-url" value="<?= e($url) ?>" readonly aria-label="Share link">
        <button class="kpm-btn kpm-btn-secondary kpm-btn-sm" type="button" data-copy="#kpm-share-url">Copy</button>
    </div>
<?php else : ?>
    <p class="kpm-help">Only you can see this note.</p>
<?php endif; ?>

    <form method="post" action="/notes/<?= e($note->id) ?>/share" autocomplete="off">
        <?= Csrf::field() ?>
        <label class="kpm-check kpm-mt">
            <input type="checkbox" name="is_public" value="1"<?= $public ? ' checked' : '' ?>>
            Public link
        </label>
        <div class="kpm-form-group kpm-mt">
            <label class="kpm-label" for="share-expires">Expires</label>
            <input class="kpm-input" id="share-expires" name="expires_at" type="datetime-local"
                value="<?= $expires !== null ? e($expires->format('Y-m-d\TH:i')) : '' ?>">
            <p class="kpm-help">Leave empty for no expiry.</p>
        </div>
        <div class="kpm-form-group">
            <label class="kpm-label" for="share-password"><?= (int) $note->share_has_password === 1 ? 'New password' : 'Password' ?></label>
            <input class="kpm-input" id="share-password" name="share_password" type="password" minlength="8" maxlength="1024"
                autocomplete="new-password" placeholder="<?= (int) $note->share_has_password === 1 ? 'Unchanged' : 'Optional' ?>">
<?php if ((int) $note->share_has_password === 1) : ?>
            <label class="kpm-check kpm-small kpm-mt"><input type="checkbox" name="remove_password" value="1"> Remove password</label>
<?php endif; ?>
        </div>
        <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit">Save sharing</button>
    </form>

<?php if ($note->share_token !== null) : ?>
    <form method="post" action="/notes/<?= e($note->id) ?>/share/regenerate" class="kpm-mt"
        data-confirm="Generate a new link? The current link stops working immediately.">
        <?= Csrf::field() ?>
        <button class="kpm-btn kpm-btn-ghost kpm-btn-sm kpm-btn-block" type="submit">Regenerate link</button>
    </form>
<?php endif; ?>
</section>
