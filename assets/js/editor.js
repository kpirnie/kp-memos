/**
 * KP Memos Note Editor
 *
 * Boots HugeRTE from the composer package (nothing loads from a cdn),
 * saves the note over fetch, then uploads queued files one at a time with
 * progress, so each upload is bounded by php's per-file limit rather than
 * the whole request.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

(() => {
    'use strict';

    // hold the form
    const form = document.getElementById('kpm-editor');
    if (!form || typeof hugerte === 'undefined') {
        return;
    }
    const maxUpload = parseInt(form.dataset.maxUpload || '0', 10);
    const status = form.querySelector('[data-status]');
    const saveButton = form.querySelector('[data-save]');
    const csrf = (window.KPM && window.KPM.csrf) || '';

    // the editor frame can't see the app's css variables, so its theme is spelled out
    const contentStyle = [
        '@font-face{font-family:"Sora";font-weight:100 800;src:url("/assets/fonts/sora-latin-wght-normal.woff2") format("woff2")}',
        '@font-face{font-family:"JetBrains Mono";font-weight:100 800;src:url("/assets/fonts/jetbrains-mono-latin-wght-normal.woff2") format("woff2")}',
        'body{background:#0c1530;color:#dde8f5;font-family:"Sora",sans-serif;line-height:1.75;margin:20px 24px}',
        'a{color:#00d4ff}',
        'pre,code,kbd,samp{font-family:"JetBrains Mono",monospace}',
    ].join('');

    // boot the editor; plugins, skins, and icons load from the composer package
    hugerte.init({
        target: document.getElementById('kpm-body'),
        base_url: '/vendor/hugerte/hugerte',
        suffix: '.min',
        skin: 'oxide-dark',
        content_css: 'dark',
        content_style: contentStyle,
        min_height: 560,
        autoresize_bottom_margin: 24,
        branding: false,
        promotion: false,
        browser_spellcheck: true,
        entity_encoding: 'raw',
        convert_urls: false,
        menubar: 'file edit view insert format tools table help',
        plugins: [
            'accordion', 'advlist', 'autolink', 'autoresize', 'charmap', 'code', 'codesample',
            'directionality', 'emoticons', 'fullscreen', 'help', 'image', 'insertdatetime', 'link',
            'lists', 'media', 'nonbreaking', 'pagebreak', 'preview', 'quickbars', 'searchreplace',
            'table', 'visualblocks', 'visualchars', 'wordcount',
        ],
        toolbar: 'undo redo | cut copy paste pastetext selectall | blocks fontfamily fontsize lineheight'
            + ' | bold italic underline strikethrough subscript superscript | forecolor backcolor removeformat'
            + ' | alignleft aligncenter alignright alignjustify | outdent indent | bullist numlist | ltr rtl'
            + ' | blockquote hr link unlink image media table codesample accordion'
            + ' | charmap emoticons pagebreak nonbreaking insertdatetime'
            + ' | searchreplace visualaid visualblocks visualchars'
            + ' | newdocument preview print fullscreen wordcount code help',
        toolbar_mode: 'wrap',
        toolbar_sticky: true,
        toolbar_sticky_offset: 72,
        image_advtab: true,
        automatic_uploads: false,
        paste_data_images: true,
        file_picker_types: 'image',
        file_picker_callback: (callback) => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.addEventListener('change', () => {
                const file = input.files[0];
                if (!file) {
                    return;
                }
                const reader = new FileReader();
                reader.addEventListener('load', () => callback(reader.result, { alt: file.name }));
                reader.readAsDataURL(file);
            });
            input.click();
        },
        media_live_embeds: true,
        setup: (ed) => {
            ed.on('dirty', () => { dirty = true; });
        },
    });

    // warn before leaving with unsaved changes
    let dirty = false;

    form.addEventListener('input', () => { dirty = true; });
    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---------------------------------------------------------------------
    // file queue
    // ---------------------------------------------------------------------
    const fileInput = form.querySelector('[data-file-input]');
    const dropzone = form.querySelector('[data-dropzone]');
    const queueList = form.querySelector('[data-queue]');
    const queue = [];

    /**
     * Format a byte count
     *
     * @param {number} bytes
     * @return {string}
     */
    const formatBytes = (bytes) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let n = bytes;
        while (n >= 1024 && i < units.length - 1) {
            n /= 1024;
            i++;
        }
        return (i === 0 ? n : n.toFixed(1)) + ' ' + units[i];
    };

    /**
     * Add files to the upload queue
     *
     * @param {FileList|File[]} files
     */
    const enqueue = (files) => {
        Array.from(files).forEach((file) => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            name.className = 'kpm-file-name';
            name.textContent = file.name;
            const size = document.createElement('span');
            size.className = 'kpm-file-size';
            size.textContent = formatBytes(file.size);
            const bar = document.createElement('progress');
            bar.max = 100;
            bar.value = 0;
            bar.hidden = true;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'kpm-icon-btn';
            remove.setAttribute('aria-label', 'Remove');
            remove.textContent = '×';
            item.append(name, size, remove, bar);

            // too big for the server; flag it and skip
            const entry = { file, item, bar, done: false };
            if (maxUpload > 0 && file.size > maxUpload) {
                item.classList.add('kpm-file-error');
                size.textContent = formatBytes(file.size) + ' — too large';
                entry.done = true;
            }
            remove.addEventListener('click', () => {
                queue.splice(queue.indexOf(entry), 1);
                item.remove();
            });
            queue.push(entry);
            queueList.appendChild(item);
            dirty = true;
        });
    };

    // choose or drop files; the native input is cleared so files only travel through the queue
    fileInput.addEventListener('change', () => {
        enqueue(fileInput.files);
        fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (e) => {
        e.preventDefault();
        dropzone.classList.add('kpm-drag');
    }));
    ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (e) => {
        e.preventDefault();
        dropzone.classList.remove('kpm-drag');
    }));
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files.length) {
            enqueue(e.dataTransfer.files);
        }
    });

    /**
     * Upload one queued file with progress
     *
     * @param {number} noteId
     * @param {object} entry
     * @return {Promise<void>}
     */
    const upload = (noteId, entry) => new Promise((resolve) => {
        const data = new FormData();
        data.append('files[]', entry.file, entry.file.name);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/notes/' + noteId + '/attachments');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', csrf);
        entry.bar.hidden = false;
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                entry.bar.value = Math.round((e.loaded / e.total) * 100);
            }
        });
        xhr.addEventListener('loadend', () => {
            let ok = xhr.status >= 200 && xhr.status < 300;
            try {
                const res = JSON.parse(xhr.responseText || '{}');
                ok = ok && res.success !== false;
            } catch (err) {
                ok = false;
            }
            entry.done = true;
            entry.item.classList.add(ok ? 'kpm-file-done' : 'kpm-file-error');
            resolve();
        });
        xhr.send(data);
    });

    // ---------------------------------------------------------------------
    // existing attachments
    // ---------------------------------------------------------------------
    form.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-delete-attachment]');
        if (!button || !window.confirm('Delete this attachment?')) {
            return;
        }
        try {
            await window.KPM.api('/attachments/' + button.dataset.deleteAttachment + '/delete', { method: 'POST' });
            button.closest('li').remove();
        } catch (err) {
            window.alert(err.message);
        }
    });

    // ---------------------------------------------------------------------
    // save
    // ---------------------------------------------------------------------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        saveButton.disabled = true;
        status.textContent = 'Saving…';

        // send the note without files
        hugerte.triggerSave();
        const data = new FormData(form);
        data.delete('files[]');
        let result;
        try {
            result = await window.KPM.api(form.getAttribute('action'), { method: 'POST', body: data });
        } catch (err) {
            status.textContent = err.message;
            saveButton.disabled = false;
            return;
        }

        // then each queued file
        const pending = queue.filter((entry) => !entry.done);
        for (let i = 0; i < pending.length; i++) {
            status.textContent = 'Uploading ' + (i + 1) + ' of ' + pending.length + '…';
            await upload(result.id, pending[i]);
        }

        // stay if anything failed so it can be retried from the note
        const failed = queue.filter((entry) => entry.item.classList.contains('kpm-file-error')).length;
        dirty = false;
        if (failed) {
            status.textContent = failed + ' file(s) could not be uploaded. The note was saved.';
            form.setAttribute('action', '/notes/' + result.id);
            form.dataset.noteId = result.id;
            saveButton.disabled = false;
            return;
        }
        window.location.href = result.url;
    });
})();
