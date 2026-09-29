<?php

/**
 * KP Memos Tags
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var list<object> $tags
 */

declare(strict_types=1);

use KPM\Core\Csrf;
?>
<div class="kpm-page-head">
    <div>
        <h1>Tags</h1>
        <p>Tags are created as you type them on a note. Rename or remove them here.</p>
    </div>
    <form class="kpm-row" method="post" action="/tags">
        <?= Csrf::field() ?>
        <input class="kpm-input kpm-input-sm" name="name" maxlength="64" placeholder="New tag" required aria-label="New tag">
        <button class="kpm-btn kpm-btn-primary kpm-btn-sm" type="submit">Add tag</button>
    </form>
</div>

<section class="kpm-card">
<?php if ($tags === []) : ?>
    <p class="kpm-muted">No tags yet.</p>
<?php else : ?>
    <ul class="kpm-tag-list">
<?php foreach ($tags as $tag) : ?>
        <li>
            <details class="kpm-tree-row">
                <summary>
                    <span class="kpm-chip">#<?= e($tag->name) ?></span>
                    <a class="kpm-badge kpm-badge-blue" href="/notes?tags=<?= e($tag->id) ?>" title="View notes"><?= e($tag->note_count) ?> notes</a>
                    <span class="kpm-grow"></span>
                    <span class="kpm-tree-edit">Edit</span>
                </summary>
                <div class="kpm-tree-forms">
                    <form class="kpm-row" method="post" action="/tags/<?= e($tag->id) ?>">
                        <?= Csrf::field() ?>
                        <input class="kpm-input kpm-input-sm" name="name" value="<?= e($tag->name) ?>" maxlength="64" required aria-label="Name">
                        <button class="kpm-btn kpm-btn-secondary kpm-btn-sm" type="submit">Rename</button>
                    </form>
                    <form method="post" action="/tags/<?= e($tag->id) ?>/delete" data-confirm="Delete this tag? Notes are kept.">
                        <?= Csrf::field() ?>
                        <button class="kpm-btn kpm-btn-danger kpm-btn-sm" type="submit">Delete</button>
                    </form>
                </div>
            </details>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
