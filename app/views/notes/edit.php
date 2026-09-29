<?php

/**
 * KP Memos Note Editor
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object|null  $note
 * @var list<object> $categories         Flat depth-first category nodes
 * @var list<int>    $selectedCategories
 * @var list<string> $noteTags
 * @var list<string> $allTags
 * @var list<object> $attachments
 * @var int          $maxUpload          Per-file byte limit from php
 * @var int          $maxFiles           Files per request from php
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Format;

// hold the state
$isNew = $note === null;
$action = $isNew ? '/notes' : '/notes/' . (int) $note->id;
?>
<form class="kpm-editor" id="kpm-editor" method="post" action="<?= e($action) ?>" enctype="multipart/form-data"
    data-max-upload="<?= e($maxUpload) ?>" data-max-files="<?= e($maxFiles) ?>" data-note-id="<?= $isNew ? '' : e($note->id) ?>">
    <?= Csrf::field() ?>

    <div class="kpm-editor-head">
        <a class="kpm-back" href="<?= $isNew ? '/notes' : '/notes/' . e($note->id) ?>">&larr; <?= $isNew ? 'Notes' : 'Back to note' ?></a>
        <input class="kpm-title-input" name="title" value="<?= e($note->title ?? '') ?>" placeholder="Note title"
            maxlength="255" required aria-label="Title"<?= $isNew ? ' autofocus' : '' ?>>
    </div>

    <div class="kpm-editor-grid">
        <div class="kpm-editor-main">
            <textarea id="kpm-body" name="body" aria-label="Note body"><?= e($note->body_html ?? '') ?></textarea>
        </div>

        <aside class="kpm-editor-side">
            <section class="kpm-card kpm-card-sm">
                <h2 class="kpm-side-title">Categories</h2>
<?php if ($categories === []) : ?>
                <p class="kpm-help">No categories yet. <a href="/categories">Create some</a>.</p>
<?php else : ?>
                <div class="kpm-checktree">
<?php foreach ($categories as $node) : ?>
                    <label class="kpm-check" style="--kpm-depth: <?= (int) $node->depth ?>">
                        <input type="checkbox" name="categories[]" value="<?= e($node->id) ?>"<?= in_array((int) $node->id, $selectedCategories, true) ? ' checked' : '' ?>>
                        <?= e($node->name) ?>
                    </label>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </section>

            <section class="kpm-card kpm-card-sm">
                <h2 class="kpm-side-title">Tags</h2>
                <div class="kpm-tags-input" data-tags>
                    <input class="kpm-input" name="tags" value="<?= e(implode(', ', $noteTags)) ?>" list="kpm-tag-list"
                        placeholder="Comma separated" maxlength="4000" autocomplete="off" aria-label="Tags">
                    <datalist id="kpm-tag-list">
<?php foreach ($allTags as $tag) : ?>
                        <option value="<?= e($tag) ?>"></option>
<?php endforeach; ?>
                    </datalist>
                </div>
            </section>

            <section class="kpm-card kpm-card-sm">
                <h2 class="kpm-side-title">Attachments</h2>
                <label class="kpm-dropzone" data-dropzone>
                    <input type="file" name="files[]" multiple data-file-input>
                    <span><strong>Drop files</strong> or click to choose</span>
                    <span class="kpm-help">Up to <?= e(Format::bytes($maxUpload)) ?> each</span>
                </label>
                <ul class="kpm-file-list" data-queue></ul>
<?php if ($attachments !== []) : ?>
                <ul class="kpm-file-list">
<?php foreach ($attachments as $file) : ?>
                    <li data-attachment="<?= e($file->id) ?>">
                        <a href="/attachments/<?= e($file->id) ?>?download=1" class="kpm-file-name"><?= e($file->original_name) ?></a>
                        <span class="kpm-file-size"><?= e(Format::bytes($file->size_bytes)) ?></span>
                        <button class="kpm-icon-btn" type="button" data-delete-attachment="<?= e($file->id) ?>" aria-label="Delete attachment" title="Delete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                        </button>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>

            <div class="kpm-editor-actions">
                <button class="kpm-btn kpm-btn-primary kpm-btn-block" type="submit" data-save>Save note</button>
                <p class="kpm-help kpm-center" data-status aria-live="polite"></p>
            </div>
        </aside>
    </div>
</form>
