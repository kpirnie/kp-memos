<?php

/**
 * KP Memos Re-authentication Fields Partial
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);
?>
<div class="kpm-grid-2">
    <div class="kpm-form-group">
        <label class="kpm-label">Current password
            <input class="kpm-input" name="current_password" type="password" autocomplete="current-password"
                maxlength="1024" required>
        </label>
    </div>
    <div class="kpm-form-group">
        <label class="kpm-label">Authenticator code
            <input class="kpm-input" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                maxlength="8" required>
        </label>
    </div>
</div>
