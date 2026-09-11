@props([
    'title',
    'buttonLabel' => null,
    'tone' => 'slate',
    'badge' => null,
])

@php
    $cardClasses = match ($tone ?? 'slate') {
        'sky' => 'border-sky-100 bg-sky-50/40',
        default => 'border-slate-200 bg-white',
    };

    $buttonClasses = match ($tone ?? 'slate') {
        'sky' => 'border-sky-200 bg-white text-sky-700 hover:border-sky-300 hover:bg-sky-50 focus:ring-sky-200',
        default => 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50 focus:ring-slate-200',
    };
@endphp

<div class="rounded-lg border px-4 py-4 {{ $cardClasses }}" data-document-upload>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <span class="min-w-0">
            <span class="block text-base font-bold text-slate-900">{{ $title }}</span>

            @if ($badge)
                <span class="mt-2 inline-flex rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                    {{ $badge }}
                </span>
            @endif
        </span>

        @if ($buttonLabel)
            <button
                type="button"
                class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border px-4 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 {{ $buttonClasses }}"
                data-document-upload-trigger
                aria-expanded="true"
            >
                {{ $buttonLabel }}
            </button>
        @endif
    </div>

    <div class="mt-4" data-document-upload-panel>
        {{ $slot }}
    </div>
</div>
