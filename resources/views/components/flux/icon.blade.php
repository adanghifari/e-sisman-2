@props(['name' => null])

@php
    $label = is_string($name) ? str_replace(['-', '_'], ' ', $name) : 'icon';
    $path = \App\Support\Icons::get($name) ?? \App\Support\Icons::FALLBACK;
@endphp

<svg {{ $attributes->class('inline-block shrink-0') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" role="img">
    {!! $path !!}
    <title>{{ $label }}</title>
</svg>
