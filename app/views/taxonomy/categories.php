<?php

/**
 * KP Memos Categories
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var object       $user
 * @var list<object> $categories Flat depth-first tree nodes
 */

declare(strict_types=1);

use KPM\Core\Csrf;
use KPM\Core\Taxonomy;
?>
<div class="kpm-page-head">
    <div>
        <h1>Categories</h1>
        <p>Nest categories as deep as you like. A note can live in several categories.</p>
    </div>
</div>

<details class="kpm-card kpm-card-sm kpm-collapsible"<?= $categories === [] ? ' open' : '' ?>>
    <summary>New category</summary>
    <form class="kpm-grid-2" method="post" action="/categories">
        <?= Csrf::field() ?>
        <div class="kpm-form-group">
            <label class="kpm-label" for="nc-name">Name</label>
            <input class="kpm-input" id="nc-name" name="name" maxlength="100" required>
        </div>
        <div class="kpm-form-group">
            <label class="kpm-label" for="nc-parent">Inside</label>
            <select class="kpm-select" id="nc-parent" name="parent_id">
                <option value="0">(top level)</option>
                <?= $this->view('partials/category-options.php', ['categories' => $categories]) ?>
            </select>
        </div>
        <div><button class="kpm-btn kpm-btn-primary" type="submit">Create category</button></div>
    </form>
</details>

<section class="kpm-card kpm-mt">
<?php if ($categories === []) : ?>
    <p class="kpm-muted">No categories yet.</p>
<?php else : ?>
    <ul class="kpm-tree">
<?php foreach ($categories as $node) : ?>
        <li class="kpm-tree-item" style="--kpm-depth: <?= (int) $node->depth ?>">
            <details class="kpm-tree-row">
                <summary>
                    <span class="kpm-tree-name"><?= e($node->name) ?></span>
                    <a class="kpm-badge kpm-badge-blue" href="/notes?category=<?= e($node->id) ?>" title="View notes"><?= e($node->note_count) ?> notes</a>
                    <span class="kpm-grow"></span>
                    <span class="kpm-tree-edit">Edit</span>
                </summary>
                <div class="kpm-tree-forms">
                    <form class="kpm-row" method="post" action="/categories/<?= e($node->id) ?>">
                        <?= Csrf::field() ?>
                        <input class="kpm-input kpm-input-sm" name="name" value="<?= e($node->name) ?>" maxlength="100" required aria-label="Name">
                        <select class="kpm-select kpm-input-sm" name="parent_id" aria-label="Inside">
                            <option value="0">(top level)</option>
                            <?= $this->view('partials/category-options.php', [
                                'categories' => $categories,
                                'selected' => [(int) ($node->parent_id ?? 0)],
                                'exclude' => Taxonomy::descendantIds((int) $user->id, (int) $node->id),
                            ]) ?>
                        </select>
                        <button class="kpm-btn kpm-btn-secondary kpm-btn-sm" type="submit">Save</button>
                    </form>
                    <form method="post" action="/categories/<?= e($node->id) ?>/delete"
                        data-confirm="Delete this category? Its subcategories move up a level; notes are kept.">
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
