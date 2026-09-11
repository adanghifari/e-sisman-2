@props([
    'title' => 'Loading...',
    'description' => 'Mohon tunggu, permintaan sedang diproses.',
    'timeoutMessage' => 'Request dibatalkan karena melebihi batas waktu 1 menit.',
])

<div
    data-loading-overlay
    data-loading-overlay-timeout-message="{{ $timeoutMessage }}"
    class="pointer-events-none fixed inset-0 z-[70] grid place-items-center bg-slate-950/0 px-4 py-6 opacity-0 backdrop-blur-none transition duration-200 ease-out"
    aria-hidden="true"
>
    <div
        role="status"
        aria-live="polite"
        data-loading-overlay-panel
        class="w-full max-w-sm scale-95 rounded-lg border border-white/70 bg-white/95 px-6 py-6 text-center opacity-0 shadow-2xl transition duration-200 ease-out"
    >
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-sky-50">
            <span class="block size-9 animate-spin rounded-full border-4 border-sky-100 border-t-sky-600"></span>
        </div>

        <p class="mt-5 text-lg font-bold text-slate-900" data-loading-overlay-title>{{ $title }}</p>
        <p class="mt-2 text-sm font-medium leading-6 text-slate-500" data-loading-overlay-description>{{ $description }}</p>
        <p class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600" data-loading-overlay-error></p>
    </div>
</div>

@once
    <script>
        (() => {
            const DEFAULT_TIMEOUT_MS = 60000;

            const overlay = document.querySelector('[data-loading-overlay]');

            if (!overlay) {
                return;
            }

            const title = overlay.querySelector('[data-loading-overlay-title]');
            const description = overlay.querySelector('[data-loading-overlay-description]');
            const error = overlay.querySelector('[data-loading-overlay-error]');
            const panel = overlay.querySelector('[data-loading-overlay-panel]');
            const defaultTitle = title?.textContent || 'Loading...';
            const defaultDescription = description?.textContent || 'Mohon tunggu, permintaan sedang diproses.';
            let activeForm = null;
            let activeSubmitter = null;
            let isOpen = false;

            const setFormDisabled = (form, disabled) => {
                form.querySelectorAll('button, input, select, textarea').forEach((field) => {
                    if (disabled) {
                        field.dataset.loadingOverlayWasDisabled = String(field.disabled);
                        field.disabled = true;

                        return;
                    }

                    if (field.dataset.loadingOverlayWasDisabled === 'false') {
                        field.disabled = false;
                    }

                    delete field.dataset.loadingOverlayWasDisabled;
                });
            };

            const showOverlay = (form) => {
                activeForm = form;
                isOpen = true;
                title.textContent = form.dataset.loadingOverlayTitle || defaultTitle;
                description.textContent = form.dataset.loadingOverlayDescription || defaultDescription;
                error.textContent = '';
                error.classList.add('hidden');
                overlay.setAttribute('aria-hidden', 'false');
                overlay.classList.remove('pointer-events-none', 'bg-slate-950/0', 'opacity-0', 'backdrop-blur-none');
                overlay.classList.add('pointer-events-auto', 'bg-slate-950/35', 'opacity-100', 'backdrop-blur-sm');
                panel.classList.remove('scale-95', 'opacity-0');
                panel.classList.add('scale-100', 'opacity-100');
                setFormDisabled(form, true);
            };

            const hideOverlay = () => {
                if (activeForm) {
                    activeForm.dataset.autosaveSubmitting = 'false';
                    setFormDisabled(activeForm, false);
                }

                activeForm = null;
                activeSubmitter = null;
                isOpen = false;
                overlay.setAttribute('aria-hidden', 'true');
                overlay.classList.add('pointer-events-none', 'bg-slate-950/0', 'opacity-0', 'backdrop-blur-none');
                overlay.classList.remove('pointer-events-auto', 'bg-slate-950/35', 'opacity-100', 'backdrop-blur-sm');
                panel.classList.add('scale-95', 'opacity-0');
                panel.classList.remove('scale-100', 'opacity-100');
            };

            const showError = (message) => {
                error.textContent = message;
                error.classList.remove('hidden');
            };

            const buildFormData = (form, submitter) => {
                const formData = new FormData(form);

                if (submitter?.name && !formData.has(submitter.name)) {
                    formData.append(submitter.name, submitter.value);
                }

                return formData;
            };

            const renderResponse = async (response) => {
                const contentType = response.headers.get('content-type') || '';

                if (!contentType.includes('text/html')) {
                    window.location.assign(response.url || window.location.href);

                    return;
                }

                const html = await response.text();
                document.open();
                document.write(html);
                document.close();

                if (response.url) {
                    window.history.replaceState({}, '', response.url);
                }
            };

            document.addEventListener('click', (event) => {
                const submitter = event.target.closest('button[type="submit"], input[type="submit"]');

                if (submitter) {
                    activeSubmitter = submitter;
                }
            }, { capture: true });

            document.addEventListener('wheel', (event) => {
                if (isOpen) {
                    event.preventDefault();
                }
            }, { passive: false });

            document.addEventListener('touchmove', (event) => {
                if (isOpen) {
                    event.preventDefault();
                }
            }, { passive: false });

            document.addEventListener('submit', async (event) => {
                const form = event.target.closest('form[data-loading-overlay-form]');

                if (!form) {
                    return;
                }

                if (event.defaultPrevented) {
                    return;
                }

                const submitter = event.submitter || activeSubmitter;

                if (submitter?.dataset.loadingOverlaySkip === 'true') {
                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();

                    return;
                }

                event.preventDefault();
                const formData = buildFormData(form, submitter);
                showOverlay(form);

                const controller = new AbortController();
                const timeoutMs = Number(form.dataset.loadingOverlayTimeout || DEFAULT_TIMEOUT_MS);
                const timeout = window.setTimeout(() => controller.abort(), timeoutMs);

                try {
                    const response = await fetch(form.action, {
                        method: form.method || 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html,application/xhtml+xml,application/json',
                        },
                        signal: controller.signal,
                    });

                    window.clearTimeout(timeout);
                    await renderResponse(response);
                } catch (requestError) {
                    window.clearTimeout(timeout);
                    showError(requestError.name === 'AbortError'
                        ? overlay.dataset.loadingOverlayTimeoutMessage
                        : 'Request belum berhasil diproses. Silakan coba lagi.');

                    window.setTimeout(hideOverlay, 1800);
                }
            });
        })();
    </script>
@endonce
