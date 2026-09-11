@props([
    'label',
    'tone' => 'sky',
])

@php
    $classes = [
        'amber' => 'bg-amber-50 text-amber-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'red' => 'bg-red-50 text-red-700',
        'slate' => 'bg-slate-100 text-slate-700',
        'sky' => 'bg-sky-50 text-sky-700',
    ][$tone] ?? 'bg-sky-50 text-sky-700';
@endphp

<span {{ $attributes->class(['rounded-md px-2.5 py-1 text-xs font-semibold', $classes]) }}>
    {{ $label }}
</span>
