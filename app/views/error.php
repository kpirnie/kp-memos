<?php

/**
 * KP Memos Error Page
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var int    $code    The status code
 * @var string $message The public message
 */

declare(strict_types=1);
?>
<section class="kpm-error">
    <div class="kpm-error-code"><?= e($code) ?></div>
    <p><?= e($message) ?></p>
    <a class="kpm-btn kpm-btn-secondary" href="/">Back to safety</a>
</section>
