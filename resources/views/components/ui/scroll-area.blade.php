@props([
    'as' => 'div',
    'maxHeight' => null,
])

@php
    $scrollStyle = filled($maxHeight) ? "max-height: {$maxHeight};" : null;
    $scrollAttributes = $attributes
        ->class(['app-scrollbar overflow-y-auto'])
        ->merge(['style' => $scrollStyle]);
@endphp

@if ($as === 'nav')
    <nav {{ $scrollAttributes }}>
        {{ $slot }}
    </nav>
@else
    <div {{ $scrollAttributes }}>
        {{ $slot }}
    </div>
@endif
