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

    const copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>';
    const doneIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';

    // copy buttons on code blocks
    document.querySelectorAll('.kpm-content pre').forEach((pre) => {
        const wrap = document.createElement('div');
        wrap.className = 'kpm-pre-wrap';
        pre.parentNode.insertBefore(wrap, pre);

        const tools = document.createElement('div');
        tools.className = 'kpm-pre-tools';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'kpm-icon-btn kpm-pre-copy';
        button.title = 'Copy';
        button.setAttribute('aria-label', 'Copy');
        button.innerHTML = copyIcon;
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(pre.innerText);
                button.innerHTML = doneIcon;
                setTimeout(() => { button.innerHTML = copyIcon; }, 1500);
            } catch (err) {
                window.alert('Copy failed; select the text and copy it manually.');
            }
        });

        tools.appendChild(button);
        wrap.appendChild(tools);
        wrap.appendChild(pre);
    });
})();