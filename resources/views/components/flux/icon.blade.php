@props(['name' => null])

@php
    $label = is_string($name) ? str_replace(['-', '_'], ' ', $name) : 'icon';
@endphp

<span {{ $attributes->class('inline-flex shrink-0 items-center justify-center') }} aria-hidden="true">
    <span class="block h-2 w-2 rounded-full bg-current"></span>
    <span class="sr-only">{{ $label }}</span>
</span>