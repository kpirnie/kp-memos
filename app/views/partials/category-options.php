<?php

/**
 * KP Memos Category Options Partial
 *
 * Renders indented <option> elements for a category tree.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var list<object> $categories Flat depth-first tree nodes
 * @var list<int>    $selected   Selected ids
 * @var list<int>    $exclude    Ids to leave out
 */

declare(strict_types=1);

// hold the state
$selected = $selected ?? [];
$exclude = $exclude ?? [];
?>
<?php foreach ($categories as $node) : ?>
<?php if (! in_array((int) $node->id, $exclude, true)) : ?>
<option value="<?= e($node->id) ?>"<?= in_array((int) $node->id, $selected, true) ? ' selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $node->depth) ?><?= e($node->name) ?></option>
<?php endif; ?>
<?php endforeach; ?>
