<?php

/**
 * KP Memos Pagination Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var \KPT\Paginator $pager
 */

declare(strict_types=1);
?>
<?php if ($pager->hasPages()) : ?>
    <nav class="kpm-pager" aria-label="Pagination">
        <?php if ($pager->hasPreviousPage()) : ?>
            <a class="kpm-pager-link" href="<?php echo e($pager->previousPageUrl()) ?>" aria-label="Previous page">&lsaquo;</a>
        <?php endif; ?>
        <?php foreach ($pager->links() as $link) : ?>
            <?php if ($link['gap']) : ?>
                <span class="kpm-pager-gap">&hellip;</span>
            <?php elseif ($link['active']) : ?>
                <span class="kpm-pager-link kpm-active" aria-current="page"><?php echo e($link['page']) ?></span>
            <?php else : ?>
                <a class="kpm-pager-link" href="<?php echo e($link['url']) ?>"><?php echo e($link['page']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($pager->hasNextPage()) : ?>
            <a class="kpm-pager-link" href="<?php echo e($pager->nextPageUrl()) ?>" aria-label="Next page">&rsaquo;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>