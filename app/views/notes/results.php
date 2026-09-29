<?php

/**
 * KP Memos Notes Results Partial
 *
 * Rendered into the page on load and returned as json on refresh.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object             $filters
 * @var list<object>       $pinned
 * @var \KPT\Paginator     $pager
 * @var array<int, object> $categoryMap
 * @var array<int, object> $tagMap
 * @var bool               $sortable
 * @var int                $count
 */

declare(strict_types=1);

use KPM\Core\NoteQuery;
?>
<p class="kpm-results-count kpm-mono"><?php echo e($count) ?> note<?php echo $count === 1 ? '' : 's' ?><?php echo NoteQuery::isFiltered($filters) ? ' match' : '' ?></p>

<?php if ($pinned !== []) : ?>
    <section class="kpm-note-section">
        <h2 class="kpm-section-title">Pinned<?php if ($sortable && count($pinned) > 1) : ?> <span class="kpm-help">drag to reorder</span><?php endif; ?></h2>
        <div class="kpm-note-grid" data-pinned-grid<?php echo $sortable ? ' data-sortable' : '' ?>>
            <?php foreach ($pinned as $note) : ?>
                <?php echo $this->view('notes/card.php', ['note' => $note, 'categoryMap' => $categoryMap, 'tagMap' => $tagMap, 'draggable' => $sortable]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="kpm-note-section">
    <?php if ($pinned !== []) : ?>
        <h2 class="kpm-section-title">Notes</h2>
    <?php endif; ?>
    <?php if ($pager->count() === 0 && $pinned === []) : ?>
        <div class="kpm-empty">
            <?php if (NoteQuery::isFiltered($filters)) : ?>
                <p>No notes match these filters.</p>
                <a class="kpm-btn kpm-btn-secondary" href="/notes" data-reset>Clear filters</a>
            <?php else : ?>
                <p>No notes yet.</p>
                <a class="kpm-btn kpm-btn-primary" href="/notes/new">Write your first note</a>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="kpm-note-grid">
            <?php foreach ($pager->items() as $note) : ?>
                <?php echo $this->view('notes/card.php', ['note' => $note, 'categoryMap' => $categoryMap, 'tagMap' => $tagMap, 'draggable' => false]) ?>
            <?php endforeach; ?>
        </div>
        <?php echo $this->view('partials/pager.php', ['pager' => $pager]) ?>
    <?php endif; ?>
</section>