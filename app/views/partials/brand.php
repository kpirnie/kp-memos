<?php

/**
 * KP Memos Auth Brand Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

use KPM\Core\View;
?>
<div class="kpm-auth-brand">
    <img src="<?php echo e(View::asset('assets/img/logo.svg')) ?>" alt="" width="72" height="72">
    <span class="kpm-brand-word"><span class="kpm-brand-kp">KP</span><span class="kpm-brand-memos">MEMOS</span></span>
</div>