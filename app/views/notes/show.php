<?php

/**
 * KP Memos Note View
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object       $note
 * @var list<object> $noteCategories
 * @var list<object> $noteTags
 * @var list<object> $attachments
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Format;
?>
<article class="kpm-note" data-note="<?php echo e($note->id) ?>">
    <div class="kpm-page-head">
        <div class="kpm-grow">
            <a class="kpm-back" href="/notes">&larr; Notes</a>
            <h1 class="kpm-note-title"><?php echo e($note->title) ?></h1>
            <p class="kpm-note-meta">
                Updated <?php echo e(Format::datetime($note->updated_at)) ?> &middot; Created <?php echo e(Format::datetime($note->created_at)) ?>
                <?php if ((int) $note->is_pinned === 1) : ?>
                    <span class="kpm-badge kpm-badge-orange">pinned</span>
                <?php endif; ?>
                <?php if ((int) $note->is_public === 1) : ?>
                    <span class="kpm-badge kpm-badge-green">public</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="kpm-row">
            <a class="kpm-btn kpm-btn-primary" href="/notes/<?php echo e($note->id) ?>/edit">Edit</a>
            <form method="post" action="/notes/<?php echo e($note->id) ?>/delete" data-confirm="Delete this note and its attachments permanently?">
                <?php echo Csrf::field() ?>
                <button class="kpm-btn kpm-btn-danger" type="submit">Delete</button>
            </form>
        </div>
    </div>

    <?php if ($noteCategories !== [] || $noteTags !== []) : ?>
        <div class="kpm-chips kpm-note-chips">
            <?php foreach ($noteCategories as $category) : ?>
                <a class="kpm-chip kpm-chip-cat" href="/notes?category=<?php echo e($category->id) ?>"><?php echo e($category->path) ?></a>
            <?php endforeach; ?>
            <?php foreach ($noteTags as $tag) : ?>
                <a class="kpm-chip" href="/notes?tags=<?php echo e($tag->id) ?>">#<?php echo e($tag->name) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="kpm-note-layout">
        <section class="kpm-card kpm-content">
            <?php echo $note->body_html /* purified by HTMLPurifier on save */ ?>
            <?php if (trim(strip_tags((string) $note->body_html, '<img>')) === '') : ?>
                <p class="kpm-muted">This note is empty.</p>
            <?php endif; ?>
        </section>

        <aside class="kpm-note-side">
            <?php if ($attachments !== []) : ?>
                <section class="kpm-card kpm-card-sm">
                    <h2 class="kpm-side-title">Attachments</h2>
                    <ul class="kpm-file-list">
                        <?php foreach ($attachments as $file) : ?>
                            <li>
                                <a class="kpm-file-name" href="/attachments/<?php echo e($file->id) ?>?download=1"><?php echo e($file->original_name) ?></a>
                                <span class="kpm-file-size"><?php echo e(Format::bytes($file->size_bytes)) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <?php echo $this->view('notes/share-panel.php', ['note' => $note]) ?>
        </aside>
    </div>
</article>