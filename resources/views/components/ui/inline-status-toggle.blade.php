@props([
    'active' => false,
    'activeText' => 'Active',
    'inactiveText' => 'Inactive',
])

<button
    type="button"
    {{ $attributes->class(['inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50']) }}
>
    <span>{{ $active ? $activeText : $inactiveText }}</span>
    <span @class([
        'relative h-5 w-9 rounded-full transition',
        'bg-sky-600' => $active,
        'bg-slate-300' => ! $active,
    ])>
        <span @class([
            'absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow-sm transition',
            'translate-x-4' => $active,
        ])></span>
    </span>
</button>
