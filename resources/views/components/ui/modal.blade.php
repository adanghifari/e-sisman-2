@props([
    'title',
    'description' => null,
    'closeAction' => null,
    'maxWidth' => '2xl',
    'clip' => true,
])

@php
    $widthClass = [
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
    ][$maxWidth] ?? 'max-w-2xl';
    $titleId = 'modal-title-'.str()->uuid();
@endphp

<div class="app-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-6">
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        tabindex="-1"
        {{ $attributes->class([
            'flex max-h-[calc(100vh-3rem)] w-full flex-col rounded-lg bg-white shadow-xl',
            'overflow-hidden' => $clip,
            'overflow-visible' => ! $clip,
            $widthClass,
        ]) }}
    >
        <div class="shrink-0 flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <h2 id="{{ $titleId }}" class="text-lg font-semibold text-slate-900">{{ $title }}</h2>

                @if ($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>

            @if ($closeAction)
                <x-ui.icon-button
                    icon="x-mark"
                    label="Tutup"
                    variant="ghost"
                    wire:click="{{ $closeAction }}"
                />
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
