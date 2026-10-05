/**
 * KP Memos Code Highlighting
 *
 * Code samples are stored as a bare pre with a language class; wrap their
 * text in a code element so prism finds them when it highlights the page.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

(() => {
    'use strict';

    // wrap each bare code sample before prism's own highlight pass on DOMContentLoaded
    document.querySelectorAll('.kpm-content pre[class*="language-"]').forEach((pre) => {
        if (pre.querySelector('code')) {
            return;
        }
        const code = document.createElement('code');
        code.append(...pre.childNodes);
        pre.appendChild(code);
    });
})();