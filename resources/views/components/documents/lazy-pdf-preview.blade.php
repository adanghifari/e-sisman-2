@props([
    'src',
    'heightClass' => 'min-h-[760px] xl:h-[82vh]',
])

<div class="bg-white" data-lazy-pdf-preview>
    <div class="flex items-center justify-center bg-slate-50 px-4 py-4" data-lazy-pdf-placeholder>
        <button
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-100"
            data-lazy-pdf-load
            data-src="{{ $src }}"
        >
            <x-flux.icon name="eye" class="size-5" data-lazy-pdf-idle-icon />
            <span
                class="hidden size-5 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600"
                aria-hidden="true"
                data-lazy-pdf-loading-icon
            ></span>
            <span data-lazy-pdf-label>Lihat Dokumen</span>
        </button>
    </div>

    <div class="hidden items-center justify-end gap-2 border-b border-slate-200 bg-white px-4 py-3" data-lazy-pdf-controls>
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100"
            title="Zoom out"
            aria-label="Zoom out"
            data-lazy-pdf-zoom-out
        >
            <x-flux.icon name="minus" class="size-4" />
        </button>
        <span class="min-w-14 text-center text-xs font-bold text-slate-600" data-lazy-pdf-zoom-label>100%</span>
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100"
            title="Zoom in"
            aria-label="Zoom in"
            data-lazy-pdf-zoom-in
        >
            <x-flux.icon name="plus" class="size-4" />
        </button>
    </div>

    <iframe
        title="Preview dokumen PDF"
        class="hidden {{ $heightClass }} w-full border-0 bg-white motion-safe:animate-[lazy-pdf-expand_180ms_ease-out]"
        data-lazy-pdf-frame
    ></iframe>
</div>

@once
    <style>
        @keyframes lazy-pdf-expand {
            from {
                max-height: 0;
                opacity: 0;
                transform: translateY(-0.25rem);
            }

            to {
                max-height: 90vh;
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
        (() => {
            if (window.lazyPdfPreviewReady) {
                return;
            }

            window.lazyPdfPreviewReady = true;

            const minimumZoom = 50;
            const maximumZoom = 200;
            const zoomStep = 10;

            const withViewerOptions = (source, zoom = null) => {
                const [base, hash = ''] = source.split('#');
                const params = new URLSearchParams(hash);

                params.set('toolbar', '0');

                if (zoom !== null) {
                    params.delete('view');
                    params.set('zoom', String(zoom));
                }

                return `${base}#${params.toString()}`;
            };

            const applyZoom = (root, nextZoom) => {
                const frame = root?.querySelector('[data-lazy-pdf-frame]');
                const label = root?.querySelector('[data-lazy-pdf-zoom-label]');
                const source = root?.dataset.lazyPdfSource;

                if (!frame || !source) {
                    return;
                }

                const zoom = Math.min(maximumZoom, Math.max(minimumZoom, nextZoom));

                root.dataset.lazyPdfZoom = String(zoom);
                frame.src = withViewerOptions(source, zoom);

                if (label) {
                    label.textContent = `${zoom}%`;
                }
            };

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-lazy-pdf-load]');

                const zoomButton = event.target.closest('[data-lazy-pdf-zoom-in], [data-lazy-pdf-zoom-out]');

                if (zoomButton) {
                    const root = zoomButton.closest('[data-lazy-pdf-preview]');
                    const currentZoom = Number(root?.dataset.lazyPdfZoom || 100);
                    const direction = zoomButton.matches('[data-lazy-pdf-zoom-in]') ? 1 : -1;

                    applyZoom(root, currentZoom + (zoomStep * direction));
                    return;
                }

                if (!button) {
                    return;
                }

                const root = button.closest('[data-lazy-pdf-preview]');
                const frame = root?.querySelector('[data-lazy-pdf-frame]');
                const placeholder = root?.querySelector('[data-lazy-pdf-placeholder]');
                const controls = root?.querySelector('[data-lazy-pdf-controls]');
                const idleIcon = button.querySelector('[data-lazy-pdf-idle-icon]');
                const loadingIcon = button.querySelector('[data-lazy-pdf-loading-icon]');
                const label = button.querySelector('[data-lazy-pdf-label]');
                const source = button.dataset.src;

                if (!frame || !source) {
                    return;
                }

                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-80');
                idleIcon?.classList.add('hidden');
                loadingIcon?.classList.remove('hidden');

                if (label) {
                    label.textContent = 'Memuat...';
                }

                frame.addEventListener('load', () => {
                    frame.classList.remove('hidden');
                    controls?.classList.remove('hidden');
                    controls?.classList.add('flex');
                    placeholder?.remove();
                }, { once: true });

                root.dataset.lazyPdfSource = source;
                root.dataset.lazyPdfZoom = '100';
                frame.src = withViewerOptions(source);
            });
        })();
    </script>
@endonce
