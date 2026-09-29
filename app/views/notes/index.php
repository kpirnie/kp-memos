<?php

/**
 * KP Memos Notes
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object       $filters
 * @var list<object> $categories Flat depth-first category nodes
 * @var list<object> $tags
 */

declare(strict_types=1);

// hold the state
$advanced = $filters->category > 0 || $filters->tags !== [] || $filters->visibility !== 'all'
    || $filters->from !== '' || $filters->to !== '';
?>
<div class="kpm-page-head">
    <div>
        <h1>Notes</h1>
    </div>
    <a class="kpm-btn kpm-btn-primary" href="/notes/new">New note</a>
</div>

<form class="kpm-filters kpm-card kpm-card-sm" id="kpm-filters" method="get" action="/notes" role="search">
    <div class="kpm-filters-bar">
        <div class="kpm-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
            <input class="kpm-input" type="search" name="q" value="<?= e($filters->q) ?>" placeholder="Search titles and note text" maxlength="200" aria-label="Search" autocomplete="off">
        </div>
        <select class="kpm-select kpm-select-auto" name="sort" aria-label="Sort">
            <option value="relevance"<?= $filters->sort === 'relevance' ? ' selected' : '' ?>>Best match</option>
            <option value="updated"<?= $filters->sort === 'updated' ? ' selected' : '' ?>>Recently updated</option>
            <option value="oldest"<?= $filters->sort === 'oldest' ? ' selected' : '' ?>>Least recently updated</option>
            <option value="created"<?= $filters->sort === 'created' ? ' selected' : '' ?>>Newest created</option>
            <option value="title"<?= $filters->sort === 'title' ? ' selected' : '' ?>>Title A–Z</option>
        </select>
        <button class="kpm-btn kpm-btn-secondary kpm-btn-sm" type="button" data-toggle-filters aria-expanded="<?= $advanced ? 'true' : 'false' ?>" aria-controls="kpm-filter-panel">Filters</button>
        <noscript><button class="kpm-btn kpm-btn-primary kpm-btn-sm" type="submit">Apply</button></noscript>
    </div>

    <div class="kpm-filter-panel" id="kpm-filter-panel"<?= $advanced ? '' : ' hidden' ?>>
        <div class="kpm-form-group">
            <label class="kpm-label" for="f-category">Category</label>
            <select class="kpm-select" id="f-category" name="category">
                <option value="0">All categories</option>
                <?= $this->view('partials/category-options.php', ['categories' => $categories, 'selected' => [$filters->category]]) ?>
            </select>
            <p class="kpm-help">Includes subcategories.</p>
        </div>
        <div class="kpm-form-group">
            <span class="kpm-label">Visibility</span>
            <div class="kpm-segmented" role="radiogroup" aria-label="Visibility">
<?php foreach (['all' => 'All', 'private' => 'Private', 'public' => 'Public'] as $value => $label) : ?>
                <label><input type="radio" name="visibility" value="<?= e($value) ?>"<?= $filters->visibility === $value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
<?php endforeach; ?>
            </div>
        </div>
        <div class="kpm-form-group">
            <label class="kpm-label" for="f-date-field">Date</label>
            <div class="kpm-row kpm-date-row">
                <select class="kpm-select kpm-select-auto" id="f-date-field" name="date_field" aria-label="Date field">
                    <option value="updated"<?= $filters->date_field === 'updated' ? ' selected' : '' ?>>Updated</option>
                    <option value="created"<?= $filters->date_field === 'created' ? ' selected' : '' ?>>Created</option>
                </select>
                <input class="kpm-input" type="date" name="from" value="<?= e($filters->from) ?>" aria-label="From">
                <span class="kpm-muted">to</span>
                <input class="kpm-input" type="date" name="to" value="<?= e($filters->to) ?>" aria-label="To">
            </div>
        </div>
        <div class="kpm-form-group kpm-filter-tags">
            <span class="kpm-label">Tags <span class="kpm-help">(notes must have every selected tag)</span></span>
<?php if ($tags === []) : ?>
            <p class="kpm-help">No tags yet.</p>
<?php else : ?>
            <div class="kpm-chips">
<?php foreach ($tags as $tag) : ?>
                <label class="kpm-chip-toggle"><input type="checkbox" name="tag" value="<?= e($tag->id) ?>"<?= in_array((int) $tag->id, $filters->tags, true) ? ' checked' : '' ?>><span>#<?= e($tag->name) ?></span></label>
<?php endforeach; ?>
            </div>
<?php endif; ?>
            <input type="hidden" name="tags" value="<?= e(implode(',', $filters->tags)) ?>">
        </div>
        <div class="kpm-filter-actions">
            <a class="kpm-btn kpm-btn-ghost kpm-btn-sm" href="/notes" data-reset>Reset filters</a>
        </div>
    </div>
</form>

<div class="kpm-results" id="kpm-results" aria-live="polite">
    <?= $this->view('notes/results.php', [
        'filters' => $filters,
        'pinned' => $pinned,
        'pager' => $pager,
        'categoryMap' => $categoryMap,
        'tagMap' => $tagMap,
        'sortable' => $sortable,
        'count' => $count,
    ]) ?>
</div>
