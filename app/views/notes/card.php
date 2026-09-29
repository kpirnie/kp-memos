<?php

/**
 * KP Memos Note Card Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object             $note        A kpm_notes_list row
 * @var array<int, object> $categoryMap
 * @var array<int, object> $tagMap
 * @var bool               $draggable   Show the drag handle
 */

declare(strict_types=1);

use KPM\Core\Format;
use KPM\Core\Taxonomy;
use KPT\Str;

// hold the state
$pinned = (int) $note->is_pinned === 1;
$expired = $note->share_expires_at !== null && strtotime($note->share_expires_at . ' UTC') <= time();
?>
<article class="kpm-note-card<?= $pinned ? ' kpm-pinned' : '' ?>" data-id="<?= e($note->id) ?>">
    <div class="kpm-note-card-top">
<?php if ($draggable) : ?>
        <button class="kpm-drag-handle" type="button" data-drag-handle aria-label="Drag to reorder" title="Drag to reorder">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
        </button>
<?php endif; ?>
        <h3 class="kpm-note-card-title"><a href="/notes/<?= e($note->id) ?>"><?= e($note->title) ?></a></h3>
        <button class="kpm-icon-btn<?= $pinned ? ' kpm-on' : '' ?>" type="button" data-pin="<?= $pinned ? '0' : '1' ?>"
            aria-label="<?= $pinned ? 'Unpin' : 'Pin' ?>" title="<?= $pinned ? 'Unpin' : 'Pin' ?>" aria-pressed="<?= $pinned ? 'true' : 'false' ?>">
            <svg viewBox="0 0 24 24" fill="<?= $pinned ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
        </button>
    </div>
<?php if ((string) $note->excerpt !== '') : ?>
    <p class="kpm-note-card-excerpt"><?= e(Str::excerpt((string) $note->excerpt, 220)) ?></p>
<?php endif; ?>
<?php
$cats = array_filter(array_map(static fn (int $id): ?object => $categoryMap[$id] ?? null, Taxonomy::ids($note->category_ids)));
$tags = array_filter(array_map(static fn (int $id): ?object => $tagMap[$id] ?? null, Taxonomy::ids($note->tag_ids)));
?>
<?php if ($cats !== [] || $tags !== []) : ?>
    <div class="kpm-chips">
<?php foreach ($cats as $category) : ?>
        <a class="kpm-chip kpm-chip-cat" href="/notes?category=<?= e($category->id) ?>" data-filter-category="<?= e($category->id) ?>" title="<?= e($category->path) ?>"><?= e($category->name) ?></a>
<?php endforeach; ?>
<?php foreach ($tags as $tag) : ?>
        <a class="kpm-chip" href="/notes?tags=<?= e($tag->id) ?>" data-filter-tag="<?= e($tag->id) ?>">#<?= e($tag->name) ?></a>
<?php endforeach; ?>
    </div>
<?php endif; ?>
    <div class="kpm-note-card-meta">
        <span title="Updated <?= e(Format::datetime($note->updated_at)) ?>"><?= e(Format::datetime($note->updated_at, 'M j, Y')) ?></span>
<?php if ((int) $note->attachment_count > 0) : ?>
        <span class="kpm-meta-icon" title="<?= e($note->attachment_count) ?> attachment(s)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.5l-8.5 8.5a5 5 0 01-7-7L14 5.5a3.5 3.5 0 015 5L10.5 19"/></svg><?= e($note->attachment_count) ?>
        </span>
<?php endif; ?>
<?php if ((int) $note->is_public === 1) : ?>
        <span class="kpm-badge <?= $expired ? 'kpm-badge-red' : 'kpm-badge-green' ?>"><?= $expired ? 'link expired' : 'public' ?><?= (int) $note->share_has_password === 1 ? ' · locked' : '' ?></span>
<?php endif; ?>
    </div>
</article>
