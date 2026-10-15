(() => {
    'use strict';
    const openers = new WeakMap();
    const show = (dialog, opener) => {
        if (!dialog || typeof dialog.showModal !== 'function') return;
        openers.set(dialog, opener || document.activeElement);
        dialog.showModal();
    };
    document.querySelectorAll('[data-open-dialog]').forEach(button => {
        button.addEventListener('click', () => {
            show(document.getElementById(button.dataset.openDialog), button);
        });
    });
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('close', () => {
            if (dialog.id === 'pm-enable-dialog') {
                window.location.assign('/dashboard/packages');
                return;
            }
            openers.get(dialog)?.focus();
        });
        if (dialog.hasAttribute('data-auto-open')) show(dialog);
    });
    const input = document.getElementById('pm-archive');
    const clear = document.getElementById('pm-clear');
    const install = document.getElementById('pm-install');
    const filename = document.getElementById('pm-filename');
    const selection = document.getElementById('pm-file-selection');
    const error = document.getElementById('pm-upload-error');
    const chooseLabel = document.getElementById('pm-choose-label');
    const nextStep = document.getElementById('pm-install-next');
    const update = () => {
        const file = input.files?.[0];
        const valid = file && /\.zip$/i.test(file.name) && file.size > 0 && file.size <= 50 * 1024 * 1024;
        let message = '';
        if (file && !/\.zip$/i.test(file.name)) message = 'Choose a ZIP file (.zip).';
        else if (file && file.size === 0) message = 'This file is empty. Choose a package ZIP with content.';
        else if (file && file.size > 50 * 1024 * 1024) message = 'This file is too large. Choose a ZIP up to 50 MB.';
        input.setCustomValidity(message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        error.textContent = message;
        error.hidden = !message;
        selection.hidden = !file;
        chooseLabel.textContent = file ? 'Choose a different ZIP' : 'Choose ZIP file';
        nextStep.textContent = valid
            ? 'Ready to read the manifest and check dependencies.'
            : 'Choose a ZIP to check its manifest and dependencies. Nothing is installed yet.';
        install.disabled = !valid;
        filename.textContent = file ? `${file.name} · ${file.size < 1024 * 1024 ? `${Math.ceil(file.size / 1024)} KB` : `${(file.size / (1024 * 1024)).toFixed(1)} MB`}` : 'No file selected';
    };
    if (input) {
        input.addEventListener('change', update);
        clear.addEventListener('click', () => { input.value = ''; update(); input.focus(); });
        update();
    }
    document.querySelectorAll('[data-image-fallback]').forEach(image => {
        const fallback = () => {
            if (image.getAttribute('src') !== image.dataset.imageFallback) image.src = image.dataset.imageFallback;
        };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    });
    document.querySelectorAll('.pm-page form[method="post"]').forEach(form => {
        form.addEventListener('submit', () => {
            // Delay disabling until the browser has constructed the request data.
            window.setTimeout(() => {
                form.setAttribute('aria-busy', 'true');
                const labels = {
                    '/dashboard/packages/install/review': 'Reading manifest…',
                    '/dashboard/packages/install/dependencies': 'Preparing dependencies…',
                    '/dashboard/packages/install': 'Installing packages…'
                };
                form.querySelectorAll('button[type="submit"]').forEach(button => {
                    button.disabled = true;
                    if (labels[form.getAttribute('action')]) button.textContent = labels[form.getAttribute('action')];
                });
            }, 0);
        });
    });
})();
