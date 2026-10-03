/**
 * KP Memos Note View
 *
 * Behaviour for the single note page.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

(() => {
    'use strict';

    // copy buttons on code blocks
    document.querySelectorAll('.kpm-content pre').forEach((pre) => {
        const wrap = document.createElement('div');
        wrap.className = 'kpm-pre-wrap';
        pre.parentNode.insertBefore(wrap, pre);
        wrap.appendChild(pre);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'kpm-btn kpm-btn-secondary kpm-btn-sm kpm-pre-copy';
        button.textContent = 'Copy';
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(pre.textContent);
                button.textContent = 'Copied';
                setTimeout(() => { button.textContent = 'Copy'; }, 1500);
            } catch (err) {
                window.alert('Copy failed; select the text and copy it manually.');
            }
        });
        wrap.appendChild(button);
    });
})();
