(() => {
    'use strict';
    const openers = new WeakMap();
    const show = (dialog, opener) => {
        if (!dialog || typeof dialog.showModal !== 'function') return;
        openers.set(dialog, opener || document.activeElement);
        dialog.showModal();
        const confirmDowngrade = dialog.querySelector('[name="downgrade_confirmed"]');
        if (confirmDowngrade) confirmDowngrade.disabled = false;
    };
    document.querySelectorAll('[data-open-dialog]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.openDialog === 'pm-downgrade-dialog' && !button.form.reportValidity()) return;
            show(document.getElementById(button.dataset.openDialog), button);
        });
    });
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('close', () => {
            const confirmDowngrade = dialog.querySelector('[name="downgrade_confirmed"]');
            if (confirmDowngrade) confirmDowngrade.disabled = true;
            if (dialog.id === 'pm-enable-dialog') {
                window.location.assign('/dashboard/packages');
                return;
            }
            openers.get(dialog)?.focus();
        });
        if (dialog.hasAttribute('data-auto-open')) {
            show(dialog);
            const fallback = document.getElementById('pm-enable-fallback');
            if (dialog.open && fallback) fallback.hidden = true;
        }
    });
    document.querySelectorAll('.pm-market-gallery').forEach(gallery => {
        const dialog = gallery.querySelector('.pm-gallery-viewer');
        if (!dialog || typeof dialog.showModal !== 'function') return;
        const links = Array.from(gallery.querySelectorAll('[data-gallery-image]'));
        const image = dialog.querySelector('img');
        const position = dialog.querySelector('[data-gallery-position]');
        let current = 0;
        const display = index => {
            current = (index + links.length) % links.length;
            image.src = links[current].href;
            image.alt = links[current].querySelector('img').alt;
            position.textContent = links.length > 1 ? (current + 1) + ' / ' + links.length : '';
        };
        links.forEach((link, index) => link.addEventListener('click', event => {
            if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            display(index);
            show(dialog, link);
        }));
        dialog.querySelectorAll('[data-gallery-step]').forEach(button => {
            button.addEventListener('click', () => display(current + Number(button.dataset.galleryStep)));
        });
        dialog.addEventListener('keydown', event => {
            if (links.length < 2 || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            display(current + (event.key === 'ArrowRight' ? 1 : -1));
        });
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
    document.querySelectorAll('video[data-market-preview], video[data-hover-preview]').forEach(video => {
        video.muted = true;
        video.addEventListener('volumechange', () => { if (!video.muted) video.muted = true; });
    });
    const previews = document.querySelectorAll('video[data-market-preview]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const cardPreviewUrls = new Map();
    document.querySelectorAll('video[data-hover-preview]').forEach(video => {
        const card = video.closest('.pm-market-card');
        let timer;
        let hover = 0;
        const stop = () => {
            hover++;
            window.clearTimeout(timer);
            video.pause();
            video.classList.remove('is-playing');
        };
        video.addEventListener('playing', () => {
            if (!video.paused) video.classList.add('is-playing');
        });
        video.addEventListener('error', stop);
        card.addEventListener('pointerenter', event => {
            if (event.pointerType === 'touch' || reducedMotion.matches || document.hidden) return;
            const request = ++hover;
            timer = window.setTimeout(async () => {
                try {
                    const id = video.dataset.hoverPreview;
                    if (!cardPreviewUrls.has(id)) {
                        const url = video.dataset.previewUrl;
                        cardPreviewUrls.set(id, url ? Promise.resolve(url) :
                            fetch('/dashboard/packages/browse/preview/' + encodeURIComponent(id))
                                .then(response => {
                                    if (!response.ok) throw new Error('Preview unavailable');
                                    return response.json();
                                })
                                .then(payload => payload.data?.video ?? null)
                                .catch(() => { cardPreviewUrls.delete(id); return null; }));
                    }
                    const url = await cardPreviewUrls.get(id);
                    if (!url || request !== hover || reducedMotion.matches || document.hidden) return;
                    if (!video.getAttribute('src')) video.src = url;
                    await video.play();
                } catch {
                    if (request === hover) stop();
                }
            }, 1000);
        });
        card.addEventListener('pointerleave', stop);
        card.addEventListener('pointercancel', stop);
        reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) stop(); });
        document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    });
    const visible = new Set();
    let observer;
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(entries => {
            entries.forEach(({ target, isIntersecting, intersectionRatio }) => {
                if (isIntersecting && intersectionRatio >= 0.5) {
                    visible.add(target);
                    if (!reducedMotion.matches && !document.hidden) target.play().catch(() => {});
                } else {
                    visible.delete(target);
                    target.pause();
                }
            });
        }, { threshold: 0.5 });
    }
    previews.forEach(video => {
        observer?.observe(video);
        video.addEventListener('error', () => {
            observer?.unobserve(video);
            visible.delete(video);
            video.hidden = true;
            video.nextElementSibling.hidden = false;
        }, { once: true });
    });
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) visible.forEach(video => video.pause());
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) visible.forEach(video => video.pause());
    });
    document.querySelectorAll('.pm-page form[method="post"]').forEach(form => {
        form.addEventListener('submit', () => {
            // Delay disabling until the browser has constructed the request data.
            window.setTimeout(() => {
                form.setAttribute('aria-busy', 'true');
                const labels = {
                    '/dashboard/packages/install/review': 'Reading manifest…',
                    '/dashboard/packages/install/dependencies': 'Preparing dependencies…',
                    '/dashboard/packages/install': 'Installing packages…',
                    '/dashboard/packages/browse/install': 'Applying release…'
                };
                form.querySelectorAll('button[type="submit"]').forEach(button => {
                    button.disabled = true;
                    if (labels[form.getAttribute('action')]) button.textContent = labels[form.getAttribute('action')];
                });
            }, 0);
        });
    });
})();
