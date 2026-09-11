@props([
    'secondaryLabel' => 'Batal',
    'secondaryAction' => null,
    'primaryLabel' => 'Save',
    'primaryAction' => null,
    'primaryType' => 'button',
    'align' => 'end',
])

@php
    $layoutClass = [
        'between' => 'justify-between',
        'end' => 'justify-end',
    ][$align] ?? 'justify-end';
@endphp

<div {{ $attributes->class(['shrink-0 flex gap-2 border-t border-slate-100 px-8 py-4', $layoutClass]) }}>
    @if ($secondaryAction)
        <x-ui.action-button type="button" variant="secondary" wire:click="{{ $secondaryAction }}">
            {{ $secondaryLabel }}
        </x-ui.action-button>
    @endif

    @if ($primaryAction)
        <x-ui.action-button type="{{ $primaryType }}" wire:click="{{ $primaryAction }}">
            {{ $primaryLabel }}
        </x-ui.action-button>
    @else
        <x-ui.action-button type="{{ $primaryType }}">
            {{ $primaryLabel }}
        </x-ui.action-button>
    @endif
</div>
