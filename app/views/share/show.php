<?php

/**
 * KP Memos Shared Note
 *
 * The public view of a shared note.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object       $note
 * @var string       $token
 * @var list<object> $attachments
 */

declare(strict_types=1);

use KPM\Core\Format;
?>
<article class="kpm-shared">
    <header class="kpm-shared-head">
        <span class="kpm-eyebrow">Shared note</span>
        <h1 class="kpm-note-title"><?php echo e($note->title) ?></h1>
        <p class="kpm-note-meta">Updated <?php echo e(Format::datetime($note->updated_at)) ?>
            <?php if ($note->share_expires_at !== null) : ?>
                &middot; Link expires <?php echo e(Format::datetime($note->share_expires_at)) ?>
            <?php endif; ?>
        </p>
    </header>

    <section class="kpm-card kpm-content">
        <?php echo $note->body_html /* purified by HTMLPurifier on save */ ?>
    </section>

    <?php if ($attachments !== []) : ?>
        <section class="kpm-card kpm-card-sm kpm-mt">
            <h2 class="kpm-side-title">Attachments</h2>
            <ul class="kpm-file-list">
                <?php foreach ($attachments as $file) : ?>
                    <li>
                        <a class="kpm-file-name" href="/s/<?php echo e($token) ?>/files/<?php echo e($file->id) ?>?download=1" rel="nofollow"><?php echo e($file->original_name) ?></a>
                        <span class="kpm-file-size"><?php echo e(Format::bytes($file->size_bytes)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>