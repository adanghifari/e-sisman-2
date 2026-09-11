@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
])

@php
    $classes = [
        'primary' => 'bg-sky-600 text-white shadow-sm hover:bg-sky-700',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
    ][$variant] ?? 'bg-sky-600 text-white shadow-sm hover:bg-sky-700';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold transition', $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold transition', $classes]) }}>
        {{ $slot }}
    </button>
@endif
