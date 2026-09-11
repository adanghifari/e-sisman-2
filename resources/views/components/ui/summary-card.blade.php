@props([
    'label',
    'value',
    'hint' => null,
    'badge' => null,
])

<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-3 text-3xl font-bold text-slate-950">{{ $value }}</p>
        </div>

        @if ($badge)
            <x-ui.status-badge :label="$badge" />
        @endif
    </div>

    @if ($hint)
        <p class="mt-3 text-sm text-slate-500">{{ $hint }}</p>
    @endif
</div>
