@props([
    'href' => null,
    'icon',
    'label',
    'type' => 'button',
    'variant' => 'secondary',
    'size' => 'md',
])

@php
    $baseClass = 'grid shrink-0 place-items-center rounded-md transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2';
    $sizeClass = [
        'sm' => 'size-8',
        'md' => 'size-9',
    ][$size] ?? 'size-9';
    $variantClass = [
        'ghost' => 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
        'secondary' => 'border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-sky-700',
    ][$variant] ?? 'border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-sky-700';
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        aria-label="{{ $label }}"
        title="{{ $label }}"
        {{ $attributes->class([$baseClass, $sizeClass, $variantClass]) }}
    >
        <x-flux.icon :name="$icon" class="size-4 transition" />
    </a>
@else
    <button
        type="{{ $type }}"
        aria-label="{{ $label }}"
        title="{{ $label }}"
        {{ $attributes->class([$baseClass, $sizeClass, $variantClass]) }}
    >
        <x-flux.icon :name="$icon" class="size-4 transition" />
    </button>
@endif
