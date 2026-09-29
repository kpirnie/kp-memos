<?php

/**
 * KP Memos Template Helpers
 *
 * Small global helpers used by the view templates.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// make sure the function doesn't already exist
if (! function_exists('e')) {

    /**
     * Escape a value for html output
     *
     * @since 8.5
     *
     * @param  mixed $value The value to escape
     * @return string
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
