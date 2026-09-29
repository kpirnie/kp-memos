<?php

/**
 * KP Memos Flash Messages Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var list<array{type: string, message: string}>|null $flashes
 * @var string|null $error
 */

declare(strict_types=1);

// only known alert types map to classes
$types = ['success' => 'success', 'error' => 'error', 'info' => 'info', 'warning' => 'warning'];
?>
<?php foreach ($flashes ?? [] as $flash) : ?>
    <div class="kpm-alert kpm-alert-<?php echo e($types[$flash['type']] ?? 'info') ?>" role="status"><?php echo e($flash['message']) ?></div>
<?php endforeach; ?>
<?php if (! empty($error)) : ?>
    <div class="kpm-alert kpm-alert-error" role="alert"><?php echo e($error) ?></div>
<?php endif; ?>