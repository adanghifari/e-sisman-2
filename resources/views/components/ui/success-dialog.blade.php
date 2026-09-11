@props([
    'title' => 'Berhasil',
    'message' => null,
    'closeLabel' => 'Tutup',
    'variant' => 'success',
])

@php
    $isWarning = $variant === 'warning';
    $accent = $isWarning
        ? [
            'border' => 'border-amber-100',
            'iconWrap' => 'bg-amber-50 ring-amber-100',
            'button' => 'bg-amber-500 hover:bg-amber-600 focus:ring-amber-200',
        ]
        : [
            'border' => 'border-emerald-100',
            'iconWrap' => 'bg-emerald-50 ring-emerald-100',
            'button' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-200',
        ];
@endphp

<div
    class="fixed inset-0 z-50 grid place-items-center bg-slate-950/35 px-4 py-6 backdrop-blur-sm"
    data-success-dialog
    role="dialog"
    aria-modal="true"
    aria-labelledby="success-dialog-title"
>
    <div class="w-full max-w-md scale-95 rounded-lg border {{ $accent['border'] }} bg-white p-6 text-center opacity-0 shadow-2xl shadow-slate-900/15 transition duration-300 ease-out data-[ready=true]:scale-100 data-[ready=true]:opacity-100">
        <div class="mx-auto grid size-20 place-items-center rounded-full {{ $accent['iconWrap'] }} ring-1">
            @if ($isWarning)
                <x-flux.icon name="exclamation-triangle" class="size-11 text-amber-500" />
            @else
                <svg class="size-12" viewBox="0 0 52 52" aria-hidden="true">
                    <circle
                        class="success-dialog-circle"
                        cx="26"
                        cy="26"
                        r="23"
                        fill="none"
                        stroke="#10b981"
                        stroke-width="4"
                    />
                    <path
                        class="success-dialog-check"
                        fill="none"
                        stroke="#059669"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="5"
                        d="M15 27.5 22.5 35 38 18"
                    />
                </svg>
            @endif
        </div>

        <h2 id="success-dialog-title" class="mt-5 text-xl font-bold text-slate-950">
            {{ $title }}
        </h2>

        @if ($message)
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ $message }}
            </p>
        @endif

        <button
            type="button"
            class="mt-6 inline-flex h-11 min-w-32 items-center justify-center rounded-lg {{ $accent['button'] }} px-5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2"
            data-success-dialog-close
        >
            {{ $closeLabel }}
        </button>
    </div>
</div>

@once
    <style>
        .success-dialog-circle {
            stroke-dasharray: 145;
            stroke-dashoffset: 145;
            animation: success-dialog-draw 520ms ease-out forwards;
        }

        .success-dialog-check {
            stroke-dasharray: 35;
            stroke-dashoffset: 35;
            animation: success-dialog-draw 420ms 360ms ease-out forwards;
        }

        @keyframes success-dialog-draw {
            to {
                stroke-dashoffset: 0;
            }
        }
    </style>

    <script>
        (() => {
            const closeDialog = (dialog) => {
                const panel = dialog?.firstElementChild;

                if (!dialog || !panel) {
                    return;
                }

                panel.dataset.ready = 'false';
                dialog.classList.add('pointer-events-none');

                setTimeout(() => dialog.remove(), 180);
            };

            window.initSuccessDialogs = () => {
                document.querySelectorAll('[data-success-dialog]:not([data-success-dialog-initialized])').forEach((dialog) => {
                    dialog.dataset.successDialogInitialized = 'true';

                    const panel = dialog.firstElementChild;
                    const closeButton = dialog.querySelector('[data-success-dialog-close]');

                    requestAnimationFrame(() => {
                        panel.dataset.ready = 'true';
                        closeButton?.focus();
                    });

                    closeButton?.addEventListener('click', () => closeDialog(dialog));

                    dialog.addEventListener('click', (event) => {
                        if (event.target === dialog) {
                            closeDialog(dialog);
                        }
                    });
                });
            };

            document.addEventListener('keydown', (event) => {
                const dialog = document.querySelector('[data-success-dialog]');

                if (event.key === 'Escape' && dialog instanceof HTMLElement) {
                    closeDialog(dialog);
                }
            });

            document.addEventListener('DOMContentLoaded', window.initSuccessDialogs);
            document.addEventListener('lived', window.initSuccessDialogs);
            window.initSuccessDialogs();
        })();
    </script>
@endonce
