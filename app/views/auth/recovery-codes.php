<?php

/**
 * KP Memos Recovery Codes
 *
 * Shown exactly once, right after they are issued.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 *
 * @var list<string> $codes
 */

declare(strict_types=1);
?>
<div class="kpm-narrow">
    <div class="kpm-page-head">
        <div>
            <span class="kpm-eyebrow">Save these now</span>
            <h1>Recovery codes</h1>
            <p>Each code signs you in once if you lose your authenticator. They will not be shown again.</p>
        </div>
    </div>
    <div class="kpm-card">
        <ol class="kpm-codes kpm-mono" id="kpm-codes">
<?php foreach ($codes as $code) : ?>
            <li><?= e($code) ?></li>
<?php endforeach; ?>
        </ol>
        <div class="kpm-row">
            <button class="kpm-btn kpm-btn-secondary" type="button" data-copy="#kpm-codes">Copy</button>
            <button class="kpm-btn kpm-btn-secondary" type="button" data-download="#kpm-codes"
                data-filename="kp-memos-recovery-codes.txt">Download</button>
            <span class="kpm-grow"></span>
            <a class="kpm-btn kpm-btn-primary" href="/notes">I saved them</a>
        </div>
    </div>
</div>
